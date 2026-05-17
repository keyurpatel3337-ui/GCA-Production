<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once __DIR__ . '/../../common/api_client.php';
require_once PAGINATION_FILE;
require_once __DIR__ . '/../../../common/helpers/format_helper.php';

// Auth check
if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE])) {
    set_flash_message('error', 'Unauthorized: You do not have permission to access this page.');
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

// Handle filters from POST or session
if (isset($_POST['academic_year'])) {
    $_SESSION['feeconfig_year_filter'] = $_POST['academic_year'];
} elseif (isset($_POST['school_id'])) {
    $_SESSION['feeconfig_school_filter'] = $_POST['school_id'];
} elseif (isset($_POST['clear_filter'])) {
    unset($_SESSION['feeconfig_year_filter']);
    unset($_SESSION['feeconfig_school_filter']);
    unset($_SESSION['feeconfig_pagination']);
}

// Handle page and per_page from POST (pagination clicks)
if (isset($_POST['page']) || isset($_POST['per_page'])) {
    $_SESSION['feeconfig_pagination'] = [
        'page' => isset($_POST['page']) ? (int) $_POST['page'] : ($_SESSION['feeconfig_pagination']['page'] ?? 1),
        'per_page' => isset($_POST['per_page']) ? (int) $_POST['per_page'] : ($_SESSION['feeconfig_pagination']['per_page'] ?? 10)
    ];
}

$api = new APIClient();

// Build request params from session
$requestParams = [];
$paginationSession = $_SESSION['feeconfig_pagination'] ?? [];
$requestParams['page'] = $paginationSession['page'] ?? 1;
$requestParams['per_page'] = $paginationSession['per_page'] ?? 10;

// Add search from POST or previous session
if (isset($_POST['search'])) {
    $requestParams['search'] = $_POST['search'];
    $_SESSION['feeconfig_search'] = $_POST['search'];
} elseif (isset($_SESSION['feeconfig_search'])) {
    $requestParams['search'] = $_SESSION['feeconfig_search'];
}

// Add filters from session if exists
if (isset($_SESSION['feeconfig_year_filter'])) {
    $requestParams['academic_year'] = $_SESSION['feeconfig_year_filter'];
}
if (isset($_SESSION['feeconfig_school_filter'])) {
    $requestParams['school_id'] = $_SESSION['feeconfig_school_filter'];
}

$response = $api->get('fees/config', $requestParams);

if ($response && isset($response['success']) && $response['success']) {
    $fee_configs = $response['data']['fee_configs'] ?? [];
    $academic_years = $response['data']['academic_years'] ?? [];
    $courses = $response['data']['courses'] ?? [];
    $mediums = $response['data']['mediums'] ?? [];
    $groups = $response['data']['groups'] ?? [];
    $schools = $response['data']['schools'] ?? [];
    $split_labels = $response['data']['split_labels'] ?? ['GHSS', 'SGM', 'MST', 'GCA'];
    $isSuperAdmin = $response['data']['is_super_admin'] ?? false;
    $pagination = $response['data']['pagination'] ?? [];
    $page = $pagination['current_page'] ?? 1;
    $perPage = $pagination['per_page'] ?? 10;
    $totalRecords = $pagination['total_records'] ?? count($fee_configs);
    $totalPages = $pagination['total_pages'] ?? ceil($totalRecords / $perPage);
    $search = $response['data']['applied_filters']['search'] ?? '';
    $activeYearFilter = $response['data']['applied_filters']['academic_year'] ?? ($_SESSION['feeconfig_year_filter'] ?? '');
    $activeSchoolFilter = $response['data']['applied_filters']['school_id'] ?? ($_SESSION['feeconfig_school_filter'] ?? '');
} else {
    $fee_configs = $academic_years = $courses = $mediums = $groups = $schools = [];
    $split_labels = ['GHSS', 'SGM', 'MST', 'GCA'];
    $isSuperAdmin = false;
    $page = 1;
    $perPage = 10;
    $totalRecords = 0;
    $totalPages = 1;
    $search = $_POST['search'] ?? '';
    $activeYearFilter = $_SESSION['feeconfig_year_filter'] ?? '';
    $activeSchoolFilter = $_SESSION['feeconfig_school_filter'] ?? '';
    set_flash_message('error', $response['error'] ?? 'Failed to load fee configuration');
}

$page_title = "Fee Configuration";

include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>

<link rel="stylesheet" href="<?php echo PORTAL_URL; ?>/assets/css/modules/fees/fee-config.css">

<!-- Include SheetJS for modern Excel exports -->
<script src="<?php echo BASE_URL; ?>/assets/vendor/xlsx/xlsx.full.min.js"></script>
<script src="../../assets/js/table-utilities.js"></script>



<div class="container-fluid">


    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_SESSION['success'] ?? ''); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_SESSION['error'] ?? ''); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Fee Configuration Management</h4>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addFeeConfigModal">
                <i class="fas fa-plus"></i> Add Fee Configuration
            </button>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bulkUploadModal">
                <i class="fas fa-upload"></i> Bulk Upload
            </button>
            <button type="button" class="btn btn-success" onclick="exportToExcel()">
                <i class="fas fa-file-excel"></i> Export to Excel
            </button>
            <button type="button" class="btn btn-danger" id="deleteSelectedBtn" onclick="deleteSelected()"
                class="fee-config-custom-1">
                <i class="fas fa-trash"></i> Delete Selected
            </button>
        </div>
    </div>

    <!-- Search and Filter -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="POST" class="row g-3">
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control" placeholder="Search by standard, year..."
                        value="<?php echo htmlspecialchars($search ?? ''); ?>">
                </div>
                <div class="col-md-2">
                    <select name="academic_year" class="form-control">
                        <option value="">All Years</option>
                        <?php foreach ($academic_years as $year): ?>
                            <option value="<?php echo htmlspecialchars($year['year_name'] ?? ''); ?>" <?php echo $activeYearFilter == $year['year_name'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($year['year_name'] ?? ''); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="school_id" class="form-control">
                        <option value="">All Schools</option>
                        <?php foreach ($schools as $school): ?>
                            <option value="<?php echo $school['id']; ?>" <?php echo $activeSchoolFilter == $school['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($school['school_name'] ?? ''); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="per_page" class="form-control">
                        <option value="10" <?php echo $perPage == 10 ? 'selected' : ''; ?>>10 per page</option>
                        <option value="25" <?php echo $perPage == 25 ? 'selected' : ''; ?>>25 per page</option>
                        <option value="50" <?php echo $perPage == 50 ? 'selected' : ''; ?>>50 per page</option>
                        <option value="100" <?php echo $perPage == 100 ? 'selected' : ''; ?>>100 per page</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                </div>
                <?php if (!empty($activeYearFilter) || !empty($activeSchoolFilter)): ?>
                    <div class="col-md-2">
                        <button type="submit" name="clear_filter" value="1" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Clear Filter
                        </button>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Fee Config Table -->
    <div class="table-responsive bg-white rounded shadow-sm">
        <table class="table table-bordered table-striped table-hover mb-0" id="feeConfigTable">
            <thead>
                <tr>
                    <th width="3%">
                        <input type="checkbox" id="selectAll" class="form-check-input">
                    </th>
                    <th>ID</th>
                    <th>Academic Year</th>
                    <th>Term</th>
                    <th>Standard Name</th>
                    <th>School</th>
                    <th>Medium</th>
                    <th>Group</th>
                    <th>Token Fee</th>
                    <th>Total Fees</th>
                    <th>Payable Fees</th>
                    <th>Installments</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($fee_configs)): ?>
                    <tr>
                        <td colspan="14" class="text-center">No fee configurations found</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($fee_configs as $index => $config):
                        $payable_fees = $config['total_fees'] - $config['token_fee'];
                        ?>
                        <tr>
                            <td>
                                <input type="checkbox" class="row-checkbox form-check-input"
                                    value="<?php echo $config['id']; ?>">
                            </td>
                            <td><?php echo $config['id']; ?></td>
                            <td><?php echo htmlspecialchars($config['academic_year'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($config['term'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($config['course_name'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($config['school_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($config['medium'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($config['group_type'] ?? 'N/A'); ?></td>
                            <td>₹<?php echo formatIndianCurrency($config['token_fee']); ?></td>
                            <td>₹<?php echo formatIndianCurrency($config['total_fees']); ?></td>
                            <td class="text-primary fw-bold">₹<?php echo formatIndianCurrency($payable_fees); ?></td>
                            <td>
                                <span class="badge bg-info"><?php echo $config['installment_count']; ?></span>
                            </td>
                            <td>
                                <?php if ($config['is_active']): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-warning" onclick="editConfig(<?php echo $config['id']; ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteFeeConfig(<?php echo $config['id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="d-flex justify-content-between align-items-center p-3 border-top">
        <div>
            <?php echo getPaginationInfo($page, $perPage, $totalRecords); ?>
        </div>
        <div>
            <?php echo renderPaginationPost($page, $totalPages, 2, $perPage); ?>
        </div>
    </div>
</div>
</div><!-- /.container-fluid -->
</div><!-- /.app-content -->
</main><!-- /.app-main -->

<!-- Add Fee Configuration Modal -->
<div class="modal fade" id="addFeeConfigModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary-custom text-white">
                <h5 class="modal-title"><i class="fas fa-plus"></i> Add Fee Configuration</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addFeeConfigForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Academic Year <span class="text-danger">*</span></label>
                                <select name="academic_year" id="academic_year" class="form-control" required>
                                    <option value="">-- Select Academic Year --</option>
                                    <?php
                                    foreach ($academic_years as $year): ?>
                                        <option value="<?php
                                        echo htmlspecialchars($year['year_name'] ?? ''); ?>" data-year-id="<?php
                                          echo $year['id']; ?>">
                                            <?php
                                            echo htmlspecialchars($year['year_name'] ?? ''); ?>
                                        </option>
                                        <?php
                                    endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Term <span class="text-danger">*</span></label>
                                <select name="term" id="term" class="form-control" required disabled>
                                    <option value="">-- Select Term --</option>
                                </select>
                                <small class="text-muted">Select academic year first</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Standard Name <span class="text-danger">*</span></label>
                                <select name="course_id" id="course_id" class="form-control" required>
                                    <option value="">-- Select Standard --</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?php echo $course['id']; ?>">
                                            <?php
                                            echo htmlspecialchars($course['course_name'] ?? ''); ?>
                                            <?php
                                            if ($course['board_name']): ?>
                                                (<?php
                                                echo htmlspecialchars($course['board_name'] ?? ''); ?>)
                                                <?php
                                            endif; ?>
                                        </option>
                                        <?php
                                    endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Medium <span class="text-danger">*</span></label>
                                <select name="medium" id="medium" class="form-control" required>
                                    <option value="">-- Select Medium --</option>
                                    <?php
                                    foreach ($mediums as $medium): ?>
                                        <option value="<?php
                                        echo htmlspecialchars($medium['medium_name'] ?? ''); ?>">
                                            <?php
                                            echo htmlspecialchars($medium['medium_name'] ?? ''); ?>
                                        </option>
                                        <?php
                                    endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">School <span class="text-danger">*</span></label>
                                <select name="school_id" id="school_id" class="form-control" required>
                                    <option value="">-- Select School --</option>
                                    <?php
                                    foreach ($schools as $school): ?>
                                        <option value="<?php
                                        echo $school['id']; ?>">
                                            <?php
                                            echo htmlspecialchars($school['school_name'] ?? ''); ?>
                                        </option>
                                        <?php
                                    endforeach; ?>
                                </select>
                                <small class="text-muted">Split labels from payment gateway configuration</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Group <span class="text-danger">*</span></label>
                                <select name="group_type" id="group_type" class="form-control" required>
                                    <option value="">-- Select Group --</option>
                                    <?php
                                    foreach ($groups as $group): ?>
                                        <option value="<?php
                                        echo htmlspecialchars($group['group_name'] ?? ''); ?>">
                                            <?php
                                            echo htmlspecialchars($group['group_name'] ?? ''); ?>
                                        </option>
                                        <?php
                                    endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Fee Type Selection Section (Shows after Term, Medium, Group selected) -->
                    <div id="feeTypeSection" class="fee-config-custom-1">
                        <hr class="my-4">
                        <h6 class="mb-3 text-primary"><i class="fas fa-money-bill-wave"></i> Select Fee Types to
                            Configure</h6>

                        <!-- School Fee -->
                        <div class="card mb-3">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="enable_school_fee"
                                                value="1">
                                            <label class="form-check-label fw-bold" for="enable_school_fee">
                                                School Fee
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <input type="number" name="school_fee" id="school_fee" class="form-control"
                                            step="0.01" min="0" placeholder="Enter amount" disabled>
                                    </div>
                                    <div class="col-md-3">
                                        <select name="school_fee_label" id="school_fee_label"
                                            class="form-control form-control-sm" <?php
                                            echo $isSuperAdmin ? '' : 'disabled'; ?>>
                                            <?php
                                            foreach ($split_labels as $index => $label): ?>
                                                <option value="<?php
                                                echo htmlspecialchars($label ?? ''); ?>" <?php
                                                  echo $index === 0 ? 'selected' : ''; ?>>
                                                    <?php
                                                    echo htmlspecialchars($label ?? ''); ?>
                                                </option>
                                                <?php
                                            endforeach; ?>
                                        </select>
                                        <small class="text-muted">Easebuzz Split Label</small>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="school_fee_gst"
                                                value="1" disabled>
                                            <label class="form-check-label" for="school_fee_gst">
                                                Apply GST (18%)
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Trust Facilities Fee (MST) -->
                        <div class="card mb-3">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="enable_trust_fee"
                                                value="1">
                                            <label class="form-check-label fw-bold" for="enable_trust_fee">
                                                Trust Facilities Fee (MST)
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <input type="number" name="trust_facilities_fee" id="trust_facilities_fee"
                                            class="form-control" step="0.01" min="0" placeholder="Enter amount"
                                            disabled>
                                    </div>
                                    <div class="col-md-3">
                                        <select name="trust_fee_label" id="trust_fee_label"
                                            class="form-control form-control-sm" <?php
                                            echo $isSuperAdmin ? '' : 'disabled'; ?>>
                                            <?php
                                            foreach ($split_labels as $index => $label): ?>
                                                <option value="<?php
                                                echo htmlspecialchars($label ?? ''); ?>" <?php
                                                  echo $index === 2 ? 'selected' : ''; ?>>
                                                    <?php
                                                    echo htmlspecialchars($label ?? ''); ?>
                                                </option>
                                                <?php
                                            endforeach; ?>
                                        </select>
                                        <small class="text-muted">Easebuzz Split Label</small>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="trust_fee_gst" value="1"
                                                disabled>
                                            <label class="form-check-label" for="trust_fee_gst">
                                                Apply GST (18%)
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Token Fee (Tuition Part-1) -->
                        <div class="card mb-3">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="enable_token_fee"
                                                value="1">
                                            <label class="form-check-label fw-bold" for="enable_token_fee">
                                                Token Fee
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <input type="number" name="tuition_fee_part1" id="tuition_fee_part1"
                                            class="form-control" step="0.01" min="0" placeholder="Enter amount"
                                            disabled>
                                        <small class="text-muted">This acts as token fee</small>
                                    </div>
                                    <div class="col-md-3">
                                        <select name="token_fee_label" id="token_fee_label"
                                            class="form-control form-control-sm" <?php
                                            echo $isSuperAdmin ? '' : 'disabled'; ?>>
                                            <?php
                                            foreach ($split_labels as $index => $label): ?>
                                                <option value="<?php
                                                echo htmlspecialchars($label ?? ''); ?>" <?php
                                                  echo $index === 3 || (count($split_labels) === 3 && $index === 0) ? 'selected' : ''; ?>>
                                                    <?php
                                                    echo htmlspecialchars($label ?? ''); ?>
                                                </option>
                                                <?php
                                            endforeach; ?>
                                        </select>
                                        <small class="text-muted">Easebuzz Split Label</small>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="token_fee_gst" value="1"
                                                disabled>
                                            <label class="form-check-label" for="token_fee_gst">
                                                Apply GST (18%)
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tuition Fee (GCA) -->
                        <div class="card mb-3">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="enable_tuition_fee"
                                                value="1">
                                            <label class="form-check-label fw-bold" for="enable_tuition_fee">
                                                Tuition Fee (GCA)
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <input type="number" name="tuition_fee_part2" id="tuition_fee_part2"
                                            class="form-control" step="0.01" min="0" placeholder="Enter amount"
                                            disabled>
                                    </div>
                                    <div class="col-md-3">
                                        <select name="tuition_fee_label" id="tuition_fee_label"
                                            class="form-control form-control-sm" <?php
                                            echo $isSuperAdmin ? '' : 'disabled'; ?>>
                                            <?php
                                            foreach ($split_labels as $index => $label): ?>
                                                <option value="<?php
                                                echo htmlspecialchars($label ?? ''); ?>" <?php
                                                  echo $index === 3 || (count($split_labels) === 3 && $index === 0) ? 'selected' : ''; ?>>
                                                    <?php
                                                    echo htmlspecialchars($label ?? ''); ?>
                                                </option>
                                                <?php
                                            endforeach; ?>
                                        </select>
                                        <small class="text-muted">Easebuzz Split Label</small>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="tuition_fee_gst"
                                                value="1" disabled>
                                            <label class="form-check-label" for="tuition_fee_gst">
                                                Apply GST (18%)
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-3" id="installmentSection" class="fee-config-custom-1">
                                    <div class="col-md-6 offset-md-3">
                                        <label class="form-label">Number of Installments</label>
                                        <input type="number" name="number_of_installments" id="number_of_installments"
                                            class="form-control" min="1" max="12" value="1">
                                        <small class="text-muted">For tuition fee only (default: 1)</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Total Fees</label>
                                    <input type="number" name="total_fees" id="total_fees" class="form-control"
                                        step="0.01" min="0" readonly>
                                    <small class="text-muted">Auto-calculated based on selected fee types</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info" id="installmentBreakdown" class="fee-config-custom-1">
                        <h6><i class="fas fa-calculator"></i> Installment Breakdown:</h6>
                        <ul class="mb-0">
                            <li>Token Fee (Tuition Part-1 + GST): <strong id="displayTokenFee">?0.00</strong></li>
                            <li>Total Payable Fees (after Token): <strong id="displayPayableFees">?0.00</strong></li>
                            <li>Number of Installments: <strong id="displayInstallmentCount">0</strong></li>
                            <li>Amount per Installment: <strong id="displayPerInstallment">?0.00</strong></li>
                        </ul>
                        <small class="text-muted mt-2 d-block">Token fee is Tuition Part-1 with GST. Remaining fees
                            split into installments.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary-custom">Save Configuration</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Configuration Modal -->
<div class="modal fade" id="viewConfigModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info-custom text-white">
                <h5 class="modal-title"><i class="fas fa-list"></i> Configuration Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="configDetails">
                <!-- Will be populated via AJAX -->
            </div>
        </div>
    </div>
</div>

<!-- Bulk Upload Modal -->
<div class="modal fade" id="bulkUploadModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-upload"></i> Bulk Upload Fee Configurations</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="bulkUploadFeeForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Instructions:</strong>
                        <ul class="mb-0 mt-2">
                            <li>Download the CSV template using the "Download Template" button</li>
                            <li>Fill in the fee configuration data in the CSV file</li>
                            <li>Upload the completed CSV file below</li>
                            <li>All monetary values should be in numbers only (without ₹ symbol)</li>
                            <li>GST fields: use 1 for Yes, 0 for No</li>
                        </ul>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Upload CSV File <span class="text-danger">*</span></label>
                        <input type="file" name="bulk_file" id="bulk_fee_file" class="form-control" accept=".csv"
                            required>
                        <small class="text-muted">Accepted format: CSV only</small>
                    </div>

                    <div id="fee_upload_preview" class="d-none">
                        <h6 class="text-primary">File Preview:</h6>
                        <div id="fee_preview_content" class="table-responsive"
                            style="max-height: 300px; overflow-y: auto;">
                            <!-- Preview will be shown here -->
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="../../assets/samples/fee_config_template.csv" class="btn btn-info me-auto" download>
                        <i class="fas fa-download"></i> Download Template
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-success" id="uploadFeeBtn">
                        <i class="fas fa-upload"></i> Upload & Process
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Fee Configuration Modal -->
<div class="modal fade" id="editFeeConfigModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning-custom text-white">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Fee Configuration</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="editFeeConfigForm">
                <input type="hidden" name="id" id="edit_config_id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Academic Year <span class="text-danger">*</span></label>
                                <select name="academic_year" id="edit_academic_year" class="form-control" required>
                                    <option value="">-- Select Academic Year --</option>
                                    <?php
                                    foreach ($academic_years as $year): ?>
                                        <option value="<?php
                                        echo htmlspecialchars($year['year_name'] ?? ''); ?>" data-year-id="<?php
                                          echo $year['id']; ?>">
                                            <?php
                                            echo htmlspecialchars($year['year_name'] ?? ''); ?>
                                        </option>
                                        <?php
                                    endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Term <span class="text-danger">*</span></label>
                                <select name="term" id="edit_term" class="form-control" required>
                                    <option value="">-- Select Term --</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Standard Name <span class="text-danger">*</span></label>
                                <select name="course_id" id="edit_course_id" class="form-control" required>
                                    <option value="">-- Select Standard --</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?php echo $course['id']; ?>">
                                            <?php
                                            echo htmlspecialchars($course['course_name'] ?? ''); ?>
                                            <?php
                                            if ($course['board_name']): ?>
                                                (<?php
                                                echo htmlspecialchars($course['board_name'] ?? ''); ?>)
                                                <?php
                                            endif; ?>
                                        </option>
                                        <?php
                                    endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Medium <span class="text-danger">*</span></label>
                                <select name="medium" id="edit_medium" class="form-control" required>
                                    <option value="">-- Select Medium --</option>
                                    <?php
                                    foreach ($mediums as $medium): ?>
                                        <option value="<?php
                                        echo htmlspecialchars($medium['medium_name'] ?? ''); ?>">
                                            <?php
                                            echo htmlspecialchars($medium['medium_name'] ?? ''); ?>
                                        </option>
                                        <?php
                                    endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">School <span class="text-danger">*</span></label>
                                <select name="school_id" id="edit_school_id" class="form-control" required>
                                    <option value="">-- Select School --</option>
                                    <?php
                                    foreach ($schools as $school): ?>
                                        <option value="<?php
                                        echo $school['id']; ?>">
                                            <?php
                                            echo htmlspecialchars($school['school_name'] ?? ''); ?>
                                        </option>
                                        <?php
                                    endforeach; ?>
                                </select>
                                <small class="text-muted">Split labels from payment gateway configuration</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Group <span class="text-danger">*</span></label>
                                <select name="group_type" id="edit_group_type" class="form-control" required>
                                    <option value="">-- Select Group --</option>
                                    <?php
                                    foreach ($groups as $group): ?>
                                        <option value="<?php
                                        echo htmlspecialchars($group['group_name'] ?? ''); ?>">
                                            <?php
                                            echo htmlspecialchars($group['group_name'] ?? ''); ?>
                                        </option>
                                        <?php
                                    endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Fee Type Selection Section -->
                    <hr class="my-4">
                    <h6 class="mb-3 text-primary"><i class="fas fa-money-bill-wave"></i> Fee Types Configuration</h6>

                    <!-- School Fee -->
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="edit_enable_school_fee"
                                            value="1">
                                        <label class="form-check-label fw-bold" for="edit_enable_school_fee">
                                            School Fee
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <input type="number" name="school_fee" id="edit_school_fee" class="form-control"
                                        step="0.01" min="0" placeholder="Enter amount" disabled>
                                </div>
                                <div class="col-md-3">
                                    <select name="school_fee_label" id="edit_school_fee_label"
                                        class="form-control form-control-sm" <?php
                                        echo $isSuperAdmin ? '' : 'disabled'; ?>>
                                        <?php
                                        foreach ($split_labels as $index => $label): ?>
                                            <option value="<?php
                                            echo htmlspecialchars($label ?? ''); ?>" <?php
                                              echo $index === 0 ? 'selected' : ''; ?>>
                                                <?php
                                                echo htmlspecialchars($label ?? ''); ?>
                                            </option>
                                            <?php
                                        endforeach; ?>
                                    </select>
                                    <small class="text-muted">Easebuzz Split Label</small>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="edit_school_fee_gst"
                                            value="1" disabled>
                                        <label class="form-check-label" for="edit_school_fee_gst">
                                            Apply GST (18%)
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Trust Facilities Fee (MST) -->
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="edit_enable_trust_fee"
                                            value="1">
                                        <label class="form-check-label fw-bold" for="edit_enable_trust_fee">
                                            Trust Facilities Fee (MST)
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <input type="number" name="trust_facilities_fee" id="edit_trust_facilities_fee"
                                        class="form-control" step="0.01" min="0" placeholder="Enter amount" disabled>
                                </div>
                                <div class="col-md-3">
                                    <select name="trust_fee_label" id="edit_trust_fee_label"
                                        class="form-control form-control-sm" <?php
                                        echo $isSuperAdmin ? '' : 'disabled'; ?>>
                                        <?php
                                        foreach ($split_labels as $index => $label): ?>
                                            <option value="<?php
                                            echo htmlspecialchars($label ?? ''); ?>" <?php
                                              echo $index === 0 ? 'selected' : ''; ?>>
                                                <?php
                                                echo htmlspecialchars($label ?? ''); ?>
                                            </option>
                                            <?php
                                        endforeach; ?>
                                    </select>
                                    <small class="text-muted">Easebuzz Split Label</small>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="edit_trust_fee_gst"
                                            value="1" disabled>
                                        <label class="form-check-label" for="edit_trust_fee_gst">
                                            Apply GST (18%)
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Token Fee (Tuition Part-1) -->
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="edit_enable_token_fee"
                                            value="1">
                                        <label class="form-check-label fw-bold" for="edit_enable_token_fee">
                                            Token Fee
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <input type="number" name="tuition_fee_part1" id="edit_tuition_fee_part1"
                                        class="form-control" step="0.01" min="0" placeholder="Enter amount" disabled>
                                    <small class="text-muted">This acts as token fee</small>
                                </div>
                                <div class="col-md-3">
                                    <select name="token_fee_label" id="edit_token_fee_label"
                                        class="form-control form-control-sm" <?php
                                        echo $isSuperAdmin ? '' : 'disabled'; ?>>
                                        <?php
                                        foreach ($split_labels as $index => $label): ?>
                                            <option value="<?php
                                            echo htmlspecialchars($label ?? ''); ?>" <?php
                                              echo $index === 1 || (count($split_labels) === 1 && $index === 0) ? 'selected' : ''; ?>>
                                                <?php
                                                echo htmlspecialchars($label ?? ''); ?>
                                            </option>
                                            <?php
                                        endforeach; ?>
                                    </select>
                                    <small class="text-muted">Easebuzz Split Label</small>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="edit_token_fee_gst"
                                            value="1" disabled>
                                        <label class="form-check-label" for="edit_token_fee_gst">
                                            Apply GST (18%)
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tuition Fee (GCA) -->
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="edit_enable_tuition_fee"
                                            value="1">
                                        <label class="form-check-label fw-bold" for="edit_enable_tuition_fee">
                                            Tuition Fee (GCA)
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <input type="number" name="tuition_fee_part2" id="edit_tuition_fee_part2"
                                        class="form-control" step="0.01" min="0" placeholder="Enter amount" disabled>
                                </div>
                                <div class="col-md-3">
                                    <select name="tuition_fee_label" id="edit_tuition_fee_label"
                                        class="form-control form-control-sm" <?php
                                        echo $isSuperAdmin ? '' : 'disabled'; ?>>
                                        <?php
                                        foreach ($split_labels as $index => $label): ?>
                                            <option value="<?php
                                            echo htmlspecialchars($label ?? ''); ?>" <?php
                                              echo $index === 1 || (count($split_labels) === 1 && $index === 0) ? 'selected' : ''; ?>>
                                                <?php
                                                echo htmlspecialchars($label ?? ''); ?>
                                            </option>
                                            <?php
                                        endforeach; ?>
                                    </select>
                                    <small class="text-muted">Easebuzz Split Label</small>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="edit_tuition_fee_gst"
                                            value="1" disabled>
                                        <label class="form-check-label" for="edit_tuition_fee_gst">
                                            Apply GST (18%)
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="row mt-3" id="editInstallmentSection" class="fee-config-custom-1">
                                <div class="col-md-6 offset-md-3">
                                    <label class="form-label">Number of Installments</label>
                                    <input type="number" name="number_of_installments" id="edit_number_of_installments"
                                        class="form-control" min="1" max="12" value="1">
                                    <small class="text-muted">For tuition fee only (default: 1)</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Total Fees</label>
                                <input type="number" name="total_fees" id="edit_total_fees" class="form-control"
                                    step="0.01" min="0" readonly>
                                <small class="text-muted">Auto-calculated based on selected fee types</small>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-info" id="editInstallmentBreakdown" class="fee-config-custom-1">
                        <h6><i class="fas fa-calculator"></i> Installment Breakdown:</h6>
                        <ul class="mb-0">
                            <li>Token Fee (Tuition Part-1 + GST): <strong id="editDisplayTokenFee">?0.00</strong></li>
                            <li>Total Payable Fees (after Token): <strong id="editDisplayPayableFees">?0.00</strong>
                            </li>
                            <li>Number of Installments: <strong id="editDisplayInstallmentCount">0</strong></li>
                            <li>Amount per Installment: <strong id="editDisplayPerInstallment">?0.00</strong></li>
                        </ul>
                        <small class="text-muted mt-2 d-block">Token fee is Tuition Part-1 with GST. Remaining fees
                            split into installments.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-warning-custom">Update Configuration</button>
                </div>
            </form>
        </div>
    </div>

    <?php
    include '../../include/footer.php'; ?>

    <script>
        // Check if user is Super Admin (for split label editing)
        const isSuperAdmin = <?php
        echo $isSuperAdmin ? 'true' : 'false'; ?>;

        // Global functions for onclick handlers
        function deleteConfig(configId) {
            showConfirm({
                title: 'Delete Config?',
                message: "This will delete the fee configuration!",
                confirmText: 'Yes, delete it!',
                confirmButtonClass: 'btn-danger',
                onConfirm: function () {
                    $.ajax({
                        url: BACKEND_URL + '/controllers/fees/fee-config-delete.php',
                        method: 'POST',
                        data: {
                            id: configId
                        },
                        dataType: 'json',
                        success: function (response) {
                            if (response.success) {
                                showToast('success', 'Deleted!', response.message);
                                setTimeout(() => location.reload(), 1500);
                            } else {
                                showToast('error', 'Error!', response.message);
                            }
                        },
                        error: function (xhr, status, error) {
                            console.error('Delete error:', error);
                            showToast('error', 'Error!', 'Failed to delete configuration: ' + error);
                        }
                    });
                }
            });
        }

        function editConfig(configId) {
            // Fetch configuration data from API
            $.api.get('fees/config', {
                get_config: configId
            }).then(config => {
                console.log('Fetched config:', config);

                // Debug: Check what options exist
                console.log('Academic year options:', $('#edit_academic_year option').map(function () {
                    return $(this).val();
                }).get());
                console.log('Course options:', $('#edit_course_id option').map(function () {
                    return $(this).val();
                }).get());
                console.log('Medium options:', $('#edit_medium option').map(function () {
                    return $(this).val();
                }).get());
                console.log('Group options:', $('#edit_group_type option').map(function () {
                    return $(this).val();
                }).get());
                console.log('School options:', $('#edit_school_id option').map(function () {
                    return $(this).val();
                }).get());

                // Set hidden ID field
                $('#edit_config_id').val(config.id);

                // Set dropdown values
                $('#edit_academic_year').val(config.academic_year);
                console.log('Academic year set to:', $('#edit_academic_year').val());

                $('#edit_course_id').val(config.course_id);
                console.log('Course set to:', $('#edit_course_id').val());

                $('#edit_medium').val(config.medium);
                console.log('Medium set to:', $('#edit_medium').val());

                $('#edit_group_type').val(config.group_type);
                console.log('Group set to:', $('#edit_group_type').val());

                $('#edit_school_id').val(String(config.school_id));
                console.log('School set to:', $('#edit_school_id').val());

                // Trigger change on academic year to load terms
                $('#edit_academic_year').trigger('change');

                // Wait for terms to load, then set term
                setTimeout(function () {
                    $('#edit_term').val(config.term);
                }, 200);

                // Setup fee checkboxes and values
                const schoolFee = parseFloat(config.school_fee) || 0;
                const trustFee = parseFloat(config.trust_facilities_fee) || 0;
                const tokenFee = parseFloat(config.tuition_fee_part1) || 0;
                const tuitionFee = parseFloat(config.tuition_fee_part2) || 0;

                // Reset all checkboxes first
                $('#edit_enable_school_fee, #edit_enable_trust_fee, #edit_enable_token_fee, #edit_enable_tuition_fee').prop('checked', false);
                $('#edit_school_fee_gst, #edit_trust_fee_gst, #edit_token_fee_gst, #edit_tuition_fee_gst').prop('checked', false);

                if (schoolFee > 0) {
                    $('#edit_enable_school_fee').prop('checked', true).trigger('change');
                    $('#edit_school_fee').val(schoolFee);
                    if (config.school_fee_label) $('#edit_school_fee_label').val(config.school_fee_label);
                    if (config.school_fee_gst == 1) $('#edit_school_fee_gst').prop('checked', true);
                }
                if (trustFee > 0) {
                    $('#edit_enable_trust_fee').prop('checked', true).trigger('change');
                    $('#edit_trust_facilities_fee').val(trustFee);
                    if (config.trust_fee_label) $('#edit_trust_fee_label').val(config.trust_fee_label);
                    if (config.trust_fee_gst == 1) $('#edit_trust_fee_gst').prop('checked', true);
                }
                if (tokenFee > 0) {
                    $('#edit_enable_token_fee').prop('checked', true).trigger('change');
                    $('#edit_tuition_fee_part1').val(tokenFee);
                    if (config.token_fee_label) $('#edit_token_fee_label').val(config.token_fee_label);
                    if (config.token_fee_gst == 1) $('#edit_token_fee_gst').prop('checked', true);
                }
                if (tuitionFee > 0) {
                    $('#edit_enable_tuition_fee').prop('checked', true).trigger('change');
                    $('#edit_tuition_fee_part2').val(tuitionFee);
                    if (config.tuition_fee_label) $('#edit_tuition_fee_label').val(config.tuition_fee_label);
                    if (config.tuition_fee_gst == 1) $('#edit_tuition_fee_gst').prop('checked', true);
                }

                $('#edit_total_fees').val(config.total_fees);
                $('#edit_number_of_installments').val(config.number_of_installments || 1);
                calculateEditTotalFees();
                updateEditInstallmentBreakdown();

                // Show modal
                var editModal = new bootstrap.Modal(document.getElementById('editFeeConfigModal'));
                editModal.show();
            }).catch(error => {
                console.error('Error fetching config:', error);
                showToast('error', 'Error', 'Failed to load configuration data: ' + (error.message || error));
            });
        }

        function calculateEditTotalFees() {
            let totalFees = 0;

            // School Fee
            if ($('#edit_enable_school_fee').is(':checked')) {
                const schoolFee = parseFloat($('#edit_school_fee').val()) || 0;
                const applyGst = $('#edit_school_fee_gst').is(':checked');
                totalFees += applyGst ? (schoolFee + schoolFee * 0.18) : schoolFee;
            }

            // Trust Fee
            if ($('#edit_enable_trust_fee').is(':checked')) {
                const trustFee = parseFloat($('#edit_trust_facilities_fee').val()) || 0;
                const applyGst = $('#edit_trust_fee_gst').is(':checked');
                totalFees += applyGst ? (trustFee + trustFee * 0.18) : trustFee;
            }

            // Token Fee (Tuition Part-1)
            if ($('#edit_enable_token_fee').is(':checked')) {
                const tokenFee = parseFloat($('#edit_tuition_fee_part1').val()) || 0;
                const applyGst = $('#edit_token_fee_gst').is(':checked');
                totalFees += applyGst ? (tokenFee + tokenFee * 0.18) : tokenFee;
            }

            // Tuition Fee (Part-2)
            if ($('#edit_enable_tuition_fee').is(':checked')) {
                const tuitionFee = parseFloat($('#edit_tuition_fee_part2').val()) || 0;
                const applyGst = $('#edit_tuition_fee_gst').is(':checked');
                totalFees += applyGst ? (tuitionFee + tuitionFee * 0.18) : tuitionFee;
            }

            $('#edit_total_fees').val(totalFees.toFixed(2));
            updateEditInstallmentBreakdown();
        }

        function updateEditInstallmentBreakdown() {
            // Only show breakdown if tuition fee is enabled
            if (!$('#edit_enable_tuition_fee').is(':checked')) {
                $('#editInstallmentBreakdown').hide();
                return;
            }

            const tuitionPart1 = parseFloat($('#edit_tuition_fee_part1').val()) || 0;
            const tokenGst = $('#edit_token_fee_gst').is(':checked');
            const tokenFee = tokenGst ? (tuitionPart1 + tuitionPart1 * 0.18) : tuitionPart1;

            const totalFees = parseFloat($('#edit_total_fees').val()) || 0;
            const numInstallments = parseInt($('#edit_number_of_installments').val()) || 1;
            const payableFees = totalFees - tokenFee;

            if (totalFees > 0 && numInstallments > 0 && payableFees > 0) {
                const perInstallment = payableFees / numInstallments;

                $('#editDisplayTokenFee').text('₹' + tokenFee.toFixed(2));
                $('#editDisplayPayableFees').text('₹' + payableFees.toFixed(2));
                $('#editDisplayInstallmentCount').text(numInstallments);
                $('#editDisplayPerInstallment').text('₹' + perInstallment.toFixed(2));
                $('#editInstallmentBreakdown').show();
            } else {
                $('#editInstallmentBreakdown').hide();
            }
        }

        function viewConfig(configId) {
            $.ajax({
                url: 'fee-config-view.php',
                method: 'GET',
                data: {
                    id: configId
                },
                success: function (response) {
                    $('#configDetails').html(response);
                    $('#viewConfigModal').modal('show');
                }
            });
        }

        $(document).ready(function () {
            // Move modals to body to prevent z-index issues
            $('#addFeeConfigModal').appendTo("body");
            $('#viewConfigModal').appendTo("body");
            $('#editFeeConfigModal').appendTo("body");

            // Show fee type section when Term, Medium, Group, and School are all selected

            function checkSelectionComplete() {
                const term = $('#term').val();
                const medium = $('#medium').val();
                const group = $('#group_type').val();
                const school = $('#school_id').val();

                if (term && medium && group && school) {
                    $('#feeTypeSection').slideDown();
                } else {
                    $('#feeTypeSection').slideUp();
                }
            }

            $('#term, #medium, #group_type, #school_id').change(checkSelectionComplete);

            // Note: Split labels are now loaded from payment gateway config, not school-specific
            // Removed dynamic label loading based on school selection

            // Function to update all split label dropdowns
            function updateSplitLabelDropdowns(labels, prefix) {
                const dropdownIds = [
                    prefix + 'school_fee_label',
                    prefix + 'trust_fee_label',
                    prefix + 'token_fee_label',
                    prefix + 'tuition_fee_label'
                ];

                dropdownIds.forEach(function (id) {
                    const select = $('#' + id);
                    const currentValue = select.val();
                    select.empty();

                    labels.forEach(function (label, index) {
                        const option = $('<option></option>').val(label).text(label);
                        // Try to keep current selection, otherwise use defaults
                        if (label === currentValue) {
                            option.prop('selected', true);
                        } else if (!currentValue && id.includes('school_fee') && index === 0) {
                            option.prop('selected', true);
                        } else if (!currentValue && id.includes('trust_fee') && index === 0) {
                            option.prop('selected', true);
                        } else if (!currentValue && (id.includes('token_fee') || id.includes('tuition_fee')) && (index === 1 || labels.length === 1)) {
                            option.prop('selected', true);
                        }
                        select.append(option);
                    });
                });
            }

            // Enable/disable fee inputs based on checkbox
            $('#enable_school_fee').change(function () {
                if ($(this).is(':checked')) {
                    $('#school_fee').prop('disabled', false).focus();
                    if (!isSuperAdmin) $('#school_fee_label').prop('disabled', false);
                    $('#school_fee_gst').prop('disabled', false);
                } else {
                    $('#school_fee').val('').prop('disabled', true);
                    if (!isSuperAdmin) $('#school_fee_label').prop('disabled', true);
                    $('#school_fee_gst').prop('checked', false).prop('disabled', true);
                }
                calculateTotalFees();
            });


            $('#enable_trust_fee').change(function () {
                if ($(this).is(':checked')) {
                    $('#trust_facilities_fee').prop('disabled', false).focus();
                    if (!isSuperAdmin) $('#trust_fee_label').prop('disabled', false);
                    $('#trust_fee_gst').prop('disabled', false);
                } else {
                    $('#trust_facilities_fee').val('').prop('disabled', true);
                    if (!isSuperAdmin) $('#trust_fee_label').prop('disabled', true);
                    $('#trust_fee_gst').prop('checked', false).prop('disabled', true);
                }
                calculateTotalFees();
            });

            $('#enable_token_fee').change(function () {
                if ($(this).is(':checked')) {
                    $('#tuition_fee_part1').prop('disabled', false).focus();
                    if (!isSuperAdmin) $('#token_fee_label').prop('disabled', false);
                    $('#token_fee_gst').prop('disabled', false);
                } else {
                    $('#tuition_fee_part1').val('').prop('disabled', true);
                    if (!isSuperAdmin) $('#token_fee_label').prop('disabled', true);
                    $('#token_fee_gst').prop('checked', false).prop('disabled', true);
                }
                calculateTotalFees();
            });

            $('#enable_tuition_fee').change(function () {
                if ($(this).is(':checked')) {
                    $('#tuition_fee_part2').prop('disabled', false).focus();
                    if (!isSuperAdmin) $('#tuition_fee_label').prop('disabled', false);
                    $('#tuition_fee_gst').prop('disabled', false);
                    $('#installmentSection').slideDown();
                } else {
                    $('#tuition_fee_part2').val('').prop('disabled', true);
                    if (!isSuperAdmin) $('#tuition_fee_label').prop('disabled', true);
                    $('#tuition_fee_gst').prop('checked', false).prop('disabled', true);
                    $('#installmentSection').slideUp();
                }
                calculateTotalFees();
            });

            // Load terms when academic year is selected (Add Modal)
            $('#academic_year').change(function () {
                const selectedOption = $(this).find(':selected');
                const yearId = selectedOption.data('year-id');

                if (yearId) {
                    $('#term').prop('disabled', true).html('<option value="">Loading...</option>');

                    $.api.get('fees/config', {
                        get_terms: true,
                        academic_year_id: yearId
                    }).then(terms => {
                        let options = '<option value="">-- Select Term --</option>';
                        terms.forEach(function (term) {
                            options += `<option value="${term.term_name}">${term.term_name}</option>`;
                        });
                        $('#term').html(options).prop('disabled', false);
                    }).catch(error => {
                        console.error('Error loading terms:', error);
                        $('#term').html('<option value="">Error loading terms</option>');
                    });
                } else {
                    $('#term').prop('disabled', true).html('<option value="">-- Select Term --</option>');
                }
            });

            // Load terms when academic year is selected (Edit Modal)
            $('#edit_academic_year').change(function () {
                const selectedOption = $(this).find(':selected');
                const yearId = selectedOption.data('year-id');

                if (yearId) {
                    $('#edit_term').html('<option value="">Loading...</option>');

                    $.api.get('fees/config', {
                        get_terms: true,
                        academic_year_id: yearId
                    }).then(terms => {
                        let options = '<option value="">-- Select Term --</option>';
                        terms.forEach(function (term) {
                            options += `<option value="${term.term_name}">${term.term_name}</option>`;
                        });
                        $('#edit_term').html(options);
                    }).catch(error => {
                        console.error('Error loading terms:', error);
                        $('#edit_term').html('<option value="">Error loading terms</option>');
                    });
                } else {
                    $('#edit_term').html('<option value="">-- Select Term --</option>');
                }
            });

            // Calculate total fees automatically
            function calculateTotalFees() {
                let totalFees = 0;

                // School Fee
                if ($('#enable_school_fee').is(':checked')) {
                    const schoolFee = parseFloat($('#school_fee').val()) || 0;
                    const applyGst = $('#school_fee_gst').is(':checked');
                    totalFees += applyGst ? (schoolFee + schoolFee * 0.18) : schoolFee;
                }

                // Trust Fee
                if ($('#enable_trust_fee').is(':checked')) {
                    const trustFee = parseFloat($('#trust_facilities_fee').val()) || 0;
                    const applyGst = $('#trust_fee_gst').is(':checked');
                    totalFees += applyGst ? (trustFee + trustFee * 0.18) : trustFee;
                }

                // Token Fee (Tuition Part-1)
                if ($('#enable_token_fee').is(':checked')) {
                    const tokenFee = parseFloat($('#tuition_fee_part1').val()) || 0;
                    const applyGst = $('#token_fee_gst').is(':checked');
                    totalFees += applyGst ? (tokenFee + tokenFee * 0.18) : tokenFee;
                }

                // Tuition Fee (Part-2)
                if ($('#enable_tuition_fee').is(':checked')) {
                    const tuitionFee = parseFloat($('#tuition_fee_part2').val()) || 0;
                    const applyGst = $('#tuition_fee_gst').is(':checked');
                    totalFees += applyGst ? (tuitionFee + tuitionFee * 0.18) : tuitionFee;
                }

                $('#total_fees').val(totalFees.toFixed(2));
                updateInstallmentBreakdown();
            }

            // Bind calculation to input changes and GST checkboxes
            $('#school_fee, #trust_facilities_fee, #tuition_fee_part1, #tuition_fee_part2').on('input', calculateTotalFees);
            $('#school_fee_gst, #trust_fee_gst, #token_fee_gst, #tuition_fee_gst').change(calculateTotalFees);

            // Calculate and display installment breakdown
            function updateInstallmentBreakdown() {
                // Only show breakdown if tuition fee is enabled
                if (!$('#enable_tuition_fee').is(':checked')) {
                    $('#installmentBreakdown').hide();
                    return;
                }

                const tuitionPart1 = parseFloat($('#tuition_fee_part1').val()) || 0;
                const tokenGst = $('#token_fee_gst').is(':checked');
                const tokenFee = tokenGst ? (tuitionPart1 + tuitionPart1 * 0.18) : tuitionPart1;

                const totalFees = parseFloat($('#total_fees').val()) || 0;
                const numInstallments = parseInt($('#number_of_installments').val()) || 1;
                const payableFees = totalFees - tokenFee;

                if (totalFees > 0 && numInstallments > 0 && payableFees > 0) {
                    const perInstallment = payableFees / numInstallments;

                    $('#displayTokenFee').text('₹' + tokenFee.toLocaleString('en-IN', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    }));
                    $('#displayPayableFees').text('₹' + payableFees.toLocaleString('en-IN', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    }));
                    $('#displayInstallmentCount').text(numInstallments);
                    $('#displayPerInstallment').text('₹' + perInstallment.toLocaleString('en-IN', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    }));
                    $('#installmentBreakdown').show();
                } else {
                    $('#installmentBreakdown').hide();
                }
            }

            $('#total_fees, #number_of_installments, #tuition_fee_part1, #token_fee_gst').on('input change', updateInstallmentBreakdown);

            // Form submission
            $('#addFeeConfigForm').submit(function (e) {
                e.preventDefault();

                const totalFees = parseFloat($('#total_fees').val()) || 0;
                const numInstallments = parseInt($('#number_of_installments').val()) || 1;

                // Calculate token fee based on checkbox
                const tuitionPart1 = parseFloat($('#tuition_fee_part1').val()) || 0;
                const tokenGst = $('#token_fee_gst').is(':checked');
                const tokenFee = $('#enable_token_fee').is(':checked') ?
                    (tokenGst ? (tuitionPart1 + tuitionPart1 * 0.18) : tuitionPart1) : 0;

                if (totalFees <= 0) {
                    showToast('error', 'Invalid Amount', 'Please select at least one fee type and enter amounts');
                    return false;
                }

                if (tokenFee > 0 && tokenFee >= totalFees) {
                    showToast('error', 'Invalid Amount', 'Token fee must be less than total fees');
                    return false;
                }

                // Add calculated values to form data
                var formData = $(this).serializeArray();

                console.log('Form data before processing:', formData);

                formData.push({
                    name: 'token_fee',
                    value: tokenFee.toFixed(2)
                });

                // Add GST flags
                formData.push({
                    name: 'school_fee_gst',
                    value: $('#school_fee_gst').is(':checked') ? 1 : 0
                });
                formData.push({
                    name: 'trust_fee_gst',
                    value: $('#trust_fee_gst').is(':checked') ? 1 : 0
                });
                formData.push({
                    name: 'token_fee_gst',
                    value: $('#token_fee_gst').is(':checked') ? 1 : 0
                });
                formData.push({
                    name: 'tuition_fee_gst',
                    value: $('#tuition_fee_gst').is(':checked') ? 1 : 0
                });

                // Set disabled fields to 0 if not enabled and add labels
                if (!$('#enable_school_fee').is(':checked')) {
                    formData = formData.filter(item => item.name !== 'school_fee' && item.name !== 'school_fee_label');
                    formData.push({
                        name: 'school_fee',
                        value: 0
                    });
                } else {
                    // Add label even if disabled (for non-super admin)
                    if (!isSuperAdmin) {
                        formData = formData.filter(item => item.name !== 'school_fee_label');
                        formData.push({
                            name: 'school_fee_label',
                            value: $('#school_fee_label').val()
                        });
                    }
                }
                if (!$('#enable_trust_fee').is(':checked')) {
                    formData = formData.filter(item => item.name !== 'trust_facilities_fee' && item.name !== 'trust_fee_label');
                    formData.push({
                        name: 'trust_facilities_fee',
                        value: 0
                    });
                } else {
                    // Add label even if disabled (for non-super admin)
                    if (!isSuperAdmin) {
                        formData = formData.filter(item => item.name !== 'trust_fee_label');
                        formData.push({
                            name: 'trust_fee_label',
                            value: $('#trust_fee_label').val()
                        });
                    }
                }
                if (!$('#enable_token_fee').is(':checked')) {
                    formData = formData.filter(item => item.name !== 'tuition_fee_part1' && item.name !== 'token_fee_label');
                    formData.push({
                        name: 'tuition_fee_part1',
                        value: 0
                    });
                } else {
                    // Add label even if disabled (for non-super admin)
                    if (!isSuperAdmin) {
                        formData = formData.filter(item => item.name !== 'token_fee_label');
                        formData.push({
                            name: 'token_fee_label',
                            value: $('#token_fee_label').val()
                        });
                    }
                }
                if (!$('#enable_tuition_fee').is(':checked')) {
                    formData = formData.filter(item => item.name !== 'tuition_fee_part2' && item.name !== 'tuition_fee_label');
                    formData.push({
                        name: 'tuition_fee_part2',
                        value: 0
                    });
                } else {
                    // Add label even if disabled (for non-super admin)
                    if (!isSuperAdmin) {
                        formData = formData.filter(item => item.name !== 'tuition_fee_label');
                        formData.push({
                            name: 'tuition_fee_label',
                            value: $('#tuition_fee_label').val()
                        });
                    }
                }

                // Convert formData array to object for JSON submission
                const dataObject = {};
                formData.forEach(item => {
                    dataObject[item.name] = item.value;
                });

                $.api.post('fees/config', dataObject).then(response => {
                    if (response.success) {
                        showToast('success', 'Success', response.message);
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showToast('error', 'Error', response.message);
                    }
                }).catch(error => {
                    showToast('error', 'Error', 'Failed to save configuration');
                });
            });

            // Edit modal checkbox handlers
            $('#edit_enable_school_fee').change(function () {
                if ($(this).is(':checked')) {
                    $('#edit_school_fee').prop('disabled', false);
                    $('#edit_school_fee_gst').prop('disabled', false);
                } else {
                    $('#edit_school_fee').val('').prop('disabled', true);
                    $('#edit_school_fee_gst').prop('checked', false).prop('disabled', true);
                }
                calculateEditTotalFees();
            });

            $('#edit_enable_trust_fee').change(function () {
                if ($(this).is(':checked')) {
                    $('#edit_trust_facilities_fee').prop('disabled', false);
                    $('#edit_trust_fee_gst').prop('disabled', false);
                } else {
                    $('#edit_trust_facilities_fee').val('').prop('disabled', true);
                    $('#edit_trust_fee_gst').prop('checked', false).prop('disabled', true);
                }
                calculateEditTotalFees();
            });

            $('#edit_enable_token_fee').change(function () {
                if ($(this).is(':checked')) {
                    $('#edit_tuition_fee_part1').prop('disabled', false);
                    $('#edit_token_fee_gst').prop('disabled', false);
                } else {
                    $('#edit_tuition_fee_part1').val('').prop('disabled', true);
                    $('#edit_token_fee_gst').prop('checked', false).prop('disabled', true);
                }
                calculateEditTotalFees();
            });

            $('#edit_enable_tuition_fee').change(function () {
                if ($(this).is(':checked')) {
                    $('#edit_tuition_fee_part2').prop('disabled', false);
                    $('#edit_tuition_fee_gst').prop('disabled', false);
                    $('#editInstallmentSection').slideDown();
                } else {
                    $('#edit_tuition_fee_part2').val('').prop('disabled', true);
                    $('#edit_tuition_fee_gst').prop('checked', false).prop('disabled', true);
                    $('#editInstallmentSection').slideUp();
                }
                calculateEditTotalFees();
            });

            $('#edit_school_fee, #edit_trust_facilities_fee, #edit_tuition_fee_part1, #edit_tuition_fee_part2').on('input', calculateEditTotalFees);
            $('#edit_school_fee_gst, #edit_trust_fee_gst, #edit_token_fee_gst, #edit_tuition_fee_gst').change(calculateEditTotalFees);
            $('#edit_total_fees, #edit_number_of_installments, #edit_tuition_fee_part1, #edit_token_fee_gst').on('input change', updateEditInstallmentBreakdown);

            // Edit form submission
            $('#editFeeConfigForm').on('submit', function (e) {
                e.preventDefault();

                const totalFees = parseFloat($('#edit_total_fees').val()) || 0;

                // Calculate token fee based on checkbox
                const tuitionPart1 = parseFloat($('#edit_tuition_fee_part1').val()) || 0;
                const tokenGst = $('#edit_token_fee_gst').is(':checked');
                const tokenFee = $('#edit_enable_token_fee').is(':checked') ?
                    (tokenGst ? (tuitionPart1 + tuitionPart1 * 0.18) : tuitionPart1) : 0;

                if (totalFees <= 0) {
                    showToast('error', 'Invalid Amount', 'Please select at least one fee type and enter amounts');
                    return false;
                }

                if (tokenFee > 0 && tokenFee >= totalFees) {
                    showToast('error', 'Invalid Amount', 'Token fee must be less than total fees');
                    return false;
                }

                // Add calculated values to form data
                var formData = $(this).serializeArray();
                formData.push({
                    name: 'token_fee',
                    value: tokenFee.toFixed(2)
                });

                // Add GST flags
                formData.push({
                    name: 'school_fee_gst',
                    value: $('#edit_school_fee_gst').is(':checked') ? 1 : 0
                });
                formData.push({
                    name: 'trust_fee_gst',
                    value: $('#edit_trust_fee_gst').is(':checked') ? 1 : 0
                });
                formData.push({
                    name: 'token_fee_gst',
                    value: $('#edit_token_fee_gst').is(':checked') ? 1 : 0
                });
                formData.push({
                    name: 'tuition_fee_gst',
                    value: $('#edit_tuition_fee_gst').is(':checked') ? 1 : 0
                });

                // Set disabled fields to 0 if not enabled
                if (!$('#edit_enable_school_fee').is(':checked')) {
                    formData = formData.filter(item => item.name !== 'school_fee' && item.name !== 'school_fee_label');
                    formData.push({
                        name: 'school_fee',
                        value: 0
                    });
                } else {
                    // Add label even if disabled (for non-super admin)
                    if (!isSuperAdmin) {
                        formData = formData.filter(item => item.name !== 'school_fee_label');
                        formData.push({
                            name: 'school_fee_label',
                            value: $('#edit_school_fee_label').val()
                        });
                    }
                }
                if (!$('#edit_enable_trust_fee').is(':checked')) {
                    formData = formData.filter(item => item.name !== 'trust_facilities_fee' && item.name !== 'trust_fee_label');
                    formData.push({
                        name: 'trust_facilities_fee',
                        value: 0
                    });
                } else {
                    // Add label even if disabled (for non-super admin)
                    if (!isSuperAdmin) {
                        formData = formData.filter(item => item.name !== 'trust_fee_label');
                        formData.push({
                            name: 'trust_fee_label',
                            value: $('#edit_trust_fee_label').val()
                        });
                    }
                }
                if (!$('#edit_enable_token_fee').is(':checked')) {
                    formData = formData.filter(item => item.name !== 'tuition_fee_part1' && item.name !== 'token_fee_label');
                    formData.push({
                        name: 'tuition_fee_part1',
                        value: 0
                    });
                } else {
                    // Add label even if disabled (for non-super admin)
                    if (!isSuperAdmin) {
                        formData = formData.filter(item => item.name !== 'token_fee_label');
                        formData.push({
                            name: 'token_fee_label',
                            value: $('#edit_token_fee_label').val()
                        });
                    }
                }
                if (!$('#edit_enable_tuition_fee').is(':checked')) {
                    formData = formData.filter(item => item.name !== 'tuition_fee_part2' && item.name !== 'tuition_fee_label');
                    formData.push({
                        name: 'tuition_fee_part2',
                        value: 0
                    });
                } else {
                    // Add label even if disabled (for non-super admin)
                    if (!isSuperAdmin) {
                        formData = formData.filter(item => item.name !== 'tuition_fee_label');
                        formData.push({
                            name: 'tuition_fee_label',
                            value: $('#edit_tuition_fee_label').val()
                        });
                    }
                }

                // Convert formData array to object for JSON submission
                const dataObject = {};
                formData.forEach(item => {
                    dataObject[item.name] = item.value;
                });

                $.api.post('fees/config-update', dataObject).then(response => {
                    if (response.success) {
                        showToast('success', 'Success', response.message);
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showToast('error', 'Error', response.message);
                    }
                }).catch(error => {
                    showToast('error', 'Error', 'An error occurred while updating fee configuration');
                });
            });
        });

        // Download Fee Template
        function downloadFeeTemplate() {
            window.location.href = '../../../counselling-backend/controllers/fees/download-fee-template.php';
        }

        // File preview for bulk upload
        $('#bulk_fee_file').on('change', function (e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    const text = e.target.result;
                    const lines = text.split('\n');

                    if (lines.length > 0) {
                        let previewHTML = '<table class="table table-sm table-bordered"><thead class="table-light">';

                        // Header row
                        const headers = lines[0].split(',');
                        previewHTML += '<tr>';
                        headers.forEach(header => {
                            previewHTML += '<th>' + header.trim() + '</th>';
                        });
                        previewHTML += '</tr></thead><tbody>';

                        // Data rows (show first 5)
                        for (let i = 1; i < Math.min(6, lines.length); i++) {
                            if (lines[i].trim()) {
                                const cols = lines[i].split(',');
                                previewHTML += '<tr>';
                                cols.forEach(col => {
                                    previewHTML += '<td>' + col.trim() + '</td>';
                                });
                                previewHTML += '</tr>';
                            }
                        }

                        previewHTML += '</tbody></table>';
                        $('#fee_preview_content').html(previewHTML);
                        $('#fee_upload_preview').removeClass('d-none');
                    }
                };
                reader.readAsText(file);
            }
        });

        // Bulk Upload Form Submission
        $('#bulkUploadFeeForm').on('submit', function (e) {
            e.preventDefault();

            const formData = new FormData(this);
            const uploadBtn = $('#uploadFeeBtn');

            uploadBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');

            $.ajax({
                url: '../../../counselling-backend/controllers/fees/bulk-upload-fee-config.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function (response) {
                    uploadBtn.prop('disabled', false).html('<i class="fas fa-upload"></i> Upload & Process');

                    if (response.success) {
                        let message = `Successfully uploaded ${response.success_count} configuration(s).`;

                        if (response.error_count > 0) {
                            message += `\n\n${response.error_count} error(s) occurred:\n`;
                            response.errors.forEach(err => {
                                message += `\nRow ${err.row}: ${err.message}`;
                            });

                            showToast('warning', 'Partial Success', message);
                            setTimeout(() => location.reload(), 3000);
                        } else {
                            showToast('success', 'Success', message);
                            setTimeout(() => location.reload(), 1500);
                        }
                    } else {
                        showToast('error', 'Upload Failed', response.message || 'An error occurred during upload');
                    }

                    $('#bulkUploadModal').modal('hide');
                    $('#bulkUploadFeeForm')[0].reset();
                    $('#fee_upload_preview').addClass('d-none');
                },
                error: function (xhr) {
                    uploadBtn.prop('disabled', false).html('<i class="fas fa-upload"></i> Upload & Process');

                    let errorMsg = 'An error occurred during upload';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }

                    showToast('error', 'Error', errorMsg);
                }
            });
        });

        // Checkbox selection handlers
        $(document).ready(function () {
            // Select All checkbox
            $('#selectAll').on('change', function () {
                const isChecked = $(this).is(':checked');
                $('.row-checkbox').prop('checked', isChecked);
                toggleDeleteButton();
            });

            // Individual checkbox
            $(document).on('change', '.row-checkbox', function () {
                const totalCheckboxes = $('.row-checkbox').length;
                const checkedCheckboxes = $('.row-checkbox:checked').length;
                $('#selectAll').prop('checked', totalCheckboxes === checkedCheckboxes);
                toggleDeleteButton();
            });

            // Toggle delete button visibility
            function toggleDeleteButton() {
                const checkedCount = $('.row-checkbox:checked').length;
                if (checkedCount > 0) {
                    $('#deleteSelectedBtn').show();
                } else {
                    $('#deleteSelectedBtn').hide();
                }
            }
        });

        // Delete selected configurations
        function deleteSelected() {
            const selected = [];
            $('.row-checkbox:checked').each(function () {
                selected.push($(this).val());
            });

            if (selected.length === 0) {
                showToast('warning', 'No Selection', 'Please select at least one configuration to delete');
                return;
            }

            showConfirm({
                title: 'Delete Configurations?',
                message: `Are you sure you want to delete ${selected.length} configuration(s)?`,
                confirmText: 'Yes, delete them!',
                confirmButtonClass: 'btn-danger',
                onConfirm: function () {
                    $.api.post('fees/config-delete-multiple', { ids: selected }).then(response => {
                        if (response.success) {
                            showToast('success', 'Deleted!', response.message);
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            showToast('error', 'Error', response.message);
                        }
                    }).catch(error => {
                        showToast('error', 'Error', 'An error occurred while deleting configurations');
                    });
                }
            });
        }

        async function exportToExcel() {
            showToast('info', 'Exporting...', 'Fetching all records, please wait...');

            try {
                const search = $('input[name="search"]').val();
                const academic_year = $('select[name="academic_year"]').val();
                const school_id = $('select[name="school_id"]').val();

                const response = await $.api.get('fees/config', {
                    export: 1,
                    search: search,
                    academic_year: academic_year,
                    school_id: school_id
                });

                if (response && response.success && response.data.fee_configs) {
                    const allConfigs = response.data.fee_configs;

                    // Create a hidden table for TableUtils
                    const $tempTable = $('<table id="tempExportTable" style="display:none"></table>');
                    $tempTable.append('<thead>' + $('#feeConfigTable thead').html() + '</thead>');
                    const $tbody = $('<tbody></tbody>');

                    allConfigs.forEach(config => {
                        const payable_fees = parseFloat(config.total_fees) - parseFloat(config.token_fee);

                        $tbody.append(`
                            <tr>
                                <td></td>
                                <td>${config.id}</td>
                                <td>${config.academic_year}</td>
                                <td>${config.term || 'N/A'}</td>
                                <td>${config.course_name}</td>
                                <td>${config.school_name || 'N/A'}</td>
                                <td>${config.medium || 'N/A'}</td>
                                <td>${config.group_type || 'N/A'}</td>
                                <td>${config.token_fee}</td>
                                <td>${config.total_fees}</td>
                                <td>${payable_fees}</td>
                                <td>${config.installment_count}</td>
                                <td>${config.is_active == 1 ? 'Active' : 'Inactive'}</td>
                                <td></td>
                            </tr>
                        `);
                    });

                    $tempTable.append($tbody);
                    $('body').append($tempTable);

                    TableUtils.exportToExcel('tempExportTable', 'fee_configuration_full_export');

                    $tempTable.remove();
                } else {
                    showToast('error', 'Export Failed', 'No data found or server error');
                }
            } catch (error) {
                console.error('Export error:', error);
                showToast('error', 'Export Error', 'An error occurred while fetching data for export');
            }
        }
    </script>

    </body>

    </html>