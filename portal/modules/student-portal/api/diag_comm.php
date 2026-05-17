<?php
require_once dirname(dirname(dirname(dirname(dirname(__DIR__))))) . '/common/constants.php';
require_once ENV_CONFIG_FILE;

echo "WALLET_API_URL: " . (defined('WALLET_API_URL') ? WALLET_API_URL : "NOT DEFINED") . "\n";
echo "GCA_PORTAL_KEY: " . (defined('GCA_PORTAL_KEY') ? GCA_PORTAL_KEY : "NOT DEFINED") . "\n";

$url = WALLET_API_URL . '/topup/initiate.php';
$data = ['student_id' => '1267', 'amount' => 10];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-API-KEY: ' . (defined('GCA_PORTAL_KEY') ? GCA_PORTAL_KEY : '')
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$response = curl_exec($ch);
$error = curl_error($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

echo "HTTP Code: $http_code\n";
if ($error) {
    echo "cURL Error: $error\n";
} else {
    echo "Response: $response\n";
}
?>
