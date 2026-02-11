<?php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json');
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$status = $_POST['status'] ?? '';
$valid_statuses = ['Applied','Shortlisted','Interviewed','Selected','Rejected'];
if ($id <= 0 || !in_array($status, $valid_statuses, true)) {
    echo json_encode(['success'=>false,'error'=>'Invalid request']);exit;
}
$stmt = $mysqli->prepare("UPDATE internship_applications SET application_status=? WHERE id=?");
if (!$stmt) { echo json_encode(['success'=>false,'error'=>'Query error']);exit; }
$stmt->bind_param('si',$status,$id);
$ok = $stmt->execute();
$stmt->close();
echo json_encode(['success'=>$ok, 'error'=>$ok?'':$mysqli->error]);
