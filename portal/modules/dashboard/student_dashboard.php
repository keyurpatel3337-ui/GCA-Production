<?php

require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once __DIR__ . '/../../common/api_client.php';
require_once __DIR__ . '/../../../common/helpers/format_helper.php';

// Load dashboard data via API
$api = new APIClient();
$response = $api->get('dashboard/student', ['student_id' => $user_id]);

// Extract data from API response
if ($response && isset($response['success']) && $response['success']) {
    $data = $response['data'] ?? [];
    $student_info = $data['student_info'] ?? null;
    $enrollment_info = $data['enrollment_info'] ?? null;
    $fee_allocated = $data['fee_allocated'] ?? false;
    $stats = $data['stats'] ?? [];
    $recent_results = $data['recent_results'] ?? [];

    // Extract stats
    $pending_appointments = $stats['pending_appointments'] ?? 0;
    $completed_appointments = $stats['completed_appointments'] ?? 0;
    $total_tests = $stats['total_tests'] ?? 0;
    $avg_score = $stats['avg_score'] ?? 0;

    // Fetch Wallet Balance
    $wallet_api_url = defined('WALLET_API_URL') ? WALLET_API_URL : null;
    $wallet_balance = 0;
    if ($wallet_api_url) {
        $wallet_student_id = $user_id;
        if (!empty($enrollment_info['enrollment_no'])) {
            $wallet_student_id = $enrollment_info['enrollment_no'];
        } else {
            try {
                if (!isset($conn)) {
                    require_once DB_CONNECT_FILE;
                }
                if (isset($conn)) {
                    $stmt = $conn->prepare("SELECT enrollment_no FROM tbl_enrolled_students WHERE registration_id = ?");
                    $stmt->execute([$user_id]);
                    $en_no = $stmt->fetchColumn();
                    if (!empty($en_no)) {
                        $wallet_student_id = $en_no;
                    }
                }
            } catch (Exception $e) {
                // Fallback to $user_id
            }
        }

        $ch = curl_init($wallet_api_url . '/balance/check.php?student_id=' . $wallet_student_id);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-API-KEY: ' . (defined('GCA_PORTAL_KEY') ? GCA_PORTAL_KEY : '')
        ]);
        $wallet_res = json_decode(curl_exec($ch), true);
        if ($wallet_res && isset($wallet_res['status']) && $wallet_res['status'] === 'success') {
            $wallet_balance = $wallet_res['data']['balance'];
        }
    }
} else {
    // Fallback to default values if API fails
    $student_info = ['token_fees_paid' => 0];
    $enrollment_info = null;
    $fee_allocated = false;
    $pending_appointments = 0;
    $completed_appointments = 0;
    $total_tests = 0;
    $avg_score = 0;
    $recent_results = [];
}
// Set Page Title
$page_title = "Student Dashboard";
$page_breadcrumb = "Dashboard";

// Check for restricted online payment (Standard 11, Course ID 1 & 2)
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;

$is_restricted_payment = false;
$std_check_res = $dbOps->customSelect("SELECT standard, course_id FROM tbl_gm_std_registration WHERE id = ?", [$user_id]);
$std_check = !empty($std_check_res) ? $std_check_res[0] : null;
if ($std_check) {
    $std_val = isset($std_check['standard']) ? intval($std_check['standard']) : 0;
    $course_val = isset($std_check['course_id']) ? intval($std_check['course_id']) : 0;
    if ($std_val == 11 && ($course_val == 1 || $course_val == 2)) {
        $is_restricted_payment = true;
    }
}

