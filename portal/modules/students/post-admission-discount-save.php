<?php
header('Content-Type: text/html; charset=utf-8');
/**
 * Post-Admission Discount Save Controller
 * Processes discount application for enrolled students
 */

require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once OPERATION_FILE;
require_once HELPER_ERROR_LOGGER;
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/format_helper.php';
require_once HELPERS_PATH . 'fee_helper.php';

// Initialize database operations
if (!isset($dbOps)) {
    $dbOps = new DatabaseOperations();
}

// Role check - Only Principal, Super Admin and Accountant can access
if (!hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_ACCOUNTANT)) {
    set_flash_message('error', "Access denied. Unauthorized to request or apply post-admission discounts.");
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$is_accountant = hasRole(ROLE_ACCOUNTANT) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN);

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash_message('error', "Invalid request method.");
    header('Location: post-admission-discount.php');
    exit;
}

// Get and validate input
$enrollment_id = intval($_POST['enrollment_id'] ?? 0);
$discount_type = $_POST['discount_type'] ?? '';
$discount_value = floatval($_POST['discount_value'] ?? 0);
$discount_reason = trim($_POST['discount_reason'] ?? '');

// Validation
$errors = [];

if ($enrollment_id <= 0) {
    $errors[] = "Invalid student selection.";
}

if (!in_array($discount_type, ['fixed', 'percentage', 'smart'])) {
    $errors[] = "Invalid discount type.";
}

if ($discount_value <= 0) {
    $errors[] = "Discount value must be greater than 0.";
}

if ($discount_type === 'percentage' && $discount_value > 100) {
    $errors[] = "Percentage discount cannot exceed 100%.";
}

if (empty($discount_reason)) {
    $errors[] = "Discount reason is required.";
}

if (!empty($errors)) {
    set_flash_message('error', implode('<br>', $errors));
    header('Location: post-admission-discount.php');
    exit;
}

try {
    // Start transaction
    $dbOps->beginTransaction();

    // Log the input data
    error_log("Post-Admission Discount - Input: Enrollment ID: $enrollment_id, Type: $discount_type, Value: $discount_value");

    // Get current student fee details using customSelect
    $student = $dbOps->customSelectOne("
        SELECT 
            e.enrollment_id,
            e.enrollment_no,
            e.registration_id,
            COALESCE(sfa.allocated_amount, 0) AS net_fees,
            COALESCE(sfa.paid_amount, 0) AS fees_paid,
            COALESCE(sfa.pending_amount, 0) AS fees_pending,
            COALESCE(e.post_admission_discount_amount, 0) AS post_admission_discount_amount
        FROM tbl_enrolled_students e
        LEFT JOIN tbl_student_fee_allocation sfa ON e.registration_id = sfa.student_id
        WHERE e.enrollment_id = ? AND e.is_active = 1
    ", [$enrollment_id]);

    // Log the fetched student data
    error_log("Post-Admission Discount - Student data: " . json_encode($student));

    if (!$student) {
        error_log("Post-Admission Discount - ERROR: Student not found for enrollment_id: $enrollment_id");
        throw new Exception("Student not found or enrollment is inactive.");
    }

    // Optimization: Check if we need to fetch fees via helper (if joined values are 0)
    if (floatval($student['net_fees'] ?? 0) <= 0) {
        $fee_summary = calculateStudentFeeSummary($conn, $student['registration_id']);
        if (!empty($fee_summary)) {
            $student['net_fees'] = $fee_summary['total_allocated'];
            $student['fees_paid'] = $fee_summary['total_paid'];
            $student['fees_pending'] = $fee_summary['total_pending'];
        }
    }

    $current_pending = floatval($student['fees_pending']);
    $current_net_fees = floatval($student['net_fees']);
    $existing_discount = floatval($student['post_admission_discount_amount'] ?? 0);

    // Log calculated values
    error_log("Post-Admission Discount - Current pending: $current_pending, Net fees: $current_net_fees, Existing discount: $existing_discount");

    // Calculate discount amount
    if ($discount_type === 'percentage') {
        $discount_amount = ($current_pending * $discount_value) / 100;
    } else {
        // For 'fixed' and 'smart', the discount_value is the actual amount
        $discount_amount = $discount_value;
    }

    // Validate discount against pending amount
    if ($discount_amount > $current_pending) {
        error_log("Post-Admission Discount - ERROR: Discount ($discount_amount) exceeds pending amount ($current_pending).");
        throw new Exception("Discount amount cannot exceed the pending fees amount of ₹" . formatIndianCurrency($current_pending));
    }

    // Round to 2 decimal places
    $discount_amount = round($discount_amount, 2);

    // Log calculated discount
    error_log("Post-Admission Discount - Calculated discount: $discount_amount (Type: $discount_type, Value: $discount_value)");

    // Calculate new values
    $new_pending = $current_pending - $discount_amount;
    $new_net_fees = $current_net_fees - $discount_amount;
    $total_post_admission_discount = $existing_discount + $discount_amount;

    $status = $is_accountant ? 'pending' : 'approved';

    // Log new values
    error_log("Post-Admission Discount - New pending: $new_pending, Status: $status, Total discount: $total_post_admission_discount");

    if (!$is_accountant) {
        // Update enrolled students table using update method - Only for direct approval
        $dbOps->update('tbl_enrolled_students', [
            'post_admission_discount_amount' => $total_post_admission_discount,
            'post_admission_discount_remarks' => $discount_reason,
            'updated_at' => date('Y-m-d H:i:s')
        ], [
            'enrollment_id' => $enrollment_id
        ]);
    }

    // Insert audit log using insert method
    $dbOps->insert('tbl_post_admission_discounts', [
        'enrollment_id' => $enrollment_id,
        'student_id' => $student['registration_id'],
        'discount_type' => $discount_type,
        'discount_value' => $discount_value,
        'discount_amount' => $discount_amount,
        'requested_amount' => $discount_amount,
        'status' => $status,
        'previous_pending' => $current_pending,
        'new_pending' => $new_pending,
        'remarks' => $discount_reason,
        'created_by' => $_SESSION['user_id'],
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
    ]);

    if (!$is_accountant) {
        // Update tbl_student_fee_allocation using execute - Only for direct approval
        $updateResult = $dbOps->execute("
            UPDATE tbl_student_fee_allocation 
            SET 
                pending_amount = pending_amount - ?,
                updated_at = NOW()
            WHERE student_id = ?
        ", [
            $discount_amount,
            $student['registration_id']
        ]);
        error_log("Post-Admission Discount - tbl_student_fee_allocation update result: " . ($updateResult ? 'SUCCESS' : 'FAILED'));
    }

    // Commit transaction
    $dbOps->commit();

    // Set success message
    if ($is_accountant) {
        set_flash_message('success', "Discount request of ₹" . formatIndianCurrency($discount_amount ?? 0) . " submitted successfully for {$student['enrollment_no']}. Waiting for Principal approval.");
    } else {
        set_flash_message('success', "Discount of ₹" . formatIndianCurrency($discount_amount ?? 0) . " applied successfully to {$student['enrollment_no']}");
    }

} catch (Exception $e) {
    // Rollback on error
    $dbOps->rollback();

    logError("Post-Admission Discount Error: " . $e->getMessage(), __FILE__, __LINE__, $e);
    set_flash_message('error', "Failed to apply discount: " . $e->getMessage());
}

header('Location: post-admission-discount.php');
exit;

