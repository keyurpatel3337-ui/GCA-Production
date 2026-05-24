<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';

/**
 * Token Fee Collect Controller
 * Supports both API mode (JSON response) and direct inclusion mode
 */

$base_path = dirname(dirname(__DIR__));

require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;

$dbOps = new DatabaseOperations();

// Check if this is an API call (via index.php router)
$is_api_call = defined('API_MODE') || (isset($_GET['route']) && !empty($_GET['route']));

if (!$is_api_call) {
    require_once OPERATION_FILE;
    require_once $base_path . '/../common/helpers/error_logger.php';
    // Check if user has appropriate role
    $allowed_roles = [ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_ACCOUNTANT];
    if (!isset($_SESSION['role_id']) || !in_array($_SESSION['role_id'], $allowed_roles)) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

// Check if student ID is provided
if (!isset($_GET['id'])) {
    if ($is_api_call) {
        sendErrorResponse('Student ID is required', 400);
    }
    set_flash_message('error', "Student ID is required");
    header('Location: ' . (defined('PORTAL_URL') ? PORTAL_URL : BASE_URL . '/portal') . '/modules/payments/token-fee-collection.php');
    exit;
}

$student_id = $_GET['id'];
$page_title = "Collect Token Fee";
$page_breadcrumb = "Fee -";

// Get student details
try {
    $student = $dbOps->customSelect(
        "SELECT s.*, 
        b.board_name as board,
        c.course_name,
        m.medium_name,
        g.group_name,
        fc.token_fee
        FROM tbl_gm_std_registration s
        LEFT JOIN tbl_boards b ON s.board_id = b.id
        LEFT JOIN tbl_courses c ON s.course_id = c.id
        LEFT JOIN tbl_medium m ON s.medium_id = m.id
        LEFT JOIN tbl_group g ON s.group_id = g.id
        LEFT JOIN tbl_fee_config fc ON s.course_id = fc.course_id 
            AND s.school_id = fc.school_id 
            AND s.medium_id = fc.medium_id 
            AND s.group_id = fc.group_id 
            AND fc.is_active = 1
        WHERE s.id = ? AND s.admission_confirmed = 1",
        [$student_id],
        true
    );

    if (!$student) {
        if ($is_api_call) {
            sendErrorResponse('Student not found or admission not confirmed', 404);
        }
        set_flash_message('error', "Student not found or admission not confirmed");
        header('Location: ' . (defined('PORTAL_URL') ? PORTAL_URL : BASE_URL . '/portal') . '/modules/payments/token-fee-collection.php');
        exit;
    }

    if ($student['token_fees_paid']) {
        if ($is_api_call) {
            sendErrorResponse('Token fee already paid for this student', 400);
        }
        $_SESSION['info_msg'] = "Token fee already paid for this student";
        header('Location: ' . (defined('PORTAL_URL') ? PORTAL_URL : BASE_URL . '/portal') . '/modules/payments/token-fee-collection.php');
        exit;
    }
} catch (PDOException $e) {
    if (!$is_api_call && function_exists('logDatabaseError')) {
        logDatabaseError($e, "Fetch Student for Token Fee Collection");
    }
    if ($is_api_call) {
        sendErrorResponse('Database error: ' . $e->getMessage(), 500);
    }
    set_flash_message('error', "Error fetching student details");
    header('Location: ' . (defined('PORTAL_URL') ? PORTAL_URL : BASE_URL . '/portal') . '/modules/payments/token-fee-collection.php');
    exit;
}

// If API call, return JSON response
if ($is_api_call) {
    sendSuccessResponse([
        'student' => $student
    ]);
}
