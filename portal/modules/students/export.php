<?php
/**
 * Student Export Handler (Excel / PDF)
 * Respects applied filters and exports all matching records
 */

require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once __DIR__ . '/../../common/api_client.php';
require_once PORTAL_GLOBALVARIABLE;
require_once OPERATION_FILE;
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../common/security_output.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Check access
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_COUNSELLOR) && !hasRole(ROLE_ACCOUNTANT) && !hasRole(ROLE_ESTABLISHMENT) && !hasRole(ROLE_RECEPTION)) {
    die("Unauthorized access");
}

set_time_limit(300); // 5 minutes max for large exports

$api = new APIClient();

// Determine current view
$currentView = $_GET['view'] ?? 'all';
if (!in_array($currentView, ['all', 'registered', 'enrolled'])) {
    $currentView = 'all';
}

$format = $_GET['format'] ?? 'excel';
if (!in_array($format, ['excel', 'pdf'])) {
    $format = 'excel';
}

// Get filters from session
$sessionKey = "students_{$currentView}_filters";
$filters = $_SESSION[$sessionKey] ?? [];

// Force export flag to bypass pagination
$filters['export'] = 1;

// Map views to API endpoints
$endpoints = [
    'all' => 'students/list',
    'registered' => 'students/registered',
    'enrolled' => 'students/enrolled'
];

$response = $api->get($endpoints[$currentView], $filters);

$students = [];
if ($response && isset($response['success']) && $response['success']) {
    $students = $response['data']['students'] ?? [];
} else {
    die("Failed to load students data for export");
}

if (empty($students)) {
    die("No records found to export");
}

// Generate File
$filename = "Students_" . ucfirst($currentView) . "_" . date('Ymd_His');

