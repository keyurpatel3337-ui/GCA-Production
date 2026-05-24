<?php

require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once __DIR__ . '/../../common/api_client.php';
require_once PAGINATION_FILE;
require_once __DIR__ . '/../../../common/helpers/format_helper.php';

// Handle AJAX request for single rule details
if (isset($_POST['get_rule'])) {
    $api = new APIClient();
    $ruleId = $_POST['get_rule'];
    $response = $api->get('scholarships/rule-detail', ['id' => $ruleId]);

    header('Content-Type: application/json');
    if ($response && isset($response['success']) && $response['success']) {
        echo json_encode($response['data'] ?? []);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Rule not found']);
    }
    exit;
}

// Handle filters and pagination from POST or session
if (isset($_POST['scholarship_type'])) {
    $_SESSION['scholarship_type_filter'] = $_POST['scholarship_type'];
} elseif (isset($_POST['course_id'])) {
    $_SESSION['scholarship_course_filter'] = $_POST['course_id'];
} elseif (isset($_POST['clear_filter'])) {
    unset($_SESSION['scholarship_type_filter']);
    unset($_SESSION['scholarship_course_filter']);
    unset($_SESSION['scholarship_pagination']);
    unset($_SESSION['scholarship_search']);
}

// Handle page and per_page from POST (pagination clicks)
if (isset($_POST['page']) || isset($_POST['per_page'])) {
    $_SESSION['scholarship_pagination'] = [
        'page' => isset($_POST['page']) ? (int) $_POST['page'] : ($_SESSION['scholarship_pagination']['page'] ?? 1),
        'per_page' => isset($_POST['per_page']) ? (int) $_POST['per_page'] : ($_SESSION['scholarship_pagination']['per_page'] ?? 10)
    ];
}

// Load scholarship rules via API
$api = new APIClient();

// Build request params from session
$requestParams = [];
$paginationSession = $_SESSION['scholarship_pagination'] ?? [];
$requestParams['page'] = $paginationSession['page'] ?? 1;
$requestParams['per_page'] = $paginationSession['per_page'] ?? 10;

// Add search from POST or previous session
if (isset($_POST['search'])) {
    $requestParams['search'] = $_POST['search'];
    $_SESSION['scholarship_search'] = $_POST['search'];
} elseif (isset($_SESSION['scholarship_search'])) {
    $requestParams['search'] = $_SESSION['scholarship_search'];
}

// Add filters from session if exists
if (isset($_SESSION['scholarship_type_filter'])) {
    $requestParams['scholarship_type'] = $_SESSION['scholarship_type_filter'];
}
if (isset($_SESSION['scholarship_course_filter'])) {
    $requestParams['course_id'] = $_SESSION['scholarship_course_filter'];
}

$response = $api->get('scholarships/rules', $requestParams);

// Extract data from API response
if ($response && isset($response['success']) && $response['success']) {
    $data = $response['data'] ?? [];
    $rules = $data['rules'] ?? [];
    $dropdowns = $data['dropdowns'] ?? [];
    $scholarship_types = $dropdowns['scholarship_types'] ?? [];
    $courses = $dropdowns['courses'] ?? [];
    $groups = $dropdowns['groups'] ?? [];
    $pagination = $data['pagination'] ?? [];
    $page = $pagination['current_page'] ?? 1;
    $perPage = $pagination['per_page'] ?? 10;
    $totalRecords = $pagination['total_records'] ?? count($rules);
    $totalPages = $pagination['total_pages'] ?? ceil($totalRecords / $perPage);
    $search = $data['applied_filters']['search'] ?? '';
    $activeTypeFilter = $data['applied_filters']['scholarship_type'] ?? ($_SESSION['scholarship_type_filter'] ?? '');
    $activeCourseFilter = $data['applied_filters']['course_id'] ?? ($_SESSION['scholarship_course_filter'] ?? '');
} else {
    // Fallback to default values if API fails
    $rules = [];
    $scholarship_types = [];
    $courses = [];
    $groups = [];
    $page = 1;
    $perPage = 10;
    $totalRecords = 0;
    $totalPages = 1;
    $search = $_POST['search'] ?? '';
    $activeTypeFilter = $_SESSION['scholarship_type_filter'] ?? '';
    $activeCourseFilter = $_SESSION['scholarship_course_filter'] ?? '';
    set_flash_message('error', $response['error'] ?? 'Failed to load scholarship rules');
}

$page_title = "List of Scholarship Rules";
include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>

