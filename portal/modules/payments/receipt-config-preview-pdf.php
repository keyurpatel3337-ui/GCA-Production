<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once __DIR__ . '/../../vendor/autoload.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/format_helper.php';

if (!hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// Get config ID
$config_id = $_GET['id'] ?? null;
if (!$config_id) {
    set_flash_message('error', "Configuration ID is required!");
    header('Location: receipt-config.php');
    exit;
}

// Initialize Database Operations
$dbOps = new DatabaseOperations();

// Fetch configuration details
try {
    error_log("Attempting to fetch config ID: " . $config_id);
    $config = $dbOps->selectOne('tbl_receipt_configuration', ['*'], ['id' => $config_id]);
    error_log("Config fetched successfully: " . ($config ? 'YES' : 'NO'));

    if (!$config) {
        throw new Exception("Configuration not found!");
    }
} catch (PDOException $e) {
    error_log("PDO Error: " . $e->getMessage());
    error_log("Error Code: " . $e->getCode());
    error_log("SQL State: " . ($e->errorInfo[0] ?? 'N/A'));
    set_flash_message('error', "Database error: " . $e->getMessage());
    header('Location: receipt-config.php');
    exit;
} catch (Exception $e) {
    error_log("Receipt Config Error: " . $e->getMessage());
    error_log("Config ID: " . $config_id);
    error_log("Error File: " . $e->getFile());
    error_log("Error Line: " . $e->getLine());
    error_log("Stack Trace: " . $e->getTraceAsString());
    set_flash_message('error', "Failed to load configuration: " . $e->getMessage());
    header('Location: receipt-config.php');
    exit;
}

// Sample data for preview
$sample_receipt = [
    'receipt_no' => '20622',
    'surname' => 'TADHA',
    'student_name' => 'AYUSH',
    'fathers_name' => 'HARESHBHAI',
    'student_id' => '0',
    'issued_date' => date('Y-m-d'),
    'payment_mode' => 'Cash',
    'course_name' => 'E-B-ENG - Other',
    'academic_year' => 'Part-1 (2026_2027)',
    'aadhaar' => '0',
    'amount' => 11800,
    'payment_for' => 'Tuition Fee (GCA) [Including CGST 9% + SGST 9%]',
];

// Convert amount to words
function numberToWords($number)
{
    $words = convertToWords($number);
    return $words . ' Only';
}

function convertToWords($number)
{
    $number = (int) $number;
    $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    if ($number == 0)
        return 'Zero';

    $words = '';
    $rupees = floor($number);

    // Crore
    if ($rupees >= 10000000) {
        $crore = floor($rupees / 10000000);
        $words .= convertToWords($crore) . ' Crore ';
        $rupees %= 10000000;
    }

    // Lakh
    if ($rupees >= 100000) {
        $lakh = floor($rupees / 100000);
        $words .= convertToWords($lakh) . ' Lakh ';
        $rupees %= 100000;
    }

    // Thousand
    if ($rupees >= 1000) {
        $thousand = floor($rupees / 1000);
        $words .= convertToWords($thousand) . ' Thousand ';
        $rupees %= 1000;
    }

    // Hundred
    if ($rupees >= 100) {
        $hundred = floor($rupees / 100);
        $words .= $ones[$hundred] . ' Hundred ';
        $rupees %= 100;
    }

    // Tens and Ones
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

$full_name = trim($sample_receipt['surname'] . ' ' . $sample_receipt['student_name'] . ' ' . $sample_receipt['fathers_name']);
$amount_in_words = numberToWords($sample_receipt['amount']);

// Include image helper functions
require_once __DIR__ . '/../../common/image_helpers.php';

try {
    error_log("Creating TCPDF instance...");
    // Create new PDF document - A5 Landscape (210mm x 148mm)
    $pdf = new TCPDF('L', 'mm', 'A5', true, 'UTF-8', false);
    error_log("TCPDF instance created successfully");

    error_log("Setting document info...");
    // Set document information
    $pdf->SetCreator('Counselling Management System');
    error_log("SetCreator done");
    $pdf->SetAuthor($config['organization_name'] ?? 'Organization');
    error_log("SetAuthor done");
    $pdf->SetTitle('Receipt Preview');
    error_log("SetTitle done");
    $pdf->SetSubject('Fee Receipt Preview');
    error_log("SetSubject done");

    // Remove default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);

    // Set margins
    $pdf->SetMargins(8, 8, 8);
    $pdf->SetAutoPageBreak(false, 0);

    // Add a page
    $pdf->AddPage();

    // Set font
    $pdf->SetFont('helvetica', '', 10);

    // Build address with line breaks
    $address_text = $config['address'];
    if ($config['city']) {
        $address_text .= ', ' . $config['city'];
    }
    if ($config['pincode']) {
        $address_text .= '-' . $config['pincode'];
    }

    // Split address by parentheses
    $address_lines = [];
    $pattern = '/\([^)]+\)|[^()]+/';
    preg_match_all($pattern, $address_text, $matches);
    foreach ($matches[0] as $part) {
        $part = trim($part);
        if (!empty($part)) {
            $address_lines[] = $part;
        }
    }

    // Outer Border
    $pdf->Rect(7, 7, 196, 134, 'D');

    // Logo
    if (!empty($config['logo_path'])) {
        $logo_path = str_replace('../', '', $config['logo_path']);
        // For PDF generation, we need the actual file path
        $full_logo_path = dirname(__DIR__, 3) . '/counselling-backend/' . $logo_path;
        if (file_exists($full_logo_path)) {
            // $clean_logo = cleanPngImage($full_logo_path);
            $pdf->Image($full_logo_path, 12, 12, 22, 22, '', '', '', false, 300, '', false, false, 0);
        }
    }

    // Organization Name (centered)
    $pdf->SetFont('helvetica', 'B', 13);
    $pdf->SetXY(40, 11);
    $pdf->Cell(125, 7, htmlspecialchars_decode($config['organization_name'] ?? 'ORGANIZATION NAME'), 0, 1, 'L');

    // Address lines
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetXY(40, 18);
    $full_address = htmlspecialchars_decode($config['address'] ?? 'Organization Address Address Line 2') . "\n" . htmlspecialchars_decode(trim(($config['city'] ?? 'City Name') . ($config['pincode'] ? ' - ' . $config['pincode'] : '')));

    $pdf->MultiCell(115, 3.5, $full_address, 0, 'L', false, 1);

    // PAN and GST (right aligned, smaller)
    $pdf->SetFont('helvetica', '', 6.5);
    $pan_line = '';
    $gst_line = '';
    if ($config['pan_number'])
        $pan_line = 'PAN : ' . $config['pan_number'];
    if ($config['gst_number'])
        $gst_line = 'GSTIN : ' . $config['gst_number'];

    if ($pan_line) {
        $pdf->SetXY(165, 18);
        $pdf->Cell(35, 2.5, $pan_line, 0, 1, 'R');
    }
    if ($gst_line) {
        $pdf->SetXY(165, 20.5);
        $pdf->Cell(35, 2.5, $gst_line, 0, 1, 'R');
    }

    // Horizontal line after header
    $pdf->Line(9, 36, 201, 36);

    // Student Name section with Receipt No
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetXY(11, 40);
    $pdf->Cell(35, 4, "Student's Name", 0, 0, 'L');
    $pdf->SetFont('helvetica', '', 8);
    $pdf->Cell(105, 4, strtoupper($full_name), 0, 0, 'L');
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell(23, 4, 'Receipt No. :', 0, 0, 'L');
    $pdf->SetFont('helvetica', '', 8);
    $pdf->Cell(0, 4, $sample_receipt['receipt_no'], 0, 1, 'L');

    // Receipt details grid - First row (Standard, Term, Date)
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetXY(11, 46);
    $pdf->Cell(18, 4, 'Standard', 0, 0, 'L');
    $pdf->SetFont('helvetica', '', 8);
    $pdf->Cell(40, 4, $sample_receipt['course_name'], 0, 0, 'L');

    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell(10, 4, 'Term', 0, 0, 'L');
    $pdf->SetFont('helvetica', '', 8);
    $pdf->Cell(50, 4, $sample_receipt['academic_year'], 0, 0, 'L');

    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell(12, 4, 'Date :', 0, 0, 'L');
    $pdf->SetFont('helvetica', '', 8);
    $pdf->Cell(0, 4, date('d/m/Y', strtotime($sample_receipt['issued_date'])), 0, 1, 'L');

    // Second row - GR No, Roll No, Pmt.Mode
    $pdf->SetXY(11, 51);
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell(18, 4, 'GR No.', 0, 0, 'L');
    $pdf->SetFont('helvetica', '', 8);
    $pdf->Cell(40, 4, $sample_receipt['student_id'], 0, 0, 'L');

    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell(15, 4, 'Roll No', 0, 0, 'L');
    $pdf->SetFont('helvetica', '', 8);
    $pdf->Cell(45, 4, $sample_receipt['aadhaar'], 0, 0, 'L');

    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell(23, 4, 'Pmt.Mode :', 0, 0, 'L');
    $pdf->SetFont('helvetica', '', 8);
    $pdf->Cell(0, 4, ucfirst($sample_receipt['payment_mode']), 0, 1, 'L');

    // Fee Table
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetXY(9, 58);

    // Table headers
    $pdf->SetFillColor(255, 255, 255);
    $pdf->Cell(15, 5, 'Sr. No.', 1, 0, 'C');
    $pdf->Cell(152, 5, 'Particulars', 1, 0, 'C');
    $pdf->Cell(25, 5, 'Amount', 1, 1, 'C');

    // Table data
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetX(9);
    $pdf->Cell(15, 5, '1', 1, 0, 'C');
    $pdf->Cell(152, 5, $sample_receipt['payment_for'], 1, 0, 'L');
    $pdf->Cell(25, 5, formatIndianCurrency($sample_receipt['amount'], false), 1, 1, 'R');

    // Empty rows for spacing
    $pdf->SetX(9);
    $pdf->Cell(15, 5, '', 1, 0, 'C');
    $pdf->Cell(152, 5, '', 1, 0, 'L');
    $pdf->Cell(25, 5, '', 1, 1, 'R');

    // Total row
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetX(9);
    $pdf->Cell(167, 5, 'Total', 'TB', 0, 'R');
    $pdf->Cell(25, 5, formatIndianCurrency($sample_receipt['amount'], false), 'TRB', 1, 'R');

    // Amount in words
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetXY(9, 79);
    $pdf->Cell(20, 5, 'Rupees :', 'LTB', 0, 'L');
    $pdf->Cell(172, 5, $amount_in_words, 'TRB', 1, 'L');

    // Bank details
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetXY(11, 88);
    $pdf->Cell(0, 3.5, 'Subject to realization of cheque', 0, 1, 'L');
    $pdf->SetX(11);
    $pdf->Cell(0, 3.5, 'Name of Bank', 0, 1, 'L');
    $pdf->SetX(11);
    $pdf->Cell(0, 3.5, 'Cheque / D.D. No.', 0, 1, 'L');

    // Bottom section - 3 columns layout
    $pdf->SetXY(11, 101);

    // Column 1: Cheque info (left)
    $pdf->SetFont('helvetica', '', 8);
    $pdf->Cell(60, 4, 'Cheque / D.D. No.', 0, 0, 'L');

    // Column 2: Jurisdiction (center)
    if ($config['city']) {
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetXY(65, 111);
        $pdf->Cell(80, 4, 'SUBJECT TO ' . strtoupper($config['city']) . ' JURISDICTION', 0, 0, 'C');
    }

    // Column 3: Signature box (right)
    $pdf->Rect(153, 101, 47, 27);

    // Signature image
    if (!empty($config['signature_path'])) {
        $sig_path = str_replace('../', '', $config['signature_path']);
        // For PDF generation, we need the actual file path
        $full_sig_path = dirname(__DIR__, 3) . '/counselling-backend/' . $sig_path;
        if (file_exists($full_sig_path)) {
            // $clean_sig = cleanPngImage($full_sig_path);
            $pdf->Image($full_sig_path, 158, 101, 45, 20, '', '', '', false, 400, '', false, false, 0);
        }
    }

    // Signature text
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetXY(153, 122);
    $pdf->Cell(47, 4, 'Authorised Signatory', 0, 1, 'C');

    // Output PDF
    $pdf->Output('Receipt_Preview.pdf', 'I');
} catch (Exception $e) {
    error_log("PDF Generation Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    die("An error occurred while generating the PDF. Please check the error log for details.");
}
