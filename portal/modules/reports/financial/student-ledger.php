<?php
/**
 * Student Master Ledger
 * Unified component for viewing complete financial statement, search, and operations
 */

require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/common/constants.php';
require_once __DIR__ . '/../../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once __DIR__ . '/../../../common/api_client.php';
require_once __DIR__ . '/../../../../common/helpers/format_helper.php';

// Load cancellation reasons
$reasons_file = __DIR__ . '/../../../config/cancellation_reasons.json';
$cancellation_reasons = [];
if (file_exists($reasons_file)) {
    $cancellation_reasons = json_decode(file_get_contents($reasons_file), true);
}


// Check access
if (!hasRole(ROLE_ACCOUNTANT) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_COUNSELLOR)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "Student Ledger";
$page_breadcrumb = "Student Ledger";

$dbOps = new DatabaseOperations();
$api = new APIClient();

// Get search query and student_id
// Get search query and student_id (support both GET and POST)
$search = $_REQUEST['search'] ?? '';
$student_id = $_REQUEST['student_id'] ?? '';

$studentData = null;
$payments = [];
$fee_allocations = [];
$hostel_allocations = [];
$transport_allocations = [];
$summary = null;
$post_admission_discounts_list = [];

if (!empty($student_id)) {
    // Fetch combined history and summary data via API
    $response = $api->get('payments/history', ['student_id' => $student_id]);

    if ($response && isset($response['success']) && $response['success']) {
        $studentData = $response['data']['student'] ?? [];
        $payments = $response['data']['payments'] ?? [];
        $ledger = $response['data']['ledger'] ?? [];

        // Build flat allocations list from ledger (API returns ledger, not fee_allocations)
        $fee_allocations = [];
        $hostel_allocations = [];
        $transport_allocations = [];
        $seen_components = [];

        foreach ($ledger as $term_entry) {
            $term_allocs = $term_entry['summary']['allocations'] ?? [];
            foreach ($term_allocs as $key => $alloc) {
                $comp = is_string($key) ? $key : ($alloc['fee_component'] ?? (string) $key);
                if (isset($seen_components[$comp]))
                    continue;
                $seen_components[$comp] = true;
                $mapped = [
                    'fee_component' => $comp,
                    'allocated_amount' => $alloc['gross_amount'] ?? 0,
                    'scholarship_amount' => $alloc['waived_amount'] ?? 0,
                    'payable_amount' => $alloc['payable_amount'] ?? 0,
                    'paid_amount' => $alloc['paid_amount'] ?? 0,
                    'pending_amount' => $alloc['pending_amount'] ?? 0,
                    'category' => $alloc['category'] ?? 'Academic',
                    'label' => $alloc['label'] ?? ucwords(str_replace('_', ' ', $comp)),
                ];
                $cat = $mapped['category'];
                if ($cat === 'Hostel') {
                    $hostel_allocations[] = $mapped;
                } elseif ($cat === 'Transport') {
                    $transport_allocations[] = $mapped;
                } else {
                    $fee_allocations[] = $mapped;
                }
            }
        }
        $summary = $response['data']['summary'] ?? [
            'total_allocated' => 0,
            'total_paid' => 0,
            'total_pending' => 0,
            'total_scholarship' => 0
        ];

        // Sort payments by date DESC for timeline (newest first)
        usort($payments, function ($a, $b) {
            return strtotime($b['payment_date']) - strtotime($a['payment_date']);
        });

        // Handle overpayment logic (Prioritize backend values if they exist)
        if (!isset($summary['overpayment'])) {
            if (($summary['total_pending'] ?? 0) < 0) {
                $summary['overpayment'] = abs($summary['total_pending']);
                $summary['total_pending_display'] = 0;
            } else {
                $summary['overpayment'] = 0;
                $summary['total_pending_display'] = $summary['total_pending'];
            }
        } else {
            $summary['total_pending_display'] = $summary['total_pending'];
        }

        // Fetch additional labels (class, group)
        $extraInfo = $dbOps->customSelect(
            "SELECT r.fathers_name, c.course_name as current_class, g.group_name, s.school_name
             FROM tbl_gm_std_registration r
             LEFT JOIN tbl_enrolled_students es ON es.registration_id = r.id AND es.is_active = 1
             LEFT JOIN tbl_group g ON r.group_id = g.id
             LEFT JOIN tbl_courses c ON r.course_id = c.id
             LEFT JOIN tbl_schools s ON r.school_id = s.id
             WHERE r.id = ?",
            [$student_id]
        );
        if (!empty($extraInfo)) {
            $studentData['fathers_name'] = $extraInfo[0]['fathers_name'];
            $studentData['current_class'] = $extraInfo[0]['current_class'];
            $studentData['group_name'] = $extraInfo[0]['group_name'];
            $studentData['school_name'] = $extraInfo[0]['school_name'];
        }

        // Fetch post-admission discounts list for this student
        $post_admission_discounts_list = $dbOps->customSelect(
            "SELECT d.*, u.name as created_by_name, app.name as approved_by_name
             FROM tbl_post_admission_discounts d
             INNER JOIN tbl_enrolled_students e ON d.enrollment_id = e.enrollment_id
             LEFT JOIN tbl_users u ON d.created_by = u.id
             LEFT JOIN tbl_users app ON d.approved_by = app.id
             WHERE e.registration_id = ?
             ORDER BY d.created_at DESC",
            [$student_id]
        );
        if ($post_admission_discounts_list === false) {
            $post_admission_discounts_list = [];
        }
    } else {
        set_flash_message('error', $response['error'] ?? 'Failed to load student ledger!');
    }
}

// Search logic if no student selected or manual search triggered
$searchResults = [];
if (!empty($search)) {
    $fuzzySearch = "%" . str_replace(' ', '%', $search) . "%";
    $searchResults = $dbOps->customSelect(
        "SELECT r.id, CONCAT_WS(' ', r.surname, r.student_name, r.fathers_name) as name, 
                r.mob as mobile, r.email, c.course_name as current_class, g.group_name
         FROM tbl_gm_std_registration r
         INNER JOIN tbl_enrolled_students es ON es.registration_id = r.id AND es.is_active = 1
         LEFT JOIN tbl_courses c ON r.course_id = c.id
         LEFT JOIN tbl_group g ON r.group_id = g.id
         WHERE (r.surname LIKE ? 
            OR r.student_name LIKE ? 
            OR r.fathers_name LIKE ?
            OR r.mob LIKE ? 
            OR r.email LIKE ?
            OR CONCAT_WS(' ', r.surname, r.student_name, r.fathers_name) LIKE ?
            OR CONCAT_WS(' ', r.student_name, r.surname) LIKE ?
            OR r.id = ?)
         LIMIT 15",
        [$fuzzySearch, $fuzzySearch, $fuzzySearch, $fuzzySearch, $fuzzySearch, $fuzzySearch, $fuzzySearch, $search]
    );
}

include '../../../include/header.php';
include '../../../include/navbar.php';
include '../../../include/sidebar.php';
?>

<style>
    /* Premium Design System - Student Ledger */
    :root {
        --glass-bg: rgba(255, 255, 255, 0.95);
        --glass-border: rgba(255, 255, 255, 0.2);
        --ledger-primary: #2563eb;
        --ledger-secondary: #7c3aed;
        --ledger-accent: #f59e0b;
        --ledger-gradient: linear-gradient(135deg, #2563eb 0%, #7c3aed 100%);
    }

    .ledger-search-box {
        background: var(--ledger-gradient);
        border-radius: 12px;
        padding: 2rem;
        box-shadow: 0 10px 25px rgba(37, 99, 235, 0.2);
        position: relative;
        overflow: hidden;
    }

    .ledger-search-box::after {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 300px;
        height: 300px;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 50%;
        pointer-events: none;
    }

    .stat-card {
        border: none;
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid rgba(0, 0, 0, 0.05);
    }

    .stat-icon {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 0.75rem;
        background: rgba(255, 255, 255, 0.2);
    }

    .stat-icon i {
        font-size: 1rem;
        color: white;
        margin: 0;
    }

    /* Timeline Styles */
    .ledger-timeline {
        position: relative;
        padding-left: 45px;
    }

    .ledger-timeline::before {
        content: '';
        position: absolute;
        left: 15px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #f1f5f9;
        border-radius: 2px;
    }

    .timeline-item {
        position: relative;
        margin-bottom: 2rem;
    }

    .timeline-dot {
        position: absolute;
        left: -38px;
        top: 0;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        background: #fff;
        border: 3px solid var(--ledger-primary);
        z-index: 2;
        box-shadow: 0 0 0 4px #fff;
    }

    .timeline-content {
        background: #fff;
        padding: 1.5rem;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        border: 1px solid #f1f5f9;
    }

    /* Tabs & Tables */
    .nav-tabs-custom {
        border-bottom: 2px solid #f1f5f9;
    }

    .nav-tabs-custom .nav-link {
        border: none;
        padding: 1rem 1.25rem;
        font-weight: 600;
        color: #64748b;
        position: relative;
    }

    .nav-tabs-custom .nav-link.active {
        color: var(--ledger-primary);
        background: transparent;
    }

    .nav-tabs-custom .nav-link.active::after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 0;
        right: 0;
        height: 2px;
        background: var(--ledger-primary);
        border-radius: 2px;
    }

    .table-ledger th {
        background: #f8fafc;
        text-transform: uppercase;
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        color: #64748b;
        border: none;
        padding: 0.75rem 1rem;
    }

    .table-ledger tr td {
        padding: 1rem;
        border-bottom: 1px solid #f1f5f9;
    }

    .search-result-pill {
        cursor: pointer;
        border-radius: 8px;
        margin-bottom: 0.25rem;
    }

    /* Profile Avatar */
    .profile-avatar-ledger {
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        border: 2px solid #fff;
    }
