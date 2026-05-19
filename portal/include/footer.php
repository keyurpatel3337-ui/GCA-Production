<?php if (!isset($main_closed) || !$main_closed): ?>
    </main><!-- /.app-main -->
<?php endif; ?>

<!-- Footer -->
<footer class="app-footer" style="display: none !important;">
    <div class="float-end d-none d-sm-inline">
        <b>Version</b> <?php echo defined('SYSTEM_VERSION') ? SYSTEM_VERSION : '1.0.0'; ?>
    </div>
    <!-- <strong>Copyright &copy; <?php //echo date('Y'); 
    ?> <a href="#"><?php //echo defined('SYSTEM_NAME') ? SYSTEM_NAME : 'Gyanmanjari'; 
     ?></a>.</strong> All rights reserved. -->
    <strong>Copyright &copy; <?php echo date('Y'); ?> <a href="https://gyanmanjari.com/" target="_blank"
            class="text-black text-decoration-none outline-none"><?php echo defined('SYSTEM_NAME') ? SYSTEM_NAME : 'Gyanmanjari'; ?></a>.</strong>
    All rights reserved. | <a href="<?php echo BASE_URL; ?>/portal/website/digital-wallet-policy.php" class="text-secondary text-decoration-none">Wallet Policy</a>
</footer>
</div><!-- /.app-wrapper -->

