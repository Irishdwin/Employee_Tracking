/**
 * Payroll Approvals script for Admin dashboard.
 */
(function () {
    'use strict';
    var API = '../../Api/Accounting/get_payroll_runs.php';
  
    function esc(s) {
      if (s == null) return '';
      var d = document.createElement('div');
      d.textContent = String(s);
      return d.innerHTML;
    }
  
    function formatMoney(value) {
      if (value == null || value === '' || isNaN(Number(value))) return '—';
      return '₱' + Number(value).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
  
    function loadRuns() {
      fetch(API, { credentials: 'include' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          var tbody = document.getElementById('payrollApprovalsBody');
          if (!tbody) return;
          var runs = (d.active_runs || []).concat(d.history_runs || []);
          if (!runs.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No payroll runs found.</td></tr>';
            return;
          }
          tbody.innerHTML = runs.map(function (run) {
            var ref = run.billing_ref_no || run.run_id;
            var curStatus = run.current_status || 'Pending DTR';
            return '<tr>'
              + '<td><strong>' + esc(ref) + '</strong></td>'
              + '<td>' + esc(run.client_name || run.client) + '</td>'
              + '<td>' + esc(run.deployment_site) + '</td>'
              + '<td>' + esc(run.cutoff_period || run.cutoff) + '</td>'
              + '<td class="text-center">' + run.employee_count + '</td>'
              + '<td class="text-end tabular-nums">' + formatMoney(run.total_invoice) + '</td>'
              + '<td><span class="badge bg-primary">' + esc(curStatus) + '</span></td>'
              + '<td><a href="../../Api/Accounting/export_client_billing.php?ref=' + encodeURIComponent(ref) + '" class="btn btn-sm btn-outline-success"><i class="bi bi-file-earmark-excel"></i> Export .xlsx</a></td>'
              + '</tr>';
          }).join('');
        })
        .catch(function () {
          var tbody = document.getElementById('payrollApprovalsBody');
          if (tbody) tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-4">Failed to load payroll runs.</td></tr>';
        });
    }
  
    document.addEventListener('DOMContentLoaded', loadRuns);
  })();