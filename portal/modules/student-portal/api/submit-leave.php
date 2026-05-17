<?php
require_once __DIR__ . '/../../../session_config.php';
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once HELPER_ERROR_LOGGER;

header('Content-Type: application/json');

// Check if user is Student
if (!isset($_SESSION['is_student_login']) || $_SESSION['is_student_login'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $student_id = $_POST['student_id'] ?? $_SESSION['student_id'];
    $leave_type = $_POST['leave_type'] ?? '';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $reason = trim($_POST['reason'] ?? '');

    if (empty($leave_type) || empty($start_date) || empty($end_date) || empty($reason)) {
        echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
        exit;
    }

    if (strtotime($end_date) < strtotime($start_date)) {
        echo json_encode(['success' => false, 'message' => 'End date cannot be before start date']);
        exit;
    }

    $diff = strtotime($end_date) - strtotime($start_date);
    if ($diff > (6 * 24 * 60 * 60)) {
        echo json_encode(['success' => false, 'message' => 'Maximum leave duration is 1 week']);
        exit;
    }

    // Check for overlapping leaves
    $stmt = $conn->prepare("SELECT id FROM tbl_student_leaves 
                            WHERE student_id = ? AND status = 'pending' 
                            AND ((start_date <= ? AND end_date >= ?) OR (start_date <= ? AND end_date >= ?))");
    $stmt->execute([$student_id, $end_date, $start_date, $end_date, $start_date]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'You already have a pending leave request overlapping with these dates']);
        exit;
    }

    $doc_path = null;
    $requires_doc = in_array($leave_type, ['Medical', 'Maran Prasange', 'Marriage']);

    // Handle document upload
    if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
        $max_size = 2 * 1024 * 1024; // 2MB

        if (!in_array($_FILES['document']['type'], $allowed_types)) {
            echo json_encode(['success' => false, 'message' => 'Invalid file format. Only JPG, PNG, and PDF are allowed.']);
            exit;
        }

        if ($_FILES['document']['size'] > $max_size) {
            echo json_encode(['success' => false, 'message' => 'File is too large. Maximum size is 2MB.']);
            exit;
        }

        $upload_dir = dirname(dirname(dirname(dirname(dirname(__DIR__))))) . '/uploads/leaves/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_ext = strtolower(pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION));
        $new_filename = 'leave_' . $student_id . '_' . time() . '.' . $file_ext;
        $dest_path = $upload_dir . $new_filename;

        if (move_uploaded_file($_FILES['document']['tmp_name'], $dest_path)) {
            $doc_path = 'uploads/leaves/' . $new_filename;
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to upload document.']);
            exit;
        }
    } elseif ($requires_doc) {
        echo json_encode(['success' => false, 'message' => 'Supporting document is required for ' . $leave_type]);
        exit;
    }

    // Save into database
    $stmt = $conn->prepare("INSERT INTO tbl_student_leaves (student_id, leave_type, start_date, end_date, reason, doc_path, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
    $result = $stmt->execute([$student_id, $leave_type, $start_date, $end_date, $reason, $doc_path]);

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Leave application submitted successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save application.']);
    }

} catch (Exception $e) {
    logError("Submit Leave API Error: " . $e->getMessage(), __FILE__, __LINE__, $e);
    echo json_encode(['success' => false, 'message' => 'An internal server error occurred', 'debug' => $e->getMessage()]);
}
