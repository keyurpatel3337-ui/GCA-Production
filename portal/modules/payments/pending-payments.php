<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once __DIR__ . '/../../common/api_client.php';
require_once PORTAL_GLOBALVARIABLE;
require_once __DIR__ . '/../../../common/helpers/format_helper.php';


// Handle POST filters and store in session
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['clear_filters'])) {
        unset($_SESSION['pending_payments_filters']);
    } else {
        // Start with existing filters or defaults
        $currentFilters = $_SESSION['pending_payments_filters'] ?? [
            'search' => '',
            'sort_by' => 'pending_desc',
            'limit' => 20,
            'page' => 1
        ];

        if (isset($_POST['page'])) {
            // Pagination request: Only update page (and limit if present)
            $currentFilters['page'] = $_POST['page'];
            if (isset($_POST['limit'])) {
                $currentFilters['limit'] = $_POST['limit'];
            }
        } else {
            // Filter request: Update filters and reset page
            $currentFilters['search'] = $_POST['search'] ?? '';
            $currentFilters['sort_by'] = $_POST['sort_by'] ?? 'pending_desc';
            // Use current limit unless changed (though usually not in filter form)
            if (isset($_POST['limit'])) {
                $currentFilters['limit'] = $_POST['limit'];
            }
            $currentFilters['page'] = 1;
        }

        $_SESSION['pending_payments_filters'] = $currentFilters;
    }

    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Get filters from session
$filters = $_SESSION['pending_payments_filters'] ?? [
    'search' => '',
    'sort_by' => 'pending_desc',
    'limit' => 20,
    'page' => 1
];

// Fetch pending payments data from API
$api = new APIClient();
$request_params = $filters;

try {
    $response = $api->get('payments/pending', $request_params);

    if ($response && isset($response['success']) && $response['success']) {
        $pending_fees = $response['data']['pending_fees'] ?? [];
        $summary = $response['data']['summary'] ?? ['student_count' => 0, 'total_allocated' => 0, 'total_paid' => 0, 'total_pending' => 0];
        $pagination = $response['data']['pagination'] ?? [];

        // Use filters for display
        $search = $filters['search'];
        $sort_by = $filters['sort_by'];

        // Sync pagination info
        if (empty($pagination)) {
            $pagination = [
                'current_page' => $filters['page'],
                'limit' => $filters['limit'],
                'total_records' => count($pending_fees),
                'total_pages' => 1
            ];
        }
    } else {
        // Log API error response
        error_log("Pending Payments API Error: " . json_encode([
            'response' => $response,
            'error' => $response['error'] ?? 'Unknown error',
            'message' => $response['message'] ?? 'No message',
            'file' => __FILE__,
            'line' => __LINE__
        ]));

        $pending_fees = [];
        $summary = ['student_count' => 0, 'total_allocated' => 0, 'total_paid' => 0, 'total_pending' => 0];
        $pagination = [
            'current_page' => 1,
            'limit' => 20,
            'total_records' => 0,
            'total_pages' => 1
        ];
        $search = '';
        $sort_by = 'pending_desc';

        if (!empty($response['error'])) {
            set_flash_message('error', $response['error'] ?? 'Failed to load pending payments');
        }
    }
} catch (Exception $e) {
    // Log exception details
    error_log("Pending Payments Exception: " . json_encode([
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]));

    $pending_fees = [];
    $summary = ['student_count' => 0, 'total_allocated' => 0, 'total_paid' => 0, 'total_pending' => 0];
    $pagination = [
        'current_page' => 1,
        'limit' => 20,
        'total_records' => 0,
        'total_pages' => 1
    ];
    $search = '';
    $sort_by = 'pending_desc';
    set_flash_message('error', 'System error: Unable to fetch pending payments. Please contact administrator.');
}

$page_title = "Pending Payments";
include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
include '../../common/pagination.php';
?>



