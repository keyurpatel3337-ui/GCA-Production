<?php

/**
 * Error Logger Utility
 * Centralized error logging for the counselling system
 * 
 * Supports categorized logging to separate log files:
 * - login-error.log    : Authentication/login errors
 * - payment-error.log  : Payment gateway errors
 * - database-error.log : Database errors
 * - file-error.log     : File operation errors
 * - validation-error.log : Input validation errors
 * - error.log          : General application errors
 * - upload-error.log   : File upload errors
 * - payment-gateway.log: Payment gateway transaction logs
 * - payment-offline.log: Offline payment logs
 * 
 * Separate log directories for each category:
 * - logs/student/      : Authentication logs
 * - logs/payment/      : Payment and gateway logs
 * - logs/database/     : Database errors
 * - logs/file/         : File operation errors
 * - logs/validation/   : Validation errors
 * - logs/error/        : General errors
 * - logs/upload/       : Upload errors
 * 
 * Usage:
 *   require_once __DIR__ . '/../../common/helpers/error_logger.php';
 *   
 *   // Log different types of errors
 *   logError("Login failed", __FILE__, __LINE__, null, LOG_CATEGORY_AUTH);
 *   logDatabaseError($e, "User registration");
 *   logGatewayActivity("Payment request sent", "INFO", $data);
 */

if (!defined('APP_INIT')) {
    define('APP_INIT', true);
}

// Error logging configuration
// Primary log path is D:\GCA\Logs (defined in constants.php)
// If constants.php was already included, LOGS_PATH is already set; otherwise define it here.
if (!defined('LOGS_PATH')) {
    define('LOGS_PATH', 'D:\\GCA\\Logs\\');
}

// Log categories for categorized logging
if (!defined('LOG_CATEGORY_AUTH'))
    define('LOG_CATEGORY_AUTH', 'auth');           // login-error.log
if (!defined('LOG_CATEGORY_PAYMENT'))
    define('LOG_CATEGORY_PAYMENT', 'payment');     // payment-error.log
if (!defined('LOG_CATEGORY_DATABASE'))
    define('LOG_CATEGORY_DATABASE', 'database');   // database-error.log
if (!defined('LOG_CATEGORY_FILE'))
    define('LOG_CATEGORY_FILE', 'file');           // file-error.log
if (!defined('LOG_CATEGORY_VALIDATION'))
    define('LOG_CATEGORY_VALIDATION', 'validation'); // validation-error.log
if (!defined('LOG_CATEGORY_GENERAL'))
    define('LOG_CATEGORY_GENERAL', 'general');     // error.log (default)
if (!defined('LOG_CATEGORY_UPLOAD'))
    define('LOG_CATEGORY_UPLOAD', 'upload');       // upload-error.log
if (!defined('LOG_CATEGORY_WARNING'))
    define('LOG_CATEGORY_WARNING', 'warning');     // warning.log
if (!defined('LOG_CATEGORY_AUTH_SUCCESS'))
    define('LOG_CATEGORY_AUTH_SUCCESS', 'auth_success'); // login-success.log
if (!defined('LOG_CATEGORY_GATEWAY'))
    define('LOG_CATEGORY_GATEWAY', 'gateway');     // payment-gateway.log
if (!defined('LOG_CATEGORY_OFFLINE'))
    define('LOG_CATEGORY_OFFLINE', 'offline');     // payment-offline.log

// Map categories to log file prefixes (date will be appended)
if (!isset($GLOBALS['LOG_FILE_MAP'])) {
    $GLOBALS['LOG_FILE_MAP'] = [
        LOG_CATEGORY_AUTH => 'login-error',
        LOG_CATEGORY_PAYMENT => 'payment-error',
        LOG_CATEGORY_DATABASE => 'database-error',
        LOG_CATEGORY_FILE => 'file-error',
        LOG_CATEGORY_VALIDATION => 'validation-error',
        LOG_CATEGORY_GENERAL => 'error',
        LOG_CATEGORY_UPLOAD => 'upload',
        LOG_CATEGORY_WARNING => 'warning',
        LOG_CATEGORY_AUTH_SUCCESS => 'login-success',
        LOG_CATEGORY_GATEWAY => 'payment-gateway',
        LOG_CATEGORY_OFFLINE => 'payment-offline'
    ];
}

/**
 * Get date-wise log filename
 * @param string $prefix Log file prefix
 * @return string Log filename with date
 */
