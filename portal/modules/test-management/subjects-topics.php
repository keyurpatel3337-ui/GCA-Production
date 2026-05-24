<?php

require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/format_helper.php';
require_once __DIR__ . '/../../common/api_client.php';
require_once PAGINATION_FILE;

// Get subject ID from query string or session
$subject_id = $_GET['subject_id'] ?? ($_SESSION['subjects_topics_pagination']['subject_id'] ?? 0);

// Handle POST pagination
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update pagination settings
    if (isset($_POST['page']) || isset($_POST['per_page'])) {
        $page = $_POST['page'] ?? 1;
        $perPage = $_POST['per_page'] ?? ($_SESSION['subjects_topics_pagination']['per_page'] ?? 25);

        // Update subject_id if present in POST, else keep existing
        $subject_id = $_POST['subject_id'] ?? $subject_id;

        $_SESSION['subjects_topics_pagination'] = [
            'page' => $page,
            'per_page' => $perPage,
            'subject_id' => $subject_id
        ];

        // Redirect to self with subject_id to maintain context
        header('Location: ' . $_SERVER['PHP_SELF'] . '?subject_id=' . $subject_id);
        exit;
    }
}

// Get pagination from session
$paginationParams = $_SESSION['subjects_topics_pagination'] ?? [
    'page' => 1,
    'per_page' => 25,
    'subject_id' => $subject_id
];

// Ensure we are viewing the correct subject if moved from another page
if ($subject_id && $subject_id != ($paginationParams['subject_id'] ?? 0)) {
    $paginationParams['subject_id'] = $subject_id;
    $paginationParams['page'] = 1; // Reset page when switching subjects
    $_SESSION['subjects_topics_pagination'] = $paginationParams;
}

$page = $paginationParams['page'];
$perPage = $paginationParams['per_page'];

$requestParams = [
    'subject_id' => $subject_id,
    'page' => $page,
    'per_page' => $perPage
];

// Load subject and topics via API with pagination
$api = new APIClient();
$response = $api->get('test-management/subjects-topics', $requestParams);

// Extract data from API response
if ($response && isset($response['success']) && $response['success']) {
    $data = $response['data'] ?? [];
    $subject = $data['subject'] ?? null;
    $topics = $data['topics'] ?? [];
    $pagination = $data['pagination'] ?? [];
    $page = $pagination['current_page'] ?? $page;
    $perPage = $pagination['per_page'] ?? $perPage;
    $totalRecords = $pagination['total_records'] ?? count($topics);
    $totalPages = $pagination['total_pages'] ?? 1;
} else {
    $subject = null;
    $topics = [];
    $page = 1;
    $perPage = 25;
    $totalRecords = 0;
    $totalPages = 1;
}

// Redirect if subject not found
if (!$subject && $subject_id) {
    set_flash_message('error', 'Subject not found');
    header('Location: subjects.php');
    exit;
}

