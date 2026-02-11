<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/job_backend.php';

if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'placement_officer' && $_SESSION['role'] !== 'admin')) {
    header('Location: login.php');
    exit;
}

$mysqli = $GLOBALS['mysqli'] ?? new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

// Setup tables
setupJobTables($mysqli);

// Get company_id from GET (first load) or POST (form submission)
$company_id = isset($_POST['company_id']) ? (int)$_POST['company_id'] : (isset($_GET['company_id']) ? (int)$_GET['company_id'] : 0);
$message = '';
$messageType = '';

// Fetch company details
$company = null;
if ($company_id > 0) {
    $stmt = $mysqli->prepare("SELECT * FROM companies WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $company_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $company = $result->fetch_assoc();
        $stmt->close();
    }
}

// If company not found and we're not in POST (form submission), redirect
// But if we're in POST, allow it to show error (company might have been deleted)
if (!$company || $company_id <= 0) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: manage_companies.php');
        exit;
    } else {
        // In POST, set error message
        $message = "Company not found. Please go back and try again.";
        $messageType = "error";
        $company = ['company_name' => 'Unknown Company']; // Set a default to prevent errors
    }
}

// Quick add job: directly insert a basic posting and return
if (isset($_GET['quick']) && $_GET['quick'] == '1') {
    $role = 'Open Position - ' . ($company['industry'] ?? 'Role');
    $location = $company['location'] ?? '';
    $ctc_min = 0.00;
    $ctc_max = 0.00;
    $skills_required = 'Python,Java';
    $min_cgpa = 6.00;
    $eligible_years = '3,4';
    $description = 'Details to be updated.';
    $deadline = date('Y-m-d', strtotime('+30 days'));

    $stmt = $mysqli->prepare("INSERT INTO job_opportunities (company, role, location, ctc_min, ctc_max, skills_required, min_cgpa, eligible_years, description, deadline) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)" );
    if (!$stmt) {
        error_log('Quick Add Job prepare failed: ' . $mysqli->error);
        header('Location: manage_companies.php?added_job=0&err=' . urlencode('Prepare failed: ' . $mysqli->error));
        exit;
    }
    $company_name = $company['company_name'] ?? ($company['name'] ?? '');
    $stmt->bind_param("sssddsdsss", $company_name, $role, $location, $ctc_min, $ctc_max, $skills_required, $min_cgpa, $eligible_years, $description, $deadline);
    $ok = $stmt->execute();
    $stmt->close();
    header('Location: manage_companies.php?added_job=' . ($ok ? '1' : '0') . ($ok ? '' : '&err=' . urlencode($mysqli->error)));
    exit;
} else if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_job'])) {
    // Re-fetch company in case it was lost
    if ($company_id > 0 && (!$company || empty($company['company_name']))) {
        $stmt = $mysqli->prepare("SELECT * FROM companies WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $company_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $company = $result->fetch_assoc();
            $stmt->close();
        }
    }
    
    // Debug: Log form submission
    error_log("Form submitted - company_id: " . ($_POST['company_id'] ?? 'not set') . ", add_job: " . (isset($_POST['add_job']) ? 'set' : 'not set'));
    
    // Validate required fields
    $role = trim($_POST['role'] ?? '');
    if (empty($role)) {
        $message = "Job Role/Title is required.";
        $messageType = "error";
    } else {
        $location = trim($_POST['location'] ?? ($company['location'] ?? ''));
        
        // Handle numeric fields - ensure they are provided and valid
        $ctc_min = isset($_POST['ctc_min']) && $_POST['ctc_min'] !== '' ? (float)$_POST['ctc_min'] : null;
        $ctc_max = isset($_POST['ctc_max']) && $_POST['ctc_max'] !== '' ? (float)$_POST['ctc_max'] : null;
        $min_cgpa = isset($_POST['min_cgpa']) && $_POST['min_cgpa'] !== '' ? (float)$_POST['min_cgpa'] : null;
        
        // Validate required numeric fields
        if ($ctc_min === null || $ctc_min < 0) {
            $message = "CTC Min is required and must be 0 or greater.";
            $messageType = "error";
        } elseif ($ctc_max === null || $ctc_max < 0) {
            $message = "CTC Max is required and must be 0 or greater.";
            $messageType = "error";
        } elseif ($min_cgpa === null || $min_cgpa < 0 || $min_cgpa > 10) {
            $message = "Minimum CGPA is required and must be between 0 and 10.";
            $messageType = "error";
        } else {
            $skills_required = trim($_POST['skills_required'] ?? '');
            if (empty($skills_required)) {
                $message = "Required Skills is required.";
                $messageType = "error";
            } else {
                $eligible_years = trim($_POST['eligible_years'] ?? '3,4');
                $description = trim($_POST['description'] ?? '');
                $deadline = isset($_POST['deadline']) && $_POST['deadline'] !== '' ? $_POST['deadline'] : null;
                
                $stmt = $mysqli->prepare("INSERT INTO job_opportunities (company, role, location, ctc_min, ctc_max, skills_required, min_cgpa, eligible_years, description, deadline) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)" );
                if (!$stmt) {
                    $message = "Error preparing insert: " . $mysqli->error;
                    $messageType = "error";
                } else {
                    // Get company name from the fetched company record
                    $company_name = $company['company_name'] ?? ($company['name'] ?? '');
                    
                    // Validate company name exists
                    if (empty($company_name)) {
                        $message = "Error: Company name not found. Please go back and try again.";
                        $messageType = "error";
                    } else {
                        // Debug: Log which company is being used
                        error_log("Adding job for company_id: {$company_id}, company_name: {$company_name}");
                        
                        $stmt->bind_param("sssddsdsss", $company_name, $role, $location, $ctc_min, $ctc_max, $skills_required, $min_cgpa, $eligible_years, $description, $deadline);
                    
                        if ($stmt->execute()) {
                            $message = "Job posting added successfully! It will now be visible to students.";
                            $messageType = "success";
                            // Clear POST data to reset form on success
                            $_POST = [];
                            // Redirect to prevent form resubmission
                            header('Location: manage_companies.php?added_job=1');
                            exit;
                        } else {
                            $message = "Error adding job posting: " . $stmt->error;
                            $messageType = "error";
                        }
                        $stmt->close();
                    }
                }
            }
        }
    }
}
?>
<?php include __DIR__ . '/../includes/partials/header.php'; ?>

