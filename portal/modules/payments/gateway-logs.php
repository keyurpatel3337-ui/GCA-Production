<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once dirname(dirname(__DIR__)) . '/common/globalvariable.php';
require_once __DIR__ . '/../../../common/helpers/format_helper.php';

if (!hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . PORTAL_URL . '/modules/dashboard/dashboard.php');
    exit;
}

$page_title = "Payment Gateway Logs";
$dbOps = new Operation();

// Filters
$log_type = $_GET['type'] ?? '';
$txn_id = $_GET['txn_id'] ?? '';
$date_from = $_GET['date_from'] ?? date('Y-m-d');
$date_to = $_GET['date_to'] ?? date('Y-m-d');

$where = "WHERE 1=1";
$params = [];

if ($log_type) {
    $where .= " AND log_type = ?";
    $params[] = $log_type;
}
if ($txn_id) {
    $where .= " AND txn_id LIKE ?";
    $params[] = "%$txn_id%";
}
if ($date_from) {
    $where .= " AND DATE(log_time) >= ?";
    $params[] = $date_from;
}
if ($date_to) {
    $where .= " AND DATE(log_time) <= ?";
    $params[] = $date_to;
}

$sql = "SELECT l.*, s.student_name, s.surname 
        FROM tbl_payment_gateway_logs l 
        LEFT JOIN tbl_gm_std_registration s ON l.user_id = s.id 
        $where 
        ORDER BY l.log_time DESC 
        LIMIT 100";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

include __DIR__ . '/../../include/header.php';
include __DIR__ . '/../../include/navbar.php';
include __DIR__ . '/../../include/sidebar.php';
?>

