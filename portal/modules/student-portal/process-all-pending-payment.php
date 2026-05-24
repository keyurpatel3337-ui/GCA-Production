<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/format_helper.php';

// Check if student is logged in
if (!isset($_SESSION['student_id']) || $_SESSION['user_role'] !== 'student') {
    header('Location: student-login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../dashboard/student_dashboard.php');
    exit;
}

$student_id = $_POST['student_id'] ?? null;
$components = $_POST['components'] ?? [];
$amounts = $_POST['amounts'] ?? [];
$labels = $_POST['labels'] ?? [];
$installment_ids = $_POST['installment_ids'] ?? [];
$total_amount = $_POST['total_amount'] ?? null;
$gateway = $_POST['gateway'] ?? 'easebuzz';

// Validation
if (empty($student_id) || empty($components) || empty($amounts) || empty($total_amount)) {
    set_flash_message('error', "Missing required payment information");
    header('Location: ../dashboard/student_dashboard.php');
    exit;
}

// Verify arrays have same length
if (count($components) !== count($amounts) || count($components) !== count($labels)) {
    set_flash_message('error', "Invalid payment data");
    header('Location: ../dashboard/student_dashboard.php');
    exit;
}

// Must have at least 1 component
if (count($components) == 0) {
    set_flash_message('error', "No fee components to pay");
    header('Location: ../dashboard/student_dashboard.php');
    exit;
}

// Verify student ID matches session
if ($student_id != $_SESSION['student_id']) {
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

// Validate and verify each component
$payment_items = [];
$verified_total = 0;
$split_amounts = [];
$component_breakdown = [];

// Fetch fee configuration with split labels
$stmt = $conn->prepare("SELECT fc.school_fee, fc.trust_facilities_fee, fc.tuition_fee_part2,
                        fc.school_fee_label, fc.trust_fee_label, fc.tuition_fee_label
                        FROM tbl_fee_config fc
                        INNER JOIN tbl_gm_std_registration s ON s.course_id = fc.course_id 
                            AND s.medium_id = fc.medium_id 
                            AND s.group_id = fc.group_id
                        WHERE s.id = ? AND fc.is_active = 1");
$stmt->execute([$student_id]);
$fee_config = $stmt->fetch(PDO::FETCH_ASSOC);

for ($i = 0; $i < count($components); $i++) {
    $component = $components[$i];
    $amount = floatval($amounts[$i]);
    $label = $labels[$i];
    $installment_id = !empty($installment_ids[$i]) ? intval($installment_ids[$i]) : null;

    // Validate fee component
    if (!in_array($component, ['school_fee', 'trust_facilities_fee', 'tuition_fee_part1', 'tuition_fee_part2', 'tuition_fee', 'hostel_security', 'token_fee', 'transport_fee'])) {
        set_flash_message('error', "Invalid fee component: $component");
        header('Location: ../dashboard/student_dashboard.php');
        exit;
    }

    // Verify amount is positive
    if ($amount <= 0) {
        set_flash_message('error', "Invalid amount for: $label");
        header('Location: ../dashboard/student_dashboard.php');
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
            [$student_id, $component, $installment_id, $current_term_id]
        );

        if ($result['count'] > 0) {
            set_flash_message('error', "$label has already been paid");
            header('Location: ../dashboard/student_dashboard.php');
            exit;
        }
    } else {
        // Check if full fee is paid
        $result = $dbOps->customSelectOne(
            "SELECT COUNT(*) as count FROM (
                 SELECT * FROM tbl_payments
             ) p
                           WHERE student_id = ? AND fee_component = ? AND (installment_id IS NULL OR installment_id = 0) AND status = 'paid' AND term_id = ?",
            [$student_id, $component, $current_term_id]
        );

        if ($result['count'] > 0) {
            set_flash_message('error', "$label has already been paid");
            header('Location: ../dashboard/student_dashboard.php');
            exit;
        }
    }

    // Add to payment items
    $payment_items[] = [
        'component' => $component,
        'label' => $label,
        'amount' => $amount,
        'installment_id' => $installment_id
    ];

    if (!isset($component_breakdown[$component])) {
        $component_breakdown[$component] = 0;
    }
    $component_breakdown[$component] += $amount;

    $verified_total += $amount;

    // Determine split label for this component
    if ($fee_config) {
        $split_label = '';

        if ($component === 'school_fee') {
            $split_label = $fee_config['school_fee_label'] ?? 'GHSS';
        } elseif ($component === 'trust_facilities_fee' || $component === 'hostel_fee' || $component === 'hostel_security' || $component === 'transport_fee') {
            // Trust facilities, hostel deposit, and transport fee all go to MST
            $split_label = $fee_config['trust_fee_label'] ?? 'MST';
        } elseif ($component === 'tuition_fee_part2' || $component === 'tuition_fee_part1' || $component === 'token_fee') {
            $split_label = $fee_config['tuition_fee_label'] ?? 'GCA';
        }

        // Add to split amounts (accumulate if same label)
        if (!empty($split_label)) {
            if (!isset($split_amounts[$split_label])) {
                $split_amounts[$split_label] = 0;
            }
            $split_amounts[$split_label] += $amount;
        }
    }
}

