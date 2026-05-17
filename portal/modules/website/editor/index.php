<?php

/**
 * Simple Database-Driven Content Editor
 * Fetches all content directly from database
 */

require_once __DIR__ . '/../../../session_config.php';
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/common/constants.php'; require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;

// Check roles
if (!hasRole(ROLE_WEBSITE_ADMIN) && !hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_id = $_POST['page'] ?? 1; // Default to Home Page

// Fetch Page Data from Database
$op = new Operation();
$page_data = $op->selectOne('tbl_pages', ['*'], ['id' => $page_id]);

if (!$page_data) {
    die("Page not found.");
}

// Fetch Sections from Database
$sections = $op->readAll('tbl_page_sections', ['page_id' => $page_id], 'display_order ASC');

$page_title = "Editor: " . $page_data['page_name'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="../../../assets/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/modules/website/editor/index.css">
</head>

<body>

    <nav class="editor-top-nav">
        <div class="d-flex align-items-center gap-3">
            <a href="../website_admin_dashboard.php" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <div class="h5 mb-0 fw-bold"><?php echo $page_data['page_name']; ?></div>
        </div>

        <div class="device-selector">
            <div class="device-btn active" data-device="desktop"><i class="fas fa-desktop"></i></div>
            <div class="device-btn" data-device="tablet"><i class="fas fa-tablet-alt"></i></div>
            <div class="device-btn" data-device="mobile"><i class="fas fa-mobile-alt"></i></div>
        </div>

        <div>
            <button id="saveBtn" class="btn btn-primary px-4 fw-bold">
                <i class="fas fa-save me-2"></i> Save Changes
            </button>
        </div>
    </nav>

    <div class="editor-container">
        <!-- Sidebar Controls -->
        <div class="editor-sidebar">
            <div class="mb-4">
                <label class="text-muted small fw-bold text-uppercase mb-2 d-block">Page Sections</label>
                <?php foreach ($sections as $section): ?>
                    <div class="section-card" data-section-id="<?php echo $section['id']; ?>">
                        <div class="section-header">
                            <span><i class="fas fa-layer-group me-2 text-primary"></i>
                                <?php echo $section['section_name']; ?></span>
                            <i class="fas fa-chevron-right small transition"></i>
                        </div>
                        <div class="section-content">
                            <?php
                            $fields = $dbOps->customSelect("SELECT * FROM tbl_page_content WHERE section_id = ?", [$section['id']]);
                            foreach ($fields as $field):
                                ?>
                                <div class="mb-3">
                                    <label
                                        class="form-label small fw-bold"><?php echo ucwords(str_replace('_', ' ', $field['field_key'])); ?></label>
                                    <?php if ($field['field_type'] == 'text' || $field['field_type'] == 'url'): ?>
                                        <input type="<?php echo $field['field_type'] == 'url' ? 'url' : 'text'; ?>"
                                            class="form-control cms-input" data-key="<?php echo $field['field_key']; ?>"
                                            value="<?php echo htmlspecialchars($field['field_value'] ?? ''); ?>">
                                    <?php elseif ($field['field_type'] == 'textarea'): ?>
                                        <textarea class="form-control cms-input" rows="3"
                                            data-key="<?php echo $field['field_key']; ?>"><?php echo htmlspecialchars($field['field_value'] ?? ''); ?></textarea>
                                    <?php elseif ($field['field_type'] == 'image'): ?>
                                        <div class="input-group">
                                            <input type="text" class="form-control cms-input"
                                                data-key="<?php echo $field['field_key']; ?>"
                                                value="<?php echo htmlspecialchars($field['field_value'] ?? ''); ?>">
                                            <button class="btn btn-outline-secondary"><i class="fas fa-upload"></i></button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Preview Pane -->
        <div class="editor-preview">
            <iframe id="previewFrame" src="<?php echo BASE_URL; ?>/index.php?preview=1" class="preview-window"></iframe>

            <div class="save-bar">
                <div class="text-muted small">
                    <i class="fas fa-sync-alt fa-spin me-2" style="display:none;" id="syncIcon"></i>
                    <span id="syncText">Draft: All changes synced</span>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(function () {
            const $frame = $('#previewFrame');

            // Section Accordion
            $('.section-header').on('click', function () {
                const $card = $(this).closest('.section-card');
                $card.toggleClass('active').siblings().removeClass('active');
            });

            // Real-time Preview via postMessage
            $('.cms-input').on('input', function () {
                const key = $(this).data('key');
                const val = $(this).val();

                // Update iFrame
                $frame[0].contentWindow.postMessage({
                    type: 'UPDATE_CONTENT',
                    key: key,
                    value: val
                }, '*');

                $('#syncText').text('Draft: Unsaved changes...');
            });

            // Device Switcher
            $('.device-btn').on('click', function () {
                const device = $(this).data('device');
                $('.device-btn').removeClass('active');
                $(this).addClass('active');

                if (device === 'desktop') $frame.css('width', '100%');
                else if (device === 'tablet') $frame.css('width', '768px');
                else if (device === 'mobile') $frame.css('width', '375px');
            });

            // Save Functionality
            $('#saveBtn').on('click', function () {
                const $btn = $(this);
                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i> Saving...');

                const data = [];
                $('.cms-input').each(function () {
                    data.push({
                        key: $(this).data('key'),
                        value: $(this).val()
                    });
                });

                $.ajax({
                    url: 'ajax_save.php',
                    method: 'POST',
                    data: {
                        page_id: <?php echo $page_id; ?>,
                        content: JSON.stringify(data)
                    },
                    success: function (response) {
                        $btn.prop('disabled', false).html('<i class="fas fa-save me-2"></i> Save Changes');
                        $('#syncText').text('Draft: All changes saved');
                        alert('Changes saved successfully!');
                    }
                });
            });
        });
    </script>
</body>

</html>