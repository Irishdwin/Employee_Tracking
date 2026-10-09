/**
 * Profile view switching — no full page reload.
 * Supports Bootstrap `.dashboard-section` SPAs and Tailwind `.view-panel` layouts.
 */
(function (global) {
  'use strict';

  var DEFAULT_PROFILE_ID = 'section-profile';
  var DEFAULT_BOOTSTRAP_HOME = 'section-overview';
  var DEFAULT_VIEW_HOME = 'dashboard';

  function closeBootstrapDropdown(toggleId) {
    var toggle = document.getElementById(toggleId || 'userDropdownToggle');
    if (!toggle || typeof bootstrap === 'undefined' || !bootstrap.Dropdown) return;
    var dd = bootstrap.Dropdown.getInstance(toggle);
    if (dd) dd.hide();
  }

  function closeCustomHeaderMenu() {
    var profileMenu = document.getElementById('header-profile-menu');
    var profileBtn = document.getElementById('header-profile-btn');
    if (!profileMenu || !profileBtn) return;
    profileBtn.setAttribute('aria-expanded', 'false');
    profileMenu.classList.add('invisible', 'opacity-0');
    profileMenu.hidden = true;
  }

  function closeMenus() {
    closeBootstrapDropdown();
    closeCustomHeaderMenu();
    var accountWrap = document.getElementById('accountMenuWrap');
    if (accountWrap) accountWrap.classList.remove('is-open');
    var accountDropdown = document.getElementById('accountDropdown');
    if (accountDropdown) accountDropdown.classList.remove('is-open');
    var empProfileMenu = document.getElementById('profile-dropdown');
    var empProfileBtn = document.getElementById('profile-menu-btn');
    if (empProfileMenu && empProfileBtn) {
      empProfileMenu.classList.add('invisible', 'opacity-0', 'scale-95');
      empProfileMenu.hidden = true;
      empProfileBtn.setAttribute('aria-expanded', 'false');
    }
  }

  function loadProfileData() {
    if (global.UserProfileHydrate && typeof UserProfileHydrate.load === 'function') {
      UserProfileHydrate.load();
    }
  }

  function wireProfileTriggers(showFn, profileId, closeFn) {
    profileId = profileId || DEFAULT_PROFILE_ID;

    document.querySelectorAll('[data-show-section]').forEach(function (el) {
      if (el.dataset.profileBound === '1') return;
      el.dataset.profileBound = '1';
      el.addEventListener('click', function (e) {
        e.preventDefault();
        var target = el.getAttribute('data-show-section');
        if (target) showFn(target);
        if (closeFn) closeFn();
      });
    });

    var menuProfile = document.getElementById('menuProfile');
    if (menuProfile && menuProfile.dataset.profileBound !== '1') {
      menuProfile.dataset.profileBound = '1';
      menuProfile.addEventListener('click', function (e) {
        e.preventDefault();
        showFn(profileId);
        if (closeFn) closeFn();
      });
    }

    var headerProfileLink = document.getElementById('headerProfileLink');
    if (headerProfileLink && headerProfileLink.dataset.profileBound !== '1') {
      headerProfileLink.dataset.profileBound = '1';
      headerProfileLink.addEventListener('click', function (e) {
        e.preventDefault();
        showFn(profileId);
        if (closeFn) closeFn();
      });
    }

    var accountProfileLink = document.getElementById('accountProfileLink');
    if (accountProfileLink && accountProfileLink.dataset.profileBound !== '1') {
      accountProfileLink.dataset.profileBound = '1';
      accountProfileLink.addEventListener('click', function (e) {
        e.preventDefault();
        showFn(profileId);
        if (closeFn) closeFn();
      });
    }
  }

  /**
   * Bootstrap SPA: .dashboard-section inside #main-content
   */
  function initBootstrapSpa(options) {
    options = options || {};
    var profileId = options.profileSectionId || DEFAULT_PROFILE_ID;
    var defaultId = options.defaultSectionId || DEFAULT_BOOTSTRAP_HOME;
    var main = document.getElementById(options.mainContentId || 'main-content');
    if (!main || !document.getElementById(profileId)) return null;

    var sections = main.querySelectorAll(options.sectionSelector || '.dashboard-section');
    var sidebarLinks = document.querySelectorAll(options.sidebarSelector || '#main-sidebar .sidebar-link[data-target], #sidebar .sidebar-link[data-target]');
    var headerTitle = options.headerTitleSelector ? document.querySelector(options.headerTitleSelector) : null;
    var headerDefault = options.headerTitleDefault || '';
    var headerProfile = options.headerTitleProfile || 'My Account';
    var onShow = options.onShowSection || null;
    var previousSectionId = defaultId;

    function updateHeader(targetId) {
      if (!headerTitle) return;
      headerTitle.textContent = targetId === profileId ? headerProfile : headerDefault;
    }

    function showSection(targetId, opts) {
      var skipHash = opts && opts.skipHash;
      if (!targetId) return;

      if (targetId !== profileId) {
        previousSectionId = targetId;
      }

      sections.forEach(function (section) {
        var show = section.id === targetId;
        section.classList.toggle('d-none', !show);
        section.classList.toggle('active', show);
      });

      sidebarLinks.forEach(function (link) {
        var linkTarget = link.getAttribute('data-target');
        link.classList.toggle('active', linkTarget === targetId && targetId !== profileId);
      });

      updateHeader(targetId);

      if (targetId === profileId) {
        loadProfileData();
      }

      if (typeof onShow === 'function') {
        onShow(targetId, previousSectionId);
      }

      if (!skipHash && history.replaceState) {
        try {
          history.replaceState(null, '', '#' + targetId);
        } catch (e) {}
      }
    }

    sidebarLinks.forEach(function (link) {
      if (link.dataset.profileNavBound === '1') return;
      link.dataset.profileNavBound = '1';
      link.addEventListener('click', function (e) {
        e.preventDefault();
        var targetId = link.getAttribute('data-target');
        if (targetId) showSection(targetId);
      });
    });

    wireProfileTriggers(showSection, profileId, closeMenus);

    var hash = (global.location.hash || '').replace(/^#/, '');
    if (hash && document.getElementById(hash)) {
      showSection(hash, { skipHash: true });
    }

    global.addEventListener('hashchange', function () {
      var h = (global.location.hash || '').replace(/^#/, '');
      if (h && document.getElementById(h)) {
        showSection(h, { skipHash: true });
      }
    });

    global.profileShowSection = showSection;
    return showSection;
  }

  /**
   * Tailwind view panels: .view-panel[data-view]
   */
  function initViewPanel(options) {
    options = options || {};
    var profileViewId = options.profileViewId || 'profile';
    var defaultViewId = options.defaultViewId || DEFAULT_VIEW_HOME;
    var panels = document.querySelectorAll(options.panelSelector || '.view-panel');
    var profilePanel = document.querySelector('[data-view="' + profileViewId + '"]');
    if (!profilePanel || !panels.length) return null;

    var navItems = document.querySelectorAll(options.navSelector || '.nav-item');
    var headerTitle = options.headerTitleSelector ? document.querySelector(options.headerTitleSelector) : null;
    var headerDefault = options.headerTitleDefault || '';
    var headerProfile = options.headerTitleProfile || 'My Account';
    var activeNavClass = options.activeNavClass || '';
    var inactiveNavClass = options.inactiveNavClass || '';
    var onShow = options.onShowView || null;
    var previousViewId = defaultViewId;
    var returnViewId = defaultViewId;
    var returnFromViews = options.returnFromViews || [];
    var baseShowView = options.existingShowView || null;

    function clearNavHighlight() {
      navItems.forEach(function (btn) {
        if (inactiveNavClass) {
          btn.className = (btn.className.split(activeNavClass).join(' ').trim() + ' ' + inactiveNavClass).trim();
        }
      });
    }

    function showView(viewId, opts) {
      var skipHash = opts && opts.skipHash;
      if (!viewId) return;
      if (!Array.prototype.some.call(panels, function (panel) { return panel.getAttribute('data-view') === viewId; })) return;
      if (returnFromViews.indexOf(viewId) === -1) returnViewId = viewId;

      if (viewId !== profileViewId) {
        previousViewId = viewId;
      }

      if (typeof baseShowView === 'function' && viewId !== profileViewId) {
        baseShowView(viewId);
      } else {
        panels.forEach(function (p) {
          p.classList.toggle('hidden', p.getAttribute('data-view') !== viewId);
        });
        if (viewId !== profileViewId) {
          navItems.forEach(function (btn) {
            var id = btn.getAttribute('data-nav');
            if (activeNavClass && inactiveNavClass) {
              var base = btn.className.replace(activeNavClass, '').replace(inactiveNavClass, '').trim();
              btn.className = base + ' ' + (id === viewId ? activeNavClass : inactiveNavClass);
            }
          });
        } else {
          clearNavHighlight();
        }
      }

      if (viewId === profileViewId) {
        panels.forEach(function (p) {
          p.classList.toggle('hidden', p.getAttribute('data-view') !== profileViewId);
        });
        clearNavHighlight();
        loadProfileData();
      }

      if (headerTitle) {
        headerTitle.textContent = viewId === profileViewId ? headerProfile : headerDefault;
      }

      if (typeof onShow === 'function') {
        onShow(viewId, previousViewId);
      }

      if (!skipHash && history.replaceState) {
        try {
          history.replaceState(null, '', options.trackViewHash && viewId !== profileViewId ? '#view-' + viewId : '#section-profile');
        } catch (e) {}
      }
    }

    if (options.returnButtonSelector) {
      document.querySelectorAll(options.returnButtonSelector).forEach(function (button) {
        button.addEventListener('click', function () { showView(returnViewId); });
      });
    }

    wireProfileTriggers(function (target) {
      if (target === DEFAULT_PROFILE_ID || target === profileViewId) {
        showView(profileViewId);
      } else if (target === 'section-overview' || target === defaultViewId) {
        showView(defaultViewId);
      }
    }, DEFAULT_PROFILE_ID, closeMenus);

    var hash = (global.location.hash || '').replace(/^#/, '');
    if (hash === DEFAULT_PROFILE_ID || hash === profileViewId) {
      showView(profileViewId, { skipHash: true });
    } else if (options.trackViewHash && hash.indexOf('view-') === 0) {
      showView(hash.slice(5), { skipHash: true });
    }

    global.addEventListener('hashchange', function () {
      var h = (global.location.hash || '').replace(/^#/, '');
      if (h === DEFAULT_PROFILE_ID || h === profileViewId) {
        showView(profileViewId, { skipHash: true });
      } else if (h === defaultViewId) {
        showView(defaultViewId, { skipHash: true });
      } else if (options.trackViewHash && h.indexOf('view-') === 0) {
        showView(h.slice(5), { skipHash: true });
      }
    });

    global.profileShowView = showView;
    return showView;
  }

  /**
   * Simple two-panel toggle (Employee dashboard).
   */
  function initSimpleToggle(options) {
    options = options || {};
    var profileId = options.profileSectionId || DEFAULT_PROFILE_ID;
    var mainId = options.mainSectionId || 'section-dashboard-main';
    if (!document.getElementById(profileId) || !document.getElementById(mainId)) return null;

    var mainEl = document.getElementById(mainId);
    var profileEl = document.getElementById(profileId);

    function showSection(targetId) {
      var showProfile = targetId === profileId;
      if (targetId === mainId) showProfile = false;
      mainEl.classList.toggle('hidden', showProfile);
      mainEl.classList.toggle('d-none', showProfile);
      profileEl.classList.toggle('hidden', !showProfile);
      profileEl.classList.toggle('d-none', !showProfile);
      if (showProfile) loadProfileData();
      if (history.replaceState) {
        try {
          history.replaceState(null, '', showProfile ? '#section-profile' : '#dashboard');
        } catch (e) {}
      }
    }

    var hash = (global.location.hash || '').replace(/^#/, '');
    if (hash === profileId) {
      showSection(profileId);
    }

    wireProfileTriggers(showSection, profileId, closeMenus);

    global.profileShowSection = showSection;
    return showSection;
  }

  function autoInit() {
    if (document.getElementById('section-dashboard-main') && document.getElementById('section-profile')) {
      var mainContent = document.getElementById('main-content');
      var usesBootstrapSpa = mainContent && mainContent.querySelector('.dashboard-section');
      if (!usesBootstrapSpa) {
        initSimpleToggle();
      }
    }
  }

  global.ProfileViewSwitch = {
    initBootstrapSpa: initBootstrapSpa,
    initViewPanel: initViewPanel,
    initSimpleToggle: initSimpleToggle,
    wireProfileTriggers: wireProfileTriggers,
    loadProfileData: loadProfileData,
    closeMenus: closeMenus,
    autoInit: autoInit
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', autoInit);
  } else {
    autoInit();
  }
})(window);
