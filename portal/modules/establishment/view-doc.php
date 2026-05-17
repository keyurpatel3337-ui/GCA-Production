<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;

// Security: Only authorized roles can view documents
if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_ESTABLISHMENT, ROLE_ACCOUNTANT, ROLE_RECEPTION])) {
    http_response_code(403);
    die('Unauthorized access');
}

if (!isset($_GET['file']) || empty($_GET['file'])) {
    http_response_code(400);
    die('Missing file parameter');
}

$file_path = $_GET['file'];

// Security: Prevent directory traversal
if (strpos($file_path, '..') !== false) {
    http_response_code(400);
    die('Invalid file path');
}

// Define the root storage path on D: drive
$storage_root = 'D:/StudentDocuments/';
$full_path = $storage_root . $file_path;

if (!file_exists($full_path)) {
    http_response_code(404);
    die('File not found');
}

// Get the mime type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $full_path);
finfo_close($finfo);

// Serve the file
header('Content-Type: ' . $mime_type);
header('Content-Length: ' . filesize($full_path));
header('Content-Disposition: inline; filename="' . basename($full_path) . '"');
readfile($full_path);
exit;
