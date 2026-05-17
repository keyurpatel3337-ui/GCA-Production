<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/common/transaction_helper.php';

/**
 * Add Payment Controller
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
    // Check if user is Accountant
    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'accountant') {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

$page_title = "Add Payment";
$page_breadcrumb = "Payment -";

// Generate transaction ID for this payment
$generated_transaction_id = '';
if (function_exists('generateUniqueTransactionID')) {
    $generated_transaction_id = generateUniqueTransactionID('GMI');
} else {
    $generated_transaction_id = 'GMI-' . date('YmdHis') . '-' . rand(1000, 9999);
}

// Get all students
try {
    $students = $dbOps->customSelect(
        "SELECT id, CONCAT(surname, ' ', student_name, ' ', IFNULL(fathers_name, '')) as name, aadhaar, mob 
         FROM tbl_gm_std_registration 
         WHERE status = 1 
         ORDER BY id ASC"
    );

    // Get courses with fee configuration
    $fees = $dbOps->select('tbl_fee_config', [
        'id',
        'course_name',
        'course_id',
        'medium_id',
        'group_id',
        'school_fee',
        'trust_facilities_fee',
        'tuition_fee_part1',
        'tuition_fee_part2',
        'hostel_fee',
        'token_fee',
        'total_fees',
        'number_of_installments'
    ], ['is_active' => 1], 'course_name');
} catch (PDOException $e) {
    if (!$is_api_call && function_exists('logDatabaseError')) {
        logDatabaseError($e, "Fetch Students and Fees");
    }
    $students = [];
    $fees = [];

    if ($is_api_call) {
        sendErrorResponse('Database error: ' . $e->getMessage(), 500);
    }
}

// Check if student_id is passed via POST or URL
$preselected_student = null;
$preselected_student_id = $_REQUEST['student_id'] ?? null;
if ($preselected_student_id) {
    try {
        $sql_student = "SELECT r.id, r.student_name, r.surname, r.fathers_name, 
                               CONCAT(r.surname, ' ', r.student_name, ' ', IFNULL(r.fathers_name, '')) as full_name,
                               r.aadhaar, r.mob, 
                               r.course_id, r.medium_id, r.group_id, r.board_id, r.school_id,
                               r.token_fees_paid, r.admission_confirmed, r.gender,
                               c.course_name, m.medium_name, g.group_name, b.board_name, sc.school_name,
                               IFNULL(t.term_name, 'Semester 1') as term_name
                        FROM tbl_gm_std_registration r
                        LEFT JOIN tbl_courses c ON r.course_id = c.id
                        LEFT JOIN tbl_medium m ON r.medium_id = m.id
                        LEFT JOIN tbl_group g ON r.group_id = g.id
                        LEFT JOIN tbl_boards b ON r.board_id = b.id
                        LEFT JOIN tbl_schools sc ON r.school_id = sc.id
                        LEFT JOIN tbl_enrolled_students es ON r.id = es.registration_id AND es.is_active = 1
                        LEFT JOIN tbl_term t ON es.current_term_id = t.id
                        WHERE r.id = ?
                        ORDER BY es.enrollment_id DESC LIMIT 1";
        $result = $dbOps->customSelect($sql_student, [$preselected_student_id]);
        $preselected_student = $result[0] ?? null;
    } catch (PDOException $e) {
        if (!$is_api_call && function_exists('logDatabaseError')) {
            logDatabaseError($e, "Fetch Preselected Student");
        }
    }
}

// If API call, return JSON response
if ($is_api_call) {
    sendSuccessResponse([
        'students' => $students,
        'fees' => $fees,
        'preselected_student' => $preselected_student,
        'generated_transaction_id' => $generated_transaction_id
    ]);
}
