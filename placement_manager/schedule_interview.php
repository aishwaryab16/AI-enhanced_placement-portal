<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/job_backend.php';

if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'placement_officer' && $_SESSION['role'] !== 'admin')) {
    header('Location: login.php');
    exit;
}

$mysqli = $GLOBALS['mysqli'] ?? new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
setupJobTables($mysqli);

$message = '';
$messageType = '';

// Batch schedule interviews for all shortlisted students
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule_all'])) {
    $scheduled_at = $_POST['scheduled_at'] ?? null;
    $panel_details = trim($_POST['panel_details'] ?? '');
    
    if (!$scheduled_at) {
        $message = 'Please select a date and time.';
        $messageType = 'error';
    } else {
        // Get all shortlisted applications
        $res = $mysqli->query("SELECT ja.*, COALESCE(ja.full_name, u.full_name, CONCAT('Student ID: ', ja.student_id)) as full_name, COALESCE(jo.company, ja.company_name, 'Unknown Company') as company, COALESCE(jo.role, ja.job_role, ja.job_title, 'Unknown Role') as role, COALESCE(jo.location, ja.location, 'Location not specified') as location FROM job_applications ja LEFT JOIN users u ON u.id = ja.student_id LEFT JOIN job_opportunities jo ON jo.id = ja.job_id WHERE ja.application_status = 'Shortlisted' ORDER BY company ASC, ja.applied_at DESC");
        
        if ($res && $res->num_rows > 0) {
            $stmt = $mysqli->prepare("INSERT INTO interviews (job_application_id, student_id, company, job_role, scheduled_at, panel_details, status) VALUES (?, ?, ?, ?, ?, ?, 'scheduled')");
            
            if ($stmt) {
                $success_count = 0;
                $error_count = 0;
                
                while ($s = $res->fetch_assoc()) {
                    $application_id = (int)$s['id'];
                    $student_id = (int)$s['student_id'];
                    $company = $s['company'] ?? 'Unknown Company';
                    $job_role = $s['role'] ?? 'Unknown Role';
                    
                    // Check if interview already exists for this application
                    $check = $mysqli->prepare("SELECT id FROM interviews WHERE job_application_id = ?");
                    if ($check) {
                        $check->bind_param('i', $application_id);
                        $check->execute();
                        $exists = $check->get_result()->fetch_assoc();
                        $check->close();
                        
                        if (!$exists) {
                            $stmt->bind_param('iissss', $application_id, $student_id, $company, $job_role, $scheduled_at, $panel_details);
                            if ($stmt->execute()) {
                                $success_count++;
                            } else {
                                $error_count++;
                            }
                        }
                    }
                }
                
                $stmt->close();
                
                if ($success_count > 0) {
                    $message = "Successfully scheduled interviews for {$success_count} student(s).";
                    if ($error_count > 0) {
                        $message .= " {$error_count} failed.";
                    }
                    $messageType = 'success';
                } else {
                    $message = 'No interviews were scheduled. They may already be scheduled.';
                    $messageType = 'error';
                }
            } else {
                $message = 'Failed to prepare statement: ' . $mysqli->error;
                $messageType = 'error';
            }
        } else {
            $message = 'No shortlisted students found.';
            $messageType = 'error';
        }
    }
}

