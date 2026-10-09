/**
 * Admin Dashboard - Phase 1 (Client Partnership)
 * Consumes client_companies.php, service_agreements.php, and admin_dashboard.php
 */

const API = {
  clients: '../../Api/Admin/client_companies.php',
  agreements: '../../Api/Admin/service_agreements.php',
  industries: '../../Api/Admin/industries.php',
  logout: '../../Api/Login/logout.php',
  realtimeCounters: '../../Api/Common/realtime_counters.php'
};
const COMMAND_CENTER_API = '../../Api/Admin/admin_dashboard.php';

// Root cause fix (reported: "can't add client" -> console showed 401s on
// client_companies.php/service_agreements.php): the session's 15-minute
// idle timeout (Api/Common/session_init.php) can expire while a long form
// like Add Client (company + Team Lead account + signed agreement upload)
// is being filled out. The submit handlers below only ever read
// `data.message`, but the 401 response body uses `error`, not `message` —
// so an expired session silently fell through to a generic "Failed to add
// client." with no indication the user needs to log back in.
// This helper detects 401/403 before parsing the body, tells the user
// plainly what happened, and sends them to the login page.
//
// Root cause fix (reported: "Stay Signed In" button still logs the Admin
// out a few seconds later): this used to redirect immediately on ANY 401,
// with no confirmation. Background polling (realtime counters, etc.) runs
// on its own schedule, uncoordinated with the session-timeout warning
// modal's heartbeat (Js/Shared/role_guard.js) — so a poll sent just before
// a successful "Stay Signed In" click could still land a stale 401 a moment
// afterward, and this function would force a logout anyway, overriding the
// session the user had just extended. It now re-confirms against the actual
// session state (the same check Js/Shared/role_guard.js's own 401-watcher
// uses) before redirecting, so a genuinely expired session still redirects,
// but a stray/stale 401 no longer does.
function handleAuthExpired(res) {
  if (res.status === 401 || res.status === 403) {
    return fetch('../../Api/Login/check_session.php', { credentials: 'include' })
      .then(function (r) { return r.json(); }) // A network/JSON failure is not confirmed session expiry.
      .then(function (data) {
        if (data && data.role) {
          // Session is actually still active (stale/racy 401) — don't treat
          // this as an expiry; let the caller's normal error handling run.
          return Promise.reject(new Error('request_failed'));
        }
        if (window.__onazulSessionControllerReady) {
          return window.__onazulSessionControllerReady.then(function (controller) {
            controller.expire();
            throw new Error('auth_expired');
          });
        }
        Swal.fire({
          icon: 'warning',
          title: 'Session expired',
          text: 'Your session has expired due to inactivity. Please log in again to continue.'
        }).then(function () {
          window.location.href = '../../index.html';
        });
        return Promise.reject(new Error('auth_expired'));
      });
  }
  return res.json();
}

let modalAddClient, modalUploadAgreement, modalAddIndustry, modalEditClient;
let agreementPreviewModal = null;

function moveFocusOutOfModal(modalEl) {
  if (!modalEl || !document.activeElement || !modalEl.contains(document.activeElement)) return;
  document.activeElement.blur();
  if (!document.body.hasAttribute('tabindex')) document.body.setAttribute('tabindex', '-1');
  document.body.focus();
}

function initModalA11y(modalIds) {
  modalIds.forEach(function (id) {
    var el = document.getElementById(id);
    if (!el) return;
    el.addEventListener('hide.bs.modal', function () {
      moveFocusOutOfModal(el);
    });
    el.addEventListener('mousedown', function (e) {
      if (e.target.closest('[data-bs-dismiss="modal"], .btn-close')) {
        moveFocusOutOfModal(el);
      }
    }, true);
  });
}

function formatRelativeTimeAdmin(iso) {
  if (!iso) return '';
  const t = new Date(iso);
  if (Number.isNaN(t.getTime())) return '';
  const diffSec = (Date.now() - t) / 1000;
  if (diffSec < 45) return 'Just now';
  if (diffSec < 3600) return Math.floor(diffSec / 60) + ' mins ago';
  if (diffSec < 86400) return Math.floor(diffSec / 3600) + ' hours ago';
  if (diffSec < 86400 * 2) return '1 day ago';
  return Math.floor(diffSec / 86400) + ' days ago';
}

function loadCommandCenterOverview() {
  const listEl = document.getElementById('ccActivityList');
  const alertsEl = document.getElementById('ccAlertsList');

  fetch(COMMAND_CENTER_API, { credentials: 'include' })
    .then(function (r) { return r.json(); })
    .then(function (d) {
      if (!d || !d.success) return;

      const kp = d.kpis || {};
      function setText(id, val) {
        const el = document.getElementById(id);
        if (el) el.textContent = val;
      }

      // Populate System Overview KPI Cards
      setText('ccKpiClients', kp.activeClients !== undefined ? String(kp.activeClients) : '0');
      setText('ccKpiDeployed', kp.totalDeployedEmployees !== undefined ? String(kp.totalDeployedEmployees) : '0');
      setText('ccKpiPayroll', kp.pendingPayrollApprovals !== undefined ? String(kp.pendingPayrollApprovals) : '0');
      setText('ccKpiQuitclaims', kp.pendingQuitclaims !== undefined ? String(kp.pendingQuitclaims) : '0');

      if (d.user) {
        const cn = document.getElementById('ccUserName');
        const cr = document.getElementById('ccUserRole');
        if (cn && d.user.name) cn.textContent = d.user.name;
        if (cr && d.user.role) cr.textContent = d.user.role;
        const ca = document.getElementById('ccUserAvatar');
        if (ca && d.user.name) {
          const parts = String(d.user.name).trim().split(/\s+/).filter(Boolean);
          const a = parts[0] ? parts[0].charAt(0) : '';
          const b = parts.length > 1 ? parts[parts.length - 1].charAt(0) : '';
          ca.textContent = (a + b).toUpperCase() || 'SA';
        }
      }

      // Populate Recent Activity
      const activities = d.activities || d.recentActivity || [];
      if (listEl) {
        if (!activities.length) {
          listEl.innerHTML = '<li class="text-secondary small py-2">No recent activity in system logs yet.</li>';
        } else {
          listEl.innerHTML = activities.map(function (a) {
            const msg = a.description || a.message || '—';
            const rel = a.timestamp || formatRelativeTimeAdmin(a.created_at) || '';
            return '' +
              '<li class="cc-activity-item">' +
              '  <div class="cc-activity-dot-wrap">' +
              '    <span class="cc-activity-dot"></span>' +
              '    <span class="cc-activity-line"></span>' +
              '  </div>' +
              '  <div class="cc-activity-body">' +
              '    <p class="cc-activity-msg">' + escapeHtml(msg) + '</p>' +
              '    <p class="cc-activity-time">' + escapeHtml(rel) + '</p>' +
              '  </div>' +
              '</li>';
          }).join('');
        }
      }

      // Populate System Alerts
      const alerts = d.alerts || [];
      if (alertsEl) {
        if (!alerts.length) {
          alertsEl.innerHTML = '<p class="text-secondary small mb-0">No active alerts.</p>';
        } else {
          alertsEl.innerHTML = alerts.map(function (a) {
            const v = a.type || a.variant || 'info';
            const box = (v === 'warning' || v === 'warn') ? 'cc-alert cc-alert-warn' : (v === 'notice' || v === 'danger') ? 'cc-alert cc-alert-notice' : 'cc-alert cc-alert-info';
            return '' +
              '<div class="' + box + '">' +
              '  <div class="cc-alert-title">' + escapeHtml(a.title || '') + '</div>' +
              '  <p class="cc-alert-msg mb-0">' + escapeHtml(a.message || '') + '</p>' +
              '</div>';
          }).join('');
        }
      }
    })
    .catch(function (err) {
      console.error("Command center load error:", err);
      if (listEl) listEl.innerHTML = '<li class="text-danger small py-2">Could not load activity.</li>';
      if (alertsEl) alertsEl.innerHTML = '<p class="text-danger small mb-0">Could not load alerts.</p>';
    });
}

function refreshAdminSidebarBadges() {
  if (window.AdminSidebarBadges && typeof AdminSidebarBadges.poll === 'function') {
    return AdminSidebarBadges.poll(API.realtimeCounters);
  }
}

