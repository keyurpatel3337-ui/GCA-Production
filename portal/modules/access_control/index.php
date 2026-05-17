<?php
$page_title = "Access Control Dashboard";
require_once dirname(dirname(__DIR__)) . '/session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once PORTAL_GLOBALVARIABLE;
require_once HELPERS_PATH . 'permission_helper.php';

// Only Super Admin can access this module
// if (!hasRole(ROLE_SUPER_ADMIN)) {
//     header('Location: ' . PORTAL_URL . '/index.php?error=access_denied');
//     exit;
// }

include PORTAL_PATH . 'include/header.php';
include PORTAL_PATH . 'include/navbar.php';
include PORTAL_PATH . 'include/sidebar.php';

// Get counts for dashboard
$stmt = $conn->query("SELECT COUNT(*) as count FROM tbl_modules");
$module_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $conn->query("SELECT COUNT(*) as count FROM tbl_roles WHERE id != " . ROLE_SUPER_ADMIN);
$role_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $conn->query("SELECT COUNT(DISTINCT user_id) as count FROM tbl_user_permissions");
$user_override_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
?>



    <div class="container-fluid">
        <!-- Dashboard Widgets -->
        <div class="row">
            <div class="col-lg-4 col-6">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3>
                            <?php echo $module_count; ?>
                        </h3>
                        <p>Total Modules</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-cubes"></i>
                    </div>
                    <a href="modules_list.php" class="small-box-footer">
                        Manage Modules <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>

            <div class="col-lg-4 col-6">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3>
                            <?php echo $role_count; ?>
                        </h3>
                        <p>Roles Managed</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-user-tag"></i>
                    </div>
                    <a href="role_permissions.php" class="small-box-footer">
                        Role Permissions <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>

            <div class="col-lg-4 col-6">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3>
                            <?php echo $user_override_count; ?>
                        </h3>
                        <p>User Overrides</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-users-cog"></i>
                    </div>
                    <a href="user_permissions.php" class="small-box-footer">
                        User Permissions <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-4 col-6">
                <!-- small box -->
                <div class="small-box bg-danger">
                    <div class="inner">
                        <h3>Logs</h3>
                        <p>System Error Logs</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <!-- Link to the existing errors.php in maintenance/logs -->
                    <a href="../maintenance/logs/errors.php" class="small-box-footer">View Logs <i
                            class="fas fa-arrow-circle-right"></i></a>
                </div>
            </div>
        </div>

        <!-- Instructions -->
        <div class="row mt-3">
            <div class="col-md-12">
                <div class="card card-outline card-primary">
                    <div class="card-header">
                        <h3 class="card-title">How It Works</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <h5>1. Modules</h5>
                                <p class="text-muted">Define the functional areas of your application (e.g., "Fee
                                    Collection", "View Reports"). These are the keys developers check in the code.</p>
                            </div>
                            <div class="col-md-4">
                                <h5>2. Role Permissions</h5>
                                <p class="text-muted">Assign default access limits for each role. For example, give
                                    "Accountants" access to "Fee" modules but not "Academic" ones.</p>
                            </div>
                            <div class="col-md-4">
                                <h5>3. User Permissions</h5>
                                <p class="text-muted">Override role defaults for specific individuals. You can Grant
                                    access to a user who normally wouldn't have it, or Revoke from one who would.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        </div>

<?php include PORTAL_PATH . 'include/footer.php'; ?>
