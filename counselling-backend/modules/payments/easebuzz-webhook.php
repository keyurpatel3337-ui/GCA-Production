<?php
header('Content-Type: application/json; charset=utf-8');

/**
 * EaseBuzz Transaction Webhook Handler
 * Receives real-time payment notifications from EaseBuzz
 * URL: https://yourdomain.com/[your-project]/portal/modules/payments/easebuzz-webhook.php
 */

session_start();
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once HELPER_ERROR_LOGGER;
require_once __DIR__ . '/../../common/helpers/enrollment_functions.php';
require_once __DIR__ . '/../../common/helpers/division_assignment_functions.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/receipt_sequence_helper.php';
require_once '../easebuzz-lib/easebuzz_payment_gateway.php';

// Initialize DB operations helper
$dbOps = new DatabaseOperations();

// Get raw POST data
$raw_post_data = file_get_contents('php://input');

// EaseBuzz sends data as POST parameters, not JSON
$webhook_data = $_POST;

if (empty($webhook_data)) {
    logError("EaseBuzz Webhook - No POST data received");
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'No data received']);
    exit;
}

// Extract webhook parameters
$txnid = $webhook_data['txnid'] ?? '';
$status = $webhook_data['status'] ?? '';
$amount = $webhook_data['amount'] ?? '';
$payment_id = $webhook_data['easepayid'] ?? '';
$firstname = $webhook_data['firstname'] ?? '';
$email = $webhook_data['email'] ?? '';
$phone = $webhook_data['phone'] ?? '';
$productinfo = $webhook_data['productinfo'] ?? '';
$hash = $webhook_data['hash'] ?? '';
$student_id = $webhook_data['udf1'] ?? '';
$payment_type = $webhook_data['udf2'] ?? '';
$transaction_id = $webhook_data['udf3'] ?? '';

// Validate required fields
if (empty($txnid) || empty($status) || empty($hash)) {
    logError("EaseBuzz Webhook - Missing required fields");
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
    exit;
}

