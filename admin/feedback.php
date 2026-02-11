<?php
require_once __DIR__ . '/../includes/config.php';
require_role('admin');

// Fetch all students for feedback dropdown
$students_query = "SELECT id, username, email FROM users WHERE role = 'student' ORDER BY username ASC";
$students_result = $mysqli->query($students_query);
$students = $students_result ? $students_result->fetch_all(MYSQLI_ASSOC) : [];

// Mock feedback history
$feedback_history = [
    [
        'id' => 1,
        'student_name' => 'John Doe',
        'student_email' => 'john@example.com',
        'feedback_type' => 'Mock Interview',
        'rating' => 4,
        'feedback' => 'Good technical knowledge and problem-solving skills. Communication needs improvement. Practice more behavioral questions.',
        'next_steps' => 'Focus on STAR method for behavioral questions. Practice with peers.',
        'given_by' => 'Admin',
        'given_date' => '2025-01-08 14:30:00'
    ],
    [
        'id' => 2,
        'student_name' => 'Jane Smith',
        'student_email' => 'jane@example.com',
        'feedback_type' => 'Project Review',
        'rating' => 5,
        'feedback' => 'Excellent project implementation. Code is well-structured and follows best practices. Documentation is comprehensive.',
        'next_steps' => 'Deploy the project and add it to portfolio. Consider adding more advanced features.',
        'given_by' => 'Admin',
        'given_date' => '2025-01-08 10:15:00'
    ],
    [
        'id' => 3,
        'student_name' => 'Mike Johnson',
        'student_email' => 'mike@example.com',
        'feedback_type' => 'Resume Review',
        'rating' => 3,
        'feedback' => 'Resume structure is good but lacks quantifiable achievements. Add more metrics and numbers to showcase impact.',
        'next_steps' => 'Revise resume with specific metrics. Add 2-3 more projects. Get it reviewed again.',
        'given_by' => 'Admin',
        'given_date' => '2025-01-07 16:45:00'
    ],
    [
        'id' => 4,
        'student_name' => 'Sarah Williams',
        'student_email' => 'sarah@example.com',
        'feedback_type' => 'General',
        'rating' => 4,
        'feedback' => 'Strong academic performance. Actively participating in placement activities. Keep up the good work!',
        'next_steps' => 'Start applying to companies. Prepare for aptitude tests.',
        'given_by' => 'Admin',
        'given_date' => '2025-01-07 09:20:00'
    ],
];

include __DIR__ . '/../includes/partials/header.php';
?>

<style>
.feedback-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 30px;
}

.page-header {
    margin-bottom: 30px;
}

.page-title {
    font-size: 32px;
    font-weight: 700;
    color: #5b1f1f;
    margin-bottom: 10px;
}

.page-subtitle {
    color: #6b7280;
    font-size: 16px;
}

.action-bar {
    display: flex;
    gap: 15px;
    margin-bottom: 30px;
    flex-wrap: wrap;
}

.btn {
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    font-size: 14px;
}

.btn-primary {
    background: linear-gradient(135deg, #5b1f1f, #ecc35c);
    color: white;
}

.btn-secondary {
    background: #f3f4f6;
    color: #4b5563;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    text-align: center;
}

.stat-value {
    font-size: 32px;
    font-weight: 700;
    color: #5b1f1f;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 13px;
    color: #6b7280;
}

.feedback-grid {
    display: grid;
    gap: 20px;
}

.feedback-card {
    background: white;
    padding: 25px;
    border-radius: 16px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    border-left: 4px solid #5b1f1f;
    transition: all 0.3s;
}

.feedback-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
}

.feedback-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 20px;
}

.student-info {
    display: flex;
    align-items: center;
    gap: 15px;
}

.student-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, #5b1f1f, #ecc35c);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 18px;
}

.student-details h3 {
    font-size: 18px;
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 5px;
}

.student-details p {
    font-size: 13px;
    color: #6b7280;
}

.feedback-meta {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 8px;
}

.rating-stars {
    display: flex;
    gap: 3px;
}

.feedback-type {
    padding: 5px 12px;
    background: #f3f4f6;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    color: #4b5563;
}

.feedback-content {
    background: #f9fafb;
    padding: 15px;
    border-radius: 12px;
    margin-bottom: 15px;
}

