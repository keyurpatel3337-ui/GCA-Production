<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';

/**
 * Admission Confirmation Controller
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

require_once dirname($base_path) . '/common/lib/Operation.php'; // Using portal Operation.php
require_once $base_path . '/../common/helpers/error_logger.php';
// Check permissions (even for API calls)
if ($is_api_call) {
    if (!isset($_SESSION['user_id'])) {
        sendErrorResponse('Unauthorized access', 401);
    }
} else {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }

    // Check if user is Counsellor, Principal, or Super Admin
    if (!hasAnyRole([ROLE_COUNSELLOR, ROLE_PRINCIPLE, ROLE_SUPER_ADMIN])) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

// Check if student ID is provided (support both GET and POST)
$student_id = $_GET['id'] ?? $_POST['id'] ?? null;

if (!$student_id) {
    if ($is_api_call) {
        sendErrorResponse('Student ID is required', 400);
    }
    header('Location: admission-confirm-list.php');
    exit;
}
$counsellor_id = $_SESSION['user_id'] ?? null;
$page_title = "Confirm Admission";
$page_breadcrumb = "Admission -";

// Get student details
try {
    if (!$is_api_call && function_exists('hasRole') && (hasRole(ROLE_PRINCIPLE) || hasRole(ROLE_SUPER_ADMIN))) {
        $stmt = $conn->prepare("SELECT s.*, b.board_name, m.medium_name, g.group_name, c.course_name 
                               FROM tbl_gm_std_registration s
                               LEFT JOIN tbl_boards b ON s.board_id = b.id
                               LEFT JOIN tbl_medium m ON s.medium_id = m.id
                               LEFT JOIN tbl_group g ON s.group_id = g.id
                               LEFT JOIN tbl_courses c ON s.course_id = c.id
                               WHERE s.id = ?");
        $stmt->execute([$student_id]);
    } else if ($is_api_call) {
        // For API, fetch without role restriction
        $stmt = $conn->prepare("SELECT s.*, b.board_name, m.medium_name, g.group_name, c.course_name 
                               FROM tbl_gm_std_registration s
                               LEFT JOIN tbl_boards b ON s.board_id = b.id
                               LEFT JOIN tbl_medium m ON s.medium_id = m.id
                               LEFT JOIN tbl_group g ON s.group_id = g.id
                               LEFT JOIN tbl_courses c ON s.course_id = c.id
                               WHERE s.id = ?");
        $stmt->execute([$student_id]);
    } else {
        $stmt = $conn->prepare("SELECT s.*, b.board_name, m.medium_name, g.group_name, c.course_name 
                               FROM tbl_gm_std_registration s
                               LEFT JOIN tbl_boards b ON s.board_id = b.id
                               LEFT JOIN tbl_medium m ON s.medium_id = m.id
                               LEFT JOIN tbl_group g ON s.group_id = g.id
                               LEFT JOIN tbl_courses c ON s.course_id = c.id
                               WHERE s.id = ? AND s.counsellor_id = ?");
        $stmt->execute([$student_id, $counsellor_id]);
    }
    $student = $stmt->fetch();

    if (!$student) {
        if ($is_api_call) {
            sendErrorResponse('Student not found or not assigned to you', 404);
        }
        set_flash_message('error', "Student not found or not assigned to you");
        header('Location: list.php');
        exit;
    }

    if ($student['admission_confirmed']) {
        if (!$is_api_call) {
            $_SESSION['info_msg'] = "Admission already confirmed for this student";
        }
    }
} catch (PDOException $e) {
    if (!$is_api_call && function_exists('logDatabaseError')) {
        logDatabaseError($e, "Fetch Student for Admission Confirmation");
    }
    if ($is_api_call) {
        sendErrorResponse('Database error: ' . $e->getMessage(), 500);
    }
    set_flash_message('error', "Error fetching student details");
    header('Location: list.php');
    exit;
}

// Get current active academic year
$current_academic_year = null;
$current_academic_year_id = null;
try {
    $stmt_ay = $conn->prepare("SELECT id, year_name FROM tbl_academic_years WHERE is_active = 1 ORDER BY year_name DESC LIMIT 1");
    $stmt_ay->execute();
    $current_academic_year = $stmt_ay->fetch();
    $current_academic_year_id = $current_academic_year['id'] ?? null;
} catch (PDOException $e) {
    if (!$is_api_call && function_exists('logDatabaseError')) {
        logDatabaseError($e, "Fetch Current Academic Year");
    }
    $current_academic_year_id = null;
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

    // If no fee config found, log it for debugging
    if (!$fee_config && !$is_api_call && function_exists('logAppError')) {
        logAppError('Fee Config Missing', "No fee config found for course_id={$student['course_id']}, medium_id={$student['medium_id']}, group_id={$student['group_id']}");
    }
} catch (PDOException $e) {
    if (!$is_api_call && function_exists('logDatabaseError')) {
        logDatabaseError($e, "Fetch Fee Configuration");
    }
    $fee_config = null;
}

// Get scholarship types
$scholarship_types = [];
try {
    $stmt_scholarship = $conn->prepare("SELECT * FROM tbl_scholarship_types WHERE is_active = 1 ORDER BY id");
    $stmt_scholarship->execute();
    $scholarship_types = $stmt_scholarship->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    if (!$is_api_call && function_exists('logDatabaseError')) {
        logDatabaseError($e, "Fetch Scholarship Types");
    }
    $scholarship_types = [];
}

// Fetch hostel and transport fee configurations for dynamic display
$hostel_fee_amount = 0;
$transport_fee_amount = 0;
$hostel_fee_config = null;
$transport_fee_config = null;
$hostel_required = isset($student['hostel_required']) && strtolower($student['hostel_required']) === 'yes';
$transport_required = isset($student['transport_required']) && strtolower($student['transport_required']) === 'yes';

// Fetch hostel fee configuration
if ($current_academic_year_id && isset($student['gender'])) {
    try {
        $stmt_hostel = $conn->prepare("SELECT boys_hostel_fee, girls_hostel_fee, gst_applicable, gst_rate 
                                        FROM tbl_hostel_fee_settings 
                                        WHERE academic_year_id = ? AND is_active = 1 
                                        LIMIT 1");
        $stmt_hostel->execute([$current_academic_year_id]);
        $hostel_fee_config = $stmt_hostel->fetch();

        if ($hostel_fee_config && $hostel_required) {
            if (strtolower($student['gender']) === 'male') {
                $hostel_fee_amount = $hostel_fee_config['boys_hostel_fee'];
            } elseif (strtolower($student['gender']) === 'female') {
                $hostel_fee_amount = $hostel_fee_config['girls_hostel_fee'];
            } else {
                $hostel_fee_amount = $hostel_fee_config['boys_hostel_fee'];
            }

            if ($hostel_fee_config['gst_applicable']) {
                $hostel_gst = ($hostel_fee_amount * $hostel_fee_config['gst_rate']) / 100;
                $hostel_fee_amount += $hostel_gst;
            }
        }
    } catch (PDOException $e) {
        if (!$is_api_call && function_exists('logDatabaseError')) {
            logDatabaseError($e, "Fetch Hostel Fee Settings");
        }
    }
}

// Fetch transport fee configuration
if ($current_academic_year_id) {
    try {
        $stmt_transport = $conn->prepare("SELECT transport_fee as amount, gst_rate 
                                          FROM tbl_transport_fee_settings 
                                          WHERE academic_year_id = ? AND is_active = 1 
                                          LIMIT 1");
        $stmt_transport->execute([$current_academic_year_id]);
        $transport_fee_config = $stmt_transport->fetch();

        if ($transport_fee_config && $transport_required) {
            $transport_fee_amount = $transport_fee_config['amount'];

            if ($transport_fee_config['gst_rate'] > 0) {
                $transport_gst = ($transport_fee_amount * $transport_fee_config['gst_rate']) / 100;
                $transport_fee_amount += $transport_gst;
            }
        }
    } catch (PDOException $e) {
        if (!$is_api_call && function_exists('logDatabaseError')) {
            logDatabaseError($e, "Fetch Transport Fee Settings");
        }
    }
}

// Get scholarship rules
$scholarship_rules = [];
try {
    $stmt_rules = $conn->prepare("SELECT sr.*, st.type_name, st.type_code 
                                   FROM tbl_scholarship_rules sr
                                   JOIN tbl_scholarship_types st ON sr.scholarship_type_id = st.id
                                   WHERE (sr.course_id = ? OR sr.course_id IS NULL)
                                     AND (sr.group_id = ? OR sr.group_id IS NULL)
                                     AND sr.is_active = 1 
                                     AND st.is_active = 1
                                   ORDER BY st.id, sr.scholarship_discount_amount ASC");
    $stmt_rules->execute([$student['course_id'], $student['group_id']]);
    $scholarship_rules = $stmt_rules->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    if (!$is_api_call && function_exists('logDatabaseError')) {
        logDatabaseError($e, "Fetch Scholarship Rules");
    }
    $scholarship_rules = [];
}

// If API call, return JSON response
if ($is_api_call) {
    sendSuccessResponse([
        'student' => $student,
        'academic_year' => $current_academic_year,
        'fee_config' => $fee_config,
        'scholarship_types' => $scholarship_types,
        'scholarship_rules' => $scholarship_rules,
        'hostel_fee_amount' => $hostel_fee_amount,
        'transport_fee_amount' => $transport_fee_amount,
        'hostel_fee_config' => $hostel_fee_config,
        'transport_fee_config' => $transport_fee_config,
        'hostel_required' => $hostel_required,
        'transport_required' => $transport_required
    ]);
}


