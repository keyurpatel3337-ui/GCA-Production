<?php
/**
 * Receipt Register
 * All receipts generated with complete details
 */

require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/common/constants.php';
require_once __DIR__ . '/../../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once __DIR__ . '/../../../../common/helpers/format_helper.php';
require_once __DIR__ . '/../../../common/pagination.php';

// Check access
if (!hasRole(ROLE_ACCOUNTANT) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "Receipt Register";
$page_breadcrumb = "Receipt Register";

// Get filters
$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date = $_GET['to_date'] ?? date('Y-m-d');
$payment_mode = $_GET['payment_mode'] ?? '';
$payment_type_filter = $_GET['payment_type'] ?? '';
$receipt_config = $_GET['receipt_config'] ?? '';
$school_filter = $_GET['school_id'] ?? '';
$medium_filter = $_GET['medium_id'] ?? '';
$group_filter = $_GET['group_id'] ?? '';
$course_filter = $_GET['course_id'] ?? '';
$term_filter = $_GET['term_id'] ?? '';
$search = $_GET['search'] ?? '';

$dbOps = new DatabaseOperations();

// Get master data for filters
$receiptConfigs = $dbOps->customSelect("SELECT id, receipt_title FROM tbl_receipt_configuration ORDER BY receipt_title", []);

$predefinedTypes = [
    'School Fee',
    'Trust Facilities Fee',
    'Tuition Fee Part 1',
    'Tuition Fee Part 2',
    'Hostel Fee',
    'Hostel Cash Fee (No Receipt)',
    'Transport Fee',
];
$dbPaymentTypes = $dbOps->customSelect("SELECT DISTINCT payment_type FROM tbl_payments WHERE payment_type IS NOT NULL AND payment_type != ''", []);
$allPaymentTypes = $predefinedTypes;
foreach ($dbPaymentTypes as $type) {
    if (!in_array($type['payment_type'], $allPaymentTypes)) {
        $allPaymentTypes[] = $type['payment_type'];
    }
}
sort($allPaymentTypes);

$schools = $dbOps->customSelect("SELECT id, school_name FROM tbl_schools ORDER BY school_name", []);
$mediums = $dbOps->customSelect("SELECT id, medium_name FROM tbl_medium ORDER BY medium_name", []);
$groups = $dbOps->customSelect("SELECT id, group_name FROM tbl_group ORDER BY group_name", []);
$courses = $dbOps->customSelect("SELECT id, course_name FROM tbl_courses ORDER BY course_name", []);
$terms = $dbOps->customSelect("SELECT id, term_name FROM tbl_term WHERE is_active = 1 ORDER BY term_number", []);

// Get all receipts/payments
$sql = "SELECT 
            p.id,
            p.receipt_no,
            p.amount,
            p.payment_date,
            p.payment_mode,
            p.payment_type,
            p.transaction_id,
            p.cheque_no,
            p.cheque_date,
            p.bank_name,
            p.remarks,
            p.created_at,
            p.fee_component,
            rc.receipt_title,
            '' as receipt_prefix,
            CONCAT(r.surname, ' ', r.student_name, ' ', IFNULL(r.fathers_name, '')) as student_full_name,
            c.course_name as current_class,
            g.group_name,
            m.medium_name,
            t.term_name,
            u.name as collected_by
        FROM tbl_payments p
        JOIN tbl_gm_std_registration r ON p.student_id = r.id
        LEFT JOIN tbl_enrolled_students es ON es.registration_id = r.id AND es.is_active = 1
        LEFT JOIN tbl_courses c ON r.course_id = c.id
        LEFT JOIN tbl_group g ON r.group_id = g.id
        LEFT JOIN tbl_medium m ON r.medium_id = m.id
        LEFT JOIN tbl_term t ON p.term_id = t.id
        LEFT JOIN tbl_receipt_configuration rc ON p.receipt_config_id = rc.id
        LEFT JOIN tbl_users u ON p.created_by = u.id
        WHERE p.status = 'paid'
        AND p.payment_date BETWEEN ? AND ?";

$params = [$from_date, $to_date];

if (!empty($payment_mode)) {
    $sql .= " AND p.payment_mode = ?";
    $params[] = $payment_mode;
}

if (!empty($payment_type_filter)) {
    $sql .= " AND p.payment_type = ?";
    $params[] = $payment_type_filter;
}

if (!empty($receipt_config)) {
    $sql .= " AND p.receipt_config_id = ?";
    $params[] = $receipt_config;
}

if (!empty($school_filter)) {
    $sql .= " AND (r.school_id = ? OR r.school_id = ?)";
    $params[] = $school_filter;
    $params[] = $school_filter;
}

if (!empty($medium_filter)) {
    $sql .= " AND (r.medium_id = ? OR r.medium_id = ?)";
    $params[] = $medium_filter;
    $params[] = $medium_filter;
}

if (!empty($group_filter)) {
    $sql .= " AND (r.group_id = ? OR r.group_id = ?)";
    $params[] = $group_filter;
    $params[] = $group_filter;
}

if (!empty($course_filter)) {
    if ($course_filter === '11th') {
        $sql .= " AND r.course_id = 1";
    } elseif ($course_filter === '12th') {
        $sql .= " AND r.course_id = 2";
    } elseif ($course_filter === 'Reneet') {
        $sql .= " AND r.course_id = 3";
    } else {
        $sql .= " AND r.course_id = ?";
        $params[] = $course_filter;
    }
}

if (!empty($term_filter)) {
    $sql .= " AND p.term_id = ?";
    $params[] = $term_filter;
}

if (!empty($search)) {
    $sql .= " AND (
        CONCAT(r.surname, ' ', r.student_name, ' ', IFNULL(r.fathers_name, '')) LIKE ? OR 
        r.mob LIKE ? OR 
        r.id LIKE ? OR 
        p.receipt_no LIKE ?
    )";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

// Pagination setup
$records_per_page = 15;
$current_page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$current_page = max(1, $current_page); // Ensure page is at least 1

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total " . substr($sql, strpos($sql, 'FROM'));
$count_result = $dbOps->customSelect($count_sql, $params);
$total_records = $count_result[0]['total'] ?? 0;
$total_pages = ceil($total_records / $records_per_page);

// 1. Fetch ALL records for Export (No LIMIT/OFFSET, Sorted ASC)
$export_sql = $sql . " ORDER BY p.payment_date ASC, p.created_at asc";
$export_params = $params;
$all_receipts = $dbOps->customSelect($export_sql, $export_params);

// 2. Fetch paginated records for UI
$offset = ($current_page - 1) * $records_per_page;
$sql .= " ORDER BY p.payment_date ASC, p.created_at asc LIMIT ? OFFSET ?";
$params[] = $records_per_page;
$params[] = $offset;

$receipts = $dbOps->customSelect($sql, $params);

// Calculate totals (from all records, not just current page)
$total_sql = "SELECT 
                COUNT(*) as total_receipts,
                SUM(p.amount) as total_amount,
                p.payment_mode
            " . substr($sql, strpos($sql, 'FROM'), strpos($sql, 'ORDER BY') - strpos($sql, 'FROM')) . "
            GROUP BY p.payment_mode";
$total_params = array_slice($params, 0, -2); // Remove LIMIT and OFFSET params
$totals_result = $dbOps->customSelect($total_sql, $total_params);

$totalReceipts = $total_records;
$totalAmount = 0;

// Mode-wise breakdown from totals query
$modeBreakdown = [];
foreach ($totals_result as $total_row) {
    $mode = strtoupper($total_row['payment_mode']);
    $modeBreakdown[$mode] = [
        'count' => $total_row['total_receipts'],
        'amount' => $total_row['total_amount']
    ];
    $totalAmount += $total_row['total_amount'];
}

include '../../../include/header.php';
include '../../../include/navbar.php';
include '../../../include/sidebar.php';
?>





<div class="container-fluid">
    <!-- Filter & Export Toolbar -->
    <div class="mb-3 d-flex justify-content-between align-items-center">
        <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#filterSection"
            aria-expanded="false" aria-controls="filterSection">
            <i class="fas fa-filter me-2"></i> Toggle Filters
        </button>
        <div class="d-flex gap-2">
            <button class="btn btn-success" onclick="exportToExcel()">
                <i class="fas fa-file-excel me-1"></i> Export Excel
            </button>
            <button class="btn btn-danger" onclick="exportToPDF()">
                <i class="fas fa-file-pdf me-1"></i> Export PDF
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="collapse" id="filterSection">
        <div class="card filter-card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <!-- Row 1: Primary Identifiers -->
                    <div class="col-md-2">
                        <label class="form-label">From Date</label>
                        <input type="date" name="from_date" class="form-control" value="<?php echo $from_date; ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">To Date</label>
                        <input type="date" name="to_date" class="form-control" value="<?php echo $to_date; ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Name, ID, Receipt, Mobile..."
                            value="<?php echo htmlspecialchars($search ?? ''); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">School</label>
                        <select name="school_id" class="form-select">
                            <option value="">All Schools</option>
                            <?php foreach ($schools as $school): ?>
                                <option value="<?php echo $school['id']; ?>" <?php echo $school_filter == $school['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($school['school_name'] ?? ''); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Medium</label>
                        <select name="medium_id" class="form-select">
                            <option value="">All Mediums</option>
                            <?php foreach ($mediums as $medium): ?>
                                <option value="<?php echo $medium['id']; ?>" <?php echo $medium_filter == $medium['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($medium['medium_name'] ?? ''); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Row 2: Course & Payment Filters -->
                    <div class="col-md-2">
                        <label class="form-label">Group</label>
                        <select name="group_id" class="form-select">
                            <option value="">All Groups</option>
                            <?php foreach ($groups as $group): ?>
                                <option value="<?php echo $group['id']; ?>" <?php echo $group_filter == $group['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($group['group_name'] ?? ''); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Standard</label>
                        <select name="course_id" class="form-select">
                            <option value="">All Standards</option>
                            <option value="11th" <?php echo $course_filter == '11th' ? 'selected' : ''; ?>>11th</option>
                            <option value="12th" <?php echo $course_filter == '12th' ? 'selected' : ''; ?>>12th</option>
                            <option value="Reneet" <?php echo $course_filter == 'Reneet' ? 'selected' : ''; ?>>Reneet</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Term</label>
                        <select name="term_id" class="form-select">
                            <option value="">All Terms</option>
                            <?php foreach ($terms as $term): ?>
                                <option value="<?php echo $term['id']; ?>" <?php echo $term_filter == $term['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($term['term_name'] ?? ''); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Payment Mode</label>
                        <select name="payment_mode" class="form-select">
                            <option value="">All Modes</option>
                            <option value="cash" <?php echo $payment_mode == 'cash' ? 'selected' : ''; ?>>Cash</option>
                            <option value="online" <?php echo $payment_mode == 'online' ? 'selected' : ''; ?>>Online</option>
                            <option value="upi" <?php echo $payment_mode == 'upi' ? 'selected' : ''; ?>>UPI</option>
                            <option value="cheque" <?php echo $payment_mode == 'cheque' ? 'selected' : ''; ?>>Cheque</option>
                            <option value="card" <?php echo $payment_mode == 'card' ? 'selected' : ''; ?>>Card</option>
                            <option value="deduction" <?php echo $payment_mode == 'deduction' ? 'selected' : ''; ?>>Deduction</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Payment Type</label>
                        <select name="payment_type" class="form-select">
                            <option value="">All Types</option>
                            <?php foreach ($allPaymentTypes as $type): ?>
                                <option value="<?php echo htmlspecialchars($type ?? ''); ?>" <?php echo $payment_type_filter == $type ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($type ?? ''); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Head wise</label>
                        <select name="receipt_config" class="form-select">
                            <option value="">All Receipts</option>
                            <?php foreach ($receiptConfigs as $config): ?>
                                <option value="<?php echo $config['id']; ?>" <?php echo $receipt_config == $config['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($config['receipt_title'] ?? ''); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Actions -->
                    <div class="col-12 d-flex justify-content-end gap-2 mt-2">
                        <a href="receipt-register.php" class="btn btn-outline-light px-4">
                            <i class="fas fa-redo me-1"></i> Reset
                        </a>
                        <button type="submit" class="btn btn-light px-5 fw-bold">
                            <i class="fas fa-filter me-1"></i> Apply Filters
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Summary Stats -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-box info">
                <h3 class="text-info">
                    <?php echo formatIndianCurrency($totalReceipts, false); ?>
                </h3>
                <p class="mb-0 text-muted">Total Receipts</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-box success">
                <h3 class="text-success">₹
                    <?php echo formatIndianCurrency($totalAmount); ?>
                </h3>
                <p class="mb-0 text-muted">Total Collection</p>
            </div>
        </div>
        <div class="col-md-6">
            <div class="stat-box warning">
                <p class="mb-2 text-muted"><strong>Mode-wise Summary</strong></p>
                <div class="mode-summary justify-content-center">
                    <?php foreach ($modeBreakdown as $mode => $data): ?>
                        <div class="mode-card">
                            <strong>₹
                                <?php echo formatIndianCurrency($data['amount']); ?>
                            </strong>
                            <small>
                                <?php echo $mode; ?> (
                                <?php echo $data['count']; ?>)
                            </small>
                        </div>
                        <?php
                    endforeach; ?>
                </div>
            </div>
        </div>
    </div>


    <!-- Data Table -->
    <div class="card">
        <div class="card-header bg-dark text-white">
            <h5 class="card-title mb-0">
                <i class="fas fa-list me-2"></i>
                Receipt Register (
                <?php echo date('d-m-Y', strtotime($from_date)); ?> -
                <?php echo date('d-m-Y', strtotime($to_date)); ?>)
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0" id="receiptTable">
                    <thead class="table-dark">
                        <tr>
                            <th class="border-0">#</th>
                            <th class="border-0">Receipt</th>
                            <th class="border-0">Date</th>
                            <th class="border-0">Student</th>
                            <th class="border-0">Class</th>
                            <th class="border-0">Group</th>
                            <th class="border-0">Medium</th>
                            <th class="border-0">Term</th>
                            <th class="border-0">Type</th>
                            <th class="border-0">Head</th>
                            <th class="border-0">Mode</th>
                            <th class="border-0">Bank</th>
                            <th class="border-0">Total</th>
                            <th class="border-0">By</th>
                            <th class="border-0">Remark</th>
                            <th class="border-0">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($receipts)): ?>
                            <tr>
                                <td colspan="16" class="text-center py-4">
                                    <i class="fas fa-folder-open text-muted fa-3x mb-3"></i>
                                    <p class="mb-0">No receipts found for selected period</p>
                                </td>
                            </tr>
                            <?php
                        else: ?>
                            <?php $sno = 1;
                            foreach ($receipts as $receipt): ?>
                                <tr>
                                    <td>
                                        <?php echo $sno++; ?>
                                    </td>
                                    <td>
                                        <button
                                            onclick="generateSecurePDF('<?php echo PORTAL_URL; ?>/modules/payments/receipt-print-pdf.php', { id: <?php echo $receipt['id']; ?> })"
                                            class="btn p-0 border-0 receipt-link text-primary shadow-none">
                                            <?php echo htmlspecialchars(($receipt['receipt_prefix'] ?? '') . $receipt['receipt_no']); ?>
                                        </button>
                                    </td>
                                    <td>
                                        <?php echo date('d-m-Y', strtotime($receipt['payment_date'])); ?>
                                    </td>
                                    <td>
                                        <strong>
                                            <?php echo htmlspecialchars($receipt['student_full_name'] ?? ''); ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($receipt['current_class'] ?? '-'); ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($receipt['group_name'] ?? '-'); ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($receipt['medium_name'] ?? '-'); ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?php echo htmlspecialchars($receipt['term_name'] ?? '-'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?php echo htmlspecialchars($receipt['payment_type'] ?? ''); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($receipt['receipt_title'] ?? '-'); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php
                                        $modeColors = [
                                            'cash' => 'success',
                                            'online' => 'primary',
                                            'upi' => 'info',
                                            'cheque' => 'warning',
                                            'card' => 'secondary'
                                        ];
                                        $modeColor = $modeColors[$receipt['payment_mode']] ?? 'dark';
                                        ?>
                                        <span class="badge mode-badge bg-<?php echo $modeColor; ?>">
                                            <?php echo strtoupper($receipt['payment_mode']); ?>
                                        </span>
                                        <?php if ($receipt['payment_mode'] == 'cheque'): ?>
                                            <?php if (!empty($receipt['cheque_no'])): ?>
                                                <br><small class="text-muted">No:
                                                    <?php echo htmlspecialchars($receipt['cheque_no'] ?? ''); ?></small>
                                                <?php
                                            endif; ?>
                                            <?php if (!empty($receipt['cheque_date'])): ?>
                                                <br><small class="text-muted">Date:
                                                    <?php echo date('d-m-Y', strtotime($receipt['cheque_date'])); ?></small>
                                                <?php
                                            endif; ?>
                                            <?php
                                        endif; ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($receipt['bank_name'] ?: '-' ?? ''); ?>
                                    </td>
                                    <td>
                                        <strong class="text-success">₹
                                            <?php echo formatIndianCurrency($receipt['amount']); ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <small>
                                            <?php echo htmlspecialchars($receipt['collected_by'] ?? 'System'); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($receipt['remarks'] ?: '-' ?? ''); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button
                                                onclick="generateSecurePDF('<?php echo PORTAL_URL; ?>/modules/payments/receipt-print-pdf.php', { id: <?php echo $receipt['id']; ?> })"
                                                class="btn btn-outline-primary" title="Print">
                                                <i class="fas fa-print"></i>
                                            </button>
                                            <a href="<?php echo PORTAL_URL; ?>/modules/payments/receipt-print-pdf.php?id=<?php echo $receipt['id']; ?>"
                                                class="btn btn-outline-info" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php
                            endforeach; ?>
                            <?php
                        endif; ?>
                    </tbody>
                    <tfoot class="table-secondary">
                        <tr>
                            <th colspan="12" class="text-end">Total:</th>
                            <th class="text-success">₹
                                <?php echo formatIndianCurrency($totalAmount); ?>
                            </th>
                            <th colspan="3"></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- Hidden Table for Full Export -->
    <table class="d-none" id="receiptExportTable">
        <thead>
            <tr>
                <th>S.No</th>
                <th>Receipt No</th>
                <th>Date</th>
                <th>Student Name</th>
                <th>Class</th>
                <th>Group</th>
                <th>Medium</th>
                <th>Term</th>
                <th>Type</th>
                <th>Mode</th>
                <th>Cheque No</th>
                <th>Cheque Date</th>
                <th>Bank Name</th>
                <th>Amount</th>
                <th>Collected By</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $ex_sno = 1;
            foreach ($all_receipts as $re): ?>
                <tr>
                    <td><?php echo $ex_sno++; ?></td>
                    <td><?php echo htmlspecialchars(($re['receipt_prefix'] ?? '') . $re['receipt_no']); ?></td>
                    <td><?php echo date('d-m-Y', strtotime($re['payment_date'])); ?></td>
                    <td><?php echo htmlspecialchars($re['student_full_name'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($re['current_class'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($re['group_name'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($re['medium_name'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($re['term_name'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($re['payment_type'] ?? ''); ?></td>
                    <td><?php echo strtoupper($re['payment_mode']); ?></td>
                    <td><?php echo htmlspecialchars($re['cheque_no'] ?: '-' ?? ''); ?></td>
                    <td><?php echo $re['cheque_date'] ? date('d-m-Y', strtotime($re['cheque_date'])) : '-'; ?></td>
                    <td><?php echo htmlspecialchars($re['bank_name'] ?: '-' ?? ''); ?></td>
                    <td><?php echo round($re['amount']); ?></td>
                    <td><?php echo htmlspecialchars($re['collected_by'] ?? 'System'); ?></td>
                    <td><?php echo htmlspecialchars($re['remarks'] ?: '-' ?? ''); ?></td>
                </tr>
                <?php
            endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="css-receipt-register-3157f5">
                <th colspan="13" class="css-receipt-register-7851db">Total Amount:</th>
                <th><?php echo round($totalAmount); ?></th>
                <th colspan="2"></th>
            </tr>
        </tfoot>
    </table>

    <!-- Pagination -->
    <?php
    $baseUrl = 'receipt-register.php?' . http_build_query(array_diff_key($_GET, ['page' => '']));
    echo renderPagination($current_page, $total_pages, $baseUrl, 2, $total_records, 'entries');
    ?>
</div>

<?php include '../../../include/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
    function exportToExcel() {
        const table = document.getElementById('receiptExportTable');
        // raw: true preserves the exact text (including leading zeros) from the table cells
        const ws = XLSX.utils.table_to_sheet(table, { raw: true });
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Receipt Register");
        XLSX.writeFile(wb, 'Receipt_Register_<?php echo $from_date; ?>_to_<?php echo $to_date; ?>.xlsx', { bookType: 'xlsx' });
    }

    function exportToPDF() {
        const params = {
            from_date: '<?php echo $from_date; ?>',
            to_date: '<?php echo $to_date; ?>',
            payment_mode: '<?php echo $payment_mode; ?>',
            payment_type: '<?php echo $payment_type_filter; ?>',
            receipt_config: '<?php echo $receipt_config; ?>',
            school_id: '<?php echo $school_filter; ?>',
            medium_id: '<?php echo $medium_filter; ?>',
            group_id: '<?php echo $group_filter; ?>',
            course_id: '<?php echo $course_filter; ?>',
            term_id: '<?php echo $term_filter; ?>',
            search: '<?php echo addslashes($search); ?>'
        };

        const queryString = new URLSearchParams(params).toString();
        window.location.href = 'receipt-register-pdf.php?' + queryString;
    }

    // Initialize DataTable if available
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof $.fn.DataTable !== 'undefined') {
            $('#receiptTable').DataTable({
                pageLength: 50,
                order: [[2, 'asc']],
                footerCallback: function (row, data, start, end, display) {
                    // Calculate total for current page if needed
                }
            });
        }
    });
</script>