try {
    // Fetch EaseBuzz configuration from static config
    require_once __DIR__ . '/../../../common/config/app-config.php';

    $config = getPaymentGatewayConfig('easebuzz');

    if (!$config) {
        logError("EaseBuzz Webhook - Gateway configuration not found");
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Gateway configuration not found']);
        exit;
    }

    $merchant_key = $config['api_key'];
    $merchant_salt = $config['api_secret'];
    $env = $config['environment'] ?? 'prod';

    // Verify hash signature
    $hash_string = $merchant_salt . '|' . $status;

    // Add all parameters in reverse order as per EaseBuzz documentation
    $hash_params = [
        'udf10',
        'udf9',
        'udf8',
        'udf7',
        'udf6',
        'udf5',
        'udf4',
        'udf3',
        'udf2',
        'udf1',
        'email',
        'firstname',
        'productinfo',
        'amount',
        'txnid',
        'key'
    ];

    foreach ($hash_params as $param) {
        $value = '';
        if ($param == 'key') {
            $value = $merchant_key;
        } else {
            $value = $webhook_data[$param] ?? '';
        }
        $hash_string .= '|' . $value;
    }

    $calculated_hash = hash('sha512', $hash_string);

    // Verify hash
    // Debug: log incoming webhook and hash values
    logError("EaseBuzz Webhook - Incoming POST: " . json_encode($webhook_data));
    logError("EaseBuzz Webhook - Calculated Hash: $calculated_hash | Received Hash: $hash");

    if ($hash !== $calculated_hash) {
        logError("EaseBuzz Webhook - Hash verification failed");
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Hash verification failed']);
        exit;
    }

    // Hash verified - Process the webhook
    // Check if this webhook was already processed
    $order = $dbOps->selectOne('tbl_payment_orders', ['id', 'status'], ['gateway_order_id' => $txnid]);

    if (!$order) {
        logError("EaseBuzz Webhook - Order not found for txnid: $txnid");
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Order not found']);
        exit;
    }

    // Check if already processed
    if ($order['status'] === 'completed') {
        logError("EaseBuzz Webhook - Order already processed | TxnID: $txnid");
        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => 'Already processed']);
        exit;
    }

    // Process based on status
    if ($status === 'success' || $status === 'completed') {
        // Payment successful
        $conn->beginTransaction();

        try {
            // Token payment recorded in tbl_payments

            // Generate receipt number using sequence helper
            $school_id = getStudentSchoolId($conn, $student_id);
            $academic_year = getCurrentAcademicYear($conn);
            $fee_type_for_seq = 'token_fee'; // Online payments currently assumed to be token fee

            $seq_result = getNextReceiptNumber($conn, $fee_type_for_seq, $school_id, $academic_year, $student_id);

            if ($seq_result['success']) {
                $receipt_no = $seq_result['receipt_no'];
            } else {
                // Fallback or Error
                logError("EaseBuzz Webhook - Failed to generate receipt number: " . ($seq_result['error'] ?? 'Unknown error'));
                // Use fallback format if sequence fails to avoid losing payment record
                $receipt_no = 'TKN-EZB-' . date('Ymd') . '-' . str_pad($student_id, 6, '0', STR_PAD_LEFT);
            }

            // Insert payment record

            $stmt = $conn->prepare("INSERT INTO tbl_payments 
                                   (student_id, receipt_no, amount, payment_date, payment_mode, 
                                    transaction_id, gateway_reference, payment_type, remarks, 
                                    status, created_at) 
                                   VALUES 
                                   (?, ?, ?, NOW(), 'online', ?, ?, 'token_fee', 'Payment via EaseBuzz Webhook', 'paid', NOW())");
            $stmt->execute([$student_id, $receipt_no, $amount, $transaction_id, $payment_id]);

            // Update order status
            $stmt = $conn->prepare("UPDATE tbl_payment_orders 
                                   SET status = 'completed', 
                                       payment_id = ?,
                                       completed_at = NOW()
                                   WHERE id = ?");
            $stmt->execute([$payment_id, $order['id']]);

            // Update pending payment
            $stmt = $conn->prepare("UPDATE tbl_pending_payments 
                                   SET status = 'completed',
                                       gateway_response = ?,
                                       updated_at = NOW()
                                   WHERE student_id = ? AND payment_type = 'token_fee' AND status = 'pending'");
            $stmt->execute([json_encode($webhook_data), $student_id]);

            // Auto-enroll student and assign fees if it's a token fee payment
            if ($payment_type === 'token_fee') {
                $enrollment_result = enrollStudentAfterTokenPayment($conn, $student_id);
                if ($enrollment_result['success'] && !empty($enrollment_result['enrollment_id'])) {
                    // Assign division and roll number automatically (if enabled in settings)
                    require_once '../common/settings_helper.php';
                    $auto_assign_enabled = getSetting($conn, 'auto_assign_division_on_enrollment', false);

                    if ($auto_assign_enabled) {
                        $division_result = assignDivisionAndRollNumber($conn, $enrollment_result['enrollment_id']);
                        if (!$division_result['success']) {
                            logError("Division assignment failed for student $student_id after webhook payment: " . $division_result['message']);
                        }
                    }
                }
            }

            $conn->commit();

            http_response_code(200);
            echo json_encode(['status' => 'success', 'message' => 'Payment processed successfully']);
        } catch (Exception $e) {
            $conn->rollBack();
            logError("EaseBuzz Webhook - Error processing payment: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Error processing payment']);
        }
    } elseif ($status === 'failure' || $status === 'failed') {
        // Payment failed
        $stmt = $conn->prepare("UPDATE tbl_payment_orders 
                               SET status = 'failed'
                               WHERE id = ?");
        $stmt->execute([$order['id']]);

        $stmt = $conn->prepare("UPDATE tbl_pending_payments 
                               SET status = 'failed',
                                   gateway_response = ?,
                                   updated_at = NOW()
                               WHERE student_id = ? AND payment_type = 'token_fee' AND status = 'pending'");
        $stmt->execute([json_encode($webhook_data), $student_id]);

        logError("EaseBuzz Webhook - Payment failed | TxnID: $txnid | Status: $status");

        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => 'Payment failure recorded']);
    } else {
        // Other status (pending, cancelled, etc.)
        logError("EaseBuzz Webhook - Unhandled status: $status | TxnID: $txnid");
        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => 'Status noted']);
    }
} catch (PDOException $e) {
    logError("EaseBuzz Webhook - Database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
} catch (Exception $e) {
    logError("EaseBuzz Webhook - Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server error']);
}
