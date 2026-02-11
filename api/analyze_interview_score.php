<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../openai_config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$conversation = $input['conversation'] ?? [];
$company = $input['company'] ?? '';
$job_role = $input['job_role'] ?? '';
$round_name = $input['round_name'] ?? 'HR Round';

if (empty($conversation) || !is_array($conversation)) {
    echo json_encode(['success' => false, 'error' => 'No conversation data provided']);
    exit;
}

// Get student data for context
$student_id = $_SESSION['user_id'];
$mysqli = $GLOBALS['mysqli'] ?? new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

// Fetch student skills
$skills = [];
$result = $mysqli->query("SELECT skill_name FROM student_skills WHERE student_id = $student_id");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $skills[] = $row['skill_name'];
    }
}

// Fetch student projects
$projects = [];
$result = $mysqli->query("SELECT project_title FROM student_projects WHERE student_id = $student_id");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $projects[] = $row['project_title'];
    }
}

// Get CGPA
$stmt = $mysqli->prepare('SELECT cgpa, branch FROM users WHERE id = ?');
$stmt->bind_param('i', $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$cgpa = $student['cgpa'] ?? 'N/A';
$branch = $student['branch'] ?? 'N/A';
$stmt->close();

// Build conversation transcript
$conversation_text = "";
foreach ($conversation as $msg) {
    $sender = $msg['sender'] ?? 'unknown';
    $text = $msg['text'] ?? '';
    if ($sender === 'ai') {
        $conversation_text .= "Interviewer: " . $text . "\n\n";
    } else if ($sender === 'student') {
        $conversation_text .= "Candidate: " . $text . "\n\n";
    }
}

// Count student responses
$student_responses = array_filter($conversation, function($msg) {
    return ($msg['sender'] ?? '') === 'student';
});
$response_count = count($student_responses);

// Build scoring prompt
$scoring_prompt = "You are an expert interview evaluator. Analyze the following interview conversation and provide a comprehensive score (0-100) based on the candidate's performance.

**Interview Details:**
- Company: {$company}
- Job Role: {$job_role}
- Round Type: {$round_name}
- Student Branch: {$branch}
- Student CGPA: {$cgpa}
- Student Skills: " . implode(', ', $skills) . "
- Student Projects: " . implode(', ', $projects) . "

**Evaluation Criteria:**
1. **Relevance & Domain Knowledge (25 points)**: How well the candidate demonstrates understanding of the role, company, and domain-specific knowledge.
2. **Answer Quality (25 points)**: Depth, clarity, and completeness of responses. Use of examples and specific details.
3. **Communication Skills (20 points)**: Clarity, articulation, professional language, and ability to convey ideas effectively.
4. **Problem-Solving & Technical Understanding (20 points)**: For technical roles, assess technical knowledge. For HR rounds, assess behavioral competencies and situational awareness.
5. **Engagement & Participation (10 points)**: Level of engagement, number of meaningful responses, and overall participation quality.

**Interview Conversation:**
{$conversation_text}

**Instructions:**
- Provide ONLY a JSON response with this exact format:
{
  \"score\": <number between 0-100>,
  \"breakdown\": {
    \"relevance\": <score out of 25>,
    \"answer_quality\": <score out of 25>,
    \"communication\": <score out of 20>,
    \"technical_understanding\": <score out of 20>,
    \"engagement\": <score out of 10>
  },
  \"feedback\": \"<brief constructive feedback in 2-3 sentences>\",
  \"strengths\": [\"<strength1>\", \"<strength2>\"],
  \"weaknesses\": [\"<weakness1>\", \"<weakness2>\"]
}

- The total score should be the sum of all breakdown scores.
- Be fair but thorough in your evaluation.
- Consider the round type (HR, Technical, Aptitude) when scoring.
- If the candidate gave very few or very short responses, penalize accordingly.
- If responses show strong domain knowledge and company awareness, reward accordingly.";

// API Configuration
if (!isOpenAIConfigured()) {
    echo json_encode(['success' => false, 'error' => 'OpenAI API key is not configured']);
    exit;
}

$apiKey = getOpenAIKey();
if (!$apiKey) {
    echo json_encode(['success' => false, 'error' => 'API key not configured']);
    exit;
}

$apiUrl = 'https://api.openai.com/v1/chat/completions';
$model = 'gpt-4o-mini';

// Prepare API request
$requestData = [
    'model' => $model,
    'messages' => [
        ['role' => 'system', 'content' => 'You are an expert interview evaluator. Always respond with valid JSON only.'],
        ['role' => 'user', 'content' => $scoring_prompt]
    ],
    'max_tokens' => 800,
    'temperature' => 0.3 // Lower temperature for more consistent scoring
];

// Make API call
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $apiKey,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    error_log('cURL Error for Interview Scoring: ' . $curlError);
    echo json_encode([
        'success' => false,
        'error' => 'Network error: ' . $curlError
    ]);
    exit;
}

if ($httpCode !== 200) {
    $errorData = json_decode($response, true);
    echo json_encode([
        'success' => false,
        'error' => 'API Error: ' . ($errorData['error']['message'] ?? 'Unknown error'),
        'http_code' => $httpCode
    ]);
    exit;
}

// Parse response
$data = json_decode($response, true);

if (!$data || !isset($data['choices'][0]['message']['content'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid response from AI',
        'raw_response' => substr($response, 0, 200)
    ]);
    exit;
}

$aiResponse = $data['choices'][0]['message']['content'];

// Extract JSON from response (handle cases where AI adds extra text)
$jsonStart = strpos($aiResponse, '{');
$jsonEnd = strrpos($aiResponse, '}');
if ($jsonStart !== false && $jsonEnd !== false) {
    $jsonString = substr($aiResponse, $jsonStart, $jsonEnd - $jsonStart + 1);
    $scoreData = json_decode($jsonString, true);
    
    if ($scoreData && isset($scoreData['score'])) {
        // Validate and sanitize score
        $score = (int)round($scoreData['score']);
        $score = max(0, min(100, $score)); // Clamp between 0-100
        
        echo json_encode([
            'success' => true,
            'score' => $score,
            'breakdown' => $scoreData['breakdown'] ?? [],
            'feedback' => $scoreData['feedback'] ?? '',
            'strengths' => $scoreData['strengths'] ?? [],
            'weaknesses' => $scoreData['weaknesses'] ?? []
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid score format from AI',
            'raw_response' => $aiResponse
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Could not parse JSON from AI response',
        'raw_response' => $aiResponse
    ]);
}
?>