if (!function_exists('getDateWiseLogFileName')) {
    function getDateWiseLogFileName($prefix)
    {
        $date = date('Y-m-d');
        return $prefix . '-' . $date . '.log';
    }
}

/**
 * Test if a directory is actually writable by attempting a write.
 * is_writable() is unreliable on Windows NTFS — use this instead.
 */
if (!function_exists('isLogDirWritable')) {
    function isLogDirWritable($dir)
    {
        if (!file_exists($dir)) {
            return false;
        }
        $testFile = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . '.write_test_' . getmypid();
        $result = @file_put_contents($testFile, '1');
        if ($result !== false) {
            @unlink($testFile);
            return true;
        }
        return false;
    }
}

/**
 * Log error messages to category-specific log file
 * 
 * @param string $message Error message to log
 * @param string $file File where error occurred (optional)
 * @param int $line Line number where error occurred (optional)
 * @param Exception|null $exception Exception object (optional)
 * @param string $category Log category (optional, default: LOG_CATEGORY_GENERAL)
 */
if (!function_exists('logError')) {
    function logError($message, $file = '', $line = 0, $exception = null, $category = LOG_CATEGORY_GENERAL)
    {
        // Get log filename from category (with date)
        $logPrefix = $GLOBALS['LOG_FILE_MAP'][$category] ?? 'error';
        $logFileName = getDateWiseLogFileName($logPrefix);

        // Category mapping to subdirectories
        $categorySubDir = $category;
        if ($category === LOG_CATEGORY_AUTH || $category === LOG_CATEGORY_AUTH_SUCCESS) $categorySubDir = 'student';
        elseif ($category === LOG_CATEGORY_PAYMENT || $category === LOG_CATEGORY_GATEWAY || $category === LOG_CATEGORY_OFFLINE) $categorySubDir = 'payment';
        elseif ($category === LOG_CATEGORY_DATABASE) $categorySubDir = 'database';
        elseif ($category === LOG_CATEGORY_GENERAL) $categorySubDir = 'error';
        elseif ($category === LOG_CATEGORY_WARNING) $categorySubDir = 'warning';
        
        $logDir = rtrim(LOGS_PATH, '/\\') . DIRECTORY_SEPARATOR . $categorySubDir;
        if (!file_exists($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $logFile = $logDir . DIRECTORY_SEPARATOR . $logFileName;

        // Fallback to system temp if LOGS_PATH is somehow not writable
        if (!isLogDirWritable($logDir)) {
            $logDir = sys_get_temp_dir();
            $logFile = $logDir . DIRECTORY_SEPARATOR . 'gca_' . $logFileName;
        }

        // Prepare log entry
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] ";

        // Add file and line info if provided
        if (!empty($file)) {
            $logEntry .= "[File: $file] ";
        }
        if ($line > 0) {
            $logEntry .= "[Line: $line] ";
        }

        // Add main message
        $logEntry .= "$message";

        // Add exception details if provided
        if ($exception !== null) {
            $logEntry .= " | Exception: " . $exception->getMessage();
            $logEntry .= " | Trace: " . $exception->getTraceAsString();
        }

        // Add user info if in session
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user_id'])) {
            $logEntry .= " | User ID: " . $_SESSION['user_id'];
        }

        // Add request URI
        if (isset($_SERVER['REQUEST_URI'])) {
            $logEntry .= " | URI: " . $_SERVER['REQUEST_URI'];
        }

        $logEntry .= PHP_EOL;

        // Write to log file - use file_put_contents with FILE_APPEND for better compatibility
        $written = @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

        // Fallback to error_log if file write fails
        if ($written === false) {
            @error_log($logEntry, 3, $logFile);
        }

        // Also log to PHP error log for server logs ONLY if it's a general error or database error
        if (in_array($category, [LOG_CATEGORY_GENERAL, LOG_CATEGORY_DATABASE, LOG_CATEGORY_AUTH, LOG_CATEGORY_FILE])) {
            @error_log($message);
        }
    }
}

/**
 * Log database errors
 * 
 * @param PDOException $e PDO Exception
 * @param string $operation Database operation that failed
 */
if (!function_exists('logDatabaseError')) {
    function logDatabaseError($e, $operation = '')
    {
        $message = "Database Error";
        if (!empty($operation)) {
            $message .= " during $operation";
        }
        $message .= ": " . $e->getMessage();

        logError($message, $e->getFile(), $e->getLine(), $e, LOG_CATEGORY_DATABASE);
    }
}

