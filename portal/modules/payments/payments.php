<?php
header('Content-Type: text/html; charset=utf-8');
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once __DIR__ . '/../../common/api_client.php';
require_once PORTAL_GLOBALVARIABLE;
require_once PAGINATION_FILE;
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/format_helper.php';

// Handle POST filters and store in session
// Handle POST filters and store in session
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['clear_filters'])) {
        unset($_SESSION['payments_filters']);
    } else {
        // Start with existing filters or defaults
        $currentFilters = $_SESSION['payments_filters'] ?? [
            'search' => '',
            'status' => '',
            'payment_mode' => '',
            'from_date' => '',
            'to_date' => '',
            'per_page' => 25,
            'page' => 1
        ];

        if (isset($_POST['page'])) {
            // Pagination request: Only update page (and per_page if present)
            $currentFilters['page'] = $_POST['page'];
            if (isset($_POST['per_page'])) {
                $currentFilters['per_page'] = $_POST['per_page'];
            }
        } else {
            // Filter request: Update filters and reset page
            $currentFilters['search'] = $_POST['search'] ?? '';
            $currentFilters['status'] = $_POST['status'] ?? '';
            $currentFilters['payment_mode'] = $_POST['payment_mode'] ?? '';
            $currentFilters['from_date'] = $_POST['from_date'] ?? '';
            $currentFilters['to_date'] = $_POST['to_date'] ?? '';
            if (isset($_POST['per_page'])) {
                $currentFilters['per_page'] = $_POST['per_page'];
            }
            $currentFilters['page'] = 1;
        }

        $_SESSION['payments_filters'] = $currentFilters;
    }

    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Get filters from session
$filters = $_SESSION['payments_filters'] ?? [
    'search' => '',
    'status' => '',
    'payment_mode' => '',
    'from_date' => '',
    'to_date' => '',
    'per_page' => 25,
    'page' => 1
];

// Fetch payments data from API with pagination
$api = new APIClient();
$requestParams = $filters;

$response = $api->get('payments/list', $requestParams);

if ($response && isset($response['success']) && $response['success']) {
    $payments = $response['data']['payments'] ?? [];
    $pagination = $response['data']['pagination'] ?? [];

    // Sync local variables with response or session
    $page = $pagination['current_page'] ?? $filters['page'];
    $perPage = $pagination['per_page'] ?? $filters['per_page'];
    $totalRecords = $pagination['total_records'] ?? count($payments);
    $totalPages = $pagination['total_pages'] ?? 1;

    $stats = $response['data']['stats'] ?? ['total_payments' => 0, 'total_collected' => 0, 'total_pending' => 0];

    // Use filters from session/local variables for display
    $search = $filters['search'];
    $status = $filters['status'];
    $payment_mode = $filters['payment_mode'];
    $from_date = $filters['from_date'];
    $to_date = $filters['to_date'];
} else {
    $payments = [];
    $page = 1;
    $perPage = 25;
    $totalRecords = 0;
    $totalPages = 1;
    $stats = ['total_payments' => 0, 'total_collected' => 0, 'total_pending' => 0];
    $search = $status = $payment_mode = $from_date = $to_date = '';

    // Only show error if it's not a fresh load (optional, but good for UX)
    if (!empty($response['error'])) {
        set_flash_message('error', $response['error'] ?? 'Failed to load payments');
    }
}

$page_title = "Payment Records";
include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>



