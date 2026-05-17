<?php

/**
 * Email Templates Management
 * Path: portal/modules/settings/email-templates.php
 */

require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once OPERATION_FILE;
require_once '../../include/header.php';
require_once '../../include/navbar.php';
require_once '../../include/sidebar.php';

// Check permissions
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// Load All Template Variables JSON for preview mapping
$allSamples = [];
$jsonFile = dirname(dirname(dirname(__DIR__))) . '/all_template_variables.json';
if (file_exists($jsonFile)) {
    $jsonContent = file_get_contents($jsonFile);
    $allSamples = json_decode($jsonContent, true) ?: [];
}
?>

<div class="app-content-header">
    <div class="container-fluid">
        <div class="row align-items-center mb-4 mt-3">
            <div class="col-md-4">
                <h3 class="m-0 fw-bold text-dark"><i class="fas fa-envelope-open-text me-2 text-primary"></i>Email Templates</h3>
            </div>
            <div class="col-md-8">
                <div class="d-flex justify-content-md-end flex-wrap gap-2 mt-3 mt-md-0">
                    <a href="email-template-gallery.php" class="btn btn-danger shadow-sm">
                        <i class="fas fa-images me-1"></i> Gallery View
                    </a>
                    <button class="btn btn-info text-white shadow-sm" onclick="downloadEmailTemplate()">
                        <i class="fas fa-download me-1"></i> Download CSV Template
                    </button>
                    <button class="btn btn-warning text-white shadow-sm" id="bulkUploadBtn">
                        <i class="fas fa-file-upload me-1"></i> Bulk Upload
                    </button>
                    <button class="btn btn-success shadow-sm" id="exportBtn">
                        <i class="fas fa-file-excel me-1"></i> Export
                    </button>
                    <button class="btn btn-danger d-none shadow-sm" id="bulkDeleteBtn">
                        <i class="fas fa-trash me-1"></i> Delete Selected
                    </button>
                    <a href="email-template-manage.php" class="btn btn-primary px-4 shadow-sm">
                        <i class="fas fa-plus me-1"></i> Add Template
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>


<div class="app-content">
    <div class="container-fluid">

        <!-- Stats Cards -->
        <div class="row mb-3" id="statsRow">
            <div class="col-md-4">
                <div class="card shadow-sm border-0 bg-primary text-white">
                    <div class="card-body p-3 d-flex align-items-center">
                        <div class="h2 mb-0 me-3"><i class="fas fa-layer-group"></i></div>
                        <div>
                            <div class="tiny opacity-75">Total Templates</div>
                            <h4 class="mb-0" id="statTotal">-</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm border-0 bg-success text-white">
                    <div class="card-body p-3 d-flex align-items-center">
                        <div class="h2 mb-0 me-3"><i class="fas fa-check-circle"></i></div>
                        <div>
                            <div class="tiny opacity-75">Active</div>
                            <h4 class="mb-0" id="statActive">-</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm border-0 bg-secondary text-white">
                    <div class="card-body p-3 d-flex align-items-center">
                        <div class="h2 mb-0 me-3"><i class="fas fa-ban"></i></div>
                        <div>
                            <div class="tiny opacity-75">Inactive</div>
                            <h4 class="mb-0" id="statInactive">-</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Card -->
        <div class="card mb-3 shadow-sm border-0">
            <div class="card-body">
                <form id="filterForm" class="row g-3">
                    <div class="col-md-3">
                        <select class="form-select" name="recipient_type" id="filterRecipient">
                            <option value="">All Recipients</option>
                            <option value="student">Student</option>
                            <option value="parent">Parent</option>
                            <option value="staff">Staff</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="category" id="filterCategory">
                            <option value="">All Categories</option>
                            <option value="STAFF_NOTIFICATION">Staff Notification</option>
                            <option value="STUDENT_NOTIFICATION">Student Notification</option>
                            <option value="ADMIN_NOTIFICATION">Admin Notification</option>
                            <option value="SYSTEM_ALERT">System Alert</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="status" id="filterStatus">
                            <option value="">All Status</option>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-3">
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
                                <th>Name / Code</th>
                                <th>Recipient</th>
                                <th>Category</th>
                                <th>Subject</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th class="text-end pe-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="templatesList">
                            <tr>
                                <td colspan="8" class="text-center p-5">
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
</div>

