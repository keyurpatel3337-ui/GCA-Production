/**
 * Fixed Layout JavaScript
 * Handles responsive behavior and smooth interactions for the fixed layout
 *
 * Features:
 * - Mobile sidebar toggle (open/close)
 * - Smooth scrolling
 * - Keyboard navigation
 * - Window resize handling
 * - Content height calculation
 */

(function () {
  'use strict';

  // Configuration
  const CONFIG = {
    MOBILE_BREAKPOINT: 991, // Standard mobile/tablet breakpoint
    NAVBAR_HEIGHT: 57,
    FOOTER_HEIGHT: 50,
    SIDEBAR_WIDTH: 300,
    TRANSITION_DURATION: 300,
  };

  // State management
  const state = {
    isMobile: false,
    sidebarOpen: false,
  };

  /**
   * Initialize the fixed layout
   */
  function init() {
    // Check if we're on mobile
    checkMobileView();

    // Set up event listeners
    setupEventListeners();

    // Calculate initial heights
    calculateContentHeight();

    // Handle sidebar state on page load
    handleInitialSidebarState();

    // Smooth scroll setup
    setupSmoothScrolling();

    // Keyboard navigation
    setupKeyboardNavigation();

    // Check localStorage for sidebar state
    if (localStorage.getItem('sidebar-collapsed') === 'true') {
      document.body.classList.add('sidebar-collapsed');
    }

    console.log('Fixed layout initialized');
  }

  /**
   * Check if current view is mobile
   */
  function checkMobileView() {
    state.isMobile = window.innerWidth <= CONFIG.MOBILE_BREAKPOINT;
    document.body.classList.toggle('is-mobile', state.isMobile);

    // Force sidebar to be closed whenever we enter mobile view
    if (state.isMobile) {
      document.body.classList.remove('sidebar-open');
      state.sidebarOpen = false;
    }
  }

  /**
   * Setup all event listeners
   */
  function setupEventListeners() {
    // Window resize
    let resizeTimeout;
    window.addEventListener('resize', function () {
      clearTimeout(resizeTimeout);
      resizeTimeout = setTimeout(function () {
        checkMobileView();
        calculateContentHeight();
        handleResponsiveLayout();
      }, 100);
    });

    // Unified Sidebar Toggle Handler (Desktop & Mobile)
    // We use capture: true to ensure we catch these clicks before AdminLTE or other plugins
    document.addEventListener(
      'click',
      function (e) {
        const desktopToggle = e.target.closest(
          '[data-lte-toggle="sidebar-full"], [data-lte-toggle="sidebar-desktop"]'
        );
        const mobileToggle = e.target.closest(
          '[data-lte-toggle="sidebar-open"], [data-lte-toggle="sidebar-mobile"]'
        );
        const miniToggle = e.target.closest(
          '[data-lte-toggle="sidebar-mini"]'
        );
        const sidebarLink = e.target.closest('.app-sidebar .nav-link');

        if (desktopToggle) {
          e.preventDefault();
          e.stopPropagation();
          console.log('Desktop sidebar toggle clicked');
          toggleDesktopSidebar();
        } else if (miniToggle) {
          e.preventDefault();
          e.stopPropagation();
          console.log('Mini sidebar toggle clicked (Desktop)');
          toggleDesktopSidebar();
        } else if (mobileToggle) {
          e.preventDefault();
          e.stopPropagation();
          console.log('Mobile sidebar toggle clicked');
          handleSidebarToggle(e);
        } else if (
          sidebarLink &&
          document.body.classList.contains('sidebar-collapsed')
        ) {
          // Check if this is a dropdown parent
          const parentItem = sidebarLink.closest('.nav-item');
          const hasSubmenu =
            parentItem && parentItem.querySelector('.nav-treeview');
          const hasArrow = sidebarLink.querySelector('.nav-arrow');
          const isDropdown =
            hasSubmenu || hasArrow || sidebarLink.getAttribute('href') === '#';

          if (isDropdown) {
            console.log(
              'Dropdown item clicked in capture phase, expanding sidebar'
            );
            toggleDesktopSidebar();
            // We DON'T stop propagation here because we want the dropdown to actually open
          }
        }
      },
      { capture: true }
    );

    // Close sidebar when clicking overlay on mobile
    document.addEventListener('click', handleOverlayClick);

    // Scroll event for navbar shadow
    const appMain = document.querySelector('.app-main');
    if (appMain) {
      appMain.addEventListener('scroll', handleScroll);
    }

    // Handle escape key to close sidebar on mobile
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && state.isMobile && state.sidebarOpen) {
        closeSidebar();
      }
    });
  }

  /**
   * Handle sidebar toggle (mobile only)
   */
  function handleSidebarToggle(e) {
    e.preventDefault();
    e.stopPropagation();

    if (state.isMobile) {
      if (state.sidebarOpen) {
        closeSidebar();
      } else {
        openSidebar();
      }
    }
  }

  /**
   * Open sidebar (mobile)
   */
  function openSidebar() {
    document.body.classList.add('sidebar-open');
    state.sidebarOpen = true;

    // Prevent body scroll
    document.body.style.overflow = 'hidden';
  }

  /**
   * Close sidebar (mobile)
   */
  function closeSidebar() {
    document.body.classList.remove('sidebar-open');
    state.sidebarOpen = false;

    // Restore body scroll
    document.body.style.overflow = '';
  }

  /**
   * Toggle desktop sidebar
   */
  function toggleDesktopSidebar() {
    const isCollapsed = document.body.classList.toggle('sidebar-collapsed');
    localStorage.setItem('sidebar-collapsed', isCollapsed);
    console.log('Sidebar collapsed state:', isCollapsed);
    console.log('Body classes:', document.body.className);

    // Trigger a resize event to allow elements to adjust if needed
    window.dispatchEvent(new Event('resize'));
  }

  /**
   * Handle overlay click to close sidebar on mobile
   */
  function handleOverlayClick(e) {
    if (state.isMobile && state.sidebarOpen) {
      const sidebar = document.querySelector('.app-sidebar');
      const toggle = document.querySelector('[data-lte-toggle="sidebar-open"]');

      // Check if click is outside sidebar and toggle button
      if (
        sidebar &&
        !sidebar.contains(e.target) &&
        toggle &&
        !toggle.contains(e.target)
      ) {
        closeSidebar();
      }
    }
  }

  /**
   * Handle initial sidebar state
   */
  function handleInitialSidebarState() {
    // On mobile, ensure sidebar is closed initially
    if (state.isMobile) {
      closeSidebar();
    }
  }

  /**
   * Calculate content area height
   */
  function calculateContentHeight() {
    const appMain = document.querySelector('.app-main');

    if (appMain) {
      const viewportHeight = window.innerHeight;
      const contentHeight =
        viewportHeight - CONFIG.NAVBAR_HEIGHT - CONFIG.FOOTER_HEIGHT;

      // Set CSS custom property for dynamic height
      document.documentElement.style.setProperty(
        '--content-height',
        contentHeight + 'px'
      );
    }
  }

  /**
   * Handle responsive layout changes
   */
  function handleResponsiveLayout() {
    if (state.isMobile) {
      // Close sidebar on mobile
      closeSidebar();
    } else {
      // Ensure body overflow is reset on desktop
      document.body.style.overflow = '';
    }
  }

  /**
   * Handle scroll for navbar shadow effect
   */
  function handleScroll() {
    const appHeader = document.querySelector('.app-header');
    const appMain = document.querySelector('.app-main');

    if (appHeader && appMain) {
      if (appMain.scrollTop > 10) {
        appHeader.classList.add('scrolled');
      } else {
        appHeader.classList.remove('scrolled');
      }
    }
  }

  /**
   * Setup smooth scrolling
   */
  function setupSmoothScrolling() {
    // Smooth scroll for anchor links within content
    document.addEventListener('click', function (e) {
      const target = e.target.closest('a[href^="#"]');

      if (target) {
        const href = target.getAttribute('href');
        if (href && href !== '#') {
          const element = document.querySelector(href);
          const appMain = document.querySelector('.app-main');

          if (element && appMain) {
            e.preventDefault();

            // Get element position relative to app-main
            const elementTop = element.offsetTop;

            // Smooth scroll
            appMain.scrollTo({
              top: elementTop - 20, // 20px offset
              behavior: 'smooth',
            });
          }
        }
      }
    });
  }

  /**
   * Setup keyboard navigation
   */
  function setupKeyboardNavigation() {
    // Keyboard shortcuts
    document.addEventListener('keydown', function (e) {
      // Ctrl/Cmd + Home to scroll to top
      if ((e.ctrlKey || e.metaKey) && e.key === 'Home') {
        e.preventDefault();
        const appMain = document.querySelector('.app-main');
        if (appMain) {
          appMain.scrollTo({ top: 0, behavior: 'smooth' });
        }
      }

      // Ctrl/Cmd + End to scroll to bottom
      if ((e.ctrlKey || e.metaKey) && e.key === 'End') {
        e.preventDefault();
        const appMain = document.querySelector('.app-main');
        if (appMain) {
          appMain.scrollTo({
            top: appMain.scrollHeight,
            behavior: 'smooth',
          });
        }
      }
    });
  }

  /**
   * Public API
   */
  window.FixedLayout = {
    init: init,
    openSidebar: openSidebar,
    closeSidebar: closeSidebar,
    toggleSidebar: handleSidebarToggle,
    getState: function () {
      return { ...state };
    },
  };

  // Auto-initialize when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
