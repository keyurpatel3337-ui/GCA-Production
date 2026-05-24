<?php
header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once OPERATION_FILE;
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/format_helper.php';

// Check if user is authorized
if (!hasRole(ROLE_COUNSELLOR) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_ACCOUNTANT)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// Get student ID
$student_id = $_GET['id'] ?? $_POST['id'] ?? null;

// Clear any old messages from previous pages at the start
if ($student_id) {
}

if (!$student_id) {
    set_flash_message('error', 'Student ID is required');
    header('Location: list.php');
    exit;
}

// Fetch student details directly from database
try {
    $stmt = $conn->prepare("SELECT s.*, 
                             p.amount as token_amount,
                             b.board_name, m.medium_name, g.group_name, c.course_name,
                             sch.school_name,
                             u.name as counsellor_name,
                             ay.year_name as academic_year
                             FROM tbl_gm_std_registration s
                             LEFT JOIN tbl_payments p ON s.id = p.student_id AND p.payment_type = 'token_fee' AND p.status = 'paid'
                             LEFT JOIN tbl_boards b ON s.board_id = b.id
                             LEFT JOIN tbl_medium m ON s.medium_id = m.id
                             LEFT JOIN tbl_group g ON s.group_id = g.id
                             LEFT JOIN tbl_courses c ON s.course_id = c.id
                             LEFT JOIN tbl_schools sch ON s.school_id = sch.id
                             LEFT JOIN tbl_users u ON s.counsellor_id = u.id
                             LEFT JOIN tbl_academic_years ay ON s.academic_year_id = ay.id
                             WHERE s.id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        set_flash_message('error', 'Student data not found');
        header('Location: list.php');
        exit;
    }

    // Check if admission is confirmed
    if (!$student['admission_confirmed']) {
        set_flash_message('error', 'Admission not yet confirmed for this student');
        header('Location: admission-confirm.php?id=' . $student_id);
        exit;
    }

    // Fetch fee config
    $stmt_fee = $conn->prepare("SELECT * FROM tbl_fee_config WHERE course_id = ? AND medium_id = ? AND group_id = ? AND is_active = 1 LIMIT 1");
    $stmt_fee->execute([$student['course_id'], $student['medium_id'], $student['group_id']]);
    $fee_config = $stmt_fee->fetch(PDO::FETCH_ASSOC);

    if (!$fee_config) {
        $fee_config = [
            'school_fee' => 0,
            'trust_facilities_fee' => 0,
            'tuition_fee_part1' => 0,
            'number_of_installments' => 3
        ];
    }

    // Calculate fees
    $total_fees = ($fee_config['school_fee'] ?? 0) + ($fee_config['trust_facilities_fee'] ?? 0) + ($fee_config['tuition_fee_part1'] ?? 0);
    $scholarship_amount = $student['scholarship_amount'] ?? 0;
    $scholarship_percentage = $student['scholarship_percentage'] ?? 0;
    $token_fee = !empty($student['token_amount']) ? $student['token_amount'] : ($fee_config['tuition_fee_part1'] ?? 0);
    $final_fees = $total_fees - $scholarship_amount;

} catch (PDOException $e) {
    set_flash_message('error', 'Database error: ' . $e->getMessage());
    header('Location: list.php');
    exit;
}

// Set page title
$page_title = 'Admission Letter - ' . ($student['admission_letter_number'] ?? '');

// Include header
include __DIR__ . '/../../include/header.php';
include __DIR__ . '/../../include/navbar.php';
include __DIR__ . '/../../include/sidebar.php';
?>


