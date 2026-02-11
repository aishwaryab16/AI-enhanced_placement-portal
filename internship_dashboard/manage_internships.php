<?php
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['placement_officer', 'admin'], true)) {
    header('Location: ../login.php');
    exit;
}

$mysqli = $GLOBALS['mysqli'] ?? null;
if (!$mysqli) {
    die("Database connection not available. Please check config.php");
}

$message = '';
$messageType = '';

// Handle internship posting
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_internship'])) {
    $company = trim($_POST['company'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $stipend_min = trim($_POST['stipend_min'] ?? '');
    $stipend_max = trim($_POST['stipend_max'] ?? '');
    $skills_required = trim($_POST['skills_required'] ?? '');
    $min_cgpa = trim($_POST['min_cgpa'] ?? '');
    $eligible_years = trim($_POST['eligible_years'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $duration = trim($_POST['duration'] ?? '');
    $start_date = trim($_POST['start_date'] ?? '');
    $deadline = trim($_POST['deadline'] ?? '');
    $apply_link = trim($_POST['apply_link'] ?? '');

    if (!$company || !$role || !$description) {
        $message = "Company, role, and description are required fields!";
        $messageType = "error";
    } else {
        $stipend_min_val = !empty($stipend_min) ? (float)$stipend_min : null;
        $stipend_max_val = !empty($stipend_max) ? (float)$stipend_max : null;
        $min_cgpa_val = !empty($min_cgpa) ? (float)$min_cgpa : null;
        
        $stmt = $mysqli->prepare("INSERT INTO internship_opportunities
            (company, role, location, stipend_min, stipend_max, skills_required, min_cgpa, eligible_years, description, duration, start_date, deadline, apply_link, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        if ($stmt) {
            $stmt->bind_param(
                "sssddssdsssss",
                $company, $role, $location, $stipend_min_val, $stipend_max_val, $skills_required, $min_cgpa_val, $eligible_years,
                $description, $duration, $start_date, $deadline, $apply_link
            );
            if ($stmt->execute()) {
                $message = "Internship posted successfully!";
                $messageType = "success";
                header('Location: manage_internships.php?msg=internship_posted');
                exit;
            } else {
                $message = "Error posting internship: " . $stmt->error;
                $messageType = "error";
            }
            $stmt->close();
        } else {
            $message = "Error preparing query: " . $mysqli->error;
            $messageType = "error";
        }
    }
}

// Handle internship deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_internship'])) {
    $internship_id_raw = $_POST['internship_id'] ?? '';
    if ($internship_id_raw === '' || !ctype_digit((string)$internship_id_raw)) {
        $message = "Unable to delete internship. Please refresh and try again.";
        $messageType = "error";
    } else {
        $internship_id = (int)$internship_id_raw;
        $stmt = $mysqli->prepare("DELETE FROM internship_opportunities WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $internship_id);
            if ($stmt->execute()) {
                $message = "Internship deleted successfully!";
                $messageType = "success";
                $stmt->close();
                header('Location: manage_internships.php?msg=internship_deleted');
                exit;
            } else {
                $message = "Error deleting internship: " . $stmt->error;
                $messageType = "error";
                $stmt->close();
            }
        } else {
            $message = "Unable to prepare delete statement: " . $mysqli->error;
            $messageType = "error";
        }
    }
}

// Handle internship update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_internship'])) {
    $internship_id = (int)($_POST['internship_id'] ?? 0);
    $company = trim($_POST['company'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $stipend_min = trim($_POST['stipend_min'] ?? '');
    $stipend_max = trim($_POST['stipend_max'] ?? '');
    $skills_required = trim($_POST['skills_required'] ?? '');
    $min_cgpa = trim($_POST['min_cgpa'] ?? '');
    $eligible_years = trim($_POST['eligible_years'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $duration = trim($_POST['duration'] ?? '');
    $start_date = trim($_POST['start_date'] ?? '');
    $deadline = trim($_POST['deadline'] ?? '');
    $apply_link = trim($_POST['apply_link'] ?? '');

    if (!$company || !$role || !$description) {
        $message = "Company, role, and description are required fields!";
        $messageType = "error";
    } else {
        $stipend_min_val = !empty($stipend_min) ? (float)$stipend_min : null;
        $stipend_max_val = !empty($stipend_max) ? (float)$stipend_max : null;
        $min_cgpa_val = !empty($min_cgpa) ? (float)$min_cgpa : null;
        
        $stmt = $mysqli->prepare("UPDATE internship_opportunities SET company=?, role=?, location=?, stipend_min=?, stipend_max=?, skills_required=?, min_cgpa=?, eligible_years=?, description=?, duration=?, start_date=?, deadline=?, apply_link=? WHERE id=?");
        if ($stmt) {
            $stmt->bind_param(
                "sssddssdsssssi",
                $company, $role, $location, $stipend_min_val, $stipend_max_val, $skills_required, $min_cgpa_val, $eligible_years,
                $description, $duration, $start_date, $deadline, $apply_link, $internship_id
            );
            if ($stmt->execute()) {
                $message = "Internship updated successfully!";
                $messageType = "success";
                $stmt->close();
                header('Location: manage_internships.php?msg=internship_updated');
                exit;
            } else {
                $message = "Error updating internship: " . $stmt->error;
                $messageType = "error";
                $stmt->close();
            }
        } else {
            $message = "Error preparing query: " . $mysqli->error;
            $messageType = "error";
        }
    }
}

// Fetch all internships
$internships = [];
$internship_query = $mysqli->query("SELECT * FROM internship_opportunities ORDER BY created_at DESC");
if ($internship_query) {
    $internships = $internship_query->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Internships</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/fontawesome-all.min.css">
    <style>
        .internship-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            position: relative;
        }
        .page-header { margin-bottom: 30px; }
        .page-header h1 { color: #5b1f1f; font-size: 28px; margin: 0; }
        .floating-btn {
            position: fixed; bottom: 30px; right: 30px; width: 60px; height: 60px;
            border-radius: 50%; background: linear-gradient(135deg,#5b1f1f,#8b3a3a);
            color: white; border: none; font-size: 24px; cursor: pointer; box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            display: flex; align-items: center; justify-content: center; z-index: 1000; transition: all 0.3s;
        }
        .floating-btn:hover { background: linear-gradient(135deg,#8b3a3a,#5b1f1f); transform: scale(1.1); box-shadow: 0 6px 12px rgba(0,0,0,0.25); }
        .internships-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px;
        }
        .internship-card {
            background: white; border-radius: 16px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            overflow: hidden; transition: transform 0.3s, box-shadow 0.3s; display: flex;
            flex-direction: column; height: 100%; border: 1px solid #e5e7eb;
        }
        .internship-card:hover { transform: translateY(-5px); box-shadow: 0 8px 16px rgba(0,0,0,0.15); }
        .intern-header { padding: 20px; border-bottom: 1px solid #eee; background-color: #f9f9f9; }
        .internship-title { color: #5b1f1f; font-size: 20px; margin: 0 0 5px 0; }
        .internship-company { color: #333; font-size: 16px; font-weight: 500; margin-bottom: 10px; }
        .internship-role { font-size: 14px; color: #666; font-weight: 500; margin-bottom: 8px; }
        .internship-details, .internship-description { padding: 0 20px; }
        .internship-details { margin-top: 10px; margin-bottom: 10px; }
        .internship-description { margin: 15px 0; color: #555; line-height: 1.5; font-size: 14px; max-height: 100px; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 4;-webkit-box-orient: vertical; }
        .internship-footer { padding: 15px 20px; background-color: #f9f9f9; border-top: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
        .actions { display: flex; gap: 10px; }
        .btn-edit, .btn-delete {
            padding: 6px 12px; border: none; border-radius: 6px; cursor: pointer;
            font-size: 14px; display: inline-flex; align-items: center; gap: 5px;
        }
        .btn-edit { background-color: #5b1f1f; color: white; }
        .btn-edit:hover { background-color: #8b3a3a; }
        .btn-delete { background-color: #f44336; color: white; }
        .btn-delete:hover { background-color: #d32f2f; }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background: white;
            border-radius: 16px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            position: relative;
        }
        .modal-header {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #5b1f1f;
            color: white;
            border-radius: 16px 16px 0 0;
        }
        .modal-header h2 {
            margin: 0;
            font-size: 20px;
            color: white;
        }
        .close {
            font-size: 24px;
            cursor: pointer;
            color: white;
        }
        .close:hover { color: #f0f0f0; }
        .modal-body { padding: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #444;
        }
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        .form-control:focus {
            border-color: #5b1f1f;
            outline: none;
            box-shadow: 0 0 0 2px rgba(91, 31, 31, 0.1);
        }
        textarea.form-control { min-height: 100px; resize: vertical; }
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding: 15px 20px;
            border-top: 1px solid #eee;
            background-color: #f9f9f9;
            border-bottom-left-radius: 16px;
            border-bottom-right-radius: 16px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary {
            background: linear-gradient(135deg,#5b1f1f,#8b3a3a);
            color: white;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg,#8b3a3a,#5b1f1f);
            transform: translateY(-2px);
        }
        .btn-secondary {
            background-color: #9e9e9e;
            color: white;
        }
        .btn-secondary:hover {
            background-color: #757575;
            transform: translateY(-2px);
        }
        .alert {
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success {
            background-color: #e8f5e9;
            color: #2e7d32;
            border-left: 4px solid #4CAF50;
        }
        .alert-error {
            background-color: #ffebee;
            color: #c62828;
            border-left: 4px solid #f44336;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/partials/header.php'; ?>
    <div class="internship-container">
        <div class="page-header">
            <h1>📝 Internship Opportunities</h1>
        </div>
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType === 'error' ? 'error' : 'success'; ?>">
                <i class="fas <?php echo $messageType === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        <div class="internships-grid">
            <?php if (empty($internships)): ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 40px; background: #f8f9fa; border-radius: 16px; margin-top: 20px;">
                    <p style="color: #6c757d; font-size: 16px; margin: 0;">No internships posted yet. Click the + button to add a new internship.</p>
                </div>
            <?php else: ?>
                <?php foreach ($internships as $internship): ?>
                    <div class="internship-card">
                        <div class="intern-header">
                            <h3 class="internship-title"><?= htmlspecialchars($internship['role'] ?? 'No Title'); ?></h3>
                            <p class="internship-company"><?= htmlspecialchars($internship['company']); ?></p>
                        </div>
                        <div class="internship-details">
                            <?php if (!empty($internship['location'])): ?>
                                <div><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($internship['location']); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($internship['stipend_min']) || !empty($internship['stipend_max'])): ?>
                                <div><i class="fas fa-rupee-sign"></i> <?= 'Rs ' . number_format($internship['stipend_min'],0) . ' - ' . number_format($internship['stipend_max'],0); ?>/month</div>
                            <?php endif; ?>
                            <?php if (!empty($internship['duration'])): ?>
                                <div><i class="fas fa-hourglass"></i> <?= htmlspecialchars($internship['duration']); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($internship['deadline'])): ?>
                                <div><i class="fas fa-calendar-alt"></i> Apply by: <?= htmlspecialchars($internship['deadline']); ?></div>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($internship['description'])): ?>
                            <div class="internship-description">
                                <?= nl2br(htmlspecialchars(substr($internship['description'], 0, 200) . (strlen($internship['description']) > 200 ? '...' : ''))); ?>
                            </div>
                        <?php endif; ?>
                        <div class="internship-footer">
                            <span class="posted-date"><i class="far fa-clock"></i> Posted on <?= date('M d, Y', strtotime($internship['created_at'])); ?></span>
                            <div class="actions">
                                <button class="btn-edit" onclick="editInternship(<?= htmlspecialchars(json_encode($internship)); ?>)"><i class="fas fa-edit"></i> Edit</button>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this internship?');">
                                    <input type="hidden" name="internship_id" value="<?= htmlspecialchars($internship['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <button type="submit" name="delete_internship" class="btn-delete"><i class="fas fa-trash"></i> Delete</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <!-- Floating action button for adding new internships -->
        <button class="floating-btn" onclick="openAddInternshipModal()"><i class="fas fa-plus"></i></button>
    </div>

    <!-- Add Internship Modal -->
    <div id="addInternshipModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Post a New Internship</h2>
                <span class="close" onclick="closeModal('addInternshipModal')">&times;</span>
            </div>
            <form method="post" action="">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="company">Company Name *</label>
                        <input type="text" id="company" name="company" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="role">Internship Role *</label>
                        <input type="text" id="role" name="role" class="form-control" required placeholder="e.g., Software Developer Intern, Data Science Intern">
                    </div>
                    <div class="form-group">
                        <label for="location">Location</label>
                        <input type="text" id="location" name="location" class="form-control" placeholder="e.g., Bangalore, Remote">
                    </div>
                    <div class="form-group">
                        <label for="stipend_min">Stipend (Min) - Rs</label>
                        <input type="number" id="stipend_min" name="stipend_min" class="form-control" step="0.01" placeholder="e.g., 20000">
                    </div>
                    <div class="form-group">
                        <label for="stipend_max">Stipend (Max) - Rs</label>
                        <input type="number" id="stipend_max" name="stipend_max" class="form-control" step="0.01" placeholder="e.g., 30000">
                    </div>
                    <div class="form-group">
                        <label for="duration">Duration</label>
                        <input type="text" id="duration" name="duration" class="form-control" placeholder="e.g., 3 months, 6 months">
                    </div>
                    <div class="form-group">
                        <label for="start_date">Start Date</label>
                        <input type="date" id="start_date" name="start_date" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="deadline">Application Deadline</label>
                        <input type="date" id="deadline" name="deadline" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="min_cgpa">Minimum CGPA</label>
                        <input type="number" id="min_cgpa" name="min_cgpa" class="form-control" step="0.01" min="0" max="10" placeholder="e.g., 7.5">
                    </div>
                    <div class="form-group">
                        <label for="eligible_years">Eligible Years</label>
                        <input type="text" id="eligible_years" name="eligible_years" class="form-control" placeholder="e.g., 2nd, 3rd, 4th year">
                    </div>
                    <div class="form-group">
                        <label for="skills_required">Required Skills (comma-separated)</label>
                        <input type="text" id="skills_required" name="skills_required" class="form-control" placeholder="e.g., Python, Java, SQL, HTML">
                    </div>
                    <div class="form-group">
                        <label for="description">Description *</label>
                        <textarea id="description" name="description" class="form-control" rows="5" required placeholder="Provide a detailed description of the internship position, responsibilities, and expectations."></textarea>
                    </div>
                    <div class="form-group">
                        <label for="apply_link">Apply Link (Optional)</label>
                        <input type="url" id="apply_link" name="apply_link" class="form-control" placeholder="https://company.com/apply">
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addInternshipModal')">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" name="post_internship" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Post Internship
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Internship Modal -->
    <div id="editInternshipModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Internship Posting</h2>
                <span class="close" onclick="closeModal('editInternshipModal')">&times;</span>
            </div>
            <form method="post" action="">
                <input type="hidden" id="edit_internship_id" name="internship_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit_company">Company Name *</label>
                        <input type="text" id="edit_company" name="company" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_role">Internship Role *</label>
                        <input type="text" id="edit_role" name="role" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_location">Location</label>
                        <input type="text" id="edit_location" name="location" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="edit_stipend_min">Stipend (Min) - Rs</label>
                        <input type="number" id="edit_stipend_min" name="stipend_min" class="form-control" step="0.01">
                    </div>
                    <div class="form-group">
                        <label for="edit_stipend_max">Stipend (Max) - Rs</label>
                        <input type="number" id="edit_stipend_max" name="stipend_max" class="form-control" step="0.01">
                    </div>
                    <div class="form-group">
                        <label for="edit_duration">Duration</label>
                        <input type="text" id="edit_duration" name="duration" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="edit_start_date">Start Date</label>
                        <input type="date" id="edit_start_date" name="start_date" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="edit_deadline">Application Deadline</label>
                        <input type="date" id="edit_deadline" name="deadline" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="edit_min_cgpa">Minimum CGPA</label>
                        <input type="number" id="edit_min_cgpa" name="min_cgpa" class="form-control" step="0.01" min="0" max="10">
                    </div>
                    <div class="form-group">
                        <label for="edit_eligible_years">Eligible Years</label>
                        <input type="text" id="edit_eligible_years" name="eligible_years" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="edit_skills_required">Required Skills (comma-separated)</label>
                        <input type="text" id="edit_skills_required" name="skills_required" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="edit_description">Description *</label>
                        <textarea id="edit_description" name="description" class="form-control" rows="5" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="edit_apply_link">Apply Link (Optional)</label>
                        <input type="url" id="edit_apply_link" name="apply_link" class="form-control">
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editInternshipModal')">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" name="update_internship" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Internship
                    </button>
                </div>
            </form>
    </div>
</div>

    <script>
    // Open Add Internship Modal
    function openAddInternshipModal() {
        document.getElementById('addInternshipModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
        
        // Set default deadline to 30 days from now
        const today = new Date();
        const futureDate = new Date(today);
        futureDate.setDate(today.getDate() + 30);
        const formattedDate = futureDate.toISOString().split('T')[0];
        document.getElementById('deadline').value = formattedDate;
        
        // Set focus to the first input field
        document.getElementById('company').focus();
    }

    // Open Edit Internship Modal
    function editInternship(internship) {
        document.getElementById('edit_internship_id').value = internship.id || '';
        document.getElementById('edit_company').value = internship.company || '';
        document.getElementById('edit_role').value = internship.role || '';
        document.getElementById('edit_location').value = internship.location || '';
        document.getElementById('edit_stipend_min').value = internship.stipend_min || '';
        document.getElementById('edit_stipend_max').value = internship.stipend_max || '';
        document.getElementById('edit_duration').value = internship.duration || '';
        document.getElementById('edit_min_cgpa').value = internship.min_cgpa || '';
        document.getElementById('edit_eligible_years').value = internship.eligible_years || '';
        document.getElementById('edit_skills_required').value = internship.skills_required || '';
        document.getElementById('edit_description').value = internship.description || '';
        document.getElementById('edit_apply_link').value = internship.apply_link || '';
        
        // Format dates for the date inputs (YYYY-MM-DD)
        if (internship.start_date) {
            const startDate = new Date(internship.start_date);
            const formattedStartDate = startDate.toISOString().split('T')[0];
            document.getElementById('edit_start_date').value = formattedStartDate;
        } else {
            document.getElementById('edit_start_date').value = '';
        }
        
        if (internship.deadline) {
            const deadline = new Date(internship.deadline);
            const formattedDeadline = deadline.toISOString().split('T')[0];
            document.getElementById('edit_deadline').value = formattedDeadline;
        } else {
            document.getElementById('edit_deadline').value = '';
        }
        
        document.getElementById('editInternshipModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
        
        // Set focus to the first input field
        document.getElementById('edit_company').focus();
    }

    // Close any modal
    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    // Close modals when clicking outside
    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    }

    // Close modals with Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            document.querySelectorAll('.modal').forEach(modal => {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            });
        }
    });

    // Show success message if redirected with success parameter
    window.onload = function() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('msg')) {
            const msg = urlParams.get('msg');
            if (msg === 'internship_posted') {
                showMessage('Internship posted successfully!', 'success');
            } else if (msg === 'internship_deleted') {
                showMessage('Internship deleted successfully!', 'success');
            } else if (msg === 'internship_updated') {
                showMessage('Internship updated successfully!', 'success');
            }
            // Remove the parameter from the URL without refreshing
            const newUrl = window.location.pathname;
            window.history.replaceState({}, document.title, newUrl);
        }
    };

    // Helper function to show messages
    function showMessage(message, type) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type}`;
        alertDiv.style.cssText = 'margin: 15px; padding: 12px 20px; border-radius: 8px;';
        alertDiv.style.backgroundColor = type === 'error' ? '#ffebee' : '#e8f5e9';
        alertDiv.style.color = type === 'error' ? '#c62828' : '#2e7d32';
        alertDiv.style.borderLeft = `4px solid ${type === 'error' ? '#f44336' : '#4CAF50'}`;
        alertDiv.style.position = 'fixed';
        alertDiv.style.top = '20px';
        alertDiv.style.right = '20px';
        alertDiv.style.zIndex = '1000';
        alertDiv.style.maxWidth = '400px';
        alertDiv.style.boxShadow = '0 2px 10px rgba(0,0,0,0.1)';
        alertDiv.style.animation = 'slideIn 0.3s ease-out';
        
        const icon = type === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle';
        alertDiv.innerHTML = `<i class="fas ${icon}" style="margin-right: 8px;"></i>${message}`;
        
        document.body.appendChild(alertDiv);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            alertDiv.style.animation = 'fadeOut 0.3s ease-out';
            setTimeout(() => {
                if (document.body.contains(alertDiv)) {
                    document.body.removeChild(alertDiv);
                }
            }, 300);
        }, 5000);
    }

    // Add CSS animations
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }
    `;
    document.head.appendChild(style);
    </script>
    <?php include __DIR__ . '/../includes/partials/footer.php'; ?>
</body>
</html>
