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

$page_title = "Files Backup";
$backup_dir = 'D:/portal_backups/files';

// Configuration for what to backup
$source_root = ROOT_PATH;
$exclude_dirs = [
    'vendor',
    '.git',
    '.playwright-mcp',
    '.vscode',
    'node_modules',
    'portal_backups',
    'tmp',
    'common/logs',
    'archive'
];
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
                $message = "Failed to create backup directory: {$backup_dir}. Please create the D:/portal_backups/files folder manually or check permissions.";
                $message_type = 'danger';
                goto skip_files_backup;
            }
        }

        // Create type-specific directory
        if (!is_dir($target_dir)) {
            if (!@mkdir($target_dir, 0755, true)) {
                $message = "Failed to create backup directory: {$target_dir}. Check permissions.";
                $message_type = 'danger';
                goto skip_files_backup;
            }
        }

        // Generate filename
        $filename = 'Files_' . date('Y-m-d_His') . '.zip';
        $filepath = $target_dir . '/' . $filename;

        try {
            if (!class_exists('ZipArchive')) {
                throw new Exception("Class 'ZipArchive' not found. Please enable the 'zip' extension in your PHP configuration (php.ini) and restart Apache/PHP-FPM.");
            }

            $zip = new ZipArchive();
            if ($zip->open($filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {

                $dir = realpath($source_root);
                $exclude_paths = array_map(function($path) use ($dir) {
                    return realpath($dir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
                }, $exclude_dirs);
                
                // Filter out nulls from realpath failures
                $exclude_paths = array_filter($exclude_paths);

                $directory_iterator = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
                
                $filter_iterator = new RecursiveCallbackFilterIterator($directory_iterator, function ($current, $key, $iterator) use ($exclude_paths) {
                    $pathname = $current->getPathname();
                    
                    // Check if current path starts with any excluded path
                    foreach ($exclude_paths as $ex_path) {
                        if (strpos($pathname, $ex_path) === 0) {
                            return false;
                        }
                    }
                    return true;
                });

                $iterator = new RecursiveIteratorIterator($filter_iterator, RecursiveIteratorIterator::SELF_FIRST);

                foreach ($iterator as $file) {
                    $pathname = $file->getPathname();
                    $relativePath = str_replace($dir . DIRECTORY_SEPARATOR, '', $pathname);
                    $relativePath = str_replace('\\', '/', $relativePath);

                    if ($file->isDir()) {
                        $zip->addEmptyDir($relativePath);
                    } else {
                        $zip->addFile($pathname, $relativePath);
                    }
                }

                $zip->close();
                $message = "Full system backup created successfully (excluding vendor/logs): {$filename}";
                $message_type = 'success';
                logError("Full files backup created: {$filename} by user ID: " . ($_SESSION['user_id'] ?? 0), 'INFO');
            } else {
                throw new Exception("Could not create zip file at: {$filepath}");
            }
        } catch (Exception $e) {
            $message = "Backup failed: " . $e->getMessage();
            $message_type = 'danger';
            logError("Files backup failed: " . $e->getMessage());
        }
        skip_files_backup:
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
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . basename($file_to_download) . '"');
            header('Content-Length: ' . filesize($file_to_download));
            readfile($file_to_download);
            exit;
        }
    }
}

// Get existing backups
$backups = ['daily' => [], 'monthly' => [], 'yearly' => []];

