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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash_message('error', 'Invalid request method!');
    header('Location: ' . BASE_URL . '/modules/test-management/answer-keys.php');
    exit;
}

$answer_key_id = $_POST['answer_key_id'] ?? 0;
$test_name = trim($_POST['test_name'] ?? '');
$paper_set_id = $_POST['paper_set_id'] ?? 0;
$test_date = $_POST['test_date'] ?? null;
$status = $_POST['status'] ?? 'active';
$questions = $_POST['questions'] ?? [];
$answers = $_POST['answers'] ?? [];

// Validation
if (empty($test_name)) {
    set_flash_message('error', 'Test name is required!');
    header('Location: ' . BASE_URL . '/modules/test-management/answer-key-edit.php?id=' . $answer_key_id);
    exit;
}

if (empty($paper_set_id)) {
    set_flash_message('error', 'Paper set is required!');
    header('Location: ' . BASE_URL . '/modules/test-management/answer-key-edit.php?id=' . $answer_key_id);
    exit;
}

if (count($questions) !== count($answers)) {
    set_flash_message('error', 'Questions and answers count mismatch!');
    header('Location: ' . BASE_URL . '/modules/test-management/answer-key-edit.php?id=' . $answer_key_id);
    exit;
}

// Verify answer key exists
try {
    $stmt = $conn->prepare("SELECT * FROM tbl_answer_keys WHERE id = ?");
    $stmt->execute([$answer_key_id]);
    $existing = $stmt->fetch();

    if (!$existing) {
        set_flash_message('error', 'Answer key not found!');
        header('Location: ' . BASE_URL . '/modules/test-management/answer-keys.php');
        exit;
    }
} catch (PDOException $e) {
    logDatabaseError($e, "Verify Answer Key Exists");
    set_flash_message('error', 'Database error: ' . $e->getMessage());
    header('Location: ' . BASE_URL . '/modules/test-management/answer-keys.php');
    exit;
}

// Build answers JSON
$answers_data = [];
foreach ($questions as $index => $question_num) {
    $answer = strtoupper(trim($answers[$index] ?? ''));
    if (!empty($answer)) {
        $answers_data[] = [
            'q' => (int) $question_num,
            'ans' => $answer
        ];
    }
}

$answers_json = json_encode($answers_data);
$total_questions = count($answers_data);

// Update answer key
try {
    $stmt = $conn->prepare("UPDATE tbl_answer_keys 
                           SET test_name = ?, 
                               paper_set_id = ?, 
                               test_date = ?, 
                               total_questions = ?, 
                               answers_json = ?, 
                               status = ?,
                               updated_at = NOW()
                           WHERE id = ?");

    $stmt->execute([
        $test_name,
        $paper_set_id,
        $test_date ?: null,
        $total_questions,
        $answers_json,
        $status,
        $answer_key_id
    ]);

    set_flash_message('success', 'Answer key updated successfully!');
    header('Location: ' . BASE_URL . '/modules/test-management/answer-key-view.php?id=' . $answer_key_id);
    exit;
} catch (PDOException $e) {
    logDatabaseError($e, "Update Answer Key");
    set_flash_message('error', 'Failed to update answer key: ' . $e->getMessage());
    header('Location: ' . BASE_URL . '/modules/test-management/answer-key-edit.php?id=' . $answer_key_id);
    exit;
}
