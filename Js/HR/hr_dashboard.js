/**
 * HR Dashboard - SPA with Job Orders, Applicant Review, Deployments
 * Sidebar navigation without full page reload; fetches APIs when section is shown.
 */

const API_BASE = '../../Api/HR/';
const JO_API = API_BASE + 'job_orders.php';
const REQ_API = API_BASE + 'job_order_requests.php';
const JOR_DETAILS_API = API_BASE + 'get_jor_details.php';
const DEPLOYMENTS_API = API_BASE + 'deployments.php';
const LOGOUT_API = '../../Api/Login/logout.php';
const RT_COUNTERS_API = '../../Api/Common/realtime_counters.php';
const MSG_THREADS_API = '../../Api/Common/messages_threads.php';
const MSG_MESSAGES_API = '../../Api/Common/messages_messages.php';
const MSG_SEND_API = '../../Api/Common/messages_send.php';
const NOTIFICATIONS_API = '../../Api/Common/notifications.php';
const HIRE_API = window.HIRE_APPLICANT_API || (API_BASE + 'convert_to_employee.php');
let inboxState = { activeThreadId: null, threads: [], userId: null };
function moveFocusOutOfModal(modalEl) {
  if (!document.activeElement || !modalEl.contains(document.activeElement)) return;
  document.activeElement.blur();
  if (!document.body.hasAttribute('tabindex')) document.body.setAttribute('tabindex', '-1');
  document.body.focus();
}

function safeModalHide(modalEl) {
  moveFocusOutOfModal(modalEl);
  var m = bootstrap.Modal.getInstance(modalEl);
  if (m) m.hide();
}

function isDashboardSectionVisible(sectionId) {
  var section = document.getElementById(sectionId);
  return !!(section && !section.classList.contains('d-none'));
}

document.addEventListener('DOMContentLoaded', function () {
  initModalA11y(['createJobOrderModal', 'reviewModal', 'hireApplicantModal', 'addDeploymentModal', 'documentPreviewModal']);
  var skipNav = window.SKIP_HR_DASHBOARD_NAV === true;
  var skipOverviewLinks = window.SKIP_HR_DASHBOARD_OVERVIEW_LINKS === true;
  var skipLogout = window.SKIP_HR_DASHBOARD_LOGOUT === true;
  if (!skipNav) initSPA();
  if (!skipOverviewLinks) initOverviewLinks();
  if (!skipLogout) initLogout();
  initJobOrders();
  initApplicantReview();
  initApplicantDocumentPreview();
  initHireApplicant();
  initDeployments();
  initMessagesInbox();
  initRealtimeCounters();
});

function initRealtimeCounters() {
  function poll() {
    fetch(RT_COUNTERS_API, { credentials: 'include' })
      .then(function (r) { return r.json().catch(function () { return null; }); })
      .then(function (d) {
        if (!d || !d.success || !d.logged_in) return;
        if (window.AdminSidebarBadges && typeof AdminSidebarBadges.apply === 'function') {
          AdminSidebarBadges.apply(d.counts || {});
        } else {
          var badge = document.getElementById('hrJobOrdersBadge');
          if (badge) {
            var c = parseInt((d.counts && d.counts.job_orders) ? d.counts.job_orders : 0, 10) || 0;
            badge.textContent = String(c);
            badge.classList.toggle('d-none', c <= 0);
          }
        }
        var jobOrdersSection = document.getElementById('section-job-orders');
        if (jobOrdersSection && jobOrdersSection.classList.contains('active')) {
          if (typeof loadJobOrderRequests === 'function') loadJobOrderRequests();
          if (typeof loadJobOrders === 'function') loadJobOrders();
        }
        var applicantSection = document.getElementById('section-applicant-review');
        if (isDashboardSectionVisible('section-applicant-review') && typeof loadApplications === 'function') {
          loadApplications();
        }
        var deploymentsSection = document.getElementById('section-deployments');
        if (deploymentsSection && deploymentsSection.classList.contains('active') && typeof loadDeployments === 'function') {
          loadDeployments();
        }
        var notifSection = document.getElementById('section-notifications');
        if (notifSection && notifSection.classList.contains('active') && window.OwNotifications) {
          OwNotifications.load();
        }
      })
      .catch(function () {});
  }
  poll();
  window.setInterval(poll, 5000);
}

function initModalA11y(modalIds) {
  modalIds.forEach(function (id) {
    var el = document.getElementById(id);
    if (!el) return;
    el.addEventListener('hide.bs.modal', function () {
      moveFocusOutOfModal(el);
    });
    // Move focus on mousedown so it's out before Bootstrap sets aria-hidden (fixes btn-close focus)
    el.addEventListener('mousedown', function (e) {
      if (e.target.closest('[data-bs-dismiss="modal"], .btn-close')) {
        moveFocusOutOfModal(el);
      }
    }, true);
  });
}

// ---- SPA navigation ----
function initSPA() {
  if (typeof ProfileViewSwitch === 'undefined' || !ProfileViewSwitch.initBootstrapSpa) return;

  window.hrShowSection = ProfileViewSwitch.initBootstrapSpa({
    defaultSectionId: 'section-overview',
    onShowSection: function (targetId) {
      if (targetId === 'section-job-orders') { loadJobOrders(); loadJobOrderRequests(); }
      else if (targetId === 'section-applicant-review') loadApplications();
      else if (targetId === 'section-messages') { loadMessageThreads().then(loadThreadMessages); }
      else if (targetId === 'section-notifications' && window.OwNotifications) OwNotifications.load();
      else if (targetId === 'section-deployments') loadDeployments();
    }
  });
  if (window.OwNotifications) {
    OwNotifications.init({ sectionId: 'section-notifications', showSection: window.hrShowSection });
  }
}

function initOverviewLinks() {
  document.querySelectorAll('#section-overview [data-target]').forEach(function (link) {
    link.addEventListener('click', function (e) {
      e.preventDefault();
      var targetId = this.getAttribute('data-target');
      if (!targetId) return;
      var section = document.getElementById(targetId);
      var sidebarLink = document.querySelector('#main-sidebar .sidebar-link[data-target="' + targetId + '"], #sidebar .sidebar-link[data-target="' + targetId + '"]');
      if (section) {
        document.querySelectorAll('.dashboard-section').forEach(function (s) {
          s.classList.add('d-none');
          s.classList.remove('active');
        });
        section.classList.remove('d-none');
        section.classList.add('active');
        if (targetId === 'section-job-orders') { loadJobOrders(); loadJobOrderRequests(); }
        else if (targetId === 'section-messages') { loadMessageThreads().then(loadThreadMessages); }
        else if (targetId === 'section-deployments') loadDeployments();
        document.querySelectorAll('#main-sidebar .sidebar-link[data-target], #sidebar .sidebar-link[data-target]').forEach(function (l) {
          l.classList.remove('active');
        });
        if (sidebarLink) sidebarLink.classList.add('active');
      }
    });
  });
}

