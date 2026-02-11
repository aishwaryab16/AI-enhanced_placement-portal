<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/job_backend.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$student_id = (int)$_SESSION['user_id'];

$input = json_decode(file_get_contents('php://input'), true);
$interview_id = isset($input['interview_id']) ? (int)$input['interview_id'] : null;
$company = trim($input['company'] ?? '');
$job_role = trim($input['job_role'] ?? '');
$score = isset($input['score']) ? (int)$input['score'] : null;
$feedback = trim($input['feedback'] ?? '');
$strengths = isset($input['strengths']) && is_array($input['strengths']) ? implode(', ', $input['strengths']) : '';
$weaknesses = isset($input['weaknesses']) && is_array($input['weaknesses']) ? implode(', ', $input['weaknesses']) : '';
$conversation = $input['conversation'] ?? [];

if ($company === '' || $job_role === '') {
    echo json_encode(['success' => false, 'error' => 'Missing company or role']);
    exit;
}

$mysqli = $GLOBALS['mysqli'] ?? new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
setupJobTables($mysqli);

// Enforce single final mock attempt per student + interview
if ($interview_id !== null) {
    $check = $mysqli->prepare("SELECT id FROM final_mock_interview_results WHERE student_id = ? AND interview_id = ? LIMIT 1");
    if ($check) {
        $check->bind_param('ii', $student_id, $interview_id);
        $check->execute();
        $existing = $check->get_result()->fetch_assoc();
        $check->close();
        if ($existing) {
            echo json_encode(['success' => false, 'error' => 'Final mock already taken for this interview']);
            exit;
        }
    }
}

$conversation_json = json_encode($conversation);

$stmt = $mysqli->prepare("INSERT INTO final_mock_interview_results (student_id, interview_id, company, job_role, score, feedback, strengths, weaknesses, conversation) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'DB error: ' . $mysqli->error]);
    exit;
}

// Allow NULL for interview_id
if ($interview_id === 0) {
    $interview_id = null;
}

$stmt->bind_param(
    'ississsss',
    $student_id,
    $interview_id,
    $company,
    $job_role,
    $score,
    $feedback,
    $strengths,
    $weaknesses,
    $conversation_json
);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to save result']);
}

$stmt->close();

