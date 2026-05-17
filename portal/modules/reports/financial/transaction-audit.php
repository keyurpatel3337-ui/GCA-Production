<?php
/**
 * Transaction Audit Log
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

$page_title = "Transaction Audit";
$from_date = $_GET['from_date'] ?? date('Y-m-d');
$to_date = $_GET['to_date'] ?? date('Y-m-d');

$dbOps = new DatabaseOperations();
$audit = $dbOps->customSelect(
    "SELECT pt.*, CONCAT(r.surname, ' ', r.student_name) as student_name, u.name as user_name
     FROM tbl_payment_transactions pt
     LEFT JOIN tbl_gm_std_registration r ON pt.student_id = r.id
     LEFT JOIN tbl_users u ON pt.processed_by = u.id
     WHERE DATE(pt.created_at) BETWEEN ? AND ?
     ORDER BY pt.created_at ASC", [$from_date, $to_date]
);

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
                    <button type="submit" class="btn btn-light"><i class="fas fa-search"></i> Search</button>
                    <button type="button" class="btn btn-danger" onclick="exportToPDF()"><i class="fas fa-file-pdf"></i> Export PDF</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-dark text-white"><h5 class="card-title mb-0">Audit Trail</h5></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-dark">
                        <tr><th>Time</th><th>Action</th><th>Payment ID</th><th>Student</th><th>Amount</th><th>User</th><th>IP</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($audit)): ?>
                            <tr><td colspan="7" class="text-center py-5">No records found</td></tr>
                        <?php else: ?>
                            <?php foreach ($audit as $a): ?>
                                <tr>
                                    <td><small><?php echo date('d M H:i:s', strtotime($a['created_at'])); ?></small></td>
                                    <td><span class="badge bg-info"><?php echo strtoupper($a['action'] ?? '-'); ?></span></td>
                                    <td><code><?php echo htmlspecialchars($a['payment_id'] ?? '-'); ?></code></td>
                                    <td><?php echo htmlspecialchars($a['student_name'] ?? '-'); ?></td>
                                    <td>₹<?php echo formatIndianCurrency($a['amount'] ?? 0); ?></td>
                                    <td><?php echo htmlspecialchars($a['user_name'] ?? 'System'); ?></td>
                                    <td><small class="text-muted"><?php echo htmlspecialchars($a['ip_address'] ?? '-'); ?></small></td>
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
        window.location.href = 'transaction-audit-pdf.php?' + params.toString();
    }
</script>

<?php include '../../../include/footer.php'; ?>

