<?php
/**
 * Discount Given Report
 */

require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/common/constants.php';
require_once __DIR__ . '/../../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once __DIR__ . '/../../../../common/helpers/format_helper.php';
require_once PAGINATION_FILE;

if (!hasRole(ROLE_ACCOUNTANT) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "Discount Report";

$dbOps = new DatabaseOperations();

// Fetch unique discount types
$discountTypes = $dbOps->customSelect("SELECT DISTINCT discount_type FROM (
    SELECT discount_type FROM tbl_post_admission_discounts
    UNION
    SELECT COALESCE(additional_scholarship_type, 'Additional Scholarship') as discount_type FROM tbl_gm_std_registration WHERE additional_scholarship_amount > 0
    UNION
    SELECT 'Payment Discount' as discount_type FROM tbl_enrolled_students WHERE post_admission_discount_amount > 0 AND post_admission_discount_remarks LIKE '%| Discount:%'
) AS t WHERE discount_type IS NOT NULL AND discount_type != '' ORDER BY discount_type");

// Filter Logic
$filterConditions = [];
$params = [];

if (!empty($_GET['from_date']) && !empty($_GET['to_date'])) {
    $filterConditions[] = "DATE(discount_date) BETWEEN ? AND ?";
    $params[] = $_GET['from_date'];
    $params[] = $_GET['to_date'];
}

if (!empty($_GET['discount_type'])) {
    $filterConditions[] = "discount_type = ?";
    $params[] = $_GET['discount_type'];
}

if (!empty($_GET['search'])) {
    $search = $_GET['search'];
    $fuzzySearch = "%" . str_replace(' ', '%', $search) . "%";
    $filterConditions[] = "(student_name LIKE ? OR remarks LIKE ? OR discount_type LIKE ? OR current_class LIKE ? OR mob LIKE ? OR student_id LIKE ?)";
    $params = array_merge($params, [$fuzzySearch, $fuzzySearch, $fuzzySearch, $fuzzySearch, $fuzzySearch, $fuzzySearch]);
}

$whereClause = !empty($filterConditions) ? "WHERE " . implode(" AND ", $filterConditions) : "";

$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$baseQuery = "SELECT * FROM (
        SELECT d.created_at as discount_date, d.discount_amount as amount, d.discount_type, 'approved' as status, d.remarks,
               CONCAT(r.surname, ' ', r.student_name,' ',r.fathers_name) as student_name, 
               CONCAT(c.course_name, IF(m.medium_name IS NOT NULL AND m.medium_name != '', CONCAT(' - ', m.medium_name), '')) as current_class,
               r.mob, r.id as student_id
        FROM tbl_post_admission_discounts d
        JOIN tbl_gm_std_registration r ON d.student_id = r.id
        LEFT JOIN tbl_enrolled_students es ON es.registration_id = r.id AND es.is_active = 1
        LEFT JOIN tbl_courses c ON r.course_id = c.id
        LEFT JOIN tbl_medium m ON r.medium_id = m.id
        UNION ALL
        SELECT COALESCE(r.admission_confirmed_date, r.created_at) as discount_date, r.additional_scholarship_amount as amount,
               COALESCE(r.additional_scholarship_type, 'Additional Scholarship') as discount_type, 'approved' as status, r.additional_scholarship_remarks as remarks,
               CONCAT(r.surname, ' ', r.student_name,' ',r.fathers_name) as student_name, 
               CONCAT(c.course_name, IF(m.medium_name IS NOT NULL AND m.medium_name != '', CONCAT(' - ', m.medium_name), '')) as current_class,
               r.mob, r.id as student_id
        FROM tbl_gm_std_registration r
        LEFT JOIN tbl_enrolled_students es ON es.registration_id = r.id AND es.is_active = 1
        LEFT JOIN tbl_courses c ON (r.course_id = c.id)
        LEFT JOIN tbl_medium m ON r.medium_id = m.id
        WHERE r.additional_scholarship_amount > 0
        UNION ALL
        SELECT es.updated_at as discount_date, es.post_admission_discount_amount as amount,
               'Payment Discount' as discount_type, 'approved' as status, es.post_admission_discount_remarks as remarks,
               CONCAT(r.surname, ' ', r.student_name,' ',r.fathers_name) as student_name, 
               CONCAT(c.course_name, IF(m.medium_name IS NOT NULL AND m.medium_name != '', CONCAT(' - ', m.medium_name), '')) as current_class,
               r.mob, r.id as student_id
        FROM tbl_enrolled_students es
        JOIN tbl_gm_std_registration r ON es.registration_id = r.id
        LEFT JOIN tbl_courses c ON r.course_id = c.id
        LEFT JOIN tbl_medium m ON r.medium_id = m.id
        WHERE es.is_active = 1 AND es.post_admission_discount_amount > 0 AND es.post_admission_discount_remarks LIKE '%| Discount:%'
    ) AS report";