document.addEventListener('DOMContentLoaded', function () {
  initModalA11y(['modalAddClient', 'modalUploadAgreement', 'modalAddIndustry', 'modalEditClient', 'viewClientModal']);
  function createModal(id) {
    const el = document.getElementById(id);
    return el ? new bootstrap.Modal(el) : null;
  }
  modalAddClient = createModal('modalAddClient');
  modalUploadAgreement = createModal('modalUploadAgreement');
  modalAddIndustry = createModal('modalAddIndustry');
  modalEditClient = createModal('modalEditClient');
  const agreementPreviewModalEl = document.getElementById('agreementPreviewModal');
  if (agreementPreviewModalEl) agreementPreviewModal = new bootstrap.Modal(agreementPreviewModalEl);

  initSPA();
  loadCommandCenterOverview();
  if (window.AdminSidebarBadges) {
    AdminSidebarBadges.poll(API.realtimeCounters);
  }
  if (window.OwNotifications) {
    OwNotifications.init({ showSection: window.adminShowSection });
  }
  // 30-day contract-expiry reminder check (employment contracts + service
  // agreements) — run once per dashboard load, same convention as the
  // other on-load checks above. Fire-and-forget: results land as regular
  // notifications for OwNotifications to pick up on its own next poll.
  fetch('../../Api/Admin/check_contract_expirations.php', { credentials: 'include' }).catch(function () {});

  function toggleAgreementEndDateRequirement(typeSelectId, endDateWrapId) {
    var typeSelect = document.getElementById(typeSelectId);
    var endDateWrap = document.getElementById(endDateWrapId);
    if (!typeSelect || !endDateWrap) return;
    var endDateInput = endDateWrap.querySelector('input[type="date"]');
    function sync() {
      var isExpiring = typeSelect.value === 'Expiring';
      endDateWrap.classList.toggle('opacity-50', !isExpiring);
      if (endDateInput) {
        endDateInput.required = isExpiring;
        // Open contracts have no end date — disable the field outright so
        // it can't be typed into or picked at all, not just visually dimmed.
        endDateInput.disabled = !isExpiring;
        if (!isExpiring) endDateInput.value = '';
      }
    }
    typeSelect.addEventListener('change', sync);
    sync();
  }
  toggleAgreementEndDateRequirement('addAgreementContractType', 'addAgreementEndDateWrap');
  toggleAgreementEndDateRequirement('editAgreementContractType', 'editAgreementEndDateWrap');

  loadCompanies();
  loadAgreements();
  loadIndustries('', 'industrySelect');
  loadIndustries('', 'editIndustrySelect');

  const btnAddClientSection = document.getElementById('btnAddClientSection');
  const btnUploadAgreementSection = document.getElementById('btnUploadAgreementSection');
  if (btnAddClientSection) btnAddClientSection.addEventListener('click', openAddClientModal);
  if (btnUploadAgreementSection) btnUploadAgreementSection.addEventListener('click', openUploadAgreementModal);

  const btnSubmitClient = document.getElementById('btnSubmitClient');
  if (btnSubmitClient) btnSubmitClient.addEventListener('click', submitClient);

  const btnSubmitAgreement = document.getElementById('btnSubmitAgreement');
  if (btnSubmitAgreement) btnSubmitAgreement.addEventListener('click', submitAgreement);

  const btnSubmitEditClient = document.getElementById('btnSubmitEditClient');
  if (btnSubmitEditClient) btnSubmitEditClient.addEventListener('click', submitEditClient);

  const btnAddIndustry = document.getElementById('btnAddIndustry');
  if (btnAddIndustry) {
    btnAddIndustry.addEventListener('click', function () {
      const input = document.getElementById('industryNameInput');
      if (input) input.value = '';
      industryEditSelection=null;
      document.querySelector('#modalAddIndustry .modal-title').textContent='Add Industry';
      modalAddIndustry.show();
      setTimeout(function () { if (input) input.focus(); }, 150);
    });
  }
  const btnAddIndustryEdit = document.getElementById('btnAddIndustryEdit');
  if (btnAddIndustryEdit) {
    btnAddIndustryEdit.addEventListener('click', function () {
      const input = document.getElementById('industryNameInput');
      if (input) input.value = '';
      industryEditSelection=null;
      document.querySelector('#modalAddIndustry .modal-title').textContent='Add Industry';
      modalAddIndustry.show();
      setTimeout(function () { if (input) input.focus(); }, 150);
    });
  }

  const btnSaveIndustry = document.getElementById('btnSaveIndustry');
  if (btnSaveIndustry) btnSaveIndustry.addEventListener('click', submitIndustry);

  PhMobile.attach(document.getElementById('addContactNumber'),{profile:true});
  ClientCompaniesUI.attachAccount(document.getElementById('formAddClient'),'addTl');

  const editTlPasswordInput = document.getElementById('editTlPassword');
  if (editTlPasswordInput) editTlPasswordInput.addEventListener('input', updateEditTlPasswordValidationUI);
  const editTlPasswordConfirmInput = document.getElementById('editTlPasswordConfirm');
  if (editTlPasswordConfirmInput) editTlPasswordConfirmInput.addEventListener('input', updateEditTlPasswordValidationUI);

  document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if(btn.dataset.accountToggleBound==='1')return;
      const targetId = btn.getAttribute('data-target');
      const input = targetId ? document.getElementById(targetId) : null;
      const icon = btn.querySelector('i');
      if (!input || !icon) return;
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      icon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
      btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });
  });

  document.addEventListener('click', function (e) {
    const btn = e.target.closest('.btn-view-agreement');
    if (!btn) return;
    e.preventDefault();
    const docPath = btn.getAttribute('data-doc-path') || '';
    const contractNo = btn.getAttribute('data-contract-no') || '';
    openAgreementPreview(docPath, contractNo);
  });

  const logoutBtn = document.getElementById('logoutBtn');
  if (logoutBtn) {
    logoutBtn.addEventListener('click', function (e) {
      e.preventDefault();
      if (typeof showLogoutConfirm === 'function') {
        showLogoutConfirm(function () { logout(); });
      } else {
        logout();
      }
    });
  }

  setupAuditLogHandlers();
  setupListFilterHandlers();

  window.setInterval(function () {
    refreshAdminSidebarBadges();
    loadCompanies();
    loadAgreements();
    const ov = document.getElementById('section-overview');
    if (ov && ov.classList.contains('active')) loadCommandCenterOverview();
  }, 10000);
});

function fetchAuditLogs(page = 1) {
  const tbody = document.getElementById('auditLogsTableBody');
  if (tbody && page === 1) {
    tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm me-2 text-primary" role="status"></div>Loading system audit logs...</td></tr>';
  }

  const search = (document.getElementById('logSearchInput') || {}).value || '';
  const module = (document.getElementById('logModuleFilter') || {}).value || '';
  const action = (document.getElementById('logActionFilter') || {}).value || '';
  const startDate = (document.getElementById('logStartDate') || {}).value || '';
  const endDate = (document.getElementById('logEndDate') || {}).value || '';

  const query = new URLSearchParams({ page, search, module, action, start_date: startDate, end_date: endDate });

  fetch('../../Api/Admin/get_system_logs.php?' + query.toString(), { credentials: 'include' })
    .then(res => res.json())
    .then(res => {
      if (res && res.success) {
        renderLogsTable(res.data);
        renderLogsPagination(res.pagination || { page: 1, pages: 1, total: 0 });
        // ST-402: keep the currently-loaded page cached for PDF export.
        window.lastAuditLogsData = res.data || [];
      } else {
        if (tbody) {
          tbody.innerHTML = '<tr><td colspan="9" class="text-center text-danger py-4">' + escapeHtml(res.message || 'Unauthorized access.') + '</td></tr>';
        }
      }
    })
    .catch(err => {
      console.error("Audit log fetch error:", err);
      if (tbody) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center text-danger py-4">Network error or session expired while loading system logs. Please ensure you are logged in as an Administrator.</td></tr>';
      }
    });
}

function renderLogsTable(logs) {
  const tbody = document.getElementById('auditLogsTableBody');
  if (!tbody) return;
  if (!logs || logs.length === 0) {
    tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">No system logs found matching criteria.</td></tr>';
    return;
  }

  tbody.innerHTML = logs.map(log => `
    <tr>
      <td><small>${escapeHtml(log.formatted_time)}</small></td>
      <td><strong>${escapeHtml(log.username)}</strong></td>
      <td><span class="badge bg-secondary">${escapeHtml(log.role)}</span></td>
      <td><span class="badge bg-info text-dark">${escapeHtml(log.module)}</span></td>
      <td><span class="badge bg-primary">${escapeHtml(log.action)}</span></td>
      <td>${escapeHtml(log.description)}</td>
      <td><code>${escapeHtml(log.reference_id || '-')}</code></td>
      <td><small>${escapeHtml(log.ip_address)}</small></td>
      <td><small>${escapeHtml(log.operating_system || '-')} / ${escapeHtml(log.browser || '-')}</small></td>
    </tr>
  `).join('');
}

function renderLogsPagination(p) {
  const container = document.getElementById('logsPagination');
  if (!container) return;
  container.innerHTML = `
    <span>Page <strong>${p.page}</strong> of <strong>${p.pages}</strong> (${p.total} total records)</span>
    <div>
      <button class="btn btn-sm btn-outline-primary me-1" ${p.page <= 1 ? 'disabled' : ''} onclick="fetchAuditLogs(${p.page - 1})">Previous</button>
      <button class="btn btn-sm btn-outline-primary" ${p.page >= p.pages ? 'disabled' : ''} onclick="fetchAuditLogs(${p.page + 1})">Next</button>
    </div>
  `;
}

// ST-500: search input is debounced (300ms) so it doesn't fire a request
// per keystroke; status filters (a <select>) apply immediately on change.
// Both reset their list back to page 1, per the requirement that a new
// search/filter always starts from page 1. Clearing the search box fires
// the same debounced 'input' handler with an empty value, which is exactly
// the "no filter" query — restoring the full list.
function debounce(fn, delayMs) {
  var t = null;
  return function () {
    var args = arguments;
    clearTimeout(t);
    t = setTimeout(function () { fn.apply(null, args); }, delayMs);
  };
}

