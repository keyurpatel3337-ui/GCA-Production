<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;

// Check if user has appropriate role
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_COUNSELLOR) && !hasRole(ROLE_RECEPTION) && !hasRole(ROLE_COMPUTER_OPERATOR)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "Bulk Student Upload";
$page_breadcrumb = "Students";

// Fetch active academic years for dropdown
try {
    $academic_years = $dbOps->select('tbl_academic_years', ['id', 'year_name'], ['is_active' => 1], 'year_name DESC');
} catch (PDOException $e) {
    logDatabaseError($e, "Fetch Academic Years");
    $academic_years = [];
}

// Fetch active boards for dropdown
try {
    $boards = $dbOps->select('tbl_boards', ['id', 'board_name'], ['is_active' => 1], 'id ASC');
} catch (PDOException $e) {
    logDatabaseError($e, "Fetch Boards");
    $boards = [];
}

// Fetch schools
try {
    $schools = $dbOps->select('tbl_schools', ['id', 'school_name'], ['is_active' => 1], 'id ASC');
} catch (PDOException $e) {
    logDatabaseError($e, "Fetch Schools");
    $schools = [];
}

// Fetch mediums
try {
    $mediums = $dbOps->select('tbl_medium', ['id', 'medium_name'], ['is_active' => 1], 'id ASC');
} catch (PDOException $e) {
    logDatabaseError($e, "Fetch Mediums");
    $mediums = [];
}

// Fetch groups
try {
    $groups = $dbOps->select('tbl_group', ['id', 'group_name'], ['is_active' => 1], 'id ASC');
} catch (PDOException $e) {
    logDatabaseError($e, "Fetch Groups");
    $groups = [];
}

// Fetch courses
try {
    $courses = $dbOps->select('tbl_courses', ['id', 'course_name', 'standard'], ['is_active' => 1], 'id ASC');
} catch (PDOException $e) {
    logDatabaseError($e, "Fetch Courses");
    $courses = [];
}

