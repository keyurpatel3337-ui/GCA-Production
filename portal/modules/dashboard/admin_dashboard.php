<?php

require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once __DIR__ . '/../../common/api_client.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/format_helper.php';

// Load dashboard data via API
$api = new APIClient();
$response = $api->get('dashboard/admin');

// Extract data from API response
if ($response && isset($response['success']) && $response['success']) {
    $stats = $response['data'] ?? [];
    $total_principles = $stats['total_principles'] ?? 0;
    $total_counsellors = $stats['total_counsellors'] ?? 0;
    $total_registrations = $stats['total_registrations'] ?? 0;
    $total_enrolled = $stats['total_enrolled'] ?? 0;
    $total_answer_keys = $stats['total_answer_keys'] ?? 0;
    $total_omr_sheets = $stats['total_omr_sheets'] ?? 0;
    $total_results = $stats['total_results'] ?? 0;

    // Detailed Stats
    $standard_details = $stats['standard_details'] ?? [];
    $group_stats = $stats['groups'] ?? [];
    $medium_stats = $stats['mediums'] ?? [];
    $fee_stats = $stats['fee_status'] ?? [];
} else {
    // Fallback to default values if API fails
    $total_principles = 0;
    $total_counsellors = 0;
    $total_registrations = 0;
    $total_enrolled = 0;
    $total_answer_keys = 0;
    $total_omr_sheets = 0;
    $total_results = 0;
    $standard_details = [];
    $group_stats = [];
    $medium_stats = [];
    $fee_stats = [];
}

// User Greeting
$hour = date('H');
$greeting = "Good Morning";
if ($hour >= 12 && $hour < 17) $greeting = "Good Afternoon";
elseif ($hour >= 17) $greeting = "Good Evening";

$admin_name = $_SESSION['user_name'] ?? 'Admin';

// Set Page Title
$page_title = "Super Admin Dashboard";
?>
<?php include '../../include/header.php'; ?>
<!-- ApexCharts -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<link rel="stylesheet" href="<?php echo PORTAL_URL; ?>/assets/css/modules/dashboard/admin_dashboard.css">

<?php include '../../include/navbar.php'; ?>
<?php include '../../include/sidebar.php'; ?>