</style>

<div class="content-wrapper">
    <div class="content-header pb-0">
        <div class="container-fluid">
            <div class="row align-items-center mb-4">
                <div class="col-sm-6">
                    <h1 class="m-0 fw-bold text-dark"><i class="fas fa-file-invoice me-2 text-primary"></i>Student
                        Ledger</h1>
                    <p class="text-muted small mb-0">Complete financial history and allocation summary</p>
                </div>
                <div class="col-sm-6 text-end">
                    <?php if ($student_id): ?>
                        <div class="btn-group">
                            <button type="button" class="btn btn-outline-primary shadow-sm" onclick="window.print()">
                                <i class="fa-solid fa-print me-1"></i> Print Statement
                            </button>
                            <a href="ledger-export.php?student_id=<?php echo $student_id; ?>"
                                class="btn btn-success shadow-sm">
                                <i class="fa-solid fa-file-excel me-1"></i> Export Excel
                            </a>
                            <a href="ledger-export-pdf.php?student_id=<?php echo $student_id; ?>"
                                class="btn btn-danger shadow-sm">
                                <i class="fa-solid fa-file-pdf me-1"></i> Export PDF
                            </a>
                        </div>
                        <?php
                    endif; ?>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <!-- Search & Selector -->
            <div class="row mb-5">
                <div class="col-lg-12">
                    <div class="ledger-search-box">
                        <form method="GET" id="searchForm">
                            <div class="row align-items-center">
                                <div class="col-md-7">
                                    <label class="form-label text-white opacity-75 small">FIND STUDENT ACCOUNT</label>
                                    <div class="input-group input-group-lg shadow-lg">
                                        <span class="input-group-text bg-white border-0">
                                            <i class="fa-solid fa-search text-primary"></i>
                                        </span>
                                        <input type="text" name="search" class="form-control border-0 shadow-none"
                                            placeholder="Enter Student ID, Name, Mobile or Email..."
                                            value="<?php echo htmlspecialchars($search ?? ''); ?>" autocomplete="off"
                                            style="font-size: 1.1rem;">
                                        <button type="submit" class="btn btn-dark px-4 fw-bold">SEARCH</button>
                                    </div>
                                </div>
                                <?php if ($student_id): ?>
                                    <div class="col-md-5 text-end pt-4">
                                        <a href="student-ledger.php"
                                            class="btn btn-link text-white text-decoration-none bg-white bg-opacity-10 px-3 rounded-pill">
                                            <i class="fas fa-sync-alt me-1"></i> Reset Ledger View
                                        </a>
                                    </div>
                                    <?php
                                endif; ?>
                            </div>
                        </form>

                        <?php if (!empty($searchResults)): ?>
                            <div class="card mt-3 shadow-lg border-0 bg-white bg-opacity-95"
                                style="backdrop-filter: blur(10px);">
                                <div class="list-group list-group-flush">
                                    <?php foreach ($searchResults as $row): ?>
                                        <a href="?student_id=<?php echo $row['id']; ?>"
                                            class="list-group-item list-group-item-action p-3 search-result-pill border-0">
                                            <div class="d-flex w-100 justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="mb-1 fw-bold text-dark"><?php echo $row['name']; ?></h6>
                                                    <small class="text-muted">
                                                        <i class="fas fa-id-card me-1"></i> <?php echo $row['id']; ?> |
                                                        <i class="fas fa-phone me-1"></i> <?php echo $row['mobile']; ?> |
                                                        <i class="fas fa-graduation-cap me-1"></i>
                                                        <?php echo $row['current_class']; ?> (<?php echo $row['group_name']; ?>)
                                                    </small>
                                                </div>
                                                <span class="badge bg-primary px-3 py-2 rounded-pill shadow-sm">View Full Ledger
                                                    <i class="fas fa-chevron-right ms-1 small"></i></span>
                                            </div>
                                        </a>
                                        <?php
                                    endforeach; ?>
                                </div>
                            </div>
                            <?php
                        endif; ?>
                    </div>
                </div>
            </div>

            <?php if ($studentData): ?>
                <!-- Student Header & Quick Stats -->
                <div class="row g-4 mb-5">
                    <div class="col-12">
                        <div class="card stat-card shadow-sm border-0 bg-white">
                            <div class="card-body p-4">
                                <div class="d-flex align-items-center gap-4">
                                    <div class="profile-avatar-ledger flex-shrink-0"
                                        style="width: 60px; height: 60px; border-radius: 15px; background: linear-gradient(135deg, #e0e7ff 0%, #ede9fe 100%); color: #4f46e5; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 800; border: 3px solid #fff;">
                                        <?php echo strtoupper(substr($studentData['student_name'], 0, 1)); ?>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <h5 class="mb-0 fw-bold text-dark">
                                                <?php echo $studentData['surname'] . ' ' . $studentData['student_name'] . ' ' . ($studentData['fathers_name'] ?? ''); ?>
                                            </h5>
                                            <span class="badge rounded-pill px-3 py-1"
                                                style="background:#ecfdf5;color:#059669;font-size:0.7rem;">
                                                <i class="fas fa-check-circle me-1"></i> ENROLLED STUDENT
                                            </span>
                                        </div>
                                        <div class="d-flex flex-wrap gap-3 mt-2">
                                            <span class="text-muted small"><i
                                                    class="fas fa-id-card me-1 text-primary"></i><strong>Student
                                                    ID:</strong> <?php echo $student_id; ?></span>
                                            <span class="text-muted small"><i
                                                    class="fas fa-graduation-cap me-1 text-primary"></i><strong>Class:</strong>
                                                <?php echo $studentData['current_class'] ?? 'N/A'; ?></span>
                                            <span class="text-muted small"><i
                                                    class="fas fa-layer-group me-1 text-primary"></i><strong>Group:</strong>
                                                <?php echo $studentData['group_name'] ?? 'N/A'; ?></span>
                                            <span class="text-muted small"><i
                                                    class="fas fa-school me-1 text-primary"></i><strong>School:</strong>
                                                <?php echo $studentData['school_name'] ?? 'N/A'; ?></span>
                                            <span class="text-muted small"><i
                                                    class="fas fa-phone me-1 text-primary"></i><strong>Contact:</strong>
                                                <?php echo $studentData['mob']; ?></span>
                                        </div>
                                    </div>
                                    <?php if (($summary['overpayment'] ?? 0) > 0): ?>
                                        <div class="flex-shrink-0">
                                            <div class="alert alert-warning py-2 px-3 mb-0 small"
                                                style="background:#fffbeb;border-color:#fde68a;">
                                                <i class="fas fa-info-circle me-1"></i>
                                                Advance/Overpayment:
                                                <strong>₹<?php echo formatIndianCurrency($summary['overpayment']); ?></strong>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Account Actions -->
                <div class="row mb-3">
                    <div class="col-12">
                        <div class="card shadow-sm border-0 bg-white">
                            <div class="card-body py-3 px-4">
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <span class="fw-bold text-dark small me-1">
                                        <i class="fas fa-bolt me-1 text-warning"></i> Quick Actions:
                                    </span>
                                    <a href="../../payments/add-payment.php?student_id=<?php echo $student_id; ?>"
                                        class="btn btn-primary btn-sm shadow-sm">
                                        <i class="fas fa-plus-circle me-1"></i> New Payment
                                    </a>
                                    <button type="button" class="btn btn-warning btn-sm text-white shadow-sm"
                                        data-bs-toggle="modal" data-bs-target="#discountModal">
                                        <i class="fas fa-tag me-1"></i> Waiver / Discount
                                    </button>
                                    <a href="ledger-export-pdf.php?student_id=<?php echo $student_id; ?>" target="_blank"
                                        class="btn btn-sm btn-outline-danger shadow-sm">
                                        <i class="fas fa-file-pdf me-1"></i> Export PDF
                                    </a>
                                    <a href="ledger-export.php?student_id=<?php echo $student_id; ?>"
                                        class="btn btn-sm btn-outline-success shadow-sm">
                                        <i class="fas fa-file-excel me-1"></i> Export Excel
                                    </a>
                                    <a href="../../students/edit-student.php?id=<?php echo $student_id; ?>"
                                        class="btn btn-sm btn-outline-dark shadow-sm ms-auto">
                                        <i class="fas fa-user-edit me-1"></i> Edit Student Profile
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ledger Details -->
                <div class="row g-4">
                    <div class="col-lg-8">
                        <div class="card shadow-sm border-0 mb-4 overflow-hidden bg-white">
                            <div class="card-header bg-white border-bottom-0 pt-4 px-4">
                                <ul class="nav nav-tabs nav-tabs-custom card-header-tabs border-bottom-0" id="ledgerTabs"
                                    role="tablist">
                                    <li class="nav-item">
                                        <a class="nav-link active" data-bs-toggle="tab" href="#totals-tab" role="tab">
                                            <i class="fas fa-calculator me-2"></i> Fee Totals
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" data-bs-toggle="tab" href="#summary-tab" role="tab">
                                            <i class="fas fa-list-check me-2"></i> Allocation Summary
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" data-bs-toggle="tab" href="#scholarship-tab" role="tab">
                                            <i class="fas fa-gift me-2"></i> Scholarship & Discounts
                                        </a>
                                    </li>
                                </ul>
                            </div>
                            <div class="card-body p-4">
                                <div class="tab-content">
                                    <!-- Summary Content -->
                                    <div class="tab-pane fade" id="summary-tab" role="tabcontent">
                                        <?php if (empty($ledger)): ?>
                                            <div class="text-center py-5">
                                                <i class="fas fa-layer-group fa-3x text-muted opacity-20 mb-3"></i>
                                                <p class="text-muted">No term-wise allocations found for this student.</p>
                                            </div>
                                        <?php else: ?>
                                            <?php foreach ($ledger as $term_data):
                                                $term_summary = $term_data['summary'];
                                                ?>
                                                <div class="term-ledger-section mb-5">
                                                    <div
                                                        class="d-flex justify-content-between align-items-center bg-light p-3 rounded-3 mb-3 border-start border-4 border-primary">
                                                        <div>
                                                            <h5 class="fw-bold text-dark mb-0">
                                                                <?php echo $term_data['term_name']; ?></h5>
                                                            <small class="text-muted"><i class="fas fa-graduation-cap me-1"></i>
                                                                <?php echo $term_data['course_name']; ?> | <i
                                                                    class="far fa-calendar-alt me-1"></i> AY:
                                                                <?php echo $term_data['academic_year']; ?></small>
                                                        </div>
                                                        <div class="text-end">
                                                            <span
                                                                class="badge <?php echo $term_summary['total_pending'] <= 0 ? 'bg-success' : 'bg-warning'; ?> px-3 py-2 rounded-pill">
                                                                <?php echo $term_summary['status']; ?>
                                                            </span>
                                                        </div>
                                                    </div>

                                                    <div class="table-responsive">
                                                        <table class="table table-ledger align-middle border-0">
                                                            <thead>
                                                                <tr>
                                                                    <th class="border-0">Fee Description</th>
                                                                    <th class="text-center border-0">Allocated</th>
                                                                    <th class="text-center border-0">Scholarship</th>
                                                                    <th class="text-center border-0">Payable</th>
                                                                    <th class="text-center border-0">Paid</th>
                                                                    <th class="text-center border-0">Balance</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php if (isset($term_summary['allocations']) && !empty($term_summary['allocations'])): ?>
                                                                    <?php foreach ($term_summary['allocations'] as $comp): ?>
                                                                        <tr>
                                                                            <td class="ps-4">
                                                                                <span
                                                                                    class="text-dark fw-bold"><?php echo $comp['label']; ?></span>
                                                                                <small class="d-block text-muted"
                                                                                    style="font-size: 0.7rem;">
                                                                                    <?php echo ($comp['category'] ?? 'Academic'); ?>
                                                                                    component
                                                                                </small>
                                                                            </td>
                                                                            <td class="text-center">
                                                                                ₹<?php echo formatIndianCurrency($comp['gross_amount']); ?>
                                                                            </td>
                                                                            <td class="text-center text-warning">
                                                                                ₹<?php echo formatIndianCurrency($comp['waived_amount']); ?>
                                                                            </td>
                                                                            <td class="text-center text-primary">
                                                                                ₹<?php echo formatIndianCurrency($comp['payable_amount']); ?>
                                                                            </td>
                                                                            <td class="text-center text-success">
                                                                                ₹<?php echo formatIndianCurrency($comp['paid_amount']); ?>
                                                                            </td>
                                                                            <td class="text-center">
                                                                                <span
                                                                                    class="badge <?php echo $comp['pending_amount'] > 0 ? 'bg-soft-danger text-danger' : 'bg-soft-success text-success'; ?>"
                                                                                    style="background: <?php echo $comp['pending_amount'] > 0 ? '#fee2e2' : '#d1fae5'; ?>; font-size: 0.8rem;">
                                                                                    ₹<?php echo formatIndianCurrency($comp['pending_amount']); ?>
                                                                                </span>
                                                                            </td>
                                                                        </tr>
                                                                    <?php endforeach; ?>
                                                                <?php endif; ?>

                                                                <?php if (false && (($term_summary['scholarship'] ?? 0) > 0 || ($term_summary['additional_scholarship'] ?? 0) > 0 || ($term_summary['post_admission_discount'] ?? 0) > 0)): ?>
                                                                    <!-- Scholarship Breakdown Row -->
                                                                    <tr class="text-muted small" style="background: #f8fafc;">
                                                                        <td class="ps-4 py-2" colspan="2">
                                                                            <div class="d-flex flex-column gap-1">
                                                                                <span class="text-dark opacity-75 fw-bold"><i
                                                                                        class="fas fa-gift me-1"></i> WAIVER
                                                                                    BREAKDOWN</span>
                                                                                <?php if (($term_summary['scholarship'] ?? 0) > 0): ?>
                                                                                    <span class="ps-3 border-start ms-2"><i
                                                                                            class="fas fa-tag me-1 opacity-50"></i> Main
                                                                                        Scholarship:
                                                                                        ₹<?php echo formatIndianCurrency($term_summary['scholarship']); ?></span>
                                                                                <?php endif; ?>
                                                                                <?php if (($term_summary['additional_scholarship'] ?? 0) > 0): ?>
                                                                                    <span class="ps-3 border-start ms-2"><i
                                                                                            class="fas fa-plus-circle me-1 opacity-50"></i>
                                                                                        Additional Scholarship:
                                                                                        ₹<?php echo formatIndianCurrency($term_summary['additional_scholarship']); ?></span>
                                                                                <?php endif; ?>
                                                                                <?php if (($term_summary['post_admission_discount'] ?? 0) > 0): ?>
                                                                                    <span class="ps-3 border-start ms-2"><i
                                                                                            class="fas fa-percent me-1 opacity-50"></i>
                                                                                        Post-Admission Discount:
                                                                                        ₹<?php echo formatIndianCurrency($term_summary['post_admission_discount']); ?></span>
                                                                                <?php endif; ?>
                                                                            </div>
                                                                        </td>
                                                                        <td class="text-center text-warning fw-bold">
                                                                            ₹<?php echo formatIndianCurrency(($term_summary['scholarship'] ?? 0) + ($term_summary['additional_scholarship'] ?? 0) + ($term_summary['post_admission_discount'] ?? 0)); ?>
                                                                        </td>
                                                                        <td colspan="3"></td>
                                                                    </tr>
                                                                <?php endif; ?>

                                                                <tr class="fw-bold bg-soft-primary" style="background: #f1f5f9;">

                                                                    <td class="ps-4 border-top border-primary border-opacity-10">
                                                                        <span class="text-primary">TERM FINANCIAL SUMMARY</span>
                                                                        <small class="d-block text-muted small">Total aggregated
                                                                            values for this semester</small>
                                                                    </td>
                                                                    <td
                                                                        class="text-center border-top border-primary border-opacity-10">
                                                                        ₹<?php echo formatIndianCurrency($term_summary['total_allocated']); ?>
                                                                    </td>
                                                                    <td
                                                                        class="text-center text-warning border-top border-primary border-opacity-10">
                                                                        ₹<?php echo formatIndianCurrency($term_summary['total_waiver']); ?>
                                                                    </td>
                                                                    <td
                                                                        class="text-center text-primary border-top border-primary border-opacity-10">
                                                                        ₹<?php echo formatIndianCurrency($term_summary['total_allocated'] - $term_summary['total_waiver']); ?>
                                                                    </td>
                                                                    <td
                                                                        class="text-center text-success border-top border-primary border-opacity-10">
                                                                        ₹<?php echo formatIndianCurrency($term_summary['total_paid']); ?>
                                                                    </td>
                                                                    <td
                                                                        class="text-center border-top border-primary border-opacity-10">
                                                                        <span
                                                                            class="badge <?php echo $term_summary['total_pending'] > 0 ? 'bg-primary text-white' : 'bg-success text-white'; ?> px-3 py-2 rounded-pill">
                                                                            ₹<?php echo formatIndianCurrency($term_summary['total_pending']); ?>
                                                                        </span>
                                                                    </td>
                                                                </tr>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Scholarship & Discounts Tab -->
                                    <div class="tab-pane fade" id="scholarship-tab" role="tabcontent">
                                        <?php if (empty($ledger)): ?>
                                            <div class="text-center py-5">
                                                <i class="fas fa-gift fa-3x text-muted opacity-20 mb-3"></i>
                                                <p class="text-muted">No scholarship or waiver details available for this student.</p>
                                            </div>
                                        <?php else:
                                            $total_main_scholarship = 0;
                                            $total_additional_scholarship = 0;
                                            $total_post_discount = 0;
                                            $total_waiver_all = 0;

                                            foreach ($ledger as $term_data) {
                                                $term_summary = $term_data['summary'];
                                                $total_main_scholarship += floatval($term_summary['scholarship'] ?? 0);
                                                $total_additional_scholarship += floatval($term_summary['additional_scholarship'] ?? 0);
                                                $total_post_discount += floatval($term_summary['post_admission_discount'] ?? 0);
                                                $total_waiver_all += floatval($term_summary['total_waiver'] ?? 0);
                                            }
                                        ?>
                                            <!-- Stats Dashboard -->
                                            <div class="row g-3 mb-4">
                                                <!-- Main Scholarship Card -->
                                                <div class="col-sm-6 col-xl-3">
                                                    <div class="card border-0 rounded-3 shadow-sm h-100" style="background: #f5f3ff; border-left: 4px solid #7c3aed !important;">
                                                        <div class="card-body p-3">
                                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                                <span class="text-muted small fw-bold text-uppercase" style="letter-spacing: 0.5px;">Main Scholarship</span>
                                                                <div class="bg-white p-2 rounded-3 shadow-sm">
                                                                    <i class="fas fa-graduation-cap text-purple" style="color: #7c3aed;"></i>
                                                                </div>
                                                            </div>
                                                            <h3 class="fw-bold mb-1" style="color: #4c1d95;">₹<?php echo formatIndianCurrency($total_main_scholarship); ?></h3>
                                                            <p class="text-muted small mb-0">Base scholarship awarded at admission</p>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Additional Scholarship Card -->
                                                <div class="col-sm-6 col-xl-3">
                                                    <div class="card border-0 rounded-3 shadow-sm h-100" style="background: #ecfeff; border-left: 4px solid #0891b2 !important;">
                                                        <div class="card-body p-3">
                                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                                <span class="text-muted small fw-bold text-uppercase" style="letter-spacing: 0.5px;">Additional Scholarship</span>
                                                                <div class="bg-white p-2 rounded-3 shadow-sm">
                                                                    <i class="fas fa-plus-circle text-cyan" style="color: #0891b2;"></i>
                                                                </div>
                                                            </div>
                                                            <h3 class="fw-bold mb-1" style="color: #164e63;">₹<?php echo formatIndianCurrency($total_additional_scholarship); ?></h3>
                                                            <p class="text-muted small mb-0">Discretionary/additional counselor waiver</p>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Post-Admission Discount Card -->
                                                <div class="col-sm-6 col-xl-3">
                                                    <div class="card border-0 rounded-3 shadow-sm h-100" style="background: #fff7ed; border-left: 4px solid #ea580c !important;">
                                                        <div class="card-body p-3">
                                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                                <span class="text-muted small fw-bold text-uppercase" style="letter-spacing: 0.5px;">Post-Adm Discount</span>
                                                                <div class="bg-white p-2 rounded-3 shadow-sm">
                                                                    <i class="fas fa-percent text-orange" style="color: #ea580c;"></i>
                                                                </div>
                                                            </div>
                                                            <h3 class="fw-bold mb-1" style="color: #7c2d12;">₹<?php echo formatIndianCurrency($total_post_discount); ?></h3>
                                                            <p class="text-muted small mb-0">Waivers/discounts approved post-admission</p>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Grand Total Waiver Card -->
                                                <div class="col-sm-6 col-xl-3">
                                                    <div class="card border-0 rounded-3 shadow-sm h-100" style="background: #f0fdf4; border-left: 4px solid #16a34a !important;">
                                                        <div class="card-body p-3">
                                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                                <span class="text-muted small fw-bold text-uppercase" style="letter-spacing: 0.5px;">Grand Total Waiver</span>
                                                                <div class="bg-white p-2 rounded-3 shadow-sm">
                                                                    <i class="fas fa-hand-holding-usd text-success" style="color: #16a34a;"></i>
                                                                </div>
                                                            </div>
                                                            <h3 class="fw-bold mb-1" style="color: #14532d;">₹<?php echo formatIndianCurrency($total_waiver_all); ?></h3>
                                                            <p class="text-muted small mb-0">Total combined waivers across all terms</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Term-Wise Waiver Table -->
                                            <div class="card border-0 shadow-sm rounded-3 overflow-hidden mb-4">
                                                <div class="card-header bg-light py-3 border-0">
                                                    <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-calendar-alt me-2 text-primary"></i>Term-wise Scholarship Breakdown</h6>
                                                </div>
                                                <div class="table-responsive">
                                                    <table class="table table-ledger align-middle border-0 mb-0">
                                                        <thead>
                                                            <tr>
                                                                <th class="border-0 ps-4">Term / Semester</th>
                                                                <th class="text-center border-0">Academic Year</th>
                                                                <th class="text-center border-0">Course/Class</th>
                                                                <th class="text-center border-0">Main Scholarship</th>
                                                                <th class="text-center border-0">Additional Scholarship</th>
                                                                <th class="text-center border-0">Post-Adm Discount</th>
                                                                <th class="text-center border-0 bg-light fw-bold text-primary">Total Waiver</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($ledger as $term_data):
                                                                $term_summary = $term_data['summary'];
                                                            ?>
                                                                <tr>
                                                                    <td class="ps-4">
                                                                        <span class="fw-bold text-dark"><?php echo htmlspecialchars($term_data['term_name']); ?></span>
                                                                    </td>
                                                                    <td class="text-center text-muted">
                                                                        <?php echo htmlspecialchars($term_data['academic_year']); ?>
                                                                    </td>
                                                                    <td class="text-center text-muted">
                                                                        <?php echo htmlspecialchars($term_data['course_name']); ?>
                                                                    </td>
                                                                    <td class="text-center text-dark">
                                                                        ₹<?php echo formatIndianCurrency($term_summary['scholarship'] ?? 0); ?>
                                                                    </td>
                                                                    <td class="text-center text-dark">
                                                                        ₹<?php echo formatIndianCurrency($term_summary['additional_scholarship'] ?? 0); ?>
                                                                    </td>
                                                                    <td class="text-center text-dark">
                                                                        ₹<?php echo formatIndianCurrency($term_summary['post_admission_discount'] ?? 0); ?>
                                                                    </td>
                                                                    <td class="text-center bg-light fw-bold text-success">
                                                                        ₹<?php echo formatIndianCurrency($term_summary['total_waiver'] ?? 0); ?>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>

                                            <!-- Post-Admission Discount History Logs -->
                                            <div class="card border-0 shadow-sm rounded-3 overflow-hidden mb-4">
                                                <div class="card-header bg-light py-3 border-0 d-flex justify-content-between align-items-center">
                                                    <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-history me-2 text-primary"></i>Post-Admission Discount History</h6>
                                                    <span class="badge bg-primary rounded-pill"><?php echo count($post_admission_discounts_list); ?> Request(s)</span>
                                                </div>
                                                <div class="table-responsive">
                                                    <table class="table table-ledger align-middle border-0 mb-0">
                                                        <thead>
                                                            <tr>
                                                                <th class="border-0 ps-4">Request Date</th>
                                                                <th class="text-center border-0">Discount Type</th>
                                                                <th class="text-center border-0">Requested Value</th>
                                                                <th class="text-center border-0">Calculated Amount</th>
                                                                <th class="text-center border-0">Created / Approved By</th>
                                                                <th class="text-center border-0">Status</th>
                                                                <th class="border-0 pe-4" style="width: 30%;">Remarks & Breakdown</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php if (empty($post_admission_discounts_list)): ?>
                                                                <tr>
                                                                    <td colspan="7" class="text-center py-4 text-muted">
                                                                        <i class="fas fa-info-circle me-1"></i> No post-admission discount requests found for this student.
                                                                    </td>
                                                                </tr>
                                                            <?php else: ?>
                                                                <?php foreach ($post_admission_discounts_list as $discount):
                                                                    $badge_style = 'bg-soft-secondary text-secondary';
                                                                    if ($discount['status'] === 'approved') {
                                                                        $badge_style = 'bg-soft-success text-success';
                                                                    } elseif ($discount['status'] === 'pending') {
                                                                        $badge_style = 'bg-soft-warning text-warning';
                                                                    } elseif ($discount['status'] === 'rejected') {
                                                                        $badge_style = 'bg-soft-danger text-danger';
                                                                    }
                                                                    
                                                                    // Format remarks for beautiful listing
                                                                    $remarks = htmlspecialchars($discount['remarks'] ?? '');
                                                                    if (strpos($remarks, 'Smart Waiver Breakdown:') !== false) {
                                                                        $parts = explode('Smart Waiver Breakdown:', $remarks);
                                                                        $main_remarks = trim($parts[0]);
                                                                        $breakdown = trim($parts[1]);
                                                                        
                                                                        $formatted_remarks = $main_remarks;
                                                                        if (!empty($breakdown)) {
                                                                            $lines = explode("\n", $breakdown);
                                                                            $bullet_points = [];
                                                                            foreach ($lines as $line) {
                                                                                $line_trimmed = trim(str_replace('-', '', $line));
                                                                                if (!empty($line_trimmed)) {
                                                                                    $bullet_points[] = '<li class="py-0.5"><i class="fas fa-check-circle text-success me-1 opacity-75"></i>' . htmlspecialchars($line_trimmed) . '</li>';
                                                                                }
                                                                            }
                                                                            if (!empty($bullet_points)) {
                                                                                $formatted_remarks .= '<div class="mt-2 small bg-light p-2 rounded-2 border-start border-3 border-success"><span class="fw-bold d-block text-success mb-1 small text-uppercase" style="font-size:0.7rem;letter-spacing:0.5px;"><i class="fas fa-magic me-1"></i>Smart Waiver Breakdown:</span><ul class="list-unstyled mb-0 ps-1">' . implode('', $bullet_points) . '</ul></div>';
                                                                            }
                                                                        }
                                                                    } else {
                                                                        $formatted_remarks = $remarks;
                                                                    }
                                                                ?>
                                                                    <tr>
                                                                        <td class="ps-4">
                                                                            <span class="text-dark fw-bold d-block"><?php echo date('d-M-Y', strtotime($discount['created_at'])); ?></span>
                                                                            <small class="text-muted"><?php echo date('h:i A', strtotime($discount['created_at'])); ?></small>
                                                                        </td>
                                                                        <td class="text-center text-uppercase">
                                                                            <span class="badge bg-soft-info text-info px-2 py-1 small"><?php echo htmlspecialchars($discount['discount_type']); ?></span>
                                                                        </td>
                                                                        <td class="text-center fw-semibold text-dark">
                                                                            <?php if ($discount['discount_type'] === 'percentage'): ?>
                                                                                <?php echo number_format($discount['discount_value'], 2); ?>%
                                                                            <?php else: ?>
                                                                                ₹<?php echo formatIndianCurrency($discount['discount_value']); ?>
                                                                            <?php endif; ?>
                                                                        </td>
                                                                        <td class="text-center fw-bold text-dark">
                                                                            ₹<?php echo formatIndianCurrency($discount['discount_amount']); ?>
                                                                        </td>
                                                                        <td class="text-center">
                                                                            <span class="small d-block text-dark">Req: <?php echo htmlspecialchars($discount['created_by_name'] ?? 'System'); ?></span>
                                                                            <?php if ($discount['status'] === 'approved'): ?>
                                                                                <small class="text-muted d-block mt-0.5">App: <?php echo htmlspecialchars($discount['approved_by_name'] ?? 'System'); ?></small>
                                                                            <?php endif; ?>
                                                                        </td>
                                                                        <td class="text-center">
                                                                            <span class="badge px-2 py-1 rounded-pill <?php echo $badge_style; ?> font-weight-bold text-uppercase" style="font-size:0.75rem;">
                                                                                <?php echo htmlspecialchars($discount['status']); ?>
                                                                            </span>
                                                                        </td>
                                                                        <td class="pe-4 text-muted small" style="white-space: normal;">
                                                                            <?php echo $formatted_remarks; ?>
                                                                        </td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            <?php endif; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Fee Totals Tab -->
                                    <div class="tab-pane fade show active" id="totals-tab" role="tabcontent">
                                        <?php if (empty($summary)): ?>
                                            <div class="text-center py-5">
                                                <i class="fas fa-calculator fa-3x text-muted opacity-20 mb-3"></i>
                                                <p class="text-muted">No fee data available.</p>
                                            </div>
                                        <?php else:
                                            $totals_cats = $summary['categories'] ?? [];
                                            $cat_config = [
                                                'Academic' => ['label' => 'Course / Academic Fee', 'icon' => 'fa-graduation-cap', 'color' => 'primary', 'bg' => '#eff6ff', 'border' => '#2563eb'],
                                                'Hostel' => ['label' => 'Hostel Fee', 'icon' => 'fa-hotel', 'color' => 'info', 'bg' => '#f0f9ff', 'border' => '#0ea5e9'],
                                                'Transport' => ['label' => 'Transport Fee', 'icon' => 'fa-bus', 'color' => 'warning', 'bg' => '#fffbeb', 'border' => '#f59e0b'],
                                                'Other' => ['label' => 'Other Fee', 'icon' => 'fa-plus-circle', 'color' => 'secondary', 'bg' => '#f8fafc', 'border' => '#64748b'],
                                            ];

                                            $gt_allocated = 0;
                                            $gt_waived = 0;
                                            $gt_payable = 0;
                                            $gt_paid = 0;
                                            $gt_balance = 0;
                                            ?>
                                            <div class="table-responsive">
                                                <table class="table align-middle mb-0" style="font-size:0.92rem;">
                                                    <thead style="background:#f1f5f9;">
                                                        <tr>
                                                            <th class="ps-4 py-3 border-0 text-muted fw-bold"
                                                                style="font-size:0.78rem;letter-spacing:.05em;text-transform:uppercase;">
                                                                Fee Category</th>
                                                            <th class="text-center py-3 border-0 text-muted fw-bold"
                                                                style="font-size:0.78rem;letter-spacing:.05em;text-transform:uppercase;">
                                                                Allocated</th>
                                                            <th class="text-center py-3 border-0 text-muted fw-bold"
                                                                style="font-size:0.78rem;letter-spacing:.05em;text-transform:uppercase;">
                                                                Scholarship / Waiver</th>
                                                            <th class="text-center py-3 border-0 text-muted fw-bold"
                                                                style="font-size:0.78rem;letter-spacing:.05em;text-transform:uppercase;">
                                                                Payable</th>
                                                            <th class="text-center py-3 border-0 text-muted fw-bold"
                                                                style="font-size:0.78rem;letter-spacing:.05em;text-transform:uppercase;">
                                                                Paid</th>
                                                            <th class="pe-4 text-end py-3 border-0 text-muted fw-bold"
                                                                style="font-size:0.78rem;letter-spacing:.05em;text-transform:uppercase;">
                                                                Balance Due</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($cat_config as $key => $cfg):
                                                            $d = $totals_cats[$key] ?? null;
                                                            if (!$d || ($d['allocated'] <= 0 && $d['paid'] <= 0))
                                                                continue;
                                                            $allocated = floatval($d['allocated']);
                                                            $waived = floatval($d['waived'] ?? 0);
                                                            $payable = $allocated - $waived;
                                                            $paid = floatval($d['paid']);
                                                            $balance = max(0, $payable - $paid);
                                                            $gt_allocated += $allocated;
                                                            $gt_waived += $waived;
                                                            $gt_payable += $payable;
                                                            $gt_paid += $paid;
                                                            $gt_balance += $balance;
                                                            ?>
                                                            <tr
                                                                style="border-left: 3px solid <?php echo $cfg['border']; ?>; background: <?php echo $cfg['bg']; ?>;">
                                                                <td class="ps-4 py-3">
                                                                    <div class="d-flex align-items-center gap-3">
                                                                        <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                                                                            style="width:38px;height:38px;background:<?php echo $cfg['border']; ?>1a;">
                                                                            <i
                                                                                class="fas <?php echo $cfg['icon']; ?> text-<?php echo $cfg['color']; ?>"></i>
                                                                        </div>
                                                                        <div>
                                                                            <div class="fw-bold text-dark">
                                                                                <?php echo $cfg['label']; ?></div>
                                                                            <?php if ($balance <= 0): ?>
                                                                                <small class="text-success fw-semibold"><i
                                                                                        class="fas fa-check-circle me-1"></i>Fully
                                                                                    Paid</small>
                                                                            <?php else: ?>
                                                                                <small class="text-danger fw-semibold"><i
                                                                                        class="fas fa-exclamation-circle me-1"></i>Balance
                                                                                    Pending</small>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                                <td class="text-center fw-semibold text-dark">
                                                                    ₹<?php echo formatIndianCurrency($allocated); ?></td>
                                                                <td class="text-center fw-semibold" style="color:#d97706;">
                                                                    <?php if ($waived > 0): ?>
                                                                        <span class="badge px-2 py-1 rounded-pill"
                                                                            style="background:#fef3c7;color:#d97706;font-size:0.82rem;">
                                                                            ₹<?php echo formatIndianCurrency($waived); ?>
                                                                        </span>
                                                                    <?php else: ?>
                                                                        <span class="text-muted">₹0</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td class="text-center fw-bold text-primary">
                                                                    ₹<?php echo formatIndianCurrency($payable); ?></td>
                                                                <td class="text-center fw-bold text-success">
                                                                    ₹<?php echo formatIndianCurrency($paid); ?></td>
                                                                <td class="pe-4 text-end">
                                                                    <span class="badge px-3 py-2 rounded-pill fw-bold"
                                                                        style="font-size:0.85rem;background:<?php echo $balance > 0 ? '#fee2e2' : '#d1fae5'; ?>;color:<?php echo $balance > 0 ? '#dc2626' : '#059669'; ?>;">
                                                                        ₹<?php echo formatIndianCurrency($balance); ?>
                                                                    </span>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                    <tfoot>
                                                        <tr style="background:#1e293b;">
                                                            <td class="ps-4 py-3">
                                                                <div class="d-flex align-items-center gap-3">
                                                                    <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                                                                        style="width:38px;height:38px;background:rgba(255,255,255,0.15);">
                                                                        <i class="fas fa-sigma text-white"></i>
                                                                    </div>
                                                                    <span class="fw-bold text-white"
                                                                        style="font-size:0.95rem;letter-spacing:.03em;">GRAND
                                                                        TOTAL</span>
                                                                </div>
                                                            </td>
                                                            <td class="text-center fw-bold text-white py-3">
                                                                ₹<?php echo formatIndianCurrency($gt_allocated); ?></td>
                                                            <td class="text-center fw-bold py-3" style="color:#fbbf24;">
                                                                ₹<?php echo formatIndianCurrency($gt_waived); ?></td>
                                                            <td class="text-center fw-bold py-3" style="color:#60a5fa;">
                                                                ₹<?php echo formatIndianCurrency($gt_payable); ?></td>
                                                            <td class="text-center fw-bold py-3" style="color:#34d399;">
                                                                ₹<?php echo formatIndianCurrency($gt_paid); ?></td>
                                                            <td class="pe-4 text-end py-3">
                                                                <span class="badge px-3 py-2 rounded-pill fw-bold shadow-sm"
                                                                    style="font-size:0.9rem;background:<?php echo $gt_balance > 0 ? '#dc2626' : '#059669'; ?>;color:#fff;">
                                                                    ₹<?php echo formatIndianCurrency($gt_balance); ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    </tfoot>
                                                </table>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Legacy Summary (Hidden but preserved for structure) -->
                                    <div class="tab-pane fade" id="summary-tab-disabled" style="display:none;">
                                        <div class="table-responsive">
                                            <table class="table table-ledger align-middle border-0">
                                                <thead>
                                                    <tr>
                                                        <th class="border-0">Fee Description</th>
                                                        <th class="text-center border-0">Allocated</th>
                                                        <th class="text-center border-0">Scholarship</th>
                                                        <th class="text-center border-0">Payable</th>
                                                        <th class="text-center border-0">Paid</th>
                                                        <th class="text-center border-0">Balance</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr class="bg-soft-primary" style="background: #f1f5f9;">
                                                        <td colspan="6" class="fw-bold text-primary py-2 px-3 small">
                                                            <i class="fas fa-graduation-cap me-2"></i> ACADEMIC & COURSE
                                                            FEES
                                                        </td>
                                                    </tr>
                                                    <?php foreach ($fee_allocations as $alloc): ?>
                                                        <tr>
                                                            <td class="ps-4">
                                                                <span
                                                                    class="fw-bold text-dark d-block"><?php echo formatFeeKey($alloc['fee_component'], $studentData['current_class'] ?? ''); ?></span>
                                                                <small class="badge bg-soft-secondary text-muted"
                                                                    style="background: #f1f5f9; font-size: 0.65rem;"><?php echo $alloc['category'] ?? 'Academic'; ?></small>
                                                            </td>
                                                            <td class="text-center fw-semibold text-dark">
                                                                ₹<?php echo formatIndianCurrency($alloc['allocated_amount']); ?>
                                                            </td>
                                                            <td class="text-center text-warning">
                                                                ₹<?php echo formatIndianCurrency($alloc['scholarship_amount']); ?>
                                                            </td>
                                                            <td class="text-center fw-bold text-primary">
                                                                ₹<?php echo formatIndianCurrency($alloc['payable_amount']); ?>
                                                            </td>
                                                            <td class="text-center text-success fw-bold">
                                                                ₹<?php echo formatIndianCurrency($alloc['paid_amount']); ?>
                                                            </td>
                                                            <td class="text-center">
                                                                <span
                                                                    class="badge <?php echo $alloc['pending_amount'] > 0 ? 'bg-soft-danger text-danger' : 'bg-soft-success text-success'; ?>"
                                                                    style="background: <?php echo $alloc['pending_amount'] > 0 ? '#fee2e2' : '#d1fae5'; ?>;">
                                                                    ₹<?php echo formatIndianCurrency($alloc['pending_amount']); ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                        <?php
                                                    endforeach; ?>

                                                    <?php if (!empty($hostel_allocations)): ?>
                                                        <tr class="bg-soft-info" style="background: #f0f9ff;">
                                                            <td colspan="6" class="fw-bold text-info py-2 px-3 small">
                                                                <i class="fas fa-hotel me-2"></i> RESIDENTIAL / HOSTEL FEES
                                                            </td>
                                                        </tr>
                                                        <?php foreach ($hostel_allocations as $alloc): ?>
                                                            <tr>
                                                                <td class="ps-4">
                                                                    <span
                                                                        class="fw-bold text-dark d-block"><?php echo formatFeeKey($alloc['fee_component'], $studentData['current_class'] ?? ''); ?></span>
                                                                    <small class="badge bg-soft-info text-info"
                                                                        style="background: #e0f2fe; font-size: 0.65rem;">Hostel</small>
                                                                </td>
                                                                <td class="text-center fw-semibold text-dark">
                                                                    ₹<?php echo formatIndianCurrency($alloc['allocated_amount']); ?>
                                                                </td>
                                                                <td class="text-center text-warning">
                                                                    ₹<?php echo formatIndianCurrency($alloc['scholarship_amount']); ?>
                                                                </td>
                                                                <td class="text-center fw-bold text-primary">
                                                                    ₹<?php echo formatIndianCurrency($alloc['payable_amount']); ?>
                                                                </td>
                                                                <td class="text-center text-success fw-bold">
                                                                    ₹<?php echo formatIndianCurrency($alloc['paid_amount']); ?>
                                                                </td>
                                                                <td class="text-center">
                                                                    <span
                                                                        class="badge <?php echo $alloc['pending_amount'] > 0 ? 'bg-soft-danger text-danger' : 'bg-soft-success text-success'; ?>"
                                                                        style="background: <?php echo $alloc['pending_amount'] > 0 ? '#fee2e2' : '#d1fae5'; ?>;">
                                                                        ₹<?php echo formatIndianCurrency($alloc['pending_amount']); ?>
                                                                    </span>
                                                                </td>
                                                            </tr>
                                                            <?php
                                                        endforeach; ?>
                                                        <?php
                                                    endif; ?>

                                                    <?php if (!empty($transport_allocations)): ?>
                                                        <tr class="bg-soft-warning" style="background: #fffbeb;">
                                                            <td colspan="6" class="fw-bold text-warning py-2 px-3 small">
                                                                <i class="fas fa-bus me-2"></i> TRANSPORT FEES
                                                            </td>
                                                        </tr>
                                                        <?php foreach ($transport_allocations as $alloc): ?>
                                                            <tr>
                                                                <td>
                                                                    <span
                                                                        class="fw-bold"><?php echo formatFeeKey($alloc['fee_component'], $studentData['current_class'] ?? ''); ?></span>
                                                                    <small
                                                                        class="text-muted d-block"><?php echo $alloc['category'] ?? ''; ?></small>
                                                                </td>
                                                                <td class="text-center fw-semibold">
                                                                    ₹<?php echo formatIndianCurrency($alloc['allocated_amount']); ?>
                                                                </td>
                                                                <td class="text-center text-warning">
                                                                    ₹<?php echo formatIndianCurrency($alloc['scholarship_amount']); ?>
                                                                </td>
                                                                <td class="text-center fw-bold text-primary">
                                                                    ₹<?php echo formatIndianCurrency($alloc['payable_amount']); ?>
                                                                </td>
                                                                <td class="text-center text-success">
                                                                    ₹<?php echo formatIndianCurrency($alloc['paid_amount']); ?>
                                                                </td>
                                                                <td
                                                                    class="text-center <?php echo $alloc['pending_amount'] > 0 ? 'text-danger' : 'text-success'; ?>">
                                                                    ₹<?php echo formatIndianCurrency($alloc['pending_amount']); ?>
                                                                </td>
                                                            </tr>
                                                            <?php
                                                        endforeach; ?>
                                                        <?php
                                                    endif; ?>
                                                </tbody>
                                                <tfoot class="bg-light">
                                                    <tr class="fw-bold">
                                                        <td>GRAND TOTAL</td>
                                                        <td class="text-center">
                                                            ₹<?php echo formatIndianCurrency($summary['total_allocated']); ?>
                                                        </td>
                                                        <td class="text-center">
                                                            ₹<?php echo formatIndianCurrency($summary['total_scholarship']); ?>
                                                        </td>
                                                        <td class="text-center">
                                                            ₹<?php echo formatIndianCurrency($summary['total_payable'] ?? ($summary['total_allocated'] - $summary['total_scholarship'])); ?>
                                                        </td>
                                                        <td class="text-center">
                                                            ₹<?php echo formatIndianCurrency($summary['total_paid']); ?>
                                                        </td>
                                                        <td class="text-center text-danger">
                                                            ₹<?php echo formatIndianCurrency($summary['total_pending_display']); ?>
                                                        </td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Operations Card -->
                    <div class="col-lg-4">
                        <div class="card shadow-sm border-0 mb-4 bg-white">
                            <div class="card-header bg-white border-bottom pt-3 pb-2 px-4">
                                <h6 class="fw-bold text-dark mb-0">
                                    <i class="fas fa-history me-2 text-primary"></i> Payment Timeline
                                </h6>
                            </div>
                            <div class="card-body p-3" style="max-height:580px;overflow-y:auto;">
                                <?php if (empty($payments)): ?>
                                    <div class="text-center py-5">
                                        <div class="bg-light d-inline-flex align-items-center justify-content-center rounded-circle mb-3"
                                            style="width: 80px; height: 80px;">
                                            <i class="fas fa-file-invoice fa-2x text-muted opacity-50"></i>
                                        </div>
                                        <h5 class="text-dark fw-bold">No Transactions Found</h5>
                                        <p class="text-muted small">This student has no payment history recorded yet.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="ledger-timeline">
                                        <?php foreach ($payments as $pay):
                                            $isCancelled = ($pay['status'] === 'cancelled' || $pay['status'] === 'cheque_return');
                                            $isChequeReturn = ($pay['status'] === 'cheque_return');
                                            $dotColor = $isCancelled ? '#ef4444' : '#10b981';
                                            if ($pay['payment_mode'] === 'discount')
                                                $dotColor = '#f59e0b';
                                            ?>
                                            <div class="timeline-item">
                                                <div class="timeline-dot" style="border-color: <?php echo $dotColor; ?>;"></div>
                                                <div class="timeline-content">
                                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                                        <div>
                                                            <div class="d-flex align-items-center gap-2 mb-1">
                                                                <span
                                                                    class="badge rounded-pill <?php echo $isCancelled ? 'bg-soft-danger text-danger' : 'bg-soft-success text-success'; ?>"
                                                                    style="background: <?php echo $isCancelled ? '#fee2e2' : '#d1fae5'; ?>;">
                                                                    <?php echo strtoupper($pay['payment_mode']); ?>
                                                                </span>
                                                                <?php if ($pay['payment_mode'] !== 'discount'): ?>
                                                                    <span class="text-muted small"><i class="fas fa-receipt me-1"></i>
                                                                        <?php echo $pay['receipt_no']; ?></span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <h4 class="fw-bold text-dark mb-0">
                                                                ₹<?php echo formatIndianCurrency($pay['amount']); ?>
                                                            </h4>
                                                            <small class="text-muted"><i class="far fa-calendar-alt me-1"></i>
                                                                <?php echo date('d M Y', strtotime($pay['payment_date'])); ?></small>
                                                        </div>
                                                        <div class="text-end">
                                                            <div class="btn-group shadow-sm">
                                                                <?php if (!$isCancelled): ?>
                                                                    <?php if ($pay['payment_mode'] !== 'discount'): ?>
                                                                        <button type="button" class="btn btn-light btn-sm"
                                                                            onclick="downloadReceipt('<?php echo $pay['id']; ?>')">
                                                                            <i class="fas fa-file-pdf me-1 text-danger"></i> PDF
                                                                        </button>
                                                                    <?php endif; ?>
                                                                    <?php if (hasAnyRole([ROLE_ACCOUNTANT, ROLE_SUPER_ADMIN, ROLE_PRINCIPLE])): ?>
                                                                        <button type="button" class="btn btn-light btn-sm text-danger"
                                                                            onclick="cancelReceipt('<?php echo $pay['id']; ?>', '<?php echo $pay['receipt_no']; ?>')">
                                                                            <i class="fas fa-trash-alt"></i>
                                                                        </button>
                                                                    <?php endif; ?>
                                                                <?php else: ?>
                                                                    <span
                                                                        class="badge bg-danger px-3"><?php echo $isChequeReturn ? 'CHEQUE RETURNED' : 'CANCELLED'; ?></span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="bg-light p-3 rounded-3">
                                                        <div class="row g-2">
                                                            <div class="col-6">
                                                                <small
                                                                    class="text-muted d-block uppercase ls-1 font-8">COMPONENT</small>
                                                                <span
                                                                    class="small fw-bold text-dark"><?php echo formatFeeKey($pay['fee_component'], $studentData['current_class'] ?? ''); ?></span>
                                                            </div>
                                                            <div class="col-6 text-end">
                                                                <small
                                                                    class="text-muted d-block uppercase ls-1 font-8">STATUS</small>
                                                                <span
                                                                    class="small fw-bold <?php echo $isCancelled ? 'text-danger' : 'text-success'; ?>"><?php echo strtoupper($pay['status'] ?? 'PAID'); ?></span>
                                                            </div>
                                                            <?php if ($pay['remarks']): ?>
                                                                <div class="col-12 mt-2 pt-2 border-top">
                                                                    <small class="text-muted d-block uppercase ls-1 font-8 mb-1">REMARKS
                                                                        / NOTE</small>
                                                                    <span
                                                                        class="small text-dark fst-italic">"<?php echo $pay['remarks']; ?>"</span>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="card shadow-sm border-0 bg-gradient-light"
                            style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);">
                            <div class="card-body p-4">
                                <h6 class="fw-bold text-dark mb-3 d-flex align-items-center">
                                    <i class="fas fa-info-circle text-primary me-2"></i>Ledger Guidelines
                                </h6>
                                <div class="space-y-3">
                                    <div class="d-flex gap-3">
                                        <div class="text-primary mt-1"><i class="fas fa-history small"></i></div>
                                        <p class="text-muted small mb-0">Timeline reflects transactions in reverse
                                            chronological order.</p>
                                    </div>
                                    <div class="d-flex gap-3">
                                        <div class="text-danger mt-1"><i class="fas fa-ban small"></i></div>
                                        <p class="text-muted small mb-0">Cancelled receipts are struck through and
                                            highlighted in red.</p>
                                    </div>
                                    <div class="d-flex gap-3">
                                        <div class="text-success mt-1"><i class="fas fa-file-export small"></i></div>
                                        <p class="text-muted small mb-0">Use the Excel Export at the top for bulk reporting.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php
            elseif ($student_id): ?>
                <div class="card border-0 shadow-sm bg-white p-5 text-center my-5">
                    <div class="bg-soft-info d-inline-flex align-items-center justify-content-center rounded-circle mx-auto mb-4"
                        style="width: 100px; height: 100px; background: #e0f2fe;">
                        <i class="fas fa-search fa-3x text-info"></i>
                    </div>
                    <h3 class="fw-bold text-dark">No Ledger Data Found</h3>
                    <p class="text-muted mx-auto" style="max-width: 500px;">Student with ID
                        <strong><?php echo $student_id; ?></strong> was not found or has no financial transactions recorded
                        for the current session.
                    </p>
                    <div class="mt-4">
                        <a href="student-ledger.php" class="btn btn-primary px-4"><i class="fas fa-redo me-2"></i>Try
                            Another Search</a>
                    </div>
                </div>
                <?php
            else: ?>
                <div class="card border-0 shadow-sm bg-white p-5 text-center my-5">
                    <div class="bg-soft-primary d-inline-flex align-items-center justify-content-center rounded-circle mx-auto mb-4"
                        style="width: 120px; height: 120px; background: #eef2ff;">
                        <i class="fas fa-user-graduate fa-4x text-primary" style="opacity: 0.8;"></i>
                    </div>
                    <h2 class="fw-bold text-dark">Account Ledger Search</h2>
                    <p class="text-muted mx-auto" style="max-width: 600px;">Enter a student ID, name, or phone number in the
                        search bar above to view their complete payment history, fee allocations, and current outstanding
                        balance.</p>
                    <div class="mt-4 d-flex justify-content-center gap-3">
                        <div class="text-start bg-light p-3 rounded-3 d-flex align-items-center gap-3"
                            style="min-width: 250px;">
                            <i class="fas fa-receipt text-success fa-lg"></i>
                            <div>
                                <small class="text-muted d-block">Quick Tip</small>
                                <span class="fw-bold small">Direct PDF Downloads</span>
                            </div>
                        </div>
                        <div class="text-start bg-light p-3 rounded-3 d-flex align-items-center gap-3"
                            style="min-width: 250px;">
                            <i class="fas fa-file-excel text-primary fa-lg"></i>
                            <div>
                                <small class="text-muted d-block">Reporting</small>
                                <span class="fw-bold small">Export to Spreadsheet</span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php
            endif; ?>
        </div>
    </section>
