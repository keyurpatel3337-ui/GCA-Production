<?php
define('APP_INIT', true);
require_once 'c:\xampp\htdocs\GCA-Production\env.config.php';

$key = EASEBUZZ_MERCHANT_KEY;
$salt = EASEBUZZ_SALT;

// Transaction details to simulate
$txnid = 'GM20260512132911F98FAB4182';
$amount = '95440.00';
$status = 'success';
$easepayid = 'E' . date('Ymd') . 'SIMULATED';
$firstname = 'KRISHABAHEN';
$email = 'krishbaraiya@gmail.com';
$phone = '9428787594';
$udf1 = '2286'; // student_id
$udf2 = 'MultipleFees';
$udf3 = $txnid;
$udf4 = '';
$udf5 = 'Multiple Fee Components';

// Build hash string in reverse order
// salt|status|udf10|udf9|udf8|udf7|udf6|udf5|udf4|udf3|udf2|udf1|email|firstname|productinfo|amount|txnid|key
$hash_string = $salt . '|' . $status . '||||||' . $udf5 . '|' . $udf4 . '|' . $udf3 . '|' . $udf2 . '|' . $udf1 . '|' . $email . '|' . $firstname . '|Fees|' . $amount . '|' . $txnid . '|' . $key;
$hash = hash('sha512', $hash_string);

$post_data = [
    'key' => $key,
    'txnid' => $txnid,
    'amount' => $amount,
    'status' => $status,
    'easepayid' => $easepayid,
    'firstname' => $firstname,
    'email' => $email,
    'phone' => $phone,
    'productinfo' => 'Fees',
    'hash' => $hash,
    'udf1' => $udf1,
    'udf2' => $udf2,
    'udf3' => $udf3,
    'udf4' => $udf4,
    'udf5' => $udf5,
];

echo "Simulating Webhook for TxnID: $txnid\n";
echo "Target URL: https://gyanmanjari.com/portal/modules/payments/easebuzz-webhook.php\n";

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => "http://localhost/GCA-Production/portal/modules/payments/easebuzz-webhook.php",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($post_data),
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/x-www-form-urlencoded"
    ],
]);

$response = curl_exec($curl);
$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

echo "HTTP Code: $http_code\n";
echo "Response: $response\n";
