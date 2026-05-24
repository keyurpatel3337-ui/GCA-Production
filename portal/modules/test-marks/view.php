<?php

/**
 * Test Marks Module - View Test Mark Details
 */

require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/format_helper.php';

// Check permissions
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_COUNSELLOR)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$id = $_POST['id'] ?? 0;

// Get test mark details
try {
    $op = new Operation();
    $mark = $op->readWithJoin(
        'tbl_test_marks tm',
        [
            'tm.*',
            "CONCAT(r.surname, ' ', r.student_name) AS student_name",
            'r.mob AS student_mobile',
            'r.fathers_name',
            'r.addr AS student_address',
            'ps.paper_set_name',
            'ps.paper_code',
            'u.name AS created_by_name',
            'e.name AS evaluated_by_name',
            'en.enrollment_no'
        ],
        [
            ['type' => 'LEFT', 'table' => 'tbl_gm_std_registration r', 'on' => 'tm.student_id = r.id'],
            ['type' => 'LEFT', 'table' => 'tbl_paper_sets ps', 'on' => 'tm.paper_set_id = ps.id'],
            ['type' => 'LEFT', 'table' => 'tbl_users u', 'on' => 'tm.created_by = u.id'],
            ['type' => 'LEFT', 'table' => 'tbl_users e', 'on' => 'tm.evaluated_by = e.id'],
            ['type' => 'LEFT', 'table' => 'tbl_enrolled_students en', 'on' => 'tm.enrollment_id = en.enrollment_id']
        ],
        ['tm.id' => $id]
    );

    if (!$mark) {
        set_flash_message('error', 'Test mark record not found.');
        header('Location: index.php');
        exit;
    }
} catch (Exception $e) {
    set_flash_message('error', 'Database error: ' . $e->getMessage());
    header('Location: index.php');
    exit;
}

$page_title = "View Test Marks - " . $mark['student_name'] . "";
include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>



