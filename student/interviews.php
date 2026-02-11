<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/job_backend.php';
require_role('student');

$student_id = $_SESSION['user_id'];
$mysqli = $GLOBALS['mysqli'] ?? new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
setupJobTables($mysqli);

// Fetch student data for sidebar
$stmt = $mysqli->prepare('SELECT * FROM users WHERE id = ?');
$stmt->bind_param('i', $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch interviews for this student
$upcoming = [];
$past = [];
$res = $mysqli->prepare("SELECT * FROM interviews WHERE student_id = ? ORDER BY scheduled_at ASC, created_at DESC");
if ($res) {
    $res->bind_param('i', $student_id);
    $res->execute();
    $result = $res->get_result();
    while ($row = $result->fetch_assoc()) {
        if ($row['status'] === 'completed') { 
            $past[] = $row; 
        } else { 
            $upcoming[] = $row; 
        }
    }
    $res->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Interviews - Student Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../includes/partials/sidebar.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f5f7fa;
            min-height: 100vh;
            display: flex;
        }

        /* Hamburger Menu Button */
        .hamburger-btn {
            position: fixed;
            top: 20px;
            left: 20px;
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #5b1f1f, #8b3a3a);
            border: none;
            border-radius: 12px;
            cursor: pointer;
            z-index: 1001;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 5px;
            box-shadow: 0 4px 15px rgba(91, 31, 31, 0.3);
            transition: all 0.3s ease;
        }

        .hamburger-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 20px rgba(91, 31, 31, 0.4);
        }

        .hamburger-btn span {
            width: 24px;
            height: 3px;
            background: white;
            border-radius: 2px;
            transition: all 0.3s ease;
        }

        .hamburger-btn.active span:nth-child(1) {
            transform: rotate(45deg) translate(7px, 7px);
        }

        .hamburger-btn.active span:nth-child(2) {
            opacity: 0;
        }

        .hamburger-btn.active span:nth-child(3) {
            transform: rotate(-45deg) translate(7px, -7px);
        }

        /* Overlay for mobile */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .sidebar-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        /* Main Content */
        .main-wrapper {
            margin-left: 240px;
            flex: 1;
            padding: 20px;
            padding-top: 0;
            width: 100%;
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .breadcrumb {
            color: #6b7280;
            font-size: 14px;
        }

        .header-banner {
            background: linear-gradient(135deg, #5b1f1f 0%, #8b3a3a 50%, #ecc35c 100%);
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 30px;
        }

        .header-banner h1 {
            color: white;
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .header-banner p {
            color: rgba(255,255,255,0.9);
            font-size: 16px;
        }

        /* Cards */
        .card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 25px;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e9ecef;
        }

        .card-header h2 {
            color: #1f2937;
            font-size: 20px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-header h2 i {
            color: #5b1f1f;
        }

        .badge-count {
            background: #5b1f1f;
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 8px;
        }

        /* Table */
        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: #f9fafb;
        }

        th {
            padding: 12px 16px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e5e7eb;
        }

        td {
            padding: 16px;
            border-bottom: 1px solid #f3f4f6;
            font-size: 14px;
            color: #1f2937;
        }

        tbody tr {
            transition: all 0.2s;
        }

        tbody tr:hover {
            background: #f9fafb;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .company-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .company-logo {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #5b1f1f, #ecc35c);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 16px;
            flex-shrink: 0;
        }

        .company-details {
            display: flex;
            flex-direction: column;
        }

        .company-name {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 2px;
        }

        .job-role {
            color: #6b7280;
            font-size: 13px;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: capitalize;
        }

        .status-scheduled {
            background: #fef3c7;
            color: #92400e;
        }

        .status-in_progress {
            background: #dbeafe;
            color: #1e40af;
        }

        .status-completed {
            background: #d1fae5;
            color: #065f46;
        }

        .status-cancelled {
            background: #fee2e2;
            color: #991b1b;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #5b1f1f, #8b3a3a);
            color: white;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #3d1414, #5b1f1f);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(91, 31, 31, 0.3);
        }

        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
        }

        .btn-secondary:hover {
            background: #e5e7eb;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6b7280;
        }

        .empty-state i {
            font-size: 48px;
            color: #d1d5db;
            margin-bottom: 16px;
        }

        .empty-state p {
            font-size: 16px;
            margin-bottom: 8px;
        }

        .empty-state span {
            font-size: 14px;
            color: #9ca3af;
        }

        .score-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            font-weight: 700;
            font-size: 18px;
        }

        .score-high {
            background: #d1fae5;
            color: #065f46;
        }

        .score-medium {
            background: #fef3c7;
            color: #92400e;
        }

        .score-low {
            background: #fee2e2;
            color: #991b1b;
        }

        .feedback-text {
            max-width: 300px;
            color: #6b7280;
            font-size: 13px;
            line-height: 1.5;
        }

        @media (max-width: 768px) {
            .main-wrapper {
                margin-left: 0;
                padding: 15px;
            }

            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s;
            }

            .table-container {
                overflow-x: scroll;
            }
        }
    </style>
