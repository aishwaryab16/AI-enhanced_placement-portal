<?php
require_once __DIR__ . '/../includes/config.php';

// Allow both admin and placement_officer roles
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'placement_officer')) {
    header('Location: login.php');
    exit;
}

$admin_id = $_SESSION['user_id'];
$admin_name = $_SESSION['username'];

// Fetch key statistics with error handling
$stats = [];

// Total students
$result = $mysqli->query("SELECT COUNT(*) as total FROM users WHERE role = 'student'");
$stats['total_students'] = $result ? $result->fetch_assoc()['total'] : 0;

// Total companies
$result = $mysqli->query("SHOW TABLES LIKE 'companies'");
if ($result && $result->num_rows > 0) {
    $result = $mysqli->query("SELECT COUNT(*) as total FROM companies");
    $stats['total_companies'] = $result ? $result->fetch_assoc()['total'] : 0;
} else {
    $stats['total_companies'] = 0;
}

// Active drives
$result = $mysqli->query("SHOW TABLES LIKE 'placement_drives'");
if ($result && $result->num_rows > 0) {
    $result = $mysqli->query("SELECT COUNT(*) as total FROM placement_drives WHERE status = 'active'");
    $stats['active_drives'] = $result ? $result->fetch_assoc()['total'] : 0;
} else {
    $stats['active_drives'] = 0;
}

// Placed students
$result = $mysqli->query("SHOW TABLES LIKE 'placements'");
if ($result && $result->num_rows > 0) {
    $result = $mysqli->query("SELECT COUNT(DISTINCT student_id) as total FROM placements");
    $stats['placed_students'] = $result ? $result->fetch_assoc()['total'] : 0;
} else {
    $stats['placed_students'] = 0;
}

// Calculate placement percentage
$stats['placement_percentage'] = $stats['total_students'] > 0 
    ? round(($stats['placed_students'] / $stats['total_students']) * 100, 1) 
    : 0;

// Scheduled interviews
$result = $mysqli->query("SHOW TABLES LIKE 'interviews'");
if ($result && $result->num_rows > 0) {
    $result = $mysqli->query("SELECT COUNT(*) as total FROM interviews WHERE status = 'scheduled'");
    $stats['scheduled_interviews'] = $result ? $result->fetch_assoc()['total'] : 0;
} else {
    $stats['scheduled_interviews'] = 0;
}

