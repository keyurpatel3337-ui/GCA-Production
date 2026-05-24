<?php
require_once __DIR__ . '/../session_config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once dirname(dirname(__DIR__)) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;

use setasign\Fpdi\Fpdi;

// Ensure no output before sending PDF
ob_start();

// Check if user is authorized
if (!hasRole(ROLE_COUNSELLOR) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_ACCOUNTANT) && !hasRole(ROLE_ESTABLISHMENT)) {
  ob_end_clean();
  set_flash_message('error', "You are not authorized to download admission letters");
  header('Location: ' . BASE_URL . '/portal/modules/students/students.php');
  exit;
}

class PDF extends Fpdi
{
  private $templatePath;

  function setTemplatePath($path)
  {
    $this->templatePath = $path;
  }

  function Header()
  {
    // Check if background template exists
    if ($this->templatePath && file_exists($this->templatePath)) {
      $ext = strtolower(pathinfo($this->templatePath, PATHINFO_EXTENSION));
      if ($ext === 'pdf') {
        // Import the background PDF and use it as a template
        $this->setSourceFile($this->templatePath);
        $tplId = $this->importPage(1);
        $this->useTemplate($tplId, 0, 0, 148, 210); // A5 size: 148mm x 210mm
      } else {
        // Use image as background
        $this->Image($this->templatePath, 0, 0, 148, 210);
      }
    }
  }
}

$pdf = new PDF('P', 'mm', 'A5');

// Using Times New Roman (built-in FPDF font 'Times')

// Accept POST or GET for student ID
$id = $_POST['id'] ?? $_GET['id'] ?? 0;

if (!$id) {
  ob_end_clean();
  set_flash_message('error', "Student ID is required");
  header('Location: ' . BASE_URL . '/portal/modules/students/students.php');
  exit;
}

if ($id) {

  try {
    // Fetch student details with all related information
    $stmt = $conn->prepare("SELECT s.*, 
                             b.board_name, m.medium_name, g.group_name, c.course_name,
                             sch.school_name, s.school_id,
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
                             WHERE s.id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
      // Check if admission is confirmed
      if (!$row['admission_confirmed']) {
        ob_end_clean();
        set_flash_message('error', "Admission not yet confirmed for this student");
        header('Location: ' . BASE_URL . '/portal/modules/students/admission-confirm.php?id=' . $id);
        exit;
      }

      // Fetch collected documents
      $stmtDocs = $conn->prepare("SELECT document_type FROM tbl_student_documents WHERE student_id = ?");
      $stmtDocs->execute([$id]);
      $collectedDocs = $stmtDocs->fetchAll(PDO::FETCH_COLUMN);

      // Determine templates
      $gcaTemplate = __DIR__ . '/../../docs/files/ADMISSION LETTER gca.png';
      $schoolTemplate = null;

      if ($row['school_id'] == 1) {
        $schoolTemplate = __DIR__ . '/../../docs/files/ADMISSION LETTER gm.png';
      } else if ($row['school_id'] == 2) {
        $schoolTemplate = __DIR__ . '/../../docs/files/ADMISSION LETTER sgm.png';
      }

      // Define Layout Configurations (X, Y coordinates, Font Size)
      $gcaLayout = [
        'id' => [24, 38, 13],
        'name' => [18, 68, 12],
        'date' => [100, 38, 12],
        'year' => [60, 82, 12],
        'docs' => [] // No checkmarks on GCA page for now
      ];

      $schoolLayout = [
        'id' => [24, 38, 12],
        'date' => [24, 50, 12],
        'name' => [18, 73.5, 12],
        'year' => [72, 88.5, 12],
        'docs' => [
          'School Leaving Certificate' => [128, 106.5],
          'First Attempt Certificate' => [128, 155],
          'Aadhar Card' => [128, 163],
          'Passport Size Photo' => [128, 170]
        ]
      ];

      // Add board-specific documents
      if ($row['board_id'] == 1) { // GSEB
        $schoolLayout['docs']['S.S.C. Marksheet'] = [128, 115];
      } else { // Other Boards
        $schoolLayout['docs']['10th Marksheet (Other Board)'] = [128, 127];
        $schoolLayout['docs']['Migration Certificate'] = [128, 144];
      }

      // Closure to write student information on the current page
      $writeStudentInfo = function ($pdf, $row, $layout, $collectedDocs = []) {
        // ID/Letter Number
        $pdf->SetFont('Times', 'B', $layout['id'][2]);
        $pdf->SetXY($layout['id'][0], $layout['id'][1]);
        $pdf->Cell(50, 10, $row['admission_letter_number'] ?? $row['id']);

        // Student Full Name
        $full_name = $row['surname'] . ' ' . $row['student_name'] . ' ' . $row['fathers_name'];
        $pdf->SetFont('Times', '', $layout['name'][2]);
        $pdf->SetXY($layout['name'][0], $layout['name'][1]);
        $pdf->Cell(150, 10, $full_name);

        // Current Date
        $pdf->SetFont('Times', '', $layout['date'][2]);
        $pdf->SetXY($layout['date'][0], $layout['date'][1]);
        $pdf->Cell(100, 10, date('d-m-Y'));

        // Academic Year (from database)
        $academic_year = $row['academic_year'] ?? '';
        $pdf->SetFont('Times', '', $layout['year'][2]);
        $pdf->SetXY($layout['year'][0], $layout['year'][1]);
        $pdf->Cell(100, 10, $academic_year);

        // Document Checkmarks
        if (!empty($layout['docs'])) {
          foreach ($layout['docs'] as $docType => $coords) {
            // Checkmark if collected
            if (in_array($docType, $collectedDocs)) {
              $pdf->SetFont('ZapfDingbats', '', 12);
              $pdf->SetXY($coords[0], $coords[1]);
              $pdf->Cell(10, 10, chr(52)); // Character 52 in ZapfDingbats is a checkmark
            }
          }
        }
      };

      // Page 1: School Template (if applicable)
      if ($schoolTemplate) {
        $pdf->setTemplatePath($schoolTemplate);
        $pdf->AddPage();
        $writeStudentInfo($pdf, $row, $schoolLayout, $collectedDocs);
      }

      // Page 2: GCA Template (Always present)
      $pdf->setTemplatePath($gcaTemplate);
      $pdf->AddPage();
      $writeStudentInfo($pdf, $row, $gcaLayout, $collectedDocs);

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
      header('Location: ' . BASE_URL . '/portal/modules/students/students.php');
      exit;
    }
  } catch (PDOException $e) {
    ob_end_clean();
    set_flash_message('error', "Database error: " . $e->getMessage());
    header('Location: ' . BASE_URL . '/portal/modules/students/students.php');
    exit;
  }
} else {
  ob_end_clean();
  set_flash_message('error', "Student ID is required");
  header('Location: ' . BASE_URL . '/portal/modules/students/students.php');
  exit;
}
