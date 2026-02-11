<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$interview_id = isset($input['interview_id']) ? (int)$input['interview_id'] : 0;
$student_id = isset($input['student_id']) ? (int)$input['student_id'] : $_SESSION['user_id'];
$company = $input['company'] ?? '';
$job_role = $input['job_role'] ?? '';
$round_name = $input['round_name'] ?? '';
$score = isset($input['score']) ? (int)$input['score'] : null;
$round_results = isset($input['round_results']) ? json_encode($input['round_results']) : null;
$status = $input['status'] ?? 'completed';

// Only allow completed interviews to be saved
if ($status !== 'completed') {
    echo json_encode(['success' => false, 'error' => 'Only completed interviews can be saved']);
    exit;
}

if (!$interview_id || !$student_id || !$company || !$job_role || !$round_name || $score === null) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

$mysqli = $GLOBALS['mysqli'] ?? new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

// Ensure table exists
require_once __DIR__ . '/../includes/job_backend.php';
setupJobTables($mysqli);

// Check if attendance record exists for this completed round
$check_stmt = $mysqli->prepare("SELECT id, completed_rounds, total_rounds FROM interview_attendance WHERE interview_id = ? AND student_id = ? AND round_name = ?");
if (!$check_stmt) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit;
}

$check_stmt->bind_param('iis', $interview_id, $student_id, $round_name);
$check_stmt->execute();
$existing = $check_stmt->get_result()->fetch_assoc();
$check_stmt->close();

if ($existing) {
    // Update existing record - only if it's being marked as completed
    $completed_rounds = (int)$existing['completed_rounds'] + 1;
    $total_rounds = (int)$existing['total_rounds'];
    
    $update_stmt = $mysqli->prepare("UPDATE interview_attendance SET 
        completed_at = NOW(),
        score = ?,
        completed_rounds = ?,
        round_results = ?,
        status = 'completed'
        WHERE id = ?");
    
    if ($update_stmt) {
        $update_stmt->bind_param('iisi', $score, $completed_rounds, $round_results, $existing['id']);
        $update_stmt->execute();
        $update_stmt->close();
        echo json_encode(['success' => true, 'message' => 'Interview attendance updated']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Update failed']);
    }
} else {
    // Create new record - only for completed interviews
    $total_rounds = 1;
    // Try to get total rounds from interviews table
    $rounds_stmt = $mysqli->prepare("SELECT interview_rounds FROM interviews WHERE id = ?");
    if ($rounds_stmt) {
        $rounds_stmt->bind_param('i', $interview_id);
        $rounds_stmt->execute();
        $interview_data = $rounds_stmt->get_result()->fetch_assoc();
        $rounds_stmt->close();
        
        if (!empty($interview_data['interview_rounds'])) {
            $rounds_array = json_decode($interview_data['interview_rounds'], true);
            if (is_array($rounds_array)) {
                $total_rounds = count($rounds_array);
            }
        }
    }
    
    $insert_stmt = $mysqli->prepare("INSERT INTO interview_attendance 
        (interview_id, student_id, company, job_role, round_name, started_at, completed_at, score, total_rounds, completed_rounds, round_results, status) 
        VALUES (?, ?, ?, ?, ?, NOW(), NOW(), ?, ?, 1, ?, 'completed')");
    
    if ($insert_stmt) {
        $insert_stmt->bind_param('iisssiis', $interview_id, $student_id, $company, $job_role, $round_name, $score, $total_rounds, $round_results);
        $insert_stmt->execute();
        $insert_stmt->close();
        echo json_encode(['success' => true, 'message' => 'Interview attendance recorded']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Insert failed']);
    }
}

// Also update the main interviews table score if all rounds are completed
if ($score !== null) {
    $interview_stmt = $mysqli->prepare("SELECT current_round_index, interview_rounds FROM interviews WHERE id = ?");
    if ($interview_stmt) {
        $interview_stmt->bind_param('i', $interview_id);
        $interview_stmt->execute();
        $interview_data = $interview_stmt->get_result()->fetch_assoc();
        $interview_stmt->close();
        
        if ($interview_data) {
            $current_index = (int)$interview_data['current_round_index'];
            $rounds_array = [];
            if (!empty($interview_data['interview_rounds'])) {
                $rounds_array = json_decode($interview_data['interview_rounds'], true);
            }
            $total_rounds = is_array($rounds_array) ? count($rounds_array) : 1;
            
            // If all rounds completed, calculate average score
            if ($current_index >= $total_rounds) {
                $avg_score_stmt = $mysqli->prepare("SELECT AVG(score) as avg_score FROM interview_attendance WHERE interview_id = ? AND score IS NOT NULL AND status = 'completed'");
                if ($avg_score_stmt) {
                    $avg_score_stmt->bind_param('i', $interview_id);
                    $avg_score_stmt->execute();
                    $avg_result = $avg_score_stmt->get_result()->fetch_assoc();
                    $avg_score_stmt->close();
                    
                    if ($avg_result && $avg_result['avg_score'] !== null) {
                        $avg_score = (int)round($avg_result['avg_score']);
                        $update_interview_stmt = $mysqli->prepare("UPDATE interviews SET score = ? WHERE id = ?");
                        if ($update_interview_stmt) {
                            $update_interview_stmt->bind_param('ii', $avg_score, $interview_id);
                            $update_interview_stmt->execute();
                            $update_interview_stmt->close();
                        }
                    }
                }
            }
        }
    }
}
?>

