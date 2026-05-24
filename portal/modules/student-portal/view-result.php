<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/format_helper.php';

// Check if user is Student (either regular login or student-specific login)
$is_student_login = isset($_SESSION['is_student_login']) && $_SESSION['is_student_login'] === true;
$student_id = $is_student_login ? $_SESSION['student_id'] : ($_SESSION['user_id'] ?? null);

if (!$is_student_login && !hasRole(ROLE_STUDENT)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$result_id = $_POST['id'] ?? 0;

// Get result details
try {
    $stmt = $conn->prepare("
        SELECT r.*, 
               ps.paper_name, ps.paper_code, ps.total_questions,
               omr.student_answers_json, omr.roll_number,
               ak.answer_key_json
        FROM tbl_test_results r
        LEFT JOIN tbl_omr_sheets omr ON r.omr_sheet_id = omr.id
        LEFT JOIN tbl_paper_sets ps ON omr.paper_set_id = ps.id
        LEFT JOIN tbl_answer_keys ak ON omr.answer_key_id = ak.id
        WHERE r.id = ? AND r.student_id = ?
    ");
    $stmt->execute([$result_id, $student_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        header('Location: my-results.php?error=not_found');
        exit;
    }
} catch (PDOException $e) {
    logDatabaseError($e, "Fetch Result Details");
    header('Location: my-results.php?error=database');
    exit;
}

$page_title = "View Result";
$page_breadcrumb = "View Result";

// Parse answer keys and student answers
$answer_key = json_decode($result['answer_key_json'], true) ?? [];
$student_answers = json_decode($result['student_answers_json'], true) ?? [];

include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>




<div class="container-fluid">
    <!-- Summary Cards -->
    <div class="row">
        <div class="col-lg-3 col-6">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3><?php echo $result['total_questions'] ?? 0; ?></h3>
                    <p>Total Questions</p>
                </div>
                <div class="icon">
                    <i class="fas fa-question-circle"></i>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3><?php echo $result['correct_answers'] ?? 0; ?></h3>
                    <p>Correct Answers</p>
                </div>
                <div class="icon">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-danger">
                <div class="inner">
                    <h3><?php echo $result['incorrect_answers'] ?? 0; ?></h3>
                    <p>Incorrect Answers</p>
                </div>
                <div class="icon">
                    <i class="fas fa-times-circle"></i>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-warning">
                <div class="inner">
                    <h3><?php echo formatIndianCurrency($result['percentage'] ?? 0); ?>%</h3>
                    <p>Percentage</p>
                </div>
                <div class="icon">
                    <i class="fas fa-chart-line"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title">Test Information</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-bordered">
                                <tr>
                                    <th width="40%">Paper Name</th>
                                    <td><?php echo htmlspecialchars($result['paper_name'] ?? 'N/A'); ?></td>
                                </tr>
                                <tr>
                                    <th>Paper Code</th>
                                    <td><?php echo htmlspecialchars($result['paper_code'] ?? 'N/A'); ?></td>
                                </tr>
                                <tr>
                                    <th>Roll Number</th>
                                    <td><?php echo htmlspecialchars($result['roll_number'] ?? 'N/A'); ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-bordered">
                                <tr>
                                    <th width="40%">Total Marks</th>
                                    <td><?php echo $result['total_marks'] ?? 0; ?></td>
                                </tr>
                                <tr>
                                    <th>Marks Obtained</th>
                                    <td><strong><?php echo $result['marks_obtained'] ?? 0; ?></strong></td>
                                </tr>
                                <tr>
                                    <th>Test Date</th>
                                    <td><?php echo isset($result['created_at']) ? date('d M Y', strtotime($result['created_at'])) : 'N/A'; ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($answer_key) && !empty($student_answers)): ?>
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Answer Sheet</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm">
                                <thead>
                                    <tr>
                                        <th>Q.No</th>
                                        <th>Your Answer</th>
                                        <th>Correct Answer</th>
                                        <th>Result</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    foreach ($answer_key as $key):
                                        $q_num = $key['question_number'];
                                        $correct_ans = $key['correct_answer'];

                                        // Find student's answer
                                        $student_ans = '';
                                        foreach ($student_answers as $ans) {
                                            if ($ans['q'] == $q_num) {
                                                $student_ans = $ans['ans'];
                                                break;
                                            }
                                        }

                                        $is_correct = (strtoupper($student_ans) == strtoupper($correct_ans));
                                        ?>
                                        <tr class="<?php echo $is_correct ? 'table-success' : 'table-danger'; ?>">
                                            <td><strong><?php echo $q_num; ?></strong></td>
                                            <td>
                                                <span class="badge badge-<?php echo $is_correct ? 'success' : 'danger'; ?>">
                                                    <?php echo $student_ans ?: 'Not Answered'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary"><?php echo $correct_ans; ?></span>
                                            </td>
                                            <td>
                                                <?php if ($is_correct): ?>
                                                    <i class="fas fa-check text-success"></i> Correct
                                                <?php else: ?>
                                                    <i class="fas fa-times text-danger"></i> Incorrect
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer">
                        <a href="my-results.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Results
                        </a>
                        <button onclick="window.print()" class="btn btn-primary">
                            <i class="fas fa-print"></i> Print Result
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include '../../include/footer.php'; ?>