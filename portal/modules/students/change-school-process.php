<?php
/**
 * Change School Process Controller
 * Handles single, mobile bulk, and checkbox bulk school changes
 */

require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once OPERATION_FILE;
require_once HELPER_ERROR_LOGGER;

// Check if user is Principle or Super Admin
if (!hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN)) {
    set_flash_message('error', "Access denied. You don't have permission to change student schools.");
    header('Location: change-school.php');
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: change-school.php');
    exit;
}

$change_type = $_POST['change_type'] ?? '';
$new_school_id = intval($_POST['new_school_id'] ?? 0);
$reason = trim($_POST['reason'] ?? '');
$user_id = $_SESSION['user_id'] ?? 0;

// Validate new school
if ($new_school_id <= 0) {
    set_flash_message('error', "Please select a valid school.");
    header('Location: change-school.php');
    exit;
}

// Verify new school exists
try {
    $stmt = $conn->prepare("SELECT id, school_name FROM tbl_schools WHERE id = ? AND is_active = 1");
    $stmt->execute([$new_school_id]);
    $new_school = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$new_school) {
        set_flash_message('error', "Selected school not found or inactive.");
        header('Location: change-school.php');
        exit;
    }
} catch (PDOException $e) {
    logError("Change School - School Validation Error: " . $e->getMessage(), __FILE__, __LINE__, $e);
    set_flash_message('error', "An error occurred while validating the school.");
    header('Location: change-school.php');
    exit;
}

$enrollment_ids = [];
$success_count = 0;
$fail_count = 0;

switch ($change_type) {
    case 'single':
        $student_id = intval($_POST['student_id'] ?? 0);
        if ($student_id > 0) {
            $enrollment_ids[] = $student_id;
        } else {
            set_flash_message('error', "Please select a student.");
            header('Location: change-school.php');
            exit;
        }
        break;

    case 'mobile_bulk':
    case 'checkbox_bulk':
        $enrollment_ids = $_POST['enrollment_ids'] ?? [];
        $enrollment_ids = array_filter(array_map('intval', $enrollment_ids), function ($id) {
            return $id > 0;
        });

        if (empty($enrollment_ids)) {
            set_flash_message('error', "No students selected for school change.");
            header('Location: change-school.php');
            exit;
        }
        break;

    default:
        set_flash_message('error', "Invalid change type.");
        header('Location: change-school.php');
        exit;
}

try {
    $conn->beginTransaction();

    // Update each student
    $updateStmt = $conn->prepare("
        UPDATE tbl_enrolled_students 
        SET school_id = ?,
            updated_at = NOW(),
            updated_by = ?
        WHERE enrollment_id = ? AND is_active = 1
    ");

    // Also update registration table
    $updateRegStmt = $conn->prepare("
        UPDATE tbl_gm_std_registration 
        SET school_id = ?,
            updated_at = NOW()
        WHERE id = (SELECT registration_id FROM tbl_enrolled_students WHERE enrollment_id = ?)
    ");

    // Log the change
    $logStmt = $conn->prepare("
        INSERT INTO tbl_audit_logs 
        (user_id, action, module, details, ip_address, created_at)
        VALUES (?, 'school_change', 'students', ?, ?, NOW())
    ");

    foreach ($enrollment_ids as $enrollment_id) {
        try {
            // Get current school info for logging
            $currentStmt = $conn->prepare("
                SELECT e.enrollment_no, r.school_id, s.school_name as old_school_name,
                       CONCAT(r.surname, ' ', r.student_name) as student_name
                FROM tbl_enrolled_students e
                LEFT JOIN tbl_schools s ON r.school_id = s.id
                INNER JOIN tbl_gm_std_registration r ON e.registration_id = r.id
                WHERE e.enrollment_id = ?
            ");
            $currentStmt->execute([$enrollment_id]);
            $current = $currentStmt->fetch(PDO::FETCH_ASSOC);

            if (!$current) {
                $fail_count++;
                continue;
            }

            // Skip if already in the same school
            if ($current['school_id'] == $new_school_id) {
                $fail_count++;
                continue;
            }

            // Update enrolled students
            $updateStmt->execute([$new_school_id, $user_id, $enrollment_id]);

            // Update registration table
            $updateRegStmt->execute([$new_school_id, $enrollment_id]);

            // Log the change
            $logDetails = json_encode([
                'enrollment_no' => $current['enrollment_no'],
                'student_name' => $current['student_name'],
                'old_school_id' => $current['school_id'],
                'old_school_name' => $current['old_school_name'] ?? 'Not Assigned',
                'new_school_id' => $new_school_id,
                'new_school_name' => $new_school['school_name'],
                'reason' => $reason,
                'change_type' => $change_type
            ]);
            $logStmt->execute([
                $user_id,
                $logDetails,
                $_SERVER['REMOTE_ADDR'] ?? null
            ]);

            $success_count++;
        } catch (PDOException $e) {
            logError("Change School - Update Error for enrollment $enrollment_id: " . $e->getMessage(), __FILE__, __LINE__, $e);
            $fail_count++;
        }
    }

    $conn->commit();

    // Set success/error message
    if ($success_count > 0) {
        $msg = "$success_count student(s) successfully changed to " . htmlspecialchars($new_school['school_name'] ?? '') . ".";
        if ($fail_count > 0) {
            $msg .= " $fail_count student(s) could not be updated.";
        }
        set_flash_message('success', $msg);
    } else {
        set_flash_message('error', "No students were updated. They may already be in the selected school.");
    }

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    logError("Change School - Transaction Error: " . $e->getMessage(), __FILE__, __LINE__, $e);
    set_flash_message('error', "An error occurred while changing schools: " . $e->getMessage());
}

header('Location: change-school.php');
exit;
