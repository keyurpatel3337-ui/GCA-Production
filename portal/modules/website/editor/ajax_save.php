<?php

/**
 * AJAX Save Handler for Website Content Editor
 * Saves page content directly to database
 */

require_once __DIR__ . '/../../../session_config.php';
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/common/constants.php'; require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;

// Check authentication and roles
if (!hasRole(ROLE_WEBSITE_ADMIN) && !hasRole(ROLE_SUPER_ADMIN)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $page_id = $_POST['page_id'] ?? null;
    $content = $_POST['content'] ?? null;

    if (!$page_id || !$content) {
        throw new Exception('Missing required parameters');
    }

    $content_array = json_decode($content, true);
    if (!is_array($content_array)) {
        throw new Exception('Invalid content format');
    }

    // Begin transaction
    $conn->beginTransaction();
    $op = new Operation();

    // Update each field
    foreach ($content_array as $item) {
        $key = $item['key'] ?? null;
        $value = $item['value'] ?? '';

        if (!$key)
            continue;

        // Update content in database
        $op->update('tbl_page_content', ['field_value' => $value], ['field_key' => $key]);
    }

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Content saved successfully',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} catch (Exception $e) {
    // Rollback on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error saving content: ' . $e->getMessage()
    ]);
}
