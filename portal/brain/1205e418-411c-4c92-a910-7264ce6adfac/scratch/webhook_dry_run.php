<?php
/**
 * DRY RUN: EaseBuzz Webhook Logic Verification
 */

require_once 'c:\xampp\htdocs\GCA-Production\portal\session_config.php';
require_once 'c:\xampp\htdocs\GCA-Production\common\constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;

echo "--- STARTING DRY RUN ---\n";

// 1. Mock Webhook Payload
$mock_payload = [
    'txnid' => 'DRYRUN_' . time(),
    'status' => 'success',
    'amount' => '100.00',
    'easepayid' => 'E20260512DRYRUN',
    'key' => '0A3P****',
    'hash' => '',
    'udf1' => '2286',
    'udf2' => 'token_fee',
    'udf3' => 'GM_DRYRUN_TXN_001',
    'udf4' => '',
    'email' => 'test@example.com',
    'firstname' => 'DryRunUser',
    'productinfo' => 'Fees'
];

// 2. Fetch Config
require_once 'c:\xampp\htdocs\GCA-Production\common\config\app-config.php';
$config = getPaymentGatewayConfig('easebuzz');
$salt = $config['api_secret'];
$key = $config['api_key'];

// 3. Hash Calculation
$hashString = $salt . '|' .
    ($mock_payload['status'] ?? '') . '|' .
    ($mock_payload['udf10'] ?? '') . '|' .
    ($mock_payload['udf9'] ?? '') . '|' .
    ($mock_payload['udf8'] ?? '') . '|' .
    ($mock_payload['udf7'] ?? '') . '|' .
    ($mock_payload['udf6'] ?? '') . '|' .
    ($mock_payload['udf5'] ?? '') . '|' .
    ($mock_payload['udf4'] ?? '') . '|' .
    ($mock_payload['udf3'] ?? '') . '|' .
    ($mock_payload['udf2'] ?? '') . '|' .
    ($mock_payload['udf1'] ?? '') . '|' .
    ($mock_payload['email'] ?? '') . '|' .
    ($mock_payload['firstname'] ?? '') . '|' .
    ($mock_payload['productinfo'] ?? '') . '|' .
    ($mock_payload['amount'] ?? '') . '|' .
    ($mock_payload['txnid'] ?? '') . '|' .
    ($mock_payload['key'] ?? $key);

$calculatedHash = hash('sha512', $hashString);
echo "1. Hash Calculation: [OK]\n";
echo "   String Start: " . substr($hashString, 0, 30) . "...\n";

// 4. Test DB
try {
    $conn->beginTransaction();
    echo "2. DB Transaction Started\n";

    $stmt = $conn->prepare("SELECT id, student_name FROM tbl_gm_std_registration WHERE id = ?");
    $stmt->execute([$mock_payload['udf1']]);
    $student = $stmt->fetch();
    echo "   Student Search: " . ($student ? "Found " . $student['student_name'] : "NOT FOUND") . "\n";

    $stmt_check = $conn->prepare("SELECT id FROM tbl_payments WHERE transaction_id = ?");
    $stmt_check->execute([$mock_payload['udf3']]);
    echo "   Duplicate Check: " . ($stmt_check->fetch() ? "Exists" : "New Record") . "\n";

    $conn->rollBack();
    echo "3. DB Transaction ROLLED BACK.\n";
    echo "--- DRY RUN SUCCESSFUL ---\n";
} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo "   [ERROR] " . $e->getMessage() . "\n";
}