$page_title = "Dashboard";
include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>



    <div class="container-fluid">
        <?php if (isset($_SESSION['success_msg'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                <i class="fas fa-check-circle"></i> <?php echo gca_safe_html($_SESSION['success_msg']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_msg'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                <i class="fas fa-exclamation-triangle"></i> <?php echo gca_safe_html($_SESSION['error_msg']);
                ?>
            </div>
        <?php endif; ?>

        <!-- Subject Info Card -->
        <?php if ($subject): ?>
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h4><i class="fas fa-book"></i> <?php echo htmlspecialchars($subject['subject_name'] ?? ''); ?></h4>
                            <p class="mb-0">
                                <strong>Code:</strong>
                                <span
                                    class="badge bg-info"><?php echo htmlspecialchars($subject['subject_code'] ?? 'N/A'); ?></span>
                                <strong class="ms-3">Status:</strong>
                                <?php if (($subject['status'] ?? '') === 'active'): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Inactive</span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="col-md-6 text-end">
                            <a href="subjects.php" class="btn btn-secondary px-4 py-2 me-2">
                                <i class="fas fa-arrow-left me-2"></i> Back to Subjects
                            </a>
                            <button type="button" class="btn btn-success-custom px-4 py-2" data-bs-toggle="modal"
                                data-bs-target="#addTopicModal">
                                <i class="fas fa-plus me-2"></i> Add Topic
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Topics List -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Topic List (Total: <?php echo formatIndianCurrency($totalRecords, false); ?>, Showing:
                    <?php echo count($topics); ?>)
                </h3>
                <div class="d-flex align-items-center">
                    <div class="input-group me-3 css-subjects-topics-88ed85">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="fas fa-search text-muted"></i>
                        </span>
                        <input type="text" id="topicSearch" class="form-control border-start-0 ps-0" placeholder="Search topics...">
                    </div>
                    <div>
                        <form method="POST" class="d-inline-block">
                            <label class="me-2">Per Page:</label>
                            <input type="hidden" name="subject_id" value="<?php echo $subject_id; ?>">
                            <input type="hidden" name="page" value="1">
                            <select name="per_page" class="form-select form-select-sm d-inline-block css-subjects-topics-dc251b"
                                onchange="this.form.submit()">
                                <option value="10" <?php echo $perPage == 10 ? 'selected' : ''; ?>>10</option>
                                <option value="25" <?php echo $perPage == 25 ? 'selected' : ''; ?>>25</option>
                                <option value="50" <?php echo $perPage == 50 ? 'selected' : ''; ?>>50</option>
                                <option value="100" <?php echo $perPage == 100 ? 'selected' : ''; ?>>100</option>
                            </select>
                        </form>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <?php if (count($topics) > 0): ?>
                    <table class="table table-bordered table-striped" id="topicsTable">
                        <thead>
                            <tr>
                                <th width="5%">ID</th>
                                <th width="5%">Order</th>
                                <th width="30%">Topic Name</th>
                                <th width="15%">Topic Code</th>
                                <th width="10%">Questions</th>
                                <th width="10%">Weightage %</th>
                                <th width="10%">Status</th>
                                <th width="15%">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topics as $topic): ?>
                                <tr>
                                    <td><?php echo $topic['id']; ?></td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo $topic['display_order'] ?? 0; ?></span>
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($topic['topic_name'] ?? ''); ?></strong></td>
                                    <td>
                                        <span
                                            class="badge bg-info text-dark"><?php echo htmlspecialchars($topic['topic_code'] ?? 'N/A'); ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary"><?php echo $topic['question_count'] ?? 0; ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-warning text-dark"><?php echo $topic['weightage'] ?? 0; ?>%</span>
                                    </td>
                                    <td>
                                        <?php if (($topic['status'] ?? '') === 'active'): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <button class="btn btn-warning btn-sm edit-btn"
                                                data-id="<?php echo $topic['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($topic['topic_name'] ?? ''); ?>"
                                                data-code="<?php echo htmlspecialchars($topic['topic_code'] ?? ''); ?>"
                                                data-order="<?php echo $topic['display_order'] ?? 0; ?>"
                                                data-weightage="<?php echo $topic['weightage'] ?? 0; ?>"
                                                data-status="<?php echo $topic['status'] ?? 'inactive'; ?>" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button
                                                class="btn btn-sm <?php echo ($topic['status'] ?? '') === 'active' ? 'btn-secondary' : 'btn-success'; ?> toggle-btn"
                                                data-id="<?php echo $topic['id']; ?>"
                                                title="<?php echo ($topic['status'] ?? '') === 'active' ? 'Deactivate' : 'Activate'; ?>">
                                                <i class="fas <?php echo ($topic['status'] ?? '') === 'active' ? 'fa-ban' : 'fa-check'; ?>"></i>
                                            </button>
                                            <button class="btn btn-danger btn-sm delete-btn"
                                                data-id="<?php echo $topic['id']; ?>" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="mt-3">
                            <?php
                            // Pass extra POST params for the subject ID
                            echo renderPaginationPost($page, $totalPages, 2, $perPage, ['subject_id' => $subject_id]);
                            ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> No topics found for this subject. Click "Add Topic" to create
                        one.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add Topic Modal -->
<div class="modal fade" id="addTopicModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Topic</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addTopicForm">
                <input type="hidden" name="subject_id" value="<?php echo $subject_id; ?>">
                <div class="modal-body">
                    <div class="form-group mb-3">
                        <label>Topic Name <span class="text-danger">*</span></label>
                        <input type="text" name="topic_name" class="form-control" placeholder="e.g., Kinematics"
                            required>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label>Topic Code</label>
                                <input type="text" name="topic_code" class="form-control" placeholder="e.g., KIN">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label>Display Order</label>
                                <input type="number" name="display_order" class="form-control" value="0">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label>Weightage (%)</label>
                                <input type="number" name="weightage" class="form-control" value="0" min="0" max="100">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label>Status <span class="text-danger">*</span></label>
                                <select name="status" class="form-control" required>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-success-custom px-4">Save Topic</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Topic Modal -->
<div class="modal fade" id="editTopicModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Topic</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editTopicForm">
                <input type="hidden" name="id" id="edit_id">
                <input type="hidden" name="subject_id" value="<?php echo $subject_id; ?>">
                <div class="modal-body">
                    <div class="form-group mb-3">
                        <label>Topic Name <span class="text-danger">*</span></label>
                        <input type="text" name="topic_name" id="edit_topic_name" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label>Topic Code</label>
                                <input type="text" name="topic_code" id="edit_topic_code" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label>Display Order</label>
                                <input type="number" name="display_order" id="edit_display_order" class="form-control">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label>Weightage (%)</label>
                                <input type="number" name="weightage" id="edit_weightage" class="form-control" min="0"
                                    max="100">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label>Status <span class="text-danger">*</span></label>
                                <select name="status" id="edit_is_active" class="form-control" required>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-success-custom px-4">Update Topic</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    $(document).ready(function () {
        // Move modals to body
        $('#addTopicModal, #editTopicModal').appendTo("body");

        // Add Topic Form Handler
        $('#addTopicForm').on('submit', function (e) {
            e.preventDefault();
            $.api.post('test-management/topic-save', $(this).serialize())
                .then(response => {
                    if (response.success) {
                        $('#addTopicModal').modal('hide');
                        showToast('success', 'Success!', response.message);
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showToast('error', 'Error!', response.error || response.message);
                    }
                }).catch(error => showToast('error', 'Error!', error.message || 'Failed to save topic'));
        });

        // Edit button click handler
        $('.edit-btn').on('click', function () {
            $('#edit_id').val($(this).data('id'));
            $('#edit_topic_name').val($(this).data('name'));
            $('#edit_topic_code').val($(this).data('code'));
            $('#edit_display_order').val($(this).data('order'));
            $('#edit_weightage').val($(this).data('weightage'));
            $('#edit_is_active').val($(this).data('status'));
            $('#editTopicModal').modal('show');
        });

        // Edit Topic Form Handler
        $('#editTopicForm').on('submit', function (e) {
            e.preventDefault();
            $.api.post('test-management/topic-save', $(this).serialize())
                .then(response => {
                    if (response.success) {
                        $('#editTopicModal').modal('hide');
                        showToast('success', 'Success!', response.message);
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showToast('error', 'Error!', response.error || response.message);
                    }
                }).catch(error => showToast('error', 'Error!', error.message || 'Failed to update topic'));
        });

        // Toggle status handler
        $('.toggle-btn').on('click', function () {
            var id = $(this).data('id');
            $.api.post('test-management/topic-toggle-status', {
                id: id
            })
                .then(response => {
                    if (response.success) {
                        showToast('success', 'Success!', response.message);
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showToast('error', 'Error!', response.error || response.message);
                    }
                }).catch(error => showToast('error', 'Error!', error.message || 'Failed to toggle status'));
        });

        // Delete handler
        $('.delete-btn').on('click', function () {
            var id = $(this).data('id');
            showConfirm({
                title: 'Delete Topic?',
                message: 'This action cannot be undone!',
                confirmText: 'Yes, Delete',
                confirmButtonClass: 'btn-danger',
                onConfirm: function () {
                    $.api.post('test-management/topic-delete', {
                        id: id
                    })
                        .then(response => {
                            if (response.success) {
                                showToast('success', 'Deleted!', response.message);
                                setTimeout(() => location.reload(), 1500);
                            } else {
                                showToast('error', 'Error!', response.error || response.message);
                            }
                        }).catch(error => showToast('error', 'Error!', error.message || 'Failed to delete topic'));
                }
            });
        });

        // Live Search Filtering
        $('#topicSearch').on('keyup', function() {
            const value = $(this).val().toLowerCase();
            $("#topicsTable tbody tr").filter(function() {
                const text = $(this).text().toLowerCase();
                $(this).toggle(text.indexOf(value) > -1);
            });
        });
    });
</script>

<?php include '../../include/footer.php'; ?>