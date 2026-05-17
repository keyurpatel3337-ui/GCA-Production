<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';
require_once HELPERS_PATH . 'fee_helper.php';

/**
 * Student Dashboard Controller
 * Supports both API mode (JSON response) and direct inclusion mode
 */

$base_path = dirname(dirname(__DIR__));

require_once DB_CONNECT_FILE;

// Check if this is an API call (via index.php router)
$is_api_call = defined('API_MODE') || (isset($_GET['route']) && !empty($_GET['route']));

if (!$is_api_call) {
    require_once OPERATION_FILE;
    require_once $base_path . '/../common/helpers/error_logger.php';
    // Check if user is Student (student-specific login only)
    if (!isset($_SESSION['is_student_login']) || $_SESSION['is_student_login'] !== true) {
        header('Location: ../student-portal/student-login.php');
        exit;
    }

    if (!isset($_SESSION['student_id'])) {
        header('Location: ../student-portal/student-login.php');
        exit;
    }
}

// For API calls, student_id might come from query param
$user_id = $_SESSION['student_id'] ?? $_GET['student_id'] ?? null;
$user_name = $_SESSION['student_name'] ?? $_SESSION['user_name'] ?? 'Student';
$isStudentLogin = true;

if (!$user_id && $is_api_call) {
    sendErrorResponse('Student ID is required', 400);
}

// Get student details including token payment status
$student_info = null;
try {
    $stmt = $conn->prepare("SELECT 
                                r.id,
                                CASE WHEN p.id IS NOT NULL OR r.token_fees_paid = 1 THEN 1 ELSE 0 END as token_fees_paid,
                                p.payment_date as token_payment_date,
                                p.transaction_id as token_transaction_id,
                                p.amount as token_amount
                            FROM tbl_gm_std_registration r 
                            LEFT JOIN tbl_payments p ON r.id = p.student_id 
                                AND (p.payment_type = 'token_fee' OR p.fee_component = 'tuition_fee_part1') 
                                AND p.status = 'paid'
                            WHERE r.id = ?");
    $stmt->execute([$user_id]);
    $student_info = $stmt->fetch();

    if (!$student_info) {
        if ($is_api_call) {
            sendErrorResponse('Student not found', 404);
        }
        // If registration info not found, something is wrong with the session or data
        session_destroy();
        header('Location: ../student-portal/student-login.php?error=invalid_session');
        exit;
    }
} catch (PDOException $e) {
    if ($is_api_call) {
        sendErrorResponse('Database error: ' . $e->getMessage(), 500);
    }
}

// Get fee allocations to check if fees are assigned (Apply absorption logic for student dashboard view)
$fee_summary = calculateStudentFeeSummary($conn, $user_id, true);
$fee_allocated = ($fee_summary && $fee_summary['total_allocated'] > 0);

// Get enrollment info including division and roll number
$enrollment_info = null;
try {
    $stmt = $conn->prepare("SELECT e.*, 
                           c.course_name, 
                           g.group_name, 
                           d.division_name,
                           m.medium_name,
                           b.board_name
                           FROM tbl_enrolled_students e
                           LEFT JOIN tbl_gm_std_registration r ON e.registration_id = r.id
                           LEFT JOIN tbl_courses c ON r.course_id = c.id
                           LEFT JOIN tbl_group g ON r.group_id = g.id
                           LEFT JOIN tbl_division d ON e.division_id = d.id
                           LEFT JOIN tbl_medium m ON r.medium_id = m.id
                           LEFT JOIN tbl_boards b ON r.board_id = b.id
                           WHERE e.registration_id = ? AND e.is_active = 1");
    $stmt->execute([$user_id]);
    $enrollment_info = $stmt->fetch();
} catch (PDOException $e) {
    if (!$is_api_call && function_exists('logDatabaseError')) {
        logDatabaseError($e, "Fetch Student Enrollment Info");
    }
}

// Get statistics
$stats = [];
$recent_results = [];
try {
    // Count pending appointments
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM tbl_appointments WHERE student_id = ? AND status = 'pending'");
    $stmt->execute([$user_id]);
    $stats['pending_appointments'] = $stmt->fetch()['total'] ?? 0;

    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM tbl_appointments WHERE student_id = ? AND status = 'completed'");
    $stmt->execute([$user_id]);
    $stats['completed_appointments'] = $stmt->fetch()['total'] ?? 0;

    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM tbl_test_results WHERE student_id = ?");
    $stmt->execute([$user_id]);
    $stats['total_tests'] = $stmt->fetch()['total'];

    $stmt = $conn->prepare("SELECT AVG(percentage) as avg_score FROM tbl_test_results WHERE student_id = ?");
    $stmt->execute([$user_id]);
    $stats['avg_score'] = $stmt->fetch()['avg_score'] ?? 0;

    // Get recent results
    $stmt = $conn->prepare("SELECT tr.*, ak.test_name, ak.test_date 
                           FROM tbl_test_results tr 
                           INNER JOIN tbl_answer_keys ak ON tr.answer_key_id = ak.id 
                           WHERE tr.student_id = ? 
                           ORDER BY tr.created_at ASC LIMIT 5");
    $stmt->execute([$user_id]);
    $recent_results = $stmt->fetchAll();
} catch (PDOException $e) {
    if (!$is_api_call && function_exists('logDatabaseError')) {
        logDatabaseError($e, "Fetch Student Dashboard Stats");
    }
}

// If API call, return JSON response
if ($is_api_call) {
    sendSuccessResponse([
        'student_info' => $student_info,
        'enrollment_info' => $enrollment_info,
        'fee_allocated' => $fee_allocated,
        'stats' => $stats,
        'recent_results' => $recent_results,
        'fee_summary' => $fee_summary
    ]);
}

// For direct inclusion
$pending_appointments = $stats['pending_appointments'] ?? 0;
$completed_appointments = $stats['completed_appointments'] ?? 0;
$total_tests = $stats['total_tests'] ?? 0;
$avg_score = $stats['avg_score'] ?? 0;

$page_title = "Student Dashboard";
$page_breadcrumb = "Dashboard";


