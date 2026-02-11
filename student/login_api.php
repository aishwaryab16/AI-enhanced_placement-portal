<?php
/**
 * Login API Endpoint
 * Handles user authentication via JSON POST request
 */

// Start output buffering to prevent any accidental output
ob_start();

// Set JSON header early
header('Content-Type: application/json');

// Log the request method for debugging
$request_method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
error_log("Login API: Request method = $request_method");

// Only allow POST requests
if ($request_method !== 'POST') {
    ob_end_clean();
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use POST.',
        'received_method' => $request_method
    ]);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!isset($input['username']) || !isset($input['password'])) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Username and password are required.'
    ]);
    exit;
}

$username = trim($input['username']);
$password = trim($input['password']);

if (empty($username) || empty($password)) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Username and password cannot be empty.'
    ]);
    exit;
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth_service.php';

try {
    $user = authenticate_user_locally($mysqli, $username, $password);
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'user_id' => $user['id'],
        'username' => $user['username'],
        'role' => $user['role'],
        'email' => $user['email'],
        'full_name' => $user['full_name']
    ]);
} catch (AuthException $e) {
    ob_end_clean();
    http_response_code($e->getHttpCode());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} catch (Exception $e) {
    $error_msg = $e->getMessage();
    $error_file = $e->getFile();
    $error_line = $e->getLine();
    error_log("Login API Exception: $error_msg in $error_file on line $error_line");
    error_log('Login API Stack Trace: ' . $e->getTraceAsString());
    if (isset($mysqli) && isset($mysqli->error) && $mysqli->error) {
        error_log("Login API Database Error: " . $mysqli->error);
    }
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred during authentication. Please try again later.',
        'debug' => (defined('DEBUG_MODE') && DEBUG_MODE) ? $error_msg : null
    ]);
} catch (Error $e) {
    $error_msg = $e->getMessage();
    $error_file = $e->getFile();
    $error_line = $e->getLine();
    error_log("Login API Fatal Error: $error_msg in $error_file on line $error_line");
    error_log('Login API Stack Trace: ' . $e->getTraceAsString());
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred during authentication. Please try again later.'
    ]);
}
?>

