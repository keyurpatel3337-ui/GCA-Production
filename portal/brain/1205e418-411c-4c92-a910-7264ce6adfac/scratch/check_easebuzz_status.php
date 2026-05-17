<?php
define('APP_INIT', true);
require_once 'c:\xampp\htdocs\GCA-Production\env.config.php';

$key = EASEBUZZ_MERCHANT_KEY;
$salt = EASEBUZZ_SALT;
$txnid = 'GM2026050611334369FAD9BF221BF8668';
$amount = '10000.00';
$email = 'savliyamanan21@gmail.com';
$phone = '9978423405';

// Correct Hash Sequence for Retrieval: key|txnid|amount|email|phone|salt
$hash_str = "$key|$txnid|10000.0|$email|$phone|$salt";
$hash = hash('sha512', $hash_str);

$post_data = http_build_query([
    'key' => $key,
    'txnid' => $txnid,
    'amount' => $amount,
    'email' => $email,
    'phone' => $phone,
    'hash' => $hash
]);

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => "https://dashboard.easebuzz.in/transaction/v1/retrieve",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $post_data,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/x-www-form-urlencoded",
        "Accept: application/json"
    ],
]);

$response = curl_exec($curl);
$err = curl_error($curl);
curl_close($curl);

if ($err) {
    echo "cURL Error #:" . $err;
} else {
    header('Content-Type: application/json');
    echo $response;
}
