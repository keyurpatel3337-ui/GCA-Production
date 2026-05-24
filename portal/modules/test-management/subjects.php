<?php

require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once __DIR__ . '/../../common/api_client.php';
require_once PAGINATION_FILE;

// Load subjects via API with pagination
$api = new APIClient();

// Handle POST pagination/filters
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['page'])) {
    $page = $_POST['page'] ?? 1;
    $perPage = $_POST['per_page'] ?? ($_SESSION['subjects_pagination']['per_page'] ?? 25);

    $_SESSION['subjects_pagination'] = [
        'page' => $page,
        'per_page' => $perPage
    ];

    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Get pagination from session
$paginationParams = $_SESSION['subjects_pagination'] ?? [
    'page' => 1,
    'per_page' => 25
];

$page = $paginationParams['page'];
$perPage = $paginationParams['per_page'];

$requestParams = [
    'page' => $page,
    'per_page' => $perPage
];

$response = $api->get('test-management/subjects', $requestParams);

// Extract data from API response
if ($response && isset($response['success']) && $response['success']) {
    $data = $response['data'] ?? [];
    $subjects = $data['subjects'] ?? [];
    $pagination = $data['pagination'] ?? [];
    $page = $pagination['current_page'] ?? $page;
    $perPage = $pagination['per_page'] ?? $perPage;
    $totalRecords = $pagination['total_records'] ?? count($subjects);
    $totalPages = $pagination['total_pages'] ?? 1;
} else {
    $subjects = [];
    $page = 1;
    $perPage = 25;
    $totalRecords = 0;
    $totalPages = 1;
    // Set error message in session or variable if needed, though safe_html below handles session msg
}

$page_title = 'Subjects Management';
include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>

<style>
    .btn-success-custom {
        background: linear-gradient(135deg, #059669 0%, #10b981 100%);
        color: white;
        border: none;
        border-radius: 8px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        transition: all 0.2s ease;
    }

    .btn-success-custom:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 8px -1px rgba(16, 185, 129, 0.2);
        color: white;
        background: linear-gradient(135deg, #047857 0%, #059669 100%);
    }
</style>



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
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center bg-white py-3">
            <h5 class="mb-0 fw-bold text-primary"><i class="fas fa-book me-2"></i>Subjects Management</h5>
            <div class="d-flex align-items-center">
                <div class="me-3" style="width: 250px;">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="fas fa-search text-muted"></i>
                        </span>
                        <input type="text" id="liveSearch" class="form-control border-start-0 ps-0"
                            placeholder="Live search subjects...">
                    </div>
                </div>
                <button type="button" class="btn btn-success-custom btn-sm fw-bold px-3" data-bs-toggle="modal"
                    data-bs-target="#addSubjectModal">
                    <i class="fas fa-plus me-1"></i> Add New Subject
                </button>
            </div>
        </div>

        <div class="card-body">
            <table class="table table-bordered table-striped" id="subjectsTable">
                <thead>
                    <tr>
                        <th width="5%">ID</th>
                        <th width="25%">Subject Name</th>
                        <th width="15%">Subject Code</th>
                        <th width="15%">Total Topics</th>
                        <th width="20%">Status</th>
                        <th width="10%">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subjects as $subject): ?>
                        <tr>
                            <td><?php echo $subject['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($subject['subject_name'] ?? ''); ?></strong></td>
                            <td><span
                                    class="badge bg-info text-dark"><?php echo htmlspecialchars($subject['subject_code'] ?? 'N/A'); ?></span>
                            </td>
                            <td>
                                <span class="badge bg-primary"><?php echo $subject['topic_count'] ?? 0; ?></span>
                                <a href="subjects-topics.php?subject_id=<?php echo $subject['id']; ?>"
                                    class="btn btn-sm btn-outline-primary ms-2">
                                    <i class="fas fa-list"></i> View Topics
                                </a>
                            </td>
                            <td>
                                <?php if (($subject['status'] ?? '') === 'active'): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <button class="btn btn-warning btn-sm edit-btn" data-id="<?php echo $subject['id']; ?>"
                                        data-name="<?php echo htmlspecialchars($subject['subject_name'] ?? ''); ?>"
                                        data-code="<?php echo htmlspecialchars($subject['subject_code'] ?? ''); ?>"
                                        data-status="<?php echo $subject['status'] ?? 'inactive'; ?>" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button
                                        class="btn btn-sm <?php echo ($subject['status'] ?? '') === 'active' ? 'btn-secondary' : 'btn-success'; ?> toggle-btn"
                                        data-id="<?php echo $subject['id']; ?>"
                                        title="<?php echo ($subject['status'] ?? '') === 'active' ? 'Deactivate' : 'Activate'; ?>">
                                        <i
                                            class="fas <?php echo ($subject['status'] ?? '') === 'active' ? 'fa-ban' : 'fa-check'; ?>"></i>
                                    </button>
                                    <button class="btn btn-danger btn-sm delete-btn" data-id="<?php echo $subject['id']; ?>"
                                        title="Delete">
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
                    echo renderPaginationPost($page, $totalPages);
                    ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Subject Modal -->
<div class="modal fade" id="addSubjectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success-custom text-white">
                <h5 class="modal-title font-weight-bold">Add New Subject</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addSubjectForm">
                <div class="modal-body">
                    <div class="form-group mb-3">
                        <label>Subject Name <span class="text-danger">*</span></label>
                        <input type="text" name="subject_name" class="form-control" placeholder="e.g., Physics"
                            required>
                    </div>
                    <div class="form-group mb-3">
                        <label>Subject Code</label>
                        <input type="text" name="subject_code" class="form-control" placeholder="e.g., PHY">
                    </div>
                    <div class="form-group">
                        <label>Status <span class="text-danger">*</span></label>
                        <select name="status" class="form-control" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-success-custom px-4">Save Subject</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Subject Modal -->
<div class="modal fade" id="editSubjectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success-custom text-white">
                <h5 class="modal-title font-weight-bold">Edit Subject</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="editSubjectForm">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body">
                    <div class="form-group mb-3">
                        <label>Subject Name <span class="text-danger">*</span></label>
                        <input type="text" name="subject_name" id="edit_subject_name" class="form-control" required>
                    </div>
                    <div class="form-group mb-3">
                        <label>Subject Code</label>
                        <input type="text" name="subject_code" id="edit_subject_code" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Status <span class="text-danger">*</span></label>
                        <select name="status" id="edit_status" class="form-control" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-success-custom px-4">Update Subject</button>
                </div>
            </form>
        </div>
    </div>
</div>

    <script>
        $(document).ready(function () {
            // Move modals to body
            $('#addSubjectModal, #editSubjectModal').appendTo("body");

            // Add Subject Form Handler
            $('#addSubjectForm').on('submit', function (e) {
                e.preventDefault();
                $.api.post('test-management/subject-save', $(this).serialize())
                    .then(response => {
                        if (response.success) {
                            $('#addSubjectModal').modal('hide');
                            showToast('success', 'Success!', response.message);
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            showToast('error', 'Error!', response.error || response.message);
                        }
                    }).catch(error => showToast('error', 'Error!', error.message || 'Failed to save subject'));
            });

            // Edit button click handler
            $('.edit-btn').on('click', function () {
                $('#edit_id').val($(this).data('id'));
                $('#edit_subject_name').val($(this).data('name'));
                $('#edit_subject_code').val($(this).data('code'));
                $('#edit_status').val($(this).data('status'));
                $('#editSubjectModal').modal('show');
            });

            // Edit Subject Form Handler
            $('#editSubjectForm').on('submit', function (e) {
                e.preventDefault();
                $.api.post('test-management/subject-save', $(this).serialize())
                    .then(response => {
                        if (response.success) {
                            $('#editSubjectModal').modal('hide');
                            showToast('success', 'Success!', response.message);
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            showToast('error', 'Error!', response.error || response.message);
                        }
                    }).catch(error => showToast('error', 'Error!', error.message || 'Failed to update subject'));
            });

            // Toggle status handler
            $('.toggle-btn').on('click', function () {
                var id = $(this).data('id');
                $.api.post('test-management/subject-toggle-status', { id: id })
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
                    title: 'Delete Subject?',
                    message: 'This will also delete all associated topics. This action cannot be undone!',
                    confirmText: 'Yes, Delete',
                    confirmButtonClass: 'btn-danger',
                    onConfirm: function () {
                        $.api.post('test-management/subject-delete', { id: id })
                            .then(response => {
                                if (response.success) {
                                    showToast('success', 'Deleted!', response.message);
                                    setTimeout(() => location.reload(), 1500);
                                } else {
                                    showToast('error', 'Error!', response.error || response.message);
                                }
                            }).catch(error => showToast('error', 'Error!', error.message || 'Failed to delete subject'));
                    }
                });
            });

            // Live Search Filtering
            $('#liveSearch').on('keyup', function () {
                const value = $(this).val().toLowerCase();
                $("#subjectsTable tbody tr").filter(function () {
                    const text = $(this).text().toLowerCase();
                    $(this).toggle(text.indexOf(value) > -1);
                });
            });
        });
    </script>

    <?php include '../../include/footer.php'; ?>