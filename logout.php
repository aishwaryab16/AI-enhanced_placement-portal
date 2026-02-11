<?php
require_once __DIR__ . '/config.php';

// Clear all session data
$_SESSION = [];

// Delete session cookie
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

// Destroy session
session_destroy();

// Redirect to index page
redirect_to('index.php');


