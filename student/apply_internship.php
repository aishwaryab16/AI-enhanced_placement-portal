<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/internship_backend.php';
require_role('student');

$student_id = $_SESSION['user_id'];
setupInternshipTables($mysqli);
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $internship_id = isset($_POST['internship_id']) ? (int) $_POST['internship_id'] : 0;

    if ($action === 'apply_internship') {
        if ($internship_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid internship ID']);
            exit;
        }

        // Get student username first
        $username_stmt = $mysqli->prepare("SELECT username FROM users WHERE id = ? LIMIT 1");
        if (!$username_stmt) {
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $mysqli->error]);
            exit;
        }
        $username_stmt->bind_param('i', $student_id);
        $username_stmt->execute();
        $username_result = $username_stmt->get_result()->fetch_assoc();
        $username_stmt->close();
        
        if (!$username_result || empty($username_result['username'])) {
            echo json_encode(['success' => false, 'error' => 'Student username not found. Please update your profile.']);
            exit;
        }
        $student_username = $username_result['username'];

        // Prevent duplicate applications
        $duplicate_check = $mysqli->prepare("SELECT id FROM internship_applications WHERE internship_id = ? AND username = ? LIMIT 1");
        if ($duplicate_check) {
            $duplicate_check->bind_param('is', $internship_id, $student_username);
            $duplicate_check->execute();
            $duplicate_check->store_result();
            if ($duplicate_check->num_rows > 0) {
                $duplicate_check->close();
                echo json_encode(['success' => false, 'error' => 'Already applied for this internship']);
                exit;
            }
            $duplicate_check->close();
        }

        // Fetch internship details
        $internship_stmt = $mysqli->prepare("SELECT * FROM internship_opportunities WHERE id = ? LIMIT 1");
        if (!$internship_stmt) {
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $mysqli->error]);
            exit;
        }
        $internship_stmt->bind_param('i', $internship_id);
        $internship_stmt->execute();
        $internship = $internship_stmt->get_result()->fetch_assoc();
        $internship_stmt->close();

        if (!$internship) {
            echo json_encode(['success' => false, 'error' => 'Internship not found']);
            exit;
        }

        // Fetch student information
        $student_stmt = $mysqli->prepare("SELECT username, full_name, cgpa, semester, year_of_passing FROM users WHERE id = ? LIMIT 1");
        if (!$student_stmt) {
            echo json_encode(['success' => false, 'error' => 'Unable to fetch student details']);
            exit;
        }
        $student_stmt->bind_param('i', $student_id);
        $student_stmt->execute();
        $student_row = $student_stmt->get_result()->fetch_assoc();
        $student_stmt->close();

        if (!$student_row) {
            echo json_encode(['success' => false, 'error' => 'Student profile not found']);
            exit;
        }

        $student_cgpa = isset($student_row['cgpa']) ? (float) $student_row['cgpa'] : 0.0;
        $student_year = 3;
        if (!empty($student_row['semester'])) {
            $student_year = (int) $student_row['semester'];
        } elseif (!empty($student_row['year_of_passing'])) {
            $student_year = (int) $student_row['year_of_passing'];
        }

        // Gather student skills
        $student_skills = [];
        $skills_result = $mysqli->query("SELECT skill_name FROM student_skills WHERE student_id = $student_id");
        if ($skills_result) {
            while ($skill_row = $skills_result->fetch_assoc()) {
                $student_skills[] = $skill_row['skill_name'];
            }
        }

        // Calculate match percentage using backend utility
        $match_data = calculateInternshipMatch($internship, $student_skills, $student_cgpa, $student_year);
        $match_percentage = (int) ($match_data['match_score'] ?? 100);

        $resume_json = '';
        $resume_check = $mysqli->query("SHOW TABLES LIKE 'generated_resumes'");
        if ($resume_check && $resume_check->num_rows > 0) {
            $resume_stmt = $mysqli->prepare("SELECT resume_json FROM generated_resumes WHERE username = ? LIMIT 1");
            if ($resume_stmt) {
                $resume_stmt->bind_param('s', $student_username);
                $resume_stmt->execute();
                $resume_result = $resume_stmt->get_result()->fetch_assoc();
                if ($resume_result && !empty($resume_result['resume_json'])) {
                    $resume_json = $resume_result['resume_json'];
                }
                $resume_stmt->close();
            }
        }

        if (empty($resume_json)) {
            echo json_encode(['success' => false, 'error' => 'No generated resume found. Please create one using the Resume Builder.']);
            exit;
        }

        // Ensure resume_json column exists (add if missing)
        $has_resume_json = false;
        $column_check = $mysqli->query("SHOW COLUMNS FROM internship_applications LIKE 'resume_json'");
        if ($column_check && $column_check->num_rows > 0) {
            $has_resume_json = true;
        } else {
            $mysqli->query("ALTER TABLE internship_applications ADD COLUMN resume_json MEDIUMTEXT NULL AFTER resume_path");
            $column_check_after = $mysqli->query("SHOW COLUMNS FROM internship_applications LIKE 'resume_json'");
            if ($column_check_after && $column_check_after->num_rows > 0) {
                $has_resume_json = true;
            }
        }

        $stipend_range = 'Rs ' . number_format((float) ($internship['stipend_min'] ?? 0), 2) . '-' . number_format((float) ($internship['stipend_max'] ?? 0), 2) . '/month';
        $min_cgpa = isset($internship['min_cgpa']) ? (float) $internship['min_cgpa'] : 0.0;
        $skills_required = $internship['skills_required'] ?? '';
        $location = $internship['location'] ?? '';

        $blank_path = '';
        if ($has_resume_json) {
            $insert_query = "INSERT INTO internship_applications (internship_id, username, internship_title, company_name, internship_role, location, stipend_range, min_cgpa, required_skills, match_percentage, application_status, resume_path, resume_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Applied', ?, ?)";
            $stmt = $mysqli->prepare($insert_query);
            if (!$stmt) {
                echo json_encode(['success' => false, 'error' => 'Database error: ' . $mysqli->error]);
                exit;
            }
            $internship_title = $internship['role'] ?? '';
            $company = $internship['company'] ?? '';
            $stmt->bind_param(
                'issssssdiss',
                $internship_id,
                $student_username,
                $internship_title,
                $company,
                $internship_title,
                $location,
                $stipend_range,
                $min_cgpa,
                $skills_required,
                $match_percentage,
                $blank_path,
                $resume_json
            );
        } else {
            // Fallback to old schema (store placeholder path)
            $insert_query = "INSERT INTO internship_applications (internship_id, username, internship_title, company_name, internship_role, location, stipend_range, min_cgpa, required_skills, match_percentage, application_status, resume_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Applied', ?)";
            $stmt = $mysqli->prepare($insert_query);
            if (!$stmt) {
                echo json_encode(['success' => false, 'error' => 'Database error: ' . $mysqli->error]);
                exit;
            }
            $placeholder_path = 'generated_resume_placeholder';
            $internship_title = $internship['role'] ?? '';
            $company = $internship['company'] ?? '';
            $stmt->bind_param(
                'issssssdiss',
                $internship_id,
                $student_username,
                $internship_title,
                $company,
                $internship_title,
                $location,
                $stipend_range,
                $min_cgpa,
                $skills_required,
                $match_percentage,
                $placeholder_path
            );
        }

        $success = $stmt->execute();
        $error = $success ? null : ($stmt->error ?: $mysqli->error);
        $stmt->close();

        echo json_encode(['success' => $success, 'error' => $error]);
        exit;
    }

    if ($action === 'save_internship') {
        $stmt = $mysqli->prepare('INSERT IGNORE INTO saved_internships (student_id, internship_id) VALUES (?, ?)');
        if ($stmt) {
            $stmt->bind_param('ii', $student_id, $internship_id);
            $stmt->execute();
            $stmt->close();
        }
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'unsave_internship') {
        $stmt = $mysqli->prepare('DELETE FROM saved_internships WHERE student_id = ? AND internship_id = ?');
        if ($stmt) {
            $stmt->bind_param('ii', $student_id, $internship_id);
            $stmt->execute();
            $stmt->close();
        }
        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action']);
    exit;
}

echo json_encode(['success' => false, 'error' => 'POST required']);