<div class="container-fluid py-4 pb-5 dashboard-wrapper">
    <?php include '../../include/mfa_alert.php'; ?>

    <!-- Welcome Header -->
    <div class="welcome-header mb-4">
        <div>
            <h2 class="greeting-text mb-1"><?php echo $greeting; ?>, <span class="admin-name"><?php echo $admin_name; ?></span>! 👋</h2>
            <p class="text-muted">Here's a quick overview of Gyanmanjari Career Academy today.</p>
        </div>
        <div class="header-actions">
            <div class="date-display">
                <i class="far fa-calendar-alt me-2"></i>
                <?php echo date('D, d M Y'); ?>
            </div>
        </div>
    </div>

    <!-- Messages -->
    <?php if (isset($_SESSION['success_msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4">
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            <div class="d-flex align-items-center">
                <i class="fas fa-check-circle fs-4 me-3"></i>
                <div><?php echo gca_safe_html($_SESSION['success_msg']); ?></div>
            </div>
        </div>
        <?php unset($_SESSION['success_msg']); ?>
    <?php endif; ?>

    <!-- Key Performance Indicators (KPIs) -->
    <div class="row g-4 mb-5">
        <!-- Principals -->
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="modern-stat-card card-blue">
                <div class="card-content">
                    <div class="stat-main">
                        <div class="stat-value"><?php echo $total_principles; ?></div>
                        <div class="stat-label">Total Principals</div>
                    </div>
                    <div class="stat-icon-wrapper">
                        <i class="fas fa-user-tie"></i>
                    </div>
                </div>
                <form method="POST" action="<?php echo PORTAL_URL; ?>/modules/settings/users.php" class="card-footer-action">
                    <input type="hidden" name="role" value="principle">
                    <button type="submit" class="action-btn">
                        Manage Principals <i class="fas fa-chevron-right ms-1"></i>
                    </button>
                </form>
            </div>
        </div>

        <!-- Counsellors -->
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="modern-stat-card card-teal">
                <div class="card-content">
                    <div class="stat-main">
                        <div class="stat-value"><?php echo $total_counsellors; ?></div>
                        <div class="stat-label">Total Counsellors</div>
                    </div>
                    <div class="stat-icon-wrapper">
                        <i class="fas fa-user-md"></i>
                    </div>
                </div>
                <form method="POST" action="<?php echo PORTAL_URL; ?>/modules/settings/users.php" class="card-footer-action">
                    <input type="hidden" name="role" value="counsellor">
                    <button type="submit" class="action-btn">
                        Manage Counsellors <i class="fas fa-chevron-right ms-1"></i>
                    </button>
                </form>
            </div>
        </div>

        <!-- Registrations -->
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="modern-stat-card card-indigo">
                <div class="card-content">
                    <div class="stat-main">
                        <div class="stat-value"><?php echo number_format($total_registrations); ?></div>
                        <div class="stat-label">Registrations</div>
                    </div>
                    <div class="stat-icon-wrapper">
                        <i class="fas fa-user-plus"></i>
                    </div>
                </div>
                <a href="<?php echo PORTAL_URL; ?>/modules/students/students.php?view=all" class="card-footer-action no-border">
                    <span class="action-btn">View All Students <i class="fas fa-chevron-right ms-1"></i></span>
                </a>
            </div>
        </div>

        <!-- Enrolled -->
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="modern-stat-card card-orange">
                <div class="card-content">
                    <div class="stat-main">
                        <div class="stat-value"><?php echo number_format($total_enrolled); ?></div>
                        <div class="stat-label">Enrolled Students</div>
                    </div>
                    <div class="stat-icon-wrapper">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                </div>
                <a href="<?php echo PORTAL_URL; ?>/modules/students/students.php?view=enrolled" class="card-footer-action no-border">
                    <span class="action-btn">View Enrolled <i class="fas fa-chevron-right ms-1"></i></span>
                </a>
            </div>
        </div>
    </div>

    <!-- Data Visualization Row -->
    <div class="row g-4 mb-5">
        <!-- Admission Trends (Sample Bar Chart) -->
        <div class="col-lg-8">
            <div class="glass-container h-100">
                <div class="container-header d-flex justify-content-between align-items-center mb-4">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-chart-line text-primary me-2"></i>Standard-wise Distribution</h5>
                    <div class="badge bg-primary-subtle text-primary rounded-pill px-3 py-2">Real-time Data</div>
                </div>
                <div id="standardChart" style="min-height: 350px;"></div>
            </div>
        </div>

        <!-- Fee Status (Pie Chart) -->
        <div class="col-lg-4">
            <div class="glass-container h-100">
                <div class="container-header mb-4">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-wallet text-warning me-2"></i>Fee Standing</h5>
                </div>
                <div id="feeStatusChart" style="min-height: 350px;"></div>
                <div class="fee-legends mt-3">
                    <?php 
                    $fee_colors = ['paid' => 'success', 'pending' => 'danger', 'partial' => 'warning', 'overdue' => 'dark'];
                    foreach ($fee_stats as $f): 
                        $color = $fee_colors[strtolower($f['name'])] ?? 'secondary';
                    ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="small text-muted"><i class="fas fa-circle text-<?php echo $color; ?> me-2" style="font-size: 8px;"></i><?php echo $f['name']; ?></span>
                            <span class="fw-bold small"><?php echo $f['count']; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Secondary Metrics Row -->
    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="mini-stat-card">
                <div class="icon bg-purple-glow"><i class="fas fa-key"></i></div>
                <div class="details">
                    <div class="label">Answer Keys</div>
                    <div class="value"><?php echo $total_answer_keys; ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="mini-stat-card">
                <div class="icon bg-info-glow"><i class="fas fa-file-upload"></i></div>
                <div class="details">
                    <div class="label">OMR Sheets</div>
                    <div class="value"><?php echo $total_omr_sheets; ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="mini-stat-card">
                <div class="icon bg-success-glow"><i class="fas fa-check-circle"></i></div>
                <div class="details">
                    <div class="label">Checked Results</div>
                    <div class="value"><?php echo $total_results; ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-access-section mb-5">
        <h5 class="section-title mb-4">Quick Access</h5>
        <div class="row g-3">
            <?php 
            $actions = [
                ['title' => 'Manage Users', 'icon' => 'fa-user-plus', 'color' => 'blue', 'link' => 'settings/users.php'],
                ['title' => 'Academic Years', 'icon' => 'fa-calendar-alt', 'color' => 'green', 'link' => 'academics/academic-years.php'],
                ['title' => 'Boards Config', 'icon' => 'fa-clipboard-list', 'color' => 'cyan', 'link' => 'academics/boards.php'],
                ['title' => 'Courses', 'icon' => 'fa-book', 'color' => 'orange', 'link' => 'academics/courses.php'],
                ['title' => 'Fee Config', 'icon' => 'fa-rupee-sign', 'color' => 'teal', 'link' => 'fees/fee-config.php'],
                ['title' => 'Gateways', 'icon' => 'fa-credit-card', 'color' => 'red', 'link' => 'settings/payment-gateways.php'],
            ];
            foreach ($actions as $act):
            ?>
            <div class="col-xl-2 col-lg-3 col-md-4 col-6">
                <a href="<?php echo PORTAL_URL; ?>/modules/<?php echo $act['link']; ?>" class="action-card color-<?php echo $act['color']; ?>">
                    <div class="icon"><i class="fas <?php echo $act['icon']; ?>"></i></div>
                    <div class="text"><?php echo $act['title']; ?></div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- Standard-wise Chart ---
        const stdNames = <?php echo json_encode(array_keys($standard_details)); ?>;
        const regData = <?php echo json_encode(array_column($standard_details, 'registered')); ?>;
        const enrolledData = <?php echo json_encode(array_column($standard_details, 'enrolled')); ?>;

        var optionsStd = {
            series: [{
                name: 'Registered',
                data: regData
            }, {
                name: 'Enrolled',
                data: enrolledData
            }],
            chart: {
                type: 'bar',
                height: 350,
                toolbar: { show: false },
                fontFamily: 'Inter, sans-serif'
            },
            colors: ['#3b82f6', '#10b981'],
            plotOptions: {
                bar: {
                    horizontal: false,
                    columnWidth: '55%',
                    borderRadius: 8,
                },
            },
            dataLabels: { enabled: false },
            stroke: {
                show: true,
                width: 2,
                colors: ['transparent']
            },
            xaxis: {
                categories: stdNames,
            },
            fill: { opacity: 1 },
            tooltip: {
                y: { formatter: function (val) { return val + " Students" } }
            },
            grid: {
                borderColor: '#f1f1f1',
                padding: { bottom: 10 }
            }
        };

        var chartStd = new ApexCharts(document.querySelector("#standardChart"), optionsStd);
        chartStd.render();

        // --- Fee Status Chart ---
        const feeLabels = <?php echo json_encode(array_column($fee_stats, 'name')); ?>;
        const feeCounts = <?php echo json_encode(array_column($fee_stats, 'count')); ?>;
        
        var optionsFee = {
            series: feeCounts,
            chart: {
                type: 'donut',
                height: 350,
                fontFamily: 'Inter, sans-serif'
            },
            labels: feeLabels,
            colors: ['#10b981', '#ef4444', '#f59e0b', '#1e293b'],
            legend: { show: false },
            plotOptions: {
                pie: {
                    donut: {
                        size: '70%',
                        labels: {
                            show: true,
                            total: {
                                show: true,
                                label: 'Total',
                                formatter: function (w) {
                                    return w.globals.seriesTotals.reduce((a, b) => a + b, 0)
                                }
                            }
                        }
                    }
                }
            },
            dataLabels: { enabled: false }
        };

        var chartFee = new ApexCharts(document.querySelector("#feeStatusChart"), optionsFee);
        chartFee.render();
    });
</script>

<?php include '../../include/footer.php'; ?>
