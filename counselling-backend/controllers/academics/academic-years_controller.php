<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';

/**
 * Academic Years Controller
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

$page_title = "Academic Year Management";
$page_breadcrumb = "Academic Years";

// Handle API POST actions
if ($is_api_call && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get POST data from either $_POST or JSON body
    $inputData = $_POST;
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    // If JSON content type, parse JSON body
    if (stripos($contentType, 'application/json') !== false) {
        $jsonInput = file_get_contents('php://input');
        $decoded = json_decode($jsonInput, true);
        if ($decoded !== null) {
            $inputData = $decoded;
        }
    }

    try {
        if ($route === 'settings/academic-year-save' || $route === 'settings/academic-year-update') {
            $id = intval($inputData['id'] ?? 0);
            $year_name = trim($inputData['year_name'] ?? '');
            $is_active = isset($inputData['is_active']) ? 1 : 0;
            $start_date = !empty($inputData['start_date']) ? $inputData['start_date'] : null;
            $end_date = !empty($inputData['end_date']) ? $inputData['end_date'] : null;
            $created_by = $_SESSION['user_id'] ?? 0;

            if (empty($year_name)) {
                echo json_encode(['success' => false, 'message' => 'Academic year name is required']);
                exit;
            }

            // Check for duplicate year name
            $existing = $dbOps->selectOne('tbl_academic_years', ['id'], ['year_name' => $year_name]);
            if ($existing && $existing['id'] != $id) {
                echo json_encode(['success' => false, 'message' => 'This academic year already exists']);
                exit;
            }

            if ($id > 0) {
                $stmt = $conn->prepare("UPDATE tbl_academic_years SET year_name = ?, start_date = ?, end_date = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$year_name, $start_date, $end_date, $is_active, $id]);
                $message = 'Academic year updated successfully';
            } else {
                $stmt = $conn->prepare("INSERT INTO tbl_academic_years (year_name, start_date, end_date, is_active, created_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$year_name, $start_date, $end_date, $is_active, $created_by]);
                $message = 'Academic year added successfully';
            }
            echo json_encode(['success' => true, 'message' => $message]);
            exit;
        } elseif ($route === 'settings/academic-year-delete') {
            $id = intval($inputData['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid academic year ID']);
                exit;
            }

            // Check dependencies (Fee Config and Terms)
            $stmt = $conn->prepare("SELECT year_name FROM tbl_academic_years WHERE id = ?");
            $stmt->execute([$id]);
            $year = $stmt->fetch();

            if ($year) {
                $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM tbl_fee_config WHERE academic_year = ?");
                $checkStmt->execute([$year['year_name']]);
                if ($checkStmt->fetch()['count'] > 0) {
                    echo json_encode(['success' => false, 'message' => 'Cannot delete: Used in fee configurations.']);
                    exit;
                }

                $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM tbl_term WHERE academic_year_id = ?");
                $checkStmt->execute([$id]);
                if ($checkStmt->fetch()['count'] > 0) {
                    echo json_encode(['success' => false, 'message' => 'Cannot delete: Linked to terms.']);
                    exit;
                }
            }

            $stmt = $conn->prepare("DELETE FROM tbl_academic_years WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Academic year deleted successfully']);
            exit;
        } elseif ($route === 'settings/academic-years-delete-multiple') {
            $ids = isset($inputData['ids']) && is_array($inputData['ids']) ? array_map('intval', $inputData['ids']) : [];
            if (empty($ids) || !is_array($ids)) {
                echo json_encode(['success' => false, 'message' => 'No academic years selected']);
                exit;
            }

            $ids = array_map('intval', $ids);
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';

            // Check if any academic years are in use in terms
            $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM tbl_term WHERE academic_year_id IN ($placeholders)");
            $checkStmt->execute($ids);
            if ($checkStmt->fetch()['count'] > 0) {
                echo json_encode(['success' => false, 'message' => 'Some selected academic years are linked to terms and cannot be deleted.']);
                exit;
            }

            $stmt = $conn->prepare("DELETE FROM tbl_academic_years WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            echo json_encode(['success' => true, 'message' => count($ids) . ' academic years deleted successfully']);
            exit;
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

// Handle GET actions (List or Single)
if ($is_api_call && $route === 'settings/academic-year-get') {
    $id = intval($_GET['id'] ?? 0);
    $academic_year = $dbOps->selectOne('tbl_academic_years', ['*'], ['id' => $id]);
    if ($academic_year) {
        echo json_encode(['success' => true, 'data' => ['academic_year' => $academic_year]]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Academic year not found']);
    }
    exit;
}

// Fetch all academic years (for listing or direct inclusion)
try {
    $academic_years = $dbOps->select('tbl_academic_years', ['*'], [], 'year_name DESC');

    // If false or null, set to empty array
    if (!is_array($academic_years)) {
        $academic_years = [];
    }

    // Fetch created_by user names
    foreach ($academic_years as &$year) {
        if (isset($year['created_by']) && $year['created_by']) {
            $creator = $dbOps->selectOne('tbl_users', ['name'], ['id' => $year['created_by']]);
            $year['created_by_name'] = $creator['name'] ?? 'Unknown';
        } else {
            $year['created_by_name'] = 'System';
        }
    }
    unset($year); // Break reference

} catch (PDOException $e) {
    $academic_years = [];
    if ($is_api_call) {
        sendErrorResponse('Database error: ' . $e->getMessage(), 500);
    }
}

// If API call, return JSON response
if ($is_api_call) {
    sendSuccessResponse(['academic_years' => $academic_years]);
}
