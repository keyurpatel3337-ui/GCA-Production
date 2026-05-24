<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';

/**
 * Schools Controller
 * Supports both API mode (JSON response) and direct inclusion mode
 */

$base_path = dirname(dirname(__DIR__));

require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
// Initialize database operations
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

$page_title = "School Management";
$page_breadcrumb = "Schools";

// Handle API POST actions
if ($is_api_call && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($route === 'settings/school-save' || $route === 'settings/school-update') {
            $id = intval($_POST['id'] ?? 0);
            $school_code = trim($_POST['school_code'] ?? '');
            $school_name = trim($_POST['school_name'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $city = trim($_POST['city'] ?? '');
            $state = trim($_POST['state'] ?? '');
            $pincode = trim($_POST['pincode'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $principal_name = trim($_POST['principal_name'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $created_by = $_SESSION['user_id'] ?? 0;

            if (empty($school_code) || empty($school_name)) {
                echo json_encode(['success' => false, 'message' => 'School code and name are required']);
                exit;
            }

            // Check for duplicate school code
            $existing = $dbOps->selectOne('tbl_schools', ['id'], ['school_code' => $school_code]);
            if ($existing && $existing['id'] != $id) {
                echo json_encode(['success' => false, 'message' => 'School code already exists']);
                exit;
            }

            $split_labels_json = json_encode([]);

            if ($id > 0) {
                $stmt = $conn->prepare("UPDATE tbl_schools SET school_code = ?, school_name = ?, address = ?, city = ?, state = ?, pincode = ?, phone = ?, email = ?, principal_name = ?, is_active = ?, split_labels = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$school_code, $school_name, $address, $city, $state, $pincode, $phone, $email, $principal_name, $is_active, $split_labels_json, $id]);
                $message = 'School updated successfully';
            } else {
                $stmt = $conn->prepare("INSERT INTO tbl_schools (school_code, school_name, address, city, state, pincode, phone, email, principal_name, is_active, split_labels, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$school_code, $school_name, $address, $city, $state, $pincode, $phone, $email, $principal_name, $is_active, $split_labels_json, $created_by]);
                $message = 'School added successfully';
            }
            echo json_encode(['success' => true, 'message' => $message]);
            exit;
        } elseif ($route === 'settings/school-delete') {
            $id = intval($_POST['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid school ID']);
                exit;
            }

            // Check dependencies (Enrolled Students and Students)
            $enrolledCount = $dbOps->count('tbl_enrolled_students', ['school_id' => $id]);
            if ($enrolledCount > 0) {
                echo json_encode(['success' => false, 'message' => 'Cannot delete school. It is being used by enrolled students.']);
                exit;
            }

            $studentCount = $dbOps->count('tbl_enrolled_students', ['school_id' => $id]);
            if ($studentCount > 0) {
                echo json_encode(['success' => false, 'message' => 'Cannot delete school. It is linked to students.']);
                exit;
            }

            $stmt = $conn->prepare("DELETE FROM tbl_schools WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'School deleted successfully']);
            exit;
        } elseif ($route === 'settings/school-delete-multiple') {
            $ids = isset($_POST['ids']) && is_array($_POST['ids']) ? array_map('intval', $_POST['ids']) : [];
            if (empty($ids) || !is_array($ids)) {
                echo json_encode(['success' => false, 'message' => 'No schools selected']);
                exit;
            }

            $ids = array_map('intval', $ids);
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';

            // Check if any schools are in use
            $dependencyCount = $dbOps->customSelectOne(
                "SELECT COUNT(*) as count FROM tbl_enrolled_students WHERE school_id IN ($placeholders)",
                $ids
            );
            if ($dependencyCount && $dependencyCount['count'] > 0) {
                echo json_encode(['success' => false, 'message' => 'Some selected schools are linked to students and cannot be deleted.']);
                exit;
            }

            $stmt = $conn->prepare("DELETE FROM tbl_schools WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            echo json_encode(['success' => true, 'message' => count($ids) . ' schools deleted successfully']);
            exit;
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

// Handle GET actions (List or Single)
if ($is_api_call && $route === 'settings/school-get') {
    $id = intval($_GET['id'] ?? 0);
    $school = $dbOps->selectOne('tbl_schools', ['*'], ['id' => $id]);
    if ($school) {
        // Decode split_labels if it's a JSON string
        if (isset($school['split_labels']) && !empty($school['split_labels'])) {
            // $school['split_labels'] = json_decode($school['split_labels'], true);
        }
        echo json_encode(['success' => true, 'data' => ['school' => $school]]);
    } else {
        echo json_encode(['success' => false, 'message' => 'School not found']);
    }
    exit;
}

// Fetch all schools (for listing or direct inclusion)
try {
    $schools = $dbOps->select('tbl_schools', ['*'], [], 'school_name ASC');

    // Fetch created_by user names
    if (is_array($schools)) {
        foreach ($schools as &$school) {
            if (isset($school['created_by']) && $school['created_by']) {
                $creator = $dbOps->selectOne('tbl_users', ['name'], ['id' => $school['created_by']]);
                $school['created_by_name'] = $creator['name'] ?? 'Unknown';
            } else {
                $school['created_by_name'] = 'System';
            }
        }
        unset($school); // Break reference
    } else {
        $schools = [];
    }

    error_log("Schools Controller: Fetched " . count($schools) . " schools.");
} catch (PDOException $e) {
    $schools = [];
    if ($is_api_call) {
        sendErrorResponse('Database error: ' . $e->getMessage(), 500);
    }
}

// If API call, return JSON response
if ($is_api_call) {
    sendSuccessResponse(['schools' => $schools]);
}