include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="fw-bold text-dark"><i class="fas fa-file-import me-2"></i>Bulk Student Import</h1>
            <p class="text-muted">Import multiple students at once using a CSV file. Follow the template carefully.</p>
        </div>
    </div>

    <form id="bulkUploadForm" method="POST" action="bulk-upload-process.php" enctype="multipart/form-data">
        <div class="row">
            <div class="col-lg-8">
                <!-- Academic Selection Card -->
                <div class="academic-card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-university me-2"></i>Academic Selection</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-4">
                            <!-- Academic Year -->
                            <div class="col-md-6">
                                <label class="academic-field-label">Academic Year <span
                                        class="text-danger">*</span></label>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="custom-icon-box mb-0">
                                        <i class="fas fa-calendar-alt"></i>
                                    </div>
                                    <select name="academic_year_id" id="academic_year_id"
                                        class="form-select custom-select-minimal" required>
                                        <!-- <option value="">-- Select Year --</option> -->
                                        <?php foreach ($academic_years as $year): ?>
                                            <option value="<?php echo $year['id']; ?>" <?php echo ($year['id'] == ($_SESSION['academic_year_id'] ?? '')) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($year['year_name'] ?? ''); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <!-- School -->
                            <div class="col-md-6">
                                <label class="academic-field-label">School <span class="text-danger">*</span></label>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="custom-icon-box mb-0">
                                        <i class="fas fa-school"></i>
                                    </div>
                                    <select name="school_id" id="school_id" class="form-select custom-select-minimal"
                                        required>
                                        <!-- <option value="">-- Select School --</option> -->
                                        <?php foreach ($schools as $school): ?>
                                            <option value="<?php echo $school['id']; ?>" <?php echo ($school['id'] == 1) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($school['school_name'] ?? ''); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <!-- Board -->
                            <div class="col-md-6">
                                <label class="academic-field-label">Board <span class="text-danger">*</span></label>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="custom-icon-box mb-0">
                                        <i class="fas fa-chalkboard"></i>
                                    </div>
                                    <select name="board_id" id="board_id" class="form-select custom-select-minimal"
                                        required>
                                        <!-- <option value="">-- Select Board --</option> -->
                                        <?php foreach ($boards as $board): ?>
                                            <option value="<?php echo $board['id']; ?>">
                                                <?php echo htmlspecialchars($board['board_name'] ?? ''); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <!-- Medium -->
                            <div class="col-md-6">
                                <label class="academic-field-label">Medium <span class="text-danger">*</span></label>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="custom-icon-box mb-0">
                                        <i class="fas fa-language"></i>
                                    </div>
                                    <select name="medium_id" id="medium_id" class="form-select custom-select-minimal"
                                        required>
                                        <!-- <option value="">-- Select Medium --</option> -->
                                        <?php foreach ($mediums as $medium): ?>
                                            <option value="<?php echo $medium['id']; ?>">
                                                <?php echo htmlspecialchars($medium['medium_name'] ?? ''); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <!-- Group -->
                            <div class="col-md-6">
                                <label class="academic-field-label">Group <span class="text-danger">*</span></label>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="custom-icon-box mb-0">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <select name="group_id" id="group_id" class="form-select custom-select-minimal"
                                        required>
                                        <!-- <option value="">-- Select Group --</option> -->
                                        <?php foreach ($groups as $group): ?>
                                            <option value="<?php echo $group['id']; ?>">
                                                <?php echo htmlspecialchars($group['group_name'] ?? ''); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <!-- Course -->
                            <div class="col-md-6">
                                <label class="academic-field-label">Standard <span class="text-danger">*</span></label>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="custom-icon-box mb-0">
                                        <i class="fas fa-book-open"></i>
                                    </div>
                                    <select name="course_id" id="course_id" class="form-select custom-select-minimal"
                                        required>
                                        <!-- <option value="">-- Select Standard --</option> -->
                                        <?php foreach ($courses as $course): ?>
                                            <option value="<?php echo $course['id']; ?>"
                                                data-standard="<?php echo htmlspecialchars($course['standard'] ?? ''); ?>">
                                                <?php echo htmlspecialchars($course['course_name'] ?? ''); ?>
                                                <?php if (!empty($course['standard'])): ?>
                                                    (Std. <?php echo htmlspecialchars($course['standard'] ?? ''); ?>)
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Data Upload Card -->
                <div class="card card-enhanced shadow-sm">
                    <div class="card-header bg-dark">
                        <h5 class="card-title text-white mb-0">
                            <i class="fas fa-file-csv me-2"></i>Data Upload
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <label class="form-label">Select CSV File <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i
                                        class="fas fa-file-upload text-primary"></i></span>
                                <input type="file" name="csv_file" class="form-control" id="csvFile" accept=".csv"
                                    required>
                            </div>
                            <small class="text-muted d-block mt-2">Maximum file size: 5MB | Only .csv files are
                                supported</small>
                        </div>

                        <div class="card border-dashed bg-light">
                            <div class="card-body text-center py-4">
                                <i class="fas fa-file-excel fa-3x text-success mb-3 opacity-25"></i>
                                <h6>Standard Data Template</h6>
                                <p class="text-muted small mb-3">Make sure your file structure matches our template
                                    for successful processing.</p>
                                <a href="../../assets/templates/student-upload-template.csv?v=1.1"
                                    class="btn btn-primary-custom btn-sm">
                                    <i class="fas fa-download me-2"></i>Download CSV Template
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-light border-top-0 d-flex justify-content-end gap-2">
                        <a href="students.php?view=all" class="btn btn-light px-4">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                        <button type="submit" class="btn btn-success-custom px-4" id="uploadBtn">
                            <i class="fas fa-cloud-upload-alt me-2"></i>Upload Students
                        </button>
                        <button type="button" class="btn btn-secondary px-4" id="uploadingBtn" style="display:none;"
                            disabled>
                            <span class="spinner-border spinner-border-sm me-2"></span>Processing...
                        </button>
                    </div>
                </div>
            </div>

            <!-- Right Column: Sidebar / Additional Info -->
            <div class="col-lg-4 align-self-start">
                <div class="glass-card mb-4">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3"><i class="fas fa-check-circle text-success me-2"></i>Privacy &
                            Policy</h6>
                        <p class="text-muted small mb-0">
                            All uploaded data is processed securely. If a student's <strong>Mobile Number</strong> or <strong>Aadhaar</strong> already exists, their record will be <strong>updated</strong> with the new information (Upsert logic).
                        </p>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3"><i class="fas fa-info-circle text-primary me-2"></i>Formatting Guide</h6>
                        <ul class="text-muted small ps-3 mb-0">
                            <li class="mb-2"><strong>DOB</strong>: Use format <code>DD-MM-YYYY</code> (e.g., 25-09-2009).</li>
                            <li class="mb-2"><strong>Mobile</strong>: 10-digit number only.</li>
                            <li class="mb-2"><strong>Aadhaar</strong>: 12-digit number only.</li>
                            <li class="mb-2"><strong>Required</strong>: Ensure <em>Surname, Name, Father's Name, DOB, Gender, Mobile, Aadhaar, School, and District</em> are filled for every row.</li>
                        </ul>
                    </div>
                </div>

                <div class="card border-0 shadow-sm border-left-primary">
                    <div class="card-body">
                        <h6 class="fw-bold"><i class="fas fa-history me-2 text-primary"></i>Recent Uploads</h6>
                        <p class="text-muted small mb-0">Check the student list to verify recent data imports.</p>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<style>
    /* Global Overrides for this page */
    /* Academic Card Styling from Image */
    .academic-card {
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid #e2e8f0;
        background: #fff;
        margin-bottom: 2rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    }

    .academic-card .card-header {
        background: #2563eb !important;
        /* Force blue from image */
        color: white !important;
        padding: 1rem 1.5rem;
        font-weight: 600;
        font-size: 1.1rem;
        border-bottom: none;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .academic-card .card-header i {
        font-size: 1.25rem;
    }

    .card-body {
        padding: 2rem 2.5rem;
    }

    .selection-field-container {
        display: flex;
        flex-direction: column;
        gap: 5px;
        padding-bottom: 0.75rem;
    }

    .academic-field-label {
        font-weight: 600;
        font-size: 1rem;
        color: #1f2937;
        margin-bottom: 0.25rem;
    }

    .custom-icon-box {
        width: 38px;
        height: 38px;
        background: #fff;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        flex-shrink: 0;
    }

    .custom-icon-box i {
        color: #4b5563;
        font-size: 1rem;
    }

    .custom-select-minimal {
        border: 1px solid #d1d5db !important;
        border-radius: 8px !important;
        background-color: #fff !important;
        color: #111827 !important;
        font-size: 1rem !important;
        padding: 0.625rem 1rem !important;
        cursor: pointer;
        width: 100%;
        transition: all 0.2s ease;
    }

    .custom-select-minimal:focus {
        border-color: #2563eb !important;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1) !important;
    }

    .card-enhanced {
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }

    .card-enhanced .card-header {
        padding: 1rem 1.5rem;
        font-weight: 600;
    }

    .border-dashed {
        border: 2px dashed #cbd5e1 !important;
    }

    .btn-success-custom {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        background-color: #059669;
        border-color: #059669;
        color: #fff;
        font-weight: 600;
        padding: 0.625rem 1.5rem;
        border-radius: 8px;
        transition: all 0.2s ease;
    }

    .btn-success-custom:hover {
        background-color: #047857;
        border-color: #047857;
        transform: translateY(-1px);
    }

    .btn-primary-custom {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        background-color: #2563eb;
        border-color: #2563eb;
        color: #fff;
        font-weight: 600;
        padding: 0.625rem 1.5rem;
        border-radius: 8px;
        transition: all 0.2s ease;
    }

    .btn-primary-custom:hover {
        background-color: #1d4ed8;
        border-color: #1d4ed8;
        color: #fff;
        transform: translateY(-1px);
    }

    .glass-card {
        background: rgba(255, 255, 255, 0.7);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.3);
        border-radius: 12px;
    }

    .border-left-primary {
        border-left: 4px solid #2563eb !important;
    }

    /* Fixed Layout Header for consistency */
    .d-flex.justify-content-between.align-items-center.mb-4 h1 {
        font-size: 1.75rem;
        color: #111827;
    }
