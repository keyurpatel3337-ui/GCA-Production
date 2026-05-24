<?php

/**
 * WhatsApp Templates Management
 * Path: portal/modules/settings/whatsapp-templates.php
 */

require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once OPERATION_FILE;
$page_title = 'WhatsApp Templates';
require_once '../../include/header.php';
require_once '../../include/navbar.php';
require_once '../../include/sidebar.php';

// Check permissions
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}
?>



<div class="container-fluid">

    <!-- Filter Card -->
    <div class="card mb-3 shadow-sm border-0">
        <div class="card-body">
            <form id="filterForm" class="row g-3">
                <div class="col-md-4">
                    <select class="form-select" name="category" id="filterCategory">
                        <option value="">All Categories</option>
                        <option value="utility">Utility</option>
                        <option value="authentication">Authentication</option>
                        <option value="marketing">Marketing</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <select class="form-select" name="status" id="filterStatus">
                        <option value="">All Approval Status</option>
                        <option value="draft">Draft</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-1"></i> Search
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Templates List -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="templatesTable">
                    <thead class="bg-light">
                        <tr>
                            <th style="width: 40px;" class="ps-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="selectAll">
                                </div>
                            </th>
                            <th>Name / Category</th>
                            <th>Body Preview</th>
                            <th>Variables</th>
                            <th>Approval</th>
                            <th>Status</th>
                            <th class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="templatesList">
                        <!-- Populated by JS -->
                        <tr>
                            <td colspan="7" class="text-center p-5">
                                <div class="spinner-border text-primary" role="status"></div>
                                <div class="mt-2">Loading templates...</div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>


<!-- Add/Edit Modal -->
<div class="modal fade" id="templateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalTitle">Add WhatsApp Template</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="templateForm">
                <div class="modal-body">
                    <input type="hidden" name="id" id="templateId">
                    <!-- Provider is now set statically in code, no need for user selection -->
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label fw-bold">Category <span class="text-danger">*</span></label>
                            <select name="template_category" id="templateCategory" class="form-select" required>
                                <option value="utility">Utility</option>
                                <option value="authentication">Authentication</option>
                                <option value="marketing">Marketing</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold">Template Name <span class="text-danger">*</span></label>
                            <input type="text" name="template_name" id="templateName" class="form-control" required
                                placeholder="e.g. welcome_message">
                            <small class="text-muted">No spaces allowed. Use underscores.</small>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold">Template Body <span class="text-danger">*</span></label>
                            <textarea name="body_text" id="bodyText" class="form-control" rows="4" required
                                placeholder="Hello {{1}}, welcome to {{2}}!"></textarea>
                            <small class="text-muted">Use {{1}}, {{2}} for variables.</small>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold">Variable Descriptions</label>
                            <input type="text" name="variable_names" id="variableNames" class="form-control"
                                placeholder="e.g. Student Name, School Name">
                            <small class="text-muted">Comma separated names for variables (for internal
                                reference).</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Header Type</label>
                            <select name="header_type" id="headerType" class="form-select">
                                <option value="none">None</option>
                                <option value="text">Text</option>
                                <option value="image">Image</option>
                                <option value="video">Video</option>
                                <option value="document">Document</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Approval Status</label>
                            <select name="approval_status" id="approvalStatus" class="form-select">
                                <option value="draft">Draft</option>
                                <option value="pending">Pending</option>
                                <option value="approved" class="text-success">Approved</option>
                                <option value="rejected" class="text-danger">Rejected</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="is_active" id="isActive" value="1"
                                    checked>
                                <label class="form-check-label fw-bold" for="isActive">Is Active</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveBtn">Save Template</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Upload Modal -->
