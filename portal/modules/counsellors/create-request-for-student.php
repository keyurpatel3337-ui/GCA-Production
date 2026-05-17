<?php
/**
 * Create Installment Request for Student - Counsellor Interface
 * Allows counsellors to create installment requests on behalf of their assigned students
 */
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;
require_once __DIR__ . '/../../common/security_output.php';

// Check if user is a counsellor
if (!hasRole(ROLE_COUNSELLOR)) {
    set_flash_message('error', 'Unauthorized access. Only counsellors can access this page.');
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "Request Installment for Student";
$page_breadcrumb = "Request Installment";

$counsellor_id = $_SESSION['user_id'];
$op = new Operation();

// Fetch only students assigned to this counsellor
$students = $dbOps->customSelect(
    "SELECT s.id, s.enrollment_id, 
            CONCAT(s.surname, ' ', s.student_name, ' ', s.fathers_name) as full_name,
            s.aadhaar, s.mob,
            c.course_name, g.group_name
     FROM tbl_gm_std_registration s
     LEFT JOIN tbl_courses c ON s.course_id = c.id
     LEFT JOIN tbl_group g ON s.group_id = g.id
     WHERE s.counsellor_id = ? AND s.is_enrolled = 1
     ORDER BY s.surname, s.student_name",
    [$counsellor_id]
);

// Fee components
$fee_components = [
    'school_fee' => 'School Fee',
    'trust_facilities_fee' => 'Trust Facilities Fee',
    'tuition_fee_part2' => 'Tuition Fee Part 2'
];

include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>



    <div class="container-fluid">

        <?php if (empty($students)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                You don't have any students assigned to you. Please contact the administrator to assign students.
            </div>
        <?php else: ?>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Create Installment Request</h3>
                    <div class="card-tools">
                        <span class="badge bg-warning text-dark">
                            <i class="fas fa-clock"></i> Requires Principal Approval
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <form id="createRequestForm" method="POST" action="create-request-for-student-process.php">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Select Student <span class="text-danger">*</span></label>
                                    <select name="student_id" id="student_id" class="form-select" required>
                                        <option value="">-- Select Your Assigned Student --</option>
                                        <?php foreach ($students as $student): ?>
                                            <option value="<?php echo $student['id']; ?>"
                                                data-aadhaar="<?php echo htmlspecialchars($student['aadhaar'] ?? ''); ?>"
                                                data-mobile="<?php echo htmlspecialchars($student['mob'] ?? ''); ?>"
                                                data-course="<?php echo htmlspecialchars($student['course_name'] ?? ''); ?>"
                                                data-group="<?php echo htmlspecialchars($student['group_name'] ?? ''); ?>">
                                                <?php echo htmlspecialchars($student['full_name'] ?? ''); ?>
                                                (<?php echo htmlspecialchars($student['enrollment_id'] ?? 'Not Enrolled'); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Student Details</label>
                                    <div id="student_details" class="p-3 bg-light rounded">
                                        <span class="text-muted">Select a student to see details</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Fee Component <span class="text-danger">*</span></label>
                                    <select name="fee_component" id="fee_component" class="form-select" required>
                                        <option value="">-- Select Fee Component --</option>
                                        <?php foreach ($fee_components as $key => $label): ?>
                                            <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Total Amount (₹) <span class="text-danger">*</span></label>
                                    <input type="number" name="amount" id="amount" class="form-control" min="100"
                                        step="0.01" required placeholder="Enter total amount">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Number of Installments <span
                                            class="text-danger">*</span></label>
                                    <select name="requested_installments" id="requested_installments" class="form-select"
                                        required>
                                        <option value="">-- Select --</option>
                                        <?php for ($i = 2; $i <= 12; $i++): ?>
                                            <option value="<?php echo $i; ?>"><?php echo $i; ?> Installments</option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label class="form-label">Reason for Installment Request <span
                                            class="text-danger">*</span></label>
                                    <textarea name="reason" id="reason" class="form-control" rows="3" required
                                        placeholder="Please explain why this student needs installment facility..."></textarea>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Each Installment Amount</label>
                                    <input type="text" id="installment_amount_preview" class="form-control" readonly>
                                    <small class="text-muted">Auto-calculated</small>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>Note:</strong> This request will be sent to the Principal for approval.
                            Once approved, the installment schedule will be created for the student.
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="reset" class="btn btn-secondary">
                                <i class="fas fa-redo"></i> Reset
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Submit Request
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        <?php endif; ?>

        </div>

<?php include '../../include/footer.php'; ?>

<script>
    $(document).ready(function () {
        // Student selection - show details
        $('#student_id').on('change', function () {
            const selected = $(this).find(':selected');
            if (selected.val()) {
                const details = `
                <div class="row">
                    <div class="col-6"><strong>Aadhaar:</strong> ${selected.data('aadhaar') || 'N/A'}</div>
                    <div class="col-6"><strong>Mobile:</strong> ${selected.data('mobile') || 'N/A'}</div>
                    <div class="col-6"><strong>Standard:</strong> ${selected.data('course') || 'N/A'}</div>
                    <div class="col-6"><strong>Group:</strong> ${selected.data('group') || 'N/A'}</div>
                </div>
            `;
                $('#student_details').html(details);
            } else {
                $('#student_details').html('<span class="text-muted">Select a student to see details</span>');
            }
        });

        // Calculate installment amount
        function updateInstallmentAmount() {
            const totalAmount = parseFloat($('#amount').val()) || 0;
            const numInstallments = parseInt($('#requested_installments').val()) || 0;

            if (totalAmount > 0 && numInstallments > 0) {
                const installmentAmount = (totalAmount / numInstallments).toFixed(2);
                $('#installment_amount_preview').val('₹' + installmentAmount);
            } else {
                $('#installment_amount_preview').val('');
            }
        }

        $('#amount, #requested_installments').on('change keyup', updateInstallmentAmount);

        // Form submission
        $('#createRequestForm').on('submit', function (e) {
            e.preventDefault();

            const form = $(this);
            const submitBtn = form.find('button[type="submit"]');
            submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Submitting...');

            $.ajax({
                url: form.attr('action'),
                method: 'POST',
                data: form.serialize(),
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        alert(response.message);
                        window.location.href = 'my-installment-requests.php';
                    } else {
                        alert('Error: ' + response.message);
                        submitBtn.prop('disabled', false).html('<i class="fas fa-paper-plane"></i> Submit Request');
                    }
                },
                error: function () {
                    alert('An error occurred. Please try again.');
                    submitBtn.prop('disabled', false).html('<i class="fas fa-paper-plane"></i> Submit Request');
                }
            });
        });
    });
</script>

</body>

</html>

