<?php
/**
 * Student Portal - My Results
 */

require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;

// Include controller to get initial data (academic years)
require_once dirname(dirname(dirname(__DIR__))) . '/counselling-backend/controllers/student-portal/my-results_controller.php';

include '../../include/header.php';
?>
<link rel="stylesheet" href="<?php echo PORTAL_URL; ?>/assets/css/modules/student-portal/my-results.css">
<?php
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>

<div class="content-wrapper">
    <section class="content py-4">
        <div class="container-fluid">
            <!-- Header -->
            <div class="glass-card mb-4">
                <div class="card-header glass-header py-3 border-0">
                    <h5 class="card-title mb-0 fw-bold"><i class="fas fa-graduation-cap me-2 text-primary"></i>My
                        Academic Results</h5>
                </div>
            </div>

            <!-- Exam List Display Area -->
            <div id="exam_list_container">
                <div class="glass-card">
                    <div class="card-body p-0">
                        <div id="loading_spinner" class="text-center p-5">
                            <i class="fas fa-spinner fa-spin fa-2x text-primary"></i> <br> Loading results...
                        </div>

                        <div class="table-responsive" id="exam_table_wrapper" style="display: none;">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-4">Examination</th>
                                        <th>Academic Year</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="exam_list_body">
                                    <!-- Rows will be injected here -->
                                </tbody>
                            </table>
                        </div>

                        <div id="no_exams_msg" class="p-5 text-center text-muted" style="display: none;">
                            <i class="fas fa-clipboard-list fa-3x mb-3 text-light-gray"></i>
                            <p>No academic results found.</p>
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
        loadExamList();
    });

    function loadExamList() {
        $('#loading_spinner').show();
        $('#exam_table_wrapper').hide();
        $('#no_exams_msg').hide();

        $.get(BACKEND_URL + '/index.php', {
            route: 'student-portal/my-results',
            action: 'get_exam_list'
        }, function (response) {
            $('#loading_spinner').hide();

            let exams = [];
            if (Array.isArray(response)) {
                exams = response;
            } else if (response.data && Array.isArray(response.data)) {
                exams = response.data;
            } else if (response.success && response.data) {
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
                        <td class="text-center">
                            <a href="print-result-pdf.php?academic_year_id=${exam.academic_year_id}&exam_type=${encodeURIComponent(exam.exam_type)}" target="_blank" class="btn btn-primary-custom btn-sm">
                                <i class="fas fa-print me-1"></i> Print Mark Sheet
                            </a>
                        </td>
                    </tr>`;
                });
                $('#exam_list_body').html(html);
                $('#exam_table_wrapper').fadeIn();
            } else {
                $('#no_exams_msg').show();
            }
        }).fail(function () {
            $('#loading_spinner').hide();
            showToast('error', 'Error', 'Failed to fetch exam list');
        });
    }
</script>