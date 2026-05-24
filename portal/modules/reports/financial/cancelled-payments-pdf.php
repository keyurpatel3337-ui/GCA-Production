<?php
/**
 * Cancelled Payments PDF Export
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
    $dbOps = new DatabaseOperations();
    $from_date = $_GET['from_date'] ?? date('Y-m-01');
    $to_date = $_GET['to_date'] ?? date('Y-m-d');
    $search_query = $_GET['search'] ?? '';

    $sql = "SELECT 
                p.*, 
                CONCAT(r.surname, ' ', r.student_name, ' ', IFNULL(r.fathers_name, '')) as student_full_name,
                c.course_name,
                u.name as issued_by_name
            FROM tbl_payments p
            LEFT JOIN tbl_gm_std_registration r ON p.student_id = r.id
            LEFT JOIN tbl_enrolled_students es ON es.registration_id = r.id AND es.is_active = 1
            LEFT JOIN tbl_courses c ON r.course_id = c.id
            LEFT JOIN tbl_users u ON p.created_by = u.id
            WHERE p.status IN ('cancelled', 'failed', 'refunded')
            AND p.payment_date BETWEEN ? AND ?";

    $params = [$from_date, $to_date];
    if (!empty($search_query)) {
        $sql .= " AND (CONCAT(r.surname, ' ', r.student_name, ' ', IFNULL(r.fathers_name, '')) LIKE ? OR r.mob LIKE ? OR r.id LIKE ? OR p.receipt_no LIKE ? OR p.transaction_id LIKE ?)";
        $like_query = "%$search_query%";
        $params = array_merge($params, [$like_query, $like_query, $like_query, $like_query, $like_query]);
    }
    $sql .= " ORDER BY p.created_at ASC";

    $cancelled = $dbOps->customSelect($sql, $params);
    $totalCancelled = array_sum(array_column($cancelled, 'amount'));

    $config = $dbOps->customSelect("SELECT * FROM tbl_receipt_configuration WHERE is_active = 1 LIMIT 1", []);
    $config = $config[0] ?? null;

    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('GCA Management System');
    $pdf->SetTitle('Cancelled Payments Report');
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
                <span style="font-size:12pt; font-weight:bold; background-color:#333; color:#fff;">CANCELLED / FAILED / REFUNDED PAYMENTS</span><br>
                <span style="font-size:10pt;">Period: ' . date('d-m-Y', strtotime($from_date)) . ' to ' . date('d-m-Y', strtotime($to_date)) . '</span>
            </td>
        </tr>
    </table>';
    $pdf->writeHTML($headerHtml, true, false, false, false, '');
    $pdf->Ln(2);

    $summaryHtml = '
    <table cellpadding="4" style="width:100%; margin-bottom:10px; border:0.5px solid #ddd;">
        <tr style="background-color:#f8f9fa;">
            <td width="33%">Total Records: <b>' . count($cancelled) . '</b></td>
            <td width="33%">Search: <b>' . ($search_query ?: 'None') . '</b></td>
            <td width="34%" style="text-align:right;">Total Amount Voided: <b>' . formatIndianCurrency($totalCancelled) . '</b></td>
        </tr>
    </table>';
    $pdf->writeHTML($summaryHtml, true, false, false, false, '');
    $pdf->Ln(2);

    $html = '
    <table border="0.5" cellpadding="4" style="width:100%; font-size:8pt;">
        <thead>
            <tr style="background-color:#eee; font-weight:bold;">
                <th width="3%" style="text-align:center;">#</th>
                <th width="10%">Date</th>
                <th width="18%">Student Name</th>
                <th width="10%">Receipt No</th>
                <th width="10%" style="text-align:right;">Amount</th>
                <th width="10%">Mode</th>
                <th width="16%">Remarks</th>
                <th width="12%">Issued By</th>
                <th width="11%" style="text-align:center;">Status</th>
            </tr>
        </thead>
        <tbody>';

    if (empty($cancelled)) {
        $html .= '<tr><td colspan="9" style="text-align:center;">No records found</td></tr>';
    } else {
        $i = 1;
        foreach ($cancelled as $row) {
            $html .= '
            <tr nobr="true">
                <td width="3%" style="text-align:center;">' . $i++ . '</td>
                <td width="10%">' . date('d-m-Y', strtotime($row['payment_date'])) . '</td>
                <td width="18%"><b>' . htmlspecialchars($row['student_full_name'] ?? '') . '</b></td>
                <td width="10%">' . htmlspecialchars($row['receipt_no'] ?: '-' ?? '') . '</td>
                <td width="10%" style="text-align:right; font-weight:bold;">' . formatIndianCurrency($row['amount']) . '</td>
                <td width="10%">' . strtoupper($row['payment_mode']) . '</td>
                <td width="16%"><small>' . htmlspecialchars($row['remarks'] ?: '-' ?? '') . '</small></td>
                <td width="12%">' . htmlspecialchars($row['issued_by_name'] ?: '-' ?? '') . '</td>
                <td width="11%" style="text-align:center;"><b>' . strtoupper($row['status']) . '</b></td>
            </tr>';
        }
    }
    $html .= '</tbody></table>';

    $pdf->writeHTML($html, true, false, false, false, '');

    if (ob_get_length())
        ob_clean();
    $filename = 'Cancelled_Payments_' . $from_date . '_to_' . $to_date . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $pdf->Output($filename, 'D');
    exit;

} catch (Exception $e) {
    if (ob_get_length())
        ob_clean();
    die("PDF Error: " . $e->getMessage());
}


