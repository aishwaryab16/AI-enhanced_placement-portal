<?php
require_once __DIR__ . '/../includes/config.php';

// Allow both admin and placement_officer roles (same access as placement dashboard)
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'placement_officer')) {
    header('Location: ../login.php');
    exit;
}

$adminId = $_SESSION['user_id'];
$adminName = $_SESSION['username'] ?? 'Coordinator';

$stats = [
    'total_students' => 0,
    'active_internships' => 0,
    'scheduled_interviews' => 0,
    'total_applications' => 0,
    'offers_released' => 0,
];

$recent_applications = [];

// Total students
$result = $mysqli->query("SELECT COUNT(*) AS total FROM users WHERE role = 'student'");
if ($result) {
    $stats['total_students'] = (int) $result->fetch_assoc()['total'];
}

// Active internship opportunities
$result = $mysqli->query("SHOW TABLES LIKE 'internship_opportunities'");
if ($result && $result->num_rows > 0) {
    $result = $mysqli->query("SELECT COUNT(*) AS total FROM internship_opportunities WHERE status = 'active'");
    if ($result) {
        $stats['active_internships'] = (int) $result->fetch_assoc()['total'];
    }
}

// Internship applications
$result = $mysqli->query("SHOW TABLES LIKE 'internship_applications'");
if ($result && $result->num_rows > 0) {
    $applicationsQuery = "
        SELECT
            COUNT(*) AS total_applications,
            SUM(CASE WHEN application_status = 'Offer Released' THEN 1 ELSE 0 END) AS offers
        FROM internship_applications
    ";
    $result = $mysqli->query($applicationsQuery);
    if ($result) {
        $row = $result->fetch_assoc();
        $stats['total_applications'] = (int) ($row['total_applications'] ?? 0);
        $stats['offers_released'] = (int) ($row['offers'] ?? 0);
    }
}

// Scheduled internship interviews
$result = $mysqli->query("SHOW TABLES LIKE 'internship_interviews'");
if ($result && $result->num_rows > 0) {
    $result = $mysqli->query("SELECT COUNT(*) AS total FROM internship_interviews WHERE status = 'scheduled'");
    if ($result) {
        $stats['scheduled_interviews'] = (int) $result->fetch_assoc()['total'];
    }
}

