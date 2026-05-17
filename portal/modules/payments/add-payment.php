<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once __DIR__ . '/../../common/api_client.php';
require_once PORTAL_GLOBALVARIABLE;

// Fetch add payment form data from API
$api = new APIClient();
// Accept both GET and POST requests
$response = $api->get('payments/add', array_merge($_GET, $_POST));

if ($response && isset($response['success']) && $response['success']) {
    $preselected_student = $response['data']['preselected_student'] ?? null;
    $generated_transaction_id = $response['data']['generated_transaction_id'] ?? 'TXN' . time();
} else {
    $preselected_student = null;
    $generated_transaction_id = 'TXN' . time();
}

$page_title = "Record New Payment";
$page_breadcrumb = "Record Payment";
include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>

<!-- Flatpickr CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/material_blue.css">
<style>
    .flatpickr-input[readonly] {
        background-color: #fff !important;
    }

    .flatpickr-calendar {
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1) !important;
        border: none !important;
        border-radius: 12px !important;
    }

    .flatpickr-day.selected {
        background: #2563EB !important;
        border-color: #2563EB !important;
    }
</style>



<div class="container-fluid">
    <form id="paymentForm">
        <div class="row g-4">
            <!-- Left Column: Payment Form -->
            <div class="col-lg-8">
                <!-- Main Payment Card -->
                <div class="card card-payment h-100 mb-0">
                    <div class="card-header bg-gradient-primary">
                        <h3 class="card-title text-white d-flex align-items-center">
                            <i class="fas fa-file-invoice-dollar me-2"></i>
                            Payment Details
                        </h3>
                    </div>
                    <div class="card-body">
                        <!-- Student Selection Section -->
                        <div class="form-section mb-4">
                            <h6 class="form-section-title">
                                <i class="fas fa-user-graduate text-primary"></i>
                                Student Information
                            </h6>
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="form-group">
                                        <label class="form-label">Select Student <span
                                                class="text-danger">*</span></label>
                                        <div class="student-search-wrapper">
                                            <div class="input-group">
                                                <span class="input-group-text bg-light">
                                                    <i class="fas fa-search text-muted"></i>
                                                </span>
                                                <input type="text" id="student_search"
                                                    class="form-control student-search-input"
                                                    placeholder="Search by Student ID, Name, or Mobile Number"
                                                    autocomplete="off">
                                            </div>
                                            <input type="hidden" name="student_id" id="student_id" required>
                                            <div id="student_search_results"></div>
                                        </div>
                                        <small class="form-text text-muted">
                                            <i class="fas fa-info-circle"></i> Type at least 2 characters to search
                                        </small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="form-label">Payment Date <span
                                                class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light">
                                                <i class="fas fa-calendar-alt text-muted"></i>
                                            </span>
                                            <input type="date" name="payment_date" class="form-control" value="<?php
                                            echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>" required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <div id="student_details"></div>
                                    <div class="card border-0 shadow-sm mt-3" id="without_gst_container"
                                        style="display: none;">
                                        <div class="card-body py-2 bg-light">
                                            <div class="form-check form-switch m-0">
                                                <input class="form-check-input" type="checkbox" id="is_without_gst">
                                                <label class="form-check-label fw-bold text-danger"
                                                    for="is_without_gst">
                                                    <i class="fas fa-exclamation-triangle me-1"></i> Direct Trust
                                                    Collection
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Scholarship Info Section -->
                                    <div id="scholarship_info_section" style="display: none;" class="mt-3">
                                        <div class="alert alert-success mb-0">
                                            <h6 class="alert-heading mb-2"><i class="fas fa-award me-2"></i>Scholarship
                                                / Discount Information
                                            </h6>
                                            <div class="row">
                                                <div class="col-md-3">
                                                    <small class="text-muted">Scholarship Amount</small>
                                                    <div class="fw-bold" id="disp_scholarship_amount">₹0</div>
                                                </div>
                                                <div class="col-md-3">
                                                    <small class="text-muted">Additional Scholarship</small>
                                                    <div class="fw-bold" id="disp_additional_scholarship">₹0
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <small class="text-muted">Scholarship %</small>
                                                    <div class="fw-bold" id="disp_scholarship_percentage">0%</div>
                                                </div>
                                                <div class="col-md-3">
                                                    <small class="text-muted">Post-Admission Discount</small>
                                                    <div class="fw-bold" id="disp_post_admission_discount">₹0
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row mt-2" id="post_admission_remarks_row"
                                                style="display: none;">
                                                <div class="col-12">
                                                    <small class="text-muted">Discount Remarks: <span
                                                            id="disp_discount_remarks"></span></small>
                                                </div>
                                            </div>
                                            <div class="mt-2 pt-2 border-top">
                                                <strong>Total Scholarship/Discount: <span class="text-success fs-5"
                                                        id="disp_total_discount">₹0</span></strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Type Selection Section -->
                        <div class="form-section mb-4">
                            <h6 class="form-section-title">
                                <i class="fas fa-money-bill-wave text-success"></i>
                                Payment Type Selection
                            </h6>
                            <p class="text-muted small mb-3">
                                <i class="fas fa-info-circle"></i> Select one or more fee types to collect. Amount
                                will be calculated automatically based on fee configuration.
                            </p>
                            <div class="row" id="payment_type_checkboxes">
                                <div class="col-md-4 mb-2">
                                    <div class="form-check custom-checkbox-card">
                                        <input class="form-check-input payment-type-checkbox" type="checkbox"
                                            name="payment_types[]" value="school_fee" id="check_school_fee">
                                        <label class="form-check-label" for="check_school_fee">
                                            <span class="fee-type-name">School Fee</span>
                                            <span class="fee-type-amount text-success" id="amt_school_fee">₹0</span>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-2">
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
                                <div class="col-md-4 mb-2">
                                    <div class="form-check custom-checkbox-card">
                                        <input class="form-check-input payment-type-checkbox" type="checkbox"
                                            name="payment_types[]" value="tuition_fee_part1"
                                            id="check_tuition_fee_part1">
                                        <label class="form-check-label" for="check_tuition_fee_part1">
                                            <span class="fee-type-name">Token Fee <small class="text-muted">(Tuition
                                                    Part 1)</small></span>
                                            <span class="fee-type-amount text-success"
                                                id="amt_tuition_fee_part1">₹0</span>
                                            <small class="text-info d-block"><i class="fas fa-key"></i> Required for
                                                Portal Access</small>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <div class="form-check custom-checkbox-card">
                                        <input class="form-check-input payment-type-checkbox" type="checkbox"
                                            name="payment_types[]" value="tuition_fee_part2"
                                            id="check_tuition_fee_part2">
                                        <label class="form-check-label" for="check_tuition_fee_part2">
                                            <span class="fee-type-name">Tuition Fee Part 2</span>
                                            <span class="fee-type-amount text-success"
                                                id="amt_tuition_fee_part2">₹0</span>
                                            <small class="text-muted d-block gst-label-tuition2">(incl. 18% GST)</small>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <div class="form-check custom-checkbox-card">
                                        <input class="form-check-input payment-type-checkbox" type="checkbox"
                                            name="payment_types[]" value="hostel_fee" id="check_hostel_fee">
                                        <label class="form-check-label" for="check_hostel_fee">
                                            <span class="fee-type-name">Hostel Fee</span>
                                            <span class="fee-type-amount text-success" id="amt_hostel_fee">₹0</span>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-2" id="hostel_cash_fee_col" style="display:none">
                                    <div class="form-check custom-checkbox-card">
                                        <input class="form-check-input payment-type-checkbox" type="checkbox"
                                            name="payment_types[]" value="hostel_cash_fee" id="check_hostel_cash_fee">
                                        <label class="form-check-label" for="check_hostel_cash_fee">
                                            <span class="fee-type-name">Hostel Cash Fee</span>
                                            <span class="fee-type-amount text-success"
                                                id="amt_hostel_cash_fee">₹0</span>
                                            <small class="text-muted d-block">(Cash - No Receipt)</small>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <div class="form-check custom-checkbox-card">
                                        <input class="form-check-input payment-type-checkbox" type="checkbox"
                                            name="payment_types[]" value="transport_fee" id="check_transport_fee">
                                        <label class="form-check-label" for="check_transport_fee">
                                            <span class="fee-type-name">Transport Fee</span>
                                            <span class="fee-type-amount text-success" id="amt_transport_fee">₹0</span>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-2">
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
                            <input type="hidden" name="payment_type" id="payment_type_combined" value="">

                            <!-- Other Fee Custom Amount (hidden by default) -->
                            <div class="row mt-3" id="other_amount_section" style="display: none;">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Other Fee Description <span
                                                class="text-danger">*</span></label>
                                        <input type="text" name="other_description" id="other_description"
                                            class="form-control" placeholder="Enter fee description">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Other Fee Amount <span
                                                class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light">₹</span>
                                            <input type="number" name="other_amount" id="other_amount"
                                                class="form-control" step="1" min="0" placeholder="0">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Total Amount Section -->
                        <div class="form-section mb-4">
                            <h6 class="form-section-title">
                                <i class="fas fa-calculator text-primary"></i>
                                Payment Amount
                            </h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Total Amount <span
                                                class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-success text-white">
                                                <i class="fas fa-rupee-sign"></i>
                                            </span>
                                            <input type="number" name="amount" id="amount"
                                                class="form-control form-control-lg" step="1" min="0" placeholder="0"
                                                required readonly>
                                        </div>
                                        <small class="form-text text-muted">
                                            <i class="fas fa-info-circle"></i> Amount is auto-calculated based on
                                            selected fee types
                                        </small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="selected-fees-summary" id="selected_fees_summary"
                                        style="display: none;">
                                        <label class="form-label">Selected Fees Breakdown:</label>
                                        <ul class="list-unstyled mb-0" id="fees_breakdown_list"></ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Discount Section -->
                        <div class="form-section mb-4">
                            <h6 class="form-section-title">
                                <i class="fas fa-tags text-danger"></i>
                                Discount (Optional)
                            </h6>
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label class="form-label">Discount Type</label>
                                        <select name="discount_type" id="discount_type"
                                            class="form-control form-select">
                                            <option value="">No Discount</option>
                                            <option value="fixed">Fixed Amount</option>
                                            <option value="percentage">Percentage</option>
                                            <option value="smart">Smart / Global Waiver</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label class="form-label">Discount Value</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light" id="discount_symbol">₹</span>
                                            <input type="number" name="discount_value" id="discount_value"
                                                class="form-control" step="1" min="0" placeholder="0" disabled>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Discount Reason</label>
                                        <input type="text" name="discount_reason" id="discount_reason"
                                            class="form-control" placeholder="Enter reason for discount" disabled>
                                    </div>
                                </div>

                                <!-- Smart Discount Options (Hidden by default) -->
                                <div class="row mt-3" id="smart_options_container" style="display: none;">
                                    <div class="col-md-12">
                                        <div class="card bg-light border-warning">
                                            <div class="card-body py-2">
                                                <h6 class="text-warning mb-2"><i class="fas fa-magic"></i> Select
                                                    Fees to Waive (Global Waiver)</h6>
                                                <div class="row align-items-center">
                                                    <div class="col-md-7">
                                                        <div class="d-flex flex-wrap gap-2 align-items-center">
                                                            <div class="form-check">
                                                                <input class="form-check-input smart-fee-check"
                                                                    type="checkbox" id="smart_check_school"
                                                                    value="school_fee">
                                                                <label class="form-check-label small"
                                                                    for="smart_check_school">School Fee</label>
                                                            </div>
                                                            <div class="form-check">
                                                                <input class="form-check-input smart-fee-check"
                                                                    type="checkbox" id="smart_check_trust"
                                                                    value="trust_facilities_fee">
                                                                <label class="form-check-label small"
                                                                    for="smart_check_trust">Trust Fee</label>
                                                            </div>
                                                            <div class="form-check">
                                                                <input class="form-check-input smart-fee-check"
                                                                    type="checkbox" id="smart_check_tuition1"
                                                                    value="tuition_fee_part1">
                                                                <label class="form-check-label small"
                                                                    for="smart_check_tuition1">Token Fee</label>
                                                            </div>
                                                            <div class="form-check">
                                                                <input class="form-check-input smart-fee-check"
                                                                    type="checkbox" id="smart_check_tuition2"
                                                                    value="tuition_fee_part2">
                                                                <label class="form-check-label small"
                                                                    for="smart_check_tuition2">Tuition P2</label>
                                                            </div>
                                                            <div class="form-check">
                                                                <input class="form-check-input smart-fee-check"
                                                                    type="checkbox" id="smart_check_hostel"
                                                                    value="hostel_fee">
                                                                <label class="form-check-label small"
                                                                    for="smart_check_hostel">Hostel Fee</label>
                                                            </div>
                                                            <div class="form-check">
                                                                <input class="form-check-input smart-fee-check"
                                                                    type="checkbox" id="smart_check_transport"
                                                                    value="transport_fee">
                                                                <label class="form-check-label small"
                                                                    for="smart_check_transport">Transport Fee</label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-5">
                                                        <div class="d-flex align-items-center gap-2">
                                                            <select id="smart_waiver_mode"
                                                                class="form-select form-select-sm" style="width: auto;">
                                                                <option value="percentage">%</option>
                                                                <option value="fixed">₹</option>
                                                            </select>
                                                            <div class="input-group input-group-sm">
                                                                <input type="number" class="form-control"
                                                                    id="smart_waiver_value" value="100" min="0">
                                                                <span class="input-group-text"
                                                                    id="smart_waiver_symbol">%</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row" id="discount_summary" style="display: none;">
                                <div class="col-md-12">
                                    <div class="alert alert-success mb-0">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><i class="fas fa-tag"></i> Discount Applied:</strong>
                                                <span id="discount_amount_display">₹0</span>
                                            </div>
                                            <div>
                                                <strong>Final Payable Amount:</strong>
                                                <span class="text-success fs-5" id="final_amount_display">₹0</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Method Section -->
                        <div class="form-section mb-4">
                            <h6 class="form-section-title">
                                <i class="fas fa-credit-card text-info"></i>
                                Payment Method
                            </h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Payment Mode <span
                                                class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light">
                                                <i class="fas fa-wallet text-muted"></i>
                                            </span>
                                            <select name="payment_mode" id="payment_mode"
                                                class="form-control form-select" required>
                                                <option value="cash">&#128181; Cash</option>
                                                <option value="online">&#128179; Online Transfer</option>
                                                <option value="upi">&#128241; UPI</option>
                                                <option value="cheque">&#128196; Cheque</option>
                                                <option value="card">&#128179; Card</option>
                                                <option value="deduction">&#128184; Deduction</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Transaction ID</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-success text-white">
                                                <i class="fas fa-hashtag"></i>
                                            </span>
                                            <input type="text" name="transaction_id" id="transaction_id"
                                                class="form-control" value="<?php
                                                echo htmlspecialchars($generated_transaction_id ?? ''); ?>" readonly>
                                        </div>
                                        <small class="form-text text-muted">
                                            <i class="fas fa-info-circle"></i> System generated Transaction ID
                                            (read-only)
                                        </small>
                                    </div>
                                </div>
                                <!-- Cheque Details (Dynamic Container) -->
                                <div id="cheque_details_container" style="display: none;" class="mt-3">
                                    <!-- Dynamic cheque inputs will be injected here -->
                                </div>
                            </div>
                        </div>

                        <!-- Remarks Section -->
                        <div class="form-section">
                            <h6 class="form-section-title">
                                <i class="fas fa-sticky-note text-warning"></i>
                                Additional Notes
                            </h6>
                            <div class="form-group mb-0">
                                <label class="form-label">Remarks</label>
                                <textarea name="remarks" class="form-control" rows="3"
                                    placeholder="Enter any additional remarks or notes about this payment..."></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons at the end of form -->
                    <div class="card-footer bg-light border-top-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="payment-tips small text-muted d-none d-md-block">
                                <i class="fas fa-lightbulb text-warning me-1"></i>
                                Verify student details and fee selection before saving
                            </div>
                            <div class="d-flex gap-2 w-100 w-md-auto justify-content-end">
                                <a href="pending-payments.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-2"></i> Cancel
                                </a>
                                <button type="submit" class="btn btn-success btn-lg px-4">
                                    <i class="fas fa-save me-2"></i> Save Payment
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Summary & Reference -->
            <div class="col-lg-4">
                <div class="sticky-top" style="top: 20px; z-index: 10;">
                    <!-- Payment Summary Card -->
                    <div class="card card-payment mb-4 border-0 shadow-sm">
                        <div class="card-header bg-gradient-info">
                            <h3 class="card-title text-white">
                                <i class="fas fa-calculator me-2"></i> Payment Summary
                            </h3>
                        </div>
                        <div class="card-body">
                            <div id="summary_placeholder" class="text-center py-4 text-muted">
                                <i class="fas fa-receipt fa-3x mb-3 opacity-25"></i>
                                <p>Select a student and fee types to see the payment summary.</p>
                            </div>
                            <div id="summary_content" style="display: none;">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="text-muted">Total Gross:</span>
                                    <span class="fw-bold fs-5" id="summary_total_gross">₹0</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-3 text-danger"
                                    id="summary_discount_row" style="display: none !important;">
                                    <span class="text-muted">Discount Applied:</span>
                                    <span class="fw-bold" id="summary_discount_amount">-₹0</span>
                                </div>
                                <hr class="my-3">
                                <div class="d-flex justify-content-between align-items-center mb-0">
                                    <span class="fw-bold text-dark">Net Payable:</span>
                                    <span class="fw-bold text-success fs-3" id="summary_net_payable">₹0</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Reference Info Card -->
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-light border-0">
                            <h6 class="card-title mb-0 fw-bold">
                                <i class="fas fa-info-circle me-2 text-primary"></i> Payment Guidelines
                            </h6>
                        </div>
                        <div class="card-body p-3">
                            <ul class="list-unstyled small mb-0">
                                <li class="mb-2 d-flex align-items-start">
                                    <i class="fas fa-check-circle text-success mt-1 me-2"></i>
                                    <span>Select student first to load fee configuration.</span>
                                </li>
                                <li class="mb-2 d-flex align-items-start">
                                    <i class="fas fa-check-circle text-success mt-1 me-2"></i>
                                    <span>Multiple fee types can be collected in one transaction.</span>
                                </li>
                                <li class="mb-2 d-flex align-items-start">
                                    <i class="fas fa-check-circle text-success mt-1 me-2"></i>
                                    <span>Discounts apply only to <strong>Tuition Fee Part 2</strong>.</span>
                                </li>
                                <li class="d-flex align-items-start">
                                    <i class="fas fa-check-circle text-success mt-1 me-2"></i>
                                    <span>Receipts will be generated separately for each component.</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<style>
    /* Payment Form Custom Styles */
    .card-payment,
    .card-fee-reference,
    .card-actions {
        border: none;
        border-radius: 0.75rem;
        overflow: hidden;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    }

    .bg-gradient-primary {
        background: linear-gradient(135deg, #2563EB 0%, #1D4ED8 100%) !important;
    }

    .bg-gradient-info {
        background: linear-gradient(135deg, #0D9488 0%, #0F766E 100%) !important;
    }

    .card-header {
        border-bottom: none;
        padding: 1rem 1.25rem;
    }

    .card-header .card-title {
        margin-bottom: 0;
        font-size: 1rem;
        font-weight: 600;
    }

    .form-section {
        background: #f8fafc;
        border-radius: 0.5rem;
        padding: 1.25rem;
        border: 1px solid #e2e8f0;
    }

    .form-section-title {
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 1rem;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid #e2e8f0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .form-section-title i {
        width: 24px;
        height: 24px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #fff;
        border-radius: 0.375rem;
        font-size: 0.875rem;
    }

    .form-label {
        font-weight: 500;
        color: #374151;
        font-size: 0.875rem;
        margin-bottom: 0.375rem;
    }

    .input-group-text {
        border-color: #e2e8f0;
    }

    .form-control,
    .form-select {
        border-color: #e2e8f0;
        border-radius: 0.375rem;
    }

    .form-control:focus,
    .form-select:focus {
        border-color: #2563EB;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }

    .form-control-lg {
        font-size: 1.25rem;
        font-weight: 600;
    }

    .fee-row {
        cursor: pointer;
        transition: background-color 0.2s ease;
    }

    .fee-row:hover {
        background-color: #f0fdf4 !important;
    }

    .payment-tips {
        background: #f8fafc;
        padding: 1rem;
        border-radius: 0.5rem;
    }

    .page-title-icon {
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
    }

    .btn-success {
        background: linear-gradient(135deg, #10B981 0%, #059669 100%);
        border: none;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }

    .btn-success:hover {
        background: linear-gradient(135deg, #059669 0%, #047857 100%);
        transform: translateY(-1px);
        box-shadow: 0 6px 15px rgba(16, 185, 129, 0.4);
    }

    .btn-outline-secondary {
        border-color: #e2e8f0;
        color: #64748b;
    }

    .btn-outline-secondary:hover {
        background-color: #f1f5f9;
        border-color: #cbd5e1;
        color: #475569;
    }

    #student_details {
        margin-top: 1rem;
    }

    #student_details .alert {
        margin-bottom: 0;
        border-radius: 0.5rem;
    }

    /* Table styles for fee reference */
    .card-fee-reference .table th {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 600;
        color: #64748b;
        background: #f8fafc;
        padding: 0.75rem;
        border: none;
    }

    .card-fee-reference .table td {
        padding: 0.75rem;
        border-color: #f1f5f9;
        vertical-align: middle;
    }

    /* Custom Checkbox Card Styles */
    .custom-checkbox-card {
        background: #fff;
        border: 2px solid #e2e8f0;
        border-radius: 0.5rem;
        padding: 0.875rem;
        transition: all 0.2s ease;
        cursor: pointer;
        min-height: 70px;
    }

    .custom-checkbox-card:hover {
        border-color: #93c5fd;
        background: #f0f9ff;
    }

    .custom-checkbox-card .form-check-input {
        width: 1.25rem;
        height: 1.25rem;
        margin-top: 0;
    }

    .custom-checkbox-card .form-check-input:checked {
        background-color: #10B981;
        border-color: #059669;
    }

    .custom-checkbox-card .form-check-input:checked+.form-check-label {
        color: #059669;
    }

    .custom-checkbox-card .form-check-label {
        cursor: pointer;
        width: 100%;
        display: flex;
        flex-direction: column;
        padding-left: 0.5rem;
    }

    .custom-checkbox-card .fee-type-name {
        font-weight: 600;
        color: #1e293b;
        font-size: 0.875rem;
    }

    .custom-checkbox-card .fee-type-amount {
        font-weight: 700;
        font-size: 0.95rem;
        margin-top: 0.25rem;
    }

    .custom-checkbox-card.selected {
        border-color: #10B981;
        background: #f0fdf4;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
    }

    .custom-checkbox-card.disabled {
        opacity: 0.6;
        pointer-events: none;
        background: #f1f5f9;
        border-style: dashed;
    }

    .custom-checkbox-card.disabled .fee-type-name {
        color: #94a3b8;
    }

    .enable-fee-link {
        pointer-events: auto;
        /* Restore clickability */
        position: relative;
        z-index: 5;
    }

    /* Selected Fees Summary in Left Column */
    .selected-fees-summary {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 0.5rem;
        padding: 1.25rem;
    }

    .selected-fees-summary ul li {
        padding: 0.5rem 0;
        color: #1e293b;
        font-size: 0.875rem;
        display: flex;
        justify-content: space-between;
    }

    .selected-fees-summary ul li:not(:last-child) {
        border-bottom: 1px dashed #bbf7d0;
    }
</style>

<!-- Payment Confirmation Modal -->
<div class="modal fade" id="paymentConfirmationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-gradient-primary text-white border-0 py-3">
                <h5 class="modal-title fw-bold"><i class="fas fa-check-circle me-2"></i>Confirm Payment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-soft-warning border-0 mb-4 bg-warning bg-opacity-10 text-warning-emphasis">
                    <i class="fas fa-exclamation-triangle me-2"></i> Please verify details carefully before saving.
                </div>
                <div class="confirmation-details">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Student:</span>
                        <span class="fw-bold" id="confirm_student_name"></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Payment Date:</span>
                        <span class="fw-bold" id="confirm_payment_date"></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Payment Mode:</span>
                        <span class="fw-bold text-uppercase" id="confirm_payment_mode"></span>
                    </div>
                    <div id="confirm_cheque_row" style="display:none;" class="mb-2">
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Cheque Details:</span>
                            <div class="text-end fw-bold" id="confirm_cheque_details"></div>
                        </div>
                    </div>
                    <hr class="my-3">
                    <div id="confirm_breakdown" class="mb-3 small text-muted"></div>
                    <div class="d-flex justify-content-between align-items-center bg-light p-3 rounded-3">
                        <span class="fw-bold text-dark fs-5">Total Payable:</span>
                        <span class="fw-bold text-success fs-3" id="confirm_amount"></span>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-link text-muted text-decoration-none" data-bs-dismiss="modal">Back
                    to Edit</button>
                <button type="button" class="btn btn-success btn-lg px-5 shadow-sm" id="btn_confirm_save">
                    <i class="fas fa-save me-2"></i> Confirm & Save
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Fee Selection Modal -->
<div class="modal fade" id="feeSelectionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Select Amount</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center pt-4 pb-4">
                <p class="mb-4 text-muted">Choose the amount to verify:</p>
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-outline-primary btn-token fw-bold py-2"></button>
                    <button type="button" class="btn btn-primary btn-total fw-bold py-2"></button>
                </div>
            </div>
        </div>
    </div>

    <?php
    include '../../include/footer.php'; ?>

    <!-- Flatpickr JS -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <script>
        $(document).ready(function () {
            // Initialize Flatpickr for Payment Date
            flatpickr("input[name='payment_date']", {
                dateFormat: "Y-m-d",
                altInput: true,
                altFormat: "d M Y",
                maxDate: "today",
                disableMobile: "true"
            });

            // Initialize Flatpickr for Cheque Date
            flatpickr("input[name='cheque_date']", {
                dateFormat: "Y-m-d",
                altInput: true,
                altFormat: "d M Y",
                maxDate: "today",
                disableMobile: "true"
            });
        });
    </script>

    <script>
        // Store fee configuration data
        let currentFeeConfig = {};
        let initialFeeConfig = {}; // To track original state for enabling/disabling fees
        let currentStudent = null; // Currently selected student
        const feeLabels = {
            'school_fee': 'School Fee',
            'trust_facilities_fee': 'Trust Facilities Fee',
            'tuition_fee_part1': 'Tuition Fee Part 1',
            'tuition_fee_part2': 'Tuition Fee Part 2',
            'hostel_fee': 'Hostel Fee',
            'hostel_cash_fee': 'Hostel Cash Fee (No Receipt)',
            'transport_fee': 'Transport Fee',
            'other': 'Other'
        };

        $(document).ready(function () {
            // Initialize Student Search Component
            const studentSearch = new StudentSearchComponent({
                inputId: 'student_search',
                hiddenInputId: 'student_id',
                resultsContainerId: 'student_search_results',
                detailsContainerId: 'student_details',
                onSelect: function (student) {
                    selectStudent(student);
                }
            });

            // Common function to handle student selection
            function selectStudent(student) {
                currentStudent = student;
                // Add selected class to input
                $('#student_search').addClass('has-selection');

                // If this was called programmatically (not via search select), update the input value
                const fullName = [student.surname, student.student_name, student.fathers_name].filter(Boolean).join(' ');
                if ($('#student_search').val() !== fullName) {
                    $('#student_search').val(fullName);
                }

                $('#student_id').val(student.id);

                console.log('Student selected:', student);

                // Display custom student details
                const detailsHtml = `
            <div class="card bg-light border-0 shadow-sm mb-0 mt-2">
                <div class="card-body p-3">
                    <div class="row align-items-center">
                        <div class="col-md-5">
                            <h6 class="mb-1 fw-bold text-primary"><i class="fas fa-user-graduate me-2"></i>${fullName}</h6>
                            <div class="d-flex flex-column gap-1">
                                <small class="text-muted"><i class="fas fa-phone me-2 fa-fw"></i>${student.mob || 'N/A'}</small>
                                <small class="text-muted"><i class="fas fa-id-card me-2 fa-fw"></i>Aadhaar: ${student.aadhaar || 'N/A'}</small>
                                <small class="text-muted"><i class="fas fa-university me-2 fa-fw"></i>School: <span class="text-dark fw-bold">${student.school_name || 'N/A'}</span></small>
                            </div>
                        </div>
                        <div class="col-md-4 border-start border-end px-3">
                            <div class="d-flex flex-column gap-1">
                                <small><strong>Course:</strong> ${student.course_name || 'N/A'}</small>
                                <small><strong>Semester:</strong> <span class="text-primary fw-bold">${student.term_name || 'Semester 1'}</span></small>
                                <small><strong>Group:</strong> ${student.group_name || 'N/A'}</small>
                            </div>
                        </div>
                        <div class="col-md-3 text-end">
                            <div class="d-flex flex-column gap-2 align-items-end">
                                <span class="badge bg-secondary">${student.medium_name || 'N/A'}</span>
                                ${student.token_fees_paid == 0 ? '<span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i> Token Pending</span>' : '<span class="badge bg-success"><i class="fas fa-check me-1"></i> Token Paid</span>'}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
                // Append token fee warning banner if token fee is not paid
                let tokenWarningHtml = '';
                if (student.token_fees_paid == 0) {
                    tokenWarningHtml = `
                    <div id="token_fee_warning" class="alert alert-danger mt-2 mb-0" role="alert">
                        <strong><i class="fas fa-lock me-1"></i> Token Fee Pending!</strong>
                        Only Token Fee (Tuition Fee Part 1) can be collected until it is paid. All other fees are locked.
                    </div>`;
                }
                $('#student_details').html(detailsHtml + tokenWarningHtml);
                $('#student_details').show();

                // Update summary placeholder
                $('#summary_placeholder').hide();
                $('#summary_content').show();
                $('#summary_total_gross').text('\u20B90');
                $('#summary_net_payable').text('\u20B90');

                // Fetch fee configuration for the selected student
                fetchStudentFeeConfig(student.id);
            }

            // Clear selection styling when input is cleared
            $('#student_search').on('input', function () {
                if ($(this).val().trim() === '') {
                    $(this).removeClass('has-selection');
                    currentStudent = null;
                    resetFeeCheckboxes();
                }
            });

            // Auto-select student if passed via URL
            <?php if ($preselected_student): ?>
                // Use PHP data for immediate rendering (faster and more reliable)
                const preselectedStudent = <?php echo json_encode($preselected_student); ?>;
                if (preselectedStudent) {
                    selectStudent(preselectedStudent);
                }
            <?php else: ?>
                // Fallback to AJAX if PHP didn't capture the student (e.g. if API call failed but ID is in URL)
                const urlParams = new URLSearchParams(window.location.search);
                const preselectedId = urlParams.get('student_id');

                if (preselectedId) {
                    // Show loading state
                    $('#student_details').html('<div class="text-center p-3"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div><p class="mt-2">Loading student details...</p></div>');

                    // Use the SEARCH API to get consistent data structure
                    $.api.get('students/search', { search: preselectedId })
                        .then(function (response) {
                            if (response.success && response.students && response.students.length > 0) {
                                // Find exact match by ID since search might return partial matches
                                const student = response.students.find(s => s.id == preselectedId) || response.students[0];
                                selectStudent(student);
                            } else {
                                $('#student_details').html('<div class="alert alert-warning">Student not found.</div>');
                            }
                        })
                        .catch(function (error) {
                            console.error('Error fetching preselected student:', error);
                            $('#student_details').html('<div class="alert alert-danger">Error loading student details.</div>');
                        });
                }
            <?php endif; ?>

            // Initialize discount field state - disabled by default
            $('#discount_type').prop('disabled', true);
            $('#discount_value').prop('disabled', true);
            $('#discount_reason').prop('disabled', true);

            // Show/hide cheque details
            $('#payment_mode').change(function () {
                const mode = $(this).val();

                // Toggle Cheque Details
                if (mode === 'cheque') {
                    $('#cheque_details_container').slideDown(200);
                    renderChequeInputs();
                } else {
                    $('#cheque_details_container').slideUp(200);
                }

                // NEW: Handle "Deduction" mode
                if (mode === 'deduction') {
                    // Ensure without GST is UNCHECKED (Deduction is now Gross)
                    if ($('#is_without_gst').is(':checked')) {
                        $('#is_without_gst').prop('checked', false).trigger('change');
                    }
                    // Auto-select Tuition Part 2 if available and not checked
                    if (!$('#check_tuition_fee_part2').is(':checked') && !$('#check_tuition_fee_part2').is(':disabled')) {
                        $('#check_tuition_fee_part2').prop('checked', true).trigger('change');
                    }
                }
            });

            // Show/Hide Without GST Toggle (Only for Tuition Fee Part 2)
            // This needs to be called after fee config is loaded, so it's handled in fetchStudentFeeConfig
            // and also when tuition fee part 2 checkbox changes.

            // Handle checkbox changes
            $('.payment-type-checkbox').on('change', function () {
                const $parent = $(this).closest('.custom-checkbox-card');

                if ($(this).is(':checked')) {
                    $parent.addClass('selected');
                } else {
                    $parent.removeClass('selected');
                }

                // Handle "Other" checkbox special case
                if ($(this).val() === 'other') {
                    if ($(this).is(':checked')) {
                        $('#other_amount_section').slideDown(200);
                    } else {
                        $('#other_amount_section').slideUp(200);
                        $('#other_amount').val('');
                        $('#other_description').val('');
                    }
                }

                // Handle Tuition Fee Part 2 checkbox for GST toggle visibility
                if ($(this).val() === 'tuition_fee_part2') {
                    // Logic updated: Toggle visibility of deduction mode option maybe?
                    // But for now, we just keep the deduction mode available in dropdown.
                    // The old toggle is hidden, so we don't need to fade it in/out.
                }

                calculateTotalAmount();
            });

            // Without GST Toggle logic
            $('#is_without_gst').on('change', function () {
                const checked = $(this).is(':checked');
                if (checked) {
                    // Disable and uncheck other fees
                    $('.payment-type-checkbox').not('#check_tuition_fee_part2').prop('checked', false).prop('disabled', true);
                    $('#check_other').prop('checked', false).prop('disabled', true);
                    $('.gst-label-tuition2').hide();

                    // Specific restriction for school_fee and trust_facilities_fee
                    $('#check_school_fee, #check_trust_facilities_fee').prop('disabled', true).prop('checked', false);
                    $('#check_school_fee, #check_trust_facilities_fee').closest('.custom-checkbox-card').addClass('disabled').removeClass('selected');

                    updateFeeCheckboxes(currentFeeConfig, currentFeeConfig.paid_fees || {}); // Refresh UI states
                } else {
                    // Re-enable everything
                    $('.payment-type-checkbox').prop('disabled', false);
                    $('#check_other').prop('disabled', false);
                    $('.gst-label-tuition2').show();
                    updateFeeCheckboxes(currentFeeConfig, currentFeeConfig.paid_fees || {});
                }
                calculateTotalAmount();
            });

            // Handle other amount input
            $('#other_amount').on('input', function () {
                calculateTotalAmount();
            });

            // Handle discount type change
            $('#discount_type').on('change', function () {
                const discountType = $(this).val();

                // Validation logic based on selection
                let currentDiscountType = discountType;
                if (currentDiscountType === 'fixed' || currentDiscountType === 'percentage') {
                    if (!$('#check_tuition_fee_part2').is(':checked')) {
                        showToast('warning', 'Restricted Option', 'Fixed/Percentage discounts are only available for Tuition Fee Part 2. Switching to Smart Waiver.');
                        currentDiscountType = 'smart';
                        $(this).val('smart');
                    }
                }

                if (currentDiscountType) {
                    // Handle Smart Discount
                    if (currentDiscountType === 'smart') {
                        $('#smart_options_container').slideDown(200);
                        $('#discount_value').prop('readonly', true); // Calculated automatically
                        $('#discount_symbol').html('₹'); // Currency symbol
                        $('#discount_value').removeAttr('max');

                        // Reset value initially if 0 or empty to trigger calc
                        if (!$('#discount_value').val()) $('#discount_value').val(0);
                    } else {
                        $('#smart_options_container').slideUp(200);
                        $('#discount_value').prop('readonly', false);
                        $('#discount_value').prop('disabled', false);

                        // Update symbol based on type
                        if (currentDiscountType === 'percentage') {
                            $('#discount_symbol').text('%');
                            $('#discount_value').attr('max', '100');
                        } else {
                            $('#discount_symbol').html('₹');
                            $('#discount_value').removeAttr('max');
                        }
                    }

                    $('#discount_reason').prop('disabled', false);
                } else {
                    $('#smart_options_container').slideUp(200);
                    $('#discount_value').prop('disabled', true).val('');
                    $('#discount_reason').prop('disabled', true).val('');
                    $('#discount_summary').hide();
                    // Reset smart inputs
                    $('.smart-fee-check').prop('checked', false);
                    $('#smart_waiver_percentage').val(100);
                }
                calculateTotalAmount();
            });

            // Smart Discount Mode Selector change
            $('#smart_waiver_mode').on('change', function () {
                const mode = $(this).val();
                if (mode === 'percentage') {
                    $('#smart_waiver_symbol').text('%');
                    $('#smart_waiver_value').val(100).attr('max', 100);
                } else {
                    $('#smart_waiver_symbol').text('₹');
                    $('#smart_waiver_value').val(0).removeAttr('max');
                }
                calculateTotalAmount();
            });

            // Smart Discount Input Listeners
            $('.smart-fee-check, #smart_waiver_value').on('change input', function () {
                calculateTotalAmount();
            });

            // Handle discount value input
            $('#discount_value').on('input', function () {
                calculateTotalAmount();
            });

            // Click on fee row to select fee types
            $('.fee-row').click(function () {
                var tokenFee = $(this).data('token');
                var totalFee = $(this).data('total');

                // Use custom modal for selection
                $('#feeSelectionModal .btn-token').html('\u20B9' + tokenFee.toLocaleString() + ' (Token)');
                $('#feeSelectionModal .btn-total').html('\u20B9' + totalFee.toLocaleString() + ' (Total)');

                $('#feeSelectionModal').modal('show');

                // Handlers
                $('#feeSelectionModal .btn-token').off('click').on('click', function () {
                    selectTokenFeeComponents();
                    $('#feeSelectionModal').modal('hide');
                });

                $('#feeSelectionModal .btn-total').off('click').on('click', function () {
                    selectAllFeeComponents();
                    $('#feeSelectionModal').modal('hide');
                });
            });

            // Form validation and submission via API Client
            $('#paymentForm').submit(function (e) {
                e.preventDefault();

                var studentId = $('#student_id').val();
                if (!studentId) {
                    showToast('error', 'Student Required', 'Please select a student first!');

                    return false;
                }

                // Check if at least one payment type is selected
                var selectedTypes = $('.payment-type-checkbox:checked');
                if (selectedTypes.length === 0) {
                    showToast('error', 'Payment Type Required', 'Please select at least one fee type!');

                    return false;
                }

                // Validate "Other" fee if selected
                if ($('#check_other').is(':checked')) {
                    if (!$('#other_description').val().trim()) {
                        showToast('error', 'Description Required', 'Please enter a description for the other fee!');

                        return false;
                    }
                    if (!$('#other_amount').val() || parseFloat($('#other_amount').val()) <= 0) {
                        showToast('error', 'Amount Required', 'Please enter a valid amount for the other fee!');

                        return false;
                    }
                }

                // Validate discount if selected
                if ($('#discount_type').val()) {
                    // Check if Tuition Fee Part 2 is selected
                    if (!$('#check_tuition_fee_part2').is(':checked')) {
                        showToast('error', 'Cannot Apply Discount', 'Discount can only be applied when Tuition Fee Part 2 is selected!');

                        return false;
                    }
                    if (!$('#discount_reason').val().trim()) {
                        showToast('error', 'Discount Reason Required', 'Please enter a reason for applying discount!');

                        return false;
                    }
                    if (!$('#discount_value').val() || parseFloat($('#discount_value').val()) <= 0) {
                        showToast('error', 'Invalid Discount', 'Please enter a valid discount value!');

                        return false;
                    }
                }

                // Validate Cheque Details if mode is cheque
                if ($('#payment_mode').val() === 'cheque') {
                    let hasError = false;
                    $('.cheque-input-card').each(function () {
                        const componentName = $(this).find('.fw-bold').text();
                        if (!$(this).find('.cheque-no').val().trim()) {
                            showToast('error', 'Cheque Number Required', `Please enter the Cheque Number for ${componentName}!`);
                            hasError = true;
                            return false;
                        }
                        if (!$(this).find('.bank-name').val().trim()) {
                            showToast('error', 'Bank Name Required', `Please enter the Bank Name for ${componentName}!`);
                            hasError = true;
                            return false;
                        }
                        if (!$(this).find('.cheque-date').val()) {
                            showToast('error', 'Cheque Date Required', `Please enter the Cheque Date for ${componentName}!`);
                            hasError = true;
                            return false;
                        }
                    });
                    if (hasError) return false;
                }

                var amount = $('#amount').val();
                if (!amount || amount <= 0) {
                    showToast('error', 'Invalid Amount', 'Please select fee types to calculate the payment amount!');

                    return false;
                }

                // Combine selected payment types into a single string
                var paymentTypes = [];
                $('.payment-type-checkbox:checked').each(function () {
                    paymentTypes.push($(this).val());
                });

                // Prepare form data as object
                var formData = {
                    student_id: $('#student_id').val(),
                    payment_date: $('input[name="payment_date"]').val(),
                    payment_type: paymentTypes.join(','),
                    amount: $('#amount').val(),
                    payment_mode: $('#payment_mode option:selected').val(),
                    transaction_id: $('#transaction_id').val(),
                    remarks: $('textarea[name="remarks"]').val(),
                    discount_type: $('#discount_type').val() || '',
                    discount_value: $('#discount_value').val() || '0',
                    discount_reason: $('#discount_reason').val() || '',
                    is_without_gst: $('#is_without_gst').is(':checked')
                };

                // Add cheque details if payment mode is cheque
                if ($('#payment_mode').val() === 'cheque') {
                    // Note: Cheque details are now handled within each item of the payment_details array
                    // We can store the first cheque info as fallback if needed, but the backend is now updated to handle per-component info
                }

                // Add other fee details if selected
                if ($('#check_other').is(':checked')) {
                    formData.other_description = $('#other_description').val();
                    formData.other_amount = $('#other_amount').val();
                }

                // Add detailed breakdown for separate receipts
                if (currentPaymentBreakdown && currentPaymentBreakdown.length > 0) {
                    formData.payment_details = currentPaymentBreakdown.map(item => {
                        let detail = {
                            fee_component: item.key,
                            amount: item.amount
                        };

                        // Add specific cheque details if in cheque mode
                        if ($('#payment_mode').val() === 'cheque') {
                            const $card = $(`.cheque-input-card[data-component="${item.key}"]`);
                            detail.cheque_no = $card.find('.cheque-no').val();
                            detail.bank_name = $card.find('.bank-name').val();
                            detail.cheque_date = $card.find('.cheque-date').val();
                        }
                        return detail;
                    });
                }

                // --- CONFIRMATION MODAL LOGIC START ---

                // Populate Modal
                $('#confirm_student_name').text($('#student_search').val());

                // Format Date
                let pDate = new Date($('input[name="payment_date"]').val());
                $('#confirm_payment_date').text(pDate.toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' }));

                // Mode
                let modeText = $('#payment_mode option:selected').text();
                $('#confirm_payment_mode').text(modeText);

                // Cheque details
                if ($('#payment_mode').val() === 'cheque') {
                    let cardHtml = '<div class="mt-2">';
                    formData.payment_details.forEach(detail => {
                        const feeName = feeLabels[detail.fee_component] || detail.fee_component;
                        let cDate = 'N/A';
                        if (detail.cheque_date) {
                            let cd = new Date(detail.cheque_date);
                            cDate = cd.toLocaleDateString('en-IN', {
                                day: 'numeric',
                                month: 'short',
                                year: 'numeric'
                            });
                        }
                        cardHtml += `
                            <div class="mb-2 p-2 bg-light rounded border-start border-3 border-primary text-start">
                                <div class="fw-bold small text-dark">${feeName}</div>
                                <div class="small">No: ${detail.cheque_no || 'N/A'} | Bank: ${detail.bank_name || 'N/A'}</div>
                                <div class="small text-muted">Date: ${cDate}</div>
                            </div>
                        `;
                    });
                    cardHtml += '</div>';
                    $('#confirm_cheque_details').html(cardHtml);
                    $('#confirm_cheque_row').show();
                } else {
                    $('#confirm_cheque_row').hide();
                }

                // Amount
                $('#confirm_amount').text('\u20B9' + Math.round(parseFloat(formData.amount)).toLocaleString('en-IN', { maximumFractionDigits: 0 }));

                // Breakdown
                let breakdownHtml = '<ul class="list-unstyled mb-0">';
                if (currentPaymentBreakdown && currentPaymentBreakdown.length > 0) {
                    currentPaymentBreakdown.forEach(item => {
                        let displayAmt = Math.round(parseFloat(item.amount)).toLocaleString('en-IN');
                        let substructure = '';

                        // Add GST breakdown for Tuition fees if applicable
                        if (item.key && item.key.includes('tuition_fee') && !formData.is_without_gst) {
                            let total = parseFloat(item.amount);
                            let base = total / 1.18;
                            let gst = total - base;
                            substructure = `<div class="small text-muted mb-1" style="font-size: 0.75rem;">
                                (\u20B9${formatCurrency(base)} + \u20B9${formatCurrency(gst)} GST)
                            </div>`;
                            // No extra label
                        } else if (item.key === 'other' && item.name.toLowerCase().includes('gst')) {
                            // Optional: if "Other" fee mention GST, we could try to calculate, but usually simple is better
                        }

                        breakdownHtml += `<li class="mb-2">
                            <div class="d-flex justify-content-between">
                                <span>${item.name}</span>
                                <span>\u20B9${displayAmt}</span>
                            </div>
                            ${substructure}
                        </li>`;
                    });
                }
                breakdownHtml += '</ul>';
                $('#confirm_breakdown').html(breakdownHtml);

                // Show Modal
                $('#paymentConfirmationModal').modal('show');

                // Handle Confirm Button
                $('#btn_confirm_save').off('click').on('click', function () {
                    $('#paymentConfirmationModal').modal('hide');
                    submitPayment(formData);
                });

                // --- CONFIRMATION MODAL LOGIC END ---
            });

            // Separate function to handle actual submission
            function submitPayment(formData) {
                // Show loading state
                const $btn = $('#btn_confirm_save');
                const $mainSubmitBtn = $('#paymentForm button[type="submit"]');
                const originalBtnText = $btn.html();
                const originalMainBtnText = $mainSubmitBtn.html();

                // Disable both buttons
                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i> Processing...');
                $mainSubmitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i> Processing...');


                // Debug: Log form data
                console.log('Submitting payment data:', formData);

                // Pre-open window to bypass popup blocker (if not without GST)
                let printWindow = null;
                const isWithoutGst = $('#is_without_gst').is(':checked');
                const paymentMode = $('input[name="payment_mode"]:checked').val();

                if (!isWithoutGst && paymentMode !== 'deduction') {
                    printWindow = window.open('', '_blank');
                    if (printWindow) {
                        printWindow.document.write('<html><head><title>Generating Receipt...</title><style>body{font-family:sans-serif;display:flex;justify-content:center;align-items:center;height:100vh;margin:0;background:#f8f9fa;color:#666;}</style></head><body><div style="text-align:center;padding-top:100px;font-family:sans-serif;color:#666;"><h3>Generating Receipt...</h3><p>Please wait while we process your request.</p></div></body></html>');
                    }
                }

                // Submit via API Client
                $.api.post('payments/payment-save', formData)
                    .then(function (response) {
                        console.log('Payment save response:', response);
                        if (response.success) {
                            showToast('success', 'Success!', response.message || 'Payment saved successfully!');

                            // Update pre-opened window or open new one if blocked/null
                            if (response.data && response.data.receipt_url) {
                                if (printWindow) {
                                    printWindow.location.href = response.data.receipt_url;
                                } else {
                                    window.open(response.data.receipt_url, '_blank');
                                }
                            } else if (printWindow) {
                                printWindow.close();
                            }

                            setTimeout(() => {
                                // Redirect to student ledger ONLY after success
                                let studentId = (response.data && response.data.student_id) ? response.data.student_id : formData.student_id;
                                if (studentId) {
                                    window.location.href = '../reports/financial/student-ledger.php?student_id=' + studentId;
                                } else {
                                    // Fallback to pending payments if student ID is somehow lost
                                    window.location.href = 'pending-payments.php';
                                }
                            }, 1500);
                        } else {
                            if (printWindow) printWindow.close();
                            showToast('error', 'Error', response.message || response.error || 'Failed to save payment');
                            $btn.prop('disabled', false).html(originalBtnText);
                            $mainSubmitBtn.prop('disabled', false).html(originalMainBtnText);
                        }
                    })
                    .catch(function (error) {
                        if (printWindow) printWindow.close();
                        console.error('Payment save error:', error);
                        showToast('error', 'Error', error.message || error.responseJSON?.error || 'An error occurred while saving the payment');
                        $btn.prop('disabled', false).html(originalBtnText);
                        $mainSubmitBtn.prop('disabled', false).html(originalMainBtnText);
                    });
            }
        });

        // Fetch fee configuration for student
        function fetchStudentFeeConfig(studentId) {
            $.api.get('common/get-student-fee-config', {
                student_id: studentId
            }).then(function (response) {
                if (response.success && response.fee_config) {
                    currentFeeConfig = response.fee_config;
                    currentFeeConfig.paid_fees = response.paid_fees || {};
                    currentFeeConfig.fee_allocations = response.fee_allocations || null; // NEW: Store calculated state

                    // Store initial config to track what was originally enabled/disabled
                    initialFeeConfig = JSON.parse(JSON.stringify(response.fee_config));

                    // Attach scholarship info to fee config object so it's available in updateFeeCheckboxes
                    if (response.scholarship_info) {
                        response.fee_config.scholarship_info = response.scholarship_info;
                        currentFeeConfig.scholarship_info = response.scholarship_info;
                    }

                    // Debug logs: expose backend objects to browser console for troubleshooting
                    try {
                        console.log('get-student-fee-config response', response);
                        console.log('fee_config:', response.fee_config);
                        console.log('paid_fees:', response.paid_fees);
                        console.log('fee_allocations:', response.fee_allocations);
                    } catch (e) {
                        // ignore
                    }

                    updateFeeCheckboxes(response.fee_config, response.paid_fees || {});
                    applyTokenFeeRestrictions();

                    // Display scholarship info if available
                    // if (response.scholarship_info) {
                    //    displayScholarshipInfo(response.scholarship_info);
                    // } else {
                    //    hideScholarshipInfo();
                    // }
                } else {
                    resetFeeCheckboxes();
                    hideScholarshipInfo();
                    showToast('warning', 'No Fee Configuration', 'No fee configuration found for this student\'s course. You can still add custom payments.');

                }
            }).catch(function (error) {
                resetFeeCheckboxes();
                hideScholarshipInfo();
                console.error('Failed to fetch fee configuration:', error);
                showToast('error', 'Error', 'Failed to fetch fee configuration. Please try again.');

            });
        }

        // Enforce token fee must be paid before any other fee can be collected
        function applyTokenFeeRestrictions() {
            if (!currentStudent || currentStudent.token_fees_paid != 0) {
                return; // No restriction needed
            }
            // Disable every fee checkbox except tuition_fee_part1 (token fee)
            $('.payment-type-checkbox').each(function () {
                if ($(this).val() !== 'tuition_fee_part1') {
                    $(this).prop('checked', false).prop('disabled', true);
                    $(this).closest('.custom-checkbox-card').addClass('disabled');
                }
            });
            // Hide "Enable" buttons (e.g. Enable Transport Fee, Enable Hostel Fee)
            // so they cannot be clicked to bypass the restriction
            $('.enable-fee-container').hide();
        }

        // Display scholarship information
        function displayScholarshipInfo(scholarshipInfo) {
            const scholarshipAmount = parseFloat(scholarshipInfo.scholarship_amount) || 0;
            const additionalScholarship = parseFloat(scholarshipInfo.additional_scholarship_amount) || 0;
            const scholarshipPercentage = parseFloat(scholarshipInfo.scholarship_percentage) || 0;
            const postAdmissionDiscount = parseFloat(scholarshipInfo.post_admission_discount) || 0;
            const discountRemarks = scholarshipInfo.post_admission_discount_remarks || '';

            // Calculate total discount
            const totalDiscount = scholarshipAmount + additionalScholarship + postAdmissionDiscount;

            // Only show section if there's any scholarship/discount
            if (totalDiscount > 0 || scholarshipPercentage > 0) {
                $('#disp_scholarship_amount').text('\u20B9' + formatCurrency(scholarshipAmount));
                $('#disp_additional_scholarship').text('\u20B9' + formatCurrency(additionalScholarship));
                $('#disp_scholarship_percentage').text(scholarshipPercentage + '%');
                $('#disp_post_admission_discount').text('\u20B9' + formatCurrency(postAdmissionDiscount));
                $('#disp_total_discount').text('\u20B9' + formatCurrency(totalDiscount));

                if (discountRemarks) {
                    $('#disp_discount_remarks').text(discountRemarks);
                    $('#post_admission_remarks_row').show();
                } else {
                    $('#post_admission_remarks_row').hide();
                }

                $('#scholarship_info_section').slideDown(200);

                // Store scholarship info in currentFeeConfig for calculation
                currentFeeConfig.scholarship_info = scholarshipInfo;
                currentFeeConfig.total_scholarship_discount = totalDiscount;
            } else {
                hideScholarshipInfo();
            }
        }

        // Hide scholarship info section
        function hideScholarshipInfo() {
            $('#scholarship_info_section').hide();
            if (currentFeeConfig) {
                currentFeeConfig.scholarship_info = null;
                currentFeeConfig.total_scholarship_discount = 0;
            }
        }

        // Update fee checkboxes with amounts from fee configuration
        function updateFeeCheckboxes(feeConfig, paidFees) {
            // Fallback to global if missing
            feeConfig = feeConfig || currentFeeConfig;
            paidFees = paidFees || currentFeeConfig.paid_fees || {};

            const allocations = currentFeeConfig.fee_allocations || {};

            // Helper to get allocation data
            const getAlloc = (key) => allocations[key] || null;

            // --- Scholarship & Discount Calculation for Tuition Part 2 ---
            // We still want to show the scholarship/discount labels for the accountant
            let scholarshipText = '';
            let discountText = '';

            const t2Alloc = getAlloc('tuition_fee_part2');
            let hasDeductions = false;
            let originalAmount = 0;
            let finalAmount = 0;

            if (t2Alloc) {
                originalAmount = t2Alloc.gross_amount;
                finalAmount = t2Alloc.pending_amount + t2Alloc.paid_amount; // Gross after scholarship but before payments

                // Compatibility for discount calculator
                currentFeeConfig.tuition_fee_part2_with_gst = originalAmount;
                if (allocations['tuition_fee_part1']) {
                    currentFeeConfig.tuition_fee_part1_with_gst = allocations['tuition_fee_part1'].gross_amount;
                } else if (allocations['token_fee']) {
                    currentFeeConfig.tuition_fee_part1_with_gst = allocations['token_fee'].gross_amount;
                }

                // Additional references for Smart Waiver
                if (allocations['school_fee']) {
                    currentFeeConfig.school_fee_with_gst = allocations['school_fee'].gross_amount;
                }
                if (allocations['trust_facilities_fee']) {
                    currentFeeConfig.trust_facilities_fee_with_gst = allocations['trust_facilities_fee'].gross_amount;
                }
                if (allocations['hostel_fee'] || allocations['hostel_security']) {
                    currentFeeConfig.hostel_fee_with_gst = (allocations['hostel_security'] ? allocations['hostel_security'].gross_amount : 0) + (allocations['hostel_fee'] ? allocations['hostel_fee'].gross_amount : 0);
                }
                if (allocations['transport_fee']) {
                    currentFeeConfig.transport_fee_with_gst = allocations['transport_fee'].gross_amount;
                }

                if (t2Alloc.waived_amount > 0) {
                    scholarshipText = `<div class="small text-success mt-1">Total Waivers/Scholarship: -\u20B9${formatCurrency(t2Alloc.waived_amount)}</div>`;
                    hasDeductions = true;
                }
            } else {
                // Fallback for UI if allocation missing
                const t2Base = parseFloat(feeConfig.tuition_fee_part2) || 0;
                originalAmount = t2Base * 1.18;
                finalAmount = originalAmount;
            }

            // Define fee components using the pre-calculated allocations if available
            const feeComponents = [
                {
                    key: 'school_fee',
                    checkId: 'check_school_fee',
                    amtId: 'amt_school_fee',
                    amount: getAlloc('school_fee') ? getAlloc('school_fee').pending_amount : (parseFloat(feeConfig.school_fee) || 0)
                },
                {
                    key: 'trust_facilities_fee',
                    checkId: 'check_trust_facilities_fee',
                    amtId: 'amt_trust_facilities_fee',
                    amount: getAlloc('trust_facilities_fee') ? getAlloc('trust_facilities_fee').pending_amount : (parseFloat(feeConfig.trust_facilities_fee) || 0)
                },
                {
                    key: 'tuition_fee_part1',
                    checkId: 'check_tuition_fee_part1',
                    amtId: 'amt_tuition_fee_part1',
                    // Note: helper might put Token Fee here
                    amount: getAlloc('tuition_fee_part1') ? getAlloc('tuition_fee_part1').pending_amount : (getAlloc('token_fee') ? getAlloc('token_fee').pending_amount : (parseFloat(feeConfig.tuition_fee_part1) * 1.18 || 0)),
                    hasGST: true
                },
                {
                    key: 'tuition_fee_part2',
                    checkId: 'check_tuition_fee_part2',
                    amtId: 'amt_tuition_fee_part2',
                    amount: t2Alloc ? t2Alloc.pending_amount : finalAmount,
                    originalAmount: originalAmount,
                    isTuitionPart2: true,
                    scholarshipHtml: scholarshipText,
                    discountHtml: discountText,
                    hasDeductions: hasDeductions,
                    hasGST: true
                },
                {
                    key: 'hostel_fee',
                    checkId: 'check_hostel_fee',
                    amtId: 'amt_hostel_fee',
                    // Prefer hostel_security allocation when present so UI reflects security deposit properly
                    amount: (getAlloc('hostel_security') ? getAlloc('hostel_security').pending_amount : (getAlloc('hostel_fee') ? getAlloc('hostel_fee').pending_amount : (parseFloat(feeConfig.hostel_fee) || 0)))
                },
                {
                    key: 'hostel_cash_fee',
                    checkId: 'check_hostel_cash_fee',
                    amtId: 'amt_hostel_cash_fee',
                    amount: getAlloc('hostel_cash_fee') ? getAlloc('hostel_cash_fee').pending_amount : (parseFloat(feeConfig.hostel_cash_fee) || 0)
                },
                {
                    key: 'transport_fee',
                    checkId: 'check_transport_fee',
                    amtId: 'amt_transport_fee',
                    amount: getAlloc('transport_fee') ? getAlloc('transport_fee').pending_amount : (parseFloat(feeConfig.transport_fee) || 0)
                }
            ];

            // Reset all checkboxes first, but remember state of Tuition Part 2 if in direct collection mode
            const t2Checked = $('#check_tuition_fee_part2').is(':checked');
            const isWithoutGst = $('#is_without_gst').is(':checked');

            $('.payment-type-checkbox').prop('checked', false).prop('disabled', false);
            $('.custom-checkbox-card').removeClass('selected disabled');
            $('.enable-fee-container, .hostel-toggle-container, .disable-fee-container').remove(); // Clear all dynamic buttons

            // Restore Tuition Part 2 selection if we're in direct collection mode
            if (isWithoutGst && t2Checked) {
                $('#check_tuition_fee_part2').prop('checked', true).closest('.custom-checkbox-card').addClass('selected');
            }

            // Reset label content for Tuition Part 2 (remove injected scholarship info)
            // We need to find the label *text* element specifically if we want to be clean, 
            // but re-creating the inner HTML of the label is easier based on current structure.
            // The structure is: label > span.fee-type-name, span.fee-type-amount, small...

            // Actually, the simplest way is to update the SPECIFIC spans we target.
            // Let's reset the Tuition Part 2 Name to default first to clear old injections
            $('#check_tuition_fee_part2').siblings('label').find('.fee-type-name').html('Tuition Fee Part 2');


            // Update each fee component
            feeComponents.forEach(function (comp) {
                const $checkbox = $('#' + comp.checkId);
                const $amtDisplay = $('#' + comp.amtId);
                const $card = $checkbox.closest('.custom-checkbox-card');
                const $label = $checkbox.siblings('label');
                const $nameDisplay = $label.find('.fee-type-name');

                // hostel_cash_fee: only show if allocation exists and security deposit is fully paid
                // OR if Full Fee toggle is enabled
                if (comp.key === 'hostel_cash_fee') {
                    const cashAlloc = getAlloc('hostel_cash_fee');
                    const secAlloc = getAlloc('hostel_security');
                    const isFullToggled = $('#hostel_fee_toggle').is(':checked');

                    if (!cashAlloc || (secAlloc && secAlloc.pending_amount > 0 && !isFullToggled)) {
                        $('#hostel_cash_fee_col').hide();
                        $card.hide();
                        $checkbox.prop('checked', false).prop('disabled', true);
                        return; // skip further processing for this component
                    }
                    $('#hostel_cash_fee_col').show();
                    $card.show();
                }

                // Check if this fee has been paid using the calculated allocations
                let allocation = null;
                if (comp.key === 'hostel_fee') {
                    const secAlloc = getAlloc('hostel_security');
                    const feeAlloc = getAlloc('hostel_fee');
                    if (feeAlloc && secAlloc) {
                        allocation = {
                            gross_amount: (parseFloat(feeAlloc.gross_amount) || 0) + (parseFloat(secAlloc.gross_amount) || 0),
                            paid_amount: (parseFloat(feeAlloc.paid_amount) || 0) + (parseFloat(secAlloc.paid_amount) || 0),
                            waived_amount: (parseFloat(feeAlloc.waived_amount) || 0) + (parseFloat(secAlloc.waived_amount) || 0)
                        };
                        allocation.pending_amount = Math.max(0, allocation.gross_amount - allocation.paid_amount - allocation.waived_amount);
                    } else if (feeAlloc) {
                        allocation = feeAlloc;
                    } else if (secAlloc) {
                        allocation = secAlloc;
                    } else {
                        allocation = null;
                    }
                } else {
                    allocation = getAlloc(comp.key);
                }
                let isFullyPaid = false;

                // Normalize allocation object: if allocation exists but missing paid/pending, derive from paidFees
                if (allocation) {
                    // Ensure numeric fields exist
                    allocation.paid_amount = parseFloat(allocation.paid_amount || 0);
                    allocation.waived_amount = parseFloat(allocation.waived_amount || 0);
                    allocation.pending_amount = parseFloat(allocation.pending_amount || (allocation.gross_amount - allocation.paid_amount - allocation.waived_amount) || 0);

                    // If backend returned no paid_amount for hostel_security but paidFees contains it, use that
                    if ((comp.key === 'hostel_fee') && (!allocation.paid_amount || allocation.paid_amount === 0)) {
                        const pf = (paidFees && (paidFees['hostel_security'] ?? paidFees['hostel_fee'])) ? parseFloat(paidFees['hostel_security'] ?? paidFees['hostel_fee']) : 0;
                        if (pf > 0) {
                            allocation.paid_amount = pf;
                            allocation.pending_amount = Math.max(0, allocation.gross_amount - allocation.paid_amount - allocation.waived_amount);
                        }
                    }

                    isFullyPaid = (Math.round(allocation.pending_amount) <= 0);
                } else if (comp.key === 'tuition_fee_part1') {
                    // Special case: check token_fee allocation if part1 missing
                    const tokenAlloc = getAlloc('token_fee');
                    if (tokenAlloc) isFullyPaid = (tokenAlloc.pending_amount <= 0);
                }

                // MODIFIED: For Hostel Fee, strictly check if FULL fee is paid before marking as complete
                const isHostelPartial = (comp.key === 'hostel_fee' &&
                    allocation && allocation.paid_amount > 0 &&
                    allocation.pending_amount > 0);

                if (isWithoutGst && (comp.key === 'school_fee' || comp.key === 'trust_facilities_fee' || comp.key === 'other')) {
                    $checkbox.prop('disabled', true).prop('checked', false);
                    $card.addClass('disabled');
                    return;
                }

                if (isFullyPaid && !isHostelPartial) {
                    // Fee already paid (and for Hostel, fully paid)
                    $checkbox.prop('disabled', true).prop('checked', false);
                    $card.addClass('disabled');
                    $amtDisplay.html('<span class="badge bg-success"><i class="fas fa-check"></i> PAID</span>');

                    // Even if paid, show the scholarship info for reference if it's Tuition Part 2
                    if (comp.isTuitionPart2 && comp.hasDeductions) {
                        let nameHtml = 'Tuition Fee Part 2';
                        nameHtml += comp.scholarshipHtml;
                        nameHtml += comp.discountHtml;
                        // Check if already applied content
                        if ($nameDisplay.html() !== nameHtml) {
                            $nameDisplay.html(nameHtml);
                        }
                    }

                } else if (Math.round(comp.amount) > 0 || isHostelPartial) { // Allow logic if partial hostel even if comp.amount might be confused
                    // Fee not paid and amount > 0 OR Partial Hostel

                    // HOSTEL FEE LOGIC with TOGGLE
                    if (comp.key === 'hostel_fee') {

                        // Get configurations (handling defaults if undefined)
                        const fullFee = currentFeeConfig.hostel_full_fee || 0;
                        const secDeposit = currentFeeConfig.hostel_security_deposit || currentFeeConfig.potential_hostel_fee || 0;
                        const paidHostel = (paidFees && (paidFees['hostel_security'] ?? paidFees['hostel_fee']))
                            ? parseFloat(paidFees['hostel_security'] ?? paidFees['hostel_fee'])
                            : 0;

                        // Calculate Balances
                        let balanceFull = Math.max(0, fullFee - paidHostel);
                        let balanceSec = Math.max(0, secDeposit - paidHostel);

                        // Determine Current Mode based on comp.amount (which reflects current config)
                        // We consider it "Full Fee Mode" if amount matches balanceFull exactly
                        let isFullMode = (Math.abs(comp.amount - balanceFull) < 1 && balanceFull !== balanceSec && balanceFull > 0);

                        // Initial Display Amount: Respect the mode we detected
                        let initialAmount = isFullMode ? balanceFull : balanceSec;

                        // If Security is fully paid (balanceSec is 0), and we act as if it's default (not full mode),
                        // we show 0 and "Security Paid". 


                        $amtDisplay.text('\u20B9' + formatCurrency(initialAmount));
                        $checkbox.data('amount', initialAmount);

                        // Auto-uncheck if balance is 0 initially?
                        if (initialAmount <= 0) {
                            $checkbox.prop('checked', false);
                            $amtDisplay.html('<span class="text-success small"><i class="fas fa-check-circle"></i> Security Paid</span>');
                        }

                        // Determine Current Mode
                        // If paidHostel > 0, we can assume they might want to complete the payment?
                        // Let's stick to Toggle State.

                        // Render Toggle Switch
                        if (fullFee > secDeposit) {
                            $('.hostel-toggle-container').remove();

                            const toggleHtml = `
                            <div class="hostel-toggle-container mt-2">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="hostel_fee_toggle" ${isFullMode ? 'checked' : ''}>
                                    <label class="form-check-label small" for="hostel_fee_toggle">Pay Full Fee</label>
                                </div>
                                <div class="small text-muted fst-italic mt-1" id="hostel_toggle_desc">
                                    ${isFullMode ?
                                    `Paying Full Balance (Total: \u20B9${formatCurrency(fullFee)})` + (paidHostel > 0 ? ` - Paid: \u20B9${formatCurrency(paidHostel)}` : '') :
                                    (initialAmount <= 0 ? 'Security Deposit Paid' : 'Paying Security Deposit Only')}
                                </div>
                            </div>
                         `;
                            $card.after(toggleHtml);

                            // Toggle Handler
                            $('#hostel_fee_toggle').on('change', function () {
                                const isChecked = $(this).is(':checked');
                                let newAmount = 0;
                                let cashAmount = 0;

                                if (isChecked) {
                                    // Split Logic: Receiptable = Sec Pending + Threshold
                                    const threshold = parseFloat(currentFeeConfig.hostel_split_threshold || 0);
                                    const receiptableLimit = balanceSec + threshold;

                                    newAmount = Math.min(balanceFull, receiptableLimit);
                                    cashAmount = Math.max(0, balanceFull - newAmount);

                                    let desc = 'Paying Full Balance (Total: \u20B9' + formatCurrency(fullFee) + ')';
                                    if (paidHostel > 0) desc += ` - Paid: \u20B9${formatCurrency(paidHostel)}`;
                                    $('#hostel_toggle_desc').text(desc);

                                    // Update checkboxes
                                    $checkbox.prop('checked', true).data('amount', newAmount);
                                    $card.addClass('selected');
                                    $amtDisplay.text('\u20B9' + formatCurrency(newAmount));

                                    // Update Hostel Cash Fee (if exists)
                                    const $cashCheckbox = $('#check_hostel_cash_fee');
                                    if ($cashCheckbox.length > 0) {
                                        if (cashAmount > 0) {
                                            $cashCheckbox.prop('checked', true).data('amount', cashAmount);
                                            $cashCheckbox.closest('.fee-card').addClass('selected');
                                            $('#amt_hostel_cash_fee').text('\u20B9' + formatCurrency(cashAmount));
                                            $('#hostel_cash_fee_col').show();
                                        } else {
                                            $cashCheckbox.prop('checked', false).data('amount', 0);
                                            $cashCheckbox.closest('.fee-card').removeClass('selected');
                                            $('#amt_hostel_cash_fee').text('\u20B90');
                                            // Hide if security still pending or no amount
                                            const secAlloc = getAlloc('hostel_security') || getAlloc('hostel_fee');
                                            if (!secAlloc || secAlloc.pending_amount > 0) {
                                                $('#hostel_cash_fee_col').hide();
                                            }
                                        }
                                    }

                                } else {
                                    newAmount = balanceSec;
                                    $('#hostel_toggle_desc').text(newAmount <= 0 ? 'Security Deposit Paid' : 'Paying Security Deposit Only');

                                    if (newAmount <= 0) {
                                        $checkbox.prop('checked', false);
                                        $card.removeClass('selected');
                                        $amtDisplay.html('<span class="text-success small"><i class="fas fa-check-circle"></i> Security Paid</span>');
                                    } else {
                                        $amtDisplay.text('\u20B9' + formatCurrency(newAmount));
                                    }

                                    // Update checkbox data
                                    $checkbox.data('amount', newAmount);

                                    // Uncheck/Hide Hostel Cash Fee
                                    const $cashCheckbox = $('#check_hostel_cash_fee');
                                    if ($cashCheckbox.length > 0) {
                                        $cashCheckbox.prop('checked', false).data('amount', 0);
                                        $cashCheckbox.closest('.fee-card').removeClass('selected');
                                        $('#amt_hostel_cash_fee').text('\u20B90');
                                        // Re-run standard visibility check
                                        const secAlloc = getAlloc('hostel_security') || getAlloc('hostel_fee');
                                        if (secAlloc && secAlloc.pending_amount > 0) {
                                            $('#hostel_cash_fee_col').hide();
                                        }
                                    }
                                }

                                // Update Config
                                currentFeeConfig.hostel_fee = newAmount;

                                calculateTotalAmount();
                            });
                        }

                    } else if (comp.key === 'transport_fee') {
                        // TRANSPORT FEE LOGIC for Monthly Collection
                        const timeline = currentFeeConfig.transport_collection_timeline || 'Term-wise';
                        const monthlyBase = parseFloat(currentFeeConfig.transport_monthly_rate || 0);
                        const gstRate = parseFloat(currentFeeConfig.transport_gst_rate || 0);
                        const monthlyRate = Math.round(monthlyBase * (1 + gstRate / 100));

                        if (monthlyRate > 0) {
                            $('.transport-months-container').remove();

                            const totalPending = comp.amount;
                            const maxMonthsAvailable = Math.max(1, Math.ceil(totalPending / monthlyRate));
                            const options = [];
                            for (let i = 1; i <= maxMonthsAvailable; i++) {
                                options.push(`<option value="${i}">${i} Month${i > 1 ? 's' : ''}</option>`);
                            }

                            const monthsHtml = `
                                <div class="transport-months-container mt-2">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-light text-muted small"><i class="fas fa-calendar-alt me-1"></i> Months</span>
                                        <select class="form-select form-select-sm" id="transport_months_selector">
                                            ${options.join('')}
                                        </select>
                                    </div>
                                    <div class="small text-muted mt-1">
                                        Rate: \u20B9${formatCurrency(monthlyRate)} / month
                                    </div>
                                </div>
                            `;
                            $card.after(monthsHtml);

                            // Initial state: 1 month or remaining balance if less
                            const initialAmount = Math.min(monthlyRate, totalPending);
                            $amtDisplay.text('\u20B9' + formatCurrency(initialAmount));
                            $checkbox.data('amount', initialAmount);

                            // Handler
                            $('#transport_months_selector').on('change', function () {
                                const m = parseInt($(this).val());
                                let amt = m * monthlyRate;
                                // If selecting max available, use the actual pending to handle any rounding/remainders
                                if (m === maxMonthsAvailable) {
                                    amt = totalPending;
                                }
                                $amtDisplay.text('\u20B9' + formatCurrency(amt));
                                $checkbox.data('amount', amt);
                                calculateTotalAmount();
                            });

                        } else {
                            // Standard Display for other timelines
                            $amtDisplay.text('\u20B9' + formatCurrency(comp.amount));
                            $checkbox.data('amount', comp.amount);
                        }

                    } else if (comp.isTuitionPart2 && comp.hasDeductions) {
                        // Special display for Tuition Part 2 with Scholarship

                        // 1. Update Name Section (Left side)
                        let nameHtml = 'Tuition Fee Part 2';
                        nameHtml += comp.scholarshipHtml;
                        nameHtml += comp.discountHtml;
                        $nameDisplay.html(nameHtml);

                        // 2. Update Amount Section (Right side)
                        const withoutGst = $('#is_without_gst').is(':checked');
                        let base = comp.amount / 1.18;
                        let gst = comp.amount - base;
                        let displayAmount = withoutGst ? base : comp.amount;

                        let amountHtml = `\u20B9${formatCurrency(displayAmount)}`;
                        if (withoutGst) {
                            // No extra label
                        } else {
                            amountHtml += `<div class="text-info small fw-normal mt-1" style="font-size: 0.7rem;">\u20B9${formatCurrency(base)} + \u20B9${formatCurrency(gst)} GST</div>`;
                        }
                        amountHtml += `<div class="text-muted small text-decoration-line-through fw-normal mt-1">\u20B9${formatCurrency(comp.originalAmount)}</div>`;
                        $amtDisplay.html(amountHtml);

                        // Store the actual payment amount in data attribute for calculation
                        $checkbox.data('amount', displayAmount);

                    } else if (comp.hasGST) {
                        // Display GST Breakdown for other GST components like Part 1 & Part 2 (without deductions)
                        const withoutGst = $('#is_without_gst').is(':checked');
                        let base = comp.amount / 1.18;
                        let gst = comp.amount - base;

                        let displayAmount = comp.amount;
                        if (withoutGst && comp.isTuitionPart2) {
                            displayAmount = base;
                        }

                        let amountHtml = `\u20B9${formatCurrency(displayAmount)}`;
                        if (withoutGst && comp.isTuitionPart2) {
                            // No extra label needed
                        } else {
                            amountHtml += `<div class="text-info small fw-normal mt-1" style="font-size: 0.7rem;">\u20B9${formatCurrency(base)} + \u20B9${formatCurrency(gst)} GST</div>`;
                        }
                        $amtDisplay.html(amountHtml);

                        $checkbox.data('amount', displayAmount);
                    } else {
                        // Standard Display
                        $amtDisplay.text('\u20B9' + formatCurrency(comp.amount));
                        $checkbox.data('amount', comp.amount); // Store amount
                    }

                    // DISABLE LOGIC: Check if this fee was originally disabled (0) and is now enabled
                    // Only for Hostel and Transport fees to avoid messing with core fees
                    if ((comp.key === 'hostel_fee' || comp.key === 'transport_fee') &&
                        initialFeeConfig && (parseFloat(initialFeeConfig[comp.key]) || 0) === 0) {

                        $('.disable-fee-container-' + comp.key).remove(); // Clear old

                        const disableHtml = `
                        <div class="disable-fee-container disable-fee-container-${comp.key} mt-2 text-center">
                            <button type="button" class="btn btn-danger btn-sm w-100 disable-fee-link shadow-sm border-0" data-type="${comp.key}">
                                <i class="fas fa-times-circle me-1"></i> Disable ${feeLabels[comp.key] || 'Fee'}
                            </button>
                        </div>
                    `;
                        $card.after(disableHtml);

                        // Bind Click
                        $card.parent().find('.disable-fee-link').last().on('click', function (e) {
                            e.preventDefault();
                            const type = $(this).data('type');

                            // Confirm before disabling? Maybe mild confirmation or just do it.
                            // Let's just do it for speed, it's reversible.

                            if (type === 'hostel_fee') currentFeeConfig.hostel_fee = 0;
                            if (type === 'transport_fee') currentFeeConfig.transport_fee = 0;

                            updateFeeCheckboxes(currentFeeConfig, currentFeeConfig.paid_fees);

                            showToast('info', 'Hostel Fee', (feeLabels[type] || 'Fee') + ' Disabled');

                        });
                    }

                } else {
                    // Fee amount is 0

                    // CHECK: Is this Hostel OR Transport Fee and can it be enabled?
                    let canEnable = false;
                    let potentialAmount = 0;
                    let enableLabel = '';

                    if (comp.key === 'hostel_fee' && (currentFeeConfig.hostel_security_deposit > 0 || currentFeeConfig.potential_hostel_fee > 0)) {
                        canEnable = true;
                        // Default to Security Deposit when enabling
                        potentialAmount = currentFeeConfig.hostel_security_deposit || currentFeeConfig.potential_hostel_fee;
                        enableLabel = 'Hostel Fee';

                        // Logic check: If Security Deposit is fully paid, maybe we should enable Full Fee directly?
                        const paidHostel = (paidFees && paidFees['hostel_fee']) ? parseFloat(paidFees['hostel_fee']) : 0;
                        if (paidHostel >= potentialAmount) {
                            // Security Deposit Paid -> Enable Balance of Full Fee
                            const fullFee = currentFeeConfig.hostel_full_fee || 0;
                            const balanceFull = Math.max(0, fullFee - paidHostel);
                            if (balanceFull > 0) {
                                potentialAmount = balanceFull; // Set to Balance
                            }
                        }

                    } else if (comp.key === 'transport_fee' && currentFeeConfig.potential_transport_fee > 0) {
                        canEnable = true;
                        potentialAmount = currentFeeConfig.potential_transport_fee;
                        enableLabel = 'Transport Fee';
                    }

                    if (canEnable) {
                        $checkbox.prop('disabled', true);
                        $card.addClass('disabled');
                        $amtDisplay.text('\u20B90'); // Keep standard display
                        $checkbox.data('amount', 0);

                        // Inject Enable Button OUTSIDE the card
                        const btnHtml = `
                        <div class="enable-fee-container mt-2">
                            <button type="button" class="btn btn-outline-primary btn-sm w-100 enable-fee-link shadow-sm" data-type="${comp.key}" data-amount="${potentialAmount}">
                                <i class="fas fa-plus-circle me-1"></i> Enable ${enableLabel}
                            </button>
                        </div>
                     `;
                        $card.after(btnHtml); // Append after card

                        // Bind click event (delegated or direct)
                        $card.parent().find('.enable-fee-link').last().on('click', function (e) {
                            e.preventDefault();
                            // No propagation needed as it's outside the card

                            const type = $(this).data('type');
                            const amount = parseFloat($(this).data('amount'));

                            // Update config to enable fee
                            if (type === 'hostel_fee') {
                                currentFeeConfig.hostel_fee = amount;
                            } else if (type === 'transport_fee') {
                                currentFeeConfig.transport_fee = amount;
                            }

                            // Refresh UI
                            updateFeeCheckboxes(currentFeeConfig, currentFeeConfig.paid_fees);
                            applyTokenFeeRestrictions();

                            // Optional: Notify user
                            showToast('success', 'Hostel Fee', enableLabel + ' Enabled');

                        });

                    } else {
                        // Standard disabled state
                        $checkbox.prop('disabled', true);
                        $card.addClass('disabled');
                        $amtDisplay.text('\u20B90');
                        $checkbox.data('amount', 0);
                    }
                }
            });

            calculateTotalAmount();
        }

        // Reset fee checkboxes
        function resetFeeCheckboxes() {
            currentFeeConfig = null;
            $('#amt_school_fee').text('\u20B90');
            $('#amt_trust_facilities_fee').text('\u20B90');
            $('#amt_tuition_fee_part1').text('\u20B90');
            $('#amt_tuition_fee_part2').text('\u20B90');
            $('#amt_hostel_fee').text('\u20B90');

            $('#hostel_cash_fee_col').hide();

            // Uncheck and reset all
            $('.payment-type-checkbox').prop('checked', false);
            $('.smart-fee-check').prop('checked', false);
            $('.custom-checkbox-card').removeClass('selected');
            $('#other_amount_section').hide();

            // Reset summary
            $('#summary_placeholder').show();
            $('#summary_content').hide();

            calculateTotalAmount();
        }

        // Global variable to store current payment components
        var currentPaymentBreakdown = [];

        // Function to render multiple cheque inputs for each selected fee
        function renderChequeInputs() {
            if ($('#payment_mode').val() !== 'cheque') return;

            const $container = $('#cheque_details_container');

            // Only re-render if selection changed to avoid clearing user input unnecessarily
            // but for simplicity and safety of this task, we will re-render

            let html = '<h6 class="mb-3 text-primary fw-bold"><i class="fas fa-money-check-alt me-2"></i>Cheque Details for Each Component</h6>';

            if (currentPaymentBreakdown && currentPaymentBreakdown.length > 0) {
                currentPaymentBreakdown.forEach((item, index) => {
                    html += `
                        <div class="card shadow-sm border-0 mb-3 cheque-input-card" data-component="${item.key}">
                            <div class="card-header bg-light py-2">
                                <span class="fw-bold small text-dark">${item.name} (\u20B9${formatCurrency(item.amount)})</span>
                            </div>
                            <div class="card-body p-3">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group mb-0">
                                            <label class="form-label small">Cheque Number <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-sm cheque-no" placeholder="No." required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group mb-0">
                                            <label class="form-label small">Bank Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-sm bank-name" placeholder="Bank" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group mb-0">
                                            <label class="form-label small">Cheque Date <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control form-control-sm cheque-date" max="${new Date().toISOString().split('T')[0]}" required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                });
            } else {
                html += '<div class="alert alert-info py-2 small">Please select fee components first.</div>';
            }

            $container.html(html);
        }

        function calculateTotalAmount() {
            let total = 0;
            let breakdown = [];

            // Enable/disable discount fields based on selection
            const isRelevantFeeChecked = $('#check_school_fee').is(':checked') ||
                $('#check_trust_facilities_fee').is(':checked') ||
                $('#check_tuition_fee_part2').is(':checked');

            if (isRelevantFeeChecked) {
                $('#discount_type').prop('disabled', false);

                // UX Improvement: Disable Fixed/Percentage if not Tuition Part 2
                const isTuition2Checked = $('#check_tuition_fee_part2').is(':checked');
                $('#discount_type option[value="fixed"]').prop('disabled', !isTuition2Checked);
                $('#discount_type option[value="percentage"]').prop('disabled', !isTuition2Checked);
            } else {
                $('#discount_type').prop('disabled', true).val('');
                $('#discount_value').prop('disabled', true).val('');
                $('#discount_reason').prop('disabled', true).val('');
                $('#discount_summary').hide();
                $('#smart_options_container').hide();
                // Reset options state
                $('#discount_type option').prop('disabled', false);
            }

            // Calculate discount first
            let discountAmount = 0;
            const discountType = $('#discount_type').val();

            // --- Smart Calculation Logic ---
            if (discountType === 'smart' && currentFeeConfig) {
                let smartTotal = 0;

                // Sum selected fees from CONFIG (Global Waiver is based on total fees, not necessarily what's being paid right now, 
                // but we usually want to waive the *outstanding* amount. 
                // However, the requested logic is "Global Waiver" - usually imply specific heads. 
                // We'll use the FULL configured amount for calculation reference.

                waivedKeys.forEach(key => {
                    let configVal = 0;
                    if (key === 'school_fee') configVal = parseFloat(currentFeeConfig.school_fee_with_gst || currentFeeConfig.school_fee || 0);
                    else if (key === 'trust_facilities_fee') configVal = parseFloat(currentFeeConfig.trust_facilities_fee_with_gst || currentFeeConfig.trust_facilities_fee || 0);
                    else if (key === 'tuition_fee_part1') configVal = parseFloat(currentFeeConfig.tuition_fee_part1_with_gst || 0);
                    else if (key === 'tuition_fee_part2') {
                        if ($('#is_without_gst').is(':checked')) {
                            configVal = parseFloat(currentFeeConfig.tuition_fee_part2 || 0);
                        } else {
                            configVal = parseFloat(currentFeeConfig.tuition_fee_part2_with_gst || 0);
                        }
                    }
                    else if (key === 'hostel_fee') configVal = parseFloat(currentFeeConfig.hostel_fee_with_gst || currentFeeConfig.hostel_full_fee || currentFeeConfig.hostel_fee || 0);
                    else if (key === 'transport_fee') configVal = parseFloat(currentFeeConfig.transport_fee_with_gst || currentFeeConfig.potential_transport_fee || currentFeeConfig.transport_fee || 0);

                    smartTotal += configVal;
                });

                const smartMode = $('#smart_waiver_mode').val();
                const smartValue = parseFloat($('#smart_waiver_value').val()) || 0;
                let calculatedDiscount = 0;

                if (smartMode === 'percentage') {
                    calculatedDiscount = (smartTotal * smartValue) / 100;
                } else {
                    calculatedDiscount = Math.min(smartValue, smartTotal);
                }

                // Set logic value and update UI
                $('#discount_value').val(calculatedDiscount.toFixed(0));
            }
            // -------------------------------

            const discountValue = parseFloat($('#discount_value').val()) || 0;

            if (currentFeeConfig) {
                if ($('#check_school_fee').is(':checked')) {
                    const amt = helperGetFeeAmount('check_school_fee');
                    total += amt;
                    breakdown.push({
                        key: 'school_fee',
                        name: feeLabels['school_fee'],
                        amount: amt,
                        original: amt
                    });
                }
                if ($('#check_trust_facilities_fee').is(':checked')) {
                    const amt = helperGetFeeAmount('check_trust_facilities_fee');
                    total += amt;
                    breakdown.push({
                        key: 'trust_facilities_fee',
                        name: feeLabels['trust_facilities_fee'],
                        amount: amt,
                        original: amt
                    });
                }
                if ($('#check_tuition_fee_part1').is(':checked')) {
                    const amt = helperGetFeeAmount('check_tuition_fee_part1');
                    total += amt;
                    breakdown.push({
                        key: 'tuition_fee_part1',
                        name: feeLabels['tuition_fee_part1'],
                        amount: amt,
                        original: amt
                    });
                }
                if ($('#check_tuition_fee_part2').is(':checked')) {
                    const amt = helperGetFeeAmount('check_tuition_fee_part2');
                    total += amt;
                    let label = feeLabels['tuition_fee_part2'];
                    if ($('#is_without_gst').is(':checked')) {
                        // No extra label
                    } else {
                        label += ' (incl. GST)';
                    }
                    breakdown.push({
                        key: 'tuition_fee_part2',
                        name: label,
                        amount: amt,
                        original: amt,
                        isTuitionPart2: true
                    });
                }
                if ($('#check_hostel_fee').is(':checked')) {
                    const amt = helperGetFeeAmount('check_hostel_fee');
                    total += amt;

                    // Determine if this is security deposit or full fee based on toggle state
                    const isFullFeeToggled = $('#hostel_fee_toggle').is(':checked');
                    const feeComponentKey = isFullFeeToggled ? 'hostel_fee' : 'hostel_security';
                    const feeComponentName = isFullFeeToggled ? feeLabels['hostel_fee'] : feeLabels['hostel_security'];

                    breakdown.push({
                        key: feeComponentKey,
                        name: feeComponentName,
                        amount: amt,
                        original: amt
                    });
                }
                if ($('#check_hostel_cash_fee').is(':checked')) {
                    const amt = helperGetFeeAmount('check_hostel_cash_fee');
                    total += amt;
                    breakdown.push({
                        key: 'hostel_cash_fee',
                        name: feeLabels['hostel_cash_fee'] || 'Hostel Cash Fee',
                        amount: amt,
                        original: amt
                    });
                }
                if ($('#check_transport_fee').is(':checked')) {
                    const amt = helperGetFeeAmount('check_transport_fee');
                    total += amt;
                    breakdown.push({
                        key: 'transport_fee',
                        name: feeLabels['transport_fee'] || 'Transport Fee',
                        amount: amt,
                        original: amt
                    });
                }
            }

            // Add other fee amount
            if ($('#check_other').is(':checked')) {
                const otherAmt = parseFloat($('#other_amount').val()) || 0;
                total += otherAmt;
                if (otherAmt > 0) {
                    const desc = $('#other_description').val() || 'Other';
                    breakdown.push({
                        key: 'other',
                        name: desc,
                        amount: otherAmt,
                        original: otherAmt
                    });
                }
            }

            // Calculate discount and apply to Tuition Part 2
            if (discountType && discountValue > 0 && total > 0) {
                if (discountType === 'percentage') {
                    discountAmount = (total * discountValue) / 100;
                } else if (discountType === 'fixed' || discountType === 'smart') {
                    discountAmount = discountValue;
                }

                // Ensure discount doesn't exceed total
                if (discountAmount > total) {
                    discountAmount = total;
                    showToast('warning', 'Discount Adjusted', 'Discount amount cannot exceed total fee amount.');

                }

                // Distribute discount across fee breakdown items
                let remainingDiscount = discountAmount;

                // Get list of keys selected for waiver
                const waivedKeys = [];
                if (discountType === 'smart') {
                    $('.smart-fee-check:checked').each(function () {
                        waivedKeys.push($(this).val());
                    });
                }

                if (discountType === 'smart') {
                    // --- Proportional Logic for Smart Waiver ---
                    const smartMode = $('#smart_waiver_mode').val();
                    const smartValue = parseFloat($('#smart_waiver_value').val()) || 0;

                    let totalSmartDiscount = 0;

                    // Reference smartTotal (Full config amount of selected heads)
                    let smartTotalRef = 0;
                    waivedKeys.forEach(key => {
                        let configVal = 0;
                        if (key === 'school_fee') configVal = parseFloat(currentFeeConfig.school_fee_with_gst || currentFeeConfig.school_fee || 0);
                        else if (key === 'trust_facilities_fee') configVal = parseFloat(currentFeeConfig.trust_facilities_fee_with_gst || currentFeeConfig.trust_facilities_fee || 0);
                        else if (key === 'tuition_fee_part1') configVal = parseFloat(currentFeeConfig.tuition_fee_part1_with_gst || 0);
                        else if (key === 'tuition_fee_part2') configVal = parseFloat(currentFeeConfig.tuition_fee_part2_with_gst || 0);
                        else if (key === 'hostel_fee') configVal = parseFloat(currentFeeConfig.hostel_fee_with_gst || currentFeeConfig.hostel_full_fee || currentFeeConfig.hostel_fee || 0);
                        else if (key === 'transport_fee') configVal = parseFloat(currentFeeConfig.transport_fee_with_gst || currentFeeConfig.potential_transport_fee || currentFeeConfig.transport_fee || 0);

                        smartTotalRef += configVal;
                    });

                    // Determine effective percentage to apply for each head
                    let effectivePercentage = 0;
                    if (smartMode === 'percentage') {
                        effectivePercentage = smartValue;
                    } else if (smartTotalRef > 0) {
                        effectivePercentage = (smartValue / smartTotalRef) * 100;
                    }

                    // Cap percentage at 100
                    if (effectivePercentage > 100) effectivePercentage = 100;

                    breakdown.forEach(item => {
                        if (waivedKeys.includes(item.key) && item.amount > 0) {
                            // Calculate specific deduction for this item based on its config amount
                            // or its current amount? For Global Waiver, it's cleaner to base on what we are paying.
                            // But usually it applies to the total liability.

                            let configAmt = 0;
                            if (item.key === 'school_fee') configAmt = parseFloat(currentFeeConfig.school_fee_with_gst || currentFeeConfig.school_fee || 0);
                            else if (item.key === 'trust_facilities_fee') configAmt = parseFloat(currentFeeConfig.trust_facilities_fee_with_gst || currentFeeConfig.trust_facilities_fee || 0);
                            else if (item.key === 'tuition_fee_part1') configAmt = parseFloat(currentFeeConfig.tuition_fee_part1_with_gst || 0);
                            else if (item.key === 'tuition_fee_part2') configAmt = parseFloat(currentFeeConfig.tuition_fee_part2_with_gst || 0);
                            else if (item.key === 'hostel_fee' || item.key === 'hostel_security') configAmt = parseFloat(currentFeeConfig.hostel_fee_with_gst || currentFeeConfig.hostel_full_fee || currentFeeConfig.hostel_fee || 0);
                            else if (item.key === 'transport_fee') configAmt = parseFloat(currentFeeConfig.transport_fee_with_gst || currentFeeConfig.potential_transport_fee || currentFeeConfig.transport_fee || 0);

                            let deduction = (configAmt * effectivePercentage) / 100;

                            // Round to whole number
                            deduction = Math.round(deduction);

                            // Cap deduction at current item amount (cannot waive more than what is being paid)
                            if (deduction > item.amount) deduction = item.amount;

                            // Apply
                            if (item.original === undefined) item.original = item.amount;
                            item.amount = item.original - deduction;
                            item.discount = (item.discount || 0) + deduction;

                            totalSmartDiscount += deduction;
                        }
                    });

                    // Update the total discount amount to match the actual applied sum
                    discountAmount = totalSmartDiscount;

                    // Update the UI input to reflect the calculated total
                    $('#discount_value').val(discountAmount.toFixed(0));

                } else {
                    // --- Priority Logic for Fixed/Percentage ---
                    // The user wants Fixed/Percentage discounts to apply to Tuition Fee Part 2 first.

                    // Pass 1: Prioritize Tuition Fee Part 2
                    breakdown.forEach(item => {
                        if (item.key === 'tuition_fee_part2' && remainingDiscount > 0 && item.amount > 0) {
                            let deduction = Math.min(item.amount, remainingDiscount);

                            item.original = item.original || item.amount;
                            item.amount = item.original - deduction;
                            item.discount = (item.discount || 0) + deduction;

                            remainingDiscount -= deduction;
                        }
                    });

                    // Pass 2: Apply remaining discount to other components (if any)
                    if (remainingDiscount > 0) {
                        breakdown.forEach(item => {
                            if (item.key !== 'tuition_fee_part2' && remainingDiscount > 0 && item.amount > 0) {
                                let deduction = Math.min(item.amount, remainingDiscount);

                                item.original = item.original || item.amount;
                                item.amount = item.original - deduction;
                                item.discount = (item.discount || 0) + deduction;

                                remainingDiscount -= deduction;
                            }
                        });
                    }
                }

                // Show discount summary
                $('#discount_amount_display').text('\u20B9' + formatCurrency(discountAmount));
                $('#final_amount_display').text('\u20B9' + formatCurrency(total - discountAmount));
                $('#discount_summary').show();
            } else {
                $('#discount_summary').hide();
            }

            // Update total amount field (Final Amount after discount)
            const finalNetPayable = Math.round(total - discountAmount);
            $('#amount').val(finalNetPayable.toFixed(0));

            // Update Summary Card on the right
            $('#summary_total_gross').text('\u20B9' + formatCurrency(total));
            if (discountAmount > 0) {
                $('#summary_discount_amount').text('-\u20B9' + formatCurrency(discountAmount));
                $('#summary_discount_row').attr('style', 'display: flex !important;');
            } else {
                $('#summary_discount_row').attr('style', 'display: none !important;');
            }
            $('#summary_net_payable').text('\u20B9' + formatCurrency(finalNetPayable));

            // Update breakdown display
            if (breakdown.length > 0) {
                let html = '';
                breakdown.forEach(item => {
                    const withoutGst = $('#is_without_gst').is(':checked');
                    if (item.discount && item.discount > 0) {
                        // Show item with discount breakdown
                        let gstInfo = '';
                        if (item.key && item.key.includes('tuition_fee')) {
                            if (withoutGst && item.key === 'tuition_fee_part2') {
                                // No extra label
                            } else {
                                let base = item.amount / 1.18;
                                let gst = item.amount - base;
                                gstInfo = `<div class="text-info small">(\u20B9${formatCurrency(base)} + \u20B9${formatCurrency(gst)} GST)</div>`;
                            }
                        }

                        html += `<li class="mb-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <span class="fw-medium">${item.name}</span>
                                <div class="text-muted small">
                                    <span>\u20B9${formatCurrency(item.original)} - Disc. \u20B9${formatCurrency(item.discount)}</span>
                                </div>
                                ${gstInfo}
                            </div>
                            <strong class="text-success">\u20B9${formatCurrency(item.amount)}</strong>
                        </div>
                    </li>`;
                    } else {
                        let gstInfo = '';
                        if (item.key && item.key.includes('tuition_fee')) {
                            if (withoutGst && item.key === 'tuition_fee_part2') {
                                // No extra label
                            } else {
                                let base = item.amount / 1.18;
                                let gst = item.amount - base;
                                gstInfo = `<div class="text-info small">(\u20B9${formatCurrency(base)} + \u20B9${formatCurrency(gst)} GST)</div>`;
                            }
                        }

                        html += `<li class="d-flex justify-content-between align-items-start mb-2">
                            <div class="flex-grow-1">
                                <span class="fw-medium">${item.name}</span>
                                ${gstInfo}
                            </div>
                            <strong>\u20B9${formatCurrency(item.amount)}</strong>
                        </li>`;
                    }
                });

                $('#fees_breakdown_list').html(html);
                $('#selected_fees_summary').show();
            } else {
                $('#selected_fees_summary').hide();
            }

            // Update global variable
            currentPaymentBreakdown = breakdown;

            // Update cheque inputs if mode is cheque
            if ($('#payment_mode').val() === 'cheque') {
                renderChequeInputs();
            }
        }

        // Helper function to select token fee components
        function selectTokenFeeComponents() {
            // Token fee typically includes Tuition Part 1
            $('#check_tuition_fee_part1').prop('checked', true).trigger('change');
        }

        // Helper function to select all fee components
        function selectAllFeeComponents() {
            $('.payment-type-checkbox').not('#check_other').prop('checked', true);
            $('.custom-checkbox-card').addClass('selected');
            $('#check_other').closest('.custom-checkbox-card').removeClass('selected');
            calculateTotalAmount();
        }

        // Format currency
        function formatCurrency(value) {
            return Math.round(parseFloat(value)).toLocaleString('en-IN', {
                maximumFractionDigits: 0
            });
        }

        // Helper to safely get fee amount from data attribute or fallback
        function helperGetFeeAmount(checkboxId) {
            const val = $('#' + checkboxId).data('amount');
            return parseFloat(val) || 0;
        }
    </script>