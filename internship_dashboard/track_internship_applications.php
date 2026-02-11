<?php
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'placement_officer')) {
    header('Location: ../login.php');
    exit;
}

$internshipId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$actionMap = [
    'shortlist' => 'Shortlisted',
    'select' => 'Selected',
    'reject' => 'Rejected',
];

foreach ($actionMap as $param => $status) {
    if (isset($_GET[$param]) && (int) $_GET[$param] > 0) {
        $applicationId = (int) $_GET[$param];
        $stmt = $mysqli->prepare("UPDATE internship_applications SET application_status = ? WHERE id = ?");
        $stmt->bind_param('si', $status, $applicationId);
        $stmt->execute();
        $stmt->close();

        $redirect = 'track_internship_applications.php';
        if ($internshipId) {
            $redirect .= '?id=' . $internshipId;
        }
        header('Location: ' . $redirect);
        exit;
    }
}

// Build filter clause safely
$filterClause = '';
if ($internshipId > 0) {
    $filterClause = 'WHERE ia.internship_id = ' . (int)$internshipId;
}

// Use a more flexible JOIN that handles case sensitivity and whitespace
$applicationsQuery = "
    SELECT 
        ia.id,
        ia.internship_id,
        ia.username,
        ia.internship_title,
        ia.company_name,
        ia.internship_role,
        ia.location,
        ia.stipend_range,
        ia.min_cgpa,
        ia.required_skills,
        ia.match_percentage,
        ia.application_status,
        ia.applied_at,
        ia.updated_at,
        ia.resume_path,
        ia.resume_json,
        u.full_name,
        u.branch,
        u.semester,
        u.cgpa,
        u.email,
        u.resume_link,
        COALESCE(io.company, ia.company_name) as company,
        COALESCE(io.role, ia.internship_role) as role,
        io.id AS opportunity_id
    FROM internship_applications ia
    LEFT JOIN users u ON u.username = ia.username
    LEFT JOIN internship_opportunities io ON io.id = ia.internship_id
    $filterClause
    ORDER BY ia.applied_at DESC
";

$applications = $mysqli->query($applicationsQuery);

// Debug: Check for query errors
if (!$applications) {
    error_log("Query error in track_internship_applications.php: " . $mysqli->error);
    // Show error in page for debugging
    $query_error = $mysqli->error;
}

$opportunities = $mysqli->query("SELECT id, company, role FROM internship_opportunities ORDER BY created_at DESC");

