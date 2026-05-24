<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';

/**
 * Courses Controller
 * Supports both API mode (JSON response) and direct inclusion mode
 */

$base_path = dirname(dirname(__DIR__));

require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
// Initialize Database Operations
$dbOps = new DatabaseOperations();

// Check if this is an API call (via index.php router)
$route = preg_replace('/[^a-zA-Z0-9\-_\/]/', '', $_GET['route'] ?? '');
$is_api_call = defined('API_MODE') || !empty($route);

if ($is_api_call) {
    header('Content-Type: application/json');
    if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_ESTABLISHMENT, ROLE_RECEPTION])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
        exit;
    }
} else {
    if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_ESTABLISHMENT, ROLE_RECEPTION])) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

$page_title = "Course Management";
$page_breadcrumb = "Courses";

// Handle API POST actions
if ($is_api_call && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($route === 'settings/course-save' || $route === 'settings/course-update') {
            $id = intval($_POST['id'] ?? 0);
            $course_name = trim($_POST['course_name'] ?? '');
            $board_id = intval($_POST['board_id'] ?? 0);
            $course_code = trim($_POST['course_code'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $standard = intval($_POST['standard'] ?? 0);
            $display_order = intval($_POST['display_order'] ?? 0);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $created_by = $_SESSION['user_id'] ?? 0;

            if (empty($course_name) || $board_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Course name and board are required']);
                exit;
            }

            // Check for duplicate course name within same board
            $stmt = $conn->prepare("SELECT id FROM tbl_courses WHERE course_name = ? AND board_id = ? AND id != ?");
            $stmt->execute([$course_name, $board_id, $id]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Course with this name already exists for this board']);
                exit;
            }

            if ($id > 0) {
                $stmt = $conn->prepare("UPDATE tbl_courses SET course_name = ?, board_id = ?, course_code = ?, description = ?, standard = ?, display_order = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$course_name, $board_id, $course_code, $description, $standard, $display_order, $is_active, $id]);
                $message = 'Course updated successfully';
            } else {
                $stmt = $conn->prepare("INSERT INTO tbl_courses (course_name, board_id, course_code, description, standard, display_order, is_active, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$course_name, $board_id, $course_code, $description, $standard, $display_order, $is_active, $created_by]);
                $message = 'Course added successfully';
            }
            echo json_encode(['success' => true, 'message' => $message]);
            exit;
        } elseif ($route === 'settings/course-delete') {
            $id = intval($_POST['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid course ID']);
                exit;
            }

            // Check dependencies (Fee Config and Students)
            $stmt = $conn->prepare("SELECT course_name FROM tbl_courses WHERE id = ?");
            $stmt->execute([$id]);
            $course = $stmt->fetch();

            if ($course) {
                $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM tbl_fee_config WHERE course_name = ?");
                $checkStmt->execute([$course['course_name']]);
                if ($checkStmt->fetch()['count'] > 0) {
                    echo json_encode(['success' => false, 'message' => 'Cannot delete course. It is used in fee configurations.']);
                    exit;
                }

                $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM tbl_enrolled_students WHERE course_id = ?");
                $checkStmt->execute([$id]);
                if ($checkStmt->fetch()['count'] > 0) {
                    echo json_encode(['success' => false, 'message' => 'Cannot delete course. It is linked to students.']);
                    exit;
                }
            }

            $stmt = $conn->prepare("DELETE FROM tbl_courses WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Course deleted successfully']);
            exit;
        } elseif ($route === 'settings/courses-delete-multiple') {
            $ids = isset($_POST['ids']) && is_array($_POST['ids']) ? array_map('intval', $_POST['ids']) : [];
            if (empty($ids) || !is_array($ids)) {
                echo json_encode(['success' => false, 'message' => 'No courses selected']);
                exit;
            }

            $ids = array_map('intval', $ids);
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';

            // Simplified check: if any of them are in student_info
            $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM tbl_enrolled_students WHERE course_id IN ($placeholders)");
            $checkStmt->execute($ids);
            if ($checkStmt->fetch()['count'] > 0) {
                echo json_encode(['success' => false, 'message' => 'Some selected courses are linked to students and cannot be deleted.']);
                exit;
            }

            $stmt = $conn->prepare("DELETE FROM tbl_courses WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            echo json_encode(['success' => true, 'message' => count($ids) . ' courses deleted successfully']);
            exit;
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

// Handle GET actions (List or Single)
if ($is_api_call && $route === 'settings/course-get') {
    $id = intval($_GET['id'] ?? 0);
    $stmt = $conn->prepare("SELECT * FROM tbl_courses WHERE id = ?");
    $stmt->execute([$id]);
    $course = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($course) {
        echo json_encode(['success' => true, 'data' => ['course' => $course]]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Course not found']);
    }
    exit;
}

// Fetch all courses (for listing or direct inclusion) - Using Operation.php
try {
    $courses = $dbOps->customSelect(
        "SELECT c.*, b.board_name, u.name as created_by_name 
         FROM tbl_courses c
         LEFT JOIN tbl_boards b ON c.board_id = b.id
         LEFT JOIN tbl_users u ON c.created_by = u.id
         ORDER BY b.board_name DESC, c.display_order desc, c.course_name DESC"
    );
    if ($courses === false)
        $courses = [];

    // Also fetch boards for the dropdowns
    $boards = $dbOps->select('tbl_boards', ['id', 'board_name'], ['is_active' => 1], 'board_name ASC');
    if ($boards === false)
        $boards = [];
} catch (PDOException $e) {
    $courses = [];
    $boards = [];
    if ($is_api_call) {
        sendErrorResponse('Database error: ' . $e->getMessage(), 500);
    }
}

// If API call, return JSON response
if ($is_api_call) {
    sendSuccessResponse([
        'courses' => $courses,
        'boards' => $boards
    ]);
}

