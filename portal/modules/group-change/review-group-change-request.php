<?php
require_once __DIR__ . '/../../session_config.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/index.php");
    exit;
}

// Check for principal role
require_once PORTAL_GLOBALVARIABLE;
require_once OPERATION_FILE;

if (!hasRole(ROLE_PRINCIPLE)) {
    header("Location: ../dashboard/principle_dashboard.php");
    exit;
}

require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once HELPER_ERROR_LOGGER;
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/format_helper.php';
$request_id = intval($_POST['id'] ?? 0);

if (!$request_id) {
    header("Location: pending-requests.php");
    exit;
}

$page_title = "Review Group Change Request";
$page_breadcrumb = "Request -";
$current_page = 'group-change-requests';

try {
    // Fetch request details - simplified to match actual table structure
    $stmt = $conn->prepare("SELECT 
            gcr.*,
            s.student_name,
            s.mob as student_contact,
            s.id as student_reg_id,
            cg.group_name as current_group_name,
            ng.group_name as requested_group_name,
            s.scholarship_amount,
            s.additional_scholarship_amount,
            s.course_id,
            s.medium_id
          FROM tbl_group_change_requests gcr
          LEFT JOIN tbl_gm_std_registration s ON gcr.student_id = s.id
          LEFT JOIN tbl_group cg ON gcr.current_group_id = cg.id
          LEFT JOIN tbl_group ng ON gcr.requested_group_id = ng.id
          WHERE gcr.id = ?");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        die("Request not found!");
    }

    // Calculate scholarship and fees
    $total_scholarship = ($request['scholarship_amount'] ?? 0) + ($request['additional_scholarship_amount'] ?? 0);

    // Get current group fees
    $stmt = $conn->prepare("SELECT total_fees FROM tbl_fee_config 
                            WHERE course_id = ? AND medium_id = ? AND group_id = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$request['course_id'], $request['medium_id'], $request['current_group_id']]);
    $current_fee = $stmt->fetch(PDO::FETCH_ASSOC);
    $current_total_fees = ($current_fee['total_fees'] ?? 0) - $total_scholarship;

    // Get new group fees
    $stmt->execute([$request['course_id'], $request['medium_id'], $request['requested_group_id']]);
    $new_fee = $stmt->fetch(PDO::FETCH_ASSOC);
    $new_total_fees = ($new_fee['total_fees'] ?? 0) - $total_scholarship;

    // Get fees already paid
    $stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total_paid FROM tbl_payments WHERE student_id = ?");
    $stmt->execute([$request['student_id']]);
    $payment_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $fees_already_paid = $payment_data['total_paid'] ?? 0;

    // Calculate differences
    $fee_difference = $new_total_fees - $current_total_fees;
    $new_pending_amount = $new_total_fees - $fees_already_paid;

    // Fetch history
    $history_stmt = $conn->prepare("SELECT 
                                        h.*,
                                        'System' as action_by_name
                                    FROM tbl_group_change_history h
                                    WHERE h.request_id = ?
                                    ORDER BY h.created_at ASC");
    $history_stmt->execute([$request_id]);
    $history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logError("Review Request Error: " . $e->getMessage(), __FILE__, __LINE__, $e);
    die("An error occurred while loading the request. Please try again.");
}
?>
<?php include '../../include/header.php'; ?>
<?php include '../../include/navbar.php'; ?>
<?php include '../../include/sidebar.php'; ?>