// ---- Messages Inbox ----
function initMessagesInbox() {
  var sendBtn = document.getElementById('btnMsgSend');
  if (!sendBtn) return; // section not present

  fetch('../../Api/Login/check_session.php', { credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (d) { inboxState.userId = d && d.user_id ? d.user_id : null; })
    .catch(function () { inboxState.userId = null; });

  sendBtn.addEventListener('click', sendInboxMessage);
  setInterval(function () {
    var section = document.getElementById('section-messages');
    if (section && section.classList.contains('active')) {
      loadMessageThreads().then(loadThreadMessages);
    }
  }, 15000);
}

function loadMessageThreads() {
  var el = document.getElementById('msgThreads');
  if (!el) return Promise.resolve();
  return fetch(MSG_THREADS_API, { credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (d) {
      if (!d.success) {
        el.innerHTML = '<div class="text-danger p-2">Failed to load conversations.</div>';
        return;
      }
      inboxState.threads = d.threads || [];
      if (!inboxState.activeThreadId && inboxState.threads.length > 0) {
        inboxState.activeThreadId = inboxState.threads[0].thread_id;
      }
      renderMessageThreads();
    })
    .catch(function () {
      el.innerHTML = '<div class="text-danger p-2">Failed to load conversations.</div>';
    });
}

function renderMessageThreads() {
  var el = document.getElementById('msgThreads');
  if (!el) return;
  if (!inboxState.threads || inboxState.threads.length === 0) {
    el.innerHTML = '<div class="text-muted p-2">No conversations yet.</div>';
    return;
  }
  el.innerHTML = inboxState.threads.map(function (t) {
    var active = t.thread_id === inboxState.activeThreadId ? 'bg-light' : '';
    var unread = t.unread_count > 0 ? '<span class="badge bg-warning text-dark ms-1">' + t.unread_count + '</span>' : '';
    return '<div class="p-2 border rounded mb-2 ' + active + '" style="cursor:pointer" data-thread-id="' + t.thread_id + '">' +
      '<div class="d-flex justify-content-between align-items-start">' +
      '<div><strong>' + esc(t.other_user_name || (t.thread_type || 'User')) + '</strong>' + unread + '</div>' +
      '</div>' +
      '<div class="small text-muted">' + esc((t.last_message_text || '').substring(0, 70)) + '</div>' +
      '</div>';
  }).join('');
  el.querySelectorAll('[data-thread-id]').forEach(function (n) {
    n.addEventListener('click', function () {
      inboxState.activeThreadId = parseInt(n.getAttribute('data-thread-id'), 10);
      renderMessageThreads();
      loadThreadMessages();
    });
  });
}

function loadThreadMessages() {
  var box = document.getElementById('msgMessages');
  if (!box) return Promise.resolve();
  if (!inboxState.activeThreadId) {
    box.innerHTML = '<div class="text-muted">Select a conversation.</div>';
    return Promise.resolve();
  }
  var thread = inboxState.threads.find(function (t) { return t.thread_id === inboxState.activeThreadId; });
  var titleEl = document.getElementById('msgActiveTitle');
  var subEl = document.getElementById('msgActiveSub');
  if (titleEl) titleEl.textContent = thread ? (thread.other_user_name || 'User') : 'Conversation';
  if (subEl) subEl.textContent = thread && thread.thread_type ? thread.thread_type.replace(/_/g, ' ') : '';

  return fetch(MSG_MESSAGES_API + '?thread_id=' + encodeURIComponent(inboxState.activeThreadId), { credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (d) {
      if (!d.success) {
        box.innerHTML = '<div class="text-danger">Failed to load messages.</div>';
        return;
      }
      var msgs = d.messages || [];
      if (msgs.length === 0) {
        box.innerHTML = '<div class="text-muted">No messages yet.</div>';
        return;
      }
      box.innerHTML = msgs.map(function (m) {
        var mine = inboxState.userId && m.sender_user_id === inboxState.userId;
        var align = mine ? 'text-end' : 'text-start';
        var cls = mine ? 'bg-primary text-white ms-auto' : 'bg-light';
        var who = m.is_system ? 'System' : esc(m.sender_name || 'User');
        return '<div class="' + align + ' mb-2"><div class="small text-muted">' + who + '</div>' +
          '<div class="d-inline-block rounded px-3 py-2 ' + cls + '" style="max-width:75%;">' + esc(m.message_text || '') + '</div></div>';
      }).join('');
      box.scrollTop = box.scrollHeight;
    })
    .catch(function () {
      box.innerHTML = '<div class="text-danger">Failed to load messages.</div>';
    });
}

function sendInboxMessage() {
  var input = document.getElementById('msgInput');
  if (!input) return;
  var text = (input.value || '').trim();
  if (!text) return;
  var payload = { message_text: text };
  if (inboxState.activeThreadId) payload.thread_id = inboxState.activeThreadId;
  fetch(MSG_SEND_API, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'same-origin',
    body: JSON.stringify(payload)
  })
    .then(function (r) { return r.json(); })
    .then(function (d) {
      if (!d.success) {
        alert(d.message || 'Failed to send message.');
        return;
      }
      inboxState.activeThreadId = d.thread_id || inboxState.activeThreadId;
      input.value = '';
      loadMessageThreads().then(loadThreadMessages);
    })
    .catch(function () { alert('Failed to send message.'); });
}

function initLogout() {
  const btn = document.getElementById('logoutBtn');
  if (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      var doLogout = function () {
        fetch(LOGOUT_API, { method: 'GET', credentials: 'same-origin' })
          .then(function () { window.location.href = '/Employee_Tracking/index.html'; })
          .catch(function () { window.location.href = '/Employee_Tracking/index.html'; });
      };
      if (typeof showLogoutConfirm === 'function') {
        showLogoutConfirm(doLogout);
      } else {
        doLogout();
      }
    });
  }
}

// ---- Job Order Requests (from Team Leads) ----
var viewJorDetailsModalInstance = null;

