<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';

/**
 * Student Registration (Add) Controller
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
// Check permissions (even for API calls)
if ($is_api_call) {
    if (!isset($_SESSION['user_id']) && !isset($_SESSION['student_id'])) {
        sendErrorResponse('Unauthorized access', 401);
    }
} else {
    // Check if user is logged in and has appropriate role
    if (!isset($_SESSION['user_id']) || !hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_COUNSELLOR])) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

$page_title = "Add New Student Registration";
$page_breadcrumb = "Student Registration";

// Fetch data for dropdowns - Using Operation.php
try {
    $schools = $dbOps->select('tbl_schools', ['*'], ['is_active' => 1], 'school_name ASC');
    $boards = $dbOps->select('tbl_boards', ['*'], ['is_active' => 1], 'id ASC');
    $mediums = $dbOps->select('tbl_medium', ['*'], ['is_active' => 1], 'id ASC');
    $groups = $dbOps->select('tbl_group', ['*'], ['is_active' => 1], 'id ASC');
    $courses = $dbOps->select('tbl_courses', ['id', 'course_name', 'standard'], ['is_active' => 1], 'course_name ASC');
    $campuses = $dbOps->select('tbl_campuses', ['*'], ['is_active' => 1], 'campus_name ASC');

    // Handle false return values
    if ($schools === false) $schools = [];
    if ($boards === false) $boards = [];
    if ($mediums === false) $mediums = [];
    if ($groups === false) $groups = [];
    if ($courses === false) $courses = [];
    if ($campuses === false) $campuses = [];
} catch (PDOException $e) {
    if (!$is_api_call && function_exists('logDatabaseError')) {
        logDatabaseError($e, "Fetch Data for Student Registration");
    }
    $schools = $boards = $mediums = $groups = $courses = $campuses = [];

    if ($is_api_call) {
        sendErrorResponse('Database error: ' . $e->getMessage(), 500);
    }
}

// If API call, return JSON response with dropdown data
if ($is_api_call) {
    sendSuccessResponse([
        'schools' => $schools,
        'boards' => $boards,
        'mediums' => $mediums,
        'groups' => $groups,
        'courses' => $courses,
        'campuses' => $campuses
    ]);
}
