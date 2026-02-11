<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/job_backend.php';
require_role('student');

$student_id = $_SESSION['user_id'];
$mysqli = $GLOBALS['mysqli'] ?? new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
setupJobTables($mysqli);

$interview_id = isset($_GET['interview_id']) ? (int)$_GET['interview_id'] : 0;
$message = '';
$messageType = '';

// Fetch interview
$stmt = $mysqli->prepare("SELECT * FROM interviews WHERE id = ? AND student_id = ?");
if (!$stmt) { die('DB error'); }
$stmt->bind_param('ii', $interview_id, $student_id);
$stmt->execute();
$interview = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$interview) { die('Interview not found.'); }

// Parse rounds and progress
$rounds = [];
if (!empty($interview['interview_rounds'])) {
    $decoded = json_decode($interview['interview_rounds'], true);
    if (is_array($decoded)) { $rounds = $decoded; }
}
$current_index = (int)($interview['current_round_index'] ?? 0);
$total_rounds = count($rounds);

// Handle round completion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_round'])) {
    $result_note = trim($_POST['result_note'] ?? '');
    $new_index = min($current_index + 1, max(0, $total_rounds));

    // Append result note to round_results JSON
    $existing_results = [];
    if (!empty($interview['round_results'])) {
        $dec = json_decode($interview['round_results'], true);
        if (is_array($dec)) { $existing_results = $dec; }
    }
    $existing_results[] = [
        'round' => $rounds[$current_index] ?? ('Round ' . ($current_index + 1)),
        'note' => $result_note,
        'completed_at' => date('Y-m-d H:i:s')
    ];
    $results_json = json_encode($existing_results);

    // If last round completed, set status completed
    $new_status = ($new_index >= $total_rounds) ? 'completed' : 'in_progress';

    $upd = $mysqli->prepare("UPDATE interviews SET current_round_index = ?, round_results = ?, status = ? WHERE id = ? AND student_id = ?");
    if ($upd) {
        $upd->bind_param('issii', $new_index, $results_json, $new_status, $interview_id, $student_id);
        $upd->execute();
        $upd->close();
        header('Location: interview_rounds.php?interview_id=' . $interview_id);
        exit;
    }
}

// Refresh interview after possible update
$stmt = $mysqli->prepare("SELECT * FROM interviews WHERE id = ? AND student_id = ?");
$stmt->bind_param('ii', $interview_id, $student_id);
$stmt->execute();
$interview = $stmt->get_result()->fetch_assoc();
$stmt->close();
$current_index = (int)($interview['current_round_index'] ?? 0);

