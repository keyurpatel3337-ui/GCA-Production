<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';

/**
 * Medium Controller
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

$page_title = "Medium Management";
$page_breadcrumb = "Mediums";

// Handle API POST actions
if ($is_api_call && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($route === 'settings/medium-save' || $route === 'settings/medium-update') {
            $id = intval($_POST['id'] ?? 0);
            $medium_name = trim($_POST['medium_name'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $created_by = $_SESSION['user_id'] ?? 0;

            if (empty($medium_name)) {
                echo json_encode(['success' => false, 'message' => 'Medium name is required']);
                exit;
            }

            // Check for duplicate medium name
            $existing = $dbOps->selectOne('tbl_medium', ['id'], ['medium_name' => $medium_name]);
            if ($existing && $existing['id'] != $id) {
                echo json_encode(['success' => false, 'message' => 'This medium name already exists']);
                exit;
            }

            if ($id > 0) {
                $stmt = $conn->prepare("UPDATE tbl_medium SET medium_name = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$medium_name, $is_active, $id]);
                $message = 'Medium updated successfully';
            } else {
                $stmt = $conn->prepare("INSERT INTO tbl_medium (medium_name, is_active, created_by, created_at) VALUES (?, ?, ?, NOW())");
                $stmt->execute([$medium_name, $is_active, $created_by]);
                $message = 'Medium added successfully';
            }
            echo json_encode(['success' => true, 'message' => $message]);
            exit;
        } elseif ($route === 'settings/medium-delete') {
            $id = intval($_POST['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid medium ID']);
                exit;
            }

            // Check dependencies (Fee Config and Students)
            $feeConfigCount = $dbOps->count('tbl_fee_config', ['medium_id' => $id]);
            if ($feeConfigCount > 0) {
                echo json_encode(['success' => false, 'message' => 'Cannot delete: Used in fee configurations.']);
                exit;
            }

            $studentCount = $dbOps->count('tbl_enrolled_students', ['medium_id' => $id]);
            if ($studentCount > 0) {
                echo json_encode(['success' => false, 'message' => 'Cannot delete: Linked to students.']);
                exit;
            }

            $stmt = $conn->prepare("DELETE FROM tbl_medium WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Medium deleted successfully']);
            exit;
        } elseif ($route === 'settings/medium-delete-multiple') {
            $ids = $_POST['ids'] ?? [];
            if (empty($ids) || !is_array($ids)) {
                echo json_encode(['success' => false, 'message' => 'No mediums selected']);
                exit;
            }

            $ids = array_map('intval', $ids);
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';

            // Check if any mediums are in use
            $dependencyCount = $dbOps->customSelectOne(
                "SELECT COUNT(*) as count FROM tbl_enrolled_students WHERE medium_id IN ($placeholders)",
                $ids
            );
            if ($dependencyCount && $dependencyCount['count'] > 0) {
                echo json_encode(['success' => false, 'message' => 'Some selected mediums are linked to students and cannot be deleted.']);
                exit;
            }

            $stmt = $conn->prepare("DELETE FROM tbl_medium WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            echo json_encode(['success' => true, 'message' => count($ids) . ' mediums deleted successfully']);
            exit;
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

// Handle GET actions (List or Single)
if ($is_api_call && $route === 'settings/medium-get') {
    $id = intval($_GET['id'] ?? 0);
    $medium = $dbOps->selectOne('tbl_medium', ['*'], ['id' => $id]);
    if ($medium) {
        echo json_encode(['success' => true, 'data' => ['medium' => $medium]]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Medium not found']);
    }
    exit;
}

// Fetch all mediums (for listing or direct inclusion)
try {
    $mediums = $dbOps->select('tbl_medium', ['*'], [], 'medium_name ASC');

    // Fetch created_by user names
    if (is_array($mediums)) {
        foreach ($mediums as &$m) {
            if (isset($m['created_by']) && $m['created_by']) {
                $creator = $dbOps->selectOne('tbl_users', ['name'], ['id' => $m['created_by']]);
                $m['created_by_name'] = $creator['name'] ?? 'Unknown';
            } else {
                $m['created_by_name'] = 'System';
            }
        }
        unset($m); // Break reference
    } else {
        $mediums = [];
    }

} catch (PDOException $e) {
    $mediums = [];
    if ($is_api_call) {
        sendErrorResponse('Database error: ' . $e->getMessage(), 500);
    }
}

// If API call, return JSON response
if ($is_api_call) {
    sendSuccessResponse(['mediums' => $mediums]);
}
