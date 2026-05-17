<?php
/**
 * Fee Due Report
 * Shows all students with pending/outstanding fees
 */

require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/common/constants.php';
require_once __DIR__ . '/../../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PAGINATION_FILE;
require_once __DIR__ . '/../../../../common/helpers/format_helper.php';
require_once HELPERS_PATH . 'fee_helper.php';

// Check access
if (!hasRole(ROLE_ACCOUNTANT) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "Fee Due Report";
$page_breadcrumb = "Fee Due Report";

// Handle POST filters
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $filters = [
        'group' => $_POST['group'] ?? ($_SESSION['pending_fees_filters']['group'] ?? ''),
        'course' => $_POST['course'] ?? ($_SESSION['pending_fees_filters']['course'] ?? ''),
        'school' => $_POST['school'] ?? ($_SESSION['pending_fees_filters']['school'] ?? ''),
        'min_amount' => $_POST['min_amount'] ?? ($_SESSION['pending_fees_filters']['min_amount'] ?? ''),
        'max_amount' => $_POST['max_amount'] ?? ($_SESSION['pending_fees_filters']['max_amount'] ?? ''),
        'search' => $_POST['search'] ?? ($_SESSION['pending_fees_filters']['search'] ?? ''),
        'page' => $_POST['page'] ?? 1,
        'per_page' => $_POST['per_page'] ?? ($_SESSION['pending_fees_filters']['per_page'] ?? 15)
    ];

    // If applying filters (not just pagination), reset to page 1
    if (isset($_POST['apply_filter'])) {
        $filters['page'] = 1;
    }

    $_SESSION['pending_fees_filters'] = $filters;
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Get filters from session
$filters = $_SESSION['pending_fees_filters'] ?? [
    'group' => '',
    'course' => '',
    'school' => '',
    'min_amount' => '',
    'max_amount' => '',
    'search' => '',
    'page' => 1,
    'per_page' => 15
];

$filter_group = $filters['group'];
$filter_course = $filters['course'];
$filter_school = $filters['school'];
$filter_min_amount = $filters['min_amount'];
$filter_max_amount = $filters['max_amount'];
$search = $filters['search'];
$page = max(1, (int) $filters['page']);
$perPage = max(1, (int) $filters['per_page']);
$offset = ($page - 1) * $perPage;

$dbOps = new DatabaseOperations();

// Get filter options
$groups = $dbOps->customSelect("SELECT id, group_name FROM tbl_group WHERE is_active = 1 ORDER BY group_name", []);
$schools = $dbOps->customSelect("SELECT id, school_name FROM tbl_schools WHERE is_active = 1 ORDER BY school_name", []);

// 1. Unified Calculation (Aligned with Student Ledger - Only Hostel Security, no Rent)
$h_set = $dbOps->customSelect("SELECT security_deposit FROM tbl_hostel_fee_settings WHERE is_active = 1 LIMIT 1")[0] ?? ['security_deposit' => 0];
$t_set = $dbOps->customSelect("SELECT transport_fee, gst_rate FROM tbl_transport_fee_settings WHERE is_active = 1 LIMIT 1")[0] ?? ['transport_fee' => 0, 'gst_rate' => 0];
$sec_dep = floatval($h_set['security_deposit']);
$trans_fee = floatval($t_set['transport_fee']) * (1 + floatval($t_set['gst_rate']) / 100);

$calc_base_academic_sql = "COALESCE(fc.total_fees, 0)";
$calc_base_hostel_sql = "(IF(r.hostel_required = 'Yes', $sec_dep, 0) + COALESCE(fc.hostel_fee, 0))";
$calc_base_transport_sql = "IF(r.transport_required = 'Yes', $trans_fee, 0)";
$calc_base_fee_sql = "($calc_base_academic_sql + $calc_base_hostel_sql + $calc_base_transport_sql)";

$universal_comps = "'hostel_security', 'transport_fee', 'admission_fee', 'security_deposit', 'token_fee', 'tuition_fee_part1', 'registration_fee', 'school_fee', 'trust_facilities_fee', 'tuition_fee_part2'";

// Academic Paid
$academic_paid = "(SELECT COALESCE(SUM(amount), 0) FROM tbl_payments p WHERE p.student_id = r.id AND p.status = 'paid' AND p.fee_component NOT IN ('hostel_fee', 'hostel_security', 'transport_fee') AND (p.term_id = es.current_term_id OR p.fee_component IN ($universal_comps)))";

// Hostel Paid (Including historical security)
$hostel_paid = "(SELECT COALESCE(SUM(amount), 0) FROM tbl_payments p WHERE p.student_id = r.id AND p.status = 'paid' AND p.fee_component IN ('hostel_fee', 'hostel_security'))";

// Transport Paid
$transport_paid = "(SELECT COALESCE(SUM(amount), 0) FROM tbl_payments p WHERE p.student_id = r.id AND p.status = 'paid' AND p.fee_component = 'transport_fee')";

$scholarship_sql = "(COALESCE(r.scholarship_amount, 0) + COALESCE(r.additional_scholarship_amount, 0))";
$discount_sql = "COALESCE(es.post_admission_discount_amount, 0)";
$calc_waiver_sql = "($scholarship_sql + $discount_sql)";

// Total Paid (For display in list) - Including Without GST for accurate pending calculation in SQL
$without_gst_paid = "(SELECT 0)"; // No longer using separate table
$calc_paid_sql = "($academic_paid + $hostel_paid + $transport_paid + $without_gst_paid)";

// Categorical Pending Calculation (Matches calculateStudentFeeSummary behavior)
$pending_academic = "GREATEST(0, $calc_base_academic_sql - $academic_paid - $calc_waiver_sql)";

// Hostel requires dynamic baseline matching for overpayments just like fee_helper
$hostel_base_dynamic = "GREATEST($calc_base_hostel_sql, $hostel_paid)";
$pending_hostel = "GREATEST(0, $hostel_base_dynamic - $hostel_paid)";

$pending_transport = "GREATEST(0, $calc_base_transport_sql - $transport_paid)";

// Final global pending is the sum of isolated categories ensuring no cross-offsets.
$calc_pending_sql = "($pending_academic + $pending_hostel + $pending_transport)";

// Build query conditions
$where_conditions = [
    "es.is_active = 1",
    "$calc_pending_sql > 0"
];
$params = [];

if (!empty($filter_group)) {
    $where_conditions[] = "r.group_id = ?";
    $params[] = $filter_group;
}

if (!empty($filter_course)) {
    if ($filter_course === '11th') {
        $where_conditions[] = "r.course_id IN (1, 2)";
    } elseif ($filter_course === '12th') {
        $where_conditions[] = "r.course_id IN (4, 5)";
    } elseif ($filter_course === 'Reneet') {
        $where_conditions[] = "r.course_id = 6";
    } else {
        $where_conditions[] = "r.course_id = ?";
        $params[] = $filter_course;
    }
}

if (!empty($filter_school)) {
    $where_conditions[] = "r.school_id = ?";
    $params[] = $filter_school;
}

if (!empty($filter_min_amount)) {
    $where_conditions[] = "$calc_pending_sql >= ?";
    $params[] = $filter_min_amount;
}

if (!empty($filter_max_amount)) {
    $where_conditions[] = "$calc_pending_sql <= ?";
    $params[] = $filter_max_amount;
}

if (!empty($search)) {
    $where_conditions[] = "(CONCAT(r.surname, ' ', r.student_name, ' ', IFNULL(r.fathers_name, '')) LIKE ? OR r.mob LIKE ? OR r.id LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_sql = implode(' AND ', $where_conditions);

// 1. Get Total Count
$countSql = "SELECT COUNT(DISTINCT r.id) as total
             FROM tbl_gm_std_registration r
             JOIN tbl_enrolled_students es ON r.id = es.registration_id
             LEFT JOIN tbl_group g ON r.group_id = g.id
             LEFT JOIN tbl_courses c ON r.course_id = c.id
             LEFT JOIN tbl_fee_config fc ON r.course_id = fc.course_id AND r.medium_id = fc.medium_id AND r.group_id = fc.group_id AND r.school_id = fc.school_id AND fc.is_active = 1
             WHERE $where_sql";

$countResult = $dbOps->customSelect($countSql, $params);
$totalRecords = $countResult[0]['total'] ?? 0;
$totalPages = ceil($totalRecords / $perPage);

// 2. Get Data
$sql = "SELECT 
            MAX(es.enrollment_id) as enrollment_id,
            r.id as student_id,
            MAX(CONCAT(r.surname, ' ', r.student_name, ' ', COALESCE(r.fathers_name, ''))) as student_name,
            MAX(r.mob) as mobile,
            MAX(r.fathers_name) as fathers_name,
            NULL as father_mobile,
            MAX(r.email) as email,
            MAX(g.group_name) as group_name,
            MAX(c.course_name) as course_name,
            MAX($calc_base_academic_sql) as course_fee_base,
            MAX($calc_base_hostel_sql) as hostel_fee_base,
            MAX($calc_base_transport_sql) as transport_fee_base,
            MAX($calc_base_fee_sql) as total_fee,
            MAX($academic_paid) as course_paid,
            MAX($hostel_paid) as hostel_paid,
            MAX($transport_paid) as transport_paid,
            MAX($calc_paid_sql) as total_paid,
            MAX($scholarship_sql) as scholarship_waiver,
            MAX($discount_sql) as discount_waiver,
            MAX($calc_waiver_sql) as total_waiver,
            MAX($pending_academic) as course_pending,
            MAX($pending_hostel) as hostel_pending,
            MAX($pending_transport) as transport_pending,
            MAX($calc_pending_sql) as pending_amount,
            MAX(sfa.due_date) as due_date
        FROM tbl_gm_std_registration r
        JOIN tbl_enrolled_students es ON r.id = es.registration_id
        LEFT JOIN tbl_group g ON r.group_id = g.id
        LEFT JOIN tbl_courses c ON r.course_id = c.id
        LEFT JOIN (SELECT student_id, MAX(due_date) as due_date FROM tbl_student_fee_allocation GROUP BY student_id) sfa ON r.id = sfa.student_id
        LEFT JOIN tbl_fee_config fc ON r.course_id = fc.course_id AND r.medium_id = fc.medium_id AND r.group_id = fc.group_id AND r.school_id = fc.school_id AND fc.is_active = 1
        WHERE $where_sql
        GROUP BY r.id";

// 2. Get Paginated Data for UI
$sql_paginated = $sql . " ORDER BY pending_amount ASC LIMIT " . (int) $perPage . " OFFSET " . (int) $offset;
$pendingFeesRaw = $dbOps->customSelect($sql_paginated, $params);

// REFACTOR: Enhance data with calculateStudentFeeSummary for 100% accuracy (matches Ledger)
$pendingFees = [];
foreach ($pendingFeesRaw as $row) {
    $summary = calculateStudentFeeSummary($conn, $row['student_id'], true);
    // Overwrite the SQL-calculated values with precise helper values
    $row['total_fee'] = $summary['total_allocated'];
    $row['total_paid'] = $summary['total_paid'];
    $row['total_waiver'] = $summary['total_waiver'];
    $row['pending_amount'] = $summary['total_pending'];

    // Categorical breakdown for export/PDF consistency if needed
    $row['course_pending'] = $summary['detailed_allocations']['tuition_fee_part1']['pending_amount'] ?? 0; // Simplified for report
    // In FeeDue report, we often just need the totals, but we'll keep the structure
    $pendingFees[] = $row;
}

// 3. Get ALL Data for Export (No LIMIT, sorted by Name ASC)
$export_sql = $sql . " ORDER BY student_name ASC";
$all_pending_fees_raw = $dbOps->customSelect($export_sql, $params);
$all_pending_fees = [];
foreach ($all_pending_fees_raw as $row) {
    $summary = calculateStudentFeeSummary($conn, $row['student_id'], true);
    $row['total_fee'] = $summary['total_allocated'];
    $row['total_paid'] = $summary['total_paid'];
    $row['total_waiver'] = $summary['total_waiver'];
    $row['pending_amount'] = $summary['total_pending'];
    $all_pending_fees[] = $row;
}


include '../../../include/header.php';
include '../../../include/navbar.php';
include '../../../include/sidebar.php';
?>

<link rel="stylesheet" href="<?php echo PORTAL_URL; ?>/assets/css/modules/reports/financial/pending-fees.php.css">



<div class="container-fluid">

    <!-- Filters Toggle & Content -->
    <!-- Added Toggle Button -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>
            Students with Pending Fees (<?php echo $totalRecords; ?>)
        </h5>
        <div class="d-flex gap-2">
            <!-- Export Buttons -->
            <button class="btn btn-sm btn-success" onclick="exportToExcel()">
                <i class="fas fa-file-excel me-1"></i> Excel
            </button>
            <button class="btn btn-sm btn-danger" onclick="exportToPDF()">
                <i class="fas fa-file-pdf me-1"></i> Export PDF
            </button>
            <!-- Filters Toggle -->
            <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="collapse"
                data-bs-target="#filterCollapse" aria-expanded="false" aria-controls="filterCollapse">
                <i class="fas fa-filter me-1"></i> Toggle Filters
            </button>
        </div>
    </div>

    <div class="collapse" id="filterCollapse">
        <div class="card filter-card mb-4">
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <input type="hidden" name="apply_filter" value="1">
                    <div class="col-md-3">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control"
                            value="<?php echo htmlspecialchars($search ?? ''); ?>" placeholder="Name or Mobile">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Group</label>
                        <select name="group" class="form-select">
                            <option value="">All Groups</option>
                            <?php foreach ($groups as $grp): ?>
                                <option value="<?php echo $grp['id']; ?>" <?php echo $filter_group == $grp['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($grp['group_name'] ?? ''); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Standard</label>
                        <select name="course" class="form-select">
                            <option value="">All Standards</option>
                            <option value="11th" <?php echo $filter_course === '11th' ? 'selected' : ''; ?>>11th</option>
                            <option value="12th" <?php echo $filter_course === '12th' ? 'selected' : ''; ?>>12th</option>
                            <option value="Reneet" <?php echo $filter_course === 'Reneet' ? 'selected' : ''; ?>>Reneet</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">School</label>
                        <select name="school" class="form-select">
                            <option value="">All Schools</option>
                            <?php foreach ($schools as $sch): ?>
                                <option value="<?php echo $sch['id']; ?>" <?php echo $filter_school == $sch['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($sch['school_name'] ?? ''); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!-- Next Row due to bootstrap grid if cols exceed 12, or just wraps -->
                    <div class="col-md-2">
                        <label class="form-label">Min Amount (₹)</label>
                        <input type="number" name="min_amount" class="form-control"
                            value="<?php echo htmlspecialchars($filter_min_amount ?? ''); ?>" placeholder="0">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Max Amount (₹)</label>
                        <input type="number" name="max_amount" class="form-control"
                            value="<?php echo htmlspecialchars($filter_max_amount ?? ''); ?>" placeholder="999999">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Per Page</label>
                        <select name="per_page" class="form-select">
                            <option value="15" <?php echo $perPage == 15 ? 'selected' : ''; ?>>15</option>
                            <option value="25" <?php echo $perPage == 25 ? 'selected' : ''; ?>>25</option>
                            <option value="50" <?php echo $perPage == 50 ? 'selected' : ''; ?>>50</option>
                            <option value="100" <?php echo $perPage == 100 ? 'selected' : ''; ?>>100</option>
                            <option value="200" <?php echo $perPage == 200 ? 'selected' : ''; ?>>200</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-light">
                            <i class="fas fa-filter"></i> Apply
                        </button>
                        <a href="pending-fees.php?reset=1" class="btn btn-outline-light" onclick="resetFilters(event)">
                            <i class="fas fa-redo"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead>
                        <tr>
                            <th class="border-0">#</th>
                            <th class="border-0">Student Name</th>
                            <th class="border-0">Standard</th>
                            <th class="border-0">Group</th>
                            <th class="border-0 hide-on-laptop">Mobile</th>
                            <th class="border-0 text-end">Total Allocated</th>
                            <th class="border-0 text-end">Paid Amount</th>
                            <th class="border-0 text-end hide-on-laptop">Waiver / Discount</th>
                            <th class="border-0 text-end">Pending Amount</th>
                            <th class="border-0 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pendingFees)): ?>
                            <tr>
                                <td colspan="21" class="text-center py-4">
                                    <i class="fas fa-check-circle text-success fa-3x mb-3"></i>
                                    <p class="mb-0">No pending fees found with current filters</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php
                            $sno = $offset + 1; // Start serial number from offset
                            foreach ($pendingFees as $student): ?>
                                <tr>
                                    <td><?php echo $sno++; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($student['student_name'] ?? ''); ?></strong>
                                        <br>
                                        <small
                                            class="text-muted hide-on-laptop"><?php echo htmlspecialchars($student['email'] ?? ''); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($student['course_name'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($student['group_name'] ?? '-'); ?></td>
                                    <td class="hide-on-laptop">
                                        <?php echo htmlspecialchars($student['mobile'] ?? '-'); ?>
                                        <?php if (!empty($student['father_mobile'])): ?>
                                            <br><small class="text-muted">Parent:
                                                <?php echo htmlspecialchars($student['father_mobile'] ?? ''); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end fw-bold">
                                        ₹<?php echo formatIndianCurrency($student['total_fee'] ?? 0); ?></td>

                                    <td class="text-success text-end fw-bold">
                                        ₹<?php echo formatIndianCurrency($student['total_paid'] ?? 0); ?></td>

                                    <td class="text-info text-end border-end border-light hide-on-laptop fw-bold">
                                        ₹<?php echo formatIndianCurrency($student['total_waiver'] ?? 0); ?></td>

                                    <?php
                                    $total_pending = $student['pending_amount'] ?? 0;
                                    ?>
                                    <td
                                        class="text-end border-end border-light fw-bold <?php echo ($total_pending > 0) ? 'amount-high' : ''; ?>">
                                        ₹<?php echo formatIndianCurrency($total_pending); ?></td>
                                    <td class="action-btns">
                                        <a href="<?php echo PORTAL_URL; ?>/modules/payments/add-payment.php?student_id=<?php echo $student['student_id']; ?>"
                                            class="btn btn-sm btn-primary" title="Collect Fee">
                                            <i class="fas fa-rupee-sign"></i>
                                        </a>
                                        <a href="student-ledger.php?student_id=<?php echo $student['student_id']; ?>"
                                            class="btn btn-sm btn-info" title="View Ledger">
                                            <i class="fas fa-book"></i>
                                        </a>
                                        <button class="btn btn-sm btn-warning" title="Send Reminder"
                                            onclick="sendReminder(<?php echo $student['student_id']; ?>)">
                                            <i class="fas fa-bell"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Controls -->
            <div class="card-footer border-top bg-white">
                <?php echo renderPaginationPost($page, $totalPages, 2, $perPage, [], $totalRecords, 'entries'); ?>
            </div>
        </div>
    </div>
</div>

<!-- Hidden Table for Export -->
<table class="d-none" id="pendingFeesExportTable">
    <thead>
        <tr>
            <th>S.No</th>
            <th>Enrollment ID</th>
            <th>Student Name</th>
            <th>Standard</th>
            <th>Group</th>
            <th>Mobile</th>
            <th>Total Allocated Fee</th>
            <th>Total Paid</th>
            <th>Total Waiver</th>
            <th>Total Pending</th>
            <th>Due Date</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $ex_sno = 1;
        foreach ($all_pending_fees as $pf): ?>
            <tr>
                <td><?php echo $ex_sno++; ?></td>
                <td><?php echo htmlspecialchars($pf['enrollment_id'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($pf['student_name'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($pf['course_name'] ?? '-'); ?></td>
                <td><?php echo htmlspecialchars($pf['group_name'] ?? '-'); ?></td>
                <td><?php echo htmlspecialchars($pf['mobile'] ?? '-'); ?></td>

                <td><?php echo round($pf['total_fee'] ?? 0); ?></td>
                <td><?php echo round($pf['total_paid'] ?? 0); ?></td>
                <td><?php echo round($pf['total_waiver'] ?? 0); ?></td>
                <td><?php echo round($pf['pending_amount'] ?? 0); ?></td>

                <td><?php echo $pf['due_date'] ? date('d-m-Y', strtotime($pf['due_date'])) : '-'; ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php include '../../../include/footer.php'; ?>

<script src="<?php echo BASE_URL; ?>/assets/vendor/xlsx/xlsx.full.min.js"></script>
<script>
    function exportToExcel() {
        const table = document.getElementById('pendingFeesExportTable');
        const wb = XLSX.utils.table_to_book(table, { sheet: "Fee Due Report" });
        XLSX.writeFile(wb, 'Fee_Due_Report_<?php echo date('Y-m-d'); ?>.xlsx');
    }

    function exportToPDF() {
        window.location.href = 'pending-fees-pdf.php';
    }

    function sendReminder(studentId) {
        if (confirm('Send fee reminder to this student?')) {
            // AJAX call to send reminder
            fetch('<?php echo PORTAL_URL; ?>/api/send-fee-reminder.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ student_id: studentId })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Reminder sent successfully!');
                    } else {
                        alert('Failed to send reminder: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error sending reminder');
                });
        }
    }

    function resetFilters(e) {
        e.preventDefault();
        // Post to same page with empty filters or separate reset endpoint?
        // Easiest is to just create a form and submit it, or use fetch
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = window.location.href;

        // Add empty inputs to implicitely reset, or logic in backend to handle reset
        // In our backend logic: if we post empty values, strict overwrite happening?
        // Not exactly, we do ?? check against session. But if we post empty strings, they become empty strings.
        // Let's manually append inputs
        ['group', 'course', 'school', 'min_amount', 'max_amount', 'per_page', 'search'].forEach(field => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = field;
            input.value = field === 'per_page' ? '15' : '';
            form.appendChild(input);
        });

        // Also reset page
        const pageInput = document.createElement('input');
        pageInput.type = 'hidden';
        pageInput.name = 'page';
        pageInput.value = '1';
        form.appendChild(pageInput);

        document.body.appendChild(form);
        form.submit();
    }
</script>