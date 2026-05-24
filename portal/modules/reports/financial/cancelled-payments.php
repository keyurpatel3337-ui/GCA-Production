<?php
/**
 * Cancelled Payments Report
 * Shows cancelled, failed, and refunded payments with filters
 */

require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/common/constants.php';
require_once __DIR__ . '/../../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once __DIR__ . '/../../../../common/helpers/format_helper.php';

// Check access
if (!hasRole(ROLE_ACCOUNTANT) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "Cancelled Payments";
$page_breadcrumb = "Cancelled Payments";

// Get filters
$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date = $_GET['to_date'] ?? date('Y-m-d');
$search_query = $_GET['search'] ?? '';
$course_filter = $_GET['course_id'] ?? '';

$dbOps = new DatabaseOperations();

// Build Query
$sql = "SELECT 
            p.*, 
            CONCAT(r.surname, ' ', r.student_name, ' ', IFNULL(r.fathers_name, '')) as student_full_name,
            r.standard,
            c.course_name,
            m.medium_name,
            u.name as issued_by_name
        FROM tbl_payments p
        LEFT JOIN tbl_gm_std_registration r ON p.student_id = r.id
        LEFT JOIN tbl_enrolled_students es ON es.registration_id = r.id AND es.is_active = 1
        LEFT JOIN tbl_courses c ON r.course_id = c.id
        LEFT JOIN tbl_medium m ON r.medium_id = m.id
        LEFT JOIN tbl_users u ON p.created_by = u.id
        WHERE p.status IN ('cancelled', 'failed', 'refunded')
        AND p.payment_date BETWEEN ? AND ?";

$params = [$from_date, $to_date];

if (!empty($search_query)) {
    $sql .= " AND (CONCAT(r.surname, ' ', r.student_name, ' ', IFNULL(r.fathers_name, '')) LIKE ? OR r.mob LIKE ? OR r.id LIKE ? OR p.receipt_no LIKE ? OR p.transaction_id LIKE ?)";
    $like_query = "%$search_query%";
    $params = array_merge($params, [$like_query, $like_query, $like_query, $like_query, $like_query]);
}

if (!empty($course_filter)) {
    if ($course_filter === '11th') {
        $sql .= " AND r.course_id = 1";
    } elseif ($course_filter === '12th') {
        $sql .= " AND r.course_id = 2";
    } elseif ($course_filter === 'Reneet') {
        $sql .= " AND r.course_id = 3";
    } else {
        $sql .= " AND r.course_id = ?";
        $params[] = $course_filter;
    }
}

$sql .= " ORDER BY p.created_at ASC";

$cancelled = $dbOps->customSelect($sql, $params);

include '../../../include/header.php';
include '../../../include/navbar.php';
include '../../../include/sidebar.php';
?>

<div class="container-fluid">
    <!-- Filters Card -->
    <div class="card mb-4">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-filter mr-1"></i> Filter Options</h3>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Name, Receipt No, Txn ID..."
                        value="<?php echo htmlspecialchars($search_query ?? ''); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">From Date</label>
                    <input type="date" name="from_date" class="form-control" value="<?php echo $from_date; ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">To Date</label>
                    <input type="date" name="to_date" class="form-control" value="<?php echo $to_date; ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Standard</label>
                    <select name="course_id" class="form-select">
                        <option value="">All Standards</option>
                        <option value="11th" <?php echo $course_filter === '11th' ? 'selected' : ''; ?>>11th</option>
                        <option value="12th" <?php echo $course_filter === '12th' ? 'selected' : ''; ?>>12th</option>
                        <option value="Reneet" <?php echo $course_filter === 'Reneet' ? 'selected' : ''; ?>>Reneet</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">
                        <i class="fas fa-search me-1"></i> Search
                    </button>
                    <a href="cancelled-payments.php" class="btn btn-secondary flex-grow-1">
                        <i class="fas fa-undo me-1"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Results Card -->
    <div class="card shadow-sm">
        <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0">Cancelled / Failed / Refunded Transactions</h3>
            <button class="btn btn-sm btn-light" onclick="exportToPDF()">
                <i class="fas fa-file-pdf me-1"></i> Export PDF
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">#</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Student Name</th>
                            <th>Receipt No</th>
                            <th>Standard</th>
                            <th>Amount</th>
                            <th>Txn ID</th>
                            <th>Mode</th>
                            <th>Reason / Remarks</th>
                            <th>Issued By</th>
                            <th>Status</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($cancelled)): ?>
                            <tr>
                                <td colspan="13" class="text-center py-5 text-muted">
                                    <i class="fas fa-info-circle fa-2x mb-3 d-block"></i>
                                    No records found for the selected criteria.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $sno = 1;
                            foreach ($cancelled as $p): ?>
                                <tr>
                                    <td class="ps-3 text-muted"><?php echo $sno++; ?></td>
                                    <td class="text-nowrap"><?php echo date('d-m-Y', strtotime($p['payment_date'])); ?></td>
                                    <td class="text-nowrap"><?php echo date('h:i A', strtotime($p['created_at'])); ?></td>
                                    <td class="fw-bold text-primary"><?php echo htmlspecialchars($p['student_full_name'] ?? ''); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($p['receipt_no'] ?? '-'); ?></td>
                                    <td>
                                        <?php 
                                        $display_std = $p['course_name'] ?? $p['standard'] ?? '-';
                                        if (!empty($p['medium_name'])) {
                                            $display_std .= ' - ' . $p['medium_name'];
                                        }
                                        echo htmlspecialchars($display_std);
                                        ?>
                                    </td>
                                    <td class="fw-bold">₹<?php echo formatIndianCurrency($p['amount']); ?></td>
                                    <td><small
                                            class="text-muted"><?php echo htmlspecialchars($p['transaction_id'] ?? '-'); ?></small>
                                    </td>
                                    <td><?php echo strtoupper($p['payment_mode']); ?></td>
                                    <td><span
                                            class="text-danger small fw-bold"><?php echo htmlspecialchars($p['remarks'] ?? 'N/A'); ?></span>
                                    </td>
                                    <td><small
                                            class="text-muted"><?php echo htmlspecialchars($p['issued_by_name'] ?? '-'); ?></small>
                                    </td>
                                    <td>
                                        <?php
                                        $status_class = 'bg-danger';
                                        if ($p['status'] == 'refunded')
                                            $status_class = 'bg-warning text-dark';
                                        if ($p['status'] == 'failed')
                                            $status_class = 'bg-secondary';
                                        ?>
                                        <span
                                            class="badge <?php echo $status_class; ?>"><?php echo strtoupper($p['status']); ?></span>
                                    </td>
                                    <td class="text-center">
                                        <a href="../../payments/receipt-print-pdf.php?id=<?php echo $p['id']; ?>"
                                            class="btn btn-sm btn-outline-info" title="View Details" target="_blank">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if (!empty($cancelled)): ?>
            <div class="card-footer py-2">
                <small class="text-muted">Showing <?php echo count($cancelled); ?> records</small>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../../include/footer.php'; ?>

<script>
    function exportToPDF() {
        const params = new URLSearchParams(window.location.search);
        window.location.href = 'cancelled-payments-pdf.php?' + params.toString();
    }
</script>