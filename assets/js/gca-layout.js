/**
 * GCA Custom Layout JS
 * Handles Sidebar Toggle and Treeview functionality
 */

document.addEventListener('DOMContentLoaded', function() {
    // Sidebar Toggle
    const toggles = document.querySelectorAll('[data-lte-toggle="sidebar"], [data-lte-toggle="sidebar-mobile"], [data-lte-toggle="sidebar-desktop"]');
    const body = document.body;

    toggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            if (window.innerWidth >= 992) {
                body.classList.toggle('sidebar-collapse');
                localStorage.setItem('sidebar-collapsed', body.classList.contains('sidebar-collapse'));
            } else {
                body.classList.toggle('sidebar-open');
            }
        });
    });

    // Restore sidebar state for desktop
    if (window.innerWidth >= 992) {
        const savedState = localStorage.getItem('sidebar-collapsed');
        if (savedState === 'true') {
            body.classList.add('sidebar-collapse');
        }
    }


    // Treeview / Accordion Menu
    const navLinks = document.querySelectorAll('.nav-sidebar .nav-item > .nav-link');
    
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            const parent = this.parentElement;
            const treeview = parent.querySelector('.nav-treeview');

            if (treeview) {
                e.preventDefault();
                
                // Toggle current menu
                const isOpen = parent.classList.contains('menu-open');
                
                // Close other open menus in the same level
                const siblings = parent.parentElement.querySelectorAll(':scope > .nav-item.menu-open');
                siblings.forEach(sibling => {
                    if (sibling !== parent) {
                        sibling.classList.remove('menu-open');
                        const siblingTree = sibling.querySelector('.nav-treeview');
                        if (siblingTree) siblingTree.style.display = 'none';
                    }
                });

                if (isOpen) {
                    parent.classList.remove('menu-open');
                    treeview.style.display = 'none';
                } else {
                    parent.classList.add('menu-open');
                    treeview.style.display = 'block';
                }
            }
        });
    });

    // Close sidebar on mobile when clicking overlay
    const overlay = document.createElement('div');
    overlay.className = 'sidebar-overlay';
    document.body.appendChild(overlay);

    overlay.addEventListener('click', function() {
        body.classList.remove('sidebar-open');
    });

    // Handle active state from URL
    const currentPath = window.location.pathname;
    const activeLink = document.querySelector(`.nav-sidebar a[href*="${currentPath}"]`);
    if (activeLink) {
        activeLink.classList.add('active');
        let parent = activeLink.closest('.nav-item');
        while (parent) {
            if (parent.classList.contains('nav-item')) {
                const treeview = parent.querySelector('.nav-treeview');
                if (treeview) {
                    parent.classList.add('menu-open');
                    treeview.style.display = 'block';
                }
            }
            parent = parent.parentElement.closest('.nav-item');
        }
    }
});
