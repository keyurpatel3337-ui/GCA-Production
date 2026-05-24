<?php

require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;
require_once __DIR__ . '/../../../common/helpers/format_helper.php';
require_once __DIR__ . '/../../../common/helpers/fee_helper.php';

// Initialize database operations if not already done
if (!isset($dbOps)) {
    $dbOps = new DatabaseOperations();
}

// Tighten access: Only parents are allowed to manage fees
$is_student = isset($_SESSION['is_student_login']) && $_SESSION['is_student_login'] === true;
$is_parent = isset($_SESSION['is_parent_login']) && $_SESSION['is_parent_login'] === true;

if ($is_student) {
    $_SESSION['error'] = 'Access Denied: Fees and Wallet are managed exclusively by Parents.';
    header('Location: ../dashboard/student_dashboard.php');
    exit;
}

if (!$is_parent) {
    header('Location: ../../parent-login.php');
    exit;
}

$page_title = "My Fee Details";
$page_breadcrumb = "Fee Details";
$student_id = $is_parent ? ($_SESSION['active_student_id'] ?? null) : ($_SESSION['student_id'] ?? null);

if (!$student_id) {
    set_flash_message('error', "Invalid session. Please login again.");
    header('Location: ' . ($is_parent ? '../parent-portal/dashboard.php' : 'student-login.php'));
    exit;
}

