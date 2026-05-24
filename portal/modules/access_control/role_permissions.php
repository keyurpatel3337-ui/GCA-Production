<?php
$page_title = "Role Permissions";
require_once dirname(dirname(__DIR__)) . '/session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once PORTAL_GLOBALVARIABLE;
require_once HELPERS_PATH . 'permission_helper.php';

if (!isset($conn)) {
    require_once DB_CONNECT_FILE;
}

// Access Check
// if (!hasRole(ROLE_SUPER_ADMIN)) {
//     header('Location: ' . PORTAL_URL . '/index.php?error=access_denied');
//     exit;
// }

$message = '';
$error = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role_id = $_POST['role_id'];
    
    // Clear existing permissions for this role
    // Ideally we would update cleanly, but delete-insert is simpler for bulk matrix
    // BUT we must be careful not to lose specific "deny" set if we had 3-state logic.
    // Here we assume 1=Allow, 0=Deny (Default).
    
    try {
        $conn->beginTransaction();
        
        // Delete all for this role first
        $del = $conn->prepare("DELETE FROM tbl_role_permissions WHERE role_id = ?");
        $del->execute([$role_id]);
        
        // Insert selected
        if (isset($_POST['permissions']) && is_array($_POST['permissions'])) {
            $insert = $conn->prepare("INSERT INTO tbl_role_permissions (role_id, module_id, has_access) VALUES (?, ?, 1)");
            foreach ($_POST['permissions'] as $module_id) {
                $insert->execute([$role_id, $module_id]);
            }
        }
        
        $conn->commit();
        $message = "Permissions updated successfully.";
    } catch (PDOException $e) {
        $conn->rollBack();
        $error = "Failed to update permissions: " . $e->getMessage();
    }
}

// Fetch Roles (excluding Super Admin)
$roles = $conn->query("SELECT * FROM tbl_roles WHERE id != " . ROLE_SUPER_ADMIN . " ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch Modules grouped
$modules = $conn->query("SELECT * FROM tbl_modules ORDER BY group_name, module_name")->fetchAll(PDO::FETCH_ASSOC);

// Selected Role (Default to first one)
$selected_role_id = isset($_GET['role_id']) ? $_GET['role_id'] : ($roles[0]['id'] ?? 0);

// Fetch Current Permissions for Selected Role
$current_perms = [];
if ($selected_role_id) {
    $stmt = $conn->prepare("SELECT module_id FROM tbl_role_permissions WHERE role_id = ? AND has_access = 1");
    $stmt->execute([$selected_role_id]);
    $current_perms = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

include PORTAL_PATH . 'include/header.php';
include PORTAL_PATH . 'include/navbar.php';
include PORTAL_PATH . 'include/sidebar.php';
?>



    <div class="container-fluid">
        
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible">
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Role Selector -->
            <div class="col-md-3">
                <div class="card card-outline card-primary">
                    <div class="card-header">
                        <h3 class="card-title">Select Role</h3>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php foreach ($roles as $role): ?>
                                <a href="?role_id=<?php echo $role['id']; ?>" 
                                   class="list-group-item list-group-item-action <?php echo $selected_role_id == $role['id'] ? 'active' : ''; ?>">
                                    <?php echo htmlspecialchars($role['role_name'] ?? ''); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Permission Matrix -->
            <div class="col-md-9">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Manage Permissions for: <strong><?php echo getRoleName($selected_role_id, $conn); ?></strong></h3>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="role_id" value="<?php echo $selected_role_id; ?>">
                        <div class="card-body">
                            <?php 
                            $current_group = '';
                            foreach ($modules as $module): 
                                if ($current_group !== $module['group_name']):
                                    if ($current_group !== '') echo '</div></div>'; // Close previous group
                                    $current_group = $module['group_name'];
                            ?>
                                <div class="mb-3">
                                    <h5 class="border-bottom pb-2"><?php echo htmlspecialchars($current_group ?? ''); ?></h5>
                                    <div class="row">
                            <?php endif; ?>
                            
                            <div class="col-md-6 col-lg-4">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="permissions[]" 
                                           value="<?php echo $module['id']; ?>" 
                                           id="mod_<?php echo $module['id']; ?>"
                                           <?php echo in_array($module['id'], $current_perms) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="mod_<?php echo $module['id']; ?>" title="<?php echo htmlspecialchars($module['description'] ?? ''); ?>">
                                        <?php echo htmlspecialchars($module['module_name'] ?? ''); ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($module['module_key'] ?? ''); ?></small>
                                    </label>
                                </div>
                            </div>

                            <?php endforeach; ?>
                            </div></div> <!-- Close last group -->
                        
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary">Save Permissions</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        </div>

<?php include PORTAL_PATH . 'include/footer.php'; ?>

