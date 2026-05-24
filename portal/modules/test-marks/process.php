<?php

/**
 * Test Marks Module - Process Actions
 * Handle add, edit, delete operations
 */

require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;

// Check permissions
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_COUNSELLOR)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$action = $_POST['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'add':
        addTestMarks();
        break;
    case 'edit':
        editTestMarks();
        break;
    case 'delete':
        deleteTestMarks();
        break;
    default:
        set_flash_message('error', 'Invalid action.');
        header('Location: index.php');
        exit;
}

function addTestMarks()
{
    global $conn;

    try {
        $student_id = $_POST['student_id'] ?? null;
        $enrollment_id = $_POST['enrollment_id'] ?? null;
        $test_type = $_POST['test_type'] ?? 'omr_mcq';
        $test_name = $_POST['test_name'] ?? '';
        $test_date = $_POST['test_date'] ?? date('Y-m-d');
        $status = $_POST['status'] ?? 'evaluated';
        $remarks = $_POST['remarks'] ?? '';

        if (!$student_id || !$test_name) {
            set_flash_message('error', 'Student and Test Name are required.');
            header('Location: add.php');
            exit;
        }

        // Initialize variables
        $paper_set_id = null;
        $total_marks = 100;
        $obtained_marks = 0;
        $total_questions = null;
        $correct_answers = null;
        $wrong_answers = null;
        $unanswered = null;
        $low_level_correct = null;
        $low_level_wrong = null;
        $medium_level_correct = null;
        $medium_level_wrong = null;
        $high_level_correct = null;
        $high_level_wrong = null;
        $subject_marks_json = null;

        if ($test_type === 'omr_mcq') {
            // OMR MCQ specific
            $paper_set_id = $_POST['paper_set_id'] ?: null;
            $total_questions = intval($_POST['total_questions'] ?? 100);
            $total_marks = floatval($_POST['total_marks'] ?? 100);
            $correct_answers = intval($_POST['correct_answers'] ?? 0);
            $wrong_answers = intval($_POST['wrong_answers'] ?? 0);
            $unanswered = $total_questions - $correct_answers - $wrong_answers;

            // Calculate obtained marks
            $marks_per_question = $total_marks / $total_questions;
            $obtained_marks = $correct_answers * $marks_per_question;

            // Difficulty levels
            $low_level_correct = intval($_POST['low_level_correct'] ?? 0);
            $low_level_wrong = intval($_POST['low_level_wrong'] ?? 0);
            $medium_level_correct = intval($_POST['medium_level_correct'] ?? 0);
            $medium_level_wrong = intval($_POST['medium_level_wrong'] ?? 0);
            $high_level_correct = intval($_POST['high_level_correct'] ?? 0);
            $high_level_wrong = intval($_POST['high_level_wrong'] ?? 0);
        } else {
            // Descriptive specific
            $total_marks = floatval($_POST['desc_total_marks'] ?? 100);
            $obtained_marks = floatval($_POST['desc_obtained_marks'] ?? 0);
            $subject_marks_json = $_POST['subject_marks_json'] ?? '[]';
        }

        // Get current user ID
        $created_by = $_SESSION['user_id'] ?? 1;

        $dbOps = new DatabaseOperations();
        $result = $dbOps->insert('tbl_test_marks', [
            'student_id' => $student_id,
            'enrollment_id' => $enrollment_id ?: null,
            'test_type' => $test_type,
            'test_name' => $test_name,
            'test_date' => $test_date,
            'paper_set_id' => $paper_set_id,
            'total_marks' => $total_marks,
            'obtained_marks' => $obtained_marks,
            'total_questions' => $total_questions,
            'correct_answers' => $correct_answers,
            'wrong_answers' => $wrong_answers,
            'unanswered' => $unanswered,
            'low_level_correct' => $low_level_correct,
            'low_level_wrong' => $low_level_wrong,
            'medium_level_correct' => $medium_level_correct,
            'medium_level_wrong' => $medium_level_wrong,
            'high_level_correct' => $high_level_correct,
            'high_level_wrong' => $high_level_wrong,
            'subject_marks_json' => $subject_marks_json,
            'remarks' => $remarks,
            'status' => $status,
            'created_by' => $created_by
        ]);

        if ($result) {
            set_flash_message('success', 'Test marks added successfully!');
            header('Location: index.php');
            exit;
        } else {
            throw new Exception('Failed to insert test marks');
        }
    } catch (Exception $e) {
        set_flash_message('error', 'Database error: ' . $e->getMessage());
        header('Location: add.php');
        exit;
    }
}

