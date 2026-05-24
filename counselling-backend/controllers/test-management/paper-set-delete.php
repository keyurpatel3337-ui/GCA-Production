<?php
require_once __DIR__ . '/../../common/session_config.php';
require_once dirname(dirname(__DIR__)) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once OPERATION_FILE;
require_once HELPER_ERROR_LOGGER;

// Check if user is Super Admin or Principle
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$paper_set_id = $_GET['id'] ?? 0;
$force_delete = $_GET['force'] ?? 0;

if (!$paper_set_id) {
    set_flash_message('error', 'Invalid paper set ID!');
    header('Location: ' . BASE_URL . '/modules/test-management/paper-sets.php');
    exit;
}

try {
    // Check if paper set exists
    $stmt = $conn->prepare("SELECT * FROM tbl_paper_sets WHERE id = ?");
    $stmt->execute([$paper_set_id]);
    $paper_set = $stmt->fetch();

    if (!$paper_set) {
        set_flash_message('error', 'Paper set not found!');
        header('Location: ' . BASE_URL . '/modules/test-management/paper-sets.php');
        exit;
    }

    // Check for related data
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM tbl_blueprint_topics WHERE paper_set_id = ?");
    $stmt->execute([$paper_set_id]);
    $topic_count = $stmt->fetch()['count'];

    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM tbl_blueprint_questions WHERE paper_set_id = ?");
    $stmt->execute([$paper_set_id]);
    $question_count = $stmt->fetch()['count'];

    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM tbl_answer_keys WHERE paper_set_id = ?");
    $stmt->execute([$paper_set_id]);
    $answer_key_count = $stmt->fetch()['count'];

    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM tbl_test_results WHERE paper_set_id = ?");
    $stmt->execute([$paper_set_id]);
    $result_count = $stmt->fetch()['count'];

    $total_related = $topic_count + $question_count + $answer_key_count + $result_count;

    // If there are related records and no force parameter, show warning
    if ($total_related > 0 && !$force_delete) {
        $_SESSION['confirm_delete'] = [
            'paper_set_id' => $paper_set_id,
            'paper_set_name' => $paper_set['paper_set_name'],
            'topic_count' => $topic_count,
            'question_count' => $question_count,
            'answer_key_count' => $answer_key_count,
            'result_count' => $result_count
        ];
        set_flash_message('warning', "This paper set has related data: $topic_count topics, $question_count questions, $answer_key_count answer keys, and $result_count test results. Click delete again to confirm.");
        header('Location: ' . BASE_URL . '/modules/test-management/paper-sets.php');
        exit;
    }

    // Proceed with deletion
    $conn->beginTransaction();

    // Delete in correct order to respect foreign key constraints

    // 1. Delete test results
    $stmt = $conn->prepare("DELETE FROM tbl_test_results WHERE paper_set_id = ?");
    $stmt->execute([$paper_set_id]);

    // 2. Delete question answers (if they reference paper_set_id)
    $stmt = $conn->prepare("DELETE FROM tbl_question_answers WHERE paper_set_id = ?");
    $stmt->execute([$paper_set_id]);

    // 3. Delete blueprint questions
    $stmt = $conn->prepare("DELETE FROM tbl_blueprint_questions WHERE paper_set_id = ?");
    $stmt->execute([$paper_set_id]);

    // 4. Delete blueprint topics
    $stmt = $conn->prepare("DELETE FROM tbl_blueprint_topics WHERE paper_set_id = ?");
    $stmt->execute([$paper_set_id]);

    // 5. Delete answer keys
    $stmt = $conn->prepare("DELETE FROM tbl_answer_keys WHERE paper_set_id = ?");
    $stmt->execute([$paper_set_id]);

    // 6. Finally delete the paper set
    $stmt = $conn->prepare("DELETE FROM tbl_paper_sets WHERE id = ?");
    $stmt->execute([$paper_set_id]);

    $conn->commit();

    // Clear any confirmation session
    unset($_SESSION['confirm_delete']);

    if ($total_related > 0) {
        set_flash_message('success', "Paper set '{$paper_set['paper_set_name']}' and all related data ({$topic_count} topics, {$question_count} questions, {$answer_key_count} answer keys, {$result_count} test results) deleted successfully!");
    } else {
        set_flash_message('success', "Paper set '{$paper_set['paper_set_name']}' deleted successfully!");
    }
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    logDatabaseError($e, "Delete Paper Set");
    set_flash_message('error', 'Error deleting paper set: ' . $e->getMessage());
}

header('Location: ' . BASE_URL . '/modules/test-management/paper-sets.php');
exit;