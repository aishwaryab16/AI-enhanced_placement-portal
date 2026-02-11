<?php
require_once __DIR__ . '/../config.php';
require_role('student');

$student_id = $_SESSION['user_id'];
$student_name = $_SESSION['username'] ?? 'Student';

// Fetch student profile
$stmt = $mysqli->prepare('SELECT * FROM users WHERE id = ?');
$stmt->bind_param('i', $student_id);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

include __DIR__ . '/partials/header.php';
?>

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

.analyzer-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 30px;
    background: #f5f7fa;
    min-height: 100vh;
}

.analyzer-header {
    background: linear-gradient(135deg, #5b1f1f, #ecc35c);
    color: white;
    padding: 40px;
    border-radius: 20px;
    text-align: center;
    margin-bottom: 40px;
    box-shadow: 0 10px 30px rgba(91, 31, 31, 0.3);
}

.analyzer-header h1 {
    font-size: 2.5rem;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 15px;
}

.analyzer-header p {
    font-size: 1.1rem;
    opacity: 0.95;
}

.main-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    margin-bottom: 30px;
}

.upload-section {
    background: white;
    border-radius: 20px;
    padding: 40px;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
}

.section-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.upload-area {
    border: 3px dashed #d1d5db;
    border-radius: 16px;
    padding: 60px 30px;
    text-align: center;
    background: linear-gradient(135deg, #f9fafb, #f3f4f6);
    transition: all 0.3s;
    cursor: pointer;
    position: relative;
}

.upload-area:hover {
    border-color: #5b1f1f;
    background: linear-gradient(135deg, #fef3f2, #fee2e2);
    transform: translateY(-2px);
}

.upload-area.dragover {
    border-color: #5b1f1f;
    background: #fef3f2;
    transform: scale(1.02);
}

.upload-icon {
    font-size: 64px;
    margin-bottom: 20px;
    opacity: 0.7;
}

.upload-text {
    font-size: 18px;
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 10px;
}

.upload-hint {
    font-size: 14px;
    color: #6b7280;
    margin-bottom: 20px;
}

.upload-btn {
    background: linear-gradient(135deg, #5b1f1f, #ecc35c);
    color: white;
    padding: 12px 30px;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    font-size: 15px;
    cursor: pointer;
    transition: all 0.3s;
}

.upload-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(91, 31, 31, 0.3);
}

.file-input {
    display: none;
}

.paste-section {
    margin-top: 30px;
}

.paste-area {
    width: 100%;
    min-height: 200px;
    padding: 20px;
    border: 2px solid #d1d5db;
    border-radius: 12px;
    font-family: 'Courier New', monospace;
    font-size: 14px;
    resize: vertical;
    transition: all 0.3s;
}

.paste-area:focus {
    outline: none;
    border-color: #5b1f1f;
    box-shadow: 0 0 0 3px rgba(91, 31, 31, 0.1);
}

.analyze-btn {
    width: 100%;
    padding: 15px;
    background: linear-gradient(135deg, #5b1f1f, #ecc35c);
    color: white;
    border: none;
    border-radius: 12px;
    font-weight: 700;
    font-size: 16px;
    cursor: pointer;
    margin-top: 20px;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.analyze-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(91, 31, 31, 0.3);
}

.analyze-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

.tips-section {
    background: white;
    border-radius: 20px;
    padding: 40px;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
}

.tip-item {
    display: flex;
    gap: 15px;
    padding: 20px;
    background: linear-gradient(135deg, #f9fafb, #f3f4f6);
    border-radius: 12px;
    margin-bottom: 15px;
    border-left: 4px solid #ecc35c;
    transition: all 0.3s;
}

.tip-item:hover {
    transform: translateX(5px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.tip-icon {
    font-size: 28px;
    flex-shrink: 0;
}

.tip-content {
    flex: 1;
}

.tip-title {
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 5px;
    font-size: 16px;
}

.tip-text {
    color: #6b7280;
    font-size: 14px;
    line-height: 1.6;
}

.results-section {
    background: white;
    border-radius: 20px;
    padding: 40px;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
    display: none;
}

.results-section.active {
    display: block;
    animation: slideIn 0.5s ease;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.score-display {
    text-align: center;
    padding: 40px;
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    border-radius: 16px;
    margin-bottom: 30px;
}

.score-circle {
    width: 150px;
    height: 150px;
    margin: 0 auto 20px;
    position: relative;
}

.score-value {
    font-size: 48px;
    font-weight: 700;
    color: #5b1f1f;
}

.score-label {
    font-size: 18px;
    color: #6b7280;
    margin-top: 10px;
}

.score-rating {
    display: inline-block;
    padding: 8px 20px;
    background: #5b1f1f;
    color: white;
    border-radius: 20px;
    font-weight: 600;
    margin-top: 15px;
}

.analysis-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.analysis-card {
    background: #f9fafb;
    border-radius: 12px;
    padding: 25px;
    border-left: 4px solid #5b1f1f;
}

.card-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 15px;
}

.card-icon {
    font-size: 28px;
}

.card-title {
    font-size: 18px;
    font-weight: 700;
    color: #1f2937;
}

.card-score {
    margin-left: auto;
    font-size: 20px;
    font-weight: 700;
    color: #5b1f1f;
}

.card-content {
    color: #374151;
    line-height: 1.6;
}

.strength-item, .weakness-item {
    padding: 12px;
    background: white;
    border-radius: 8px;
    margin-bottom: 10px;
    display: flex;
    align-items: start;
    gap: 10px;
}

.strength-item {
    border-left: 3px solid #10b981;
}

.weakness-item {
    border-left: 3px solid #ef4444;
}

.suggestions-section {
    background: linear-gradient(135deg, #e0f2fe, #bae6fd);
    border-radius: 12px;
    padding: 25px;
    margin-top: 30px;
}

.suggestion-item {
    background: white;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 12px;
    display: flex;
    gap: 12px;
    align-items: start;
}

.suggestion-icon {
    font-size: 24px;
    flex-shrink: 0;
}

.suggestion-text {
    flex: 1;
    color: #374151;
    line-height: 1.6;
}

.action-buttons {
    display: flex;
    gap: 15px;
    margin-top: 30px;
}

.action-btn {
    flex: 1;
    padding: 15px;
    border: none;
    border-radius: 12px;
    font-weight: 600;
    font-size: 15px;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.btn-primary {
    background: linear-gradient(135deg, #5b1f1f, #ecc35c);
    color: white;
}

.btn-secondary {
    background: #f3f4f6;
    color: #374151;
    border: 2px solid #d1d5db;
}

.action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
}

.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.8);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}

.loading-overlay.active {
    display: flex;
}

.loading-content {
    background: white;
    padding: 50px;
    border-radius: 20px;
    text-align: center;
}

.spinner {
    width: 60px;
    height: 60px;
    border: 5px solid #f3f4f6;
    border-top-color: #5b1f1f;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 25px;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.loading-text {
    font-size: 18px;
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 10px;
}

.loading-subtext {
    font-size: 14px;
    color: #6b7280;
}

@media (max-width: 1024px) {
    .main-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .analyzer-header h1 {
        font-size: 2rem;
    }
    
    .action-buttons {
        flex-direction: column;
    }
}
</style>

<div class="analyzer-container">
    <div class="analyzer-header">
        <h1><span>🤖</span> AI Resume Analyzer</h1>
        <p>Get instant AI-powered feedback on your resume and improve your chances of landing your dream job</p>
    </div>

    <div class="main-grid">
        <!-- Upload Section -->
        <div class="upload-section">
            <h2 class="section-title">
                <span>📤</span>
                Upload Your Resume
            </h2>

            <div class="upload-area" id="uploadArea">
                <div class="upload-icon">📄</div>
                <div class="upload-text">Drag & Drop your resume here</div>
                <div class="upload-hint">or click to browse (PDF, DOC, DOCX, TXT)</div>
                <button class="upload-btn" onclick="document.getElementById('fileInput').click()">
                    Choose File
                </button>
                <input type="file" id="fileInput" class="file-input" accept=".pdf,.doc,.docx,.txt" onchange="handleFileSelect(event)">
            </div>

            <div class="paste-section">
                <h3 style="margin-bottom: 15px; color: #1f2937; font-size: 16px;">
                    <span>📋</span> Or Paste Resume Text
                </h3>
                <textarea 
                    id="resumeText" 
                    class="paste-area" 
                    placeholder="Paste your resume content here...&#10;&#10;Include:&#10;• Contact Information&#10;• Professional Summary&#10;• Work Experience&#10;• Education&#10;• Skills&#10;• Projects & Achievements"
                ></textarea>
            </div>

            <button class="analyze-btn" id="analyzeBtn" onclick="analyzeResume()">
                <span>🔍</span>
                Analyze Resume with AI
            </button>
        </div>

        <!-- Tips Section -->
        <div class="tips-section">
            <h2 class="section-title">
                <span>💡</span>
                Resume Tips
            </h2>

            <div class="tip-item">
                <div class="tip-icon">✅</div>
                <div class="tip-content">
                    <div class="tip-title">Use Action Verbs</div>
                    <div class="tip-text">Start bullet points with strong action verbs like "Developed", "Managed", "Implemented", "Achieved"</div>
                </div>
            </div>

            <div class="tip-item">
                <div class="tip-icon">📊</div>
                <div class="tip-content">
                    <div class="tip-title">Quantify Achievements</div>
                    <div class="tip-text">Include numbers, percentages, and metrics to demonstrate impact (e.g., "Increased sales by 30%")</div>
                </div>
            </div>

            <div class="tip-item">
                <div class="tip-icon">🎯</div>
                <div class="tip-content">
                    <div class="tip-title">Tailor to Job Description</div>
                    <div class="tip-text">Customize your resume for each application by matching keywords from the job posting</div>
                </div>
            </div>

            <div class="tip-item">
                <div class="tip-icon">📝</div>
                <div class="tip-content">
                    <div class="tip-title">Keep It Concise</div>
                    <div class="tip-text">Aim for 1-2 pages maximum. Focus on relevant experience from the last 10 years</div>
                </div>
            </div>

            <div class="tip-item">
                <div class="tip-icon">🔤</div>
                <div class="tip-content">
                    <div class="tip-title">Proofread Carefully</div>
                    <div class="tip-text">Check for spelling, grammar, and formatting errors. Have someone else review it too</div>
                </div>
            </div>

            <div class="tip-item">
                <div class="tip-icon">🎨</div>
                <div class="tip-content">
                    <div class="tip-title">Professional Formatting</div>
                    <div class="tip-text">Use consistent fonts, spacing, and bullet points. Ensure it's ATS-friendly</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Results Section -->
    <div class="results-section" id="resultsSection">
        <h2 class="section-title">
            <span>📊</span>
            Analysis Results
        </h2>

        <div class="score-display">
            <div class="score-circle">
                <svg width="150" height="150">
                    <circle cx="75" cy="75" r="65" fill="none" stroke="#e5e7eb" stroke-width="12"></circle>
                    <circle id="scoreCircle" cx="75" cy="75" r="65" fill="none" stroke="#5b1f1f" stroke-width="12" 
                            stroke-dasharray="408.4" stroke-dashoffset="408.4" 
                            style="transform: rotate(-90deg); transform-origin: center; transition: stroke-dashoffset 2s ease;">
                    </circle>
                </svg>
                <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);">
                    <div class="score-value" id="scoreValue">0</div>
                    <div style="font-size: 14px; color: #6b7280;">/ 100</div>
                </div>
            </div>
            <div class="score-label">Overall Resume Score</div>
            <div class="score-rating" id="scoreRating">Analyzing...</div>
        </div>

        <div class="analysis-grid">
            <div class="analysis-card">
                <div class="card-header">
                    <div class="card-icon">📝</div>
                    <div class="card-title">Content Quality</div>
                    <div class="card-score" id="contentScore">-</div>
                </div>
                <div class="card-content" id="contentAnalysis">
                    Analyzing content structure and relevance...
                </div>
            </div>

            <div class="analysis-card">
                <div class="card-header">
                    <div class="card-icon">🎨</div>
                    <div class="card-title">Formatting</div>
                    <div class="card-score" id="formatScore">-</div>
                </div>
                <div class="card-content" id="formatAnalysis">
                    Checking formatting and readability...
                </div>
            </div>

            <div class="analysis-card">
                <div class="card-header">
                    <div class="card-icon">🔑</div>
                    <div class="card-title">Keywords</div>
                    <div class="card-score" id="keywordScore">-</div>
                </div>
                <div class="card-content" id="keywordAnalysis">
                    Analyzing keyword optimization...
                </div>
            </div>

            <div class="analysis-card">
                <div class="card-header">
                    <div class="card-icon">💼</div>
                    <div class="card-title">Experience</div>
                    <div class="card-score" id="experienceScore">-</div>
                </div>
                <div class="card-content" id="experienceAnalysis">
                    Evaluating work experience section...
                </div>
            </div>

            <div class="analysis-card">
                <div class="card-header">
                    <div class="card-icon">⚡</div>
                    <div class="card-title">Skills</div>
                    <div class="card-score" id="skillsScore">-</div>
                </div>
                <div class="card-content" id="skillsAnalysis">
                    Reviewing skills presentation...
                </div>
            </div>

            <div class="analysis-card">
                <div class="card-header">
                    <div class="card-icon">🤖</div>
                    <div class="card-title">ATS Compatibility</div>
                    <div class="card-score" id="atsScore">-</div>
                </div>
                <div class="card-content" id="atsAnalysis">
                    Checking ATS system compatibility...
                </div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
            <div>
                <h3 style="color: #10b981; margin-bottom: 15px; font-size: 18px; display: flex; align-items: center; gap: 8px;">
                    <span>✅</span> Strengths
                </h3>
                <div id="strengthsList"></div>
            </div>
            <div>
                <h3 style="color: #ef4444; margin-bottom: 15px; font-size: 18px; display: flex; align-items: center; gap: 8px;">
                    <span>⚠️</span> Areas to Improve
                </h3>
                <div id="weaknessesList"></div>
            </div>
        </div>

        <div class="suggestions-section">
            <h3 style="margin-bottom: 20px; font-size: 20px; font-weight: 700; color: #1f2937;">
                <span>💡</span> AI Recommendations
            </h3>
            <div id="suggestionsList"></div>
        </div>

        <div class="action-buttons">
            <button class="action-btn btn-primary" onclick="downloadReport()">
                <span>📥</span> Download Report
            </button>
            <button class="action-btn btn-primary" onclick="window.location.href='resume_builder_advanced.php'">
                <span>✏️</span> Improve with Builder
            </button>
            <button class="action-btn btn-secondary" onclick="analyzeAnother()">
                <span>🔄</span> Analyze Another
            </button>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-content">
        <div class="spinner"></div>
        <div class="loading-text">Analyzing Your Resume...</div>
        <div class="loading-subtext">Our AI is reviewing your content, formatting, and keywords</div>
    </div>
</div>

<script>
// Drag and drop functionality
const uploadArea = document.getElementById('uploadArea');
const fileInput = document.getElementById('fileInput');

uploadArea.addEventListener('dragover', (e) => {
    e.preventDefault();
    uploadArea.classList.add('dragover');
});

uploadArea.addEventListener('dragleave', () => {
    uploadArea.classList.remove('dragover');
});

uploadArea.addEventListener('drop', (e) => {
    e.preventDefault();
    uploadArea.classList.remove('dragover');
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        handleFile(files[0]);
    }
});

uploadArea.addEventListener('click', (e) => {
    if (e.target === uploadArea || e.target.closest('.upload-icon, .upload-text, .upload-hint')) {
        fileInput.click();
    }
});

function handleFileSelect(event) {
    const file = event.target.files[0];
    if (file) {
        handleFile(file);
    }
}

function handleFile(file) {
    const validTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'text/plain'];
    
    if (!validTypes.includes(file.type)) {
        alert('Please upload a valid file (PDF, DOC, DOCX, or TXT)');
        return;
    }

    if (file.size > 5 * 1024 * 1024) {
        alert('File size should be less than 5MB');
        return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById('resumeText').value = 'File uploaded: ' + file.name + '\n\nContent will be extracted and analyzed...';
        alert('File uploaded successfully! Click "Analyze Resume" to continue.');
    };
    reader.readAsText(file);
}

function analyzeResume() {
    const resumeText = document.getElementById('resumeText').value.trim();
    
    if (!resumeText) {
        alert('Please upload a file or paste your resume text first!');
        return;
    }

    // Show loading
    document.getElementById('loadingOverlay').classList.add('active');
    
    // Simulate AI analysis
    setTimeout(() => {
        performAnalysis(resumeText);
        document.getElementById('loadingOverlay').classList.remove('active');
        document.getElementById('resultsSection').classList.add('active');
        document.getElementById('resultsSection').scrollIntoView({ behavior: 'smooth' });
    }, 3000);
}

function performAnalysis(text) {
    // Simulate AI analysis with realistic scores
    const wordCount = text.split(/\s+/).length;
    const hasEmail = /\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/.test(text);
    const hasPhone = /\b\d{10}\b|\b\d{3}[-.\s]?\d{3}[-.\s]?\d{4}\b/.test(text);
    const hasActionVerbs = /(developed|managed|led|created|implemented|designed|achieved|improved)/gi.test(text);
    const hasNumbers = /\d+%|\d+\+/.test(text);
    
    // Calculate scores
    const contentScore = Math.min(95, 60 + (wordCount > 200 ? 20 : 10) + (hasActionVerbs ? 15 : 0));
    const formatScore = Math.min(95, 70 + (hasEmail ? 10 : 0) + (hasPhone ? 10 : 0));
    const keywordScore = Math.min(95, 65 + (hasActionVerbs ? 15 : 0) + (hasNumbers ? 10 : 0));
    const experienceScore = Math.min(95, 70 + (wordCount > 300 ? 15 : 5));
    const skillsScore = Math.min(95, 75 + (text.toLowerCase().includes('skill') ? 10 : 0));
    const atsScore = Math.min(95, 80 + (hasEmail && hasPhone ? 10 : 0));
    
    const overallScore = Math.round((contentScore + formatScore + keywordScore + experienceScore + skillsScore + atsScore) / 6);
    
    // Update UI
    updateScore(overallScore);
    updateScoreRating(overallScore);
    
    document.getElementById('contentScore').textContent = contentScore + '/100';
    document.getElementById('formatScore').textContent = formatScore + '/100';
    document.getElementById('keywordScore').textContent = keywordScore + '/100';
    document.getElementById('experienceScore').textContent = experienceScore + '/100';
    document.getElementById('skillsScore').textContent = skillsScore + '/100';
    document.getElementById('atsScore').textContent = atsScore + '/100';
    
    // Update analysis text
    document.getElementById('contentAnalysis').innerHTML = contentScore >= 80 
        ? 'Strong content structure with clear sections and relevant information.'
        : 'Content could be improved with more detailed descriptions and achievements.';
        
    document.getElementById('formatAnalysis').innerHTML = formatScore >= 80
        ? 'Well-formatted resume with proper contact information and structure.'
        : 'Consider improving formatting and ensuring all contact details are included.';
        
    document.getElementById('keywordAnalysis').innerHTML = keywordScore >= 80
        ? 'Good use of industry keywords and action verbs throughout.'
        : 'Add more relevant keywords and action verbs to improve visibility.';
        
    document.getElementById('experienceAnalysis').innerHTML = experienceScore >= 80
        ? 'Experience section is detailed with clear role descriptions.'
        : 'Expand on your work experience with more specific achievements.';
        
    document.getElementById('skillsAnalysis').innerHTML = skillsScore >= 80
        ? 'Skills are well-presented and relevant to your field.'
        : 'Consider adding more technical and soft skills relevant to your target role.';
        
    document.getElementById('atsAnalysis').innerHTML = atsScore >= 80
        ? 'Resume is ATS-friendly with standard formatting and clear sections.'
        : 'Improve ATS compatibility by using standard section headings and avoiding complex formatting.';
    
    // Generate strengths
    const strengths = [];
    if (hasEmail && hasPhone) strengths.push('Complete contact information provided');
    if (hasActionVerbs) strengths.push('Effective use of action verbs');
    if (hasNumbers) strengths.push('Quantifiable achievements included');
    if (wordCount > 300) strengths.push('Comprehensive content coverage');
    if (contentScore >= 80) strengths.push('Well-structured content organization');
    
    const strengthsHTML = strengths.map(s => `
        <div class="strength-item">
            <span style="color: #10b981; font-size: 20px;">✓</span>
            <div>${s}</div>
        </div>
    `).join('');
    document.getElementById('strengthsList').innerHTML = strengthsHTML || '<div class="strength-item"><span>✓</span><div>Keep building your resume!</div></div>';
    
    // Generate weaknesses
    const weaknesses = [];
    if (!hasEmail || !hasPhone) weaknesses.push('Missing or incomplete contact information');
    if (!hasActionVerbs) weaknesses.push('Limited use of strong action verbs');
    if (!hasNumbers) weaknesses.push('Lack of quantifiable achievements');
    if (wordCount < 200) weaknesses.push('Content could be more detailed');
    if (formatScore < 80) weaknesses.push('Formatting needs improvement');
    
    const weaknessesHTML = weaknesses.map(w => `
        <div class="weakness-item">
            <span style="color: #ef4444; font-size: 20px;">!</span>
            <div>${w}</div>
        </div>
    `).join('');
    document.getElementById('weaknessesList').innerHTML = weaknessesHTML || '<div class="weakness-item"><span>!</span><div>Great job! Minor improvements suggested below.</div></div>';
    
    // Generate suggestions
    const suggestions = [
        'Add specific metrics and numbers to demonstrate your impact (e.g., "Increased efficiency by 25%")',
        'Use more action verbs at the start of bullet points (Developed, Managed, Led, Implemented)',
        'Tailor your resume to match keywords from the job description you\'re applying for',
        'Keep your resume to 1-2 pages and focus on the most recent and relevant experience',
        'Include a professional summary at the top highlighting your key strengths',
        'Ensure consistent formatting throughout (fonts, spacing, bullet points)',
        'Add relevant technical skills and certifications for your target role',
        'Proofread carefully for any spelling or grammatical errors'
    ];
    
    const suggestionsHTML = suggestions.slice(0, 5).map(s => `
        <div class="suggestion-item">
            <div class="suggestion-icon">💡</div>
            <div class="suggestion-text">${s}</div>
        </div>
    `).join('');
    document.getElementById('suggestionsList').innerHTML = suggestionsHTML;
}

function updateScore(score) {
    const circle = document.getElementById('scoreCircle');
    const circumference = 408.4;
    const offset = circumference - (circumference * score / 100);
    
    // Animate score
    let currentScore = 0;
    const interval = setInterval(() => {
        if (currentScore >= score) {
            clearInterval(interval);
        } else {
            currentScore++;
            document.getElementById('scoreValue').textContent = currentScore;
            const currentOffset = circumference - (circumference * currentScore / 100);
            circle.style.strokeDashoffset = currentOffset;
        }
    }, 20);
}

function updateScoreRating(score) {
    const rating = document.getElementById('scoreRating');
    if (score >= 90) {
        rating.textContent = 'Excellent';
        rating.style.background = '#10b981';
    } else if (score >= 80) {
        rating.textContent = 'Very Good';
        rating.style.background = '#3b82f6';
    } else if (score >= 70) {
        rating.textContent = 'Good';
        rating.style.background = '#f59e0b';
    } else if (score >= 60) {
        rating.textContent = 'Fair';
        rating.style.background = '#f97316';
    } else {
        rating.textContent = 'Needs Improvement';
        rating.style.background = '#ef4444';
    }
}

function downloadReport() {
    alert('Downloading detailed analysis report...\n\nThis feature would generate a PDF report with all analysis results, suggestions, and improvement tips.');
}

function analyzeAnother() {
    document.getElementById('resultsSection').classList.remove('active');
    document.getElementById('resumeText').value = '';
    document.getElementById('fileInput').value = '';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>
