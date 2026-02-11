<?php
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['placement_officer', 'admin'], true)) {
    header('Location: ../login.php');
    exit;
}

$mysqli->query("CREATE TABLE IF NOT EXISTS placement_broadcasts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    audience VARCHAR(100) DEFAULT 'All Students',
    message TEXT NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$status = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $audience = trim($_POST['audience'] ?? 'All Students');
    $message = trim($_POST['message'] ?? '');

    if ($title === '' || $message === '') {
        $status = 'Please provide both a title and a message.';
    } else {
        $stmt = $mysqli->prepare("INSERT INTO placement_broadcasts (title, audience, message, created_by) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            $creator = (int)$_SESSION['user_id'];
            $stmt->bind_param('sssi', $title, $audience, $message, $creator);
            if ($stmt->execute()) {
                $status = 'Announcement sent successfully!';
            } else {
                $status = 'Failed to save announcement: ' . $stmt->error;
            }
            $stmt->close();
        } else {
            $status = 'Unable to prepare announcement statement.';
        }
    }
}

$announcements = [];
$res = $mysqli->query("SELECT pb.*, u.full_name FROM placement_broadcasts pb LEFT JOIN users u ON u.id = pb.created_by ORDER BY pb.created_at DESC LIMIT 20");
if ($res) { $announcements = $res->fetch_all(MYSQLI_ASSOC); }
?>
<?php include __DIR__ . '/../includes/partials/header.php'; ?>

<style>
.hub-container { max-width: 1000px; margin: 40px auto; padding: 0 16px; }
.hub-title { font-size: 32px; color: #5b1f1f; font-weight: 700; margin-bottom: 24px; display:flex; align-items:center; gap:12px; }
.broadcast-card { background: #fff; border-radius: 16px; padding: 24px; box-shadow: 0 12px 32px rgba(91,31,31,0.12); margin-bottom: 24px; }
.broadcast-card h3 { margin: 0 0 12px 0; font-size: 20px; color: #1f2937; }
.broadcast-meta { font-size: 13px; color: #6b7280; margin-bottom: 16px; }
.broadcast-body { color: #374151; line-height: 1.6; white-space: pre-line; }
.form-card { background: #fff; border-radius: 16px; padding: 24px; box-shadow: 0 16px 32px rgba(15,23,42,0.08); margin-bottom: 32px; }
.form-group { margin-bottom: 20px; }
.form-group label { display:block; font-weight:600; color:#374151; margin-bottom:8px; }
.input, textarea, select { width:100%; padding:12px 14px; border:1px solid #d1d5db; border-radius:10px; font-family:inherit; font-size:14px; transition:border-color 0.2s ease; }
.input:focus, textarea:focus, select:focus { outline:none; border-color:#5b1f1f; box-shadow:0 0 0 3px rgba(91,31,31,0.15); }
textarea { min-height: 140px; resize: vertical; }
.submit-btn { background: linear-gradient(135deg, #5b1f1f, #8b3a3a); color:white; border:none; padding:12px 20px; border-radius:10px; font-weight:700; cursor:pointer; box-shadow:0 12px 24px rgba(91,31,31,0.2); }
.submit-btn:hover { transform: translateY(-1px); box-shadow:0 16px 30px rgba(91,31,31,0.28); }
.status { margin-bottom: 20px; padding: 12px 16px; border-radius: 10px; background: #fef3c7; color: #92400e; font-weight: 600; }
.empty-state { text-align:center; padding: 24px; color: #9ca3af; }
</style>

<div class="hub-container">
    <div class="hub-title"><i class="fas fa-bullhorn"></i> Communication Hub</div>

    <?php if ($status !== ''): ?>
        <div class="status"><?php echo htmlspecialchars($status); ?></div>
    <?php endif; ?>

    <div class="form-card">
        <h2 style="margin-top:0; margin-bottom:16px;">Create Announcement</h2>
        <form method="POST">
            <div class="form-group">
                <label for="title">Announcement Title</label>
                <input id="title" name="title" class="input" placeholder="e.g., Infosys Drive - Round 2 Update" required>
            </div>
            <div class="form-group">
                <label for="audience">Audience</label>
                <select id="audience" name="audience">
                    <option value="All Students">All Students</option>
                    <option value="Shortlisted Candidates">Shortlisted Candidates</option>
                    <option value="Panel Members">Panel Members</option>
                    <option value="Placement Team">Placement Team</option>
                </select>
            </div>
            <div class="form-group">
                <label for="message">Message</label>
                <textarea id="message" name="message" placeholder="Write the announcement message..." required></textarea>
            </div>
            <button class="submit-btn" type="submit"><i class="fas fa-paper-plane"></i> Send Announcement</button>
        </form>
    </div>

    <div class="broadcast-card">
        <h3><i class="fas fa-history"></i> Recent Announcements</h3>
        <?php if (!empty($announcements)): ?>
            <?php foreach ($announcements as $row): ?>
                <div style="padding:16px 0; border-bottom:1px solid #f1f5f9;">
                    <div class="broadcast-meta">
                        <strong><?php echo htmlspecialchars($row['title']); ?></strong> &bull; <?php echo htmlspecialchars($row['audience']); ?>
                        <div>
                            Sent by <?php echo htmlspecialchars($row['full_name'] ?: 'System'); ?> on <?php echo date('M d, Y h:i A', strtotime($row['created_at'])); ?>
                        </div>
                    </div>
                    <div class="broadcast-body"><?php echo nl2br(htmlspecialchars($row['message'])); ?></div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">No announcements yet. Use the form above to send your first broadcast.</div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/partials/footer.php'; ?>
