<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once HELPER_ERROR_LOGGER;
require_once HELPER_NOTIFICATION_FUNCTIONS;
require_once HELPER_WHATSAPP_FUNCTIONS;

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    sendErrorResponse('Unauthorized', 401);
}

if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != ROLE_PRINCIPLE) {
    sendErrorResponse('Access denied', 403);
}

$request_id = intval($_POST['request_id'] ?? 0);
$action = $_POST['action'] ?? '';
$review_remarks = trim($_POST['review_remarks'] ?? '');

if (!in_array($action, ['approve', 'reject'])) {
    sendErrorResponse('Invalid action', 400);
}

if (empty($review_remarks)) {
    sendErrorResponse('Review remarks are required', 400);
}

try {
    // Fetch full request details
    $stmt = $conn->prepare("SELECT gcr.*, s.student_name, s.group_id as current_db_group_id
                            FROM tbl_group_change_requests gcr
                            LEFT JOIN tbl_gm_std_registration s ON gcr.student_id = s.id
                            WHERE gcr.id = ?");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        throw new Exception("Request not found!");
    }

    if (!in_array($request['status'], ['pending', 'under_review'])) {
        throw new Exception("This request has already been processed!");
    }

    $conn->beginTransaction();

    $new_status = $action === 'approve' ? 'approved' : 'rejected';
    $old_status = $request['status'];

    // Update request with review info
    $stmt = $conn->prepare("UPDATE tbl_group_change_requests 
                            SET status = ?,
                                reviewed_by = ?,
                                review_date = NOW(),
                                review_comments = ?
                            WHERE id = ?");
    $stmt->execute([$new_status, $_SESSION['user_id'], $review_remarks, $request_id]);

    // Log in history
    $stmt = $conn->prepare("INSERT INTO tbl_group_change_history 
                            (request_id, action_type, action_by, action_by_role, old_status, new_status, remarks)
                            VALUES (?, ?, ?, 'principal', ?, ?, ?)");
    $stmt->execute([$request_id, $new_status, $_SESSION['user_id'], $old_status, $new_status, $review_remarks]);

    // If approved, perform group update
    if ($action === 'approve') {
        // 1. Update student's group in tbl_gm_std_registration
        $stmt = $conn->prepare("UPDATE tbl_gm_std_registration 
                                SET group_id = ? 
                                WHERE id = ?");
        $stmt->execute([$request['requested_group_id'], $request['student_id']]);

        // Log group update in history
        $stmt = $conn->prepare("INSERT INTO tbl_group_change_history 
                                (request_id, action_type, action_by, action_by_role, old_status, new_status, remarks)
                                VALUES (?, 'group_updated', ?, 'system', 'approved', 'approved', 
                                        'Student group updated successfully')");
        $stmt->execute([$request_id, $_SESSION['user_id']]);

        // Send approval notification
        try {
            // Fetch student and group details
            $stmt = $conn->prepare("SELECT r.*, 
                                           cg.group_name as old_group_name,
                                           ng.group_name as new_group_name,
                                           c.course_name,
                                           u.name as approver_name
                                    FROM tbl_gm_std_registration r
                                    LEFT JOIN tbl_group cg ON ? = cg.id
                                    LEFT JOIN tbl_group ng ON ? = ng.id
                                    LEFT JOIN tbl_courses c ON r.course_id = c.id
                                    LEFT JOIN tbl_users u ON ? = u.id
                                    WHERE r.id = ?");
            $stmt->execute([$request['current_group_id'], $request['requested_group_id'], $_SESSION['user_id'], $request['student_id']]);
            $student = $stmt->fetch();

            if ($student) {
                $recipient = [
                    'name' => $student['full_name'],
                    'email' => $student['email'],
                    'mobile' => $student['mob']
                ];

                $variables = [
                    'student_name' => $student['full_name'],
                    'request_id' => 'REQ-' . $request_id,
                    'old_group' => $student['old_group_name'] ?? 'N/A',
                    'new_group' => $student['new_group_name'] ?? 'N/A',
                    'course_name' => $student['course_name'] ?? 'N/A',
                    'approver_name' => $student['approver_name'] ?? 'Principal',
                    'approval_date' => date('d-M-Y')
                ];

                sendNotification(
                    $conn,
                    'group_change_approved',
                    $recipient,
                    $variables,
                    ['student_id' => $request['student_id'], 'request_id' => $request_id]
                );
            }
        } catch (Exception $e) {
            logError("Group change approval notification error: " . $e->getMessage());
        }
    } else {
        // Send rejection notification
        try {
            // Fetch student and group details
            $stmt = $conn->prepare("SELECT r.*, 
                                           cg.group_name as current_group_name,
                                           ng.group_name as requested_group_name,
                                           c.course_name,
                                           u.name as reviewer_name
                                    FROM tbl_gm_std_registration r
                                    LEFT JOIN tbl_group cg ON ? = cg.id
                                    LEFT JOIN tbl_group ng ON ? = ng.id
                                    LEFT JOIN tbl_courses c ON r.course_id = c.id
                                    LEFT JOIN tbl_users u ON ? = u.id
                                    WHERE r.id = ?");
            $stmt->execute([$request['current_group_id'], $request['requested_group_id'], $_SESSION['user_id'], $request['student_id']]);
            $student = $stmt->fetch();

            if ($student) {
                $recipient = [
                    'name' => $student['full_name'],
                    'email' => $student['email'],
                    'mobile' => $student['mob']
                ];

                $variables = [
                    'student_name' => $student['full_name'],
                    'request_id' => 'REQ-' . $request_id,
                    'current_group' => $student['current_group_name'] ?? 'N/A',
                    'requested_group' => $student['requested_group_name'] ?? 'N/A',
                    'course_name' => $student['course_name'] ?? 'N/A',
                    'reviewer_name' => $student['reviewer_name'] ?? 'Principal',
                    'rejection_reason' => $review_remarks,
                    'rejection_date' => date('d-M-Y')
                ];

                sendNotification(
                    $conn,
                    'group_change_rejected',
                    $recipient,
                    $variables,
                    ['student_id' => $request['student_id'], 'request_id' => $request_id]
                );
            }
        } catch (Exception $e) {
            logError("Group change rejection notification error: " . $e->getMessage());
        }
    }

    $conn->commit();

    $message = $action === 'approve'
        ? 'Request approved successfully! Student group and fees have been updated.'
        : 'Request rejected successfully.';

    sendSuccessResponse(['request_id' => $request_id, 'action' => $action], $message);
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    logError("Process Review Error: " . $e->getMessage() . "\nStack: " . $e->getTraceAsString());
    sendErrorResponse('Error: ' . $e->getMessage(), 500);
}
