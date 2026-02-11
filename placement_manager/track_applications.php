<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/job_backend.php';

if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'placement_officer' && $_SESSION['role'] !== 'admin')) {
    header('Location: login.php');
    exit;
}

$mysqli = $GLOBALS['mysqli'] ?? new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

// Check database connection
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Setup tables
setupJobTables($mysqli);

// Verify table exists and has data
$table_check = $mysqli->query("SHOW TABLES LIKE 'job_applications'");
if (!$table_check || $table_check->num_rows === 0) {
    error_log("ERROR: job_applications table does not exist!");
}

$message = '';
$messageType = '';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $application_id = $_POST['application_id'] ?? 0;
    $new_status = $_POST['application_status'] ?? 'Applied';
    
    $stmt = $mysqli->prepare("UPDATE job_applications SET application_status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $application_id);
    
    if ($stmt->execute()) {
        $message = "Application status updated successfully!";
        $messageType = "success";
    } else {
        $message = "Error updating status: " . $mysqli->error;
        $messageType = "error";
    }
    $stmt->close();
}

// Fetch all applications with student and job details
// This is a GET request - fetch all data from job_applications table
$applications = [];

// First, let's check if there are any applications at all
$count_query = "SELECT COUNT(*) as total FROM job_applications";
$count_result = $mysqli->query($count_query);
$total_count = 0;
if ($count_result) {
    $count_row = $count_result->fetch_assoc();
    $total_count = $count_row['total'] ?? 0;
} else {
    error_log("Error counting applications: " . $mysqli->error);
}

// Also get a raw sample to verify data exists
$sample_query = "SELECT * FROM job_applications LIMIT 5";
$sample_result = $mysqli->query($sample_query);
$sample_data = [];
if ($sample_result) {
    $sample_data = $sample_result->fetch_all(MYSQLI_ASSOC);
}

// Fetch all applications - ensure we get everything from job_applications table
// Use LEFT JOIN so we get all applications even if user or job data is missing
$query = "
    SELECT 
        ja.id,
        ja.job_id,
        ja.student_id,
        ja.job_title,
        ja.company_name,
        ja.job_role,
        ja.location,
        ja.salary_range,
        ja.min_cgpa,
        ja.required_skills,
        ja.match_percentage,
        ja.application_status,
        ja.applied_at,
        ja.updated_at,
        ja.resume_path,
        ja.resume_json,
        COALESCE(ja.full_name, u.full_name, CONCAT('Student ID: ', ja.student_id)) as student_name,
        COALESCE(ja.username, u.username, '') as username,
        u.resume_link,
        COALESCE(u.email, '') as student_email,
        COALESCE(u.phone, '') as student_phone,
        COALESCE(u.cgpa, 0) as student_cgpa,
        COALESCE(u.branch, '') as student_branch,
        COALESCE(u.semester, '') as student_semester,
        COALESCE(jo.company, ja.company_name, 'Unknown Company') as company,
        COALESCE(jo.role, ja.job_role, ja.job_title, 'Unknown Role') as job_role_display,
        COALESCE(jo.location, ja.location, 'Location not specified') as job_location
    FROM job_applications ja
    LEFT JOIN users u ON ja.student_id = u.id
    LEFT JOIN job_opportunities jo ON ja.job_id = jo.id
    ORDER BY ja.applied_at DESC
";

$result = $mysqli->query($query);
if ($result) {
    $applications = $result->fetch_all(MYSQLI_ASSOC);
} else {
    // Log error for debugging
    $error_msg = "Error fetching applications: " . $mysqli->error;
    error_log($error_msg);
    // If query fails, try a simpler query without joins
    $simple_query = "SELECT * FROM job_applications ORDER BY applied_at DESC";
    $simple_result = $mysqli->query($simple_query);
    if ($simple_result) {
        $applications = $simple_result->fetch_all(MYSQLI_ASSOC);
        // Add placeholder fields for missing joined data
        foreach ($applications as &$app) {
            $app['student_name'] = $app['full_name'] ?? 'Student ID: ' . ($app['student_id'] ?? 'N/A');
            $app['username'] = $app['username'] ?? '';
            $app['resume_link'] = '';
            $app['student_email'] = '';
            $app['student_phone'] = '';
            $app['student_cgpa'] = 0;
            $app['student_branch'] = '';
            $app['student_semester'] = '';
            $app['company'] = $app['company_name'] ?? 'Unknown Company';
            $app['job_role_display'] = $app['job_role'] ?? $app['job_title'] ?? 'Unknown Role';
            $app['job_location'] = $app['location'] ?? 'Location not specified';
        }
        unset($app);
    } else {
        $applications = [];
    }
}

