<?php

/**
 * Unified Students Management
 * Consolidated component for All Students, Registered (Pending Token), and Enrolled views
 */

require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once __DIR__ . '/../../common/api_client.php';
require_once PORTAL_GLOBALVARIABLE;
require_once OPERATION_FILE;
require_once HELPER_FLASH_MESSAGE;
require_once PAGINATION_FILE;
require_once __DIR__ . '/../../common/security_output.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/format_helper.php';

// Check access
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_COUNSELLOR) && !hasRole(ROLE_ACCOUNTANT) && !hasRole(ROLE_ESTABLISHMENT) && !hasRole(ROLE_RECEPTION) && !hasRole(ROLE_COMPUTER_OPERATOR)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$api = new APIClient();

// Determine current view: all (default), registered, enrolled
$currentView = $_GET['view'] ?? 'all';
if (!in_array($currentView, ['all', 'registered', 'enrolled'])) {
    $currentView = 'all';
}

$page_titles = [
    'all' => 'Master Student List',
    'registered' => 'Registered Students (Pending Token Fee)',
    'enrolled' => 'Enrolled Students'
];
$page_title = $page_titles[$currentView];
$page_breadcrumb = "Students - " . ucwords($currentView);

// Handle POST filters (session-based)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sessionKey = "students_{$currentView}_filters";
    if (isset($_POST['clear_filters'])) {
        unset($_SESSION[$sessionKey]);
    }
    else {
        $existingFilters = $_SESSION[$sessionKey] ?? [];
        $_SESSION[$sessionKey] = array_merge($existingFilters, $_POST);
        // Reset page if filters changed (and it's not a direct pagination request)
        if (!isset($_POST['page'])) {
            $_SESSION[$sessionKey]['page'] = 1;
        }
    }
    header('Location: ' . $_SERVER['PHP_SELF'] . '?view=' . $currentView);
    exit;
}

// Get filters from session and merge with defaults to ensure all keys exist
$sessionKey = "students_{$currentView}_filters";
$defaultFilters = [
    'search' => '',
    'course' => '',
    'board' => '',
    'academic_year' => '',
    'group' => '',
    'medium' => '',
    'school' => '',
    'gender' => '',
    'hostel_required' => '',
    'transport_required' => '',
    'counsellor_id' => '',
    'campus' => '',
    'division' => '',
    'page' => 1,
    'per_page' => ($currentView === 'all' ? 10 : 15),
    'sort' => 'id',
    'order' => 'asc'
];

if ($currentView === 'enrolled') {
    $defaultFilters['payment_status'] = '';
}

// Merge session filters into defaults to ensure no missing keys
$filters = array_merge($defaultFilters, $_SESSION[$sessionKey] ?? []);

// Allow GET parameters to override filters (for direct dashboard links)
foreach ($defaultFilters as $key => $value) {
    if (isset($_GET[$key])) {
        $filters[$key] = $_GET[$key];
        // Persist to session, but check if it's explicitly cleared
        if ($_GET[$key] === '') {
            unset($_SESSION[$sessionKey][$key]);
        }
        else {
            $_SESSION[$sessionKey][$key] = $_GET[$key];
        }
    }
}

// Check for explicit clear flags
if (isset($_GET['clear_counsellor'])) {
    $filters['counsellor_id'] = '';
    $_SESSION[$sessionKey]['counsellor_id'] = '';
}

// Calculate active filters for display
$activeFilters = [];
$ignoreFilters = ['page', 'per_page', 'sort', 'order'];
foreach ($filters as $key => $value) {
    if (in_array($key, $ignoreFilters)) continue;
    if (isset($defaultFilters[$key]) && $value !== $defaultFilters[$key] && $value !== '') {
        $activeFilters[$key] = $value;
    }
}
$activeFiltersCount = count($activeFilters);

// Map views to API endpoints
$endpoints = [
    'all' => 'students/list',
    'registered' => 'students/registered',
    'enrolled' => 'students/enrolled'
];

$response = $api->get($endpoints[$currentView], $filters);

$students = [];
$pagination = [];
$totalRecords = 0;
$totalPages = 1;
$dropdowns = [];

if ($response && isset($response['success']) && $response['success']) {
    $students = $response['data']['students'] ?? [];
    $pagination = $response['data']['pagination'] ?? [];
    $totalRecords = $pagination['total_records'] ?? count($students);
    $totalPages = $pagination['total_pages'] ?? 1;
    $dropdowns = $response['data']['filters'] ?? [];
}
else {
    set_flash_message('error', $response['error'] ?? 'Failed to load students list');
}

