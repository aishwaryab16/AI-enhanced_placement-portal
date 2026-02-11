<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/job_backend.php';
require_role('student');

$student_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Setup tables
setupJobTables($mysqli);
// Only insert sample data if explicitly requested (not on every page load)
// insertSampleData($mysqli); // Commented out to prevent re-inserting deleted data

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Turn off error display, but log errors
    ini_set('display_errors', 0);
    error_reporting(E_ALL);
    
    // Start output buffering to catch any unexpected output
    ob_start();
    
    // Set JSON header
       header('Content-Type: application/json');
    
    try {
        // Get action from POST (might be in FormData)
        $action = $_POST['action'] ?? 'apply_job';
        
        if ($action === 'apply_job') {
            $job_id = isset($_POST['job_id']) ? (int)$_POST['job_id'] : 0;
            
            // Validate job_id
            if ($job_id <= 0) {
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'Invalid job ID']);
                exit;
            }
            
            // Get job details from job_opportunities table
            $job_query = "SELECT * FROM job_opportunities WHERE id = ?";
            $job_stmt = $mysqli->prepare($job_query);
            if (!$job_stmt) {
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'Database error: ' . $mysqli->error]);
                exit;
            }
            $job_stmt->bind_param('i', $job_id);
            $job_stmt->execute();
            $job = $job_stmt->get_result()->fetch_assoc();
            $job_stmt->close();
            
            if (!$job) {
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'Job not found']);
                exit;
            }
            
            // Check if already applied - only check by job_id for jobs with valid job_id
            $existing = null;

            if ($job_id > 0) {
                $check_stmt = $mysqli->prepare("SELECT ja.id FROM job_applications ja INNER JOIN job_opportunities jo ON jo.id = ja.job_id WHERE ja.student_id = ? AND ja.job_id = ? AND (jo.created_at IS NULL OR ja.applied_at IS NULL OR ja.applied_at >= jo.created_at) LIMIT 1");
                if ($check_stmt) {
                    $check_stmt->bind_param('ii', $student_id, $job_id);
                    $check_stmt->execute();
                    $existing = $check_stmt->get_result()->fetch_assoc();
                    $check_stmt->close();
                }
            } else {
                // Only do fallback check if job_id is actually NULL/0 (legacy jobs)
                // For new jobs from manage_jobs.php, job_id should always be valid
                $job_title = $job['job_title'] ?? ($job['role'] ?? ($job['job_role'] ?? ''));
                $job_company = $job['company'] ?? ($job['company_name'] ?? '');
                if (!empty($job_title) && !empty($job_company)) {
                    $check_stmt = $mysqli->prepare("SELECT id FROM job_applications WHERE student_id = ? AND job_id IS NULL AND job_title = ? AND company_name = ? LIMIT 1");
                    if ($check_stmt) {
                        $check_stmt->bind_param('iss', $student_id, $job_title, $job_company);
                        $check_stmt->execute();
                        $existing = $check_stmt->get_result()->fetch_assoc();
                        $check_stmt->close();
                    }
                }
            }
            
            if ($existing) {
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'Already applied for this job']);
                exit;
            }
            
            // Fetch student's username and full_name
            $user_stmt = $mysqli->prepare("SELECT username, full_name FROM users WHERE id = ?");
            if (!$user_stmt) {
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'Database error: ' . $mysqli->error]);
                exit;
            }
            $user_stmt->bind_param('i', $student_id);
            $user_stmt->execute();
            $user_result = $user_stmt->get_result();
            $user = $user_result->fetch_assoc();
            $username = $user['username'] ?? '';
            $full_name = $user['full_name'] ?? '';
            $user_stmt->close();

            // Fetch latest generated resume (JSON) for the username
            $resume_json = '';
            if (!empty($username)) {
                // Check if generated_resumes table exists first
                $table_check = $mysqli->query("SHOW TABLES LIKE 'generated_resumes'");
                if ($table_check && $table_check->num_rows > 0) {
                    $resume_stmt = $mysqli->prepare("SELECT resume_json FROM generated_resumes WHERE username = ? LIMIT 1");
                    if ($resume_stmt) {
                        $resume_stmt->bind_param('s', $username);
                        $resume_stmt->execute();
                        $resume_result = $resume_stmt->get_result();
                        if ($resume_row = $resume_result->fetch_assoc()) {
                            $resume_json = $resume_row['resume_json'] ?? '';
                        }
                        $resume_stmt->close();
                    }
                }
            }

            // Calculate match percentage (simplified)
            $match_percentage = 100; // Default match
            
            // Check if resume_json column exists, if not use resume_path column
            $has_resume_json = false;
            try {
                $check_column = $mysqli->query("SHOW COLUMNS FROM job_applications LIKE 'resume_json'");
                $has_resume_json = $check_column && $check_column->num_rows > 0;
            } catch (Exception $e) {
                // Column doesn't exist, use fallback
                $has_resume_json = false;
            }
            
            if ($has_resume_json) {
                $insert_query = "
                    INSERT INTO job_applications 
                    (job_id, student_id, username, full_name, resume_json, job_title, company_name, job_role, location, salary_range, min_cgpa, required_skills, match_percentage, application_status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Applied')
                ";
            } else {
                // Fallback to old structure if resume_json column doesn't exist
                $insert_query = "
                    INSERT INTO job_applications 
                    (job_id, student_id, username, full_name, resume_path, job_title, company_name, job_role, location, salary_range, min_cgpa, required_skills, match_percentage, application_status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Applied')
                ";
            }
            
            // Safe defaults for any nullable job fields
            $job_title_val = $job['job_title'] ?? ($job['role'] ?? ($job['job_role'] ?? ''));
            $job_role_val = $job['role'] ?? ($job['job_role'] ?? '');
            $company_val = $job['company'] ?? ($job['company_name'] ?? '');
            $location_val = $job['location'] ?? '';
            $min_cgpa_val = isset($job['min_cgpa']) && $job['min_cgpa'] !== '' ? (float)$job['min_cgpa'] : 0.0;
            $skills_val = $job['skills_required'] ?? '';
            $salary_range = "Rs " . (string)($job['ctc_min'] ?? 0) . "-" . (string)($job['ctc_max'] ?? 0) . " LPA";
            $resume_path = ''; // Empty for new system

            $insert_stmt = $mysqli->prepare($insert_query);
            if ($insert_stmt) {
                // Ensure job_id is saved correctly - use the validated job_id (already validated above)
                $job_id_param = (int)$job_id;
                if ($has_resume_json) {
                    $insert_stmt->bind_param('iissssssssdsi', 
                        $job_id_param,
                        $student_id,
                        $username,
                        $full_name,
                        $resume_json,
                        $job_title_val,
                        $company_val, 
                        $job_role_val,
                        $location_val, 
                        $salary_range, 
                        $min_cgpa_val, 
                        $skills_val, 
                        $match_percentage
                    );
                } else {
                    $insert_stmt->bind_param('iissssssssdsi', 
                        $job_id_param,
                        $student_id,
                        $username,
                        $full_name,
                        $resume_path,
                        $job_title_val,
                        $company_val, 
                        $job_role_val,
                        $location_val, 
                        $salary_range, 
                        $min_cgpa_val, 
                        $skills_val, 
                        $match_percentage
                    );
                }
                $success = $insert_stmt->execute();
                if (!$success) {
                    $error = $insert_stmt->error ?: $mysqli->error;
                    error_log("Failed to insert job application: " . $error);
                    error_log("Job ID: " . $job_id . ", Student ID: " . $student_id);
                    error_log("Query: " . $insert_query);
                    error_log("Has resume_json: " . ($has_resume_json ? 'yes' : 'no'));
                } else {
                    $error = null;
                    // Log successful insertion for debugging
                    error_log("Successfully inserted job application - Job ID: " . $job_id_param . ", Student ID: " . $student_id);
                }
                $insert_stmt->close();
            } else {
                $success = false;
                $error = $mysqli->error;
                error_log("Failed to prepare statement: " . $error);
                error_log("Query: " . $insert_query);
            }
            
            // Clean any output buffer and send JSON response
            ob_end_clean();
            if ($success) {
                echo json_encode(['success' => true, 'error' => null, 'job_id' => $job_id_param]);
            } else {
                echo json_encode(['success' => false, 'error' => $error ?? 'Unknown error occurred']);
            }
            exit;
    } elseif ($action === 'save_job') {
        $job_id = $_POST['job_id'] ?? 0;
        $stmt = $mysqli->prepare('INSERT IGNORE INTO saved_jobs (student_id, job_id) VALUES (?, ?)');
        $stmt->bind_param('ii', $student_id, $job_id);
        $success = $stmt->execute();
        $stmt->close();
        ob_end_clean();
        echo json_encode(['success' => $success]);
        exit;
    } elseif ($action === 'unsave_job') {
        $job_id = $_POST['job_id'] ?? 0;
        $stmt = $mysqli->prepare('DELETE FROM saved_jobs WHERE student_id = ? AND job_id = ?');
        $stmt->bind_param('ii', $student_id, $job_id);
        $success = $stmt->execute();
        $stmt->close();
        ob_end_clean();
        echo json_encode(['success' => $success]);
        exit;
        } else {
            ob_end_clean();
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            exit;
        }
    } catch (Exception $e) {
        // Catch any exceptions and return JSON error
        ob_end_clean();
        error_log("Error in job_opportunities.php POST handler: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'error' => 'An error occurred while processing your request. Please try again.',
            'debug' => (ini_get('display_errors') ? $e->getMessage() : null)
        ]);
        exit;
    } catch (Error $e) {
        // Catch fatal errors (PHP 7+)
        ob_end_clean();
        error_log("Fatal error in job_opportunities.php POST handler: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'error' => 'A fatal error occurred. Please check the server logs.',
            'debug' => (ini_get('display_errors') ? $e->getMessage() : null)
        ]);
        exit;
    }
}

