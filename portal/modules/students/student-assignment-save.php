<?php

/**
 * Student Assignment Save Handler
 * Handles all student-to-counsellor assignment operations
 */
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;

// Check if user is Super Admin or Principle
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE)) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }
    set_flash_message('error', "Access denied. You don't have permission.");
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$action = $_POST['action'] ?? $_POST['action'] ?? '';
$redirect_url = 'student-assignment.php';

// Handle AJAX requests
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';
if ($is_ajax) {
    header('Content-Type: application/json');
}

try {
    switch ($action) {
        case 'auto_assign':
            // Auto assign students to counsellors
            $students_per_counsellor = intval($_POST['students_per_counsellor'] ?? 10);

            // Get all active counsellors
            $counsellors = $dbOps->select('tbl_users', ['id'], ['role_id' => ROLE_COUNSELLOR, 'status' => 'active']);
            $counsellor_ids = array_column($counsellors, 'id');

            if (empty($counsellor_ids)) {
                throw new Exception('No active counsellors found');
            }

            // Get unassigned students
            $sql = "SELECT id FROM tbl_gm_std_registration WHERE counsellor_id IS NULL AND status = 1 ORDER BY id ASC";
            $students = $dbOps->customSelect($sql);
            $student_ids = array_column($students, 'id');

            if (empty($student_ids)) {
                set_flash_message('success', 'No unassigned students found');
                header('Location: ' . $redirect_url);
                exit;
            }

            // Distribute students to counsellors
            $counsellor_index = 0;
            $counts = array_fill_keys($counsellor_ids, 0);
            $assigned = 0;

            foreach ($student_ids as $student_id) {
                // Find counsellor with least assignments (up to limit)
                $current_counsellor = $counsellor_ids[$counsellor_index];

                if ($counts[$current_counsellor] < $students_per_counsellor) {
                    $dbOps->update(
                        'tbl_gm_std_registration',
                        ['counsellor_id' => $current_counsellor],
                        ['id' => $student_id]
                    );
                    $counts[$current_counsellor]++;
                    $assigned++;
                }

                // Move to next counsellor
                $counsellor_index = ($counsellor_index + 1) % count($counsellor_ids);

                // Check if all counsellors are at limit
                $all_full = true;
                foreach ($counts as $count) {
                    if ($count < $students_per_counsellor) {
                        $all_full = false;
                        break;
                    }
                }
                if ($all_full)
                    break;
            }

            set_flash_message('success', "$assigned student(s) have been assigned to counsellors successfully!");
            break;

        case 'bulk_assign':
            // Bulk assign selected students to a counsellor
            $counsellor_id = intval($_POST['counsellor_id'] ?? 0);
            $student_ids = $_POST['student_ids'] ?? [];

            if ($counsellor_id <= 0) {
                throw new Exception('Please select a counsellor');
            }

            if (empty($student_ids)) {
                throw new Exception('Please select at least one student');
            }

            $op = new Operation();

            // Validate counsellor exists
            $counsellor = $op->selectOne('tbl_users', ['*'], ['id' => $counsellor_id, 'role_id' => ROLE_COUNSELLOR]);
            if (!$counsellor) {
                throw new Exception('Invalid counsellor selected');
            }

            // Assign students
            foreach ($student_ids as $sid) {
                $op->update('tbl_gm_std_registration', [
                    'counsellor_id' => $counsellor_id
                ], ['id' => $sid]);
            }

            $count = count($student_ids);
            set_flash_message('success', "$count student(s) assigned to counsellor successfully!");
            break;

        case 'individual_assign':
            // Assign single student to counsellor
            $counsellor_id = intval($_POST['counsellor_id'] ?? 0);
            $student_id = intval($_POST['student_id'] ?? 0);

            if ($counsellor_id <= 0 || $student_id <= 0) {
                throw new Exception('Invalid student or counsellor');
            }

            $op = new Operation();
            $op->update('tbl_gm_std_registration', [
                'counsellor_id' => $counsellor_id
            ], ['id' => $student_id]);

            set_flash_message('success', "Student assigned to counsellor successfully!");
            break;

        case 'mobile_bulk_assign':
            // Bulk assign by mobile numbers (AJAX)
            $counsellor_id = intval($_POST['counsellor_id'] ?? 0);
            $student_ids = $_POST['student_ids'] ?? [];

            if ($counsellor_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Please select a counsellor']);
                exit;
            }

            if (empty($student_ids)) {
                echo json_encode(['success' => false, 'message' => 'No students to assign']);
                exit;
            }

            // Assign students
            $op = new Operation();
            $count = 0;
            foreach ($student_ids as $sid) {
                $student_check = $op->selectOne('tbl_gm_std_registration', ['*'], ['id' => $sid, 'counsellor_id' => null]);
                if ($student_check) {
                    $op->update('tbl_gm_std_registration', [
                        'counsellor_id' => $counsellor_id
                    ], ['id' => $sid]);
                    $count++;
                }
            }

            echo json_encode(['success' => true, 'message' => "$count student(s) assigned successfully!"]);
            exit;

        case 'remove':
            // Remove counsellor assignment
            $student_id = intval($_POST['student_id'] ?? 0);

            if ($student_id <= 0) {
                throw new Exception('Invalid student ID');
            }

            $op = new Operation();
            $op->update('tbl_gm_std_registration', [
                'counsellor_id' => null
            ], ['id' => $student_id]);

            set_flash_message('success', "Counsellor assignment removed successfully!");
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    logAppError('Student Assignment', $e->getMessage());

    if ($is_ajax) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }

    set_flash_message('error', $e->getMessage());
}

header('Location: ' . $redirect_url);
exit;

