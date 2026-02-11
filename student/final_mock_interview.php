<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/job_backend.php';
require_role('student');

$student_id = $_SESSION['user_id'];
$mysqli = $GLOBALS['mysqli'] ?? new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
setupJobTables($mysqli);

$interview_id = isset($_GET['interview_id']) ? (int)$_GET['interview_id'] : 0;
$company = $_GET['company'] ?? '';
$role = $_GET['role'] ?? '';

// If interview_id is provided, fetch canonical company/role from interviews table
if ($interview_id > 0) {
    $stmt = $mysqli->prepare("SELECT company, job_role FROM interviews WHERE id = ? AND student_id = ?");
    if ($stmt) {
        $stmt->bind_param('ii', $interview_id, $student_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            $company = $row['company'];
            $role = $row['job_role'];
        } else {
            die('Interview not found for this student.');
        }
    }
}

// Check if final mock already taken
$has_final_mock = false;
$final_result = null;
if ($interview_id > 0) {
    $stmt = $mysqli->prepare("SELECT score, feedback, strengths, weaknesses, created_at FROM final_mock_interview_results WHERE student_id = ? AND interview_id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('ii', $student_id, $interview_id);
        $stmt->execute();
        $final_result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($final_result) {
            $has_final_mock = true;
        }
    }
}

