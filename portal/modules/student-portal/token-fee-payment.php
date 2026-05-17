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

// Initialize DatabaseOperations
$op = new Operation();

// Fetch student and payment details
$students = $op->customSelect("SELECT s.*, 
                       COALESCE(p_pend.payment_mode, 'online') as token_payment_mode,
                       CASE WHEN p.id IS NOT NULL OR s.token_fees_paid = 1 THEN 1 ELSE 0 END as token_fees_paid,
                       s.student_name, s.surname, s.fathers_name, s.mob, s.aadhaar,
                       c.course_name,
                       fc.school_fee, fc.trust_facilities_fee, fc.tuition_fee_part1,
                       pp.payment_gateway, pp.transaction_id as pending_transaction_id
                       FROM tbl_gm_std_registration s
                       LEFT JOIN tbl_payments p ON s.id = p.student_id AND (p.payment_type = 'token_fee' OR p.fee_component = 'tuition_fee_part1') AND p.status = 'paid'
                       LEFT JOIN tbl_payments p_pend ON s.id = p_pend.student_id AND p_pend.payment_type = 'token_fee' AND p_pend.status = 'pending'
                       LEFT JOIN tbl_courses c ON s.course_id = c.id
                       LEFT JOIN tbl_fee_config fc ON s.course_id = fc.course_id 
                            AND s.medium_id = fc.medium_id 
                            AND s.group_id = fc.group_id 
                            AND fc.is_active = 1
                       LEFT JOIN tbl_pending_payments pp ON s.id = pp.student_id AND pp.payment_type = 'token_fee'
                       WHERE s.id = ?", [$student_id]);
$student = !empty($students) ? $students[0] : null;

if (!$student) {
    set_flash_message('error', "Student record not found");
    header('Location: student-login.php');
    exit;
}

// Calculate actual token amount (Only Tuition Part 1 with 18% GST)
$tuition_part1 = floatval($student['tuition_fee_part1']);
$gst_part1 = $tuition_part1 * 0.18;
$actual_token_amount = $tuition_part1 + $gst_part1; // Rs. 11,800

// Override the token_amount with calculated value
$student['token_amount'] = $actual_token_amount;

// If already paid, redirect to dashboard
if ($student['token_fees_paid']) {
    header('Location: ../dashboard/student_dashboard.php');
    exit;
}

// If not online payment mode, redirect back
if ($student['token_payment_mode'] !== 'online') {
    set_flash_message('error', "Online payment not enabled. Please contact accounts department.");
    header('Location: student-login.php');
    exit;
}

$page_title = "Token Fee Payment";
$page_breadcrumb = "Fee Payment";

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
        <div class="col-md-8">
            <?php if (isset($_SESSION['error_msg'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($_SESSION['error_msg'] ?? '');
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['info_msg'])): ?>
                <div class="alert alert-info alert-dismissible fade show">
                    <i class="fas fa-info-circle me-2"></i> <?php echo htmlspecialchars($_SESSION['info_msg'] ?? '');
                    unset($_SESSION['info_msg']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card card-outline card-primary shadow">
                <div class="card-header text-center py-4 bg-light">
                    <i class="fas fa-money-bill-wave fa-3x text-primary mb-3"></i>
                    <h4 class="fw-bold mb-1">Token Fee Payment</h4>
                    <p class="text-muted mb-0">Complete your payment to activate your account</p>
                </div>

                <div class="card-body">
                    <!-- Student Information -->
                    <div class="mb-4">
                        <h5 class="border-bottom pb-2 mb-3"><i class="fas fa-user-circle me-2 text-primary"></i>Student
                            Details</h5>
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="text-muted small d-block">Full Name</label>
                                <span
                                    class="fw-bold"><?php echo htmlspecialchars($student['surname'] . ' ' . $student['student_name'] . ' ' . $student['fathers_name'] ?? ''); ?></span>
                            </div>
                            <div class="col-sm-6">
                                <label class="text-muted small d-block">Aadhaar / Mobile</label>
                                <span class="fw-bold"><?php echo htmlspecialchars($student['aadhaar'] ?? ''); ?> /
                                    <?php echo htmlspecialchars($student['mob'] ?? ''); ?></span>
                            </div>
                            <div class="col-12">
                                <label class="text-muted small d-block">Standard</label>
                                <span
                                    class="fw-bold text-primary"><?php echo htmlspecialchars($student['course_name'] ?? ''); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Amount to Pay -->
                    <div class="bg-gradient-primary text-white p-4 rounded-3 text-center mb-4 shadow-sm">
                        <p class="mb-1 opacity-75">Admission Token Fee Amount</p>
                        <h1 class="display-4 fw-bold mb-0">
                            ₹<?php echo formatIndianCurrency($student['token_amount']); ?></h1>
                        <small class="opacity-75">One-time payment for admission confirmation</small>
                    </div>

                    <!-- Payment Gateway Selection -->
                    <form id="paymentForm" action="process-token-payment.php" method="POST">
                        <h5 class="mb-3"><i class="fas fa-credit-card me-2 text-primary"></i>Select Payment Method
                        </h5>

                        <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
                        <input type="hidden" name="amount" value="<?php echo $student['token_amount']; ?>">
                        <input type="hidden" name="gateway" value="easebuzz">

                        <div class="list-group mb-4">
                            <button type="button"
                                class="list-group-item list-group-item-action border-primary bg-light p-3">
                                <div class="d-flex w-100 align-items-center">
                                    <div class="form-check me-3">
                                        <input class="form-check-input" type="radio" name="gateway_radio"
                                            id="gateway_easebuzz" value="easebuzz" checked>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1 fw-bold">EaseBuzz Payment Gateway</h6>
                                        <p class="mb-0 text-muted small">Pay securely via UPI, Cards, Net Banking,
                                            or Wallets</p>
                                    </div>
                                    <i class="fas fa-shield-alt text-success fs-4"></i>
                                </div>
                            </button>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg w-100 py-3 fw-bold shadow-sm">
                            <i class="fas fa-lock me-2"></i> Proceed to Secure Payment
                        </button>
                    </form>

                    <div class="alert alert-warning mt-4 mb-0 border-0 bg-light-warning">
                        <small class="d-block">
                            <i class="fas fa-info-circle me-1 text-warning"></i>
                            <strong>Secure Payment:</strong> Your transaction is encrypted and secure. You will be
                            redirected to the EaseBuzz secure payment page.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../include/footer.php'; ?>