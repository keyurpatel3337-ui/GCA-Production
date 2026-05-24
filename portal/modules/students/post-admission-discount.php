<?php
header('Content-Type: text/html; charset=utf-8');
/**
 * Post-Admission Discount Management
 * Allows Principal and Super Admin to apply discounts after admission confirmation
 */

require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once OPERATION_FILE;
require_once OPERATION_FILE;
require_once HELPER_ERROR_LOGGER;
require_once PAGINATION_FILE;
require_once __DIR__ . '/../../../common/helpers/format_helper.php';
require_once HELPERS_PATH . 'fee_helper.php';

// Role check - Principal, Super Admin, and Accountant can access (Accountant for requesting)
if (!hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_ACCOUNTANT)) {
    set_flash_message('error', "Access denied. Only Principal, Accountant and Super Admin can access post-admission discounts.");
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "Post-Admission Discount";

// Convert POST filters to GET for better bookmarking and UX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['clear_filters'])) {
        header('Location: ' . strtok($_SERVER["REQUEST_URI"], '?'));
        exit;
    }

    $queryParams = [];
    if (!empty($_POST['search']))
        $queryParams['search'] = $_POST['search'];
    if (!empty($_POST['school_id']))
        $queryParams['school_id'] = $_POST['school_id'];
    if (!empty($_POST['course_id']))
        $queryParams['course_id'] = $_POST['course_id'];
    if (!empty($_POST['group_id']))
        $queryParams['group_id'] = $_POST['group_id'];
    if (!empty($_POST['per_page']))
        $queryParams['per_page'] = $_POST['per_page'];

    $queryString = http_build_query($queryParams);
    header('Location: ' . strtok($_SERVER["REQUEST_URI"], '?') . '?' . $queryString);
    exit;
}

// Get filters from GET
$search = $_GET['search'] ?? '';
$filter_school = $_GET['school_id'] ?? '';
$filter_course = $_GET['course_id'] ?? '';
$filter_group = $_GET['group_id'] ?? '';
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$perPage = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 10;
$offset = ($page - 1) * $perPage;