function formatJorDetailDate(value) {
  if (!value) return '—';
  var d = new Date(value);
  if (isNaN(d.getTime())) return String(value);
  return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

function viewJorDetails(jorId) {
  jorId = parseInt(jorId, 10) || 0;
  if (!jorId) return;

  var modalEl = document.getElementById('viewJorDetailsModal');
  var tbody = document.getElementById('viewJorDetailsTableBody');
  if (!modalEl || !tbody) return;

  var metaId = document.getElementById('viewJorMetaId');
  var metaCompany = document.getElementById('viewJorMetaCompany');
  var metaLead = document.getElementById('viewJorMetaLead');
  var metaDate = document.getElementById('viewJorMetaDate');
  var metaRemarks = document.getElementById('viewJorMetaRemarks');

  if (metaId) metaId.textContent = 'JOR-' + jorId;
  if (metaCompany) metaCompany.textContent = 'Loading…';
  if (metaLead) metaLead.textContent = 'Loading…';
  if (metaDate) metaDate.textContent = 'Loading…';
  if (metaRemarks) {
    metaRemarks.textContent = '';
    metaRemarks.classList.add('d-none');
  }
  tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Loading details…</td></tr>';

  if (!viewJorDetailsModalInstance) {
    viewJorDetailsModalInstance = new bootstrap.Modal(modalEl);
  }
  viewJorDetailsModalInstance.show();

  fetch(JOR_DETAILS_API + '?jor_id=' + encodeURIComponent(String(jorId)), { credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (!data || !data.success) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-4">' + esc((data && data.message) || 'Failed to load details.') + '</td></tr>';
        return;
      }

      if (metaId) metaId.textContent = 'JOR-' + (data.jor_id || jorId);
      if (metaCompany) metaCompany.textContent = data.company_name || '—';
      if (metaLead) metaLead.textContent = data.requested_by || '—';
      if (metaDate) metaDate.textContent = formatJorDetailDate(data.created_at);

      if (metaRemarks) {
        var remarks = String(data.remarks || '').trim();
        if (remarks) {
          metaRemarks.textContent = 'Remarks: ' + remarks;
          metaRemarks.classList.remove('d-none');
        } else {
          metaRemarks.classList.add('d-none');
        }
      }

      var positions = Array.isArray(data.positions) ? data.positions : [];
      if (!positions.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No deployment rows found.</td></tr>';
        return;
      }

      tbody.innerHTML = positions.map(function (p) {
        var workers = parseInt(p.required_workers, 10) || 0;
        return '<tr>' +
          '<td class="fw-semibold">' + esc(p.position_title || '—') + '</td>' +
          '<td>' + esc(p.category_name || '—') + '</td>' +
          '<td><span class="jor-worker-badge"><i class="bi bi-people-fill"></i> ' + workers + ' worker' + (workers === 1 ? '' : 's') + '</span></td>' +
          '<td>' + esc(p.deployment_site || '—') + '</td>' +
          '<td>' + esc(p.work_schedule || '—') + '</td>' +
        '</tr>';
      }).join('');
    })
    .catch(function () {
      tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-4">Request failed while loading details.</td></tr>';
    });
}
window.viewJorDetails = viewJorDetails;

function loadJobOrderRequests() {
  var filterEl = document.getElementById('requestFilter');
  var status = filterEl ? filterEl.value : '';
  var url = status ? REQ_API + '?status=' + encodeURIComponent(status) : REQ_API;
  fetch(url, { credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (d) {
      var tbody = document.getElementById('requestsBody');
      if (!tbody) return;
      if (!d.success || !d.requests || d.requests.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">No job order requests.</td></tr>';
        return;
      }
      tbody.innerHTML = d.requests.map(function (r) {
        var requestId = r.request_id || r.jor_id;
        var actions = '<button type="button" class="btn btn-sm btn-outline-primary me-1" title="View details" onclick="viewJorDetails(' + requestId + ')"><i class="bi bi-eye"></i></button>';
        if (r.status === 'Pending') {
          actions += '<button class="btn btn-sm btn-success me-1 btnApprove" data-id="' + requestId + '">Post</button>' +
            '<button class="btn btn-sm btn-outline-danger btnReject" data-id="' + requestId + '">Reject</button>';
        }
        var statusCls = r.status === 'Approved' ? 'success' : (r.status === 'Rejected' ? 'danger' : 'warning');
        return '<tr><td>' + esc(r.requested_by) + '</td><td>' + esc(r.company_name) + '</td><td>' + esc(r.position) + '</td><td>' + r.required_count + '</td><td>' + (esc(r.location) || '–') + '</td><td>' + (esc(r.work_schedule) || '–') + '</td><td>' + (r.created_at ? new Date(r.created_at).toLocaleDateString() : '–') + '</td><td><span class="badge bg-' + statusCls + '">' + esc(r.status) + '</span></td><td>' + actions + '</td></tr>';
      }).join('');
      tbody.querySelectorAll('.btnApprove').forEach(function (btn) {
        btn.addEventListener('click', function () { approveJobOrderRequest(parseInt(btn.getAttribute('data-id'), 10)); });
      });
      tbody.querySelectorAll('.btnReject').forEach(function (btn) {
        btn.addEventListener('click', function () { rejectJobOrderRequest(parseInt(btn.getAttribute('data-id'), 10)); });
      });
    })
    .catch(function () {
      var tbody = document.getElementById('requestsBody');
      if (tbody) tbody.innerHTML = '<tr><td colspan="9" class="text-center text-danger py-4">Failed to load.</td></tr>';
    });
}

function approveJobOrderRequest(id) {
  if (typeof Swal !== 'undefined') {
    Swal.fire({
      title: 'Post Job Order Request?',
      text: 'Are you sure you want to post this request as an active Job Order?',
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#198754', // Green button
      cancelButtonColor: '#6c757d',
      confirmButtonText: 'Yes, Post It'
    }).then(function (result) {
      if (result.isConfirmed) {
        fetch(REQ_API, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'approve', request_id: id }),
          credentials: 'same-origin'
        })
          .then(function (r) { return r.json(); })
          .then(function (d) {
            if (d.success) {
              Swal.fire({
                icon: 'success',
                title: 'Posted!',
                text: d.message || 'Job Order posted successfully.',
                timer: 2000,
                showConfirmButton: false
              });
              loadJobOrderRequests();
              loadJobOrders();
            } else {
              Swal.fire({
                icon: 'error',
                title: 'Error',
                text: d.message || 'Failed to post request.'
              });
            }
          })
          .catch(function () {
            Swal.fire({
              icon: 'error',
              title: 'Error',
              text: 'Request failed. Please try again.'
            });
          });
      }
    });
  } else {
    // Fallback if SweetAlert2 isn't loaded
    if (!confirm('Post this request as a Job Order?')) return;
    fetch(REQ_API, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'approve', request_id: id }), credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d.success) { loadJobOrderRequests(); loadJobOrders(); }
        else alert(d.message || 'Error');
      });
  }
}

function rejectJobOrderRequest(id) {
  if (!confirm('Reject this request?')) return;
  fetch(REQ_API, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'reject', request_id: id }), credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (d) {
      if (d.success) loadJobOrderRequests();
      else alert(d.message || 'Error');
    });
}