$recent_result = $mysqli->query("
    SELECT ia.application_status, ia.applied_at, io.company, io.role, u.full_name, ia.id
    FROM internship_applications ia
    LEFT JOIN internship_opportunities io ON io.id = ia.internship_id
    LEFT JOIN users u ON u.id = ia.student_id
    ORDER BY ia.applied_at DESC
    LIMIT 5
");
if ($recent_result) {
    while ($row = $recent_result->fetch_assoc()) {
        $recent_applications[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Internship Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --brand-primary: #5b1f1f;
            --brand-primary-dark: #4a1919;
            --brand-accent: #e2b458;
            --brand-accent-light: #f0d084;
            --neutral-bg: #f8f9fa;
            --card-shadow: 0 6px 20px rgba(91, 31, 31, 0.12);
            --border-radius: 14px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: var(--neutral-bg);
            color: #1f2937;
            min-height: 100vh;
        }

        .dashboard-wrapper {
            max-width: 1320px;
            margin: 0 auto;
            padding: 32px 20px 48px;
        }

        .dashboard-header {
            background: linear-gradient(135deg, var(--brand-primary) 0%, var(--brand-primary-dark) 100%);
            color: #fff;
            padding: 36px;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            margin-bottom: 28px;
        }

        .header-title h1 {
            font-size: 1.95rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header-title p {
            margin-top: 6px;
            opacity: 0.9;
            font-size: 0.95rem;
        }

        .user-summary {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 12px 18px;
            background: rgba(255,255,255,0.12);
            border-radius: 12px;
        }

        .user-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--brand-accent) 0%, var(--brand-accent-light) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--brand-primary);
        }

        .user-details span {
            display: block;
            font-size: 0.9rem;
        }

        .logout-link {
            background: linear-gradient(135deg, var(--brand-accent) 0%, var(--brand-accent-light) 100%);
            color: #1f1f1f;
            border: none;
            border-radius: 10px;
            padding: 10px 18px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            box-shadow: 0 6px 16px rgba(91, 31, 31, 0.22);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .logout-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 24px rgba(91, 31, 31, 0.26);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: #fff;
            border-radius: var(--border-radius);
            padding: 24px;
            box-shadow: var(--card-shadow);
            position: relative;
            overflow: hidden;
        }

        .stat-card::after {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: inherit;
            border: 1px solid rgba(226, 180, 88, 0.2);
            pointer-events: none;
        }

        .stat-icon {
            font-size: 1.8rem;
            color: var(--brand-accent);
            margin-bottom: 14px;
        }

        .stat-value {
            font-size: 2.3rem;
            font-weight: 700;
            color: var(--brand-primary);
        }

        .stat-label {
            font-size: 0.9rem;
            color: #6b7280;
            margin-top: 4px;
            letter-spacing: 0.4px;
        }

        .modules-section {
            background: #fff;
            border-radius: var(--border-radius);
            padding: 32px;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(226, 180, 88, 0.25);
        }

        .modules-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 18px;
            border-bottom: 1px solid #e5ecf5;
            margin-bottom: 24px;
        }

        .module-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 20px;
        }

        .modules-header h2 {
            font-size: 1.4rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--brand-primary);
        }

        .module-card {
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.98) 0%, rgba(250, 245, 234, 0.9) 100%);
            border-radius: 18px;
            padding: 26px;
            border: 1px solid rgba(226, 180, 88, 0.35);
            display: grid;
            gap: 16px;
            box-shadow: 0 14px 36px rgba(91, 31, 31, 0.1);
        }

        .module-card.secondary {
            border: 1px solid rgba(226, 180, 88, 0.28);
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.98) 0%, rgba(245, 243, 232, 0.95) 100%);
        }

        .module-icon {
            font-size: 2.2rem;
            color: var(--brand-accent);
        }

        .module-title {
            font-size: 1.35rem;
            font-weight: 700;
            color: var(--brand-primary);
        }

        .module-description {
            color: #4b5563;
            line-height: 1.6;
        }

        .module-features {
            list-style: none;
            display: grid;
            gap: 10px;
            font-size: 0.95rem;
            color: #374151;
        }

        .module-features li {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .module-features li::before {
            content: '\f00c';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            color: #2fad71;
            font-size: 0.8rem;
        }

        .action-btn {
            justify-self: start;
            background: linear-gradient(135deg, var(--brand-primary) 0%, var(--brand-primary-dark) 100%);
            color: #fff;
            padding: 12px 24px;
            border-radius: 10px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            box-shadow: 0 10px 24px rgba(91, 31, 31, 0.24);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .module-app-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .module-app-item {
            background: #f9fafb;
            border-radius: 12px;
            padding: 14px 16px;
            border-left: 4px solid rgba(226, 180, 88, 0.6);
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        .module-app-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
        }

        .module-app-company {
            font-weight: 700;
            color: #1f2937;
        }

        .module-app-role {
            color: #6b7280;
            font-size: 0.85rem;
        }

        .module-status {
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
            background: #dbeafe;
            color: #1d4ed8;
        }

        .module-status.selected { background: #d1fae5; color: #047857; }
        .module-status.rejected { background: #fee2e2; color: #b91c1c; }
        .module-status.shortlisted { background: #fef3c7; color: #92400e; }

        .module-app-meta {
            display: flex;
            gap: 12px;
            color: #6b7280;
            font-size: 0.82rem;
        }

        .module-card.secondary .action-btn {
            background: linear-gradient(135deg, #5b1f1f, #8b3a3a);
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 30px rgba(91, 31, 31, 0.28);
        }

        @media (max-width: 768px) {
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .modules-section {
                padding: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <header class="dashboard-header">
            <div class="header-title">
                <h1><i class="fas fa-user-tie"></i> Internship Management Dashboard</h1>
                <p>Central hub for internship postings, applications, and interview coordination.</p>
            </div>
            <div class="user-summary">
                <div class="user-avatar"><?php echo strtoupper(substr($adminName, 0, 1)); ?></div>
                <div class="user-details">
                    <span><?php echo htmlspecialchars($adminName); ?></span>
                    <span><?php echo ucfirst(str_replace('_', ' ', $_SESSION['role'])); ?></span>
                </div>
                <a class="logout-link" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </header>

        <section class="stats-grid">
            <article class="stat-card">
                <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
                <div class="stat-value"><?php echo $stats['total_students']; ?></div>
                <div class="stat-label">Active Students</div>
            </article>
            <article class="stat-card">
                <div class="stat-icon"><i class="fas fa-briefcase"></i></div>
                <div class="stat-value"><?php echo $stats['active_internships']; ?></div>
                <div class="stat-label">Active Internships</div>
            </article>
            <article class="stat-card">
                <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                <div class="stat-value"><?php echo $stats['scheduled_interviews']; ?></div>
                <div class="stat-label">Scheduled Interviews</div>
            </article>
            <article class="stat-card">
                <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
                <div class="stat-value"><?php echo $stats['total_applications']; ?></div>
                <div class="stat-label">Applications Received</div>
            </article>
            <article class="stat-card">
                <div class="stat-icon"><i class="fas fa-award"></i></div>
                <div class="stat-value"><?php echo $stats['offers_released']; ?></div>
                <div class="stat-label">Offers Released</div>
            </article>
        </section>

        <section class="modules-section">
            <div class="modules-header">
                <h2><i class="fas fa-th-large"></i> Internship Management Module</h2>
            </div>
            <div class="module-grid">
                <div class="module-card">
                    <div class="module-icon"><i class="fas fa-building-user"></i></div>
                    <h3 class="module-title">Internship Management</h3>
                    <p class="module-description">
                        Plan, publish, and manage every internship engagement with the same efficiency you enjoy in the company management module—tailored specifically for experiential learning programs.
                    </p>
                    <ul class="module-features">
                        <li>Create and update internship opportunities</li>
                        <li>Define stipend, duration, and skill expectations</li>
                        <li>Configure eligibility filters and auto shortlisting</li>
                        <li>Track student applications and status changes</li>
                        <li>Schedule internship assessments & interviews</li>
                    </ul>
                    <a href="manage_internships.php" class="action-btn">
                        <i class="fas fa-arrow-right"></i>
                        Manage Internships
                    </a>
                </div>
                <div class="module-card secondary" id="recentAppCardOverview" style="cursor:pointer;" onclick="window.location.href='track_internship_applications.php';">
                    <div class="module-icon"><i class="fas fa-user-graduate"></i></div>
                    <h3 class="module-title">Recent Internship Applications</h3>
                    <p class="module-description">
                        Quick overview of the latest student submissions. Tap to review the detailed tracker page.
                    </p>
                    <?php if (!empty($recent_applications)): ?>
                        <div class="module-app-list">
                            <?php foreach ($recent_applications as $idx => $app):
                                $status = strtolower($app['application_status'] ?? 'applied');
                                $status_class = 'module-status';
                                if ($status === 'selected') $status_class .= ' selected';
                                elseif ($status === 'rejected') $status_class .= ' rejected';
                                elseif ($status === 'shortlisted') $status_class .= ' shortlisted';
                            ?>
                                <div class="module-app-item" tabindex="0">
                                    <div class="module-app-header">
                                        <div>
                                            <div class="module-app-company"><?= htmlspecialchars($app['company'] ?? 'Company'); ?></div>
                                            <div class="module-app-role"><?= htmlspecialchars($app['role'] ?? 'Internship Role'); ?></div>
                                        </div>
                                        <span class="<?= $status_class ?>"><?= htmlspecialchars($app['application_status'] ?? 'Applied'); ?></span>
                                    </div>
                                    <div class="module-app-meta">
                                        <span><i class="fas fa-user"></i> <?= htmlspecialchars($app['full_name'] ?? 'Student'); ?></span>
                                        <span><i class="fas fa-clock"></i> <?= htmlspecialchars(date('d M', strtotime($app['applied_at'] ?? 'now'))); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="color:#6b7280;">No internship applications yet. Recent activity will appear here.</p>
                    <?php endif; ?>
                    <span class="action-btn" style="margin-top: 8px; width: fit-content; display: inline-block;">
                        <i class="fas fa-users"></i> Go to Tracker
                    </span>
                </div>
            </div>
        </section>
    </div>

