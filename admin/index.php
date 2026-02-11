<?php
require_once __DIR__ . '/../includes/config.php';
require_role('admin');

$admin_id = $_SESSION['user_id'];

// Fetch all students with their data
$students_query = "
    SELECT 
        u.id,
        u.username,
        u.email,
        u.full_name,
        u.phone,
        u.branch,
        u.semester,
        u.cgpa,
        u.created_at
    FROM users u
    WHERE u.role = 'student'
    ORDER BY u.username ASC
";
$students_result = $mysqli->query($students_query);
$students = $students_result ? $students_result->fetch_all(MYSQLI_ASSOC) : [];

// Calculate statistics
$total_students = count($students);
$ready_students = 0;
$moderate_students = 0;
$needs_work_students = 0;

foreach ($students as &$student) {
    // Calculate profile completion
    $fields = ['username', 'email', 'full_name', 'phone', 'branch', 'semester', 'cgpa'];
    $completed = 0;
    foreach ($fields as $field) {
        if (!empty($student[$field])) $completed++;
    }
    $student['profile_completion'] = round(($completed / count($fields)) * 100);
    
    // Determine readiness status
    if ($student['profile_completion'] >= 80 && !empty($student['cgpa']) && $student['cgpa'] >= 7.0) {
        $student['readiness'] = 'Ready';
        $ready_students++;
    } elseif ($student['profile_completion'] >= 50) {
        $student['readiness'] = 'Moderate';
        $moderate_students++;
    } else {
        $student['readiness'] = 'Needs Work';
        $needs_work_students++;
    }
}

include __DIR__ . '/../includes/partials/header.php';
?>

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

.faculty-dashboard {
    display: grid;
    grid-template-columns: 260px 1fr;
    min-height: 100vh;
    background: #f5f7fa;
}

