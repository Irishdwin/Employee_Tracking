/**
 * Accounting dashboard — active payroll runs, history archives, client billing export.
 */
(function (global) {
  'use strict';

  var API = '../../Api/Accounting/get_payroll_runs.php';
  var exportBase = '../../Api/Accounting/export_client_billing.php';

  var state = {
    activeRuns: [],
    historyRuns: [],
    clients: [],
    historyFilters: {
      client: '',
      status: '',
      query: ''
    },
    activeFilters: {
      payrollType: '',
      companyId: '',
      period: '',
      status: ''
    }
  };

  function esc(s) {
    if (s == null) return '';
    var d = document.createElement('div');
    d.textContent = String(s);
    return d.innerHTML;
  }

  function formatMoney(value) {
    if (value == null || value === '' || isNaN(Number(value))) {
      return '—';
    }
    return '₱' + Number(value).toLocaleString('en-PH', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    });
  }

  function statusBadge(status) {
    var s = String(status || 'Draft');
    if (s === 'Approved') {
      return '<span class="badge rounded-pill bg-success">Approved</span>';
    }
    if (s === 'Exported') {
      return '<span class="badge rounded-pill bg-primary">Exported</span>';
    }
    if (s === 'Pending') {
      return '<span class="badge rounded-pill bg-warning text-dark">Pending</span>';
    }
    return '<span class="badge rounded-pill bg-secondary">Draft</span>';
  }

  function exportUrl(ref) {
    return exportBase + '?ref=' + encodeURIComponent(ref);
  }

  function exportButton(ref, label, extraClass) {
    var cls = 'btn btn-sm btn-outline-success' + (extraClass ? ' ' + extraClass : '');
    return '<a href="' + esc(exportUrl(ref)) + '" class="' + cls + '">'
      + '<i class="bi bi-file-earmark-excel me-1"></i> ' + esc(label)
      + '</a>';
  }

  function canExport(run) {
    return run.employee_count > 0 && run.exportable !== false;
  }

  function emptyActiveRow() {
    return '<tr id="empty-active-runs-msg">'
      + '<td colspan="8" class="text-center py-5 text-muted">'
      + '<i class="bi bi-inbox fs-2 d-block mb-2 text-secondary"></i>'
      + '<span>No active payroll runs found. Click <strong>"Sync Payroll Runs"</strong> to fetch verified DTRs.</span>'
      + '</td></tr>';
  }

  function payrollTypeBadge(type) {
    var t = (type === 'A') ? 'A' : 'B';
    var cls = t === 'A' ? 'bg-primary-subtle text-primary border border-primary-subtle' : 'bg-info-subtle text-info-emphasis border border-info-subtle';
    return '<span class="badge ' + cls + '">Type ' + t + '</span>';
  }

  function filterActiveRuns(runList) {
    var f = state.activeFilters;
    return (runList || []).filter(function (run) {
      if (f.payrollType && (run.payroll_type || 'B') !== f.payrollType) return false;
      if (f.companyId && String(run.company_id) !== String(f.companyId)) return false;
      if (f.period && (run.cutoff_period || run.cutoff) !== f.period) return false;
      if (f.status && run.status !== f.status) return false;
      return true;
    });
  }

  function emptyHistoryRow() {
    return '<tr id="empty-history-runs-msg">'
      + '<td colspan="8" class="text-center py-5 text-muted">'
      + '<i class="bi bi-archive fs-2 d-block mb-2 text-secondary"></i>'
      + '<span>No archived payroll runs match your filters.</span>'
      + '</td></tr>';
  }

  function renderActivePayrollRuns(runs) {
    var tbody = document.getElementById('active-payroll-runs-body');
    if (!tbody) return;

    var filtered = filterActiveRuns(runs);
    if (!filtered || !filtered.length) {
      tbody.innerHTML = emptyActiveRow();
      return;
    }

    tbody.innerHTML = filtered.map(function (run) {
      var ref = run.billing_ref_no || run.run_id;
      var exportBtn = canExport(run)
        ? exportButton(ref, 'Export Client Billing (.xlsx)')
        : '<span class="text-muted small">Choose a site-specific run</span>';
      var isHighlighted = global.__highlightPayrollRun
        && (run.run_id === global.__highlightPayrollRun || ref === global.__highlightPayrollRun);
      var rowCls = 'border-b border-slate-200' + (isHighlighted ? ' table-warning' : '');

      return '<tr class="' + rowCls + '" data-run-row="' + esc(run.run_id) + '">'
        + '<td class="border-r border-slate-200 px-4 py-3 font-semibold text-brand-navy">' + esc(ref) + '</td>'
        + '<td class="border-r border-slate-200 px-4 py-3">' + esc(run.client_name || run.client) + '</td>'
        + '<td class="border-r border-slate-200 px-4 py-3 text-center">' + payrollTypeBadge(run.payroll_type) + '</td>'
        + '<td class="border-r border-slate-200 px-4 py-3">' + esc(run.deployment_site) + '</td>'
        + '<td class="border-r border-slate-200 px-4 py-3">' + esc(run.cutoff_period || run.cutoff) + '</td>'
        + '<td class="border-r border-slate-200 px-4 py-3 text-center">' + esc(String(run.employee_count)) + '</td>'
        + '<td class="border-r border-slate-200 px-4 py-3">' + statusBadge(run.status) + ' <span class="d-block text-muted small">' + esc(run.current_status || '') + '</span></td>'
        + '<td class="px-4 py-3 text-right">' + exportBtn + '<button class="btn btn-sm btn-outline-primary mt-1" type="button" data-billing-import="' + esc(run.run_id) + '">Import Updated Billing</button></td>'
        + '</tr>';
    }).join('');

    if (global.__highlightPayrollRun) {
      var target = tbody.querySelector('[data-run-row="' + global.__highlightPayrollRun + '"]');
      if (target && target.scrollIntoView) {
        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
      global.__highlightPayrollRun = null;
    }
  }

  function filterHistoryRuns(historyList) {
    var client = state.historyFilters.client;
    var status = state.historyFilters.status;
    var query = state.historyFilters.query.trim().toLowerCase();

    return (historyList || []).filter(function (run) {
      var site = document.getElementById('filter-history-site'), type = document.getElementById('filter-history-type'), start = document.getElementById('filter-history-start'), end = document.getElementById('filter-history-end');
      if (site && site.value && String(run.site_id) !== site.value) return false;
      if (type && type.value && run.payroll_type !== type.value) return false;
      if (start && start.value && run.period_start < start.value) return false;
      if (end && end.value && run.period_end > end.value) return false;
      if (client && (run.client_name || run.client) !== client) {
        return false;
      }
      if (status && run.status !== status) {
        return false;
      }
      if (query) {
        var haystack = [
          run.billing_ref_no,
          run.run_id,
          run.deployment_site,
          run.client_name,
          run.client,
          run.cutoff_period,
          run.cutoff
        ].join(' ').toLowerCase();
        if (haystack.indexOf(query) === -1) {
          return false;
        }
      }
      return true;
    });
  }

  function renderPayrollHistory(historyList) {
    var tbody = document.getElementById('payroll-history-body');
    if (!tbody) return;

    var filtered = filterHistoryRuns(historyList);
    if (!filtered.length) {
      tbody.innerHTML = emptyHistoryRow();
      return;
    }

    tbody.innerHTML = filtered.map(function (run) {
      var ref = run.billing_ref_no || run.run_id;
      var summaryBtn = '<button type="button" class="btn btn-sm btn-outline-primary me-1" '
        + 'data-history-summary="' + esc(ref) + '">'
        + '<i class="bi bi-eye me-1"></i> View Summary</button>';
      var downloadBtn = canExport(run)
        ? exportButton(ref, 'Re-Download .xlsx', 'btn-outline-secondary').replace('?ref=', '?archive=1&ref=')
        : '<span class="text-muted small">Unavailable</span>';

      return '<tr class="border-b border-slate-200">'
        + '<td class="border-r border-slate-200 px-4 py-3 font-semibold text-brand-navy">' + esc(ref) + '</td>'
        + '<td class="border-r border-slate-200 px-4 py-3">' + esc(run.client_name || run.client) + '</td>'
        + '<td class="border-r border-slate-200 px-4 py-3">' + esc(run.deployment_site) + '</td>'
        + '<td class="border-r border-slate-200 px-4 py-3">' + esc(run.cutoff_period || run.cutoff) + '</td>'
        + '<td class="border-r border-slate-200 px-4 py-3 text-center">' + esc(String(run.employee_count)) + '</td>'
        + '<td class="border-r border-slate-200 px-4 py-3 text-right tabular-nums">' + formatMoney(run.total_invoice) + '</td>'
        + '<td class="border-r border-slate-200 px-4 py-3">' + statusBadge(run.status) + '</td>'
        + '<td class="px-4 py-3 text-right">' + summaryBtn + downloadBtn + '<button class="btn btn-sm btn-outline-primary mt-1" type="button" data-billing-import="' + esc(run.run_id) + '">Import Updated Billing</button></td>'
        + '</tr>';
    }).join('');

    tbody.querySelectorAll('[data-history-summary]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        openHistorySummary(btn.getAttribute('data-history-summary'));
      });
    });
  }

  function populateClientFilter(clients) {
    var select = document.getElementById('filter-history-client');
    if (!select) return;

    var current = select.value || state.historyFilters.client;
    select.innerHTML = '<option value="">All Clients</option>'
      + (clients || []).map(function (client) {
        return '<option value="' + esc(client) + '">' + esc(client) + '</option>';
      }).join('');
    select.value = current;
  }

  function openHistorySummary(ref) {
    global.PayrollBilling.details(ref);
  }

  function bindHistoryFilters() {
    var clientSelect = document.getElementById('filter-history-client');
    var statusSelect = document.getElementById('filter-history-status');
    var searchInput = document.getElementById('search-history-query');

    if (clientSelect) {
      clientSelect.addEventListener('change', function () {
        state.historyFilters.client = clientSelect.value;
        renderPayrollHistory(state.historyRuns);
      });
    }
    if (statusSelect) {
      statusSelect.addEventListener('change', function () {
        state.historyFilters.status = statusSelect.value;
        renderPayrollHistory(state.historyRuns);
      });
    }
    if (searchInput) {
      searchInput.addEventListener('input', function () {
        state.historyFilters.query = searchInput.value;
        renderPayrollHistory(state.historyRuns);
      });
    }
  }

  function populatePayrollFilterOptions(runList) {
    var companySelect = document.getElementById('payrollFilterCompany');
    var periodSelect = document.getElementById('payrollFilterPeriod');
    if (!companySelect && !periodSelect) return;

    var companies = {};
    var periods = {};
    (runList || []).forEach(function (run) {
      if (run.company_id != null) {
        companies[run.company_id] = run.client_name || run.client || ('Company #' + run.company_id);
      }
      var label = run.cutoff_period || run.cutoff;
      if (label) periods[label] = label;
    });

    if (companySelect) {
      var currentCompany = companySelect.value;
      companySelect.innerHTML = '<option value="">All Companies</option>'
        + Object.keys(companies).map(function (id) {
          return '<option value="' + esc(id) + '">' + esc(companies[id]) + '</option>';
        }).join('');
      companySelect.value = currentCompany;
    }
    if (periodSelect) {
      var currentPeriod = periodSelect.value;
      periodSelect.innerHTML = '<option value="">All Periods</option>'
        + Object.keys(periods).map(function (label) {
          return '<option value="' + esc(label) + '">' + esc(label) + '</option>';
        }).join('');
      periodSelect.value = currentPeriod;
    }
  }

  function bindActiveRunFilters() {
    var typeGroup = document.getElementById('payrollFilterTypeGroup');
    if (typeGroup) {
      typeGroup.querySelectorAll('.payroll-type-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
          typeGroup.querySelectorAll('.payroll-type-btn').forEach(function (b) {
            b.classList.remove('bg-brand-navy', 'text-white');
            b.classList.add('text-slate-600');
          });
          btn.classList.add('bg-brand-navy', 'text-white');
          btn.classList.remove('text-slate-600');
          state.activeFilters.payrollType = btn.getAttribute('data-value') || '';
          renderActivePayrollRuns(state.activeRuns);
        });
      });
    }

    var applyBtn = document.getElementById('payrollFilterApplyBtn');
    if (applyBtn) {
      applyBtn.addEventListener('click', function () {
        var companySelect = document.getElementById('payrollFilterCompany');
        var periodSelect = document.getElementById('payrollFilterPeriod');
        var statusSelect = document.getElementById('payrollFilterStatus');
        state.activeFilters.companyId = companySelect ? companySelect.value : '';
        state.activeFilters.period = periodSelect ? periodSelect.value : '';
        state.activeFilters.status = statusSelect ? statusSelect.value : '';
        renderActivePayrollRuns(state.activeRuns);
      });
    }
  }

  function loadPayrollRuns() {
    var activeBody = document.getElementById('active-payroll-runs-body');
    var historyBody = document.getElementById('payroll-history-body');

    if (activeBody) {
      activeBody.innerHTML = '<tr><td colspan="8" class="px-4 py-6 text-center text-slate-500">Loading payroll runs…</td></tr>';
    }
    if (historyBody) {
      historyBody.innerHTML = '<tr><td colspan="8" class="px-4 py-6 text-center text-slate-500">Loading payroll history…</td></tr>';
    }

    return fetch(API, { credentials: 'include' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data || !data.success) {
          var msg = esc((data && data.message) || 'Failed to load payroll runs.');
          if (activeBody) {
            activeBody.innerHTML = '<tr><td colspan="8" class="px-4 py-6 text-center text-red-600">' + msg + '</td></tr>';
          }
          if (historyBody) {
            historyBody.innerHTML = '<tr><td colspan="8" class="px-4 py-6 text-center text-red-600">' + msg + '</td></tr>';
          }
          return;
        }

        if (data.export_base) exportBase = data.export_base;
        state.activeRuns = data.active_runs || [];
        state.historyRuns = data.history_runs || [];
        state.clients = data.clients || [];

        if (!state.activeRuns.length && data.runs && data.runs.length) {
          state.activeRuns = data.runs.filter(function (run) {
            return run.status === 'Draft' || run.status === 'Pending';
          });
          state.historyRuns = data.runs.filter(function (run) {
            return run.status === 'Approved' || run.status === 'Exported';
          });
        }

        var siteFilter = document.getElementById('filter-history-site');
        if (siteFilter) { var selectedSite = siteFilter.value, sites = {}; state.historyRuns.concat(state.activeRuns).forEach(function (run) { if (run.site_id) sites[run.site_id] = (run.client_name || '') + ' / ' + run.deployment_site; }); siteFilter.innerHTML = '<option value="">All sites</option>' + Object.keys(sites).map(function (id) { return '<option value="' + esc(id) + '">' + esc(sites[id]) + '</option>'; }).join(''); siteFilter.value = selectedSite; }
        populateClientFilter(state.clients);
        populatePayrollFilterOptions(state.activeRuns.concat(state.historyRuns));
        renderActivePayrollRuns(state.activeRuns);
        renderPayrollHistory(state.historyRuns);
      })
      .catch(function (err) {
        console.warn('accounting payroll runs:', err);
        if (activeBody) {
          activeBody.innerHTML = '<tr><td colspan="8" class="px-4 py-6 text-center text-red-600">Unable to load payroll runs.</td></tr>';
        }
        if (historyBody) {
          historyBody.innerHTML = '<tr><td colspan="8" class="px-4 py-6 text-center text-red-600">Unable to load payroll history.</td></tr>';
        }
      });
  }

  global.Accounting = {
    load: loadPayrollRuns,
    renderActivePayrollRuns: renderActivePayrollRuns,
    renderPayrollHistory: renderPayrollHistory
  };

  global.AccountingPayroll = {
    load: loadPayrollRuns
  };

  document.addEventListener('DOMContentLoaded', function () {
    bindHistoryFilters();
    ['filter-history-site','filter-history-type','filter-history-start','filter-history-end'].forEach(function (id) { var field = document.getElementById(id); if (field) field.addEventListener('change', function () { renderPayrollHistory(state.historyRuns); }); });
    bindActiveRunFilters();

    var refreshBtn = document.getElementById('btnRefreshPayrollRuns');
    if (refreshBtn) {
      refreshBtn.addEventListener('click', loadPayrollRuns);
    }

    var refreshHistoryBtn = document.getElementById('btnRefreshPayrollHistory');
    if (refreshHistoryBtn) {
      refreshHistoryBtn.addEventListener('click', loadPayrollRuns);
    }

    var summaryClose = document.getElementById('payrollHistorySummaryClose');
    var summaryModal = document.getElementById('payrollHistorySummaryModal');
    if (summaryClose && summaryModal) {
      summaryClose.addEventListener('click', function () {
        if (global.bootstrap) {
          global.bootstrap.Modal.getOrCreateInstance(summaryModal).hide();
        } else {
          summaryModal.classList.add('hidden');
          summaryModal.classList.remove('flex');
        }
      });
    }
  });
})(window);