<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';

/**
 * Admission Confirmation List Controller
 * Supports both API mode (JSON response) and direct inclusion mode
 */

$base_path = dirname(dirname(__DIR__));

require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;

// Initialize Database Operations
$dbOps = new DatabaseOperations();

// Check if this is an API call (via index.php router)
$is_api_call = defined('API_MODE') || (isset($_GET['route']) && !empty($_GET['route']));

// Include dependencies

// require_once OPERATION_FILE; // Already included above
// Check permissions (even for API calls)
if ($is_api_call) {
    if (!isset($_SESSION['user_id'])) {
        sendErrorResponse('Unauthorized access', 401);
    }
} else {
    // Check if user is Counsellor, Principal, or Super Admin
    if (!hasRole(ROLE_COUNSELLOR) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN)) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

$user_name = $_SESSION['user_name'] ?? '';
$counsellor_id = $_SESSION['user_id'] ?? null;
$current_page = 'admission-confirm';

// Get students assigned to this counsellor pending admission confirmation
$search = $_GET['search'] ?? '';

// For Principal and Super Admin, show all students pending admission
$is_admin_or_principle = !$is_api_call && function_exists('hasRole') && (hasRole(ROLE_SUPER_ADMIN) || hasRole(ROLE_PRINCIPLE));

// For API calls, we can pass a counsellor_id parameter or show all
if ($is_api_call) {
    $is_admin_or_principle = !isset($_GET['counsellor_id']) || empty($_GET['counsellor_id']);
    if (isset($_GET['counsellor_id'])) {
        $counsellor_id = $_GET['counsellor_id'];
    }
}

// Pagination
$page = isset($_REQUEST['page']) ? max(1, (int) $_REQUEST['page']) : 1;
$perPage = isset($_REQUEST['per_page']) ? max(1, (int) $_REQUEST['per_page']) : 15;
$offset = ($page - 1) * $perPage;

// Base Condition
$where_clauses = ["(s.admission_confirmed = 0 OR s.admission_confirmed IS NULL)"];
$params = [];

if (!$is_admin_or_principle && $counsellor_id) {
    $where_clauses[] = "s.counsellor_id = ?";
    $params[] = $counsellor_id;
}

if (!empty($search)) {
    $where_clauses[] = "(s.student_name LIKE ? OR s.surname LIKE ? OR s.mob LIKE ? OR s.aadhaar LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

$where_sql = implode(' AND ', $where_clauses);

try {
    // 1. Get Total Count
    $countQuery = "SELECT COUNT(*) as total FROM tbl_gm_std_registration s WHERE $where_sql";
    $countStmt = $conn->prepare($countQuery);
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalRecords / $perPage);

    // 2. Main Query
    $query = "SELECT s.*, b.board_name, m.medium_name, g.group_name, c.course_name
              FROM tbl_gm_std_registration s
              LEFT JOIN tbl_boards b ON s.board_id = b.id
              LEFT JOIN tbl_medium m ON s.medium_id = m.id
              LEFT JOIN tbl_group g ON s.group_id = g.id
              LEFT JOIN tbl_courses c ON s.course_id = c.id
              WHERE $where_sql
              ORDER BY s.id ASC
              LIMIT " . (int) $perPage . " OFFSET " . (int) $offset;

    $students = $dbOps->customSelect($query, $params) ?: [];

    // Get fee configuration for each student's course
    foreach ($students as &$student) {
        $fee_config_data = $dbOps->selectOne('tbl_fee_config', ['total_fees', 'token_fee'], [
            'course_id' => $student['course_id'],
            'medium_id' => $student['medium_id'],
            'group_id' => $student['group_id'],
            'is_active' => 1
        ]);
        $student['course_fee'] = $fee_config_data['total_fees'] ?? null;
        $student['token_fee'] = $fee_config_data['token_fee'] ?? null;
    }
    unset($student);
} catch (PDOException $e) {
    if (!$is_api_call && function_exists('logDatabaseError')) {
        logDatabaseError($e, "Fetch Pending Admissions");
    }
    $students = [];
    $totalRecords = 0;

    if ($is_api_call) {
        sendErrorResponse('Database error: ' . $e->getMessage(), 500);
    }
}

$page_title = "Students Pending Admission Confirmation";
$page_breadcrumb = "Admission Confirm";

// If API call, return JSON response
if ($is_api_call) {
    sendSuccessResponse([
        'students' => $students,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $perPage,
            'total_records' => $totalRecords,
            'total_pages' => $totalPages
        ],
        'total' => $totalRecords,
        'applied_filters' => [
            'search' => $search,
            'counsellor_id' => $counsellor_id
        ]
    ]);
}

