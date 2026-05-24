<?php
require_once dirname(__DIR__, 3) . '/session_config.php';
require_once dirname(__DIR__, 4) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;

// Check if user is Maintenance Admin or Super Admin
if (!hasRole(ROLE_MAINTENANCE) && !hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "Database Backup";
$backup_dir = 'D:/portal_backups/database';
$message = '';
$message_type = '';

// Handle backup action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'backup') {
        $backup_type = $_POST['backup_type'] ?? 'daily';
        $target_dir = $backup_dir . '/' . $backup_type;

        // Create base backup directory first if not exists
        if (!is_dir($backup_dir)) {
            if (!@mkdir($backup_dir, 0755, true)) {
                $message = "Failed to create backup directory: {$backup_dir}. Please create the D:/portal_backups folder manually or check permissions.";
                $message_type = 'danger';
                goto skip_backup;
            }
        }

        // Create type-specific directory
        if (!is_dir($target_dir)) {
            if (!@mkdir($target_dir, 0755, true)) {
                $message = "Failed to create backup directory: {$target_dir}. Check permissions.";
                $message_type = 'danger';
                goto skip_backup;
            }
        }

        // Generate filename
        $filename = 'DB_' . date('Y-m-d_His') . '.sql';
        $filepath = $target_dir . '/' . $filename;

        // Get database credentials from env.config.php
        require_once ENV_CONFIG_FILE;

        // Use global variables if not set from require
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
        // FORCE LOCALHOST for backup script as per user request
        $db_host = '127.0.0.1'; // Force TCP/IP localhost
        $db_user = $username ?? 'root';
        $db_pass = $password ?? '';
        $db_name = $dbname ?? '';

        // PHP-based backup (Bypasses mysqldump version/plugin issues)
        try {
            // Check connection
            if (!isset($conn))
                throw new Exception("Database connection not available.");

            $file_handle = fopen($filepath, 'w');
            if (!$file_handle)
                throw new Exception("Could not create backup file at {$filepath}. Check folder permissions.");

            // Write header
            fwrite($file_handle, "-- Database Backup: {$dbname}\n");
            fwrite($file_handle, "-- Generated: " . date('Y-m-d H:i:s') . "\n");
            fwrite($file_handle, "-- Host: {$host}\n");
            fwrite($file_handle, "-- PHP Version: " . phpversion() . "\n\n");
            fwrite($file_handle, "SET FOREIGN_KEY_CHECKS=0;\n");
            fwrite($file_handle, "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n\n");

            // Get all tables
            $tables = [];
            $result = $conn->query("SHOW TABLES");
            while ($row = $result->fetch(PDO::FETCH_NUM)) {
                $tables[] = $row[0];
            }

            foreach ($tables as $table) {
                // Structure
                fwrite($file_handle, "\n-- Table structure for table `{$table}`\n");
                $stmt = $conn->query("SHOW CREATE TABLE `{$table}`");
                $create_table = $stmt->fetch(PDO::FETCH_NUM);
                fwrite($file_handle, "DROP TABLE IF EXISTS `{$table}`;\n");
                fwrite($file_handle, $create_table[1] . ";\n\n");

                // Data
                fwrite($file_handle, "-- Dumping data for table `{$table}`\n");
                $data = $conn->query("SELECT * FROM `{$table}`");
                $num_cols = $data->columnCount();

                while ($row = $data->fetch(PDO::FETCH_NUM)) {
                    $insert_str = "INSERT INTO `{$table}` VALUES(";
                    for ($j = 0; $j < $num_cols; $j++) {
                        if (isset($row[$j])) {
                            // Basic escaping for PHP implementation
                            $val = addslashes($row[$j]);
                            $val = str_replace(["\n", "\r"], ["\\n", "\\r"], $val);
                            $insert_str .= '"' . $val . '"';
                        } else {
                            $insert_str .= 'NULL';
                        }
                        if ($j < ($num_cols - 1))
                            $insert_str .= ',';
                    }
                    $insert_str .= ");\n";
                    fwrite($file_handle, $insert_str);
                }
                fwrite($file_handle, "\n");
            }

            fwrite($file_handle, "\nSET FOREIGN_KEY_CHECKS=1;\n");
            fclose($file_handle);

            $message = "Database backup created successfully: {$filename} (PHP Export)";
            $message_type = 'success';
            logError("Database backup created using PHP: {$filename}", 'INFO');

        } catch (Exception $e) {
            if (isset($file_handle) && is_resource($file_handle))
                fclose($file_handle);
            $message = "Backup failed: " . $e->getMessage();
            $message_type = 'danger';
            logError("Database backup failed: " . $e->getMessage());
            if (file_exists($filepath))
                @unlink($filepath);
        }
        skip_backup:
    }

    if ($_POST['action'] === 'delete' && isset($_POST['file'])) {
        $file_to_delete = $backup_dir . '/' . $_POST['type'] . '/' . basename($_POST['file']);
        if (file_exists($file_to_delete) && unlink($file_to_delete)) {
            $message = "Backup deleted successfully.";
            $message_type = 'success';
        } else {
            $message = "Failed to delete backup.";
            $message_type = 'danger';
        }
    }

    if ($_POST['action'] === 'download' && isset($_POST['file'])) {
        $file_to_download = $backup_dir . '/' . $_POST['type'] . '/' . basename($_POST['file']);
        if (file_exists($file_to_download)) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($file_to_download) . '"');
            header('Content-Length: ' . filesize($file_to_download));
            readfile($file_to_download);
            exit;
        }
    }
}

