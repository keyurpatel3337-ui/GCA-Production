<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';

/**
 * Student Edit Controller
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

require_once OPERATION_FILE;
require_once $base_path . '/../common/helpers/error_logger.php';
// Check permissions (even for API calls)
if ($is_api_call) {
    if (!isset($_SESSION['user_id']) && !isset($_SESSION['student_id'])) {
        sendErrorResponse('Unauthorized access', 401);
    }
} else {
    // Check if user is logged in and has appropriate role
    if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_COUNSELLOR)) {
        set_flash_message('error', 'Unauthorized access');
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

// Check if student ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    if ($is_api_call) {
        sendErrorResponse('Student ID is required', 400);
    }
    set_flash_message('error', 'Student ID is required');
    header('Location: list.php');
    exit;
}

$student_id = intval($_GET['id']);
$page_title = "Edit Student Information";

// Fetch student details
try {
    $query = "SELECT r.*, e.division_id, e.roll_no, e.enrollment_no 
              FROM tbl_gm_std_registration r 
              LEFT JOIN tbl_enrolled_students e ON r.id = e.registration_id 
              WHERE r.id = ?";
    $student = $dbOps->customSelectOne($query, [$student_id]);

    if (!$student) {
        if ($is_api_call) {
            sendErrorResponse('Student not found', 404);
        }
        set_flash_message('error', 'Student not found');
        header('Location: list.php');
        exit;
    }

    // If counsellor, verify they can only edit their assigned students (skip for API)
    if (!$is_api_call && function_exists('hasRole') && hasRole(ROLE_COUNSELLOR) && $student['counsellor_id'] != $_SESSION['user_id']) {
        set_flash_message('error', 'You can only edit students assigned to you');
        header('Location: list.php');
        exit;
    }
} catch (PDOException $e) {
    if (!$is_api_call && function_exists('logDatabaseError')) {
        logDatabaseError($e, "Fetch Student for Edit");
    }
    if ($is_api_call) {
        sendErrorResponse('Database error: ' . $e->getMessage(), 500);
    }
    set_flash_message('error', 'Error loading student data');
    header('Location: list.php');
    exit;
}

// Fetch dropdown data - Using Operation.php
try {
    $schools = $dbOps->select('tbl_schools', ['*'], ['is_active' => 1], 'school_name ASC');
    $boards = $dbOps->select('tbl_boards', ['*'], ['is_active' => 1], 'id ASC');
    $mediums = $dbOps->select('tbl_medium', ['*'], ['is_active' => 1], 'id ASC');
    $groups = $dbOps->select('tbl_group', ['*'], ['is_active' => 1], 'id ASC');
    $courses = $dbOps->select('tbl_courses', ['id', 'course_name', 'standard'], ['is_active' => 1], 'course_name ASC');
    $campuses = $dbOps->select('tbl_campuses', ['*'], ['is_active' => 1], 'campus_name ASC');
    $divisions = $dbOps->select('tbl_division', ['id', 'division_name'], ['is_active' => 1], 'division_name ASC');

    // Handle false return values
    if ($schools === false)
        $schools = [];
    if ($boards === false)
        $boards = [];
    if ($mediums === false)
        $mediums = [];
    if ($groups === false)
        $groups = [];
    if ($courses === false)
        $courses = [];
    if ($campuses === false)
        $campuses = [];
    if ($divisions === false)
        $divisions = [];
} catch (PDOException $e) {
    if (!$is_api_call && function_exists('logDatabaseError')) {
        logDatabaseError($e, "Fetch Dropdown Data for Edit Student");
    }
    $schools = $boards = $mediums = $groups = $courses = $campuses = [];
}

// If API call, return JSON response
if ($is_api_call) {
    sendSuccessResponse([
        'student' => $student,
        'schools' => $schools,
        'boards' => $boards,
        'mediums' => $mediums,
        'groups' => $groups,
        'courses' => $courses,
        'campuses' => $campuses,
        'divisions' => $divisions
    ]);
}
