<?php
/**
 * Timetable Management Controller
 * Handles CRUD and overlapping conflict validations for class schedules
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

// Define write and read roles
$write_roles = [ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_ESTABLISHMENT, ROLE_DEPT_HEAD];
$read_roles = [ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_ESTABLISHMENT, ROLE_DEPT_HEAD, ROLE_TEACHER, ROLE_ASSISTANT_TEACHER, ROLE_STUDENT];

// Handle write operations (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hasAnyRole($write_roles)) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized: Insufficient permissions to modify timetables.']);
        exit;
    }

    try {
        if ($route === 'academics/timetable-save') {
            $id = intval($_POST['id'] ?? 0);
            $course_id = intval($_POST['course_id'] ?? 0);
            $group_id = intval($_POST['group_id'] ?? 0);
            $division_id = intval($_POST['division_id'] ?? 0);
            $subject_id = intval($_POST['subject_id'] ?? 0);
            $teacher_id = intval($_POST['teacher_id'] ?? 0);
            $day_of_week = trim($_POST['day_of_week'] ?? '');
            $start_time = trim($_POST['start_time'] ?? '');
            $end_time = trim($_POST['end_time'] ?? '');
            $room_no = trim($_POST['room_no'] ?? '');
            $academic_year = trim($_POST['academic_year'] ?? '');
            $is_active = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;
            $created_by = $_SESSION['user_id'] ?? null;

            // Form validations
            if ($course_id <= 0 || $group_id <= 0 || $division_id <= 0 || $subject_id <= 0 || $teacher_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'All academic details (Course, Group, Division, Subject, Teacher) are required.']);
                exit;
            }
            if (empty($day_of_week) || empty($start_time) || empty($end_time) || empty($academic_year)) {
                echo json_encode(['success' => false, 'message' => 'Day, Start Time, End Time, and Academic Year are required.']);
                exit;
            }

            // Standardize times for comparison (HH:MM:SS)
            $start_time = date('H:i:s', strtotime($start_time));
            $end_time = date('H:i:s', strtotime($end_time));

            if ($start_time >= $end_time) {
                echo json_encode(['success' => false, 'message' => 'Start Time must be strictly before End Time.']);
                exit;
            }

            // Overlap Validation Check:
            // Checks if a teacher, division, or classroom is already booked on the same day during the same time interval
            $conflictStmt = $conn->prepare("
                SELECT t.*, 
                       c.course_name, 
                       g.group_name, 
                       d.division_name,
                       s.subject_name,
                       u.name as teacher_name
                FROM tbl_timetable t
                LEFT JOIN tbl_courses c ON t.course_id = c.id
                LEFT JOIN tbl_group g ON t.group_id = g.id
                LEFT JOIN tbl_division d ON t.division_id = d.id
                LEFT JOIN tbl_subjects s ON t.subject_id = s.id
                LEFT JOIN tbl_users u ON t.teacher_id = u.id
                WHERE t.day_of_week = ? 
                  AND t.id != ?
                  AND t.academic_year = ?
                  AND t.is_active = 1
                  AND (t.start_time < ? AND t.end_time > ?)
                  AND (
                      (t.course_id = ? AND t.group_id = ? AND t.division_id = ?) 
                      OR t.teacher_id = ? 
                      OR (t.room_no = ? AND t.room_no IS NOT NULL AND t.room_no != '')
                  )
                LIMIT 1
            ");
            
            $conflictStmt->execute([
                $day_of_week, 
                $id, 
                $academic_year, 
                $end_time, 
                $start_time,
                $course_id, 
                $group_id, 
                $division_id, 
                $teacher_id, 
                $room_no
            ]);
            
            $conflict = $conflictStmt->fetch(PDO::FETCH_ASSOC);

            if ($conflict) {
                $conflict_start = date('g:i A', strtotime($conflict['start_time']));
                $conflict_end = date('g:i A', strtotime($conflict['end_time']));
                $msg = "Conflict Detected! ";

                if ($conflict['course_id'] == $course_id && $conflict['group_id'] == $group_id && $conflict['division_id'] == $division_id) {
                    $msg .= "Division **" . $conflict['division_name'] . "** already has a scheduled lecture: **" . $conflict['subject_name'] . "** with **" . $conflict['teacher_name'] . "** from " . $conflict_start . " to " . $conflict_end . ".";
                } elseif ($conflict['teacher_id'] == $teacher_id) {
                    $msg .= "Teacher **" . $conflict['teacher_name'] . "** is already teaching **" . $conflict['subject_name'] . "** in Division **" . $conflict['division_name'] . "** from " . $conflict_start . " to " . $conflict_end . ".";
                } elseif (!empty($room_no) && $conflict['room_no'] === $room_no) {
                    $msg .= "Classroom/Room **" . $room_no . "** is already occupied for **" . $conflict['subject_name'] . "** in Division **" . $conflict['division_name'] . "** from " . $conflict_start . " to " . $conflict_end . ".";
                } else {
                    $msg .= "Schedule overlaps with an existing entry at " . $conflict_start . " - " . $conflict_end . ".";
                }

                echo json_encode(['success' => false, 'message' => $msg]);
                exit;
            }

            // Save or Update
            if ($id > 0) {
                $stmt = $conn->prepare("
                    UPDATE tbl_timetable 
                    SET course_id = ?, group_id = ?, division_id = ?, subject_id = ?, teacher_id = ?, 
                        day_of_week = ?, start_time = ?, end_time = ?, room_no = ?, academic_year = ?, 
                        is_active = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([
                    $course_id, $group_id, $division_id, $subject_id, $teacher_id, 
                    $day_of_week, $start_time, $end_time, $room_no, $academic_year, 
                    $is_active, $id
                ]);
                $message = 'Timetable slot updated successfully!';
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO tbl_timetable 
                    (course_id, group_id, division_id, subject_id, teacher_id, day_of_week, start_time, end_time, room_no, academic_year, is_active, created_by, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $course_id, $group_id, $division_id, $subject_id, $teacher_id, 
                    $day_of_week, $start_time, $end_time, $room_no, $academic_year, 
                    $is_active, $created_by
                ]);
                $message = 'Timetable slot added successfully!';
            }

            echo json_encode(['success' => true, 'message' => $message]);
            exit;

        } elseif ($route === 'academics/timetable-delete') {
            $id = intval($_POST['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid ID']);
                exit;
            }

            $stmt = $conn->prepare("DELETE FROM tbl_timetable WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Timetable slot deleted successfully.']);
            exit;
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

// Handle read operations (GET)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!hasAnyRole($read_roles)) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
        exit;
    }

    try {
        // Fetch single record details
        if ($route === 'academics/timetable-get') {
            $id = intval($_GET['id'] ?? 0);
            $slot = $dbOps->selectOne('tbl_timetable', ['*'], ['id' => $id]);
            if ($slot) {
                echo json_encode(['success' => true, 'data' => ['slot' => $slot]]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Timetable slot not found']);
            }
            exit;
        }

        // Fetch List
        $division_id = intval($_GET['division_id'] ?? 0);
        $course_id = intval($_GET['course_id'] ?? 0);
        $group_id = intval($_GET['group_id'] ?? 0);
        $teacher_id = intval($_GET['teacher_id'] ?? 0);
        $student_id = intval($_GET['student_id'] ?? 0);
        $academic_year = trim($_GET['academic_year'] ?? '');

        // If accessed by a logged-in student, force their division
        if (hasRole(ROLE_STUDENT)) {
            // Find student's enrolled division
            $student_user_id = $_SESSION['user_id'];
            // Map tbl_users user_id to student_id or find it via email/phone
            // Typically in GCA, student session is linked to tbl_enrolled_students
            $enrolled = $conn->prepare("
                SELECT es.division_id, es.course_id, es.group_id, es.academic_year 
                FROM tbl_enrolled_students es
                JOIN tbl_gm_std_registration reg ON es.student_id = reg.id
                WHERE reg.email = ? OR reg.mob = ?
                LIMIT 1
            ");
            // Find user details
            $user_stmt = $conn->prepare("SELECT email, phone FROM tbl_users WHERE id = ?");
            $user_stmt->execute([$student_user_id]);
            $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                $enrolled->execute([$user['email'], $user['phone']]);
                $enroll_data = $enrolled->fetch(PDO::FETCH_ASSOC);
                if ($enroll_data) {
                    $division_id = $enroll_data['division_id'];
                    $course_id = $enroll_data['course_id'];
                    $group_id = $enroll_data['group_id'];
                    $academic_year = $enroll_data['academic_year'];
                }
            }
        }

        // If accessed by a logged-in teacher, allow them to see their own classes
        if (hasAnyRole([ROLE_TEACHER, ROLE_ASSISTANT_TEACHER]) && empty($division_id)) {
            $teacher_id = $_SESSION['user_id'];
        }

        // Build list query
        $sql = "SELECT t.*, 
                       c.course_name, 
                       g.group_name, 
                       d.division_name, 
                       s.subject_name,
                       u.name as teacher_name
                FROM tbl_timetable t
                LEFT JOIN tbl_courses c ON t.course_id = c.id
                LEFT JOIN tbl_group g ON t.group_id = g.id
                LEFT JOIN tbl_division d ON t.division_id = d.id
                LEFT JOIN tbl_subjects s ON t.subject_id = s.id
                LEFT JOIN tbl_users u ON t.teacher_id = u.id
                WHERE t.is_active = 1";
        
        $params = [];

        if ($division_id > 0) {
            $sql .= " AND t.division_id = ?";
            $params[] = $division_id;
        }
        if ($course_id > 0) {
            $sql .= " AND t.course_id = ?";
            $params[] = $course_id;
        }
        if ($group_id > 0) {
            $sql .= " AND t.group_id = ?";
            $params[] = $group_id;
        }
        if ($teacher_id > 0) {
            $sql .= " AND t.teacher_id = ?";
            $params[] = $teacher_id;
        }
        if (!empty($academic_year)) {
            $sql .= " AND t.academic_year = ?";
            $params[] = $academic_year;
        }

        $sql .= " ORDER BY CASE t.day_of_week
                    WHEN 'Monday' THEN 1
                    WHEN 'Tuesday' THEN 2
                    WHEN 'Wednesday' THEN 3
                    WHEN 'Thursday' THEN 4
                    WHEN 'Friday' THEN 5
                    WHEN 'Saturday' THEN 6
                    WHEN 'Sunday' THEN 7
                  END, t.start_time ASC";

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $slots = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format dates and durations for response
        foreach ($slots as &$slot) {
            $slot['formatted_start'] = date('g:i A', strtotime($slot['start_time']));
            $slot['formatted_end'] = date('g:i A', strtotime($slot['end_time']));
            $slot['time_range'] = $slot['formatted_start'] . ' - ' . $slot['formatted_end'];
        }
        unset($slot);

        // Also fetch dropdown parameters if requested for Admin view
        $dropdowns = [];
        if (hasAnyRole($write_roles) && empty($division_id) && empty($teacher_id)) {
            $dropdowns['courses'] = $dbOps->select('tbl_courses', ['id', 'course_name'], ['is_active' => 1], 'course_name ASC') ?: [];
            $dropdowns['groups'] = $dbOps->select('tbl_group', ['id', 'group_name'], ['is_active' => 1], 'group_name ASC') ?: [];
            $dropdowns['divisions'] = $dbOps->select('tbl_division', ['id', 'division_name'], ['is_active' => 1], 'display_order ASC') ?: [];
            $dropdowns['subjects'] = $dbOps->select('tbl_subjects', ['id', 'subject_name'], ['activated' => 1, 'is_deleted' => 0], 'subject_name ASC') ?: [];
            
            // Fetch users with Teacher/Faculty roles
            $teacher_role_ids = [ROLE_TEACHER, ROLE_ASSISTANT_TEACHER, ROLE_COUNSELLOR];
            $placeholders = str_repeat('?,', count($teacher_role_ids) - 1) . '?';
            $t_stmt = $conn->prepare("SELECT id, name FROM tbl_users WHERE role_id IN ($placeholders) AND status = 'active' ORDER BY name ASC");
            $t_stmt->execute($teacher_role_ids);
            $dropdowns['teachers'] = $t_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            
            // Fetch active academic years
            $dropdowns['academic_years'] = $dbOps->select('tbl_academic_years', ['id', 'academic_year', 'is_current'], ['status' => 'active'], 'academic_year DESC') ?: [];
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'timetable' => $slots,
                'dropdowns' => $dropdowns
            ]
        ]);
        exit;

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}
