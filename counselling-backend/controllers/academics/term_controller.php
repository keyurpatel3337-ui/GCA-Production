<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';

/**
 * Term Controller
 * Supports both API mode (JSON response) and direct inclusion mode
 */

$base_path = dirname(dirname(__DIR__));

require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
// Initialize Database Operations
$dbOps = new DatabaseOperations();

// Check if this is an API call (via index.php router)
$is_api_call = defined('API_MODE') || (isset($_GET['route']) && !empty($_GET['route']));
$route = $_GET['route'] ?? '';

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

$page_title = "Term Management";
$page_breadcrumb = "Terms";

// Handle API POST actions
if ($is_api_call && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($route === 'settings/term-save' || $route === 'settings/term-update') {
            $id = intval($_POST['id'] ?? 0);
            $academic_year_id = intval($_POST['academic_year_id'] ?? 0);
            $term_name = trim($_POST['term_name'] ?? '');
            $term_number = intval($_POST['term_number'] ?? 0);
            $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
            $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $created_by = $_SESSION['user_id'] ?? 0;

            if ($academic_year_id <= 0 || empty($term_name) || $term_number < 1) {
                echo json_encode(['success' => false, 'message' => 'Academic year, term name, and term number are required']);
                exit;
            }

            // Check for duplicate term name within same academic year
            $duplicate = $dbOps->customSelectOne(
                "SELECT id FROM tbl_term WHERE academic_year_id = ? AND term_name = ? AND id != ?",
                [$academic_year_id, $term_name, $id]
            );
            if ($duplicate) {
                echo json_encode(['success' => false, 'message' => 'Term already exists for this academic year']);
                exit;
            }

            // Check for duplicate term number within same academic year
            $duplicate = $dbOps->customSelectOne(
                "SELECT id FROM tbl_term WHERE academic_year_id = ? AND term_number = ? AND id != ?",
                [$academic_year_id, $term_number, $id]
            );
            if ($duplicate) {
                echo json_encode(['success' => false, 'message' => 'Term number already exists for this academic year']);
                exit;
            }

            if ($id > 0) {
                $stmt = $conn->prepare("UPDATE tbl_term SET academic_year_id = ?, term_name = ?, term_number = ?, start_date = ?, end_date = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$academic_year_id, $term_name, $term_number, $start_date, $end_date, $is_active, $id]);
                $message = 'Term updated successfully';
            } else {
                $stmt = $conn->prepare("INSERT INTO tbl_term (academic_year_id, term_name, term_number, start_date, end_date, is_active, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$academic_year_id, $term_name, $term_number, $start_date, $end_date, $is_active, $created_by]);
                $message = 'Term added successfully';
            }
            echo json_encode(['success' => true, 'message' => $message]);
            exit;
        } elseif ($route === 'settings/term-delete') {
            $id = intval($_POST['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid term ID']);
                exit;
            }

            // Check dependencies (Fee Config and Students)
            $stmt = $conn->prepare("SELECT term_name FROM tbl_term WHERE id = ?");
            $stmt->execute([$id]);
            $term = $stmt->fetch();

            if ($term) {
                $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM tbl_fee_config WHERE term = ?");
                $checkStmt->execute([$term['term_name']]);
                if ($checkStmt->fetch()['count'] > 0) {
                    echo json_encode(['success' => false, 'message' => 'Cannot delete term. It is used in fee configurations.']);
                    exit;
                }

                $studentCount = $dbOps->count('tbl_enrolled_students', ['term_id' => $id]);
                if ($studentCount > 0) {
                    echo json_encode(['success' => false, 'message' => 'Cannot delete term. It is linked to students.']);
                    exit;
                }
            }

            $stmt = $conn->prepare("DELETE FROM tbl_term WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Term deleted successfully']);
            exit;
        } elseif ($route === 'settings/term-delete-multiple') {
            $ids = $_POST['ids'] ?? [];
            if (empty($ids) || !is_array($ids)) {
                echo json_encode(['success' => false, 'message' => 'No terms selected']);
                exit;
            }

            $ids = array_map('intval', $ids);
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';

            // Check if any terms are in use
            $dependencyCount = $dbOps->customSelectOne(
                "SELECT COUNT(*) as count FROM tbl_enrolled_students WHERE term_id IN ($placeholders)",
                $ids
            );
            if ($dependencyCount && $dependencyCount['count'] > 0) {
                echo json_encode(['success' => false, 'message' => 'Some selected terms are linked to students and cannot be deleted.']);
                exit;
            }

            $stmt = $conn->prepare("DELETE FROM tbl_term WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            echo json_encode(['success' => true, 'message' => count($ids) . ' terms deleted successfully']);
            exit;
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

// Handle GET actions (List or Single)
if ($is_api_call && $route === 'settings/term-get') {
    $id = intval($_GET['id'] ?? 0);
    $term = $dbOps->selectOne('tbl_term', ['*'], ['id' => $id]);
    if ($term) {
        echo json_encode(['success' => true, 'data' => ['term' => $term]]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Term not found']);
    }
    exit;
}

// Fetch all terms (for listing or direct inclusion) - Using Operation.php
try {
    $terms = $dbOps->customSelect(
        "SELECT t.*, ay.year_name 
         FROM tbl_term t
         LEFT JOIN tbl_academic_years ay ON t.academic_year_id = ay.id
         ORDER BY ay.year_name DESC, t.term_number desc"
    );
    if ($terms === false)
        $terms = [];

    // Also fetch academic years for dropdown
    $academic_years = $dbOps->select('tbl_academic_years', ['id', 'year_name'], ['is_active' => 1], 'year_name DESC');
    if ($academic_years === false)
        $academic_years = [];
} catch (PDOException $e) {
    $terms = [];
    $academic_years = [];
    if ($is_api_call) {
        sendErrorResponse('Database error: ' . $e->getMessage(), 500);
    }
}

// If API call, return JSON response
if ($is_api_call) {
    sendSuccessResponse([
        'terms' => $terms,
        'academic_years' => $academic_years
    ]);
}


