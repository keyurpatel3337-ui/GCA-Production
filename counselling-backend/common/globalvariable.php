<?php

require_once dirname(__DIR__) . '/../common/constants.php';
/**
 * Global Variables Configuration
 * Contains all system-wide variables and constants
 */

// Load environment configuration
require_once ENV_CONFIG_FILE;

// Session check is now handled by individual controllers
// This file only provides global variables and constants
// No automatic redirect - each controller manages its own authentication

// Database configuration for external student database (use same as main DB)
if (!defined('EXT_DB_HOST')) {
    define('EXT_DB_HOST', $host);
}
if (!defined('EXT_DB_NAME')) {
    define('EXT_DB_NAME', $dbname);
}
if (!defined('EXT_DB_USER')) {
    define('EXT_DB_USER', $username);
}
if (!defined('EXT_DB_PASS')) {
    define('EXT_DB_PASS', $password);
}

// Fetch System Configuration from Database
$system_settings = [];
try {
    // Only include db_connect if not already loaded
    if (!isset($conn)) {
        require_once DB_CONNECT_FILE;
    }

    $settings_stmt = $conn->prepare("SELECT setting_key, setting_value FROM tbl_system_settings WHERE category = 'general'");
    $settings_stmt->execute();
    while ($row = $settings_stmt->fetch(PDO::FETCH_ASSOC)) {
        $system_settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    // Use empty array, defaults will be applied below
}

// System Configuration from database with fallback defaults
if (!defined('SYSTEM_NAME')) {
    define('SYSTEM_NAME', $system_settings['SYSTEM_NAME'] ?? 'Gyanmanjari Career Academy');
}
if (!defined('SYSTEM_SHORT_NAME')) {
    define('SYSTEM_SHORT_NAME', $system_settings['system_short_name'] ?? 'GCA');
}
if (!defined('SYSTEM_VERSION')) {
    define('SYSTEM_VERSION', '1.0.0');
}
if (!defined('SYSTEM_ADDRESS')) {
    define('SYSTEM_ADDRESS', $system_settings['address'] ?? 'Address Line 1, City, State - PIN');
}
if (!defined('SYSTEM_PHONE')) {
    define('SYSTEM_PHONE', $system_settings['contact_phone'] ?? '+91-XXXXXXXXXX');
}
if (!defined('UPLOAD_PATH')) {
    define('UPLOAD_PATH', '../uploads/');
}
if (!defined('ANSWER_KEY_PATH')) {
    define('ANSWER_KEY_PATH', '../uploads/answer_keys/');
}
if (!defined('PROFILE_IMAGE_PATH')) {
    define('PROFILE_IMAGE_PATH', '../uploads/profiles/');
}
if (!defined('MAX_FILE_SIZE')) {
    define('MAX_FILE_SIZE', 5242880); // 5MB in bytes
}

// EaseBuzz Payment Gateway Configuration (from env.config.php)
// EASEBUZZ_MERCHANT_KEY, EASEBUZZ_SALT, EASEBUZZ_ENV are already defined in env.config.php

// Pagination
if (!defined('RECORDS_PER_PAGE')) {
    define('RECORDS_PER_PAGE', 10);
}

// User Roles
if (!defined('ROLE_SUPER_ADMIN')) {
    define('ROLE_SUPER_ADMIN', 1);
}
if (!defined('ROLE_PRINCIPLE')) {
    define('ROLE_PRINCIPLE', 2);
}
if (!defined('ROLE_COUNSELLOR')) {
    define('ROLE_COUNSELLOR', 3);
}
if (!defined('ROLE_STUDENT')) {
    define('ROLE_STUDENT', 4);
}
if (!defined('ROLE_ACCOUNTANT')) {
    define('ROLE_ACCOUNTANT', 5);
}
if (!defined('ROLE_WEBSITE_ADMIN')) {
    define('ROLE_WEBSITE_ADMIN', 6);
}
if (!defined('ROLE_MAINTENANCE')) {
    define('ROLE_MAINTENANCE', 7);
}
if (!defined('ROLE_ESTABLISHMENT')) {
    define('ROLE_ESTABLISHMENT', 8);
}
if (!defined('ROLE_RECEPTION')) {
    define('ROLE_RECEPTION', 9);
}
if (!defined('ROLE_COMPUTER_OPERATOR')) {
    define('ROLE_COMPUTER_OPERATOR', 28);
}
if (!defined('ROLE_DEPT_HEAD')) {
    define('ROLE_DEPT_HEAD', 12);
}
if (!defined('ROLE_ASSISTANT_TEACHER')) {
    define('ROLE_ASSISTANT_TEACHER', 27);
}
if (!defined('ROLE_TEACHER')) {
    define('ROLE_TEACHER', 29);
}

// User Status
if (!defined('STATUS_ACTIVE')) {
    define('STATUS_ACTIVE', 'active');
}
if (!defined('STATUS_INACTIVE')) {
    define('STATUS_INACTIVE', 'inactive');
}
if (!defined('STATUS_SUSPENDED')) {
    define('STATUS_SUSPENDED', 'suspended');
}

// Date & Time Format
if (!defined('DATE_FORMAT')) {
    define('DATE_FORMAT', 'd-m-Y');
}
if (!defined('DATETIME_FORMAT')) {
    define('DATETIME_FORMAT', 'd-m-Y H:i:s');
}
if (!defined('TIME_FORMAT')) {
    define('TIME_FORMAT', 'H:i:s');
}
if (!defined('STATUS_ACTIVE')) {
    define('STATUS_ACTIVE', 'active');
}
if (!defined('STATUS_INACTIVE')) {
    define('STATUS_INACTIVE', 'inactive');
}
if (!defined('STATUS_SUSPENDED')) {
    define('STATUS_SUSPENDED', 'suspended');
}

// Note: DATE_FORMAT, DATETIME_FORMAT, and TIME_FORMAT are already defined above with checks

// Session Variables
// Support both regular users and student-specific login
// Declare as global for access in functions
global $user_id, $user_name, $user_email, $user_role, $role_name, $role_id;

if (isset($_SESSION['is_student_login']) && $_SESSION['is_student_login'] === true) {
    $user_id = $_SESSION['student_id'] ?? null;
    $user_name = $_SESSION['student_name'] ?? 'Student';
    $user_email = '';
    $user_role = 'student';
    $role_name = 'Student';
    $role_id = ROLE_STUDENT;
} elseif (isset($_SESSION['is_parent_login']) && $_SESSION['is_parent_login'] === true) {
    $user_id = $_SESSION['active_student_id'] ?? null;
    $student_id = $_SESSION['active_student_id'] ?? null;
    $user_name = $_SESSION['parent_mobile'] ?? 'Parent';
    $user_email = '';
    $user_role = 'parent';
    $role_name = 'Parent';
    $role_id = 0;
} else {
    $user_id = $_SESSION['user_id'] ?? null;
    $user_name = $_SESSION['user_name'] ?? 'Guest';
    $user_email = $_SESSION['user_email'] ?? '';
    $user_role = $_SESSION['user_role'] ?? '';
    $role_name = $_SESSION['role_name'] ?? '';
    $role_id = $_SESSION['role_id'] ?? 0;
}

// Current Page - get from URL
$current_page = basename($_SERVER['PHP_SELF'], '.php');

// Base URL Configuration
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base_url = $protocol . '://' . $host . dirname($_SERVER['PHP_SELF'] ?? '/') . '/';

// External Database Connection for Student Data
try {
    $ext_conn = new PDO(
        "mysql:host=" . EXT_DB_HOST . ";dbname=" . EXT_DB_NAME . ";charset=utf8mb4",
        EXT_DB_USER,
        EXT_DB_PASS
    );
    $ext_conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $ext_conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Note: error_logger.php may not be loaded yet when globalvariable.php is included
    // So we use native error_log here and also try to use logDatabaseError if available
    if (function_exists('logDatabaseError')) {
        logDatabaseError($e, "External Database Connection");
    } else {
        error_log("External DB Connection Error: " . $e->getMessage());
    }
    $ext_conn = null;
}

// Create upload directories if they don't exist
$upload_dirs = [
    '../uploads/',
    '../uploads/answer_keys/',
    '../uploads/profiles/'
];

foreach ($upload_dirs as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }
}

