<?php
require_once dirname(__DIR__, 3) . '/session_config.php';
require_once dirname(__DIR__, 4) . '/common/constants.php';
require_once DB_CONNECT_FILE;

// Check if user is Maintenance Admin or Super Admin
if (!hasRole(ROLE_MAINTENANCE) && !hasRole(ROLE_SUPER_ADMIN)) {
    die("Unauthorized access.");
}

$file = $_GET['file'] ?? '';
$type = $_GET['type'] ?? '';

if (!$file) {
    die("File name is required.");
}

$base_dir = 'D:/portal_backups';
switch ($type) {
    case 'pdf':
        $file_path = $base_dir . '/receipt_pdfs/' . basename($file);
        break;
    case 'files':
        $file_path = $base_dir . '/files/' . basename($file);
        break;
    case 'db':
        $file_path = $base_dir . '/database/' . basename($file);
        break;
    default:
        die("Invalid backup type.");
}

if (!file_exists($file_path)) {
    die("File not found at: " . $file_path);
}

// Set headers for download
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
header('Content-Length: ' . filesize($file_path));
header('Pragma: no-cache');
header('Expires: 0');

// Clear buffer and stream file
ob_clean();
flush();
readfile($file_path);
exit;
