<?php
require_once __DIR__ . '/../../common/session_config.php';
require_once dirname(dirname(__DIR__)) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;

// Initialize Database Operations
$dbOps = new DatabaseOperations();

// Check if user is Super Admin, Principle, or Counsellor
if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_COUNSELLOR])) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['paper_set_id'])) {
    set_flash_message('error', 'Invalid request!');
    header('Location: ' . BASE_URL . '/modules/test-management/blueprint-upload.php');
    exit;
}

$paper_set_id = $_POST['paper_set_id'];
$topics = $_POST['topics'] ?? [];

if (empty($topics)) {
    set_flash_message('error', 'No topics to save!');
    header('Location: ' . BASE_URL . '/modules/test-management/blueprint-upload.php');
    exit;
}

try {
    $conn->beginTransaction();

    // Delete existing blueprint for this paper set
    $stmt = $conn->prepare("DELETE FROM tbl_blueprint_questions WHERE paper_set_id = ?");
    $stmt->execute([$paper_set_id]);

    $stmt = $conn->prepare("DELETE FROM tbl_blueprint_topics WHERE paper_set_id = ?");
    $stmt->execute([$paper_set_id]);

    $total_questions = 0;
    $low_count = 0;
    $medium_count = 0;
    $high_count = 0;

    // Insert topics and questions
    foreach ($topics as $topic_data) {
        // Parse question numbers
        $low_questions = array_filter(array_map('trim', explode(',', $topic_data['low_questions'] ?? '')));
        $medium_questions = array_filter(array_map('trim', explode(',', $topic_data['medium_questions'] ?? '')));
        $high_questions = array_filter(array_map('trim', explode(',', $topic_data['high_questions'] ?? '')));

        $topic_total = count($low_questions) + count($medium_questions) + count($high_questions);

        if ($topic_total == 0)
            continue; // Skip empty topics

        // Lookup or create subject
        $subject = $dbOps->selectOne('tbl_subjects', ['id'], ['subject_name' => $topic_data['subject_category']]);

        if (!$subject) {
            // Create subject if not exists
            $stmt = $conn->prepare("INSERT INTO tbl_subjects (subject_name, status) VALUES (?, 'active')");
            $stmt->execute([$topic_data['subject_category']]);
            $subject_id = $conn->lastInsertId();
        } else {
            $subject_id = $subject['id'];
        }

        // Lookup or create topic
        $topic = $dbOps->selectOne('tbl_topics', ['id'], [
            'topic_name_english' => $topic_data['topic_name_english'],
            'subject_id' => $subject_id
        ]);

        if (!$topic) {
            // Create topic if not exists
            $stmt = $conn->prepare("INSERT INTO tbl_topics (subject_id, topic_name_english, status) VALUES (?, ?, 'active')");
            $stmt->execute([$subject_id, $topic_data['topic_name_english']]);
            $topic_id_ref = $conn->lastInsertId();
        } else {
            $topic_id_ref = $topic['id'];
        }

        // Insert topic into blueprint_topics with foreign keys
        $stmt = $conn->prepare("INSERT INTO tbl_blueprint_topics 
                               (paper_set_id, sr_no, subject_id, topic_id, total_questions) 
                               VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $paper_set_id,
            $topic_data['sr_no'],
            $subject_id,
            $topic_id_ref,
            $topic_total
        ]);

        $blueprint_topic_id = $conn->lastInsertId();

        // Insert low level questions
        foreach ($low_questions as $q_num) {
            if (!is_numeric($q_num))
                continue;
            $stmt = $conn->prepare("INSERT INTO tbl_blueprint_questions 
                                   (blueprint_topic_id, paper_set_id, question_number, difficulty_level, marks) 
                                   VALUES (?, ?, ?, 'low', 1.00)");
            $stmt->execute([$blueprint_topic_id, $paper_set_id, (int) $q_num]);
            $low_count++;
        }

        // Insert medium level questions
        foreach ($medium_questions as $q_num) {
            if (!is_numeric($q_num))
                continue;
            $stmt = $conn->prepare("INSERT INTO tbl_blueprint_questions 
                                   (blueprint_topic_id, paper_set_id, question_number, difficulty_level, marks) 
                                   VALUES (?, ?, ?, 'medium', 1.00)");
            $stmt->execute([$blueprint_topic_id, $paper_set_id, (int) $q_num]);
            $medium_count++;
        }

        // Insert high level questions
        foreach ($high_questions as $q_num) {
            if (!is_numeric($q_num))
                continue;
            $stmt = $conn->prepare("INSERT INTO tbl_blueprint_questions 
                                   (blueprint_topic_id, paper_set_id, question_number, difficulty_level, marks) 
                                   VALUES (?, ?, ?, 'high', 1.00)");
            $stmt->execute([$blueprint_topic_id, $paper_set_id, (int) $q_num]);
            $high_count++;
        }

        $total_questions += $topic_total;
    }

    // Update paper set counts
    $stmt = $conn->prepare("UPDATE tbl_paper_sets 
                           SET total_questions = ?, 
                               low_level_count = ?, 
                               medium_level_count = ?, 
                               high_level_count = ? 
                           WHERE id = ?");
    $stmt->execute([$total_questions, $low_count, $medium_count, $high_count, $paper_set_id]);

    $conn->commit();

    // Clean up temp file if exists
    if (isset($_SESSION['blueprint_preview']['file_path'])) {
        $temp_file = $_SESSION['blueprint_preview']['file_path'];
        if (file_exists($temp_file)) {
            unlink($temp_file);
        }
    }

    // Clear session data
    unset($_SESSION['blueprint_preview']);

    set_flash_message('success', "Blueprint uploaded successfully! Total: $total_questions questions (Low: $low_count, Medium: $medium_count, High: $high_count)");
    header('Location: ' . BASE_URL . '/modules/test-management/blueprint.php?paper_set_id=' . $paper_set_id);
    exit;
} catch (PDOException $e) {
    $conn->rollBack();
    set_flash_message('error', 'Error saving blueprint: ' . $e->getMessage());
    header('Location: ' . BASE_URL . '/modules/test-management/blueprint-preview.php');
    exit;
}