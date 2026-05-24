<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/format_helper.php';

// Check if user is Student
if (!isset($_SESSION['is_student_login']) || $_SESSION['is_student_login'] !== true) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$student_id = $_SESSION['student_id'];
$page_title = "My Leaves";
$page_breadcrumb = "Leaves -";

try {
    // Fetch all leave requests for this student
    $stmt = $conn->prepare("SELECT * FROM tbl_student_leaves 
                            WHERE student_id = ? 
                            ORDER BY applied_at ASC");
    $stmt->execute([$student_id]);
    $leaves = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logError("My Leaves Page Error: " . $e->getMessage(), __FILE__, __LINE__, $e);
    set_flash_message('error', "An error occurred while loading your leaves.");
    $leaves = [];
}

include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card card-primary card-outline shadow-sm">
            <div class="card-header border-bottom-0 d-flex justify-content-between align-items-center">
                <h3 class="card-title text-primary fw-bold mb-0">
                    <i class="fas fa-list-alt me-2"></i> Leave History
                </h3>
                <div class="card-tools">
                    <a href="apply-leave.php" class="btn btn-sm btn-primary shadow-sm">
                        <i class="fas fa-plus me-1"></i> Apply for Leave
                    </a>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0 text-center align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th>#ID</th>
                                <th>Type</th>
                                <th>Duration</th>
                                <th>Applied On</th>
                                <th>Document</th>
                                <th>Status</th>
                                <th>Action/Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($leaves)): ?>
                                <tr>
                                    <td colspan="7" class="text-muted py-4">
                                        <i class="fas fa-folder-open fa-2x mb-2 text-light"></i><br>
                                        No leave applications found.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($leaves as $leave): ?>
                                    <tr>
                                        <td><strong>LR-
                                                <?php echo $leave['id']; ?>
                                            </strong></td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?php echo htmlspecialchars($leave['leave_type'] ?? ''); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            // Calculate days
                                            $start = new DateTime($leave['start_date']);
                                            $end = new DateTime($leave['end_date']);
                                            $diff = $start->diff($end);
                                            $days = $diff->days + 1;

                                            echo date('d M Y', strtotime($leave['start_date'])) . ' <br>to<br> ' . date('d M Y', strtotime($leave['end_date']));
                                            echo "<br><small class='text-muted'>({$days} Days)</small>";
                                            ?>
                                        </td>
                                        <td>
                                            <?php echo date('d M Y, h:i A', strtotime($leave['applied_at'])); ?>
                                        </td>
                                        <td>
                                            <?php if ($leave['doc_path']): ?>
                                                <a href="<?php echo BASE_URL . '/' . htmlspecialchars($leave['doc_path'] ?? ''); ?>"
                                                    target="_blank" class="btn btn-xs btn-outline-info">
                                                    <i class="fas fa-eye"></i> View Doc
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted"><i class="fas fa-minus"></i></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $badgeClass = match ($leave['status']) {
                                                'approved' => 'success',
                                                'pending' => 'warning',
                                                'rejected' => 'danger',
                                                'cancelled' => 'secondary',
                                                default => 'secondary'
                                            };
                                            echo "<span class='badge bg-{$badgeClass}'>" . ucfirst($leave['status']) . "</span>";
                                            ?>
                                        </td>
                                        <td>
                                            <?php if ($leave['status'] === 'pending'): ?>
                                                <button class="btn btn-xs btn-danger cancel-leave"
                                                    data-id="<?php echo $leave['id']; ?>">
                                                    <i class="fas fa-times"></i> Cancel
                                                </button>
                                            <?php elseif (!empty($leave['remarks'])): ?>
                                                <button class="btn btn-xs btn-outline-secondary" data-bs-toggle="popover"
                                                    data-bs-trigger="focus" title="Remarks"
                                                    data-bs-content="<?php echo htmlspecialchars($leave['remarks'] ?? ''); ?>">
                                                    <i class="fas fa-comment-dots"></i> Remarks
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted"><i class="fas fa-minus"></i></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

</div>

<?php include '../../include/footer.php'; ?>

<script>
    $(document).ready(function () {
        // Initialize bootstrap popovers for remarks
        const popoverTriggerList = document.querySelectorAll('[data-bs-toggle="popover"]');
        const popoverList = [...popoverTriggerList].map(popoverTriggerEl => new bootstrap.Popover(popoverTriggerEl));

        // Handle cancel button
        $('.cancel-leave').on('click', function () {
            const leaveId = $(this).data('id');

            showConfirm({
                title: 'Cancel Request',
                message: 'Are you sure you want to cancel this leave application?',
                confirmText: 'Yes, Cancel it',
                confirmButtonClass: 'btn-danger',
                onConfirm: function () {
                    $.ajax({
                        url: 'api/cancel-leave.php',
                        type: 'POST',
                        data: { id: leaveId, student_id: <?php echo $student_id; ?> },
                        dataType: 'json',
                        success: function (response) {
                            if (response.success) {
                                if (typeof showToast === 'function') showToast('success', 'Success!', response.message);
                                setTimeout(() => location.reload(), 1500);
                            } else {
                                if (typeof showToast === 'function') showToast('error', 'Error!', response.message || response.error);
                            }
                        },
                        error: function () {
                            if (typeof showToast === 'function') showToast('error', 'Error!', 'Failed to cancel the request.');
                        }
                });
        }
        });
    });
});
</script>