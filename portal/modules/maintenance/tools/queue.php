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

$page_title = "Email/SMS Queue";

// Check if queue table exists
$has_table = false;
$queue_items = [];

try {
    $check = $conn->query("SHOW TABLES LIKE 'tbl_notification_queue'");
    $has_table = $check->rowCount() > 0;

    if ($has_table) {
        $queue_items = $conn->query("SELECT * FROM tbl_notification_queue ORDER BY created_at DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
}

include $_SERVER['DOCUMENT_ROOT'] . '/portal/include/header.php';
include $_SERVER['DOCUMENT_ROOT'] . '/portal/include/sidebar.php';
?>



    <div class="container-fluid">

        <?php if (!$has_table): ?>
            <div class="alert alert-warning">
                <h5><i class="fas fa-exclamation-triangle"></i> Queue Table Not Found</h5>
                <p>Create the notification queue table with:</p>
                <pre class="bg-dark text-light p-3 rounded">CREATE TABLE tbl_notification_queue (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type ENUM('email', 'sms', 'whatsapp'),
            recipient VARCHAR(255),
            subject VARCHAR(255),
            message TEXT,
            status ENUM('pending', 'sent', 'failed'),
            retry_count INT DEFAULT 0,
            error_message TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            sent_at TIMESTAMP NULL,
            INDEX idx_status (status)
        );</pre>
            </div>
        <?php else: ?>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-list"></i> Queue Items</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($queue_items)): ?>
                        <div class="alert alert-info">Queue is empty. Notifications will appear here when queued.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Recipient</th>
                                        <th>Subject</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($queue_items as $item): ?>
                                        <tr>
                                            <td><span
                                                    class="badge bg-<?php echo $item['type'] === 'email' ? 'primary' : 'success'; ?>">
                                                    <?php echo $item['type']; ?>
                                                </span></td>
                                            <td>
                                                <?php echo $item['recipient']; ?>
                                            </td>
                                            <td>
                                                <?php echo $item['subject']; ?>
                                            </td>
                                            <td><span
                                                    class="badge bg-<?php echo $item['status'] === 'sent' ? 'success' : ($item['status'] === 'failed' ? 'danger' : 'warning'); ?>">
                                                    <?php echo $item['status']; ?>
                                                </span></td>
                                            <td>
                                                <?php echo date('d-M H:i', strtotime($item['created_at'])); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php endif; ?>

        </div>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/portal/include/footer.php'; ?>


