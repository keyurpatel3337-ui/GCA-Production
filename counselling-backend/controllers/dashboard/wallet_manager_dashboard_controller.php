<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';

/**
 * Wallet Manager Dashboard Controller
 */

$base_path = dirname(dirname(__DIR__));

require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;

// Initialize Database Operations
$dbOps = new DatabaseOperations();

// Check if this is an API call
$is_api_call = defined('API_MODE') || (isset($_GET['route']) && !empty($_GET['route']));

// For API calls, skip session/role checks and just return data
if (!$is_api_call) {
    if (!hasRole(ROLE_WALLET_MANAGER) && !hasRole(ROLE_SUPER_ADMIN)) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

// Function to call Wallet API
function callWalletSummaryAPI($endpoint, $method = 'GET', $data = [])
{
    if (!defined('WALLET_API_URL')) {
        return ['status' => 'error', 'message' => 'Wallet API URL not defined'];
    }

    $url = WALLET_API_URL . $endpoint;
    $ch = curl_init();

    if ($method === 'GET' && !empty($data)) {
        $url .= '?' . http_build_query($data);
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

    if ($error)
        return ['status' => 'error', 'message' => $error];
    return json_decode($response, true);
}

// Get wallet statistics via API
$stats = [];
try {
    // Attempt to fetch global summary from Wallet API
    $summary_response = callWalletSummaryAPI('/summary/overview.php');

    if ($summary_response && isset($summary_response['status']) && $summary_response['status'] === 'success') {
        $stats = $summary_response['data'];
    } else {
        // Fallback or empty stats
        $stats = [
            'total_balance' => 0,
            'deposits_today' => 0,
            'transactions_today' => 0,
            'active_wallets' => 0
        ];
    }

    $success = true;
    $error = null;
} catch (Exception $e) {
    $success = false;
    $error = $e->getMessage();
}

// If API call, return JSON response
if ($is_api_call) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'data' => $stats,
        'error' => $error
    ]);
    exit;
}

// For direct inclusion
$total_balance = $stats['total_balance'] ?? 0;
$deposits_today = $stats['deposits_today'] ?? 0;
$transactions_today = $stats['transactions_today'] ?? 0;
$active_wallets = $stats['active_wallets'] ?? 0;

$page_title = "Wallet Manager Dashboard";
$page_breadcrumb = "Dashboard";
