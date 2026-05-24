<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once dirname(dirname(__DIR__)) . '/common/globalvariable.php';
require_once __DIR__ . '/../../../common/helpers/format_helper.php';
require_once __DIR__ . '/../../common/pagination.php';

if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPAL)) {
    header('Location: ' . PORTAL_URL . '/modules/dashboard/dashboard.php');
    exit;
}

$page_title = "Refund Management";
$dbOps = new Operation();

// Filters
$txn_id = $_GET['txn_id'] ?? '';
$student_name_search = $_GET['student_name'] ?? '';
$standard_id = $_GET['standard_id'] ?? '';
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');

$where = "WHERE p.status = 'paid' AND p.payment_mode = 'online'";
$params = [];

if ($txn_id) {
    $where .= " AND p.transaction_id LIKE ?";
    $params[] = "%$txn_id%";
}
if ($student_name_search) {
    $where .= " AND (s.student_name LIKE ? OR s.surname LIKE ? OR s.fathers_name LIKE ?)";
    $params[] = "%$student_name_search%";
    $params[] = "%$student_name_search%";
    $params[] = "%$student_name_search%";
}
if ($standard_id) {
    $where .= " AND s.standard = ?";
    $params[] = $standard_id;
}
if ($date_from) {
    $where .= " AND DATE(p.payment_date) >= ?";
    $params[] = $date_from;
}
if ($date_to) {
    $where .= " AND DATE(p.payment_date) <= ?";
    $params[] = $date_to;
}

// Pagination Logic
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Count total records for pagination
$count_sql = "SELECT COUNT(*) FROM tbl_payments p $where";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Fetch Standards for dropdown
$standards = $conn->query("SELECT * FROM standard ORDER BY stdid")->fetchAll();

$sql = "SELECT p.*, s.student_name, s.surname, s.fathers_name, s.email as student_email, s.mob as student_phone 
        FROM tbl_payments p 
        LEFT JOIN tbl_gm_std_registration s ON p.student_id = s.id 
        $where 
        ORDER BY p.payment_date DESC 
        LIMIT $limit OFFSET $offset";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll();

include __DIR__ . '/../../include/header.php';
include __DIR__ . '/../../include/navbar.php';
include __DIR__ . '/../../include/sidebar.php';
?>

