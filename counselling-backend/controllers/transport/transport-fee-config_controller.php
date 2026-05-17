<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';

/**
 * Transport Fee Configuration Controller
 * Supports both API mode (JSON response) and direct inclusion mode
 */

$base_path = dirname(dirname(__DIR__));

require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;

// Initialize database operations
$dbOps = new DatabaseOperations();

// Check if this is an API call (via index.php router)
$is_api_call = defined('API_MODE') || (isset($_GET['route']) && !empty($_GET['route']));

if (!$is_api_call) {
    require_once OPERATION_FILE;
    require_once $base_path . '/../common/helpers/error_logger.php';
    // Check if user is Super Admin
    if (!hasRole(ROLE_SUPER_ADMIN)) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

// Fetch all transport fee settings
try {
    $sql = "SELECT tfs.*, u.name as created_by_name, ay.year_name as academic_year, c.course_name 
            FROM tbl_transport_fee_settings tfs 
            LEFT JOIN tbl_users u ON tfs.created_by = u.id 
            LEFT JOIN tbl_academic_years ay ON tfs.academic_year_id = ay.id 
            LEFT JOIN tbl_courses c ON tfs.course_id = c.id
            ORDER BY ay.year_name DESC, c.course_name ASC";
    $transport_settings = $dbOps->customSelect($sql);
} catch (PDOException $e) {
    if (!$is_api_call && function_exists('logDatabaseError')) {
        logDatabaseError($e, "Fetch Transport Fee Settings");
    }
    $transport_settings = [];

    if ($is_api_call) {
        sendErrorResponse('Database error: ' . $e->getMessage(), 500);
    }
}

try {
    $academic_years = $dbOps->select('tbl_academic_years', ['id', 'year_name'], ['is_active' => 1], 'year_name DESC');
} catch (PDOException $e) {
    if (!$is_api_call && function_exists('logDatabaseError')) {
        logDatabaseError($e, "Fetch Academic Years");
    }
}

// Fetch courses for dropdown
$courses = [];
try {
    $courses = $dbOps->select('tbl_courses', ['id', 'course_name'], ['is_active' => 1], 'course_name ASC');
} catch (PDOException $e) {
    if (!$is_api_call && function_exists('logDatabaseError')) {
        logDatabaseError($e, "Fetch Courses");
    }
}

// If API call, return JSON response
if ($is_api_call) {
    sendSuccessResponse([
        'transport_settings' => $transport_settings,
        'academic_years' => $academic_years,
        'courses' => $courses
    ]);
}

$page_title = "Transport Fee Configuration";
$page_breadcrumb = "Fee Config";
$current_page = 'transport-fee-config';


