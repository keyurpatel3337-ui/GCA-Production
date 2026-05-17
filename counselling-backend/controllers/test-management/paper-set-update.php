<?php
require_once __DIR__ . '/../../common/session_config.php';
require_once dirname(dirname(__DIR__)) . '/common/constants.php'; require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once OPERATION_FILE;
require_once HELPER_ERROR_LOGGER;

// Initialize Database Operations
$dbOps = new DatabaseOperations();

// Check if user is authorized (Admin or Counsellor)
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_COUNSELLOR)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paper_set_id = $_POST['paper_set_id'] ?? 0;
    $paper_name = trim($_POST['paper_name'] ?? '');
    $paper_code = trim($_POST['paper_code'] ?? '');
    $total_questions = intval($_POST['total_questions'] ?? 100);
    $low_level_count = intval($_POST['low_level_count'] ?? 48);
    $medium_level_count = intval($_POST['medium_level_count'] ?? 26);
    $high_level_count = intval($_POST['high_level_count'] ?? 26);
    $status = $_POST['status'] ?? 'active';

    // Validation
    if (empty($paper_name) || empty($paper_code)) {
        $_SESSION['error_message'] = 'Paper name and code are required.';
        header('Location: ' . BASE_URL . '/modules/test-management/paper-set-edit.php?id=' . $paper_set_id);
        exit;
    }

    if ($total_questions != ($low_level_count + $medium_level_count + $high_level_count)) {
        $_SESSION['error_message'] = 'Total questions must equal the sum of difficulty levels.';
        header('Location: ' . BASE_URL . '/modules/test-management/paper-set-edit.php?id=' . $paper_set_id);
        exit;
    }

    try {
        // Check if paper code already exists (excluding current paper set)
        $duplicate = $dbOps->customSelectOne("SELECT id FROM tbl_paper_sets WHERE paper_code = ? AND id != ?", [$paper_code, $paper_set_id]);
        if ($duplicate) {
            $_SESSION['error_message'] = 'Paper code already exists. Please use a different code.';
            header('Location: ' . BASE_URL . '/modules/test-management/paper-set-edit.php?id=' . $paper_set_id);
            exit;
        }

        // Update paper set
        $stmt = $conn->prepare("
            UPDATE tbl_paper_sets 
            SET paper_name = ?, 
                paper_code = ?, 
                total_questions = ?,
                low_level_count = ?,
                medium_level_count = ?,
                high_level_count = ?,
                status = ?
            WHERE id = ?
        ");

        $result = $stmt->execute([
            $paper_name,
            $paper_code,
            $total_questions,
            $low_level_count,
            $medium_level_count,
            $high_level_count,
            $status,
            $paper_set_id
        ]);

        if ($result) {
            $_SESSION['success_message'] = 'Paper set updated successfully.';
            header('Location: ' . BASE_URL . '/modules/test-management/paper-sets.php');
        } else {
            $_SESSION['error_message'] = 'Failed to update paper set.';
            header('Location: ' . BASE_URL . '/modules/test-management/paper-set-edit.php?id=' . $paper_set_id);
        }
    } catch (PDOException $e) {
        logDatabaseError($e, "Update Paper Set");
        $_SESSION['error_message'] = 'Database error: ' . $e->getMessage();
        header('Location: ' . BASE_URL . '/modules/test-management/paper-set-edit.php?id=' . $paper_set_id);
    }
    exit;
} else {
    header('Location: ' . BASE_URL . '/modules/test-management/paper-sets.php');
    exit;
}
