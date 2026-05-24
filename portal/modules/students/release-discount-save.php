<?php
header('Content-Type: text/html; charset=utf-8');
/**
 * Release/Modify Discount Save Controller
 * Processes modifications to scholarship and post-admission discount
 */

require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;

// Initialize dbOps
if (!isset($dbOps)) {
    $dbOps = new DatabaseOperations();
}

// Check permissions
if (!hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN)) {
    set_flash_message('error', 'Unauthorized access');
    header('Location: ../../dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: post-admission-discount.php');
    exit;
}

$enrollment_id = intval($_POST['enrollment_id'] ?? 0);
$new_initial_scholarship = floatval($_POST['initial_scholarship'] ?? 0);
$new_post_discount = floatval($_POST['post_discount'] ?? 0);
$reason = trim($_POST['modification_reason'] ?? '');

if ($enrollment_id <= 0 || empty($reason)) {
    set_flash_message('error', 'Invalid input or missing reason.');
    header('Location: post-admission-discount.php');
    exit;
}

try {
    $dbOps->beginTransaction();

    // 1. Fetch current details to calculate difference
    $student = $dbOps->customSelectOne("
        SELECT 
            e.enrollment_id,
            e.registration_id,
            e.enrollment_no,
            e.post_admission_discount_amount,
            r.scholarship_amount,
            r.additional_scholarship_amount,
            COALESCE(sfa.pending_amount, 0) AS fees_pending
        FROM tbl_enrolled_students e
        INNER JOIN tbl_gm_std_registration r ON e.registration_id = r.id
        LEFT JOIN tbl_student_fee_allocation sfa ON e.registration_id = sfa.student_id
        WHERE e.enrollment_id = ?
    ", [$enrollment_id]);

    if (!$student) {
        throw new Exception("Student not found.");
    }

    $reg_id = $student['registration_id'];

    // Original Values
    $old_initial_total = floatval($student['scholarship_amount']) + floatval($student['additional_scholarship_amount']);
    $old_post_discount = floatval($student['post_admission_discount_amount']);
    $old_total_discount = $old_initial_total + $old_post_discount;

    // New Values
    $new_total_discount = $new_initial_scholarship + $new_post_discount;

    // Calculate Difference
    $discount_diff = $new_total_discount - $old_total_discount;

    // 2. Update Registration Table (Scholarship)
    $dbOps->update('tbl_gm_std_registration', [
        'scholarship_amount' => $new_initial_scholarship,
        'additional_scholarship_amount' => 0, // Reset additional as we merged it
        'updated_at' => date('Y-m-d H:i:s')
    ], ['id' => $reg_id]);

    // 3. Update Enrolled Students Table (Post Admission Discount)
    $dbOps->update('tbl_enrolled_students', [
        'post_admission_discount_amount' => $new_post_discount,
        'updated_at' => date('Y-m-d H:i:s')
    ], ['enrollment_id' => $enrollment_id]);

    // 4. Update Fee Allocation (Pending Amount)
    // Pending = OldPending - Diff
    // Example: OldPending=5000. Increase Discount by 2000 (Diff=2000). NewPending = 5000 - 2000 = 3000. Correct.
    // Example: OldPending=3000. Decrease Discount by 1000 (Diff=-1000). NewPending = 3000 - (-1000) = 4000. Correct.

    $new_pending = floatval($student['fees_pending']) - $discount_diff;
    if ($new_pending < 0)
        $new_pending = 0; // pending shouldn't be negative ideally, but if paid > allocated... logic handles it elsewhere

    // Update pending_amount ONLY. Do NOT touch allocated_amount.
    // We also need to update 'scholarship_amount' and 'additional_scholarship' columns in allocation table if they exist,
    // to keep them in sync for reporting. 
    // `tbl_student_fee_allocation` has `scholarship_amount` and `additional_scholarship`.
    // The `fee_allocation_helper` usually calculates these.
    // We should ideally call the sync helper, BUT the helper relies on us updating the source tables first (which we did).
    // So calling the helper is the SAFEST way to ensure all columns (pending, scholarship, etc) are correct.

    // Let's try to call the helper.
    require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/fee_allocation_helper.php';
    $sync_result = syncStudentFeeAllocation($conn, $reg_id);

    if (!$sync_result['success']) {
        throw new Exception("Failed to sync fee allocation: " . $sync_result['message']);
    }

    // 5. Audit Log (Log the change)
    // We can use the generic activity log or insert into tbl_post_admission_discounts as a record of change
    $dbOps->insert('tbl_post_admission_discounts', [
        'enrollment_id' => $enrollment_id,
        'student_id' => $reg_id,
        'discount_type' => 'modification',
        'discount_value' => $discount_diff, // The change amount
        'discount_amount' => $discount_diff,
        'previous_pending' => $student['fees_pending'],
        'new_pending' => $new_pending, // This might differ slightly if sync did something complex, but good enough for log
        'remarks' => "Released/Modified: Changed Initial Scholarship from $old_initial_total to $new_initial_scholarship, Post-Disc from $old_post_discount to $new_post_discount. Reason: $reason",
        'created_by' => $_SESSION['user_id']
    ]);

    $dbOps->commit();
    set_flash_message('success', 'Discounts updated successfully.');

} catch (Exception $e) {
    $dbOps->rollback();
    logError("Release Discount Save Error: " . $e->getMessage());
    set_flash_message('error', 'Failed to save changes: ' . $e->getMessage());
}

header('Location: post-admission-discount.php');
exit;
