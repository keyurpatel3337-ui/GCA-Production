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

// Check if role or search parameters are passed in GET (e.g. from dashboard or sidebar redirection)
if (isset($_GET['role'])) {
    $roleParam = trim($_GET['role']);
    if ($roleParam !== '') {
        $_SESSION['users_role_filter'] = $roleParam;
    } else {
        unset($_SESSION['users_role_filter']);
    }
    $_SESSION['users_pagination']['page'] = 1;
}

if (isset($_GET['search'])) {
    $_SESSION['users_search'] = trim($_GET['search']);
    $_SESSION['users_pagination']['page'] = 1;
}

// Initialize session variables if not set
if (!isset($_SESSION['users_pagination'])) {
    $_SESSION['users_pagination'] = ['page' => 1, 'per_page' => 10];
} else {
    $_SESSION['users_pagination']['per_page'] = 10;
}

// Check for clearing filters
if (isset($_POST['clear_filter'])) {
    unset($_SESSION['users_role_filter']);
    unset($_SESSION['users_dept_filter']);
    unset($_SESSION['users_desig_filter']);
    unset($_SESSION['users_search']);
    $_SESSION['users_pagination']['page'] = 1;
    $_SESSION['users_pagination']['per_page'] = 10;
    // Redirect to prevent form resubmission
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Handle new search/filter submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['page'])) {
    // If a search or filter is submitted (and it's not just a pagination click),
    // we reset the page to 1 so the user doesn't get stuck on a page that doesn't exist for the new filtered subset.
    $_SESSION['users_pagination']['page'] = 1;
    
    // Process search query
    if (isset($_POST['search'])) {
        $_SESSION['users_search'] = trim($_POST['search']);
    }
    
    // Process role filter
    if (isset($_POST['role'])) {
        if ($_POST['role'] === '') {
            unset($_SESSION['users_role_filter']);
        } else {
            $_SESSION['users_role_filter'] = $_POST['role'];
        }
    }

    // Process department filter
    if (isset($_POST['dept'])) {
        if ($_POST['dept'] === '') {
            unset($_SESSION['users_dept_filter']);
        } else {
            $_SESSION['users_dept_filter'] = $_POST['dept'];
        }
    }

    // Process designation filter
    if (isset($_POST['designation'])) {
        if ($_POST['designation'] === '') {
            unset($_SESSION['users_desig_filter']);
        } else {
            $_SESSION['users_desig_filter'] = $_POST['designation'];
        }
    }
}

// Handle page clicks explicitly (which are also POSTed via pagination forms)
if (isset($_POST['page'])) {
    $_SESSION['users_pagination']['page'] = max(1, (int)$_POST['page']);
}

// Ensure perPage is always locked to 10
$_SESSION['users_pagination']['per_page'] = 10;

$api = new APIClient();

// Build API request params
$requestParams = [
    'page' => $_SESSION['users_pagination']['page'] ?? 1,
    'per_page' => 10
];

if (isset($_SESSION['users_search']) && $_SESSION['users_search'] !== '') {
    $requestParams['search'] = $_SESSION['users_search'];
}

if (isset($_SESSION['users_role_filter']) && $_SESSION['users_role_filter'] !== '') {
    $requestParams['role'] = $_SESSION['users_role_filter'];
}

if (isset($_SESSION['users_dept_filter']) && $_SESSION['users_dept_filter'] !== '') {
    $requestParams['dept'] = $_SESSION['users_dept_filter'];
}

if (isset($_SESSION['users_desig_filter']) && $_SESSION['users_desig_filter'] !== '') {
    $requestParams['designation'] = $_SESSION['users_desig_filter'];
}

$response = $api->get('settings/users', $requestParams);

