<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';

/**
 * Receipts List Controller
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
    // Check if user has appropriate role
    if (!hasRole(ROLE_ACCOUNTANT) && !hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_COUNSELLOR)) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';

// Build query - Using tbl_payments as the source of truth for receipts
// (receipt-print-pdf.php already reads from tbl_payments, not tbl_receipts)
$query = "SELECT p.id, p.receipt_no, p.amount, p.payment_date as issued_date,
                 p.payment_mode, p.payment_type as payment_for, p.fee_component,
                 p.status, p.student_id, p.created_at, p.created_by as generated_by,
                 CONCAT(s.surname, ' ', s.student_name, ' ', IFNULL(s.fathers_name, '')) as student_name
          FROM tbl_payments p
          INNER JOIN tbl_gm_std_registration s ON p.student_id = s.id
          WHERE p.receipt_no IS NOT NULL AND p.receipt_no != ''";

$params = [];

if (!empty($search)) {
    $query .= " AND (s.student_name LIKE :search OR s.surname LIKE :search OR s.fathers_name LIKE :search OR p.receipt_no LIKE :search OR CONCAT(s.surname, ' ', s.student_name, ' ', s.fathers_name) LIKE :search)";
    $params['search'] = "%$search%";
}

if (!empty($from_date)) {
    $query .= " AND p.payment_date >= :from_date";
    $params['from_date'] = $from_date;
}

if (!empty($to_date)) {
    $query .= " AND p.payment_date <= :to_date";
    $params['to_date'] = $to_date;
}

$query .= " ORDER BY p.payment_date DESC, p.id DESC";

try {
    $receipts = $dbOps->customSelect($query, $params);
} catch (PDOException $e) {
    if (!$is_api_call && function_exists('logDatabaseError')) {
        logDatabaseError($e, "Fetch Receipts");
    }
    if ($is_api_call) {
        sendErrorResponse('Database error: ' . $e->getMessage(), 500);
    }
    $receipts = [];
}

if ($is_api_call) {
    sendSuccessResponse([
        'receipts' => $receipts,
        'applied_filters' => [
            'search' => $search,
            'from_date' => $from_date,
            'to_date' => $to_date
        ]
    ]);
}


