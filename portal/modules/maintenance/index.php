<?php
require_once dirname(__DIR__, 2) . '/session_config.php';
require_once dirname(__DIR__, 3) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;
require_once dirname(__DIR__, 3) . '/common/helpers/format_helper.php';
// Check if user is Maintenance Admin or Super Admin
if (!hasRole(ROLE_MAINTENANCE) && !hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "System Maintenance Dashboard";

// Get system statistics
try {
    $op = new Operation();

    // Total Students
    $students_count = $conn->query("SELECT COUNT(*) FROM tbl_gm_std_registration")->fetchColumn();

    // Active Enrollments
    $enrollments_count = $conn->query("SELECT COUNT(*) FROM tbl_enrolled_students WHERE is_active = 1")->fetchColumn();

    // Today's Payments
    $today_payments = $conn->query("SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total FROM tbl_payments WHERE DATE(payment_date) = CURDATE()")->fetch(PDO::FETCH_ASSOC);

    // Database Size
    $db_size = $conn->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn();

    // D: Drive Space
    $d_drive_free = @disk_free_space('D:');
    $d_drive_total = @disk_total_space('D:');
    $d_drive_free_gb = $d_drive_free ? round($d_drive_free / 1024 / 1024 / 1024, 2) : 'N/A';
    $d_drive_total_gb = $d_drive_total ? round($d_drive_total / 1024 / 1024 / 1024, 2) : 'N/A';

    // C: Drive Space
    $c_drive_free = @disk_free_space('C:');
    $c_drive_free_gb = $c_drive_free ? round($c_drive_free / 1024 / 1024 / 1024, 2) : 'N/A';

    // Check Last Backup
    $backup_dir = 'D:/portal_backups/database/daily';
    $last_backup = 'Never';
    $backup_status = 'warning';
    if (is_dir($backup_dir)) {
        $files = glob($backup_dir . '/*.sql');
        if (!empty($files)) {
            usort($files, function ($a, $b) {
                return filemtime($b) - filemtime($a);
            });
            $last_backup = date('d-M-Y H:i', filemtime($files[0]));
            $backup_status = (time() - filemtime($files[0])) < 86400 ? 'success' : 'warning';
        }
    }

} catch (Exception $e) {
    logError("Maintenance Dashboard Error: " . $e->getMessage());
}

include $_SERVER['DOCUMENT_ROOT'] . '/portal/include/header.php';
include $_SERVER['DOCUMENT_ROOT'] . '/portal/include/sidebar.php';
?>



<div class="container-fluid">

    <!-- Quick Stats Row -->
    <div class="row mb-4">
        <div class="col-lg-3 col-6">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3>
                        <?php echo formatIndianCurrency($students_count ?? 0, false); ?>
                    </h3>
                    <p>Total Students</p>
                </div>
                <div class="icon"><i class="fas fa-users"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3>
                        <?php echo formatIndianCurrency($enrollments_count ?? 0, false); ?>
                    </h3>
                    <p>Active Enrollments</p>
                </div>
                <div class="icon"><i class="fas fa-user-check"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-warning">
                <div class="inner">
                    <h3>₹
                        <?php echo formatIndianCurrency($today_payments['total'] ?? 0, false); ?>
                    </h3>
                    <p>Today's Payments (
                        <?php echo $today_payments['count'] ?? 0; ?>)
                    </p>
                </div>
                <div class="icon"><i class="fas fa-rupee-sign"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-primary">
                <div class="inner">
                    <h3>
                        <?php echo $db_size ?? 0; ?> MB
                    </h3>
                    <p>Database Size</p>
                </div>
                <div class="icon"><i class="fas fa-database"></i></div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- System Health -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-heartbeat"></i> System Health</h3>
                </div>
                <div class="card-body p-0">
                    <table class="table table-striped mb-0">
                        <tbody>
                            <tr>
                                <td><i class="fas fa-server text-primary"></i> MySQL Database</td>
                                <td class="text-end">
                                    <span class="badge bg-success"><i class="fas fa-check"></i> Connected</span>
                                </td>
                            </tr>
                            <tr>
                                <td><i class="fas fa-hdd text-info"></i> C: Drive</td>
                                <td class="text-end">
                                    <span
                                        class="badge bg-<?php echo ($c_drive_free_gb !== 'N/A' && $c_drive_free_gb > 5) ? 'success' : 'warning'; ?>">
                                        <?php echo $c_drive_free_gb; ?> GB free
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td><i class="fas fa-hdd text-success"></i> D: Drive (Backups)</td>
                                <td class="text-end">
                                    <span
                                        class="badge bg-<?php echo ($d_drive_free_gb !== 'N/A' && $d_drive_free_gb > 10) ? 'success' : 'warning'; ?>">
                                        <?php echo $d_drive_free_gb; ?> GB free
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td><i class="fas fa-clock text-warning"></i> Last Backup</td>
                                <td class="text-end">
                                    <span class="badge bg-<?php echo $backup_status; ?>">
                                        <?php echo $last_backup; ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td><i class="fas fa-memory text-danger"></i> PHP Memory</td>
                                <td class="text-end">
                                    <span class="badge bg-info">
                                        <?php echo round(memory_get_usage() / 1024 / 1024, 2); ?> MB used
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">
                    <a href="system/health.php" class="btn btn-sm btn-primary">
                        <i class="fas fa-stethoscope"></i> Full Health Check
                    </a>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-bolt"></i> Quick Actions</h3>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6">
                            <a href="backup/database.php" class="btn btn-outline-primary w-100 py-3">
                                <i class="fas fa-database fa-2x mb-2 d-block"></i>
                                Backup Database
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="backup/files.php" class="btn btn-outline-success w-100 py-3">
                                <i class="fas fa-folder fa-2x mb-2 d-block"></i>
                                Backup Files
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="backup/receipt-reports.php" class="btn btn-outline-info w-100 py-3">
                                <i class="fas fa-file-excel fa-2x mb-2 d-block"></i>
                                Receipt Reports
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="logs/errors.php" class="btn btn-outline-danger w-100 py-3">
                                <i class="fas fa-exclamation-circle fa-2x mb-2 d-block"></i>
                                Error Logs
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Module Cards -->
    <div class="row mt-4">
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="fas fa-database fa-3x text-primary mb-3"></i>
                    <h5>Backup Management</h5>
                    <p class="text-muted small">Database, Files, Receipt Reports</p>
                    <a href="backup/database.php" class="btn btn-primary btn-sm">Open</a>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="fas fa-plug fa-3x text-success mb-3"></i>
                    <h5>API Debugger</h5>
                    <p class="text-muted small">API Logs, Test API Endpoints</p>
                    <a href="api-debug/index.php" class="btn btn-success btn-sm">Open</a>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="fas fa-chart-bar fa-3x text-info mb-3"></i>
                    <h5>System Statistics</h5>
                    <p class="text-muted small">Usage Stats, Growth Charts</p>
                    <a href="system/statistics.php" class="btn btn-info btn-sm">Open</a>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="fas fa-tools fa-3x text-warning mb-3"></i>
                    <h5>System Tools</h5>
                    <p class="text-muted small">Cache, Sessions, DB Tools</p>
                    <a href="tools/cache.php" class="btn btn-warning btn-sm">Open</a>
                </div>
            </div>
        </div>
    </div>

</div>

<style>
    .small-box {
        border-radius: 0.5rem;
        box-shadow: 0 0 1px rgba(0, 0, 0, .125), 0 1px 3px rgba(0, 0, 0, .2);
        position: relative;
        padding: 20px;
        margin-bottom: 0;
    }

    .small-box .inner h3 {
        font-size: 2.2rem;
        font-weight: 700;
        margin: 0;
        color: #fff;
    }

    .small-box .inner p {
        font-size: 0.9rem;
        color: rgba(255, 255, 255, 0.8);
        margin: 0;
    }

    .small-box .icon {
        position: absolute;
        top: 15px;
        right: 15px;
        font-size: 4rem;
        color: rgba(255, 255, 255, 0.2);
    }
</style>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/portal/include/footer.php'; ?>