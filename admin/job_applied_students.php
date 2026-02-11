<?php
require_once __DIR__ . '/../includes/config.php';
require_role('admin');

$admin_id = $_SESSION['user_id'];

// Get filter parameters
$job_role_filter = $_GET['job_role'] ?? '';
$status_filter = $_GET['status'] ?? '';
$search_term = $_GET['search'] ?? '';

// Build the query
$query = "
    SELECT 
        ja.*,
        u.username,
        u.full_name,
        u.email,
        u.branch,
        u.semester,
        u.cgpa
    FROM job_applications ja
    JOIN users u ON ja.student_id = u.id
    WHERE 1=1
";

$params = [];
$types = '';

if (!empty($job_role_filter)) {
    $query .= " AND ja.job_role = ?";
    $params[] = $job_role_filter;
    $types .= 's';
}

if (!empty($status_filter)) {
    $query .= " AND ja.application_status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if (!empty($search_term)) {
    $query .= " AND (u.username LIKE ? OR u.full_name LIKE ? OR ja.company_name LIKE ? OR ja.job_title LIKE ?)";
    $search_param = "%$search_term%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $types .= 'ssss';
}

$query .= " ORDER BY ja.applied_at DESC";

$stmt = $mysqli->prepare($query);
if ($stmt && !empty($params)) {
    $stmt->bind_param($types, ...$params);
} elseif ($stmt) {
    // No parameters
}

$applications = [];
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    $applications = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Get unique job roles for filter
$roles_query = "SELECT DISTINCT job_role FROM job_applications ORDER BY job_role";
$roles_result = $mysqli->query($roles_query);
$job_roles = $roles_result ? $roles_result->fetch_all(MYSQLI_ASSOC) : [];

// Get statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_applications,
        COUNT(CASE WHEN application_status = 'Applied' THEN 1 END) as applied,
        COUNT(CASE WHEN application_status = 'Shortlisted' THEN 1 END) as shortlisted,
        COUNT(CASE WHEN application_status = 'Interviewed' THEN 1 END) as interviewed,
        COUNT(CASE WHEN application_status = 'Selected' THEN 1 END) as selected,
        COUNT(CASE WHEN application_status = 'Rejected' THEN 1 END) as rejected
    FROM job_applications
";
$stats_result = $mysqli->query($stats_query);
$stats = $stats_result ? $stats_result->fetch_assoc() : [];

include __DIR__ . '/../includes/partials/header.php';
?>

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

.faculty-dashboard {
    display: grid;
    grid-template-columns: 260px 1fr;
    min-height: 100vh;
    background: #f5f7fa;
}

