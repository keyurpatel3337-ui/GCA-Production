<?php
// print-result-pdf.php

require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once '../../vendor/autoload.php';

// Include controller for data fetching logic
require_once dirname(dirname(dirname(__DIR__))) . '/counselling-backend/controllers/student-portal/my-results_controller.php';
require_once dirname(dirname(dirname(__DIR__))) . '/counselling-backend/controllers/results/ResultCalculator.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/format_helper.php';

// Check access
$is_admin = hasRole(ROLE_SUPER_ADMIN) || hasRole(ROLE_PRINCIPLE) || hasRole(ROLE_COUNSELLOR) || hasRole(ROLE_ACCOUNTANT);
$is_student = isset($_SESSION['is_student_login']) && $_SESSION['is_student_login'] === true;
$is_parent = isset($_SESSION['is_parent_login']) && $_SESSION['is_parent_login'] === true;

if (!$is_admin && !$is_student && !$is_parent) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

if ($is_admin) {
    $student_id = $_GET['student_id'] ?? 0;
    if (!$student_id)
        die("Student ID is required for admin view.");
} else {
    $student_id = $_SESSION['student_id'];
}

$academic_year_id = $_GET['academic_year_id'] ?? 0;

if (!$academic_year_id) {
    die("Academic Year ID is required.");
}

