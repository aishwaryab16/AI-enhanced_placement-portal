<?php
require_once __DIR__ . '/../includes/config.php';
require_role('student');

$student_id = $_SESSION['user_id'];
$interview_id = isset($_GET['interview_id']) ? (int)$_GET['interview_id'] : 0;
$company = $_GET['company'] ?? '';
$role = $_GET['role'] ?? '';
$round = $_GET['round'] ?? 'Technical Round';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Technical Coding Round - <?php echo htmlspecialchars($company); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Inter','Segoe UI',Tahoma,Arial,sans-serif; margin:0; background:#f5f7fa; }
        .wrap { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg,#5b1f1f,#8b3a3a,#ecc35c); color:#fff; padding:22px; border-radius:12px; margin-bottom:16px; }
        .split { display:grid; grid-template-columns: 1.1fr 1.4fr; gap:16px; }
        .card { background:#fff; border-radius:12px; padding:16px; box-shadow:0 2px 8px rgba(0,0,0,0.08); }
        .q-title { font-weight:800; font-size:18px; color:#111827; margin:0 0 6px 0; }
        .q-meta  { color:#6b7280; font-size:13px; margin-bottom:10px; }
        .q-body  { color:#374151; line-height:1.6; white-space:pre-wrap; }
        .controls { display:flex; gap:10px; align-items:center; margin-bottom:10px; flex-wrap: wrap; }
        select, button, .btn { padding:10px 12px; border:1px solid #e5e7eb; border-radius:8px; background:#fff; font-weight:600; cursor:pointer; }
        .btn-primary { background: linear-gradient(135deg,#5b1f1f,#8b3a3a); border:none; color:#fff; }
        #editor { width:100%; height:520px; border:1px solid #e5e7eb; border-radius:10px; }
        .io { display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-top:10px; }
        textarea { width:100%; min-height:110px; border:1px solid #e5e7eb; border-radius:8px; padding:10px; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace; font-size:13px; }
        .footer-actions { display:flex; justify-content:flex-end; gap:10px; margin-top:12px; flex-wrap:wrap; }
        .muted { color:#6b7280; font-size:13px; }
        .question-list { display:flex; flex-direction:column; gap:10px; }
        .question-item { border:1px solid #e5e7eb; border-radius:10px; padding:12px; cursor:pointer; }
        .question-item.active { outline:2px solid #ecc35c; background:#fffbeb; }
        .badge { display:inline-block; padding:4px 10px; border-radius:12px; font-size:12px; font-weight:700; margin-right:8px; }
        .result-card { margin-top:16px; border:1px solid #e5e7eb; border-radius:12px; padding:14px; background:#f8fafc; display:none; }
        .result-title { font-weight:700; margin:0 0 8px 0; color:#111827; display:flex; align-items:center; gap:8px; }
        .result-body { color:#1f2937; line-height:1.6; white-space:pre-wrap; }
        .badge-easy { background:#d1fae5; color:#065f46; }
        .badge-medium { background:#fef3c7; color:#92400e; }
        .badge-hard { background:#fee2e2; color:#991b1b; }
    </style>
    <!-- Ace Editor (load without SRI to avoid integrity mismatch issues across mirrors) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.32.3/ace.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.32.3/ext-language_tools.min.js"></script>
</head>
<body>
    <div class="wrap">
        <div class="header">
            <h2 style="margin:0;">Technical Coding Round — <?php echo htmlspecialchars($company); ?> (<?php echo htmlspecialchars($role); ?>)</h2>
            <div><?php echo htmlspecialchars($round); ?></div>
        </div>

        <div class="split">
            <!-- Left: Questions -->
            <div class="card">
                <div class="q-title"><i class="fas fa-code"></i> Coding Questions</div>
                <div class="q-meta muted">Pick one problem; write your solution on the right.</div>
                <div id="questionList" class="question-list"></div>
                <div style="margin-top:10px" class="muted">Tip: Prefer clean, readable code. Add comments only where needed.</div>
            </div>

            <!-- Right: Editor -->
            <div class="card">
                <div class="controls">
                    <div>
                        <label class="muted">Language</label>
                        <select id="language">
                            <option value="python">Python</option>
                            <option value="javascript">JavaScript</option>
                            <option value="java">Java</option>
                            <option value="c_cpp">C/C++</option>
                        </select>
                    </div>
                    <div>
                        <label class="muted">Theme</label>
                        <select id="theme">
                            <option value="github">GitHub</option>
                            <option value="monokai">Monokai</option>
                            <option value="one_dark">One Dark</option>
                            <option value="chrome">Chrome</option>
                        </select>
                    </div>
                    <button id="btnTemplate" class="btn">Insert Template</button>
                </div>
                <div id="editor">// Write your solution here...</div>
                <div class="io">
                    <div>
                        <div class="muted">Custom Input (optional)</div>
                        <textarea id="customInput" placeholder="Input to test locally (not executed on server)"></textarea>
                    </div>
                    <div>
                        <div class="muted">Notes (optional)</div>
                        <textarea id="notes" placeholder="Add any explanation/approach here"></textarea>
                    </div>
                </div>
                <div class="footer-actions">
                    <button id="btnCopy" class="btn"><i class="fas fa-copy"></i> Copy Code</button>
                    <button id="btnSubmit" class="btn-primary"><i class="fas fa-robot"></i> Evaluate Attempt</button>
                    <button id="btnFinish" class="btn-primary" style="background:#2563eb;" disabled><i class="fas fa-flag-checkered"></i> Finish Round</button>
                </div>
                <div class="muted" style="margin-top:8px;">Evaluate as many attempts as you need. When you are satisfied, click <strong>Finish Round</strong> to return.</div>
                <div id="resultCard" class="result-card">
                    <div class="result-title"><i class="fas fa-clipboard-check"></i> Evaluation</div>
                    <div id="resultVerdict" class="result-body"></div>
                    <div id="resultFeedback" class="result-body" style="margin-top:8px;"></div>
                    <details style="margin-top:10px;">
                        <summary style="cursor:pointer; font-weight:600; color:#5b1f1f;">Reference solution</summary>
                        <pre id="resultReference" style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:12px; overflow:auto; margin-top:8px;"></pre>
                    </details>
                    <div id="resultAttempts" class="result-body" style="margin-top:12px; border-top:1px solid #e5e7eb; padding-top:10px; font-size:13px;"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
    const interviewId = <?php echo (int)$interview_id; ?>;
    const company = <?php echo json_encode($company); ?>;
    const role = <?php echo json_encode($role); ?>;

    const QUESTIONS = [
        {
            id: 'twosum',
            title: 'Two Sum',
            difficulty: 'easy',
            body: 'Given an array of integers nums and an integer target, return indices of the two numbers such that they add up to target.\nConstraints: O(n) expected.\nExample: nums=[2,7,11,15], target=9 → [0,1]'
        },
        {
            id: 'anagrams',
            title: 'Group Anagrams',
            difficulty: 'medium',
            body: 'Given an array of strings, group the anagrams together.\nExample: [\"eat\",\"tea\",\"tan\",\"ate\",\"nat\",\"bat\"] → [[\"ate\",\"eat\",\"tea\"],[\"nat\",\"tan\"],[\"bat\"]]'
        },
        {
            id: 'lru',
            title: 'LRU Cache',
            difficulty: 'hard',
            body: 'Design a data structure that follows the constraints of a Least Recently Used (LRU) cache with get(key) and put(key,value) in O(1).'
        },
        {
            id: 'intervals',
            title: 'Merge Intervals',
            difficulty: 'medium',
            body: 'Given an array of intervals where intervals[i] = [starti, endi], merge all overlapping intervals.'
        },
        {
            id: 'bst',
            title: 'Validate BST',
            difficulty: 'medium',
            body: 'Given the root of a binary tree, determine if it is a valid binary search tree (BST).'
        }
    ];

    function badge(d) {
        if (d === 'easy') return '<span class=\"badge badge-easy\">Easy</span>';
        if (d === 'hard') return '<span class=\"badge badge-hard\">Hard</span>';
        return '<span class=\"badge badge-medium\">Medium</span>';
    }

    function renderQuestions() {
        const list = document.getElementById('questionList');
        list.innerHTML = '';
        QUESTIONS.forEach((q, idx) => {
            const item = document.createElement('div');
            item.className = 'question-item' + (idx===0?' active':'');
            item.dataset.id = q.id;
            item.innerHTML = `
                <div style=\"display:flex; justify-content:space-between; align-items:center; gap:10px;\">
                    <div style=\"font-weight:700; color:#111827;\">${q.title}</div>
                    ${badge(q.difficulty)}
                </div>
                <div class=\"q-body\" style=\"margin-top:8px;\">${q.body.replace(/\\n/g,'<br>')}</div>
            `;
            item.onclick = () => {
                document.querySelectorAll('.question-item').forEach(el => el.classList.remove('active'));
                item.classList.add('active');
            };
            list.appendChild(item);
        });
    }
    renderQuestions();

    // Ace setup
    ace.require('ace/ext/language_tools');
    const editor = ace.edit('editor');
    editor.setTheme('ace/theme/github');
    editor.session.setMode('ace/mode/python');
    editor.setOptions({
        fontSize: '13px',
        enableBasicAutocompletion: true,
        enableLiveAutocompletion: true,
        showPrintMargin: false
    });

    const langSelect = document.getElementById('language');
    const themeSelect = document.getElementById('theme');
    langSelect.addEventListener('change', () => {
        const map = { python:'python', javascript:'javascript', java:'java', c_cpp:'c_cpp' };
        editor.session.setMode('ace/mode/' + (map[langSelect.value] || 'python'));
    });
    themeSelect.addEventListener('change', () => {
        const map = { github:'github', monokai:'monokai', one_dark:'one_dark', chrome:'chrome' };
        editor.setTheme('ace/theme/' + (map[themeSelect.value] || 'github'));
    });
    document.getElementById('btnTemplate').addEventListener('click', () => {
        const lang = langSelect.value;
        const templates = {
            python: '#!/usr/bin/env python3\n\"\"\"Solution Template\"\"\"\nfrom typing import *\n\ndef solve():\n    # TODO: write your code\n    pass\n\nif __name__ == \"__main__\":\n    solve()\n',
            javascript: '/** Solution Template */\nfunction solve(){\n  // TODO: write your code\n}\nsolve();\n',
            java: 'import java.util.*; \nclass Main {\n  static void solve(){\n    // TODO: write your code\n  }\n  public static void main(String[] args){ solve(); }\n}\n',
            c_cpp: '#include <bits/stdc++.h>\nusing namespace std;\nvoid solve(){\n  // TODO: write your code\n}\nint main(){ solve(); return 0; }\n'
        };
        editor.setValue(templates[lang] || templates.python, -1);
        editor.focus();
    });

    const btnCopy = document.getElementById('btnCopy');
    const btnSubmit = document.getElementById('btnSubmit');
    const btnFinish = document.getElementById('btnFinish');
    const notesBox = document.getElementById('notes');
    const customInputBox = document.getElementById('customInput');
    const resultCard = document.getElementById('resultCard');
    const resultVerdict = document.getElementById('resultVerdict');
    const resultFeedback = document.getElementById('resultFeedback');
    const resultReference = document.getElementById('resultReference');
    const resultAttempts = document.getElementById('resultAttempts');
    const evaluationLog = [];

    btnCopy.addEventListener('click', async () => {
        const code = editor.getValue();
        try { await navigator.clipboard.writeText(code); alert('Code copied to clipboard.'); } catch(e) { alert('Copy failed.'); }
    });

    function buildPrompt(question, code, lang, customInput) {
        return `You are a senior technical interviewer.\nEvaluate the candidate's solution for the coding problem below.\n\nProblem Title: ${question.title}\nProblem Statement:\n${question.body}\n\nCandidate language: ${lang}\nCandidate code:\n\"\"\"\n${code}\n\"\"\"\n\nCandidate provided optional custom input (may be empty):\n${customInput || 'N/A'}\n\nAnalyse the code for correctness, performance, and edge cases.\nRespond STRICTLY in JSON with keys:\n{\n  \"verdict\": \"Correct\" | \"Incorrect\" | \"Partially Correct\",\n  \"feedback\": \"Clear explanation of the evaluation and any issues\",\n  \"reference_solution\": \"Idiomatic reference implementation in ${lang}\"\n}\nIf the solution is incorrect, explain why and provide a full working reference solution in the specified language.`;
    }

    function renderAttempts() {
        if (!evaluationLog.length) {
            resultAttempts.textContent = '';
            resultAttempts.style.display = 'none';
            return;
        }
        const lines = evaluationLog.map((entry, idx) => `${idx + 1}) ${entry.title} — ${entry.verdict}${entry.language ? ' [' + entry.language + ']' : ''}`);
        resultAttempts.textContent = `Attempts so far:\n${lines.join('\n')}`;
        resultAttempts.style.display = 'block';
    }

    function showResult(question, lang, verdict, feedback, reference) {
        evaluationLog.push({
            title: question.title,
            verdict,
            feedback,
            language: lang,
            timestamp: new Date().toISOString()
        });
        resultVerdict.textContent = `Verdict: ${verdict}`;
        resultFeedback.textContent = feedback || 'No additional feedback provided.';
        resultReference.textContent = reference || 'Reference solution unavailable.';
        renderAttempts();
        resultCard.style.display = 'block';
        btnFinish.disabled = evaluationLog.length === 0;
        resultCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function buildSummary() {
        if (!evaluationLog.length) {
            return 'Technical coding round completed.';
        }
        const attemptsSummary = evaluationLog.map((entry, idx) => `${idx + 1}) ${entry.title} -> ${entry.verdict}`).join(' | ');
        let summary = `[Technical Round] ${attemptsSummary}`;
        const lastFeedback = evaluationLog[evaluationLog.length - 1]?.feedback || '';
        if (lastFeedback) {
            const trimmed = lastFeedback.length > 140 ? lastFeedback.slice(0, 137) + '...' : lastFeedback;
            summary += ` | Last feedback: ${trimmed}`;
        }
        return summary;
    }

    function submitRound(summaryBase) {
        let summary = summaryBase;
        const extra = (notesBox.value || '').trim();
        if (extra) summary += ` | Notes: ${extra}`;
        const MAX_NOTE = 450;
        if (summary.length > MAX_NOTE) summary = summary.slice(0, MAX_NOTE - 3) + '...';
        // Post back to mark round complete with summary note
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `interview_rounds.php?interview_id=${interviewId}`;
        const f1 = document.createElement('input'); f1.type='hidden'; f1.name='result_note'; f1.value=summary; form.appendChild(f1);
        const f2 = document.createElement('input'); f2.type='hidden'; f2.name='complete_round'; f2.value='1'; form.appendChild(f2);
        document.body.appendChild(form);
        form.submit();
    }

    function resetSubmitButton() {
        btnSubmit.disabled = false;
        btnSubmit.innerHTML = '<i class="fas fa-robot"></i> Evaluate & Submit';
    }

    btnSubmit.addEventListener('click', async () => {
        const active = document.querySelector('.question-item.active');
        const qId = active ? active.dataset.id : QUESTIONS[0].id;
        const question = QUESTIONS.find(x => x.id === qId) || QUESTIONS[0];
        const lang = document.getElementById('language').value;
        const code = editor.getValue().trim();
        if (!code) {
            alert('Please write your solution before submitting.');
            return;
        }
        btnSubmit.disabled = true;
        btnSubmit.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Evaluating...';
        resultCard.style.display = 'none';

        const prompt = buildPrompt(question, code, lang, customInputBox.value.trim());
        let verdict = 'Evaluation failed';
        let feedback = 'The evaluator could not produce a response.';
        let reference = '';

        try {
            const res = await fetch('../api/ai_proxy.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ prompt, target_role: role, target_company: company })
            });
            const data = await res.json();
            if (!data.success) throw new Error(data.error || 'AI evaluation error');
            let payload = data.response;
            let parsed = null;
            try {
                const jsonMatch = payload.match(/\{[\s\S]*\}/);
                if (jsonMatch) payload = jsonMatch[0];
                parsed = JSON.parse(payload);
            } catch (err) {
                try {
                    payload = payload.replace(/^```(json)?/i, '').replace(/```$/,'').trim();
                    parsed = JSON.parse(payload);
                } catch (err2) {
                    throw err2;
                }
            }
            verdict = parsed?.verdict || verdict;
            feedback = parsed?.feedback || feedback;
            reference = parsed?.reference_solution || '';
        } catch (error) {
            console.error('Evaluation error', error);
            alert('Unable to evaluate the code automatically. Feedback will be limited but the round will be marked complete.');
        } finally {
            resetSubmitButton();
        }

        showResult(question, lang, verdict, feedback, reference);
    });

    btnFinish.addEventListener('click', () => {
        if (!evaluationLog.length) {
            alert('Please run at least one evaluation before finishing the round.');
            return;
        }
        const summary = buildSummary();
        submitRound(summary);
    });
    </script>
</body>
</html>


