<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';

/**
 * Campuses Controller
 * Supports both API mode (JSON response) and direct inclusion mode
 */

$base_path = dirname(dirname(__DIR__));

require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
// Initialize database operations
$dbOps = new DatabaseOperations();
/** @var PDO $conn */
global $conn;

// Check if this is an API call (via index.php router)
$is_api_call = defined('API_MODE') || (isset($_GET['route']) && !empty($_GET['route']));
$route = $_GET['route'] ?? '';

// Access Control: Super Admin and Principal only for management
if ($is_api_call) {
    header('Content-Type: application/json');
    if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
        exit;
    }
} else {
    if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE])) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

$page_title = "Campus Management";
$page_breadcrumb = "Campuses";

// Handle API POST actions
if ($is_api_call && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($route === 'settings/campus-save' || $route === 'settings/campus-update') {
            $id = intval($_POST['id'] ?? 0);
            $campus_code = trim($_POST['campus_code'] ?? '');
            $campus_name = trim($_POST['campus_name'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $created_by = $_SESSION['user_id'] ?? 0;

            if (empty($campus_code) || empty($campus_name)) {
                echo json_encode(['success' => false, 'message' => 'Campus code and name are required']);
                exit;
            }

            // Check for duplicate campus code
            $existing = $dbOps->selectOne('tbl_campuses', ['id'], ['campus_code' => $campus_code]);
            if ($existing && $existing['id'] != $id) {
                echo json_encode(['success' => false, 'message' => 'Campus code already exists']);
                exit;
            }

            if ($id > 0) {
                $stmt = $conn->prepare("UPDATE tbl_campuses SET campus_code = ?, campus_name = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$campus_code, $campus_name, $is_active, $id]);
                $message = 'Campus updated successfully';
            } else {
                $stmt = $conn->prepare("INSERT INTO tbl_campuses (campus_code, campus_name, is_active, created_by, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([$campus_code, $campus_name, $is_active, $created_by]);
                $message = 'Campus added successfully';
            }
            echo json_encode(['success' => true, 'message' => $message]);
            exit;
        } elseif ($route === 'settings/campus-delete') {
            $id = intval($_POST['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid campus ID']);
                exit;
            }

            // Check dependencies (Students linked to this campus)
            $studentCount = $dbOps->count('tbl_gm_std_registration', ['campus_id' => $id]);
            if ($studentCount > 0) {
                echo json_encode(['success' => false, 'message' => 'Cannot delete campus. It is linked to students.']);
                exit;
            }

            $stmt = $conn->prepare("DELETE FROM tbl_campuses WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Campus deleted successfully']);
            exit;
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

// Handle GET actions (Single)
if ($is_api_call && $route === 'settings/campus-get') {
    $id = intval($_GET['id'] ?? 0);
    $campus = $dbOps->selectOne('tbl_campuses', ['*'], ['id' => $id]);
    if ($campus) {
        echo json_encode(['success' => true, 'data' => ['campus' => $campus]]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Campus not found']);
    }
    exit;
}

// Fetch all campuses (for listing or direct inclusion)
try {
    $campuses = $dbOps->select('tbl_campuses', ['*'], [], 'campus_name ASC');

    // Fetch creator names
    if (is_array($campuses)) {
        foreach ($campuses as &$campus) {
            if (isset($campus['created_by']) && $campus['created_by']) {
                $creator = $dbOps->selectOne('tbl_users', ['name'], ['id' => $campus['created_by']]);
                $campus['created_by_name'] = $creator['name'] ?? 'Unknown';
            } else {
                $campus['created_by_name'] = 'System';
            }
        }
        unset($campus);
    } else {
        $campuses = [];
    }

} catch (PDOException $e) {
    $campuses = [];
    if ($is_api_call) {
        sendErrorResponse('Database error: ' . $e->getMessage(), 500);
    }
}

// If API call, return JSON response
if ($is_api_call) {
    sendSuccessResponse(['campuses' => $campuses]);
}
