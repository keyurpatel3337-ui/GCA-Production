<?php
/**
 * GST Report
 * Reports for Tuition Fee Part 1 (Token Fee) and Tuition Fee Part 2 which attract GST
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

$page_title = "GST Report";

$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date = $_GET['to_date'] ?? date('Y-m-d');
$search = $_GET['search'] ?? '';
$course_filter = $_GET['course_id'] ?? '';

$dbOps = new DatabaseOperations();

// Get payments with GST (Tuition Fee Part 1 and Part 2)
// User specified: "tution fee part 1 / token fee and tution fee part 2 me GST apply ho ta hai"
// Based on add-payment.php, values are 'tuition_fee_part1' and 'tuition_fee_part2'
$whereConditions = ["p.status = 'paid'", "p.payment_date BETWEEN ? AND ?", "(p.payment_type LIKE '%Tuition Fee Part 1%' OR p.payment_type LIKE '%Tuition Fee Part 2%')"];
$params = [$from_date, $to_date];

if (!empty($course_filter)) {
    if ($course_filter === '11th') {
        $whereConditions[] = "r.course_id = 1";
    } elseif ($course_filter === '12th') {
        $whereConditions[] = "r.course_id = 2";
    } elseif ($course_filter === 'Reneet') {
        $whereConditions[] = "r.course_id = 3";
    } else {
        $whereConditions[] = "r.course_id = ?";
        $params[] = $course_filter;
    }
}

if (!empty($search)) {
    $searchTerm = "%$search%";
    $whereConditions[] = "(CONCAT(r.surname, ' ', r.student_name, ' ', IFNULL(r.fathers_name, '')) LIKE ? OR r.mob LIKE ? OR r.id LIKE ? OR p.receipt_no LIKE ?)";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$whereSql = implode(" AND ", $whereConditions);

// Pagination setup
$records_per_page = 15;
$current_page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$current_page = max(1, $current_page); // Ensure page is at least 1

// Get total count for pagination (grouped count)
$count_sql = "SELECT COUNT(*) as total FROM (
                SELECT 1 FROM tbl_payments p 
                JOIN tbl_gm_std_registration r ON p.student_id = r.id 
                WHERE $whereSql 
                GROUP BY p.payment_date, p.payment_type, p.payment_mode
              ) as sub";
$count_result = $dbOps->customSelect($count_sql, $params);
$total_records = $count_result[0]['total'] ?? 0;
$total_pages = ceil($total_records / $records_per_page);

$sql = "SELECT 
            p.payment_date,
            p.payment_type,
            p.payment_mode,
            SUM(p.amount) as total_amount,
            COUNT(*) as txn_count
        FROM tbl_payments p
        JOIN tbl_gm_std_registration r ON p.student_id = r.id
        WHERE $whereSql
        GROUP BY p.payment_date, p.payment_type, p.payment_mode
        ORDER BY p.payment_date DESC, p.payment_type ASC";

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
            WHERE $whereSql";
$total_params = array_slice($params, 0, -2); // Remove LIMIT and OFFSET params
$totals_result = $dbOps->customSelect($total_sql, $total_params);
$grand_total_amount = $totals_result[0]['total_amount'] ?? 0;

$totalCollection = 0;
$totalTaxable = 0;
$totalSGST = 0;
$totalCGST = 0;
$totalGST = 0;

$processedPayments = [];

foreach ($payments as $payment) {
    // Determine the specific type for display
    $types = [];
    if (stripos($payment['payment_type'], 'Tuition Fee Part 1') !== false) {
        $types[] = "Token Fee (Part 1)";
    } else if (stripos($payment['payment_type'], 'Token Fee') !== false) {
        $types[] = "Token Fee (Part 1)";
    }

    if (stripos($payment['payment_type'], 'Tuition Fee Part 2') !== false) {
        $types[] = "Tuition Fee Part 2";
    }
    $displayType = implode(', ', $types);
    if (empty($displayType)) {
        $displayType = $payment['payment_type']; // Fallback
    }

    // Calculation: Amount is Inclusive of 18% GST
    // Taxable = Amount / 1.18
    $amount = $payment['total_amount'];
    $taxable = $amount / 1.18;
    $gst = $amount - $taxable;
    $cgst = $gst / 2;
    $sgst = $gst / 2;

    $processedPayments[] = [
        'payment_date' => $payment['payment_date'],
        'payment_mode' => $payment['payment_mode'],
        'txn_count' => $payment['txn_count'],
        'display_type' => $displayType,
        'amount' => $amount,
        'taxable' => $taxable,
        'cgst' => $cgst,
        'sgst' => $sgst,
        'gst' => $gst
    ];

    $totalCollection += $amount;
    $totalTaxable += $taxable;
    $totalGST += $gst;
    $totalCGST += $cgst;
    $totalSGST += $sgst;
}

// Calculate Grand Totals for summary cards and footer
$grand_taxable = $grand_total_amount / 1.18;
$grand_gst = $grand_total_amount - $grand_taxable;
$grand_cgst = $grand_gst / 2;
$grand_sgst = $grand_gst / 2;

include '../../../include/header.php';
include '../../../include/navbar.php';
include '../../../include/sidebar.php';
?>



<div class="container-fluid">

    <!-- Filter Section -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label text-dark">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Name, Receipt, Mobile..."
                        value="<?php echo htmlspecialchars($search ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label text-dark">From Date</label>
                    <input type="date" name="from_date" class="form-control" value="<?php echo $from_date; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label text-dark">To Date</label>
                    <input type="date" name="to_date" class="form-control" value="<?php echo $to_date; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label text-dark">Standard</label>
                    <select name="course_id" class="form-select">
                        <option value="">All Standards</option>
                        <option value="11th" <?php echo $course_filter == '11th' ? 'selected' : ''; ?>>11th</option>
                        <option value="12th" <?php echo $course_filter == '12th' ? 'selected' : ''; ?>>12th</option>
                        <option value="Reneet" <?php echo $course_filter == 'Reneet' ? 'selected' : ''; ?>>Reneet</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter me-1"></i> Apply
                    </button>
                    <a href="gst-report.php" class="btn btn-secondary">
                        <i class="fas fa-redo me-1"></i> Reset
                    </a>
                </div>
                <div class="col-md-3 d-flex align-items-end justify-content-end gap-2">
                    <button type="button" class="btn btn-success" onclick="exportToExcel()">
                        <i class="fas fa-file-excel me-1"></i> Export Excel
                    </button>
                    <button type="button" class="btn btn-danger" onclick="exportToPDF()">
                        <i class="fas fa-file-pdf me-1"></i> Export PDF
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="small-box bg-primary text-white">
                <div class="inner">
                    <h3>₹<?php echo formatIndianCurrency($grand_total_amount); ?></h3>
                    <p>Total Collection (Incl. GST)</p>
                </div>
                <div class="icon">
                    <i class="fas fa-wallet"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="small-box bg-info text-white">
                <div class="inner">
                    <h3>₹<?php echo formatIndianCurrency($grand_taxable); ?></h3>
                    <p>Taxable Value</p>
                </div>
                <div class="icon">
                    <i class="fas fa-calculator"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="small-box bg-warning text-dark">
                <div class="inner">
                    <h3>₹<?php echo formatIndianCurrency($grand_gst); ?></h3>
                    <p>Total GST (18%)</p>
                </div>
                <div class="icon">
                    <i class="fas fa-percentage"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="small-box bg-success text-white">
                <div class="inner">
                    <h3><?php echo $total_records; ?></h3>
                    <p>Total Transactions</p>
                </div>
                <div class="icon">
                    <i class="fas fa-list-ol"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- GST Table -->
    <div class="card">
        <div class="card-header bg-dark text-white">
            <h5 class="card-title mb-0">
                <i class="fas fa-table me-2"></i>
                GST Detailed Report (<?php echo date('d-m-Y', strtotime($from_date)); ?> -
                <?php echo date('d-m-Y', strtotime($to_date)); ?>)
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-striped mb-0" id="gstTable">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Fee Type</th>
                            <th>Payment Mode</th>
                            <th class="text-center">Count</th>
                            <th class="text-end">Total Amount</th>
                            <th class="text-end">Taxable Value</th>
                            <th class="text-end">CGST (9%)</th>
                            <th class="text-end">SGST (9%)</th>
                            <th class="text-end">Total GST</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($processedPayments)): ?>
                            <tr>
                                <td colspan="10" class="text-center py-4">No records found for the selected period.</td>
                            </tr>
                        <?php else: ?>
                            <?php $i = ($current_page - 1) * $records_per_page + 1;
                            foreach ($processedPayments as $row): ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td><?php echo date('d-m-Y', strtotime($row['payment_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($row['display_type'] ?? ''); ?></td>
                                    <td><?php echo ucfirst($row['payment_mode'] ?? ''); ?></td>
                                    <td class="text-center"><?php echo $row['txn_count']; ?></td>
                                    <td class="text-end fw-bold">
                                        ₹<?php echo formatIndianCurrency($row['amount']); ?>
                                    </td>
                                    <td class="text-end">₹<?php echo formatIndianCurrency($row['taxable']); ?></td>
                                    <td class="text-end">₹<?php echo formatIndianCurrency($row['cgst']); ?></td>
                                    <td class="text-end">₹<?php echo formatIndianCurrency($row['sgst']); ?></td>
                                    <td class="text-end fw-bold text-danger">
                                        ₹<?php echo formatIndianCurrency($row['gst']); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot class="table-dark">
                        <tr>
                            <th colspan="5" class="text-end">Page Total</th>
                            <th class="text-end">₹<?php echo formatIndianCurrency($totalCollection); ?></th>
                            <th class="text-end">₹<?php echo formatIndianCurrency($totalTaxable); ?></th>
                            <th class="text-end">₹<?php echo formatIndianCurrency($totalCGST); ?></th>
                            <th class="text-end">₹<?php echo formatIndianCurrency($totalSGST); ?></th>
                            <th class="text-end">₹<?php echo formatIndianCurrency($totalGST); ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- Pagination -->
    <?php
    $baseUrl = 'gst-report.php?' . http_build_query(array_diff_key($_GET, ['page' => '']));
    echo renderPagination($current_page, $total_pages, $baseUrl, 2, $total_records, 'records');
    ?>
</div>

<?php include '../../../include/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
    function exportToExcel() {
        window.location.href = 'gst-report-excel.php?from_date=<?php echo $from_date; ?>&to_date=<?php echo $to_date; ?>&search=<?php echo urlencode($search); ?>';
    }

    function exportToPDF() {
        window.location.href = 'gst-report-pdf.php?from_date=<?php echo $from_date; ?>&to_date=<?php echo $to_date; ?>&search=<?php echo urlencode($search); ?>';
    }
</script>