include __DIR__ . '/../includes/partials/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Final Mock Interview - <?php echo htmlspecialchars($company ?: 'Company'); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            font-family: 'Inter','Segoe UI',Tahoma,Arial,sans-serif;
            background: #f5f7fa;
            margin: 0;
        }
        .wrap {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg,#5b1f1f,#8b3a3a,#ecc35c);
            color:#fff;
            padding:24px;
            border-radius:16px;
            margin-bottom:20px;
            box-shadow:0 10px 30px rgba(91,31,31,0.35);
        }
        .header h2 {
            margin:0 0 6px 0;
        }
        .header p {
            margin:4px 0 0 0;
            opacity:0.9;
        }
        .grid {
            display:grid;
            grid-template-columns: minmax(0, 3fr) minmax(0, 2fr);
            gap:16px;
            align-items:flex-start;
        }
        .card {
            background:#fff;
            border-radius:14px;
            padding:16px;
            box-shadow:0 2px 10px rgba(0,0,0,0.06);
        }
        .ready-card {
            text-align:left;
            padding:20px;
            border-radius:16px;
            background: #0f172a;
            color: #e5e7eb;
            box-shadow:0 16px 40px rgba(15,23,42,0.6);
            margin-bottom:16px;
        }
        .ready-card h3 {
            margin:0 0 10px 0;
            font-size:1.4rem;
        }
        .ready-card p {
            margin:4px 0;
            font-size:0.95rem;
        }
        .btn-primary {
            display:inline-flex;
            align-items:center;
            gap:8px;
            padding:10px 18px;
            border-radius:999px;
            border:none;
            cursor:pointer;
            font-weight:700;
            background:linear-gradient(135deg,#22c55e,#16a34a);
            color:#f9fafb;
            box-shadow:0 8px 20px rgba(34,197,94,0.45);
            margin-top:10px;
        }
        .btn-primary:disabled {
            opacity:0.6;
            cursor:not-allowed;
            box-shadow:none;
        }
        .chat-box {
            border-radius:14px;
            background:#0b1120;
            color:#e5e7eb;
            display:flex;
            flex-direction:column;
            min-height:420px;
            max-height:520px;
            overflow:hidden;
        }
        .chat-header {
            padding:12px 16px;
            border-bottom:1px solid rgba(148,163,184,0.3);
            display:flex;
            align-items:center;
            justify-content:space-between;
            background:linear-gradient(135deg,#0b1120,#111827);
        }
        .chat-header h3 {
            margin:0;
            font-size:0.95rem;
            display:flex;
            align-items:center;
            gap:8px;
        }
        .chat-body {
            flex:1;
            padding:14px 16px;
            overflow-y:auto;
            display:flex;
            flex-direction:column;
            gap:10px;
        }
        .chat-input {
            padding:12px 14px;
            border-top:1px solid rgba(148,163,184,0.3);
            background:#020617;
            display:flex;
            gap:10px;
            align-items:flex-end;
        }
        .chat-input textarea {
            flex:1;
            min-height:40px;
            max-height:100px;
            border-radius:10px;
            border:1px solid rgba(148,163,184,0.5);
            background:#020617;
            color:#e5e7eb;
            padding:8px 10px;
            resize:vertical;
            font-family:inherit;
            font-size:0.9rem;
        }
        .chat-input textarea:focus {
            outline:none;
            border-color:#22c55e;
        }
        .chat-btn {
            border-radius:999px;
            border:none;
            padding:8px 14px;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            background:#22c55e;
            color:#f9fafb;
            cursor:pointer;
        }
        .msg {
            padding:8px 10px;
            border-radius:10px;
            max-width:80%;
            font-size:0.9rem;
            white-space:pre-wrap;
            word-break:break-word;
        }
        .msg.ai {
            align-self:flex-start;
            background:#111827;
        }
        .msg.student {
            align-self:flex-end;
            background:#22c55e;
            color:#0f172a;
        }
        .typing {
            font-size:0.85rem;
            color:#9ca3af;
        }
        .score-badge {
            display:inline-flex;
            align-items:center;
            gap:6px;
            padding:4px 10px;
            border-radius:999px;
            background:#dbf4ff;
            color:#0f172a;
            font-size:0.8rem;
            font-weight:600;
        }
        @media (max-width: 900px) {
            .grid {
                grid-template-columns: minmax(0,1fr);
            }
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="header">
        <h2>Final Mock Interview - <?php echo htmlspecialchars($company ?: 'Company'); ?></h2>
        <p>Role: <strong><?php echo htmlspecialchars($role ?: 'Role'); ?></strong></p>
        <p style="margin-top:6px;font-size:0.9rem;">This is a <strong>one-time</strong> full mock HR-style interview. Your performance will be scored and stored for your placement officer.</p>
    </div>

    <?php if ($has_final_mock && $final_result): ?>
        <div class="card" style="border-left:4px solid #16a34a;">
            <h3 style="margin-top:0;display:flex;align-items:center;gap:8px;"><i class="fas fa-check-circle" style="color:#16a34a;"></i> Final Mock Completed</h3>
            <p>You have already taken the final mock interview for this company/role.</p>
            <?php if ($final_result['score'] !== null): ?>
                <p>
                    <span class="score-badge">
                        <i class="fas fa-star"></i> Score: <?php echo (int)$final_result['score']; ?> / 100
                    </span>
                </p>
            <?php endif; ?>
            <?php if (!empty($final_result['feedback'])): ?>
                <p style="margin-top:10px;"><strong>AI Feedback:</strong><br><?php echo nl2br(htmlspecialchars($final_result['feedback'])); ?></p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="ready-card">
            <h3><i class="fas fa-hourglass-start"></i> Are you ready for your final mock?</h3>
            <p>This mock simulates your actual HR discussion. You will get only <strong>one attempt</strong>, so ensure you're in a quiet place with a stable connection.</p>
            <p>After you finish, the system will:<br>- Analyze your conversation<br>- Generate a score out of 100<br>- Store the result separately for the placement team.</p>
            <button id="btnStartMock" class="btn-primary">
                <i class="fas fa-play-circle"></i> Yes, I'm ready
            </button>
        </div>

        <div class="grid">
            <div class="chat-box" id="chatBox" style="opacity:0.4;pointer-events:none;">
                <div class="chat-header">
                    <h3><i class="fas fa-user-tie"></i> AI HR Interviewer</h3>
                    <span style="font-size:0.8rem;color:#9ca3af;">Final mock session</span>
                </div>
                <div class="chat-body" id="chatBody">
                    <div class="msg ai">
                        Hi, when you click <strong>"Yes, I'm ready"</strong>, your final mock interview will begin. 
                        Please answer honestly and in complete sentences, as you would in a real company interview.
                    </div>
                </div>
                <div class="chat-input">
                    <textarea id="userInput" placeholder="Click 'Yes, I'm ready' above to begin..." disabled></textarea>
                    <button id="btnSend" class="chat-btn" disabled><i class="fas fa-paper-plane"></i></button>
                </div>
            </div>

            <div class="card">
                <h3 style="margin-top:0;display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-clipboard-list"></i> Mock Summary & Finish
                </h3>
                <p style="font-size:0.9rem;color:#4b5563;">Once you've answered enough questions, click <strong>Finish & Analyze</strong>. The AI will evaluate your overall performance.</p>
                <textarea id="notes" placeholder="Optional: jot down any reflections or points you want the AI to consider in its evaluation..." style="width:100%;min-height:120px;margin-top:10px;border-radius:10px;border:1px solid #e5e7eb;padding:10px;font-family:inherit;"></textarea>
                <button id="btnFinish" class="btn-primary" style="width:100%;justify-content:center;margin-top:12px;" disabled>
                    <i class="fas fa-flag-checkered"></i> Finish & Analyze
                </button>
                <p id="status" style="margin-top:8px;font-size:0.85rem;color:#6b7280;"></p>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
<?php if (!$has_final_mock): ?>
const company = <?php echo json_encode($company); ?>;
const role = <?php echo json_encode($role); ?>;
const interviewId = <?php echo (int)$interview_id; ?>;

const btnStartMock = document.getElementById('btnStartMock');
const chatBox = document.getElementById('chatBox');
const chatBody = document.getElementById('chatBody');
const userInput = document.getElementById('userInput');
const btnSend = document.getElementById('btnSend');
const btnFinish = document.getElementById('btnFinish');
const notes = document.getElementById('notes');
const statusEl = document.getElementById('status');

let conversation = [];
let isWaiting = false;
let started = false;

function addMessage(sender, text) {
    const div = document.createElement('div');
    div.className = 'msg ' + (sender === 'ai' ? 'ai' : 'student');
    div.textContent = text;
    chatBody.appendChild(div);
    chatBody.scrollTop = chatBody.scrollHeight;
    conversation.push({ sender: sender, text: text, ts: new Date().toISOString() });
    if (sender === 'student') {
        updateFinishState();
    }
}

function updateFinishState() {
    const studentTurns = conversation.filter(m => m.sender === 'student').length;
    btnFinish.disabled = studentTurns < 3;
}

async function fetchAIReply(latestText, isFirst) {
    if (isWaiting) return;
    isWaiting = true;
    statusEl.textContent = 'AI is thinking...';

    const history = conversation.map(m => (m.sender === 'ai' ? 'Interviewer: ' : 'Candidate: ') + m.text).join('\n');
    const basePrompt = `You are a senior HR interviewer at ${company || 'the company'}, conducting a final mock interview for the ${role || 'open'} role.

Guidelines:
- Keep questions focused on role fit, domain understanding, past projects, and alignment with ${company || 'the company'}.
- Be encouraging, but maintain professional tone.
- Responses should be 2–3 sentences and always end with a clear follow-up question.
- Do not ask trivia about the company's history; focus on skills, mindset, and how the candidate would work at ${company || 'the company'}.
`;

    const directive = isFirst
        ? 'Greet the candidate, introduce yourself as HR from the company, and ask the first question about their background and why they are interested in this role.'
        : `The candidate just said: "${latestText}". Briefly acknowledge, then ask a follow-up that digs deeper into their suitability for the ${role || 'role'} at ${company || 'the company'}.`;

    const prompt = basePrompt + '\nConversation so far:\n' + (history || 'No previous messages.') + '\n\n' + directive;

    try {
        const res = await fetch('../api/ai_proxy.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ prompt, target_role: role, target_company: company })
        });
        const data = await res.json();
        if (!data.success) throw new Error(data.error || 'AI error');
        const reply = (data.response || '').trim();
        addMessage('ai', reply);
    } catch (e) {
        console.error(e);
        addMessage('ai', 'Sorry, I am having trouble generating the next question. Please try again in a moment.');
    } finally {
        isWaiting = false;
        statusEl.textContent = '';
    }
}

btnStartMock.addEventListener('click', () => {
    started = true;
    btnStartMock.disabled = true;
    chatBox.style.opacity = 1;
    chatBox.style.pointerEvents = 'auto';
    userInput.disabled = false;
    userInput.placeholder = 'Type your first answer here...';
    btnSend.disabled = false;
    statusEl.textContent = 'Mock started. Answer as you would in a real interview.';
    fetchAIReply('', true);
});

btnSend.addEventListener('click', () => {
    const text = (userInput.value || '').trim();
    if (!text || !started) return;
    addMessage('student', text);
    userInput.value = '';
    fetchAIReply(text, false);
});

userInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        btnSend.click();
    }
});