<main class="app-main">

    
        <div class="container-fluid">
            <?php
            if (isset($_SESSION['success_msg'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle"></i> <?php
                    echo htmlspecialchars($_SESSION['success_msg'] ?? '');
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php
            endif; ?>

            <?php
            if (isset($_SESSION['error_msg'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle"></i> <?php
                    echo htmlspecialchars($_SESSION['error_msg'] ?? '');
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php
            endif; ?>

            <!-- Search and Filter -->
            <div class="card mb-3">
                <div class="card-body">
                    <form method="POST" class="row g-3">
                        <div class="col-md-3">
                            <input type="text" name="search" class="form-control"
                                placeholder="Search by type, standard..."
                                value="<?php echo htmlspecialchars($search ?? ''); ?>">
                        </div>
                        <div class="col-md-2">
                            <select name="scholarship_type" class="form-control">
                                <option value="">All Types</option>
                                <?php foreach ($scholarship_types as $type): ?>
                                    <option value="<?php echo $type['id']; ?>" <?php echo $activeTypeFilter == $type['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($type['type_name'] ?? ''); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="course_id" class="form-control">
                                <option value="">All Standards</option>
                                <option value="11th" <?php echo $activeCourseFilter === '11th' ? 'selected' : ''; ?>>11th</option>
                                <option value="12th" <?php echo $activeCourseFilter === '12th' ? 'selected' : ''; ?>>12th</option>
                                <option value="Reneet" <?php echo $activeCourseFilter === 'Reneet' ? 'selected' : ''; ?>>Reneet</option>
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
                        <?php if (!empty($activeTypeFilter) || !empty($activeCourseFilter) || !empty($search)): ?>
                            <div class="col-md-2">
                                <button type="submit" name="clear_filter" value="1" class="btn btn-outline-secondary">
                                    <i class="fas fa-times"></i> Clear
                                </button>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">List of Scholarship Rules</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="rulesTable" class="table table-bordered table-striped no-auto-paginate">
                            <thead class="table-dark">
                                <tr>
                                    <th>#</th>
                                    <th>Type</th>
                                    <th>Standard & Group</th>
                                    <th>Criteria Range</th>
                                    <th>Discount</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if (empty($rules)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center">No scholarship rules found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($rules as $index => $rule):
                                        $rowNumber = ($page - 1) * $perPage + $index + 1;
                                        ?>
                                        <tr>
                                            <td><?php echo $rowNumber; ?></td>
                                            <td>
                                                <strong><?php
                                                echo htmlspecialchars($rule['type_name'] ?? ''); ?></strong>
                                                <br><small class="text-muted"><?php
                                                echo htmlspecialchars($rule['type_code'] ?? ''); ?></small>
                                            </td>
                                            <td>
                                                <?php
                                                echo htmlspecialchars($rule['course_name'] ?? ''); ?>
                                                <?php
                                                if ($rule['group_name']): ?>
                                                    <br><span class="badge bg-secondary"><?php
                                                    echo htmlspecialchars($rule['group_name'] ?? ''); ?></span>
                                                    <?php
                                                endif; ?>
                                            </td>
                                            <td>
                                                <?php

                                                if ($rule['type_code'] === 'GMSAT') {
                                                    echo "Marks: " . ($rule['gmsat_minimum_mark'] + 0) . " - " . ($rule['gmsat_maximum_mark'] + 0);
                                                } elseif ($rule['type_code'] === 'BOARD') {
                                                    echo "Percent: " . ($rule['board_pr_minimum'] + 0) . "% - " . ($rule['board_pr_maximum'] + 0) . "%";
                                                } else {
                                                    echo "N/A";
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?php
                                                if ($rule['discount_type'] === 'percentage'): ?>
                                                    <span class="badge bg-info text-dark"><?php
                                                    echo ($rule['scholarship_discount_amount'] + 0); ?>%</span>
                                                    <?php
                                                else: ?>
                                                    <span
                                                        class="badge bg-success">₹<?php
                                                        echo formatIndianCurrency($rule['scholarship_discount_amount']); ?></span>
                                                    <?php
                                                endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                if ($rule['is_active']): ?>
                                                    <span class="badge bg-success">Active</span>
                                                    <?php
                                                else: ?>
                                                    <span class="badge bg-secondary">Inactive</span>
                                                    <?php
                                                endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-warning edit-btn" data-id="<?php
                                                echo $rule['id']; ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger delete-btn" data-id="<?php
                                                echo $rule['id']; ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
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
        </div>
    </div>
</main>

<!-- Add Scholarship Rule Modal -->
<div class="modal fade" id="addRuleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalTitle"><i class="fas fa-plus"></i> Add Scholarship Rule</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="ruleForm" method="POST">
                <input type="hidden" name="id" id="rule_id">
                <input type="hidden" name="action" id="form_action" value="add">

                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Scholarship Type <span class="text-danger">*</span></label>
                            <select name="scholarship_type_id" id="scholarship_type_id" class="form-control" required>
                                <option value="">-- Select Type --</option>
                                <?php
                                foreach ($scholarship_types as $type): ?>
                                    <option value="<?php
                                    echo $type['id']; ?>" data-code="<?php
                                      echo $type['type_code']; ?>">
                                        <?php
                                        echo htmlspecialchars($type['type_name'] ?? ''); ?>
                                    </option>
                                    <?php
                                endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select name="is_active" id="is_active" class="form-control">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Standard <span class="text-danger">*</span></label>
                            <select name="course_id" id="course_id" class="form-control" required>
                                <option value="">-- Select Standard --</option>
                                <?php
                                foreach ($courses as $course): ?>
                                    <option value="<?php
                                    echo $course['id']; ?>">
                                        <?php
                                        echo htmlspecialchars($course['course_name'] ?? ''); ?>
                                    </option>
                                    <?php
                                endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Group</label>
                            <select name="group_id" id="group_id" class="form-control" required>
                                <option value="">-- Select Group --</option>
                                <?php
                                foreach ($groups as $group): ?>
                                    <option value="<?php
                                    echo $group['id']; ?>">
                                        <?php
                                        echo htmlspecialchars($group['group_name'] ?? ''); ?>
                                    </option>
                                    <?php
                                endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div id="criteria_section">
                        <h6 class="text-primary mt-2">Eligibility Criteria</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3" id="gmsat_min_container">
                                <label class="form-label">Min Range (Marks/%) <span class="text-danger">*</span></label>
                                <input type="number" name="min_range" id="min_range" class="form-control" step="0.01"
                                    required>
                            </div>
                            <div class="col-md-6 mb-3" id="gmsat_max_container">
                                <label class="form-label">Max Range (Marks/%) <span class="text-danger">*</span></label>
                                <input type="number" name="max_range" id="max_range" class="form-control" step="0.01"
                                    required>
                            </div>
                        </div>
                    </div>

                    <h6 class="text-primary mt-2">Discount Benefits</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Discount Benefit Type <span class="text-danger">*</span></label>
                            <select name="discount_type" id="discount_type" class="form-control" required>
                                <option value="percentage">Percentage (%)</option>
                                <option value="amount">Fixed Amount (?)</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Discount Value <span class="text-danger">*</span></label>
                            <input type="number" name="scholarship_discount_amount" id="scholarship_discount_amount"
                                class="form-control" step="0.01" required>
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">Save Rule</button>
                </div>
            </form>
        </div>
    </div>
</div>



<!-- Bulk Upload Modal -->
<div class="modal fade" id="bulkUploadModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-upload"></i> Bulk Upload Scholarship Rules</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="bulkUploadForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Instructions:</strong>
                        <ul class="mb-0 mt-2">
                            <li>Download the CSV template using the "Download Template" button</li>
                            <li>Fill in the scholarship rules data in the CSV file</li>
                            <li>Upload the completed CSV file below</li>
                            <li>Ensure all required fields are filled correctly</li>
                        </ul>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Upload CSV File <span class="text-danger">*</span></label>
                        <input type="file" name="bulk_file" id="bulk_file" class="form-control" accept=".csv" required>
                        <small class="text-muted">Accepted format: CSV only</small>
                    </div>

                    <div id="upload_preview" class="d-none">
                        <h6 class="text-primary">File Preview:</h6>
                        <div id="preview_content" class="table-responsive">
                            <!-- Preview will be shown here -->
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-success" id="uploadBtn">
                        <i class="fas fa-upload"></i> Upload & Process
                    </button>
                </div>
            </form>
        </div>
        </div>

<?php
include '../../include/footer.php'; ?>

<script>
    $(document).ready(function () {
        // Move modals to body to prevent z-index/stacking context issues
        $('#addRuleModal').appendTo("body");
        $('#bulkUploadModal').appendTo("body");

        // Download Template Function
        window.downloadTemplate = function () {
            // Download CSV template from backend
            window.location.href = '<?php echo BACKEND_URL; ?>/controllers/scholarships/download-template.php';
        };

        // File Preview on Selection
        $('#bulk_file').on('change', function (e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    const text = e.target.result;
                    const lines = text.split('\n').slice(0, 6); // Show first 5 rows + header

                    let table = '<table class="table table-sm table-bordered"><thead><tr>';
                    const headers = lines[0].split(',');
                    headers.forEach(h => table += `<th>${h.trim()}</th>`);
                    table += '</tr></thead><tbody>';

                    for (let i = 1; i < lines.length && i < 6; i++) {
                        if (lines[i].trim()) {
                            table += '<tr>';
                            const cols = lines[i].split(',');
                            cols.forEach(c => table += `<td>${c.trim()}</td>`);
                            table += '</tr>';
                        }
                    }
                    table += '</tbody></table>';

                    $('#preview_content').html(table);
                    $('#upload_preview').removeClass('d-none');
                };
                reader.readAsText(file);
            }
        });

        // Bulk Upload Form Submission
        $('#bulkUploadForm').on('submit', function (e) {
            e.preventDefault();

            const formData = new FormData(this);

            // Show loading
            $('#uploadBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');

            $.ajax({
                url: '<?php echo BACKEND_URL; ?>/controllers/scholarships/bulk-upload.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    $('#uploadBtn').prop('disabled', false).html('<i class="fas fa-upload"></i> Upload & Process');

                    if (response.success) {
                        const successCount = response.data.success_count || 0;
                        const errorCount = response.data.error_count || 0;
                        const errors = response.data.errors || [];

                        let message = `Successfully imported ${successCount} scholarship rules.`;
                        if (errorCount > 0) {
                            message += `<br>${errorCount} rows had errors:<br><ul class="mb-0 mt-2">`;
                            errors.forEach(err => {
                                message += `<li>Row ${err.row}: ${err.message}</li>`;
                            });
                            message += '</ul>';
                        }

                        showToast(errorCount > 0 ? 'warning' : 'success', errorCount > 0 ? 'Partially Completed' : 'Success', message);
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        showToast('error', 'Error', response.message || 'Failed to process file.');
                    }
                },
                error: function (xhr) {
                    $('#uploadBtn').prop('disabled', false).html('<i class="fas fa-upload"></i> Upload & Process');
                    const response = xhr.responseJSON || {};
                    showToast('error', 'Error', response.message || 'An error occurred during upload.');
                }
            });
        });

        // Edit Button Click
        $('.edit-btn').click(function () {
            const id = $(this).data('id');
            $.get('scholarship-rules.php?get_rule=' + id, function (data) {
                $('#rule_id').val(data.id);
                $('#form_action').val('edit');
                $('#modalTitle').html('<i class="fas fa-edit"></i> Edit Scholarship Rule');
                $('#submitBtn').text('Update Rule');

                $('#scholarship_type_id').val(data.scholarship_type_id);
                $('#is_active').val(data.is_active);
                $('#course_id').val(data.course_id);
                $('#group_id').val(data.group_id);
                $('#discount_type').val(data.discount_type);
                $('#scholarship_discount_amount').val(data.scholarship_discount_amount);

                // Determine which range to show
                if (data.gmsat_minimum_mark != null && data.gmsat_minimum_mark != '0.00') {
                    $('#min_range').val(data.gmsat_minimum_mark);
                    $('#max_range').val(data.gmsat_maximum_mark);
                } else if (data.board_pr_minimum != null && data.board_pr_minimum != '0.00') {
                    $('#min_range').val(data.board_pr_minimum);
                    $('#max_range').val(data.board_pr_maximum);
                } else {
                    $('#min_range').val('');
                    $('#max_range').val('');
                }

                $('#addRuleModal').modal('show');
            });
        });

        // Delete Button Click
        $('.delete-btn').click(function () {
            const id = $(this).data('id');
            showConfirm({
                title: 'Delete Rule',
                message: 'Are you sure you want to delete this scholarship rule? This action cannot be undone.',
                confirmText: 'Yes, Delete',
                confirmButtonClass: 'btn-danger',
                onConfirm: function () {
                    const formData = {
                        id: id,
                        action: 'delete'
                    };
                    $.api.post('scholarships/rule-save', formData).then(response => {
                        if (response.success) {
                            showToast('success', 'Deleted!', response.message || 'Rule deleted.');
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            showToast('error', 'Error', response.error || response.message || 'Failed to delete.');
                        }
                    }).catch(error => {
                        showToast('error', 'Error', error.message || 'An error occurred.');
                    });
                }
            });
        });

        // Reset modal on close
        $('#addRuleModal').on('hidden.bs.modal', function () {
            $('#ruleForm')[0].reset();
            $('#rule_id').val('');
            $('#form_action').val('add');
            $('#modalTitle').html('<i class="fas fa-plus"></i> Add Scholarship Rule');
            $('#submitBtn').text('Save Rule');
        });

        // Rule Form Submission (Add/Edit)
        $('#ruleForm').on('submit', function (e) {
            e.preventDefault();
            const formData = {};
            $(this).serializeArray().forEach(item => {
                formData[item.name] = item.value;
            });

            $.api.post('scholarships/rule-save', formData).then(response => {
                if (response.success) {
                    showToast('success', 'Success', response.message || 'Rule saved successfully.');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast('error', 'Error', response.error || response.message || 'Failed to save rule.');
                }
            }).catch(error => {
                showToast('error', 'Error', error.message || 'An error occurred.');
            });
        });
    });
</script>