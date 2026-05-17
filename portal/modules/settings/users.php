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

$api = new APIClient();
// Get roles for the filter dropdown
$rolesResponse = $api->get('settings/users', ['per_page' => 1]); // Quick way to get roles list
$roles = $rolesResponse['data']['roles'] ?? [];

// Capture initial filters from Dashboard redirects (POST or GET)
$initial_role = $_POST['role'] ?? $_GET['role'] ?? '';
$initial_search = $_POST['search'] ?? $_GET['search'] ?? '';

$page_title = "Manage Users";

include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>

<!-- Include SheetJS for modern Excel exports -->
<script src="<?php echo BASE_URL; ?>/assets/vendor/xlsx/xlsx.full.min.js"></script>
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

    <!-- Search and Filter -->
    <div class="card mb-3">
        <div class="card-body">
            <form id="filterForm" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Search</label>
                    <input type="text" name="search" id="searchInput" class="form-control" placeholder="Search by name, email...">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Role Filter</label>
                    <select name="role" id="roleFilter" class="form-select">
                        <option value="">All Roles</option>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?php echo htmlspecialchars($role['role_slug'] ?? ''); ?>">
                                <?php echo htmlspecialchars($role['role_name'] ?? ''); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Per Page</label>
                    <select name="per_page" id="perPageFilter" class="form-select">
                        <option value="10">10 per page</option>
                        <option value="25">25 per page</option>
                        <option value="50">50 per page</option>
                        <option value="100">100 per page</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="fas fa-search me-1"></i> Search
                        </button>
                        <button type="button" id="resetBtn" class="btn btn-outline-secondary">
                            <i class="fas fa-undo"></i> Reset
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="d-flex gap-2">
            <button type="button" id="deleteSelectedBtn" class="btn btn-danger-custom" style="display: none;" onclick="deleteSelected()">
                <i class="fas fa-trash-alt me-1"></i> Delete Selected
            </button>
            <button type="button" class="btn btn-success-custom" onclick="exportToExcel()">
                <i class="fas fa-file-excel me-1"></i> Export to Excel
            </button>
        </div>
        <div class="text-muted small">
            Total Users: <span id="totalRecordsCount" class="fw-bold">0</span>
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
            <tbody id="usersTableBody">
                <!-- Data loaded via AJAX -->
                <tr>
                    <td colspan="8" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-2 mb-0 text-muted">Loading users...</p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div id="paginationContainer" class="mt-3"></div>
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
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" name="password" id="add_password" class="form-control" required>
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('add_password', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role <span class="text-danger">*</span></label>
                        <select name="role_id" class="form-select" required>
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
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" id="edit_email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password (leave blank to keep current)</label>
                        <div class="input-group">
                            <input type="password" name="password" id="edit_password" class="form-control">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('edit_password', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role <span class="text-danger">*</span></label>
                        <select name="role_id" id="edit_role_id" class="form-select" required>
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
</div>

<script>
    let currentPage = 1;

    document.addEventListener('DOMContentLoaded', function () {
        // Set initial filters if passed from dashboard
        const initialRole = "<?php echo $initial_role; ?>";
        const initialSearch = "<?php echo $initial_search; ?>";
        
        if (initialRole) {
            $('#roleFilter').val(initialRole);
        }
        if (initialSearch) {
            $('#searchInput').val(initialSearch);
        }

        loadUsers();

        $('#filterForm').on('submit', function (e) {
            e.preventDefault();
            currentPage = 1;
            loadUsers();
        });

        $('#resetBtn').on('click', function () {
            $('#filterForm')[0].reset();
            currentPage = 1;
            loadUsers();
        });

        $('#selectAll').on('change', function () {
            $('.row-checkbox').prop('checked', $(this).prop('checked'));
            toggleDeleteButton();
        });

        $(document).on('change', '.row-checkbox', toggleDeleteButton);

        // Add/Edit Form submissions
        $('#addUserForm').on('submit', function (e) {
            e.preventDefault();
            const btn = $(this).find('button[type="submit"]');
            const originalText = btn.html();
            btn.html('<i class="fas fa-spinner fa-spin me-1"></i> Processing...').prop('disabled', true);

            $.api.post('settings/user-save', $(this).serialize()).then(response => {
                if (response.success) {
                    showToast('success', 'Success', response.message);
                    $('#addUserModal').modal('hide');
                    $(this)[0].reset();
                    loadUsers();
                } else {
                    showToast('error', 'Error', response.error || response.message);
                }
                btn.html(originalText).prop('disabled', false);
            }).catch(() => {
                showToast('error', 'Error', 'Failed to add user');
                btn.html(originalText).prop('disabled', false);
            });
        });

        $('#editUserForm').on('submit', function (e) {
            e.preventDefault();
            const btn = $(this).find('button[type="submit"]');
            const originalText = btn.html();
            btn.html('<i class="fas fa-spinner fa-spin me-1"></i> Processing...').prop('disabled', true);

            $.api.post('settings/user-update', $(this).serialize()).then(response => {
                if (response.success) {
                    showToast('success', 'Success', response.message);
                    $('#editUserModal').modal('hide');
                    loadUsers();
                } else {
                    showToast('error', 'Error', response.error || response.message);
                }
                btn.html(originalText).prop('disabled', false);
            }).catch(() => {
                showToast('error', 'Error', 'Failed to update user');
                btn.html(originalText).prop('disabled', false);
            });
        });
    });

    function loadUsers(page = 1) {
        currentPage = page;
        const filters = {
            search: $('#searchInput').val(),
            role: $('#roleFilter').val(),
            per_page: $('#perPageFilter').val(),
            page: page
        };

        $('#usersTableBody').html(`
            <tr>
                <td colspan="8" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2 mb-0 text-muted">Loading users...</p>
                </td>
            </tr>
        `);

        $.api.get('settings/users', filters).then(response => {
            if (response.success) {
                renderTable(response.data.users);
                renderPagination(response.data.pagination);
                $('#totalRecordsCount').text(response.data.pagination.total_records);
            } else {
                showToast('error', 'Error', response.message);
            }
        }).catch(() => {
            $('#usersTableBody').html('<tr><td colspan="8" class="text-center text-danger">Failed to load users</td></tr>');
        });
    }

    function renderTable(users) {
        const tbody = $('#usersTableBody');
        tbody.empty();

        if (users.length === 0) {
            tbody.html('<tr><td colspan="8" class="text-center py-4">No users found matching your criteria</td></tr>');
            return;
        }

        users.forEach(user => {
            const statusBadge = user.is_active ? 
                '<span class="badge bg-success">Active</span>' : 
                '<span class="badge bg-danger">Inactive</span>';
            
            const createdAt = user.created_at ? new Date(user.created_at).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : 'N/A';

            const row = `
                <tr>
                    <td><input type="checkbox" class="row-checkbox form-check-input" value="${user.id}"></td>
                    <td>${user.id}</td>
                    <td class="fw-bold">${user.name}</td>
                    <td>${user.email}</td>
                    <td><span class="badge bg-info">${user.role_name}</span></td>
                    <td>${statusBadge}</td>
                    <td>${createdAt}</td>
                    <td>
                        <button class="btn btn-sm btn-info me-1" onclick='editUser(${JSON.stringify(user)})'>
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="deleteUser(${user.id})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
            tbody.append(row);
        });
        
        $('#selectAll').prop('checked', false);
        toggleDeleteButton();
    }

    function renderPagination(p) {
        const container = $('#paginationContainer');
        container.empty();

        if (p.total_pages <= 1) return;

        let html = '<div class="d-flex justify-content-between align-items-center">';
        html += '<ul class="pagination mb-0">';
        
        // Previous
        html += `<li class="page-item ${p.current_page == 1 ? 'disabled' : ''}">
            <a class="page-link" href="javascript:void(0)" onclick="loadUsers(${p.current_page - 1})">Previous</a>
        </li>`;

        for (let i = 1; i <= p.total_pages; i++) {
            if (i == 1 || i == p.total_pages || (i >= p.current_page - 2 && i <= p.current_page + 2)) {
                html += `<li class="page-item ${p.current_page == i ? 'active' : ''}">
                    <a class="page-link" href="javascript:void(0)" onclick="loadUsers(${i})">${i}</a>
                </li>`;
            } else if (i == p.current_page - 3 || i == p.current_page + 3) {
                html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }

        // Next
        html += `<li class="page-item ${p.current_page == p.total_pages ? 'disabled' : ''}">
            <a class="page-link" href="javascript:void(0)" onclick="loadUsers(${p.current_page + 1})">Next</a>
        </li>`;
        
        html += '</ul>';
        html += `<div class="text-muted small">Showing ${(p.current_page - 1) * p.per_page + 1} to ${Math.min(p.current_page * p.per_page, p.total_records)} of ${p.total_records} entries</div>`;
        html += '</div>';

        container.html(html);
    }

    function togglePasswordVisibility(inputId, btn) {
        const input = document.getElementById(inputId);
        const icon = btn.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }

    function toggleDeleteButton() {
        $('#deleteSelectedBtn').toggle($('.row-checkbox:checked').length > 0);
    }

    function exportToExcel() {
        TableUtils.exportToExcel('usersTable', 'users_export');
    }

    function deleteSelected() {
        const selected = $('.row-checkbox:checked').map(function () { return this.value; }).get();
        showConfirm({
            title: `Delete ${selected.length} user(s)?`,
            message: 'Are you sure you want to delete selected users?',
            confirmText: 'Yes, delete!',
            confirmButtonClass: 'btn-danger',
            onConfirm: function () {
                $.api.post('settings/users-delete-multiple', { ids: selected }).then(response => {
                    if (response.success) {
                        showToast('success', 'Deleted!', response.message);
                        loadUsers(currentPage);
                    } else {
                        showToast('error', 'Error', response.message);
                    }
                });
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
            title: 'Delete user?',
            message: 'Are you sure you want to delete this user?',
            confirmText: 'Yes, delete!',
            confirmButtonClass: 'btn-danger',
            onConfirm: function () {
                $.api.post('settings/user-delete', { id: id }).then(response => {
                    if (response.success) {
                        showToast('success', 'Deleted!', response.message);
                        loadUsers(currentPage);
                    } else {
                        showToast('error', 'Error', response.message);
                    }
                });
            }
        });
    }
</script>

<?php include '../../include/footer.php'; ?>