<div class="container-fluid">
    <!-- Student Info Card -->
    <div class="card mb-3 border-0 shadow-sm overflow-hidden">
        <div class="card-header bg-success-custom text-white border-0 py-3">
            <h3 class="card-title mb-0 font-weight-bold"><i class="fas fa-user-graduate me-2"></i> Student Information
            </h3>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <p><strong>Student Name:</strong><br><?php echo htmlspecialchars($mark['student_name'] ?? ''); ?></p>
                </div>
                <div class="col-md-4">
                    <p><strong>Mobile:</strong><br><?php echo htmlspecialchars($mark['student_mobile'] ?? ''); ?></p>
                </div>
                <div class="col-md-4">
                    <p><strong>Father's
                            Name:</strong><br><?php echo htmlspecialchars($mark['fathers_name'] ?? 'N/A'); ?></p>
                </div>
            </div>
            <?php if ($mark['enrollment_no']): ?>
                <div class="row">
                    <div class="col-12">
                        <p><strong>Enrollment No:</strong> <span
                                class="badge bg-info"><?php echo htmlspecialchars($mark['enrollment_no'] ?? ''); ?></span></p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Test Info Card -->
    <div class="card mb-3 border-0 shadow-sm overflow-hidden">
        <div class="card-header bg-success-custom text-white border-0 py-3">
            <h3 class="card-title mb-0 font-weight-bold"><i class="fas fa-clipboard-list me-2"></i> Test Information
            </h3>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <p><strong>Test Name:</strong><br><?php echo htmlspecialchars($mark['test_name'] ?? ''); ?></p>
                </div>
                <div class="col-md-3">
                    <p><strong>Test Type:</strong><br>
                        <?php if ($mark['test_type'] === 'omr_mcq'): ?>
                            <span class="badge bg-primary"><i class="fas fa-qrcode"></i> OMR MCQ</span>
                        <?php else: ?>
                            <span class="badge bg-success"><i class="fas fa-pencil-alt"></i> Descriptive</span>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="col-md-3">
                    <p><strong>Test Date:</strong><br><?php echo date('d M Y', strtotime($mark['test_date'])); ?>
                    </p>
                </div>
                <div class="col-md-3">
                    <p><strong>Status:</strong><br>
                        <?php if ($mark['status'] === 'pending'): ?>
                            <span class="badge bg-warning text-dark"><i class="fas fa-clock"></i> Pending</span>
                        <?php elseif ($mark['status'] === 'evaluated'): ?>
                            <span class="badge bg-info"><i class="fas fa-check"></i> Evaluated</span>
                        <?php else: ?>
                            <span class="badge bg-success"><i class="fas fa-check-double"></i> Verified</span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <?php if ($mark['paper_set_name']): ?>
                <div class="row">
                    <div class="col-12">
                        <p><strong>Paper Set:</strong> <?php echo htmlspecialchars($mark['paper_set_name'] ?? ''); ?>
                            (<?php echo htmlspecialchars($mark['paper_code'] ?? ''); ?>)</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Results Card -->
    <div class="card mb-3 border-0 shadow-sm overflow-hidden">
        <div class="card-header bg-success-custom text-white border-0 py-3">
            <h3 class="card-title mb-0 font-weight-bold"><i class="fas fa-chart-bar me-2"></i> Results</h3>
        </div>
        <div class="card-body">
            <!-- Score Summary -->
            <div class="row text-center mb-4">
                <div class="col-md-4">
                    <div class="card bg-light">
                        <div class="card-body">
                            <h2 class="text-primary"><?php echo formatIndianCurrency($mark['obtained_marks']); ?></h2>
                            <p class="mb-0">Obtained Marks</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-light">
                        <div class="card-body">
                            <h2 class="text-secondary"><?php echo formatIndianCurrency($mark['total_marks']); ?></h2>
                            <p class="mb-0">Total Marks</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-light">
                        <div class="card-body">
                            <?php
                            $pct = $mark['percentage'];
                            $color = $pct >= 80 ? 'success' : ($pct >= 60 ? 'primary' : ($pct >= 40 ? 'warning' : 'danger'));
                            ?>
                            <h2 class="text-<?php echo $color; ?> fw-bold"><?php echo formatIndianCurrency($pct); ?>%
                            </h2>
                            <p class="mb-0 text-muted small uppercase">Percentage</p>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($mark['test_type'] === 'omr_mcq'): ?>
                <!-- OMR Details -->
                <h5><i class="fas fa-qrcode"></i> OMR MCQ Analysis</h5>
                <div class="row mb-3">
                    <div class="col-md-3 text-center">
                        <div class="card border-success">
                            <div class="card-body">
                                <h3 class="text-success"><?php echo $mark['correct_answers'] ?? 0; ?></h3>
                                <p class="mb-0">Correct</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 text-center">
                        <div class="card border-danger">
                            <div class="card-body">
                                <h3 class="text-danger"><?php echo $mark['wrong_answers'] ?? 0; ?></h3>
                                <p class="mb-0">Wrong</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 text-center">
                        <div class="card border-secondary">
                            <div class="card-body">
                                <h3 class="text-secondary"><?php echo $mark['unanswered'] ?? 0; ?></h3>
                                <p class="mb-0">Unanswered</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 text-center">
                        <div class="card border-info">
                            <div class="card-body">
                                <h3 class="text-info"><?php echo $mark['total_questions'] ?? 100; ?></h3>
                                <p class="mb-0">Total Questions</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Difficulty Level Breakdown -->
                <h5><i class="fas fa-layer-group"></i> Difficulty Level Performance</h5>
                <div class="row">
                    <div class="col-md-4">
                        <div class="card bg-success bg-opacity-25">
                            <div class="card-header bg-success text-white">Low Level</div>
                            <div class="card-body">
                                <?php
                                $low_total = ($mark['low_level_correct'] ?? 0) + ($mark['low_level_wrong'] ?? 0);
                                $low_pct = $low_total > 0 ? (($mark['low_level_correct'] ?? 0) / $low_total * 100) : 0;
                                ?>
                                <p>Correct: <strong
                                        class="text-success"><?php echo $mark['low_level_correct'] ?? 0; ?></strong></p>
                                <p>Wrong: <strong class="text-danger"><?php echo $mark['low_level_wrong'] ?? 0; ?></strong>
                                </p>
                                <p>Accuracy: <strong><?php echo formatIndianCurrency($low_pct); ?>%</strong></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-warning bg-opacity-25">
                            <div class="card-header bg-warning">Medium Level</div>
                            <div class="card-body">
                                <?php
                                $med_total = ($mark['medium_level_correct'] ?? 0) + ($mark['medium_level_wrong'] ?? 0);
                                $med_pct = $med_total > 0 ? (($mark['medium_level_correct'] ?? 0) / $med_total * 100) : 0;
                                ?>
                                <p>Correct: <strong
                                        class="text-success"><?php echo $mark['medium_level_correct'] ?? 0; ?></strong>
                                </p>
                                <p>Wrong: <strong
                                        class="text-danger"><?php echo $mark['medium_level_wrong'] ?? 0; ?></strong></p>
                                <p>Accuracy: <strong><?php echo formatIndianCurrency($med_pct); ?>%</strong></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-danger bg-opacity-25">
                            <div class="card-header bg-danger text-white">High Level</div>
                            <div class="card-body">
                                <?php
                                $high_total = ($mark['high_level_correct'] ?? 0) + ($mark['high_level_wrong'] ?? 0);
                                $high_pct = $high_total > 0 ? (($mark['high_level_correct'] ?? 0) / $high_total * 100) : 0;
                                ?>
                                <p>Correct: <strong
                                        class="text-success"><?php echo $mark['high_level_correct'] ?? 0; ?></strong>
                                </p>
                                <p>Wrong: <strong class="text-danger"><?php echo $mark['high_level_wrong'] ?? 0; ?></strong>
                                </p>
                                <p>Accuracy: <strong><?php echo formatIndianCurrency($high_pct); ?>%</strong></p>
                            </div>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <!-- Descriptive Details -->
                <h5><i class="fas fa-pencil-alt"></i> Subject-wise Performance</h5>
                <?php
                $subjects = json_decode($mark['subject_marks_json'] ?? '[]', true);
                if ($subjects && is_array($subjects) && count($subjects) > 0):
                    ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead class="table-dark">
                                <tr>
                                    <th>Subject</th>
                                    <th>Total Marks</th>
                                    <th>Obtained</th>
                                    <th>Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($subjects as $subject): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($subject['subject'] ?? ''); ?></td>
                                        <td><?php echo formatIndianCurrency($subject['total']); ?></td>
                                        <td><?php echo formatIndianCurrency($subject['obtained']); ?></td>
                                        <td>
                                            <?php
                                            $s_pct = $subject['total'] > 0 ? ($subject['obtained'] / $subject['total'] * 100) : 0;
                                            $s_color = $s_pct >= 80 ? 'success' : ($s_pct >= 60 ? 'primary' : ($s_pct >= 40 ? 'warning' : 'danger'));
                                            ?>
                                            <span
                                                class="badge bg-<?php echo $s_color; ?>"><?php echo formatIndianCurrency($s_pct); ?>%</span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No subject-wise breakdown available.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Remarks -->
    <?php if ($mark['remarks']): ?>
        <div class="card mb-3 border-0 shadow-sm overflow-hidden">
            <div class="card-header bg-secondary text-white border-0 py-3">
                <h3 class="card-title mb-0 font-weight-bold"><i class="fas fa-comment me-2"></i> Remarks</h3>
            </div>
            <div class="card-body">
                <p><?php echo nl2br(htmlspecialchars($mark['remarks'] ?? '')); ?></p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Action Buttons -->
    <div class="card border-0 shadow-sm overflow-hidden">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <a href="edit.php?id=<?php echo $mark['id']; ?>" class="btn btn-warning px-4 py-2 me-2">
                        <i class="fas fa-edit me-2"></i> Edit
                    </a>
                    <a href="../counselling/sessions/add.php?student_id=<?php echo $mark['student_id']; ?>&test_id=<?php echo $mark['id']; ?>"
                        class="btn btn-success-custom px-4 py-2">
                        <i class="fas fa-comments me-2"></i> Create Counselling Session
                    </a>
                </div>
                <div>
                    <a href="index.php" class="btn btn-secondary px-4 py-2 me-2">
                        <i class="fas fa-arrow-left me-2"></i> Back to List
                    </a>
                    <button type="button" onclick="window.print();" class="btn btn-info px-4 py-2 text-white">
                        <i class="fas fa-print me-2"></i> Print
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../include/footer.php'; ?>

