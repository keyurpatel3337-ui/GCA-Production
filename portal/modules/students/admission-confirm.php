<?php
header('Content-Type: text/html; charset=utf-8');
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once __DIR__ . '/../../common/api_client.php';
require_once PORTAL_GLOBALVARIABLE;
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/format_helper.php';

// Handle form submission for admission confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student_id']) && isset($_POST['token_fee'])) {
    // This is form submission, process it
    $postData = [
        'student_id' => $_POST['student_id'],
        'token_fee' => $_POST['token_fee'],
        'payment_mode' => $_POST['payment_mode'] ?? 'offline',
        'scholarship_type_id' => $_POST['scholarship_type_id'] ?? null,
        'scholarship_rule_id' => $_POST['scholarship_rule_id'] ?? null,
        'scholarship_percentage' => $_POST['scholarship_percentage'] ?? 0,
        'scholarship_amount' => $_POST['scholarship_amount'] ?? 0,
        'additional_scholarship_type' => $_POST['additional_scholarship_type'] ?? null,
        'additional_scholarship_value' => $_POST['additional_scholarship_value'] ?? 0,
        'additional_scholarship_amount' => $_POST['additional_scholarship_amount'] ?? 0,
        'additional_scholarship_remarks' => $_POST['additional_scholarship_remarks'] ?? '',
        'counsellor_discount' => $_POST['counsellor_discount'] ?? 0,
        'remarks' => $_POST['remarks'] ?? ''
    ];

    $api = new APIClient();
    $response = $api->post('students/admission-confirm-save', $postData);

    if ($response && isset($response['success']) && $response['success'] === true) {
        set_flash_message('success', $response['message'] ?? 'Admission confirmed successfully');

        // Get student_id from response
        $confirmed_student_id = $response['data']['student_id'] ?? $_POST['student_id'];

        // If online payment, redirect to payment gateway
        if ($postData['payment_mode'] === 'online' && isset($response['data']['payment_url'])) {
            header('Location: ' . $response['data']['payment_url']);
            exit;
        }

        // For offline payment, auto-submit form to generate PDF in new tab and redirect to list
        exit;
    } else {
        // Log the actual response for debugging
        error_log("Admission confirm save failed. Response: " . print_r($response, true));
        set_flash_message('error', $response['error'] ?? $response['message'] ?? 'Failed to confirm admission');
        // Continue to show form with error
    }
}

// Debug: Log everything received

// Check if student ID is provided
$redirect_post = $_SESSION['_redirect_post'] ?? null;
unset($_SESSION['_redirect_post']);

// Debug: Log what we're receiving

if (!isset($_POST['id']) && !isset($_POST['student_id']) && !isset($_GET['id'])) {
    set_flash_message('error', 'Student ID is required');
    header('Location: students.php?view=all');
    exit;
}

$student_id = $_POST['id'] ?? $_POST['student_id'] ?? $_GET['id'] ?? $redirect_post['id'] ?? null;

// Fetch admission confirm data from API
$api = new APIClient();

$response = $api->get('students/admission-confirm', ['id' => $student_id]);

if ($response && isset($response['success']) && $response['success']) {
    $student = $response['data']['student'] ?? [];
    $fee_config = $response['data']['fee_config'] ?? null;

    // Clear old messages from other pages now that this page loaded successfully
    if (!isset($_POST['student_id'])) {
        // Only clear if not form submission (GET request)
    }
    $scholarship_types = $response['data']['scholarship_types'] ?? [];
    $scholarship_rules = $response['data']['scholarship_rules'] ?? [];
    $hostel_fee_amount = $response['data']['hostel_fee_amount'] ?? 0;
    $transport_fee_amount = $response['data']['transport_fee_amount'] ?? 0;
    $hostel_fee_config = $response['data']['hostel_fee_config'] ?? null;
    $transport_fee_config = $response['data']['transport_fee_config'] ?? null;

    // Calculate fee breakdown from fee_config
    $tuition_fee_part1 = $fee_config['tuition_fee_part1'] ?? 0;
    $tuition_fee_part2 = $fee_config['tuition_fee_part2'] ?? 0;
    $school_fee = $fee_config['school_fee'] ?? 0;
    $trust_facilities_fee = $fee_config['trust_facilities_fee'] ?? 0;
    $hostel_fee = $hostel_fee_amount;
    $transport_fee = $transport_fee_amount;

    // Calculate GST for tuition fee part 1
    $gst_rate = !empty($fee_config['gst_rate']) ? $fee_config['gst_rate'] : 18;
    $gst_applicable = $fee_config['gst_applicable'] ?? true;

    if ($gst_applicable && $gst_rate > 0) {
        $tuition_part1_gst = ($tuition_fee_part1 * $gst_rate) / 100;
        $tuition_part1_with_gst = $tuition_fee_part1 + $tuition_part1_gst;

        $tuition_part2_gst = ($tuition_fee_part2 * $gst_rate) / 100;
        $tuition_part2_with_gst = $tuition_fee_part2 + $tuition_part2_gst;
    } else {
        $tuition_part1_gst = 0;
        $tuition_part1_with_gst = $tuition_fee_part1;

        $tuition_part2_gst = 0;
        $tuition_part2_with_gst = $tuition_fee_part2;
    }

    // Token fee is Tuition Part 1 with GST
    $token_fee_total = $tuition_part1_with_gst;

    // Remaining Tuition is Part 2 with GST
    $balance_after_token = $tuition_part2_with_gst;

    // Calculate best scholarship based on marks for each type
    // Check if marks need to be manually entered
    $marks_found = !empty($student['gmsat_marks']) || !empty($student['board_percentage']);
    $gmsat_marks = floatval($student['gmsat_marks'] ?? 0);
    $board_percentage = floatval($student['board_percentage'] ?? 0);

    // Allow manual override via POST if marks are being updated
    if (isset($_POST['manual_gmsat_marks']) || isset($_POST['manual_board_percentage'])) {
        $gmsat_marks = floatval($_POST['manual_gmsat_marks'] ?? $gmsat_marks);
        $board_percentage = floatval($_POST['manual_board_percentage'] ?? $board_percentage);
        $marks_found = true;
    }

    $course_id = $student['course_id'] ?? 0;
    $group_id = $student['group_id'] ?? 0;

    // Calculate eligible scholarships by type
    $eligible_scholarships = [];
    $best_scholarship = 0;
    $best_scholarship_type = '';
    $best_scholarship_details = '';
    $best_scholarship_rule_id = null;

    foreach ($scholarship_rules as $rule) {
        if ($rule['course_id'] == $course_id && $rule['group_id'] == $group_id && $rule['is_active']) {
            $discount = floatval($rule['scholarship_discount_amount']);
            $type_id = $rule['scholarship_type_id'];
            $rule_id = $rule['id'];
            $type_name = '';

            // Get scholarship type name
            foreach ($scholarship_types as $type) {
                if ($type['id'] == $type_id) {
                    $type_name = $type['type_name'];
                    break;
                }
            }

            // GMSAT based
            if (!empty($rule['gmsat_minimum_mark']) && !empty($rule['gmsat_maximum_mark'])) {
                if ($gmsat_marks >= $rule['gmsat_minimum_mark'] && $gmsat_marks <= $rule['gmsat_maximum_mark']) {
                    if (!isset($eligible_scholarships[$type_id]) || $discount > $eligible_scholarships[$type_id]['discount']) {
                        $eligible_scholarships[$type_id] = [
                            'type_name' => $type_name,
                            'discount' => $discount,
                            'rule_id' => $rule_id,
                            'details' => "{$type_name}: GMSAT {$rule['gmsat_minimum_mark']}-{$rule['gmsat_maximum_mark']} marks"
                        ];
                        if ($discount > $best_scholarship) {
                            $best_scholarship = $discount;
                            $best_scholarship_type = $type_id;
                            $best_scholarship_rule_id = $rule_id;
                            $best_scholarship_details = $eligible_scholarships[$type_id]['details'] . " = {$discount}%";
                        }
                    }
                }
            }

            // Board percentage based
            if (!empty($rule['board_pr_minimum']) && !empty($rule['board_pr_maximum'])) {
                if ($board_percentage >= floatval($rule['board_pr_minimum']) && $board_percentage <= floatval($rule['board_pr_maximum'])) {
                    if (!isset($eligible_scholarships[$type_id]) || $discount > $eligible_scholarships[$type_id]['discount']) {
                        $eligible_scholarships[$type_id] = [
                            'type_name' => $type_name,
                            'discount' => $discount,
                            'rule_id' => $rule_id,
                            'details' => "{$type_name}: Board {$rule['board_pr_minimum']}-{$rule['board_pr_maximum']}%"
                        ];
                        if ($discount > $best_scholarship) {
                            $best_scholarship = $discount;
                            $best_scholarship_type = $type_id;
                            $best_scholarship_rule_id = $rule_id;
                            $best_scholarship_details = $eligible_scholarships[$type_id]['details'] . " = {$discount}%";
                        }
                    }
                }
            }
        }
    }

    $auto_scholarship = $best_scholarship;
    $auto_scholarship_details = $best_scholarship_details;
} else {
    set_flash_message('error', $response['error'] ?? 'Failed to load admission data');
    header('Location: students.php?view=all');
    exit;
}

