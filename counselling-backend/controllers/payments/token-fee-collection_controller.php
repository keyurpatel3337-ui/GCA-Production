<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';

/**
 * Token Fee Collection List Controller
 * Supports both API mode (JSON response) and direct inclusion mode
 */

$base_path = dirname(dirname(__DIR__));

require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;

// Initialize database operations
$dbOps = new DatabaseOperations();

// Check if this is an API call
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

// Get filter parameters
$search = $_GET['search'] ?? '';
$payment_status = $_GET['payment_status'] ?? 'pending';

// Build query for admitted students pending token fee payment
$query = "SELECT s.*, 
          b.board_name as board,
          c.course_name,
          fc.token_fee
          FROM tbl_gm_std_registration s
          LEFT JOIN tbl_boards b ON s.board_id = b.id
          LEFT JOIN tbl_courses c ON s.course_id = c.id
          LEFT JOIN tbl_fee_config fc ON s.course_id = fc.course_id 
              AND s.school_id = fc.school_id 
              AND s.medium_id = fc.medium_id 
              AND s.group_id = fc.group_id 
              AND fc.is_active = 1
          WHERE s.admission_confirmed = 1
          AND (s.is_enrolled = 0 OR s.is_enrolled IS NULL)";

$params = [];

// Filter by payment status
if ($payment_status === 'pending') {
    $query .= " AND (s.token_fees_paid = 0 OR s.token_fees_paid IS NULL)";
} elseif ($payment_status === 'paid') {
    $query .= " AND s.token_fees_paid = 1";
}

if (!empty($search)) {
    $query .= " AND (
        s.student_name LIKE :search1 
        OR s.surname LIKE :search2 
        OR s.mob LIKE :search3 
        OR s.aadhaar LIKE :search4 
        OR s.admission_letter_number LIKE :search5
        OR CONCAT(s.surname, ' ', s.student_name) LIKE :search6
        OR CONCAT(s.surname, ' ', s.student_name, ' ', s.fathers_name) LIKE :search7
    )";
    $searchTerm = "%$search%";
    $params['search1'] = $searchTerm;
    $params['search2'] = $searchTerm;
    $params['search3'] = $searchTerm;
    $params['search4'] = $searchTerm;
    $params['search5'] = $searchTerm;
    $params['search6'] = $searchTerm;
    $params['search7'] = $searchTerm;
}

$query .= " ORDER BY s.admission_confirmed_date ASC";

try {
    $students = $dbOps->customSelect($query, $params);

    // Get summary statistics
    $sql_summary = "SELECT 
                        COUNT(*) as total_students,
                        SUM(CASE WHEN token_fees_paid = 1 THEN 1 ELSE 0 END) as paid_count,
                        SUM(CASE WHEN token_fees_paid = 0 OR token_fees_paid IS NULL THEN 1 ELSE 0 END) as pending_count
                        FROM tbl_gm_std_registration 
                        WHERE admission_confirmed = 1
                        AND (is_enrolled = 0 OR is_enrolled IS NULL)";
    $summary_result = $dbOps->customSelect($sql_summary);
    $summary = $summary_result[0] ?? ['total_students' => 0, 'paid_count' => 0, 'pending_count' => 0];
} catch (PDOException $e) {
    if (!$is_api_call && function_exists('logDatabaseError')) {
        logDatabaseError($e, "Fetch Students for Token Fee Collection");
    }
    if ($is_api_call) {
        sendErrorResponse('Database error: ' . $e->getMessage(), 500);
    }
    $students = [];
    $summary = ['total_students' => 0, 'paid_count' => 0, 'pending_count' => 0];
}

if ($is_api_call) {
    sendSuccessResponse([
        'students' => $students,
        'summary' => $summary,
        'applied_filters' => [
            'search' => $search,
            'payment_status' => $payment_status
        ]
    ]);
}


