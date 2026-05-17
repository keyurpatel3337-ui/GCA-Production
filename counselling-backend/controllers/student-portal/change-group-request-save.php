<?php
require_once __DIR__ . '/../../common/session_config.php';
require_once dirname(dirname(__DIR__)) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;
require_once HELPER_NOTIFICATION_FUNCTIONS;
require_once HELPER_WHATSAPP_FUNCTIONS;

// Check if user is Student
if (!isset($_SESSION['is_student_login']) || $_SESSION['is_student_login'] !== true) {
    set_flash_message('error', "Unauthorized access!");
    header('Location: ' . BASE_URL . '/modules/student-portal/change-group-request.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash_message('error', "Invalid request method!");
    header('Location: ' . BASE_URL . '/modules/student-portal/change-group-request.php');
    exit;
}

$student_id = $_SESSION['student_id'];
$enrollment_id = intval($_POST['enrollment_id'] ?? 0);
$current_group_id = intval($_POST['current_group_id'] ?? 0);
$current_course_id = intval($_POST['current_course_id'] ?? 0);
$current_fee_config_id = intval($_POST['current_fee_config_id'] ?? 0);
$requested_group_id = intval($_POST['requested_group_id'] ?? 0);
$reason = trim($_POST['reason'] ?? '');
$fees_already_paid = floatval($_POST['fees_already_paid'] ?? 0);
$scholarship_amount = floatval($_POST['scholarship_amount'] ?? 0);

try {
    // Validation
    if ($enrollment_id <= 0 || $requested_group_id <= 0) {
        throw new Exception("Invalid enrollment or group ID!");
    }

    if (strlen($reason) < 50) {
        throw new Exception("Please provide a detailed reason (minimum 50 characters)!");
    }

    if ($current_group_id === $requested_group_id) {
        throw new Exception("You are already in this group!");
    }

    // Check for existing pending request
    $stmt = $conn->prepare("SELECT id FROM tbl_group_change_requests 
                            WHERE student_id = ? AND status IN ('pending', 'under_review')");
    $stmt->execute([$student_id]);
    if ($stmt->fetch()) {
        throw new Exception("You already have a pending request. Please wait for it to be reviewed.");
    }

    $conn->beginTransaction();

    // Get current fee details
    $current_total_fees = 0;
    if ($current_fee_config_id > 0) {
        $stmt = $conn->prepare("SELECT total_fees FROM tbl_fee_config WHERE id = ?");
        $stmt->execute([$current_fee_config_id]);
        $current_fee = $stmt->fetch(PDO::FETCH_ASSOC);
        $current_total_fees = $current_fee['total_fees'] ?? 0;
    }

    // Get new group's fee config (matching course, medium, group)
    $stmt = $conn->prepare("SELECT s.course_id, s.medium_id
                            FROM tbl_gm_std_registration s
                            WHERE s.id = ?");
    $stmt->execute([$student_id]);
    $student_info = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $conn->prepare("SELECT id, total_fees FROM tbl_fee_config 
                            WHERE course_id = ? AND medium_id = ? AND group_id = ? 
                            AND is_active = 1
                            LIMIT 1");
    $stmt->execute([
        $student_info['course_id'],
        $student_info['medium_id'],
        $requested_group_id
    ]);
    $new_fee_config = $stmt->fetch(PDO::FETCH_ASSOC);

    $new_fee_config_id = $new_fee_config['id'] ?? null;
    $new_total_fees = $new_fee_config['total_fees'] ?? 0;

    // Apply scholarship to both current and new fees
    $current_net_fees = $current_total_fees - $scholarship_amount;
    $new_net_fees = $new_total_fees - $scholarship_amount;

    $fee_difference = $new_net_fees - $current_net_fees;
    $adjusted_pending = $new_net_fees - $fees_already_paid;

    // Insert request
    $stmt = $conn->prepare("INSERT INTO tbl_group_change_requests 
                            (student_id, enrollment_id, 
                             current_group_id, requested_group_id,
                             reason, status)
                            VALUES (?, ?, ?, ?, ?, 'pending')");

    $stmt->execute([
        $student_id,
        $enrollment_id,
        $current_group_id,
        $requested_group_id,
        $reason
    ]);

    $request_id = $conn->lastInsertId();

    // Log in history
    $stmt = $conn->prepare("INSERT INTO tbl_group_change_history 
                            (request_id, action_type, action_by, action_by_role, new_status, remarks)
                            VALUES (?, 'created', ?, 'student', 'pending', 'Request created by student')");
    $stmt->execute([$request_id, $student_id]);

    $conn->commit();

    // Send group change request notifications
    try {
        // Fetch student and group details
        $stmt = $conn->prepare("SELECT r.*, 
                                       cg.group_name as current_group_name,
                                       ng.group_name as new_group_name,
                                       c.course_name
                                FROM tbl_gm_std_registration r
                                LEFT JOIN tbl_group cg ON ? = cg.id
                                LEFT JOIN tbl_group ng ON ? = ng.id
                                LEFT JOIN tbl_courses c ON r.course_id = c.id
                                WHERE r.id = ?");
        $stmt->execute([$current_group_id, $requested_group_id, $student_id]);
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
                'requested_group' => $student['new_group_name'] ?? 'N/A',
                'course_name' => $student['course_name'] ?? 'N/A',
                'reason' => $reason,
                'request_date' => date('d-M-Y')
            ];

            // Send notification to student
            sendNotification(
                $conn,
                'group_change_submitted',
                $recipient,
                $variables,
                ['student_id' => $student_id, 'request_id' => $request_id]
            );
        }
    } catch (Exception $e) {
        // Log but don't fail the request
        logError("Group change request notification error: " . $e->getMessage());
    }

    set_flash_message('success', "Group change request submitted successfully! Request ID: REQ-$request_id");
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    logError("Group Change Request Error: " . $e->getMessage());
    set_flash_message('error', $e->getMessage());
}

header('Location: ' . BASE_URL . '/modules/student-portal/change-group-request.php');
exit;