<div class="container-fluid">
    <!-- Request Information -->
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h3 class="card-title mb-0"><i class="fas fa-info-circle me-2"></i>Request Information</h3>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-sm table-borderless">
                        <tr>
                            <td class="fw-bold text-muted" width="150">Request Number:</td>
                            <td><strong>REQ-<?php echo $request['id']; ?></strong></td>
                        </tr>
                        <tr>
                            <td class="fw-bold text-muted">Student Name:</td>
                            <td><?php echo htmlspecialchars($request['student_name'] ?? ''); ?></td>
                        </tr>
                        <tr>
                            <td class="fw-bold text-muted">Student ID:</td>
                            <td><?php echo $request['student_id']; ?></td>
                        </tr>
                        <tr>
                            <td class="fw-bold text-muted">Contact:</td>
                            <td><?php echo htmlspecialchars($request['student_contact'] ?? ''); ?></td>
                        </tr>
                        <tr>
                            <td class="fw-bold text-muted">Course/Medium:</td>
                            <td>Course ID: <?php echo $request['course_id']; ?> / Medium ID:
                                <?php echo $request['medium_id']; ?>
                            </td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-sm table-borderless">
                        <tr>
                            <td class="fw-bold text-muted" width="150">Request Date:</td>
                            <td><?php echo date('d-m-Y h:i A', strtotime($request['request_date'])); ?></td>
                        </tr>
                        <tr>
                            <td class="fw-bold text-muted">Current Group:</td>
                            <td><?php echo htmlspecialchars($request['current_group_name'] ?? ''); ?></td>
                        </tr>
                        <tr>
                            <td class="fw-bold text-muted">Requested Group:</td>
                            <td><strong
                                    class="text-primary"><?php echo htmlspecialchars($request['requested_group_name'] ?? ''); ?></strong>
                            </td>
                        </tr>
                        <tr>
                            <td class="fw-bold text-muted">Status:</td>
                            <td>
                                <?php
                                $status_badges = [
                                    'pending' => 'bg-warning text-dark',
                                    'under_review' => 'bg-info',
                                    'approved' => 'bg-success',
                                    'rejected' => 'bg-danger',
                                    'cancelled' => 'bg-secondary'
                                ];
                                $status_badge = $status_badges[$request['status']] ?? 'bg-secondary';
                                ?>
                                <span class="badge <?php echo $status_badge; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $request['status'])); ?>
                                </span>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Reason for Change -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title mb-0"><i class="fas fa-comment-alt me-2"></i>Reason for Group Change</h3>
        </div>
        <div class="card-body">
            <div class="alert alert-light border mb-0">
                <?php echo nl2br(htmlspecialchars($request['reason'] ?? '')); ?>
            </div>
        </div>
    </div>

    <!-- Fee Comparison -->
    <div class="card">
        <div class="card-header bg-info text-white">
            <h3 class="card-title mb-0"><i class="fas fa-calculator me-2"></i>Fee Comparison</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="table-dark">
                        <tr>
                            <th>Description</th>
                            <th class="text-end" width="200">Amount (?)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Current Group Total Fees</strong></td>
                            <td class="text-end">?<?php echo formatIndianCurrency($current_total_fees, false); ?></td>
                        </tr>
                        <tr>
                            <td><strong>New Group Total Fees</strong></td>
                            <td class="text-end">?<?php echo formatIndianCurrency($new_total_fees, false); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Fees Already Paid</strong></td>
                            <td class="text-end">?<?php echo formatIndianCurrency($fees_already_paid, false); ?></td>
                        </tr>
                        <?php
                        $fee_diff = $fee_difference;
                        if ($fee_diff > 0) {
                            $fee_class = 'table-danger';
                            $fee_text = '+?' . formatIndianCurrency($fee_diff, false) . ' (Increase)';
                        } elseif ($fee_diff < 0) {
                            $fee_class = 'table-success';
                            $fee_text = '-?' . formatIndianCurrency(abs($fee_diff), false) . ' (Decrease)';
                        } else {
                            $fee_class = 'table-info';
                            $fee_text = '?0.00 (No Change)';
                        }
                        ?>
                        <tr class="<?php echo $fee_class; ?>">
                            <td><strong>Fee Difference</strong></td>
                            <td class="text-end"><strong><?php echo $fee_text; ?></strong></td>
                        </tr>
                        <tr>
                            <td><strong>Current Pending Amount</strong></td>
                            <td class="text-end">
                                ?<?php echo formatIndianCurrency($request['current_pending_amount'] ?? 0, false); ?>
                            </td>
                        </tr>
                        <tr class="table-warning">
                            <td><strong>New Pending Amount (After Change)</strong></td>
                            <td class="text-end">
                                <strong>?<?php echo formatIndianCurrency($new_pending_amount, false); ?></strong>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <?php if ($fee_diff != 0): ?>
                <div class="alert <?php echo $fee_diff > 0 ? 'alert-warning' : 'alert-success'; ?> mt-3 mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Note:</strong>
                    <?php if ($fee_diff > 0): ?>
                        Student will need to pay additional ?<?php echo formatIndianCurrency($fee_diff, false); ?> after group
                        change
                        approval.
                    <?php else: ?>
                        Student will have a credit of ?<?php echo formatIndianCurrency(abs($fee_diff), false); ?> after group
                        change
                        approval.
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Review Form (if not yet reviewed) -->
    <?php if ($request['status'] === 'pending' || $request['status'] === 'under_review'): ?>
        <div class="card">
            <div class="card-header bg-warning">
                <h3 class="card-title mb-0"><i class="fas fa-gavel me-2"></i>Review Actions</h3>
            </div>
            <div class="card-body">
                <form id="reviewForm">
                    <input type="hidden" name="request_id" value="<?php echo $request_id; ?>">

                    <?php if ($request['status'] === 'pending'): ?>
                        <div class="mb-3">
                            <button type="button" class="btn btn-info" id="markUnderReview">
                                <i class="fas fa-eye me-1"></i> Mark as Under Review
                            </button>
                        </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label for="review_remarks" class="form-label fw-bold">Review Remarks / Comments *</label>
                        <textarea class="form-control" id="review_remarks" name="review_remarks" rows="4" required
                            placeholder="Enter your remarks for this request..."></textarea>
                    </div>

                    <div class="d-flex gap-2 flex-wrap">
                        <button type="submit" name="action" value="approve" class="btn btn-success btn-lg">
                            <i class="fas fa-check-circle me-1"></i> Approve Request
                        </button>
                        <button type="submit" name="action" value="reject" class="btn btn-danger btn-lg">
                            <i class="fas fa-times-circle me-1"></i> Reject Request
                        </button>
                        <a href="pending-requests.php" class="btn btn-secondary btn-lg">
                            <i class="fas fa-arrow-left me-1"></i> Back to List
                        </a>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- Review Information (if already reviewed) -->
    <?php if ($request['status'] === 'approved' || $request['status'] === 'rejected'): ?>
        <div class="card">
            <div
                class="card-header <?php echo $request['status'] === 'approved' ? 'bg-success' : 'bg-danger'; ?> text-white">
                <h3 class="card-title mb-0"><i class="fas fa-clipboard-check me-2"></i>Review Information</h3>
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless">
                    <tr>
                        <td class="fw-bold text-muted" width="200">Reviewed By:</td>
                        <td><?php echo htmlspecialchars($request['reviewed_by_name'] ?? 'N/A'); ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold text-muted">Review Date:</td>
                        <td><?php echo $request['review_date'] ? date('d-m-Y h:i A', strtotime($request['review_date'])) : 'N/A'; ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="fw-bold text-muted">Review Remarks:</td>
                        <td><?php echo nl2br(htmlspecialchars($request['review_comments'] ?? 'N/A')); ?></td>
                    </tr>
                    <?php if ($request['status'] === 'approved'): ?>
                        <tr>
                            <td class="fw-bold text-muted">Fee Adjustment Done:</td>
                            <td>
                                <span
                                    class="badge <?php echo $request['fee_adjustment_done'] ? 'bg-success' : 'bg-warning text-dark'; ?>">
                                    <?php echo $request['fee_adjustment_done'] ? 'Yes' : 'No'; ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td class="fw-bold text-muted">Group Updated:</td>
                            <td>
                                <span
                                    class="badge <?php echo $request['group_updated'] ? 'bg-success' : 'bg-warning text-dark'; ?>">
                                    <?php echo $request['group_updated'] ? 'Yes' : 'No'; ?>
                                </span>
                            </td>
                        </tr>
                    <?php endif; ?>
                </table>
                <a href="pending-requests.php" class="btn btn-secondary mt-3">
                    <i class="fas fa-arrow-left me-1"></i> Back to List
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Request History Timeline -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title mb-0"><i class="fas fa-history me-2"></i>Request History</h3>
        </div>
        <div class="card-body">
            <?php if (empty($history)): ?>
                <p class="text-muted mb-0">No history available.</p>
            <?php else: ?>
                <?php foreach ($history as $h): ?>
                    <div class="timeline-item">
                        <div class="timeline-header">
                            <strong><?php echo ucfirst(str_replace('_', ' ', $h['action_type'])); ?></strong>
                            <?php if ($h['action_by_name']): ?>
                                by <?php echo htmlspecialchars($h['action_by_name'] ?? ''); ?>
                            <?php endif; ?>
                            <span class="text-muted float-end">
                                <?php echo date('d M Y h:i A', strtotime($h['created_at'])); ?>
                            </span>
                        </div>
                        <?php if ($h['remarks']): ?>
                            <div class="timeline-body text-muted">
                                <?php echo nl2br(htmlspecialchars($h['remarks'] ?? '')); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../../include/footer.php'; ?>



