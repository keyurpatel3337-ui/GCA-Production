<?php
/**
 * Class-wise Collection Report
 * Shows fee collection breakdown by class
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

$page_title = "Class-wise Collection";
$page_breadcrumb = "Class-wise Collection";

// Get filters
$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date = $_GET['to_date'] ?? date('Y-m-d');
$course_filter = $_GET['course_id'] ?? '';

$dbOps = new DatabaseOperations();

// Get class-wise collection data
$sql = "SELECT 
            c.course_name as current_class,
            COUNT(DISTINCT es.registration_id) as total_students,
            COUNT(DISTINCT p.student_id) as students_paid,
            COUNT(p.id) as transaction_count,
            IFNULL(SUM(p.amount), 0) as total_collected,
            IFNULL(SUM(CASE WHEN p.payment_mode = 'cash' THEN p.amount ELSE 0 END), 0) as cash_amount,
            IFNULL(SUM(CASE WHEN p.payment_mode = 'cheque' THEN p.amount ELSE 0 END), 0) as cheque_amount,
            IFNULL(SUM(CASE WHEN p.payment_mode IN ('online', 'upi', 'card') THEN p.amount ELSE 0 END), 0) as online_amount
        FROM tbl_enrolled_students es
        JOIN tbl_gm_std_registration r ON es.registration_id = r.id
        JOIN tbl_courses c ON r.course_id = c.id
        LEFT JOIN tbl_payments p ON p.student_id = es.registration_id 
            AND p.status = 'paid' 
            AND p.payment_date BETWEEN ? AND ?
        WHERE es.is_active = 1 ";

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

$sql .= " GROUP BY c.course_name
        ORDER BY c.course_name";

$classData = $dbOps->customSelect($sql, $params);

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

foreach ($classData as $row) {
    $grandTotal['students'] += $row['total_students'];
    $grandTotal['students_paid'] += $row['students_paid'];
    $grandTotal['transactions'] += $row['transaction_count'];
    $grandTotal['collected'] += $row['total_collected'];
    $grandTotal['cash'] += $row['cash_amount'];
    $grandTotal['cheque'] += $row['cheque_amount'];
    $grandTotal['online'] += $row['online_amount'];
}

// Prepare chart data
$chartLabels = array_column($classData, 'current_class');
$chartData = array_column($classData, 'total_collected');

include '../../../include/header.php';
include '../../../include/navbar.php';
include '../../../include/sidebar.php';
?>

<style>
    .filter-card {
        background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        border: none;
        border-radius: 12px;
    }

    .filter-card .form-label {
        color: white;
        font-weight: 500;
    }

    .chart-container {
        position: relative;
        height: 350px;
    }

    .summary-row {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 20px;
    }

    .summary-item {
        text-align: center;
    }

    .summary-item h3 {
        font-size: 1.8rem;
        font-weight: 700;
        margin-bottom: 5px;
    }

    .summary-item p {
        margin: 0;
        opacity: 0.9;
    }

    .progress-bar-custom {
        height: 8px;
        border-radius: 4px;
        background: #e9ecef;
        overflow: hidden;
    }

    .progress-bar-custom .fill {
        height: 100%;
        border-radius: 4px;
        transition: width 0.5s ease;
    }
</style>



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
                    <a href="class-wise.php" class="btn btn-outline-light">
                        <i class="fas fa-redo"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Row -->
    <div class="summary-row">
        <div class="row">
            <div class="col-md-3 summary-item">
                <h3>
                    <?php echo formatIndianCurrency($grandTotal['students'], false); ?>
                </h3>
                <p>Total Students</p>
            </div>
            <div class="col-md-3 summary-item">
                <h3>
                    <?php echo formatIndianCurrency($grandTotal['students_paid'], false); ?>
                </h3>
                <p>Students Paid</p>
            </div>
            <div class="col-md-3 summary-item">
                <h3>
                    <?php echo formatIndianCurrency($grandTotal['transactions'], false); ?>
                </h3>
                <p>Transactions</p>
            </div>
            <div class="col-md-3 summary-item">
                <h3>₹
                    <?php echo formatIndianCurrency($grandTotal['collected']); ?>
                </h3>
                <p>Total Collected</p>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-bar me-2"></i>Collection by Class
                    </h5>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="classBarChart"></canvas>
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
                        <canvas id="classPieChart"></canvas>
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
        <div class="card-header bg-success text-white">
            <h5 class="card-title mb-0">
                <i class="fas fa-table me-2"></i>
                Class-wise Breakdown
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0" id="classTable">
                    <thead class="table-dark">
                        <tr>
                            <th>Class</th>
                            <th>Total Students</th>
                            <th>Students Paid</th>
                            <th>Transactions</th>
                            <th>Cash Collection</th>
                            <th>Cheque Collection</th>
                            <th>Online Collection</th>
                            <th>Total Collection</th>
                            <th>Collection %</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($classData)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <i class="fas fa-folder-open text-muted fa-3x mb-3"></i>
                                    <p class="mb-0">No data found for selected period</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($classData as $row):
                                $percentage = $grandTotal['collected'] > 0
                                    ? ($row['total_collected'] / $grandTotal['collected']) * 100
                                    : 0;
                                $paymentRate = $row['total_students'] > 0
                                    ? ($row['students_paid'] / $row['total_students']) * 100
                                    : 0;
                                ?>
                                <tr>
                                    <td>
                                        <strong>
                                            <?php echo htmlspecialchars($row['current_class'] ?? ''); ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <?php echo formatIndianCurrency($row['total_students'], false); ?>
                                    </td>
                                    <td>
                                        <?php echo formatIndianCurrency($row['students_paid'], false); ?>
                                        <br>
                                        <small class="text-muted">(
                                            <?php echo round($paymentRate); ?>%)
                                        </small>
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
                                    <td>
                                        <strong>₹
                                            <?php echo formatIndianCurrency($row['total_collected']); ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="progress-bar-custom" style="width: 80px;">
                                                <div class="fill bg-success"
                                                    style="width: <?php echo min($percentage, 100); ?>%;"></div>
                                            </div>
                                            <span>
                                                <?php echo round($percentage, 1); ?>%
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
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
    // Chart colors
    const colors = [
        '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF',
        '#FF9F40', '#7C4DFF', '#00BCD4', '#8BC34A', '#FF5722'
    ];

    // Bar Chart
    const barCtx = document.getElementById('classBarChart').getContext('2d');
    new Chart(barCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($chartLabels); ?>,
            datasets: [{
                label: 'Collection Amount (₹)',
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
    const pieCtx = document.getElementById('classPieChart').getContext('2d');
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
                legend: {
                    position: 'right'
                }
            }
        }
    });

    function exportToExcel() {
        const table = document.getElementById('classTable');
        const wb = XLSX.utils.table_to_book(table, { sheet: "Class-wise Collection" });
        XLSX.writeFile(wb, 'Class_wise_Collection_<?php echo $from_date; ?>_to_<?php echo $to_date; ?>.xlsx');
    }

    function exportToPDF() {
        window.location.href = 'class-wise-pdf.php?from_date=<?php echo $from_date; ?>&to_date=<?php echo $to_date; ?>';
    }
</script>