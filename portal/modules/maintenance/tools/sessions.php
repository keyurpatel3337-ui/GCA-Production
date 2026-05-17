<?php
require_once dirname(__DIR__, 3) . '/session_config.php';
require_once dirname(__DIR__, 4) . '/common/constants.php';
require_once PORTAL_GLOBALVARIABLE;

// Check if user is Maintenance Admin or Super Admin
if (!hasRole(ROLE_MAINTENANCE) && !hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "Session Manager";

// Get session info
$session_path = session_save_path();
$sessions = [];

if (is_dir($session_path)) {
    $files = glob($session_path . '/sess_*');
    foreach ($files as $file) {
        $sessions[] = [
            'id' => str_replace('sess_', '', basename($file)),
            'size' => round(filesize($file) / 1024, 2),
            'modified' => date('d-M-Y H:i', filemtime($file))
        ];
    }
}

include $_SERVER['DOCUMENT_ROOT'] . '/portal/include/header.php';
include $_SERVER['DOCUMENT_ROOT'] . '/portal/include/sidebar.php';
?>

<link rel="stylesheet" href="<?php echo PORTAL_URL; ?>/assets/css/modules/maintenance/tools/sessions.css">

    <div class="container-fluid">

        <div class="row">
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-info-circle"></i> Session Info</h3>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-striped mb-0">
                            <tr>
                                <td>Session Path</td>
                                <td><code><?php echo $session_path; ?></code></td>
                            </tr>
                            <tr>
                                <td>Active Sessions</td>
                                <td><strong>
                                        <?php echo count($sessions); ?>
                                    </strong></td>
                            </tr>
                            <tr>
                                <td>Current Session ID</td>
                                <td><code><?php echo session_id(); ?></code></td>
                            </tr>
                            <tr>
                                <td>Session Lifetime</td>
                                <td>
                                    <?php echo ini_get('session.gc_maxlifetime'); ?> seconds
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-list"></i> Active Sessions</h3>
                    </div>
                    <div class="card-body p-0 sessions-custom-1">
                        <?php if (empty($sessions)): ?>
                            <div class="alert alert-info m-3">No session files found.</div>
                        <?php else: ?>
                            <table class="table table-striped table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Session ID</th>
                                        <th>Size</th>
                                        <th>Last Modified</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sessions as $sess): ?>
                                        <tr class="<?php echo $sess['id'] === session_id() ? 'table-success' : ''; ?>">
                                            <td><code><?php echo substr($sess['id'], 0, 20); ?>...</code>
                                                <?php echo $sess['id'] === session_id() ? '<span class="badge bg-primary">Current</span>' : ''; ?>
                                            </td>
                                            <td>
                                                <?php echo $sess['size']; ?> KB
                                            </td>
                                            <td>
                                                <?php echo $sess['modified']; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        </div>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/portal/include/footer.php'; ?>
