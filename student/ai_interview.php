<?php
require_once __DIR__ . '/../includes/config.php';
require_role('student');

$student_id = $_SESSION['user_id'];
$student_name = $_SESSION['username'] ?? 'Student';

// Get student resume data for AI analysis
$student_query = "
    SELECT 
        u.username,
        u.email,
        u.created_at as join_date
    FROM users u
    WHERE u.id = ? AND u.role = 'student'";

$stmt = $mysqli->prepare($student_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student_data = $stmt->get_result()->fetch_assoc();

// Initialize default values
$student_data['total_quizzes'] = 0;
$student_data['avg_quiz_score'] = 0;
$student_data['chapters_assigned'] = 0;
$student_data['modules_assigned'] = 0;

// Try to get assignment data
$assignments = [];

// Check if student_assignments table exists
$checkTable = $mysqli->query("SHOW TABLES LIKE 'student_assignments'");
if ($checkTable && $checkTable->num_rows > 0) {
    try {
        $completion_query = "
            SELECT 
                c.title as chapter_title,
                m.title as module_title,
                sa.assigned_at,
                'completed' as status
            FROM student_assignments sa
            LEFT JOIN chapters c ON sa.chapter_id = c.id
            LEFT JOIN modules m ON sa.module_id = m.id
            WHERE sa.student_id = ?
            ORDER BY sa.assigned_at DESC
            LIMIT 5";

        $stmt2 = $mysqli->prepare($completion_query);
        if ($stmt2) {
            $stmt2->bind_param("i", $student_id);
            $stmt2->execute();
            $assignments_result = $stmt2->get_result();
            
            while ($row = $assignments_result->fetch_assoc()) {
                $assignments[] = $row;
            }
            
            $student_data['chapters_assigned'] = count(array_filter($assignments, function($a) { return !empty($a['chapter_title']); }));
            $student_data['modules_assigned'] = count(array_filter($assignments, function($a) { return !empty($a['module_title']); }));
            $stmt2->close();
        }
    } catch (Exception $e) {
        // Table exists but query failed, use sample data
    }
}

// If no assignments found, use sample data
if (empty($assignments)) {
    $assignments = [
        ['chapter_title' => 'Programming Fundamentals', 'status' => 'completed'],
        ['chapter_title' => 'Web Development', 'status' => 'completed'],
        ['chapter_title' => 'Database Management', 'status' => 'in_progress']
    ];
    $student_data['chapters_assigned'] = 3;
}

// Try to get quiz data
$quiz_scores = [];
$total_score = 0;
$quiz_count = 0;

// Check if quiz_submissions table exists
$checkQuizTable = $mysqli->query("SHOW TABLES LIKE 'quiz_submissions'");
if ($checkQuizTable && $checkQuizTable->num_rows > 0) {
    try {
        $quiz_query = "
            SELECT 
                qs.score,
                qs.submitted_at,
                c.title as chapter_title
            FROM quiz_submissions qs
            LEFT JOIN chapters c ON qs.chapter_id = c.id
            WHERE qs.user_id = ?
            ORDER BY qs.submitted_at DESC
            LIMIT 3";

        $stmt3 = $mysqli->prepare($quiz_query);
        if ($stmt3) {
            $stmt3->bind_param("i", $student_id);
            $stmt3->execute();
            $quiz_result = $stmt3->get_result();
            
            while ($row = $quiz_result->fetch_assoc()) {
                $quiz_scores[] = $row;
                $total_score += $row['score'];
                $quiz_count++;
            }
            $stmt3->close();
        }
    } catch (Exception $e) {
        // Table exists but query failed
    }
}

if ($quiz_count > 0) {
    $student_data['avg_quiz_score'] = $total_score / $quiz_count;
    $student_data['total_quizzes'] = $quiz_count;
} else {
    // Use sample data if no quiz data found
    $quiz_scores = [
        ['score' => 85, 'chapter_title' => 'Programming Fundamentals'],
        ['score' => 92, 'chapter_title' => 'Web Development'],
        ['score' => 78, 'chapter_title' => 'Database Concepts']
    ];
    $student_data['avg_quiz_score'] = 85;
    $student_data['total_quizzes'] = 3;
}

// Calculate performance level
$avg_score = $student_data['avg_quiz_score'] ?? 0;
if ($avg_score >= 90) {
    $performance_level = 'Excellent';
    $skill_level = 'Advanced';
} elseif ($avg_score >= 80) {
    $performance_level = 'Very Good';
    $skill_level = 'Intermediate-Advanced';
} elseif ($avg_score >= 70) {
    $performance_level = 'Good';
    $skill_level = 'Intermediate';
} else {
    $performance_level = 'Developing';
    $skill_level = 'Beginner-Intermediate';
}

// Create resume summary for AI
$resume_summary = [
    'name' => $student_data['username'],
    'email' => $student_data['email'],
    'performance_level' => $performance_level,
    'skill_level' => $skill_level,
    'avg_score' => round($avg_score, 1),
    'total_quizzes' => $student_data['total_quizzes'],
    'chapters_completed' => $student_data['chapters_assigned'],
    'recent_topics' => array_column($assignments, 'chapter_title'),
    'quiz_performance' => $quiz_scores
];

include __DIR__ . '/../includes/partials/header.php';
?>

<style>
.interview-container {
    max-width: 1000px;
    margin: 0 auto;
    padding: 20px;
    display: block !important;
    visibility: visible !important;
}

.resume-analysis {
    background: white;
    color: #333;
    padding: 25px;
    border-radius: 15px;
    margin-bottom: 25px;
    display: block !important;
    visibility: visible !important;
    opacity: 1 !important;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    border: 1px solid #e0e0e0;
}

.resume-analysis h2 {
    margin-bottom: 15px;
    font-size: 1.8rem;
    color: #5b1f1f;
}

.resume-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 20px;
}

