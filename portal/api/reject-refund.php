<?php

/**
 * Reject Refund Request API
 * Admin rejects a refund request
 */

session_start();
require_once dirname(dirname(__DIR__)) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once '../globalvariable.php';
require_once dirname(dirname(__DIR__)) . '/common/helpers/notification_functions.php';
require_once dirname(dirname(__DIR__)) . '/common/helpers/whatsapp_functions.php';
require_once dirname(dirname(__DIR__)) . '/common/helpers/format_helper.php';

header('Content-Type: application/json');

// Check admin access
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get input
$input = json_decode(file_get_contents('php://input'), true);
$request_id = $input['request_id'] ?? null;
$reason = $input['reason'] ?? '';

if (!$request_id || !$reason) {
    echo json_encode(['success' => false, 'message' => 'Request ID and rejection reason are required']);
    exit;
}

if (strlen($reason) < 20) {
    echo json_encode(['success' => false, 'message' => 'Rejection reason must be at least 20 characters']);
    exit;
}

try {
    // Fetch request details
    $request = $dbOps->selectOne('tbl_refund_requests', ['*'], ['id' => $request_id]);

    if (!$request) {
        echo json_encode(['success' => false, 'message' => 'Request not found']);
        exit;
    }

    // Validate current status
    if (!in_array($request['request_status'], ['pending', 'under_review'])) {
        echo json_encode(['success' => false, 'message' => 'Request cannot be rejected in current status']);
        exit;
    }

    // Update request to rejected
    $update_sql = "UPDATE tbl_refund_requests SET 
        request_status = 'rejected',
        rejected_by = ?,
        rejected_at = NOW(),
        rejection_reason = ?
        WHERE id = ?";

    $stmt = $conn->prepare($update_sql);
    $stmt->execute([
        $_SESSION['user_id'],
        $reason,
        $request_id
    ]);

    // Send notification to student and accountant
    sendRefundNotification($request_id, 'rejected', $conn);

    echo json_encode([
        'success' => true,
        'message' => 'Refund request rejected successfully.'
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

function sendRefundNotification($request_id, $action, $conn)
{
    try {
        // Fetch refund request details with student info
        $stmt = $conn->prepare("
            SELECT 
                rr.*,
                r.full_name,
                r.email,
                r.mob as mobile,
                c.course_name,
                p.amount as original_amount,
                p.receipt_no
            FROM tbl_refund_requests rr
            INNER JOIN tbl_gm_std_registration r ON rr.student_id = r.id
            LEFT JOIN tbl_courses c ON r.course_id = c.id
            LEFT JOIN tbl_payments p ON rr.payment_id = p.id
            WHERE rr.id = ?
        ");
        $stmt->execute([$request_id]);
        $request = $stmt->fetch();

        if (!$request) {
            return;
        }

        $recipient = [
            'name' => $request['full_name'],
            'email' => $request['email'],
            'mobile' => $request['mobile']
        ];

        $variables = [
            'student_name' => $request['full_name'],
            'request_number' => $request['request_number'],
            'refund_amount' => formatIndianCurrency($request['refund_amount']),
            'original_amount' => formatIndianCurrency($request['original_amount']),
            'receipt_no' => $request['receipt_no'],
            'course_name' => $request['course_name'] ?? 'N/A',
            'action_date' => date('d-M-Y'),
            'rejection_reason' => $request['rejection_reason'] ?? 'Not specified'
        ];

        sendNotification(
            $conn,
            'refund_rejected',
            $recipient,
            $variables,
            ['student_id' => $request['student_id'], 'refund_request_id' => $request_id]
        );
    } catch (Exception $e) {
        error_log("Refund notification error: " . $e->getMessage());
    }
}