// ---- Job Orders ----
function initJobOrders() {
  var requestFilter = document.getElementById('requestFilter');
  if (requestFilter) requestFilter.addEventListener('change', loadJobOrderRequests);
  const joCompanySelect = document.getElementById('joCompanySelect');
  if (joCompanySelect) {
    joCompanySelect.addEventListener('change', function () {
      const cid = this.value;
      const sel = document.getElementById('joAgreementSelect');
      sel.innerHTML = '<option value="">Loading…</option>';
      if (!cid) {
        sel.innerHTML = '<option value="">Select client first</option>';
        return;
      }
      fetch(JO_API + '?check_agreement=1&company_id=' + cid, { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (d.can_create && d.agreements && d.agreements.length) {
            sel.innerHTML = d.agreements.map(function (a) {
              return '<option value="' + a.ServiceAgreementID + '">' + esc(a.ContractNumber) + ' (' + (a.ContractStartDate || '') + ' – ' + (a.ContractEndDate || 'ongoing') + ')</option>';
            }).join('');
            document.getElementById('noAgreementBlock').classList.add('validation-block');
          } else {
            sel.innerHTML = '<option value="">No active agreement</option>';
            document.getElementById('noAgreementBlock').classList.remove('validation-block');
          }
        });
    });
  }

  document.getElementById('btnCreateJobOrder').addEventListener('click', function () {
    loadJOCompanies().then(function () {
      document.getElementById('joAgreementSelect').innerHTML = '<option value="">Select client first</option>';
      document.getElementById('noAgreementBlock').classList.add('validation-block');
      var now = new Date();
      document.getElementById('joRequestDateDisplay').value = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');
      new bootstrap.Modal(document.getElementById('createJobOrderModal')).show();
    });
  });

  var joWorkScheduleSelect = document.getElementById('joWorkScheduleSelect');
  var joWorkScheduleOther = document.getElementById('joWorkScheduleOther');
  if (joWorkScheduleSelect && joWorkScheduleOther) {
    joWorkScheduleSelect.addEventListener('change', function () {
      joWorkScheduleOther.style.display = this.value === 'Other' ? 'block' : 'none';
      if (this.value !== 'Other') joWorkScheduleOther.value = '';
    });
  }

  document.getElementById('btnSubmitJobOrder').addEventListener('click', function () {
    const form = document.getElementById('createJobOrderForm');
    const fd = new FormData(form);
    if (joWorkScheduleSelect && joWorkScheduleSelect.value === 'Other' && joWorkScheduleOther) {
      fd.set('work_schedule', joWorkScheduleOther.value.trim());
    }
    fd.delete('work_schedule_other');
    fd.append('action', 'create');
    const alert = document.getElementById('createJOAlert');
    fetch(JO_API, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) {
        return r.text().then(function (text) {
          try {
            return { ok: r.ok, status: r.status, data: JSON.parse(text) };
          } catch (e) {
            return { ok: false, status: r.status, data: null, raw: text };
          }
        });
      })
      .then(function (res) {
        var d = res.data;
        if (res.data === null) {
          showAlert(alert, res.raw || 'Request failed: server returned invalid response (status ' + res.status + ')');
          return;
        }
        if (d && d.success) {
          safeModalHide(document.getElementById('createJobOrderModal'));
          form.reset();
          loadJobOrders();
          alert.classList.add('d-none');
        } else {
          showAlert(alert, (d && (d.message || d.error)) || 'Request failed');
        }
      })
      .catch(function (err) {
        showAlert(alert, 'Request failed: ' + (err.message || 'network error'));
      });
  });
}

function loadJOCompanies() {
  return fetch(JO_API + '?list=companies', { credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (d) {
      const sel = document.getElementById('joCompanySelect');
      sel.innerHTML = '<option value="">Select client company</option>' + (d.companies || []).map(function (c) {
        return '<option value="' + c.CompanyID + '">' + esc(c.Name) + '</option>';
      }).join('');
    });
}

function loadJobOrders() {
  fetch(JO_API, { credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (d) {
      const tbody = document.getElementById('ordersBody');
      if (!tbody) return;
      if (!d.success || !d.job_orders || d.job_orders.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No Job Orders yet.</td></tr>';
        return;
      }
      tbody.innerHTML = d.job_orders.map(function (o) {
        return '<tr><td>' + esc(o.CompanyName) + '</td><td>' + esc(o.Position) + '</td><td>' + o.RequiredCount + '</td><td>' + (esc(o.Location) || '–') + '</td><td>' + (esc(o.WorkSchedule) || '–') + '</td><td>' + (o.RequestDate || o.CreatedAt || '–') + '</td></tr>';
      }).join('');
    })
    .catch(function () {
      const tbody = document.getElementById('ordersBody');
      if (tbody) tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-4">Failed to load.</td></tr>';
    });
}

// ---- Applicant Review ----
function initApplicantReview() {
  const filterStatus = document.getElementById('filterStatus');
  if (filterStatus) filterStatus.addEventListener('change', loadApplications);
  setInterval(function () {
    if (isDashboardSectionVisible('section-applicant-review')) loadApplications();
  }, 30000);
}

function loadApplications() {
  const filterEl = document.getElementById('filterStatus');
  const status = filterEl ? filterEl.value : '';
  const url = API_BASE + 'applicant_review.php' + (status ? '?status=' + encodeURIComponent(status) : '');

  fetch(url, { credentials: 'include' })
    .then(function (r) {
      return r.text().then(function (text) {
        var data = null;
        try {
          data = text ? JSON.parse(text) : null;
        } catch (e) {
          data = null;
        }
        return { ok: r.ok, status: r.status, data: data };
      });
    })
    .then(function (res) {
      const tbody = document.getElementById('applicationsBody');
      if (!tbody) return;
      const data = res.data || {};
      if (res.ok && data.success) {
        renderApplications(data.applications || []);
      } else if (res.status === 401 || res.status === 403) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-4">Session expired or access denied. Please sign in again.</td></tr>';
      } else {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-4">' + escapeHtml((data && (data.error || data.message)) || 'Failed to load applications.') + '</td></tr>';
      }
    })
    .catch(function () {
      const tbody = document.getElementById('applicationsBody');
      if (tbody) tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-4">Error loading applications.</td></tr>';
    });
}

function renderApplications(apps) {
  const tbody = document.getElementById('applicationsBody');
  if (!tbody) return;
  if (apps.length === 0) {
    tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No applications found.</td></tr>';
    return;
  }
  tbody.innerHTML = apps.map(function (app) {
    var docCount = parseInt(app.document_count != null ? app.document_count : app.doc_count, 10) || 0;
    return '<tr><td><strong>' + escapeHtml(app.applicant_name) + '</strong><br><small class="text-muted">' + escapeHtml(app.email) + '</small></td><td>' + escapeHtml(app.position) + '</td><td>' + escapeHtml(app.company_name) + '</td><td>' + app.application_date + '</td><td><span class="badge bg-' + (app.status_badge || getStatusColor(app.status)) + '">' + escapeHtml(app.status) + '</span></td><td><span class="badge bg-' + (docCount > 0 ? 'success' : 'warning') + '">' + docCount + ' doc' + (docCount !== 1 ? 's' : '') + '</span></td><td><button class="btn btn-sm btn-primary" data-app-id="' + app.application_id + '"><i class="bi bi-eye"></i> Review</button></td></tr>';
  }).join('');

  tbody.querySelectorAll('button[data-app-id]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      openReview(parseInt(this.getAttribute('data-app-id'), 10));
    });
  });
}

