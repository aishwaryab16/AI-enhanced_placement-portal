<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/job_backend.php';

if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'placement_officer' && $_SESSION['role'] !== 'admin')) {
    header('Location: login.php');
    exit;
}

$mysqli = $GLOBALS['mysqli'] ?? new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

// Setup tables
setupJobTables($mysqli);

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
$applications = [];
$query = "
    SELECT 
        ja.*,
        COALESCE(ja.full_name, u.full_name, CONCAT('Student ID: ', ja.student_id)) as student_name,
        u.email as student_email,
        u.phone as student_phone,
        u.cgpa as student_cgpa,
        u.branch as student_branch,
        COALESCE(jo.company, ja.company_name) as company,
        COALESCE(jo.role, ja.job_role, ja.job_title) as job_role,
        COALESCE(jo.location, ja.location) as job_location
    FROM job_applications ja
    LEFT JOIN users u ON ja.student_id = u.id
    LEFT JOIN job_opportunities jo ON (ja.job_id = jo.id OR (ja.company_name = jo.company AND ja.job_title = jo.role))
    ORDER BY ja.applied_at DESC
";

$result = $mysqli->query($query);
if ($result) {
    $applications = $result->fetch_all(MYSQLI_ASSOC);
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
    grid-template-columns: 1fr 1.5fr 1fr 1fr 1fr 1fr 1.5fr;
    gap: 15px;
    padding: 15px 20px;
    background: #5b1f1f;
    color: white;
    font-weight: 600;
    font-size: 14px;
}

.table-row {
    display: grid;
    grid-template-columns: 1fr 1.5fr 1fr 1fr 1fr 1fr 1.5fr;
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
                <div>Student</div>
                <div>Job Details</div>
                <div>Company</div>
                <div>Match %</div>
                <div>Applied On</div>
                <div>Status</div>
                <div>Actions</div>
            </div>
            
            <?php foreach ($applications as $app): ?>
                <div class="table-row">
                    <div>
                        <div style="font-weight: 600; color: #333;"><?php echo htmlspecialchars($app['student_name'] ?? 'N/A'); ?></div>
                        <div style="font-size: 12px; color: #666;"><?php echo htmlspecialchars($app['student_email'] ?? ''); ?></div>
                        <div style="font-size: 11px; color: #999;">CGPA: <?php echo $app['student_cgpa'] ?? 'N/A'; ?></div>
                    </div>
                    <div>
                        <div style="font-weight: 600; color: #5b1f1f;"><?php echo htmlspecialchars($app['job_title'] ?? $app['job_role'] ?? 'N/A'); ?></div>
                        <div style="font-size: 12px; color: #666;"><?php echo htmlspecialchars($app['location'] ?? $app['job_location'] ?? 'N/A'); ?></div>
                        <div style="font-size: 11px; color: #999;"><?php echo htmlspecialchars($app['salary_range'] ?? 'N/A'); ?></div>
                    </div>
                    <div style="font-weight: 600; color: #333;">
                        <?php echo htmlspecialchars($app['company_name'] ?? 'N/A'); ?>
                    </div>
                    <div>
                        <span style="background: #e8f5e9; color: #2e7d32; padding: 4px 10px; border-radius: 12px; font-weight: 600; font-size: 13px;">
                            <?php echo $app['match_percentage'] ?? 0; ?>%
                        </span>
                    </div>
                    <div style="font-size: 13px; color: #666;">
                        <?php echo date('M d, Y', strtotime($app['applied_at'] ?? 'now')); ?>
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

