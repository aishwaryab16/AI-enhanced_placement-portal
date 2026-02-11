<?php
require_once __DIR__ . '/../includes/config.php';

// Allow both admin and placement_officer roles
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'placement_officer')) {
    header('Location: login.php');
    exit;
}

$mysqli = $GLOBALS['mysqli'] ?? new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

// Ensure interview_attendance table exists
require_once __DIR__ . '/../includes/job_backend.php';
setupJobTables($mysqli);

// Fetch all completed interviews with overall scores and feedback
// Use overall_score from interviews table if available, otherwise calculate from interview_attendance
$query = "SELECT 
    i.student_id,
    i.company,
    i.job_role,
    COALESCE(i.overall_score, (SELECT AVG(score) FROM interview_attendance WHERE interview_id = i.id AND status = 'completed' AND score IS NOT NULL)) as overall_score,
    COALESCE(i.overall_feedback, 'Interview completed successfully. All rounds finished.') as overall_feedback,
    i.updated_at as last_completed_at,
    i.created_at as first_started_at,
    (SELECT COUNT(*) FROM interview_attendance WHERE interview_id = i.id AND status = 'completed' AND score IS NOT NULL) as rounds_completed,
    u.full_name,
    u.email,
    u.branch,
    u.cgpa
FROM interviews i
INNER JOIN users u ON i.student_id = u.id
WHERE i.status = 'completed' 
AND EXISTS (
    SELECT 1 FROM interview_attendance 
    WHERE interview_id = i.id 
    AND status = 'completed' 
    AND score IS NOT NULL
)
ORDER BY overall_score DESC, i.updated_at DESC";

$result = $mysqli->query($query);
$all_interviews = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $row['overall_score'] = (int)round($row['overall_score']); // Round to integer
        $all_interviews[] = $row;
    }
}

// Get top 5 performers with overall scores
// Use overall_score from interviews table if available, otherwise calculate from interview_attendance
$top5_query = "SELECT 
    i.student_id,
    i.company,
    i.job_role,
    COALESCE(i.overall_score, (SELECT AVG(score) FROM interview_attendance WHERE interview_id = i.id AND status = 'completed' AND score IS NOT NULL)) as overall_score,
    COALESCE(i.overall_feedback, 'Interview completed successfully. All rounds finished.') as overall_feedback,
    i.updated_at as last_completed_at,
    i.created_at as first_started_at,
    (SELECT COUNT(*) FROM interview_attendance WHERE interview_id = i.id AND status = 'completed' AND score IS NOT NULL) as rounds_completed,
    u.full_name,
    u.email,
    u.branch,
    u.cgpa
FROM interviews i
INNER JOIN users u ON i.student_id = u.id
WHERE i.status = 'completed' 
AND EXISTS (
    SELECT 1 FROM interview_attendance 
    WHERE interview_id = i.id 
    AND status = 'completed' 
    AND score IS NOT NULL
)
ORDER BY overall_score DESC
LIMIT 5";

$top5_result = $mysqli->query($top5_query);
$top5_students = [];
if ($top5_result) {
    while ($row = $top5_result->fetch_assoc()) {
        $row['overall_score'] = (int)round($row['overall_score']); // Round to integer
        $top5_students[] = $row;
    }
}