// Fetch student data
$stmt = $mysqli->prepare('SELECT * FROM users WHERE id = ?');
$stmt->bind_param('i', $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

$student_username = $student['username'] ?? '';

$student_cgpa = $student['cgpa'] ?? 0;
$student_year = $student['year'] ?? 3;

// Fetch student skills
$skills = [];
$result = $mysqli->query("SELECT skill_name FROM student_skills WHERE student_id = $student_id");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $skills[] = $row['skill_name'];
    }
}

// Get jobs with match scores
$jobs = getJobsWithMatches($mysqli, $student_id, $skills, $student_cgpa, $student_year);
$saved_job_ids = getSavedJobIds($mysqli, $student_id);
$applied_jobs = getAppliedJobs($mysqli, $student_id, $student_username);

// Get filter parameters
$filter_company = $_GET['company'] ?? '';
$filter_role = $_GET['role'] ?? '';
$filter_eligible_only = isset($_GET['eligible_only']);

// Apply filters
if ($filter_company || $filter_role || $filter_eligible_only) {
    $jobs = array_filter($jobs, function($job) use ($filter_company, $filter_role, $filter_eligible_only) {
        $job_company = $job['company'] ?? '';
        $job_role = $job['role'] ?? '';
        if ($filter_company && stripos((string)$job_company, $filter_company) === false) return false;
        if ($filter_role && stripos((string)$job_role, $filter_role) === false) return false;
        if ($filter_eligible_only && !$job['is_eligible']) return false;
        return true;
    });
}

