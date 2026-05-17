<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once __DIR__ . '/../../common/api_client.php';
require_once PORTAL_GLOBALVARIABLE;

// Check permissions
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE)) {
    header('Location: students.php?view=all');
    exit;
}

$student_id = isset($_POST['id']) ? (int) $_POST['id'] : (isset($_GET['id']) ? (int) $_GET['id'] : 0);

if (!$student_id) {
    set_flash_message('error', 'Invalid Student ID');
    header('Location: students.php?view=all');
    exit;
}

// Fetch student details directly from database (More reliable than search API)
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;

try {
    $op = new Operation();
    // Using readWithJoin similar to details.php but optimized for this view
    $student = $op->readWithJoin(
        'tbl_gm_std_registration s',
        [
            's.*',
            'b.board_name',
            'm.medium_name',
            'g.group_name',
            'c.course_name',
            'sch.school_name'
        ],
        [
            ['type' => 'LEFT', 'table' => 'tbl_boards b', 'on' => 's.board_id = b.id'],
            ['type' => 'LEFT', 'table' => 'tbl_medium m', 'on' => 's.medium_id = m.id'],
            ['type' => 'LEFT', 'table' => 'tbl_group g', 'on' => 's.group_id = g.id'],
            ['type' => 'LEFT', 'table' => 'tbl_courses c', 'on' => 's.course_id = c.id'],
            ['type' => 'LEFT', 'table' => 'tbl_schools sch', 'on' => 's.school_id = sch.id']
        ],
        ['s.id' => $student_id]
    );

    if (!$student) {
        set_flash_message('error', 'Student not found');
        header('Location: students.php?view=all');
        exit;
    }

    // Construct full name if not present
    if (!isset($student['full_name'])) {
        $student['full_name'] = trim(($student['surname'] ?? '') . ' ' . ($student['student_name'] ?? '') . ' ' . ($student['fathers_name'] ?? ''));
    }

    // Fetch Scholarship Types and Rules
    $scholarship_types = $op->select('tbl_scholarship_types', ['*'], ['is_active' => 1], 'type_name ASC');
    $scholarship_rules = $op->select('tbl_scholarship_rules', ['*'], ['is_active' => 1]);

} catch (Exception $e) {
    set_flash_message('error', 'Database error: ' . $e->getMessage());
    header('Location: students.php?view=all');
    exit;
}

// Check if already enrolled
if (!empty($student['enrollment_id'])) {
    set_flash_message('warning', 'Student is already enrolled');
    header('Location: students.php?view=all');
    exit;
}

// Generate Transaction ID
require_once __DIR__ . '/../../../counselling-backend/common/transaction_helper.php';
$generated_transaction_id = function_exists('generateUniqueTransactionID') ? generateUniqueTransactionID('GMI') : 'TXN' . time();

// --- SCHOLARSHIP CALCULATION LOGIC ---
$marks_found = !empty($student['gmsat_marks']) || !empty($student['board_percentage']);
$gmsat_marks = floatval($student['gmsat_marks'] ?? 0);
$board_percentage = floatval($student['board_percentage'] ?? 0);

$course_id = $student['course_id'] ?? 0;
$group_id = $student['group_id'] ?? 0;

$eligible_scholarships = [];
$best_scholarship = 0;
$best_scholarship_type = '';
$best_scholarship_details = '';
$best_scholarship_rule_id = null;

if (!empty($scholarship_rules)) {
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
}
$auto_scholarship = $best_scholarship;
$auto_scholarship_details = $best_scholarship_details;
// --- END SCHOLARSHIP CALCULATION ---

$page_title = "₹0";
include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>



