<?php
// CRITICAL: Prevent any HTML output before JSON
ini_set('display_errors', 0);
ini_set('html_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/wallet_debug.log');

require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/common/constants.php';
require_once __DIR__ . '/../../../session_config.php';
require_once ENV_CONFIG_FILE;

// Check if student is logged in
if (!isset($_SESSION['is_student_login']) || $_SESSION['is_student_login'] !== true) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? $_SESSION['student_id'] ?? '';
$action = $_GET['action'] ?? '';

error_log("Wallet Action Triggered: Action=$action, UserID=$user_id");

header('Content-Type: application/json');

// Helper for API calls
function callWalletAPI($endpoint, $method = 'GET', $data = [])
{
    if (!defined('WALLET_API_URL')) {
        return ['status' => 'error', 'message' => 'Wallet API URL not defined'];
    }

    $url = WALLET_API_URL . $endpoint;
    $ch = curl_init();

    if ($method === 'GET' && !empty($data)) {
        $url .= (strpos($url, '?') !== false ? '&' : '?') . http_build_query($data);
    }

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-API-KEY: ' . (defined('GCA_PORTAL_KEY') ? GCA_PORTAL_KEY : '')
        ]);
    } else {
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-API-KEY: ' . (defined('GCA_PORTAL_KEY') ? GCA_PORTAL_KEY : '')
        ]);
    }

    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($error) {
        error_log("Wallet API cURL Error ($url): " . $error);
        return ['status' => 'error', 'message' => 'API Connection Error: ' . $error];
    }

    if ($http_code >= 400) {
        error_log("Wallet API HTTP Error ($url) - Code: $http_code, Response: $response");
    }

    $decoded = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Wallet API JSON Parse Error ($url): " . $response);
        return ['status' => 'error', 'message' => 'Invalid API Response Format', 'raw_response' => $response];
    }

    return $decoded;
}

// Routes
if ($action === 'topup') {
    // SECURITY: Online deposits are disabled
    echo json_encode(['status' => 'error', 'message' => 'Online deposits are temporarily unavailable. Please visit the accounts office.']);
    exit;

    $input = json_decode(file_get_contents('php://input'), true);
    $amount = floatval($input['amount'] ?? 0);

    if ($amount <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid amount']);
        exit;
    }

    $response = callWalletAPI('/topup/initiate.php', 'POST', [
        'student_id' => $user_id,
        'amount' => $amount
    ]);

    echo json_encode($response);
} elseif ($action === 'update-pin') {
    $input = json_decode(file_get_contents('php://input'), true);
    $pin = $input['pin'] ?? '';

    if (strlen($pin) !== 4 || !is_numeric($pin)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid PIN format']);
        exit;
    }

    $response = callWalletAPI('/wallet/update-pin.php', 'POST', [
        'student_id' => $user_id,
        'pin' => $pin
    ]);

    echo json_encode($response);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
}
