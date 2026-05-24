<?php
/**
 * View Term Result Report
 */

require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once __DIR__ . '/../../common/api_client.php';

$student_id = $_GET['student_id'] ?? 0;
$api = new APIClient();

// Get student info
$student = $api->get('students/details', ['id' => $student_id])['data'] ?? [];
if (empty($student)) {
    die("Student not found!");
}

// Get marks for all exam types
$exam_types = ['First Exam', 'Second Exam', 'Annual', 'Internal'];
$all_marks = [];
foreach ($exam_types as $type) {
    $response = $api->get('results/marks', ['action' => 'get', 'student_id' => $student_id, 'exam_type' => $type]);
    $all_marks[$type] = json_decode($response, true) ?? [];
}

$page_title = "Progress Report";
include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>



<div class="content-wrapper">
    <div class="container-fluid">
        <div class="report-card">
            <div class="report-header position-relative">
                <img src="../../assets/images/logo.png" height="80" class="mb-3" alt="Logo">
                <h2 class="fw-bold mb-0">GUJARAT COUNSELLING ACADEMY</h2>
                <p class="text-muted">Standard 11th Science - Academic Year 2024-25</p>
                <div class="position-absolute top-0 end-0 no-print">
                    <button onclick="window.print()" class="btn btn-outline-dark btn-sm">
                        <i class="fas fa-print me-1"></i> Print Report
                    </button>
                </div>
            </div>

            <div class="student-meta">
                <div class="row">
                    <div class="col-8">
                        <p class="mb-1"><strong>Student Name:</strong>
                            <?php echo htmlspecialchars($student['full_name'] ?? ''); ?>
                        </p>
                        <p class="mb-1"><strong>GR Number:</strong>
                            <?php echo htmlspecialchars($student['roll_no'] ?? 'N/A'); ?>
                        </p>
                        <p class="mb-1"><strong>Stream:</strong> Science</p>
                    </div>
                    <div class="col-4 text-end">
                        <p class="mb-1"><strong>Roll No:</strong>
                            <?php echo htmlspecialchars($student['mobile_number'] ?? 'N/A'); ?>
                        </p>
                        <p class="mb-1"><strong>Date:</strong>
                            <?php echo date('d M Y'); ?>
                        </p>
                    </div>
                </div>
            </div>

            <h5 class="fw-bold border-bottom pb-2 mb-3">Academic Performance Summary</h5>

            <div class="table-responsive">
                <table class="mark-table">
                    <thead>
                        <tr>
                            <th class="subject-name">Subject</th>
                            <th>First Exam (50)</th>
                            <th>Second Exam (50)</th>
                            <th>Internal (20)</th>
                            <th>Annual (100)</th>
                            <th>Grand Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Get unique subjects across all marks
                        $subjects_map = [];
                        foreach ($all_marks as $type => $marks) {
                            foreach ($marks as $m) {
                                if (!isset($subjects_map[$m['subject_id']])) {
                                    $subjects_map[$m['subject_id']] = $m['subject_name'];
                                }
                            }
                        }

                        $grand_total_overall = 0;
                        foreach ($subjects_map as $sid => $sname):
                            $first = 0;
                            $second = 0;
                            $internal = 0;
                            $annual = 0;

                            // Extract marks
                            foreach ($all_marks['First Exam'] as $m)
                                if ($m['subject_id'] == $sid)
                                    $first = $m['total_marks'];
                            foreach ($all_marks['Second Exam'] as $m)
                                if ($m['subject_id'] == $sid)
                                    $second = $m['total_marks'];
                            foreach ($all_marks['Internal'] as $m)
                                if ($m['subject_id'] == $sid)
                                    $internal = $m['total_marks'];
                            foreach ($all_marks['Annual'] as $m)
                                if ($m['subject_id'] == $sid)
                                    $annual = $m['total_marks'];

                            $row_total = $first + $second + $internal + $annual;
                            $grand_total_overall += $row_total;
                            ?>
                            <tr>
                                <td class="subject-name">
                                    <?php echo htmlspecialchars($sname ?? ''); ?>
                                </td>
                                <td>
                                    <?php echo $first ?: '-'; ?>
                                </td>
                                <td>
                                    <?php echo $second ?: '-'; ?>
                                </td>
                                <td>
                                    <?php echo $internal ?: '-'; ?>
                                </td>
                                <td>
                                    <?php echo $annual ?: '-'; ?>
                                </td>
                                <td class="fw-bold text-dark">
                                    <?php echo $row_total; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="total-row">
                            <td class="subject-name">GRAND TOTAL</td>
                            <td colspan="4" class="text-end">OVERALL MARKS OBTAINED</td>
                            <td class="bg-dark text-white">
                                <?php echo $grand_total_overall; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="row mt-5">
                <div class="col-4 text-center mt-5">
                    <div class="border-top pt-2">Class Teacher</div>
                </div>
                <div class="col-4 text-center mt-5">
                    <div class="border-top pt-2">Parent's Signature</div>
                </div>
                <div class="col-4 text-center mt-5">
                    <div class="border-top pt-2">Principal</div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../include/footer.php'; ?>