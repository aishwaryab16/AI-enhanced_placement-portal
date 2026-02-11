<?php
require_once __DIR__ . '/../includes/config.php';

echo "<h1>🔧 Quick Student Adder</h1>";

// Handle adding student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_student'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $full_name = trim($_POST['full_name']);

    if (!empty($username) && !empty($email) && !empty($full_name)) {
        // Hash password properly
        $hashedPassword = password_hash('password', PASSWORD_DEFAULT);

        // Insert with correct field name
        $query = "INSERT INTO users (username, email, full_name, role, password_hash, created_at)
                  VALUES (?, ?, ?, 'student', ?, NOW())";

        $stmt = $mysqli->prepare($query);
        $stmt->bind_param('ssss', $username, $email, $full_name, $hashedPassword);

        echo "<div style='background: #e3f2fd; padding: 20px; border-radius: 8px; margin: 20px 0;'>";

        if ($stmt->execute()) {
            $newId = $stmt->insert_id;
            echo "<h2 style='color: green;'>✅ Student Added Successfully!</h2>";
            echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 15px 0;'>";
            echo "<h3>Login Credentials:</h3>";
            echo "<p><strong>Username:</strong> {$username}</p>";
            echo "<p><strong>Password:</strong> password</p>";
            echo "<p><strong>Email:</strong> {$email}</p>";
            echo "<p><strong>Student ID:</strong> {$newId}</p>";
            echo "</div>";

            echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 15px 0;'>";
            echo "<h3>🎉 Ready to Login!</h3>";
            echo "<p>You can now login with:</p>";
            echo "<ul>";
            echo "<li><strong>Username:</strong> {$username}</li>";
            echo "<li><strong>Password:</strong> password</li>";
            echo "</ul>";
            echo "<p><a href='login.php' style='background: #4caf50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 10px;'>Go to Login Page</a></p>";
            echo "</div>";
        } else {
            echo "<h2 style='color: red;'>❌ Error Adding Student</h2>";
            echo "<p>Error: " . $stmt->error . "</p>";
        }

        $stmt->close();
        echo "</div>";
    }
}

// Show current students
echo "<h2>👥 Current Students in Database</h2>";
$studentsQuery = "SELECT id, username, email, full_name FROM users WHERE role = 'student' ORDER BY id";
$studentsResult = $mysqli->query($studentsQuery);

if ($studentsResult && $studentsResult->num_rows > 0) {
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #5b1f1f; color: white;'><th>ID</th><th>Username</th><th>Email</th><th>Full Name</th><th>Login Status</th></tr>";

    while ($row = $studentsResult->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td style='font-weight: bold;'>" . htmlspecialchars($row['username']) . "</td>";
        echo "<td>" . htmlspecialchars($row['email']) . "</td>";
        echo "<td>" . htmlspecialchars($row['full_name'] ?? 'N/A') . "</td>";
        echo "<td style='color: green;'>✅ Ready to Login</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: #856404; background: #fff3cd; padding: 15px; border-radius: 5px;'>No students found. Add one below!</p>";
}
?>

<style>
    body { font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px; }
    h1, h2 { color: #5b1f1f; }
    input { padding: 10px; margin: 5px; width: 200px; border: 1px solid #ccc; border-radius: 5px; }
    button { background: #4caf50; color: white; padding: 12px 25px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
    button:hover { background: #45a049; }
    table { font-size: 14px; }
    a { color: #5b1f1f; text-decoration: none; }
    a:hover { text-decoration: underline; }
</style>

<h2>➕ Add New Student</h2>
<form method="POST" style="background: #f9f9f9; padding: 20px; border-radius: 8px;">
    <p>
        <label><strong>Username:</strong></label><br>
        <input type="text" name="username" placeholder="student2" required>
    </p>

    <p>
        <label><strong>Email:</strong></label><br>
        <input type="email" name="email" placeholder="student2@example.com" required>
    </p>

    <p>
        <label><strong>Full Name:</strong></label><br>
        <input type="text" name="full_name" placeholder="Student Two" required>
    </p>

    <p>
        <button type="submit" name="add_student">✅ Add Student</button>
    </p>
</form>

<div style="background: #e3f2fd; padding: 15px; border-radius: 5px; margin: 20px 0;">
    <h3>📋 How It Works:</h3>
    <ol>
        <li><strong>Add Student:</strong> Fill the form above and click "Add Student"</li>
        <li><strong>Auto-Hash Password:</strong> Password is automatically hashed as "password"</li>
        <li><strong>Correct Field Names:</strong> Uses proper database field names</li>
        <li><strong>Immediate Login:</strong> Student can login right away with username + "password"</li>
    </ol>
</div>

<hr>
<p><a href="login.php">← Back to Login</a> | <a href="admin.php">Admin Dashboard</a></p>