// Verify total amount matches sum of individual amounts
if (abs($verified_total - floatval($total_amount)) > 0.01) {
    set_flash_message('error', "Total amount mismatch");
    error_log("Payment total mismatch: Submitted=$total_amount, Calculated=$verified_total");
    header('Location: ../dashboard/student_dashboard.php');
    exit;
}

// Store payment info in session for gateway processing
$_SESSION['pending_payment'] = [
    'student_id' => $student_id,
    'payment_type' => 'multiple_pending_fees',
    'total_amount' => $verified_total,
    'items' => $payment_items,
    'split_amounts' => $split_amounts, // Split amounts for EaseBuzz
    'fee_label' => 'Multiple Fee Components (' . count($payment_items) . ' items)',
    'fee_component' => 'multiple', // Special marker for multiple components
    'item_count' => count($payment_items)
];

// Log payment initiation with split details
error_log("Multiple Fee Payment Initiated - Student ID: $student_id, Items: " . count($payment_items) . ", Total: ₹" . formatIndianCurrency($verified_total) . ", Split: " . json_encode($split_amounts));

// Log detailed payment items breakdown
error_log("=== MULTIPLE FEE PAYMENT DETAILS ===");
error_log("Student ID: $student_id");
error_log("Total Items: " . count($payment_items));
error_log("Total Amount: ₹" . formatIndianCurrency($verified_total));
error_log("Payment Items:");
foreach ($payment_items as $index => $item) {
    $item_log = sprintf(
        "  Item %d: %s | Component: %s | Amount: ₹%s | Installment ID: %s",
        $index + 1,
        $item['label'],
        $item['component'],
        formatIndianCurrency($item['amount']),
        $item['installment_id'] ? $item['installment_id'] : 'N/A'
    );
    error_log($item_log);
}
error_log("Split Amounts (Labels):");
foreach ($split_amounts as $label => $split_amount) {
    error_log("  Label: $label | Amount: ₹" . formatIndianCurrency($split_amount));
}
error_log("UDF Values:");
error_log("  udf1: $student_id (Student ID)");
error_log("  udf2: multiple_pending_fees (Payment Type)");
error_log("  udf3: [Transaction ID - will be generated]");
error_log("  udf4: multiple (Fee Component)");
error_log("  udf5: Multiple Fee Components (" . count($payment_items) . " items) (Product Info)");
error_log("  udf6: [Empty - No single installment] (Installment ID)");
error_log("Session Data Stored:");
error_log("  payment_type: multiple_pending_fees");
error_log("  item_count: " . count($payment_items));
error_log("  split_amounts: " . json_encode($split_amounts));
error_log("  items: " . json_encode($payment_items));
error_log("=== END MULTIPLE FEE PAYMENT DETAILS ===");

// Route to appropriate gateway - Only EaseBuzz is supported
if ($gateway !== 'easebuzz') {
    set_flash_message('error', "Only EaseBuzz payment gateway is supported");
    header('Location: pay-all-pending-fees.php');
    exit;
}

// Set variables expected by easebuzz-pending-payment.php
$amount = $verified_total;
$fee_component = 'multiple'; // Special marker for multiple components
$fee_label = 'Multiple Fee Components (' . count($payment_items) . ' items)';
$installment_id = null; // Not applicable for multiple payments

// Enable split payments with override for multiple fee payment
$enable_split_payments_override = false; // Disabled split payment as EaseBuzz hasn't enabled it
$split_amounts_override = $split_amounts;

// Set variables for unified handler
$payment_type = 'MultipleFees';
$product_info = $fee_label;
$session_splits = $split_amounts;
$session_component_breakdown = $component_breakdown;

// Include EaseBuzz payment processing for pending fees
include '../payments/easebuzz-initiate.php';