// Get applied job IDs - filter out NULL, empty strings, and 0 values (0 means no job_id)
$applied_job_ids = [];
foreach ($applied_jobs as $appRow) {
    $jid = isset($appRow['job_id']) ? (int)$appRow['job_id'] : 0;
    if ($jid > 0) {
        $applied_job_ids[] = $jid;
    }
}
$applied_job_ids = array_unique($applied_job_ids); // Remove duplicates
$applied_job_ids = array_values($applied_job_ids); // Re-index array

// Map of job_id => application_status for quick lookups
$applied_job_status_by_id = [];
foreach ($applied_jobs as $appRow) {
    $jid = isset($appRow['job_id']) ? (int)$appRow['job_id'] : 0;
    if ($jid > 0) {
        $applied_job_status_by_id[$jid] = $appRow['application_status'] ?? 'Applied';
    }
}
// Find shortlisted applications for notification banner
$shortlisted_apps = array_values(array_filter($applied_jobs, function($row) {
    return isset($row['application_status']) && $row['application_status'] === 'Shortlisted';
}));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Opportunities - PlacementHub</title>
    <link rel="stylesheet" href="../assets/css/fontawesome-all.min.css">
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

        /* Main Content */
        .main-wrapper {
            margin-left: 240px;
            flex: 1;
            padding: 20px;
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .breadcrumb {
            color: #6b7280;
            font-size: 14px;
        }

        .header-banner {
            background: linear-gradient(135deg, #5b1f1f 0%, #8b3a3a 50%, #ecc35c 100%);
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 30px;
        }

        .header-banner h1 {
            color: white;
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .header-banner p {
            color: rgba(255,255,255,0.9);
            font-size: 16px;
        }

        /* Shortlist Notification */
        .shortlist-banner {
            background: #fff7ed;
            border: 1px solid #fed7aa;
            color: #92400e;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .shortlist-banner-title {
            font-weight: 700;
            margin-bottom: 8px;
        }
        .shortlist-list {
            margin-left: 18px;
        }
        .shortlist-list li { margin: 4px 0; }

        /* Filters */
        .filters {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .filter-row {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-input {
            flex: 1;
            min-width: 200px;
            padding: 10px 15px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
        }

        .filter-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #5b1f1f;
            color: white;
        }

        .btn-primary:hover {
            background: #3d1414;
        }

        /* Job Grid */
        .job-grid {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 20px;
        }

        .jobs-main {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .job-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: all 0.3s;
        }

        .job-card:hover {
            box-shadow: 0 4px 16px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }

        .job-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }

        .job-company {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .company-logo {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #5b1f1f, #ecc35c);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 18px;
        }

        .job-title {
            font-size: 18px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 4px;
        }

        .job-company-name {
            color: #6b7280;
            font-size: 14px;
        }

        .match-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 13px;
        }

        .match-high {
            background: #d1fae5;
            color: #065f46;
        }

        .match-medium {
            background: #fef3c7;
            color: #92400e;
        }

        .match-low {
            background: #fee2e2;
            color: #991b1b;
        }

        .job-details {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .job-detail {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #6b7280;
            font-size: 14px;
        }

        .job-detail i {
            color: #5b1f1f;
        }

        .job-skills {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 15px;
        }

        .skill-tag {
            padding: 4px 12px;
            background: #f3f4f6;
            border-radius: 6px;
            font-size: 12px;
        }

        .skill-tag.matched {
            background: #d1fae5;
            color: #065f46;
        }

        .skill-tag.missing {
            background: #fee2e2;
            color: #991b1b;
        }

        .job-actions {
            display: flex;
            gap: 10px;
        }

        .btn-apply {
            flex: 1;
            background: #5b1f1f;
            color: white;
            padding: 10px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-apply:hover {
            background: #3d1414;
        }

        .btn-apply:disabled {
            background: #9ca3af;
            cursor: not-allowed;
        }

        .btn-save {
            width: 40px;
            height: 40px;
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            cursor: pointer;
        }

        .btn-save.saved {
            background: #5b1f1f;
            color: white;
        }

        /* Sidebar Widgets */
        .sidebar-widgets {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .widget {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .widget-header {
            font-size: 16px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .drive-item {
            padding: 12px;
            background: #f9fafb;
            border-radius: 8px;
            margin-bottom: 10px;
            border-left: 4px solid #5b1f1f;
        }

        .drive-name {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 4px;
            font-size: 14px;
        }

        .drive-date {
            color: #6b7280;
            font-size: 13px;
        }

        .application-item {
            padding: 12px;
            background: #f9fafb;
            border-radius: 8px;
            margin-bottom: 10px;
        }

        .app-company {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 4px;
        }

        .app-status {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            background: #dbeafe;
            color: #1e40af;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 10000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 20px;
            padding: 30px;
            max-width: 700px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e5e7eb;
        }

        .modal-title {
            font-size: 24px;
            font-weight: 700;
            color: #1f2937;
        }

        .modal-close {
            width: 32px;
            height: 32px;
            background: #f3f4f6;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: #6b7280;
        }

        .modal-close:hover {
            background: #e5e7eb;
        }

        .modal-section {
            margin-bottom: 20px;
        }

        .modal-section-title {
            font-size: 16px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .modal-section-content {
            color: #6b7280;
            line-height: 1.6;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #f3f4f6;
        }

        .detail-label {
            font-weight: 600;
            color: #1f2937;
        }

        .detail-value {
            color: #6b7280;
        }

        @media (max-width: 1200px) {
            .job-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include __DIR__ . '/../includes/partials/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-wrapper">
        <!-- Top Bar -->
        <div class="top-bar">
            
        </div>

        <!-- Header Banner -->
        <div class="header-banner">
            <h1>💼 Job Opportunities</h1>
            <p>AI-Matched jobs based on your skills and profile</p>
        </div>

        <?php if (!empty($shortlisted_apps)): ?>
            <div class="shortlist-banner">
                <div class="shortlist-banner-title">⭐ You have been shortlisted!</div>
                <ul class="shortlist-list">
                    <?php foreach ($shortlisted_apps as $s): ?>
                        <li>
                            <?php echo htmlspecialchars(($s['company'] ?? $s['company_name'] ?? 'Company'));
                            echo ' - ' . htmlspecialchars(($s['role'] ?? $s['job_role'] ?? $s['job_title'] ?? 'Role')); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="filters">
            <form method="GET" class="filter-row">
                <input type="text" name="company" placeholder="Company" class="filter-input" value="<?php echo htmlspecialchars($filter_company); ?>">
                <input type="text" name="role" placeholder="Job Role" class="filter-input" value="<?php echo htmlspecialchars($filter_role); ?>">
                <label class="filter-checkbox">
                    <input type="checkbox" name="eligible_only" <?php echo $filter_eligible_only ? 'checked' : ''; ?>>
                    <span>Eligible Only</span>
                </label>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter"></i> Filter
                </button>
                <a href="job_opportunities.php" class="btn" style="background: #e5e7eb; color: #1f2937;">Clear</a>
            </form>
        </div>

        <!-- Job Grid -->
        <div class="job-grid">
            <!-- Jobs Main -->
            <div class="jobs-main">
                <?php foreach ($jobs as $job): 
                    // Get job ID - prioritize 'id' field from job_opportunities table
                    $jid = 0;
                    if (isset($job['id']) && $job['id'] !== null && $job['id'] !== '') {
                        $jid = (int)$job['id'];
                    } elseif (isset($job['job_id']) && $job['job_id'] !== null && $job['job_id'] !== '') {
                        $jid = (int)$job['job_id'];
                    }
                    
                    // Check if applied - ensure array contains integers
                    $is_applied = $jid > 0 && in_array($jid, $applied_job_ids, true);
                ?>
                <div class="job-card">
                    <div class="job-header">
                        <div class="job-company">
                            <div class="company-logo">
                                <?php echo strtoupper(substr($job['company'], 0, 1)); ?>
                            </div>
                            <div>
                                <div class="job-title">
                                    <?php echo htmlspecialchars($job['role']); ?>
                                    <?php if (stripos($job['role'], 'intern') !== false): ?>
                                        <span style="background: linear-gradient(135deg, #3b82f6, #60a5fa); color: white; padding: 3px 8px; border-radius: 6px; font-size: 11px; margin-left: 8px; font-weight: 600;">INTERNSHIP</span>
                                    <?php endif; ?>
                                    <?php if (isset($applied_job_status_by_id[$jid]) && $applied_job_status_by_id[$jid] === 'Shortlisted'): ?>
                                        <span style="background: #fef3c7; color: #92400e; padding: 3px 8px; border-radius: 6px; font-size: 11px; margin-left: 8px; font-weight: 700;">SHORTLISTED</span>
                                    <?php endif; ?>
                                </div>
                                <div class="job-company-name"><?php 
                                    $companyName = $job['company'] ?? '';
                                    if (!$companyName && isset($job['company_name'])) { $companyName = $job['company_name']; }
                                    if (!$companyName) { $companyName = 'Unknown Company'; }
                                    echo htmlspecialchars($companyName);
                                ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="job-details">
                        <div class="job-detail">
                            <i class="fas fa-map-marker-alt"></i>
                            <?php echo !empty($job['location']) ? htmlspecialchars($job['location']) : 'Location not specified'; ?>
                        </div>
                        <div class="job-detail">
                            <i class="fas fa-rupee-sign"></i>
                            <?php 
                            $ctc_min = (float)($job['ctc_min'] ?? 0);
                            $ctc_max = (float)($job['ctc_max'] ?? 0);
                            $isInternship = stripos($job['role'], 'intern') !== false;
                            
                            if ($ctc_min > 0 || $ctc_max > 0) {
                                if ($isInternship) {
                                    echo 'Rs ' . number_format($ctc_min * 100, 0) . 'k-' . number_format($ctc_max * 100, 0) . 'k/month';
                                } else {
                                    echo 'Rs ' . number_format($ctc_min, 2) . '-' . number_format($ctc_max, 2) . ' LPA';
                                }
                            } else {
                                echo 'Salary not specified';
                            }
                            ?>
                        </div>
                        <div class="job-detail">
                            <i class="fas fa-graduation-cap"></i>
                            Min CGPA: <?php 
                            $min_cgpa = (float)($job['min_cgpa'] ?? 0);
                            echo $min_cgpa > 0 ? number_format($min_cgpa, 2) : 'Not specified';
                            ?>
                        </div>
                    </div>

                    <div class="job-skills">
                        <?php 
                        $skills_required = trim($job['skills_required'] ?? '');
                        if (!empty($skills_required)) {
                            $job_skills = array_filter(array_map('trim', explode(',', $skills_required)));
                            if (!empty($job_skills)) {
                                foreach ($job_skills as $skill): 
                                    if (empty($skill)) continue;
                                    $is_matched = in_array(strtolower($skill), array_map('strtolower', $skills));
                                ?>
                                    <span class="skill-tag <?php echo $is_matched ? 'matched' : 'missing'; ?>">
                                        <?php echo htmlspecialchars($skill); ?>
                                        <?php echo $is_matched ? '✓' : ''; ?>
                                    </span>
                                <?php endforeach; 
                            } else {
                                echo '<span style="color: #6b7280; font-size: 14px;">Skills not specified</span>';
                            }
                        } else {
                            echo '<span style="color: #6b7280; font-size: 14px;">Skills not specified</span>';
                        }
                        ?>
                    </div>

                    <div class="job-actions">
                        <button class="btn-apply" onclick="viewJobDetails(<?php echo $jid; ?>)" style="background: linear-gradient(135deg, #5b1f1f, #ecc35c); flex: 0.5;">
                            <i class="fas fa-info-circle"></i> View Details
                        </button>
                        <button class="btn-apply" data-job-id="<?php echo $jid; ?>" onclick="applyJob(<?php echo $jid; ?>)" <?php echo $is_applied ? 'disabled style="background: #9ca3af; cursor: not-allowed;"' : ''; ?>>
                            <?php echo $is_applied ? 'Applied ✓' : 'Apply Now'; ?>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Sidebar Widgets -->
            <div class="sidebar-widgets">
                <!-- Application Tracker -->
                <div class="widget">
                    <div class="widget-header">
                        <i class="fas fa-tasks"></i>
                        My Applications
                    </div>
                    <?php if (empty($applied_jobs)): ?>
                        <p style="color: #6b7280; font-size: 14px;">No applications yet</p>
                    <?php else: ?>
                        <?php foreach (array_slice($applied_jobs, 0, 5) as $app): ?>
                            <div class="application-item">
                                <div class="app-company"><?php echo htmlspecialchars($app['company'] ?? $app['company_name'] ?? ''); ?> - <?php echo htmlspecialchars($app['role'] ?? $app['job_role'] ?? $app['job_title'] ?? ''); ?></div>
                                <span class="app-status"><?php echo htmlspecialchars($app['application_status'] ?? 'Applied'); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Job Details Modal -->
    <div id="jobModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="modalJobTitle">Job Details</h2>
                <button class="modal-close" onclick="closeModal()">×</button>
            </div>
            <div id="modalJobContent"></div>
        </div>
    </div>

    <!-- Resume Upload Modal -->
    <div id="resumeModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h2 class="modal-title">Confirm Application</h2>
                <button class="modal-close" onclick="closeResumeModal()">×</button>
            </div>
            <div style="padding: 20px 0;">
                <form id="resumeUploadForm">
                    <input type="hidden" id="resumeJobId" name="job_id" value="">
                    <div style="margin-bottom: 20px; color: #333; font-weight: 600; font-size: 16px;">
                        <i class="fas fa-file-alt"></i> Are you sure you want to apply to this job? Your latest generated resume will be attached automatically.
                    </div>
                    <div style="display: flex; gap: 10px; justify-content: flex-end;">
                        <button type="button" onclick="closeResumeModal()" style="padding: 10px 20px; background: #e5e7eb; color: #1f2937; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                            Cancel
                        </button>
                        <button type="submit" style="padding: 10px 20px; background: #5b1f1f; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                            <i class="fas fa-paper-plane"></i> Confirm & Apply
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Store jobs data for modal
        const jobsData = <?php echo json_encode($jobs); ?>;

        function viewJobDetails(jobId) {
            const job = jobsData.find(j => j.id == jobId);
            if (!job) return;

            document.getElementById('modalJobTitle').textContent = job.role + ' at ' + job.company;
            
            const matchClass = job.match_score >= 80 ? 'match-high' : (job.match_score >= 60 ? 'match-medium' : 'match-low');
            
            const skills = job.skills_required.split(',');
            const studentSkills = <?php echo json_encode(array_map('strtolower', $skills)); ?>;
            
            let skillsHTML = '';
            skills.forEach(skill => {
                const isMatched = studentSkills.includes(skill.trim().toLowerCase());
                skillsHTML += `<span class="skill-tag ${isMatched ? 'matched' : 'missing'}" style="margin: 4px;">${skill.trim()} ${isMatched ? '✓' : ''}</span>`;
            });

            const eligibilityHTML = job.is_eligible 
                ? '<span style="color: #10b981; font-weight: 600;">✓ You are eligible for this role</span>'
                : '<span style="color: #ef4444; font-weight: 600;">✗ You may not meet all requirements</span>';

            document.getElementById('modalJobContent').innerHTML = `
                <div class="modal-section">
                    <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 20px;">
                        <div class="company-logo" style="width: 60px; height: 60px; font-size: 24px;">
                            ${job.company.charAt(0)}
                        </div>
                        <div>
                            <h3 style="font-size: 20px; color: #1f2937; margin-bottom: 5px;">${job.role}</h3>
                            <p style="color: #6b7280;">${job.company}</p>
                        </div>
                        <div class="match-badge ${matchClass}" style="margin-left: auto;">
                            ${job.match_score}% Match
                        </div>
                    </div>
                </div>

                <div class="modal-section">
                    <div class="modal-section-title">
                        <i class="fas fa-info-circle"></i>
                        Job Description
                    </div>
                    <div class="modal-section-content">
                        ${job.description}
                    </div>
                </div>

                <div class="modal-section">
                    <div class="modal-section-title">
                        <i class="fas fa-briefcase"></i>
                        Job Details
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Location</span>
                        <span class="detail-value"><i class="fas fa-map-marker-alt"></i> ${job.location}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Salary Range</span>
                        <span class="detail-value"><i class="fas fa-rupee-sign"></i> ${job.ctc_min} - ${job.ctc_max} LPA</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Minimum CGPA</span>
                        <span class="detail-value">${job.min_cgpa}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Eligible Years</span>
                        <span class="detail-value">Year ${job.eligible_years}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Application Deadline</span>
                        <span class="detail-value"><i class="fas fa-calendar"></i> ${job.deadline ? new Date(job.deadline).toLocaleDateString('en-US', {year: 'numeric', month: 'long', day: 'numeric'}) : 'No deadline specified'}</span>
                    </div>
                </div>

                <div class="modal-section">
                    <div class="modal-section-title">
                        <i class="fas fa-code"></i>
                        Required Skills
                    </div>
                    <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px;">
                        ${skillsHTML}
                    </div>
                </div>

                <div class="modal-section">
                    <div class="modal-section-title">
                        <i class="fas fa-check-circle"></i>
                        Eligibility Status
                    </div>
                    <div class="modal-section-content">
                        ${eligibilityHTML}
                        <div style="margin-top: 15px;">
                            <strong>Your Match Breakdown:</strong>
                            <div style="margin-top: 10px;">
                                <div style="margin-bottom: 8px;">
                                    <span style="color: #6b7280;">Skills Match:</span>
                                    <span style="font-weight: 600; color: #5b1f1f;">${job.skill_match}%</span>
                                </div>
                                <div style="margin-bottom: 8px;">
                                    <span style="color: #6b7280;">CGPA Match:</span>
                                    <span style="font-weight: 600; color: #5b1f1f;">${job.cgpa_match}%</span>
                                </div>
                                <div style="margin-bottom: 8px;">
                                    <span style="color: #6b7280;">Year Eligibility:</span>
                                    <span style="font-weight: 600; color: #5b1f1f;">${job.year_match}%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 25px;">
                    <button class="btn-apply" onclick="applyJobFromModal(${job.id})" style="flex: 1;">
                        <i class="fas fa-paper-plane"></i> Apply Now
                    </button>
                    <button class="btn" onclick="closeModal()" style="background: #e5e7eb; color: #1f2937;">
                        Close
                    </button>
                </div>
            `;

            document.getElementById('jobModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('jobModal').classList.remove('active');
        }

        function applyJobFromModal(jobId) {
            closeModal();
            applyJob(jobId);
        }

        function applyJob(jobId) {
            // Accept jobId 0 as valid (some legacy rows may have id=0)
            if (jobId === undefined || jobId === null || Number.isNaN(Number(jobId))) {
                alert('Invalid job. Please refresh and try again.');
                return;
            }
            
            // Show resume upload modal
            document.getElementById('resumeJobId').value = jobId;
            document.getElementById('resumeModal').classList.add('active');
        }

        function closeResumeModal() {
            document.getElementById('resumeModal').classList.remove('active');
            document.getElementById('resumeUploadForm').reset();
        }

        // Handle resume upload form submission
        document.getElementById('resumeUploadForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const jobId = document.getElementById('resumeJobId').value;

            const formData = new FormData();
            formData.append('action', 'apply_job');
            formData.append('job_id', jobId);

            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Applying...';

            fetch('job_opportunities.php', {
                method: 'POST',
                body: formData
            })
            .then(async response => {
                if (!response.ok) {
                    let errorData;
                    try { errorData = await response.json(); } catch (e) { errorData = {}; }
                    throw new Error(errorData.error || `Server error (${response.status})`);
                }
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    const text = await response.text();
                    throw new Error('Server returned non-JSON response: ' + text.substring(0, 200));
                }
                return response.json();
            })
            .then(data => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
                if (data.success) {
                    const jobId = document.getElementById('resumeJobId').value;
                    
                    // Close modal first
                    closeResumeModal();
                    
                    // Update the button state immediately - use data attribute for reliable selection
                    const jobIdNum = parseInt(jobId);
                    const applyButtons = document.querySelectorAll(`button.btn-apply[data-job-id="${jobIdNum}"]`);
                    
                    console.log('Found buttons to update:', applyButtons.length, 'for job ID:', jobIdNum);
                    
                    // Update all found buttons
                    applyButtons.forEach(btn => {
                        btn.disabled = true;
                        btn.innerHTML = 'Applied ✓';
                        btn.style.background = '#9ca3af';
                        btn.style.cursor = 'not-allowed';
                        btn.setAttribute('onclick', ''); // Remove onclick
                        btn.removeAttribute('onclick'); // Also remove the attribute
                        console.log('Button updated:', btn);
                    });
                    
                    // Fallback: If no buttons found by data attribute, try onclick attribute
                    if (applyButtons.length === 0) {
                        console.log('No buttons found by data attribute, trying onclick...');
                        const allButtons = document.querySelectorAll('button.btn-apply');
                        allButtons.forEach(btn => {
                            const onclickAttr = btn.getAttribute('onclick') || '';
                            if (onclickAttr.includes(`applyJob(${jobId})`) || onclickAttr.includes(`applyJob(${jobIdNum})`)) {
                                btn.disabled = true;
                                btn.innerHTML = 'Applied ✓';
                                btn.style.background = '#9ca3af';
                                btn.style.cursor = 'not-allowed';
                                btn.setAttribute('onclick', '');
                                console.log('Button updated via onclick fallback:', btn);
                            }
                        });
                    }
                    
                    // Update "My Applications" sidebar immediately
                    updateApplicationsSidebar(jobId);
                    
                    // Show success message
                    alert('Application submitted successfully!');
                    
                    // Don't reload - keep the updated state on the page
                } else {
                    alert(data.error || 'Failed to submit application. Please try again.');
                }
            })
            .catch(error => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
                const errorMsg = error.message || 'Error submitting application. Please try again.';
                alert(errorMsg);
                console.error('Error:', error);
            });
        });

        function toggleSave(jobId, btn) {
            const isSaved = btn.classList.contains('saved');
            const action = isSaved ? 'unsave_job' : 'save_job';
            
            fetch('job_opportunities.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=${action}&job_id=${jobId}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    btn.classList.toggle('saved');
                }
            });
        }

        // Function to update the "My Applications" sidebar after applying
        function updateApplicationsSidebar(jobId) {
            try {
                // Find the job details from jobsData
                const job = jobsData.find(j => j.id == jobId || j.id == parseInt(jobId));
                if (!job) {
                    console.log('Job not found in jobsData for ID:', jobId);
                    return;
                }

                // Find the applications widget by searching all widgets
                const widgets = document.querySelectorAll('.widget');
                let applicationsWidgetEl = null;
                widgets.forEach(w => {
                    const header = w.querySelector('.widget-header');
                    if (header) {
                        const headerText = header.textContent || header.innerText || '';
                        if (headerText.includes('My Applications')) {
                            applicationsWidgetEl = w;
                        }
                    }
                });

                if (!applicationsWidgetEl) {
                    console.log('Applications widget not found');
                    return;
                }

                // Check if "No applications yet" message exists
                const noAppsMsg = applicationsWidgetEl.querySelector('p');
                if (noAppsMsg) {
                    const msgText = noAppsMsg.textContent || noAppsMsg.innerText || '';
                    if (msgText.includes('No applications yet')) {
                        noAppsMsg.remove();
                    }
                }

                // Create new application item
                const companyName = job.company || job.company_name || 'Unknown Company';
                const jobRole = job.role || job.job_title || 'Unknown Role';
                
                const newAppItem = document.createElement('div');
                newAppItem.className = 'application-item';
                newAppItem.innerHTML = `
                    <div class="app-company">${escapeHtml(companyName)} - ${escapeHtml(jobRole)}</div>
                    <span class="app-status">Applied</span>
                `;
                
                // Insert at the top of the list
                const firstItem = applicationsWidgetEl.querySelector('.application-item');
                if (firstItem) {
                    applicationsWidgetEl.insertBefore(newAppItem, firstItem);
                } else {
                    // If no existing items, append after the header
                    const header = applicationsWidgetEl.querySelector('.widget-header');
                    if (header) {
                        // Find the next element after header or append to widget
                        let insertPoint = header.nextElementSibling;
                        if (insertPoint) {
                            applicationsWidgetEl.insertBefore(newAppItem, insertPoint);
                        } else {
                            applicationsWidgetEl.appendChild(newAppItem);
                        }
                    } else {
                        applicationsWidgetEl.appendChild(newAppItem);
                    }
                }
                
                console.log('Application added to sidebar:', companyName, jobRole);
            } catch (error) {
                console.error('Error updating applications sidebar:', error);
            }
        }

        // Helper function to escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Close modal on outside click
        document.getElementById('jobModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        document.getElementById('resumeModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeResumeModal();
            }
        });
    </script>
</body>
</html>
