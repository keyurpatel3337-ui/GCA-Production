<?php
header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once __DIR__ . '/../../common/api_client.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/format_helper.php';

// Load dashboard data via API
$api = new APIClient();
$response = $api->get('dashboard/accountant');

// Extract data from API response
if ($response && isset($response['success']) && $response['success']) {
    $stats = $response['data'] ?? [];
    $total_students = $stats['total_students'] ?? 0;
    $pending_fees = $stats['pending_fees'] ?? 0;
    $fully_paid = $stats['fully_paid'] ?? 0;
    $total_revenue = $stats['total_revenue'] ?? 0;
    $pending_token_fees = $stats['pending_fees'] ?? 0;
    $pending_payments = $stats['pending_payments'] ?? 0;
    $monthly_collection = $stats['monthly_collection'] ?? 0;
    $total_receipts = $stats['total_receipts'] ?? 0;
    $recent_transactions = $stats['recent_transactions'] ?? [];
    $chart_data = [];

    // Detailed Stats
    $standard_details = $stats['standard_details'] ?? [];
    $group_stats = $stats['groups'] ?? [];
    $medium_stats = $stats['mediums'] ?? [];
    $fee_stats = $stats['fee_status'] ?? [];
} else {
    // Fallback to default values if API fails
    $total_students = 0;
    $pending_fees = 0;
    $total_revenue = 0;
    $pending_token_fees = 0;
    $pending_payments = 0;
    $monthly_collection = 0;
    $total_receipts = 0;
    $recent_transactions = [];
    $chart_data = [];
    $standard_details = [];
    $group_stats = [];
    $medium_stats = [];
    $fee_stats = [];
}
?>

<?php
$page_title = "Account Dashboard";
include '../../include/header.php'; ?>
<?php
include '../../include/navbar.php'; ?>
<?php
include '../../include/sidebar.php'; ?>




