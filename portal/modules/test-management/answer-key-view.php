<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;

// Check if user is Super Admin or Principle
if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_COUNSELLOR])) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$answer_key_id = $_POST['id'] ?? 0;

// Get answer key details
try {
    $op = new Operation();

    $answer_key = $op->readWithJoin(
        'tbl_answer_keys ak',
        ['ak.*', 'ps.paper_set_name', 'ps.paper_code', 'u.name as uploader_name'],
        [
            ['type' => 'INNER', 'table' => 'tbl_paper_sets ps', 'on' => 'ak.paper_set_id = ps.id'],
            ['type' => 'LEFT', 'table' => 'tbl_users u', 'on' => 'ak.uploaded_by = u.id']
        ],
        ['ak.id' => $answer_key_id]
    );

    if (!$answer_key) {
        set_flash_message('error', 'Answer key not found!');
        header('Location: answer-keys.php');
        exit;
    }

    // Parse answers JSON
    $answers = json_decode($answer_key['answers_json'], true);
    if (!is_array($answers)) {
        $answers = [];
    }
} catch (Exception $e) {
    logDatabaseError($e, "Fetch Answer Key Details");
    set_flash_message('error', 'Error fetching answer key details');
    header('Location: answer-keys.php');
    exit;
}

$page_title = "View Answer Key" ;
$page_breadcrumb = "Key -";
?>
<?php include '../../include/header.php'; ?>
<?php include '../../include/navbar.php'; ?>
<?php include '../../include/sidebar.php'; ?>




    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <!-- Answer Key Information -->
                <div class="card">
                    <div class="card-header bg-info">
                        <h3 class="card-title"><i class="fas fa-info-circle"></i> Answer Key Information</h3>
                        <div class="card-tools">
                            <a href="answer-keys.php" class="btn btn-sm btn-light">
                                <i class="fas fa-arrow-left"></i> Back to List
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <th width="200">Test Name:</th>
                                        <td><strong><?php echo htmlspecialchars($answer_key['test_name'] ?? ''); ?></strong>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Paper Set:</th>
                                        <td>
                                            <span class="badge bg-info text-dark">
                                                <?php echo htmlspecialchars($answer_key['paper_set_name'] ?? ''); ?>
                                                (<?php echo htmlspecialchars($answer_key['paper_code'] ?? ''); ?>)
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Test Date:</th>
                                        <td><?php echo $answer_key['test_date'] ? date('d M Y', strtotime($answer_key['test_date'])) : 'N/A'; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Total Questions:</th>
                                        <td><span
                                                class="badge bg-primary"><?php echo $answer_key['total_questions']; ?></span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <th width="200">Uploaded By:</th>
                                        <td><?php echo htmlspecialchars($answer_key['uploader_name'] ?? 'N/A'); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Upload Date:</th>
                                        <td><?php echo date('d M Y, h:i A', strtotime($answer_key['created_at'])); ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Status:</th>
                                        <td>
                                            <?php if ($answer_key['status'] == 'active'): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php if (isset($answer_key['omr_file']) && $answer_key['omr_file']): ?>
                                        <tr>
                                            <th>OMR File:</th>
                                            <td>
                                                <a href="../../uploads/answer_keys/<?php echo htmlspecialchars($answer_key['omr_file'] ?? ''); ?>"
                                                    class="btn btn-sm btn-primary" target="_blank">
                                                    <i class="fas fa-download"></i> Download
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Answer Key Details -->
                <div class="card">
                    <div class="card-header bg-success">
                        <h3 class="card-title"><i class="fas fa-list-ol"></i> Answer Key Details
                            (<?php echo count($answers); ?> Questions)</h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-sm btn-light" onclick="window.print()">
                                <i class="fas fa-print"></i> Print
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($answers)): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i> No answers found in this answer key.
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php
                                $chunks = array_chunk($answers, 25); // Show 25 questions per column
                                foreach ($chunks as $chunk):
                                    ?>
                                    <div class="col-md-3">
                                        <table class="table table-sm table-bordered table-striped">
                                            <thead class="table-dark">
                                                <tr>
                                                    <th width="60">Q. No.</th>
                                                    <th class="text-center">Answer</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($chunk as $answer): ?>
                                                    <tr>
                                                        <td><strong><?php echo $answer['q']; ?></strong></td>
                                                        <td class="text-center">
                                                            <span class="badge bg-success badge-lg">
                                                                <?php echo strtoupper($answer['ans']); ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Summary Statistics -->
                            <div class="row mt-4">
                                <div class="col-md-12">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h5>Answer Distribution:</h5>
                                            <?php
                                            $answer_counts = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0];
                                            foreach ($answers as $answer) {
                                                $ans = strtoupper($answer['ans']);
                                                if (isset($answer_counts[$ans])) {
                                                    $answer_counts[$ans]++;
                                                }
                                            }
                                            ?>
                                            <div class="row text-center">
                                                <div class="col-md-3">
                                                    <div class="info-box bg-success">
                                                        <span class="info-box-icon"><i class="fas fa-circle"></i></span>
                                                        <div class="info-box-content">
                                                            <span class="info-box-text">Option A</span>
                                                            <span class="info-box-number"><?php echo $answer_counts['A']; ?>
                                                                questions</span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="info-box bg-info">
                                                        <span class="info-box-icon"><i class="fas fa-circle"></i></span>
                                                        <div class="info-box-content">
                                                            <span class="info-box-text">Option B</span>
                                                            <span class="info-box-number"><?php echo $answer_counts['B']; ?>
                                                                questions</span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="info-box bg-warning">
                                                        <span class="info-box-icon"><i class="fas fa-circle"></i></span>
                                                        <div class="info-box-content">
                                                            <span class="info-box-text">Option C</span>
                                                            <span class="info-box-number"><?php echo $answer_counts['C']; ?>
                                                                questions</span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="info-box bg-danger">
                                                        <span class="info-box-icon"><i class="fas fa-circle"></i></span>
                                                        <div class="info-box-content">
                                                            <span class="info-box-text">Option D</span>
                                                            <span class="info-box-number"><?php echo $answer_counts['D']; ?>
                                                                questions</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer">
                        <a href="answer-keys.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to List
                        </a>
                        <a href="answer-key-edit.php?id=<?php echo $answer_key_id; ?>" class="btn btn-warning">
                            <i class="fas fa-edit"></i> Edit Answer Key
                        </a>
                    </div>
                </div>
            </div>
        </div>
        </div>

<?php include '../../include/footer.php'; ?>

<style media="print">
    .content-wrapper {
        margin: 0 !important;
        padding: 0 !important;
    }

    .card-tools,
    .card-footer,
    .breadcrumb,
    .btn {
        display: none !important;
    }
    }
</style>