</head>
<body>
    <!-- Hamburger Menu Button -->
    <button class="hamburger-btn" id="hamburgerBtn">
        <span></span>
        <span></span>
        <span></span>
    </button>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <?php include __DIR__ . '/../includes/partials/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-wrapper">
        <!-- Top Bar -->
        <div class="top-bar">
            
        </div>

        <!-- Header Banner -->
        <div class="header-banner">
            <h1>💼 My Interviews</h1>
            <p>View your scheduled company interviews and track your interview history</p>
        </div>

        <!-- Upcoming/Scheduled Interviews -->
        <div class="card">
            <div class="card-header">
                <h2>
                    <i class="fas fa-calendar-check"></i>
                    Upcoming Interviews
                    <span class="badge-count"><?php echo count($upcoming); ?></span>
                </h2>
            </div>
            <div class="table-container">
                <?php if (empty($upcoming)): ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <p>No upcoming interviews</p>
                        <span>Your scheduled interviews will appear here</span>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Company</th>
                                <th>Position</th>
                                <th>Scheduled Date</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($upcoming as $i): ?>
                                <tr>
                                    <td>
                                        <div class="company-info">
                                            <div class="company-logo">
                                                <?php echo strtoupper(substr($i['company'], 0, 1)); ?>
                                            </div>
                                            <div class="company-details">
                                                <div class="company-name"><?php echo htmlspecialchars($i['company']); ?></div>
                                                <?php if (!empty($i['panel_details'])): ?>
                                                    <div class="job-role" style="font-size: 11px; color: #9ca3af;">
                                                        <i class="fas fa-users"></i> <?php echo htmlspecialchars(substr($i['panel_details'], 0, 30)) . (strlen($i['panel_details']) > 30 ? '...' : ''); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="job-role" style="font-weight: 600; color: #1f2937;">
                                            <?php echo htmlspecialchars($i['job_role']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="display: flex; flex-direction: column; gap: 4px;">
                                            <span style="font-weight: 600; color: #1f2937;">
                                                <?php echo $i['scheduled_at'] ? date('M d, Y', strtotime($i['scheduled_at'])) : '-'; ?>
                                            </span>
                                            <span style="font-size: 12px; color: #6b7280;">
                                                <i class="fas fa-clock"></i> <?php echo $i['scheduled_at'] ? date('H:i', strtotime($i['scheduled_at'])) : '-'; ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower(str_replace(' ', '_', $i['status'])); ?>">
                                            <?php echo htmlspecialchars($i['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="interview_rounds.php?interview_id=<?php echo (int)$i['id']; ?>" 
                                           class="btn btn-primary">
                                            <i class="fas fa-play-circle"></i>
                                            Start Interview
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Past Interviews -->
        <div class="card">
            <div class="card-header">
                <h2>
                    <i class="fas fa-history"></i>
                    Past Interviews
                    <span class="badge-count"><?php echo count($past); ?></span>
                </h2>
            </div>
            <div class="table-container">
                <?php if (empty($past)): ?>
                    <div class="empty-state">
                        <i class="fas fa-file-alt"></i>
                        <p>No completed interviews yet</p>
                        <span>Your interview results will appear here after completion</span>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Company</th>
                                <th>Position</th>
                                <th>Overall Score</th>
                                <th>Overall Feedback</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($past as $i): 
                                $score = isset($i['overall_score']) && $i['overall_score'] !== null ? (int)$i['overall_score'] : null;
                                $scoreClass = $score !== null ? ($score >= 80 ? 'score-high' : ($score >= 60 ? 'score-medium' : 'score-low')) : '';
                            ?>
                                <tr>
                                    <td>
                                        <div class="company-info">
                                            <div class="company-logo">
                                                <?php echo strtoupper(substr($i['company'], 0, 1)); ?>
                                            </div>
                                            <div class="company-details">
                                                <div class="company-name"><?php echo htmlspecialchars($i['company']); ?></div>
                                                <div class="job-role" style="font-size: 11px; color: #9ca3af;">
                                                    <?php echo $i['scheduled_at'] ? date('M d, Y', strtotime($i['scheduled_at'])) : ''; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="job-role" style="font-weight: 600; color: #1f2937;">
                                            <?php echo htmlspecialchars($i['job_role']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($score !== null): ?>
                                            <div class="score-badge <?php echo $scoreClass; ?>">
                                                <?php echo $score; ?>%
                                            </div>
                                        <?php else: ?>
                                            <span style="color: #9ca3af;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="feedback-text">
                                            <?php echo !empty($i['overall_feedback']) ? nl2br(htmlspecialchars($i['overall_feedback'])) : '<span style="color: #9ca3af;">No feedback provided</span>'; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Hamburger Menu Toggle
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        function toggleSidebar() {
            hamburgerBtn.classList.toggle('active');
            sidebar.classList.toggle('active');
            sidebarOverlay.classList.toggle('active');
        }

        hamburgerBtn.addEventListener('click', toggleSidebar);
        sidebarOverlay.addEventListener('click', toggleSidebar);

        const navLinks = sidebar.querySelectorAll('.nav-item, .logout-btn');
        navLinks.forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 1024) {
                    toggleSidebar();
                }
            });
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && sidebar.classList.contains('active')) {
                toggleSidebar();
            }
        });
    </script>
</body>
</html>