function getStatusColor(status) {
  const colors = {
    'Applied': 'secondary',
    'Pending': 'secondary',
    'Scheduled for Interview': 'info',
    'For Interview': 'info',
    'Interview Scheduled': 'info',
    'Endorsed to Client': 'primary',
    'Endorsed': 'primary',
    'Approved by Company': 'warning',
    'Approved': 'warning',
    'Rejected': 'danger',
    'Declined': 'danger',
    'Hired': 'success'
  };
  return colors[status] || 'secondary';
}

function openReview(appId) {
  fetch(API_BASE + 'get_application_details.php?id=' + appId, { credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (data.success) {
        renderReviewModal(data, appId);
        new bootstrap.Modal(document.getElementById('reviewModal')).show();
      }
    });
}

function renderReviewModal(data, appId) {
  const content = document.getElementById('reviewContent');
  const statusBadgeClass = data.status_badge || getStatusColor(data.status);
  const endorsedBtn = (data.workflow_stage === 'applied' || data.workflow_stage === 'scheduled_for_interview')
    ? '<button class="btn btn-primary btn-sm btn-endorse" data-app-id="' + appId + '">Endorse to Client</button>'
    : '';

  content.innerHTML =
    '<div class="mb-3">' +
      '<h6>Applicant Information</h6>' +
      '<p><strong>Name:</strong> ' + escapeHtml(data.applicant_name) + '</p>' +
      '<p><strong>Email:</strong> ' + escapeHtml(data.email) + '</p>' +
      '<p><strong>Mobile:</strong> ' + escapeHtml(data.mobile || 'N/A') + '</p>' +
    '</div>' +
    '<div class="mb-3">' +
      '<h6>Application Details</h6>' +
      '<p><strong>Position:</strong> ' + escapeHtml(data.position) + '</p>' +
      '<p><strong>Company:</strong> ' + escapeHtml(data.company_name) + '</p>' +
      '<p><strong>Applied:</strong> ' + data.application_date + '</p>' +
      '<p><strong>Current Status:</strong> <span class="badge bg-' + statusBadgeClass + '">' + escapeHtml(data.status) + '</span></p>' +
      (data.interview_date ? '<p><strong>Interview:</strong> ' + escapeHtml(data.interview_date) + '</p>' : '') +
      (data.endorsed_date ? '<p><strong>Endorsed:</strong> ' + escapeHtml(data.endorsed_date) + '</p>' : '') +
    '</div>' +
    '<div class="mb-3">' +
      '<h6>Documents</h6>' +
      '<div id="documentsList">' + renderDocuments(data.documents || []) + '</div>' +
    '</div>' +
    '<div class="mb-3">' +
      '<h6>Actions</h6>' +
      '<div class="d-flex gap-2 flex-wrap mb-3">' +
        '<button class="btn btn-info btn-sm btn-schedule" data-app-id="' + appId + '"><i class="bi bi-calendar"></i> Schedule Interview</button>' +
        endorsedBtn +
        buildHireActionHtml(data, appId) +
      '</div>' +
      '<div class="border rounded p-2 bg-light small">' +
        '<div class="row g-2 align-items-center">' +
          '<div class="col-md-5 col-12">' +
            '<label class="form-label mb-1">Interview date</label>' +
            '<input type="date" class="form-control form-control-sm" id="interviewDate">' +
          '</div>' +
          '<div class="col-md-4 col-12">' +
            '<label class="form-label mb-1">Time</label>' +
            '<select class="form-select form-select-sm" id="interviewTime">' +
              '<option value="">Select time</option>' +
              '<option value="09:00">09:00 AM</option>' +
              '<option value="10:00">10:00 AM</option>' +
              '<option value="11:00">11:00 AM</option>' +
              '<option value="14:00">02:00 PM</option>' +
              '<option value="15:00">03:00 PM</option>' +
              '<option value="16:00">04:00 PM</option>' +
            '</select>' +
          '</div>' +
          '<div class="col-md-3 col-12">' +
            '<button type="button" class="btn btn-outline-primary btn-sm w-100 btn-save-interview">' +
              '<i class="bi bi-save me-1"></i>Save schedule' +
            '</button>' +
          '</div>' +
        '</div>' +
      '</div>' +
    '</div>';

  // Wire actions
  var btnSchedule = content.querySelector('.btn-schedule');
  var btnEndorse = content.querySelector('.btn-endorse');
  var btnSaveInterview = content.querySelector('.btn-save-interview');

  if (btnSchedule) {
    btnSchedule.addEventListener('click', function () {
      triggerScheduleInterview(appId);
    });
  }

  if (btnSaveInterview) {
    btnSaveInterview.addEventListener('click', function () {
      triggerScheduleInterview(appId);
    });
  }

  if (btnEndorse) {
    btnEndorse.addEventListener('click', function () {
      endorseToClient(appId, data);
    });
  }

  var btnHire = content.querySelector('.btn-hire-applicant');
  if (btnHire) {
    btnHire.addEventListener('click', function () {
      openHireApplicantModal(appId, data.applicant_name + ' — ' + data.position + ' @ ' + data.company_name);
    });
  }
}

function buildHireActionHtml(data, appId) {
  if (data.is_hired) {
    return ' <span class="badge bg-success align-self-center">Hired</span>';
  }
  if (data.can_hire) {
    return ' <button type="button" class="btn btn-dark btn-sm btn-hire-applicant" data-app-id="' + appId + '">' +
      '<i class="bi bi-person-check"></i> Hire Applicant</button>';
  }
  if ((data.client_approval_status || '') === 'Approved' && !data.contract_uploaded) {
    return ' <span class="text-muted small align-self-center">Upload contract in Applicant Contracts first.</span>';
  }
  return '';
}

