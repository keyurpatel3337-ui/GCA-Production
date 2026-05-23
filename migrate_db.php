<?php
/**
 * Database Migration Script
 * Decompresses DB_counselling_2026-05-23_19-30.sql.gz, fixes collation, and imports it into counselling DB
 */

$gzFile = 'c:/xampp/htdocs/GCA-Production/DB_counselling_2026-05-23_19-30.sql.gz';
$sqlFile = 'c:/xampp/htdocs/GCA-Production/DB_counselling_2026-05-23_19-30.sql';

echo "Starting migration...\n";

if (!file_exists($gzFile)) {
    die("Error: GZ file not found at $gzFile\n");
}

// 1. Decompress GZ file and replace collation
echo "Decompressing $gzFile and fixing collation ('utf8mb4_0900_ai_ci' -> 'utf8mb4_unicode_ci')...\n";
$gz = gzopen($gzFile, 'rb');
$out = fopen($sqlFile, 'wb');

if (!$gz || !$out) {
    die("Error: Could not open files for decompression.\n");
}

$lineCount = 0;
while (!gzeof($gz)) {
    $line = gzgets($gz);
    if ($line === false) {
        break;
    }
    
    // Replace 'utf8mb4_0900_ai_ci' with 'utf8mb4_unicode_ci'
    if (strpos($line, 'utf8mb4_0900_ai_ci') !== false) {
        $line = str_replace('utf8mb4_0900_ai_ci', 'utf8mb4_unicode_ci', $line);
    }
    
    fwrite($out, $line);
    $lineCount++;
}

gzclose($gz);
fclose($out);
echo "Decompression and replacement complete. Lines processed: $lineCount. File size: " . round(filesize($sqlFile) / 1024 / 1024, 2) . " MB\n";

// 2. Connect to MySQL and ensure DB exists
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'counselling';

try {
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Creating database if not exists: $dbname...\n";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
} catch (PDOException $e) {
    die("Database Connection Error: " . $e->getMessage() . "\n");
}

// 3. Import SQL file using mysql CLI
echo "Importing $sqlFile into database $dbname...\n";
$mysqlPath = 'C:/xampp/mysql/bin/mysql.exe';

if (!file_exists($mysqlPath)) {
    $mysqlPath = 'mysql';
}

$command = "\"$mysqlPath\" --user=$user --host=$host $dbname < \"$sqlFile\" 2>&1";
echo "Executing: $command\n";

exec($command, $output, $returnVar);

if ($returnVar === 0) {
    echo "SUCCESS: Database migrated successfully!\n";
    // Delete temp sql file
    unlink($sqlFile);
    echo "Cleaned up temporary SQL file.\n";
} else {
    echo "FAILURE: Database import failed!\n";
    echo "Output:\n" . implode("\n", $output) . "\n";
}
?>
