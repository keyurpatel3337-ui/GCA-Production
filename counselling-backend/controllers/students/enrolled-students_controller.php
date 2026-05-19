<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';
require_once HELPERS_PATH . 'fee_helper.php';

set_time_limit(300); // 5 minutes max for large exports

/**
 * Enrolled Students Controller
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
require_once $base_path . '/common/pagination.php';
// Check permissions (even for API calls)
if ($is_api_call) {
    if (!isset($_SESSION['user_id']) && !isset($_SESSION['student_id'])) {
        sendErrorResponse('Unauthorized access', 401);
    }
}
else {
    // Check if user has appropriate role for non-API inclusion
    if (!hasRole(ROLE_ACCOUNTANT) && !hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_COUNSELLOR) && !hasRole(ROLE_PRINCIPLE)) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

$page_title = "Enrolled Students";
$page_breadcrumb = "Students -";

// Filters
$search = $_GET['search'] ?? '';
$filter_course = $_GET['course'] ?? '';
$filter_board = $_GET['board'] ?? '';
$filter_academic_year = $_GET['academic_year'] ?? '';
$filter_group = $_GET['group'] ?? '';
$filter_medium = $_GET['medium'] ?? '';
$filter_school = $_GET['school'] ?? '';
$filter_division = $_GET['division'] ?? '';
$filter_payment_status = $_GET['payment_status'] ?? '';
$filter_gender = $_GET['gender'] ?? '';
$filter_hostel = $_GET['hostel_required'] ?? '';
$filter_transport = $_GET['transport_required'] ?? '';
$filter_campus = $_GET['campus'] ?? '';
$sort_by = $_GET['sort'] ?? 'id';
$sort_order = $_GET['order'] ?? 'asc';

// Fetch dropdown data for filters - Using Operation.php
try {
    // Boards
    $boards = $dbOps->select('tbl_boards', ['id', 'board_name'], ['is_active' => 1], 'board_name ASC');
    if ($boards === false) {
        error_log("EnrolledStudentsController: Failed to fetch boards");
        $boards = [];
    }

    // Academic Years
    $academic_years = $dbOps->select('tbl_academic_years', ['id', 'year_name'], ['is_active' => 1], 'year_name DESC');
    if ($academic_years === false) {
        error_log("EnrolledStudentsController: Failed to fetch academic years");
        $academic_years = [];
    }

    // Groups
    $groups = $dbOps->select('tbl_group', ['id', 'group_name'], ['is_active' => 1], 'group_name ASC');
    if ($groups === false)
        $groups = [];

    // Courses
    $courses = $dbOps->select('tbl_courses', ['id', 'course_name'], ['is_active' => 1], 'course_name ASC');
    if ($courses === false) {
        error_log("EnrolledStudentsController: Failed to fetch courses");
        $courses = [];
    }

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
    $divisions = $dbOps->select('tbl_division', ['id', 'division_name'], ['is_active' => 1], 'display_order ASC');
    if ($divisions === false) {
        error_log("EnrolledStudentsController: Failed to fetch divisions");
        $divisions = [];
    }
}
catch (PDOException $e) {
    if (!$is_api_call && function_exists('logDatabaseError')) {
        logDatabaseError($e, "Fetch Filter Dropdowns");
    }
    $boards = $academic_years = $groups = $courses = $mediums = $schools = $divisions = [];
}

// Pagination parameters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$is_export = isset($_GET['export']) && ($_GET['export'] == 1 || $_GET['export'] === 'true');
$perPage = isset($_GET['per_page']) ? max(1, min(100, intval($_GET['per_page']))) : 10;
if ($is_export) {
    $perPage = 999999; // Bypass pagination limit for exports
}
$offset = ($page - 1) * $perPage;

// Build query for enrolled students
try {
    $where_conditions = ["e.is_active = 1"];
    $params = [];

    if ($search) {
        if (is_numeric($search)) {
            $where_conditions[] = "(r.id = ? OR r.mob LIKE ? OR r.aadhaar LIKE ? OR e.enrollment_no LIKE ?)";
            $params = array_merge($params, [$search, "%" . $search . "%", "%" . $search . "%", "%" . $search . "%"]);
        }
        else {
            $fuzzySearch = "%" . str_replace(' ', '%', $search) . "%";
            $where_conditions[] = "(r.student_name LIKE ? OR r.surname LIKE ? OR r.fathers_name LIKE ? OR r.mob LIKE ? OR r.aadhaar LIKE ? OR e.enrollment_no LIKE ? OR CONCAT_WS(' ', r.surname, r.student_name, r.fathers_name) LIKE ? OR CONCAT_WS(' ', r.student_name, r.surname) LIKE ?)";
            $params = array_merge($params, [$fuzzySearch, $fuzzySearch, $fuzzySearch, $fuzzySearch, $fuzzySearch, $fuzzySearch, $fuzzySearch, $fuzzySearch]);
        }
    }

    if ($filter_course) {
        if ($filter_course === '11th') {
            $where_conditions[] = "r.course_id IN (1, 2)";
        } elseif ($filter_course === '12th') {
            $where_conditions[] = "r.course_id IN (4, 5)";
        } elseif ($filter_course === 'Reneet') {
            $where_conditions[] = "r.course_id = 6";
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

    // Real-time Financial Logic for Filtering (Aligned with Dashboard/Reports)
    $h_set = $dbOps->customSelect("SELECT security_deposit FROM tbl_hostel_fee_settings WHERE is_active = 1 LIMIT 1")[0] ?? ['security_deposit' => 0];
    $t_set = $dbOps->customSelect("SELECT transport_fee, gst_rate FROM tbl_transport_fee_settings WHERE is_active = 1 LIMIT 1")[0] ?? ['transport_fee' => 0, 'gst_rate' => 0];
    $sec_dep = floatval($h_set['security_deposit']);
    $trans_fee = floatval($t_set['transport_fee']) * (1 + floatval($t_set['gst_rate']) / 100);

    $academic_base = "COALESCE(fc.total_fees, 0)";
    $hostel_base = "(IF(r.hostel_required = 'Yes', $sec_dep, 0) + COALESCE(fc.hostel_fee, 0))";
    $transport_base = "IF(r.transport_required = 'Yes', $trans_fee, 0)";
    $total_base = "($academic_base + $hostel_base + $transport_base)";
    $scholarship = "(COALESCE(r.scholarship_amount, 0) + COALESCE(r.additional_scholarship_amount, 0))";
    $discount = "COALESCE(e.post_admission_discount_amount, 0)";
    $waiver = "($scholarship + $discount)";
    $universal_comps = "'hostel_security', 'transport_fee', 'admission_fee', 'security_deposit', 'token_fee', 'tuition_fee_part1', 'registration_fee', 'school_fee', 'trust_facilities_fee', 'tuition_fee_part2'";

    $academic_paid = "(SELECT COALESCE(SUM(p_sub.amount), 0) FROM tbl_payments p_sub WHERE p_sub.student_id = r.id AND p_sub.status = 'paid' AND p_sub.fee_component NOT IN ('hostel_fee', 'hostel_security', 'transport_fee') AND (p_sub.term_id = e.current_term_id OR p_sub.fee_component IN ($universal_comps)))";
    $hostel_paid = "(SELECT COALESCE(SUM(p_sub.amount), 0) FROM tbl_payments p_sub WHERE p_sub.student_id = r.id AND p_sub.status = 'paid' AND p_sub.fee_component IN ('hostel_fee', 'hostel_security'))";
    $transport_paid = "(SELECT COALESCE(SUM(p_sub.amount), 0) FROM tbl_payments p_sub WHERE p_sub.student_id = r.id AND p_sub.status = 'paid' AND p_sub.fee_component = 'transport_fee')";

    $pending_academic = "GREATEST(0, $academic_base - $academic_paid - $waiver)";
    $hostel_base_dyn = "GREATEST($hostel_base, $hostel_paid)";
    $pending_hostel = "GREATEST(0, $hostel_base_dyn - $hostel_paid)";
    $pending_transport = "GREATEST(0, $transport_base - $transport_paid)";
    $total_pending = "($pending_academic + $pending_hostel + $pending_transport)";
    $total_paid = "($academic_paid + $hostel_paid + $transport_paid)";

    if ($filter_payment_status) {
        if ($filter_payment_status === 'paid') {
            $where_conditions[] = "($total_pending) <= 0";
        }
        elseif ($filter_payment_status === 'pending') {
            $where_conditions[] = "($total_paid) = 0 AND ($total_pending) > 0";
        }
        elseif ($filter_payment_status === 'partial') {
            $where_conditions[] = "($total_paid) > 0 AND ($total_pending) > 0";
        }
    }

    $where_sql = implode(' AND ', $where_conditions);

    // Count total records first
    $countQuery = "SELECT COUNT(e.enrollment_id) as total
              FROM tbl_enrolled_students e
              LEFT JOIN tbl_gm_std_registration r ON e.registration_id = r.id
              LEFT JOIN tbl_fee_config fc ON r.course_id = fc.course_id AND r.medium_id = fc.medium_id AND r.group_id = fc.group_id AND r.school_id = fc.school_id AND fc.is_active = 1
              WHERE $where_sql";
    $countResult = $dbOps->customSelectOne($countQuery, $params);
    $totalRecords = $countResult['total'] ?? 0;
    $totalPages = ceil($totalRecords / $perPage);

    // Build Sort Clause
    $sort_col = "r.id";
    if ($sort_by === 'name') {
        $sort_col = "r.surname";
    }

    // Get paginated data with calculated fees (matching payment-history logic)
    $query = "SELECT DISTINCT e.enrollment_id, r.id as student_reg_id, e.*, 
              CONCAT(r.surname, ' ', r.student_name, ' ', IFNULL(r.fathers_name, '')) as full_name,
              r.student_name, r.surname, r.fathers_name, r.mob, r.gender, r.email, r.gr_no, r.aadhaar,
              r.hostel_required, r.transport_required, r.scholarship_amount, r.additional_scholarship_amount,
              r.course_id, r.medium_id, r.group_id,
              c.course_name,
              g.group_name,
              sch.school_name,
              d.division_name,
              b.board_name,
              m.medium_name,
              cp.campus_name,
              r.admission_confirmed_date,
              r.created_at as registration_date,
              u.name as counsellor_name,
              COALESCE((SELECT SUM(p.amount) 
                        FROM tbl_payments p 
                        WHERE p.student_id = e.registration_id 
                        AND p.status = 'paid'), 0) as fees_paid
              FROM tbl_enrolled_students e
              LEFT JOIN tbl_gm_std_registration r ON e.registration_id = r.id
              LEFT JOIN tbl_courses c ON r.course_id = c.id
              LEFT JOIN tbl_group g ON r.group_id = g.id
              LEFT JOIN tbl_division d ON e.division_id = d.id
              LEFT JOIN tbl_boards b ON r.board_id = b.id
              LEFT JOIN tbl_medium m ON r.medium_id = m.id
              LEFT JOIN tbl_schools sch ON r.school_id = sch.id
              LEFT JOIN tbl_campuses cp ON r.campus_id = cp.id
              LEFT JOIN tbl_users u ON r.counsellor_id = u.id
              LEFT JOIN tbl_fee_config fc ON r.course_id = fc.course_id AND r.medium_id = fc.medium_id AND r.group_id = fc.group_id AND r.school_id = fc.school_id AND fc.is_active = 1
              WHERE $where_sql
              ORDER BY $sort_col $sort_order
              LIMIT $perPage OFFSET $offset";

    $students_raw = $dbOps->customSelect($query, $params) ?: [];

    $students = [];
    foreach ($students_raw as $student) {

        // Use unified fee helper for accurate and consistent calculation
        $summary = calculateStudentFeeSummary($conn, $student['registration_id']);

        $student['fees_allocated'] = $summary['total_allocated'] ?? 0;
        $student['fees_paid'] = $summary['total_paid'] ?? 0;
        $student['fees_pending'] = $summary['total_pending'] ?? 0;

        $students[] = $student;
    }

}
catch (PDOException $e) {
    if (!$is_api_call && function_exists('logDatabaseError')) {
        logDatabaseError($e, "Fetch Enrolled Students");
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
            'payment_status' => $filter_payment_status,
            'gender' => $filter_gender,
            'hostel_required' => $filter_hostel,
            'transport_required' => $filter_transport
        ]
    ]);
}
