<?php
require_once __DIR__ . '/../includes/config.php';
require_role('admin');

// Fetch pending resume submissions from database
$pending_verifications = [];
$approved_verifications = [];
$rejected_verifications = [];

$checkTable = $mysqli->query("SHOW TABLES LIKE 'resume_submissions'");
if ($checkTable && $checkTable->num_rows > 0) {
    // Fetch pending resumes
    $pending_query = "
        SELECT 
            rs.*,
            u.username as student_name,
            u.email as student_email
        FROM resume_submissions rs
        JOIN users u ON rs.student_id = u.id
        WHERE rs.status = 'pending'
        ORDER BY rs.uploaded_at DESC
    ";
    $result = $mysqli->query($pending_query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $row['ai_analysis'] = json_decode($row['ai_analysis'], true);
            $pending_verifications[] = $row;
        }
    }
    
    // Fetch approved resumes
    $approved_query = "
        SELECT 
            rs.*,
            u.username as student_name,
            u.email as student_email
        FROM resume_submissions rs
        JOIN users u ON rs.student_id = u.id
        WHERE rs.status = 'approved'
        ORDER BY rs.reviewed_at DESC
        LIMIT 10
    ";
    $result = $mysqli->query($approved_query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $row['ai_analysis'] = json_decode($row['ai_analysis'], true);
            $approved_verifications[] = $row;
        }
    }
    
    // Fetch rejected resumes
    $rejected_query = "
        SELECT 
            rs.*,
            u.username as student_name,
            u.email as student_email
        FROM resume_submissions rs
        JOIN users u ON rs.student_id = u.id
        WHERE rs.status = 'rejected'
        ORDER BY rs.reviewed_at DESC
        LIMIT 10
    ";
    $result = $mysqli->query($rejected_query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $row['ai_analysis'] = json_decode($row['ai_analysis'], true);
            $rejected_verifications[] = $row;
        }
    }
}

// If no data, use mock data for demonstration
if (empty($pending_verifications)) {
    $pending_verifications = [
        [
            'id' => 1,
            'student_id' => 1,
            'student_name' => 'John Doe',
            'student_email' => 'john@example.com',
            'file_name' => 'john_doe_resume.pdf',
            'uploaded_at' => '2025-01-08 10:30:00',
            'status' => 'pending',
            'ai_score' => 78,
            'ai_analysis' => [
                'score' => 78,
                'strengths' => [
                    'Clear formatting and professional layout',
                    'Strong technical skills section',
                    'Relevant project experience included'
                ],
                'weaknesses' => [
                    'Missing quantifiable achievements',
                    'No leadership experience mentioned',
                    'Contact information incomplete'
                ],
                'suggestions' => [
                    'Add metrics to project descriptions (e.g., "Improved performance by 40%")',
                    'Include soft skills and certifications',
                    'Add LinkedIn profile link'
                ],
                'recommendation' => 'Approve with minor improvements needed'
            ]
        ]
    ];
}

if (empty($approved_verifications)) {
    $approved_verifications = [
        [
            'id' => 5,
            'student_name' => 'Tom Brown',
            'file_name' => 'tom_resume.pdf',
            'reviewed_at' => '2025-01-05',
            'ai_score' => 85
        ]
    ];
}

if (empty($rejected_verifications)) {
    $rejected_verifications = [];
}

include __DIR__ . '/../includes/partials/header.php';
?>

<style>
.verification-container {
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

.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    padding: 25px;
    border-radius: 16px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    border-left: 4px solid #5b1f1f;
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

.tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 25px;
    border-bottom: 2px solid #e5e7eb;
}

.tab {
    padding: 12px 24px;
    background: none;
    border: none;
    border-bottom: 3px solid transparent;
    cursor: pointer;
    font-weight: 600;
    color: #6b7280;
    transition: all 0.3s;
}

.tab.active {
    color: #5b1f1f;
    border-bottom-color: #5b1f1f;
}

.tab:hover {
    color: #5b1f1f;
}

.verification-grid {
    display: grid;
    gap: 20px;
}

.verification-card {
    background: white;
    padding: 25px;
    border-radius: 16px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: all 0.3s;
}

.verification-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
}

.verification-header {
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
    font-size: 14px;
    color: #6b7280;
}

.document-info {
    background: #f9fafb;
    padding: 15px;
    border-radius: 12px;
    margin-bottom: 20px;
}

.document-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.document-row:last-child {
    margin-bottom: 0;
}

.document-label {
    font-size: 13px;
    color: #6b7280;
    font-weight: 600;
}

.document-value {
    font-size: 14px;
    color: #1f2937;
    font-weight: 500;
}

.action-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    font-size: 14px;
}

.btn-approve {
    background: #d1fae5;
    color: #065f46;
}

.btn-reject {
    background: #fee2e2;
    color: #991b1b;
}

.btn-view {
    background: #dbeafe;
    color: #1e40af;
}

