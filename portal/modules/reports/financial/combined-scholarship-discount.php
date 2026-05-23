<?php
/**
 * Combined Scholarship & Discount Report
 * Unified view of all waivers applied to students
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

$page_title = "Combined Scholarship & Discount Report";

$dbOps = new DatabaseOperations();

// Filter Logic
$filterConditions = ["(sfa.scholarship_amount > 0 OR sfa.additional_scholarship > 0 OR sfa.post_admission_discount > 0)"];
$params = [];

if (!empty($_GET['course_id'])) {
    $course_id = $_GET['course_id'];
    if ($course_id === '11th') {
        $filterConditions[] = "r.course_id = 1";
    } elseif ($course_id === '12th') {
        $filterConditions[] = "r.course_id = 2";
    } elseif ($course_id === 'Reneet') {
        $filterConditions[] = "r.course_id = 3";
    } else {
        $filterConditions[] = "r.course_id = ?";
        $params[] = $course_id;
    }
}

if (!empty($_GET['search'])) {
    $search = $_GET['search'];
    $searchTerm = "%" . $search . "%";
    $filterConditions[] = "(CONCAT(r.surname, ' ', r.student_name, ' ', IFNULL(r.fathers_name, '')) LIKE ? OR r.mob LIKE ? OR r.id LIKE ?)";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

$whereClause = "WHERE " . implode(" AND ", $filterConditions);

// Pagination
$limit = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Base Query
$baseQuery = "FROM tbl_student_fee_allocation sfa
              JOIN tbl_gm_std_registration r ON sfa.student_id = r.id
              LEFT JOIN tbl_courses c ON r.course_id = c.id
              $whereClause";

// Count
$countResult = $dbOps->customSelect("SELECT COUNT(*) as total $baseQuery", $params);
$totalRecords = $countResult[0]['total'] ?? 0;
$totalPages = ceil($totalRecords / $limit);

// Data Query
$dataQuery = "SELECT 
                r.id, r.surname, r.student_name, r.fathers_name, r.mob, 
                c.course_name as current_class,
                sfa.scholarship_amount,
                sfa.additional_scholarship,
                sfa.post_admission_discount,
                (sfa.scholarship_amount + sfa.additional_scholarship + sfa.post_admission_discount) as total_waiver,
                sfa.allocated_amount,
                sfa.paid_amount,
                sfa.pending_amount
              $baseQuery
              ORDER BY total_waiver DESC
              LIMIT $limit OFFSET $offset";

$students = $dbOps->customSelect($dataQuery, $params);

// Totals for Summary Cards
$summarySql = "SELECT 
                SUM(sfa.scholarship_amount) as total_scholarship,
                SUM(sfa.additional_scholarship) as total_additional,
                SUM(sfa.post_admission_discount) as total_discount,
                SUM(sfa.scholarship_amount + sfa.additional_scholarship + sfa.post_admission_discount) as grand_total_waiver
               $baseQuery";
$summaryResult = $dbOps->customSelect($summarySql, $params);
$summary = $summaryResult[0] ?? ['total_scholarship' => 0, 'total_additional' => 0, 'total_discount' => 0, 'grand_total_waiver' => 0];

include '../../../include/header.php';
include '../../../include/navbar.php';
include '../../../include/sidebar.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="h3 mb-0 text-gray-800"><?php echo $page_title; ?></h2>
        <div>
            <button class="btn btn-success btn-sm me-2" onclick="exportToExcel()">
                <i class="fas fa-file-excel me-1"></i> Excel
            </button>
            <button class="btn btn-danger btn-sm" onclick="window.print()">
                <i class="fas fa-print me-1"></i> Print
            </button>
        </div>
    </div>

    <!-- Summary Statistics -->
    <div class="row g-4 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Scholarship</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">₹<?php echo formatIndianCurrency($summary['total_scholarship']); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-medal fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Additional Scholarship</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">₹<?php echo formatIndianCurrency($summary['total_additional']); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-plus-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Post-Adm Discounts</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">₹<?php echo formatIndianCurrency($summary['total_discount']); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-percent fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Grand Total Waiver</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">₹<?php echo formatIndianCurrency($summary['grand_total_waiver']); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-hand-holding-usd fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Search Student</label>
                    <input type="text" name="search" class="form-control" placeholder="Name or Mobile..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Standard</label>
                    <select name="course_id" class="form-select">
                        <option value="">All Standards</option>
                        <option value="11th" <?php echo ($_GET['course_id'] ?? '') == '11th' ? 'selected' : ''; ?>>11th</option>
                        <option value="12th" <?php echo ($_GET['course_id'] ?? '') == '12th' ? 'selected' : ''; ?>>12th</option>
                        <option value="Reneet" <?php echo ($_GET['course_id'] ?? '') == 'Reneet' ? 'selected' : ''; ?>>Reneet</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-filter me-1"></i> Apply</button>
                    <a href="combined-scholarship-discount.php" class="btn btn-secondary"><i class="fas fa-undo"></i></a>
                </div>
            </form>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 bg-white d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Student Waiver Details</h6>
            <span class="badge bg-primary"><?php echo $totalRecords; ?> Students</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="waiverTable">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-3">Student Details</th>
                            <th>Class</th>
                            <th class="text-end">Scholarship</th>
                            <th class="text-end">Add. Schol.</th>
                            <th class="text-end">Discount</th>
                            <th class="text-end bg-soft-success">Total Waiver</th>
                            <th class="text-end">Net Pending</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($students)): ?>
                            <tr><td colspan="7" class="text-center py-5">No records found</td></tr>
                        <?php else: ?>
                            <?php foreach ($students as $s): ?>
                                <tr>
                                    <td class="ps-3">
                                        <div class="fw-bold"><?php echo htmlspecialchars(trim(($s['surname'] ?? '') . ' ' . ($s['student_name'] ?? '') . ' ' . ($s['fathers_name'] ?? ''))); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($s['mob'] ?? '-'); ?> (ID: <?php echo $s['id']; ?>)</small>
                                    </td>
                                    <td><?php echo htmlspecialchars($s['current_class'] ?? 'N/A'); ?></td>
                                    <td class="text-end">₹<?php echo formatIndianCurrency($s['scholarship_amount']); ?></td>
                                    <td class="text-end">₹<?php echo formatIndianCurrency($s['additional_scholarship']); ?></td>
                                    <td class="text-end">₹<?php echo formatIndianCurrency($s['post_admission_discount']); ?></td>
                                    <td class="text-end fw-bold text-success bg-soft-success">₹<?php echo formatIndianCurrency($s['total_waiver']); ?></td>
                                    <td class="text-end">
                                        <span class="badge <?php echo $s['pending_amount'] > 0 ? 'bg-danger' : 'bg-success'; ?>">
                                            ₹<?php echo formatIndianCurrency($s['pending_amount']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white border-top-0">
            <div class="d-flex justify-content-between align-items-center">
                <div class="text-muted small">
                    Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $totalRecords); ?> of <?php echo $totalRecords; ?> entries
                </div>
                <?php if ($totalPages > 1): ?>
                    <nav>
                        <?php 
                        $baseUrl = $_SERVER['PHP_SELF'] . '?' . http_build_query(array_diff_key($_GET, ['page' => '']));
                        echo renderPagination($page, $totalPages, $baseUrl, 2, $totalRecords, 'records'); 
                        ?>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
    function exportToExcel() {
        const table = document.getElementById('waiverTable');
        const wb = XLSX.utils.table_to_book(table, { sheet: "Waiver Report" });
        XLSX.writeFile(wb, 'Combined_Scholarship_Discount_Report_<?php echo date('Y-m-d'); ?>.xlsx');
    }
</script>

<?php include '../../../include/footer.php'; ?>
