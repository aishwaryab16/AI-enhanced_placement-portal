<?php
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['placement_officer', 'admin'], true)) {
    header('Location: ../login.php');
    exit;
}
?>
<?php include __DIR__ . '/../includes/partials/header.php'; ?>

<style>
.control-container { max-width: 900px; margin: 40px auto; padding: 0 16px; }
.page-title { font-size: 32px; font-weight: 700; color: #5b1f1f; margin-bottom: 20px; display:flex; align-items:center; gap:12px; }
.card-grid { display:grid; gap:20px; }
.control-card { background:#fff; border-radius:16px; padding:20px; box-shadow:0 12px 32px rgba(15,23,42,0.08); }
.control-card h3 { margin:0 0 10px 0; font-size:20px; color:#1f2937; }
.control-card p { color:#6b7280; margin-bottom:16px; line-height:1.6; }
.control-actions { display:flex; gap:12px; flex-wrap:wrap; }
.control-actions a { display:inline-flex; align-items:center; gap:8px; padding:10px 16px; border-radius:10px; background:linear-gradient(135deg,#5b1f1f,#8b3a3a); color:#fff; text-decoration:none; font-weight:600; box-shadow:0 8px 20px rgba(91,31,31,0.2); }
.control-actions a.secondary { background:#f1f5f9; color:#1f2937; box-shadow:none; border:1px solid #e2e8f0; }
</style>

<div class="control-container">
    <div class="page-title"><i class="fas fa-user-shield"></i> Admin Control Center</div>
    <div class="card-grid">
        <div class="control-card">
            <h3>User Management</h3>
            <p>Create or remove placement team members and manage access levels.</p>
            <div class="control-actions">
                <a href="../admin_dashboard_enhanced.php"><i class="fas fa-user-cog"></i> Open Admin Dashboard</a>
                <a class="secondary" href="../add_student.php"><i class="fas fa-user-plus"></i> Quick Add Student</a>
            </div>
        </div>
        <div class="control-card">
            <h3>Data Tools</h3>
            <p>Backup and export critical placement data for analytics and archival.</p>
            <div class="control-actions">
                <a class="secondary" href="../export_database.php"><i class="fas fa-database"></i> Export Database</a>
                <a class="secondary" href="../setup_analytics_system.php"><i class="fas fa-chart-pie"></i> Setup Analytics</a>
            </div>
        </div>
        <div class="control-card">
            <h3>Automation & AI</h3>
            <p>Configure AI services that power interviews, resume evaluation and insights.</p>
            <div class="control-actions">
                <a class="secondary" href="../manage_ai.php"><i class="fas fa-robot"></i> Manage AI Integrations</a>
                <a class="secondary" href="../AI_INTERVIEW_SETUP.md" target="_blank"><i class="fas fa-book"></i> Setup Guide</a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/partials/footer.php'; ?>