btnFinish.addEventListener('click', async () => {
    const studentTurns = conversation.filter(m => m.sender === 'student').length;
    if (studentTurns === 0) {
        alert('Please answer at least one question before finishing.');
        return;
    }
    btnFinish.disabled = true;
    btnSend.disabled = true;
    userInput.disabled = true;
    statusEl.textContent = 'Analyzing your performance...';

    try {
        const scoreRes = await fetch('../api/analyze_interview_score.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                conversation: conversation,
                company: company,
                job_role: role,
                round_name: 'Final Mock Interview'
            })
        });
        const scoreData = await scoreRes.json();
        if (!scoreData.success) {
            throw new Error(scoreData.error || 'Scoring failed');
        }

        // Save in final mock table
        const saveRes = await fetch('../api/save_final_mock_result.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                interview_id: interviewId,
                company: company,
                job_role: role,
                score: scoreData.score,
                feedback: scoreData.feedback || '',
                strengths: scoreData.strengths || [],
                weaknesses: scoreData.weaknesses || [],
                conversation: conversation
            })
        });
        const saveData = await saveRes.json();
        if (!saveData.success) {
            throw new Error(saveData.error || 'Could not save result');
        }

        statusEl.textContent = 'Final mock completed and saved. Score: ' + scoreData.score + '/100';
        alert('Your final mock result has been saved with score ' + scoreData.score + '/100.');
        if (interviewId > 0) {
            window.location.href = 'interview_rounds.php?interview_id=' + interviewId;
        }
    } catch (e) {
        console.error(e);
        alert('Error while analyzing or saving the mock result: ' + e.message);
        statusEl.textContent = 'Something went wrong. Please try again.';
        btnFinish.disabled = false;
        btnSend.disabled = false;
        userInput.disabled = false;
    }
});
<?php endif; ?>
</script>

<?php include __DIR__ . '/../includes/partials/footer.php'; ?>