// Helper function to check user role
if (!function_exists('hasRole')) {
    function hasRole($required_role_id)
    {
        global $role_id;
        return isset($role_id) && $role_id == $required_role_id;
    }
}

// Helper function to check if user has any of the specified roles
if (!function_exists('hasAnyRole')) {
    function hasAnyRole($role_ids)
    {
        global $role_id;
        return in_array($role_id, $role_ids);
    }
}

// Helper function to format date
if (!function_exists('formatDate')) {
    function formatDate($date, $format = DATE_FORMAT)
    {
        if (empty($date))
            return '';
        $timestamp = strtotime($date);
        return date($format, $timestamp);
    }
}

// Helper function to get user full name by ID
if (!function_exists('getUserName')) {
    function getUserName($user_id, $conn)
    {
        try {
            $stmt = $conn->prepare("SELECT name FROM tbl_users WHERE id = ?");
            $stmt->execute([$user_id]);
            $result = $stmt->fetch();
            return $result ? $result['name'] : 'Unknown';
        } catch (PDOException $e) {
            return 'Unknown';
        }
    }
}

// Helper function to get role name by ID
if (!function_exists('getRoleName')) {
    function getRoleName($role_id, $conn)
    {
        try {
            $stmt = $conn->prepare("SELECT role_name FROM tbl_roles WHERE id = ?");
            $stmt->execute([$role_id]);
            $result = $stmt->fetch();
            return $result ? $result['role_name'] : 'Unknown';
        } catch (PDOException $e) {
            return 'Unknown';
        }
    }
}

/**
 * Redirect to index.php with proper BASE_URL handling
 * Use this instead of hardcoded /counselling/index.php
 */
if (!function_exists('redirectToIndex')) {
    function redirectToIndex()
    {
        // In production: https://domain.com/index.php
        // In local: http://localhost/counselling/index.php
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}
