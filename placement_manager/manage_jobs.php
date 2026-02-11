<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/job_backend.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['placement_officer', 'admin'], true)) {
    header('Location: ../login.php');
    exit;
}

// Use global mysqli connection
$mysqli = $GLOBALS['mysqli'] ?? null;

if (!$mysqli) {
    die("Database connection not available. Please check config.php");
}

// Setup job tables
setupJobTables($mysqli);

$message = '';
$messageType = '';

// Handle job posting
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_job'])) {
    $title = trim($_POST['title'] ?? '');
    $company = trim($_POST['company'] ?? '');
    $job_role = trim($_POST['job_role'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $requirements = trim($_POST['requirements'] ?? '');
    $job_type = trim($_POST['job_type'] ?? 'Full-time');
    $salary = trim($_POST['salary'] ?? '');
    $application_deadline = trim($_POST['application_deadline'] ?? '');
    $posted_by = $_SESSION['user_id'] ?? 0;
    
    if (empty($title) || empty($company) || empty($description) || empty($job_role)) {
        $message = "Title, company, job role, and description are required fields!";
        $messageType = "error";
    } else {
        // Ensure role column exists
        $check_role_col = $mysqli->query("SHOW COLUMNS FROM job_opportunities LIKE 'role'");
        $has_role_col = $check_role_col && $check_role_col->num_rows > 0;
        
        // If role column doesn't exist, add it
        if (!$has_role_col) {
            $mysqli->query("ALTER TABLE job_opportunities ADD COLUMN role VARCHAR(100) NULL AFTER company");
            $has_role_col = true;
        }
        
        if ($has_role_col) {
            $stmt = $mysqli->prepare("INSERT INTO job_opportunities 
                (job_title, company, company_name, role, location, job_description, requirements, job_type, salary, application_deadline, posted_by, posted_date, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'Active')");
                
            if ($stmt) {
                $typeString = str_repeat('s', 10) . 'i';
                $stmt->bind_param($typeString, $title, $company, $company, $job_role, $location, $description, $requirements, $job_type, $salary, $application_deadline, $posted_by);
                
                if ($stmt->execute()) {
                    $message = "Job posted successfully!";
                    $messageType = "success";
                    header('Location: manage_jobs.php?msg=job_posted');
                    exit;
                } else {
                    $message = "Error posting job: " . $stmt->error;
                    $messageType = "error";
                }
                $stmt->close();
            } else {
                $message = "Error preparing query: " . $mysqli->error;
                $messageType = "error";
            }
        } else {
            // Fallback if role column doesn't exist
            $stmt = $mysqli->prepare("INSERT INTO job_opportunities 
                (job_title, company, company_name, location, job_description, requirements, job_type, salary, application_deadline, posted_by, posted_date, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'Active')");
                
            if ($stmt) {
                $typeString = str_repeat('s', 9) . 'i';
                $stmt->bind_param($typeString, $title, $company, $company, $location, $description, $requirements, $job_type, $salary, $application_deadline, $posted_by);
                
                if ($stmt->execute()) {
                    $message = "Job posted successfully!";
                    $messageType = "success";
                    header('Location: manage_jobs.php?msg=job_posted');
                    exit;
                } else {
                    $message = "Error posting job: " . $stmt->error;
                    $messageType = "error";
                }
                $stmt->close();
            } else {
                $message = "Error preparing query: " . $mysqli->error;
                $messageType = "error";
            }
        }
    }
}

// Handle job deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_job'])) {
    $job_id_raw = $_POST['job_id'] ?? '';
    if ($job_id_raw === '' || !ctype_digit((string)$job_id_raw)) {
        $message = "Unable to delete job. Please refresh the page and try again.";
        $messageType = "error";
    } else {
        $job_id = (int)$job_id_raw;
        $exists_stmt = $mysqli->prepare("SELECT id FROM job_opportunities WHERE id = ? LIMIT 1");
        if ($exists_stmt) {
            $exists_stmt->bind_param('i', $job_id);
            $exists_stmt->execute();
            $exists_result = $exists_stmt->get_result();
            $job_exists = $exists_result && $exists_result->num_rows === 1;
            $exists_stmt->close();
        } else {
            $job_exists = false;
        }

        if (!$job_exists) {
            $message = "The selected job could not be found. It may have already been deleted.";
            $messageType = "error";
        } else {
            $stmt = $mysqli->prepare("DELETE FROM job_opportunities WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $job_id);
                if ($stmt->execute()) {
                    $message = "Job deleted successfully!";
                    $messageType = "success";
                    $stmt->close();
                    header('Location: manage_jobs.php?msg=job_deleted');
                    exit;
                } else {
                    $message = "Error deleting job: " . ($stmt->error ?: 'Unknown database error.');
                    $messageType = "error";
                    $stmt->close();
                }
            } else {
                $message = "Unable to prepare delete statement: " . $mysqli->error;
                $messageType = "error";
            }
        }
    }
}

// Handle job update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_job'])) {
    $job_id = (int)($_POST['job_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $company = trim($_POST['company'] ?? '');
    $job_role = trim($_POST['job_role'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $requirements = trim($_POST['requirements'] ?? '');
    $job_type = trim($_POST['job_type'] ?? 'Full-time');
    $salary = trim($_POST['salary'] ?? '');
    $application_deadline = trim($_POST['application_deadline'] ?? '');
    $status = trim($_POST['status'] ?? 'Active');
    
    if (empty($title) || empty($company) || empty($description) || empty($job_role)) {
        $message = "Title, company, job role, and description are required fields!";
        $messageType = "error";
    } else {
        // Ensure role column exists
        $check_role_col = $mysqli->query("SHOW COLUMNS FROM job_opportunities LIKE 'role'");
        $has_role_col = $check_role_col && $check_role_col->num_rows > 0;
        
        // If role column doesn't exist, add it
        if (!$has_role_col) {
            $mysqli->query("ALTER TABLE job_opportunities ADD COLUMN role VARCHAR(100) NULL AFTER company");
            $has_role_col = true;
        }
        
        if ($has_role_col) {
            $stmt = $mysqli->prepare("UPDATE job_opportunities SET 
                job_title = ?, 
                company = ?,
                company_name = ?, 
                role = ?,
                location = ?, 
                job_description = ?, 
                requirements = ?, 
                job_type = ?, 
                salary = ?, 
                application_deadline = ?,
                status = ? 
                WHERE id = ?");
                
            if ($stmt) {
                $typeString = str_repeat('s', 10) . 'si'; // 10 strings + status string + id integer
                $stmt->bind_param($typeString, $title, $company, $company, $job_role, $location, $description, 
                                 $requirements, $job_type, $salary, $application_deadline, $status, $job_id);
                
                if ($stmt->execute()) {
                    $message = "Job updated successfully!";
                    $messageType = "success";
                    header('Location: manage_jobs.php?msg=job_updated');
                    exit;
                } else {
                    $message = "Error updating job: " . $stmt->error;
                    $messageType = "error";
                }
                $stmt->close();
            } else {
                $message = "Error preparing query: " . $mysqli->error;
                $messageType = "error";
            }
        } else {
            // Fallback if role column doesn't exist
            $stmt = $mysqli->prepare("UPDATE job_opportunities SET 
                job_title = ?, 
                company = ?,
                company_name = ?, 
                location = ?, 
                job_description = ?, 
                requirements = ?, 
                job_type = ?, 
                salary = ?, 
                application_deadline = ?,
                status = ? 
                WHERE id = ?");
                
            if ($stmt) {
                $typeString = str_repeat('s', 9) . 'si';
                $stmt->bind_param($typeString, $title, $company, $company, $location, $description, 
                                 $requirements, $job_type, $salary, $application_deadline, $status, $job_id);
                
                if ($stmt->execute()) {
                    $message = "Job updated successfully!";
                    $messageType = "success";
                    header('Location: manage_jobs.php?msg=job_updated');
                    exit;
                } else {
                    $message = "Error updating job: " . $stmt->error;
                    $messageType = "error";
                }
                $stmt->close();
            } else {
                $message = "Error preparing query: " . $mysqli->error;
                $messageType = "error";
            }
        }
    }
}

// Fetch all job opportunities with correct column names (after handling POST actions)
$jobs = [];
$jobs_query = $mysqli->query("SELECT 
    id,
    job_title,
    COALESCE(company, company_name) as company,
    company_name,
    role,
    location,
    job_description as description,
    requirements,
    job_type,
    salary,
    application_deadline,
    posted_by,
    posted_date,
    status
    FROM job_opportunities 
    ORDER BY posted_date DESC");
    
if ($jobs_query) {
    $jobs = $jobs_query->fetch_all(MYSQLI_ASSOC);
    // Debug: Uncomment to see the structure of fetched jobs
    // echo '<pre>'; print_r($jobs); echo '</pre>';
} else {
    // Debug: Show error if query fails
    // echo '<p>Query failed: ' . $mysqli->error . '</p>';
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Jobs - Placement Portal</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .jobs-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            position: relative;
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-header h1 {
            color: #5b1f1f;
            margin: 0 0 20px 0;
            font-size: 28px;
        }

        /* Floating action button */
        .floating-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg,#5b1f1f,#8b3a3a);
            color: white;
            border: none;
            font-size: 24px;
            cursor: pointer;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            transition: all 0.3s;
        }

        .floating-btn:hover {
            background: linear-gradient(135deg,#8b3a3a,#5b1f1f);
            transform: scale(1.1);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.25);
        }

        /* Jobs grid */
        .jobs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }

        .job-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
            display: flex;
            flex-direction: column;
            height: 100%;
            border: 1px solid #e5e7eb;
        }

        .job-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
        }

        .job-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            background-color: #f9f9f9;
        }

        .job-title {
            margin: 0 0 5px 0;
            color: #5b1f1f;
            font-size: 20px;
        }

        .job-company {
            margin: 0 0 10px 0;
            color: #333;
            font-size: 16px;
            font-weight: 500;
        }

        .job-type {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            background-color: #f0f0f0;
            color: #5b1f1f;
            border: 1px solid #e0e0e0;
        }

        .job-details {
            padding: 15px 20px;
            flex-grow: 1;
        }

        .detail-row {
            display: flex;
            margin-bottom: 10px;
            align-items: center;
        }

        .detail-icon {
            margin-right: 10px;
            width: 18px;
            color: #5b1f1f;
            text-align: center;
        }

        .job-description {
            margin: 15px 0;
            color: #555;
            line-height: 1.5;
            font-size: 14px;
            max-height: 100px;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 4;
            -webkit-box-orient: vertical;
        }

        .job-footer {
            padding: 15px 20px;
            background-color: #f9f9f9;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .job-posted {
            font-size: 13px;
            color: #757575;
        }

        .job-actions {
            display: flex;
            gap: 10px;
        }

        .btn-edit, .btn-delete {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-edit {
            background-color: #5b1f1f;
            color: white;
        }

        .btn-edit:hover {
            background-color: #8b3a3a;
        }

        .btn-delete {
            background-color: #f44336;
            color: white;
        }

        .btn-delete:hover {
            background-color: #d32f2f;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: white;
            border-radius: 16px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            position: relative;
        }

        .modal-header {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #5b1f1f;
            color: white;
            border-radius: 16px 16px 0 0;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 20px;
            color: white;
        }

        .close {
            font-size: 24px;
            cursor: pointer;
            color: white;
        }

        .close:hover {
            color: #f0f0f0;
        }

        .modal-body {
            padding: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #444;
        }

        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-control:focus {
            border-color: #5b1f1f;
            outline: none;
            box-shadow: 0 0 0 2px rgba(91, 31, 31, 0.1);
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding: 15px 20px;
            border-top: 1px solid #eee;
            background-color: #f9f9f9;
            border-bottom-left-radius: 16px;
            border-bottom-right-radius: 16px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg,#5b1f1f,#8b3a3a);
            color: white;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg,#8b3a3a,#5b1f1f);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background-color: #9e9e9e;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #757575;
            transform: translateY(-2px);
        }

        /* Alert styles */
        .alert {
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background-color: #e8f5e9;
            color: #2e7d32;
            border-left: 4px solid #4CAF50;
        }

        .alert-error {
            background-color: #ffebee;
            color: #c62828;
            border-left: 4px solid #f44336;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .jobs-grid {
                grid-template-columns: 1fr;
            }
            
            .floating-btn {
                width: 50px;
                height: 50px;
                font-size: 20px;
                bottom: 20px;
                right: 20px;
            }
            
            .job-actions {
                flex-direction: column;
                width: 100%;
            }
            
            .btn-edit, .btn-delete {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/partials/header.php'; ?>
    
    <div class="jobs-container">
        <div class="page-header">
            <h1>📋 Job Opportunities</h1>
        </div>
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType === 'error' ? 'error' : 'success'; ?>">
                <i class="fas <?php echo $messageType === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="jobs-grid">
            <?php if (empty($jobs)): ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 40px; background: #f8f9fa; border-radius: 16px; margin-top: 20px;">
                    <p style="color: #6c757d; font-size: 16px; margin: 0;">
                        No job opportunities posted yet. Click the + button to add a new job.
                    </p>
                </div>
            <?php else: ?>
                <?php foreach ($jobs as $job): ?>
                    <div class="job-card">
                        <div class="job-header">
                            <h3 class="job-title"><?php echo htmlspecialchars($job['job_title'] ?? 'No Title'); ?></h3>
                            <p class="job-company"><?php echo htmlspecialchars($job['company'] ?? $job['company_name'] ?? 'N/A'); ?></p>
                            <?php if (!empty($job['role'])): ?>
                                <p style="margin: 5px 0; color: #666; font-size: 14px; font-weight: 500;">
                                    <i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($job['role']); ?>
                                </p>
                            <?php endif; ?>
                            <span class="job-type"><?php echo htmlspecialchars($job['job_type'] ?? 'Full-time'); ?></span>
                        </div>
                        
                        <div class="job-details">
                            <?php if (!empty($job['location'])): ?>
                                <div class="detail-row">
                                    <span class="detail-icon"><i class="fas fa-map-marker-alt"></i></span>
                                    <span><?php echo htmlspecialchars($job['location']); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($job['salary'])): ?>
                                <div class="detail-row">
                                    <span class="detail-icon"><i class="fas fa-money-bill-wave"></i></span>
                                    <span><?php echo htmlspecialchars($job['salary']); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($job['application_deadline'])): ?>
                                <div class="detail-row">
                                    <span class="detail-icon"><i class="far fa-calendar-alt"></i></span>
                                    <span>Apply by: <?php echo date('M d, Y', strtotime($job['application_deadline'])); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($job['description'])): ?>
                                <div class="job-description">
                                    <?php echo nl2br(htmlspecialchars(substr($job['description'], 0, 200) . (strlen($job['description']) > 200 ? '...' : ''))); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($job['requirements'])): ?>
                                <div class="job-requirements">
                                    <strong>Requirements:</strong>
                                    <ul style="margin: 8px 0 0 20px; padding: 0; color: #555; font-size: 14px;">
                                        <?php 
                                        $requirements = explode("\n", $job['requirements']);
                                        foreach ($requirements as $req): 
                                            if (trim($req) !== ''):
                                        ?>
                                            <li><?php echo htmlspecialchars(trim($req, "-•\t \r\n")); ?></li>
                                        <?php 
                                            endif;
                                        endforeach; 
                                        ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="job-footer">
                            <div class="job-posted">
                                <i class="far fa-clock"></i> Posted on <?php echo date('M d, Y', strtotime($job['posted_date'])); ?>
                            </div>
                            <div class="job-actions">
                                <button class="btn-edit" onclick="editJob(<?php echo htmlspecialchars(json_encode($job)); ?>)">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this job?');">
                                    <input type="hidden" name="job_id" value="<?php echo htmlspecialchars($job['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <button type="submit" name="delete_job" class="btn-delete">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Floating action button for adding new jobs -->
        <button class="floating-btn" onclick="openAddJobModal()">
            <i class="fas fa-plus"></i>
        </button>
    </div>

    <!-- Add Job Modal -->
    <div id="addJobModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Post a New Job</h2>
                <span class="close" onclick="closeModal('addJobModal')">&times;</span>
            </div>
            <form method="post" action="">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="title">Job Title *</label>
                        <input type="text" id="title" name="title" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="company">Company Name *</label>
                        <input type="text" id="company" name="company" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="job_role">Job Role *</label>
                        <input type="text" id="job_role" name="job_role" class="form-control" required placeholder="e.g., Software Engineer, Data Analyst, etc.">
                    </div>
                    <div class="form-group">
                        <label for="location">Location</label>
                        <input type="text" id="location" name="location" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="job_type">Job Type</label>
                        <select id="job_type" name="job_type" class="form-control">
                            <option value="Full-time">Full-time</option>
                            <option value="Part-time">Part-time</option>
                            <option value="Contract">Contract</option>
                            <option value="Internship">Internship</option>
                            <option value="Temporary">Temporary</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="salary">Salary/Compensation</label>
                        <input type="text" id="salary" name="salary" class="form-control" placeholder="e.g., $50,000 - $70,000 per year">
                    </div>
                    <div class="form-group">
                        <label for="application_deadline">Application Deadline</label>
                        <input type="date" id="application_deadline" name="application_deadline" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="description">Job Description *</label>
                        <textarea id="description" name="description" class="form-control" rows="5" required placeholder="Provide a detailed description of the job position, responsibilities, and expectations."></textarea>
                    </div>
                    <div class="form-group">
                        <label for="requirements">Requirements (One per line)</label>
                        <textarea id="requirements" name="requirements" class="form-control" rows="5" placeholder="List the required qualifications, skills, and experience (one per line)"></textarea>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addJobModal')">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" name="post_job" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Post Job
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Job Modal -->
    <div id="editJobModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Job Posting</h2>
                <span class="close" onclick="closeModal('editJobModal')">&times;</span>
            </div>
            <form method="post" action="">
                <input type="hidden" id="edit_job_id" name="job_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit_title">Job Title *</label>
                        <input type="text" id="edit_title" name="title" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_company">Company Name *</label>
                        <input type="text" id="edit_company" name="company" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_job_role">Job Role *</label>
                        <input type="text" id="edit_job_role" name="job_role" class="form-control" required placeholder="e.g., Software Engineer, Data Analyst, etc.">
                    </div>
                    <div class="form-group">
                        <label for="edit_location">Location</label>
                        <input type="text" id="edit_location" name="location" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="edit_job_type">Job Type</label>
                        <select id="edit_job_type" name="job_type" class="form-control">
                            <option value="Full-time">Full-time</option>
                            <option value="Part-time">Part-time</option>
                            <option value="Contract">Contract</option>
                            <option value="Internship">Internship</option>
                            <option value="Temporary">Temporary</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_salary">Salary/Compensation</label>
                        <input type="text" id="edit_salary" name="salary" class="form-control" placeholder="e.g., $50,000 - $70,000 per year">
                    </div>
                    <div class="form-group">
                        <label for="edit_application_deadline">Application Deadline</label>
                        <input type="date" id="edit_application_deadline" name="application_deadline" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="edit_description">Job Description *</label>
                        <textarea id="edit_description" name="description" class="form-control" rows="5" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="edit_requirements">Requirements (One per line)</label>
                        <textarea id="edit_requirements" name="requirements" class="form-control" rows="5"></textarea>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editJobModal')">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" name="update_job" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Job
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    // Open Add Job Modal
    function openAddJobModal() {
        document.getElementById('addJobModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
        
        // Set default application deadline to 30 days from now
        const today = new Date();
        const futureDate = new Date(today);
        futureDate.setDate(today.getDate() + 30);
        const formattedDate = futureDate.toISOString().split('T')[0];
        document.getElementById('application_deadline').value = formattedDate;
        
        // Set focus to the first input field
        document.getElementById('title').focus();
    }

    // Open Edit Job Modal
    function editJob(job) {
        document.getElementById('edit_job_id').value = job.id || '';
        document.getElementById('edit_title').value = job.title || '';
        document.getElementById('edit_company').value = job.company || '';
        document.getElementById('edit_job_role').value = job.role || '';
        document.getElementById('edit_location').value = job.location || '';
        document.getElementById('edit_job_type').value = job.job_type || 'Full-time';
        document.getElementById('edit_salary').value = job.salary || '';
        document.getElementById('edit_description').value = job.description || '';
        document.getElementById('edit_requirements').value = job.requirements || '';
        
        // Format date for the date input (YYYY-MM-DD)
        if (job.application_deadline) {
            const deadline = new Date(job.application_deadline);
            const formattedDate = deadline.toISOString().split('T')[0];
            document.getElementById('edit_application_deadline').value = formattedDate;
        } else {
            document.getElementById('edit_application_deadline').value = '';
        }
        
        document.getElementById('editJobModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
        
        // Set focus to the first input field
        document.getElementById('edit_title').focus();
    }

    // Close any modal
    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    // Close modals when clicking outside
    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    }

    // Close modals with Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            document.querySelectorAll('.modal').forEach(modal => {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            });
        }
    });

    // Show success message if redirected with success parameter
    window.onload = function() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('msg')) {
            const msg = urlParams.get('msg');
            if (msg === 'job_posted') {
                showMessage('Job posted successfully!', 'success');
            } else if (msg === 'job_deleted') {
                showMessage('Job deleted successfully!', 'success');
            } else if (msg === 'job_updated') {
                showMessage('Job updated successfully!', 'success');
            }
            // Remove the parameter from the URL without refreshing
            const newUrl = window.location.pathname;
            window.history.replaceState({}, document.title, newUrl);
        }
    };

    // Helper function to show messages
    function showMessage(message, type) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type}`;
        alertDiv.style.cssText = 'margin: 15px; padding: 12px 20px; border-radius: 8px;';
        alertDiv.style.backgroundColor = type === 'error' ? '#ffebee' : '#e8f5e9';
        alertDiv.style.color = type === 'error' ? '#c62828' : '#2e7d32';
        alertDiv.style.borderLeft = `4px solid ${type === 'error' ? '#f44336' : '#4CAF50'}`;
        alertDiv.style.position = 'fixed';
        alertDiv.style.top = '20px';
        alertDiv.style.right = '20px';
        alertDiv.style.zIndex = '1000';
        alertDiv.style.maxWidth = '400px';
        alertDiv.style.boxShadow = '0 2px 10px rgba(0,0,0,0.1)';
        alertDiv.style.animation = 'slideIn 0.3s ease-out';
        
        const icon = type === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle';
        alertDiv.innerHTML = `<i class="fas ${icon}" style="margin-right: 8px;"></i>${message}`;
        
        document.body.appendChild(alertDiv);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            alertDiv.style.animation = 'fadeOut 0.3s ease-out';
            setTimeout(() => {
                document.body.removeChild(alertDiv);
            }, 300);
        }, 5000);
    }

    // Add CSS animations
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }
    `;
    document.head.appendChild(style);
    </script>
    
    <?php include __DIR__ . '/../includes/partials/footer.php'; ?>
</body>
</html>
