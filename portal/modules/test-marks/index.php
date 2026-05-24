<?php

/**
 * Test Marks Module - Index Page
 * Lists all test marks (OMR MCQ and Descriptive) with filtering
 */

require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/format_helper.php';

// Check permissions
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_COUNSELLOR) && !hasRole(ROLE_COMPUTER_OPERATOR)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// Handle POST - Store filters in session
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['clear_filters'])) {
        unset($_SESSION['test_marks_filters']);
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    $_SESSION['test_marks_filters'] = $_POST;
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Get filter parameters from session
$filters = $_SESSION['test_marks_filters'] ?? [];
$test_type = $filters['type'] ?? 'all';
$status = $filters['status'] ?? 'all';
$date_from = $filters['date_from'] ?? '';
$date_to = $filters['date_to'] ?? '';
$search = $filters['search'] ?? '';
$selected_group = $filters['group_id'] ?? 'all';
$selected_division = $filters['division_id'] ?? 'all';

// Fetch groups and divisions for filters
$groups = $conn->query("SELECT id, group_name FROM tbl_group WHERE is_active = 1 ORDER BY group_name")->fetchAll(PDO::FETCH_ASSOC);
$divisions = $conn->query("SELECT id, division_name FROM tbl_division WHERE is_active = 1 ORDER BY division_name")->fetchAll(PDO::FETCH_ASSOC);

// Build query
$sql = "
    SELECT tm.*, 
           CONCAT(r.surname, ' ', r.student_name) AS student_name,
           r.mob AS student_mobile,
           ps.paper_set_name,
           ps.paper_code,
           u.name AS created_by_name,
           e.name AS evaluated_by_name,
           g.group_name,
           d.division_name
    FROM tbl_test_marks tm
    LEFT JOIN tbl_gm_std_registration r ON tm.student_id = r.id
    LEFT JOIN tbl_enrolled_students es ON tm.enrollment_id = es.enrollment_id
    LEFT JOIN tbl_group g ON r.group_id = g.id
    LEFT JOIN tbl_division d ON es.division_id = d.id
    LEFT JOIN tbl_paper_sets ps ON tm.paper_set_id = ps.id
    LEFT JOIN tbl_users u ON tm.created_by = u.id
    LEFT JOIN tbl_users e ON tm.evaluated_by = e.id
    WHERE 1=1
";

$params = [];

if ($test_type !== 'all') {
    $sql .= " AND tm.test_type = ?";
    $params[] = $test_type;
}

if ($status !== 'all') {
    $sql .= " AND tm.status = ?";
    $params[] = $status;
}

if ($date_from) {
    $sql .= " AND tm.test_date >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $sql .= " AND tm.test_date <= ?";
    $params[] = $date_to;
}

if ($selected_group !== 'all') {
    $sql .= " AND r.group_id = ?";
    $params[] = $selected_group;
}

if ($selected_division !== 'all') {
    $sql .= " AND es.division_id = ?";
    $params[] = $selected_division;
}

if ($search) {
    $sql .= " AND (r.student_name LIKE ? OR r.surname LIKE ? OR tm.test_name LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$sql .= " ORDER BY tm.created_at ASC";

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $test_marks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $test_marks = [];
    set_flash_message('error', 'Error fetching test marks: ' . $e->getMessage());
}

// Get counts
try {
    $op = new Operation();
    $count_results = $op->customSelect(
        'SELECT test_type, COUNT(*) as count FROM tbl_test_marks GROUP BY test_type',
        []
    );
    $counts = ['omr_mcq' => 0, 'descriptive' => 0, 'total' => 0];
    if (is_array($count_results)) {
        foreach ($count_results as $row) {
            if (isset($row['test_type'])) {
                $counts[$row['test_type']] = $row['count'];
                $counts['total'] += $row['count'];
            }
        }
    }
} catch (Exception $e) {
    $counts = ['omr_mcq' => 0, 'descriptive' => 0, 'total' => 0];
}

$page_title = "Test Marks" ;
include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>





    <div class="container-fluid">
        <!-- Statistics Cards -->
        <div class="row">
            <div class="col-lg-4 col-6">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3><?php echo $counts['total']; ?></h3>
                        <p>Total Test Records</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <form method="POST" class="small-box-footer css-index-ee35d7">
                        <input type="hidden" name="type" value="all">
                        <button type="submit" class="css-index-afc5b0">
                            View All <i class="fas fa-arrow-circle-right"></i>
                        </button>
                    </form>
                </div>
            </div>

            <div class="col-lg-4 col-6">
                <div class="small-box bg-primary">
                    <div class="inner">
                        <h3><?php echo $counts['omr_mcq']; ?></h3>
                        <p>OMR MCQ Tests</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-qrcode"></i>
                    </div>
                    <form method="POST" class="small-box-footer css-index-ee35d7">
                        <input type="hidden" name="type" value="omr_mcq">
                        <button type="submit" class="css-index-afc5b0">
                            View OMR Tests <i class="fas fa-arrow-circle-right"></i>
                        </button>
                    </form>
                </div>
            </div>

            <div class="col-lg-4 col-6">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3><?php echo $counts['descriptive']; ?></h3>
                        <p>Descriptive Tests</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-pencil-alt"></i>
                    </div>
                    <form method="POST" class="small-box-footer css-index-ee35d7">
                        <input type="hidden" name="type" value="descriptive">
                        <button type="submit" class="css-index-afc5b0">
                            View Descriptive <i class="fas fa-arrow-circle-right"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="fas fa-filter"></i> Filters</h5>
            </div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label">Test Type</label>
                        <select name="type" class="form-select">
                            <option value="all" <?php echo $test_type === 'all' ? 'selected' : ''; ?>>All Types</option>
                            <option value="omr_mcq" <?php echo $test_type === 'omr_mcq' ? 'selected' : ''; ?>>OMR MCQ
                            </option>
                            <option value="descriptive" <?php echo $test_type === 'descriptive' ? 'selected' : ''; ?>>
                                Descriptive</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending
                            </option>
                            <option value="evaluated" <?php echo $status === 'evaluated' ? 'selected' : ''; ?>>Evaluated
                            </option>
                            <option value="verified" <?php echo $status === 'verified' ? 'selected' : ''; ?>>Verified
                            </option>
                        </select>
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
                    <div class="col-md-2">
                        <label class="form-label">Group</label>
                        <select name="group_id" class="form-select">
                            <option value="all">All Groups</option>
                            <?php foreach ($groups as $g): ?>
                                <option value="<?php echo $g['id']; ?>" <?php echo $selected_group == $g['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($g['group_name'] ?? ''); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Division</label>
                        <select name="division_id" class="form-select">
                            <option value="all">All Divisions</option>
                            <?php foreach ($divisions as $d): ?>
                                <option value="<?php echo $d['id']; ?>" <?php echo $selected_division == $d['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($d['division_name'] ?? ''); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Student/Test name"
                            value="<?php echo htmlspecialchars($search ?? ''); ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2 w-100"><i class="fas fa-search"></i> Filter</button>
                        <button type="submit" name="clear_filters" value="1" class="btn btn-secondary"><i
                                class="fas fa-sync"></i></button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Test Marks List -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <?php
                    if ($test_type === 'all') {
                        echo 'All Test Marks';
                    } else {
                        echo ucfirst(str_replace('_', ' ', $test_type)) . ' Test Marks';
                    }
                    ?>
                </h3>
                <div class="card-tools d-flex align-items-center">
                    <div class="me-3 css-index-fa3505">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white border-end-0">
                                <i class="fas fa-search text-muted"></i>
                            </span>
                            <input type="text" id="liveSearch" class="form-control border-start-0 ps-0" placeholder="Live search results...">
                        </div>
                    </div>
                    <a href="add.php" class="btn btn-success-custom btn-sm me-2">
                        <i class="fas fa-plus"></i> Add Test Marks
                    </a>
                    <a href="import-omr.php" class="btn btn-info btn-sm">
                        <i class="fas fa-file-import"></i> Import from OMR
                    </a>
                </div>
            </div>
            <div class="card-body">
                <?php if (isset($_SESSION['success_msg'])): ?>
                    <div class="alert alert-success alert-dismissible">
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        <?php echo gca_safe_html($_SESSION['success_msg']);
                        ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_msg'])): ?>
                    <div class="alert alert-danger alert-dismissible">
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        <?php echo gca_safe_html($_SESSION['error_msg']);
                        ?>
                    </div>
                <?php endif; ?>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="testMarksTable">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>Student</th>
                                <th>Test Name</th>
                                <th>Paper Set</th>
                                <th>Type</th>
                                <th>Date</th>
                                <th>Marks</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($test_marks)): ?>
                                <tr>
                                    <td colspan="9" class="text-center">No test marks found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($test_marks as $index => $mark): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($mark['student_name'] ?? 'N/A'); ?></strong>
                                            <?php if (!empty($mark['student_mobile'])): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($mark['student_mobile'] ?? ''); ?></small>
                                            <?php endif; ?>
                                            <?php if (!empty($mark['group_name'])): ?>
                                                <br><span class="badge bg-light text-dark border"><i class="fas fa-users small me-1"></i><?php echo htmlspecialchars($mark['group_name'] ?? ''); ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($mark['division_name'])): ?>
                                                <span class="badge bg-light text-dark border"><i class="fas fa-layer-group small me-1"></i><?php echo htmlspecialchars($mark['division_name'] ?? ''); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($mark['test_name'] ?? 'N/A'); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($mark['paper_set_name'] ?? 'N/A'); ?>
                                            <?php if (!empty($mark['paper_code'])): ?>
                                                <br><small
                                                    class="text-muted"><?php echo htmlspecialchars($mark['paper_code'] ?? ''); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($mark['test_type'] === 'omr_mcq'): ?>
                                                <span class="badge bg-primary">OMR MCQ</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">Descriptive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $mark['test_date'] ? date('d-M-Y', strtotime($mark['test_date'])) : 'N/A'; ?>
                                        </td>
                                        <td>
                                            <?php if ($mark['test_type'] === 'omr_mcq'): ?>
                                                <strong><?php echo $mark['obtained_marks'] ?? 0; ?></strong> /
                                                <?php echo $mark['total_marks'] ?? 0; ?>
                                                <?php if (isset($mark['percentage'])): ?>
                                                    <br><small
                                                        class="text-muted"><?php echo formatIndianCurrency($mark['percentage']); ?>%</small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <strong><?php echo $mark['obtained_marks'] ?? 0; ?></strong> /
                                                <?php echo $mark['total_marks'] ?? 0; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $status_class = [
                                                'pending' => 'warning',
                                                'evaluated' => 'info',
                                                'verified' => 'success'
                                            ];
                                            $status_val = $mark['status'] ?? 'pending';
                                            ?>
                                            <span class="badge bg-<?php echo $status_class[$status_val] ?? 'secondary'; ?>">
                                                <?php echo ucfirst($status_val); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="view.php?id=<?php echo $mark['id']; ?>" class="btn btn-sm btn-info"
                                                title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit.php?id=<?php echo $mark['id']; ?>" class="btn btn-sm btn-warning"
                                                title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button class="btn btn-sm btn-danger"
                                                onclick="deleteItem(<?php echo $mark['id']; ?>)" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
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

<?php include '../../include/footer.php'; ?>

<script>
    function deleteItem(id) {
        DeleteHandler.deleteItem(id, 'test-marks/delete', 'test mark');
    }

    $(document).ready(function() {
        // Live Search Filtering
        $('#liveSearch').on('keyup', function() {
            const value = $(this).val().toLowerCase();
            $("#testMarksTable tbody tr").filter(function() {
                // Search in Student Name (col 2), Test Name (col 3), and Paper Set (col 4)
                const text = $(this).text().toLowerCase();
                $(this).toggle(text.indexOf(value) > -1);
            });
        });
    });
</script>