function editTestMarks()
{
    global $conn;

    try {
        $id = $_POST['id'] ?? null;
        $student_id = $_POST['student_id'] ?? null;
        $enrollment_id = $_POST['enrollment_id'] ?? null;
        $test_type = $_POST['test_type'] ?? 'omr_mcq';
        $test_name = $_POST['test_name'] ?? '';
        $test_date = $_POST['test_date'] ?? date('Y-m-d');
        $status = $_POST['status'] ?? 'evaluated';
        $remarks = $_POST['remarks'] ?? '';

        if (!$id || !$student_id || !$test_name) {
            set_flash_message('error', 'Invalid request. Required fields missing.');
            header('Location: index.php');
            exit;
        }

        // Initialize variables
        $paper_set_id = null;
        $total_marks = 100;
        $obtained_marks = 0;
        $total_questions = null;
        $correct_answers = null;
        $wrong_answers = null;
        $unanswered = null;
        $low_level_correct = null;
        $low_level_wrong = null;
        $medium_level_correct = null;
        $medium_level_wrong = null;
        $high_level_correct = null;
        $high_level_wrong = null;
        $subject_marks_json = null;

        if ($test_type === 'omr_mcq') {
            $paper_set_id = $_POST['paper_set_id'] ?: null;
            $total_questions = intval($_POST['total_questions'] ?? 100);
            $total_marks = floatval($_POST['total_marks'] ?? 100);
            $correct_answers = intval($_POST['correct_answers'] ?? 0);
            $wrong_answers = intval($_POST['wrong_answers'] ?? 0);
            $unanswered = $total_questions - $correct_answers - $wrong_answers;

            $marks_per_question = $total_marks / $total_questions;
            $obtained_marks = $correct_answers * $marks_per_question;

            $low_level_correct = intval($_POST['low_level_correct'] ?? 0);
            $low_level_wrong = intval($_POST['low_level_wrong'] ?? 0);
            $medium_level_correct = intval($_POST['medium_level_correct'] ?? 0);
            $medium_level_wrong = intval($_POST['medium_level_wrong'] ?? 0);
            $high_level_correct = intval($_POST['high_level_correct'] ?? 0);
            $high_level_wrong = intval($_POST['high_level_wrong'] ?? 0);
        } else {
            $total_marks = floatval($_POST['desc_total_marks'] ?? 100);
            $obtained_marks = floatval($_POST['desc_obtained_marks'] ?? 0);
            $subject_marks_json = $_POST['subject_marks_json'] ?? '[]';
        }

        $dbOps = new DatabaseOperations();
        $result = $dbOps->update('tbl_test_marks', [
            'student_id' => $student_id,
            'enrollment_id' => $enrollment_id ?: null,
            'test_type' => $test_type,
            'test_name' => $test_name,
            'test_date' => $test_date,
            'paper_set_id' => $paper_set_id,
            'total_marks' => $total_marks,
            'obtained_marks' => $obtained_marks,
            'total_questions' => $total_questions,
            'correct_answers' => $correct_answers,
            'wrong_answers' => $wrong_answers,
            'unanswered' => $unanswered,
            'low_level_correct' => $low_level_correct,
            'low_level_wrong' => $low_level_wrong,
            'medium_level_correct' => $medium_level_correct,
            'medium_level_wrong' => $medium_level_wrong,
            'high_level_correct' => $high_level_correct,
            'high_level_wrong' => $high_level_wrong,
            'subject_marks_json' => $subject_marks_json,
            'remarks' => $remarks,
            'status' => $status
        ], ['id' => $id]);

        if ($result !== false) {
            set_flash_message('success', 'Test marks updated successfully!');
            header('Location: index.php');
            exit;
        } else {
            throw new Exception('Failed to update test marks');
        }
    } catch (Exception $e) {
        set_flash_message('error', 'Error: ' . $e->getMessage());
        header('Location: edit.php?id=' . ($_POST['id'] ?? ''));
        exit;
    }
}

function deleteTestMarks()
{
    try {
        $id = $_POST['id'] ?? null;

        if (!$id) {
            set_flash_message('error', 'Invalid request.');
            header('Location: index.php');
            exit;
        }

        $dbOps = new DatabaseOperations();
        $result = $dbOps->delete('tbl_test_marks', ['id' => $id]);

        if ($result !== false) {
            set_flash_message('success', 'Test marks deleted successfully!');
            header('Location: index.php');
            exit;
        } else {
            throw new Exception('Failed to delete test marks');
        }
    } catch (Exception $e) {
        set_flash_message('error', 'Error: ' . $e->getMessage());
        header('Location: index.php');
        exit;
    }
}
