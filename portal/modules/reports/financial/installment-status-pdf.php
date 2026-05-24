<?php
/**
 * Installment Status PDF Export
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
    $installments = $dbOps->customSelect(
        "SELECT fi.*, CONCAT(r.surname, ' ', r.student_name) as student_name, r.mob as mobile, c.course_name as current_class
         FROM tbl_fee_installments fi
         JOIN tbl_enrolled_students es ON fi.student_id = es.registration_id
         JOIN tbl_gm_std_registration r ON es.registration_id = r.id
         LEFT JOIN tbl_courses c ON r.course_id = c.id
         WHERE es.is_active = 1
         ORDER BY fi.due_date ASC",
        []
    );

    $config = $dbOps->customSelect("SELECT * FROM tbl_receipt_configuration WHERE is_active = 1 LIMIT 1", []);
    $config = $config[0] ?? null;

    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('GCA Management System');
    $pdf->SetTitle('Installment Status Report');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(true);
    $pdf->setFooterFont(array(PDF_FONT_NAME_DATA, '', 7));
    $pdf->SetMargins(5, 5, 5);
    $pdf->SetAutoPageBreak(TRUE, 10);
    $pdf->AddPage();

    $org_address = ($config['address'] ?? '') . ', ' . ($config['city'] ?? '');
    $headerHtml = '
    <table cellpadding="2" class="css-installment-status-pdf-8588e4">
        <tr>
            <td class="css-installment-status-pdf-539b04">
                <span class="css-installment-status-pdf-86c905">' . htmlspecialchars($config['organization_name'] ?? SYSTEM_NAME) . '</span><br>
                <span class="css-installment-status-pdf-1b8847">' . htmlspecialchars($org_address ?? '') . '</span><br>
                <span class="css-installment-status-pdf-269fc9">INSTALLMENT TRACKING REPORT</span><br>
                <span class="css-installment-status-pdf-1b8847">Snapshot as of: ' . date('d M Y h:i A') . '</span>
            </td>
        </tr>
    </table>';
    $pdf->writeHTML($headerHtml, true, false, false, false, '');
    $pdf->Ln(2);

    $html = '
    <table border="0.5" cellpadding="4" class="css-installment-status-pdf-c6ad08">
        <thead>
            <tr class="css-installment-status-pdf-5f2273">
                <th width="4%" class="css-installment-status-pdf-539b04">#</th>
                <th width="22%">Student Name</th>
                <th width="12%">Class</th>
                <th width="18%">Installment</th>
                <th width="12%" class="css-installment-status-pdf-08a0ed">Amount</th>
                <th width="12%">Due Date</th>
                <th width="10%">Status</th>
                <th width="10%">Mobile</th>
            </tr>
        </thead>
        <tbody>';

    if (empty($installments)) {
        $html .= '<tr><td colspan="8" class="css-installment-status-pdf-539b04">No installments found</td></tr>';
    } else {
        $i = 1;
        foreach ($installments as $inst) {
            $status = $inst['status'] ?? 'pending';
            $statusColor = $status == 'paid' ? '#28a745' : ($status == 'partial' ? '#ffc107' : '#dc3545');
            $html .= '
            <tr nobr="true">
                <td width="4%" class="css-installment-status-pdf-539b04">' . $i++ . '</td>
                <td width="22%"><b>' . htmlspecialchars($inst['student_name'] ?? '') . '</b></td>
                <td width="12%">' . htmlspecialchars($inst['current_class'] ?: '-' ?? '') . '</td>
                <td width="18%">' . htmlspecialchars($inst['installment_name'] ?: 'Installment' ?? '') . '</td>
                <td width="12%" class="css-installment-status-pdf-714e9d">' . formatIndianCurrency($inst['amount'] ?: 0) . '</td>
                <td width="12%">' . date('d-m-Y', strtotime($inst['due_date'])) . '</td>
                <td width="10%" class="css-installment-status-pdf-489de4">' . strtoupper($status) . '</td>
                <td width="10%">' . htmlspecialchars($inst['mobile'] ?: '-' ?? '') . '</td>
            </tr>';
        }
    }
    $html .= '</tbody></table>';

    $pdf->writeHTML($html, true, false, false, false, '');

    if (ob_get_length())
        ob_clean();
    $pdf->Output('Installment_Status_' . date('Y-m-d') . '.pdf', 'D');
    exit;

} catch (Exception $e) {
    if (ob_get_length())
        ob_clean();
    die("PDF Error: " . $e->getMessage());
}

