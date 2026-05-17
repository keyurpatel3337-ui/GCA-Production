<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';

/**
 * Users Controller
 * Supports both API mode (JSON response) and direct inclusion mode
 */

$base_path = dirname(dirname(__DIR__));

// Include bootstrap for proper response functions
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;

// Initialize Database Operations
$dbOps = new DatabaseOperations();

// Check if this is an API call
$is_api_call = defined('API_MODE') || (isset($_GET['route']) && !empty($_GET['route']));

// Parse JSON request body if present for API calls
if ($is_api_call) {
    $json_input = file_get_contents('php://input');
    if (!empty($json_input)) {
        $json_data = json_decode($json_input, true);
        if (is_array($json_data)) {
            $_POST = array_merge($_POST, $json_data);
            $_REQUEST = array_merge($_REQUEST, $json_data);
        }
    }
}

// Include additional dependencies
// Note: Operation.php already included above, common/operations.php removed to avoid duplicate class
require_once $base_path . '/../common/helpers/error_logger.php';
require_once $base_path . '/common/pagination.php';

// Unified Auth Check
if ($is_api_call) {
    if (!isset($_SESSION['user_id'])) {
        sendErrorResponse('Unauthorized access', 401);
    }
    // Only Super Admin and Principal can manage users
    if ($_SESSION['role_id'] != ROLE_SUPER_ADMIN && $_SESSION['role_id'] != ROLE_PRINCIPLE) {
        sendErrorResponse('Permission denied', 403);
    }
} else {
    if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE])) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

// Get the specific action from the route
$routeAction = isset($_GET['route']) ? explode('/', $_GET['route'])[1] : 'users';

// POST actions (Save, Update, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'] ?? $_POST['id'] ?? null;
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role_id = $_POST['role_id'] ?? null;
    $phone = trim($_POST['phone'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $status = $_POST['status'] ?? 'active';

    // Define allowed roles for Principal
    $principal_allowed_roles = [ROLE_COUNSELLOR, ROLE_ACCOUNTANT, ROLE_ESTABLISHMENT, ROLE_RECEPTION];
    $is_principal = ($_SESSION['role_id'] == ROLE_PRINCIPLE);

    if ($routeAction === 'user-save' || $routeAction === 'user-update') {
        if (empty($name) || empty($email) || empty($role_id)) {
            sendErrorResponse('Name, Email and Role are required', 400);
        }

        // Integrity check: Principal can only assign authorized roles
        if ($is_principal && !in_array($role_id, $principal_allowed_roles)) {
            sendErrorResponse('Unauthorized role assignment', 403);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            sendErrorResponse('Invalid email format', 400);
        }

        try {
            if ($routeAction === 'user-save') {
                if (empty($password))
                    sendErrorResponse('Password is required', 400);

                // Check email
                $stmt = $conn->prepare("SELECT id FROM tbl_users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch())
                    sendErrorResponse('Email already exists', 400);

                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO tbl_users (name, email, role_id, phone, password, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$name, $email, $role_id, $phone, $hashed_password, $status]);
                sendSuccessResponse(null, 'User created successfully');
            } else {
                if (!$user_id)
                    sendErrorResponse('User ID is required', 400);

                // Check email for other users
                $stmt = $conn->prepare("SELECT id FROM tbl_users WHERE email = ? AND id != ?");
                $stmt->execute([$email, $user_id]);
                if ($stmt->fetch())
                    sendErrorResponse('Email already exists for another user', 400);

                if (!empty($password)) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE tbl_users SET name = ?, email = ?, role_id = ?, phone = ?, password = ?, status = ? WHERE id = ?");
                    $stmt->execute([$name, $email, $role_id, $phone, $hashed_password, $status, $user_id]);
                } else {
                    $stmt = $conn->prepare("UPDATE tbl_users SET name = ?, email = ?, role_id = ?, phone = ?, status = ? WHERE id = ?");
                    $stmt->execute([$name, $email, $role_id, $phone, $status, $user_id]);
                }
                sendSuccessResponse(null, 'User updated successfully');
            }
        } catch (PDOException $e) {
            sendErrorResponse('Database error: ' . $e->getMessage(), 500);
        }
    } elseif ($routeAction === 'user-delete' || $routeAction === 'users-delete-multiple') {
        $ids = ($routeAction === 'users-delete-multiple') ? ($_POST['ids'] ?? []) : [$user_id];
        if (empty($ids))
            sendErrorResponse('IDs are required', 400);

        try {
            $conn->beginTransaction();
            $deletedCount = 0;
            $errors = [];

            foreach ($ids as $id) {
                if ($id == ($_SESSION['user_id'] ?? 0)) {
                    $errors[] = "You cannot delete yourself (ID $id)";
                    continue;
                }

                // Principal can only delete users they manage
                if ($is_principal) {
                    $checkStmt = $conn->prepare("SELECT role_id FROM tbl_users WHERE id = ?");
                    $checkStmt->execute([$id]);
                    $targetUser = $checkStmt->fetch();
                    if (!$targetUser || !in_array($targetUser['role_id'], $principal_allowed_roles)) {
                        $errors[] = "Unauthorized to delete user ID $id";
                        continue;
                    }
                }

                // Dependency checks (simplified for brevity, actual apps use CASCADE or manual checks)
                // Check if user has created payments
                $stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_payments WHERE created_by = ?");
                $stmt->execute([$id]);
                if ($stmt->fetchColumn() > 0) {
                    $errors[] = "User ID $id has related records and cannot be deleted";
                    continue;
                }

                $stmt = $conn->prepare("DELETE FROM tbl_users WHERE id = ?");
                $stmt->execute([$id]);
                $deletedCount++;
            }
            $conn->commit();
            sendSuccessResponse(['deleted' => $deletedCount, 'errors' => $errors], "Deleted $deletedCount user(s)");
        } catch (PDOException $e) {
            $conn->rollBack();
            sendErrorResponse('Database error: ' . $e->getMessage(), 500);
        }
    }
    exit;
}

