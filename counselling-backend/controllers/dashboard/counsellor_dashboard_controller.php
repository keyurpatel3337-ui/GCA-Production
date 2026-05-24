<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';

/**
 * Counsellor Dashboard Controller
 * Supports both API mode (JSON response) and direct inclusion mode
 */

$base_path = dirname(dirname(__DIR__));

require_once DB_CONNECT_FILE;

// Check if this is an API call (via index.php router)
$is_api_call = defined('API_MODE') || (isset($_GET['route']) && !empty($_GET['route']));

if (!$is_api_call) {
    require_once OPERATION_FILE;
    require_once $base_path . '/../common/helpers/error_logger.php';
    if (!hasRole(ROLE_COUNSELLOR)) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

$user_id = $_SESSION['user_id'] ?? null;
$role_id = $_SESSION['role_id'] ?? null;

$page_title = "Counsellor Dashboard";

// Get statistics
$stats = [];
try {
    // My Students
    if ($user_id) {
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM tbl_gm_std_registration WHERE counsellor_id = ?");
        $stmt->execute([$user_id]);
        $stats['total_students'] = $stmt->fetch()['total'];

        // Total enrolled (assigned to me)
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM tbl_enrolled_students e JOIN tbl_gm_std_registration r ON e.registration_id = r.id WHERE r.counsellor_id = ?");
        $stmt->execute([$user_id]);
        $stats['total_enrolled'] = $stmt->fetch()['total'];

        // Pending admission confirmations
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM tbl_gm_std_registration WHERE counsellor_id = ? AND (admission_confirmed = 0 OR admission_confirmed IS NULL)");
        $stmt->execute([$user_id]);
        $stats['pending_confirmations'] = $stmt->fetch()['total'];
    }

    $success = true;
    $error = null;
} catch (PDOException $e) {
    if (!$is_api_call && function_exists('logDatabaseError')) {
        logDatabaseError($e, "Fetch Counsellor Dashboard Stats");
    }
    $success = false;
    $error = $e->getMessage();
}

// If API call, return JSON response
if ($is_api_call) {
    sendJsonResponse([
        'success' => $success,
        'data' => $stats,
        'error' => $error
    ]);
}

// For direct inclusion
$total_students = $stats['total_students'] ?? 0;
$total_enrolled = $stats['total_enrolled'] ?? 0;

$page_title = "Counsellor Dashboard";
$page_breadcrumb = "Dashboard";
