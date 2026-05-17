<?php
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/common/constants.php';
require_once __DIR__ . '/../../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;

header('Content-Type: application/json');

if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_ACCOUNTANT, ROLE_PRINCIPLE, ROLE_WALLET_MANAGER])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';

// Helper for Wallet API calls
function callWalletAPI($endpoint, $method = 'POST', $data = [])
{
    if (!defined('WALLET_API_URL')) {
        return ['status' => 'error', 'message' => 'Wallet API URL not defined'];
    }

    $url = WALLET_API_URL . $endpoint;
    $ch = curl_init();

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

    if ($error)
        return ['status' => 'error', 'message' => $error];
    return json_decode($response, true);
}

if ($action === 'manual-deposit') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['student_id']) || empty($input['amount'])) {
        echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
        exit;
    }

    // Call Wallet API Manual Deposit endpoint
    $response = callWalletAPI('/wallet/manual-deposit.php', 'POST', [
        'student_id' => $input['student_id'],
        'amount' => $input['amount'],
        'admin_id' => $_SESSION['user_id'] ?? 'Admin',
        'role' => $_SESSION['role_name'] ?? 'Admin',
        'note' => $input['note'] ?? 'Manual deposit from portal'
    ]);

    echo json_encode($response);
} elseif ($action === 'initiate-online-topup') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['student_id']) || empty($input['amount'])) {
        echo json_encode(['status' => 'error', 'message' => 'Missing student ID or amount']);
        exit;
    }

    $response = callWalletAPI('/topup/initiate.php', 'POST', [
        'student_id' => $input['student_id'],
        'amount' => floatval($input['amount'])
    ]);

    echo json_encode($response);
} elseif ($action === 'transaction-history') {
    $data = [
        'start_date' => $_GET['start_date'] ?? null,
        'end_date' => $_GET['end_date'] ?? null,
        'type' => $_GET['type'] ?? null,
        'student_id' => $_GET['student_id'] ?? null
    ];

    $response = callWalletAPI('/transaction/history.php', 'GET', array_filter($data));
    echo json_encode($response);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
}