<div class="container-fluid">
    <!-- Summary Cards -->
    <div class="row">
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="small-box bg-warning">
                <div class="inner">
                    <h3>₹<?php
                    echo formatIndianCurrency($summary['total_scholarship'] ?? 0); ?></h3>
                    <p>Scholarship (Applied)</p>
                </div>
                <div class="icon">
                    <i class="fas fa-award"></i>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3>₹<?php
                    echo formatIndianCurrency($summary['total_allocated'] ?? 0); ?></h3>
                    <p>Total Allocated</p>
                </div>
                <div class="icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3>₹<?php
                    echo formatIndianCurrency($summary['total_paid'] ?? 0); ?></h3>
                    <p>Total Paid</p>
                </div>
                <div class="icon">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="small-box bg-danger">
                <div class="inner">
                    <h3>₹<?php
                    echo formatIndianCurrency($summary['total_pending'] ?? 0); ?></h3>
                    <p>Total Pending</p>
                </div>
                <div class="icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Pending Fee Records</h3>
        </div>
        <div class="card-body">
            <!-- Filters -->
            <form method="POST" class="mb-3">
                <div class="row">
                    <div class="col-md-5">
                        <input type="text" name="search" class="form-control" placeholder="Search by name or Aadhaar..."
                            value="<?php
                            echo htmlspecialchars($search ?? ''); ?>">
                    </div>
                    <div class="col-md-4">
                        <select name="sort_by" class="form-control">
                            <option value="pending_desc" <?php
                            echo $sort_by === 'pending_desc' ? 'selected' : ''; ?>>
                                Pending (High to Low)</option>
                            <option value="pending_asc" <?php
                            echo $sort_by === 'pending_asc' ? 'selected' : ''; ?>>
                                Pending (Low to High)</option>
                            <option value="due_date" <?php
                            echo $sort_by === 'due_date' ? 'selected' : ''; ?>>Due Date
                            </option>
                            <option value="name" <?php
                            echo $sort_by === 'name' ? 'selected' : ''; ?>>Name</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="fas fa-filter"></i> Apply
                        </button>
                        <button type="submit" name="clear_filters" value="1" class="btn btn-secondary flex-grow-1">
                            <i class="fas fa-times"></i> Clear
                        </button>
                    </div>
                </div>
            </form>

            <!-- Pending Payments Table -->
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>Mobile</th>
                            <th>Fee Type</th>
                            <th>Allocated</th>
                            <th>Paid</th>
                            <th>Pending</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (empty($pending_fees)): ?>
                            <tr>
                                <td colspan="9" class="text-center">No pending payments found</td>
                            </tr>
                            <?php
                        else: ?>
                            <?php
                            foreach ($pending_fees as $fee): ?>
                                <?php

                                $is_overdue = !empty($fee['due_date']) && strtotime($fee['due_date']) < strtotime('today');
                                ?>
                                <tr class="<?php
                                echo $is_overdue ? 'table-danger' : ''; ?>">
                                    <td><strong><?php
                                    echo htmlspecialchars($fee['name'] ?? ''); ?></strong></td>
                                    <td><?php
                                    echo htmlspecialchars($fee['mobile'] ?? 'N/A'); ?></td>
                                    <td><?php
                                    echo htmlspecialchars($fee['fee_type'] ?? 'N/A'); ?></td>
                                    <td>₹<?php
                                    echo formatIndianCurrency($fee['allocated_amount']); ?></td>
                                    <td>₹<?php
                                    echo formatIndianCurrency($fee['paid_amount']); ?></td>
                                    <td><strong class="text-danger">₹<?php
                                    echo formatIndianCurrency($fee['pending_amount']); ?></strong>
                                    </td>
                                    <td>
                                        <?php
                                        echo !empty($fee['due_date']) ? date('d-M-Y', strtotime($fee['due_date'])) : 'N/A'; ?>
                                        <?php
                                        if ($is_overdue): ?>
                                            <span class="badge bg-danger">Overdue</span>
                                            <?php
                                        endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        if ($fee['status'] === 'pending'): ?>
                                            <span class="badge bg-danger">Pending</span>
                                            <?php
                                        elseif ($fee['status'] === 'partial'): ?>
                                            <span class="badge bg-warning text-dark">Partial</span>
                                            <?php
                                        endif; ?>
                                    </td>
                                    <td>
                                        <form method="POST" action="add-payment.php" class="css-pending-payments-3df0a3">
                                            <input type="hidden" name="student_id"
                                                value="<?php echo $fee['student_id'] ?? ''; ?>">
                                            <input type="hidden" name="fee_id" value="<?php echo $fee['id'] ?? ''; ?>">
                                            <button type="submit" class="btn btn-sm btn-success" title="Add Payment">
                                                <i class="fas fa-plus"></i> Pay
                                            </button>
                                        </form>
                                        <form method="POST" action="../reports/financial/student-ledger.php" class="css-pending-payments-3df0a3">
                                            <input type="hidden" name="student_id"
                                                value="<?php echo $fee['student_id'] ?? ''; ?>">
                                            <button type="submit" class="btn btn-sm btn-info" title="Payment History">
                                                <i class="fas fa-history"></i>
                                            </button>
                                        </form>
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
            <?php if (!empty($pagination) && $pagination['total_pages'] > 1): ?>
                <div class="row mt-3">
                    <div class="col-sm-12 col-md-5">
                        <div class="dataTables_info" role="status" aria-live="polite">
                            <?php echo getPaginationInfo($pagination['current_page'], $pagination['limit'], $pagination['total_records']); ?>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-7">
                        <div class="dataTables_paginate paging_simple_numbers">
                            <?php
                            echo renderPaginationPost($pagination['current_page'], $pagination['total_pages']);
                            ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
include '../../include/footer.php'; ?>