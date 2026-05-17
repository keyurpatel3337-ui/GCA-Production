<?php
$page_title = "User Permission Overrides";
require_once dirname(dirname(__DIR__)) . '/session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once PORTAL_GLOBALVARIABLE;
require_once HELPERS_PATH . 'permission_helper.php';

// Access Check
// if (!hasRole(ROLE_SUPER_ADMIN)) {
//     header('Location: ' . PORTAL_URL . '/index.php?error=access_denied');
//     exit;
// }

$message = '';
$error = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    $user_id = $_POST['user_id'];

    try {
        $conn->beginTransaction();

        // Remove existing overrides for this user
        $del = $conn->prepare("DELETE FROM tbl_user_permissions WHERE user_id = ?");
        $del->execute([$user_id]);

        // Add new overrides
        if (isset($_POST['override']) && is_array($_POST['override'])) {
            $insert = $conn->prepare("INSERT INTO tbl_user_permissions (user_id, module_id, has_access) VALUES (?, ?, ?)");
            foreach ($_POST['override'] as $module_id => $access) {
                // Only insert if it's set to explicit Allow (1) or Deny (0)
                // We ignore "Default" (no entry in table)
                if ($access !== '') {
                    $insert->execute([$user_id, $module_id, $access]);
                }
            }
        }

        $conn->commit();
        $message = "User updated successfully.";
    } catch (PDOException $e) {
        $conn->rollBack();
        $error = "Error updating user: " . $e->getMessage();
    }
}

// User Search
$search_query = $_GET['q'] ?? '';
$selected_user_id = $_GET['user_id'] ?? ($_POST['user_id'] ?? 0);
$selected_user = null;
$users = [];

if ($search_query) {
    // Search users
    $stmt = $conn->prepare("SELECT id, name, email, (SELECT role_name FROM tbl_roles WHERE id = tbl_users.role_id) as role_name 
                           FROM tbl_users 
                           WHERE name LIKE ? OR email LIKE ? LIMIT 20");
    $term = "%$search_query%";
    $stmt->execute([$term, $term]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($selected_user_id) {
    // Get User Details
    $stmt = $conn->prepare("SELECT u.*, r.role_name FROM tbl_users u JOIN tbl_roles r ON u.role_id = r.id WHERE u.id = ?");
    $stmt->execute([$selected_user_id]);
    $selected_user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get Role Permissions (for reference)
    $stmt = $conn->prepare("SELECT module_id FROM tbl_role_permissions WHERE role_id = ? AND has_access = 1");
    $stmt->execute([$selected_user['role_id']]);
    $role_perms = $stmt->fetchAll(PDO::FETCH_COLUMN); // List of module IDs allowed by role

    // Get User Overrides
    $stmt = $conn->prepare("SELECT module_id, has_access FROM tbl_user_permissions WHERE user_id = ?");
    $stmt->execute([$selected_user_id]);
    $valid_overrides = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // [module_id => has_access]
}

// Fetch Modules
$modules = $conn->query("SELECT * FROM tbl_modules ORDER BY group_name, module_name")->fetchAll(PDO::FETCH_ASSOC);

include PORTAL_PATH . 'include/header.php';
include PORTAL_PATH . 'include/navbar.php';
include PORTAL_PATH . 'include/sidebar.php';
?>

<link rel="stylesheet" href="<?php echo PORTAL_URL; ?>/assets/css/modules/access_control/user_permissions.css">

    <div class="container-fluid">

        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible">
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Search User -->
            <div class="col-md-4">
                <div class="card card-outline card-primary">
                    <div class="card-header">
                        <h3 class="card-title">Find User</h3>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="">
                            <div class="input-group mb-3">
                                <input type="text" name="q" class="form-control" placeholder="Search name or email"
                                    value="<?php echo htmlspecialchars($search_query ?? ''); ?>">
                                <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i></button>
                            </div>
                        </form>

                        <?php if (!empty($users)): ?>
                            <div class="list-group">
                                <?php foreach ($users as $user): ?>
                                    <a href="?user_id=<?php echo $user['id']; ?>&q=<?php echo urlencode($search_query); ?>"
                                        class="list-group-item list-group-item-action <?php echo $selected_user_id == $user['id'] ? 'active' : ''; ?>">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">
                                                <?php echo htmlspecialchars($user['name'] ?? ''); ?>
                                            </h6>
                                            <small>
                                                <?php echo htmlspecialchars($user['role_name'] ?? ''); ?>
                                            </small>
                                        </div>
                                        <small>
                                            <?php echo htmlspecialchars($user['email'] ?? ''); ?>
                                        </small>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Permission Table -->
            <div class="col-md-8">
                <?php if ($selected_user): ?>
                    <div class="card">
                        <div class="card-header bg-light">
                            <h3 class="card-title">
                                Permissions for: <strong>
                                    <?php echo htmlspecialchars($selected_user['name'] ?? ''); ?>
                                </strong>
                                <span class="badge bg-secondary ms-2">
                                    <?php echo htmlspecialchars($selected_user['role_name'] ?? ''); ?>
                                </span>
                            </h3>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="user_id" value="<?php echo $selected_user['id']; ?>">
                            <!-- Keep search query for UX -->
                            <div class="card-body p-0">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Module</th>
                                            <th>Role Default</th>
                                            <th>User Override</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($modules as $module):
                                            $role_allows = in_array($module['id'], $role_perms);
                                            $user_val = isset($valid_overrides[$module['id']]) ? $valid_overrides[$module['id']] : ''; // '' means inherit
                                            ?>
                                            <tr>
                                                <td>
                                                    <strong>
                                                        <?php echo htmlspecialchars($module['module_name'] ?? ''); ?>
                                                    </strong>
                                                    <br><small class="text-muted">
                                                        <?php echo htmlspecialchars($module['group_name'] ?? ''); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <?php if ($role_allows): ?>
                                                        <span class="badge bg-success">Allowed</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Denied</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <select name="override[<?php echo $module['id']; ?>]"
                                                        class="form-select form-select-sm user_permissions-custom-1 <?php echo $user_val === '1' ? 'user_permissions-bg-allow' : ($user_val === '0' ? 'user_permissions-bg-deny' : ''); ?>">
                                                        <option value="" <?php echo $user_val === '' ? 'selected' : ''; ?>>Inherit
                                                            (Default)</option>
                                                        <option value="1" <?php echo $user_val === 1 ? 'selected' : ''; ?>>Allow
                                                            (Force)</option>
                                                        <option value="0" <?php echo $user_val === 0 ? 'selected' : ''; ?>>Deny
                                                            (Force)</option>
                                                    </select>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="card-footer">
                                <button type="submit" class="btn btn-primary float-end">Save User Overrides</button>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        Select a user from the left to manage permissions.
                    </div>
                <?php endif; ?>
            </div>
        </div>
        </div>

<?php include PORTAL_PATH . 'include/footer.php'; ?>
