<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';

/**
 * Student Details Controller
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

// require_once OPERATION_FILE; // Removed to avoid duplicate class declaration
require_once $base_path . '/../common/helpers/error_logger.php';
// Check permissions (even for API calls)
if ($is_api_call) {
    if (!isset($_SESSION['user_id']) && !isset($_SESSION['student_id'])) {
        sendErrorResponse('Unauthorized access', 401);
    }
} else {
    // Check if user has appropriate role for non-API inclusion
    if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_COUNSELLOR)) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

// Check if student ID is provided
if (!isset($_GET['id'])) {
    if ($is_api_call) {
        sendErrorResponse('Student ID is required', 400);
    }
    set_flash_message('error', "Student ID is required");
    header('Location: list.php');
    exit;
}

$student_id = $_GET['id'];
$user_id = $_SESSION['user_id'] ?? null;
$page_title = "Student Details";
$page_breadcrumb = "Student Details";

// Get student details
try {
    $stmt = $conn->prepare("SELECT s.*, 
                           u.name as counsellor_name, 
                           u.email as counsellor_email,
                           u.phone as counsellor_phone,
                           b.board_name,
                           m.medium_name,
                           g.group_name,
                           c.course_name,
                           ay.year_name as academic_year
                           FROM tbl_gm_std_registration s
                           LEFT JOIN tbl_users u ON s.counsellor_id = u.id
                           LEFT JOIN tbl_boards b ON s.board_id = b.id
                           LEFT JOIN tbl_medium m ON s.medium_id = m.id
                           LEFT JOIN tbl_group g ON s.group_id = g.id
                           LEFT JOIN tbl_courses c ON s.course_id = c.id
                           LEFT JOIN tbl_academic_years ay ON s.academic_year_id = ay.id
                           WHERE s.id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();

    if (!$student) {
        if ($is_api_call) {
            sendErrorResponse('Student not found', 404);
        }
        set_flash_message('error', "Student not found");
        header('Location: list.php');
        exit;
    }

    // If counsellor, verify they can only view their assigned students (skip for API)
    if (!$is_api_call && function_exists('hasRole') && hasRole(ROLE_COUNSELLOR) && $student['counsellor_id'] != $user_id) {
        set_flash_message('error', "You can only view students assigned to you");
        header('Location: list.php');
        exit;
    }
} catch (PDOException $e) {
    if (!$is_api_call && function_exists('logDatabaseError')) {
        logDatabaseError($e, "Fetch Student Details");
    }
    if ($is_api_call) {
        sendErrorResponse('Database error: ' . $e->getMessage(), 500);
    }
    set_flash_message('error', "Error fetching student details");
    header('Location: list.php');
    exit;
}

// Get enrollment information if student is enrolled
$enrollment = null;
try {
    $stmt_enroll = $conn->prepare("SELECT e.*, 
                                   d.division_name,
                                   s.school_name
                                   FROM tbl_enrolled_students e
                                   LEFT JOIN tbl_gm_std_registration r ON e.registration_id = r.id
                                   LEFT JOIN tbl_division d ON e.division_id = d.id
                                   LEFT JOIN tbl_schools s ON r.school_id = s.id
                                   WHERE e.registration_id = ?
                                   ORDER BY e.enrollment_date DESC
                                   LIMIT 1");
    $stmt_enroll->execute([$student_id]);
    $enrollment = $stmt_enroll->fetch();
} catch (PDOException $e) {
    if (!$is_api_call && function_exists('logDatabaseError')) {
        logDatabaseError($e, "Fetch Student Enrollment");
    }
    $enrollment = null;
}

// Get student's test results if any
$test_results = [];
try {
    $stmt = $conn->prepare("SELECT tr.*, 
                           ps.paper_set_name,
                           ak.test_name,
                           omr.roll_number
                           FROM tbl_test_results tr
                           LEFT JOIN tbl_paper_sets ps ON tr.paper_set_id = ps.id
                           LEFT JOIN tbl_answer_keys ak ON tr.answer_key_id = ak.id
                           LEFT JOIN tbl_omr_sheets omr ON tr.omr_sheet_id = omr.id
                           WHERE tr.student_id = ?
                           ORDER BY tr.created_at DESC");
    $stmt->execute([$student_id]);
    $test_results = $stmt->fetchAll();
} catch (PDOException $e) {
    if (!$is_api_call && function_exists('logDatabaseError')) {
        logDatabaseError($e, "Fetch Student Test Results");
    }
    $test_results = [];
}

// Fetch all counsellors for assignment modal (only for non-API and admin/principle)
$counsellors = [];
if (!$is_api_call && function_exists('hasRole') && (hasRole(ROLE_SUPER_ADMIN) || hasRole(ROLE_PRINCIPLE))) {
    try {
        $counsellors = $dbOps->select(
            'tbl_users',
            ['id', 'name', 'email'],
            ['role_id' => ROLE_COUNSELLOR, 'status' => 'active'],
            'name'
        );
        if ($counsellors === false)
            $counsellors = [];
    } catch (PDOException $e) {
        if (function_exists('logDatabaseError')) {
            logDatabaseError($e, "Fetch Counsellors for Assignment Modal");
        }
    }
}

// If API call, return JSON response
if ($is_api_call) {
    sendSuccessResponse([
        'student' => $student,
        'enrollment' => $enrollment,
        'test_results' => $test_results
    ]);
}


