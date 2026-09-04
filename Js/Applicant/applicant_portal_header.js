/**
 * Shared applicant portal topbar: account dropdown, header stats, auth visibility.
 */
(function (global) {
  'use strict';

  var STATUS_API = '../../Api/Applicant/get_profile_status.php';
  var HEADER_STATS_API = '../../Api/Applicant/applicant_header_stats.php';

  function fetchJson(url, opts) {
    var o = opts ? Object.assign({}, opts) : {};
    o.credentials = 'include';
    o.headers = Object.assign({ Accept: 'application/json' }, o.headers || {});
    return fetch(url, o).then(function (r) {
      return r.text().then(function (t) {
        var data = null;
        try {
          data = t ? JSON.parse(t) : null;
        } catch (e) {
          data = null;
        }
        return { ok: r.ok, status: r.status, data: data };
      });
    });
  }

  function initials(name) {
    var s = String(name || '').trim();
    if (!s) return 'U';
    var parts = s.split(/\s+/);
    return parts[0].charAt(0).toUpperCase() + (parts[1] ? parts[1].charAt(0).toUpperCase() : '');
  }

  function init(options) {
    options = options || {};
    var requireCompleteProfile = options.requireCompleteProfile === true;
    var onReady = typeof options.onReady === 'function' ? options.onReady : null;
    var loginRedirect = options.loginRedirect || '../../index.html';
    var incompleteRedirect = options.incompleteRedirect || 'applicant_profile_master.html#profile';

    var accountWrap = document.getElementById('accountMenuWrap');
    var accountTrigger = document.getElementById('accountMenuTrigger');
    var accountDropdown = document.getElementById('accountDropdown');
    var btnOpen = document.getElementById('btnOpenSignIn');
    var btnOut = document.getElementById('btnSignOut');

    var applicationsCountEl = document.getElementById('accountStatApplications');
    var savedCountEl = document.getElementById('accountStatSaved');
    var roleEl = document.getElementById('accountDropdownRole');
    var locEl = document.getElementById('accountDropdownLoc');
    var nameEl = document.getElementById('accountDropdownName');
    var avatarEl = document.getElementById('accountAvatarInitial');

    function setSavedCount() {
      try {
        var raw = localStorage.getItem('onazul_saved_job_ids');
        var arr = raw ? JSON.parse(raw) : [];
        if (savedCountEl) savedCountEl.textContent = String(Array.isArray(arr) ? arr.length : 0);
      } catch (e) {
        if (savedCountEl) savedCountEl.textContent = '0';
      }
    }

    function closeMenu() {
      if (!accountWrap || !accountDropdown) return;
      accountWrap.classList.remove('is-open');
      accountDropdown.classList.remove('is-open');
      if (accountTrigger) accountTrigger.setAttribute('aria-expanded', 'false');
    }

    function loadHeaderStats() {
      return fetchJson(HEADER_STATS_API, { method: 'GET' }).then(function (res) {
        var d = res.data;
        if (!d || !d.success || !d.logged_in) return;
        if (nameEl) nameEl.textContent = d.full_name || 'Applicant';
        if (avatarEl) avatarEl.textContent = initials(d.full_name || 'Applicant');
        if (roleEl) roleEl.textContent = d.saved_role ? d.saved_role : 'Add a role in your profile';
        if (locEl) locEl.textContent = d.home_location ? d.home_location : 'Add a home location';
        if (applicationsCountEl) {
          applicationsCountEl.textContent = String(d.applications_count != null ? d.applications_count : 0);
        }
      }).catch(function () {});
    }

    if (accountTrigger && accountDropdown) {
      accountTrigger.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var open = accountDropdown.classList.contains('is-open');
        if (open) closeMenu();
        else {
          accountWrap.classList.add('is-open');
          accountDropdown.classList.add('is-open');
          accountTrigger.setAttribute('aria-expanded', 'true');
          loadHeaderStats();
          setSavedCount();
        }
      });
    }

    document.addEventListener('click', function (e) {
      if (!accountWrap || !accountDropdown) return;
      if (accountWrap.contains(e.target)) return;
      closeMenu();
    });

    window.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeMenu();
    });

    if (btnOut) {
      btnOut.addEventListener('click', function () {
        closeMenu();
        fetch('../../Api/Login/logout.php', {
          method: 'GET',
          credentials: 'include',
          headers: { Accept: 'application/json' }
        })
          .catch(function () {})
          .then(function () {
            window.location.href = '/Employee_Tracking/index.html';
          });
      });
    }

    if (btnOpen) {
      btnOpen.addEventListener('click', function () {
        window.location.href = '../../Html/Applicant/applicant_landing.html';
      });
    }

    return fetchJson(STATUS_API, { method: 'GET' }).then(function (res) {
      var st = res.data;
      if (!res.ok || !st || !st.success || st.logged_in === false) {
        if (accountWrap) accountWrap.classList.add('d-none');
        if (btnOpen) btnOpen.classList.remove('d-none');
        if (options.redirectIfLoggedOut !== false) {
          window.location.replace(loginRedirect);
        }
        return { ok: false };
      }

      if (requireCompleteProfile && (st.status === 'incomplete' || !st.profile_completed)) {
        window.location.replace(incompleteRedirect);
        return { ok: false };
      }

      if (accountWrap) accountWrap.classList.remove('d-none');
      if (btnOpen) btnOpen.classList.add('d-none');
      loadHeaderStats();
      setSavedCount();

      if (onReady) onReady({ st: st });
      return { ok: true, st: st };
    });
  }

  global.ApplicantPortalHeader = { init: init };
})(typeof window !== 'undefined' ? window : this);
