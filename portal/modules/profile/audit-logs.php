<?php

/**
 * Audit Logs Viewer
 * Displays system activity logs with filtering
 */

require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once '../../common/settings_helper.php';
require_once PAGINATION_FILE;
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/format_helper.php';

// Only Super Admin can view audit logs
if (!hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "Audit Logs";
$page_breadcrumb = "Settings / Audit Logs";

// Handle POST filters and store in session
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['clear_filters'])) {
        unset($_SESSION['audit_logs_filters']);
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    $filters = [
        'category' => $_POST['action_category'] ?? ($_SESSION['audit_logs_filters']['category'] ?? ''),
        'action_type' => $_POST['action_type'] ?? ($_SESSION['audit_logs_filters']['action_type'] ?? ''),
        'user' => $_POST['user'] ?? ($_SESSION['audit_logs_filters']['user'] ?? ''),
        'date_from' => $_POST['date_from'] ?? ($_SESSION['audit_logs_filters']['date_from'] ?? ''),
        'date_to' => $_POST['date_to'] ?? ($_SESSION['audit_logs_filters']['date_to'] ?? ''),
        'page' => $_POST['page'] ?? 1 // Update page from POST
    ];

    // If filtering (not just paging), reset to page 1
    // We can detecting filtering vs paging by checking button name or implicit logic
    // But since we use one form for filter and separate forms for pagination, we can assume:
    // If 'page' is transmitted and it's from pagination logic, we keep it.
    // If it's a new filter submission, we might want to reset.
    // Ideally, the filter form shouldn't send 'page' or should send '1'.

    $_SESSION['audit_logs_filters'] = $filters;

    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Get filters from session
$savedFilters = $_SESSION['audit_logs_filters'] ?? [];
$filterCategory = $savedFilters['category'] ?? ''; // used to be module
$filterActionType = $savedFilters['action_type'] ?? ''; // used to be action
$filterUser = $savedFilters['user'] ?? '';
$filterDateFrom = $savedFilters['date_from'] ?? '';
$filterDateTo = $savedFilters['date_to'] ?? '';
$page = max(1, (int) ($savedFilters['page'] ?? 1));
$perPage = 10;

// Build query
$where = [];
$params = [];

if ($filterCategory) {
    $where[] = "action_category = ?";
    $params[] = $filterCategory;
}
if ($filterActionType) {
    $where[] = "action_type LIKE ?";
    $params[] = "%$filterActionType%";
}
if ($filterUser) {
    $where[] = "(user_name LIKE ? OR performed_by = ?)";
    $params[] = "%$filterUser%";
    $params[] = (int) $filterUser;
}
if ($filterDateFrom) {
    $where[] = "DATE(created_at) >= ?";
    $params[] = $filterDateFrom;
}
if ($filterDateTo) {
    $where[] = "DATE(created_at) <= ?";
    $params[] = $filterDateTo;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Get total count
$countQuery = "SELECT COUNT(*) FROM tbl_audit_logs $whereClause";
$countStmt = $conn->prepare($countQuery);
$countStmt->execute($params);
$totalRecords = $countStmt->fetchColumn();
$totalPages = ceil($totalRecords / $perPage);

// Get logs
$offset = ($page - 1) * $perPage;
$query = "SELECT * FROM tbl_audit_logs $whereClause ORDER BY id DESC LIMIT $perPage OFFSET $offset";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique categories for filter
$categories = $conn->query("SELECT DISTINCT action_category FROM tbl_audit_logs WHERE action_category IS NOT NULL ORDER BY action_category")->fetchAll(PDO::FETCH_COLUMN);
?>
<?php include '../../include/header.php'; ?>
<?php include '../../include/navbar.php'; ?>
<?php include '../../include/sidebar.php'; ?>


<div class="container-fluid">


    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="POST" class="row g-3">
                <input type="hidden" name="page" value="1"> <!-- Reset page on new filter -->
                <div class="col-md-2">
                    <label class="form-label">Category</label>
                    <select name="action_category" class="form-select">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat ?? ''); ?>" <?php echo $filterCategory === $cat ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $cat)) ?? ''); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Action Type</label>
                    <input type="text" name="action_type" class="form-control" placeholder="Search action type..."
                        value="<?php echo htmlspecialchars($filterActionType ?? ''); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">User</label>
                    <input type="text" name="user" class="form-control" placeholder="Name or ID..."
                        value="<?php echo htmlspecialchars($filterUser ?? ''); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Date From</label>
                    <input type="date" name="date_from" class="form-control"
                        value="<?php echo htmlspecialchars($filterDateFrom ?? ''); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Date To</label>
                    <input type="date" name="date_to" class="form-control"
                        value="<?php echo htmlspecialchars($filterDateTo ?? ''); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Filter
                        </button>
                        <button type="submit" name="clear_filters" value="1" class="btn btn-outline-secondary"
                            formnovalidate>
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Logs Table -->
    <div class="card">
        <div class="card-header">
            <strong>Showing <?php echo formatIndianCurrency($totalRecords, false); ?> records</strong>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 no-auto-paginate">
                    <thead>
                        <tr>
                            <th class="css-audit-logs-ed5b9a">Date/Time</th>
                            <th class="css-audit-logs-ed5b9a">User</th>
                            <th class="css-audit-logs-ed5b9a">Action Type</th>
                            <th class="css-audit-logs-db8a21">Category</th>
                            <th>Description</th>
                            <th class="css-audit-logs-db8a21">IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="fas fa-info-circle fa-2x mb-2"></i>
                                    <p>No audit logs found</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td>
                                        <small>
                                            <?php echo date('d M Y', strtotime($log['created_at'])); ?><br>
                                            <span
                                                class="text-muted"><?php echo date('H:i:s', strtotime($log['created_at'])); ?></span>
                                        </small>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($log['user_name'] ?? 'System'); ?>
                                        <?php if ($log['performed_by']): ?>
                                            <br><small class="text-muted">ID: <?php echo $log['performed_by']; ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge bg-info"><?php echo htmlspecialchars(str_replace('_', ' ', $log['action_type']) ?? ''); ?></span></td>
                                    <td><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $log['action_category'] ?? '-'))); ?></td>
                                    <td><small><?php echo htmlspecialchars($log['description'] ?? '-'); ?></small></td>
                                    <td><code><?php echo htmlspecialchars($log['ip_address'] ?? '-'); ?></code></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="card-footer">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <?php echo renderPaginationPost($page, $totalPages); ?>
                    <div class="text-muted">
                        <small>
                            Showing <strong><?php echo (($page - 1) * $perPage) + 1; ?></strong> to
                            <strong><?php echo min($page * $perPage, $totalRecords); ?></strong> of
                            <strong><?php echo formatIndianCurrency($totalRecords, false); ?></strong> entries
                        </small>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
</div>

<?php include '../../include/footer.php'; ?>
