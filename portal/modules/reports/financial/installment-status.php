<?php
/**
 * Installment Status Report
 */

require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/common/constants.php';
require_once __DIR__ . '/../../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once __DIR__ . '/../../../../common/helpers/format_helper.php';

if (!hasRole(ROLE_ACCOUNTANT) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "Installment Status";
$dbOps = new DatabaseOperations();

$installments = $dbOps->customSelect(
    "SELECT fi.*, CONCAT(r.surname, ' ', r.student_name) as student_name, r.mob as mobile, c.course_name as current_class
     FROM tbl_fee_installments fi
     JOIN tbl_enrolled_students es ON fi.student_id = es.registration_id
     JOIN tbl_gm_std_registration r ON es.registration_id = r.id
     LEFT JOIN tbl_courses c ON r.course_id = c.id
     WHERE es.is_active = 1
     ORDER BY fi.due_date ASC",
    []
);

include '../../../include/header.php';
include '../../../include/navbar.php';
include '../../../include/sidebar.php';
?>

<div class="container-fluid">
    <div class="text-end mb-3">
        <button class="btn btn-danger btn-sm" onclick="exportToPDF()"><i class="fas fa-file-pdf me-1"></i> Export
            PDF</button>
    </div>
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Installment Tracking</h5>
        </div>
        <div class="card-body">
            <?php if (empty($installments)): ?>
                <div class="text-center py-5">
                    <h4>No Installments Found</h4>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-striped">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>Student</th>
                                <th>Class</th>
                                <th>Installment</th>
                                <th>Amount</th>
                                <th>Due Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $sno = 1;
                            foreach ($installments as $inst): ?>
                                <tr>
                                    <td><?php echo $sno++; ?></td>
                                    <td><?php echo htmlspecialchars($inst['student_name'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($inst['current_class'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($inst['installment_name'] ?? 'Installment'); ?></td>
                                    <td>₹<?php echo formatIndianCurrency($inst['amount'] ?? 0); ?></td>
                                    <td><?php echo date('d-m-Y', strtotime($inst['due_date'])); ?></td>
                                    <td>
                                        <?php $status = $inst['status'] ?? 'pending';
                                        $badge = $status == 'paid' ? 'success' : ($status == 'partial' ? 'warning' : 'danger'); ?>
                                        <span class="badge bg-<?php echo $badge; ?>"><?php echo strtoupper($status); ?></span>
                                    </td>
                                    <td><a href="student-ledger.php?student_id=<?php echo $inst['student_id'] ?? ''; ?>"
                                            class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    function exportToPDF() {
        window.location.href = 'installment-status-pdf.php';
    }
</script>

<?php include '../../../include/footer.php'; ?>