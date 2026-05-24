<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once PORTAL_GLOBALVARIABLE;
require_once __DIR__ . '/../../common/api_client.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/format_helper.php';

// Auth check
if (!hasRole(ROLE_WALLET_MANAGER) && !hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ../../login.php');
    exit;
}

// Load dashboard data via API
$api = new APIClient();
$response = $api->get('dashboard/wallet_manager');

// Extract data from API response
if ($response && isset($response['success']) && $response['success']) {
    $stats = $response['data'] ?? [];
} else {
    $stats = [];
}

$total_balance = $stats['total_balance'] ?? 0;
$deposits_today = $stats['deposits_today'] ?? 0;
$transactions_today = $stats['transactions_today'] ?? 0;
$active_wallets = $stats['active_wallets'] ?? 0;

$page_title = "Wallet Manager Dashboard";
$page_breadcrumb = "Dashboard";

include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>

<div class="container-fluid py-4 pb-5">
    <?php
    include '../../include/mfa_alert.php';
    ?>
    <div class="row g-4 mb-5">
        <!-- Stats Cards -->
        <div class="col-xl-3 col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100 bg-primary text-white">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="icon-box bg-white bg-opacity-25 rounded-circle p-3">
                            <i class="fas fa-wallet fs-4"></i>
                        </div>
                        <span class="badge bg-white bg-opacity-25 rounded-pill">Global</span>
                    </div>
                    <h6 class="text-uppercase tracking-wider small opacity-75 mb-1">Total Wallet Balance</h6>
                    <h2 class="fw-bold mb-0">₹
                        <?php echo formatIndianCurrency($total_balance); ?>
                    </h2>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100 bg-success text-white">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="icon-box bg-white bg-opacity-25 rounded-circle p-3">
                            <i class="fas fa-arrow-down fs-4"></i>
                        </div>
                        <span class="badge bg-white bg-opacity-25 rounded-pill">Today</span>
                    </div>
                    <h6 class="text-uppercase tracking-wider small opacity-75 mb-1">Deposits Today</h6>
                    <h2 class="fw-bold mb-0">₹
                        <?php echo formatIndianCurrency($deposits_today); ?>
                    </h2>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100 bg-info text-white">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="icon-box bg-white bg-opacity-25 rounded-circle p-3">
                            <i class="fas fa-exchange-alt fs-4"></i>
                        </div>
                        <span class="badge bg-white bg-opacity-25 rounded-pill">Today</span>
                    </div>
                    <h6 class="text-uppercase tracking-wider small opacity-75 mb-1">Transactions</h6>
                    <h2 class="fw-bold mb-0">
                        <?php echo $transactions_today; ?>
                    </h2>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100 bg-warning text-white">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="icon-box bg-white bg-opacity-25 rounded-circle p-3">
                            <i class="fas fa-users fs-4"></i>
                        </div>
                        <span class="badge bg-white bg-opacity-25 rounded-pill">Total</span>
                    </div>
                    <h6 class="text-uppercase tracking-wider small opacity-75 mb-1">Active Wallets</h6>
                    <h2 class="fw-bold mb-0">
                        <?php echo $active_wallets; ?>
                    </h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <h4 class="fw-bold mb-4 text-dark d-flex align-items-center">
        <i class="fas fa-bolt text-warning me-2"></i> Quick Management
    </h4>

    <div class="row g-4 mb-5">
        <div class="col-xl-3 col-lg-4 col-md-6">
            <a href="<?php echo PORTAL_URL; ?>/modules/fees/manual-wallet-deposit.php"
                class="card border-0 shadow-sm rounded-4 text-decoration-none h-100 action-card">
                <div class="card-body p-4 d-flex align-items-center">
                    <div class="icon-box bg-success-light text-success rounded-circle p-3 me-3">
                        <i class="fas fa-plus-circle fs-3"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold text-dark mb-1">Manual Deposit</h6>
                        <p class="text-muted small mb-0">Add funds to student wallets</p>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-xl-3 col-lg-4 col-md-6">
            <a href="<?php echo PORTAL_URL; ?>/modules/reports/financial/wallet-reports.php"
                class="card border-0 shadow-sm rounded-4 text-decoration-none h-100 action-card">
                <div class="card-body p-4 d-flex align-items-center">
                    <div class="icon-box bg-primary-light text-primary rounded-circle p-3 me-3">
                        <i class="fas fa-file-invoice fs-3"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold text-dark mb-1">Wallet Reports</h6>
                        <p class="text-muted small mb-0">View transaction history</p>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-xl-3 col-lg-4 col-md-6">
            <a href="<?php echo PORTAL_URL; ?>/modules/fees/manage-wallet-accounts.php"
                class="card border-0 shadow-sm rounded-4 text-decoration-none h-100 action-card">
                <div class="card-body p-4 d-flex align-items-center">
                    <div class="icon-box bg-danger-light text-danger rounded-circle p-3 me-3" style="background-color: rgba(255, 71, 87, 0.1); color: #ff4757;">
                        <i class="fas fa-users-cog fs-3"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold text-dark mb-1">Manage Wallets</h6>
                        <p class="text-muted small mb-0">Set limits & block accounts</p>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <!-- Recent Activity Placeholder -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-0 p-4">
                    <h5 class="fw-bold mb-0">System Alerts</h5>
                </div>
                <div class="card-body p-4 pt-0">
                    <div class="alert alert-info border-0 rounded-4 d-flex align-items-center p-3">
                        <i class="fas fa-info-circle fs-4 me-3"></i>
                        <div>
                            <h6 class="fw-bold mb-1">Wallet API Sync</h6>
                            <p class="mb-0 small">Dashboard statistics are fetched in real-time from the external wallet
                                system.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .bg-success-light {
        background-color: rgba(46, 213, 115, 0.1);
    }

    .bg-primary-light {
        background-color: rgba(0, 123, 255, 0.1);
    }

    .bg-danger-light {
        background-color: rgba(255, 71, 87, 0.1);
    }

    .action-card {
        transition: all 0.3s ease;
        border: 1px solid transparent !important;
    }

    .action-card:hover {
        transform: translateY(-5px);
        border-color: rgba(0, 123, 255, 0.2) !important;
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05) !important;
    }

    .tracking-wider {
        letter-spacing: 0.1em;
    }
</style>

<?php include '../../include/footer.php'; ?>