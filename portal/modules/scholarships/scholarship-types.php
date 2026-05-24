<?php

require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once __DIR__ . '/../../common/api_client.php';
require_once PAGINATION_FILE;

// Handle POST filters and store in session
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['clear_filters'])) {
        unset($_SESSION['scholarship_types_filters']);
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    // Check if this is a pagination request (only page/per_page submitted)
    $isPaginationRequest = (isset($_POST['page']) || isset($_POST['per_page'])) &&
        !isset($_POST['search']) && !isset($_POST['type_name']);

    if ($isPaginationRequest) {
        // Only update page and per_page, preserve other filters
        if (!isset($_SESSION['scholarship_types_filters'])) {
            $_SESSION['scholarship_types_filters'] = [];
        }
        if (isset($_POST['page'])) {
            $_SESSION['scholarship_types_filters']['page'] = (int) $_POST['page'];
        }
        if (isset($_POST['per_page'])) {
            $_SESSION['scholarship_types_filters']['per_page'] = (int) $_POST['per_page'];
        }
    } else {
        $_SESSION['scholarship_types_filters'] = [
            'search' => $_POST['search'] ?? '',
            'per_page' => $_POST['per_page'] ?? 25,
            'page' => $_POST['page'] ?? 1
        ];
    }

    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Get filters from session
$requestParams = $_SESSION['scholarship_types_filters'] ?? [];
if (!isset($requestParams['per_page'])) {
    $requestParams['per_page'] = 25;
}

// Load scholarship types via API with pagination
$api = new APIClient();
$response = $api->get('scholarships/types', $requestParams);

// Extract data from API response
if ($response && isset($response['success']) && $response['success']) {
    $data = $response['data'] ?? [];
    $scholarship_types = $data['types'] ?? [];
    $pagination = $data['pagination'] ?? [];
    $page = $pagination['current_page'] ?? 1;
    $perPage = $pagination['per_page'] ?? 25;
    $totalRecords = $pagination['total_records'] ?? count($scholarship_types);
    $totalPages = $pagination['total_pages'] ?? 1;
} else {
    $scholarship_types = [];
    $page = 1;
    $perPage = 25;
    $totalRecords = 0;
    $totalPages = 1;
}

$page_title = "Scholarship Types";
include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>

<!-- Include SheetJS for modern Excel exports -->
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script src="../../assets/js/table-utilities.js"></script>




<div class="container-fluid">


    <?php if (isset($_SESSION['success_msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_SESSION['success_msg'] ?? '');
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_msg'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($_SESSION['error_msg'] ?? '');
            ?>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Scholarship Type Management</h3>
                </div>
                <div class="card-body">
                    <table class="table table-bordered table-striped" id="scholarshipTypesTable">
                        <thead>
                            <tr>
                                <th width="3%">
                                    <input type="checkbox" id="selectAll" class="form-check-input">
                                </th>
                                <th>ID</th>
                                <th>Type Name</th>
                                <th>Type Code</th>
                                <th>Description</th>
                                <th>Status</th>
                                <th>Created By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($scholarship_types as $type): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="row-checkbox form-check-input"
                                            value="<?php echo $type['id']; ?>">
                                    </td>
                                    <td><?php echo $type['id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($type['type_name'] ?? ''); ?></strong></td>
                                    <td><span
                                            class="badge bg-info text-dark"><?php echo htmlspecialchars($type['type_code'] ?? ''); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($type['description'] ?? '-'); ?></td>
                                    <td>
                                        <?php if ($type['is_active']): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($type['created_by_name'] ?? 'System'); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-info"
                                            onclick="editType(<?php echo htmlspecialchars(json_encode($type) ?? ''); ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="POST" action="scholarship-type-toggle.php"
                                            style="display:inline;margin:0;">
                                            <input type="hidden" name="id" value="<?php echo $type['id']; ?>">
                                            <button type="submit"
                                                class="btn btn-sm <?php echo $type['is_active'] ? 'btn-warning' : 'btn-success'; ?>">
                                                <i class="fas fa-<?php echo $type['is_active'] ? 'ban' : 'check'; ?>"></i>
                                            </button>
                                        </form>
                                        <button class="btn btn-sm btn-danger"
                                            onclick="hardDelete(<?php echo $type['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <?php if ($totalRecords > 0): ?>
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div class="text-muted">
                                <?php echo getPaginationInfo($page, $perPage, $totalRecords); ?>
                            </div>
                            <?php if ($totalPages > 1): ?>
                                <?php echo renderPaginationPost($page, $totalPages, 2, $perPage); ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<!-- Add Type Modal -->
<div class="modal fade" id="addTypeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Scholarship Type</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addTypeForm" method="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Type Name <span class="text-danger">*</span></label>
                        <input type="text" name="type_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Type Code <span class="text-danger">*</span></label>
                        <input type="text" name="type_code" class="form-control" required
                            placeholder="e.g., GMSAT, BOARD">
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="is_active_add" name="is_active"
                                value="1" checked>
                            <label class="custom-control-label" for="is_active_add">Active</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Type</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Type Modal -->
<div class="modal fade" id="editTypeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Scholarship Type</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editTypeForm" method="POST">
                <input type="hidden" name="type_id" id="edit_type_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Type Name <span class="text-danger">*</span></label>
                        <input type="text" name="type_name" id="edit_type_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Type Code <span class="text-danger">*</span></label>
                        <input type="text" name="type_code" id="edit_type_code" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="edit_is_active" name="is_active"
                                value="1">
                            <label class="custom-control-label" for="edit_is_active">Active</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Update Type</button>
                </div>
            </form>
        </div>
    </div>

    <?php include '../../include/footer.php'; ?>

    <script>
        $('#selectAll').on('change', function () {
            $('.row-checkbox').prop('checked', $(this).prop('checked'));
            toggleDeleteButton();
        });
        $(document).on('change', '.row-checkbox', function () {
            $('#selectAll').prop('checked', $('.row-checkbox:checked').length === $('.row-checkbox').length);
            toggleDeleteButton();
        });

        function toggleDeleteButton() {
            $('#deleteSelectedBtn').toggle($('.row-checkbox:checked').length > 0);
        }

        function exportToExcel() {
            TableUtils.exportToExcel('scholarshipTypesTable', 'scholarship_types_export');
        }

        function deleteSelected() {
            let selectedIds = [];
            $('.row-checkbox:checked').each(function () {
                selectedIds.push($(this).val());
            });
            if (selectedIds.length === 0) {
                showToast('warning', 'Warning', 'Please select at least one scholarship type!');
                return;
            }
            showConfirm({
                title: 'Are you sure?',
                message: `You are about to delete ${selectedIds.length} scholarship type(s)!`,
                confirmText: 'Yes, delete them!',
                confirmButtonClass: 'btn-danger',
                onConfirm: function () {
                    $.api.post('scholarships/types-delete-multiple', {
                        ids: selectedIds
                    }).then(response => {
                        if (response.success) {
                            showToast('success', 'Deleted!', response.message);
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            showToast('error', 'Error!', response.message || 'Failed to delete.');
                        }
                    }).catch(error => {
                        console.error('API Error:', error);
                        showToast('error', 'Error', error.message || 'An error occurred.');
                    });
                }
            });
        }

        function hardDelete(id) {
            showConfirm({
                title: 'Permanently Delete?',
                message: "This will permanently delete the scholarship type from the database. This action cannot be undone!",
                confirmText: 'Yes, delete permanently!',
                confirmButtonClass: 'btn-danger',
                onConfirm: function () {
                    $.api.post('scholarships/type-hard-delete', {
                        id: id
                    }).then(response => {
                        if (response.success) {
                            showToast('success', 'Deleted!', response.message || 'Type permanently deleted.');
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            showToast('error', 'Error!', response.message || 'Failed to delete type.');
                        }
                    }).catch(error => {
                        console.error('API Error:', error);
                        showToast('error', 'Error', error.message || 'An error occurred.');
                    });
                }
            });
        }

        function editType(type) {
            $('#edit_type_id').val(type.id);
            $('#edit_type_name').val(type.type_name);
            $('#edit_type_code').val(type.type_code);
            $('#edit_description').val(type.description || '');
            $('#edit_is_active').prop('checked', type.is_active == 1);
            $('#editTypeModal').modal('show');
        }

        // Add Type Form Submission
        $('#addTypeForm').on('submit', function (e) {
            e.preventDefault();
            const formData = {};
            $(this).serializeArray().forEach(item => {
                formData[item.name] = item.value;
            });
            formData.is_active = $('#is_active_add').is(':checked') ? '1' : '0';

            $.api.post('scholarships/type-save', formData).then(response => {
                if (response.success) {
                    showToast('success', 'Success', response.message || 'Type saved successfully.');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast('error', 'Error', response.error || response.message || 'Failed to save type.');
                }
            }).catch(error => {
                showToast('error', 'Error', error.message || 'An error occurred.');
            });
        });

        // Edit Type Form Submission
        $('#editTypeForm').on('submit', function (e) {
            e.preventDefault();
            const formData = {};
            $(this).serializeArray().forEach(item => {
                formData[item.name] = item.value;
            });
            formData.is_active = $('#edit_is_active').is(':checked') ? '1' : '0';

            $.api.post('scholarships/type-save', formData).then(response => {
                if (response.success) {
                    showToast('success', 'Success', response.message || 'Type updated successfully.');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast('error', 'Error', response.error || response.message || 'Failed to update type.');
                }
            }).catch(error => {
                showToast('error', 'Error', error.message || 'An error occurred.');
            });
        });
    </script>