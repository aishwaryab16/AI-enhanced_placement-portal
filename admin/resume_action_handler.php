<?php
require_once __DIR__ . '/../includes/config.php';
require_role('admin');

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$resume_id = $_POST['resume_id'] ?? 0;

if (!$action || !$resume_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

// Check if table exists
$checkTable = $mysqli->query("SHOW TABLES LIKE 'resume_submissions'");
if (!$checkTable || $checkTable->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Resume submissions table not found']);
    exit;
}

if ($action === 'approve') {
    $admin_feedback = $_POST['feedback'] ?? '';
    
    $stmt = $mysqli->prepare("UPDATE resume_submissions SET status = 'approved', admin_feedback = ?, reviewed_at = NOW() WHERE id = ?");
    $stmt->bind_param('si', $admin_feedback, $resume_id);
    
    if ($stmt->execute()) {
        // TODO: Send email notification to student
        echo json_encode(['success' => true, 'message' => 'Resume approved successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
    }
    $stmt->close();
    
} elseif ($action === 'reject') {
    $rejection_reason = $_POST['reason'] ?? '';
    
    if (empty($rejection_reason)) {
        echo json_encode(['success' => false, 'message' => 'Rejection reason is required']);
        exit;
    }
    
    $stmt = $mysqli->prepare("UPDATE resume_submissions SET status = 'rejected', admin_feedback = ?, reviewed_at = NOW() WHERE id = ?");
    $stmt->bind_param('si', $rejection_reason, $resume_id);
    
    if ($stmt->execute()) {
        // TODO: Send email notification to student
        echo json_encode(['success' => true, 'message' => 'Resume rejected']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
    }
    $stmt->close();
    
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>