$countResult = $dbOps->customSelect("SELECT COUNT(*) as total FROM ($baseQuery) as count_tbl $whereClause", $params);
$totalRecords = $countResult[0]['total'] ?? 0;
$totalPages = ceil($totalRecords / $limit);

$sumResult = $dbOps->customSelect("SELECT SUM(amount) as total_amount FROM ($baseQuery) as sum_tbl $whereClause", $params);
$totalDiscount = $sumResult[0]['total_amount'] ?? 0;

$discounts = $dbOps->customSelect("$baseQuery $whereClause ORDER BY discount_date ASC LIMIT $limit OFFSET $offset", $params);

include '../../../include/header.php';
include '../../../include/navbar.php';
include '../../../include/sidebar.php';
?>

<div class="container-fluid">
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Name, Remarks..."
                        value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Discount Type</label>
                    <select name="discount_type" class="form-select">
                        <option value="">All Types</option>
                        <?php foreach ($discountTypes as $type): ?>
                            <option value="<?php echo htmlspecialchars($type['discount_type'] ?? ''); ?>" <?php echo (isset($_GET['discount_type']) && $_GET['discount_type'] == $type['discount_type']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type['discount_type'] ?? ''); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">From Date</label>
                    <input type="date" name="from_date" class="form-control"
                        value="<?php echo htmlspecialchars($_GET['from_date'] ?? ''); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">To Date</label>
                    <input type="date" name="to_date" class="form-control"
                        value="<?php echo htmlspecialchars($_GET['to_date'] ?? ''); ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end gap-1">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Apply</button>
                    <a href="discounts.php" class="btn btn-secondary btn-sm"><i class="fas fa-redo"></i> Reset</a>
                    <button type="button" class="btn btn-danger btn-sm" onclick="exportToPDF()"><i
                            class="fas fa-file-pdf"></i> PDF</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-4 bg-success text-white">
        <div class="card-body text-center">
            <h2>₹<?php echo formatIndianCurrency($totalDiscount); ?></h2>
            <p class="mb-0">Total Discounts Given (<?php echo $totalRecords; ?> records)</p>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Student</th>
                            <th>Class</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($discounts)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5">No records found</td>
                            </tr>
                        <?php else: ?>
                            <?php $sno = $offset + 1;
                            foreach ($discounts as $d): ?>
                                <tr>
                                    <td><?php echo $sno++; ?></td>
                                    <td><?php echo date('d-m-Y', strtotime($d['discount_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($d['student_name'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($d['current_class'] ?: '-' ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($d['discount_type'] ?? ''); ?></td>
                                    <td class="text-success fw-bold">₹<?php echo formatIndianCurrency($d['amount']); ?></td>
                                    <td><span class="badge bg-success"><?php echo strtoupper($d['status']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer clearfix">
            <?php 
            $baseUrl = $_SERVER['PHP_SELF'] . '?' . http_build_query(array_diff_key($_GET, ['page' => '']));
            echo renderPagination($page, $totalPages, $baseUrl, 2, $totalRecords, 'records'); 
            ?>
        </div>
    </div>
</div>

<script>
    function exportToPDF() {
        const params = new URLSearchParams(window.location.search);
        window.location.href = 'discounts-pdf.php?' + params.toString();
    }
</script>

<?php include '../../../include/footer.php'; ?>