<!-- Bootstrap 5 Bundle (includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
<!-- Bootstrap Toasts Container -->
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999;" id="toastPlacement"></div>

<!-- Generic Confirmation Modal -->
<div class="modal fade" id="genericConfirmModal" tabindex="-1" aria-labelledby="genericConfirmModalLabel"
    aria-hidden="true" style="z-index: 10000;">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="genericConfirmModalLabel">Confirm Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="genericConfirmModalBody">
                Are you sure you want to proceed?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="genericConfirmModalBtn">Confirm</button>
            </div>
        </div>
    </div>
</div>

<!-- Core Portal JS -->
<!-- Select2 -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<!-- API Client -->
<script src="<?php echo PORTAL_URL; ?>/assets/js/api-client.js"></script>
<!-- Table Utilities (exportToExcel, selectAll, toggleDeleteButton) -->
<script src="<?php echo PORTAL_URL; ?>/assets/js/table-utilities.js"></script>
<!-- Delete Handler (deleteItem, deleteSelected) -->
<script src="<?php echo PORTAL_URL; ?>/assets/js/delete-handler.js"></script>
<!-- Fixed Layout JS -->
<script src="<?php echo PORTAL_URL; ?>/assets/js/fixed-layout.js"></script>
<!-- Student Search Component -->
<script src="<?php echo PORTAL_URL; ?>/assets/js/student-search.js"></script>
<!-- Notification Bell Component -->
<script src="<?php echo PORTAL_URL; ?>/assets/js/notification-bell.js"></script>
<!-- Form Validator (3-Layer Validation - JS Layer) -->
<script src="<?php echo PORTAL_URL; ?>/assets/js/form-validator.js"></script>
<!-- Receipt Utilities (downloadReceipt) -->
<script src="<?php echo PORTAL_URL; ?>/assets/js/receipt-utils.js"></script>
<!-- Flatpickr -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<script>
    // Global SweetAlert2 Alert Interceptor
    if (typeof Swal !== 'undefined') {
        window.alert = function(message) {
            Swal.fire({
                html: `<div style="font-family: 'Inter', sans-serif; font-size: 1.05rem; color: #2d3748; line-height: 1.5; font-weight: 500;">${message}</div>`,
                icon: 'warning',
                confirmButtonColor: '#0d6efd',
                confirmButtonText: 'OK',
                heightAuto: false
            });
        };
    }

    // Set API URL for notification bell before it initializes
    document.addEventListener('DOMContentLoaded', () => {
        const bellElement = document.querySelector('#notification-bell');
        if (bellElement && !bellElement.hasAttribute('data-api-url')) {
            bellElement.setAttribute('data-api-url', '<?php echo PORTAL_URL; ?>/api/notifications.php');
        }
    }, {
        capture: true,
        once: true
    });

    // Toast Helper Function
    function showToast(type, title, message) {
        const container = document.getElementById('toastPlacement');
        if (!container) return;

        const icon = type === 'success' ? 'fa-check-circle' : (type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle');
        const bgClass = type === 'success' ? 'bg-success' : (type === 'error' ? 'bg-danger' : 'bg-info');

        const toastId = 'toast-' + Date.now();
        const toastHtml = `
            <div id="${toastId}" class="toast align-items-center text-white ${bgClass} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fas ${icon} me-2"></i><strong>${title}</strong><br>${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        `;

        container.insertAdjacentHTML('beforeend', toastHtml);
        const toastElement = document.getElementById(toastId);
        const toast = new bootstrap.Toast(toastElement, { delay: 4000 });
        toast.show();

        // Remove from DOM after hidden
        toastElement.addEventListener('hidden.bs.toast', () => {
            toastElement.remove();
        });
    }

    // Confirmation Helper Function (Bootstrap Modal)
    function showConfirm(options) {
        const modalElement = document.getElementById('genericConfirmModal');
        if (!modalElement) {
            // Fallback to native confirm if modal doesn't exist
            if (confirm(options.message.replace(/<[^>]*>?/gm, ''))) {
                if (typeof options.onConfirm === 'function') options.onConfirm();
            }
            return;
        }

        const modal = new bootstrap.Modal(modalElement);
        $('#genericConfirmModal .modal-title').html(options.title || 'Confirm Action');
        $('#genericConfirmModalBody').html(options.message || 'Are you sure?');

        const confirmBtn = $('#genericConfirmModalBtn');
        confirmBtn.html(options.confirmText || 'Confirm')
            .off('click')
            .on('click', function () {
                if (typeof options.onConfirm === 'function') {
                    options.onConfirm();
                }
                modal.hide();
            });

        // Set button class
        confirmBtn.removeClass('btn-primary btn-danger btn-success btn-warning btn-info')
            .addClass('btn ' + (options.confirmButtonClass || 'btn-primary'));

        modal.show();
    }

    // PDF Generation with logging to console and server
    function generateSecurePDF(url, params = {}) {
        const queryParams = new URLSearchParams(params).toString();
        const fullUrl = queryParams ? `${url}${url.includes('?') ? '&' : '?'}${queryParams}` : url;

        console.log(`[PDF] Call: ${url}`, params);

        fetch(fullUrl)
            .then(async response => {
                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error(errorText || `HTTP ${response.status}`);
                }

                const contentType = response.headers.get('content-type');
                if (contentType && !contentType.includes('application/pdf')) {
                    const responseText = await response.text();
                    throw new Error(`Server returned non-PDF: ${responseText.substring(0, 100)}...`);
                }

                return response.blob();
            })
            .then(blob => {
                const blobUrl = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = blobUrl;
                link.target = '_blank';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                setTimeout(() => URL.revokeObjectURL(blobUrl), 100);
            })
            .catch(error => {
                console.error('[PDF Error]', error);

                // Log to server as well
                fetch('<?php echo PORTAL_URL; ?>/ajax/log-client-error.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        message: error.message,
                        context: 'PDF Generation',
                        details: { url, params }
                    })
                }).catch(e => console.error('Failed to log to server:', e));

                showToast('error', 'Generation Failed', 'Could not generate PDF. Check console for details.');
            });
    }

    $(document).ready(function () {
        // Global fix: Move all modals to body
        if ($('.modal').length > 0) {
            $('.modal').appendTo('body');
        }

        if ($.fn.select2) {
            $('.select2').select2({
                theme: 'bootstrap4'
            });
        }

        // Initialize Flatpickr on all date inputs to display dd-mm-yyyy but process Y-m-d
        if (typeof flatpickr !== 'undefined') {
            flatpickr('input[type="date"]', {
                dateFormat: "Y-m-d",
                altInput: true,
                altFormat: "d-m-Y",
                allowInput: true
            });
        }

        // Confirm delete action - Using showConfirm modal
        $('.btn-delete').off('click').on('click', function (e) {
            e.preventDefault();
            const $btn = $(this);
            showConfirm({
                title: 'Delete Record',
                message: 'Are you sure you want to delete this record? This action cannot be undone.',
                confirmText: 'Yes, Delete',
                confirmButtonClass: 'btn-danger',
                onConfirm: function () {
                    // Re-trigger the original action (form submit or href)
                    if ($btn.closest('form').length) {
                        $btn.closest('form').submit();
                    } else if ($btn.attr('href') && $btn.attr('href') !== '#') {
                        window.location.href = $btn.attr('href');
                    } else {
                        $btn.off('click').trigger('click');
                    }
                }
            });
            return false;
        });

        // Handle Session Messages via Toasts
        <?php
        // New flash message system
        if (function_exists('has_flash_messages') && has_flash_messages()) {
            $messages = get_flash_messages(true); // Get and clear messages
            foreach ($messages as $msg) {
                $type = $msg['type'];
                $title = ucfirst($type);
                $text = function_exists('safe_js') ? gca_safe_js($msg['message']) : json_encode($msg['message']);
                echo "showToast('{$type}', '{$title}', {$text});\n";
            }
        }

        // Fallback unsets for old session keys to prevent persistence
        if (isset($_SESSION['success_msg']))
            unset($_SESSION['success_msg']);
        if (isset($_SESSION['error_msg']))
            unset($_SESSION['error_msg']);
        if (isset($_SESSION['success']))
            unset($_SESSION['success']);
        if (isset($_SESSION['error']))
            unset($_SESSION['error']);
        ?>
    });

    // ============================================
    // SIDEBAR DROPDOWN TOGGLE - Enhanced with better initialization
    // ============================================
    console.log('[OES] Sidebar logic initializing...');

    // Use event delegation for sidebar dropdowns (more robust than direct binding)
    document.addEventListener('click', function (e) {
        const navLink = e.target.closest('.app-sidebar .nav-item > .nav-link, .sidebar-menu .nav-item > .nav-link, .nav-item > .nav-link');
        if (!navLink) return;

        const navItem = navLink.closest('.nav-item');
        const treeview = navItem.querySelector('.nav-treeview');

        // Only handle if it's a dropdown parent
        if (treeview || navLink.querySelector('.nav-arrow') || navLink.getAttribute('href') === '#' || navLink.getAttribute('href') === 'javascript:void(0)') {
            console.log('[OES] Sidebar dropdown clicked:', navLink.textContent.trim());
            e.preventDefault();
            e.stopPropagation();

            const isOpen = navItem.classList.contains('menu-open');

            // Close other open menus (accordion behavior)
            document.querySelectorAll('.app-sidebar .nav-item.menu-open, .sidebar-menu .nav-item.menu-open').forEach(function (item) {
                if (item !== navItem) {
                    item.classList.remove('menu-open');
                    const tv = item.querySelector('.nav-treeview');
                    if (tv) tv.style.display = 'none';
                }
            });

            // Toggle current menu
            if (isOpen) {
                navItem.classList.remove('menu-open');
                if (treeview) treeview.style.display = 'none';
            } else {
                navItem.classList.add('menu-open');
                if (treeview) treeview.style.display = 'block';
            }
        }
    }, { capture: true });

    // Run on DOMContentLoaded
    document.addEventListener('DOMContentLoaded', function () {
        initNavbarDropdowns();
        setActiveMenu();

        setTimeout(function () {
            document.body.classList.remove('preload-transitions');
        }, 500);
    });

    // Also run immediately in case DOM is already loaded
    if (document.readyState === 'interactive' || document.readyState === 'complete') {
        initNavbarDropdowns();
    }

    // Initialize Bootstrap dropdowns for navbar (profile, notifications, etc.)
    function initNavbarDropdowns() {
        // Get all dropdown toggles in navbar
        const dropdownElementList = document.querySelectorAll('.navbar [data-bs-toggle="dropdown"]');

        // Initialize each dropdown with Bootstrap 5
        dropdownElementList.forEach(function (dropdownToggleEl) {
            // Check if already initialized
            if (!dropdownToggleEl.hasAttribute('data-dropdown-initialized')) {
                try {
                    new bootstrap.Dropdown(dropdownToggleEl, {
                        autoClose: true
                    });
                    dropdownToggleEl.setAttribute('data-dropdown-initialized', 'true');
                } catch (e) {
                    console.error('Error initializing dropdown:', e);
                }
            }
        });
    }

    function setActiveMenu() {
        const currentPath = window.location.pathname;
        const navLinks = document.querySelectorAll('.app-sidebar .nav-link, .sidebar-menu .nav-link');

        navLinks.forEach(function (link) {
            const href = link.getAttribute('href');
            if (href && href !== '#') {
                if (currentPath.includes(href)) {
                    const parentTreeview = link.closest('.nav-treeview');
                    if (parentTreeview) {
                        const parentNavItem = parentTreeview.closest('.nav-item');
                        if (parentNavItem && !parentNavItem.classList.contains('menu-open')) {
                            parentNavItem.classList.add('menu-open');
                        }
                    }
                }
            }
        });

        navLinks.forEach(function (link) {
            const href = link.getAttribute('href');
            if (href && href !== '#') {
                if (currentPath.includes(href)) {
                    link.classList.add('active');
                }
            }
        });
    }