<script>
    $(document).ready(function () {
        // Mark as under review
        $('#markUnderReview').click(function () {
            showConfirm({
                title: 'Mark as Under Review?',
                text: 'This will change the status to Under Review',
                icon: 'question',
                confirmButtonColor: '#17a2b8',
                confirmButtonText: 'Yes, mark it!',
                onConfirm: () => {
                    $.ajax({
                        url: 'mark-under-review.php',
                        method: 'POST',
                        data: {
                            request_id: <?php echo $request_id; ?>
                        },
                        dataType: 'json',
                        success: function (response) {
                            if (response.success) {
                                showToast('success', 'Success!', 'Request marked as Under Review');
                                setTimeout(() => location.reload(), 1500);
                            } else {
                                showToast('error', 'Error!', response.message);
                            }
                        },
                        error: function () {
                            showToast('error', 'Error!', 'Error processing request');
                        }
                    });
                }
            });
        });

        // Handle approval/rejection
        $('#reviewForm').submit(function (e) {
            e.preventDefault();

            var action = $(document.activeElement).val();
            var remarks = $('#review_remarks').val().trim();

            if (!remarks) {
                showToast('warning', 'Required!', 'Please enter review remarks');
                return;
            }

            var confirmTitle = action === 'approve' ? 'Approve Request?' : 'Reject Request?';
            var confirmText = action === 'approve' ?
                'This will update the student\'s group and adjust fees automatically.' :
                'This request will be rejected.';
            var confirmColor = action === 'approve' ? '#28a745' : '#dc3545';

            const self = this;
            showConfirm({
                title: confirmTitle,
                text: confirmText,
                icon: 'question',
                confirmButtonColor: confirmColor,
                confirmButtonText: action === 'approve' ? 'Yes, Approve!' : 'Yes, Reject!',
                onConfirm: () => {
                    var formData = $(self).serializeArray();
                    formData.push({
                        name: 'action',
                        value: action
                    });

                    $('button[type="submit"]').prop('disabled', true);
                    showToast('info', 'Processing...', 'Please wait.');

                    $.ajax({
                        url: 'process-review.php',
                        method: 'POST',
                        data: $.param(formData),
                        dataType: 'json',
                        success: function (response) {
                            if (response.success) {
                                showToast('success', 'Success!', response.message);
                                setTimeout(() => window.location.href = 'pending-requests.php', 1500);
                            } else {
                                showToast('error', 'Error!', response.message);
                                $('button[type="submit"]').prop('disabled', false);
                            }
                        },
                        error: function () {
                            showToast('error', 'Error!', 'Error processing request');
                            $('button[type="submit"]').prop('disabled', false);
                        }
                    });
                }
            });
        });
    });
</script>
