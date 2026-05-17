/**
 * GCA Custom Layout JS
 * Handles Sidebar Toggle and Treeview functionality
 */

(function() {
    "use strict";

    function initLayout() {
        const body = document.body;

        // 1. Sidebar Collapse/Expand Toggle
        document.addEventListener('click', function(e) {
            const toggle = e.target.closest('[data-lte-toggle="sidebar"], [data-lte-toggle="sidebar-mobile"], [data-lte-toggle="sidebar-desktop"]');
            if (toggle) {
                e.preventDefault();
                e.stopPropagation();
                if (window.innerWidth >= 992) {
                    body.classList.toggle('sidebar-collapse');
                    localStorage.setItem('sidebar-collapsed', body.classList.contains('sidebar-collapse'));
                } else {
                    body.classList.toggle('sidebar-open');
                }
            }
        });

        // 2. Treeview Menu Logic (Event Delegation)
        document.addEventListener('click', function(e) {
            const link = e.target.closest('.nav-item > .nav-link');
            if (!link) return;

            const parent = link.parentElement;
            const treeview = parent.querySelector(':scope > .nav-treeview');

            if (treeview || link.getAttribute('href') === '#' || link.getAttribute('href') === 'javascript:void(0)') {
                e.preventDefault();
                e.stopPropagation();

                if (!treeview) return;

                // SPECIAL FIX: If sidebar is collapsed, expand it first when clicking a menu
                if (window.innerWidth >= 992 && body.classList.contains('sidebar-collapse')) {
                    body.classList.remove('sidebar-collapse');
                    localStorage.setItem('sidebar-collapsed', 'false');
                    // Give a tiny delay for the sidebar to start expanding before opening dropdown
                    setTimeout(() => {
                        parent.classList.add('menu-open');
                    }, 10);
                    return;
                }

                // Normal toggle: allow multiple menus to stay open
                parent.classList.toggle('menu-open');
            }
        });

        // 3. Mobile Overlay
        let overlay = document.querySelector('.sidebar-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'sidebar-overlay';
            document.body.appendChild(overlay);
        }
        
        overlay.addEventListener('click', function() {
            body.classList.remove('sidebar-open');
        });

        // 4. Persistence & Active State
        if (window.innerWidth >= 992 && localStorage.getItem('sidebar-collapsed') === 'true') {
            body.classList.add('sidebar-collapse');
        }

        // Set active menu and expand parents
        const currentPath = window.location.pathname;
        const allLinks = document.querySelectorAll('.app-sidebar .nav-link');
        
        allLinks.forEach(link => {
            const href = link.getAttribute('href');
            if (href && href !== '#' && currentPath.includes(href)) {
                link.classList.add('active');
                
                let p = link.closest('.nav-item');
                while (p) {
                    p.classList.add('menu-open');
                    p = p.parentElement.closest('.nav-item');
                }
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initLayout);
    } else {
        initLayout();
    }
})();
