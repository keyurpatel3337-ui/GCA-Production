<?php
/**
 * Target vs Actual Collection Report
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

$page_title = "Target vs Actual";

$dbOps = new DatabaseOperations();

// Calculate expected fee from all active students
$expected = $dbOps->customSelect(
    "SELECT SUM(sfa.allocated_amount) as target FROM tbl_student_fee_allocation sfa
     JOIN tbl_enrolled_students es ON sfa.student_id = es.registration_id
     WHERE es.is_active = 1",
    []
);
$target = $expected[0]['target'] ?? 0;

// Actual collected
$actual = $dbOps->customSelect(
    "SELECT SUM(amount) as collected FROM tbl_payments WHERE status = 'paid'",
    []
);
$collected = $actual[0]['collected'] ?? 0;

$pending = $target - $collected;
$percentage = $target > 0 ? ($collected / $target) * 100 : 0;

include '../../../include/header.php';
include '../../../include/navbar.php';
include '../../../include/sidebar.php';
?>



<div class="container-fluid text-end mb-3">
    <button class="btn btn-danger btn-sm" onclick="exportToPDF()">
        <i class="fas fa-file-pdf me-1"></i> Export PDF
    </button>
</div>

<div class="container-fluid">
    <div class="target-header">
        <h2>
            <?php echo round($percentage, 1); ?>% Achieved
        </h2>
        <p class="mb-0">Collection Progress</p>
        <div class="progress mt-3 css-target-vs-actual-8f49fe">
            <div class="progress-bar bg-white css-target-vs-actual-f0f213">
                ₹
                <?php echo formatIndianCurrency($collected); ?>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <h3 class="text-primary">₹
                        <?php echo formatIndianCurrency($target); ?>
                    </h3>
                    <p class="text-muted mb-0">Target (Total Expected)</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <h3 class="text-success">₹
                        <?php echo formatIndianCurrency($collected); ?>
                    </h3>
                    <p class="text-muted mb-0">Actual Collected</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <h3 class="text-danger">₹
                        <?php echo formatIndianCurrency($pending); ?>
                    </h3>
                    <p class="text-muted mb-0">Pending Collection</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <canvas id="targetChart" class="css-target-vs-actual-a0b01d"></canvas>
                </div>
                <div class="col-md-6">
                    <h5>Collection Analysis</h5>
                    <table class="table">
                        <tr>
                            <td>Total Fee Expected</td>
                            <td class="text-end"><strong>₹
                                    <?php echo formatIndianCurrency($target); ?>
                                </strong></td>
                        </tr>
                        <tr>
                            <td>Amount Collected</td>
                            <td class="text-end text-success"><strong>₹
                                    <?php echo formatIndianCurrency($collected); ?>
                                </strong></td>
                        </tr>
                        <tr>
                            <td>Amount Pending</td>
                            <td class="text-end text-danger"><strong>₹
                                    <?php echo formatIndianCurrency($pending); ?>
                                </strong></td>
                        </tr>
                        <tr>
                            <td>Collection Rate</td>
                            <td class="text-end"><strong>
                                    <?php echo round($percentage, 1); ?>%
                                </strong></td>
                        </tr>
                    </table>
                    <a href="pending-fees.php" class="btn btn-warning">View Pending Fees</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    function exportToPDF() {
        window.location.href = 'target-vs-actual-pdf.php';
    }

    new Chart(document.getElementById('targetChart'), {
        type: 'doughnut',
        data: {
            labels: ['Collected', 'Pending'],
            datasets: [{
                data: [<?php echo $collected; ?>, <?php echo $pending; ?>],
                backgroundColor: ['#28a745', '#dc3545'],
                borderWidth: 0
            }]
        },
        options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
    });
</script>

<?php include '../../../include/footer.php'; ?>