<?php
/**
 * Configuration File
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'placement_portal');
define('DB_PORT', 3306);

// Create mysqli-compatible wrapper classes
class MySQLiWrapper {
    private $pdo;
    public $connect_error = null;
    public $error = '';
    public $errno = 0;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function query($sql) {
        try {
            $stmt = $this->pdo->query($sql);
            $this->error = '';
            $this->errno = 0;
            return new MySQLiResultWrapper($stmt);
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            $this->errno = $e->getCode();
            return false;
        }
    }
    
    public function prepare($sql) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $this->error = '';
            $this->errno = 0;
            return new MySQLiStmtWrapper($stmt, $this);
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            $this->errno = $e->getCode();
            return false;
        }
    }
    
    public function real_escape_string($string) {
        return substr($this->pdo->quote($string), 1, -1);
    }
    
    public function set_charset($charset) {
        return true;
    }
    
    public function close() {
        $this->pdo = null;
    }
    
    public function __get($name) {
        if ($name === 'insert_id') {
            return $this->pdo->lastInsertId();
        }
        if ($name === 'affected_rows') {
            return $this->pdo->rowCount();
        }
        return null;
    }
}

class MySQLiResultWrapper {
    private $stmt;
    public $num_rows = 0;
    
    public function __construct($stmt) {
        $this->stmt = $stmt;
        if ($stmt) {
            $this->num_rows = $stmt->rowCount();
        }
    }
    
    public function fetch_assoc() {
        return $this->stmt ? $this->stmt->fetch(PDO::FETCH_ASSOC) : null;
    }
    
    public function fetch_all($mode = MYSQLI_ASSOC) {
        return $this->stmt ? $this->stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }
    
    public function free() {
        $this->stmt = null;
    }
}

class MySQLiStmtWrapper {
    private $stmt;
    private $wrapper;
    public $num_rows = 0;
    public $error = '';
    
    public function __construct($stmt, $wrapper = null) {
        $this->stmt = $stmt;
        $this->wrapper = $wrapper;
    }
    
    public function bind_param($types, &...$vars) {
        if (!$this->stmt) return false;
        foreach ($vars as $i => $var) {
            $this->stmt->bindValue($i + 1, $var);
        }
        return true;
    }
    
    public function execute() {
        try {
            if (!$this->stmt) return false;
            $result = $this->stmt->execute();
            if ($this->wrapper) {
                $this->wrapper->error = '';
                $this->wrapper->errno = 0;
            }
            return $result;
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            if ($this->wrapper) {
                $this->wrapper->error = $e->getMessage();
                $this->wrapper->errno = $e->getCode();
            }
            return false;
        }
    }
    
    public function store_result() {
        // For PDO, we need to fetch all results to get row count
        // This is called after execute() in mysqli style
        if ($this->stmt) {
            $this->num_rows = $this->stmt->rowCount();
        }
        return true;
    }
    
    public function get_result() {
        return new MySQLiResultWrapper($this->stmt);
    }
    
    public function close() {
        $this->stmt = null;
    }
    
    public function __get($name) {
        if ($name === 'insert_id') {
            return $this->stmt->lastInsertId();
        }
        if ($name === 'affected_rows') {
            return $this->stmt->rowCount();
        }
        return null;
    }
}

// Connect to database using PDO (with timeout)
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5  // 5 second timeout
        ]
    );
    
    $mysqli = new MySQLiWrapper($pdo);
    
} catch (PDOException $e) {
    // Show a user-friendly error message
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Database Connection Error</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 50px; background: #f5f5f5; }
            .error-box { background: white; padding: 30px; border-radius: 10px; max-width: 600px; margin: 0 auto; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            h1 { color: #d32f2f; }
            .details { background: #fff3cd; padding: 15px; border-radius: 5px; margin-top: 20px; }
            .solution { background: #d1ecf1; padding: 15px; border-radius: 5px; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class='error-box'>
            <h1>⚠️ Database Connection Failed</h1>
            <p>Unable to connect to the MySQL database.</p>
            <div class='details'>
                <strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "
            </div>
            <div class='solution'>
                <strong>Solutions:</strong>
                <ol>
                    <li>Make sure MySQL/XAMPP/WAMP is running</li>
                    <li>Verify database credentials in config.php</li>
                    <li>Check if database 'placements' exists</li>
                    <li>Ensure MySQL is running on port " . DB_PORT . "</li>
                </ol>
            </div>
        </div>
    </body>
    </html>";
    exit;
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Role-based access control
 */
function require_role($role) {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $role) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Check if user is logged in
 */
function require_login() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Check if user is logged in (returns boolean)
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Redirect to a page
 */
function redirect_to($page) {
    header("Location: " . $page);
    exit;
}

/**
 * Sanitize input
 */
function clean_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Site Configuration
define('SITE_NAME', 'GMU Placement Portal');
define('SITE_URL', 'http://localhost/placement');

// OpenAI API Configuration (optional - for AI features)
// define('OPENAI_API_KEY', 'your-openai-api-key-here'); // Unused - API key now handled via environment variable in ai_proxy.php

// Email Configuration (for email.php)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
define('FROM_EMAIL', 'your-email@gmail.com');
define('FROM_NAME', 'GMU Placement Portal');
?>
