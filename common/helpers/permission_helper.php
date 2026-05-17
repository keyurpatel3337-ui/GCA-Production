<?php
/**
 * Permission Helper Functions
 * Handles granular access control validation
 */

if (!defined('ROLE_SUPER_ADMIN')) {
    // Ensure constants are loaded if accessed directly
    require_once dirname(__DIR__, 2) . '/common/constants.php';
    require_once PORTAL_GLOBALVARIABLE;
}

/**
 * Check if the current user (or specified user) has access to a specific module
 * 
 * Logic:
 * 1. Super Admin always has access.
 * 2. Check User Permissions table for explicit override (Allow/Deny).
 * 3. If no user override, check Role Permissions table.
 * 4. Default to false if not found.
 * 
 * @param string $module_key The unique key of the module (e.g., 'fees.collection')
 * @param int|null $user_id Optional user ID. Defaults to current logged-in user.
 * @return bool True if allowed, False otherwise.
 */
function hasModuleAccess($module_key, $user_id = null)
{
    global $conn, $role_id;

    // Use current session if user_id is not provided
    if ($user_id === null) {
        $user_id = $_SESSION['user_id'] ?? 0;
        $current_role_id = $_SESSION['role_id'] ?? 0;
    } else {
        // Fetch role for the specific user if provided
        $stmt = $conn->prepare("SELECT role_id FROM tbl_users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $current_role_id = $user['role_id'] ?? 0;
    }

    // 1. Super Admin Bypass
    if ($current_role_id == ROLE_SUPER_ADMIN) {
        return true;
    }

    // Get Module ID
    // Cache this query if possible in future optimization
    $stmt = $conn->prepare("SELECT id FROM tbl_modules WHERE module_key = ?");
    $stmt->execute([$module_key]);
    $module = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$module) {
        // Module doesn't exist - secure by default
        return false;
    }

    $module_id = $module['id'];

    // 2. Check User Specific Permissions (Override)
    // Note: tbl_user_permissions and tbl_role_permissions tables removed during DB optimization
    // These queries will return default (deny) if tables don't exist
    try {
        $stmt = $conn->prepare("SELECT has_access FROM tbl_user_permissions WHERE user_id = ? AND module_id = ?");
        $stmt->execute([$user_id, $module_id]);
        $user_perm = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user_perm) {
            return (bool) $user_perm['has_access'];
        }
    } catch (PDOException $e) {
        // Table may not exist — skip user permission check
    }

    // 3. Check Role Permissions
    try {
        $stmt = $conn->prepare("SELECT has_access FROM tbl_role_permissions WHERE role_id = ? AND module_id = ?");
        $stmt->execute([$current_role_id, $module_id]);
        $role_perm = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($role_perm) {
            return (bool) $role_perm['has_access'];
        }
    } catch (PDOException $e) {
        // Table may not exist — skip role permission check
    }

    // 4. Default Deny
    return false;
}

/**
 * Enforce permission requirement for a page
 * Redirects to error page or dashboard if access is denied.
 * 
 * @param string $module_key
 */
function requirePermission($module_key)
{
    if (!hasModuleAccess($module_key)) {
        // Check if it's an AJAX request
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('HTTP/1.1 403 Forbidden');
            echo json_encode(['status' => 'error', 'message' => 'Access Denied']);
            exit;
        }

        // Regular request - Redirect
        $_SESSION['error_msg'] = "Access Denied: You do not have permission to access " . htmlspecialchars($module_key ?? '');
        header('Location: ' . PORTAL_URL . '/index.php?error=access_denied');
        exit;
    }
}
