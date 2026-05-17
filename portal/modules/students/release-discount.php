<?php
header('Content-Type: text/html; charset=utf-8');
/**
 * Release/Modify Discount Page
 * Allows Principal to modify Scholarship and Post-Admission Discount amounts
 */

require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/format_helper.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/fee_helper.php';

// Check if user is logged in and has required permissions (Principal or Super Admin)
if (!hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN)) {
    set_flash_message('error', 'Unauthorized access');
    header('Location: ../../dashboard.php');
    exit;
}

// Get enrollment ID from URL
$enrollment_id = isset($_GET['enrollment_id']) ? intval($_GET['enrollment_id']) : 0;

if (!$enrollment_id) {
    header('Location: post-admission-discount.php');
    exit;
}

// Fetch student details
$stmt = $conn->prepare("
    SELECT 
        e.enrollment_id,
        e.registration_id,
        e.enrollment_no,
        e.enrollment_date,
        e.post_admission_discount_amount,
        e.post_admission_discount_remarks,
        r.surname,
        r.student_name,
        r.fathers_name,
        CONCAT(r.surname, ' ', r.student_name, ' ', r.fathers_name) AS full_name,
        r.mob,
        COALESCE(r.scholarship_amount, 0) AS scholarship_amount,
        COALESCE(r.additional_scholarship_amount, 0) AS additional_scholarship_amount,
        COALESCE(s.school_name, '') AS school_name,
        COALESCE(c.course_name, '') AS course_name,
        COALESCE(sfa.allocated_amount, 0) AS net_fees,
        COALESCE(sfa.paid_amount, 0) AS fees_paid,
        COALESCE(sfa.pending_amount, 0) AS fees_pending
    FROM tbl_enrolled_students e
    INNER JOIN tbl_gm_std_registration r ON e.registration_id = r.id
    LEFT JOIN tbl_student_fee_allocation sfa ON e.registration_id = sfa.student_id
    LEFT JOIN tbl_schools s ON r.school_id = s.id
    LEFT JOIN tbl_courses c ON r.course_id = c.id
    WHERE e.enrollment_id = :enrollment_id
");
$stmt->bindParam(':enrollment_id', $enrollment_id, PDO::PARAM_INT);
$stmt->execute();
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    set_flash_message('error', 'Student not found');
    header('Location: post-admission-discount.php');
    exit;
}

// Calculate total initial scholarship (Scholarship + Additional)
$initial_scholarship_total = floatval($student['scholarship_amount']) + floatval($student['additional_scholarship_amount']);
$post_admission_discount = floatval($student['post_admission_discount_amount']);

// Get complete fee breakdown
$fee_summary = calculateStudentFeeSummary($conn, $student['registration_id']);
$detailed_allocations = $fee_summary['detailed_allocations'] ?? [];

$page_title = "Release/Modify Discount";
include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>



