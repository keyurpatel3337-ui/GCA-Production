<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once __DIR__ . '/../../common/api_client.php';
require_once PORTAL_GLOBALVARIABLE;
require_once __DIR__ . '/../../../common/helpers/format_helper.php';

// Check if user is Accountant, Principle or Super Admin
if (!hasRole(ROLE_ACCOUNTANT) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// Clear old session data if requested via GET
if (isset($_GET['clear'])) {
    unset($_SESSION['financial_reports_filters']);
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// Handle POST filters and store in session
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['clear_filters'])) {
        unset($_SESSION['financial_reports_filters']);
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    // If filters are posted, use them
    if (isset($_POST['chart_view']) || isset($_POST['date_range'])) {
        $_SESSION['financial_reports_filters'] = [
            'from_date' => $_POST['from_date'],
            'to_date' => $_POST['to_date'],
            'chart_view' => $_POST['chart_view'] ?? 'daily',
            'date_range' => $_POST['date_range'] ?? 'current_month'
        ];
    }

    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Get filters from session or use defaults
$filters = $_SESSION['financial_reports_filters'] ?? [
    'from_date' => date('Y-m-01'),
    'to_date' => date('Y-m-t'),
    'chart_view' => 'daily',
    'date_range' => 'current_month'
];

$requestParams = [
    'from_date' => $filters['from_date'] ?? date('Y-m-01'),
    'to_date' => $filters['to_date'] ?? date('Y-m-d'),
    'chart_view' => $filters['chart_view'] ?? 'daily'
];

$api = new APIClient();
$response = $api->get('payments/financial-reports', $requestParams);

if ($response && isset($response['success']) && $response['success']) {
    $collection_stats = $response['data']['collection_stats'] ?? ['count' => 0, 'total' => 0];
    $mode_breakdown = $response['data']['mode_breakdown'] ?? [];
    $type_breakdown = $response['data']['type_breakdown'] ?? [];
    $chart_data = $response['data']['chart_data'] ?? [];
    $daily_breakdown = $response['data']['daily_breakdown'] ?? [];
    $course_breakdown = $response['data']['course_breakdown'] ?? [];
    $comparison_stats = $response['data']['comparison_stats'] ?? ['today_total' => 0, 'yesterday_total' => 0, 'overall_total' => 0];
    $from_date = $response['data']['applied_filters']['from_date'] ?? date('Y-m-01');
    $to_date = $response['data']['applied_filters']['to_date'] ?? date('Y-m-d');
    $chart_view = $response['data']['applied_filters']['chart_view'] ?? 'daily';
} else {
    $collection_stats = ['count' => 0, 'total' => 0];
    $mode_breakdown = [];
    $type_breakdown = [];
    $chart_data = [];
    $daily_breakdown = [];
    $course_breakdown = [];
    $comparison_stats = ['today_total' => 0, 'yesterday_total' => 0, 'overall_total' => 0];
    $from_date = $filters['from_date'];
    $to_date = $filters['to_date'];
    $chart_view = $filters['chart_view'];
    set_flash_message('error', $response['error'] ?? 'Failed to load financial reports');
}

// For form display
$date_range = $filters['date_range'] ?? 'current_month';
$chart_view = $chart_view ?? 'daily';

$page_title = "Financial Reports";
$page_breadcrumb = "Reports";
?>
<?php include '../../include/header.php'; ?>
<?php include '../../include/navbar.php'; ?>
<?php include '../../include/sidebar.php'; ?>




<link rel="stylesheet" href="<?php echo PORTAL_URL; ?>/assets/css/modules/payments/financial-reports.php.css">

<div class="container-fluid py-4">
    <!-- Filters -->
    <div class="glass-card mb-4">
        <div class="report-filter-box">
            <form method="POST" id="reportForm">
                    <div class="row align-items-end g-3">
                        <div class="col-md-3">
                            <label class="form-label fw-bold small text-uppercase opacity-75">View Type</label>
                            <select name="chart_view" id="chart_view" class="form-select border-0 shadow-sm bg-white"
                                style="height: 45px;">
                                <option value="daily" <?php echo ($chart_view ?? 'daily') === 'daily' ? 'selected' : ''; ?>>
                                    Daily Breakdown</option>
                                <option value="weekly" <?php echo ($chart_view ?? '') === 'weekly' ? 'selected' : ''; ?>>
                                    Weekly Aggregation</option>
                                <option value="monthly" <?php echo ($chart_view ?? '') === 'monthly' ? 'selected' : ''; ?>>
                                    Monthly Summary</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small text-uppercase opacity-75">Quick Range</label>
                            <select name="date_range" id="date_range" class="form-select border-0 shadow-sm bg-white"
                                style="height: 45px;" onchange="handleDateRangeChange()">
                                <option value="current_month"
                                    <?php echo ($date_range ?? 'current_month') === 'current_month' ? 'selected' : ''; ?>>
                                    Current Month</option>
                                <option value="last_month"
                                    <?php echo ($date_range ?? '') === 'last_month' ? 'selected' : ''; ?>>Last Month
                                </option>
                                <option value="last_3_months"
                                    <?php echo ($date_range ?? '') === 'last_3_months' ? 'selected' : ''; ?>>Last 3 Months
                                </option>
                                <option value="last_6_months"
                                    <?php echo ($date_range ?? '') === 'last_6_months' ? 'selected' : ''; ?>>Last 6 Months
                                </option>
                                <option value="last_year"
                                    <?php echo ($date_range ?? '') === 'last_year' ? 'selected' : ''; ?>>Last Year</option>
                                <option value="current_financial_year"
                                    <?php echo ($date_range ?? '') === 'current_financial_year' ? 'selected' : ''; ?>>Current
                                    FY</option>
                                <option value="previous_financial_year"
                                    <?php echo ($date_range ?? '') === 'previous_financial_year' ? 'selected' : ''; ?>>
                                    Previous FY</option>
                                <option value="custom" <?php echo ($date_range ?? '') === 'custom' ? 'selected' : ''; ?>>
                                    Custom Range</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold small text-uppercase opacity-75">From</label>
                            <input type="date" name="from_date" id="from_date"
                                class="form-control border-0 shadow-sm bg-white" style="height: 45px;"
                                value="<?php echo $from_date; ?>"
                                <?php echo ($date_range ?? 'current_month') !== 'custom' ? 'readonly' : ''; ?>>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold small text-uppercase opacity-75">To</label>
                            <input type="date" name="to_date" id="to_date"
                                class="form-control border-0 shadow-sm bg-white" style="height: 45px;"
                                value="<?php echo $to_date; ?>"
                                <?php echo ($date_range ?? 'current_month') !== 'custom' ? 'readonly' : ''; ?>>
                        </div>
                        <div class="col-md-2 d-flex gap-2">
                            <button type="submit" class="btn btn-primary w-100 shadow-sm" style="height: 45px;">
                                <i class="fas fa-sync-alt"></i> Apply
                            </button>
                            <button type="button" onclick="exportToPDF()" class="btn btn-danger shadow-sm" style="height: 45px;">
                                <i class="fas fa-file-pdf me-1"></i> Export PDF
                            </button>
                        </div>
                    </div>

                    <script>
                        function exportToPDF() {
                            const from = document.getElementById('from_date').value;
                            const to = document.getElementById('to_date').value;
                            const view = document.getElementById('chart_view').value;
                            window.location.href = `financial-reports-pdf.php?from_date=${from}&to_date=${to}&chart_view=${view}`;
                        }

                        function handleDateRangeChange() {
                            const range = document.getElementById('date_range').value;
                            const fromInput = document.getElementById('from_date');
                            const toInput = document.getElementById('to_date');
                            const form = document.getElementById('reportForm');

                            const today = new Date();
                            const yyyy = today.getFullYear();
                            const mm = String(today.getMonth() + 1).padStart(2, '0');
                            const dd = String(today.getDate()).padStart(2, '0');
                            const todayStr = `${yyyy}-${mm}-${dd}`;

                            const formatDate = (date) => {
                                const y = date.getFullYear();
                                const m = String(date.getMonth() + 1).padStart(2, '0');
                                const d = String(date.getDate()).padStart(2, '0');
                                return `${y}-${m}-${d}`;
                            };

                            if (range === 'custom') {
                                fromInput.readOnly = false;
                                toInput.readOnly = false;
                                return; // Don't auto-submit for custom
                            } else {
                                fromInput.readOnly = true;
                                toInput.readOnly = true;
                            }

                            let fromDate = '';
                            let toDate = todayStr;

                            if (range === 'current_month') {
                                fromDate = `${yyyy}-${mm}-01`;
                                const lastDay = new Date(yyyy, today.getMonth() + 1, 0);
                                toDate = formatDate(lastDay);
                            } else if (range === 'last_month') {
                                const firstDayPrevMonth = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                                const lastDayPrevMonth = new Date(today.getFullYear(), today.getMonth(), 0);
                                fromDate = formatDate(firstDayPrevMonth);
                                toDate = formatDate(lastDayPrevMonth);
                            } else if (range === 'last_3_months') {
                                const d = new Date();
                                d.setMonth(d.getMonth() - 3);
                                fromDate = formatDate(d);
                            } else if (range === 'last_6_months') {
                                const d = new Date();
                                d.setMonth(d.getMonth() - 6);
                                fromDate = formatDate(d);
                            } else if (range === 'last_year') {
                                const d = new Date();
                                d.setFullYear(d.getFullYear() - 1);
                                fromDate = formatDate(d);
                            } else if (range === 'current_financial_year') {
                                let startYear = yyyy;
                                if (today.getMonth() < 3) startYear = yyyy - 1;
                                fromDate = `${startYear}-04-01`;
                                toDate = `${startYear + 1}-03-31`;
                            } else if (range === 'previous_financial_year') {
                                let startYear = yyyy;
                                if (today.getMonth() < 3) startYear = yyyy - 1;
                                const prevFyStart = new Date(startYear - 1, 3, 1);
                                const prevFyEnd = new Date(startYear, 2, 31);
                                fromDate = formatDate(prevFyStart);
                                toDate = formatDate(prevFyEnd);
                            }

                            fromInput.value = fromDate;
                            toInput.value = toDate;
                            form.submit();
                        }
                    </script>
                </form>
            </div>
        </div>


        <!-- Period Summary (Selected Range) -->
        <div class="d-flex align-items-center mb-3 mt-4">
            <h6 class="stat-label mb-0 text-primary">
                <i class="fas fa-calendar-alt me-2"></i> Report Period: 
                <span class="text-dark fw-bold"><?php echo date('d M Y', strtotime($from_date)); ?> - <?php echo date('d M Y', strtotime($to_date)); ?></span>
            </h6>
        </div>

        <!-- Summary Cards -->
        <div class="row g-4 mb-4">
            <div class="col-xl-6">
                <div class="glass-card h-100">
                    <div class="stat-card">
                        <div class="stat-top">
                            <div>
                                <div class="stat-value"><?php echo $collection_stats['count']; ?></div>
                                <div class="stat-label">Total Transactions</div>
                            </div>
                            <div class="stat-icon bg-icon-info">
                                <i class="fas fa-receipt"></i>
                            </div>
                        </div>
                        <div class="mt-2">
                            <span class="badge bg-soft-info text-info bg-opacity-10 px-0">
                                <i class="fas fa-clock me-1"></i> For selected period
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="glass-card h-100">
                    <div class="stat-card">
                        <div class="stat-top">
                            <div>
                                <div class="stat-value">
                                    ₹<?php echo formatIndianCurrency($collection_stats['total'] ?? 0); ?>
                                </div>
                                <div class="stat-label">Total Collection</div>
                            </div>
                            <div class="stat-icon bg-icon-success">
                                <i class="fas fa-wallet"></i>
                            </div>
                        </div>
                        <div class="mt-2">
                            <span class="badge bg-soft-success text-success bg-opacity-10 px-0">
                                <i class="fas fa-chart-line me-1"></i> Total combined revenue
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Collection Chart -->
        <div class="row g-4 mb-4">
            <div class="col-md-8">
                <div class="glass-card h-100">
                    <div class="card-header bg-transparent border-0 px-4 pt-4">
                        <h5 class="fw-bold mb-0 text-primary">
                            <i class="fas fa-chart-area me-2"></i> Collection Trend
                        </h5>
                    </div>
                    <div class="card-body px-4 pb-4">
                        <canvas id="collectionChart" style="height: 320px;"></canvas>
                    </div>
                </div>
            </div>

            <!-- Payment Mode Pie Chart -->
            <div class="col-md-4">
                <div class="glass-card h-100">
                    <div class="card-header bg-transparent border-0 px-4 pt-4">
                        <h5 class="fw-bold mb-0 text-primary">
                            <i class="fas fa-chart-pie me-2"></i> Mode Distribution
                        </h5>
                    </div>
                    <div class="card-body px-4 pb-4">
                        <canvas id="modeChart" style="height: 320px;"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Breakdown Tables -->
        <div class="row g-4 mb-4" id="mode">
            <!-- Payment Mode Breakdown -->
            <div class="col-md-6">
                <div class="glass-card">
                    <div class="card-header bg-transparent border-0 px-4 pt-4">
                        <h6 class="stat-label mb-0">
                            <i class="fas fa-credit-card me-2 text-primary"></i> Mode Breakdown
                        </h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-enhanced mb-0">
                                <thead>
                                    <tr>
                                        <th class="ps-4">Payment Mode</th>
                                        <th>Count</th>
                                        <th class="pe-4 text-end">Total Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($mode_breakdown as $mode): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <span class="badge bg-soft-info text-info bg-opacity-10 text-uppercase fw-bold" style="letter-spacing: 0.5px;">
                                                    <?php echo htmlspecialchars($mode['payment_mode'] ?? ''); ?>
                                                </span>
                                            </td>
                                            <td class="fw-medium text-muted"><?php echo $mode['count']; ?></td>
                                            <td class="pe-4 text-end text-dark fw-bold">
                                                ₹<?php echo formatIndianCurrency($mode['total']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Type Breakdown -->
            <div class="col-md-6">
                <div class="glass-card">
                    <div class="card-header bg-transparent border-0 px-4 pt-4">
                        <h6 class="stat-label mb-0">
                            <i class="fas fa-tags me-2 text-primary"></i> Type Breakdown
                        </h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-enhanced mb-0">
                                <thead>
                                    <tr>
                                        <th class="ps-4">Payment Type</th>
                                        <th>Count</th>
                                        <th class="pe-4 text-end">Total Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($type_breakdown as $type): ?>
                                        <tr>
                                            <td class="ps-4 fw-medium text-dark">
                                                <?php echo htmlspecialchars($type['payment_type'] ?? ''); ?>
                                            </td>
                                            <td class="text-muted"><?php echo $type['count']; ?></td>
                                            <td class="pe-4 text-end text-dark fw-bold">
                                                ₹<?php echo formatIndianCurrency($type['total']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Course-wise Breakdown -->
        <div class="glass-card mb-4" id="course">
            <div class="card-header bg-transparent border-0 px-4 pt-4 d-flex align-items-center justify-content-between">
                <h6 class="stat-label mb-0">
                    <i class="fas fa-graduation-cap me-2 text-primary"></i> Course-wise Collection Breakdown
                    <small class="text-muted fw-normal ms-2" style="font-size:0.75rem; text-transform:none;">(click a row to expand)</small>
                </h6>
                <button class="btn btn-sm btn-success px-3 shadow-sm" onclick="exportTableToExcel('courseTable')">
                    <i class="fas fa-file-excel me-1"></i> Export Excel
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive px-4 pb-4">
                    <table class="table table-enhanced mb-0" id="courseTable">
                        <thead>
                            <tr>
                                <th class="ps-4" style="width:32px;"></th>
                                <th>COURSE</th>
                                <th class="text-center">STUDENTS</th>
                                <th class="text-center">TRANSACTIONS</th>
                                <th class="text-center">CASH</th>
                                <th class="text-center">ONLINE</th>
                                <th class="text-center">OFFLINE</th>
                                <th class="pe-4 text-end">TOTAL COLLECTION</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($course_breakdown)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-5">
                                        <div class="opacity-50">
                                            <i class="fas fa-folder-open fs-2 mb-2"></i>
                                            <p class="mb-0">No data available for selected period</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php
                                $grand_students = 0; $grand_txn = 0;
                                $grand_cash = 0; $grand_online = 0; $grand_offline = 0; $grand_total = 0;
                                foreach ($course_breakdown as $idx => $row):
                                    $grand_students += intval($row['student_count']);
                                    $grand_txn      += intval($row['transaction_count']);
                                    $grand_cash     += floatval($row['cash']);
                                    $grand_online   += floatval($row['online_amount']);
                                    $grand_offline  += floatval($row['offline_amount']);
                                    $grand_total    += floatval($row['total_collection']);
                                    $rowId = 'course-detail-' . $idx;
                                ?>
                                <!-- Summary row -->
                                <tr class="course-summary-row" data-target="<?php echo $rowId; ?>" style="cursor:pointer;">
                                    <td class="ps-4 text-center">
                                        <i class="fas fa-chevron-right course-chevron text-muted" style="font-size:0.75rem; transition:transform 0.2s;"></i>
                                    </td>
                                    <td class="fw-bold text-dark"><?php echo htmlspecialchars($row['course_name'] ?? ''); ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-primary bg-opacity-10 text-primary fw-bold px-2">
                                            <?php echo number_format($row['student_count']); ?>
                                        </span>
                                    </td>
                                    <td class="text-center text-muted fw-medium"><?php echo number_format($row['transaction_count']); ?></td>
                                    <td class="text-center text-warning fw-medium">₹<?php echo formatIndianCurrency($row['cash']); ?></td>
                                    <td class="text-center text-primary fw-medium">₹<?php echo formatIndianCurrency($row['online_amount']); ?></td>
                                    <td class="text-center text-secondary fw-medium">₹<?php echo formatIndianCurrency($row['offline_amount']); ?></td>
                                    <td class="pe-4 text-end text-dark fw-bold" style="font-size:1.05rem;">₹<?php echo formatIndianCurrency($row['total_collection']); ?></td>
                                </tr>
                                <!-- Expandable detail row -->
                                <tr id="<?php echo $rowId; ?>" class="course-detail-row" style="display:none;">
                                    <td colspan="8" class="p-0" style="background:#f8faff; border-top:none;">
                                        <div class="px-4 py-3">
                                            <div class="row g-3">
                                                <!-- Type breakdown -->
                                                <div class="col-md-7">
                                                    <div class="fw-bold text-muted small text-uppercase mb-2" style="letter-spacing:0.5px;">
                                                        <i class="fas fa-tags me-1 text-primary"></i> Fee Type Breakdown
                                                    </div>
                                                    <table class="table table-sm table-borderless mb-0" style="font-size:0.85rem;">
                                                        <thead>
                                                            <tr style="color:#64748b; font-size:0.75rem; text-transform:uppercase;">
                                                                <th class="ps-0">Type</th>
                                                                <th class="text-center">Txn</th>
                                                                <th class="text-end pe-0">Amount</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($row['type_breakdown'] as $t): ?>
                                                            <tr>
                                                                <td class="ps-0 text-dark"><?php echo htmlspecialchars($t['payment_type'] ?? 'N/A'); ?></td>
                                                                <td class="text-center text-muted"><?php echo number_format($t['transaction_count']); ?></td>
                                                                <td class="text-end pe-0 fw-medium text-dark">₹<?php echo formatIndianCurrency($t['total']); ?></td>
                                                            </tr>
                                                            <?php endforeach; ?>
                                                            <?php if (empty($row['type_breakdown'])): ?>
                                                            <tr><td colspan="3" class="text-muted ps-0">No data</td></tr>
                                                            <?php endif; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                                <!-- Mode breakdown -->
                                                <div class="col-md-5">
                                                    <div class="fw-bold text-muted small text-uppercase mb-2" style="letter-spacing:0.5px;">
                                                        <i class="fas fa-credit-card me-1 text-success"></i> Payment Mode Breakdown
                                                    </div>
                                                    <table class="table table-sm table-borderless mb-0" style="font-size:0.85rem;">
                                                        <thead>
                                                            <tr style="color:#64748b; font-size:0.75rem; text-transform:uppercase;">
                                                                <th class="ps-0">Mode</th>
                                                                <th class="text-center">Txn</th>
                                                                <th class="text-end pe-0">Amount</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($row['mode_breakdown'] as $m): ?>
                                                            <tr>
                                                                <td class="ps-0">
                                                                    <span class="badge text-uppercase fw-bold" style="font-size:0.7rem; background:<?php
                                                                        $mc = strtolower($m['payment_mode'] ?? '');
                                                                        echo match(true) {
                                                                            in_array($mc, ['online','upi','bank transfer','bank_transfer']) => '#dbeafe; color:#1d4ed8',
                                                                            $mc === 'cash'    => '#fef9c3; color:#92400e',
                                                                            $mc === 'cheque'  => '#dcfce7; color:#166534',
                                                                            default           => '#f1f5f9; color:#475569'
                                                                        };
                                                                    ?>;">
                                                                        <?php echo htmlspecialchars($m['payment_mode'] ?? 'N/A'); ?>
                                                                    </span>
                                                                </td>
                                                                <td class="text-center text-muted"><?php echo number_format($m['transaction_count']); ?></td>
                                                                <td class="text-end pe-0 fw-medium text-dark">₹<?php echo formatIndianCurrency($m['total']); ?></td>
                                                            </tr>
                                                            <?php endforeach; ?>
                                                            <?php if (empty($row['mode_breakdown'])): ?>
                                                            <tr><td colspan="3" class="text-muted ps-0">No data</td></tr>
                                                            <?php endif; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <!-- Grand Total Row -->
                                <tr style="background:#f0f4ff; border-top:2px solid #2563eb;">
                                    <td class="ps-4"></td>
                                    <td class="fw-bold text-primary" style="font-size:1rem;">GRAND TOTAL</td>
                                    <td class="text-center fw-bold text-primary"><?php echo number_format($grand_students); ?></td>
                                    <td class="text-center fw-bold"><?php echo number_format($grand_txn); ?></td>
                                    <td class="text-center fw-bold text-warning">₹<?php echo formatIndianCurrency($grand_cash); ?></td>
                                    <td class="text-center fw-bold text-primary">₹<?php echo formatIndianCurrency($grand_online); ?></td>
                                    <td class="text-center fw-bold text-secondary">₹<?php echo formatIndianCurrency($grand_offline); ?></td>
                                    <td class="pe-4 text-end fw-bold text-success" style="font-size:1.1rem;">₹<?php echo formatIndianCurrency($grand_total); ?></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Daily Collection Breakdown (Pivot Table) -->
        <div class="glass-card mb-5" id="daily">
            <div
                class="card-header bg-transparent border-0 px-4 pt-4 d-flex align-items-center justify-content-between">
                <h6 class="stat-label mb-0">
                    <i class="fas fa-table me-2 text-primary"></i> Daily Collection Breakdown
                </h6>
                <div class="card-tools">
                    <button class="btn btn-sm btn-success px-3 shadow-sm" onclick="exportTableToExcel('dailyTable')">
                        <i class="fas fa-file-excel me-1"></i> Export Excel
                    </button>
                    <a href="financial-reports.php?clear=1" class="btn btn-sm btn-light border ms-2">
                        <i class="fas fa-redo"></i>
                    </a>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive px-4 pb-4">
                    <table class="table table-enhanced mb-0" id="dailyTable">
                        <thead>
                            <tr>
                                <th class="ps-4 text-start">DATE</th>
                                <th class="text-center">ONLINE</th>
                                <th class="text-center">OFFLINE</th>
                                <?php
                                $payment_types = $daily_breakdown['payment_types'] ?? [];
                                foreach ($payment_types as $pt): ?>
                                    <th class="text-center"><?php echo strtoupper(htmlspecialchars($pt ?? '')); ?></th>
                                <?php endforeach; ?>
                                <th class="pe-4 text-end">TOTAL</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $pivot_data = $daily_breakdown['data'] ?? [];
                            if (empty($pivot_data)): ?>
                                <tr>
                                    <td colspan="<?php echo 4 + count($payment_types); ?>" class="text-center py-5">
                                        <div class="opacity-50">
                                            <i class="fas fa-folder-open fs-2 mb-2"></i>
                                            <p class="mb-0">No data available for selected period</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($pivot_data as $row): ?>
                                    <tr>
                                        <td class="ps-4 fw-bold text-dark">
                                            <?php echo date('d M, Y', strtotime($row['date'])); ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="text-primary fw-medium">₹<?php echo formatIndianCurrency($row['online_total']); ?></span>
                                        </td>
                                        <td class="text-center">
                                            <span class="text-secondary fw-medium">₹<?php echo formatIndianCurrency($row['offline_total']); ?></span>
                                        </td>
                                        <?php foreach ($payment_types as $pt): ?>
                                            <td class="text-center text-muted">
                                                ₹<?php echo formatIndianCurrency($row['types'][$pt] ?? 0); ?></td>
                                        <?php endforeach; ?>
                                        <td class="pe-4 text-end text-dark fw-bold" style="font-size: 1.05rem;">
                                            ₹<?php echo formatIndianCurrency($row['day_total']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <?php include '../../include/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Collection Trend Chart
        var ctx1 = document.getElementById('collectionChart').getContext('2d');
        var gradient = ctx1.createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, 'rgba(37, 99, 235, 0.15)');
        gradient.addColorStop(1, 'rgba(37, 99, 235, 0.01)');

        var collectionChart = new Chart(ctx1, {
            type: 'line',
            data: {
                labels: [<?php echo implode(',', array_map(function ($d) {
                    return '"' . ($d['date'] ?? $d['month'] ?? $d['week']) . '"';
                }, $chart_data)); ?>],
                datasets: [{
                    label: 'Collection',
                    data: [<?php echo implode(',', array_column($chart_data, 'total')); ?>],
                    backgroundColor: gradient,
                    borderColor: '#2563eb',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#2563eb',
                    pointBorderWidth: 2,
                    pointHoverRadius: 6,
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index',
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        padding: 12,
                        titleFont: { size: 14, weight: 'bold' },
                        bodyFont: { size: 13 },
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                return ' Collection: \u20B9' + context.raw.toLocaleString('en-IN');
                            }
                        }
                    }
                },
                scales: {
                    x: { 
                        grid: { display: false },
                        ticks: { font: { size: 11 } }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0,0,0,0.05)' },
                        ticks: {
                            font: { size: 11 },
                            callback: function (value) {
                                if (value >= 100000) return '\u20B9' + (value / 100000).toFixed(1) + 'L';
                                if (value >= 1000) return '\u20B9' + (value / 1000).toFixed(0) + 'K';
                                return '\u20B9' + value;
                            }
                        }
                    }
                }
            }
        });

        // Payment Mode Pie Chart
        var ctx2 = document.getElementById('modeChart').getContext('2d');
        var modeChart = new Chart(ctx2, {
            type: 'doughnut',
            data: {
                labels: [<?php echo implode(',', array_map(function ($m) {
                    return '"' . strtoupper($m['payment_mode']) . '"';
                }, $mode_breakdown)); ?>],
                datasets: [{
                    data: [<?php echo implode(',', array_column($mode_breakdown, 'total')); ?>],
                    backgroundColor: [
                        '#2563eb', // primary
                        '#10b981', // success
                        '#f59e0b', // warning
                        '#06b6d4', // info
                        '#ef4444', // danger
                        '#8b5cf6'  // purple
                    ],
                    hoverOffset: 15,
                    borderWidth: 2,
                    borderColor: '#fff',
                    cutout: '75%'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { 
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 20,
                            font: { size: 11, weight: '600' }
                        }
                    },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                let total = context.dataset.data.reduce((a, b) => a + b, 0);
                                let percentage = ((context.raw / total) * 100).toFixed(1);
                                return ' ' + context.label + ': \u20B9' + context.raw.toLocaleString('en-IN') + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });

        // Course row expand / collapse
        $(document).on('click', '.course-summary-row', function () {
            const targetId = $(this).data('target');
            const $detail = $('#' + targetId);
            const $chevron = $(this).find('.course-chevron');
            const isOpen = $detail.is(':visible');

            // Close all other open rows first
            $('.course-detail-row:visible').slideUp(150);
            $('.course-chevron').css('transform', 'rotate(0deg)');

            if (!isOpen) {
                $detail.slideDown(200);
                $chevron.css('transform', 'rotate(90deg)');
            }
        });

        function exportTableToExcel(tableId) {
            const table = document.getElementById(tableId);
            if (!table) return;
            const wb = XLSX.utils.book_new();
            const ws = XLSX.utils.table_to_sheet(table);
            XLSX.utils.book_append_sheet(wb, ws, 'Report');
            XLSX.writeFile(wb, tableId + '_export.xlsx');
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js"></script>