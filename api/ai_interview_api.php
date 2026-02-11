<?php
// Suppress all errors and warnings to prevent HTML output
error_reporting(0);
ini_set('display_errors', 0);

// Start output buffering to catch any unwanted output
ob_start();

try {
    // Don't load config.php as it might output HTML errors
    // Instead, create a minimal database connection if needed
    
    header('Content-Type: application/json');
    
    // Clear any output that might have been generated
    ob_clean();
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    $message = $input['message'] ?? '';
    $resume_data = $input['resume_data'] ?? null;
    $domain_id = $input['domain_id'] ?? null;
    
    // Temporary hardcoded key for testing (revert to getenv after)
    $apiKey = 'YOUR_OPENAI_API_KEY_HERE';
    
    // Check if API key is valid
    if (!$apiKey || strlen($apiKey) < 20) {
        ob_end_clean();
        echo json_encode([
            'reply' => '🔧 <strong>OpenAI API Setup Required</strong><br><br>Please configure your OpenAI API key.',
            'error' => 'API key not configured'
        ]);
        exit;
    }
    
    // Check if domain_id is provided
    if (!$domain_id) {
        ob_end_clean();
        echo json_encode([
            'reply' => 'Please select an interview domain first.',
            'error' => 'No domain selected'
        ]);
        exit;
    }
    
    $reply = generateResumeBasedReply($message, $resume_data, $apiKey, $domain_id);
    ob_end_clean();
    echo json_encode(['reply' => $reply]);
    
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode([
        'reply' => 'I apologize, but I\'m having trouble connecting to the AI service right now. Please try again in a moment.<br><br><small>Error: ' . $e->getMessage() . '</small>',
        'error' => $e->getMessage()
    ]);
} catch (Error $e) {
    ob_end_clean();
    echo json_encode([
        'reply' => 'System error occurred. Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine(),
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}

function generateResumeBasedReply($message, $resume_data, $apiKey, $domain_id) {
    // Validate API key before proceeding
    if (empty($apiKey) || strlen($apiKey) < 20) {
        throw new Exception("Invalid API key provided to function");
    }
    
    // Connect to database using PDO (aligned with config.php)
    try {
        $pdo = new PDO(
            "mysql:host=localhost;port=3306;dbname=placements;charset=utf8mb4",
            "root",
            "",
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 5
            ]
        );
        
        // Fetch the domain prompt template
        $stmt = $pdo->prepare("SELECT prompt_template, domain_name FROM interview_domains WHERE id = ? AND is_active = 1");
        $stmt->execute([$domain_id]);
        $domain = $stmt->fetch();
        
        if (!$domain) {
            throw new Exception("Invalid or inactive interview domain selected");
        }
        
        $prompt_template = $domain['prompt_template'];
        $domain_name = $domain['domain_name'];
        
    } catch (PDOException $e) {
        throw new Exception("Database error: " . $e->getMessage());
    }
    
    // Create detailed resume context
    $resume_context = '';
    if ($resume_data) {
        $topics = implode(', ', array_filter($resume_data['recent_topics'] ?? []));
        $quiz_details = '';
        if (!empty($resume_data['quiz_performance'])) {
            foreach ($resume_data['quiz_performance'] as $quiz) {
                $quiz_details .= "- {$quiz['chapter_title']}: {$quiz['score']}%\n";
            }
        }
        
        $resume_context = "
STUDENT ACADEMIC PROFILE:
=========================
Name: {$resume_data['name']}
Performance Level: {$resume_data['performance_level']}
Overall Average Score: {$resume_data['avg_score']}%
Total Quizzes Completed: {$resume_data['total_quizzes']}
Chapters/Topics Studied: {$topics}
Current Skill Level: {$resume_data['skill_level']}

RECENT QUIZ PERFORMANCE:
{$quiz_details}

LEARNING AREAS:
{$topics}
";
    }
    
    // Replace placeholders in the prompt template
    $system_prompt = str_replace('{resume_context}', $resume_context, $prompt_template);
    $system_prompt = str_replace('[ANSWER]', $message, $system_prompt);

    // Prepare conversation history (you might want to store this in session)
    $messages = [
        ['role' => 'system', 'content' => $system_prompt],
        ['role' => 'user', 'content' => $message]
    ];

    $payload = [
        'model' => defined('OPENAI_MODEL') ? OPENAI_MODEL : 'gpt-4o-mini',
        'messages' => $messages,
        'temperature' => defined('OPENAI_TEMPERATURE') ? OPENAI_TEMPERATURE : 0.7,
        'max_tokens' => defined('OPENAI_MAX_TOKENS') ? OPENAI_MAX_TOKENS : 300,
        'presence_penalty' => 0.1,
        'frequency_penalty' => 0.1
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.openai.com/v1/chat/completions',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => defined('OPENAI_TIMEOUT') ? OPENAI_TIMEOUT : 30,
        CURLOPT_CONNECTTIMEOUT => defined('OPENAI_CONNECT_TIMEOUT') ? OPENAI_CONNECT_TIMEOUT : 10,
        CURLOPT_SSL_VERIFYPEER => false, // Disable for dev
        CURLOPT_SSL_VERIFYHOST => false, // Disable for dev
        CURLOPT_CAINFO => __DIR__ . '/cacert.pem', // Use local CA bundle (download if needed)
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        throw new Exception("Network error: " . $curlError);
    }

    if ($response === false) {
        throw new Exception("Failed to get response from OpenAI API");
    }

    $data = json_decode($response, true);

    if ($httpCode !== 200) {
        $errorMsg = $data['error']['message'] ?? 'Unknown API error';
        $errorType = $data['error']['type'] ?? 'unknown';
        $errorCode = $data['error']['code'] ?? 'unknown';
        
        // Log the full error for debugging
        error_log("OpenAI API Error - HTTP: $httpCode, Type: $errorType, Code: $errorCode, Key length: " . strlen($apiKey) . ", Error: " . json_encode($data));
        
        // Provide user-friendly error messages
        if ($httpCode === 401) {
            throw new Exception("Invalid API key. Please check your OpenAI API key configuration.");
        } elseif ($httpCode === 429) {
            throw new Exception("API rate limit exceeded. Please try again in a few moments.");
        } elseif ($httpCode === 500 || $httpCode === 503) {
            throw new Exception("OpenAI service is temporarily unavailable. Please try again later.");
        } else {
            throw new Exception("OpenAI API Error (HTTP {$httpCode}): " . $errorMsg);
        }
    }

    if (!isset($data['choices'][0]['message']['content'])) {
        throw new Exception("Invalid response format from OpenAI API");
    }

    return trim($data['choices'][0]['message']['content']);
}

// Function to validate API key format (basic check)
function isValidApiKey($key) {
    return preg_match('/^sk-[a-zA-Z0-9]{48}$/', $key);
}
?>
