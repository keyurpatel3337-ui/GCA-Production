<?php
/**
 * API: Send Bulk Fee Reminders
 * Receives array of student_ids and sends Email + WhatsApp reminders
 */

header('Content-Type: application/json; charset=utf-8');

require_once dirname(dirname(__DIR__)) . '/common/constants.php';
require_once __DIR__ . '/../session_config.php';
require_once PORTAL_GLOBALVARIABLE;
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once HELPERS_PATH . 'format_helper.php';
require_once HELPERS_PATH . 'fee_helper.php';
require_once HELPERS_PATH . 'notification_functions.php';

// Check access
if (!hasRole(ROLE_ACCOUNTANT) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$student_ids = $input['student_ids'] ?? [];

if (empty($student_ids)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Student IDs are required']);
    exit;
}

$sent_count = 0;
$errors = [];

foreach ($student_ids as $student_id) {
    try {
        // Fetch student details
        $stmt = $conn->prepare("SELECT student_name, surname, fathers_name, email, mob, parent_mob FROM tbl_gm_std_registration WHERE id = ?");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$student) continue;

        // Calculate pending amount
        $summary = calculateStudentFeeSummary($conn, $student_id, true);
        $pending_amount = $summary['total_pending'] ?? 0;

        if ($pending_amount <= 0) continue;

        // Prepare notification
        $recipient = [
            'name' => $student['student_name'] . ' ' . $student['surname'],
            'email' => $student['email'],
            'mobile' => $student['mob']
        ];

        $variables = [
            'student_name' => $student['student_name'],
            'amount' => formatIndianCurrency($pending_amount),
            'due_date' => date('d-M-Y'), 
            'portal_url' => PORTAL_URL
        ];

        $options = [
            'student_id' => $student_id,
            'reference_type' => 'fee',
            'reference_id' => $student_id
        ];

        // Send notification
        $result = sendNotification($conn, 'fee_reminder', $recipient, $variables, $options);
        
        if ($result['whatsapp']['success'] || $result['email']['success']) {
            $sent_count++;
        }

    } catch (Exception $e) {
        $errors[] = "Student ID $student_id: " . $e->getMessage();
    }
}

echo json_encode([
    'success' => true,
    'count' => $sent_count,
    'total_requested' => count($student_ids),
    'errors' => $errors
]);
