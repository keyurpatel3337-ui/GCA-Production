<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';

/**
 * Admission Letter Controller
 * Supports both API mode (JSON response) and direct inclusion mode
 */

$base_path = dirname(dirname(__DIR__));

require_once DB_CONNECT_FILE;

// Check if this is an API call (via index.php router)
$is_api_call = defined('API_MODE') || (isset($_GET['route']) && !empty($_GET['route']));

// Helper functions for API responses
if (!function_exists('sendJsonResponse')) {
    function sendJsonResponse($data, $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}

if (!function_exists('sendErrorResponse')) {
    function sendErrorResponse($message, $statusCode = 400, $details = null)
    {
        $response = [
            'success' => false,
            'error' => $message
        ];
        if ($details !== null) {
            $response['details'] = $details;
        }
        sendJsonResponse($response, $statusCode);
    }
}

if (!function_exists('sendSuccessResponse')) {
    function sendSuccessResponse($data = null, $message = null)
    {
        $response = ['success' => true];
        if ($message !== null) {
            $response['message'] = $message;
        }
        if ($data !== null) {
            $response['data'] = $data;
        }
        sendJsonResponse($response);
    }
}

// Include dependencies

require_once OPERATION_FILE;
require_once $base_path . '/../common/helpers/error_logger.php';
// Check permissions (even for API calls)
if ($is_api_call) {
    if (!isset($_SESSION['user_id'])) {
        sendErrorResponse('Unauthorized access', 401);
    }
} else {
    // Check if user is authorized (Counsellor, Accountant, Principle, or Super Admin)
    if (!hasRole(ROLE_COUNSELLOR) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN) && (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'accountant')) {
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
    header('Location: ../students/list.php');
    exit;
}

$student_id = $_GET['id'];
$page_title = "Admission Letter";
$page_breadcrumb = "Admission Letter";

// Get student details with admission confirmation
try {
    $stmt = $conn->prepare("SELECT s.*, u.name as counsellor_name,
                           b.board_name, m.medium_name, g.group_name, c.course_name
                           FROM tbl_gm_std_registration s
                           LEFT JOIN tbl_users u ON s.admission_confirmed_by = u.id
                           LEFT JOIN tbl_boards b ON s.board_id = b.id
                           LEFT JOIN tbl_medium m ON s.medium_id = m.id
                           LEFT JOIN tbl_group g ON s.group_id = g.id
                           LEFT JOIN tbl_courses c ON s.course_id = c.id
                           WHERE s.id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();

    if (!$student) {
        if ($is_api_call) {
            sendErrorResponse('Student not found', 404);
        }
        set_flash_message('error', "Student not found");
        header('Location: ../students/list.php');
        exit;
    }

    if (!$student['admission_confirmed']) {
        if ($is_api_call) {
            sendErrorResponse('Admission not yet confirmed for this student', 400);
        }
        set_flash_message('error', "Admission not yet confirmed for this student");
        header('Location: admission-confirm.php?id=' . $student_id);
        exit;
    }
} catch (PDOException $e) {
    if (!$is_api_call && function_exists('logDatabaseError')) {
        logDatabaseError($e, "Fetch Student for Admission Letter");
    }
    if ($is_api_call) {
        sendErrorResponse('Database error: ' . $e->getMessage(), 500);
    }
    set_flash_message('error', "Error fetching student details");
    header('Location: ../students/list.php');
    exit;
}

// Get fee configuration
$fee_config = null;
try {
    $stmt = $conn->prepare("SELECT fc.*, c.course_name 
                           FROM tbl_fee_config fc
                           LEFT JOIN tbl_courses c ON fc.course_id = c.id
                           WHERE fc.course_id = ? AND fc.medium_id = ? AND fc.group_id = ? AND fc.is_active = 1 
                           LIMIT 1");
    $stmt->execute([$student['course_id'], $student['medium_id'], $student['group_id']]);
    $fee_config = $stmt->fetch();
} catch (PDOException $e) {
    if (!$is_api_call && function_exists('logDatabaseError')) {
        logDatabaseError($e, "Fetch Fee Configuration");
    }
    $fee_config = null;
}

// Calculate fees after scholarship
$total_fees = $fee_config ? $fee_config['total_fees'] : 0;
$token_fee_config = $fee_config ? $fee_config['token_fee'] : 0;
$scholarship_amount = $student['scholarship_amount'];
$scholarship_percentage = $student['scholarship_percentage'];

if ($scholarship_percentage > 0) {
    $scholarship_amount = ($total_fees * $scholarship_percentage) / 100;
}

$final_fees = $total_fees - $scholarship_amount;

// Token Fee Breakdown
$tuition_part1 = $fee_config['tuition_fee_part1'] ?? 0;
$gst = $tuition_part1 * 0.18;
$token_fee = $tuition_part1 + $gst;

// If API call, return JSON response
if ($is_api_call) {
    sendSuccessResponse([
        'student' => $student,
        'fee_config' => $fee_config,
        'fee_breakdown' => [
            'total_fees' => $total_fees,
            'scholarship_amount' => $scholarship_amount,
            'scholarship_percentage' => $scholarship_percentage,
            'final_fees' => $final_fees,
            'token_fee' => $token_fee,
            'tuition_part1' => $tuition_part1,
            'gst' => $gst
        ]
    ]);
}
