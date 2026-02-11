<?php
session_start();
require_once "includes/config.php"; // contains DB_HOST, DB_USER, DB_PASS, DB_NAME

// If already logged in, redirect to appropriate dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin/index.php");
    } elseif ($_SESSION['role'] === 'placement_officer') {
        header("Location: placement_manager/placement_officer.php");
    } else {
        header("Location: student/profile.php");
    }
    exit;
}

// Connect to database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME,DB_PORT);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$error = "";

// Get preferred role from URL parameter
$preferred_role = isset($_GET['role']) ? $_GET['role'] : '';

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (!empty($username) && !empty($password)) {
        // Prepared statement to prevent SQL injection
        $stmt = $conn->prepare("SELECT id, username, password_hash, role FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows == 1) {
            $stmt->bind_result($id, $db_username, $db_password_hash, $db_role);
            $stmt->fetch();

            // Verify password (assuming it's hashed with password_hash)
            if (password_verify($password, $db_password_hash)) {
                $_SESSION['user_id'] = $id;
                $_SESSION['username'] = $db_username;
                $_SESSION['role'] = $db_role;

                // Redirect based on role
                if ($db_role === 'admin') {
                    header("Location: admin/index.php");
                } elseif ($db_role === 'placement_officer') {
                    // Check if username is "placement" for super admin
                    if ($db_username === 'placement') {
                        header("Location: placement_manager/index.php");
                    } else {
                        header("Location: placement_manager/placement_officer.php");
                    }
                } else {
                    header("Location: student/profile.php");
                }
                exit;
            } else {
                $error = "Invalid password.";
            }
        } else {
            $error = "No user found with that username.";
        }
        $stmt->close();
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
    .back-button {
      display: block;
      text-align: center;
      margin-top: 15px;
      color: rgba(128, 0, 32, 0.7);
      text-decoration: none;
      font-size: 14px;
      font-weight: 400;
      transition: color 0.3s ease;
    }
    .back-button:hover {
      color: rgba(128, 0, 32, 1);
      text-decoration: underline;
    }
    .login-right h2 {
      font-size: 22px;
      margin-bottom: 30px;
      color:rgba(128, 0, 32, 0.6);
      font-weight: 600;
      text-align: center;
      letter-spacing: 0.5px;
    }
    .login-right input, .login-right select {
      width: 100%;
      padding: 15px;
      margin: 10px 0;
      border: 2px solid #e0e0e0;
      border-radius: 8px;
      font-size: 16px;
      box-sizing: border-box;
      transition: border-color 0.3s;
    }
    .login-right input:focus, .login-right select:focus {
      outline: none;
      border-color:rgba(128, 0, 32, 0.62);
    }
    .login-right select {
      background: white;
      cursor: pointer;
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
      font-size: 14px;
      text-align: center;
    }
    @media (max-width: 768px) {
      .login-card {
        flex-direction: column;
        width: 100%;
        max-width: 95vw;
      }
      .login-left {
        padding: 30px 20px;
      }
      .login-left img {
        max-width: 200px;
      }
      .login-right {
        padding: 30px 20px;
      }
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
      <form method="POST" action="">
       
        <input type="text" name="username" placeholder="Username" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">LOGIN</button>
        <a href="index.php" class="back-button">← Back to Home</a>
      </form>
      <?php if (!empty($error)) { echo "<p class='error'>$error</p>"; } ?>
    </div>
  </div>
</body>
</html>
