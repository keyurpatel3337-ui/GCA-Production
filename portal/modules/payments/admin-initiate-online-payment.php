<?php
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/format_helper.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/fee_helper.php';

// Access check: only ROLE_ACCOUNTANT, ROLE_PRINCIPLE, ROLE_SUPER_ADMIN
if (!hasRole(ROLE_ACCOUNTANT) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN)) {
    set_flash_message('error', "Unauthorized access. Only Accountants, Principals, and Super Admins can initiate online payments.");
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

function redirectBackWithError($message, $student_id = null) {
    set_flash_message('error', $message);
    if ($student_id) {
        header('Location: ../reports/financial/student-ledger.php?student_id=' . urlencode($student_id));
    } else {
        header('Location: ../reports/financial/student-ledger.php');
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectBackWithError("Invalid request method");
}

$student_id = $_POST['student_id'] ?? null;
$component = $_POST['component'] ?? null;

if (empty($student_id) || empty($component)) {
    redirectBackWithError("Missing required payment information", $student_id);
}

// Fetch student details
$students = $dbOps->customSelect("SELECT s.*, 
                       CASE WHEN p.id IS NOT NULL OR s.token_fees_paid = 1 THEN 1 ELSE 0 END as token_fees_paid,
                       s.student_name, s.surname, s.fathers_name, s.mob, s.aadhaar,
                       c.course_name,
                       fc.school_fee, fc.trust_facilities_fee, fc.tuition_fee_part1, fc.tuition_fee_part2,
                       s.scholarship_amount, s.additional_scholarship_amount,
                       e.enrollment_id,
                       e.current_term_id,
                       e.post_admission_discount_amount,
                       sch.school_name
                       FROM tbl_gm_std_registration s
                       LEFT JOIN tbl_payments p ON s.id = p.student_id AND p.payment_type = 'token_fee' AND p.status = 'paid'
                       LEFT JOIN tbl_courses c ON s.course_id = c.id
                       LEFT JOIN tbl_fee_config fc ON s.course_id = fc.course_id 
                            AND s.medium_id = fc.medium_id 
                            AND s.group_id = fc.group_id 
                            AND fc.is_active = 1
                       LEFT JOIN tbl_enrolled_students e ON s.id = e.registration_id AND e.is_active = 1
                       LEFT JOIN tbl_schools sch ON s.school_id = sch.id
                       WHERE s.id = ?", [$student_id]);
$student = !empty($students) ? $students[0] : null;

if (!$student) {
    redirectBackWithError("Student record not found", $student_id);
}

// Store return URL in session for easebuzz-callback-handler.php
$_SESSION['payment_redirect_url'] = PORTAL_URL . '/modules/reports/financial/student-ledger.php?student_id=' . $student_id;

$installment_id = null;
$session_splits = null;
$session_component_breakdown = null;

if ($component !== 'all' && !in_array($component, ['Academic', 'Hostel', 'Transport', 'Other'])) {
    // Single component payment initiation
    if (!in_array($component, ['school_fee', 'trust_facilities_fee', 'tuition_fee_part2', 'hostel_fee', 'hostel_security', 'transport_fee'])) {
        redirectBackWithError("Invalid fee component: " . htmlspecialchars($component), $student_id);
    }

    $current_term_id = $student['current_term_id'] ?? 1;
    $fee_summary = calculateStudentFeeSummary($conn, $student_id, false, $current_term_id);
    if (!$fee_summary) {
        redirectBackWithError("Could not calculate fee details", $student_id);
    }

    $allocations = $fee_summary['allocations'];
    if (!isset($allocations[$component])) {
        redirectBackWithError("Fee component allocation not found: " . htmlspecialchars($component), $student_id);
    }

    $fee_amount = floatval($allocations[$component]['pending_amount'] ?? 0);
    if ($fee_amount <= 0) {
        redirectBackWithError("No pending dues found for " . $allocations[$component]['label'], $student_id);
    }

    $fee_label = $allocations[$component]['label'] ?? 'Fee Payment';

    // Store payment info in session for unified callback processing
    $_SESSION['pending_payment'] = [
        'student_id' => $student_id,
        'fee_component' => $component,
        'fee_label' => $fee_label,
        'amount' => $fee_amount,
        'payment_type' => 'pending_fee',
        'installment_id' => null
    ];

    $payment_type = $component;
    $product_info = $fee_label;
    $amount = $fee_amount;

} else {
    // Pay All pending fees initiation
    $current_term_id = $student['current_term_id'] ?? 1;
    $fee_summary = calculateStudentFeeSummary($conn, $student_id, true, $current_term_id);
    if (!$fee_summary) {
        redirectBackWithError("Could not calculate fee details", $student_id);
    }

    $allocations = $fee_summary['allocations'];
    $pending_fees = [];
    $total_amount = 0;
    $split_amounts = [];
    $component_breakdown = [];

    // Fetch fee config to get split labels
    $stmt = $conn->prepare("SELECT fc.school_fee, fc.trust_facilities_fee, fc.tuition_fee_part2,
                            fc.school_fee_label, fc.trust_fee_label, fc.tuition_fee_label
                            FROM tbl_fee_config fc
                            INNER JOIN tbl_gm_std_registration s ON s.course_id = fc.course_id 
                                AND s.medium_id = fc.medium_id 
                                AND s.group_id = fc.group_id
                            WHERE s.id = ? AND fc.is_active = 1");
    $stmt->execute([$student_id]);
    $fee_config = $stmt->fetch(PDO::FETCH_ASSOC);

    foreach ($allocations as $comp_key => $data) {
        if ($component === 'all') {
            if (strpos(strtolower($comp_key), 'hostel') !== false) {
                continue; // Exclude hostel fees from Pay All
            }
        } else {
            $comp_cat = $data['category'] ?? 'Academic';
            if (strpos(strtolower($comp_key), 'hostel') !== false) {
                $comp_cat = 'Hostel';
            } elseif ($comp_key === 'transport_fee') {
                $comp_cat = 'Transport';
            }
            if (strtolower($comp_cat) !== strtolower($component)) {
                continue;
            }
        }
        
        $pending = floatval($data['pending_amount'] ?? 0);
        if ($pending > 0) {
            $label = $data['label'];
            if ($comp_key === 'hostel_security') {
                $label = 'Hostel Security Deposit';
            }
            
            $pending_fees[] = [
                'component' => $comp_key,
                'label' => $label,
                'amount' => $pending
            ];
            
            if (!isset($component_breakdown[$comp_key])) {
                $component_breakdown[$comp_key] = 0;
            }
            $component_breakdown[$comp_key] += $pending;
            
            $total_amount += $pending;
            
            if ($fee_config) {
                $split_label = '';
                if ($comp_key === 'school_fee') {
                    $split_label = $fee_config['school_fee_label'] ?? 'GHSS';
                } elseif ($comp_key === 'trust_facilities_fee' || $comp_key === 'transport_fee' || $comp_key === 'hostel_security') {
                    $split_label = $fee_config['trust_fee_label'] ?? 'MST';
                } elseif ($comp_key === 'tuition_fee_part2' || $comp_key === 'tuition_fee_part1' || $comp_key === 'token_fee') {
                    $split_label = $fee_config['tuition_fee_label'] ?? 'GCA';
                }
                
                if (!empty($split_label)) {
                    if (!isset($split_amounts[$split_label])) {
                        $split_amounts[$split_label] = 0;
                    }
                    $split_amounts[$split_label] += $pending;
                }
            }
        }
    }

    if ($total_amount <= 0) {
        redirectBackWithError("No pending fee components found to pay.", $student_id);
    }

    // Store payment info in session for unified callback processing
    $_SESSION['pending_payment'] = [
        'student_id' => $student_id,
        'payment_type' => 'multiple_pending_fees',
        'total_amount' => $total_amount,
        'items' => $pending_fees,
        'split_amounts' => $split_amounts,
        'fee_label' => $component === 'all' ? 'Multiple Fee Components (' . count($pending_fees) . ' items)' : "$component Fee Payment",
        'fee_component' => 'multiple',
        'item_count' => count($pending_fees)
    ];

    $payment_type = $component === 'all' ? 'MultipleFees' : $component . 'Fees';
    $product_info = $component === 'all' ? 'Multiple Fee Components (' . count($pending_fees) . ' items)' : "$component Fee Payment";
    $amount = $total_amount;
    $session_splits = $split_amounts;
    $session_component_breakdown = $component_breakdown;
}

// Log initiation
error_log("Admin Initiated Payment - Student ID: $student_id, Component: $component, Amount: ₹$amount, By User: " . ($_SESSION['user_id'] ?? 'unknown'));

// Include unified EaseBuzz payment initiation handler
include 'easebuzz-initiate.php';