.feedback-label {
    font-size: 12px;
    font-weight: 600;
    color: #6b7280;
    margin-bottom: 8px;
    text-transform: uppercase;
}

.feedback-text {
    color: #4b5563;
    line-height: 1.6;
    font-size: 14px;
}

.next-steps {
    background: #fef3c7;
    padding: 15px;
    border-radius: 12px;
    margin-bottom: 15px;
}

.feedback-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 15px;
    border-top: 1px solid #e5e7eb;
    font-size: 13px;
    color: #6b7280;
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
    overflow-y: auto;
}

.modal.active {
    display: flex;
}

.modal-content {
    background: white;
    padding: 30px;
    border-radius: 16px;
    max-width: 700px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 2px solid #e5e7eb;
}

.modal-title {
    font-size: 24px;
    font-weight: 700;
    color: #5b1f1f;
}

.close-modal {
    background: none;
    border: none;
    font-size: 28px;
    cursor: pointer;
    color: #6b7280;
}

.form-group {
    margin-bottom: 20px;
}

.form-label {
    display: block;
    font-weight: 600;
    color: #4b5563;
    margin-bottom: 8px;
    font-size: 14px;
}

.form-input, .form-select, .form-textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 14px;
}

.form-textarea {
    min-height: 120px;
    resize: vertical;
}

.btn-submit {
    width: 100%;
    padding: 14px;
    background: linear-gradient(135deg, #5b1f1f, #ecc35c);
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 16px;
    cursor: pointer;
}

.btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(91, 31, 31, 0.3);
}
</style>

<div class="feedback-container">
    <div class="page-header">
        <h1 class="page-title">💬 Feedback Management</h1>
        <p class="page-subtitle">Provide feedback to students and track their progress</p>
    </div>

    <div class="action-bar">
        <button class="btn btn-primary" onclick="openFeedbackModal()">
            ➕ Give New Feedback
        </button>
        <button class="btn btn-secondary" onclick="openBroadcastModal()">
            📢 Broadcast Message
        </button>
        <button class="btn btn-secondary" onclick="exportFeedback()">
            📥 Export Report
        </button>
    </div>

    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-value"><?php echo count($feedback_history); ?></div>
            <div class="stat-label">Total Feedback Given</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">2</div>
            <div class="stat-label">This Week</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">4.2</div>
            <div class="stat-label">Average Rating</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo count($students); ?></div>
            <div class="stat-label">Total Students</div>
        </div>
    </div>

    <h2 style="font-size: 20px; font-weight: 600; color: #1f2937; margin-bottom: 20px;">Recent Feedback</h2>

    <div class="feedback-grid">
        <?php foreach ($feedback_history as $feedback): ?>
        <div class="feedback-card">
            <div class="feedback-header">
                <div class="student-info">
                    <div class="student-avatar">
                        <?php echo strtoupper(substr($feedback['student_name'], 0, 1)); ?>
                    </div>
                    <div class="student-details">
                        <h3><?php echo htmlspecialchars($feedback['student_name']); ?></h3>
                        <p><?php echo htmlspecialchars($feedback['student_email']); ?></p>
                    </div>
                </div>
                <div class="feedback-meta">
                    <div class="rating-stars">
                        <?php for ($i = 0; $i < 5; $i++): ?>
                            <span style="color: <?php echo $i < $feedback['rating'] ? '#ecc35c' : '#e5e7eb'; ?>; font-size: 18px;">⭐</span>
                        <?php endfor; ?>
                    </div>
                    <span class="feedback-type"><?php echo htmlspecialchars($feedback['feedback_type']); ?></span>
                </div>
            </div>

            <div class="feedback-content">
                <div class="feedback-label">Feedback</div>
                <div class="feedback-text"><?php echo htmlspecialchars($feedback['feedback']); ?></div>
            </div>

            <?php if (!empty($feedback['next_steps'])): ?>
            <div class="next-steps">
                <div class="feedback-label">Recommended Next Steps</div>
                <div class="feedback-text"><?php echo htmlspecialchars($feedback['next_steps']); ?></div>
            </div>
            <?php endif; ?>

            <div class="feedback-footer">
                <span>Given by: <strong><?php echo $feedback['given_by']; ?></strong></span>
                <span>📅 <?php echo date('M d, Y h:i A', strtotime($feedback['given_date'])); ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Give Feedback Modal -->
