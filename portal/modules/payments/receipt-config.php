<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once __DIR__ . '/../../common/api_client.php';
require_once PORTAL_GLOBALVARIABLE;

// Auth check (moved from controller)
if (!hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$api = new APIClient();
$response = $api->get('payments/receipt-config');

if ($response && isset($response['success']) && $response['success']) {
    $receipts = $response['data']['receipts'] ?? [];
} else {
    $receipts = [];
    set_flash_message('error', $response['error'] ?? 'Failed to load receipt configurations');
}

$page_title = "Receipt Configuration";

include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>



    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div></div> <!-- Spacer -->
            <div>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="fas fa-plus"></i> Add Receipt Configuration
                </button>
            </div>
        </div>
        <div class="card shadow-sm">
            <div class="card-body">
                <table class="table table-bordered table-hover" id="dataTable">
                    <thead class="table-primary-custom">
                        <tr>
                            <th width="5%">#</th>
                            <th width="20%">Organization Name</th>
                            <th width="15%">Receipt Title</th>
                            <th width="12%">GST Number</th>
                            <th width="12%">Contact</th>
                            <th width="8%">Status</th>
                            <th width="8%">Default</th>
                            <th width="10%">Created By</th>
                            <th width="10%">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ($receipts as $index => $receipt): ?>
                            <tr>
                                <td><?php
                                echo $index + 1; ?></td>
                                <td><strong><?php
                                echo htmlspecialchars($receipt['organization_name'] ?? ''); ?></strong></td>
                                <td><?php
                                echo htmlspecialchars($receipt['receipt_title'] ?? ''); ?></td>
                                <td><?php
                                echo htmlspecialchars($receipt['gst_number'] ?? '-'); ?></td>
                                <td>
                                    <?php
                                    if ($receipt['phone']): ?>
                                        <i class="fas fa-phone text-primary"></i>
                                        <?php
                                        echo htmlspecialchars($receipt['phone'] ?? ''); ?><br>
                                        <?php
                                    endif; ?>
                                    <?php
                                    if ($receipt['email']): ?>
                                        <i class="fas fa-envelope text-info"></i>
                                        <?php
                                        echo htmlspecialchars($receipt['email'] ?? ''); ?>
                                        <?php
                                    endif; ?>
                                </td>
                                <td>
                                    <?php
                                    if ($receipt['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                        <?php
                                    else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                        <?php
                                    endif; ?>
                                </td>
                                <td>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" <?php
                                        echo $receipt['is_active'] ? 'checked' : ''; ?> onchange="setDefault(<?php
                                               echo $receipt['id']; ?>, this.checked)">
                                    </div>
                                </td>
                                <td><?php
                                echo htmlspecialchars($receipt['created_by_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <a href="receipt-config-preview.php?id=<?php
                                    echo $receipt['id']; ?>" class="btn btn-sm btn-success"
                                        title="Preview Receipt Layout (HTML)" target="_blank">
                                        <i class="fas fa-receipt"></i>
                                    </a>
                                    <a href="receipt-config-preview-pdf.php?id=<?php
                                    echo $receipt['id']; ?>" class="btn btn-sm btn-danger" title="Preview Receipt PDF"
                                        target="_blank">
                                        <i class="fas fa-file-pdf"></i>
                                    </a>
                                    <button class="btn btn-sm btn-info" onclick="viewReceipt(<?php
                                    echo $receipt['id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-warning" onclick="editReceipt(<?php
                                    echo $receipt['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteReceipt(<?php
                                    echo $receipt['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php
                        endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary-custom text-white">
                <h5 class="modal-title" id="modalTitle"><i class="fas fa-plus"></i> Add Receipt Configuration</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="receiptConfigForm" enctype="multipart/form-data">
                <input type="hidden" name="id" id="receipt_id">
                <div class="modal-body">
                    <ul class="nav nav-tabs mb-3" id="receiptTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="org-tab" data-bs-toggle="tab"
                                data-bs-target="#org-details" type="button">
                                <i class="fas fa-building"></i> Organization
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="contact-tab" data-bs-toggle="tab"
                                data-bs-target="#contact-details" type="button">
                                <i class="fas fa-address-card"></i> Contact
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="media-tab" data-bs-toggle="tab" data-bs-target="#media-details"
                                type="button">
                                <i class="fas fa-image"></i> Logo & Signature
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="additional-tab" data-bs-toggle="tab"
                                data-bs-target="#additional-details" type="button">
                                <i class="fas fa-info-circle"></i> Additional Info
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <!-- Organization Details Tab -->
                        <div class="tab-pane fade show active" id="org-details">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Receipt Title <span
                                                class="text-danger">*</span></label>
                                        <input type="text" name="receipt_title" id="receipt_title" class="form-control"
                                            placeholder="Fee Receipt" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Organization Name <span
                                                class="text-danger">*</span></label>
                                        <input type="text" name="organization_name" id="organization_name"
                                            class="form-control" placeholder="XYZ Counselling Institute" required>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Organization Type <span
                                                class="text-danger">*</span></label>
                                        <select name="organization_type" id="organization_type" class="form-select"
                                            required>
                                            <option value="other">Other</option>
                                            <option value="school">School</option>
                                            <option value="trust">Trust</option>
                                            <option value="hostel">Hostel</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">GST Number</label>
                                        <input type="text" name="gst_number" id="gst_number" class="form-control"
                                            placeholder="22AAAAA0000A1Z5">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">PAN Number</label>
                                        <input type="text" name="pan_number" id="pan_number" class="form-control"
                                            placeholder="ABCDE1234F">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Contact Details Tab -->
                        <div class="tab-pane fade" id="contact-details">
                            <div class="mb-3">
                                <label class="form-label">Address</label>
                                <textarea name="address" id="address" class="form-control" rows="2"
                                    placeholder="Complete address"></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">City</label>
                                        <input type="text" name="city" id="city" class="form-control"
                                            placeholder="Ahmedabad">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">State</label>
                                        <input type="text" name="state" id="state" class="form-control"
                                            placeholder="Gujarat">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Pincode</label>
                                        <input type="text" name="pincode" id="pincode" class="form-control"
                                            placeholder="380001">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Phone</label>
                                        <input type="text" name="phone" id="phone" class="form-control"
                                            placeholder="+91 1234567890">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" name="email" id="email" class="form-control"
                                            placeholder="info@example.com">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Website</label>
                                        <input type="text" name="website" id="website" class="form-control"
                                            placeholder="www.example.com">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Logo & Signature Tab -->
                        <div class="tab-pane fade" id="media-details">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Organization Logo</label>
                                        <input type="file" name="logo" id="logo" class="form-control" accept="image/*"
                                            onchange="previewImage(this, 'logoPreview')">
                                        <small class="text-muted">Recommended: 200x80 pixels (PNG/JPG)</small>
                                        <div class="mt-2">
                                            <img id="logoPreview" src="" alt="Logo Preview" class="img-thumbnail d-none css-receipt-config-196f5c">
                                            <input type="hidden" name="existing_logo" id="existing_logo">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Authorized Signature</label>
                                        <input type="file" name="signature" id="signature" class="form-control"
                                            accept="image/*" onchange="previewImage(this, 'signaturePreview')">
                                        <small class="text-muted">Recommended: 200x80 pixels (PNG/JPG)</small>
                                        <div class="mt-2">
                                            <img id="signaturePreview" src="" alt="Signature Preview"
                                                class="img-thumbnail d-none css-receipt-config-196f5c">
                                            <input type="hidden" name="existing_signature" id="existing_signature">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Authorized Signatory Name</label>
                                        <input type="text" name="authorized_signatory" id="authorized_signatory"
                                            class="form-control" placeholder="John Doe">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Designation</label>
                                        <input type="text" name="designation" id="designation" class="form-control"
                                            placeholder="Director / Principal">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Additional Information Tab -->
                        <div class="tab-pane fade" id="additional-details">
                            <div class="mb-3">
                                <label class="form-label">Footer Text</label>
                                <textarea name="footer_text" id="footer_text" class="form-control" rows="2"
                                    placeholder="Thank you for your payment!"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Terms & Conditions</label>
                                <textarea name="terms_conditions" id="terms_conditions" class="form-control" rows="4"
                                    placeholder="1. Fees once paid will not be refunded.&#10;2. Receipt should be preserved for future reference."></textarea>
                            </div>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input type="checkbox" name="is_active" value="1" class="form-check-input"
                                        id="is_active">
                                    <label class="form-check-label" for="is_active">Set as Active/Default</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary-custom">
                        <i class="fas fa-save"></i> Save Configuration
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Modal -->
<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info-custom text-white">
                <h5 class="modal-title"><i class="fas fa-eye"></i> View Receipt Configuration</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewContent">
                <!-- Content loaded via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
        </div>

<?php
include '../../include/footer.php'; ?>

<script>
    $(document).ready(function () {
        // Move modals to body to prevent z-index issues
        $('#addModal').appendTo("body");
        $('#viewModal').appendTo("body");
    });

    function previewImage(input, previewId) {
        const preview = document.getElementById(previewId);
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function (e) {
                preview.src = e.target.result;
                preview.classList.remove('d-none');
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    $('#receiptConfigForm').on('submit', function (e) {
        e.preventDefault();

        const formData = new FormData(this);

        const $submitBtn = $(this).find('button[type="submit"]');
        const originalBtnText = $submitBtn.html();
        $submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i> Saving...');

        $.api.post('payments/receipt-config-save', formData, {
            processData: false,
            contentType: false
        }).then(response => {
            if (response.success) {
                showToast('success', 'Success', response.message);
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast('error', 'Error', response.message || 'Failed to save configuration');
                $submitBtn.prop('disabled', false).html(originalBtnText);
            }
        }).catch(error => {
            showToast('error', 'Error', error.message || 'Failed to save configuration');
            $submitBtn.prop('disabled', false).html(originalBtnText);
        });
    });

    function editReceipt(id) {
        $.api.get('payments/receipt-config-get', {
            id: id
        }).then(data => {
            if (data.success) {
                const config = data.data;

                $('#modalTitle').html('<i class="fas fa-edit"></i> Edit Receipt Configuration');
                $('#receipt_id').val(config.id);
                $('#receipt_title').val(config.receipt_title);
                $('#organization_name').val(config.organization_name);
                $('#organization_type').val(config.organization_type || 'other');
                $('#gst_number').val(config.gst_number);
                $('#pan_number').val(config.pan_number);
                $('#address').val(config.address);
                $('#city').val(config.city);
                $('#state').val(config.state);
                $('#pincode').val(config.pincode);
                $('#phone').val(config.phone);
                $('#email').val(config.email);
                $('#website').val(config.website);
                $('#authorized_signatory').val(config.authorized_signatory);
                $('#designation').val(config.designation);
                $('#footer_text').val(config.footer_text);
                $('#terms_conditions').val(config.terms_conditions);
                $('#is_active').prop('checked', config.is_active == 1);

                if (config.logo_path) {
                    const logoUrl = config.logo_path.startsWith('http') ? config.logo_path : '<?php echo BACKEND_URL; ?>/' + config.logo_path;
                    $('#logoPreview').attr('src', logoUrl).removeClass('d-none');
                    $('#existing_logo').val(config.logo_path);
                }

                if (config.signature_path) {
                    const sigUrl = config.signature_path.startsWith('http') ? config.signature_path : '<?php echo BACKEND_URL; ?>/' + config.signature_path;
                    $('#signaturePreview').attr('src', sigUrl).removeClass('d-none');
                    $('#existing_signature').val(config.signature_path);
                }

                $('#addModal').modal('show');
            } else {
                showToast('error', 'Error', data.message);
            }
        }).catch(error => {
            showToast('error', 'Error', error.message || 'Failed to load receipt configuration');
        });
    }

    function viewReceipt(id) {
        $.api.get('payments/receipt-config-view?id=' + id).then(response => {
            if (response.success && response.html) {
                $('#viewContent').html(response.html);
                $('#viewModal').modal('show');
            } else {
                showToast('error', 'Error', response.message || 'Failed to load receipt view');
            }
        }).catch(error => {
            console.error('API Error:', error);
            showToast('error', 'Error', error.message || 'Failed to load receipt');
        });
    }

    function deleteReceipt(id) {
        showConfirm({
            title: 'Are you sure?',
            text: "This receipt configuration will be deleted!",
            icon: 'warning',
            confirmButtonText: 'Yes, delete it!',
            confirmButtonColor: '#d33',
            onConfirm: () => {
                $.api.post('payments/receipt-config-delete', {
                    id: id
                }).then(response => {
                    if (response.success) {
                        showToast('success', 'Deleted!', response.message);
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showToast('error', 'Error!', response.message);
                    }
                }).catch(error => {
                    showToast('error', 'Error!', error.message || 'Failed to delete receipt');
                });
            }
        });
    }

    function setDefault(id, isActive) {
        $.api.post('payments/receipt-config-set-default', {
            id: id,
            is_active: isActive ? 1 : 0
        }).then(response => {
            if (response.success) {
                showToast('success', 'Success', response.message);
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast('error', 'Error', response.message);
            }
        }).catch(error => {
            showToast('error', 'Error', error.message || 'Failed to update default status');
        });
    }

    // Reset form when modal is closed
    $('#addModal').on('hidden.bs.modal', function () {
        $('#receiptConfigForm')[0].reset();
        $('#receipt_id').val('');
        $('#modalTitle').html('<i class="fas fa-plus"></i> Add Receipt Configuration');
        $('#logoPreview, #signaturePreview').addClass('d-none').attr('src', '');
        $('#existing_logo, #existing_signature').val('');
        // Return to first tab
        $('#org-tab').tab('show');
    });
</script>