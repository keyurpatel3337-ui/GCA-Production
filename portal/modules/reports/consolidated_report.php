<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;
require_once DB_CONNECT_FILE;
require_once __DIR__ . '/../../../common/helpers/format_helper.php';

// Check access - Principal, Establishment, or Super Admin
if (!hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_ESTABLISHMENT) && !hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "Consolidated Institution Report";

// Initialize Metrics
$total_registrations = 0;
$total_enrolled = 0;
$board_stats = [];
$course_stats = [];
$today_collection = 0;
$month_collection = 0;

// Fetch Metrics
try {

    // 1. Student Statistics
    $total_registrations = $conn->query("SELECT COUNT(*) FROM tbl_gm_std_registration")->fetchColumn();
    $total_enrolled = $conn->query("SELECT COUNT(*) FROM tbl_enrolled_students WHERE is_active = 1")->fetchColumn();

    // Distribution by Board
    $board_stats = $conn->query("
        SELECT b.board_name, COUNT(r.id) as count 
        FROM tbl_boards b 
        LEFT JOIN tbl_gm_std_registration r ON r.board_id = b.id 
        GROUP BY b.id, b.board_name 
        ORDER BY count ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Distribution by Course
    $course_stats = $conn->query("
        SELECT c.course_name, COUNT(r.id) as count 
        FROM tbl_courses c 
        LEFT JOIN tbl_gm_std_registration r ON r.course_id = c.id 
        GROUP BY c.id, c.course_name 
        ORDER BY count ASC 
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    // 2. Financial Summary (Simplified for high-level view)
    $today = date('Y-m-d');
    $month_start = date('Y-m-01');

    $today_collection = $conn->query("SELECT SUM(amount) FROM tbl_payments WHERE DATE(payment_date) = '$today' AND status = 'success'")->fetchColumn() ?: 0;
    $month_collection = $conn->query("SELECT SUM(amount) FROM tbl_payments WHERE DATE(payment_date) >= '$month_start' AND status = 'success'")->fetchColumn() ?: 0;

} catch (Exception $e) {
    logError("Consolidated Report Error: " . $e->getMessage());
    $error_message = "Failed to load complete report data.";
}

include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>

<div class="container-fluid py-4 pb-5">
    <!-- Header Section -->
    <div class="d-flex align-items-center justify-content-between mb-4 mt-2">
        <div>
            <h4 class="fw-bold mb-1 text-dark">Consolidated Institution Report</h4>
            <p class="text-muted small mb-0">Institutional performance overview as of <?php echo date('d M Y, H:i'); ?>
            </p>
        </div>
        <div class="text-end">
            <button class="btn btn-outline-primary btn-sm rounded-pill px-3 fw-bold" onclick="window.print()">
                <i class="fas fa-print me-1"></i> Print Report
            </button>
        </div>
    </div>

    <!-- Key Performance Indicators -->
    <div class="row g-4 mb-5">
        <div class="col-xl-3 col-md-6">
            <div class="glass-card p-4 h-100 border-0 shadow-sm">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small fw-bold text-uppercase mb-1">Total Registrations</div>
                        <div class="h3 fw-bold mb-0 text-primary">
                            <?php echo formatIndianCurrency($total_registrations, false); ?>
                        </div>
                    </div>
                    <div class="stat-icon bg-blue-subtle text-primary p-3 rounded-3">
                        <i class="fas fa-user-plus fa-lg"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="glass-card p-4 h-100 border-0 shadow-sm">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small fw-bold text-uppercase mb-1">Enrolled Students</div>
                        <div class="h3 fw-bold mb-0 text-success">
                            <?php echo formatIndianCurrency($total_enrolled, false); ?>
                        </div>
                    </div>
                    <div class="stat-icon bg-green-subtle text-success p-3 rounded-3">
                        <i class="fas fa-user-graduate fa-lg"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="glass-card p-4 h-100 border-0 shadow-sm">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small fw-bold text-uppercase mb-1">Today's Collection</div>
                        <div class="h3 fw-bold mb-0 text-dark">₹
                            <?php echo formatIndianCurrency($today_collection); ?>
                        </div>
                    </div>
                    <div class="stat-icon bg-orange-subtle text-warning p-3 rounded-3">
                        <i class="fas fa-coins fa-lg"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="glass-card p-4 h-100 border-0 shadow-sm">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small fw-bold text-uppercase mb-1">Monthly Collection</div>
                        <div class="h3 fw-bold mb-0 text-indigo">₹
                            <?php echo formatIndianCurrency($month_collection); ?>
                        </div>
                    </div>
                    <div class="stat-icon bg-indigo-subtle text-indigo p-3 rounded-3">
                        <i class="fas fa-wallet fa-lg"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Student Distribution by Board -->
        <div class="col-lg-6">
            <div class="glass-card h-100 border-0 shadow-sm overflow-hidden">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="card-title mb-0 fw-bold"><i class="fas fa-chart-pie me-2 text-primary"></i> Distribution
                        by Board</h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="border-0 ps-4">Board Name</th>
                                <th class="border-0 text-end pe-4">Students</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($board_stats as $board): ?>
                                <tr>
                                    <td class="ps-4 fw-medium">
                                        <?php echo htmlspecialchars($board['board_name'] ?? ''); ?>
                                    </td>
                                    <td class="text-end pe-4 fw-bold">
                                        <?php echo formatIndianCurrency($board['count'], false); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Top Courses Distribution -->
        <div class="col-lg-6">
            <div class="glass-card h-100 border-0 shadow-sm overflow-hidden">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="card-title mb-0 fw-bold"><i class="fas fa-th-list me-2 text-success"></i> Top 5 Courses
                    </h5>
                </div>
                <div class="card-body p-4">
                    <?php if (empty($course_stats)): ?>
                        <div class="text-center py-4 opacity-50">No course data available.</div>
                    <?php else: ?>
                        <?php
                        $max_count = max(array_column($course_stats, 'count')) ?: 1;
                        foreach ($course_stats as $course):
                            $percent = ($course['count'] / $max_count) * 100;
                            ?>
                            <div class="mb-4">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="fw-medium text-dark">
                                        <?php echo htmlspecialchars($course['course_name'] ?? ''); ?>
                                    </span>
                                    <span class="fw-bold text-muted">
                                        <?php echo formatIndianCurrency($course['count'], false); ?>
                                    </span>
                                </div>
                                <div class="progress rounded-pill" style="height: 8px;">
                                    <div class="progress-bar bg-primary rounded-pill" style="width: <?php echo $percent; ?>%">
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="<?php echo PORTAL_URL; ?>/assets/css/modules/reports/consolidated_report.php.css">

<?php include '../../include/footer.php'; ?>