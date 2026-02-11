<?php
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['placement_officer', 'admin'], true)) {
    header('Location: ../login.php');
    exit;
}
?>
<?php include __DIR__ . '/../includes/partials/header.php'; ?>

<div style="max-width: 960px; margin: 60px auto; padding: 24px; background: #fff; border-radius: 16px; box-shadow: 0 12px 32px rgba(15,23,42,0.08);">
    <h1 style="margin-bottom: 12px; color: #5b1f1f;">Recruiter Dashboard</h1>
    <p style="color: #6b7280; line-height: 1.7;">
        Advanced recruiter analytics will appear here. Planned features include PRS-based candidate recommendations,
        recruiter feedback loops, and AI-generated outreach suggestions. Stay tuned while we connect this page with
        the analytics services.
    </p>
</div>

<?php include __DIR__ . '/../includes/partials/footer.php'; ?>
