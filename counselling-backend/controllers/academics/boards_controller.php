<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';

/**
 * Boards Controller
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

$page_title = "Board Management";
$page_breadcrumb = "Boards";

// Handle API POST actions
if ($is_api_call && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($route === 'settings/board-save' || $route === 'settings/board-update') {
            $id = intval($_POST['id'] ?? 0);
            $board_name = trim($_POST['board_name'] ?? '');
            $board_code = trim($_POST['board_code'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $created_by = $_SESSION['user_id'] ?? 0;

            if (empty($board_name)) {
                echo json_encode(['success' => false, 'message' => 'Board name is required']);
                exit;
            }

            // Check for duplicate board name
            $existing = $dbOps->selectOne('tbl_boards', ['id'], ['board_name' => $board_name]);
            if ($existing && $existing['id'] != $id) {
                echo json_encode(['success' => false, 'message' => 'Board with this name already exists']);
                exit;
            }

            if ($id > 0) {
                $stmt = $conn->prepare("UPDATE tbl_boards SET board_name = ?, board_code = ?, description = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$board_name, $board_code, $description, $is_active, $id]);
                $message = 'Board updated successfully';
            } else {
                $stmt = $conn->prepare("INSERT INTO tbl_boards (board_name, board_code, description, is_active, created_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$board_name, $board_code, $description, $is_active, $created_by]);
                $message = 'Board added successfully';
            }
            echo json_encode(['success' => true, 'message' => $message]);
            exit;
        } elseif ($route === 'settings/board-delete') {
            $id = intval($_POST['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid board ID']);
                exit;
            }

            // Check dependencies (Linked Courses)
            $count = $dbOps->count('tbl_courses', ['board_id' => $id]);
            if ($count > 0) {
                echo json_encode(['success' => false, 'message' => 'Cannot delete board. It is assigned to courses.']);
                exit;
            }

            $stmt = $conn->prepare("DELETE FROM tbl_boards WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Board deleted successfully']);
            exit;
        } elseif ($route === 'settings/boards-delete-multiple') {
            $ids = isset($_POST['ids']) && is_array($_POST['ids']) ? array_map('intval', $_POST['ids']) : [];
            if (empty($ids) || !is_array($ids)) {
                echo json_encode(['success' => false, 'message' => 'No boards selected']);
                exit;
            }

            $ids = array_map('intval', $ids);
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';

            // Check if any boards are in use
            $dependencyCount = $dbOps->customSelectOne(
                "SELECT COUNT(*) as count FROM tbl_courses WHERE board_id IN ($placeholders)",
                $ids
            );
            if ($dependencyCount && $dependencyCount['count'] > 0) {
                echo json_encode(['success' => false, 'message' => 'Some selected boards are linked to courses and cannot be deleted.']);
                exit;
            }

            $stmt = $conn->prepare("DELETE FROM tbl_boards WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            echo json_encode(['success' => true, 'message' => count($ids) . ' boards deleted successfully']);
            exit;
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

// Handle GET actions (List or Single)
if ($is_api_call && $route === 'settings/board-get') {
    $id = intval($_GET['id'] ?? 0);
    $board = $dbOps->selectOne('tbl_boards', ['*'], ['id' => $id]);
    if ($board) {
        echo json_encode(['success' => true, 'data' => ['board' => $board]]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Board not found']);
    }
    exit;
}

// Fetch all boards (for listing or direct inclusion)
try {
    $boards = $dbOps->select('tbl_boards', ['*'], [], 'board_name ASC');

    // Fetch created_by user names
    if (is_array($boards)) {
        foreach ($boards as &$board) {
            if (isset($board['created_by']) && $board['created_by']) {
                $creator = $dbOps->selectOne('tbl_users', ['name'], ['id' => $board['created_by']]);
                $board['created_by_name'] = $creator['name'] ?? 'Unknown';
            } else {
                $board['created_by_name'] = 'System';
            }
        }
        unset($board); // Break reference
    } else {
        $boards = [];
    }

} catch (PDOException $e) {
    $boards = [];
    if ($is_api_call) {
        sendErrorResponse('Database error: ' . $e->getMessage(), 500);
    }
}

// If API call, return JSON response
if ($is_api_call) {
    sendSuccessResponse([
        'boards' => $boards
    ]);
}
