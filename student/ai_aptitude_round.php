<?php
require_once __DIR__ . '/../includes/config.php';
require_role('student');

$student_id = $_SESSION['user_id'];
$interview_id = isset($_GET['interview_id']) ? (int)$_GET['interview_id'] : 0;
$company = $_GET['company'] ?? '';
$role = $_GET['role'] ?? '';
$round = $_GET['round'] ?? 'Aptitude Round';

// Helper to post back to interview_rounds.php after submit
function post_back_form($interview_id, $note) {
    echo '<form id="backForm" method="POST" action="interview_rounds.php?interview_id=' . (int)$interview_id . '">';
    echo '<input type="hidden" name="result_note" value="' . htmlspecialchars($note, ENT_QUOTES) . '">';
    echo '<input type="hidden" name="complete_round" value="1">';
    echo '</form>';
    echo '<script>document.getElementById("backForm").submit();</script>';
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Aptitude Test - <?php echo htmlspecialchars($company); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background:#f5f7fa; margin:0; padding:20px; }
        .wrap { max-width: 900px; margin: 0 auto; }
        .header { background: linear-gradient(135deg,#5b1f1f,#8b3a3a,#ecc35c); color:#fff; padding:22px; border-radius:12px; margin-bottom:16px; }
        .card { background:#fff; border-radius:12px; padding:16px; box-shadow:0 2px 8px rgba(0,0,0,0.08); }
        .question { margin-bottom:16px; border:1px solid #e5e7eb; padding:14px; border-radius:10px; }
        .q-title { font-weight:700; color:#1f2937; margin-bottom:8px; }
        .option { display:flex; align-items:center; gap:8px; padding:8px; border-radius:8px; cursor:pointer; }
        .option:hover { background:#f9fafb; }
        .btn { padding:12px 18px; border:none; border-radius:8px; font-weight:700; cursor:pointer; }
        .btn-primary { background: linear-gradient(135deg, #5b1f1f, #8b3a3a); color:white; }
        .muted { color:#6b7280; font-size:14px; }
        .loading { padding:18px; text-align:center; color:#6b7280; }
        .warning-modal { display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.7); z-index:10000; }
        .warning-modal.show { display:flex; align-items:center; justify-content:center; }
        .warning-content { background:#fff; border-radius:12px; padding:24px; max-width:500px; box-shadow:0 4px 20px rgba(0,0,0,0.3); text-align:center; }
        .warning-content h3 { color:#dc2626; margin-top:0; }
        .warning-content p { color:#374151; margin:12px 0; }
        .tab-switch-count { background:#fee2e2; color:#991b1b; padding:8px 12px; border-radius:6px; display:inline-block; margin-top:8px; font-weight:600; }
        .btn-warning { background:#dc2626; color:white; padding:10px 20px; border:none; border-radius:6px; cursor:pointer; font-weight:600; margin-top:12px; }
        .btn-warning:hover { background:#b91c1c; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="header">
            <h2 style="margin:0;">AI Aptitude Test — <?php echo htmlspecialchars($company); ?> (<?php echo htmlspecialchars($role); ?>)</h2>
            <div><?php echo htmlspecialchars($round); ?></div>
        </div>

        <div class="card">
            <div id="loader" class="loading">Generating company & domain-based aptitude questions...</div>
            <form id="quizForm" style="display:none;">
                <div id="questions"></div>
                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:12px;">
                    <button type="button" class="btn btn-primary" onclick="submitQuiz()"><i class="fas fa-paper-plane"></i> Submit</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tab Switch Warning Modal -->
    <div id="warningModal" class="warning-modal">
        <div class="warning-content">
            <h3><i class="fas fa-exclamation-triangle"></i> Warning: Tab Switch Detected</h3>
            <p>You have switched away from the test window. This action has been recorded.</p>
            <p><strong>Please return to the test tab immediately.</strong></p>
            <div class="tab-switch-count">
                Tab switches: <span id="switchCount">0</span>
            </div>
            <p style="font-size:12px; color:#6b7280; margin-top:12px;">
                Multiple tab switches may result in automatic submission of your test.
            </p>
            <button class="btn-warning" onclick="closeWarning()">I Understand</button>
        </div>
    </div>

    <script>
    const company = <?php echo json_encode($company); ?>;
    const role = <?php echo json_encode($role); ?>;
    const interviewId = <?php echo (int)$interview_id; ?>;

    async function generateQuestions() {
        const prompt = `You are an assessment generator.
Return EXACTLY 30 UNIQUE multiple‑choice aptitude questions tailored for company "${company}" and role/domain "${role}".

Hard rules:
- Questions MUST be varied. Do NOT repeat the same template with only numbers changed. Avoid repeating lead‑ins like "Quick estimate".
- Balance categories (roughly):
  * Quantitative (percentages, ratios, time & work, speed‑time‑distance, profit & loss, mixtures) → 10
  * Logical reasoning (arrangements, deductions, syllogisms, series, coding/decoding, puzzles) → 10
  * Role/domain numeracy for "${role}" at ${company} (metrics, capacity/latency trade‑offs, cost/benefit, data interpretation) → 10
- Difficulty mix: 10 easy, 14 medium, 6 hard. Include a "difficulty" field.
- Exactly 4 options (A–D). Only one correct answer. Include a concise "explanation".
- Heavily ground scenarios in ${company} and the ${role} domain without requiring company trivia.

STRICT OUTPUT (pure JSON; no markdown, no commentary):
{"questions":[{"q":"...","options":["...","...","...","..."],"answer":"<one option text exactly>","explanation":"...","difficulty":"easy|medium|hard","category":"quantitative|logical|role"}, ...]}
Array length MUST be 30.`;
        try {
            const res = await fetch('../api/ai_proxy.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ prompt, target_role: role, target_company: company })
            });
            const data = await res.json();
            if (!data.success) throw new Error(data.error || 'AI error');
            let payload = data.response;
            // Try to extract a JSON object from the response (common when models add text)
            let parsed = null;
            try {
                const jsonMatch = payload.match(/\{[\s\S]*\}/);
                if (jsonMatch) payload = jsonMatch[0];
                parsed = JSON.parse(payload);
            } catch (e1) {
                // Secondary attempt: trim code fences if present
                try {
                    payload = payload.replace(/^```(json)?/i, '').replace(/```$/,'').trim();
                    parsed = JSON.parse(payload);
                } catch (e2) {
                    throw e2;
                }
            }
            const qs = Array.isArray(parsed.questions) ? parsed.questions : [];
            // Enforce exactly 30 by trimming or duplicating easy ones
            if (qs.length > 30) parsed.questions = qs.slice(0,30);
            if (qs.length < 30) {
                const need = 30 - qs.length;
                for (let i=0;i<need;i++) {
                    parsed.questions.push({
                        q: `Estimation: If a system must handle ${1000+i} req/s at p99=100ms, how many concurrent workers are needed? (assume perfect scaling)` ,
                        options:["10","50","100","${(1000+i)/10}"],
                        answer:"100",
                        explanation:"Workers ≈ RPS * latency (s). 1000 * 0.1 = 100.",
                        difficulty:"easy"
                    });
                }
            }
            renderQuiz(parsed.questions || []);
        } catch (e) {
            // Fallback: mixed static bank with company/role interpolation (30 diversified items)
            const bank = [
                {q:`${company} is discounting a subscription by 20% then raising the new price by 25%. Net change?`, options:["No change","+5%","-5%","+2%"], answer:"No change", explanation:"0.8×1.25=1", difficulty:"easy"},
                {q:`A ${company} ${role} team estimates a feature in 12 story‑points. Velocity is 24 points/iteration. What fraction of an iteration is needed?`, options:["1/4","1/2","2/3","3/4"], answer:"1/2", explanation:"12/24", difficulty:"easy"},
                {q:`Mixture: A data stream has 30% errors. After filtering, errors drop to 12%. What fraction of total events were removed if only erroneous events were dropped?`, options:["40%","50%","60%","80%"], answer:"60%", explanation:"x removed s.t. 0.3N−x=0.12(N−x) ⇒ x=0.18N ⇒ 60% of errors", difficulty:"medium"},
                {q:`Arrangement: Three services A,B,C must deploy with B after A and C anywhere. How many orders?`, options:["1","2","3","4"], answer:"3", explanation:"(A,B,C),(A,C,B),(C,A,B)", difficulty:"easy"},
                {q:`Series: 3, 9, 27, ?, 243`, options:["54","81","72","90"], answer:"81", explanation:"×3", difficulty:"easy"},
                {q:`Syllogism: All blueprints are docs. Some docs are specs. Conclusion?`, options:["Some blueprints are specs","No blueprint is spec","Some docs may be blueprints","All specs are blueprints"], answer:"Some docs may be blueprints", explanation:"Possibility from premises", difficulty:"medium"},
                {q:`Time & Work: Dev A completes task in 6 days, Dev B in 8 days. Together?`, options:["3 d","24/7 d","3.5 d","4 d"], answer:"24/7 d", explanation:"1/6+1/8=7/24", difficulty:"easy"},
                {q:`Speed: A job pipeline at ${company} processes 1200 tasks in 30 min. How many per second?`, options:["0.4","0.6","0.8","1"], answer:"0.67", explanation:"1200/1800≈0.67", difficulty:"easy"},
                {q:`Logic grid: If service X must precede Y, and Z cannot follow Y, which valid orders exist?`, options:["X‑Y‑Z","Z‑X‑Y","Y‑X‑Z","Y‑Z‑X"], answer:"Z‑X‑Y", explanation:"X before Y; Z cannot be after Y", difficulty:"medium"},
                {q:`Profit & Loss: Ad budget 40k generates revenue 52k. Margin?`, options:["20%","25%","30%","40%"], answer:"30%", explanation:"(52−40)/40", difficulty:"easy"},
                {q:`Data interpretation: A dashboard shows 15% drop then 10% rise. Net?`, options:["−5%","−4.5%","−6.5%","0%"], answer:"−6.5%", explanation:"0.85×1.10=0.935", difficulty:"easy"},
                {q:`Coding/Decoding: If LOAD→MPBE, then TEAM→?`, options:["UFBN","UGCN","UCBN","UFAN"], answer:"UFBN", explanation:"+1 shift", difficulty:"easy"},
                {q:`Estimation: ${company} expects 3M MAU, average 4 requests/day. Total requests/day?`, options:["3M","8M","10M","12M"], answer:"12M", explanation:"3×4", difficulty:"easy"},
                {q:`Set theory: 60% use App, 50% use Web, 25% both. What % use at least one?`, options:["85%","90%","95%","100%"], answer:"85%", explanation:"A+W−both", difficulty:"easy"},
                {q:`Permutation: In a canary deploy, 5 shards, choose 2 for canary. Ways?`, options:["5","10","20","25"], answer:"10", explanation:"5C2", difficulty:"medium"},
                {q:`Queueing: With λ=5/s and μ=7/s, utilization?`, options:["0.5","0.6","0.7","0.9"], answer:"0.71", explanation:"ρ=λ/μ≈0.71", difficulty:"medium"},
                {q:`Number system: Smallest n such that n%7=3 and n%5=2?`, options:["17","23","38","53"], answer:"17", explanation:"Check candidates", difficulty:"medium"},
                {q:`Puzzle: Two truthful, one liar in a triad. Minimum yes/no questions to find liar?`, options:["1","2","3","4"], answer:"2", explanation:"Pairwise check", difficulty:"hard"},
                {q:`Cost trade‑off: Moving from 2×large to 3×medium instances reduces latency 20% at +10% cost. Benefit/cost index?`, options:["1","1.5","2","2.5"], answer:"2", explanation:"20/10", difficulty:"easy"},
                {q:`Mixture 2: Combine logs with 40% and 10% error rates to get 25% overall. Ratio high:low?`, options:["1:1","2:1","3:2","1:2"], answer:"1:1", explanation:"Average of 40 and 10 is 25 at equal parts", difficulty:"medium"},
                {q:`Scheduling: If a suite has 8 independent tests and 2 runners, min batches?`, options:["2","3","4","5"], answer:"4", explanation:"ceil(8/2)", difficulty:"easy"},
                {q:`Series: 4, 11, 25, 46, ?`, options:["64","70","76","82"], answer:"76", explanation:"+7,+14,+21,+30 (+7n)", difficulty:"medium"},
                {q:`Ratio: Page views split Mobile:Desktop=7:3. Total 200k. Mobile views?`, options:["70k","100k","120k","140k"], answer:"140k", explanation:"7/10 of 200k", difficulty:"easy"},
                {q:`Binary reasoning: If A→B and not B, what about A?`, options:["A true","A false","A undetermined","A and B true"], answer:"A false", explanation:"Modus tollens", difficulty:"easy"},
                {q:`GDPR risk: Probability of incident is 0.02 per month. Over a quarter (3 months), risk at least one incident?`, options:["0.02","0.06","0.059","0.98"], answer:"0.059", explanation:"1−0.98^3", difficulty:"medium"},
                {q:`Path puzzle: From node A to D via B/C with constraints (A→B, A→C, B↛D, C→D). Paths?`, options:["1","2","3","4"], answer:"1", explanation:"Only A→C→D", difficulty:"medium"},
                {q:`Hard estimation: ${company} ingests 2TB/day, compresses 40%. Over 30 days, stored TB?`, options:["24","36","48","60"], answer:"36", explanation:"2×0.6×30", difficulty:"hard"},
                {q:`Time & Work 2: 3 engineers equal speed finish in 4 days. How long for 2 engineers?`, options:["5","6","8","9"], answer:"6", explanation:"Work=12 units; 2 eng → 6 days", difficulty:"easy"},
                {q:`Critical path: Tasks (2,3,1,4) days with deps result in min 8 days. If one 3‑day task is parallelized to 2, new min?`, options:["7","6","5","4"], answer:"7", explanation:"Critical path reduces by 1", difficulty:"hard"},
                {q:`Domain numeracy: For a ${role} at ${company}, CPI improves from 2.5 to 2.0 at same frequency. Perf gain?`, options:["20%","25%","50%","80%"], answer:"25%", explanation:"2.5/2.0−1", difficulty:"medium"}
            ];
            renderQuiz(bank);
        }
    }

    function renderQuiz(questions) {
        const loader = document.getElementById('loader');
        const form = document.getElementById('quizForm');
        const container = document.getElementById('questions');
        loader.style.display = 'none';
        form.style.display = 'block';
        container.innerHTML = '';
        questions.slice(0,30).forEach((q, idx) => {
            const qDiv = document.createElement('div');
            qDiv.className = 'question';
            qDiv.innerHTML = `
                <div class="q-title">Q${idx+1}. ${q.q}</div>
                ${q.options.map((opt,i)=>`
                    <label class='option'>
                        <input type='radio' name='q${idx}' value='${opt}'> ${opt}
                    </label>
                `).join('')}
                <div class='muted'>Explanation (shown after submit)</div>
            `;
            container.appendChild(qDiv);
        });
        // store to window
        window.__questions = questions;
    }

    function submitQuiz() {
        const questions = window.__questions || [];
        let score = 0;
        const total = Math.min(30, questions.length);
        questions.slice(0,30).forEach((q, idx) => {
            const sel = document.querySelector(`input[name="q${idx}"]:checked`);
            if (sel && sel.value.trim() === (q.answer||'').trim()) score++;
        });
        const note = `AI Aptitude Score: ${score}/${total} for ${company} (${role})`;
        // post back by creating a form
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `interview_rounds.php?interview_id=${interviewId}`;
        const f1 = document.createElement('input'); f1.type='hidden'; f1.name='result_note'; f1.value=note; form.appendChild(f1);
        const f2 = document.createElement('input'); f2.type='hidden'; f2.name='complete_round'; f2.value='1'; form.appendChild(f2);
        document.body.appendChild(form);
        form.submit();
    }

    // Tab switch detection and warning
    let tabSwitchCount = 0;
    let warningShown = false;
    let switchDetected = false; // Flag to prevent double counting
    const MAX_TAB_SWITCHES = 5; // Auto-submit after 5 switches

    function showWarning() {
        // Prevent double counting - only count once per switch event
        if (switchDetected) return;
        
        const modal = document.getElementById('warningModal');
        const countSpan = document.getElementById('switchCount');
        
        // Increment count when tab is switched
        tabSwitchCount++;
        countSpan.textContent = tabSwitchCount;
        switchDetected = true; // Mark that we've counted this switch
        
        // Show warning modal
        modal.classList.add('show');
        warningShown = true;

        // Auto-submit if too many switches
        if (tabSwitchCount >= MAX_TAB_SWITCHES) {
            setTimeout(() => {
                alert('Maximum tab switches exceeded. Submitting test automatically...');
                submitQuiz();
            }, 2000);
        }
    }

    function closeWarning() {
        const modal = document.getElementById('warningModal');
        modal.classList.remove('show');
        warningShown = false;
        // Reset switch flag when warning is closed so next switch can be counted
        switchDetected = false;
    }

    // Page Visibility API to detect tab switches (primary method)
    document.addEventListener('visibilitychange', function() {
        // Only track if quiz is loaded (not during question generation)
        const quizForm = document.getElementById('quizForm');
        if (quizForm && quizForm.style.display !== 'none') {
            if (document.hidden) {
                // User switched away from the tab
                showWarning();
            } else {
                // User returned to the tab - reset flag for next switch
                // But don't reset if warning is still showing (they need to acknowledge)
                if (!warningShown) {
                    switchDetected = false;
                }
            }
        }
    });

    // Window blur - only count if visibilitychange didn't already fire
    // This prevents double counting when both events fire for the same action
    window.addEventListener('blur', function() {
        const quizForm = document.getElementById('quizForm');
        if (quizForm && quizForm.style.display !== 'none') {
            // Only count blur if we haven't already counted a visibility change
            // visibilitychange fires first for tab switches, so this prevents double counting
            if (!switchDetected && document.hidden) {
                // Edge case: blur fired but visibilitychange might not have (rare)
                showWarning();
            }
        }
    });

    window.addEventListener('focus', function() {
        // Reset flag when window regains focus (if warning was closed)
        if (!warningShown) {
            switchDetected = false;
        }
    });

    // Prevent context menu (right-click) to discourage cheating
    document.addEventListener('contextmenu', function(e) {
        e.preventDefault();
        return false;
    });

    // Prevent common keyboard shortcuts (Ctrl+W, Ctrl+T, etc.)
    document.addEventListener('keydown', function(e) {
        // Allow normal typing, but block some shortcuts
        if (e.ctrlKey || e.metaKey) {
            // Allow Ctrl+S (save) and Ctrl+Enter (submit) but block others
            if (e.key === 'w' || e.key === 'n' || e.key === 't') {
                e.preventDefault();
                showWarning();
                return false;
            }
        }
        // Block F12 (developer tools)
        if (e.key === 'F12') {
            e.preventDefault();
            showWarning();
            return false;
        }
    });

    generateQuestions();
    </script>
</body>
</html>


