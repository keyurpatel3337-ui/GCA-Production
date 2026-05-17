<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;

// Check if user has appropriate role
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "Manage Counsellors";
$page_breadcrumb = "Counsellors";

// Get search and filter parameters
$search = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? '';

// Get all counsellors with filters
try {
    $where_clauses = ["u.role_id = ?"];
    $params = [ROLE_COUNSELLOR];

    if ($search) {
        $where_clauses[] = "(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR s.name LIKE ? OR s.personal_email LIKE ?)";
        $search_param = "%$search%";
        $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param]);
    }

    if ($status_filter) {
        $where_clauses[] = "u.status = ?";
        $params[] = $status_filter;
    }

    $where_sql = implode(" AND ", $where_clauses);

    $stmt = $conn->prepare("SELECT u.*, r.role_name, s.name as staff_name, s.personal_email as staff_email
                            FROM tbl_users u 
                            INNER JOIN tbl_roles r ON u.role_id = r.id 
                            LEFT JOIN tbl_staff s ON u.id = s.user_id
                            WHERE $where_sql 
                            ORDER BY u.id DESC");
    $stmt->execute($params);
    $counsellors = $stmt->fetchAll();

    // Get all roles except Super Admin for the modal selection
    $role_stmt = $conn->prepare("SELECT id, role_name FROM tbl_roles WHERE id != ? ORDER BY role_name DESC");
    $role_stmt->execute([ROLE_SUPER_ADMIN]);
    $available_roles = $role_stmt->fetchAll();
} catch (PDOException $e) {
    logDatabaseError($e, "Fetch Counsellors for Admin");
    $counsellors = [];
    $available_roles = [];
}
?>

<?php include '../../include/header.php'; ?>
<?php include '../../include/navbar.php'; ?>
<?php include '../../include/sidebar.php'; ?>




