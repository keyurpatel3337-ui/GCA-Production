<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';
require_once HELPERS_PATH . 'fee_helper.php';

/**
 * Registered Students Controller
 * Supports both API mode (JSON response) and direct inclusion mode
 */

$base_path = dirname(dirname(__DIR__));

require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;

// Initialize Database Operations
$dbOps = new DatabaseOperations();
/** @var PDO $conn */
global $conn;

// Check if this is an API call (via index.php router)
$is_api_call = defined('API_MODE') || (isset($_GET['route']) && !empty($_GET['route']));

// Include dependencies

// require_once OPERATION_FILE; // Removed to avoid duplicate class declaration
require_once $base_path . '/../common/helpers/error_logger.php';
// Check permissions (even for API calls)
if ($is_api_call) {
    if (!isset($_SESSION['user_id']) && !isset($_SESSION['student_id'])) {
        sendErrorResponse('Unauthorized access', 401);
    }
} else {
    // Check if user is Accountant or Super Admin for non-API inclusion
    if (!hasRole(ROLE_ACCOUNTANT) && !hasRole(ROLE_SUPER_ADMIN)) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

$page_title = "Registered Students (Pending Token Fee)";
$page_breadcrumb = "Students -";

// Filters
$search = $_GET['search'] ?? '';
$filter_course = $_GET['course'] ?? '';
$filter_board = $_GET['board'] ?? '';
$filter_academic_year = $_GET['academic_year'] ?? '';
$filter_group = $_GET['group'] ?? '';
$filter_medium = $_GET['medium'] ?? '';
$filter_school = $_GET['school'] ?? '';
$filter_gender = $_GET['gender'] ?? '';
$filter_hostel = $_GET['hostel_required'] ?? '';
$filter_transport = $_GET['transport_required'] ?? '';
$filter_campus = $_GET['campus'] ?? '';
$filter_division = $_GET['division'] ?? '';
$sort_by = $_GET['sort'] ?? 'id';
$sort_order = $_GET['order'] ?? 'asc';

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

} catch (PDOException $e) {
    if (!$is_api_call && function_exists('logDatabaseError')) {
        logDatabaseError($e, "Fetch Filter Dropdowns");
    }
    $boards = $academic_years = $groups = $courses = $mediums = $schools = [];
}

