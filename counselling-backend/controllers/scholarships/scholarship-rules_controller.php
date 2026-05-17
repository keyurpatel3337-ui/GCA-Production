<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';

/**
 * Scholarship Rules Controller
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
    // Check if user is Super Admin or Accountant
    if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_ACCOUNTANT)) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

// Handle AJAX request for single rule (works in both modes)
if (isset($_GET['get_rule'])) {
    header('Content-Type: application/json');
    try {
        $rule = $dbOps->selectOne('tbl_scholarship_rules', ['*'], ['id' => $_GET['get_rule']]);
        echo json_encode($rule);
    } catch (PDOException $e) {
        if (!$is_api_call && function_exists('logDatabaseError')) {
            logDatabaseError($e, "Fetch Single Scholarship Rule");
        }
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    exit;
}

// Fetch active scholarship types
$scholarship_types = [];
try {
    $scholarship_types = $dbOps->select('tbl_scholarship_types', ['id', 'type_name', 'type_code'], ['is_active' => 1], 'type_name ASC');
} catch (PDOException $e) {
    if (!$is_api_call && function_exists('logDatabaseError')) {
        logDatabaseError($e, "Fetch Scholarship Types");
    }
}

// Fetch active courses
$courses = [];
try {
    $courses = $dbOps->select('tbl_courses', ['id', 'course_name'], ['is_active' => 1], 'course_name ASC');
} catch (PDOException $e) {
    if (!$is_api_call && function_exists('logDatabaseError')) {
        logDatabaseError($e, "Fetch Courses");
    }
}

// Fetch active groups
$groups = [];
try {
    $groups = $dbOps->select('tbl_group', ['id', 'group_name'], ['is_active' => 1], 'group_name ASC');
} catch (PDOException $e) {
    if (!$is_api_call && function_exists('logDatabaseError')) {
        logDatabaseError($e, "Fetch Groups");
    }
}

// Get pagination and filter parameters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = isset($_GET['per_page']) ? max(1, min(100, intval($_GET['per_page']))) : 10;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$scholarship_type_filter = isset($_GET['scholarship_type']) ? intval($_GET['scholarship_type']) : 0;
$course_filter = isset($_GET['course_id']) ? $_GET['course_id'] : '';

// Build WHERE conditions
$whereConditions = [];
$whereParams = [];

if (!empty($search)) {
    $whereConditions[] = "(st.type_name LIKE :search OR st.type_code LIKE :search OR c.course_name LIKE :search OR g.group_name LIKE :search)";
    $whereParams[':search'] = "%$search%";
}

if ($scholarship_type_filter > 0) {
    $whereConditions[] = "sr.scholarship_type_id = :scholarship_type_id";
    $whereParams[':scholarship_type_id'] = $scholarship_type_filter;
}

if (!empty($course_filter)) {
    if ($course_filter === '11th') {
        $whereConditions[] = "sr.course_id IN (1, 2)";
    } elseif ($course_filter === '12th') {
        $whereConditions[] = "sr.course_id IN (4, 5)";
    } elseif ($course_filter === 'Reneet') {
        $whereConditions[] = "sr.course_id = 6";
    } else {
        $whereConditions[] = "sr.course_id = :course_id";
        $whereParams[':course_id'] = $course_filter;
    }
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Fetch total count
$total_records = 0;
try {
    $countQuery = "SELECT COUNT(*) as total
                   FROM tbl_scholarship_rules sr
                   LEFT JOIN tbl_scholarship_types st ON sr.scholarship_type_id = st.id
                   LEFT JOIN tbl_courses c ON sr.course_id = c.id
                   LEFT JOIN tbl_group g ON sr.group_id = g.id
                   $whereClause";
    $countResult = $dbOps->customSelect($countQuery, $whereParams);
    $total_records = $countResult[0]['total'] ?? 0;
} catch (PDOException $e) {
    if (!$is_api_call && function_exists('logDatabaseError')) {
        logDatabaseError($e, "Count Scholarship Rules");
    }
}

// Calculate pagination
$total_pages = $total_records > 0 ? ceil($total_records / $per_page) : 1;
$offset = ($page - 1) * $per_page;

// Fetch rules with pagination
$rules = [];
try {
    $query = "SELECT sr.*, 
              st.type_name, st.type_code,
              c.course_name,
              g.group_name
              FROM tbl_scholarship_rules sr
              LEFT JOIN tbl_scholarship_types st ON sr.scholarship_type_id = st.id
              LEFT JOIN tbl_courses c ON sr.course_id = c.id
              LEFT JOIN tbl_group g ON sr.group_id = g.id
              $whereClause
              ORDER BY sr.id DESC
              LIMIT $per_page OFFSET $offset";

    $rules = $dbOps->customSelect($query, $whereParams);

    if ($rules === false) {
        $rules = [];
    }
} catch (PDOException $e) {
    if (!$is_api_call && function_exists('logDatabaseError')) {
        logDatabaseError($e, "Fetch All Scholarship Rules");
    }

    if ($is_api_call) {
        sendErrorResponse('Database error: ' . $e->getMessage(), 500);
    }
}

// If API call, return JSON response
if ($is_api_call) {
    sendSuccessResponse([
        'rules' => $rules,
        'dropdowns' => [
            'scholarship_types' => $scholarship_types,
            'courses' => $courses,
            'groups' => $groups
        ],
        'pagination' => [
            'current_page' => $page,
            'per_page' => $per_page,
            'total_records' => $total_records,
            'total_pages' => $total_pages
        ],
        'applied_filters' => [
            'search' => $search,
            'scholarship_type' => $scholarship_type_filter,
            'course_id' => $course_filter
        ]
    ]);
}

$page_title = "Scholarship Rules Management";
$page_breadcrumb = "Scholarship Rules";


