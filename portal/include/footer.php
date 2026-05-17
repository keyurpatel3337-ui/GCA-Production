<?php if (!isset($main_closed) || !$main_closed): ?>
    </main><!-- /.app-main -->
<?php endif; ?>

<!-- Footer -->
<footer class="app-footer hidden">
    <div class="float-end d-none d-sm-inline">
        <b>Version</b> <?php echo defined('SYSTEM_VERSION') ? SYSTEM_VERSION : '1.0.0'; ?>
    </div>
    <strong>Copyright &copy; <?php echo date('Y'); ?> <a href="https://gyanmanjari.com/" target="_blank"
            class="text-black text-decoration-none outline-none"><?php echo defined('SYSTEM_NAME') ? SYSTEM_NAME : 'Gyanmanjari'; ?></a>.</strong>
    All rights reserved. | <a href="<?php echo BASE_URL; ?>/portal/website/digital-wallet-policy.php" class="text-secondary text-decoration-none">Wallet Policy</a>
</footer>
</div><!-- /.app-wrapper -->

<!-- Bootstrap 5 Bundle (includes Popper) -->
<script src="<?php echo BASE_URL; ?>/assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<!-- SweetAlert2 -->
<script src="<?php echo BASE_URL; ?>/assets/vendor/sweetalert2/sweetalert2.all.min.js"></script>
<!-- Bootstrap Toasts Container -->
<div class="toast-container position-fixed top-0 end-0 p-3 toast-container-portal" id="toastPlacement"></div>

<!-- Generic Confirmation Modal -->
<div class="modal fade generic-confirm-modal" id="genericConfirmModal" tabindex="-1" aria-labelledby="genericConfirmModalLabel"
    aria-hidden="true">
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
<script src="<?php echo PORTAL_URL; ?>/assets/js/gca-layout.js"></script>
<!-- Select2 -->
<script src="<?php echo PORTAL_URL; ?>/assets/vendor/select2/select2.min.js"></script>
<!-- Chart.js -->
<script src="<?php echo PORTAL_URL; ?>/assets/vendor/chartjs/chart.umd.min.js"></script>
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
<script src="<?php echo PORTAL_URL; ?>/assets/vendor/flatpickr/flatpickr.min.js"></script>

<script>
    // Initialize Bootstrap dropdowns for navbar
    function initNavbarDropdowns() {
        const dropdownElementList = document.querySelectorAll('.navbar [data-bs-toggle="dropdown"]');
        dropdownElementList.forEach(function (dropdownToggleEl) {
            if (!dropdownToggleEl.hasAttribute('data-dropdown-initialized')) {
                try {
                    new bootstrap.Dropdown(dropdownToggleEl, { autoClose: true });
                    dropdownToggleEl.setAttribute('data-dropdown-initialized', 'true');
                } catch (e) { console.error('Error initializing dropdown:', e); }
            }
        });
    }

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
            </div>`;
        container.insertAdjacentHTML('beforeend', toastHtml);
        const toastElement = document.getElementById(toastId);
        const toast = new bootstrap.Toast(toastElement, { delay: 4000 });
        toast.show();
        toastElement.addEventListener('hidden.bs.toast', () => { toastElement.remove(); });
    }

    // Confirmation Helper Function
    function showConfirm(options) {
        const modalElement = document.getElementById('genericConfirmModal');
        if (!modalElement) {
            if (confirm(options.message.replace(/<[^>]*>?/gm, ''))) { if (typeof options.onConfirm === 'function') options.onConfirm(); }
            return;
        }
        const modal = new bootstrap.Modal(modalElement);
        $('#genericConfirmModal .modal-title').html(options.title || 'Confirm Action');
        $('#genericConfirmModalBody').html(options.message || 'Are you sure?');
        const confirmBtn = $('#genericConfirmModalBtn');
        confirmBtn.html(options.confirmText || 'Confirm').off('click').on('click', function () {
                if (typeof options.onConfirm === 'function') options.onConfirm();
                modal.hide();
            });
        confirmBtn.removeClass('btn-primary btn-danger btn-success btn-warning btn-info')
            .addClass('btn ' + (options.confirmButtonClass || 'btn-primary'));
        modal.show();
    }

    $(document).ready(function () {
        initNavbarDropdowns();
        
        // Remove preload class
        setTimeout(() => { document.body.classList.remove('preload-transitions'); }, 500);

        // Set API URL for notification bell
        const bellElement = document.querySelector('#notification-bell');
        if (bellElement) bellElement.setAttribute('data-api-url', '<?php echo PORTAL_URL; ?>/api/notifications.php');

        // Global fix: Move all modals to body
        if ($('.modal').length > 0) { $('.modal').appendTo('body'); }

        if ($.fn.select2) { $('.select2').select2({ theme: 'bootstrap4' }); }

        if (typeof flatpickr !== 'undefined') {
            flatpickr('input[type="date"]', { dateFormat: "Y-m-d", altInput: true, altFormat: "d-m-Y", allowInput: true });
        }

        $('.btn-delete').off('click').on('click', function (e) {
            return confirm("Are you sure you want to delete this record? This action cannot be undone.");
        });

        // Handle Session Messages
        <?php
        if (function_exists('has_flash_messages') && has_flash_messages()) {
            $messages = get_flash_messages(true);
            foreach ($messages as $msg) {
                $type = $msg['type'];
                $title = ucfirst($type);
                $text = function_exists('gca_safe_js') ? gca_safe_js($msg['message']) : json_encode($msg['message']);
                echo "showToast('{$type}', '{$title}', {$text});\n";
            }
        }
        unset($_SESSION['success_msg'], $_SESSION['error_msg'], $_SESSION['success'], $_SESSION['error']);
        ?>

        // Prevent resubmission
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    });
</script>

<?php
$disable_inspect = (defined('ENVIRONMENT') && ENVIRONMENT === 'production') || (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'gyanmanjari.com') !== false);
if ($disable_inspect): ?>
    <script>
        document.addEventListener('contextmenu', event => event.preventDefault());
        document.onkeydown = function (e) {
            if (e.keyCode == 123 || (e.ctrlKey && e.shiftKey && (e.keyCode == 73 || e.keyCode == 67 || e.keyCode == 74)) || (e.ctrlKey && e.keyCode == 85)) return false;
        }
    </script>
<?php endif; ?>

</body>
</html>