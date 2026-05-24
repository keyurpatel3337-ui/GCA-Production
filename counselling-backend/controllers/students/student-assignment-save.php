<?php
require_once __DIR__ . '/../../common/session_config.php';
require_once dirname(dirname(__DIR__)) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once OPERATION_FILE;

$dbOps = new DatabaseOperations();

// Check if user is Super Admin or Principle
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE)) {
    http_response_code(403);
    set_flash_message('error', "Access denied. You don't have permission to assign counsellors.");
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['action'] ?? '');

    if ($action == 'auto_assign') {
        $students_per_counsellor = isset($_POST['students_per_counsellor']) ? max(1, intval($_POST['students_per_counsellor'])) : 1;

        if ($students_per_counsellor < 1) {
            set_flash_message('error', "Students per counsellor must be at least 1!");
            header('Location: ' . BASE_URL . '/modules/students/student-assignment.php');
            exit;
        }

        try {
            $counsellors = $dbOps->select(
                'tbl_users',
                ['id', 'name'],
                ['role_id' => ROLE_COUNSELLOR, 'status' => 'active'],
                'name'
            );

            if (empty($counsellors)) {
                set_flash_message('error', "No active counsellors found!");
                header('Location: ' . BASE_URL . '/modules/students/student-assignment.php');
                exit;
            }

            $students = $dbOps->customSelect(
                "SELECT id FROM tbl_gm_std_registration WHERE counsellor_id IS NULL ORDER BY RAND()"
            );

            if (empty($students)) {
                set_flash_message('warning', "No unassigned students found!");
                header('Location: ' . BASE_URL . '/modules/students/student-assignment.php');
                exit;
            }

            $total_assigned = 0;
            $counsellor_index = 0;
            $counsellor_count = count($counsellors);
            $assignments = [];

            foreach ($counsellors as $counsellor) {
                $assignments[$counsellor['id']] = 0;
            }

            foreach ($students as $student) {
                $assigned = false;
                $attempts = 0;

                while (!$assigned && $attempts < $counsellor_count) {
                    $current_counsellor = $counsellors[$counsellor_index];

                    if ($assignments[$current_counsellor['id']] < $students_per_counsellor) {
                        $stmt = $conn->prepare("UPDATE tbl_gm_std_registration SET counsellor_id = ? WHERE id = ?");
                        $stmt->execute([$current_counsellor['id'], $student['id']]);

                        $assignments[$current_counsellor['id']]++;
                        $total_assigned++;
                        $assigned = true;
                    }

                    $counsellor_index = ($counsellor_index + 1) % $counsellor_count;
                    $attempts++;
                }

                if (!$assigned) {
                    break;
                }
            }

            $details = [];
            foreach ($counsellors as $counsellor) {
                if ($assignments[$counsellor['id']] > 0) {
                    $details[] = $counsellor['name'] . ": " . $assignments[$counsellor['id']] . " student(s)";
                }
            }

            set_flash_message('success', "Auto assignment completed! Total $total_assigned student(s) assigned. " . implode(', ', $details));
        } catch (PDOException $e) {
            set_flash_message('error', "Error during auto assignment: " . $e->getMessage());
        }

        header('Location: ' . BASE_URL . '/modules/students/student-assignment.php');
        exit;
    } elseif ($action == 'bulk_assign') {
        $counsellor_id = intval($_POST['counsellor_id'] ?? 0);
        $student_ids = isset($_POST['student_ids']) && is_array($_POST['student_ids']) ? array_map('intval', $_POST['student_ids']) : [];

        if (empty($student_ids)) {
            set_flash_message('error', "Please select at least one student to assign!");
            header('Location: ' . BASE_URL . '/modules/students/student-assignment.php');
            exit;
        }

        try {
            $placeholders = implode(',', array_fill(0, count($student_ids), '?'));
            $sql = "UPDATE tbl_gm_std_registration SET counsellor_id = ? WHERE id IN ($placeholders)";
            $params = array_merge([$counsellor_id], $student_ids);

            $stmt = $conn->prepare($sql);
            $stmt->execute($params);

            $count = intval($stmt->rowCount());
            set_flash_message('success', "$count student(s) assigned successfully!");
        } catch (PDOException $e) {
            set_flash_message('error', "Error: " . $e->getMessage());
        }

        header('Location: ' . BASE_URL . '/modules/students/student-assignment.php');
        exit;
    } elseif ($action == 'individual_assign') {
        $student_id = intval($_POST['student_id'] ?? 0);
        $counsellor_id = intval($_POST['counsellor_id'] ?? 0);

        try {
            $stmt = $conn->prepare("UPDATE tbl_gm_std_registration SET counsellor_id = ? WHERE id = ?");
            $stmt->execute([$counsellor_id, $student_id]);

            set_flash_message('success', "Student assigned successfully!");
        } catch (PDOException $e) {
            set_flash_message('error', "Error: " . $e->getMessage());
        }

        header('Location: ' . BASE_URL . '/modules/students/student-assignment.php');
        exit;
    } elseif ($action == 'mobile_bulk_assign') {
        header('Content-Type: application/json');

        $counsellor_id = intval($_POST['counsellor_id'] ?? 0);
        $student_ids = isset($_POST['student_ids']) && is_array($_POST['student_ids']) ? array_map('intval', $_POST['student_ids']) : [];

        if ($counsellor_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Please select a counsellor']);
            exit;
        }

        if (empty($student_ids)) {
            echo json_encode(['success' => false, 'message' => 'No students to assign']);
            exit;
        }

        try {
            $placeholders = implode(',', array_fill(0, count($student_ids), '?'));
            $sql = "UPDATE tbl_gm_std_registration SET counsellor_id = ? WHERE id IN ($placeholders)";
            $params = array_merge([$counsellor_id], $student_ids);

            $stmt = $conn->prepare($sql);
            $stmt->execute($params);

            $count = intval($stmt->rowCount());
            echo json_encode([
                'success' => true,
                'message' => "$count student(s) assigned successfully!",
                'count' => $count
            ]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')]);
        }
        exit;
    }
} elseif ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['action'])) {
    $action = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['action'] ?? '');

    if ($action == 'remove') {
        $student_id = intval($_GET['student_id'] ?? 0);

        try {
            $stmt = $conn->prepare("UPDATE tbl_gm_std_registration SET counsellor_id = NULL WHERE id = ?");
            $stmt->execute([$student_id]);

            set_flash_message('success', "Counsellor assignment removed successfully!");
        } catch (PDOException $e) {
            set_flash_message('error', "Error: " . $e->getMessage());
        }

        header('Location: ' . BASE_URL . '/modules/students/student-assignment.php');
        exit;
    }
} else {
    header('Location: ' . BASE_URL . '/modules/students/student-assignment.php');
    exit;
}
