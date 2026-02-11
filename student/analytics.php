<?php
require_once __DIR__ . '/../includes/config.php';
require_role('student');

$student_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Fetch student data
$stmt = $mysqli->prepare('SELECT * FROM users WHERE id = ?');
$stmt->bind_param('i', $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch academic data
$academic_data = null;
$result = $mysqli->query("SELECT * FROM resume_academic_data WHERE student_id = $student_id");
if ($result) $academic_data = $result->fetch_assoc();

// Fetch skills
$skills = [];
$result = $mysqli->query("SELECT * FROM student_skills WHERE student_id = $student_id ORDER BY proficiency_level DESC");
if ($result) $skills = $result->fetch_all(MYSQLI_ASSOC);

// Fetch projects
$projects = [];
$result = $mysqli->query("SELECT * FROM student_projects WHERE student_id = $student_id ORDER BY start_date DESC");
if ($result) $projects = $result->fetch_all(MYSQLI_ASSOC);

// Fetch certifications
$certifications = [];
$result = $mysqli->query("SELECT * FROM student_certifications WHERE student_id = $student_id ORDER BY issue_date DESC");
if ($result) $certifications = $result->fetch_all(MYSQLI_ASSOC);

// Fetch experience
$experiences = [];
$result = $mysqli->query("SELECT * FROM student_experience WHERE student_id = $student_id ORDER BY start_date DESC");
if ($result) $experiences = $result->fetch_all(MYSQLI_ASSOC);

// Fetch PRS data
$prs_data = null;
$result = $mysqli->query("SELECT * FROM placement_readiness_scores WHERE student_id = $student_id");
if ($result) $prs_data = $result->fetch_assoc();

// Fetch companies
$companies = [];
$result = $mysqli->query("SELECT company_name, industry, avg_package_range FROM company_intelligence ORDER BY company_name");
if ($result) $companies = $result->fetch_all(MYSQLI_ASSOC);

$has_data = !empty($skills) || !empty($projects);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard - Student Portal</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../includes/partials/sidebar.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            /* Colors refined for maroon and light cream theme */
            --sidebar-bg: linear-gradient(180deg, #5b1f1f 0%, #3d1414 100%); /* Deep Maroon to darker maroon gradient */
            --sidebar-item-color: #9ca3af; /* Light grey for nav items */
            --sidebar-item-hover-bg: rgba(255, 255, 255, 0.05);
            --sidebar-item-active-bg: rgba(255, 255, 255, 0.1); /* Transparent white for active item */
            --sidebar-item-active-border: transparent; /* No active border for calendar page */

            --accent-color-primary: #ecc35c; /* Gold from calendar page */
            --accent-color-secondary: #d4a843; /* Darker gold from calendar page */
            --text-primary: #5b1f1f; /* Dark brown from calendar page */
            --text-secondary: #6b7280; /* Muted grey for descriptions */
            --card-bg: #ffffff; /* White for card backgrounds, to contrast with cream body */
            --body-bg: #f5f7fa; /* Light grey, close to light cream */
            --border-light: #e9ecef; /* Light grey borders */
            --shadow-light: rgba(0, 0, 0, 0.05);
            --shadow-medium: rgba(0, 0, 0, 0.1);
            --chart-color-1: #ecc35c; /* Gold from calendar */
            --chart-color-2: #d4a843; /* Darker gold from calendar */
            --chart-color-3: #adb5bd; /* Grey */
            --chart-color-4: #6c757d; /* Darker Grey */
            --chart-color-5: #495057; /* Even Darker Grey */
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--body-bg);
            min-height: 100vh;
            display: flex;
        }

        /* Main Content */
        .main-wrapper {
            margin-left: 240px;
            flex: 1;
            padding: 20px;
            background: #f5f7fa;
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: white;
            padding: 15px 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .top-bar-left {
            display: flex;
            align-items: center;
        }

        .top-bar-left a {
            transition: all 0.3s;
            padding: 8px 12px;
            border-radius: 8px;
        }

        .top-bar-left a:hover {
            background: #f3f4f6;
            color: #5b1f1f !important;
        }

        .top-bar-left h2 {
            color: #5b1f1f;
            font-size: 24px;
            margin-bottom: 5px;
        }

        .breadcrumb {
            color: #6b7280;
            font-size: 14px;
        }

        .top-bar-right {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .icon-btn {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: #f3f4f6;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            color: #5b1f1f;
        }

        .icon-btn:hover {
            background: linear-gradient(135deg, #ecc35c, #d4a843);
            color: #5b1f1f;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-weight: 700;
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

        .btn-secondary {
            background: #f3f4f6;
            color: #5b1f1f;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        /* Analytics Grid - Adopted from image layout */
        .analytics-grid {
            display: grid;
            grid-template-columns: 2fr 1fr; /* Main content wider than sidebar-like cards */
            gap: 20px;
            margin-bottom: 30px;
        }

        .analytics-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            border: 1px solid #e9ecef;
        }

        .analytics-card:hover {
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .analytics-card.full-width {
            grid-column: 1 / -1; /* Spans full width of the grid */
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .card-icon {
            width: 45px;
            height: 45px;
            border-radius: 50%; /* Circular icon */
            background: linear-gradient(135deg, #ecc35c, #d4a843);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: #5b1f1f;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        .card-title h3 {
            font-size: 18px;
            font-weight: 600;
            color: #5b1f1f;
            margin-bottom: 4px;
        }

        .card-title p {
            color: #6b7280;
            font-size: 13px;
        }

        .chart-container {
            position: relative;
            height: 250px; /* Adjusted height for compactness */
            margin-bottom: 15px;
        }

        .stats-row {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .stat-box {
            flex: 1;
    text-align: center;
            padding: 15px;
            background: #f3f4f6;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: #5b1f1f;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 11px;
            color: #6b7280;
            font-weight: 600;
    text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .company-match-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .company-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 15px;
            background: #f3f4f6;
            border-radius: 8px;
            border-left: 3px solid #5b1f1f;
        }

        .company-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .company-logo-mini {
            width: 30px;
            height: 30px;
            border-radius: 6px;
            background: linear-gradient(135deg, #ecc35c, #d4a843);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #5b1f1f;
            font-weight: 600;
            font-size: 14px;
        }

        .company-details h4 {
            font-size: 15px;
            font-weight: 600;
            color: #5b1f1f;
            margin-bottom: 2px;
        }

        .company-details p {
            font-size: 11px;
            color: #6b7280;
        }

        .match-percentage {
            font-size: 16px;
            font-weight: 700;
            color: #5b1f1f;
        }

        /* Skill Heatmap */
        .skill-heatmap .skill-row {
    display: grid;
            grid-template-columns: 1.5fr repeat(3, 1fr);
            gap: 5px;
            padding: 8px 0;
            border-bottom: 1px solid #e5e7eb;
            align-items: center;
        }

        .skill-heatmap .skill-row.header {
            font-weight: 700;
            color: #5b1f1f;
            border-bottom: 2px solid #e5e7eb;
        }

        .skill-heatmap .skill-cell {
            padding: 5px;
            font-size: 13px;
            color: #5b1f1f;
        }

        .skill-heatmap .skill-cell.mastered {
            background: #d4edda; /* Light green */
            color: #155724; /* Dark green */
            border-radius: 4px;
    text-align: center;
        }

        .skill-heatmap .skill-cell.learning {
            background: #ffeeba; /* Light yellow */
            color: #856404; /* Dark yellow */
            border-radius: 4px;
            text-align: center;
        }

        .skill-heatmap .skill-cell.missing {
            background: #f8d7da; /* Light red */
            color: #721c24; /* Dark red */
            border-radius: 4px;
            text-align: center;
        }

        .legend span {
            font-size: 12px;
            color: #6b7280;
        }

        /* Recommendations */
        .recommendation-card {
            background: #f3f4f6;
            border-left: 4px solid #5b1f1f;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            box-shadow: 0 1px 5px rgba(0,0,0,0.05);
        }

        .recommendation-card i {
            color: #5b1f1f;
            font-size: 20px;
        }

        .recommendation-card div h4 {
            color: #5b1f1f;
            font-size: 15px;
            margin-bottom: 4px;
        }

        .recommendation-card div p {
            color: #6b7280;
            font-size: 13px;
            line-height: 1.4;
        }

        .progress-bar-container {
    width: 100%;
            background-color: #e5e7eb;
            border-radius: 5px;
            overflow: hidden;
            margin-top: 15px;
        }

        .progress-bar {
            height: 10px;
            background: linear-gradient(90deg, #ecc35c, #d4a843);
            width: 0%; /* Initial width for animation */
            border-radius: 5px;
            text-align: center;
            color: #5b1f1f;
            line-height: 10px;
            font-size: 8px;
            transition: width 1s ease-out;
        }

        /* Top bar specific buttons - to match image */
        .top-bar-right .btn-profile {
            background: #5b1f1f;
            color: white;
        }
        .top-bar-right .btn-build-resume {
            background: linear-gradient(135deg, #ecc35c, #d4a843);
            color: #5b1f1f;
        }
        .top-bar-right .btn-profile:hover,
        .top-bar-right .btn-build-resume:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        /* Global transitions and animations */
        .analytics-card,
        .btn,
        .icon-btn,
        .nav-item,
        .logout-btn,
        .company-item,
        .recommendation-card {
            transition: all 0.2s ease-in-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .analytics-card {
            animation: fadeInUp 0.5s ease-out forwards;
        }

        /* Adjust animation delays */
        .analytics-card:nth-child(1) { animation-delay: 0.0s; }
        .analytics-card:nth-child(2) { animation-delay: 0.1s; }
        .analytics-card:nth-child(3) { animation-delay: 0.2s; }
        .analytics-card:nth-child(4) { animation-delay: 0.3s; }
        .analytics-card:nth-child(5) { animation-delay: 0.4s; }
        .analytics-card:nth-child(6) { animation-delay: 0.5s; }
        .analytics-card:nth-child(7) { animation-delay: 0.6s; }
        .analytics-card:nth-child(8) { animation-delay: 0.7s; }

        /* Specific styling for the header banner */
        .header-banner {
            background: linear-gradient(135deg, #5b1f1f, #8b3a3a);
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 20px;
            color: white;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .header-banner h1 {
            font-size: 2.2em;
            margin-bottom: 10px;
    font-weight: 700;
        }

        .header-banner p {
            font-size: 1.1em;
            opacity: 0.9;
        }

        /* Tabs in the main content area */
        .main-content-tabs {
            display: flex;
            background: #f3f4f6;
            border-radius: 10px;
            padding: 5px;
            margin-bottom: 20px;
            box-shadow: 0 1px 5px rgba(0,0,0,0.05);
            flex-wrap: wrap;
        }

        .main-content-tab {
            padding: 10px 20px;
            border: none;
            background: transparent;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            color: #6b7280;
            transition: all 0.3s;
            font-size: 14px;
        }

        .main-content-tab.active {
            background: linear-gradient(135deg, #ecc35c, #d4a843);
            color: #5b1f1f;
            box-shadow: 0 2px 8px rgba(236, 195, 92, 0.3);
        }

        .main-content-tab:hover:not(.active) {
            background: #e5e7eb;
            color: #5b1f1f;
}

/* Responsive Design */
        @media (max-width: 1024px) {
            .analytics-grid {
                grid-template-columns: 1fr;
            }
        }

@media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .main-wrapper {
                margin-left: 0;
            }

            .stats-row {
                flex-direction: column;
                gap: 10px;
            }

            .chart-container {
                height: 250px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include __DIR__ . '/../includes/partials/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-wrapper">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="top-bar-left">
                <div class="breadcrumb">Analytics / Dashboard</div>
            </div>
            <div class="top-bar-right">
                <!-- Buttons removed as per new design -->
            </div>
        </div>

        <!-- Header Banner -->
        <div class="header-banner">
            <h1>Your Comprehensive Analytics Dashboard</h1>
            <p>Dive deep into your career performance and readiness with AI-powered insights.</p>
        </div>

        <!-- Main Content Layout -->
        <div class="analytics-grid">
            <!-- Left Column (wider) -->
            <div class="left-column" style="display: flex; flex-direction: column; gap: 20px;">
                <!-- 1. Career Fit Breakdown -->
                <div class="analytics-card">
                    <div class="card-header">
                        <div class="card-icon"><i class="fas fa-bullseye"></i></div>
                        <div class="card-title">
                            <h3>Career Fit Breakdown</h3>
                            <p>Company Match Analysis</p>
                        </div>
                        <button class="btn btn-secondary" style="margin-left: auto; padding: 8px 15px; font-size: 13px;" onclick="showDetails('careerFit')">Details</button>
                    </div>
                    <div class="chart-container"><canvas id="careerFitDoughnutChart"></canvas></div>
                    <div class="company-match-list">
                        <!-- Company match items will be loaded here dynamically by JS -->
                        <div class="company-item">
                            <div class="company-info">
                                <div class="company-logo-mini">I</div>
                                <div>
                                    <h4>IBM</h4>
                                    <p>Technology</p>
                                </div>
                            </div>
                            <div class="match-percentage">85%</div>
                        </div>
                         <div class="company-item">
                            <div class="company-info">
                                <div class="company-logo-mini">In</div>
                                <div>
                                    <h4>Infosys</h4>
                                    <p>IT Services</p>
                                </div>
                            </div>
                            <div class="match-percentage">78%</div>
                        </div>
                    </div>
                </div>

                <!-- 3. Job Readiness Trendline -->
                <div class="analytics-card">
                    <div class="card-header">
                        <div class="card-icon"><i class="fas fa-chart-line"></i></div>
                        <div class="card-title">
                            <h3>Job Readiness Trend</h3>
                            <p>Your employability score over time</p>
                        </div>
                        <button class="btn btn-secondary" style="margin-left: auto; padding: 8px 15px; font-size: 13px;" onclick="showDetails('jobReadiness')">Details</button>
                    </div>
                    <div class="chart-container"><canvas id="jobReadinessLineChart"></canvas></div>
                </div>

                <!-- 5. AI Learning Progress Graph -->
                <div class="analytics-card">
                    <div class="card-header">
                        <div class="card-icon"><i class="fas fa-brain"></i></div>
                        <div class="card-title">
                            <h3>AI Learning Progress</h3>
                            <p>Timeline of skill improvements</p>
                        </div>
                        <button class="btn btn-secondary" style="margin-left: auto; padding: 8px 15px; font-size: 13px;" onclick="showDetails('aiProgress')">Details</button>
                    </div>
                    <div class="chart-container"><canvas id="learningProgressLineChart"></canvas></div>
                </div>

                <!-- 7. Engagement Analytics -->
                <div class="analytics-card">
                    <div class="card-header">
                        <div class="card-icon"><i class="fas fa-chart-pie"></i></div>
                        <div class="card-title">
                            <h3>Engagement Analytics</h3>
                            <p>Learning activity and progress</p>
                        </div>
                        <button class="btn btn-secondary" style="margin-left: auto; padding: 8px 15px; font-size: 13px;" onclick="showDetails('engagement')">Details</button>
                    </div>
                    <div class="chart-container"><canvas id="engagementDoughnutChart"></canvas></div>
                </div>
            </div>

            <!-- Right Column (narrower) -->
            <div class="right-column" style="display: flex; flex-direction: column; gap: 20px;">
                <!-- 2. Skill Proficiency Radar -->
                <div class="analytics-card">
                    <div class="card-header">
                        <div class="card-icon"><i class="fas fa-crosshairs"></i></div>
                        <div class="card-title">
                            <h3>Skill Proficiency Radar</h3>
                            <p>Strengths vs. Industry Average</p>
                        </div>
                        <button class="btn btn-secondary" style="margin-left: auto; padding: 8px 15px; font-size: 13px;" onclick="showDetails('skillRadar')">Details</button>
                    </div>
                    <div class="chart-container" style="height: 200px;"><canvas id="skillRadarChart"></canvas></div>
                    <div class="stats-row">
                        <div class="stat-box">
                            <div class="stat-value" id="employabilityScore">87%</div>
                            <div class="stat-label">Employability</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-value" id="jobReadinessScore">92%</div>
                            <div class="stat-label">Job Readiness</div>
                        </div>
                    </div>
                </div>

                <!-- 4. Peer Comparison Analytics -->
                <div class="analytics-card">
                    <div class="card-header">
                        <div class="card-icon"><i class="fas fa-users"></i></div>
                        <div class="card-title">
                            <h3>Peer Comparison Analytics</h3>
                            <p>Your performance vs. batch average</p>
                        </div>
                        <button class="btn btn-secondary" style="margin-left: auto; padding: 8px 15px; font-size: 13px;" onclick="showDetails('peerComparison')">Details</button>
                    </div>
                    <div class="chart-container"><canvas id="peerComparisonBarChart"></canvas></div>
                    <p style="font-size: 13px; color: var(--text-primary); margin-top: 15px;">
                        You are in the <strong style="color: var(--accent-color-primary);">top 25%</strong> for Python proficiency.
                    </p>
                    <p style="font-size: 13px; color: var(--text-primary);">
                        <strong style="color: var(--accent-color-primary);">Leaderboard:</strong> AI/ML, Web Dev, Data Science.
                    </p>
                </div>

                <!-- 6. Placement Insights -->
                <div class="analytics-card">
                    <div class="card-header">
                        <div class="card-icon"><i class="fas fa-briefcase"></i></div>
                        <div class="card-title">
                            <h3>Placement Insights</h3>
                            <p>Real-time college placement data</p>
                        </div>
                        <button class="btn btn-secondary" style="margin-left: auto; padding: 8px 15px; font-size: 13px;" onclick="showDetails('placementInsights')">Details</button>
                    </div>
                    <div class="stats-row">
                        <div class="stat-box">
                            <div class="stat-value">127</div>
                            <div class="stat-label">Placed</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-value">8.5 LPA</div>
                            <div class="stat-label">Avg. Package</div>
                        </div>
                    </div>
                    <div class="chart-container" style="height: 200px; margin-top: 15px;"><canvas id="domainDemandChart"></canvas></div>
                </div>

                <!-- 8. Actionable Insights Section -->
                <div class="analytics-card">
                    <div class="card-header">
                        <div class="card-icon"><i class="fas fa-lightbulb"></i></div>
                        <div class="card-title">
                            <h3>Actionable Insights</h3>
                            <p>AI-generated recommendations</p>
                        </div>
                        <button class="btn btn-secondary" style="margin-left: auto; padding: 8px 15px; font-size: 13px;" onclick="showDetails('recommendations')">Details</button>
                    </div>
                    <div class="recommendation-list">
                        <div class="recommendation-card">
                            <i class="fas fa-cogs"></i>
                            <div>
                                <h4>Complete Docker Module</h4>
                                <p>Unlock 2 new job opportunities in cloud engineering.</p>
                            </div>
                        </div>
                        <div class="recommendation-card">
                            <i class="fas fa-share-alt"></i>
                            <div>
                                <h4>Boost LinkedIn Activity</h4>
                                <p>Increase your visibility to recruiters by 30%.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
    <script>
        Chart.register(ChartDataLabels);

        // Get CSS variables
        const style = getComputedStyle(document.documentElement);
        const accentColorPrimary = style.getPropertyValue('--accent-color-primary').trim();
        const accentColorSecondary = style.getPropertyValue('--accent-color-secondary').trim();
        const textPrimary = style.getPropertyValue('--text-primary').trim();
        const textSecondary = style.getPropertyValue('--text-secondary').trim();
        const cardBg = style.getPropertyValue('--card-bg').trim();
        const chartColor1 = style.getPropertyValue('--chart-color-1').trim();
        const chartColor2 = style.getPropertyValue('--chart-color-2').trim();
        const chartColor3 = style.getPropertyValue('--chart-color-3').trim();
        const chartColor4 = style.getPropertyValue('--chart-color-4').trim();
        const chartColor5 = style.getPropertyValue('--chart-color-5').trim();

        // Helper for gradients in charts
        function createGradient(ctx, chartArea, colorStart, colorEnd) {
            const gradient = ctx.createLinearGradient(0, chartArea.bottom, 0, chartArea.top);
            gradient.addColorStop(0, colorStart);
            gradient.addColorStop(1, colorEnd);
            return gradient;
        }

        // 1. Career Fit Breakdown - Doughnut Chart
        function initializeCareerFitDoughnutChart() {
            const ctx = document.getElementById('careerFitDoughnutChart').getContext('2d');
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['IBM', 'Infosys', 'TCS', 'Accenture', 'Wipro'],
                    datasets: [{
                        data: [18, 22, 17, 24, 20],
                        backgroundColor: [
                            '#5b1f1f', /* Dark Brown */
                            '#ecc35c', /* Gold */
                            '#d4a843', /* Darker Gold */
                            '#8b3a3a', /* Medium Brown */
                            '#a0522d'  /* Sienna */
                        ],
                        hoverOffset: 8,
                        borderWidth: 0,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '75%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: '#5b1f1f',
                                boxWidth: 12,
                                padding: 15,
                            }
                        },
                        datalabels: {
                            color: 'white',
                            font: { size: 12, weight: 'bold' },
                            formatter: (value, context) => {
                                // Instead of percentage, show the raw value followed by '%'
                                return value + '%';
                            },
                            display: 'auto',
                        }
                    }
                }
            });
        }

        // 2. Skill Proficiency Radar Chart
        function initializeSkillRadarChart() {
            const ctx = document.getElementById('skillRadarChart').getContext('2d');
            new Chart(ctx, {
                type: 'radar',
                data: {
                    labels: ['Python', 'ML', 'Docker', 'AWS', 'React', 'SQL'],
                    datasets: [{
                        label: 'Your Skills',
                        data: [90, 70, 40, 60, 85, 75],
                        backgroundColor: 'rgba(91, 31, 31, 0.2)', /* #5b1f1f with transparency */
                        borderColor: '#5b1f1f',
                        pointBackgroundColor: '#5b1f1f',
                        pointBorderColor: '#fff',
                        pointHoverBackgroundColor: '#fff',
                        pointHoverBorderColor: '#5b1f1f',
                        borderWidth: 2,
                        fill: true,
                    }, {
                        label: 'Industry Average',
                        data: [75, 60, 55, 70, 80, 85],
                        backgroundColor: 'rgba(236, 195, 92, 0.2)', /* #ecc35c with transparency */
                        borderColor: '#ecc35c',
                        pointBackgroundColor: '#ecc35c',
                        pointBorderColor: '#fff',
                        pointHoverBackgroundColor: '#fff',
                        pointHoverBorderColor: '#ecc35c',
                        borderWidth: 2,
                        fill: true,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        r: {
                            beginAtZero: true,
                            max: 100,
                            grid: { color: '#e5e7eb' },
                            angleLines: { color: '#e5e7eb' },
                            pointLabels: { color: '#5b1f1f', font: { size: 11 } },
                            ticks: { backdropColor: 'white', color: '#6b7280', font: { size: 10 } }
                        }
                    },
                    plugins: {
                        legend: { position: 'top', labels: { color: '#5b1f1f', boxWidth: 12, padding: 15 } },
                        datalabels: { display: false }
                    }
                }
            });
        }

        // 3. Job Readiness Trendline - Line Chart
        function initializeJobReadinessLineChart() {
            const ctx = document.getElementById('jobReadinessLineChart').getContext('2d');
            
            // Use PRS data for job readiness score if available, otherwise generate a dummy trend
            let jobReadinessData = [];
            let jobReadinessLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

            if (phpPrsData && phpPrsData.job_readiness_score) {
                // Create a plausible trend leading up to the current score
                const currentScore = phpPrsData.job_readiness_score;
                for (let i = 0; i < 12; i++) {
                    jobReadinessData.push(currentScore - (11 - i) * (Math.random() * 2 + 0.5)); // Decreasing trend towards current
                }
                jobReadinessData = jobReadinessData.map(score => Math.max(50, Math.min(100, score))); // Cap scores
            } else {
                // Default dummy data if no PRS data
                jobReadinessData = [65, 68, 72, 75, 80, 82, 85, 87, 88, 89, 90, 91];
            }

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: jobReadinessLabels,
                    datasets: [{
                        label: 'Employability Score',
                        data: jobReadinessData,
                        fill: true,
                        backgroundColor: (context) => createGradient(context.chart.ctx, context.chart.chartArea, '#ecc35c', 'rgba(236, 195, 92, 0)'), /* Gold with transparency */
                        borderColor: '#ecc35c',
                        tension: 0.4,
                        pointRadius: 3,
                        pointBackgroundColor: '#ecc35c',
                        pointBorderColor: 'white',
                        pointHoverRadius: 5,
                        pointHoverBackgroundColor: 'white',
                        pointHoverBorderColor: '#ecc35c',
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: { grid: { display: false, borderColor: '#e5e7eb' }, ticks: { color: '#6b7280' } },
                        y: { beginAtZero: true, max: 100, grid: { color: '#e5e7eb' }, ticks: { color: '#6b7280' } }
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: { mode: 'index', intersect: false },
                        datalabels: { display: false }
                    }
                }
            });
        }

        // 4. Peer Comparison Analytics - Bar Chart
        function initializePeerComparisonBarChart() {
            const ctx = document.getElementById('peerComparisonBarChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Python', 'CGPA', 'Certifications', 'Projects'],
                    datasets: [{
                        label: 'Your Score',
                        data: [90, 8.5, 5, 4],
                        backgroundColor: '#5b1f1f',
                        borderColor: '#5b1f1f',
                        borderWidth: 1,
                        borderRadius: 4,
                    }, {
                        label: 'Batch Average',
                        data: [75, 7.8, 3, 2],
                        backgroundColor: '#ecc35c',
                        borderColor: '#ecc35c',
                        borderWidth: 1,
                        borderRadius: 4,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: { grid: { display: false, borderColor: '#e5e7eb' }, ticks: { color: '#6b7280' } },
                        y: { beginAtZero: true, grid: { color: '#e5e7eb' }, ticks: { color: '#6b7280' } }
                    },
                    plugins: {
                        legend: { position: 'top', labels: { color: '#5b1f1f', boxWidth: 12, padding: 15 } },
                        datalabels: {
                            color: 'white',
                            anchor: 'end',
                            align: 'top',
                            formatter: (value, context) => {
                                if (context.dataset.label === 'Your Score') {
                                    return value;
                                }
                                return null;
                            },
                            display: 'auto',
                        }
                    }
                }
            });
        }

        // 6. Placement Insights - Doughnut Chart (Domain Demand)
        function initializeDomainDemandChart() {
            const ctx = document.getElementById('domainDemandChart').getContext('2d');

            // Generate dynamic domain demand data based on available companies or dummy values
            const domainCounts = {};
            phpCompanies.forEach(company => {
                const industry = company.industry || 'Other';
                domainCounts[industry] = (domainCounts[industry] || 0) + 1;
            });

            let domainLabels = Object.keys(domainCounts);
            let domainData = Object.values(domainCounts).map(count => count * 10); // Scale for visualization

            // Ensure at least some data if phpCompanies is empty
            if (domainLabels.length === 0) {
                domainLabels = ['AI', 'Cloud', 'Web', 'Data Science', 'Other'];
                domainData = [35, 20, 15, 20, 10]; // Default dummy values
            }

            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: domainLabels,
                    datasets: [{
                        data: domainData,
                        backgroundColor: [
                            '#5b1f1f', /* Dark Brown */
                            '#ecc35c', /* Gold */
                            '#d4a843', /* Darker Gold */
                            '#8b3a3a', /* Medium Brown */
                            '#a0522d',  /* Sienna */
                            '#c0c0c0' // Additional color for more industries
                        ].slice(0, domainLabels.length),
                        hoverOffset: 8,
                        borderWidth: 0,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '75%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { color: '#5b1f1f', boxWidth: 12, padding: 15 }
                        },
                        datalabels: {
                            color: 'white',
                            font: { size: 12, weight: 'bold' },
                            formatter: (value, context) => {
                                const total = context.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                                const percentage = (value * 100 / total).toFixed(0) + '%';
                                return percentage;
                            },
                            display: 'auto',
                        }
                    }
                }
            });
        }

        // 5. AI Learning Progress Graph - Line Chart
        function initializeLearningProgressLineChart() {
            const ctx = document.getElementById('learningProgressLineChart').getContext('2d');

            let learningProgressLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            let learningProgressData = [];

            if (phpSkills.length > 0 || phpCertifications.length > 0) {
                // Simulate progress based on the number of skills and certifications
                const baseScore = 50; // Starting point
                const skillContribution = phpSkills.length * 5; // Each skill adds 5 points
                const certContribution = phpCertifications.length * 10; // Each certification adds 10 points
                const maxScore = 100;

                let currentProgress = baseScore;
                for (let i = 0; i < 12; i++) {
                    currentProgress += (skillContribution / 12) + (certContribution / 12) + (Math.random() * 2 - 1); // Gradual increase with some randomness
                    learningProgressData.push(Math.min(maxScore, Math.max(baseScore, currentProgress)));
                }
            } else {
                // Default dummy data if no skills or certifications
                learningProgressData = [50, 55, 60, 65, 75, 80, 82, 85, 87, 89, 90, 91];
            }

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: learningProgressLabels.slice(0, learningProgressData.length),
                    datasets: [{
                        label: 'Competency Score',
                        data: learningProgressData,
                        fill: true,
                        backgroundColor: (context) => createGradient(context.chart.ctx, context.chart.chartArea, '#ecc35c', 'rgba(236, 195, 92, 0)'), /* Gold with transparency */
                        borderColor: '#ecc35c',
                        tension: 0.4,
                        pointRadius: 3,
                        pointBackgroundColor: '#ecc35c',
                        pointBorderColor: 'white',
                        pointHoverRadius: 5,
                        pointHoverBackgroundColor: 'white',
                        pointHoverBorderColor: '#ecc35c',
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: { grid: { display: false, borderColor: '#e5e7eb' }, ticks: { color: '#6b7280' } },
                        y: { beginAtZero: true, max: 100, grid: { color: '#e5e7eb' }, ticks: { color: '#6b7280' } }
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: { mode: 'index', intersect: false },
                        datalabels: { display: false }
                    }
                }
            });
        }

        // 7. Engagement Analytics - Doughnut Chart
        function initializeEngagementDoughnutChart() {
            const ctx = document.getElementById('engagementDoughnutChart').getContext('2d');

            let engagementLabels = ['Learning Modules', 'Certifications Uploaded', 'Projects Completed', 'Experience Gained'];
            let engagementData = [];

            // Calculate engagement metrics based on available data
            const learningModulesCount = phpSkills.length + (phpAcademicData ? 1 : 0); // Simplified: assumes skills/academic data imply learning
            const certificationsUploadedCount = phpCertifications.length;
            const projectsCompletedCount = phpProjects.length;
            const experienceGainedCount = phpExperiences.length;

            if (learningModulesCount > 0 || certificationsUploadedCount > 0 || projectsCompletedCount > 0 || experienceGainedCount > 0) {
                engagementData.push(learningModulesCount * 10); // Scale for visualization
                engagementData.push(certificationsUploadedCount * 15);
                engagementData.push(projectsCompletedCount * 20);
                engagementData.push(experienceGainedCount * 25);
                engagementLabels = ['Learning Modules', 'Certifications Uploaded', 'Projects Completed', 'Experience Gained'];
            } else {
                // Default dummy data if no actual data
                engagementData = [40, 25, 35, 10]; // Added another value for 4 labels
                engagementLabels = ['Learning Modules', 'Certifications Uploaded', 'Mock Interview Progress', 'Resume Views'];
            }
            
            // Ensure data and labels have matching lengths, slice labels if data is shorter
            engagementLabels = engagementLabels.slice(0, engagementData.length);

            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: engagementLabels,
                    datasets: [{
                        data: engagementData,
                        backgroundColor: [
                            '#5b1f1f', /* Dark Brown */
                            '#ecc35c', /* Gold */
                            '#d4a843',  /* Darker Gold */
                            '#8b3a3a' // Additional color for more data
                        ].slice(0, engagementData.length),
                        hoverOffset: 8,
                        borderWidth: 0,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '75%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { color: '#5b1f1f', boxWidth: 12, padding: 15 }
                        },
                        datalabels: {
                            color: 'white',
                            font: { size: 12, weight: 'bold' },
                            formatter: (value, context) => {
                                const total = context.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                                const percentage = (value * 100 / total).toFixed(0) + '%';
                                return percentage;
                            },
                            display: 'auto',
                        }
                    }
                }
            });
        }

        // 8. Actionable Insights - Dummy Data for recommendations (already present in HTML)

        // Helper function for showing details (placeholder for future implementation)
        function showDetails(componentName) {
            alert(`Showing details for ${componentName}. This feature is under development.`);
        }

        // Initialize all charts and data on DOMContentLoaded
        document.addEventListener('DOMContentLoaded', function() {
            initializeCareerFitDoughnutChart();
            initializeSkillRadarChart();
            initializeJobReadinessLineChart();
            initializePeerComparisonBarChart();
            initializeDomainDemandChart();
            initializeLearningProgressLineChart();
            initializeEngagementDoughnutChart();

            // Animate progress bar for Performance Predictor
            const progressBar = document.querySelector('.performance-predictor-card .progress-bar');
            if (progressBar) {
                setTimeout(() => {
                    // Use PRS data if available, otherwise default
                    const projectedScore = phpPrsData && phpPrsData.placement_score ? phpPrsData.placement_score : 88;
                    document.getElementById('projectedFitScore').textContent = `${projectedScore}%`;
                    progressBar.style.width = `${projectedScore}%`; /* Set target width */

                    const prsLastUpdated = phpPrsData && phpPrsData.last_updated ? phpPrsData.last_updated : 'N/A';
                    document.getElementById('prsLastUpdated').textContent = prsLastUpdated;

                }, 100);
            }

            // Populate Actionable Insights
            const recommendationList = document.querySelector('.recommendation-list');
            if (recommendationList && phpPrsData && phpPrsData.recommendations) {
                const recommendations = JSON.parse(phpPrsData.recommendations);
                recommendationList.innerHTML = ''; // Clear existing dummy data
                if (recommendations.length > 0) {
                    recommendations.forEach(rec => {
                        const recommendationCard = document.createElement('div');
                        recommendationCard.classList.add('recommendation-card');
                        // Simple icon mapping for dummy data
                        let iconClass = 'fas fa-lightbulb'; 
                        if (rec.toLowerCase().includes('docker')) iconClass = 'fas fa-cogs';
                        else if (rec.toLowerCase().includes('linkedin')) iconClass = 'fas fa-share-alt';
                        else if (rec.toLowerCase().includes('sql')) iconClass = 'fas fa-database';
                        else if (rec.toLowerCase().includes('interview')) iconClass = 'fas fa-comments';

                        recommendationCard.innerHTML = `
                            <i class="${iconClass}"></i>
                            <div>
                                <h4>${rec}</h4>
                                <p>AI-generated insight to boost your career.</p>
                            </div>
                        `;
                        recommendationList.appendChild(recommendationCard);
                    });
                } else {
                    recommendationList.innerHTML = '<p style="text-align: center; color: #999; padding: 20px;">No recommendations available.</p>';
                }
            }
        });
    </script>
</body>
</html>