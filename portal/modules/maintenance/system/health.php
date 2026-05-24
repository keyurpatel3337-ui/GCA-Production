<?php
require_once dirname(__DIR__, 3) . '/session_config.php';
require_once dirname(__DIR__, 4) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once PORTAL_GLOBALVARIABLE;

// Check if user is Maintenance Admin or Super Admin
if (!hasRole(ROLE_MAINTENANCE) && !hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "System Health";

// Perform health checks
$health_checks = [];

// 1. MySQL Connection
try {
    $start = microtime(true);
    $conn->query('SELECT 1');
    $db_time = round((microtime(true) - $start) * 1000, 2);
    $health_checks['mysql'] = ['status' => 'ok', 'message' => "Connected ({$db_time}ms)", 'icon' => 'fa-database', 'color' => 'success'];
} catch (Exception $e) {
    $health_checks['mysql'] = ['status' => 'error', 'message' => 'Connection failed', 'icon' => 'fa-database', 'color' => 'danger'];
}

// 2. C: Drive Space
$c_free = @disk_free_space('C:');
$c_total = @disk_total_space('C:');
if ($c_free && $c_total) {
    $c_gb = round($c_free / 1024 / 1024 / 1024, 2);
    $c_percent = round(($c_free / $c_total) * 100);
    $c_color = $c_gb > 10 ? 'success' : ($c_gb > 5 ? 'warning' : 'danger');
    $health_checks['c_drive'] = ['status' => $c_color === 'success' ? 'ok' : 'warning', 'message' => "{$c_gb} GB free ({$c_percent}%)", 'icon' => 'fa-hdd', 'color' => $c_color];
} else {
    $health_checks['c_drive'] = ['status' => 'error', 'message' => 'Unable to check', 'icon' => 'fa-hdd', 'color' => 'danger'];
}

// 3. D: Drive Space (Backups)
$d_free = @disk_free_space('D:');
$d_total = @disk_total_space('D:');
if ($d_free && $d_total) {
    $d_gb = round($d_free / 1024 / 1024 / 1024, 2);
    $d_percent = round(($d_free / $d_total) * 100);
    $d_color = $d_gb > 20 ? 'success' : ($d_gb > 10 ? 'warning' : 'danger');
    $health_checks['d_drive'] = ['status' => $d_color === 'success' ? 'ok' : 'warning', 'message' => "{$d_gb} GB free ({$d_percent}%)", 'icon' => 'fa-hdd', 'color' => $d_color];
} else {
    $health_checks['d_drive'] = ['status' => 'warning', 'message' => 'D: Drive not found', 'icon' => 'fa-hdd', 'color' => 'warning'];
}

// 4. PHP Memory
$mem_used = memory_get_usage(true);
$mem_limit = ini_get('memory_limit');
$mem_limit_bytes = intval($mem_limit) * 1024 * 1024;
$mem_percent = round(($mem_used / $mem_limit_bytes) * 100);
$mem_color = $mem_percent < 50 ? 'success' : ($mem_percent < 80 ? 'warning' : 'danger');
$health_checks['php_memory'] = ['status' => $mem_color === 'success' ? 'ok' : 'warning', 'message' => round($mem_used / 1024 / 1024, 2) . " MB / {$mem_limit} ({$mem_percent}%)", 'icon' => 'fa-memory', 'color' => $mem_color];

// 5. Last Database Backup
$db_backup_dir = 'D:/portal_backups/database/daily';
$last_db_backup = 'Never';
$backup_color = 'danger';
if (is_dir($db_backup_dir)) {
    $files = glob($db_backup_dir . '/*.sql');
    if (!empty($files)) {
        usort($files, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        $last_time = filemtime($files[0]);
        $last_db_backup = date('d-M-Y H:i', $last_time);
        $hours_ago = (time() - $last_time) / 3600;
        $backup_color = $hours_ago < 24 ? 'success' : ($hours_ago < 48 ? 'warning' : 'danger');
    }
}
$health_checks['last_backup'] = ['status' => $backup_color === 'success' ? 'ok' : 'warning', 'message' => $last_db_backup, 'icon' => 'fa-clock', 'color' => $backup_color];

// 6. PHP Version
$php_version = phpversion();
$health_checks['php_version'] = ['status' => 'ok', 'message' => $php_version, 'icon' => 'fa-code', 'color' => 'info'];

// 7. Upload Directory Writable
$upload_dir = ROOT_PATH . 'uploads';
$upload_writable = is_writable($upload_dir);
$health_checks['upload_dir'] = ['status' => $upload_writable ? 'ok' : 'error', 'message' => $upload_writable ? 'Writable' : 'Not Writable', 'icon' => 'fa-folder', 'color' => $upload_writable ? 'success' : 'danger'];

// 8. Session Status
$session_active = session_status() === PHP_SESSION_ACTIVE;
$health_checks['session'] = ['status' => $session_active ? 'ok' : 'warning', 'message' => $session_active ? 'Active' : 'Inactive', 'icon' => 'fa-user-clock', 'color' => $session_active ? 'success' : 'warning'];

include $_SERVER['DOCUMENT_ROOT'] . '/portal/include/header.php';
include $_SERVER['DOCUMENT_ROOT'] . '/portal/include/sidebar.php';
?>



    <div class="container-fluid">

        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-stethoscope"></i> Health Checks</h3>
                        <span class="float-end text-muted small">Last checked:
                            <?php echo date('d-M-Y H:i:s'); ?>
                        </span>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-striped mb-0">
                            <tbody>
                                <tr>
                                    <td><i class="fas fa-database text-primary"></i> MySQL Database</td>
                                    <td class="text-end"><span
                                            class="badge bg-<?php echo $health_checks['mysql']['color']; ?>">
                                            <?php echo $health_checks['mysql']['message']; ?>
                                        </span></td>
                                </tr>
                                <tr>
                                    <td><i class="fas fa-hdd text-info"></i> C: Drive (System)</td>
                                    <td class="text-end"><span
                                            class="badge bg-<?php echo $health_checks['c_drive']['color']; ?>">
                                            <?php echo $health_checks['c_drive']['message']; ?>
                                        </span></td>
                                </tr>
                                <tr>
                                    <td><i class="fas fa-hdd text-success"></i> D: Drive (Backups)</td>
                                    <td class="text-end"><span
                                            class="badge bg-<?php echo $health_checks['d_drive']['color']; ?>">
                                            <?php echo $health_checks['d_drive']['message']; ?>
                                        </span></td>
                                </tr>
                                <tr>
                                    <td><i class="fas fa-memory text-warning"></i> PHP Memory</td>
                                    <td class="text-end"><span
                                            class="badge bg-<?php echo $health_checks['php_memory']['color']; ?>">
                                            <?php echo $health_checks['php_memory']['message']; ?>
                                        </span></td>
                                </tr>
                                <tr>
                                    <td><i class="fas fa-clock text-secondary"></i> Last Backup</td>
                                    <td class="text-end"><span
                                            class="badge bg-<?php echo $health_checks['last_backup']['color']; ?>">
                                            <?php echo $health_checks['last_backup']['message']; ?>
                                        </span></td>
                                </tr>
                                <tr>
                                    <td><i class="fas fa-code text-info"></i> PHP Version</td>
                                    <td class="text-end"><span
                                            class="badge bg-<?php echo $health_checks['php_version']['color']; ?>">
                                            <?php echo $health_checks['php_version']['message']; ?>
                                        </span></td>
                                </tr>
                                <tr>
                                    <td><i class="fas fa-folder text-warning"></i> Upload Directory</td>
                                    <td class="text-end"><span
                                            class="badge bg-<?php echo $health_checks['upload_dir']['color']; ?>">
                                            <?php echo $health_checks['upload_dir']['message']; ?>
                                        </span></td>
                                </tr>
                                <tr>
                                    <td><i class="fas fa-user-clock text-primary"></i> Session Status</td>
                                    <td class="text-end"><span
                                            class="badge bg-<?php echo $health_checks['session']['color']; ?>">
                                            <?php echo $health_checks['session']['message']; ?>
                                        </span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer">
                        <a href="" class="btn btn-primary"><i class="fas fa-sync"></i> Refresh</a>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-chart-pie"></i> Summary</h3>
                    </div>
                    <div class="card-body text-center">
                        <?php
                        $ok_count = count(array_filter($health_checks, fn($h) => $h['status'] === 'ok'));
                        $total = count($health_checks);
                        $health_percent = round(($ok_count / $total) * 100);
                        $overall_color = $health_percent >= 80 ? 'success' : ($health_percent >= 50 ? 'warning' : 'danger');
                        ?>
                        <h1 class="display-1 text-<?php echo $overall_color; ?>">
                            <?php echo $health_percent; ?>%
                        </h1>
                        <p class="text-muted">
                            <?php echo $ok_count; ?> /
                            <?php echo $total; ?> checks passed
                        </p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-server"></i> Server Info</h3>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0">
                            <tr>
                                <td>Server</td>
                                <td>
                                    <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?>
                                </td>
                            </tr>
                            <tr>
                                <td>PHP</td>
                                <td>
                                    <?php echo phpversion(); ?>
                                </td>
                            </tr>
                            <tr>
                                <td>MySQL</td>
                                <td>
                                    <?php echo $conn->query('SELECT VERSION()')->fetchColumn(); ?>
                                </td>
                            </tr>
                            <tr>
                                <td>OS</td>
                                <td>
                                    <?php echo PHP_OS; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        </div>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/portal/include/footer.php'; ?>
