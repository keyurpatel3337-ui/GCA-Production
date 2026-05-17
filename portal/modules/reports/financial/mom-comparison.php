<?php
/**
 * Month-on-Month Comparison Report
 * Compares current month with previous months
 */

require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/common/constants.php';
require_once __DIR__ . '/../../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once __DIR__ . '/../../../../common/helpers/format_helper.php';

// Check access
if (!hasRole(ROLE_ACCOUNTANT) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "Month-on-Month Comparison";
$page_breadcrumb = "MoM Comparison";

$dbOps = new DatabaseOperations();

// Get last 12 months data
$monthlyData = [];
for ($i = 11; $i >= 0; $i--) {
    $monthStart = date('Y-m-01', strtotime("-$i months"));
    $monthEnd = date('Y-m-t', strtotime("-$i months"));
    $monthLabel = date('M Y', strtotime("-$i months"));

    $result = $dbOps->customSelect(
        "SELECT 
            COUNT(*) as transaction_count,
            IFNULL(SUM(amount), 0) as total_amount,
            COUNT(DISTINCT student_id) as unique_students,
            IFNULL(SUM(CASE WHEN payment_mode = 'cash' THEN amount ELSE 0 END), 0) as cash,
            IFNULL(SUM(CASE WHEN payment_mode IN ('online', 'upi', 'card') THEN amount ELSE 0 END), 0) as online,
            IFNULL(SUM(CASE WHEN payment_mode = 'cheque' THEN amount ELSE 0 END), 0) as cheque
         FROM tbl_payments 
         WHERE status = 'paid' 
         AND payment_date BETWEEN ? AND ?",
        [$monthStart, $monthEnd]
    );

    $monthlyData[] = [
        'month' => $monthLabel,
        'month_short' => date('M', strtotime("-$i months")),
        'period' => "$monthStart to $monthEnd",
        'transactions' => $result[0]['transaction_count'],
        'amount' => $result[0]['total_amount'],
        'students' => $result[0]['unique_students'],
        'cash' => $result[0]['cash'],
        'online' => $result[0]['online'],
        'cheque' => $result[0]['cheque']
    ];
}

// Current and previous month comparison
$currentMonth = $monthlyData[11];
$previousMonth = $monthlyData[10];

$amountChange = $previousMonth['amount'] > 0
    ? (($currentMonth['amount'] - $previousMonth['amount']) / $previousMonth['amount']) * 100
    : 0;
$txnChange = $previousMonth['transactions'] > 0
    ? (($currentMonth['transactions'] - $previousMonth['transactions']) / $previousMonth['transactions']) * 100
    : 0;

// Chart data
$chartLabels = array_column($monthlyData, 'month_short');
$chartAmounts = array_column($monthlyData, 'amount');
$chartTransactions = array_column($monthlyData, 'transactions');

include '../../../include/header.php';
include '../../../include/navbar.php';
include '../../../include/sidebar.php';
?>

<link rel="stylesheet" href="<?php echo PORTAL_URL; ?>/assets/css/modules/reports/financial/mom-comparison.php.css">



<div class="container-fluid">
    <!-- Comparison Header -->
    <div class="comparison-header">
        <div class="row align-items-center">
            <div class="col-md-4">
                <div class="comparison-box">
                    <p>
                        <?php echo $previousMonth['month']; ?>
                    </p>
                    <h3>₹
                        <?php echo formatIndianCurrency($previousMonth['amount']); ?>
                    </h3>
                    <small>
                        <?php echo formatIndianCurrency($previousMonth['transactions'], false); ?> transactions
                    </small>
                </div>
            </div>
            <div class="col-md-4 text-center py-3">
                <div class="vs-badge">VS</div>
                <div class="mt-3">
                    <span class="change-indicator <?php echo $amountChange >= 0 ? 'change-up' : 'change-down'; ?>">
                        <i class="fas fa-arrow-<?php echo $amountChange >= 0 ? 'up' : 'down'; ?>"></i>
                        <?php echo abs(round($amountChange, 1)); ?>% in collection
                    </span>
                </div>
            </div>
            <div class="col-md-4">
                <div class="comparison-box">
                    <p>
                        <?php echo $currentMonth['month']; ?> (Current)
                    </p>
                    <h3>₹
                        <?php echo formatIndianCurrency($currentMonth['amount']); ?>
                    </h3>
                    <small>
                        <?php echo formatIndianCurrency($currentMonth['transactions'], false); ?> transactions
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <h4 class="text-success">₹
                        <?php echo formatIndianCurrency($currentMonth['amount'] - $previousMonth['amount']); ?>
                    </h4>
                    <p class="text-muted mb-0">Amount Difference</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <h4 class="<?php echo $txnChange >= 0 ? 'text-success' : 'text-danger'; ?>">
                        <?php echo ($txnChange >= 0 ? '+' : '') . round($txnChange, 1); ?>%
                    </h4>
                    <p class="text-muted mb-0">Transaction Growth</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <h4 class="text-primary">
                        <?php echo formatIndianCurrency($currentMonth['students'], false); ?>
                    </h4>
                    <p class="text-muted mb-0">Unique Students (This Month)</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <?php
                    $avgAmount = $currentMonth['transactions'] > 0
                        ? $currentMonth['amount'] / $currentMonth['transactions']
                        : 0;
                    ?>
                    <h4 class="text-info">₹
                        <?php echo formatIndianCurrency($avgAmount); ?>
                    </h4>
                    <p class="text-muted mb-0">Avg Transaction Value</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-line me-2"></i>12-Month Collection Trend
                    </h5>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-pie me-2"></i>This Month Breakdown
                    </h5>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="breakdownChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Export Buttons -->
    <div class="mb-3 d-flex justify-content-end gap-2">
        <button class="btn btn-success" onclick="exportToExcel()">
            <i class="fas fa-file-excel me-1"></i> Export Excel
        </button>
        <button class="btn btn-danger" onclick="window.print()">
            <i class="fas fa-print me-1"></i> Print
        </button>
    </div>

    <!-- Monthly Data Table -->
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="card-title mb-0">
                <i class="fas fa-table me-2"></i>
                12-Month Data Table
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover trend-table mb-0" id="trendTable">
                    <thead class="table-dark">
                        <tr>
                            <th>Month</th>
                            <th>Transactions</th>
                            <th>Students</th>
                            <th>Cash Collection</th>
                            <th>Online Collection</th>
                            <th>Cheque Collection</th>
                            <th>Total Collection</th>
                            <th>MoM Change</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $prevAmount = 0;
                        foreach ($monthlyData as $index => $month):
                            $momChange = $prevAmount > 0
                                ? (($month['amount'] - $prevAmount) / $prevAmount) * 100
                                : 0;
                            $rowClass = '';
                            if ($index == 11)
                                $rowClass = 'highlight-current';
                            elseif ($index == 10)
                                $rowClass = 'highlight-previous';
                            ?>
                            <tr class="<?php echo $rowClass; ?>">
                                <td>
                                    <strong>
                                        <?php echo $month['month']; ?>
                                    </strong>
                                    <?php if ($index == 11): ?>
                                        <span class="badge bg-success ms-1">Current</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo formatIndianCurrency($month['transactions'], false); ?>
                                </td>
                                <td>
                                    <?php echo formatIndianCurrency($month['students'], false); ?>
                                </td>
                                <td class="text-success">₹
                                    <?php echo formatIndianCurrency($month['cash']); ?>
                                </td>
                                <td class="text-primary">₹
                                    <?php echo formatIndianCurrency($month['online']); ?>
                                </td>
                                <td class="text-info">₹
                                    <?php echo formatIndianCurrency($month['cheque']); ?>
                                </td>
                                <td><strong>₹
                                        <?php echo formatIndianCurrency($month['amount']); ?>
                                    </strong></td>
                                <td>
                                    <?php if ($index > 0): ?>
                                        <span class="<?php echo $momChange >= 0 ? 'text-success' : 'text-danger'; ?>">
                                            <i class="fas fa-arrow-<?php echo $momChange >= 0 ? 'up' : 'down'; ?>"></i>
                                            <?php echo ($momChange >= 0 ? '+' : '') . round($momChange, 1); ?>%
                                        </span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php
                            $prevAmount = $month['amount'];
                        endforeach;
                        ?>
                    </tbody>
                    <tfoot class="table-secondary">
                        <tr>
                            <th>Total (12 Months)</th>
                            <th>
                                <?php echo formatIndianCurrency(array_sum(array_column($monthlyData, 'transactions')), false); ?>
                            </th>
                            <th>-</th>
                            <th class="text-success">₹
                                <?php echo formatIndianCurrency(array_sum(array_column($monthlyData, 'cash'))); ?>
                            </th>
                            <th class="text-primary">₹
                                <?php echo formatIndianCurrency(array_sum(array_column($monthlyData, 'online'))); ?>
                            </th>
                            <th class="text-info">₹
                                <?php echo formatIndianCurrency(array_sum(array_column($monthlyData, 'cheque'))); ?>
                            </th>
                            <th>₹
                                <?php echo formatIndianCurrency(array_sum(array_column($monthlyData, 'amount'))); ?>
                            </th>
                            <th>-</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../../../include/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/vendor/xlsx/xlsx.full.min.js"></script>
<script>
    // Trend Line Chart
    const trendCtx = document.getElementById('trendChart').getContext('2d');
    new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($chartLabels); ?>,
            datasets: [{
                label: 'Collection (₹)',
                data: <?php echo json_encode(array_map('floatval', $chartAmounts)); ?>,
                borderColor: '#667eea',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#667eea',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 5
            }, {
                label: 'Transactions',
                data: <?php echo json_encode(array_map('intval', $chartTransactions)); ?>,
                borderColor: '#28a745',
                backgroundColor: 'transparent',
                tension: 0.4,
                pointBackgroundColor: '#28a745',
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                intersect: false,
                mode: 'index'
            },
            scales: {
                y: {
                    beginAtZero: true,
                    position: 'left',
                    ticks: {
                        callback: function (value) {
                            return '₹' + value.toLocaleString();
                        }
                    }
                },
                y1: {
                    beginAtZero: true,
                    position: 'right',
                    grid: {
                        drawOnChartArea: false
                    }
                }
            }
        }
    });

    // Breakdown Donut Chart
    const breakdownCtx = document.getElementById('breakdownChart').getContext('2d');
    new Chart(breakdownCtx, {
        type: 'doughnut',
        data: {
            labels: ['Cash', 'Online/UPI/Card', 'Cheque'],
            datasets: [{
                data: [
                    <?php echo $currentMonth['cash']; ?>, 
                    <?php echo $currentMonth['online']; ?>,
                    <?php echo $currentMonth['cheque']; ?>
                ],
                backgroundColor: ['#28a745', '#007bff', '#17a2b8'],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });

    function exportToExcel() {
        const table = document.getElementById('trendTable');
        const wb = XLSX.utils.table_to_book(table, { sheet: "MoM Comparison" });
        XLSX.writeFile(wb, 'Month_on_Month_Comparison_<?php echo date('Y-m-d'); ?>.xlsx');
    }
</script>