<?php
/**
 * Group Change Reports & Statistics
 * Analyzes group change requests and their financial impact
 */

require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/format_helper.php';

// Check if user is Principal, Admin or Accountant
if (!hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_ACCOUNTANT)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "Group Change Reports & Statistics";
$page_breadcrumb = "Reports";

$dbOps = new DatabaseOperations();

// --- 1. Filter Logic ---
$from_date = $_GET['from_date'] ?? date('Y-m-01', strtotime('-6 months'));
$to_date = $_GET['to_date'] ?? date('Y-m-d');
$school_filter = $_GET['school_id'] ?? '';
$group_filter = $_GET['group_id'] ?? '';

$whereConditions = ["1=1"];
$params = [];

if (!empty($from_date) && !empty($to_date)) {
    $whereConditions[] = "gcr.request_date BETWEEN ? AND ?";
    $params[] = $from_date . ' 00:00:00';
    $params[] = $to_date . ' 23:59:59';
}

if (!empty($school_filter)) {
    $whereConditions[] = "r.school_id = ?";
    $params[] = $school_filter;
}

if (!empty($group_filter)) {
    $whereConditions[] = "gcr.requested_group_id = ?";
    $params[] = $group_filter;
}

$whereClause = implode(' AND ', $whereConditions);

// Fetch master data for filters
$schools = $dbOps->customSelect("SELECT id, school_name FROM tbl_schools ORDER BY school_name", []);
$groups = $dbOps->customSelect("SELECT id, group_name FROM tbl_group ORDER BY group_name", []);

