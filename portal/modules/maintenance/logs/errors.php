<?php
require_once dirname(__DIR__, 3) . '/session_config.php';
require_once dirname(__DIR__, 4) . '/common/constants.php';
require_once PORTAL_GLOBALVARIABLE;

// Check if user is Maintenance Admin or Super Admin
if (!hasRole(ROLE_MAINTENANCE) && !hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "Error Logs";

// Log file paths
$log_files = [
    'PHP Errors' => 'C:/xampp/php/logs/php_error_log',
    'Apache Errors' => 'C:/xampp/apache/logs/error.log',
    'Application Logs' => COMMON_PATH . 'logs/application.log'
];

$selected_log = $_GET['log'] ?? 'PHP Errors';
$log_file = $log_files[$selected_log] ?? '';
$lines = intval($_GET['lines'] ?? 100);
$log_content = [];

if (file_exists($log_file)) {
    $content = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $log_content = array_slice(array_reverse($content), 0, $lines);
}

include $_SERVER['DOCUMENT_ROOT'] . '/portal/include/header.php';
include $_SERVER['DOCUMENT_ROOT'] . '/portal/include/sidebar.php';
?>

<link rel="stylesheet" href="<?php echo PORTAL_URL; ?>/assets/css/modules/maintenance/logs/errors.css">

    <div class="container-fluid">

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Log File</label>
                        <select name="log" class="form-select">
                            <?php foreach (array_keys($log_files) as $log): ?>
                                <option value="<?php echo $log; ?>" <?php echo $selected_log === $log ? 'selected' : ''; ?>>
                                    <?php echo $log; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Lines to Show</label>
                        <select name="lines" class="form-select">
                            <option value="50" <?php echo $lines === 50 ? 'selected' : ''; ?>>50 lines</option>
                            <option value="100" <?php echo $lines === 100 ? 'selected' : ''; ?>>100 lines</option>
                            <option value="200" <?php echo $lines === 200 ? 'selected' : ''; ?>>200 lines</option>
                            <option value="500" <?php echo $lines === 500 ? 'selected' : ''; ?>>500 lines</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Apply</button>
                        <a href="" class="btn btn-secondary"><i class="fas fa-sync"></i> Refresh</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Log Content -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-file-alt"></i>
                    <?php echo $selected_log; ?>
                </h3>
                <span class="float-end text-muted small">
                    <?php echo file_exists($log_file) ? 'File: ' . $log_file : 'File not found'; ?>
                </span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($log_content)): ?>
                    <div class="alert alert-info m-3">No log entries found or file does not exist.</div>
                <?php else: ?>
                    <div class="log-viewer errors-custom-1">
                        <?php foreach ($log_content as $line): ?>
                            <?php
                            $class = 'text-white';
                            if (stripos($line, 'error') !== false || stripos($line, 'fatal') !== false)
                                $class = 'text-danger';
                            elseif (stripos($line, 'warning') !== false)
                                $class = 'text-warning';
                            elseif (stripos($line, 'notice') !== false)
                                $class = 'text-info';
                            ?>
                            <div class="<?php echo $class; ?> errors-custom-2">
                                <?php echo htmlspecialchars($line ?? ''); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        </div>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/portal/include/footer.php'; ?>