</script>

<?php
// Disable inspect on production
$disable_inspect = (defined('ENVIRONMENT') && ENVIRONMENT === 'production');
// Check hostname for gyanmanjari.com
if (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'gyanmanjari.com') !== false) {
    $disable_inspect = true;
}

if ($disable_inspect):
    ?>
    <script>
        document.addEventListener('contextmenu', event => event.preventDefault());
        document.onkeydown = function (e) {
            if (e.keyCode == 123) { // F12
                return false;
            }
            if (e.ctrlKey && e.shiftKey && e.keyCode == 'I'.charCodeAt(0)) { // Ctrl+Shift+I
                return false;
            }
            if (e.ctrlKey && e.shiftKey && e.keyCode == 'C'.charCodeAt(0)) { // Ctrl+Shift+C
                return false;
            }
            if (e.ctrlKey && e.shiftKey && e.keyCode == 'J'.charCodeAt(0)) { // Ctrl+Shift+J
                return false;
            }
            if (e.ctrlKey && e.keyCode == 'U'.charCodeAt(0)) { // Ctrl+U
                return false;
            }
        }
    </script>
<?php endif; ?>

<script>
    // Lock browser back navigation on sensitive pages
    // Effectively prevents the user from going back to a previous state/form
    (function() {
        window.history.pushState(null, null, window.location.href);
        window.onpopstate = function() {
            window.history.pushState(null, null, window.location.href);
        };
    })();

    // Prevent "Confirm Form Resubmission" dialog on refresh
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }
</script>

</body>

</html>