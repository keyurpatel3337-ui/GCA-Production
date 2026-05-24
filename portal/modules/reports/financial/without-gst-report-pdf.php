<?php
/**
 * Direct Collection Report PDF Export
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
    $course_filter = $_GET['course_id'] ?? '';
    $payment_type_filter = $_GET['payment_type'] ?? '';
    $receipt_config_filter = $_GET['receipt_config_id'] ?? '';

    $dbOps = new DatabaseOperations();

    $whereConditions = ["p.status = 'paid'", "p.payment_date BETWEEN ? AND ?"];
    $params = [$from_date, $to_date];

    if (!empty($search)) {
        $searchTerm = "%$search%";
        $whereConditions[] = "(CONCAT(r.surname, ' ', r.student_name, ' ', IFNULL(r.fathers_name, '')) LIKE ? OR r.mob LIKE ? OR r.id LIKE ? OR p.transaction_id LIKE ?)";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    if (!empty($course_filter)) {
        if ($course_filter === '11th') {
            $whereConditions[] = "r.course_id = 1";
        } elseif ($course_filter === '12th') {
            $whereConditions[] = "r.course_id = 2";
        } elseif ($course_filter === 'Reneet') {
            $whereConditions[] = "r.course_id = 3";
        } else {
            $whereConditions[] = "r.course_id = ?";
            $params[] = $course_filter;
        }
    }

    if (!empty($payment_type_filter)) {
        $whereConditions[] = "p.payment_type = ?";
        $params[] = $payment_type_filter;
    }

    if (!empty($receipt_config_filter)) {
        $whereConditions[] = "p.receipt_config_id = ?";
        $params[] = $receipt_config_filter;
    }

    $whereSql = implode(" AND ", $whereConditions);
    $sql = "SELECT 
                p.payment_date, p.amount, p.payment_mode, p.transaction_id, p.fee_component,
                CONCAT(r.surname, ' ', r.student_name, ' ', IFNULL(r.fathers_name, '')) as student_full_name,
                c.course_name,
                m.medium_name,
                CONCAT(c.course_name, ' - ', m.medium_name) as standard_display
            FROM tbl_payments p
            JOIN tbl_gm_std_registration r ON p.student_id = r.id
            LEFT JOIN tbl_enrolled_students es ON es.registration_id = r.id AND es.is_active = 1
            LEFT JOIN tbl_courses c ON r.course_id = c.id
            LEFT JOIN tbl_medium m ON r.medium_id = m.id
            WHERE p.receipt_no = '0' AND $whereSql
            ORDER BY p.payment_date DESC, p.id DESC";

    $payments = $dbOps->customSelect($sql, $params);

    $totalCollection = 0;
    foreach ($payments as $p) {
        $totalCollection += $p['amount'];
    }

    $config = $dbOps->customSelect("SELECT * FROM tbl_receipt_configuration WHERE is_active = 1 LIMIT 1", []);
    $config = $config[0] ?? null;

    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('GCA Management System');
    $pdf->SetTitle('Direct Collection Report');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(true);
    $pdf->setFooterFont(array(PDF_FONT_NAME_DATA, '', 7));
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetAutoPageBreak(TRUE, 15);

    // Set font to DejaVuSans to support Rupee symbol
    $pdf->SetFont('dejavusans', '', 9);

    $pdf->AddPage();

    $org_address = ($config['address'] ?? '') . ', ' . ($config['city'] ?? '');
    $headerHtml = '
    <table cellpadding="2" class="css-without-gst-report-pdf-8588e4">
        <tr>
            <td class="css-without-gst-report-pdf-539b04">
                <span class="css-without-gst-report-pdf-86c905">' . htmlspecialchars($config['organization_name'] ?? SYSTEM_NAME) . '</span><br>
                <span class="css-without-gst-report-pdf-1b8847">' . htmlspecialchars($org_address ?? '') . '</span><br>
                <div class="css-without-gst-report-pdf-383745">
                    <span class="css-without-gst-report-pdf-17ae11">Direct Collection Report</span>
                </div><br>
                <span class="css-without-gst-report-pdf-1b8847">Period: ' . date('d-m-Y', strtotime($from_date)) . ' to ' . date('d-m-Y', strtotime($to_date)) . '</span>
            </td>
        </tr>
    </table>';
    $pdf->writeHTML($headerHtml, true, false, false, false, '');
    $pdf->Ln(5);

    $html = '
    <table border="0.5" cellpadding="6" class="css-without-gst-report-pdf-c6ad08">
        <thead>
            <tr class="css-without-gst-report-pdf-be335d">
                <th width="5%" class="css-without-gst-report-pdf-539b04">#</th>
                <th width="10%">Date</th>
                <th width="25%">Student Name</th>
                <th width="15%">Standard</th>
                <th width="15%">Fee Component</th>
                <th width="15%">Mode</th>
                <th width="15%" class="css-without-gst-report-pdf-08a0ed">Amount</th>
            </tr>
        </thead>
        <tbody>';

    if (empty($payments)) {
        $html .= '<tr><td colspan="7" class="css-without-gst-report-pdf-5713bc">No records found for the selected period.</td></tr>';
    }
    else {
        $i = 1;
        foreach ($payments as $row) {
            $html .= '
            <tr nobr="true">
                <td width="5%" class="css-without-gst-report-pdf-539b04">' . $i++ . '</td>
                <td width="10%">' . date('d-m-Y', strtotime($row['payment_date'])) . '</td>
                <td width="25%">' . htmlspecialchars($row['student_full_name'] ?? '') . '</td>
                <td width="15%">' . htmlspecialchars($row['standard_display'] ?? $row['course_name'] ?? '') . '</td>
                <td width="15%">' . formatFeeKey($row['fee_component']) . '</td>
                <td width="15%">' . strtoupper($row['payment_mode']) . '</td>
                <td width="15%" class="css-without-gst-report-pdf-d2e3c5">&#8377;' . formatIndianCurrency($row['amount']) . '</td>
            </tr>';
        }
    }

    $html .= '
            <tr class="css-without-gst-report-pdf-efb668">
                <td width="85%" colspan="6" class="css-without-gst-report-pdf-021d04">GRAND TOTAL</td>
                <td width="15%" class="css-without-gst-report-pdf-4091ae">&#8377;' . formatIndianCurrency($totalCollection) . '</td>
            </tr>
        </tbody>
    </table>';

    $pdf->writeHTML($html, true, false, false, false, '');

    if (ob_get_length())
        ob_clean();
    $filename = 'Deduction_Report_' . $from_date . '_to_' . $to_date . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $pdf->Output($filename, 'D');
    exit;

}
catch (Exception $e) {
    if (ob_get_length())
        ob_clean();
    die("PDF Error: " . $e->getMessage());
}
