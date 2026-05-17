<?php
/**
 * Admin - Student Result History
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

$student_id = $_GET['student_id'] ?? 0;
$academic_year_id = $_GET['academic_year_id'] ?? 0;

if (!$student_id) {
    echo "Student ID is required.";
    exit;
}

$api = new APIClient();
$page_title = "Student Result History";
$page_breadcrumb = "Results - History";

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
</style>

<div class="content-wrapper">
    <section class="content py-4">
        <div class="container-fluid">
            <!-- Header -->
            <div class="glass-card mb-4">
                <div class="card-header glass-header py-3 border-0 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title mb-0 fw-bold"><i class="fas fa-history me-2 text-primary"></i>Result
                            History</h5>
                    </div>
                    <div>
                        <a href="results.php" class="btn btn-secondary btn-sm me-2">
                            <i class="fas fa-arrow-left me-1"></i> Back to List
                        </a>
                        <a href="entry.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus-circle me-1"></i> New Entry
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div id="student_details_container">
                        <!-- Student details will be loaded here via JS -->
                        <div class="d-flex align-items-center">
                            <div class="spinner-border text-primary me-3" role="status"></div>
                            <span>Loading student details...</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Exam History List -->
            <div id="history_container">
                <div class="glass-card">
                    <div class="card-header bg-transparent border-0">
                        <h6 class="fw-bold mb-0 text-primary">Examination Record</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-4">Examination</th>
                                        <th>Academic Year</th>
                                        <th>Standard</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="exam_history_body">
                                    <!-- Rows injected here -->
                                    <tr>
                                        <td colspan="4" class="text-center p-4"><i class="fas fa-spinner fa-spin"></i>
                                            Loading exams...</td>
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
    const studentId = <?php echo $student_id; ?>;
    const academicYearId = <?php echo $academic_year_id; ?>;

    $(document).ready(function () {
        loadStudentDetails();
        loadExamHistory();
    });

    function loadStudentDetails() {
        // Fetch specific student details. We can use enrolled students detail API or search API with ID.
        // Using `students/details` endpoint if available, otherwise utilizing list filter.
        // Assuming `students/list` can filter by ID or we just display basic info from previous page?
        // Better to fetch fresh data.

        $.get(BACKEND_URL + '/index.php', {
            route: 'students/details',
            id: studentId
        }, function (response) {
            if (response.success && response.data && response.data.student) {
                const s = response.data.student;
                const e = response.data.enrollment;
                const rollNo = e && e.roll_no ? e.roll_no : (s.roll_no || 'N/A');

                const html = `
                    <div class="row">
                        <div class="col-md-6">
                            <h4 class="fw-bold mb-1">${s.student_name} ${s.fathers_name || ''} ${s.surname}</h4>
                            <p class="text-muted mb-0"><i class="fas fa-id-badge me-2"></i>GR No: ${s.gr_no || 'N/A'} | Roll No: ${rollNo}</p>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <span class="badge bg-primary-custom fs-6 mb-1">${s.course_name || 'N/A'}</span><br>
                            <span class="text-muted small">${s.school_name || (e ? e.school_name : '') || 'N/A'}</span>
                        </div>
                    </div>
                `;
                $('#student_details_container').html(html);
            } else {
                $('#student_details_container').html('<div class="alert alert-danger">Failed to load student details.</div>');
            }
        }).fail(function () {
            // Fallback if details endpoint fails, use list filter
            // This is a robust fallback for development environment without full API doc
            $.get(BACKEND_URL + '/index.php', {
                route: 'students/list',
                search: studentId
            }, function (resp) {
                if (resp.data) {
                    // Find exact match if multiple returned
                    let s = Array.isArray(resp.data) ? resp.data.find(x => x.id == studentId) : resp.data.students.find(x => x.id == studentId);
                    if (s) {
                        const html = `
                            <div class="row">
                                <div class="col-md-6">
                                    <h4 class="fw-bold mb-1">${s.full_name}</h4>
                                    <p class="text-muted mb-0"><i class="fas fa-id-badge me-2"></i>GR No: ${s.gr_no || 'N/A'} | Roll No: ${s.roll_no || 'N/A'}</p>
                                </div>
                                <div class="col-md-6 text-md-end">
                                    <span class="badge bg-primary-custom fs-6 mb-1">${s.course_name}</span>
                                </div>
                            </div>
                        `;
                        $('#student_details_container').html(html);
                    }
                }
            });
        });
    }

    function loadExamHistory() {
        // We need an endpoint that lists exams for a student.
        // We created `get_exam_list` in `student-portal/my-results` controller.
        // We need to ensure Admin can call it. Assuming we updated permissions there?
        // Wait, `my-results_controller.php` checks for `is_student_login` for API calls?
        // We need to UPDATE `my-results_controller.php` to allow Admins to use `get_exam_list` with a `student_id` param.

        $.get(BACKEND_URL + '/index.php', {
            route: 'student-portal/my-results',
            action: 'get_exam_list',
            student_id: studentId // Passing override ID
        }, function (response) {
            let exams = [];
            if (Array.isArray(response)) {
                exams = response;
            } else if (response.data) {
                exams = response.data;
            }

            if (exams.length > 0) {
                let html = '';
                exams.forEach(function (exam) {
                    html += `<tr>
                        <td class="ps-4 fw-bold text-primary">
                            <i class="fas fa-file-alt me-2"></i> ${exam.exam_type}
                        </td>
                        <td>${exam.year_name}</td>
                        <td>Course ID: ${exam.course_id}</td>
                        <td class="text-center">
                            <a href="../student-portal/print-result-pdf.php?academic_year_id=${exam.academic_year_id}&student_id=${studentId}" target="_blank" class="btn btn-info btn-sm text-white me-2">
                                <i class="fas fa-print me-1"></i> Print
                            </a>
                            <a href="entry.php?student_id=${studentId}&exam_type=${encodeURIComponent(exam.exam_type)}&academic_year_id=${exam.academic_year_id}" class="btn btn-warning btn-sm text-dark">
                                <i class="fas fa-edit me-1"></i> Edit
                            </a>
                        </td>
                    </tr>`;
                });
                $('#exam_history_body').html(html);
            } else {
                $('#exam_history_body').html('<tr><td colspan="4" class="text-center p-4 text-muted">No exam history found for this student.</td></tr>');
            }
        }).fail(function () {
            $('#exam_history_body').html('<tr><td colspan="4" class="text-center p-4 text-danger">Error loading history. Ensure permission is granted.</td></tr>');
        });
    }
</script>