// Job applications
$result = $mysqli->query("SHOW TABLES LIKE 'job_applications'");
if ($result && $result->num_rows > 0) {
    $result = $mysqli->query("SELECT COUNT(*) as total FROM job_applications");
    $stats['total_applications'] = $result ? $result->fetch_assoc()['total'] : 0;
} else {
    $stats['total_applications'] = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Placement Cell Dashboard - Professional</title>
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

        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        /* Professional Header */
        .dashboard-header {
            background: linear-gradient(135deg, #5b1f1f 0%, #4a1919 100%);
            color: white;
            padding: 40px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(91, 31, 31, 0.2);
            border: 2px solid #e2b458;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .header-content h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
            color: white;
        }

        .header-content p {
            opacity: 0.95;
            font-size: 1rem;
            font-weight: 400;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            border: 1px solid rgba(226, 180, 88, 0.3);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #e2b458, #f0d084);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: #5b1f1f;
            font-weight: 700;
        }

        .user-details {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-weight: 600;
            font-size: 0.95rem;
        }

        .user-role {
            font-size: 0.8rem;
            opacity: 0.8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .logout-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: linear-gradient(135deg, #e2b458, #f0d084);
            color: #000;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(226, 180, 88, 0.4);
        }

        /* Stats Grid */
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
            transition: all 0.3s ease;
            border-left: 4px solid #e2b458;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.12);
        }

        .stat-icon {
            font-size: 2rem;
            color: #5b1f1f;
            margin-bottom: 12px;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: #5b1f1f;
            margin-bottom: 5px;
            line-height: 1;
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Section Card */
        .section-card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e9ecef;
        }

        .section-title {
            font-size: 1.5rem;
            color: #5b1f1f;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .section-title i {
            color: #e2b458;
        }

        /* Module Grid */
        .module-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
        }

        .module-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
        }

        .module-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.12);
            border-color: #e2b458;
        }

        .module-icon {
            font-size: 2.5rem;
            color: #5b1f1f;
            margin-bottom: 15px;
        }

        .module-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #5b1f1f;
            margin-bottom: 10px;
        }

        .module-description {
            color: #6c757d;
            line-height: 1.6;
            margin-bottom: 15px;
            font-size: 0.95rem;
        }

        .module-features {
            list-style: none;
            margin-bottom: 20px;
        }

        .module-features li {
            padding: 6px 0;
            color: #495057;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9rem;
        }

        .module-features li::before {
            content: '\f00c';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            color: #28a745;
            font-size: 0.8rem;
        }

        .action-btn {
            background: linear-gradient(135deg, #5b1f1f, #4a1919);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            font-size: 0.9rem;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(91, 31, 31, 0.3);
        }

        .action-btn i {
            font-size: 0.9rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .module-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Header -->
        <div class="dashboard-header">
            <div class="header-content">
                <h1><i class="fas fa-building"></i> Placement Cell Dashboard</h1>
                <p>Complete Placement Management System</p>
            </div>
            <div class="header-actions">
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($admin_name, 0, 1)); ?>
                    </div>
                    <div class="user-details">
                        <span class="user-name"><?php echo htmlspecialchars($admin_name); ?></span>
                        <span class="user-role"><?php echo ucfirst(str_replace('_', ' ', $_SESSION['role'])); ?></span>
                    </div>
                </div>
                <a href="../logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </div>

        <!-- Key Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
                <div class="stat-value"><?php echo $stats['total_students']; ?></div>
                <div class="stat-label">Total Students</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-building"></i></div>
                <div class="stat-value"><?php echo $stats['total_companies']; ?></div>
                <div class="stat-label">Companies</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-rocket"></i></div>
                <div class="stat-value"><?php echo $stats['active_drives']; ?></div>
                <div class="stat-label">Active Drives</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-value"><?php echo $stats['placed_students']; ?></div>
                <div class="stat-label">Placed</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                <div class="stat-value"><?php echo $stats['placement_percentage']; ?>%</div>
                <div class="stat-label">Placement Rate</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                <div class="stat-value"><?php echo $stats['scheduled_interviews']; ?></div>
                <div class="stat-label">Interviews</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
                <div class="stat-value"><?php echo $stats['total_applications']; ?></div>
                <div class="stat-label">Applications</div>
            </div>
        </div>

        <!-- Main Modules -->
        <div class="section-card">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-th-large"></i>
                    Management Modules
                </h2>
            </div>
            <div class="module-grid">
                <!-- Company Management -->
                <div class="module-card">
                    <div class="module-icon"><i class="fas fa-building"></i></div>
                    <h3 class="module-title">Company Management</h3>
                    <p class="module-description">Complete company coordination and job posting system</p>
                    <ul class="module-features">
                        <li>Add/Edit Companies</li>
                        <li>Job Posting Management</li>
                        <li>Eligibility Criteria Setup</li>
                        <li>Auto-filter Eligible Students</li>
                        <li>Schedule Drives & Tests</li>
                    </ul>
                    <a href="manage_jobs.php" class="action-btn">
                        <i class="fas fa-arrow-right"></i> Manage Companies
                    </a>
                </div>

                <!-- Student Analytics -->
                <div class="module-card">
                    <div class="module-icon"><i class="fas fa-chart-line"></i></div>
                    <h3 class="module-title">Student Placement Analytics</h3>
                    <p class="module-description">Comprehensive analytics and reporting dashboard</p>
                    <ul class="module-features">
                        <li>Eligible vs Applied Stats</li>
                        <li>Department-wise Analysis</li>
                        <li>Company-wise Breakdown</li>
                        <li>Gender & Category Distribution</li>
                        <li>Power BI Integration</li>
                    </ul>
                    <a href="placement_officer.php" class="action-btn">
                        <i class="fas fa-chart-bar"></i> View Analytics
                    </a>
                </div>

                <!-- Communication Hub -->
                <div class="module-card">
                    <div class="module-icon"><i class="fas fa-bullhorn"></i></div>
                    <h3 class="module-title">Communication Hub</h3>
                    <p class="module-description">Broadcast and notification management system</p>
                    <ul class="module-features">
                        <li>Broadcast Messages</li>
                        <li>Filtered Group Messaging</li>
                        <li>Real-time Notifications</li>
                        <li>Drive Updates</li>
                        <li>Shortlist Announcements</li>
                    </ul>
                    <a href="communication_hub.php" class="action-btn">
                        <i class="fas fa-comments"></i> Open Hub
                    </a>
                </div>

                <!-- Interview Management -->
                <div class="module-card">
                    <div class="module-icon"><i class="fas fa-calendar-alt"></i></div>
                    <h3 class="module-title">Interview & Drive Management</h3>
                    <p class="module-description">Complete interview coordination and tracking</p>
                    <ul class="module-features">
                        <li>Assign Interview Panels</li>
                        <li>Faculty Observer Assignment</li>
                        <li>Track Ongoing Interviews</li>
                        <li>Upload Shortlist Results</li>
                        <li>Offer Letter Tracking</li>
                    </ul>
                    <a href="schedule_interview.php" class="action-btn">
                        <i class="fas fa-tasks"></i> Manage Interviews
                    </a>
                </div>

                <!-- Interview Feedback -->
                <div class="module-card">
                    <div class="module-icon"><i class="fas fa-clipboard-check"></i></div>
                    <h3 class="module-title">Interview Feedback & Applications</h3>
                    <p class="module-description">Track applications and collect interview feedback</p>
                    <ul class="module-features">
                        <li>Job Application Tracking</li>
                        <li>Interview Feedback Collection</li>
                        <li>Performance Scoring</li>
                        <li>Strength & Improvement Areas</li>
                        <li>Application Status Updates</li>
                    </ul>
                    <a href="applications.php" class="action-btn">
                        <i class="fas fa-file-alt"></i> View Applications
                    </a>
                </div>

                <!-- Company Questions Bank -->
                <div class="module-card">
                    <div class="module-icon"><i class="fas fa-question-circle"></i></div>
                    <h3 class="module-title">Company Questions Bank</h3>
                    <p class="module-description">AI-powered interview question repository</p>
                    <ul class="module-features">
                        <li>Company-wise Questions</li>
                        <li>Technical & HR Questions</li>
                        <li>Difficulty Level Classification</li>
                        <li>AI-Generated Questions</li>
                        <li>Student Preparation Resources</li>
                    </ul>
                    <a href="company_questions.php" class="action-btn">
                        <i class="fas fa-book"></i> Manage Questions
                    </a>
                </div>

                <!-- Admin Control -->
                <div class="module-card">
                    <div class="module-icon"><i class="fas fa-user-shield"></i></div>
                    <h3 class="module-title">Admin Control</h3>
                    <p class="module-description">User management and system administration</p>
                    <ul class="module-features">
                        <li>Create/Remove Users</li>
                        <li>Role-based Access Control</li>
                        <li>Backup & Restore</li>
                        <li>Data Export Options</li>
                        <li>System Settings</li>
                    </ul>
                    <a href="admin_control.php" class="action-btn">
                        <i class="fas fa-cog"></i> Admin Panel
                    </a>
                </div>

                <!-- AI Insights -->
                <div class="module-card">
                    <div class="module-icon"><i class="fas fa-brain"></i></div>
                    <h3 class="module-title">AI Insights (Advanced)</h3>
                    <p class="module-description">Predictive analytics and intelligent recommendations</p>
                    <ul class="module-features">
                        <li>Predictive Placement Analytics</li>
                        <li>Student Success Probability</li>
                        <li>Company Feedback Loop</li>
                        <li>Skill Demand Insights</li>
                        <li>AI HR Bot Metrics</li>
                    </ul>
                    <a href="ai_insights.php" class="action-btn">
                        <i class="fas fa-chart-pie"></i> View Insights
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
