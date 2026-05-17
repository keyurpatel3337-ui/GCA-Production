<?php

require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once __DIR__ . '/../../common/api_client.php';
require_once __DIR__ . '/../../../common/helpers/format_helper.php';

// Load dashboard data via API
$api = new APIClient();
$response = $api->get('dashboard/student', ['student_id' => $user_id]);

// Extract data from API response
if ($response && isset($response['success']) && $response['success']) {
    $data = $response['data'] ?? [];
    $student_info = $data['student_info'] ?? null;
    $enrollment_info = $data['enrollment_info'] ?? null;
    $fee_allocated = $data['fee_allocated'] ?? false;
    $stats = $data['stats'] ?? [];
    $recent_results = $data['recent_results'] ?? [];

    // Extract stats
    $pending_appointments = $stats['pending_appointments'] ?? 0;
    $completed_appointments = $stats['completed_appointments'] ?? 0;
    $total_tests = $stats['total_tests'] ?? 0;
    $avg_score = $stats['avg_score'] ?? 0;

    // Fetch Wallet Balance
    $wallet_api_url = defined('WALLET_API_URL') ? WALLET_API_URL : null;
    $wallet_balance = 0;
    if ($wallet_api_url) {
        $ch = curl_init($wallet_api_url . '/balance/check.php?student_id=' . $user_id);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-API-KEY: ' . (defined('GCA_PORTAL_KEY') ? GCA_PORTAL_KEY : '')
        ]);
        $wallet_res = json_decode(curl_exec($ch), true);
        if ($wallet_res && isset($wallet_res['status']) && $wallet_res['status'] === 'success') {
            $wallet_balance = $wallet_res['data']['balance'];
        }
    }
} else {
    // Fallback to default values if API fails
    $student_info = ['token_fees_paid' => 0];
    $enrollment_info = null;
    $fee_allocated = false;
    $pending_appointments = 0;
    $completed_appointments = 0;
    $total_tests = 0;
    $avg_score = 0;
    $recent_results = [];
}
// Set Page Title
$page_title = "Student Dashboard";
$page_breadcrumb = "Dashboard";
?>
<?php
include '../../include/header.php'; ?>
<?php
include '../../include/navbar.php'; ?>
<?php
include '../../include/sidebar.php'; ?>



