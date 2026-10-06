(function (global) {
  'use strict';

  var PLACEMENTS_API = '/Employee_Tracking/Api/Applicant/get_job_placements.php';
  var SUBMIT_API = '/Employee_Tracking/Api/Applicant/submit_job_application.php';

  var pendingJobId = null;
  var placementModalInstance = null;

  function escapeHtml(text) {
    if (text == null) return '';
    var d = document.createElement('div');
    d.textContent = String(text);
    return d.innerHTML;
  }

  function fetchJson(url, options) {
    return fetch(url, options || {}).then(function (r) {
      return r.text().then(function (text) {
        var data = null;
        try {
          data = text ? JSON.parse(text) : null;
        } catch (e) {
          data = null;
        }
        return { ok: r.ok, status: r.status, data: data };
      });
    });
  }

  function notifySuccess(message) {
    // Tells the applicant what happens next (their application now shows up
    // in My Applications) and lets the button itself take them there,
    // instead of just dismissing the modal.
    var baseMessage = message || 'Application submitted successfully!';
    var nextStepMessage = baseMessage + ' You can track its status anytime in My Applications.';

    if (typeof Swal !== 'undefined') {
      Swal.fire({
        icon: 'success',
        title: 'Application Submitted!',
        text: nextStepMessage,
        confirmButtonText: 'Proceed to My Applications',
        confirmButtonColor: '#0056b3',
        customClass: {
          confirmButton: 'btn btn-primary px-4 py-2'
        }
      }).then(function (result) {
        if (result.isConfirmed) {
          global.location.href = 'my_applications.html';
        }
      });
    } else if (global.ApplicantMessageToast && typeof global.ApplicantMessageToast.show === 'function') {
      global.ApplicantMessageToast.show(nextStepMessage, 'success');
    } else {
      alert(nextStepMessage);
    }
  }

  function notifyError(message) {
    if (typeof Swal !== 'undefined') {
      Swal.fire({
        icon: 'error',
        title: 'Notice',
        text: message || 'Could not apply.',
        confirmButtonColor: '#dc3545',
        customClass: {
          confirmButton: 'btn btn-danger px-4 py-2'
        }
      });
    } else if (global.ApplicantMessageToast && typeof global.ApplicantMessageToast.show === 'function') {
      global.ApplicantMessageToast.show(message || 'Could not apply.', 'danger');
    } else {
      alert(message || 'Could not apply.');
    }
  }

  function refreshJobList() {
    if (global.ApplicantJobFilter && typeof global.ApplicantJobFilter.loadJobs === 'function') {
      global.ApplicantJobFilter.loadJobs(undefined, { silent: true });
    } else if (typeof global.loadJobWall === 'function') {
      global.loadJobWall();
    } else if (typeof global.loadRecommendedJobs === 'function') {
      if (typeof global.getJobWallState === 'function') {
        var wallState = global.getJobWallState();
        global.loadRecommendedJobs(wallState.mode === 'profile' ? 'match' : wallState.mode);
      } else {
        global.loadRecommendedJobs();
      }
    }
  }

  function submitApplication(jobId, targetDetailId) {
    jobId = parseInt(jobId, 10) || 0;
    if (!jobId) return Promise.resolve();

    var payload = { job_order_id: jobId, job_id: jobId };
    var detailId = parseInt(targetDetailId, 10) || 0;
    if (detailId > 0) {
      payload.target_detail_id = detailId;
    }

    var confirmBtn = document.getElementById('placement-confirm-btn');
    if (confirmBtn) {
      confirmBtn.disabled = true;
      confirmBtn.textContent = 'Submitting…';
    }

    return fetchJson(SUBMIT_API, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      credentials: 'include',
      body: JSON.stringify(payload)
    })
      .then(function (res) {
        var d = res.data || {};
        if (res.ok && d.success) {
          closePlacementModal();
          notifySuccess(d.message || 'Application submitted successfully!');
          refreshJobList();
        } else if (d.requires_selection) {
          return handleApplyAction(jobId);
        } else {
          notifyError(d.message || 'Could not submit application.');
        }
      })
      .catch(function () {
        notifyError('Network error while submitting application.');
      })
      .finally(function () {
        if (confirmBtn) {
          confirmBtn.disabled = false;
          confirmBtn.textContent = 'Confirm Application';
        }
      });
  }

  function renderPlacementsList(placements, companyName) {
    var container = document.getElementById('placements-list-container');
    var subtitle = document.getElementById('placement-modal-subtitle');
    if (subtitle) {
      subtitle.textContent = companyName
        ? 'Choose the position and campus/site you want to apply for at ' + companyName + '.'
        : 'Choose your preferred position and deployment site.';
    }
    if (!container) return;

    container.innerHTML = placements.map(function (p, idx) {
      var detailId = p.detail_id != null ? p.detail_id : (p.target_detail_id != null ? p.target_detail_id : '');
      var inputId = 'placement-option-' + detailId + '-' + idx;
      var workers = parseInt(p.required_workers, 10) || 0;
      var workersLabel = workers > 0 ? workers + ' worker' + (workers === 1 ? '' : 's') + ' needed · ' : '';
      var isFilled = p.is_filled === true || p.status === 'filled';
      return (
        '<label class="placement-option-card' + (isFilled ? ' placement-option-filled' : '') + '" for="' + escapeHtml(inputId) + '">' +
          (isFilled ? '<span class="placement-filled-badge">FILLED</span>' : '') +
          '<input type="radio" class="form-check-input placement-radio" name="placement_choice" id="' + escapeHtml(inputId) + '" value="' + escapeHtml(String(detailId)) + '"' +
            (!isFilled && idx === 0 ? ' checked' : '') + (isFilled ? ' disabled' : '') + ' />' +
          '<div class="placement-option-body">' +
            '<div class="placement-option-title">' + escapeHtml(p.position_title || p.position || 'Role') + '</div>' +
            '<div class="placement-option-meta">' + escapeHtml(workersLabel + (p.deployment_site || p.site || '—')) + '</div>' +
            '<div class="placement-option-schedule"><i class="bi bi-clock"></i> ' + escapeHtml(p.work_schedule || '—') + '</div>' +
            (p.category_name ? '<div class="placement-option-category text-muted small">' + escapeHtml(p.category_name) + '</div>' : '') +
          '</div>' +
        '</label>'
      );
    }).join('');

    var firstOpen = container.querySelector('input.placement-radio:not(:disabled)');
    if (firstOpen) firstOpen.checked = true;
  }

  function openPlacementModal(placements, jobId, companyName) {
    pendingJobId = jobId;
    renderPlacementsList(placements, companyName);

    var modalEl = document.getElementById('placement-modal');
    if (!modalEl || typeof bootstrap === 'undefined') {
      notifyError('Placement modal is unavailable. Please refresh the page.');
      return;
    }

    if (!placementModalInstance) {
      placementModalInstance = new bootstrap.Modal(modalEl);
    }
    placementModalInstance.show();
  }

  function closePlacementModal() {
    pendingJobId = null;
    if (placementModalInstance) {
      placementModalInstance.hide();
    }
  }

  function getSelectedDetailId() {
    var selected = document.querySelector('#placements-list-container input.placement-radio:checked');
    if (!selected) return 0;
    return parseInt(selected.value, 10) || 0;
  }

  function handleApplyAction(jobId) {
    jobId = parseInt(jobId, 10) || 0;
    if (!jobId) return Promise.resolve();

    return fetchJson(PLACEMENTS_API + '?job_id=' + encodeURIComponent(String(jobId)), {
      credentials: 'include'
    })
      .then(function (res) {
        var d = res.data || {};
        if (!res.ok || !d.success) {
          notifyError(d.message || 'Could not load placement options.');
          return;
        }

        var placements = Array.isArray(d.placements) ? d.placements : [];
        var openPlacements = global.JobWallSlots && typeof global.JobWallSlots.openPlacements === 'function'
          ? global.JobWallSlots.openPlacements(placements)
          : placements.filter(function (p) { return !(p.is_filled === true || p.status === 'filled'); });

        if (!openPlacements.length) {
          notifyError('All deployment slots for this job order are filled.');
          refreshJobList();
          return;
        }

        var requiresSelection = d.requires_selection === true || openPlacements.length > 1 || placements.length > 1;

        if (requiresSelection && openPlacements.length > 1) {
          openPlacementModal(placements, jobId, d.company_name || '');
          return;
        }

        var only = openPlacements[0] || {};
        var detailId = only.detail_id != null ? only.detail_id : (only.target_detail_id != null ? only.target_detail_id : 0);
        return submitApplication(jobId, detailId);
      })
      .catch(function () {
        notifyError('Could not load placement options.');
      });
  }

  function requestApply(jobId) {
    if (global.OnazulAuth && typeof global.OnazulAuth.isLoggedIn === 'function' && !global.OnazulAuth.isLoggedIn()) {
      global.OnazulAuth.requireAuth(function () {
        handleApplyAction(jobId);
      }, 'landing');
      return;
    }
    handleApplyAction(jobId);
  }

  function initPlacementModal() {
    var confirmBtn = document.getElementById('placement-confirm-btn');
    if (confirmBtn) {
      confirmBtn.addEventListener('click', function () {
        if (!pendingJobId) return;
        var detailId = getSelectedDetailId();
        if (!detailId) {
          notifyError('Please select your preferred position and deployment site.');
          return;
        }
        submitApplication(pendingJobId, detailId);
      });
    }

    var modalEl = document.getElementById('placement-modal');
    if (modalEl) {
      modalEl.addEventListener('hidden.bs.modal', function () {
        pendingJobId = null;
      });
    }
  }

  global.ApplicantApply = {
    handleApplyAction: handleApplyAction,
    requestApply: requestApply,
    submitApplication: submitApplication
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPlacementModal);
  } else {
    initPlacementModal();
  }
})(window);