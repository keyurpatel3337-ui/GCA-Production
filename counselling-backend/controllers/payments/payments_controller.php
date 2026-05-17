<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';

/**
 * All Payments Controller
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
    // Check if user is Accountant or Super Admin
    if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['accountant', 'super_admin'])) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

$page_title = "All Payments";
$page_breadcrumb = "Payments -";

// Get filter parameters
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$payment_mode = $_GET['payment_mode'] ?? '';
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';

// Pagination parameters
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = max(1, min(100, intval($_GET['per_page'] ?? 25)));
$offset = ($page - 1) * $per_page;

// Build base query
$base_query = "FROM tbl_payments p
          INNER JOIN tbl_gm_std_registration s ON p.student_id = s.id
          LEFT JOIN tbl_users u ON p.created_by = u.id
          WHERE 1=1";

$params = [];

if (!empty($search)) {
    $base_query .= " AND (
        s.student_name LIKE :search1 
        OR p.receipt_no LIKE :search2 
        OR s.aadhaar LIKE :search3
        OR s.surname LIKE :search4
        OR CONCAT(s.surname, ' ', s.student_name) LIKE :search5
    )";
    $params['search1'] = "%$search%";
    $params['search2'] = "%$search%";
    $params['search3'] = "%$search%";
    $params['search4'] = "%$search%";
    $params['search5'] = "%$search%";
}

if (!empty($status)) {
    $base_query .= " AND p.status = :status";
    $params['status'] = $status;
}

if (!empty($payment_mode)) {
    $base_query .= " AND p.payment_mode = :payment_mode";
    $params['payment_mode'] = $payment_mode;
}

if (!empty($from_date)) {
    $base_query .= " AND p.payment_date >= :from_date";
    $params['from_date'] = $from_date;
}

if (!empty($to_date)) {
    $base_query .= " AND p.payment_date <= :to_date";
    $params['to_date'] = $to_date;
}

// Data query with pagination
$select_query = "SELECT p.*, 
          s.student_name, 
          s.surname, 
          s.fathers_name, 
          s.aadhaar, 
          u.name as created_by_name
          " . $base_query . " ORDER BY p.payment_date ASC, p.id DESC LIMIT $per_page OFFSET $offset";

// Count query for pagination
$count_query = "SELECT COUNT(*) as total " . $base_query;

try {
    // Get total count
    $count_result = $dbOps->customSelect($count_query, $params);
    $total_records = $count_result[0]['total'] ?? 0;
    $total_pages = ceil($total_records / $per_page);

    // Get paginated data
    $payments = $dbOps->customSelect($select_query, $params);

    // Get statistics (without pagination filters for overall stats)
    $stats_query = "SELECT 
                    COUNT(*) as total_payments,
                    SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as total_collected,
                    SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as total_pending
                    FROM tbl_payments";
    $stats_result = $dbOps->customSelect($stats_query);
    $stats = $stats_result[0] ?? ['total_payments' => 0, 'total_collected' => 0, 'total_pending' => 0];
} catch (PDOException $e) {
    if (!$is_api_call && function_exists('logDatabaseError')) {
        logDatabaseError($e, "Fetch Payments");
    }
    $payments = [];
    $total_records = 0;
    $total_pages = 1;
    $stats = ['total_payments' => 0, 'total_collected' => 0, 'total_pending' => 0];

    if ($is_api_call) {
        sendErrorResponse('Database error: ' . $e->getMessage(), 500);
    }
}

// If API call, return JSON response
if ($is_api_call) {
    sendSuccessResponse([
        'payments' => $payments,
        'stats' => $stats,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $per_page,
            'total_records' => $total_records,
            'total_pages' => $total_pages
        ],
        'applied_filters' => [
            'search' => $search,
            'status' => $status,
            'payment_mode' => $payment_mode,
            'from_date' => $from_date,
            'to_date' => $to_date
        ]
    ]);
}



