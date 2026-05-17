<?php
/**
 * Direct Collection Report
 * Reports for payments collected without GST (stored in tbl_payments_without_gst)
 */

require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/common/constants.php';
require_once __DIR__ . '/../../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once __DIR__ . '/../../../../common/helpers/format_helper.php';
require_once __DIR__ . '/../../../common/pagination.php';

if (!hasRole(ROLE_ACCOUNTANT) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "Direct Collection Report";

$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date = $_GET['to_date'] ?? date('Y-m-d');
$search = $_GET['search'] ?? '';
$course_filter = $_GET['course_id'] ?? '';
$payment_type_filter = $_GET['payment_type'] ?? '';
$receipt_config_filter = $_GET['receipt_config_id'] ?? '';

$dbOps = new DatabaseOperations();

// Get filter master data
$predefinedTypes = [
    'School Fee',
    'Trust Facilities Fee',
    'Tuition Fee Part 1',
    'Tuition Fee Part 2',
    'Hostel Fee',
    'Transport Fee',
];
$dbPaymentTypes = $dbOps->customSelect("SELECT DISTINCT payment_type FROM tbl_payments WHERE receipt_no = '0' AND payment_type IS NOT NULL AND payment_type != ''", []);
$allPaymentTypes = $predefinedTypes;
foreach ($dbPaymentTypes as $type) {
    if (!in_array($type['payment_type'], $allPaymentTypes)) {
        $allPaymentTypes[] = $type['payment_type'];
    }
}
sort($allPaymentTypes);

$receiptConfigs = $dbOps->customSelect("SELECT id, receipt_title FROM tbl_receipt_configuration ORDER BY receipt_title", []);

// Get payments without GST (querying tbl_payments_without_gst)
$whereConditions = ["p.status = 'paid'", "p.payment_date BETWEEN ? AND ?"];
$params = [$from_date, $to_date];

if (!empty($search)) {
    $searchTerm = "%$search%";
    $whereConditions[] = "(CONCAT(r.surname, ' ', r.student_name, ' ', IFNULL(r.fathers_name, '')) LIKE ? OR r.mob LIKE ? OR r.id LIKE ? OR p.transaction_id LIKE ?)";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($course_filter)) {
    if ($course_filter === '11th') {
        $whereConditions[] = "r.course_id IN (1, 2)";
    } elseif ($course_filter === '12th') {
        $whereConditions[] = "r.course_id IN (4, 5)";
    } elseif ($course_filter === 'Reneet') {
        $whereConditions[] = "r.course_id = 6";
    } else {
        $whereConditions[] = "r.course_id = ?";
        $params[] = $course_filter;
    }
}

if (!empty($payment_type_filter)) {
    $whereConditions[] = "p.payment_type = ?";
    $params[] = $payment_type_filter;
}

if (!empty($receipt_config_filter)) {
    $whereConditions[] = "p.receipt_config_id = ?";
    $params[] = $receipt_config_filter;
}

$whereSql = implode(" AND ", $whereConditions);

// Pagination setup
$records_per_page = 15;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$current_page = max(1, $current_page); // Ensure page is at least 1

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM tbl_payments p JOIN tbl_gm_std_registration r ON p.student_id = r.id WHERE p.receipt_no = '0' AND $whereSql";
$count_result = $dbOps->customSelect($count_sql, $params);
$total_records = $count_result[0]['total'] ?? 0;
$total_pages = ceil($total_records / $records_per_page);

$sql = "SELECT 
            p.id,
            p.payment_date,
            p.amount,
            p.payment_type,
            p.payment_mode,
            p.transaction_id,
            p.fee_component,
            CONCAT(r.surname, ' ', r.student_name, ' ', IFNULL(r.fathers_name, '')) as student_full_name,
            c.course_name,
            m.medium_name,
            CONCAT(c.course_name, ' - ', m.medium_name) as standard_display
        FROM tbl_payments p
        JOIN tbl_gm_std_registration r ON p.student_id = r.id
        LEFT JOIN tbl_enrolled_students es ON es.registration_id = r.id AND es.is_active = 1
        LEFT JOIN tbl_courses c ON r.course_id = c.id
        LEFT JOIN tbl_medium m ON r.medium_id = m.id
        WHERE p.receipt_no = '0' AND $whereSql
        ORDER BY p.payment_date DESC, p.id DESC";

// Fetch paginated records for UI
$offset = ($current_page - 1) * $records_per_page;
$sql .= " LIMIT ? OFFSET ?";
$params[] = $records_per_page;
$params[] = $offset;

$payments = $dbOps->customSelect($sql, $params);

// Calculate totals (from all records, not just current page)
$total_sql = "SELECT 
                SUM(p.amount) as total_amount
            FROM tbl_payments p
            JOIN tbl_gm_std_registration r ON p.student_id = r.id
            WHERE p.receipt_no = '0' AND $whereSql";
$total_params = array_slice($params, 0, -2); // Remove LIMIT and OFFSET params
$totals_result = $dbOps->customSelect($total_sql, $total_params);
$grand_total_amount = $totals_result[0]['total_amount'] ?? 0;

include '../../../include/header.php';
include '../../../include/navbar.php';
include '../../../include/sidebar.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 fw-bold text-dark"><i class="fas fa-file-invoice-dollar me-2 text-danger"></i>Direct Collection Report</h1>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <!-- Filter Section -->
            <div class="card mb-4 shadow-sm border-0">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label text-dark small fw-bold">SEARCH</label>
                            <input type="text" name="search" class="form-control" placeholder="Name, ID, Transaction..."
                                value="<?php echo htmlspecialchars($search ?? ''); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label text-dark small fw-bold">STANDARD</label>
                            <select name="course_id" class="form-select">
                                <option value="">All Standards</option>
                                <option value="11th" <?php echo $course_filter === '11th' ? 'selected' : ''; ?>>11th</option>
                                <option value="12th" <?php echo $course_filter === '12th' ? 'selected' : ''; ?>>12th</option>
                                <option value="Reneet" <?php echo $course_filter === 'Reneet' ? 'selected' : ''; ?>>Reneet</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label text-dark small fw-bold">FROM DATE</label>
                            <input type="date" name="from_date" class="form-control" value="<?php echo $from_date; ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label text-dark small fw-bold">TO DATE</label>
                            <input type="date" name="to_date" class="form-control" value="<?php echo $to_date; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-dark small fw-bold">PAYMENT TYPE</label>
                            <select name="payment_type" class="form-select">
                                <option value="">All Types</option>
                                <?php foreach ($allPaymentTypes as $type): ?>
                                    <option value="<?php echo htmlspecialchars($type ?? ''); ?>" <?php echo $payment_type_filter == $type ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($type ?? ''); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-dark small fw-bold">HEAD WISE</label>
                            <select name="receipt_config_id" class="form-select">
                                <option value="">All Receipts</option>
                                <?php foreach ($receiptConfigs as $config): ?>
                                    <option value="<?php echo $config['id']; ?>" <?php echo $receipt_config_filter == $config['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($config['receipt_title'] ?? ''); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-12 d-flex align-items-end gap-2 justify-content-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter me-1"></i> Apply Filters
                            </button>
                            <a href="without-gst-report.php" class="btn btn-secondary">
                                <i class="fas fa-redo me-1"></i> Reset
                            </a>
                            <div class="ms-2 d-flex gap-2">
                                <button type="button" class="btn btn-success" onclick="exportToExcel()">
                                    <i class="fas fa-file-excel me-1"></i> Excel
                                </button>
                                <button type="button" class="btn btn-danger" onclick="exportToPDF()">
                                    <i class="fas fa-file-pdf me-1"></i> PDF
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="small-box bg-white shadow-sm border-start border-danger border-4">
                        <div class="inner p-3">
                            <h3 class="fw-bold text-dark">₹<?php echo formatIndianCurrency($grand_total_amount); ?></h3>
                            <p class="text-muted mb-0">Total Collection</p>
                        </div>
                        <div class="icon text-danger opacity-25">
                            <i class="fas fa-wallet"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="small-box bg-white shadow-sm border-start border-primary border-4">
                        <div class="inner p-3">
                            <h3 class="fw-bold text-dark"><?php echo $total_records; ?></h3>
                            <p class="text-muted mb-0">Total Transactions</p>
                        </div>
                        <div class="icon text-primary opacity-25">
                            <i class="fas fa-exchange-alt"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Report Table -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-danger text-white py-3">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-table me-2"></i>
                        Transaction Details (<?php echo date('d-m-Y', strtotime($from_date)); ?> - <?php echo date('d-m-Y', strtotime($to_date)); ?>)
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="reportTable">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-3">#</th>
                                    <th>Date</th>
                                    <th>Student Name</th>
                                    <th>Standard</th>
                                    <th>Fee Component</th>
                                    <th>Transaction ID</th>
                                    <th>Mode</th>
                                    <th class="text-end">Amount</th>
                                    <th class="text-center pe-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($payments)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-5">
                                            <i class="fas fa-info-circle fa-2x text-muted mb-3 d-block"></i>
                                            <p class="text-muted">No transactions found for the selected period.</p>
                                        </td>
                                    </tr>
                                <?php
else: ?>
                                    <?php $i = ($current_page - 1) * $records_per_page + 1;
    foreach ($payments as $row): ?>
                                        <tr>
                                            <td class="ps-3"><?php echo $i++; ?></td>
                                            <td><span class="fw-medium"><?php echo date('d-m-Y', strtotime($row['payment_date'])); ?></span></td>
                                            <td>
                                                <span class="fw-bold text-dark"><?php echo htmlspecialchars($row['student_full_name'] ?? ''); ?></span>
                                            </td>
                                            <td><?php echo htmlspecialchars($row['standard_display'] ?? $row['course_name']); ?></td>
                                            <td>
                                                <span class="badge bg-soft-secondary text-dark" style="background-color: #f1f5f9;">
                                                    <?php echo formatFeeKey($row['fee_component']); ?>
                                                </span>
                                            </td>
                                            <td><code class="small"><?php echo htmlspecialchars($row['transaction_id'] ?? ''); ?></code></td>
                                            <td>
                                                <span class="badge rounded-pill bg-light text-dark border">
                                                    <?php echo strtoupper($row['payment_mode']); ?>
                                                </span>
                                            </td>
                                            <td class="text-end fw-bold text-danger">
                                                ₹<?php echo formatIndianCurrency($row['amount']); ?>
                                            </td>
                                            <td class="text-center pe-3">
                                                <?php if (hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_ACCOUNTANT])): ?>
                                                <button type="button" class="btn btn-sm btn-outline-danger border-0" 
                                                        onclick="hardDeletePayment(<?php echo $row['id']; ?>, '<?php echo $row['transaction_id']; ?>', <?php echo $row['amount']; ?>)" 
                                                        title="Hard Delete Payment">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                                <?php else: ?>
                                                    <span class="text-muted small">No Access</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php
    endforeach; ?>
                                <?php
