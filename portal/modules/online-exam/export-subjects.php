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

// Fetch all subjects with standard info
$subjects = $conn->query("SELECT s.subject_name, s.status, std.stdtext 
                         FROM tbl_subjects s 
                         LEFT JOIN standard std ON s.standard_id = std.stdid 
                         ORDER BY std.stdid ASC, s.subject_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Headers for download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=subjects_export_' . date('Y-m-d') . '.csv');

// Create a file pointer connected to the output stream
$output = fopen('php://output', 'w');

// Output the column headings
fputcsv($output, ['Standard', 'Subject Name', 'Status']);

// Loop over the rows, outputting them
foreach ($subjects as $row) {
    fputcsv($output, [
        $row['stdtext'] ?? 'General / All',
        $row['subject_name'],
        ucfirst($row['status'])
    ]);
}

fclose($output);
exit();