</div>

<!-- Discount Modal -->
<div class="modal fade" id="discountModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-warning py-4 px-4 border-0">
                <div class="d-flex align-items-center">
                    <div class="bg-white bg-opacity-25 rounded-circle p-3 me-3">
                        <i class="fas fa-tag text-white fa-lg"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-white mb-0">Apply Special Waiver</h5>
                        <p class="text-white opacity-75 small mb-0">Post-admission fee adjustment and scholarship</p>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="discountForm">
                <div class="modal-body p-4">
                    <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
                    <div class="row g-4 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark">Waiver Type</label>
                            <select name="type" id="discountType" class="form-select py-2" required>
                                <option value="flat">Fixed Amount (Flat Adjustment)</option>
                                <option value="smart">Percentage (Smart Waiver Utility)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark">Adjustment Value</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">₹</span>
                                <input type="number" name="amount" id="discountAmount"
                                    class="form-control py-2 border-start-0" placeholder="0.00" required>
                            </div>
                        </div>
                    </div>

                    <div id="smartOptions" class="card bg-light border-0 mb-4 d-none overflow-hidden">
                        <div class="card-header bg-white py-3 border-0">
                            <h6 class="fw-bold small mb-0 text-primary uppercase ls-1">SMART WAIVER UTILITY</h6>
                        </div>
                        <div class="card-body p-4">
                            <p class="text-muted small mb-3">Select components to apply the percentage waiver on:</p>
                            <div class="d-flex flex-wrap gap-3 mb-4">
                                <?php
                                $all_pending = array_merge($fee_allocations, $hostel_allocations, $transport_allocations);
                                foreach ($all_pending as $alloc):
                                    if ($alloc['pending_amount'] > 0): ?>
                                        <div class="form-check bg-white px-3 py-2 rounded-2 border shadow-xs d-flex align-items-center gap-2"
                                            style="padding-left: 2.5rem;">
                                            <input class="form-check-input fee-check" type="checkbox"
                                                value="<?php echo $alloc['pending_amount']; ?>"
                                                id="chk_<?php echo $alloc['fee_component']; ?>"
                                                style="width: 1.2rem; height: 1.2rem; margin-left: -1.7rem;">
                                            <label class="form-check-label small fw-semibold text-dark mb-0"
                                                for="chk_<?php echo $alloc['fee_component']; ?>">
                                                <?php echo formatFeeKey($alloc['fee_component']); ?>
                                                <span
                                                    class="text-muted fw-normal ms-1">(₹<?php echo formatIndianCurrency($alloc['pending_amount']); ?>)</span>
                                            </label>
                                        </div>
                                        <?php
                                    endif;
                                endforeach; ?>
                            </div>
                            <div
                                class="row align-items-center bg-white p-3 rounded-3 border-start border-4 border-primary">
                                <div class="col-auto"><label class="small fw-bold text-dark">CALCULATE
                                        PERCENTAGE:</label></div>
                                <div class="col-4">
                                    <div class="input-group input-group-sm shadow-sm">
                                        <input type="number" id="smartPercent" class="form-control text-center fw-bold"
                                            value="0" min="0" max="100">
                                        <span class="input-group-text bg-primary text-white border-primary">%</span>
                                    </div>
                                </div>
                                <div class="col text-end">
                                    <span class="text-muted small">Auto-calculates the final amount</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Reason for Waiver</label>
                        <textarea name="reason" class="form-control" rows="3"
                            placeholder="Explain why this discount/waiver is being given..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light p-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-warning px-4 text-white" onclick="submitDiscount()">
                        Apply Waiver Now
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const discountType = document.getElementById('discountType');
        const smartOptions = document.getElementById('smartOptions');
        const discountAmount = document.getElementById('discountAmount');
        const smartPercent = document.getElementById('smartPercent');
        const feeChecks = document.querySelectorAll('.fee-check');

        if (discountType) {
            discountType.addEventListener('change', function () {
                if (this.value === 'smart') {
                    smartOptions.classList.remove('d-none');
                    discountAmount.readOnly = true;
                    calculateSmartAmount();
                } else {
                    smartOptions.classList.add('d-none');
                    discountAmount.readOnly = false;
                }
            });
        }

        function calculateSmartAmount() {
            let totalBase = 0;
            feeChecks.forEach(cb => { if (cb.checked) totalBase += parseFloat(cb.value || 0); });
            const percent = parseFloat(smartPercent.value || 0);
            discountAmount.value = Math.round((totalBase * percent) / 100);
        }

        if (feeChecks) feeChecks.forEach(cb => cb.addEventListener('change', calculateSmartAmount));
        if (smartPercent) smartPercent.addEventListener('input', calculateSmartAmount);
    });

    function submitDiscount() {
        const form = document.getElementById('discountForm');
        if (!form.checkValidity()) { form.reportValidity(); return; }

        const data = Object.fromEntries(new FormData(form).entries());

        showToast('info', 'Applying Waiver...', 'Please wait.');

        fetch('<?php echo BASE_URL; ?>/counselling-backend/controllers/payments/discount-save.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
            .then(res => res.json())
            .then(response => {
                if (response.success) {
                    showToast('success', 'Success', 'Waiver applied successfully');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast('error', 'Error', response.message || 'Failed to apply waiver');
                }
            })
            .catch(err => showToast('error', 'Error', 'An error occurred during communication'));
    }

    // downloadReceipt is now handled by receipt-utils.js

    function downloadLedgerPDF(studentId) {
        generateSecurePDF('ledger-export-pdf.php', { student_id: studentId });
    }

    function cancelReceipt(paymentId, receiptNo) {
        const reasons = <?php echo json_encode($cancellation_reasons); ?>;
        let optionsHtml = '<option value="">-- Select Reason --</option>';

        reasons.forEach(group => {
            optionsHtml += `<optgroup label="${group.category}">`;
            group.options.forEach(opt => {
                optionsHtml += `<option value="${opt}">${opt}</option>`;
            });
            optionsHtml += `</optgroup>`;
        });
        optionsHtml += `<optgroup label="Other"><option value="Other">Other</option></optgroup>`;

        showConfirm({
            title: 'Cancel Receipt',
            message: `
                <div class="text-start">
                    <p>Are you sure you want to cancel Receipt <strong>#${receiptNo}</strong>?</p>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Reason for Cancellation</label>
                        <select id="cancelReasonInput" class="form-select" onchange="toggleCancelOther(this)">
                            ${optionsHtml}
                        </select>
                    </div>
                    <div class="mb-3 d-none" id="cancelOtherReasonContainer">
                        <label class="form-label fw-bold small">Type Custom Reason</label>
                        <textarea id="cancelOtherReasonInput" class="form-control" rows="2" placeholder="Enter custom reason..."></textarea>
                    </div>
                    <p class="text-danger small"><i class="fas fa-exclamation-triangle me-1"></i> This action will void the payment and cannot be undone.</p>
                </div>
            `,
            confirmText: 'Yes, Cancel Receipt',
            confirmButtonClass: 'btn-danger',
            onConfirm: function () {
                let reason = $('#cancelReasonInput').val();
                const otherReason = $('#cancelOtherReasonInput').val().trim();

                if (reason === 'Other') {
                    if (otherReason.length < 5) {
                        showToast('error', 'Error', 'Please provide a valid custom reason (min 5 characters).');
                        return;
                    }
                    reason = otherReason;
                }

                if (!reason || reason.trim().length < 5) {
                    showToast('error', 'Error', 'Please provide a valid cancellation reason (min 5 characters).');
                    return;
                }

                showToast('info', 'Cancelling...', 'Please wait.');
                $.api.post('payments/cancel-receipt', {
                    payment_id: paymentId,
                    reason: reason,
                    is_new_reason: ($('#cancelReasonInput').val() === 'Other')
                })
                    .then(res => {
                        if (res.success) {
                            showToast('success', 'Cancelled', 'Receipt voided successfully');
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            showToast('error', 'Error', res.message || 'Failed to cancel receipt');
                        }
                    })
                    .catch(() => showToast('error', 'Error', 'Communication failure'));
            }
        });
    }

    function toggleCancelOther(select) {
        const container = document.getElementById('cancelOtherReasonContainer');
        if (select.value === 'Other') {
            container.classList.remove('d-none');
            document.getElementById('cancelOtherReasonInput').focus();
        } else {
            container.classList.add('d-none');
        }
    }
</script>

<?php include '../../../include/footer.php'; ?>