<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';

/**
 * Student Portal My Results Controller
 * Supports both API mode (JSON response) and direct inclusion mode
 */

$base_path = dirname(dirname(__DIR__));

require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;

// Initialize database operations
$dbOps = new DatabaseOperations();

// Check if this is an API call (via index.php router)
$is_api_call = defined('API_MODE') || (isset($_GET['route']) && !empty($_GET['route']));

if (!$is_api_call) {
    require_once OPERATION_FILE;
    require_once $base_path . '/../common/helpers/error_logger.php';
    // Check if user is Student OR Parent OR Admin
    $is_student = isset($_SESSION['is_student_login']) && $_SESSION['is_student_login'] === true;
    $is_parent = isset($_SESSION['is_parent_login']) && $_SESSION['is_parent_login'] === true;
    $is_admin = hasRole(ROLE_SUPER_ADMIN) || hasRole(ROLE_PRINCIPLE) || hasRole(ROLE_COUNSELLOR) || hasRole(ROLE_ACCOUNTANT);

    if (!$is_student && !$is_admin && !$is_parent) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

$page_title = "My Results";
$page_breadcrumb = "Results";

// For API, student_id can come from query param
// If admin, prioritize GET. If student, force SESSION.
$is_admin_api = isset($_SESSION['user_id']) && (hasRole(ROLE_SUPER_ADMIN) || hasRole(ROLE_PRINCIPLE) || hasRole(ROLE_COUNSELLOR));
if ($is_admin_api && isset($_GET['student_id'])) {
    $student_id = $_GET['student_id'];
} else {
    // If parent, prioritize SESSION active_student_id or student_id
    $student_id = $_SESSION['student_id'] ?? $_SESSION['active_student_id'] ?? $_GET['student_id'] ?? null;
}

if (!$student_id && $is_api_call) {
    sendErrorResponse('Student ID is required', 400);
}

// Handle Actions
$action = $_GET['action'] ?? 'get_initial_data';

if ($is_api_call) {
    switch ($action) {
        case 'get_marks':
            // Existing logic to get specific exam marks
            $exam_type = $_GET['exam_type'] ?? '';
            $academic_year_id = $_GET['academic_year_id'] ?? 0;
            getStudentAcademicMarks($dbOps, $student_id, $exam_type, $academic_year_id);
            break;

        case 'get_exam_list':
            // New logic to get list of exams taken
            getStudentExamList($dbOps, $student_id);
            break;

        case 'get_initial_data':
        default:
            getInitialData($dbOps);
            break;
    }
} else {
    // Direct include - just prepare $academic_years
    $academic_years = fetchAcademicYears($dbOps);
}

function fetchAcademicYears($dbOps)
{
    try {
        $sql = "SELECT * FROM tbl_academic_years ORDER BY start_date DESC";
        return $dbOps->customSelect($sql);
    } catch (Exception $e) {
        return [];
    }
}

function getInitialData($dbOps)
{
    $years = fetchAcademicYears($dbOps);
    sendSuccessResponse(['academic_years' => $years]);
}

function getStudentAcademicMarks($dbOps, $student_id, $exam_type, $academic_year_id)
{
    try {
        $sql = "SELECT m.*, s.subject_name 
                FROM tbl_student_exam_marks m
                JOIN tbl_subjects s ON m.subject_id = s.id
                WHERE m.student_id = ? AND m.exam_type = ?
                ORDER BY s.id";

        $params = [$student_id, $exam_type];

        if ($academic_year_id) {
            $sql = "SELECT m.*, s.subject_name 
                    FROM tbl_student_exam_marks m
                    JOIN tbl_subjects s ON m.subject_id = s.id
                    WHERE m.student_id = ? AND m.exam_type = ? AND m.academic_year_id = ?
                    ORDER BY s.id";
            $params[] = $academic_year_id;
        }

        $marks = $dbOps->customSelect($sql, $params);
        sendSuccessResponse($marks);

    } catch (Exception $e) {
        sendErrorResponse('Database error: ' . $e->getMessage());
    }
}

/**
 * Get list of exams taken by the student (For List View)
 */
function getStudentExamList($dbOps, $student_id)
{
    try {
        // Group by Academic Year and Exam Type
        $sql = "SELECT DISTINCT m.academic_year_id, m.exam_type, ay.year_name, m.course_id, ay.start_date
                FROM tbl_student_exam_marks m
                JOIN tbl_academic_years ay ON m.academic_year_id = ay.id
                WHERE m.student_id = ?
                ORDER BY ay.start_date DESC, m.exam_type desc";

        $exams = $dbOps->customSelect($sql, [$student_id]);
        sendSuccessResponse($exams);
    } catch (Exception $e) {
        sendErrorResponse('Database error: ' . $e->getMessage());
    }
}

/**
 * Helper to get consolidated marks for PDF generation (called internally)
 */
function getConsolidatedMarksForPDF($dbOps, $student_id, $academic_year_id)
{
    try {
        // Fetch all marks: theory, practical, internal columns
        $sql = "SELECT m.*, s.subject_name 
                FROM tbl_student_exam_marks m
                JOIN tbl_subjects s ON m.subject_id = s.id
                WHERE m.student_id = ? AND m.academic_year_id = ?
                ORDER BY s.id";

        $raw_marks = $dbOps->customSelect($sql, [$student_id, $academic_year_id]);
        $temp = [];

        foreach ($raw_marks as $m) {
            $sid = $m['subject_id'];
            if (!isset($temp[$sid])) {
                $temp[$sid] = [
                    'subject_name' => $m['subject_name'],
                    'first' => 0.0,
                    'second' => 0.0,
                    'annual' => 0.0,
                    'internal' => 0.0,
                    'practical_total' => 0.0,
                    'grace' => 0.0
                ];
            }

            $eType = strtolower(trim($m['exam_type']));

            // Extract components
            $theoryVal = (float) ($m['theory_marks'] ?? 0);
            $internalVal = (float) ($m['internal_marks'] ?? 0);
            $practicalVal = (float) ($m['practical_marks'] ?? 0);

            // Map to Exam Type Categories
            if (strpos($eType, 'first') !== false) {
                $temp[$sid]['first'] += $theoryVal;
            } elseif (strpos($eType, 'second') !== false) {
                $temp[$sid]['second'] += $theoryVal;
            } elseif (strpos($eType, 'annual') !== false) {
                $temp[$sid]['annual'] += $theoryVal;
            } elseif (strpos($eType, 'internal') !== false) {
                $temp[$sid]['internal'] += $internalVal;
            }

            // Accumulate Practical (from any exam type that has it)
            $temp[$sid]['practical_total'] += $practicalVal;
        }

        $final_list = [];
        foreach ($temp as $sid => $t) {
            // 1. Theory Row
            $final_list[] = [
                'subject_id' => $sid, // Original ID implies Theory
                'subject_name' => $t['subject_name'],
                'subject_type' => 'Theory',
                'first_exam' => $t['first'],
                'second_exam' => $t['second'],
                'annual_exam' => $t['annual'],
                'internal_mark' => $t['internal'],
                'grace_marks' => $t['grace'],
                'subject_grade' => '-' // Placeholder
            ];

            // 2. Practical Row (Only if positive marks exist)
            if ($t['practical_total'] > 0) {
                $final_list[] = [
                    'subject_id' => $sid . '_prac', // Distinct ID to treat as separate row
                    'subject_name' => $t['subject_name'],
                    'subject_type' => 'Practical',
                    'obtained_marks' => $t['practical_total'],
                    'grace_marks' => 0,
                    'subject_grade' => '-'
                ];
            }
        }

        return $final_list;

    } catch (Exception $e) {
        return [];
    }
}


