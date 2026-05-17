<?php
if (PHP_SAPI !== 'cli') {
    require_once dirname(__DIR__, 3) . '/session_config.php';
} else {
    // Manually define essentials for CLI if session_config is skipped
    require_once dirname(__DIR__, 4) . '/common/constants.php';
    if (!defined('ENVIRONMENT'))
        define('ENVIRONMENT', 'development');
}

require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;
require_once dirname(__DIR__, 4) . '/common/helpers/fee_allocation_helper.php';

// Check if user is Maintenance Admin or Super Admin (Skip if CLI)
if (PHP_SAPI !== 'cli') {
    if (!hasRole(ROLE_MAINTENANCE) && !hasRole(ROLE_SUPER_ADMIN)) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

$page_title = "Sync Fee Allocations";
$message = "";
$status = "";

// Handle CLI execution
if (PHP_SAPI === 'cli' || ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_sync']))) {
    try {
        if (isset($conn)) {
            $conn->beginTransaction();

            // Get all active enrolled students
            $stmt = $conn->query("SELECT registration_id FROM tbl_enrolled_students WHERE is_active = 1");
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $total = count($students);
            if (PHP_SAPI === 'cli')
                echo "Found $total active students to sync.\n";
            $success_count = 0;
            $fail_count = 0;

            foreach ($students as $student) {
                if (PHP_SAPI === 'cli')
                    echo "Syncing student ID: " . $student['registration_id'] . "... ";
                $res = syncStudentFeeAllocation($conn, $student['registration_id']);
                if ($res && $res['success']) {
                    $success_count++;
                    if (PHP_SAPI === 'cli')
                        echo "Done.\n";
                } else {
                    $fail_count++;
                    if (PHP_SAPI === 'cli')
                        echo "Failed: " . ($res['message'] ?? 'Unknown error') . "\n";
                }
            }

            $conn->commit();
            $status = "success";
            $message = "Synchronization complete! Processed $total students. Success: $success_count, Failed: $fail_count.";

            if (PHP_SAPI === 'cli') {
                echo $message . "\n";
            }
        }
    } catch (Exception $e) {
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollBack();
        }
        $status = "error";
        $message = "Error during sync: " . $e->getMessage();
        if (function_exists('logError')) {
            logError($message);
        }
        if (PHP_SAPI === 'cli') {
            echo $message . "\n";
        }
    }
}

if (PHP_SAPI === 'cli') {
    exit;
}

$doc_root = $_SERVER['DOCUMENT_ROOT'];
if (empty($doc_root)) {
    $doc_root = dirname(__DIR__, 4); // Fallback for some local setups
}

include $doc_root . '/portal/include/header.php';
include $doc_root . '/portal/include/sidebar.php';
?>

<div class="container-fluid">
    <div class="row mb-2">
        <div class="col-sm-6">
            <h1 class="m-0 text-dark">
                <?php echo $page_title; ?>
            </h1>
        </div>
        <div class="col-sm-6 text-end">
            <a href="../index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Maintenance
            </a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $status === 'success' ? 'success' : 'danger'; ?> alert-dismissible">
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            <h5><i class="icon fas fa-<?php echo $status === 'success' ? 'check' : 'ban'; ?>"></i>
                <?php echo $status === 'success' ? 'Success' : 'Error'; ?>!
            </h5>
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-8">
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title">Fee Allocation Re-sync Tool</h3>
                </div>
                <div class="card-body">
                    <p class="lead">This tool will recalculate the <code>pending_amount</code> and
                        <code>scholarship_amount</code> for all active enrolled students.
                    </p>

                    <div class="alert alert-info">
                        <h5><i class="icon fas fa-info"></i> Why use this?</h5>
                        <ul>
                            <li>Fixes discrepancies between Dashboard and Reports.</li>
                            <li>Ensures all scholarship/discount updates are reflected in the database.</li>
                            <li>Recommended after bulk data imports or database migrations.</li>
                        </ul>
                    </div>

                    <p><strong>Note:</strong> This process might take a few moments depending on the number of students.
                        Please do not close the window during the process.</p>
                </div>
                <div class="card-footer">
                    <form method="POST">
                        <button type="submit" name="start_sync" class="btn btn-primary btn-lg"
                            onclick="return confirm('Starting synchronization for all students. Proceed?')">
                            <i class="fas fa-sync-alt"></i> Start Global Sync
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card card-warning">
                <div class="card-header">
                    <h3 class="card-title">Safety Check</h3>
                </div>
                <div class="card-body">
                    <p>Before running, it is always recommended to take a database backup.</p>
                    <a href="../backup/database.php" class="btn btn-outline-warning w-100">
                        <i class="fas fa-database"></i> Go to Backup Page
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/portal/include/footer.php'; ?>