<?php
/**
 * Students Module API Endpoint
 * Handles all AJAX API requests for student operations
 */
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;

// Set JSON response header
header('Content-Type: application/json');

// CORS Configuration - Whitelist allowed origins
$allowedOrigins = [
    'http://localhost',
    'http://localhost:80',
    'http://127.0.0.1',
    'https://gyanmanjari.com',
    'http://gyanmanjari.com'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get JSON input for POST requests
$input = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true) ?? $_POST;
}

// Get action from query parameter or input
$action = $_POST['action'] ?? $input['action'] ?? '';

// Response helper
function jsonResponse($success, $data = null, $message = null)
{
    $response = ['success' => $success];
    if ($data !== null)
        $response['data'] = $data;
    if ($message !== null)
        $response['message'] = $message;
    echo json_encode($response);
    exit;
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    jsonResponse(false, null, 'Not authenticated');
}

try {
    switch ($action) {
        // ==================== Counsellor Assignment APIs ====================

        case 'get-counsellors':
            // Get all active counsellors
            $counsellors = $dbOps->customSelect("SELECT id, name, email, phone FROM tbl_users WHERE role_id = ? AND status = 'active' ORDER BY name", [ROLE_COUNSELLOR]);
            jsonResponse(true, ['counsellors' => $counsellors]);
            break;

        case 'counsellor-assign':
            // Check permissions
            if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE)) {
                jsonResponse(false, null, 'Permission denied');
            }

            $student_id = intval($input['student_id'] ?? 0);
            $counsellor_id = isset($input['counsellor_id']) && $input['counsellor_id'] !== '' ? intval($input['counsellor_id']) : null;
            $assignAction = $input['assign_action'] ?? 'assign';

            if ($student_id <= 0) {
                jsonResponse(false, null, 'Invalid student ID');
            }

            if ($assignAction === 'remove') {
                $stmt = $conn->prepare("UPDATE tbl_gm_std_registration SET counsellor_id = NULL WHERE id = ?");
                $stmt->execute([$student_id]);
                jsonResponse(true, null, 'Counsellor removed successfully');
            } else {
                if (!$counsellor_id) {
                    jsonResponse(false, null, 'Please select a counsellor');
                }
                $stmt = $conn->prepare("UPDATE tbl_gm_std_registration SET counsellor_id = ? WHERE id = ?");
                $stmt->execute([$counsellor_id, $student_id]);
                jsonResponse(true, null, 'Counsellor assigned successfully');
            }
            break;

        case 'counsellor-bulk-assign':
            // Check permissions
            if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE)) {
                jsonResponse(false, null, 'Permission denied');
            }

            $counsellor_id = intval($input['counsellor_id'] ?? 0);
            $student_ids = $input['student_ids'] ?? [];

            if ($counsellor_id <= 0) {
                jsonResponse(false, null, 'Please select a counsellor');
            }

            if (empty($student_ids)) {
                jsonResponse(false, null, 'No students selected');
            }

            $placeholders = str_repeat('?,', count($student_ids) - 1) . '?';
            $stmt = $conn->prepare("UPDATE tbl_gm_std_registration SET counsellor_id = ? WHERE id IN ($placeholders)");
            $stmt->execute(array_merge([$counsellor_id], $student_ids));

            $count = $stmt->rowCount();
            jsonResponse(true, ['count' => $count], "$count student(s) assigned successfully");
            break;

        case 'counsellor-auto-assign':
            // Check permissions
            if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE)) {
                jsonResponse(false, null, 'Permission denied');
            }

            $students_per_counsellor = intval($input['students_per_counsellor'] ?? 10);

            // Get all active counsellors
            $stmt = $conn->query("SELECT id FROM tbl_users WHERE role_id = " . ROLE_COUNSELLOR . " AND status = 'active'");
            $counsellors = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (empty($counsellors)) {
                jsonResponse(false, null, 'No active counsellors found');
            }

            // Get unassigned students
            $sql = "SELECT id FROM tbl_gm_std_registration WHERE counsellor_id IS NULL AND status = 1 ORDER BY id ASC";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $students = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (empty($students)) {
                jsonResponse(true, ['count' => 0], 'No unassigned students found');
            }

            // Distribute students
            $counsellor_index = 0;
            $counts = array_fill_keys($counsellors, 0);
            $assigned = 0;

            $update_stmt = $conn->prepare("UPDATE tbl_gm_std_registration SET counsellor_id = ? WHERE id = ?");

            foreach ($students as $student_id) {
                $current_counsellor = $counsellors[$counsellor_index];

                if ($counts[$current_counsellor] < $students_per_counsellor) {
                    $update_stmt->execute([$current_counsellor, $student_id]);
                    $counts[$current_counsellor]++;
                    $assigned++;
                }

                $counsellor_index = ($counsellor_index + 1) % count($counsellors);

                $all_full = true;
                foreach ($counts as $count) {
                    if ($count < $students_per_counsellor) {
                        $all_full = false;
                        break;
                    }
                }
                if ($all_full)
                    break;
            }

            jsonResponse(true, ['count' => $assigned], "$assigned student(s) assigned successfully");
            break;

        case 'preview-by-mobile':
            // Preview students by mobile numbers
            $mobile_numbers_raw = $input['mobile_numbers'] ?? '';

            if (empty($mobile_numbers_raw)) {
                jsonResponse(false, null, 'No mobile numbers provided');
            }

            // Parse mobile numbers
            $mobile_numbers = array_map('trim', preg_split('/[,\s\n]+/', $mobile_numbers_raw));
            $mobile_numbers = array_filter($mobile_numbers, function ($m) {
                return preg_match('/^\d{10}$/', $m);
            });

            if (empty($mobile_numbers)) {
                jsonResponse(false, null, 'No valid mobile numbers found');
            }

            // Find students
            $placeholders = str_repeat('?,', count($mobile_numbers) - 1) . '?';
            $stmt = $conn->prepare("SELECT s.id, CONCAT(s.surname, ' ', s.student_name, ' ', s.fathers_name) as full_name, 
                                    s.mob, s.counsellor_id, u.name as counsellor_name
                                    FROM tbl_gm_std_registration s
                                    LEFT JOIN tbl_users u ON s.counsellor_id = u.id
                                    WHERE s.mob IN ($placeholders)");
            $stmt->execute($mobile_numbers);
            $students = $stmt->fetchAll();

            // Find not found numbers
            $found_mobiles = array_column($students, 'mob');
            $not_found = array_diff($mobile_numbers, $found_mobiles);

            jsonResponse(true, [
                'students' => $students,
                'not_found' => array_values($not_found)
            ]);
            break;

        case 'search_students':
            $query = $_GET['query'] ?? '';
            if (strlen($query) < 2) {
                echo json_encode([]);
                exit;
            }

            $sql = "SELECT id, student_name, mob 
                    FROM tbl_gm_std_registration 
                    WHERE (student_name LIKE ? OR id LIKE ? OR mob LIKE ?) 
                    LIMIT 10";
            $params = ["%$query%", "%$query%", "%$query%"];
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($results);
            exit;
            break;

        default:
            jsonResponse(false, null, 'Invalid action: ' . $action);
    }

} catch (PDOException $e) {
    logDatabaseError($e, "Students API - $action");
    jsonResponse(false, null, 'Database error occurred');
} catch (Exception $e) {
    logAppError("Students API - $action", $e->getMessage());
    jsonResponse(false, null, $e->getMessage());
}