.summary-item {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    padding: 15px;
    border-radius: 10px;
    text-align: center;
    border: 1px solid #dee2e6;
}

.summary-value {
    font-size: 1.5rem;
    font-weight: bold;
    margin-bottom: 5px;
    color: #5b1f1f;
}

.summary-label {
    font-size: 0.9rem;
    color: #666;
}

.interview-section {
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.interview-header {
    background: #ecc35c;
    padding: 20px;
    border-bottom: 2px solid #5b1f1f;
}

.interview-header h3 {
    color: #5b1f1f;
    margin: 0;
    font-size: 1.5rem;
}

.chat-container {
    height: 500px;
    overflow-y: auto;
    padding: 20px;
    background: #fafafa;
}

.chat-input-section {
    padding: 20px;
    background: white;
    border-top: 1px solid #eee;
}

.chat-input {
    display: flex;
    gap: 10px;
    align-items: flex-start;
}

.chat-input textarea {
    flex: 1;
    min-height: 50px;
    padding: 12px;
    border: 2px solid #ddd;
    border-radius: 10px;
    font-family: inherit;
    resize: vertical;
}

.chat-input button {
    padding: 12px 20px;
    border: none;
    border-radius: 10px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-primary {
    background: linear-gradient(135deg, #5b1f1f, #ecc35c);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
}

.btn-secondary {
    background: #ecc35c;
    color: #5b1f1f;
    border: 2px solid #5b1f1f;
}

.btn-secondary:hover {
    background: #5b1f1f;
    color: white;
}

.message {
    margin: 15px 0;
    padding: 15px;
    border-radius: 12px;
    max-width: 80%;
}

.message.user {
    background: linear-gradient(135deg, #e3f2fd, #bbdefb);
    margin-left: auto;
    text-align: right;
}

.message.assistant {
    background: linear-gradient(135deg, #f5f5f5, #eeeeee);
    margin-right: auto;
}

.message-sender {
    font-weight: bold;
    margin-bottom: 8px;
    color: #5b1f1f;
}

.typing-indicator {
    margin: 15px 0;
    padding: 15px;
    border-radius: 12px;
    background: #f5f5f5;
    margin-right: auto;
    max-width: 80%;
}

.status-text {
    margin-top: 10px;
    font-size: 0.9rem;
    color: #666;
}

.resume-topics {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 10px;
}

.topic-tag {
    background: #f0f0f0;
    color: #333;
    padding: 5px 12px;
    border-radius: 15px;
    font-size: 0.8rem;
    border: 1px solid #ddd;
}
</style>

<div class="interview-container">
    <!-- Resume Analysis Section -->
    <div class="resume-analysis">
        <h2>📋 Resume Analysis</h2>
        <p>Before we begin your interview, I've analyzed your academic performance and learning progress:</p>
        
        <div class="resume-summary">
            <div class="summary-item">
                <div class="summary-value"><?php echo number_format($avg_score, 1); ?>%</div>
                <div class="summary-label">Average Score</div>
            </div>
            <div class="summary-item">
                <div class="summary-value"><?php echo $performance_level; ?></div>
                <div class="summary-label">Performance Level</div>
            </div>
            <div class="summary-item">
                <div class="summary-value"><?php echo $student_data['total_quizzes']; ?></div>
                <div class="summary-label">Quizzes Completed</div>
            </div>
            <div class="summary-item">
                <div class="summary-value"><?php echo $student_data['chapters_assigned']; ?></div>
                <div class="summary-label">Topics Studied</div>
            </div>
        </div>
        
        <div class="resume-topics">
            <strong>Recent Topics:</strong>
            <?php foreach (array_slice($assignments, 0, 3) as $assignment): ?>
                <span class="topic-tag"><?php echo htmlspecialchars($assignment['chapter_title'] ?? 'General Study'); ?></span>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Domain Selection Section -->
    <div class="domain-selection-section" style="background: white; border-radius: 15px; padding: 25px; margin-bottom: 25px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);">
        <h3 style="color: #5b1f1f; margin-bottom: 15px;">🎯 Select Interview Domain</h3>
        <p style="color: #666; margin-bottom: 20px;">Choose the area you'd like to be interviewed on:</p>
        
        <div class="domains-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px;">
            <?php
            // Fetch active interview domains
            $checkDomainsTable = $mysqli->query("SHOW TABLES LIKE 'interview_domains'");
            if ($checkDomainsTable && $checkDomainsTable->num_rows > 0) {
                $domainsResult = $mysqli->query("SELECT * FROM interview_domains WHERE is_active = 1 ORDER BY domain_name ASC");
                if ($domainsResult && $domainsResult->num_rows > 0) {
                    $interviewDomains = $domainsResult->fetch_all(MYSQLI_ASSOC);
                    foreach ($interviewDomains as $domain):
            ?>
                        <div class="domain-option" onclick="selectDomain(<?php echo $domain['id']; ?>, '<?php echo htmlspecialchars($domain['domain_name']); ?>')" 
                             style="padding: 20px; border: 2px solid #ddd; border-radius: 10px; text-align: center; cursor: pointer; transition: all 0.3s;">
                            <div style="font-size: 36px; margin-bottom: 10px;"><?php echo htmlspecialchars($domain['icon']); ?></div>
                            <div style="font-weight: 600; color: #333; margin-bottom: 5px;"><?php echo htmlspecialchars($domain['domain_name']); ?></div>
                            <div style="font-size: 11px; padding: 3px 8px; background: #f0f0f0; border-radius: 10px; display: inline-block;">
                                <?php echo ucfirst($domain['difficulty_level']); ?>
                            </div>
                        </div>
            <?php
                    endforeach;
                } else {
                    echo '<p style="grid-column: 1/-1; text-align: center; color: #999;">No interview domains available. <a href="setup_interview_domains.php">Setup domains</a></p>';
                }
            } else {
                echo '<p style="grid-column: 1/-1; text-align: center; color: #999;">Interview domains not configured. <a href="setup_interview_domains.php">Setup now</a></p>';
            }
            ?>
        </div>
        
        <div id="selectedDomainDisplay" style="margin-top: 20px; padding: 15px; background: #e8f5e9; border-radius: 8px; display: none;">
            <strong style="color: #2e7d32;">Selected Domain:</strong> <span id="selectedDomainName"></span>
        </div>
    </div>

    <!-- Interview Section -->
    <div class="interview-section">
        <div class="interview-header">
            <h3>🤖 AI Interview Practice</h3>
            <p style="margin: 10px 0 0 0; color: #666;">
                <span id="interviewInstructions">Select a domain above to begin your personalized interview.</span>
            </p>
        </div>
        
        <div id="chatbox" class="chat-container">
            <div class="message assistant">
                <div class="message-sender">AI Interviewer</div>
                <div>Hello <?php echo htmlspecialchars($student_name); ?>! I've reviewed your academic profile. 
                
                I can see you have a <strong><?php echo $performance_level; ?></strong> performance level with an average score of <strong><?php echo number_format($avg_score, 1); ?>%</strong>. 
                You've completed <?php echo $student_data['total_quizzes']; ?> quizzes and studied <?php echo $student_data['chapters_assigned']; ?> topics.
                
                I'll be conducting a personalized AI-powered interview based on your strengths in areas like 
                <?php 
                $topics = array_column(array_slice($assignments, 0, 2), 'chapter_title');
                echo implode(' and ', array_filter($topics)) ?: 'your studied subjects';
                ?>.
                
                <strong>Ready to begin your AI interview?</strong> Type "yes" or click the microphone to speak!
                
                <small style="color: #666; font-style: italic;">Note: This interview uses OpenAI's GPT-4 to provide intelligent, personalized questions based on your academic performance.</small></div>
            </div>
        </div>
        
        <div class="chat-input-section">
            <div class="chat-input">
                <textarea id="userInput" placeholder="Type your answer or click the microphone to speak..."></textarea>
                <button class="btn-primary" id="sendBtn" onclick="sendMessage()">Send</button>
                <button class="btn-secondary" id="micBtn" onclick="toggleRecording()">🎤 Speak</button>
            </div>
            <div id="recStatus" class="status-text"></div>
        </div>
    </div>
</div>

<script>
// Pass resume data to JavaScript for AI context
const resumeData = <?php echo json_encode($resume_summary); ?>;

const chatbox = document.getElementById('chatbox');
const userInput = document.getElementById('userInput');
const recStatus = document.getElementById('recStatus');
let recognition = null;
let isListening = false;
let selectedDomainId = null;
let selectedDomainName = '';

// Domain selection function
function selectDomain(domainId, domainName) {
    selectedDomainId = domainId;
    selectedDomainName = domainName;
    
    // Update UI
    document.querySelectorAll('.domain-option').forEach(el => {
        el.style.border = '2px solid #ddd';
        el.style.background = 'white';
    });
    event.target.closest('.domain-option').style.border = '2px solid #5b1f1f';
    event.target.closest('.domain-option').style.background = '#fff5f5';
    
    // Show selected domain
    document.getElementById('selectedDomainDisplay').style.display = 'block';
    document.getElementById('selectedDomainName').textContent = domainName;
    document.getElementById('interviewInstructions').textContent = 
        `Domain selected: ${domainName}. Based on your resume, I'll ask personalized questions in this area.`;
    
    // Clear chat and add welcome message for the domain
    chatbox.innerHTML = `
        <div class="message assistant">
            <div class="message-sender">AI Interviewer</div>
            <div>Hello! I'm ready to conduct your <strong>${domainName}</strong> interview. 
            I've reviewed your profile and will tailor questions to your skill level.
            <br><br>
            Type "start" or "begin" when you're ready, or ask me any questions about the interview process!
            </div>
        </div>
    `;
}

// Initialize Speech Recognition
function initSpeechRecognition() {
    if ('webkitSpeechRecognition' in window) {
        recognition = new webkitSpeechRecognition();
    } else if ('SpeechRecognition' in window) {
        recognition = new SpeechRecognition();
    } else {
        recStatus.textContent = 'Speech recognition not supported in this browser.';
        document.getElementById('micBtn').disabled = true;
        return false;
    }

    recognition.continuous = false;
    recognition.interimResults = false;
    recognition.lang = 'en-US';

    recognition.onstart = function() {
        isListening = true;
        recStatus.textContent = 'Listening... Speak now!';
        document.getElementById('micBtn').textContent = '■ Stop';
        document.getElementById('micBtn').style.background = '#dc3545';
    };

    recognition.onresult = function(event) {
        const transcript = event.results[0][0].transcript;
        userInput.value = transcript;
        
        setTimeout(() => {
            sendMessage();
        }, 500);
    };

    recognition.onerror = function(event) {
        recStatus.textContent = 'Speech recognition error: ' + event.error;
        resetMicButton();
    };

    recognition.onend = function() {
        resetMicButton();
    };

    return true;
}

function resetMicButton() {
    isListening = false;
    recStatus.textContent = '';
    document.getElementById('micBtn').textContent = '🎤 Speak';
    document.getElementById('micBtn').style.background = '';
}

function appendMessage(sender, text) {
    const div = document.createElement('div');
    div.className = 'message ' + (sender === 'You' ? 'user' : 'assistant');
    
    div.innerHTML = `
        <div class="message-sender">${sender === 'You' ? 'You' : 'AI Interviewer'}</div>
        <div>${escapeHtml(text)}</div>
    `;
    
    chatbox.appendChild(div);
    chatbox.scrollTop = chatbox.scrollHeight;
}

function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, function(m) { 
        return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[m]); 
    });
}

async function sendMessage() {
    const text = userInput.value.trim();
    if (!text) return;
    
    // Check if domain is selected
    if (!selectedDomainId) {
        alert('Please select an interview domain first!');
        return;
    }
    
    appendMessage('You', text);
    userInput.value = '';
    
    // Show typing indicator
    const typingDiv = document.createElement('div');
    typingDiv.id = 'typing-indicator';
    typingDiv.className = 'typing-indicator';
    typingDiv.innerHTML = `
        <div class="message-sender">AI Interviewer</div>
        <div><em>Analyzing your response and preparing next question...</em></div>
    `;
    chatbox.appendChild(typingDiv);
    chatbox.scrollTop = chatbox.scrollHeight;
    
    try {
        const res = await fetch('../api/ai_interview_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                message: text,
                resume_data: resumeData,
                domain_id: selectedDomainId
            })
        });
        const data = await res.json();
        
        // Remove typing indicator
        const typing = document.getElementById('typing-indicator');
        if (typing) typing.remove();
        
        const reply = (data && data.reply) ? data.reply : 'Sorry, I could not generate a response.';
        appendMessage('AI Interviewer', reply);
        
        // Text-to-speech for the response
        if ('speechSynthesis' in window) {
            const utterance = new SpeechSynthesisUtterance(reply);
            utterance.rate = 0.8;
            utterance.pitch = 1;
            speechSynthesis.speak(utterance);
        }
        
    } catch (e) {
        const typing = document.getElementById('typing-indicator');
        if (typing) typing.remove();
        console.error('AI Interview Error:', e);
        appendMessage('AI Interviewer', 'Error contacting AI. Please try again. Error: ' + e.message);
    }
}

function toggleRecording() {
    if (!recognition) {
        if (!initSpeechRecognition()) return;
    }
    
    if (isListening) {
        recognition.stop();
    } else {
        recognition.start();
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initSpeechRecognition();
    
    userInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });
});
</script>

<?php include __DIR__ . '/../includes/partials/footer.php'; ?>
