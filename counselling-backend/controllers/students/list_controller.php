<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';
require_once HELPERS_PATH . 'fee_helper.php';

/**
 * Student List Controller
 * Handles data fetching, filtering, and sorting for the students list.
 * Supports both API mode (JSON response) and direct inclusion mode.
 */

// Since this file will be included by list.php, we need to ensure paths are correct
// If accessed directly (not recommended), we use __DIR__ to find root
$base_path = dirname(dirname(__DIR__));

require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;

// Initialize Database Operations
$dbOps = new DatabaseOperations();
/** @var PDO $conn */
global $conn;

// Check if this is an API call (via index.php router)
$is_api_call = defined('API_MODE') || (isset($_GET['route']) && !empty($_GET['route']));

// Include additional dependencies
require_once BACKEND_GLOBALVARIABLE;
require_once $base_path . '/../common/helpers/error_logger.php';
require_once $base_path . '/common/pagination.php';

// Check permissions (even for API calls)
if ($is_api_call) {
    if (!isset($_SESSION['user_id']) && !isset($_SESSION['student_id'])) {
        sendErrorResponse('Unauthorized access', 401);
    }
} else {
    // Check if user has appropriate role for non-API inclusion
    if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_COUNSELLOR) && !hasRole(ROLE_ACCOUNTANT)) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

$page_title = "Manage Students";
$page_breadcrumb = "Students -";

// Get filter/search parameters - Support both GET and POST
// API calls send as GET query params, direct forms might POST
$search = $_REQUEST['search'] ?? '';
$filter_board = $_REQUEST['board'] ?? '';
$filter_academic_year = $_REQUEST['academic_year'] ?? '';
$filter_group = $_REQUEST['group'] ?? '';
$filter_course = $_REQUEST['course'] ?? '';
$filter_medium = $_REQUEST['medium'] ?? '';
$filter_school = $_REQUEST['school'] ?? '';
$filter_gender = $_REQUEST['gender'] ?? '';
$filter_hostel = $_REQUEST['hostel_required'] ?? '';
$filter_transport = $_REQUEST['transport_required'] ?? '';
$filter_campus = $_REQUEST['campus'] ?? '';
$filter_division = $_REQUEST['division'] ?? '';
$sort_by = $_REQUEST['sort'] ?? 'id';
$sort_order = $_REQUEST['order'] ?? 'asc';
$enrolled_only = isset($_REQUEST['enrolled_only']) && ($_REQUEST['enrolled_only'] == 1 || $_REQUEST['enrolled_only'] === 'true');

// Validate sort parameters
$valid_sort_columns = ['id', 'name'];
$valid_sort_orders = ['asc', 'desc'];
if (!in_array($sort_by, $valid_sort_columns))
    $sort_by = 'id';
if (!in_array($sort_order, $valid_sort_orders))
    $sort_order = 'desc';

// Fetch dropdown data for filters - Using Operation.php
try {
    // Boards
    $boards = $dbOps->select('tbl_boards', ['id', 'board_name'], ['is_active' => 1], 'board_name ASC');
    if ($boards === false)
        $boards = [];

    // Academic Years
    $academic_years = $dbOps->select('tbl_academic_years', ['id', 'year_name'], ['is_active' => 1], 'year_name DESC');
    if ($academic_years === false)
        $academic_years = [];

    // Groups
    $groups = $dbOps->select('tbl_group', ['id', 'group_name'], ['is_active' => 1], 'group_name ASC');
    if ($groups === false)
        $groups = [];

    // Courses
    $courses = $dbOps->select('tbl_courses', ['id', 'course_name'], ['is_active' => 1], 'course_name ASC');
    if ($courses === false)
        $courses = [];

    // Mediums
    $mediums = $dbOps->select('tbl_medium', ['id', 'medium_name'], ['is_active' => 1], 'medium_name ASC');
    if ($mediums === false)
        $mediums = [];

    // Schools
    $schools = $dbOps->select('tbl_schools', ['id', 'school_name'], ['is_active' => 1], 'school_name ASC');
    if ($schools === false)
        $schools = [];

    // Campuses
    $campuses = $dbOps->select('tbl_campuses', ['id', 'campus_name'], ['is_active' => 1], 'campus_name ASC');
    if ($campuses === false)
        $campuses = [];

    // Divisions
    $divisions = $dbOps->select('tbl_division', ['id', 'division_name'], ['is_active' => 1], 'division_name ASC');
    if ($divisions === false)
        $divisions = [];

    // Counsellors (all active users with counsellor role)
    $counsellors = $dbOps->customSelect("SELECT id, name FROM tbl_users WHERE status = 'active' ORDER BY name DESC", []) ?: [];
} catch (PDOException $e) {
    logDatabaseError($e, "Fetch Filter Dropdowns");
    $boards = $academic_years = $groups = $courses = $mediums = $schools = [];
}

