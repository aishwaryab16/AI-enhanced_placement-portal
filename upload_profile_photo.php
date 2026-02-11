<?php
require_once __DIR__ . '/includes/config.php';
require_role('student');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (empty($_FILES['profile_photo']) || $_FILES['profile_photo']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No photo uploaded or upload error']);
    exit;
}

$file = $_FILES['profile_photo'];
$maxSize = 5 * 1024 * 1024; // 5MB
$allowedTypes = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'image/webp' => 'webp'
];

if ($file['size'] > $maxSize) {
    echo json_encode(['success' => false, 'message' => 'File too large. Maximum size is 5MB.']);
    exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!isset($allowedTypes[$mimeType])) {
    echo json_encode(['success' => false, 'message' => 'Unsupported file type.']);
    exit;
}

$studentId = $_SESSION['user_id'] ?? null;
if (!$studentId) {
    echo json_encode(['success' => false, 'message' => 'Student session not found']);
    exit;
}

// Make sure upload directory exists
$uploadDir = __DIR__ . '/uploads/profile_photos/';
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        echo json_encode(['success' => false, 'message' => 'Failed to create upload directory']);
        exit;
    }
}

$extension = $allowedTypes[$mimeType];
$filename = 'profile_' . $studentId . '_' . time() . '.' . $extension;
$destination = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $destination)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file']);
    exit;
}

// Build relative URL (accessible from student/profile.php)
$relativePath = '../uploads/profile_photos/' . $filename;

$stmt = $mysqli->prepare('UPDATE users SET profile_photo = ? WHERE id = ?');
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Failed to prepare update statement']);
    exit;
}
$stmt->bind_param('si', $relativePath, $studentId);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'photo_url' => $relativePath]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update profile photo']);
}

$stmt->close();