<div class="container-fluid">

    <?php if (isset($_SESSION['success_msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4">
            <div class="d-flex align-items-center">
                <i class="fas fa-check-circle me-2 fs-5"></i>
                <div><?php echo gca_safe_html($_SESSION['success_msg']); ?></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success_msg']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_msg'])): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4">
            <div class="d-flex align-items-center">
                <i class="fas fa-exclamation-circle me-2 fs-5"></i>
                <div><?php echo gca_safe_html($_SESSION['error_msg']); ?></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error_msg']); ?>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0 fw-bold text-dark"><i class="fas fa-users-cog text-primary me-2"></i> Counsellor
                List</h5>
            <button type="button" class="btn btn-primary btn-sm rounded-pill px-3" data-bs-toggle="modal"
                data-bs-target="#addCounsellorModal">
                <i class="fas fa-user-plus me-1"></i> Add Counsellor
            </button>
        </div>

        <!-- Filters Section -->
        <div class="card-body bg-light border-top border-bottom py-3 px-4">
            <form id="filterForm" class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label small fw-bold text-uppercase text-muted">Search Counsellor</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0"><i
                                class="fas fa-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-start-0"
                            placeholder="Name, Email or Phone..." value="<?php echo htmlspecialchars($search ?? ''); ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-uppercase text-muted">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive
                        </option>
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-sm btn-dark px-3 rounded-pill">
                        <i class="fas fa-filter me-1"></i> Apply Filters
                    </button>
                    <?php if ($search || $status_filter): ?>
                        <a href="list.php" class="btn btn-sm btn-outline-secondary px-3 rounded-pill ms-1">
                            <i class="fas fa-times me-1"></i> Clear
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">ID</th>
                            <th>Name / Contact</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($counsellors)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="fas fa-user-slash fa-3x mb-3 opacity-50"></i>
                                        <p class="mb-0">No counsellors found.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($counsellors as $counsellor): ?>
                                <tr>
                                    <td class="ps-4 text-muted small fw-bold">#<?php echo $counsellor['id']; ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-initial rounded-circle bg-primary-subtle text-primary fw-bold me-3 d-flex align-items-center justify-content-center"
                                                style="width: 40px; height: 40px; font-size: 1.2rem;">
                                                <?php echo strtoupper(substr($counsellor['name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <h6 class="mb-0 fw-bold text-dark">
                                                    <?php echo htmlspecialchars($counsellor['staff_name'] ?: $counsellor['name'] ?? ''); ?>
                                                </h6>
                                                <div class="small text-muted mt-1">
                                                    <i class="fas fa-envelope me-1 text-secondary"></i>
                                                    <?php echo htmlspecialchars($counsellor['staff_email'] ?: $counsellor['email'] ?? ''); ?>
                                                </div>
                                                <?php if (!empty($counsellor['phone'])): ?>
                                                    <div class="small text-muted">
                                                        <i class="fas fa-phone me-1 text-secondary"></i>
                                                        <?php echo htmlspecialchars($counsellor['phone'] ?? ''); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($counsellor['status'] == 'active'): ?>
                                            <span
                                                class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill">
                                                <i class="fas fa-check-circle me-1"></i> Active
                                            </span>
                                        <?php else: ?>
                                            <span
                                                class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-1 rounded-pill">
                                                <i class="fas fa-ban me-1"></i> Inactive
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="small text-muted">
                                            <i class="far fa-clock me-1"></i>
                                            <?php echo $counsellor['last_login'] ? date('d M Y, h:i A', strtotime($counsellor['last_login'])) : 'Never Logged In'; ?>
                                        </div>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-sm btn-light text-primary"
                                                onclick="editCounsellor(<?php echo $counsellor['id']; ?>)" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-light text-danger ms-1"
                                                onclick="deleteCounsellor(<?php echo $counsellor['id']; ?>)" title="Delete">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white border-top-0 py-3">
            <div class="small text-muted">
                Total Counsellors: <strong><?php echo count($counsellors); ?></strong>
            </div>
        </div>
    </div>
</div>

<!-- Add Counsellor Modal -->
<div class="modal fade" id="addCounsellorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="fas fa-user-plus me-2"></i> Add New Counsellor</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <form id="addCounsellorForm">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase text-muted">User Role <span
                                class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i
                                    class="fas fa-user-tag text-muted"></i></span>
                            <select name="role_id" class="form-select border-start-0 ps-0" required>
                                <option value="">Select Role</option>
                                <?php foreach ($available_roles as $role): ?>
                                    <option value="<?php echo $role['id']; ?>" <?php echo $role['role_name'] == 'Counsellor' ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($role['role_name'] ?? ''); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase text-muted">Full Name <span
                                class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i
                                    class="fas fa-user text-muted"></i></span>
                            <input type="text" name="name" class="form-control border-start-0 ps-0"
                                placeholder="Enter full name" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase text-muted">Email Address <span
                                class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i
                                    class="fas fa-envelope text-muted"></i></span>
                            <input type="email" name="email" class="form-control border-start-0 ps-0"
                                placeholder="Enter email address" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase text-muted">Phone Number</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i
                                    class="fas fa-phone text-muted"></i></span>
                            <input type="text" name="phone" class="form-control border-start-0 ps-0"
                                placeholder="Enter phone number">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase text-muted">Password <span
                                class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i
                                    class="fas fa-lock text-muted"></i></span>
                            <input type="password" name="password" class="form-control border-start-0 ps-0"
                                placeholder="Create a password" required>
                        </div>
                        <div class="form-text small">Minimum 8 characters.</div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-1"></i> Save
                        Counsellor</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Counsellor Modal -->
<div class="modal fade" id="editCounsellorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold"><i class="fas fa-user-edit me-2"></i> Edit Counsellor</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <form id="editCounsellorForm">
                <input type="hidden" name="id" id="edit_user_id">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase text-muted">User Role <span
                                class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i
                                    class="fas fa-user-tag text-muted"></i></span>
                            <select name="role_id" id="edit_role_id" class="form-select border-start-0 ps-0" required>
                                <option value="">Select Role</option>
                                <?php foreach ($available_roles as $role): ?>
                                    <option value="<?php echo $role['id']; ?>">
                                        <?php echo htmlspecialchars($role['role_name'] ?? ''); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase text-muted">Full Name <span
                                class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i
                                    class="fas fa-user text-muted"></i></span>
                            <input type="text" name="name" id="edit_name" class="form-control border-start-0 ps-0"
                                placeholder="Enter full name" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase text-muted">Email Address <span
                                class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i
                                    class="fas fa-envelope text-muted"></i></span>
                            <input type="email" name="email" id="edit_email" class="form-control border-start-0 ps-0"
                                placeholder="Enter email address" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase text-muted">Phone Number</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i
                                    class="fas fa-phone text-muted"></i></span>
                            <input type="text" name="phone" id="edit_phone" class="form-control border-start-0 ps-0"
                                placeholder="Enter phone number">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase text-muted">Status <span
                                class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i
                                    class="fas fa-toggle-on text-muted"></i></span>
                            <select name="status" id="edit_status" class="form-select border-start-0 ps-0" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase text-muted">Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i
                                    class="fas fa-lock text-muted"></i></span>
                            <input type="password" name="password" class="form-control border-start-0 ps-0"
                                placeholder="Leave blank to keep current">
                        </div>
                        <div class="form-text small">Only fill if you want to change the password.</div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-dark px-4"><i class="fas fa-save me-1"></i> Update
                        Counsellor</button>
                </div>
            </form>
        </div>
    </div>

    <?php include '../../include/footer.php'; ?>

    <script>
        $(document).ready(function () {
            // Modal handled by global footer logic

            // Filter Form Submission
            $('#filterForm').on('submit', function (e) {
                e.preventDefault();
                const search = $(this).find('input[name="search"]').val();
                const status = $(this).find('select[name="status"]').val();
                window.location.href = `list.php?search=${encodeURIComponent(search)}&status=${encodeURIComponent(status)}`;
            });

            // Add Form Submission
            $('#addCounsellorForm').on('submit', function (e) {
                e.preventDefault();
                const formData = $(this).serialize();

                const submitBtn = $(this).find('button[type="submit"]');
                const originalText = submitBtn.html();
                submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Saving...');

                $.api.post('settings/user-save', formData).then(response => {
                    if (response.success) {
                        showToast('success', 'Success', response.message);
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showToast('error', 'Error', response.error || response.message);
                        submitBtn.prop('disabled', false).html(originalText);
                    }
                }).catch(() => {
                    showToast('error', 'Error', 'Failed to save counsellor. Please try again.');
                    submitBtn.prop('disabled', false).html(originalText);
                });
            });

            // Edit Form Submission
            $('#editCounsellorForm').on('submit', function (e) {
                e.preventDefault();
                const formData = $(this).serialize();

                const submitBtn = $(this).find('button[type="submit"]');
                const originalText = submitBtn.html();
                submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Updating...');

                $.api.post('settings/user-update', formData).then(response => {
                    if (response.success) {
                        showToast('success', 'Success', response.message);
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showToast('error', 'Error', response.error || response.message);
                        submitBtn.prop('disabled', false).html(originalText);
                    }
                }).catch(() => {
                    showToast('error', 'Error', 'Failed to update counsellor. Please try again.');
                    submitBtn.prop('disabled', false).html(originalText);
                });
            });
        });

        function deleteCounsellor(id) {
            if (confirm('Are you sure you want to delete this counsellor? This action cannot be undone.')) {
                $.api.post('settings/user-delete', { id: id }).then(response => {
                    if (response.success) {
                        showToast('success', 'Success', response.message);
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showToast('error', 'Error', response.error || response.message);
                    }
                }).catch(() => {
                    showToast('error', 'Error', 'Failed to delete counsellor. Please try again.');
                });
            }
        }

        function editCounsellor(id) {
            // Show loading state or modal immediately with placeholders
            const modal = new bootstrap.Modal(document.getElementById('editCounsellorModal'));
            
            $.api.get('settings/user-get', { id: id }).then(response => {
                if (response.success && response.data && response.data.user) {
                    const user = response.data.user;
                    $('#edit_user_id').val(user.id);
                    $('#edit_role_id').val(user.role_id);
                    $('#edit_name').val(user.name);
                    $('#edit_email').val(user.email);
                    $('#edit_phone').val(user.phone);
                    $('#edit_status').val(user.status);
                    
                    modal.show();
                } else {
                    showToast('error', 'Error', response.error || 'Failed to fetch user details.');
                }
            }).catch(() => {
                showToast('error', 'Error', 'Failed to fetch user details. Please try again.');
            });
        }
    </script>
