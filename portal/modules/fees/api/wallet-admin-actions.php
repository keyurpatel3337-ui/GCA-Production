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
function callWalletAPI(string $endpoint, string $method = 'POST', array $data = []): array
{
    if (!defined('WALLET_API_URL')) {
        return ['status' => 'error', 'message' => 'Wallet API URL not defined'];
    }

    $url = WALLET_API_URL . $endpoint;
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

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

    $decoded = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'status' => 'error',
            'message' => 'Invalid Wallet API Response Format',
            'raw_response' => $response
        ];
    }
    return $decoded;
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

    if (isset($response['status']) && $response['status'] === 'success' && isset($response['data']['transactions'])) {
        $student_ids = array_unique(array_filter(array_column($response['data']['transactions'], 'student_id')));
        $student_names = [];
        
        if (isset($conn) && $conn instanceof \PDO && !empty($student_ids)) {
            $placeholders = implode(',', array_fill(0, count($student_ids), '?'));
            $stmt = $conn->prepare("
                SELECT e.enrollment_no, r.student_name, r.surname, r.fathers_name 
                FROM tbl_enrolled_students e
                JOIN tbl_gm_std_registration r ON e.registration_id = r.id 
                WHERE e.enrollment_no IN ($placeholders)
            ");
            $stmt->execute($student_ids);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $fullName = trim(($row['surname'] ?? '') . ' ' . ($row['student_name'] ?? '') . ' ' . ($row['fathers_name'] ?? ''));
                $student_names[$row['enrollment_no']] = $fullName;
            }
        }

        foreach ($response['data']['transactions'] as &$tx) {
            $sid = $tx['student_id'] ?? '';
            $tx['student_name'] = !empty($student_names[$sid]) ? $student_names[$sid] : 'Student #' . $sid;
            $tx['type'] = $tx['tx_type'] ?? 'DEBIT';
            $tx['receipt_ref'] = $tx['reference_id'] ?? 'N/A';
            $tx['status'] = 'SUCCESS'; // Ledger transactions are always successful
        }
        unset($tx);
    }

    echo json_encode($response);
} elseif ($action === 'generate-report') {
    $reportType = $_GET['report_type'] ?? 'tx_history';
    $startDate = $_GET['start_date'] ?? '';
    $endDate = $_GET['end_date'] ?? '';
    $txType = $_GET['tx_type'] ?? 'all';
    $studentSearch = $_GET['student_search'] ?? '';

    try {
        $wallet_conn = new PDO(
            "mysql:host=" . EXT_DB_HOST . ";dbname=student_wallet;charset=utf8mb4",
            EXT_DB_USER,
            EXT_DB_PASS
        );
        $wallet_conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $wallet_conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to connect to wallet database: ' . $e->getMessage()]);
        exit;
    }

    $reportData = [];

    switch ($reportType) {
        case 'day_book':
            $sql = "SELECT 
                        DATE(created_at) AS report_date,
                        SUM(CASE WHEN tx_type = 'CREDIT' AND description NOT LIKE '%Refund%' THEN amount ELSE 0 END) AS total_credits,
                        SUM(CASE WHEN tx_type = 'DEBIT' THEN amount ELSE 0 END) AS total_debits,
                        SUM(CASE WHEN tx_type = 'CREDIT' AND description LIKE '%Refund%' THEN amount ELSE 0 END) AS total_refunds,
                        COUNT(tx_id) AS tx_count
                    FROM wallet_transactions";
            
            $conditions = [];
            $params = [];
            if (!empty($startDate)) {
                $conditions[] = "created_at >= ?";
                $params[] = $startDate . ' 00:00:00';
            }
            if (!empty($endDate)) {
                $conditions[] = "created_at <= ?";
                $params[] = $endDate . ' 23:59:59';
            }
            if (!empty($conditions)) {
                $sql .= " WHERE " . implode(" AND ", $conditions);
            }
            $sql .= " GROUP BY DATE(created_at) ORDER BY report_date DESC LIMIT 100";
            
            $stmt = $wallet_conn->prepare($sql);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll();
            break;

        case 'deposit_summary':
            $sql = "SELECT 
                        CASE 
                            WHEN merchant_id = 'EASEBUZZ' OR description LIKE '%Online%' THEN 'Online (Easebuzz)'
                            ELSE 'Offline (Cash/Cheque)'
                        END AS deposit_mode,
                        COUNT(*) AS total_count,
                        SUM(amount) AS total_amount,
                        AVG(amount) AS avg_amount
                    FROM wallet_transactions
                    WHERE tx_type = 'CREDIT' AND description NOT LIKE '%Refund%'";
            
            $params = [];
            if (!empty($startDate)) {
                $sql .= " AND created_at >= ?";
                $params[] = $startDate . ' 00:00:00';
            }
            if (!empty($endDate)) {
                $sql .= " AND created_at <= ?";
                $params[] = $endDate . ' 23:59:59';
            }
            $sql .= " GROUP BY deposit_mode";
            
            $stmt = $wallet_conn->prepare($sql);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll();
            break;

        case 'merchant_settlement':
            $sql = "SELECT 
                        merchant_id,
                        COUNT(*) AS sales_count,
                        SUM(amount) AS total_sales_volume,
                        MIN(created_at) AS first_sale,
                        MAX(created_at) AS last_sale
                    FROM wallet_transactions
                    WHERE tx_type = 'DEBIT'";
            
            $params = [];
            if (!empty($startDate)) {
                $sql .= " AND created_at >= ?";
                $params[] = $startDate . ' 00:00:00';
            }
            if (!empty($endDate)) {
                $sql .= " AND created_at <= ?";
                $params[] = $endDate . ' 23:59:59';
            }
            $sql .= " GROUP BY merchant_id ORDER BY total_sales_volume DESC";
            
            $stmt = $wallet_conn->prepare($sql);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll();
            break;

        case 'low_balance':
            $sql = "SELECT 
                        student_id,
                        current_balance,
                        daily_limit,
                        status
                    FROM wallet_accounts
                    WHERE current_balance < 100.00 AND status = 'ACTIVE'
                    ORDER BY current_balance ASC
                    LIMIT 100";
            
            $stmt = $wallet_conn->prepare($sql);
            $stmt->execute();
            $reportData = $stmt->fetchAll();
            
            if (!empty($reportData) && isset($conn) && $conn instanceof \PDO) {
                $student_ids = array_column($reportData, 'student_id');
                $placeholders = implode(',', array_fill(0, count($student_ids), '?'));
                $s_stmt = $conn->prepare("
                    SELECT e.enrollment_no, r.student_name, r.surname, r.fathers_name 
                    FROM tbl_enrolled_students e
                    JOIN tbl_gm_std_registration r ON e.registration_id = r.id 
                    WHERE e.enrollment_no IN ($placeholders)
                ");
                $s_stmt->execute($student_ids);
                $names_map = [];
                while ($row = $s_stmt->fetch(PDO::FETCH_ASSOC)) {
                    $names_map[$row['enrollment_no']] = trim(($row['surname'] ?? '') . ' ' . ($row['student_name'] ?? '') . ' ' . ($row['fathers_name'] ?? ''));
                }
                foreach ($reportData as &$row) {
                    $row['student_name'] = $names_map[$row['student_id']] ?? 'Student #' . $row['student_id'];
                }
                unset($row);
            }
            break;

        case 'refund_dispute':
            $sql = "SELECT 
                        wt.tx_id,
                        wa.student_id,
                        wt.amount,
                        wt.reference_id AS original_ref,
                        wt.description AS refund_reason,
                        wt.created_at AS refund_date
                    FROM wallet_transactions wt
                    JOIN wallet_accounts wa ON wt.wallet_id = wa.wallet_id
                    WHERE wt.tx_type = 'CREDIT' AND wt.description LIKE '%Refund%'";
            
            $params = [];
            if (!empty($startDate)) {
                $sql .= " AND wt.created_at >= ?";
                $params[] = $startDate . ' 00:00:00';
            }
            if (!empty($endDate)) {
                $sql .= " AND wt.created_at <= ?";
                $params[] = $endDate . ' 23:59:59';
            }
            $sql .= " ORDER BY wt.created_at DESC LIMIT 100";
            
            $stmt = $wallet_conn->prepare($sql);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll();
            
            if (!empty($reportData) && isset($conn) && $conn instanceof \PDO) {
                $student_ids = array_unique(array_column($reportData, 'student_id'));
                $names_map = getNamesMap($conn, $student_ids);
                foreach ($reportData as &$row) {
                    $sid = $row['student_id'];
                    $fullName = $names_map[$sid] ?? '';
                    $row['student_name'] = !empty($fullName) ? $fullName : (stripos($sid, 'EMP-') === 0 ? 'Staff #' . $sid : 'Student #' . $sid);
                }
                unset($row);
            }
            break;

        case 'gateway_pipeline':
            $sql = "SELECT 
                        wt.topup_id,
                        wa.student_id,
                        wt.amount,
                        wt.gateway_ref,
                        wt.status,
                        wt.created_at
                    FROM wallet_topups wt
                    JOIN wallet_accounts wa ON wt.wallet_id = wa.wallet_id";
            
            $conditions = [];
            $params = [];
            if (!empty($startDate)) {
                $conditions[] = "wt.created_at >= ?";
                $params[] = $startDate . ' 00:00:00';
            }
            if (!empty($endDate)) {
                $conditions[] = "wt.created_at <= ?";
                $params[] = $endDate . ' 23:59:59';
            }
            if (!empty($conditions)) {
                $sql .= " WHERE " . implode(" AND ", $conditions);
            }
            $sql .= " ORDER BY wt.created_at DESC LIMIT 100";
            
            $stmt = $wallet_conn->prepare($sql);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll();
            
            if (!empty($reportData) && isset($conn) && $conn instanceof \PDO) {
                $student_ids = array_unique(array_column($reportData, 'student_id'));
                $names_map = getNamesMap($conn, $student_ids);
                foreach ($reportData as &$row) {
                    $sid = $row['student_id'];
                    $fullName = $names_map[$sid] ?? '';
                    $row['student_name'] = !empty($fullName) ? $fullName : (stripos($sid, 'EMP-') === 0 ? 'Staff #' . $sid : 'Student #' . $sid);
                }
                unset($row);
            }
            break;

        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid report type']);
            exit;
    }

    echo json_encode([
        'status' => 'success',
        'report_type' => $reportType,
        'data' => $reportData
    ]);
} elseif ($action === 'list-accounts') {
    try {
        $wallet_conn = new PDO(
            "mysql:host=" . EXT_DB_HOST . ";dbname=student_wallet;charset=utf8mb4",
            EXT_DB_USER,
            EXT_DB_PASS
        );
        $wallet_conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $wallet_conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to connect to wallet database: ' . $e->getMessage()]);
        exit;
    }

    $searchTerm = $_GET['search'] ?? '';
    
    // Fetch all wallet accounts
    $sql = "SELECT * FROM wallet_accounts";
    $stmt = $wallet_conn->query($sql);
    $accounts = $stmt->fetchAll();

    if (!empty($accounts) && isset($conn) && $conn instanceof \PDO) {
        // Map GCA portal names
        $student_ids = array_column($accounts, 'student_id');
        $placeholders = implode(',', array_fill(0, count($student_ids), '?'));
        
        $s_sql = "
            SELECT e.enrollment_no, r.student_name, r.surname, r.fathers_name 
            FROM tbl_enrolled_students e
            JOIN tbl_gm_std_registration r ON e.registration_id = r.id 
            WHERE e.enrollment_no IN ($placeholders)
        ";
        $s_stmt = $conn->prepare($s_sql);
        $s_stmt->execute($student_ids);
        
        $names_map = [];
        while ($row = $s_stmt->fetch(PDO::FETCH_ASSOC)) {
            $names_map[$row['enrollment_no']] = trim(($row['surname'] ?? '') . ' ' . ($row['student_name'] ?? '') . ' ' . ($row['fathers_name'] ?? ''));
        }

        foreach ($accounts as $idx => &$acc) {
            $sid = $acc['student_id'];
            $fullName = $names_map[$sid] ?? '';
            
            // If search is non-empty and doesn't match name or student_id, filter out
            if (!empty($searchTerm)) {
                if (stripos($fullName, $searchTerm) === false && stripos($sid, $searchTerm) === false) {
                    unset($accounts[$idx]);
                    continue;
                }
            }
            $acc['student_name'] = !empty($fullName) ? $fullName : 'Student #' . $sid;
        }
        unset($acc);
        // Reset keys
        $accounts = array_values($accounts);
    }

    echo json_encode(['status' => 'success', 'data' => $accounts]);
} elseif ($action === 'update-limit') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (empty($input['student_id']) || !isset($input['daily_limit'])) {
        echo json_encode(['status' => 'error', 'message' => 'Missing student ID or limit']);
        exit;
    }

    try {
        $wallet_conn = new PDO(
            "mysql:host=" . EXT_DB_HOST . ";dbname=student_wallet;charset=utf8mb4",
            EXT_DB_USER,
            EXT_DB_PASS
        );
        $wallet_conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $wallet_conn->prepare("UPDATE wallet_accounts SET daily_limit = ? WHERE student_id = ?");
        $stmt->execute([floatval($input['daily_limit']), $input['student_id']]);
        
        echo json_encode(['status' => 'success', 'message' => 'Daily spending limit updated successfully']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
} elseif ($action === 'update-status') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (empty($input['student_id']) || empty($input['status'])) {
        echo json_encode(['status' => 'error', 'message' => 'Missing student ID or status']);
        exit;
    }

    $status = strtoupper($input['status']);
    if (!in_array($status, ['ACTIVE', 'BLOCKED', 'SUSPENDED'])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid status value']);
        exit;
    }

    try {
        $wallet_conn = new PDO(
            "mysql:host=" . EXT_DB_HOST . ";dbname=student_wallet;charset=utf8mb4",
            EXT_DB_USER,
            EXT_DB_PASS
        );
        $wallet_conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $wallet_conn->prepare("UPDATE wallet_accounts SET status = ? WHERE student_id = ?");
        $stmt->execute([$status, $input['student_id']]);
        
        echo json_encode(['status' => 'success', 'message' => "Account status updated to $status successfully"]);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
} elseif ($action === 'reset-pin') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (empty($input['student_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'Missing student ID']);
        exit;
    }

    try {
        $wallet_conn = new PDO(
            "mysql:host=" . EXT_DB_HOST . ";dbname=student_wallet;charset=utf8mb4",
            EXT_DB_USER,
            EXT_DB_PASS
        );
        $wallet_conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $wallet_conn->prepare("DELETE FROM wallet_pins WHERE student_id = ?");
        $stmt->execute([$input['student_id']]);
        
        echo json_encode(['status' => 'success', 'message' => 'Transaction PIN reset successfully. The student will be prompted to set a new PIN next time they visit their wallet.']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
}
