<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/format_helper.php';

// Check if user is Super Admin or Principle
if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE])) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$result_id = $_POST['id'] ?? $_POST['result_id'] ?? 0;

// Get test result details
try {
    $op = new Operation();

    $result = $op->readWithJoin(
        'tbl_test_results tr',
        [
            'tr.*',
            'ps.paper_set_name',
            'ps.paper_code',
            's.student_name',
            's.surname',
            's.mob as mobile_number'
        ],
        [
            ['type' => 'JOIN', 'table' => 'tbl_paper_sets ps', 'on' => 'tr.paper_set_id = ps.id'],
            ['type' => 'LEFT', 'table' => 'tbl_gm_std_registration s', 'on' => 'tr.student_id = s.id']
        ],
        ['tr.id' => $result_id]
    );

    if (!$result) {
        set_flash_message('error', 'Result not found!');
        header('Location: ../results/results.php');
        exit;
    }

    // Build full student name
    $result['student_name'] = trim(($result['surname'] ?? '') . ' ' . ($result['student_name'] ?? ''));
    $result['student_name'] = $result['student_name'] ?: 'N/A';
    $result['roll_number'] = $result['mobile_number'] ?? 'N/A';

    // Get question-wise answers with topic information
    $questions = $op->selectWithJoin(
        'tbl_question_answers qa',
        ['qa.*', 'bt.id as topic_id', 's.subject_name as subject_category', 't.topic_name_english', 'bt.sr_no'],
        [
            ['type' => 'LEFT', 'table' => 'tbl_blueprint_questions bq', 'on' => 'qa.paper_set_id = bq.paper_set_id AND qa.question_number = bq.question_number'],
            ['type' => 'LEFT', 'table' => 'tbl_blueprint_topics bt', 'on' => 'bq.blueprint_topic_id = bt.id'],
            ['type' => 'LEFT', 'table' => 'tbl_subjects s', 'on' => 'bt.subject_id = s.id'],
            ['type' => 'LEFT', 'table' => 'tbl_topics t', 'on' => 'bt.topic_id = t.id']
        ],
        ['qa.omr_sheet_id' => $result['omr_sheet_id']],
        'qa.question_number'
    );

    // Get all topics for this paper set with question ranges
    $topics = $op->customSelect(
        "SELECT bt.id, s.subject_name as subject_category, bt.sr_no, t.topic_name_english, bt.total_questions,
                MIN(bq.question_number) as start_q, MAX(bq.question_number) as end_q
         FROM tbl_blueprint_topics bt
         JOIN tbl_blueprint_questions bq ON bt.id = bq.blueprint_topic_id
         LEFT JOIN tbl_subjects s ON bt.subject_id = s.id
         LEFT JOIN tbl_topics t ON bt.topic_id = t.id
         WHERE bt.paper_set_id = ?
         GROUP BY bt.id, s.subject_name, bt.sr_no, t.topic_name_english, bt.total_questions
         ORDER BY bt.sr_no",
        [$result['paper_set_id']]
    );
} catch (Exception $e) {
    set_flash_message('error', 'Database error: ' . $e->getMessage());
    header('Location: ../results/results.php');
    exit;
}

// Calculate subject-wise and topic-wise statistics
$subject_stats = [];
$topic_stats = [];

