<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;

// Check access
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_ESTABLISHMENT) && !hasRole(ROLE_RECEPTION)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "Issue New Certificate";
$page_breadcrumb = [
    'Home' => BASE_URL . '/portal/index.php',
    'Certificates' => 'index.php',
    'Issue New' => ''
];

include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>

<link rel="stylesheet" href="<?php echo PORTAL_URL; ?>/assets/css/modules/certificates/issue.css">

<?php
$op = new Operation();

// Get search query and student_id
$search = $_REQUEST['search'] ?? '';
$student_id = $_REQUEST['student_id'] ?? '';

$searchResults = [];
$selectedStudent = null;

// Handle search logic
if (!empty($search)) {
    try {
        $searchResults = $op->customSelect(
            "SELECT r.id, CONCAT(r.surname, ' ', r.student_name, ' ', IFNULL(r.fathers_name, '')) as name, 
                    r.mob as mobile, r.email, c.course_name as current_class, g.group_name
             FROM tbl_gm_std_registration r
             LEFT JOIN tbl_courses c ON r.course_id = c.id
             LEFT JOIN tbl_group g ON r.group_id = g.id
             WHERE (r.surname LIKE ? 
                OR r.student_name LIKE ? 
                OR r.mob LIKE ? 
                OR r.email LIKE ?
                OR CONCAT(r.surname, ' ', r.student_name, ' ', IFNULL(r.fathers_name, '')) LIKE ?
                OR r.id = ?)
             LIMIT 15",
            ["%$search%", "%$search%", "%$search%", "%$search%", "%$search%", $search]
        );
    } catch (Exception $e) {
        $searchResults = [];
    }
}

// Handle selected student logic
if (!empty($student_id)) {
    try {
        $selectedStudent = $op->customSelectOne(
            "SELECT s.*, c.course_name, g.group_name, ay.year_name as academic_year 
             FROM tbl_gm_std_registration s
             LEFT JOIN tbl_courses c ON s.course_id = c.id
             LEFT JOIN tbl_group g ON s.group_id = g.id
             LEFT JOIN tbl_academic_years ay ON s.academic_year_id = ay.id
             WHERE s.id = ?
             LIMIT 1",
            [$student_id]
        );

        if (!$selectedStudent) {
            error_log("Certificate Module: No student found with ID $student_id");
        }
    } catch (Exception $e) {
        error_log("Certificate Module Selection Error: " . $e->getMessage());
        $selectedStudent = null;
    }
}

?>

<div class="block-header">
    <div class="row">
        <div class="col-lg-7 col-md-6 col-sm-12">
            <h2><?php echo $page_title; ?></h2>
            <ul class="breadcrumb">
                <?php foreach ($page_breadcrumb as $label => $link): ?>
                    <?php if ($link): ?>
                        <li class="breadcrumb-item"><a href="<?php echo $link; ?>"><?php echo $label; ?></a></li>
                    <?php else: ?>
                        <li class="breadcrumb-item active"><?php echo $label; ?></li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="col-lg-5 col-md-6 col-sm-12 text-end">
            <a href="index.php" class="btn btn-dark btn-round"><i class="fas fa-arrow-left me-1"></i> Back to
                History</a>
        </div>
    </div>