<div class="container-fluid">
    <form id="moveStudentForm">
        <input type="hidden" name="student_id" id="student_id" value="<?php echo $student['id']; ?>">

        <div class="row">
            <div class="col-12">
                <!-- Student Details (Full Width, 2-Column Content) -->
                <div class="card mb-3">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0"><i class="fas fa-user-graduate"></i> Student Details</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <!-- Col 1: Personal Info -->
                            <div class="col-md-6 border-end">
                                <h6 class="text-primary border-bottom pb-2 mb-3">Personal Information</h6>
                                <table class="table table-borderless table-sm">
                                    <tr>
                                        <td class="text-muted" width="35%">Full Name:</td>
                                        <td class="fw-bold">
                                            <?php echo htmlspecialchars($student['full_name'] ?? ''); ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Mobile:</td>
                                        <td>
                                            <?php if (!empty($student['mob'])): ?>
                                                <?php echo htmlspecialchars($student['mob'] ?? ''); ?>
                                            <?php else: ?>
                                                <input type="text" name="update_mob"
                                                    class="form-control form-control-sm border-warning"
                                                    placeholder="Enter Mobile *" required>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Email:</td>
                                        <td>
                                            <?php if (!empty($student['email'])): ?>
                                                <?php echo htmlspecialchars($student['email'] ?? ''); ?>
                                            <?php else: ?>
                                                <input type="email" name="update_email"
                                                    class="form-control form-control-sm border-warning"
                                                    placeholder="Enter Email">
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Aadhaar:</td>
                                        <td>
                                            <?php if (!empty($student['aadhaar'])): ?>
                                                <?php echo htmlspecialchars($student['aadhaar'] ?? ''); ?>
                                            <?php else: ?>
                                                <input type="text" name="update_aadhaar"
                                                    class="form-control form-control-sm border-warning"
                                                    placeholder="Enter Aadhaar">
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Date of Birth:</td>
                                        <td>
                                            <?php if (!empty($student['dob'])): ?>
                                                <?php echo date('d-M-Y', strtotime($student['dob'])); ?>
                                            <?php else: ?>
                                                <input type="date" name="update_dob"
                                                    class="form-control form-control-sm border-warning" required>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Gender:</td>
                                        <td>
                                            <?php if (!empty($student['gender'])): ?>
                                                <?php echo htmlspecialchars($student['gender'] ?? ''); ?>
                                            <?php else: ?>
                                                <select name="update_gender"
                                                    class="form-select form-select-sm border-warning" required>
                                                    <option value="">Select Gender</option>
                                                    <option value="Male">Male</option>
                                                    <option value="Female">Female</option>
                                                </select>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Address:</td>
                                        <td>
                                            <?php if (!empty($student['addr'])): ?>
                                                <small><?php echo htmlspecialchars($student['addr'] ?? ''); ?></small>
                                            <?php else: ?>
                                                <textarea name="update_addr"
                                                    class="form-control form-control-sm border-warning" rows="2"
                                                    placeholder="Enter Address" required></textarea>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <!-- Col 2: Academic Info -->
                            <div class="col-md-6 padding-left-md">
                                <h6 class="text-primary border-bottom pb-2 mb-3">Academic Information</h6>
                                <table class="table table-borderless table-sm">
                                    <tr>
                                        <td class="text-muted" width="35%">Course:</td>
                                        <td><span
                                                class="badge bg-info"><?php echo htmlspecialchars($student['course_name'] ?? ''); ?></span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class=" text-muted">Group:</td>
                                        <td><?php echo htmlspecialchars($student['group_name'] ?? ''); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Medium:</td>
                                        <td><?php echo htmlspecialchars($student['medium_name'] ?? ''); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Board:</td>
                                        <td><?php echo htmlspecialchars($student['board_name'] ?? ''); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">School:</td>
                                        <td><?php echo htmlspecialchars($student['school_name'] ?? ''); ?></td>
                                    </tr>
                                </table>

                                <!-- Detailed Info Section -->
                                <!-- Scholarship Info Section -->
                                <div id="scholarship_info_section" style="display: none;" class="mt-3">
                                    <div class="alert alert-success mb-0">
                                        <h6 class="alert-heading mb-2"><i class="fas fa-award me-2"></i>Scholarship
                                            / Discount Information</h6>
                                        <div class="row text-center">
                                            <div class="col-6 mb-2">
                                                <small class="text-muted d-block">Scholarship Amount</small>
                                                <span class="fw-bold" id="disp_scholarship_amount">₹0</span>
                                            </div>
                                            <div class="col-6 mb-2">
                                                <small class="text-muted d-block">Add. Scholarship</small>
                                                <span class="fw-bold" id="disp_additional_scholarship">₹0</span>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted d-block">Post-Admission</small>
                                                <span class="fw-bold" id="disp_post_admission_discount">₹0</span>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted d-block">Total Discount</small>
                                                <span class="text-success fw-bold"
                                                    id="disp_total_discount">₹0</span>
                                            </div>
                                        </div>
                                        <div class="mt-2 text-muted small fst-italic" id="disp_discount_remarks">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Scholarship Selection Section -->
                <div class="card mb-3">
                    <div class="card-header bg-success text-white">
                        <h5 class="card-title mb-0"><i class="fas fa-graduation-cap"></i> Scholarship Options</h5>
                    </div>
                    <div class="card-body">
                        <input type="hidden" id="auto_scholarship_percent" value="<?php echo $auto_scholarship; ?>">
                        <input type="hidden" name="scholarship_rule_id" id="scholarship_rule_id"
                            value="<?php echo $best_scholarship_rule_id; ?>">
                        <input type="hidden" name="scholarship_amount" id="scholarship_amount" value="0">
                        <input type="hidden" name="scholarship_percentage" id="scholarship_percentage" value="0">
                        <!-- Manual Marks Inputs (Hidden by default or if needed) -->
                        <input type="hidden" name="manual_gmsat_marks" id="manual_gmsat_marks"
                            value="<?php echo $gmsat_marks; ?>">
                        <input type="hidden" name="manual_board_percentage" id="manual_board_percentage"
                            value="<?php echo $board_percentage; ?>">

                        <?php if (!$marks_found): ?>
                            <div class="alert alert-warning">
                                <small><i class="fas fa-exclamation-triangle"></i> <strong>Marks Not Found.</strong>
                                    Please enter marks manually in profile update section if available, or select
                                    scholarship directly below.</small>
                            </div>
                        <?php elseif ($auto_scholarship > 0): ?>
                            <div class="alert alert-success">
                                <small>
                                    <i class="fas fa-trophy"></i> <strong>Best Match:</strong>
                                    <?php echo $auto_scholarship_details; ?>
                                </small>
                            </div>
                        <?php endif; ?>

                        <div class="form-group">
                            <label class="form-label">Select Scholarship Type</label>
                            <select name="scholarship_type_id" id="scholarship_type_id" class="form-select">
                                <option value="">-- No Scholarship --</option>
                                <?php foreach ($scholarship_types as $type): ?>
                                    <?php
                                    $is_eligible = isset($eligible_scholarships[$type['id']]);
                                    $discount_text = $is_eligible ? $eligible_scholarships[$type['id']]['discount'] . '% Discount' : 'Not Eligible (0%)';
                                    $rule_id = $is_eligible ? $eligible_scholarships[$type['id']]['rule_id'] : 0;
                                    $selected = ($best_scholarship_type == $type['id']) ? 'selected' : '';
                                    ?>
                                    <option value="<?php echo $type['id']; ?>"
                                        data-discount="<?php echo $is_eligible ? $eligible_scholarships[$type['id']]['discount'] : 0; ?>"
                                        data-rule-id="<?php echo $rule_id; ?>" <?php echo $selected; ?>>
                                        <?php echo htmlspecialchars($type['type_name'] ?? ''); ?> -
                                        <?php echo $discount_text; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted fst-italic mt-1 d-block">Applies to Tuition Fee Part 2</small>

                            <!-- Manual Marks Entry Toggle -->
                            <div class="mt-2">
                                <button type="button" class="btn btn-sm btn-outline-primary" id="showManualMarksEntry"
                                    style="<?php echo $marks_found ? 'display:none;' : ''; ?>">
                                    <i class="fas fa-edit"></i> Enter Marks Manually
                                </button>
                                <button type="button" class="btn btn-sm btn-link text-decoration-none"
                                    id="changeMarksBtn" style="<?php echo !$marks_found ? 'display:none;' : ''; ?>">
                                    <i class="fas fa-pen"></i> Change Marks
                                </button>
                            </div>

                            <!-- Manual Marks Form -->
                            <div id="manualMarksSection" class="card bg-light mt-3" style="display: none;">
                                <div class="card-header bg-secondary text-white py-2">
                                    <h6 class="card-title mb-0 small"><i class="fas fa-calculator"></i> Check
                                        Eligibility</h6>
                                </div>
                                <div class="card-body py-2">
                                    <div id="selectScholarshipFirst" class="alert alert-info py-1 small mb-2">
                                        <i class="fas fa-arrow-up"></i> Select a scholarship type above to see
                                        requirements.
                                    </div>

                                    <div id="manualMarksForm" style="display:none;">
                                        <div class="row g-2">
                                            <div class="col-md-6" id="gmsatMarksField" style="display:none;">
                                                <label class="form-label small mb-1">GMSAT Marks</label>
                                                <input type="number" id="input_gmsat_marks"
                                                    class="form-control form-control-sm" step="0.01"
                                                    placeholder="Enter Marks">
                                                <small class="text-muted" style="font-size: 0.7rem;"
                                                    id="gmsatRangeInfo"></small>
                                            </div>
                                            <div class="col-md-6" id="boardPercentageField" style="display:none;">
                                                <label class="form-label small mb-1">Board %</label>
                                                <input type="number" id="input_board_percentage"
                                                    class="form-control form-control-sm" step="0.01" max="100"
                                                    placeholder="Enter %">
                                                <small class="text-muted" style="font-size: 0.7rem;"
                                                    id="boardRangeInfo"></small>
                                            </div>
                                            <div class="col-12 mt-2">
                                                <button type="button" id="checkEligibilityBtn"
                                                    class="btn btn-sm btn-primary w-100">
                                                    Check Eligibility
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Hostel & Transport Requirements Section -->
                <div class="card mb-3">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="card-title mb-0"><i class="fas fa-building"></i> Hostel & Transport Requirements
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Hostel Required</label>
                                    <div class="btn-group w-100" role="group">
                                        <input type="radio" class="btn-check" name="update_hostel_required"
                                            id="hostel_yes" value="Yes" <?php echo (strtolower($student['hostel_required'] ?? 'no') === 'yes') ? 'checked' : ''; ?>>
                                        <label class="btn btn-outline-success" for="hostel_yes"><i
                                                class="fas fa-check"></i> Yes</label>

                                        <input type="radio" class="btn-check" name="update_hostel_required"
                                            id="hostel_no" value="No" <?php echo (strtolower($student['hostel_required'] ?? 'no') !== 'yes') ? 'checked' : ''; ?>>
                                        <label class="btn btn-outline-secondary" for="hostel_no"><i
                                                class="fas fa-times"></i> No</label>
                                    </div>
                                    <small class="text-muted">Hostel is only available for Gyanmanjari
                                        schools</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Transport Required</label>
                                    <div class="btn-group w-100" role="group">
                                        <input type="radio" class="btn-check" name="update_transport_required"
                                            id="transport_yes" value="Yes" <?php echo (strtolower($student['transport_required'] ?? 'no') === 'yes') ? 'checked' : ''; ?>>
                                        <label class="btn btn-outline-success" for="transport_yes"><i
                                                class="fas fa-check"></i> Yes</label>

                                        <input type="radio" class="btn-check" name="update_transport_required"
                                            id="transport_no" value="No" <?php echo (strtolower($student['transport_required'] ?? 'no') !== 'yes') ? 'checked' : ''; ?>>
                                        <label class="btn btn-outline-secondary" for="transport_no"><i
                                                class="fas fa-times"></i> No</label>
                                    </div>
                                    <small class="text-muted">Transport facility if applicable</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Fee Collection Section -->
                <div class="card mb-3">
                    <div class="card-header bg-success text-white">
                        <h5 class="card-title mb-0"><i class="fas fa-file-invoice-dollar"></i> Fee Collection</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> Select fees to collect during enrollment. You can
                            select
                            "Token Fee" to confirm admission.
                        </div>

                        <div class="row" id="payment_type_checkboxes">
                            <div class="col-md-6 mb-2">
                                <div class="form-check custom-checkbox-card">
                                    <input class="form-check-input payment-type-checkbox" type="checkbox"
                                        name="payment_types[]" value="school_fee" id="check_school_fee">
                                    <label class="form-check-label" for="check_school_fee">
                                        <span class="fee-type-name">School Fee</span>
                                        <span class="fee-type-amount text-success" id="amt_school_fee">₹0</span>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-2">
                                <div class="form-check custom-checkbox-card">
                                    <input class="form-check-input payment-type-checkbox" type="checkbox"
                                        name="payment_types[]" value="trust_facilities_fee"
                                        id="check_trust_facilities_fee">
                                    <label class="form-check-label" for="check_trust_facilities_fee">
                                        <span class="fee-type-name">Trust Facilities Fee</span>
                                        <span class="fee-type-amount text-success"
                                            id="amt_trust_facilities_fee">₹0</span>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-2">
                                <div class="form-check custom-checkbox-card">
                                    <input class="form-check-input payment-type-checkbox" type="checkbox"
                                        name="payment_types[]" value="tuition_fee_part1" id="check_tuition_fee_part1">
                                    <label class="form-check-label" for="check_tuition_fee_part1">
                                        <span class="fee-type-name">Token Fee <small class="text-muted">(Tuition
                                                Part
                                                1)</small></span>
                                        <span class="fee-type-amount text-success"
                                            id="amt_tuition_fee_part1">₹0</span>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-2">
                                <div class="form-check custom-checkbox-card">
                                    <input class="form-check-input payment-type-checkbox" type="checkbox"
                                        name="payment_types[]" value="tuition_fee_part2" id="check_tuition_fee_part2">
                                    <label class="form-check-label" for="check_tuition_fee_part2">
                                        <span class="fee-type-name">Tuition Fee Part 2</span>
                                        <span class="fee-type-amount text-success"
                                            id="amt_tuition_fee_part2">₹0</span>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-2">
                                <div class="form-check custom-checkbox-card">
                                    <input class="form-check-input payment-type-checkbox" type="checkbox"
                                        name="payment_types[]" value="hostel_fee" id="check_hostel_fee">
                                    <label class="form-check-label" for="check_hostel_fee">
                                        <span class="fee-type-name">Hostel Fee</span>
                                        <span class="fee-type-amount text-success" id="amt_hostel_fee">₹0</span>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-2">
                                <div class="form-check custom-checkbox-card">
                                    <input class="form-check-input payment-type-checkbox" type="checkbox"
                                        name="payment_types[]" value="transport_fee" id="check_transport_fee">
                                    <label class="form-check-label" for="check_transport_fee">
                                        <span class="fee-type-name">Transport Fee</span>
                                        <span class="fee-type-amount text-success"
                                            id="amt_transport_fee">₹0</span>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-2">
                                <div class="form-check custom-checkbox-card">
                                    <input class="form-check-input payment-type-checkbox" type="checkbox"
                                        name="payment_types[]" value="other" id="check_other">
                                    <label class="form-check-label" for="check_other">
                                        <span class="fee-type-name">Other</span>
                                        <span class="fee-type-amount text-muted" id="amt_other">Custom</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Other Fee Custom Amount (hidden by default) -->
                        <div class="row mt-2" id="other_amount_section" style="display: none;">
                            <div class="col-6">
                                <input type="text" name="other_description" id="other_description"
                                    class="form-control form-control-sm" placeholder="Description">
                            </div>
                            <div class="col-6">
                                <input type="number" name="other_amount" id="other_amount"
                                    class="form-control form-control-sm" placeholder="Amount">
                            </div>
                        </div>

                        <hr>

                        <!-- Payment Details -->
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">Payment Date</label>
                                <input type="date" name="payment_date" class="form-control"
                                    value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Payment Mode</label>
                                <select name="payment_mode" id="payment_mode" class="form-select" required>
                                    <option value="cash">💵 Cash</option>
                                    <option value="online">💳 Online Transfer</option>
                                    <option value="upi">📱 UPI</option>
                                    <option value="cheque">📄 Cheque</option>
                                    <option value="card">💳 Card</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mt-2" id="cheque_details" style="display: none;">
                            <div class="col-6">
                                <input type="text" name="cheque_no" class="form-control form-control-sm"
                                    placeholder="Cheque No">
                            </div>
                            <div class="col-6">
                                <input type="text" name="bank_name" class="form-control form-control-sm"
                                    placeholder="Bank Name">
                            </div>
                        </div>

                        <div class="mb-3 mt-3">
                            <label class="form-label">Transaction ID</label>
                            <input type="text" name="transaction_id" id="transaction_id" class="form-control bg-light"
                                value="<?php echo $generated_transaction_id; ?>" readonly>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
                            <h5 class="mb-0">Total Amount:</h5>
                            <h3 class="text-success mb-0" id="display_total_amount">₹0</h3>
                            <input type="hidden" name="amount" id="amount" value="0">
                        </div>

                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-check-circle"></i> Confirm & Move to Enrolled
                            </button>
                            <a href="students.php?view=all" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<link rel="stylesheet" href="<?php echo PORTAL_URL; ?>assets/css/modules/students/move-to-enrolled.css">

