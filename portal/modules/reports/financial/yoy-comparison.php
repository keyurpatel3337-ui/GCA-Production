<?php
/**
 * Year-on-Year Comparison Report
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

$page_title = "Year-on-Year Comparison";

$dbOps = new DatabaseOperations();

$currentYear = date('Y');
$previousYear = $currentYear - 1;

$currentYearData = $dbOps->customSelect(
    "SELECT MONTH(payment_date) as month, SUM(amount) as total
     FROM tbl_payments WHERE status = 'paid' AND YEAR(payment_date) = ?
     GROUP BY MONTH(payment_date)",
    [$currentYear]
);

$previousYearData = $dbOps->customSelect(
    "SELECT MONTH(payment_date) as month, SUM(amount) as total
     FROM tbl_payments WHERE status = 'paid' AND YEAR(payment_date) = ?
     GROUP BY MONTH(payment_date)",
    [$previousYear]
);

$currentByMonth = array_column($currentYearData, 'total', 'month');
$previousByMonth = array_column($previousYearData, 'total', 'month');

$months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$currentTotals = [];
$previousTotals = [];

for ($i = 1; $i <= 12; $i++) {
    $currentTotals[] = $currentByMonth[$i] ?? 0;
    $previousTotals[] = $previousByMonth[$i] ?? 0;
}

$totalCurrent = array_sum($currentTotals);
$totalPrevious = array_sum($previousTotals);
$yoyChange = $totalPrevious > 0 ? (($totalCurrent - $totalPrevious) / $totalPrevious) * 100 : 0;

include '../../../include/header.php';
include '../../../include/navbar.php';
include '../../../include/sidebar.php';
?>

<style>
    .yoy-header {
        background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
        border-radius: 12px;
        padding: 30px;
        color: white;
        margin-bottom: 25px;
    }

    .year-box {
        text-align: center;
        padding: 20px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 8px;
    }

    .year-box h3 {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 5px;
    }

    .chart-container {
        position: relative;
        height: 400px;
    }
</style>

<div class="container-fluid text-end mb-3">
    <button class="btn btn-danger btn-sm" onclick="exportToPDF()">
        <i class="fas fa-file-pdf me-1"></i> Export PDF
    </button>
</div>

<div class="container-fluid">
    <div class="yoy-header">
        <div class="row align-items-center">
            <div class="col-md-4">
                <div class="year-box">
                    <p>
                        <?php echo $previousYear; ?>
                    </p>
                    <h3>₹
                        <?php echo formatIndianCurrency($totalPrevious); ?>
                    </h3>
                </div>
            </div>
            <div class="col-md-4 text-center">
                <div class="badge <?php echo $yoyChange >= 0 ? 'bg-success' : 'bg-danger'; ?>"
                    style="font-size: 1.2rem; padding: 10px 20px;">
                    <i class="fas fa-arrow-<?php echo $yoyChange >= 0 ? 'up' : 'down'; ?>"></i>
                    <?php echo ($yoyChange >= 0 ? '+' : '') . round($yoyChange, 1); ?>% YoY
                </div>
            </div>
            <div class="col-md-4">
                <div class="year-box">
                    <p>
                        <?php echo $currentYear; ?> (Current)
                    </p>
                    <h3>₹
                        <?php echo formatIndianCurrency($totalCurrent); ?>
                    </h3>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0"><i class="fas fa-chart-line me-2"></i>Monthly Comparison</h5>
        </div>
        <div class="card-body">
            <div class="chart-container">
                <canvas id="yoyChart"></canvas>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="yoyTable">
                    <thead class="table-dark">
                        <tr>
                            <th>Month</th>
                            <th>
                                <?php echo $previousYear; ?>
                            </th>
                            <th>
                                <?php echo $currentYear; ?>
                            </th>
                            <th>Change</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for ($i = 0; $i < 12; $i++):
                            $change = $previousTotals[$i] > 0 ? (($currentTotals[$i] - $previousTotals[$i]) / $previousTotals[$i]) * 100 : 0;
                            ?>
                            <tr>
                                <td><strong>
                                        <?php echo $months[$i]; ?>
                                    </strong></td>
                                <td>₹
                                    <?php echo formatIndianCurrency($previousTotals[$i]); ?>
                                </td>
                                <td>₹
                                    <?php echo formatIndianCurrency($currentTotals[$i]); ?>
                                </td>
                                <td><span class="<?php echo $change >= 0 ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo ($change >= 0 ? '+' : '') . round($change, 1); ?>%
                                    </span></td>
                            </tr>
                        <?php endfor; ?>
                    </tbody>
                    <tfoot class="table-secondary">
                        <tr>
                            <th>Total</th>
                            <th>₹
                                <?php echo formatIndianCurrency($totalPrevious); ?>
                            </th>
                            <th>₹
                                <?php echo formatIndianCurrency($totalCurrent); ?>
                            </th>
                            <th class="<?php echo $yoyChange >= 0 ? 'text-success' : 'text-danger'; ?>">
                                <?php echo ($yoyChange >= 0 ? '+' : '') . round($yoyChange, 1); ?>%
                            </th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    function exportToPDF() {
        window.location.href = 'yoy-comparison-pdf.php';
    }

    const ctx = document.getElementById('yoyChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($months); ?>,
            datasets: [{
                label: '<?php echo $previousYear; ?>',
                data: <?php echo json_encode(array_map('floatval', $previousTotals)); ?>,
                backgroundColor: 'rgba(108, 117, 125, 0.7)',
                borderRadius: 4
            }, {
                label: '<?php echo $currentYear; ?>',
                data: <?php echo json_encode(array_map('floatval', $currentTotals)); ?>,
                backgroundColor: 'rgba(102, 126, 234, 0.9)',
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { y: { beginAtZero: true, ticks: { callback: v => '₹' + v.toLocaleString() } } }
        }
    });
</script>

<?php include '../../../include/footer.php'; ?>