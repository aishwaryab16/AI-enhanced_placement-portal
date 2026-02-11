<?php
require_once __DIR__ . '/includes/config.php';
require_role('student');

header('Content-Type: application/json');

$student_id = $_SESSION['user_id'];
$response = ['success' => false, 'message' => ''];

// Get student username
$username = '';
$stmt = $mysqli->prepare('SELECT username FROM users WHERE id = ?');
if ($stmt) {
    $stmt->bind_param('i', $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $username = $row['username'] ?? '';
    }
    $stmt->close();
}

if (empty($username)) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

// Create student_projects table if it doesn't exist
$mysqli->query("CREATE TABLE IF NOT EXISTS student_projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    project_title VARCHAR(255) NOT NULL,
    role VARCHAR(255),
    project_url VARCHAR(500),
    start_date DATE,
    end_date DATE,
    is_ongoing TINYINT(1) DEFAULT 0,
    description TEXT,
    skills_used TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_project') {
    $project_id = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
    $title = trim($_POST['title'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $url = trim($_POST['url'] ?? '');
    $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
    $is_ongoing = isset($_POST['is_ongoing']) ? (int)$_POST['is_ongoing'] : 0;
    $description = trim($_POST['description'] ?? '');
    $skills = trim($_POST['skills'] ?? '');
    
    if (empty($title)) {
        $response['message'] = 'Project title is required';
        echo json_encode($response);
        exit;
    }
    
    if ($project_id > 0) {
        // Update existing project
        $stmt = $mysqli->prepare("UPDATE student_projects SET 
            project_title = ?, 
            role = ?, 
            project_url = ?, 
            start_date = ?, 
            end_date = ?, 
            is_ongoing = ?, 
            description = ?, 
            skills_used = ? 
            WHERE id = ? AND username = ?");
        
        if ($stmt) {
            $stmt->bind_param('sssssisssi', $title, $role, $url, $start_date, $end_date, $is_ongoing, $description, $skills, $project_id, $username);
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Project updated successfully';
            } else {
                $response['message'] = 'Failed to update project: ' . $stmt->error;
            }
            $stmt->close();
        } else {
            $response['message'] = 'Database error: ' . $mysqli->error;
        }
    } else {
        // Insert new project
        $stmt = $mysqli->prepare("INSERT INTO student_projects 
            (username, project_title, role, project_url, start_date, end_date, is_ongoing, description, skills_used) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        if ($stmt) {
            $stmt->bind_param('sssssisss', $username, $title, $role, $url, $start_date, $end_date, $is_ongoing, $description, $skills);
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Project saved successfully';
            } else {
                $response['message'] = 'Failed to save project: ' . $stmt->error;
            }
            $stmt->close();
        } else {
            $response['message'] = 'Database error: ' . $mysqli->error;
        }
    }
} else {
    $response['message'] = 'Invalid request';
}

echo json_encode($response);
?>

