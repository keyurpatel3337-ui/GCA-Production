<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;

// Check if user has appropriate role
if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_COMPUTER_OPERATOR])) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "Direct Admission (Class 12) Upload";
$page_breadcrumb = "Students";

// Fetch active academic years for dropdown
try {
    $academic_years = $dbOps->select('tbl_academic_years', ['id', 'year_name'], ['is_active' => 1], 'year_name DESC');
    $boards = $dbOps->select('tbl_boards', ['id', 'board_name'], ['is_active' => 1], 'id ASC');
    $schools = $dbOps->select('tbl_schools', ['id', 'school_name'], ['is_active' => 1], 'id ASC');
    $mediums = $dbOps->select('tbl_medium', ['id', 'medium_name'], ['is_active' => 1], 'id ASC');
    $groups = $dbOps->select('tbl_group', ['id', 'group_name'], ['is_active' => 1], 'id ASC');
    // Filter courses for Standard 12 only
    $courses = $dbOps->select('tbl_courses', ['id', 'course_name', 'standard'], ['is_active' => 1, 'standard' => 12], 'id ASC');
} catch (PDOException $e) {
    $academic_years = $boards = $schools = $mediums = $groups = $courses = [];
}

include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="fw-bold text-dark"><i class="fas fa-user-plus me-2"></i>Direct Admission (Class 12)</h1>
            <p class="text-muted">Directly import and confirm students into Class 12. This process will also generate
                enrollment numbers and assign fees.</p>
        </div>
    </div>

    <form id="directAdmissionForm" method="POST"
        action="../../../counselling-backend/controllers/students/direct-admission-bulk-upload.php"
        enctype="multipart/form-data">
        <div class="row">
            <div class="col-lg-8">
                <!-- Academic Selection Card -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white py-3">
                        <h5 class="mb-0"><i class="fas fa-university me-2"></i>Academic Selection</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-4">
                            <!-- Academic Year -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Academic Year <span
                                        class="text-danger">*</span></label>
                                <select name="academic_year_id" class="form-select" required>
                                    <?php foreach ($academic_years as $year): ?>
                                        <option value="<?php echo $year['id']; ?>" <?php echo ($year['id'] == ($_SESSION['academic_year_id'] ?? '')) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($year['year_name'] ?? ''); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <!-- School -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold">School <span class="text-danger">*</span></label>
                                <select name="school_id" class="form-select" required>
                                    <?php foreach ($schools as $school): ?>
                                        <option value="<?php echo $school['id']; ?>">
                                            <?php echo htmlspecialchars($school['school_name'] ?? ''); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <!-- Board -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Board <span class="text-danger">*</span></label>
                                <select name="board_id" class="form-select" required>
                                    <?php foreach ($boards as $board): ?>
                                        <option value="<?php echo $board['id']; ?>">
                                            <?php echo htmlspecialchars($board['board_name'] ?? ''); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <!-- Medium -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Medium <span class="text-danger">*</span></label>
                                <select name="medium_id" class="form-select" required>
                                    <?php foreach ($mediums as $medium): ?>
                                        <option value="<?php echo $medium['id']; ?>">
                                            <?php echo htmlspecialchars($medium['medium_name'] ?? ''); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <!-- Group -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Group <span class="text-danger">*</span></label>
                                <select name="group_id" class="form-select" required>
                                    <?php foreach ($groups as $group): ?>
                                        <option value="<?php echo $group['id']; ?>">
                                            <?php echo htmlspecialchars($group['group_name'] ?? ''); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <!-- Course -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Standard (Class 12) <span
                                        class="text-danger">*</span></label>
                                <select name="course_id" class="form-select" required>
                                    <option value="">-- Select Standard --</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?php echo $course['id']; ?>">
                                            <?php echo htmlspecialchars($course['course_name'] ?? ''); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Data Upload Card -->
                <div class="card shadow-sm">
                    <div class="card-header bg-dark text-white py-3">
                        <h5 class="mb-0"><i class="fas fa-file-csv me-2"></i>CSV File Upload</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <label class="form-label">Select CSV File <span class="text-danger">*</span></label>
                            <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                            <div class="form-text mt-2">Maximum file size: 5MB. Only .csv files are supported.</div>
                        </div>

                        <div class="card bg-light border-dashed">
                            <div class="card-body text-center py-4">
                                <i class="fas fa-file-excel fa-3x text-success mb-3 opacity-25"></i>
                                <h6>Direct Admission Template</h6>
                                <p class="text-muted small mb-3">Ensure your CSV follows the standard 21-column format
                                    including:<br>
                                    <code>Surname, Name, Father Name, DOB, Gender, Mobile, Aadhaar, ...</code>
                                </p>
                                <a href="../../assets/templates/student-upload-template.csv?v=1.1"
                                    class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-download me-2"></i>Download Template
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-white d-flex justify-content-end gap-2 py-3">
                        <a href="students.php" class="btn btn-light">Cancel</a>
                        <button type="submit" class="btn btn-success px-4" id="submitBtn">
                            <i class="fas fa-cloud-upload-alt me-2"></i>Process Admission
                        </button>
                        <button type="button" class="btn btn-secondary px-4 css-direct-admission-upload-93b8ea" id="loadingBtn"
                            disabled>
                            <span class="spinner-border spinner-border-sm me-2"></span>Processing...
                        </button>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm border-0 bg-light mb-4">
                    <div class="card-body">
                        <h6 class="fw-bold text-primary mb-3"><i class="fas fa-info-circle me-2"></i>Important Notes
                        </h6>
                        <ul class="small text-muted ps-3">
                            <li class="mb-2">Students will be <strong>automatically confirmed</strong> for admission.
                            </li>
                            <li class="mb-2"><strong>Enrollment Numbers</strong> will be generated immediately.</li>
                            <li class="mb-2"><strong>Class 12 Fees</strong> will be allocated based on the selected
                                course config.</li>
                            <li class="mb-2"><strong>Upsert Logic</strong>: Students with existing mobile numbers or Aadhaar will be <strong>updated</strong> with new information.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>



<script>
    document.getElementById('directAdmissionForm').addEventListener('submit', function () {
        document.getElementById('submitBtn').style.display = 'none';
        document.getElementById('loadingBtn').style.display = 'inline-block';
    });
</script>

<?php include '../../include/footer.php'; ?>