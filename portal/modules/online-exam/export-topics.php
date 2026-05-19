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

// Fetch all topics with full hierarchy info
$topics = $conn->query("SELECT t.topic_name_english, c.chapter, s.subject_name, std.stdtext 
                      FROM tbl_topics t 
                      LEFT JOIN tbl_chapters c ON t.chapter_id = c.chpid 
                      LEFT JOIN tbl_subjects s ON t.subject_id = s.id 
                      LEFT JOIN standard std ON s.standard_id = std.stdid
                      ORDER BY std.stdid ASC, s.subject_name ASC, c.chapter ASC, t.topic_name_english ASC")->fetchAll(PDO::FETCH_ASSOC);

// Headers for download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=topics_export_' . date('Y-m-d') . '.csv');

// Create a file pointer connected to the output stream
$output = fopen('php://output', 'w');

// Output the column headings
fputcsv($output, ['Standard', 'Subject', 'Chapter', 'Topic Name']);

// Loop over the rows, outputting them
foreach ($topics as $row) {
    fputcsv($output, [
        $row['stdtext'] ?? 'N/A',
        $row['subject_name'] ?? 'N/A',
        $row['chapter'] ?? 'General',
        $row['topic_name_english']
    ]);
}

fclose($output);
exit();
