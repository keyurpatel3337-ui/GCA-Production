<?php
header('Content-Type: text/html; charset=utf-8');
/**
 * My Discount Requests
 * Allows Accountants to track the status of their discount requests
 */

require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/format_helper.php';

// Role check - Only Accountant can access (Principal/SuperAdmin can also see if they want)
if (!hasRole(ROLE_ACCOUNTANT) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN)) {
    set_flash_message('error', "Access denied.");
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "My Discount Requests";

// Fetch requests created by this user
try {
    $where = "d.created_by = ?";
    $params = [$_SESSION['user_id']];

    // If Principal/Admin, they might want to see all? 
    // But this page is specifically "My Requests".

    $sql = "SELECT 
                d.*,
                r.surname, r.student_name,
                e.enrollment_no,
                app.name as approved_by_name
            FROM tbl_post_admission_discounts d
            INNER JOIN tbl_gm_std_registration r ON d.student_id = r.id
            INNER JOIN tbl_enrolled_students e ON d.enrollment_id = e.enrollment_id
            LEFT JOIN tbl_users app ON d.approved_by = app.id
            WHERE $where
            ORDER BY d.created_at ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logError("My Discount Requests Error: " . $e->getMessage(), __FILE__, __LINE__, $e);
    $requests = [];
}

include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>

<div class="container-fluid">
    <div class="card card-info">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-list"></i> My Discount Requests</h3>
        </div>
        <div class="card-body">
            <?php if (empty($requests)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> You haven't submitted any discount requests yet.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>Date</th>
                                <th>Student</th>
                                <th>Requested Amt</th>
                                <th>Final Amt</th>
                                <th>Status</th>
                                <th>Reason</th>
                                <th>Approval Info</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $row): ?>
                                <tr>
                                    <td>
                                        <?php echo date('d-M-Y', strtotime($row['created_at'])); ?>
                                    </td>
                                    <td>
                                        <strong>
                                            <?php echo htmlspecialchars($row['surname'] . ' ' . $row['student_name'] ?? ''); ?>
                                        </strong><br>
                                        <small class="text-muted">
                                            <?php echo $row['enrollment_no']; ?>
                                        </small>
                                    </td>
                                    <td>₹
                                        <?php echo formatIndianCurrency($row['requested_amount'] ?? $row['discount_amount']); ?>
                                    </td>
                                    <td>
                                        <?php if ($row['status'] === 'approved'): ?>
                                            ₹
                                            <?php echo formatIndianCurrency($row['discount_amount']); ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($row['status'] === 'pending'): ?>
                                            <span class="badge bg-warning text-dark"><i class="fas fa-clock"></i> Pending</span>
                                        <?php elseif ($row['status'] === 'approved'): ?>
                                            <span class="badge bg-success"><i class="fas fa-check-circle"></i> Approved</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger"><i class="fas fa-times-circle"></i> Rejected</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><small>
                                            <?php echo htmlspecialchars($row['remarks'] ?? ''); ?>
                                        </small></td>
                                    <td>
                                        <?php if ($row['status'] === 'approved'): ?>
                                            <small>By:
                                                <?php echo htmlspecialchars($row['approved_by_name'] ?? 'Principal'); ?>
                                            </small><br>
                                            <small>
                                                <?php echo date('d-M-Y H:i', strtotime($row['approved_at'])); ?>
                                            </small>
                                        <?php elseif ($row['status'] === 'rejected'): ?>
                                            <small class="text-danger">Reason:
                                                <?php echo htmlspecialchars($row['rejection_reason'] ?? ''); ?>
                                            </small>
                                        <?php else: ?>
                                            <span class="text-muted">Waiting...</span>
                                        <?php endif; ?>
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