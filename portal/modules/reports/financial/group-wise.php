<?php
/**
 * Group-wise Collection Report
 * Shows fee collection breakdown by student groups (Science, Commerce, Arts, etc.)
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

$page_title = "Group-wise Collection";
$page_breadcrumb = "Group-wise Collection";

// Get filters
$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date = $_GET['to_date'] ?? date('Y-m-d');
$course_filter = $_GET['course_id'] ?? '';

$dbOps = new DatabaseOperations();

// Get group-wise collection data
$sql = "SELECT 
            g.id as group_id,
            g.group_name,
            COUNT(DISTINCT es.registration_id) as total_students,
            COUNT(DISTINCT p.student_id) as students_paid,
            COUNT(p.id) as transaction_count,
            IFNULL(SUM(p.amount), 0) as total_collected,
            IFNULL(SUM(CASE WHEN p.payment_mode = 'cash' THEN p.amount ELSE 0 END), 0) as cash_amount,
            IFNULL(SUM(CASE WHEN p.payment_mode = 'cheque' THEN p.amount ELSE 0 END), 0) as cheque_amount,
            IFNULL(SUM(CASE WHEN p.payment_mode IN ('online', 'upi', 'card') THEN p.amount ELSE 0 END), 0) as online_amount
        FROM tbl_group g
        LEFT JOIN tbl_gm_std_registration r ON r.group_id = g.id
        LEFT JOIN tbl_enrolled_students es ON es.registration_id = r.id AND es.is_active = 1
        LEFT JOIN tbl_payments p ON p.student_id = es.registration_id 
            AND p.status = 'paid' 
            AND p.payment_date BETWEEN ? AND ?
        WHERE g.is_active = 1 ";

$params = [$from_date, $to_date];

if (!empty($course_filter)) {
    if ($course_filter === '11th') {
        $sql .= " AND r.course_id IN (1, 2) ";
    } elseif ($course_filter === '12th') {
        $sql .= " AND r.course_id IN (4, 5) ";
    } elseif ($course_filter === 'Reneet') {
        $sql .= " AND r.course_id = 6 ";
    } else {
        $sql .= " AND r.course_id = ? ";
        $params[] = $course_filter;
    }
}

$sql .= " GROUP BY g.id, g.group_name
        ORDER BY g.group_name";

$groupData = $dbOps->customSelect($sql, $params);

// Calculate grand totals
$grandTotal = [
    'students' => 0,
    'students_paid' => 0,
    'transactions' => 0,
    'collected' => 0,
    'cash' => 0,
    'cheque' => 0,
    'online' => 0
];

foreach ($groupData as $row) {
    $grandTotal['students'] += $row['total_students'];
    $grandTotal['students_paid'] += $row['students_paid'];
    $grandTotal['transactions'] += $row['transaction_count'];
    $grandTotal['collected'] += $row['total_collected'];
    $grandTotal['cash'] += $row['cash_amount'];
    $grandTotal['cheque'] += $row['cheque_amount'];
    $grandTotal['online'] += $row['online_amount'];
}

// Prepare chart data
$chartLabels = array_column($groupData, 'group_name');
$chartData = array_column($groupData, 'total_collected');

include '../../../include/header.php';
include '../../../include/navbar.php';
include '../../../include/sidebar.php';
?>

<link rel="stylesheet" href="<?php echo PORTAL_URL; ?>/assets/css/modules/reports/financial/group-wise.php.css">



<div class="container-fluid">
    <!-- Filters -->
    <div class="card filter-card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">From Date</label>
                    <input type="date" name="from_date" class="form-control" value="<?php echo $from_date; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Standard</label>
                    <select name="course_id" class="form-select">
                        <option value="">All Standards</option>
                        <option value="11th" <?php echo $course_filter === '11th' ? 'selected' : ''; ?>>11th</option>
                        <option value="12th" <?php echo $course_filter === '12th' ? 'selected' : ''; ?>>12th</option>
                        <option value="Reneet" <?php echo $course_filter === 'Reneet' ? 'selected' : ''; ?>>Reneet</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">To Date</label>
                    <input type="date" name="to_date" class="form-control" value="<?php echo $to_date; ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-light">
                        <i class="fas fa-filter"></i> Apply
                    </button>
                    <a href="group-wise.php" class="btn btn-outline-light">
                        <i class="fas fa-redo"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Group Cards -->
    <div class="row mb-4">
        <?php
        $colorClasses = ['bg-science', 'bg-commerce', 'bg-arts', 'bg-default'];
        $i = 0;
        foreach ($groupData as $row):
            $colorClass = $colorClasses[$i % count($colorClasses)];
            $i++;
            ?>
            <div class="col-md-4 mb-4">
                <div class="card group-card h-100">
                    <div class="group-header <?php echo $colorClass; ?>">
                        <h4>
                            <?php echo htmlspecialchars($row['group_name'] ?? ''); ?>
                        </h4>
                        <p>
                            <?php echo formatIndianCurrency($row['total_students'], false); ?> Students
                        </p>
                    </div>
                    <div class="group-stats">
                        <div class="group-stat-row">
                            <span class="label">Students Paid</span>
                            <span class="value text-success">
                                <?php echo formatIndianCurrency($row['students_paid'], false); ?>
                            </span>
                        </div>
                        <div class="group-stat-row">
                            <span class="label">Transactions</span>
                            <span class="value">
                                <?php echo formatIndianCurrency($row['transaction_count'], false); ?>
                            </span>
                        </div>
                        <div class="group-stat-row">
                            <span class="label">Cash Collection</span>
                            <span class="value text-success">₹
                                <?php echo formatIndianCurrency($row['cash_amount']); ?>
                            </span>
                        </div>
                        <div class="group-stat-row">
                            <span class="label">Cheque Collection</span>
                            <span class="value text-info">₹
                                <?php echo formatIndianCurrency($row['cheque_amount']); ?>
                            </span>
                        </div>
                        <div class="group-stat-row">
                            <span class="label">Online Collection</span>
                            <span class="value text-primary">₹
                                <?php echo formatIndianCurrency($row['online_amount']); ?>
                            </span>
                        </div>
                        <div class="group-stat-row" style="background: #f0f9ff; margin: 0 -20px -20px; padding: 15px 20px;">
                            <span class="label"><strong>Total Collection</strong></span>
                            <span class="value text-dark" style="font-size: 1.2rem;">₹
                                <?php echo formatIndianCurrency($row['total_collected']); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Charts Row -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-bar me-2"></i>Collection by Group
                    </h5>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="groupBarChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-pie me-2"></i>Collection Distribution
                    </h5>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="groupPieChart"></canvas>
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
        <button class="btn btn-danger" onclick="exportToPDF()">
            <i class="fas fa-file-pdf me-1"></i> Export PDF
        </button>
    </div>

    <!-- Data Table -->
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="card-title mb-0">
                <i class="fas fa-table me-2"></i>
                Group-wise Summary Table
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0" id="groupTable">
                    <thead class="table-dark">
                        <tr>
                            <th>Group</th>
                            <th>Total Students</th>
                            <th>Students Paid</th>
                            <th>Transactions</th>
                            <th>Cash</th>
                            <th>Cheque</th>
                            <th>Online</th>
                            <th>Total Collection</th>
                            <th>Share %</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($groupData as $row):
                            $percentage = $grandTotal['collected'] > 0
                                ? ($row['total_collected'] / $grandTotal['collected']) * 100
                                : 0;
                            ?>
                            <tr>
                                <td><strong>
                                        <?php echo htmlspecialchars($row['group_name'] ?? ''); ?>
                                    </strong></td>
                                <td>
                                    <?php echo formatIndianCurrency($row['total_students'], false); ?>
                                </td>
                                <td>
                                    <?php echo formatIndianCurrency($row['students_paid'], false); ?>
                                </td>
                                <td>
                                    <?php echo formatIndianCurrency($row['transaction_count'], false); ?>
                                </td>
                                <td class="text-success">₹
                                    <?php echo formatIndianCurrency($row['cash_amount']); ?>
                                </td>
                                <td class="text-info">₹
                                    <?php echo formatIndianCurrency($row['cheque_amount']); ?>
                                </td>
                                <td class="text-primary">₹
                                    <?php echo formatIndianCurrency($row['online_amount']); ?>
                                </td>
                                <td><strong>₹
                                        <?php echo formatIndianCurrency($row['total_collected']); ?>
                                    </strong></td>
                                <td>
                                    <span class="badge bg-info">
                                        <?php echo round($percentage, 1); ?>%
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-secondary">
                        <tr>
                            <th>Total</th>
                            <th>
                                <?php echo formatIndianCurrency($grandTotal['students'], false); ?>
                            </th>
                            <th>
                                <?php echo formatIndianCurrency($grandTotal['students_paid'], false); ?>
                            </th>
                            <th>
                                <?php echo formatIndianCurrency($grandTotal['transactions'], false); ?>
                            </th>
                            <th class="text-success">₹
                                <?php echo formatIndianCurrency($grandTotal['cash']); ?>
                            </th>
                            <th class="text-info">₹
                                <?php echo formatIndianCurrency($grandTotal['cheque']); ?>
                            </th>
                            <th class="text-primary">₹
                                <?php echo formatIndianCurrency($grandTotal['online']); ?>
                            </th>
                            <th>₹
                                <?php echo formatIndianCurrency($grandTotal['collected']); ?>
                            </th>
                            <th>100%</th>
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
    const colors = ['#667eea', '#f5576c', '#4facfe', '#43e97b', '#fa709a', '#fee140'];

    // Bar Chart
    const barCtx = document.getElementById('groupBarChart').getContext('2d');
    new Chart(barCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($chartLabels); ?>,
            datasets: [{
                label: 'Collection (₹)',
                data: <?php echo json_encode(array_map('floatval', $chartData)); ?>,
                backgroundColor: colors,
                borderWidth: 0,
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function (value) {
                            return '₹' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });

    // Pie Chart
    const pieCtx = document.getElementById('groupPieChart').getContext('2d');
    new Chart(pieCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($chartLabels); ?>,
            datasets: [{
                data: <?php echo json_encode(array_map('floatval', $chartData)); ?>,
                backgroundColor: colors,
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'right' }
            }
        }
    });

    function exportToExcel() {
        const table = document.getElementById('groupTable');
        const wb = XLSX.utils.table_to_book(table, { sheet: "Group-wise Collection" });
        XLSX.writeFile(wb, 'Group_wise_Collection_<?php echo $from_date; ?>_to_<?php echo $to_date; ?>.xlsx');
    }

    function exportToPDF() {
        window.location.href = 'group-wise-pdf.php?from_date=<?php echo $from_date; ?>&to_date=<?php echo $to_date; ?>';
    }
</script>