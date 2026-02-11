<?php if (session_status() === PHP_SESSION_NONE) { session_start(); } ?>
<?php
    $projectRoot = realpath(__DIR__ . '/../../');
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    $baseUrl = '';

    if ($projectRoot && $docRoot) {
        $normalizedProject = str_replace('\\', '/', $projectRoot);
        $normalizedRoot = rtrim(str_replace('\\', '/', $docRoot), '/');

        if (strpos($normalizedProject, $normalizedRoot) === 0) {
            $baseUrl = substr($normalizedProject, strlen($normalizedRoot));
            if ($baseUrl !== '') {
                $baseUrl = '/' . ltrim($baseUrl, '/');
            }
        }
    }

    $basePrefix = $baseUrl === '' ? '' : rtrim($baseUrl, '/');

    $assetUrl = ($basePrefix === '' ? '' : $basePrefix) . '/assets/styles.css';
    if ($assetUrl === '') {
        $assetUrl = '/assets/styles.css';
    }

    $studentDashboardUrl = ($basePrefix === '' ? '' : $basePrefix) . '/student/profile.php';
    $adminDashboardUrl = ($basePrefix === '' ? '' : $basePrefix) . '/admin/index.php';
    $logoutUrl = ($basePrefix === '' ? '' : $basePrefix) . '/logout.php';
    $loginUrl = ($basePrefix === '' ? '' : $basePrefix) . '/login.php';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GMU Placement Portal</title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($assetUrl, ENT_QUOTES); ?>?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(($basePrefix === '' ? '' : $basePrefix) . '/assets/css/fontawesome-all.min.css', ENT_QUOTES); ?>?v=<?php echo time(); ?>">
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
                        <a class="btn" href="<?php echo htmlspecialchars($adminDashboardUrl, ENT_QUOTES); ?>">Admin Dashboard</a>
                    <?php else: ?>
                        <a class="btn" href="<?php echo htmlspecialchars($studentDashboardUrl, ENT_QUOTES); ?>">Student Dashboard</a>
                    <?php endif; ?>
                    <a class="btn" href="<?php echo htmlspecialchars($logoutUrl, ENT_QUOTES); ?>">Logout</a>
                <?php else: ?>
                    <a class="btn" href="<?php echo htmlspecialchars($loginUrl, ENT_QUOTES); ?>">Login</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>
    <main class="container mt-24">


