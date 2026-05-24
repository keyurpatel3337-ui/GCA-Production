<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/format_helper.php';
// Check if user is Principal or Admin
if (!hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "Group Change Request History";
$page_breadcrumb = "Request History";

// Handle POST filters and store in session
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['clear_filters'])) {
        unset($_SESSION['group_change_history_filters']);
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    $_SESSION['group_change_history_filters'] = [
        'status' => $_POST['status'] ?? 'all',
        'search' => $_POST['search'] ?? '',
        'date_from' => $_POST['date_from'] ?? '',
        'date_to' => $_POST['date_to'] ?? ''
    ];

    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Get filter parameters from session
$savedFilters = $_SESSION['group_change_history_filters'] ?? [];
$status_filter = $savedFilters['status'] ?? 'all';
$search = $savedFilters['search'] ?? '';
$date_from = $savedFilters['date_from'] ?? '';
$date_to = $savedFilters['date_to'] ?? '';

try {
    // Build query with filters
    $sql = "SELECT gcr.*, 
            s.surname, s.student_name, s.fathers_name, s.aadhaar_number,
            cg.group_name as current_group_name,
            rg.group_name as requested_group_name,
            u.name as reviewed_by_name
            FROM tbl_group_change_requests gcr
            LEFT JOIN tbl_gm_std_registration s ON gcr.student_id = s.id
            LEFT JOIN tbl_group cg ON gcr.current_group_id = cg.id
            LEFT JOIN tbl_group rg ON gcr.requested_group_id = rg.id
            LEFT JOIN tbl_users u ON gcr.reviewed_by = u.id
            WHERE 1=1";

    $params = [];

    if ($status_filter !== 'all') {
        $sql .= " AND gcr.status = ?";
        $params[] = $status_filter;
    }

    if (!empty($search)) {
        $sql .= " AND (s.student_name LIKE ? OR s.surname LIKE ? OR s.fathers_name LIKE ? OR s.aadhaar_number LIKE ? OR gcr.id LIKE ?)";
        $search_param = "%{$search}%";
        $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param]);
    }

    if (!empty($date_from)) {
        $sql .= " AND gcr.request_date >= ?";
        $params[] = $date_from . ' 00:00:00';
    }

    if (!empty($date_to)) {
        $sql .= " AND gcr.request_date <= ?";
        $params[] = $date_to . ' 23:59:59';
    }

    $sql .= " ORDER BY gcr.request_date ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logError("Request History Error: " . $e->getMessage(), __FILE__, __LINE__, $e);
    set_flash_message('error', "An error occurred while loading request history.");
    $requests = [];
}

?>
    <?php include '../../include/sidebar.php'; ?>
    <?php include '../../include/header.php'; ?>

    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-history"></i> Group Change Request History</h2>
            <div>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <button onclick="window.print()" class="btn btn-info">
                    <i class="fas fa-print"></i> Print
                </button>
                <button onclick="exportToExcel()" class="btn btn-success">
                    <i class="fas fa-file-excel"></i> Export to Excel
                </button>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo gca_safe_html($_SESSION['success']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo gca_safe_html($_SESSION['error']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="fas fa-filter"></i> Filters</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status
                                </option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>
                                    Pending</option>
                                <option value="under_review" <?php echo $status_filter === 'under_review' ? 'selected' : ''; ?>>Under Review</option>
                                <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>
                                    Approved</option>
                                <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>
                                    Rejected</option>
                                <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>
                                    Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" class="form-control"
                                placeholder="Student name, Aadhaar, Request No"
                                value="<?php echo htmlspecialchars($search ?? ''); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">From Date</label>
                            <input type="date" name="date_from" class="form-control"
                                value="<?php echo htmlspecialchars($date_from ?? ''); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">To Date</label>
                            <input type="date" name="date_to" class="form-control"
                                value="<?php echo htmlspecialchars($date_to ?? ''); ?>">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> Filter
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Results -->
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-list"></i> Request History
                    <span class="badge bg-primary"><?php echo count($requests); ?> Records</span>
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($requests)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> No requests found matching your criteria.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table id="historyTable" class="table table-hover table-striped">
                            <thead>
                                <tr>
                                    <th>Request No</th>
                                    <th>Request Date</th>
                                    <th>Student</th>
                                    <th>Aadhaar</th>
                                    <th>Current Group</th>
                                    <th>Requested Group</th>
                                    <th>Fee Impact</th>
                                    <th>Status</th>
                                    <th>Reviewed By</th>
                                    <th>Review Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($requests as $req): ?>
                                    <?php
                                    $badge_class = match ($req['status']) {
                                        'pending' => 'warning',
                                        'under_review' => 'info',
                                        'approved' => 'success',
                                        'rejected' => 'danger',
                                        'cancelled' => 'secondary',
                                        default => 'secondary'
                                    };

                                    $fee_diff = $req['fee_difference'] ?? 0;
                                    if ($fee_diff > 0) {
                                        $fee_class = 'fee-increase';
                                        $fee_icon = 'fa-arrow-up';
                                        $fee_text = '+?' . formatIndianCurrency($fee_diff, false);
                                    } elseif ($fee_diff < 0) {
                                        $fee_class = 'fee-decrease';
                                        $fee_icon = 'fa-arrow-down';
                                        $fee_text = '-?' . formatIndianCurrency(abs($fee_diff), false);
                                    } else {
                                        $fee_class = 'fee-neutral';
                                        $fee_icon = 'fa-minus';
                                        $fee_text = 'No Change';
                                    }
                                    ?>
                                    <tr>
                                        <td><strong>REQ-<?php echo $req['id']; ?></strong></td>
                                        <td><?php echo date('d M Y', strtotime($req['request_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($req['surname'] . ' ' . $req['student_name'] . ' ' . $req['fathers_name'] ?? ''); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars(substr($req['aadhaar_number'], 0, 4) . ' XXXX XXXX' ?? ''); ?>
                                        </td>
                                        <td><span
                                                class="badge bg-secondary"><?php echo htmlspecialchars($req['current_group_name'] ?? ''); ?></span>
                                        </td>
                                        <td><span
                                                class="badge bg-primary"><?php echo htmlspecialchars($req['requested_group_name'] ?? ''); ?></span>
                                        </td>
                                        <td class="<?php echo $fee_class; ?>">
                                            <i class="fas <?php echo $fee_icon; ?>"></i> <?php echo $fee_text; ?>
                                        </td>
                                        <td><span
                                                class="badge bg-<?php echo $badge_class; ?>"><?php echo ucfirst(str_replace('_', ' ', $req['status'])); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($req['reviewed_by_name'] ?? '-'); ?></td>
                                        <td><?php echo $req['review_date'] ? date('d M Y', strtotime($req['review_date'])) : '-'; ?>
                                        </td>
                                        <td>
                                            <a href="review-group-change-request.php?id=<?php echo $req['id']; ?>"
                                                class="btn btn-sm btn-primary" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include '../../include/footer.php'; ?>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

    <script>
        function exportToExcel() {
            const table = document.getElementById('historyTable');
            const wb = XLSX.utils.table_to_book(table, {
                sheet: "History"
            });
            const filename = 'group_change_history_' + new Date().toISOString().slice(0, 10) + '.xlsx';
            XLSX.writeFile(wb, filename);
        }
    </script>
</body>

</html>

