<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';
require_once DB_CONNECT_FILE;
require_once __DIR__ . '/../../../common/helpers/receipt_mapping_functions.php';

header('Content-Type: application/json');

if (!hasRole(ROLE_SUPER_ADMIN)) {
    sendErrorResponse('Unauthorized access', 403);
}

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    sendErrorResponse('Invalid mapping ID', 400);
}

try {
    if (deleteFeeReceiptMapping($conn, $id)) {
        sendSuccessResponse(['id' => $id], 'Mapping deleted successfully');
    } else {
        sendErrorResponse('Failed to delete mapping', 500);
    }
} catch (Exception $e) {
    sendErrorResponse('Error deleting mapping: ' . $e->getMessage(), 500);
}
