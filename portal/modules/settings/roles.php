<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once __DIR__ . '/../../common/api_client.php';
require_once PORTAL_GLOBALVARIABLE;

// Auth check
if (!hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$api = new APIClient();
$response = $api->get('settings/roles');

if ($response && isset($response['success']) && $response['success']) {
    $roles = $response['data']['roles'] ?? [];
} else {
    $roles = [];
    set_flash_message('error', $response['error'] ?? 'Failed to load roles');
}

require_once PAGINATION_FILE;
require_once __DIR__ . '/../../common/security_output.php';

$page_title = "Manage Roles";

include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>

<!-- Include SheetJS for modern Excel exports -->
<script src="<?php echo BASE_URL; ?>/assets/vendor/xlsx/xlsx.full.min.js"></script>
<script src="../../assets/js/table-utilities.js"></script>


<div class="container-fluid">


    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($_SESSION['success'] ?? ''); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($_SESSION['error'] ?? ''); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Responsive Table -->
    <div class="table-responsive bg-white rounded shadow-sm">
        <table class="table table-bordered table-striped table-hover mb-0" id="rolesTable">
            <thead>
                <tr>
                    <th width="3%">
                        <input type="checkbox" id="selectAll" class="form-check-input">
                    </th>
                    <th>ID</th>
                    <th>Role Name</th>
                    <th>Role Slug</th>
                    <th>Description</th>
                    <th>Users Count</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($roles as $role): ?>
                    <tr>
                        <td>
                            <input type="checkbox" class="row-checkbox form-check-input" value="<?php echo $role['id']; ?>">
                        </td>
                        <td>
                            <?php echo $role['id']; ?>
                        </td>
                        <td>
                            <span
                                class="badge badge-<?php echo $role['id'] == 1 ? 'danger' : ($role['id'] == 2 ? 'primary' : ($role['id'] == 3 ? 'info' : 'success')); ?>">
                                <?php echo htmlspecialchars($role['role_name'] ?? ''); ?>
                            </span>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($role['role_slug'] ?? ''); ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($role['description'] ?? 'N/A'); ?>
                        </td>
                        <td><span class="badge bg-info text-dark">
                                <?php echo $role['user_count']; ?> users
                            </span></td>
                        <td>
                            <?php echo date('d M Y', strtotime($role['created_at'])); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</div>

<!-- Create Role Modal -->
<div class="modal fade" id="createRoleModal" tabindex="-1" role="dialog" aria-labelledby="createRoleModalLabel"
    aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 class="modal-title" id="createRoleModalLabel">
                    <i class="fas fa-user-tag"></i> Create New Role
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <form id="createRoleForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="role_name">Role Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="role_name" name="role_name" required
                            placeholder="e.g., Teacher, Manager">
                    </div>
                    <div class="form-group">
                        <label for="role_slug">Role Slug <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="role_slug" name="role_slug" required
                            placeholder="e.g., teacher, manager">
                        <small class="form-text text-muted">Lowercase letters and hyphens only. No spaces.</small>
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"
                            placeholder="Brief description of the role"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Create Role
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        $('#selectAll').on('change', function () {
            $('.row-checkbox').prop('checked', $(this).prop('checked'));
            toggleDeleteButton();
        });
        $('.row-checkbox').on('change', toggleDeleteButton);

        function toggleDeleteButton() {
            $('#deleteSelectedBtn').toggle($('.row-checkbox:checked').length > 0);
        }

        function exportToExcel() {
            TableUtils.exportToExcel('rolesTable', 'roles_list');
        }

        function deleteSelected() {
            const selected = $('.row-checkbox:checked').map(function () {
                return this.value;
            }).get();
            if (selected.length === 0) {
                showToast('warning', 'Warning', 'Please select at least one role to delete');
                return;
            }
            showConfirm({
                title: 'Are you sure?',
                message: 'You are about to delete ' + selected.length + ' role(s)',
                confirmText: 'Yes, delete them!',
                confirmButtonClass: 'btn-danger',
                onConfirm: function () {
                    $.api.post('settings/roles-delete-multiple', {
                        ids: selected
                    }).then(response => {
                        if (response.success) {
                            showToast('success', 'Deleted!', response.message);
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            showToast('error', 'Error', response.message);
                        }
                    }).catch(error => {
                        showToast('error', 'Error', 'An error occurred while deleting roles');
                    });
                }
            });
        }

        // AJAX Role Creation
        $('#createRoleForm').on('submit', function (e) {
            e.preventDefault();
            $.api.post('settings/role-save', $(this).serialize()).then(response => {
                if (response.success) {
                    showToast('success', 'Success', response.message);
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast('error', 'Error', response.error || response.message);
                }
            }).catch(error => {
                showToast('error', 'Error', 'An error occurred while creating role');
            });
        });

        // Auto-generate slug from role name
        document.getElementById('role_name').addEventListener('input', function () {
            const slug = this.value.toLowerCase()
                .replace(/[^a-z0-9\s-]/g, '')
                .replace(/\s+/g, '-')
                .replace(/-+/g, '-')
                .trim();
            document.getElementById('role_slug').value = slug;
        });

        // Move modal to body to prevent z-index issues
        $(document).ready(function () {
            $('#createRoleModal').appendTo("body");
        });
    </script>

    <?php include '../../include/footer.php'; ?>