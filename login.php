<?php
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth_service.php';

// If already logged in → redirect
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin/index.php");
    } elseif ($_SESSION['role'] === 'placement_officer') {
        $username = $_SESSION['username'] ?? '';
        if ($username === 'placement') {
            header("Location: placement_manager/index.php");
        } elseif ($username === 'internship_admin') {
            header("Location: internship_dashboard/index.php");
        } else {
            header("Location: placement_manager/placement_officer.php");
        }
    } elseif ($_SESSION['role'] === 'internship_admin') {
        header("Location: internship/internship_admin.php");
    } else {
        header("Location: student/profile.php");
    }
    exit;
}

$error = "";

// When form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($username) && !empty($password)) {

        try {
            $user = authenticate_user_locally($mysqli, $username, $password);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            if ($user['role'] === "admin") {
                header("Location: admin/index.php");
            } elseif ($user['role'] === "placement_officer") {
                header("Location: placement_manager/placement_officer.php");
            } elseif ($user['role'] === "internship_admin") {
                header("Location: internship/internship_admin.php");
            } else {
                header("Location: student/profile.php");
            }
            exit;
        } catch (AuthException $authEx) {
            $error = $authEx->getMessage();
        } catch (Exception $ex) {
            error_log('Login error: ' . $ex->getMessage());
            $error = "Unable to connect to authentication service. Please check if the server is running.";
        }
    } else {
        $error = "Please enter both username and password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>GMU Placement Portal - Login</title>
  <style>
    body {
      margin: 0;
      padding: 0;
      font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
      background:rgb(246, 246, 237);
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
    }
    .login-card {
      background-color: white;
      border-radius: 15px;
      box-shadow: 0 8px 25px rgba(0,0,0,0.15);
      width: 900px;
      max-width: 95vw;
      display: flex;
      overflow: hidden;
      min-height: 500px;
    }
    .login-left {
      flex: 1;
      background:rgb(96, 9, 9);
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      padding: 40px;
      position: relative;
    }
    .login-left img {
      width: 100%;
      max-width: 280px;
      height: auto;
      margin-bottom: 30px;
      filter: drop-shadow(0 4px 8px rgba(0,0,0,0.3));
    }
    .login-left h1 {
      font-size: 26px;
      margin: 0;
      font-weight: 600;
      color: #ffffff;
      text-align: center;
      letter-spacing: 1px;
    }
    .login-left p.tagline {
      font-style: italic;
      font-size: 14px;
      margin: 10px 0 0;
      color: #f5e6d3;
      text-align: center;
    }
    .login-right {
      flex: 1;
      padding: 50px 40px;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }
    .login-right h2 {
      font-size: 22px;
      margin-bottom: 30px;
      color:rgba(128, 0, 32, 0.6);
      font-weight: 600;
      text-align: center;
    }
    .login-right input {
      width: 100%;
      padding: 15px;
      margin: 10px 0;
      border: 2px solid #e0e0e0;
      border-radius: 8px;
      font-size: 16px;
    }
    .login-right input:focus {
      outline: none;
      border-color:rgba(128, 0, 32, 0.62);
    }
    .login-right button {
      width: 100%;
      padding: 15px;
      margin-top: 20px;
      background: linear-gradient(135deg, rgb(249, 229, 115), rgb(238, 192, 107));
      color: #1a1a1a;
      font-size: 16px;
      font-weight: bold;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      transition: all 0.3s;
    }
    .login-right button:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(249, 229, 115, 0.4);
    }
    .error {
      color: #d32f2f;
      margin-top: 10px;
      text-align: center;
    }
  </style>
</head>
<body>

  <div class="login-card">
    <div class="login-left">
      <img src="assets/images/gmu (1).png"  alt="GM University Logo">
      <h1>GM UNIVERSITY</h1>
      <p class="tagline">Innovating Minds</p>
    </div>

    <div class="login-right">
      <h2>Placement Portal</h2>

      <form method="POST">
        <input type="text" name="username" placeholder="Username" autocomplete="username" required>
        <input type="password" name="password" placeholder="Password" autocomplete="current-password" required>
        <button type="submit">LOGIN</button>
      </form>

      <?php if (!empty($error)) { echo "<p class='error'>$error</p>"; } ?>
    </div>
  </div>

</body>
</html>