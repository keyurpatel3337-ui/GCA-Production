<?php
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/format_helper.php';

// Tighten access: Only parents are allowed to manage payments
$is_student = isset($_SESSION['is_student_login']) && $_SESSION['is_student_login'] === true;
$is_parent = isset($_SESSION['is_parent_login']) && $_SESSION['is_parent_login'] === true;

if ($is_student) {
    $_SESSION['error'] = 'Access Denied: Fees and Wallet are managed exclusively by Parents.';
    header('Location: ../dashboard/student_dashboard.php');
    exit;
}

if (!$is_parent) {
    header('Location: ../../parent-login.php');
    exit;
}

$student_id = $_SESSION['active_student_id'] ?? $_SESSION['student_id'];
$fee_component = $_REQUEST['component'] ?? '';
$installment_id = isset($_REQUEST['installment_id']) ? intval($_REQUEST['installment_id']) : null;

// Validate fee component
if (!in_array($fee_component, ['school_fee', 'trust_facilities_fee', 'tuition_fee_part2', 'transport_fee'])) {
    set_flash_message('error', "Invalid fee component");
    header('Location: ../dashboard/student_dashboard.php');
    exit;
}

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
                       LEFT JOIN tbl_payments p ON s.id = p.student_id AND p.payment_type = 'token_fee' AND p.status = 'paid'
                       LEFT JOIN tbl_courses c ON s.course_id = c.id
                       LEFT JOIN tbl_fee_config fc ON s.course_id = fc.course_id 
                            AND s.medium_id = fc.medium_id 
                            AND s.group_id = fc.group_id 
                            AND fc.is_active = 1
                       LEFT JOIN tbl_enrolled_students e ON s.id = e.registration_id AND e.is_active = 1
                       WHERE s.id = ?", [$student_id]);
$student = !empty($students) ? $students[0] : null;

// Log fetched data for debugging
error_log("Pending Fee Payment - Student ID: $student_id, Component: $fee_component, Enrollment ID: " . ($student['enrollment_id'] ?? 'null'));

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

// Token fee must be paid first (Bypass for Re-NEET students)
if (!$student['token_fees_paid'] && $student['course_id'] != 6) {
    set_flash_message('error', "Please pay token fee first");
    header('Location: token-fee-payment.php');
    exit;
}

// Use central fee helper for accurate calculations
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/fee_helper.php';

// Get comprehensive fee summary strictly for the current term
$fee_summary = calculateStudentFeeSummary($conn, $student_id, false, $student['current_term_id'] ?? 1);
if (!$fee_summary) {
    set_flash_message('error', "Could not calculate fee details");
    header('Location: ../dashboard/student_dashboard.php');
    exit;
}

$allocations = $fee_summary['allocations'];

// Calculate fee amount and details based on component
$fee_label = '';
$fee_organization = '';
$fee_amount = 0;

