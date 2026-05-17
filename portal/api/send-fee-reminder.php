<?php
/**
 * API: Send Individual Fee Reminder
 * Receives student_id and sends Email + WhatsApp reminder
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
$student_id = $input['student_id'] ?? null;

if (!$student_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Student ID is required']);
    exit;
}

try {
    // Fetch student details
    $stmt = $conn->prepare("SELECT s.student_name, s.surname, s.fathers_name, s.email, s.mob, c.course_name, pp.updated_at as due_date 
                          FROM tbl_gm_std_registration s 
                          LEFT JOIN tbl_courses c ON s.course_id = c.id
                          LEFT JOIN tbl_pending_payments pp ON s.id = pp.student_id
                          WHERE s.id = ? LIMIT 1");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        throw new Exception("Student not found");
    }

    // Calculate pending amount
    $summary = calculateStudentFeeSummary($conn, $student_id, true);
    $pending_amount = $summary['total_pending'] ?? 0;

    if ($pending_amount <= 0) {
        echo json_encode(['success' => true, 'message' => 'Student has no pending fees. No reminder sent.']);
        exit;
    }

    // Prepare notification
    $recipient = [
        'name' => $student['student_name'] . ' ' . $student['surname'],
        'email' => $student['email'],
        'mobile' => $student['mob']
    ];

    $student_full_name = trim(($student['student_name'] ?? '') . ' ' . ($student['surname'] ?? ''));

    // We only need to pass the raw values, sendNotification will format them
    // and map them to {{1}} and {{2}} for the parent_update_not template.
    $variables = [
        'student_name' => $student['student_name'],
        'student_full_name' => $student_full_name,
        'amount' => $pending_amount, 
        'due_date' => date('d-M-Y'),
        'course_name' => $student['course_name'] ?? 'Fee',
        'installment_number' => 'N/A',
        'days_remaining' => ceil((strtotime($student['due_date'] ?? date('Y-m-d')) - time()) / 86400),
        'payment_url' => PORTAL_URL,
        'portal_url' => PORTAL_URL
    ];

    $options = [
        'student_id' => $student_id,
        'reference_type' => 'fee',
        'reference_id' => $student_id
    ];

    // Send notification
    $result = sendNotification($conn, 'fee_reminder', $recipient, $variables, $options);

    echo json_encode([
        'success' => true,
        'message' => 'Reminder sent successfully',
        'details' => $result
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
