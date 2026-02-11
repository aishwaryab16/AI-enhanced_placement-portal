<?php
require_once __DIR__ . '/includes/config.php';
require_role('student');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$studentId = $_SESSION['user_id'] ?? null;
if (!$studentId) {
    echo json_encode(['success' => false, 'message' => 'Student session not found']);
    exit;
}

// Ensure projects table exists
$createTableSql = "
    CREATE TABLE IF NOT EXISTS student_projects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        username VARCHAR(100) NOT NULL,
        project_title VARCHAR(255) NOT NULL,
        role VARCHAR(255),
        project_url VARCHAR(500),
        description TEXT,
        skills_used TEXT,
        start_date DATE NULL,
        end_date DATE NULL,
        is_ongoing TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";
$mysqli->query($createTableSql);

// Fetch username for student
$username = '';
$stmtUser = $mysqli->prepare('SELECT username FROM users WHERE id = ?');
if ($stmtUser) {
    $stmtUser->bind_param('i', $studentId);
    $stmtUser->execute();
    $result = $stmtUser->get_result();
    if ($row = $result->fetch_assoc()) {
        $username = $row['username'] ?? '';
    }
    $stmtUser->close();
}

if (empty($username)) {
    echo json_encode(['success' => false, 'message' => 'Unable to resolve student username']);
    exit;
}

$action = $_POST['action'] ?? 'save_project';

if ($action === 'delete_project') {
    $projectId = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
    if ($projectId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid project ID']);
        exit;
    }
    $stmt = $mysqli->prepare('DELETE FROM student_projects WHERE id = ? AND student_id = ? AND username = ?');
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Failed to prepare delete statement']);
        exit;
    }
    $stmt->bind_param('iis', $projectId, $studentId, $username);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Project deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete project']);
    }
    $stmt->close();
    exit;
}

$projectId = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
$title = trim($_POST['title'] ?? '');
$role = trim($_POST['role'] ?? '');
$url = trim($_POST['url'] ?? '');
$startDate = trim($_POST['start_date'] ?? '');
$endDate = trim($_POST['end_date'] ?? '');
$isOngoing = isset($_POST['is_ongoing']) ? (int)$_POST['is_ongoing'] : 0;
$description = trim($_POST['description'] ?? '');
$skills = trim($_POST['skills'] ?? '');

if ($title === '') {
    echo json_encode(['success' => false, 'message' => 'Project title is required']);
    exit;
}

// Normalize dates
$startDate = $startDate !== '' ? $startDate : null;
$endDate = $isOngoing ? null : ($endDate !== '' ? $endDate : null);

if ($projectId > 0) {
    $stmt = $mysqli->prepare('
        UPDATE student_projects
        SET project_title = ?, role = ?, project_url = ?, description = ?, skills_used = ?, start_date = ?, end_date = ?, is_ongoing = ?
        WHERE id = ? AND student_id = ? AND username = ?
    ');
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Failed to prepare update statement']);
        exit;
    }
    $stmt->bind_param(
        'sssssssiiis',
        $title,
        $role,
        $url,
        $description,
        $skills,
        $startDate,
        $endDate,
        $isOngoing,
        $projectId,
        $studentId,
        $username
    );
} else {
    $stmt = $mysqli->prepare('
        INSERT INTO student_projects (student_id, username, project_title, role, project_url, description, skills_used, start_date, end_date, is_ongoing)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Failed to prepare insert statement']);
        exit;
    }
    $stmt->bind_param(
        'issssssssi',
        $studentId,
        $username,
        $title,
        $role,
        $url,
        $description,
        $skills,
        $startDate,
        $endDate,
        $isOngoing
    );
}

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
}

$stmt->close();

