<?php
header('Content-Type: text/html; charset=utf-8');
/**
 * Pending Discount Approvals
 * Allows Principal and Super Admin to review, modify, and approve/reject discount requests
 */

require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/format_helper.php';

// Role check - Only Principal and Super Admin can access
if (!hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN)) {
    set_flash_message('error', "Access denied. Only Principal and Super Admin can approve discounts.");
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "Pending Discount Approvals";

// Fetch pending requests
try {
    $sql = "SELECT 
                d.*,
                r.surname, r.student_name, r.fathers_name,
                e.enrollment_no,
                s.school_name,
                c.course_name,
                a.name as requested_by_name
            FROM tbl_post_admission_discounts d
            INNER JOIN tbl_gm_std_registration r ON d.student_id = r.id
            INNER JOIN tbl_enrolled_students e ON d.enrollment_id = e.enrollment_id
            LEFT JOIN tbl_schools s ON r.school_id = s.id
            LEFT JOIN tbl_courses c ON r.course_id = c.id
            LEFT JOIN tbl_users a ON d.created_by = a.id
            ORDER BY 
                CASE WHEN d.status = 'pending' THEN 1 ELSE 2 END ASC,
                d.created_at DESC";

    $stmt = $conn->query($sql);
    $pending_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logError("Pending Discount Approvals Error: " . $e->getMessage(), __FILE__, __LINE__, $e);
    $pending_list = [];
}

include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>

<div class="container-fluid">
    <div class="row mb-3">
        <div class="col-12">
            <a href="post-admission-discount.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Students
            </a>
        </div>
    </div>

    <div class="card card-warning">
        <div class="card-header">
            <h3 class="card-title text-dark"><i class="fas fa-clock"></i> Pending Discount Approvals</h3>
        </div>
        <div class="card-body">
            <?php if (empty($pending_list)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No pending discount requests found.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>Date</th>
                                <th>Student Details</th>
                                <th>Requested By</th>
                                <th>Requested Amount</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_list as $row): ?>
                                <tr>
                                    <td>
                                        <?php echo date('d-M-Y H:i', strtotime($row['created_at'])); ?>
                                    </td>
                                    <td>
                                        <strong>
                                            <?php echo htmlspecialchars($row['surname'] . ' ' . $row['student_name'] ?? ''); ?>
                                        </strong><br>
                                        <small class="text-muted">
                                            <?php echo $row['enrollment_no']; ?>
                                        </small><br>
                                        <small class="text-muted">
                                            <?php echo $row['course_name']; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($row['requested_by_name'] ?? 'System'); ?>
                                    </td>
                                    <td>
                                        <div class="input-group input-group-sm" style="max-width: 150px;">
                                            <span class="input-group-text">₹</span>
                                            <input type="number" class="form-control approval-amount"
                                                value="<?php echo round($row['discount_amount']); ?>"
                                                data-id="<?php echo $row['discount_id']; ?>">
                                        </div>
                                        <small class="text-info">Type: <?php echo ucfirst($row['discount_type']); ?>
                                            (<?php echo $row['discount_value']; ?><?php echo $row['discount_type'] == 'percentage' ? '%' : ''; ?>)</small>
                                    </td>
                                    <td><small><?php echo htmlspecialchars($row['remarks'] ?? ''); ?></small></td>
                                    <td>
                                        <?php if ($row['status'] === 'approved'): ?>
                                                        <span class="badge bg-success">Approved</span>
                                                <?php elseif ($row['status'] === 'rejected'): ?>
                                                        <span class="badge bg-danger">Rejected</span>
                                                <?php else: ?>
                                                        <span class="badge bg-warning text-dark">Pending</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($row['status'] === 'pending'): ?>
                                                        <div class="d-flex gap-1">
                                                <button class="btn btn-sm btn-success action-btn" data-action="approve"
                                                    data-id="<?php echo $row['discount_id']; ?>">
                                                    <i class="fas fa-check"></i> Approve
                                                </button>
                                                <button class="btn btn-sm btn-danger action-btn" data-action="reject"
                                                    data-id="<?php echo $row['discount_id']; ?>">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                            </div>
                                                <?php else: ?>
                                                        <span class="text-muted small">Processed</span>
                                                <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Rejection Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Reject Discount Request</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="reject_id">
                <div class="mb-3">
                    <label class="form-label">Reason for Rejection <span class="text-danger">*</span></label>
                    <textarea id="reject_reason" class="form-control" rows="3" placeholder="Enter reason..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmReject">Confirm Rejection</button>
            </div>
        </div>
    </div>
</div>

<?php include '../../include/footer.php'; ?>

<script>
    $(document).ready(function () {
        $('.action-btn').on('click', function () {
            const btn = $(this);
            const action = btn.data('action');
            const id = btn.data('id');

            if (action === 'approve') {
                const amount = btn.closest('tr').find('.approval-amount').val();
                if (confirm(`Are you sure you want to approve this discount with amount ₹${amount}?`)) {
                    processRequest(id, 'approve', amount);
                }
            } else {
                $('#reject_id').val(id);
                $('#reject_reason').val('');
                $('#rejectModal').modal('show');
            }
        });

        $('#confirmReject').on('click', function () {
            const id = $('#reject_id').val();
            const reason = $('#reject_reason').val().trim();

            if (!reason) {
                alert('Please provide a reason for rejection.');
                return;
            }

            processRequest(id, 'reject', 0, reason);
        });

        function processRequest(id, action, amount, reason = '') {
            $.ajax({
                url: 'discount-approval-process.php',
                type: 'POST',
                data: {
                    id: id,
                    action: action,
                    amount: amount,
                    reason: reason
                },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        if (typeof showToast === 'function') {
                            showToast('success', 'Success', response.message);
                        } else {
                            alert(response.message);
                        }
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        if (typeof showToast === 'function') {
                            showToast('error', 'Error', response.message);
                        } else {
                            alert(response.message);
                        }
                    }
                },
                error: function () {
                    alert('An error occurred during processing.');
                }
            });
        }
    });
</script>