<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';

/**
 * Reports Controller
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
    // Check if user is Super Admin, Principle, or Counsellor
    if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_COUNSELLOR)) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

$page_title = "Reports & Analytics";
$page_breadcrumb = "Reports";

// Determine if counsellor for filtering
$is_counsellor = !$is_api_call && function_exists('hasRole') && hasRole(ROLE_COUNSELLOR);
$counsellor_id = $_GET['counsellor_id'] ?? $_SESSION['user_id'] ?? null;

// Get statistics
try {
    $stats = [];

    // If counsellor, count only their assigned students
    if ($is_counsellor && $counsellor_id) {
        $stats['students'] = $dbOps->count('tbl_gm_std_registration', ['counsellor_id' => $counsellor_id]);
    } else {
        $stats['students'] = $dbOps->count('tbl_gm_std_registration', ['status' => 1]);
    }

    $stats['paper_sets'] = $dbOps->count('tbl_paper_sets', ['status' => 'active']);

    // If counsellor, count only OMR sheets for their students
    if ($is_counsellor && $counsellor_id) {
        $sql_omr_total = "SELECT COUNT(*) as count FROM tbl_omr_sheets 
                           WHERE student_id IN (SELECT id FROM tbl_gm_std_registration WHERE counsellor_id = ?)";
        $omr_total_result = $dbOps->customSelect($sql_omr_total, [$counsellor_id]);
        $stats['omr_total'] = $omr_total_result[0]['count'] ?? 0;

        $sql_omr_checked = "SELECT COUNT(*) as count FROM tbl_omr_sheets 
                           WHERE status = 'checked' 
                           AND student_id IN (SELECT id FROM tbl_gm_std_registration WHERE counsellor_id = ?)";
        $omr_checked_result = $dbOps->customSelect($sql_omr_checked, [$counsellor_id]);
        $stats['omr_checked'] = $omr_checked_result[0]['count'] ?? 0;

        $sql_results = "SELECT COUNT(*) as count FROM tbl_test_results 
                       WHERE student_id IN (SELECT id FROM tbl_gm_std_registration WHERE counsellor_id = ?)";
        $results_result = $dbOps->customSelect($sql_results, [$counsellor_id]);
        $stats['results'] = $results_result[0]['count'] ?? 0;

        // Average score
        $sql_avg = "SELECT AVG(percentage) as avg_score FROM tbl_test_results 
                   WHERE student_id IN (SELECT id FROM tbl_gm_std_registration WHERE counsellor_id = ?)";
        $avg_result = $dbOps->customSelect($sql_avg, [$counsellor_id]);
        $stats['avg_score'] = ($avg_score = $avg_result[0]['avg_score'] ?? null) !== null ? round($avg_score, 2) : 0;

        // Top performers
        $sql_top = "SELECT tr.*, os.student_name, os.roll_number 
                   FROM tbl_test_results tr
                   LEFT JOIN tbl_omr_sheets os ON tr.omr_sheet_id = os.id
                   WHERE tr.student_id IN (SELECT id FROM tbl_gm_std_registration WHERE counsellor_id = ?)
                   ORDER BY tr.percentage DESC LIMIT 10";
        $top_performers = $dbOps->customSelect($sql_top, [$counsellor_id]);
    } else {
        $stats['omr_total'] = $dbOps->count('tbl_omr_sheets');
        $stats['omr_checked'] = $dbOps->count('tbl_omr_sheets', ['status' => 'checked']);
        $stats['results'] = $dbOps->count('tbl_test_results');

        // Average score
        $sql_avg = "SELECT AVG(percentage) as avg_score FROM tbl_test_results";
        $avg_result = $dbOps->customSelect($sql_avg);
        $stats['avg_score'] = ($avg_score = $avg_result[0]['avg_score'] ?? null) !== null ? round($avg_score, 2) : 0;

        // Top performers
        $sql_top = "SELECT tr.*, os.student_name, os.roll_number 
                   FROM tbl_test_results tr
                   LEFT JOIN tbl_omr_sheets os ON tr.omr_sheet_id = os.id
                   ORDER BY tr.percentage DESC LIMIT 10";
        $top_performers = $dbOps->customSelect($sql_top);
    }
} catch (PDOException $e) {
    if (!$is_api_call && function_exists('logDatabaseError')) {
        logDatabaseError($e, "Fetch Reports and Top Performers");
    }
    $stats = [];
    $top_performers = [];

    if ($is_api_call) {
        sendErrorResponse('Database error: ' . $e->getMessage(), 500);
    }
}

// If API call, return JSON response
if ($is_api_call) {
    sendSuccessResponse([
        'stats' => $stats,
        'top_performers' => $top_performers ?? []
    ]);
}


