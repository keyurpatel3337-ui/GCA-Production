<?php if (!isset($main_closed) || !$main_closed): ?>
    </main><!-- /.app-main -->
<?php endif; ?>

<!-- Footer -->
<footer class="app-footer">
    <div class="float-end d-none d-sm-inline">
        <b>Version</b> <?php echo SYSTEM_VERSION; ?>
    </div>
    <strong>Copyright &copy; 2025 <a href="#"><?php echo SYSTEM_NAME; ?></a>.</strong> All rights reserved.
</footer>
</div><!-- /.app-wrapper -->

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<!-- Bootstrap 5 Bundle (includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- Bootstrap Toasts Container -->
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999;" id="toastPlacement"></div>

<!-- AdminLTE 4 -->
<script src="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta3/dist/js/adminlte.min.js"></script>
<!-- Select2 -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<!-- Fixed Layout JS -->
<script src="<?php echo PORTAL_URL; ?>/assets/js/fixed-layout.js"></script>
<!-- Student Search Component -->
<script src="<?php echo PORTAL_URL; ?>/assets/js/student-search.js"></script>
<!-- Notification Bell Component -->
<script src="<?php echo PORTAL_URL; ?>/assets/js/notification-bell.js"></script>
<script>
    // Set API URL for notification bell before it initializes
    document.addEventListener('DOMContentLoaded', () => {
        const bellElement = document.querySelector('#notification-bell');
        if (bellElement && !bellElement.hasAttribute('data-api-url')) {
            bellElement.setAttribute('data-api-url', '<?php echo PORTAL_URL; ?>/api/notifications.php');
        }
    }, {
        capture: true,
        once: true
    }); // Run before notification bell's listener

    // Toast Helper Function
    function showToast(type, title, message) {
        const container = document.getElementById('toastPlacement');
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

    $(document).ready(function () {
        // Global fix: Move all modals to body
        if ($('.modal').length > 0) {
            $('.modal').appendTo('body');
        }

        // Initialize manual tables
        $('table[id]').each(function () {
            if ($(this).attr('id') && $(this).find('tbody tr').length > 0 && !$(this).hasClass('no-auto-paginate')) {
                initManualTable($(this).attr('id'));
            }
        });

        // Initialize Select2
        if ($.fn.select2) {
            $('.select2').select2({
                theme: 'bootstrap4'
            });
        }

        // Confirm delete action - Using standard confirm()
        $('.btn-delete').off('click').on('click', function (e) {
            return confirm("Are you sure you want to delete this record? This action cannot be undone.");
        });

        // Handle Session Messages via Toasts
        <?php
        // New flash message system
        if (function_exists('get_flash_messages')) {
            $messages = get_flash_messages(true);
            foreach ($messages as $msg) {
                $type = $msg['type'];
                $title = ucfirst($type);
                $text = function_exists('safe_js') ? safe_js($msg['message']) : json_encode($msg['message']);
                echo "showToast('{$type}', '{$title}', {$text});\n";
            }
        }

        // Legacy system fallbacks
        if (isset($_SESSION['success_msg'])) {
            $text = function_exists('safe_js') ? safe_js($_SESSION['success_msg']) : json_encode($_SESSION['success_msg']);
            echo "showToast('success', 'Success', {$text});\n";
            unset($_SESSION['success_msg']);
        }
        if (isset($_SESSION['error_msg'])) {
            $text = function_exists('safe_js') ? safe_js($_SESSION['error_msg']) : json_encode($_SESSION['error_msg']);
            echo "showToast('error', 'Error', {$text});\n";
            unset($_SESSION['error_msg']);
        }
        ?>
    });

    // ============================================
    // SIDEBAR DROPDOWN TOGGLE - Simple, No Animation
    // ============================================
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize sidebar dropdown functionality
        initSidebarDropdowns();

        // Set active menu based on current page
        setActiveMenu();

        // Remove preload class after delay to enable smooth transitions for user interactions only
        // 500ms delay ensures AdminLTE JS has finished initializing the menu state
        setTimeout(function() {
            document.body.classList.remove('preload-transitions');
        }, 500);
    });

    function initSidebarDropdowns() {
        // Get all nav items with dropdowns (having nav-treeview)
        const navItems = document.querySelectorAll('.sidebar .nav-item:has(.nav-treeview)');

        navItems.forEach(function(navItem) {
            const navLink = navItem.querySelector(':scope > .nav-link');
            const treeview = navItem.querySelector('.nav-treeview');

            if (navLink && treeview) {
                // Prevent default link behavior
                navLink.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    // Check if this menu is already open
                    const isOpen = navItem.classList.contains('menu-open');

                    // Close all other open menus (accordion behavior)
                    const allNavItems = document.querySelectorAll('.sidebar .nav-item.menu-open');
                    allNavItems.forEach(function(item) {
                        if (item !== navItem) {
                            item.classList.remove('menu-open');
                        }
                    });

                    // Toggle current menu - simple show/hide
                    if (isOpen) {
                        navItem.classList.remove('menu-open');
                    } else {
                        navItem.classList.add('menu-open');
                    }
                });
            }
        });
    }

    function setActiveMenu() {
        const currentPath = window.location.pathname;
        const navLinks = document.querySelectorAll('.sidebar .nav-link');

        // First pass: Set menu-open class immediately to prevent animation
        navLinks.forEach(function(link) {
            const href = link.getAttribute('href');

            if (href && href !== '#') {
                if (currentPath.includes(href)) {
                    const parentTreeview = link.closest('.nav-treeview');
                    if (parentTreeview) {
                        const parentNavItem = parentTreeview.closest('.nav-item');
                        if (parentNavItem && !parentNavItem.classList.contains('menu-open')) {
                            // Add class immediately without any animation
                            parentNavItem.classList.add('menu-open');
                        }
                    }
                }
            }
        });

        // Second pass: Add active styling
        navLinks.forEach(function(link) {
            const href = link.getAttribute('href');

            if (href && href !== '#') {
                if (currentPath.includes(href)) {
                    link.classList.add('active');
                }
            }
        });
    }

    // ============================================
    // SIDEBAR RESPONSIVE BEHAVIOR
    // ============================================
    document.addEventListener('DOMContentLoaded', function() {
        // On mobile, start with collapsed sidebar
        if (window.innerWidth < 992) {
            document.body.classList.add('sidebar-collapse');
        }

        // Sidebar toggle button - toggles sidebar-collapse class on body for mini sidebar
        const sidebarToggle = document.querySelector('[data-lte-toggle="sidebar"]');
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                // Toggle classes
                document.body.classList.toggle('sidebar-collapse');
                document.body.classList.toggle('sidebar-open');

                // Store preference
                const isCollapsed = document.body.classList.contains('sidebar-collapse');
                localStorage.setItem('sidebar-collapsed', isCollapsed);
            });
        }

        // Restore sidebar state from localStorage
        const savedState = localStorage.getItem('sidebar-collapsed');
        if (savedState === 'true') {
            document.body.classList.add('sidebar-collapse');
            document.body.classList.remove('sidebar-open');
        } else if (savedState === 'false') {
            document.body.classList.remove('sidebar-collapse');
            document.body.classList.add('sidebar-open');
        }
    });

    // Manual Table Search and Sort Utility
    function initManualTable(tableId) {
        const table = $('#' + tableId);
        const tbody = table.find('tbody');
        const rows = tbody.find('tr').toArray();
        let currentPage = 1;
        const perPage = 10;
        let filteredRows = rows;

        // Find card container and header
        const card = table.closest('.card');
        const cardHeader = card.find('.card-header');

        // Add search box if not exists
        if (!card.find('.table-search').length && !table.prev('.table-search-fallback').length) {
            const searchHtml = `
                  <div class="table-search ms-auto">
                      <div class="input-group input-group-sm" style="width: 200px;">
                          <input type="text" class="form-control table-search-input" placeholder="Search...">
                          <span class="input-group-text"><i class="fas fa-search"></i></span>
                      </div>
                  </div>
              `;

            if (cardHeader.length) {
                // Inject into card header
                if (!cardHeader.hasClass('d-flex')) {
                    cardHeader.addClass('d-flex justify-content-between align-items-center');
                }
                cardHeader.append(searchHtml);
            } else {
                // Fallback: Inject above table
                table.parent().before(`<div class="d-flex justify-content-end mb-3 table-search-fallback">${searchHtml}</div>`);
            }

            // Add pagination wrapper below table
            const paginationHtml = `
                  <div class="table-pagination-wrapper mt-3 px-3 pb-3">
                      <div class="d-flex justify-content-between align-items-center flex-wrap">
                          <div class="text-muted small table-info">
                               Showing <span class="current-range">0-0</span> of <span class="total-count">0</span> entries
                          </div>
                          <nav>
                              <ul class="pagination pagination-sm mb-0">
                              </ul>
                          </nav>
                      </div>
                  </div>
              `;

            // If table is inside card-body, we might want to put pagination in card-footer?
            // For now, putting it after table is safe.
            if (card.find('.card-footer').length) {
                // reuse existing footer if specifically empty?
                card.find('.card-footer').html(paginationHtml); // Caution: might overwrite
            } else {
                table.parent().after(paginationHtml);
            }
        }

        // Re-select specific elements
        const searchInput = card.find('.table-search-input').length ? card.find('.table-search-input') : table.parent().prev().find('.table-search-input');
        const paginationWrapper = table.closest('.card').find('.table-pagination-wrapper').length ? table.closest('.card').find('.table-pagination-wrapper') : table.parent().next('.table-pagination-wrapper');

        const rangeDisplay = paginationWrapper.find('.current-range');
        const totalDisplay = paginationWrapper.find('.total-count');
        const paginationNav = paginationWrapper.find('.pagination');

        // Render pagination
        function renderPagination() {
            const totalPages = Math.ceil(filteredRows.length / perPage);
            paginationNav.empty();

            if (totalPages <= 1 && filteredRows.length > 0) return;

            // Previous button
            paginationNav.append(`
                  <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
                      <a class="page-link" href="#" data-page="${currentPage - 1}"><i class="fas fa-chevron-left"></i></a>
                  </li>
              `);

            // Page numbers
            for (let i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= currentPage - 2 && i <= currentPage + 2)) {
                    paginationNav.append(`
                          <li class="page-item ${i === currentPage ? 'active' : ''}">
                              <a class="page-link" href="#" data-page="${i}">${i}</a>
                          </li>
                      `);
                } else if (i === currentPage - 3 || i === currentPage + 3) {
                    paginationNav.append(`<li class="page-item disabled"><span class="page-link">...</span></li>`);
                }
            }

            // Next button
            paginationNav.append(`
                  <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
                      <a class="page-link" href="#" data-page="${currentPage + 1}"><i class="fas fa-chevron-right"></i></a>
                  </li>
              `);

            // Pagination click handler
            paginationNav.find('a.page-link').on('click', function(e) {
                e.preventDefault();
                const page = parseInt($(this).data('page'));
                if (page > 0 && page <= totalPages) {
                    currentPage = page;
                    displayPage();
                }
            });
        }

        // Display current page
        function displayPage() {
            tbody.empty();
            const start = (currentPage - 1) * perPage;
            const end = start + perPage;
            const pageRows = filteredRows.slice(start, end);
            tbody.append(pageRows);

            const showing = filteredRows.length > 0 ? `${start + 1}-${Math.min(end, filteredRows.length)}` : '0-0';
            rangeDisplay.text(showing);
            totalDisplay.text(filteredRows.length);

            renderPagination();
        }

        // Search functionality
        searchInput.off('keyup').on('keyup', function() {
            const searchTerm = $(this).val().toLowerCase();
            filteredRows = rows.filter(row => {
                const text = $(row).text().toLowerCase();
                return text.includes(searchTerm);
            });
            currentPage = 1;
            displayPage();
        });

        // Sort functionality
        table.find('th').css('cursor', 'pointer').off('click').on('click', function() {
            const th = $(this);
            const index = th.index();
            const isAsc = th.hasClass('sort-asc');

            table.find('th').removeClass('sort-asc sort-desc');
            th.addClass(isAsc ? 'sort-desc' : 'sort-asc');

            filteredRows.sort((a, b) => {
                const aVal = $(a).find('td').eq(index).text();
                const bVal = $(b).find('td').eq(index).text();
                const comparison = aVal.localeCompare(bVal, undefined, {
                    numeric: true
                });
                return isAsc ? -comparison : comparison;
            });

            currentPage = 1;
            displayPage();
        });

        // Default sort by first column (ID) in ascending order
        if (rows.length > 0) {
            table.find('th').eq(0).addClass('sort-asc');
        }

        // Initial display
        displayPage();
    }
</script>

</body>

</html>