try {
    // Overall Statistics
    $sql = "SELECT 
        COUNT(*) as total_requests,
        SUM(CASE WHEN gcr.status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN gcr.status = 'under_review' THEN 1 ELSE 0 END) as under_review,
        SUM(CASE WHEN gcr.status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN gcr.status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN gcr.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
        FROM tbl_group_change_requests gcr
        JOIN tbl_gm_std_registration r ON gcr.student_id = r.id
        WHERE $whereClause";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $overall_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Approval Rate
    $total = (int) $overall_stats['total_requests'];
    $approved = (int) $overall_stats['approved'];
    $approval_rate = $total > 0 ? round(($approved / $total) * 100, 2) : 0;

    // Group-wise statistics
    $sql = "SELECT 
        g.group_name,
        COUNT(*) as request_count,
        SUM(CASE WHEN gcr.status = 'approved' THEN 1 ELSE 0 END) as approved_count
        FROM tbl_group_change_requests gcr
        JOIN tbl_gm_std_registration r ON gcr.student_id = r.id
        LEFT JOIN tbl_group g ON gcr.requested_group_id = g.id
        WHERE $whereClause
        GROUP BY gcr.requested_group_id
        ORDER BY request_count ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $group_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Monthly trend (last 6 months)
    $sql = "SELECT 
        DATE_FORMAT(gcr.request_date, '%Y-%m') as month,
        COUNT(*) as total_requests,
        SUM(CASE WHEN gcr.status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN gcr.status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM tbl_group_change_requests gcr
        JOIN tbl_gm_std_registration r ON gcr.student_id = r.id
        WHERE $whereClause
        GROUP BY DATE_FORMAT(gcr.request_date, '%Y-%m')
        ORDER BY month ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $monthly_trend = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fee Impact Analysis
    $sql = "SELECT 
        SUM(CASE WHEN (COALESCE(gcr.new_total_fees, 0) - COALESCE(gcr.current_total_fees, 0)) > 0 THEN 1 ELSE 0 END) as fee_increase_count,
        SUM(CASE WHEN (COALESCE(gcr.new_total_fees, 0) - COALESCE(gcr.current_total_fees, 0)) < 0 THEN 1 ELSE 0 END) as fee_decrease_count,
        SUM(CASE WHEN (COALESCE(gcr.new_total_fees, 0) - COALESCE(gcr.current_total_fees, 0)) = 0 THEN 1 ELSE 0 END) as no_fee_change_count,
        ROUND(AVG(COALESCE(gcr.new_total_fees, 0) - COALESCE(gcr.current_total_fees, 0)), 0) as avg_fee_difference,
        ROUND(SUM(COALESCE(gcr.new_total_fees, 0) - COALESCE(gcr.current_total_fees, 0)), 0) as total_fee_difference
        FROM tbl_group_change_requests gcr
        JOIN tbl_gm_std_registration r ON gcr.student_id = r.id
        WHERE $whereClause AND gcr.status = 'approved'";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $fee_impact = $stmt->fetch(PDO::FETCH_ASSOC);

    // Top Reasons for Group Change
    $sql = "SELECT 
        CASE 
            WHEN reason LIKE '%interest%' THEN 'Interest in Subject'
            WHEN reason LIKE '%career%' THEN 'Career Goals'
            WHEN reason LIKE '%performance%' THEN 'Academic Performance'
            WHEN reason LIKE '%guidance%' OR reason LIKE '%counseling%' THEN 'Counseling Guidance'
            ELSE 'Other Reasons'
        END as reason_category,
        COUNT(*) as count
        FROM tbl_group_change_requests gcr
        JOIN tbl_gm_std_registration r ON gcr.student_id = r.id
        WHERE $whereClause
        GROUP BY reason_category
        ORDER BY count ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $reason_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    if (defined('HELPER_ERROR_LOGGER')) {
        logError("Reports Page Error: " . $e->getMessage(), __FILE__, __LINE__, $e);
    }
    set_flash_message('error', "An error occurred while loading report data.");
    $overall_stats = ['total_requests' => 0, 'pending' => 0, 'under_review' => 0, 'approved' => 0, 'rejected' => 0, 'cancelled' => 0];
    $approval_rate = 0;
    $group_stats = [];
    $monthly_trend = [];
    $fee_impact = ['fee_increase_count' => 0, 'fee_decrease_count' => 0, 'no_fee_change_count' => 0, 'avg_fee_difference' => 0, 'total_fee_difference' => 0];
    $reason_stats = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        .stat-card {
            border-start: 4px solid;
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .chart-container {
            position: relative;
            height: 300px;
        }

        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>

<body>
    <?php include '../../include/header.php'; ?>
    <?php include '../../include/navbar.php'; ?>
    <?php include '../../include/sidebar.php'; ?>

    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-chart-bar"></i> Group Change Reports & Statistics</h2>
            <div class="no-print">
                <a href="financial/index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to
                    Dashboard</a>
                <button onclick="window.print()" class="btn btn-info"><i class="fas fa-print"></i> Print Report</button>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="card shadow-sm mb-4 no-print">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-filter"></i> Filters</h5>
                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse"
                    data-bs-target="#filterBody">
                    <i class="fas fa-chevron-down"></i>
                </button>
            </div>
            <div id="filterBody" class="collapse show">
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
                            <label class="form-label">Requested Group</label>
                            <select name="group_id" class="form-select">
                                <option value="">All Groups</option>
                                <?php foreach ($groups as $group): ?>
                                    <option value="<?php echo $group['id']; ?>" <?php echo $group_filter == $group['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($group['group_name'] ?? ''); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end gap-2">
                            <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i>
                                Apply</button>
                            <a href="group-change-report.php" class="btn btn-secondary w-100"><i
                                    class="fas fa-redo"></i> Reset</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $_SESSION['error'];
                unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Overall Statistics -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-chart-pie"></i> Overall Statistics</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-6 col-md-2 text-center">
                                <h3 class="text-info fw-bold">
                                    <?php echo (int) ($overall_stats['total_requests'] ?? 0); ?>
                                </h3>
                                <p class="text-muted small mb-0">Total Requests</p>
                            </div>
                            <div class="col-6 col-md-2 text-center">
                                <h3 class="text-warning fw-bold"><?php echo (int) ($overall_stats['pending'] ?? 0); ?>
                                </h3>
                                <p class="text-muted small mb-0">Pending</p>
                            </div>
                            <div class="col-6 col-md-2 text-center">
                                <h3 class="text-primary fw-bold">
                                    <?php echo (int) ($overall_stats['under_review'] ?? 0); ?>
                                </h3>
                                <p class="text-muted small mb-0">Under Review</p>
                            </div>
                            <div class="col-6 col-md-2 text-center">
                                <h3 class="text-success fw-bold"><?php echo (int) ($overall_stats['approved'] ?? 0); ?>
                                </h3>
                                <p class="text-muted small mb-0">Approved</p>
                            </div>
                            <div class="col-6 col-md-2 text-center">
                                <h3 class="text-danger fw-bold"><?php echo (int) ($overall_stats['rejected'] ?? 0); ?>
                                </h3>
                                <p class="text-muted small mb-0">Rejected</p>
                            </div>
                            <div class="col-6 col-md-2 text-center">
                                <h3 class="text-success fw-bold"><?php echo $approval_rate; ?>%</h3>
                                <p class="text-muted small mb-0">Approval Rate</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row 1 -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="fas fa-chart-line"></i> Monthly Trend (Last 6 Months)</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container"><canvas id="monthlyTrendChart"></canvas></div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="fas fa-chart-pie"></i> Status Distribution</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container"><canvas id="statusDistributionChart"></canvas></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row 2 -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="fas fa-users"></i> Most Requested Groups</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container"><canvas id="groupDistributionChart"></canvas></div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="fas fa-rupee-sign"></i> Fee Impact Analysis</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-4">
                                <h4 class="text-danger"><?php echo $fee_impact['fee_increase_count']; ?></h4>
                                <p class="text-muted">Fee Increase</p>
                            </div>
                            <div class="col-md-4">
                                <h4 class="text-success"><?php echo $fee_impact['fee_decrease_count']; ?></h4>
                                <p class="text-muted">Fee Decrease</p>
                            </div>
                            <div class="col-md-4">
                                <h4 class="text-info"><?php echo $fee_impact['no_fee_change_count']; ?></h4>
                                <p class="text-muted">No Change</p>
                            </div>
                        </div>
                        <hr>
                        <div class="row text-center">
                            <div class="col-md-6">
                                <h5 class="text-primary">
                                    ₹<?php echo formatIndianCurrency((float) ($fee_impact['avg_fee_difference'] ?? 0)); ?>
                                </h5>
                                <p class="text-muted">Average Fee Difference</p>
                            </div>
                            <div class="col-md-6">
                                <h5 class="text-primary">
                                    ₹<?php echo formatIndianCurrency((float) ($fee_impact['total_fee_difference'] ?? 0)); ?>
                                </h5>
                                <p class="text-muted">Total Fee Impact</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Group-wise Table -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="fas fa-table"></i> Group-wise Request Statistics</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Requested Group</th>
                                <th>Total Requests</th>
                                <th>Approved</th>
                                <th>Approval Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($group_stats as $stat):
                                $rate = $stat['request_count'] > 0 ? round(($stat['approved_count'] / $stat['request_count']) * 100, 2) : 0;
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($stat['group_name'] ?? ''); ?></strong></td>
                                    <td><?php echo $stat['request_count']; ?></td>
                                    <td><?php echo $stat['approved_count']; ?></td>
                                    <td>
                                        <div class="progress" style="height: 25px;">
                                            <div class="progress-bar bg-success" style="width: <?php echo $rate; ?>%">
                                                <?php echo $rate; ?>%
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <?php include '../../include/footer.php'; ?>

    <script shadow-sm>
        // Monthly Trend Chart
        new Chart(document.getElementById('monthlyTrendChart'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($monthly_trend, 'month')); ?>,
                datasets: [{
                    label: 'Total Requests', data: <?php echo json_encode(array_column($monthly_trend, 'total_requests')); ?>, borderColor: 'rgb(75, 192, 192)', tension: 0.1
                }, {
                    label: 'Approved', data: <?php echo json_encode(array_column($monthly_trend, 'approved')); ?>, borderColor: 'rgb(40, 167, 69)', tension: 0.1
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });

        // Status Distribution Chart
        new Chart(document.getElementById('statusDistributionChart'), {
            type: 'doughnut',
            data: {
                labels: ['Pending', 'Review', 'Approved', 'Rejected', 'Cancelled'],
                datasets: [{
                    data: [<?php echo $overall_stats['pending']; ?>, <?php echo $overall_stats['under_review']; ?>, <?php echo $overall_stats['approved']; ?>, <?php echo $overall_stats['rejected']; ?>, <?php echo $overall_stats['cancelled']; ?>],
                    backgroundColor: ['#ffc107', '#0d6efd', '#198754', '#dc3545', '#6c757d']
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });

        // Group Distribution Chart
        new Chart(document.getElementById('groupDistributionChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($group_stats, 'group_name')); ?>,
                datasets: [{ label: 'Request Count', data: <?php echo json_encode(array_column($group_stats, 'request_count')); ?>, backgroundColor: 'rgba(54, 162, 235, 0.5)', borderColor: 'rgb(54, 162, 235)', borderWidth: 1 }]
            },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
        });
    </script>
</body>

</html>