<?php

/**
 * Submit Refund Request API
 * Handles student refund request submission
 */

session_start();
header('Content-Type: application/json');

require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once HELPER_ERROR_LOGGER;
require_once HELPER_NOTIFICATION_FUNCTIONS;
require_once HELPER_WHATSAPP_FUNCTIONS;
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/format_helper.php';

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$student_id = $_SESSION['student_id'];

try {
    // Validate input
    $payment_id = $_POST['payment_id'] ?? null;
    $refund_type = $_POST['refund_type'] ?? 'full';
    $refund_amount = $_POST['refund_amount'] ?? null;
    $refund_reason = trim($_POST['refund_reason'] ?? '');
    $refund_mode = $_POST['refund_mode'] ?? 'gateway';

    // Validation
    if (!$payment_id || !$refund_reason) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }

    if (strlen($refund_reason) < 20) {
        echo json_encode(['success' => false, 'message' => 'Reason must be at least 20 characters']);
        exit;
    }

    // Fetch payment details
    $stmt = $conn->prepare("
        SELECT * FROM tbl_payments 
        WHERE id = ? AND student_id = ? AND status = 'paid'
    ");
    $stmt->execute([$payment_id, $student_id]);
    $payment = $stmt->fetch();

    if (!$payment) {
        echo json_encode(['success' => false, 'message' => 'Payment not found or not eligible for refund']);
        exit;
    }

    // Check if refund request already exists
    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM tbl_refund_requests 
        WHERE payment_id = ? AND request_status NOT IN ('rejected', 'failed')
    ");
    $stmt->execute([$payment_id]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'A refund request already exists for this payment']);
        exit;
    }

    // Determine refund amount
    if ($refund_type === 'full') {
        $refund_amount = $payment['amount'];
    } else {
        $refund_amount = floatval($refund_amount);
        if ($refund_amount <= 0 || $refund_amount > $payment['amount']) {
            echo json_encode(['success' => false, 'message' => 'Invalid refund amount']);
            exit;
        }
    }

    // Handle file uploads
    $uploaded_files = [];
    if (isset($_FILES['supporting_documents'])) {
        $upload_dir = '../../uploads/refund_documents/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_count = count($_FILES['supporting_documents']['name']);
        for ($i = 0; $i < $file_count && $i < 5; $i++) {
            if ($_FILES['supporting_documents']['error'][$i] === UPLOAD_ERR_OK) {
                $file_name = $_FILES['supporting_documents']['name'][$i];
                $file_tmp = $_FILES['supporting_documents']['tmp_name'][$i];
                $file_size = $_FILES['supporting_documents']['size'][$i];

                // Validate file size (2MB max)
                if ($file_size > 2 * 1024 * 1024) {
                    continue;
                }

                // Generate unique filename
                $ext = pathinfo($file_name, PATHINFO_EXTENSION);
                $unique_name = 'refund_' . $student_id . '_' . time() . '_' . $i . '.' . $ext;
                $target_path = $upload_dir . $unique_name;

                if (move_uploaded_file($file_tmp, $target_path)) {
                    $uploaded_files[] = $unique_name;
                }
            }
        }
    }

    // Bank details (if applicable)
    $bank_details = null;
    if ($refund_mode === 'bank_transfer') {
        $bank_details = [
            'account_number' => $_POST['bank_account_number'] ?? null,
            'ifsc' => $_POST['bank_ifsc'] ?? null,
            'account_holder' => $_POST['bank_account_holder'] ?? null,
            'bank_name' => $_POST['bank_name'] ?? null
        ];
    }

    $conn->beginTransaction();

    try {
        // Generate request number
        $request_number = 'REF-' . date('Ymd') . '-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);

        // Check if request number exists
        $stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_refund_requests WHERE request_number = ?");
        $stmt->execute([$request_number]);
        while ($stmt->fetchColumn() > 0) {
            $request_number = 'REF-' . date('Ymd') . '-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
            $stmt->execute([$request_number]);
        }

        // Get user ID for student
        $stmt = $conn->prepare("SELECT id FROM tbl_users WHERE email = (SELECT mob FROM tbl_gm_std_registration WHERE id = ?) LIMIT 1");
        $stmt->execute([$student_id]);
        $user_id = $stmt->fetchColumn();

        if (!$user_id) {
            // If no user found, use student_id as fallback
            $user_id = $student_id;
        }

        // Insert refund request
        $stmt = $conn->prepare("
            INSERT INTO tbl_refund_requests (
                request_number, payment_id, student_id, refund_amount, refund_reason, refund_type,
                request_status, requested_by, requested_by_role, refund_mode,
                bank_account_number, bank_ifsc, bank_account_holder, bank_name,
                supporting_documents, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, 'student', ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $request_number,
            $payment_id,
            $student_id,
            $refund_amount,
            $refund_reason,
            $refund_type,
            $user_id,
            $refund_mode,
            $bank_details['account_number'] ?? null,
            $bank_details['ifsc'] ?? null,
            $bank_details['account_holder'] ?? null,
            $bank_details['bank_name'] ?? null,
            empty($uploaded_files) ? null : json_encode($uploaded_files)
        ]);

        $refund_request_id = $conn->lastInsertId();

        $conn->commit();

        // Send refund request notification to student and accountant
        try {
            // Fetch student details
            $stmt = $conn->prepare("SELECT r.*, c.course_name 
                                   FROM tbl_gm_std_registration r
                                   LEFT JOIN tbl_courses c ON r.course_id = c.id
                                   WHERE r.id = ?");
            $stmt->execute([$student_id]);
            $student = $stmt->fetch();

            if ($student) {
                $recipient = [
                    'name' => $student['full_name'],
                    'email' => $student['email'],
                    'mobile' => $student['mob']
                ];

                $variables = [
                    'student_name' => $student['full_name'],
                    'request_number' => $request_number,
                    'refund_amount' => formatIndianCurrency($refund_amount),
                    'refund_type' => ucfirst($refund_type),
                    'refund_reason' => $refund_reason,
                    'refund_mode' => ucfirst(str_replace('_', ' ', $refund_mode)),
                    'request_date' => date('d-M-Y'),
                    'course_name' => $student['course_name'] ?? 'N/A'
                ];

                // Send notification to student
                sendNotification(
                    $conn,
                    'refund_request_submitted',
                    $recipient,
                    $variables,
                    ['student_id' => $student_id, 'refund_request_id' => $refund_request_id]
                );
            }
        } catch (Exception $e) {
            // Log but don't fail the request
            logError("Refund request notification error: " . $e->getMessage());
        }

        echo json_encode([
            'success' => true,
            'message' => 'Refund request submitted successfully! Request Number: ' . $request_number,
            'request_number' => $request_number,
            'request_id' => $refund_request_id
        ]);
    } catch (Exception $e) {
        $conn->rollBack();
        logError("Refund Request Submission Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error submitting refund request']);
    }
} catch (Exception $e) {
    logError("Refund Request API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error occurred']);
}
