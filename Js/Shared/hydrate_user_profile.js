/**
 * Loads logged-in user profile into dashboard UI (headers, avatars, profile cards).
 * Include after role_guard.js on protected pages.
 */
(function (global) {
  'use strict';

  var DEFAULT_API = '../../Api/Common/get_current_user.php';

  function absUrl(relativePath) {
    try {
      return new URL(relativePath, window.location.href).href;
    } catch (e) {
      return relativePath;
    }
  }

  function initials(name) {
    if (!name) return 'U';
    var parts = String(name).trim().split(/\s+/).filter(Boolean);
    var a = parts[0] ? parts[0].charAt(0) : '';
    var b = parts.length > 1 ? parts[parts.length - 1].charAt(0) : '';
    return (a + b).toUpperCase() || 'U';
  }

  function setText(el, value) {
    if (!el || value == null) return;
    el.textContent = String(value);
  }

  function applyKnownIds(user) {
    var name = user.full_name || 'User';
    var ini = user.initials || initials(name);
    var subtitle = user.subtitle || user.role_label || user.role || '';

    var map = {
      userName: name,
      adminName: name,
      userNameTop: name,
      ccUserName: name,
      ccUserRole: user.role_label || user.role || '',
      sidebarUserName: name,
      sidebarUserRole: user.role_label || user.role || '',
      tlSidebarUserName: name,
      tlHeaderUserName: name,
      acHeaderUserName: name,
      profileNameRole: subtitle.indexOf('—') !== -1 || subtitle.indexOf('|') !== -1
        ? name + ' | ' + subtitle
        : (subtitle && subtitle !== name ? name + ' — ' + subtitle : name)
    };

    Object.keys(map).forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.textContent = map[id];
    });

    ['userAvatarCircle', 'ccUserAvatar', 'tlHeaderInitial', 'acHeaderInitial', 'profile-initial', 'sidebarUserInitialCompact'].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.textContent = ini;
    });

    var profileNameRole = document.getElementById('profile-name-role');
    if (profileNameRole && user.role === 'Employee' && user.subtitle) {
      profileNameRole.innerHTML = name + ' <span class="font-normal text-slate-400">|</span> ' + user.subtitle.replace(' — ', ' - ');
    }
  }

  function applyDataFields(user) {
    document.querySelectorAll('[data-user-field]').forEach(function (el) {
      var key = el.getAttribute('data-user-field');
      if (!key) return;
      var val = user[key];
      if (val == null || val === '') {
        if (el.getAttribute('data-user-empty') === 'hide') {
          el.classList.add('d-none', 'hidden');
        }
        return;
      }
      if (Array.isArray(val)) {
        el.textContent = val.map(function (x) { return x.name || x; }).join(', ');
        return;
      }
      el.textContent = String(val);
    });

    document.querySelectorAll('[data-user-initials]').forEach(function (el) {
      el.textContent = user.initials || initials(user.full_name);
    });
  }

  function applyProfileCard(user) {
    var card = document.getElementById('userProfileCard');
    if (!card) return;

    var set = function (id, val) {
      var el = document.getElementById(id);
      if (el) el.textContent = val || '—';
    };

    set('upcFullName', user.full_name);
    set('upcEmail', user.email);
    set('upcRole', user.role_label || user.role);
    set('upcMobile', user.mobile);
    set('upcAddress', user.address || user.home_location);
    set('upcBirthDate', user.birth_date);
    set('upcPosition', user.position_title);
    set('upcCompany', user.company_name || user.client_name);
    set('upcEmployeeNumber', user.employee_number);

    var companiesEl = document.getElementById('upcAssignedCompanies');
    if (companiesEl && Array.isArray(user.assigned_companies)) {
      if (!user.assigned_companies.length) {
        companiesEl.textContent = '—';
      } else {
        companiesEl.innerHTML = user.assigned_companies.map(function (c) {
          return '<li class="text-sm text-slate-700">' + (c.name || '') + (c.location ? ' <span class="text-slate-500">(' + c.location + ')</span>' : '') + '</li>';
        }).join('');
      }
    }
  }

  function applyUser(user) {
    if (!user) return;
    applyKnownIds(user);
    applyDataFields(user);
    applyProfileCard(user);
    global.currentUserProfile = user;
    try {
      document.dispatchEvent(new CustomEvent('user-profile-loaded', { detail: user }));
    } catch (e) {}
  }

  function load(apiUrl) {
    var url = absUrl(apiUrl || global.CURRENT_USER_API || DEFAULT_API);
    return fetch(url, { credentials: 'include' })
      .then(function (r) { return r.json().catch(function () { return null; }); })
      .then(function (d) {
        if (!d || !d.success || !d.user) return null;
        applyUser(d.user);
        return d.user;
      })
      .catch(function () { return null; });
  }

  function init() {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', function () { load(); });
    } else {
      load();
    }
  }

  global.UserProfileHydrate = {
    load: load,
    apply: applyUser,
    init: init
  };

  init();
})(window);
