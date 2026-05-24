<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';
header('Content-Type: application/json');

$base_path = dirname(dirname(__DIR__));

require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once $base_path . '/../common/helpers/error_logger.php';
// Log the request for debugging
error_log("Division Assignment Bulk Save - POST data: " . print_r($_POST, true));

// Get database connection
global $conn;

$dbOps = new DatabaseOperations();

// Check if user is Super Admin or Principal
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE)) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

try {
    $course_division_id = intval($_POST['course_division_id'] ?? 0);
    $student_ids = isset($_POST['student_ids']) && is_array($_POST['student_ids']) ? array_map('intval', $_POST['student_ids']) : [];

    if ($course_division_id <= 0 || empty($student_ids)) {
        echo json_encode(['success' => false, 'error' => 'Please select a division and at least one student']);
        exit;
    }

    $conn->beginTransaction();

    // Get course-division details
    $course_division = $dbOps->selectOne('tbl_course_division', ['*'], ['id' => $course_division_id, 'is_active' => 1]);

    if (!$course_division) {
        throw new Exception("Invalid course-division mapping.");
    }

    $division_id = $course_division['division_id'];
    $current_roll_no = $course_division['current_roll_no'];
    $max_capacity = $course_division['max_capacity'];
    $total_students = $course_division['total_students'];

    if ($max_capacity && ($total_students + count($student_ids)) > $max_capacity) {
        $conn->rollBack();
        echo json_encode([
            'success' => false,
            'error' => "Assignment would exceed maximum capacity of {$max_capacity}. Current: {$total_students}, Trying to add: " . count($student_ids)
        ]);
        exit;
    }

    $assigned_count = 0;
    $failed_assignments = [];

    foreach ($student_ids as $enrollment_id) {
        try {
            $student = $dbOps->customSelectOne(
                "SELECT e.*, s.student_name, s.surname, s.course_id, s.group_id 
                FROM tbl_enrolled_students e
                LEFT JOIN tbl_gm_std_registration s ON e.registration_id = s.id
                WHERE e.enrollment_id = ?",
                [$enrollment_id]
            );

            if (!$student) {
                $failed_assignments[] = "Enrollment ID {$enrollment_id} not found";
                continue;
            }

            $student_fullname = htmlspecialchars(($student['surname'] ?? '') . ' ' . ($student['student_name'] ?? ''), ENT_QUOTES, 'UTF-8');

            // Check for duplicate registration_id in same division
            $duplicate_check = $dbOps->customSelectOne(
                "SELECT enrollment_id FROM tbl_enrolled_students 
                WHERE registration_id = ? AND division_id = ? AND enrollment_id != ?",
                [$student['registration_id'], $division_id, $enrollment_id]
            );

            if ($duplicate_check) {
                $failed_assignments[] = "{$student_fullname} (Reg ID: " . intval($student['registration_id']) . ") - Duplicate enrollment exists in this division";
                continue;
            }

            if ($student['course_id'] != $course_division['course_id'] || $student['group_id'] != $course_division['group_id']) {
                $failed_assignments[] = "{$student_fullname} - Course/Group mismatch";
                continue;
            }

            // Check if student is already assigned to this division
            if ($student['division_id'] == $division_id) {
                $failed_assignments[] = "{$student_fullname} - Already assigned to this division";
                continue;
            }

            // Check if student already has a division assigned (prevent reassignment without explicit action)
            if ($student['division_id'] && $student['roll_no']) {
                $failed_assignments[] = "{$student_fullname} - Already assigned to Division " . intval($student['division_id']) . " with Roll No " . intval($student['roll_no']);
                continue;
            }

            $current_roll_no++;
            $new_roll_no = $current_roll_no;

            $stmt_update = $conn->prepare("UPDATE tbl_enrolled_students 
                                          SET division_id = ?, roll_no = ?, updated_at = NOW()
                                          WHERE enrollment_id = ?");
            $stmt_update->execute([$division_id, $new_roll_no, $enrollment_id]);

            $assigned_count++;
        } catch (Exception $e) {
            $failed_assignments[] = "Student ID {$enrollment_id}: " . $e->getMessage();
            logError($e, "Bulk Division Assignment - Student {$enrollment_id}");
        }
    }

    $stmt_update_cd = $conn->prepare("UPDATE tbl_course_division 
                                     SET current_roll_no = ?, total_students = total_students + ?, updated_at = NOW()
                                     WHERE id = ?");
    $stmt_update_cd->execute([$current_roll_no, $assigned_count, $course_division_id]);

    $conn->commit();

    $response = ['success' => true];

    if ($assigned_count > 0) {
        $response['message'] = "Successfully assigned {$assigned_count} student(s) to division.";
    }

    if (!empty($failed_assignments)) {
        $response['warnings'] = $failed_assignments;
        $response['message'] = ($response['message'] ?? '') . " Some assignments failed: " . implode(", ", $failed_assignments);
    }

    echo json_encode($response);
    exit;
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    logError($e, "Bulk Division Assignment");
    echo json_encode(['success' => false, 'error' => "Assignment failed: " . $e->getMessage()]);
    exit;
}
