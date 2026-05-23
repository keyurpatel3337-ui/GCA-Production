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
    header('Location: token-fee-payment.php');
    exit;
}

$student_id = $_POST['student_id'] ?? null;
$amount = $_POST['amount'] ?? null;
$gateway = $_POST['gateway'] ?? null;

// Validation
if (empty($student_id) || empty($amount) || empty($gateway)) {
    set_flash_message('error', "Missing required payment information");
    header('Location: token-fee-payment.php');
    exit;
}

// Verify student ID matches parent session
if ($student_id != $_SESSION['active_student_id'] && $student_id != $_SESSION['student_id']) {
    set_flash_message('error', "Invalid student ID");
    header('Location: token-fee-payment.php');
    exit;
}

// Fetch student details using DatabaseOperations
// Fetch student details using DatabaseOperations
$student = $dbOps->customSelectOne(
    "SELECT s.*,
     CASE WHEN p.id IS NOT NULL OR s.token_fees_paid = 1 THEN 1 ELSE 0 END as token_fees_paid
     FROM tbl_gm_std_registration s
     LEFT JOIN tbl_payments p ON s.id = p.student_id AND (p.payment_type = 'token_fee' OR p.fee_component = 'tuition_fee_part1') AND p.status = 'paid'
     WHERE s.id = ?",
    [$student_id]
);

if (!$student || $student['token_fees_paid']) {
    set_flash_message('error', "Invalid request or payment already completed");
    header('Location: token-fee-payment.php');
    exit;
}

// Route to appropriate gateway - Only EaseBuzz is supported
if ($gateway !== 'easebuzz') {
    set_flash_message('error', "Only EaseBuzz payment gateway is supported");
    header('Location: token-fee-payment.php');
    exit;
}

// Set variables for unified handler
$payment_type = 'token_fee';
$product_info = 'Token Fee Payment';

// Include EaseBuzz payment processing
include '../payments/easebuzz-initiate.php';
