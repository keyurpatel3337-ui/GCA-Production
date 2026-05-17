<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once __DIR__ . '/../../common/api_client.php';
require_once PORTAL_GLOBALVARIABLE;

if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE])) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$apiClient = new APIClient();
$response = $apiClient->get('settings/campuses');

$campuses = (is_array($response) && isset($response['success']) && $response['success']) ? ($response['data']['campuses'] ?? []) : [];

$page_title = 'Campus Management';
include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>

<div class="container-fluid py-4">
    <div class="card shadow-sm overflow-hidden border-0">
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
            <h5 class="card-title mb-0 fw-bold text-dark">
                <i class="fas fa-university me-2 text-primary"></i> Campus List
            </h5>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3"
                    onclick="exportToExcel()">
                    <i class="fas fa-file-excel me-1"></i> Export
                </button>
                <button type="button" class="btn btn-primary btn-sm rounded-pill px-3" data-bs-toggle="modal"
                    data-bs-target="#addModal">
                    <i class="fas fa-plus me-1"></i> Add Campus
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="campusesTable">
                    <thead class="table-light">
                        <tr>
                            <th width="5%">#</th>
                            <th width="30%">Campus Name</th>
                            <th width="15%">Code</th>
                            <th width="15%">Status</th>
                            <th width="20%">Created By</th>
                            <th width="15%">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($campuses as $index => $campus): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><strong><?= htmlspecialchars($campus['campus_name'] ?? '') ?></strong></td>
                                <td><?= htmlspecialchars($campus['campus_code'] ?? '-') ?></td>
                                <td>
                                    <?php if ($campus['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($campus['created_by_name'] ?? 'N/A') ?></td>
                                <td>
                                    <div class="btn-group">
                                        <button class="btn btn-sm btn-outline-primary"
                                            onclick="editItem(<?= htmlspecialchars(json_encode($campus) ?? '') ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger"
                                            onclick="deleteItem(<?= $campus['id'] ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($campuses)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">No campuses found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="fas fa-plus me-2 text-primary"></i>Add Campus</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addForm" method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Campus Name <span class="text-danger">*</span></label>
                        <input type="text" name="campus_name" class="form-control" placeholder="e.g., Ishavashyam Campus" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Campus Code <span class="text-danger">*</span></label>
                        <input type="text" name="campus_code" class="form-control" placeholder="e.g., ISH" required>
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="add_active" checked>
                            <label class="form-check-label" for="add_active">Active Status</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Save Campus</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0 bg-warning-light">
                <h5 class="modal-title fw-bold"><i class="fas fa-edit me-2 text-warning"></i>Edit Campus</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editForm" method="POST">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Campus Name <span class="text-danger">*</span></label>
                        <input type="text" name="campus_name" id="edit_campus_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Campus Code <span class="text-danger">*</span></label>
                        <input type="text" name="campus_code" id="edit_campus_code" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="edit_active">
                            <label class="form-check-label" for="edit_active">Active Status</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-warning rounded-pill px-4">Update Campus</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Include SheetJS for modern Excel exports -->
<script src="<?php echo BASE_URL; ?>/assets/vendor/xlsx/xlsx.full.min.js"></script>
<script src="../../assets/js/table-utilities.js"></script>
<?php include '../../include/footer.php'; ?>

<script>
    function exportToExcel() {
        TableUtils.exportToExcel('campusesTable', 'campuses_export');
    }

    $('#addForm').on('submit', function (e) {
        e.preventDefault();
        $.api.post('settings/campus-save', $(this).serialize()).then(response => {
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
        $('#edit_campus_name').val(data.campus_name);
        $('#edit_campus_code').val(data.campus_code);
        $('#edit_active').prop('checked', data.is_active == 1);
        $('#editModal').modal('show');
    }

    $('#editForm').on('submit', function (e) {
        e.preventDefault();
        $.api.post('settings/campus-update', $(this).serialize()).then(response => {
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
            title: 'Delete Campus?',
            message: "This campus will be deleted if not linked to any student!",
            confirmText: 'Yes, delete it!',
            confirmButtonClass: 'btn-danger',
            onConfirm: function () {
                $.api.post('settings/campus-delete', {
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
