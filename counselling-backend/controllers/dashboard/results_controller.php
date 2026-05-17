<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';

/**
 * Dashboard Results Controller
 */

$base_path = dirname(dirname(__DIR__));

require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once $base_path . '/../common/helpers/error_logger.php';
// Check if user is Principle
if (!hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// Get filter parameters
$paper_set_id = $_GET['paper_set_id'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$query = "SELECT r.*, 
                 s.student_name, s.surname, s.mob,
                 ps.paper_set_name, ps.paper_code
          FROM tbl_test_results r
          LEFT JOIN tbl_gm_std_registration s ON r.student_id = s.id
          LEFT JOIN tbl_omr_sheets omr ON r.omr_sheet_id = omr.id
          LEFT JOIN tbl_paper_sets ps ON omr.paper_set_id = ps.id
          WHERE 1=1";

$params = [];

if (!empty($paper_set_id)) {
    $query .= " AND omr.paper_set_id = ?";
    $params[] = $paper_set_id;
}

if (!empty($search)) {
    $query .= " AND (s.student_name LIKE ? OR s.surname LIKE ? OR s.mob LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

$query .= " ORDER BY r.id ASC";

try {
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logDatabaseError($e, "Fetch Results");
    $results = [];
}

// Get paper sets for filter
try {
    $paper_sets = $dbOps->customSelect("SELECT id, paper_set_name, paper_code FROM tbl_paper_sets ORDER BY paper_set_name", []);
} catch (PDOException $e) {
    $paper_sets = [];
}

// Get statistics
try {
    $result = $dbOps->customSelectOne("SELECT COUNT(*) as total FROM tbl_test_results", []);
    $total_results = $result['total'];

    $result = $dbOps->customSelectOne("SELECT AVG(percentage) as avg_percentage FROM tbl_test_results", []);
    $avg_percentage = $result['avg_percentage'] ?? 0;
} catch (PDOException $e) {
    $total_results = 0;
    $avg_percentage = 0;
}

$page_title = "Test Results";
$page_breadcrumb = [
    ['title' => 'Home', 'url' => 'dashboard.php'],
    ['title' => 'Results', 'url' => '']
];

