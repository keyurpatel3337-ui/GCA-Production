<?php

/**
 * Database Backup Download
 * Generates and downloads a SQL backup of the database
 */

require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once '../../common/settings_helper.php';

// Only Super Admin can download backup
if (!hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// Get database credentials from config
$dbHost = $db_host ?? 'localhost';
$dbName = $db_name ?? 'counselling';
$dbUser = $db_user ?? 'root';
$dbPass = $db_pass ?? '';

try {
    // Log the backup action
    logAudit($conn, 'Database Backup', 'Settings', 'Downloaded database backup');

    // Generate filename
    $filename = 'backup_' . $dbName . '_' . date('Y-m-d_His') . '.sql';

    // Set headers for download
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Start output
    $output = "-- Database Backup\n";
    $output .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $output .= "-- Database: " . $dbName . "\n";
    $output .= "-- --------------------------------------------------------\n\n";
    $output .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    echo $output;

    // Get all tables and views
    $tables = $conn->query("SHOW FULL TABLES")->fetchAll(PDO::FETCH_NUM);

    // First dump all tables
    foreach ($tables as $tableInfo) {
        $table = $tableInfo[0];
        $type = $tableInfo[1]; // 'BASE TABLE' or 'VIEW'

        if ($type === 'BASE TABLE') {
            try {
                // Get create table statement
                $createStmt = $conn->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);

                if (!isset($createStmt['Create Table'])) {
                    echo "-- Skipping $table: Not a valid table\n\n";
                    continue;
                }

                echo "-- Table: " . $table . "\n";
                echo "DROP TABLE IF EXISTS `$table`;\n";
                echo $createStmt['Create Table'] . ";\n\n";

                // Get table data
                $rows = $conn->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($rows)) {
                    $columns = array_keys($rows[0]);
                    $columnList = '`' . implode('`, `', $columns) . '`';

                    foreach ($rows as $row) {
                        $values = array_map(function ($value) use ($conn) {
                            if ($value === null) {
                                return 'NULL';
                            }
                            return $conn->quote($value);
                        }, array_values($row));

                        echo "INSERT INTO `$table` ($columnList) VALUES (" . implode(', ', $values) . ");\n";
                    }
                    echo "\n";
                }
            } catch (PDOException $e) {
                echo "-- Error backing up table $table: " . $e->getMessage() . "\n\n";
            }
        }
    }

    // Then dump all views
    foreach ($tables as $tableInfo) {
        $table = $tableInfo[0];
        $type = $tableInfo[1];

        if ($type === 'VIEW') {
            try {
                // Get create view statement
                $createStmt = $conn->query("SHOW CREATE VIEW `$table`")->fetch(PDO::FETCH_ASSOC);
                echo "-- View: " . $table . "\n";
                echo "DROP VIEW IF EXISTS `$table`;\n";

                // Remove DEFINER clause to avoid permission issues
                $viewDefinition = $createStmt['Create View'];
                $viewDefinition = preg_replace('/DEFINER=`[^`]+`@`[^`]+`/', '', $viewDefinition);

                echo $viewDefinition . ";\n\n";
            } catch (PDOException $e) {
                echo "-- Error backing up view $table: " . $e->getMessage() . "\n\n";
            }
        }
    }

    echo "SET FOREIGN_KEY_CHECKS=1;\n";
    echo "-- End of backup\n";
} catch (PDOException $e) {
    // If error, redirect back with message
    $_SESSION['error_message'] = 'Failed to generate backup: ' . $e->getMessage();
    header('Location: settings.php');
    exit;
}