</style>

<script>
    document.getElementById('bulkUploadForm').addEventListener('submit', function (e) {
        const form = this;
        const uploadBtn = document.getElementById('uploadBtn');
        const uploadingBtn = document.getElementById('uploadingBtn');

        // Validate required fields
        const requiredFields = ['academic_year_id', 'school_id', 'board_id', 'medium_id', 'group_id', 'course_id'];
        for (const field of requiredFields) {
            if (!form[field].value) {
                e.preventDefault();
                if (typeof showToast === 'function') {
                    showToast('warning', 'Missing Information', `Please select ${field.replace('_id', '').replace('_', ' ')}`);
                } else {
                    alert(`Please select ${field.replace('_id', '').replace('_', ' ')}`);
                }
                return false;
            }
        }

        const fileInput = document.getElementById('csvFile');
        if (!fileInput.files.length) {
            e.preventDefault();
            if (typeof showToast === 'function') {
                showToast('warning', 'No File Selected', 'Please select a CSV file to upload');
            } else {
                alert('Please select a CSV file to upload');
            }
            return false;
        }

        // Show loading state
        uploadBtn.style.display = 'none';
        uploadingBtn.style.display = 'inline-block';

        return true;
    });
</script>

<?php include '../../include/footer.php'; ?>
</body>

</html>