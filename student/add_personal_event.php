<?php
require_once __DIR__ . '/../includes/config.php';
require_role('student');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = $_SESSION['user_id'];
    $event_title = $_POST['event_title'] ?? '';
    $event_date = $_POST['event_date'] ?? '';
    $event_time = $_POST['event_time'] ?? null;
    $event_type = $_POST['event_type'] ?? 'other';
    $location = $_POST['location'] ?? '';
    $description = $_POST['description'] ?? '';
    
    // Check if events table exists
    $checkTable = $mysqli->query("SHOW TABLES LIKE 'events'");
    if ($checkTable && $checkTable->num_rows == 0) {
        // Create events table if it doesn't exist
        $createTable = "CREATE TABLE events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_title VARCHAR(255) NOT NULL,
            event_date DATE NOT NULL,
            event_time TIME,
            event_type ENUM('interview', 'deadline', 'workshop', 'placement', 'other') DEFAULT 'other',
            location VARCHAR(255),
            description TEXT,
            created_by INT,
            is_personal BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $mysqli->query($createTable);
    }
    
    // Insert personal event
    $stmt = $mysqli->prepare("INSERT INTO events (event_title, event_date, event_time, event_type, location, description, created_by, is_personal) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
    $stmt->bind_param('ssssssi', $event_title, $event_date, $event_time, $event_type, $location, $description, $student_id);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = 'Event added successfully!';
    } else {
        $_SESSION['error_message'] = 'Failed to add event: ' . $stmt->error;
    }
    $stmt->close();
}

header('Location: calendar.php');
exit;
?>
