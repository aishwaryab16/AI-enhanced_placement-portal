<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/job_backend.php';

header('Content-Type: application/json');

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get the job ID and student ID from the request
$job_id = isset($_POST['job_id']) ? (int)$_POST['job_id'] : 0;
$student_id = (int)$_SESSION['user_id'];

if (!$job_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid job ID']);
    exit;
}

try {
    // Ensure tables (and columns) exist
    setupJobTables($mysqli);

    // Fetch job details
    $job_stmt = $mysqli->prepare("SELECT * FROM job_opportunities WHERE id = ?");
    $job_stmt->bind_param('i', $job_id);
    $job_stmt->execute();
    $job = $job_stmt->get_result()->fetch_assoc();
    $job_stmt->close();

    if (!$job) {
        echo json_encode(['success' => false, 'message' => 'Job not found']);
        exit;
    }

    // Check if already applied (by job_id for accuracy)
    $check = $mysqli->prepare("SELECT id FROM job_applications WHERE job_id = ? AND student_id = ?");
    $check->bind_param('ii', $job_id, $student_id);
    $check->execute();
    $already = $check->get_result()->fetch_assoc();
    $check->close();
    if ($already) {
        echo json_encode(['success' => false, 'message' => 'You have already applied for this job']);
        exit;
    }

    // Fetch student's username and full_name
    $user_stmt = $mysqli->prepare("SELECT username, full_name FROM users WHERE id = ?");
    $user_stmt->bind_param('i', $student_id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    $user = $user_result->fetch_assoc();
    $username = $user['username'] ?? '';
    $full_name = $user['full_name'] ?? '';
    $user_stmt->close();

    // Compute match percentage (simple default; can be enhanced)
    $match_percentage = 100;

    // Prepare values with safe defaults
    $job_role_val = $job['role'] ?? ($job['job_role'] ?? '');
    $company_val = $job['company'] ?? ($job['company_name'] ?? '');
    $location_val = $job['location'] ?? '';
    $min_cgpa_val = isset($job['min_cgpa']) && $job['min_cgpa'] !== '' ? (float)$job['min_cgpa'] : 0.0;
    $skills_val = $job['skills_required'] ?? '';
    $salary_range = "Rs " . (string)($job['ctc_min'] ?? 0) . "-" . (string)($job['ctc_max'] ?? 0) . " LPA";

    // Insert into comprehensive job_applications schema expected by track_applications.php
    $insert_query = "
        INSERT INTO job_applications 
        (job_id, student_id, username, full_name, job_title, company_name, job_role, location, salary_range, min_cgpa, required_skills, match_percentage, application_status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Applied')
    ";
    $stmt = $mysqli->prepare($insert_query);
    if ($stmt) {
        $stmt->bind_param(
            'iisssisssdsi',
            $job_id,
            $student_id,
            $username,
            $full_name,
            $job_role_val,
            $company_val,
            $job_role_val,
            $location_val,
            $salary_range,
            $min_cgpa_val,
            $skills_val,
            $match_percentage
        );

        $ok = $stmt->execute();
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to prepare insert: ' . $mysqli->error]);
        exit;
    }

    if ($ok) {
        // Update applications count in job_opportunities table
        $update = $mysqli->prepare("UPDATE job_opportunities SET applications_count = applications_count + 1 WHERE id = ?");
        $update->bind_param('i', $job_id);
        $update->execute();
        
        echo json_encode(['success' => true, 'message' => 'Application submitted successfully']);
    } else {
        throw new Exception('Failed to submit application');
    }
} catch (Exception $e) {
    error_log('Error in apply_job.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while processing your application']);
}
?>
