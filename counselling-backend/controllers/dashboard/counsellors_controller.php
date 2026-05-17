<?php

/**
 * Dashboard Counsellors Controller
 * Supports both API mode (JSON response) and direct inclusion mode
 */

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';

require_once DB_CONNECT_FILE;

$base_path = dirname(dirname(__DIR__));

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

// Pagination parameters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = isset($_GET['per_page']) ? max(1, intval($_GET['per_page'])) : 10;
$offset = ($page - 1) * $per_page;

// Search parameter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get all counsellors with pagination
try {
    $counsellor_role_id = defined('ROLE_COUNSELLOR') ? ROLE_COUNSELLOR : 3;

    // Build WHERE clause for search
    $whereClause = "WHERE u.role_id = ?";
    $params = [$counsellor_role_id];

    if (!empty($search)) {
        $whereClause .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    // Get total count
    $countStmt = $conn->prepare("
        SELECT COUNT(DISTINCT u.id) as total
        FROM tbl_users u
        $whereClause
    ");
    $countStmt->execute($params);
    $total_records = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_records / $per_page);

    // Get paginated results
    $stmt = $conn->prepare("
        SELECT u.*, 
               COUNT(DISTINCT s.id) as total_students,
               COUNT(DISTINCT a.id) as total_appointments
        FROM tbl_users u
        LEFT JOIN tbl_gm_std_registration s ON u.id = s.counsellor_id
        LEFT JOIN tbl_appointments a ON u.id = a.counsellor_id
        $whereClause
        GROUP BY u.id
        ORDER BY u.name
        LIMIT $per_page OFFSET $offset
    ");
    $stmt->execute($params);
    $counsellors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $pagination = [
        'current_page' => $page,
        'per_page' => $per_page,
        'total_records' => $total_records,
        'total_pages' => $total_pages
    ];
} catch (PDOException $e) {
    if (!$is_api_call && function_exists('logDatabaseError')) {
        logDatabaseError($e, "Fetch Counsellors");
    }
    $counsellors = [];
    $pagination = [
        'current_page' => 1,
        'per_page' => $per_page,
        'total_records' => 0,
        'total_pages' => 1
    ];

    if ($is_api_call) {
        sendErrorResponse('Database error: ' . $e->getMessage(), 500);
    }
}

// If API call, return JSON response
if ($is_api_call) {
    sendSuccessResponse([
        'counsellors' => $counsellors,
        'pagination' => $pagination,
        'applied_filters' => [
            'search' => $search
        ]
    ]);
}

$page_title = "Counsellors Management";
$page_breadcrumb = [
    ['title' => 'Home', 'url' => 'dashboard.php'],
    ['title' => 'Counsellors', 'url' => '']
];