switch ($fee_component) {
    case 'school_fee':
        $fee_label = 'School Fee';
        // Get school name
        $stmt_school = $conn->prepare("SELECT school_name FROM tbl_schools WHERE id = ?");
        $stmt_school->execute([$student['school_id']]);
        $school_data = $stmt_school->fetch();
        $fee_organization = $school_data['school_name'] ?? 'Gyanmanjari';

        $fee_amount = floatval($allocations['school_fee']['pending_amount'] ?? $student['school_fee']);
        break;

    case 'trust_facilities_fee':
        $fee_label = 'Trust Facilities Fee';
        $fee_organization = 'Mahatma Seva Trust';

        $fee_amount = floatval($allocations['trust_facilities_fee']['pending_amount'] ?? $student['trust_facilities_fee']);
        break;

    case 'tuition_fee_part2':
        $fee_label = 'Tuition Fee Part 2';
        $fee_organization = 'Gyanmanjari Career Academy';

        // If installment ID is provided, get installment amount
        if ($installment_id) {
            $stmt_inst = $conn->prepare("SELECT due_amount, installment_number FROM tbl_fee_installments WHERE id = ?");
            $stmt_inst->execute([$installment_id]);
            $installment_data = $stmt_inst->fetch();

            if ($installment_data) {
                $fee_amount = floatval($installment_data['due_amount']);
                $fee_label .= ' - Installment #' . $installment_data['installment_number'];
            } else {
                set_flash_message('error', "Invalid installment ID");
                header('Location: ../dashboard/student_dashboard.php');
                exit;
            }
        } else {
            $fee_amount = floatval($allocations['tuition_fee_part2']['pending_amount'] ?? 0);
            error_log("Pending Fee Payment - Tuition Part 2 Calculation from helper: Final=$fee_amount");
        }
        break;

    case 'hostel_security':
        $fee_label = 'Hostel Security Deposit';
        $fee_organization = 'Mahatma Seva Trust';

        $fee_amount = floatval($allocations['hostel_security']['pending_amount'] ?? 0);

        if ($fee_amount <= 0) {
            set_flash_message('error', "Unable to calculate hostel security deposit. Please contact administration.");
            header('Location: ../dashboard/student_dashboard.php');
            exit;
        }
        break;

    case 'transport_fee':
        $fee_label = $allocations['transport_fee']['label'] ?? 'Transport Fee';
        $fee_organization = 'Mahatma Seva Trust';

        $fee_amount = floatval($allocations['transport_fee']['pending_amount'] ?? 0);

        if ($fee_amount <= 0) {
            set_flash_message('error', "No pending transport fee found. Please contact administration.");
            header('Location: ../dashboard/student_dashboard.php');
            exit;
        }

        // Use transport config from helper for Monthly selector logic
        $ts = $fee_summary['transport_config'] ?? null;
        $transport_is_monthly = ($ts && $ts['timeline'] === 'Monthly');
        $transport_monthly_rate = $ts['monthly_rate'] ?? 0;
        break;
}

// Check if already paid
$current_term_id = $student['current_term_id'] ?? 1;

if ($installment_id) {
    // Check if this specific installment is paid
    $result = $dbOps->customSelectOne(
        "SELECT COUNT(*) as count FROM tbl_payments 
                           WHERE student_id = ? AND fee_component = ? AND installment_id = ? AND status = 'paid' AND term_id = ?",
        [$student_id, $fee_component, $installment_id, $current_term_id]
    );
    $payment_count = $result['count'];

    if ($payment_count > 0) {
        $_SESSION['info_msg'] = "This installment has already been paid";
        header('Location: ../dashboard/student_dashboard.php');
        exit;
    }
} else {
    // Check if full fee is paid
    $result = $dbOps->customSelectOne(
        "SELECT COUNT(*) as count FROM tbl_payments 
                           WHERE student_id = ? AND fee_component = ? AND status = 'paid' AND term_id = ?",
        [$student_id, $fee_component, $current_term_id]
    );
    $payment_count = $result['count'];

    if ($payment_count > 0) {
        $_SESSION['info_msg'] = "This fee has already been paid";
        header('Location: ../dashboard/student_dashboard.php');
        exit;
    }
}

