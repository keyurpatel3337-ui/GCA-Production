<?php

/**
 * EaseBuzz Payment Integration for Pending Fees
 * Handles school fee, trust facilities fee, and tuition part 2 payments through EaseBuzz gateway
 */

// This file is included from process-pending-payment.php
// Variables available: $student_id, $amount, $fee_component, $fee_label, $student, $conn, $installment_id

// Include official EaseBuzz library
require_once __DIR__ . '/../../easebuzz-lib/easebuzz_payment_gateway.php';

// Ensure operations file is included
if (!function_exists('generateUniqueTransactionID')) {
    require_once __DIR__ . '/../../common/transaction_helper.php';
}

// Include Operation.php for database operations
if (!class_exists('Operation')) {
    require_once OPERATION_FILE;
}
$dbOps = new Operation();

// Fetch EaseBuzz configuration
$config = $dbOps->selectOne('tbl_payment_gateway_config', ['*'], ['gateway_name' => 'easebuzz', 'is_active' => 1]);

if (!$config) {
    set_flash_message('error', "EaseBuzz payment gateway is not configured. Please contact administrator.");
    header('Location: pending-fee-payment.php?component=' . urlencode($fee_component));
    exit;
}

$merchant_key = $config['api_key'];
$merchant_salt = $config['api_secret'];
$env = $config['environment'] ?? 'test'; // 'test' or 'prod'

// Initialize EaseBuzz with official library
$easebuzz = new Easebuzz($merchant_key, $merchant_salt, $env);

// Generate unique transaction ID
$transaction_id = generateUniqueTransactionID('GM');
$txnid = $transaction_id;

// Student details
$firstname = trim($student['surname'] . ' ' . $student['student_name'] . ' ' . $student['fathers_name']);
// Remove special characters from name
$firstname = preg_replace('/[^a-zA-Z0-9 ]/', '', $firstname);
// Ensure name is not too long
$firstname = substr($firstname, 0, 50);
// Fallback if name is empty after sanitization
if (empty($firstname)) {
    $firstname = 'Student ' . $student_id;
}

$email = !empty($student['email']) ? $student['email'] : 'student' . $student_id . '@institution.edu';
$phone = $student['mob'];

// Validate and sanitize productinfo (required by EaseBuzz)
$productinfo = !empty($fee_label) ? $fee_label : 'Fee Payment';
// EaseBuzz is very strict - only allow alphanumeric and spaces
$productinfo = preg_replace('/[^a-zA-Z0-9 ]/', ' ', $productinfo);
// Remove multiple spaces
$productinfo = preg_replace('/\s+/', ' ', $productinfo);
// Trim and ensure it's not too long (max 100 chars for safety)
$productinfo = trim(substr($productinfo, 0, 100));
// Ensure it's not empty after sanitization
if (empty($productinfo)) {
    $productinfo = 'Student Fee Payment';
}

// Validate amount (must be positive number)
$validated_amount = floatval($amount);
if ($validated_amount <= 0) {
    set_flash_message('error', "Invalid payment amount");
    header('Location: ../student-portal/pending-fee-payment.php?component=' . urlencode($fee_component));
    exit;
}

// Validate phone (must be exactly 10 digits - EaseBuzz requirement)
$phone = preg_replace('/[^0-9]/', '', $phone);
if (strlen($phone) != 10) {
    set_flash_message('error', "Invalid mobile number. Phone number must be exactly 10 digits.");
    header('Location: ../student-portal/pending-fee-payment.php?component=' . urlencode($fee_component));
    exit;
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $email = 'student' . $student_id . '@institution.edu';
}