endif; ?>
                            </tbody>
                            <tfoot class="bg-light">
                                <tr class="fw-bold">
                                    <td colspan="7" class="text-end ps-3 py-3 font-weight-bold">GRAND TOTAL</td>
                                    <td class="text-end py-3 h5 mb-0 fw-bold text-danger">₹<?php echo formatIndianCurrency($grand_total_amount); ?></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div class="mt-4 pb-4">
                <?php
$baseUrl = 'without-gst-report.php?' . http_build_query(array_diff_key($_GET, ['page' => '']));
echo renderPagination($current_page, $total_pages, $baseUrl, 2, $total_records, 'records');
?>
            </div>
        </div>
    </section>
</div>

<?php include '../../../include/footer.php'; ?>

<script src="<?php echo BASE_URL; ?>/assets/vendor/xlsx/xlsx.full.min.js"></script>
<script>
    function exportToExcel() {
        var table = document.getElementById('reportTable');
        var wb = XLSX.utils.table_to_book(table, { sheet: "Direct Collection Report" });
        XLSX.writeFile(wb, 'Direct_Collection_Report_<?php echo $from_date; ?>_to_<?php echo $to_date; ?>.xlsx');
    }

    function exportToPDF() {
        const params = new URLSearchParams({
            from_date: '<?php echo $from_date; ?>',
            to_date: '<?php echo $to_date; ?>',
            search: '<?php echo addslashes($search); ?>',
            course_id: '<?php echo $course_filter; ?>',
            payment_type: '<?php echo $payment_type_filter; ?>',
            receipt_config_id: '<?php echo $receipt_config_filter; ?>'
        });
        window.location.href = 'without-gst-report-pdf.php?' + params.toString();
    }

    function hardDeletePayment(id, txId, amount) {
        const formattedAmount = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(amount);
        if (confirm(`Are you sure you want to PERMANENTLY DELETE payment ${txId} of ${formattedAmount}?\n\nThis action cannot be undone and will re-calculate the student's pending fees.`)) {
            
            // Show loading state (optional for native, but let's keep it simple)
            const btn = event.currentTarget;
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            btn.disabled = true;

            fetch('../../../../counselling-backend/index.php?route=payments/payment-without-gst-hard-delete', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ id: id })
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert(result.message || 'Payment deleted successfully');
                    location.reload();
                } else {
                    const errorMsg = result.error || result.message || 'Failed to delete payment';
                    alert('Error: ' + errorMsg);
                    btn.innerHTML = originalHtml;
                    btn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Request failed: ' + error);
                btn.innerHTML = originalHtml;
                btn.disabled = false;
            });
        }
    }
</script>
