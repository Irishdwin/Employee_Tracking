/**
 * Collapsible dashboard sidebar — desktop compact + mobile drawer with localStorage persistence.
 */
(function () {
  'use strict';

  var STORAGE_KEY = 'sidebar-collapsed';
  var MOBILE_MAX = 767;

  function isMobileViewport() {
    return window.innerWidth <= MOBILE_MAX;
  }

  function getWrapper() {
    return document.getElementById('dashboard-wrapper');
  }

  function getOverlay() {
    return document.getElementById('sidebar-overlay');
  }

  function setMobileOpen(open) {
    var wrapper = getWrapper();
    var overlay = getOverlay();
    if (!wrapper) return;

    wrapper.classList.toggle('mobile-sidebar-open', open);
    document.body.classList.toggle('sidebar-drawer-open', open);

    var toggleBtn = document.getElementById('hamburger-toggle');
    if (toggleBtn) {
      toggleBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    if (overlay) {
      overlay.classList.toggle('is-visible', open);
      overlay.setAttribute('aria-hidden', open ? 'false' : 'true');
    }
  }

  function closeMobileSidebar() {
    setMobileOpen(false);
  }

  function applyDesktopCollapsed(collapsed) {
    var wrapper = getWrapper();
    if (!wrapper) return;
    wrapper.classList.toggle('sidebar-collapsed', collapsed);
    var toggleBtn = document.getElementById('hamburger-toggle');
    if (toggleBtn && !isMobileViewport()) {
      toggleBtn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    }
  }

  function readStoredCollapsed() {
    try {
      return localStorage.getItem(STORAGE_KEY) === 'true';
    } catch (e) {
      return false;
    }
  }

  function storeCollapsed(collapsed) {
    try {
      localStorage.setItem(STORAGE_KEY, collapsed ? 'true' : 'false');
    } catch (e) {
      /* ignore quota errors */
    }
  }

  function applySidebarState() {
    var wrapper = getWrapper();
    if (!wrapper) return;

    if (isMobileViewport()) {
      applyDesktopCollapsed(false);
      closeMobileSidebar();
      return;
    }

    closeMobileSidebar();
    applyDesktopCollapsed(readStoredCollapsed());
  }

  function toggleSidebar() {
    var wrapper = getWrapper();
    if (!wrapper) return;

    if (isMobileViewport()) {
      setMobileOpen(!wrapper.classList.contains('mobile-sidebar-open'));
      return;
    }

    var collapsed = !wrapper.classList.contains('sidebar-collapsed');
    applyDesktopCollapsed(collapsed);
    storeCollapsed(collapsed);
  }

  function bindNavCloseOnMobile() {
    var sidebar = document.getElementById('main-sidebar');
    if (!sidebar) return;

    sidebar.addEventListener('click', function (e) {
      if (!isMobileViewport()) return;
      var target = e.target.closest('a, button[data-nav], button[data-target], .sidebar-link, .nav-item');
      if (target) closeMobileSidebar();
    });
  }

  function initCollapsibleSidebar() {
    var toggleBtn = document.getElementById('hamburger-toggle');
    var overlay = getOverlay();

    applySidebarState();

    if (toggleBtn) {
      toggleBtn.addEventListener('click', function (e) {
        e.preventDefault();
        toggleSidebar();
      });
    }

    if (overlay) {
      overlay.addEventListener('click', closeMobileSidebar);
    }

    var resizeTimer;
    window.addEventListener('resize', function () {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(applySidebarState, 120);
    });

    bindNavCloseOnMobile();
  }

  window.toggleSidebar = toggleSidebar;
  window.closeMobileSidebar = closeMobileSidebar;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCollapsibleSidebar);
  } else {
    initCollapsibleSidebar();
  }
})();
