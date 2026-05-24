<?php
/**
 * Fee Defaulters PDF Export
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
    $threshold_days = $_GET['threshold'] ?? 30;
    $filter_class = $_GET['class'] ?? '';
    $filter_group = $_GET['group'] ?? '';
    $search = $_GET['search'] ?? '';

    $sql = "SELECT 
                es.enrollment_id,
                CONCAT(r.surname, ' ', r.student_name) as student_name,
                r.mob as mobile,
                r.fathers_name as father_name,
                CONCAT(c.course_name, IF(m.medium_name IS NOT NULL AND m.medium_name != '', CONCAT(' - ', m.medium_name), '')) as current_class,
                g.group_name,
                COALESCE(sfa.pending_amount, 0) as pending_amount,
                sfa.due_date,
                DATEDIFF(CURDATE(), IFNULL(sfa.due_date, es.enrollment_date)) as days_overdue
            FROM tbl_enrolled_students es
            JOIN tbl_gm_std_registration r ON es.registration_id = r.id
            LEFT JOIN tbl_group g ON r.group_id = g.id
            LEFT JOIN tbl_courses c ON r.course_id = c.id
            LEFT JOIN tbl_medium m ON r.medium_id = m.id
            LEFT JOIN tbl_student_fee_allocation sfa ON es.registration_id = sfa.student_id
            WHERE es.is_active = 1
            AND COALESCE(sfa.pending_amount, 0) > 0
            AND DATEDIFF(CURDATE(), IFNULL(sfa.due_date, es.enrollment_date)) >= ?";

    $params = [$threshold_days];
    if (!empty($filter_class)) {
        $sql .= " AND c.course_name = ?";
        $params[] = $filter_class;
    }
    if (!empty($filter_group)) {
        $sql .= " AND r.group_id = ?";
        $params[] = $filter_group;
    }

    if (!empty($search)) {
        $sql .= " AND (CONCAT(r.surname, ' ', r.student_name, ' ', IFNULL(r.fathers_name, '')) LIKE ? OR r.mob LIKE ? OR r.id LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    $sql .= " ORDER BY days_overdue ASC, pending_amount ASC";

    $defaulters = $dbOps->customSelect($sql, $params);
    $totalOutstanding = array_sum(array_column($defaulters, 'pending_amount'));

    $config = $dbOps->customSelect("SELECT * FROM tbl_receipt_configuration WHERE is_active = 1 LIMIT 1", []);
    $config = $config[0] ?? null;

    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('GCA Management System');
    $pdf->SetTitle('Fee Defaulters List');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(true);
    $pdf->setFooterFont(array(PDF_FONT_NAME_DATA, '', 7));
    $pdf->SetMargins(5, 5, 5);
    $pdf->SetAutoPageBreak(TRUE, 10);
    $pdf->AddPage();

    $org_address = ($config['address'] ?? '') . ', ' . ($config['city'] ?? '');
    $headerHtml = '
    <table cellpadding="2" class="css-fee-defaulters-pdf-8588e4">
        <tr>
            <td class="css-fee-defaulters-pdf-539b04">
                <span class="css-fee-defaulters-pdf-86c905">' . htmlspecialchars($config['organization_name'] ?? SYSTEM_NAME) . '</span><br>
                <span class="css-fee-defaulters-pdf-1b8847">' . htmlspecialchars($org_address ?? '') . '</span><br>
                <span class="css-fee-defaulters-pdf-96a72c">FEE DEFAULTERS LIST (' . $threshold_days . '+ Days Overdue)</span><br>
                <span class="css-fee-defaulters-pdf-1b8847">Generated on: ' . date('d M Y h:i A') . '</span>
            </td>
        </tr>
    </table>';
    $pdf->writeHTML($headerHtml, true, false, false, false, '');
    $pdf->Ln(2);

    $summaryHtml = '
    <table cellpadding="4" class="css-fee-defaulters-pdf-0e766f">
        <tr class="css-fee-defaulters-pdf-6eb74d">
            <td width="33%">Total Defaulters: <b>' . count($defaulters) . '</b></td>
            <td width="33%">Threshold: <b>' . $threshold_days . '+ Days</b></td>
            <td width="34%" class="css-fee-defaulters-pdf-08a0ed">Total Outstanding: <b>' . formatIndianCurrency($totalOutstanding) . '</b></td>
        </tr>
    </table>';
    $pdf->writeHTML($summaryHtml, true, false, false, false, '');
    $pdf->Ln(2);

    $html = '
    <table border="0.5" cellpadding="4" class="css-fee-defaulters-pdf-6eb086">
        <thead>
            <tr class="css-fee-defaulters-pdf-5f2273">
                <th width="4%" class="css-fee-defaulters-pdf-539b04">#</th>
                <th width="20%">Student Name</th>
                <th width="15%">Class / Group</th>
                <th width="18%">Parent / Contact</th>
                <th width="12%" class="css-fee-defaulters-pdf-539b04">Due Date</th>
                <th width="10%" class="css-fee-defaulters-pdf-539b04">Days Overdue</th>
                <th width="21%" class="css-fee-defaulters-pdf-08a0ed">Outstanding Amount</th>
            </tr>
        </thead>
        <tbody>';

    if (empty($defaulters)) {
        $html .= '<tr><td colspan="7" class="css-fee-defaulters-pdf-539b04">No defaulters found</td></tr>';
    } else {
        $i = 1;
        foreach ($defaulters as $row) {
            $color = ($row['days_overdue'] > 90) ? '#d9534f' : (($row['days_overdue'] > 60) ? '#f0ad4e' : '#000');
            $html .= '
            <tr nobr="true">
                <td width="4%" class="css-fee-defaulters-pdf-539b04">' . $i++ . '</td>
                <td width="20%"><b>' . htmlspecialchars($row['student_name'] ?? '') . '</b></td>
                <td width="15%">' . htmlspecialchars($row['current_class'] ?: '-' ?? '') . ' (' . htmlspecialchars($row['group_name'] ?: '-' ?? '') . ')</td>
                <td width="18%">' . htmlspecialchars($row['father_name'] ?: '-' ?? '') . ' / ' . htmlspecialchars($row['mobile'] ?: '-' ?? '') . '</td>
                <td width="12%" class="css-fee-defaulters-pdf-539b04">' . ($row['due_date'] ? date('d-m-Y', strtotime($row['due_date'])) : '-') . '</td>
                <td width="10%" class="css-fee-defaulters-pdf-e0e81e">' . $row['days_overdue'] . '</td>
                <td width="21%" class="css-fee-defaulters-pdf-8983b7">' . formatIndianCurrency($row['pending_amount']) . '</td>
            </tr>';
        }
    }
    $html .= '</tbody></table>';

    $pdf->writeHTML($html, true, false, false, false, '');

    if (ob_get_length())
        ob_clean();
    $filename = 'Fee_Defaulters_' . date('Y-m-d') . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $pdf->Output($filename, 'D');
    exit;

} catch (Exception $e) {
    if (ob_get_length())
        ob_clean();
    die("PDF Error: " . $e->getMessage());
}


