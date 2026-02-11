<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/internship_backend.php';

if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'placement_officer')) {
    header('Location: ../login.php');
    exit;
}

setupInternshipTables($mysqli);

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$isEdit = false;
$fields = [
    'company' => '',
    'role' => '',
    'location' => '',
    'stipend_min' => 0,
    'stipend_max' => 0,
    'skills_required' => '',
    'min_cgpa' => 0,
    'eligible_years' => '',
    'description' => '',
    'duration' => '',
    'start_date' => '',
    'deadline' => '',
];

if ($id) {
    $result = $mysqli->query("SELECT * FROM internship_opportunities WHERE id = $id");
    if ($result && $data = $result->fetch_assoc()) {
        $fields = $data;
        $isEdit = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($fields as $key => $value) {
        if (isset($_POST[$key])) {
            $fields[$key] = htmlspecialchars(trim($_POST[$key]));
        }
    }

    if ($isEdit) {
        $stmt = $mysqli->prepare("
            UPDATE internship_opportunities
            SET company=?, role=?, location=?, stipend_min=?, stipend_max=?, skills_required=?, min_cgpa=?, eligible_years=?, description=?, duration=?, start_date=?, deadline=?
            WHERE id=?
        ");
        $stmt->bind_param(
            'sssddsdsssssi',
            $fields['company'],
            $fields['role'],
            $fields['location'],
            $fields['stipend_min'],
            $fields['stipend_max'],
            $fields['skills_required'],
            $fields['min_cgpa'],
            $fields['eligible_years'],
            $fields['description'],
            $fields['duration'],
            $fields['start_date'],
            $fields['deadline'],
            $id
        );
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $mysqli->prepare("
            INSERT INTO internship_opportunities
            (company, role, location, stipend_min, stipend_max, skills_required, min_cgpa, eligible_years, description, duration, start_date, deadline)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        $stmt->bind_param(
            'sssddsdsssss',
            $fields['company'],
            $fields['role'],
            $fields['location'],
            $fields['stipend_min'],
            $fields['stipend_max'],
            $fields['skills_required'],
            $fields['min_cgpa'],
            $fields['eligible_years'],
            $fields['description'],
            $fields['duration'],
            $fields['start_date'],
            $fields['deadline']
        );
        $stmt->execute();
        $stmt->close();
    }

    header('Location: manage_internships.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isEdit ? 'Edit Internship Posting' : 'Add Internship Posting' ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --brand-primary: #5b1f1f;
            --brand-primary-dark: #3d1414;
            --brand-accent: #e2b458;
            --brand-accent-light: #f0d084;
            --bg-light: #f8f9fa;
            --card-shadow: 0 14px 34px rgba(91, 31, 31, 0.16);
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-light);
            margin: 0;
            padding: 0;
            color: #2f343a;
        }

        .wrapper {
            max-width: 720px;
            margin: 48px auto;
            padding: 40px 44px;
            background: #ffffff;
            border-radius: 18px;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(226, 180, 88, 0.24);
        }

        .wrapper h2 {
            font-size: 1.9rem;
            margin: 0 0 28px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--brand-primary);
        }

        .form-row {
            margin-bottom: 18px;
        }

        .form-row label {
            display: block;
            font-weight: 600;
            margin-bottom: 6px;
            color: var(--brand-primary);
            letter-spacing: 0.3px;
        }

        .form-row input,
        .form-row textarea {
            width: 100%;
            padding: 12px 14px;
            border-radius: 10px;
            border: 1.5px solid rgba(226, 180, 88, 0.55);
            font-size: 15px;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
            background: #fffbf2;
        }

        .form-row input:focus,
        .form-row textarea:focus {
            outline: none;
            border-color: var(--brand-primary);
            box-shadow: 0 0 0 3px rgba(226, 180, 88, 0.35);
        }

        .actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 26px;
        }

        .cancel-btn,
        .save-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 15px;
            border: none;
            cursor: pointer;
            text-decoration: none;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .cancel-btn {
            background: #f0f0f3;
            color: #3c3f44;
        }

        .save-btn {
            background: linear-gradient(135deg, var(--brand-primary) 0%, var(--brand-primary-dark) 100%);
            color: #ffffff;
            box-shadow: 0 12px 24px rgba(91, 31, 31, 0.25);
        }

        .actions a:hover,
        .actions button:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 20px rgba(91, 31, 31, 0.18);
        }

        @media (max-width: 768px) {
            .wrapper {
                margin: 24px 16px;
                padding: 32px;
            }

            .actions {
                flex-direction: column-reverse;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <h2>
            <i class="fas <?= $isEdit ? 'fa-edit' : 'fa-plus' ?>"></i>
            <?= $isEdit ? 'Edit Internship' : 'Add Internship' ?>
        </h2>
        <form method="POST" autocomplete="off">
            <div class="form-row">
                <label for="company">Company</label>
                <input id="company" name="company" required value="<?= htmlspecialchars($fields['company']) ?>">
            </div>
            <div class="form-row">
                <label for="role">Role</label>
                <input id="role" name="role" required value="<?= htmlspecialchars($fields['role']) ?>">
            </div>
            <div class="form-row">
                <label for="location">Location</label>
                <input id="location" name="location" value="<?= htmlspecialchars($fields['location']) ?>">
            </div>
            <div class="form-row">
                <label for="stipend_min">Stipend Min (₹ / month)</label>
                <input id="stipend_min" type="number" min="0" step="0.01" name="stipend_min" value="<?= htmlspecialchars($fields['stipend_min']) ?>">
            </div>
            <div class="form-row">
                <label for="stipend_max">Stipend Max (₹ / month)</label>
                <input id="stipend_max" type="number" min="0" step="0.01" name="stipend_max" value="<?= htmlspecialchars($fields['stipend_max']) ?>">
            </div>
            <div class="form-row">
                <label for="duration">Duration</label>
                <input id="duration" name="duration" value="<?= htmlspecialchars($fields['duration']) ?>">
            </div>
            <div class="form-row">
                <label for="skills_required">Skills Required (comma separated)</label>
                <input id="skills_required" name="skills_required" value="<?= htmlspecialchars($fields['skills_required']) ?>">
            </div>
            <div class="form-row">
                <label for="min_cgpa">Minimum CGPA</label>
                <input id="min_cgpa" type="number" min="0" max="10" step="0.01" name="min_cgpa" value="<?= htmlspecialchars($fields['min_cgpa']) ?>">
            </div>
            <div class="form-row">
                <label for="eligible_years">Eligible Years (comma separated)</label>
                <input id="eligible_years" name="eligible_years" value="<?= htmlspecialchars($fields['eligible_years']) ?>">
            </div>
            <div class="form-row">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="4"><?= htmlspecialchars($fields['description']) ?></textarea>
            </div>
            <div class="form-row">
                <label for="start_date">Start Date</label>
                <input id="start_date" type="date" name="start_date" value="<?= htmlspecialchars($fields['start_date']) ?>">
            </div>
            <div class="form-row">
                <label for="deadline">Deadline</label>
                <input id="deadline" type="date" name="deadline" value="<?= htmlspecialchars($fields['deadline']) ?>">
            </div>
            <div class="actions">
                <a class="cancel-btn" href="manage_internships.php"><i class="fas fa-arrow-left"></i> Cancel</a>
                <button class="save-btn" type="submit"><i class="fas fa-save"></i> Save</button>
            </div>
        </form>
    </div>
</body>
</html> 