<div class="modal" id="feedbackModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Give Feedback to Student</h2>
            <button class="close-modal" onclick="closeModal('feedbackModal')">×</button>
        </div>
        <form id="feedbackForm">
            <div class="form-group">
                <label class="form-label">Select Student *</label>
                <select class="form-select" id="studentSelect" required>
                    <option value="">Choose a student...</option>
                    <?php foreach ($students as $student): ?>
                        <option value="<?php echo $student['id']; ?>">
                            <?php echo htmlspecialchars($student['username']); ?> (<?php echo htmlspecialchars($student['email']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Feedback Type *</label>
                <select class="form-select" id="feedbackType" required>
                    <option value="">Select type...</option>
                    <option value="Mock Interview">Mock Interview</option>
                    <option value="Project Review">Project Review</option>
                    <option value="Resume Review">Resume Review</option>
                    <option value="Technical Assessment">Technical Assessment</option>
                    <option value="General">General Feedback</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Rating *</label>
                <select class="form-select" id="rating" required>
                    <option value="">Select rating...</option>
                    <option value="5">⭐⭐⭐⭐⭐ Excellent (5/5)</option>
                    <option value="4">⭐⭐⭐⭐ Very Good (4/5)</option>
                    <option value="3">⭐⭐⭐ Good (3/5)</option>
                    <option value="2">⭐⭐ Needs Improvement (2/5)</option>
                    <option value="1">⭐ Poor (1/5)</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Feedback / Remarks *</label>
                <textarea class="form-textarea" id="feedbackText" required placeholder="Provide detailed feedback about the student's performance..."></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Recommended Next Steps</label>
                <textarea class="form-textarea" id="nextSteps" placeholder="Suggest specific actions or improvements..."></textarea>
            </div>

            <button type="submit" class="btn-submit">Submit Feedback</button>
        </form>
    </div>
</div>

<!-- Broadcast Modal -->
<div class="modal" id="broadcastModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">📢 Broadcast Message to All Students</h2>
            <button class="close-modal" onclick="closeModal('broadcastModal')">×</button>
        </div>
        <form id="broadcastForm">
            <div class="form-group">
                <label class="form-label">Subject *</label>
                <input type="text" class="form-input" id="broadcastSubject" required placeholder="Message subject...">
            </div>

            <div class="form-group">
                <label class="form-label">Message *</label>
                <textarea class="form-textarea" id="broadcastMessage" required placeholder="Type your message to all students..."></textarea>
            </div>

            <button type="submit" class="btn-submit">Send to All Students</button>
        </form>
    </div>
</div>

<script>
function openFeedbackModal() {
    document.getElementById('feedbackModal').classList.add('active');
}

function openBroadcastModal() {
    document.getElementById('broadcastModal').classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

function exportFeedback() {
    alert('Exporting feedback report...\n\nThis would generate a PDF/Excel report with all feedback data.');
}

// Submit Feedback Form
document.getElementById('feedbackForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const student = document.getElementById('studentSelect').options[document.getElementById('studentSelect').selectedIndex].text;
    const type = document.getElementById('feedbackType').value;
    const rating = document.getElementById('rating').value;
    const feedback = document.getElementById('feedbackText').value;
    const nextSteps = document.getElementById('nextSteps').value;
    
    alert(`Feedback submitted successfully!\n\nStudent: ${student}\nType: ${type}\nRating: ${rating} stars\n\nThe student will be notified via email.`);
    
    closeModal('feedbackModal');
    this.reset();
    location.reload();
});

// Submit Broadcast Form
document.getElementById('broadcastForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const subject = document.getElementById('broadcastSubject').value;
    const message = document.getElementById('broadcastMessage').value;
    
    alert(`Broadcasting message to all students:\n\nSubject: ${subject}\n\nThis would send an email/notification to all ${<?php echo count($students); ?>} students.`);
    
    closeModal('broadcastModal');
    this.reset();
});

// Close modals when clicking outside
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});
</script>

<?php include __DIR__ . '/../includes/partials/footer.php'; ?>
