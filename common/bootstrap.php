<?php
/**
 * Application Bootstrap File
 * 
 * This is the SINGLE entry point that loads all required configuration.
 * Include this file at the start of any PHP script.
 * 
 * Usage: require_once __DIR__ . '/common/bootstrap.php';
 * Or:    require_once ROOT_PATH . 'common/bootstrap.php';
 */

// Prevent multiple inclusions
if (defined('APP_BOOTSTRAPPED')) {
    return;
}
define('APP_BOOTSTRAPPED', true);

// Start output buffering 
ob_start();

// ============================================================================
// STEP 1: Load Constants & Configuration
// ============================================================================
require_once __DIR__ . '/constants.php';
require_once ROOT_PATH . 'env.config.php';
require_once COMMON_PATH . 'config/app-config.php';

// Set timezone
date_default_timezone_set('Asia/Kolkata');

// ============================================================================
// STEP 2: Security & Input Handling
// ============================================================================

// Sanitize requests
if (file_exists(ROOT_PATH . 'portal/common/request-sanitizer.php')) {
    require_once ROOT_PATH . 'portal/common/request-sanitizer.php';
}

// Handle JSON input for APIs
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
        $_POST = array_merge($_POST, $data);
        $_REQUEST = array_merge($_REQUEST, $data);
    }
}

// ============================================================================
// STEP 3: Session Management
// ============================================================================
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', (defined('ENVIRONMENT') && ENVIRONMENT === 'production') ? 1 : 0);
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_path', '/');
    ini_set('session.gc_maxlifetime', 86400);
    ini_set('session.cookie_lifetime', 86400);
    session_name('GCA_SESSION');
    session_start();
}

// ============================================================================
// STEP 4: Core Files & Helpers
// ============================================================================
require_once COMMON_PATH . 'db_connect.php';

// Load key helpers
if (file_exists(COMMON_PATH . 'helpers/flash_message.php')) {
    require_once COMMON_PATH . 'helpers/flash_message.php';
    if (function_exists('migrate_old_session_messages')) {
        migrate_old_session_messages();
    }
}

// Load Audit Middleware
require_once HELPERS_PATH . 'audit_middleware.php';

// ============================================================================
// STEP 5: Core Helper Functions
// ============================================================================

/**
 * Send JSON response
 */
if (!function_exists('sendJsonResponse')) {
    function sendJsonResponse($data, $statusCode = 200)
    {
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=utf-8');
        }
        $output = ob_get_clean(); // Get any previous output/warnings
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
}

/**
 * Send Success Response
 */
if (!function_exists('sendSuccessResponse')) {
    function sendSuccessResponse($data = null, $message = null)
    {
        $response = ['success' => true];
        if ($message !== null)
            $response['message'] = $message;
        if ($data !== null)
            $response['data'] = $data;
        sendJsonResponse($response);
    }
}

/**
 * Send Error Response
 */
if (!function_exists('sendErrorResponse')) {
    function sendErrorResponse($message, $statusCode = 400, $details = null)
    {
        $response = ['success' => false, 'error' => $message];
        if ($details !== null)
            $response['details'] = $details;
        sendJsonResponse($response, $statusCode);
    }
}

/**
 * Dynamic Loading Helpers
 */
function loadOperation()
{
    require_once OPERATION_FILE;
}

function loadHelper($helperName)
{
    $file = HELPERS_PATH . $helperName . '.php';
    if (file_exists($file)) {
        require_once $file;
        return true;
    }
    return false;
}

function loadService($serviceName)
{
    $file = SERVICES_PATH . $serviceName . '.php';
    if (file_exists($file)) {
        require_once $file;
        return true;
    }
    return false;
}
?>