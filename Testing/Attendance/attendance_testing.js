/**
 * DEVELOPMENT / TESTING ONLY.
 * Drives the Attendance Testing Dashboard — talks only to the endpoints in
 * ./Api/, which in turn write to the real production `attendance` /
 * `daily_time_records` tables.
 */
(function () {
  'use strict';

  var API = 'Api/';
  var state = {
    companyId: null,
    teamLeadId: null,
    employeeId: null,
  };

  function $(id) { return document.getElementById(id); }

  function esc(s) {
    if (s == null) return '';
    var d = document.createElement('div');
    d.textContent = String(s);
    return d.innerHTML;
  }

  function showAlert(el, message, isError) {
    el.textContent = message;
    el.className = 'alert small mt-3 ' + (isError ? 'alert-danger' : 'alert-success');
    el.classList.remove('d-none');
  }

  function fetchJson(url, options) {
    return fetch(url, Object.assign({ credentials: 'include' }, options || {}))
      .then(function (r) { return r.json(); });
  }

  function loadCompanies() {
    fetchJson(API + 'get_companies.php')
      .then(function (res) {
        var sel = $('selCompany');
        if (!res || !res.success) {
          sel.innerHTML = '<option value="">Failed to load — Admin login required?</option>';
          return;
        }
        sel.innerHTML = '<option value="">Select a company…</option>' +
          res.companies.map(function (c) { return '<option value="' + c.company_id + '">' + esc(c.name) + '</option>'; }).join('');
      })
      .catch(function () {
        $('selCompany').innerHTML = '<option value="">Network error</option>';
      });
  }

  function loadTeamLeads(companyId) {
    var sel = $('selTeamLead');
    sel.disabled = true;
    sel.innerHTML = '<option value="">Loading…</option>';
    resetEmployeeAndBelow();

    fetchJson(API + 'get_teamleads.php?company_id=' + encodeURIComponent(companyId))
      .then(function (res) {
        if (!res || !res.success) {
          sel.innerHTML = '<option value="">Failed to load</option>';
          return;
        }
        if (!res.team_leads.length) {
          sel.innerHTML = '<option value="">No Team Leads deployed to this company</option>';
          return;
        }
        sel.innerHTML = '<option value="">Select a Team Lead…</option>' +
          res.team_leads.map(function (t) { return '<option value="' + t.team_lead_id + '">' + esc(t.name) + '</option>'; }).join('');
        sel.disabled = false;
      })
      .catch(function () {
        sel.innerHTML = '<option value="">Network error</option>';
      });
  }

  function loadEmployees(companyId, teamLeadId) {
    var sel = $('selEmployee');
    sel.disabled = true;
    sel.innerHTML = '<option value="">Loading…</option>';
    resetRecordCards();

    fetchJson(API + 'get_employees.php?company_id=' + encodeURIComponent(companyId) + '&team_lead_id=' + encodeURIComponent(teamLeadId))
      .then(function (res) {
        if (!res || !res.success) {
          sel.innerHTML = '<option value="">Failed to load</option>';
          return;
        }
        if (!res.employees.length) {
          sel.innerHTML = '<option value="">No employees under this Team Lead</option>';
          return;
        }
        sel.innerHTML = '<option value="">Select an employee…</option>' +
          res.employees.map(function (e) { return '<option value="' + e.employee_id + '">' + esc(e.name) + ' (' + esc(e.employee_number) + ')</option>'; }).join('');
        sel.disabled = false;
      })
      .catch(function () {
        sel.innerHTML = '<option value="">Network error</option>';
      });
  }

  function loadEmployeeContext(employeeId) {
    fetchJson(API + 'get_employee_attendance_list.php?employee_id=' + encodeURIComponent(employeeId))
      .then(function (res) {
        if (!res || !res.success) return;

        var scheduleEl = $('scheduleInfo');
        var s = res.schedule;
        scheduleEl.innerHTML = '<strong>Duty schedule:</strong> ' + esc(s.working_days) + ', ' + esc(s.time_in) + '–' + esc(s.time_out) +
          ', ' + esc(s.break_duration) + ' min break, ' + esc(s.grace_period) + ' min grace period.';
        scheduleEl.classList.remove('d-none');

        $('cardAddRecord').style.display = '';
        $('cardRecordsList').style.display = '';
        renderRecords(res.records || []);
      })
      .catch(function () {});
  }

  function renderRecords(records) {
    var tbody = $('recordsTableBody');
    if (!records.length) {
      tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">No attendance records yet for this employee.</td></tr>';
      return;
    }
    tbody.innerHTML = records.map(function (r) {
      var lockedNote = r.locked ? ' <span class="badge bg-secondary">Submitted — locked</span>' : '';
      var actions = r.locked
        ? '<span class="text-muted small">—</span>'
        : '<button type="button" class="btn btn-sm btn-outline-primary me-1 btn-edit-record" data-id="' + r.attendance_id + '" data-date="' + esc(r.date) + '" data-time-in="' + esc(r.time_in || '') + '" data-time-out="' + esc(r.time_out || '') + '">Edit</button>' +
          '<button type="button" class="btn btn-sm btn-outline-danger btn-delete-record" data-id="' + r.attendance_id + '">Delete</button>';
      return '<tr class="' + (r.locked ? 'record-locked' : '') + '">' +
        '<td>' + esc(r.date) + '</td>' +
        '<td>' + esc(r.time_in || '—') + '</td>' +
        '<td>' + esc(r.time_out || '—') + '</td>' +
        '<td>' + esc(r.status) + lockedNote + '</td>' +
        '<td>' + esc(r.minutes_late) + '</td>' +
        '<td>' + actions + '</td>' +
        '</tr>';
    }).join('');

    tbody.querySelectorAll('.btn-edit-record').forEach(function (btn) {
      btn.addEventListener('click', function () {
        $('inpDate').value = btn.getAttribute('data-date');
        $('inpTimeIn').value = btn.getAttribute('data-time-in');
        $('inpTimeOut').value = btn.getAttribute('data-time-out');
        $('btnSaveRecord').setAttribute('data-editing-id', btn.getAttribute('data-id'));
        $('btnSaveRecord').textContent = 'Update Record';
        window.scrollTo({ top: $('cardAddRecord').offsetTop - 20, behavior: 'smooth' });
      });
    });
    tbody.querySelectorAll('.btn-delete-record').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (!window.confirm('Delete this test attendance record? This cannot be undone.')) return;
        fetchJson(API + 'delete_attendance.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ attendance_id: btn.getAttribute('data-id') })
        }).then(function (res) {
          if (!res || !res.success) {
            alert((res && res.message) || 'Failed to delete.');
            return;
          }
          loadEmployeeContext(state.employeeId);
        }).catch(function () {
          alert('Network error while deleting.');
        });
      });
    });
  }

  function resetEmployeeAndBelow() {
    state.teamLeadId = null;
    state.employeeId = null;
    $('selEmployee').disabled = true;
    $('selEmployee').innerHTML = '<option value="">Select a Team Lead first</option>';
    resetRecordCards();
  }

  function resetRecordCards() {
    state.employeeId = null;
    $('scheduleInfo').classList.add('d-none');
    $('cardAddRecord').style.display = 'none';
    $('cardRecordsList').style.display = 'none';
    $('saveResult').classList.add('d-none');
  }

  function resetSaveForm() {
    $('inpDate').value = '';
    $('inpTimeIn').value = '';
    $('inpTimeOut').value = '';
    $('inpRemarks').value = '';
    $('btnSaveRecord').removeAttribute('data-editing-id');
    $('btnSaveRecord').textContent = 'Save Record';
  }

  function bindEvents() {
    $('selCompany').addEventListener('change', function () {
      state.companyId = this.value || null;
      if (state.companyId) {
        loadTeamLeads(state.companyId);
      } else {
        resetEmployeeAndBelow();
        $('selTeamLead').innerHTML = '<option value="">Select a company first</option>';
        $('selTeamLead').disabled = true;
      }
    });

    $('selTeamLead').addEventListener('change', function () {
      state.teamLeadId = this.value || null;
      if (state.teamLeadId) {
        loadEmployees(state.companyId, state.teamLeadId);
      } else {
        resetRecordCards();
        $('selEmployee').innerHTML = '<option value="">Select a Team Lead first</option>';
        $('selEmployee').disabled = true;
      }
    });

    $('selEmployee').addEventListener('change', function () {
      state.employeeId = this.value || null;
      resetSaveForm();
      if (state.employeeId) {
        loadEmployeeContext(state.employeeId);
      } else {
        resetRecordCards();
      }
    });

    $('btnSaveRecord').addEventListener('click', function () {
      var editingId = this.getAttribute('data-editing-id');
      var date = $('inpDate').value;
      var timeIn = $('inpTimeIn').value;
      var timeOut = $('inpTimeOut').value;
      var remarks = $('inpRemarks').value;
      var resultEl = $('saveResult');

      if (!date || !timeIn) {
        showAlert(resultEl, 'Date and Time In are required.', true);
        return;
      }

      var body = editingId
        ? { action: 'update', attendance_id: editingId, date: date, time_in: timeIn, time_out: timeOut, remarks: remarks }
        : { action: 'create', employee_id: state.employeeId, date: date, time_in: timeIn, time_out: timeOut, remarks: remarks };

      fetchJson(API + 'save_attendance.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
      }).then(function (res) {
        if (!res || !res.success) {
          showAlert(resultEl, (res && res.message) || 'Failed to save.', true);
          return;
        }
        showAlert(resultEl, res.message + ' Computed status: ' + res.computed_status +
          (res.minutes_late ? (' (' + res.minutes_late + ' min late)') : '') +
          (res.undertime_minutes ? (', ' + res.undertime_minutes + ' min undertime') : ''), false);
        resetSaveForm();
        loadEmployeeContext(state.employeeId);
      }).catch(function () {
        showAlert(resultEl, 'Network error while saving.', true);
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    bindEvents();
    loadCompanies();
  });
})();
