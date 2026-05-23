<?php
/**
 * Collector-wise Report
 * Shows fee collection by each staff member
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

$page_title = "Collector-wise Report";
$page_breadcrumb = "Collector-wise Report";

// Get filters
$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date = $_GET['to_date'] ?? date('Y-m-d');
$selected_collector = $_GET['collector'] ?? '';
$course_filter = $_GET['course_id'] ?? '';

$dbOps = new DatabaseOperations();

// Get list of collectors
$collectors = $dbOps->customSelect(
    "SELECT DISTINCT u.id, u.name
     FROM tbl_payments p
     JOIN tbl_users u ON p.created_by = u.id
     WHERE p.status = 'paid'
     ORDER BY u.name",
    []
);

// Get collector-wise data
$sql = "SELECT 
            u.id as collector_id,
            u.name as collector_name,
            r.role_name as collector_role,
            COUNT(p.id) as transaction_count,
            COUNT(DISTINCT p.student_id) as students_served,
            IFNULL(SUM(p.amount), 0) as total_collected,
            IFNULL(SUM(CASE WHEN p.payment_mode = 'cash' THEN p.amount ELSE 0 END), 0) as cash_amount,
            IFNULL(SUM(CASE WHEN p.payment_mode IN ('online', 'upi', 'card') THEN p.amount ELSE 0 END), 0) as online_amount,
            IFNULL(SUM(CASE WHEN p.payment_mode = 'cheque' THEN p.amount ELSE 0 END), 0) as cheque_amount,
            MIN(p.payment_date) as first_collection_date,
            MAX(p.payment_date) as last_collection_date
        FROM tbl_payments p
        JOIN tbl_users u ON p.created_by = u.id
        JOIN tbl_gm_std_registration std_r ON p.student_id = std_r.id
        LEFT JOIN tbl_roles r ON u.role_id = r.id
        WHERE p.status = 'paid'
        AND p.payment_date BETWEEN ? AND ?";

$params = [$from_date, $to_date];

if (!empty($course_filter)) {
    if ($course_filter === '11th') {
        $sql .= " AND std_r.course_id = 1";
    } elseif ($course_filter === '12th') {
        $sql .= " AND std_r.course_id = 2";
    } elseif ($course_filter === 'Reneet') {
        $sql .= " AND std_r.course_id = 3";
    } else {
        $sql .= " AND std_r.course_id = ?";
        $params[] = $course_filter;
    }
}

if (!empty($selected_collector)) {
    $sql .= " AND p.created_by = ?";
    $params[] = $selected_collector;
}

$sql .= " GROUP BY u.id, u.name, r.role_name
          ORDER BY total_collected ASC";

$collectorData = $dbOps->customSelect($sql, $params);

// Calculate grand totals
$grandTotal = [
    'transactions' => 0,
    'students' => 0,
    'collected' => 0,
    'cash' => 0,
    'online' => 0,
    'cheque' => 0
];

foreach ($collectorData as $row) {
    $grandTotal['transactions'] += $row['transaction_count'];
    $grandTotal['students'] += $row['students_served'];
    $grandTotal['collected'] += $row['total_collected'];
    $grandTotal['cash'] += $row['cash_amount'];
    $grandTotal['online'] += $row['online_amount'];
    $grandTotal['cheque'] += $row['cheque_amount'];
}

// Chart data
$chartLabels = array_column($collectorData, 'collector_name');
$chartData = array_column($collectorData, 'total_collected');

include '../../../include/header.php';
include '../../../include/navbar.php';
include '../../../include/sidebar.php';
?>

<style>
    .filter-card {
        background: linear-gradient(135deg, #232526 0%, #414345 100%);
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

    .collector-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        text-align: center;
        transition: all 0.3s ease;
    }

    .collector-card:hover {
        transform: translateY(-5px);
    }

    .collector-avatar {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        font-weight: 700;
        margin: 0 auto 15px;
    }

    .collector-name {
        font-weight: 600;
        font-size: 1.1rem;
        margin-bottom: 5px;
    }

    .collector-role {
        color: #6c757d;
        font-size: 0.85rem;
        margin-bottom: 15px;
    }

    .collector-amount {
        font-size: 1.5rem;
        font-weight: 700;
        color: #28a745;
    }

    .collector-stats {
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid #eee;
        display: flex;
        justify-content: space-around;
    }

    .collector-stat {
        text-align: center;
    }

    .collector-stat .value {
        font-weight: 600;
        font-size: 1.1rem;
    }

    .collector-stat .label {
        color: #6c757d;
        font-size: 0.8rem;
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
                    <label class="form-label">To Date</label>
                    <input type="date" name="to_date" class="form-control" value="<?php echo $to_date; ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Collector</label>
                    <select name="collector" class="form-select">
                        <option value="">All Collectors</option>
                        <?php foreach ($collectors as $col): ?>
                            <option value="<?php echo $col['id']; ?>" <?php echo $selected_collector == $col['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($col['name'] ?? ''); ?>
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
                <div class="col-md-3 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-light">
                        <i class="fas fa-filter"></i> Apply
                    </button>
                    <a href="collector-wise.php" class="btn btn-outline-light">
                        <i class="fas fa-redo"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Collector Cards -->
    <div class="row mb-4">
        <?php foreach ($collectorData as $collector): ?>
            <div class="col-md-4 col-lg-3 mb-4">
                <div class="collector-card h-100">
                    <div class="collector-avatar">
                        <?php echo strtoupper(substr($collector['collector_name'], 0, 1)); ?>
                    </div>
                    <div class="collector-name">
                        <?php echo htmlspecialchars($collector['collector_name'] ?? ''); ?>
                    </div>
                    <div class="collector-role">
                        <span class="badge bg-secondary">
                            <?php echo htmlspecialchars($collector['collector_role'] ?? 'Staff'); ?>
                        </span>
                    </div>
                    <div class="collector-amount">₹
                        <?php echo formatIndianCurrency($collector['total_collected']); ?>
                    </div>
                    <div class="collector-stats">
                        <div class="collector-stat">
                            <div class="value">
                                <?php echo formatIndianCurrency($collector['transaction_count'], false); ?>
                            </div>
                            <div class="label">Transactions</div>
                        </div>
                        <div class="collector-stat">
                            <div class="value">
                                <?php echo formatIndianCurrency($collector['students_served'], false); ?>
                            </div>
                            <div class="label">Students</div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Chart -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-bar me-2"></i>Collection Comparison
                    </h5>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="collectorChart"></canvas>
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

    <!-- Detailed Table -->
    <div class="card">
        <div class="card-header bg-dark text-white">
            <h5 class="card-title mb-0">
                <i class="fas fa-table me-2"></i>
                Detailed Breakdown
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0" id="collectorTable">
                    <thead class="table-dark">
                        <tr>
                            <th>Collector</th>
                            <th>Role</th>
                            <th>Transactions</th>
                            <th>Students</th>
                            <th>Cash</th>
                            <th>Online/UPI</th>
                            <th>Cheque</th>
                            <th>Total</th>
                            <th>Active Period</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($collectorData as $collector): ?>
                            <tr>
                                <td>
                                    <strong>
                                        <?php echo htmlspecialchars($collector['collector_name'] ?? ''); ?>
                                    </strong>
                                </td>
                                <td>
                                    <span class="badge bg-info">
                                        <?php echo htmlspecialchars($collector['collector_role'] ?? '-'); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo formatIndianCurrency($collector['transaction_count'], false); ?>
                                </td>
                                <td>
                                    <?php echo formatIndianCurrency($collector['students_served'], false); ?>
                                </td>
                                <td class="text-success">₹
                                    <?php echo formatIndianCurrency($collector['cash_amount']); ?>
                                </td>
                                <td class="text-primary">₹
                                    <?php echo formatIndianCurrency($collector['online_amount']); ?>
                                </td>
                                <td class="text-warning">₹
                                    <?php echo formatIndianCurrency($collector['cheque_amount']); ?>
                                </td>
                                <td><strong>₹
                                        <?php echo formatIndianCurrency($collector['total_collected']); ?>
                                    </strong></td>
                                <td>
                                    <small>
                                        <?php echo date('d M', strtotime($collector['first_collection_date'])); ?> -
                                        <?php echo date('d M', strtotime($collector['last_collection_date'])); ?>
                                    </small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-secondary">
                        <tr>
                            <th>Total</th>
                            <th>-</th>
                            <th>
                                <?php echo formatIndianCurrency($grandTotal['transactions'], false); ?>
                            </th>
                            <th>
                                <?php echo formatIndianCurrency($grandTotal['students'], false); ?>
                            </th>
                            <th class="text-success">₹
                                <?php echo formatIndianCurrency($grandTotal['cash']); ?>
                            </th>
                            <th class="text-primary">₹
                                <?php echo formatIndianCurrency($grandTotal['online']); ?>
                            </th>
                            <th class="text-warning">₹
                                <?php echo formatIndianCurrency($grandTotal['cheque']); ?>
                            </th>
                            <th>₹
                                <?php echo formatIndianCurrency($grandTotal['collected']); ?>
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
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
    const colors = ['#667eea', '#f5576c', '#43e97b', '#4facfe', '#fa709a', '#fee140'];

    const ctx = document.getElementById('collectorChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($chartLabels); ?>,
            datasets: [{
                label: 'Total Collection (₹)',
                data: <?php echo json_encode(array_map('floatval', $chartData)); ?>,
                backgroundColor: colors,
                borderWidth: 0,
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: {
                legend: { display: false }
            },
            scales: {
                x: {
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

    function exportToExcel() {
        const table = document.getElementById('collectorTable');
        const wb = XLSX.utils.table_to_book(table, { sheet: "Collector Report" });
        XLSX.writeFile(wb, 'Collector_Report_<?php echo $from_date; ?>_to_<?php echo $to_date; ?>.xlsx');
    }

    function exportToPDF() {
        const collector = '<?php echo $selected_collector; ?>';
        window.location.href = `collector-wise-pdf.php?from_date=<?php echo $from_date; ?>&to_date=<?php echo $to_date; ?>&collector=${collector}`;
    }
</script>