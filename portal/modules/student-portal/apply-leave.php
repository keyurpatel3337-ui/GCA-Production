<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;

// Check if user is Student
if (!isset($_SESSION['is_student_login']) || $_SESSION['is_student_login'] !== true) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$student_id = $_SESSION['student_id'];
$page_title = "Apply for Leave";
$page_breadcrumb = "Leaves -";

try {
    // Check if there's already a pending request
    $stmt = $conn->prepare("SELECT id, status FROM tbl_student_leaves 
                            WHERE student_id = ? AND status = 'pending'");
    $stmt->execute([$student_id]);
    $pending_request = $stmt->fetch(PDO::FETCH_ASSOC);

    // Leave Types
    $leave_types = [
        'Medical' => 'Medical',
        'Maran Prasange' => 'Maran Prasange',
        'Marriage' => 'Marriage',
        'Function in House' => 'Function in House',
        'Personal Region' => 'Personal Region',
        'Emergency' => 'Emergency',
        'Sport Activity' => 'Sport Activity'
    ];
} catch (PDOException $e) {
    logError("Apply Leave Page Error: " . $e->getMessage(), __FILE__, __LINE__, $e);
    set_flash_message('error', "An error occurred while loading the page. Please try again.");
    $pending_request = null;
    $leave_types = [];
}

include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>

<?php if ($pending_request): ?>
    <div class="alert alert-warning">
        <h5><i class="fas fa-info-circle"></i> Pending Request</h5>
        <p>You already have a
            <?php echo $pending_request['status']; ?> leave request
            (Request No: <strong>LR-
                <?php echo $pending_request['id']; ?>
            </strong>).
        </p>
        <p>Please wait for it to be reviewed before submitting a new request.</p>
        <a href="my-leaves.php" class="btn btn-sm btn-primary">
            <i class="fas fa-list"></i> View My Leaves
        </a>
    </div>
<?php else: ?>
    <div class="row">
        <div class="col-md-8">
            <div class="card card-primary card-outline shadow-sm">
                <div class="card-header border-bottom-0">
                    <h5 class="card-title text-primary fw-bold mb-0">
                        <i class="fas fa-file-signature me-2"></i> Leave Application Form
                    </h5>
                </div>
                <div class="card-body">
                    <form id="applyLeaveForm" enctype="multipart/form-data">
                        <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">

                        <div class="form-group mb-3">
                            <label class="form-label fw-bold" for="leave_type">Leave Category <span
                                    class="text-danger">*</span></label>
                            <select name="leave_type" id="leave_type" class="form-select form-control" required>
                                <option value="">-- Select Leave Category --</option>
                                <?php foreach ($leave_types as $val => $label): ?>
                                    <option value="<?php echo htmlspecialchars($val ?? ''); ?>">
                                        <?php echo htmlspecialchars($label ?? ''); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6 form-group">
                                <label class="form-label fw-bold" for="start_date">Start Date <span
                                        class="text-danger">*</span></label>
                                <input type="date" name="start_date" id="start_date" class="form-control" required
                                    min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                                <small class="text-muted">Must be applied min. 1 day in advance.</small>
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="form-label fw-bold" for="end_date">End Date <span
                                        class="text-danger">*</span></label>
                                <input type="date" name="end_date" id="end_date" class="form-control" required
                                    min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                                <small class="text-muted">Maximum duration is 1 week.</small>
                            </div>
                        </div>

                        <div class="form-group mb-3">
                            <label class="form-label fw-bold" for="reason">Reason for Leave <span
                                    class="text-danger">*</span></label>
                            <textarea name="reason" id="reason" class="form-control" rows="4" required
                                placeholder="Please provide details..."></textarea>
                        </div>

                        <div class="form-group mb-3" id="document_upload_group" style="display: none;">
                            <label class="form-label fw-bold" for="document">Supporting Document <span class="text-danger"
                                    id="doc_required_asterisk" style="display:none;">*</span></label>
                            <input type="file" name="document" id="document" class="form-control"
                                accept=".jpg,.jpeg,.png,.pdf">
                            <small class="text-muted" id="doc_hint">Accepted formats: JPG, PNG, PDF (Max 2MB)</small>
                        </div>

                        <div class="form-check mb-3 mt-4">
                            <input type="checkbox" name="confirm" id="confirm" class="form-check-input" required>
                            <label class="form-check-label text-muted" for="confirm">
                                I confirm that the information provided is accurate and any submitted documents are genuine.
                            </label>
                        </div>
                    </form>
                </div>
                <div class="card-footer bg-white border-top-0 pt-0">
                    <button type="submit" form="applyLeaveForm" class="btn btn-primary shadow-sm" id="btnSubmit">
                        <i class="fas fa-paper-plane me-1"></i> Submit Application
                    </button>
                    <a href="dashboard.php" class="btn btn-light shadow-sm ms-2">Cancel</a>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card card-info card-outline shadow-sm">
                <div class="card-header bg-light">
                    <h3 class="card-title text-info fw-bold mb-0"><i class="fas fa-info-circle me-1"></i> Leave Policy
                        Guidelines</h3>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i> <strong>Notice Period:</strong>
                            Leaves must be applied at least 1 day prior to the start date.</li>
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i> <strong>Duration:</strong> A single
                            request can be made for a maximum of 7 days (1 week).</li>
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i> <strong>Medical Leave:</strong>
                            Medical certificate or prescription is mandatory.</li>
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i> <strong>Maran Prasange:</strong>
                            Document (Maran no melo / Kagar) is mandatory.</li>
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i> <strong>Marriage:</strong> Kankotri
                            (Invitation Card) is mandatory.</li>
                        <li><i class="fas fa-check text-success me-2"></i> <strong>Approval:</strong> All requests are
                            subject to review by Counsellors or the Principal.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

</div> <!-- End of content wrapper which is started in header -->

<?php include '../../include/footer.php'; ?>

<script>
    $(document).ready(function () {
        // Show/hide and require document upload based on leave type
        $('#leave_type').on('change', function () {
            const type = $(this).val();
            const docGroup = $('#document_upload_group');
            const docAsterisk = $('#doc_required_asterisk');
            const docInput = $('#document');

            // Requires document: Medical, Maran Prasange, Marriage
            if (type === 'Medical' || type === 'Maran Prasange' || type === 'Marriage') {
                docGroup.show();
                docAsterisk.show();
                docInput.prop('required', true);
            } else {
                docGroup.show(); // Always show as optional for others
                docAsterisk.hide();
                docInput.prop('required', false);
            }
        });

        // Date validation: End date must be >= Start date, max 7 days
        $('#start_date, #end_date').on('change', function () {
            const startDateVal = $('#start_date').val();
            const endDateVal = $('#end_date').val();

            if (startDateVal) {
                $('#end_date').attr('min', startDateVal);

                if (endDateVal) {
                    const start = new Date(startDateVal);
                    const end = new Date(endDateVal);
                    const diffTime = Math.abs(end - start);
                    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

                    if (end < start) {
                        if (typeof showToast === 'function') showToast('error', 'Error', 'End date cannot be before start date.');
                        $('#end_date').val('');
                    } else if (diffDays > 6) { // diffDays > 6 means > 7 days total length
                        if (typeof showToast === 'function') showToast('error', 'Error', 'Maximum leave duration is 1 week (7 days).');
                        $('#end_date').val('');
                    }
                }
            }
        });

        // Form Submission
        $('#applyLeaveForm').on('submit', function (e) {
            e.preventDefault();

            // Custom validations check here if needed before processing API request.

            showConfirm({
                title: 'Confirm Application',
                message: 'Are you sure you want to submit this leave request? Please verify dates and attached documents.',
                confirmText: 'Yes, Submit',
                confirmButtonClass: 'btn-primary',
                onConfirm: function () {
                    $('#btnSubmit').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');

                    const formData = new FormData(document.getElementById('applyLeaveForm'));

                    // Usually we can use $.api.post or fetch
                    $.ajax({
                        url: 'api/submit-leave.php',
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        dataType: 'json',
                        success: function (response) {
                            if (response.success) {
                                if (typeof showToast === 'function') showToast('success', 'Success!', response.message);
                                setTimeout(() => {
                                    window.location.href = 'my-leaves.php';
                                }, 1500);
                            } else {
                                if (typeof showToast === 'function') showToast('error', 'Error!', response.error || response.message);
                                $('#btnSubmit').prop('disabled', false).html('<i class="fas fa-paper-plane me-1"></i> Submit Application');
                            }
                        },
                        error: function (xhr, status, error) {
                            let errMsg = 'Failed to submit application.';
                            try {
                                const res = JSON.parse(xhr.responseText);
                                if (res.error || res.message) errMsg = res.error || res.message;
                            } catch (e) { }

                            if (typeof showToast === 'function') showToast('error', 'Error!', errMsg);
                            $('#btnSubmit').prop('disabled', false).html('<i class="fas fa-paper-plane me-1"></i> Submit Application');
                        }
                    });
                }
            });
        });
    });
</script>