/**
 * Log file operation errors
 * 
 * @param string $operation File operation that failed
 * @param string $filename File name
 * @param string $error Error message
 */
if (!function_exists('logFileError')) {
    function logFileError($operation, $filename, $error = '')
    {
        $message = "File $operation Error for '$filename'";
        if (!empty($error)) {
            $message .= ": $error";
        }

        logError($message, '', 0, null, LOG_CATEGORY_FILE);
    }
}

/**
 * Log authentication errors
 * 
 * @param string $username Username/email attempted
 * @param string $reason Reason for failure
 */
if (!function_exists('logAuthError')) {
    function logAuthError($username, $reason)
    {
        $message = "Authentication Failed - Username: $username - Reason: $reason";
        logError($message, '', 0, null, LOG_CATEGORY_AUTH);
    }
}

/**
 * Log successful authentication
 * 
 * @param string $username Username/email
 */
if (!function_exists('logAuthSuccess')) {
    function logAuthSuccess($username)
    {
        $message = "Authentication Successful - Username: $username";
        logError($message, '', 0, null, LOG_CATEGORY_AUTH_SUCCESS);
    }
}

/**
 * Log validation errors
 * 
 * @param string $field Field that failed validation
 * @param string $value Value that failed (optional, be careful with sensitive data)
 * @param string $reason Validation failure reason
 */
if (!function_exists('logValidationError')) {
    function logValidationError($field, $value = '', $reason = '')
    {
        $message = "Validation Error - Field: $field";
        if (!empty($reason)) {
            $message .= " - Reason: $reason";
        }
        // Don't log passwords or sensitive data
        if (!in_array(strtolower($field), ['password', 'pin', 'token', 'secret'])) {
            if (!empty($value)) {
                $message .= " - Value: $value";
            }
        }

        logError($message, '', 0, null, LOG_CATEGORY_VALIDATION);
    }
}

/**
 * Log payment gateway errors
 * 
 * @param string $operation Payment operation that failed
 * @param string $txnId Transaction ID (optional)
 * @param string $error Error message (optional)
 * @param array $data Additional data like amount, status (optional)
 */
if (!function_exists('logPaymentError')) {
    function logPaymentError($operation, $txnId = '', $error = '', $data = [])
    {
        $message = "Payment Error - Operation: $operation";
        if (!empty($txnId)) {
            $message .= " | TxnID: $txnId";
        }
        if (!empty($error)) {
            $message .= " | Error: $error";
        }
        if (!empty($data)) {
            // Mask sensitive data
            $safeData = $data;
            if (isset($safeData['key'])) {
                $safeData['key'] = substr($safeData['key'], 0, 4) . '****';
            }
            $message .= " | Data: " . json_encode($safeData);
        }

        logError($message, '', 0, null, LOG_CATEGORY_PAYMENT);
    }
}

/**
 * Log payment debug information (for successful payments too)
 * 
 * @param string $operation Payment operation
 * @param array $data Debug data
 */
if (!function_exists('logPaymentDebug')) {
    function logPaymentDebug($operation, $data = [])
    {
        $message = "Payment Debug - Operation: $operation";
        if (!empty($data)) {
            // Mask sensitive data
            $safeData = $data;
            if (isset($safeData['key'])) {
                $safeData['key'] = substr($safeData['key'], 0, 4) . '****';
            }
            $message .= " | Data: " . json_encode($safeData);
        }

        logError($message, '', 0, null, LOG_CATEGORY_PAYMENT);
    }
}

/**
 * Log comprehensive payment gateway activity (Unified Log)
 * 
 * @param string $message Activity message
 * @param string $status Status level (INFO, SUCCESS, FAILED, DROPPED, ERROR)
 * @param array $data Additional data to log (will be masked)
 */