// Schedule interviews for a specific company with rounds
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule_company'])) {
    $company_name = trim($_POST['company_name'] ?? '');
    $scheduled_at = $_POST['scheduled_at'] ?? null;
    $panel_details = trim($_POST['panel_details'] ?? '');
    $interview_rounds_json = $_POST['interview_rounds_json'] ?? '';
    $interview_rounds = !empty($interview_rounds_json) ? $interview_rounds_json : null;
    
    if (!$company_name || !$scheduled_at) {
        $message = 'Please provide company name and date/time.';
        $messageType = 'error';
    } elseif (!$interview_rounds || empty(json_decode($interview_rounds, true))) {
        $message = 'Please select at least one interview round.';
        $messageType = 'error';
    } else {
        // Get shortlisted applications for this company
        $stmt = $mysqli->prepare("SELECT ja.*, COALESCE(ja.full_name, u.full_name, CONCAT('Student ID: ', ja.student_id)) as full_name, COALESCE(jo.company, ja.company_name, 'Unknown Company') as company, COALESCE(jo.role, ja.job_role, ja.job_title, 'Unknown Role') as role FROM job_applications ja LEFT JOIN users u ON u.id = ja.student_id LEFT JOIN job_opportunities jo ON jo.id = ja.job_id WHERE ja.application_status = 'Shortlisted' AND (COALESCE(jo.company, ja.company_name) = ?)");
        
        if ($stmt) {
            $stmt->bind_param('s', $company_name);
            $stmt->execute();
            $res = $stmt->get_result();
            $stmt->close();
            
            if ($res && $res->num_rows > 0) {
                $insert_stmt = $mysqli->prepare("INSERT INTO interviews (job_application_id, student_id, company, job_role, scheduled_at, panel_details, interview_rounds, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'scheduled')");
                
                if ($insert_stmt) {
                    $success_count = 0;
                    $error_count = 0;
                    
                    while ($s = $res->fetch_assoc()) {
                        $application_id = (int)$s['id'];
                        $student_id = (int)$s['student_id'];
                        $company = $s['company'] ?? 'Unknown Company';
                        $job_role = $s['role'] ?? 'Unknown Role';
                        
                        // Check if interview already exists for this application
                        $check = $mysqli->prepare("SELECT id FROM interviews WHERE job_application_id = ?");
                        if ($check) {
                            $check->bind_param('i', $application_id);
                            $check->execute();
                            $exists = $check->get_result()->fetch_assoc();
                            $check->close();
                            
                            if (!$exists) {
                                $insert_stmt->bind_param('iisssss', $application_id, $student_id, $company, $job_role, $scheduled_at, $panel_details, $interview_rounds);
                                if ($insert_stmt->execute()) {
                                    $success_count++;
                                } else {
                                    $error_count++;
                                }
                            }
                        }
                    }
                    
                    $insert_stmt->close();
                    
                    if ($success_count > 0) {
                        $message = "Successfully scheduled interviews for {$success_count} student(s) from {$company_name}.";
                        if ($error_count > 0) {
                            $message .= " {$error_count} failed.";
                        }
                        $messageType = 'success';
                    } else {
                        $message = 'No interviews were scheduled. They may already be scheduled.';
                        $messageType = 'error';
                    }
                } else {
                    $message = 'Failed to prepare statement: ' . $mysqli->error;
                    $messageType = 'error';
                }
            } else {
                $message = 'No shortlisted students found for this company.';
                $messageType = 'error';
            }
        } else {
            $message = 'Failed to prepare statement: ' . $mysqli->error;
            $messageType = 'error';
        }
    }
}

// Load shortlisted applications and group by company
$shortlisted = [];
$shortlisted_by_company = [];

// First, let's check if there are any shortlisted applications
$status_check = $mysqli->query("SELECT COUNT(*) as total, application_status FROM job_applications GROUP BY application_status");
$status_counts = [];
if ($status_check) {
    while ($row = $status_check->fetch_assoc()) {
        $status_counts[trim($row['application_status'])] = (int)$row['total'];
    }
}

// Query for shortlisted students - use COALESCE for full_name and handle status matching
// First try with exact match
$query = "SELECT 
    ja.*, 
    COALESCE(ja.full_name, u.full_name, CONCAT('Student ID: ', ja.student_id)) as full_name,
    COALESCE(ja.username, u.username, '') as username,
    COALESCE(jo.company, ja.company_name, 'Unknown Company') as company, 
    COALESCE(jo.role, ja.job_role, ja.job_title, 'Unknown Role') as role, 
    COALESCE(jo.location, ja.location, 'Location not specified') as location 
FROM job_applications ja 
LEFT JOIN users u ON u.id = ja.student_id 
LEFT JOIN job_opportunities jo ON jo.id = ja.job_id 
WHERE ja.application_status = 'Shortlisted' 
ORDER BY company ASC, ja.applied_at DESC";

