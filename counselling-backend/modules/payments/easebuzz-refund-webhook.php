<?php

/**
 * EaseBuzz Refund Webhook Handler
 * Receives refund notifications from EaseBuzz
 * URL: https://yourdomain.com/[your-project]/portal/modules/payments/easebuzz-refund-webhook.php
 */

header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../../common/bootstrap.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once HELPER_ERROR_LOGGER;

// Get raw POST data
$raw_post_data = file_get_contents('php://input');

// EaseBuzz sends refund data as POST parameters
$webhook_data = $_POST;

if (empty($webhook_data)) {
    logError("EaseBuzz Refund Webhook - No POST data received");
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'No data received']);
    exit;
}

// Extract refund webhook parameters
$txnid = $webhook_data['txnid'] ?? '';
$refund_id = $webhook_data['refund_id'] ?? '';
$refund_amount = $webhook_data['refund_amount'] ?? '';
$refund_status = $webhook_data['refund_status'] ?? '';
$bank_ref_num = $webhook_data['bank_ref_num'] ?? '';
$payment_id = $webhook_data['easepayid'] ?? '';
$hash = $webhook_data['hash'] ?? '';

// Validate required fields
if (empty($txnid) || empty($refund_id) || empty($hash)) {
    logError("EaseBuzz Refund Webhook - Missing required fields");
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
    exit;
}

try {
    // Fetch EaseBuzz configuration from static config
    require_once __DIR__ . '/../../../common/config/app-config.php';

    $config = getPaymentGatewayConfig('easebuzz');

    if (!$config) {
        logError("EaseBuzz Refund Webhook - Gateway configuration not found");
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Gateway configuration not found']);
        exit;
    }

    $merchant_key = $config['api_key'];
    $merchant_salt = $config['api_secret'];

    // Verify hash signature for refund
    // Hash format: salt|refund_status|refund_amount|refund_id|txnid|key
    $hash_string = $merchant_salt . '|' . $refund_status . '|' . $refund_amount . '|' . $refund_id . '|' . $txnid . '|' . $merchant_key;
    $calculated_hash = hash('sha512', $hash_string);

    // Verify hash
    if ($hash !== $calculated_hash) {
        logError("EaseBuzz Refund Webhook - Hash verification failed | Received: $hash | Calculated: $calculated_hash");
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Hash verification failed']);
        exit;
    }

    // Hash verified - Process the refund webhook
    // Find the original payment
    $stmt = $conn->prepare("SELECT p.*, po.student_id 
                           FROM tbl_payments p
                           JOIN tbl_payment_orders po ON p.transaction_id = po.transaction_id
                           WHERE p.gateway_reference = ? OR po.gateway_order_id = ?");
    $stmt->execute([$payment_id, $txnid]);
    $payment = $stmt->fetch();

    if (!$payment) {
        logError("EaseBuzz Refund Webhook - Payment not found for txnid: $txnid");
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Payment not found']);
        exit;
    }

    $conn->beginTransaction();

    try {
        // Update refund tracking in tbl_refund_requests (consolidated - no separate tbl_refunds)
        $stmt = $conn->prepare("UPDATE tbl_refund_requests 
                               SET request_status = ?,
                                   gateway_refund_id = ?,
                                   gateway_reference = ?,
                                   bank_reference = ?,
                                   refund_data = ?,
                                   processed_at = NOW()
                               WHERE payment_id = ?
                               ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([
            $refund_status,
            $refund_id,
            $payment_id,
            $bank_ref_num,
            json_encode($webhook_data),
            $payment['id']
        ]);

        // Update payment status if refund is successful
        if ($refund_status === 'success' || $refund_status === 'completed') {
            $stmt = $conn->prepare("UPDATE tbl_payments 
                                   SET status = 'refunded',
                                       remarks = CONCAT(remarks, ' | Refund: ', ?, ' (', ?, ')'),
                                       updated_at = NOW()
                                   WHERE id = ?");
            $stmt->execute([$refund_amount, $refund_id, $payment['id']]);

            // Token fee records are part of tbl_payments. No need to update tbl_gm_std_registration
        } elseif ($refund_status === 'failed' || $refund_status === 'failure') {
            // Log failed refund attempt
            $stmt = $conn->prepare("UPDATE tbl_payments 
                                   SET remarks = CONCAT(remarks, ' | Refund Failed: ', ?, ' (', ?, ')'),
                                       updated_at = NOW()
                                   WHERE id = ?");
            $stmt->execute([$refund_amount, $refund_id, $payment['id']]);

            logError("EaseBuzz Refund Webhook - Refund failed | Refund ID: $refund_id | TxnID: $txnid");
        }

        $conn->commit();

        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => 'Refund webhook processed']);
    } catch (Exception $e) {
        $conn->rollBack();
        logError("EaseBuzz Refund Webhook - Error processing refund: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Error processing refund']);
    }
} catch (PDOException $e) {
    logError("EaseBuzz Refund Webhook - Database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
} catch (Exception $e) {
    logError("EaseBuzz Refund Webhook - Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server error']);
}


