<?php
/**
 * Security Utilities
 * 
 * This file provides common security functions used across the application.
 */

/**
 * Initialize secure session with proper cookie configuration
 */
function init_secure_session() {
    if (session_status() === PHP_SESSION_NONE) {
        // Load environment settings
        $use_secure = ($_ENV['SECURE_COOKIES'] ?? 'true') === 'true';
        
        // Set secure cookie parameters
        session_set_cookie_params([
            'lifetime' => 0,                          // Session cookie (deleted on browser close)
            'path' => '/',
            'domain' => $_SERVER['HTTP_HOST'] ?? '',
            'secure' => $use_secure && isset($_SERVER['HTTPS']), // HTTPS only in production
            'httponly' => true,                       // Prevent JavaScript access
            'samesite' => 'Strict'                    // CSRF protection
        ]);
        
        session_start();
        
        // Regenerate session ID on login to prevent session fixation
        if (!isset($_SESSION['session_started'])) {
            session_regenerate_id(true);
            $_SESSION['session_started'] = true;
        }
    }
}

/**
 * Generate CSRF token for forms
 * 
 * @return string The CSRF token
 */
function get_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token from POST request
 * 
 * @param string $token The token to verify
 * @return bool True if valid, false otherwise
 */
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token ?? '');
}

/**
 * Check if user session is still valid (not timed out)
 * 
 * @return bool True if session is valid, false if expired
 */
function is_session_valid() {
    $timeout = (int)($_ENV['SESSION_TIMEOUT_MINUTES'] ?? 30) * 60;
    
    if (!isset($_SESSION['last_activity'])) {
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    if (time() - $_SESSION['last_activity'] > $timeout) {
        session_destroy();
        return false;
    }
    
    $_SESSION['last_activity'] = time();
    return true;
}

/**
 * Check login rate limiting
 * 
 * @return array ['allowed' => bool, 'attempts' => int, 'remaining' => int]
 */
function check_login_rate_limit() {
    $ip = $_SERVER['REMOTE_ADDR'];
    $key = "login_attempts_" . md5($ip);
    $max_attempts = (int)($_ENV['MAX_LOGIN_ATTEMPTS'] ?? 5);
    $window_minutes = (int)($_ENV['LOGIN_ATTEMPT_WINDOW_MINUTES'] ?? 15);
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'time' => time()];
    }
    
    // Reset counter if window has passed
    if (time() - $_SESSION[$key]['time'] > ($window_minutes * 60)) {
        $_SESSION[$key] = ['count' => 0, 'time' => time()];
    }
    
    return [
        'allowed' => $_SESSION[$key]['count'] < $max_attempts,
        'attempts' => $_SESSION[$key]['count'],
        'remaining' => max(0, $max_attempts - $_SESSION[$key]['count'])
    ];
}

/**
 * Increment login attempt counter
 */
function increment_login_attempts() {
    $ip = $_SERVER['REMOTE_ADDR'];
    $key = "login_attempts_" . md5($ip);
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'time' => time()];
    }
    
    $_SESSION[$key]['count']++;
    
    // Log failed login attempts
    error_log("Failed login attempt from IP: $ip (Attempt: {$_SESSION[$key]['count']})");
}

/**
 * Clear login attempt counter (call after successful login)
 */
function clear_login_attempts() {
    $ip = $_SERVER['REMOTE_ADDR'];
    $key = "login_attempts_" . md5($ip);
    unset($_SESSION[$key]);
}

/**
 * Safe output escaping for HTML
 * 
 * @param mixed $value Value to escape
 * @param string $flags HTML entity encode flags
 * @return string Safely escaped string
 */
function safe_output($value, $flags = ENT_QUOTES) {
    return htmlspecialchars((string)$value, $flags, 'UTF-8');
}

/**
 * Safe output for JavaScript context
 * 
 * @param mixed $value Value to escape
 * @return string JSON-encoded and escaped string
 */
function safe_json($value) {
    return json_encode($value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
}

/**
 * Verify user is authenticated
 * 
 * @return bool True if user is logged in and session valid
 */
function is_user_authenticated() {
    return isset($_SESSION['admin_logged_in']) 
        && $_SESSION['admin_logged_in'] === true 
        && isset($_SESSION['admin_id'])
        && is_session_valid();
}

/**
 * Require authentication or redirect to login
 */
function require_auth() {
    if (!is_user_authenticated()) {
        header("Location: login.php");
        exit;
    }
}

/**
 * Get current authenticated user
 * 
 * @return array|null User data or null if not authenticated
 */
function get_current_user() {
    if (!is_user_authenticated()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['admin_id'] ?? null,
        'username' => $_SESSION['admin_username'] ?? null,
        'login_time' => $_SESSION['login_time'] ?? null
    ];
}

/**
 * Log security events
 * 
 * @param string $event Event type
 * @param array $data Additional data
 */
function log_security_event($event, $data = []) {
    $user_id = $_SESSION['admin_id'] ?? 'unknown';
    $ip = $_SERVER['REMOTE_ADDR'];
    $timestamp = date('Y-m-d H:i:s');
    
    $log_message = "[$timestamp] Event: $event | User ID: $user_id | IP: $ip";
    
    if (!empty($data)) {
        $log_message .= " | Data: " . json_encode($data);
    }
    
    error_log($log_message);
}
?>
