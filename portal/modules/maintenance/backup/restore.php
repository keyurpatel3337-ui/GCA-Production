<?php
require_once dirname(__DIR__, 3) . '/session_config.php';
require_once dirname(__DIR__, 4) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;

// Check if user is Maintenance Admin or Super Admin
if (!hasRole(ROLE_MAINTENANCE) && !hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "Restore Backup";
$message = '';
$message_type = '';

// Handle restore action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'restore') {
    $restore_type = $_POST['restore_type'] ?? '';
    $file_path    = $_POST['file_path'] ?? '';

    // Allowlist: only files inside known backup directories are permitted
    $allowed_base_dirs = [
        realpath('D:/portal_backups/database/daily'),
        realpath('D:/portal_backups/database/monthly'),
        realpath('D:/portal_backups/database/yearly'),
    ];

    // Resolve the real path to prevent path traversal (e.g. ../../etc/passwd)
    $real_file_path = realpath($file_path);

    $is_allowed = false;
    if ($real_file_path !== false && pathinfo($real_file_path, PATHINFO_EXTENSION) === 'sql') {
        foreach ($allowed_base_dirs as $base_dir) {
            if ($base_dir !== false && strpos($real_file_path, $base_dir . DIRECTORY_SEPARATOR) === 0) {
                $is_allowed = true;
                break;
            }
        }
    }

    if ($restore_type === 'database' && $is_allowed && file_exists($real_file_path)) {
        // Restore database — all arguments sanitized with escapeshellarg
        require_once ENV_CONFIG_FILE;

        // Use proc_open for explicit pipe control — avoids shell interpolation issues
        $descriptors = [
            0 => ['file', $real_file_path, 'r'], // stdin: the SQL dump file
            1 => ['pipe', 'w'],                   // stdout
            2 => ['pipe', 'w'],                   // stderr
        ];
        $proc_cmd = [
            $mysql_path,
            '--user='     . $username,
            '--password=' . $password,
            '--host='     . $host,
            $dbname,
        ];
        $process = proc_open($proc_cmd, $descriptors, $pipes);
        if (is_resource($process)) {
            fclose($pipes[1]);
            $error_output = stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            $return_var = proc_close($process);
        } else {
            $return_var = 1;
            $error_output = 'Failed to start mysql process';
        }

        if ($return_var === 0) {
            $message      = 'Database restored successfully from: ' . htmlspecialchars(basename($real_file_path), ENT_QUOTES, 'UTF-8');
            $message_type = 'success';
            logError('Database restored from: ' . basename($real_file_path) . ' by user ID: ' . ($_SESSION['user_id'] ?? 0), 'INFO');
        } else {
            $message      = 'Restore failed. Check server error logs for details.';
            $message_type = 'danger';
            // Log raw output server-side only — never expose to browser
            logError('Restore failed for ' . basename($real_file_path) . ': ' . ($error_output ?? ''), 'ERROR');
        }
    } else {
        $message      = 'Invalid or disallowed backup file specified.';
        $message_type = 'danger';
    }
}

// Get available backups for restore
$db_backups = [];
$backup_dirs = ['daily', 'monthly', 'yearly'];
foreach ($backup_dirs as $type) {
    $dir = "D:/portal_backups/database/{$type}";
    if (is_dir($dir)) {
        $files = glob($dir . '/*.sql');
        foreach ($files as $file) {
            $db_backups[] = [
                'path' => $file,
                'name' => basename($file),
                'type' => ucfirst($type),
                'size' => round(filesize($file) / 1024 / 1024, 2),
                'date' => date('d-M-Y H:i', filemtime($file))
            ];
        }
    }
}
usort($db_backups, function ($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

include $_SERVER['DOCUMENT_ROOT'] . '/portal/include/header.php';
include $_SERVER['DOCUMENT_ROOT'] . '/portal/include/sidebar.php';
?>



    <div class="container-fluid">

        <?php if ($message): ?>
            <div class="alert alert-<?php echo htmlspecialchars($message_type, ENT_QUOTES, 'UTF-8'); ?> alert-dismissible fade show">
                <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i> <strong>Warning:</strong> Restoring a backup will overwrite
            current data. This action cannot be undone. Make sure to create a fresh backup before restoring.
        </div>

        <!-- Database Restore -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-database"></i> Database Restore</h3>
            </div>
            <div class="card-body">
                <?php if (empty($db_backups)): ?>
                    <div class="alert alert-info">No database backups available for restore.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Filename</th>
                                    <th>Type</th>
                                    <th>Size</th>
                                    <th>Created</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($db_backups as $backup): ?>
                                    <tr>
                                        <td><i class="fas fa-file text-primary"></i>
                                            <?php echo $backup['name']; ?>
                                        </td>
                                        <td><span class="badge bg-secondary">
                                                <?php echo $backup['type']; ?>
                                            </span></td>
                                        <td>
                                            <?php echo $backup['size']; ?> MB
                                        </td>
                                        <td>
                                            <?php echo $backup['date']; ?>
                                        </td>
                                        <td>
                                            <form method="POST"
                                                onsubmit="return confirm('Are you sure you want to restore this backup? This will overwrite all current database data!');">
                                                <input type="hidden" name="action" value="restore">
                                                <input type="hidden" name="restore_type" value="database">
                                                <input type="hidden" name="file_path" value="<?php echo htmlspecialchars($backup['path'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <button type="submit" class="btn btn-sm btn-warning">
                                                    <i class="fas fa-undo"></i> Restore
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        </div>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/portal/include/footer.php'; ?>
