<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;

// Check if user is Maintenance Admin or Super Admin
if (!hasRole(ROLE_MAINTENANCE) && !hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "Maintenance Dashboard";

// Get system statistics
try {
    if (!isset($conn)) {
        require_once DB_CONNECT_FILE;
    }

    // Total Students
    $students_count = $conn->query("SELECT COUNT(*) FROM tbl_gm_std_registration")->fetchColumn();

    // Active Enrollments
    $enrollments_count = $conn->query("SELECT COUNT(*) FROM tbl_enrolled_students WHERE is_active = 1")->fetchColumn();

    // Database Size
    $db_size = $conn->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn();

    // Drive Space Stats
    $d_drive_free = @disk_free_space('D:');
    $d_drive_total = @disk_total_space('D:');
    $d_drive_free_gb = $d_drive_free ? round($d_drive_free / 1024 / 1024 / 1024, 2) : 'N/A';

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

include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>


<div class="container-fluid py-4 pb-5">

    <!-- Key Metrics Row -->
    <div class="row g-4 mb-5">
        <!-- Database Health -->
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="glass-card h-100">
                <div class="stat-card">
                    <div class="stat-top">
                        <div>
                            <div class="stat-value text-primary">
                                <?php echo $db_size; ?> MB
                            </div>
                            <div class="stat-label">Database size</div>
                        </div>
                        <div class="stat-icon bg-icon-info">
                            <i class="fas fa-database"></i>
                        </div>
                    </div>
                    <a href="../maintenance/tools/database.php" class="stat-link text-info">
                        Explore Tools <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Last Backup -->
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="glass-card h-100">
                <div class="stat-card">
                    <div class="stat-top">
                        <div>
                            <div
                                class="stat-value text-<?php echo $backup_status == 'success' ? 'success' : 'warning'; ?>">
                                <?php echo $last_backup != 'Never' ? 'Recent' : 'None'; ?>
                            </div>
                            <div class="stat-label">Last Backup:
                                <?php echo $last_backup; ?>
                            </div>
                        </div>
                        <div class="stat-icon bg-icon-success">
                            <i class="fas fa-history"></i>
                        </div>
                    </div>
                    <a href="../maintenance/backup/database.php" class="stat-link text-success">
                        Manage Backups <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Drive Space -->
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="glass-card h-100">
                <div class="stat-card">
                    <div class="stat-top">
                        <div>
                            <div class="stat-value text-<?php echo ($c_drive_free_gb > 10) ? 'primary' : 'danger'; ?>">
                                <?php echo $c_drive_free_gb; ?> GB
                            </div>
                            <div class="stat-label">C: Drive Free</div>
                        </div>
                        <div class="stat-icon bg-icon-primary">
                            <i class="fas fa-hdd"></i>
                        </div>
                    </div>
                    <a href="../maintenance/system/health.php" class="stat-link text-primary">
                        System Health <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- PHP Memory -->
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="glass-card h-100">
                <div class="stat-card">
                    <div class="stat-top">
                        <div>
                            <div class="stat-value text-warning">
                                <?php echo round(memory_get_usage() / 1024 / 1024, 2); ?> MB
                            </div>
                            <div class="stat-label">Memory Usage</div>
                        </div>
                        <div class="stat-icon bg-icon-warning">
                            <i class="fas fa-memory"></i>
                        </div>
                    </div>
                    <a href="../maintenance/system/info.php" class="stat-link text-warning">
                        PHP Info <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions & Recent Logs -->
    <div class="row g-4">
        <!-- Quick Actions -->
        <div class="col-lg-6">
            <div class="glass-card p-4 h-100">
                <h4 class="fw-bold mb-4 d-flex align-items-center">
                    <i class="fas fa-bolt text-warning me-2"></i> Quick Actions
                </h4>
                <div class="row g-3">
                    <div class="col-6">
                        <a href="../maintenance/backup/database.php" class="quick-action-btn">
                            <div class="quick-icon bg-primary-subtle text-primary">
                                <i class="fas fa-server"></i>
                            </div>
                            <div class="quick-info">
                                <strong>DB Backup</strong>
                                <span>Manual trigger</span>
                            </div>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="../maintenance/tools/cache.php" class="quick-action-btn">
                            <div class="quick-icon bg-success-subtle text-success">
                                <i class="fas fa-broom"></i>
                            </div>
                            <div class="quick-info">
                                <strong>Clear Cache</strong>
                                <span>Purge system</span>
                            </div>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="../maintenance/api-debug/index.php" class="quick-action-btn">
                            <div class="quick-icon bg-info-subtle text-info">
                                <i class="fas fa-plug"></i>
                            </div>
                            <div class="quick-info">
                                <strong>API Logs</strong>
                                <span>Debug stream</span>
                            </div>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="../maintenance/error-logs/errors.php" class="quick-action-btn">
                            <div class="quick-icon bg-danger-subtle text-danger">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="quick-info">
                                <strong>Fatal Errors</strong>
                                <span>PHP logs</span>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity / Logs -->
        <div class="col-lg-6">
            <div class="glass-card p-4 h-100">
                <h4 class="fw-bold mb-4 d-flex align-items-center">
                    <i class="fas fa-list-alt text-primary me-2"></i> Recent Activity
                </h4>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Action</th>
                                <th>User</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            try {
                                $logs = $conn->query("SELECT action, user_id, created_at FROM tbl_audit_log ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
                                if ($logs):
                                    foreach ($logs as $log): ?>
                                        <tr>
                                            <td><span class="badge bg-light text-dark fw-normal">
                                                    <?php echo htmlspecialchars($log['action'] ?? ''); ?>
                                                </span></td>
                                            <td><small>UID:
                                                    <?php echo $log['user_id']; ?>
                                                </small></td>
                                            <td><small class="text-muted">
                                                    <?php echo date('H:i:s', strtotime($log['created_at'])); ?>
                                                </small></td>
                                        </tr>
                                    <?php endforeach;
                                else: ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted">No recent logs found</td>
                                    </tr>
                                <?php endif;
                            } catch (Exception $e) {
                                echo '<tr><td colspan="3" class="text-danger">Failed to load logs</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Welcome Banner */
    .welcome-banner {
        padding: 2.5rem;
        background: linear-gradient(135deg, #1e3a5f 0%, #0f172a 100%);
        border-radius: 20px;
        color: white;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    }

    /* Glass Card */
    .glass-card {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.4);
        border-radius: 16px;
        box-shadow: 0 8px 32px rgba(31, 38, 135, 0.07);
        transition: transform 0.3s ease;
    }

    /* Quick Action Button */
    .quick-action-btn {
        display: flex;
        align-items: center;
        padding: 1rem;
        background: white;
        border: 1px solid #edf2f7;
        border-radius: 12px;
        text-decoration: none;
        color: inherit;
        transition: all 0.2s ease;
    }

    .quick-action-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        border-color: #e2e8f0;
    }

    .quick-icon {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        margin-right: 1rem;
    }

    .quick-info strong {
        display: block;
        font-size: 0.95rem;
        color: #2d3748;
    }

    .quick-info span {
        font-size: 0.8rem;
        color: #718096;
    }

    /* Stat Cards */
    .stat-card {
        padding: 1.5rem;
    }

    .stat-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1rem;
    }

    .stat-value {
        font-size: 1.75rem;
        font-weight: 700;
    }

    .stat-label {
        color: #718096;
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.025em;
        font-weight: 600;
    }

    .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }

    .bg-icon-info {
        background: rgba(0, 184, 217, 0.1);
        color: #00B8D9;
    }

    .bg-icon-success {
        background: rgba(54, 179, 126, 0.1);
        color: #36B37E;
    }

    .bg-icon-primary {
        background: rgba(0, 82, 204, 0.1);
        color: #0052CC;
    }

    .bg-icon-warning {
        background: rgba(255, 171, 0, 0.1);
        color: #FFAB00;
    }

    .stat-link {
        font-size: 0.875rem;
        font-weight: 600;
        text-decoration: none;
    }
</style>

<?php include '../../include/footer.php'; ?>

