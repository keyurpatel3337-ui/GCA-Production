<?php
ob_start();
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once PORTAL_GLOBALVARIABLE;
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once DB_CONNECT_FILE;
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

$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Student Template');
$rowNum = 1;

$sheet->fromArray($headers, null, 'A' . $rowNum++);
$sheet->fromArray($sampleData, null, 'A' . $rowNum++);

for ($i = 0; $i < 5; $i++) {
    $sheet->fromArray(array_fill(0, count($headers), ''), null, 'A' . $rowNum++);
}

$sheet->fromArray([''], null, 'A' . $rowNum++);
$sheet->fromArray(['### INSTRUCTIONS ###'], null, 'A' . $rowNum++);
$sheet->fromArray(['1. All fields marked with * are mandatory'], null, 'A' . $rowNum++);
$sheet->fromArray(['2. Date of Birth format: YYYY-MM-DD (e.g., 2005-05-15)'], null, 'A' . $rowNum++);
$sheet->fromArray(['3. Gender: Male, Female, or Other'], null, 'A' . $rowNum++);
$sheet->fromArray(['4. Board: ' . $board_options], null, 'A' . $rowNum++);
$sheet->fromArray(['5. Medium: Gujarati or English'], null, 'A' . $rowNum++);
$sheet->fromArray(['6. Group: A or B'], null, 'A' . $rowNum++);
$sheet->fromArray(['7. Mobile No: 10 digit number without spaces or special characters'], null, 'A' . $rowNum++);
$sheet->fromArray(['8. Aadhaar: 12 digit number without spaces'], null, 'A' . $rowNum++);
$sheet->fromArray(['9. Hostel Required: Yes or No'], null, 'A' . $rowNum++);
$sheet->fromArray(['10. Status: 1 for Active, 0 for Inactive'], null, 'A' . $rowNum++);
$sheet->fromArray(['11. Do not modify the header row'], null, 'A' . $rowNum++);
$sheet->fromArray(['12. Delete the sample data row before uploading'], null, 'A' . $rowNum++);

// Clear any existing output buffering to prevent headers already sent or stray output
while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="student_bulk_upload_template_' . date('Y-m-d') . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$writer->save('php://output');
exit();