<div class="container-fluid">
    <?php
    if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i> <?php
            echo gca_safe_html($_SESSION['success']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert">&times;</button>
        </div>
        <?php
    endif; ?>

    <?php
    if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle"></i> <?php
            echo gca_safe_html($_SESSION['error']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert">&times;</button>
        </div>
        <?php
    endif; ?>

    <!-- Statistics Cards -->
    <div class="row g-4 mb-5">
        <div class="col-xl-4 col-md-6">
            <div class="glass-card">
                <div class="stat-card">
                    <div class="stat-top">
                        <div>
                            <div class="stat-value"><?php echo formatIndianCurrency($stats['total_payments'], false); ?>
                            </div>
                            <div class="stat-label">Total Payments</div>
                        </div>
                        <div class="stat-icon bg-icon-info">
                            <i class="fas fa-receipt"></i>
                        </div>
                    </div>
                    <div class="stat-link text-info">
                        Overall transaction count
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6">
            <div class="glass-card">
                <div class="stat-card">
                    <div class="stat-top">
                        <div>
                            <div class="stat-value">
                                ₹<?php echo formatIndianCurrency($stats['total_collected'] ?? 0); ?></div>
                            <div class="stat-label">Total Collected</div>
                        </div>
                        <div class="stat-icon bg-icon-success">
                            <i class="fas fa-wallet"></i>
                        </div>
                    </div>
                    <div class="stat-link text-success">
                        Confirmed revenue
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6">
            <div class="glass-card">
                <div class="stat-card">
                    <div class="stat-top">
                        <div>
                            <div class="stat-value">
                                ₹<?php echo formatIndianCurrency($stats['total_pending'] ?? 0); ?></div>
                            <div class="stat-label">Total Pending</div>
                        </div>
                        <div class="stat-icon bg-icon-warning">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <div class="stat-link text-warning">
                        Awaiting settlement
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters and Payments Table -->
    <div class="card card-enhanced">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list me-2"></i> Payment Records
            </h3>
            <div class="card-tools">
                <a href="add-payment.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus me-1"></i> Add Payment
                </a>
            </div>
        </div>
        <div class="card-body">
            <!-- Filters -->
            <div class="form-section mb-4 p-3 bg-light rounded-3 border">
                <div class="form-section-title mb-3 fw-bold text-dark border-bottom pb-2">
                    <i class="fas fa-filter text-primary me-2"></i> Filter Payments
                </div>
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Search</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i
                                        class="fas fa-search text-muted"></i></span>
                                <input type="text" name="search" class="form-control border-start-0"
                                    placeholder="Receipt or Student name..." value="<?php
                                    echo htmlspecialchars((string)($search ?? '')); ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold">Status</label>
                            <select name="status" class="form-select">
                                <option value="">All Statuses</option>
                                <option value="paid" <?php echo $status === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending
                                </option>
                                <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>
                                    Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold">Payment Mode</label>
                            <select name="payment_mode" class="form-select">
                                <option value="">All Modes</option>
                                <option value="cash" <?php echo $payment_mode === 'cash' ? 'selected' : ''; ?>>Cash
                                </option>
                                <option value="online" <?php echo $payment_mode === 'online' ? 'selected' : ''; ?>>Online
                                </option>
                                <option value="upi" <?php echo $payment_mode === 'upi' ? 'selected' : ''; ?>>UPI</option>
                                <option value="cheque" <?php echo $payment_mode === 'cheque' ? 'selected' : ''; ?>>Cheque
                                </option>
                                <option value="card" <?php echo $payment_mode === 'card' ? 'selected' : ''; ?>>Card
                                </option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold">From Date</label>
                            <input type="date" name="from_date" class="form-control" value="<?php echo $from_date; ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold">To Date</label>
                            <input type="date" name="to_date" class="form-control" value="<?php echo $to_date; ?>">
                        </div>
                        <div class="col-md-1 d-flex align-items-end gap-2">
                            <button type="submit" class="btn btn-primary w-100" title="Search">
                                <i class="fas fa-search"></i>
                            </button>
                            <button type="submit" name="clear_filters" value="1" class="btn btn-outline-secondary w-100"
                                title="Clear">
                                <i class="fas fa-undo"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Payments Table -->
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="border-0">Receipt No</th>
                            <th class="border-0">Date</th>
                            <th class="border-0">Student Name</th>
                            <th class="border-0 text-end">Amount</th>
                            <th class="border-0 text-center">Mode</th>
                            <th class="border-0">Type</th>
                            <th class="border-0 text-center">Status</th>
                            <th class="border-0 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (empty($payments)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <div class="empty-state-icon d-inline-flex mb-2"
                                        style="width:60px;height:60px;font-size:1.5rem;">
                                        <i class="fas fa-receipt"></i>
                                    </div>
                                    <div class="text-muted">No payments found matching your criteria</div>
                                </td>
                            </tr>
                            <?php
                        else: ?>
                            <?php
                            foreach ($payments as $payment): ?>
                                <tr>
                                    <td><code><?php
                                    echo htmlspecialchars((string)($payment['receipt_no'] ?? '')); ?></code></td>
                                    <td><?php
                                    echo date('d-M-Y', strtotime($payment['payment_date'])); ?></td>
                                    <td><strong><?php
                                    $full_name = trim(
                                        ($payment['surname'] ?? '') . ' ' .
                                        ($payment['student_name'] ?? '') . ' ' .
                                        ($payment['fathers_name'] ?? '')
                                    );
                                    echo htmlspecialchars((string)($full_name ?: ($payment['student_name'] ?? '')));
                                    ?></strong></td>
                                    <td class="text-end font-monospace fw-bold text-success">₹<?php
                                    echo formatIndianCurrency($payment['amount']); ?>
                                    </td>
                                    <td class="text-center">
                                        <?php
                                        $mode_icon = [
                                            'cash' => 'fa-money-bill-wave text-success',
                                            'online' => 'fa-globe text-primary',
                                            'upi' => 'fa-mobile-alt text-info',
                                            'cheque' => 'fa-money-check text-warning',
                                            'card' => 'fa-credit-card text-danger'
                                        ];
                                        $mode = strtolower($payment['payment_mode']);
                                        ?>
                                        <span
                                            class="d-flex align-items-center justify-content-center gap-1 border rounded-pill px-2 py-1 small">
                                            <i class="fas <?php echo $mode_icon[$mode] ?? 'fa-wallet'; ?>"></i>
                                            <?php echo strtoupper($payment['payment_mode']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="text-muted small"><?php echo ucfirst($payment['payment_type']); ?></span>
                                    </td>
                                    <td class="text-center">
                                        <?php
                                        $badge_class = [
                                            'paid' => 'bg-success',
                                            'pending' => 'bg-warning text-dark',
                                            'cancelled' => 'bg-danger',
                                            'refunded' => 'bg-secondary'
                                        ];
                                        ?>
                                        <span class="badge rounded-pill <?php
                                        echo $badge_class[$payment['status']] ?? 'bg-secondary'; ?>">
                                            <?php
                                            echo ucfirst($payment['status']); ?>
                                        </span>
                                    </td>

                                    <td class="text-center">
                                        <div class="btn-group">
                                            <a href="javascript:void(0);"
                                                onclick="viewPaymentHistory(<?php echo $payment['student_id']; ?>)"
                                                class="btn btn-sm btn-outline-info rounded-pill" title="Payment History">
                                                <i class="fas fa-history me-1"></i> History
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php
                            endforeach; ?>
                            <?php
                        endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php 
                echo renderPaginationPost($page, $totalPages, 2, $perPage, [], $totalRecords, 'entries');
            ?>
        </div>
    </div>
</div>

<?php
include '../../include/footer.php'; ?>

<script>
    function viewPaymentHistory(studentId) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '../reports/financial/student-ledger.php';

        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'student_id';
        input.value = studentId;

        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
    }
</script>