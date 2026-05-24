<?php
ob_start();
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once PORTAL_GLOBALVARIABLE;
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

// Only Principle and Super Admin can download this template
if (!hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN)) {
    die('Unauthorized access.');
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Fee Config Template');

// Define column headers
$headers = [
    'academic_year',
    'term',
    'course_name',
    'school_code',
    'medium_name',
    'group_name',
    'school_fee',
    'school_fee_label',
    'school_fee_gst',
    'trust_facilities_fee',
    'trust_fee_label',
    'trust_fee_gst',
    'tuition_fee_part1',
    'token_fee_label',
    'token_fee_gst',
    'tuition_fee_part2',
    'tuition_fee_label',
    'tuition_fee_gst',
    'token_fee',
    'total_fees',
    'number_of_installments',
    'is_active'
];

// Set headers in Row 1
$sheet->fromArray($headers, null, 'A1');

// Add sample data in Row 2
$sampleData = [
    '2026-2027',
    'Semester 1',
    '11th Gujarati',
    'GM',
    'Gujarati',
    'A Group (Board)',
    '15000.00',
    'GHSS',
    '0',
    '15000.00',
    'MST',
    '0',
    '10000.00',
    'GCA',
    '1',
    '45000.00',
    'GCA',
    '1',
    '11800.00',
    '94900.00',
    '1',
    '1'
];
$sheet->fromArray($sampleData, null, 'A2');

// Basic Styling
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '4F81BD']
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
        ],
    ],
];
$sheet->getStyle('A1:V1')->applyFromArray($headerStyle);
$sheet->getStyle('A2:V2')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

// Auto-size columns
foreach (range('A', $sheet->getHighestColumn()) as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Clear any existing output buffering to prevent headers already sent or stray output
while (ob_get_level()) {
    ob_end_clean();
}

// Download Headers
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="fee_config_template_' . date('Y-m-d') . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