// Debug: Log if we have applications but query returned empty
if ($total_count > 0 && empty($applications)) {
    error_log("Warning: Found {$total_count} applications in table but query returned empty. Query: " . $query);
    error_log("MySQL Error: " . $mysqli->error);
}

// Get filter parameters
$filter_status = $_GET['status'] ?? '';
$filter_company = $_GET['company'] ?? '';
$filter_student = $_GET['student'] ?? '';

// Apply filters
if ($filter_status || $filter_company || $filter_student) {
    $applications = array_filter($applications, function($app) use ($filter_status, $filter_company, $filter_student) {
        if ($filter_status && $app['application_status'] !== $filter_status) return false;
        if ($filter_company && stripos($app['company_name'], $filter_company) === false) return false;
        if ($filter_student && stripos($app['student_name'], $filter_student) === false) return false;
        return true;
    });
}

// Get statistics
$total_applications = count($applications);
$status_counts = [
    'Applied' => 0,
    'Shortlisted' => 0,
    'Interviewed' => 0,
    'Selected' => 0,
    'Rejected' => 0
];

foreach ($applications as $app) {
    $status = $app['application_status'] ?? 'Applied';
    if (isset($status_counts[$status])) {
        $status_counts[$status]++;
    }
}
?>
<?php include __DIR__ . '/../includes/partials/header.php'; ?>

<style>
.track-applications-container {
    max-width: 1400px;
    margin: 30px auto;
    padding: 20px;
}

.page-header {
    background: linear-gradient(135deg, #5b1f1f, #ecc35c);
    color: white;
    padding: 30px;
    border-radius: 15px;
    margin-bottom: 30px;
}

.page-header h1 {
    margin: 0 0 10px 0;
    font-size: 32px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    text-align: center;
    border-left: 4px solid #5b1f1f;
}

.stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: #5b1f1f;
    margin-bottom: 5px;
}

.stat-label {
    color: #666;
    font-size: 0.9rem;
}

.filters {
    background: white;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    align-items: end;
}

.filter-group {
    flex: 1;
    min-width: 200px;
}

.filter-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: #333;
}

.filter-group input,
.filter-group select {
    width: 100%;
    padding: 10px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 14px;
}

.applications-table {
    background: white;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.table-header {
    display: grid;
    grid-template-columns: 1.5fr 1fr 0.8fr 0.8fr 0.8fr 1.2fr 1fr 1fr 1fr 1fr 1.5fr;
    gap: 15px;
    padding: 15px 20px;
    background: #5b1f1f;
    color: white;
    font-weight: 600;
    font-size: 14px;
}

.table-row {
    display: grid;
    grid-template-columns: 1.5fr 1fr 0.8fr 0.8fr 0.8fr 1.2fr 1fr 1fr 1fr 1fr 1.5fr;
    gap: 15px;
    padding: 15px 20px;
    border-bottom: 1px solid #e0e0e0;
    align-items: center;
}

.table-row:hover {
    background: #f8f9fa;
}

.table-row:last-child {
    border-bottom: none;
}

.status-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-align: center;
}