foreach ($questions as $q) {
    $subject = $q['subject_category'] ?? 'Unknown';
    $topic_id = $q['topic_id'] ?? 0;
    $difficulty = $q['difficulty_level'] ?? 'low';

    // Initialize subject stats
    if (!isset($subject_stats[$subject])) {
        $subject_stats[$subject] = [
            'low_total' => 0,
            'low_correct' => 0,
            'medium_total' => 0,
            'medium_correct' => 0,
            'high_total' => 0,
            'high_correct' => 0,
            'total_questions' => 0,
            'total_correct' => 0
        ];
    }

    // Initialize topic stats with difficulty breakdown
    if ($topic_id && !isset($topic_stats[$topic_id])) {
        $topic_stats[$topic_id] = [
            'low_total' => 0,
            'low_correct' => 0,
            'medium_total' => 0,
            'medium_correct' => 0,
            'high_total' => 0,
            'high_correct' => 0,
            'total' => 0,
            'correct' => 0,
            'topic_name' => $q['topic_name_english'] ?? 'Unknown',
            'subject' => $subject,
            'sr_no' => $q['sr_no'] ?? 0
        ];
    }

    // Update subject stats
    $subject_stats[$subject][$difficulty . '_total']++;
    $subject_stats[$subject]['total_questions']++;
    if ($q['is_correct']) {
        $subject_stats[$subject][$difficulty . '_correct']++;
        $subject_stats[$subject]['total_correct']++;
    }

    // Update topic stats with difficulty breakdown
    if ($topic_id) {
        $topic_stats[$topic_id][$difficulty . '_total']++;
        $topic_stats[$topic_id]['total']++;
        if ($q['is_correct']) {
            $topic_stats[$topic_id][$difficulty . '_correct']++;
            $topic_stats[$topic_id]['correct']++;
        }
    }
}

// Calculate percentages and categorize strength/weakness
foreach ($topic_stats as $id => &$stat) {
    $stat['percentage'] = $stat['total'] > 0 ? ($stat['correct'] / $stat['total']) * 100 : 0;
    $stat['strength'] = $stat['percentage'] == 100 ? 'Strong' : 'Needs Improvement';
}

// Calculate section-wise percentages
$section_percentages = [];
foreach ($subject_stats as $subject => $stats) {
    $section_percentages[$subject] = $stats['total_questions'] > 0
        ? ($stats['total_correct'] / $stats['total_questions']) * 100
        : 0;
}

// Stream recommendation logic
$maths_physics = ($section_percentages['Maths'] ?? 0) + ($section_percentages['Physics'] ?? 0);
$biology_chemistry = ($section_percentages['Biology'] ?? 0) + ($section_percentages['Chemistry'] ?? 0);
$science_total = ($section_percentages['Science Chemistry'] ?? 0) + ($section_percentages['Science Physics'] ?? 0) + ($section_percentages['Science Biology'] ?? 0);

$recommended_stream = 'Commerce';
if ($maths_physics >= 170) {
    $recommended_stream = 'Science (Engineering)';
} elseif ($biology_chemistry >= 170 || $science_total >= 170) {
    $recommended_stream = 'Science (Medical/NEET)';
} elseif (($section_percentages['Maths'] ?? 0) + ($section_percentages['Statistics'] ?? 0) >= 170) {
    $recommended_stream = 'Commerce';
}

$page_title = "Detailed Report Card";
$page_breadcrumb = "Card -";
?>
<?php include '../../include/header.php'; ?>




