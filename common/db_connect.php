<?php
if (!defined('APP_INIT')) {
    header('HTTP/1.1 403 Forbidden');
    die("Direct access to configuration files is prohibited.");
}

require_once __DIR__ . '/../common/constants.php';
/**
 * Common Database Connection File
 * Centralized database connection for the entire application
 *
 * This file loads the environment configuration and creates a PDO connection
 * that can be used from anywhere in the project.
 *
 * @location common/db_connect.php
 * @author Counselling Portal Team
 * @version 1.0
 * @date January 2026
 */

// Prevent multiple inclusions
if (isset($GLOBALS['__db_connected']) && $GLOBALS['__db_connected'] === true) {
    return;
}

// Load environment configuration - single source of truth
require_once ENV_CONFIG_FILE;

// Load error logger if available
$errorLoggerPaths = [
    __DIR__ . '/helpers/error_logger.php',
    __DIR__ . '/../common/helpers/error_logger.php',
];

foreach ($errorLoggerPaths as $loggerPath) {
    if (file_exists($loggerPath)) {
        require_once $loggerPath;
        break;
    }
}

// Create database connection if not already exists
if (!isset($conn) || $conn === null) {
    try {
        $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false,  // Disable persistent connections
        ];

        $conn = new PDO($dsn, $username, $password, $options);

        // Set UTF-8 charset for proper special character support
        $conn->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
        $conn->exec('SET CHARACTER SET utf8mb4');

        // Set MySQL timeout values
        $conn->exec('SET SESSION wait_timeout = 600');
        $conn->exec('SET SESSION interactive_timeout = 600');
        $conn->exec("SET time_zone = '+05:30'");

        // Mark connection as established
        $GLOBALS['__db_connected'] = true;
    } catch (PDOException $e) {
        if (function_exists('logDatabaseError')) {
            logDatabaseError($e, 'Database Connection');
        }
        error_log('Database connection failed: ' . $e->getMessage());
        die('Database Error: ' . $e->getMessage());
    }
}

/**
 * Auto-close database connection at script end
 * This prevents max_connections_per_hour errors
 */
if (!isset($GLOBALS['__db_shutdown_registered'])) {
    register_shutdown_function(function () use (&$conn) {
        if ($conn !== null) {
            $conn = null;
        }
    });
    $GLOBALS['__db_shutdown_registered'] = true;
}