include __DIR__ . '/../includes/partials/header.php';
?>
<style>
    .wrap { max-width: 1000px; margin: 0 auto; padding: 20px; }
    .header { background: linear-gradient(135deg, #431010 0%, #7a1919 45%, #fcbf49 100%); color:#fff; padding:22px; border-radius:12px; margin-bottom:16px; box-shadow:0 8px 20px rgba(91,31,31,0.25); }
    .header h2 { margin:0; color:#fff8dc; text-shadow:0 3px 8px rgba(0,0,0,0.35); letter-spacing:0.5px; }
    .grid { display:grid; grid-template-columns: 2fr 1fr; gap:16px; }
    .card { background:#fff; border-radius:12px; padding:16px; box-shadow:0 2px 8px rgba(0,0,0,0.08); }
    .title { font-weight:700; color:#1f2937; margin-bottom:10px; display:flex; align-items:center; gap:8px; }
    .round-item { display:flex; align-items:center; justify-content:space-between; padding:10px 12px; border:1px solid #e5e7eb; border-radius:10px; margin-bottom:8px; }
    .round-item.locked { opacity:0.6; }
    .badge { padding:4px 10px; border-radius:12px; font-weight:700; font-size:12px; }
    .badge-active { background:#dbeafe; color:#1e40af; }
    .badge-locked { background:#e5e7eb; color:#374151; }
    .badge-done { background:#d1fae5; color:#065f46; }
    .btn { padding:10px 16px; border:none; border-radius:8px; font-weight:700; cursor:pointer; }
    .btn-primary { background: linear-gradient(135deg, #5b1f1f, #8b3a3a); color:white; }
    .btn-secondary { background:#e5e7eb; color:#374151; }
    .actions { display:flex; gap:10px; }
    .note { width:100%; border:2px solid #e5e7eb; border-radius:8px; padding:10px; }
    a.round-link { text-decoration:none; }
</style>
<div class="wrap">
        <div class="header">
            <h2 style="margin:0;">Company: <?php echo htmlspecialchars($interview['company']); ?> — Role: <?php echo htmlspecialchars($interview['job_role']); ?></h2>
            <div><?php echo $total_rounds; ?> Round(s) scheduled — Status: <strong><?php echo htmlspecialchars($interview['status']); ?></strong></div>
        </div>

        <div class="grid">
            <div class="card">
                <div class="title"><i class="fas fa-list-ol"></i> Rounds</div>
                <?php if (empty($rounds)): ?>
                    <p style="color:#6b7280;">No rounds configured for this interview.</p>
                <?php else: ?>
                    <?php foreach ($rounds as $idx => $roundName): 
                        $isCurrent = ($idx === $current_index);
                        $isDone = ($idx < $current_index);
                        $locked = ($idx > $current_index);
                    ?>
                        <div class="round-item <?php echo $locked ? 'locked' : ''; ?>">
                            <div style="display:flex; align-items:center; gap:10px;">
                                <div class="badge <?php echo $isCurrent ? 'badge-active' : ($isDone ? 'badge-done' : 'badge-locked'); ?>">
                                    <?php echo $isCurrent ? 'Current' : ($isDone ? 'Done' : 'Locked'); ?>
                                </div>
                                <div style="font-weight:600; color:#1f2937;">
                                    <?php echo htmlspecialchars($roundName); ?>
                                </div>
                            </div>
                            <?php if ($isCurrent): ?>
                                <div class="actions">
                                    <?php 
                                        // Treat common variants/misspellings as aptitude
                                        $lowerRound = strtolower($roundName);
                                        $isAptitude = (
                                            strpos($lowerRound, 'aptitude') !== false ||
                                            strpos($lowerRound, 'apptitude') !== false ||
                                            strpos($lowerRound, 'apt test') !== false ||
                                            strpos($lowerRound, 'apt-round') !== false
                                        );
                                    ?>
                                    <?php 
                                        $isTechnical = (
                                            strpos($lowerRound, 'technical') !== false ||
                                            strpos($lowerRound, 'coding') !== false ||
                                            strpos($lowerRound, 'programming') !== false ||
                                            strpos($lowerRound, 'code') !== false
                                        );
                                    ?>
                                    <?php if ($isAptitude): ?>
                                        <a class="round-link" href="ai_aptitude_round.php?interview_id=<?php echo (int)$interview_id; ?>&company=<?php echo urlencode($interview['company']); ?>&role=<?php echo urlencode($interview['job_role']); ?>&round=<?php echo urlencode($roundName); ?>">
                                            <button class="btn btn-primary"><i class="fas fa-play"></i> Start Round</button>
                                        </a>
                                    <?php elseif ($isTechnical): ?>
                                        <a class="round-link" href="technical_coding_round.php?interview_id=<?php echo (int)$interview_id; ?>&company=<?php echo urlencode($interview['company']); ?>&role=<?php echo urlencode($interview['job_role']); ?>&round=<?php echo urlencode($roundName); ?>">
                                            <button class="btn btn-primary"><i class="fas fa-play"></i> Start Round</button>
                                        </a>
                                    <?php else: ?>
                                        <a class="round-link" href="ai_interview.php?company=<?php echo urlencode($interview['company']); ?>&role=<?php echo urlencode($interview['job_role']); ?>&round=<?php echo urlencode($roundName); ?>" target="_blank">
                                            <button class="btn btn-primary"><i class="fas fa-play"></i> Start Round</button>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="title"><i class="fas fa-flag-checkered"></i> Complete Current Round</div>
                <?php if ($current_index < $total_rounds): ?>
                    <form method="POST">
                        <textarea class="note" name="result_note" rows="4" placeholder="Notes/Result for this round (optional)"></textarea>
                        <div style="margin-top:10px; display:flex; justify-content:flex-end;">
                            <button type="submit" name="complete_round" class="btn btn-primary"><i class="fas fa-check"></i> Mark Current Round Completed</button>
                        </div>
                    </form>
                <?php else: ?>
                    <p style="color:#065f46; font-weight:600;">All rounds completed.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php include __DIR__ . '/../includes/partials/footer.php'; ?>


