<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';

/**
 * Delete Multiple Students
 * Supports both API mode (JSON response) and AJAX calls
 */

$base_path = dirname(dirname(__DIR__));

// Check if this is an API call (via index.php router)
$is_api_call = defined('API_MODE') || (isset($_GET['route']) && !empty($_GET['route']));

// Start session only if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once DB_CONNECT_FILE;

// Include dependencies

require_once OPERATION_FILE;
require_once $base_path . '/../common/helpers/error_logger.php';
// Check permissions (even for API calls)
if ($is_api_call) {
    if (!isset($_SESSION['user_id'])) {
        sendErrorResponse('Unauthorized access', 401);
    }
    if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE)) {
        sendErrorResponse('Permission denied', 403);
    }
} else {
    // Check if user has appropriate role for non-API inclusion
    if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
        exit;
    }
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    $data = $_POST;
}

if (!isset($data['ids']) || !is_array($data['ids'])) {
    if ($is_api_call) {
        sendErrorResponse('Invalid data provided', 400);
    }
    echo json_encode(['success' => false, 'message' => 'Invalid data provided']);
    exit;
}

$ids = array_map('intval', $data['ids']);
$deletedCount = 0;
$skippedCount = 0;
$errors = [];

try {
    $conn->beginTransaction();

    foreach ($ids as $id) {
        // Check if student exists
        $stmt = $conn->prepare("SELECT CONCAT(surname, ' ', student_name, ' ', fathers_name) as full_name FROM tbl_gm_std_registration WHERE id = ?");
        $stmt->execute([$id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$student) {
            $skippedCount++;
            $errors[] = "Student with ID $id not found";
            continue;
        }

        // Check if student has enrollment record
        $stmt = $conn->prepare("SELECT enrollment_id FROM tbl_enrolled_students WHERE registration_id = ?");
        $stmt->execute([$id]);
        $enrollment = $stmt->fetch(PDO::FETCH_ASSOC);

        // Check if student has counselling sessions
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM tbl_sessions WHERE student_id = ?");
        $stmt->execute([$id]);
        $sessionsResult = $stmt->fetch(PDO::FETCH_ASSOC);

        // Check if student has appointments
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM tbl_appointments WHERE student_id = ?");
        $stmt->execute([$id]);
        $appointmentsResult = $stmt->fetch(PDO::FETCH_ASSOC);

        // Check if student has payment records
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM tbl_payments WHERE student_id = ?");
        $stmt->execute([$id]);
        $paymentsResult = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($sessionsResult['count'] > 0 || $appointmentsResult['count'] > 0 || $paymentsResult['count'] > 0) {
            $skippedCount++;
            $reasons = [];
            if ($sessionsResult['count'] > 0) {
                $reasons[] = "{$sessionsResult['count']} counselling session(s)";
            }
            if ($appointmentsResult['count'] > 0) {
                $reasons[] = "{$appointmentsResult['count']} appointment(s)";
            }
            if ($paymentsResult['count'] > 0) {
                $reasons[] = "{$paymentsResult['count']} payment record(s)";
            }
            $errors[] = "Student '{$student['full_name']}' has " . implode(" and ", $reasons);
            continue;
        }

        // Delete related records first (if any)
        if ($enrollment) {
            $stmt = $conn->prepare("DELETE FROM tbl_enrolled_students WHERE registration_id = ?");
            $stmt->execute([$id]);
        }

        // Delete the student
        $stmt = $conn->prepare("DELETE FROM tbl_gm_std_registration WHERE id = ?");
        if ($stmt->execute([$id])) {
            $deletedCount++;
        } else {
            $skippedCount++;
            $errors[] = "Failed to delete student '{$student['full_name']}'";
        }
    }

    $conn->commit();

    $message = "Successfully deleted $deletedCount student(s)";
    if ($skippedCount > 0) {
        $message .= ". Skipped $skippedCount student(s)";
        if (!empty($errors)) {
            $message .= ": " . implode(", ", array_slice($errors, 0, 3));
            if (count($errors) > 3) {
                $message .= " and " . (count($errors) - 3) . " more";
            }
        }
    }

    if ($is_api_call) {
        sendSuccessResponse([
            'deleted' => $deletedCount,
            'skipped' => $skippedCount,
            'errors' => $errors
        ], $message);
    }

    echo json_encode([
        'success' => true,
        'message' => $message,
        'deleted' => $deletedCount,
        'skipped' => $skippedCount
    ]);
} catch (Exception $e) {
    $conn->rollBack();
    if ($is_api_call) {
        sendErrorResponse('Error: ' . $e->getMessage(), 500);
    }
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
