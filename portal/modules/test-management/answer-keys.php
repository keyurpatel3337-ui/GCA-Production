<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;

// Check if user is Super Admin or Principle
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "Manage Answer Keys";
$page_breadcrumb = "Answer Keys";

// Get all answer keys
try {
    $op = new Operation();

    $answer_keys = $op->readWithJoin(
        'tbl_answer_keys ak',
        ['ak.*', 'ps.paper_set_name', 'u.name as uploader_name'],
        [
            ['type' => 'INNER', 'table' => 'tbl_paper_sets ps', 'on' => 'ak.paper_set_id = ps.id'],
            ['type' => 'LEFT', 'table' => 'tbl_users u', 'on' => 'ak.uploaded_by = u.id']
        ],
        [],
        'ak.id ASC',
    );

    // Get all paper sets for dropdown
    $paper_sets = $op->readAll('tbl_paper_sets', ['status' => 'active'], 'paper_set_name ASC');
} catch (Exception $e) {
    logDatabaseError($e, "Fetch Answer Keys and Paper Sets for Admin");
    $answer_keys = [];
    $paper_sets = [];
}
?>
<?php include '../../include/header.php'; ?>
<?php include '../../include/navbar.php'; ?>
<?php include '../../include/sidebar.php'; ?>




<div class="container-fluid">


    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="card-title">Answer Key Management</h3>
                    </div>
                    <div class="ms-auto">
                        <button type="button" class="btn btn-success-custom px-4 py-2 me-2" data-bs-toggle="modal"
                            data-bs-target="#addAnswerKeyModal">
                            <i class="fas fa-cloud-upload-alt me-2"></i> Add Answer Key
                        </button>
                        <button type="button" class="btn btn-success-custom px-4 py-2" data-bs-toggle="modal"
                            data-bs-target="#manualEntryModal">
                            <i class="fas fa-plus me-2"></i> Manual Entry
                        </button>
                    </div>
                </div>
                <div class="card-footer bg-light">
                    <div class="row align-items-center">
                        <div class="col-md-4">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0">
                                    <i class="fas fa-search text-muted"></i>
                                </span>
                                <input type="text" id="answerKeySearch" class="form-control border-start-0 ps-0"
                                    placeholder="Search answer keys...">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Test Name</th>
                                    <th>Paper Set</th>
                                    <th>Test Date</th>
                                    <th>Total Questions</th>
                                    <th>Uploaded By</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($answer_keys as $key): ?>
                                    <tr>
                                        <td><?php echo $key['id']; ?></td>
                                        <td><strong><?php echo htmlspecialchars($key['test_name'] ?? ''); ?></strong></td>
                                        <td><span
                                                class="badge bg-info text-white"><?php echo htmlspecialchars($key['paper_set_name'] ?? ''); ?></span>
                                        </td>
                                        <td><?php echo $key['test_date'] ? formatDate($key['test_date']) : '-'; ?></td>
                                        <td><?php echo $key['total_questions']; ?></td>
                                        <td><?php echo htmlspecialchars($key['uploader_name'] ?? 'N/A'); ?></td>
                                        <td>
                                            <?php if ($key['status'] == 'active'): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="answer-key-view.php?id=<?php echo $key['id']; ?>"
                                                    class="btn btn-info btn-sm" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="answer-key-edit.php?id=<?php echo $key['id']; ?>"
                                                    class="btn btn-warning btn-sm" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="answer-key-delete.php?id=<?php echo $key['id']; ?>"
                                                    class="btn btn-danger btn-sm btn-delete" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</div>


<!-- Manual Entry Modal -->
<div class="modal fade" id="manualEntryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success">
                <h5 class="modal-title text-white">Manual Answer Key Entry</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <form action="answer-key-manual-entry.php" method="GET">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> <strong>Manual Entry Mode:</strong> You will be able to enter
                        answers for each question individually after selecting a paper set.
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-file-alt"></i> Select Paper Set <span
                                class="text-danger">*</span></label>
                        <select name="paper_set_id" class="form-control" required>
                            <option value="">-- Choose Paper Set --</option>
                            <?php foreach ($paper_sets as $ps): ?>
                                <option value="<?php echo $ps['id']; ?>">
                                    <?php echo htmlspecialchars($ps['paper_set_name'] ?? '') . ' (' . $ps['paper_code'] . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">Select the paper set for which you want to enter answer
                            keys</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-keyboard"></i> Continue to Manual Entry
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Add Answer Key Modal -->
<div class="modal fade" id="addAnswerKeyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success-custom">
                <h5 class="modal-title font-weight-bold text-white"><i class="fas fa-upload"></i> Upload Answer Key</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <form id="uploadAnswerKeyForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> <strong>Upload Answer Key:</strong> Enter test details and
                        upload the answer key file or provide answers in JSON format.
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-pencil-alt"></i> Test Name <span
                                        class="text-danger">*</span></label>
                                <input type="text" name="test_name" class="form-control"
                                    placeholder="e.g., Final Exam 2024" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-file-alt"></i> Paper Set <span
                                        class="text-danger">*</span></label>
                                <select name="paper_set_id" class="form-control" required>
                                    <option value="">-- Select Paper Set --</option>
                                    <?php foreach ($paper_sets as $ps): ?>
                                        <option value="<?php echo $ps['id']; ?>">
                                            <?php echo htmlspecialchars($ps['paper_set_name'] ?? '') . ' (' . $ps['paper_code'] . ')'; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-calendar-alt"></i> Test Date</label>
                        <input type="date" name="test_date" class="form-control">
                        <small class="form-text text-muted">Optional: Select the date when this test was
                            conducted</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-upload"></i> Upload Answer Key File</label>
                        <input type="file" name="answer_key_file" class="form-control">
                        <small class="form-text text-muted"><i class="fas fa-info-circle"></i> Optional: Upload answer
                            key document (PDF, DOCX, or image)</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-code"></i> Answer Key (JSON Format)</label>
                        <textarea name="answers_json" class="form-control font-monospace" rows="5"
                            placeholder='[{"q":1,"ans":"A"},{"q":2,"ans":"B"},{"q":3,"ans":"C"}]'></textarea>
                        <small class="form-text text-muted">
                            <i class="fas fa-lightbulb"></i> <strong>Format:</strong>
                            <code>[{"q":question_number,"ans":"answer"}]</code>
                        </small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-toggle-on"></i> Status <span
                                class="text-danger">*</span></label>
                        <select name="status" class="form-control" required>
                            <option value="active" selected>Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-success-custom px-4">Save Answer Key</button>
                </div>
            </form>
        </div>
    </div>

    <?php include '../../include/footer.php'; ?>
    <script>
        $(document).ready(function () {
            // Move modals to body to prevent z-index issues
            $('#manualEntryModal').appendTo("body");
            $('#addAnswerKeyModal').appendTo("body");

            // Upload Answer Key Form Handler
            $('#uploadAnswerKeyForm').on('submit', function (e) {
                e.preventDefault();
                var formData = new FormData(this);

                $.ajax({
                    url: BACKEND_URL + '/index.php?route=test-management/answer-key-save',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    xhrFields: {
                        withCredentials: true
                    },
                    success: function (response) {
                        if (response.success) {
                            $('#addAnswerKeyModal').modal('hide');
                            showToast('success', 'Success!', response.message);
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            showToast('error', 'Error!', response.error || response.message);
                        }
                    },
                    error: function (xhr, status, error) {
                        showToast('error', 'Error!', 'Failed to upload answer key: ' + error);
                    }
                });
            });
        });
    </script>