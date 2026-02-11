<?php
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/openai_config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$prompt = $input['prompt'] ?? '';
$target_role = $input['target_role'] ?? 'Software Engineer';
$target_company = $input['target_company'] ?? '';
$context = $input['context'] ?? [];

if (empty($prompt)) {
    echo json_encode(['success' => false, 'error' => 'No prompt provided']);
    exit;
}

// Get student data
$student_id = $_SESSION['user_id'];

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
$stmt = $mysqli->prepare('SELECT cgpa FROM users WHERE id = ?');
$stmt->bind_param('i', $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$cgpa = $student['cgpa'] ?? 'N/A';
$stmt->close();

// Build context-aware prompt
$contextInfo = "You are a career advisor AI. Help the student with their career questions.\n\n";
$contextInfo .= "Student Profile:\n";
$contextInfo .= "- Target Role: $target_role\n";
if ($target_company) {
    $contextInfo .= "- Target Company: $target_company\n";
}
if (!empty($skills)) {
    $contextInfo .= "- Skills: " . implode(', ', $skills) . "\n";
}
if (!empty($projects)) {
    $contextInfo .= "- Projects: " . implode(', ', $projects) . "\n";
}
$contextInfo .= "- CGPA: $cgpa\n\n";
$contextInfo .= "Question: $prompt\n\n";
$contextInfo .= "Provide a helpful, specific, and actionable response.";

// API Configuration - OpenAI
if (!isOpenAIConfigured()) {
    echo json_encode(['success' => false, 'error' => 'OpenAI API key is not configured. Please configure it in openai_config.php']);
    exit;
}

$apiKey = getOpenAIKey();
if (!$apiKey) {
    echo json_encode(['success' => false, 'error' => 'API key not configured']);
    exit;
}
$apiUrl = 'https://api.openai.com/v1/chat/completions';
$model = 'gpt-4o-mini'; // Using same model as other features

// Prepare API request for OpenAI
$requestData = [
    'model' => $model,
    'messages' => [
        ['role' => 'user', 'content' => $contextInfo]
    ],
    'max_tokens' => 1024,
    'temperature' => 0.7
];

// Make API call using cURL
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $apiKey,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
// Enhanced SSL handling for Windows dev environments
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_CAPATH, null); // Disable CA path
curl_setopt($ch, CURLOPT_CAINFO, __DIR__ . '/cacert.pem'); // Use local CA bundle

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// Enhanced error logging
if ($curlError) {
    error_log('cURL Error for AI Service: ' . $curlError); // Log to PHP error log
    echo json_encode([
        'success' => false,
        'error' => 'Network error: ' . $curlError,
        'details' => 'Check server logs for more info'
    ]);
    exit;
}

// Check HTTP response code
if ($httpCode !== 200) {
    $errorData = json_decode($response, true);
    echo json_encode([
        'success' => false,
        'error' => 'API Error: ' . ($errorData['error']['message'] ?? 'Unknown error'),
        'http_code' => $httpCode,
        'details' => $errorData
    ]);
    exit;
}

// Parse response from OpenAI
$data = json_decode($response, true);

if (!$data) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid response from OpenAI API',
        'raw_response' => substr($response, 0, 200) // First 200 chars for debugging
    ]);
    exit;
}

if (isset($data['choices'][0]['message']['content'])) {
    $aiResponse = $data['choices'][0]['message']['content'];
    echo json_encode([
        'success' => true,
        'response' => $aiResponse
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'No response from AI',
        'raw_response' => $data
    ]);
}