function formatPhpAmount(value) {
  var n = Number(value);
  if (!isFinite(n)) return '—';
  return '₱' + n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function updateHireSalaryPreview() {
  var dailyEl = document.getElementById('hireDailyRate');
  var hourlyEl = document.getElementById('hireHourlyRatePreview');
  var monthlyEl = document.getElementById('hireMonthlyCreditPreview');
  if (!dailyEl || !hourlyEl || !monthlyEl) return;
  var daily = parseFloat(dailyEl.value);
  if (!isFinite(daily) || daily <= 0) {
    hourlyEl.textContent = '—';
    monthlyEl.textContent = '—';
    return;
  }
  hourlyEl.textContent = formatPhpAmount(Math.round((daily / 8) * 100) / 100);
  monthlyEl.textContent = formatPhpAmount(Math.round((daily * 26) * 100) / 100);
}

function initHireApplicant() {
  var btn = document.getElementById('btnSubmitHireApplicant');
  if (!btn || btn.dataset.bound === '1') return;
  btn.dataset.bound = '1';
  btn.addEventListener('click', submitHireApplicant);
  var dailyEl = document.getElementById('hireDailyRate');
  if (dailyEl && dailyEl.dataset.bound !== '1') {
    dailyEl.dataset.bound = '1';
    dailyEl.addEventListener('input', updateHireSalaryPreview);
    dailyEl.addEventListener('change', updateHireSalaryPreview);
  }
}

function openHireApplicantModal(appId, summaryText) {
  var hireModalEl = document.getElementById('hireApplicantModal');
  if (!hireModalEl) return;
  document.getElementById('hireApplicationId').value = String(appId);
  document.getElementById('hireSssNumber').value = '';
  document.getElementById('hirePhilhealthNumber').value = '';
  document.getElementById('hirePagibigNumber').value = '';
  var dailyEl = document.getElementById('hireDailyRate');
  var allowanceEl = document.getElementById('hireAllowance');
  if (dailyEl) dailyEl.value = '';
  if (allowanceEl) allowanceEl.value = '0';
  updateHireSalaryPreview();
  var alertEl = document.getElementById('hireApplicantAlert');
  if (alertEl) alertEl.classList.add('d-none');
  var summaryEl = document.getElementById('hireApplicantSummary');
  if (summaryEl && summaryText) summaryEl.textContent = 'Hiring: ' + summaryText;
  var reviewModal = document.getElementById('reviewModal');
  if (reviewModal) safeModalHide(reviewModal);
  new bootstrap.Modal(hireModalEl).show();
}

function submitHireApplicant() {
  var appId = parseInt(document.getElementById('hireApplicationId').value, 10) || 0;
  var sss = (document.getElementById('hireSssNumber').value || '').trim();
  var phil = (document.getElementById('hirePhilhealthNumber').value || '').trim();
  var pag = (document.getElementById('hirePagibigNumber').value || '').trim();
  var dailyRate = parseFloat((document.getElementById('hireDailyRate') || {}).value || '');
  var allowance = parseFloat((document.getElementById('hireAllowance') || {}).value || '0');
  var alertEl = document.getElementById('hireApplicantAlert');
  var btn = document.getElementById('btnSubmitHireApplicant');

  if (!appId || !sss || !phil || !pag) {
    showAlert(alertEl, 'Please complete all required government ID fields.');
    return;
  }
  if (!isFinite(dailyRate) || dailyRate <= 0) {
    showAlert(alertEl, 'Daily Rate (PHP) is required and must be greater than zero.');
    return;
  }
  if (!isFinite(allowance) || allowance < 0) {
    showAlert(alertEl, 'Monthly Allowance cannot be negative.');
    return;
  }

  if (btn) btn.disabled = true;
  fetch(HIRE_API, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'same-origin',
    body: JSON.stringify({
      application_id: appId,
      sss_number: sss,
      philhealth_number: phil,
      pagibig_number: pag,
      daily_rate: dailyRate,
      allowance: isFinite(allowance) ? allowance : 0
    })
  })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (!data || !data.success) {
        showAlert(alertEl, (data && data.message) ? data.message : 'Hire failed.');
        return;
      }
      safeModalHide(document.getElementById('hireApplicantModal'));
      safeModalHide(document.getElementById('reviewModal'));
      var msg = data.message || 'Applicant successfully hired and role elevated.';
      if (data.employee_number) {
        msg += ' Employee #: ' + data.employee_number;
      }
      if (data.salary && data.salary.monthly_salary_credit != null) {
        msg += ' Monthly credit: ' + formatPhpAmount(data.salary.monthly_salary_credit) + '.';
      }
      if (typeof Swal !== 'undefined') {
        Swal.fire({
          icon: 'success',
          title: 'Hired',
          text: msg,
          timer: 3500,
          showConfirmButton: true
        });
      } else {
        alert(msg);
      }
      removeApplicationRowFromTable(appId);
      loadApplications();
      if (window.AdminSidebarBadges && typeof AdminSidebarBadges.poll === 'function') {
        AdminSidebarBadges.poll(RT_COUNTERS_API);
      }
    })
    .catch(function () {
      showAlert(alertEl, 'Request failed. Please try again.');
    })
    .finally(function () {
      if (btn) btn.disabled = false;
    });
}

function removeApplicationRowFromTable(appId) {
  var tbody = document.getElementById('applicationsBody');
  if (!tbody) return;
  var reviewBtn = tbody.querySelector('button[data-app-id="' + appId + '"]');
  if (reviewBtn) {
    var row = reviewBtn.closest('tr');
    if (row) row.remove();
  }
  if (!tbody.querySelector('tr')) {
    tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No applications found.</td></tr>';
  }
}

function resolveApplicantDocUrl(filePath) {
  if (!filePath) return '';
  var p = String(filePath).trim().replace(/\\/g, '/');
  if (/^https?:\/\//i.test(p)) return p;
  p = p.replace(/^\.\.\/\.\.\//, '');
  return '../../' + p.replace(/^\/+/, '');
}

function inferApplicantFileType(filename, filePath) {
  var source = String(filename || filePath || '').toLowerCase();
  var ext = source.split('.').pop();
  if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'].indexOf(ext) !== -1) return 'image';
  if (ext === 'pdf') return 'pdf';
  return 'other';
}

function initApplicantDocumentPreview() {
  var previewEl = document.getElementById('documentPreviewModal');
  if (!previewEl || previewEl.dataset.bound === '1') return;
  previewEl.dataset.bound = '1';

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.btn-view-applicant-doc');
    if (!btn) return;
    e.preventDefault();
    openApplicantDocumentPreview(
      btn.getAttribute('data-file-url'),
      btn.getAttribute('data-file-type'),
      btn.getAttribute('data-doc-title')
    );
  });

  previewEl.addEventListener('show.bs.modal', function () {
    window.setTimeout(function () {
      var backs = document.querySelectorAll('.modal-backdrop');
      if (backs.length) backs[backs.length - 1].style.zIndex = '1055';
      previewEl.style.zIndex = '1060';
    }, 0);
  });

  previewEl.addEventListener('hidden.bs.modal', function () {
    var frameEl = document.getElementById('documentPreviewFrame');
    var imageEl = document.getElementById('documentPreviewImage');
    if (frameEl) {
      frameEl.removeAttribute('src');
      frameEl.style.display = 'none';
    }
    if (imageEl) {
      imageEl.removeAttribute('src');
      imageEl.style.display = 'none';
    }
  });
}

