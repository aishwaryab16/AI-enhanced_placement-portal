<?php if (session_status() === PHP_SESSION_NONE) { session_start(); } ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GMU Placement Portal</title>
    <link rel="stylesheet" href="assets/styles.css?v=<?php echo time(); ?>">
</head>
<?php
    $roleClass = isset($_SESSION['role']) ? 'role-' . htmlspecialchars($_SESSION['role']) : 'role-guest';
    $pageClass = 'page-' . htmlspecialchars(pathinfo($_SERVER['PHP_SELF'], PATHINFO_FILENAME));
?>
<body class="<?php echo $roleClass . ' ' . $pageClass; ?>">
    <header class="site-header">
        <div class="inner container">
            <h1>GMU PLACEMENT PORTAL</h1>
            <nav>
                <?php if (isset($_SESSION['role'])): ?>
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                        <a class="btn" href="admin_dashboard_enhanced.php">Admin Dashboard</a>
                    <?php else: ?>
                        <a class="btn" href="student.php">Student Dashboard</a>
                    <?php endif; ?>
                    <a class="btn" href="logout.php">Logout</a>
                <?php else: ?>
                    <a class="btn" href="login.php">Login</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>
    <main class="container mt-24">