/* Sidebar */
.faculty-sidebar {
    background: linear-gradient(180deg, #5b1f1f 0%, #3d1414 100%);
    color: white;
    padding: 20px;
    position: sticky;
    top: 0;
    height: 100vh;
    overflow-y: auto;
}

.sidebar-header {
    text-align: center;
    padding: 20px 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    margin-bottom: 20px;
}

.admin-avatar {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    background: linear-gradient(135deg, #ecc35c, #f7f3b7);
    color: #5b1f1f;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    font-weight: 700;
    margin: 0 auto 15px;
    border: 3px solid rgba(236, 195, 92, 0.3);
}

.admin-name {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 5px;
}

.admin-role {
    font-size: 14px;
    opacity: 0.8;
}

.sidebar-nav {
    margin-top: 20px;
}

.nav-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 15px;
    border-radius: 8px;
    color: white;
    text-decoration: none;
    transition: all 0.3s;
    margin-bottom: 5px;
}

.nav-item:hover, .nav-item.active {
    background: rgba(236, 195, 92, 0.2);
    transform: translateX(5px);
}

.nav-icon {
    font-size: 20px;
}

/* Main Content */
.faculty-content {
    padding: 30px;
    overflow-y: auto;
}

.page-header {
    margin-bottom: 30px;
}

.page-title {
    font-size: 32px;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 5px;
}

.page-subtitle {
    color: #6b7280;
    font-size: 16px;
}

/* Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 16px;
    padding: 25px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    transition: all 0.3s;
    border-left: 4px solid #5b1f1f;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
}

.stat-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.stat-icon {
    font-size: 32px;
}

.stat-value {
    font-size: 36px;
    font-weight: 700;
    color: #5b1f1f;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 14px;
    color: #6b7280;
}

/* Filters */
.filters-section {
    background: white;
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 30px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.filters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.filter-label {
    font-size: 14px;
    font-weight: 600;
    color: #4b5563;
}

.filter-input, .filter-select {
    padding: 10px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 14px;
}

/* Applications Table */
.applications-section {
    background: white;
    border-radius: 16px;
    padding: 25px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.section-title {
    font-size: 20px;
    font-weight: 600;
    color: #1f2937;
}

.applications-table {
    width: 100%;
    border-collapse: collapse;
}

.applications-table thead {
    background: #f9fafb;
    border-bottom: 2px solid #e5e7eb;
}

.applications-table th {
    padding: 12px;
    text-align: left;
    font-size: 13px;
    font-weight: 600;
    color: #6b7280;
    text-transform: uppercase;
}

.applications-table td {
    padding: 15px 12px;
    border-bottom: 1px solid #f3f4f6;
}

.applications-table tbody tr {
    transition: all 0.2s;
}

.applications-table tbody tr:hover {
    background: #fef9f5;
}

.student-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.student-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #5b1f1f, #ecc35c);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 14px;
}

.student-details {
    display: flex;
    flex-direction: column;
}

.student-name {
    font-weight: 600;
    color: #1f2937;
    font-size: 14px;
}

.student-email {
    font-size: 12px;
    color: #6b7280;
}

.job-info {
    display: flex;
    flex-direction: column;
}

.job-title {
    font-weight: 600;
    color: #1f2937;
    font-size: 14px;
}

.company-name {
    font-size: 12px;
    color: #6b7280;
}

.status-badge {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-align: center;
}

.status-applied {
    background: #dbeafe;
    color: #1e40af;
}

.status-shortlisted {
    background: #fef3c7;
    color: #92400e;
}

.status-interviewed {
    background: #e0e7ff;
    color: #3730a3;
}

.status-selected {
    background: #d1fae5;
    color: #065f46;
}

.status-rejected {
    background: #fee2e2;
    color: #991b1b;
}

.match-badge {
    background: #d1fae5;
    color: #065f46;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
}

.action-buttons {
    display: flex;
    gap: 8px;
}

.btn-action {
    padding: 6px 12px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 600;
    transition: all 0.2s;
}

.btn-view {
    background: #dbeafe;
    color: #1e40af;
}

.btn-update {
    background: #fef3c7;
    color: #92400e;
}

.btn-action:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.btn-submit {
    background: linear-gradient(135deg, #5b1f1f, #ecc35c);
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(91, 31, 31, 0.3);
}
</style>

<div class="faculty-dashboard">
    <!-- Sidebar -->
    <aside class="faculty-sidebar">
        <div class="sidebar-header">
            <div class="admin-avatar">
                <?php echo strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)); ?>
            </div>
            <div class="admin-name"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></div>
            <div class="admin-role">Faculty / Mentor</div>
        </div>

        <nav class="sidebar-nav">
            <a href="admin_dashboard_enhanced.php" class="nav-item">
                <span class="nav-icon">📊</span>
                <span>Overview</span>
            </a>
            <a href="#students" class="nav-item">
                <span class="nav-icon">👥</span>
                <span>Students</span>
            </a>
            <a href="resume_review.php" class="nav-item">
                <span class="nav-icon">🤖</span>
                <span>Resume Review</span>
            </a>
            <a href="feedback.php" class="nav-item">
                <span class="nav-icon">💬</span>
                <span>Feedback</span>
            </a>
            <a href="#reports" class="nav-item">
                <span class="nav-icon">📈</span>
                <span>Reports</span>
            </a>
            <a href="manage_content.php" class="nav-item">
                <span class="nav-icon">📚</span>
                <span>Content</span>
            </a>
            <a href="manage_interview_domains.php" class="nav-item">
                <span class="nav-icon">🎯</span>
                <span>Interview Domains</span>
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="faculty-content">
        <div class="page-header">
            <h1 class="page-title">Job Applied Students</h1>
            <p class="page-subtitle">Track students who have applied for jobs and their application status</p>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?php echo $stats['total_applications'] ?? 0; ?></div>
                        <div class="stat-label">Total Applications</div>
                    </div>
                    <div class="stat-icon">💼</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?php echo $stats['applied'] ?? 0; ?></div>
                        <div class="stat-label">Applied</div>
                    </div>
                    <div class="stat-icon">📝</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?php echo $stats['shortlisted'] ?? 0; ?></div>
                        <div class="stat-label">Shortlisted</div>
                    </div>
                    <div class="stat-icon">⭐</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?php echo $stats['interviewed'] ?? 0; ?></div>
                        <div class="stat-label">Interviewed</div>
                    </div>
                    <div class="stat-icon">🎤</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?php echo $stats['selected'] ?? 0; ?></div>
                        <div class="stat-label">Selected</div>
                    </div>
                    <div class="stat-icon">✅</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?php echo $stats['rejected'] ?? 0; ?></div>
                        <div class="stat-label">Rejected</div>
                    </div>
                    <div class="stat-icon">❌</div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-section">
            <form method="GET" class="filters-grid">
                <div class="filter-group">
                    <label class="filter-label">Search</label>
                    <input type="text" class="filter-input" name="search" value="<?php echo htmlspecialchars($search_term); ?>" placeholder="Student name, company, job title...">
                </div>

                <div class="filter-group">
                    <label class="filter-label">Job Role</label>
                    <select class="filter-select" name="job_role">
                        <option value="">All Roles</option>
                        <?php foreach ($job_roles as $role): ?>
                            <option value="<?php echo htmlspecialchars($role['job_role']); ?>" <?php echo $job_role_filter === $role['job_role'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($role['job_role']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label">Status</label>
                    <select class="filter-select" name="status">
                        <option value="">All Status</option>
                        <option value="Applied" <?php echo $status_filter === 'Applied' ? 'selected' : ''; ?>>Applied</option>
                        <option value="Shortlisted" <?php echo $status_filter === 'Shortlisted' ? 'selected' : ''; ?>>Shortlisted</option>
                        <option value="Interviewed" <?php echo $status_filter === 'Interviewed' ? 'selected' : ''; ?>>Interviewed</option>
                        <option value="Selected" <?php echo $status_filter === 'Selected' ? 'selected' : ''; ?>>Selected</option>
                        <option value="Rejected" <?php echo $status_filter === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label">&nbsp;</label>
                    <button type="submit" class="btn-submit">Filter</button>
                </div>
            </form>
        </div>

        <!-- Applications Table -->
        <div class="applications-section">
            <div class="section-header">
                <h2 class="section-title">Job Applications</h2>
            </div>

            <table class="applications-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Job Details</th>
                        <th>Job Role</th>
                        <th>Match %</th>
                        <th>Status</th>
                        <th>Applied Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($applications)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px; color: #6b7280;">
                                No job applications found. Students need to apply for jobs first.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($applications as $app): ?>
                            <tr>
                                <td>
                                    <div class="student-info">
                                        <div class="student-avatar">
                                            <?php echo strtoupper(substr($app['username'], 0, 1)); ?>
                                        </div>
                                        <div class="student-details">
                                            <div class="student-name"><?php echo htmlspecialchars($app['username']); ?></div>
                                            <div class="student-email"><?php echo htmlspecialchars($app['email']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="job-info">
                                        <div class="job-title"><?php echo htmlspecialchars($app['job_title']); ?></div>
                                        <div class="company-name"><?php echo htmlspecialchars($app['company_name']); ?></div>
                                    </div>
                                </td>
                                <td><strong><?php echo htmlspecialchars($app['job_role']); ?></strong></td>
                                <td>
                                    <span class="match-badge"><?php echo $app['match_percentage']; ?>% Match</span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($app['application_status']); ?>">
                                        <?php echo $app['application_status']; ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($app['applied_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-action btn-view" onclick="viewApplication(<?php echo $app['id']; ?>)">
                                            👁️ View
                                        </button>
                                        <button class="btn-action btn-update" onclick="updateStatus(<?php echo $app['id']; ?>, '<?php echo $app['application_status']; ?>')">
                                            ✏️ Update
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<script>
function viewApplication(appId) {
    alert(`Viewing application details for ID: ${appId}\n\nThis would show:\n- Full application details\n- Student profile\n- Job requirements\n- Application timeline`);
}

function updateStatus(appId, currentStatus) {
    const newStatus = prompt(`Update application status for ID: ${appId}\n\nCurrent status: ${currentStatus}\n\nEnter new status:`, currentStatus);
    if (newStatus && newStatus !== currentStatus) {
        alert(`Status updated from "${currentStatus}" to "${newStatus}" for application ID: ${appId}\n\nThis would update the database and notify the student.`);
    }
}
</script>

<?php include __DIR__ . '/../includes/partials/footer.php'; ?>