// Fetch fee configuration with split labels for split payment
$stmt = $conn->prepare("SELECT fc.school_fee, fc.trust_facilities_fee, fc.tuition_fee_part2,
                        fc.school_fee_label, fc.trust_fee_label, fc.tuition_fee_label
                        FROM tbl_fee_config fc
                        INNER JOIN tbl_gm_std_registration s ON s.course_id = fc.course_id 
                            AND s.medium_id = fc.medium_id 
                            AND s.group_id = fc.group_id
                        WHERE s.id = ? AND fc.is_active = 1");
$stmt->execute([$student_id]);
$fee_config = $stmt->fetch();

// Calculate split amounts based on payment type
$split_amounts = [];

if ($fee_config) {
    if ($fee_component === 'school_fee') {
        // SCHOOL FEE: Fixed amount (Rs. 15,000)
        $school_fee = floatval($fee_config['school_fee']);
        $school_label = $fee_config['school_fee_label'] ?? 'SPLITLABEL1';

        if (!empty($school_label) && $school_fee > 0) {
            $split_amounts[$school_label] = number_format(round($school_fee), 2, '.', '');
        }
        error_log("School Fee Split Payment - Label: $school_label, Amount: $school_fee");
    } elseif ($fee_component === 'trust_facilities_fee' || $fee_component === 'hostel_fee' || $fee_component === 'hostel_security' || $fee_component === 'transport_fee') {
        // TRUST, HOSTEL, and TRANSPORT all go to the Trust label (MST)
        $trust_label = $fee_config['trust_fee_label'] ?? 'SPLITLABEL1';

        if (!empty($trust_label) && $validated_amount > 0) {
            $split_amounts[$trust_label] = number_format(round($validated_amount), 2, '.', '');
        }
        error_log("$fee_component Split Payment - Label: $trust_label, Amount: $validated_amount");
    } elseif ($fee_component === 'tuition_fee_part2') {
        // TUITION PART 2: With GST
        $tuition_part2 = floatval($fee_config['tuition_fee_part2']);
        $tuition_part2_with_gst = $tuition_part2 + ($tuition_part2 * 0.18);
        $tuition_label = $fee_config['tuition_fee_label'] ?? 'SPLITLABEL2';

        if (!empty($tuition_label) && $tuition_part2_with_gst > 0) {
            $split_amounts[$tuition_label] = number_format(round($tuition_part2_with_gst), 2, '.', '');
        }
        error_log("Tuition Part 2 Split Payment - Label: $tuition_label, Amount: $tuition_part2_with_gst");
    }
}

// Store split amounts as JSON
$split_amounts_json = !empty($split_amounts) ? json_encode($split_amounts) : '';

// Enable split payments - Easebuzz settlement must be configured in merchant dashboard
$enable_split_payments = true; // Split payments enabled - ensure SPLITLABEL1, SPLITLABEL2 are configured in Easebuzz dashboard

// Payment parameters as required by EaseBuzz official library
// CRITICAL: amount must be STRING with decimal format (e.g., "15000.00"), phone must be string
// EaseBuzz validates string format with regex, then converts to float internally
$payment_params = array(
    'txnid' => $txnid,
    'amount' => number_format(round((float) $validated_amount), 2, '.', ''), // Must be string "15000.00" format
    'productinfo' => $productinfo,
    'firstname' => $firstname,
    'email' => $email,
    'phone' => (string) $phone, // Must be string for EaseBuzz validation
    'surl' => PORTAL_URL . '/modules/payments/easebuzz-pending-callback.php',
    'furl' => PORTAL_URL . '/modules/payments/easebuzz-pending-callback.php',
    'udf1' => (string) $student_id, // Store student ID
    'udf2' => (string) $fee_component, // Payment type (school_fee, trust_facilities_fee, etc.)
    'udf3' => (string) $transaction_id, // Our internal transaction ID
    'udf4' => (string) $fee_component, // Fee component
    'udf5' => $productinfo, // Product info (cannot use JSON here)
    'udf6' => $installment_id ? (string) $installment_id : '', // Installment ID (if applicable)
    'address1' => '',
    'address2' => '',
    'city' => '',
    'state' => '',
    'country' => '',
    'zipcode' => '',
);

// Add split payment parameters if enabled
if ($enable_split_payments && !empty($split_amounts)) {
    $payment_params['split_payments'] = json_encode($split_amounts);
    error_log("Easebuzz Split Payments ENABLED: " . json_encode($split_amounts));
} else {
    error_log("Split payments DISABLED - using single payment flow.");
}

// Log parameters for debugging (remove in production)
error_log("EaseBuzz Payment Params: " . json_encode(array(
    'txnid' => $txnid,
    'amount' => $payment_params['amount'],
    'productinfo' => $productinfo,
    'firstname' => $firstname,
    'student_id' => $student_id,
    'fee_component' => $fee_component,
    'installment_id' => $installment_id
)));

// Store order details
try {
    $stmt = $conn->prepare("INSERT INTO tbl_payment_orders 
                           (student_id, order_id, transaction_id, gateway_order_id, 
                            amount, split_amounts, payment_gateway, status, created_by, created_at) 
                           VALUES (?, ?, ?, ?, ?, ?, 'easebuzz', 'created', ?, NOW())
                           ON DUPLICATE KEY UPDATE 
                           gateway_order_id = VALUES(gateway_order_id),
                           amount = VALUES(amount),
                           split_amounts = VALUES(split_amounts),
                           updated_at = NOW()");
    $stmt->execute([$student_id, $txnid, $transaction_id, $txnid, $amount, $split_amounts_json, $student_id]);
} catch (PDOException $e) {
    set_flash_message('error', "Error creating payment order: " . $e->getMessage());
    header('Location: pending-fee-payment.php?component=' . urlencode($fee_component));
    exit;
}

// ============================================================
// ERROR.LOG LOGGING FOR EASEBUZZ SUPPORT DEBUGGING
// ============================================================
$log_file = __DIR__ . '/../../logs/error.log';
$log_separator = str_repeat("=", 80);

$debug_log = "\n$log_separator\n";
$debug_log .= "EASEBUZZ PAYMENT DEBUG - " . date('Y-m-d H:i:s') . "\n";
$debug_log .= "$log_separator\n";
$debug_log .= "MERCHANT CONFIGURATION:\n";
$debug_log .= "  Merchant Key: $merchant_key\n";
$debug_log .= "  Merchant ID: " . ($config['merchant_id'] ?? 'N/A') . "\n";
$debug_log .= "  Environment: $env\n";
$debug_log .= "\nTRANSACTION DETAILS:\n";
$debug_log .= "  Transaction ID: $txnid\n";
$debug_log .= "  Amount: ₹$validated_amount\n";
$debug_log .= "  Product Info: $productinfo\n";
$debug_log .= "  Fee Component: $fee_component\n";
$debug_log .= "  Fee Label: $fee_label\n";
$debug_log .= "  Installment ID: " . ($installment_id ?? 'N/A') . "\n";
$debug_log .= "\nCUSTOMER DETAILS:\n";
$debug_log .= "  Name: $firstname\n";
$debug_log .= "  Email: $email\n";
$debug_log .= "  Phone: $phone\n";
$debug_log .= "  Student ID: $student_id\n";
$debug_log .= "\nCALLBACK URLs:\n";
$debug_log .= "  Success/Failure URL: " . PORTAL_URL . "/modules/payments/easebuzz-pending-callback.php\n";

if (!empty($split_amounts)) {
    $debug_log .= "\nSPLIT PAYMENT CONFIGURATION:\n";
    $debug_log .= "  Split Enabled: " . ($enable_split_payments ? 'YES' : 'NO (DISABLED - REQUIRES SETUP)') . "\n";
    $debug_log .= "\nSPLIT AMOUNTS BREAKDOWN:\n";
    foreach ($split_amounts as $label => $split_amount) {
        $debug_log .= "  Label: \"$label\" => Amount: ₹$split_amount\n";
    }
    $debug_log .= "\nSplit Amounts JSON:\n";
    $debug_log .= "  $split_amounts_json\n";
} else {
    $debug_log .= "\nNO SPLIT AMOUNTS CONFIGURED\n";
}

$debug_log .= "\nCOMPLETE PAYMENT PARAMS:\n";
$debug_log .= json_encode($payment_params, JSON_PRETTY_PRINT) . "\n";
$debug_log .= "\nUDF FIELDS (User Defined Fields):\n";
$debug_log .= "  udf1 (Student ID): " . $payment_params['udf1'] . "\n";
$debug_log .= "  udf2 (Payment Type): " . $payment_params['udf2'] . "\n";
$debug_log .= "  udf3 (Transaction ID): " . $payment_params['udf3'] . "\n";
$debug_log .= "  udf4 (Fee Component): " . $payment_params['udf4'] . "\n";
$debug_log .= "  udf5 (Product Info): " . $payment_params['udf5'] . "\n";
$debug_log .= "  udf6 (Installment ID): " . $payment_params['udf6'] . "\n";
$debug_log .= "\nERROR MESSAGE: Payment settlement not set properly\n";
$debug_log .= "NOTE: Please share this log with Easebuzz support team\n";
$debug_log .= "$log_separator\n";

// Write to error.log
file_put_contents($log_file, $debug_log, FILE_APPEND);
error_log("Easebuzz payment debug info logged to: $log_file");

// Call EaseBuzz API using official library
// $redirect = false means we handle the response ourselves
try {
    error_log("==== EASEBUZZ API CALL - PENDING PAYMENT ====");
    error_log("Payment Params Being Sent: " . json_encode($payment_params));

    $result = $easebuzz->initiatePaymentAPI($payment_params, false);
    $result_data = json_decode($result, true);

    error_log("Easebuzz API Raw Response: " . $result);
    error_log("Easebuzz API Response (decoded): " . json_encode($result_data));
    error_log("Response Status: " . ($result_data['status'] ?? 'NOT SET'));
    error_log("Response Data/Error: " . ($result_data['data'] ?? $result_data['error'] ?? 'NOT SET'));

    // Check if payment link was generated successfully
    if (!isset($result_data['status']) || $result_data['status'] != 1) {
        $error_msg = $result_data['data'] ?? $result_data['error'] ?? 'Payment initiation failed';
        error_log("EASEBUZZ API ERROR: " . $error_msg);
        error_log("Full EaseBuzz Response: " . print_r($result_data, true));
        throw new Exception($error_msg);
    }

    // Validate that we have an access key
    // When redirect=false, the response contains 'access_key' field
    $access_key = $result_data['access_key'] ?? $result_data['data'] ?? '';

    if (empty($access_key)) {
        error_log("Easebuzz API Error: Empty access key received");
        error_log("Full response data: " . print_r($result_data, true));
        throw new Exception("Invalid payment link generated - missing access key");
    }

    // Generate payment URL and redirect
    error_log("Access Key: " . $access_key);

    $payment_url = ($env === 'prod')
        ? "https://pay.easebuzz.in/pay/" . $access_key
        : "https://testpay.easebuzz.in/pay/" . $access_key;

    error_log("Generated Payment URL: " . $payment_url);

    // Redirect to payment gateway
    header('Location: ' . $payment_url);
    exit;
} catch (Exception $e) {
    error_log("Easebuzz Pending Payment Error: " . $e->getMessage());
    error_log("Payment Params Sent: " . json_encode($payment_params));
    set_flash_message('error', "Payment gateway error: " . $e->getMessage());
    header('Location: ../student-portal/pending-fee-payment.php?component=' . urlencode($fee_component));
    exit;
}
