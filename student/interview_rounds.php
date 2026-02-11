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
    $current_round_name = $rounds[$current_index] ?? ('Round ' . ($current_index + 1));

    // Append result note to round_results JSON
    $existing_results = [];
    if (!empty($interview['round_results'])) {
        $dec = json_decode($interview['round_results'], true);
        if (is_array($dec)) { $existing_results = $dec; }
    }
    $existing_results[] = [
        'round' => $current_round_name,
        'note' => $result_note,
        'completed_at' => date('Y-m-d H:i:s')
    ];
    $results_json = json_encode($existing_results);

    // If last round completed, set status completed
    $new_status = ($new_index >= $total_rounds) ? 'completed' : 'in_progress';

    // Check if score is provided in POST
    $provided_score = isset($_POST['interview_score']) ? (int)$_POST['interview_score'] : null;
    $calculated_score = 70; // Default score
    
    if ($provided_score !== null) {
        $calculated_score = $provided_score;
    }
    
    // Get total rounds
    $total_rounds_count = count($rounds);
    
    // FIRST: Save attendance record for this round (must be done before calculating overall score)
    // Check if a record exists for this interview and student (without round_name)
    $check_stmt = $mysqli->prepare("SELECT id, completed_rounds FROM interview_attendance WHERE interview_id = ? AND student_id = ? ORDER BY id DESC LIMIT 1");
    if ($check_stmt) {
        $check_stmt->bind_param('ii', $interview_id, $student_id);
        $check_stmt->execute();
        $existing_attendance = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();
        
        // Count existing records for this interview to determine if we should update or insert
        $count_stmt = $mysqli->prepare("SELECT COUNT(*) as count FROM interview_attendance WHERE interview_id = ? AND student_id = ? AND status = 'completed'");
        $count_stmt->bind_param('ii', $interview_id, $student_id);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result()->fetch_assoc();
        $existing_count = (int)$count_result['count'];
        $count_stmt->close();
        
        // Always create a new record for each completed round
        $insert_attendance = $mysqli->prepare("INSERT INTO interview_attendance 
            (interview_id, student_id, company, job_role, started_at, completed_at, score, total_rounds, completed_rounds, round_results, status) 
            VALUES (?, ?, ?, ?, NOW(), NOW(), ?, ?, ?, ?, 'completed')");
        if ($insert_attendance) {
            $new_completed_rounds = $existing_count + 1;
            $insert_attendance->bind_param('iissiis', $interview_id, $student_id, $interview['company'], $interview['job_role'], $calculated_score, $total_rounds_count, $new_completed_rounds, $results_json);
            $insert_attendance->execute();
            $insert_attendance->close();
        }
    }
    
    // SECOND: If all rounds are completed, calculate overall score and feedback from ALL attendance records
    $overall_score = null;
    $overall_feedback = '';
    
    if ($new_status === 'completed') {
        // Calculate overall score from all completed rounds (including the one just saved)
        $avg_score_stmt = $mysqli->prepare("SELECT AVG(score) as avg_score FROM interview_attendance WHERE interview_id = ? AND student_id = ? AND status = 'completed' AND score IS NOT NULL");
        if ($avg_score_stmt) {
            $avg_score_stmt->bind_param('ii', $interview_id, $student_id);
            $avg_score_stmt->execute();
            $avg_result = $avg_score_stmt->get_result()->fetch_assoc();
            $avg_score_stmt->close();
            
            if ($avg_result && $avg_result['avg_score'] !== null) {
                $overall_score = (int)round($avg_result['avg_score']);
                
                // Extract feedback from all round results
                $feedback_parts = [];
                $rounds_feedback = $mysqli->prepare("SELECT round_results, completed_at FROM interview_attendance WHERE interview_id = ? AND student_id = ? AND status = 'completed' ORDER BY completed_at");
                if ($rounds_feedback) {
                    $rounds_feedback->bind_param('ii', $interview_id, $student_id);
                    $rounds_feedback->execute();
                    $rounds_result = $rounds_feedback->get_result();
                    $round_number = 1;
                    while ($round_row = $rounds_result->fetch_assoc()) {
                        $round_data = json_decode($round_row['round_results'], true);
                        if (is_array($round_data) && !empty($round_data)) {
                            foreach ($round_data as $round_item) {
                                if (isset($round_item['note']) && !empty(trim($round_item['note']))) {
                                    // Extract AI feedback if available
                                    $note = trim($round_item['note']);
                                    $round_label = isset($round_item['round']) ? $round_item['round'] : "Round {$round_number}";
                                    if (strpos($note, 'AI Evaluation:') !== false) {
                                        // Extract AI evaluation part
                                        $ai_part = substr($note, strpos($note, 'AI Evaluation:'));
                                        $feedback_parts[] = $round_label . ":\n" . $ai_part;
                                    } else {
                                        $feedback_parts[] = $round_label . ': ' . $note;
                                    }
                                }
                            }
                        }
                        $round_number++;
                    }
                    $rounds_feedback->close();
                }
                
                if (!empty($feedback_parts)) {
                    $overall_feedback = implode("\n\n", $feedback_parts);
                } else {
                    $overall_feedback = "Interview completed successfully. All rounds finished. Overall performance score: {$overall_score}%.";
                }
            } else {
                // Fallback if no scores found
                $overall_feedback = "Interview completed successfully. All rounds finished.";
            }
        }
    }

    // THIRD: Update interviews table with overall score and feedback
    $upd = $mysqli->prepare("UPDATE interviews SET current_round_index = ?, round_results = ?, status = ?, overall_score = ?, overall_feedback = ? WHERE id = ? AND student_id = ?");
    if ($upd) {
        $upd->bind_param('issisi', $new_index, $results_json, $new_status, $overall_score, $overall_feedback, $interview_id, $student_id);
        $upd->execute();
        $upd->close();
    }
    
    header('Location: interview_rounds.php?interview_id=' . $interview_id);
    exit;
}

