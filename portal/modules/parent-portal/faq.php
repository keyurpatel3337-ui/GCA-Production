<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;
require_once PORTAL_GLOBALVARIABLE;

// Ensure parent is logged in
if (!isset($_SESSION['is_parent_login']) || $_SESSION['is_parent_login'] !== true) {
    header('Location: ../../parent-login.php');
    exit;
}

$page_title = "Parent Frequently Asked Questions";
$page_breadcrumb = "FAQ";

include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>

<div class="container-fluid py-4 pb-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-primary py-4 text-white border-0">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-white bg-opacity-25 p-3 me-3">
                            <i class="fas fa-question-circle fa-2x"></i>
                        </div>
                        <div>
                            <h4 class="mb-0 fw-bold">Parent FAQ & Guides</h4>
                            <p class="mb-0 text-white text-opacity-75">Common questions about fees, results, and
                                services</p>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4 p-md-5">

                    <!-- Fee Related FAQ -->
                    <div class="mb-5">
                        <h5 class="fw-bold mb-4 text-primary"><i class="fas fa-money-bill-wave me-2"></i>Fee & Payments
                        </h5>
                        <div class="accordion accordion-flush custom-accordion" id="feeFaq">
                            <div class="accordion-item border-bottom">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed fw-bold py-3" type="button"
                                        data-bs-toggle="collapse" data-bs-target="#fee1">
                                        How can I pay my child's fees online?
                                    </button>
                                </h2>
                                <div id="fee1" class="accordion-collapse collapse" data-bs-parent="#feeFaq">
                                    <div class="accordion-body text-muted">
                                        You can pay fees by clicking on "Gyan Manjari Fees" on your dashboard. It will
                                        show a list of pending installments. You can click "Pay Now" to use our secure
                                        online payment gateway (UPI, Credit/Debit Cards, Net Banking).
                                    </div>
                                </div>
                            </div>
                            <div class="accordion-item border-bottom">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed fw-bold py-3" type="button"
                                        data-bs-toggle="collapse" data-bs-target="#fee2">
                                        Can I pay for multiple children at once?
                                    </button>
                                </h2>
                                <div id="fee2" class="accordion-collapse collapse" data-bs-parent="#feeFaq">
                                    <div class="accordion-body text-muted">
                                        Currently, you need to switch child context from the dashboard and pay their
                                        respective fees individually to ensure receipts are generated correctly for each
                                        student.
                                    </div>
                                </div>
                            </div>
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed fw-bold py-3" type="button"
                                        data-bs-toggle="collapse" data-bs-target="#fee3">
                                        How do I request for a Fee Installment?
                                    </button>
                                </h2>
                                <div id="fee3" class="accordion-collapse collapse" data-bs-parent="#feeFaq">
                                    <div class="accordion-body text-muted">
                                        In the "My Fees" section, if a fee component allows installments, you will see a
                                        "Request Installment" link. Click it, specify the number of installments and the
                                        reason, and our account office will review it.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Academic Related FAQ -->
                    <div class="mb-5">
                        <h5 class="fw-bold mb-4 text-success"><i class="fas fa-graduation-cap me-2"></i>Academic Results
                        </h5>
                        <div class="accordion accordion-flush custom-accordion" id="resultFaq">
                            <div class="accordion-item border-bottom">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed fw-bold py-3" type="button"
                                        data-bs-toggle="collapse" data-bs-target="#res1">
                                        When are test results published?
                                    </button>
                                </h2>
                                <div id="res1" class="accordion-collapse collapse" data-bs-parent="#resultFaq">
                                    <div class="accordion-body text-muted">
                                        Usually, OMR-based test results are published within 24-48 hours of the
                                        examination. You will receive a WhatsApp notification once the results are
                                        uploaded.
                                    </div>
                                </div>
                            </div>
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed fw-bold py-3" type="button"
                                        data-bs-toggle="collapse" data-bs-target="#res2">
                                        How to interpret the "Performance Score"?
                                    </button>
                                </h2>
                                <div id="res2" class="accordion-collapse collapse" data-bs-parent="#resultFaq">
                                    <div class="accordion-body text-muted">
                                        The performance score is a weighted average of theory and practical marks. A
                                        score above 75% is considered "Excellent", 60-75% is "Good", and below 40%
                                        requires immediate attention and counselling.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Support Related -->
                    <div>
                        <h5 class="fw-bold mb-4 text-warning"><i class="fas fa-headset me-2"></i>Contact & Support</h5>
                        <div class="p-4 rounded-4 bg-light">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center mb-3">
                                        <i class="fas fa-phone-alt text-primary me-3"></i>
                                        <div>
                                            <h6 class="mb-0 fw-bold">Admission/Fee Dept.</h6>
                                            <p class="mb-0 small text-muted">+91 90999 51160</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center mb-3">
                                        <i class="fas fa-envelope text-primary me-3"></i>
                                        <div>
                                            <h6 class="mb-0 fw-bold">Academic Support</h6>
                                            <p class="mb-0 small text-muted">support@gyanmanjari.com</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>



<?php include '../../include/footer.php'; ?>