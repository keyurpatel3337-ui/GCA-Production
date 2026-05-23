<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once __DIR__ . '/../../common/api_client.php';
require_once __DIR__ . '/../../../common/helpers/format_helper.php';

// Ensure parent is logged in
if (!isset($_SESSION['is_parent_login']) || $_SESSION['is_parent_login'] !== true) {
    header('Location: ../../parent-login.php');
    exit;
}

$active_student_id = $_SESSION['active_student_id'];
$children = $_SESSION['children'];

// Load dashboard data for active child via API
$api = new APIClient();
$response = $api->get('dashboard/student', ['student_id' => $active_student_id]);

// Extract data from API response
if ($response && isset($response['success']) && $response['success']) {
    $data = $response['data'] ?? [];
    $student_info = $data['student_info'] ?? null;
    $enrollment_info = $data['enrollment_info'] ?? null;
    $stats = $data['stats'] ?? [];

    $pending_appointments = $stats['pending_appointments'] ?? 0;
    $total_tests = $stats['total_tests'] ?? 0;

    // Wallet Balance
    $wallet_api_url = defined('WALLET_API_URL') ? WALLET_API_URL : null;
    $wallet_balance = 0;
    if ($wallet_api_url) {
        $wallet_student_id = $active_student_id;
        if (!empty($enrollment_info['enrollment_no'])) {
            $wallet_student_id = $enrollment_info['enrollment_no'];
        } else {
            try {
                if (!isset($conn)) {
                    require_once DB_CONNECT_FILE;
                }
                if (isset($conn)) {
                    $stmt = $conn->prepare("SELECT enrollment_no FROM tbl_enrolled_students WHERE registration_id = ?");
                    $stmt->execute([$active_student_id]);
                    $en_no = $stmt->fetchColumn();
                    if (!empty($en_no)) {
                        $wallet_student_id = $en_no;
                    }
                }
            } catch (Exception $e) {
                // Fallback to active_student_id
            }
        }

        $ch = curl_init($wallet_api_url . '/balance/check.php?student_id=' . $wallet_student_id);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-API-KEY: ' . (defined('GCA_PORTAL_KEY') ? GCA_PORTAL_KEY : '')]);
        $wallet_res = json_decode(curl_exec($ch), true);
        if ($wallet_res && isset($wallet_res['status']) && $wallet_res['status'] === 'success') {
            $wallet_balance = $wallet_res['data']['balance'];
        }
    }
}

$page_title = "Parent Dashboard";
$page_breadcrumb = "Dashboard";

// Override user_id for sidebar/header to show active student data
$user_id = $active_student_id;
$user_name = $_SESSION['parent_mobile']; // Or parent name if available

include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>

