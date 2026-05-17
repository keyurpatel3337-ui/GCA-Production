<?php

require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';
require_once HELPERS_PATH . 'fee_helper.php';

/**
 * Pending Payments Controller
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
    require_once $base_path . '/../common/helpers/error_logger.php';
    // Check if user is Accountant or Super Admin
    if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['accountant', 'super_admin'])) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

$page_title = "Pending Payments";
$page_breadcrumb = "Payments -";

// Get filters
$search = $_REQUEST['search'] ?? '';
$sort_by = $_REQUEST['sort_by'] ?? 'pending_desc';

try {
    // Pagination parameters
    $page = isset($_REQUEST['page']) ? max(1, intval($_REQUEST['page'])) : 1;
    $limit = isset($_REQUEST['limit']) ? max(1, intval($_REQUEST['limit'])) : 20;
    $offset = ($page - 1) * $limit;

    // 1. Get Global Summary & Total Count
    // Use tbl_student_fee_allocation (sfa) as the primary source of truth for pending status
    $baseQuery = "FROM tbl_student_fee_allocation sfa
                  JOIN tbl_gm_std_registration r ON sfa.student_id = r.id
                  WHERE sfa.pending_amount > 0 AND sfa.status != 'paid'";

    $whereClause = "";
    $params = [];
    if (!empty($search)) {
        $whereClause .= " AND (CONCAT(r.surname, ' ', r.student_name, ' ', IFNULL(r.fathers_name, '')) LIKE :search1 
                    OR r.aadhaar LIKE :search2 
                    OR r.mob LIKE :search3)";
        $searchParam = "%{$search}%";
        $params['search1'] = $searchParam;
        $params['search2'] = $searchParam;
        $params['search3'] = $searchParam;
    }

    $summarySql = "SELECT 
                    COUNT(*) as total_records,
                    COUNT(DISTINCT sfa.student_id) as student_count,
                    SUM(sfa.allocated_amount) as total_allocated,
                    SUM(sfa.paid_amount) as total_paid,
                    SUM(sfa.pending_amount) as total_pending,
                    SUM(sfa.scholarship_amount + sfa.additional_scholarship) as total_scholarship
                   $baseQuery $whereClause";

    $summaryResult = $dbOps->customSelect($summarySql, $params);

    $summary = [
        'student_count' => 0,
        'total_allocated' => 0,
        'total_paid' => 0,
        'total_pending' => 0,
        'total_scholarship' => 0
    ];
    $total_records = 0;

    if ($summaryResult && !empty($summaryResult)) {
        $row = $summaryResult[0];
        $summary = [
            'student_count' => intval($row['student_count'] ?? 0),
            'total_allocated' => floatval($row['total_allocated'] ?? 0),
            'total_paid' => floatval($row['total_paid'] ?? 0),
            'total_pending' => floatval($row['total_pending'] ?? 0),
            'total_scholarship' => floatval($row['total_scholarship'] ?? 0)
        ];
        $total_records = intval($row['total_records'] ?? 0);
    }

    // 2. Get Paginated Data
    $dataQuery = "SELECT sfa.student_id, sfa.due_date, sfa.status as alloc_status, r.mob as mobile  $baseQuery $whereClause";

    // Add sorting
    switch ($sort_by) {
        case 'pending_asc':
            $dataQuery .= " ORDER BY sfa.pending_amount ASC";
            break;
        case 'pending_desc':
            $dataQuery .= " ORDER BY sfa.pending_amount ASC";
            break;
        case 'due_date':
            $dataQuery .= " ORDER BY sfa.due_date ASC, sfa.pending_amount ASC";
            break;
        case 'name':
            $dataQuery .= " ORDER BY r.surname ASC, r.student_name ASC";
            break;
        default:
            $dataQuery .= " ORDER BY sfa.pending_amount ASC";
    }

    // Add Pagination
    $dataQuery .= " LIMIT $limit OFFSET $offset";

    $students_list = $dbOps->customSelect($dataQuery, $params) ?: [];
    
    $pending_fees = [];
    foreach ($students_list as $row) {
        // Use fee_helper to get 100% accurate, up-to-date breakdown
        $stu_summary = calculateStudentFeeSummary($conn, $row['student_id']);
        
        if ($stu_summary && $stu_summary['total_pending'] > 0) {
            $pending_fees[] = [
                'id' => $row['student_id'],
                'student_id' => $row['student_id'],
                'name' => $stu_summary['student_name'],
                'mobile' => $row['mobile'],
                'fee_type' => 'Total Student Fees',
                'allocated_amount' => $stu_summary['total_allocated'],
                'paid_amount' => $stu_summary['total_paid'],
                'pending_amount' => $stu_summary['total_pending'],
                'due_date' => $row['due_date'],
                'status' => match(strtolower($stu_summary['status'] ?? '')) {
                    'partially paid' => 'partial',
                    'fully paid'     => 'paid',
                    default          => strtolower($stu_summary['status'] ?? 'pending')
                },
                'scholarship_amount' => $stu_summary['total_waiver']
            ];
        }
    }

    // Prepare pagination meta
    $total_pages = ceil($total_records / $limit);
    $pagination = [
        'total_records' => $total_records,
        'total_pages' => $total_pages,
        'current_page' => $page,
        'limit' => $limit
    ];

    // Log success for debugging
    error_log("Pending Payments Success: " . json_encode([
        'count' => count($pending_fees),
        'summary' => $summary,
        'pagination' => $pagination,
        'search' => $search,
        'sort_by' => $sort_by
    ]));

} catch (PDOException $e) {
    // Log database error
    error_log("Pending Payments DB Error: " . json_encode([
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'query' => $dataQuery
    ]));

    if (!$is_api_call && function_exists('logDatabaseError')) {
        logDatabaseError($e, "Fetch Pending Payments");
    }

    $pending_fees = [];
    $summary = [
        'student_count' => 0,
        'total_allocated' => 0,
        'total_paid' => 0,
        'total_pending' => 0
    ];

    if ($is_api_call) {
        sendErrorResponse('Database error: ' . $e->getMessage(), 500);
    }
} catch (Exception $e) {
    // Log general error
    error_log("Pending Payments Error: " . json_encode([
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]));

    $pending_fees = [];
    $summary = [
        'student_count' => 0,
        'total_allocated' => 0,
        'total_paid' => 0,
        'total_pending' => 0
    ];

    if ($is_api_call) {
        sendErrorResponse('Error: ' . $e->getMessage(), 500);
    }
}

// If API call, return JSON response
if ($is_api_call) {
    sendSuccessResponse([
        'pending_fees' => $pending_fees,
        'summary' => $summary,
        'pagination' => $pagination ?? [],
        'applied_filters' => [
            'search' => $search,
            'sort_by' => $sort_by
        ]
    ]);
}


