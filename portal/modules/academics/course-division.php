<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once __DIR__ . '/../../common/api_client.php';
require_once PORTAL_GLOBALVARIABLE;

if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_ESTABLISHMENT, ROLE_RECEPTION])) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$apiClient = new APIClient();
$response = $apiClient->get('settings/course-divisions');

$mappings = $response['success'] ? $response['data']['mappings'] : [];
$courses = $response['success'] ? $response['data']['courses'] : [];
$groups = $response['success'] ? $response['data']['groups'] : [];
$divisions = $response['success'] ? $response['data']['divisions'] : [];

$page_title = 'Standard-Division Mapping';
include '../../include/header.php';
?>
<link rel="stylesheet" href="<?= PORTAL_URL ?>/assets/css/modules/academics/course-division.css">
<?php
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>



<div class="container-fluid py-4">
    <div class="card mb-4 shadow-sm overflow-hidden border-0">
        <div class="card-header bg-white py-3 px-4 border-bottom">
            <h5 class="card-title mb-0 fw-bold text-dark">
                <i class="fas fa-plus-circle me-2 text-primary"></i> Add New Mapping
            </h5>
        </div>
        <form id="addForm" method="POST">
            <div class="card-body">
                <div class="row align-items-end">
                    <div class="col-md-3">
                        <div class="mb-3">
                            <label class="form-label">Standard <span class="text-danger">*</span></label>
                            <select name="course_id" class="form-select" required>
                                <option value="">-- Select Standard --</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?= $course['id'] ?>"><?= htmlspecialchars($course['course_name'] ?? '') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="mb-3">
                            <label class="form-label">Group <span class="text-danger">*</span></label>
                            <select name="group_id" class="form-select" required>
                                <option value="">-- Select Group --</option>
                                <?php foreach ($groups as $group): ?>
                                    <option value="<?= $group['id'] ?>"><?= htmlspecialchars($group['group_name'] ?? '') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="mb-3">
                            <label class="form-label">Division <span class="text-danger">*</span></label>
                            <select name="division_id" class="form-select" required>
                                <option value="">-- Select Division --</option>
                                <?php foreach ($divisions as $division): ?>
                                    <option value="<?= $division['id'] ?>">
                                        <?= htmlspecialchars($division['division_name'] ?? '') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="mb-3">
                            <label class="form-label">Start Roll No <span class="text-danger">*</span></label>
                            <input type="number" name="start_roll_no" class="form-control" value="1" min="1" required>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="mb-3">
                            <label class="form-label">Max Capacity</label>
                            <input type="number" name="max_capacity" class="form-control" placeholder="Optional"
                                min="1">
                        </div>
                    </div>
                    <div class="col-md-1">
                        <div class="mb-3">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-plus"></i> Add
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="card shadow-sm overflow-hidden border-0 mt-4">
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
            <h5 class="card-title mb-0 fw-bold text-dark">
                <i class="fas fa-list me-2 text-primary"></i> Mapping List
            </h5>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3"
                    onclick="exportToExcel()">
                    <i class="fas fa-file-excel me-1"></i> Export
                </button>
                <div id="deleteSelectedBtn">
                    <button class="btn btn-danger btn-sm rounded-pill px-3" onclick="deleteSelected()">
                        <i class="fas fa-trash-alt me-1"></i> Delete Selected
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="mappingTable">
                    <thead class="table-light">
                        <tr>
                            <th width="3%">
                                <input type="checkbox" id="selectAll" class="form-check-input">
                            </th>
                            <th width="5%">#</th>
                            <th width="20%">Standard</th>
                            <th width="15%">Group</th>
                            <th width="10%">Division</th>
                            <th width="10%">Roll Range</th>
                            <th width="10%">Students</th>
                            <th width="10%">Capacity</th>
                            <th width="7%">Status</th>
                            <th width="10%">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mappings as $index => $mapping): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" class="row-checkbox form-check-input"
                                        value="<?= $mapping['id'] ?>">
                                </td>
                                <td><?= $index + 1 ?></td>
                                <td><?= htmlspecialchars($mapping['course_name'] ?? '') ?></td>
                                <td><?= htmlspecialchars($mapping['group_name'] ?? '') ?></td>
                                <td><strong><?= htmlspecialchars($mapping['division_name'] ?? '') ?></strong></td>
                                <td><?= $mapping['start_roll_no'] ?> - <?= $mapping['current_roll_no'] ?></td>
                                <td><span class="badge bg-primary"><?= $mapping['total_students'] ?></span></td>
                                <td>
                                    <?php if ($mapping['max_capacity']): ?>
                                        <?= $mapping['max_capacity'] ?>
                                        <?php if ($mapping['total_students'] >= $mapping['max_capacity']): ?>
                                            <span class="badge bg-danger ms-1">Full</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($mapping['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <button class="btn btn-sm btn-outline-primary"
                                            onclick="editItem(<?= htmlspecialchars(json_encode($mapping) ?? '') ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger"
                                            onclick="deleteItem(<?= $mapping['id'] ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
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

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Mapping</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editForm">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Standard <span class="text-danger">*</span></label>
                        <select name="course_id" id="edit_course_id" class="form-select" required>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?= $course['id'] ?>"><?= htmlspecialchars($course['course_name'] ?? '') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Group <span class="text-danger">*</span></label>
                        <select name="group_id" id="edit_group_id" class="form-select" required>
                            <?php foreach ($groups as $group): ?>
                                <option value="<?= $group['id'] ?>"><?= htmlspecialchars($group['group_name'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Division <span class="text-danger">*</span></label>
                        <select name="division_id" id="edit_division_id" class="form-select" required>
                            <?php foreach ($divisions as $division): ?>
                                <option value="<?= $division['id'] ?>"><?= htmlspecialchars($division['division_name'] ?? '') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Start Roll No <span class="text-danger">*</span></label>
                                <input type="number" name="start_roll_no" id="edit_start_roll_no" class="form-control"
                                    min="1" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Max Capacity</label>
                                <input type="number" name="max_capacity" id="edit_max_capacity" class="form-control"
                                    min="1">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="edit_active">
                            <label class="form-check-label" for="edit_active">Active Status</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-warning">Update Mapping</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Include SheetJS for modern Excel exports -->
    <script src="<?php echo BASE_URL; ?>/assets/vendor/xlsx/xlsx.full.min.js"></script>
    <script src="../../assets/js/table-utilities.js"></script>
    <?php include '../../include/footer.php'; ?>

    <script>
        // Select All Checkbox
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
            TableUtils.exportToExcel('mappingTable', 'course_division_mappings_export');
        }

        function deleteSelected() {
            let selectedIds = [];
            $('.row-checkbox:checked').each(function () {
                selectedIds.push($(this).val());
            });
            if (selectedIds.length === 0) {
                showToast('warning', 'Warning', 'Please select at least one mapping to delete!');
                return;
            }
            showConfirm({
                title: 'Delete Selected Mappings?',
                message: `You are about to delete ${selectedIds.length} mapping(s)!`,
                confirmText: 'Yes, delete them!',
                confirmButtonClass: 'btn-danger',
                onConfirm: function () {
                    $.api.post('settings/course-division-delete-multiple', {
                        ids: selectedIds
                    }).then(response => {
                        if (response.success) {
                            showToast('success', 'Deleted!', response.message);
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            showToast('error', 'Error!', response.message);
                        }
                    }).catch(error => {
                        showToast('error', 'Error!', error.message || 'An error occurred');
                    });
                }
            });
        }

        $('#addForm').on('submit', function (e) {
            e.preventDefault();
            $.api.post('settings/course-division-save', $(this).serialize()).then(response => {
                if (response.success) {
                    showToast('success', 'Success', response.message);
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast('error', 'Error', response.message);
                }
            }).catch(error => {
                showToast('error', 'Error', error.message || 'An error occurred');
            });
        });

        function editItem(data) {
            $('#edit_id').val(data.id);
            $('#edit_course_id').val(data.course_id);
            $('#edit_group_id').val(data.group_id);
            $('#edit_division_id').val(data.division_id);
            $('#edit_start_roll_no').val(data.start_roll_no);
            $('#edit_max_capacity').val(data.max_capacity);
            $('#edit_active').prop('checked', data.is_active == 1);
            $('#editModal').modal('show');
        }

        $('#editForm').on('submit', function (e) {
            e.preventDefault();
            $.api.post('settings/course-division-update', $(this).serialize()).then(response => {
                if (response.success) {
                    showToast('success', 'Success', response.message);
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast('error', 'Error', response.message);
                }
            }).catch(error => {
                showToast('error', 'Error', error.message || 'An error occurred');
            });
        });

        function deleteItem(id) {
            showConfirm({
                title: 'Delete Mapping?',
                message: "This mapping will be deleted!",
                confirmText: 'Yes, delete it!',
                confirmButtonClass: 'btn-danger',
                onConfirm: function () {
                    $.api.post('settings/course-division-delete', {
                        id: id
                    }).then(response => {
                        if (response.success) {
                            showToast('success', 'Deleted!', response.message);
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            showToast('error', 'Error!', response.message);
                        }
                    }).catch(error => {
                        showToast('error', 'Error!', error.message || 'An error occurred');
                    });
                }
            });
        }

        $(document).ready(function () {
            $('#editModal').appendTo("body");
        });
    </script>