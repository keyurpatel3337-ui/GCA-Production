<?php
/**
 * Term-wise Fee Report
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

$page_title = "Term-wise Fee Report";
$dbOps = new DatabaseOperations();

$year = date('Y');
$terms = [
    ['name' => 'Term 1 (Apr-Jul)', 'start' => $year . '-04-01', 'end' => $year . '-07-31'],
    ['name' => 'Term 2 (Aug-Nov)', 'start' => $year . '-08-01', 'end' => $year . '-11-30'],
    ['name' => 'Term 3 (Dec-Mar)', 'start' => $year . '-12-01', 'end' => ($year + 1) . '-03-31'],
];

$termData = [];
foreach ($terms as $term) {
    $result = $dbOps->customSelect(
        "SELECT COUNT(*) as count, IFNULL(SUM(amount), 0) as total FROM tbl_payments WHERE status = 'paid' AND payment_date BETWEEN ? AND ?",
        [$term['start'], $term['end']]
    );
    $termData[] = ['name' => $term['name'], 'count' => $result[0]['count'], 'total' => $result[0]['total']];
}
$grandTotal = array_sum(array_column($termData, 'total'));

include '../../../include/header.php';
include '../../../include/navbar.php';
include '../../../include/sidebar.php';
?>

<div class="container-fluid">
    <div class="text-end mb-3">
        <button class="btn btn-danger btn-sm" onclick="exportToPDF()"><i class="fas fa-file-pdf me-1"></i> Export PDF</button>
    </div>
    <div class="row mb-4">
        <?php $colors = ['primary', 'success', 'info']; foreach ($termData as $i => $term): ?>
            <div class="col-md-4">
                <div class="card bg-<?php echo $colors[$i]; ?> text-white text-center p-3">
                    <h5><?php echo $term['name']; ?></h5>
                    <h3>₹<?php echo formatIndianCurrency($term['total']); ?></h3>
                    <p class="mb-0"><?php echo $term['count']; ?> transactions</p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <div class="card-header"><h5 class="card-title mb-0">Term Comparison</h5></div>
        <div class="card-body"><canvas id="termChart" style="max-height: 300px;"></canvas></div>
    </div>

    <div class="card mt-4">
        <div class="card-body p-0">
            <table class="table mb-0">
                <thead class="table-dark"><tr><th>Term</th><th>Transactions</th><th>Collection</th><th>Share</th></tr></thead>
                <tbody>
                    <?php foreach ($termData as $term): $share = $grandTotal > 0 ? ($term['total'] / $grandTotal) * 100 : 0; ?>
                        <tr><td><strong><?php echo $term['name']; ?></strong></td><td><?php echo $term['count']; ?></td><td class="text-success fw-bold">₹<?php echo formatIndianCurrency($term['total']); ?></td><td><?php echo round($share, 1); ?>%</td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    function exportToPDF() { window.location.href = 'term-wise-pdf.php'; }
    new Chart(document.getElementById('termChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($termData, 'name')); ?>,
            datasets: [{ label: 'Collection (₹)', data: <?php echo json_encode(array_map('floatval', array_column($termData, 'total'))); ?>, backgroundColor: ['#007bff', '#28a745', '#17a2b8'], borderRadius: 8 }]
        },
        options: { responsive: true, scales: { y: { beginAtZero: true } } }
    });
</script>

<?php include '../../../include/footer.php'; ?>