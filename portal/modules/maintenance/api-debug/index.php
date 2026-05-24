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

$page_title = "API Logs";

// Get API logs from database (if table exists)
$api_logs = [];
$has_table = false;

try {
    $check = $conn->query("SHOW TABLES LIKE 'tbl_api_logs'");
    $has_table = $check->rowCount() > 0;

    if ($has_table) {
        $stmt = $conn->query("SELECT * FROM tbl_api_logs ORDER BY created_at ASC LIMIT 100");
        $api_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    // Table doesn't exist
}

include $_SERVER['DOCUMENT_ROOT'] . '/portal/include/header.php';
include $_SERVER['DOCUMENT_ROOT'] . '/portal/include/sidebar.php';
?>



    <div class="container-fluid">

        <?php if (!$has_table): ?>
            <div class="alert alert-warning">
                <h5><i class="fas fa-exclamation-triangle"></i> API Logging Not Configured</h5>
                <p>The API logging table (<code>tbl_api_logs</code>) does not exist. Run the following SQL to create it:</p>
                <pre class="bg-dark text-light p-3 rounded">CREATE TABLE tbl_api_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                endpoint VARCHAR(255),
                method ENUM('GET', 'POST', 'PUT', 'DELETE'),
                request_headers TEXT,
                request_body TEXT,
                response_code INT,
                response_body TEXT,
                response_time_ms INT,
                ip_address VARCHAR(45),
                user_id INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_endpoint (endpoint),
                INDEX idx_created_at (created_at)
            );</pre>
            </div>
        <?php else: ?>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-list"></i> Recent API Calls</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($api_logs)): ?>
                        <div class="alert alert-info">No API logs found yet. API calls will appear here once logged.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Method</th>
                                        <th>Endpoint</th>
                                        <th>Response</th>
                                        <th>Time (ms)</th>
                                        <th>IP</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($api_logs as $log): ?>
                                        <tr>
                                            <td>
                                                <?php echo date('d-M H:i:s', strtotime($log['created_at'])); ?>
                                            </td>
                                            <td>
                                                <span
                                                    class="badge bg-<?php echo $log['method'] === 'GET' ? 'success' : ($log['method'] === 'POST' ? 'primary' : 'warning'); ?>">
                                                    <?php echo $log['method']; ?>
                                                </span>
                                            </td>
                                            <td><code><?php echo $log['endpoint']; ?></code></td>
                                            <td>
                                                <span
                                                    class="badge bg-<?php echo $log['response_code'] >= 200 && $log['response_code'] < 300 ? 'success' : 'danger'; ?>">
                                                    <?php echo $log['response_code']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo $log['response_time_ms']; ?>ms
                                            </td>
                                            <td><small>
                                                    <?php echo $log['ip_address']; ?>
                                                </small></td>
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


