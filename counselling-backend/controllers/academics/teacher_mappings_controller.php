<?php
/**
 * Teacher Mappings Controller
 * Handles mapping of teachers to subjects and divisions in the counselling system
 */

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';

require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;

$dbOps = new DatabaseOperations();

// Check if this is an API call
$is_api_call = defined('API_MODE') || (isset($_GET['route']) && !empty($_GET['route']));
$route = $_GET['route'] ?? '';

// Check general login authorization
if (!isset($_SESSION['user_id'])) {
    if ($is_api_call) {
        echo json_encode(['success' => false, 'message' => 'User not logged in']);
        exit;
    } else {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

// Access roles (Super Admin, Principal, Department Head can manage teacher mappings)
$authorized_roles = [ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_DEPT_HEAD];

if (!hasAnyRole($authorized_roles)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Insufficient permissions to access teacher mappings.']);
    exit;
}

// Handle Write Operations (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($route === 'academics/teacher-subject-save') {
            $teacher_id = intval($_POST['teacher_id'] ?? 0);
            $subject_ids = $_POST['subject_ids'] ?? [];

            if ($teacher_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid Teacher ID specified.']);
                exit;
            }

            if (!is_array($subject_ids)) {
                $subject_ids = empty($subject_ids) ? [] : [$subject_ids];
            }

            $conn->beginTransaction();

            // Clear old mappings
            $deleteStmt = $conn->prepare("DELETE FROM tbl_teacher_subject_mapping WHERE teacher_id = ?");
            $deleteStmt->execute([$teacher_id]);

            // Save new mappings
            if (!empty($subject_ids)) {
                $insertStmt = $conn->prepare("INSERT INTO tbl_teacher_subject_mapping (teacher_id, subject_id) VALUES (?, ?)");
                foreach ($subject_ids as $sub_id) {
                    $insertStmt->execute([$teacher_id, intval($sub_id)]);
                }
            }

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Teacher-Subject mapping updated successfully!']);
            exit;

        } elseif ($route === 'academics/teacher-division-save') {
            $teacher_id = intval($_POST['teacher_id'] ?? 0);
            $division_ids = $_POST['division_ids'] ?? [];

            if ($teacher_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid Teacher ID specified.']);
                exit;
            }

            if (!is_array($division_ids)) {
                $division_ids = empty($division_ids) ? [] : [$division_ids];
            }

            $conn->beginTransaction();

            // Clear old mappings
            $deleteStmt = $conn->prepare("DELETE FROM tbl_teacher_division_mapping WHERE teacher_id = ?");
            $deleteStmt->execute([$teacher_id]);

            // Save new mappings
            if (!empty($division_ids)) {
                $insertStmt = $conn->prepare("INSERT INTO tbl_teacher_division_mapping (teacher_id, division_id) VALUES (?, ?)");
                foreach ($division_ids as $div_id) {
                    $insertStmt->execute([$teacher_id, intval($div_id)]);
                }
            }

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Teacher-Division mapping updated successfully!']);
            exit;
        }
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

// Handle Read Operations (GET)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        if ($route === 'academics/teacher-mappings') {
            // 1. Fetch Teachers (Active users with teaching or coordination roles)
            $teacher_roles = [ROLE_TEACHER, ROLE_ASSISTANT_TEACHER, ROLE_COUNSELLOR, ROLE_DEPT_HEAD];
            $placeholders = str_repeat('?,', count($teacher_roles) - 1) . '?';
            
            $teachersStmt = $conn->prepare("
                SELECT u.id, u.name, u.email, r.role_name, s.designation, d.dept_name as department
                FROM tbl_users u
                LEFT JOIN tbl_roles r ON u.role_id = r.id
                LEFT JOIN tbl_staff s ON u.id = s.user_id
                LEFT JOIN tbl_departments d ON s.dept_id = d.id
                WHERE u.role_id IN ($placeholders) AND u.status = 'active'
                ORDER BY u.name ASC
            ");
            $teachersStmt->execute($teacher_roles);
            $teachers = $teachersStmt->fetchAll(PDO::FETCH_ASSOC);

            // 2. Fetch Subject Mappings
            $subMappingStmt = $conn->query("
                SELECT m.teacher_id, m.subject_id, s.subject_name
                FROM tbl_teacher_subject_mapping m
                JOIN tbl_subjects s ON m.subject_id = s.id
                WHERE s.activated = 1 AND s.is_deleted = 0
            ");
            $subject_mappings = $subMappingStmt->fetchAll(PDO::FETCH_ASSOC);

            // 3. Fetch Division Mappings
            $divMappingStmt = $conn->query("
                SELECT m.teacher_id, m.division_id, d.division_name
                FROM tbl_teacher_division_mapping m
                JOIN tbl_division d ON m.division_id = d.id
                WHERE d.is_active = 1
            ");
            $division_mappings = $divMappingStmt->fetchAll(PDO::FETCH_ASSOC);

            // 4. Fetch dropdown master parameters
            $subjects = $dbOps->select('tbl_subjects', ['id', 'subject_name'], ['activated' => 1, 'is_deleted' => 0], 'subject_name ASC') ?: [];
            $divisions = $dbOps->select('tbl_division', ['id', 'division_name'], ['is_active' => 1], 'display_order ASC') ?: [];

            // Structure mappings by teacher_id
            $subjects_by_teacher = [];
            foreach ($subject_mappings as $sm) {
                $subjects_by_teacher[$sm['teacher_id']][] = [
                    'id' => $sm['subject_id'],
                    'name' => $sm['subject_name']
                ];
            }

            $divisions_by_teacher = [];
            foreach ($division_mappings as $dm) {
                $divisions_by_teacher[$dm['teacher_id']][] = [
                    'id' => $dm['division_id'],
                    'name' => $dm['division_name']
                ];
            }

            // Append mappings directly to teacher records
            foreach ($teachers as &$t) {
                $t['subjects'] = $subjects_by_teacher[$t['id']] ?? [];
                $t['divisions'] = $divisions_by_teacher[$t['id']] ?? [];
            }
            unset($t);

            echo json_encode([
                'success' => true,
                'data' => [
                    'teachers' => $teachers,
                    'subjects' => $subjects,
                    'divisions' => $divisions
                ]
            ]);
            exit;
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}
