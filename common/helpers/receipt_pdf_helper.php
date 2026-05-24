<?php
require_once __DIR__ . '/../constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/format_helper.php';
require_once dirname(__DIR__) . '/../portal/common/image_helpers.php';
require_once __DIR__ . '/receipt_mapping_functions.php';

/**
 * Generate a receipt PDF and save it to a specified path.
 * Matches the logic in portal/modules/payments/receipt-print-pdf.php
 * 
 * @param PDO $conn Database connection
 * @param int $receipt_id The ID of the receipt in tbl_payments
 * @param string $save_path The absolute path where the PDF should be saved
 * @return array Result with success status and message/path
 */
function generateAndSaveReceiptPDF($conn, $receipt_id, $save_path)
{
    try {
        // Ensure directory exists
        $dir = dirname($save_path);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0777, true)) {
                throw new Exception("Failed to create directory: $dir");
            }
        }

        // Fetch receipt details (Identical to receipt-print-pdf.php)
        $op = new Operation();
        $receipt = $op->readWithJoin(
            'tbl_payments p',
            [
                'p.*',
                'p.payment_date as issued_date',
                's.surname',
                's.student_name',
                's.fathers_name',
                's.mob',
                's.aadhaar',
                's.schoolname',
                's.standard',
                's.course_id',
                's.board_id',
                's.medium_id',
                's.group_id',
                'b.board_name',
                'm.medium_name',
                'g.group_name',
                'e.roll_no',
                'ay.year_name as academic_year',
                'u.name as issued_by_name'
            ],
            [
                ['type' => 'INNER', 'table' => 'tbl_gm_std_registration s', 'on' => 'p.student_id = s.id'],
                ['type' => 'LEFT', 'table' => 'tbl_boards b', 'on' => 's.board_id = b.id'],
                ['type' => 'LEFT', 'table' => 'tbl_medium m', 'on' => 's.medium_id = m.id'],
                ['type' => 'LEFT', 'table' => 'tbl_group g', 'on' => 's.group_id = g.id'],
                ['type' => 'LEFT', 'table' => 'tbl_enrolled_students e', 'on' => 's.id = e.registration_id'],
                ['type' => 'LEFT', 'table' => 'tbl_academic_years ay', 'on' => 's.academic_year_id = ay.id'],
                ['type' => 'LEFT', 'table' => 'tbl_users u', 'on' => 'p.created_by = u.id']
            ],
            ['p.id' => $receipt_id]
        );

        if (!$receipt) {
            throw new Exception("Receipt not found for ID: $receipt_id");
        }

        // Format common fields
        $receipt['payment_for'] = ucfirst(str_replace('_', ' ', $receipt['payment_type'] ?? '')) . ' Payment';
        $full_name = trim($receipt['surname'] . ' ' . $receipt['student_name'] . ' ' . $receipt['fathers_name']);

        // Get student's school_id for configuration
        $student_school_id = null;
        $student_data = $op->selectOne('tbl_gm_std_registration', ['school_id'], ['id' => $receipt['student_id']]);
        if ($student_data) {
            $student_school_id = $student_data['school_id'];
        }

        // Get receipt configuration
        $config_id = getReceiptConfigForFee($conn, $receipt['fee_component'] ?? '', $student_school_id);
        $config = $config_id ? getReceiptConfigDetails($conn, $config_id) : $op->selectOne('tbl_receipt_configuration', ['*'], ['is_active' => 1]);

        if (!$config) {
            throw new Exception("Receipt configuration not found.");
        }

        // Amount in words
        $receipt['amount'] = round((float) $receipt['amount']);
        $amount_in_words = helperNumberToWords($receipt['amount']);

        // Create PDF (using TCPDF)
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('GCA');
        $pdf->SetAuthor($config['organization_name']);
        $pdf->SetTitle('Receipt - ' . $receipt['receipt_no']);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(8, 8, 8);
        $pdf->SetAutoPageBreak(false, 0);

        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 11);

        // Outer Border
        $pdf->Rect(7, 7, 196, 134, 'D');

        // Logo
        if (!empty($config['logo_path'])) {
            $logo_path = str_replace('../', '', $config['logo_path']);
            $full_logo_path = ROOT_PATH . 'counselling-backend/' . $logo_path;
            if (file_exists($full_logo_path)) {
                $clean_logo = cleanPngImage($full_logo_path);
                $pdf->Image($clean_logo, 12, 12, 22, 22, '', '', '', false, 300, '', false, false, 0);
                if ($clean_logo != $full_logo_path)
                    @unlink($clean_logo);
            }
        }

        // Organization Name
        $pdf->SetFont('helvetica', 'B', 15);
        $pdf->SetXY(40, 11);
        $pdf->Cell(125, 7, $config['organization_name'], 0, 1, 'L');

        // Address
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetXY(40, 18);
        $full_address = $config['address'] . "\n" . trim(($config['city'] ?? '') . ($config['pincode'] ? ' - ' . $config['pincode'] : ''));
        $pdf->MultiCell(115, 4, $full_address, 0, 'L', false, 1);

        // PAN / GST
        $pdf->SetFont('helvetica', '', 9);
        if ($config['pan_number']) {
            $pdf->SetXY(155, 27);
            $pdf->Cell(45, 4, 'PAN : ' . $config['pan_number'], 0, 1, 'R');
        }
        if ($config['gst_number']) {
            $pdf->SetXY(155, 31);
            $pdf->Cell(45, 4, 'GSTIN : ' . $config['gst_number'], 0, 1, 'R');
        }

        $pdf->Line(9, 36, 201, 36);

        // Student Box
        $pdf->Rect(9, 38, 192, 8);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetXY(11, 40);
        $pdf->Cell(30, 5, "Student's Name :", 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 5, strtoupper($full_name), 0, 1, 'L');

        // Data Grid Row 1 (Standard, Term, Date)
        $pdf->SetXY(11, 49);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(21, 5, 'Standard', 0, 0, 'L');
        $pdf->Cell(4, 5, ':', 0, 0, 'C');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(55, 5, $receipt['standard'] ?? 'N/A', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 9);
        if (($receipt['course_id'] ?? 0) != 3) {
            $pdf->Cell(16, 5, 'Term', 0, 0, 'L');
            $pdf->Cell(4, 5, ':', 0, 0, 'C');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(40, 5, $receipt['semester'] ?? 'Semester 1', 0, 0, 'L');
        } else {
            $pdf->Cell(60, 5, '', 0, 0, 'L');
        }
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(18, 5, 'Date', 0, 0, 'L');
        $pdf->Cell(4, 5, ':', 0, 0, 'C');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(0, 5, date('d/m/Y', strtotime($receipt['issued_date'])), 0, 1, 'L');

        // Data Grid Row 2 (Group, Year, Receipt No.)
        $pdf->SetXY(11, 55);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(21, 5, 'Group', 0, 0, 'L');
        $pdf->Cell(4, 5, ':', 0, 0, 'C');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(55, 5, $receipt['group_name'] ?? 'N/A', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(16, 5, 'Year', 0, 0, 'L');
        $pdf->Cell(4, 5, ':', 0, 0, 'C');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(40, 5, $receipt['academic_year'] ?? helperGetAcademicYear($receipt['issued_date']), 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(18, 5, 'Receipt No.', 0, 0, 'L');
        $pdf->Cell(4, 5, ':', 0, 0, 'C');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(0, 5, $receipt['receipt_no'], 0, 1, 'L');

        // Metadata grid Row 3 (Medium, Roll No, Pmt.Mode)
        $pdf->SetXY(11, 61);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(21, 5, 'Medium', 0, 0, 'L');
        $pdf->Cell(4, 5, ':', 0, 0, 'C');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(55, 5, $receipt['medium_name'] ?? 'N/A', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(16, 5, 'Roll No.', 0, 0, 'L');
        $pdf->Cell(4, 5, ':', 0, 0, 'C');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(40, 5, $receipt['roll_no'] ?? 'N/A', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(18, 5, 'Pmt.Mode', 0, 0, 'L');
        $pdf->Cell(4, 5, ':', 0, 0, 'C');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(0, 5, ucfirst($receipt['payment_mode'] ?? ''), 0, 1, 'L');

        // Table Header
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetXY(9, 68);
        $pdf->Cell(15, 8, 'Sr. No.', 1, 0, 'C');
        $pdf->Cell(152, 8, 'Particulars', 1, 0, 'C');
        $pdf->Cell(25, 8, 'Amount', 1, 1, 'C');

        // Table Body
        $pdf->SetFont('helvetica', '', 11);
        $pdf->SetX(9);
        $pdf->Cell(15, 8, '1', 'LR', 0, 'C');

        $particulars_text = $receipt['payment_for'];
        if (isset($receipt['fee_component'])) {
            switch ($receipt['fee_component']) {
                case 'school_fee':
                    $particulars_text = 'Tuition Fee';
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
                case 'transport_fee':
                    $particulars_text = 'Vehicle Fee';
                    break;
                case 'hostel_fee':
                    $particulars_text = 'Hostel Fee';
                    break;
                default:
                    $particulars_text = ucfirst(str_replace('_', ' ', $receipt['fee_component']));
            }
        }
        $pdf->Cell(152, 8, $particulars_text, 'LR', 0, 'L');
        $pdf->Cell(25, 8, formatIndianCurrency($receipt['amount']), 'LR', 1, 'R');

        // Empty rows (Spacer)
        $pdf->SetX(9);
        $pdf->Cell(15, 4, '', 'LR', 0, 'C');
        $pdf->Cell(152, 4, '', 'LR', 0, 'L');
        $pdf->Cell(25, 4, '', 'LR', 1, 'R');
        $pdf->SetX(9);
        $pdf->Cell(15, 4, '', 'LR', 0, 'C');
        $pdf->Cell(152, 4, '', 'LR', 0, 'L');
        $pdf->Cell(25, 4, '', 'LR', 1, 'R');

        // Total
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetX(9);
        $pdf->Cell(20, 8, 'Rupees :', 'LTB', 0, 'L');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(132, 8, $amount_in_words, 'TB', 0, 'L');
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(15, 8, 'Total:', 'TRB', 0, 'C');
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(25, 8, formatIndianCurrency($receipt['amount']), 'LTRB', 1, 'R');

        // Bank info
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetXY(11, 105);
        $pdf->Cell(0, 5, 'Subject to realization of cheque', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetX(11);
        $pdf->Cell(0, 5, 'Name of Bank: ' . ($receipt['bank_name']), 0, 1, 'L');
        $pdf->SetX(11);
        $pdf->Cell(0, 5, 'Cheque / D.D. No.: ' . ($receipt['cheque_no']), 0, 1, 'L');
        $pdf->SetX(11);
        $pdf->Cell(0, 5, 'Transaction ID: ' . ($receipt['transaction_id']), 0, 1, 'L');

        if (!empty($receipt['payment_id'])) {
            $pdf->SetX(11);
            $pdf->Cell(0, 5, 'Payment ID: ' . $receipt['payment_id'], 0, 1, 'L');
        }

        // Bottom section
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetXY(11, 130);
        $pdf->Cell(100, 5, 'SUBJECT TO BHAVNAGAR JURISDICTION', 0, 1, 'L');
        $pdf->SetFont('helvetica', 'I', 7);
        $pdf->SetX(11);
        $pdf->Cell(100, 5, '*This receipt is valid without signature as it is generated by the system.*', 0, 0, 'L');

        // Signature box
        $pdf->Rect(153, 112, 47, 27);
        if (!empty($config['signature_path'])) {
            $sig_path = str_replace('../', '', $config['signature_path']);
            $full_sig_path = ROOT_PATH . 'counselling-backend/' . $sig_path;
            if (file_exists($full_sig_path)) {
                $pdf->Image($full_sig_path, 158, 112, 45, 20, '', '', '', false, 400, '', false, false, 0);
            }
        }
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetXY(153, 133);
        $pdf->Cell(47, 5, 'Authorised Signatory', 0, 1, 'C');

        // Add refund/rules policy blocks to saved PDFs (same behavior as receipt-print-pdf.php)
        if (in_array(($receipt['course_id'] ?? null), [1, '1'])) {
            $pdf->SetY(150);

            // Title
            $pdf->SetFont('helvetica', 'B', 14);
            $pdf->SetFillColor(0, 0, 0);
            $pdf->Rect(11, 150, 1.5, 6, 'F');
            $pdf->SetXY(14, 150);
            $pdf->Cell(0, 6, 'REFUND POLICY :', 0, 1, 'L');

            $pdf->Ln(5);

            // Section 1: Who paid only Tokan fee
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetX(11);
            $pdf->Cell(0, 6, 'Who paid only Tokan fee.', 0, 1, 'L');

            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->SetX(14);
            $pdf->Cell(5, 5, '>', 0, 0, 'R');
            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetX(20);
            $pdf->Cell(0, 5, 'Tokan Fee will be non-refundable.', 0, 1, 'L');

            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->SetX(14);
            $pdf->Cell(5, 5, '>', 0, 0, 'R');
            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetX(20);
            $pdf->MultiCell(0, 5, 'Tokan Fee will be refundable only for the Pre-admitted student who has cancelled admission up to 15th March', 0, 'L');

            $pdf->Ln(4);

            // Section 2: Who paid full Fees and cancelled the admission
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetX(11);
            $pdf->Cell(0, 6, 'Who paid full Fees and cancelled the admission.', 0, 1, 'L');

            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->SetX(14);
            $pdf->Cell(5, 5, '>', 0, 0, 'R');
            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetX(20);
            $pdf->Cell(30, 5, '1 to 30 Days', 0, 0, 'L');
            $pdf->Cell(0, 5, ':  80% Fees Refundable.', 0, 1, 'L');

            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->SetX(14);
            $pdf->Cell(5, 5, '>', 0, 0, 'R');
            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetX(20);
            $pdf->Cell(30, 5, '31 to 60 Days', 0, 0, 'L');
            $pdf->Cell(0, 5, ':  65% Fees Refundable.', 0, 1, 'L');

            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->SetX(14);
            $pdf->Cell(5, 5, '>', 0, 0, 'R');
            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetX(20);
            $pdf->Cell(30, 5, '61 to 90 Days', 0, 0, 'L');
            $pdf->Cell(0, 5, ':  50% Fees Refundable.', 0, 1, 'L');

            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->SetX(14);
            $pdf->Cell(5, 5, '>', 0, 0, 'R');
            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetX(20);
            $pdf->Cell(30, 5, 'After 90 Days', 0, 0, 'L');
            $pdf->Cell(0, 5, ':  Fees will be Non-Refundable.', 0, 1, 'L');
        } elseif (($receipt['course_id'] ?? null) == 3) {
            $pdf->SetY(145);

            // Rules and fees policy title
            $pdf->SetFont('helvetica', 'BU', 12);
            $pdf->SetXY(11, 145);
            $pdf->Cell(0, 6, 'Rules and fees policy are as under :', 0, 1, 'L');

            $pdf->Ln(2);

            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetX(11);
            $pdf->Cell(8, 5, '1>', 0, 0, 'R');
            $pdf->SetX(21);
            $pdf->MultiCell(0, 5, 'Student should attain all classes and lecture without being absent.', 0, 'L');

            $pdf->SetX(11);
            $pdf->Cell(8, 5, '2>', 0, 0, 'R');
            $pdf->SetX(21);
            $pdf->MultiCell(0, 5, 'Student should keep discipline and obey all rules made by academy.', 0, 'L');

            $pdf->SetX(11);
            $pdf->Cell(8, 5, '3>', 0, 0, 'R');
            $pdf->SetX(21);
            $pdf->MultiCell(0, 5, "Attain all seminar's and tests.", 0, 'L');

            $pdf->Ln(4);

            // Fees policy title
            $pdf->SetFont('helvetica', 'BU', 12);
            $pdf->SetX(11);
            $pdf->Cell(0, 6, 'Fees policy :', 0, 1, 'L');

            $pdf->Ln(2);

            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetX(11);
            $pdf->Cell(8, 5, '1>', 0, 0, 'R');
            $pdf->SetX(21);
            $pdf->MultiCell(0, 5, 'Parents must pay fees regularly, each installment in time limit.', 0, 'L');

            $pdf->SetX(11);
            $pdf->Cell(8, 5, '2>', 0, 0, 'R');
            $pdf->SetX(21);
            $pdf->MultiCell(0, 5, 'In case of full fees payment, fees will refunded by refund policy made by academy are as under :', 0, 'L');

            $pdf->SetX(21);
            $pdf->Cell(8, 5, '(a)', 0, 0, 'L');
            $pdf->MultiCell(0, 5, 'After 1 week of starting of classes, 80% of fee will be refunded.', 0, 'L');

            $pdf->SetX(21);
            $pdf->Cell(8, 5, '(b)', 0, 0, 'L');
            $pdf->MultiCell(0, 5, 'After 2 week of starting of classes, 60% of fee will be refunded.', 0, 'L');

            $pdf->SetX(21);
            $pdf->Cell(8, 5, '(c)', 0, 0, 'L');
            $pdf->MultiCell(0, 5, 'After 3 week of starting of classes, 50% of fee will be refunded.', 0, 'L');

            $pdf->SetX(21);
            $pdf->Cell(8, 5, '(d)', 0, 0, 'L');
            $pdf->MultiCell(0, 5, 'After 4 week of starting of classes, 40% of fee will be refunded.', 0, 'L');

            $pdf->SetX(21);
            $pdf->Cell(8, 5, '(e)', 0, 0, 'L');
            $pdf->MultiCell(0, 5, 'After 28 days of starting of classes, fee will not be refunded.', 0, 'L');

            $pdf->Ln(2);

            $pdf->SetX(11);
            $pdf->Cell(8, 5, '3>', 0, 0, 'R');
            $pdf->SetX(21);
            $pdf->MultiCell(0, 5, 'For benefit of refund, parents / student should apply for the same in prescribed format.', 0, 'L');
        }

        // Save PDF
        $pdf->Output($save_path, 'F');

        return [
            'success' => true,
            'message' => 'Receipt saved successfully',
            'file_path' => $save_path
        ];

    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Failed to generate PDF: ' . $e->getMessage()
        ];
    }
}

/**
 * Helper to convert number to words (Identical to receipt-print-pdf.php)
 * 
 * @param float|int|string $number
 * @return string
 */
function helperNumberToWords($number)
{
    return helperConvertToWords($number) . ' Only';
}

/**
 * Recursive helper to convert number to words
 * 
 * @param float|int|string $number
 * @return string
 */
function helperConvertToWords($number)
{
    $number = round((float) $number);
    $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    if ($number == 0)
        return 'Zero';

    $words = '';
    $rupees = floor($number);

    if ($rupees >= 10000000) {
        $words .= helperConvertToWords(floor($rupees / 10000000)) . ' Crore ';
        $rupees %= 10000000;
    }
    if ($rupees >= 100000) {
        $words .= helperConvertToWords(floor($rupees / 100000)) . ' Lakh ';
        $rupees %= 100000;
    }
    if ($rupees >= 1000) {
        $words .= helperConvertToWords(floor($rupees / 1000)) . ' Thousand ';
        $rupees %= 1000;
    }
    if ($rupees >= 100) {
        $words .= $ones[(int)floor($rupees / 100)] . ' Hundred ';
        $rupees %= 100;
    }
    if ($rupees >= 20) {
        $words .= $tens[(int)floor($rupees / 10)] . ' ';
        $rupees %= 10;
    }
    if ($rupees > 0 && $rupees < 20) {
        $words .= $ones[(int)$rupees] . ' ';
    }

    return trim($words);
}

/**
 * Calculate academic year based on date (April starts)
 */
function helperGetAcademicYear($date = null)
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
