<?php
$file = 'c:\xampp\htdocs\GCA-Production\common\helpers\error_logger.php';
$content = file_get_contents($file);

$oldFunction = 'function logGatewayActivity($message, $status = \'INFO\', $data = [])
{
    $logPrefix = $GLOBALS[\'LOG_FILE_MAP\'][LOG_CATEGORY_GATEWAY] ?? \'payment-gateway\';
    $logFileName = getDateWiseLogFileName($logPrefix);

    $logDir = rtrim(LOGS_PATH, \'/\\\\\');
    $logFile = $logDir . DIRECTORY_SEPARATOR . $logFileName;

    if (!isLogDirWritable($logDir)) {
        $logFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . \'gca_\' . $logFileName;
    }

    // Mask sensitive data
    $safeData = $data;
    $sensitiveKeys = [\'key\', \'salt\', \'merchant_salt\', \'api_key\', \'api_secret\', \'hash\'];
    foreach ($sensitiveKeys as $key) {
        if (isset($safeData[$key])) {
            $safeData[$key] = substr($safeData[$key], 0, 4) . \'****\';
        }
    }

    $timestamp = date(\'Y-m-d H:i:s\');
    $statusLabel = strtoupper($status);
    $logEntry = "[$timestamp] [$statusLabel] $message";

    if (!empty($safeData)) {
        $logEntry .= " | Data: " . json_encode($safeData);
    }

    // Add user info if in session
    if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION[\'user_id\'])) {
        $logEntry .= " | User ID: " . $_SESSION[\'user_id\'];
    }

    // Add request URI
    if (isset($_SERVER[\'REQUEST_URI\'])) {
        $logEntry .= " | URI: " . $_SERVER[\'REQUEST_URI\'];
    }

    $logEntry .= PHP_EOL;

    @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}';

$newFunction = 'function logGatewayActivity($message, $status = \'INFO\', $data = [])
{
    $logPrefix = $GLOBALS[\'LOG_FILE_MAP\'][LOG_CATEGORY_GATEWAY] ?? \'payment-gateway\';
    $logFileName = getDateWiseLogFileName($logPrefix);

    $logDir = rtrim(LOGS_PATH, \'/\\\\\');
    $logFile = $logDir . DIRECTORY_SEPARATOR . $logFileName;

    if (!isLogDirWritable($logDir)) {
        $logFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . \'gca_\' . $logFileName;
    }

    // Mask sensitive data
    $safeData = $data;
    $sensitiveKeys = [\'key\', \'salt\', \'merchant_salt\', \'api_key\', \'api_secret\', \'hash\'];
    foreach ($sensitiveKeys as $key) {
        if (isset($safeData[$key])) {
            $safeData[$key] = substr($safeData[$key], 0, 4) . \'****\';
        }
    }

    $timestamp = date(\'Y-m-d H:i:s\');
    $statusLabel = strtoupper($status);
    $logEntry = "[$timestamp] [$statusLabel] $message";

    if (!empty($safeData)) {
        $logEntry .= " | Data: " . json_encode($safeData);
    }

    // Add user info if in session
    if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION[\'user_id\'])) {
        $logEntry .= " | User ID: " . $_SESSION[\'user_id\'];
    }

    // Add request URI
    if (isset($_SERVER[\'REQUEST_URI\'])) {
        $logEntry .= " | URI: " . $_SERVER[\'REQUEST_URI\'];
    }

    $logEntry .= PHP_EOL;

    @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

    // --- NEW: Also write to database table ---
    try {
        global $conn;
        if (isset($conn)) {
            $user_id = (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION[\'user_id\'])) ? $_SESSION[\'user_id\'] : null;
            $txn_id = null;
            $amount = null;

            if (preg_match(\'/TxnID:\\\s*([A-Z0-9_]+)/i\', $message, $matches)) {
                $txn_id = $matches[1];
            } elseif (isset($data[\'txnid\'])) {
                $txn_id = $data[\'txnid\'];
            }

            if (preg_match(\'/Amount:\\\s*([0-9.]+)/i\', $message, $matches)) {
                $amount = floatval($matches[1]);
            } elseif (isset($data[\'amount\'])) {
                $amount = floatval($data[\'amount\']);
            }

            $stmt = $conn->prepare("INSERT INTO tbl_payment_gateway_logs 
                (log_time, log_type, txn_id, amount, raw_data, uri, ip_address, user_id) 
                VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $statusLabel,
                $txn_id,
                $amount,
                json_encode($safeData),
                $_SERVER[\'REQUEST_URI\'] ?? null,
                $_SERVER[\'REMOTE_ADDR\'] ?? null,
                $user_id
            ]);
        }
    } catch (Exception $e) {
        error_log("DB Logging failed: " . $e->getMessage());
    }
}';

// Use a more robust way to replace since there might be different line endings or whitespace
$pos = strpos($content, 'function logGatewayActivity');
if ($pos !== false) {
    // Find the end of the function (counting braces)
    $braceCount = 0;
    $started = false;
    $endPos = $pos;
    for ($i = $pos; $i < strlen($content); $i++) {
        if ($content[$i] === '{') {
            $braceCount++;
            $started = true;
        } elseif ($content[$i] === '}') {
            $braceCount--;
        }
        
        if ($started && $braceCount === 0) {
            $endPos = $i + 1;
            break;
        }
    }
    
    $newContent = substr($content, 0, $pos) . $newFunction . substr($content, $endPos);
    file_put_contents($file, $newContent);
    echo "Successfully updated logGatewayActivity function.\n";
} else {
    echo "Function not found.\n";
}