function openApplicantDocumentPreview(fileUrl, fileType, title) {
  var previewEl = document.getElementById('documentPreviewModal');
  var titleEl = document.getElementById('documentPreviewTitle');
  var frameEl = document.getElementById('documentPreviewFrame');
  var imageEl = document.getElementById('documentPreviewImage');
  var emptyEl = document.getElementById('documentPreviewEmpty');
  var downloadEl = document.getElementById('documentPreviewDownload');
  if (!previewEl || !titleEl || !frameEl || !imageEl || !emptyEl || !downloadEl) return;

  var url = fileUrl || '';
  var type = (fileType || '').toLowerCase();

  titleEl.textContent = title || 'Document Preview';
  downloadEl.href = url;
  downloadEl.classList.toggle('d-none', !url);

  frameEl.style.display = 'none';
  imageEl.style.display = 'none';
  emptyEl.classList.add('d-none');
  frameEl.removeAttribute('src');
  imageEl.removeAttribute('src');

  if (!url) {
    emptyEl.textContent = 'Preview is not available for this file.';
    emptyEl.classList.remove('d-none');
  } else if (type === 'image') {
    imageEl.src = url;
    imageEl.style.display = 'block';
  } else if (type === 'pdf') {
    frameEl.src = url;
    frameEl.style.display = 'block';
  } else {
    emptyEl.textContent = 'Preview is not available for this file type. Use Download / Open File.';
    emptyEl.classList.remove('d-none');
  }

  bootstrap.Modal.getOrCreateInstance(previewEl).show();
}

function renderDocuments(docs) {
  if (docs.length === 0) return '<p class="text-muted">No documents uploaded.</p>';
  return '<ul class="list-group">' + docs.map(function (doc) {
    var fileUrl = resolveApplicantDocUrl(doc.file_path);
    var fileType = inferApplicantFileType(doc.original_filename, doc.file_path);
    var docTitle = (doc.doc_type || 'Document') + ' - ' + (doc.original_filename || 'File');
    return '<li class="list-group-item d-flex justify-content-between align-items-center gap-2">' +
      '<span>' + escapeHtml(doc.doc_type) + ' - ' + escapeHtml(doc.original_filename) + '</span>' +
      '<button type="button" class="btn btn-sm btn-outline-primary btn-view-applicant-doc flex-shrink-0" ' +
      'data-file-url="' + escapeHtml(fileUrl) + '" ' +
      'data-file-type="' + escapeHtml(fileType) + '" ' +
      'data-doc-title="' + escapeHtml(docTitle) + '">View</button>' +
    '</li>';
  }).join('') + '</ul>';
}

function updateStatus(appId, status) {
  fetch(API_BASE + 'update_application_status.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ application_id: appId, status: status }),
    credentials: 'same-origin'
  })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (data.success) {
        alert('Status updated successfully!');
        safeModalHide(document.getElementById('reviewModal'));
        loadApplications();
      } else {
        alert(data.message || 'Failed to update status.');
      }
    });
}

function triggerScheduleInterview(appId) {
  var dateInput = document.getElementById('interviewDate');
  var timeInput = document.getElementById('interviewTime');
  if (!dateInput || !timeInput) return;

  var d = dateInput.value;
  var t = timeInput.value;
  if (!d || !t) {
    if (typeof Swal !== 'undefined') {
      Swal.fire({
        icon: 'warning',
        title: 'Missing Date/Time',
        text: 'Please select both interview date and time.',
        confirmButtonColor: '#0056b3'
      });
    } else {
      alert('Please select both interview date and time.');
    }
    if (!d) dateInput.focus();
    else timeInput.focus();
    return;
  }

  var date = d + ' ' + t;
  scheduleInterview(appId, date);
}

function scheduleInterview(appId, date) {
  if (!date) return;
  fetch(API_BASE + 'schedule_interview.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ application_id: appId, interview_date: date }),
    credentials: 'same-origin'
  })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (data.success) {
        if (typeof Swal !== 'undefined') {
          Swal.fire({
            icon: 'success',
            title: 'Interview Scheduled!',
            text: 'Interview scheduled successfully!',
            confirmButtonColor: '#0056b3'
          });
        } else {
          alert('Interview scheduled successfully!');
        }
        safeModalHide(document.getElementById('reviewModal'));
        loadApplications();
      } else {
        if (typeof Swal !== 'undefined') {
          Swal.fire({
            icon: 'error',
            title: 'Scheduling Failed',
            text: data.message || 'Failed to schedule interview.',
            confirmButtonColor: '#dc3545'
          });
        } else {
          alert(data.message || 'Failed to schedule interview.');
        }
      }
    })
    .catch(function () {
      if (typeof Swal !== 'undefined') {
        Swal.fire({
          icon: 'error',
          title: 'Network Error',
          text: 'Request failed. Please try again.',
          confirmButtonColor: '#dc3545'
        });
      } else {
        alert('Request failed. Please try again.');
      }
    });
}

