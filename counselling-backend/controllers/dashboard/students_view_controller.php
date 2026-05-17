<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';

/**
 * Dashboard Students View Controller
 * Supports both API mode (JSON response) and direct inclusion mode
 */

$base_path = dirname(dirname(__DIR__));

require_once DB_CONNECT_FILE;

// Check if this is an API call (via index.php router)
$is_api_call = defined('API_MODE') || (isset($_GET['route']) && !empty($_GET['route']));

if (!$is_api_call) {
    require_once OPERATION_FILE;
    require_once $base_path . '/../common/helpers/error_logger.php';
    // Check if user is Principle
    if (!hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN)) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$board = $_GET['board'] ?? '';
$counsellor_id = $_GET['counsellor_id'] ?? '';

// Build query
$query = "SELECT s.*, u.name as counsellor_name, b.board_name, m.medium_name, g.group_name, c.course_name
          FROM tbl_gm_std_registration s
          LEFT JOIN tbl_users u ON s.counsellor_id = u.id
          LEFT JOIN tbl_boards b ON s.board_id = b.id
          LEFT JOIN tbl_medium m ON s.medium_id = m.id
          LEFT JOIN tbl_group g ON s.group_id = g.id
          LEFT JOIN tbl_courses c ON s.course_id = c.id
          WHERE 1=1";

$params = [];

if (!empty($search)) {
    $query .= " AND (s.student_name LIKE ? OR s.surname LIKE ? OR s.mob LIKE ? OR s.aadhaar LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if (!empty($board)) {
    $query .= " AND s.board_id = ?";
    $params[] = $board;
}

if (!empty($counsellor_id)) {
    $query .= " AND s.counsellor_id = ?";
    $params[] = $counsellor_id;
}

$query .= " ORDER BY s.id ASC";

try {
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    if (!$is_api_call && function_exists('logDatabaseError')) {
        logDatabaseError($e, "Fetch Students");
    }
    $students = [];

    if ($is_api_call) {
        sendErrorResponse('Database error: ' . $e->getMessage(), 500);
    }
}

// Get statistics
try {
    $result = $dbOps->customSelectOne("SELECT COUNT(*) as total FROM tbl_gm_std_registration WHERE status = 1", []);
    $total_active = $result['total'];

    $result = $dbOps->customSelectOne("SELECT COUNT(*) as total FROM tbl_gm_std_registration WHERE counsellor_id IS NOT NULL", []);
    $assigned_students = $result['total'];

    $result = $dbOps->customSelectOne("SELECT COUNT(*) as total FROM tbl_gm_std_registration WHERE counsellor_id IS NULL", []);
    $unassigned_students = $result['total'];
} catch (PDOException $e) {
    $total_active = 0;
    $assigned_students = 0;
    $unassigned_students = 0;
}

// If API call, return JSON response
if ($is_api_call) {
    sendSuccessResponse([
        'students' => $students,
        'stats' => [
            'total_active' => $total_active,
            'assigned' => $assigned_students,
            'unassigned' => $unassigned_students
        ],
        'applied_filters' => [
            'search' => $search,
            'board' => $board,
            'counsellor_id' => $counsellor_id
        ]
    ]);
}

$page_title = "Students Management";
$page_breadcrumb = [
    ['title' => 'Home', 'url' => 'dashboard.php'],
    ['title' => 'Students', 'url' => '']
];

