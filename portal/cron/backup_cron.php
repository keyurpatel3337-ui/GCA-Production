<?php
define('APP_INIT', true);
/**
 * Portal Backup Cron Job (Database & Files)
 * 
 * Usage:
 * php backup_cron.php --type=daily
 * php backup_cron.php --type=monthly
 * php backup_cron.php --type=yearly
 */

// Basic Security
if (php_sapi_name() !== 'cli') {
    die('Access denied');
}

// Configuration
require_once dirname(dirname(__DIR__)) . '/common/constants.php'; // C:\xampp\htdocs\common\constants.php
require_once ENV_CONFIG_FILE;

// Set timezone and memory limit
date_default_timezone_set('Asia/Kolkata');
ini_set('memory_limit', '512M');
set_time_limit(0);

// Arguments
$options = getopt("", ["type:", "target:"]);
$type = $options['type'] ?? 'daily'; // daily, monthly, yearly
$target = $options['target'] ?? 'all'; // all, database, files

if (!in_array($type, ['daily', 'monthly', 'yearly'])) {
    die("Invalid type. Use --type=daily|monthly|yearly\n");
}

// Backup Paths
$backup_root = 'D:/portal_backups';
$backup_root = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $backup_root);

// Use a single backup root for all backups (database, files, logs)
$db_backup_dir = $backup_root . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . $type;

$files_backup_dir = $backup_root . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . $type;
$log_dir = $backup_root . DIRECTORY_SEPARATOR . 'logs';

// Ensure directories exist
if (!file_exists($db_backup_dir))
    mkdir($db_backup_dir, 0777, true);
if (!file_exists($files_backup_dir))
    mkdir($files_backup_dir, 0777, true);
if (!file_exists($log_dir))
    mkdir($log_dir, 0777, true);

// Logger
$log_file = "$log_dir/backup_" . date('Y-m-d') . ".log";
function logMsg($msg)
{
    global $log_file;
    $time = date('Y-m-d H:i:s');
    $line = "[$time] $msg" . PHP_EOL;
    echo $line;
    file_put_contents($log_file, $line, FILE_APPEND);
}

logMsg("=== Starting $type Backup (Target: $target) ===");

// ---------------------------------------------------------
// 1. DATABASE BACKUP
// ---------------------------------------------------------
if ($target === 'all' || $target === 'database') {
    // Determine mysqldump path
    $mysqldump = 'C:\\xampp\\mysql\\bin\\mysqldump.exe'; // Default XAMPP path
    if (!file_exists($mysqldump)) {
        // Try to find it
        $mysqldump = trim(shell_exec('where mysqldump'));
    }

    if ($mysqldump && file_exists($mysqldump)) {
        // DB Config
        $db_host = $host ?? 'localhost';
        $db_user = $username ?? 'root';
        $db_pass = $password ?? '';

        // Get database list
        try {
            $mysqli = new mysqli($db_host, $db_user, $db_pass);
            if ($mysqli->connect_error) {
                throw new Exception("Connection failed: " . $mysqli->connect_error);
            }

            $result = $mysqli->query("SHOW DATABASES");
            $databases = [];
            while ($row = $result->fetch_array()) {
                $db = $row[0];
                // Skip system databases
                if (!in_array($db, ['information_schema', 'performance_schema', 'mysql', 'sys', 'phpmyadmin'])) {
                    $databases[] = $db;
                }
            }
            $mysqli->close();
            logMsg("Found " . count($databases) . " databases to backup.");
        } catch (Exception $e) {
            logMsg("ERROR: Could not fetch database list: " . $e->getMessage());
            // Fallback to the default one if list fails
            $databases = [$dbname ?? 'counselling'];
        }

        foreach ($databases as $db_name) {
            $date_suffix = ($type === 'daily') ? date('Y-m-d_H-i') : (($type === 'monthly') ? date('Y-m') : date('Y'));
            $db_filename = "DB_{$db_name}_{$date_suffix}.sql";
            $db_target = "$db_backup_dir/$db_filename";

            $cmd = "\"$mysqldump\" --host=$db_host --user=$db_user --password=\"$db_pass\" --single-transaction --routines --triggers \"$db_name\" > \"$db_target\" 2>&1";

            logMsg("Executing: " . str_replace($db_pass, '********', $cmd));
            logMsg("Backing up Database: $db_name");
            exec($cmd, $output, $return);

            if ($return === 0 && file_exists($db_target) && filesize($db_target) > 0) {
                logMsg("Database verified ($db_name): " . round(filesize($db_target) / 1024 / 1024, 2) . " MB");

                // Compress
                if (function_exists('gzopen')) {
                    $gz_file = $db_target . '.gz';
                    $fp = fopen($db_target, 'rb');
                    $zp = gzopen($gz_file, 'wb9');
                    while (!feof($fp))
                        gzwrite($zp, fread($fp, 524288)); // 512KB
                    fclose($fp);
                    gzclose($zp);
                    unlink($db_target); // Remove raw sql
                    logMsg("Compressed to: $gz_file");
                }
            } else {
                logMsg("ERROR: Database backup failed for $db_name. Code: $return");
            }
        }
    } else {
        logMsg("ERROR: mysqldump not found!");
    }
}

// ---------------------------------------------------------
// 2. FILES BACKUP
// ---------------------------------------------------------
if ($target === 'all' || $target === 'files') {
    // Define what to backup
    $source_dir = dirname(__DIR__) . '/uploads'; // portal/uploads
    if (file_exists($source_dir)) {
        $date_suffix = ($type === 'daily') ? date('Y-m-d_H-i') : (($type === 'monthly') ? date('Y-m') : date('Y'));
        $zip_filename = "Files_{$date_suffix}.zip";
        $zip_target = "$files_backup_dir/$zip_filename";

        logMsg("Backing up Files: $source_dir");

        $zip = new ZipArchive();
        if ($zip->open($zip_target, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($source_dir),
                RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($files as $name => $file) {
                if (!$file->isDir()) {
                    $filePath = $file->getRealPath();
                    $relativePath = substr($filePath, strlen($source_dir) + 1);
                    $zip->addFile($filePath, $relativePath);
                }
            }
            $zip->close();
            logMsg("Files verified: " . round(filesize($zip_target) / 1024 / 1024, 2) . " MB - Saved to $zip_target");
        } else {
            logMsg("ERROR: Could not create ZIP file");
        }
    } else {
        logMsg("WARNING: Uploads directory not found ($source_dir)");
    }
}

// Retention policy removed as per latest requirements.

logMsg("=== Backup Completed ===");
logMsg(""); // New line