function setupListFilterHandlers() {
  var clientsSearch = document.getElementById('clientsSearchInput');
  var clientsStatus = document.getElementById('clientsStatusFilter');
  if (clientsSearch) clientsSearch.addEventListener('input', debounce(function () { loadCompanies(1); }, 300));
  if (clientsStatus) clientsStatus.addEventListener('change', function () { loadCompanies(1); });

  var agreementsSearch = document.getElementById('agreementsSearchInput');
  var agreementsStatus = document.getElementById('agreementsStatusFilter');
  if (agreementsSearch) agreementsSearch.addEventListener('input', debounce(function () { loadAgreements(1); }, 300));
  if (agreementsStatus) agreementsStatus.addEventListener('change', function () { loadAgreements(1); });
}

function setupAuditLogHandlers() {
  const filterBtn = document.getElementById('btnFilterLogs');
  if (filterBtn) filterBtn.addEventListener('click', function() { fetchAuditLogs(1); });

  const exportBtn = document.getElementById('btnExportAuditLogs');
  if (exportBtn) {
    exportBtn.addEventListener('click', function() {
      const query = new URLSearchParams({
        search: (document.getElementById('logSearchInput') || {}).value || '',
        module: (document.getElementById('logModuleFilter') || {}).value || '',
        action: (document.getElementById('logActionFilter') || {}).value || '',
        start_date: (document.getElementById('logStartDate') || {}).value || '',
        end_date: (document.getElementById('logEndDate') || {}).value || ''
      });
      window.location.href = '../../Api/Admin/export_system_logs.php?' + query.toString();
    });
  }

  // ST-402: PDF export via the browser's own print-to-PDF capability —
  // no server-side PDF library is used. Builds a clean, print-only table
  // from the currently loaded audit log rows, then opens the print dialog;
  // the user selects "Save as PDF" as the destination.
  const exportPdfBtn = document.getElementById('btnExportAuditLogsPDF');
  if (exportPdfBtn) {
    exportPdfBtn.addEventListener('click', exportAuditLogsToPDF);
  }
}

function exportAuditLogsToPDF() {
  const container = document.getElementById('printableAuditLogs');
  if (!container) return;
  const rows = window.lastAuditLogsData || [];
  const rowsHtml = rows.map(function (log) {
    return '<tr>' +
      '<td>' + escapeHtml(log.formatted_time) + '</td>' +
      '<td>' + escapeHtml(log.username) + '</td>' +
      '<td>' + escapeHtml(log.role) + '</td>' +
      '<td>' + escapeHtml(log.module) + '</td>' +
      '<td>' + escapeHtml(log.action) + '</td>' +
      '<td>' + escapeHtml(log.description) + '</td>' +
      '<td>' + escapeHtml(log.reference_id || '-') + '</td>' +
      '<td>' + escapeHtml(log.ip_address) + '</td>' +
      '</tr>';
  }).join('');

  container.innerHTML =
    '<h3>ONAZUL HRIS &mdash; System Audit Logs</h3>' +
    '<p>Exported: ' + escapeHtml(new Date().toLocaleString()) + '</p>' +
    '<table><thead><tr>' +
    '<th>Timestamp</th><th>User</th><th>Role</th><th>Module</th><th>Action</th><th>Description</th><th>Ref ID</th><th>IP Address</th>' +
    '</tr></thead><tbody>' +
    (rowsHtml || '<tr><td colspan="8">No records to export.</td></tr>') +
    '</tbody></table>';

  window.print();
}

function loadIndustries(selectedName, selectElId) {
  const sel = document.getElementById(selectElId || 'industrySelect');
  if (!sel) return;
  sel.innerHTML = '<option value="">Loading…</option>';
  return fetch(API.industries, { credentials: 'include' })
    .then(r => r.json())
    .then(d => {
      if (!d.success) {
        sel.innerHTML = '<option value="">(Run industry migration)</option>';
        return;
      }
      const items = d.industries || [];
      sel.innerHTML = '<option value="">Select industry</option>' + items.map(i =>
        '<option data-industry-id="'+i.id+'" data-edit-token="'+escapeHtml(i.edit_token||'')+'" value="' + escapeHtml(i.name) + '">' + escapeHtml(i.name) + '</option>'
      ).join('');
      if (selectedName) sel.value = selectedName;
      sel.dispatchEvent(new Event('change'));
    })
    .catch(() => {
      sel.innerHTML = '<option value="">Failed to load</option>';
    });
}

var industryEditSelection=null;
function openIndustryEditor(selectId) {
 const select=document.getElementById(selectId),option=select.selectedOptions[0];
 if(!option || !option.dataset.industryId)return;
 industryEditSelection={id:option.dataset.industryId,token:option.dataset.editToken};
 document.getElementById('industryNameInput').value=option.value;
 document.querySelector('#modalAddIndustry .modal-title').textContent='Edit Industry';
 modalAddIndustry.show();
}
function submitIndustry() {
  const input = document.getElementById('industryNameInput');
  if(input && !input.reportValidity())return;
  const name = input ? input.value.trim() : '';
  if (!name) {
    Swal.fire({ icon: 'warning', title: 'Required', text: 'Industry name is required.' });
    return;
  }
  const fd = new FormData();
  fd.append('action',industryEditSelection?'update':'create');
  if(industryEditSelection){fd.append('industry_id',industryEditSelection.id);fd.append('edit_token',industryEditSelection.token);}
  fd.append('name', name);
  ClientCompaniesUI.request(API.industries, { method: 'POST', body: fd, credentials: 'include' })
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        moveFocusOutOfModal(document.getElementById('modalAddIndustry'));
        modalAddIndustry.hide();
        loadIndustries(name, 'industrySelect');
        loadIndustries(name, 'editIndustrySelect');
        loadCompanies();
        Swal.fire({ icon: 'success', title: 'Saved', text: d.message || 'Industry added.' });
      } else {
        Swal.fire({ icon: 'error', title: 'Error', text: d.message || 'Failed to add industry.' });
      }
    })
    .catch(() => Swal.fire({ icon: 'error', title: 'Error', text: 'Request failed.' }));
}

function openAddClientModal() {
  var form = document.getElementById('formAddClient');
  if (form) {
    ['addTlFullName', 'addTlEmail', 'addTlPassword', 'addTlPasswordConfirm', 'addAgreementStartDate', 'addAgreementEndDate', 'addAgreementNotes', 'addAgreementSignedDoc'].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.value = '';
    });
    const contactInput = document.getElementById('addContactNumber');
    if (contactInput) {contactInput.value = '';contactInput.dispatchEvent(new Event('input'));}
    const statusSelect = document.getElementById('addAgreementStatus');
    if (statusSelect) statusSelect.value = 'Active';
    const emailStatus = document.getElementById('addTlEmailCheckStatus');
    if (emailStatus) {
      emailStatus.className = 'text-muted d-block mt-1';
      emailStatus.textContent = 'Must be a valid Gmail address unique across the system.';
    }
    updateTlPasswordValidationUI();
  }
  if (modalAddClient) modalAddClient.show();
}

function resetEditTeamLeadForm() {
  ['editTlFullName', 'editTlEmail', 'editTlPassword', 'editTlPasswordConfirm'].forEach(function (id) {
    const el = document.getElementById(id);
    if (el) el.value = '';
  });
  updateEditTlPasswordValidationUI();
}

function editTeamLeadFormHasInput() {
  const ids = ['editTlFullName', 'editTlEmail', 'editTlPassword', 'editTlPasswordConfirm'];
  return ids.some(function (id) {
    const el = document.getElementById(id);
    return el && String(el.value || '').trim() !== '';
  });
}

function showEditCurrentTeamLead(teamLead) {
  const card = document.getElementById('editCurrentTeamLeadCard');
  const body = document.getElementById('editCurrentTeamLeadBody');
  const title = document.getElementById('editTeamLeadCreateTitle');
  const hint = document.getElementById('editTeamLeadCreateHint');
  if (teamLead && teamLead.UsersID) {
    if (card) card.classList.remove('d-none');
    if (body) {
      body.textContent = (teamLead.FullName || 'Team Lead')
        + (teamLead.Email ? ' — ' + teamLead.Email : '');
    }
    if (title) title.innerHTML = 'Replace Team Lead <span class="text-muted fw-normal">(optional)</span>';
    if (hint) {
      hint.textContent = 'Leave blank to keep the current Team Lead. Fill in the fields below to create a new Team Lead account for this company.';
    }
  } else {
    if (card) card.classList.add('d-none');
    if (body) body.textContent = '';
    if (title) title.innerHTML = 'Team Lead account <span class="text-danger">*</span>';
    if (hint) {
      hint.textContent = 'Creates a Team Lead user linked to this company. Enter a valid Gmail address.';
    }
  }
}