<div class="container-fluid">
    <?php
    if (isset($_SESSION['success_msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i> <?php
            echo $_SESSION['success_msg'];
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert">&times;</button>
        </div>
        <?php
    endif; ?>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-file-alt"></i> Admission Letter
                    </h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-success btn-sm" onclick="window.print()">
                            <i class="fas fa-print"></i> Print Letter
                        </button>
                        <form method="POST" action="<?php echo PORTAL_URL; ?>/scripts/generate_pdf.php" target="_blank"
                            style="display:inline;margin:0;">
                            <input type="hidden" name="id" value="<?php echo $student_id; ?>">
                            <button type="submit" class="btn btn-danger btn-sm">
                                <i class="fas fa-file-pdf"></i> Download PDF
                            </button>
                        </form>
                        <a href="list.php" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                    </div>
                </div>
                <div class="card-body" id="admission-letter">
                    <!-- Letter Header -->
                    <div class="text-center mb-4">
                        <h2><?php
                        echo SYSTEM_NAME; ?></h2>
                        <p>Regd. Office: <?php
                        echo SYSTEM_ADDRESS ?? 'Address Line 1, City, State - PIN'; ?></p>
                        <p>Phone: <?php
                        echo SYSTEM_PHONE ?? '+91-XXXXXXXXXX'; ?> | Email:
                            <?php
                            echo SYSTEM_EMAIL ?? 'info@example.com'; ?>
                        </p>
                        <hr>
                        <h4 class="text-uppercase"><u>ADMISSION LETTER</u></h4>
                    </div>

                    <div class="row mb-3">
                        <div class="col-6">
                            <strong>Letter No:</strong>
                            <?php
                            echo htmlspecialchars($student['admission_letter_number'] ?? 'N/A'); ?>
                        </div>
                        <div class="col-6 text-end">
                            <strong>Date:</strong>
                            <?php
                            echo isset($student['admission_confirmed_date']) ? date('d-M-Y', strtotime($student['admission_confirmed_date'])) : 'N/A'; ?>
                        </div>
                    </div>

                    <!-- Student Details -->
                    <div class="mb-4">
                        <p><strong>To,</strong></p>
                        <p>
                            <strong><?php
                            echo htmlspecialchars(($student['surname'] ?? '') . ' ' . ($student['student_name'] ?? '')); ?></strong><br>
                            S/o <?php
                            echo htmlspecialchars($student['fathers_name'] ?? 'N/A'); ?><br>
                            <?php
                            echo nl2br(htmlspecialchars($student['addr'] ?? '')); ?><br>
                            Mobile: <?php
                            echo htmlspecialchars($student['mob'] ?? 'N/A'); ?>
                        </p>
                    </div>

                    <p><strong>Subject: Admission Confirmation for Academic Year
                            <?php
                            echo date('Y'); ?>-<?php
                              echo date('Y') + 1; ?></strong></p>

                    <p>Dear <?php
                    echo htmlspecialchars($student['student_name'] ?? 'Student'); ?>,</p>

                    <p>We are pleased to inform you that your admission to <strong><?php
                    echo SYSTEM_NAME; ?></strong>
                        for the course <strong><?php
                        echo htmlspecialchars($student['board_name'] ?? 'N/A'); ?></strong>
                        (Standard <?php
                        echo htmlspecialchars($student['standard'] ?? 'N/A'); ?>) has been
                        <strong>confirmed</strong>.
                    </p>

                    <!-- Fee Details -->
                    <div class="mt-4">
                        <h5><u>Fee Structure:</u></h5>
                        <table class="table table-bordered">
                            <tr>
                                <td width="60%">Total Course Fees</td>
                                <td class="text-end">₹<?php
                                echo formatIndianCurrency($total_fees); ?></td>
                            </tr>
                            <?php
                            if ($scholarship_amount > 0): ?>
                                <tr>
                                    <td>Scholarship Granted
                                        <?php
                                        if ($scholarship_percentage > 0): ?>
                                            (<?php
                                            echo $scholarship_percentage; ?>%)
                                            <?php
                                        endif; ?>
                                    </td>
                                    <td class="text-end text-success">-
                                        ₹<?php
                                        echo formatIndianCurrency($scholarship_amount); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Net Payable Amount</strong></td>
                                    <td class="text-end"><strong>₹<?php
                                    echo formatIndianCurrency($final_fees); ?></strong>
                                    </td>
                                </tr>
                                <?php
                            endif; ?>
                            <tr class="bg-light">
                                <td colspan="2"><strong>Token Fee Breakdown (To be paid immediately)</strong></td>
                            </tr>
                            <tr>
                                <td class="ps-4">Tuition Fee Part 1 (incl. 18% GST)</td>
                                <td class="text-end">₹<?php echo formatIndianCurrency($token_fee); ?></td>
                            </tr>
                            <tr class="bg-warning">
                                <td><strong>Total Token Fee</strong></td>
                                <td class="text-end"><strong>₹<?php
                                echo formatIndianCurrency($token_fee); ?></strong></td>
                            </tr>
                            <tr class="bg-light">
                                <td colspan="2"><strong>Separate Payment Required</strong></td>
                            </tr>
                            <tr>
                                <td class="ps-4">School Fee</td>
                                <td class="text-end">₹<?php
                                echo formatIndianCurrency($fee_config['school_fee'] ?? 0); ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="ps-4">Trust Facilities Fee</td>
                                <td class="text-end">
                                    ₹<?php
                                    echo formatIndianCurrency($fee_config['trust_facilities_fee'] ?? 0); ?></td>
                            </tr>
                        </table>
                    </div>

                    <!-- Instructions -->
                    <div class="mt-4">
                        <h5><u>Next Steps:</u></h5>
                        <ol>
                            <li>Please submit this admission letter to the <strong>Accounts Department</strong></li>
                            <li>Pay the Token Fee of <strong>₹<?php
                            echo formatIndianCurrency($token_fee); ?></strong>
                                (Tuition Fee Part 1 with GST)</li>
                            <li><strong>Important:</strong> School Fee
                                (₹<?php
                                echo formatIndianCurrency($fee_config['school_fee'] ?? 0); ?>) and Trust
                                Facilities Fee
                                (₹<?php
                                echo formatIndianCurrency($fee_config['trust_facilities_fee'] ?? 0); ?>)
                                must be paid <strong>separately</strong></li>
                            <li>Payment modes accepted: <strong>Online or Offline</strong></li>
                            <li>After token fee payment, you will receive access to the <strong>Student
                                    Portal</strong></li>
                            <li>Login credentials will be: <strong>Aadhaar Number</strong> and <strong>Mobile Number
                                    (as password)</strong></li>
                            <li>The remaining fees can be paid in
                                <?php
                                echo $fee_config ? ($fee_config['number_of_installments'] ?? 'installments') : 'installments'; ?>
                                as per the schedule
                            </li>
                        </ol>
                    </div>

                    <!-- Terms & Conditions -->
                    <div class="mt-4">
                        <h5><u>Terms & Conditions:</u></h5>
                        <ul>
                            <li>The token fee is non-refundable</li>
                            <li>Regular attendance is mandatory (minimum 75%)</li>
                            <li>Fee installments must be paid on time to avoid late fee charges</li>
                            <li>Student must maintain discipline and follow institute rules</li>
                        </ul>
                    </div>

                    <p class="mt-4">We look forward to welcoming you to our institution.</p>

                    <div class="mt-5 row">
                        <div class="col-6">
                            <p>
                                <strong>Confirmed by:</strong><br>
                                <?php
                                echo htmlspecialchars($student['counsellor_name'] ?? 'N/A'); ?><br>
                                Counsellor
                            </p>
                        </div>
                        <div class="col-6 text-end">
                            <p>
                                <br><br>
                                <strong>Authorized Signatory</strong><br>
                                <?php
                                echo SYSTEM_NAME; ?>
                            </p>
                        </div>
                    </div>

                    <div class="text-center mt-4">
                        <small class="text-muted">This is a computer-generated document and does not require a
                            physical signature.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include '../../include/footer.php'; ?>



<?php
// Include footer
include __DIR__ . '/../../include/footer.php';
?>