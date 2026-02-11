<?php
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['placement_officer', 'admin'], true)) {
    header('Location: ../login.php');
    exit;
}

$questions = [];
$hasTable = false;

$check = $mysqli->query("SHOW TABLES LIKE 'company_questions'");
if ($check && $check->num_rows > 0) {
    $hasTable = true;
    $sql = "SELECT company_name, question_text, question_type, difficulty_level, created_at FROM company_questions ORDER BY created_at DESC LIMIT 100";
    $res = $mysqli->query($sql);
    if ($res) { $questions = $res->fetch_all(MYSQLI_ASSOC); }
}
?>
<?php include __DIR__ . '/../includes/partials/header.php'; ?>

<style>
.questions-container { max-width: 1100px; margin: 40px auto; padding: 0 16px; }
.page-title { font-size: 32px; color: #5b1f1f; font-weight: 700; margin-bottom: 24px; display:flex; align-items:center; gap:12px; }
.question-card { background:#fff; border-radius:16px; padding:20px; box-shadow:0 12px 32px rgba(15,23,42,0.08); margin-bottom:20px; }
.question-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; }
.company { font-weight:700; color:#1f2937; font-size:18px; }
.meta { display:flex; gap:10px; font-size:12px; text-transform:uppercase; letter-spacing:0.8px; color:#64748b; }
.question-text { color:#374151; line-height:1.6; white-space:pre-line; }
.empty-state { padding:28px; text-align:center; color:#94a3b8; font-size:14px; background:#fff; border-radius:16px; box-shadow:0 12px 32px rgba(15,23,42,0.08); }
.add-note { margin-bottom:20px; padding:16px; border-radius:12px; background:#fef3c7; color:#92400e; font-size:14px; }
</style>

<div class="questions-container">
    <div class="page-title"><i class="fas fa-question-circle"></i> Company Questions Bank</div>
    <div class="add-note">
        Upload AI-generated or manually curated interview questions from the placement team. Use the admin tools or API integration to populate this repository for students.
    </div>

    <?php if ($hasTable && !empty($questions)): ?>
        <?php foreach ($questions as $row): ?>
            <div class="question-card">
                <div class="question-header">
                    <div class="company"><?php echo htmlspecialchars($row['company_name'] ?: 'Company'); ?></div>
                    <div class="meta">
                        <span><?php echo htmlspecialchars(ucfirst($row['question_type'] ?: 'General')); ?></span>
                        <span><?php echo htmlspecialchars(ucfirst($row['difficulty_level'] ?: 'Medium')); ?></span>
                        <span><?php echo $row['created_at'] ? date('M d, Y', strtotime($row['created_at'])) : 'N/A'; ?></span>
                    </div>
                </div>
                <div class="question-text"><?php echo nl2br(htmlspecialchars($row['question_text'] ?: 'Question description unavailable.')); ?></div>
            </div>
        <?php endforeach; ?>
    <?php elseif ($hasTable): ?>
        <div class="empty-state">No questions have been added yet. Use the admin panel or scripts to populate company-specific interview questions.</div>
    <?php else: ?>
        <div class="empty-state">The company questions table has not been created yet. Run the setup scripts to enable this module.</div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/partials/footer.php'; ?>