<div class="container-fluid py-4 pb-5">
    <?php
    if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="fas fa-check-circle fs-4 me-3"></i>
                <div><?php echo htmlspecialchars($_SESSION['success'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php
    endif; ?>

    <?php
    if (isset($_SESSION['error']) || isset($_SESSION['error_msg'])): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="fas fa-exclamation-circle fs-4 me-3"></i>
                <div><?php echo htmlspecialchars($_SESSION['error'] ?? $_SESSION['error_msg'], ENT_QUOTES, 'UTF-8');
                unset($_SESSION['error'], $_SESSION['error_msg']); ?></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php
    endif; ?>

    <?php
    if (isset($_SESSION['warning_msg'])): ?>
        <div class="alert alert-warning alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="fas fa-exclamation-triangle fs-4 me-3"></i>
                <div><?php echo htmlspecialchars($_SESSION['warning_msg'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php
    endif; ?>

    <?php
    if (isset($_SESSION['info_msg'])): ?>
        <div class="alert alert-info alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="fas fa-info-circle fs-4 me-3"></i>
                <div><?php echo htmlspecialchars($_SESSION['info_msg'] ?? '', ENT_QUOTES, 'UTF-8');
                unset($_SESSION['info_msg']); ?></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php
    endif; ?>

    <!-- Token Payment Status Banner -->
    <?php
    if (!$student_info['token_fees_paid'] && ($student_info['course_id'] ?? 0) != 6): ?>
        <div class="alert alert-warning mb-4 shadow-sm" style="border-start: 4px solid #ff9800;">
            <div class="d-flex align-items-center">
                <div class="me-3">
                    <i class="fas fa-exclamation-triangle fa-2x text-warning"></i>
                </div>
                <div class="flex-grow-1">
                    <h5 class="alert-heading mb-1">
                        <i class="fas fa-rupee-sign"></i> Token Fee Payment Pending Verification
                    </h5>
                    <p class="mb-2">
                        Your offline token fee payment is under verification by the accounts department.
                        You have limited access to the portal until the payment is confirmed.
                    </p>
                    <small class="text-muted">
                        <i class="fas fa-info-circle"></i> For any queries, please contact the accounts department.
                    </small>
                </div>
            </div>
        </div>
        <?php
    endif; ?>

    <!-- Session Information Bar -->
    <!-- <div class="session-info-bar mb-3 d-flex flex-wrap justify-content-between align-items-center opacity-75">
        <div class="d-flex align-items-center me-3">
            <span class="badge bg-light text-dark border me-2">
                <i class="fas fa-id-card me-1 text-muted"></i> Aadhaar:
                <?php echo htmlspecialchars($_SESSION['student_aadhaar'] ?? 'N/A'); ?>
            </span>
            <span class="badge bg-light text-dark border me-2">
                <i class="fas fa-clock me-1 text-muted"></i> Login:
                <?php echo isset($_SESSION['login_time']) ? date('d-m-Y h:i A', $_SESSION['login_time']) : 'N/A'; ?>
            </span>
            <span class="badge bg-light text-dark border me-2">
                <i class="fas fa-network-wired me-1 text-muted"></i> IP: <?php echo $_SERVER['REMOTE_ADDR']; ?>
            </span>
        </div>

    <!-- Welcome Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="fw-bold text-dark mb-1">
                Welcome back, <?php echo htmlspecialchars($_SESSION['student_name'] ?? 'Student'); ?>! 👋
            </h2>
            <p class="text-muted">
                Here's what's happening with your counseling today.
            </p>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-4 mb-5">
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="glass-card h-100">
                <div class="stat-card">
                    <div class="stat-top">
                        <div>
                            <div class="stat-value"><?php echo gca_safe_html($pending_appointments); ?></div>
                            <div class="stat-label">Pending Appointments</div>
                        </div>
                        <div class="stat-icon bg-icon-info">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <a href="<?php echo PORTAL_URL; ?>/modules/student-portal/my-appointments.php?status=pending"
                        class="stat-link text-info">
                        View Details <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="glass-card h-100">
                <div class="stat-card">
                    <div class="stat-top">
                        <div>
                            <div class="stat-value"><?php echo gca_safe_html($completed_appointments); ?></div>
                            <div class="stat-label">Completed Sessions</div>
                        </div>
                        <div class="stat-icon bg-icon-success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <a href="<?php echo PORTAL_URL; ?>/modules/student-portal/my-appointments.php?status=completed"
                        class="stat-link text-success">
                        View Details <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="glass-card h-100">
                <div class="stat-card">
                    <div class="stat-top">
                        <div>
                            <div class="stat-value"><?php echo gca_safe_html($total_tests); ?></div>
                            <div class="stat-label">Tests Taken</div>
                        </div>
                        <div class="stat-icon bg-icon-warning">
                            <i class="fas fa-file-alt"></i>
                        </div>
                    </div>
                    <a href="<?php echo PORTAL_URL; ?>/modules/student-portal/my-results.php"
                        class="stat-link text-warning">
                        View Details <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="glass-card h-100">
                <div class="stat-card">
                    <div class="stat-top">
                        <div>
                            <div class="stat-value" id="wallet-balance-display">
                                ₹<?php echo formatIndianCurrency($wallet_balance); ?></div>
                            <div class="stat-label">Digital Wallet</div>
                        </div>
                        <div class="stat-icon bg-icon-primary">
                            <i class="fas fa-wallet"></i>
                        </div>
                    </div>
                    <a href="<?php echo PORTAL_URL; ?>/modules/student-portal/my-wallet.php"
                        class="stat-link text-primary">
                        Manage Wallet <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Enrollment Info Card (Division & Roll Number) -->
    <?php
    if ($enrollment_info): ?>
        <div class="row g-3 mb-4">
            <div class="col-12">
                <div class="card card-enhanced border-start border-primary border-4">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center"
                                    style="width: 60px; height: 60px;">
                                    <i class="fas fa-id-card fa-2x"></i>
                                </div>
                            </div>
                            <div class="col">
                                <h5 class="mb-1">Enrollment Information</h5>
                                <p class="text-muted mb-0">Enrollment No:
                                    <strong><?php
                                    echo htmlspecialchars($enrollment_info['enrollment_no'] ?? ''); ?></strong>
                                </p>
                            </div>
                            <div class="col-auto text-end">
                                <div class="row g-4">
                                    <div class="col">
                                        <span class="text-muted small">Standard</span>
                                        <br><span class="badge bg-info text-dark fs-6"><?php
                                        echo htmlspecialchars($enrollment_info['course_name'] ?? 'N/A'); ?></span>
                                    </div>
                                    <div class="col">
                                        <span class="text-muted small">Group</span>
                                        <br><span class="badge bg-secondary fs-6"><?php
                                        echo htmlspecialchars($enrollment_info['group_name'] ?? 'N/A'); ?></span>
                                    </div>
                                    <div class="col">
                                        <span class="text-muted small">Division</span>
                                        <br>
                                        <?php
                                        if ($enrollment_info['division_id']): ?>
                                            <span class="badge bg-success fs-5"><?php
                                            echo htmlspecialchars($enrollment_info['division_name'] ?? ''); ?></span>
                                            <?php
                                        else: ?>
                                            <span class="badge bg-warning text-dark fs-6">Not Assigned</span>
                                            <?php
                                        endif; ?>
                                    </div>
                                    <div class="col">
                                        <span class="text-muted small">Roll No</span>
                                        <br>
                                        <?php
                                        if ($enrollment_info['roll_no']): ?>
                                            <span class="badge bg-primary fs-4"><?php
                                            echo $enrollment_info['roll_no']; ?></span>
                                            <?php
                                        else: ?>
                                            <span class="badge bg-warning text-dark fs-6">Not Assigned</span>
                                            <?php
                                        endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    endif; ?>

    <div class="row">
        <div class="col-12">
            <?php
            $fee_summary = $data['fee_summary'] ?? null;

            if ($fee_summary):
                $total_pending = $fee_summary['total_pending'];
                $detailed_allocations = $fee_summary['allocations'];
                ?>

                <div class="card card-enhanced">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h3 class="card-title mb-0"><i class="fas fa-list-alt"></i> Fee Structure & Payment Details</h3>
                        <div class="header-stats">
                            <span class="badge bg-light text-dark fs-6 me-2">Total:
                                ₹<?php echo formatIndianCurrency($fee_summary['total_allocated']); ?></span>
                            <span
                                class="badge <?php echo $total_pending > 0 ? 'bg-warning text-dark' : 'bg-success'; ?> fs-6">
                                Status: <?php echo $fee_summary['status']; ?>
                            </span>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if ($total_pending > 0): ?>
                            <div
                                class="alert alert-warning mb-4 d-flex align-items-center justify-content-between shadow-sm border-start border-4 border-warning">
                                <div>
                                    <i class="fas fa-exclamation-circle fa-lg me-2"></i>
                                    <?php
                                    $pending_count = 0;
                                    foreach ($detailed_allocations as $alloc) {
                                        if ($alloc['pending_amount'] > 0)
                                            $pending_count++;
                                    }
                                    ?>
                                    <strong>You have <?php echo $pending_count; ?> pending fee components</strong>
                                    <span class="ms-3">Amount Due:
                                        <strong>₹<?php echo formatIndianCurrency($total_pending); ?></strong></span>
                                </div>
                                <button type="button" onclick="payAllPendingFees()" class="btn btn-success btn-lg shadow-sm">
                                    <i class="fas fa-credit-card me-1"></i> Pay All Outstanding
                                </button>
                            </div>
                            <?php
                        else: ?>
                            <div
                                class="alert alert-success mb-4 d-flex align-items-center shadow-sm border-start border-4 border-success">
                                <i class="fas fa-check-circle fa-lg me-2"></i>
                                <div>
                                    <strong>Fully Settled!</strong> All your current fees have been paid. Thank you!
                                </div>
                            </div>
                            <?php
                        endif; ?>

                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle">
                                <thead class="table-primary">
                                    <tr>
                                        <th width="30%">FEE COMPONENT</th>
                                        <th width="15%">ALLOCATED</th>
                                        <th width="15%">PAID</th>
                                        <th width="15%">WAIVER</th>
                                        <th width="10%">PENDING</th>
                                        <th width="15%">ACTION</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($detailed_allocations as $key => $alloc): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($alloc['label'] ?? ''); ?></strong>
                                                <br><small class="text-muted"><?php echo $alloc['category']; ?></small>
                                            </td>
                                            <td>₹<?php echo formatIndianCurrency($alloc['gross_amount']); ?></td>
                                            <td class="text-success">
                                                ₹<?php echo formatIndianCurrency($alloc['paid_amount']); ?></td>
                                            <td class="text-info">
                                                <?php echo $alloc['waived_amount'] > 0 ? '₹' . formatIndianCurrency($alloc['waived_amount']) : '-'; ?>
                                            </td>
                                            <td>
                                                <?php if ($alloc['pending_amount'] > 0): ?>
                                                    <span
                                                        class="text-danger fw-bold">₹<?php echo formatIndianCurrency($alloc['pending_amount']); ?></span>
                                                    <?php
                                                else: ?>
                                                    <span class="badge bg-success">PAID</span>
                                                    <?php
                                                endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($alloc['paid_amount'] > 0): ?>
                                                    <button type="button"
                                                        onclick="downloadReceipt('<?php echo $alloc['receipt_no']; ?>', '<?php echo $key; ?>')"
                                                        class="btn btn-outline-info btn-sm">
                                                        <i class="fas fa-download"></i> Receipt
                                                    </button>
                                                    <?php
                                                elseif ($alloc['pending_amount'] > 0): ?>
                                                    <button type="button" onclick="payFee('<?php echo $key; ?>')"
                                                        class="btn btn-warning btn-sm">
                                                        <i class="fas fa-credit-card"></i> Pay Now
                                                    </button>
                                                    <?php
                                                endif; ?>
                                            </td>
                                        </tr>
                                        <?php
                                    endforeach; ?>
                                </tbody>
                                <tfoot class="table-dark">
                                    <tr>
                                        <td><strong>Grand Total</strong></td>
                                        <td><strong>₹<?php echo formatIndianCurrency($fee_summary['total_allocated']); ?></strong>
                                        </td>
                                        <td><strong>₹<?php echo formatIndianCurrency($fee_summary['total_paid']); ?></strong>
                                        </td>
                                        <td><strong>₹<?php echo formatIndianCurrency($fee_summary['total_waiver']); ?></strong>
                                        </td>
                                        <td><strong>₹<?php echo formatIndianCurrency($fee_summary['total_pending']); ?></strong>
                                        </td>
                                        <td>-</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <div class="mt-3 text-end">
                            <a href="student-ledger.php" class="btn btn-link">
                                <i class="fas fa-book-open"></i> View Detailed Transaction History
                            </a>
                        </div>
                    </div>
                </div>
                <?php
            else: ?>
                <div class="card glass-card text-center p-5">
                    <i class="fas fa-receipt fa-4x text-muted mb-3"></i>
                    <h4>No Fee Structure Found</h4>
                    <p class="text-muted">Your fee structure has not been allocated yet. Please contact the admissions
                        department.</p>
                </div>
                <?php
            endif; ?>
        </div>
    </div>
</div>

<?php
include '../../include/footer.php'; ?>

<script>
    const user_id = '<?php echo $user_id; ?>';
    
    document.addEventListener('DOMContentLoaded', function () {
        const ctx = document.getElementById('performanceChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Test 1', 'Test 2', 'Test 3', 'Test 4', 'Test 5'],
                    datasets: [{
                        label: 'Score (%)',
                        data: [65, 75, 80, 70, 85],
                        backgroundColor: 'rgba(54, 162, 235, 0.5)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100
                        }
                    }
                }
            });
        }
    });

    // Fee payment functions (same as my-fees.php)
    function downloadReceipt(receiptNo, feeComponent) {
        if (typeof generateSecurePDF === 'function') {
            generateSecurePDF('<?php echo PORTAL_URL; ?>/modules/payments/receipt-print-pdf.php', {
                receipt_no: receiptNo,
                fee_component: feeComponent,
                student_id: user_id
            });
        } else {
            // Fallback to the global utility if it's somehow available but generateSecurePDF isn't (unlikely)
            window.location.href = '<?php echo PORTAL_URL; ?>/modules/payments/receipt-print-pdf.php?receipt_no=' + receiptNo + '&fee_component=' + feeComponent + '&student_id=' + user_id;
        }
    }

    function payFee(component, installmentId = null) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '<?php echo PORTAL_URL; ?>/modules/student-portal/pending-fee-payment.php';

        const componentInput = document.createElement('input');
        componentInput.type = 'hidden';
        componentInput.name = 'component';
        componentInput.value = component;
        form.appendChild(componentInput);

        if (installmentId) {
            const installmentInput = document.createElement('input');
            installmentInput.type = 'hidden';
            installmentInput.name = 'installment_id';
            installmentInput.value = installmentId;
            form.appendChild(installmentInput);
        }

        document.body.appendChild(form);
        form.submit();
    }

    function payAllPendingFees() {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '<?php echo PORTAL_URL; ?>/modules/student-portal/pay-all-pending-fees.php';
        document.body.appendChild(form);
        form.submit();
    }
</script>