<?php

require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once __DIR__ . '/../../common/api_client.php';
require_once __DIR__ . '/../../../common/helpers/format_helper.php';

// Load transport fee configuration via API
$api = new APIClient();
$response = $api->get('transport/fee-config');

// Extract data from API response
if ($response && isset($response['success']) && $response['success']) {
    $data = $response['data'] ?? [];
    $transport_settings = $data['transport_settings'] ?? [];
    $academic_years = $data['academic_years'] ?? [];
    $courses = $data['courses'] ?? [];
} else {
    // Fallback to default values if API fails
    $transport_settings = [];
    $academic_years = [];
}

$page_title = "Transport Fee Configuration";
include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>




<div class="container-fluid">
    <div class="row mb-2">
        <div class="col-sm-6">
            <h1 class="m-0"><?php echo $page_title; ?></h1>
        </div>
        <div class="col-sm-6 text-end">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addConfigModal">
                <i class="fas fa-plus"></i> Add Configuration
            </button>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Transport Settings List</h3>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped mb-0">
                    <thead>
                        <tr>
                            <th>Academic Year</th>
                            <th>Standard</th>
                            <th>Timeline</th>
                            <th>Monthly Fee</th>
                            <th>Months (T1/T2/An)</th>
                            <th>GST Rate</th>
                            <th>Status</th>
                            <th>Description</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transport_settings)): ?>
                            <tr>
                                <td colspan="9" class="text-center">No configurations found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($transport_settings as $config): ?>
                                <tr>
                                    <td><?php echo $config['academic_year']; ?></td>
                                    <td><?php echo $config['course_name'] ?: '<span class="text-muted">All Standards</span>'; ?></td>
                                    <td><span class="badge bg-info"><?php echo $config['collection_timeline'] ?? 'Term-wise'; ?></span></td>
                                    <td>₹<?php echo formatIndianCurrency($config['transport_fee']); ?></td>
                                    <td><?php echo "{$config['term1_months']} / {$config['term2_months']} / {$config['annual_months']}"; ?></td>
                                    <td><?php echo $config['gst_rate']; ?>%</td>
                                    <td>
                                        <?php echo $config['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>'; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($config['description'] ?? ''); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-warning"
                                            onclick='editConfig(<?php echo json_encode($config); ?>)'>
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- Add Config Modal -->
<div class="modal fade" id="addConfigModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Transport Fee Configuration</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addConfigForm" onsubmit="return saveConfig(event, 'add')">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Academic Year <span class="text-danger">*</span></label>
                                <select name="academic_year_id" class="form-control" required>
                                    <option value="">Select Academic Year</option>
                                    <?php foreach ($academic_years as $ay): ?>
                                        <option value="<?php echo $ay['id']; ?>"><?php echo $ay['year_name'] ?? ''; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Standard (Optional)</label>
                                <select name="course_id" class="form-control">
                                    <option value="">All Standards</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?php echo $course['id']; ?>"><?php echo $course['course_name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Leave empty to apply to all standards by default</small>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Monthly Fee <span class="text-danger">*</span></label>
                                <input type="number" name="transport_fee" class="form-control" step="0.01" min="0"
                                    required placeholder="e.g., 1800">
                            </div>
                        </div>
                        <div class="col-md-6">

                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Term 1 Months</label>
                                <input type="number" name="term1_months" class="form-control" value="7">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Term 2 Months</label>
                                <input type="number" name="term2_months" class="form-control" value="6">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Annual Months</label>
                                <input type="number" name="annual_months" class="form-control" value="12">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Timeline <span class="text-danger">*</span></label>
                                <select name="collection_timeline" class="form-control" required>
                                    <option value="Term-wise">Term-wise (Custom)</option>
                                    <option value="Monthly">Monthly</option>
                                    <option value="Quarterly">Quarterly</option>
                                    <option value="Half-Yearly">Half-Yearly</option>
                                    <option value="Annually">Annually</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>GST Rate (%)</label>
                                <input type="number" name="gst_rate" class="form-control" step="0.01" min="0" max="100"
                                    value="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="custom-control custom-switch mt-4">
                                    <input type="checkbox" class="custom-control-input" id="add_is_active"
                                        name="is_active" value="1" checked>
                                    <label class="custom-control-label" for="add_is_active">Active</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" class="form-control" rows="3"
                            placeholder="Optional notes about transport arrangements, routes, etc."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Configuration</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Config Modal -->
<div class="modal fade" id="editConfigModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Transport Fee Configuration</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editConfigForm" onsubmit="return saveConfig(event, 'edit')">
                <input type="hidden" name="config_id" id="edit_config_id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Academic Year <span class="text-danger">*</span></label>
                                <select name="academic_year_id" id="edit_academic_year_id" class="form-control"
                                    required>
                                    <option value="">Select Academic Year</option>
                                    <?php foreach ($academic_years as $ay): ?>
                                        <option value="<?php echo $ay['id']; ?>"><?php echo $ay['year_name'] ?? ''; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Standard (Optional)</label>
                                <select name="course_id" id="edit_course_id" class="form-control">
                                    <option value="">All Standards</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?php echo $course['id']; ?>"><?php echo $course['course_name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Monthly Fee <span class="text-danger">*</span></label>
                                <input type="number" name="transport_fee" id="edit_transport_fee" class="form-control"
                                    step="0.01" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Collection Timeline <span class="text-danger">*</span></label>
                                <select name="collection_timeline" id="edit_collection_timeline" class="form-control"
                                    required>
                                    <option value="Term-wise">Term-wise (Custom)</option>
                                    <option value="Monthly">Monthly</option>
                                    <option value="Quarterly">Quarterly</option>
                                    <option value="Half-Yearly">Half-Yearly</option>
                                    <option value="Annually">Annually</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Term 1 Months</label>
                                <input type="number" name="term1_months" id="edit_term1_months" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Term 2 Months</label>
                                <input type="number" name="term2_months" id="edit_term2_months" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Annual Months</label>
                                <input type="number" name="annual_months" id="edit_annual_months" class="form-control">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>GST Rate (%)</label>
                                <input type="number" name="gst_rate" id="edit_gst_rate" class="form-control" step="0.01"
                                    min="0" max="100">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="custom-control custom-switch mt-4">
                                    <input type="checkbox" class="custom-control-input" id="edit_is_active"
                                        name="is_active" value="1">
                                    <label class="custom-control-label" for="edit_is_active">Active</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Update Configuration</button>
                </div>
            </form>
        </div>
    </div>

    <?php include '../../include/footer.php'; ?>

    <script>
        function editConfig(config) {
            $('#edit_config_id').val(config.id);
            $('#edit_academic_year_id').val(config.academic_year_id);
            $('#edit_course_id').val(config.course_id || '');
            $('#edit_transport_fee').val(config.transport_fee);
            $('#edit_collection_timeline').val(config.collection_timeline || 'Term-wise');
            $('#edit_term1_months').val(config.term1_months);
            $('#edit_term2_months').val(config.term2_months);
            $('#edit_annual_months').val(config.annual_months);
            $('#edit_gst_rate').val(config.gst_rate);
            $('#edit_is_active').prop('checked', config.is_active == 1);
            $('#edit_description').val(config.description || '');

            $('#editConfigModal').modal('show');
        }

        function saveConfig(event, mode) {
            event.preventDefault();

            const formId = mode === 'add' ? '#addConfigForm' : '#editConfigForm';
            const form = $(formId)[0];

            if (!form.checkValidity()) {
                form.reportValidity();
                return false;
            }

            const formData = new FormData(form);

            $.api.post('transport/fee-save', Object.fromEntries(formData))
                .then(response => {
                    if (response.success) {
                        showToast('success', 'Success', mode === 'add' ? 'Configuration added successfully' : 'Configuration updated successfully');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showToast('error', 'Error', response.message || 'Failed to save configuration');
                    }
                })
                .catch(error => {
                    showToast('error', 'Error', error.message || 'An error occurred while saving');
                });

            return false;
        }

        // Move modals to body to prevent z-index issues
        $(document).ready(function () {
            $('#addConfigModal').appendTo("body");
            $('#editConfigModal').appendTo("body");
        });
    </script>