// Fetch Student Details
$dbOps = new DatabaseOperations();
$student = $dbOps->customSelectOne("SELECT s.*, 
                                     b.board_name, m.medium_name, g.group_name, c.course_name,
                                     ay.year_name, e.roll_no as roll_number, e.current_term_id
                                     FROM tbl_gm_std_registration s
                                     LEFT JOIN tbl_boards b ON s.board_id = b.id
                                     LEFT JOIN tbl_medium m ON s.medium_id = m.id
                                     LEFT JOIN tbl_group g ON s.group_id = g.id
                                     LEFT JOIN tbl_courses c ON s.course_id = c.id
                                     LEFT JOIN tbl_academic_years ay ON ay.id = ?
                                     LEFT JOIN tbl_enrolled_students e ON s.id = e.registration_id
                                     WHERE s.id = ?", [$academic_year_id, $student_id]);

if (!$student) {
    die("Student not found.");
}

// Fetch Consolidated Marks
$marks_data = getConsolidatedMarksForPDF($dbOps, $student_id, $academic_year_id);

// --- Result Calculation ---
$calculator = new ResultCalculator();
$calcData = [];
if (!empty($marks_data)) {
    foreach ($marks_data as $data) {
        $sid = $data['subject_id'];

        if ($data['subject_type'] == 'Theory') {
            $calcData[$sid] = [
                'First Exam' => ['obtained' => (float) ($data['first_exam'] ?? 0), 'max' => 50],
                'Second Exam' => ['obtained' => (float) ($data['second_exam'] ?? 0), 'max' => 50],
                'Annual Exam' => ['obtained' => (float) ($data['annual_exam'] ?? 0), 'max' => 80],
                'Internal' => ['obtained' => (float) ($data['internal_mark'] ?? 0), 'max' => 20],
                'grace_marks' => (float) ($data['grace_marks'] ?? 0)
            ];
        } else {
            // Practical (Use unique key to prevent overwrite)
            $calcData[$sid . '_prac'] = [
                'Practical' => ['obtained' => (float) ($data['obtained_marks'] ?? 0), 'max' => 50]
            ];
        }
    }
}
$calculationResult = $calculator->calculateResult($calcData);

// --- PDF GENERATION ---

// Extend TCPDF to create custom Header and Footer
class MYPDF extends TCPDF
{
    // Page header
    public function Header()
    {
        // Simple header or leaving blank as per image (image has no big header, just a border)
        // Set font
        $this->SetFont('helvetica', 'B', 12);
    }
}

// create new PDF document
$pdf = new MYPDF('P', 'mm', 'A4', true, 'UTF-8', false);

// set document information
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('GCA');
$pdf->SetTitle('Mark Sheet - ' . $student['student_name']);

// set default header data
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// set default monospaced font
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

// set margins
$pdf->SetMargins(10, 10, 10);

// set auto page breaks
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

// set image scale factor
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// add a page
$pdf->AddPage();

// Borders
$pdf->Rect(5, 5, 200, 287, 'D'); // Outer border

// Set Font
$pdf->SetFont('helvetica', '', 10);

// --- Header Table ---
$html = '
<style>
    table {
        border-collapse: collapse;
        width: 100%;
        font-family: helvetica;
        font-size: 10pt;
    }
    th, td {
        border: 1px solid black;
        padding: 5px;
        text-align: center;
    }
    .bold {
        font-weight: bold;
    }
    .left {
        text-align: left;
    }
    .no-border {
        border: none;
    }
</style>

<br><br><br><br><br><br><br><br>

<table cellpadding="4">
    <tr>
        <td width="15%" class="bold">Year ' . $student['year_name'] . '</td>
        <td width="10%" class="bold">Group</td>
        <td width="25%" class="bold">' . ($student['group_name'] ?? 'A Group (Board/JEE Main/Advanced)') . '</td>
        <td width="20%" class="bold">School Index No.</td>
        <td width="30%" class="bold">12.118</td>
    </tr>
    <tr>
        <td width="15%" class="bold">Student Name</td>
        <td width="65%" class="left bold" colspan="2">' . strtoupper(($student['surname'] ?? '') . ' ' . ($student['student_name'] ?? '') . ' ' . ($student['fathers_name'] ?? '')) . '</td>
        <td width="10%" class="bold">Roll No.</td>
        <td width="10%" class="bold">' . ($student['roll_number'] ?? '-') . '</td>
    </tr>
    <tr>
        <td width="15%" class="bold">G.R. No.</td>
        <td width="15%" class="bold">' . ($student['gr_no'] ?? '-') . '</td>
        <td width="20%" class="bold">Birth Date</td>
        <td width="20%" class="bold">' . ($student['dob'] ? date('d-m-Y', strtotime($student['dob'])) : '-') . '</td>
        <td width="15%" class="bold">Seat No.</td>
        <td width="15%" class="bold">' . ($student['seat_no'] ?? '-') . '</td>
    </tr>
</table>
<br><br>
';

$pdf->writeHTML($val = $html, $ln = true, $fill = false, $reseth = false, $cell = false, $align = '');

// --- Marks Table ---
$html = '
<table cellpadding="5" border="1" style="border-collapse:collapse;">
    <thead>
        <tr style="background-color:#f2f2f2;">
            <th width="5%" rowspan="2" align="center" valign="middle" class="bold">Sr.</th>
            <th width="28%" rowspan="2" align="left" valign="middle" class="bold">Subject</th>
            <th width="8%" align="center" valign="middle" class="bold">First<br>Exam</th>
            <th width="8%" align="center" valign="middle" class="bold">Second<br>Exam</th>
            <th width="8%" align="center" valign="middle" class="bold">Annual<br>Exam</th>
            <th width="8%" align="center" valign="middle" class="bold">Inter.<br>Mark</th>
            <th width="9%" align="center" valign="middle" class="bold">Total<br>Mark</th>
            <th width="9%" align="center" valign="middle" class="bold">Obtain<br>Mark</th>
            <th width="8%" align="center" valign="middle" class="bold">Grace</th>
            <th width="9%" align="center" valign="middle" class="bold">Grade</th>
        </tr>
        <tr style="background-color:#f2f2f2;">
            <th align="center" valign="middle">50</th>
            <th align="center" valign="middle">50</th>
            <th align="center" valign="middle">80</th>
            <th align="center" valign="middle">20</th>
            <th align="center" valign="middle">200</th>
            <th align="center" valign="middle">100</th>
            <th align="center" valign="middle">-</th>
            <th align="center" valign="middle">-</th>
        </tr>
    </thead>
    <tbody>';

$sr = 1;

$total_obtained_agg = 0;
$total_max_agg = 0;

if (!empty($marks_data)) {
    foreach ($marks_data as $data) {
        $sid = $data['subject_id'];
        // Use the correct key for practical subjects if it was modified in $calcData
        $subjResultKey = ($data['subject_type'] == 'Theory') ? $sid : $sid . '_prac';
        $subjResult = $calculationResult['subjects'][$subjResultKey] ?? [];
        $grade = $subjResult['grade'] ?? '-';
        $grace = $data['grace_marks'] ?? 0;

        if ($data['subject_type'] == 'Theory') {
            $first = $data['first_exam'];
            $second = $data['second_exam'];
            $annual = $data['annual_exam'];
            $internal = $data['internal_mark'];

            // The total_obtained from ResultCalculator is the sum of raw marks (out of 200)
            $raw_sum = ($subjResult['total_obtained'] ?? 0); // Sum of raw marks from calculator
            $obtained_display = ($raw_sum / 2) + $grace; // Scaled to 100 + grace

            $html .= '
            <tr>
                <td width="5%" align="center" valign="middle">' . $sr++ . '</td>
                <td width="28%" align="left" valign="middle">' . htmlspecialchars($data['subject_name'] ?? '') . '</td>
                <td width="8%" align="center" valign="middle">' . formatIndianCurrency($first, false) . '</td>
                <td width="8%" align="center" valign="middle">' . formatIndianCurrency($second, false) . '</td>
                <td width="8%" align="center" valign="middle">' . formatIndianCurrency($annual, false) . '</td>
                <td width="8%" align="center" valign="middle">' . formatIndianCurrency($internal, false) . '</td>
                <td width="9%" align="center" valign="middle">' . formatIndianCurrency($raw_sum, false) . '</td>
                <td width="9%" align="center" valign="middle"><b>' . formatIndianCurrency($obtained_display, false) . '</b></td>
                <td width="8%" align="center" valign="middle">' . ($grace > 0 ? $grace : '-') . '</td>
                <td width="9%" align="center" valign="middle">' . $grade . '</td>
            </tr>';

            $total_obtained_agg += $obtained_display;
            $total_max_agg += 100;

        } else {
            // Practical
            $practical_marks = $data['obtained_marks'];
            // Practical is Out of 50. No scaling.

            $html .= '
            <tr>
                <td width="5%" align="center" valign="middle">' . $sr++ . '</td>
                <td width="28%" align="left" valign="middle">' . htmlspecialchars($data['subject_name'] ?? '') . ' Prac.</td>
                <td width="40%" colspan="5" align="center" valign="middle" style="letter-spacing:1px;">Out of (50)</td>
                <td width="9%" align="center" valign="middle"><b>' . formatIndianCurrency($practical_marks, false) . '</b></td>
                <td width="8%" align="center" valign="middle">-</td>
                <td width="9%" align="center" valign="middle">' . $grade . '</td>
            </tr>';

            $total_obtained_agg += $practical_marks;
            $total_max_agg += 50;
        }
    }
} else {
    // Empty rows demo
    for ($i = 1; $i <= 5; $i++) {
        $html .= '<tr><td width="5%" align="center" valign="middle">' . $i . '</td><td width="28%" align="left" valign="middle">Subject ' . $i . '</td><td colspan="8"></td></tr>';
    }
}

$html .= '</tbody></table>';
$html .= '<br><br>';

// Footer
// We use our aggregated totals for the absolute display "Out of X",
// BUT we MUST use ResultCalculator for Percentage, Grade, and Result Status.

$percentage = $calculationResult['overall_percentage'] ?? 0;
$grade = $calculationResult['overall_grade'] ?? '-';
$result_status = $calculationResult['final_result'] ?? '-';


$html .= '
<table cellpadding="5">
    <tr>
        <td width="65%" align="right" class="no-border">Total Obtain Marks (Out of ' . formatIndianCurrency($total_max_agg, false) . ')</td>
        <td width="10%" class="no-border bold">' . formatIndianCurrency($total_obtained_agg, false) . '</td>
        <td width="25%" class="no-border"></td>
    </tr>
</table>

<br><br>
<table cellpadding="5">
    <tr>
        <td width="25%" class="no-border">Percentage: ' . formatIndianCurrency($percentage) . '%</td>
        <td width="25%" class="no-border">Grade: ' . $grade . '</td>
        <td width="25%" class="no-border">Result: ' . $result_status . '</td>
        <td width="25%" class="no-border" align="right">Authorized Signature</td>
    </tr>
</table>
';

$pdf->writeHTML($html, true, false, true, false, '');

// Output PDF
$filename = 'MarkSheet_' . $student['roll_number'] . '.pdf';
$pdf->Output($filename, 'D');
?>