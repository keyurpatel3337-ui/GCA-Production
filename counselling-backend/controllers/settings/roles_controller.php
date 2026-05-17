<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';

/**
 * Roles Controller
 * Supports both API mode (JSON response) and direct inclusion mode
 */

$base_path = dirname(dirname(__DIR__));

// Include bootstrap for proper response functions
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;

$dbOps = new DatabaseOperations();

// Check if this is an API call
$is_api_call = defined('API_MODE') || (isset($_GET['route']) && !empty($_GET['route']));

if (!$is_api_call) {
    require_once OPERATION_FILE;
    // Auth check
    if (!hasRole(ROLE_SUPER_ADMIN)) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

$action = isset($_GET['route']) ? explode('/', $_GET['route'])[1] : 'list';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'role-save') {
        $role_name = trim($_POST['role_name'] ?? '');
        $role_slug = trim($_POST['role_slug'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (empty($role_name) || empty($role_slug)) {
            sendErrorResponse('Role name and slug are required', 400);
        }

        try {
            // Check if slug already exists
            $existing = $dbOps->selectOne('tbl_roles', ['id'], ['role_slug' => $role_slug]);
            if ($existing) {
                sendErrorResponse('Role slug already exists', 400);
            }

            $stmt = $conn->prepare("INSERT INTO tbl_roles (role_name, role_slug, description, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$role_name, $role_slug, $description]);

            sendSuccessResponse(null, 'Role created successfully');
        } catch (PDOException $e) {
            sendErrorResponse('Database error: ' . $e->getMessage(), 500);
        }
    } elseif ($action === 'roles-delete-multiple') {
        $ids = $_POST['ids'] ?? [];
        if (empty($ids) || !is_array($ids)) {
            sendErrorResponse('Invalid selection', 400);
        }

        try {
            $conn->beginTransaction();
            $deletedCount = 0;
            $errors = [];

            foreach ($ids as $id) {
                // Check if role is assigned to users
                $stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_users WHERE role_id = ?");
                $stmt->execute([$id]);
                if ($stmt->fetchColumn() > 0) {
                    $errors[] = "Role ID $id is in use";
                    continue;
                }

                $stmt = $conn->prepare("DELETE FROM tbl_roles WHERE id = ?");
                $stmt->execute([$id]);
                $deletedCount++;
            }

            $conn->commit();
            sendSuccessResponse(['deleted' => $deletedCount, 'errors' => $errors], "Deleted $deletedCount role(s)");
        } catch (PDOException $e) {
            $conn->rollBack();
            sendErrorResponse('Database error: ' . $e->getMessage(), 500);
        }
    }
    exit;
}

// Default action: List
try {
    $roles = $dbOps->customSelect(
        "SELECT r.*, COUNT(u.id) as user_count 
        FROM tbl_roles r 
        LEFT JOIN tbl_users u ON r.id = u.role_id 
        GROUP BY r.id 
        ORDER BY r.id"
    );

    if ($is_api_call) {
        sendSuccessResponse(['roles' => $roles]);
    }
} catch (PDOException $e) {
    if ($is_api_call) {
        sendErrorResponse('Database error: ' . $e->getMessage(), 500);
    }
}
