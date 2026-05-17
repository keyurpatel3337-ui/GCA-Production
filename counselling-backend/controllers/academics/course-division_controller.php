<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';

/**
 * Course Division Controller
 * Supports both API mode (JSON response) and direct inclusion mode
 */

$base_path = dirname(dirname(__DIR__));

require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
$dbOps = new DatabaseOperations();

// Check if this is an API call (via index.php router)
$is_api_call = defined('API_MODE') || (isset($_GET['route']) && !empty($_GET['route']));
$route = $_GET['route'] ?? '';

if ($is_api_call) {
    header('Content-Type: application/json');
    if (!hasRole(ROLE_SUPER_ADMIN)) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
        exit;
    }
} else {
    if (!hasRole(ROLE_SUPER_ADMIN)) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

$page_title = "Course Division Mapping";
$page_breadcrumb = "Course Divisions";

// Handle API POST actions
if ($is_api_call && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($route === 'settings/course-division-save' || $route === 'settings/course-division-update') {
            $id = intval($_POST['id'] ?? 0);
            $course_id = intval($_POST['course_id'] ?? 0);
            $group_id = intval($_POST['group_id'] ?? 0);
            $division_id = intval($_POST['division_id'] ?? 0);
            $start_roll_no = intval($_POST['start_roll_no'] ?? 1);
            $max_capacity = !empty($_POST['max_capacity']) ? intval($_POST['max_capacity']) : null;
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $created_by = $_SESSION['user_id'] ?? 0;

            if ($course_id <= 0 || $group_id <= 0 || $division_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Course, Group and Division are required']);
                exit;
            }

            // Check for duplicate mapping
            $existing = $dbOps->customSelect(
                "SELECT id FROM tbl_course_division WHERE course_id = ? AND group_id = ? AND division_id = ? AND id != ?",
                [$course_id, $group_id, $division_id, $id],
                true
            );
            if ($existing) {
                echo json_encode(['success' => false, 'message' => 'This combination already exists']);
                exit;
            }

            if ($id > 0) {
                $stmt = $conn->prepare("UPDATE tbl_course_division SET course_id = ?, group_id = ?, division_id = ?, start_roll_no = ?, max_capacity = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$course_id, $group_id, $division_id, $start_roll_no, $max_capacity, $is_active, $id]);
                $message = 'Mapping updated successfully';
            } else {
                $stmt = $conn->prepare("INSERT INTO tbl_course_division (course_id, group_id, division_id, start_roll_no, max_capacity, is_active, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$course_id, $group_id, $division_id, $start_roll_no, $max_capacity, $is_active, $created_by]);
                $message = 'Mapping added successfully';
            }
            echo json_encode(['success' => true, 'message' => $message]);
            exit;
        } elseif ($route === 'settings/course-division-delete') {
            $id = intval($_POST['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid ID']);
                exit;
            }

            $stmt = $conn->prepare("DELETE FROM tbl_course_division WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Mapping deleted successfully']);
            exit;
        } elseif ($route === 'settings/course-divisions-delete-multiple') { // Note: index.php might map this differently?
            // Assuming index.php route mapping: 'course-divisions-delete-multiple' => ...
            $ids = $_POST['ids'] ?? [];
            if (empty($ids) || !is_array($ids)) {
                echo json_encode(['success' => false, 'message' => 'No items selected']);
                exit;
            }

            $ids = array_map('intval', $ids);
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';

            $stmt = $conn->prepare("DELETE FROM tbl_course_division WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            echo json_encode(['success' => true, 'message' => count($ids) . ' mappings deleted successfully']);
            exit;
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

// Handle GET actions (List or Single)
if ($is_api_call && $route === 'settings/course-division-get') {
    $id = intval($_GET['id'] ?? 0);
    $mapping = $dbOps->selectOne('tbl_course_division', ['*'], ['id' => $id]);
    if ($mapping) {
        echo json_encode(['success' => true, 'data' => ['mapping' => $mapping]]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Mapping not found']);
    }
    exit;
}

// Fetch all mappings (for listing or direct inclusion)
try {
    $mappings = $dbOps->customSelect(
        "SELECT cd.*, 
        c.course_name, 
        g.group_name, 
        d.division_name,
        u.name as created_by_name 
        FROM tbl_course_division cd
        LEFT JOIN tbl_courses c ON cd.course_id = c.id
        LEFT JOIN tbl_group g ON cd.group_id = g.id
        LEFT JOIN tbl_division d ON cd.division_id = d.id
        LEFT JOIN tbl_users u ON cd.created_by = u.id
        ORDER BY c.course_name ASC, g.group_name desc, d.display_order DESC"
    );

    // Also fetch dropdown data
    $courses = $dbOps->select('tbl_courses', ['id', 'course_name'], ['is_active' => 1], 'course_name ASC');
    $groups = $dbOps->select('tbl_group', ['id', 'group_name'], ['is_active' => 1], 'group_name ASC');
    $divisions = $dbOps->select('tbl_division', ['id', 'division_name'], ['is_active' => 1], 'display_order ASC');
} catch (PDOException $e) {
    $mappings = [];
    $courses = [];
    $groups = [];
    $divisions = [];
    if ($is_api_call) {
        sendErrorResponse('Database error: ' . $e->getMessage(), 500);
    }
}

// If API call, return JSON response
if ($is_api_call) {
    sendSuccessResponse([
        'mappings' => $mappings,
        'courses' => $courses,
        'groups' => $groups,
        'divisions' => $divisions
    ]);
}

