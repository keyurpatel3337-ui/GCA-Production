<?php
header('Content-Type: text/html; charset=utf-8');
/**
 * Post-Admission Discount Application Page
 * Allows Principal and Super Admin to apply discounts after admission confirmation
 */

require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/format_helper.php';
require_once HELPERS_PATH . 'fee_helper.php';

// Check if user is logged in and has required permissions
if (!hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_ACCOUNTANT)) {
    set_flash_message('error', 'Unauthorized access');
    header('Location: ../../dashboard.php');
    exit;
}

$is_accountant = hasRole(ROLE_ACCOUNTANT) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN);

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
        e.enrollment_no,
        e.registration_id,
        e.enrollment_date,
        e.current_term_id,
        e.post_admission_discount_amount,
        e.post_admission_discount_remarks,
        r.surname,
        r.student_name,
        r.fathers_name,
        CONCAT(r.surname, ' ', r.student_name, ' ', r.fathers_name) AS full_name,
        r.mob,
        r.course_id,
        r.medium_id,
        r.group_id,
        r.school_id,
        COALESCE(r.scholarship_amount, 0) AS scholarship_amount,
        COALESCE(r.additional_scholarship_amount, 0) AS additional_scholarship_amount,
        COALESCE(r.scholarship_percentage, 0) AS scholarship_percentage,
        s.school_name,
        c.course_name,
        COALESCE(sfa.allocated_amount, 0) AS net_fees,
        COALESCE(sfa.paid_amount, 0) AS fees_paid,
        COALESCE(sfa.pending_amount, 0) AS fees_pending,
        g.group_name
    FROM tbl_enrolled_students e
    INNER JOIN tbl_gm_std_registration r ON e.registration_id = r.id
    LEFT JOIN tbl_student_fee_allocation sfa ON e.registration_id = sfa.student_id
    LEFT JOIN tbl_schools s ON r.school_id = s.id
    LEFT JOIN tbl_courses c ON r.course_id = c.id
    LEFT JOIN tbl_group g ON r.group_id = g.id
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

// Optimization: Check if we need to fetch fees via helper (if joined values are 0)
if (floatval($student['net_fees'] ?? 0) <= 0) {
    $fee_summary = calculateStudentFeeSummary($conn, $student['registration_id']);
    if (!empty($fee_summary)) {
        $student['net_fees'] = $fee_summary['total_allocated'];
        $student['fees_paid'] = $fee_summary['total_paid'];
        $student['fees_pending'] = $fee_summary['total_pending'];
    }
}

$total_scholarship = floatval($student['scholarship_amount'] ?? 0) + floatval($student['additional_scholarship_amount'] ?? 0);