foreach (['daily', 'monthly', 'yearly'] as $type) {
    $type_dir = $backup_dir . '/' . $type;
    if (is_dir($type_dir)) {
        $files = glob($type_dir . '/*.zip');
        foreach ($files as $file) {
            $backups[$type][] = [
                'name' => basename($file),
                'size' => round(filesize($file) / 1024 / 1024, 2),
                'date' => date('d-M-Y H:i:s', filemtime($file))
            ];
        }
        usort($backups[$type], function ($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
    }
}

// Get source info - Main project size (excluding vendor/logs)
$main_size = 0;
$main_count = 0;
$dir = realpath($source_root);
if ($dir) {
    $exclude_paths = array_map(function($path) use ($dir) {
        return realpath($dir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
    }, $exclude_dirs);
    $exclude_paths = array_filter($exclude_paths);

    $directory_iterator = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
    $filter_iterator = new RecursiveCallbackFilterIterator($directory_iterator, function ($current, $key, $iterator) use ($exclude_paths) {
        $pathname = $current->getPathname();
        foreach ($exclude_paths as $ex_path) {
            if (strpos($pathname, $ex_path) === 0) return false;
        }
        return true;
    });

    $iterator = new RecursiveIteratorIterator($filter_iterator);
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $main_size += $file->getSize();
            $main_count++;
        }
    }
}
$dir_info = [
    'Project Files' => ['size' => round($main_size / 1024 / 1024, 2), 'count' => $main_count]
];

include $_SERVER['DOCUMENT_ROOT'] . '/portal/include/header.php';
include $_SERVER['DOCUMENT_ROOT'] . '/portal/include/sidebar.php';
?>



    <div class="container-fluid">

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Source Info -->
        <div class="row mb-4">
            <?php foreach ($dir_info as $name => $info): ?>
                <div class="col-md-6">
                    <div class="card bg-light">
                        <div class="card-body">
                            <h5><i class="fas fa-folder-open text-warning"></i>
                                <?php echo ucfirst(str_replace('_', ' ', $name)); ?>
                            </h5>
                            <p class="mb-0">
                                <strong>
                                    <?php echo $info['count']; ?>
                                </strong> files |
                                <strong>
                                    <?php echo $info['size']; ?> MB
                                </strong>
                            </p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Backup Now Card -->
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-plus-circle"></i> Create New Backup</h3>
            </div>
            <div class="card-body">
                <form method="POST" class="row g-3 align-items-end">
                    <input type="hidden" name="action" value="backup">
                    <div class="col-md-4">
                        <label class="form-label">Backup Type</label>
                        <select name="backup_type" class="form-select">
                            <option value="daily">Daily</option>
                            <option value="monthly">Monthly</option>
                            <option value="yearly">Yearly</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Backup Location</label>
                        <input type="text" class="form-control" value="D:/portal_backups/files/" readonly>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-folder"></i> Backup Files Now
                        </button>
                    </div>
                </form>
                <div class="alert alert-info mt-3 mb-0">
                    <i class="fas fa-info-circle"></i> This will create a ZIP file containing all project files excluding 
                    <strong><?php echo implode(', ', $exclude_dirs); ?></strong>.
                </div>
            </div>
        </div>

        <!-- Existing Backups -->
        <div class="card">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="tab" href="#daily">
                            Daily <span class="badge bg-primary">
                                <?php echo count($backups['daily']); ?>
                            </span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#monthly">
                            Monthly <span class="badge bg-success">
                                <?php echo count($backups['monthly']); ?>
                            </span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#yearly">
                            Yearly <span class="badge bg-warning">
                                <?php echo count($backups['yearly']); ?>
                            </span>
                        </a>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content">
                    <?php foreach (['daily', 'monthly', 'yearly'] as $type): ?>
                        <div class="tab-pane fade <?php echo $type === 'daily' ? 'show active' : ''; ?>"
                            id="<?php echo $type; ?>">
                            <?php if (empty($backups[$type])): ?>
                                <div class="alert alert-info">No
                                    <?php echo $type; ?> backups found.
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Filename</th>
                                                <th>Size</th>
                                                <th>Created</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($backups[$type] as $backup): ?>
                                                <tr>
                                                    <td><i class="fas fa-file-archive text-warning"></i>
                                                        <?php echo $backup['name']; ?>
                                                    </td>
                                                    <td>
                                                        <?php echo $backup['size']; ?> MB
                                                    </td>
                                                    <td>
                                                        <?php echo $backup['date']; ?>
                                                    </td>
                                                    <td>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="action" value="download">
                                                            <input type="hidden" name="type" value="<?php echo $type; ?>">
                                                            <input type="hidden" name="file" value="<?php echo $backup['name']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-success">
                                                                <i class="fas fa-download"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" class="d-inline"
                                                            onsubmit="return confirm('Delete this backup?');">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="type" value="<?php echo $type; ?>">
                                                            <input type="hidden" name="file" value="<?php echo $backup['name']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-danger">
                                                                <i class="fas fa-trash"></i>
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
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        </div>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/portal/include/footer.php'; ?>