$page_title = $fee_label . " Payment";
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
            <?php display_flash_messages(); ?>

            <div class="card card-outline card-success shadow-lg border-0 overflow-hidden">
                <div class="card-header text-center py-4 bg-light border-bottom">
                    <i
                        class="fas fa-file-invoice-dollar fa-3x text-success mb-3 animate__animated animate__pulse animate__infinite"></i>
                    <h4 class="fw-bold mb-1"><?php echo htmlspecialchars($fee_label ?? ''); ?> Payment</h4>
                    <p class="text-muted mb-0">Securely complete your fee payment</p>
                </div>

                <div class="card-body p-4">
                    <div class="row g-4 mb-4">
                        <!-- Student Section -->
                        <div class="col-md-6 border-end">
                            <h6 class="text-uppercase text-muted fw-bold mb-3 small tracking-wider"><i
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

                        <!-- Payment Section -->
                        <div class="col-md-6">
                            <h6 class="text-uppercase text-muted fw-bold mb-3 small tracking-wider"><i
                                    class="fas fa-receipt me-2 text-success"></i>Payment Details</h6>
                            <div class="mb-2">
                                <span class="text-muted small d-block">Fee Component</span>
                                <span class="fw-bold fs-6"><?php echo htmlspecialchars($fee_label ?? ''); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Amount Card -->
                    <div class="amount-card bg-success text-white p-4 rounded-4 text-center mb-4 shadow-sm"
                        style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                        <span class="text-uppercase small fw-bold opacity-75 d-block mb-1">Total Payable
                            Amount</span>
                        <h1 class="display-3 fw-bold mb-0">₹<span id="display_amount"><?php echo formatIndianCurrency($fee_amount); ?></span></h1>
                        <small class="opacity-75 tracking-wide fw-medium" id="display_label"><?php echo strtoupper($fee_label); ?></small>
                    </div>

                    <?php if ($fee_component === 'transport_fee' && isset($transport_is_monthly) && $transport_is_monthly): ?>
                        <div class="card bg-light border-0 shadow-sm mb-4">
                            <div class="card-body">
                                <h6 class="fw-bold mb-3 small text-muted text-uppercase"><i class="fas fa-calendar-alt me-2 text-success"></i>Select Months to Pay</h6>
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <select class="form-select" id="transport_months">
                                            <?php 
                                            $max_months = ceil($fee_amount / $transport_monthly_rate);
                                            for ($i = 1; $i <= $max_months; $i++) {
                                                echo "<option value='$i'>$i Month" . ($i > 1 ? 's' : '') . "</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mt-2 mt-md-0">
                                        <div class="text-muted small">
                                            Rate: ₹<?php echo formatIndianCurrency($transport_monthly_rate); ?> / month
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const monthsSelector = document.getElementById('transport_months');
                            const amountInput = document.querySelector('input[name="amount"]');
                            const displayAmount = document.getElementById('display_amount');
                            const monthlyRate = <?php echo $transport_monthly_rate; ?>;
                            const totalPending = <?php echo $fee_amount; ?>;
                            const maxMonths = <?php echo $max_months; ?>;

                            monthsSelector.addEventListener('change', function() {
                                const months = parseInt(this.value);
                                let newAmount = months * monthlyRate;
                                
                                // Cap at total pending and handle last month rounding
                                if (months >= maxMonths) {
                                    newAmount = totalPending;
                                }
                                
                                amountInput.value = newAmount;
                                displayAmount.innerText = new Intl.NumberFormat('en-IN').format(newAmount);
                            });

                            // Initialize with 1 month
                            const initialAmount = Math.min(monthlyRate, totalPending);
                            amountInput.value = initialAmount;
                            displayAmount.innerText = new Intl.NumberFormat('en-IN').format(initialAmount);
                        });
                        </script>
                    <?php endif; ?>

                    <!-- Gateway Selection -->
                    <form id="paymentForm" action="process-pending-payment.php" method="POST">
                        <h6 class="text-uppercase text-muted fw-bold mb-3 small tracking-wider"><i
                                class="fas fa-wallet me-2 text-success"></i>Payment Method</h6>

                        <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
                        <input type="hidden" name="fee_component"
                            value="<?php echo htmlspecialchars($fee_component ?? ''); ?>">
                        <input type="hidden" name="fee_label" value="<?php echo htmlspecialchars($fee_label ?? ''); ?>">
                        <input type="hidden" name="amount" value="<?php echo $fee_amount; ?>">
                        <input type="hidden" name="gateway" value="easebuzz">
                        <?php if ($installment_id): ?>
                            <input type="hidden" name="installment_id" value="<?php echo $installment_id; ?>">
                        <?php endif; ?>

                        <div
                            class="card border-success bg-success-subtle mb-4 p-3 cursor-pointer hover-shadow transition">
                            <div class="d-flex align-items-center">
                                <div class="form-check me-3">
                                    <input class="form-check-input" type="radio" name="gateway_radio"
                                        id="gateway_easebuzz" value="easebuzz" checked>
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
                                processed through EaseBuzz's PCI-DSS compliant secure servers. We do not store your
                                card or bank details.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../include/footer.php'; ?>