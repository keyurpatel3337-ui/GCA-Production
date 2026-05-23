<?php
/**
 * Transport Fee Report
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

$page_title = "Transport Fee Report";
$dbOps = new DatabaseOperations();

// --- 1. Filter Logic ---
$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date = $_GET['to_date'] ?? date('Y-m-d');
$gender = $_GET['gender'] ?? '';
$search = $_GET['search'] ?? '';

$whereConditions = ["p.status = 'paid'", "(p.payment_type LIKE '%transport%' OR p.fee_component LIKE '%transport%' OR p.fee_component LIKE '%bus%')"];
$params = [];

if (!empty($from_date) && !empty($to_date)) {
    $whereConditions[] = "p.payment_date BETWEEN ? AND ?";
    $params[] = $from_date;
    $params[] = $to_date;
}

if (!empty($gender)) {
    $whereConditions[] = "r.gender = ?";
    $params[] = $gender;
}

if (!empty($search)) {
    $searchTerm = "%" . $search . "%";
    $whereConditions[] = "(CONCAT(r.surname, ' ', r.student_name, ' ', IFNULL(r.fathers_name, '')) LIKE ? OR r.mob LIKE ? OR r.id LIKE ? OR c.course_name LIKE ? OR p.receipt_no LIKE ? OR p.payment_mode LIKE ?)";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$whereClause = "WHERE " . implode(' AND ', $whereConditions);

$baseQuery = "SELECT p.*, r.surname, r.student_name, IFNULL(r.fathers_name, '') as fathers_name, r.gender, 
                     CONCAT(c.course_name, IF(m.medium_name IS NOT NULL AND m.medium_name != '', CONCAT(' - ', m.medium_name), '')) as current_class
              FROM tbl_payments p
              JOIN tbl_gm_std_registration r ON p.student_id = r.id
              LEFT JOIN tbl_enrolled_students es ON es.registration_id = r.id AND es.is_active = 1
              LEFT JOIN tbl_courses c ON r.course_id = c.id
              LEFT JOIN tbl_medium m ON r.medium_id = m.id
              $whereClause";

if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    $sql = "$baseQuery ORDER BY p.payment_date ASC";
    $results = $dbOps->customSelect($sql, $params);

    require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/portal/vendor/autoload.php';
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Transport Fees');

    $headers = ['Date', 'Receipt No', 'Student Name', 'Gender', 'Class', 'Payment Mode', 'Amount', 'Status'];
    $col = 'A';
    foreach ($headers as $h) {
        $sheet->setCellValue($col . '1', $h);
        $sheet->getStyle($col . '1')->getFont()->setBold(true);
        $col++;
    }

    if ($results) {
        $rowNum = 2;
        foreach ($results as $row) {
            $fullName = trim($row['surname'] . ' ' . $row['student_name'] . ' ' . $row['fathers_name']);
            $sheet->setCellValue('A' . $rowNum, date('d-m-Y', strtotime($row['payment_date'])));
            $sheet->setCellValue('B' . $rowNum, $row['receipt_no']);
            $sheet->setCellValue('C' . $rowNum, $fullName);
            $sheet->setCellValue('D' . $rowNum, $row['gender']);
            $sheet->setCellValue('E' . $rowNum, $row['current_class']);
            $sheet->setCellValue('F' . $rowNum, strtoupper($row['payment_mode']));
            $sheet->setCellValue('G' . $rowNum, round($row['amount']));
            $sheet->setCellValue('H' . $rowNum, strtoupper($row['status']));
            $rowNum++;
        }
    }

    foreach (range('A', 'H') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="transport_fees_' . date('Y-m-d') . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$countResult = $dbOps->customSelect("SELECT COUNT(*) as total FROM ($baseQuery) as count_tbl", $params);
$totalRecords = $countResult[0]['total'] ?? 0;
$totalPages = ceil($totalRecords / $limit);

$sumResult = $dbOps->customSelect("SELECT SUM(amount) as total_collected FROM ($baseQuery) as sum_tbl", $params);
$totalCollected = $sumResult[0]['total_collected'] ?? 0;

$payments = $dbOps->customSelect("$baseQuery ORDER BY p.payment_date ASC LIMIT $limit OFFSET $offset", $params);

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
                    <input type="text" name="search" class="form-control" placeholder="Name, Receipt, Class..."
                        value="<?php echo htmlspecialchars($search ?? ''); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Gender</label>
                    <select name="gender" class="form-select">
                        <option value="">All</option>
                        <option value="Male" <?php echo ($gender == 'Male') ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo ($gender == 'Female') ? 'selected' : ''; ?>>Female</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">From Date</label>
                    <input type="date" name="from_date" class="form-control" value="<?php echo $from_date; ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">To Date</label>
                    <input type="date" name="to_date" class="form-control" value="<?php echo $to_date; ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end gap-1">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Apply</button>
                    <a href="transport-fees.php" class="btn btn-secondary btn-sm"><i class="fas fa-redo"></i> Reset</a>
                    <button type="submit" name="export" value="excel" class="btn btn-outline-success btn-sm"><i
                            class="fas fa-file-excel"></i> Excel</button>
                    <button type="button" class="btn btn-danger btn-sm" onclick="exportToPDF()"><i
                            class="fas fa-file-pdf"></i> Export PDF</button>
                </div>
            </form>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-warning text-dark text-center p-3">
                <h3>₹<?php echo formatIndianCurrency($totalCollected); ?></h3>
                <p class="mb-0">Total Collected</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white text-center p-3">
                <h3>₹<?php echo formatIndianCurrency($totalCollected); ?></h3>
                <p class="mb-0">Total Paid (Cleared)</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-secondary text-white text-center p-3">
                <h3><?php echo $totalRecords; ?></h3>
                <p class="mb-0">Total Transactions</p>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Transaction Details</h3>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Receipt No</th>
                            <th>Student Name</th>
                            <th>Gender</th>
                            <th>Class</th>
                            <th>Amount</th>
                            <th>Mode</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($payments)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5">No transport fee payments found</td>
                            </tr>
                        <?php else: ?>
                            <?php $sno = $offset + 1;
                            foreach ($payments as $p): ?>
                                <tr>
                                    <td><?php echo $sno++; ?></td>
                                    <td><?php echo date('d-m-Y', strtotime($p['payment_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($p['receipt_no'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars(trim($p['surname'] . ' ' . $p['student_name'] . ' ' . $p['fathers_name']) ?? ''); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($p['gender'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($p['current_class'] ?? '-'); ?></td>
                                    <td class="text-success fw-bold">₹<?php echo formatIndianCurrency($p['amount']); ?>
                                    </td>
                                    <td><?php echo strtoupper($p['payment_mode']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer clearfix">
            <?php
            $baseUrl = "transport-fees.php?" . http_build_query(array_diff_key($_GET, ['page' => '']));
            echo renderPagination($page, $totalPages, $baseUrl, 2, $totalRecords, 'transport fee payments');
            ?>
        </div>
    </div>
</div>

<script>
    function exportToPDF() {
        const params = new URLSearchParams(window.location.search);
        window.location.href = 'transport-fees-pdf.php?' + params.toString();
    }
</script>

<?php include '../../../include/footer.php'; ?>