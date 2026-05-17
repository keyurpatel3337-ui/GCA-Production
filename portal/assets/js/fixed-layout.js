/**
 * Fixed Layout JavaScript (Refactored)
 * Handles only non-conflicting UI enhancements like scroll shadows and height calculation.
 * Sidebar/Menu logic is handled exclusively by gca-layout.js
 */

(function () {
  'use strict';

  const CONFIG = {
    MOBILE_BREAKPOINT: 991,
    NAVBAR_HEIGHT: 57,
    FOOTER_HEIGHT: 50,
  };

  const state = {
    isMobile: false,
  };

  function init() {
    checkMobileView();
    calculateContentHeight();
    setupEventListeners();
    setupSmoothScrolling();
    setupKeyboardNavigation();
    console.log('Fixed layout utilities initialized');
  }

  function checkMobileView() {
    state.isMobile = window.innerWidth <= CONFIG.MOBILE_BREAKPOINT;
    document.body.classList.toggle('is-mobile', state.isMobile);
  }

  function setupEventListeners() {
    let resizeTimeout;
    window.addEventListener('resize', function () {
      clearTimeout(resizeTimeout);
      resizeTimeout = setTimeout(function () {
        checkMobileView();
        calculateContentHeight();
      }, 100);
    });

    const appMain = document.querySelector('.app-main');
    if (appMain) {
      appMain.addEventListener('scroll', handleScroll);
    }
  }

  function calculateContentHeight() {
    const appMain = document.querySelector('.app-main');
    if (appMain) {
      const viewportHeight = window.innerHeight;
      const contentHeight = viewportHeight - CONFIG.NAVBAR_HEIGHT - CONFIG.FOOTER_HEIGHT;
      document.documentElement.style.setProperty('--content-height', contentHeight + 'px');
    }
  }

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

  function setupSmoothScrolling() {
    document.addEventListener('click', function (e) {
      const target = e.target.closest('a[href^="#"]');
      if (target) {
        const href = target.getAttribute('href');
        if (href && href !== '#') {
          const element = document.querySelector(href);
          const appMain = document.querySelector('.app-main');
          if (element && appMain) {
            e.preventDefault();
            appMain.scrollTo({
              top: element.offsetTop - 20,
              behavior: 'smooth',
            });
          }
        }
      }
    });
  }

  function setupKeyboardNavigation() {
    document.addEventListener('keydown', function (e) {
      if ((e.ctrlKey || e.metaKey) && e.key === 'Home') {
        const appMain = document.querySelector('.app-main');
        if (appMain) appMain.scrollTo({ top: 0, behavior: 'smooth' });
      }
      if ((e.ctrlKey || e.metaKey) && e.key === 'End') {
        const appMain = document.querySelector('.app-main');
        if (appMain) appMain.scrollTo({ top: appMain.scrollHeight, behavior: 'smooth' });
      }
    });
  }

  window.FixedLayout = {
    init: init,
    getState: function () { return { ...state }; },
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