<div class="app-content css-result-e1ddcb">
    <div class="container-fluid">
        <!-- Page 1: Subject-wise Detailed Breakdown -->
        <div class="card shadow-sm css-result-76ffa0">
            <div class="report-header">
                <h3 class="css-result-ce3c7d">
                    <?php echo SYSTEM_NAME; ?>
                </h3>
                <p class="css-result-62fa68">GMSAT 2026 COUNSELING - Detailed Performance Report</p>
            </div>

            <div class="card-body css-result-822d04">
                <!-- Student Information -->
                <table class="table table-bordered student-info-table mb-4">
                    <tr>
                        <th class="css-result-5c6268">Roll No</th>
                        <td class="css-result-db1172"><?php echo htmlspecialchars($result['roll_number'] ?? ''); ?></td>
                        <th class="css-result-5c6268">Paper Code</th>
                        <td><?php echo htmlspecialchars($result['paper_code'] ?? ''); ?></td>
                    </tr>
                    <tr>
                        <th class="css-result-2ff067">Name</th>
                        <td colspan="3" class="css-result-db1172">
                            <?php echo htmlspecialchars($result['student_name'] ?? ''); ?>
                        </td>
                    </tr>
                    <tr>
                        <th class="css-result-2ff067">Mo.</th>
                        <td><?php echo htmlspecialchars($result['mobile_number'] ?? ''); ?></td>
                        <th class="css-result-2ff067">Test Date</th>
                        <td><?php echo date('d M Y', strtotime($result['created_at'])); ?></td>
                    </tr>
                </table>

                <!-- Subject-wise Detailed Table -->
                <h5 class="mb-3 css-result-705192">
                    Subject-wise Performance Analysis
                </h5>
                <div class="table-responsive">
                    <table class="table table-bordered subject-table">
                        <thead>
                            <tr>
                                <th rowspan="2" class="css-result-3d1f6b">Sub.</th>
                                <th rowspan="2" class="css-result-3d1f6b">Sr No.</th>
                                <th rowspan="2" class="css-result-3d1f6b">Topic</th>
                                <th colspan="2" class="css-result-45221e">Low</th>
                                <th colspan="2" class="css-result-f9cd49">Medium</th>
                                <th colspan="2" class="css-result-71a5fc">High</th>
                                <th colspan="2" class="css-result-d5bdb1">Total</th>
                                <th rowspan="2" class="css-result-5bd753">Total<br />Question
                                </th>
                                <th rowspan="2" class="css-result-0ce980">Right<br />Question
                                </th>
                            </tr>
                            <tr>
                                <th class="css-result-45221e">Question</th>
                                <th class="css-result-45221e">Right Q.</th>
                                <th class="css-result-f9cd49">Question</th>
                                <th class="css-result-f9cd49">Right Q.</th>
                                <th class="css-result-71a5fc">Question</th>
                                <th class="css-result-71a5fc">Right Q.</th>
                                <th class="css-result-d5bdb1">Question</th>
                                <th class="css-result-d5bdb1">Right Q.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $current_subject = '';
                            $subject_row_counts = [];

                            // First pass: count rows per subject
                            foreach ($topics as $topic) {
                                $subj = $topic['subject_category'];
                                if (!isset($subject_row_counts[$subj])) {
                                    $subject_row_counts[$subj] = 0;
                                }
                                $subject_row_counts[$subj]++;
                            }

                            $subject_first_row = [];
                            foreach ($topics as $index => $topic):
                                $subject = $topic['subject_category'];
                                $topic_id = $topic['id'];

                                // Get stats for this specific topic
                                $topic_data = $topic_stats[$topic_id] ?? [
                                    'low_total' => 0,
                                    'low_correct' => 0,
                                    'medium_total' => 0,
                                    'medium_correct' => 0,
                                    'high_total' => 0,
                                    'high_correct' => 0,
                                    'total' => 0,
                                    'correct' => 0
                                ];

                                // Get stats for the entire subject (for rowspan columns)
                                $subject_data = $subject_stats[$subject] ?? [
                                    'total_questions' => 0,
                                    'total_correct' => 0
                                ];

                                // Determine if this is first row of subject
                                $is_first_row = !isset($subject_first_row[$subject]);
                                if ($is_first_row) {
                                    $subject_first_row[$subject] = true;
                                }
                                ?>
                                <tr>
                                    <?php if ($is_first_row): ?>
                                        <td rowspan="<?php echo $subject_row_counts[$subject]; ?>"
                                            style="vertical-align: middle; font-weight: 600; background: #f8f9fa;">
                                            <?php echo htmlspecialchars($subject ?? ''); ?>
                                        </td>
                                    <?php endif; ?>
                                    <td><?php echo str_pad($topic['sr_no'], 2, '0', STR_PAD_LEFT); ?></td>
                                    <td class="css-result-85ae51">
                                        <?php echo htmlspecialchars($topic['topic_name_english'] ?? ''); ?>
                                    </td>

                                    <!-- Low Level -->
                                    <td class="css-result-12936e"><?php echo $topic_data['low_total']; ?></td>
                                    <td class="css-result-b03bdc">
                                        <?php echo $topic_data['low_correct']; ?>
                                    </td>

                                    <!-- Medium Level -->
                                    <td class="css-result-e390bc"><?php echo $topic_data['medium_total']; ?></td>
                                    <td class="css-result-65d96b">
                                        <?php echo $topic_data['medium_correct']; ?>
                                    </td>

                                    <!-- High Level -->
                                    <td class="css-result-8963da"><?php echo $topic_data['high_total']; ?></td>
                                    <td class="css-result-7a3d4c">
                                        <?php echo $topic_data['high_correct']; ?>
                                    </td>

                                    <!-- Total -->
                                    <td class="css-result-487916"><?php echo $topic_data['total']; ?></td>
                                    <td class="css-result-8c260a"><?php echo $topic_data['correct']; ?>
                                    </td>

                                    <?php if ($is_first_row): ?>
                                        <td rowspan="<?php echo $subject_row_counts[$subject]; ?>"
                                            style="vertical-align: middle; font-weight: 700; font-size: 14px; background: #f5f5f5;">
                                            <?php echo $subject_data['total_questions']; ?>
                                        </td>
                                        <td rowspan="<?php echo $subject_row_counts[$subject]; ?>"
                                            style="vertical-align: middle; font-weight: 700; font-size: 14px; background: #c8e6c9; color: #2e7d32;">
                                            <?php echo $subject_data['total_correct']; ?>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>

                            <!-- Grand Total Row -->
                            <tr class="css-result-ea1f50">
                                <td colspan="3" class="css-result-e03ec8">Total</td>
                                <td><?php echo $result['low_level_correct'] + $result['low_level_wrong']; ?></td>
                                <td class="css-result-d0cb9e"><?php echo $result['low_level_correct']; ?></td>
                                <td><?php echo $result['medium_level_correct'] + $result['medium_level_wrong']; ?></td>
                                <td class="css-result-bffedf"><?php echo $result['medium_level_correct']; ?></td>
                                <td><?php echo $result['high_level_correct'] + $result['high_level_wrong']; ?></td>
                                <td class="css-result-ae6780"><?php echo $result['high_level_correct']; ?></td>
                                <td><?php echo $result['total_questions']; ?></td>
                                <td class="css-result-d36771"><?php echo $result['correct_answers']; ?></td>
                                <td class="css-result-61808b">
                                    <?php echo $result['total_questions']; ?>
                                </td>
                                <td class="css-result-9f56eb">
                                    <?php echo $result['correct_answers']; ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Print Button -->
                <div class="text-end mt-3 no-print">
                    <button onclick="window.print()" class="btn btn-primary">
                        <i class="fas fa-print"></i> Print Report Card
                    </button>
                    <a href="../results/results.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Results
                    </a>
                </div>
            </div>
        </div>

        <!-- Page 2: Topic-wise Analysis & Stream Recommendation -->
        <div class="card shadow-sm">
            <div class="report-header">
                <h3 class="css-result-ce3c7d">
                    <?php echo SYSTEM_NAME; ?>
                </h3>
                <p class="css-result-62fa68">GMSAT 2026 COUNSELING - Topic Analysis & Stream Guidance
                </p>
            </div>

            <div class="card-body css-result-822d04">
                <!-- Student Information Header -->
                <table class="table table-bordered student-info-table mb-3">
                    <tr>
                        <th class="css-result-4532f2">Roll No.</th>
                        <td class="css-result-db1172"><?php echo htmlspecialchars($result['roll_number'] ?? ''); ?></td>
                    </tr>
                    <tr>
                        <th class="css-result-2ff067">Student Name</th>
                        <td class="css-result-db1172"><?php echo htmlspecialchars($result['student_name'] ?? ''); ?></td>
                    </tr>
                    <tr>
                        <th class="css-result-2ff067">Date of Counseling</th>
                        <td><?php echo date('d M Y'); ?></td>
                    </tr>
                    <tr>
                        <th class="css-result-2ff067">Counselor Name</th>
                        <td><!-- To be filled --></td>
                    </tr>
                </table>

                <!-- Topic-wise Performance -->
                <h5 class="mb-3 css-result-705192">
                    Topic-wise Detailed Performance
                </h5>
                <div class="table-responsive">
                    <table class="table table-bordered topic-table">
                        <thead>
                            <tr class="css-result-81d3f5">
                                <th>Subject</th>
                                <th>Topic</th>
                                <th>Question Range</th>
                                <th>Total Question</th>
                                <th>Correct</th>
                                <th>Percentage</th>
                                <th>Strength/Weakness</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topics as $topic):
                                $topic_id = $topic['id'];
                                $stat = $topic_stats[$topic_id] ?? ['total' => 0, 'correct' => 0, 'percentage' => 0];
                                $q_range = "Q({$topic['start_q']} - {$topic['end_q']})";
                                ?>
                                <tr>
                                    <td class="css-result-db1172">
                                        <?php echo htmlspecialchars($topic['subject_category'] ?? ''); ?>
                                    </td>
                                    <td class="css-result-102b7f">
                                        <?php echo htmlspecialchars($topic['topic_name_english'] ?? ''); ?>
                                    </td>
                                    <td><?php echo $q_range; ?></td>
                                    <td class="css-result-db1172"><?php echo $topic['total_questions']; ?></td>
                                    <td class="css-result-db50da"><?php echo $stat['correct']; ?></td>
                                    <td class="css-result-db1172">
                                        <span
                                            class="badge <?php echo $stat['percentage'] >= 75 ? 'bg-success' : ($stat['percentage'] >= 50 ? 'bg-warning text-dark' : 'bg-danger'); ?>">
                                            <?php echo formatIndianCurrency($stat['percentage']); ?>%
                                        </span>

                                    </td>
                                    <td>
                                        <span
                                            class="<?php echo $stat['percentage'] == 100 ? 'strength-strong' : 'strength-weak'; ?>">
                                            <?php echo $stat['percentage'] == 100 ? 'Strong' : 'Needs Improvement'; ?>
                                        </span>
                                    </td>
                                    <td></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Stream Recommendation -->
                <div class="recommendation-box mt-4">
                    <p class="css-result-cf07f5">Automated Stream Recommendation (Based on Sectional
                        Performance)</p>
                    <h4 class="css-result-62d9ae">
                        <?php echo $recommended_stream; ?>
                    </h4>
                </div>

                <!-- Formula Explanation -->
                <div class="alert alert-info mt-3">
                    <strong>Formula:</strong><br />
                    If (Maths + Physics) = 85% ? Recommend JEE (Science)<br />
                    If (Biology + Chemistry) = 85% OR Science = 85% ? Recommend NEET (Science)<br />
                    If (Maths + Statistics) = 85% but Science &lt; 50% ? Recommend Commerce<br />
                    <small><em>*Best - Foundation / Concept Strengthening Required</em></small>
                </div>

                <!-- Section-wise Strength Chart -->
                <h5 class="mt-4 mb-3 css-result-705192">
                    Section-wise Strength Chart
                </h5>
                <div class="row">
                    <div class="col-md-8 offset-md-2">
                        <?php foreach ($section_percentages as $subject => $percentage): ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="css-result-db1172"><?php echo htmlspecialchars($subject ?? ''); ?></span>
                                    <span class="css-result-1a231d"><?php echo formatIndianCurrency($percentage); ?>%</span>
                                </div>
                                <div class="css-result-effb3c">
                                    <div class="chart-bar css-result-27f14a">
                                        <?php if ($percentage > 10): ?>
                                            <?php echo formatIndianCurrency($percentage); ?>%
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Major Subject -->
                <div class="text-center mt-4 css-result-c0c07c">
                    <h6 class="css-result-523d25">
                        Suggested Major Focus:
                        <span class="css-result-8e24db">
                            <?php
                            arsort($section_percentages);
                            $top_subject = array_key_first($section_percentages);
                            echo htmlspecialchars($top_subject ?? 'Not Available');
                            ?>
                        </span>
                    </h6>
                </div>

                <!-- Print Button -->
                <div class="text-end mt-3 no-print">
                    <button onclick="window.print()" class="btn btn-primary">
                        <i class="fas fa-print"></i> Print Report Card
                    </button>
                    <a href="../results/results.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Results
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../include/footer.php'; ?>