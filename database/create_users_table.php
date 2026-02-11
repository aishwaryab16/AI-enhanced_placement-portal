<?php
/**
 * Create Users Table Script
 * This script creates the users table if it doesn't exist
 */

require_once __DIR__ . '/../includes/config.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Create Users Table</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #5b1f1f; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .code { background: #f4f4f4; padding: 15px; border-radius: 5px; font-family: monospace; margin: 15px 0; overflow-x: auto; }
        button { background: #5b1f1f; color: white; padding: 12px 24px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        button:hover { background: #4a1919; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Create Users Table</h1>

<?php
// Check if database connection is available
if (!isset($mysqli) || !$mysqli) {
    echo '<div class="error"><strong>❌ Error:</strong> Database connection failed. Please check your configuration.</div>';
    exit;
}

echo '<div class="info"><strong>✓ Database Connected:</strong> ' . DB_NAME . '</div>';

// Check if users table exists
$table_check = $mysqli->query("SHOW TABLES LIKE 'users'");
$table_exists = $table_check && $table_check->num_rows > 0;

if ($table_exists) {
    echo '<div class="success"><strong>✓ Users Table Exists</strong></div>';
    
    // Check table structure
    $columns_result = $mysqli->query("SHOW COLUMNS FROM users");
    $columns = [];
    if ($columns_result) {
        while ($col = $columns_result->fetch_assoc()) {
            $columns[] = $col['Field'];
        }
    }
    
    echo '<div class="info"><strong>Table Columns:</strong> ' . implode(', ', $columns) . '</div>';
    
    // Check for required columns
    $required = ['id', 'username', 'password_hash', 'role'];
    $missing = array_diff($required, $columns);
    
    if (!empty($missing)) {
        echo '<div class="error"><strong>⚠ Missing Columns:</strong> ' . implode(', ', $missing) . '</div>';
        echo '<p>Click the button below to add missing columns or recreate the table.</p>';
    } else {
        echo '<div class="success"><strong>✓ All Required Columns Present</strong></div>';
        echo '<p>The users table is properly configured. You can now use the login system.</p>';
        echo '<p><a href="../login.php"><button>Go to Login Page</button></a></p>';
        exit;
    }
} else {
    echo '<div class="error"><strong>❌ Users Table Does Not Exist</strong></div>';
    echo '<p>The users table needs to be created. Click the button below to create it.</p>';
}

// Handle table creation
if (isset($_POST['create_table'])) {
    echo '<div class="info"><strong>Creating users table...</strong></div>';
    
    // SQL to create users table
    $create_sql = "
    CREATE TABLE IF NOT EXISTS `users` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `username` varchar(50) NOT NULL,
        `password_hash` varchar(255) NOT NULL,
        `role` varchar(20) NOT NULL DEFAULT 'student',
        `email` varchar(100) DEFAULT NULL,
        `full_name` varchar(100) DEFAULT NULL,
        `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
        `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `username` (`username`),
        KEY `role` (`role`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    if ($mysqli->query($create_sql)) {
        echo '<div class="success"><strong>✅ Users Table Created Successfully!</strong></div>';
        
        // Create a default admin user
        $admin_username = 'admin';
        $admin_password = 'admin';
        $admin_password_hash = password_hash($admin_password, PASSWORD_DEFAULT);
        
        $insert_sql = "INSERT INTO users (username, password_hash, role, email, full_name) 
                       VALUES (?, ?, 'admin', 'admin@placement.com', 'System Administrator')
                       ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)";
        
        $stmt = $mysqli->prepare($insert_sql);
        if ($stmt) {
            $stmt->bind_param('ss', $admin_username, $admin_password_hash);
            if ($stmt->execute()) {
                echo '<div class="success">';
                echo '<strong>✅ Default Admin User Created</strong><br>';
                echo '<strong>Username:</strong> admin<br>';
                echo '<strong>Password:</strong> admin<br>';
                echo '<strong>⚠️ Important:</strong> Please change the admin password after first login!';
                echo '</div>';
            }
            $stmt->close();
        }
        
        echo '<p><a href="../login.php"><button>Go to Login Page</button></a></p>';
    } else {
        echo '<div class="error"><strong>❌ Error Creating Table:</strong> ' . $mysqli->error . '</div>';
        echo '<div class="code"><strong>SQL Used:</strong><br>' . htmlspecialchars($create_sql) . '</div>';
    }
} else if (isset($_POST['add_columns'])) {
    // Add missing columns
    echo '<div class="info"><strong>Adding missing columns...</strong></div>';
    
    $alterations = [];
    
    if (!in_array('id', $columns)) {
        $alterations[] = "ADD COLUMN `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST";
    }
    if (!in_array('username', $columns)) {
        $alterations[] = "ADD COLUMN `username` varchar(50) NOT NULL UNIQUE";
    }
    if (!in_array('password_hash', $columns)) {
        $alterations[] = "ADD COLUMN `password_hash` varchar(255) NOT NULL";
    }
    if (!in_array('role', $columns)) {
        $alterations[] = "ADD COLUMN `role` varchar(20) NOT NULL DEFAULT 'student'";
    }
    if (!in_array('email', $columns)) {
        $alterations[] = "ADD COLUMN `email` varchar(100) DEFAULT NULL";
    }
    if (!in_array('full_name', $columns)) {
        $alterations[] = "ADD COLUMN `full_name` varchar(100) DEFAULT NULL";
    }
    
    if (!empty($alterations)) {
        $alter_sql = "ALTER TABLE `users` " . implode(", ", $alterations);
        
        if ($mysqli->query($alter_sql)) {
            echo '<div class="success"><strong>✅ Missing Columns Added Successfully!</strong></div>';
            echo '<p><a href="?"><button>Refresh</button></a></p>';
        } else {
            echo '<div class="error"><strong>❌ Error Adding Columns:</strong> ' . $mysqli->error . '</div>';
        }
    } else {
        echo '<div class="info">No columns to add.</div>';
    }
} else {
    // Show form to create table
    if (!$table_exists) {
        echo '<form method="POST">';
        echo '<input type="hidden" name="create_table" value="1">';
        echo '<button type="submit">Create Users Table</button>';
        echo '</form>';
    } else {
        echo '<form method="POST">';
        echo '<input type="hidden" name="add_columns" value="1">';
        echo '<button type="submit">Add Missing Columns</button>';
        echo '</form>';
    }
    
    echo '<div class="code">';
    echo '<strong>SQL to Create Table (if you prefer to run it manually in phpMyAdmin):</strong><br><br>';
    echo htmlspecialchars("
CREATE TABLE IF NOT EXISTS `users` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `username` varchar(50) NOT NULL,
    `password_hash` varchar(255) NOT NULL,
    `role` varchar(20) NOT NULL DEFAULT 'student',
    `email` varchar(100) DEFAULT NULL,
    `full_name` varchar(100) DEFAULT NULL,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `username` (`username`),
    KEY `role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo '</div>';
}
?>

    </div>
</body>
</html>

