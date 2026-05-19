<?php
/**
 * Refund Report
 * All refunds issued
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

$page_title = "Refund Report";

$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date = $_GET['to_date'] ?? date('Y-m-d');

$dbOps = new DatabaseOperations();

$refunds = $dbOps->customSelect(
    "SELECT r.id, r.refund_amount as amount, r.refund_reason as reason, 
            r.request_status as status, r.created_at, r.student_id,
            CONCAT(s.surname, ' ', s.student_name, ' ', IFNULL(s.fathers_name, '')) as student_name,
            s.mob as mobile
     FROM tbl_refund_requests r
     LEFT JOIN tbl_gm_std_registration s ON r.student_id = s.id
     WHERE DATE(r.created_at) BETWEEN ? AND ?
     ORDER BY r.created_at ASC",
    [$from_date, $to_date]
);

$totalRefunds = array_sum(array_column($refunds, 'amount'));

include '../../../include/header.php';
include '../../../include/navbar.php';
include '../../../include/sidebar.php';
?>

<style>
    .filter-card {
        background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        border: none;
        border-radius: 12px;
    }

    .filter-card .form-label {
        color: white;
        font-weight: 500;
    }
</style>

<div class="container-fluid">
    <div class="card filter-card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">From Date</label>
                    <input type="date" name="from_date" class="form-control" value="<?php echo $from_date; ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">To Date</label>
                    <input type="date" name="to_date" class="form-control" value="<?php echo $to_date; ?>">
                </div>
                <div class="col-md-4 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-light"><i class="fas fa-filter"></i> Apply</button>
                    <a href="refunds.php" class="btn btn-outline-light"><i class="fas fa-redo"></i></a>
                </div>
            </form>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card bg-danger text-white">
                <div class="card-body text-center">
                    <h3>₹
                        <?php echo formatIndianCurrency($totalRefunds); ?>
                    </h3>
                    <p class="mb-0">Total Refunds</p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card bg-secondary text-white">
                <div class="card-body text-center">
                    <h3>
                        <?php echo count($refunds); ?>
                    </h3>
                    <p class="mb-0">Refund Transactions</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0"><i class="fas fa-list me-2"></i>Refund Transactions</h5>
            <div class="d-flex gap-2">
                <button class="btn btn-success btn-sm" onclick="exportToExcel()">
                    <i class="fas fa-file-excel me-1"></i> Excel
                </button>
                <button class="btn btn-light btn-sm" onclick="exportToPDF()">
                    <i class="fas fa-file-pdf me-1"></i> Export PDF
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Student</th>
                            <th>Amount</th>
                            <th>Reason</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($refunds)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <i class="fas fa-check-circle text-success fa-3x mb-3"></i>
                                    <p>No refunds in selected period</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $sno = 1;
                            foreach ($refunds as $ref): ?>
                                <tr>
                                    <td>
                                        <?php echo $sno++; ?>
                                    </td>
                                    <td>
                                        <?php echo date('d-m-Y', strtotime($ref['created_at'])); ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($ref['student_name'] ?? '-'); ?>
                                    </td>
                                    <td class="text-danger">₹
                                        <?php echo formatIndianCurrency($ref['amount']); ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($ref['reason'] ?? '-'); ?>
                                    </td>
                                    <td><span
                                            class="badge bg-<?php echo ($ref['status'] ?? '') == 'completed' ? 'success' : 'warning'; ?>">
                                            <?php echo strtoupper($ref['status'] ?? 'pending'); ?>
                                        </span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
    function exportToExcel() {
        var table = document.querySelector('table');
        var wb = XLSX.utils.table_to_book(table, { sheet: "Refunds" });
        XLSX.writeFile(wb, 'Refund_Report_<?php echo $from_date; ?>_to_<?php echo $to_date; ?>.xlsx');
    }

    function exportToPDF() {
        const params = new URLSearchParams(window.location.search);
        window.location.href = 'refunds-pdf.php?' + params.toString();
    }
</script>

<?php include '../../../include/footer.php'; ?>