if (!function_exists('logGatewayActivity')) {
    function logGatewayActivity($message, $status = 'INFO', $data = [])
{
    $logPrefix = $GLOBALS['LOG_FILE_MAP'][LOG_CATEGORY_GATEWAY] ?? 'payment-gateway';
    $logFileName = getDateWiseLogFileName($logPrefix);

    $logDir = rtrim(LOGS_PATH, '/\\') . DIRECTORY_SEPARATOR . 'payment';
    $logFile = $logDir . DIRECTORY_SEPARATOR . $logFileName;

    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    if (!isLogDirWritable($logDir)) {
        $logFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gca_' . $logFileName;
    }

    // Mask sensitive data
    $safeData = $data;
    $sensitiveKeys = ['key', 'salt', 'merchant_salt', 'api_key', 'api_secret', 'hash'];
    foreach ($sensitiveKeys as $key) {
        if (isset($safeData[$key])) {
            $safeData[$key] = substr($safeData[$key], 0, 4) . '****';
        }
    }

    $timestamp = date('Y-m-d H:i:s');
    $statusLabel = strtoupper($status);
    $logEntry = "[$timestamp] [$statusLabel] $message";

    if (!empty($safeData)) {
        $logEntry .= " | Data: " . json_encode($safeData);
    }

    // Add user info if in session
    if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user_id'])) {
        $logEntry .= " | User ID: " . $_SESSION['user_id'];
    }

    // Add request URI
    if (isset($_SERVER['REQUEST_URI'])) {
        $logEntry .= " | URI: " . $_SERVER['REQUEST_URI'];
    }

    $logEntry .= PHP_EOL;

    @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

    // --- NEW: Also write to database table ---
    try {
        global $conn;
        if (isset($conn)) {
            $user_id = (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user_id'])) ? $_SESSION['user_id'] : null;
            $txn_id = null;
            $amount = null;

            if (preg_match('/TxnID:\\s*([A-Z0-9_]+)/i', $message, $matches)) {
                $txn_id = $matches[1];
            } elseif (isset($data['txnid'])) {
                $txn_id = $data['txnid'];
            }

            if (preg_match('/Amount:\\s*([0-9.]+)/i', $message, $matches)) {
                $amount = floatval($matches[1]);
            } elseif (isset($data['amount'])) {
                $amount = floatval($data['amount']);
            }

            $stmt = $conn->prepare("INSERT INTO tbl_payment_gateway_logs 
                (log_time, log_type, txn_id, amount, raw_data, uri, ip_address, user_id) 
                VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $statusLabel,
                $txn_id,
                $amount,
                json_encode($safeData),
                $_SERVER['REQUEST_URI'] ?? null,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $user_id
            ]);
        }
    } catch (Exception $e) {
        error_log("DB Logging failed: " . $e->getMessage());
    }
}
}

/**
 * Log offline/manual payment activity
 * 
 * @param string $message Activity message
 * @param string $status Status level (INFO, SUCCESS, FAILED, ERROR)
 * @param array $data Additional data to log
 */
if (!function_exists('logOfflineActivity')) {
    function logOfflineActivity($message, $status = 'INFO', $data = [])
    {
        $logPrefix = $GLOBALS['LOG_FILE_MAP'][LOG_CATEGORY_OFFLINE] ?? 'payment-offline';
        $logFileName = getDateWiseLogFileName($logPrefix);

        $logDir = rtrim(LOGS_PATH, '/\\') . DIRECTORY_SEPARATOR . 'payment';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $logFile = $logDir . DIRECTORY_SEPARATOR . $logFileName;

        if (!isLogDirWritable($logDir)) {
            $logFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gca_' . $logFileName;
        }

        $timestamp = date('Y-m-d H:i:s');
        $statusLabel = strtoupper($status);
        $logEntry = "[$timestamp] [$statusLabel] $message";

        if (!empty($data)) {
            $logEntry .= " | Data: " . json_encode($data);
        }

        // Add user info if in session
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user_id'])) {
            $logEntry .= " | User ID: " . $_SESSION['user_id'];
        }

        // Add request URI
        if (isset($_SERVER['REQUEST_URI'])) {
            $logEntry .= " | URI: " . $_SERVER['REQUEST_URI'];
        }

        $logEntry .= PHP_EOL;

        @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}

/**
 * Log general application errors
 * 
 * @param string $context Context where error occurred
 * @param string $message Error message
 * @param array $data Additional data (optional)
 */
if (!function_exists('logAppError')) {
    function logAppError($context, $message, $data = [])
    {
        $logMessage = "[$context] $message";
        if (!empty($data)) {
            $logMessage .= " | Data: " . json_encode($data);
        }

        logError($logMessage);
    }
}

/**
 * Log informational messages (not errors)
 * 
 * @param string $message Info message to log
 * @param string $category Log category (optional, default: LOG_CATEGORY_GENERAL)
 */
