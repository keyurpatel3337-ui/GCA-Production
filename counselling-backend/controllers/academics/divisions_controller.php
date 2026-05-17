<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';

/**
 * Divisions Controller
 * Supports both API mode (JSON response) and direct inclusion mode
 */

$base_path = dirname(dirname(__DIR__));

require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
// Initialize database operations
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

$page_title = "Division Management";
$page_breadcrumb = "Divisions";

// Handle API POST actions
if ($is_api_call && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($route === 'settings/division-save' || $route === 'settings/division-update') {
            $id = intval($_POST['id'] ?? 0);
            $division_name = trim($_POST['division_name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $display_order = intval($_POST['display_order'] ?? 0);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $created_by = $_SESSION['user_id'] ?? 0;

            if (empty($division_name)) {
                echo json_encode(['success' => false, 'message' => 'Division name is required']);
                exit;
            }

            // Check for duplicate division name
            $existing = $dbOps->selectOne('tbl_division', ['id'], ['division_name' => $division_name]);
            if ($existing && $existing['id'] != $id) {
                echo json_encode(['success' => false, 'message' => 'This division name already exists']);
                exit;
            }

            if ($id > 0) {
                $stmt = $conn->prepare("UPDATE tbl_division SET division_name = ?, description = ?, display_order = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$division_name, $description, $display_order, $is_active, $id]);
                $message = 'Division updated successfully';
            } else {
                $stmt = $conn->prepare("INSERT INTO tbl_division (division_name, description, display_order, is_active, created_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$division_name, $description, $display_order, $is_active, $created_by]);
                $message = 'Division added successfully';
            }
            echo json_encode(['success' => true, 'message' => $message]);
            exit;
        } elseif ($route === 'settings/division-delete') {
            $id = intval($_POST['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid division ID']);
                exit;
            }

            // Check dependencies (Course-Division mapping)
            $count = $dbOps->count('tbl_course_division', ['division_id' => $id]);
            if ($count > 0) {
                echo json_encode(['success' => false, 'message' => 'Cannot delete: Used in course-division mappings.']);
                exit;
            }

            $stmt = $conn->prepare("DELETE FROM tbl_division WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Division deleted successfully']);
            exit;
        } elseif ($route === 'settings/divisions-delete-multiple') {
            $ids = $_POST['ids'] ?? [];
            if (empty($ids) || !is_array($ids)) {
                echo json_encode(['success' => false, 'message' => 'No divisions selected']);
                exit;
            }

            $ids = array_map('intval', $ids);
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';

            // Check if any divisions are in use
            $dependencyCount = $dbOps->customSelect(
                "SELECT COUNT(*) as count FROM tbl_course_division WHERE division_id IN ($placeholders)",
                $ids,
                true
            );
            if ($dependencyCount && $dependencyCount['count'] > 0) {
                echo json_encode(['success' => false, 'message' => 'Some selected divisions are used in course-division mappings and cannot be deleted.']);
                exit;
            }

            $stmt = $conn->prepare("DELETE FROM tbl_division WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            echo json_encode(['success' => true, 'message' => count($ids) . ' divisions deleted successfully']);
            exit;
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

// Handle GET actions (List or Single)
if ($is_api_call && $route === 'settings/division-get') {
    $id = intval($_GET['id'] ?? 0);
    $division = $dbOps->selectOne('tbl_division', ['*'], ['id' => $id]);
    if ($division) {
        echo json_encode(['success' => true, 'data' => ['division' => $division]]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Division not found']);
    }
    exit;
}

// Fetch all divisions (for listing or direct inclusion)
try {
    $divisions = $dbOps->select('tbl_division', ['*'], [], 'display_order ASC, division_name ASC');

    // Fetch created_by user names
    if (is_array($divisions)) {
        foreach ($divisions as &$division) {
            if (isset($division['created_by']) && $division['created_by']) {
                $creator = $dbOps->selectOne('tbl_users', ['name'], ['id' => $division['created_by']]);
                $division['created_by_name'] = $creator['name'] ?? 'Unknown';
            } else {
                $division['created_by_name'] = 'System';
            }
        }
        unset($division); // Break reference
    } else {
        $divisions = [];
    }

} catch (PDOException $e) {
    $divisions = [];
    if ($is_api_call) {
        sendErrorResponse('Database error: ' . $e->getMessage(), 500);
    }
}

// If API call, return JSON response
if ($is_api_call) {
    sendSuccessResponse(['divisions' => $divisions]);
}