.btn-remarks {
    background: #fef3c7;
    color: #92400e;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.status-badge {
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.status-approved {
    background: #d1fae5;
    color: #065f46;
}

.status-rejected {
    background: #fee2e2;
    color: #991b1b;
}

.status-pending {
    background: #fef3c7;
    color: #92400e;
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
}

.modal.active {
    display: flex;
}

.modal-content {
    background: white;
    padding: 30px;
    border-radius: 16px;
    max-width: 600px;
    width: 90%;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
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
}

.form-textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    min-height: 100px;
    resize: vertical;
}

.btn-submit {
    background: linear-gradient(135deg, #5b1f1f, #ecc35c);
    color: white;
    width: 100%;
}
</style>

<div class="verification-container">
    <div class="page-header">
        <h1 class="page-title">🤖 AI Resume Analyzer - Admin Review</h1>
        <p class="page-subtitle">Review AI-analyzed resumes and approve/reject with feedback</p>
    </div>

    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-value"><?php echo count($pending_verifications); ?></div>
            <div class="stat-label">Pending Verifications</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo count($approved_verifications); ?></div>
            <div class="stat-label">Approved Today</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">0</div>
            <div class="stat-label">Rejected Today</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo count($pending_verifications) + count($approved_verifications); ?></div>
            <div class="stat-label">Total This Week</div>
        </div>
    </div>

    <div class="tabs">
        <button class="tab active" onclick="switchTab('pending')">Pending (<?php echo count($pending_verifications); ?>)</button>
        <button class="tab" onclick="switchTab('approved')">Approved (<?php echo count($approved_verifications); ?>)</button>
        <button class="tab" onclick="switchTab('rejected')">Rejected (0)</button>
    </div>

    <!-- Pending Verifications -->
    <div id="pending-tab" class="verification-grid">
        <?php foreach ($pending_verifications as $verification): ?>
        <div class="verification-card">
            <div class="verification-header">
                <div class="student-info">
                    <div class="student-avatar">
                        <?php echo strtoupper(substr($verification['student_name'], 0, 1)); ?>
                    </div>
                    <div class="student-details">
                        <h3><?php echo htmlspecialchars($verification['student_name']); ?></h3>
                        <p><?php echo htmlspecialchars($verification['student_email']); ?></p>
                    </div>
                </div>
                <span class="status-badge status-pending">Pending</span>
            </div>

            <div class="document-info">
                <div class="document-row">
                    <span class="document-label">AI Score:</span>
                    <span class="document-value">
                        <strong style="font-size: 24px; color: #5b1f1f;"><?php echo $verification['ai_score'] ?? $verification['ai_analysis']['score']; ?>/100</strong>
                    </span>
                </div>
                <div class="document-row">
                    <span class="document-label">File Name:</span>
                    <span class="document-value">📄 <?php echo htmlspecialchars($verification['file_name']); ?></span>
                </div>
                <div class="document-row">
                    <span class="document-label">Uploaded:</span>
                    <span class="document-value">⏰ <?php echo date('M d, Y h:i A', strtotime($verification['uploaded_at'])); ?></span>
                </div>
                <div class="document-row">
                    <span class="document-label">AI Recommendation:</span>
                    <span class="document-value">💡 <?php echo htmlspecialchars($verification['ai_analysis']['recommendation']); ?></span>
                </div>
            </div>

            <?php if (!empty($verification['ai_analysis'])): ?>
            <div style="background: #f9fafb; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                <div style="margin-bottom: 12px;">
                    <strong style="color: #10b981;">✅ Strengths:</strong>
                    <ul style="margin: 8px 0 0 20px; color: #4b5563; font-size: 13px;">
                        <?php foreach ($verification['ai_analysis']['strengths'] as $strength): ?>
                            <li><?php echo htmlspecialchars($strength); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div style="margin-bottom: 12px;">
                    <strong style="color: #ef4444;">⚠️ Weaknesses:</strong>
                    <ul style="margin: 8px 0 0 20px; color: #4b5563; font-size: 13px;">
                        <?php foreach ($verification['ai_analysis']['weaknesses'] as $weakness): ?>
                            <li><?php echo htmlspecialchars($weakness); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div>
                    <strong style="color: #3b82f6;">💡 AI Suggestions:</strong>
                    <ul style="margin: 8px 0 0 20px; color: #4b5563; font-size: 13px;">
                        <?php foreach ($verification['ai_analysis']['suggestions'] as $suggestion): ?>
                            <li><?php echo htmlspecialchars($suggestion); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>

            <div class="action-buttons">
                <button class="btn btn-view" onclick="viewDocument(<?php echo $verification['id']; ?>)">
                    👁️ View Document
                </button>
                <button class="btn btn-approve" onclick="approveDocument(<?php echo $verification['id']; ?>, '<?php echo htmlspecialchars($verification['student_name']); ?>')">
                    ✅ Approve
                </button>
                <button class="btn btn-reject" onclick="openRejectModal(<?php echo $verification['id']; ?>, '<?php echo htmlspecialchars($verification['student_name']); ?>')">
                    ❌ Reject
                </button>
                <button class="btn btn-remarks" onclick="openRemarksModal(<?php echo $verification['id']; ?>)">
                    💬 Add Remarks
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Approved Verifications -->
    <div id="approved-tab" class="verification-grid" style="display: none;">
        <?php foreach ($approved_verifications as $verification): ?>
        <div class="verification-card">
            <div class="verification-header">
                <div class="student-info">
                    <div class="student-avatar">
                        <?php echo strtoupper(substr($verification['student_name'], 0, 1)); ?>
                    </div>
                    <div class="student-details">
                        <h3><?php echo htmlspecialchars($verification['student_name']); ?></h3>
                        <p><?php echo htmlspecialchars($verification['document_type']); ?></p>
                    </div>
                </div>
                <span class="status-badge status-approved">✅ Approved</span>
            </div>

            <div class="document-info">
                <div class="document-row">
                    <span class="document-label">AI Score:</span>
                    <span class="document-value"><strong style="color: #10b981;"><?php echo $verification['ai_score'] ?? 'N/A'; ?>/100</strong></span>
                </div>
                <div class="document-row">
                    <span class="document-label">Approved Date:</span>
                    <span class="document-value"><?php echo date('M d, Y', strtotime($verification['reviewed_at'])); ?></span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Rejected Verifications -->
    <div id="rejected-tab" class="verification-grid" style="display: none;">
        <div style="text-align: center; padding: 60px 20px; color: #9ca3af;">
            <div style="font-size: 48px; margin-bottom: 15px;">📭</div>
            <p>No rejected documents</p>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal" id="rejectModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Reject Document</h2>
            <button class="close-modal" onclick="closeModal('rejectModal')">×</button>
        </div>
        <form id="rejectForm">
            <input type="hidden" id="rejectDocId">
            <div class="form-group">
                <label class="form-label">Student Name</label>
                <input type="text" class="form-textarea" id="rejectStudentName" readonly style="min-height: auto; padding: 10px;">
            </div>
            <div class="form-group">
                <label class="form-label">Reason for Rejection *</label>
                <textarea class="form-textarea" id="rejectReason" required placeholder="Provide detailed reason for rejection..."></textarea>
            </div>
            <button type="submit" class="btn btn-submit">Submit Rejection</button>
        </form>
    </div>
</div>

<!-- Remarks Modal -->
<div class="modal" id="remarksModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Add Remarks</h2>
            <button class="close-modal" onclick="closeModal('remarksModal')">×</button>
        </div>
        <form id="remarksForm">
            <input type="hidden" id="remarksDocId">
            <div class="form-group">
                <label class="form-label">Remarks / Notes</label>
                <textarea class="form-textarea" id="remarksText" placeholder="Add any notes or remarks about this document..."></textarea>
            </div>
            <button type="submit" class="btn btn-submit">Save Remarks</button>
        </form>
    </div>
</div>

<script>
function switchTab(tab) {
    // Hide all tabs
    document.getElementById('pending-tab').style.display = 'none';
    document.getElementById('approved-tab').style.display = 'none';
    document.getElementById('rejected-tab').style.display = 'none';
    
    // Show selected tab
    document.getElementById(tab + '-tab').style.display = 'grid';
    
    // Update tab buttons
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    event.target.classList.add('active');
}

function viewDocument(id) {
    alert('Opening document viewer for ID: ' + id + '\n\nThis would open the document in a new window or embedded viewer.');
}

async function approveDocument(id, studentName) {
    if (confirm('Approve resume for ' + studentName + '?\n\nThe student will be notified via email.')) {
        try {
            const response = await fetch('resume_action_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=approve&resume_id=' + id + '&feedback=Resume approved by admin'
            });
            
            const result = await response.json();
            
            if (result.success) {
                alert('✅ Resume approved successfully!\n\nStudent has been notified.');
                location.reload();
            } else {
                alert('Error: ' + result.message);
            }
        } catch (error) {
            console.error('Approve error:', error);
            alert('Failed to approve resume. Please try again.');
        }
    }
}

function openRejectModal(id, studentName) {
    document.getElementById('rejectDocId').value = id;
    document.getElementById('rejectStudentName').value = studentName;
    document.getElementById('rejectModal').classList.add('active');
}

function openRemarksModal(id) {
    document.getElementById('remarksDocId').value = id;
    document.getElementById('remarksModal').classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

// Form submissions
document.getElementById('rejectForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const id = document.getElementById('rejectDocId').value;
    const reason = document.getElementById('rejectReason').value;
    
    try {
        const response = await fetch('resume_action_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=reject&resume_id=' + id + '&reason=' + encodeURIComponent(reason)
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('❌ Resume rejected.\n\nStudent has been notified with feedback.');
            location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        console.error('Reject error:', error);
        alert('Failed to reject resume. Please try again.');
    }
});

document.getElementById('remarksForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const remarks = document.getElementById('remarksText').value;
    alert('Remarks saved:\n' + remarks);
    closeModal('remarksModal');
});

// Close modal when clicking outside
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});
</script>

<?php include __DIR__ . '/../includes/partials/footer.php'; ?>
