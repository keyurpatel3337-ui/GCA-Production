<?php
/**
 * Manage Email Template (Add/Edit)
 * Path: portal/modules/settings/email-template-manage.php
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

$templateId = isset($_GET['id']) ? $_GET['id'] : '';
$isEdit = !empty($templateId);
$pageTitle = $isEdit ? 'Edit Email Template' : 'Add Email Template';

// Load All Template Variables JSON
$allSamples = [];
$jsonFile = dirname(dirname(dirname(__DIR__))) . '/all_template_variables.json';
if (file_exists($jsonFile)) {
    $jsonContent = file_get_contents($jsonFile);
    $allSamples = json_decode($jsonContent, true) ?: [];
}
?>

<div class="app-content-header">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-6">
                <h3 class="mb-0"><?php echo $pageTitle; ?></h3>
            </div>
            <div class="col-sm-6 text-end">
                <a href="email-templates.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back to List
                </a>
            </div>
        </div>
    </div>
</div>

<div class="app-content">
    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0"><?php echo $isEdit ? 'Update Template Details' : 'Create New Template'; ?></h5>
                    </div>
                    <form id="templateForm">
                        <div class="card-body">
                            <input type="hidden" name="id" id="templateId" value="<?php echo $templateId; ?>">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Template Name <span class="text-danger">*</span></label>
                                    <input type="text" name="template_name" id="templateName" class="form-control" required
                                        placeholder="e.g. Welcome Email">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Template Code <span class="text-danger">*</span></label>
                                    <input type="text" name="template_code" id="templateCode" class="form-control" required
                                        placeholder="WELCOME_EMAIL">
                                    <small class="text-muted">Unique identifier used in code.</small>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Recipient Type</label>
                                    <select name="recipient_type" id="recipientType" class="form-select">
                                        <option value="student">Student</option>
                                        <option value="parent">Parent</option>
                                        <option value="staff">Staff</option>
                                        <option value="admin">Admin</option>
                                        <option value="counsellor">Counsellor</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Category</label>
                                    <select name="template_category" id="templateCategory" class="form-select">
                                        <option value="STAFF_NOTIFICATION">Staff Notification</option>
                                        <option value="STUDENT_NOTIFICATION">Student Notification</option>
                                        <option value="ADMIN_NOTIFICATION">Admin Notification</option>
                                        <option value="SYSTEM_ALERT">System Alert</option>
                                        <option value="DAILY_REPORT">Daily Report</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Priority</label>
                                    <select name="priority" id="priority" class="form-select">
                                        <option value="low">Low</option>
                                        <option value="normal" selected>Normal</option>
                                        <option value="high">High</option>
                                    </select>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label fw-bold">Subject <span class="text-danger">*</span></label>
                                    <input type="text" name="subject" id="subject" class="form-control" required
                                        placeholder="Subject of the email">
                                </div>
                                <div class="col-md-12">
                                    <div class="row g-3">
                                        <div class="col-lg-6">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <label class="form-label fw-bold mb-0">HTML Content <span class="text-danger">*</span></label>
                                                <small class="text-muted">You can use {{variable_name}} for variables.</small>
                                            </div>
                                            <textarea name="html_content" id="htmlContent" class="form-control" rows="20" required
                                                placeholder="<p>Hello {{student_name}},</p>..."></textarea>
                                        </div>
                                        <div class="col-lg-6">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <label class="form-label fw-bold mb-0">Live Preview</label>
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-outline-secondary active" id="btnDesktop">
                                                        <i class="fas fa-desktop"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-secondary" id="btnMobile">
                                                        <i class="fas fa-mobile-alt"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-danger" id="btnDownloadPDF" title="Download PDF Preview">
                                                        <i class="fas fa-file-pdf"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="preview-container border rounded bg-white overflow-hidden">
                                                <iframe id="livePreview" class="w-100 h-100 border-0"></iframe>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label fw-bold">Variables (Auto-extracted from content)</label>
                                    <input type="text" name="variables" id="variables" class="form-control bg-light"
                                        placeholder='["student_name", "order_id"]' readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">CC (Comma separated)</label>
                                    <input type="text" name="cc_emails" id="ccEmails" class="form-control"
                                        placeholder="admin@example.com">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">BCC (Comma separated)</label>
                                    <input type="text" name="bcc_emails" id="bccEmails" class="form-control"
                                        placeholder="logs@example.com">
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
                        <div class="card-footer bg-light text-end p-3">
                            <a href="email-templates.php" class="btn btn-secondary me-2">Cancel</a>
                            <button type="submit" class="btn btn-primary" id="saveBtn">
                                <i class="fas fa-save me-1"></i> Save Template
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../include/footer.php'; ?>

<!-- HTML2PDF Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<script>
    const apiClient = new CounsellingAPI();

    // Data from all_template_variables.json
    const ALL_TEMPLATE_SAMPLES = <?php echo json_encode($allSamples); ?>;

    $(document).ready(async function () {
        const templateId = $('#templateId').val();
        const $htmlContent = $('#htmlContent');
        const $preview = $('#livePreview');

        // Default Fallback Sample Data Mapping (for new or unmatched templates)
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

        function updatePreview() {
            let content = $htmlContent.val();
            
            // Automatic Variable Extraction
            const variableRegex = /{{(.*?)}}/g;
            let match;
            const variables = new Set();
            
            while ((match = variableRegex.exec(content)) !== null) {
                const varName = match[1].trim();
                if (varName) {
                    variables.add(varName);
                }
            }
            
            const varArray = Array.from(variables);
            $('#variables').val(JSON.stringify(varArray));

            // Replace variables with sample data for preview
            let previewContent = content;
            const tCode = $('#templateCode').val();
            const templateSpecificSamples = ALL_TEMPLATE_SAMPLES[tCode] ? ALL_TEMPLATE_SAMPLES[tCode].variables : {};

            varArray.forEach(varName => {
                // Priority: 1. Template-specific JSON, 2. Global Fallback, 3. Placeholder
                const value = templateSpecificSamples[varName] || SYSTEM_SAMPLE_DATA[varName] || `[${varName}]`;
                const regex = new RegExp(`{{${varName}}}`, 'g');
                previewContent = previewContent.replace(regex, value);
            });

            // Live Preview Update
            const doc = $preview[0].contentDocument || $preview[0].contentWindow.document;
            doc.open();
            doc.write(previewContent);
            doc.close();
        }

        $htmlContent.on('input', updatePreview);

        $('#btnDesktop').on('click', function () {
            $('.preview-container').css('max-width', '100%');
            $(this).addClass('active').siblings().removeClass('active');
        });

        $('#btnMobile').on('click', function () {
            $('.preview-container').css('max-width', '375px');
            $(this).addClass('active').siblings().removeClass('active');
        });

        $('#btnDownloadPDF').on('click', function () {
            const element = $preview[0].contentDocument.body;
            const templateName = $('#templateName').val() || 'email-template';
            const opt = {
                margin: 1,
                filename: `${templateName}.pdf`,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2 },
                jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
            };

            html2pdf().set(opt).from(element).save();
        });

        if (templateId) {
            // Load existing template data
            try {
                const response = await apiClient.get('settings/email-template-get', { id: templateId });
                if (response.success) {
                    const t = response.data;
                    $('#templateName').val(t.template_name);
                    $('#templateCode').val(t.template_code);
                    $('#recipientType').val(t.recipient_type);
                    $('#templateCategory').val(t.template_category);
                    $('#priority').val(t.priority);
                    $('#subject').val(t.subject);
                    $('#htmlContent').val(t.html_content);
                    $('#variables').val(t.variables);
                    $('#ccEmails').val(t.cc_emails);
                    $('#bccEmails').val(t.bcc_emails);
                    $('#isActive').prop('checked', t.is_active == 1);
                    
                    updatePreview();
                } else {
                    showToast('error', 'Error', 'Failed to fetch template data');
                }
            } catch (error) {
                showToast('error', 'Error', 'An error occurred while fetching template data');
            }
        }

        $('#templateForm').on('submit', async function (e) {
            e.preventDefault();
            
            // Validate Variables JSON if provided
            const variablesInput = $('#variables').val().trim();
            if (variablesInput && variablesInput !== '[]') {
                try {
                    const parsed = JSON.parse(variablesInput);
                    if (!Array.isArray(parsed)) {
                        showToast('error', 'Validation Error', 'Variables must be a valid JSON array (e.g., ["name", "id"])');
                        return;
                    }
                } catch (e) {
                    showToast('error', 'Validation Error', 'Invalid JSON format in Variables field');
                    return;
                }
            }

            const formData = new FormData(this);
            const btn = $('#saveBtn');
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Saving...');

            try {
                const response = await apiClient.post('settings/email-template-save', formData);
                if (response.success) {
                    showToast('success', 'Success', response.message);
                    setTimeout(() => {
                        window.location.href = 'email-templates.php';
                    }, 1000);
                } else {
                    showToast('error', 'Error', response.message);
                }
            } catch (error) {
                showToast('error', 'Error', 'Submission failed');
            } finally {
                btn.prop('disabled', false).html('<i class="fas fa-save me-1"></i> Save Template');
            }
        });
    });
</script>

<style>
    .card {
        border-radius: 12px;
        overflow: hidden;
    }
    .card-header {
        padding: 1.25rem;
    }
    .form-control:focus, .form-select:focus {
        border-color: #007bff;
        box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
    }
    .btn {
        padding: 0.5rem 1.5rem;
        border-radius: 8px;
        font-weight: 500;
    }
    .preview-container {
        height: 500px;
        transition: max-width 0.3s ease;
        margin: 0 auto;
        background-color: #f8f9fa !important;
        box-shadow: inset 0 0 10px rgba(0,0,0,0.05);
    }
</style>
