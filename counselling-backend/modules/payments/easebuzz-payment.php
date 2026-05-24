<?php

/**
 * EaseBuzz Payment Integration
 * Handles token fee payment through EaseBuzz gateway using official library
 * Supports split payments based on fee configuration labels
 */

// This file is included from process-token-payment.php OR process-pending-payment.php
// Variables available: $student_id, $amount, $student, $conn
// Optional variables: $fee_component, $fee_label, $installment_id

// Initialize configuration variables

// Determine payment type and product info dynamically
$payment_type = $fee_component ?? 'token_fee';
$product_info = $fee_label ?? 'Token Fee Payment';
$installment_reference = $installment_id ?? null;

// Include official EaseBuzz library
require_once __DIR__ . '/../../easebuzz-lib/easebuzz_payment_gateway.php';

// Ensure operations file is included
if (!function_exists('generateUniqueTransactionID')) {
    require_once __DIR__ . '/../../common/transaction_helper.php';
}
require_once __DIR__ . '/../../../common/helpers/format_helper.php';

// Include Operation.php for database operations
if (!class_exists('Operation')) {
    require_once OPERATION_FILE;
}
$dbOps = new Operation();

// Fetch EaseBuzz configuration
$config = $dbOps->selectOne('tbl_payment_gateway_config', ['*'], ['gateway_name' => 'easebuzz', 'is_active' => 1]);

if (!$config) {
    set_flash_message('error', "EaseBuzz payment gateway is not configured. Please contact administrator.");
    // Redirect to appropriate page based on context
    $redirect_url = (isset($fee_component) && $fee_component !== 'token_fee') ? 'my-fees.php' : 'token-fee-payment.php';
    header('Location: ../student-portal/' . $redirect_url);
    exit;
}

$merchant_key = $config['api_key'];
$merchant_salt = $config['api_secret'];
$api_url = $config['api_url'] ?? '';

// Determine environment from API URL (environment column doesn't exist in table)
// testpay.easebuzz.in = test, pay.easebuzz.in = prod
$env = (strpos($api_url, 'testpay') !== false) ? 'test' : 'prod';

// Log configuration for debugging
error_log("EASEBUZZ CONFIG - Merchant Key: " . substr($merchant_key, 0, 4) . "*****, Environment: $env, API URL: $api_url");

// Validate merchant key and salt
if (empty($merchant_key) || empty($merchant_salt)) {
    set_flash_message('error', "Payment gateway credentials are missing. Please contact administrator.");
    $redirect_url = (isset($fee_component) && $fee_component !== 'token_fee') ? 'my-fees.php' : 'token-fee-payment.php';
    header('Location: ../student-portal/' . $redirect_url);
    exit;
}

// Initialize EaseBuzz with official library
$easebuzz = new Easebuzz($merchant_key, $merchant_salt, $env);

// Generate unique transaction ID for both internal and gateway use
$transaction_id = generateUniqueTransactionID('GM');
$txnid = $transaction_id;  // Use same ID for gateway

// Student details
$firstname = trim($student['surname'] . ' ' . $student['student_name'] . ' ' . $student['fathers_name']);
$firstname = substr($firstname, 0, 50); // Limit to 50 characters
$email = !empty($student['email']) ? $student['email'] : 'student' . $student_id . '@institution.edu';
// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $email = 'student' . $student_id . '@institution.edu';
}
$phone = preg_replace('/[^0-9]/', '', $student['mob']); // Remove non-numeric characters
// Ensure phone is exactly 10 digits
if (strlen($phone) != 10) {
    set_flash_message('error', "Invalid mobile number. Phone number must be exactly 10 digits.");
    $redirect_url = (isset($fee_component) && $fee_component !== 'token_fee') ? 'my-fees.php' : 'token-fee-payment.php';
    header('Location: ../student-portal/' . $redirect_url);
    exit;
}