.status-applied { background: #e3f2fd; color: #1976d2; }
.status-shortlisted { background: #fff3e0; color: #f57c00; }
.status-interviewed { background: #f3e5f5; color: #7b1fa2; }
.status-selected { background: #e8f5e9; color: #2e7d32; }
.status-rejected { background: #ffebee; color: #c62828; }

.status-select {
    padding: 6px 10px;
    border: 2px solid #e0e0e0;
    border-radius: 6px;
    font-size: 13px;
    cursor: pointer;
}

.btn-update {
    background: #5b1f1f;
    color: white;
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    font-size: 13px;
}

.btn-update:hover {
    background: #ecc35c;
    color: #5b1f1f;
}

.alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #999;
}

.empty-state i {
    font-size: 64px;
    margin-bottom: 20px;
    opacity: 0.3;
}
</style>

<div class="track-applications-container">
    <div class="page-header">
        <h1>📊 Track Applications</h1>
        <p>Monitor and manage all student job applications</p>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- Debug Info (temporary - remove after testing) -->
    <?php if (isset($_GET['debug'])): ?>
        <div class="alert" style="background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; padding: 20px; margin-bottom: 20px;">
            <strong>Debug Info:</strong><br>
            Total applications in database: <strong><?php echo $total_count; ?></strong><br>
            Applications fetched by query: <strong><?php echo count($applications); ?></strong><br>
            Raw sample data count: <strong><?php echo count($sample_data); ?></strong><br>
            <br>
            <?php if (!empty($sample_data)): ?>
                <details>
                    <summary><strong>Raw Sample Data (first 5 rows from job_applications table):</strong></summary>
                    <pre style="background: #f5f5f5; padding: 10px; overflow-x: auto; font-size: 11px;"><?php print_r($sample_data); ?></pre>
                </details>
            <?php else: ?>
                <p style="color: #dc3545;"><strong>WARNING: No raw data found in job_applications table!</strong></p>
            <?php endif; ?>
            <br>
            <?php if (!empty($applications)): ?>
                <details>
                    <summary><strong>First Application After JOIN:</strong></summary>
                    <pre style="background: #f5f5f5; padding: 10px; overflow-x: auto; font-size: 11px;"><?php print_r($applications[0]); ?></pre>
                </details>
            <?php else: ?>
                <p style="color: #dc3545;"><strong>WARNING: Query returned no results even though <?php echo $total_count; ?> applications exist!</strong></p>
                <p>This suggests a JOIN issue. Check error logs for details.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?php echo $total_applications; ?></div>
            <div class="stat-label">Total Applications</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $status_counts['Applied']; ?></div>
            <div class="stat-label">Applied</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $status_counts['Shortlisted']; ?></div>
            <div class="stat-label">Shortlisted</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $status_counts['Interviewed']; ?></div>
            <div class="stat-label">Interviewed</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $status_counts['Selected']; ?></div>
            <div class="stat-label">Selected</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $status_counts['Rejected']; ?></div>
            <div class="stat-label">Rejected</div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters">
        <div class="filter-group">
            <label>Filter by Status</label>
            <select id="statusFilter" onchange="applyFilters()">
                <option value="">All Statuses</option>
                <option value="Applied" <?php echo $filter_status === 'Applied' ? 'selected' : ''; ?>>Applied</option>
                <option value="Shortlisted" <?php echo $filter_status === 'Shortlisted' ? 'selected' : ''; ?>>Shortlisted</option>
                <option value="Interviewed" <?php echo $filter_status === 'Interviewed' ? 'selected' : ''; ?>>Interviewed</option>
                <option value="Selected" <?php echo $filter_status === 'Selected' ? 'selected' : ''; ?>>Selected</option>
                <option value="Rejected" <?php echo $filter_status === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
            </select>
        </div>
        <div class="filter-group">
            <label>Filter by Company</label>
            <input type="text" id="companyFilter" placeholder="Company name..." value="<?php echo htmlspecialchars($filter_company); ?>" onkeyup="applyFilters()">
        </div>
        <div class="filter-group">
            <label>Filter by Student</label>
            <input type="text" id="studentFilter" placeholder="Student name..." value="<?php echo htmlspecialchars($filter_student); ?>" onkeyup="applyFilters()">
        </div>
    </div>

    <!-- Applications Table -->
    <div class="applications-table">
        <?php if (empty($applications)): ?>
            <div class="empty-state">
                <i class="fas fa-file-alt"></i>
                <p style="font-size: 18px; font-weight: 600; margin-bottom: 8px;">No Applications Found</p>
                <p>No students have applied for jobs yet.</p>
            </div>
        <?php else: ?>
            <div class="table-header">
                <div>Full Name</div>
                <div>USN</div>
                <div>Branch</div>
                <div>Semester</div>
                <div>CGPA</div>
                <div>Company</div>
                <div>Job Role</div>
                <div>Applied On</div>
                <div>Resume</div>
                <div>Status</div>
                <div>Actions</div>
            </div>
            
            <?php foreach ($applications as $app): ?>
                <div class="table-row">
                    <div>
                        <div style="font-weight: 600; color: #333;"><?php echo htmlspecialchars($app['student_name'] ?? 'Student ID: ' . ($app['student_id'] ?? 'N/A')); ?></div>
                    </div>
                    <div>
                        <div style="color: #666;"><?php echo htmlspecialchars($app['username'] ?? 'N/A'); ?></div>
                    </div>
                    <div style="color: #666; font-size: 13px;">
                        <?php echo htmlspecialchars($app['student_branch'] ?? 'N/A'); ?>
                    </div>
                    <div style="color: #666; font-size: 13px;">
                        <?php echo htmlspecialchars($app['student_semester'] ?? ($app['semester'] ?? 'N/A')); ?>
                    </div>
                    <div style="color: #666; font-size: 13px; font-weight: 600;">
                        <?php 
                        $cgpa = $app['student_cgpa'] ?? $app['cgpa'] ?? 0;
                        echo $cgpa > 0 ? number_format((float)$cgpa, 2) : 'N/A';
                        ?>
                    </div>
                    <div style="font-weight: 600; color: #333;">
                        <?php echo htmlspecialchars($app['company'] ?? $app['company_name'] ?? 'N/A'); ?>
                    </div>
                    <div style="color: #666; font-size: 13px;">
                        <?php echo htmlspecialchars($app['job_role_display'] ?? $app['job_role'] ?? $app['job_title'] ?? 'N/A'); ?>
                    </div>
                    <div style="font-size: 13px; color: #666;">
                        <?php echo $app['applied_at'] ? date('M d, Y', strtotime($app['applied_at'])) : 'N/A'; ?>
                    </div>
                    <div>
                        <?php 
                        // Priority: 1. Check if resume_json exists (generated resume), 2. resume_path, 3. resume_link, 4. check uploads folder
                        $has_resume_json = !empty($app['resume_json']);
                        $resume_path = $app['resume_path'] ?? '';
                        $resume_link = $app['resume_link'] ?? '';
                        $resume_file = '';
                        
                        // Check if resume exists in uploads folder (fallback)
                        if (empty($resume_path) && !empty($app['student_id'])) {
                            $resume_pattern = __DIR__ . "/../uploads/resumes/resume_" . $app['student_id'] . "_*";
                            $resume_files = glob($resume_pattern);
                            if (!empty($resume_files)) {
                                // Get relative path from project root
                                $resume_file = str_replace(__DIR__ . '/../', '', $resume_files[0]);
                            }
                        }
                        
                        if ($has_resume_json): 
                            // Generated resume - show in HTML viewer
                        ?>
                            <a href="view_resume.php?id=<?php echo $app['id']; ?>" target="_blank" style="color: #5b1f1f; text-decoration: none; font-weight: 600; font-size: 13px;">
                                <i class="fas fa-file-alt"></i> View Resume
                            </a>
                        <?php elseif (!empty($resume_path)): 
                            // Resume uploaded with application
                            $resume_url = (strpos($resume_path, '/') === 0) ? $resume_path : '../' . $resume_path;
                        ?>
                            <a href="<?php echo htmlspecialchars($resume_url); ?>" target="_blank" style="color: #5b1f1f; text-decoration: none; font-weight: 600; font-size: 13px;">
                                <i class="fas fa-file-pdf"></i> View Resume
                            </a>
                        <?php elseif (!empty($resume_link)): 
                            // If it's a full URL, use it directly
                            if (filter_var($resume_link, FILTER_VALIDATE_URL)): ?>
                                <a href="<?php echo htmlspecialchars($resume_link); ?>" target="_blank" style="color: #5b1f1f; text-decoration: none; font-weight: 600; font-size: 13px;">
                                    <i class="fas fa-file-pdf"></i> View Resume
                                </a>
                            <?php else: 
                                // If it's a relative path, make it absolute
                                $resume_url = (strpos($resume_link, '/') === 0) ? $resume_link : '../' . $resume_link;
                            ?>
                                <a href="<?php echo htmlspecialchars($resume_url); ?>" target="_blank" style="color: #5b1f1f; text-decoration: none; font-weight: 600; font-size: 13px;">
                                    <i class="fas fa-file-pdf"></i> View Resume
                                </a>
                            <?php endif;
                        elseif (!empty($resume_file)): ?>
                            <a href="../<?php echo htmlspecialchars($resume_file); ?>" target="_blank" style="color: #5b1f1f; text-decoration: none; font-weight: 600; font-size: 13px;">
                                <i class="fas fa-file-pdf"></i> View Resume
                            </a>
                        <?php else: ?>
                            <span style="color: #9ca3af; font-style: italic; font-size: 13px;">No resume</span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <span class="status-badge status-<?php echo strtolower($app['application_status'] ?? 'applied'); ?>">
                            <?php echo htmlspecialchars($app['application_status'] ?? 'Applied'); ?>
                        </span>
                    </div>
                    <div>
                        <form method="POST" style="display: flex; gap: 8px; align-items: center;">
                            <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                            <select name="application_status" class="status-select" required>
                                <option value="Applied" <?php echo ($app['application_status'] ?? '') === 'Applied' ? 'selected' : ''; ?>>Applied</option>
                                <option value="Shortlisted" <?php echo ($app['application_status'] ?? '') === 'Shortlisted' ? 'selected' : ''; ?>>Shortlisted</option>
                                <option value="Interviewed" <?php echo ($app['application_status'] ?? '') === 'Interviewed' ? 'selected' : ''; ?>>Interviewed</option>
                                <option value="Selected" <?php echo ($app['application_status'] ?? '') === 'Selected' ? 'selected' : ''; ?>>Selected</option>
                                <option value="Rejected" <?php echo ($app['application_status'] ?? '') === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                            <button type="submit" name="update_status" class="btn-update">Update</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function applyFilters() {
    const status = document.getElementById('statusFilter').value;
    const company = document.getElementById('companyFilter').value;
    const student = document.getElementById('studentFilter').value;
    
    const params = new URLSearchParams();
    if (status) params.append('status', status);
    if (company) params.append('company', company);
    if (student) params.append('student', student);
    
    window.location.href = 'track_applications.php?' + params.toString();
}
</script>

<?php include __DIR__ . '/../includes/partials/footer.php'; ?>