function updateEditTlPasswordValidationUI() {
  const pwdEl = document.getElementById('editTlPassword');
  const confirmEl = document.getElementById('editTlPasswordConfirm');
  const confirmHint = document.getElementById('editTlPasswordConfirmHint');
  const ruleMap = [
    { id: 'editTlRuleLen', ok: (v) => v.length >= 8 },
    { id: 'editTlRuleUpper', ok: (v) => /[A-Z]/.test(v) },
    { id: 'editTlRuleLower', ok: (v) => /[a-z]/.test(v) },
    { id: 'editTlRuleNumber', ok: (v) => /\d/.test(v) },
    { id: 'editTlRuleSpecial', ok: (v) => /[^A-Za-z0-9]/.test(v) }
  ];
  if (!pwdEl || !confirmEl || !confirmHint) return;
  const pwd = String(pwdEl.value || '');
  const confirmPwd = String(confirmEl.value || '');
  const check = validateStrongPassword(pwd);
  pwdEl.classList.remove('is-valid', 'is-invalid');
  confirmEl.classList.remove('is-valid', 'is-invalid');
  ruleMap.forEach(function (rule) {
    const el = document.getElementById(rule.id);
    if (!el) return;
    el.classList.remove('text-muted', 'text-danger', 'text-success');
    if (pwd.length === 0) { el.classList.add('text-muted'); return; }
    el.classList.add(rule.ok(pwd) ? 'text-success' : 'text-danger');
  });
  if (pwd.length > 0) pwdEl.classList.add(check.isValid ? 'is-valid' : 'is-invalid');
  if (confirmPwd.length > 0) {
    if (pwd === confirmPwd) {
      confirmEl.classList.add('is-valid');
      confirmHint.className = 'form-text text-success';
      confirmHint.textContent = 'Passwords match.';
    } else {
      confirmEl.classList.add('is-invalid');
      confirmHint.className = 'form-text text-danger';
      confirmHint.textContent = 'Passwords do not match.';
    }
  } else {
    confirmHint.className = 'form-text';
    confirmHint.textContent = 'Re-enter the same password.';
  }
}

// Client-side mirror of Api/Helpers/password_policy.php::is_common_or_predictable_password().
// This is a UX hint only — the server (validate_strong_password()) is the
// authoritative check for UT-102, including on the direct-API-call path.
function isCommonOrPredictablePassword(password) {
  const commonBases = [
    'admin', 'administrator', 'password', 'welcome', 'qwerty', 'qwertyuiop',
    'letmein', 'changeme', 'test', 'testing', 'user', 'guest', 'login',
    'default', 'onazul', 'company', 'employee', 'hr', 'root', 'iloveyou',
    'monkey', 'dragon', 'football', 'master', 'zxcvbnm', 'asdfgh'
  ];
  const lower = password.toLowerCase();
  const alnum = lower.replace(/[^a-z0-9]/g, '');
  const base = alnum.replace(/\d+$/, '');
  const normalizedBase = base
    .replace(/0/g, 'o').replace(/1/g, 'i').replace(/3/g, 'e')
    .replace(/4/g, 'a').replace(/5/g, 's').replace(/7/g, 't');

  if (commonBases.includes(base) || commonBases.includes(normalizedBase) || commonBases.includes(alnum)) {
    return true;
  }
  if (/0123456789|1234567890|12345678|23456789|87654321|98765432/.test(alnum)) return true;
  if (/qwertyui|asdfghjk|zxcvbnm|qazwsx/.test(alnum)) return true;
  if (/(.)\1{4,}/.test(alnum)) return true;
  return false;
}

function validateStrongPassword(password) {
  const checks = [
    { ok: password.length >= 8, message: 'at least 8 characters' },
    { ok: /[A-Z]/.test(password), message: '1 uppercase letter' },
    { ok: /[a-z]/.test(password), message: '1 lowercase letter' },
    { ok: /\d/.test(password), message: '1 number' },
    { ok: /[^A-Za-z0-9]/.test(password), message: '1 special character' }
  ];
  const failed = checks.filter(c => !c.ok).map(c => c.message);
  const formatOk = failed.length === 0;
  const commonOrPredictable = formatOk && isCommonOrPredictablePassword(password);
  return { isValid: formatOk && !commonOrPredictable, failed, commonOrPredictable };
}

function updateTlPasswordValidationUI(prefix) {
  prefix=typeof prefix==='string'?prefix:'addTl';
  const pwdEl = document.getElementById(prefix+'Password');
  const confirmEl = document.getElementById(prefix+'PasswordConfirm');
  const confirmHint = document.getElementById(prefix+'PasswordConfirmHint');
  const ruleMap = [
    { id: prefix+'RuleLen', ok: (v) => v.length >= 8 },
    { id: prefix+'RuleUpper', ok: (v) => /[A-Z]/.test(v) },
    { id: prefix+'RuleLower', ok: (v) => /[a-z]/.test(v) },
    { id: prefix+'RuleNumber', ok: (v) => /\d/.test(v) },
    { id: prefix+'RuleSpecial', ok: (v) => /[^A-Za-z0-9]/.test(v) }
  ];
  if (!pwdEl || !confirmEl || !confirmHint) return;

  const pwd = String(pwdEl.value || '');
  const confirmPwd = String(confirmEl.value || '');
  const check = validateStrongPassword(pwd);

  pwdEl.classList.remove('is-valid', 'is-invalid');
  confirmEl.classList.remove('is-valid', 'is-invalid');

  ruleMap.forEach((rule) => {
    const el = document.getElementById(rule.id);
    if (!el) return;
    el.classList.remove('text-muted', 'text-danger', 'text-success');
    if (pwd.length === 0) {
      el.classList.add('text-muted');
      return;
    }
    el.classList.add(rule.ok(pwd) ? 'text-success' : 'text-danger');
  });

  if (pwd.length > 0) {
    pwdEl.classList.add(check.isValid ? 'is-valid' : 'is-invalid');
  }

  if (confirmPwd.length > 0) {
    if (pwd === confirmPwd) {
      confirmEl.classList.add('is-valid');
      confirmHint.className = 'form-text text-success';
      confirmHint.textContent = 'Passwords match.';
    } else {
      confirmEl.classList.add('is-invalid');
      confirmHint.className = 'form-text text-danger';
      confirmHint.textContent = 'Passwords do not match.';
    }
  } else {
    confirmHint.className = 'form-text';
    confirmHint.textContent = 'Re-enter the same password.';
  }
}

function resetEditAgreementFields() {
  var ids = ['editAgreementContractNumber', 'editAgreementStartDate', 'editAgreementEndDate', 'editAgreementNotes'];
  ids.forEach(function (id) {
    var el = document.getElementById(id);
    if (el) el.value = '';
  });
  var statusEl = document.getElementById('editAgreementStatus');
  if (statusEl) statusEl.value = 'Active';
  var contractTypeEl = document.getElementById('editAgreementContractType');
  if (contractTypeEl) {
    contractTypeEl.value = 'Open';
    contractTypeEl.dispatchEvent(new Event('change'));
  }
  var fileEl = document.getElementById('editAgreementSignedDoc');
  if (fileEl) fileEl.value = '';
}

function agreementRecordIsActive(agreement) {
  if (!agreement || agreement.Status !== 'Active') return false;
  var path = String(agreement.SignedDocumentPath || '').trim();
  return path !== '';
}

function loadEditClientAgreements(companyId) {
  var banner = document.getElementById('editAgreementNeedsBanner');
  var current = document.getElementById('editAgreementCurrent');
  var currentBody = document.getElementById('editAgreementCurrentBody');
  var uploadPanel = document.getElementById('editAgreementUploadPanel');
  var uploadHint = document.getElementById('editAgreementUploadHint');
  resetEditAgreementFields();

  if (!companyId) {
    if (banner) banner.classList.add('d-none');
    if (current) current.classList.add('d-none');
    return Promise.resolve();
  }

  return fetch(API.agreements + '?company_id=' + encodeURIComponent(companyId), { credentials: 'include' })
    .then(function (r) { return r.json(); })
    .then(function (d) {
      var list = (d && d.success && d.agreements) ? d.agreements : [];
      var active = list.find(agreementRecordIsActive) || null;

      if (active && current && currentBody) {
        if (banner) banner.classList.add('d-none');
        current.classList.remove('d-none');
        var docBtn = active.SignedDocumentPath
          ? '<button type="button" class="btn btn-sm btn-outline-primary btn-view-agreement" data-doc-path="' + escapeHtml(active.SignedDocumentPath) + '" data-contract-no="' + escapeHtml(active.ContractNumber || '') + '">View document</button>'
          : '';
        currentBody.innerHTML = '<div class="d-flex flex-wrap align-items-center justify-content-between gap-2">'
          + '<div><span class="badge bg-success me-1">Active</span> '
          + '<strong>' + escapeHtml(active.ContractNumber || 'Agreement') + '</strong>'
          + ' <span class="text-muted">(' + escapeHtml(active.ContractStartDate || '–')
          + (active.ContractEndDate ? ' to ' + escapeHtml(active.ContractEndDate) : '') + ')</span></div>'
          + docBtn
          + '</div>';
        if (uploadHint) uploadHint.textContent = 'Upload a new agreement to replace or add another contract.';
      } else {
        if (banner) banner.classList.remove('d-none');
        if (current) current.classList.add('d-none');
        if (currentBody) currentBody.innerHTML = '';
        if (uploadHint) uploadHint.textContent = 'Upload the signed service agreement for this company.';
      }
      if (uploadPanel) uploadPanel.classList.remove('d-none');
    })
    .catch(function () {
      if (banner) banner.classList.remove('d-none');
      if (current) current.classList.add('d-none');
    });
}

function hasEditAgreementUploadPayload() {
  var contractNumber = (document.getElementById('editAgreementContractNumber') || {}).value;
  var startDate = (document.getElementById('editAgreementStartDate') || {}).value;
  var fileInput = document.getElementById('editAgreementSignedDoc');
  var hasFile = fileInput && fileInput.files && fileInput.files.length > 0;
  return hasFile || String(contractNumber || '').trim() !== '' || String(startDate || '').trim() !== '';
}

