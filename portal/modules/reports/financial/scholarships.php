<?php
/**
 * Scholarship Applied Report
 */

require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/common/constants.php';
require_once __DIR__ . '/../../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PAGINATION_FILE;
require_once __DIR__ . '/../../../../common/helpers/format_helper.php';

if (!hasRole(ROLE_ACCOUNTANT) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "Scholarship Report" ;

// Handle POST filters
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $filters = [
        'scholarship_type' => $_POST['scholarship_type'] ?? ($_SESSION['scholarship_filters']['scholarship_type'] ?? ''),
        'course_id' => $_POST['course_id'] ?? ($_SESSION['scholarship_filters']['course_id'] ?? ''),
        'search' => $_POST['search'] ?? ($_SESSION['scholarship_filters']['search'] ?? ''),
        'page' => $_POST['page'] ?? 1,
        'per_page' => $_POST['per_page'] ?? ($_SESSION['scholarship_filters']['per_page'] ?? 15)
    ];

    if (isset($_POST['apply_filter'])) {
        $filters['page'] = 1;
    }

    $_SESSION['scholarship_filters'] = $filters;
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Get filters from session
$filters = $_SESSION['scholarship_filters'] ?? [
    'scholarship_type' => '',
    'course_id' => '',
    'search' => '',
    'page' => 1,
    'per_page' => 15
];

$scholarship_type_id = $filters['scholarship_type'];
$course_id = $filters['course_id'];
$search = $filters['search'];
$page = max(1, (int) $filters['page']);
$perPage = max(1, (int) $filters['per_page']);
$offset = ($page - 1) * $perPage;

$dbOps = new DatabaseOperations();

// Build WHERE conditions
$where_conditions = ["(r.scholarship_rule_id IS NOT NULL OR r.scholarship_amount > 0)"];
$params = [];

if (!empty($scholarship_type_id)) {
    $where_conditions[] = "sr.scholarship_type_id = ?";
    $params[] = $scholarship_type_id;
}

if (!empty($course_id)) {
    if ($course_id === '11th') {
        $where_conditions[] = "r.course_id IN (1, 2)";
    } elseif ($course_id === '12th') {
        $where_conditions[] = "r.course_id IN (4, 5)";
    } elseif ($course_id === 'Reneet') {
        $where_conditions[] = "r.course_id = 6";
    } else {
        $where_conditions[] = "r.course_id = ?";
        $params[] = $course_id;
    }
}

if (!empty($search)) {
    $searchTerm = "%" . $search . "%";
    $where_conditions[] = "(CONCAT(r.surname, ' ', r.student_name, ' ', IFNULL(r.fathers_name, '')) LIKE ? OR r.mob LIKE ? OR r.id LIKE ? OR es.enrollment_no LIKE ?)";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$where_sql = implode(' AND ', $where_conditions);

// 1. Get Total Count for Pagination
$countSql = "SELECT COUNT(*) as total
             FROM tbl_gm_std_registration r
             LEFT JOIN tbl_scholarship_rules sr ON r.scholarship_rule_id = sr.id
             LEFT JOIN tbl_scholarship_types st ON sr.scholarship_type_id = st.id
             LEFT JOIN tbl_enrolled_students es ON es.enrollment_id = r.enrollment_id AND es.is_active = 1
             LEFT JOIN tbl_courses c ON r.course_id = c.id
             WHERE $where_sql";

$countResult = $dbOps->customSelect($countSql, $params);
$totalRecords = $countResult[0]['total'] ?? 0;
$totalPages = ceil($totalRecords / $perPage);

// 2. Build main query for data
$sql = "SELECT r.id, r.surname, r.student_name, r.fathers_name, r.mob, 
                 r.scholarship_amount, r.scholarship_percentage,
                 st.type_name as scholarship_type,
                 c.course_name as current_class,
                 es.enrollment_no
          FROM tbl_gm_std_registration r
          LEFT JOIN tbl_scholarship_rules sr ON r.scholarship_rule_id = sr.id
          LEFT JOIN tbl_scholarship_types st ON sr.scholarship_type_id = st.id
          LEFT JOIN tbl_enrolled_students es ON es.enrollment_id = r.enrollment_id AND es.is_active = 1
          LEFT JOIN tbl_courses c ON r.course_id = c.id
          WHERE $where_sql";

// 3. Get Paginated Data for UI
$sql_paginated = $sql . " ORDER BY st.type_name, r.surname, r.student_name LIMIT " . (int) $perPage . " OFFSET " . (int) $offset;
$students = $dbOps->customSelect($sql_paginated, $params);

if ($students === false) {
    $students = [];
}

// 4. Get ALL Data for Export (No LIMIT)
$export_sql = $sql . " ORDER BY st.type_name, r.surname, r.student_name";
$all_students = $dbOps->customSelect($export_sql, $params);

if ($all_students === false) {
    $all_students = [];
}

// Get scholarship types for filter
$scholarship_types = $dbOps->customSelect("SELECT id, type_name FROM tbl_scholarship_types WHERE is_active = 1 ORDER BY type_name", []);

// Summary stats (Global Totals)
$sumSql = "SELECT SUM(r.scholarship_amount) as total_amount
           FROM tbl_gm_std_registration r
           LEFT JOIN tbl_scholarship_rules sr ON r.scholarship_rule_id = sr.id
           LEFT JOIN tbl_enrolled_students es ON es.enrollment_id = r.enrollment_id AND es.is_active = 1
           WHERE $where_sql";
$sumResult = $dbOps->customSelect($sumSql, $params);
$totalScholarshipAmount = $sumResult[0]['total_amount'] ?? 0;
$totalStudents = $totalRecords;

include '../../../include/header.php';
include '../../../include/navbar.php';
include '../../../include/sidebar.php';
?>

<div class="container-fluid">
    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-6 col-lg-3">
            <div class="small-box bg-warning">
                <div class="inner">
                    <h3><?php echo $totalStudents; ?></h3>
                    <p>Total Students</p>
                </div>
                <div class="icon"><i class="fas fa-users"></i></div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="small-box bg-success text-white">
                <div class="inner">
                    <h3>₹<?php echo formatIndianCurrency($totalScholarshipAmount); ?></h3>
                    <p>Total Scholarship Amount</p>
                </div>
                <div class="icon"><i class="fas fa-rupee-sign"></i></div>
            </div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card mb-4 mt-2">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0"><i class="fas fa-filter me-2"></i>Filters</h5>
        </div>
        <div class="card-body">
            <form method="POST" class="row g-3">
                <input type="hidden" name="apply_filter" value="1">
                <div class="col-md-3">
                    <label class="form-label">Scholarship Type</label>
                    <select name="scholarship_type" class="form-select select2">
                        <option value="">All Types</option>
                        <?php foreach ($scholarship_types as $type): ?>
                            <option value="<?php echo $type['id']; ?>" <?php echo $scholarship_type_id == $type['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($type['type_name'] ?? ''); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Standard</label>
                    <select name="course_id" class="form-select select2">
                        <option value="">All Standards</option>
                        <option value="11th" <?php echo $course_id == '11th' ? 'selected' : ''; ?>>11th</option>
                        <option value="12th" <?php echo $course_id == '12th' ? 'selected' : ''; ?>>12th</option>
                        <option value="Reneet" <?php echo $course_id == 'Reneet' ? 'selected' : ''; ?>>Reneet</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Search Student</label>
                    <input type="text" name="search" class="form-control" placeholder="Name, Mobile or Enrollment..." value="<?php echo htmlspecialchars($search ?? ''); ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i>Apply</button>
                    <a href="#" class="btn btn-secondary" onclick="resetFilters(event)"><i class="fas fa-sync-alt"></i></a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center bg-dark text-white">
            <h5 class="card-title mb-0">Student-wise Scholarship Details</h5>
            <div class="card-tools d-flex gap-2">
                <button type="button" class="btn btn-success btn-sm" onclick="exportToExcel()"><i class="fas fa-file-excel me-1"></i> Excel</button>
                <button type="button" class="btn btn-danger btn-sm" onclick="exportToPDF()"><i class="fas fa-file-pdf me-1"></i> Export PDF</button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="scholarshipTable">
                    <thead class="bg-light">
                        <tr><th>#</th><th>Student Name</th><th>Class</th><th>Scholarship Type</th><th class="text-center">Benefit (%)</th><th class="text-end">Amount</th><th>Enrollment No.</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($students)): ?>
                            <tr><td colspan="7" class="text-center py-5"><p class="text-muted">No records found</p></td></tr>
                        <?php else: ?>
                            <?php $sno = $offset + 1; foreach ($students as $s): ?>
                                <tr>
                                    <td><?php echo $sno++; ?></td>
                                    <td><strong><?php echo htmlspecialchars(trim(($s['surname'] ?? '') . ' ' . ($s['student_name'] ?? '') . ' ' . ($s['fathers_name'] ?? ''))); ?></strong><br><small class="text-muted"><?php echo htmlspecialchars($s['mob'] ?? '-'); ?></small></td>
                                    <td><?php echo htmlspecialchars($s['current_class'] ?? 'N/A'); ?></td>
                                    <td><span class="badge bg-info"><?php echo htmlspecialchars($s['scholarship_type'] ?? '-'); ?></span></td>
                                    <td class="text-center"><?php echo ($s['scholarship_percentage'] > 0) ? ($s['scholarship_percentage'] . '%') : '-'; ?></td>
                                    <td class="text-end fw-bold text-success">₹<?php echo formatIndianCurrency($s['scholarship_amount'] ?? 0); ?></td>
                                    <td><?php echo $s['enrollment_no'] ? '<span class="badge bg-secondary">'.$s['enrollment_no'].'</span>' : '<span class="badge bg-light text-dark">Not Enrolled</span>'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($totalRecords > 0): ?>
                <div class="p-3 border-top"><div class="d-flex justify-content-between align-items-center"><div class="text-muted"><?php echo getPaginationInfo($page, $perPage, $totalRecords); ?></div><?php if ($totalPages > 1) echo renderPaginationPost($page, $totalPages, 2, $perPage); ?></div></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Hidden Table for Export -->
<table class="d-none" id="scholarshipExportTable">
    <thead><tr><th>#</th><th>Surname</th><th>Student Name</th><th>Fathers Name</th><th>Mobile</th><th>Class</th><th>Scholarship Type</th><th>Benefit (%)</th><th>Amount</th><th>Enrollment No.</th></tr></thead>
    <tbody><?php $ex_sno = 1; foreach ($all_students as $as): ?><tr><td><?php echo $ex_sno++; ?></td><td><?php echo htmlspecialchars($as['surname'] ?? ''); ?></td><td><?php echo htmlspecialchars($as['student_name'] ?? ''); ?></td><td><?php echo htmlspecialchars($as['fathers_name'] ?? ''); ?></td><td><?php echo htmlspecialchars($as['mob'] ?? ''); ?></td><td><?php echo htmlspecialchars($as['current_class'] ?? '-'); ?></td><td><?php echo htmlspecialchars($as['scholarship_type'] ?? '-'); ?></td><td><?php echo ($as['scholarship_percentage'] > 0) ? ($as['scholarship_percentage'] . '%') : '-'; ?></td><td><?php echo round($as['scholarship_amount'] ?? 0); ?></td><td><?php echo htmlspecialchars($as['enrollment_no'] ?? '-'); ?></td></tr><?php endforeach; ?></tbody>
</table>

<?php include '../../../include/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
    function exportToExcel() {
        const table = document.getElementById('scholarshipExportTable');
        const wb = XLSX.utils.table_to_book(table, { sheet: "Scholarship Report" });
        XLSX.writeFile(wb, 'Scholarship_Applied_Report_<?php echo date('Y-m-d'); ?>.xlsx');
    }
    function exportToPDF() {
        window.location.href = 'scholarships-pdf.php';
    }
    function resetFilters(e) { /* existing logic */ }
</script>
