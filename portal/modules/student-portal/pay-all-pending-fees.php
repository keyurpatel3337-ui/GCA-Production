<?php
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/format_helper.php';

// Check if student is logged in
if (!isset($_SESSION['student_id']) || $_SESSION['user_role'] !== 'student') {
    header('Location: student-login.php');
    exit;
}

$student_id = $_SESSION['student_id'];

// Fetch student and payment details
$students = $dbOps->customSelect("SELECT s.*, 
                       CASE WHEN p.id IS NOT NULL OR s.token_fees_paid = 1 THEN 1 ELSE 0 END as token_fees_paid,
                       s.student_name, s.surname, s.fathers_name, s.mob, s.aadhaar,
                       c.course_name,
                       fc.school_fee, fc.trust_facilities_fee, fc.tuition_fee_part1, fc.tuition_fee_part2,
                       s.scholarship_amount, s.additional_scholarship_amount,
                       e.enrollment_id,
                       e.current_term_id,
                       e.post_admission_discount_amount
                       FROM tbl_gm_std_registration s
                       LEFT JOIN (
                           SELECT * FROM tbl_payments
                       ) p ON s.id = p.student_id AND p.payment_type = 'token_fee' AND p.status = 'paid'
                       LEFT JOIN tbl_courses c ON s.course_id = c.id
                       LEFT JOIN tbl_fee_config fc ON s.course_id = fc.course_id 
                            AND s.medium_id = fc.medium_id 
                            AND s.group_id = fc.group_id 
                            AND fc.is_active = 1
                       LEFT JOIN tbl_enrolled_students e ON s.id = e.registration_id AND e.is_active = 1
                       WHERE s.id = ?", [$student_id]);
$student = !empty($students) ? $students[0] : null;

if (!$student) {
    set_flash_message('error', "Student record not found");
    header('Location: student-login.php');
    exit;
}

// Block payment for restricted students
$std_val = isset($student['standard']) ? intval($student['standard']) : 0;
$course_val = isset($student['course_id']) ? intval($student['course_id']) : 0;
if ($std_val == 11 && ($course_val == 1 || $course_val == 2)) {
    set_flash_message('error', 'Please pay your fee at Account Department.');
    header('Location: ../dashboard/student_dashboard.php');
    exit;
}

// Token fee must be paid first
if (!$student['token_fees_paid']) {
    set_flash_message('error', "Please pay token fee first");
    header('Location: token-fee-payment.php');
    exit;
}

// Use central fee helper for accurate calculations
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/fee_helper.php';

// Check which fees are already paid for the CURRENT term
$current_term_id = $student['current_term_id'] ?? 1;

// Get comprehensive fee summary strictly for the current term
$fee_summary = calculateStudentFeeSummary($conn, $student_id, true, $current_term_id);
if (!$fee_summary) {
    set_flash_message('error', "Could not calculate fee details");
    header('Location: ../dashboard/student_dashboard.php');
    exit;
}

$allocations = $fee_summary['allocations'];

// Build list of pending fees from centralized allocations
$pending_fees = [];
$total_amount = 0;

foreach ($allocations as $component => $data) {
    $pending = floatval($data['pending_amount'] ?? 0);
    
    // Special handling for hostel: match the label used in pending-fee-payment.php
    $label = $data['label'];
    if ($component === 'hostel_security') {
        $label = 'Hostel Security Deposit';
    }

    if ($pending > 0) {
        $org = 'Gyanmanjari Career Academy'; // Default
        if ($component === 'school_fee') {
            $org = $student['school_name'] ?? 'School';
        } elseif (in_array($component, ['trust_facilities_fee', 'hostel_fee', 'hostel_security', 'transport_fee'])) {
            $org = 'Mahatma Seva Trust';
        }

        $pending_fees[] = [
            'component' => $component,
            'label' => $label,
            'amount' => $pending,
            'org' => $org
        ];
        $total_amount += $pending;
    }
}

// If no pending fees or only one fee, redirect to appropriate page
if (count($pending_fees) == 0) {
    set_flash_message('info', "You have no pending fees");
    header('Location: ../dashboard/student_dashboard.php');
    exit;
} elseif (count($pending_fees) == 1) {
    // Redirect to single payment page
    $redirect_url = 'pending-fee-payment.php?component=' . $pending_fees[0]['component'];
    if (!empty($pending_fees[0]['installment_id'])) {
        $redirect_url .= '&installment_id=' . intval($pending_fees[0]['installment_id']);
    }
    header('Location: ' . $redirect_url);
    exit;
}

$page_title = "Pay All Pending Fees";
$page_breadcrumb = "Pay All";

include '../../include/header.php';
?>
<script type="text/javascript">
    window.history.pushState(null, null, window.location.href);
    window.onpopstate = function() {
        window.history.pushState(null, null, window.location.href);
    };
</script>
<?php
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>



<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <?php display_flash_messages(); ?>

            <div class="card card-outline card-success shadow-lg border-0 overflow-hidden">
                <div class="card-header text-center py-4 bg-light border-bottom">
                    <i
                        class="fas fa-file-invoice-dollar fa-3x text-success mb-3 animate__animated animate__pulse animate__infinite"></i>
                    <h4 class="fw-bold mb-1">Pay All Pending Fees</h4>
                    <p class="text-muted mb-0">Complete payment for <?php echo count($pending_fees); ?> pending fee
                        components</p>
                </div>

                <div class="card-body p-4">
                    <!-- Student Details -->
                    <div class="row g-4 mb-4 pb-4 border-bottom">
                        <div class="col-md-6">
                            <h6 class="text-uppercase text-muted fw-bold mb-3 small"><i
                                    class="fas fa-user-graduate me-2 text-success"></i>Student Details</h6>
                            <div class="mb-2">
                                <span class="text-muted small d-block">Full Name</span>
                                <span
                                    class="fw-bold fs-6"><?php echo htmlspecialchars($student['surname'] . ' ' . $student['student_name'] . ' ' . $student['fathers_name'] ?? ''); ?></span>
                            </div>
                            <div class="mb-2">
                                <span class="text-muted small d-block">Aadhaar Number</span>
                                <span class="fw-bold fs-6"><?php echo htmlspecialchars($student['aadhaar'] ?? ''); ?></span>
                            </div>
                            <div>
                                <span class="text-muted small d-block">Standard</span>
                                <span
                                    class="badge bg-success-subtle text-success border border-success-subtle"><?php echo htmlspecialchars($student['course_name'] ?? ''); ?></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-uppercase text-muted fw-bold mb-3 small"><i
                                    class="fas fa-receipt me-2 text-success"></i>Payment Summary</h6>
                            <div class="mb-2">
                                <span class="text-muted small d-block">Number of Components</span>
                                <span class="fw-bold fs-6"><?php echo count($pending_fees); ?> Fee Components</span>
                            </div>
                        </div>
                    </div>

                    <!-- Fee Breakdown -->
                    <h6 class="text-uppercase text-muted fw-bold mb-3 small"><i
                            class="fas fa-list me-2 text-success"></i>Fee Breakdown</h6>
                    <div class="table-responsive mb-4">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th width="5%">#</th>
                                    <th width="30%">Fee Component</th>
                                    <th width="20%" class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_fees as $index => $fee): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><?php echo htmlspecialchars($fee['label'] ?? ''); ?></td>
                                        <td class="text-end">₹<?php echo formatIndianCurrency($fee['amount']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-dark">
                                <tr>
                                    <th colspan="2" class="text-end">Total Amount:</th>
                                    <th class="text-end">₹<?php echo formatIndianCurrency($total_amount); ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- Amount Card -->
                    <div class="amount-card bg-success text-white p-4 rounded-4 text-center mb-4 shadow-sm"
                        style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                        <span class="text-uppercase small fw-bold opacity-75 d-block mb-1">Total Payable
                            Amount</span>
                        <h1 class="display-3 fw-bold mb-0">₹<?php echo formatIndianCurrency($total_amount); ?>
                        </h1>
                        <small class="opacity-75 tracking-wide fw-medium">ALL PENDING FEES</small>
                    </div>

                    <!-- Payment Method -->
                    <form id="paymentForm" action="process-all-pending-payment.php" method="POST">
                        <h6 class="text-uppercase text-muted fw-bold mb-3 small"><i
                                class="fas fa-wallet me-2 text-success"></i>Payment Method</h6>

                        <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
                        <input type="hidden" name="total_amount" value="<?php echo $total_amount; ?>">
                        <?php foreach ($pending_fees as $index => $fee): ?>
                            <input type="hidden" name="components[]"
                                value="<?php echo htmlspecialchars($fee['component'] ?? ''); ?>">
                            <input type="hidden" name="amounts[]" value="<?php echo $fee['amount']; ?>">
                            <input type="hidden" name="labels[]" value="<?php echo htmlspecialchars($fee['label'] ?? ''); ?>">
                            <?php if (isset($fee['installment_id'])): ?>
                                <input type="hidden" name="installment_ids[]" value="<?php echo $fee['installment_id']; ?>">
                            <?php else: ?>
                                <input type="hidden" name="installment_ids[]" value="">
                            <?php endif; ?>
                        <?php endforeach; ?>

                        <div
                            class="card border-success bg-success-subtle mb-4 p-3 cursor-pointer hover-shadow transition">
                            <div class="d-flex align-items-center">
                                <div class="form-check me-3">
                                    <input class="form-check-input" type="radio" name="gateway" id="gateway_easebuzz"
                                        value="easebuzz" checked>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1 fw-bold">EaseBuzz Payment Gateway</h6>
                                    <p class="mb-0 text-muted small">Cards, UPI, Net Banking, and Wallets</p>
                                </div>
                                <img src="https://easebuzz.in/static/base/assets/images/easebuzz-logo.png"
                                    alt="Easebuzz" height="25" class="opacity-75">
                            </div>
                        </div>

                        <div class="d-grid gap-3">
                            <button type="submit" class="btn btn-success btn-lg py-3 fw-bold shadow-sm rounded-3">
                                <i class="fas fa-lock-alt me-2"></i> Proceed to Secure Payment
                            </button>
                            <a href="../dashboard/student_dashboard.php" class="btn btn-outline-secondary py-2 fw-medium rounded-3">
                                <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                            </a>
                        </div>
                    </form>

                    <div class="mt-4 p-3 bg-light rounded-3 border">
                        <div class="d-flex align-items-center">
                            <div class="bg-warning-subtle p-2 rounded-circle me-3">
                                <i class="fas fa-shield-check text-warning fs-4"></i>
                            </div>
                            <div class="small text-muted">
                                <strong class="text-dark">Fully Encrypted:</strong> Your payment information is
                                processed through EaseBuzz's PCI-DSS compliant secure servers. All
                                <?php echo count($pending_fees); ?> fees will be processed in a single secure
                                transaction.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../include/footer.php'; ?>