// Build search filter - search by full name (surname, student_name, fathers_name) or mobile
$searchWhere = "";
$searchParams = [];
if ($search) {
    if (is_numeric($search)) {
        $searchWhere = "AND (r.id = ? OR r.mob LIKE ? OR r.aadhaar LIKE ? OR e.enrollment_no LIKE ?)";
        $searchParams = [$search, "%$search%", "%$search%", "%$search%"];
    } else {
        $fuzzySearch = "%" . str_replace(' ', '%', $search) . "%";
        $searchWhere = "AND (r.student_name LIKE ? OR r.surname LIKE ? OR r.fathers_name LIKE ? OR r.mob LIKE ? OR r.aadhaar LIKE ? OR e.enrollment_no LIKE ? OR CONCAT_WS(' ', r.surname, r.student_name, r.fathers_name) LIKE ? OR CONCAT_WS(' ', r.student_name, r.surname) LIKE ?)";
        $searchParams = [$fuzzySearch, $fuzzySearch, $fuzzySearch, $fuzzySearch, $fuzzySearch, $fuzzySearch, $fuzzySearch, $fuzzySearch];
    }
}

// Build filter conditions
$filterWhere = "";
$filterParams = [];
if ($filter_board) {
    $filterWhere .= " AND r.board_id = ?";
    $filterParams[] = $filter_board;
}
if ($filter_academic_year) {
    $filterWhere .= " AND r.academic_year_id = ?";
    $filterParams[] = $filter_academic_year;
}
if ($filter_group) {
    $filterWhere .= " AND r.group_id = ?";
    $filterParams[] = $filter_group;
}
if ($filter_course) {
    if ($filter_course === '11th') {
        $filterWhere .= " AND r.course_id IN (1, 2)";
    } elseif ($filter_course === '12th') {
        $filterWhere .= " AND r.course_id IN (4, 5)";
    } elseif ($filter_course === 'Reneet') {
        $filterWhere .= " AND r.course_id = 6";
    } else {
        $filterWhere .= " AND r.course_id = ?";
        $filterParams[] = $filter_course;
    }
}
if ($filter_medium) {
    $filterWhere .= " AND r.medium_id = ?";
    $filterParams[] = $filter_medium;
}
if ($filter_school) {
    $filterWhere .= " AND r.school_id = ?";
    $filterParams[] = $filter_school;
}
if ($filter_gender) {
    $filterWhere .= " AND r.gender = ?";
    $filterParams[] = $filter_gender;
}
if ($filter_hostel !== '') {
    $filterWhere .= " AND r.hostel_required = ?";
    $filterParams[] = $filter_hostel;
}
if ($filter_transport !== '') {
    $filterWhere .= " AND r.transport_required = ?";
    $filterParams[] = $filter_transport;
}
if ($filter_campus) {
    $filterWhere .= " AND r.campus_id = ?";
    $filterParams[] = $filter_campus;
}
if ($filter_division) {
    if ($filter_division === 'none') {
        $filterWhere .= " AND (e.division_id IS NULL OR e.division_id = 0)";
    } else {
        $filterWhere .= " AND e.division_id = ?";
        $filterParams[] = $filter_division;
    }
}
if ($enrolled_only) {
    $filterWhere .= " AND e.enrollment_id IS NOT NULL";
}



// Determine ORDER BY clause
if ($sort_by === 'name') {
    $orderBy = "ORDER BY r.surname $sort_order, r.student_name $sort_order";
} else {
    $orderBy = "ORDER BY r.id $sort_order";
}

// Get counsellor_id filter from request (for Principal/Admin viewing specific counsellor's students)
$filter_counsellor_id = isset($_REQUEST['counsellor_id']) && $_REQUEST['counsellor_id'] !== '' ? intval($_REQUEST['counsellor_id']) : null;

// Pagination parameters
$page = isset($_REQUEST['page']) ? max(1, intval($_REQUEST['page'])) : 1;
$is_export = isset($_REQUEST['export']) && ($_REQUEST['export'] == 1 || $_REQUEST['export'] === 'true');
$perPage = isset($_REQUEST['per_page']) ? max(1, min(100, intval($_REQUEST['per_page']))) : 10;
if ($is_export) {
    $perPage = 999999; // Bypass pagination limit for exports
}
$offset = ($page - 1) * $perPage;

