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

$page_title = "Configuration";

// Get configurations from various sources
$configs = [];

// SMTP Config
try {
    $smtp = $conn->query("SELECT * FROM tbl_smtp_config LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($smtp) {
        $configs['SMTP'] = [
            'Host' => $smtp['host'] ?? 'Not configured',
            'Port' => $smtp['port'] ?? 'N/A',
            'Username' => $smtp['username'] ?? 'N/A',
            'Encryption' => $smtp['encryption'] ?? 'None',
            'Status' => ($smtp['is_active'] ?? 0) ? 'Active' : 'Inactive'
        ];
    }
} catch (Exception $e) {
}

// Payment Gateway
try {
    $pg = $conn->query("SELECT * FROM tbl_payment_gateways WHERE is_active = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($pg) {
        $configs['Payment Gateway'] = [
            'Name' => $pg['name'] ?? 'Unknown',
            'Environment' => $pg['environment'] ?? 'test',
            'Status' => 'Active'
        ];
    }
} catch (Exception $e) {
}

// System Settings
$configs['System'] = [
    'Name' => SYSTEM_NAME ?? 'N/A',
    'Short Name' => SYSTEM_SHORT_NAME ?? 'N/A',
    'Environment' => ENVIRONMENT ?? 'production',
    'Base URL' => BASE_URL
];

// Paths
$configs['Paths'] = [
    'Root' => ROOT_PATH,
    'Portal' => PORTAL_PATH,
    'Uploads' => UPLOADS_PATH,
    'Logs' => LOGS_PATH
];

// Backup
$configs['Backup'] = [
    'Database Path' => 'D:/portal_backups/database',
    'Files Path' => 'D:/portal_backups/files',
    'Reports Path' => 'D:/portal_backups/receipt_reports'
];

include $_SERVER['DOCUMENT_ROOT'] . '/portal/include/header.php';
include $_SERVER['DOCUMENT_ROOT'] . '/portal/include/sidebar.php';
?>



    <div class="container-fluid">

        <div class="row">
            <?php foreach ($configs as $section => $items): ?>
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-cog"></i>
                                <?php echo $section; ?>
                            </h3>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-striped mb-0">
                                <?php foreach ($items as $key => $value): ?>
                                    <tr>
                                        <td width="40%"><strong>
                                                <?php echo $key; ?>
                                            </strong></td>
                                        <td>
                                            <?php if (strpos($key, 'Password') !== false || strpos($key, 'Key') !== false): ?>
                                                <code>********</code>
                                            <?php else: ?>
                                                <code><?php echo htmlspecialchars($value ?? ''); ?></code>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="alert alert-warning mt-3">
            <i class="fas fa-exclamation-triangle"></i> Configuration is view-only for safety. To modify settings, use
            the respective Settings pages in the admin panel.
        </div>

        </div>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/portal/include/footer.php'; ?>
