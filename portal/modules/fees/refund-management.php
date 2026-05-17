<?php
header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once __DIR__ . '/../../common/api_client.php';
require_once PORTAL_GLOBALVARIABLE;
require_once PAGINATION_FILE; 
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/format_helper.php';

// Check admin access
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_COUNSELLOR) && !hasRole(ROLE_ACCOUNTANT)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// Handle POST filters/pagination and store in session
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Start with existing filters or defaults
    $currentFilters = $_SESSION['refund_management_filters'] ?? [
        'status' => 'all',
        'search' => '',
        'per_page' => 15,
        'page' => 1
    ];

    if (isset($_POST['page'])) {
        // Pagination request: Update page and per_page, keep other filters
        $currentFilters['page'] = $_POST['page'];
        if (isset($_POST['per_page'])) {
            $currentFilters['per_page'] = $_POST['per_page'];
        }
    } else {
        // Filter request: Update filters and reset page
        $currentFilters['status'] = $_POST['status'] ?? 'all';
        $currentFilters['search'] = $_POST['search'] ?? '';
        
        // Check for per_page update (though usually not in filter form)
        if (isset($_POST['per_page'])) {
            $currentFilters['per_page'] = $_POST['per_page'];
        }
        $currentFilters['page'] = 1;
    }

    $_SESSION['refund_management_filters'] = $currentFilters;

    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Get filters from session
$filters = $_SESSION['refund_management_filters'] ?? [
    'status' => 'all',
    'search' => '',
    'per_page' => 15,
    'page' => 1
];

$requestParams = $filters; // Use session filters for API request
$api = new APIClient();
$response = $api->get('fees/refund-management', $requestParams);

if ($response && isset($response['success']) && $response['success']) {
    $refund_requests = $response['data']['refund_requests'] ?? [];
    $stats = $response['data']['stats'] ?? [];
    $filter_status = $response['data']['applied_filters']['status'] ?? 'all';
    $search = $response['data']['applied_filters']['search'] ?? '';

    // Pagination
    $pagination = $response['data']['pagination'] ?? [];
    $page = $pagination['current_page'] ?? 1;
    $perPage = $pagination['per_page'] ?? 25;
    $totalRecords = $pagination['total_records'] ?? ($response['data']['total'] ?? count($refund_requests));
    $totalPages = $pagination['total_pages'] ?? 1;

} else {
    $refund_requests = [];
    $stats = ['pending' => 0, 'under_review' => 0, 'approved' => 0, 'completed' => 0, 'total_refunded' => 0];
    $filter_status = $_POST['status'] ?? 'all';
    $search = $_POST['search'] ?? '';

    $page = 1;
    $perPage = 25;
    $totalRecords = 0;
    $totalPages = 1;

    set_flash_message('error', $response['error'] ?? 'Failed to load refund management data');
}

$page_title = "Refund Management";