// Fetch fee configuration with split labels for split payment
$stmt = $conn->prepare("SELECT fc.school_fee, fc.trust_facilities_fee, fc.tuition_fee_part1,
                        fc.school_fee_label, fc.trust_fee_label, fc.tuition_fee_label
                        FROM tbl_fee_config fc
                        INNER JOIN tbl_gm_std_registration s ON s.course_id = fc.course_id 
                            AND s.medium_id = fc.medium_id 
                            AND s.group_id = fc.group_id
                        WHERE s.id = ? AND fc.is_active = 1");
$stmt->execute([$student_id]);
$fee_config = $stmt->fetch();

// Calculate split amounts based on payment type
// Split payments use FIXED amounts assigned to specific Easebuzz labels
// Labels must match exactly what's configured in your Easebuzz merchant dashboard
$split_amounts = [];

if ($fee_config) {
    // Determine which label to use based on payment type
    if ($payment_type === 'token_fee') {
        // TOKEN FEE: Only Tuition Part 1 with GST (Rs. 11,800) - Semester 1 only
        $tuition_part1 = floatval($fee_config['tuition_fee_part1']);
        $tuition_with_gst = $tuition_part1 + ($tuition_part1 * 0.18);

        // Use tuition label from config (e.g., SPLITLABEL2, GCA, etc.)
        $tuition_label = $fee_config['tuition_fee_label'] ?? 'SPLITLABEL2';

        if (!empty($tuition_label) && $tuition_with_gst > 0) {
            $split_amounts[$tuition_label] = number_format(round($tuition_with_gst), 2, '.', '');
        }

        error_log("Token Fee Split Payment - Label: $tuition_label, Amount: $tuition_with_gst");
    } elseif ($payment_type === 'school_fee') {
        // SCHOOL FEE: Fixed amount (Rs. 15,000)
        $school_fee = floatval($fee_config['school_fee']);
        $school_label = $fee_config['school_fee_label'] ?? 'SPLITLABEL1';

        if (!empty($school_label) && $school_fee > 0) {
            $split_amounts[$school_label] = number_format(round($school_fee), 2, '.', '');
        }

        error_log("School Fee Split Payment - Label: $school_label, Amount: $school_fee");
    } elseif ($payment_type === 'trust_facilities_fee' || $payment_type === 'hostel_fee' || $payment_type === 'hostel_security' || $payment_type === 'transport_fee') {
        // TRUST, HOSTEL, and TRANSPORT all go to the Trust label (MST)
        $trust_label = $fee_config['trust_fee_label'] ?? 'SPLITLABEL1';

        if (!empty($trust_label) && floatval($amount) > 0) {
            $split_amounts[$trust_label] = number_format(round(floatval($amount)), 2, '.', '');
        }
        error_log("$payment_type Split Payment - Label: $trust_label, Amount: $amount");
    } elseif ($payment_type === 'tuition_fee_part2') {
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

// Store split amounts as JSON for callback processing
$split_amounts_json = !empty($split_amounts) ? json_encode($split_amounts) : '';

// Set split payment enabled flag
$enable_split_payments = !empty($split_amounts);

// Log split amounts for debugging
if (!empty($split_amounts_json)) {
    error_log("Easebuzz Split Amounts JSON: " . $split_amounts_json);
}


// Extract split label and amount for UDF fields (Easebuzz doesn't accept JSON in UDF)
// Following best practice: pass label and amount separately in UDF5 and UDF6
// Reference: Similar to how GCI system handles split payments in pay.php
$split_label = '';
$split_amount_value = '';
if (!empty($split_amounts)) {
    // Get first (and only) split entry
    $split_label = array_key_first($split_amounts);
    $split_amount_value = $split_amounts[$split_label];
}

// Payment parameters as required by EaseBuzz official library
// CRITICAL: amount must be STRING with decimal format (e.g., "15000.00"), phone must be string
// EaseBuzz validates string format with regex, then converts to float internally
$payment_params = array(
    'txnid' => $txnid,
    'amount' => number_format(round((float) $amount), 2, '.', ''), // Must be string "15000.00" format
    'productinfo' => substr($product_info, 0, 100), // Limit product info to 100 chars
    'firstname' => $firstname, // Already sanitized above
    'email' => $email, // Already validated above
    'phone' => (string) $phone, // Must be string for EaseBuzz validation
    'surl' => PORTAL_URL . '/modules/payments/easebuzz-callback.php',
    'furl' => PORTAL_URL . '/modules/payments/easebuzz-callback.php',
    'udf1' => (string) $student_id, // Store student ID
    'udf2' => $payment_type, // Dynamic: token_fee, school_fee, trust_facilities_fee, tuition_fee_part2, hostel_fee, transport_fee
    'udf3' => $transaction_id, // Our internal transaction ID
    'udf4' => (string) ($installment_reference ?? ''), // Installment ID if applicable
    'udf5' => $split_label, // Split settlement label (e.g., GCA, SPLITLABEL1) - Easebuzz requires string, not JSON
    'udf6' => $split_amount_value, // Split amount value - Passed separately as string
    'address1' => '',
    'address2' => '',
    'city' => '',
    'state' => '',
    'country' => '',
    'zipcode' => '',
);

// ============================================================
// CONSOLE LOGGING FOR EASEBUZZ SUPPORT DEBUGGING
// ============================================================
echo "<script>
console.log('='.repeat(80));
console.log('EASEBUZZ PAYMENT PARAMETERS - FOR SUPPORT TEAM');
console.log('='.repeat(80));
console.log('\\nðŸ“Œ MERCHANT CONFIGURATION:');
console.log('Merchant Key: " . json_encode($merchant_key) . "');
console.log('Environment: " . json_encode($env) . "');
console.log('\\nðŸ’° TRANSACTION DETAILS:');
console.log('Transaction ID: " . json_encode($txnid) . "');
console.log('Amount: ₹" . json_encode($amount) . "');
console.log('Product Info: " . json_encode($product_info) . "');
console.log('Payment Type: " . json_encode($payment_type) . "');
console.log('\\nðŸ‘¤ CUSTOMER DETAILS:');
console.log('Name: " . json_encode($firstname) . "');
console.log('Email: " . json_encode($email) . "');
console.log('Phone: " . json_encode($phone) . "');
console.log('Student ID: " . json_encode($student_id) . "');
console.log('\\nðŸ”— CALLBACK URLs:');
console.log('Success URL: " . json_encode(PORTAL_URL . '/modules/payments/easebuzz-callback.php') . "');
console.log('Failure URL: " . json_encode(PORTAL_URL . '/modules/payments/easebuzz-callback.php') . "');
";

if (!empty($split_amounts)) {
    echo "console.log('\\nðŸ’¸ SPLIT PAYMENT CONFIGURATION:');\n";
    echo "console.log('Split Status: " . (!empty($split_amounts) ? 'ENABLED' : 'NOT CONFIGURED') . "');\n";
    echo "console.log('\\nðŸ“Š SPLIT AMOUNTS BREAKDOWN:');\n";
    foreach ($split_amounts as $label => $split_amount) {
        echo "console.log('  Label: \"" . $label . "\" => Amount: ₹" . $split_amount . "');\n";
    }
    echo "console.log('\\nðŸ“ UDF FIELDS (SEPARATE LABEL & AMOUNT):');\n";
    echo "console.log('  udf5 (Split Label): \"" . $split_label . "\"');\n";
    echo "console.log('  udf6 (Split Amount): \"" . $split_amount_value . "\"');\n";
} else {
    echo "console.log('\\nâš ï¸  NO SPLIT AMOUNTS CONFIGURED');\n";
}

echo "console.log('\\nðŸ“¦ COMPLETE PAYMENT PARAMS OBJECT:');
console.log(" . json_encode($payment_params, JSON_PRETTY_PRINT) . ");
console.log('='.repeat(80));
console.log('âš ï¸  ERROR: Payment settlement not set properly');
console.log('ðŸ“§ Please share this console output with Easebuzz support team');
console.log('ðŸŽ¯ Merchant ID: " . json_encode($config['merchant_id'] ?? 'N/A') . "');
console.log('='.repeat(80));
</script>";

// Also log to PHP error log for server-side debugging
error_log("\n" . str_repeat("=", 80));
error_log("EASEBUZZ PAYMENT DEBUG - Transaction ID: $txnid");
error_log(str_repeat("=", 80));
error_log("Merchant Key: $merchant_key");
error_log("Environment: $env");
error_log("Amount: " . $amount);
error_log("Payment Type: $payment_type");
error_log("Split Status: " . (!empty($split_amounts) ? 'ENABLED' : 'NOT CONFIGURED'));
if (!empty($split_amounts)) {
    error_log("Split Amounts: " . json_encode($split_amounts));
}
error_log("Full Payment Params: " . json_encode($payment_params));
error_log(str_repeat("=", 80));


// Add split payment parameters following Easebuzz documentation format
// Format: {"label1": amount1, "label2": amount2}
// Labels must match exactly what's configured in Easebuzz merchant dashboard

if (!empty($split_amounts)) {
    // Convert PHP array to JSON string as per Easebuzz documentation
    // Example: {"axisaccount": 100, "hdfcaccount": 100}
    $payment_params['split_payments'] = json_encode($split_amounts, JSON_FORCE_OBJECT);
    error_log("Easebuzz Split Payments ENABLED: " . $payment_params['split_payments']);
    error_log("Split payments array: " . print_r($split_amounts, true));
} else {
    error_log("Split payments DISABLED - using single payment flow. Enable after Easebuzz configuration.");
}

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
    // Redirect based on payment type
    $redirect_url = ($payment_type === 'token_fee') ? 'token-fee-payment.php' : 'my-fees.php';
    header('Location: ../student-portal/' . $redirect_url);
    exit;
}

// Call EaseBuzz API using official library
// $redirect = false means we handle the redirect ourselves (recommended approach)
try {
    error_log("==== CALLING EASEBUZZ API ====");
    error_log("Payment Params: " . json_encode($payment_params));

    $result = $easebuzz->initiatePaymentAPI($payment_params, false);
    $result_data = json_decode($result, true);

    error_log("Easebuzz API Raw Response: " . $result);
    error_log("Easebuzz API Response (decoded): " . json_encode($result_data));

    // Check if payment link was generated successfully
    if (!isset($result_data['status']) || $result_data['status'] != 1) {
        $error_msg = $result_data['data'] ?? $result_data['error'] ?? 'Payment initiation failed';
        error_log("Easebuzz API Error: " . $error_msg);
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

    // Generate payment URL based on environment
    error_log("Access Key: " . $access_key);

    $payment_url = ($env === 'prod')
        ? "https://pay.easebuzz.in/pay/" . $access_key
        : "https://testpay.easebuzz.in/pay/" . $access_key;

    error_log("Generated Payment URL: " . $payment_url);
} catch (Exception $e) {
    error_log("Easebuzz Payment Error: " . $e->getMessage());
    set_flash_message('error', "Payment gateway error: " . $e->getMessage());
    $redirect_url = ($payment_type === 'token_fee') ? 'token-fee-payment.php' : 'my-fees.php';
    header('Location: ../student-portal/' . $redirect_url);
    exit;
}

if (isset($payment_url) && !empty($payment_url)) {

    // Output HTML with JavaScript to open payment in new window/tab
    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Processing Payment...</title>
        
    </head>

    <body>
        <div class="payment-container">
            <div class="spinner"></div>
            <h2>Opening Payment Gateway...</h2>
            <p>Please wait while we redirect you to the secure payment page</p>
            <div class="amount"><?php echo formatIndianCurrency($amount); ?></div>
            <p><strong><?php echo htmlspecialchars($product_info ?? ''); ?></strong></p>

            <div class="info-box">
                <p><strong>Transaction ID:</strong> <?php echo htmlspecialchars($txnid ?? ''); ?></p>
                <p><strong>Student:</strong> <?php echo htmlspecialchars($firstname ?? ''); ?></p>
            </div>

            <div class="css-easebuzz-payment-194b57">
                <a href="../student-portal/<?php echo ($payment_type === 'token_fee') ? 'token-fee-payment.php' : 'my-fees.php'; ?>"
                    class="btn btn-secondary">
                    Cancel & Go Back
                </a>
            </div>
        </div>

        <script>
            const paymentUrl = <?php echo json_encode($payment_url); ?>;
            let paymentWindow = null;

            // Add JavaScript to show parameters before redirect (for debugging)
            // Create formatted output for Easebuzz support
            const debugInfo = {
                'Merchant Key': <?php echo json_encode($merchant_key); ?>,
                'Environment': <?php echo json_encode($env); ?>,
                'Transaction ID': <?php echo json_encode($txnid); ?>,
                'Amount': '₹<?php echo json_encode($amount); ?>',
                'Payment Type': <?php echo json_encode($payment_type); ?>,
                'Split Enabled': <?php echo json_encode($enable_split_payments); ?>,
                'Split Amounts': <?php echo json_encode($split_amounts); ?>,
                'Product Info': <?php echo json_encode($product_info); ?>,
                'Customer Name': <?php echo json_encode($firstname); ?>,
                'Customer Email': <?php echo json_encode($email); ?>,
                'Customer Phone': <?php echo json_encode($phone); ?>,
                'Merchant ID': <?php echo json_encode($config['merchant_id'] ?? 'N/A'); ?>
            };

            // Save to sessionStorage so it persists
            sessionStorage.setItem('easebuzzDebugParams', JSON.stringify(debugInfo, null, 2));

            // Log debug info to console
            console.clear();
            console.log('='.repeat(80));
            console.log('ðŸ”´ EASEBUZZ PARAMETERS - SEND THIS TO SUPPORT TEAM');
            console.log('='.repeat(80));
            console.table(debugInfo);
            console.log('\nðŸ“‹ COPY THIS JSON:');
            console.log(JSON.stringify(debugInfo, null, 2));
            console.log('='.repeat(80));

            // Redirect to payment gateway in same window
            window.onload = function () {
                // Redirect after 2 seconds
                setTimeout(function () {
                    window.location.href = paymentUrl;
                }, 2000);
            };
        </script>
    </body>

    </html>
    <?php
    exit;
} else {
    // This else block is no longer reachable due to try-catch above
    error_log("Easebuzz Payment - Unexpected code path reached");
    set_flash_message('error', "Payment initiation failed. Please try again.");
    $redirect_url = ($payment_type === 'token_fee') ? 'token-fee-payment.php' : 'my-fees.php';
    header('Location: ../student-portal/' . $redirect_url);
    exit;
}
