<?php
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['placement_officer', 'admin'], true)) {
    header('Location: ../login.php');
    exit;
}

$insights = [
    'applications_per_student' => 0,
    'shortlist_rate' => 0,
    'placement_rate' => 0,
    'active_companies' => 0,
];

$totalStudents = 0;
$res = $mysqli->query("SELECT COUNT(*) AS total FROM users WHERE role = 'student'");
if ($res) { $totalStudents = (int)($res->fetch_assoc()['total'] ?? 0); }

$checkApps = $mysqli->query("SHOW TABLES LIKE 'job_applications'");
if ($checkApps && $checkApps->num_rows > 0) {
    $res = $mysqli->query("SELECT COUNT(*) AS total FROM job_applications");
    $totalApps = $res ? (int)($res->fetch_assoc()['total'] ?? 0) : 0;
    if ($totalStudents > 0) {
        $insights['applications_per_student'] = round($totalApps / max($totalStudents, 1), 2);
    }
    $res = $mysqli->query("SELECT COUNT(*) AS total FROM job_applications WHERE application_status = 'Shortlisted'");
    $shortlisted = $res ? (int)($res->fetch_assoc()['total'] ?? 0) : 0;
    $insights['shortlist_rate'] = $totalApps > 0 ? round(($shortlisted / $totalApps) * 100, 1) : 0;
}

$checkPlaced = $mysqli->query("SHOW COLUMNS FROM users LIKE 'is_placed'");
if ($checkPlaced && $checkPlaced->num_rows > 0 && $totalStudents > 0) {
    $res = $mysqli->query("SELECT COUNT(*) AS total FROM users WHERE role = 'student' AND is_placed = 1");
    $placed = $res ? (int)($res->fetch_assoc()['total'] ?? 0) : 0;
    $insights['placement_rate'] = round(($placed / max($totalStudents, 1)) * 100, 1);
}

$checkCompanies = $mysqli->query("SHOW TABLES LIKE 'companies'");
if ($checkCompanies && $checkCompanies->num_rows > 0) {
    $res = $mysqli->query("SELECT COUNT(*) AS total FROM companies WHERE status = 'active' OR status IS NULL");
    if ($res) { $insights['active_companies'] = (int)($res->fetch_assoc()['total'] ?? 0); }
}

$recommendations = [];
if ($insights['shortlist_rate'] < 25) {
    $recommendations[] = 'Shortlist rate is below 25%. Review eligibility criteria or provide targeted interview preparation to students.';
}
if ($insights['placement_rate'] < 10) {
    $recommendations[] = 'Overall placement rate is low. Engage alumni mentors and conduct mock interviews to boost conversions.';
}
if ($insights['applications_per_student'] < 2) {
    $recommendations[] = 'Students are applying to fewer roles on average. Encourage them to explore more opportunities and tailor their resumes.';
}
if ($insights['active_companies'] < 5) {
    $recommendations[] = 'Increase active company outreach to widen opportunities. Coordinate with the corporate relations team.';
}
if (empty($recommendations)) {
    $recommendations[] = 'Great job! Key performance indicators look healthy. Maintain consistent communication with recruiters.';
}
?>
<?php include __DIR__ . '/../includes/partials/header.php'; ?>

<style>
.insights-container { max-width: 900px; margin: 40px auto; padding: 0 16px; }
.page-title { font-size: 32px; color:#5b1f1f; font-weight:700; margin-bottom:24px; display:flex; align-items:center; gap:12px; }
.metric-list { display:grid; gap:16px; margin-bottom:30px; }
.metric-item { background:#fff; border-radius:16px; padding:20px; box-shadow:0 10px 28px rgba(15,23,42,0.08); display:flex; justify-content:space-between; align-items:center; }
.metric-label { color:#64748b; font-size:13px; text-transform:uppercase; letter-spacing:0.8px; }
.metric-value { font-size:28px; color:#1f2937; font-weight:700; }
.recommendations { background:#fff; border-radius:16px; padding:24px; box-shadow:0 12px 32px rgba(91,31,31,0.12); }
.recommendations h3 { margin-top:0; color:#1f2937; }
.recommendations ul { margin:16px 0 0 20px; color:#374151; line-height:1.7; }
</style>

<div class="insights-container">
    <div class="page-title"><i class="fas fa-brain"></i> AI Insights</div>

    <div class="metric-list">
        <div class="metric-item">
            <div class="metric-label">Applications per Student</div>
            <div class="metric-value"><?php echo number_format($insights['applications_per_student'], 2); ?></div>
        </div>
        <div class="metric-item">
            <div class="metric-label">Shortlist Rate</div>
            <div class="metric-value"><?php echo number_format($insights['shortlist_rate'], 1); ?>%</div>
        </div>
        <div class="metric-item">
            <div class="metric-label">Placement Rate</div>
            <div class="metric-value"><?php echo number_format($insights['placement_rate'], 1); ?>%</div>
        </div>
        <div class="metric-item">
            <div class="metric-label">Active Companies</div>
            <div class="metric-value"><?php echo number_format($insights['active_companies']); ?></div>
        </div>
    </div>

    <div class="recommendations">
        <h3><i class="fas fa-lightbulb"></i> Recommended Actions</h3>
        <ul>
            <?php foreach ($recommendations as $rec): ?>
                <li><?php echo htmlspecialchars($rec); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<?php include __DIR__ . '/../includes/partials/footer.php'; ?>
