<?php
require_once __DIR__ . '/../includes/config.php';
require_role('student');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('job_opportunities.php');
}

$student_id = $_SESSION['user_id'];
$job_title = trim($_POST['job_title'] ?? '');
$company_name = trim($_POST['company_name'] ?? '');
$job_role = trim($_POST['job_role'] ?? '');
$location = trim($_POST['location'] ?? '');
$salary_range = trim($_POST['salary_range'] ?? '');
$min_cgpa = floatval($_POST['min_cgpa'] ?? 0);
$required_skills = trim($_POST['required_skills'] ?? '');
$match_percentage = intval($_POST['match_percentage'] ?? 0);

if (empty($job_title) || empty($company_name) || empty($job_role)) {
    $_SESSION['flash_error'] = 'Missing required job information.';
    redirect_to('job_opportunities.php');
}

// Check if student already applied for this job
$check_query = "SELECT id FROM job_applications WHERE student_id = ? AND job_title = ? AND company_name = ?";
$check_stmt = $mysqli->prepare($check_query);
$check_stmt->bind_param('iss', $student_id, $job_title, $company_name);
$check_stmt->execute();
$existing = $check_stmt->get_result()->fetch_assoc();
$check_stmt->close();

if ($existing) {
    $_SESSION['flash_error'] = 'You have already applied for this job.';
    redirect_to('job_opportunities.php');
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

// Insert new application
$insert_query = "
    INSERT INTO job_applications 
    (student_id, username, full_name, job_title, company_name, job_role, location, salary_range, min_cgpa, required_skills, match_percentage, application_status)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Applied')
";

$insert_stmt = $mysqli->prepare($insert_query);
$insert_stmt->bind_param('isssssssdsi', 
    $student_id,
    $username,
    $full_name,
    $job_title, 
    $company_name, 
    $job_role, 
    $location, 
    $salary_range, 
    $min_cgpa, 
    $required_skills, 
    $match_percentage
);

if ($insert_stmt->execute()) {
    $_SESSION['flash_success'] = 'Job application submitted successfully!';
} else {
    $_SESSION['flash_error'] = 'Failed to submit application. Please try again.';
}

$insert_stmt->close();
redirect_to('job_opportunities.php');
?>