<!-- Bulk Upload Modal -->
<div class="modal fade" id="bulkUploadModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="fas fa-file-upload"></i> Bulk Upload Email Templates</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <strong>Instructions:</strong>
                    <ol class="mb-0 mt-2">
                        <li>Download the CSV template using the "Download CSV Template" button</li>
                        <li>Fill in the template with your email template data</li>
                        <li>Ensure template_code is unique and in UPPERCASE format (e.g., STUDENT_WELCOME)</li>
                        <li>Save the file in CSV format</li>
                        <li>Upload the completed CSV file below</li>
                    </ol>
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

<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title">Template Preview</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" style="height: 600px;">
                <iframe id="quickPreviewFrame" class="w-100 h-100 border-0"></iframe>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Test Modal -->
<div class="modal fade" id="testModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Test Email Template</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="testForm">
                <div class="modal-body">
                    <input type="hidden" name="template_id" id="testTemplateId">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Recipient Email <span class="text-danger">*</span></label>
                            <input type="email" name="test_email" class="form-control" required
                                placeholder="test@example.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Recipient Name</label>
                            <input type="text" name="test_name" class="form-control" value="Test User">
                        </div>
                        <div class="col-md-12">
                            <h6 class="fw-bold border-bottom pb-2">Template Variables</h6>
                            <div id="testVariablesContainer">
                                <p class="text-muted small">Loading variables...</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer text-end p-3">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-success" id="testBtn">Send Test Email</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Include SheetJS for modern Excel exports -->
<script src="<?php echo BASE_URL; ?>/assets/vendor/xlsx/xlsx.full.min.js"></script>
<script src="../../assets/js/table-utilities.js"></script>
<?php require_once '../../include/footer.php'; ?>

