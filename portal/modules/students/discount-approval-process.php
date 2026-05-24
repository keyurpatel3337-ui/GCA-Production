<?php
header('Content-Type: application/json');
/**
 * Discount Approval Process
 * Handles Principal's decision on pending discount requests
 */

require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/format_helper.php';

// Role check - Only Principal and Super Admin can access
if (!hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$dbOps = new DatabaseOperations();
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$action = $_POST['action'] ?? '';
$final_amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
$reason = $_POST['reason'] ?? '';

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid Request ID']);
    exit;
}

try {
    // 1. Fetch the request details
    $req = $dbOps->customSelectOne("SELECT * FROM tbl_post_admission_discounts WHERE discount_id = ? AND status = 'pending'", [$id]);

    if (!$req) {
        echo json_encode(['success' => false, 'message' => 'Request not found or already processed']);
        exit;
    }

    $enrollment_id = $req['enrollment_id'];
    $student_id = $req['student_id'];

    if ($action === 'approve') {
        if ($final_amount < 0) {
            echo json_encode(['success' => false, 'message' => 'Amount cannot be negative']);
            exit;
        }

        $dbOps->beginTransaction();

        // A. Update the discount record
        $dbOps->update('tbl_post_admission_discounts', [
            'status' => 'approved',
            'discount_amount' => $final_amount,
            'approved_by' => $_SESSION['user_id'],
            'approved_at' => date('Y-m-d H:i:s')
        ], ['discount_id' => $id]);

        // B. Update tbl_enrolled_students (Increment the total post-admission discount)
        $dbOps->execute("
            UPDATE tbl_enrolled_students 
            SET 
                post_admission_discount_amount = post_admission_discount_amount + ?,
                post_admission_discount_remarks = CONCAT(IFNULL(post_admission_discount_remarks, ''), ' | Approved: ', ?),
                updated_at = NOW()
            WHERE enrollment_id = ?
        ", [$final_amount, $req['remarks'], $enrollment_id]);

        // C. Update tbl_student_fee_allocation (Decreasing the pending amount)
        $dbOps->execute("
            UPDATE tbl_student_fee_allocation 
            SET 
                pending_amount = pending_amount - ?,
                updated_at = NOW()
            WHERE student_id = ?
        ", [$final_amount, $student_id]);

        $dbOps->commit();
        echo json_encode(['success' => true, 'message' => 'Discount approved and ledger updated successfully']);

    } elseif ($action === 'reject') {
        $dbOps->update('tbl_post_admission_discounts', [
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'approved_by' => $_SESSION['user_id'],
            'approved_at' => date('Y-m-d H:i:s')
        ], ['discount_id' => $id]);

        echo json_encode(['success' => true, 'message' => 'Discount request rejected']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }

} catch (Exception $e) {
    $dbOps->rollback();
    logError("Discount Approval Process Error: " . $e->getMessage(), __FILE__, __LINE__, $e);
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}
