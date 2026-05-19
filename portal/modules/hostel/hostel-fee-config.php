<?php

require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once __DIR__ . '/../../common/api_client.php';
require_once __DIR__ . '/../../../common/helpers/format_helper.php';

// Load hostel fee configuration via API
$api = new APIClient();
$response = $api->get('hostel/fee-config');

// Extract data from API response
if ($response && isset($response['success']) && $response['success']) {
    $data = $response['data'] ?? [];
    $hostel_settings = $data['hostel_settings'] ?? [];
    $academic_years = $data['academic_years'] ?? [];
} else {
    // Fallback to default values if API fails
    $hostel_settings = [];
    $academic_years = [];
}

$page_title = "Hostel Fee Configuration";
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
            <h3 class="card-title">Fee Settings List</h3>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped mb-0">
                    <thead>
                        <tr>
                            <th>Academic Year</th>
                            <th>Boys Fee</th>
                            <th>Girls Fee</th>
                            <th>Security</th>
                            <th>Hostel Fee Pending</th>
                            <th>Hostel Fee (Receiptable)</th>
                            <th>Hostel Fee Cash</th>
                            <th>GST</th>
                            <th>Mess</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($hostel_settings)): ?>
                            <tr>
                                <td colspan="11" class="text-center">No configurations found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($hostel_settings as $config): ?>
                                <tr>
                                    <td><?php echo $config['academic_year']; ?></td>
                                    <?php 
                                        $hostel_total = max($config['boys_hostel_fee'], $config['girls_hostel_fee']);
                                        $pending = $hostel_total - $config['security_deposit'];
                                        $receiptable = $config['split_threshold'];
                                        $cash = max(0, $pending - $receiptable);
                                    ?>
                                    <td>₹<?php echo formatIndianCurrency($config['boys_hostel_fee']); ?></td>
                                    <td>₹<?php echo formatIndianCurrency($config['girls_hostel_fee']); ?></td>
                                    <td>₹<?php echo formatIndianCurrency($config['security_deposit']); ?></td>
                                    <td>₹<?php echo formatIndianCurrency($pending); ?></td>
                                    <td>₹<?php echo formatIndianCurrency($receiptable); ?></td>
                                    <td>₹<?php echo formatIndianCurrency($cash); ?></td>
                                    <td>
                                        <?php if ($config['gst_applicable']): ?>
                                            <span class="badge bg-info"><?php echo $config['gst_rate']; ?>%</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">No</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo $config['mess_charges_included'] ? '<span class="badge bg-success">Included</span>' : '<span class="badge bg-warning">Excluded</span>'; ?>
                                    </td>
                                    <td>
                                        <?php echo $config['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>'; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-warning"
                                            onclick='editConfig(<?php echo json_encode($config); ?>)'>
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger"
                                            onclick='deleteConfig(<?php echo $config["id"]; ?>)'>
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
    </div>

</div>

<!-- Add Config Modal -->
<div class="modal fade" id="addConfigModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Hostel Fee Configuration</h5>
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
                                    <?php
                                    foreach ($academic_years as $ay): ?>
                                        <option value="<?php
                                        echo $ay['id']; ?>"><?php
                                          echo $ay['year_name'] ?? ''; ?>
                                        </option>
                                        <?php
                                    endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Boys Hostel Fee <span class="text-danger">*</span></label>
                                <input type="number" name="boys_hostel_fee" class="form-control" step="0.01" min="0"
                                    required placeholder="e.g., 50000">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Girls Hostel Fee <span class="text-danger">*</span></label>
                                <input type="number" name="girls_hostel_fee" class="form-control" step="0.01" min="0"
                                    required placeholder="e.g., 50000">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Security Deposit</label>
                                <input type="number" name="security_deposit" class="form-control" step="0.01" min="0"
                                    value="5000" placeholder="e.g., 5000">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Hostel Fee (Receiptable)</label>
                                <input type="number" name="split_threshold" class="form-control" step="0.01" min="0"
                                    value="20000" placeholder="e.g., 20000">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>GST Applicable?</label>
                                <select name="gst_applicable" id="add_gst_applicable" class="form-control"
                                    onchange="toggleGstRate('add')">
                                    <option value="0">No</option>
                                    <option value="1">Yes</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>GST Rate (%)</label>
                                <input type="number" name="gst_rate" id="add_gst_rate" class="form-control" step="0.01"
                                    min="0" max="100" value="0" disabled>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>AC Room Extra Charge</label>
                                <input type="number" name="ac_room_extra_charge" class="form-control" step="0.01"
                                    min="0" value="0" placeholder="e.g., 10000">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="add_mess_included"
                                        name="mess_charges_included" value="1" checked>
                                    <label class="custom-control-label" for="add_mess_included">Mess Charges
                                        Included</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="add_is_active"
                                        name="is_active" value="1" checked>
                                    <label class="custom-control-label" for="add_is_active">Active</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Remarks</label>
                        <textarea name="remarks" class="form-control" rows="3"></textarea>
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
                <h5 class="modal-title">Edit Hostel Fee Configuration</h5>
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
                                    <?php
                                    foreach ($academic_years as $ay): ?>
                                        <option value="<?php
                                        echo $ay['id']; ?>"><?php
                                          echo $ay['year_name'] ?? ''; ?>
                                        </option>
                                        <?php
                                    endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Boys Hostel Fee <span class="text-danger">*</span></label>
                                <input type="number" name="boys_hostel_fee" id="edit_boys_hostel_fee"
                                    class="form-control" step="0.01" min="0" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Girls Hostel Fee <span class="text-danger">*</span></label>
                                <input type="number" name="girls_hostel_fee" id="edit_girls_hostel_fee"
                                    class="form-control" step="0.01" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Security Deposit</label>
                                <input type="number" name="security_deposit" id="edit_security_deposit"
                                    class="form-control" step="0.01" min="0">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Hostel Fee (Receiptable)</label>
                                <input type="number" name="split_threshold" id="edit_split_threshold"
                                    class="form-control" step="0.01" min="0">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>GST Applicable?</label>
                                <select name="gst_applicable" id="edit_gst_applicable" class="form-control"
                                    onchange="toggleGstRate('edit')">
                                    <option value="0">No</option>
                                    <option value="1">Yes</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>GST Rate (%)</label>
                                <input type="number" name="gst_rate" id="edit_gst_rate" class="form-control" step="0.01"
                                    min="0" max="100">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>AC Room Extra Charge</label>
                                <input type="number" name="ac_room_extra_charge" id="edit_ac_room_extra_charge"
                                    class="form-control" step="0.01" min="0">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="edit_mess_included"
                                        name="mess_charges_included" value="1">
                                    <label class="custom-control-label" for="edit_mess_included">Mess Charges
                                        Included</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="edit_is_active"
                                        name="is_active" value="1">
                                    <label class="custom-control-label" for="edit_is_active">Active</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Remarks</label>
                        <textarea name="remarks" id="edit_remarks" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Update Configuration</button>
                </div>
            </form>
        </div>
    </div>

    <?php
    include '../../include/footer.php'; ?>

    <script>
        function toggleGstRate(prefix) {
            const gstApplicable = document.getElementById(prefix + '_gst_applicable').value;
            const gstRateInput = document.getElementById(prefix + '_gst_rate');

            if (gstApplicable == '1') {
                gstRateInput.disabled = false;
                if (gstRateInput.value == '0') {
                    gstRateInput.value = '18.00';
                }
            } else {
                gstRateInput.disabled = true;
                gstRateInput.value = '0';
            }
        }

        function editConfig(config) {
            $('#edit_config_id').val(config.id);
            $('#edit_academic_year_id').val(config.academic_year_id);
            $('#edit_boys_hostel_fee').val(config.boys_hostel_fee);
            $('#edit_girls_hostel_fee').val(config.girls_hostel_fee);
            $('#edit_security_deposit').val(config.security_deposit);
            $('#edit_split_threshold').val(config.split_threshold);
            $('#edit_gst_applicable').val(config.gst_applicable);
            $('#edit_gst_rate').val(config.gst_rate);
            $('#edit_ac_room_extra_charge').val(config.ac_room_extra_charge);
            $('#edit_mess_included').prop('checked', config.mess_charges_included == 1);
            $('#edit_is_active').prop('checked', config.is_active == 1);
            $('#edit_remarks').val(config.remarks || '');

            toggleGstRate('edit');
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

            $.api.post('hostel/fee-save', Object.fromEntries(formData))
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

        function deleteConfig(id) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.api.post('hostel/fee-delete', { id: id })
                        .then(response => {
                            if (response.success) {
                                showToast('success', 'Deleted!', 'Configuration has been deleted.');
                                setTimeout(() => location.reload(), 1500);
                            } else {
                                showToast('error', 'Error', response.message || 'Failed to delete configuration');
                            }
                        })
                        .catch(error => {
                            showToast('error', 'Error', error.message || 'An error occurred while deleting');
                        });
                }
            });
        }

        // Move modals to body to prevent z-index issues
        $(document).ready(function () {
            $('#addConfigModal').appendTo("body");
            $('#editConfigModal').appendTo("body");
        });
    </script>