<div class="modal fade" id="bulkUploadModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="fas fa-file-upload"></i> Bulk Upload WhatsApp Templates</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <strong>Instructions:</strong>
                    <ol class="mb-0 mt-2">
                        <li>Download the CSV template using the "Download CSV Template" button</li>
                        <li>Fill in the template with your WhatsApp template data</li>
                        <li>Save the file in CSV format</li>
                        <li>Upload the completed CSV file below</li>
                    </ol>
                    <div class="mt-2">
                        <strong>Note:</strong> Provider is set automatically from active WhatsApp provider
                        configuration.
                    </div>
                </div>

                <form id="bulkUploadForm" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Upload CSV File <span class="text-danger">*</span></label>
                        <input type="file" name="csv_file" id="csvFile" class="form-control" accept=".csv" required>
                        <small class="text-muted">File must be in CSV format with required columns</small>
                    </div>

                    <div id="uploadProgress" class="d-none">
                        <div class="progress">
                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-warning"
                                role="progressbar" style="width: 100%"></div>
                        </div>
                        <p class="text-center mt-2">Processing... Please wait</p>
                    </div>

                    <div id="uploadResults" class="d-none">
                        <!-- Results will be displayed here -->
                    </div>
                </form>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-warning" id="processUploadBtn">
                    <i class="fas fa-upload"></i> Upload & Process
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Test Modal -->
<div class="modal fade" id="testModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">Test Template</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="testForm">
                <div class="modal-body">
                    <input type="hidden" name="template_id" id="testId">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Phone Number</label>
                        <input type="text" name="test_number" class="form-control" required placeholder="91xxxxxxxxxx">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Variables (Comma separated)</label>
                        <input type="text" name="test_variables" class="form-control" placeholder="Val1, Val2">
                        <small class="text-muted" id="varNamesHint"></small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-info text-white" id="testBtn">Send Test Message</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Include SheetJS for modern Excel exports -->
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script src="../../assets/js/table-utilities.js"></script>
<?php require_once '../../include/footer.php'; ?>

