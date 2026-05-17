<?php
/**
 * Class-wise Collection PDF Export
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
    $dbOps = new DatabaseOperations();

    $sql = "SELECT 
                c.course_name as current_class,
                COUNT(DISTINCT es.registration_id) as total_students,
                COUNT(DISTINCT p.student_id) as students_paid,
                COUNT(p.id) as transaction_count,
                IFNULL(SUM(p.amount), 0) as total_collected,
                IFNULL(SUM(CASE WHEN p.payment_mode = 'cash' THEN p.amount ELSE 0 END), 0) as cash_amount,
                IFNULL(SUM(CASE WHEN p.payment_mode = 'cheque' THEN p.amount ELSE 0 END), 0) as cheque_amount,
                IFNULL(SUM(CASE WHEN p.payment_mode IN ('online', 'upi', 'card') THEN p.amount ELSE 0 END), 0) as online_amount
            FROM tbl_enrolled_students es
            JOIN tbl_gm_std_registration r ON es.registration_id = r.id
            JOIN tbl_courses c ON r.course_id = c.id
            LEFT JOIN tbl_payments p ON p.student_id = es.registration_id 
                AND p.status = 'paid' 
                AND p.payment_date BETWEEN ? AND ?
            WHERE es.is_active = 1
            GROUP BY c.course_name
            ORDER BY c.course_name";

    $classData = $dbOps->customSelect($sql, [$from_date, $to_date]);

    $grandTotal = ['students' => 0, 'students_paid' => 0, 'transactions' => 0, 'collected' => 0, 'cash' => 0, 'cheque' => 0, 'online' => 0];
    foreach ($classData as $row) {
        $grandTotal['students'] += $row['total_students'];
        $grandTotal['students_paid'] += $row['students_paid'];
        $grandTotal['transactions'] += $row['transaction_count'];
        $grandTotal['collected'] += $row['total_collected'];
        $grandTotal['cash'] += $row['cash_amount'];
        $grandTotal['cheque'] += $row['cheque_amount'];
        $grandTotal['online'] += $row['online_amount'];
    }

    $config = $dbOps->customSelect("SELECT * FROM tbl_receipt_configuration WHERE is_active = 1 LIMIT 1", []);
    $config = $config[0] ?? null;

    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('GCA Management System');
    $pdf->SetTitle('Class-wise Collection');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(true);
    $pdf->setFooterFont(array(PDF_FONT_NAME_DATA, '', 7));
    $pdf->SetMargins(5, 5, 5);
    $pdf->SetAutoPageBreak(TRUE, 10);
    $pdf->AddPage();

    $org_address = ($config['address'] ?? '') . ', ' . ($config['city'] ?? '');
    $headerHtml = '
    <table cellpadding="2" style="width:100%;">
        <tr>
            <td style="text-align:center;">
                <span style="font-size:16pt; font-weight:bold;">' . htmlspecialchars($config['organization_name'] ?? SYSTEM_NAME) . '</span><br>
                <span style="font-size:10pt;">' . htmlspecialchars($org_address ?? '') . '</span><br>
                <span style="font-size:12pt; font-weight:bold; background-color:#f0f0f0;">CLASS-WISE COLLECTION REPORT</span><br>
                <span style="font-size:10pt;">Period: ' . date('d-m-Y', strtotime($from_date)) . ' to ' . date('d-m-Y', strtotime($to_date)) . '</span>
            </td>
        </tr>
    </table>';
    $pdf->writeHTML($headerHtml, true, false, false, false, '');
    $pdf->Ln(2);

    $html = '
    <table border="0.5" cellpadding="4" style="width:100%; font-size:8pt;">
        <thead>
            <tr style="background-color:#333; color:#fff; font-weight:bold;">
                <th width="15%">Class</th>
                <th width="10%" style="text-align:center;">Total Students</th>
                <th width="10%" style="text-align:center;">Students Paid</th>
                <th width="10%" style="text-align:center;">Transactions</th>
                <th width="12%" style="text-align:right;">Cash</th>
                <th width="12%" style="text-align:right;">Cheque</th>
                <th width="12%" style="text-align:right;">Online</th>
                <th width="12%" style="text-align:right;">Total</th>
                <th width="7%" style="text-align:center;">%</th>
            </tr>
        </thead>
        <tbody>';

    if (empty($classData)) {
        $html .= '<tr><td colspan="9" style="text-align:center;">No data found</td></tr>';
    } else {
        foreach ($classData as $row) {
            $percentage = $grandTotal['collected'] > 0 ? ($row['total_collected'] / $grandTotal['collected']) * 100 : 0;
            $html .= '
            <tr nobr="true">
                <td width="15%"><b>' . htmlspecialchars($row['current_class'] ?? '') . '</b></td>
                <td width="10%" style="text-align:center;">' . $row['total_students'] . '</td>
                <td width="10%" style="text-align:center;">' . $row['students_paid'] . '</td>
                <td width="10%" style="text-align:center;">' . $row['transaction_count'] . '</td>
                <td width="12%" style="text-align:right;">' . formatIndianCurrency($row['cash_amount']) . '</td>
                <td width="12%" style="text-align:right;">' . formatIndianCurrency($row['cheque_amount']) . '</td>
                <td width="12%" style="text-align:right;">' . formatIndianCurrency($row['online_amount']) . '</td>
                <td width="12%" style="text-align:right; font-weight:bold;">' . formatIndianCurrency($row['total_collected']) . '</td>
                <td width="7%" style="text-align:center;">' . round($percentage, 1) . '%</td>
            </tr>';
        }
    }

    $html .= '
            <tr style="background-color:#f0f0f0; font-weight:bold;">
                <td width="15%">GRAND TOTAL</td>
                <td width="10%" style="text-align:center;">' . $grandTotal['students'] . '</td>
                <td width="10%" style="text-align:center;">' . $grandTotal['students_paid'] . '</td>
                <td width="10%" style="text-align:center;">' . $grandTotal['transactions'] . '</td>
                <td width="12%" style="text-align:right;">' . formatIndianCurrency($grandTotal['cash']) . '</td>
                <td width="12%" style="text-align:right;">' . formatIndianCurrency($grandTotal['cheque']) . '</td>
                <td width="12%" style="text-align:right;">' . formatIndianCurrency($grandTotal['online']) . '</td>
                <td width="12%" style="text-align:right;">' . formatIndianCurrency($grandTotal['collected']) . '</td>
                <td width="7%" style="text-align:center;">100%</td>
            </tr>
        </tbody>
    </table>';

    $pdf->writeHTML($html, true, false, false, false, '');

    if (ob_get_length())
        ob_clean();
    $filename = 'Class_wise_Collection_' . $from_date . '_to_' . $to_date . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $pdf->Output($filename, 'D');
    exit;

} catch (Exception $e) {
    if (ob_get_length())
        ob_clean();
    die("PDF Error: " . $e->getMessage());
}