include '../../include/header.php';
?>
<link rel="stylesheet" href="<?php echo PORTAL_URL; ?>/assets/css/modules/students/students.css">
<?php
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>

<?php // Excel exports handled via export.php (server-side) ?>

<div class="content-wrapper">
    <section class="content">
        <div class="container-fluid">
            <!-- Action Buttons for Admins -->
            <?php if (hasRole(ROLE_SUPER_ADMIN) || hasRole(ROLE_PRINCIPLE) || hasRole(ROLE_RECEPTION) || hasRole(ROLE_ESTABLISHMENT) || hasRole(ROLE_COMPUTER_OPERATOR)): ?>
                <div class="d-flex justify-content-end gap-2 mb-3">
                    <a href="bulk-student-edit.php" id="bulkEditBtn" class="btn btn-warning">
                        <i class="fas fa-edit"></i> Bulk Edit
                    </a>
                    <a href="bulk-send-credentials.php" class="btn btn-info">
                        <i class="fas fa-envelope"></i> Bulk Send Credentials
                    </a>
                    <a href="upload.php" class="btn btn-success">
                        <i class="fas fa-file-upload"></i> Upload Students
                    </a>
                    <a href="add.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add New Student
                    </a>
                </div>
            <?php endif; ?>

            <?php if (!empty($filters['counsellor_id'])): ?>
                <div class="alert alert-info py-2 mb-3 d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-user-tie me-2"></i>
                        Viewing students assigned to counsellor:
                        <strong><?php echo htmlspecialchars($response['data']['applied_filters']['counsellor_name'] ?? 'Selected Counsellor'); ?></strong>
                    </div>
                    <a href="?view=<?php echo $currentView; ?>&clear_counsellor=1" class="btn btn-sm btn-outline-info">
                        <i class="fas fa-times"></i> Clear Filter
                    </a>
                </div>
            <?php endif; ?>

            <!-- Filter Section -->
            <div class="card shadow-sm border-0 mb-4" style="position: relative; z-index: 10;">
                <div class="card-header <?php echo ($activeFiltersCount > 0) ? 'bg-primary-subtle' : 'bg-white'; ?> py-3 border-0">
                    <h5 class="card-title mb-0 fw-bold">
                        <i class="fas fa-filter me-2 text-primary"></i>Filters
                        <?php if ($activeFiltersCount > 0): ?>
                            <span class="badge bg-primary ms-2"><?php echo $activeFiltersCount; ?> Active</span>
                        <?php endif; ?>
                    </h5>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-bs-toggle="collapse"
                            data-bs-target="#filterCollapse">
                            <i class="fas fa-minus"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body collapse <?php echo ($activeFiltersCount > 0) ? 'show' : ''; ?>" id="filterCollapse">
                    <form method="POST">
                        <?php if (!empty($filters['counsellor_id'])): ?>
                            <input type="hidden" name="counsellor_id"
                                value="<?php echo htmlspecialchars($filters['counsellor_id'] ?? ''); ?>">
                        <?php endif; ?>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Search Student</label>
                                <input type="text" name="search" class="form-control"
                                    placeholder="Name, ID, Aadhaar or Mobile..."
                                    value="<?php echo htmlspecialchars($filters['search'] ?? ''); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Academic Year</label>
                                <select name="academic_year" class="form-select">
                                    <option value="">All Years</option>
                                    <?php foreach ($dropdowns['academic_years'] ?? [] as $y): ?>
                                        <option value="<?php echo $y['id']; ?>" <?php echo(($filters['academic_year'] ?? '') == $y['id']) ? 'selected' : ''; ?>>
                                            <?php echo $y['year_name']; ?>
                                        </option>
                                    <?php
endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Board</label>
                                <select name="board" class="form-select">
                                    <option value="">All Boards</option>
                                    <?php foreach ($dropdowns['boards'] ?? [] as $b): ?>
                                        <option value="<?php echo $b['id']; ?>" <?php echo(($filters['board'] ?? '') == $b['id']) ? 'selected' : ''; ?>>
                                            <?php echo $b['board_name']; ?>
                                        </option>
                                    <?php
endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">School</label>
                                <select name="school" class="form-select">
                                    <option value="">All Schools</option>
                                    <?php foreach ($dropdowns['schools'] ?? [] as $s): ?>
                                        <option value="<?php echo $s['id']; ?>" <?php echo(($filters['school'] ?? '') == $s['id']) ? 'selected' : ''; ?>>
                                            <?php echo $s['school_name']; ?>
                                        </option>
                                    <?php
endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Gender</label>
                                <select name="gender" class="form-select">
                                    <option value="">All</option>
                                    <option value="Male" <?php echo(($filters['gender'] ?? '') === 'Male') ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo(($filters['gender'] ?? '') === 'Female') ? 'selected' : ''; ?>>Female</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Standard</label>
                                <select name="course" class="form-select">
                                    <option value="">All Standards</option>
                                    <option value="11th" <?php echo(($filters['course'] ?? '') == '11th') ? 'selected' : ''; ?>>11th</option>
                                    <option value="12th" <?php echo(($filters['course'] ?? '') == '12th') ? 'selected' : ''; ?>>12th</option>
                                    <option value="Reneet" <?php echo(($filters['course'] ?? '') == 'Reneet') ? 'selected' : ''; ?>>Reneet</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Group</label>
                                <select name="group" class="form-select">
                                    <option value="">All Groups</option>
                                    <?php foreach ($dropdowns['groups'] ?? [] as $g): ?>
                                        <option value="<?php echo $g['id']; ?>" <?php echo(($filters['group'] ?? '') == $g['id']) ? 'selected' : ''; ?>>
                                            <?php echo $g['group_name']; ?>
                                        </option>
                                    <?php
endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Medium</label>
                                <select name="medium" class="form-select">
                                    <option value="">All Mediums</option>
                                    <?php foreach ($dropdowns['mediums'] ?? [] as $m): ?>
                                        <option value="<?php echo $m['id']; ?>" <?php echo(($filters['medium'] ?? '') == $m['id']) ? 'selected' : ''; ?>>
                                            <?php echo $m['medium_name'] ?? $m['name']; ?>
                                        </option>
                                    <?php
endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Hostel Required</label>
                                <select name="hostel_required" class="form-select">
                                    <option value="">All</option>
                                    <option value="Yes" <?php echo (($filters['hostel_required'] ?? '') === 'Yes') ? 'selected' : ''; ?>>Yes</option>
                                    <option value="No" <?php echo (($filters['hostel_required'] ?? '') === 'No') ? 'selected' : ''; ?>>No</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Transport Required</label>
                                <select name="transport_required" class="form-select">
                                    <option value="">All</option>
                                    <option value="Yes" <?php echo (($filters['transport_required'] ?? '') === 'Yes') ? 'selected' : ''; ?>>Yes</option>
                                    <option value="No" <?php echo (($filters['transport_required'] ?? '') === 'No') ? 'selected' : ''; ?>>No</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Campus</label>
                                <select name="campus" class="form-select">
                                    <option value="">All Campuses</option>
                                    <?php foreach ($dropdowns['campuses'] ?? [] as $cp): ?>
                                        <option value="<?php echo $cp['id']; ?>" <?php echo(($filters['campus'] ?? '') == $cp['id']) ? 'selected' : ''; ?>>
                                            <?php echo $cp['campus_name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Division</label>
                                <select name="division" class="form-select">
                                    <option value="">All Divisions</option>
                                    <option value="none" <?php echo(($filters['division'] ?? '') === 'none') ? 'selected' : ''; ?>>Not Assigned</option>
                                    <?php foreach ($dropdowns['divisions'] ?? [] as $d): ?>
                                        <option value="<?php echo $d['id']; ?>" <?php echo(($filters['division'] ?? '') == $d['id']) ? 'selected' : ''; ?>>
                                            <?php echo $d['division_name']; ?>
                                        </option>
                                    <?php
                                    endforeach; ?>
                                </select>
                            </div>

                            <?php if ($currentView === 'enrolled'): ?>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold">Payment Status</label>
                                    <select name="payment_status" class="form-select">
                                        <option value="">Status</option>
                                        <option value="paid" <?php echo(($filters['payment_status'] ?? '') === 'paid') ? 'selected' : ''; ?>>Paid</option>
                                        <option value="pending" <?php echo(($filters['payment_status'] ?? '') === 'pending') ? 'selected' : ''; ?>>Pending</option>
                                    </select>
                                </div>
                            <?php endif; ?>
                            
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Sort By</label>
                                <select name="sort" class="form-select" data-placeholder="Sort By">
                                    <option value="id" <?php echo(($filters['sort'] ?? '') === 'id') ? 'selected' : ''; ?>>ID</option>
                                    <option value="name" <?php echo(($filters['sort'] ?? '') === 'name') ? 'selected' : ''; ?>>Name</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Sort Order</label>
                                <select name="order" class="form-select" data-placeholder="Order">
                                    <option value="asc" <?php echo(($filters['order'] ?? '') === 'asc') ? 'selected' : ''; ?>>Ascending</option>
                                    <option value="desc" <?php echo(($filters['order'] ?? '') === 'desc') ? 'selected' : ''; ?>>Descending</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Per Page</label>
                                <select name="per_page" class="form-select" data-placeholder="Per Page">
                                    <option value="10" <?php echo(($filters['per_page'] ?? '') == 10) ? 'selected' : ''; ?>>10</option>
                                    <option value="20" <?php echo(($filters['per_page'] ?? '') == 20) ? 'selected' : ''; ?>>20</option>
                                    <option value="50" <?php echo(($filters['per_page'] ?? '') == 50) ? 'selected' : ''; ?>>50</option>
                                    <option value="100" <?php echo(($filters['per_page'] ?? '') == 100) ? 'selected' : ''; ?>>100</option>
                                </select>
                            </div>

                            <div class="col-md-auto ms-auto d-flex gap-2">
                                <button type="submit" class="btn btn-primary px-4">
                                    <i class="fas fa-search me-1"></i> Apply Filters
                                </button>
                                <button type="submit" name="clear_filters" value="1" class="btn btn-light"
                                    title="Clear All">
                                    <i class="fas fa-undo"></i>
                                </button>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-success dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="Export">
                                        <i class="fas fa-file-export me-1"></i> Export
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <a class="dropdown-item" href="export.php?view=<?php echo $currentView; ?>&format=excel">
                                                <i class="fas fa-file-excel text-success me-2"></i> Export Excel
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="export.php?view=<?php echo $currentView; ?>&format=pdf">
                                                <i class="fas fa-file-pdf text-danger me-2"></i> Export PDF
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Active Filter Tokens -->
            <?php if ($activeFiltersCount > 0): ?>
                <div class="mb-3 d-flex flex-wrap gap-2 align-items-center">
                    <span class="small fw-bold text-muted"><i class="fas fa-tags me-1"></i>Active:</span>
                    <?php
                    foreach ($activeFilters as $key => $value) {
                        $label = ucwords(str_replace('_', ' ', $key));
                        $displayValue = $value;

                        // Try to get pretty name for IDs
                        if ($key === 'academic_year') {
                            foreach ($dropdowns['academic_years'] ?? [] as $item) if ($item['id'] == $value) $displayValue = $item['year_name'];
                        } elseif ($key === 'board') {
                            foreach ($dropdowns['boards'] ?? [] as $item) if ($item['id'] == $value) $displayValue = $item['board_name'];
                        } elseif ($key === 'school') {
                            foreach ($dropdowns['schools'] ?? [] as $item) if ($item['id'] == $value) $displayValue = $item['school_name'];
                        } elseif ($key === 'course') {
                            if ($value === '11th' || $value === '12th' || $value === 'Reneet') {
                                $displayValue = $value;
                            } else {
                                foreach ($dropdowns['courses'] ?? [] as $item) if ($item['id'] == $value) $displayValue = $item['course_name'] ?? $item['name'];
                            }
                        } elseif ($key === 'group') {
                            foreach ($dropdowns['groups'] ?? [] as $item) if ($item['id'] == $value) $displayValue = $item['group_name'];
                        } elseif ($key === 'medium') {
                            foreach ($dropdowns['mediums'] ?? [] as $item) if ($item['id'] == $value) $displayValue = $item['medium_name'] ?? $item['name'];
                        } elseif ($key === 'division') {
                            foreach ($dropdowns['divisions'] ?? [] as $item) if ($item['id'] == $value) $displayValue = $item['division_name'];
                        } elseif ($key === 'campus') {
                            foreach ($dropdowns['campuses'] ?? [] as $item) if ($item['id'] == $value) $displayValue = $item['campus_name'];
                        }

                        echo '<span class="badge bg-white text-primary border border-primary-subtle d-flex align-items-center py-1 px-2">';
                        echo '<strong>' . htmlspecialchars($label ?? '') . ':</strong> ' . htmlspecialchars($displayValue ?? '');
                        echo '<button type="button" class="btn-close ms-2" style="font-size: 0.5rem;" onclick="clearIndividualFilter(\'' . $key . '\')" title="Remove"></button>';
                        echo '</span>';
                    }
                    ?>
                    <form method="POST" style="display:inline;">
                        <button type="submit" name="clear_filters" value="1" class="btn btn-link btn-sm text-danger p-0 ms-2 text-decoration-none">
                            <i class="fas fa-times-circle"></i> Clear All
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- List Card -->
            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-students table-hover align-middle mb-0" id="studentsTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Student Details</th>
                                    <th>School / Board</th>
                                    <th>Campus</th>
                                    <th>Standard/Grade</th>
                                    <?php if ($currentView === 'enrolled'): ?>
                                        <th>Div / Roll</th>
                                        <th>Fees Paid</th>
                                        <th>Balance</th>
                                    <?php
elseif ($currentView === 'registered'): ?>
                                        <th>Admission Date</th>
                                        <th>Status</th>
                                    <?php
else: ?>
                                        <th>Academic Year</th>
                                        <th>Status</th>
                                    <?php
endif; ?>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($students)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-5">
                                            <i class="fas fa-users-slash text-muted fa-3x mb-3"></i>
                                            <p class="text-muted">No students found matching current filters.</p>
                                        </td>
                                    </tr>
                                <?php
else:
    foreach ($students as $row): ?>
                                        <tr>
                                            <td class="small fw-bold text-muted">
                                                <?php echo $row['id'] ?? $row['registration_id']; ?>
                                            </td>
                                            <td>
                                                <span class="fw-bold d-block text-primary">
                                                    <?php echo $row['full_name']; ?>
                                                </span>
                                                <small class="text-muted"><i class="fas fa-mobile-alt me-1"></i>
                                                    <?php echo $row['mob'] ?? $row['phone']; ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="small fw-bold">
                                                    <?php echo htmlspecialchars($row['school_name'] ?? $row['school'] ?? 'N/A'); ?>
                                                </div>
                                                <div class="small text-muted">
                                                    <?php echo htmlspecialchars($row['board_name'] ?? $row['board'] ?? 'N/A'); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="fw-bold text-dark small">
                                                    <i class="fas fa-university me-1 text-primary"></i>
                                                    <?php echo htmlspecialchars($row['campus_name'] ?? 'N/A'); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark border">
                                                    <?php echo $row['course_name'] ?? $row['course']; ?>
                                                </span>
                                                <small class="text-muted d-block mt-1">
                                                    <?php echo $row['group_name'] ?? ''; ?>
                                                </small>
                                            </td>

                                            <?php if ($currentView === 'enrolled'): ?>
                                                <td>
                                                    <span class="badge bg-success-subtle text-success border-success-subtle">
                                                        <?php echo $row['division_name'] ?? '-'; ?>
                                                    </span>
                                                    <div class="mt-1 small fw-bold">Roll: <?php echo $row['roll_no'] ?? '-'; ?>
                                                    </div>
                                                </td>
                                                <td class="text-success fw-bold">₹
                                                    <?php echo formatIndianCurrency($row['fees_paid'] ?? 0); ?>
                                                </td>
                                                <td class="text-danger fw-bold">₹
                                                    <?php echo formatIndianCurrency($row['fees_pending'] ?? 0); ?>
                                                </td>
                                            <?php
        elseif ($currentView === 'registered'): ?>
                                                <td class="small">
                                                    <?php echo date('d M Y', strtotime($row['admission_confirmed_date'] ?? 'now')); ?>
                                                </td>
                                                <td><span class="badge bg-warning text-dark status-badge">PENDING TOKEN</span></td>
                                            <?php
        else: ?>
                                                <td class="small">
                                                    <?php echo $row['academic_year'] ?? '-'; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($row['admission_confirmed'])): ?>
                                                        <span class="badge bg-success status-badge">CONFIRMED</span>
                                                    <?php
            else: ?>
                                                        <span class="badge bg-secondary status-badge">PENDING</span>
                                                    <?php
            endif; ?>
                                                </td>
                                            <?php
        endif; ?>

                                            <td class="text-center">
                                                <div class="btn-group btn-group-sm">
                                                    <!-- View Details -->
                                                    <a href="details.php?id=<?php echo $row['id'] ?? $row['registration_id']; ?>"
                                                        class="btn btn-info" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </a>

                                                    <!-- Ledger/Payments -->
                                                    <a href="../reports/financial/student-ledger.php?student_id=<?php echo $row['id'] ?? $row['registration_id']; ?>"
                                                        class="btn btn-outline-info" title="Ledger">
                                                        <i class="fas fa-book"></i>
                                                    </a>

                                                    <!-- Record Counselling Session -->
                                                    <?php if (!hasRole(ROLE_ACCOUNTANT)): ?>
                                                        <a href="create-session.php?student_id=<?php echo $row['id'] ?? $row['registration_id']; ?>"
                                                            class="btn btn-outline-success" title="Record Session">
                                                            <i class="fas fa-clipboard-list"></i>
                                                        </a>
                                                    <?php
        endif; ?>

                                                    <?php if ($currentView === 'registered'): ?>
                                                        <a href="../payments/add-payment.php?student_id=<?php echo $row['id']; ?>"
                                                            class="btn btn-warning" title="Collect Token">
                                                            <i class="fas fa-money-bill-wave"></i>
                                                        </a>
                                                    <?php
        endif; ?>

                                                    <?php if ($currentView === 'enrolled' && (hasRole(ROLE_SUPER_ADMIN) || hasRole(ROLE_PRINCIPLE) || hasRole(ROLE_ESTABLISHMENT) || hasRole(ROLE_COMPUTER_OPERATOR))): ?>
                                                        <a href="upload-documents.php?id=<?php echo $row['id'] ?? $row['registration_id']; ?>"
                                                            class="btn btn-primary" title="Upload Documents">
                                                            <i class="fas fa-file-upload"></i>
                                                        </a>
                                                    <?php
        endif; ?>

                                                    <!-- Admission Actions -->
                                                    <?php if (hasRole(ROLE_SUPER_ADMIN) || hasRole(ROLE_PRINCIPLE) || hasRole(ROLE_ESTABLISHMENT) || hasRole(ROLE_COMPUTER_OPERATOR)): ?>
                                                        <?php if (!empty($row['admission_confirmed']) || $currentView === 'enrolled'): ?>
                                                            <a href="../../scripts/generate_pdf.php?id=<?php echo $row['id'] ?? $row['registration_id']; ?>"
                                                                class="btn btn-outline-primary" target="_blank" title="Download Letter">
                                                                <i class="fas fa-file-download"></i>
                                                            </a>
                                                        <?php
            elseif ($currentView !== 'enrolled'): ?>
                                                            <a href="admission-confirm.php?id=<?php echo $row['id'] ?? $row['registration_id']; ?>"
                                                                class="btn btn-warning" title="Confirm Admission">
                                                                <i class="fas fa-check-circle"></i>
                                                            </a>
                                                        <?php
            endif; ?>
                                                    <?php
        endif; ?>

                                                    <!-- Edit Profile -->
                                                    <a href="edit-student.php?id=<?php echo $row['id'] ?? $row['registration_id']; ?>"
                                                        class="btn btn-outline-primary" title="Edit Profile">
                                                        <i class="fas fa-user-edit"></i>
                                                    </a>

                                                    <!-- Delete Action -->
                                                    <?php if (hasRole(ROLE_SUPER_ADMIN) || hasRole(ROLE_PRINCIPLE)): ?>
                                                        <button type="button" class="btn btn-outline-danger"
                                                            onclick="deleteStudent(<?php echo $row['id'] ?? $row['registration_id']; ?>)"
                                                            title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    <?php
        endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php
    endforeach;
endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <!-- Pagination Footer -->
                <?php if ($totalPages > 1 || $totalRecords > 0): ?>
                    <div class="card-footer bg-transparent border-top p-3">
                        <?php echo renderPaginationPost($pagination['current_page'], $pagination['total_pages'], 2, $filters['per_page'], [], $totalRecords, 'students'); ?>
                    </div>
                <?php
endif; ?>
            </div>
        </div>
    </section>
</div>

<script>
    // exportToExcel function removed as export is now server-side

    function deleteStudent(id) {
        if (confirm("Are you sure you want to delete this student record? This action cannot be undone.")) {
            window.location.href = 'appointment-delete.php?id=' + id + '&redirect=students.php';
        }
    }

    function clearIndividualFilter(key) {
        // Create a hidden form and submit it
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = window.location.href;
        
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = '';
        form.appendChild(input);
        
        document.body.appendChild(form);
        form.submit();
    }

    $(document).ready(function() {
        // Select2 removed as per user request
    });
</script>

<?php include '../../include/footer.php'; ?>