$tokens = $dbOps->customSelect("SELECT 
                       r.standard, r.course_id,
                       CASE WHEN p.id IS NOT NULL OR r.token_fees_paid = 1 THEN 1 ELSE 0 END as token_fees_paid,
                       p.payment_date as token_payment_date,
                       p.transaction_id as token_transaction_id,
                       p.amount as token_amount,
                       r.hostel_required, r.gender,fc.school_fee, fc.trust_facilities_fee, fc.tuition_fee_part1, fc.tuition_fee_part2,
                       r.scholarship_amount, r.additional_scholarship_amount,
                       r.scholarship_percentage, e.current_term_id
                       FROM tbl_gm_std_registration r
                       LEFT JOIN (
                           SELECT id, student_id, payment_date, transaction_id, amount, status, payment_type, fee_component, (CASE WHEN receipt_no = '0' THEN 1 ELSE 0 END) as is_without_gst FROM tbl_payments
                       ) p ON r.id = p.student_id 
                            AND (p.payment_type = 'token_fee' OR p.fee_component = 'tuition_fee_part1') 
                            AND p.status = 'paid'
                       LEFT JOIN tbl_enrolled_students e ON r.id = e.registration_id
                       LEFT JOIN tbl_fee_config fc ON r.course_id = fc.course_id 
                            AND r.medium_id = fc.medium_id 
                            AND r.group_id = fc.group_id 
                            AND r.school_id = fc.school_id
                            AND fc.is_active = 1 
                            AND fc.term IN (CASE WHEN e.current_term_id = 2 THEN '2nd Term' ELSE '1st Term' END, 
                                          CASE WHEN e.current_term_id = 2 THEN 'Semester 2' ELSE 'Semester 1' END)
                       WHERE r.id = ?", [$student_id]);
$token_info = !empty($tokens) ? $tokens[0] : null;
$current_term_id = intval($token_info['current_term_id'] ?? 1);

$is_restricted_payment = false;
if ($token_info) {
    $std_val = isset($token_info['standard']) ? intval($token_info['standard']) : 0;
    $course_val = isset($token_info['course_id']) ? intval($token_info['course_id']) : 0;
    if ($std_val == 11 && ($course_val == 1 || $course_val == 2)) {
        $is_restricted_payment = true;
    }
}

// Check if student record existed
if (!$token_info) {
    set_flash_message('error', "Student registration details not found. Please contact administration.");
    header('Location: ../dashboard/student_dashboard.php');
    exit;
}

// --- REFACTORED: Use strictly term-bound summary for student/parent view ---
$summary = calculateStudentFeeSummary($conn, $student_id, true, $current_term_id);
$detailed_allocations = $summary['allocations'];

// Map to existing variables for UI compatibility
$school_fee = $detailed_allocations['school_fee']['gross_amount'] ?? 0;
$trust_facilities_fee = $detailed_allocations['trust_facilities_fee']['gross_amount'] ?? 0;
$tuition_part1_with_gst = $detailed_allocations['tuition_fee_part1']['gross_amount'] ?? 0;

// For display, we show amount after scholarships/discounts (waived)
$tuition_part2_after_discount = ($detailed_allocations['tuition_fee_part2']['gross_amount'] ?? 0) - ($detailed_allocations['tuition_fee_part2']['waived_amount'] ?? 0);
$hostel_fee = 0;
$hostel_required = false;

// Re-add variables needed for installment logic below
$enrollment_datas = $dbOps->customSelect("SELECT enrollment_id FROM tbl_gm_std_registration WHERE id = ?", [$student_id]);
$enrollment_id = !empty($enrollment_datas) ? $enrollment_datas[0]['enrollment_id'] : null;
$id_list = [$student_id];
if ($enrollment_id)
    $id_list[] = $enrollment_id;
$placeholders = implode(',', array_fill(0, count($id_list), '?'));
$query_term_id = $current_term_id;

// Payment Status Logic (Replaces manual execute() calls)
// Helper: If paid, pending_amount will be 0.
function mapToPaymentInfo($alloc)
{
    if (!$alloc)
        return null;
    if ($alloc['pending_amount'] == 0 && ($alloc['gross_amount'] > 0 || $alloc['paid_amount'] > 0 || $alloc['is_without_gst'] == 1)) {
        return [
            'receipt_no' => $alloc['receipt_no'] ?: '-',
            'is_without_gst' => $alloc['is_without_gst'] ?? 0,
            'amount' => $alloc['paid_amount']
        ];
    }
    return null;
}

$school_fee_payment = mapToPaymentInfo($detailed_allocations['school_fee'] ?? null);
$trust_fee_payment = mapToPaymentInfo($detailed_allocations['trust_facilities_fee'] ?? null);
$tuition_part1_payment = mapToPaymentInfo($detailed_allocations['tuition_fee_part1'] ?? null);
$tuition_part2_payment = mapToPaymentInfo($detailed_allocations['tuition_fee_part2'] ?? null);
$hostel_fee_payment = null;
// ------------------------------------------------------------------

// Check for approved installment requests (Installments still need manual handling as helper doesn't detail them yet)
$has_tuition_part2_installments = false;
$tuition_part2_installments = [];
if (!$tuition_part2_payment) {
    // Check if there's an approved installment request
    $params = array_merge($id_list, ['tuition_fee_part2', 'approved', $query_term_id]);
    $stmt = $conn->prepare("SELECT * FROM tbl_installment_requests 
                           WHERE student_id IN ($placeholders) AND fee_component = ? 
                           AND status = ? AND term_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute($params);
    $installment_request = $stmt->fetch();

    if ($installment_request) {
        // Get allocation ID
        $stmt = $conn->prepare("SELECT id FROM tbl_student_fee_allocation 
                               WHERE student_id IN ($placeholders) AND term_id = ? ORDER BY academic_year DESC LIMIT 1");
        $stmt->execute(array_merge($id_list, [$query_term_id]));
        $allocation = $stmt->fetch();

        if ($allocation) {
            // Get installments
            $stmt = $conn->prepare("SELECT fi.*, 
                                   p.receipt_no, p.payment_date, p.status as payment_status_record, p.is_without_gst
                                   FROM tbl_fee_installments fi
                                   LEFT JOIN (
                                       SELECT id, installment_id, receipt_no, payment_date, status, fee_component, (CASE WHEN receipt_no = '0' THEN 1 ELSE 0 END) as is_without_gst FROM tbl_payments
                                   ) p ON fi.id = p.installment_id 
                                       AND p.fee_component = 'tuition_fee_part2' AND p.status = 'paid'
                                   WHERE fi.allocation_id = ? 
                                   ORDER BY fi.installment_number");
            $stmt->execute([$allocation['id']]);
            $tuition_part2_installments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $has_tuition_part2_installments = !empty($tuition_part2_installments);
        }
    }
}

// Logic moved to top section for installments detection above.
$allocations = [];
// $allocations fetch continues using existing $id_list and $placeholders.

$stmt = $conn->prepare("SELECT sfa.*, fc.course_name, fc.academic_year as config_year, 
    fc.total_fees, fc.number_of_installments
    FROM tbl_student_fee_allocation sfa
    JOIN tbl_fee_config fc ON sfa.fee_config_id = fc.id
    WHERE sfa.student_id IN ($placeholders) AND sfa.term_id = ?
    ORDER BY sfa.academic_year ASC");
$stmt->execute(array_merge($id_list, [$query_term_id]));
$allocations = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../../include/header.php';
?>
<script type="text/javascript">
    window.history.pushState(null, null, window.location.href);
    window.onpopstate = function() {
        window.history.pushState(null, null, window.location.href);
    };
</script>
<?php
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>


</head>

<body>




    <div class="container-fluid">

        <?php if (!$token_info || !$token_info['token_fees_paid']): ?>
            <!-- If token fee not paid, show only token fee payment option -->
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> Please pay the token fee (₹11,800/-) to confirm your
                admission and view detailed fee structure.
            </div>
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h3 class="card-title"><i class="fas fa-money-bill-wave"></i> Token Fee Payment</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Fee Component</th>
                                    <th>Organization</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>Tuition Fee Part 1</strong> (incl. 18% GST)</td>
                                    <td>Gyanmanjari Career Academy</td>
                                    <td><strong>₹<?php echo formatIndianCurrency($tuition_part1_with_gst); ?></strong>
                                    </td>
                                    <td><span class="badge bg-warning">Pending</span></td>
                                    <td>
                                        <?php if (!$is_restricted_payment): ?>
                                            <a href="token-fee-payment.php" class="btn btn-warning btn-sm">
                                                <i class="fas fa-credit-card"></i> Pay Now
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Token fee paid - show complete fee breakdown based on current term -->
            <?php if ($current_term_id === 1): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> Token fee paid successfully! Below are your Semester 1 pending fees.
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> You have been promoted to <strong>Semester 2</strong>. Below are your
                    Semester 2 fees.
                </div>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h3 class="card-title"><i class="fas fa-list-alt"></i> Fee Structure & Payment Details</h3>
                </div>
                <div class="card-body">
                    <?php
                    // Calculate all pending fees
                    $pending_fees = [];
                    $total_pending_amount = 0;

                    // School fee
                    if (!$school_fee_payment && $school_fee > 0 && !$has_tuition_part2_installments) {
                        $pending_fees[] = ['component' => 'school_fee', 'label' => 'School Fee', 'amount' => $school_fee];
                        $total_pending_amount += $school_fee;
                    }

                    // Trust facilities fee
                    if (!$trust_fee_payment && $trust_facilities_fee > 0 && !$has_tuition_part2_installments) {
                        $pending_fees[] = ['component' => 'trust_facilities_fee', 'label' => 'Trust Facilities Fee', 'amount' => $trust_facilities_fee];
                        $total_pending_amount += $trust_facilities_fee;
                    }

                    // Tuition fee part 2
                    if (!$has_tuition_part2_installments && !$tuition_part2_payment && $tuition_part2_after_discount > 0) {
                        $pending_fees[] = ['component' => 'tuition_fee_part2', 'label' => 'Tuition Fee Part 2', 'amount' => $tuition_part2_after_discount];
                        $total_pending_amount += $tuition_part2_after_discount;
                    }

                    // Hostel security deposit (online collection)
                    if ($hostel_required && !$hostel_fee_payment && $hostel_fee > 0) {
                        $pending_fees[] = ['component' => 'hostel_security', 'label' => 'Hostel Security Deposit', 'amount' => $hostel_fee];
                        $total_pending_amount += $hostel_fee;
                    }

                    // Transport fee
                    $transport_row = $detailed_allocations['transport_fee'] ?? null;
                    if ($transport_row && $transport_row['pending_amount'] > 0) {
                        $transport_amount = $transport_row['pending_amount'];

                        // Use transport config from helper to check for Monthly timeline
                        $ts = $summary['transport_config'] ?? null;
                        $is_monthly = ($ts && $ts['timeline'] === 'Monthly');

                        // If it's Monthly, we ALWAYS show it (as 1 month installment)
                        // If it's Term-wise, we ONLY show it if there are NO academic installments
                        if ($is_monthly || (!$has_tuition_part2_installments)) {
                            if ($is_monthly) {
                                $monthly_total = $ts['monthly_rate'];
                                // Only include 1 month in the 'Pay All' total if more than 1 month pending
                                if ($transport_amount > $monthly_total) {
                                    $transport_amount = $monthly_total;
                                }
                            }

                            $pending_fees[] = ['component' => 'transport_fee', 'label' => $transport_row['label'], 'amount' => $transport_amount];
                            $total_pending_amount += $transport_amount;
                        }
                    }

                    // Check for unpaid installments
                    if ($has_tuition_part2_installments) {
                        foreach ($tuition_part2_installments as $inst) {
                            $is_paid = !empty($inst['receipt_no']);
                            if (!$is_paid && $inst['due_amount'] > 0) {
                                $pending_fees[] = [
                                    'component' => 'tuition_fee_part2',
                                    'label' => 'Tuition Fee Part 2 - Installment #' . $inst['installment_number'],
                                    'amount' => floatval($inst['due_amount']),
                                    'installment_id' => $inst['id']
                                ];
                                $total_pending_amount += floatval($inst['due_amount']);
                            }
                        }
                    }

                    // Show "Pay All" button if there are pending fees
                    if (count($pending_fees) > 1 && $total_pending_amount > 0):
                        ?>
                        <div class="alert alert-warning mb-4 d-flex align-items-center justify-content-between">
                            <div>
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <strong>You have <?php echo count($pending_fees); ?> pending fee components</strong>
                                <span class="ms-3">Total Pending:
                                    <strong>₹<?php echo formatIndianCurrency($total_pending_amount); ?></strong></span>
                            </div>
                             <?php if (!$is_restricted_payment): ?>
                                 <button type="button" onclick="payAllPendingFees()" class="btn btn-success btn-lg">
                                     <i class="fas fa-credit-card"></i> Pay All Pending Fees
                                 </button>
                             <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-primary">
                                <tr>
                                    <th width="25%">Fee Component</th>
                                    <th width="20%">Organization</th>
                                    <th width="15%">Amount</th>
                                    <th width="15%">Status</th>
                                    <th width="10%">Receipt No</th>
                                    <th width="15%">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- School Fee -->
                                <?php if ($school_fee > 0): ?>
                                    <tr class="<?php echo $school_fee_payment ? 'installment-paid' : 'installment-pending'; ?>">
                                        <td><strong>School Fee</strong></td>
                                        <td>
                                            <?php
                                            // Determine school name based on school_id
                                            $school_datas = $dbOps->customSelect("SELECT school_name FROM tbl_schools WHERE id = (SELECT school_id FROM tbl_gm_std_registration WHERE id = ?)", [$student_id]);
                                            $school_data = !empty($school_datas) ? $school_datas[0] : null;
                                            echo htmlspecialchars($school_data['school_name'] ?? 'Gyanmanjari');
                                            ?>
                                        </td>
                                        <td><strong>₹<?php echo formatIndianCurrency($school_fee); ?></strong></td>
                                        <td>
                                            <?php if ($school_fee_payment): ?>
                                                <span class="badge bg-success"><i class="fas fa-check-circle"></i> Paid</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning"><i class="fas fa-exclamation-circle"></i>
                                                    Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $school_fee_payment ? htmlspecialchars($school_fee_payment['receipt_no'] ?? '') : '-'; ?>
                                        </td>
                                        <td>
                                            <?php if ($school_fee_payment): ?>
                                                <button type="button"
                                                    onclick="downloadReceipt('<?php echo htmlspecialchars($school_fee_payment['receipt_no'] ?? '', ENT_QUOTES); ?>', 'school_fee')"
                                                    class="btn btn-info btn-sm">
                                                    <i class="fas fa-download"></i> Receipt
                                                </button>
                                            <?php else: ?>
                                                <?php if (!$is_restricted_payment): ?>
                                                    <button type="button" onclick="payFee('school_fee')" class="btn btn-warning btn-sm">
                                                        <i class="fas fa-credit-card"></i> Pay Now
                                                    </button>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>

                                <!-- Trust Facilities Fee -->
                                <?php if ($trust_facilities_fee > 0): ?>
                                    <tr class="<?php echo $trust_fee_payment ? 'installment-paid' : 'installment-pending'; ?>">
                                        <td><strong>Trust Facilities Fee</strong></td>
                                        <td>Mahatma Seva Trust</td>
                                        <td><strong>₹<?php echo formatIndianCurrency($trust_facilities_fee); ?></strong>
                                        </td>
                                        <td>
                                            <?php if ($trust_fee_payment): ?>
                                                <span class="badge bg-success"><i class="fas fa-check-circle"></i> Paid</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning"><i class="fas fa-exclamation-circle"></i>
                                                    Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $trust_fee_payment ? htmlspecialchars($trust_fee_payment['receipt_no'] ?? '') : '-'; ?>
                                        </td>
                                        <td>
                                            <?php if ($trust_fee_payment): ?>
                                                <button type="button"
                                                    onclick="downloadReceipt('<?php echo htmlspecialchars($trust_fee_payment['receipt_no'] ?? '', ENT_QUOTES); ?>', 'trust_facilities_fee')"
                                                    class="btn btn-info btn-sm">
                                                    <i class="fas fa-download"></i> Receipt
                                                </button>
                                            <?php else: ?>
                                                <?php if (!$is_restricted_payment): ?>
                                                    <button type="button" onclick="payFee('trust_facilities_fee')"
                                                        class="btn btn-warning btn-sm">
                                                        <i class="fas fa-credit-card"></i> Pay Now
                                                    </button>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>

                                <!-- Tuition Fee Part 1 -->
                                <?php if ($current_term_id === 1 && $tuition_part1_with_gst > 0): ?>
                                    <tr class="installment-paid">
                                        <td><strong>Tuition Fee Part 1</strong> (incl. 18% GST)</td>
                                        <td>Gyanmanjari Career Academy</td>
                                        <td><strong>₹<?php echo formatIndianCurrency($tuition_part1_with_gst); ?></strong>
                                        </td>
                                        <td><span class="badge bg-success"><i class="fas fa-check-circle"></i> Paid</span>
                                        </td>
                                        <td>
                                            <?php
                                            if ($tuition_part1_payment) {
                                                echo htmlspecialchars($tuition_part1_payment['receipt_no'] ?? '');
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php if ($tuition_part1_payment): ?>
                                                <button type="button"
                                                    onclick="downloadReceipt('<?php echo htmlspecialchars($tuition_part1_payment['receipt_no'] ?? '', ENT_QUOTES); ?>', 'tuition_fee_part1')"
                                                    class="btn btn-info btn-sm">
                                                    <i class="fas fa-download"></i> Receipt
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted">Receipt Processing</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>

                                <!-- Hostel Fee (if applicable) -->
                                <?php if ($hostel_required && $hostel_fee > 0): ?>
                                    <tr class="<?php echo $hostel_fee_payment ? 'installment-paid' : 'installment-pending'; ?>">
                                        <td><strong><?php echo formatFeeKey('hostel_security'); ?></strong></td>
                                        <td>MST</td>
                                        <td><strong>₹<?php echo formatIndianCurrency($hostel_fee); ?></strong></td>
                                        <td>
                                            <?php if ($hostel_fee_payment): ?>
                                                <span class="badge bg-success"><i class="fas fa-check-circle"></i> Paid</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning"><i class="fas fa-exclamation-circle"></i>
                                                    Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $hostel_fee_payment ? htmlspecialchars($hostel_fee_payment['receipt_no'] ?? '') : '-'; ?>
                                        </td>
                                        <td>
                                            <?php if ($hostel_fee_payment): ?>
                                                <button type="button"
                                                    onclick="downloadReceipt('<?php echo htmlspecialchars($hostel_fee_payment['receipt_no'] ?? '', ENT_QUOTES); ?>', 'hostel_fee')"
                                                    class="btn btn-info btn-sm">
                                                    <i class="fas fa-download"></i> Receipt
                                                </button>
                                            <?php else: ?>
                                                <?php if (!$is_restricted_payment): ?>
                                                    <button type="button" onclick="payFee('hostel_security')" class="btn btn-warning btn-sm">
                                                        <i class="fas fa-credit-card"></i> Pay Now
                                                    </button>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>

                                <!-- Transport Fee (if applicable) -->
                                <?php
                                $transport_row = $detailed_allocations['transport_fee'] ?? null;
                                if ($transport_row && $transport_row['gross_amount'] > 0):
                                    $is_paid = ($transport_row['pending_amount'] <= 0);
                                    ?>
                                    <tr class="<?php echo $is_paid ? 'installment-paid' : 'installment-pending'; ?>">
                                        <td><strong><?php echo htmlspecialchars($transport_row['label'] ?? ''); ?></strong></td>
                                        <td>Mahatma Seva Trust</td>
                                        <td><strong>₹<?php echo formatIndianCurrency($transport_row['gross_amount']); ?></strong>
                                        </td>
                                        <td>
                                            <?php if ($is_paid): ?>
                                                <span class="badge bg-success"><i class="fas fa-check-circle"></i> Paid</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning"><i class="fas fa-exclamation-circle"></i>
                                                    Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $is_paid ? htmlspecialchars($transport_row['receipt_no'] ?? '') : '-'; ?>
                                        </td>
                                        <td>
                                            <?php if ($is_paid): ?>
                                                <button type="button"
                                                    onclick="downloadReceipt('<?php echo htmlspecialchars($transport_row['receipt_no'] ?? '', ENT_QUOTES); ?>', 'transport_fee')"
                                                    class="btn btn-info btn-sm">
                                                    <i class="fas fa-download"></i> Receipt
                                                </button>
                                            <?php else: ?>
                                                <?php if (!$is_restricted_payment): ?>
                                                    <button type="button" onclick="payFee('transport_fee')"
                                                        class="btn btn-warning btn-sm">
                                                        <i class="fas fa-credit-card"></i> Pay Now
                                                    </button>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                <?php if ($has_tuition_part2_installments): ?>
                                    <!-- Installment Mode: Show breakdown -->
                                    <?php
                                    $total_paid_installments = 0;
                                    $total_pending_installments = 0;
                                    foreach ($tuition_part2_installments as $inst) {
                                        if ($inst['payment_status'] === 'paid') {
                                            $total_paid_installments += floatval($inst['due_amount']);
                                        } else {
                                            $total_pending_installments += floatval($inst['due_amount']);
                                        }
                                    }
                                    $all_paid = ($total_pending_installments == 0);
                                    ?>
                                    <tr class="<?php echo $all_paid ? 'installment-paid' : 'installment-partial'; ?>">
                                        <td colspan="6" class="p-0">
                                            <table class="table table-sm mb-0">
                                                <tr class="table-info">
                                                    <td colspan="6">
                                                        <strong><i class="fas fa-list-ol"></i> Tuition Fee Part 2</strong>
                                                        (incl. 18% GST) -
                                                        <span class="badge bg-primary">Approved for
                                                            <?php echo count($tuition_part2_installments); ?>
                                                            Installments</span>
                                                        <?php if ($total_scholarship > 0): ?>
                                                            <br><small class="text-success">Scholarship:
                                                                -₹<?php echo formatIndianCurrency($total_scholarship); ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php foreach ($tuition_part2_installments as $idx => $inst):
                                                    $is_paid = ($inst['payment_status'] === 'paid');
                                                    ?>
                                                    <tr class="<?php echo $is_paid ? 'table-success' : ''; ?>">
                                                        <td width="25%">
                                                            <i class="fas fa-calendar-check"></i> Installment
                                                            #<?php echo $inst['installment_number']; ?>
                                                            <?php if (!empty($inst['due_date'])): ?>
                                                                <br><small class="text-muted">Due:
                                                                    <?php echo date('d M Y', strtotime($inst['due_date'])); ?></small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td width="20%">Gyanmanjari Career Academy</td>
                                                        <td width="15%">
                                                            <strong>₹<?php echo formatIndianCurrency($inst['due_amount']); ?></strong>
                                                        </td>
                                                        <td width="15%">
                                                            <?php if ($is_paid): ?>
                                                                <span class="badge bg-success"><i class="fas fa-check-circle"></i>
                                                                    Paid</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-warning"><i class="fas fa-clock"></i>
                                                                    Pending</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td width="10%">
                                                            <?php echo $is_paid ? htmlspecialchars($inst['receipt_no'] ?? '') : '-'; ?>
                                                        </td>
                                                        <td width="15%">
                                                            <?php if ($is_paid): ?>
                                                                <button type="button"
                                                                    onclick="downloadReceipt('<?php echo htmlspecialchars($inst['receipt_no'] ?? '', ENT_QUOTES); ?>', 'tuition_fee_part2')"
                                                                    class="btn btn-info btn-sm">
                                                                    <i class="fas fa-download"></i> Receipt
                                                                </button>
                                                            <?php else: ?>
                                                <?php if (!$is_restricted_payment): ?>
                                                    <button type="button"
                                                        onclick="payFee('tuition_fee_part2', <?php echo $inst['id']; ?>)"
                                                        class="btn btn-warning btn-sm">
                                                        <i class="fas fa-credit-card"></i> Pay
                                                        ₹<?php echo formatIndianCurrency($inst['due_amount']); ?>
                                                    </button>
                                                <?php endif; ?>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                <tr class="table-dark">
                                                    <td colspan="2"><strong>Subtotal (Tuition Part 2)</strong></td>
                                                    <td><strong>₹<?php echo formatIndianCurrency($tuition_part2_after_discount); ?></strong>
                                                    </td>
                                                    <td colspan="3">
                                                        <span class="text-success">Paid:
                                                            ₹<?php echo formatIndianCurrency($total_paid_installments); ?></span>
                                                        |
                                                        <span class="text-warning">Pending:
                                                            ₹<?php echo formatIndianCurrency($total_pending_installments); ?></span>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <!-- Regular Mode: Single payment -->
                                    <tr
                                        class="<?php echo $tuition_part2_payment ? 'installment-paid' : 'installment-pending'; ?>">
                                        <td><strong>Tuition Fee Part 2</strong> (incl. 18% GST)
                                            <?php if ($total_scholarship > 0): ?>
                                                <br><small class="text-success">Scholarship:
                                                    -₹<?php echo formatIndianCurrency($total_scholarship); ?></small>
                                            <?php endif; ?>
                                            <?php if ($post_admission_discount > 0): ?>
                                                <br><small class="text-info">Post-Admission Discount:
                                                    -₹<?php echo formatIndianCurrency($post_admission_discount); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>Gyanmanjari Career Academy</td>
                                        <td>
                                            <strong>₹<?php echo formatIndianCurrency($tuition_part2_after_discount); ?></strong>
                                            <?php if ($total_scholarship > 0 || $post_admission_discount > 0): ?>
                                                <br><small
                                                    class="text-muted"><del>₹<?php echo formatIndianCurrency($tuition_part2_with_gst); ?></del></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($tuition_part2_payment): ?>
                                                <span class="badge bg-success"><i class="fas fa-check-circle"></i> Paid</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning"><i class="fas fa-exclamation-circle"></i>
                                                    Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $tuition_part2_payment ? htmlspecialchars($tuition_part2_payment['receipt_no'] ?? '') : '-'; ?>
                                        </td>
                                        <td>
                                            <?php if ($tuition_part2_payment): ?>
                                                <button type="button"
                                                    onclick="downloadReceipt('<?php echo htmlspecialchars($tuition_part2_payment['receipt_no'] ?? '', ENT_QUOTES); ?>', 'tuition_fee_part2')"
                                                    class="btn btn-info btn-sm">
                                                    <i class="fas fa-download"></i> Receipt
                                                </button>
                                            <?php else: ?>
                                                <?php if (!$is_restricted_payment): ?>
                                                    <button type="button" onclick="payFee('tuition_fee_part2')"
                                                        class="btn btn-warning btn-sm">
                                                        <i class="fas fa-credit-card"></i> Pay Now
                                                    </button>
                                                    <br><small>
                                                        <a href="#"
                                                            onclick="openInstallmentRequest('tuition_fee_part2', <?php echo $tuition_part2_after_discount; ?>); return false;"
                                                            class="text-primary">
                                                            <i class="fas fa-calendar-alt"></i> Request Installment
                                                        </a>
                                                    </small>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-dark">
                                    <td colspan="2"><strong>Total</strong></td>
                                    <td><strong>₹<?php
                                    $total_fees = $school_fee + $trust_facilities_fee + $tuition_part1_with_gst + $tuition_part2_after_discount;
                                    // Add hostel fee if applicable
                                    if ($hostel_required && $hostel_fee > 0) {
                                        $total_fees += $hostel_fee;
                                    }
                                    // Add transport fee if applicable
                                    $transport_row = $detailed_allocations['transport_fee'] ?? null;
                                    if ($transport_row && $transport_row['gross_amount'] > 0) {
                                        $total_fees += $transport_row['gross_amount'];
                                    }
                                    echo formatIndianCurrency($total_fees);
                                    ?></strong></td>
                                    <td colspan="3">
                                        <?php
                                        $total_paid = $tuition_part1_with_gst;
                                        if ($school_fee_payment)
                                            $total_paid += $school_fee;
                                        if ($trust_fee_payment)
                                            $total_paid += $trust_facilities_fee;

                                        // Handle tuition part 2 - check installments or single payment
                                        if ($has_tuition_part2_installments) {
                                            $total_paid += $total_paid_installments;
                                        } else if ($tuition_part2_payment) {
                                            $total_paid += $tuition_part2_after_discount;
                                        }

                                        // Add hostel fee if paid
                                        if ($hostel_fee_payment) {
                                            $total_paid += floatval($hostel_fee_payment['amount']);
                                        }

                                        // Add transport fee if paid
                                        $transport_row = $detailed_allocations['transport_fee'] ?? null;
                                        if ($transport_row && $transport_row['pending_amount'] <= 0 && $transport_row['gross_amount'] > 0) {
                                            $total_paid += $transport_row['paid_amount'];
                                        }

                                        $total_pending = $total_fees - $total_paid;
                                        ?>
                                        <span class="text-success">Paid:
                                            ₹<?php echo formatIndianCurrency($total_paid); ?></span> |
                                        <span class="text-danger">Pending:
                                            ₹<?php echo formatIndianCurrency($total_pending); ?></span>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

        <?php endif; ?>

    </div>

    </div>

    <!-- Installment Request Modal -->
    <div class="modal fade" id="installmentRequestModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-calendar-alt"></i> Request Installment Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="installmentRequestForm">
                    <div class="modal-body">
                        <input type="hidden" id="fee_component" name="fee_component">
                        <input type="hidden" id="amount" name="amount">

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> You can request to pay your fees in installments. The
                            accountant will review your request.
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Fee Component</label>
                            <input type="text" class="form-control" id="fee_component_display" readonly>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Total Amount</label>
                            <input type="text" class="form-control" id="amount_display" readonly>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Number of Installments <span class="text-danger">*</span></label>
                            <select name="requested_installments" class="form-select" required>
                                <option value="">Select...</option>
                                <option value="2">2 Installments</option>
                                <option value="3">3 Installments</option>
                                <option value="4">4 Installments</option>
                                <option value="5">5 Installments</option>
                                <option value="6">6 Installments</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Reason for Installment Request <span
                                    class="text-danger">*</span></label>
                            <textarea name="reason" class="form-control" rows="4" required
                                placeholder="Please explain why you need installment payment..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Submit Request</button>
                    </div>
                </form>
            </div>
        </div>

        <?php include '../../include/footer.php'; ?>

        <script>
            function openInstallmentRequest(feeComponent, amount) {
                const componentNames = {
                    'school_fee': 'School Fee',
                    'trust_facilities_fee': 'Trust Facilities Fee',
                    'tuition_fee_part2': 'Tuition Fee Part 2'
                };

                $('#fee_component').val(feeComponent);
                $('#amount').val(amount);
                $('#fee_component_display').val(componentNames[feeComponent] || feeComponent);
                $('#amount_display').val('₹' + amount.toLocaleString('en-IN', {
                    maximumFractionDigits: 0
                }));
                $('#installmentRequestModal').modal('show');
            }

            $(document).ready(function () {
                // Move modals to body to prevent z-index issues
                $('#installmentRequestModal').appendTo("body");

                $('#installmentRequestForm').on('submit', function (e) {
                    e.preventDefault();

                    const submitBtn = $(this).find('button[type="submit"]');
                    submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Submitting...');

                    $.ajax({
                        url: 'request-installment.php',
                        method: 'POST',
                        data: $(this).serialize(),
                        dataType: 'json',
                        success: function (response) {
                            if (response.success) {
                                showToast('success', 'Success', response.message);
                                $('#installmentRequestModal').modal('hide');
                                setTimeout(() => location.reload(), 1500);
                            } else {
                                showToast('error', 'Error', response.message);
                            }
                        },
                        error: function () {
                            showToast('error', 'Error', 'An error occurred. Please try again.');
                        },
                        complete: function () {
                            submitBtn.prop('disabled', false).html('Submit Request');
                        }
                    });
                });
            });

            function downloadReceipt(receiptNo, feeComponent) {
                generateSecurePDF('../payments/receipt-print-pdf.php', {
                    receipt_no: receiptNo,
                    fee_component: feeComponent,
                    student_id: '<?php echo $student_id; ?>'
                });
            }

            function payFee(component, installmentId = null) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'pending-fee-payment.php';

                const componentInput = document.createElement('input');
                componentInput.type = 'hidden';
                componentInput.name = 'component';
                componentInput.value = component;
                form.appendChild(componentInput);

                if (installmentId) {
                    const installmentInput = document.createElement('input');
                    installmentInput.type = 'hidden';
                    installmentInput.name = 'installment_id';
                    installmentInput.value = installmentId;
                    form.appendChild(installmentInput);
                }

                document.body.appendChild(form);
                form.submit();
            }

            function payAllPendingFees() {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'pay-all-pending-fees.php';
                document.body.appendChild(form);
                form.submit();
            }
        </script>