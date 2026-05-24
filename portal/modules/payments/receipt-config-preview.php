<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
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
    $config = $dbOps->selectOne('tbl_receipt_configuration', ['*'], ['id' => $config_id]);

    if (!$config) {
        set_flash_message('error', "Configuration not found!");
        header('Location: receipt-config.php');
        exit;
    }
} catch (PDOException $e) {
    set_flash_message('error', "Failed to load configuration!");
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
    'bank_name' => '',
    'cheque_no' => '',
    'transaction_id' => ''
];

// Convert amount to words
function numberToWords($number)
{
    $number = (int) $number;
    $ones = [
        '',
        'One',
        'Two',
        'Three',
        'Four',
        'Five',
        'Six',
        'Seven',
        'Eight',
        'Nine',
        'Ten',
        'Eleven',
        'Twelve',
        'Thirteen',
        'Fourteen',
        'Fifteen',
        'Sixteen',
        'Seventeen',
        'Eighteen',
        'Nineteen'
    ];

    $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    if ($number == 0)
        return 'Zero';

    $words = '';
    $rupees = floor($number);

    // Crore
    if ($rupees >= 10000000) {
        $crore = floor($rupees / 10000000);
        $words .= numberToWords($crore) . ' Crore ';
        $rupees %= 10000000;
    }

    // Lakh
    if ($rupees >= 100000) {
        $lakh = floor($rupees / 100000);
        $words .= numberToWords($lakh) . ' Lakh ';
        $rupees %= 100000;
    }

    // Thousand
    if ($rupees >= 1000) {
        $thousand = floor($rupees / 1000);
        $words .= numberToWords($thousand) . ' Thousand ';
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

    $words = trim($words);
    if (empty($words))
        $words = 'Zero';

    $words .= ' Only';

    return $words;
}

$full_name = trim($sample_receipt['surname'] . ' ' . $sample_receipt['student_name'] . ' ' . $sample_receipt['fathers_name']);
$amount_in_words = numberToWords($sample_receipt['amount']);
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipt Preview - <?php echo htmlspecialchars($config['organization_name'] ?? ''); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
</head>

<body>
    <div class="preview-header">
        <h2><i class="fas fa-receipt"></i> Receipt Print Layout Preview</h2>
        <div class="action-buttons">
            <button onclick="window.print()" class="btn btn-success">
                <i class="fas fa-print"></i> Print Preview
            </button>
            <a href="receipt-config.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Configuration
            </a>
        </div>
    </div>

    <div class="preview-note">
        <i class="fas fa-info-circle"></i>
        <strong>Preview Mode:</strong> This is a sample receipt showing how your configuration will appear. Actual
        receipts will contain real student and payment data.
    </div>

    <div class="receipt-container">
        <!-- Header -->
        <div class="receipt-header">
            <div class="logo-section">
                <?php if (!empty($config['logo_path'])): ?>
                    <?php
                    // Use BACKEND_URL for proper image path
                    $logo_path = str_replace('../', '', $config['logo_path']);
                    $logo_url = BACKEND_URL . '/' . $logo_path;
                    ?>
                    <img src="<?php echo htmlspecialchars($logo_url ?? ''); ?>" alt="Logo" onerror="this.style.display='none'">
                <?php else: ?>
                    <div class="logo-placeholder">No Logo<br>Uploaded</div>
                <?php endif; ?>
            </div>

            <div class="header-info">
                <div class="org-name"><?php echo htmlspecialchars($config['organization_name'] ?? ''); ?></div>
                <?php
                // Build full address string
                $address_text = $config['address'];
                if ($config['city']) {
                    $address_text .= ', ' . $config['city'];
                }
                if ($config['pincode']) {
                    $address_text .= '-' . $config['pincode'];
                }

                // Split address into lines
                // First, extract all parenthesized sections
                $lines = [];
                $pattern = '/\([^)]+\)|[^()]+/';
                preg_match_all($pattern, $address_text, $matches);

                foreach ($matches[0] as $part) {
                    $part = trim($part);
                    if (!empty($part)) {
                        // If it's a parenthesized section, add as separate line
                        if (preg_match('/^\(.+\)$/', $part)) {
                            $lines[] = htmlspecialchars($part ?? '');
                        } else {
                            // Non-parenthesized text
                            $lines[] = htmlspecialchars($part ?? '');
                        }
                    }
                }

                // Display each line
                foreach ($lines as $line) {
                    echo '<div class="org-address">' . $line . '</div>';
                }
                ?>
                <?php if ($config['phone'] || $config['email']): ?>
                    <div class="org-address">
                        <?php if ($config['phone']): ?>Tel: <?php echo htmlspecialchars($config['phone'] ?? ''); ?><?php endif; ?>
                        <?php if ($config['email']): ?> | Email:
                            <?php echo htmlspecialchars($config['email'] ?? ''); ?>     <?php endif; ?>
                    </div>
                <?php endif; ?>
                <div class="org-details">
                    <?php if ($config['pan_number']): ?>PAN :-
                        <?php echo htmlspecialchars($config['pan_number'] ?? ''); ?><br><?php endif; ?>
                    <?php if ($config['gst_number']): ?>GSTIN :-
                        <?php echo htmlspecialchars($config['gst_number'] ?? ''); ?><?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Student Information -->
        <div class="student-info">
            <div class="info-row">
                <div class="label">Student's Name</div>
                <div class="value"><?php echo strtoupper(htmlspecialchars($full_name ?? '')); ?></div>
            </div>
        </div>

        <!-- Receipt Meta Information -->
        <div class="receipt-meta">
            <div class="meta-item">
                <span class="label">Receipt No. :</span>
                <span class="value"><?php echo htmlspecialchars($sample_receipt['receipt_no'] ?? ''); ?></span>
            </div>
            <div class="meta-item">
                <span class="label">Date :</span>
                <span class="value"><?php echo date('d/m/Y', strtotime($sample_receipt['issued_date'])); ?></span>
            </div>
            <div class="meta-item">
                <span class="label">Pmt.Mode :</span>
                <span class="value"><?php echo ucfirst(htmlspecialchars($sample_receipt['payment_mode'] ?? '')); ?></span>
            </div>
        </div>

        <div class="receipt-meta">
            <div class="meta-item">
                <span class="label">Standard</span>
                <span class="value"><?php echo htmlspecialchars($sample_receipt['course_name'] ?? ''); ?></span>
            </div>
            <div class="meta-item">
                <span class="label">Term</span>
                <span class="value"><?php echo htmlspecialchars($sample_receipt['academic_year'] ?? ''); ?></span>
            </div>
            <div class="meta-item">
                <span class="label">Roll No</span>
                <span class="value"><?php echo htmlspecialchars($sample_receipt['aadhaar'] ?? ''); ?></span>
            </div>
        </div>

        <div class="meta-item css-receipt-config-preview-3301ea">
            <span class="label">GR No.</span>
            <span class="value"><?php echo htmlspecialchars($sample_receipt['student_id'] ?? ''); ?></span>
        </div>

        <!-- Fee Details Table -->
        <table class="fee-table">
            <thead>
                <tr>
                    <th class="sr-no">Sr. No.</th>
                    <th>Particulars</th>
                    <th class="amount">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="sr-no">1</td>
                    <td><?php echo htmlspecialchars($sample_receipt['payment_for'] ?? ''); ?></td>
                    <td class="amount"><?php echo formatIndianCurrency($sample_receipt['amount'], false); ?></td>
                </tr>
                <tr class="total-row">
                    <td colspan="2" class="css-receipt-config-preview-e701f2">Total</td>
                    <td class="amount css-receipt-config-preview-6d007e"><?php echo formatIndianCurrency($sample_receipt['amount'], false); ?></td>
                </tr>
            </tbody>
        </table>

        <!-- Amount in Words -->
        <div class="amount-words">
            <span class="label">Rupees :</span>
            <span><?php echo $amount_in_words; ?></span>
        </div>

        <!-- Bank Details -->
        <div class="bank-details">
            <strong>Subject to realization of cheque</strong>
            <div>Name of Bank</div>
            <div>Cheque / D.D. No.</div>
        </div>

        <!-- Bottom Section with Three Columns -->
        <div class="bottom-section">
            <div class="cheque-info">
                Cheque / D.D. No.
            </div>

            <div class="jurisdiction-center">
                <?php if ($config['city']): ?>
                    SUBJECT TO <?php echo strtoupper(htmlspecialchars($config['city'] ?? '')); ?> JURISDICTION
                <?php endif; ?>
            </div>

            <div class="signature-right">
                <?php if (!empty($config['signature_path'])): ?>
                    <?php
                    // Use BACKEND_URL for proper image path
                    $sig_path = str_replace('../', '', $config['signature_path']);
                    $sig_url = BACKEND_URL . '/' . $sig_path;
                    ?>
                    <img src="<?php echo htmlspecialchars($sig_url ?? ''); ?>" alt="Signature"
                        style="max-width: 150px; max-height: 60px;" onerror="this.style.display='none'">
                <?php endif; ?>
                <div class="signature-box">
                    <div class="signature-line">Authorised Signatory</div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>