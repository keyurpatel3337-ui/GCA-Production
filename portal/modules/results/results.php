<?php
/**
 * Admin - Academic Results List
 */

require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once __DIR__ . '/../../common/api_client.php';

// Check access
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_COUNSELLOR)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$api = new APIClient();
$page_title = "Academic Results";
$page_breadcrumb = "Results - List";

// Fetch Dropdowns
$academic_years = $api->get('settings/academic-years')['data']['academic_years'] ?? [];
$courses = $api->get('academics/courses')['data'] ?? []; // Assuming this endpoint exists or similar
// If not, we can hardcode or fetch via direct DB helper if needed, but let's try to be consistent.
// `entry.php` only had academic years & subjects.
// To list students, we need Course/Standard.

include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>

<style>
    .glass-header {
        background: rgba(255, 255, 255, 0.8);
        backdrop-filter: blur(10px);
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    }

    .form-section-title {
        font-size: 0.875rem;
        color: var(--text-light);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-weight: 700;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
</style>

<div class="content-wrapper">
    <section class="content py-4">
        <div class="container-fluid">
            <!-- Header and Search -->
            <div class="glass-card mb-4">
                <div class="card-header glass-header py-3 border-0">
                    <h5 class="card-title mb-0 fw-bold"><i class="fas fa-list-alt me-2 text-primary"></i>Student Results
                    </h5>
                    <div class="card-tools">
                        <a href="bulk-marks-entry.php" class="btn btn-secondary btn-sm me-2">
                            <i class="fas fa-layer-group me-1"></i> Bulk Entry
                        </a>
                        <a href="entry.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus-circle me-1"></i> Manual Mark Entry
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="form-section-title">
                        <i class="fas fa-filter text-primary"></i> Filter Students
                    </div>
                    <form id="filter_form">
                        <div class="row g-4">
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Academic Session</label>
                                <select id="academic_year" name="academic_year" class="form-select" required>
                                    <option value="">Select Year</option>
                                    <?php foreach ($academic_years as $year): ?>
                                        <option value="<?php echo $year['id']; ?>" <?php echo $year['is_active'] ? 'selected' : ''; ?>>
                                            <?php echo $year['year_name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Standard</label>
                                <select id="course" name="course" class="form-select">
                                    <option value="">All Standards</option>
                                    <option value="11th">11th</option>
                                    <option value="12th">12th</option>
                                    <option value="Reneet">Reneet</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Search Student</label>
                                <input type="text" id="search" class="form-control" placeholder="Name or Roll No">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary-custom w-100">
                                    <i class="fas fa-search me-1"></i> Search
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Student List -->
            <div id="results_container">
                <div class="glass-card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-4">Student Name</th>
                                        <th>Roll No</th>
                                        <th>Standard</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="student_list_body">
                                    <!-- Rows injected here -->
                                    <tr>
                                        <td colspan="4" class="text-center p-4 text-muted">Please search to view
                                            students</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include '../../include/footer.php'; ?>

<script>
    $(document).ready(function () {
        $('#filter_form').on('submit', function (e) {
            e.preventDefault();
            loadStudents();
        });

        // Initial load if desired, or wait for search
        // loadStudents(); 
    });

    function loadStudents() {
        const year = $('#academic_year').val();
        const search = $('#search').val();
        const course = $('#course').val();

        if (!year) {
            showToast('warning', 'Warning', 'Please select Academic Year');
            return;
        }

        $('#student_list_body').html('<tr><td colspan="4" class="text-center p-4"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>');

        // Reuse enrolled students API or simple search
        $.get(BACKEND_URL + '/index.php', {
            route: 'students/list', // Using existing students list API
            academic_year: year,
            search: search,
            course_id: course,
            view: 'all' // or similar flag if needed
        }, function (response) {

            let students = [];
            if (response.data && response.data.students) {
                students = response.data.students;
            } else if (Array.isArray(response.data)) {
                students = response.data;
            }

            if (students.length > 0) {
                let html = '';
                students.forEach(function (s) {
                    // Link to PDF generator. Note: Access to student-portal/print-result-pdf.php needs check.
                    // We might need to allow admins to use that file.
                    // Or we assume admins can access it if we update the check there (step 15 check).
                    // The PDF file checks: $_SESSION['is_student_login']
                    // We need to UPDATE that file to allow admins too.

                    let printLink = `../student-portal/print-result-pdf.php?academic_year_id=${year}&student_id_override=${s.id}`;
                    // We will need to update print-result-pdf.php to accept student_id_override for admins.

                    html += `<tr>
                        <td class="ps-4">
                            <div class="fw-bold">${s.full_name}</div>
                            <small class="text-muted"><i class="fas fa-id-card me-1"></i> GR: ${s.gr_no || '-'}</small>
                        </td>
                        <td>${s.roll_no || '-'}</td>
                        <td>${s.course_name || '-'}</td>
                        <td class="text-center">
                            <a href="student-history.php?student_id=${s.id}&academic_year_id=${year}" class="btn btn-info btn-sm text-white">
                                <i class="fas fa-history me-1"></i> View History
                            </a>
                        </td>
                    </tr>`;
                });
                $('#student_list_body').html(html);
            } else {
                $('#student_list_body').html('<tr><td colspan="4" class="text-center p-4 text-muted">No students found</td></tr>');
            }
        });
    }

    function printMarkSheet(studentId, yearId) {
        // We'll mock a student login for validation OR update the PDF script.
        // Better to update PDF script to allow admins.
        // For now, let's open the URL with a special param we will implement next.
        window.open(`../student-portal/print-result-pdf.php?academic_year_id=${yearId}&student_id=${studentId}`, '_blank');
    }
</script>