function buildEditAgreementFormData(companyId) {
  var contractNumber = String((document.getElementById('editAgreementContractNumber') || {}).value || '').trim();
  var startDate = String((document.getElementById('editAgreementStartDate') || {}).value || '').trim();
  var endDate = String((document.getElementById('editAgreementEndDate') || {}).value || '').trim();
  var status = String((document.getElementById('editAgreementStatus') || {}).value || 'Active').trim();
  var contractType = String((document.getElementById('editAgreementContractType') || {}).value || 'Open').trim();
  var notes = String((document.getElementById('editAgreementNotes') || {}).value || '').trim();
  var fileInput = document.getElementById('editAgreementSignedDoc');

  if (!hasEditAgreementUploadPayload()) return null;

  if (!contractNumber || !startDate) {
    return { error: 'Contract number and start date are required to upload a service agreement.' };
  }
  if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
    return { error: 'Please select the signed service agreement document (PDF, JPG, or PNG).' };
  }

  var fd = new FormData();
  fd.append('action', 'create');
  fd.append('client_company_id', String(companyId));
  fd.append('contract_number', contractNumber);
  fd.append('contract_start_date', startDate);
  fd.append('contract_end_date', endDate);
  fd.append('status', status || 'Active');
  fd.append('contract_type', contractType || 'Open');
  fd.append('notes', notes);
  fd.append('signed_document', fileInput.files[0]);
  return fd;
}

function uploadEditClientAgreement(companyId) {
  var payload = buildEditAgreementFormData(companyId);
  if (!payload) return Promise.resolve({ skipped: true });
  if (payload.error) return Promise.resolve({ error: payload.error });

  return fetch(API.agreements, { method: 'POST', body: payload, credentials: 'include' })
    .then(function (res) { return res.json(); })
    .then(function (data) {
      if (!data || !data.success) {
        return { error: (data && data.message) ? data.message : 'Failed to upload service agreement.' };
      }
      return { success: true, message: data.message || 'Service Agreement uploaded.', response: data };
    })
    .catch(function () {
      return { error: 'Failed to upload service agreement.' };
    });
}

function openEditClientModal(companyId) {
  if (!companyId) return;
  const form = document.getElementById('formEditClient');
  const nameInput = document.getElementById('editCompanyName');
  const addressInput = document.getElementById('editAddress');
  const contactNumberInput = document.getElementById('editContactNumber');
  const hiddenId = document.getElementById('editCompanyId');
  if (!form || !hiddenId) return;

  hiddenId.value = companyId;

  var company = null;
  fetch(API.clients + '?company_id=' + encodeURIComponent(companyId), { credentials: 'include' })
    .then(r => r.json())
    .then(d => {
      if (!d.success || !d.company) {
        Swal.fire({ icon: 'error', title: 'Error', text: d.message || 'Client company not found.' });
        return;
      }
      company = d.company;

      if (nameInput) nameInput.value = company.Name || '';
      if (addressInput) addressInput.value = company.Address || '';
      if (contactNumberInput) contactNumberInput.value = company.ContactNumber || '';
      var payrollTypeSelect = document.getElementById('editPayrollType');
      if (payrollTypeSelect) payrollTypeSelect.value = (company.PayrollType === 'A') ? 'A' : 'B';

      resetEditTeamLeadForm();
      showEditCurrentTeamLead(d.team_lead || null);
      return fetch(API.clients + '?list=team_leads&company_id=' + encodeURIComponent(companyId), {credentials:'include'})
        .then(r => r.json()).then(function (leads) {
          if (!leads.success) throw new Error('Unable to load Team Leads');
          const select=document.getElementById('editAssignedTeamLead');
          select.innerHTML='<option value="">Keep current / create new account below</option>';
          (leads.team_leads||[]).forEach(function (lead) {
            const option=new Option(lead.FullName+' — '+lead.Email,lead.UsersID);
            option.dataset.contactNumber=lead.ContactNumber||'';
            select.add(option);
          });
          select.value=company.UserID ? String(company.UserID) : '';
          function toggleLeadFields(){
            ['editTlFullName','editTlEmail','editTlPassword','editTlPasswordConfirm'].forEach(function(id){const input=document.getElementById(id);if(input)input.disabled=Boolean(select.value);});
          }
          select.onchange=function(){
            if(select.value)resetEditTeamLeadForm();
            toggleLeadFields();
            if(select.value && contactNumberInput){contactNumberInput.value=select.options[select.selectedIndex].dataset.contactNumber||'+63';}
          };
          toggleLeadFields();
          return loadIndustries(company.Industry || '', 'editIndustrySelect');
        }).then(function () {
        return loadEditClientAgreements(companyId);
      });
    })
    .then(function () {
      if (company) modalEditClient.show();
    })
    .catch(function () {
      Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to load client details.' });
    });
}

function openUploadAgreementModal() {
  populateClientSelect();
  if (modalUploadAgreement) modalUploadAgreement.show();
}