if ($format === 'excel') {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Set Header Row
    $headers = ['ID', 'Full Name', 'Mobile', 'Email', 'Gender', 'School', 'Board', 'Standard', 'Group', 'Medium', 'Hostel', 'Transport', 'Aadhaar Number', 'Campus', 'Father\'s Name', 'Date of Birth', 'Counsellor Name', 'Admission Date'];
    if ($currentView === 'enrolled') {
        $headers[] = 'Division';
        $headers[] = 'Roll No';
        $headers[] = 'Fees Paid';
        $headers[] = 'Fees Pending';
    } else {
        $headers[] = 'Academic Year';
        $headers[] = 'Status';
    }
    
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . '1', $header);
        $sheet->getStyle($col . '1')->getFont()->setBold(true);
        $sheet->getColumnDimension($col)->setAutoSize(true);
        $col++;
    }
    
    // Add Data
    $rowNum = 2;
    foreach ($students as $row) {
        $sheet->setCellValue('A' . $rowNum, $row['id'] ?? $row['registration_id']);
        $sheet->setCellValue('B' . $rowNum, $row['full_name']);
        $sheet->setCellValue('C' . $rowNum, $row['mob'] ?? $row['phone'] ?? '');
        $sheet->setCellValue('D' . $rowNum, $row['email'] ?? '');
        $sheet->setCellValue('E' . $rowNum, $row['gender'] ?? '');
        $sheet->setCellValue('F' . $rowNum, $row['school_name'] ?? $row['school'] ?? '');
        $sheet->setCellValue('G' . $rowNum, $row['board_name'] ?? $row['board'] ?? '');
        $sheet->setCellValue('H' . $rowNum, $row['course_name'] ?? $row['course'] ?? '');
        $sheet->setCellValue('I' . $rowNum, $row['group_name'] ?? '');
        $sheet->setCellValue('J' . $rowNum, $row['medium_name'] ?? '');
        $sheet->setCellValue('K' . $rowNum, $row['hostel_required'] ?? 'No');
        $sheet->setCellValue('L' . $rowNum, $row['transport_required'] ?? 'No');
        $sheet->setCellValue('M' . $rowNum, $row['aadhaar'] ?? '');
        $sheet->setCellValue('N' . $rowNum, $row['campus_name'] ?? '');
        $sheet->setCellValue('O' . $rowNum, $row['fathers_name'] ?? '');
        $sheet->setCellValue('P' . $rowNum, $row['dob'] ?? '');
        $sheet->setCellValue('Q' . $rowNum, $row['counsellor_name'] ?? '');
        
        $admissionDate = $row['admission_confirmed_date'] ?? $row['registration_date'] ?? $row['created_at'] ?? '';
        $sheet->setCellValue('R' . $rowNum, $admissionDate);
        
        $nextCol = 'S';
        if ($currentView === 'enrolled') {
            $sheet->setCellValue($nextCol++ . $rowNum, $row['division_name'] ?? '-');
            $sheet->setCellValue($nextCol++ . $rowNum, $row['roll_no'] ?? '-');
            $sheet->setCellValue($nextCol++ . $rowNum, $row['fees_paid'] ?? 0);
            $sheet->setCellValue($nextCol++ . $rowNum, $row['fees_pending'] ?? 0);
        } else {
            $sheet->setCellValue($nextCol++ . $rowNum, $row['academic_year'] ?? '-');
            $status = 'Pending';
            if (!empty($row['admission_confirmed'])) $status = 'Confirmed';
            if ($currentView === 'registered') $status = 'Pending Token';
            $sheet->setCellValue($nextCol++ . $rowNum, $status);
        }
        $rowNum++;
    }
    
    // Output
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
    header('Cache-Control: max-age=0');
    
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
} else if ($format === 'pdf') {
    // Generate PDF using TCPDF
    // TCPDF might not be namespaced depending on version, usually it's global
    if (!class_exists('TCPDF')) {
        die("TCPDF library not found");
    }
    
    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetTitle('Students List - ' . ucfirst($currentView));
    $pdf->SetMargins(10, 10, 10);
    $pdf->AddPage();
    
    $html = '<h2>' . ucfirst($currentView) . ' Students List</h2>';
    $html .= '<table border="1" cellpadding="5" cellspacing="0" class="css-export-ecfba0">';
    $html .= '<tr class="css-export-c8474b">';
    $html .= '<th>ID</th><th>Name</th><th>Mobile</th><th>Aadhaar</th><th>Campus</th><th>Father\'s Name</th><th>DOB</th><th>Counsellor</th><th>Adm Date</th><th>Course</th><th>Board</th>';
    if ($currentView === 'enrolled') {
        $html .= '<th>Div</th><th>Roll</th><th>Paid</th><th>Pending</th>';
    } else {
        $html .= '<th>Year</th><th>Status</th>';
    }
    $html .= '</tr>';
    
    foreach ($students as $row) {
        $html .= '<tr>';
        $html .= '<td>' . ($row['id'] ?? $row['registration_id']) . '</td>';
        $html .= '<td>' . htmlspecialchars($row['full_name'] ?? '') . '</td>';
        $html .= '<td>' . ($row['mob'] ?? $row['phone'] ?? '') . '</td>';
        $html .= '<td>' . ($row['aadhaar'] ?? '') . '</td>';
        $html .= '<td>' . ($row['campus_name'] ?? '') . '</td>';
        $html .= '<td>' . ($row['fathers_name'] ?? '') . '</td>';
        $html .= '<td>' . ($row['dob'] ?? '') . '</td>';
        $html .= '<td>' . ($row['counsellor_name'] ?? '') . '</td>';
        
        $admissionDate = $row['admission_confirmed_date'] ?? $row['registration_date'] ?? $row['created_at'] ?? '';
        $html .= '<td>' . $admissionDate . '</td>';
        
        $html .= '<td>' . htmlspecialchars($row['course_name'] ?? $row['course'] ?? '') . '</td>';
        $html .= '<td>' . htmlspecialchars($row['board_name'] ?? $row['board'] ?? '') . '</td>';
        
        if ($currentView === 'enrolled') {
            $html .= '<td>' . ($row['division_name'] ?? '-') . '</td>';
            $html .= '<td>' . ($row['roll_no'] ?? '-') . '</td>';
            $html .= '<td>' . ($row['fees_paid'] ?? 0) . '</td>';
            $html .= '<td>' . ($row['fees_pending'] ?? 0) . '</td>';
        } else {
            $html .= '<td>' . ($row['academic_year'] ?? '-') . '</td>';
            $status = 'Pending';
            if (!empty($row['admission_confirmed'])) $status = 'Confirmed';
            if ($currentView === 'registered') $status = 'Pending Token';
            $html .= '<td>' . $status . '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</table>';
    
    $pdf->writeHTML($html, true, false, true, false, '');
    
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
    $pdf->Output($filename . '.pdf', 'D');
    exit;
}
