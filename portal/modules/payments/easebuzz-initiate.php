<?php

/**
 * Unified EaseBuzz Payment Initiation Handler
 * Replaces easebuzz-payment.php and easebuzz-pending-payment.php
 */

require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once __DIR__ . '/../../common/api_client.php';
require_once __DIR__ . '/../../common/transaction_helper.php';
require_once __DIR__ . '/../../common/services/EaseBuzzService.php';
require_once __DIR__ . '/../../../common/helpers/error_logger.php';

// Variables expected from including script (satisfying IDE/Linter):
/** @var int $student_id */
/** @var float|string $amount */
/** @var array $student */
/** @var string $payment_type */
/** @var string $product_info */
/** @var int|null $installment_id */
/** @var array|null $session_splits */
/** @var array|null $session_component_breakdown */
/** @var PDO $conn */

try {
    $ebService = new EaseBuzzService();

    // Generate Transaction ID
    $transaction_id = generateUniqueTransactionID('GM');
    $txnid = $transaction_id;

    // Calculate Split Amounts
    $split_amounts = $ebService->getSplitAmounts($conn, $student_id, $payment_type, $amount, $session_splits ?? null);
    $split_amounts_json = !empty($split_amounts) ? json_encode($split_amounts) : '';

    // Format Basic Params
    $surl = PORTAL_URL . '/modules/payments/easebuzz-callback-handler.php';
    $furl = PORTAL_URL . '/modules/payments/easebuzz-callback-handler.php';

    $payment_params = EaseBuzzService::formatBasicParams($student, $amount, $txnid, $product_info, $surl, $furl);

    // Add UDFs for callback processing
    $payment_params['udf1'] = (string) $student_id;
    $payment_params['udf2'] = $payment_type;
    $payment_params['udf3'] = $transaction_id;
    $payment_params['udf4'] = $payment_type; // Fee component
    $payment_params['udf5'] = $payment_params['productinfo'];
    $payment_params['udf6'] = isset($installment_id) ? (string) $installment_id : '';
    $payment_params['udf7'] = !empty($_SESSION['payment_redirect_url']) ? 'ledger' : '';
    $payment_params['udf8'] = '';
    $payment_params['udf9'] = '';
    $payment_params['udf10'] = '';

    // Add Split Payment Param if applicable
    if (!empty($split_amounts)) {
        $formatted_split = [];
        foreach ($split_amounts as $label => $amt) {
            $formatted_split[$label] = number_format((float) $amt, 2, '.', '');
        }
        $payment_params['split_payments'] = json_encode($formatted_split);
    }

    $component_breakdown_json = !empty($session_component_breakdown) ? json_encode($session_component_breakdown) : null;

    // Store Order in Database
    $created_by = $_SESSION['user_id'] ?? 1;
    $stmt = $conn->prepare("INSERT INTO tbl_payment_orders 
                           (student_id, order_id, transaction_id, gateway_order_id, 
                            amount, split_amounts, component_breakdown, payment_gateway, status, created_by, created_at) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, 'easebuzz', 'created', ?, NOW())
                           ON DUPLICATE KEY UPDATE 
                           gateway_order_id = VALUES(gateway_order_id),
                           amount = VALUES(amount),
                           split_amounts = VALUES(split_amounts),
                           component_breakdown = VALUES(component_breakdown),
                           updated_at = NOW()");
    $stmt->execute([$student_id, $txnid, $transaction_id, $txnid, $amount, $split_amounts_json, $component_breakdown_json, $created_by]);

    // Log Initiation
    logGatewayActivity("Initiating EaseBuzz Payment | TxnID: $txnid | Amount: $amount", 'INITIATE', $payment_params);

    // Initiate and Redirect
    $payment_url = $ebService->initiatePayment($payment_params);
    header('Location: ' . $payment_url);
    exit;

} catch (Exception $e) {
    logGatewayActivity("EaseBuzz Initiation Error: " . $e->getMessage(), 'ERROR', ['txnid' => $txnid ?? 'unknown']);
    error_log("EaseBuzz Initiation Error: " . $e->getMessage());
    set_flash_message('error', "Payment gateway error: " . $e->getMessage());

    if ($payment_type === 'token_fee') {
        header('Location: ../student-portal/token-fee-payment.php');
    } else {
        header('Location: ../parent-portal/dashboard.php');
    }
    exit;
}
