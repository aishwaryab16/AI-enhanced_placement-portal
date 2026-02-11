<?php
// Fetch student data for sidebar
$student_id = $_SESSION['user_id'];
$stmt = $mysqli->prepare('SELECT * FROM users WHERE id = ?');
$stmt->bind_param('i', $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="logo">
        <div class="logo-icon">P</div>
        <div class="logo-text">Placement</div>
    </div>

    <div class="user-profile">
        <div class="user-avatar">
            <?php echo strtoupper(substr($student['full_name'] ?? 'S', 0, 1)); ?>
        </div>
        <div class="user-info">
            <div class="user-name"><?php echo htmlspecialchars(explode(' ', $student['full_name'] ?? 'Student')[0]); ?></div>
            <div class="user-role">Student</div>
        </div>
    </div>

    <nav class="nav-menu">
        <a href="profile.php" class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'profile.php') ? 'active' : ''; ?>">
            <i class="fas fa-user"></i>
            <span>My Profile</span>
        </a>
        <a href="job_opportunities.php" class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'job_opportunities.php') ? 'active' : ''; ?>">
            <i class="fas fa-briefcase"></i>
            <span>Job Opportunities</span>
        </a>
        <a href="resources.php" class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'resources.php') ? 'active' : ''; ?>">
            <i class="fas fa-building"></i>
            <span>Company Resources</span>
        </a>
        <a href="resume_builder.php" class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'resume_builder.php') ? 'active' : ''; ?>">
            <i class="fas fa-file-alt"></i>
            <span>Resume Builder</span>
        </a>
        <a href="career_advisor.php" class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'career_advisor.php') ? 'active' : ''; ?>">
            <i class="fas fa-robot"></i>
            <span>Career Advisor</span>
        </a>
        <a href="ai_interview.php" class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'ai_interview.php') ? 'active' : ''; ?>">
            <i class="fas fa-microphone"></i>
            <span>AI Interview</span>
        </a>
        <a href="interviews.php" class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'interviews.php') ? 'active' : ''; ?>">
            <i class="fas fa-building"></i>
            <span>Company Interview</span>
        </a>
        <a href="calendar.php" class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'calendar.php') ? 'active' : ''; ?>">
            <i class="fas fa-calendar"></i>
            <span>Calendar</span>
        </a>
    </nav>

    <a href="../logout.php" class="logout-btn">
        <i class="fas fa-sign-out-alt"></i>
        <span>Log Out</span>
    </a>
</div>