<div class="app-main"> <!-- Main content wrapper for standard GCA layout -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-dark fw-bold"><i class="fas fa-terminal me-2 text-primary"></i>Gateway Activity Logs</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="<?php echo PORTAL_URL; ?>/modules/dashboard/admin_dashboard.php">Home</a></li>
                        <li class="breadcrumb-item active">Gateway Logs</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <!-- Filter Card -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="card-title mb-0 fw-bold text-muted"><i class="fas fa-filter me-1"></i> Filter Logs</h6>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label small fw-bold">Log Type</label>
                            <select name="type" class="form-select form-select-sm border-light">
                                <option value="">All Types</option>
                                <option value="INFO" <?php echo $log_type == 'INFO' ? 'selected' : ''; ?>>INFO</option>
                                <option value="SUCCESS" <?php echo $log_type == 'SUCCESS' ? 'selected' : ''; ?>>SUCCESS</option>
                                <option value="FAILED" <?php echo $log_type == 'FAILED' ? 'selected' : ''; ?>>FAILED</option>
                                <option value="ERROR" <?php echo $log_type == 'ERROR' ? 'selected' : ''; ?>>ERROR</option>
                                <option value="WEBHOOK" <?php echo $log_type == 'WEBHOOK' ? 'selected' : ''; ?>>WEBHOOK</option>
                                <option value="INITIATE" <?php echo $log_type == 'INITIATE' ? 'selected' : ''; ?>>INITIATE</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Transaction ID</label>
                            <input type="text" name="txn_id" class="form-control form-control-sm border-light" value="<?php echo htmlspecialchars($txn_id); ?>" placeholder="Search Txn ID...">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold">From Date</label>
                            <input type="date" name="date_from" class="form-control form-control-sm border-light" value="<?php echo $date_from; ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold">To Date</label>
                            <input type="date" name="date_to" class="form-control form-control-sm border-light" value="<?php echo $date_to; ?>">
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary btn-sm me-2 flex-grow-1 shadow-sm"><i class="fas fa-search me-1"></i> Search</button>
                            <a href="gateway-logs.php" class="btn btn-light btn-sm flex-grow-1 border shadow-sm"><i class="fas fa-sync me-1"></i> Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Logs Table Card -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="card-title mb-0 fw-bold"><i class="fas fa-list-ul me-1 text-primary"></i> Latest Activity (100 Records)</h6>
                    <span class="badge bg-light text-dark border fw-normal"><?php echo count($logs); ?> entries found</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light text-muted small text-uppercase fw-bold">
                                <tr>
                                    <th class="ps-4 css-gateway-logs-c25159">Timestamp</th>
                                    <th class="css-gateway-logs-b15add">Type</th>
                                    <th>Transaction ID</th>
                                    <th>Amount</th>
                                    <th>Student Context</th>
                                    <th class="text-end pe-4">Action</th>
                                </tr>
                            </thead>
                            <tbody class="small">
                                <?php if (empty($logs)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-5">
                                            <div class="py-4">
                                                <i class="fas fa-history fa-3x text-light mb-3"></i>
                                                <p class="text-muted">No gateway activity found for the selected filters.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($logs as $log): 
                                        $badgeClass = 'bg-secondary';
                                        $icon = 'fa-info-circle';
                                        if ($log['log_type'] == 'SUCCESS') { $badgeClass = 'bg-success-subtle text-success border border-success-subtle'; $icon = 'fa-check-circle'; }
                                        if ($log['log_type'] == 'FAILED' || $log['log_type'] == 'ERROR') { $badgeClass = 'bg-danger-subtle text-danger border border-danger-subtle'; $icon = 'fa-times-circle'; }
                                        if ($log['log_type'] == 'WEBHOOK') { $badgeClass = 'bg-info-subtle text-info border border-info-subtle'; $icon = 'fa-plug'; }
                                        if ($log['log_type'] == 'INITIATE') { $badgeClass = 'bg-warning-subtle text-warning border border-warning-subtle'; $icon = 'fa-hourglass-start'; }
                                    ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="fw-bold"><?php echo date('d M, Y', strtotime($log['log_time'])); ?></div>
                                                <div class="text-muted smaller"><?php echo date('h:i:s A', strtotime($log['log_time'])); ?></div>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $badgeClass; ?> rounded-pill px-3">
                                                    <i class="fas <?php echo $icon; ?> me-1"></i> <?php echo $log['log_type']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <code class="text-primary fw-bold"><?php echo $log['txn_id'] ?: '-'; ?></code>
                                            </td>
                                            <td class="fw-bold text-dark">
                                                <?php echo $log['amount'] > 0 ? formatIndianCurrency($log['amount']) : '<span class="text-muted">-</span>'; ?>
                                            </td>
                                            <td>
                                                <?php if ($log['student_name']): ?>
                                                    <div class="fw-bold text-dark"><?php echo $log['surname'] . ' ' . $log['student_name']; ?></div>
                                                    <div class="text-muted smaller">Reg ID: <?php echo $log['user_id']; ?></div>
                                                <?php else: ?>
                                                    <span class="text-muted small italic">N/A (System/Guest)</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end pe-4">
                                                <button type="button" class="btn btn-sm btn-outline-primary rounded-circle view-log-details" 
                                                        data-details='<?php echo htmlspecialchars($log['raw_data'], ENT_QUOTES); ?>'
                                                        data-uri="<?php echo htmlspecialchars($log['uri']); ?>"
                                                        data-ip="<?php echo $log['ip_address']; ?>"
                                                        title="View Payload">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if ($log['log_type'] == 'SUCCESS' && (hasRole(ROLE_SUPER_ADMIN) || hasRole(ROLE_PRINCIPAL))): ?>
                                                    <a href="refund-management.php?txn_id=<?php echo $log['txn_id']; ?>" class="btn btn-sm btn-outline-danger rounded-circle ms-1" title="Refund">
                                                        <i class="fas fa-undo"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Raw Data Modal -->
<div class="modal fade" id="logDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white border-0 py-3">
                <h6 class="modal-title mb-0"><i class="fas fa-code me-2 text-warning"></i>Transaction Payload Details</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="bg-light p-3 border-bottom d-flex justify-content-between align-items-center">
                    <div class="small"><strong>URI:</strong> <code id="modal-uri" class="text-secondary"></code></div>
                    <div class="small"><strong>IP:</strong> <span id="modal-ip" class="badge bg-secondary"></span></div>
                </div>
                <div class="position-relative">
                    <button class="btn btn-xs btn-light position-absolute top-0 end-0 m-2 border" onclick="copyToClipboard()">
                        <i class="fas fa-copy"></i> Copy
                    </button>
                    <pre id="modal-json" class="p-4 mb-0 css-gateway-logs-84b9c0"></pre>
                </div>
            </div>
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-dark btn-sm px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const detailsModal = new bootstrap.Modal(document.getElementById('logDetailsModal'));
    const modalJson = document.getElementById('modal-json');
    const modalUri = document.getElementById('modal-uri');
    const modalIp = document.getElementById('modal-ip');

    document.querySelectorAll('.view-log-details').forEach(btn => {
        btn.addEventListener('click', function() {
            try {
                const rawData = this.getAttribute('data-details');
                const data = JSON.parse(rawData);
                modalJson.textContent = JSON.stringify(data, null, 4);
                modalUri.textContent = this.getAttribute('data-uri');
                modalIp.textContent = this.getAttribute('data-ip');
                detailsModal.show();
            } catch (e) {
                console.error("Failed to parse JSON", e);
                modalJson.textContent = this.getAttribute('data-details');
                modalUri.textContent = this.getAttribute('data-uri');
                modalIp.textContent = this.getAttribute('data-ip');
                detailsModal.show();
            }
        });
    });
});

function copyToClipboard() {
    const text = document.getElementById('modal-json').textContent;
    navigator.clipboard.writeText(text).then(() => {
        alert('Copied to clipboard!');
    });
}
</script>

<?php include __DIR__ . '/../../include/footer.php'; ?>
