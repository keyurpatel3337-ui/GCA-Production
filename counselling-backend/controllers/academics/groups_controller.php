<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';

/**
 * Group Controller
 * Supports both API mode (JSON response) and direct inclusion mode
 */

$base_path = dirname(dirname(__DIR__));

require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
// Initialize database operations
$dbOps = new DatabaseOperations();

// Check if this is an API call (via index.php router)
$route = preg_replace('/[^a-zA-Z0-9\-_\/]/', '', $_GET['route'] ?? '');
$is_api_call = defined('API_MODE') || !empty($route);

if ($is_api_call) {
    header('Content-Type: application/json');
    if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_ESTABLISHMENT, ROLE_RECEPTION])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
        exit;
    }
} else {
    if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_ESTABLISHMENT, ROLE_RECEPTION])) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

$page_title = "Group Management";
$page_breadcrumb = "Groups";

// Handle API POST actions
if ($is_api_call && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($route === 'settings/group-save' || $route === 'settings/group-update') {
            $id = intval($_POST['id'] ?? 0);
            $group_name = trim($_POST['group_name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $created_by = $_SESSION['user_id'] ?? 0;

            if (empty($group_name)) {
                echo json_encode(['success' => false, 'message' => 'Group name is required']);
                exit;
            }

            // Check for duplicate group name
            $existing = $dbOps->selectOne('tbl_group', ['id'], ['group_name' => $group_name]);
            if ($existing && $existing['id'] != $id) {
                echo json_encode(['success' => false, 'message' => 'This group name already exists']);
                exit;
            }

            if ($id > 0) {
                $stmt = $conn->prepare("UPDATE tbl_group SET group_name = ?, description = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$group_name, $description, $is_active, $id]);
                $message = 'Group updated successfully';
            } else {
                $stmt = $conn->prepare("INSERT INTO tbl_group (group_name, description, is_active, created_by, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([$group_name, $description, $is_active, $created_by]);
                $message = 'Group added successfully';
            }
            echo json_encode(['success' => true, 'message' => $message]);
            exit;
        } elseif ($route === 'settings/group-delete') {
            $id = intval($_POST['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid group ID']);
                exit;
            }

            // Check dependencies (Fee Config)
            $count = $dbOps->count('tbl_fee_config', ['group_id' => $id]);
            if ($count > 0) {
                echo json_encode(['success' => false, 'message' => 'Cannot delete: Used in fee configurations.']);
                exit;
            }

            $stmt = $conn->prepare("DELETE FROM tbl_group WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Group deleted successfully']);
            exit;
        } elseif ($route === 'settings/group-delete-multiple') {
            $ids = isset($_POST['ids']) && is_array($_POST['ids']) ? array_map('intval', $_POST['ids']) : [];
            if (empty($ids) || !is_array($ids)) {
                echo json_encode(['success' => false, 'message' => 'No groups selected']);
                exit;
            }

            $ids = array_map('intval', $ids);
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';

            // Check if any groups are in use in fee_config
            $dependencyCount = $dbOps->customSelectOne(
                "SELECT COUNT(*) as count FROM tbl_fee_config WHERE group_id IN ($placeholders)",
                $ids
            );
            if ($dependencyCount && $dependencyCount['count'] > 0) {
                echo json_encode(['success' => false, 'message' => 'Some selected groups are used in fee configurations and cannot be deleted.']);
                exit;
            }

            $stmt = $conn->prepare("DELETE FROM tbl_group WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            echo json_encode(['success' => true, 'message' => count($ids) . ' groups deleted successfully']);
            exit;
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

// Handle GET actions (List or Single)
if ($is_api_call && $route === 'settings/group-get') {
    $id = intval($_GET['id'] ?? 0);
    $group = $dbOps->selectOne('tbl_group', ['*'], ['id' => $id]);
    if ($group) {
        echo json_encode(['success' => true, 'data' => ['group' => $group]]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Group not found']);
    }
    exit;
}

// Fetch all groups (for listing or direct inclusion)
try {
    $groups = $dbOps->select('tbl_group', ['*'], [], 'group_name ASC');

    // Fetch created_by user names
    if (is_array($groups)) {
        foreach ($groups as &$group) {
            if (isset($group['created_by']) && $group['created_by']) {
                $creator = $dbOps->selectOne('tbl_users', ['name'], ['id' => $group['created_by']]);
                $group['created_by_name'] = $creator['name'] ?? 'Unknown';
            } else {
                $group['created_by_name'] = 'System';
            }
        }
        unset($group); // Break reference
    } else {
        $groups = [];
    }

} catch (PDOException $e) {
    $groups = [];
    if ($is_api_call) {
        sendErrorResponse('Database error: ' . $e->getMessage(), 500);
    }
}

// If API call, return JSON response
if ($is_api_call) {
    sendSuccessResponse(['groups' => $groups]);
}
