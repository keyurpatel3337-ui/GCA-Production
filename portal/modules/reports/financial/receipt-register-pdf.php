<?php
/**
 * Receipt Register PDF Export
 * Generates a PDF version of the receipt register report
 */

ob_start();
require_once __DIR__ . '/../../../session_config.php';
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once __DIR__ . '/../../../../common/helpers/format_helper.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

// Check access
if (!hasRole(ROLE_ACCOUNTANT) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

try {
    // Get filters
    $from_date = $_GET['from_date'] ?? date('Y-m-01');
    $to_date = $_GET['to_date'] ?? date('Y-m-d');
    $payment_mode = $_GET['payment_mode'] ?? '';
    $payment_type_filter = $_GET['payment_type'] ?? '';
    $receipt_config_filter = $_GET['receipt_config'] ?? '';
    $school_filter = $_GET['school_id'] ?? '';
    $medium_filter = $_GET['medium_id'] ?? '';
    $group_filter = $_GET['group_id'] ?? '';
    $course_filter = $_GET['course_id'] ?? '';
    $term_filter = $_GET['term_id'] ?? '';
    $search = $_GET['search'] ?? '';

    $dbOps = new DatabaseOperations();

    // Get all receipts/payments (No limit for PDF)
    $sql = "SELECT 
                p.id,
                p.receipt_no,
                p.amount,
                p.payment_date,
                p.payment_mode,
                p.payment_type,
                p.transaction_id,
                p.cheque_no,
                p.cheque_date,
                p.bank_name,
                p.remarks,
                p.created_at,
                p.fee_component,
                rc.receipt_title,
                '' as receipt_prefix,
                CONCAT(r.surname, ' ', r.student_name, ' ', IFNULL(r.fathers_name, '')) as student_full_name,
                c.course_name as current_class,
                g.group_name,
                t.term_name,
                u.name as collected_by
            FROM tbl_payments p
            JOIN tbl_gm_std_registration r ON p.student_id = r.id
            LEFT JOIN tbl_enrolled_students es ON es.registration_id = r.id AND es.is_active = 1
            LEFT JOIN tbl_courses c ON r.course_id = c.id
            LEFT JOIN tbl_group g ON r.group_id = g.id
            LEFT JOIN tbl_term t ON p.term_id = t.id
            LEFT JOIN tbl_receipt_configuration rc ON p.receipt_config_id = rc.id
            LEFT JOIN tbl_users u ON p.created_by = u.id
            WHERE p.status = 'paid'
            AND p.payment_date BETWEEN ? AND ?";

    $params = [$from_date, $to_date];

    if (!empty($payment_mode)) {
        $sql .= " AND p.payment_mode = ?";
        $params[] = $payment_mode;
    }

    if (!empty($payment_type_filter)) {
        $sql .= " AND p.payment_type = ?";
        $params[] = $payment_type_filter;
    }

    if (!empty($receipt_config_filter)) {
        $sql .= " AND p.receipt_config_id = ?";
        $params[] = $receipt_config_filter;
    }

    if (!empty($school_filter)) {
        $sql .= " AND (r.school_id = ? OR r.school_id = ?)";
        $params[] = $school_filter;
        $params[] = $school_filter;
    }

    if (!empty($medium_filter)) {
        $sql .= " AND (r.medium_id = ? OR r.medium_id = ?)";
        $params[] = $medium_filter;
        $params[] = $medium_filter;
    }

    if (!empty($group_filter)) {
        $sql .= " AND (r.group_id = ? OR r.group_id = ?)";
        $params[] = $group_filter;
        $params[] = $group_filter;
    }

    if (!empty($course_filter)) {
        $sql .= " AND (r.course_id = ? OR r.course_id = ?)";
        $params[] = $course_filter;
        $params[] = $course_filter;
    }

    if (!empty($term_filter)) {
        $sql .= " AND p.term_id = ?";
        $params[] = $term_filter;
    }

    if (!empty($search)) {
        $sql .= " AND (
            CONCAT(r.surname, ' ', r.student_name, ' ', IFNULL(r.fathers_name, '')) LIKE ? OR 
            r.mob LIKE ? OR 
            r.id LIKE ? OR 
            p.receipt_no LIKE ?
        )";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    $sql .= " ORDER BY p.payment_date ASC, p.created_at asc";
    $receipts = $dbOps->customSelect($sql, $params);

    // Get default receipt configuration for fallback
    $defConfig = $dbOps->customSelect("SELECT * FROM tbl_receipt_configuration WHERE is_active = 1 LIMIT 1", []);
    $defConfig = $defConfig[0] ?? null;

    if (!$defConfig) {
        throw new Exception("Default receipt configuration not found!");
    }

    // Determine Dynamic Organization Name and Header Details
    $org_name = $defConfig['organization_name'];
    $org_address = ($defConfig['address'] ?? '') . ', ' . ($defConfig['city'] ?? '');

    // Priority 1: Receipt Config Filter
    if (!empty($receipt_config_filter)) {
        $selConfig = $dbOps->customSelect("SELECT * FROM tbl_receipt_configuration WHERE id = ?", [$receipt_config_filter]);
        if ($selConfig) {
            $org_name = $selConfig[0]['organization_name'];
            $org_address = ($selConfig[0]['address'] ?? '') . ', ' . ($selConfig[0]['city'] ?? '');
        }
    }
    // Priority 2: School Filter
    elseif (!empty($school_filter)) {
        $selSchool = $dbOps->customSelect("SELECT school_name FROM tbl_schools WHERE id = ?", [$school_filter]);
        if ($selSchool) {
            $org_name = $selSchool[0]['school_name'];
            // Try to find matching address from configs
            $schoolConfig = $dbOps->customSelect("SELECT * FROM tbl_receipt_configuration WHERE organization_name LIKE ?", ['%' . $selSchool[0]['school_name'] . '%']);
            if ($schoolConfig) {
                $org_address = ($schoolConfig[0]['address'] ?? '') . ', ' . ($schoolConfig[0]['city'] ?? '');
            }
        }
    }
    // Priority 3: Payment Type Filter
    elseif (!empty($payment_type_filter)) {
        if (in_array($payment_type_filter, ['Hostel Fee', 'Trust Facilities Fee', 'Transport Fee'])) {
            $org_name = "Mahatma Seva Trust";
            $mstConfig = $dbOps->customSelect("SELECT * FROM tbl_receipt_configuration WHERE receipt_title LIKE '%MST%' LIMIT 1", []);
            if ($mstConfig)
                $org_address = ($mstConfig[0]['address'] ?? '') . ', ' . ($mstConfig[0]['city'] ?? '');
        } elseif ($payment_type_filter == 'School Fee') {
            $org_name = "GM and SGM Receipts";
            $org_address = "";
        } elseif (in_array($payment_type_filter, ['Tuition Fee Part 1', 'Tuition Fee Part 2', 'Token Fee'])) {
            $org_name = "GYANMANJARI CAREER ACADEMY";
            $gcaConfig = $dbOps->customSelect("SELECT * FROM tbl_receipt_configuration WHERE receipt_title LIKE '%GCA%' LIMIT 1", []);
            if ($gcaConfig)
                $org_address = ($gcaConfig[0]['address'] ?? '') . ', ' . ($gcaConfig[0]['city'] ?? '');
        }
    }

    // Create new PDF document - A4 Landscape for the report
    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);

    // Set document information
    $pdf->SetCreator('GCA Management System');
    $pdf->SetTitle('Receipt Register');

    // Set header
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(true);
    $pdf->setFooterFont(array(PDF_FONT_NAME_DATA, '', 7));

    // Set margins - very tight for landscape report
    $pdf->SetMargins(5, 5, 5);
    $pdf->SetHeaderMargin(0);
    $pdf->SetFooterMargin(7);
    $pdf->SetAutoPageBreak(TRUE, 10);

    // Set font
    $pdf->SetFont('helvetica', '', 9);

    // Add a page
    $pdf->AddPage();

    // Header Content
    $html_header = '
    <table cellpadding="2" style="width:100%;">
        <tr>
            <td style="text-align:center;">
                <span style="font-size:16pt; font-weight:bold;">' . htmlspecialchars($org_name ?? '') . '</span><br>
                ' . (!empty($org_address) ? '<span style="font-size:10pt;">' . htmlspecialchars($org_address ?? '') . '</span><br>' : '') . '
                <span style="font-size:12pt; font-weight:bold; background-color:#f0f0f0;">RECEIPT REGISTER</span><br>
                <span style="font-size:9pt;">Report Period: ' . date('d-M-Y', strtotime($from_date)) . ' to ' . date('d-M-Y', strtotime($to_date)) . '</span>
            </td>
        </tr>
    </table>';

    $pdf->writeHTML($html_header, true, false, false, false, '');
    $pdf->Ln(2);

    // Table Header
    $html = '
    <table border="0.5" cellpadding="4" style="width:100%; font-size:8pt;">
        <thead>
            <tr style="background-color:#333; color:#fff; font-weight:bold;">
                <th width="5%" style="text-align:center;">S.No</th>
                <th width="8%" style="text-align:center;">Receipt No</th>
                <th width="8%" style="text-align:center;">Date</th>
                <th width="15%">Student Name</th>
                <th width="10%">Class/Group</th>
                <th width="8%">Term</th>
                <th width="8%">Payment Type</th>
                <th width="7%" style="text-align:center;">Mode</th>
                <th width="10%">Bank/Trans</th>
                <th width="7%" style="text-align:right;">Amount</th>
                <th width="8%">Collected By</th>
                <th width="7%">Remark</th>
            </tr>
        </thead>
        <tbody>';

    $sno = 1;
    $totalAmount = 0;

    if (empty($receipts)) {
        $html .= '<tr><td colspan="11" style="text-align:center;">No records found for selected period</td></tr>';
    } else {
        foreach ($receipts as $receipt) {
            $totalAmount += $receipt['amount'];

            $bankDetails = '';
            if ($receipt['payment_mode'] == 'cheque') {
                $bankDetails = '<b>Bank:</b> ' . htmlspecialchars($receipt['bank_name'] ?: '-' ?? '') . '<br>';
                $bankDetails .= '<b>Chq No:</b> ' . htmlspecialchars($receipt['cheque_no'] ?: '-' ?? '') . '<br>';
                $bankDetails .= '<b>Chq Date:</b> ' . ($receipt['cheque_date'] ? date('d-m-Y', strtotime($receipt['cheque_date'])) : '-');
            } elseif ($receipt['payment_mode'] == 'online') {
                $bankDetails = '<b>Trans ID:</b> ' . htmlspecialchars($receipt['transaction_id'] ?: '-' ?? '');
            } else {
                $bankDetails = '-';
            }

            $html .= '
            <tr nobr="true">
                <td width="5%" style="text-align:center;">' . $sno++ . '</td>
                <td width="8%" style="text-align:center;">' . htmlspecialchars(($receipt['receipt_prefix'] ?? '') . $receipt['receipt_no']) . '</td>
                <td width="8%" style="text-align:center;">' . date('d-m-Y', strtotime($receipt['payment_date'])) . '</td>
                <td width="15%"><b>' . htmlspecialchars($receipt['student_full_name'] ?? '') . '</b></td>
                <td width="10%">' . htmlspecialchars($receipt['current_class'] ?: '-' ?? '') . ' / ' . htmlspecialchars($receipt['group_name'] ?: '-' ?? '') . '</td>
                <td width="8%">' . htmlspecialchars($receipt['term_name'] ?: '-' ?? '') . '</td>
                <td width="8%">' . htmlspecialchars($receipt['payment_type'] ?? '') . '</td>
                <td width="7%" style="text-align:center;">' . strtoupper($receipt['payment_mode']) . '</td>
                <td width="10%">' . $bankDetails . '</td>
                <td width="7%" style="text-align:right; font-weight:bold;">' . formatIndianCurrency($receipt['amount']) . '</td>
                <td width="8%">' . htmlspecialchars($receipt['collected_by'] ?: 'System' ?? '') . '</td>
                <td width="7%"><small>' . htmlspecialchars($receipt['remarks'] ?: '-' ?? '') . '</small></td>
            </tr>';
        }
    }

    $html .= '
            <tr style="background-color:#f0f0f0; font-weight:bold;">
                <td colspan="9" style="text-align:right;">GRAND TOTAL</td>
                <td style="text-align:right;">' . formatIndianCurrency($totalAmount) . '</td>
                <td colspan="2"></td>
            </tr>
        </tbody>
    </table>';

    $pdf->writeHTML($html, true, false, false, false, '');

    // Reset output buffer
    if (ob_get_length())
        ob_clean();

    // Output PDF
    $filename = 'Receipt_Register_' . $from_date . '_to_' . $to_date . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $pdf->Output($filename, 'D');
    exit;

} catch (Exception $e) {
    if (ob_get_length())
        ob_clean();
    die("PDF Error: " . $e->getMessage());
}

