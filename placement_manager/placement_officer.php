<?php
require_once __DIR__ . '/../includes/config.php';

// Check if user is placement officer
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'placement_officer') {
    header('Location: login.php');
    exit;
}

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

// Get statistics (using safe queries with error handling)
$totalCompanies = 0;
$result = $mysqli->query("SELECT COUNT(*) as count FROM company_intelligence");
if ($result) $totalCompanies = $result->fetch_assoc()['count'] ?? 0;

$activeJobs = 0; // No job_postings table exists yet

$totalApplications = 0; // No student_applications table exists yet

$totalPlacements = 0; // No placement_records table exists yet

// Get recent applications (empty for now)
$recentApplications = [];

// Get recent placements (empty for now)
$recentPlacements = [];

// All data initialized above

?>
<?php include __DIR__ . '/../includes/partials/header.php'; ?>

<style>
.placement-dashboard {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

.dashboard-header {
    background: linear-gradient(135deg, #5b1f1f, #ecc35c);
    color: white;
    padding: 30px;
    border-radius: 15px;
    margin-bottom: 30px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

.dashboard-header h1 {
    margin: 0 0 10px 0;
    font-size: 32px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    padding: 25px;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    border-left: 5px solid #5b1f1f;
    transition: transform 0.3s;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
}

.stat-icon {
    font-size: 40px;
    margin-bottom: 10px;
}

.stat-value {
    font-size: 36px;
    font-weight: bold;
    color: #5b1f1f;
    margin: 10px 0;
}

.stat-label {
    color: #666;
    font-size: 14px;
    text-transform: uppercase;
    font-weight: 600;
}

.quick-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.action-card {
    background: white;
    padding: 25px;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    text-decoration: none;
    color: inherit;
    transition: all 0.3s;
    border: 2px solid transparent;
}

.action-card:hover {
    transform: translateY(-5px);
    border-color: #5b1f1f;
    box-shadow: 0 8px 20px rgba(91, 31, 31, 0.2);
}

.action-icon {
    font-size: 48px;
    margin-bottom: 15px;
}

.action-card h3 {
    color: #5b1f1f;
    margin: 0 0 10px 0;
    font-size: 20px;
}

.action-card p {
    color: #666;
    margin: 0;
    font-size: 14px;
}

.recent-section {
    background: white;
    padding: 25px;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    margin-bottom: 30px;
}

.recent-section h2 {
    color: #5b1f1f;
    margin: 0 0 20px 0;
    font-size: 24px;
    border-bottom: 3px solid #ecc35c;
    padding-bottom: 10px;
}

.recent-table {
    width: 100%;
    border-collapse: collapse;
}

.recent-table th {
    background: #5b1f1f;
    color: white;
    padding: 12px;
    text-align: left;
    font-weight: 600;
}

.recent-table td {
    padding: 12px;
    border-bottom: 1px solid #eee;
}

.recent-table tr:hover {
    background: #f9f9f9;
}

.status-badge {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-applied { background: #e3f2fd; color: #1976d2; }
.status-shortlisted { background: #fff3e0; color: #f57c00; }
.status-selected { background: #e8f5e9; color: #388e3c; }
.status-rejected { background: #ffebee; color: #d32f2f; }
.status-placed { background: #e8f5e9; color: #2e7d32; }
</style>

<div class="placement-dashboard">
    <!-- Header -->
    <div class="dashboard-header">
        <h1>🏢 Placement Officer Dashboard</h1>
        <p>Welcome back, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Placement Officer'); ?>! Manage placements, companies, and track student progress.</p>
    </div>

    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">🏢</div>
            <div class="stat-value"><?php echo $totalCompanies; ?></div>
            <div class="stat-label">Active Companies</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">💼</div>
            <div class="stat-value"><?php echo $activeJobs; ?></div>
            <div class="stat-label">Active Job Postings</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">📝</div>
            <div class="stat-value"><?php echo $totalApplications; ?></div>
            <div class="stat-label">Total Applications</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">🎯</div>
            <div class="stat-value"><?php echo $totalPlacements; ?></div>
            <div class="stat-label">Placements This Year</div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
        <a href="manage_companies.php" class="action-card">
            <div class="action-icon">🏢</div>
            <h3>Manage Companies</h3>
            <p>Add, edit, and manage company profiles and contacts</p>
        </a>
        <a href="manage_jobs.php" class="action-card">
            <div class="action-icon">💼</div>
            <h3>Job Postings</h3>
            <p>Create and manage job opportunities for students</p>
        </a>
        <a href="track_applications.php" class="action-card">
            <div class="action-icon">📊</div>
            <h3>Track Applications</h3>
            <p>Monitor student applications and update statuses</p>
        </a>
        <a href="placement_records.php" class="action-card">
            <div class="action-icon">🎓</div>
            <h3>Placement Records</h3>
            <p>Record and track successful student placements</p>
        </a>
        <a href="analytics.php" class="action-card">
            <div class="action-icon">📈</div>
            <h3>Reports & Analytics</h3>
            <p>View placement statistics and generate reports</p>
        </a>
        <a href="student_profiles.php" class="action-card">
            <div class="action-icon">👥</div>
            <h3>Student Profiles</h3>
            <p>View and manage student placement profiles</p>
        </a>
        <a href="recruiter_dashboard.php" class="action-card">
            <div class="action-icon">🎯</div>
            <h3>Recruiter Dashboard</h3>
            <p>Advanced analytics, PRS scores, and student matching</p>
        </a>
    </div>

    <!-- Recent Applications -->
    <div class="recent-section">
        <h2>📝 Recent Applications</h2>
        <?php if (empty($recentApplications)): ?>
            <p style="text-align: center; color: #999; padding: 20px;">No applications yet</p>
        <?php else: ?>
            <table class="recent-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Job Title</th>
                        <th>Company</th>
                        <th>Applied Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentApplications as $app): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($app['full_name'] ?? $app['username']); ?></td>
                            <td><?php echo htmlspecialchars($app['job_title']); ?></td>
                            <td><?php echo htmlspecialchars($app['company_name']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($app['application_date'])); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower(str_replace(' ', '', $app['status'])); ?>">
                                    <?php echo $app['status']; ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Recent Placements -->
    <div class="recent-section">
        <h2>🎯 Recent Placements</h2>
        <?php if (empty($recentPlacements)): ?>
            <p style="text-align: center; color: #999; padding: 20px;">No placements recorded yet</p>
        <?php else: ?>
            <table class="recent-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Company</th>
                        <th>Position</th>
                        <th>Package</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentPlacements as $placement): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($placement['full_name'] ?? $placement['username']); ?></td>
                            <td><?php echo htmlspecialchars($placement['company_name']); ?></td>
                            <td><?php echo htmlspecialchars($placement['position']); ?></td>
                            <td><?php echo htmlspecialchars($placement['package']); ?></td>
                            <td>
                                <span class="status-badge status-placed">
                                    <?php echo $placement['status']; ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/partials/footer.php'; ?>