<style>
.job-posting-container {
    max-width: 800px;
    margin: 30px auto;
    padding: 20px;
}

.page-header {
    background: linear-gradient(135deg, #5b1f1f, #ecc35c);
    color: white;
    padding: 30px;
    border-radius: 15px;
    margin-bottom: 30px;
}

.page-header h1 {
    margin: 0 0 10px 0;
    font-size: 28px;
}

.page-header p {
    margin: 0;
    opacity: 0.9;
}

.form-container {
    background: white;
    padding: 30px;
    border-radius: 15px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #333;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 12px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 14px;
}

.form-group textarea {
    min-height: 100px;
    resize: vertical;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.btn-submit {
    background: #5b1f1f;
    color: white;
    padding: 14px 28px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    font-size: 16px;
    width: 100%;
}

.btn-submit:hover {
    background: #ecc35c;
    color: #5b1f1f;
}

.btn-back {
    background: #6c757d;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    text-decoration: none;
    display: inline-block;
    margin-bottom: 20px;
}

.btn-back:hover {
    background: #5a6268;
}

.alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}
</style>

<div class="job-posting-container">
    <a href="manage_companies.php" class="btn-back">← Back to Companies</a>
    
    <div class="page-header">
        <h1>➕ Add Job Posting</h1>
        <p>Add a job opportunity for <strong><?php echo htmlspecialchars($company['company_name']); ?></strong></p>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="form-container">
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?company_id=<?php echo $company_id; ?>">
            <input type="hidden" name="company_id" value="<?php echo $company_id; ?>">
            <div class="form-group">
                <label>Job Role/Title *</label>
                <input type="text" name="role" required placeholder="e.g., Software Engineer, Data Analyst" value="<?php echo htmlspecialchars($_POST['role'] ?? ''); ?>">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Location</label>
                    <input type="text" name="location" value="<?php echo htmlspecialchars($_POST['location'] ?? ($company['location'] ?? '')); ?>" placeholder="e.g., Bangalore">
                </div>
                <div class="form-group">
                    <label>Application Deadline</label>
                    <input type="date" name="deadline" value="<?php echo htmlspecialchars($_POST['deadline'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>CTC Min (LPA) *</label>
                    <input type="number" name="ctc_min" step="0.01" min="0" placeholder="e.g., 5.00" value="<?php echo htmlspecialchars($_POST['ctc_min'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label>CTC Max (LPA) *</label>
                    <input type="number" name="ctc_max" step="0.01" min="0" placeholder="e.g., 10.00" value="<?php echo htmlspecialchars($_POST['ctc_max'] ?? ''); ?>" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Minimum CGPA *</label>
                    <input type="number" name="min_cgpa" step="0.01" min="0" max="10" placeholder="e.g., 7.00" value="<?php echo htmlspecialchars($_POST['min_cgpa'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label>Eligible Years *</label>
                    <select name="eligible_years" required>
                        <option value="2,3" <?php echo (($_POST['eligible_years'] ?? '3,4') == '2,3') ? 'selected' : ''; ?>>2nd, 3rd Year</option>
                        <option value="3,4" <?php echo (($_POST['eligible_years'] ?? '3,4') == '3,4') ? 'selected' : ''; ?>>3rd, 4th Year</option>
                        <option value="4" <?php echo (($_POST['eligible_years'] ?? '3,4') == '4') ? 'selected' : ''; ?>>4th Year Only</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label>Required Skills (comma-separated) *</label>
                <input type="text" name="skills_required" placeholder="e.g., Python, Java, React, SQL" value="<?php echo htmlspecialchars($_POST['skills_required'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label>Job Description</label>
                <textarea name="description" placeholder="Detailed job description..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
            </div>
            
            <button type="submit" name="add_job" class="btn-submit" id="submitBtn">Add Job Posting</button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const submitBtn = document.getElementById('submitBtn');
    
    if (form && submitBtn) {
        // Log when form is submitted
        form.addEventListener('submit', function(e) {
            console.log('Form submitting...');
            console.log('Company ID:', document.querySelector('input[name="company_id"]')?.value);
            console.log('Form data:', new FormData(form));
        });
        
        // Log when button is clicked
        submitBtn.addEventListener('click', function(e) {
            console.log('Submit button clicked');
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/partials/footer.php'; ?>