function endorseToClient(appId, appData) {
  if (appData && !appData.interview_date) {
    if (typeof Swal !== 'undefined') {
      Swal.fire({
        icon: 'warning',
        title: 'Interview Required',
        text: 'This applicant is not eligible for endorsement because they have not undergone an interview yet.',
        confirmButtonColor: '#0056b3'
      });
    } else {
      alert('This applicant cannot be endorsed because they have not undergone an interview yet.');
    }
    return;
  }

  var doEndorse = function () {
    fetch(API_BASE + 'endorse_to_client.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ application_id: appId }),
      credentials: 'same-origin'
    })
      .then(function (r) {
        return r.text().then(function (text) {
          try {
            return { ok: r.ok, status: r.status, data: JSON.parse(text) };
          } catch (e) {
            return { ok: false, status: r.status, data: null, raw: text };
          }
        });
      })
      .then(function (res) {
        var d = res.data;
        if (res.data === null) {
          if (typeof Swal !== 'undefined') {
            Swal.fire({
              icon: 'error',
              title: 'Error',
              text: 'Invalid server response (status ' + res.status + ')'
            });
          } else {
            alert('Invalid server response (status ' + res.status + ')');
          }
          return;
        }
        if (res.ok && d && d.success) {
          if (typeof Swal !== 'undefined') {
            Swal.fire({
              icon: 'success',
              title: 'Applicant Endorsed!',
              text: d.message || 'Applicant endorsed to client successfully!',
              confirmButtonColor: '#0056b3'
            });
          } else {
            alert(d.message || 'Applicant endorsed to client successfully!');
          }
          safeModalHide(document.getElementById('reviewModal'));
          loadApplications();
        } else {
          if (typeof Swal !== 'undefined') {
            Swal.fire({
              icon: 'error',
              title: 'Endorsement Failed',
              text: (d && (d.message || d.error)) || 'Failed to endorse applicant.',
              confirmButtonColor: '#dc3545'
            });
          } else {
            alert((d && (d.message || d.error)) || 'Failed to endorse applicant.');
          }
        }
      })
      .catch(function (err) {
        if (typeof Swal !== 'undefined') {
          Swal.fire({
            icon: 'error',
            title: 'Network Error',
            text: 'Request failed: ' + (err.message || 'network error'),
            confirmButtonColor: '#dc3545'
          });
        } else {
          alert('Request failed: ' + (err.message || 'network error'));
        }
      });
  };

  if (typeof Swal !== 'undefined') {
    Swal.fire({
      title: 'Endorse Applicant?',
      text: 'Are you sure you want to endorse this applicant to the client?',
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#0056b3',
      cancelButtonColor: '#6c757d',
      confirmButtonText: 'Yes, Endorse'
    }).then(function (result) {
      if (result.isConfirmed) {
        doEndorse();
      }
    });
  } else {
    if (!confirm('Endorse this applicant to the client?')) return;
    doEndorse();
  }
}

function simulateClientApproval(appId) {
  if (!confirm('Simulate client company approval for this endorsed applicant?')) return;
  fetch(API_BASE + 'simulate_client_approval.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ application_id: appId }),
    credentials: 'same-origin'
  })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (data.success) {
        alert(data.message || 'Client approval recorded.');
        openReview(appId);
        loadApplications();
      } else {
        alert(data.message || 'Failed to simulate client approval.');
      }
    });
}

// ---- Deployments ----
function initDeployments() {
  document.getElementById('btnAddDeployment').addEventListener('click', function () {
    loadDeploymentSelects();
    document.getElementById('addDeploymentForm').reset();
    document.getElementById('addDeploymentAlert').classList.add('d-none');
    new bootstrap.Modal(document.getElementById('addDeploymentModal')).show();
  });

  document.getElementById('btnSubmitDeployment').addEventListener('click', function () {
    const form = document.getElementById('addDeploymentForm');
    const fd = new FormData(form);
    fd.append('action', 'create');
    const alert = document.getElementById('addDeploymentAlert');
    fetch(DEPLOYMENTS_API, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d.success) {
          safeModalHide(document.getElementById('addDeploymentModal'));
          form.reset();
          loadDeployments();
          alert.classList.add('d-none');
        } else {
          showAlert(alert, d.message || 'Error');
        }
      })
      .catch(function () { showAlert(alert, 'Request failed'); });
  });
}

function loadDeploymentSelects() {
  Promise.all([
    fetch(DEPLOYMENTS_API + '?list=employees', { credentials: 'same-origin' }).then(function (r) { return r.json(); }),
    fetch(DEPLOYMENTS_API + '?list=companies', { credentials: 'same-origin' }).then(function (r) { return r.json(); }),
    fetch(DEPLOYMENTS_API + '?list=team_leads', { credentials: 'same-origin' }).then(function (r) { return r.json(); })
  ]).then(function (results) {
    var emp = results[0], comp = results[1], tl = results[2];
    var selEmp = document.getElementById('selEmployee');
    selEmp.innerHTML = '<option value="">Select employee</option>' + (emp.employees || []).map(function (e) {
      return '<option value="' + e.EmployeeID + '">' + esc((e.LastName || '') + ', ' + (e.FirstName || '')) + ' (' + (e.EmployeeNumber || '-') + ')</option>';
    }).join('');
    var selComp = document.getElementById('selCompany');
    selComp.innerHTML = '<option value="">Select company</option>' + (comp.companies || []).map(function (c) {
      return '<option value="' + c.CompanyID + '">' + esc(c.Name) + '</option>';
    }).join('');
    var selTL = document.getElementById('selTeamLead');
    selTL.innerHTML = '<option value="">None</option>' + (tl.team_leads || []).map(function (t) {
      return '<option value="' + t.UsersID + '">' + esc(t.FullName) + '</option>';
    }).join('');
  });
}

function loadDeployments() {
  fetch(DEPLOYMENTS_API, { credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (d) {
      const tbody = document.getElementById('deploymentsBody');
      if (!tbody) return;
      if (!d.success || !d.deployments || d.deployments.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No deployments yet.</td></tr>';
        return;
      }
      var today = new Date().toISOString().slice(0, 10);
      tbody.innerHTML = d.deployments.map(function (dep) {
        var endBtn = (!dep.end_date || dep.end_date >= today) ? '<button class="btn btn-sm btn-outline-danger btn-end-dep" data-id="' + dep.deployment_id + '">End</button>' : '–';
        return '<tr><td><strong>' + esc(dep.employee_name) + '</strong><br><small class="text-muted">' + esc(dep.employee_number) + '</small></td><td>' + esc(dep.company_name) + '</td><td>' + esc(dep.position) + '</td><td>' + (esc(dep.location) || '–') + '</td><td>' + (esc(dep.team_lead_name) || '–') + '</td><td>' + (dep.start_date || '–') + '</td><td>' + (dep.end_date || 'Active') + '</td><td>' + endBtn + '</td></tr>';
      }).join('');

      tbody.querySelectorAll('.btn-end-dep').forEach(function (btn) {
        btn.addEventListener('click', function () {
          endDeployment(parseInt(this.getAttribute('data-id'), 10));
        });
      });
    })
    .catch(function () {
      const tbody = document.getElementById('deploymentsBody');
      if (tbody) tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-4">Failed to load.</td></tr>';
    });
}

function endDeployment(id) {
  var end = prompt('End date (YYYY-MM-DD):', new Date().toISOString().slice(0, 10));
  if (!end) return;
  var fd = new FormData();
  fd.append('action', 'end');
  fd.append('deployment_id', id);
  fd.append('end_date', end);
  fetch(DEPLOYMENTS_API, { method: 'POST', body: fd, credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (d) {
      if (d.success) loadDeployments();
      else alert(d.message || 'Error');
    });
}

// ---- Helpers ----
function showAlert(el, msg, type) {
  if (!el) return;
  el.className = 'alert alert-' + (type || 'danger');
  el.textContent = msg;
  el.classList.remove('d-none');
}

function esc(s) {
  if (s == null) return '';
  var d = document.createElement('div');
  d.textContent = s;
  return d.innerHTML;
}

function escapeHtml(text) {
  if (text == null) return '';
  var div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}