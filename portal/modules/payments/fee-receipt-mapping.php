<?php

require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once __DIR__ . '/../../common/api_client.php';

// Load fee receipt mappings via API
$api = new APIClient();
$response = $api->get('payments/fee-receipt-mapping');

// Extract data from API response
if ($response && isset($response['success']) && $response['success']) {
    $data = $response['data'] ?? [];
    $mappings = $data['mappings'] ?? [];
} else {
    // Fallback to default values if API fails
    $mappings = [];
}

include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>


<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="fas fa-link me-2"></i>Fee-Receipt Mapping</h4>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="fas fa-plus"></i> Add Mapping
    </button>
</div>
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

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Fee Type to Receipt Configuration Mappings</h3>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="dataTable">
                <thead class="table-primary-custom">
                    <tr>
                        <th width="5%">#</th>
                        <th width="20%">Fee Type</th>
                        <th width="18%">School</th>
                        <th width="20%">Receipt Configuration</th>
                        <th width="15%">Organization Type</th>
                        <th width="12%">Status</th>
                        <th width="10%">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (empty($mappings)): ?>
                        <tr>
                            <td colspan="7" class="text-center">
                                <div class="py-3">
                                    <i class="fas fa-info-circle text-muted"></i>
                                    <p class="mb-0 text-muted">No mappings configured yet. Click "Add Mapping" to create one.</p>
                                </div>
                            </td>
                        </tr>
                    <?php
                    else: ?>
                        <?php
                        foreach ($mappings as $index => $mapping): ?>
                            <tr>
                                <td><?php
                                    echo $index + 1; ?></td>
                                <td>
                                    <strong><?php
                                            echo formatFeeType($mapping['fee_type']); ?></strong>
                                    <br><small class="text-muted"><?php
                                                                    echo $mapping['fee_type']; ?></small>
                                </td>
                                <td>
                                    <?php
                                    if ($mapping['school_id']): ?>
                                        <i class="fas fa-school text-primary"></i>
                                        <?php
                                        echo htmlspecialchars($mapping['school_name'] ?? ''); ?>
                                    <?php
                                    else: ?>
                                        <span class="badge bg-secondary">All Schools</span>
                                    <?php
                                    endif; ?>
                                </td>
                                <td>
                                    <strong><?php
                                            echo htmlspecialchars($mapping['organization_name'] ?? ''); ?></strong>
                                    <br><small class="text-muted"><?php
                                                                    echo htmlspecialchars($mapping['receipt_title'] ?? ''); ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-info">
                                        <?php
                                        echo ucfirst($mapping['organization_type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    if ($mapping['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php
                                    else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php
                                    endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-sm btn-warning"
                                            onclick="editMapping(<?php
                                                                    echo htmlspecialchars(json_encode($mapping) ?? ''); ?>)"
                                            title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger"
                                            onclick="deleteMapping(<?php
                                                                    echo $mapping['id']; ?>)"
                                            title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php
                        endforeach; ?>
                    <?php
                    endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Info Card -->
<div class="card mt-3">
    <div class="card-header bg-info">
        <h3 class="card-title text-white"><i class="fas fa-info-circle"></i> How It Works</h3>
    </div>
    <div class="card-body">
        <ul class="mb-0">
            <li><strong>School Fee:</strong> Can be mapped to specific school receipts (one per school)</li>
            <li><strong>Trust Facilities Fee:</strong> Typically mapped to trust organization receipt (applies to all schools)</li>
            <li><strong>Hostel Fee:</strong> Mapped to hostel facility receipt (applies to all schools)</li>
            <li><strong>Priority:</strong> School-specific mappings take priority over generic mappings</li>
            <li><strong>Fallback:</strong> If no mapping exists, the default active receipt configuration is used</li>
        </ul>
    </div>
</div>

<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="addForm" action="fee-receipt-mapping-save.php" method="POST">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Add Fee-Receipt Mapping</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Fee Type <span class="text-danger">*</span></label>
                            <select name="fee_type" class="form-select" required>
                                <option value="">-- Select Fee Type --</option>
                                <?php
                                foreach (getFeeTypes() as $key => $label): ?>
                                    <option value="<?php
                                                    echo $key; ?>"><?php
                                                                    echo $label; ?></option>
                                <?php
                                endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">School (Optional)</label>
                            <select name="school_id" class="form-select">
                                <option value="">-- All Schools --</option>
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
                            <small class="form-text text-muted">Leave blank for global mapping (all schools)</small>
                        </div>

                        <div class="col-md-12 mb-3">
                            <label class="form-label">Receipt Configuration <span class="text-danger">*</span></label>
                            <select name="receipt_config_id" class="form-select" required>
                                <option value="">-- Select Receipt Configuration --</option>
                                <?php
                                foreach ($receipt_configs as $config): ?>
                                    <option value="<?php
                                                    echo $config['id']; ?>">
                                        <?php
                                        echo htmlspecialchars($config['organization_name'] ?? ''); ?>
                                        - <?php
                                            echo htmlspecialchars($config['receipt_title'] ?? ''); ?>
                                        (<?php
                                            echo ucfirst($config['organization_type']); ?>)
                                    </option>
                                <?php
                                endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-lightbulb"></i> <strong>Tip:</strong>
                        <ul class="mb-0 mt-2">
                            <li>For school-specific fees, select both the school and receipt configuration</li>
                            <li>For fees applicable to all schools (trust, hostel), leave school blank</li>
                            <li>Duplicate fee type + school combinations will update existing mapping</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Mapping
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Mapping Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="editForm" action="fee-receipt-mapping-update.php" method="POST">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Fee-Receipt Mapping</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Fee Type <span class="text-danger">*</span></label>
                            <select name="fee_type" id="edit_fee_type" class="form-select" required>
                                <?php
                                foreach (getFeeTypes() as $key => $label): ?>
                                    <option value="<?php
                                                    echo $key; ?>"><?php
                                                                    echo $label; ?></option>
                                <?php
                                endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">School (Optional)</label>
                            <select name="school_id" id="edit_school_id" class="form-select">
                                <option value="">-- All Schools --</option>
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
                        </div>

                        <div class="col-md-12 mb-3">
                            <label class="form-label">Receipt Configuration <span class="text-danger">*</span></label>
                            <select name="receipt_config_id" id="edit_receipt_config_id" class="form-select" required>
                                <?php
                                foreach ($receipt_configs as $config): ?>
                                    <option value="<?php
                                                    echo $config['id']; ?>">
                                        <?php
                                        echo htmlspecialchars($config['organization_name'] ?? ''); ?>
                                        - <?php
                                            echo htmlspecialchars($config['receipt_title'] ?? ''); ?>
                                    </option>
                                <?php
                                endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-save"></i> Update Mapping
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
include '../../include/footer.php'; ?>

<script>
    function editMapping(mapping) {
        $('#edit_id').val(mapping.id);
        $('#edit_fee_type').val(mapping.fee_type);
        $('#edit_school_id').val(mapping.school_id || '');
        $('#edit_receipt_config_id').val(mapping.receipt_config_id);
        $('#editModal').modal('show');
    }

    function deleteMapping(id) {
        if (confirm('Are you sure you want to delete this mapping?')) {
            window.location.href = 'fee-receipt-mapping-delete.php?id=' + id;
        }
    }

    $(document).ready(function() {
        // Move modals to body to prevent z-index issues
        $('#addModal').appendTo("body");
        $('#editModal').appendTo("body");
    });
</script>