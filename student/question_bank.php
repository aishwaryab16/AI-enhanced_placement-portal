<?php
require_once __DIR__ . '/../includes/config.php';
require_role('student');

// Get quiz parameters from URL
$company_id = isset($_GET['company_id']) ? intval($_GET['company_id']) : 0;
$company_name = isset($_GET['company_name']) ? $_GET['company_name'] : '';
$language = isset($_GET['language']) ? $_GET['language'] : '';
$is_ai_suggested = isset($_GET['ai_suggested']) && $_GET['ai_suggested'] == '1';

if (empty($company_name) || empty($language)) {
    header('Location: resources.php');
    exit;
}

// Fetch company details
$mysqli = $GLOBALS['mysqli'] ?? new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
$company = null;

if ($company_id > 0) {
    $stmt = $mysqli->prepare("SELECT id, company_name, logo_url, industry, location, website, description, contact_person, contact_email, contact_phone, is_active FROM company_resources WHERE id = ? AND is_active = 1");
    $stmt->bind_param('i', $company_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $company = $result->fetch_assoc();

    if (!$company) {
        $stmt = $mysqli->prepare("SELECT id, company_name, logo_url, industry, location, website, description, contact_person, contact_email, contact_phone, is_active FROM companies WHERE id = ? AND is_active = 1");
        $stmt->bind_param('i', $company_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $company = $result->fetch_assoc();
    }

    if (!$company) {
        $stmt = $mysqli->prepare("SELECT id, company_name, NULL AS logo_url, industry, location, website, about_company AS description, NULL AS contact_person, NULL AS contact_email, NULL AS contact_phone, 1 AS is_active FROM company_intelligence WHERE id = ?");
        $stmt->bind_param('i', $company_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $company = $result->fetch_assoc();
    }
}

// If no company found in DB, create a virtual one for AI-suggested companies
if (!$company) {
    $company = [
        'id' => 0,
        'company_name' => $company_name,
        'logo_url' => null,
        'industry' => 'Technology',
        'location' => '',
        'website' => '',
        'description' => '',
        'is_active' => 1
    ];
}

?>

<?php include __DIR__ . '/../includes/partials/header.php'; ?>

<style>
.quiz-page-container {
    max-width: 1000px;
    margin: 0 auto;
    padding: 20px;
    min-height: 100vh;
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
}

.quiz-header {
    background: white;
    padding: 30px;
    border-radius: 15px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    margin-bottom: 30px;
    text-align: center;
}

.quiz-title {
    font-size: 32px;
    font-weight: 700;
    color: #5b1f1f;
    margin-bottom: 10px;
}

.quiz-subtitle {
    font-size: 18px;
    color: #666;
    margin-bottom: 20px;
}

.company-info {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 20px;
    background: #f8f9fa;
    padding: 15px 25px;
    border-radius: 10px;
    margin-bottom: 20px;
}

.company-logo {
    width: 50px;
    height: 50px;
    border-radius: 8px;
    object-fit: contain;
    background: white;
    padding: 5px;
}

.company-details h3 {
    margin: 0;
    font-size: 18px;
    color: #5b1f1f;
}

.company-details p {
    margin: 5px 0 0 0;
    color: #666;
    font-size: 14px;
}

.language-badge {
    display: inline-block;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 8px 20px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 16px;
    margin: 10px 0;
}

.quiz-content {
    background: white;
    padding: 30px;
    border-radius: 15px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}

.quiz-loading {
    text-align: center;
    padding: 60px 20px;
    color: #666;
}

.quiz-loading i {
    font-size: 64px;
    margin-bottom: 20px;
    color: #667eea;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.quiz-info {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.quiz-info-item {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.quiz-info-label {
    font-size: 12px;
    opacity: 0.9;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 5px;
}

.quiz-info-value {
    font-size: 24px;
    font-weight: 700;
}

.quiz-question-section {
    margin-bottom: 30px;
}

.question-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 10px;
}

.question-meta {
    display: flex;
    gap: 10px;
    align-items: center;
}

.difficulty-badge {
    padding: 5px 12px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.difficulty-badge.difficulty-easy {
    background: #e8f5e9;
    color: #2e7d32;
}

.difficulty-badge.difficulty-medium {
    background: #fff3e0;
    color: #ef6c00;
}

.difficulty-badge.difficulty-hard {
    background: #ffebee;
    color: #c62828;
}

.difficulty-badge.difficulty-advanced {
    background: #f3e5f5;
    color: #7b1fa2;
}

.time-estimate {
    color: #666;
    font-size: 14px;
}

.topics-covered {
    color: #666;
    font-size: 14px;
}

.question-text {
    font-size: 20px;
    font-weight: 600;
    color: #333;
    margin-bottom: 25px;
    line-height: 1.6;
}

.quiz-options {
    display: flex;
    flex-direction: column;
    gap: 15px;
    margin-bottom: 25px;
}

.quiz-option {
    display: flex;
    align-items: flex-start;
    padding: 15px 20px;
    border: 2px solid #e0e0e0;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.3s;
    background: #fafafa;
}

.quiz-option:hover {
    border-color: #667eea;
    background: #f0f4ff;
    transform: translateX(5px);
}

.quiz-option input[type="radio"] {
    margin-right: 15px;
    margin-top: 3px;
    cursor: pointer;
}

.quiz-option label {
    cursor: pointer;
    flex: 1;
    color: #333;
    line-height: 1.6;
}

.quiz-option.selected {
    border-color: #667eea;
    background: #e3f2fd;
}

.interview-tip {
    background: #fff9c4;
    border-left: 4px solid #fbc02d;
    padding: 15px;
    border-radius: 5px;
    margin-top: 20px;
    font-size: 14px;
}

.interview-tip i {
    color: #f57f17;
    margin-right: 8px;
}

.quiz-navigation {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 2px solid #f0f0f0;
}

.quiz-btn {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border: none;
    padding: 12px 30px;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 8px;
}

.quiz-btn:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
}

.quiz-btn:disabled {
    background: #ccc;
    cursor: not-allowed;
    transform: none;
}

.quiz-progress {
    font-size: 14px;
    color: #666;
    font-weight: 600;
}

.quiz-results {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 40px;
    border-radius: 15px;
    text-align: center;
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
}

.quiz-score {
    font-size: 48px;
    font-weight: 700;
    margin-bottom: 15px;
}

.quiz-score-text {
    font-size: 24px;
    margin-bottom: 20px;
    opacity: 0.9;
}

.quiz-feedback {
    font-size: 16px;
    line-height: 1.6;
    margin-bottom: 30px;
    opacity: 0.9;
}

.quiz-actions {
    display: flex;
    gap: 15px;
    justify-content: center;
    flex-wrap: wrap;
}

.error-message {
    background: #ffebee;
    border: 2px solid #c62828;
    color: #c62828;
    padding: 20px;
    border-radius: 10px;
    text-align: center;
    margin: 20px 0;
}

.error-message i {
    font-size: 24px;
    margin-bottom: 10px;
}

.back-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #5b1f1f;
    text-decoration: none;
    font-weight: 600;
    margin-bottom: 20px;
    transition: all 0.3s;
}

.back-link:hover {
    color: #8b3a3a;
    transform: translateX(-4px);
}

@media (max-width: 768px) {
    .quiz-info {
        flex-direction: column;
        text-align: center;
    }
    
    .question-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .quiz-navigation {
        flex-direction: column;
        gap: 15px;
    }
    
    .quiz-btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<div class="quiz-page-container">
    <a href="company_details.php?id=<?php echo $company_id; ?><?php echo $is_ai_suggested ? '&ai_suggested=1&name=' . urlencode($company_name) : ''; ?>" class="back-link">
        <i class="fas fa-arrow-left"></i> Back to Company
    </a>

    <div class="quiz-header">
        <h1 class="quiz-title">Practice Questions</h1>
        <div class="language-badge">
            <i class="fas fa-code"></i> <?php echo htmlspecialchars($language); ?>
        </div>
        <p class="quiz-subtitle">Technical interview questions for <?php echo htmlspecialchars($company_name); ?></p>
        
        <div class="company-info">
            <?php if (!empty($company['logo_url'])): ?>
                <img src="<?php echo htmlspecialchars($company['logo_url']); ?>" 
                     alt="<?php echo htmlspecialchars($company['company_name']); ?> logo" 
                     class="company-logo"
                     onerror="this.style.display='none'">
            <?php endif; ?>
            <div class="company-details">
                <h3><?php echo htmlspecialchars($company['company_name']); ?></h3>
                <p><?php echo htmlspecialchars($company['industry'] ?? 'Technology'); ?> • <?php echo htmlspecialchars($language); ?></p>
            </div>
        </div>
    </div>

    <div class="quiz-content" id="quizContent">
        <div class="quiz-loading">
            <i class="fas fa-spinner"></i>
            <h3>Loading Questions...</h3>
            <p>Preparing your practice questions</p>
        </div>
    </div>
</div>

<script>
let currentQuiz = null;
let currentQuestionIndex = 0;
let userAnswers = [];
let timeLeft = 0;
let timerInterval = null;
const TIME_PER_QUESTION = 300; // 5 minutes per question

async function loadQuiz() {
    try {
        const response = await fetch('../api/language_quiz_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                company_id: <?php echo $company_id; ?>,
                company_name: '<?php echo addslashes($company_name); ?>',
                industry: '<?php echo addslashes($company['industry'] ?? ''); ?>',
                language: '<?php echo addslashes($language); ?>',
                ai_suggested: <?php echo $is_ai_suggested ? 'true' : 'false'; ?>
            })
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        console.log('Quiz API Response:', data);
        
        if (data.success) {
            currentQuiz = data.quiz;
            currentQuestionIndex = 0;
            userAnswers = [];
            displayQuestion();
        } else {
            showError('Failed to load quiz: ' + (data.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Quiz loading error:', error);
        showError('Failed to load quiz. Error: ' + error.message);
    }
}

function displayQuestion() {
    if (currentQuestionIndex >= currentQuiz.questions.length) {
        showResults();
        return;
    }

    const question = currentQuiz.questions[currentQuestionIndex];
    const quizContent = document.getElementById('quizContent');
    
    const quizInfoHtml = `
        <div class="quiz-info">
            <div class="quiz-info-item">
                <div class="quiz-info-label">Questions</div>
                <div class="quiz-info-value">${currentQuiz.total_questions}</div>
            </div>
            <div class="quiz-info-item">
                <div class="quiz-info-label">Difficulty</div>
                <div class="quiz-info-value">${currentQuiz.difficulty || 'Mixed'}</div>
            </div>
            <div class="quiz-info-item">
                <div class="quiz-info-label">Estimated Time</div>
                <div class="quiz-info-value">${currentQuiz.estimated_time || '60-100 min'}</div>
            </div>
            <div class="quiz-info-item">
                <div class="quiz-info-label">Progress</div>
                <div class="quiz-info-value">${currentQuestionIndex + 1}/${currentQuiz.total_questions}</div>
            </div>
        </div>
    `;
    
    const questionHtml = `
        <div class="quiz-question-section">
            <div class="question-header">
                <div class="question-meta">
                    ${question.difficulty ? `<span class="difficulty-badge difficulty-${question.difficulty.toLowerCase()}">${question.difficulty}</span>` : ''}
                    ${question.time_estimate ? `<span class="time-estimate">${question.time_estimate}</span>` : ''}
                </div>
                ${question.topics_covered ? `
                    <div class="topics-covered">
                        <strong>Topics:</strong> ${question.topics_covered.join(', ')}
                    </div>
                ` : ''}
            </div>
            
            <div class="question-text">${question.question}</div>
            
            <div class="quiz-options">
                ${question.options.map((option, index) => `
                    <div class="quiz-option" onclick="selectOption(${index})">
                        <input type="radio" name="answer" value="${index}" id="option${index}" ${userAnswers[currentQuestionIndex] === index ? 'checked' : ''}>
                        <label for="option${index}">${option}</label>
                    </div>
                `).join('')}
            </div>
            
            ${question.interview_tip ? `
                <div class="interview-tip">
                    <i class="fas fa-lightbulb"></i>
                    <strong>Interview Tip:</strong> ${question.interview_tip}
                </div>
            ` : ''}
        </div>
        
        <div class="quiz-navigation">
            <button type="button" class="quiz-btn" onclick="previousQuestion()" ${currentQuestionIndex === 0 ? 'disabled' : ''}>
                <i class="fas fa-arrow-left"></i> Previous
            </button>
            <div class="quiz-progress">Question ${currentQuestionIndex + 1} of ${currentQuiz.total_questions}</div>
            <button type="button" class="quiz-btn" onclick="nextQuestion()" id="nextBtn" ${userAnswers[currentQuestionIndex] === undefined ? 'disabled' : ''}>
                ${currentQuestionIndex === currentQuiz.questions.length - 1 ? 'Submit' : 'Next'} <i class="fas fa-arrow-right"></i>
            </button>
        </div>
    `;
    
    quizContent.innerHTML = quizInfoHtml + questionHtml;
}

function selectOption(index) {
    userAnswers[currentQuestionIndex] = index;
    document.querySelectorAll('.quiz-option').forEach((opt, i) => {
        if (i === index) {
            opt.classList.add('selected');
        } else {
            opt.classList.remove('selected');
        }
    });
    document.getElementById('nextBtn').disabled = false;
}

function previousQuestion() {
    if (currentQuestionIndex > 0) {
        currentQuestionIndex--;
        displayQuestion();
    }
}

function nextQuestion() {
    if (userAnswers[currentQuestionIndex] === undefined) {
        alert('Please select an answer before proceeding.');
        return;
    }
    
    if (currentQuestionIndex < currentQuiz.questions.length - 1) {
        currentQuestionIndex++;
        displayQuestion();
    } else {
        showResults();
    }
}

function showResults() {
    let correctAnswers = 0;
    currentQuiz.questions.forEach((question, index) => {
        if (Number(userAnswers[index]) === Number(question.correct_answer)) {
            correctAnswers++;
        }
    });
    
    const score = Math.round((correctAnswers / currentQuiz.questions.length) * 100);
    const quizContent = document.getElementById('quizContent');
    
    let feedback = '';
    if (score >= 90) {
        feedback = 'Excellent! You have a strong understanding of <?php echo htmlspecialchars($language); ?>.';
    } else if (score >= 70) {
        feedback = 'Good work! You have a solid foundation in <?php echo htmlspecialchars($language); ?>.';
    } else if (score >= 50) {
        feedback = 'Not bad! Keep practicing to improve your <?php echo htmlspecialchars($language); ?> skills.';
    } else {
        feedback = 'Keep learning! Review the concepts and try again.';
    }
    
    quizContent.innerHTML = `
        <div class="quiz-results">
            <div class="quiz-score">${score}%</div>
            <div class="quiz-score-text">You got ${correctAnswers} out of ${currentQuiz.questions.length} questions correct</div>
            <div class="quiz-feedback">${feedback}</div>
            <div class="quiz-actions">
                <button class="quiz-btn" onclick="location.reload()">
                    <i class="fas fa-redo"></i> Retry Quiz
                </button>
                <a href="company_details.php?id=<?php echo $company_id; ?><?php echo $is_ai_suggested ? '&ai_suggested=1&name=' . urlencode($company_name) : ''; ?>" class="quiz-btn" style="text-decoration: none; display: inline-flex;">
                    <i class="fas fa-arrow-left"></i> Back to Company
                </a>
            </div>
        </div>
    `;

    // Ensure the results are visible
    quizContent.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function showError(message) {
    const quizContent = document.getElementById('quizContent');
    quizContent.innerHTML = `
        <div class="error-message">
            <i class="fas fa-exclamation-triangle"></i>
            <div><strong>Error:</strong> ${message}</div>
            <a href="company_details.php?id=<?php echo $company_id; ?><?php echo $is_ai_suggested ? '&ai_suggested=1&name=' . urlencode($company_name) : ''; ?>" class="quiz-btn" style="margin-top: 20px; text-decoration: none; display: inline-flex;">
                <i class="fas fa-arrow-left"></i> Back to Company
            </a>
        </div>
    `;
}

// Load quiz when page loads
window.addEventListener('DOMContentLoaded', loadQuiz);
</script>

<?php include __DIR__ . '/../includes/partials/footer.php'; ?>