</div>

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-11">
            <!-- Search & Selector -->
            <div class="ledger-search-box">
                <form method="GET" id="searchForm">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <label class="form-label text-white opacity-75 small uppercase fw-bold ls-1">FIND STUDENT
                                ACCOUNT</label>
                            <div class="input-group input-group-lg shadow-lg">
                                <span class="input-group-text bg-white border-0">
                                    <i class="fa-solid fa-search text-primary"></i>
                                </span>
                                <input type="text" name="search" class="form-control border-0 shadow-none issue-custom-1"
                                    placeholder="Enter Student ID, Name, Mobile or Email..."
                                    value="<?php echo htmlspecialchars($search ?? ''); ?>" autocomplete="off">
                                <button type="submit" class="btn btn-dark px-4 fw-bold">SEARCH</button>
                            </div>
                        </div>
                        <?php if ($student_id): ?>
                            <div class="col-md-4 text-end pt-4">
                                <a href="issue.php"
                                    class="btn btn-link text-white text-decoration-none bg-white bg-opacity-10 px-3 rounded-pill">
                                    <i class="fas fa-sync-alt me-1"></i> Clear Selection
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </form>

                <?php if (!empty($searchResults)): ?>
                    <div class="card mt-0 search-results-dropdown">
                        <div class="list-group list-group-flush">
                            <?php foreach ($searchResults as $row): ?>
                                <a href="?student_id=<?php echo $row['id']; ?>"
                                    class="list-group-item list-group-item-action p-3 search-result-pill border-0">
                                    <div class="d-flex w-100 justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1 fw-bold text-dark"><?php echo $row['name']; ?></h6>
                                            <small class="text-muted">
                                                <i class="fas fa-id-card me-1"></i> <?php echo $row['id']; ?> |
                                                <i class="fas fa-phone me-1"></i> <?php echo $row['mobile']; ?> |
                                                <i class="fas fa-graduation-cap me-1"></i>
                                                <?php echo $row['current_class']; ?> (<?php echo $row['group_name']; ?>)
                                            </small>
                                        </div>
                                        <span class="btn btn-primary btn-sm btn-select-student shadow-sm">Select Student
                                            <i class="fas fa-check-circle ms-1"></i></span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($selectedStudent): ?>
                <div class="row">
                    <div class="col-md-4">
                        <!-- Student Profile Card -->
                        <div class="card card-enhanced border-0 shadow-sm h-100">
                            <div class="card-body p-4 text-center">
                                <div class="profile-avatar mx-auto mb-3 issue-custom-2">
                                    <?php echo strtoupper(substr($selectedStudent['student_name'], 0, 1)); ?>
                                </div>
                                <h5 class="mb-1 fw-bold text-dark">
                                    <?php echo $selectedStudent['surname'] . ' ' . $selectedStudent['student_name'] . ' ' . ($selectedStudent['fathers_name'] ?? ''); ?>
                                </h5>
                                <p class="badge bg-soft-success text-success rounded-pill px-3 py-1 mb-4 issue-custom-3">
                                    <i class="fas fa-check-circle me-1"></i> ACTIVE STUDENT
                                </p>

                                <div class="text-start mt-3">
                                    <div class="d-flex justify-content-between border-bottom pb-2 mb-2">
                                        <span class="text-muted small">Student ID</span>
                                        <span class="fw-bold small"><?php echo $selectedStudent['id']; ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between border-bottom pb-2 mb-2">
                                        <span class="text-muted small">Standard</span>
                                        <span
                                            class="fw-bold small"><?php echo $selectedStudent['course_name'] ?? 'N/A'; ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between border-bottom pb-2 mb-2">
                                        <span class="text-muted small">Group</span>
                                        <span
                                            class="fw-bold small"><?php echo $selectedStudent['group_name'] ?? 'N/A'; ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted small">Mobile</span>
                                        <span class="fw-bold small"><?php echo $selectedStudent['mob']; ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-8">
                        <div class="card card-enhanced border-0 shadow-sm">
                            <div class="card-header bg-gradient-primary text-white py-3">
                                <h4 class="card-title mb-0">
                                    <i class="fas fa-certificate me-2"></i> Configure Certificate
                                </h4>
                            </div>
                            <div class="card-body p-4">
                                <form action="generate.php" method="POST" target="_blank">
                                    <input type="hidden" name="student_id" value="<?php echo $selectedStudent['id']; ?>">

                                    <div class="mb-4">
                                        <label for="certificate_type" class="form-label fw-bold">Certificate Type <span
                                                class="text-danger">*</span></label>
                                        <select name="certificate_type" id="certificate_type" class="form-select" required>
                                            <option value="">-- Select Certificate Type --</option>
                                            <option value="bonafide">Bonafide Certificate</option>
                                            <option value="character">Character / Conduct Certificate</option>
                                            <option value="slc">School Leaving Certificate (SLC) / TC</option>
                                            <option value="attempt">Attempt / Trial Certificate</option>
                                            <option value="fees_paid">Fees Paid Certificate</option>
                                            <option value="provisional">Provisional Passing Certificate</option>
                                            <option value="course_completion">Course Completion Certificate</option>
                                            <option value="sports">Sports / Extra-Curricular Achievement</option>
                                        </select>
                                        <small class="text-muted mt-2 d-block"><i class="fas fa-info-circle me-1"></i>
                                            Currently only
                                            Bonafide and SLC are fully templated.</small>
                                    </div>

                                    <div class="d-grid gap-2 mt-4">
                                        <button type="submit" class="btn btn-primary btn-lg shadow-sm">
                                            <i class="fas fa-file-pdf me-2"></i> Generate & Download PDF
                                        </button>
                                        <p class="text-center text-muted small mt-2">The certificate will be saved to the
                                            issued records automatically.</p>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="text-center py-5 bg-white rounded-4 shadow-sm">
                    <div class="opacity-25 mb-4">
                        <i class="fas fa-search-plus fa-4x text-primary"></i>
                    </div>
                    <h5 class="text-dark fw-bold">Ready to Generate</h5>
                    <p class="text-muted px-5">Please search for a student using the box above to begin configuring their
                        certificate.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../../include/footer.php'; ?>

<script>
    $(document).ready(function () {
        // Form specific scripts if needed
    });
</script>