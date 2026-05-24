<?php
/**
 * Gateway Transactions Report
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

$page_title = "Gateway Transactions" ;
$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date = $_GET['to_date'] ?? date('Y-m-d');

$dbOps = new DatabaseOperations();
$transactions = $dbOps->customSelect(
    "SELECT po.*, CONCAT(r.surname, ' ', r.student_name) as student_name
     FROM tbl_payment_orders po
     LEFT JOIN tbl_gm_std_registration r ON po.student_id = r.id
     WHERE DATE(po.created_at) BETWEEN ? AND ?
     ORDER BY po.created_at ASC", [$from_date, $to_date]
);

$successful = array_filter($transactions, fn($t) => ($t['status'] ?? '') == 'paid');
$failed = array_filter($transactions, fn($t) => in_array($t['status'] ?? '', ['failed', 'error']));

include '../../../include/header.php';
include '../../../include/navbar.php';
include '../../../include/sidebar.php';
?>

<div class="container-fluid">
    <div class="card mb-4 bg-dark py-3">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label text-white">From Date</label>
                    <input type="date" name="from_date" class="form-control" value="<?php echo $from_date; ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label text-white">To Date</label>
                    <input type="date" name="to_date" class="form-control" value="<?php echo $to_date; ?>">
                </div>
                <div class="col-md-4 d-flex align-items-end gap-1">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Apply</button>
                    <button type="button" class="btn btn-danger" onclick="exportToPDF()"><i class="fas fa-file-pdf"></i> Export PDF</button>
                    <a href="gateway-transactions.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-4"><div class="card bg-primary text-white text-center p-3"><h3><?php echo count($transactions); ?></h3><p class="mb-0">Total Orders</p></div></div>
        <div class="col-md-4"><div class="card bg-success text-white text-center p-3"><h3><?php echo count($successful); ?></h3><p class="mb-0">Successful</p></div></div>
        <div class="col-md-4"><div class="card bg-danger text-white text-center p-3"><h3><?php echo count($failed); ?></h3><p class="mb-0">Failed/Error</p></div></div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr><th>#</th><th>Date</th><th>Order ID</th><th>Student</th><th>Amount</th><th>Gateway</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transactions)): ?>
                            <tr><td colspan="7" class="text-center py-5">No records found</td></tr>
                        <?php else: ?>
                            <?php $sno = 1; foreach ($transactions as $t): ?>
                                <tr>
                                    <td><?php echo $sno++; ?></td>
                                    <td><?php echo date('d M Y H:i', strtotime($t['created_at'])); ?></td>
                                    <td><code><?php echo htmlspecialchars($t['order_id'] ?? '-'); ?></code></td>
                                    <td><?php echo htmlspecialchars($t['student_name'] ?? '-'); ?></td>
                                    <td>₹<?php echo formatIndianCurrency($t['amount'] ?? 0); ?></td>
                                    <td><span class="badge bg-info"><?php echo htmlspecialchars($t['gateway'] ?? 'Easebuzz'); ?></span></td>
                                    <td><?php $status = $t['status'] ?? 'pending'; $badge = $status == 'paid' ? 'success' : ($status == 'pending' ? 'warning' : 'danger'); ?>
                                        <span class="badge bg-<?php echo $badge; ?>"><?php echo strtoupper($status); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    function exportToPDF() {
        const params = new URLSearchParams(window.location.search);
        window.location.href = 'gateway-transactions-pdf.php?' + params.toString();
    }
</script>

<?php include '../../../include/footer.php'; ?>


