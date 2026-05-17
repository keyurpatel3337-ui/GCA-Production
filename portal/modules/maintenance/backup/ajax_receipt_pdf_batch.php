<?php
require_once dirname(__DIR__, 3) . '/session_config.php';
require_once dirname(__DIR__, 4) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;
require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once dirname(__DIR__, 4) . '/common/helpers/receipt_mapping_functions.php';
require_once dirname(__DIR__, 3) . '/common/image_helpers.php';
require_once dirname(__DIR__, 4) . '/common/helpers/format_helper.php';
// Check if user is Maintenance Admin or Super Admin
if (!hasRole(ROLE_MAINTENANCE) && !hasRole(ROLE_SUPER_ADMIN)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? '';

// Helper function to calculate academic year based on date
function getAcademicYear($date = null)
{
    if ($date) {
        $timestamp = strtotime($date);
        $year = (int) date('Y', $timestamp);
        $month = (int) date('n', $timestamp);
    } else {
        $year = (int) date('Y');
        $month = (int) date('n');
    }
    if ($month >= 4) {
        return $year . '-' . substr($year + 1, 2);
    } else {
        return ($year - 1) . '-' . substr($year, 2);
    }
}

// Convert amount to words
function numberToWords($number)
{
    $words = convertToWords($number);
    return $words . ' Only';
}

function convertToWords($number)
{
    $number = round((float) $number);
    $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
    if ($number == 0)
        return 'Zero';
    $words = '';
    $rupees = floor($number);
    if ($rupees >= 10000000) {
        $crore = floor($rupees / 10000000);
        $words .= convertToWords($crore) . ' Crore ';
        $rupees %= 10000000;
    }
    if ($rupees >= 100000) {
        $lakh = floor($rupees / 100000);
        $words .= convertToWords($lakh) . ' Lakh ';
        $rupees %= 100000;
    }
    if ($rupees >= 1000) {
        $thousand = floor($rupees / 1000);
        $words .= convertToWords($thousand) . ' Thousand ';
        $rupees %= 1000;
    }
    if ($rupees >= 100) {
        $hundred = floor($rupees / 100);
        $words .= $ones[$hundred] . ' Hundred ';
        $rupees %= 100;
    }
    if ($rupees >= 20) {
        $ten = floor($rupees / 10);
        $words .= $tens[$ten] . ' ';
        $rupees %= 10;
    }
    if ($rupees > 0 && $rupees < 20) {
        $words .= $ones[$rupees] . ' ';
    }
    return trim($words);
}
$backup_base_dir = 'D:/portal_backups/receipt_pdfs';
$temp_dir = $backup_base_dir . '/temp';

if (!is_dir($temp_dir)) {
    mkdir($temp_dir, 0755, true);
}

header('Content-Type: application/json');

try {
    if ($action === 'init') {
        $startDate = $_POST['start_date'] ?? '';
        $endDate = $_POST['end_date'] ?? '';
        $reportType = $_POST['report_type'] ?? '';
        $reportDate = $_POST['report_date'] ?? '';

        $where = "p.status = 'paid'";
        $params = [];

        if ($reportType && $reportDate) {
            if ($reportType === 'daily') {
                $where .= " AND DATE(p.payment_date) = :date";
                $params = ['date' => $reportDate];
            } elseif ($reportType === 'monthly') {
                $where .= " AND YEAR(p.payment_date) = YEAR(:date) AND MONTH(p.payment_date) = MONTH(:date)";
                $params = ['date' => $reportDate];
            } elseif ($reportType === 'yearly') {
                $where .= " AND YEAR(p.payment_date) = YEAR(:date)";
                $params = ['date' => $reportDate];
            }
        } elseif ($startDate && $endDate) {
            $where .= " AND DATE(p.payment_date) BETWEEN :start AND :end";
            $params = ['start' => $startDate, 'end' => $endDate];
        } else {
            throw new Exception("Parameters missing");
        }

        // Count total receipts in range
        $sql = "SELECT COUNT(*) FROM tbl_payments p WHERE {$where}";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $total = $stmt->fetchColumn();

        $sessionId = uniqid('backup_');
        $sessionPath = $temp_dir . '/' . $sessionId;
        mkdir($sessionPath, 0755, true);

        echo json_encode([
            'success' => true,
            'total' => $total,
            'session_id' => $sessionId
        ]);
        exit;
    }

    if ($action === 'process') {
        $sessionId = $_POST['session_id'] ?? '';
        $offset = (int) ($_POST['offset'] ?? 0);
        $limit = (int) ($_POST['limit'] ?? 20);
        $startDate = $_POST['start_date'] ?? '';
        $endDate = $_POST['end_date'] ?? '';
        $reportType = $_POST['report_type'] ?? '';
        $reportDate = $_POST['report_date'] ?? '';

        if (!$sessionId || !is_dir($temp_dir . '/' . $sessionId)) {
            throw new Exception("Invalid session");
        }

        $sessionPath = $temp_dir . '/' . $sessionId;

        $where = "p.status = 'paid'";
        $params = [];

        if ($reportType && $reportDate) {
            if ($reportType === 'daily') {
                $where .= " AND DATE(p.payment_date) = :date";
                $params = ['date' => $reportDate];
            } elseif ($reportType === 'monthly') {
                $where .= " AND YEAR(p.payment_date) = YEAR(:date) AND MONTH(p.payment_date) = MONTH(:date)";
                $params = ['date' => $reportDate];
            } elseif ($reportType === 'yearly') {
                $where .= " AND YEAR(p.payment_date) = YEAR(:date)";
                $params = ['date' => $reportDate];
            }
        } elseif ($startDate && $endDate) {
            $where .= " AND DATE(p.payment_date) BETWEEN :start AND :end";
            $params = ['start' => $startDate, 'end' => $endDate];
        }

        // Fetch chunk of receipts
        $sql = "SELECT p.id, p.receipt_no, p.student_id, p.fee_component 
                FROM tbl_payments p 
                WHERE {$where} 
                LIMIT :limit OFFSET :offset";
        $stmt = $conn->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue(':' . $key, $val);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $receiptList = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $processed = 0;
        foreach ($receiptList as $r_info) {
            // REUSE LOGIC FROM receipt-print-pdf.php (Condensed)
            $receipt_id = $r_info['id'];

            // Faking REQUEST variables for the logic we're about to borrow/mimic
            $_REQUEST['id'] = $receipt_id;

            // Fetch receipt details (mimicking receipt-print-pdf.php logic)
            $op = new Operation();
            $receipt = $op->readWithJoin(
                'tbl_payments p',
                ['p.*', 'p.payment_date as issued_date', 's.surname', 's.student_name', 's.fathers_name', 's.mob', 's.aadhaar', 's.schoolname', 's.standard', 's.board_id', 's.medium_id', 's.group_id', 'b.board_name', 'm.medium_name', 'g.group_name', 'e.roll_no', 'ay.year_name as academic_year', 'u.name as issued_by_name'],
                [
                    ['type' => 'INNER', 'table' => 'tbl_gm_std_registration s', 'on' => 'p.student_id = s.id'],
                    ['type' => 'LEFT', 'table' => 'tbl_boards b', 'on' => 's.board_id = b.id'],
                    ['type' => 'LEFT', 'table' => 'tbl_medium m', 'on' => 's.medium_id = m.id'],
                    ['type' => 'LEFT', 'table' => 'tbl_group g', 'on' => 's.group_id = g.id'],
                    ['type' => 'LEFT', 'table' => 'tbl_enrolled_students e', 'on' => 's.id = e.registration_id'],
                    ['type' => 'LEFT', 'table' => 'tbl_academic_years ay', 'on' => 's.academic_year_id = ay.id'],
                    ['type' => 'LEFT', 'table' => 'tbl_users u', 'on' => 'p.created_by = u.id']
                ],
                ['p.id' => $receipt_id, 'e.is_active' => 1]
            );

            if (!$receipt)
                continue;

            $receipt['payment_for'] = ucfirst(str_replace('_', ' ', $receipt['payment_type'] ?? '')) . ' Payment';
            $receipts = [$receipt];

            // Get config
            $student_school_id = $receipt['school_id'] ?? null;
            $fee_component = $receipt['fee_component'] ?? null;
            $config_id = getReceiptConfigForFee($conn, $fee_component, $student_school_id);
            $current_config = $config_id ? getReceiptConfigDetails($conn, $config_id) : $op->selectOne('tbl_receipt_configuration', ['*'], ['is_active' => 1]);

            if (!$current_config)
                continue;

            // Generate PDF using TCPDF (Borrowed from receipt-print-pdf.php)
            $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
            $pdf->SetCreator('GCA System');
            $pdf->SetAuthor($current_config['organization_name']);
            $pdf->SetTitle('Receipt - ' . $receipt['receipt_no']);
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetMargins(8, 8, 8);
            $pdf->SetAutoPageBreak(false, 0);
            $pdf->AddPage();

            // --- PDF CONTENT START (Full Sync with receipt-print-pdf.php) ---
            $pdf->SetFont('helvetica', '', 11);
            $amount_in_words = numberToWords($receipt['amount']);

            // Build address with line breaks
            $address_text = $current_config['address'];
            if (!empty($current_config['city']))
                $address_text .= ', ' . $current_config['city'];
            if (!empty($current_config['pincode']))
                $address_text .= '-' . $current_config['pincode'];

            $address_lines = [];
            $pattern = '/\([^)]+\)|[^()]+/';
            preg_match_all($pattern, $address_text, $matches);
            foreach ($matches[0] as $part) {
                $part = trim($part);
                if (!empty($part))
                    $address_lines[] = $part;
            }

            // Outer Border
            $pdf->Rect(7, 7, 196, 134, 'D');

            // Logo
            if (!empty($current_config['logo_path'])) {
                $lp = str_replace('../', '', $current_config['logo_path']);
                $flp = dirname(__DIR__, 3) . '/counselling-backend/' . $lp;
                if (file_exists($flp)) {
                    $cl = cleanPngImage($flp);
                    $pdf->Image($cl, 12, 12, 22, 22, '', '', '', false, 300, '', false, false, 0);
                    if ($cl != $flp)
                        @unlink($cl);
                }
            }

            // Organization Name (centered)
            $pdf->SetFont('helvetica', 'B', 13);
            $pdf->SetXY(40, 13);
            $pdf->Cell(125, 5, $current_config['organization_name'], 0, 1, 'C');

            // Address lines
            $pdf->SetFont('helvetica', '', 9);
            $y_pos = 19;
            foreach ($address_lines as $line) {
                $pdf->SetXY(40, $y_pos);
                $pdf->Cell(125, 4, $line, 0, 1, 'C');
                $y_pos += 4;
            }

            // PAN and GST
            $pdf->SetFont('helvetica', '', 6.5);
            if (!empty($current_config['pan_number'])) {
                $pdf->SetXY(165, 18);
                $pdf->Cell(35, 2.5, 'PAN : ' . $current_config['pan_number'], 0, 1, 'R');
            }
            if (!empty($current_config['gst_number'])) {
                $pdf->SetXY(165, 20.5);
                $pdf->Cell(35, 2.5, 'GSTIN : ' . $current_config['gst_number'], 0, 1, 'R');
            }

            // Horizontal line after header
            $pdf->Line(9, 36, 201, 36);

            // Student Name with Roll No
            $pdf->Rect(9, 38, 192, 8);
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetXY(11, 40);
            $pdf->Cell(28, 5, "Student's Name", 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 10);
            $full_name = strtoupper(trim(($receipt['surname'] ?? '') . ' ' . ($receipt['student_name'] ?? '') . ' ' . ($receipt['fathers_name'] ?? '')));
            $pdf->Cell(100, 5, $full_name, 0, 0, 'L');
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell(18, 5, 'Roll No', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 10);
            $pdf->Cell(0, 5, $receipt['roll_no'] ?? 'N/A', 0, 1, 'L');

            // Receipt details grid - Row 1
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetXY(11, 49);
            $pdf->Cell(26, 5, 'Receipt No. :', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(22, 5, $receipt['receipt_no'], 0, 0, 'L');
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(16, 5, 'Date :', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(56, 5, date('d/m/Y', strtotime($receipt['issued_date'])), 0, 0, 'L');
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(26, 5, 'Pmt.Mode :', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(0, 5, ucfirst($receipt['payment_mode']), 0, 1, 'L');

            // Row 2
            $pdf->SetXY(11, 55);
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(26, 5, 'Standard :', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(22, 5, $receipt['standard'] ?? 'N/A', 0, 0, 'L');
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(16, 5, 'Group :', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(56, 5, $receipt['group_name'] ?? 'N/A', 0, 0, 'L');
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(20, 5, 'Medium :', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(0, 5, $receipt['medium_name'] ?? 'N/A', 0, 1, 'L');

            // Row 3
            $pdf->SetXY(11, 61);
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(26, 5, 'Year :', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(22, 5, $receipt['academic_year'] ?? getAcademicYear($receipt['issued_date']), 0, 0, 'L');
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(16, 5, 'Term :', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(56, 5, $receipt['semester'] ?? 'Semester 1', 0, 0, 'L');
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(20, 5, 'GR No. :', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(0, 5, $receipt['student_id'] ?? '0', 0, 1, 'L');

            // Fee Table
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetXY(9, 68);
            $pdf->Cell(15, 6, 'Sr. No.', 1, 0, 'C');
            $pdf->Cell(152, 6, 'Particulars', 1, 0, 'C');
            $pdf->Cell(25, 6, 'Amount', 1, 1, 'C');

            $pdf->SetFont('helvetica', '', 9);
            $pdf->SetX(9);
            $pdf->Cell(15, 6, '1', 1, 0, 'C');

            $particulars_text = $receipt['payment_for'];
            if (!empty($receipt['fee_component'])) {
                switch ($receipt['fee_component']) {
                    case 'school_fee':
                        $particulars_text = 'School Fee';
                        break;
                    case 'trust_facilities_fee':
                        $particulars_text = 'Trust Facilities Fee';
                        break;
                    case 'tuition_fee_part1':
                        $particulars_text = 'Tuition Fee Part 1 (including CGST 9% + SGST 9%)';
                        break;
                    case 'tuition_fee_part2':
                        $particulars_text = 'Tuition Fee Part 2 (including CGST 9% + SGST 9%)';
                        break;
                    default:
                        $particulars_text = ucfirst(str_replace('_', ' ', $receipt['fee_component']));
                }
            }
            $pdf->Cell(152, 6, $particulars_text, 1, 0, 'L');
            $pdf->Cell(25, 6, formatIndianCurrency($receipt['amount'], false), 1, 1, 'R');

            // Empty row
            $pdf->SetX(9);
            $pdf->Cell(15, 6, '', 1, 0, 'C');
            $pdf->Cell(152, 6, '', 1, 0, 'L');
            $pdf->Cell(25, 6, '', 1, 1, 'R');

            // Total row
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetX(9);
            $pdf->Cell(167, 6, 'Total', 1, 0, 'R');
            $pdf->Cell(25, 6, formatIndianCurrency($receipt['amount'], false), 1, 1, 'R');

            // Amount in words
            $pdf->SetFont('helvetica', '', 9);
            $pdf->SetXY(9, 93);
            $pdf->Cell(20, 6, 'Rupees :', 1, 0, 'L');
            $pdf->Cell(172, 6, $amount_in_words, 1, 1, 'L');

            // Payment details
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetXY(11, 102);
            $pdf->Cell(0, 5, 'Subject to realization of cheque', 0, 1, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->SetX(11);
            $pdf->Cell(0, 5, 'Bank Name: ' . ($receipt['bank_name'] ?? '___________'), 0, 1, 'L');
            $pdf->SetX(11);
            $pdf->Cell(0, 5, 'Cheque / D.D. No.: ' . ($receipt['cheque_no'] ?? '___________'), 0, 1, 'L');
            $pdf->SetX(11);
            $pdf->Cell(0, 5, 'Transaction ID: ' . ($receipt['transaction_id'] ?? '___________'), 0, 1, 'L');

            // Bottom section
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetXY(11, 130);
            $pdf->Cell(100, 5, 'SUBJECT TO BHAVNAGAR JURISDICTION', 0, 0, 'L');

            // Signature
            $pdf->Rect(153, 112, 47, 27);
            if (!empty($current_config['signature_path'])) {
                $sig_path = str_replace('../', '', $current_config['signature_path']);
                $full_sig_path = dirname(__DIR__, 3) . '/counselling-backend/' . $sig_path;
                if (file_exists($full_sig_path)) {
                    $pdf->Image($full_sig_path, 158, 112, 45, 20, '', '', '', false, 400, '', false, false, 0);
                }
            }
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetXY(153, 133);
            $pdf->Cell(47, 5, 'Authorised Signatory', 0, 1, 'C');

            // Save to file
            $pdf_filename = 'Receipt_' . $receipt['receipt_no'] . '_' . str_replace(' ', '_', $receipt['student_name']) . '_' . $receipt['id'] . '.pdf';
            $pdf->Output($sessionPath . '/' . $pdf_filename, 'F');
            $processed++;
        }

        echo json_encode([
            'success' => true,
            'processed' => $processed
        ]);
        exit;
    }

    if ($action === 'finalize') {
        $sessionId = $_POST['session_id'] ?? '';
        if (!$sessionId || !is_dir($temp_dir . '/' . $sessionId)) {
            throw new Exception("Invalid session");
        }

        $sessionPath = $temp_dir . '/' . $sessionId;
        $zipFilename = 'Receipt_PDF_Backup_' . date('Ymd_His') . '.zip';
        $zipFilepath = $backup_base_dir . '/' . $zipFilename;

        $zip = new ZipArchive();
        if ($zip->open($zipFilepath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($sessionPath),
                RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($files as $name => $file) {
                if (!$file->isDir()) {
                    $filePath = $file->getRealPath();
                    $relativePath = basename($filePath);
                    $zip->addFile($filePath, $relativePath);
                }
            }
            $zip->close();

            // Cleanup temp session dir
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($sessionPath, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($files as $fileinfo) {
                $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
                $todo($fileinfo->getRealPath());
            }
            rmdir($sessionPath);

            echo json_encode([
                'success' => true,
                'filename' => $zipFilename
            ]);
        } else {
            throw new Exception("Could not create ZIP file");
        }
        exit;
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>