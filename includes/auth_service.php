<?php
/**
 * Shared authentication helper so both login.php and student/login_api.php
 * can reuse the same credential validation logic without making HTTP calls.
 */

if (!class_exists('AuthException')) {
    class AuthException extends Exception {
        protected $httpCode;

        public function __construct($message, $httpCode = 400)
        {
            parent::__construct($message, $httpCode);
            $this->httpCode = $httpCode;
        }

        public function getHttpCode()
        {
            return $this->httpCode;
        }
    }
}

if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle)
    {
        $needle = (string)$needle;
        if ($needle === '') {
            return true;
        }
        return strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

if (!function_exists('authenticate_user_locally')) {
    /**
     * Validate credentials against the database and return user info.
     *
     * @param MySQLiWrapper|mysqli $mysqli
     * @param string $username
     * @param string $password
     * @return array{id:int,username:string,role:string,email:string,full_name:string}
     * @throws AuthException when validation fails or user not found
     * @throws Exception for unexpected server/database issues
     */
    function authenticate_user_locally($mysqli, $username, $password)
    {
        if (!$mysqli) {
            throw new Exception('Database connection not available.');
        }

        $username = trim((string)$username);
        $password = trim((string)$password);

        if ($username === '' || $password === '') {
            throw new AuthException('Username and password are required.', 400);
        }

        // Ensure users table exists
        $table_check = $mysqli->query("SHOW TABLES LIKE 'users'");
        if (!$table_check || $table_check->num_rows === 0) {
            throw new Exception("Users table not found. Please ensure the 'users' table exists.");
        }

        // Gather column metadata
        $columns_res = $mysqli->query("SHOW COLUMNS FROM users");
        if (!$columns_res) {
            throw new Exception('Unable to inspect users table structure.');
        }

        $columns = [];
        while ($col = $columns_res->fetch_assoc()) {
            $columns[] = $col['Field'];
        }

        $password_col = null;
        foreach (['password_hash', 'password', 'PASSWORD', 'passwd'] as $candidate) {
            if (in_array($candidate, $columns, true)) {
                $password_col = $candidate;
                break;
            }
        }
        if (!$password_col) {
            throw new Exception('No password column found in users table.');
        }

        $id_col = in_array('id', $columns, true)
            ? 'id'
            : (in_array('ID', $columns, true)
                ? 'ID'
                : (in_array('SL_NO', $columns, true) ? 'SL_NO' : 'id'));

        $username_col = in_array('username', $columns, true)
            ? 'username'
            : (in_array('USER_NAME', $columns, true)
                ? 'USER_NAME'
                : (in_array('usn', $columns, true) ? 'usn' : 'username'));

        $role_select = in_array('role', $columns, true)
            ? 'role'
            : (in_array('user_type', $columns, true) ? 'user_type' : "'student' as role");

        $email_select = in_array('email', $columns, true)
            ? 'email'
            : (in_array('email_id', $columns, true) ? 'email_id' : "'' as email");

        $name_select = in_array('full_name', $columns, true)
            ? 'full_name'
            : (in_array('name', $columns, true) ? 'name' : "'' as full_name");

        $query = "SELECT 
                {$id_col} AS id,
                {$username_col} AS username,
                {$password_col} AS password_hash,
                {$role_select},
                {$email_select},
                {$name_select}
            FROM users
            WHERE {$username_col} = ?
            LIMIT 1";

        $stmt = $mysqli->prepare($query);
        if (!$stmt) {
            $error_msg = $mysqli->error ?? 'Unknown prepare error';
            throw new Exception('Failed to prepare login query: ' . $error_msg);
        }

        $stmt->bind_param('s', $username);
        if (!$stmt->execute()) {
            $error_msg = $stmt->error ?? 'Unknown execution error';
            $stmt->close();
            throw new Exception('Failed to execute login query: ' . $error_msg);
        }

        $result = $stmt->get_result();
        $user = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        if (!$user) {
            throw new AuthException('Invalid username or password.', 401);
        }

        $password_hash = (string)($user['password_hash'] ?? '');
        if ($password_hash === '') {
            throw new Exception('User account not configured with a password.');
        }

        $is_hashed = str_starts_with($password_hash, '$2y$')
            || str_starts_with($password_hash, '$2a$')
            || str_starts_with($password_hash, '$2b$')
            || str_starts_with($password_hash, '$argon2');

        $password_valid = $is_hashed
            ? password_verify($password, $password_hash)
            : hash_equals($password_hash, $password);

        if (!$password_valid) {
            throw new AuthException('Invalid username or password.', 401);
        }

        return [
            'id' => (int)($user['id'] ?? 0),
            'username' => $user['username'] ?? $username,
            'role' => $user['role'] ?? 'student',
            'email' => $user['email'] ?? '',
            'full_name' => $user['full_name'] ?? ''
        ];
    }
}
?>

