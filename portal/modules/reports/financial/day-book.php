<?php
/**
 * Day Book
 * Daily transaction log for accountants
 */

require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/common/constants.php';
require_once __DIR__ . '/../../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once __DIR__ . '/../../../../common/helpers/format_helper.php';
require_once PAGINATION_FILE;

// Check access
if (!hasRole(ROLE_ACCOUNTANT) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "Day Book";
$page_breadcrumb = "Day Book";

// Get selected date
$selected_date = $_GET['date'] ?? date('Y-m-d');

$dbOps = new DatabaseOperations();

// Pagination settings
$items_per_page = 10;
$current_page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($current_page - 1) * $items_per_page;

// 1. Get Summary Stats for the whole day (regardless of pagination)
$summarySql = "SELECT 
                    COUNT(*) as total_count,
                    SUM(amount) as grand_total,
                    SUM(CASE WHEN payment_mode = 'cash' THEN amount ELSE 0 END) as cash_total,
                    SUM(CASE WHEN payment_mode IN ('online', 'upi', 'card') THEN amount ELSE 0 END) as online_total,
                    SUM(CASE WHEN payment_mode = 'cheque' THEN amount ELSE 0 END) as cheque_total,
                    SUM(CASE WHEN payment_mode NOT IN ('cash', 'online', 'upi', 'card', 'cheque') THEN amount ELSE 0 END) as other_total
                FROM tbl_payments 
                WHERE status = 'paid' AND DATE(payment_date) = ?";

$summaryRes = $dbOps->customSelect($summarySql, [$selected_date]);
$stats = $summaryRes[0] ?? [];

$totalTransactions = $stats['total_count'] ?? 0;
$grandTotal = $stats['grand_total'] ?? 0;
$cashTotal = $stats['cash_total'] ?? 0;
$onlineTotal = $stats['online_total'] ?? 0;
$chequeTotal = $stats['cheque_total'] ?? 0;
$otherTotal = $stats['other_total'] ?? 0;

$total_pages = ceil($totalTransactions / $items_per_page);

// 2. Get ALL Transactions for Export
$export_sql = "SELECT 
            p.id, p.receipt_no, p.amount, p.payment_date, p.payment_mode, p.payment_type,
            p.created_at, u.name as collected_by,
            CONCAT(r.surname, ' ', r.student_name, ' ', IFNULL(r.fathers_name, '')) as student_name
        FROM tbl_payments p
        JOIN tbl_gm_std_registration r ON p.student_id = r.id
        LEFT JOIN tbl_users u ON p.created_by = u.id
        WHERE p.status = 'paid' AND DATE(p.payment_date) = ?
        ORDER BY p.created_at ASC";
$all_transactions = $dbOps->customSelect($export_sql, [$selected_date]);

// 3. Get Paginated Transactions
$sql = "SELECT 
            p.id,
            p.receipt_no,
            p.amount,
            p.payment_date,
            p.payment_mode,
            p.payment_type,
            p.transaction_id,
            p.fee_component,
            p.created_at,
            p.remarks,
            rc.receipt_title,
            '' as receipt_prefix,
            CONCAT(r.surname, ' ', r.student_name, ' ', IFNULL(r.fathers_name, '')) as student_name,
            r.mob as student_mobile,
            c.course_name as current_class,
            g.group_name,
            u.name as collected_by
        FROM tbl_payments p
        JOIN tbl_gm_std_registration r ON p.student_id = r.id
        LEFT JOIN tbl_enrolled_students es ON es.registration_id = r.id AND es.is_active = 1
        LEFT JOIN tbl_courses c ON r.course_id = c.id
        LEFT JOIN tbl_group g ON r.group_id = g.id
        LEFT JOIN tbl_receipt_configuration rc ON p.receipt_config_id = rc.id
        LEFT JOIN tbl_users u ON p.created_by = u.id
        WHERE p.status = 'paid'
        AND DATE(p.payment_date) = ?
        ORDER BY p.created_at ASC
        LIMIT ? OFFSET ?";

$transactions = $dbOps->customSelect($sql, [$selected_date, $items_per_page, $offset]);

// Calculate Page Total
$pageTotal = 0;
if (!empty($transactions)) {
    foreach ($transactions as $txn) {
        $pageTotal += $txn['amount'];
    }
}

// Get previous and next dates with transactions
$prevDateResult = $dbOps->customSelect(
    "SELECT MAX(payment_date) as prev_date FROM tbl_payments WHERE payment_date < ? AND status = 'paid'",
    [$selected_date]
);
$prevDate = $prevDateResult[0]['prev_date'] ?? null;

$nextDateResult = $dbOps->customSelect(
    "SELECT MIN(payment_date) as next_date FROM tbl_payments WHERE payment_date > ? AND status = 'paid'",
    [$selected_date]
);
$nextDate = $nextDateResult[0]['next_date'] ?? null;

// Opening Balance Calculation (Sum of all payments before current date)
$openingBalanceResult = $dbOps->customSelect(
    "SELECT SUM(amount) as opening_balance FROM tbl_payments WHERE payment_date < ? AND status = 'paid'",
    [$selected_date]
);
$openingBalance = $openingBalanceResult[0]['opening_balance'] ?? 0;
$closingBalance = $openingBalance + $grandTotal;

// AJAX Detection: Return only the table and pagination if ajax=1 is present AND it's a real AJAX request
$isAjax = isset($_GET['ajax']) && $_GET['ajax'] == 1 && 
          (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest');

include '../../../include/header.php';
include '../../../include/navbar.php';
include '../../../include/sidebar.php';
?>

<style>
    @media print {
        .no-print {
            display: none !important;
        }

        .card {
            border: 1px solid #ddd !important;
        }
    }
</style>



<div class="container-fluid">
    <!-- Date Navigation -->
    <div class="card card-enhanced mb-4 no-print">
        <div class="card-header bg-gradient-primary">
            <div class="d-flex align-items-center gap-3">
                <?php if ($prevDate): ?>
                    <a href="?date=<?php echo $prevDate; ?>" class="btn btn-light btn-sm">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php else: ?>
                    <button class="btn btn-light btn-sm" disabled><i class="fas fa-chevron-left"></i></button>
                <?php endif; ?>

                <div>
                    <h5 class="card-title text-white mb-0">
                        <i class="fas fa-calendar-day me-2"></i>
                        <?php echo date('l, d F Y', strtotime($selected_date)); ?>
                    </h5>
                    <small class="text-white-50">
                        <?php echo $totalTransactions; ?> transactions recorded
                    </small>
                </div>

                <?php if ($nextDate): ?>
                    <a href="?date=<?php echo $nextDate; ?>" class="btn btn-light btn-sm">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <button class="btn btn-light btn-sm" disabled><i class="fas fa-chevron-right"></i></button>
                <?php endif; ?>
            </div>

            <form method="GET" class="d-flex gap-2">
                <input type="date" name="date" class="form-control form-control-sm"
                    value="<?php echo $selected_date; ?>">
                <button type="submit" class="btn btn-light btn-sm">
                    <i class="fas fa-search"></i> Go
                </button>
                <a href="?date=<?php echo date('Y-m-d'); ?>" class="btn btn-light btn-sm">
                    <i class="fas fa-calendar-check me-1"></i> Today
                </a>
            </form>
        </div>
    </div>

    <!-- Print Header (visible only in print) -->
    <div class="d-none d-print-block text-center mb-4">
        <h3>
            <?php echo SYSTEM_NAME; ?>
        </h3>
        <h4>Day Book -
            <?php echo date('d F Y', strtotime($selected_date)); ?>
        </h4>
    </div>

    <!-- Summary Cards -->
    <div class="row g-4 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="glass-card">
                <div class="stat-card">
                    <div class="stat-top">
                        <div>
                            <div class="stat-value">₹<?php echo formatIndianCurrency($openingBalance); ?></div>
                            <div class="stat-label">Opening Balance</div>
                        </div>
                        <div class="stat-icon bg-icon-primary">
                            <i class="fas fa-wallet"></i>
                        </div>
                    </div>
                    <div class="stat-link text-primary">Carried forward from previous day</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="glass-card">
                <div class="stat-card">
                    <div class="stat-top">
                        <div>
                            <div class="stat-value">₹<?php echo formatIndianCurrency($grandTotal); ?></div>
                            <div class="stat-label">Today's Collection</div>
                        </div>
                        <div class="stat-icon bg-icon-success">
                            <i class="fas fa-indian-rupee-sign"></i>
                        </div>
                    </div>
                    <div class="stat-link text-success"><?php echo $totalTransactions; ?> transactions today</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="glass-card">
                <div class="stat-card">
                    <div class="stat-top">
                        <div>
                            <div class="stat-value">₹<?php echo formatIndianCurrency($closingBalance); ?></div>
                            <div class="stat-label">Closing Balance</div>
                        </div>
                        <div class="stat-icon bg-icon-purple">
                            <i class="fas fa-vault"></i>
                        </div>
                    </div>
                    <div class="stat-link text-info">End of day balance</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="glass-card">
                <div class="stat-card">
                    <div class="stat-top">
                        <div>
                            <div class="stat-value"><?php echo $totalTransactions; ?></div>
                            <div class="stat-label">Total Transactions</div>
                        </div>
                        <div class="stat-icon bg-icon-info">
                            <i class="fas fa-receipt"></i>
                        </div>
                    </div>
                    <div class="stat-link text-info">Receipts issued today</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Mode Breakdown -->
    <div class="row g-4 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="glass-card">
                <div class="stat-card">
                    <div class="stat-top">
                        <div>
                            <div class="stat-value">₹<?php echo formatIndianCurrency($cashTotal); ?></div>
                            <div class="stat-label">Cash Collection</div>
                        </div>
                        <div class="stat-icon bg-icon-success">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="glass-card">
                <div class="stat-card">
                    <div class="stat-top">
                        <div>
                            <div class="stat-value">₹<?php echo formatIndianCurrency($onlineTotal); ?></div>
                            <div class="stat-label">Online / UPI / Card</div>
                        </div>
                        <div class="stat-icon bg-icon-primary">
                            <i class="fas fa-globe"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="glass-card">
                <div class="stat-card">
                    <div class="stat-top">
                        <div>
                            <div class="stat-value">₹<?php echo formatIndianCurrency($chequeTotal); ?></div>
                            <div class="stat-label">Cheque</div>
                        </div>
                        <div class="stat-icon bg-icon-warning">
                            <i class="fas fa-money-check"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="glass-card">
                <div class="stat-card">
                    <div class="stat-top">
                        <div>
                            <div class="stat-value">₹<?php echo formatIndianCurrency($otherTotal); ?></div>
                            <div class="stat-label">Other Modes</div>
                        </div>
                        <div class="stat-icon bg-icon-danger">
                            <i class="fas fa-coins"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>



    <!-- Transactions Table -->
    <?php if ($isAjax) { ob_end_clean(); ob_start(); } ?>
    <div id="transaction-log-container">
        <div class="card card-enhanced">
            <div class="card-header bg-gradient-primary">
                <h5 class="card-title text-white mb-0">
                    <i class="fas fa-list-alt me-2"></i>
                    Transaction Log
                </h5>
                <div class="card-tools d-flex gap-2">
                    <div class="dropdown no-print">
                        <button type="button" class="btn btn-success btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="fab fa-whatsapp me-1"></i> WhatsApp Report
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow">
                            <li><a class="dropdown-item" href="javascript:void(0)" onclick="sendWhatsAppReport('simple')"><i class="fas fa-file-invoice me-2 text-success"></i>Simple Summary</a></li>
                            <li><a class="dropdown-item" href="javascript:void(0)" onclick="sendWhatsAppReport('detailed')"><i class="fas fa-list-ol me-2 text-primary"></i>Detailed Summary</a></li>
                            <li><a class="dropdown-item" href="javascript:void(0)" onclick="sendWhatsAppReport('coursewise')"><i class="fas fa-layer-group me-2 text-warning"></i>Course-wise Summary</a></li>
                        </ul>
                    </div>
                    <button class="btn btn-light btn-sm" onclick="exportToExcel()">
                        <i class="fas fa-file-excel me-1 text-success"></i> Excel
                    </button>
                    <button class="btn btn-light btn-sm" onclick="exportToPDF()">
                        <i class="fas fa-file-pdf me-1 text-danger"></i> Export PDF
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="dayBookTable">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Receipt No</th>
                                <th>Student</th>
                                <th>Class</th>
                                <th>Description</th>
                                <th>Mode</th>
                                <th class="text-end">Amount</th>
                                <th class="no-print">Collected By</th>
                                <th class="no-print text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($transactions)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-5">
                                        <i class="fas fa-calendar-times text-muted fa-3x mb-3"></i>
                                        <p class="mb-0">No transactions recorded on this date</p>
                                        <a href="?date=<?php echo date('Y-m-d'); ?>" class="btn btn-primary mt-3">
                                            Go to Today
                                        </a>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($transactions as $txn): ?>
                                    <tr>
                                        <td class="text-nowrap fw-medium text-muted">
                                            <i class="fas fa-clock me-1"></i>
                                            <?php echo date('h:i A', strtotime($txn['created_at'])); ?>
                                        </td>
                                        <td class="fw-bold">
                                            <?php echo htmlspecialchars(($txn['receipt_prefix'] ?? '') . $txn['receipt_no']); ?>
                                        </td>
                                        <td>
                                            <strong>
                                                <?php echo htmlspecialchars($txn['student_name'] ?? ''); ?>
                                            </strong>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($txn['student_mobile'] ?? ''); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($txn['current_class'] ?? '-'); ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo htmlspecialchars($txn['payment_type'] ?? ''); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $modeIcons = [
                                                'cash' => 'money-bill-wave text-success',
                                                'online' => 'globe text-primary',
                                                'upi' => 'mobile-alt text-info',
                                                'cheque' => 'money-check text-warning',
                                                'card' => 'credit-card text-secondary'
                                            ];
                                            $icon = $modeIcons[$txn['payment_mode']] ?? 'coins text-dark';
                                            ?>
                                            <i class="fas fa-<?php echo $icon; ?> me-1"></i>
                                            <?php echo strtoupper($txn['payment_mode']); ?>
                                        </td>
                                        <td class="text-end">
                                            <strong class="text-success">₹
                                                <?php echo formatIndianCurrency($txn['amount']); ?>
                                            </strong>
                                        </td>
                                        <td class="no-print">
                                            <small>
                                                <?php echo htmlspecialchars($txn['collected_by'] ?? 'System'); ?>
                                            </small>
                                        </td>
                                        <td class="no-print text-center">
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-sm btn-outline-primary"
                                                    onclick="downloadReceipt(<?php echo $txn['id']; ?>)" title="Print Receipt">
                                                    <i class="fas fa-print"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger"
                                                    onclick="cancelReceipt(<?php echo $txn['id']; ?>, '<?php echo htmlspecialchars($txn['receipt_no'] ?? ''); ?>')"
                                                    title="Cancel Receipt">
                                                    <i class="fas fa-ban"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <tfoot class="table-secondary">
                            <tr>
                                <th colspan="6" class="text-end">Page Total:</th>
                                <th class="text-end text-primary">₹
                                    <?php echo formatIndianCurrency($pageTotal); ?>
                                </th>
                                <th class="no-print"></th>
                                <th class="no-print"></th>
                            </tr>
                            <tr>
                                <th colspan="6" class="text-end">Day Total:</th>
                                <th class="text-end text-success">₹
                                    <?php echo formatIndianCurrency($grandTotal); ?>
                                </th>
                                <th class="no-print"></th>
                                <th class="no-print"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <!-- Pagination -->
            <div class="card-footer clearfix no-print">
                <?php
                $baseUrl = $_SERVER['PHP_SELF'] . '?' . http_build_query(array_diff_key($_GET, ['page' => '']));
                echo renderPagination($current_page, $total_pages, $baseUrl, 2, $totalTransactions, 'transactions');
                ?>
            </div>
        </div>
    </div>
    <?php 
    if ($isAjax) {
        echo ob_get_clean();
        exit;
    }
    ?>

    <!-- Hidden Table for Export -->
    <table class="d-none" id="dayBookExportTable">
        <thead>
            <tr>
                <th>Time</th>
                <th>Receipt No</th>
                <th>Student Name</th>
                <th>Payment Type</th>
                <th>Mode</th>
                <th>Amount</th>
                <th>Collected By</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($all_transactions as $at): ?>
                <tr>
                    <td><?php echo date('h:i A', strtotime($at['created_at'])); ?></td>
                    <td><?php echo htmlspecialchars($at['receipt_no'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($at['student_name'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($at['payment_type'] ?? ''); ?></td>
                    <td><?php echo strtoupper($at['payment_mode']); ?></td>
                    <td><?php echo round($at['amount']); ?></td>
                    <td><?php echo htmlspecialchars($at['collected_by'] ?? 'System'); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="5" style="text-align: right;">Day Total:</th>
                <th><?php echo round($grandTotal); ?></th>
                <th></th>
            </tr>
        </tfoot>
    </table>

    <!-- Closing Summary -->
    <div class="card mt-4">
        <div class="card-header bg-gradient-dark">
            <h5 class="card-title text-white mb-0">
                <i class="fas fa-file-signature me-2"></i>
                Day Closing Summary
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-bordered">
                        <tr>
                            <th>Opening Balance</th>
                            <td class="text-end">₹
                                <?php echo formatIndianCurrency($openingBalance); ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Total Cash Received</th>
                            <td class="text-end">₹
                                <?php echo formatIndianCurrency($cashTotal); ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Total Online/UPI/Card</th>
                            <td class="text-end">₹
                                <?php echo formatIndianCurrency($onlineTotal); ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Total Cheque Received</th>
                            <td class="text-end">₹
                                <?php echo formatIndianCurrency($chequeTotal); ?>
                            </td>
                        </tr>
                        <tr class="table-primary">
                            <th>Closing Balance</th>
                            <th class="text-end">₹
                                <?php echo formatIndianCurrency($closingBalance); ?>
                            </th>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-bordered">
                        <tr>
                            <th>Total Transactions</th>
                            <td class="text-end">
                                <?php echo $totalTransactions; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Date</th>
                            <td class="text-end">
                                <?php echo date('d-m-Y', strtotime($selected_date)); ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Report Generated</th>
                            <td class="text-end">
                                <?php echo date('d/m/Y h:i A'); ?>
                            </td>
                        </tr>
                    </table>

                    <div class="mt-4 pt-4 border-top d-print-block" style="display: none;">
                        <div class="row">
                            <div class="col-6 text-center">
                                <p class="mb-5">_______________________</p>
                                <p>Prepared By</p>
                            </div>
                            <div class="col-6 text-center">
                                <p class="mb-5">_______________________</p>
                                <p>Verified By</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<?php
include '../../../include/footer.php';
?>

<script src="<?php echo BASE_URL; ?>/assets/vendor/xlsx/xlsx.full.min.js"></script>
<script>
    function exportToExcel() {
        const table = document.getElementById('dayBookExportTable');
        const wb = XLSX.utils.table_to_book(table, { sheet: "Day Book" });
        XLSX.writeFile(wb, 'Day_Book_<?php echo $selected_date; ?>.xlsx');
    }

    function exportToPDF() {
        window.location.href = 'day-book-pdf.php?date=<?php echo $selected_date; ?>';
    }

    $(document).on('click', '#transaction-log-container .pagination a', function (e) {
        e.preventDefault();
        const url = $(this).attr('href');
        if (!url || url === '#' || url === 'javascript:void(0)') return;

        const container = $('#transaction-log-container');
        container.css('opacity', '0.5');

        const ajaxUrl = url + (url.includes('?') ? '&' : '?') + 'ajax=1';

        $.ajax({
            url: ajaxUrl,
            type: 'GET',
            success: function (response) {
                const parser = new DOMParser();
                const doc = parser.parseFromString(response, 'text/html');
                const newContent = doc.getElementById('transaction-log-container');

                if (newContent) {
                    container.html(newContent.innerHTML);
                    container.css('opacity', '1');
                    window.history.pushState({ path: url }, '', url);

                    // Scroll to top of table
                    $('html, body').animate({
                        scrollTop: $("#transaction-log-container").offset().top - 100
                    }, 200);
                } else {
                    window.location.href = url;
                }
            },
            error: function () {
                window.location.href = url;
            }
        });
    });

    function cancelReceipt(paymentId, receiptNo) {
        showConfirm({
            title: 'Cancel Receipt',
            message: `
                <div class="text-start">
                    <p>Are you sure you want to cancel Receipt <strong>#${receiptNo}</strong>?</p>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Reason for Cancellation</label>
                        <select id="cancelReasonInput" class="form-select">
                            <option value="">-- Select Reason --</option>
                            <optgroup label="Cash Related">
                                <option value="Cash amount entered incorrectly">Cash amount entered incorrectly</option>
                                <option value="Cash receipt generated by mistake">Cash receipt generated by mistake</option>
                                <option value="Cash refund requested">Cash refund requested</option>
                            </optgroup>
                            <optgroup label="Cheque Related">
                                <option value="Cheque details entered incorrectly">Cheque details entered incorrectly</option>
                                <option value="Cheque deposited in wrong account">Cheque deposited in wrong account</option>
                            </optgroup>
                            <optgroup label="Cheque Return Related">
                                <option value="Cheque bounced/returned by bank">Cheque bounced/returned by bank</option>
                                <option value="Signature mismatch on cheque">Signature mismatch on cheque</option>
                                <option value="Insufficient funds">Insufficient funds</option>
                            </optgroup>
                            <optgroup label="Online Related">
                                <option value="Online payment failed but receipt generated">Online payment failed but receipt generated</option>
                                <option value="Duplicate online payment">Duplicate online payment</option>
                                <option value="Transaction dispute/chargeback">Transaction dispute/chargeback</option>
                            </optgroup>
                            <optgroup label="Other">
                                <option value="Data entry error">Data entry error</option>
                                <option value="Student withdrew admission">Student withdrew admission</option>
                                <option value="Other">Other</option>
                            </optgroup>
                        </select>
                    </div>
                    <p class="text-danger small"><i class="fas fa-exclamation-triangle me-1"></i> This action will void the payment and cannot be undone.</p>
                </div>
            `,
            confirmText: 'Yes, Cancel Receipt',
            confirmButtonClass: 'btn-danger',
            onConfirm: function () {
                const reason = $('#cancelReasonInput').val();
                if (!reason || reason.trim().length < 5) {
                    showToast('error', 'Error', 'Please select a valid cancellation reason.');
                    return;
                }

                showToast('info', 'Cancelling...', 'Please wait.');
                $.api.post('payments/cancel-receipt', { payment_id: paymentId, reason: reason })
                    .then(res => {
                        if (res.success) {
                            showToast('success', 'Cancelled', 'Receipt voided successfully');
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            showToast('error', 'Error', res.message || 'Failed to cancel receipt');
                        }
                    })
                    .catch(() => showToast('error', 'Error', 'Communication failure'));
            }
        });
    }

    function sendWhatsAppReport(type) {
        const defaultMobile = '9998994020';
        const mobile = prompt("Enter Mobile Number to send " + type + " report:", defaultMobile);
        
        if (mobile && mobile.length >= 10) {
            const selectedDate = '<?php echo $selected_date; ?>';
            const apiUrl = '../../../api/whatsapp/send-daily-report.php';
            
            console.log("Sending WhatsApp Report:", { type, mobile, date: selectedDate, url: apiUrl });

            if (window.showToast) {
                showToast('info', 'Sending...', 'Please wait while we send the WhatsApp report.');
            }

            $.ajax({
                url: apiUrl,
                type: 'POST',
                data: {
                    type: type,
                    mobile: mobile,
                    date: selectedDate
                },
                success: function(response) {
                    if (response.success) {
                        if (window.showToast) {
                            showToast('success', 'Success', response.message);
                        } else {
                            alert(response.message);
                        }
                    } else {
                        if (window.showToast) {
                            showToast('error', 'Error', response.message);
                        } else {
                            alert(response.message);
                        }
                    }
                },
                error: function() {
                    if (window.showToast) {
                        showToast('error', 'Error', 'Failed to communicate with the server.');
                    } else {
                        alert('Failed to communicate with the server.');
                    }
                }
            });
        } else if (mobile !== null) {
            alert("Please enter a valid 10-digit mobile number.");
        }
    }
</script>