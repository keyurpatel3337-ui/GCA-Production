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

$page_title = "Cron Jobs Manager";

// Predefined cron jobs
$cron_jobs = [
    [
        'name' => 'Daily Database Backup',
        'schedule' => 'Daily at 2:00 AM',
        'script' => 'portal/cron/backup_cron.php --type=daily --target=database',
        'status' => 'active'
    ],
    [
        'name' => 'Daily Files Backup',
        'schedule' => 'Daily at 2:30 AM',
        'script' => 'portal/cron/backup_cron.php --type=daily --target=files',
        'status' => 'active'
    ],
    [
        'name' => 'Daily Receipt Report',
        'schedule' => 'Daily at 11:55 PM',
        'script' => 'portal/cron/receipt_report_cron.php --type=daily',
        'status' => 'active'
    ],
    [
        'name' => 'Monthly Receipt Report',
        'schedule' => '1st of month at 12:30 AM',
        'script' => 'portal/cron/receipt_report_cron.php --type=monthly',
        'status' => 'active'
    ],
    [
        'name' => 'Weekly Cleanup',
        'schedule' => 'Sunday at 3:00 AM',
        'script' => 'portal/cron/cleanup_cron.php',
        'status' => 'active'
    ],
    [
        'name' => 'Health Check',
        'schedule' => 'Every 30 minutes',
        'script' => 'portal/cron/health_check_cron.php',
        'status' => 'pending'
    ]
];

include $_SERVER['DOCUMENT_ROOT'] . '/portal/include/header.php';
include $_SERVER['DOCUMENT_ROOT'] . '/portal/include/sidebar.php';
?>



    <div class="container-fluid">

        <div class="alert alert-info">
            <h5><i class="fas fa-info-circle"></i> Windows Task Scheduler Setup</h5>
            <p class="mb-0">On Windows, use Task Scheduler to run these scripts. Create a new task with:</p>
            <ul class="mb-0">
                <li><strong>Program:</strong> <code>C:\xampp\php\php.exe</code></li>
                <li><strong>Arguments:</strong> <code>C:\xampp\htdocs\[script_path]</code></li>
            </ul>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-list"></i> Scheduled Jobs</h3>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Job Name</th>
                                <th>Schedule</th>
                                <th>Script</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cron_jobs as $job): ?>
                                <tr>
                                    <td><strong>
                                            <?php echo $job['name']; ?>
                                        </strong></td>
                                    <td><span class="badge bg-secondary">
                                            <?php echo $job['schedule']; ?>
                                        </span></td>
                                    <td><code><?php echo $job['script']; ?></code></td>
                                    <td>
                                        <span
                                            class="badge bg-<?php echo $job['status'] === 'active' ? 'success' : 'warning'; ?>">
                                            <?php echo ucfirst($job['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-terminal"></i> Manual Execution</h3>
            </div>
            <div class="card-body">
                <p>To manually run a cron job, open Command Prompt and run:</p>
                <pre class="bg-dark text-light p-3 rounded">cd C:\xampp\htdocs
C:\xampp\php\php.exe portal/cron/backup_cron.php --type=daily --target=database</pre>
            </div>
        </div>

        </div>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/portal/include/footer.php'; ?>