// GET actions (List, Get)
$is_principal = ($_SESSION['role_id'] == ROLE_PRINCIPLE);
$principal_allowed_roles = [ROLE_COUNSELLOR, ROLE_ACCOUNTANT, ROLE_ESTABLISHMENT, ROLE_RECEPTION];

if ($routeAction === 'user-get') {
    $id = $_GET['id'] ?? null;
    if (!$id)
        sendErrorResponse('ID is required');

    try {
        $stmt = $conn->prepare("SELECT u.*, s.name as staff_name, s.personal_email as staff_email 
                                FROM tbl_users u 
                                LEFT JOIN tbl_staff s ON u.id = s.user_id 
                                WHERE u.id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user)
            sendErrorResponse('User not found', 404);

        // Prioritize staff name/email if available
        if ($user['staff_name'])
            $user['name'] = $user['staff_name'];
        if ($user['staff_email'])
            $user['email'] = $user['staff_email'];

        // Access check: Principal can only get users they are allowed to manage
        if ($is_principal && !in_array($user['role_id'], $principal_allowed_roles)) {
            sendErrorResponse('Access denied', 403);
        }

        sendSuccessResponse(['user' => $user]);
    } catch (PDOException $e) {
        sendErrorResponse($e->getMessage());
    }
} else {
    // List logic (same as original with filters)
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = max(1, (int) ($_GET['per_page'] ?? 50));
    $search = $_GET['search'] ?? '';
    $role_filter = $_GET['role'] ?? '';

    try {
        // Exclude Student and Website Admin roles from the dropdown
        $excluded_roles = [ROLE_STUDENT, ROLE_WEBSITE_ADMIN];

        if ($is_principal) {
            $roles_query = "SELECT * FROM tbl_roles WHERE id IN (" . implode(',', $principal_allowed_roles) . ") ORDER BY id";
        } else {
            $roles_query = "SELECT * FROM tbl_roles WHERE id NOT IN (" . implode(',', $excluded_roles) . ") ORDER BY id";
        }

        $roles = $dbOps->customSelect($roles_query);
        if ($roles === false)
            $roles = [];

        $where_clauses = [];
        $params = [];

        if ($is_principal) {
            $where_clauses[] = "u.role_id IN (" . implode(',', $principal_allowed_roles) . ")";
        }

        if ($search) {
            $where_clauses[] = "(u.name LIKE ? OR u.email LIKE ? OR r.role_name LIKE ? OR s.name LIKE ? OR s.personal_email LIKE ?)";
            $params = array_merge($params, ["%$search%", "%$search%", "%$search%", "%$search%", "%$search%"]);
        }

        if (!empty($role_filter)) {
            $where_clauses[] = "r.role_slug = ?";
            $params[] = $role_filter;
        }

        $where = !empty($where_clauses) ? "WHERE " . implode(' AND ', $where_clauses) : "";

        $countQuery = "SELECT COUNT(*) FROM tbl_users u LEFT JOIN tbl_roles r ON u.role_id = r.id LEFT JOIN tbl_staff s ON u.id = s.user_id $where";
        $countStmt = $conn->prepare($countQuery);
        $countStmt->execute($params);
        $totalRecords = $countStmt->fetchColumn();
        $totalPages = ceil($totalRecords / $perPage);

        $offset = ($page - 1) * $perPage;
        $stmt = $conn->prepare("SELECT u.*, r.role_name, s.name as staff_name, s.personal_email as staff_email 
                              FROM tbl_users u 
                              LEFT JOIN tbl_roles r ON u.role_id = r.id 
                              LEFT JOIN tbl_staff s ON u.id = s.user_id
                              $where
                              ORDER BY u.id ASC
                              LIMIT $perPage OFFSET $offset");
        $stmt->execute($params);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Process users to prioritize staff data
        foreach ($users as &$u) {
            if ($u['staff_name'])
                $u['name'] = $u['staff_name'];
            if ($u['staff_email'])
                $u['email'] = $u['staff_email'];
        }

        if ($is_api_call) {
            sendSuccessResponse([
                'users' => $users,
                'roles' => $roles,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total_records' => $totalRecords,
                    'total_pages' => $totalPages
                ],
                'applied_filters' => [
                    'search' => $search,
                    'role' => $role_filter
                ]
            ]);
        }
    } catch (PDOException $e) {
        if ($is_api_call)
            sendErrorResponse($e->getMessage());
    }
}

