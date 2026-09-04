/**
 * Applicant hire celebration — detects Employee role elevation and shows onboarding modal.
 * Include on applicant portal pages with window.ROLE_GUARD_DEFER_ELEVATION = true.
 */
(function (global) {
  'use strict';

  var API = '../../Api/Applicant/check_hire_elevation.php';
  var POLL_MS = 8000;
  var STORAGE_KEY = 'onazul_hire_celebration_ack';
  var modalInstance = null;
  var pollTimer = null;
  var modalShown = false;

  function employeeDashboardUrl() {
    return global.location.origin + '/Employee_Tracking/Html/Employee/employee_dashboard.html';
  }

  function ensureModalDom() {
    if (document.getElementById('hiredCelebrationModal')) {
      return;
    }

    var wrap = document.createElement('div');
    wrap.innerHTML =
      '<div class="modal fade" id="hiredCelebrationModal" tabindex="-1"' +
      ' aria-labelledby="hiredCelebrationTitle" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">' +
      '<div class="modal-dialog modal-dialog-centered">' +
      '<div class="modal-content border-0 shadow-lg" style="border-radius:18px;overflow:hidden;">' +
      '<div class="modal-header border-0 text-white" style="background:linear-gradient(135deg,#0056b3 0%,#003366 100%);">' +
      '<h5 class="modal-title fw-bold" id="hiredCelebrationTitle">🎉 Congratulations! You are Hired!</h5>' +
      '</div>' +
      '<div class="modal-body p-4">' +
      '<p class="mb-0" style="line-height:1.65;color:#334155;font-size:15px;">' +
      'Welcome to the Onazul Manpower Services team! Your account has been officially upgraded to an Employee Profile. ' +
      'You can continue using your current email and password to access the system. ' +
      'Click below to enter your new Employee Dashboard.' +
      '</p>' +
      '</div>' +
      '<div class="modal-footer border-0 pt-0 px-4 pb-4">' +
      '<button type="button" class="btn btn-success fw-bold w-100" id="btnGoEmployeeDashboard">' +
      '<i class="bi bi-box-arrow-in-right me-2"></i>Go to Employee Dashboard' +
      '</button>' +
      '</div>' +
      '</div></div></div>';

    document.body.appendChild(wrap.firstElementChild);

    var btn = document.getElementById('btnGoEmployeeDashboard');
    if (btn) {
      btn.addEventListener('click', function () {
        try {
          sessionStorage.setItem(STORAGE_KEY, '1');
          localStorage.removeItem('onazul_saved_job_ids');
        } catch (e) {}
        global.location.href = employeeDashboardUrl();
      });
    }
  }

  function showCelebrationModal() {
    if (modalShown) {
      return;
    }

    try {
      if (sessionStorage.getItem(STORAGE_KEY) === '1') {
        return;
      }
    } catch (e) {}

    if (typeof bootstrap === 'undefined') {
      return;
    }

    ensureModalDom();
    var el = document.getElementById('hiredCelebrationModal');
    if (!el) {
      return;
    }

    modalShown = true;
    global.__hireCelebrationActive = true;
    modalInstance = bootstrap.Modal.getOrCreateInstance(el, {
      backdrop: 'static',
      keyboard: false
    });
    modalInstance.show();
  }

  function fetchElevation() {
    return fetch(API, { credentials: 'include', headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json(); })
      .catch(function () { return null; });
  }

  function handleElevation(data) {
    if (!data || !data.success) {
      return;
    }

    var roleLower = String(data.role || '').trim().toLowerCase();
    var shouldCelebrate = data.show_celebration === true
      || data.role_elevated === true
      || data.elevated === true
      || roleLower === 'employee'
      || data.has_hired_application === true;

    if (shouldCelebrate) {
      showCelebrationModal();
    }
  }

  function poll() {
    fetchElevation().then(handleElevation);
  }

  function init() {
    ensureModalDom();
    poll();
    if (pollTimer) {
      global.clearInterval(pollTimer);
    }
    pollTimer = global.setInterval(poll, POLL_MS);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  global.ApplicantHireCelebration = {
    poll: poll,
    show: showCelebrationModal
  };
})(typeof window !== 'undefined' ? window : this);
