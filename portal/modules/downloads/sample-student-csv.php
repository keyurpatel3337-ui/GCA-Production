<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php'; require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/index.php');
    exit();
}

// Fetch valid boards from database
try {
    $boards = $dbOps->select('tbl_boards', ['board_name'], ['is_active' => 1], 'board_name ASC');
    $valid_boards = array_column($boards, 'board_name');
    $board_options = implode('/', $valid_boards);
} catch (PDOException $e) {
    // Fallback to hardcoded values if database query fails
    $board_options = 'GSEB/CBSE/ICSE';
    $valid_boards = ['GSEB', 'CBSE', 'ICSE'];
}

// Define column headers for the CSV template
$headers = [
    'Surname*',
    'Student Name*',
    'Father Name*',
    'Date of Birth* (YYYY-MM-DD)',
    'Gender* (Male/Female/Other)',
    'Board* (' . $board_options . ')',
    'Medium* (Gujarati/English)',
    'Group* (1=Science/2=Commerce/3=Arts)',
    'Mobile No* (10 digits)',
    'Alternate Mobile No (10 digits)',
    'Aadhaar Card Number* (12 digits)',
    'School Name* (Std.10th)',
    'School Address* (Std.10th)',
    'Residence Address*',
    'District*',
    'Father Full Name*',
    'Father Education*',
    'Father Occupation*',
    'Office Address*',
    'Hostel Required* (Yes/No)',
    'Password*',
    'Status* (1=Active/0=Inactive)'
];

// Sample data row (use first board from database)
$sampleData = [
    'Patel',
    'Raj',
    'Kumar',
    '2005-05-15',
    'Male',
    !empty($valid_boards) ? $valid_boards[0] : 'GSEB',
    'English',
    '1',
    '9876543210',
    '9876543211',
    '123456789012',
    'ABC High School',
    '123 School Street, City',
    '456 Home Street, City',
    'Ahmedabad',
    'Kumar Patel',
    'Graduate',
    'Business',
    '789 Office Road, City',
    'No',
    'password123',
    '1'
];

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="student_bulk_upload_template_' . date('Y-m-d') . '.csv"');
header('Cache-Control: max-age=0');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// Write headers
fputcsv($output, $headers);

// Write sample data
fputcsv($output, $sampleData);

// Write 5 empty rows for data entry
for ($i = 0; $i < 5; $i++) {
    fputcsv($output, array_fill(0, count($headers), ''));
}

// Write instructions as comments
fputcsv($output, ['']);
fputcsv($output, ['### INSTRUCTIONS ###']);
fputcsv($output, ['1. All fields marked with * are mandatory']);
fputcsv($output, ['2. Date of Birth format: YYYY-MM-DD (e.g., 2005-05-15)']);
fputcsv($output, ['3. Gender: Male, Female, or Other']);
fputcsv($output, ['4. Board: ' . $board_options]);
fputcsv($output, ['5. Medium: Gujarati or English']);
fputcsv($output, ['6. Group: A or B']);
fputcsv($output, ['7. Mobile No: 10 digit number without spaces or special characters']);
fputcsv($output, ['8. Aadhaar: 12 digit number without spaces']);
fputcsv($output, ['9. Hostel Required: Yes or No']);
fputcsv($output, ['10. Status: 1 for Active, 0 for Inactive']);
fputcsv($output, ['11. Do not modify the header row']);
fputcsv($output, ['12. Delete the sample data row before uploading']);

fclose($output);
exit();
