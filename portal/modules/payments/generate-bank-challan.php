<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/format_helper.php';

// Check if user is Accountant
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'accountant') {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// Check if student ID is provided
if (!isset($_POST['id'])) {
    set_flash_message('error', "Student ID is required");
    header('Location: token-fee-collection.php');
    exit;
}

$student_id = $_POST['id'];

// Get student details
try {
    $op = new Operation();

    $student = $op->readWithJoin(
        'tbl_gm_std_registration s',
        [
            's.*',
            'CASE WHEN p.id IS NOT NULL THEN 1 ELSE 0 END as token_fees_paid',
            'b.board_name as board',
            'c.course_name',
            'm.medium_name',
            'g.group_name',
            'fc.token_fee',
            'fc.school_fee',
            'fc.trust_facilities_fee',
            'fc.tuition_fee_part1'
        ],
        [
            ['type' => 'LEFT', 'table' => 'tbl_boards b', 'on' => 's.board_id = b.id'],
            ['type' => 'LEFT', 'table' => 'tbl_courses c', 'on' => 's.course_id = c.id'],
            ['type' => 'LEFT', 'table' => 'tbl_medium m', 'on' => 's.medium_id = m.id'],
            ['type' => 'LEFT', 'table' => 'tbl_group g', 'on' => 's.group_id = g.id'],
            ['type' => 'LEFT', 'table' => 'tbl_fee_config fc', 'on' => 's.course_id = fc.course_id AND s.school_id = fc.school_id AND s.medium_id = fc.medium_id AND s.group_id = fc.group_id AND fc.is_active = 1'],
            ['type' => 'LEFT', 'table' => 'tbl_payments p', 'on' => "s.id = p.student_id AND p.payment_type = 'token_fee' AND p.status = 'paid'"]
        ],
        ['s.id' => $student_id, 's.admission_confirmed' => 1]
    );

    if (!$student) {
        set_flash_message('error', "Student not found or admission not confirmed");
        header('Location: token-fee-collection.php');
        exit;
    }

    if ($student['token_fees_paid']) {
        $_SESSION['info_msg'] = "Token fee already paid for this student";
        header('Location: token-fee-collection.php');
        exit;
    }
} catch (Exception $e) {
    logDatabaseError($e, "Fetch Student for Bank Challan");
    set_flash_message('error', "Error fetching student details");
    header('Location: token-fee-collection.php');
    exit;
}

// Use token_fee from config as the total
$total_token_fee = floatval($student['token_fee'] ?: 11800);

// For breakdown, we prioritize Tuition Part 1 + GST if it matches token_fee
$tuition_part1 = floatval($student['tuition_fee_part1']);
$gst_part1 = $tuition_part1 * 0.18;
$tuition_part1_with_gst = $tuition_part1 + $gst_part1;

// If Tuition P1 + GST matches Token Fee, we show that breakdown
// Otherwise we just show School Fee/Trust Fee etc. if they are part of it
// For now, most systems use Token Fee = Tuition P1 + GST
$show_detailed_breakdown = (abs($tuition_part1_with_gst - $total_token_fee) < 1);

// Generate challan number
$challan_number = 'CH-' . date('Ymd') . '-' . str_pad($student_id, 5, '0', STR_PAD_LEFT);
$challan_date = date('d-M-Y');
$valid_till = date('d-M-Y', strtotime('+15 days'));

$page_title = "Bank Challan";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
</head>

<body>

    <!-- Print Button -->
    <div class="no-print print-btn">
        <button onclick="window.print()" class="btn btn-primary btn-lg">
            <i class="fas fa-print"></i> Print Challan
        </button>
        <a href="token-fee-collection.php" class="btn btn-secondary btn-lg ms-2">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>

    <div class="challan-container">
        <!-- Header -->
        <div class="challan-header">
            <h2><i class="fas fa-university"></i> GYANMANJARI EDUCATION TRUST</h2>
            <p>Fee Payment Bank Challan</p>
        </div>

        <!-- Body -->
        <div class="challan-body">
            <!-- Challan Info -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="info-row">
                        <span class="info-label">Challan Number:</span>
                        <span class="info-value challan-number"><?php echo $challan_number; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Issue Date:</span>
                        <span class="info-value"><?php echo $challan_date; ?></span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-row">
                        <span class="info-label">Valid Till:</span>
                        <span class="info-value"><strong><?php echo $valid_till; ?></strong></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Academic Year:</span>
                        <span class="info-value">2024-2025</span>
                    </div>
                </div>
            </div>

            <!-- Student Details Section -->
            <div class="challan-section">
                <h5 class="section-title"><i class="fas fa-user-graduate"></i> Student Details</h5>
                <div class="row">
                    <div class="col-md-6">
                        <div class="info-row">
                            <span class="info-label">Admission Letter No:</span>
                            <span
                                class="info-value"><strong><?php echo htmlspecialchars($student['admission_letter_number'] ?? ''); ?></strong></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Student Name:</span>
                            <span
                                class="info-value"><strong><?php echo htmlspecialchars($student['surname'] . ' ' . $student['student_name'] . ' ' . $student['fathers_name'] ?? ''); ?></strong></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Father's Name:</span>
                            <span class="info-value"><?php echo htmlspecialchars($student['fathers_name'] ?? ''); ?></span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-row">
                            <span class="info-label">Course:</span>
                            <span class="info-value"><?php echo htmlspecialchars($student['course_name'] ?? ''); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Medium / Group:</span>
                            <span
                                class="info-value"><?php echo htmlspecialchars($student['medium_name'] . ' / ' . $student['group_name'] ?? ''); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Mobile Number:</span>
                            <span class="info-value"><?php echo htmlspecialchars($student['mob'] ?? ''); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Fee Details Section -->
            <div class="challan-section">
                <h5 class="section-title"><i class="fas fa-rupee-sign"></i> Fee Details (Token Fee)</h5>
                <table class="fee-table">
                    <thead>
                        <tr>
                            <th>Sr.</th>
                            <th>Fee Component</th>
                            <th>Amount (₹)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($show_detailed_breakdown): ?>
                            <tr>
                                <td>1</td>
                                <td>Tuition Fee (Part-1)</td>
                                <td>₹<?php echo formatIndianCurrency($tuition_part1); ?></td>
                            </tr>
                            <tr>
                                <td>2</td>
                                <td>GST @ 18%</td>
                                <td>₹<?php echo formatIndianCurrency($gst_part1); ?></td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <td>1</td>
                                <td>Registration / Token Fee</td>
                                <td>₹<?php echo formatIndianCurrency($total_token_fee); ?></td>
                            </tr>
                        <?php endif; ?>
                        <tr class="total-row">
                            <td colspan="2"><strong>Total Payable Amount</strong></td>
                            <td><strong>₹<?php echo formatIndianCurrency($total_token_fee); ?></strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Bank Details Section -->
            <div class="challan-section">
                <h5 class="section-title"><i class="fas fa-landmark"></i> Bank Account Details</h5>
                <div class="bank-details">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-row">
                                <span class="info-label">Bank Name:</span>
                                <span class="info-value"><strong>State Bank of India</strong></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Branch:</span>
                                <span class="info-value">Bhavnagar Main Branch</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Account Name:</span>
                                <span class="info-value">Gyanmanjari Education Trust</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-row">
                                <span class="info-label">Account Number:</span>
                                <span class="info-value"><strong>30123456789012</strong></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">IFSC Code:</span>
                                <span class="info-value"><strong>SBIN0001234</strong></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Account Type:</span>
                                <span class="info-value">Current Account</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Important Notes -->
            <div class="important-note">
                <h5><i class="fas fa-exclamation-triangle"></i> Important Instructions</h5>
                <ul>
                    <li>Please mention the <strong>Challan Number</strong> and <strong>Admission Letter Number</strong>
                        in the deposit slip remarks.</li>
                    <li>This challan is valid for <strong>15 days</strong> from the date of issue.</li>
                    <li>After depositing the fee, please submit a copy of the bank receipt to the accounts department.
                    </li>
                    <li>For any queries, contact: <strong>+91 9876543210</strong></li>
                </ul>
            </div>
        </div>

        <!-- Footer with Signatures -->
        <div class="challan-footer">
            <div class="row">
                <div class="col-md-4">
                    <div class="signature-box">
                        <span class="signature-label">Depositor's Signature</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="signature-box">
                        <span class="signature-label">Bank Cashier's Signature</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="signature-box">
                        <span class="signature-label">Bank Seal</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>