$page_title = "Confirm Admission";
include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>



<div class="content-wrapper">
    <div class="confirm-header animate__animated animate__fadeIn">
        <div class="container-fluid">
            <h1 class="fw-bold mb-2">Admission Confirmation</h1>
            <p class="opacity-75 mb-0">Review fees and finalize student enrollment</p>
        </div>
    </div>

    <div class="container-fluid mt-5 pb-5">
        <?php if (isset($_SESSION['error_msg'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle"></i> <?php echo gca_safe_html($_SESSION['error_msg']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success_msg'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> <?php echo gca_safe_html($_SESSION['success_msg']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['info_msg'])): ?>
            <div class="alert alert-info alert-dismissible fade show">
                <i class="fas fa-info-circle"></i> <?php echo gca_safe_html($_SESSION['info_msg']);
                unset($_SESSION['info_msg']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Student Details -->
            <div class="col-lg-7">
                <div class="glass-card h-100 animate__animated animate__fadeInLeft">
                    <div class="card-header bg-transparent border-0 pt-4 px-4">
                        <h5 class="fw-bold mb-0 text-dark">
                            <i class="fas fa-user-graduate me-2 text-primary"></i> Student Profile
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <div class="detail-icon"><i class="fas fa-id-card"></i></div>
                                    <div>
                                        <div class="small text-muted">Full Name</div>
                                        <div class="fw-bold">
                                            <?php echo htmlspecialchars(($student['surname'] ?? '') . ' ' . ($student['student_name'] ?? '')); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <div class="detail-icon"><i class="fas fa-users"></i></div>
                                    <div>
                                        <div class="small text-muted">Father's Name</div>
                                        <div class="fw-bold">
                                            <?php echo htmlspecialchars($student['fathers_name'] ?? 'N/A'); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <div class="detail-icon"><i class="fas fa-phone"></i></div>
                                    <div>
                                        <div class="small text-muted">Mobile</div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($student['mob'] ?? 'N/A'); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <div class="detail-icon"><i class="fas fa-hashtag"></i></div>
                                    <div>
                                        <div class="small text-muted">Aadhaar</div>
                                        <div class="fw-bold">
                                            <?php echo htmlspecialchars($student['aadhaar'] ?? 'N/A'); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="detail-item">
                                    <div>
                                        <div class="small text-muted">Board</div>
                                        <div class="fw-bold">
                                            <?php echo htmlspecialchars($student['board_name'] ?? 'N/A'); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="detail-item">
                                    <div>
                                        <div class="small text-muted">Standard</div>
                                        <div class="fw-bold">
                                            <?php echo htmlspecialchars($student['standard'] ?? 'N/A'); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="detail-item">
                                    <div>
                                        <div class="small text-muted">Group</div>
                                        <div class="fw-bold">
                                            <?php echo htmlspecialchars($student['group_name'] ?? 'N/A'); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 p-3 rounded-3 bg-light border d-flex gap-4">
                            <div class="d-flex align-items-center">
                                <div class="me-2 small fw-bold">Hostel:</div>
                                <span
                                    class="badge <?php echo (strtolower($student['hostel_required'] ?? 'no') === 'yes') ? 'bg-success' : 'bg-secondary'; ?> rounded-pill">
                                    <?php echo htmlspecialchars(ucfirst($student['hostel_required'] ?? 'No')); ?>
                                </span>
                            </div>
                            <div class="d-flex align-items-center">
                                <div class="me-2 small fw-bold">Transport:</div>
                                <span
                                    class="badge <?php echo (strtolower($student['transport_required'] ?? 'no') === 'yes') ? 'bg-success' : 'bg-secondary'; ?> rounded-pill">
                                    <?php echo htmlspecialchars(ucfirst($student['transport_required'] ?? 'No')); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Fee Structure -->
            <div class="col-lg-5">
                <div class="glass-card h-100 animate__animated animate__fadeInRight">
                    <div class="card-header bg-transparent border-0 pt-4 px-4">
                        <h5 class="fw-bold mb-0 text-dark">
                            <i class="fas fa-coins me-2 text-warning"></i> Fee Configuration
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($fee_config): ?>
                            <div class="mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="text-muted fw-bold">Token Fee (Payable Now)</span>
                                    <span
                                        class="fs-4 fw-bold text-primary">₹<?php echo formatIndianCurrency($token_fee_total); ?></span>
                                </div>
                                <div
                                    class="p-3 bg-primary bg-opacity-10 rounded-3 border border-primary border-opacity-25 small">
                                    <i class="fas fa-info-circle me-1"></i> Includes Tuition Part 1 with 18% GST.
                                </div>
                            </div>

                            <div id="feeBreakdown" class="space-y-3">
                                <div class="d-flex justify-content-between py-2 border-bottom border-dashed">
                                    <span class="text-muted">School Fee</span>
                                    <span class="fw-bold">₹<?php echo formatIndianCurrency($school_fee); ?></span>
                                </div>
                                <div class="d-flex justify-content-between py-2 border-bottom border-dashed">
                                    <span class="text-muted">Trust Facilities</span>
                                    <span
                                        class="fw-bold">₹<?php echo formatIndianCurrency($trust_facilities_fee); ?></span>
                                </div>
                                <div class="d-flex justify-content-between py-2 border-bottom border-dashed">
                                    <span class="text-muted">Tuition Fee Part 2 <small>(incl. GST)</small></span>
                                    <span
                                        class="fw-bold">₹<?php echo formatIndianCurrency($tuition_part2_with_gst); ?></span>
                                </div>
                                <?php if ($hostel_fee > 0): ?>
                                    <div id="hostel_fee_row"
                                        class="d-flex justify-content-between py-2 border-bottom border-dashed">
                                        <span class="text-muted">Hostel Fee</span>
                                        <span
                                            class="fw-bold fee-value">₹<?php echo formatIndianCurrency($hostel_fee); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div
                                class="mt-4 p-3 bg-dark text-white rounded-3 shadow-sm d-flex justify-content-between align-items-center">
                                <span class="fw-bold">Total Balance Remaining</span>
                                <span id="totalBalanceRemaining"
                                    class="fs-5 fw-bold">₹<?php echo formatIndianCurrency($balance_after_token + $school_fee + $trust_facilities_fee); ?></span>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-exclamation-triangle text-warning fa-3x mb-3"></i>
                                <p class="text-muted">Fee configuration not found for this student's course.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Scholarship Section -->
        <?php if (!empty($scholarship_types)): ?>
            <div class="glass-card mt-4 animate__animated animate__fadeInUp">
                <div
                    class="card-header bg-success bg-opacity-10 border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-success">
                        <i class="fas fa-graduation-cap me-2"></i> Scholarship & Discounts
                    </h5>
                </div>
                <div class="card-body p-4">
                    <?php if (!$marks_found): ?>
                        <div class="alert alert-soft-warning border-0 p-4 rounded-3 d-flex align-items-center mb-4">
                            <div class="me-4 fs-3"><i class="fas fa-exclamation-triangle text-warning"></i></div>
                            <div>
                                <h6 class="fw-bold mb-1">Marks Not Found</h6>
                                <p class="mb-2 small">Enter marks manually to check scholarship eligibility.</p>
                                <button type="button" class="btn btn-warning btn-sm fw-bold rounded-pill"
                                    id="showManualMarksEntry">
                                    <i class="fas fa-edit me-1"></i> Enter Marks Manually
                                </button>
                            </div>
                        </div>
                    <?php elseif ($auto_scholarship > 0 || !empty($eligible_scholarships)): ?>
                        <div class="alert alert-soft-success border-0 p-4 rounded-3 mb-4 shadow-sm css-admission-confirm-87f6da">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-success text-white rounded-circle p-2 me-3 shadow-sm">
                                    <i class="fas fa-trophy"></i>
                                </div>
                                <h5 class="fw-bold mb-0 text-success">Scholarship Eligible!</h5>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-auto">
                                    <div class="px-3 py-1 bg-white rounded-pill small border fw-bold text-dark">
                                        GMSAT: <?php echo $gmsat_marks; ?>
                                    </div>
                                </div>
                                <div class="col-md-auto">
                                    <div class="px-3 py-1 bg-white rounded-pill small border fw-bold text-dark">
                                        Board: <?php echo formatIndianCurrency($board_percentage); ?>%
                                    </div>
                                </div>
                            </div>
                            <?php if ($auto_scholarship > 0): ?>
                                <div class="mt-3 p-3 bg-white bg-opacity-50 rounded-3 border-start border-4 border-success">
                                    <div class="fw-bold text-dark"><?php echo $auto_scholarship_details; ?></div>
                                    <div class="small text-muted mt-1"><i class="fas fa-info-circle me-1"></i> Applied to Tuition
                                        Fee Part 2</div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <form id="admissionConfirmForm" method="POST" action="">
                        <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
                        <input type="hidden" name="token_fee" value="<?php echo $token_fee_total; ?>">
                        <input type="hidden" id="auto_scholarship_percent" value="<?php echo $auto_scholarship; ?>">
                        <input type="hidden" name="scholarship_rule_id" id="scholarship_rule_id" value="">
                        <input type="hidden" name="scholarship_amount" id="scholarship_amount" value="0">
                        <input type="hidden" name="scholarship_percentage" id="scholarship_percentage" value="0">
                        <input type="hidden" name="additional_scholarship_amount" id="additional_scholarship_amount"
                            value="0">
                        <input type="hidden" name="additional_scholarship_value" id="additional_scholarship_value"
                            value="0">
                        <input type="hidden" name="additional_scholarship_type" id="additional_scholarship_type" value="">
                        <input type="hidden" name="additional_scholarship_remarks" id="additional_scholarship_remarks"
                            value="">
                        <input type="hidden" name="manual_gmsat_marks" id="hidden_gmsat_marks"
                            value="<?php echo $gmsat_marks; ?>">
                        <input type="hidden" name="manual_board_percentage" id="hidden_board_percentage"
                            value="<?php echo $board_percentage; ?>">
                        <input type="hidden" name="hostel_required" id="hostel_required"
                            value="<?php echo $student['hostel_required'] ?? 'No'; ?>">
                        <input type="hidden" name="transport_required" id="transport_required"
                            value="<?php echo $student['transport_required'] ?? 'No'; ?>">

                        <div class="row g-4 mt-2">
                            <div class="col-lg-8">
                                <div class="row g-4">
                                    <div class="col-md-12">
                                        <label class="fw-bold mb-2 text-dark">Select Scholarship Type</label>
                                        <select name="scholarship_type_id" id="scholarship_type_id"
                                            class="form-select form-select-lg rounded-3 shadow-sm border-primary border-opacity-25 css-admission-confirm-70fb8b">
                                            <option value="">-- No Scholarship --</option>
                                            <?php foreach ($scholarship_types as $type): ?>
                                                <?php
                                                $is_eligible = isset($eligible_scholarships[$type['id']]);
                                                $discount_text = $is_eligible ? $eligible_scholarships[$type['id']]['discount'] . '% Discount' : 'Not Eligible';
                                                $rule_id = $is_eligible ? $eligible_scholarships[$type['id']]['rule_id'] : 0;
                                                $selected = ($best_scholarship_type == $type['id']) ? 'selected' : '';
                                                ?>
                                                <option value="<?php echo $type['id']; ?>"
                                                    data-discount="<?php echo $is_eligible ? $eligible_scholarships[$type['id']]['discount'] : 0; ?>"
                                                    data-rule-id="<?php echo $rule_id; ?>"
                                                    data-eligible="<?php echo $is_eligible ? 'yes' : 'no'; ?>"
                                                    data-details="<?php echo $is_eligible ? htmlspecialchars($eligible_scholarships[$type['id']]['details'] ?? '') : ''; ?>"
                                                    <?php echo $selected; ?>>
                                                    <?php echo htmlspecialchars($type['type_name'] ?? ''); ?> -
                                                    <?php echo $discount_text; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="mt-2 small text-muted">
                                            <?php if ($marks_found): ?>
                                                <i class="fas fa-check-circle text-success me-1"></i> Pre-selected based on
                                                student marks.
                                                <button type="button" class="btn btn-link btn-sm p-0 fw-bold"
                                                    id="changeMarksBtn">Change Marks</button>
                                            <?php else: ?>
                                                <i class="fas fa-info-circle me-1"></i> Select a type to enter marks manually.
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Manual Marks Entry Section -->
                                    <div class="col-md-12 css-admission-confirm-24c749" id="manualMarksSection">
                                        <div
                                            class="p-4 rounded-3 bg-light border border-dashed border-primary border-opacity-25">
                                            <h6 class="fw-bold mb-3"><i class="fas fa-edit me-2"></i> Enter Marks for
                                                Eligibility</h6>
                                            <div id="selectScholarshipFirst" class="small text-muted mb-3">
                                                Please select a scholarship type above first.
                                            </div>
                                            <div id="manualMarksForm" class="css-admission-confirm-93b8ea">
                                                <div class="row g-3">
                                                    <div class="col-md-4 css-admission-confirm-93b8ea" id="gmsatMarksField">
                                                        <label class="small fw-bold mb-1">GMSAT Marks</label>
                                                        <input type="number" name="manual_gmsat_marks_input"
                                                            id="manual_gmsat_marks" class="form-control" min="0" max="200"
                                                            step="0.01" value="<?php echo $gmsat_marks; ?>">
                                                        <div class="extra-small text-muted mt-1" id="gmsatRangeInfo"></div>
                                                    </div>
                                                    <div class="col-md-4 css-admission-confirm-93b8ea" id="boardPercentageField">
                                                        <label class="small fw-bold mb-1">Board %</label>
                                                        <input type="number" name="manual_board_percentage_input"
                                                            id="manual_board_percentage" class="form-control" min="0"
                                                            max="100" step="0.01" value="<?php echo $board_percentage; ?>">
                                                        <div class="extra-small text-muted mt-1" id="boardRangeInfo"></div>
                                                    </div>
                                                    <div class="col-md-4 d-flex align-items-end">
                                                        <button type="button" id="checkEligibilityBtn"
                                                            class="btn btn-primary btn-sm w-100 fw-bold">
                                                            Check Eligibility
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="requirement-box shadow-sm">
                                            <label class="fw-bold mb-3 d-flex align-items-center">
                                                <i class="fas fa-building me-2 text-warning"></i> Hostel Required?
                                            </label>
                                            <div class="btn-group w-100" role="group">
                                                <input type="radio" class="btn-check" name="hostel_toggle" id="hostel_yes"
                                                    value="Yes" <?php echo (strtolower($student['hostel_required'] ?? 'no') === 'yes') ? 'checked' : ''; ?>>
                                                <label class="btn btn-outline-success py-2 fw-bold"
                                                    for="hostel_yes">YES</label>
                                                <input type="radio" class="btn-check" name="hostel_toggle" id="hostel_no"
                                                    value="No" <?php echo (strtolower($student['hostel_required'] ?? 'no') !== 'yes') ? 'checked' : ''; ?>>
                                                <label class="btn btn-outline-secondary py-2 fw-bold"
                                                    for="hostel_no">NO</label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="requirement-box shadow-sm">
                                            <label class="fw-bold mb-3 d-flex align-items-center">
                                                <i class="fas fa-bus me-2 text-primary"></i> Transport Required?
                                            </label>
                                            <div class="btn-group w-100" role="group">
                                                <input type="radio" class="btn-check" name="transport_toggle"
                                                    id="transport_yes" value="Yes" <?php echo (strtolower($student['transport_required'] ?? 'no') === 'yes') ? 'checked' : ''; ?>>
                                                <label class="btn btn-outline-success py-2 fw-bold"
                                                    for="transport_yes">YES</label>
                                                <input type="radio" class="btn-check" name="transport_toggle"
                                                    id="transport_no" value="No" <?php echo (strtolower($student['transport_required'] ?? 'no') !== 'yes') ? 'checked' : ''; ?>>
                                                <label class="btn btn-outline-secondary py-2 fw-bold"
                                                    for="transport_no">NO</label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="fw-bold mb-2">Additional Discount (Max 5%)</label>
                                        <div class="input-group input-group-lg">
                                            <input type="number" name="counsellor_discount" id="counsellor_discount"
                                                class="form-control rounded-start-3" min="0" max="5" step="0.1" value="0">
                                            <span class="input-group-text bg-light rounded-end-3 fw-bold">%</span>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="fw-bold mb-2">Payment Mode</label>
                                        <select name="payment_mode" id="payment_mode"
                                            class="form-select form-select-lg rounded-3" required>
                                            <option value="online">Online Payment</option>
                                            <option value="offline" selected>Offline Payment</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-4">
                                <div class="summary-card h-100 d-flex flex-column justify-content-between shadow-lg">
                                    <div>
                                        <h5 class="fw-bold mb-4 opacity-75">Payout Summary</h5>
                                        <div class="d-flex justify-content-between mb-3">
                                            <span>Original Token</span>
                                            <span>₹<?php echo formatIndianCurrency($token_fee_total); ?></span>
                                        </div>
                                        <div id="summaryDiscountRow" class="text-success small css-admission-confirm-93b8ea">
                                            <div class="d-flex justify-content-between mb-1">
                                                <span>Total Savings</span>
                                                <span>- ₹<span id="summaryDiscountAmount">0</span></span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-4 pt-4 border-top border-secondary">
                                        <div class="text-muted small mb-1">AMOUT TO PAY NOW</div>
                                        <div class="payable-amount mb-4">₹<span
                                                id="payableAmountDisplay"><?php echo formatIndianCurrency($token_fee_total); ?></span>
                                        </div>

                                        <button type="submit"
                                            class="btn btn-primary btn-lg w-100 py-3 rounded-pill fw-bold shadow">
                                            CONFIRM ADMISSION <i class="fas fa-arrow-right ms-2"></i>
                                        </button>
                                        <a href="admission-confirm-list.php"
                                            class="btn btn-link btn-sm text-secondary w-100 mt-2 text-decoration-none">
                                            Cancel and back to list
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="row mt-3">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-body">
                            <form id="admissionConfirmForm" method="POST" action="">
                                <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
                                <input type="hidden" name="token_fee" value="<?php echo $token_fee_total ?? 0; ?>">

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label><strong>Payment Mode</strong></label>
                                            <select name="payment_mode" id="payment_mode" class="form-control" required>
                                                <option value="online">Online Payment</option>
                                                <option value="offline">Offline Payment</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="row mt-3">
                                    <div class="col-md-12 text-center">
                                        <button type="submit" class="btn btn-success btn-lg">
                                            <i class="fas fa-check-circle"></i> Confirm Admission & Pay Token Fee
                                        </button>
                                        <a href="admission-confirm-list.php" class="btn btn-secondary btn-lg">
                                            <i class="fas fa-arrow-left"></i> Back to List
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php include '../../include/footer.php'; ?>

    <script>
        $(document).ready(function () {
            var tuitionPart1Base = <?php echo $tuition_fee_part1 ?? 0; ?>; // Base amount without GST
            var tuitionPart1Gst = <?php echo $tuition_part1_gst ?? 0; ?>;
            var originalTokenFee = <?php echo $token_fee_total ?? 0; ?>; // Part 1 with GST
            var tuitionPart2Base = <?php echo $tuition_fee_part2 ?? 0; ?>; // Base amount without GST
            var tuitionPart2WithGst = <?php echo $tuition_part2_with_gst ?? 0; ?>;
            var tuitionPart2Gst = <?php echo $tuition_part2_gst ?? 0; ?>;
            var schoolFee = <?php echo $school_fee ?? 0; ?>;
            var trustFacilitiesFee = <?php echo $trust_facilities_fee ?? 0; ?>;
            var hostelFee = <?php echo $hostel_fee ?? 0; ?>;
            var transportFee = <?php echo $transport_fee ?? 0; ?>;
            var autoScholarship = <?php echo $auto_scholarship ?? 0; ?>;
            var autoScholarshipDetails = <?php echo json_encode($auto_scholarship_details ?? ''); ?>;
            var selectedScholarshipType = "";
            var autoDiscountAmount = 0;
            var counsellorDiscountAmount = 0;
            var scholarshipRules = <?php echo json_encode($scholarship_rules ?? []); ?>;
            var courseId = <?php echo $course_id ?? 0; ?>;
            var groupId = <?php echo $group_id ?? 0; ?>;
            var marksFound = <?php echo $marks_found ? 'true' : 'false'; ?>;

            // Hostel and Transport fee configurations
            var hostelFeeConfig = <?php echo json_encode($hostel_fee_config ?? null); ?>;
            var transportFeeConfig = <?php echo json_encode($transport_fee_config ?? null); ?>;
            var studentGender = '<?php echo strtolower($student['gender'] ?? 'male'); ?>';
            var currentHostelFee = 0;
            var currentTransportFee = 0;

            // Initialize scholarship rule ID if best match is pre-selected
            <?php if ($best_scholarship_rule_id): ?>
                $('#scholarship_rule_id').val('<?php echo $best_scholarship_rule_id; ?>');
            <?php endif; ?>

            console.log('Debug - scholarshipRules:', scholarshipRules);
            console.log('Debug - scholarshipRules count:', scholarshipRules.length);
            console.log('Debug - courseId:', courseId, 'groupId:', groupId);

            function formatIndianCurrency(amount) {
                return amount.toLocaleString('en-IN', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }

            // Handle manual marks entry toggle
            $('#showManualMarksEntry, #changeMarksBtn').click(function () {
                $('#manualMarksSection').slideDown();
                $(this).hide();
                var selectedTypeId = $('#scholarship_type_id').val();
                if (selectedTypeId && marksFound) {
                    marksFound = false;
                    updateManualMarksForm(selectedTypeId);
                }
            });

            // Function to update manual marks form fields based on scholarship type
            function updateManualMarksForm(typeId) {
                var relevantRules = scholarshipRules.filter(function (rule) {
                    return rule.scholarship_type_id == typeId && rule.course_id == courseId && rule.group_id == groupId && rule.is_active;
                });

                if (relevantRules.length === 0) {
                    $('#manualMarksForm').hide();
                    $('#selectScholarshipFirst').html('<i class="fas fa-exclamation-triangle"></i> No rules found for this scholarship type and your course/group.');
                    $('#selectScholarshipFirst').removeClass('alert-info').addClass('alert-warning').show();
                    return;
                }

                var hasGmsatRules = false;
                var hasBoardRules = false;
                var gmsatRanges = [];
                var boardRanges = [];

                relevantRules.forEach(function (rule) {
                    if (rule.gmsat_minimum_mark && rule.gmsat_maximum_mark) {
                        hasGmsatRules = true;
                        gmsatRanges.push(rule.gmsat_minimum_mark + '-' + rule.gmsat_maximum_mark + ' (' + rule.scholarship_discount_amount + '%)');
                    }
                    if (rule.board_pr_minimum && rule.board_pr_maximum) {
                        hasBoardRules = true;
                        boardRanges.push(rule.board_pr_minimum + '-' + rule.board_pr_maximum + '% (' + rule.scholarship_discount_amount + '%)');
                    }
                });

                $('#selectScholarshipFirst').hide();
                $('#manualMarksForm').show();

                if (hasGmsatRules) {
                    $('#gmsatMarksField').show();
                    $('#gmsatRangeInfo').text('Eligible ranges: ' + gmsatRanges.join(', '));
                    $('#manual_gmsat_marks').prop('required', true);
                } else {
                    $('#gmsatMarksField').hide();
                    $('#manual_gmsat_marks').prop('required', false);
                }

                if (hasBoardRules) {
                    $('#boardPercentageField').show();
                    $('#boardRangeInfo').text('Eligible ranges: ' + boardRanges.join(', '));
                    $('#manual_board_percentage').prop('required', true);
                } else {
                    $('#boardPercentageField').hide();
                    $('#manual_board_percentage').prop('required', false);
                }
            }

            // Handle manual marks eligibility check
            $('#checkEligibilityBtn').click(function (e) {
                e.preventDefault();
                var gmsat = parseFloat($('#manual_gmsat_marks').val()) || 0;
                var board = parseFloat($('#manual_board_percentage').val()) || 0;
                var gmsatRequired = $('#gmsatMarksField').is(':visible') && $('#manual_gmsat_marks').prop('required');
                var boardRequired = $('#boardPercentageField').is(':visible') && $('#manual_board_percentage').prop('required');

                if (gmsatRequired && gmsat <= 0) {
                    if (typeof showToast === 'function') {
                        showToast('warning', 'GMSAT Marks Required', 'Please enter GMSAT marks for the selected scholarship type');
                    } else {
                        alert('Please enter GMSAT marks for the selected scholarship type');
                    }
                    return false;
                }

                if (boardRequired && board <= 0) {
                    if (typeof showToast === 'function') {
                        showToast('warning', 'Board Percentage Required', 'Please enter Board percentage for the selected scholarship type');
                    } else {
                        alert('Please enter Board percentage for the selected scholarship type');
                    }
                    return false;
                }

                if (!gmsatRequired && !boardRequired && gmsat <= 0 && board <= 0) {
                    if (typeof showToast === 'function') {
                        showToast('warning', 'Marks Required', 'Please enter marks for eligibility check');
                    } else {
                        alert('Please enter marks for eligibility check');
                    }
                    return false;
                }

                $('#hidden_gmsat_marks').val(gmsat);
                $('#hidden_board_percentage').val(board);

                const btn = $(this);
                const originalBtnHtml = btn.html();
                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Checking...');

                var form = $('<form>', {
                    'method': 'POST',
                    'action': ''
                });
                form.append($('<input>', {
                    'type': 'hidden',
                    'name': 'id',
                    'value': '<?php echo $student_id; ?>'
                }));
                form.append($('<input>', {
                    'type': 'hidden',
                    'name': 'manual_gmsat_marks',
                    'value': gmsat
                }));
                form.append($('<input>', {
                    'type': 'hidden',
                    'name': 'manual_board_percentage',
                    'value': board
                }));
                $('body').append(form);
                form.submit();
            });

            // Handle scholarship type selection change
            $('#scholarship_type_id').change(function () {
                var selectedOption = $(this).find('option:selected');
                var selectedTypeId = $(this).val();
                var discount = parseFloat(selectedOption.attr('data-discount')) || 0;
                var ruleId = selectedOption.attr('data-rule-id') || '';
                var details = selectedOption.attr('data-details') || '';
                var isEligible = selectedOption.attr('data-eligible');

                if (selectedTypeId && !marksFound) {
                    updateManualMarksForm(selectedTypeId);
                }

                if (isEligible === 'yes' && discount > 0) {
                    autoScholarship = discount;
                    autoScholarshipDetails = details;
                    selectedScholarshipType = selectedOption.text();
                    $('#scholarship_rule_id').val(ruleId);
                } else if ($(this).val() === '') {
                    autoScholarship = 0;
                    autoScholarshipDetails = '';
                    selectedScholarshipType = '';
                    $('#scholarship_rule_id').val('');
                    $('#manualMarksForm').hide();
                    $('#selectScholarshipFirst').show();
                } else {
                    if (typeof showToast === 'function') {
                        showToast('warning', 'Not Eligible', 'Student does not meet the criteria for this scholarship type. Please enter marks manually to check eligibility.');
                    }

                    if (selectedTypeId && !marksFound) {
                        updateManualMarksForm(selectedTypeId);
                    }
                    autoScholarship = 0;
                    autoScholarshipDetails = '';
                    $('#scholarship_rule_id').val('');
                }

                updateAllCalculations();
            });

            function updateAllCalculations() {
                var counsellorDiscount = parseFloat($('#counsellor_discount').val()) || 0;
                if (counsellorDiscount > 5) {
                    counsellorDiscount = 5;
                    $('#counsellor_discount').val(5);
                    if (typeof showToast === 'function') {
                        showToast('warning', 'Maximum Limit', 'Counsellor discount cannot exceed 5%');
                    }
                }
                if (counsellorDiscount < 0) {
                    counsellorDiscount = 0;
                    $('#counsellor_discount').val(0);
                }

                var totalDiscountPercent = autoScholarship + counsellorDiscount;
                // Apply discount on BOTH tuition parts combined BASE amount (before GST)
                var totalTuitionBase = tuitionPart1Base + tuitionPart2Base;
                var autoDiscountBase = totalTuitionBase * (autoScholarship / 100);
                var counsellorDiscountBase = totalTuitionBase * (counsellorDiscount / 100);
                var totalDiscountBase = autoDiscountBase + counsellorDiscountBase;

                // After discount, calculate new base amount and add GST
                var discountedTuitionBase = totalTuitionBase - totalDiscountBase;
                var gstRate = <?php echo ($gst_rate > 0) ? $gst_rate : 18; ?>;
                var discountedTuitionGst = (discountedTuitionBase * gstRate) / 100;
                var totalTuitionAfterDiscount = discountedTuitionBase + discountedTuitionGst;

                // Token fee is FIXED at 11,800 (to pay now)
                var payableAmount = originalTokenFee;

                // Remaining Part 2 = Total tuition after discount - Token fee
                var discountedTuitionPart2 = totalTuitionAfterDiscount - originalTokenFee;

                // For display: discount amounts with GST
                var autoDiscountGst = (autoDiscountBase * gstRate) / 100;
                var counsellorDiscountGst = (counsellorDiscountBase * gstRate) / 100;
                autoDiscountAmount = autoDiscountBase + autoDiscountGst;
                counsellorDiscountAmount = counsellorDiscountBase + counsellorDiscountGst;
                var totalDiscountAmount = autoDiscountAmount + counsellorDiscountAmount;

                // Debug logging
                console.log('GST Calculation Debug:');
                console.log('GST Rate:', gstRate);
                console.log('Auto Discount Base:', autoDiscountBase);
                console.log('Auto Discount GST:', autoDiscountGst);
                console.log('Auto Discount Total (with GST):', autoDiscountAmount);
                console.log('Counsellor Discount Base:', counsellorDiscountBase);
                console.log('Counsellor Discount GST:', counsellorDiscountGst);
                console.log('Counsellor Discount Total (with GST):', counsellorDiscountAmount);
                console.log('Total Discount Amount:', totalDiscountAmount);

                var totalBalance = schoolFee + trustFacilitiesFee + discountedTuitionPart2 + hostelFee;

                // Update hidden fields for form submission (amounts WITH GST)
                $('#scholarship_amount').val(autoDiscountAmount.toFixed(2));
                $('#scholarship_percentage').val(autoScholarship.toFixed(2));
                $('#additional_scholarship_amount').val(counsellorDiscountAmount.toFixed(2));
                $('#additional_scholarship_value').val(counsellorDiscount.toFixed(2));
                $('#additional_scholarship_type').val(counsellorDiscount > 0 ? 'percentage' : '');
                $('#additional_scholarship_remarks').val(counsellorDiscount > 0 ? 'Counsellor Discretionary Discount: ' + counsellorDiscount.toFixed(2) + '%' : '');

                if (autoScholarship > 0) {
                    $('#autoDiscountPercent').text(autoScholarship.toFixed(1));
                    $('#autoDiscountAmount').text(formatIndianCurrency(autoDiscountAmount));
                    $('#autoScholarshipRow').show();
                } else {
                    $('#autoScholarshipRow').hide();
                }

                if (counsellorDiscount > 0) {
                    $('#counsellorPercent').text(counsellorDiscount.toFixed(1));
                    $('#counsellorDiscountAmount').text(formatIndianCurrency(counsellorDiscountAmount));
                    $('#counsellorDiscountRow').show();
                } else {
                    $('#counsellorDiscountRow').hide();
                }

                if (totalDiscountPercent > 0) {
                    $('#totalDiscountPercent').text(totalDiscountPercent.toFixed(1));
                    $('#totalDiscountAmount').text(formatIndianCurrency(totalDiscountAmount));
                    $('#totalDiscountRow').show();
                    $('#finalTokenAmount').text(formatIndianCurrency(payableAmount));
                    $('#finalTokenRow').show();
                    $('#summaryDiscountRow').show();
                    $('#summaryDiscountAmount').text(formatIndianCurrency(totalDiscountAmount));
                    $('#savingsText').show();
                    $('#totalSavings').text(formatIndianCurrency(totalDiscountAmount));
                } else {
                    $('#totalDiscountRow').hide();
                    $('#finalTokenRow').hide();
                    $('#summaryDiscountRow').hide();
                    $('#savingsText').hide();
                }

                $('#payableAmount').text('₹' + formatIndianCurrency(payableAmount));
                $('#payableAmountDisplay').text(formatIndianCurrency(payableAmount));

                if (autoScholarship > 0 || counsellorDiscount > 0) {
                    var details = '<ul class="mb-0">';
                    if (autoScholarship > 0) {
                        var selectedOption = $('#scholarship_type_id option:selected');
                        var scholarshipTypeName = '';
                        if (selectedOption.val()) {
                            scholarshipTypeName = selectedOption.text().split(' - ')[0];
                        }
                        details += '<li><strong>' + scholarshipTypeName + ':</strong> ' + autoScholarshipDetails + ' = ₹' + formatIndianCurrency(autoDiscountAmount) + '</li>';
                    }
                    if (counsellorDiscount > 0) {
                        details += '<li><strong>Counsellor Discretionary Discount:</strong> ' + counsellorDiscount.toFixed(1) + '% = ₹' + formatIndianCurrency(counsellorDiscountAmount) + '</li>';
                    }
                    details += '<li class="text-success"><strong>Total Savings on Tuition Fee:</strong> ₹' + formatIndianCurrency(totalDiscountAmount) + '</li>';
                    details += '<li class="text-primary"><strong>Discounted Tuition Fee:</strong> ₹' + formatIndianCurrency(discountedTuitionBase) + '</li>';
                    details += '<li class="text-info"><strong>Total Balance After Token (with scholarship):</strong> ₹' + formatIndianCurrency(totalBalance) + '</li>';
                    details += '</ul>';
                    $('#scholarshipDescription').html(details);
                    $('#scholarshipDetails').show();
                } else {
                    $('#scholarshipDetails').hide();
                }
            }

            $('#counsellor_discount').on('input change', function () {
                updateAllCalculations();
            });

            $('#payment_mode').change(function () {
                var mode = $(this).val();
                if (mode === 'online') {
                    $(this).removeClass('border-warning').addClass('border-success');
                } else {
                    $(this).removeClass('border-success').addClass('border-warning');
                }
            });

            $('#admissionConfirmForm').submit(function (e) {
                var paymentMode = $('#payment_mode').val();
                var counsellorDiscount = parseFloat($('#counsellor_discount').val()) || 0;
                var totalDiscountPercent = autoScholarship + counsellorDiscount;

                // Note: tuitionPart2WithGst might be undefined if fee_config is null, but this code only runs if fee_config exists
                var originalTuitionPart2 = typeof tuitionPart2WithGst !== 'undefined' ? tuitionPart2WithGst : 0;
                var totalDiscountAmount = originalTuitionPart2 * (totalDiscountPercent / 100);
                var payableAmount = originalTokenFee;

                if (paymentMode === 'online') {
                    const submitBtn = $(this).find('button[type="submit"]');
                    submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing Online Payment...');
                    return true;
                } else {
                    e.preventDefault();
                    var scholarshipTypeName = '';
                    if (autoScholarship > 0) {
                        var selectedOption = $('#scholarship_type_id option:selected');
                        if (selectedOption.val()) {
                            scholarshipTypeName = selectedOption.text().split(' - ')[0];
                        }
                    }

                    if (typeof showConfirm === 'function') {
                        var confirmHtml = '<div class="text-start">' +
                            '<p><strong>Student:</strong> <?php echo addslashes(($student['surname'] ?? '') . ' ' . ($student['student_name'] ?? '')); ?></p>' +
                            '<p><strong>Token Fee (Fixed - To be paid now):</strong> ₹' + formatIndianCurrency(originalTokenFee) + '</p>' +
                            (autoScholarship > 0 ? '<p><strong>Scholarship Type:</strong> ' + scholarshipTypeName + '</p><p><strong>Scholarship on Tuition Part 2:</strong> ' + autoScholarship.toFixed(1) + '% (₹' + formatIndianCurrency(autoDiscountAmount) + ')</p>' : '') +
                            (counsellorDiscount > 0 ? '<p><strong>Counsellor Discount:</strong> ' + counsellorDiscount.toFixed(1) + '% (₹' + formatIndianCurrency(counsellorDiscountAmount) + ')</p>' : '') +
                            (totalDiscountPercent > 0 ? '<p class="text-success"><strong>Total Discount:</strong> ' + totalDiscountPercent.toFixed(1) + '% (₹' + formatIndianCurrency(totalDiscountAmount) + ')</p>' : '') +
                            '<hr>' +
                            '<h5 class="text-primary"><strong>Amount to Collect Now: ₹' + formatIndianCurrency(payableAmount) + '</strong></h5>' +
                            '<p class="text-warning small"><i class="fas fa-exclamation-triangle"></i> Please ensure payment has been received before confirming.</p>' +
                            '</div>';

                        showConfirm({
                            title: 'Confirm Offline Payment',
                            message: confirmHtml,
                            confirmText: 'Yes, Confirm & Submit',
                            confirmButtonClass: 'btn-success',
                            onConfirm: function () {
                                const submitBtn = $('#admissionConfirmForm').find('button[type="submit"]');
                                submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Confirming Admission...');
                                $('#admissionConfirmForm')[0].submit();
                            }
                        });
                    } else {
                        if (confirm("Are you sure you want to confirm this offline payment and admission?")) {
                            $(this).find('button[type="submit"]').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Confirming Admission...');
                            this.submit();
                        }
                    }
                    return false;
                }
            });

            $('#payment_mode').trigger('change');
            updateAllCalculations();

            // Handle hostel/transport toggle changes
            $('input[name="hostel_toggle"]').change(function () {
                $('#hostel_required').val($(this).val());
                updateHostelFeeDisplay();
            });

            $('input[name="transport_toggle"]').change(function () {
                $('#transport_required').val($(this).val());
                updateTransportFeeDisplay();
            });

            // Function to update hostel fee display
            function updateHostelFeeDisplay() {
                var hostelRequired = $('input[name="hostel_toggle"]:checked').val();

                if (hostelRequired === 'Yes' && hostelFeeConfig) {
                    var hostelAmount = studentGender === 'male' ? hostelFeeConfig.boys_hostel_fee : hostelFeeConfig.girls_hostel_fee;
                    if (hostelFeeConfig.gst_applicable && hostelFeeConfig.gst_rate > 0) {
                        var gst = (hostelAmount * hostelFeeConfig.gst_rate) / 100;
                        hostelAmount += gst;
                    }
                    currentHostelFee = hostelAmount;

                    if ($('#hostel_fee_row').length) {
                        $('#hostel_fee_row .fee-value').text('₹' + formatIndianCurrency(hostelAmount));
                        $('#hostel_fee_row').show();
                    } else {
                        var rowHtml = '<div id="hostel_fee_row" class="d-flex justify-content-between py-2 border-bottom border-dashed">' +
                            '<span class="text-muted">Hostel Fee</span>' +
                            '<span class="fw-bold fee-value">₹' + formatIndianCurrency(hostelAmount) + '</span>' +
                            '</div>';
                        $('#feeBreakdown').append(rowHtml);
                    }
                } else {
                    currentHostelFee = 0;
                    $('#hostel_fee_row').hide();
                }
                updateTotalBalance();
            }

            // Function to update transport fee display
            function updateTransportFeeDisplay() {
                var transportRequired = $('input[name="transport_toggle"]:checked').val();

                if (transportRequired === 'Yes' && transportFeeConfig && transportFeeConfig.amount) {
                    var transportAmount = parseFloat(transportFeeConfig.amount);
                    if (transportFeeConfig.gst_rate && transportFeeConfig.gst_rate > 0) {
                        var gst = (transportAmount * parseFloat(transportFeeConfig.gst_rate)) / 100;
                        transportAmount += gst;
                    }
                    currentTransportFee = transportAmount;

                    if ($('#transport_fee_row').length) {
                        $('#transport_fee_row .fee-value').text('₹' + formatIndianCurrency(transportAmount));
                        $('#transport_fee_row').show();
                    } else {
                        var rowHtml = '<div id="transport_fee_row" class="d-flex justify-content-between py-2 border-bottom border-dashed">' +
                            '<span class="text-muted">Transport Fee</span>' +
                            '<span class="fw-bold fee-value">₹' + formatIndianCurrency(transportAmount) + '</span>' +
                            '</div>';
                        $('#feeBreakdown').append(rowHtml);
                    }
                } else {
                    currentTransportFee = 0;
                    $('#transport_fee_row').hide();
                }
                updateTotalBalance();
            }

            // Function to update total balance
            function updateTotalBalance() {
                var totalBalance = parseFloat(tuitionPart2WithGst) + parseFloat(schoolFee) + parseFloat(trustFacilitiesFee) + parseFloat(currentHostelFee) + parseFloat(currentTransportFee);
                $('#totalBalanceRemaining').text('₹' + formatIndianCurrency(totalBalance));
            }

            // Initialize displays
            updateHostelFeeDisplay();
            updateTransportFeeDisplay();
        });
    </script>