// Diagnostic: Check total applications in table (needed for diagnostic display)
$total_check = $mysqli->query("SELECT COUNT(*) as total FROM internship_applications");
$total_count = 0;
if ($total_check) {
    $total_row = $total_check->fetch_assoc();
    $total_count = (int)($total_row['total'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Internship Applications</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --brand-primary: #5b1f1f;
            --brand-primary-dark: #3d1414;
            --brand-accent: #e2b458;
            --brand-accent-light: #f0d084;
            --bg-light: #f8f9fa;
            --card-shadow: 0 14px 32px rgba(91, 31, 31, 0.12);
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-light);
            margin: 0;
            color: #2f353d;
        }

        .container {
            max-width: 1200px;
            margin: 48px auto;
            padding: 36px 40px 44px;
            background: #ffffff;
            border-radius: 18px;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(226, 180, 88, 0.24);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 28px;
        }

        .header h2 {
            margin: 0;
            font-size: 1.8rem;
            color: var(--brand-primary);
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            align-items: center;
            margin-bottom: 26px;
        }

        .filter-bar select,
        .filter-bar button {
            padding: 10px 16px;
            border-radius: 10px;
            border: 1.5px solid rgba(226, 180, 88, 0.4);
            font-size: 0.95rem;
            background: #fffbf2;
        }

        .filter-bar select:focus {
            outline: none;
            border-color: var(--brand-primary);
            box-shadow: 0 0 0 3px rgba(226, 180, 88, 0.28);
        }

        .filter-bar button {
            cursor: pointer;
            background: linear-gradient(135deg, var(--brand-primary) 0%, var(--brand-primary-dark) 100%);
            color: #ffffff;
            border: none;
            font-weight: 600;
            box-shadow: 0 12px 24px rgba(91, 31, 31, 0.2);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .filter-bar button:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 26px rgba(91, 31, 31, 0.24);
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        table {
            width: 100%;
            min-width: 1200px;
            border-collapse: collapse;
            border-radius: 16px;
            overflow: hidden;
            background: #fffdf7;
            border: 1px solid rgba(226, 180, 88, 0.22);
        }

        thead {
            background: linear-gradient(135deg, rgba(226, 180, 88, 0.95) 0%, rgba(240, 208, 132, 0.9) 100%);
            color: #321a1a;
        }

        th, td {
            padding: 12px 14px;
            text-align: left;
            font-size: 0.9rem;
            white-space: nowrap;
        }

        th:nth-child(1), td:nth-child(1) { min-width: 120px; } /* Full Name */
        th:nth-child(2), td:nth-child(2) { min-width: 100px; } /* Username */
        th:nth-child(3), td:nth-child(3) { min-width: 120px; } /* Branch */
        th:nth-child(4), td:nth-child(4) { min-width: 80px; } /* Semester */
        th:nth-child(5), td:nth-child(5) { min-width: 70px; } /* CGPA */
        th:nth-child(6), td:nth-child(6) { min-width: 100px; } /* Company */
        th:nth-child(7), td:nth-child(7) { min-width: 140px; } /* Job Role */
        th:nth-child(8), td:nth-child(8) { min-width: 110px; } /* Applied On */
        th:nth-child(9), td:nth-child(9) { min-width: 120px; white-space: normal; } /* Resume */
        th:nth-child(10), td:nth-child(10) { min-width: 100px; } /* Status */
        th:nth-child(11), td:nth-child(11) { min-width: 200px; white-space: normal; } /* Actions */

        tbody tr {
            border-bottom: 1px solid rgba(226, 180, 88, 0.18);
        }

        tbody tr:hover {
            background: #fef6e7;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-applied { background: #e5e7eb; color: #374151; }
        .status-shortlisted { background: #fef3c7; color: #92400e; }
        .status-selected { background: #d1fae5; color: #047857; }
        .status-rejected { background: #fee2e2; color: #b91c1c; }

        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 8px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.85rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.12);
        }

        .btn-shortlist { background: linear-gradient(135deg, #f8d477, #e2b458); color: #3d1f1f; }
        .btn-select { background: linear-gradient(135deg, #34d399, #059669); color: #ffffff; }
        .btn-reject { background: linear-gradient(135deg, #f87171, #dc2626); color: #ffffff; }
        .btn-resume { 
            background: linear-gradient(135deg, var(--brand-primary) 0%, var(--brand-primary-dark) 100%); 
            color: #ffffff !important;
            padding: 8px 14px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
            white-space: nowrap;
        }
        .btn-resume:hover {
            background: linear-gradient(135deg, var(--brand-primary-dark) 0%, var(--brand-primary) 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(91, 31, 31, 0.2);
        }

        @media (max-width: 1200px) {
            .table-wrapper {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            .container {
                padding: 24px 20px;
            }
        }

        @media (max-width: 768px) {
            .table-wrapper {
                margin: 0 -10px;
                border-radius: 0;
            }
            
            table {
                min-width: 1000px;
            }
            
            th, td {
                padding: 10px 8px;
                font-size: 0.85rem;
            }
            
            .btn-resume {
                padding: 6px 10px;
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2><i class="fas fa-folder-open"></i> Internship Applications</h2>
        </div>

        <?php if (isset($query_error)): ?>
            <div style="background: #fee2e2; color: #b91c1c; padding: 12px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #dc2626;">
                <strong>Database Error:</strong> <?= htmlspecialchars($query_error) ?>
            </div>
        <?php endif; ?>

        <?php 
        // Diagnostic: Show raw data from internship_applications to help debug
        if ($total_count > 0 && (!$applications || $applications->num_rows === 0)) {
            $raw_data = $mysqli->query("SELECT id, username, internship_id, company_name, internship_role, applied_at FROM internship_applications LIMIT 5");
            if ($raw_data && $raw_data->num_rows > 0) {
                echo '<div style="background: #fef3c7; color: #92400e; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #f59e0b;">';
                echo '<strong>🔍 Diagnostic Information:</strong><br><br>';
                echo '<strong>Applications in database:</strong><br>';
                echo '<table style="width:100%; border-collapse: collapse; margin-top: 10px; font-size: 0.9rem;">';
                echo '<tr style="background: #fbbf24; color: #78350f;"><th style="padding: 8px; text-align: left; border: 1px solid #d97706;">ID</th><th style="padding: 8px; text-align: left; border: 1px solid #d97706;">Username</th><th style="padding: 8px; text-align: left; border: 1px solid #d97706;">Internship ID</th><th style="padding: 8px; text-align: left; border: 1px solid #d97706;">Company</th><th style="padding: 8px; text-align: left; border: 1px solid #d97706;">Role</th></tr>';
                while ($raw = $raw_data->fetch_assoc()) {
                    $username_check = $mysqli->query("SELECT username FROM users WHERE username = '" . $mysqli->real_escape_string($raw['username']) . "' LIMIT 1");
                    $user_exists = $username_check && $username_check->num_rows > 0;
                    echo '<tr style="background: ' . ($user_exists ? '#d1fae5' : '#fee2e2') . ';">';
                    echo '<td style="padding: 8px; border: 1px solid #d97706;">' . htmlspecialchars($raw['id']) . '</td>';
                    echo '<td style="padding: 8px; border: 1px solid #d97706;">' . htmlspecialchars($raw['username'] ?? 'NULL') . ' ' . ($user_exists ? '✓' : '✗') . '</td>';
                    echo '<td style="padding: 8px; border: 1px solid #d97706;">' . htmlspecialchars($raw['internship_id'] ?? 'NULL') . '</td>';
                    echo '<td style="padding: 8px; border: 1px solid #d97706;">' . htmlspecialchars($raw['company_name'] ?? 'N/A') . '</td>';
                    echo '<td style="padding: 8px; border: 1px solid #d97706;">' . htmlspecialchars($raw['internship_role'] ?? 'N/A') . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
                echo '<br><small>✓ = Username exists in users table | ✗ = Username NOT found in users table</small>';
                echo '</div>';
            }
        }
        ?>

        <form class="filter-bar" method="get">
            <select name="id">
                <option value="">All Internships</option>
                <?php if ($opportunities): ?>
                    <?php while ($row = $opportunities->fetch_assoc()): ?>
                        <option value="<?= (int) $row['id'] ?>" <?= $internshipId === (int) $row['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($row['company'] . ' – ' . $row['role']) ?>
                        </option>
                    <?php endwhile; ?>
                <?php endif; ?>
            </select>
            <button type="submit"><i class="fas fa-filter"></i> Apply Filter</button>
        </form>

        <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Full Name</th>
                    <th>Username</th>
                    <th>Branch</th>
                    <th>Semester</th>
                    <th>CGPA</th>
                    <th>Company</th>
                    <th>Job Role</th>
                    <th>Applied On</th>
                    <th>Resume</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($applications && $applications->num_rows > 0): ?>
                    <?php while ($row = $applications->fetch_assoc()): ?>
                        <?php
                            $statusClass = 'status-applied';
                            if ($row['application_status'] === 'Shortlisted') {
                                $statusClass = 'status-shortlisted';
                            } elseif ($row['application_status'] === 'Selected') {
                                $statusClass = 'status-selected';
                            } elseif ($row['application_status'] === 'Rejected') {
                                $statusClass = 'status-rejected';
                            }
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($row['full_name'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($row['username'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($row['branch'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($row['semester'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($row['cgpa'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($row['company'] ?? 'Unknown') ?></td>
                            <td><?= htmlspecialchars($row['role'] ?? 'Role') ?></td>
                            <td><?= htmlspecialchars(date('M d, Y', strtotime($row['applied_at']))) ?></td>
                            <td>
                                <?php
                                    // Priority: 1. Check if resume_json exists (generated resume), 2. resume_path, 3. resume_link, 4. check generated_resumes table
                                    $has_resume_json = !empty($row['resume_json']);
                                    $resume_path = $row['resume_path'] ?? '';
                                    $resume_link = $row['resume_link'] ?? '';
                                    $username = $row['username'] ?? '';
                                    
                                    // If no resume_json in application, check generated_resumes table
                                    if (!$has_resume_json && !empty($username)) {
                                        $resume_check = $mysqli->query("SHOW TABLES LIKE 'generated_resumes'");
                                        if ($resume_check && $resume_check->num_rows > 0) {
                                            $resume_stmt = $mysqli->prepare("SELECT resume_json FROM generated_resumes WHERE username = ? LIMIT 1");
                                            if ($resume_stmt) {
                                                $resume_stmt->bind_param('s', $username);
                                                $resume_stmt->execute();
                                                $resume_result = $resume_stmt->get_result()->fetch_assoc();
                                                if ($resume_result && !empty($resume_result['resume_json'])) {
                                                    $has_resume_json = true;
                                                }
                                                $resume_stmt->close();
                                            }
                                        }
                                    }
                                ?>
                                <?php if ($has_resume_json): ?>
                                    <!-- Generated resume - show in HTML viewer -->
                                    <a href="view_internship_resume.php?id=<?= (int)$row['id'] ?>" target="_blank" class="btn-resume"><i class="fas fa-file-alt"></i> View Resume</a>
                                <?php elseif (!empty($resume_path)): ?>
                                    <!-- Resume uploaded with application -->
                                    <a class="btn-resume" href="../<?= ltrim($resume_path, '/') ?>" target="_blank"><i class="fas fa-file-alt"></i> View Resume</a>
                                <?php elseif (!empty($resume_link)): ?>
                                    <!-- Resume link -->
                                    <a class="btn-resume" href="<?= htmlspecialchars($resume_link) ?>" target="_blank"><i class="fas fa-file-alt"></i> View Resume</a>
                                <?php else: ?>
                                    <span style="color:#aaa">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge <?= $statusClass ?>">
                                    <?= htmlspecialchars($row['application_status'] ?? 'Applied') ?>
                                </span>
                            </td>
                            <td>
                                <form method="post" action="update_intern_application_status.php" style="display:flex; gap:6px; align-items:center; flex-wrap:wrap;">
                                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                    <select name="status" style="padding: 6px 10px; border-radius: 6px; font-size: 0.85rem; border: 1px solid #ddd; min-width: 120px;">
                                        <option value="Applied" <?= $row['application_status']=='Applied'?'selected':'' ?>>Applied</option>
                                        <option value="Shortlisted" <?= $row['application_status']=='Shortlisted'?'selected':'' ?>>Shortlisted</option>
                                        <option value="Interviewed" <?= $row['application_status']=='Interviewed'?'selected':'' ?>>Interviewed</option>
                                        <option value="Selected" <?= $row['application_status']=='Selected'?'selected':'' ?>>Selected</option>
                                        <option value="Rejected" <?= $row['application_status']=='Rejected'?'selected':'' ?>>Rejected</option>
                                    </select>
                                    <button type="submit" class="btn" style="background:#74370b;color:white;font-weight:600;padding:6px 14px;border-radius:6px;font-size:0.85rem;border:none;cursor:pointer;white-space:nowrap;">Update</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="11" style="text-align:center; padding: 28px;">
                            <?php if ($total_count > 0): ?>
                                <div style="color: #dc2626; margin-bottom: 10px;">
                                    <strong>⚠️ Data Mismatch Detected</strong><br>
                                    There are <?= $total_count ?> application(s) in the database, but none are matching the current query.
                                </div>
                                <div style="color: #6b7280; font-size: 0.9rem;">
                                    Possible causes:<br>
                                    • Username mismatch between <code>internship_applications</code> and <code>users</code> tables<br>
                                    • Filter is excluding all records<br>
                                    • JOIN conditions are not matching
                                </div>
                            <?php else: ?>
                                No applications found for the selected filter.
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</body>
</html>