<script>
    const apiClient = new CounsellingAPI();
    const ALL_TEMPLATE_SAMPLES = <?php echo json_encode($allSamples); ?>;

    async function loadTemplates() {
        const filters = $('#filterForm').serialize();
        try {
            const response = await apiClient.get('settings/email-templates', filters);
            const {
                templates,
                stats
            } = response.data;

            // Update Stats
            $('#statTotal').text(stats.total);
            $('#statActive').text(stats.active);
            $('#statInactive').text(stats.inactive);

            let html = '';
            if (templates.length === 0) {
                html = '<tr><td colspan="8" class="text-center p-4">No templates found</td></tr>';
            } else {
                templates.forEach(t => {
                    const activeSwitch = t.is_active == 1 ? 'checked' : '';
                    const priorityClass = t.priority === 'high' ? 'danger' : (t.priority === 'low' ? 'secondary' : 'info');

                    html += `
                        <tr data-id="${t.id}">
                            <td class="ps-3 text-center">
                                <div class="form-check">
                                    <input class="form-check-input row-checkbox" type="checkbox" value="${t.id}">
                                </div>
                            </td>
                            <td>
                                <div class="fw-bold">${t.template_name}</div>
                                <div class="tiny text-muted uppercase">${t.template_code}</div>
                            </td>
                            <td><span class="badge bg-primary tiny">${t.recipient_type}</span></td>
                            <td><span class="badge bg-light text-dark tiny">${t.template_category}</span></td>
                            <td>
                                <div class="small text-truncate" style="max-width: 200px;" title="${t.subject}">
                                    ${t.subject}
                                </div>
                            </td>
                            <td><span class="badge bg-${priorityClass} uppercase tiny">${t.priority}</span></td>
                            <td>
                                <div class="form-check form-switch">
                                    <input class="form-check-input toggle-status" type="checkbox" data-id="${t.id}" ${activeSwitch}>
                                </div>
                            </td>
                            <td class="text-end pe-3">
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-outline-dark quick-preview-btn" data-id="${t.id}" title="Quick Preview">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-success test-btn" data-id="${t.id}" title="Test">
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                    <a href="email-template-manage.php?id=${t.id}" class="btn btn-sm btn-outline-primary" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
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
        } catch (error) {
            $('#templatesList').html(`<tr><td colspan="8" class="text-danger text-center p-4">Error: ${error.message}</td></tr>`);
        }
    }

    function toggleBulkActions() {
        const count = $('.row-checkbox:checked').length;
        $('#bulkDeleteBtn').toggleClass('d-none', count === 0);
    }

    $(document).ready(function () {
        loadTemplates();

        $('#filterForm').on('submit', function (e) {
            e.preventDefault();
            loadTemplates();
        });

        $('#selectAll').on('change', function () {
            $('.row-checkbox').prop('checked', $(this).is(':checked'));
            toggleBulkActions();
        });

        $(document).on('change', '.row-checkbox', toggleBulkActions);

        // Remove old Add/Edit modal logic

        // Toggle Status
        $(document).on('change', '.toggle-status', async function () {
            const id = $(this).data('id');
            const isActive = $(this).is(':checked') ? 1 : 0;
            const toggle = $(this);

            try {
                const response = await apiClient.post('settings/email-template-toggle', {
                    id,
                    is_active: isActive
                });
                if (!response.success) {
                    toggle.prop('checked', !isActive);
                    showToast('error', 'Error', response.message);
                }
            } catch (error) {
                toggle.prop('checked', !isActive);
            }
        });

        // Delete
        $(document).on('click', '.delete-btn', function () {
            confirmDelete([$(this).data('id')]);
        });
        $('#bulkDeleteBtn').on('click', function () {
            const ids = $('.row-checkbox:checked').map((i, el) => $(el).val()).get();
            confirmDelete(ids);
        });

        async function confirmDelete(ids) {
            showConfirm({
                title: 'Are you sure?',
                message: `Delete ${ids.length} template(s)?`,
                confirmText: 'Yes, delete!',
                confirmButtonClass: 'btn-danger',
                onConfirm: async function () {
                    try {
                        const response = await apiClient.post('settings/email-template-delete', { ids });
                        if (response.success) {
                            showToast('success', 'Deleted', response.message);
                            loadTemplates();
                        } else {
                            showToast('error', 'Error', 'Operation failed');
                        }
                    } catch (error) {
                        showToast('error', 'Error', 'Operation failed');
                    }
                }
            });
        }

        // Quick Preview
        $(document).on('click', '.quick-preview-btn', async function () {
            const id = $(this).data('id');
            $('#previewModal').modal('show');
            const iframe = document.getElementById('quickPreviewFrame');
            const doc = iframe.contentDocument || iframe.contentWindow.document;
            doc.open();
            doc.write('<div style="text-align:center;padding:50px;"><i class="fas fa-spinner fa-spin fa-2x"></i><br>Generating preview...</div>');
            doc.close();

            try {
                const response = await apiClient.get('settings/email-template-get', { id });
                const t = response.data;
                let vars = [];
                try {
                    vars = JSON.parse(t.variables) || [];
                } catch (e) { }

                let content = t.html_content;
                const templateSpecificSamples = ALL_TEMPLATE_SAMPLES[t.template_code] ? ALL_TEMPLATE_SAMPLES[t.template_code].variables : {};
                
                const SYSTEM_SAMPLE_DATA = {
                    'student_name': 'John Doe',
                    'parent_name': 'Richard Doe',
                    'staff_name': 'Admin User',
                    'course_name': 'Bachelor of Technology',
                    'branch_name': 'Computer Science',
                    'registration_no': 'GCA2026001',
                    'roll_no': '2026CS01',
                    'academic_year': '2025-26',
                    'semester': '4th Semester',
                    'exam_name': 'Mid-Term Examination',
                    'result_grade': 'A+',
                    'payment_amount': '₹15,000',
                    'order_id': 'ORD_12345678',
                    'transaction_date': new Date().toLocaleDateString(),
                    'login_url': 'https://gyanmanjari.com/login',
                    'portal_url': 'https://gyanmanjari.com',
                    'otp': '123456',
                    'expiry_time': '10 Minutes',
                    'support_email': 'support@gyanmanjari.co.in'
                };

                vars.forEach(varName => {
                    const value = templateSpecificSamples[varName] || SYSTEM_SAMPLE_DATA[varName] || `[${varName}]`;
                    const regex = new RegExp(`{{${varName}}}`, 'g');
                    content = content.replace(regex, value);
                });

                doc.open();
                doc.write(content);
                doc.close();
            } catch (error) {
                doc.open();
                doc.write('<div class="text-danger" style="text-align:center;padding:50px;">Failed to load preview.</div>');
                doc.close();
            }
        });

        // Test
        $(document).on('click', '.test-btn', async function () {
            const id = $(this).data('id');
            $('#testTemplateId').val(id);
            $('#testVariablesContainer').html('<p class="text-muted small">Extracting variables...</p>');
            $('#testModal').modal('show');

            try {
                const response = await apiClient.get('settings/email-template-get', {
                    id
                });
                const t = response.data;
                let vars = [];
                try {
                    vars = JSON.parse(t.variables) || [];
                } catch (e) { }

                if (vars.length > 0) {
                    let varHtml = '<div class="row">';
                    vars.forEach(v => {
                        varHtml += `
                            <div class="col-md-6 mb-2">
                                <label class="small fw-bold">${v}</label>
                                <input type="text" name="var_${v}" class="form-control form-control-sm" placeholder="Value for ${v}">
                            </div>
                        `;
                    });
                    varHtml += '</div>';
                    $('#testVariablesContainer').html(varHtml);
                } else {
                    $('#testVariablesContainer').html('<p class="text-success small">No variables required for this template.</p>');
                }
            } catch (e) {
                $('#testVariablesContainer').html('<p class="text-danger small">Failed to load variables.</p>');
            }
        });

        $('#testForm').on('submit', async function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            const btn = $('#testBtn');
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Sending...');

            try {
                const response = await apiClient.post('settings/email-template-test', formData);
                if (response.success) {
                    showToast('success', 'Sent', 'Test email sent successfully!');
                    $('#testModal').modal('hide');
                } else {
                    showToast('error', 'Error', response.message);
                }
            } catch (error) {
                showToast('error', 'Error', 'Test failed');
            } finally {
                btn.prop('disabled', false).text('Send Test Email');
            }
        });

        // Export
        $('#exportBtn').on('click', function () {
            TableUtils.exportToExcel('templatesTable', 'email_templates');
        });

        // Download Template Function
        function downloadEmailTemplate() {
            window.location.href = '../../../counselling-backend/controllers/settings/download-email-template-csv.php';
        }

        // Bulk Upload Modal
        $('#bulkUploadBtn').on('click', function () {
            $('#bulkUploadModal').modal('show');
            $('#bulkUploadForm')[0].reset();
            $('#uploadResults').addClass('d-none');
        });

        // Process Bulk Upload
        $('#processUploadBtn').on('click', function () {
            const form = $('#bulkUploadForm')[0];
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            const formData = new FormData(form);
            const btn = $(this);

            btn.prop('disabled', true);
            $('#uploadProgress').removeClass('d-none');
            $('#uploadResults').addClass('d-none');

            $.ajax({
                url: '../../../counselling-backend/index.php?route=settings/email-template-bulk-upload',
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
                error: function (xhr) {
                    $('#uploadProgress').addClass('d-none');
                    $('#uploadResults').removeClass('d-none').html(`
                        <div class="alert alert-danger">
                            <i class="fas fa-times-circle"></i> Upload failed. Please check your file and try again.
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

<link rel="stylesheet" href="<?php echo PORTAL_URL; ?>/assets/css/modules/settings/email-templates.php.css">