// Get enrolled students for selection with fees from tbl_student_fee_allocation
try {
    // 1. Fetch Filter Options (Schools and Courses)
    $schools_stmt = $conn->query("SELECT id, school_name FROM tbl_schools WHERE is_active = 1 ORDER BY school_name");
    $schools = $schools_stmt->fetchAll(PDO::FETCH_ASSOC);

    $courses_stmt = $conn->query("SELECT id, course_name FROM tbl_courses WHERE is_active = 1 ORDER BY course_name");
    $courses = $courses_stmt->fetchAll(PDO::FETCH_ASSOC);

    $groups_stmt = $conn->query("SELECT id, group_name FROM tbl_group WHERE is_active = 1 ORDER BY group_name");
    $groups = $groups_stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Build Query
    $whereClauses = ["e.is_active = 1", "e.enrollment_status = 'active'"];
    $params = [];

    if (!empty($search)) {
        // Search by individual fields OR concatenated full name
        $whereClauses[] = "(
            r.student_name LIKE ? OR 
            r.surname LIKE ? OR 
            e.enrollment_no LIKE ? OR 
            r.mob LIKE ? OR 
            CONCAT(r.surname, ' ', r.student_name, ' ', r.fathers_name) LIKE ? OR
            CONCAT(r.student_name, ' ', r.surname) LIKE ?
        )";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm; // For Full Name (Surname First)
        $params[] = $searchTerm; // For Full Name (First Name First)
    }

    if (!empty($filter_school)) {
        $whereClauses[] = "r.school_id = ?";
        $params[] = $filter_school;
    }

    if (!empty($filter_course)) {
        if ($filter_course === '11th') {
            $whereClauses[] = "r.course_id = 1";
        } elseif ($filter_course === '12th') {
            $whereClauses[] = "r.course_id = 2";
        } elseif ($filter_course === 'Reneet') {
            $whereClauses[] = "r.course_id = 3";
        } else {
            $whereClauses[] = "r.course_id = ?";
            $params[] = $filter_course;
        }
    }

    if (!empty($filter_group)) {
        $whereClauses[] = "r.group_id = ?";
        $params[] = $filter_group;
    }

    $whereSql = implode(' AND ', $whereClauses);

    // 3. Get Total Count
    $countSql = "SELECT COUNT(*) 
                 FROM tbl_enrolled_students e
                 INNER JOIN tbl_gm_std_registration r ON e.registration_id = r.id
                 WHERE $whereSql";
    $countStmt = $conn->prepare($countSql);
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetchColumn();
    $totalPages = max(1, ceil($totalRecords / $perPage));

    // 4. Fetch Data with Pagination
    $sql = "SELECT 
            e.enrollment_id,
            e.enrollment_no,
            e.registration_id,
            COALESCE(sfa.allocated_amount, 0) AS net_fees,
            COALESCE(sfa.paid_amount, 0) AS fees_paid,
            COALESCE(sfa.pending_amount, 0) AS fees_pending,
            COALESCE(e.post_admission_discount_amount, 0) AS post_admission_discount_amount,
            COALESCE(e.post_admission_discount_remarks, '') AS post_admission_discount_remarks,
            r.surname,
            r.student_name,
            r.fathers_name,
            CONCAT(r.surname, ' ', r.student_name, ' ', r.fathers_name) AS full_name,
            r.mob,
            r.aadhaar,
            COALESCE(r.scholarship_amount, 0) AS scholarship_amount,
            COALESCE(r.additional_scholarship_amount, 0) AS additional_scholarship_amount,
            COALESCE(r.scholarship_percentage, 0) AS scholarship_percentage,
            (COALESCE(r.scholarship_amount, 0) + COALESCE(r.additional_scholarship_amount, 0)) AS total_scholarship,
            s.school_name,
            c.course_name,
            g.group_name,
            (SELECT COUNT(*) FROM tbl_post_admission_discounts WHERE enrollment_id = e.enrollment_id AND status = 'pending') AS pending_requests
        FROM tbl_enrolled_students e
        INNER JOIN tbl_gm_std_registration r ON e.registration_id = r.id
        LEFT JOIN tbl_student_fee_allocation sfa ON e.registration_id = sfa.student_id
        LEFT JOIN tbl_schools s ON r.school_id = s.id
        LEFT JOIN tbl_courses c ON r.course_id = c.id
        LEFT JOIN tbl_group g ON r.group_id = g.id
        WHERE $whereSql
        ORDER BY e.enrollment_date ASC
        LIMIT $perPage OFFSET $offset";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    logError("Post-Admission Discount - Fetch Students Error: " . $e->getMessage(), __FILE__, __LINE__, $e);
    $students = [];
    $totalRecords = 0;
    $totalPages = 1;
    $schools = [];
    $courses = [];
    $groups = [];
}

include '../../include/header.php';
?>
<!-- DataTables -->
<!-- <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css"> -->
<!-- <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css"> -->
<?php
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>