$res = $mysqli->query($query);
if ($res) { 
    $shortlisted = $res->fetch_all(MYSQLI_ASSOC);
    
    // If query returns empty but status_counts shows shortlisted students exist, try a fallback
    if (empty($shortlisted) && isset($status_counts['Shortlisted']) && $status_counts['Shortlisted'] > 0) {
        // Fallback: fetch all applications and filter in PHP (handles whitespace issues)
        $all_apps_query = "SELECT 
            ja.*, 
            COALESCE(ja.full_name, u.full_name, CONCAT('Student ID: ', ja.student_id)) as full_name,
            COALESCE(ja.username, u.username, '') as username,
            COALESCE(jo.company, ja.company_name, 'Unknown Company') as company, 
            COALESCE(jo.role, ja.job_role, ja.job_title, 'Unknown Role') as role, 
            COALESCE(jo.location, ja.location, 'Location not specified') as location,
            ja.application_status
        FROM job_applications ja 
        LEFT JOIN users u ON u.id = ja.student_id 
        LEFT JOIN job_opportunities jo ON jo.id = ja.job_id 
        ORDER BY ja.applied_at DESC";
        
        $all_apps = $mysqli->query($all_apps_query);
        if ($all_apps) {
            while ($app = $all_apps->fetch_assoc()) {
                $status = trim($app['application_status'] ?? '');
                if (strcasecmp($status, 'Shortlisted') === 0) {
                    $shortlisted[] = $app;
                }
            }
        }
        
        // If still empty, try a more permissive query
        if (empty($shortlisted)) {
            $permissive_query = "SELECT 
                ja.*, 
                COALESCE(ja.full_name, u.full_name, CONCAT('Student ID: ', ja.student_id)) as full_name,
                COALESCE(ja.username, u.username, '') as username,
                COALESCE(jo.company, ja.company_name, 'Unknown Company') as company, 
                COALESCE(jo.role, ja.job_role, ja.job_title, 'Unknown Role') as role, 
                COALESCE(jo.location, ja.location, 'Location not specified') as location
            FROM job_applications ja 
            LEFT JOIN users u ON u.id = ja.student_id 
            LEFT JOIN job_opportunities jo ON jo.id = ja.job_id 
            WHERE LOWER(TRIM(ja.application_status)) = 'shortlisted' 
            ORDER BY company ASC, ja.applied_at DESC";
            
            $permissive_res = $mysqli->query($permissive_query);
            if ($permissive_res) {
                $shortlisted = $permissive_res->fetch_all(MYSQLI_ASSOC);
            }
        }
    }
    
    // Group by company
    foreach ($shortlisted as $s) {
        $company = $s['company'] ?? 'Other Companies';
        if (empty($company) || trim($company) === '') {
            $company = 'Other Companies';
        }
        if (!isset($shortlisted_by_company[$company])) {
            $shortlisted_by_company[$company] = [];
        }
        $shortlisted_by_company[$company][] = $s;
    }
    // Sort companies alphabetically
    ksort($shortlisted_by_company);
}

