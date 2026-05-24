<?php
require_once __DIR__ . '/../../common/session_config.php';
require_once dirname(dirname(__DIR__)) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;
require_once dirname(dirname(__DIR__)) . '/common/helpers/format_helper.php';
header('Content-Type: application/json; charset=utf-8');

header('Content-Type: application/json');

if (!isset($_SESSION['is_student_login']) || $_SESSION['is_student_login'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$student_id = intval(htmlspecialchars($_POST['student_id'] ?? '0', ENT_QUOTES, 'UTF-8'));
$current_group_id = intval(htmlspecialchars($_POST['current_group_id'] ?? '0', ENT_QUOTES, 'UTF-8'));
$requested_group_id = intval(htmlspecialchars($_POST['requested_group_id'] ?? '0', ENT_QUOTES, 'UTF-8'));
$fees_already_paid = floatval(htmlspecialchars($_POST['fees_already_paid'] ?? '0', ENT_QUOTES, 'UTF-8'));

try {
    // Get student details including scholarship information
    $stmt = $conn->prepare("SELECT course_id, medium_id, 
                            scholarship_amount, scholarship_percentage,
                            additional_scholarship_amount
                            FROM tbl_gm_std_registration WHERE id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        throw new Exception("Student not found");
    }

    // Calculate total scholarship
    $total_scholarship = ($student['scholarship_amount'] ?? 0) + ($student['additional_scholarship_amount'] ?? 0);
    $scholarship_percentage = $student['scholarship_percentage'] ?? 0;

    // Get current fee config
    $stmt = $conn->prepare("SELECT fc.total_fees, fc.course_name, g.group_name
                            FROM tbl_fee_config fc
                            LEFT JOIN tbl_group g ON fc.group_id = g.id
                            WHERE fc.course_id = ? AND fc.medium_id = ? 
                            AND fc.group_id = ? AND fc.is_active = 1
                            LIMIT 1");
    $stmt->execute([
        $student['course_id'],
        $student['medium_id'],
        $current_group_id
    ]);
    $current_fee = $stmt->fetch(PDO::FETCH_ASSOC);
    $current_total = $current_fee['total_fees'] ?? 0;

    // Get new fee config
    $stmt->execute([
        $student['course_id'],
        $student['medium_id'],
        $requested_group_id
    ]);
    $new_fee = $stmt->fetch(PDO::FETCH_ASSOC);
    $new_total = $new_fee['total_fees'] ?? 0;

    // Get group name
    $stmt = $conn->prepare("SELECT group_name FROM tbl_group WHERE id = ?");
    $stmt->execute([$requested_group_id]);
    $new_group = $stmt->fetch(PDO::FETCH_ASSOC);

    // Apply scholarship to fees
    $current_net_fees = $current_total - $total_scholarship;
    $new_net_fees = $new_total - $total_scholarship;

    // Calculate difference (after scholarship)
    $difference = $new_net_fees - $current_net_fees;
    $new_pending = $new_net_fees - $fees_already_paid;

    // Generate HTML
    $html = '<table class="table table-sm table-bordered mb-0">';

    // Scholarship section (if applicable)
    if ($total_scholarship > 0) {
        $html .= '<tr class="table-info"><th colspan="2"><i class="fas fa-award"></i> Scholarship Applied</th></tr>';
        $html .= '<tr><td>Scholarship Amount</td><td class="text-end">₹' . formatIndianCurrency($total_scholarship) . '</td></tr>';
        if ($scholarship_percentage > 0) {
            $html .= '<tr><td>Scholarship Percentage</td><td class="text-end">' . formatIndianCurrency($scholarship_percentage) . '%</td></tr>';
        }
    }

    $html .= '<tr><th colspan="2" class="bg-light">Current Group: ' . htmlspecialchars($current_fee['group_name'] ?? 'N/A') . '</th></tr>';
    $html .= '<tr><td>Total Fees</td><td class="text-end">₹' . formatIndianCurrency($current_total) . '</td></tr>';
    if ($total_scholarship > 0) {
        $html .= '<tr><td>Scholarship Discount</td><td class="text-end text-success">-₹' . formatIndianCurrency($total_scholarship) . '</td></tr>';
        $html .= '<tr><td><strong>Net Fees</strong></td><td class="text-end"><strong>₹' . formatIndianCurrency($current_net_fees) . '</strong></td></tr>';
    }
    $html .= '<tr><td>Already Paid</td><td class="text-end">₹' . htmlspecialchars(formatIndianCurrency($fees_already_paid), ENT_QUOTES, 'UTF-8') . '</td></tr>';
    $html .= '<tr><td>Current Pending</td><td class="text-end">₹' . htmlspecialchars(formatIndianCurrency($current_net_fees - $fees_already_paid), ENT_QUOTES, 'UTF-8') . '</td></tr>';

    $html .= '<tr><th colspan="2" class="bg-light">New Group: ' . htmlspecialchars($new_group['group_name'] ?? 'N/A') . '</th></tr>';
    $html .= '<tr><td>Total Fees</td><td class="text-end">₹' . formatIndianCurrency($new_total) . '</td></tr>';
    if ($total_scholarship > 0) {
        $html .= '<tr><td>Scholarship Discount</td><td class="text-end text-success">-₹' . formatIndianCurrency($total_scholarship) . '</td></tr>';
        $html .= '<tr><td><strong>Net Fees</strong></td><td class="text-end"><strong>₹' . formatIndianCurrency($new_net_fees) . '</strong></td></tr>';
    }
    $html .= '<tr><td>Already Paid</td><td class="text-end">₹' . htmlspecialchars(formatIndianCurrency($fees_already_paid), ENT_QUOTES, 'UTF-8') . '</td></tr>';
    $html .= '<tr><td><strong>New Pending Amount</strong></td><td class="text-end"><strong>₹' . htmlspecialchars(formatIndianCurrency($new_pending), ENT_QUOTES, 'UTF-8') . '</strong></td></tr>';

    $html .= '<tr class="table-' . ($difference > 0 ? 'danger' : ($difference < 0 ? 'success' : 'info')) . '">';
    $html .= '<td><strong>Fee Difference</strong></td>';
    $html .= '<td class="text-end"><strong>';
    if ($difference > 0) {
        $html .= '+₹' . formatIndianCurrency($difference) . ' (Increase)';
    } elseif ($difference < 0) {
        $html .= '-₹' . formatIndianCurrency(abs($difference)) . ' (Decrease)';
    } else {
        $html .= '₹0.00 (No Change)';
    }
    $html .= '</strong></td></tr>';
    $html .= '</table>';

    if ($new_total == 0) {
        $html .= '<div class="alert alert-warning mt-2 mb-0">';
        $html .= '<i class="fas fa-exclamation-triangle"></i> Fee configuration not found for the selected group. ';
        $html .= 'Fees will be determined by the administration after approval.';
        $html .= '</div>';
    }

    echo json_encode([
        'success' => true,
        'html' => $html,
        'current_total' => $current_total,
        'new_total' => $new_total,
        'difference' => $difference,
        'new_pending' => $new_pending,
        'scholarship_amount' => $total_scholarship,
        'current_net_fees' => $current_net_fees,
        'new_net_fees' => $new_net_fees
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
} catch (Exception $e) {
    logError("Fee Difference Preview Error: " . $e->getMessage(), __FILE__, __LINE__, $e);
    echo json_encode([
        'success' => false,
        'message' => 'Error calculating fee difference. Please try again.'
    ]);
}
