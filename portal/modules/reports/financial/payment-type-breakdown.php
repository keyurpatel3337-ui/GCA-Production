<?php
/**
 * Payment Type Breakdown Report
 * Shows collection breakdown by payment type with nested payment mode details
 */

require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/common/constants.php';
require_once __DIR__ . '/../../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;

// Check access
if (!hasRole(ROLE_ACCOUNTANT) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "Payment Type Breakdown";
$page_breadcrumb = "Payment Type Breakdown";

// Helper function for Indian Number Formatting (e.g., 4,11,11,123)
// Include global format helper
require_once __DIR__ . '/../../../../common/helpers/format_helper.php';

// Get filters
$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date = $_GET['to_date'] ?? date('Y-m-d');
$school_filter = $_GET['school_id'] ?? '';
$medium_filter = $_GET['medium_id'] ?? '';
$group_filter = $_GET['group_id'] ?? '';
$course_filter = $_GET['course_id'] ?? '';

$dbOps = new DatabaseOperations();

// Get master data for filters
$schools = $dbOps->customSelect("SELECT id, school_name FROM tbl_schools ORDER BY school_name", []);
$mediums = $dbOps->customSelect("SELECT id, medium_name FROM tbl_medium ORDER BY medium_name", []);
$groups = $dbOps->customSelect("SELECT id, group_name FROM tbl_group ORDER BY group_name", []);

// Build query for payment type and mode breakdown
$sql = "SELECT 
            p.payment_type,
            p.payment_mode,
            COUNT(*) as transaction_count,
            SUM(p.amount) as total_amount
        FROM tbl_payments p
        JOIN tbl_gm_std_registration r ON p.student_id = r.id
        LEFT JOIN tbl_enrolled_students es ON es.registration_id = r.id AND es.is_active = 1
        WHERE p.status = 'paid'
        AND p.payment_date BETWEEN ? AND ?";

$params = [$from_date, $to_date];

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

$sql .= " GROUP BY p.payment_type, p.payment_mode
          ORDER BY p.payment_type, p.payment_mode";

$results = $dbOps->customSelect($sql, $params);

// Organize data by payment type with nested payment modes
$paymentTypeBreakdown = [];
$grandTotal = 0;
$grandCount = 0;

foreach ($results as $row) {
    $type = $row['payment_type'] ?? 'Other';
    $mode = strtoupper($row['payment_mode']);
    $amount = $row['total_amount'];
    $count = $row['transaction_count'];

    if (!isset($paymentTypeBreakdown[$type])) {
        $paymentTypeBreakdown[$type] = [
            'total_amount' => 0,
            'total_count' => 0,
            'modes' => []
        ];
    }

    $paymentTypeBreakdown[$type]['total_amount'] += $amount;
    $paymentTypeBreakdown[$type]['total_count'] += $count;
    $paymentTypeBreakdown[$type]['modes'][$mode] = [
        'amount' => $amount,
        'count' => $count
    ];

    $grandTotal += $amount;
    $grandCount += $count;
}

// Sort by total amount descending
uasort($paymentTypeBreakdown, function ($a, $b) {
    return $b['total_amount'] - $a['total_amount'];
});

include '../../../include/header.php';
include '../../../include/navbar.php';
include '../../../include/sidebar.php';
?>

<style>
    /* Common styling matching Receipt Register */
    .filter-card {
        background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
        border: none;
        border-radius: 12px;
    }

    .filter-card .form-label {
        color: white;
        font-weight: 500;
    }

    /* Stat Box Styling */
    .stat-box {
        background: white;
        border-radius: 12px;
        padding: 20px;
        text-align: center;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        border-left: 4px solid;
    }

    .stat-box.info {
        border-color: #17a2b8;
    }

    .stat-box.success {
        border-color: #28a745;
    }

    .stat-box.warning {
        border-color: #ffc107;
    }

    .stat-box.danger {
        border-color: #dc3545;
    }

    .stat-box h3 {
        font-size: 1.6rem;
        font-weight: 700;
        margin-bottom: 5px;
        /* Monospace for numbers alignment */
    }

    /* Breakdown Card Styling */
    .card-outline {
        border-top: 3px solid #007bff;
        border-radius: 8px;
        box-shadow: 0 0 1px rgba(0, 0, 0, .125), 0 1px 3px rgba(0, 0, 0, .2);
    }

    .card-header {
        background-color: transparent;
        border-bottom: 1px solid rgba(0, 0, 0, .125);
        padding: .75rem 1.25rem;
    }

    .mode-item {
        padding: 10px;
        border-bottom: 1px solid #eee;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .mode-item:last-child {
        border-bottom: none;
    }

    .mode-badge {
        font-size: 0.85rem;
        padding: 4px 8px;
    }

    .progress-bar-container {
        width: 100px;
        height: 6px;
        background-color: #e9ecef;
        border-radius: 3px;
        margin-left: 10px;
        display: inline-block;
        vertical-align: middle;
    }

    .progress-bar-fill {
        height: 100%;
        border-radius: 3px;
    }

    .amount-text {
        letter-spacing: -0.5px;
    }

    .no-data {
        text-align: center;
        padding: 60px 20px;
        color: #6c757d;
    }

    .no-data i {
        font-size: 4rem;
        margin-bottom: 20px;
        opacity: 0.3;
    }

    @media print {

        .filter-card,
        .btn,
        .breadcrumb,
        .no-print {
            display: none !important;
        }

        .card {
            border: 1px solid #ddd;
            box-shadow: none;
        }
    }
</style>



<div class="container-fluid">
    <!-- Filter Toggle Button -->
    <div class="mb-3 d-flex justify-content-between align-items-center no-print">
        <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#filterSection"
            aria-expanded="false" aria-controls="filterSection">
            <i class="fas fa-filter me-2"></i> Toggle Filters
        </button>
        <div class="btn-group">
            <button class="btn btn-success" onclick="exportToExcel()">
                <i class="fas fa-file-excel me-1"></i> Excel
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
                    <div class="col-md-2">
                        <label class="form-label">From Date</label>
                        <input type="date" name="from_date" class="form-control" value="<?php echo $from_date; ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">To Date</label>
                        <input type="date" name="to_date" class="form-control" value="<?php echo $to_date; ?>">
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
                    <div class="col-md-12 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-light">
                            <i class="fas fa-filter"></i> Apply
                        </button>
                        <a href="payment-type-breakdown.php" class="btn btn-outline-light">
                            <i class="fas fa-redo"></i> Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Summary Stats (Matching Receipt Register) -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-box success">
                <h3 class="text-success amount-text">₹<?php echo formatIndianCurrency($grandTotal); ?></h3>
                <p class="mb-0 text-muted">Total Collection</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-box info">
                <h3 class="text-info"><?php echo formatIndianCurrency($grandCount, false); ?></h3>
                <p class="mb-0 text-muted">Total Transactions</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-box warning">
                <h3 class="text-warning"><?php echo count($paymentTypeBreakdown); ?></h3>
                <p class="mb-0 text-muted">Payment Categories</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-box danger">
                <h3 class="text-danger amount-text">
                    ₹<?php echo $grandCount > 0 ? formatIndianCurrency($grandTotal / $grandCount) : 0; ?></h3>
                <p class="mb-0 text-muted">Avg. Transaction</p>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-list me-2"></i>
                        Type-wise Breakdown (<?php echo date('d-m-Y', strtotime($from_date)); ?> -
                        <?php echo date('d-m-Y', strtotime($to_date)); ?>)
                    </h5>
                </div>
                <div class="card-body bg-light">
                    <?php if (empty($paymentTypeBreakdown)): ?>
                        <div class="no-data">
                            <i class="fas fa-folder-open"></i>
                            <h4>No Data Found</h4>
                            <p>No payment records found for the selected period and filters.</p>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php
                            $colors = ['primary', 'success', 'info', 'warning', 'danger', 'secondary'];
                            $idx = 0;
                            foreach ($paymentTypeBreakdown as $type => $data):
                                $themeColor = $colors[$idx % count($colors)];
                                $idx++;
                                $percentage = $grandTotal > 0 ? ($data['total_amount'] / $grandTotal * 100) : 0;
                                ?>
                                <div class="col-md-6 mb-4">
                                    <div class="card card-outline border-top-<?php echo $themeColor; ?> h-100">
                                        <div class="card-header">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h5 class="mb-0 text-<?php echo $themeColor; ?> font-weight-bold">
                                                    <?php echo htmlspecialchars($type ?? ''); ?>
                                                </h5>
                                                <span class="badge bg-<?php echo $themeColor; ?>">
                                                    <?php echo number_format($percentage, 1); ?>%
                                                </span>
                                            </div>
                                        </div>
                                        <div class="card-body p-0">
                                            <div
                                                class="p-3 bg-light border-bottom d-flex justify-content-between align-items-end">
                                                <div>
                                                    <small class="text-muted text-uppercase">Total Amount</small>
                                                    <h4 class="mb-0 font-weight-bold amount-text">
                                                        ₹<?php echo formatIndianCurrency($data['total_amount']); ?></h4>
                                                </div>
                                                <div class="text-end">
                                                    <small
                                                        class="text-muted"><?php echo formatIndianCurrency($data['total_count'], false); ?>
                                                        txns</small>
                                                </div>
                                            </div>
                                            <div class="p-2">
                                                <?php foreach ($data['modes'] as $mode => $modeData):
                                                    $modePercentage = $data['total_amount'] > 0 ? ($modeData['amount'] / $data['total_amount'] * 100) : 0;

                                                    // Resolve badge color matching other pages
                                                    $badgeClass = match (strtolower($mode)) {
                                                        'cash' => 'bg-success',
                                                        'online' => 'bg-primary',
                                                        'upi' => 'bg-info',
                                                        'cheque' => 'bg-warning text-dark',
                                                        'card' => 'bg-secondary',
                                                        default => 'bg-dark'
                                                    };
                                                    ?>
                                                    <div class="mode-item">
                                                        <div class="d-flex align-items-center" style="width: 45%;">
                                                            <span class="badge <?php echo $badgeClass; ?> me-2"
                                                                style="width: 80px;"><?php echo $mode; ?></span>
                                                            <small
                                                                class="text-muted d-none d-sm-inline"><?php echo formatIndianCurrency($modeData['count'], false); ?>
                                                                txns</small>
                                                        </div>
                                                        <div class="d-flex align-items-center justify-content-end"
                                                            style="width: 55%;">
                                                            <div class="text-end me-2">
                                                                <div class="fw-bold amount-text">
                                                                    ₹<?php echo formatIndianCurrency($modeData['amount']); ?>
                                                                </div>
                                                                <div class="progress"
                                                                    style="height: 3px; width: 80px; margin-left: auto;">
                                                                    <div class="progress-bar <?php echo $badgeClass; ?>"
                                                                        style="width: <?php echo $modePercentage; ?>%"></div>
                                                                </div>
                                                            </div>
                                                            <small
                                                                class="text-muted w-25 text-end"><?php echo number_format($modePercentage, 0); ?>%</small>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../../include/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
    function exportToExcel() {
        const wb = XLSX.utils.book_new();
        const data = [
            ['Payment Type Breakdown Report'],
            ['Period: <?php echo date('d-m-Y', strtotime($from_date)); ?> to <?php echo date('d-m-Y', strtotime($to_date)); ?>'],
            [],
            ['Payment Type', 'Payment Mode', 'Transactions', 'Amount', '% of Type', '% of Total']
        ];

        <?php foreach ($paymentTypeBreakdown as $type => $typeData): ?>
            <?php foreach ($typeData['modes'] as $mode => $modeData): ?>
                data.push([
                    '<?php echo addslashes($type); ?>',
                    '<?php echo $mode; ?>',
                    <?php echo $modeData['count']; ?>,
                    <?php echo round($modeData['amount']); ?>,
                    <?php echo number_format($typeData['total_amount'] > 0 ? ($modeData['amount'] / $typeData['total_amount'] * 100) : 0, 2); ?>,
                    <?php echo number_format($grandTotal > 0 ? ($modeData['amount'] / $grandTotal * 100) : 0, 2); ?>
                ]);
            <?php endforeach; ?>
        <?php endforeach; ?>

        data.push([]);
        data.push(['Grand Total', '', <?php echo $grandCount; ?>, <?php echo round($grandTotal); ?>, '100.00', '100.00']);

        const ws = XLSX.utils.aoa_to_sheet(data);
        XLSX.utils.book_append_sheet(wb, ws, 'Payment Type Breakdown');
        XLSX.writeFile(wb, 'Payment_Type_Breakdown_<?php echo $from_date; ?>_to_<?php echo $to_date; ?>.xlsx');
    }

    function exportToPDF() {
        const params = new URLSearchParams(window.location.search);
        window.location.href = 'payment-type-breakdown-pdf.php?' + params.toString();
    }
</script>