// Get existing backups
$backups = [
    'daily' => [],
    'monthly' => [],
    'yearly' => []
];

foreach (['daily', 'monthly', 'yearly'] as $type) {
    $type_dir = $backup_dir . '/' . $type;
    if (is_dir($type_dir)) {
        $files = glob($type_dir . '/*.sql');
        foreach ($files as $file) {
            $backups[$type][] = [
                'name' => basename($file),
                'size' => round(filesize($file) / 1024 / 1024, 2),
                'date' => date('d-M-Y H:i:s', filemtime($file))
            ];
        }
        // Sort by date descending
        usort($backups[$type], function ($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
    }
}

include $_SERVER['DOCUMENT_ROOT'] . '/portal/include/header.php';
include $_SERVER['DOCUMENT_ROOT'] . '/portal/include/sidebar.php';
?>



<div class="container-fluid py-4">
    <!-- Message handling with refined alerts -->
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show border-0 shadow-sm mb-4">
            <div class="d-flex align-items-center">
                <i
                    class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> fs-4 me-3"></i>
                <div><?php echo $message; ?></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Statistics Row -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="glass-card p-4 d-flex align-items-center h-100">
                <div class="stat-icon bg-info-subtle text-info me-3 css-database-8a775b">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div>
                    <div class="text-muted small fw-bold text-uppercase mb-1">Daily Backups</div>
                    <div class="h3 fw-bold mb-0 text-dark"><?php echo count($backups['daily']); ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="glass-card p-4 d-flex align-items-center h-100">
                <div class="stat-icon bg-success-subtle text-success me-3 css-database-8a775b">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div>
                    <div class="text-muted small fw-bold text-uppercase mb-1">Monthly Backups</div>
                    <div class="h3 fw-bold mb-0 text-dark"><?php echo count($backups['monthly']); ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="glass-card p-4 d-flex align-items-center h-100">
                <div class="stat-icon bg-warning-subtle text-warning me-3 css-database-8a775b">
                    <i class="fas fa-history"></i>
                </div>
                <div>
                    <div class="text-muted small fw-bold text-uppercase mb-1">Yearly Backups</div>
                    <div class="h3 fw-bold mb-0 text-dark"><?php echo count($backups['yearly']); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create New Backup Card -->
    <div class="glass-card mb-4 overflow-hidden border-0">
        <div class="card-header bg-primary text-white py-3 px-4 border-0">
            <h5 class="card-title mb-0 fw-bold"><i class="fas fa-shield-alt me-2"></i> Create Manual Backup</h5>
        </div>
        <div class="card-body p-4">
            <form method="POST" class="row g-4 align-items-end">
                <input type="hidden" name="action" value="backup">
                <div class="col-lg-4 col-md-6">
                    <label class="form-label fw-bold text-dark mb-2">Select Interval</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i
                                class="fas fa-clock"></i></span>
                        <select name="backup_type" class="form-select border-start-0 ps-0 bg-light">
                            <option value="daily">Daily Maintenance</option>
                            <option value="monthly">Monthly Snapshot</option>
                            <option value="yearly">Yearly Archival</option>
                        </select>
                    </div>
                </div>
                <div class="col-lg-5 col-md-6">
                    <label class="form-label fw-bold text-dark mb-2">Target Storage Path</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i
                                class="fas fa-folder-open"></i></span>
                        <input type="text" class="form-control border-start-0 ps-0 bg-light"
                            value="D:/portal_backups/database/" readonly>
                    </div>
                </div>
                <div class="col-lg-3 col-md-12">
                    <button type="submit" class="btn btn-primary w-100 py-2 fw-bold shadow-sm">
                        <i class="fas fa-database me-2"></i> Generate Backup
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Existing Backups Explorer -->
    <div class="glass-card border-0 overflow-hidden">
        <div class="card-header bg-white py-0 border-bottom">
            <ul class="nav nav-tabs border-0" id="backupTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active px-4 py-3 border-0 fw-bold css-database-d5d5bf" data-bs-toggle="tab" href="#daily">
                        <i class="fas fa-calendar-day me-2 text-info"></i> Daily
                        <span
                            class="badge bg-info-subtle text-info ms-2 rounded-pill"><?php echo count($backups['daily']); ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link px-4 py-3 border-0 fw-bold css-database-d5d5bf" data-bs-toggle="tab" href="#monthly">
                        <i class="fas fa-calendar-alt me-2 text-success"></i> Monthly
                        <span
                            class="badge bg-success-subtle text-success ms-2 rounded-pill"><?php echo count($backups['monthly']); ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link px-4 py-3 border-0 fw-bold css-database-d5d5bf" data-bs-toggle="tab" href="#yearly">
                        <i class="fas fa-history me-2 text-warning"></i> Yearly
                        <span
                            class="badge bg-warning-subtle text-warning ms-2 rounded-pill"><?php echo count($backups['yearly']); ?></span>
                    </a>
                </li>
            </ul>
        </div>
        <div class="card-body p-0">
            <div class="tab-content">
                <?php foreach (['daily', 'monthly', 'yearly'] as $type): ?>
                    <div class="tab-pane fade <?php echo $type === 'daily' ? 'show active' : ''; ?>"
                        id="<?php echo $type; ?>">
                        <?php if (empty($backups[$type])): ?>
                            <div class="text-center py-5">
                                <div class="mb-3 text-muted opacity-50">
                                    <i class="fas fa-database fa-4x"></i>
                                </div>
                                <h5 class="text-muted fw-bold">No <?php echo ucfirst($type); ?> Backups</h5>
                                <p class="text-muted small">Generated SQL backups for this category will appear here.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th class="ps-4 text-uppercase small fw-bold">Backup Name</th>
                                            <th class="text-uppercase small fw-bold">File Size</th>
                                            <th class="text-uppercase small fw-bold">Created On</th>
                                            <th class="text-end pe-4 text-uppercase small fw-bold">Management</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($backups[$type] as $backup): ?>
                                            <tr>
                                                <td class="ps-4">
                                                    <div class="d-flex align-items-center">
                                                        <div class="icon-box bg-primary-subtle text-primary me-3 rounded p-2 css-database-86aa09">
                                                            <i class="fas fa-file-code"></i>
                                                        </div>
                                                        <div>
                                                            <div class="fw-bold text-dark"><?php echo $backup['name']; ?></div>
                                                            <small class="text-muted">Type: SQL Script</small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span
                                                        class="badge bg-light text-dark border fw-medium px-2 py-1"><?php echo $backup['size']; ?>
                                                        MB</span>
                                                </td>
                                                <td>
                                                    <div class="text-dark fw-medium small"><?php echo $backup['date']; ?></div>
                                                </td>
                                                <td class="text-end pe-4">
                                                    <div class="btn-group">
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="action" value="download">
                                                            <input type="hidden" name="type" value="<?php echo $type; ?>">
                                                            <input type="hidden" name="file" value="<?php echo $backup['name']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-success"
                                                                title="Download Backup">
                                                                <i class="fas fa-download"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" class="d-inline ms-1"
                                                            onsubmit="return confirm('Permanently delete this backup file? This action cannot be undone.');">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="type" value="<?php echo $type; ?>">
                                                            <input type="hidden" name="file" value="<?php echo $backup['name']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                                                title="Delete Backup">
                                                                <i class="fas fa-trash-alt"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
</div>



<?php include $_SERVER['DOCUMENT_ROOT'] . '/portal/include/footer.php'; ?>