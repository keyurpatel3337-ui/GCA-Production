<?php
require_once dirname(__DIR__) . '/common/constants.php';
require_once __DIR__ . '/session_config.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;
require_once dirname(__DIR__) . '/common/helpers/audit_log_helper.php';

// Check if it's a student-specific login
$isStudentLogin = isset($_SESSION['is_student_login']) && $_SESSION['is_student_login'] === true;

// Store user info before destroying session
$user_id = $_SESSION['user_id'] ?? null;
$username = $_SESSION['user_name'] ?? $_SESSION['username'] ?? 'Unknown';

// LOG: User logout
if ($user_id) {
    logLogout($conn, $user_id, $username);
}

// Destroy all session data
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destroy the session
session_destroy();

// Redirect based on login type
if ($isStudentLogin) {
    header('Location: modules/student-portal/student-login.php');
} else {
    // Redirect to main login page
    $redirectUrl = defined('PORTAL_URL') ? PORTAL_URL . '/login.php' : 'login.php';
    header('Location: ' . $redirectUrl);
}
exit;