<?php include '../../include/footer.php'; ?>

<script>
    let currentFeeConfig = null;
    const scholarshipRules = <?php echo json_encode($scholarship_rules ?? []); ?>;
    const courseId = <?php echo $course_id ?? 0; ?>;
    const groupId = <?php echo $group_id ?? 0; ?>;
    let marksFound = <?php echo $marks_found ? 'true' : 'false'; ?>;

    const feeLabels = {
        'school_fee': 'School Fee',
        'trust_facilities_fee': 'Trust Facilities Fee',
        'tuition_fee_part1': 'Tuition Fee Part 1',
        'tuition_fee_part2': 'Tuition Fee Part 2',
        'tuition_fee_part2': 'Tuition Fee Part 2',
        'hostel_fee': 'Hostel Fee',
        'transport_fee': 'Transport Fee',
        'other': 'Other'
    };

    $(document).ready(function () {
        // Fetch fee config immediately
        fetchStudentFeeConfig(<?php echo $student_id; ?>);

        // --- Manual Marks & Eligibility Logic ---

        // Toggle Manual Entry
        $('#showManualMarksEntry, #changeMarksBtn').click(function () {
            $('#manualMarksSection').slideDown();
            $(this).hide();
            // If type selected, update form immediately
            const selectedTypeId = $('#scholarship_type_id').val();
            if (selectedTypeId) {
                updateManualMarksForm(selectedTypeId);
            }
        });

        // Update inputs when type changes
        function updateManualMarksForm(typeId) {
            if (!typeId) {
                $('#manualMarksForm').hide();
                $('#selectScholarshipFirst').show().removeClass('alert-warning').addClass('alert-info').html('<i class="fas fa-arrow-up"></i> Select a scholarship type above to see requirements.');
                return;
            }

            const relevantRules = scholarshipRules.filter(function (rule) {
                return rule.scholarship_type_id == typeId && rule.course_id == courseId && rule.group_id == groupId && rule.is_active == 1;
            });

            if (relevantRules.length === 0) {
                $('#manualMarksForm').hide();
                $('#selectScholarshipFirst').show().removeClass('alert-info').addClass('alert-warning').html('<i class="fas fa-exclamation-triangle"></i> No rules found for this scholarship type.');
                return;
            }

            let hasGmsat = false;
            let hasBoard = false;
            let gmsatRanges = [];
            let boardRanges = [];

            relevantRules.forEach(function (rule) {
                if (rule.gmsat_minimum_mark && rule.gmsat_maximum_mark) {
                    hasGmsat = true;
                    gmsatRanges.push(rule.gmsat_minimum_mark + '-' + rule.gmsat_maximum_mark);
                }
                if (rule.board_pr_minimum && rule.board_pr_maximum) {
                    hasBoard = true;
                    boardRanges.push(rule.board_pr_minimum + '-' + rule.board_pr_maximum + '%');
                }
            });

            $('#selectScholarshipFirst').hide();
            $('#manualMarksForm').show();

            if (hasGmsat) {
                $('#gmsatMarksField').show();
                $('#gmsatRangeInfo').text('Ranges: ' + gmsatRanges.join(', '));
            } else {
                $('#gmsatMarksField').hide();
            }

            if (hasBoard) {
                $('#boardPercentageField').show();
                $('#boardRangeInfo').text('Ranges: ' + boardRanges.join(', '));
            } else {
                $('#boardPercentageField').hide();
            }
        }

        // Check Eligibility Button
        $('#checkEligibilityBtn').click(function () {
            const gmsat = parseFloat($('#input_gmsat_marks').val()) || 0;
            const board = parseFloat($('#input_board_percentage').val()) || 0;
            const typeId = $('#scholarship_type_id').val();

            if (!typeId) {
                showToast('error', 'Error', 'Please select a scholarship type first.');
                return;
            }

            // Find best match rule for these marks
            const relevantRules = scholarshipRules.filter(function (rule) {
                return rule.scholarship_type_id == typeId && rule.course_id == courseId && rule.group_id == groupId && rule.is_active == 1;
            });

            let bestMatch = null;
            let maxDiscount = -1;

            relevantRules.forEach(function (rule) {
                let eligible = false;
                // GMSAT Check
                if (rule.gmsat_minimum_mark && rule.gmsat_maximum_mark) {
                    if (gmsat >= parseFloat(rule.gmsat_minimum_mark) && gmsat <= parseFloat(rule.gmsat_maximum_mark)) {
                        eligible = true;
                    }
                }
                // Board Check
                if (rule.board_pr_minimum && rule.board_pr_maximum) {
                    if (board >= parseFloat(rule.board_pr_minimum) && board <= parseFloat(rule.board_pr_maximum)) {
                        eligible = true;
                    }
                }

                if (eligible) {
                    const discount = parseFloat(rule.scholarship_discount_amount);
                    if (discount > maxDiscount) {
                        maxDiscount = discount;
                        bestMatch = rule;
                    }
                }
            });

            // Update hidden fields
            $('#manual_gmsat_marks').val(gmsat);
            $('#manual_board_percentage').val(board);

            if (bestMatch) {
                showToast('success', 'Eligible!', 'You are eligible for ' + maxDiscount + '% discount.');

                // Artificial event to update dropdown state (visual only, real logic is via hidden inputs)
                // Actually we can just update the dropdown option to look "Eligible" for this user session? 
                // Or easier: Just trigger the change logic with the new valid rule
                // We need to update the OPTION element's data attributes so the change handler picks it up

                const $opt = $('#scholarship_type_id option[value="' + typeId + '"]');
                $opt.data('discount', maxDiscount);
                $opt.data('rule-id', bestMatch.id);
                $opt.text($opt.text().split(' - ')[0] + ' - ' + maxDiscount + '% Discount (Verified)');

                // Trigger change to update fees
                $('#scholarship_type_id').trigger('change');

            } else {
                showToast('error', 'Not Eligible', 'Based on the entered marks, you are not eligible for this scholarship.');
                // Reset dropdown data for this option
                const $opt = $('#scholarship_type_id option[value="' + typeId + '"]');
                $opt.data('discount', 0);
                $opt.data('rule-id', '');
                $opt.text($opt.text().split(' - ')[0] + ' - Not Eligible (0%)');
                $('#scholarship_type_id').trigger('change');
            }
        });

        // Scholarship Dropdown Interaction
        $('#scholarship_type_id').on('change', function () {
            const selectedOption = $(this).find('option:selected');
            const discountPercent = parseFloat(selectedOption.data('discount')) || 0;
            const ruleId = selectedOption.data('rule-id') || '';
            const typeId = $(this).val();

            // Update hidden fields
            $('#scholarship_percentage').val(discountPercent);
            $('#scholarship_rule_id').val(ruleId);

            // Update Manual Form UI
            if ($('#manualMarksSection').is(':visible')) {
                updateManualMarksForm(typeId);
            }

            // Re-render fees with new discount
            if (currentFeeConfig) {
                // Fake a scholarship info object for local calculation
                if (!currentFeeConfig.scholarship_info) currentFeeConfig.scholarship_info = {};
                currentFeeConfig.scholarship_info.scholarship_percentage = discountPercent;
                // Reset amounts to force recalculation
                updateFeeValues(currentFeeConfig, currentFeeConfig.paid_fees || {});
            }
            calculateTotal();
        });

        // Hostel Toggle
        $('input[name="update_hostel_required"]').change(function () {
            const val = $(this).val(); // Yes or No
            if (currentFeeConfig) {
                const potentialFee = parseFloat(currentFeeConfig.potential_hostel_fee) || 0;
                // Update config
                currentFeeConfig.hostel_fee = (val === 'Yes') ? potentialFee : 0;
                // Update UI values logic
                updateFeeValues(currentFeeConfig, currentFeeConfig.paid_fees || {});

                // Auto-check logic
                const $chk = $('#check_hostel_fee');
                if (val === 'Yes' && potentialFee > 0) {
                    $chk.prop('checked', true).trigger('change');
                } else if (val === 'No') {
                    $chk.prop('checked', false).trigger('change');
                }
            }
        });

        // Transport Toggle
        $('input[name="update_transport_required"]').change(function () {
            const val = $(this).val(); // Yes or No
            if (currentFeeConfig) {
                const potentialFee = parseFloat(currentFeeConfig.potential_transport_fee) || 0;
                // Update config
                currentFeeConfig.transport_fee = (val === 'Yes') ? potentialFee : 0;
                // Update UI
                updateFeeValues(currentFeeConfig, currentFeeConfig.paid_fees || {});

                // Auto-check logic
                const $chk = $('#check_transport_fee');
                if (val === 'Yes' && potentialFee > 0) {
                    $chk.prop('checked', true).trigger('change');
                } else if (val === 'No') {
                    $chk.prop('checked', false).trigger('change');
                }
            }
        });

        // Checkbox Interaction
        $('.payment-type-checkbox').on('change', function () {
            if ($(this).is(':checked')) {
                $(this).closest('.custom-checkbox-card').addClass('selected');
                if ($(this).val() === 'other') $('#other_amount_section').show();
            } else {
                $(this).closest('.custom-checkbox-card').removeClass('selected');
                if ($(this).val() === 'other') $('#other_amount_section').hide();
            }
            calculateTotal();
        });

        $('#other_amount').on('input', calculateTotal);

        $('#payment_mode').change(function () {
            if ($(this).val() === 'cheque') $('#cheque_details').show();
            else $('#cheque_details').hide();
        });

        $('#moveStudentForm').on('submit', function (e) {
            e.preventDefault();
            const formData = $(this).serialize();

            showConfirm({
                title: 'Move to Enrolled?',
                message: 'Are you sure you want to move this student to enrolled list?' + ($('#amount').val() > 0 ? ' Payment of ₹' + $('#amount').val() + ' will be recorded.' : ''),
                confirmText: 'Yes, Move',
                confirmButtonClass: 'btn-primary',
                onConfirm: function () {
                    processMove(formData);
                }
            });
        });
    });

    function fetchStudentFeeConfig(studentId) {
        $.api.get('common/get-student-fee-config', { student_id: studentId })
            .then(function (response) {
                if (response.success && response.fee_config) {
                    currentFeeConfig = response.fee_config;
                    currentFeeConfig.paid_fees = response.paid_fees; // Store for re-use

                    // Initial Scholarship Setup from auto-calculation if any
                    // We rely on the PHP auto-calculation for initial state, but we need to respect it in JS
                    const initialPercent = parseFloat($('#scholarship_type_id').find('option:selected').data('discount')) || 0;
                    if (!currentFeeConfig.scholarship_info) currentFeeConfig.scholarship_info = {};
                    currentFeeConfig.scholarship_info.scholarship_percentage = initialPercent;

                    // Trigger update
                    updateFeeValues(response.fee_config, response.paid_fees || {});

                    // Trigger change to ensure hidden fields are set
                    $('#scholarship_type_id').trigger('change');

                } else {
                    showToast('warning', 'Warning', 'Fee configuration not found.');
                }
            })
            .catch(function (error) {
                console.error(error);
                console.error(error);
                showToast('error', 'Error', 'Failed to load fee configuration.');
            });
    }

    function displayScholarshipInfo(info) {
        // Kept for backward compat if needed, but we used PHP for initial display
        $('#scholarship_info_section').show();
    }

    function formatAmount(amount) {
        return '₹' + Math.round(parseFloat(amount)).toLocaleString('en-IN');
    }

    function updateFeeValues(config, paidFees) {
        // Calculate with GST
        const t1 = Math.round((parseFloat(config.tuition_fee_part1) || 0) * 1.18);
        const t2 = Math.round((parseFloat(config.tuition_fee_part2) || 0) * 1.18);

        // Calculate Tuition Part 2 Final after Scholarship
        // Scholarship is usually on Base Amount, but here logic says "Discount"
        // In admission-confirm, scholarship is % of Tuition Part 2.
        // Logic: Discount Amount = (Tuition Part 2 Base) * (Percent / 100)
        // Then apply GST to the remaining? Or is it flat off?
        // Usually: (Base - Discount) + GST. 
        // Let's assume standard logic: 
        // 1. Calculate Discount Amount
        const percent = parseFloat(config.scholarship_info.scholarship_percentage) || 0;
        const baseT2 = parseFloat(config.tuition_fee_part2) || 0;
        const discountAmount = Math.round((baseT2 * percent) / 100);

        // Update hidden field for saving
        $('#scholarship_amount').val(discountAmount);

        // 2. Calculate Final T2
        // Assuming GST applies to the discounted amount
        // If GST is 18%:
        const finalBaseT2 = Math.max(0, baseT2 - discountAmount);
        const finalT2WithGST = Math.round(finalBaseT2 * 1.18);

        // Update global variable or data attribute for T2
        // We need to update the checkbox data-amount

        const fees = [
            { id: 'school_fee', val: parseFloat(config.school_fee) || 0 },
            { id: 'trust_facilities_fee', val: parseFloat(config.trust_facilities_fee) || 0 },
            { id: 'tuition_fee_part1', val: t1 },
            { id: 'tuition_fee_part2', val: finalT2WithGST, original: t2, is_discounted: percent > 0 },
            { id: 'hostel_fee', val: parseFloat(config.hostel_fee) || 0 },
            { id: 'transport_fee', val: parseFloat(config.transport_fee) || 0 }
        ];

        fees.forEach(fee => {
            const $chk = $('#check_' + fee.id);
            const $amt = $('#amt_' + fee.id);

            // Update the data-amount only if it wasn't manually checked/unchecked logic (actually we just update it always)
            // But wait, if already paid, we disable it.
            $chk.data('amount', fee.val);

            // If currently checked, this will affect total calculation next time calculateTotal is called.

            // Check if paid
            if (paidFees[fee.id] && paidFees[fee.id] > 0) {
                $chk.prop('disabled', true).prop('checked', false);
                $chk.closest('.custom-checkbox-card').addClass('disabled');
                $amt.html('<span class="badge bg-success">PAID</span>');
            } else {
                if (fee.id === 'tuition_fee_part2' && fee.is_discounted) {
                    $amt.html(formatAmount(fee.val) + ' <small class="text-muted text-decoration-line-through">' + formatAmount(fee.original) + '</small> <span class="badge bg-success">' + percent + '% Off</span>');
                } else if (fee.id === 'tuition_fee_part2') {
                    $amt.text(formatAmount(fee.val));
                } else {
                    $amt.text(formatAmount(fee.val));
                }
            }
        });

        // Force recalculate total based on checked boxes (and new amounts)
        calculateTotal();
    }

    function calculateTotal() {
        let total = 0;
        $('.payment-type-checkbox:checked').each(function () {
            if ($(this).val() === 'other') {
                total += parseFloat($('#other_amount').val()) || 0;
            } else {
                total += parseFloat($(this).data('amount')) || 0;
            }
        });

        total = Math.round(total);
        $('#display_total_amount').html(formatAmount(total));
        $('#amount').val(total);
    }

    function processMove(data) {
        const submitBtn = $('#moveStudentForm button[type="submit"]');
        const originalBtnHtml = submitBtn.html();
        submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');

        $.api.post('students/move_student', data)
            .then(function (response) {
                if (response.success) {
                    showToast('success', 'Success', 'Student moved successfully!');
                    setTimeout(() => {
                        window.location.href = 'students.php?view=all';
                    }, 2000);
                } else {
                    submitBtn.prop('disabled', false).html(originalBtnHtml);
                    showToast('error', 'Error', response.message || 'Operation failed');
                }
            })
            .catch(function (error) {
                submitBtn.prop('disabled', false).html(originalBtnHtml);
                showToast('error', 'Error', error.message || 'An error occurred');
            });
    }
</script>