/** SPA: one main panel at a time; tables live only under their sidebar feature */
function initSPA() {
  const mainContent = document.getElementById('main-content-area') || document.getElementById('main-content');
  const sections = mainContent ? mainContent.querySelectorAll('.dashboard-section') : document.querySelectorAll('.dashboard-section');
  const sidebarLinks = document.querySelectorAll('#main-sidebar .sidebar-link[data-target], #sidebar .sidebar-link[data-target]');
  const headerTitle = document.querySelector('.cc-header-title');

  function closeUserDropdown() {
    var toggle = document.getElementById('userDropdownToggle');
    if (!toggle || typeof bootstrap === 'undefined' || !bootstrap.Dropdown) return;
    var dd = bootstrap.Dropdown.getInstance(toggle);
    if (dd) dd.hide();
  }

  function updateHeaderTitle(targetId) {
    if (!headerTitle) return;
    if (targetId === 'section-profile') {
      headerTitle.textContent = 'My Account';
    } else if (targetId === 'notifications-section') {
      headerTitle.textContent = 'Notifications';
    } else if (targetId === 'section-settings') {
      headerTitle.textContent = 'System Settings';
    } else if (targetId === 'section-deduction-settings') {
      headerTitle.textContent = 'Deduction Settings';
    } else if (targetId === 'section-job-categories') {
      headerTitle.textContent = 'Job Categories';
    } else if (targetId === 'section-holidays') {
      headerTitle.textContent = 'Holidays';
    } else if (targetId === 'section-notifications') {
      headerTitle.textContent = 'Notifications';
    } else {
      headerTitle.textContent = 'Command Center';
    }
  }

  function showSection(targetId, opts) {
    const o = opts || {};
    const skipHash = !!o.skipHash;
    if (!targetId) return;

    sections.forEach(function (section) {
      if (section.id === targetId) {
        section.classList.remove('d-none');
        section.classList.add('active');
        if (targetId === 'section-clients') loadCompanies();
        else if (targetId === 'section-agreements') loadAgreements();
        else if (targetId === 'section-job-orders') {
          if (typeof loadJobOrders === 'function') loadJobOrders();
          if (typeof loadJobOrderRequests === 'function') loadJobOrderRequests();
        } else if (targetId === 'section-applicant-review' && typeof loadApplications === 'function') {
          loadApplications();
        } else if (targetId === 'section-deployments' && typeof loadDeployments === 'function') {
          loadDeployments();
        } else if (targetId === 'section-overview') {
          loadCommandCenterOverview();
        } else if (targetId === 'section-logs') {
          if (typeof fetchAuditLogs === 'function') fetchAuditLogs(1);
        } else if (targetId === 'section-profile') {
          if (window.UserProfileHydrate && typeof UserProfileHydrate.load === 'function') {
            UserProfileHydrate.load();
          }
        } else if (targetId === 'notifications-section') {
          if (window.OwNotifications) {
            OwNotifications.initPanel();
            OwNotifications.load();
          }
        } else if (targetId === 'section-deduction-settings' && window.DeductionSettings && typeof window.DeductionSettings.load === 'function') {
          window.DeductionSettings.load();
        } else if (targetId === 'section-job-categories' && window.JobCategoriesAdmin && typeof window.JobCategoriesAdmin.load === 'function') {
          window.JobCategoriesAdmin.load();
        } else if (targetId === 'section-holidays' && window.HolidaysAdmin && typeof window.HolidaysAdmin.load === 'function') {
          window.HolidaysAdmin.load();
        } else if (targetId === 'section-resignations' && window.EmployeeExit) {
          window.EmployeeExit.load();
        } else if (targetId === 'section-settings' && window.AgencyContactSettings && typeof window.AgencyContactSettings.load === 'function') {
          window.AgencyContactSettings.load();
        }
      } else {
        section.classList.add('d-none');
        section.classList.remove('active');
      }
    });

    sidebarLinks.forEach(function (l) {
      var linkTarget = l.getAttribute('data-target');
      var isProfileOrNotif = targetId === 'section-profile' || targetId === 'notifications-section';
      l.classList.toggle('active', linkTarget === targetId && !isProfileOrNotif);
    });

    updateHeaderTitle(targetId);

    if (!skipHash && history.replaceState) {
      try {
        history.replaceState(null, '', '#' + targetId);
      } catch (e) {}
    }
  }

  window.adminShowSection = showSection;

  sidebarLinks.forEach(function (link) {
    link.addEventListener('click', function (e) {
      e.preventDefault();
      const targetId = link.getAttribute('data-target');
      showSection(targetId);
    });
  });

  document.querySelectorAll('[data-show-section]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      e.preventDefault();
      var targetId = el.getAttribute('data-show-section');
      if (!targetId) return;
      showSection(targetId);
      closeUserDropdown();
    });
  });

  const hash = (window.location.hash || '').replace(/^#/, '');
  if (hash) {
    const exists = Array.prototype.some.call(sections, function (s) { return s.id === hash; });
    if (exists) showSection(hash, { skipHash: true });
  }

  window.addEventListener('hashchange', function () {
    const h = (window.location.hash || '').replace(/^#/, '');
    if (!h) return;
    const exists = Array.prototype.some.call(sections, function (s) { return s.id === h; });
    if (exists) showSection(h, { skipHash: true });
  });
}

function logout() {
  fetch(API.logout, { method: 'GET', credentials: 'include' })
    .then(() => { window.location.href = '/Employee_Tracking/index.html'; })
    .catch(() => { window.location.href = '/Employee_Tracking/index.html'; });
}

function companyHasActiveAgreement(company) {
  return company && (company.HasActiveAgreement == 1 || company.HasActiveAgreement === true);
}

function companyDisplayStatus(company) {
  return companyHasActiveAgreement(company) ? 'Active' : 'Pending';
}

function companyStatusBadgeClass(company) {
  return companyHasActiveAgreement(company) ? 'bg-success' : 'bg-warning';
}

function renderCompanyNameCell(company) {
  var name = escapeHtml((company && company.Name) ? company.Name : '–');
  if (companyHasActiveAgreement(company)) {
    return '<span class="company-name-cell">' + name + '</span>';
  }
  return '<span class="company-name-cell" title="Service agreement required — upload a signed agreement">'
    + '<span class="agreement-needed-dot" aria-hidden="true"></span>'
    + '<span>' + name + '</span>'
    + '<span class="badge bg-danger-subtle text-danger border border-danger-subtle ms-1 d-none d-md-inline" style="font-size:0.65rem;">Needs agreement</span>'
    + '</span>';
}

// ST-500: search + status filter + pagination state for the Client
// Companies list. Server-side (client_companies.php now accepts
// search/status/page/limit) since the same table already supports
// server-side search — pagination and the status filter extend that same
// query rather than adding separate client-side logic.
var companiesListState = { page: 1, search: '', status: '' };

function loadCompanies(page) {
  if (typeof page === 'number') companiesListState.page = page;

  var searchInput = document.getElementById('clientsSearchInput');
  var statusSelect = document.getElementById('clientsStatusFilter');
  companiesListState.search = searchInput ? searchInput.value.trim() : '';
  companiesListState.status = statusSelect ? statusSelect.value : '';

  var query = new URLSearchParams({
    search: companiesListState.search,
    status: companiesListState.status,
    page: companiesListState.page,
    limit: 10
  });

  fetch(API.clients + '?' + query.toString(), { credentials: 'include' })
    .then(res => res.json())
    .then(data => {
      const rowHtml = function () {
        if (data.error) {
          return '<tr><td colspan="7" class="text-center text-danger py-4">' + (data.error || 'Unauthorized') + '. <a href="../../index.html">Login</a></td></tr>';
        }
        if (!data.success || !data.companies || data.companies.length === 0) {
          const kpi = document.getElementById('kpiClients');
          if (kpi && !companiesListState.search && !companiesListState.status) kpi.textContent = '0';
          return '<tr><td colspan="7" class="text-center text-muted py-4">No client companies found.</td></tr>';
        }
        const kpi = document.getElementById('kpiClients');
        if (kpi && !companiesListState.search && !companiesListState.status && data.pagination) kpi.textContent = data.pagination.total;
        return data.companies.map(c => {
          var needsAgreement = !companyHasActiveAgreement(c);
          var payrollType = (c.PayrollType === 'A') ? 'A' : 'B';
          var payrollBadgeCls = payrollType === 'A' ? 'bg-primary-subtle text-primary border border-primary-subtle' : 'bg-info-subtle text-info-emphasis border border-info-subtle';
          return '<tr' + (needsAgreement ? ' class="needs-agreement-row"' : '') + '>'
            + '<td>' + renderCompanyNameCell(c) + '</td>'
            + '<td>' + escapeHtml(c.ContactPerson || '–') + '</td>'
            + '<td>' + escapeHtml(c.ContactNumber || '–') + '</td>'
            + '<td>' + escapeHtml(c.Industry || '–') + '</td>'
            + '<td><span class="badge ' + payrollBadgeCls + '">Type ' + payrollType + '</span></td>'
            + '<td><span class="badge ' + companyStatusBadgeClass(c) + '">' + companyDisplayStatus(c) + '</span></td>'
            + '<td><div class="d-flex gap-1">'
            + '<button type="button" class="btn btn-sm btn-outline-info" onclick="openViewClientModal(' + c.CompanyID + ', \'' + companyDisplayStatus(c) + '\')">'
            + '<i class="bi bi-eye me-1"></i>View</button>'
            + '<button type="button" class="btn btn-sm btn-outline-primary" onclick="openEditClientModal(' + c.CompanyID + ')">'
            + '<i class="bi bi-pencil me-1"></i>Edit</button>'
            + '<button type="button" class="btn btn-sm btn-outline-primary" onclick="manageDeploymentSites(' + c.CompanyID + ')">Sites</button>'
            + '</div></td>'
            + '</tr>';
        }).join('');
      }();
      const el = document.getElementById('companiesTableBody');
      if (el) el.innerHTML = rowHtml;

      if (data.success && data.pagination && typeof renderPaginationControls === 'function') {
        renderPaginationControls('companiesPagination', data.pagination, function (targetPage) {
          loadCompanies(targetPage);
        }, 'companies');
      }
    })
    .catch(err => {
      const errRow = '<tr><td colspan="7" class="text-center text-danger py-4">Failed to load clients.</td></tr>';
      const el = document.getElementById('companiesTableBody');
      if (el) el.innerHTML = errRow;
      console.error(err);
    });
}

// ST-500: search + status filter + pagination state for the main Admin
// Service Agreements list. Server-side, matching client_companies.php's
// pattern above — service_agreements.php's company-scoped lookup (used by
// the client view/edit modal) is untouched; only this no-company-id call
// site is affected.
var agreementsListState = { page: 1, search: '', status: '' };

function loadAgreements(page) {
  if (typeof page === 'number') agreementsListState.page = page;

  var searchInput = document.getElementById('agreementsSearchInput');
  var statusSelect = document.getElementById('agreementsStatusFilter');
  agreementsListState.search = searchInput ? searchInput.value.trim() : '';
  agreementsListState.status = statusSelect ? statusSelect.value : '';

  var query = new URLSearchParams({
    search: agreementsListState.search,
    status: agreementsListState.status,
    page: agreementsListState.page,
    limit: 10
  });

  fetch(API.agreements + '?' + query.toString(), { credentials: 'include' })
    .then(res => res.json())
    .then(data => {
      let rowHtml;
      if (data.error) {
        rowHtml = '<tr><td colspan="6" class="text-center text-danger py-4">' + (data.error || 'Unauthorized') + '.</td></tr>';
      } else if (!data.success || !data.agreements || data.agreements.length === 0) {
        const kpi = document.getElementById('kpiAgreements');
        if (kpi && !agreementsListState.search && !agreementsListState.status) kpi.textContent = '0';
        rowHtml = '<tr><td colspan="6" class="text-center text-muted py-4">No service agreements found.</td></tr>';
      } else {
        const kpi = document.getElementById('kpiAgreements');
        if (kpi && !agreementsListState.search && !agreementsListState.status && data.pagination) kpi.textContent = data.pagination.total;
        rowHtml = data.agreements.map(a => {
          const docLink = a.SignedDocumentPath
            ? '<button type="button" class="btn btn-sm btn-outline-primary btn-view-agreement" data-doc-path="' + escapeHtml(a.SignedDocumentPath) + '" data-contract-no="' + escapeHtml(a.ContractNumber || '') + '">View</button>'
            : '–';
          return '<tr><td>' + escapeHtml(a.ContractNumber || '–') + '</td><td>' + escapeHtml(a.CompanyName || '–') + '</td><td>' + escapeHtml(a.ContractStartDate || '–') + '</td><td>' + escapeHtml(a.ContractEndDate || '–') + '</td><td><span class="badge ' + (a.Status === 'Active' ? 'bg-success' : 'bg-secondary') + '">' + escapeHtml(a.Status || '–') + '</span></td><td>' + docLink + '</td></tr>';
        }).join('');
      }
      const el = document.getElementById('agreementsTableBody');
      if (el) el.innerHTML = rowHtml;

      if (data.success && data.pagination && typeof renderPaginationControls === 'function') {
        renderPaginationControls('agreementsPagination', data.pagination, function (targetPage) {
          loadAgreements(targetPage);
        }, 'agreements');
      }
    })
    .catch(err => {
      const errRow = '<tr><td colspan="6" class="text-center text-danger py-4">Failed to load agreements.</td></tr>';
      const el = document.getElementById('agreementsTableBody');
      if (el) el.innerHTML = errRow;
      console.error(err);
    });
}

function openAgreementPreview(docPath, contractNo) {
  if (!docPath) return;
  const normalized = String(docPath).replace(/^\/+/, '');
  const fileUrl = '../../' + normalized;
  const ext = normalized.split('.').pop().toLowerCase();
  const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'].indexOf(ext) !== -1;
  const isFrameFriendly = isImage || ['pdf'].indexOf(ext) !== -1;

  const titleEl = document.getElementById('agreementPreviewTitle');
  const frameEl = document.getElementById('agreementPreviewFrame');
  const imageEl = document.getElementById('agreementPreviewImage');
  const emptyEl = document.getElementById('agreementPreviewEmpty');
  const downloadEl = document.getElementById('agreementPreviewDownload');
  if (!titleEl || !frameEl || !imageEl || !emptyEl || !downloadEl || !agreementPreviewModal) return;

  titleEl.textContent = 'Service Agreement Preview' + (contractNo ? ' — ' + contractNo : '');
  downloadEl.href = fileUrl;

  frameEl.style.display = 'none';
  imageEl.style.display = 'none';
  emptyEl.classList.add('d-none');
  frameEl.removeAttribute('src');
  imageEl.removeAttribute('src');

  if (isImage) {
    imageEl.src = fileUrl;
    imageEl.style.display = 'block';
  } else if (isFrameFriendly) {
    frameEl.src = fileUrl;
    frameEl.style.display = 'block';
  } else {
    emptyEl.classList.add('d-none');
  }

  agreementPreviewModal.show();
}

function populateClientSelect() {
  const select = document.getElementById('agreementClientSelect');
  select.innerHTML = '<option value="">Select company</option>';
  // ST-500: client_companies.php's list branch is now paginated (10/page)
  // for the main Admin table, but this dropdown needs every company —
  // request the (raised) max page size explicitly rather than page 1 of 10.
  fetch(API.clients + '?limit=1000', { credentials: 'include' })
    .then(res => res.json())
    .then(data => {
      if (data.success && data.companies && data.companies.length > 0) {
        data.companies.forEach(c => {
          const opt = document.createElement('option');
          opt.value = c.CompanyID;
          opt.textContent = c.Name || 'Company #' + c.CompanyID;
          select.appendChild(opt);
        });
      }
    })
    .catch(() => {});
}

function submitClient() {
  const form = document.getElementById('formAddClient');
  if(!form.reportValidity())return;
  const name = form.name.value.trim();
  const contactNumber = (form.contact_number && form.contact_number.value) ? form.contact_number.value.trim() : '';
  const tlName = (form.tl_full_name && form.tl_full_name.value) ? form.tl_full_name.value.trim() : '';
  const tlEmail = (form.tl_email && form.tl_email.value) ? form.tl_email.value.trim() : '';
  const tlPass = (form.tl_password && form.tl_password.value) ? form.tl_password.value : '';
  const tlPass2 = (form.tl_password_confirm && form.tl_password_confirm.value) ? form.tl_password_confirm.value : '';
  
  const agreementStartDate = (form.agreement_start_date && form.agreement_start_date.value) ? form.agreement_start_date.value.trim() : '';
  const agreementEndDate = (form.agreement_end_date && form.agreement_end_date.value) ? form.agreement_end_date.value.trim() : '';
  const agreementStatus = (form.agreement_status && form.agreement_status.value) ? form.agreement_status.value.trim() : 'Active';
  const agreementNotes = (form.agreement_notes && form.agreement_notes.value) ? form.agreement_notes.value.trim() : '';
  const signedDocInput = document.getElementById('addAgreementSignedDoc');

  if (!name) {
    Swal.fire({ icon: 'warning', title: 'Required', text: 'Company name is required.' });
    return;
  }
  if (!contactNumber) {
    Swal.fire({ icon: 'warning', title: 'Required', text: 'Contact number is required.' });
    return;
  }
  const phMobileRegex = PhMobile.profileRule;
  if (!phMobileRegex.test(contactNumber)) {
    Swal.fire({
      icon: 'warning',
      title: 'Invalid Contact Number',
      text: 'Please enter a valid Philippine mobile number.'
    });
    return;
  }
  if (!(form.team_lead_id && form.team_lead_id.value)) {
  if (!tlName || !tlEmail || !tlPass) {
    Swal.fire({ icon: 'warning', title: 'Required', text: 'Team Lead full name, email, and password are required.' });
    return;
  }
  if (ClientCompaniesUI.gmailMessage(tlEmail)) {
    Swal.fire({ icon: 'warning', title: 'Gmail required', text: 'Team Lead email must be a valid Gmail address.' });
    return;
  }
  const pwdCheck = validateStrongPassword(tlPass);
  if (!pwdCheck.isValid) {
    const pwdMsg = pwdCheck.commonOrPredictable
      ? 'Password is too common or predictable. Please choose a stronger password.'
      : 'Password must include ' + pwdCheck.failed.join(', ') + '.';
    Swal.fire({ icon: 'warning', title: 'Password policy', text: pwdMsg });
    return;
  }
  if (tlPass !== tlPass2) {
    Swal.fire({ icon: 'warning', title: 'Password', text: 'Passwords do not match.' });
    return;
  }
  }
  if (!agreementStartDate) {
    Swal.fire({ icon: 'warning', title: 'Required', text: 'Contract Start Date is required.' });
    return;
  }
  if (!signedDocInput || !signedDocInput.files || signedDocInput.files.length === 0) {
    Swal.fire({ icon: 'warning', title: 'Required', text: 'Signed Service Agreement document upload is required.' });
    return;
  }

  const fd = new FormData();
  fd.append('action', 'create');
  fd.append('name', name);
  fd.append('address', form.address.value.trim());
  fd.append('contact_number', contactNumber);
  fd.append('industry', form.industry.value.trim());
  fd.append('payroll_type', (form.payroll_type && form.payroll_type.value) ? form.payroll_type.value : 'B');
  fd.append('team_lead_id',form.team_lead_id ? form.team_lead_id.value : '');
  fd.append('site_name',form.site_name ? form.site_name.value : '');
  fd.append('tl_full_name', tlName);
  fd.append('tl_email', tlEmail);
  fd.append('tl_password', tlPass);
  fd.append('tl_password_confirm', tlPass2);
  fd.append('agreement_start_date', agreementStartDate);
  fd.append('agreement_end_date', agreementEndDate);
  fd.append('agreement_status', agreementStatus);
  fd.append('agreement_contract_type', (form.agreement_contract_type && form.agreement_contract_type.value) ? form.agreement_contract_type.value : 'Open');
  fd.append('agreement_notes', agreementNotes);
  fd.append('signed_document', signedDocInput.files[0]);

  ClientCompaniesUI.request(API.clients, { method: 'POST', body: fd, credentials: 'include' })
    .then(handleAuthExpired)
    .then(data => {
      if (data.success) {
        if (window.OwSystemEvents && data.response && typeof OwSystemEvents.afterUploadResponse === 'function') {
          OwSystemEvents.afterUploadResponse(data.response);
        }
        Swal.fire({ icon: 'success', title: 'Saved', text: data.message || 'Client company and service agreement registered successfully.' });
        if (modalAddClient) modalAddClient.hide();
        form.reset();
        const contactInput = document.getElementById('addContactNumber');
        if (contactInput) contactInput.value = '';
        loadCompanies();
        loadAgreements();
        refreshAdminSidebarBadges();
      } else {
        Swal.fire({ icon: 'error', title: 'Error', text: data.message || data.error || 'Failed to add client.' });
      }
    })
    .catch(err => {
      if (err && err.message === 'auth_expired') return;
      Swal.fire({ icon: 'error', title: 'Error', text: 'Request failed.' });
      console.error(err);
    });
}

function submitEditClient() {
  const form = document.getElementById('formEditClient');
  if (!form) return;

  const companyId = form.company_id.value;
  const name = form.name.value.trim();
  const selectedTeamLead = form.team_lead_id ? form.team_lead_id.value : '';
  const hasNewTl = !selectedTeamLead && editTeamLeadFormHasInput();
  const tlName = (form.tl_full_name && form.tl_full_name.value) ? form.tl_full_name.value.trim() : '';
  const tlEmail = (form.tl_email && form.tl_email.value) ? form.tl_email.value.trim() : '';
  const tlPass = (form.tl_password && form.tl_password.value) ? form.tl_password.value : '';
  const tlPass2 = (form.tl_password_confirm && form.tl_password_confirm.value) ? form.tl_password_confirm.value : '';
  const hasCurrentTl = document.getElementById('editCurrentTeamLeadCard')
    && !document.getElementById('editCurrentTeamLeadCard').classList.contains('d-none');

  if (!companyId) {
    Swal.fire({ icon: 'warning', title: 'Required', text: 'Client company id is missing.' });
    return;
  }
  if (!name) {
    Swal.fire({ icon: 'warning', title: 'Required', text: 'Company name is required.' });
    return;
  }
  if (!hasCurrentTl && !hasNewTl && !selectedTeamLead) {
    Swal.fire({ icon: 'warning', title: 'Required', text: 'Create a Team Lead account below (name, Gmail, and password).' });
    return;
  }
  if (hasNewTl) {
    if (!tlName || !tlEmail || !tlPass) {
      Swal.fire({ icon: 'warning', title: 'Required', text: 'Complete all Team Lead fields when creating a new account.' });
      return;
    }
    if (ClientCompaniesUI.gmailMessage(tlEmail)) {
      Swal.fire({ icon: 'warning', title: 'Gmail required', text: 'Team Lead email must be a valid Gmail address.' });
      return;
    }
    const pwdCheck = validateStrongPassword(tlPass);
    if (!pwdCheck.isValid) {
      const pwdMsg = pwdCheck.commonOrPredictable
        ? 'Password is too common or predictable. Please choose a stronger password.'
        : 'Password must include ' + pwdCheck.failed.join(', ') + '.';
      Swal.fire({ icon: 'warning', title: 'Password policy', text: pwdMsg });
      return;
    }
    if (tlPass !== tlPass2) {
      Swal.fire({ icon: 'warning', title: 'Password', text: 'Passwords do not match.' });
      return;
    }
  }

  const fd = new FormData();
  fd.append('action', 'update');
  if (selectedTeamLead) fd.append('team_lead_id', selectedTeamLead);
  fd.append('company_id', companyId);
  fd.append('name', name);
  fd.append('address', form.address.value.trim());
  fd.append('contact_number', form.contact_number.value.trim());
  fd.append('industry', form.industry.value.trim());
  fd.append('payroll_type', (form.payroll_type && form.payroll_type.value) ? form.payroll_type.value : 'B');
  if (hasNewTl) {
    fd.append('tl_full_name', tlName);
    fd.append('tl_email', tlEmail);
    fd.append('tl_password', tlPass);
    fd.append('tl_password_confirm', tlPass2);
  }

  ClientCompaniesUI.request(API.clients, { method: 'POST', body: fd, credentials: 'include' })
    .then(handleAuthExpired)
    .then(data => {
      if (!data.success) {
        Swal.fire({ icon: 'error', title: 'Error', text: data.message || data.error || 'Failed to update client.' });
        return;
      }
      return uploadEditClientAgreement(companyId).then(function (agResult) {
        if (agResult && agResult.error) {
          Swal.fire({
            icon: 'warning',
            title: 'Company saved',
            text: 'Client details were saved, but the agreement upload failed: ' + agResult.error
          });
        } else if (agResult && agResult.success) {
          if (window.OwSystemEvents && agResult.response && typeof OwSystemEvents.afterUploadResponse === 'function') {
            OwSystemEvents.afterUploadResponse(agResult.response);
          }
          Swal.fire({
            icon: 'success',
            title: 'Saved',
            text: (data.message || 'Client company updated.') + ' ' + (agResult.message || 'Service agreement uploaded.')
          });
        } else {
          Swal.fire({ icon: 'success', title: 'Saved', text: data.message || 'Client company updated successfully.' });
        }
        modalEditClient.hide();
        loadCompanies();
        loadAgreements();
        refreshAdminSidebarBadges();
      });
    })
    .catch(err => {
      if (err && err.message === 'auth_expired') return;
      Swal.fire({ icon: 'error', title: 'Error', text: 'Request failed.' });
      console.error(err);
    });
}

function submitAgreement() {
  const form = document.getElementById('formUploadAgreement');
  const clientId = form.client_company_id.value;
  const contractNumber = form.contract_number.value.trim();
  const startDate = form.contract_start_date.value;
  const fileInput = form.signed_document;

  if (!clientId || !contractNumber || !startDate) {
    Swal.fire({ icon: 'warning', title: 'Required', text: 'Client company, contract number, and start date are required.' });
    return;
  }
  if (!fileInput.files || fileInput.files.length === 0) {
    Swal.fire({ icon: 'warning', title: 'Required', text: 'Please select the signed document (PDF, JPG or PNG).' });
    return;
  }

  const fd = new FormData();
  fd.append('action', 'create');
  fd.append('client_company_id', clientId);
  fd.append('contract_number', contractNumber);
  fd.append('contract_start_date', startDate);
  fd.append('contract_end_date', form.contract_end_date.value);
  fd.append('status', form.status.value || 'Active');
  fd.append('notes', form.notes.value.trim());
  fd.append('signed_document', fileInput.files[0]);

  fetch(API.agreements, { method: 'POST', body: fd, credentials: 'include' })
    .then(handleAuthExpired)
    .then(data => {
      if (data.success) {
        Swal.fire({ icon: 'success', title: 'Saved', text: data.message || 'Service Agreement created successfully.' });
        if (modalUploadAgreement) modalUploadAgreement.hide();
        if (window.OwSystemEvents && typeof OwSystemEvents.afterUploadResponse === 'function') {
          OwSystemEvents.afterUploadResponse(data);
        } else if (typeof triggerServiceAgreementNotification === 'function') {
          triggerServiceAgreementNotification(form.contract_end_date.value, {
            document_name: contractNumber,
            contract_number: contractNumber,
            start_date: startDate,
            contract_start_date: startDate,
            company_name: '',
            service_agreement_id: data.service_agreement_id || 0
          }).catch(function (err) { console.warn('Service agreement notify:', err); });
        }
        form.reset();
        loadAgreements();
        loadCompanies();
        refreshAdminSidebarBadges();
      } else {
        Swal.fire({ icon: 'error', title: 'Error', text: data.message || data.error || 'Failed to create agreement.' });
      }
    })
    .catch(err => {
      if (err && err.message === 'auth_expired') return;
      Swal.fire({ icon: 'error', title: 'Error', text: 'Request failed.' });
      console.error(err);
    });
}

function escapeHtml(str) {
  if (str == null) return '–';
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

function openViewClientModal(companyId, rowStatus) {
  var modalEl = document.getElementById('viewClientModal');
  var bodyEl = document.getElementById('viewClientModalBody');
  if (!modalEl || !bodyEl) return;

  bodyEl.innerHTML = '<div class="text-center text-slate-500 py-6"><div class="spinner-border spinner-border-sm me-2 text-blue-600" role="status"></div>Loading company details...</div>';
  var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
  modal.show();

  fetch(API.clients + '?company_id=' + encodeURIComponent(companyId), { credentials: 'include' })
    .then(function (r) { return r.json(); })
    .then(function (d) {
      if (!d || !d.success || !d.company) {
        bodyEl.innerHTML = '<div class="alert alert-danger mb-0">' + escapeHtml((d && d.message) || 'Failed to load company details.') + '</div>';
        return;
      }

      var c = d.company;
      var tl = d.team_lead;
      
      // Match the exact status of the row clicked in the table
      var isRowActive = (rowStatus === 'Active') || companyHasActiveAgreement(c) || (c.Status && String(c.Status).toLowerCase() === 'active') || c.IsActive == 1;
      var statusBadge = isRowActive
        ? '<span class="badge bg-success text-xs px-2.5 py-1 rounded-full font-semibold">Active</span>'
        : '<span class="badge bg-warning text-dark text-xs px-2.5 py-1 rounded-full font-semibold">Pending Agreement</span>';

      var tlInfo = (tl && (tl.FullName || tl.Name))
        ? escapeHtml(tl.FullName || tl.Name) + (tl.Email ? ' (' + escapeHtml(tl.Email) + ')' : '')
        : 'None assigned';

      bodyEl.innerHTML =
        '<div class="space-y-4 text-left">' +
        '  <div class="rounded-xl bg-slate-50 p-4 border border-slate-200 mb-3">' +
        '    <div class="d-flex justify-content-between align-items-center mb-3">' +
        '      <h6 class="font-bold text-slate-800 text-xs uppercase tracking-wider text-blue-700 m-0"><i class="bi bi-building me-1"></i> General Information</h6>' +
               statusBadge +
        '    </div>' +
        '    <div class="row g-3 text-sm">' +
        '      <div class="col-md-6"><span class="text-secondary d-block small">Company Name:</span> <strong class="text-dark fs-6">' + escapeHtml(c.Name || '—') + '</strong></div>' +
        '      <div class="col-md-6"><span class="text-secondary d-block small">Industry:</span> <strong class="text-dark">' + escapeHtml(c.Industry || '—') + '</strong></div>' +
        '      <div class="col-12"><span class="text-secondary d-block small">Address:</span> <strong class="text-dark">' + escapeHtml(c.Address || 'N/A') + '</strong></div>' +
        '    </div>' +
        '  </div>' +

        '  <div class="rounded-xl bg-slate-50 p-4 border border-slate-200">' +
        '    <h6 class="font-bold text-slate-800 text-xs uppercase tracking-wider mb-3 text-blue-700"><i class="bi bi-person-lines-fill me-1"></i> Contact &amp; Team Lead Details</h6>' +
        '    <div class="row g-3 text-sm">' +
        '      <div class="col-md-6"><span class="text-secondary d-block small">Contact Person:</span> <strong class="text-dark">' + escapeHtml(c.ContactPerson || 'N/A') + '</strong></div>' +
        '      <div class="col-md-6"><span class="text-secondary d-block small">Contact Number:</span> <strong class="text-dark">' + escapeHtml(c.ContactNumber || 'N/A') + '</strong></div>' +
        '      <div class="col-12"><span class="text-secondary d-block small">Assigned Team Lead:</span> <strong class="text-dark">' + tlInfo + '</strong></div>' +
        '    </div>' +
        '  </div>' +
        '</div>';
    })
    .catch(function () {
      bodyEl.innerHTML = '<div class="alert alert-danger mb-0">Error fetching company details. Please try again.</div>';
    });
}
