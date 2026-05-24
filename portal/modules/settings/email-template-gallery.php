<?php
/**
 * Email Template Gallery (Review & Approval)
 * Path: portal/modules/settings/email-template-gallery.php
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

// Load All Template Variables JSON for sample data
$allSamples = [];
$jsonFile = dirname(dirname(dirname(__DIR__))) . '/all_template_variables.json';
if (file_exists($jsonFile)) {
    $jsonContent = file_get_contents($jsonFile);
    $allSamples = json_decode($jsonContent, true) ?: [];
}
?>

<div class="app-content-header d-print-none">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-6">
                <h3 class="mb-0">Email Template Gallery</h3>
                <p class="text-muted small">Review all templates for approval</p>
            </div>
            <div class="col-sm-6 text-end">
                <button class="btn btn-danger me-2" id="downloadAllBtn">
                    <i class="fas fa-file-pdf me-1"></i> Download All as PDF
                </button>
                <a href="email-templates.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back to List
                </a>
            </div>
        </div>
    </div>
</div>

<div class="app-content">
    <div class="container-fluid">
        <div id="galleryContainer" class="row g-4">
            <!-- Templates will be loaded here -->
            <div class="col-12 text-center p-5 loading-state">
                <div class="spinner-border text-primary" role="status"></div>
                <div class="mt-2">Loading template gallery...</div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../include/footer.php'; ?>

<!-- HTML2PDF Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<script>
    const apiClient = new CounsellingAPI();
    const ALL_TEMPLATE_SAMPLES = <?php echo json_encode($allSamples); ?>;
    
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

    let activeTemplates = [];

    async function loadGallery() {
        try {
            const response = await apiClient.get('settings/email-templates', { status: 1 }); // Only active
            activeTemplates = response.data.templates;
            
            if (activeTemplates.length === 0) {
                $('#galleryContainer').html('<div class="col-12 text-center p-5"><h4>No active templates found.</h4></div>');
                return;
            }

            let html = '';
            activeTemplates.forEach(t => {
                html += `
                    <div class="col-xl-6 col-lg-12 template-card-wrapper" id="card_${t.id}">
                        <div class="card shadow-sm border-0 h-100 overflow-hidden">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0 fw-bold">${t.template_name}</h6>
                                    <small class="text-muted uppercase tiny">${t.template_code}</small>
                                </div>
                                <div class="d-print-none text-end">
                                    <span class="badge bg-primary tiny">${t.recipient_type}</span>
                                    <span class="badge bg-info tiny">${t.priority}</span>
                                    <button class="btn btn-sm btn-outline-danger ms-2 download-single-pdf" data-id="${t.id}" title="Download individual PDF">
                                        <i class="fas fa-file-pdf"></i>
                                    </button>
                                    <a href="email-template-manage.php?id=${t.id}" class="btn btn-sm btn-link text-primary p-0 ms-2" title="Edit Template">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </div>
                            </div>
                            <div class="card-body p-0 css-email-template-gallery-47143d">
                                <iframe id="preview_${t.id}" class="preview-iframe w-100 h-100 border-0"></iframe>
                            </div>
                            <div class="card-footer bg-white border-top-0 d-print-none">
                                <div class="small text-muted">
                                    <strong>Subject:</strong> ${t.subject}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });

            $('#galleryContainer').html(html);

            // Render content into iframes
            activeTemplates.forEach(t => {
                renderTemplate(t);
            });

        } catch (error) {
            $('#galleryContainer').html(`<div class="col-12 text-center p-5 text-danger">Error: ${error.message}</div>`);
        }
    }

    function renderTemplate(t) {
        const iframe = document.getElementById(`preview_${t.id}`);
        if (!iframe) return;

        let content = t.html_content;
        let vars = [];
        try {
            vars = JSON.parse(t.variables) || [];
        } catch (e) {}

        const templateSpecificSamples = ALL_TEMPLATE_SAMPLES[t.template_code] ? ALL_TEMPLATE_SAMPLES[t.template_code].variables : {};

        vars.forEach(varName => {
            const value = templateSpecificSamples[varName] || SYSTEM_SAMPLE_DATA[varName] || `[${varName}]`;
            const regex = new RegExp(`{{${varName}}}`, 'g');
            content = content.replace(regex, value);
        });

        const doc = iframe.contentDocument || iframe.contentWindow.document;
        doc.open();
        doc.write(`
            <html>
                <head>
                    
                </head>
                <body>${content}</body>
            </html>
        `);
        doc.close();
    }

    function getTemplateHtml(t) {
        let content = t.html_content;
        let vars = [];
        try {
            vars = JSON.parse(t.variables) || [];
        } catch (e) {}

        const templateSpecificSamples = ALL_TEMPLATE_SAMPLES[t.template_code] ? ALL_TEMPLATE_SAMPLES[t.template_code].variables : {};

        vars.forEach(varName => {
            const value = templateSpecificSamples[varName] || SYSTEM_SAMPLE_DATA[varName] || `[${varName}]`;
            const regex = new RegExp(`{{${varName}}}`, 'g');
            content = content.replace(regex, value);
        });

        return `
            <div class="css-email-template-gallery-2060cc">
                ${content}
            </div>
        `;
    }

    async function downloadPDF(t, filename) {
        const container = document.createElement('div');
        container.style.position = 'fixed';
        container.style.left = '-9999px';
        container.style.top = '0';
        container.style.width = '800px';
        container.style.background = 'white';
        
        const h = document.createElement('h2');
        h.innerText = t.template_name;
        h.style.padding = '20px';
        h.style.borderBottom = '1px solid #eee';
        h.style.margin = '0';
        container.appendChild(h);

        const contentDiv = document.createElement('div');
        contentDiv.innerHTML = getTemplateHtml(t);
        container.appendChild(contentDiv);
        
        document.body.appendChild(container);

        const opt = {
            margin: 0.5,
            filename: filename,
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2, useCORS: true },
            jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
        };

        try {
            await html2pdf().set(opt).from(container).save();
        } finally {
            document.body.removeChild(container);
        }
    }

    $(document).ready(function() {
        loadGallery();

        // Download Single PDF
        $(document).on('click', '.download-single-pdf', function() {
            const id = $(this).data('id');
            const t = activeTemplates.find(templ => templ.id == id);
            const btn = $(this);
            
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
            downloadPDF(t, `${t.template_code}.pdf`).finally(() => {
                btn.prop('disabled', false).html('<i class="fas fa-file-pdf"></i>');
            });
        });

        // Download All PDF
        $('#downloadAllBtn').on('click', async function() {
            const btn = $(this);
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Generating All PDFs...');
            
            const masterContainer = document.createElement('div');
            masterContainer.style.position = 'fixed';
            masterContainer.style.left = '-9999px';
            masterContainer.style.top = '0';
            masterContainer.style.width = '800px';
            masterContainer.style.background = 'white';

            activeTemplates.forEach(t => {
                const tempPage = document.createElement('div');
                tempPage.style.pageBreakAfter = 'always';
                tempPage.style.padding = '20px';
                
                const h = document.createElement('h1');
                h.innerText = t.template_name;
                h.style.borderBottom = '1px solid #333';
                h.style.marginBottom = '20px';
                tempPage.appendChild(h);

                const contentDiv = document.createElement('div');
                contentDiv.innerHTML = getTemplateHtml(t);
                tempPage.appendChild(contentDiv);
                
                masterContainer.appendChild(tempPage);
            });

            document.body.appendChild(masterContainer);

            const opt = {
                margin: 0.5,
                filename: 'All_Email_Templates.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 1.5, useCORS: true },
                jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' },
                pagebreak: { mode: 'always' }
            };

            try {
                await html2pdf().set(opt).from(masterContainer).save();
            } finally {
                document.body.removeChild(masterContainer);
                btn.prop('disabled', false).html('<i class="fas fa-file-pdf me-1"></i> Download All as PDF');
            }
        });
    });
</script>


