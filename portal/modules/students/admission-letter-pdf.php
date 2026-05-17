<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php'; require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once OPERATION_FILE;
require_once '../../vendor/autoload.php';

use setasign\Fpdi\Fpdi;

// Ensure no output before sending PDF
ob_start();

// Check if user is authorized
if (!hasRole(ROLE_COUNSELLOR) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_ACCOUNTANT)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

class PDF extends Fpdi
{
    function Header()
    {
        // Check if background PDF template exists
        $templatePath = __DIR__ . '/../../../docs/files/ADMISSION LETTER online.pdf';
        if (file_exists($templatePath)) {
            // Import the background PDF and use it as a template
            $this->setSourceFile($templatePath);
            $tplId = $this->importPage(1);
            $this->useTemplate($tplId, 0, 0, 210, 297); // A4 size: 210mm x 297mm
        }
    }
}

$pdf = new PDF();

// Load custom fonts if available
if (file_exists(__DIR__ . '/../../../Calibrib.php')) {
    $pdf->AddFont('Calibrib', '', 'Calibrib.php');
}
if (file_exists(__DIR__ . '/../../../Calibri.php')) {
    $pdf->AddFont('Calibri', '', 'Calibri.php');
}

$pdf->AddPage();

// Accept POST only for student ID
$id = $_POST['id'] ?? 0;

if (!$id) {
    ob_end_clean();
    set_flash_message('error', "Student ID is required");
    header('Location: ' . BASE_URL . '/portal/modules/students/list.php');
    exit;
}

if ($id) {

    try {
        // Fetch student details with all related information including academic year
        $sql = "SELECT s.*, 
                             b.board_name, m.medium_name, g.group_name, c.course_name,
                             sch.school_name,
                             u.name as counsellor_name,
                             ay.year_name as academic_year
                             FROM tbl_gm_std_registration s
                             LEFT JOIN tbl_boards b ON s.board_id = b.id
                             LEFT JOIN tbl_medium m ON s.medium_id = m.id
                             LEFT JOIN tbl_group g ON s.group_id = g.id
                             LEFT JOIN tbl_courses c ON s.course_id = c.id
                             LEFT JOIN tbl_schools sch ON s.school_id = sch.id
                             LEFT JOIN tbl_users u ON s.counsellor_id = u.id
                             LEFT JOIN tbl_academic_years ay ON s.academic_year_id = ay.id
                             WHERE s.id = ?";
        $row = $dbOps->customSelectOne($sql, [$id]);

        if ($row) {
            // Check if admission is confirmed
            if (!$row['admission_confirmed']) {
                ob_end_clean();
                set_flash_message('error', "Admission not yet confirmed for this student");
                $_SESSION['_redirect_post'] = ['id' => $id];
                header('Location: admission-confirm.php');
                exit;
            }

            // Use Calibri Bold for ID/Letter Number
            $font_family = file_exists(__DIR__ . '/../../../Calibrib.php') ? 'Calibrib' : 'Arial';
            $pdf->SetFont($font_family, 'B', 13);
            $pdf->SetXY(50, 79);
            $pdf->Cell(50, 10, $row['admission_letter_number'] ?? $row['id']);

            // Student Full Name
            $full_name = $row['surname'] . ' ' . $row['student_name'] . ' ' . $row['fathers_name'];
            $pdf->SetFont($font_family, '', 13);
            $pdf->SetXY(42, 110);
            $pdf->Cell(150, 10, $full_name);

            // // Standard
            // $pdf->SetFont(file_exists(__DIR__ . '/../../../Calibri.php') ? 'Calibri' : 'Arial', '', 13.5);
            // $pdf->SetXY(165, 240.5);
            // $pdf->Cell(50, 10, $row['standard'] ?? $row['course_name']);

            // Current Date
            $pdf->SetFont(file_exists(__DIR__ . '/../../../Calibri.php') ? 'Calibri' : 'Arial', '', 12);
            $pdf->SetXY(150, 79);
            $pdf->Cell(100, 10, date('d-m-Y'));

            // Academic Year (from database)
            $academic_year = $row['academic_year'] ?? '';
            $pdf->SetFont(file_exists(__DIR__ . '/../../../Calibri.php') ? 'Calibri' : 'Arial', '', 12);
            $pdf->SetXY(92, 124.5);
            $pdf->Cell(100, 10, $academic_year);

            // Ensure no output before sending the PDF
            ob_end_clean();

            // Generate filename
            $filename = 'Admission_Letter_' . $row['surname'] . '_' . $row['student_name'] . '_' . date('Ymd') . '.pdf';

            // Force download by setting appropriate headers
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $filename . '"');
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');

            // Output the PDF to the browser
            $pdf->Output('I', $filename); // 'I' = inline display, 'D' = force download
            exit;
        } else {
            ob_end_clean();
            set_flash_message('error', "Student not found");
            header('Location: list.php');
            exit;
        }
    } catch (PDOException $e) {
        ob_end_clean();
        set_flash_message('error', "Database error: " . $e->getMessage());
        header('Location: list.php');
        exit;
    }
} else {
    ob_end_clean();
    set_flash_message('error', "Student ID is required");
    header('Location: list.php');
    exit;
}