<div class="container-fluid">
    <!-- Back Button -->
    <div class="row mb-3">
        <div class="col-12">
            <a href="post-admission-discount.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Student Details Card -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0"><i class="fas fa-user"></i> Student Details</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tbody>
                            <tr>
                                <th width="45%" class="text-muted">Enrollment No:</th>
                                <td><strong class="text-primary">
                                        <?php echo htmlspecialchars($student['enrollment_no'] ?? ''); ?>
                                    </strong></td>
                            </tr>
                            <tr>
                                <th class="text-muted">Student Name:</th>
                                <td><strong>
                                        <?php echo htmlspecialchars($student['full_name'] ?? ''); ?>
                                    </strong></td>
                            </tr>
                            <tr>
                                <th class="text-muted">Mobile:</th>
                                <td>
                                    <?php echo htmlspecialchars($student['mob'] ?? ''); ?>
                                </td>
                            </tr>
                            <tr>
                                <th class="text-muted">School:</th>
                                <td>
                                    <?php echo htmlspecialchars($student['school_name'] ?? ''); ?>
                                </td>
                            </tr>
                            <tr>
                                <th class="text-muted">Course:</th>
                                <td>
                                    <?php echo htmlspecialchars($student['course_name'] ?? ''); ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Fee Summary Card -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-success text-white">
                    <h5 class="card-title mb-0"><i class="fas fa-rupee-sign"></i> Fee Summary</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tbody>
                            <tr>
                                <th width="55%" class="text-muted">Total Fees:</th>
                                <td class="text-end"><strong class="fs-5">₹
                                        <?php echo formatIndianCurrency($student['net_fees']); ?>
                                    </strong></td>
                            </tr>
                            <tr>
                                <th class="text-muted">Fees Paid:</th>
                                <td class="text-end text-success"><strong>₹
                                        <?php echo formatIndianCurrency($student['fees_paid']); ?>
                                    </strong></td>
                            </tr>
                            <tr>
                                <th class="text-muted">Fees Pending:</th>
                                <td class="text-end text-danger"><strong>₹<span id="display_pending">
                                            <?php echo formatIndianCurrency($student['fees_pending']); ?>
                                        </span></strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Fee Component Breakdown Table -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><i class="fas fa-list-alt"></i> Current Fee Component Breakdown</h5>
                    <span class="badge bg-light text-dark">Term Based (Term
                        <?php echo $student['current_term_id'] ?? 1; ?>)</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0 align-middle">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-3">Fee Component</th>
                                    <th class="text-end">Allocated (Gross)</th>
                                    <th class="text-end">Paid Amount</th>
                                    <th class="text-end">Scholarship/Disc.</th>
                                    <th class="text-end">Pending</th>
                                    <th class="text-center pe-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($detailed_allocations)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted small">No allocation data found
                                            for this student.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($detailed_allocations as $comp_key => $alloc): ?>
                                        <tr class="component-row" data-key="<?php echo $comp_key; ?>"
                                            data-label="<?php echo htmlspecialchars($alloc['label'] ?? ''); ?>"
                                            data-waived="<?php echo $alloc['waived_amount']; ?>">
                                            <td class="ps-3">
                                                <div class="fw-bold"><?php echo htmlspecialchars($alloc['label'] ?? ''); ?></div>
                                                <small class="text-muted"><?php echo $alloc['category']; ?></small>
                                            </td>
                                            <td class="text-end">
                                                ₹<?php echo formatIndianCurrency($alloc['gross_amount']); ?></td>
                                            <td class="text-end text-success">
                                                ₹<?php echo formatIndianCurrency($alloc['paid_amount']); ?></td>
                                            <td class="text-end text-info">
                                                ₹<?php echo formatIndianCurrency($alloc['waived_amount']); ?></td>
                                            <td
                                                class="text-end fw-bold <?php echo $alloc['pending_amount'] > 0 ? 'text-danger' : 'text-success'; ?>">
                                                ₹<?php echo formatIndianCurrency($alloc['pending_amount']); ?>
                                            </td>
                                            <td class="text-center pe-3">
                                                <?php if ($alloc['waived_amount'] > 0): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-danger release-comp-btn"
                                                        data-bs-toggle="tooltip" title="Release discount for this component">
                                                        <i class="fas fa-minus-circle"></i> Release
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-muted small">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr class="table-dark">
                                        <td class="ps-3 fw-bold">TOTAL</td>
                                        <td class="text-end fw-bold">
                                            ₹<?php echo formatIndianCurrency($fee_summary['total_allocated']); ?></td>
                                        <td class="text-end fw-bold">
                                            ₹<?php echo formatIndianCurrency($fee_summary['total_paid']); ?></td>
                                        <td class="text-end fw-bold">
                                            ₹<?php echo formatIndianCurrency($fee_summary['total_waiver']); ?></td>
                                        <td class="text-end fw-bold">
                                            ₹<?php echo formatIndianCurrency($fee_summary['total_pending']); ?></td>
                                        <td></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-white small text-muted">
                    <i class="fas fa-info-circle me-1"></i> These figures represent the current state before any
                    modifications below.
                </div>
            </div>
        </div>
    </div>

    <!-- Modification Form -->
    <form id="modifyForm" method="POST" action="release-discount-save.php">
        <input type="hidden" name="enrollment_id" value="<?php echo $enrollment_id; ?>">
        <input type="hidden" id="original_pending" value="<?php echo $student['fees_pending']; ?>">

        <!-- Hidden inputs to track original values for change detection -->
        <input type="hidden" id="orig_initial_scholarship" value="<?php echo $initial_scholarship_total; ?>">
        <input type="hidden" id="orig_post_discount" value="<?php echo $post_admission_discount; ?>">

        <div class="row">
            <div class="col-md-8">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-warning">
                        <h5 class="card-title mb-0 text-dark"><i class="fas fa-edit me-2"></i> Confirm Modification</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="modification_reason" class="form-label fw-bold">Reason for Modification: <span
                                    class="text-danger">*</span></label>
                            <textarea class="form-control border-primary" id="modification_reason"
                                name="modification_reason" rows="3"
                                placeholder="e.g., Released GCA Part 2 scholarship as requested by principal..."
                                required></textarea>
                            <div class="form-text text-muted">A clear reason is required for audit purposes.</div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse"
                                data-bs-target="#manualAdjustments">
                                <i class="fas fa-cog"></i> Manual Adjustment
                            </button>
                            <div>
                                <a href="post-admission-discount.php" class="btn btn-light me-2">Cancel</a>
                                <button type="submit" class="btn btn-primary btn-lg px-5 shadow-sm" id="saveBtn">
                                    <i class="fas fa-save me-2"></i> Save Changes
                                </button>
                            </div>
                        </div>

                        <!-- Hidden Manual Adjustment Section -->
                        <div class="collapse mt-4 p-3 bg-light rounded border" id="manualAdjustments">
                            <h6 class="fw-bold mb-3 border-bottom pb-2">Manual Amount Overrides</h6>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="initial_scholarship" class="form-label small">Initial Scholarship
                                        (Reg.):</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">₹</span>
                                        <input type="number" class="form-control" id="initial_scholarship"
                                            name="initial_scholarship" value="<?php echo $initial_scholarship_total; ?>"
                                            step="0.01" min="0">
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="post_discount" class="form-label small">Post-Admission Discount:</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">₹</span>
                                        <input type="number" class="form-control" id="post_discount"
                                            name="post_discount" value="<?php echo $post_admission_discount; ?>"
                                            step="0.01" min="0">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <!-- Preview Section -->
                <div class="card shadow-sm border-0 h-100 mb-4">
                    <div class="card-header bg-dark text-white">
                        <h5 class="card-title mb-0"><i class="fas fa-chart-line me-2"></i> Impact Preview</h5>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                <div>
                                    <div class="small text-muted">New Total Discount</div>
                                    <div class="h5 mb-0 fw-bold text-primary">₹<span
                                            id="prev_total_new">0.00</span></div>
                                </div>
                                <div class="text-end">
                                    <div class="small badge bg-light text-dark border"><span
                                            id="prev_total_diff">0.00</span></div>
                                </div>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3 bg-light">
                                <div>
                                    <div class="small text-muted">Updated Pending Fee</div>
                                    <div class="h4 mb-0 fw-bold text-danger">₹<span
                                            id="prev_pending_new">0.00</span></div>
                                </div>
                                <div class="text-end">
                                    <div class="small badge bg-danger text-white"><span
                                            id="prev_pending_diff">0.00</span></div>
                                </div>
                            </li>
                        </ul>
                    </div>
                    <div class="card-footer bg-white small text-center text-muted border-top-0">
                        Real-time calculation based on your inputs.
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<?php include '../../include/footer.php'; ?>

