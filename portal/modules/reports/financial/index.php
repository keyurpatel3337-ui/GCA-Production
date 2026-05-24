<?php
/**
 * Financial Reports Dashboard
 * Unified entry point for all financial reports
 */

require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/common/constants.php';
require_once __DIR__ . '/../../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once __DIR__ . '/../../../common/api_client.php';
require_once __DIR__ . '/../../../../common/helpers/format_helper.php';

// Check access - Accountant, Principal, or Super Admin
if (!hasRole(ROLE_ACCOUNTANT) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
$dbOps = new DatabaseOperations();




$page_title = "Financial Reports";
$page_breadcrumb = "Financial Reports";

// Get quick stats via API
$api = new APIClient();

// Today's collection
$today = date('Y-m-d');
$todayStats = $api->get('payments/financial-reports', [
    'from_date' => $today,
    'to_date' => $today,
    'chart_view' => 'daily'
]);
$todayCollection = $todayStats['data']['collection_stats']['total'] ?? 0;
$todayCount = $todayStats['data']['collection_stats']['count'] ?? 0;

// This month's collection
$monthStart = date('Y-m-01');
$monthStats = $api->get('payments/financial-reports', [
    'from_date' => $monthStart,
    'to_date' => $today,
    'chart_view' => 'daily'
]);
$monthCollection = $monthStats['data']['collection_stats']['total'] ?? 0;
$monthCount = $monthStats['data']['collection_stats']['count'] ?? 0;

// This week's collection
$weekStart = date('Y-m-d', strtotime('monday this week'));
$weekStats = $api->get('payments/financial-reports', [
    'from_date' => $weekStart,
    'to_date' => $today,
    'chart_view' => 'daily'
]);
$weekCollection = $weekStats['data']['collection_stats']['total'] ?? 0;
$weekCount = $weekStats['data']['collection_stats']['count'] ?? 0;

// Define report categories
$reportCategories = [
    [
        'title' => 'Collection Reports',
        'icon' => 'fas fa-chart-line',
        'color' => 'bg-primary',
        'description' => 'Daily, weekly, monthly collection trends and breakdowns',
        'reports' => [
            ['name' => 'Collection Summary', 'link' => PORTAL_URL . '/modules/payments/financial-reports.php', 'icon' => 'fas fa-coins'],
            ['name' => 'Direct Collection Report', 'link' => 'without-gst-report.php', 'icon' => 'fas fa-file-invoice-dollar'],
            ['name' => 'Payment Mode Breakdown', 'link' => PORTAL_URL . '/modules/payments/financial-reports.php#mode', 'icon' => 'fas fa-credit-card'],
            ['name' => 'Payment Type Breakdown', 'link' => 'payment-type-breakdown.php', 'icon' => 'fas fa-tags'],
            ['name' => 'Receipt Breakdown', 'link' => 'receipt-breakdown.php', 'icon' => 'fas fa-receipt'],
            ['name' => 'Daily Pivot Table', 'link' => PORTAL_URL . '/modules/payments/financial-reports.php#daily', 'icon' => 'fas fa-table'],
        ]
    ],
    [
        'title' => 'Pending Fee Reports',
        'icon' => 'fas fa-exclamation-triangle',
        'color' => 'bg-warning',
        'description' => 'Outstanding fees, defaulters, and installment status',
        'reports' => [
            ['name' => 'Fee Due Report', 'link' => 'pending-fees.php', 'icon' => 'fas fa-file-invoice-dollar'],
            ['name' => 'Overdue Fee Report', 'link' => 'fee-defaulters.php?threshold=1', 'icon' => 'fas fa-clock'],
            ['name' => 'Fee Defaulters List', 'link' => 'fee-defaulters.php', 'icon' => 'fas fa-user-times'],
            ['name' => 'Installment Status', 'link' => 'installment-status.php', 'icon' => 'fas fa-calendar-check'],
        ]
    ],
    [
        'title' => 'Student-wise Reports',
        'icon' => 'fas fa-user-graduate',
        'color' => 'bg-info',
        'description' => 'Individual student payment history and ledger',
        'reports' => [
            ['name' => 'Student Fee Ledger', 'link' => 'student-ledger.php', 'icon' => 'fas fa-book'],
            ['name' => 'Payment Statement', 'link' => 'student-ledger.php', 'icon' => 'fas fa-history'],
            ['name' => 'Partial Payments', 'link' => 'partial-payments.php', 'icon' => 'fas fa-percentage'],
        ]
    ],
    [
        'title' => 'Refund Reports',
        'icon' => 'fas fa-undo-alt',
        'color' => 'bg-danger',
        'description' => 'Refunds, cancellations, and failed transactions',
        'reports' => [
            ['name' => 'Refund Report', 'link' => 'refunds.php', 'icon' => 'fas fa-hand-holding-usd'],
            ['name' => 'Cancelled Payments', 'link' => 'cancelled-payments.php', 'icon' => 'fas fa-ban'],
            ['name' => 'Failed Transactions', 'link' => 'failed-transactions.php', 'icon' => 'fas fa-times-circle'],
        ]
    ],
    [
        'title' => 'Comparison Reports',
        'icon' => 'fas fa-balance-scale',
        'color' => 'bg-secondary',
        'description' => 'Month-on-month and year-on-year comparisons',
        'reports' => [
            ['name' => 'Month-on-Month', 'link' => 'mom-comparison.php', 'icon' => 'fas fa-calendar-alt'],
            ['name' => 'Year-on-Year', 'link' => 'yoy-comparison.php', 'icon' => 'fas fa-chart-bar'],
            ['name' => 'Target vs Actual', 'link' => 'target-vs-actual.php', 'icon' => 'fas fa-bullseye'],
        ]
    ],
    [
        'title' => 'Category Reports',
        'icon' => 'fas fa-layer-group',
        'color' => 'bg-success',
        'description' => 'Class-wise, group-wise, and special fee reports',
        'reports' => [
            ['name' => 'Class-wise Collection', 'link' => 'class-wise.php', 'icon' => 'fas fa-chalkboard'],
            ['name' => 'Group-wise Collection', 'link' => 'group-wise.php', 'icon' => 'fas fa-users'],
            ['name' => 'Hostel Fee Report', 'link' => 'hostel-fees.php', 'icon' => 'fas fa-building'],
            ['name' => 'Transport Fee Report', 'link' => 'transport-fees.php', 'icon' => 'fas fa-bus'],
        ]
    ],
    [
        'title' => 'Receipt & Accounting',
        'icon' => 'fas fa-receipt',
        'color' => 'bg-dark',
        'description' => 'Receipt register, day book, and collector reports',
        'reports' => [
            ['name' => 'Receipt Register', 'link' => 'receipt-register.php', 'icon' => 'fas fa-file-alt'],
            ['name' => 'Receipt Breakdown', 'link' => 'receipt-breakdown.php', 'icon' => 'fas fa-receipt'],
            ['name' => 'Day Book', 'link' => 'day-book.php', 'icon' => 'fas fa-book-open'],
            ['name' => 'Collector-wise Report', 'link' => 'collector-wise.php', 'icon' => 'fas fa-user-tie'],
        ]
    ],
    [
        'title' => 'Scholarship Reports',
        'icon' => 'fas fa-graduation-cap',
        'color' => 'bg-purple',
        'description' => 'Scholarships, discounts, and fee waivers',
        'reports' => [
            ['name' => 'Scholarship Applied', 'link' => 'scholarships.php', 'icon' => 'fas fa-medal'],
            ['name' => 'Discount Given', 'link' => 'discounts.php', 'icon' => 'fas fa-percent'],
            ['name' => 'Combine Scholarship & Discount', 'link' => 'combined-scholarship-discount.php', 'icon' => 'fas fa-balance-scale'],
            ['name' => 'Group Change Fee Impact', 'link' => '../group-change-report.php', 'icon' => 'fas fa-exchange-alt'],
        ]
    ],
    [
        'title' => 'Gateway Reports',
        'icon' => 'fas fa-network-wired',
        'color' => 'bg-indigo',
        'description' => 'Payment gateway transactions and audit',
        'reports' => [
            ['name' => 'Gateway Transactions', 'link' => 'gateway-transactions.php', 'icon' => 'fas fa-exchange-alt'],
            ['name' => 'Transaction Audit', 'link' => 'transaction-audit.php', 'icon' => 'fas fa-shield-alt'],
            ['name' => 'GST Report', 'link' => 'gst-report.php', 'icon' => 'fas fa-file-invoice'],
            ['name' => 'Term-wise Report', 'link' => 'term-wise.php', 'icon' => 'fas fa-calendar'],
        ]
    ],
    [
        'title' => 'Wallet System Reports',
        'icon' => 'fas fa-wallet',
        'color' => 'bg-wallet-grad',
        'description' => 'Daily Day Books, Merchant Settlements, Deposit Summaries, and Low Balances',
        'reports' => [
            ['name' => 'Wallet Ledger History', 'link' => 'wallet-reports.php?report_type=transaction_history', 'icon' => 'fas fa-history'],
            ['name' => 'Daily Day Book (Reconciliation)', 'link' => 'wallet-reports.php?report_type=day_book', 'icon' => 'fas fa-book'],
            ['name' => 'Merchant Settlement', 'link' => 'wallet-reports.php?report_type=merchant_settlement', 'icon' => 'fas fa-store'],
            ['name' => 'Low Balance List', 'link' => 'wallet-reports.php?report_type=low_balance', 'icon' => 'fas fa-exclamation-circle'],
            ['name' => 'Wallet Refund logs', 'link' => 'wallet-reports.php?report_type=refunds', 'icon' => 'fas fa-undo-alt'],
        ]
    ],
];




include '../../../include/header.php';
include '../../../include/navbar.php';
include '../../../include/sidebar.php';
?>




<div class="container-fluid py-4 pb-5">

    <!-- Quick Stats -->
    <div class="row g-4 mb-4">
        <div class="col-xl-3 col-lg-6">
            <div class="glass-card h-100">
                <div class="stat-card">
                    <div class="stat-top">
                        <div>
                            <div class="stat-value">
                                ₹<?php echo formatIndianCurrency($todayCollection); ?>
                            </div>
                            <div class="stat-label">Today's Collection</div>
                        </div>
                        <div class="stat-icon bg-icon-success">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <span class="badge bg-soft-success text-success bg-opacity-10 px-0">
                            <i class="fas fa-receipt me-1"></i> <?php echo $todayCount; ?> transactions
                        </span>
                        <a href="<?php echo PORTAL_URL; ?>/modules/payments/financial-reports.php?date_range=today"
                            class="stat-link text-success">
                            View <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6">
            <div class="glass-card h-100">
                <div class="stat-card">
                    <div class="stat-top">
                        <div>
                            <div class="stat-value">
                                ₹<?php echo formatIndianCurrency($weekCollection); ?>
                            </div>
                            <div class="stat-label">This Week's Total</div>
                        </div>
                        <div class="stat-icon bg-icon-info">
                            <i class="fas fa-calendar-week"></i>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <span class="badge bg-soft-info text-info bg-opacity-10 px-0">
                            <i class="fas fa-receipt me-1"></i> <?php echo $weekCount; ?> transactions
                        </span>
                        <a href="<?php echo PORTAL_URL; ?>/modules/payments/financial-reports.php?date_range=this_week"
                            class="stat-link text-info">
                            View <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6">
            <div class="glass-card h-100">
                <div class="stat-card">
                    <div class="stat-top">
                        <div>
                            <div class="stat-value">
                                ₹<?php echo formatIndianCurrency($monthCollection); ?>
                            </div>
                            <div class="stat-label">Monthly Revenue</div>
                        </div>
                        <div class="stat-icon bg-icon-primary">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <span class="badge bg-soft-primary text-primary bg-opacity-10 px-0">
                            <i class="fas fa-receipt me-1"></i> <?php echo $monthCount; ?> transactions
                        </span>
                        <a href="<?php echo PORTAL_URL; ?>/modules/payments/financial-reports.php?date_range=current_month"
                            class="stat-link text-primary">
                            View <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6">
            <div class="glass-card h-100">
                <div class="stat-card">
                    <div class="stat-top">
                        <div>
                            <div class="stat-value"><i class="fas fa-hourglass-half fs-3"></i></div>
                            <div class="stat-label">Pending Dues</div>
                        </div>
                        <div class="stat-icon bg-icon-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <span class="badge bg-soft-warning text-warning bg-opacity-10 px-0">
                            <i class="fas fa-file-invoice me-1"></i> Outstanding
                        </span>
                        <a href="pending-fees.php" class="stat-link text-warning">
                            View Report <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Access -->
    <h4 class="section-title mb-4">
        <i class="fas fa-bolt text-warning"></i> Quick Access
    </h4>
    <div class="row g-3 mb-5">
        <div class="col-xl-3 col-lg-3 col-md-6">
            <a href="<?php echo PORTAL_URL; ?>/modules/payments/financial-reports.php" class="quick-action-btn h-100">
                <div class="quick-icon bg-soft-primary text-primary css-index-947eef">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="quick-info">
                    <strong>Collection Summary</strong>
                    <span>Overall trends</span>
                </div>
            </a>
        </div>
        <div class="col-xl-3 col-lg-3 col-md-6">
            <a href="pending-fees.php" class="quick-action-btn h-100">
                <div class="quick-icon bg-soft-warning text-warning css-index-8557c1">
                    <i class="fas fa-file-invoice-dollar"></i>
                </div>
                <div class="quick-info">
                    <strong>Pending Fees</strong>
                    <span>Outstanding balances</span>
                </div>
            </a>
        </div>
        <div class="col-xl-3 col-lg-3 col-md-6">
            <a href="receipt-register.php" class="quick-action-btn h-100">
                <div class="quick-icon bg-soft-dark text-dark css-index-68d287">
                    <i class="fas fa-receipt"></i>
                </div>
                <div class="quick-info">
                    <strong>Receipt Register</strong>
                    <span>Transaction log</span>
                </div>
            </a>
        </div>
        <div class="col-xl-3 col-lg-3 col-md-6">
            <a href="day-book.php" class="quick-action-btn h-100">
                <div class="quick-icon bg-soft-info text-info css-index-cab35b">
                    <i class="fas fa-book-open"></i>
                </div>
                <div class="quick-info">
                    <strong>Day Book</strong>
                    <span>Daily accounts</span>
                </div>
            </a>
        </div>
    </div>

    <!-- Report Categories Grid -->
    <h4 class="section-title mb-4">
        <i class="fas fa-layer-group text-primary"></i> Report Categories
    </h4>
    <div class="report-grid">
        <?php foreach ($reportCategories as $category): ?>
            <?php
            // Only show Wallet System Reports to Super Admin and Principal
            if ($category['color'] === 'bg-wallet-grad' && !hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE)) {
                continue;
            }
            ?>
            <div class="glass-card">
                <div
                    class="card-header <?php echo $category['color']; ?> text-white border-0 py-3 shadow-sm d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-3">
                        <i class="<?php echo $category['icon']; ?> fs-4"></i>
                        <h5 class="mb-0 fw-semibold"><?php echo $category['title']; ?></h5>
                    </div>
                    <span
                        class="badge bg-white text-dark bg-opacity-25 rounded-pill px-3"><?php echo count($category['reports']); ?></span>
                </div>
                <div class="card-body py-3">
                    <p class="text-muted small mb-3 opacity-75"><?php echo $category['description']; ?></p>
                    <div class="list-group list-group-flush">
                        <?php foreach ($category['reports'] as $report): ?>
                            <a href="<?php echo $report['link']; ?>" class="report-item-link px-1">
                                <i class="<?php echo $report['icon']; ?>"></i>
                                <span class="fw-medium"><?php echo $report['name']; ?></span>
                                <i class="fas fa-chevron-right ms-auto opacity-25 fs-xs css-index-dcab71"></i>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Export All Data -->
    <h4 class="section-title mt-5 mb-4">
        <i class="fas fa-download text-success"></i> Comprehensive Export
    </h4>
    <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
        <div class="card-body p-4 bg-white">
            <div class="row align-items-center">
                <div class="col-lg-6 mb-3 mb-lg-0">
                    <h5 class="fw-bold mb-1">Export Financial Ledger</h5>
                    <p class="text-muted small mb-0">Generate and download combined reports for auditing and
                        compliance.</p>
                </div>
                <div class="col-lg-6 text-lg-end">
                    <div class="btn-group gap-2 d-flex d-lg-inline-flex">
                        <button class="btn btn-success px-4" onclick="exportAllData('excel')">
                            <i class="fas fa-file-excel me-2"></i> Excel
                        </button>
                        <button class="btn btn-danger px-4" onclick="exportAllData('pdf')">
                            <i class="fas fa-file-pdf me-2"></i> Export PDF
                        </button>
                        <button class="btn btn-light border px-4" onclick="window.print()">
                            <i class="fas fa-print me-2"></i> Print
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../../include/footer.php'; ?>

<script>
    function exportAllData(format) {
        const today = new Date().toISOString().split('T')[0];
        const monthStart = today.substring(0, 8) + '01';

        // Redirect to export endpoint
        const params = new URLSearchParams({
            format: format,
            from_date: monthStart,
            to_date: today,
            report_type: 'all'
        });

        window.location.href = `export.php?${params.toString()}`;
    }
</script>