if ($response && isset($response['success']) && $response['success']) {
    $users = $response['data']['users'] ?? [];
    $roles = $response['data']['roles'] ?? [];
    $departments = $response['data']['departments'] ?? [];
    $designations = $response['data']['designations'] ?? [];
    $pagination = $response['data']['pagination'] ?? [];
    $page = $pagination['current_page'] ?? 1;
    $perPage = $pagination['per_page'] ?? 10;
    $totalRecords = $pagination['total_records'] ?? 0;
    $totalPages = $pagination['total_pages'] ?? 1;
    $search = $response['data']['applied_filters']['search'] ?? '';
    
    $activeRoleFilter = $response['data']['applied_filters']['role'] ?? ($_SESSION['users_role_filter'] ?? '');
    if ($activeRoleFilter !== '') {
        $_SESSION['users_role_filter'] = $activeRoleFilter;
    }
    
    $activeDeptFilter = $response['data']['applied_filters']['dept'] ?? ($_SESSION['users_dept_filter'] ?? '');
    if ($activeDeptFilter !== '') {
        $_SESSION['users_dept_filter'] = $activeDeptFilter;
    }

    $activeDesigFilter = $response['data']['applied_filters']['designation'] ?? ($_SESSION['users_desig_filter'] ?? '');
    if ($activeDesigFilter !== '') {
        $_SESSION['users_desig_filter'] = $activeDesigFilter;
    }
} else {
    $users = [];
    $roles = [];
    $departments = [];
    $designations = [];
    $page = 1;
    $perPage = 10;
    $totalRecords = 0;
    $totalPages = 1;
    $search = $_SESSION['users_search'] ?? '';
    $activeRoleFilter = $_SESSION['users_role_filter'] ?? '';
    $activeDeptFilter = $_SESSION['users_dept_filter'] ?? '';
    $activeDesigFilter = $_SESSION['users_desig_filter'] ?? '';
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
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body bg-light rounded">
            <form method="POST" class="row g-3 align-items-center">
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1">Search User</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Search by name, email..."
                            value="<?php echo htmlspecialchars($search ?? ''); ?>">
                    </div>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">Filter by Role</label>
                    <select name="role" class="form-select" onchange="this.form.submit()">
                        <option value="">All Roles</option>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?php echo $role['id']; ?>" <?php echo $activeRoleFilter == $role['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($role['role_name'] ?? ''); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">Department</label>
                    <select name="dept" class="form-select" onchange="this.form.submit()">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $dept_opt): ?>
                            <option value="<?php echo $dept_opt['id']; ?>" <?php echo $activeDeptFilter == $dept_opt['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept_opt['dept_name'] ?? ''); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">Designation</label>
                    <select name="designation" class="form-select" onchange="this.form.submit()">
                        <option value="">All Designations</option>
                        <?php foreach ($designations as $desig_opt): ?>
                            <option value="<?php echo htmlspecialchars($desig_opt['designation']); ?>" <?php echo $activeDesigFilter == $desig_opt['designation'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($desig_opt['designation']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3 d-flex align-items-end gap-2 pt-3">
                    <button type="submit" class="btn btn-primary flex-grow-1">
                        <i class="fas fa-filter me-1"></i> Apply Filters
                    </button>
                    <?php if (!empty($search) || !empty($activeRoleFilter) || !empty($activeDeptFilter) || !empty($activeDesigFilter)): ?>
                        <button type="submit" name="clear_filter" value="1" class="btn btn-outline-danger">
                            <i class="fas fa-undo-alt me-1"></i> Clear
                        </button>
                    <?php endif; ?>
                </div>
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
                    <th>Designation</th>
                    <th>Department</th>
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
                        <td><span class="badge bg-info"><?php echo htmlspecialchars($user['role_name'] ?? 'N/A'); ?></span></td>
                        <td><?php echo htmlspecialchars($user['designation'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($user['department_name'] ?? 'N/A'); ?></td>
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
        <div class="row mt-2">
            <div class="col-12">
                <?php 
                $extraParams = [];
                if (!empty($search)) {
                    $extraParams['search'] = $search;
                }
                if (!empty($activeRoleFilter)) {
                    $extraParams['role'] = $activeRoleFilter;
                }
                if (!empty($activeDeptFilter)) {
                    $extraParams['dept'] = $activeDeptFilter;
                }
                if (!empty($activeDesigFilter)) {
                    $extraParams['designation'] = $activeDesigFilter;
                }
                echo renderPaginationPost($page, $totalPages, 2, $perPage, $extraParams, $totalRecords, 'users'); 
                ?>
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