<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../dashboard/student_dashboard.php');
    exit;
}

$student_id = $_POST['student_id'] ?? null;
$fee_component = $_POST['fee_component'] ?? null;
$fee_label = $_POST['fee_label'] ?? '';
$amount = $_POST['amount'] ?? null;
$gateway = $_POST['gateway'] ?? null;
$installment_id = isset($_POST['installment_id']) ? intval($_POST['installment_id']) : null;

// Validation
if (empty($student_id) || empty($fee_component) || empty($amount) || empty($gateway)) {
    set_flash_message('error', "Missing required payment information");
    header('Location: ../dashboard/student_dashboard.php');
    exit;
}

// Generate proper fee label if not provided
if (empty($fee_label)) {
    $fee_labels = [
        'school_fee' => 'School Fee',
        'trust_facilities_fee' => 'Trust Facilities Fee',
        'tuition_fee_part2' => 'Tuition Fee Part 2',
        'transport_fee' => 'Transport Fee'
    ];
    $fee_label = $fee_labels[$fee_component] ?? 'Fee Payment';

    // Add installment info if applicable
    if ($installment_id) {
        $stmt_check = $conn->prepare("SELECT installment_number FROM tbl_fee_installments WHERE id = ?");
        $stmt_check->execute([$installment_id]);
        $inst_data = $stmt_check->fetch();
        if ($inst_data) {
            $fee_label .= ' Installment ' . $inst_data['installment_number'];
        }
    }
}

// Validate fee component
if (!in_array($fee_component, ['school_fee', 'trust_facilities_fee', 'tuition_fee_part2', 'hostel_fee', 'hostel_security', 'transport_fee'])) {
    set_flash_message('error', "Invalid fee component");
    header('Location: ../dashboard/student_dashboard.php');
    exit;
}

// Verify student ID matches parent session
if ($student_id != $_SESSION['active_student_id'] && $student_id != $_SESSION['student_id']) {
    set_flash_message('error', "Invalid student ID");
    header('Location: ../dashboard/student_dashboard.php');
    exit;
}

// Fetch student details and current term
$student_data = $dbOps->customSelect("SELECT s.*, 
                                       CASE WHEN p.id IS NOT NULL OR s.token_fees_paid = 1 THEN 1 ELSE 0 END as token_fees_paid,
                                       e.current_term_id 
                                       FROM tbl_gm_std_registration s
                                       LEFT JOIN (
                                           SELECT * FROM tbl_payments
                                       ) p ON s.id = p.student_id AND p.payment_type = 'token_fee' AND p.status = 'paid'
                                       LEFT JOIN tbl_enrolled_students e ON s.id = e.registration_id AND e.is_active = 1
                                       WHERE s.id = ?", [$student_id]);
$student = !empty($student_data) ? $student_data[0] : null;

if (!$student) {
    set_flash_message('error', "Student not found");
    header('Location: ../dashboard/student_dashboard.php');
    exit;
}

// Check if token fee is paid
if (!$student['token_fees_paid']) {
    set_flash_message('error', "Please pay token fee first");
    header('Location: token-fee-payment.php');
    exit;
}

// Check if already paid
$current_term_id = $student['current_term_id'] ?? 1;

if ($installment_id) {
    // Check if this specific installment is paid
    $result = $dbOps->customSelectOne(
        "SELECT COUNT(*) as count FROM (
             SELECT * FROM tbl_payments
         ) p
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
        "SELECT COUNT(*) as count FROM (
             SELECT * FROM tbl_payments
         ) p
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

// Store payment info in session for gateway processing
$_SESSION['pending_payment'] = [
    'student_id' => $student_id,
    'fee_component' => $fee_component,
    'fee_label' => $fee_label,
    'amount' => $amount,
    'payment_type' => 'pending_fee',
    'installment_id' => $installment_id
];

// Route to appropriate gateway - Only EaseBuzz is supported
if ($gateway !== 'easebuzz') {
    set_flash_message('error', "Only EaseBuzz payment gateway is supported");
    header('Location: pending-fee-payment.php?component=' . urlencode($fee_component));
    exit;
}

// Set variables for unified handler
$payment_type = $fee_component;
$product_info = $fee_label;

// Include EaseBuzz payment processing for pending fees
include '../payments/easebuzz-initiate.php';