include __DIR__ . '/../includes/partials/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interview Results - Placement Manager</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            min-height: 100vh;
            color: #2c3e50;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .header {
            background: linear-gradient(135deg, #5b1f1f 0%, #4a1919 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(91, 31, 31, 0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .header h1 {
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
        }

        .back-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #5b1f1f;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e9ecef;
        }

        .section-title i {
            color: #e2b458;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        thead {
            background: #f8f9fa;
        }

        th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #5b1f1f;
            border-bottom: 2px solid #e9ecef;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
            color: #495057;
        }

        tbody tr:hover {
            background: #f8f9fa;
        }

        .score-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 0.9rem;
        }

        .score-excellent {
            background: #d1fae5;
            color: #065f46;
        }

        .score-good {
            background: #dbeafe;
            color: #1e40af;
        }

        .score-average {
            background: #fef3c7;
            color: #92400e;
        }

        .score-poor {
            background: #fee2e2;
            color: #991b1b;
        }

        .top-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            color: white;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.85rem;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }

        .no-data i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            border-left: 4px solid #e2b458;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: #5b1f1f;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-trophy"></i> Interview Results</h1>
            <a href="index.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo count($all_interviews); ?></div>
                <div class="stat-label">Total Completed Interviews</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo count($top5_students); ?></div>
                <div class="stat-label">Top Performers</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">
                    <?php 
                    $avg_score = count($all_interviews) > 0 
                        ? round(array_sum(array_column($all_interviews, 'overall_score')) / count($all_interviews), 1)
                        : 0;
                    echo $avg_score;
                    ?>
                </div>
                <div class="stat-label">Average Score</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">
                    <?php 
                    $max_score = count($all_interviews) > 0 
                        ? max(array_column($all_interviews, 'overall_score'))
                        : 0;
                    echo $max_score;
                    ?>
                </div>
                <div class="stat-label">Highest Score</div>
            </div>
        </div>

        <!-- Top 5 Performers -->
        <div class="section">
            <h2 class="section-title">
                <i class="fas fa-medal"></i>
                Top 5 Performers
            </h2>
            <?php if (empty($top5_students)): ?>
                <div class="no-data">
                    <i class="fas fa-inbox"></i>
                    <p>No interview results available yet.</p>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Student Name</th>
                                <th>Email</th>
                                <th>Branch</th>
                                <th>CGPA</th>
                                <th>Company</th>
                                <th>Role</th>
                                <th>Rounds Completed</th>
                                <th>Overall Score</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top5_students as $index => $student): 
                                $rank = $index + 1;
                                $score = (int)$student['overall_score'];
                                $scoreClass = $score >= 80 ? 'score-excellent' : ($score >= 70 ? 'score-good' : ($score >= 60 ? 'score-average' : 'score-poor'));
                            ?>
                                <tr>
                                    <td>
                                        <?php if ($rank <= 3): ?>
                                            <span class="top-badge">
                                                <i class="fas fa-trophy"></i>
                                                #<?php echo $rank; ?>
                                            </span>
                                        <?php else: ?>
                                            <strong>#<?php echo $rank; ?></strong>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($student['full_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($student['email']); ?></td>
                                    <td><?php echo htmlspecialchars($student['branch'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($student['cgpa'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($student['company']); ?></td>
                                    <td><?php echo htmlspecialchars($student['job_role']); ?></td>
                                    <td>
                                        <?php echo (int)$student['rounds_completed']; ?> round(s)
                                    </td>
                                    <td>
                                        <span class="score-badge <?php echo $scoreClass; ?>">
                                            <?php echo $score; ?>%
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- All Interview Results -->
        <div class="section">
            <h2 class="section-title">
                <i class="fas fa-list"></i>
                All Interview Results
            </h2>
            <?php if (empty($all_interviews)): ?>
                <div class="no-data">
                    <i class="fas fa-inbox"></i>
                    <p>No interview results available yet.</p>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Email</th>
                                <th>Branch</th>
                                <th>CGPA</th>
                                <th>Company</th>
                                <th>Role</th>
                                <th>Rounds Completed</th>
                                <th>Overall Score</th>
                                <th>Completed Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_interviews as $interview): 
                                $score = (int)$interview['overall_score'];
                                $scoreClass = $score >= 80 ? 'score-excellent' : ($score >= 70 ? 'score-good' : ($score >= 60 ? 'score-average' : 'score-poor'));
                            ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($interview['full_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($interview['email']); ?></td>
                                    <td><?php echo htmlspecialchars($interview['branch'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($interview['cgpa'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($interview['company']); ?></td>
                                    <td><?php echo htmlspecialchars($interview['job_role']); ?></td>
                                    <td>
                                        <?php echo (int)$interview['rounds_completed']; ?> round(s)
                                    </td>
                                    <td>
                                        <span class="score-badge <?php echo $scoreClass; ?>">
                                            <?php echo $score; ?>%
                                        </span>
                                    </td>
                                    <td><?php echo $interview['last_completed_at'] ? date('M d, Y', strtotime($interview['last_completed_at'])) : 'N/A'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