// Fetch pending discount requests for this student
$pending_requests = $dbOps->customSelect("
    SELECT d.*, a.name as requested_by_name 
    FROM tbl_post_admission_discounts d
    LEFT JOIN tbl_users a ON d.created_by = a.id
    WHERE d.enrollment_id = ? AND d.status = 'pending'
    ORDER BY d.created_at ASC
", [$enrollment_id]);

// Fetch fee configuration for smart waiver
$fee_config = null;
try {
    // Get Term Name for fee config filtering
    $term_name = 'Semester 1'; // Default
    if ($student && !empty($student['current_term_id'])) {
        $term_row = $dbOps->selectOne('tbl_term', ['term_name'], ['id' => $student['current_term_id']]);
        if ($term_row) {
            $term_name = $term_row['term_name'];
        }
    }

    $fee_config = $dbOps->selectOne('tbl_fee_config', [
        'school_fee',
        'trust_facilities_fee',
        'tuition_fee_part1',
        'tuition_fee_part2',
        'hostel_fee',
        'token_fee'
    ], [
        'course_id' => $student['course_id'],
        'medium_id' => $student['medium_id'],
        'group_id' => $student['group_id'],
        'term' => $term_name,
        'is_active' => 1
    ]);

    if (!$fee_config) {
        // Fallback to course-term
        $res = $dbOps->customSelect("SELECT school_fee, trust_facilities_fee, tuition_fee_part1, tuition_fee_part2, hostel_fee, token_fee FROM tbl_fee_config WHERE course_id = ? AND term = ? AND is_active = 1 ORDER BY id DESC LIMIT 1", [$student['course_id'], $term_name]);
        $fee_config = $res[0] ?? null;
    }
} catch (Exception $e) {
    logError("Fee Config Fetch Error: " . $e->getMessage());
}

$page_title = $is_accountant ? "Request Post-Admission Discount" : "Apply Post-Admission Discount";
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
                                <td><strong
                                        class="text-primary"><?php echo htmlspecialchars($student['enrollment_no'] ?? ''); ?></strong>
                                </td>
                            </tr>
                            <tr>
                                <th class="text-muted">Student Name:</th>
                                <td><strong><?php echo htmlspecialchars($student['full_name'] ?? ''); ?></strong></td>
                            </tr>
                            <tr>
                                <th class="text-muted">Mobile:</th>
                                <td><?php echo htmlspecialchars($student['mob'] ?? ''); ?></td>
                            </tr>
                            <tr>
                                <th class="text-muted">School:</th>
                                <td><?php echo htmlspecialchars($student['school_name'] ?? 'N/A'); ?></td>
                            </tr>
                            <tr>
                                <th class="text-muted">Course:</th>
                                <td><?php echo htmlspecialchars($student['course_name'] ?? 'N/A'); ?></td>
                            </tr>
                            <tr>
                                <th class="text-muted">Group:</th>
                                <td><?php echo htmlspecialchars($student['group_name'] ?? 'N/A'); ?></td>
                            </tr>
                            <tr>
                                <th class="text-muted">Enrollment Date:</th>
                                <td><?php echo date('d-M-Y', strtotime($student['enrollment_date'])); ?></td>
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
                                <td class="text-end"><strong class="fs-5">₹<span
                                            id="display-net-fees"><?php echo formatIndianCurrency($student['net_fees'], false); ?></span></strong>
                                </td>
                            </tr>
                            <tr>
                                <th class="text-muted">Fees Paid:</th>
                                <td class="text-end text-success">
                                    <strong>₹<?php echo formatIndianCurrency($student['fees_paid'], false); ?></strong>
                                </td>
                            </tr>
                            <tr>
                                <th class="text-muted">Fees Pending:</th>
                                <td class="text-end text-danger"><strong>₹<span
                                            id="display-fees-pending"><?php echo formatIndianCurrency($student['fees_pending'], false); ?></span></strong>
                                </td>
                            </tr>
                            <tr class="border-top">
                                <th class="text-muted">Admission Scholarship:</th>
                                <td class="text-end text-info">
                                    <?php if ($total_scholarship > 0): ?>
                                        <strong>₹<?php echo formatIndianCurrency($total_scholarship, false); ?></strong>
                                        <?php if ($student['scholarship_percentage'] > 0): ?>
                                            <small
                                                class="text-muted">(<?php echo $student['scholarship_percentage']; ?>%)</small>
                                        <?php endif; ?>
                                        <?php if ($student['scholarship_amount'] > 0 && $student['additional_scholarship_amount'] > 0): ?>
                                            <br><small class="text-muted">
                                                Base:
                                                ₹<?php echo formatIndianCurrency($student['scholarship_amount'], false); ?>
                                                +
                                                Additional:
                                                ₹<?php echo formatIndianCurrency($student['additional_scholarship_amount'], false); ?>
                                            </small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">No scholarship</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th class="text-muted">Existing Discount:</th>
                                <td class="text-end">
                                    <strong class="text-warning" id="existing-discount-display">
                                        ₹<?php echo formatIndianCurrency($student['post_admission_discount_amount'] ?? 0, false); ?>
                                    </strong>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Discount Form -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-warning">
                    <h5 class="card-title mb-0"><i class="fas fa-percentage"></i>
                        <?php echo $is_accountant ? 'Request New Discount' : 'Apply New Discount'; ?></h5>
                </div>

                <?php if (!empty($pending_requests)): ?>
                    <div class="card-body bg-light border-bottom">
                        <h6 class="text-warning"><i class="fas fa-clock"></i> Existing Pending Requests:</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Date</th>
                                        <th>Requested By</th>
                                        <th>Amount</th>
                                        <th>Reason</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_requests as $req): ?>
                                        <tr>
                                            <td><?php echo date('d-M-Y', strtotime($req['created_at'])); ?></td>
                                            <td><?php echo htmlspecialchars($req['requested_by_name'] ?? 'Unknown'); ?></td>
                                            <td>₹<?php echo formatIndianCurrency($req['discount_amount']); ?></td>
                                            <td><?php echo htmlspecialchars($req['remarks'] ?? ''); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
                <form id="discountForm" method="POST" action="post-admission-discount-save.php">
                    <input type="hidden" name="enrollment_id" value="<?php echo $enrollment_id; ?>">
                    <input type="hidden" id="current_net_fees" value="<?php echo $student['net_fees']; ?>">
                    <input type="hidden" id="current_fees_paid" value="<?php echo $student['fees_paid']; ?>">
                    <input type="hidden" id="current_pending_fees" value="<?php echo $student['fees_pending']; ?>">
                    <input type="hidden" id="existing_discount"
                        value="<?php echo $student['post_admission_discount_amount'] ?? 0; ?>">

                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="discount_type" class="form-label">Discount Type: <span
                                            class="text-danger">*</span></label>
                                    <select class="form-select" id="discount_type" name="discount_type" required>
                                        <option value="">-- Select Discount Type --</option>
                                        <option value="fixed">Fixed Amount (₹)</option>
                                        <option value="percentage">Percentage (%)</option>
                                        <option value="smart">Smart / Global Waiver</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6" id="discount_value_container">
                                <div class="mb-3">
                                    <label for="discount_value" class="form-label">Discount Value: <span
                                            class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="discount_value" name="discount_value"
                                        step="1" min="0" placeholder="Enter discount value" required>
                                    <small class="form-text text-muted" id="discount-hint">Select discount type
                                        first</small>
                                </div>
                            </div>
                        </div>

                        <!-- Smart Discount Options (Hidden by default) -->
                        <div class="row mt-2 css-post-admission-discount-apply-224b51" id="smart_options_container">
                            <div class="col-md-12">
                                <div class="card bg-light border-warning">
                                    <div class="card-body py-2">
                                        <h6 class="text-warning mb-2"><i class="fas fa-magic"></i> Select
                                            Fees to Waive (Global Waiver)</h6>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-borderless mb-0">
                                                <tbody>
                                                    <tr>
                                                        <td class="css-post-admission-discount-apply-5172a7">
                                                            <div class="form-check">
                                                                <input class="form-check-input smart-fee-check"
                                                                    type="checkbox" id="smart_check_school"
                                                                    value="school_fee"
                                                                    data-amount="<?php echo $fee_config['school_fee'] ?? 0; ?>">
                                                                <label class="form-check-label fw-bold"
                                                                    for="smart_check_school">School Fee
                                                                    (₹<?php echo number_format($fee_config['school_fee'] ?? 0); ?>)</label>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex gap-2 align-items-center">
                                                                <select
                                                                    class="form-select form-select-sm border-warning smart-fee-type css-post-admission-discount-apply-bd8e43"
                                                                    data-target="school_fee" disabled>
                                                                    <option value="percentage">Percentage (%)</option>
                                                                    <option value="amount">Amount (₹)</option>
                                                                </select>
                                                                <input type="number"
                                                                    class="form-control form-control-sm smart-fee-value css-post-admission-discount-apply-7ecc66"
                                                                    data-target="school_fee" value="" min="0" step="any"
                                                                    placeholder="Enter value"
                                                                    disabled>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td>
                                                            <div class="form-check mt-1">
                                                                <input class="form-check-input smart-fee-check"
                                                                    type="checkbox" id="smart_check_trust"
                                                                    value="trust_facilities_fee"
                                                                    data-amount="<?php echo $fee_config['trust_facilities_fee'] ?? 0; ?>">
                                                                <label class="form-check-label fw-bold"
                                                                    for="smart_check_trust">Trust Fee
                                                                    (₹<?php echo number_format($fee_config['trust_facilities_fee'] ?? 0); ?>)</label>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex gap-2 align-items-center">
                                                                <select
                                                                    class="form-select form-select-sm border-warning smart-fee-type css-post-admission-discount-apply-bd8e43"
                                                                    data-target="trust_facilities_fee" disabled>
                                                                    <option value="percentage">Percentage (%)</option>
                                                                    <option value="amount">Amount (₹)</option>
                                                                </select>
                                                                <input type="number"
                                                                    class="form-control form-control-sm smart-fee-value css-post-admission-discount-apply-7ecc66"
                                                                    data-target="trust_facilities_fee" value="" min="0"
                                                                    step="any" placeholder="Enter value" disabled>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td>
                                                            <div class="form-check mt-1">
                                                                <input class="form-check-input smart-fee-check"
                                                                    type="checkbox" id="smart_check_tuition2"
                                                                    value="tuition_fee_part2"
                                                                    data-amount="<?php echo ($fee_config['tuition_fee_part2'] ?? 0) * 1.18; ?>">
                                                                <label class="form-check-label fw-bold"
                                                                    for="smart_check_tuition2">Tuition Part 2 (Incl.
                                                                    GST:
                                                                    ₹<?php echo number_format(($fee_config['tuition_fee_part2'] ?? 0) * 1.18); ?>)</label>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex gap-2 align-items-center">
                                                                <select
                                                                    class="form-select form-select-sm border-warning smart-fee-type css-post-admission-discount-apply-bd8e43"
                                                                    data-target="tuition_fee_part2" disabled>
                                                                    <option value="percentage">Percentage (%)</option>
                                                                    <option value="amount">Amount (₹)</option>
                                                                </select>
                                                                <input type="number"
                                                                    class="form-control form-control-sm smart-fee-value css-post-admission-discount-apply-7ecc66"
                                                                    data-target="tuition_fee_part2" value="" min="0"
                                                                    step="any" placeholder="Enter value" disabled>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="discount_reason" class="form-label">Reason for Discount: <span
                                    class="text-danger">*</span></label>
                            <textarea class="form-control" id="discount_reason" name="discount_reason" rows="3"
                                placeholder="Enter reason for giving discount..." required></textarea>
                        </div>

                        <!-- Discount Preview -->
                        <div class="alert alert-info alert-dismissible css-post-admission-discount-apply-224b51" id="discount-preview">
                            <h5 class="alert-heading"><i class="icon fas fa-calculator"></i> Discount Preview:</h5>
                            <table class="table table-sm mb-0">
                                <tbody>
                                    <tr>
                                        <th width="40%">Current Net Fees:</th>
                                        <td class="text-end">₹<span id="preview-current-fees">0.00</span></td>
                                    </tr>
                                    <tr>
                                        <th>New Discount Amount:</th>
                                        <td class="text-end text-warning"><strong>- ₹<span
                                                    id="preview-discount">0.00</span></strong></td>
                                    </tr>
                                    <tr class="border-top">
                                        <th>Updated Net Fees:</th>
                                        <td class="text-end text-success"><strong>₹<span
                                                    id="preview-new-fees">0.00</span></strong></td>
                                    </tr>
                                    <tr>
                                        <th>Updated Pending Fees:</th>
                                        <td class="text-end text-danger"><strong>₹<span
                                                    id="preview-new-pending">0.00</span></strong></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="card-footer">
                        <button type="submit" class="btn btn-success" id="submitBtn">
                            <i class="fas fa-paper-plane"></i>
                            <?php echo $is_accountant ? 'Submit Request' : 'Apply Discount'; ?>
                        </button>
                        <a href="post-admission-discount.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
            </div>
            </form>
        </div>
    </div>