<div class="container-fluid">
    <?php
    include '../../include/mfa_alert.php';
    ?>
    <?php
    if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i> <?php
            echo gca_safe_html($_SESSION['success']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <?php
    endif; ?>

    <?php
    if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle"></i> <?php
            echo gca_safe_html($_SESSION['error']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <?php
    endif; ?>


    <!-- Statistics Cards -->
    <div class="row g-4 mb-5">
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="glass-card">
                <div class="stat-card">
                    <div class="stat-top">
                        <div>
                            <div class="stat-value"><?php echo formatIndianCurrency($total_students, false); ?></div>
                            <div class="stat-label">Total Students</div>
                        </div>
                        <div class="stat-icon bg-icon-info">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                    </div>
                    <a href="../students/students.php?view=enrolled" class="stat-link text-info">
                        View Details <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="glass-card">
                <div class="stat-card">
                    <div class="stat-top">
                        <div>
                            <div class="stat-value"><?php echo formatIndianCurrency($pending_token_fees, false); ?>
                            </div>
                            <div class="stat-label">Pending Token Fees</div>
                        </div>
                        <div class="stat-icon bg-icon-warning">
                            <i class="fas fa-money-check-alt"></i>
                        </div>
                    </div>
                    <a href="../students/students.php?view=registered" class="stat-link text-warning">
                        Collect Fees <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="glass-card">
                <div class="stat-card">
                    <div class="stat-top">
                        <div>
                            <div class="stat-value"><?php echo formatIndianCurrency($pending_payments, false); ?></div>
                            <div class="stat-label">Pending Term Fees</div>
                        </div>
                        <div class="stat-icon bg-icon-primary">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <a href="../payments/pending-payments.php" class="stat-link text-primary">
                        View Details <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="glass-card">
                <div class="stat-card">
                    <div class="stat-top">
                        <div>
                            <div class="stat-value"><?php echo formatIndianCurrency($fully_paid, false); ?></div>
                            <div class="stat-label">Fully Paid Students</div>
                        </div>
                        <div class="stat-icon bg-icon-success">
                            <i class="fas fa-check-double"></i>
                        </div>
                    </div>
                    <a href="../students/students.php?view=enrolled&payment_status=paid" class="stat-link text-success">
                        View Details <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="glass-card">
                <div class="stat-card">
                    <div class="stat-top">
                        <div>
                            <div class="stat-value">₹<?php echo formatIndianCurrency($monthly_collection); ?>
                            </div>
                            <div class="stat-label">Monthly Collection</div>
                        </div>
                        <div class="stat-icon bg-icon-success">
                            <i class="fas fa-rupee-sign"></i>
                        </div>
                    </div>
                    <a href="../payments/financial-reports.php" class="stat-link text-success">
                        View Report <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Secondary Metrics Row -->
    <div class="row g-4 mb-5">
        <div class="col-lg-3 col-md-6">
            <a href="../payments/payments.php" class="text-decoration-none text-dark">
                <div class="glass-card p-3 d-flex align-items-center shadow-hover">
                    <div class="stat-icon bg-icon-danger me-3 css-accountant_dashboard-4fd294">
                        <i class="fas fa-receipt"></i>
                    </div>
                    <div>
                        <div class="stat-value fs-3 mb-0"><?php echo formatIndianCurrency($total_receipts, false); ?>
                        </div>
                        <div class="stat-label">Total Receipts</div>
                    </div>
                </div>
            </a>
        </div>
    </div>


    <!-- Standard-wise Performance Row -->
    <h4 class="section-title mt-5">
        <i class="fas fa-graduation-cap text-primary"></i> Standard-wise Performance
    </h4>
    <div class="row g-4 mb-5">
        <?php 
        $std_icons = ['11th' => 'fa-book-open', '12th' => 'fa-graduation-cap', 'Reneet' => 'fa-redo'];
        $std_colors = ['11th' => 'info', '12th' => 'primary', 'Reneet' => 'danger'];
        
        foreach ($standard_details as $name => $data): 
            $color = $std_colors[$name] ?? 'secondary';
            $icon = $std_icons[$name] ?? 'fa-user-graduate';
        ?>
        <div class="col-xl-4 col-lg-4">
            <div class="glass-card h-100 overflow-hidden">
                <div class="p-3 border-bottom border-light bg-<?php echo $color; ?>-subtle d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold text-<?php echo $color; ?>">
                        <i class="fas <?php echo $icon; ?> me-2"></i><?php echo $name; ?>
                    </h5>
                    <span class="badge bg-<?php echo $color; ?> text-white rounded-pill">Total: <?php echo $data['registered']; ?></span>
                </div>
                <div class="p-4">
                    <div class="row g-2 mb-4">
                        <div class="col-4">
                            <div class="p-2 rounded-3 bg-light text-center border">
                                <div class="small text-muted mb-1">Reg.</div>
                                <div class="h6 mb-0 fw-bold"><?php echo $data['registered']; ?></div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 rounded-3 bg-light text-center border">
                                <div class="small text-muted mb-1">Enrolled</div>
                                <div class="h6 mb-0 fw-bold text-primary"><?php echo $data['enrolled']; ?></div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 rounded-3 bg-success-subtle text-center border border-success-subtle">
                                <div class="small text-success mb-1">Paid</div>
                                <div class="h6 mb-0 fw-bold text-success"><?php echo $data['paid']; ?></div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 rounded-3 bg-warning-subtle text-center border border-warning-subtle">
                                <div class="small text-warning mb-1">Partial</div>
                                <div class="h6 mb-0 fw-bold text-warning"><?php echo $data['partial']; ?></div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 rounded-3 bg-danger-subtle text-center border border-danger-subtle">
                                <div class="small text-danger mb-1">Pending</div>
                                <div class="h6 mb-0 fw-bold text-danger"><?php echo $data['pending']; ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-2 pt-3 border-top">
                        <div class="mb-3">
                            <div class="small fw-bold text-muted mb-2"><i class="fas fa-users me-1"></i>Groups</div>
                            <div class="d-flex flex-wrap gap-1">
                                <?php foreach ($data['groups'] as $g): ?>
                                    <span class="badge bg-white text-dark border fw-normal"><?php echo $g['name']; ?>: <strong><?php echo $g['count']; ?></strong></span>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="mb-0">
                            <div class="small fw-bold text-muted mb-2"><i class="fas fa-language me-1"></i>Mediums</div>
                            <div class="d-flex flex-wrap gap-1">
                                <?php foreach ($data['mediums'] as $m): ?>
                                    <span class="badge bg-white text-dark border fw-normal"><?php echo $m['name']; ?>: <strong><?php echo $m['count']; ?></strong></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Detailed Analysis Row -->
    <h4 class="section-title">
        <i class="fas fa-chart-pie text-success"></i> Demographic & Payment Analysis
    </h4>
    <div class="row g-4 mb-5">
        <!-- Group Wise -->
        <div class="col-xl-4 col-lg-4">
            <div class="glass-card h-100">
                <div class="p-3 border-bottom border-light">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-users text-success me-2"></i>Group Wise Breakdown</h6>
                </div>
                <div class="p-3">
                    <?php 
                    if (!empty($group_stats)):
                        foreach ($group_stats as $g): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="fw-medium text-muted"><?php echo $g['name']; ?></span>
                            <span class="badge bg-success-subtle text-success rounded-pill px-3 py-2"><?php echo $g['count']; ?> Students</span>
                        </div>
                        <?php endforeach; 
                    else:
                        echo '<div class="text-center py-4 text-muted">No data available</div>';
                    endif;
                    ?>
                </div>
            </div>
        </div>

        <!-- Medium Wise -->
        <div class="col-xl-4 col-lg-4">
            <div class="glass-card h-100">
                <div class="p-3 border-bottom border-light">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-language text-info me-2"></i>Medium Wise Distribution</h6>
                </div>
                <div class="p-3">
                    <?php 
                    if (!empty($medium_stats)):
                        foreach ($medium_stats as $m): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="fw-medium text-muted"><?php echo $m['name']; ?></span>
                            <span class="badge bg-info-subtle text-info rounded-pill px-3 py-2"><?php echo $m['count']; ?> Students</span>
                        </div>
                        <?php endforeach; 
                    else:
                        echo '<div class="text-center py-4 text-muted">No data available</div>';
                    endif;
                    ?>
                </div>
            </div>
        </div>

        <!-- Fee Status Wise -->
        <div class="col-xl-4 col-lg-4">
            <div class="glass-card h-100">
                <div class="p-3 border-bottom border-light">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-money-bill-wave text-warning me-2"></i>Overall Fee Standing</h6>
                </div>
                <div class="p-3">
                    <?php 
                    $fee_colors = ['paid' => 'success', 'pending' => 'danger', 'partial' => 'warning', 'overdue' => 'dark'];
                    if (!empty($fee_stats)):
                        foreach ($fee_stats as $f): 
                            $color = $fee_colors[strtolower($f['name'])] ?? 'secondary';
                        ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="fw-medium text-muted text-capitalize"><?php echo $f['name']; ?></span>
                            <span class="badge bg-<?php echo $color; ?>-subtle text-<?php echo $color; ?> rounded-pill px-3 py-2"><?php echo $f['count']; ?> Students</span>
                        </div>
                        <?php endforeach; 
                    else:
                        echo '<div class="text-center py-4 text-muted">No data available</div>';
                    endif;
                    ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <h4 class="section-title">
        <i class="fas fa-bolt text-warning"></i> Quick Actions
    </h4>

    <div class="row g-3 mb-5">
        <div class="col-xl-3 col-lg-3 col-md-6 col-6">
            <a href="../payments/add-payment.php" class="quick-action-btn">
                <div class="quick-icon bg-soft-primary text-primary bg-opacity-10 css-accountant_dashboard-a29ce1">
                    <i class="fas fa-plus"></i>
                </div>
                <div class="quick-info">
                    <strong>Payments</strong>
                    <span>Add New</span>
                </div>
            </a>
        </div>

        <div class="col-xl-3 col-lg-3 col-md-6 col-6">
            <a href="../payments/financial-reports.php" class="quick-action-btn">
                <div class="quick-icon bg-soft-warning text-warning bg-opacity-10 css-accountant_dashboard-8557c1">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="quick-info">
                    <strong>Reports</strong>
                    <span>Financial</span>
                </div>
            </a>
        </div>

        <div class="col-xl-3 col-lg-3 col-md-6 col-6">
            <a href="../students/students.php?view=enrolled" class="quick-action-btn">
                <div class="quick-icon bg-soft-danger text-danger bg-opacity-10 css-accountant_dashboard-2376f6">
                    <i class="fas fa-users"></i>
                </div>
                <div class="quick-info">
                    <strong>Students</strong>
                    <span>View All</span>
                </div>
            </a>
        </div>
        
        <div class="col-xl-3 col-lg-3 col-md-6 col-6">
            <a href="../reports/financial/student-ledger.php" class="quick-action-btn">
                <div class="quick-icon bg-soft-success text-success bg-opacity-10 css-accountant_dashboard-a31f5e">
                    <i class="fas fa-book"></i>
                </div>
                <div class="quick-info">
                    <strong>Ledger</strong>
                    <span>Student</span>
                </div>
            </a>
        </div>
    </div>




</div>
</div>

<?php
include '../../include/footer.php'; ?>