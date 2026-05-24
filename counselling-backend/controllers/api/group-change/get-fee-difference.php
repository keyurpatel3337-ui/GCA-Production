<?php

/**
 * API Endpoint: Get Fee Difference Preview
 * Calculates fee impact when student changes group
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../common/session_config.php';
require_once dirname(dirname(__DIR__)) . '/../../common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;

// Check authentication
if (!isset($_SESSION['is_student_login']) && !isset($_SESSION['user_role'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get and validate parameters
$student_id = intval($_POST['student_id'] ?? 0);
$current_group_id = intval($_POST['current_group_id'] ?? 0);
$new_group_id = intval($_POST['new_group_id'] ?? 0);

if ($student_id <= 0 || $current_group_id <= 0 || $new_group_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

try {
    // Get student details
    $stmt = $conn->prepare("SELECT board_id, medium_id, course_id, standard, enrollment_id
                            FROM tbl_gm_std_registration WHERE id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        exit;
    }

    // Get current fee config
    $stmt = $conn->prepare("SELECT fc.total_fees, fc.id as fee_config_id, 
                            fc.course_name, g.group_name
                            FROM tbl_fee_config fc
                            LEFT JOIN tbl_group g ON fc.group_id = g.id
                            WHERE fc.board_id = ? AND fc.medium_id = ? 
                            AND fc.group_id = ? AND fc.standard = ? AND fc.is_active = 1
                            LIMIT 1");
    $stmt->execute([
        $student['board_id'],
        $student['medium_id'],
        $current_group_id,
        $student['standard']
    ]);
    $current_fee = $stmt->fetch(PDO::FETCH_ASSOC);
    $current_total = $current_fee['total_fees'] ?? 0;
    $current_config_id = $current_fee['fee_config_id'] ?? null;

    // Get new fee config
    $stmt->execute([
        $student['board_id'],
        $student['medium_id'],
        $new_group_id,
        $student['standard']
    ]);
    $new_fee = $stmt->fetch(PDO::FETCH_ASSOC);
    $new_total = $new_fee['total_fees'] ?? 0;
    $new_config_id = $new_fee['fee_config_id'] ?? null;

    // Get group name for new group
    $stmt = $conn->prepare("SELECT group_name FROM tbl_group WHERE id = ?");
    $stmt->execute([$new_group_id]);
    $new_group = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get fees already paid
    $stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total_paid 
                            FROM tbl_payments 
                            WHERE student_id = ? AND payment_type != 'token_fee'");
    $stmt->execute([$student_id]);
    $payment_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $fees_paid = $payment_data['total_paid'] ?? 0;

    // Calculate difference
    $difference = $new_total - $current_total;
    $current_pending = $current_total - $fees_paid;
    $new_pending = $new_total - $fees_paid;

    // Prepare response
    $response = [
        'success' => true,
        'data' => [
            'current_group' => $current_fee['group_name'] ?? 'N/A',
            'new_group' => $new_group['group_name'] ?? 'N/A',
            'current_total_fees' => $current_total,
            'new_total_fees' => $new_total,
            'fee_difference' => $difference,
            'fees_already_paid' => $fees_paid,
            'current_pending_amount' => $current_pending,
            'new_pending_amount' => $new_pending,
            'current_fee_config_id' => $current_config_id,
            'new_fee_config_id' => $new_config_id,
            'impact_type' => $difference > 0 ? 'increase' : ($difference < 0 ? 'decrease' : 'no_change')
        ]
    ];

    echo json_encode($response);
} catch (PDOException $e) {
    logError("API Get Fee Difference Error: " . $e->getMessage(), __FILE__, __LINE__, $e);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
} catch (Exception $e) {
    logError("API Get Fee Difference Error: " . $e->getMessage(), __FILE__, __LINE__, $e);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred'
    ]);
}
