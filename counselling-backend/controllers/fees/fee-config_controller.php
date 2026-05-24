<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';

/**
 * Fee Configuration Controller
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
    // Check if user is Super Admin or Principle
    if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE)) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

// Handle POST request for creating fee config
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once $base_path . '/controllers/fees/fee-config-save.php';
    exit;
}

// Check if current user is Super Admin (for split label editing)
$isSuperAdmin = !$is_api_call && function_exists('hasRole') && hasRole(ROLE_SUPER_ADMIN);

// Fixed split labels: GCA, GHSS, MST, SGM
// No need to fetch from payment gateway table

// Handle AJAX request for single config
if (isset($_GET['get_config'])) {
    header('Content-Type: application/json');
    $config_id = intval($_GET['get_config']);
    $sql = "SELECT fc.*, 
        m.medium_name as medium, 
        g.group_name as group_type,
        s.school_code as school_name
        FROM tbl_fee_config fc 
        LEFT JOIN tbl_medium m ON fc.medium_id = m.id 
        LEFT JOIN tbl_group g ON fc.group_id = g.id 
        LEFT JOIN tbl_schools s ON fc.school_id = s.id
        WHERE fc.id = ?";
    $config = $dbOps->customSelect($sql, [$config_id]);
    echo json_encode($config[0] ?? null);
    exit;
}

// Handle AJAX request for terms by academic year
if (isset($_GET['get_terms'])) {
    header('Content-Type: application/json');
    $academic_year_id = intval($_GET['academic_year_id'] ?? 0);
    $terms = $dbOps->select('tbl_term', ['id', 'term_name', 'term_number'], ['academic_year_id' => $academic_year_id, 'is_active' => 1], 'term_number ASC');
    echo json_encode($terms);
    exit;
}

// Fetch dropdown data
try {
    $academic_years = $dbOps->select('tbl_academic_years', ['id', 'year_name'], ['is_active' => 1], 'year_name DESC');

    $schools = $dbOps->select('tbl_schools', ['id', 'school_name'], ['is_active' => 1], 'school_name ASC');

    $sql_courses = "SELECT c.id, c.course_name, b.board_name 
        FROM tbl_courses c 
        LEFT JOIN tbl_boards b ON c.board_id = b.id 
        WHERE c.is_active = 1 
        ORDER BY c.course_name DESC";
    $courses = $dbOps->customSelect($sql_courses);

    $mediums = $dbOps->select('tbl_medium', ['id', 'medium_name'], ['is_active' => 1], 'medium_name ASC');

    $groups = $dbOps->select('tbl_group', ['id', 'group_name'], ['is_active' => 1], 'group_name ASC');

    $sql_terms = "SELECT t.id, t.term_name, t.term_number, t.academic_year_id, ay.year_name 
        FROM tbl_term t 
        JOIN tbl_academic_years ay ON t.academic_year_id = ay.id
        WHERE t.is_active = 1 
        ORDER BY ay.year_name DESC, t.term_number desc";
    $terms = $dbOps->customSelect($sql_terms);

    // Fixed split labels: GCA, GHSS, MST, SGM
    $split_labels = ['GCA', 'GHSS', 'MST', 'SGM'];

    // Fetch all fee configurations with pagination, search, and filters
    $page = isset($_REQUEST['page']) ? max(1, intval($_REQUEST['page'])) : 1;
    $perPage = isset($_REQUEST['per_page']) ? max(1, intval($_REQUEST['per_page'])) : 10;
    $search = isset($_REQUEST['search']) ? trim($_REQUEST['search']) : '';
    $academicYearFilter = isset($_REQUEST['academic_year']) ? trim($_REQUEST['academic_year']) : '';
    $schoolIdFilter = isset($_REQUEST['school_id']) ? intval($_REQUEST['school_id']) : 0;

    $offset = ($page - 1) * $perPage;

    // Handle export all - bypass pagination
    if (isset($_REQUEST['export']) && $_REQUEST['export'] == 1) {
        $perPage = 10000; // Large enough for all records
        $offset = 0;
        $page = 1;
    }

    // Build WHERE clause
    $whereConditions = [];
    $params = [];

    if (!empty($search)) {
        $whereConditions[] = "(fc.course_name LIKE ? OR fc.academic_year LIKE ? OR s.school_name LIKE ? OR m.medium_name LIKE ? OR g.group_name LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    if (!empty($academicYearFilter)) {
        $whereConditions[] = "fc.academic_year = ?";
        $params[] = $academicYearFilter;
    }

    if (!empty($schoolIdFilter)) {
        $whereConditions[] = "fc.school_id = ?";
        $params[] = $schoolIdFilter;
    }

    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

    // Count total records
    $countSql = "SELECT COUNT(*) as total 
        FROM tbl_fee_config fc 
        LEFT JOIN tbl_medium m ON fc.medium_id = m.id 
        LEFT JOIN tbl_group g ON fc.group_id = g.id 
        LEFT JOIN tbl_schools s ON fc.school_id = s.id
        $whereClause";

    $countResult = $dbOps->customSelect($countSql, $params);
    $totalRecords = $countResult[0]['total'] ?? 0;
    $totalPages = ceil($totalRecords / $perPage);

    // Fetch fee configurations with pagination
    $sql_configs = "SELECT fc.*, 
        fc.number_of_installments as installment_count,
        m.medium_name as medium,
        g.group_name as group_type,
        s.school_code as school_name
        FROM tbl_fee_config fc 
        LEFT JOIN tbl_medium m ON fc.medium_id = m.id 
        LEFT JOIN tbl_group g ON fc.group_id = g.id 
        LEFT JOIN tbl_schools s ON fc.school_id = s.id
        $whereClause
        ORDER BY fc.id DESC
        LIMIT $perPage OFFSET $offset";

    $fee_configs = $dbOps->customSelect($sql_configs, $params);
} catch (PDOException $e) {
    if ($is_api_call) {
        sendErrorResponse('Database error: ' . $e->getMessage(), 500);
    }
    $fee_configs = [];
}

// If API call, return JSON response
if ($is_api_call) {
    $appliedFilters = [];
    if (!empty($academicYearFilter)) {
        $appliedFilters['academic_year'] = $academicYearFilter;
    }
    if (!empty($schoolIdFilter)) {
        $appliedFilters['school_id'] = $schoolIdFilter;
    }
    if (!empty($search)) {
        $appliedFilters['search'] = $search;
    }

    sendSuccessResponse([
        'fee_configs' => $fee_configs,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $perPage,
            'total_records' => $totalRecords,
            'total_pages' => $totalPages
        ],
        'applied_filters' => $appliedFilters,
        'academic_years' => $academic_years ?? [],
        'schools' => $schools ?? [],
        'courses' => $courses ?? [],
        'mediums' => $mediums ?? [],
        'groups' => $groups ?? [],
        'terms' => $terms ?? [],
        'split_labels' => $split_labels ?? [],
        'is_super_admin' => $isSuperAdmin
    ]);
}

$page_title = "Fee Configuration Management";
$page_breadcrumb = "Fee Config";


