<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_PATH . 'session_config.php';
require_once PORTAL_GLOBALVARIABLE;

// Check access
if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_COUNSELLOR, ROLE_DEPT_HEAD, ROLE_ASSISTANT_TEACHER])) {
    exit("Access Denied");
}

$type = isset($_GET['type']) ? $_GET['type'] : '';

if (!in_array($type, ['subject', 'chapter', 'topic', 'question'])) {
    exit("Invalid type");
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=sample_' . $type . 's.csv');
$output = fopen('php://output', 'w');

if ($type === 'subject') {
    fputcsv($output, ['Standard', 'Subject Name', 'Status']);
    fputcsv($output, ['11th Science', 'Physics', 'active']);
    fputcsv($output, ['General', 'General Knowledge', 'active']);
} elseif ($type === 'chapter') {
    fputcsv($output, ['Standard', 'Subject', 'Chapter Name']);
    fputcsv($output, ['11th Science', 'Physics', 'Kinematics']);
} elseif ($type === 'topic') {
    fputcsv($output, ['Standard', 'Subject', 'Chapter', 'Topic Name']);
    fputcsv($output, ['11th Science', 'Physics', 'Kinematics', 'Motion in a Straight Line']);
} elseif ($type === 'question') {
    fputcsv($output, ['Standard', 'Subject', 'Chapter', 'Topic', 'Type', 'Level', 'Question', 'Option A', 'Option B', 'Option C', 'Option D', 'Correct', 'Marks']);
    fputcsv($output, ['11th Science', 'Physics', 'Kinematics', 'Motion in a Straight Line', 'MCQ', 'Level A', 'What is speed?', 'Distance/Time', 'Time/Distance', 'Mass*Velocity', 'None', 'A', '1']);
}

fclose($output);
exit();
