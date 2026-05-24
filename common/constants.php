<?php
/**
 * Constants Configuration File
 * 
 * This file contains all path constants used throughout the application.
 * Include this file at the beginning of your scripts to use these constants.
 * 
 * Usage: require_once __DIR__ . '/common/constants.php';
 */

// Prevent direct access
if (!defined('APP_INIT')) {
    define('APP_INIT', true);
}

// ============================================================================
// ROOT PATHS
// ============================================================================

// Application root directory (parent of common folder)
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
}

// Common directory path
if (!defined('COMMON_PATH')) {
    define('COMMON_PATH', __DIR__ . DIRECTORY_SEPARATOR);
}

// ============================================================================
// COMMON SUBDIRECTORY PATHS
// ============================================================================

// Library path (contains Operation.php, easebuzz, etc.)
define('LIB_PATH', COMMON_PATH . 'lib' . DIRECTORY_SEPARATOR);

// Helpers path (contains helper functions)
define('HELPERS_PATH', COMMON_PATH . 'helpers' . DIRECTORY_SEPARATOR);

// Services path (contains NotificationService, WhatsAppHelper, etc.)
define('SERVICES_PATH', COMMON_PATH . 'services' . DIRECTORY_SEPARATOR);

// Logs path (external storage for better log management)
define('LOGS_PATH', 'D:\\GCA\\Logs\\');

// ============================================================================
// APPLICATION MODULE PATHS
// ============================================================================

// Portal directory
define('PORTAL_PATH', ROOT_PATH . 'portal' . DIRECTORY_SEPARATOR);
define('PORTAL_INCLUDE_PATH', PORTAL_PATH . 'include' . DIRECTORY_SEPARATOR);

// Counselling backend directory
define('BACKEND_PATH', ROOT_PATH . 'counselling-backend' . DIRECTORY_SEPARATOR);

// Layouts directory
define('LAYOUTS_PATH', ROOT_PATH . 'layouts' . DIRECTORY_SEPARATOR);

// Views directory
define('VIEWS_PATH', ROOT_PATH . 'views' . DIRECTORY_SEPARATOR);

// Assets directory
define('ASSETS_PATH', ROOT_PATH . 'assets' . DIRECTORY_SEPARATOR);

// Uploads directory
define('UPLOADS_PATH', ROOT_PATH . 'uploads' . DIRECTORY_SEPARATOR);

// Include directory
define('INCLUDE_PATH', ROOT_PATH . 'include' . DIRECTORY_SEPARATOR);

// Email templates directory
define('EMAIL_TEMPLATES_PATH', ROOT_PATH . 'email_templates' . DIRECTORY_SEPARATOR);

// ============================================================================
// COMMON FILE PATHS
// ============================================================================

// Environment configuration file
define('ENV_CONFIG_FILE', ROOT_PATH . 'env.config.php');

// Database connection file
define('DB_CONNECT_FILE', COMMON_PATH . 'db_connect.php');

// Operation class file
define('OPERATION_FILE', LIB_PATH . 'Operation.php');

// ============================================================================
// HELPER FILE PATHS
// ============================================================================

define('HELPER_DB_CONNECTION_MANAGER', HELPERS_PATH . 'db_connection_manager.php');
define('HELPER_EMAIL_FUNCTIONS', HELPERS_PATH . 'email_functions.php');
define('HELPER_ERROR_LOGGER', HELPERS_PATH . 'error_logger.php');
define('HELPER_NOTIFICATION_FUNCTIONS', HELPERS_PATH . 'notification_functions.php');
define('HELPER_WHATSAPP_FUNCTIONS', HELPERS_PATH . 'whatsapp_functions.php');
define('HELPER_DIVISION_HELPER', HELPERS_PATH . 'division_helper.php');
define('HELPER_DIVISION_ASSIGNMENT', HELPERS_PATH . 'division_assignment_functions.php');
define('HELPER_ENROLLMENT_FUNCTIONS', HELPERS_PATH . 'enrollment_functions.php');
define('HELPER_RECEIPT_MAPPING', HELPERS_PATH . 'receipt_mapping_functions.php');
define('HELPER_FLASH_MESSAGE', HELPERS_PATH . 'flash_message.php');

// Portal-specific helpers
define('PAGINATION_FILE', PORTAL_PATH . 'common' . DIRECTORY_SEPARATOR . 'pagination.php');
define('PORTAL_GLOBALVARIABLE', PORTAL_PATH . 'common' . DIRECTORY_SEPARATOR . 'globalvariable.php');
define('BACKEND_GLOBALVARIABLE', BACKEND_PATH . 'common' . DIRECTORY_SEPARATOR . 'globalvariable.php');

// ============================================================================
// SERVICE FILE PATHS
// ============================================================================

define('SERVICE_NOTIFICATION', SERVICES_PATH . 'NotificationService.php');
define('SERVICE_WHATSAPP', SERVICES_PATH . 'WhatsAppHelper.php');

// ============================================================================
// URL PATHS (with environment detection)
// ============================================================================

// Get current host information for URL generation
$current_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
if (empty($current_host)) {
    $current_host = $_SERVER['SERVER_NAME'] ?? 'localhost';
}
$script_path = $_SERVER['SCRIPT_FILENAME'] ?? '';

// Detect protocol (HTTP or HTTPS)
$protocol = 'http';
if (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
    (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
    (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') ||
    (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
) {
    $protocol = 'https';
}

// Determine if local environment
$is_local_env = (
    strpos($current_host, 'localhost') !== false ||
    strpos($current_host, '127.0.0.1') !== false ||
    strpos($script_path, 'wamp64') !== false ||
    strpos($script_path, 'xampp') !== false ||
    strpos($script_path, 'C:') !== false ||
    strpos($script_path, 'D:') !== false
);

// Base URL
if (!defined('BASE_URL')) {
    if ($is_local_env && stripos($current_host, 'gyanmanjari.com') === false) {
        define('BASE_URL', $protocol . '://' . $current_host . '/GCA-Production');
    } else {
        define('BASE_URL', $protocol . '://' . $current_host);
    }
}

// Frontend URL (same as BASE_URL)
if (!defined('FRONTEND_URL')) {
    define('FRONTEND_URL', BASE_URL);
}

// Portal URL
if (!defined('PORTAL_URL')) {
    define('PORTAL_URL', BASE_URL . '/portal');
}

// Backend URL
if (!defined('BACKEND_URL')) {
    define('BACKEND_URL', BASE_URL . '/counselling-backend');
}

// API URL
if (!defined('API_URL')) {
    define('API_URL', BACKEND_URL . '/api');
}

// Assets URL
if (!defined('ASSETS_URL')) {
    define('ASSETS_URL', BASE_URL . '/assets');
}

// Uploads URL
if (!defined('UPLOADS_URL')) {
    define('UPLOADS_URL', BASE_URL . '/uploads');
}

// Environment constant
if (!defined('ENVIRONMENT')) {
    define('ENVIRONMENT', $is_local_env ? 'development' : 'production');
}

// System Name
if (!defined('SYSTEM_NAME')) {
    define('SYSTEM_NAME', 'GCA');
}

// Error Reporting based on environment
if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '0'); // Log errors; never display to browser
    ini_set('log_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}
