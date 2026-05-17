<?php

/**
 * Update Refund Request Status API
 * Update status (e.g., mark as under review)
 */

session_start();
require_once dirname(dirname(__DIR__)) . '/common/constants.php'; require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once '../globalvariable.php';

header('Content-Type: application/json');

// Check admin access
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_COUNSELLOR) && !hasRole(ROLE_ACCOUNTANT)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get input
$input = json_decode(file_get_contents('php://input'), true);
$request_id = $input['request_id'] ?? null;
$status = $input['status'] ?? null;

if (!$request_id || !$status) {
    echo json_encode(['success' => false, 'message' => 'Request ID and status are required']);
    exit;
}

$allowed_statuses = ['under_review', 'processing'];
if (!in_array($status, $allowed_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

try {
    $update_fields = ['request_status' => $status];

    if ($status === 'under_review') {
        $update_fields['reviewed_by'] = $_SESSION['user_id'];
        $update_fields['reviewed_at'] = date('Y-m-d H:i:s');
    }

    $set_clause = [];
    $params = [];
    foreach ($update_fields as $field => $value) {
        $set_clause[] = "$field = ?";
        $params[] = $value;
    }
    $params[] = $request_id;

    $sql = "UPDATE tbl_refund_requests SET " . implode(', ', $set_clause) . " WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    echo json_encode([
        'success' => true,
        'message' => 'Status updated to ' . str_replace('_', ' ', $status)
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