<script>
    // Wait for ALL resources (including external scripts) to be fully loaded
    let apiClient;

    // Download Template Function - Global scope
    function downloadWhatsAppTemplate() {
        window.location.href = '../../../counselling-backend/controllers/settings/download-whatsapp-template-csv.php';
    }

    async function loadInitialData() {
        try {
            // Initialize API client if not already done
            if (!apiClient) {
                if (typeof CounsellingAPI === 'undefined') {
                    console.error('CounsellingAPI is not available yet');
                    throw new Error('API Client not loaded. Please refresh the page.');
                }
                apiClient = new CounsellingAPI();
            }

            // Static WhatsApp configuration - no provider selection needed
            console.log('Using static WhatsApp configuration');

            // Load templates directly
            loadTemplates();
        } catch (error) {
            console.error('Failed to load initial data:', error);
            console.error('Error details:', {
                message: error.message,
                stack: error.stack,
                name: error.name
            });
            showToast('error', 'Initialization Error', 'Failed to initialize. Please refresh the page. ' + error.message);
        }
    }

    async function loadTemplates() {
        const filters = $('#filterForm').serialize();
        console.log('Loading templates with filters:', filters);

        try {
            console.log('Calling API: settings/whatsapp-templates');
            const response = await apiClient.get('settings/whatsapp-templates', filters);
            console.log('API Response received:', response);

            if (!response.success) {
                throw new Error(response.message || 'Failed to fetch templates');
            }

            const templates = response.data.templates;
            console.log('Templates count:', templates.length);

            let html = '';
            if (templates.length === 0) {
                console.log('No templates found');
                html = '<tr><td colspan="7" class="text-center p-4">No templates found</td></tr>';
            } else {
                templates.forEach(t => {
                    const statusBadge = getStatusBadge(t.approval_status);
                    const activeSwitch = t.is_active == 1 ? 'checked' : '';

                    html += `
                        <tr data-id="${t.id}">
                            <td class="ps-3 text-center">
                                <div class="form-check">
                                    <input class="form-check-input row-checkbox" type="checkbox" value="${t.id}">
                                </div>
                            </td>
                            <td>
                                <div class="fw-bold">${t.template_name}</div>
                                <span class="badge bg-light text-dark tiny">${t.template_category}</span>
                            </td>
                            <td>
                                <div class="small text-muted text-truncate" style="max-width: 300px;" title="${t.body_text}">
                                    ${t.body_text}
                                </div>
                            </td>
                            <td>
                                <div class="small text-primary">${t.variable_names || 'No variables'}</div>
                            </td>
                            <td>${statusBadge}</td>
                            <td>
                                <div class="form-check form-switch">
                                    <input class="form-check-input toggle-status" type="checkbox" data-id="${t.id}" ${activeSwitch}>
                                </div>
                            </td>
                            <td class="text-end pe-3">
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-outline-info test-btn" data-id="${t.id}" data-vars="${t.variable_names}" title="Test">
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-primary edit-btn" data-id="${t.id}" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger delete-btn" data-id="${t.id}" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `;
                });
            }
            $('#templatesList').html(html);
            toggleBulkActions();
            console.log('Templates loaded successfully');
        } catch (error) {
            console.error('============ TEMPLATE FETCH ERROR ============');
            console.error('Error Type:', error.name);
            console.error('Error Message:', error.message);
            console.error('Error Stack:', error.stack);
            console.error('Filters Used:', filters);
            console.error('API Client State:', {
                baseURL: apiClient?.baseURL,
                initialized: !!apiClient
            });
            console.error('Current Response:', error.response);
            console.error('============================================');

            const errorMsg = error.message || 'Unknown error occurred';
            $('#templatesList').html(`
                <tr>
                    <td colspan="7" class="text-danger text-center p-4">
                        <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                        <div><strong>Error loading templates:</strong> ${errorMsg}</div>
                        <div class="small mt-2">Check browser console for details</div>
                    </td>
                </tr>
            `);

            // Show user-friendly alert
            showToast('error', 'Failed to Load Templates', errorMsg);
        }
    }

    function getStatusBadge(status) {
        switch (status) {
            case 'approved':
                return '<span class="badge bg-success">Approved</span>';
            case 'pending':
                return '<span class="badge bg-warning">Pending</span>';
            case 'rejected':
                return '<span class="badge bg-danger">Rejected</span>';
            default:
                return '<span class="badge bg-secondary">Draft</span>';
        }
    }

    function toggleBulkActions() {
        const count = $('.row-checkbox:checked').length;
        $('#bulkDeleteBtn').toggleClass('d-none', count === 0);
    }

    // Use window load event instead of document ready to ensure all scripts are loaded
    window.addEventListener('load', function () {
        console.log('WhatsApp Templates page - window fully loaded');
        console.log('jQuery version:', $.fn.jquery);
        console.log('Bootstrap modal available:', typeof $.fn.modal);
        console.log('CounsellingAPI available:', typeof CounsellingAPI !== 'undefined');

        // Check if API client class is available
        if (typeof CounsellingAPI === 'undefined') {
            console.error('CounsellingAPI not loaded! Checking script...');
            console.log('Scripts in page:', Array.from(document.scripts).map(s => s.src));

            showToast('error', 'Script Loading Error', 'API Client script failed to load. Please check your internet connection and refresh.');
            setTimeout(() => location.reload(), 3000);
            return;
        }

        // Initialize API client
        apiClient = new CounsellingAPI();
        console.log('API Client initialized successfully');

        // Verify critical elements exist
        console.log('Download button exists:', $('#downloadTemplateBtn').length > 0);
        console.log('Bulk upload button exists:', $('#bulkUploadBtn').length > 0);
        console.log('Bulk upload modal exists:', $('#bulkUploadModal').length > 0);
        console.log('Template modal exists:', $('#templateModal').length > 0);

        // Initialize modals
        if ($('#bulkUploadModal').length > 0) {
            try {
                const bulkModal = new bootstrap.Modal(document.getElementById('bulkUploadModal'), {
                    keyboard: true,
                    backdrop: true
                });
                console.log('Bulk upload modal initialized');
            } catch (e) {
                console.warn('Could not initialize bulk modal (may already be initialized):', e.message);
            }
        }

        // Start loading data
        loadInitialData();

        // Filter form
        $('#filterForm').on('submit', function (e) {
            e.preventDefault();
            loadTemplates();
        });

        // Select All
        $('#selectAll').on('change', function () {
            $('.row-checkbox').prop('checked', $(this).is(':checked'));
            toggleBulkActions();
        });

        $(document).on('change', '.row-checkbox', toggleBulkActions);

        // Add
        $('#templateModal').on('show.bs.modal', function () {
            if (!$('#templateId').val()) {
                $('#templateForm')[0].reset();
                $('#modalTitle').text('Add WhatsApp Template');
            }
        });

        // Edit
        $(document).on('click', '.edit-btn', async function () {
            const id = $(this).data('id');
            const btn = $(this);
            btn.prop('disabled', true);

            try {
                const response = await apiClient.get('settings/whatsapp-template-get', {
                    id
                });
                const t = response.data;

                $('#templateId').val(t.id);
                // Provider is now auto-set from active provider, no need to populate
                $('#templateCategory').val(t.template_category);
                $('#templateName').val(t.template_name);
                $('#bodyText').val(t.body_text);
                $('#variableNames').val(t.variable_names);
                $('#headerType').val(t.header_type || 'none');
                $('#approvalStatus').val(t.approval_status);
                $('#isActive').prop('checked', t.is_active == 1);

                $('#modalTitle').text('Edit WhatsApp Template');
                $('#templateModal').modal('show');
            } catch (error) {
                console.error('Failed to fetch template for editing:', error);
                console.error('Template ID:', id);
                console.error('Error details:', {
                    message: error.message,
                    stack: error.stack
                });
                showToast('error', 'Error', 'Failed to fetch template: ' + error.message);
            } finally {
                btn.prop('disabled', false);
            }
        });

        // Save
        $('#templateForm').on('submit', async function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            const btn = $('#saveBtn');
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');

            try {
                const response = await apiClient.post('settings/whatsapp-template-save', formData);
                if (response.success) {
                    showToast('success', 'Success', response.message);
                    $('#templateModal').modal('hide');
                    loadTemplates();
                } else {
                    showToast('error', 'Error', response.message);
                }
            } catch (error) {
                console.error('Failed to save template:', error);
                console.error('Form data:', Object.fromEntries(new FormData($('#templateForm')[0])));
                console.error('Error details:', {
                    message: error.message,
                    stack: error.stack
                });
                showToast('error', 'Error', 'Submission failed: ' + error.message);
            } finally {
                btn.prop('disabled', false).text('Save Template');
            }
        });

        // Toggle Status
        $(document).on('change', '.toggle-status', async function () {
            const id = $(this).data('id');
            const isActive = $(this).is(':checked') ? 1 : 0;
            const toggle = $(this);

            try {
                const response = await apiClient.post('settings/whatsapp-template-toggle', {
                    id,
                    is_active: isActive
                });
                if (!response.success) {
                    toggle.prop('checked', !isActive);
                    showToast('error', 'Error', response.message);
                }
            } catch (error) {
                console.error('Failed to toggle template status:', error);
                console.error('Template ID:', id, 'Attempted status:', isActive);
                console.error('Error details:', {
                    message: error.message,
                    stack: error.stack
                });
                toggle.prop('checked', !isActive);
                showToast('error', 'Error', 'Failed to update status: ' + error.message);
            }
        });

        // Delete
        $(document).on('click', '.delete-btn', function () {
            const id = $(this).data('id');
            confirmDelete([id]);
        });

        $('#bulkDeleteBtn').on('click', function () {
            const ids = $('.row-checkbox:checked').map((i, el) => $(el).val()).get();
            confirmDelete(ids);
        });

        async function confirmDelete(ids) {
            showConfirm({
                title: 'Are you sure?',
                text: `Delete ${ids.length} template(s)? This cannot be undone.`,
                icon: 'warning',
                confirmButtonColor: '#d33',
                confirmButtonText: 'Yes, delete!',
                onConfirm: async () => {
                    try {
                        const response = await apiClient.post('settings/whatsapp-template-delete', {
                            ids
                        });
                        if (response.success) {
                            showToast('success', 'Deleted', response.message);
                            loadTemplates();
                        } else {
                            showToast('error', 'Error', response.message);
                        }
                    } catch (error) {
                        console.error('Failed to delete template(s):', error);
                        console.error('Template IDs:', ids);
                        console.error('Error details:', {
                            message: error.message,
                            stack: error.stack
                        });
                        showToast('error', 'Error', 'Delete operation failed: ' + error.message);
                    }
                }
            });
        }

        // Test
        $(document).on('click', '.test-btn', function () {
            const id = $(this).data('id');
            const vars = $(this).data('vars') || 'No variables defined';
            $('#testId').val(id);
            $('#varNamesHint').text(`Hint: ${vars}`);
            $('#testModal').modal('show');
        });

        $('#testForm').on('submit', async function (e) {
            e.preventDefault();
            // Extract form data as plain object (FormData doesn't work with JSON.stringify)
            const testData = {
                template_id: $('#testId').val(),
                test_number: $('input[name="test_number"]').val(),
                test_variables: $('input[name="test_variables"]').val() || ''
            };
            const btn = $('#testBtn');
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Sending...');

            try {
                const response = await apiClient.post('settings/whatsapp-template-test', testData);
                if (response.success) {
                    showToast('success', 'Sent', 'Test message sent successfully!');
                    $('#testModal').modal('hide');
                } else {
                    showToast('error', 'Error', response.message);
                }
            } catch (error) {
                console.error('Failed to send test message:', error);
                console.error('Form data:', Object.fromEntries(new FormData($('#testForm')[0])));
                console.error('Error details:', {
                    message: error.message,
                    stack: error.stack
                });
                showToast('error', 'Error', 'Test failed: ' + error.message);
            } finally {
                btn.prop('disabled', false).text('Send Test Message');
            }
        });

        // Export
        $('#exportBtn').on('click', function () {
            TableUtils.exportToExcel('templatesTable', 'whatsapp_templates');
        });

        // Bulk Upload Modal
        $('#bulkUploadBtn').on('click', function (e) {
            e.preventDefault();
            console.log('Bulk Upload button clicked');
            console.log('Looking for modal with ID: bulkUploadModal');

            const modal = $('#bulkUploadModal');
            console.log('Modal element found:', modal.length > 0);
            console.log('Modal element:', modal[0]);

            if (modal.length === 0) {
                console.error('Bulk upload modal not found in DOM!');
                showToast('error', 'Error', 'Upload modal not found. Please refresh the page.');
                return;
            }

            try {
                console.log('Attempting to show modal...');
                modal.modal('show');
                console.log('Modal show called successfully');

                // Reset form
                const form = $('#bulkUploadForm')[0];
                if (form) {
                    form.reset();
                    console.log('Form reset');
                } else {
                    console.warn('Form not found');
                }

                $('#uploadResults').addClass('d-none');
                $('#uploadProgress').addClass('d-none');
                console.log('Upload results and progress hidden');
            } catch (error) {
                console.error('Error showing bulk upload modal:', error);
                console.error('Error details:', {
                    message: error.message,
                    stack: error.stack
                });
                showToast('error', 'Error', 'Failed to open upload modal: ' + error.message);
            }
        });

        // Process Bulk Upload
        $('#processUploadBtn').on('click', function () {
            console.log('Process upload button clicked');
            const form = $('#bulkUploadForm')[0];
            console.log('Form element:', form);

            if (!form) {
                console.error('Bulk upload form not found!');
                showToast('error', 'Error', 'Upload form not found. Please refresh the page.');
                return;
            }

            if (!form.checkValidity()) {
                console.log('Form validation failed');
                form.reportValidity();
                return;
            }

            console.log('Form is valid, preparing upload...');
            const formData = new FormData(form);
            console.log('Form data created');
            console.log('File selected:', formData.get('csv_file'));
            const btn = $(this);

            btn.prop('disabled', true);
            $('#uploadProgress').removeClass('d-none');
            $('#uploadResults').addClass('d-none');

            $.ajax({
                url: '../../../counselling-backend/index.php?route=settings/whatsapp-template-bulk-upload',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function (response) {
                    $('#uploadProgress').addClass('d-none');
                    $('#uploadResults').removeClass('d-none');

                    if (response.success) {
                        let html = `
                            <div class="alert alert-success">
                                <h5><i class="fas fa-check-circle"></i> Upload Completed</h5>
                                <p class="mb-0">
                                    <strong>Total Processed:</strong> ${response.data.total_processed}<br>
                                    <strong>Successfully Created:</strong> ${response.data.success_count}<br>
                                    <strong>Errors:</strong> ${response.data.error_count}
                                </p>
                            </div>
                        `;

                        if (response.data.errors && response.data.errors.length > 0) {
                            html += `
                                <div class="alert alert-warning">
                                    <h6><i class="fas fa-exclamation-triangle"></i> Errors Found:</h6>
                                    <ul class="mb-0" style="max-height: 200px; overflow-y: auto;">
                            `;
                            response.data.errors.forEach(function (error) {
                                html += `<li>Row ${error.row}: ${error.message}</li>`;
                            });
                            html += `</ul></div>`;
                        }

                        $('#uploadResults').html(html);

                        // Reload templates
                        setTimeout(() => loadTemplates(), 1500);
                    } else {
                        $('#uploadResults').html(`
                            <div class="alert alert-danger">
                                <i class="fas fa-times-circle"></i> ${response.message}
                            </div>
                        `);
                    }
                },
                error: function (xhr, status, error) {
                    console.error('Bulk upload failed:', error);
                    console.error('XHR details:', {
                        status: xhr.status,
                        statusText: xhr.statusText,
                        responseText: xhr.responseText,
                        readyState: xhr.readyState
                    });
                    console.error('Error status:', status);

                    let errorMsg = 'Upload failed. Please check your file and try again.';
                    try {
                        const response = JSON.parse(xhr.responseText);
                        errorMsg = response.message || errorMsg;
                    } catch (e) {
                        console.error('Could not parse error response:', e);
                    }

                    $('#uploadProgress').addClass('d-none');
                    $('#uploadResults').removeClass('d-none').html(`
                        <div class="alert alert-danger">
                            <i class="fas fa-times-circle"></i> ${errorMsg}
                            <div class="mt-2 small">Status: ${xhr.status} ${xhr.statusText}</div>
                        </div>
                    `);
                },
                complete: function () {
                    btn.prop('disabled', false);
                }
            });
        });
    });
</script>