// Get students based on user role with server-side pagination
try {
    // If counsellor, show only their assigned students
    if (hasRole(ROLE_COUNSELLOR)) {
        $baseWhere = "WHERE r.counsellor_id = ?";
        $baseParams = [$user_id];
    } elseif ($filter_counsellor_id && (hasRole(ROLE_SUPER_ADMIN) || hasRole(ROLE_PRINCIPLE))) {
        // If Principal/Super Admin views with counsellor_id parameter, filter by that counsellor
        $baseWhere = "WHERE r.counsellor_id = ?";
        $baseParams = [$filter_counsellor_id];
    } else {
        $baseWhere = "WHERE 1=1";
        $baseParams = [];
    }

    // Count total records first
    $countQuery = "SELECT COUNT(r.id) as total
              FROM tbl_gm_std_registration r
              LEFT JOIN tbl_enrolled_students e ON r.id = e.registration_id
              $baseWhere $searchWhere $filterWhere";
    $countResult = $dbOps->customSelectOne($countQuery, array_merge($baseParams, $searchParams, $filterParams));
    $totalRecords = $countResult['total'] ?? 0;
    $totalPages = ceil($totalRecords / $perPage);

    // Get paginated data
    $query = "SELECT r.id, 
              CONCAT(r.surname, ' ', r.student_name, ' ', r.fathers_name) as full_name,
              r.surname, r.student_name, r.fathers_name,
              r.mob as phone, r.email, r.gr_no, r.aadhaar, r.dob, r.gender, b.board_name as board, m.medium_name as medium, 
              c.course_name as course, g.group_name as group_name, r.schoolname, 
              sch.school_name,
              r.status, r.created_at, r.admission_confirmed, r.admission_confirmed_date,
              r.hostel_required, r.transport_required, r.academic_year_id,
              d.division_name,
              ay.year_name as academic_year,
              cp.campus_name,
              u.name as counsellor_name
              FROM tbl_gm_std_registration r
              LEFT JOIN tbl_boards b ON r.board_id = b.id
              LEFT JOIN tbl_medium m ON r.medium_id = m.id
              LEFT JOIN tbl_courses c ON r.course_id = c.id
              LEFT JOIN tbl_group g ON r.group_id = g.id
              LEFT JOIN tbl_schools sch ON r.school_id = sch.id
              LEFT JOIN tbl_enrolled_students e ON r.id = e.registration_id
              LEFT JOIN tbl_division d ON e.division_id = d.id
              LEFT JOIN tbl_academic_years ay ON r.academic_year_id = ay.id
              LEFT JOIN tbl_campuses cp ON r.campus_id = cp.id
              LEFT JOIN tbl_users u ON r.counsellor_id = u.id
              $baseWhere $searchWhere $filterWhere
              $orderBy
              LIMIT $perPage OFFSET $offset";
    $students_raw = $dbOps->customSelect($query, array_merge($baseParams, $searchParams, $filterParams)) ?: [];

    $students = [];
    foreach ($students_raw as $student) {
        if (!$is_export) {
            $summary = calculateStudentFeeSummary($conn, $student['id']);
            $student['fees_allocated'] = $summary['total_allocated'] ?? 0;
            $student['fees_paid'] = $summary['total_paid'] ?? 0;
            $student['fees_pending'] = $summary['total_pending'] ?? 0;
        } else {
            $student['fees_allocated'] = 0;
            $student['fees_paid'] = 0;
            $student['fees_pending'] = 0;
        }
        $students[] = $student;
    }
} catch (PDOException $e) {
    if (!$is_api_call && function_exists('logDatabaseError')) {
        logDatabaseError($e, "Fetch Students for " . ($role_name ?? 'Unknown'));
    }
    $students = [];
    $totalRecords = 0;

    if ($is_api_call) {
        sendErrorResponse('Database error: ' . $e->getMessage(), 500);
    }
}

// Check if any filters are applied
$hasFilters = $search || $filter_board || $filter_academic_year || $filter_group || $filter_course || $filter_medium || $filter_school || $filter_counsellor_id;

// Fetch counsellor name if filtering by counsellor_id
$filter_counsellor_name = null;
if ($filter_counsellor_id) {
    try {
        $counsellor_info = $dbOps->selectOne('tbl_users', ['name'], ['id' => $filter_counsellor_id]);
        $filter_counsellor_name = $counsellor_info['name'] ?? 'Unknown';
    } catch (PDOException $e) {
        $filter_counsellor_name = 'Unknown';
    }
}

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
        'filters' => [
            'boards' => $boards ?? [],
            'academic_years' => $academic_years ?? [],
            'groups' => $groups ?? [],
            'courses' => $courses ?? [],
            'mediums' => $mediums ?? [],
            'schools' => $schools ?? [],
            'campuses' => $campuses ?? [],
            'divisions' => $divisions ?? [],
            'counsellors' => $counsellors ?? []
        ],
        'applied_filters' => [
            'search' => $search,
            'board' => $filter_board,
            'academic_year' => $filter_academic_year,
            'group' => $filter_group,
            'course' => $filter_course,
            'medium' => $filter_medium,
            'school' => $filter_school,
            'campus' => $filter_campus,
            'division' => $filter_division,
            'gender' => $filter_gender,
            'hostel_required' => $filter_hostel,
            'transport_required' => $filter_transport,
            'counsellor_id' => $filter_counsellor_id,
            'counsellor_name' => $filter_counsellor_name
        ]
    ]);
}

// Close connection immediately after fetching data
$conn = null;