<div class="app-main">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-dark fw-bold"><i class="fas fa-undo-alt me-2 text-danger"></i>Refund Management</h1>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <!-- Search Card -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Transaction ID</label>
                            <input type="text" name="txn_id" class="form-control form-control-sm" value="<?php echo htmlspecialchars($txn_id); ?>" placeholder="Search ID...">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Student Name</label>
                            <input type="text" name="student_name" class="form-control form-control-sm" value="<?php echo htmlspecialchars($student_name_search); ?>" placeholder="Search Name...">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold">Standard</label>
                            <select name="standard_id" class="form-select form-select-sm">
                                <option value="">All Standards</option>
                                <?php foreach ($standards as $std): ?>
                                    <option value="<?php echo $std['stdid']; ?>" <?php echo $standard_id == $std['stdid'] ? 'selected' : ''; ?>>
                                        <?php echo $std['stdtext']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold">From</label>
                            <input type="date" name="date_from" class="form-control form-control-sm" value="<?php echo $date_from; ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold">To</label>
                            <input type="date" name="date_to" class="form-control form-control-sm" value="<?php echo $date_to; ?>">
                        </div>
                        <div class="col-12 text-end mt-3">
                            <a href="refund-management.php" class="btn btn-light btn-sm px-3 shadow-sm me-2"><i class="fas fa-sync-alt"></i> Reset</a>
                            <button type="submit" class="btn btn-primary btn-sm px-4 shadow-sm"><i class="fas fa-search me-1"></i> Search</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Payments Table -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3">
                    <h6 class="card-title mb-0 fw-bold">Eligible Transactions for Refund</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light small text-uppercase fw-bold text-muted">
                                <tr>
                                    <th class="ps-4">Date</th>
                                    <th>Student</th>
                                    <th>Transaction ID</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th class="text-end pe-4">Action</th>
                                </tr>
                            </thead>
                            <tbody class="small">
                                <?php if (empty($payments)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">No transactions found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($payments as $p): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="fw-bold"><?php echo date('d M, Y', strtotime($p['payment_date'])); ?></div>
                                            </td>
                                            <td>
                                                <div class="fw-bold"><?php echo $p['surname'] . ' ' . $p['student_name'] . ' ' . $p['fathers_name']; ?></div>
                                                <div class="text-muted smaller">ID: <?php echo $p['student_id']; ?></div>
                                            </td>
                                            <td>
                                                <div class="font-monospace text-primary"><?php echo $p['transaction_id']; ?></div>
                                                <div class="text-muted smaller">EasePay ID: <?php echo $p['payment_id']; ?></div>
                                            </td>
                                            <td class="fw-bold text-dark"><?php echo formatIndianCurrency($p['amount']); ?></td>
                                            <td><span class="badge bg-success-subtle text-success border border-success-subtle px-3">PAID</span></td>
                                            <td class="text-end pe-4">
                                                <button class="btn btn-danger btn-sm px-3 shadow-sm init-refund" 
                                                        data-txnid="<?php echo $p['transaction_id']; ?>"
                                                        data-amount="<?php echo $p['amount']; ?>"
                                                        data-phone="<?php echo $p['student_phone']; ?>"
                                                        data-email="<?php echo $p['student_email']; ?>"
                                                        data-name="<?php echo $p['surname'] . ' ' . $p['student_name'] . ' ' . $p['fathers_name']; ?>">
                                                    <i class="fas fa-undo me-1"></i> Refund
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-white border-0 py-3">
                    <?php 
                        $baseUrl = basename($_SERVER['PHP_SELF']) . '?' . http_build_query(array_diff_key($_GET, ['page' => '']));
                        echo renderPagination($page, $total_pages, $baseUrl, 2, $total_records, 'transactions'); 
                    ?>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Refund Modal -->
<div class="modal fade" id="refundModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-undo me-2"></i>Process Refund</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="refundForm">
                <div class="modal-body">
                    <div class="alert alert-warning small border-0 shadow-sm">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Warning:</strong> Refund is irreversible. Amount will be credited back to the source account.
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Student Name</label>
                        <input type="text" id="modal-name" class="form-control form-control-sm bg-light" readonly>
                    </div>

                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Transaction ID</label>
                            <input type="text" name="txnid" id="modal-txnid" class="form-control form-control-sm bg-light" readonly>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Total Paid Amount</label>
                            <input type="text" name="amount" id="modal-amount" class="form-control form-control-sm bg-light" readonly>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-danger">Refund Amount (₹)</label>
                        <input type="number" name="refund_amount" id="modal-refund-amount" step="0.01" class="form-control form-control-lg border-danger fw-bold" required>
                        <div class="form-text smaller">Max possible: ₹<span id="modal-max-refund"></span></div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Phone</label>
                            <input type="text" name="phone" id="modal-phone" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Email</label>
                            <input type="email" name="email" id="modal-email" class="form-control form-control-sm" required>
                        </div>
                    </div>
                    
                    <div class="mb-0">
                        <label class="form-label small fw-bold">Refund Reason</label>
                        <textarea name="reason" class="form-control form-control-sm" rows="2" placeholder="Enter reason for refund..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="submitRefund" class="btn btn-danger btn-sm px-4"><i class="fas fa-check-circle me-1"></i> Confirm Refund</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const refundModal = new bootstrap.Modal(document.getElementById('refundModal'));
    const refundForm = document.getElementById('refundForm');

    document.querySelectorAll('.init-refund').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('modal-name').value = this.dataset.name;
            document.getElementById('modal-txnid').value = this.dataset.txnid;
            document.getElementById('modal-amount').value = this.dataset.amount;
            document.getElementById('modal-refund-amount').value = this.dataset.amount;
            document.getElementById('modal-max-refund').textContent = this.dataset.amount;
            document.getElementById('modal-phone').value = this.dataset.phone;
            document.getElementById('modal-email').value = this.dataset.email;
            
            refundModal.show();
        });
    });

    refundForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const maxAmt = parseFloat(document.getElementById('modal-max-refund').textContent);
        const refundAmt = parseFloat(document.getElementById('modal-refund-amount').value);

        if (refundAmt > maxAmt) {
            alert('Refund amount cannot exceed the paid amount.');
            return;
        }

        if (!confirm('Are you absolutely sure you want to process this refund?')) {
            return;
        }

        const btn = document.getElementById('submitRefund');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Processing...';

        const formData = new FormData(this);
        
        fetch('process-refund.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                alert('Refund processed successfully! Transaction ID: ' + data.txn_id);
                location.reload();
            } else {
                alert('Error: ' + data.message);
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check-circle me-1"></i> Confirm Refund';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Something went wrong. Please try again.');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check-circle me-1"></i> Confirm Refund';
        });
    });
});
</script>

<?php include __DIR__ . '/../../include/footer.php'; ?>