</div>
</div>

<?php include '../../include/footer.php'; ?>

<script>
    $(document).ready(function () {
        const netFees = parseFloat($('#current_net_fees').val());
        const feesPaid = parseFloat($('#current_fees_paid').val());
        const pendingFees = parseFloat($('#current_pending_fees').val());
        const existingDiscount = parseFloat($('#existing_discount').val());
        const isAccountant = <?php echo $is_accountant ? 'true' : 'false'; ?>;

        // Update discount hint based on type
        $('#discount_type').on('change', function () {
            const type = $(this).val();
            if (type === 'fixed') {
                $('#discount_value_container').show();
                $('#smart_options_container').hide();
                $('#discount_value').prop('required', true).prop('readonly', false);
                $('#discount-hint').html('Enter amount in Rupees (₹)');
                $('#discount_value').attr('max', netFees);
            } else if (type === 'percentage') {
                $('#discount_value_container').show();
                $('#smart_options_container').hide();
                $('#discount_value').prop('required', true).prop('readonly', false);
                $('#discount-hint').html('Enter percentage (0-100)');
                $('#discount_value').attr('max', 100);
            } else if (type === 'smart') {
                $('#discount_value_container').show();
                $('#smart_options_container').slideDown();
                $('#discount_value').prop('required', true).prop('readonly', true);
                $('#discount-hint').html('<i class="fas fa-info-circle"></i> Calculated automatically based on selection');
            } else {
                $('#discount-hint').text('Select discount type first');
            }
            calculateDiscount();
        });

        // Listen for smart waiver changes
        $('.smart-fee-check').on('change', function () {
            const target = $(this).val();
            const isChecked = $(this).is(':checked');

            $(`.smart-fee-type[data-target="${target}"]`).prop('disabled', !isChecked);
            $(`.smart-fee-value[data-target="${target}"]`).prop('disabled', !isChecked);

            if (isChecked && !$(`.smart-fee-value[data-target="${target}"]`).val()) {
                $(`.smart-fee-value[data-target="${target}"]`).val(100); // Default to 100%
            }

            if ($('#discount_type').val() === 'smart') {
                calculateDiscount();
            }
        });

        $('.smart-fee-type, .smart-fee-value').on('change input', function () {
            if ($('#discount_type').val() === 'smart') {
                calculateDiscount();
            }
        });

        // Calculate discount on value change
        $('#discount_value').on('input', calculateDiscount);

        function calculateDiscount() {
            const type = $('#discount_type').val();
            let value = parseFloat($('#discount_value').val()) || 0;

            if (!type) {
                $('#discount-preview').hide();
                return;
            }

            let discountAmount = 0;

            if (type === 'smart') {
                let smartTotal = 0;

                $('.smart-fee-check:checked').each(function () {
                    const target = $(this).val();
                    const feeAmount = parseFloat($(this).data('amount')) || 0;
                    const waiverType = $(`.smart-fee-type[data-target="${target}"]`).val();
                    let waiverValue = parseFloat($(`.smart-fee-value[data-target="${target}"]`).val()) || 0;

                    let componentDiscount = 0;
                    if (waiverType === 'percentage') {
                        if (waiverValue > 100) waiverValue = 100;
                        if (waiverValue < 0) waiverValue = 0;
                        componentDiscount = (feeAmount * waiverValue) / 100;
                    } else if (waiverType === 'amount') {
                        if (waiverValue > feeAmount) {
                            waiverValue = feeAmount; // Cap amount to the specific fee amount
                            $(`.smart-fee-value[data-target="${target}"]`).val(waiverValue);
                        }
                        if (waiverValue < 0) waiverValue = 0;
                        componentDiscount = waiverValue;
                    }

                    smartTotal += componentDiscount;
                });

                discountAmount = smartTotal;
                $('#discount_value').val(Math.round(discountAmount));
            } else if (type === 'fixed') {
                discountAmount = value;
                if (discountAmount > pendingFees) {
                    if (typeof showToast === 'function') {
                        showToast('error', 'Invalid Discount', 'Discount amount cannot exceed pending fees!');
                    } else {
                        alert('Discount amount cannot exceed pending fees!');
                    }
                    $('#discount_value').val('');
                    $('#discount-preview').hide();
                    return;
                }
            } else if (type === 'percentage') {
                if (value > 100) {
                    if (typeof showToast === 'function') {
                        showToast('error', 'Invalid Percentage', 'Percentage cannot exceed 100%!');
                    } else {
                        alert('Percentage cannot exceed 100%!');
                    }
                    $('#discount_value').val('');
                    $('#discount-preview').hide();
                    return;
                }
                discountAmount = (pendingFees * value) / 100;
            }

            // Calculate new values
            const newNetFees = netFees - discountAmount;
            const newPending = newNetFees - feesPaid;

            // Update preview
            $('#preview-current-fees').text(Math.round(netFees).toFixed(0));
            $('#preview-discount').text(Math.round(discountAmount).toFixed(0));
            $('#preview-new-fees').text(Math.round(newNetFees).toFixed(0));
            $('#preview-new-pending').text(Math.round(newPending).toFixed(0));
            $('#discount-preview').slideDown();
        }

        // Form submission with confirmation
        $('#discountForm').on('submit', function (e) {
            e.preventDefault();

            const type = $('#discount_type').val();
            const value = $('#discount_value').val();
            let originalReason = $('#discount_reason').val().trim();

            // Remove previous breakdown if it exists
            const breakdownIndex = originalReason.indexOf('\n\nSmart Waiver Breakdown:');
            if (breakdownIndex !== -1) {
                originalReason = originalReason.substring(0, breakdownIndex).trim();
            }

            if (!type || !value || !originalReason) {
                if (typeof showToast === 'function') {
                    showToast('warning', 'Incomplete Form', 'Please fill all required fields!');
                } else {
                    alert('Please fill all required fields!');
                }
                return;
            }

            let finalReason = originalReason;

            // Append smart waiver breakdown to reason
            if (type === 'smart') {
                let breakdown = '\n\nSmart Waiver Breakdown:';
                let hasSelections = false;
                $('.smart-fee-check:checked').each(function () {
                    hasSelections = true;
                    const target = $(this).val();
                    const feeName = $(this).siblings('label').text().split(' (')[0].trim();
                    const waiverType = $(`.smart-fee-type[data-target="${target}"]`).val();
                    let waiverValue = parseFloat($(`.smart-fee-value[data-target="${target}"]`).val()) || 0;

                    if (waiverType === 'percentage') {
                        breakdown += `\n- ${feeName}: ${waiverValue}%`;
                    } else if (waiverType === 'amount') {
                        breakdown += `\n- ${feeName}: \u20B9${waiverValue}`;
                    }
                });

                if (hasSelections) {
                    finalReason += breakdown;
                } else {
                    alert('Please select at least one fee component for the Smart Waiver.');
                    return;
                }
            }

            const discountAmount = $('#preview-discount').text();
            const newNetFees = $('#preview-new-fees').text();

            if (confirm(`Confirm Discount ${isAccountant ? 'Request' : 'Application'}
            
Discount Amount: \u20B9${discountAmount}
New Net Fees (Potential): \u20B9${newNetFees}
Reason: ${finalReason}

Are you sure you want to ${isAccountant ? 'propose' : 'apply'} this discount?`)) {
                $('#discount_reason').val(finalReason); // Update textarea right before submit
                $('#submitBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');
                this.submit();
            } else {
                $('#discount_reason').val(originalReason); // Revert textarea if cancelled
            }
        });
    });
</script>