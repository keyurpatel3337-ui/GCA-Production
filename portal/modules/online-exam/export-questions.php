<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;
require_once PORTAL_PATH . 'session_config.php';
require_once PORTAL_GLOBALVARIABLE;

// Check access
if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_COUNSELLOR, ROLE_DEPT_HEAD, ROLE_ASSISTANT_TEACHER])) {
    exit("Access Denied");
}

// Fetch all questions with full hierarchy info
$sql = "SELECT q.id, q.marks, q.difficulty,
               q.question_text, q.option_a, q.option_b, q.option_c, q.option_d, q.correct_option,
               s.subject_name, qt.type_name, c.chapter as chapter_name, t.topic_name_english as topic_name, std.stdtext 
        FROM tbl_oes_questions q
        LEFT JOIN tbl_subjects s ON q.subject_id = s.id 
        LEFT JOIN tbl_oes_question_types qt ON q.question_type_id = qt.id
        LEFT JOIN chapters c ON q.chapter_id = c.chpid
        LEFT JOIN tbl_topics t ON q.topic_id = t.id
        LEFT JOIN standard std ON q.standard_id = std.stdid
        WHERE q.status = 1
        ORDER BY q.id DESC";

$questions = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Headers for download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=questions_export_' . date('Y-m-d') . '.csv');

// Create a file pointer connected to the output stream
$output = fopen('php://output', 'w');

// Output the column headings
fputcsv($output, ['Standard', 'Subject', 'Chapter', 'Topic', 'Type', 'Level', 'Question', 'Option A', 'Option B', 'Option C', 'Option D', 'Correct', 'Marks']);

// Loop over the rows, outputting them
foreach ($questions as $row) {
    fputcsv($output, [
        $row['stdtext'] ?? 'N/A',
        $row['subject_name'] ?? 'N/A',
        $row['chapter_name'] ?? 'General',
        $row['topic_name'] ?? 'General',
        $row['type_name'] ?? 'MCQ',
        $row['difficulty'],
        strip_tags($row['question_text']),
        strip_tags($row['option_a']),
        strip_tags($row['option_b']),
        strip_tags($row['option_c']),
        strip_tags($row['option_d']),
        $row['correct_option'],
        $row['marks']
    ]);
}

fclose($output);
exit();
