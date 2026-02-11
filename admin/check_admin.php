<?php
require_once __DIR__ . '/../includes/config.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Admin User Check</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #5b1f1f, #e2b458);
            padding: 40px;
            color: #333;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        h1 {
            color: #5b1f1f;
            border-bottom: 3px solid #e2b458;
            padding-bottom: 15px;
        }
        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        .info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #5b1f1f;
            color: white;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: linear-gradient(135deg, #5b1f1f, #4a1919);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin: 10px 5px;
            font-weight: 600;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(91, 31, 31, 0.3);
        }
        .btn-secondary {
            background: linear-gradient(135deg, #e2b458, #f0d084);
            color: #000;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
        }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔍 Admin User Diagnostic</h1>";

// Check database connection
echo "<div class='info'><strong>✓ Database Connected:</strong> placements (Port: 3307)</div>";

// Check if users table exists
$result = $mysqli->query("SHOW TABLES LIKE 'users'");
if ($result->num_rows == 0) {
    echo "<div class='error'>
        <strong>❌ Error:</strong> 'users' table does not exist!<br><br>
        <strong>Solution:</strong> You need to create the users table first.
        <a href='create_database.php' class='btn'>Create Database Tables</a>
    </div>";
    exit;
}

echo "<div class='success'><strong>✓ Users Table:</strong> Exists</div>";

// Check for admin users
$result = $mysqli->query("SELECT id, username, role, created_at FROM users WHERE role IN ('admin', 'placement_officer')");

if ($result->num_rows == 0) {
    echo "<div class='error'>
        <strong>❌ No Admin Users Found!</strong><br><br>
        You need to create an admin user. Click the button below to create one.
    </div>";
    
    // Create admin button
    echo "<form method='POST' action=''>
        <input type='hidden' name='create_admin' value='1'>
        <button type='submit' class='btn'>Create Admin User Now</button>
    </form>";
} else {
    echo "<div class='success'><strong>✓ Admin Users Found:</strong> " . $result->num_rows . "</div>";
    
    echo "<h2>Admin Users in Database:</h2>";
    echo "<table>
        <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Role</th>
            <th>Created</th>
            <th>Test Login</th>
        </tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>
            <td>" . $row['id'] . "</td>
            <td><strong>" . htmlspecialchars($row['username']) . "</strong></td>
            <td><span style='background: #e2b458; padding: 4px 12px; border-radius: 12px; color: #000;'>" . $row['role'] . "</span></td>
            <td>" . $row['created_at'] . "</td>
            <td><code>Password: " . $row['username'] . "</code></td>
        </tr>";
    }
    echo "</table>";
    
    echo "<div class='info'>
        <strong>📝 Login Credentials:</strong><br>
        Use the username from the table above, and the password is the same as the username.<br>
        Example: Username: <code>admin</code>, Password: <code>admin</code>
    </div>";
}

// Handle admin creation
if (isset($_POST['create_admin'])) {
    $username = 'admin';
    $password = 'admin';
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $mysqli->prepare("INSERT INTO users (username, password_hash, role, email, full_name) VALUES (?, ?, 'admin', 'admin@placement.com', 'System Administrator')");
    $stmt->bind_param("ss", $username, $password_hash);
    
    if ($stmt->execute()) {
        echo "<div class='success'>
            <strong>✅ Admin User Created Successfully!</strong><br><br>
            <strong>Username:</strong> <code>admin</code><br>
            <strong>Password:</strong> <code>admin</code><br><br>
            <a href='login.php' class='btn'>Go to Login Page</a>
        </div>";
    } else {
        echo "<div class='error'>
            <strong>❌ Error creating admin:</strong> " . $stmt->error . "
        </div>";
    }
}

// Check all users
echo "<h2>All Users in Database:</h2>";
$result = $mysqli->query("SELECT id, username, role, email FROM users ORDER BY role, username");

if ($result->num_rows == 0) {
    echo "<div class='info'>No users found in database.</div>";
} else {
    echo "<table>
        <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Role</th>
            <th>Email</th>
        </tr>";
    
    while ($row = $result->fetch_assoc()) {
        $roleColor = $row['role'] == 'admin' ? '#e2b458' : ($row['role'] == 'placement_officer' ? '#17a2b8' : '#6c757d');
        echo "<tr>
            <td>" . $row['id'] . "</td>
            <td><strong>" . htmlspecialchars($row['username']) . "</strong></td>
            <td><span style='background: $roleColor; padding: 4px 12px; border-radius: 12px; color: " . ($row['role'] == 'student' ? 'white' : '#000') . ";'>" . $row['role'] . "</span></td>
            <td>" . htmlspecialchars($row['email']) . "</td>
        </tr>";
    }
    echo "</table>";
}

echo "
        <div style='margin-top: 30px; padding-top: 20px; border-top: 2px solid #e9ecef;'>
            <a href='login.php' class='btn'>Go to Login Page</a>
            <a href='placement_cell_dashboard.php' class='btn btn-secondary'>Go to Dashboard</a>
        </div>
    </div>
</body>
</html>";
?>
