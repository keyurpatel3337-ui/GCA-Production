<?php
/**
 * Fee Defaulters List
 * Students who haven't paid fees beyond threshold days
 */

require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/common/constants.php';
require_once __DIR__ . '/../../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once __DIR__ . '/../../../../common/helpers/format_helper.php';
require_once __DIR__ . '/../../../../common/helpers/fee_helper.php';
require_once PAGINATION_FILE;

// Check access
if (!hasRole(ROLE_ACCOUNTANT) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "Fee Defaulters List" ;
$page_breadcrumb = "Fee Defaulters";

// Get filters
$threshold_days = $_GET['threshold'] ?? 30;
$course_filter = $_GET['course_id'] ?? '';
$filter_division = $_GET['division'] ?? '';
$filter_group = $_GET['group'] ?? '';
$search = $_GET['search'] ?? '';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;
$is_export = isset($_GET['export']) && $_GET['export'] === '1';

$dbOps = new DatabaseOperations();

// Get filter options
$groups = $dbOps->customSelect("SELECT id, group_name FROM tbl_group WHERE is_active = 1 ORDER BY group_name", []);
$divisions = $dbOps->select('tbl_division', ['id', 'division_name'], ['is_active' => 1], 'division_name ASC') ?: [];

// Build query for defaulters - students with pending fees older than threshold
$sql = "SELECT 
            es.enrollment_id,
            r.id as student_id,
            r.student_name,
            r.fathers_name as father_name,
            r.surname,
            r.mob as mobile,
            r.parent_mob as father_mobile,
            r.email,
            c.course_name as current_class,
            g.group_name,
            COALESCE(sfa.allocated_amount, 0) as total_fee,
            COALESCE(sfa.paid_amount, 0) as total_paid,
            COALESCE(sfa.pending_amount, 0) as pending_amount,
            sfa.due_date,
            DATEDIFF(CURDATE(), IFNULL(sfa.due_date, es.enrollment_date)) as days_overdue,
            IFNULL(last_payment.last_payment_date, 'Never') as last_payment_date
        FROM tbl_enrolled_students es
        JOIN tbl_gm_std_registration r ON es.registration_id = r.id
        LEFT JOIN tbl_group g ON r.group_id = g.id
        LEFT JOIN tbl_courses c ON r.course_id = c.id
        LEFT JOIN tbl_student_fee_allocation sfa ON es.registration_id = sfa.student_id
        LEFT JOIN (
            SELECT student_id, MAX(payment_date) as last_payment_date
            FROM tbl_payments 
            WHERE status = 'paid' 
            GROUP BY student_id
        ) last_payment ON r.id = last_payment.student_id
        WHERE es.is_active = 1
        AND COALESCE(sfa.pending_amount, 0) > 0
        AND DATEDIFF(CURDATE(), IFNULL(sfa.due_date, es.enrollment_date)) >= ?";

$params = [$threshold_days];

if (!empty($course_filter)) {
    if ($course_filter === '11th') {
        $sql .= " AND r.course_id IN (1, 2)";
    } elseif ($course_filter === '12th') {
        $sql .= " AND r.course_id IN (4, 5)";
    } elseif ($course_filter === 'Reneet') {
        $sql .= " AND r.course_id = 6";
    } else {
        $sql .= " AND r.course_id = ?";
        $params[] = $course_filter;
    }
}

if (!empty($filter_group)) {
    $sql .= " AND r.group_id = ?";
    $params[] = $filter_group;
}

if (!empty($filter_division)) {
    if ($filter_division === 'none') {
        $sql .= " AND (es.division_id IS NULL OR es.division_id = 0)";
    } else {
        $sql .= " AND es.division_id = ?";
        $params[] = $filter_division;
    }
}

if (!empty($search)) {
    $sql .= " AND (CONCAT(r.surname, ' ', r.student_name, ' ', IFNULL(r.fathers_name, '')) LIKE ? OR r.mob LIKE ? OR r.id LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

// Base query is now complete for counting purposes
$baseSql = $sql;

// Add sorting for data display in a separate variable to leave baseSql clean
$displaySql = $baseSql . " ORDER BY days_overdue ASC, pending_amount ASC";

// Export Handler - If exporting, get all records and skip pagination
if ($is_export) {
    $defaulters = $dbOps->customSelect($displaySql, $params);
    
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="Fee_Defaulters_'.date('Y-m-d').'.xls"');
    header('Cache-Control: max-age=0');
    
    echo '<table border="1">';
    echo '<tr><th>#</th><th>Enrollment ID</th><th>Student & Parent Details</th><th>Class</th><th>Group</th><th>Outstanding</th><th>Days Overdue</th><th>Last Payment</th></tr>';
    $sno = 1;
    $conn = $dbOps->getConnection();
    foreach ($defaulters as $student) {
        $summary = calculateStudentFeeSummary($conn, $student['student_id'], true);
        $pending = $summary['total_pending'] ?? 0;
        if ($pending <= 0) continue; // Skip if already paid
        
        $fullName = $student['student_name'] . ' ' . $student['father_name'] . ' ' . $student['surname'];
        echo '<tr>';
        echo '<td>'.$sno++.'</td>';
        echo '<td>'.$student['enrollment_id'].'</td>';
        echo '<td>';
        echo '<strong>'.$fullName.'</strong><br>';
        echo 'Mobile: '.$student['father_mobile'];
        if ($student['mobile'] != $student['father_mobile']) echo ' / '.$student['mobile'];
        echo '</td>';
        echo '<td>'.$student['current_class'].'</td>';
        echo '<td>'.$student['group_name'].'</td>';
        echo '<td>'.$pending.'</td>';
        echo '<td>'.$student['days_overdue'].'</td>';
        echo '<td>'.$student['last_payment_date'].'</td>';
        echo '</tr>';
    }
    echo '</table>';
    exit;
}

// 1. Get Total Count and Aggregate Totals using the clean base query
$countSql = "SELECT COUNT(*) as total_count, SUM(sub.pending_amount) as total_outstanding FROM (
    $baseSql
) as sub";
$summary = $dbOps->customSelectOne($countSql, $params);
$totalDefaulters = $summary['total_count'] ?? 0;
$totalOutstanding = $summary['total_outstanding'] ?? 0;

// 2. Get Paginated Data using the ordered display query
$paginatedSql = $displaySql . " LIMIT $per_page OFFSET $offset";
$defaulters = $dbOps->customSelect($paginatedSql, $params);

// Calculate averages for the full set using the clean base query
$avgDaysOverdue = 0;
if ($totalDefaulters > 0) {
    $avgSql = "SELECT AVG(sub.days_overdue) as avg_days FROM ($baseSql) as sub";
    $avgRow = $dbOps->customSelectOne($avgSql, $params);
    $avgDaysOverdue = $avgRow['avg_days'] ?? 0;
}

include '../../../include/header.php';
include '../../../include/navbar.php';
include '../../../include/sidebar.php';
?>

<style>
    .filter-card {
        background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
        border: none;
        border-radius: 12px;
    }
    
    .filter-card .form-label {
        color: white;
        font-weight: 500;
    }
    
    .stat-box {
        background: white;
        border-radius: 12px;
        padding: 20px;
        text-align: center;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    
    .stat-box h3 {
        font-size: 1.8rem;
        font-weight: 700;
        margin-bottom: 5px;
    }
    
    .stat-box p {
        color: #6c757d;
        margin: 0;
    }
    
    .severity-critical {
        background-color: #ffe6e6;
    }
    
    .severity-high {
        background-color: #fff3e6;
    }
    
    .severity-medium {
        background-color: #fffde6;
    }
    
    .badge-days {
        font-size: 0.9rem;
        padding: 5px 10px;
    }
    
    .days-critical { background-color: #dc3545; }
    .days-high { background-color: #fd7e14; }
    .days-medium { background-color: #ffc107; color: #000; }
    .days-low { background-color: #28a745; }
</style>



    <div class="container-fluid">
        <!-- Filters -->
        <div class="card filter-card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Overdue Threshold (Days)</label>
                        <select name="threshold" class="form-select">
                            <option value="15" <?php echo $threshold_days == 15 ? 'selected' : ''; ?>>15+ Days</option>
                            <option value="30" <?php echo $threshold_days == 30 ? 'selected' : ''; ?>>30+ Days</option>
                            <option value="60" <?php echo $threshold_days == 60 ? 'selected' : ''; ?>>60+ Days</option>
                            <option value="90" <?php echo $threshold_days == 90 ? 'selected' : ''; ?>>90+ Days</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Standard</label>
                        <select name="course_id" class="form-select">
                            <option value="">All Standards</option>
                            <option value="11th" <?php echo $course_filter == '11th' ? 'selected' : ''; ?>>11th</option>
                            <option value="12th" <?php echo $course_filter == '12th' ? 'selected' : ''; ?>>12th</option>
                            <option value="Reneet" <?php echo $course_filter == 'Reneet' ? 'selected' : ''; ?>>Reneet</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Group</label>
                        <select name="group" class="form-select">
                            <option value="">All Groups</option>
                            <?php foreach ($groups as $grp): ?>
                                <option value="<?php echo $grp['id']; ?>" 
                                    <?php echo $filter_group == $grp['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($grp['group_name'] ?? ''); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Division</label>
                        <select name="division" class="form-select">
                            <option value="">All Divisions</option>
                            <option value="none" <?php echo $filter_division === 'none' ? 'selected' : ''; ?>>Not Assigned</option>
                            <?php foreach ($divisions as $div): ?>
                                <option value="<?php echo $div['id']; ?>" 
                                    <?php echo $filter_division == $div['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($div['division_name'] ?? ''); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-white">Search Student</label>
                        <input type="text" name="search" class="form-control" placeholder="Name, ID, Mobile..." value="<?php echo htmlspecialchars($search ?? ''); ?>">
                    </div>
                    <div class="col-md-3 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-light">
                            <i class="fas fa-filter"></i> Apply
                        </button>
                        <a href="fee-defaulters.php" class="btn btn-outline-light">
                            <i class="fas fa-redo"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Summary Stats -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stat-box">
                    <h3 class="text-danger"><?php echo formatIndianCurrency($totalDefaulters, false); ?></h3>
                    <p>Total Defaulters</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-box">
                    <h3 class="text-warning">₹<?php echo formatIndianCurrency($totalOutstanding); ?></h3>
                    <p>Total Outstanding</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-box">
                    <h3 class="text-info"><?php echo round($avgDaysOverdue); ?> days</h3>
                    <p>Average Overdue Days</p>
                </div>
            </div>
        </div>

        <!-- Export & Actions -->
        <div class="mb-3 d-flex justify-content-between flex-wrap gap-2">
            <div>
                <button class="btn btn-warning" onclick="sendBulkReminders()">
                    <i class="fas fa-bell me-1"></i> Send Bulk Reminders
                </button>
            </div>
            <div class="d-flex gap-2">
                <a href="<?php echo $_SERVER['PHP_SELF'] . '?' . http_build_query(array_merge($_GET, ['export' => '1'])); ?>" class="btn btn-success">
                    <i class="fas fa-file-excel me-1"></i> Export Excel (All)
                </a>
                <button class="btn btn-danger" onclick="exportToPDF()">
                    <i class="fas fa-file-pdf me-1"></i> Export PDF
                </button>
            </div>
        </div>

        <!-- Data Table -->
        <div class="card">
            <div class="card-header bg-danger text-white">
                <h5 class="card-title mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Defaulters (<?php echo $threshold_days; ?>+ Days Overdue)
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="defaultersTable">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>Student & Parent Details</th>
                                <th>Class/Group</th>
                                <th>Last Payment</th>
                                <th>Days Overdue</th>
                                <th>Outstanding</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($defaulters)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <i class="fas fa-check-circle text-success fa-3x mb-3"></i>
                                        <p class="mb-0">No defaulters found with current criteria</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php $sno = ($page - 1) * $per_page + 1; 
                                    $conn = $dbOps->getConnection();
                                    foreach ($defaulters as $student): 
                                    $summary = calculateStudentFeeSummary($conn, $student['student_id'], true);
                                    $pending_actual = $summary['total_pending'] ?? 0;
                                    
                                    $days = $student['days_overdue'];
                                    $rowClass = '';
                                    if ($pending_actual <= 0) continue; // Skip if already paid per helper
                                    
                                    $badgeClass = 'days-low';
                                    if ($days > 90) {
                                        $rowClass = 'severity-critical';
                                        $badgeClass = 'days-critical';
                                    } elseif ($days > 60) {
                                        $rowClass = 'severity-high';
                                        $badgeClass = 'days-high';
                                    } elseif ($days > 30) {
                                        $rowClass = 'severity-medium';
                                        $badgeClass = 'days-medium';
                                    }
                                ?>
                                    <tr class="<?php echo $rowClass; ?>">
                                        <td><?php echo $sno++; ?></td>
                                        <td>
                                            <?php $fullName = ($student['student_name'] ?? '') . ' ' . ($student['father_name'] ?? '') . ' ' . ($student['surname'] ?? ''); ?>
                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($fullName ?? ''); ?></div>
                                            <div class="text-muted small">
                                                <i class="fas fa-phone-alt me-1"></i>
                                                <?php 
                                                echo htmlspecialchars($student['father_mobile'] ?? ''); 
                                                if (!empty($student['mobile']) && $student['mobile'] !== $student['father_mobile']) {
                                                    echo ' / ' . htmlspecialchars($student['mobile'] ?? '');
                                                }
                                                ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php 
                                            if ($student['last_payment_date'] == 'Never') {
                                                echo '<span class="badge bg-secondary">Never Paid</span>';
                                            } else {
                                                echo date('d-m-Y', strtotime($student['last_payment_date']));
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-days <?php echo $badgeClass; ?>">
                                                <?php echo $days; ?> days
                                            </span>
                                        </td>
                                        <td>
                                            <strong class="text-danger">
                                                ₹<?php echo formatIndianCurrency($pending_actual); ?>
                                            </strong>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="<?php echo PORTAL_URL; ?>/modules/payments/add-payment.php?student_id=<?php echo $student['student_id']; ?>" 
                                                   class="btn btn-primary" title="Collect Fee">
                                                    <i class="fas fa-rupee-sign"></i>
                                                </a>
                                                <button class="btn btn-warning" title="Send Reminder" 
                                                    onclick="sendReminder(<?php echo $student['student_id']; ?>)">
                                                    <i class="fas fa-bell"></i>
                                                </button>
                                                <a href="tel:<?php echo $student['father_mobile'] ?: $student['mobile']; ?>" 
                                                   class="btn btn-success" title="Call Parent">
                                                    <i class="fas fa-phone"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="card-footer bg-white">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="small text-muted">
                        Showing <?php echo $totalDefaulters > 0 ? ($offset + 1) : 0; ?> to 
                        <?php echo min($offset + $per_page, $totalDefaulters); ?> of 
                        <?php echo $totalDefaulters; ?> defaulters
                    </div>
                    
                    <?php 
                    $baseUrl = $_SERVER['PHP_SELF'] . '?' . http_build_query(array_diff_key($_GET, ['page' => '']));
                    echo renderPagination($page, $totalPages, $baseUrl, 2, $totalDefaulters, 'defaulters'); 
                    ?>
                </div>
            </div>
        </div>
    </div>

<?php include '../../../include/footer.php'; ?>

<script src="<?php echo BASE_URL; ?>/assets/vendor/xlsx/xlsx.full.min.js"></script>
<script>
function exportToExcel() {
    const table = document.getElementById('defaultersTable');
    const wb = XLSX.utils.table_to_book(table, {sheet: "Fee Defaulters"});
    XLSX.writeFile(wb, 'Fee_Defaulters_<?php echo date('Y-m-d'); ?>.xlsx');
}
function exportToPDF() {
    const params = new URLSearchParams(window.location.search);
    window.location.href = 'fee-defaulters-pdf.php?' + params.toString();
}

function sendReminder(studentId) {
    if (confirm('Send fee reminder to this student and parent?')) {
        fetch('<?php echo PORTAL_URL; ?>/api/send-fee-reminder.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({student_id: studentId})
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Reminder sent successfully!');
            } else {
                alert('Failed: ' + data.message);
            }
        });
    }
}

function sendBulkReminders() {
    if (confirm('Send fee reminders to ALL defaulters listed? This will send SMS/WhatsApp to all students and parents.')) {
        const studentIds = <?php echo json_encode(array_column($defaulters, 'student_id')); ?>;
        
        fetch('<?php echo PORTAL_URL; ?>/api/send-bulk-reminders.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({student_ids: studentIds})
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Bulk reminders sent successfully to ' + data.count + ' students!');
            } else {
                alert('Failed: ' + data.message);
            }
        });
    }
}
</script>



