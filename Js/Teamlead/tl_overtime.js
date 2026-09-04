/**
 * Team Lead dashboard — Overtime Management panel.
 * Talks to Api/TeamLead/get_overtime_roster.php and Api/TeamLead/save_overtime.php.
 */
(function (global) {
  'use strict';

  var ROSTER_API = '../../Api/TeamLead/get_overtime_roster.php';
  var SAVE_API = '../../Api/TeamLead/save_overtime.php';

  var currentDate = new Date().toISOString().slice(0, 10);
  var lastRoster = [];
  var editingOvertimeId = null; // null => creating new session(s)

  function esc(s) {
    if (s == null) return '';
    var d = document.createElement('div');
    d.textContent = String(s);
    return d.innerHTML;
  }

  function statusBadgeClass(status) {
    var s = String(status || 'Pending');
    if (s === 'Approved') return 'bg-emerald-100 text-emerald-800';
    if (s === 'Completed') return 'bg-blue-100 text-blue-800';
    if (s === 'Active') return 'bg-amber-100 text-amber-800';
    if (s === 'Rejected') return 'bg-red-100 text-red-800';
    return 'bg-slate-200 text-slate-700'; // Pending
  }

  function updateCounts(roster) {
    var counts = { Pending: 0, Active: 0, Completed: 0, Approved: 0 };
    roster.forEach(function (emp) {
      (emp.overtime_sessions || []).forEach(function (s) {
        if (counts.hasOwnProperty(s.status)) counts[s.status]++;
      });
    });
    var setText = function (id, v) { var el = document.getElementById(id); if (el) el.textContent = String(v); };
    setText('otCountPending', counts.Pending);
    setText('otCountActive', counts.Active);
    setText('otCountCompleted', counts.Completed);
    setText('otCountApproved', counts.Approved);
  }

  function buildRow(emp) {
    var sessions = emp.overtime_sessions && emp.overtime_sessions.length ? emp.overtime_sessions : [null];
    return sessions.map(function (s) {
      var checkbox = s ? '' : '<input type="checkbox" class="ot-select-checkbox" value="' + emp.employee_id + '" />';
      var otStart = s ? esc(s.ot_start_display) : '—';
      var otEnd = s ? esc(s.ot_end_display) : '—';
      var otHours = s && s.ot_hours != null ? esc(s.ot_hours) + ' hrs' : '—';
      var status = s ? s.status : null;
      var badge = status ? '<span class="rounded-full px-2.5 py-1 text-xs font-bold ' + statusBadgeClass(status) + '">' + esc(status) + '</span>' : '<span class="text-xs text-slate-400">Not started</span>';

      var actions = '';
      if (s) {
        actions += '<button type="button" class="ot-edit-btn mr-2 text-xs font-semibold text-brand-accent hover:underline" data-overtime-id="' + s.overtime_id + '" data-ot-start="' + esc(s.ot_start) + '" data-ot-end="' + esc(s.ot_end) + '" data-remarks="' + esc(s.remarks || '') + '">Edit</button>';
        if (status === 'Pending') {
          actions += '<button type="button" class="ot-status-btn mr-2 text-xs font-semibold text-blue-700 hover:underline" data-overtime-id="' + s.overtime_id + '" data-status="Active">Mark Active</button>';
        }
        if (status === 'Active') {
          actions += '<button type="button" class="ot-status-btn mr-2 text-xs font-semibold text-blue-700 hover:underline" data-overtime-id="' + s.overtime_id + '" data-status="Completed">Complete</button>';
        }
        if (status === 'Completed') {
          actions += '<button type="button" class="ot-status-btn mr-2 text-xs font-semibold text-emerald-700 hover:underline" data-overtime-id="' + s.overtime_id + '" data-status="Approved">Approve</button>';
          actions += '<button type="button" class="ot-status-btn text-xs font-semibold text-red-700 hover:underline" data-overtime-id="' + s.overtime_id + '" data-status="Rejected">Reject</button>';
        }
      } else {
        actions = '<span class="text-xs text-slate-400">Select employee, then "Start Overtime for Selected"</span>';
      }

      return '<tr>' +
        '<td class="px-3 py-3">' + checkbox + '</td>' +
        '<td class="px-4 py-3 font-semibold text-brand-navy">' + esc(emp.employee_name) + '<div class="text-xs font-normal text-slate-500">' + esc(emp.employee_number) + '</div></td>' +
        '<td class="px-4 py-3">' + esc(emp.company_name || '—') + '</td>' +
        '<td class="px-4 py-3 text-slate-600">' + esc(emp.regular_schedule) + '</td>' +
        '<td class="px-4 py-3">' + otStart + '</td>' +
        '<td class="px-4 py-3">' + otEnd + '</td>' +
        '<td class="px-4 py-3 font-semibold">' + otHours + '</td>' +
        '<td class="px-4 py-3">' + badge + '</td>' +
        '<td class="px-4 py-3">' + actions + '</td>' +
        '</tr>';
    }).join('');
  }

  function renderRoster(roster) {
    var tbody = document.getElementById('overtimeTableBody');
    if (!tbody) return;
    if (!roster.length) {
      tbody.innerHTML = '<tr><td colspan="9" class="px-4 py-6 text-center text-slate-500">No employees currently deployed under you.</td></tr>';
      syncSelectAllState();
      return;
    }
    tbody.innerHTML = roster.map(buildRow).join('');
    updateCounts(roster);
    bindRowActions();
    syncSelectAllState();
  }

  function bindRowActions() {
    document.querySelectorAll('.ot-select-checkbox').forEach(function (cb) {
      cb.addEventListener('change', function () {
        updateStartButtonState();
        syncSelectAllState();
      });
    });
    document.querySelectorAll('.ot-edit-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        openEditModal(parseInt(btn.getAttribute('data-overtime-id'), 10), {
          otStart: btn.getAttribute('data-ot-start'),
          otEnd: btn.getAttribute('data-ot-end'),
          remarks: btn.getAttribute('data-remarks') || ''
        });
      });
    });
    document.querySelectorAll('.ot-status-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var overtimeId = parseInt(btn.getAttribute('data-overtime-id'), 10);
        var status = btn.getAttribute('data-status');
        setStatus(overtimeId, status);
      });
    });
  }

  function updateStartButtonState() {
    var anyChecked = document.querySelectorAll('.ot-select-checkbox:checked').length > 0;
    var btn = document.getElementById('btn-start-overtime');
    if (btn) btn.disabled = !anyChecked;
  }

  /** Keeps the header "select all" checkbox in sync with the row checkboxes
   * (checked when all are checked, indeterminate when some are, unchecked/
   * disabled when there's nothing selectable — e.g. everyone already has an
   * OT session for the chosen date). */
  function syncSelectAllState() {
    var selectAll = document.getElementById('overtime-select-all');
    if (!selectAll) return;
    var boxes = document.querySelectorAll('.ot-select-checkbox');
    if (!boxes.length) {
      selectAll.checked = false;
      selectAll.indeterminate = false;
      selectAll.disabled = true;
      return;
    }
    selectAll.disabled = false;
    var checkedCount = document.querySelectorAll('.ot-select-checkbox:checked').length;
    selectAll.checked = checkedCount === boxes.length;
    selectAll.indeterminate = checkedCount > 0 && checkedCount < boxes.length;
  }

  function loadRoster() {
    var picker = document.getElementById('overtime-date-picker');
    if (picker && !picker.value) picker.value = currentDate;
    if (picker) currentDate = picker.value;

    var label = document.getElementById('overtime-date-label');
    if (label) label.textContent = currentDate;

    var tbody = document.getElementById('overtimeTableBody');
    if (tbody) tbody.innerHTML = '<tr><td colspan="9" class="px-4 py-6 text-center text-slate-500">Loading team…</td></tr>';

    return fetch(ROSTER_API + '?date=' + encodeURIComponent(currentDate), { credentials: 'include' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res || !res.success) {
          if (tbody) tbody.innerHTML = '<tr><td colspan="9" class="px-4 py-6 text-center text-red-600">' + esc((res && res.message) || 'Failed to load overtime roster.') + '</td></tr>';
          return;
        }
        lastRoster = res.employees || [];
        renderRoster(lastRoster);
      })
      .catch(function () {
        if (tbody) tbody.innerHTML = '<tr><td colspan="9" class="px-4 py-6 text-center text-red-600">Network error while loading overtime roster.</td></tr>';
      });
  }

  function openEditModal(overtimeId, prefill) {
    editingOvertimeId = overtimeId; // null => create mode
    var modal = document.getElementById('overtime-edit-modal');
    var title = document.getElementById('overtime-edit-modal-title');
    var startInput = document.getElementById('overtime-edit-start');
    var endInput = document.getElementById('overtime-edit-end');
    var remarksInput = document.getElementById('overtime-edit-remarks');
    var errEl = document.getElementById('overtime-edit-error');
    if (!modal) return;

    if (title) title.textContent = overtimeId ? 'Edit Overtime' : 'Start Overtime';
    if (startInput) startInput.value = (prefill && prefill.otStart) ? prefill.otStart.slice(0, 5) : '18:00';
    if (endInput) endInput.value = (prefill && prefill.otEnd) ? prefill.otEnd.slice(0, 5) : '20:00';
    if (remarksInput) remarksInput.value = (prefill && prefill.remarks) || '';
    if (errEl) { errEl.classList.add('hidden'); errEl.textContent = ''; }

    modal.classList.remove('hidden');
    modal.classList.add('flex');
  }

  function closeEditModal() {
    var modal = document.getElementById('overtime-edit-modal');
    if (modal) {
      modal.classList.add('hidden');
      modal.classList.remove('flex');
    }
    editingOvertimeId = null;
  }

  function showEditError(msg) {
    var errEl = document.getElementById('overtime-edit-error');
    if (errEl) {
      errEl.textContent = msg;
      errEl.classList.remove('hidden');
    }
  }

  function saveEdit() {
    var startInput = document.getElementById('overtime-edit-start');
    var endInput = document.getElementById('overtime-edit-end');
    var remarksInput = document.getElementById('overtime-edit-remarks');
    var otStart = startInput ? startInput.value : '';
    var otEnd = endInput ? endInput.value : '';
    var remarks = remarksInput ? remarksInput.value : '';

    if (!otStart || !otEnd) {
      showEditError('OT start and end times are required.');
      return;
    }
    if (otEnd <= otStart) {
      showEditError('OT end time must be after OT start time.');
      return;
    }

    var body;
    if (editingOvertimeId) {
      body = { action: 'update', overtime_id: editingOvertimeId, ot_start: otStart, ot_end: otEnd, remarks: remarks };
    } else {
      var selected = Array.prototype.slice.call(document.querySelectorAll('.ot-select-checkbox:checked')).map(function (cb) { return cb.value; });
      if (!selected.length) {
        showEditError('Select at least one employee first.');
        return;
      }
      body = { action: 'create', employee_ids: selected, date: currentDate, ot_start: otStart, ot_end: otEnd, remarks: remarks };
    }

    fetch(SAVE_API, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body)
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res || !res.success) {
          showEditError((res && res.message) || 'Failed to save overtime.');
          return;
        }
        closeEditModal();
        loadRoster();
      })
      .catch(function () {
        showEditError('Network error while saving overtime.');
      });
  }

  function setStatus(overtimeId, status) {
    fetch(SAVE_API, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'set_status', overtime_id: overtimeId, status: status })
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res || !res.success) {
          alert((res && res.message) || 'Failed to update overtime status.');
          return;
        }
        loadRoster();
      })
      .catch(function () {
        alert('Network error while updating overtime status.');
      });
  }

  function bindStaticControls() {
    var startBtn = document.getElementById('btn-start-overtime');
    if (startBtn) {
      startBtn.addEventListener('click', function () { openEditModal(null, null); });
    }
    var closeBtn = document.getElementById('overtime-edit-modal-close');
    var cancelBtn = document.getElementById('overtime-edit-cancel');
    var saveBtn = document.getElementById('overtime-edit-save');
    if (closeBtn) closeBtn.addEventListener('click', closeEditModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeEditModal);
    if (saveBtn) saveBtn.addEventListener('click', saveEdit);

    var modal = document.getElementById('overtime-edit-modal');
    if (modal) {
      modal.addEventListener('click', function (e) {
        if (e.target === modal) closeEditModal();
      });
    }
    var picker = document.getElementById('overtime-date-picker');
    if (picker) {
      picker.addEventListener('change', loadRoster);
    }
    var selectAll = document.getElementById('overtime-select-all');
    if (selectAll) {
      selectAll.addEventListener('change', function () {
        var checked = selectAll.checked;
        document.querySelectorAll('.ot-select-checkbox').forEach(function (cb) {
          cb.checked = checked;
        });
        selectAll.indeterminate = false;
        updateStartButtonState();
      });
    }
  }

  document.addEventListener('DOMContentLoaded', bindStaticControls);
  // In case this script executes after DOMContentLoaded already fired (e.g. deferred load).
  if (document.readyState === 'interactive' || document.readyState === 'complete') {
    bindStaticControls();
  }

  global.TlOvertime = {
    load: loadRoster
  };
})(window);
