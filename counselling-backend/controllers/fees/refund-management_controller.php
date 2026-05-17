<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';

/**
 * Refund Management Controller
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
    // Check admin access
    if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_COUNSELLOR) && !hasRole(ROLE_ACCOUNTANT)) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

$user_role = $_SESSION['role_name'] ?? 'admin';

// Pagination
$page = isset($_REQUEST['page']) ? max(1, (int) $_REQUEST['page']) : 1;
$perPage = isset($_REQUEST['per_page']) ? max(1, (int) $_REQUEST['per_page']) : 15;
$offset = ($page - 1) * $perPage;

// Fetch all refund requests
$filter_status = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Base Query Conditions
$where_clauses = ["1=1"];
$params = [];

if ($filter_status !== 'all') {
    $where_clauses[] = "request_status = ?";
    $params[] = $filter_status;
}

if ($search) {
    $where_clauses[] = "(request_number LIKE ? OR full_name LIKE ? OR receipt_number LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_sql = implode(' AND ', $where_clauses);

$refund_requests = [];
$stats = [];
$totalRecords = 0;
$totalPages = 1;

try {
    // 1. Get Statistics (Global, not filtered by pagination, but MAYBE filtered by search? 
    // Usually stats are dashboard-like, so global is better. Keeping original logic which was global for stats)
    $stats_query = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN request_status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN request_status = 'under_review' THEN 1 ELSE 0 END) as under_review,
            SUM(CASE WHEN request_status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN request_status = 'processing' THEN 1 ELSE 0 END) as processing,
            SUM(CASE WHEN request_status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN request_status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN request_status = 'completed' THEN refund_amount ELSE 0 END) as total_refunded
        FROM tbl_refund_requests
    ";
    $stats = $dbOps->customSelectOne($stats_query, []) ?: [];

    // 2. Get Total Count for Pagination (Filtered)
    $countQuery = "SELECT COUNT(*) as total FROM vw_refund_requests_detailed WHERE $where_sql";
    $countResult = $dbOps->customSelect($countQuery, $params);
    $totalRecords = $countResult[0]['total'] ?? 0;
    $totalPages = ceil($totalRecords / $perPage);

    // 3. Get Data (Filtered & Paginated)
    $sql = "SELECT * FROM vw_refund_requests_detailed WHERE $where_sql";
    $sql .= " ORDER BY 
        CASE request_status
            WHEN 'pending' THEN 1
            WHEN 'under_review' THEN 2
            WHEN 'approved' THEN 3
            WHEN 'processing' THEN 4
            ELSE 5
        END,
        requested_at ASC
        LIMIT " . (int) $perPage . " OFFSET " . (int) $offset;

    $refund_requests = $dbOps->customSelect($sql, $params) ?: [];
} catch (PDOException $e) {
    $refund_requests = [];
    $stats = [];
    $totalRecords = 0;

    if ($is_api_call) {
        sendErrorResponse('Database error: ' . $e->getMessage(), 500);
    }
}

// If API call, return JSON response
if ($is_api_call) {
    sendSuccessResponse([
        'refund_requests' => $refund_requests,
        'stats' => $stats,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $perPage,
            'total_records' => $totalRecords,
            'total_pages' => $totalPages
        ],
        'total' => $totalRecords,
        'applied_filters' => [
            'status' => $filter_status,
            'search' => $search
        ]
    ]);
}

if (!function_exists('getStatusBadge')) {
    function getStatusBadge($status)
    {
        $badges = [
            'pending' => '<span class="badge bg-warning"><i class="bi bi-clock"></i> Pending</span>',
            'under_review' => '<span class="badge bg-info"><i class="bi bi-search"></i> Under Review</span>',
            'approved' => '<span class="badge bg-success"><i class="bi bi-check"></i> Approved</span>',
            'rejected' => '<span class="badge bg-danger"><i class="bi bi-x"></i> Rejected</span>',
            'processing' => '<span class="badge bg-primary"><i class="bi bi-hourglass-split"></i> Processing</span>',
            'completed' => '<span class="badge bg-success"><i class="bi bi-check-circle"></i> Completed</span>',
            'failed' => '<span class="badge bg-danger"><i class="bi bi-exclamation-triangle"></i> Failed</span>'
        ];
        return $badges[$status] ?? '<span class="badge bg-secondary">' . ucfirst($status) . '</span>';
    }
}