<div class="container-fluid py-4 pb-5">
    <!-- Child Selector -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                        <div>
                            <h4 class="mb-1 fw-bold">Select Child</h4>
                            <p class="text-muted mb-0">Switch between your children to view their details</p>
                        </div>
                        <div class="d-flex gap-2">
                            <?php foreach ($children as $child):
                                $is_active = ($child['id'] == $active_student_id);
                                ?>
                                <a href="switch-child.php?student_id=<?php echo $child['id']; ?>"
                                    class="btn <?php echo $is_active ? 'btn-primary' : 'btn-outline-primary'; ?> rounded-pill px-4">
                                    <i class="fas fa-user-graduate me-2"></i>
                                    <?php echo htmlspecialchars($child['student_name'] ?? ''); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Child Summary -->
    <div class="row g-4 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-gradient-info text-white">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between mb-3">
                        <div class="rounded-circle bg-white bg-opacity-25 p-3">
                            <i class="fas fa-id-card fa-2x"></i>
                        </div>
                    </div>
                    <h6 class="text-white text-opacity-75 mb-1">Active Child</h6>
                    <h3 class="fw-bold mb-0">
                        <?php echo htmlspecialchars($student_info['student_name'] ?? 'N/A'); ?>
                    </h3>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between mb-3">
                        <div class="rounded-circle bg-success bg-opacity-10 text-success p-3">
                            <i class="fas fa-wallet fa-2x"></i>
                        </div>
                        <span class="text-success fw-bold">Live</span>
                    </div>
                    <h6 class="text-muted mb-1">Wallet Balance</h6>
                    <h3 class="fw-bold mb-0">₹
                        <?php echo formatIndianCurrency($wallet_balance); ?>
                    </h3>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between mb-3">
                        <div class="rounded-circle bg-warning bg-opacity-10 text-warning p-3">
                            <i class="fas fa-file-invoice-dollar fa-2x"></i>
                        </div>
                    </div>
                    <?php
                    $fee_summary = $data['fee_summary'] ?? null;
                    $pending_amt = $fee_summary['total_pending'] ?? 0;
                    ?>
                    <h6 class="text-muted mb-1">Pending Fees</h6>
                    <h3 class="fw-bold mb-0 <?php echo $pending_amt > 0 ? 'text-danger' : 'text-success'; ?>">
                        ₹
                        <?php echo formatIndianCurrency($pending_amt); ?>
                    </h3>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between mb-3">
                        <div class="rounded-circle bg-primary bg-opacity-10 text-primary p-3">
                            <i class="fas fa-graduation-cap fa-2x"></i>
                        </div>
                    </div>
                    <h6 class="text-muted mb-1">Tests Taken</h6>
                    <h3 class="fw-bold mb-0">
                        <?php echo $total_tests; ?>
                    </h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Access Links for Parent -->
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-transparent border-0 p-4 pb-0">
                    <h5 class="fw-bold mb-0">Active Child Details -
                        <?php echo htmlspecialchars($student_info['student_name'] ?? ''); ?>
                    </h5>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <a href="../student-portal/my-fees.php"
                                class="d-block p-4 rounded-4 bg-light text-decoration-none transition-hover shadow-hover">
                                <i class="fas fa-money-check-alt fa-2x text-primary mb-3"></i>
                                <h6 class="fw-bold text-dark">Gyan Manjari Fees</h6>
                                <p class="small text-muted mb-0">View all installments and pay fees online for this
                                    child.</p>
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="../student-portal/my-results.php"
                                class="d-block p-4 rounded-4 bg-light text-decoration-none transition-hover shadow-hover">
                                <i class="fas fa-poll-h fa-2x text-success mb-3"></i>
                                <h6 class="fw-bold text-dark">Academic Results</h6>
                                <p class="small text-muted mb-0">Check performance, OMR copies and test scores.</p>
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="../student-portal/hostel-services.php"
                                class="d-block p-4 rounded-4 bg-light text-decoration-none transition-hover shadow-hover">
                                <i class="fas fa-hotel fa-2x text-warning mb-3"></i>
                                <h6 class="fw-bold text-dark">Hostel Services</h6>
                                <p class="small text-muted mb-0">Manage room details, leaves and hostel attendance.</p>
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="../student-portal/profile.php"
                                class="d-block p-4 rounded-4 bg-light text-decoration-none transition-hover shadow-hover">
                                <i class="fas fa-user-circle fa-2x text-secondary mb-3"></i>
                                <h6 class="fw-bold text-dark">Student Profile</h6>
                                <p class="small text-muted mb-0">View registration details and academic records.</p>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 bg-gradient-primary text-white overflow-hidden">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-4">Academic Help</h5>
                    <div class="mb-4">
                        <p class="small text-white text-opacity-75">Need any assistance regarding your child's
                            education?</p>
                        <a href="../student-portal/my-appointments.php" class="btn btn-white btn-sm px-3 rounded-pill">
                            <i class="fas fa-calendar-alt me-2"></i> Book Appointment
                        </a>
                    </div>
                    <hr class="bg-white bg-opacity-25">
                    <div>
                        <p class="small text-white text-opacity-75">Quick Links</p>
                        <ul class="list-unstyled small">
                            <li class="mb-2"><a href="faq.php" class="text-white text-decoration-none"><i
                                        class="fas fa-chevron-right me-2 opacity-50"></i> Fee Structure FAQ</a></li>
                            <li class="mb-2"><a href="faq.php" class="text-white text-decoration-none"><i
                                        class="fas fa-chevron-right me-2 opacity-50"></i> Result Analysis Guide</a></li>
                            <li><a href="faq.php" class="text-white text-decoration-none"><i
                                        class="fas fa-chevron-right me-2 opacity-50"></i> Contact Counselling office</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .bg-gradient-info {
        background: linear-gradient(135deg, #0dcaf0 0%, #0aa2c0 100%);
    }

    .bg-gradient-primary {
        background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%);
    }

    .transition-hover {
        transition: all 0.3s ease;
    }

    .transition-hover:hover {
        transform: translateY(-5px);
        background: #fff !important;
    }

    .shadow-hover:hover {
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1) !important;
    }

    .btn-white {
        background: white;
        color: #4f46e5;
        border: none;
    }

    .btn-white:hover {
        background: #f8fafc;
        color: #3730a3;
    }
</style>

<?php include '../../include/footer.php'; ?>