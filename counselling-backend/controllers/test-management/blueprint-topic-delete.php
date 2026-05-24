<?php
require_once __DIR__ . '/../../common/session_config.php';
require_once dirname(dirname(__DIR__)) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once OPERATION_FILE;

// Check if user is Super Admin or Principle
if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE])) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$topic_id = $_GET['id'] ?? 0;
$paper_set_id = $_GET['paper_set_id'] ?? 0;
$force_delete = $_GET['force'] ?? 0;

if (!$topic_id || !$paper_set_id) {
    set_flash_message('error', 'Invalid request parameters!');
    header('Location: ' . BASE_URL . '/modules/test-management/blueprint.php?paper_set_id=' . $paper_set_id);
    exit;
}

try {
    $conn->beginTransaction();

    // Check if topic exists
    $stmt = $conn->prepare("SELECT * FROM tbl_blueprint_topics WHERE id = ? AND paper_set_id = ?");
    $stmt->execute([$topic_id, $paper_set_id]);
    $topic = $stmt->fetch();

    if (!$topic) {
        throw new Exception('Topic not found!');
    }

    // Check if there are questions linked to this topic
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM tbl_blueprint_questions WHERE blueprint_topic_id = ?");
    $stmt->execute([$topic_id]);
    $question_count = $stmt->fetch()['count'];

    if ($question_count > 0 && !$force_delete) {
        set_flash_message('warning', "This topic has $question_count questions linked to it.");
        $_SESSION['confirm_delete'] = [
            'topic_id' => $topic_id,
            'paper_set_id' => $paper_set_id,
            'question_count' => $question_count
        ];
        header('Location: ' . BASE_URL . '/modules/test-management/blueprint.php?paper_set_id=' . $paper_set_id);
        exit;
    }

    // Delete linked questions first (cascade delete)
    if ($question_count > 0) {
        $stmt = $conn->prepare("DELETE FROM tbl_blueprint_questions WHERE blueprint_topic_id = ?");
        $stmt->execute([$topic_id]);
    }

    // Delete the topic
    $stmt = $conn->prepare("DELETE FROM tbl_blueprint_topics WHERE id = ?");
    $stmt->execute([$topic_id]);

    $conn->commit();

    if ($question_count > 0) {
        set_flash_message('success', "Topic and $question_count linked questions deleted successfully!");
    } else {
        set_flash_message('success', 'Topic deleted successfully!');
    }
    header('Location: ' . BASE_URL . '/modules/test-management/blueprint.php?paper_set_id=' . $paper_set_id);
    exit;
} catch (PDOException $e) {
    $conn->rollBack();
    set_flash_message('error', 'Database error: ' . $e->getMessage());
    header('Location: ' . BASE_URL . '/modules/test-management/blueprint.php?paper_set_id=' . $paper_set_id);
    exit;
} catch (Exception $e) {
    $conn->rollBack();
    set_flash_message('error', $e->getMessage());
    header('Location: ' . BASE_URL . '/modules/test-management/blueprint.php?paper_set_id=' . $paper_set_id);
    exit;
}
