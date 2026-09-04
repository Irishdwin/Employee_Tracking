/**
 * Employee dashboard — Payroll History tab.
 * Reuses the existing #payslipModal + EmployeePayroll.fillPayslipModal()
 * (Js/Employee/employee_payroll.js) — no new modal, no duplicated rendering.
 */
(function (global) {
  'use strict';

  var HISTORY_API = '../../Api/Employee/get_employee_payroll_history.php';

  function money(n) {
    var val = Number(n);
    if (!isFinite(val)) return '—';
    return '₱' + val.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function esc(s) {
    if (s == null) return '';
    var d = document.createElement('div');
    d.textContent = String(s);
    return d.innerHTML;
  }

  function statusBadgeClassFor(status) {
    var s = String(status || '');
    if (s === 'Client Approved' || s === 'Payroll Released') return 'bg-success';
    if (s === 'For Client Approval' || s === 'Payroll Computed') return 'bg-primary';
    if (s === 'Returned for Revision' || s === 'Needs Correction') return 'bg-danger';
    if (s === 'On Hold') return 'bg-warning text-dark';
    return 'bg-secondary';
  }

  function renderHistory(rows) {
    var tbody = document.getElementById('empPayrollHistoryBody');
    if (!tbody) return;
    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="7" class="px-4 py-6 text-center text-slate-500">No finalized payroll yet. Your payslip will appear here once Accounting releases it.</td></tr>';
      return;
    }
    tbody.innerHTML = rows.map(function (r) {
      var statusHtml = r.payroll_status
        ? '<span class="badge ' + statusBadgeClassFor(r.payroll_status) + '">' + esc(r.payroll_status) + '</span>'
        : '<span class="text-slate-400 small">Not yet updated</span>';
      return '<tr>' +
        '<td class="px-4 py-3 font-semibold text-brand-navy">' + esc(r.period_label) + '</td>' +
        '<td class="px-4 py-3">' + esc(r.cycle_label) + '</td>' +
        '<td class="px-4 py-3 text-right">' + esc(money(r.gross_pay)) + '</td>' +
        '<td class="px-4 py-3 text-right text-red-600">' + esc(money(r.total_deductions)) + '</td>' +
        '<td class="px-4 py-3 text-right font-bold">' + esc(money(r.net_pay)) + '</td>' +
        '<td class="px-4 py-3">' + statusHtml + '</td>' +
        '<td class="px-4 py-3">' +
        '  <button type="button" class="rounded-lg border border-brand-accent px-3 py-1.5 text-xs font-bold text-brand-accent hover:bg-brand-accent hover:text-white" data-payroll-id="' + r.payroll_id + '">View Payslip</button>' +
        '</td>' +
        '</tr>';
    }).join('');

    tbody.querySelectorAll('[data-payroll-id]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        openHistoryPayslip(parseInt(btn.getAttribute('data-payroll-id'), 10));
      });
    });
  }

  function loadHistory() {
    var tbody = document.getElementById('empPayrollHistoryBody');
    if (tbody) tbody.innerHTML = '<tr><td colspan="7" class="px-4 py-6 text-center text-slate-500">Loading payroll history…</td></tr>';

    return fetch(HISTORY_API, { credentials: 'include' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res || !res.success) {
          if (tbody) tbody.innerHTML = '<tr><td colspan="7" class="px-4 py-6 text-center text-red-600">' + esc((res && res.message) || 'Failed to load payroll history.') + '</td></tr>';
          return;
        }
        renderHistory(res.history || []);
      })
      .catch(function () {
        if (tbody) tbody.innerHTML = '<tr><td colspan="7" class="px-4 py-6 text-center text-red-600">Network error while loading payroll history.</td></tr>';
      });
  }

  function openHistoryPayslip(payrollId) {
    fetch(HISTORY_API + '?payroll_id=' + encodeURIComponent(payrollId), { credentials: 'include' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res || !res.success || !res.payslip) {
          alert((res && res.message) || 'Failed to load this payslip.');
          return;
        }
        var modalEl = document.getElementById('payslipModal');
        if (!modalEl || typeof bootstrap === 'undefined' || !window.EmployeePayroll) return;
        window.EmployeePayroll.fillPayslipModal(res.payslip);
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
      })
      .catch(function () {
        alert('Network error while loading this payslip.');
      });
  }

  global.EmployeePayrollHistory = {
    load: loadHistory
  };
})(window);
