<?php
/**
 * GST Report PDF Export
 */

ob_start();
require_once __DIR__ . '/../../../session_config.php';
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once __DIR__ . '/../../../../common/helpers/format_helper.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

if (!hasRole(ROLE_ACCOUNTANT) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

try {
    $from_date = $_GET['from_date'] ?? date('Y-m-01');
    $to_date = $_GET['to_date'] ?? date('Y-m-d');
    $search = $_GET['search'] ?? '';
    $dbOps = new DatabaseOperations();

    $whereConditions = ["p.status = 'paid'", "p.payment_date BETWEEN ? AND ?", "(p.payment_type LIKE '%Tuition Fee Part 1%' OR p.payment_type LIKE '%Tuition Fee Part 2%')"];
    $params = [$from_date, $to_date];

    if (!empty($search)) {
        $searchTerm = "%$search%";
        $whereConditions[] = "(CONCAT(r.surname, ' ', r.student_name, ' ', IFNULL(r.fathers_name, '')) LIKE ? OR r.mob LIKE ? OR r.id LIKE ? OR p.receipt_no LIKE ?)";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    $whereSql = implode(" AND ", $whereConditions);
    $sql = "SELECT 
                p.payment_date, p.payment_type, p.payment_mode,
                SUM(p.amount) as total_amount,
                COUNT(*) as txn_count
            FROM tbl_payments p
            JOIN tbl_gm_std_registration r ON p.student_id = r.id
            WHERE $whereSql
            GROUP BY p.payment_date, p.payment_type, p.payment_mode
            ORDER BY p.payment_date DESC, p.payment_type ASC";

    $payments = $dbOps->customSelect($sql, $params);

    $totalCollection = 0;
    $totalTaxable = 0;
    $totalGST = 0;
    $totalCGST = 0;
    $totalSGST = 0;
    $processedRecords = [];

    foreach ($payments as $p) {
        $taxable = $p['total_amount'] / 1.18;
        $gst = $p['total_amount'] - $taxable;
        $cgst = $sgst = $gst / 2;

        $types = [];
        if (stripos($p['payment_type'], 'Tuition Fee Part 1') !== false) {
            $types[] = "Token Fee (P1)";
        } else if (stripos($p['payment_type'], 'Token Fee') !== false) {
            $types[] = "Token Fee (P1)";
        }
        if (stripos($p['payment_type'], 'Tuition Fee Part 2') !== false) {
            $types[] = "Tuition Fee P2";
        }
        $displayType = implode(', ', $types);
        if (empty($displayType)) {
            $displayType = $p['payment_type'];
        }

        $processedRecords[] = [
            'payment_date' => $p['payment_date'],
            'payment_mode' => $p['payment_mode'],
            'txn_count' => $p['txn_count'],
            'display_type' => $displayType,
            'amount' => $p['total_amount'],
            'taxable' => $taxable,
            'cgst' => $cgst,
            'sgst' => $sgst,
            'gst' => $gst
        ];

        $totalCollection += $p['total_amount'];
        $totalTaxable += $taxable;
        $totalGST += $gst;
        $totalCGST += $cgst;
        $totalSGST += $sgst;
    }

    $config = $dbOps->customSelect("SELECT * FROM tbl_receipt_configuration WHERE is_active = 1 LIMIT 1", []);
    $config = $config[0] ?? null;

    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('GCA Management System');
    $pdf->SetTitle('GST Report');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(true);
    $pdf->setFooterFont(array(PDF_FONT_NAME_DATA, '', 7));
    $pdf->SetMargins(5, 5, 5);
    $pdf->SetAutoPageBreak(TRUE, 10);
    $pdf->AddPage();

    $org_address = ($config['address'] ?? '') . ', ' . ($config['city'] ?? '');
    $headerHtml = '
    <table cellpadding="2" class="css-gst-report-pdf-8588e4">
        <tr>
            <td class="css-gst-report-pdf-539b04">
                <span class="css-gst-report-pdf-86c905">' . htmlspecialchars($config['organization_name'] ?? SYSTEM_NAME) . '</span><br>
                <span class="css-gst-report-pdf-1b8847">' . htmlspecialchars($org_address ?? '') . '</span><br>
                <span class="css-gst-report-pdf-0c5bfa">GST DETAILED REPORT (DATE-WISE)</span><br>
                <span class="css-gst-report-pdf-1b8847">Period: ' . date('d-m-Y', strtotime($from_date)) . ' to ' . date('d-m-Y', strtotime($to_date)) . '</span>
            </td>
        </tr>
    </table>';
    $pdf->writeHTML($headerHtml, true, false, false, false, '');
    $pdf->Ln(2);

    $html = '
    <table border="0.5" cellpadding="4" class="css-gst-report-pdf-6eb086">
        <thead>
            <tr class="css-gst-report-pdf-de3663">
                <th width="5%" class="css-gst-report-pdf-539b04">#</th>
                <th width="10%">Date</th>
                <th width="15%">Fee Type</th>
                <th width="10%">Mode</th>
                <th width="5%" class="css-gst-report-pdf-539b04">Count</th>
                <th width="11%" class="css-gst-report-pdf-08a0ed">Total Amount</th>
                <th width="11%" class="css-gst-report-pdf-08a0ed">Taxable Value</th>
                <th width="11%" class="css-gst-report-pdf-08a0ed">CGST (9%)</th>
                <th width="11%" class="css-gst-report-pdf-08a0ed">SGST (9%)</th>
                <th width="11%" class="css-gst-report-pdf-08a0ed">Total GST</th>
            </tr>
        </thead>
        <tbody>';

    if (empty($processedRecords)) {
        $html .= '<tr><td colspan="10" class="css-gst-report-pdf-539b04">No records found</td></tr>';
    } else {
        $i = 1;
        foreach ($processedRecords as $row) {
            $html .= '
            <tr nobr="true">
                <td width="5%" class="css-gst-report-pdf-539b04">' . $i++ . '</td>
                <td width="10%">' . date('d-m-Y', strtotime($row['payment_date'])) . '</td>
                <td width="15%">' . htmlspecialchars($row['display_type'] ?? '') . '</td>
                <td width="10%">' . ucfirst($row['payment_mode'] ?? '') . '</td>
                <td width="5%" class="css-gst-report-pdf-539b04">' . $row['txn_count'] . '</td>
                <td width="11%" class="css-gst-report-pdf-08a0ed">' . formatIndianCurrency($row['amount']) . '</td>
                <td width="11%" class="css-gst-report-pdf-08a0ed">' . formatIndianCurrency($row['taxable']) . '</td>
                <td width="11%" class="css-gst-report-pdf-08a0ed">' . formatIndianCurrency($row['cgst']) . '</td>
                <td width="11%" class="css-gst-report-pdf-08a0ed">' . formatIndianCurrency($row['sgst']) . '</td>
                <td width="11%" class="css-gst-report-pdf-714e9d">' . formatIndianCurrency($row['gst']) . '</td>
            </tr>';
        }
    }

    $html .= '
            <tr class="css-gst-report-pdf-bf35f5">
                <td width="45%" colspan="5" class="css-gst-report-pdf-08a0ed">GRAND TOTAL</td>
                <td width="11%" class="css-gst-report-pdf-08a0ed">' . formatIndianCurrency($totalCollection) . '</td>
                <td width="11%" class="css-gst-report-pdf-08a0ed">' . formatIndianCurrency($totalTaxable) . '</td>
                <td width="11%" class="css-gst-report-pdf-08a0ed">' . formatIndianCurrency($totalCGST) . '</td>
                <td width="11%" class="css-gst-report-pdf-08a0ed">' . formatIndianCurrency($totalSGST) . '</td>
                <td width="11%" class="css-gst-report-pdf-08a0ed">' . formatIndianCurrency($totalGST) . '</td>
            </tr>
        </tbody>
    </table>';

    $pdf->writeHTML($html, true, false, false, false, '');

    if (ob_get_length())
        ob_clean();
    $filename = 'GST_Report_' . $from_date . '_to_' . $to_date . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $pdf->Output($filename, 'D');
    exit;

} catch (Exception $e) {
    if (ob_get_length())
        ob_clean();
    die("PDF Error: " . $e->getMessage());
}

