/**
 * Employee dashboard — payroll summaries and payslip modal.
 */
(function (global) {
  'use strict';

  var PAYROLL_API = '../../Api/Employee/get_employee_payroll.php';
  var payrollCache = null;

  function money(n) {
    if (n == null || n === '') return '—';
    var val = Number(n);
    if (!isFinite(val)) return '—';
    return '₱' + val.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function setText(id, text) {
    var el = document.getElementById(id);
    if (el) el.textContent = text;
  }

  var STATUS_BADGE_CLASS = {
    secondary: 'bg-slate-200 text-slate-700',
    warning: 'bg-amber-100 text-amber-800',
    info: 'bg-sky-100 text-sky-800',
    success: 'bg-emerald-100 text-emerald-800',
    primary: 'bg-blue-100 text-blue-800'
  };

  function renderStatusBadge(elId, tracker) {
    var el = document.getElementById(elId);
    if (!el || !tracker) return;
    var cls = STATUS_BADGE_CLASS[tracker.status_badge] || STATUS_BADGE_CLASS.secondary;
    el.className = 'inline-block rounded-full px-3 py-1 text-xs font-bold ' + cls;
    el.textContent = tracker.current_status || tracker.status_label || 'Pending';
    var infoId = elId + '-updated';
    var info = document.getElementById(infoId);
    if (!info) { info = document.createElement('div'); info.id = infoId; info.className = 'text-xs text-slate-500 mt-2'; el.parentNode.insertBefore(info, el.nextSibling); }
    info.textContent = tracker.updated_at ? 'Updated ' + new Date(tracker.updated_at.replace(' ', 'T')).toLocaleString('en-PH', { hour12: true }) : '';
  }

  function renderPayrollCards(data) {
    if (!data || !data.payroll) return;
    var p = data.payroll;
    var notice = document.getElementById('employee-leave-pay-notice');
    if (!notice) { var anchor = document.getElementById('empPayrollTabStatus'); if (anchor) { notice = document.createElement('div'); notice.id = 'employee-leave-pay-notice'; notice.className = 'rounded-lg border border-blue-200 bg-blue-50 p-3 mt-3 text-sm text-blue-900'; anchor.parentNode.appendChild(notice); } }
    if (notice) notice.textContent = data.leave_pay_notice || 'Your leave pay will be paid together with your 13th-month pay.';
    var period = data.period || {};
    var tracker = data.payroll_tracker || {};
    var attendance = p.attendance_summary || { present: 0, late: 0, absent: 0 };

    // Dashboard "Kinsenas Payroll" card — period info + status only, no estimated amounts.
    setText('empPayrollNextPayday', period.payday_display ? 'Next Payday: ' + period.payday_display : 'Next Payday: —');
    setText('empPayrollCutoff', period.label ? 'Cutoff period: ' + period.label : 'Cutoff period: —');
    renderStatusBadge('empDashboardPayrollStatus', tracker);

    // Payroll tab — "Payroll Overview" — period info, status, days worked, attendance summary.
    setText('empPayrollTabNextPayday', period.payday_display ? 'Next Payday: ' + period.payday_display : 'Next Payday: —');
    setText('empPayrollTabCutoff', period.label ? 'Cutoff period: ' + period.label : 'Cutoff period: —');
    renderStatusBadge('empPayrollTabStatus', tracker);
    setText('empPayrollDaysWorked', p.days_worked != null ? String(p.days_worked) : '—');
    setText('empPayrollPresentCount', String(attendance.present || 0));
    setText('empPayrollLateCount', String(attendance.late || 0));
    setText('empPayrollAbsentCount', String(attendance.absent || 0));
  }

  function statusBadgeClassFor(status) {
    var s = String(status || '');
    if (s === 'Client Approved' || s === 'Payroll Released') return 'bg-success';
    if (s === 'For Client Approval' || s === 'Payroll Computed') return 'bg-primary';
    if (s === 'Returned for Revision' || s === 'Needs Correction') return 'bg-danger';
    if (s === 'On Hold') return 'bg-warning text-dark';
    return 'bg-secondary';
  }

  function renderPayslipStatus(payrollStatus, history) {
    var block = document.getElementById('payslipStatusBlock');
    if (!block) return;

    if (!payrollStatus) {
      block.classList.add('d-none');
      return;
    }
    block.classList.remove('d-none');

    var badge = document.getElementById('payslipStatusBadge');
    if (badge) {
      badge.textContent = payrollStatus.status || '—';
      badge.className = 'badge ' + statusBadgeClassFor(payrollStatus.status);
    }
    setText('payslipStatusUpdatedAt', payrollStatus.updated_at ? ('Updated ' + payrollStatus.updated_at) : '');
    setText('payslipStatusRemarks', payrollStatus.remarks ? ('"' + payrollStatus.remarks + '"') : '');

    var proofLink = document.getElementById('payslipStatusProofLink');
    if (proofLink) {
      if (payrollStatus.has_proof && (payrollStatus.proof_url || payrollStatus.proof_history_id)) {
        proofLink.href = payrollStatus.proof_url || '../../Api/Common/view_payroll_proof.php?id=' + payrollStatus.proof_history_id;
        proofLink.classList.remove('d-none');
      } else {
        proofLink.classList.add('d-none');
      }
    }

    var timelineEl = document.getElementById('payslipStatusTimeline');
    if (timelineEl) {
      var items = Array.isArray(history) ? history : [];
      if (!items.length) {
        timelineEl.innerHTML = '';
      } else {
        timelineEl.innerHTML = '<div class="text-uppercase text-muted fw-bold mt-2 mb-1" style="font-size:0.7rem;">Status History</div>' +
          items.slice().reverse().map(function (h) {
            return '<div class="d-flex justify-content-between border-top pt-1 pb-1">' +
              '<span>' + escapeHtml(h.status) + (h.remarks ? ' <span class="text-muted">— "' + escapeHtml(h.remarks) + '"</span>' : '') + '</span>' +
              '<span class="text-muted ms-2">' + escapeHtml(h.created_at) + '</span>' +
              '</div>';
          }).join('');
      }
    }
  }

  function escapeHtml(s) {
    if (s == null) return '';
    var d = document.createElement('div');
    d.textContent = String(s);
    return d.innerHTML;
  }

  function fillPayslipModal(payslip) {
    if (!payslip) return;
    setText('payslipEmployeeName', payslip.employee_name || '—');
    setText('payslipEmployeeId', payslip.employee_number || '—');
    setText('payslipPeriod', payslip.period_label || '—');
    setText('payslipDesignation', payslip.designation || '—');
    setText('payslipSite', payslip.assigned_site || '—');
    setText('payslipCompany', payslip.company || 'Onazul Manpower Services');

    var body = document.getElementById('payslipFigures');
    var notice = document.getElementById('payslipNotFinalizedNotice');
    var statusBlock = document.getElementById('payslipStatusBlock');

    if (!payslip.is_finalized) {
      if (body) body.classList.add('d-none');
      if (statusBlock) statusBlock.classList.add('d-none');
      if (notice) {
        notice.classList.remove('d-none');
        notice.textContent = payslip.message || 'Your payslip will be available once payroll has been finalized.';
      }
      return;
    }

    if (body) body.classList.remove('d-none');
    if (notice) notice.classList.add('d-none');

    renderPayslipStatus(payslip.payroll_status, payslip.payroll_status_history);

    setText('payslipDaysWorked', payslip.days_worked != null ? String(payslip.days_worked) : '—');
    setText('payslipDailyRate', payslip.daily_rate != null ? money(payslip.daily_rate) : '—');

    var e = payslip.earnings || {};
    setText('payslipBasicPay', money(e.basic_pay));
    setText('payslipSundayOt', money((e.sunday_overtime || 0) + (e.regular_overtime || 0) + (e.approved_overtime || 0)));
    setText('payslipHolidayPay', money((e.holiday_pay || 0) + (e.holiday_ot_pay || 0)));
    setText('payslipAddBack', money(e.add_back));
    setText('payslipSil', money(e.sil_accrual));
    setText('payslipAllowance', money(e.allowance));

    var lu = payslip.late_undertime || {};
    setText('payslipLateMinutes', lu.late_minutes != null ? lu.late_minutes + ' min' : '—');
    setText('payslipUndertimeMinutes', lu.undertime_minutes != null ? lu.undertime_minutes + ' min' : '—');
    setText('payslipLateUndertimeDeduction', lu.amount != null ? ('- ' + money(lu.amount)) : '—');

    var d = payslip.deductions || {};
    setText('payslipSss', money(d.sss_ee));
    setText('payslipPhilhealth', money(d.philhealth_ee));
    setText('payslipPagibig', money(d.pagibig_ee));
    setText('payslipOtherDeductions', money(d.other != null ? d.other : Math.max(0, Number(payslip.total_deductions || 0) - Number(d.sss_ee || 0) - Number(d.philhealth_ee || 0) - Number(d.pagibig_ee || 0))));

    setText('payslipNetPay', money(payslip.net_pay));
    setText('payslipGrossPay', money(payslip.gross_pay));
    setText('payslipTotalDeductions', money(payslip.total_deductions));
  }

  function loadPayroll() {
    return fetch(PAYROLL_API, { credentials: 'include' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data || !data.success) return null;
        payrollCache = data;
        renderPayrollCards(data);
        return data;
      })
      .catch(function (err) {
        console.warn('employee payroll:', err);
        return null;
      });
  }

  function openPayslipModal() {
    var modalEl = document.getElementById('payslipModal');
    if (!modalEl || typeof bootstrap === 'undefined') return;

    var show = function (data) {
      if (data && data.payslip) fillPayslipModal(data.payslip);
      bootstrap.Modal.getOrCreateInstance(modalEl).show();
    };

    if (payrollCache && payrollCache.payslip) {
      show(payrollCache);
    } else {
      loadPayroll().then(show);
    }
  }

  function init() {
    loadPayroll();
    var lastUnread = null;
    document.addEventListener('onazul:realtime-counts', function (event) { var count = event.detail && event.detail.counts && event.detail.counts.notifications_unread; if (count != null && count !== lastUnread) { lastUnread = count; if (!document.hidden) loadPayroll(); } });
    document.addEventListener('visibilitychange', function () { if (!document.hidden) loadPayroll(); });

    var btnView = document.getElementById('btnViewPayslip');
    if (btnView) {
      btnView.addEventListener('click', openPayslipModal);
    }

    var btnPrint = document.getElementById('btnPrintPayslip');
    if (btnPrint) {
      btnPrint.addEventListener('click', function () {
        var body = document.getElementById('payslipPrintArea');
        if (!body) {
          global.print();
          return;
        }
        var w = global.open('', '_blank', 'width=800,height=900');
        if (!w) {
          global.print();
          return;
        }
        w.document.write('<html><head><title>Payslip</title>');
        w.document.write('<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">');
        w.document.write('<style>body{padding:24px;font-family:Inter,system-ui,sans-serif} .payslip-net{font-size:1.75rem;font-weight:700;color:#0A2A5E}</style>');
        w.document.write('</head><body>' + body.innerHTML + '</body></html>');
        w.document.close();
        w.focus();
        w.print();
      });
    }
  }

  global.EmployeePayroll = {
    load: loadPayroll,
    openPayslip: openPayslipModal,
    getCache: function () { return payrollCache; },
    fillPayslipModal: fillPayslipModal
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})(window);