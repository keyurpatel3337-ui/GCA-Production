<?php
/**
 * Failed Online Transactions
 */

require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/common/constants.php';
require_once __DIR__ . '/../../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once __DIR__ . '/../../../../common/helpers/format_helper.php';

if (!hasRole(ROLE_ACCOUNTANT) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "Failed Transactions";

$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date = $_GET['to_date'] ?? date('Y-m-d');

$dbOps = new DatabaseOperations();

$failed = $dbOps->customSelect(
    "SELECT po.*, CONCAT(r.surname, ' ', r.student_name, ' ', IFNULL(r.fathers_name, '')) as student_name
     FROM tbl_payment_orders po
     LEFT JOIN tbl_gm_std_registration r ON po.student_id = r.id
     WHERE po.status IN ('failed', 'error', 'timeout')
     AND DATE(po.created_at) BETWEEN ? AND ?
     ORDER BY po.created_at ASC",
    [$from_date, $to_date]
);

include '../../../include/header.php';
include '../../../include/navbar.php';
include '../../../include/sidebar.php';
?>



<div class="container-fluid">
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Order ID</th>
                            <th>Student</th>
                            <th>Amount</th>
                            <th>Gateway</th>
                            <th>Error</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($failed)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5"><i
                                        class="fas fa-check-circle text-success fa-3x mb-3"></i>
                                    <p>No failed transactions</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $sno = 1;
                            foreach ($failed as $f): ?>
                                <tr>
                                    <td><?php echo $sno++; ?></td>
                                    <td><?php echo date('d M Y H:i', strtotime($f['created_at'])); ?></td>
                                    <td><code><?php echo htmlspecialchars($f['order_id'] ?? '-'); ?></code></td>
                                    <td><?php echo htmlspecialchars($f['student_name'] ?? '-'); ?></td>
                                    <td>₹<?php echo formatIndianCurrency($f['amount'] ?? 0); ?></td>
                                    <td><?php echo htmlspecialchars($f['gateway'] ?? '-'); ?></td>
                                    <td><small
                                            class="text-danger"><?php echo htmlspecialchars($f['error_message'] ?? '-'); ?></small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../../../include/footer.php'; ?>