<div class="container-fluid">
    <?php if (isset($_SESSION['error_msg'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo gca_safe_html($_SESSION['error_msg']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['success_msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i>
            <?php echo gca_safe_html($_SESSION['success_msg']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Info Alert -->
    <div class="alert alert-info">
        <h5><i class="fas fa-info-circle"></i> Post-Admission Discount</h5>
        <p class="mb-0">Use this feature to apply additional discounts or scholarships to students after their
            admission has been confirmed. This discount will be applied to the pending fees.</p>
    </div>


    <!-- Search and Filter Section -->
    <div class="bg-white p-3 rounded shadow-sm mb-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0"><i class="fas fa-search"></i> Search & Filter Options</h5>
            <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse"
                data-bs-target="#filterBody" aria-expanded="false">
                <i class="fas fa-filter"></i> Toggle Filters
            </button>
        </div>

        <div id="filterBody" class="collapse show">
            <form method="POST" id="filterForm" class="p-3">
                <div class="row g-3">
                    <!-- Search Student -->
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Search Student</label>
                        <input type="text" name="search" class="form-control form-control-sm"
                            placeholder="Name, Enrollment No, Mobile..."
                            value="<?php echo htmlspecialchars($search ?? ''); ?>">
                    </div>

                    <!-- School Filter -->
                    <div class="col-md-4">
                        <label class="form-label fw-bold">School</label>
                        <select name="school_id" class="form-select form-select-sm">
                            <option value="">All Schools</option>
                            <?php foreach ($schools as $s): ?>
                                <option value="<?php echo $s['id']; ?>" <?php echo $filter_school == $s['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($s['school_name'] ?? ''); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Course & Group Filter Combined UI -->
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Standard</label>
                        <select name="course_id" class="form-select form-select-sm">
                            <option value="">All Standards</option>
                            <option value="11th" <?php echo $filter_course == '11th' ? 'selected' : ''; ?>>11th</option>
                            <option value="12th" <?php echo $filter_course == '12th' ? 'selected' : ''; ?>>12th</option>
                            <option value="Reneet" <?php echo $filter_course == 'Reneet' ? 'selected' : ''; ?>>Reneet</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold">Group</label>
                        <select name="group_id" class="form-select form-select-sm">
                            <option value="">All Groups</option>
                            <?php foreach ($groups as $g): ?>
                                <option value="<?php echo $g['id']; ?>" <?php echo $filter_group == $g['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($g['group_name'] ?? ''); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Filter Action Buttons -->
                    <div class="col-12">
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-search me-1"></i> Search & Filter
                            </button>
                            <button type="submit" name="clear_filters" value="1" class="btn btn-secondary btn-sm">
                                <i class="fas fa-undo me-1"></i> Clear Filters
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>




    <!-- Enrolled Students Table -->
    <div class="card card-primary">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-user-graduate"></i> Enrolled Students</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="studentsTable" class="table table-bordered table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Enrollment No</th>
                            <th>Student Name</th>
                            <th>Mobile</th>
                            <th>School</th>
                            <th>Course (Group)</th>
                            <th>Net Fees</th>
                            <th>Paid</th>
                            <th>Pending</th>
                            <th>Existing Scholarship</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student):
                            // Optimization: Check if we need to fetch fees via helper (if joined values are 0)
                            if (floatval($student['net_fees'] ?? 0) <= 0) {
                                $fee_summary = calculateStudentFeeSummary($conn, $student['registration_id']);
                                if (!empty($fee_summary)) {
                                    $student['net_fees'] = $fee_summary['total_allocated'];
                                    $student['fees_paid'] = $fee_summary['total_paid'];
                                    $student['fees_pending'] = $fee_summary['total_pending'];
                                }
                            }
                            ?>
                            <tr>
                                <td><span
                                        class="badge bg-primary"><?php echo htmlspecialchars($student['enrollment_no'] ?? ''); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($student['full_name'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($student['mob'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($student['school_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php
                                    $display_course = $student['course_name'] ?? 'N/A';
                                    if (!empty($student['group_name'])) {
                                        $display_course .= " (" . $student['group_name'] . ")";
                                    }
                                    echo htmlspecialchars($display_course ?? '');
                                    ?>
                                </td>
                                <td>₹<?php echo formatIndianCurrency($student['net_fees'] ?? 0); ?></td>
                                <td class="text-success">
                                    ₹<?php echo formatIndianCurrency($student['fees_paid'] ?? 0); ?>
                                </td>
                                <td class="text-danger">
                                    ₹<?php echo formatIndianCurrency($student['fees_pending'] ?? 0); ?>
                                </td>
                                <td>
                                    <?php
                                    $total_scholarship = floatval($student['total_scholarship']);
                                    if ($total_scholarship > 0):
                                        ?>
                                        <span class="badge bg-warning text-dark"
                                            title="Scholarship: ₹<?php echo formatIndianCurrency($student['scholarship_amount'] ?? 0); ?> + Additional: ₹<?php echo formatIndianCurrency($student['additional_scholarship_amount'] ?? 0); ?>">
                                            <i class="fas fa-award"></i>
                                            ₹<?php echo formatIndianCurrency($total_scholarship); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($student['pending_requests'] > 0): ?>
                                        <span class="badge bg-warning text-dark w-100 mb-1"
                                            title="A discount request is already pending approval"><i class="fas fa-clock"></i>
                                            Pending Approval</span>
                                    <?php else: ?>
                                        <a href="post-admission-discount-apply.php?enrollment_id=<?php echo $student['enrollment_id']; ?>"
                                            class="btn btn-sm btn-success w-100 mb-1">
                                            <i class="fas fa-percentage"></i> Apply Discount
                                        </a>
                                    <?php endif; ?>
                                    <?php if (hasRole(ROLE_PRINCIPLE) || hasRole(ROLE_SUPER_ADMIN)): ?>
                                        <a href="release-discount.php?enrollment_id=<?php echo $student['enrollment_id']; ?>"
                                            class="btn btn-sm btn-primary w-100">
                                            <i class="fas fa-hand-holding-usd"></i> Release Discount
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </table>
            </div>

            <!-- Pagination -->
            <?php if (!empty($students)): ?>
                <div class="mt-3">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <?php echo renderPagination($page, $totalPages, 2, $perPage); ?>
                        <div class="text-muted">
                            <?php echo getPaginationInfo($page, $perPage, $totalRecords); ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</div>

<!-- Modal -->
<div class="modal fade" id="discountModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-percentage"></i> Apply Post-Admission Discount</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Student Details Section -->
                <div class="card mb-3">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0"><i class="fas fa-user"></i> Student Details</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-sm table-bordered mb-0">
                                    <tr>
                                        <th width="40%">Enrollment No:</th>
                                        <td><span id="detail_enrollment" class="fw-bold text-primary"></span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Student Name:</th>
                                        <td><span id="detail_name" class="fw-bold"></span></td>
                                    </tr>
                                    <tr>
                                        <th>Mobile:</th>
                                        <td><span id="detail_mobile"></span></td>
                                    </tr>
                                    <tr>
                                        <th>School:</th>
                                        <td><span id="detail_school"></span></td>
                                    </tr>
                                    <tr>
                                        <th>Course:</th>
                                        <td><span id="detail_course"></span></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-header bg-secondary text-white">
                                        <h5 class="mb-0"><i class="fas fa-rupee-sign"></i> Fee Summary</h5>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm mb-0">
                                            <tr>
                                                <th>Net Fees:</th>
                                                <td class="text-end">₹<span id="detail_netfees">0</span></td>
                                            </tr>
                                            <tr>
                                                <th>Fees Paid:</th>
                                                <td class="text-end text-success">₹<span id="detail_feespaid">0</span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Fees Pending:</th>
                                                <td class="text-end text-danger">₹<span id="detail_feespending">0</span>
                                                </td>
                                            </tr>
                                            <tr id="scholarshipRow" style="display: none;">
                                                <th>Admission Scholarship:</th>
                                                <td class="text-end text-info">
                                                    <span class="badge bg-warning text-dark"><i
                                                            class="fas fa-award"></i> ₹<span
                                                            id="detail_scholarship">0</span></span>
                                                    <br><small id="scholarship_breakdown" class="text-muted"></small>
                                                </td>
                                            </tr>
                                            <tr id="existingDiscountRow" style="display: none;">
                                                <th>Post-Admission Discount:</th>
                                                <td class="text-end text-warning">
                                                    ₹<span id="detail_existingdiscount">0</span>
                                                    <br><small id="discount_remarks" class="text-muted"></small>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Discount Form Section -->
                <div class="card mb-3">
                    <div class="card-header bg-success text-white">
                        <h6 class="mb-0"><i class="fas fa-gift"></i> Discount Details</h6>
                    </div>
                    <div class="card-body">
                        <form id="discountForm" method="POST" action="post-admission-discount-save.php">
                            <input type="hidden" name="enrollment_id" id="enrollment_id">
                            <input type="hidden" name="calculated_discount_amount" id="calculated_discount_amount">

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Discount Type <span class="text-danger">*</span></label>
                                        <select name="discount_type" id="discount_type" class="form-control" required>
                                            <option value="">-- Select Type --</option>
                                            <option value="fixed">Fixed Amount (₹)</option>
                                            <option value="percentage">Percentage (%)</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Discount Value <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text" id="discountPrefix">₹</span>
                                            <input type="number" name="discount_value" id="discount_value"
                                                class="form-control" min="0" step="1" required
                                                placeholder="Enter discount value">
                                            <span class="input-group-text" id="discountSuffix"
                                                style="display: none;">%</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Calculated Discount Amount</label>
                                        <div class="input-group">
                                            <span class="input-group-text">₹</span>
                                            <input type="text" id="discount_amount_display"
                                                class="form-control bg-light" readonly>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row mt-3">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label>Reason for Discount <span class="text-danger">*</span></label>
                                        <textarea name="discount_reason" id="discount_reason" class="form-control"
                                            rows="3" required
                                            placeholder="Enter the reason for applying this discount..."></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- Updated Fee Summary -->
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h5 class="text-center mb-3">After Discount Applied</h5>
                                            <table class="table table-sm mb-0">
                                                <tr>
                                                    <th>Current Pending:</th>
                                                    <td class="text-end">₹<span id="current_pending">0</span>
                                                    </td>
                                                </tr>
                                                <tr class="text-success">
                                                    <th>Discount:</th>
                                                    <td class="text-end">- ₹<span id="discount_preview">0</span></td>
                                                </tr>
                                                <tr class="table-primary">
                                                    <th>New Pending:</th>
                                                    <td class="text-end"><strong>₹<span
                                                                id="new_pending">0</span></strong>
                                                    </td>
                                                </tr>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row mt-3">
                                <div class="col-12">
                                    <button type="submit" class="btn btn-success btn-lg" id="applyDiscountBtn">
                                        <i class="fas fa-check-circle"></i> Apply Discount
                                    </button>
                                    <button type="button" class="btn btn-secondary btn-lg" onclick="resetForm()">
                                        <i class="fas fa-times"></i> Cancel
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" class="btn btn-success" id="applyDiscountModalBtn">
                    <i class="fas fa-check-circle"></i> Apply Discount
                </button>
            </div>
        </div>
    </div>
</div>
</div>

<?php include '../../include/footer.php'; ?>

<!-- DataTables & Plugins -->


<script>
    $(document).ready(function () {
        var currentPending = 0;

        // Apply discount button click
        $(document).on('click', '.apply-discount-btn', function () {
            var btn = $(this);

            // Reset form
            $('#discountForm')[0].reset();
            $('#discount_type').val('');
            $('#discount_value').val('');
            $('#discount_reason').val('');
            $('#discount_amount_display').val('');
            $('#discountPrefix').show();
            $('#discountSuffix').hide();

            // Populate student details
            $('#detail_enrollment').text(btn.data('enrollment'));
            $('#detail_name').text(btn.data('name'));
            $('#detail_mobile').text(btn.data('mobile'));
            $('#detail_school').text(btn.data('school'));
            $('#detail_course').text(btn.data('course'));
            $('#detail_netfees').text(formatCurrency(btn.data('netfees')));
            $('#detail_feespaid').text(formatCurrency(btn.data('feespaid')));
            $('#detail_feespending').text(formatCurrency(btn.data('feespending')));

            // Show admission scholarship if exists
            var totalScholarship = parseFloat(btn.data('scholarship')) || 0;
            if (totalScholarship > 0) {
                $('#detail_scholarship').text(formatCurrency(totalScholarship));
                var scholarshipAmt = parseFloat(btn.data('scholarship-amt')) || 0;
                var additionalScholarship = parseFloat(btn.data('additional-scholarship')) || 0;
                var scholarshipPercent = parseFloat(btn.data('scholarship-percent')) || 0;

                var breakdown = '';
                if (scholarshipAmt > 0) breakdown += 'Base: ₹' + formatCurrency(scholarshipAmt);
                if (additionalScholarship > 0) breakdown += (breakdown ? ' + ' : '') + 'Additional: ₹' + formatCurrency(additionalScholarship);
                if (scholarshipPercent > 0) breakdown += (breakdown ? ' (' : '(') + scholarshipPercent + '%)';

                $('#scholarship_breakdown').text(breakdown);
                $('#scholarshipRow').show();
            } else {
                $('#scholarshipRow').hide();
            }

            // Show existing post-admission discount if exists
            var existingDiscount = parseFloat(btn.data('existingdiscount')) || 0;
            if (existingDiscount > 0) {
                $('#detail_existingdiscount').text(formatCurrency(existingDiscount));
                var discountRemarks = btn.data('discount-remarks') || '';
                $('#discount_remarks').text(discountRemarks ? 'Reason: ' + discountRemarks : '');
                $('#existingDiscountRow').show();
            } else {
                $('#existingDiscountRow').hide();
            }

            currentPending = parseFloat(btn.data('feespending')) || 0;
            $('#current_pending').text(formatCurrency(currentPending));
            $('#enrollment_id').val(btn.data('enrollment-id'));

            // Reset preview
            $('#discount_preview').text('0');
            $('#new_pending').text(formatCurrency(currentPending));

            // Show modal
            $('#discountModal').modal('show');
        });

        // Discount type change
        $('#discount_type').on('change', function () {
            if ($(this).val() === 'percentage') {
                $('#discountPrefix').hide();
                $('#discountSuffix').show();
                $('#discount_value').attr('max', 100);
            } else {
                $('#discountPrefix').show();
                $('#discountSuffix').hide();
                $('#discount_value').attr('max', currentPending);
            }
            calculateDiscount();
        });

        // Discount value change
        $('#discount_value').on('input', function () {
            calculateDiscount();
        });

        // Calculate discount
        function calculateDiscount() {
            var type = $('#discount_type').val();
            var value = parseFloat($('#discount_value').val()) || 0;
            var discountAmount = 0;

            if (type === 'percentage') {
                discountAmount = (currentPending * value) / 100;
            } else {
                discountAmount = value;
            }

            // Cap discount to pending amount
            if (discountAmount > currentPending) {
                discountAmount = currentPending;
            }

            var newPending = currentPending - discountAmount;

            $('#discount_amount_display').val(formatCurrency(discountAmount));
            $('#calculated_discount_amount').val(Math.round(discountAmount).toFixed(0));
            $('#discount_preview').text(formatCurrency(discountAmount));
            $('#new_pending').text(formatCurrency(newPending));
        }

        // Format currency
        function formatCurrency(amount) {
            var num = parseFloat(amount);
            if (isNaN(num)) {
                num = 0;
            }
            return Math.round(num).toLocaleString('en-IN', {
                maximumFractionDigits: 0
            });
        }

        // Apply discount button click (modal footer button)
        $('#applyDiscountModalBtn').on('click', function () {
            var discountAmount = parseFloat($('#calculated_discount_amount').val()) || 0;

            // Validate form
            if (!$('#discount_type').val()) {
                showToast('error', 'Error', 'Please select discount type');
                return;
            }

            if (!$('#discount_value').val() || $('#discount_value').val() <= 0) {
                showToast('error', 'Error', 'Please enter a valid discount value');
                return;
            }

            if (!$('#discount_reason').val().trim()) {
                showToast('error', 'Error', 'Please enter reason for discount');
                return;
            }

            if (discountAmount <= 0) {
                showToast('error', 'Error', 'Invalid discount amount calculated');
                return;
            }

            // Show confirmation
            showConfirm({
                title: 'Apply Post-Admission Discount',
                message: `Apply this discount?<br><br>
                    <strong>Student:</strong> ${$('#detail_name').text()}<br>
                    <strong>Enrollment:</strong> ${$('#detail_enrollment').text()}<br>
                    <strong>Discount Amount:</strong> ₹${formatCurrency(discountAmount)}<br>
                    <strong>Current Pending:</strong> ₹${$('#current_pending').text()}<br>
                    <strong>New Pending:</strong> ₹${$('#new_pending').text()}<br><br>
                    <em class="text-danger">This action cannot be undone!</em>`,
                confirmText: 'Yes, Apply Discount',
                confirmButtonClass: 'btn-success',
                onConfirm: function () {
                    const btn = $('#applyDiscountModalBtn');
                    const originalHtml = btn.html();
                    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Applying...');
                    $('#discountForm').submit();
                }
            });
        });
    });
</script>