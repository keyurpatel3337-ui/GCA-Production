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
$response = $apiClient->get('settings/courses');

$courses = (is_array($response) && isset($response['success']) && $response['success']) ? ($response['data']['courses'] ?? []) : [];
$boards = (is_array($response) && isset($response['success']) && $response['success']) ? ($response['data']['boards'] ?? []) : [];

$page_title = 'Standard Management';
include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>



<div class="container-fluid py-4">
    <div class="card shadow-sm overflow-hidden border-0">
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
            <h5 class="card-title mb-0 fw-bold text-dark">
                <i class="fas fa-book me-2 text-primary"></i> Standard List
            </h5>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3"
                    onclick="exportToExcel()">
                    <i class="fas fa-file-excel me-1"></i> Export
                </button>
                <button type="button" class="btn btn-primary btn-sm rounded-pill px-3" data-bs-toggle="modal"
                    data-bs-target="#addModal">
                    <i class="fas fa-plus me-1"></i> Add Standard
                </button>
                <div id="deleteSelectedBtn" style="display: none;">
                    <button class="btn btn-danger btn-sm rounded-pill px-3" onclick="deleteSelected()">
                        <i class="fas fa-trash-alt me-1"></i> Delete Selected
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="coursesTable">
                    <thead class="table-light">
                        <tr>
                            <th width="3%">
                                <input type="checkbox" id="selectAll" class="form-check-input">
                            </th>
                            <th width="5%">#</th>
                            <th width="18%">Standard Name</th>
                            <th width="13%">Board</th>
                            <th width="10%">Code</th>
                            <th width="8%">Standard</th>
                            <th width="23%">Description</th>
                            <th width="8%">Order</th>
                            <th width="8%">Status</th>
                            <th width="12%">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($courses as $index => $course): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" class="row-checkbox form-check-input"
                                        value="<?= $course['id'] ?>">
                                </td>
                                <td><?= $index + 1 ?></td>
                                <td><strong><?= htmlspecialchars($course['course_name'] ?? '') ?></strong></td>
                                <td><?= htmlspecialchars($course['board_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($course['course_code'] ?? '-') ?></td>
                                <td><span
                                        class="badge bg-info text-white"><?= htmlspecialchars($course['standard'] ?? 'N/A') ?></span>
                                </td>
                                <td><?= htmlspecialchars($course['description'] ?? '-') ?></td>
                                <td><span class="badge bg-light text-dark"><?= $course['display_order'] ?></span></td>
                                <td>
                                    <?php if ($course['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <button class="btn btn-sm btn-outline-primary"
                                            onclick="editItem(<?= htmlspecialchars(json_encode($course) ?? '') ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger"
                                            onclick="deleteItem(<?= $course['id'] ?>)">
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

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Add Standard</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addForm" method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Standard Name <span class="text-danger">*</span></label>
                        <input type="text" name="course_name" class="form-control"
                            placeholder="Engineering, Medical, Commerce" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Board <span class="text-danger">*</span></label>
                        <select name="board_id" class="form-select" required>
                            <option value="">-- Select Board --</option>
                            <?php foreach ($boards as $board): ?>
                                <option value="<?= $board['id'] ?>"><?= htmlspecialchars($board['board_name'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Standard Code</label>
                        <input type="text" name="course_code" class="form-control" placeholder="ENG">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Standard <span class="text-danger">*</span></label>
                        <select name="standard" class="form-select" required>
                            <option value="">-- Select Standard --</option>
                            <option value="11">Class 11</option>
                            <option value="12">Class 12</option>
                            <option value="0">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Display Order</label>
                        <input type="number" name="display_order" class="form-control" value="0">
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="add_active"
                                checked>
                            <label class="form-check-label" for="add_active">Active</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Standard</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Standard</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editForm" method="POST">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Standard Name <span class="text-danger">*</span></label>
                        <input type="text" name="course_name" id="edit_course_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Board <span class="text-danger">*</span></label>
                        <select name="board_id" id="edit_board_id" class="form-select" required>
                            <option value="">-- Select Board --</option>
                            <?php foreach ($boards as $board): ?>
                                <option value="<?= $board['id'] ?>"><?= htmlspecialchars($board['board_name'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Standard Code</label>
                        <input type="text" name="course_code" id="edit_course_code" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Standard <span class="text-danger">*</span></label>
                        <select name="standard" id="edit_standard" class="form-select" required>
                            <option value="">-- Select Standard --</option>
                            <option value="11">Class 11</option>
                            <option value="12">Class 12</option>
                            <option value="0">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="edit_description" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Display Order</label>
                        <input type="number" name="display_order" id="edit_display_order" class="form-control">
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
                    <button type="submit" class="btn btn-warning">Update Standard</button>
                </div>
            </form>
        </div>
    </div>

    <?php include '../../include/footer.php'; ?>

    <script>
        // Initialize table utilities using centralized functions
        $(document).ready(function () {
            // Initialize select-all checkbox functionality
            TableUtils.initSelectAll('selectAll', 'row-checkbox', 'deleteSelectedBtn');

            // Move modals to body
            $('#addModal').appendTo("body");
            $('#editModal').appendTo("body");
        });

        // Export to Excel using centralized utility
        function exportToExcel() {
            TableUtils.exportToExcel('coursesTable', 'courses_export');
        }

        // Delete selected using centralized utility
        function deleteSelected() {
            DeleteHandler.deleteSelected('row-checkbox', 'settings/courses-delete-multiple', 'course');
        }

        // Delete single item using centralized utility
        function deleteItem(id) {
            DeleteHandler.deleteItem(id, 'settings/course-delete', 'course');
        }

        // Add form handler
        $('#addForm').on('submit', function (e) {
            e.preventDefault();
            $.api.post('settings/course-save', $(this).serialize()).then(response => {
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

        // Edit item function
        function editItem(data) {
            $('#edit_id').val(data.id);
            $('#edit_course_name').val(data.course_name);
            $('#edit_board_id').val(data.board_id);
            $('#edit_course_code').val(data.course_code);
            $('#edit_standard').val(data.standard);
            $('#edit_description').val(data.description);
            $('#edit_display_order').val(data.display_order);
            $('#edit_active').prop('checked', data.is_active == 1);
            $('#editModal').modal('show');
        }

        // Edit form handler
        $('#editForm').on('submit', function (e) {
            e.preventDefault();
            $.api.post('settings/course-update', $(this).serialize()).then(response => {
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
    </script>