// Load scheduled interviews
$interviews = [];
$res = $mysqli->query("SELECT i.*, COALESCE(u.full_name, CONCAT('Student ID: ', i.student_id)) as full_name FROM interviews i LEFT JOIN users u ON u.id = i.student_id ORDER BY i.scheduled_at DESC, i.created_at DESC");
if ($res) { $interviews = $res->fetch_all(MYSQLI_ASSOC); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interview Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background:#f5f7fa; margin:0; }
        .wrap { max-width: 1200px; margin: 30px auto; padding: 0 16px; }
        .header { background: linear-gradient(135deg,#5b1f1f,#ecc35c); color:#fff; padding:24px; border-radius:12px; margin-bottom:20px; }
        .grid { display:grid; grid-template-columns: 1fr 1fr; gap:20px; }
        .card { background:#fff; border-radius:12px; padding:16px; box-shadow:0 2px 8px rgba(0,0,0,0.08); }
        .title { font-weight:700; color:#1f2937; margin-bottom:10px; display:flex; align-items:center; gap:8px; }
        table { width:100%; border-collapse: collapse; }
        th, td { padding:10px; border-bottom:1px solid #eef2f7; text-align:left; font-size:14px; }
        th { background:#f9fafb; color:#6b7280; text-transform: uppercase; font-size:12px; }
        .btn { padding:8px 12px; border-radius:8px; border:none; cursor:pointer; font-weight:600; }
        .btn-primary { background:#5b1f1f; color:#fff; }
        .alert { padding:12px 14px; border-radius:8px; margin-bottom:12px; }
        .alert-success { background:#d1fae5; color:#065f46; }
        .alert-error { background:#fee2e2; color:#991b1b; }
        .row { display:flex; gap:8px; align-items:center; }
        input[type=datetime-local], textarea { padding:8px; border:1px solid #e5e7eb; border-radius:8px; font-family:inherit; }
        .search-filter { background: white; padding: 16px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .search-filter input { width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px; }
        .search-filter input:focus { outline: none; border-color: #5b1f1f; }
        .company-group { margin-bottom: 15px; background: #f9fafb; border-radius: 12px; padding: 16px; border: 1px solid #e5e7eb; transition: all 0.3s; }
        .company-group.collapsed .company-table-container { display: none; }
        .company-header { background: linear-gradient(135deg, #5b1f1f, #8b3a3a); color: white; padding: 14px 18px; border-radius: 10px; margin-bottom: 15px; display: flex; align-items: center; justify-content: space-between; cursor: pointer; transition: all 0.3s; }
        .company-header:hover { background: linear-gradient(135deg, #3d1414, #5b1f1f); }
        .company-header h3 { margin: 0; font-size: 17px; font-weight: 700; display: flex; align-items: center; gap: 10px; flex: 1; }
        .company-header .toggle-icon { font-size: 14px; transition: transform 0.3s; }
        .company-header.collapsed .toggle-icon { transform: rotate(-90deg); }
        .company-count { background: rgba(255,255,255,0.25); padding: 5px 12px; border-radius: 14px; font-size: 12px; font-weight: 600; }
        .company-many { background: rgba(236, 195, 92, 0.1); }
        .company-table-container { max-height: 500px; overflow-y: auto; }
        .company-table-container::-webkit-scrollbar { width: 8px; }
        .company-table-container::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
        .company-table-container::-webkit-scrollbar-thumb { background: #888; border-radius: 10px; }
        .company-table-container::-webkit-scrollbar-thumb:hover { background: #555; }
        .company-table { margin-top: 0; width: 100%; background: white; border-radius: 8px; overflow: hidden; border: 1px solid #e5e7eb; }
        .company-table thead { background: #f9fafb; position: sticky; top: 0; z-index: 10; }
        .company-table thead th { padding: 12px 16px; border-bottom: 2px solid #e5e7eb; text-align: left; }
        .company-table tbody tr { border-bottom: 1px solid #f3f4f6; transition: background 0.2s; }
        .company-table tbody tr:hover { background: #f9fafb; }
        .company-table tbody tr:last-child { border-bottom: none; }
        .students-container { max-height: none; overflow-y: visible; padding-right: 8px; }
        .expand-all-btn { background: #e5e7eb; color: #374151; padding: 8px 16px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 13px; margin-bottom: 12px; }
        .expand-all-btn:hover { background: #d1d5db; }
        /* Company cards (compact, job card feel) */
        .company-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 12px; margin-bottom: 16px; }
        .company-card { background: linear-gradient(135deg, #5b1f1f, #8b3a3a); color: #fff; border-radius: 12px; padding: 14px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 2px 8px rgba(0,0,0,0.08); cursor: pointer; transition: transform 0.15s ease, box-shadow 0.2s ease; }
        .company-card:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0,0,0,0.12); }
        .company-card.active { outline: 2px solid #fbbf24; }
        .company-card .left { display: flex; align-items: center; gap: 10px; font-weight: 700; font-size: 15px; }
        .company-card .badge { background: rgba(255,255,255,0.22); padding: 6px 10px; border-radius: 14px; font-weight: 700; font-size: 12px; }
        /* Schedule Interview Button */
        .schedule-btn { background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.3); padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; transition: all 0.2s; margin-right: 8px; }
        .schedule-btn:hover { background: rgba(255,255,255,0.3); }
        /* Modal Styles */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
        .modal-content { background: white; margin: 5% auto; padding: 24px; border-radius: 12px; width: 90%; max-width: 500px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .modal-header h3 { margin: 0; color: #1f2937; font-size: 20px; }
        .close-modal { background: none; border: none; font-size: 24px; cursor: pointer; color: #6b7280; }
        .close-modal:hover { color: #1f2937; }
        .round-checkbox { display: flex; align-items: center; gap: 10px; padding: 12px; background: #f9fafb; border-radius: 8px; margin-bottom: 10px; cursor: pointer; transition: background 0.2s; }
        .round-checkbox:hover { background: #f3f4f6; }
        .round-checkbox input[type="checkbox"] { width: 18px; height: 18px; cursor: pointer; }
        .round-checkbox label { cursor: pointer; font-weight: 500; color: #374151; flex: 1; }
        .modal-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }
        .btn-secondary { background: #e5e7eb; color: #374151; padding: 10px 20px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; }
        .btn-secondary:hover { background: #d1d5db; }
    </style>
    </head>
<body>
    <div class="wrap">
        <div class="header"><h2>Interview & Drive Management</h2><div>Schedule and track interviews for shortlisted students</div></div>
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <!-- Schedule Form for All Students -->
        <?php if (!empty($shortlisted)): ?>
            <div class="card" style="margin-bottom: 20px;">
                <div class="title"><i class="fas fa-calendar-plus"></i> Schedule Interviews for All Shortlisted Students</div>
                <form method="POST" style="display: flex; gap: 12px; align-items: end; flex-wrap: wrap;">
                    <div style="flex: 1; min-width: 200px;">
                        <label style="display: block; margin-bottom: 6px; font-weight: 600; color: #4b5563; font-size: 13px;">Date & Time</label>
                        <input type="datetime-local" name="scheduled_at" required style="width: 100%; padding: 10px; border: 2px solid #e5e7eb; border-radius: 8px; font-family: inherit;">
                    </div>
                    <div style="flex: 2; min-width: 250px;">
                        <label style="display: block; margin-bottom: 6px; font-weight: 600; color: #4b5563; font-size: 13px;">Panel Details (Optional)</label>
                        <textarea name="panel_details" placeholder="Enter panel details, venue, or other notes..." rows="2" style="width: 100%; padding: 10px; border: 2px solid #e5e7eb; border-radius: 8px; font-family: inherit; resize: vertical;"></textarea>
                    </div>
                    
                </form>
                <div style="margin-top: 12px; padding: 10px; background: #fef3c7; border-radius: 6px; color: #92400e; font-size: 13px;">
                    <i class="fas fa-info-circle"></i> This will schedule interviews for <strong><?php echo count($shortlisted); ?> shortlisted student(s)</strong> at the same time.
                </div>
            </div>
        <?php endif; ?>
        
        <div class="grid">
            <div class="card">
                <div class="title"><i class="fas fa-star"></i> Shortlisted Students (<?php echo count($shortlisted); ?>)</div>
                <?php if (empty($shortlisted)): ?>
                    <div style="padding: 40px; text-align: center; color: #6b7280;">
                        <i class="fas fa-user-check" style="font-size: 48px; margin-bottom: 16px; opacity: 0.3;"></i>
                        <p>No shortlisted applications found.</p>
                        <?php if (isset($status_counts['Shortlisted']) && $status_counts['Shortlisted'] > 0): ?>
                            <p style="margin-top: 10px; font-size: 13px; color: #f59e0b;">
                                <i class="fas fa-exclamation-triangle"></i> 
                                Note: Database shows <?php echo $status_counts['Shortlisted']; ?> shortlisted student(s), but they couldn't be loaded. 
                                Please check the application status in track_applications.php.
                            </p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <!-- Company Cards: click to view shortlisted students for that company -->
                    <div class="company-cards">
                        <?php foreach ($shortlisted_by_company as $company_name => $students): 
                            $companyCardId = 'company-' . preg_replace('/[^a-zA-Z0-9]/', '-', strtolower($company_name));
                        ?>
                            <div class="company-card" id="card-<?php echo $companyCardId; ?>" onclick="showCompany('<?php echo $companyCardId; ?>')">
                                <div class="left">
                                    <i class="fas fa-building"></i>
                                    <span><?php echo htmlspecialchars($company_name); ?></span>
                                </div>
                                <div class="badge"><?php echo count($students); ?> shortlisted</div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Search Filter -->
                    <div class="search-filter">
                        <input type="text" id="companySearch" placeholder="Search by company name or student name..." onkeyup="filterCompanies()" style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px;">
                    </div>
                    
                    <!-- Expand/Collapse All -->
                    <button class="expand-all-btn" onclick="toggleAllCompanies()">
                        <i class="fas fa-chevron-down" id="expandAllIcon"></i> <span id="expandAllText">Expand All</span>
                    </button>
                    
                    <div class="students-container">
                        <?php foreach ($shortlisted_by_company as $company_name => $students): 
                            $hasManyStudents = count($students) > 5;
                            $companyId = 'company-' . preg_replace('/[^a-zA-Z0-9]/', '-', strtolower($company_name));
                        ?>
                            <?php 
                                $studentNames = array_map(function($s) { 
                                    return strtolower($s['full_name'] ?? ''); 
                                }, $students);
                                $studentNamesStr = implode(' ', array_filter($studentNames));
                            ?>
                            <div class="company-group <?php echo $hasManyStudents ? 'company-many' : ''; ?>" id="group-<?php echo $companyId; ?>" data-company="<?php echo htmlspecialchars(strtolower($company_name)); ?>" data-students="<?php echo htmlspecialchars($studentNamesStr); ?>">
                                <div class="company-header">
                                    <h3 onclick="toggleCompany('<?php echo $companyId; ?>')" style="flex: 1; cursor: pointer;">
                                        <i class="fas fa-building"></i>
                                        <?php echo htmlspecialchars($company_name); ?>
                                        <span class="company-count"><?php echo count($students); ?> student(s)</span>
                                    </h3>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <button class="schedule-btn" onclick="event.stopPropagation(); openRoundModal('<?php echo htmlspecialchars($company_name); ?>', '<?php echo $companyId; ?>')">
                                            <i class="fas fa-calendar-plus"></i> Schedule Interview
                                        </button>
                                        <i class="fas fa-chevron-down toggle-icon" id="icon-<?php echo $companyId; ?>" onclick="toggleCompany('<?php echo $companyId; ?>')" style="cursor: pointer;"></i>
                                    </div>
                                </div>
                                <div class="company-table-container" id="<?php echo $companyId; ?>">
                                    <table class="company-table">
                                        <thead>
                                            <tr>
                                                <th>Student</th>
                                                <th>Role</th>
                                                <th>Applied Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($students as $s): ?>
                                                <tr>
                                                    <td style="font-weight: 600; color: #1f2937; padding: 12px 16px;">
                                                        <?php echo htmlspecialchars($s['full_name'] ?? 'N/A'); ?>
                                                    </td>
                                                    <td style="color: #6b7280; padding: 12px 16px;">
                                                        <?php echo htmlspecialchars($s['role'] ?? $s['job_role'] ?? $s['job_title'] ?? 'N/A'); ?>
                                                    </td>
                                                    <td style="color: #6b7280; font-size: 13px; padding: 12px 16px;">
                                                        <?php echo $s['applied_at'] ? date('M d, Y', strtotime($s['applied_at'])) : '-'; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card">
                <div class="title"><i class="fas fa-calendar-check"></i> Scheduled Interviews</div>
                <table>
                    <thead>
                        <tr><th>Student</th><th>Company</th><th>Role</th><th>Date</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($interviews)): ?>
                            <tr><td colspan="5" style="color:#6b7280;">No interviews scheduled yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($interviews as $i): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($i['full_name'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($i['company']); ?></td>
                                    <td><?php echo htmlspecialchars($i['job_role']); ?></td>
                                    <td><?php echo $i['scheduled_at'] ? date('M d, Y H:i', strtotime($i['scheduled_at'])) : '-'; ?></td>
                                    <td><?php echo htmlspecialchars($i['status']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Round Selection Modal -->
    <div id="roundModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-list-check"></i> Select Interview Rounds</h3>
                <button class="close-modal" onclick="closeRoundModal()">&times;</button>
            </div>
            <form id="roundForm">
                <input type="hidden" id="selectedCompany" name="company_name">
                <div id="roundsContainer">
                    <div class="round-checkbox">
                        <input type="checkbox" id="round_aptitude" name="interview_rounds[]" value="Aptitude Round">
                        <label for="round_aptitude">Aptitude Round</label>
                    </div>
                    <div class="round-checkbox">
                        <input type="checkbox" id="round_technical" name="interview_rounds[]" value="Technical Round">
                        <label for="round_technical">Technical Round</label>
                    </div>
                    <div class="round-checkbox">
                        <input type="checkbox" id="round_hr" name="interview_rounds[]" value="HR Round">
                        <label for="round_hr">HR Round</label>
                    </div>
                    <div class="round-checkbox">
                        <input type="checkbox" id="round_group" name="interview_rounds[]" value="Group Discussion">
                        <label for="round_group">Group Discussion</label>
                    </div>
                    <div class="round-checkbox">
                        <input type="checkbox" id="round_final" name="interview_rounds[]" value="Final Round">
                        <label for="round_final">Final Round</label>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="closeRoundModal()">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="proceedToSchedule()">Next: Schedule</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Schedule Interview Modal -->
    <div id="scheduleModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-calendar-check"></i> Schedule Interview</h3>
                <button class="close-modal" onclick="closeScheduleModal()">&times;</button>
            </div>
            <form id="scheduleForm" method="POST">
                <input type="hidden" name="schedule_company" value="1">
                <input type="hidden" id="scheduleCompanyName" name="company_name">
                <input type="hidden" id="scheduleRounds" name="interview_rounds_json">
                
                <div style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 6px; font-weight: 600; color: #4b5563; font-size: 13px;">Selected Rounds:</label>
                    <div id="selectedRoundsDisplay" style="padding: 12px; background: #f9fafb; border-radius: 8px; color: #374151; font-size: 14px;"></div>
                </div>

                <div style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 6px; font-weight: 600; color: #4b5563; font-size: 13px;">Date & Time <span style="color: red;">*</span></label>
                    <input type="datetime-local" name="scheduled_at" id="scheduleDateTime" required style="width: 100%; padding: 10px; border: 2px solid #e5e7eb; border-radius: 8px; font-family: inherit;">
                </div>

                <div style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 6px; font-weight: 600; color: #4b5563; font-size: 13px;">Panel Details (Optional)</label>
                    <textarea name="panel_details" placeholder="Enter panel details, venue, or other notes..." rows="3" style="width: 100%; padding: 10px; border: 2px solid #e5e7eb; border-radius: 8px; font-family: inherit; resize: vertical;"></textarea>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="closeScheduleModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-calendar-check"></i> Schedule Interview
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Toggle individual company section
        function toggleCompany(companyId) {
            const container = document.getElementById(companyId);
            const icon = document.getElementById('icon-' + companyId);
            const group = container.closest('.company-group');
            
            if (container.style.display === 'none') {
                container.style.display = 'block';
                icon.classList.remove('fa-chevron-right');
                icon.classList.add('fa-chevron-down');
                group.classList.remove('collapsed');
            } else {
                container.style.display = 'none';
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-right');
                group.classList.add('collapsed');
            }
        }

        // Toggle all companies
        let allExpanded = true;
        function toggleAllCompanies() {
            const containers = document.querySelectorAll('.company-table-container');
            const icons = document.querySelectorAll('.toggle-icon');
            const groups = document.querySelectorAll('.company-group');
            const expandBtn = document.getElementById('expandAllIcon');
            const expandText = document.getElementById('expandAllText');
            
            allExpanded = !allExpanded;
            
            containers.forEach(container => {
                container.style.display = allExpanded ? 'block' : 'none';
            });
            
            icons.forEach(icon => {
                if (allExpanded) {
                    icon.classList.remove('fa-chevron-right');
                    icon.classList.add('fa-chevron-down');
                } else {
                    icon.classList.remove('fa-chevron-down');
                    icon.classList.add('fa-chevron-right');
                }
            });
            
            groups.forEach(group => {
                if (allExpanded) {
                    group.classList.remove('collapsed');
                } else {
                    group.classList.add('collapsed');
                }
            });
            
            expandBtn.className = allExpanded ? 'fas fa-chevron-up' : 'fas fa-chevron-down';
            expandText.textContent = allExpanded ? 'Collapse All' : 'Expand All';
        }

        // Show only selected company's group (from card click)
        function showCompany(companyId) {
            // deactivate all cards
            document.querySelectorAll('.company-card').forEach(c => c.classList.remove('active'));
            const activeCard = document.getElementById('card-' + companyId);
            if (activeCard) activeCard.classList.add('active');

            // hide all groups
            document.querySelectorAll('.company-group').forEach(group => {
                group.style.display = 'none';
            });

            // show selected group and ensure its table is visible
            const group = document.getElementById('group-' + companyId);
            if (group) {
                group.style.display = 'block';
                const container = document.getElementById(companyId);
                if (container) container.style.display = 'block';
                const icon = document.getElementById('icon-' + companyId);
                if (icon) {
                    icon.classList.remove('fa-chevron-right');
                    icon.classList.add('fa-chevron-down');
                }
                group.classList.remove('collapsed');
                group.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }

        // Filter companies by search
        function filterCompanies() {
            const searchTerm = document.getElementById('companySearch').value.toLowerCase();
            const groups = document.querySelectorAll('.company-group');
            
            groups.forEach(group => {
                const companyName = group.getAttribute('data-company');
                const students = group.getAttribute('data-students');
                const matches = companyName.includes(searchTerm) || students.includes(searchTerm);
                
                group.style.display = matches ? 'block' : 'none';
            });
        }

        // Round Selection Modal Functions
        let currentCompanyName = '';
        let currentCompanyId = '';
        
        function openRoundModal(companyName, companyId) {
            currentCompanyName = companyName;
            currentCompanyId = companyId;
            document.getElementById('selectedCompany').value = companyName;
            document.getElementById('roundModal').style.display = 'block';
            // Reset checkboxes
            document.querySelectorAll('#roundForm input[type="checkbox"]').forEach(cb => cb.checked = false);
        }
        
        function closeRoundModal() {
            document.getElementById('roundModal').style.display = 'none';
        }
        
        function proceedToSchedule() {
            const selectedRounds = Array.from(document.querySelectorAll('#roundForm input[type="checkbox"]:checked')).map(cb => cb.value);
            
            if (selectedRounds.length === 0) {
                alert('Please select at least one interview round.');
                return;
            }
            
            // Close round modal and open schedule modal
            closeRoundModal();
            
            // Set form values
            document.getElementById('scheduleCompanyName').value = currentCompanyName;
            document.getElementById('scheduleRounds').value = JSON.stringify(selectedRounds);
            
            // Display selected rounds
            document.getElementById('selectedRoundsDisplay').innerHTML = selectedRounds.map(r => `<span style="display: inline-block; background: #5b1f1f; color: white; padding: 4px 10px; border-radius: 12px; margin: 4px; font-size: 12px;">${r}</span>`).join('');
            
            // Open schedule modal
            document.getElementById('scheduleModal').style.display = 'block';
        }
        
        function closeScheduleModal() {
            document.getElementById('scheduleModal').style.display = 'none';
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const roundModal = document.getElementById('roundModal');
            const scheduleModal = document.getElementById('scheduleModal');
            if (event.target === roundModal) {
                closeRoundModal();
            }
            if (event.target === scheduleModal) {
                closeScheduleModal();
            }
        }
        
        // Initialize all expanded by default
        document.addEventListener('DOMContentLoaded', function() {
            // Start with all groups hidden until a company card is clicked
            document.querySelectorAll('.company-group').forEach(group => {
                group.style.display = 'none';
            });
            // If there is at least one company, auto-select the first card
            const firstCard = document.querySelector('.company-card');
            if (firstCard) {
                const id = firstCard.id.replace('card-', '');
                showCompany(id);
            }
        });
    </script>
</body>
</html>