if (!function_exists('logInfo')) {
    function logInfo($message, $category = LOG_CATEGORY_GENERAL)
    {
        // Get log filename from category (with date)
        $logPrefix = $GLOBALS['LOG_FILE_MAP'][$category] ?? 'error';

        // Use info log instead of error log
        $logPrefix = str_replace('-error', '-info', $logPrefix);
        $logPrefix = str_replace('error', 'info', $logPrefix);
        $logFileName = getDateWiseLogFileName($logPrefix);

        $logDir = rtrim(LOGS_PATH, '/\\');
        
        $categorySubDir = $category;
        if ($category === LOG_CATEGORY_GENERAL) $categorySubDir = 'info';
        elseif ($category === LOG_CATEGORY_DATABASE) $categorySubDir = 'database';
        elseif ($category === LOG_CATEGORY_AUTH) $categorySubDir = 'student';
        elseif ($category === LOG_CATEGORY_PAYMENT) $categorySubDir = 'payment';

        $logDir = $logDir . DIRECTORY_SEPARATOR . $categorySubDir;
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $logFile = $logDir . DIRECTORY_SEPARATOR . $logFileName;

        if (!isLogDirWritable($logDir)) {
            $logFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gca_' . $logFileName;
        }

        // Prepare log entry
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] [INFO] $message";

        // Add user info if in session
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user_id'])) {
            $logEntry .= " | User ID: " . $_SESSION['user_id'];
        }

        // Add request URI
        if (isset($_SERVER['REQUEST_URI'])) {
            $logEntry .= " | URI: " . $_SERVER['REQUEST_URI'];
        }

        $logEntry .= PHP_EOL;

        // Write to log file
        @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}

/**
 * Log upload progress and details to a specific upload log file
 * 
 * @param string $message Message to log
 * @param string $session_id Unique ID for the upload session
 * @param string $category Log category (optional, default: LOG_CATEGORY_UPLOAD)
 */
if (!function_exists('logUploadProgress')) {
    function logUploadProgress($message, $session_id, $category = LOG_CATEGORY_UPLOAD)
    {
        // Get log filename based on session ID
        $logFileName = "upload-session-{$session_id}.log";

        $logDir = rtrim(LOGS_PATH, '/\\');
        $logFile = $logDir . DIRECTORY_SEPARATOR . $logFileName;

        if (!isLogDirWritable($logDir)) {
            $logFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gca_' . $logFileName;
        }

        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] [Session: $session_id] $message" . PHP_EOL;

        @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}

// Set custom error handler for PHP errors
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    // Don't log suppressed errors (with @)
    if (!(error_reporting() & $errno)) {
        return false;
    }

    $errorTypes = [
        E_ERROR => 'ERROR',
        E_WARNING => 'WARNING',
        E_PARSE => 'PARSE ERROR',
        E_NOTICE => 'NOTICE',
        E_CORE_ERROR => 'CORE ERROR',
        E_CORE_WARNING => 'CORE WARNING',
        E_COMPILE_ERROR => 'COMPILE ERROR',
        E_COMPILE_WARNING => 'COMPILE WARNING',
        E_USER_ERROR => 'USER ERROR',
        E_USER_WARNING => 'USER WARNING',
        E_USER_NOTICE => 'USER NOTICE',
        E_RECOVERABLE_ERROR => 'RECOVERABLE ERROR',
        E_DEPRECATED => 'DEPRECATED',
        E_USER_DEPRECATED => 'USER DEPRECATED'
    ];

    $errorType = $errorTypes[$errno] ?? 'UNKNOWN ERROR';
    
    // Separate warnings/notices from actual errors
    $category = LOG_CATEGORY_GENERAL;
    if (in_array($errno, [E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING, E_USER_WARNING, E_NOTICE, E_USER_NOTICE, E_DEPRECATED, E_USER_DEPRECATED])) {
        $category = LOG_CATEGORY_WARNING;
    }
    
    logError("PHP $errorType: $errstr", $errfile, $errline, null, $category);

    // Don't execute PHP internal error handler
    return true;
});

// Set custom exception handler for uncaught exceptions
set_exception_handler(function ($exception) {
    logError(
        "Uncaught Exception: " . $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine(),
        $exception
    );

    // Display user-friendly message
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo "An error occurred. Please try again later.";
    exit(1);
});
