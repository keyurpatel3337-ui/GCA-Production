<?php

require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once __DIR__ . '/../../common/api_client.php';
require_once PAGINATION_FILE;

// Load counsellors data via API
$api = new APIClient();

// Fetch roles for the modal (excluding super admin role)
$rolesResponse = $api->get('settings/roles');
$roles = [];
if ($rolesResponse && isset($rolesResponse['success']) && $rolesResponse['success']) {
    $allRoles = $rolesResponse['data']['roles'] ?? [];
    // Filter out super admin role (id = 1)
    foreach ($allRoles as $role) {
        if ($role['id'] != 1) {
            $roles[] = $role;
        }
    }
}

// Handle POST pagination/filter state
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $filters = [
        'page' => $_POST['page'] ?? 1,
        'per_page' => $_POST['per_page'] ?? ($_SESSION['counsellors_pagination']['per_page'] ?? 10),
        'search' => $_POST['search'] ?? ($_SESSION['counsellors_pagination']['search'] ?? '')
    ];

    // If search changed, reset page to 1 (simple logic: if POST has search, it's a search submit or page change)
    // Actually, distinct buttons would clarify. But usually if I change page, I send existing search.
    // If I change search text and hit search, I probably send page=1 (or default).

    $_SESSION['counsellors_pagination'] = $filters;
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Get pagination from session
$paginationState = $_SESSION['counsellors_pagination'] ?? [
    'page' => 1,
    'per_page' => 10,
    'search' => ''
];

$page = $paginationState['page'];
$perPage = $paginationState['per_page'];
$search = $paginationState['search'];

// Load counsellors list via API
$params = [
    'page' => $page,
    'per_page' => $perPage,
    'search' => $search
];

$counsellorsResponse = $api->get('dashboard/counsellors', $params);
if ($counsellorsResponse && isset($counsellorsResponse['success']) && $counsellorsResponse['success']) {
    $data = $counsellorsResponse['data'] ?? [];
    $counsellors = $data['counsellors'] ?? [];
    $pagination = $data['pagination'] ?? [];

    // Update local vars from response pagination source of truth if available
    $page = $pagination['current_page'] ?? $page;
    $perPage = $pagination['per_page'] ?? $perPage;
    $totalRecords = $pagination['total_records'] ?? 0;
    $totalPages = $pagination['total_pages'] ?? 1;
} else {
    $counsellors = [];
    $data = [];
    $pagination = [];
    $totalRecords = 0;
    $totalPages = 1;
}

include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>

<div class="app-content">
    <div class="container-fluid">
        

        <!-- Search and Filter Section -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="bg-white p-3 rounded shadow-sm">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h5 class="mb-0">All Counsellors</h5>
                        <form method="POST" class="d-flex gap-2 align-items-center flex-wrap">
                            <input type="hidden" name="page" value="1">
                            <select name="per_page" class="form-select form-select-sm css-counsellors-dc251b" onchange="this.form.submit()">
                                <option value="10" <?php echo $perPage == 10 ? 'selected' : ''; ?>>10</option>
                                <option value="25" <?php echo $perPage == 25 ? 'selected' : ''; ?>>25</option>
                                <option value="50" <?php echo $perPage == 50 ? 'selected' : ''; ?>>50</option>
                                <option value="100" <?php echo $perPage == 100 ? 'selected' : ''; ?>>100</option>
                            </select>
                            <input type="text" name="search" class="form-control form-control-sm"
                                placeholder="Search counsellors..." value="<?php echo htmlspecialchars($search ?? ''); ?>"
                                style="width: 250px;">
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="fas fa-search"></i> Search
                            </button>
                            <?php if (!empty($search)): ?>
                                <a href="counsellors.php" class="btn btn-sm btn-secondary" onclick="resetSearch(event)">
                                    <i class="fas fa-times"></i> Clear
                                </a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <?php
                if (empty($counsellors)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> No counsellors found.
                    </div>
                    <?php
                else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Students</th>
                                    <th>Appointments</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                foreach ($counsellors as $counsellor): ?>
                                    <tr>
                                        <td><?php
                                        echo htmlspecialchars($counsellor['name'] ?? ''); ?></td>
                                        <td><?php
                                        echo htmlspecialchars($counsellor['email'] ?? ''); ?></td>
                                        <td><?php
                                        echo htmlspecialchars($counsellor['phone'] ?? 'N/A'); ?></td>
                                        <td>
                                            <span class="badge bg-info text-dark">
                                                <?php
                                                echo $counsellor['total_students']; ?> Students
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-warning text-dark">
                                                <?php
                                                echo $counsellor['total_appointments']; ?> Appointments
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            if ($counsellor['status'] == 'active'): ?>
                                                <span class="badge bg-success">Active</span>
                                                <?php
                                            else: ?>
                                                <span class="badge bg-danger">Inactive</span>
                                                <?php
                                            endif; ?>
                                        </td>
                                        <td>
                                            <form method="GET" action="../students/students.php" class="css-counsellors-f8e39d">
                                                <input type="hidden" name="counsellor_id"
                                                    value="<?php echo $counsellor['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-info" title="View Students">
                                                    <i class="fas fa-users"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php
                                endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php
                endif; ?>

                <!-- Pagination -->
                <?php if (!empty($counsellors)): ?>
                    <div class="mt-3">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <?php
                                echo renderPaginationPost($page, $totalPages, 2, $perPage);
                                ?>
                            </div>
                            <div class="text-muted">
                                <small>
                                    <?php echo getPaginationInfo($page, $perPage, $totalRecords); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add Counsellor Modal -->
<div class="modal fade" id="addCounsellorModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Add New Counsellor</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addCounsellorForm">
                <div class="modal-body">
                    <div class="form-group mb-3">
                        <label>Full Name *</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="form-group mb-3">
                        <label>Email *</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="form-group mb-3">
                        <label>Role *</label>
                        <select name="role_id" class="form-control" required>
                            <option value="">Select Role</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?php echo $role['id']; ?>">
                                    <?php echo htmlspecialchars($role['role_name'] ?? ''); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group mb-3">
                        <label>Phone</label>
                        <input type="text" name="phone" class="form-control">
                    </div>
                    <div class="form-group mb-3">
                        <label>Password *</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="form-group mb-3">
                        <label>Status</label>
                        <select name="status" class="form-control">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Counsellor</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
include '../../include/footer.php'; ?>

<script>
    $(document).ready(function () {
        // Move modal to body to prevent z-index issues
        $('#addCounsellorModal').appendTo("body");
    });

    function resetSearch(e) {
        e.preventDefault();
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = window.location.href;

        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'search';
        input.value = '';
        form.appendChild(input);

        const page = document.createElement('input');
        page.type = 'hidden';
        page.name = 'page';
        page.value = '1';
        form.appendChild(page);

        document.body.appendChild(form);
        form.submit();
    }

    $('#addCounsellorForm').on('submit', function (e) {
        e.preventDefault();
        const form = $(this);

        // Convert form data to object
        const formData = {};
        form.serializeArray().forEach(item => {
            formData[item.name] = item.value;
        });

        $.api.post('settings/user-save', formData).then(response => {
            if (response.success) {
                showToast('success', 'Success', response.message);
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast('error', 'Error', response.error || response.message);
            }
        }).catch(error => {
            showToast('error', 'Error', error.message || 'An error occurred. Please try again.');
        });
    });
</script>