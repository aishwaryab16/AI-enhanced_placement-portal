<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/internship_backend.php';

$app_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$ok = false;

if ($app_id <= 0) {
    echo '<div style="color:#c44;font-weight:700;padding:20px;">Invalid application.</div>';
    exit;
}

$app = $mysqli->query("SELECT ia.*, u.full_name, u.username, u.usn, u.branch, u.semester, u.cgpa, io.company, io.role FROM internship_applications ia
    LEFT JOIN users u ON ia.student_id = u.id
    LEFT JOIN internship_opportunities io ON io.id = ia.internship_id
    WHERE ia.id = $app_id LIMIT 1")->fetch_assoc();
if (!$app) {
    echo '<div style="color:#c44;font-weight:700;padding:20px;">Application not found.</div>';
    exit;
}

$status_map = [
    'Applied' => 'badge-applied',
    'Shortlisted' => 'badge-shortlisted',
    'Interviewed' => 'badge-interviewed',
    'Selected' => 'badge-selected',
    'Rejected' => 'badge-rejected'
];
$status_badge = $status_map[$app['application_status']] ?? 'badge-applied';
$date_fmt = $app['applied_at'] ? date('M d, Y', strtotime($app['applied_at'])) : '';

$resume_link = !empty($app['resume_path']) ? '../' . ltrim($app['resume_path'], '/') : '';

?>
<style>
.profile-modal-row { display: flex; margin-bottom: 14px; }
.profile-label { min-width:140px; color:#6b7280; font-weight:600; }
.profile-value { color: #232325; font-weight:500; }
.badge-applied { background: #dbeafe; color: #1e40af; border-radius: 8px; font-size:13px; padding:4px 10px; font-weight:700;}
.badge-shortlisted { background: #fef3c7; color: #92400e; border-radius: 8px; font-size:13px; padding:4px 10px; font-weight:700;}
.badge-interviewed { background: #e0e7ff; color: #4338ca; border-radius: 8px; font-size:13px; padding:4px 10px; font-weight:700;}
.badge-selected { background: #d1fae5; color: #047857; border-radius: 8px; font-size:13px; padding:4px 10px; font-weight:700;}
.badge-rejected { background: #fee2e2; color: #b91c1c; border-radius: 8px; font-size:13px; padding:4px 10px; font-weight:700;}
.tracker-actions-row { margin-top: 24px; }
.tracker-actions-row form { display: flex; gap: 12px; align-items: center; }
.tracker-actions-row select, .tracker-actions-row button { font-size:15px; padding:8px; border-radius:7px; border:1px solid #eee; }
.tracker-actions-row button { background: #5b1f1f; color:white; border:none; font-weight:700; cursor:pointer; }
.tracker-actions-row button:hover { background: #89432a; }
</style>
<div style="font-size:1.16rem;font-weight:700;margin-bottom:10px;color:#4a1a1a;">
    <i class="fas fa-user-graduate"></i> <?= htmlspecialchars($app['full_name'] ?? 'Student') ?>
</div>
<div class="profile-modal-row"><span class="profile-label">USN:</span><span class="profile-value"><?= htmlspecialchars($app['usn'] ?? '') ?></span></div>
<div class="profile-modal-row"><span class="profile-label">Branch:</span><span class="profile-value"><?= htmlspecialchars($app['branch'] ?? '') ?></span></div>
<div class="profile-modal-row"><span class="profile-label">Semester:</span><span class="profile-value"><?= htmlspecialchars($app['semester'] ?? '') ?></span></div>
<div class="profile-modal-row"><span class="profile-label">CGPA:</span><span class="profile-value"><?= htmlspecialchars($app['cgpa'] ?? '') ?></span></div>
<hr style="margin:17px 0;">
<div class="profile-modal-row"><span class="profile-label">Company:</span><span class="profile-value"><?= htmlspecialchars($app['company'] ?? '') ?></span></div>
<div class="profile-modal-row"><span class="profile-label">Applied Role:</span><span class="profile-value"><?= htmlspecialchars($app['role'] ??  $app['internship_role'] ?? '' ) ?></span></div>
<div class="profile-modal-row"><span class="profile-label">Status:</span><span class="profile-value"><span class="<?= $status_badge ?>"><?= htmlspecialchars($app['application_status']) ?></span></span></div>
<div class="profile-modal-row"><span class="profile-label">Applied On:</span><span class="profile-value"><?= htmlspecialchars($date_fmt) ?></span></div>
<?php if ($resume_link): ?>
<div class="profile-modal-row"><span class="profile-label">Resume:</span>
    <span class="profile-value"><a href="<?= htmlspecialchars($resume_link) ?>" target="_blank" style="color:#1971cf;font-weight:600;"><i class="fas fa-file-alt"></i> View Resume</a></span>
</div>
<?php endif; ?>
<!-- Status update action form, if desired: -->
<div class="tracker-actions-row">
    <form method="POST" onsubmit="return updateTrackerStatus(event,<?= $app_id ?>);">
        <label for="status-update" style="font-weight:600;color:#763a1a">Update Status:</label>
        <select name="new_status" id="status-update">
            <option<?= $app['application_status']=='Applied'?' selected':'' ?>>Applied</option>
            <option<?= $app['application_status']=='Shortlisted'?' selected':'' ?>>Shortlisted</option>
            <option<?= $app['application_status']=='Interviewed'?' selected':'' ?>>Interviewed</option>
            <option<?= $app['application_status']=='Selected'?' selected':'' ?>>Selected</option>
            <option<?= $app['application_status']=='Rejected'?' selected':'' ?>>Rejected</option>
        </select>
        <button type="submit"><i class="fas fa-sync-alt"></i> Update</button>
        <span id="status-update-result"></span>
    </form>
</div>
<script>
function updateTrackerStatus(e, appId) {
    e.preventDefault();
    var form = e.target;
    var status = form.querySelector('select').value;
    var info = form.querySelector('#status-update-result');
    info.textContent = '';
    fetch('update_intern_application_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id='+encodeURIComponent(appId)+'&status='+encodeURIComponent(status)
    }).then(r=>r.json()).then(d=>{
        if (d.success) {
            info.innerHTML = '<span style="color:#198e54;font-weight:600;">Updated!</span>';
            setTimeout(function(){ location.reload(); }, 888);
        } else {
            info.innerHTML = '<span style="color:#d60a2f;font-weight:600;">'+(d.error||'Failed')+'</span>';
        }
    });
    return false;
}
</script>