// Fetch student standard, group and division for online scheduled exams
$std_stmt = $conn->prepare("
    SELECT r.course_id, r.standard as reg_standard_num, r.medium_id, r.group_id, e.division_id, s.stdid, s.stdnumber 
    FROM tbl_gm_std_registration r
    LEFT JOIN tbl_enrolled_students e ON r.id = e.registration_id AND e.is_active = 1
    LEFT JOIN standard s ON s.stdnumber = r.standard AND (
        (r.standard = 13)
        OR (r.medium_id = 1 AND s.stdtext LIKE '%Gujarati%')
        OR (r.medium_id = 2 AND s.stdtext LIKE '%English%')
    )
    WHERE r.id = ?
");
$std_stmt->execute([$user_id]);
$exam_student = $std_stmt->fetch();

$exam_course_id = $exam_student ? ($exam_student['course_id'] ?? 0) : 0;
$exam_standard_id = $exam_student ? ($exam_student['stdid'] ?? 0) : 0;
$exam_group_id = $exam_student ? ($exam_student['group_id'] ?? null) : null;
$exam_division_id = $exam_student ? ($exam_student['division_id'] ?? null) : null;

// Fetch active scheduled exams (excluding self-practice)
$exam_sql = "SELECT e.*, 
        (SELECT status FROM tbl_oes_student_exams WHERE exam_id = e.id AND student_id = ?) as attempt_status
        FROM tbl_oes_exams e 
        WHERE (e.standard_id = ? OR e.standard_id IS NULL) 
        AND (e.group_id = ? OR e.group_id IS NULL)
        AND (e.division_id = ? OR e.division_id IS NULL)
        AND e.exam_mode = 'Practice'
        AND e.status IN ('Scheduled', 'Live')
        AND e.end_time >= NOW()
        AND e.student_id IS NULL
        ORDER BY e.start_time ASC";
$exam_stmt = $conn->prepare($exam_sql);
$exam_stmt->execute([$user_id, $exam_standard_id, $exam_group_id, $exam_division_id]);
$scheduled_exams = $exam_stmt->fetchAll();
?>
<?php
include '../../include/header.php'; ?>
<?php
include '../../include/navbar.php'; ?>
<?php
include '../../include/sidebar.php'; ?>



<div class="container-fluid py-4 pb-5">
    <style>
        .pulse-live {
            width: 8px;
            height: 8px;
            background-color: #10b981;
            border-radius: 50%;
            display: inline-block;
            box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
            animation: pulse-green 1.5s infinite;
            vertical-align: middle;
        }
        @keyframes pulse-green {
            0% {
                transform: scale(0.95);
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
            }
            70% {
                transform: scale(1);
                box-shadow: 0 0 0 6px rgba(16, 185, 129, 0);
            }
            100% {
                transform: scale(0.95);
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
            }
        }
    </style>
    <?php
    if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="fas fa-check-circle fs-4 me-3"></i>
                <div><?php echo htmlspecialchars($_SESSION['success'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php
    endif; ?>

    <?php
    if (isset($_SESSION['error']) || isset($_SESSION['error_msg'])): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="fas fa-exclamation-circle fs-4 me-3"></i>
                <div><?php echo htmlspecialchars($_SESSION['error'] ?? $_SESSION['error_msg'], ENT_QUOTES, 'UTF-8');
                unset($_SESSION['error'], $_SESSION['error_msg']); ?></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php
    endif; ?>

    <?php
    if (isset($_SESSION['warning_msg'])): ?>
        <div class="alert alert-warning alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="fas fa-exclamation-triangle fs-4 me-3"></i>
                <div><?php echo htmlspecialchars($_SESSION['warning_msg'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php
    endif; ?>

    <?php
    if (isset($_SESSION['info_msg'])): ?>
        <div class="alert alert-info alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="fas fa-info-circle fs-4 me-3"></i>
                <div><?php echo htmlspecialchars($_SESSION['info_msg'] ?? '', ENT_QUOTES, 'UTF-8');
                unset($_SESSION['info_msg']); ?></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php
    endif; ?>

    <!-- Token Payment Status Banner -->
    <?php
    if (!$student_info['token_fees_paid'] && ($student_info['course_id'] ?? 0) != 6): ?>
        <div class="alert alert-warning mb-4 shadow-sm" style="border-start: 4px solid #ff9800;">
            <div class="d-flex align-items-center">
                <div class="me-3">
                    <i class="fas fa-exclamation-triangle fa-2x text-warning"></i>
                </div>
                <div class="flex-grow-1">
                    <h5 class="alert-heading mb-1">
                        <i class="fas fa-rupee-sign"></i> Token Fee Payment Pending Verification
                    </h5>
                    <p class="mb-2">
                        Your offline token fee payment is under verification by the accounts department.
                        You have limited access to the portal until the payment is confirmed.
                    </p>
                    <small class="text-muted">
                        <i class="fas fa-info-circle"></i> For any queries, please contact the accounts department.
                    </small>
                </div>
            </div>
        </div>
        <?php
    endif; ?>

    <!-- Session Information Bar -->
    <!-- <div class="session-info-bar mb-3 d-flex flex-wrap justify-content-between align-items-center opacity-75">
        <div class="d-flex align-items-center me-3">
            <span class="badge bg-light text-dark border me-2">
                <i class="fas fa-id-card me-1 text-muted"></i> Aadhaar:
                <?php echo htmlspecialchars($_SESSION['student_aadhaar'] ?? 'N/A'); ?>
            </span>
            <span class="badge bg-light text-dark border me-2">
                <i class="fas fa-clock me-1 text-muted"></i> Login:
                <?php echo isset($_SESSION['login_time']) ? date('d-m-Y h:i A', $_SESSION['login_time']) : 'N/A'; ?>
            </span>
            <span class="badge bg-light text-dark border me-2">
                <i class="fas fa-network-wired me-1 text-muted"></i> IP: <?php echo $_SERVER['REMOTE_ADDR']; ?>
            </span>
        </div>

    <!-- Welcome & Enrollment Banner -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="welcome-banner p-4 rounded-4 shadow-sm text-white position-relative overflow-hidden mb-2" style="background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);">
                <!-- Background decorative shapes/gradients -->
                <div class="position-absolute" style="right: -30px; bottom: -30px; opacity: 0.15; font-size: 9rem; pointer-events: none; color: #fff;">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                
                <div class="row align-items-center position-relative" style="z-index: 1;">
                    <!-- Left side: Welcome message & basic student status -->
                    <div class="col-lg-6 mb-3 mb-lg-0">
                        <div class="d-flex align-items-center">
                            <div class="avatar-container rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 64px; height: 64px; border: 2px solid rgba(255, 255, 255, 0.4); flex-shrink: 0; background: rgba(255, 255, 255, 0.25);">
                                <i class="fas fa-user-graduate fa-2x text-white"></i>
                            </div>
                            <div>
                                <span class="badge mb-2 px-2 py-1 small fw-semibold text-uppercase tracking-wider text-white" style="background: rgba(255, 255, 255, 0.2) !important; border: 1px solid rgba(255, 255, 255, 0.25);">Student Portal</span>
                                <h2 class="fw-bold mb-1 text-white" style="font-size: 1.75rem; letter-spacing: -0.5px;">
                                    Welcome back, <?php echo htmlspecialchars($_SESSION['student_name'] ?? 'Student'); ?>!
                                </h2>
                                <p class="mb-0 text-white-50 small">
                                    <i class="fas fa-calendar-alt me-1"></i> Academic Session: <?php echo date('Y'); ?> | Here's what's happening with your counseling.
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right side: Enrollment details (only if $enrollment_info exists) -->
                    <?php if ($enrollment_info): ?>
                        <div class="col-lg-6">
                            <div class="p-3 rounded-3" style="background: rgba(255, 255, 255, 0.12); border: 1px solid rgba(255, 255, 255, 0.15);">
                                <div class="d-flex justify-content-between align-items-center mb-2 pb-2" style="border-bottom: 1px solid rgba(255, 255, 255, 0.15);">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-id-card text-white me-2"></i>
                                        <span class="fw-semibold text-white small">Enrollment Details</span>
                                    </div>
                                    <span class="badge fw-bold px-2 py-1 small" style="background-color: #ffffff !important; color: #1e40af !important;">
                                        No: <?php echo htmlspecialchars($enrollment_info['enrollment_no'] ?? ''); ?>
                                    </span>
                                </div>
                                <div class="row g-2 text-center d-flex align-items-stretch">
                                    <div class="col">
                                        <div class="p-2 rounded h-100 d-flex flex-column justify-content-center" style="background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.08);">
                                            <div class="text-white-50 small" style="font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.5px;">Course</div>
                                            <div class="fw-bold text-white small text-truncate" title="<?php echo htmlspecialchars($enrollment_info['course_name'] ?? 'N/A'); ?>">
                                                <?php echo htmlspecialchars($enrollment_info['course_name'] ?? 'N/A'); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col">
                                        <div class="p-2 rounded h-100 d-flex flex-column justify-content-center" style="background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.08);">
                                            <div class="text-white-50 small" style="font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.5px;">Medium</div>
                                            <div class="fw-bold text-white small text-truncate" title="<?php echo htmlspecialchars($enrollment_info['medium_name'] ?? 'N/A'); ?>">
                                                <?php echo htmlspecialchars($enrollment_info['medium_name'] ?? 'N/A'); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col">
                                        <div class="p-2 rounded h-100 d-flex flex-column justify-content-center" style="background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.08);">
                                            <div class="text-white-50 small" style="font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.5px;">Group</div>
                                            <div class="fw-bold text-white small" style="white-space: normal; word-wrap: break-word; line-height: 1.2;" title="<?php echo htmlspecialchars($enrollment_info['group_name'] ?? 'N/A'); ?>">
                                                <?php echo htmlspecialchars($enrollment_info['group_name'] ?? 'N/A'); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col">
                                        <div class="p-2 rounded h-100 d-flex flex-column justify-content-center" style="background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.08);">
                                            <div class="text-white-50 small" style="font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.5px;">Division</div>
                                            <div class="fw-bold text-white small text-truncate">
                                                <?php if ($enrollment_info['division_id']): ?>
                                                    <span class="text-white fw-bold"><?php echo htmlspecialchars($enrollment_info['division_name'] ?? ''); ?></span>
                                                <?php else: ?>
                                                    <span class="text-warning small" style="font-size: 0.75rem;">Not Assigned</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col">
                                        <div class="p-2 rounded h-100 d-flex flex-column justify-content-center" style="background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.08);">
                                            <div class="text-white-50 small" style="font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.5px;">Roll No</div>
                                            <div class="fw-bold text-white small text-truncate">
                                                <?php if ($enrollment_info['roll_no']): ?>
                                                    <span class="text-white fw-bold"><?php echo $enrollment_info['roll_no']; ?></span>
                                                <?php else: ?>
                                                    <span class="text-warning small" style="font-size: 0.75rem;">Not Assigned</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- If not enrolled yet, display status -->
                        <div class="col-lg-6 text-lg-end">
                            <div class="d-inline-block px-3 py-2 rounded-3 text-start" style="background: rgba(255, 255, 255, 0.12); border: 1px solid rgba(255, 255, 255, 0.15);">
                                <span class="d-block small text-white-50"><i class="fas fa-info-circle me-1"></i> Account Status</span>
                                <span class="fw-semibold text-warning"><i class="fas fa-spinner fa-spin me-1"></i> Awaiting Enrollment Approval</span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-4 mb-5">
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="glass-card h-100">
                <div class="stat-card">
                    <div class="stat-top">
                        <div>
                            <div class="stat-value"><?php echo gca_safe_html($pending_appointments); ?></div>
                            <div class="stat-label">Pending Appointments</div>
                        </div>
                        <div class="stat-icon bg-icon-info">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <a href="<?php echo PORTAL_URL; ?>/modules/student-portal/my-appointments.php?status=pending"
                        class="stat-link text-info">
                        View Details <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="glass-card h-100">
                <div class="stat-card">
                    <div class="stat-top">
                        <div>
                            <div class="stat-value"><?php echo gca_safe_html($completed_appointments); ?></div>
                            <div class="stat-label">Completed Sessions</div>
                        </div>
                        <div class="stat-icon bg-icon-success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <a href="<?php echo PORTAL_URL; ?>/modules/student-portal/my-appointments.php?status=completed"
                        class="stat-link text-success">
                        View Details <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="glass-card h-100">
                <div class="stat-card">
                    <div class="stat-top">
                        <div>
                            <div class="stat-value"><?php echo gca_safe_html($total_tests); ?></div>
                            <div class="stat-label">Tests Taken</div>
                        </div>
                        <div class="stat-icon bg-icon-warning">
                            <i class="fas fa-file-alt"></i>
                        </div>
                    </div>
                    <a href="<?php echo PORTAL_URL; ?>/modules/student-portal/my-results.php"
                        class="stat-link text-warning">
                        View Details <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="glass-card h-100">
                <div class="stat-card">
                    <div class="stat-top">
                        <div>
                            <div class="stat-value" id="wallet-balance-display">
                                ₹<?php echo formatIndianCurrency($wallet_balance); ?></div>
                            <div class="stat-label">Digital Wallet</div>
                        </div>
                        <div class="stat-icon bg-icon-primary">
                            <i class="fas fa-wallet"></i>
                        </div>
                    </div>
                    <a href="<?php echo PORTAL_URL; ?>/modules/student-portal/my-wallet.php"
                        class="stat-link text-primary">
                        Manage Wallet <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>


    <div class="row g-4">
        <!-- LEFT: Official Scheduled Exams -->
        <div class="col-xl-6 col-lg-6 col-12">
            <div class="card card-enhanced h-100">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-calendar-alt me-2" style="color: #ffffff !important;"></i> Official Scheduled Exams
                    </h3>
                    <span class="badge bg-light text-dark fs-6">
                        Active: <?php echo count($scheduled_exams); ?>
                    </span>
                </div>
                <div class="card-body p-4" style="max-height: 520px; overflow-y: auto;">
                    <?php if (count($scheduled_exams) > 0): ?>
                        <div class="d-flex flex-column gap-3">
                            <?php foreach ($scheduled_exams as $e): 
                                $is_submitted = ($e['attempt_status'] === 'Submitted');
                                $is_live = (strtotime($e['start_time']) <= time() && strtotime($e['end_time']) >= time());
                                
                                if ($is_submitted) {
                                    $card_border = 'border-start border-secondary border-4';
                                    $status_label = 'Submitted';
                                    $status_badge_class = 'bg-secondary';
                                } else if ($is_live) {
                                    $card_border = 'border-start border-success border-4';
                                    $status_label = '<span class="pulse-live me-1"></span> LIVE NOW';
                                    $status_badge_class = 'bg-success';
                                } else {
                                    $card_border = 'border-start border-warning border-4';
                                    $status_label = 'Upcoming';
                                    $status_badge_class = 'bg-warning text-dark';
                                }
                            ?>
                                <div class="card border shadow-sm p-3 mb-2 <?php echo $card_border; ?>" style="border-radius: 12px; transition: transform 0.2s ease;">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <h6 class="fw-bold text-dark mb-1" style="font-size: 0.95rem;"><?php echo htmlspecialchars($e['title']); ?></h6>
                                            <p class="text-muted mb-0 small text-truncate" style="max-width: 250px;" title="<?php echo htmlspecialchars($e['description'] ?: 'No instructions provided.'); ?>">
                                                <?php echo htmlspecialchars($e['description'] ?: 'No instructions provided.'); ?>
                                            </p>
                                        </div>
                                        <span class="badge <?php echo $status_badge_class; ?> px-2 py-1 small fw-semibold" style="font-size: 0.75rem; letter-spacing: 0.2px;">
                                            <?php echo $status_label; ?>
                                        </span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top border-light">
                                        <div class="d-flex flex-wrap gap-2">
                                            <span class="badge bg-light text-dark small"><i class="far fa-clock me-1 text-muted"></i> <?php echo $e['duration_mins']; ?> Mins</span>
                                            <span class="badge bg-light text-dark small"><i class="fas fa-star me-1 text-warning"></i> <?php echo $e['total_marks']; ?> Marks</span>
                                        </div>
                                        <div>
                                            <?php if ($e['attempt_status'] === 'Submitted'): ?>
                                                <button disabled class="btn btn-secondary btn-sm py-1 px-3 font-weight-bold small" style="border-radius: 8px; opacity: 0.8;">Completed</button>
                                            <?php elseif ($is_live): ?>
                                                <a href="<?php echo PORTAL_URL; ?>/modules/student-portal/take-exam.php?id=<?php echo $e['id']; ?>" class="btn btn-sm py-1 px-3 font-weight-bold text-white small" style="border-radius: 8px; background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); border: none; box-shadow: 0 4px 10px rgba(99, 102, 241, 0.25);">Start <i class="fas fa-arrow-right ms-1"></i></a>
                                            <?php else: ?>
                                                <button disabled class="btn btn-light btn-sm py-1 px-3 text-muted small" style="border-radius: 8px;" title="Locked until start time"><i class="fas fa-lock me-1"></i> Locked</button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-calendar-times fa-4x text-muted mb-3 opacity-50"></i>
                            <h5 class="fw-bold text-dark">No Active Scheduled Exams</h5>
                            <p class="text-muted small mb-0">There are no official scheduled practice tests assigned to you at the moment.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- RIGHT: Fee Structure & Payment Details -->
        <div class="col-xl-6 col-lg-6 col-12">
            <?php
            $fee_summary = $data['fee_summary'] ?? null;

            if ($fee_summary):
                // Filter out Hostel Fees completely from student view and calculations
                if (isset($fee_summary['allocations'])) {
                    $filtered_allocations = [];
                    $hostel_allocated = 0;
                    $hostel_paid = 0;
                    $hostel_waiver = 0;
                    $hostel_pending = 0;
                    
                    foreach ($fee_summary['allocations'] as $key => $alloc) {
                        $is_hostel = (
                            strpos(strtolower($key), 'hostel') !== false || 
                            strpos(strtolower($alloc['label'] ?? ''), 'hostel') !== false ||
                            strpos(strtolower($alloc['category'] ?? ''), 'hostel') !== false
                        );
                        
                        if ($is_hostel) {
                            $hostel_allocated += floatval($alloc['gross_amount'] ?? 0);
                            $hostel_paid += floatval($alloc['paid_amount'] ?? 0);
                            $hostel_waiver += floatval($alloc['waived_amount'] ?? 0);
                            $hostel_pending += floatval($alloc['pending_amount'] ?? 0);
                        } else {
                            $filtered_allocations[$key] = $alloc;
                        }
                    }
                    
                    $fee_summary['allocations'] = $filtered_allocations;
                    $fee_summary['total_allocated'] = floatval($fee_summary['total_allocated']) - $hostel_allocated;
                    $fee_summary['total_paid'] = floatval($fee_summary['total_paid']) - $hostel_paid;
                    $fee_summary['total_waiver'] = floatval($fee_summary['total_waiver']) - $hostel_waiver;
                    $fee_summary['total_pending'] = floatval($fee_summary['total_pending']) - $hostel_pending;
                }

                $total_pending = $fee_summary['total_pending'];
                $detailed_allocations = $fee_summary['allocations'];
                ?>

                <div class="card card-enhanced h-100">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h3 class="card-title mb-0"><i class="fas fa-list-alt"></i> Fee Structure & Payments</h3>
                        <div class="header-stats">
                            <span class="badge bg-light text-dark fs-6 me-2">Total:
                                ₹<?php echo formatIndianCurrency($fee_summary['total_allocated']); ?></span>
                            <span
                                class="badge <?php echo $total_pending > 0 ? 'bg-warning text-dark' : 'bg-success'; ?> fs-6">
                                <?php echo $fee_summary['status']; ?>
                            </span>
                        </div>
                    </div>
                    <div class="card-body p-4" style="max-height: 520px; overflow-y: auto;">
                        <?php if ($total_pending > 0): ?>
                            <div
                                class="alert alert-warning p-3 mb-3 d-flex align-items-center justify-content-between shadow-sm border-start border-4 border-warning small">
                                <div>
                                    <i class="fas fa-exclamation-circle fa-lg me-2"></i>
                                    <strong>₹<?php echo formatIndianCurrency($total_pending); ?> Outstanding</strong>
                                </div>
                                 <?php if (!$is_restricted_payment): ?>
                                     <button type="button" onclick="payAllPendingFees()" class="btn btn-success btn-sm shadow-sm font-weight-bold">
                                         Pay All Outstanding
                                     </button>
                                 <?php endif; ?>
                            </div>
                            <?php
                        else: ?>
                            <div
                                class="alert alert-success p-3 mb-3 d-flex align-items-center shadow-sm border-start border-4 border-success small">
                                <i class="fas fa-check-circle fa-lg me-2"></i>
                                <div>
                                    All fees paid. Thank you!
                                </div>
                            </div>
                            <?php
                        endif; ?>

                        <div class="table-responsive">
                            <table class="table table-sm table-bordered table-hover align-middle small mb-0">
                                <thead class="table-primary">
                                    <tr style="font-size: 0.78rem;">
                                        <th width="35%">COMPONENT</th>
                                        <th width="13%">GROSS</th>
                                        <th width="13%">PAID</th>
                                        <th width="12%">WAIVER</th>
                                        <th width="12%">DUE</th>
                                        <th width="15%">ACTION</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($detailed_allocations as $key => $alloc): ?>
                                        <tr style="font-size: 0.82rem;">
                                            <td>
                                                <strong class="text-dark"><?php echo htmlspecialchars($alloc['label'] ?? ''); ?></strong>
                                                <br><small class="text-muted"><?php echo $alloc['category']; ?></small>
                                            </td>
                                            <td>₹<?php echo formatIndianCurrency($alloc['gross_amount']); ?></td>
                                            <td class="text-success">
                                                ₹<?php echo formatIndianCurrency($alloc['paid_amount']); ?></td>
                                            <td class="text-info">
                                                <?php echo $alloc['waived_amount'] > 0 ? '₹' . formatIndianCurrency($alloc['waived_amount']) : '-'; ?>
                                            </td>
                                            <td>
                                                <?php if ($alloc['pending_amount'] > 0): ?>
                                                    <span
                                                        class="text-danger fw-bold">₹<?php echo formatIndianCurrency($alloc['pending_amount']); ?></span>
                                                    <?php
                                                else: ?>
                                                    <span class="badge bg-success small">PAID</span>
                                                    <?php
                                                endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($alloc['paid_amount'] > 0): ?>
                                                    <button type="button"
                                                        onclick="downloadReceipt('<?php echo $alloc['receipt_no']; ?>', '<?php echo $key; ?>')"
                                                        class="btn btn-outline-info btn-xs py-0 px-1 font-weight-bold" style="font-size: 0.72rem; border-radius: 4px;">
                                                        Receipt
                                                    </button>
                                                    <?php
                                                elseif ($alloc['pending_amount'] > 0): ?>
                                                     <?php if (!$is_restricted_payment): ?>
                                                         <button type="button" onclick="payFee('<?php echo $key; ?>')"
                                                             class="btn btn-warning btn-xs py-0 px-1 font-weight-bold text-dark" style="font-size: 0.72rem; border-radius: 4px;">
                                                             Pay Now
                                                         </button>
                                                     <?php endif; ?>

                                                    <?php
                                                endif; ?>
                                            </td>
                                        </tr>
                                        <?php
                                    endforeach; ?>
                                </tbody>
                                <tfoot class="table-dark">
                                    <tr style="font-size: 0.8rem;">
                                        <td><strong>Total</strong></td>
                                        <td><strong>₹<?php echo formatIndianCurrency($fee_summary['total_allocated']); ?></strong></td>
                                        <td><strong>₹<?php echo formatIndianCurrency($fee_summary['total_paid']); ?></strong></td>
                                        <td><strong>₹<?php echo formatIndianCurrency($fee_summary['total_waiver']); ?></strong></td>
                                        <td><strong>₹<?php echo formatIndianCurrency($fee_summary['total_pending']); ?></strong></td>
                                        <td>-</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <div class="mt-3 text-end small">
                            <a href="student-ledger.php" class="btn btn-link py-0 px-1">
                                <i class="fas fa-book-open"></i> Detailed Transaction Ledger
                            </a>
                        </div>
                    </div>
                </div>
                <?php
            else: ?>
                <div class="card glass-card text-center p-5">
                    <i class="fas fa-receipt fa-4x text-muted mb-3"></i>
                    <h4>No Fee Structure Found</h4>
                    <p class="text-muted">Your fee structure has not been allocated yet. Please contact the admissions
                        department.</p>
                </div>
                <?php
            endif; ?>
        </div>
    </div>    </div>
    </div>
</div>

<?php
include '../../include/footer.php'; ?>

<script>
    const user_id = '<?php echo $user_id; ?>';
    
    document.addEventListener('DOMContentLoaded', function () {
        const ctx = document.getElementById('performanceChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Test 1', 'Test 2', 'Test 3', 'Test 4', 'Test 5'],
                    datasets: [{
                        label: 'Score (%)',
                        data: [65, 75, 80, 70, 85],
                        backgroundColor: 'rgba(54, 162, 235, 0.5)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100
                        }
                    }
                }
            });
        }
    });

    // Fee payment functions (same as my-fees.php)
    function downloadReceipt(receiptNo, feeComponent) {
        if (typeof generateSecurePDF === 'function') {
            generateSecurePDF('<?php echo PORTAL_URL; ?>/modules/payments/receipt-print-pdf.php', {
                receipt_no: receiptNo,
                fee_component: feeComponent,
                student_id: user_id
            });
        } else {
            // Fallback to the global utility if it's somehow available but generateSecurePDF isn't (unlikely)
            window.location.href = '<?php echo PORTAL_URL; ?>/modules/payments/receipt-print-pdf.php?receipt_no=' + receiptNo + '&fee_component=' + feeComponent + '&student_id=' + user_id;
        }
    }

    function payFee(component, installmentId = null) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '<?php echo PORTAL_URL; ?>/modules/student-portal/pending-fee-payment.php';

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
        form.action = '<?php echo PORTAL_URL; ?>/modules/student-portal/pay-all-pending-fees.php';
        document.body.appendChild(form);
        form.submit();
    }
</script>