<script>
    $(document).ready(function () {
        // Initial Calculation
        calculateImpact();

        // Listen for input changes
        $('#initial_scholarship, #post_discount').on('input', function () {
            calculateImpact();
        });

        function calculateImpact() {
            // Original Values
            const origScholarship = parseFloat($('#orig_initial_scholarship').val()) || 0;
            const origPostDisc = parseFloat($('#orig_post_discount').val()) || 0;
            const origTotal = origScholarship + origPostDisc;
            const origPending = parseFloat($('#original_pending').val()) || 0;

            // New Values
            const newScholarship = parseFloat($('#initial_scholarship').val()) || 0;
            const newPostDisc = parseFloat($('#post_discount').val()) || 0;
            const newTotal = newScholarship + newPostDisc;

            // Difference (Positive means we are giving MORE discount, so Pending decreases)
            // (Negative means we are removing discount, so Pending increases)
            const diff = newTotal - origTotal;

            const newPending = origPending - diff;

            // Update UI
            $('#prev_total_new').text(newTotal.toFixed(2));
            $('#prev_total_diff').html(formatDiff(diff));

            $('#prev_pending_new').text(newPending.toFixed(2));
            $('#prev_pending_diff').html(formatDiff(-diff)); // Inverse logic for pending

            // Validation style
            if (newPending < 0) {
                $('#prev_pending_new').addClass('text-danger').removeClass('text-primary');
            } else {
                $('#prev_pending_new').removeClass('text-danger').addClass('text-primary');
            }
        }

        function formatDiff(val) {
            if (val > 0) return '<span class="text-success">+' + val.toFixed(2) + '</span>';
            if (val < 0) return '<span class="text-danger">' + val.toFixed(2) + '</span>';
            return '<span class="text-muted">0.00</span>';
        }

        $('#modifyForm').on('submit', function (e) {
            e.preventDefault();

            // Ensure Smart Breakdown is correctly formatted
            const reason = $('#modification_reason').val().trim();
            if (!reason) {
                if (typeof showToast === 'function') {
                    showToast('error', 'Error', 'Please provide a reason for this modification.');
                } else {
                    alert('Please provide a reason for this modification.');
                }
                return;
            }

            if (confirm("Are you sure you want to update these discount amounts? This will directly affect the student's pending fees.")) {
                const btn = $('#saveBtn');
                const originalHtml = btn.html();
                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');
                this.submit();
            }
        });

        // Component Release Logic
        $('.release-comp-btn').on('click', function () {
            const row = $(this).closest('.component-row');
            const label = row.data('label');
            const compKey = row.data('key');
            const waived = parseFloat(row.data('waived')) || 0;

            // Set default value to 'waived' so user can just click OK to release all
            let rawInput = prompt(`How much discount do you want to RELEASE (reduce) for ${label}?\n(Current Discount: ₹${waived})`, waived);

            if (rawInput === null) return;

            // Clean up input: remove commas, currency symbols, and extra spaces
            rawInput = rawInput.toString().replace(/,/g, '').replace(/₹/g, '').trim();

            const amount = parseFloat(rawInput);
            if (isNaN(amount) || amount < 0) {
                alert("Please enter a valid number (0 or greater).");
                return;
            }

            if (amount > waived) {
                alert(`Release amount (₹${amount}) cannot exceed current discount (₹${waived}).`);
                return;
            }

            // Decide which field to subtract from (Post-admission favored)
            const postDiscField = $('#post_discount');
            const scholarField = $('#initial_scholarship');

            let currentPost = parseFloat(postDiscField.val()) || 0;
            let currentSch = parseFloat(scholarField.val()) || 0;

            if (currentPost >= amount) {
                postDiscField.val((currentPost - amount).toFixed(2));
            } else {
                // If post-discount isn't enough, take the rest from scholarship
                const remaining = amount - currentPost;
                postDiscField.val(0);
                scholarField.val(Math.max(0, currentSch - remaining).toFixed(2));
            }

            // Update Reason
            let reason = $('#modification_reason').val().trim();
            const breakdownHeader = "\n\nSmart Waiver Breakdown (Modifications):";

            if (!reason.includes(breakdownHeader)) {
                reason += breakdownHeader;
            }

            reason += `\n- Released ₹${amount} from ${label}`;
            $('#modification_reason').val(reason);

            // Trigger Recalculation
            calculateImpact();

            if (typeof showToast === 'function') {
                showToast('success', 'Released', `₹${amount} released from ${label}. Pending fee updated.`);
            }
        });
    });
</script>