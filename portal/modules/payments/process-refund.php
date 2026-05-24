<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once dirname(dirname(__DIR__)) . '/common/globalvariable.php';
require_once __DIR__ . '/../../../common/helpers/error_logger.php';

header('Content-Type: application/json');

if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPAL)) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$txnid = $_POST['txnid'] ?? '';
$refund_amount = $_POST['refund_amount'] ?? '';
$amount = $_POST['amount'] ?? ''; // Original amount
$phone = $_POST['phone'] ?? '';
$email = $_POST['email'] ?? '';
$reason = $_POST['reason'] ?? '';

if (empty($txnid) || empty($refund_amount) || empty($phone) || empty($email)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required parameters']);
    exit;
}

try {
    // Load EaseBuzz Library
    require_once __DIR__ . '/../../easebuzz-lib/easebuzz_payment_gateway.php';
    require_once __DIR__ . '/../../../common/config/app-config.php';

    $config = getPaymentGatewayConfig('easebuzz');
    if (!$config) {
        throw new Exception("Gateway configuration not found");
    }

    $merchant_key = $config['api_key'];
    $merchant_salt = $config['api_secret'];
    $env = $config['environment'] ?? 'prod';

    $easebuzzObj = new Easebuzz($merchant_key, $merchant_salt, $env);

    $postData = array(
        "txnid" => $txnid,
        "refund_amount" => number_format((float)$refund_amount, 2, '.', ''),
        "phone" => $phone,
        "email" => $email,
        "amount" => number_format((float)$amount, 2, '.', '')
    );

    logGatewayActivity("Initiating Refund for TxnID: $txnid | Amount: $refund_amount", 'INITIATE', $postData);

    $result_json = $easebuzzObj->refundAPI($postData);
    $result = json_decode($result_json, true);

    if (isset($result['status']) && $result['status'] == 1) {
        // Refund Successful in EaseBuzz API
        logGatewayActivity("Refund Success for TxnID: $txnid", 'SUCCESS', $result);
        
        // Update local records
        $conn->beginTransaction();
        
        // Mark payment as refunded (Optional: You might want a partial refund logic here)
        $stmt = $conn->prepare("UPDATE tbl_payments SET remarks = CONCAT(remarks, ' | Refunded ₹', ?, ' on ', NOW(), ' Reason: ', ?) WHERE transaction_id = ?");
        $stmt->execute([$refund_amount, $reason, $txnid]);
        
        $conn->commit();

        echo json_encode([
            'status' => 'success', 
            'message' => 'Refund processed successfully',
            'txn_id' => $txnid,
            'easebuzz_response' => $result
        ]);
    } else {
        // Refund Failed
        $error_msg = $result['data'] ?? ($result['error_desc'] ?? 'Unknown error from EaseBuzz');
        logGatewayActivity("Refund Failed for TxnID: $txnid | Error: $error_msg", 'FAILED', $result);
        
        echo json_encode([
            'status' => 'error', 
            'message' => $error_msg
        ]);
    }

} catch (Exception $e) {
    logGatewayActivity("Refund Exception for TxnID: $txnid | Error: " . $e->getMessage(), 'ERROR');
    echo json_encode(['status' => 'error', 'message' => 'Server Error: ' . $e->getMessage()]);
}
