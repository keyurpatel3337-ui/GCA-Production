<?php
/**
 * Send Dynamic WhatsApp Message
 */

require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once __DIR__ . '/../../common/api_client.php';
require_once PORTAL_GLOBALVARIABLE;
require_once OPERATION_FILE;
require_once HELPER_FLASH_MESSAGE;
require_once __DIR__ . '/../../common/security_output.php';

// Check access
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_ESTABLISHMENT) && !hasRole(ROLE_COMPUTER_OPERATOR)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title      = "Send Dynamic WhatsApp";
$page_breadcrumb = "Communication - WhatsApp";

include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';

// Pre-fill student if ID is passed
$target_student_id   = $_GET['student_id'] ?? '';
$target_student_name = '';
$target_student_mob  = '';

if ($target_student_id) {
    $stmt = $conn->prepare("SELECT surname, student_name, mob FROM tbl_gm_std_registration WHERE id = ?");
    $stmt->execute([$target_student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($student) {
        $target_student_name = trim($student['surname'] . ' ' . $student['student_name']);
        $target_student_mob  = $student['mob'];
    }
}
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-dark"><?php echo $page_title; ?></h1>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <!-- Left: Form -->
                <div class="col-md-7">
                    <div class="card card-primary card-outline shadow">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fab fa-whatsapp mr-2"></i>Compose Message</h3>
                        </div>
                        <form id="whatsappForm" action="handlers/send-whatsapp-dynamic-handler.php" method="POST">

                            <div class="card-body">

                                <!-- Template Selection -->
                                <div class="form-group">
                                    <label class="font-weight-bold">Select Template <span class="text-danger">*</span></label>
                                    <input type="hidden" name="template_name" id="template_name" value="parent_update_not">
                                    <div class="row">
                                        <div class="col-6">
                                            <div class="tpl-card tpl-active" id="card_specific" data-tpl="parent_update_not">
                                                <i class="fas fa-user-graduate fa-lg mb-1 d-block tpl-icon"></i>
                                                <strong class="d-block">Specific Student</strong>
                                                <small class="tpl-name">parent_update_not</small>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="tpl-card" id="card_common" data-tpl="parent_update_note">
                                                <i class="fas fa-bullhorn fa-lg mb-1 d-block tpl-icon"></i>
                                                <strong class="d-block">Common Notice</strong>
                                                <small class="tpl-name">parent_update_note</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <hr class="mt-1 mb-3">

                                <!-- Student Selection -->
                                <div class="form-group">
                                    <label class="font-weight-bold">Recipient Student <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="text" id="studentSearch" class="form-control"
                                               placeholder="Search by Name, ID or Mobile..."
                                               value="<?php echo htmlspecialchars($target_student_name ? "$target_student_name ($target_student_id)" : ''); ?>"
                                               <?php echo $target_student_id ? 'readonly' : ''; ?>>
                                        <input type="hidden" name="student_id"   id="student_id"   value="<?php echo $target_student_id; ?>" required>
                                        <input type="hidden" name="student_name" id="student_name" value="<?php echo htmlspecialchars($target_student_name); ?>">
                                        <?php if ($target_student_id): ?>
                                            <div class="input-group-append">
                                                <a href="send-whatsapp-dynamic.php" class="btn btn-outline-secondary">
                                                    <i class="fas fa-times mr-1"></i>Change
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div id="searchResults" class="list-group shadow-sm mt-1 css-send-whatsapp-dynamic-829a89"></div>
                                    <small class="text-muted" id="mobDisplay">
                                        <?php if ($target_student_mob): ?>
                                            <i class="fas fa-phone mr-1"></i>Mobile: <strong><?php echo $target_student_mob; ?></strong>
                                        <?php endif; ?>
                                    </small>
                                </div>

                                <hr class="mt-2 mb-3">

                                <!-- Variable {{1}}: Message / Suchna -->
                                <div class="form-group">
                                    <label class="font-weight-bold" id="label_msg">
                                        <span id="label_msg_text">Message <small class="text-muted">(Subject / Notification)</small></span>
                                        <span class="badge badge-primary ml-1">{{1}}</span>
                                        <span class="text-danger">*</span>
                                    </label>
                                    <textarea name="message" id="messageInput" class="form-control" rows="3"
                                              placeholder="Enter notification subject or message..."
                                              required maxlength="300"></textarea>
                                    <div class="d-flex justify-content-between mt-1">
                                        <small class="text-muted" id="hint_msg">Main subject / notification line</small>
                                        <small id="charCount1" class="badge badge-light">0 / 300</small>
                                    </div>
                                </div>

                                <!-- Variable {{2}}: Details (parent_update_note) OR auto student name (parent_update_not) -->
                                <div class="form-group" id="details_group">
                                    <label class="font-weight-bold" id="label_det">
                                        <span id="label_det_text">વધુ વિગત <small class="text-muted">(Additional Details)</small></span>
                                        <span class="badge badge-secondary ml-1">{{2}}</span>
                                        <span class="text-danger" id="det_required">*</span>
                                    </label>
                                    <textarea name="details" id="detailsInput" class="form-control" rows="3"
                                              placeholder="e.g. વધુ માહિતી માટે શાળા ઓફિસ સાથે સંપર્ક કરો"
                                              maxlength="300"></textarea>
                                    <div class="d-flex justify-content-between mt-1">
                                        <small class="text-muted" id="hint_det">Additional details / description</small>
                                        <small id="charCount2" class="badge badge-light">0 / 300</small>
                                    </div>
                                </div>

                                <!-- Student name auto-fill note for parent_update_not -->
                                <div id="auto_name_info" class="alert alert-light border py-2 mb-3 css-send-whatsapp-dynamic-93b8ea">
                                    <small>
                                        <i class="fas fa-info-circle text-primary mr-1"></i>
                                        <strong>{{2}}</strong> will be auto-filled with the selected student's name.
                                        <span id="auto_name_preview" class="text-primary font-weight-bold"></span>
                                    </small>
                                </div>

                                <!-- Template Info -->
                                <div class="alert alert-info py-2 mb-0" id="template_info">
                                    <small id="template_info_text">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        Template: <strong id="tpl_name_display">parent_update_not</strong> &nbsp;|&nbsp;
                                        Category: <strong>Utility</strong> &nbsp;|&nbsp;
                                        Language: <strong id="tpl_lang_display">English</strong>
                                    </small>
                                </div>

                            </div>
                            <div class="card-footer bg-white d-flex justify-content-between align-items-center">
                                <button type="reset" class="btn btn-default" id="resetBtn">
                                    <i class="fas fa-undo mr-1"></i>Clear
                                </button>
                                <button type="submit" class="btn btn-success px-4" id="sendBtn" disabled>
                                    <i class="fab fa-whatsapp mr-2"></i>Send Message
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Right: Preview + Info -->
                <div class="col-md-5">

                    <!-- WhatsApp Preview -->
                    <div class="card shadow-sm border-0 css-send-whatsapp-dynamic-d47ccf">
                        <div class="card-header bg-success text-white css-send-whatsapp-dynamic-686579">
                            <h3 class="card-title mb-0"><i class="fab fa-whatsapp mr-2"></i>WhatsApp Preview</h3>
                        </div>
                        <div class="card-body css-send-whatsapp-dynamic-db16c6">
                            <div class="wa-bubble p-3 bg-white shadow-sm css-send-whatsapp-dynamic-e23099">
                                <span id="previewText"></span>
                                <div class="text-right mt-1">
                                    <small class="text-muted css-send-whatsapp-dynamic-49f064"><?php echo date('H:i'); ?> <i class="fas fa-check-double text-primary"></i></small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Variable Map -->
                    <div class="card card-outline card-primary mt-3">
                        <div class="card-header py-2">
                            <h6 class="card-title mb-0"><i class="fas fa-code mr-1"></i>Variable Mapping</h6>
                        </div>
                        <div class="card-body py-2" id="var_map">
                            <table class="table table-sm table-borderless mb-0">
                                <tr>
                                    <td width="60"><span class="badge badge-primary">{{1}}</span></td>
                                    <td class="text-muted small" id="varmap1">Message — Main notification line</td>
                                </tr>
                                <tr>
                                    <td><span class="badge badge-secondary">{{2}}</span></td>
                                    <td class="text-muted small" id="varmap2">Student Name — Auto from selection</td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Guidelines -->
                    <div class="card card-outline card-warning mt-3">
                        <div class="card-body py-2">
                            <h6 class="font-weight-bold text-warning"><i class="fas fa-exclamation-triangle mr-1"></i>Guidelines</h6>
                            <ul class="small mb-0 pl-3">
                                <li>Gujarati or English — both accepted.</li>
                                <li>Avoid special characters like #, %, &.</li>
                                <li>Both <strong>{{1}}</strong> and <strong>{{2}}</strong> must be filled.</li>
                                <li>Counts as a <strong>Utility</strong> message in BhashSMS wallet.</li>
                            </ul>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>
</div>

<script>
$(document).ready(function () {
    const msgInput       = $('#messageInput');
    const detInput       = $('#detailsInput');
    const sendBtn        = $('#sendBtn');
    const studentIdInput = $('#student_id');
    const studentNameInput = $('#student_name');
    const studentSearch  = $('#studentSearch');
    const searchResults  = $('#searchResults');
    const mobDisplay     = $('#mobDisplay');
    const detailsGroup   = $('#details_group');
    const autoNameInfo   = $('#auto_name_info');

    const TEMPLATES = {
        'parent_update_not': {
            lang: 'English',
            base: "Dear Parent,\n\nThis is regarding {{1}} for student {{2}}.\n\nRegards,\nGCA.",
            v1label: 'Message <small class="text-muted">(Regarding / Subject)</small>',
            v1hint: 'What the notice is about',
            v1ph: 'e.g. fee payment, exam schedule, attendance',
            v2auto: true,
            varmap1: 'Message — Main notification line',
            varmap2: 'Student Name — Auto from selection',
        },
        'parent_update_note': {
            lang: 'Gujarati',
            base: "આદરણીય વાલી,\n\nજ્ઞાનમંજરી વિદ્યાપીઠ તરફથી અગત્યની સૂચના મોકલવામાં આવી છે કે,\n {{1}}.\nઆ અંગેની વધુ વિગત આ મુજબ છે:\n{{2}}.\n\nસહકાર બદલ આભાર,\nજ્ઞાનમંજરી વિદ્યાપીઠ.",
            v1label: 'સૂચના <small class="text-muted">(Subject / Notification)</small>',
            v1hint: 'Main subject / notification line',
            v1ph: 'e.g. ફી ભરવાની છેલ્લી તારીખ 15 મે, 2026 છે',
            v2auto: false,
            v2label: 'વધુ વિગત <small class="text-muted">(Additional Details)</small>',
            v2hint: 'Additional details / description',
            v2ph: 'e.g. વધુ માહિતી માટે શાળા ઓફિસ સાથે સંપર્ક કરો',
            varmap1: 'સૂચના — Main notification line',
            varmap2: 'વધુ વિગત — Additional details',
        }
    };

    function getSelectedTemplate() {
        return $('#template_name').val();
    }

    function updatePreview() {
        const tplKey = getSelectedTemplate();
        const tpl    = TEMPLATES[tplKey];
        const v1     = msgInput.val().trim();
        const v2val  = tpl.v2auto ? (studentNameInput.val().trim()) : detInput.val().trim();

        const ph1 = '<span class="text-primary font-italic">...{{1}} here...</span>';
        const ph2 = '<span class="text-secondary font-italic">...{{2}} here...</span>';

        let preview = tpl.base
            .replace('{{1}}', v1 || ph1)
            .replace('{{2}}', v2val || ph2);
        $('#previewText').html(preview);
    }

    function applyTemplate() {
        const tplKey = getSelectedTemplate();
        const tpl    = TEMPLATES[tplKey];

        // Update card highlight
        $('.tpl-card').removeClass('tpl-active tpl-active-green');
        if (tplKey === 'parent_update_not') {
            $('#card_specific').addClass('tpl-active');
        } else {
            $('#card_common').addClass('tpl-active-green');
        }

        // Update label/placeholder for {{1}}
        $('#label_msg_text').html(tpl.v1label);
        $('#hint_msg').text(tpl.v1hint);
        msgInput.attr('placeholder', tpl.v1ph);

        // Update template info
        $('#tpl_name_display').text(tplKey);
        $('#tpl_lang_display').text(tpl.lang);

        // Update variable map
        $('#varmap1').text(tpl.varmap1);
        $('#varmap2').text(tpl.varmap2);

        if (tpl.v2auto) {
            // Hide details textarea, show auto-name info
            detailsGroup.hide();
            autoNameInfo.show();
            detInput.val('').removeAttr('required');
            const name = studentNameInput.val().trim();
            $('#auto_name_preview').text(name ? `"${name}"` : '');
        } else {
            // Show details textarea
            detailsGroup.show();
            autoNameInfo.hide();
            detInput.attr('required', 'required');
            $('#label_det_text').html(tpl.v2label);
            $('#hint_det').text(tpl.v2hint);
            detInput.attr('placeholder', tpl.v2ph);
        }

        updatePreview();
        validateForm();
    }

    function validateForm() {
        const tplKey = getSelectedTemplate();
        const tpl    = TEMPLATES[tplKey];
        const v1ok   = msgInput.val().trim().length > 0;
        const v2ok   = tpl.v2auto ? studentIdInput.val().length > 0 : detInput.val().trim().length > 0;
        const stdOk  = studentIdInput.val().length > 0;
        sendBtn.prop('disabled', !(v1ok && v2ok && stdOk));
    }

    // Template card click
    $('.tpl-card').on('click', function () {
        const tpl = $(this).data('tpl');
        $('#template_name').val(tpl);
        msgInput.val('');
        detInput.val('');
        $('#charCount1, #charCount2').text('0 / 300');
        applyTemplate();
    });

    msgInput.on('input', function () {
        $('#charCount1').text($(this).val().length + ' / 300');
        updatePreview();
        validateForm();
    });

    detInput.on('input', function () {
        $('#charCount2').text($(this).val().length + ' / 300');
        updatePreview();
        validateForm();
    });

    // Student search
    let searchTimer = null;
    studentSearch.on('keyup', function () {
        clearTimeout(searchTimer);
        let q = $(this).val();
        if (q.length < 3) { searchResults.hide(); return; }
        searchTimer = setTimeout(function () {
            $.ajax({
                url: 'api.php', method: 'GET',
                data: { action: 'search_students', query: q },
                success: function (data) {
                    let results = JSON.parse(data), html = '';
                    if (results.length > 0) {
                        results.forEach(function (s) {
                            html += `<a href="#" class="list-group-item list-group-item-action py-2 select-student"
                                        data-id="${s.id}" data-name="${s.student_name}" data-mob="${s.mob}">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1 fw-bold">${s.student_name}</h6>
                                            <small class="text-primary">ID: ${s.id}</small>
                                        </div>
                                        <small class="text-muted"><i class="fas fa-phone mr-1"></i>${s.mob}</small>
                                     </a>`;
                        });
                    } else {
                        html = '<div class="list-group-item text-muted">No students found</div>';
                    }
                    searchResults.html(html).show();
                }
            });
        }, 300);
    });

    $(document).on('click', '.select-student', function (e) {
        e.preventDefault();
        let id   = $(this).data('id'),
            name = $(this).data('name'),
            mob  = $(this).data('mob');
        studentIdInput.val(id);
        studentNameInput.val(name);
        studentSearch.val(`${name} (${id})`);
        mobDisplay.html(`<i class="fas fa-phone mr-1"></i>Mobile: <strong>${mob}</strong>`);
        searchResults.hide();
        // Update auto-name preview if specific template is active
        $('#auto_name_preview').text(`"${name}"`);
        updatePreview();
        validateForm();
    });

    $(document).click(function (e) {
        if (!$(e.target).closest('.form-group').length) searchResults.hide();
    });

    $('#resetBtn').on('click', function () {
        $('#charCount1, #charCount2').text('0 / 300');
        mobDisplay.text('');
        studentNameInput.val('');
        $('#auto_name_preview').text('');
        sendBtn.prop('disabled', true);
        setTimeout(function () { applyTemplate(); updatePreview(); }, 50);
    });

    $('#whatsappForm').on('submit', function (e) {
        e.preventDefault();
        const form = this;
        const tplKey = getSelectedTemplate();
        const name   = studentNameInput.val().trim() || 'selected student';
        const mob    = mobDisplay.find('strong').text() || '';
        const label  = tplKey === 'parent_update_not' ? 'Specific Student Notice' : 'Common Notice (Gujarati)';
        showConfirm({
            title: 'Send WhatsApp Message',
            message: `Send <strong>"${label}"</strong> WhatsApp to <strong>${name}</strong>${mob ? ' (' + mob + ')' : ''}?`,
            confirmText: 'Yes, Send',
            confirmButtonClass: 'btn-success',
            onConfirm: function () {
                const btn = $('#sendBtn');
                btn.prop('disabled', true).html('<i class="fab fa-whatsapp mr-2"></i> Sending...');
                form.submit();
            }
        });
    });

    // Init
    applyTemplate();
});
</script>



<?php include '../../include/footer.php'; ?>
