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
$response = $apiClient->get('settings/terms');

$terms = $response['success'] ? $response['data']['terms'] : [];
$academic_years = $response['success'] ? $response['data']['academic_years'] : [];

$page_title = 'Term/Semester Management';
include '../../include/header.php';
?>
<link rel="stylesheet" href="<?= PORTAL_URL ?>/assets/css/modules/academics/term.css">
<?php
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>



<div class="container-fluid py-4">
    <div class="card shadow-sm overflow-hidden border-0">
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
            <h5 class="card-title mb-0 fw-bold text-dark">
                <i class="fas fa-calendar-alt me-2 text-primary"></i> Term List
            </h5>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3"
                    onclick="exportToExcel()">
                    <i class="fas fa-file-excel me-1"></i> Export
                </button>
                <button type="button" class="btn btn-primary btn-sm rounded-pill px-3" data-bs-toggle="modal"
                    data-bs-target="#addModal">
                    <i class="fas fa-plus me-1"></i> Add Term
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
                <table class="table table-hover align-middle" id="termTable">
                    <thead class="table-light">
                        <tr>
                            <th width="3%">
                                <input type="checkbox" id="selectAll" class="form-check-input">
                            </th>
                            <th width="5%">#</th>
                            <th width="17%">Academic Year</th>
                            <th width="17%">Term Name</th>
                            <th width="10%">Term Number</th>
                            <th width="13%">Start Date</th>
                            <th width="13%">End Date</th>
                            <th width="10%">Status</th>
                            <th width="12%">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($terms as $index => $term): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" class="row-checkbox form-check-input" value="<?= $term['id'] ?>">
                                </td>
                                <td><?= $index + 1 ?></td>
                                <td><strong><?= htmlspecialchars($term['year_name'] ?? '') ?></strong></td>
                                <td><?= htmlspecialchars($term['term_name'] ?? '') ?></td>
                                <td><span class="badge bg-info"><?= $term['term_number'] ?></span></td>
                                <td><?= $term['start_date'] ? date('d M Y', strtotime($term['start_date'])) : '-' ?>
                                </td>
                                <td><?= $term['end_date'] ? date('d M Y', strtotime($term['end_date'])) : '-' ?></td>
                                <td>
                                    <?php if ($term['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <button class="btn btn-sm btn-outline-primary"
                                            onclick="editItem(<?= htmlspecialchars(json_encode($term) ?? '') ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger"
                                            onclick="deleteItem(<?= $term['id'] ?>)">
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
                <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Add Term/Semester</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addForm" method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Academic Year <span class="text-danger">*</span></label>
                        <select name="academic_year_id" class="form-control" required>
                            <option value="">-- Select Academic Year --</option>
                            <?php foreach ($academic_years as $year): ?>
                                <option value="<?= $year['id'] ?>"><?= htmlspecialchars($year['year_name'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Term Name <span class="text-danger">*</span></label>
                        <input type="text" name="term_name" class="form-control" placeholder="e.g., Semester 1, Term 1"
                            required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Term Number <span class="text-danger">*</span></label>
                        <input type="number" name="term_number" class="form-control" min="1" placeholder="1" required>
                        <small class="text-muted">Sequential order: 1, 2, 3, etc.</small>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Start Date</label>
                                <input type="date" name="start_date" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">End Date</label>
                                <input type="date" name="end_date" class="form-control">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="add_active"
                                checked>
                            <label class="form-check-label" for="add_active">Active Status</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Term</button>
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
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Term/Semester</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editForm" method="POST">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Academic Year <span class="text-danger">*</span></label>
                        <select name="academic_year_id" id="edit_academic_year_id" class="form-control" required>
                            <option value="">-- Select Academic Year --</option>
                            <?php foreach ($academic_years as $year): ?>
                                <option value="<?= $year['id'] ?>"><?= htmlspecialchars($year['year_name'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Term Name <span class="text-danger">*</span></label>
                        <input type="text" name="term_name" id="edit_term_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Term Number <span class="text-danger">*</span></label>
                        <input type="number" name="term_number" id="edit_term_number" class="form-control" min="1"
                            required>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Start Date</label>
                                <input type="date" name="start_date" id="edit_start_date" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">End Date</label>
                                <input type="date" name="end_date" id="edit_end_date" class="form-control">
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
                    <button type="submit" class="btn btn-warning">Update Term</button>
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
            TableUtils.exportToExcel('termTable', 'term_export');
        }

        function deleteSelected() {
            let selectedIds = [];
            $('.row-checkbox:checked').each(function () {
                selectedIds.push($(this).val());
            });
            if (selectedIds.length === 0) {
                showToast('warning', 'Warning', 'Please select at least one term!');
                return;
            }
            showConfirm({
                title: 'Delete Selected Terms?',
                message: `You are about to delete ${selectedIds.length} term(s)!`,
                confirmText: 'Yes, delete them!',
                confirmButtonClass: 'btn-danger',
                onConfirm: function () {
                    $.api.post('settings/term-delete-multiple', {
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
            $.api.post('settings/term-save', $(this).serialize()).then(response => {
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
            $('#edit_academic_year_id').val(data.academic_year_id);
            $('#edit_term_name').val(data.term_name);
            $('#edit_term_number').val(data.term_number);
            $('#edit_start_date').val(data.start_date);
            $('#edit_end_date').val(data.end_date);
            $('#edit_active').prop('checked', data.is_active == 1);
            $('#editModal').modal('show');
        }

        $('#editForm').on('submit', function (e) {
            e.preventDefault();
            $.api.post('settings/term-update', $(this).serialize()).then(response => {
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
                title: 'Delete Term?',
                message: "This term will be deleted!",
                confirmText: 'Yes, delete it!',
                confirmButtonClass: 'btn-danger',
                onConfirm: function () {
                    $.api.post('settings/term-delete', {
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
            $('#addModal').appendTo("body");
            $('#editModal').appendTo("body");
        });
    </script>