<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once __DIR__ . '/../../common/api_client.php';
require_once PAGINATION_FILE;

// Auth check
if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE])) {
    set_flash_message('error', 'Unauthorized: You do not have permission to access this page.');
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

// Handle role filter from POST or session
if (isset($_POST['role'])) {
    $_SESSION['users_role_filter'] = $_POST['role'];
} elseif (isset($_POST['clear_filter'])) {
    unset($_SESSION['users_role_filter']);
    unset($_SESSION['users_pagination']);
}

// Handle page and per_page from POST (pagination clicks)
if (isset($_POST['page']) || isset($_POST['per_page'])) {
    $_SESSION['users_pagination'] = [
        'page' => isset($_POST['page']) ? (int) $_POST['page'] : ($_SESSION['users_pagination']['page'] ?? 1),
        'per_page' => isset($_POST['per_page']) ? (int) $_POST['per_page'] : ($_SESSION['users_pagination']['per_page'] ?? 10)
    ];
}

$api = new APIClient();

// Build request params from session
$requestParams = [];
$paginationSession = $_SESSION['users_pagination'] ?? [];
$requestParams['page'] = $paginationSession['page'] ?? 1;
$requestParams['per_page'] = $paginationSession['per_page'] ?? 10;

// Add search from POST or previous session
if (isset($_POST['search'])) {
    $requestParams['search'] = $_POST['search'];
    $_SESSION['users_search'] = $_POST['search'];
} elseif (isset($_SESSION['users_search'])) {
    $requestParams['search'] = $_SESSION['users_search'];
}

// Add role from session if exists
if (isset($_SESSION['users_role_filter'])) {
    $requestParams['role'] = $_SESSION['users_role_filter'];
}

$response = $api->get('settings/users', $requestParams);

if ($response && isset($response['success']) && $response['success']) {
    $users = $response['data']['users'] ?? [];
    $roles = $response['data']['roles'] ?? [];
    $pagination = $response['data']['pagination'] ?? [];
    $page = $pagination['current_page'] ?? 1;
    $perPage = $pagination['per_page'] ?? 10;
    $totalRecords = $pagination['total_records'] ?? 0;
    $totalPages = $pagination['total_pages'] ?? 1;
    $search = $response['data']['applied_filters']['search'] ?? '';
    $activeRoleFilter = $response['data']['applied_filters']['role'] ?? ($_SESSION['users_role_filter'] ?? '');
} else {
    $users = [];
    $roles = [];
    $page = 1;
    $perPage = 10;
    $totalRecords = 0;
    $totalPages = 1;
    $search = $_POST['search'] ?? '';
    $activeRoleFilter = $_SESSION['users_role_filter'] ?? '';
    set_flash_message('error', $response['error'] ?? 'Failed to load users');
}

$page_title = "Manage Users";

include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>

<!-- Include SheetJS for modern Excel exports -->
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script src="../../assets/js/table-utilities.js"></script>



<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold mb-0 text-dark">User Management</h4>
        <button type="button" class="btn btn-primary d-flex align-items-center gap-2" data-bs-toggle="modal"
            data-bs-target="#addUserModal">
            <i class="fas fa-user-plus"></i>
            <span>Add New User</span>
        </button>
    </div>


    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_SESSION['success'] ?? '');
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_SESSION['error'] ?? '');
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Search and Filter -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="POST" class="row g-3">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control" placeholder="Search by name, email..."
                        value="<?php echo htmlspecialchars($search ?? ''); ?>">
                </div>
                <div class="col-md-2">
                    <select name="per_page" class="form-control">
                        <option value="10" <?php echo $perPage == 10 ? 'selected' : ''; ?>>10 per page</option>
                        <option value="25" <?php echo $perPage == 25 ? 'selected' : ''; ?>>25 per page</option>
                        <option value="50" <?php echo $perPage == 50 ? 'selected' : ''; ?>>50 per page</option>
                        <option value="100" <?php echo $perPage == 100 ? 'selected' : ''; ?>>100 per page</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                </div>
                <?php if (!empty($activeRoleFilter)): ?>
                    <div class="col-md-2">
                        <button type="submit" name="clear_filter" value="1" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Clear Filter
                        </button>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="table-responsive bg-white rounded shadow-sm">
        <table class="table table-bordered table-striped table-hover mb-0" id="usersTable">
            <thead>
                <tr>
                    <th width="3%">
                        <input type="checkbox" id="selectAll" class="form-check-input">
                    </th>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td>
                            <input type="checkbox" class="row-checkbox form-check-input" value="<?php echo $user['id']; ?>">
                        </td>
                        <td><?php echo $user['id']; ?></td>
                        <td><?php echo htmlspecialchars($user['name'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($user['email'] ?? ''); ?></td>
                        <td><span class="badge bg-info"><?php echo htmlspecialchars($user['role_name'] ?? 'N/A'); ?></span>
                        </td>
                        <td>
                            <?php if ($user['is_active'] ?? true): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo isset($user['created_at']) ? date('d M Y', strtotime($user['created_at'])) : 'N/A'; ?>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-info"
                                onclick="editUser(<?php echo htmlspecialchars(json_encode($user) ?? ''); ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="deleteUser(<?php echo $user['id']; ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalRecords > 0): ?>
        <div class="d-flex justify-content-between align-items-center mt-3">
            <?php echo renderPaginationPost($page, $totalPages, 2, $perPage); ?>
            <div class="text-muted">
                <?php echo getPaginationInfo($page, $perPage, $totalRecords); ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-plus"></i> Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addUserForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Role <span class="text-danger">*</span></label>
                        <select name="role_id" class="form-control" required>
                            <option value="">Select Role</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?php echo $role['id']; ?>">
                                    <?php echo htmlspecialchars($role['role_name'] ?? ''); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-edit"></i> Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editUserForm">
                <input type="hidden" name="user_id" id="edit_user_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" id="edit_email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Password (leave blank to keep current)</label>
                        <input type="password" name="password" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label>Role <span class="text-danger">*</span></label>
                        <select name="role_id" id="edit_role_id" class="form-control" required>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?php echo $role['id']; ?>">
                                    <?php echo htmlspecialchars($role['role_name'] ?? ''); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update User</button>
                </div>
            </form>
        </div>
    </div>


    <script>
        document.addEventListener('DOMContentLoaded', function () {
            $('#selectAll').on('change', function () {
                $('.row-checkbox').prop('checked', $(this).prop('checked'));
                toggleDeleteButton();
            });
            $('.row-checkbox').on('change', toggleDeleteButton);

            $('#addUserForm').on('submit', function (e) {
                e.preventDefault();
                $.api.post('settings/user-save', $(this).serialize()).then(response => {
                    if (response.success) {
                        showToast('success', 'Success', response.message);
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showToast('error', 'Error', response.error || response.message);
                    }
                }).catch(() => showToast('error', 'Error', 'Failed to add user'));
            });

            $('#editUserForm').on('submit', function (e) {
                e.preventDefault();
                const userId = $('#edit_user_id').val();
                if (!userId) {
                    showToast('error', 'Error', 'User ID is missing');
                    return;
                }
                $.api.post('settings/user-update', $(this).serialize()).then(response => {
                    if (response.success) {
                        showToast('success', 'Success', response.message);
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showToast('error', 'Error', response.error || response.message);
                    }
                }).catch(() => showToast('error', 'Error', 'Failed to update user'));
            });
        });

        function toggleDeleteButton() {
            $('#deleteSelectedBtn').toggle($('.row-checkbox:checked').length > 0);
        }

        function exportToExcel() {
            TableUtils.exportToExcel('usersTable', 'users_export');
        }

        function deleteSelected() {
            const selected = $('.row-checkbox:checked').map(function () {
                return this.value;
            }).get();
            if (selected.length === 0) return showToast('warning', 'Warning', 'Please select users to delete');

            showConfirm({
                title: 'Delete ' + selected.length + ' user(s)?',
                message: 'Are you sure you want to delete the selected users? This action cannot be undone.',
                confirmText: 'Yes, delete!',
                confirmButtonClass: 'btn-danger',
                onConfirm: function () {
                    $.api.post('settings/users-delete-multiple', {
                        ids: selected
                    }).then(response => {
                        if (response.success) {
                            showToast('success', 'Deleted!', response.message);
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            showToast('error', 'Error', response.message);
                        }
                    }).catch(() => showToast('error', 'Error', 'Failed to delete users'));
                }
            });
        }

        function editUser(user) {
            $('#edit_user_id').val(user.id);
            $('#edit_name').val(user.name);
            $('#edit_email').val(user.email);
            $('#edit_role_id').val(user.role_id);
            $('#editUserModal').modal('show');
        }

        function deleteUser(id) {
            showConfirm({
                title: 'Delete this user?',
                message: 'Are you sure you want to delete this user? This action cannot be undone.',
                confirmText: 'Yes, delete!',
                confirmButtonClass: 'btn-danger',
                onConfirm: function () {
                    $.api.post('settings/user-delete', {
                        id: id
                    }).then(response => {
                        if (response.success) {
                            showToast('success', 'Deleted!', response.message);
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            showToast('error', 'Error', response.message);
                        }
                    }).catch(() => showToast('error', 'Error', 'Failed to delete user'));
                }
            });
        }
    </script>

    <?php include '../../include/footer.php'; ?>