<?php

require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';
$base_path = dirname(dirname(__DIR__));
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once $base_path . '/../common/helpers/error_logger.php';

// Set error log path explicitly
ini_set('log_errors', 1);
ini_set('error_log', 'C:/xampp/php/logs/php_error_log');

header('Content-Type: application/json');

// Test error logging
error_log("=== FEE CONFIG UPDATE STARTED ===");
error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
error_log("Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));

// Check if user is Super Admin or Principle
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Read POST data - handle both form data and JSON
$postData = $_POST;
if (empty($postData)) {
    $rawInput = file_get_contents('php://input');
    $jsonData = json_decode($rawInput, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
        $postData = $jsonData;
    }
}

// Log incoming data for debugging
error_log("FEE CONFIG UPDATE - Incoming data: " . json_encode([
    'postData' => $postData,
    'user_id' => $_SESSION['user_id'] ?? 'unknown',
    'user_role' => $_SESSION['role'] ?? 'unknown'
], JSON_PRETTY_PRINT));

try {
    $conn->beginTransaction();

    // Validate required fields
    if (
        empty($postData['id']) ||
        empty($postData['academic_year']) || empty($postData['course_id']) ||
        empty($postData['term']) || empty($postData['medium']) || empty($postData['group_type']) ||
        empty($postData['school_id']) ||
        !isset($postData['school_fee']) || !isset($postData['trust_facilities_fee']) ||
        !isset($postData['tuition_fee_part1']) || !isset($postData['tuition_fee_part2']) ||
        !isset($postData['token_fee']) || !isset($postData['total_fees']) ||
        empty($postData['number_of_installments'])
    ) {
        throw new Exception('All required fields must be filled');
    }

    $config_id = $postData['id'];
    $school_id = intval($postData['school_id']);

    if ($school_id <= 0) {
        throw new Exception('Please select a valid school');
    }

    // Get course_name from course_id
    $course_id = intval($postData['course_id']);
    $stmt = $conn->prepare("SELECT course_name FROM tbl_courses WHERE id = ? AND is_active = 1");
    $stmt->execute([$course_id]);
    $course_name = $stmt->fetchColumn();
    if (!$course_name) {
        throw new Exception('Invalid course selected');
    }

    // Get medium_id from medium_name
    $stmt = $conn->prepare("SELECT id FROM tbl_medium WHERE medium_name = ? AND is_active = 1");
    $stmt->execute([$postData['medium']]);
    $medium_id = $stmt->fetchColumn();
    if (!$medium_id) {
        throw new Exception('Invalid medium selected');
    }

    // Get group_id from group_name
    $stmt = $conn->prepare("SELECT id FROM tbl_group WHERE group_name = ? AND is_active = 1");
    $stmt->execute([$postData['group_type']]);
    $group_id = $stmt->fetchColumn();
    if (!$group_id) {
        throw new Exception('Invalid group selected');
    }

    // Calculate new payable fees
    $new_total_fees = floatval($postData['total_fees']);
    $new_token_fee = floatval($postData['token_fee']);
    $new_payable_fees = $new_total_fees - $new_token_fee;
    $new_installments = intval($postData['number_of_installments']);

    // Update fee configuration with split labels, GST flags, and school_id
    $stmt = $conn->prepare("UPDATE tbl_fee_config 
                            SET academic_year = ?, term = ?, course_id = ?, course_name = ?, medium_id = ?, group_id = ?, school_id = ?,
                                school_fee = ?, school_fee_label = ?, school_fee_gst = ?,
                                trust_facilities_fee = ?, trust_fee_label = ?, trust_fee_gst = ?,
                                tuition_fee_part1 = ?, token_fee_label = ?, token_fee_gst = ?,
                                tuition_fee_part2 = ?, tuition_fee_label = ?, tuition_fee_gst = ?,
                                token_fee = ?, total_fees = ?, number_of_installments = ?, 
                                updated_at = NOW()
                            WHERE id = ?");
    $stmt->execute([
        $postData['academic_year'],
        $postData['term'],
        $course_id,
        $course_name,
        $medium_id,
        $group_id,
        $school_id,
        $postData['school_fee'],
        $postData['school_fee_label'] ?? null,
        isset($postData['school_fee_gst']) && $postData['school_fee_gst'] ? 1 : 0,
        $postData['trust_facilities_fee'],
        $postData['trust_fee_label'] ?? null,
        isset($postData['trust_fee_gst']) && $postData['trust_fee_gst'] ? 1 : 0,
        $postData['tuition_fee_part1'],
        $postData['token_fee_label'] ?? null,
        isset($postData['token_fee_gst']) && $postData['token_fee_gst'] ? 1 : 0,
        $postData['tuition_fee_part2'],
        $postData['tuition_fee_label'] ?? null,
        isset($postData['tuition_fee_gst']) && $postData['tuition_fee_gst'] ? 1 : 0,
        $postData['token_fee'],
        $postData['total_fees'],
        $postData['number_of_installments'],
        $config_id
    ]);

    // Update student fee allocations if any exist for this config
    $stmt = $conn->prepare("SELECT id, student_id, paid_amount FROM tbl_student_fee_allocation WHERE fee_config_id = ?");
    $stmt->execute([$config_id]);
    $allocations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $students_updated = 0;
    foreach ($allocations as $allocation) {
        // Calculate new pending amount (new payable - already paid)
        $new_pending = $new_payable_fees - $allocation['paid_amount'];
        if ($new_pending < 0)
            $new_pending = 0;

        // Update allocation record
        $stmt = $conn->prepare("UPDATE tbl_student_fee_allocation 
                               SET allocated_amount = ?, pending_amount = ?, updated_at = NOW()
                               WHERE id = ?");
        $stmt->execute([$new_payable_fees, $new_pending, $allocation['id']]);

        // Delete old installments that are not paid and recreate with new amounts
        $stmt = $conn->prepare("DELETE FROM tbl_fee_installments 
                               WHERE allocation_id = ? AND payment_status = 'pending'");
        $stmt->execute([$allocation['id']]);

        // Get count of remaining paid installments
        $stmt = $conn->prepare("SELECT COUNT(*), MAX(installment_number) as max_inst 
                               FROM tbl_fee_installments 
                               WHERE allocation_id = ? AND payment_status != 'pending'");
        $stmt->execute([$allocation['id']]);
        $paid_info = $stmt->fetch(PDO::FETCH_ASSOC);
        $paid_installments_count = $paid_info[0] ?? 0;
        $max_paid_installment = $paid_info['max_inst'] ?? 0;

        // Create remaining installments with new amounts
        $remaining_installments = $new_installments - $paid_installments_count;
        if ($remaining_installments > 0 && $new_pending > 0) {
            $amount_per_remaining = $new_pending / $remaining_installments;

            for ($i = 1; $i <= $remaining_installments; $i++) {
                $installment_number = $max_paid_installment + $i;
                $stmt = $conn->prepare("INSERT INTO tbl_fee_installments 
                    (allocation_id, student_id, fee_config_id, installment_number, 
                    due_amount, paid_amount, payment_status, created_by) 
                    VALUES (?, ?, ?, ?, ?, 0.00, 'pending', ?)");
                $stmt->execute([
                    $allocation['id'],
                    $allocation['student_id'],
                    $config_id,
                    $installment_number,
                    $amount_per_remaining,
                    $_SESSION['user_id'] ?? 0
                ]);
            }
        }

        $students_updated++;
    }

    $conn->commit();

    $message = 'Fee configuration updated successfully';
    if ($students_updated > 0) {
        $message .= ". Updated fees for {$students_updated} student(s).";
    }
    echo json_encode(['success' => true, 'message' => $message]);
} catch (PDOException $e) {
    $conn->rollBack();

    // Log the detailed error
    $error_details = [
        'file' => 'fee-config-update.php',
        'error_type' => 'PDOException',
        'error_code' => $e->getCode(),
        'error_message' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'post_data' => $postData,
        'user_id' => $_SESSION['user_id'] ?? 'unknown'
    ];
    error_log("FEE CONFIG UPDATE ERROR: " . json_encode($error_details, JSON_PRETTY_PRINT));

    if ($e->getCode() == 23000) {
        echo json_encode(['success' => false, 'message' => 'Configuration already exists for this course and academic year']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} catch (Exception $e) {
    $conn->rollBack();

    // Log the detailed error
    $error_details = [
        'file' => 'fee-config-update.php',
        'error_type' => 'Exception',
        'error_message' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'post_data' => $postData ?? [],
        'user_id' => $_SESSION['user_id'] ?? 'unknown'
    ];
    error_log("FEE CONFIG UPDATE ERROR: " . json_encode($error_details, JSON_PRETTY_PRINT));

    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