// Build query for registered students
try {
    $where_conditions = [
        "r.admission_confirmed = 1",
        "r.token_fees_paid = 0",
        "r.status = 1"
    ];
    $params = [];

    if ($search) {
        if (is_numeric($search)) {
            $where_conditions[] = "(r.id = ? OR r.mob LIKE ? OR r.aadhaar LIKE ? OR r.admission_letter_number LIKE ?)";
            $params = array_merge($params, [$search, "%" . $search . "%", "%" . $search . "%", "%" . $search . "%"]);
        } else {
            $fuzzySearch = "%" . str_replace(' ', '%', $search) . "%";
            $where_conditions[] = "(r.student_name LIKE ? OR r.surname LIKE ? OR r.fathers_name LIKE ? OR r.mob LIKE ? OR r.aadhaar LIKE ? OR r.admission_letter_number LIKE ? OR CONCAT_WS(' ', r.surname, r.student_name, r.fathers_name) LIKE ? OR CONCAT_WS(' ', r.student_name, r.surname) LIKE ?)";
            $params = array_merge($params, [$fuzzySearch, $fuzzySearch, $fuzzySearch, $fuzzySearch, $fuzzySearch, $fuzzySearch, $fuzzySearch, $fuzzySearch]);
        }
    }

    if ($filter_course) {
        if ($filter_course === '11th') {
            $where_conditions[] = "r.course_id = 1";
        } elseif ($filter_course === '12th') {
            $where_conditions[] = "r.course_id = 2";
        } elseif ($filter_course === 'Reneet') {
            $where_conditions[] = "r.course_id = 3";
        } else {
            $where_conditions[] = "r.course_id = ?";
            $params[] = $filter_course;
        }
    }

    if ($filter_board) {
        $where_conditions[] = "r.board_id = ?";
        $params[] = $filter_board;
    }

    if ($filter_academic_year) {
        $where_conditions[] = "r.academic_year_id = ?";
        $params[] = $filter_academic_year;
    }

    if ($filter_group) {
        $where_conditions[] = "r.group_id = ?";
        $params[] = $filter_group;
    }

    if ($filter_medium) {
        $where_conditions[] = "r.medium_id = ?";
        $params[] = $filter_medium;
    }

    if ($filter_school) {
        $where_conditions[] = "r.school_id = ?";
        $params[] = $filter_school;
    }

    if ($filter_gender) {
        $where_conditions[] = "r.gender = ?";
        $params[] = $filter_gender;
    }

    if ($filter_hostel !== '') {
        $where_conditions[] = "r.hostel_required = ?";
        $params[] = $filter_hostel;
    }

    if ($filter_hostel !== '') {
        $where_conditions[] = "r.hostel_required = ?";
        $params[] = $filter_hostel;
    }

    if ($filter_transport !== '') {
        $where_conditions[] = "r.transport_required = ?";
        $params[] = $filter_transport;
    }

    if ($filter_campus) {
        $where_conditions[] = "r.campus_id = ?";
        $params[] = $filter_campus;
    }

    if ($filter_division) {
        if ($filter_division === 'none') {
            $where_conditions[] = "(e.division_id IS NULL OR e.division_id = 0)";
        } else {
            $where_conditions[] = "e.division_id = ?";
            $params[] = $filter_division;
        }
    }

    $where_sql = implode(' AND ', $where_conditions);

              // Build Sort Clause
              $sort_col = "r.id";
              if ($sort_by === 'name') {
                  $sort_col = "r.surname";
              }

              $query = "SELECT r.*, 
              CONCAT(r.surname, ' ', r.student_name, ' ', IFNULL(r.fathers_name, '')) as full_name,
              c.course_name,
              g.group_name,
              b.board_name,
              m.medium_name,
              sch.school_name,
              cp.campus_name,
              u.name as counsellor_name
              FROM tbl_gm_std_registration r
              LEFT JOIN tbl_courses c ON r.course_id = c.id
              LEFT JOIN tbl_group g ON r.group_id = g.id
              LEFT JOIN tbl_boards b ON r.board_id = b.id
              LEFT JOIN tbl_medium m ON r.medium_id = m.id
              LEFT JOIN tbl_schools sch ON r.school_id = sch.id
              LEFT JOIN tbl_campuses cp ON r.campus_id = cp.id
              LEFT JOIN tbl_enrolled_students e ON r.id = e.registration_id
              LEFT JOIN tbl_users u ON r.counsellor_id = u.id
              WHERE $where_sql
              ORDER BY $sort_col $sort_order";

    // Pagination
    $page = isset($_REQUEST['page']) ? max(1, (int) $_REQUEST['page']) : 1;
    $is_export = isset($_REQUEST['export']) && ($_REQUEST['export'] == 1 || $_REQUEST['export'] === 'true');
    $perPage = isset($_REQUEST['per_page']) ? max(1, (int) $_REQUEST['per_page']) : 15;
    if ($is_export) {
        $perPage = 999999; // Bypass pagination limit for exports
    }
    $offset = ($page - 1) * $perPage;

    // 1. Get Total Count
    $countQuery = "SELECT COUNT(*) as total FROM tbl_gm_std_registration r 
                   LEFT JOIN tbl_enrolled_students e ON r.id = e.registration_id
                   WHERE $where_sql";
    $countStmt = $conn->prepare($countQuery);
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalRecords / $perPage);

    // 2. Add LIMIT and OFFSET to main query
    $query .= " LIMIT " . (int) $perPage . " OFFSET " . (int) $offset;

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $students_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        logDatabaseError($e, "Fetch Registered Students");
    }
    $students = [];
    $totalRecords = 0;

    if ($is_api_call) {
        sendErrorResponse('Database error: ' . $e->getMessage(), 500);
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
        'total' => $totalRecords,
        'filters' => [
            'boards' => $boards ?? [],
            'academic_years' => $academic_years ?? [],
            'groups' => $groups ?? [],
            'courses' => $courses ?? [],
            'mediums' => $mediums ?? [],
            'schools' => $schools ?? [],
            'campuses' => $campuses ?? [],
            'divisions' => $divisions ?? []
        ],
        'applied_filters' => [
            'search' => $search,
            'course' => $filter_course,
            'board' => $filter_board,
            'academic_year' => $filter_academic_year,
            'group' => $filter_group,
            'medium' => $filter_medium,
            'school' => $filter_school,
            'campus' => $filter_campus,
            'division' => $filter_division,
            'gender' => $filter_gender,
            'hostel_required' => $filter_hostel,
            'transport_required' => $filter_transport
        ]
    ]);
}


