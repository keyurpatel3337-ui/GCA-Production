<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/format_helper.php';

// Check if user is Accountant OR Student
$is_student = isset($_SESSION['is_student_login']) && $_SESSION['is_student_login'] === true;
$is_accountant = !$is_student && hasRole(ROLE_ACCOUNTANT);

if (!$is_accountant && !$is_student) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// Get receipt ID, receipt number, or student ID
$receipt_id = $_REQUEST['id'] ?? null;
$receipt_ids = $_REQUEST['ids'] ?? null;
$receipt_no = $_REQUEST['receipt_no'] ?? null;
$fetch_student_id = $_REQUEST['student_id'] ?? null;

if (!$receipt_id && !$receipt_ids && !$receipt_no && !$fetch_student_id) {
    set_flash_message('error', "Receipt ID, number, or student ID is required!");
    header('Location: ' . ($is_student ? '../student-portal/my-fees.php' : PORTAL_URL . '/modules/reports/financial/receipt-register.php'));
    exit;
}

// Fetch receipt details
try {
    $op = new Operation();
    $receipts = [];

    // Common Joins for tbl_payments
    $joins = [
        ['type' => 'INNER', 'table' => 'tbl_gm_std_registration s', 'on' => 'p.student_id = s.id'],
        ['type' => 'LEFT', 'table' => 'tbl_boards b', 'on' => 's.board_id = b.id'],
        ['type' => 'LEFT', 'table' => 'tbl_medium m', 'on' => 's.medium_id = m.id'],
        ['type' => 'LEFT', 'table' => 'tbl_group g', 'on' => 's.group_id = g.id'],
        ['type' => 'LEFT', 'table' => 'tbl_enrolled_students e', 'on' => 's.id = e.registration_id AND e.is_active = 1'],
        ['type' => 'LEFT', 'table' => 'tbl_academic_years ay', 'on' => 's.academic_year_id = ay.id'],
        ['type' => 'LEFT', 'table' => 'tbl_users u', 'on' => 'p.created_by = u.id']
    ];

    $select_fields = [
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
        's.addr',
        's.school_id',
        'b.board_name',
        'm.medium_name',
        'g.group_name',
        'e.roll_no',
        'ay.year_name as academic_year',
        'u.name as issued_by_name'
    ];

    if ($is_student && $fetch_student_id && !$receipt_no) {
        $student_id = $_SESSION['student_id'];
        if ($fetch_student_id != $student_id) {
            set_flash_message('error', "Unauthorized access!");
            header('Location: ../student-portal/my-fees.php');
            exit;
        }

        $receipts = $op->selectWithJoin(
            'tbl_payments p',
            $select_fields,
            $joins,
            ['p.student_id' => $student_id, 'p.payment_type' => 'token_fee'],
            "CASE p.fee_component WHEN 'school_fee' THEN 1 WHEN 'trust_facilities_fee' THEN 2 WHEN 'tuition_fee_part1' THEN 3 ELSE 4 END"
        );
    } else {
        $conditions = [];
        if ($receipt_ids) {
            $ids_array = explode(',', $receipt_ids);
            $ids_array = array_filter(array_map('intval', $ids_array));
            if (!empty($ids_array)) {
                $placeholders = implode(',', array_fill(0, count($ids_array), '?'));
                $stmt = $conn->prepare("
                    SELECT " . implode(', ', $select_fields) . "
                    FROM tbl_payments p
                    INNER JOIN tbl_gm_std_registration s ON p.student_id = s.id
                    LEFT JOIN tbl_boards b ON s.board_id = b.id
                    LEFT JOIN tbl_medium m ON s.medium_id = m.id
                    LEFT JOIN tbl_group g ON s.group_id = g.id
                    LEFT JOIN tbl_enrolled_students e ON s.id = e.registration_id AND e.is_active = 1
                    LEFT JOIN tbl_academic_years ay ON s.academic_year_id = ay.id
                    LEFT JOIN tbl_users u ON p.created_by = u.id
                    WHERE p.id IN ($placeholders)
                    ORDER BY FIELD(p.id, $placeholders)
                ");
                $stmt->execute(array_merge($ids_array, $ids_array));
                $receipts = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Set conditions to empty to avoid processing below
                $conditions = [];
            }
        } elseif ($receipt_id) {
            $conditions['p.id'] = $receipt_id;
        } elseif ($receipt_no) {
            $conditions['p.receipt_no'] = $receipt_no;
            if ($fetch_student_id)
                $conditions['p.student_id'] = $fetch_student_id;
        }

        if (!empty($conditions)) {
            // Add fee_component filter if provided
            if (isset($_REQUEST['fee_component']) && !empty($_REQUEST['fee_component'])) {
                $conditions['p.fee_component'] = $_REQUEST['fee_component'];
            }
            $receipt = $op->readWithJoin('tbl_payments p', $select_fields, $joins, $conditions);

            if ($receipt) {
                if (($receipt['payment_type'] ?? '') == 'token_fee') {
                    $receipts = $op->selectWithJoin(
                        'tbl_payments p',
                        $select_fields,
                        $joins,
                        ['p.student_id' => $receipt['student_id'], 'p.payment_type' => 'token_fee'],
                        "CASE p.fee_component WHEN 'school_fee' THEN 1 WHEN 'trust_facilities_fee' THEN 2 WHEN 'tuition_fee_part1' THEN 3 ELSE 4 END"
                    );
                } else {
                    $receipts = [$receipt];
                }
            }
        }
    }

    // Format for display
    foreach ($receipts as &$r) {
        $r['payment_for'] = ucfirst(str_replace('_', ' ', $r['payment_type'] ?? 'Fee')) . ' Payment';
    }
    unset($r);

    if (empty($receipts)) {
        set_flash_message('error', "Receipt not found!");
        header('Location: ' . ($is_student ? '../student-portal/my-fees.php' : PORTAL_URL . '/modules/reports/financial/receipt-register.php'));
        exit;
    }

    // For token fee payments with multiple receipts, use the same receipt number for all
    if (count($receipts) > 1 && isset($receipts[0]['payment_type']) && $receipts[0]['payment_type'] == 'token_fee') {
        $common_receipt_no = $receipts[0]['receipt_no']; // Use first receipt number for all
        foreach ($receipts as &$receipt) {
            $receipt['receipt_no'] = $common_receipt_no;
        }
        unset($receipt); // Break reference
    }

    // Get student's school_id
    $student_school_id = $receipts[0]['school_id'] ?? null;

    // Get receipt configurations for each fee component (for ALL receipts)
    $receipt_configs = [];
    if (isset($receipts[0]['fee_component'])) {
        require_once __DIR__ . '/../../../common/helpers/receipt_mapping_functions.php';
        foreach ($receipts as $r) {
            $fee_component = $r['fee_component'];
            $config_id = getReceiptConfigForFee($conn, $fee_component, $student_school_id);
            if ($config_id) {
                $config_details = getReceiptConfigDetails($conn, $config_id);
                $receipt_configs[$fee_component] = $config_details;
            }
        }
    }

    // Get active receipt configuration (fallback)
    $op = new Operation();
    $config = $op->selectOne('tbl_receipt_configuration', ['*'], ['is_active' => 1]);

    if (!$config) {
        set_flash_message('error', "Receipt configuration not found! Please configure receipt settings first.");
        header('Location: ' . ($is_student ? '../student-portal/my-fees.php' : PORTAL_URL . '/modules/reports/financial/receipt-register.php'));
        exit;
    }

} catch (Exception $e) {
    logError("Receipt Preview Error: " . $e->getMessage());
    set_flash_message('error', "Failed to load receipt!");
    header('Location: ' . ($is_student ? '../student-portal/my-fees.php' : PORTAL_URL . '/modules/reports/financial/receipt-register.php'));
    exit;
}

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

$full_name = trim(($receipts[0]['surname'] ?? '') . ' ' . ($receipts[0]['student_name'] ?? '') . ' ' . ($receipts[0]['fathers_name'] ?? ''));
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Print Receipt - <?php echo htmlspecialchars($receipts[0]['receipt_no'] ?? ''); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            font-size: 13px;
            line-height: 1.4;
            color: #000;
            background: #fff;
        }

        .receipt-container {
            width: 210mm;
            min-height: 148mm;
            margin: 0 auto;
            padding: 8mm;
            border: 1px solid #000;
            background: #fff;
            position: relative;
            page-break-after: always;
        }

        .receipt-header {
            border-bottom: 1px solid #000;
            padding-bottom: 8px;
            margin-bottom: 10px;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
        }

        .logo-section {
            flex: 0 0 80px;
        }

        .logo-section img {
            width: 80px;
            height: 80px;
            object-fit: contain;
        }

        .header-info {
            flex: 1;
            text-align: left;
            padding: 0 15px;
        }

        .org-name {
            font-size: 22px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        .org-address {
            font-size: 13px;
            margin-bottom: 2px;
            max-width: 500px;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .org-details {
            flex: 0 0 160px;
            font-size: 12px;
            text-align: right;
            padding-top: 15px;
        }

        .student-info-box {
            border: 1px solid #000;
            padding: 4px 10px;
            margin: 8px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .student-info-box .label {
            font-weight: bold;
            font-size: 11px;
        }

        .receipt-meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 4px 15px;
            margin: 8px 0;
        }

        .meta-item {
            display: flex;
            font-size: 10.5px;
        }

        .meta-item .label {
            font-weight: bold;
            flex: 0 0 75px;
        }

        .meta-item .colon {
            flex: 0 0 12px;
            font-weight: bold;
            text-align: center;
        }

        .fee-table {
            width: 100%;
            border-collapse: collapse;
            margin: 8px 0;
        }

        .fee-table th,
        .fee-table td {
            border: 1px solid #000;
            padding: 4px 8px;
            text-align: left;
        }

        .fee-table th {
            font-weight: bold;
            text-align: center;
            font-size: 13px;
        }

        .fee-table td {
            font-size: 13px;
        }

        .fee-table .amount {
            text-align: right;
            width: 100px;
        }

        .amount-words-box {
            border: 1px solid #000;
            padding: 4px 10px;
            margin: 8px 0;
            display: flex;
            font-size: 10.5px;
        }

        .amount-words-box .label {
            font-weight: bold;
            margin-right: 10px;
        }

        .bottom-section {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
        }

        .payment-notes {
            flex: 1;
            font-size: 10px;
        }

        .payment-notes p {
            margin-bottom: 2px;
        }

        .signature-section {
            flex: 0 0 180px;
            text-align: center;
        }

        .signature-box {
            border: 1px solid #000;
            height: 70px;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .signature-box img {
            max-width: 160px;
            max-height: 60px;
        }

        .auth-label {
            font-weight: bold;
            font-size: 10.5px;
        }

        .jurisdiction {
            margin-top: 8px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .system-generated {
            font-size: 8px;
            font-style: italic;
            color: #444;
        }

        @media print {
            body {
                background: #fff;
                padding: 0;
            }

            .receipt-container {
                box-shadow: none;
                margin: 0 auto;
                border: 1px solid #000;
            }

            .no-print {
                display: none !important;
            }
        }
    </style>
</head>

<body>
    <?php foreach ($receipts as $receipt):
        $current_config = $config;
        if (isset($receipt['fee_component']) && isset($receipt_configs[$receipt['fee_component']])) {
            $current_config = $receipt_configs[$receipt['fee_component']];
        }
        $amount_in_words = numberToWords($receipt['amount']);
        ?>

        <div class="receipt-container">
            <!-- Header -->
            <div class="receipt-header">
                <div class="logo-section">
                    <?php if (!empty($current_config['logo_path'])): ?>
                        <?php
                        $logo_path = str_replace('../', '', $current_config['logo_path']);
                        $logo_url = BACKEND_URL . '/' . $logo_path;
                        ?>
                        <img src="<?php echo htmlspecialchars($logo_url ?? ''); ?>" alt="Logo">
                    <?php endif; ?>
                </div>

                <div class="header-info">
                    <div class="org-name"><?php echo htmlspecialchars($current_config['organization_name'] ?? ''); ?></div>
                    <div class="org-address"><?php echo htmlspecialchars($current_config['address'] ?? ''); ?></div>
                    <div class="org-address">
                        <?php
                        $city_pin = trim(($current_config['city'] ?? '') . ($current_config['pincode'] ? ' - ' . $current_config['pincode'] : ''));
                        echo htmlspecialchars($city_pin ?? '');
                        ?>
                    </div>

                    <?php if ($current_config['phone'] || $current_config['email']): ?>
                        <div class="org-address">
                            <?php if ($current_config['phone'])
                                echo 'Tel: ' . htmlspecialchars($current_config['phone'] ?? ''); ?>
                            <?php if ($current_config['email'])
                                echo ' | Email: ' . htmlspecialchars($current_config['email'] ?? ''); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="org-details">
                    <?php if ($current_config['pan_number']): ?>
                        <div>PAN : <?php echo htmlspecialchars($current_config['pan_number'] ?? ''); ?></div>
                    <?php endif; ?>
                    <?php if ($current_config['gst_number']): ?>
                        <div>GSTIN : <?php echo htmlspecialchars($current_config['gst_number'] ?? ''); ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Student Info Box -->
            <div class="student-info-box">
                <div>
                    <span class="label">Student's Name :</span>
                    <span><?php echo strtoupper(htmlspecialchars($full_name ?? '')); ?></span>
                </div>
                <div>
                    <span class="label">Date :</span>
                    <span style="margin-left: 40px;"><?php echo date('d/m/Y', strtotime($receipt['issued_date'])); ?></span>
                </div>
            </div>

            <!-- Meta Grid -->
            <div class="receipt-meta-grid">
                <div class="meta-item">
                    <span class="label">Standard</span>
                    <span class="colon">:</span>
                    <span><?php
                    $display_std = $receipt['course_name'] ?? $receipt['standard'] ?? 'N/A';
                    if (($receipt['course_id'] ?? 0) == 3 || strtolower(trim((string) $display_std)) === 're-neet') {
                        $display_std = 'Re-Neet';
                    }
                    echo htmlspecialchars($display_std);
                    ?></span>
                </div>
                <div class="meta-item">
                    <span class="label">Group</span>
                    <span class="colon">:</span>
                    <span><?php echo htmlspecialchars($receipt['group_name'] ?? 'N/A'); ?></span>
                </div>
                <div class="meta-item">
                    <span class="label">Medium</span>
                    <span class="colon">:</span>
                    <span><?php echo htmlspecialchars($receipt['medium_name'] ?? 'N/A'); ?></span>
                </div>

                <div class="meta-item">
                    <span class="label">Receipt No.</span>
                    <span class="colon">:</span>
                    <span><?php echo htmlspecialchars($receipt['receipt_no'] ?? ''); ?></span>
                </div>
                <div class="meta-item">
                    <span class="label">Roll No.</span>
                    <span class="colon">:</span>
                    <span><?php echo htmlspecialchars($receipt['roll_no'] ?? 'N/A'); ?></span>
                </div>
                <div class="meta-item">
                    <span class="label">Pmt.Mode</span>
                    <span class="colon">:</span>
                    <span><?php echo ucfirst(htmlspecialchars($receipt['payment_mode'] ?? '')); ?></span>
                </div>

                <div class="meta-item">
                    <span class="label">Year</span>
                    <span class="colon">:</span>
                    <span><?php echo htmlspecialchars($receipt['academic_year'] ?? 'N/A'); ?></span>
                </div>
                <?php if (($receipt['course_id'] ?? 0) != 3): ?>
                    <div class="meta-item">
                        <span class="label">Term</span>
                        <span class="colon">:</span>
                        <span><?php echo htmlspecialchars($receipt['semester'] ?? 'Semester 1'); ?></span>
                    </div>
                <?php endif; ?>
                <div class="meta-item">
                    <span class="label">GR No.</span>
                    <span class="colon">:</span>
                    <span><?php echo htmlspecialchars($receipt['student_id'] ?? '0'); ?></span>
                </div>
            </div>

            <!-- Fee Table -->
            <table class="fee-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">Sr. No.</th>
                        <th>Particulars</th>
                        <th class="amount">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="text-align: center;">1</td>
                        <td>
                            <?php
                            $p_text = $receipt['payment_for'] ?? 'Fee Payment';
                            if (isset($receipt['fee_component'])) {
                                switch ($receipt['fee_component']) {
                                    case 'school_fee':
                                        $p_text = 'School Fee';
                                        break;
                                    case 'trust_facilities_fee':
                                        $p_text = 'Trust Facilities Fee';
                                        break;
                                    case 'tuition_fee_part1':
                                        $p_text = 'Tuition Fee Part 1 (including CGST 9% + SGST 9%)';
                                        break;
                                    case 'tuition_fee_part2':
                                        $p_text = 'Tuition Fee Part 2 (including CGST 9% + SGST 9%)';
                                        break;
                                    case 'hostel_fee':
                                    case 'hostel_security':
                                        $p_text = 'Hostel Fee';
                                        break;
                                    default:
                                        $p_text = ucfirst(str_replace('_', ' ', $receipt['fee_component']));
                                }
                            }
                            echo $p_text;
                            ?>
                        </td>
                        <td class="amount"><?php echo formatIndianCurrency($receipt['amount']); ?></td>
                    </tr>
                    <tr style="height: 30px;">
                        <td style="border-bottom: 1px solid #000;"></td>
                        <td style="border-bottom: 1px solid #000;"></td>
                        <td style="border-bottom: 1px solid #000;"></td>
                    </tr>
                </tbody>
            </table>

            <!-- Amount in words with Total -->
            <div class="amount-words-box">
                <div style="flex: 1; display: flex;">
                    <span class="label">Rupees :</span>
                    <span style="font-weight: 500;"><?php echo $amount_in_words; ?></span>
                </div>
                <div style="flex: 0 0 150px; text-align: right; border-left: 1px solid #000; padding-left: 10px;">
                    <span class="label" style="margin-right: 5px;">Total:</span>
                    <span
                        style="font-weight: bold; font-size: 14px;"><?php echo formatIndianCurrency($receipt['amount']); ?></span>
                </div>
            </div>

            <!-- Bottom Section -->
            <div class="bottom-section">
                <div class="payment-notes">
                    <p><strong>Subject to realization of cheque</strong></p>
                    <p>Bank Name: <?php echo htmlspecialchars($receipt['bank_name'] ?? '___________'); ?></p>
                    <p>Cheque / D.D. No.: <?php echo htmlspecialchars($receipt['cheque_no'] ?? '___________'); ?></p>
                    <p>Transaction ID: <?php echo htmlspecialchars($receipt['transaction_id'] ?? '___________'); ?></p>
                    <?php if (!empty($receipt['payment_id'])): ?>
                        <p>Payment ID: <?php echo $receipt['payment_id']; ?></p>
                    <?php endif; ?>

                    <div class="jurisdiction">SUBJECT TO BHAVNAGAR JURISDICTION</div>
                    <div class="system-generated">*This receipt is valid without signature as it is generated by the
                        system.*</div>
                </div>

                <div class="signature-section">
                    <div class="signature-box">
                        <?php
                        $sig_url = null;
                        if (isset($receipt['created_by']) && $receipt['created_by'] == 70) {
                            // Check if the image exists in assets
                            $bhautik_sig_path = dirname(__DIR__, 3) . '/assets/images/Bhautik_dumaniya.png';
                            if (file_exists($bhautik_sig_path)) {
                                $sig_url = BASE_URL . '/assets/images/Bhautik_dumaniya.png';
                            }
                        }

                        if (!$sig_url && !empty($current_config['signature_path'])):
                            $sig_path = str_replace('../', '', $current_config['signature_path']);
                            $sig_url = BACKEND_URL . '/' . $sig_path;
                        endif;

                        if ($sig_url): ?>
                            <img src="<?php echo htmlspecialchars($sig_url ?? ''); ?>" alt="Signature">
                        <?php endif; ?>
                    </div>
                    <div class="auth-label">Authorised Signatory</div>
                </div>
            </div>

            <?php if (in_array(($receipt['course_id'] ?? null), [1, '1'])): ?>
                <div style="margin-top: 20px; border-top: 1px dashed #ccc; padding-top: 10px;">
                    <h4
                        style="background: #000; color: #fff; padding: 2px 5px; display: inline-block; margin-bottom: 10px; font-size: 13px;">
                        REFUND POLICY :</h4>
                    <div style="margin-bottom: 5px; font-size: 11px;">
                        <strong>Who paid only Tokan fee.</strong>
                        <ul style="list-style: none; margin-left: 15px; margin-top: 5px;">
                            <li><span style="font-weight: bold; margin-right: 5px;">></span> Tokan Fee will be non-refundable.
                            </li>
                            <li><span style="font-weight: bold; margin-right: 5px;">></span> Tokan Fee will be refundable only
                                for the Pre-admitted student who has cancelled admission up to 15th March</li>
                        </ul>
                    </div>
                    <div style="font-size: 11px;">
                        <strong>Who paid full Fees and cancelled the admission.</strong>
                        <ul style="list-style: none; margin-left: 15px; margin-top: 5px;">
                            <li><span style="font-weight: bold; margin-right: 5px;">></span> <span
                                    style="display:inline-block; width: 100px;">1 to 30 Days</span> : 80% Fees Refundable.</li>
                            <li><span style="font-weight: bold; margin-right: 5px;">></span> <span
                                    style="display:inline-block; width: 100px;">31 to 60 Days</span> : 65% Fees Refundable.</li>
                            <li><span style="font-weight: bold; margin-right: 5px;">></span> <span
                                    style="display:inline-block; width: 100px;">61 to 90 Days</span> : 50% Fees Refundable.</li>
                            <li><span style="font-weight: bold; margin-right: 5px;">></span> <span
                                    style="display:inline-block; width: 100px;">After 90 Days</span> : Fees will be
                                Non-Refundable.</li>
                        </ul>
                    </div>
                </div>
            <?php elseif (($receipt['course_id'] ?? null) == 3): ?>
                <div style="margin-top: 20px; border-top: 1px dashed #ccc; padding-top: 10px;">
                    <h4 style="text-decoration: underline; margin-bottom: 8px; font-size: 13px;">Rules and fees policy are as
                        under :</h4>
                    <ul style="list-style: none; font-size: 11px;">
                        <li style="margin-bottom: 4px;"><span
                                style="display:inline-block; width: 20px; text-align:right; margin-right: 5px;">1&gt;</span>
                            Student should attain all classes and lecture without being absent.</li>
                        <li style="margin-bottom: 4px;"><span
                                style="display:inline-block; width: 20px; text-align:right; margin-right: 5px;">2&gt;</span>
                            Student should keep discipline and obey all rules made by academy.</li>
                        <li style="margin-bottom: 4px;"><span
                                style="display:inline-block; width: 20px; text-align:right; margin-right: 5px;">3&gt;</span>
                            Attain all seminar's and tests.</li>
                    </ul>
                    <h4 style="text-decoration: underline; margin-top: 10px; margin-bottom: 8px; font-size: 13px;">Fees policy :
                    </h4>
                    <ul style="list-style: none; font-size: 11px;">
                        <li style="margin-bottom: 4px;"><span
                                style="display:inline-block; width: 20px; text-align:right; margin-right: 5px;">1&gt;</span>
                            Parents must pay fees regularly, each installment in time limit.</li>
                        <li style="margin-bottom: 4px;"><span
                                style="display:inline-block; width: 20px; text-align:right; margin-right: 5px;">2&gt;</span> In
                            case of full fees payment, fees will refunded by refund policy made by academy are as under :
                            <ul style="list-style: none; margin-left: 25px; margin-top: 4px;">
                                <li>(a) After 1 week of starting of classes, 80% of fee will be refunded.</li>
                                <li>(b) After 2 week of starting of classes, 60% of fee will be refunded.</li>
                                <li>(c) After 3 week of starting of classes, 50% of fee will be refunded.</li>
                                <li>(d) After 4 week of starting of classes, 40% of fee will be refunded.</li>
                                <li>(e) After 28 days of starting of classes, fee will not be refunded.</li>
                            </ul>
                        </li>
                        <li style="margin-top: 4px;"><span
                                style="display:inline-block; width: 20px; text-align:right; margin-right: 5px;">3&gt;</span> For
                            benefit of refund, parents / student should apply for the same in prescribed format.</li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

</body>

</html>