/* Sidebar */
.faculty-sidebar {
    background: linear-gradient(180deg, #5b1f1f 0%, #3d1414 100%);
    color: white;
    padding: 20px;
    position: sticky;
    top: 0;
    height: 100vh;
    overflow-y: auto;
}

.sidebar-header {
    text-align: center;
    padding: 20px 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    margin-bottom: 20px;
}

.admin-avatar {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    background: linear-gradient(135deg, #ecc35c, #f7f3b7);
    color: #5b1f1f;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    font-weight: 700;
    margin: 0 auto 15px;
    border: 3px solid rgba(236, 195, 92, 0.3);
}

.admin-name {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 5px;
}

.admin-role {
    font-size: 14px;
    opacity: 0.8;
}

.sidebar-nav {
    margin-top: 20px;
}

.nav-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 15px;
    border-radius: 8px;
    color: white;
    text-decoration: none;
    transition: all 0.3s;
    margin-bottom: 5px;
}

.nav-item:hover, .nav-item.active {
    background: rgba(236, 195, 92, 0.2);
    transform: translateX(5px);
}

.nav-icon {
    font-size: 20px;
}

/* Main Content */
.faculty-content {
    padding: 30px;
    overflow-y: auto;
}

.page-header {
    margin-bottom: 30px;
}

.page-title {
    font-size: 32px;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 5px;
}

.page-subtitle {
    color: #6b7280;
    font-size: 16px;
}

/* Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 16px;
    padding: 25px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    transition: all 0.3s;
    border-left: 4px solid #5b1f1f;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
}

.stat-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.stat-icon {
    font-size: 32px;
}

.stat-value {
    font-size: 36px;
    font-weight: 700;
    color: #5b1f1f;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 14px;
    color: #6b7280;
}

/* Filters */
.filters-section {
    background: white;
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 30px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.filters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.filter-label {
    font-size: 14px;
    font-weight: 600;
    color: #4b5563;
}

.filter-input, .filter-select {
    padding: 10px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 14px;
}

/* Students Table */
.students-section {
    background: white;
    border-radius: 16px;
    padding: 25px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.section-title {
    font-size: 20px;
    font-weight: 600;
    color: #1f2937;
}

.students-table {
    width: 100%;
    border-collapse: collapse;
}

.students-table thead {
    background: #f9fafb;
    border-bottom: 2px solid #e5e7eb;
}

.students-table th {
    padding: 12px;
    text-align: left;
    font-size: 13px;
    font-weight: 600;
    color: #6b7280;
    text-transform: uppercase;
}

.students-table td {
    padding: 15px 12px;
    border-bottom: 1px solid #f3f4f6;
}

.students-table tbody tr {
    transition: all 0.2s;
}

.students-table tbody tr:hover {
    background: #fef9f5;
}

.student-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.student-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #5b1f1f, #ecc35c);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 14px;
}

.student-details {
    display: flex;
    flex-direction: column;
}

.student-name {
    font-weight: 600;
    color: #1f2937;
    font-size: 14px;
}

.student-email {
    font-size: 12px;
    color: #6b7280;
}

.progress-bar {
    width: 100%;
    height: 8px;
    background: #e5e7eb;
    border-radius: 10px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #5b1f1f, #ecc35c);
    transition: width 0.5s ease;
}

.progress-text {
    font-size: 12px;
    color: #6b7280;
    margin-top: 5px;
}

.status-badge {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-align: center;
}

.status-ready {
    background: #d1fae5;
    color: #065f46;
}

.status-moderate {
    background: #fef3c7;
    color: #92400e;
}

.status-needs-work {
    background: #fee2e2;
    color: #991b1b;
}

.action-buttons {
    display: flex;
    gap: 8px;
}

.btn-action {
    padding: 6px 12px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 600;
    transition: all 0.2s;
}

.btn-view {
    background: #dbeafe;
    color: #1e40af;
}

.btn-feedback {
    background: #fef3c7;
    color: #92400e;
}

.btn-verify {
    background: #d1fae5;
    color: #065f46;
}

.btn-action:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

/* Modal */
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
    border-radius: 16px;
    padding: 30px;
    max-width: 800px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
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
    color: #6b7280;
    cursor: pointer;
    line-height: 1;
}

.close-modal:hover {
    color: #1f2937;
}

.modal-section {
    margin-bottom: 25px;
}

.modal-section h3 {
    font-size: 18px;
    color: #1f2937;
    margin-bottom: 15px;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.info-item {
    padding: 12px;
    background: #f9fafb;
    border-radius: 8px;
}

.info-label {
    font-size: 12px;
    color: #6b7280;
    margin-bottom: 5px;
}

.info-value {
    font-size: 16px;
    font-weight: 600;
    color: #1f2937;
}

.feedback-form {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.form-label {
    font-size: 14px;
    font-weight: 600;
    color: #4b5563;
}

.form-input, .form-textarea, .form-select {
    padding: 10px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 14px;
}

.form-textarea {
    min-height: 100px;
    resize: vertical;
}

.btn-submit {
    background: linear-gradient(135deg, #5b1f1f, #ecc35c);
    color: white;
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(91, 31, 31, 0.3);
}

/* Quick Actions */
.quick-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.action-card {
    background: white;
    border-radius: 16px;
    padding: 20px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    cursor: pointer;
    transition: all 0.3s;
    border: 2px solid transparent;
}

.action-card:hover {
    border-color: #5b1f1f;
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
}

.action-icon {
    font-size: 36px;
    margin-bottom: 10px;
}

.action-title {
    font-size: 16px;
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 5px;
}

.action-description {
    font-size: 13px;
    color: #6b7280;
}
</style>

<div class="faculty-dashboard">
    <!-- Sidebar -->
    <aside class="faculty-sidebar">
        <div class="sidebar-header">
            <div class="admin-avatar">
                <?php echo strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)); ?>
            </div>
            <div class="admin-name"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></div>
            <div class="admin-role">Faculty / Mentor</div>
        </div>

        <nav class="sidebar-nav">
            <a href="index.php" class="nav-item active">
                <span class="nav-icon">📊</span>
                <span>Overview</span>
            </a>
            <a href="#students" class="nav-item">
                <span class="nav-icon">👥</span>
                <span>Students</span>
            </a>
            <a href="resume_review.php" class="nav-item">
                <span class="nav-icon">🤖</span>
                <span>Resume Review</span>
            </a>
            <a href="feedback.php" class="nav-item">
                <span class="nav-icon">💬</span>
                <span>Feedback</span>
            </a>
            <a href="#reports" class="nav-item">
                <span class="nav-icon">📈</span>
                <span>Reports</span>
            </a>
            <a href="manage_content.php" class="nav-item">
                <span class="nav-icon">📚</span>
                <span>Content</span>
            </a>
            <a href="manage_interview_domains.php" class="nav-item">
                <span class="nav-icon">🎯</span>
                <span>Interview Domains</span>
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="faculty-content">
        <div class="page-header">
            <h1 class="page-title">Faculty Dashboard</h1>
            <p class="page-subtitle">Track, guide, and verify students' placement readiness</p>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?php echo $total_students; ?></div>
                        <div class="stat-label">Total Students</div>
                    </div>
                    <div class="stat-icon">👨‍🎓</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?php echo $ready_students; ?></div>
                        <div class="stat-label">Ready for Placement</div>
                    </div>
                    <div class="stat-icon">✅</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?php echo $moderate_students; ?></div>
                        <div class="stat-label">Moderate Progress</div>
                    </div>
                    <div class="stat-icon">⚠️</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?php echo $needs_work_students; ?></div>
                        <div class="stat-label">Needs Attention</div>
                    </div>
                    <div class="stat-icon">🔴</div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <div class="action-card" onclick="openBroadcastModal()">
                <div class="action-icon">📢</div>
                <div class="action-title">Broadcast Message</div>
                <div class="action-description">Send message to all students</div>
            </div>

            <div class="action-card" onclick="generateReport()">
                <div class="action-icon">📊</div>
                <div class="action-title">Generate Report</div>
                <div class="action-description">Export placement progress report</div>
            </div>

            <div class="action-card" onclick="window.location.href='manage_interview_domains.php'">
                <div class="action-icon">🎯</div>
                <div class="action-title">Interview Domains</div>
                <div class="action-description">Manage AI interview topics</div>
            </div>

            <div class="action-card" onclick="aiMentorAssistant()">
                <div class="action-icon">🤖</div>
                <div class="action-title">AI Mentor Assistant</div>
                <div class="action-description">Get AI-powered insights</div>
            </div>

            <div class="action-card" onclick="viewJobAppliedStudents()">
                <div class="action-icon">💼</div>
                <div class="action-title">Job Applied Students</div>
                <div class="action-description">Track students who have applied for jobs and their application status</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-section">
            <div class="filters-grid">
                <div class="filter-group">
                    <label class="filter-label">Search Student</label>
                    <input type="text" class="filter-input" id="searchStudent" placeholder="Name or email..." onkeyup="filterStudents()">
                </div>

                <div class="filter-group">
                    <label class="filter-label">Readiness Status</label>
                    <select class="filter-select" id="filterReadiness" onchange="filterStudents()">
                        <option value="">All Status</option>
                        <option value="Ready">Ready</option>
                        <option value="Moderate">Moderate</option>
                        <option value="Needs Work">Needs Work</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label">CGPA Range</label>
                    <select class="filter-select" id="filterCGPA" onchange="filterStudents()">
                        <option value="">All CGPA</option>
                        <option value="9-10">9.0 - 10.0</option>
                        <option value="8-9">8.0 - 8.9</option>
                        <option value="7-8">7.0 - 7.9</option>
                        <option value="0-7">Below 7.0</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label">Branch</label>
                    <select class="filter-select" id="filterBranch" onchange="filterStudents()">
                        <option value="">All Branches</option>
                        <option value="CSE">Computer Science</option>
                        <option value="ECE">Electronics</option>
                        <option value="MECH">Mechanical</option>
                        <option value="CIVIL">Civil</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Students Table -->
        <div class="students-section" id="students">
            <div class="section-header">
                <h2 class="section-title">Student Overview</h2>
            </div>

            <table class="students-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Branch</th>
                        <th>CGPA</th>
                        <th>Profile Progress</th>
                        <th>Readiness</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="studentsTableBody">
                    <?php foreach ($students as $student): ?>
                    <tr class="student-row" 
                        data-readiness="<?php echo $student['readiness']; ?>"
                        data-cgpa="<?php echo $student['cgpa'] ?? 0; ?>"
                        data-branch="<?php echo $student['branch'] ?? ''; ?>"
                        data-name="<?php echo strtolower($student['username'] . ' ' . ($student['full_name'] ?? '') . ' ' . $student['email']); ?>">
                        <td>
                            <div class="student-info">
                                <div class="student-avatar">
                                    <?php echo strtoupper(substr($student['username'], 0, 1)); ?>
                                </div>
                                <div class="student-details">
                                    <div class="student-name"><?php echo htmlspecialchars($student['username']); ?></div>
                                    <div class="student-email"><?php echo htmlspecialchars($student['email']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($student['branch'] ?? 'N/A'); ?></td>
                        <td><strong><?php echo $student['cgpa'] ?? 'N/A'; ?></strong></td>
                        <td>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $student['profile_completion']; ?>%;"></div>
                            </div>
                            <div class="progress-text"><?php echo $student['profile_completion']; ?>% Complete</div>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $student['readiness'])); ?>">
                                <?php echo $student['readiness']; ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn-action btn-view" onclick='viewStudent(<?php echo json_encode($student); ?>)'>
                                    👁️ View
                                </button>
                                <button class="btn-action btn-feedback" onclick="openFeedbackModal(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars($student['username']); ?>')">
                                    💬 Feedback
                                </button>
                                <button class="btn-action btn-verify" onclick="openVerifyModal(<?php echo $student['id']; ?>)">
                                    ✅ Verify
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<!-- Student Detail Modal -->
<div class="modal" id="studentModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title" id="studentModalTitle">Student Details</h2>
            <button class="close-modal" onclick="closeModal('studentModal')">×</button>
        </div>
        <div id="studentModalBody"></div>
    </div>
</div>

<!-- Feedback Modal -->
<div class="modal" id="feedbackModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Provide Feedback</h2>
            <button class="close-modal" onclick="closeModal('feedbackModal')">×</button>
        </div>
        <form class="feedback-form" id="feedbackForm">
            <input type="hidden" id="feedbackStudentId">
            
            <div class="form-group">
                <label class="form-label">Student</label>
                <input type="text" class="form-input" id="feedbackStudentName" readonly>
            </div>

            <div class="form-group">
                <label class="form-label">Feedback Type</label>
                <select class="form-select" id="feedbackType" required>
                    <option value="">Select type...</option>
                    <option value="mock_interview">Mock Interview</option>
                    <option value="project_review">Project Review</option>
                    <option value="resume_review">Resume Review</option>
                    <option value="general">General Feedback</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Rating</label>
                <select class="form-select" id="feedbackRating" required>
                    <option value="">Select rating...</option>
                    <option value="5">⭐⭐⭐⭐⭐ Excellent</option>
                    <option value="4">⭐⭐⭐⭐ Good</option>
                    <option value="3">⭐⭐⭐ Average</option>
                    <option value="2">⭐⭐ Needs Improvement</option>
                    <option value="1">⭐ Poor</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Feedback / Remarks</label>
                <textarea class="form-textarea" id="feedbackRemarks" required placeholder="Provide detailed feedback..."></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Recommended Next Steps</label>
                <textarea class="form-textarea" id="feedbackNextSteps" placeholder="Suggest improvements or next actions..."></textarea>
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
        <form class="feedback-form" id="broadcastForm">
            <div class="form-group">
                <label class="form-label">Subject</label>
                <input type="text" class="form-input" id="broadcastSubject" required placeholder="Message subject...">
            </div>

            <div class="form-group">
                <label class="form-label">Message</label>
                <textarea class="form-textarea" id="broadcastMessage" required placeholder="Type your message to all students..."></textarea>
            </div>

            <button type="submit" class="btn-submit">Send to All Students</button>
        </form>
    </div>
</div>

<!-- Verify Modal -->
<div class="modal" id="verifyModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">✅ Verify Student Documents</h2>
            <button class="close-modal" onclick="closeModal('verifyModal')">×</button>
        </div>
        <div class="modal-section">
            <h3>Documents to Verify</h3>
            <div style="display: flex; flex-direction: column; gap: 15px;">
                <div style="padding: 15px; background: #f9fafb; border-radius: 8px; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <div style="font-weight: 600;">Resume</div>
                        <div style="font-size: 12px; color: #6b7280;">Uploaded: 2 days ago</div>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button class="btn-action" style="background: #d1fae5; color: #065f46;">✅ Approve</button>
                        <button class="btn-action" style="background: #fee2e2; color: #991b1b;">❌ Reject</button>
                    </div>
                </div>
                
                <div style="padding: 15px; background: #f9fafb; border-radius: 8px; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <div style="font-weight: 600;">Certification - AWS Cloud</div>
                        <div style="font-size: 12px; color: #6b7280;">Uploaded: 5 days ago</div>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button class="btn-action" style="background: #d1fae5; color: #065f46;">✅ Approve</button>
                        <button class="btn-action" style="background: #fee2e2; color: #991b1b;">❌ Reject</button>
                    </div>
                </div>
                
                <div style="padding: 15px; background: #f9fafb; border-radius: 8px; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <div style="font-weight: 600;">Project - E-commerce Website</div>
                        <div style="font-size: 12px; color: #6b7280;">Uploaded: 1 week ago</div>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button class="btn-action" style="background: #d1fae5; color: #065f46;">✅ Approve</button>
                        <button class="btn-action" style="background: #fee2e2; color: #991b1b;">❌ Reject</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// View Student Details
function viewStudent(student) {
    const modal = document.getElementById('studentModal');
    const title = document.getElementById('studentModalTitle');
    const body = document.getElementById('studentModalBody');
    
    title.textContent = `${student.username} - Profile Details`;
    
    body.innerHTML = `
        <div class="modal-section">
            <h3>Personal Information</h3>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Full Name</div>
                    <div class="info-value">${student.full_name || 'Not set'}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Email</div>
                    <div class="info-value">${student.email}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Phone</div>
                    <div class="info-value">${student.phone || 'Not set'}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Branch</div>
                    <div class="info-value">${student.branch || 'Not set'}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Semester</div>
                    <div class="info-value">${student.semester || 'Not set'}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">CGPA</div>
                    <div class="info-value">${student.cgpa || 'Not set'}</div>
                </div>
            </div>
        </div>
        
        <div class="modal-section">
            <h3>Placement Readiness</h3>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Profile Completion</div>
                    <div class="info-value">${student.profile_completion}%</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Status</div>
                    <div class="info-value">
                        <span class="status-badge status-${student.readiness.toLowerCase().replace(' ', '-')}">
                            ${student.readiness}
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="modal-section">
            <h3>Quick Actions</h3>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button class="btn-action btn-feedback" onclick="openFeedbackModal(${student.id}, '${student.username}')">
                    💬 Give Feedback
                </button>
                <button class="btn-action btn-verify" onclick="openVerifyModal(${student.id})">
                    ✅ Verify Documents
                </button>
                <button class="btn-action" style="background: #dbeafe; color: #1e40af;" onclick="generateStudentReport(${student.id})">
                    📊 Generate Report
                </button>
            </div>
        </div>
    `;
    
    modal.classList.add('active');
}

// Open Feedback Modal
function openFeedbackModal(studentId, studentName) {
    document.getElementById('feedbackStudentId').value = studentId;
    document.getElementById('feedbackStudentName').value = studentName;
    closeModal('studentModal'); // Close student modal if open
    document.getElementById('feedbackModal').classList.add('active');
}

// Open Verify Modal
function openVerifyModal(studentId) {
    closeModal('studentModal'); // Close student modal if open
    document.getElementById('verifyModal').classList.add('active');
}

// Open Broadcast Modal
function openBroadcastModal() {
    document.getElementById('broadcastModal').classList.add('active');
}

// Close Modal
function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

// Filter Students
function filterStudents() {
    const searchText = document.getElementById('searchStudent').value.toLowerCase();
    const readiness = document.getElementById('filterReadiness').value;
    const cgpaRange = document.getElementById('filterCGPA').value;
    const branch = document.getElementById('filterBranch').value;
    
    const rows = document.querySelectorAll('.student-row');
    
    rows.forEach(row => {
        const name = row.dataset.name;
        const studentReadiness = row.dataset.readiness;
        const studentCGPA = parseFloat(row.dataset.cgpa);
        const studentBranch = row.dataset.branch;
        
        let show = true;
        
        // Search filter
        if (searchText && !name.includes(searchText)) {
            show = false;
        }
        
        // Readiness filter
        if (readiness && studentReadiness !== readiness) {
            show = false;
        }
        
        // CGPA filter
        if (cgpaRange) {
            const [min, max] = cgpaRange.split('-').map(Number);
            if (studentCGPA < min || studentCGPA >= max) {
                show = false;
            }
        }
        
        // Branch filter
        if (branch && studentBranch !== branch) {
            show = false;
        }
        
        row.style.display = show ? '' : 'none';
    });
}

// Submit Feedback Form
document.getElementById('feedbackForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const studentId = document.getElementById('feedbackStudentId').value;
    const type = document.getElementById('feedbackType').value;
    const rating = document.getElementById('feedbackRating').value;
    const remarks = document.getElementById('feedbackRemarks').value;
    const nextSteps = document.getElementById('feedbackNextSteps').value;
    
    // Here you would send this to the server
    alert(`Feedback submitted for student ID: ${studentId}\nType: ${type}\nRating: ${rating} stars`);
    
    closeModal('feedbackModal');
    this.reset();
});

// Submit Broadcast Form
document.getElementById('broadcastForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const subject = document.getElementById('broadcastSubject').value;
    const message = document.getElementById('broadcastMessage').value;
    
    alert(`Broadcasting message to all students:\nSubject: ${subject}\n\nThis would send an email/notification to all students.`);
    
    closeModal('broadcastModal');
    this.reset();
});

// Generate Report
function generateReport() {
    alert('Generating comprehensive placement progress report...\n\nThis would export an Excel/PDF report with:\n- Student-wise progress\n- Department analytics\n- Readiness statistics\n- Performance trends');
}

// AI Mentor Assistant
function aiMentorAssistant() {
    alert('🤖 AI Mentor Assistant\n\nThis feature would:\n- Analyze student test data\n- Generate improvement reports\n- Recommend personalized next steps\n- Provide AI-powered insights');
}

// Generate Student Report
function generateStudentReport(studentId) {
    alert(`Generating individual report for student ID: ${studentId}\n\nThis would include:\n- Academic performance\n- Skill assessments\n- Interview performance\n- Placement readiness score`);
}

// View Job Applied Students
function viewJobAppliedStudents() {
    window.location.href = 'job_applied_students.php';
}

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
