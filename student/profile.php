<?php
require_once __DIR__ . '/../includes/config.php';
require_role('student');

$student_id = $_SESSION['user_id'];
$student_username = '';
// Fetch username early so POST handlers can use it
try {
    $u_stmt = $mysqli->prepare('SELECT username FROM users WHERE id = ?');
    if ($u_stmt) {
        $u_stmt->bind_param('i', $student_id);
        $u_stmt->execute();
        $u_res = $u_stmt->get_result();
        if ($row = $u_res->fetch_assoc()) { $student_username = $row['username'] ?? ''; }
        $u_stmt->close();
    }
} catch (Exception $e) { /* ignore, will fallback to later fetch */ }
$success_message = '';
$error_message = '';

// Ensure self-introduction column exists
$self_intro_column = $mysqli->query("SHOW COLUMNS FROM users LIKE 'self_intro_video'");
if ($self_intro_column && $self_intro_column->num_rows === 0) {
    $mysqli->query("ALTER TABLE users ADD COLUMN self_intro_video TEXT NULL");
}

// Handle form submission
// Note: Personal Info and Professional Info are read-only (GET only)
// Only Skills, Projects, Areas of Interest, About You, and Profile Photo can be updated (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_additional') {
        // GitHub / LinkedIn links are stored in a dedicated additional info table
        $github_link = trim($_POST['github_link'] ?? '');
        $linkedin_link = trim($_POST['linkedin_link'] ?? '');

        // Ensure additional info table exists
        $mysqli->query("CREATE TABLE IF NOT EXISTS student_additional_info (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            github_link VARCHAR(255) DEFAULT NULL,
            linkedin_link VARCHAR(255) DEFAULT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_student (student_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $stmt = $mysqli->prepare('INSERT INTO student_additional_info (student_id, github_link, linkedin_link)
                                  VALUES (?, ?, ?)
                                  ON DUPLICATE KEY UPDATE github_link = VALUES(github_link), linkedin_link = VALUES(linkedin_link)');
        if ($stmt) {
            $stmt->bind_param('iss', $student_id, $github_link, $linkedin_link);
            if ($stmt->execute()) {
                $success_message = 'Additional info updated successfully!';
            } else {
                $error_message = 'Failed to update additional info.';
            }
            $stmt->close();
        } else {
            $error_message = 'Failed to prepare additional info update.';
        }
    } elseif ($_POST['action'] === 'update_about') {
        $about_text = $_POST['about_text'] ?? '';
        
        $stmt = $mysqli->prepare('UPDATE users SET bio = ? WHERE id = ?');
        $stmt->bind_param('si', $about_text, $student_id);
        if ($stmt->execute()) {
            $success_message = 'About section updated successfully!';
        } else {
            $error_message = 'Failed to update about section.';
        }
        $stmt->close();
    } elseif ($_POST['action'] === 'update_interests') {
        $interests_json = $_POST['interests'] ?? '[]';
        $username = $student_username ?: ($student['username'] ?? '');
        
        // Validate JSON
        $interests_array = json_decode($interests_json, true);
        if (json_last_error() === JSON_ERROR_NONE && !empty($username)) {
            // Check if student_interests table exists
            $check_table = $mysqli->query("SHOW TABLES LIKE 'student_interests'");
            if ($check_table->num_rows === 0) {
                $mysqli->query("CREATE TABLE IF NOT EXISTS student_interests (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    username VARCHAR(100) NOT NULL,
                    interest_name VARCHAR(100) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )");
            }

            // Delete existing interests for this user
            $delete_stmt = $mysqli->prepare('DELETE FROM student_interests WHERE username = ?');
            if ($delete_stmt) {
                $delete_stmt->bind_param('s', $username);
                $delete_stmt->execute();
                $delete_stmt->close();
            }
            
            // Insert new interests
            $insert_stmt = $mysqli->prepare('INSERT INTO student_interests (username, interest_name) VALUES (?, ?)');
            if ($insert_stmt) {
                $inserted = 0;
                foreach ($interests_array as $interest) {
                    $interest = trim($interest);
                    if (!empty($interest)) {
                        $insert_stmt->bind_param('ss', $username, $interest);
                        if ($insert_stmt->execute()) {
                            $inserted++;
                        }
                    }
                }
                $insert_stmt->close();
                
                if ($inserted > 0) {
                    $success_message = 'Interests updated successfully!';
                } else {
                    $success_message = 'Interests cleared successfully!';
                }
            } else {
                $error_message = 'Failed to prepare interests insert statement.';
            }
        } else {
            $error_message = 'Invalid interests data format or username not found.';
        }
    } elseif ($_POST['action'] === 'update_skills') {
        // Update existing and add new skills by username
        $username = $student_username ?: ($student['username'] ?? '');
        if (!empty($username)) {
            // Update existing skill names
            foreach ($_POST as $key => $value) {
                if (strpos($key, 'skill_name_') === 0) {
                    $index = substr($key, strlen('skill_name_'));
                    $skill_id = $_POST['skill_id_' . $index] ?? '';
                    $skill_name = trim($value);
                    if ($skill_id && $skill_name !== '') {
                        $stmt = $mysqli->prepare('UPDATE student_skills SET skill_name = ? WHERE id = ? AND username = ?');
                        if ($stmt) {
                            $skill_id_int = (int)$skill_id; // bind_param needs variables by reference
                            $stmt->bind_param('sis', $skill_name, $skill_id_int, $username);
                            $stmt->execute();
                            $stmt->close();
                        }
                    }
                }
            }

            // Add new skill if provided
            $new_skill_name = trim($_POST['new_skill_name'] ?? '');
            $new_skill_proficiency = $_POST['new_skill_proficiency'] ?? 'intermediate';
            if ($new_skill_name !== '') {
                $stmt = $mysqli->prepare('INSERT INTO student_skills (student_id, username, skill_name, proficiency_level) VALUES (?, ?, ?, ?)');
                if ($stmt) {
                    $stmt->bind_param('isss', $student_id, $username, $new_skill_name, $new_skill_proficiency);
                    $stmt->execute();
                    $stmt->close();
                }
            }

            $success_message = 'Skills updated successfully!';
        } else {
            $error_message = 'Unable to update skills: username missing.';
        }
    } elseif ($_POST['action'] === 'update_achievements') {
        // Save achievements into dedicated table per user
        $username = $student_username ?: ($student['username'] ?? '');
        if (!empty($username)) {
            // Ensure achievements table exists
            $mysqli->query("CREATE TABLE IF NOT EXISTS student_achievements (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(100) NOT NULL,
                achievement_text TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_username (username)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // Completely replace the user's achievements with non-empty rows from the form
            $del = $mysqli->prepare('DELETE FROM student_achievements WHERE username = ?');
            if ($del) {
                $del->bind_param('s', $username);
                $del->execute();
                $del->close();
            }

            $items = [];
            for ($i = 0; $i < 10; $i++) {
                $val = trim($_POST['achievement_' . $i] ?? '');
                if ($val !== '') { $items[] = $val; }
            }

            if (!empty($items)) {
                $ins = $mysqli->prepare('INSERT INTO student_achievements (username, achievement_text) VALUES (?, ?)');
                if ($ins) {
                    foreach ($items as $txt) {
                        $ins->bind_param('ss', $username, $txt);
                        $ins->execute();
                    }
                    $ins->close();
                }
            }
            $success_message = 'Achievements updated successfully!';
        } else {
            $error_message = 'Unable to update achievements: username missing.';
        }
    } elseif ($_POST['action'] === 'update_self_intro') {
        $self_intro_link = trim($_POST['self_intro_video'] ?? '');
        // Allow empty to clear
        $stmt = $mysqli->prepare('UPDATE users SET self_intro_video = ? WHERE id = ?');
        if ($stmt) {
            $stmt->bind_param('si', $self_intro_link, $student_id);
            if ($stmt->execute()) {
                $success_message = $self_intro_link !== '' ? 'Self-introduction video updated successfully!' : 'Self-introduction video removed.';
            } else {
                $error_message = 'Failed to update self-introduction.';
            }
            $stmt->close();
        } else {
            $error_message = 'Failed to prepare self-introduction update.';
        }
    }
}

// Fetch all student data (build select list based on available columns)
$user_select_cols = 'id, username, role, full_name, email, branch, semester, sgpa, backlogs, profile_photo, bio, phone, father_phone, aadhar_number, gender, category, year_of_passing, address, is_placed, college, self_intro_video';
$has_photo_url = false;
$col_check = $mysqli->query("SHOW COLUMNS FROM users LIKE 'photo_url'");
if ($col_check && $col_check->num_rows > 0) { $has_photo_url = true; }
if ($has_photo_url) { $user_select_cols .= ', photo_url'; }

$stmt = $mysqli->prepare("SELECT $user_select_cols FROM users WHERE id = ?");
if ($stmt === false) {
    error_log("Prepare failed: " . $mysqli->error);
    die("Database error: " . htmlspecialchars($mysqli->error));
}
$stmt->bind_param('i', $student_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();

// Map columns to expected format
if ($student) {
    $student['location'] = $student['branch'] ?? '';
    $student['year'] = $student['semester'] ? ceil($student['semester'] / 2) : 1;
    $student['profile_photo'] = $student['profile_photo'] ?? ($has_photo_url && isset($student['photo_url']) ? $student['photo_url'] : '');
    $student['about'] = $student['bio'] ?? '';
    // Ensure all fields have defaults
    $student['phone'] = $student['phone'] ?? '';
    $student['permanent_address'] = $student['permanent_address'] ?? '';
    $student['temporary_address'] = $student['temporary_address'] ?? '';
    $student['father_phone'] = $student['father_phone'] ?? '';
    $student['aadhar_number'] = $student['aadhar_number'] ?? '';
    $student['cgpa'] = $student['cgpa'] ?? '';
    $student['semester'] = $student['semester'] ?? '';
    $student['branch'] = $student['branch'] ?? '';
    $student['resume_link'] = $student['resume_link'] ?? '';
    $student['additional_links'] = $student['additional_links'] ?? '';
    $student['self_intro_video'] = $student['self_intro_video'] ?? '';
}

// Fetch additional info (GitHub / LinkedIn) from dedicated table
$github_link = '';
$linkedin_link = '';
$check_additional_table = $mysqli->query("SHOW TABLES LIKE 'student_additional_info'");
if ($check_additional_table && $check_additional_table->num_rows > 0) {
    $stmt = $mysqli->prepare('SELECT github_link, linkedin_link FROM student_additional_info WHERE student_id = ?');
    if ($stmt) {
        $stmt->bind_param('i', $student_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $github_link = $row['github_link'] ?? '';
            $linkedin_link = $row['linkedin_link'] ?? '';
        }
        $stmt->close();
    }
}

// Fetch academic data
$academic_data = null;
$result = $mysqli->query("SELECT * FROM resume_academic_data WHERE student_id = $student_id");
if ($result) $academic_data = $result->fetch_assoc();

// If no academic data, create default from user data
if (!$academic_data && $student) {
    $academic_data = [
        'roll_number' => $student['username'] ?? '',
        'branch' => $student['branch'] ?? 'Computer Science',
        'cgpa' => $student['cgpa'] ?? 0,
        'semester' => $student['semester'] ?? '',
        'backlogs' => $student['backlogs'] ?? 0,
        'attendance_percentage' => 85.5,
        'is_verified' => 1
    ];
}

// Fetch skills (by username per schema)
$skills = [];
$check_skills_table = $mysqli->query("SHOW TABLES LIKE 'student_skills'");
if ($check_skills_table && $check_skills_table->num_rows > 0 && !empty($student['username'])) {
    $stmt = $mysqli->prepare('SELECT * FROM student_skills WHERE username = ? ORDER BY created_at DESC');
    if ($stmt) {
        $stmt->bind_param('s', $student['username']);
        $stmt->execute();
        $skills = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

// Fetch projects (by username per schema)
$projects = [];
$check_projects_table = $mysqli->query("SHOW TABLES LIKE 'student_projects'");
if ($check_projects_table && $check_projects_table->num_rows > 0 && !empty($student['username'])) {
    $stmt = $mysqli->prepare("SELECT sp.*, COALESCE(sp.skills_used,'') AS skills FROM student_projects sp WHERE sp.username = ? ORDER BY (sp.is_ongoing=1) DESC, sp.start_date DESC");
    if ($stmt) {
        $stmt->bind_param('s', $student['username']);
        $stmt->execute();
        $projects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

// Fetch achievements from dedicated table (fallbacks removed for clarity)
$achievements = [];
if (!empty($student['username'])) {
    // Ensure table exists (safe no-op if already there)
    $mysqli->query("CREATE TABLE IF NOT EXISTS student_achievements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) NOT NULL,
        achievement_text TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_username (username)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $stmt = $mysqli->prepare('SELECT id, achievement_text FROM student_achievements WHERE username = ? ORDER BY created_at DESC');
    if ($stmt) {
        $stmt->bind_param('s', $student['username']);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $txt = trim($row['achievement_text'] ?? '');
            if ($txt !== '') {
                $achievements[] = [
                    'id'   => (int) $row['id'],
                    'text' => $txt,
                ];
            }
        }
        $stmt->close();
    }
}

$has_data = !empty($skills) || !empty($projects);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Student Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../includes/partials/sidebar.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f5f7fa;
            min-height: 100vh;
            display: flex;
        }

        /* Hamburger Menu Button */
        .hamburger-btn {
            position: fixed;
            top: 20px;
            left: 20px;
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #5b1f1f, #8b3a3a);
            border: none;
            border-radius: 12px;
            cursor: pointer;
            z-index: 1001;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 5px;
            box-shadow: 0 4px 15px rgba(91, 31, 31, 0.3);
            transition: all 0.3s ease;
        }

        .hamburger-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 20px rgba(91, 31, 31, 0.4);
        }

        .hamburger-btn span {
            width: 24px;
            height: 3px;
            background: white;
            border-radius: 2px;
            transition: all 0.3s ease;
        }

        .hamburger-btn.active span:nth-child(1) {
            transform: rotate(45deg) translate(7px, 7px);
        }

        .hamburger-btn.active span:nth-child(2) {
            opacity: 0;
        }

        .hamburger-btn.active span:nth-child(3) {
            transform: rotate(-45deg) translate(7px, -7px);
        }

        /* Overlay for mobile */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .sidebar-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        /* Main Content */
        .main-wrapper {
            margin-left: 240px;
            flex: 1;
            padding: 20px;
            padding-top: 90px;
            width: calc(100% - 240px);
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .top-bar-left {
            display: flex;
            align-items: center;
        }

        .top-bar-left a {
            transition: all 0.3s;
            padding: 8px 12px;
            border-radius: 8px;
        }

        .top-bar-left a:hover {
            background: #f3f4f6;
            color: #5b1f1f !important;
        }

        .top-bar-left h2 {
            color: #1f2937;
            font-size: 24px;
            margin-bottom: 5px;
        }

        .breadcrumb {
            color: #6b7280;
            font-size: 14px;
        }

        .top-bar-right {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .icon-btn {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: white;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .icon-btn:hover {
            background: #f3f4f6;
        }

        .header-banner {
            background: linear-gradient(135deg, #5b1f1f 0%, #8b3a3a 50%, #ecc35c 100%);
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }

        .header-banner::after {
            content: '';
            position: absolute;
            right: -50px;
            top: -50px;
            width: 300px;
            height: 300px;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200"><circle cx="100" cy="100" r="80" fill="%23ecc35c" opacity="0.2"/></svg>');
            background-size: contain;
        }

        .header-banner h1 {
            font-size: 32px;
            color: white;
            margin-bottom: 5px;
            position: relative;
            z-index: 1;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 30px;
        }

        .main-content {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .card-header h2 {
            color: #1f2937;
            font-size: 20px;
            font-weight: 700;
        }

        .edit-btn {
            background: #f3f4f6;
            border: 2px solid #e5e7eb;
            color: #5b1f1f;
            cursor: pointer;
            font-size: 14px;
            padding: 8px 16px;
            border-radius: 8px;
            transition: all 0.3s;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .edit-btn:hover {
            background: #5b1f1f;
            color: white;
            border-color: #5b1f1f;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(91, 31, 31, 0.3);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            font-size: 14px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
            background: #f9fafb;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #5b1f1f;
            background: white;
        }

        .form-group input:disabled,
        .form-group textarea:disabled {
            background: transparent;
            color: #1f2937;
            border: none;
            padding: 0;
            font-weight: inherit;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #5b1f1f, #8b3a3a);
            color: white;
        }

        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .btn-group {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }

        .right-sidebar {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .profile-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border: 3px solid #5b1f1f;
            position: relative;
        }

        .profile-card-icons {
            position: absolute;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
        }

        .profile-card-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .profile-card-icon:hover {
            background: #e5e7eb;
        }

        .profile-avatar-large {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            background: linear-gradient(135deg, #a78bfa, #c4b5fd);
            margin: 20px auto;
            position: relative;
            overflow: hidden;
        }

        .profile-avatar-large img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .avatar-edit {
            position: absolute;
            bottom: 5px;
            right: 5px;
            width: 40px;
            height: 40px;
            background: #1f2937;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: 3px solid white;
        }

        .avatar-edit i {
            color: white;
            font-size: 14px;
        }

        .profile-name {
            font-size: 22px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 12px;
        }

        .profile-badges {
            display: flex;
            gap: 8px;
            justify-content: center;
            margin-bottom: 20px;
        }

        .badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-student {
            background: #f3f4f6;
            color: #374151;
        }

        .badge-freelancer {
            background: #dbeafe;
            color: #1e40af;
            position: relative;
        }

        .badge-freelancer::after {
            content: '';
            position: absolute;
            top: -2px;
            right: -2px;
            width: 8px;
            height: 8px;
            background: #8b5cf6;
            border-radius: 50%;
            border: 2px solid white;
        }

        .location-card {
            background: #f9fafb;
            padding: 20px;
            border-radius: 12px;
            margin-top: 20px;
        }

        .location-card h4 {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 10px;
        }

        .location-card p {
            color: #1f2937;
            font-weight: 600;
        }

        .about-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .about-card h3 {
            font-size: 16px;
            color: #1f2937;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .word-count {
            font-size: 12px;
            color: #6b7280;
            font-weight: 400;
        }

        /* Optimized Personal Info Card */
        .info-card {
            padding: 0;
            background: #ffffff;
            border-radius: 0 0 10px 10px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
            overflow: hidden;
        }
        
        .info-section {
            padding: 16px 20px;
            position: relative;
        }
        
        .info-section:first-child {
            border-right: 1px solid #f0f0f0;
        }
        
        .info-section:last-child {
            padding-bottom: 16px;
        }
        
        .info-section-title {
            font-size: 18px;
            color: #661c1c;
            margin: 0 0 16px 0;
            display: flex;
            align-items: center;
            font-weight: 700;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .info-section-title i {
            margin-right: 8px;
            font-size: 15px;
            color: #5b1f1f;
            width: 18px;
            text-align: center;
        }
        
        .info-item {
            margin-bottom: 10px;
            display: flex;
            align-items: flex-start;
            padding: 4px 0;
        }
        
        .info-item:last-child {
            margin-bottom: 0;
        }
        
        .info-label {
            font-size: 15px;
            color: #3f3d56;
            width: 120px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            font-weight: 600;
            letter-spacing: 0.2px;
            padding: 4px 0;
        }
        
        .info-label i {
            margin-right: 8px;
            width: 16px;
            text-align: center;
            color: #5b1f1f;
            font-size: 15px;
            opacity: 1;
        }
        
        .info-value {
            font-size: 16px;
            color: #111827;
            font-weight: 600;
            line-height: 1.5;
            flex: 1;
            min-width: 0;
            word-break: break-word;
            padding: 4px 0 4px 16px;
            margin-left: 0;
            border-left: 2px solid #f0f0f0;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin: 0 -10px;
            padding: 0 10px;
            background: #f9fafb;
            border-radius: 8px;
            padding: 10px;
            margin-top: 8px;
        }
        
        .info-grid .info-item {
            margin: 0;
            padding: 6px 8px;
            background: #fff;
            border-radius: 6px;
            border: 1px solid #f0f0f0;
            transition: all 0.2s ease;
            flex-direction: column;
            align-items: flex-start;
        }
        
        .info-grid .info-item:hover {
            background: #fff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transform: none;
        }
        
        .info-grid .info-label {
            width: 100%;
            margin-bottom: 2px;
            padding: 0;
            font-size: 11.5px;
            color: #6b7280;
            opacity: 0.9;
        }
        
        .info-grid .info-value {
            font-size: 13px;
            color: #111827;
            font-weight: 500;
            padding-left: 0;
            border-left: none;
            padding: 2px 0 0 0;
        }
        
        @media (max-width: 992px) {
            .info-card {
                grid-template-columns: 1fr;
            }
            
            .info-section:first-child {
                border-right: none;
                border-bottom: 1px solid #f0f0f0;
            }
        }
        
        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
        }

        .about-card textarea {
            width: 100%;
            min-height: 120px;
            padding: 15px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 14px;
            line-height: 1.6;
            resize: vertical;
            background: #f9fafb;
            font-family: inherit;
        }

        .about-card textarea:focus {
            outline: none;
            border-color: #5b1f1f;
            background: white;
        }

        .item-card {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 15px;
            border-left: 4px solid #5b1f1f;
        }

        .item-title {
            font-size: 18px;
            font-weight: 700;
            color: #5b1f1f;
            margin-bottom: 5px;
        }

        .item-subtitle {
            color: #78350f;
            font-size: 14px;
            font-weight: 600;
        }

        .item-meta {
            color: #92400e;
            font-size: 12px;
            margin-top: 5px;
        }

        .item-meta input {
            background: transparent;
            border: none;
            color: inherit;
            font-weight: inherit;
            font-size: inherit;
            padding: 0;
            width: auto;
        }

        .tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
        }

        .tag {
            background: rgba(91, 31, 31, 0.1);
            color: #5b1f1f;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
        }

        .tag input {
            background: transparent;
            border: none;
            color: inherit;
            font-weight: inherit;
            font-size: inherit;
            padding: 0;
            width: auto;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .sidebar {
                width: 260px;
                left: -260px;
            }

            .main-wrapper {
                padding: 15px;
                padding-top: 80px;
            }

            .hamburger-btn {
                top: 15px;
                left: 15px;
                width: 45px;
                height: 45px;
            }
        }
    </style>
</head>
<body>
    <!-- Hamburger Menu Button -->
    <button class="hamburger-btn" id="hamburgerBtn">
        <span></span>
        <span></span>
        <span></span>
    </button>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <?php include __DIR__ . '/../includes/partials/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-wrapper">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="top-bar-left">
                <div class="breadcrumb">My Profile</div>
            </div>
            <div class="top-bar-right">
                <button class="icon-btn">
                    <i class="fas fa-bell"></i>
                </button>
                <button class="icon-btn">
                    <i class="fas fa-search"></i>
                </button>
                <a href="analytics.php" class="btn btn-primary" style="text-decoration: none;">
                    <i class="fas fa-chart-line"></i> Analytics
                </a>
            </div>
        </div>

        <!-- Header Banner -->
        <div class="header-banner">
            <h1>Tune In And Level Up At<br>Your <em>Own Pace.</em></h1>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Main Content -->
            <div class="main-content">
                <!-- Optimized Personal Info Card -->
                <div class="card" style="border-radius: 10px; box-shadow: 0 2px 15px rgba(0,0,0,0.05); margin-bottom: 0;">
                    <div class="card-header" style="padding: 14px 20px; background: linear-gradient(135deg, #f9f5ff 0%, #f3e8ff 100%); border-radius: 10px 10px 0 0; border-bottom: 1px solid #f0f0f0;">
                        <h2 style="margin: 0; font-size: 16px; color: #5b1f1f; font-weight: 600; display: flex; align-items: center;">
                            <i class="fas fa-user-circle" style="margin-right: 10px; color: #7e22ce; font-size: 18px;"></i>
                            Personal Information
                        </h2>
                    </div>
                    <div class="info-card">
                        <div class="info-section">
                            <div class="info-section-title">
                                <i class="fas fa-user"></i> Basic Information
                            </div>
                            <div class="info-item">
                                <div class="info-label"><i class="fas fa-user"></i> Name</div>
                                <div class="info-value"><?php echo !empty($student['full_name']) ? htmlspecialchars($student['full_name']) : '—'; ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label"><i class="fas fa-envelope"></i> Email</div>
                                <div class="info-value"><?php echo !empty($student['email']) ? htmlspecialchars($student['email']) : '—'; ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label"><i class="fas fa-phone"></i> Contact</div>
                                <div class="info-value"><?php echo !empty($student['phone']) ? htmlspecialchars($student['phone']) : '—'; ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label"><i class="fas fa-phone-alt"></i> Father's Contact</div>
                                <div class="info-value"><?php echo !empty($student['father_phone']) ? htmlspecialchars($student['father_phone']) : '—'; ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label"><i class="fas fa-id-card"></i> Aadhar</div>
                                <div class="info-value"><?php echo !empty($student['aadhar_number']) ? htmlspecialchars($student['aadhar_number']) : '—'; ?></div>
                            </div>
                        </div>
                        
                        <div class="info-section">
                            <div class="info-section-title">
                                <i class="fas fa-graduation-cap"></i> Academic Details
                            </div>
                            <div class="info-item">
                                <div class="info-label"><i class="fas fa-code-branch"></i> Branch</div>
                                <div class="info-value"><?php echo !empty($student['branch']) ? htmlspecialchars($student['branch']) : '—'; ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label"><i class="fas fa-calendar-alt"></i> Semester</div>
                                <div class="info-value"><?php echo !empty($student['semester']) ? 'Semester ' . htmlspecialchars($student['semester']) : '—'; ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label"><i class="fas fa-chart-line"></i> CGPA</div>
                                <div class="info-value"><?php echo !empty($student['cgpa']) ? htmlspecialchars($student['cgpa']) : '—'; ?></div>
                            </div>
                            
                        <div class="info-section-title" style="margin-top: 16px;">
                            <i class="fas fa-home"></i> Address
                        </div>
                        <div class="info-item">
                            <div class="info-label"><i class="fas fa-map-marker-alt"></i> Address</div>
                            <div class="info-value"><?php echo !empty($student['address']) ? htmlspecialchars($student['address']) : '—'; ?></div>
                        </div>
                        </div>
                    </div>
                </div>

                <!-- Professional Info Card (Read-Only) -->
                <div class="card" style="margin-top: 24px; border-radius: 10px; box-shadow: 0 2px 15px rgba(0,0,0,0.05);">
                    <div class="card-header">
                        <h2>Professional info</h2>
                    </div>
                    <?php if ($academic_data): ?>
                        <div class="item-card">
                            <div class="item-title">Bachelor of Engineering</div>
                            <div class="item-subtitle"><?php echo htmlspecialchars($academic_data['branch'] ?? $student['branch'] ?? 'Computer Science'); ?></div>
                            <div class="item-meta">
                                Roll: <strong><?php echo htmlspecialchars($academic_data['roll_number'] ?? $student['username'] ?? '—'); ?></strong> |
                                CGPA: <strong><?php 
                                    $cgpa_val = $academic_data['cgpa'] ?? $student['cgpa'] ?? null;
                                    if ($cgpa_val !== null && $cgpa_val !== '' && is_numeric($cgpa_val)) {
                                        echo number_format((float)$cgpa_val, 2);
                                    } else {
                                        echo '—';
                                    }
                                ?></strong> |
                                Semester: <strong><?php echo htmlspecialchars($academic_data['semester'] ?? $student['semester'] ?? '—'); ?></strong>
                            </div>
                            <div class="tags">
                                <span class="tag">Backlogs: <strong><?php echo $academic_data['backlogs'] ?? $student['backlogs'] ?? 0; ?></strong></span>
                                <span class="tag">Attendance: <strong><?php 
                                    $attendance_val = $academic_data['attendance_percentage'] ?? null;
                                    if ($attendance_val !== null && $attendance_val !== '' && is_numeric($attendance_val)) {
                                        echo number_format((float)$attendance_val, 1);
                                    } else {
                                        echo '—';
                                    }
                                ?>%</strong></span>
                                <?php if (isset($academic_data['is_verified']) && $academic_data['is_verified']): ?>
                                    <span class="tag" style="background: #d1fae5; color: #065f46;">✓ Verified</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <p style="color: #6b7280;">No professional information available.</p>
                    <?php endif; ?>
                </div>

                <!-- Additional Info Card -->
                <div class="card">
                    <div class="card-header">
                        <h2>Additional info</h2>
                        <button type="button" id="additionalEditBtn" class="edit-btn" onclick="enableForm('additionalForm')">
                            <i class="fas fa-edit"></i>
                            <span>Edit</span>
                        </button>
                    </div>
                    <form id="additionalForm" method="POST">
                        <input type="hidden" name="action" value="update_additional">
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label>GitHub Profile URL</label>
                                <input type="url" name="github_link" value="<?php echo htmlspecialchars($github_link ?? ''); ?>" disabled placeholder="https://github.com/username">
                            </div>
                            <div class="form-group full-width">
                                <label>LinkedIn Profile URL</label>
                                <input type="url" name="linkedin_link" value="<?php echo htmlspecialchars($linkedin_link ?? ''); ?>" disabled placeholder="https://linkedin.com/in/username">
                            </div>
                        </div>
                        <div class="btn-group" style="display: none;" id="additionalFormButtons">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="cancelEdit('additionalForm')">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Achievement Card -->
                <div class="card">
                    <div class="card-header">
                        <h2>Achievement</h2>
                        <button type="button" class="edit-btn" onclick="enableForm('achievementsForm')">
                            <i class="fas fa-edit"></i>
                            <span>Edit</span>
                        </button>
                    </div>
                    <form id="achievementsForm" method="POST">
                        <input type="hidden" name="action" value="update_achievements">
                        <div id="achievementsList" style="display: flex; flex-direction: column; gap: 12px;">
                            <?php $max = max(3, count($achievements)); for ($i=0; $i<$max; $i++):
                                $val = $achievements[$i]['text'] ?? ($achievements[$i] ?? '');
                            ?>
                                <div class="achievement-row" style="display: flex; align-items: center; gap: 12px; padding: 12px; background: #f9fafb; border-radius: 10px;">
                                    <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #fef3c7, #fde68a); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-trophy" style="color: #ecc35c;"></i>
                                    </div>
                                    <input type="text" name="achievement_<?php echo $i; ?>" value="<?php echo htmlspecialchars($val); ?>" class="achInput" disabled style="flex:1; border:none; background:transparent; color:#1f2937; font-weight:600; font-size:14px;">
                                </div>
                            <?php endfor; ?>
                        </div>
                        <div class="btn-group" style="display:none;" id="achievementsFormButtons">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                            <button type="button" class="btn btn-secondary" onclick="cancelEdit('achievementsForm')">Cancel</button>
                        </div>
                    </form>
                </div>

                <!-- Skills Card -->
                <div class="card">
                    <div class="card-header">
                        <h2>Skills (<?php echo count($skills); ?>)</h2>
                        <button type="button" class="edit-btn" onclick="enableForm('skillsForm')">
                            <i class="fas fa-edit"></i>
                            <span>Edit</span>
                        </button>
                    </div>
                    <form id="skillsForm" method="POST">
                        <input type="hidden" name="action" value="update_skills">
                        <?php if (!empty($skills)): ?>
                            <div class="tags">
                                <?php foreach (array_slice($skills, 0, 10) as $idx => $skill): ?>
                                    <span class="tag" style="background:#f9fafb;">
                                        <input type="text" name="skill_name_<?php echo $idx; ?>" value="<?php echo htmlspecialchars($skill['skill_name']); ?>" disabled style="border:none; background:transparent; min-width:80px;">
                                        <input type="hidden" name="skill_id_<?php echo $idx; ?>" value="<?php echo (int)$skill['id']; ?>">
                                    </span>
                                <?php endforeach; ?>
                            </div>
                            <div style="margin-top: 15px;">
                                <label style="font-weight: 600; color: #374151; margin-bottom: 8px; display: block;">Add New Skill:</label>
                                <div style="display: flex; gap: 10px;">
                                    <input type="text" name="new_skill_name" placeholder="Enter skill name" disabled style="flex: 1; padding: 10px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px;">
                                    <select name="new_skill_proficiency" disabled style="padding: 10px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px;">
                                        <option value="beginner">Beginner</option>
                                        <option value="intermediate" selected>Intermediate</option>
                                        <option value="advanced">Advanced</option>
                                        <option value="expert">Expert</option>
                                    </select>
                                </div>
                            </div>
                        <?php else: ?>
                            <p style="color: #6b7280;">No skills added yet.</p>
                            <div style="margin-top: 15px;">
                                <label style="font-weight: 600; color: #374151; margin-bottom: 8px; display: block;">Add Your First Skill:</label>
                                <div style="display: flex; gap: 10px;">
                                    <input type="text" name="new_skill_name" placeholder="Enter skill name" disabled style="flex: 1; padding: 10px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px;">
                                    <select name="new_skill_proficiency" disabled style="padding: 10px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px;">
                                        <option value="beginner">Beginner</option>
                                        <option value="intermediate" selected>Intermediate</option>
                                        <option value="advanced">Advanced</option>
                                        <option value="expert">Expert</option>
                                    </select>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="btn-group" style="display: none;" id="skillsFormButtons">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                            <button type="button" class="btn btn-secondary" onclick="cancelEdit('skillsForm')">Cancel</button>
                        </div>
                    </form>
                </div>

                <!-- Projects Card -->
                <div class="card">
                    <div class="card-header">
                        <h2>Projects (<?php echo count($projects); ?>)</h2>
                        <button class="btn btn-primary" onclick="showAddProjectModal()">
                            <i class="fas fa-plus"></i> Add Project
                        </button>
                    </div>
                    <?php if (!empty($projects)): ?>
                        <div style="display: flex; flex-direction: column; gap: 15px;">
                            <?php foreach ($projects as $project): ?>
                                <div style="padding: 15px; background: #f9fafb; border-radius: 10px; border-left: 4px solid #5b1f1f;">
                                    <div style="font-weight: 600; color: #1f2937; margin-bottom: 5px; font-size: 16px;">
                                        <?php echo htmlspecialchars($project['project_title'] ?? $project['title'] ?? 'Untitled Project'); ?>
                                    </div>
                                    <?php if (!empty($project['role'])): ?>
                                        <div style="font-size: 14px; color: #6b7280; margin-bottom: 8px;">
                                            <strong>Role:</strong> <?php echo htmlspecialchars($project['role']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($project['description'])): ?>
                                        <div style="font-size: 14px; color: #4b5563; margin-bottom: 8px;">
                                            <?php echo htmlspecialchars(substr($project['description'], 0, 150)); ?>
                                            <?php if (strlen($project['description']) > 150): ?>...<?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($project['skills_used']) || !empty($project['skills'])): ?>
                                        <div style="font-size: 13px; color: #6b7280; margin-top: 8px;">
                                            <strong>Skills:</strong> <?php echo htmlspecialchars($project['skills_used'] ?? $project['skills'] ?? ''); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($project['start_date'])): ?>
                                        <div style="font-size: 12px; color: #9ca3af; margin-top: 8px;">
                                            <?php 
                                            $start = date('M Y', strtotime($project['start_date']));
                                            $end = !empty($project['end_date']) ? date('M Y', strtotime($project['end_date'])) : ($project['is_ongoing'] ? 'Present' : 'N/A');
                                            echo $start . ' - ' . $end;
                                            ?>
                                        </div>
                                    <?php endif; ?>
                                    <div style="margin-top:10px; display:flex; gap:8px; justify-content:flex-end;">
                                        <button type="button" class="btn btn-secondary" style="padding:6px 12px;font-size:12px;" onclick="editProject(<?php echo (int)($project['id'] ?? 0); ?>)">Edit</button>
                                        <button type="button" class="btn btn-secondary" style="padding:6px 12px;font-size:12px;background:#fee2e2;color:#b91c1c;border-color:#fecaca;" onclick="deleteProject(<?php echo (int)($project['id'] ?? 0); ?>)">Delete</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 40px; color: #6b7280;">
                            <i class="fas fa-folder-open" style="font-size: 48px; margin-bottom: 15px; opacity: 0.3;"></i>
                            <p style="font-size: 16px; margin-bottom: 5px;">No projects added yet.</p>
                            <p style="font-size: 14px;">Click "Add Project" to get started!</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Add/Edit Project Modal -->
                <div id="projectModal" style="display:none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
                    <div style="background:#fff; width: 640px; max-width: 95vw; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
                        <div style="display:flex; align-items:center; justify-content: space-between; padding:16px 20px; border-bottom:1px solid #eee;">
                            <h3 id="projectModalTitle" style="margin:0; font-size:18px; color:#1f2937;">Add Project</h3>
                            <button onclick="closeProjectModal()" style="background:transparent; border:none; font-size:18px; cursor:pointer; color:#6b7280;">&times;</button>
                        </div>
                        <form id="projectForm" style="padding: 20px;">
                            <input type="hidden" name="action" value="save_project">
                            <input type="hidden" name="project_id" value="">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Project Title</label>
                                    <input type="text" name="title" placeholder="e.g., Portfolio Website" required>
                                </div>
                                <div class="form-group">
                                    <label>Role</label>
                                    <input type="text" name="role" placeholder="e.g., Full Stack Developer">
                                </div>
                                <div class="form-group full-width">
                                    <label>Project URL</label>
                                    <input type="url" name="url" placeholder="https://...">
                                </div>
                                <div class="form-group">
                                    <label>Start Date</label>
                                    <input type="date" name="start_date">
                                </div>
                                <div class="form-group">
                                    <label>End Date</label>
                                    <input type="date" name="end_date">
                                </div>
                                <div class="form-group">
                                    <label>Ongoing?</label>
                                    <select name="is_ongoing">
                                        <option value="0">No</option>
                                        <option value="1">Yes</option>
                                    </select>
                                </div>
                                <div class="form-group full-width">
                                    <label>Description</label>
                                    <textarea name="description" rows="4" placeholder="Short summary of the project, responsibilities, outcomes..."></textarea>
                                </div>
                                <div class="form-group full-width">
                                    <label>Skills (comma-separated)</label>
                                    <input type="text" name="skills" placeholder="React, Node.js, MySQL">
                                </div>
                            </div>
                            <div class="btn-group" style="justify-content: flex-end;">
                                <button type="button" class="btn btn-secondary" onclick="closeProjectModal()">Cancel</button>
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Project</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Right Sidebar -->
            <div class="right-sidebar">
				<!-- Profile Card -->
				<div class="profile-card">
					<div class="profile-card-icons">
						<div class="profile-card-icon">
							<i class="fas fa-camera"></i>
						</div>
						<div class="profile-card-icon">
							<i class="fas fa-upload"></i>
						</div>
					</div>
					
					<div class="profile-avatar-large">
						<img id="profilePhotoImage"
						     src="<?php echo !empty($student['profile_photo']) ? htmlspecialchars($student['profile_photo']) : ''; ?>"
						     alt="Profile Photo"
						     style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%; <?php echo empty($student['profile_photo']) ? 'display:none;' : ''; ?>"
						     onerror="this.style.display='none'; document.getElementById('profilePhotoPlaceholder').style.display='flex';">
						<div id="profilePhotoPlaceholder" class="avatar-placeholder" style="width: 100%; height: 100%; background: linear-gradient(135deg, #a78bfa, #c4b5fd); display: <?php echo empty($student['profile_photo']) ? 'flex' : 'none'; ?>; align-items: center; justify-content: center; font-size: 48px; color: white; font-weight: 700; position: absolute; border-radius: 50%;">
							<?php echo strtoupper(substr($student['full_name'] ?? 'S', 0, 1)); ?>
						</div>
						<div class="avatar-edit" onclick="document.getElementById('profilePhotoInput').click();" style="cursor: pointer;">
							<i class="fas fa-camera"></i>
						</div>
					</div>
					<input type="file" id="profilePhotoInput" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" style="display: none;" onchange="uploadProfilePhoto(this.files[0])">
					
					<div class="profile-name"><?php echo htmlspecialchars($student['full_name'] ?? 'Student'); ?></div>
					
					<div class="profile-badges">
						<span class="badge badge-student">Student</span>
						<span class="badge badge-freelancer">Freelancer</span>
					</div>

					<div class="location-card">
						<h4>Location</h4>
						<p><?php echo htmlspecialchars($student['branch'] ?? 'Not specified'); ?></p>
						<button style="background: transparent; border: none; color: #6b7280; cursor: pointer; margin-top: 10px;">
							<i class="fas fa-plus"></i>
						</button>
					</div>
				</div>

				<!-- About You Card -->
                <div class="about-card">
                    <h3>
                        About You
                        <span class="word-count" id="wordCount">0/100 words</span>
                    </h3>
                    <form method="POST" id="aboutForm">
                        <input type="hidden" name="action" value="update_about">
                        <textarea id="aboutTextarea" name="about_text" placeholder="Tell us about yourself, your career goals, and what makes you unique..." onkeyup="updateWordCount()"><?php echo htmlspecialchars($student['bio'] ?? ''); ?></textarea>
                        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 12px; justify-content: center;">
                            <i class="fas fa-save"></i> Save About
                        </button>
                    </form>
                </div>

                <!-- Areas of Interest Card -->
                <div class="card">
                    <div class="card-header">
                        <h2>Areas of interest</h2>
                        <button type="button" class="edit-btn" onclick="toggleInterestEdit()">
                            <i class="fas fa-edit"></i>
                            <span>Edit</span>
                        </button>
                    </div>
                    <div id="interestTags" style="display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 15px;">
                        <?php 
                        // Load interests from student_interests table
                        $interests = [];
                        $username = $student['username'] ?? '';
                        if (!empty($username)) {
                            $check_interests_table = $mysqli->query("SHOW TABLES LIKE 'student_interests'");
                            if ($check_interests_table && $check_interests_table->num_rows > 0) {
                                $stmt = $mysqli->prepare('SELECT interest_name FROM student_interests WHERE username = ?');
                                if ($stmt) {
                                    $stmt->bind_param('s', $username);
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    while ($row = $result->fetch_assoc()) {
                                        $interests[] = $row['interest_name'];
                                    }
                                    $stmt->close();
                                }
                            }
                        }
                        // Fallback to users.interests if student_interests is empty
                        if (empty($interests) && !empty($student['interests'])) {
                            $interests_json = $student['interests'];
                            if (is_string($interests_json)) {
                                $interests = json_decode($interests_json, true) ?: [];
                            } elseif (is_array($interests_json)) {
                                $interests = $interests_json;
                            }
                        }
                        if (empty($interests)): ?>
                            <p style="color: #6b7280; font-size: 14px; width: 100%;">No interests added yet. Click Edit to add your interests.</p>
                        <?php else: ?>
                            <?php foreach ($interests as $interest): ?>
                                <span class="tag editable-tag" data-interest="<?php echo htmlspecialchars($interest); ?>">
                                    <?php echo htmlspecialchars($interest); ?> 
                                    <i class="fas fa-times" onclick="removeTag(this)" style="margin-left: 6px; cursor: pointer;"></i>
                                </span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div id="addInterestSection" style="display: none;">
                        <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                            <input type="text" id="newInterestInput" placeholder="Add new interest..." onkeypress="if(event.key === 'Enter') addInterest()" style="flex: 1; padding: 10px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px;">
                            <button onclick="addInterest()" class="btn btn-primary" style="padding: 10px 20px;">
                                <i class="fas fa-plus"></i> Add
                            </button>
                        </div>
                        <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                            <span class="tag" onclick="quickAddInterest('AI/ML')" style="cursor: pointer; background: #e5e7eb;">+ AI/ML</span>
                            <span class="tag" onclick="quickAddInterest('Blockchain')" style="cursor: pointer; background: #e5e7eb;">+ Blockchain</span>
                            <span class="tag" onclick="quickAddInterest('Mobile Development')" style="cursor: pointer; background: #e5e7eb;">+ Mobile Development</span>
                            <span class="tag" onclick="quickAddInterest('DevOps')" style="cursor: pointer; background: #e5e7eb;">+ DevOps</span>
                            <span class="tag" onclick="quickAddInterest('Cybersecurity')" style="cursor: pointer; background: #e5e7eb;">+ Cybersecurity</span>
                            <span class="tag" onclick="quickAddInterest('UI/UX Design')" style="cursor: pointer; background: #e5e7eb;">+ UI/UX Design</span>
                        </div>
                        <div style="margin-top: 15px;">
                            <button onclick="saveInterests()" class="btn btn-primary" style="width: 100%;">
                                <i class="fas fa-save"></i> Save Interests
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card" style="display: flex; justify-content: center; align-items: center; padding: 30px;">
                    <a href="calendar.php" class="btn btn-secondary" style="text-decoration: none; justify-content: center; color: #5b1f1f !important; font-size: 18px; padding: 16px 28px; border-radius: 12px; min-width: 240px; display: inline-flex; align-items: center;">
                        <i class="fas fa-calendar" style="color: #5b1f1f !important; margin-right: 10px; font-size: 20px;"></i> My Calendar
                    </a>
                </div>

                <!-- Self Introduction Card -->
                <div class="card">
                    <div class="card-header">
                        <h2>Self-Introduction</h2>
                        <button type="button" class="edit-btn" onclick="enableForm('selfIntroForm')">
                            <i class="fas fa-edit"></i>
                            <span>Edit</span>
                        </button>
                    </div>
                    <div style="margin-bottom: 20px;">
                        <?php
                        $self_intro_url = trim($student['self_intro_video'] ?? '');
                        $self_intro_url = $self_intro_url && !preg_match('~^https?://~i', $self_intro_url)
                            ? 'https://' . ltrim($self_intro_url, '/')
                            : $self_intro_url;
                        if (!empty($self_intro_url)) {
                            $embed_html = '';
                            if (preg_match('~(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|v/))([\w\-]{11})~', $self_intro_url, $matches)) {
                                $video_id = $matches[1];
                                $embed_src = 'https://www.youtube.com/embed/' . htmlspecialchars($video_id);
                                $embed_html = '<div style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;border-radius:12px;box-shadow:0 5px 15px rgba(0,0,0,0.15);"><iframe src="' . $embed_src . '" style="position:absolute;top:0;left:0;width:100%;height:100%;border:0;border-radius:12px;" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe></div>';
                            } elseif (preg_match('~\.(mp4|webm|ogg)$~i', $self_intro_url)) {
                                $embed_html = '<video controls style="width:100%;border-radius:12px;box-shadow:0 5px 15px rgba(0,0,0,0.15);"><source src="' . htmlspecialchars($self_intro_url) . '"></video>';
                            }
                            if ($embed_html) {
                                echo $embed_html;
                            } else {
                                echo '<p style="color:#6b7280;">Self-introduction video: <a href="' . htmlspecialchars($self_intro_url) . '" target="_blank" rel="noopener">' . htmlspecialchars($self_intro_url) . '</a></p>';
                            }
                        } else {
                            echo '<p style="color:#6b7280;">No self-introduction video added yet. Click Edit to add one.</p>';
                        }
                        ?>
                    </div>
                    <form id="selfIntroForm" method="POST">
                        <input type="hidden" name="action" value="update_self_intro">
                        <div class="form-group full-width">
                            <label>Self-Introduction Video URL (YouTube or direct video link)</label>
                            <input type="url" name="self_intro_video" value="<?php echo htmlspecialchars($student['self_intro_video'] ?? ''); ?>" disabled placeholder="https://youtu.be/your-video">
                        </div>
                        <div class="btn-group" style="display:none;" id="selfIntroFormButtons">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Video
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="cancelEdit('selfIntroForm')">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Ensure the Additional info edit button always enables the form (backup to inline onclick)
        document.addEventListener('DOMContentLoaded', function() {
            var additionalBtn = document.getElementById('additionalEditBtn');
            if (additionalBtn) {
                additionalBtn.addEventListener('click', function(e) {
                    // If inline handler didn't run for any reason, run here
                    if (typeof window.enableForm === 'function') {
                        window.enableForm('additionalForm');
                    }
                });
            }
        });
        // Fallback: force-enable a form's fields and show its Save/Cancel buttons
        window.uploadProfilePhoto = async function(file) {
            if (!file) return;
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!allowedTypes.includes(file.type)) {
                alert('Please choose a JPG, PNG, GIF, or WEBP image.');
                return;
            }
            if (file.size > 5 * 1024 * 1024) {
                alert('File too large. Maximum size is 5MB.');
                return;
            }

            const formData = new FormData();
            formData.append('profile_photo', file);

            const avatarContainer = document.querySelector('.profile-avatar-large');
            if (avatarContainer) {
                avatarContainer.classList.add('uploading');
            }

            try {
                const response = await fetch('../upload_profile_photo.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error((data && data.message) || 'Failed to upload photo.');
                }

                const imgEl = document.getElementById('profilePhotoImage');
                const placeholderEl = document.getElementById('profilePhotoPlaceholder');
                if (imgEl && data.photo_url) {
                    imgEl.src = data.photo_url + '?t=' + Date.now();
                    imgEl.style.display = 'block';
                }
                if (placeholderEl) {
                    placeholderEl.style.display = 'none';
                }

                if (typeof showToast === 'function') {
                    showToast('Profile photo updated!', 'success');
                } else {
                    alert('Profile photo updated!');
                }
            } catch (error) {
                console.error('Photo upload failed:', error);
                alert(error.message || 'Failed to upload photo.');
            } finally {
                if (avatarContainer) {
                    avatarContainer.classList.remove('uploading');
                }
                const input = document.getElementById('profilePhotoInput');
                if (input) {
                    input.value = '';
                }
            }
        };
        // Fallback: force-enable a form's fields and show its Save/Cancel buttons
        window.enableForm = function(formId) {
            const form = document.getElementById(formId);
            if (!form) return;
            const inputs = form.querySelectorAll('input:not([type="hidden"]), select, textarea');
            inputs.forEach(input => {
                input.disabled = false;
                input.style.background = 'white';
                input.style.border = '2px solid #5b1f1f';
                input.style.borderRadius = '6px';
                input.style.padding = '4px 8px';
            });
            const buttons = document.getElementById(formId + 'Buttons');
            if (buttons) buttons.style.display = 'flex';
            if (typeof showToast === 'function') showToast('Edit mode enabled', 'info');
        };
        // Ensure toggleEdit is in global scope
        window.toggleEdit = function(formId) {
            const form = document.getElementById(formId);
            if (!form) {
                console.error('Form not found:', formId);
                return;
            }

            const inputs = form.querySelectorAll('input:not([type="hidden"]), select, textarea');
            if (inputs.length === 0) {
                console.error('No inputs found in form:', formId);
                return;
            }

            const buttons = document.getElementById(formId + 'Buttons');
            const isCurrentlyDisabled = inputs[0].disabled;

            // Toggle disabled state - enable if disabled, disable if enabled
            inputs.forEach(input => {
                input.disabled = !isCurrentlyDisabled;
                if (!input.disabled) {
                    input.style.background = 'white';
                    input.style.border = '2px solid #5b1f1f';
                    input.style.borderRadius = '6px';
                    input.style.padding = '4px 8px';
                    input.classList.add('editing');
                } else {
                    input.style.background = 'transparent';
                    input.style.border = 'none';
                    input.style.padding = '0';
                    input.classList.remove('editing');
                }
            });

            // Toggle buttons visibility
            if (buttons) {
                buttons.style.display = isCurrentlyDisabled ? 'flex' : 'none';
            }

            // Show toast if available
            if (typeof showToast === 'function' && !isCurrentlyDisabled) {
                showToast('Edit mode enabled', 'info');
            }
        };

        window.cancelEdit = function(formId) {
            const form = document.getElementById(formId);
            if (!form) return;

            const inputs = form.querySelectorAll('input:not([type="hidden"]), select, textarea');
            const buttons = document.getElementById(formId + 'Buttons');

            // Disable all inputs
            inputs.forEach(input => {
                input.disabled = true;
                input.style.background = 'transparent';
                input.style.border = 'none';
                input.style.padding = '0';
                input.classList.remove('editing');
            });
            if (buttons) buttons.style.display = 'none';
        };

        // Toggle interests edit mode
        window.toggleInterestEdit = function() {
            const container = document.getElementById('interestTags');
            const addSection = document.getElementById('addInterestSection');
            if (!container || !addSection) return;

            // show/hide the add section and make existing tags removable
            const isHidden = addSection.style.display === 'none' || addSection.style.display === '';
            addSection.style.display = isHidden ? 'block' : 'none';

            const tags = container.querySelectorAll('.editable-tag i');
            tags.forEach(icon => { icon.style.display = isHidden ? 'inline-block' : 'none'; });
        };

        // Remove a tag (interest)
        window.removeTag = function(iconEl) {
            const tagEl = iconEl.closest('.tag');
            if (tagEl && tagEl.parentElement) {
                tagEl.parentElement.removeChild(tagEl);
            }
        };

        // Quick add preset interest
        window.quickAddInterest = function(text) {
            const input = document.getElementById('newInterestInput');
            if (input) {
                input.value = text;
                addInterest();
            }
        };

        // Add interest from input
        window.addInterest = function() {
            const input = document.getElementById('newInterestInput');
            const container = document.getElementById('interestTags');
            if (!input || !container) return;
            const val = (input.value || '').trim();
            if (!val) return;

            const span = document.createElement('span');
            span.className = 'tag editable-tag';
            span.setAttribute('data-interest', val);
            span.style.marginRight = '6px';
            span.innerHTML = `${val} <i class="fas fa-times" onclick="removeTag(this)" style="margin-left: 6px; cursor: pointer;"></i>`;
            container.appendChild(span);
            input.value = '';
        };

        // Save interests to server
        window.saveInterests = function() {
            const container = document.getElementById('interestTags');
            if (!container) return;
            const tags = Array.from(container.querySelectorAll('.editable-tag'))
                .map(t => t.getAttribute('data-interest') || t.textContent.trim())
                .filter(Boolean);

            // Create a form and submit
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            const action = document.createElement('input');
            action.name = 'action';
            action.value = 'update_interests';
            form.appendChild(action);

            const interests = document.createElement('input');
            interests.name = 'interests';
            interests.value = JSON.stringify(tags);
            form.appendChild(interests);

            document.body.appendChild(form);
            form.submit();
        };

        // About word counter
        window.updateWordCount = function() {
            const ta = document.getElementById('aboutTextarea');
            const wc = document.getElementById('wordCount');
            if (!ta || !wc) return;
            const words = (ta.value || '').trim().split(/\s+/).filter(Boolean);
            wc.textContent = `${words.length}/100 words`;
        };

        // Initialize word count on load
        document.addEventListener('DOMContentLoaded', updateWordCount);

        // Make projects available to JS for edit/delete
        window.projectsData = <?php echo json_encode($projects); ?>;

        // Project modal handlers
        window.showAddProjectModal = function() {
            const modal = document.getElementById('projectModal');
            const form = document.getElementById('projectForm');
            if (!modal || !form) return;
            form.reset();
            form.querySelector('[name="project_id"]').value = '';
            const titleEl = document.getElementById('projectModalTitle');
            if (titleEl) titleEl.textContent = 'Add Project';
            modal.style.display = 'flex';
        };

        window.closeProjectModal = function() {
            const modal = document.getElementById('projectModal');
            if (modal) modal.style.display = 'none';
        };

        window.editProject = function(projectId) {
            const modal = document.getElementById('projectModal');
            const form = document.getElementById('projectForm');
            if (!modal || !form || !window.projectsData) return;
            const project = window.projectsData.find(p => parseInt(p.id) === parseInt(projectId));
            if (!project) return;

            form.reset();
            form.querySelector('[name="project_id"]').value = project.id || '';
            form.querySelector('[name="title"]').value = project.project_title || project.title || '';
            form.querySelector('[name="role"]').value = project.role || '';
            form.querySelector('[name="url"]').value = project.project_url || '';
            form.querySelector('[name="start_date"]').value = project.start_date || '';
            form.querySelector('[name="end_date"]').value = project.end_date || '';
            form.querySelector('[name="is_ongoing"]').value = project.is_ongoing ? '1' : '0';
            form.querySelector('[name=\"description\"]').value = project.description || '';
            form.querySelector('[name=\"skills\"]').value = project.skills_used || project.skills || '';

            const titleEl = document.getElementById('projectModalTitle');
            if (titleEl) titleEl.textContent = 'Edit Project';
            modal.style.display = 'flex';
        };

        window.deleteProject = function(projectId) {
            if (!confirm('Are you sure you want to delete this project?')) return;

            const formData = new FormData();
            formData.append('action', 'delete_project');
            formData.append('project_id', projectId);

            fetch('../save_project.php', {
                method: 'POST',
                body: formData
            }).then(res => res.json())
            .then(data => {
                if (data && data.success) {
                    if (typeof showToast === 'function') {
                        showToast('Project deleted successfully', 'success');
                    } else {
                        alert('Project deleted successfully');
                    }
                    setTimeout(() => window.location.reload(), 400);
                } else {
                    alert((data && data.message) || 'Failed to delete project');
                }
            }).catch(err => {
                console.error('Error deleting project:', err);
                alert('Network error while deleting project: ' + err.message);
            });
        };

        // Submit project form via fetch to save_project.php
        (function(){
            const form = document.getElementById('projectForm');
            if (!form) return;
            form.addEventListener('submit', async function(e){
                e.preventDefault();
                const formData = new FormData(form);
                
                // Show loading state
                const submitBtn = form.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                
                try {
                    const res = await fetch('../save_project.php', { method: 'POST', body: formData });
                    
                    if (!res.ok) {
                        throw new Error(`HTTP error! status: ${res.status}`);
                    }
                    
                    const data = await res.json();
                    if (data && data.success) {
                        if (typeof showToast === 'function') {
                            showToast('Project saved successfully', 'success');
                        } else {
                            alert('Project saved successfully');
                        }
                        // Close modal and refresh page to reflect changes
                        closeProjectModal();
                        setTimeout(() => {
                            window.location.reload();
                        }, 500);
                    } else {
                        alert((data && data.message) || 'Failed to save project');
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                    }
                } catch (err) {
                    console.error('Error saving project:', err);
                    alert('Network error while saving project: ' + err.message);
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            });
        })();

        // Hamburger Menu Toggle
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        function toggleSidebar() {
            hamburgerBtn.classList.toggle('active');
            sidebar.classList.toggle('active');
            sidebarOverlay.classList.toggle('active');
        }

        hamburgerBtn.addEventListener('click', toggleSidebar);
        sidebarOverlay.addEventListener('click', toggleSidebar);

        // Close sidebar on navigation link click (mobile)
        const navLinks = sidebar.querySelectorAll('.nav-item, .logout-btn');
        navLinks.forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 1024) {
                    toggleSidebar();
                }
            });
        });

        // Close sidebar on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && sidebar.classList.contains('active')) {
                toggleSidebar();
            }
        });
    </script>
    </body>
    </html>