// Handle restart interview
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restart_interview'])) {
    // Reset to first round - clear results and start fresh
    $empty_results = '[]';
    $upd = $mysqli->prepare("UPDATE interviews SET current_round_index = 0, status = 'in_progress', round_results = ? WHERE id = ? AND student_id = ?");
    if ($upd) {
        $upd->bind_param('sii', $empty_results, $interview_id, $student_id);
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

// Parse round results
$round_results = [];
if (!empty($interview['round_results'])) {
    $dec = json_decode($interview['round_results'], true);
    if (is_array($dec)) { $round_results = $dec; }
}
$is_completed = ($interview['status'] === 'completed');

include __DIR__ . '/../includes/partials/header.php';
?>
<style>
    .wrap { max-width: 1000px; margin: 0 auto; padding: 20px; }
    .header { background: linear-gradient(135deg, #431010 0%, #7a1919 45%, #fcbf49 100%); color:#fff; padding:22px; border-radius:12px; margin-bottom:16px; box-shadow:0 8px 20px rgba(91,31,31,0.25); }
    .header-top { display:flex; flex-wrap:wrap; gap:12px; justify-content:space-between; align-items:center; }
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
            <div class="header-top">
                <h2 style="margin:0;">Company: <?php echo htmlspecialchars($interview['company']); ?> — Role: <?php echo htmlspecialchars($interview['job_role']); ?></h2>
                <a href="interviews.php" class="btn btn-secondary" style="background:rgba(255,255,255,0.2);color:#fff;border:1px solid rgba(255,255,255,0.4);">
                    <i class="fas fa-arrow-left"></i> Back to Companies Interviews
                </a>
            </div>
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
                                        $isHr = (bool)preg_match('/\b(h\.?r\.?|human\s+resource[s]?)\b/', $lowerRound);
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
                                    <?php elseif ($isHr): ?>
                                        <a class="round-link" href="hr_interview_round.php?interview_id=<?php echo (int)$interview_id; ?>&company=<?php echo urlencode($interview['company']); ?>&role=<?php echo urlencode($interview['job_role']); ?>&round=<?php echo urlencode($roundName); ?>">
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

        </div>

        <?php if ($is_completed && !empty($round_results)): ?>
            <div class="card" style="margin-top:20px;">
                <div class="title"><i class="fas fa-clipboard-list"></i> All Rounds Results</div>
                <div style="margin-top:15px;">
                    <?php foreach ($round_results as $idx => $result): ?>
                        <div style="border:1px solid #e5e7eb; border-radius:10px; padding:15px; margin-bottom:12px; background:#f9fafb;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                                <div style="font-weight:700; color:#1f2937; font-size:1.1rem;">
                                    <i class="fas fa-circle" style="color:#10b981; font-size:0.7rem; margin-right:8px;"></i>
                                    <?php echo htmlspecialchars($result['round'] ?? 'Round ' . ($idx + 1)); ?>
                                </div>
                                <?php if (!empty($result['completed_at'])): ?>
                                    <div style="font-size:0.85rem; color:#6b7280;">
                                        <?php echo date('M d, Y H:i', strtotime($result['completed_at'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($result['note'])): ?>
                                <div style="color:#374151; line-height:1.6; padding:10px; background:#fff; border-radius:6px; border-left:3px solid #10b981;">
                                    <?php echo nl2br(htmlspecialchars($result['note'])); ?>
                                </div>
                            <?php else: ?>
                                <div style="color:#9ca3af; font-style:italic;">No notes recorded for this round.</div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php include __DIR__ . '/../includes/partials/footer.php'; ?>