if (!function_exists('getStatusBadge')) {
    function getStatusBadge($status)
    {
        $badges = [
            'pending' => '<span class="badge bg-warning"><i class="bi bi-clock"></i> Pending</span>',
            'under_review' => '<span class="badge bg-info"><i class="bi bi-search"></i> Under Review</span>',
            'approved' => '<span class="badge bg-success"><i class="bi bi-check"></i> Approved</span>',
            'rejected' => '<span class="badge bg-danger"><i class="bi bi-x"></i> Rejected</span>',
            'processing' => '<span class="badge bg-primary"><i class="bi bi-hourglass-split"></i> Processing</span>',
            'completed' => '<span class="badge bg-success"><i class="bi bi-check-circle"></i> Completed</span>',
            'failed' => '<span class="badge bg-danger"><i class="bi bi-exclamation-triangle"></i> Failed</span>'
        ];
        return $badges[$status] ?? '<span class="badge bg-secondary">' . ucfirst($status) . '</span>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Gyan Manjari</title>
    <link href="<?php echo BASE_URL; ?>/assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/admin-style.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo PORTAL_URL; ?>/assets/css/modules/fees/refund-management.css">
</head>

<body>
    <?php
    include '../include/admin-header.php'; ?>

    <div class="container-fluid mt-4">
        

        <!-- Alert Container -->
        <div id="alertContainer"></div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stat-card priority-high">
                    <div class="card-body">
                        <h6 class="text-muted">Pending Review</h6>
                        <div class="stat-number text-danger"><?php
                                                                echo $stats['pending'] ?? 0; ?></div>
                        <small>Requires immediate attention</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card priority-medium">
                    <div class="card-body">
                        <h6 class="text-muted">Under Review</h6>
                        <div class="stat-number text-warning"><?php
                                                                echo $stats['under_review'] ?? 0; ?></div>
                        <small>Being reviewed</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card priority-medium">
                    <div class="card-body">
                        <h6 class="text-muted">Approved</h6>
                        <div class="stat-number text-info"><?php
                                                            echo $stats['approved'] ?? 0; ?></div>
                        <small>Ready to process</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card priority-low">
                    <div class="card-body">
                        <h6 class="text-muted">Completed</h6>
                        <div class="stat-number text-success"><?php
                                                                echo $stats['completed'] ?? 0; ?></div>
                        <small>₹<?php
                                echo formatIndianCurrency($stats['total_refunded'] ?? 0); ?> refunded</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Status Filter</label>
                        <select name="status" class="form-select" onchange="this.form.submit()">
                            <option value="all" <?php
                                                echo $filter_status === 'all' ? 'selected' : ''; ?>>All Requests</option>
                            <option value="pending" <?php
                                                    echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="under_review" <?php
                                                            echo $filter_status === 'under_review' ? 'selected' : ''; ?>>Under Review</option>
                            <option value="approved" <?php
                                                        echo $filter_status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="processing" <?php
                                                        echo $filter_status === 'processing' ? 'selected' : ''; ?>>Processing</option>
                            <option value="completed" <?php
                                                        echo $filter_status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="rejected" <?php
                                                        echo $filter_status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Search by request number, student name, or receipt..." value="<?php
                                                                                                                                                            echo htmlspecialchars($search ?? ''); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search"></i> Search
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Refund Requests Table -->
        <div class="card">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-table"></i> Refund Requests (<?php echo $totalRecords; ?>)</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="refundTable" class="table table-hover table-bordered">
                        <thead class="table-dark">
                            <tr>
                                <th>Request #</th>
                                <th>Student</th>
                                <th>Receipt</th>
                                <th>Amount</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Days Pending</th>
                                <th>Requested On</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($refund_requests)): ?>
                                <tr>
                                    <td colspan="9" class="text-center py-4 text-muted">No refund requests found.</td>
                                </tr>
                            <?php else: ?>
                                <?php
                                foreach ($refund_requests as $request): ?>
                                    <tr class="<?php
                                                echo $request['days_pending'] > 3 ? 'table-warning' : ''; ?>">
                                        <td>
                                            <strong><?php
                                                    echo htmlspecialchars($request['request_number'] ?? ''); ?></strong>
                                            <?php
                                            if ($request['days_pending'] > 3): ?>
                                                <br><small class="text-danger"><i class="bi bi-exclamation-triangle"></i> Urgent</small>
                                            <?php
                                            endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            echo htmlspecialchars($request['full_name'] ?? ''); ?><br>
                                            <small class="text-muted"><?php
                                                                        echo htmlspecialchars($request['student_mobile'] ?? ''); ?></small>
                                        </td>
                                        <td><?php
                                            echo htmlspecialchars($request['receipt_number'] ?? ''); ?></td>
                                        <td>
                                            <strong>₹<?php
                                                        echo formatIndianCurrency($request['refund_amount']); ?></strong><br>
                                            <small class="text-muted">of ₹<?php
                                                                            echo formatIndianCurrency($request['payment_amount']); ?></small>
                                        </td>
                                        <td><span class="badge bg-secondary"><?php
                                                                                echo ucfirst($request['refund_type']); ?></span></td>
                                        <td><?php
                                            echo getStatusBadge($request['request_status']); ?></td>
                                        <td>
                                            <strong><?php
                                                    echo $request['days_pending']; ?></strong> days
                                        </td>
                                        <td><?php
                                            echo date('d-M-Y', strtotime($request['requested_at'])); ?></td>
                                        <td class="action-buttons">
                                            <button class="btn btn-sm btn-info" onclick="viewDetails(<?php
                                                                                                        echo $request['id']; ?>)" title="View Details">
                                                <i class="bi bi-eye"></i>
                                            </button>

                                            <?php
                                            if ($request['request_status'] === 'pending'): ?>
                                                <button class="btn btn-sm btn-warning" onclick="markUnderReview(<?php
                                                                                                                echo $request['id']; ?>)" title="Mark Under Review">
                                                    <i class="bi bi-clock-history"></i>
                                                </button>
                                                <button class="btn btn-sm btn-success" onclick="approveRequest(<?php
                                                                                                                echo $request['id']; ?>)" title="Approve">
                                                    <i class="bi bi-check-circle"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger" onclick="rejectRequest(<?php
                                                                                                                echo $request['id']; ?>)" title="Reject">
                                                    <i class="bi bi-x-circle"></i>
                                                </button>
                                            <?php
                                            elseif ($request['request_status'] === 'approved'): ?>
                                                <button class="btn btn-sm btn-primary" onclick="processRefund(<?php
                                                                                                                echo $request['id']; ?>)" title="Process Refund">
                                                    <i class="bi bi-currency-rupee"></i> Process
                                                </button>
                                            <?php
                                            endif; ?>
                                        </td>
                                    </tr>
                                <?php
                                endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Controls -->
                <?php if ($totalRecords > 0): ?>
                    <div class="d-flex justify-content-between align-items-center mt-3 border-top pt-3">
                        <div class="text-muted">
                            <?php echo getPaginationInfo($page, $perPage, $totalRecords); ?>
                        </div>
                        <?php if ($totalPages > 1): ?>
                            <?php
                            echo renderPaginationPost($page, $totalPages);
                            ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <!-- View Details Modal -->
    <div class="modal fade" id="viewDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="bi bi-eye"></i> Refund Request Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="refundDetailsContent">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Approve Modal -->
    <div class="modal fade" id="approveModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="bi bi-check-circle"></i> Approve Refund Request</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="approveForm">
                    <div class="modal-body">
                        <input type="hidden" id="approve_request_id">
                        <div class="alert alert-success">
                            <i class="bi bi-info-circle"></i> This will approve the refund request and make it ready for processing.
                        </div>
                        <div class="mb-3">
                            <label for="approval_remarks" class="form-label">Approval Remarks (Optional)</label>
                            <textarea class="form-control" id="approval_remarks" rows="3"
                                placeholder="Add any remarks or instructions..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-circle"></i> Approve Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reject Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-x-circle"></i> Reject Refund Request</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="rejectForm">
                    <div class="modal-body">
                        <input type="hidden" id="reject_request_id">
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle"></i> This action will reject the refund request.
                        </div>
                        <div class="mb-3">
                            <label for="rejection_reason" class="form-label">Rejection Reason <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="rejection_reason" rows="4" required
                                placeholder="Please provide a detailed reason for rejection..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-x-circle"></i> Reject Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Process Refund Modal -->
    <div class="modal fade" id="processModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-currency-rupee"></i> Process Refund via EaseBuzz</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="processModalContent">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php
    include '../include/admin-footer.php'; ?>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="<?php echo BASE_URL; ?>/assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            // Move modals to body to prevent z-index issues
            $('#viewDetailsModal').appendTo("body");
            $('#approveModal').appendTo("body");
            $('#rejectModal').appendTo("body");
            $('#processModal').appendTo("body");
        });

        async function viewDetails(requestId) {
            const modal = new bootstrap.Modal(document.getElementById('viewDetailsModal'));
            modal.show();

            try {
                const response = await fetch(`api/get-refund-details-admin.php?id=${requestId}`);
                const result = await response.json();

                if (result.success) {
                    document.getElementById('refundDetailsContent').innerHTML = result.html;
                } else {
                    document.getElementById('refundDetailsContent').innerHTML =
                        '<div class="alert alert-danger">Error loading details</div>';
                }
            } catch (error) {
                document.getElementById('refundDetailsContent').innerHTML =
                    '<div class="alert alert-danger">Error loading details</div>';
            }
        }

        async function markUnderReview(requestId) {
            if (!confirm('Mark this request as under review?')) return;

            try {
                const response = await fetch('api/update-refund-status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        request_id: requestId,
                        status: 'under_review'
                    })
                });

                const result = await response.json();
                showAlert(result.message, result.success ? 'success' : 'danger');
                if (result.success) setTimeout(() => location.reload(), 1500);
            } catch (error) {
                showAlert('Error updating status', 'danger');
            }
        }

        function approveRequest(requestId) {
            document.getElementById('approve_request_id').value = requestId;
            new bootstrap.Modal(document.getElementById('approveModal')).show();
        }

        function rejectRequest(requestId) {
            document.getElementById('reject_request_id').value = requestId;
            new bootstrap.Modal(document.getElementById('rejectModal')).show();
        }

        document.getElementById('approveForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const requestId = document.getElementById('approve_request_id').value;
            const remarks = document.getElementById('approval_remarks').value;

            try {
                const response = await fetch('api/approve-refund.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        request_id: requestId,
                        remarks
                    })
                });

                const result = await response.json();
                showAlert(result.message, result.success ? 'success' : 'danger');
                if (result.success) {
                    bootstrap.Modal.getInstance(document.getElementById('approveModal')).hide();
                    setTimeout(() => location.reload(), 1500);
                }
            } catch (error) {
                showAlert('Error approving request', 'danger');
            }
        });

        document.getElementById('rejectForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const requestId = document.getElementById('reject_request_id').value;
            const reason = document.getElementById('rejection_reason').value;

            if (reason.length < 20) {
                showAlert('Rejection reason must be at least 20 characters', 'warning');
                return;
            }

            try {
                const response = await fetch('api/reject-refund.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        request_id: requestId,
                        reason
                    })
                });

                const result = await response.json();
                showAlert(result.message, result.success ? 'success' : 'danger');
                if (result.success) {
                    bootstrap.Modal.getInstance(document.getElementById('rejectModal')).hide();
                    setTimeout(() => location.reload(), 1500);
                }
            } catch (error) {
                showAlert('Error rejecting request', 'danger');
            }
        });

        async function processRefund(requestId) {
            const modal = new bootstrap.Modal(document.getElementById('processModal'));
            modal.show();

            try {
                const response = await fetch(`api/process-refund-easebuzz.php?request_id=${requestId}`);
                const result = await response.json();

                if (result.success) {
                    document.getElementById('processModalContent').innerHTML = result.html;
                } else {
                    document.getElementById('processModalContent').innerHTML =
                        '<div class="alert alert-danger">' + result.message + '</div>';
                }
            } catch (error) {
                document.getElementById('processModalContent').innerHTML =
                    '<div class="alert alert-danger">Error loading refund processing</div>';
            }
        }

        function showAlert(message, type) {
            const alert = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            document.getElementById('alertContainer').innerHTML = alert;
            window.scrollTo(0, 0);
        }

        function exportData() {
            window.location.href = 'api/export-refunds.php?' + new URLSearchParams({
                status: '<?php
                            echo $filter_status; ?>',
                search: '<?php
                            echo $search; ?>'
            });
        }
    </script>
</body>

</html>

