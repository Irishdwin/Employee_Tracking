(function (global) {
  'use strict';
  var context, detailsGeneration = 0;
  function esc(value) { var e = document.createElement('span'); e.textContent = value == null ? '—' : String(value); return e.innerHTML; }
  function money(value) { return value == null ? '—' : '₱' + Number(value).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
  function date(value) { return value ? new Date(String(value).replace(' ', 'T')).toLocaleString('en-PH', { hour12: true }) : '—'; }
  function request(url, options) {
    options = options || {}; options.credentials = 'include';
    if (String(options.method || 'GET').toUpperCase() === 'GET') return fetch(url, options);
    if (!context) context = fetch('../../Api/Accounting/payroll_workflow_context.php', { credentials: 'include' }).then(function (r) { return r.json(); }).then(function (data) { if (!data.success) throw new Error(data.message); return data; });
    return context.then(function (data) { options.headers = Object.assign({}, options.headers, { 'X-Payroll-CSRF': data.csrf_token }); return fetch(url, options); });
  }
  function modal(id, title) {
    var element = document.getElementById(id);
    if (!element) {
      element = document.createElement('div'); element.id = id; element.className = 'modal fade'; element.tabIndex = -1;
      element.setAttribute('aria-labelledby', id + '-title');
      element.innerHTML = '<div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="' + id + '-title">' + esc(title) + '</h5><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"></div></div></div>';
      document.body.appendChild(element);
    }
    global.bootstrap.Modal.getOrCreateInstance(element).show(); return element.querySelector('.modal-body');
  }
  function detail(run) {
    var generation = ++detailsGeneration;
    var body = modal('billingRunDetails', 'Payroll Run Details'); body.innerHTML = '<p role="status">Loading historical payroll…</p>';
    request('../../Api/Accounting/get_payroll_run_details.php?run_id=' + encodeURIComponent(run)).then(function (r) { return r.json(); }).then(function (data) {
      if (generation !== detailsGeneration) return;
      if (!data.success) throw new Error(data.message);
      var r = data.run, t = data.totals, employees = data.employees || [], hasEmployees = employees.length > 0;
      function metric(label, value, hint, primary) {
        return '<div class="prd-metric'+(primary?' prd-primary':'')+'"><span>'+esc(label)+'</span><strong>'+esc(value)+'</strong><small>'+esc(hint)+'</small></div>';
      }
      function recorded(value) { return value == null || value === '' ? 'Not recorded' : value; }
      function payrollMoney(value) { return hasEmployees ? money(value) : 'Unavailable'; }
      var columns=['Employee / number','Deployment site','Regular hours','Overtime hours','Holiday hours','Basic pay','Overtime pay','Holiday pay','Allowances','Add Back','Deductions','Gross pay','Net pay','Payment date'];
      body.innerHTML = '<div class="prd-summary"><div><div class="prd-eyebrow">Billing reference</div><h2>'+esc(r.BillingRefNo || r.RunCode)+'</h2><p>'+esc(r.client_name)+'</p><div class="prd-badges"><span class="badge bg-primary">'+esc(recorded(r.CurrentStatus))+'</span><span class="badge bg-secondary">Run: '+esc(recorded(r.Status))+'</span></div></div><dl><div><dt>Deployment site</dt><dd>'+esc(recorded((data.sites || []).join(' / ') || r.DeploymentSite))+'</dd></div><div><dt>Payroll period</dt><dd>'+esc(r.CutoffLabel || r.PeriodStart+' – '+r.PeriodEnd)+'</dd></div><div><dt>Payroll type</dt><dd>'+esc(r.PayrollType ? 'Type '+r.PayrollType : 'Not recorded')+'</dd></div></dl></div>'
        + (data.legacy ? '<div class="prd-note"><strong>Historical payroll record</strong><p>These figures come from saved records. “Unavailable” or “—” means that the detailed amount was not recorded; it does not mean zero.</p></div>' : '')
        + (!hasEmployees ? '<div class="prd-note prd-warning"><strong>Employee details are unavailable for this run</strong><p>No employee payroll records are linked to this saved run. The client invoice below is recorded separately, so employee totals cannot be shown.</p></div>' : '')
        + '<section class="prd-section"><h3>Employee payroll</h3><p class="prd-help">Pay earned by employees for this payroll period.</p><div class="prd-grid">'
        + metric('Employees with payroll details',hasEmployees ? data.employee_count : 'Unavailable','Linked employee records')
        + metric('Regular hours',hasEmployees ? t.regular_hours : 'Unavailable','Total regular duty hours')
        + metric('Overtime hours',hasEmployees ? t.ot_hours : 'Unavailable','Total extra duty hours')
        + metric('Gross payroll',payrollMoney(t.gross),'Employee pay before deductions')
        + metric('Total deductions',payrollMoney(t.deductions),'Amounts deducted from employee pay')
        + metric('Net payroll',payrollMoney(t.net),'Employee take-home pay',true)+'</div></section>'
        + '<section class="prd-section"><h3>Client billing</h3><p class="prd-help">Amounts billed to the client company.</p><div class="prd-grid prd-billing">'
        + metric('Agency fee',money(data.billing && data.billing.agency_fee),'Agency service charge')
        + metric('VAT',money(data.billing && data.billing.vat),'Tax recorded on the billing')
        + metric('Total client invoice',money(r.TotalInvoice),'Total amount billed to the client',true)+'</div></section>'
        + '<section class="prd-section"><h3>Employee breakdown</h3>'
        + (hasEmployees ? '<p class="prd-help">Amounts are in Philippine pesos (₱). Scroll sideways to see all pay components. Add Back is an adjustment added to employee pay.</p><div class="prd-table" tabindex="0" role="region" aria-label="Employee payroll breakdown"><table><thead><tr>'+columns.map(function(label){return '<th scope="col">'+esc(label)+'</th>';}).join('')+'</tr></thead><tbody>'
          + employees.map(function(employee){return '<tr><th scope="row">'+esc(employee.name)+'<small>'+esc(employee.employee_number)+'</small></th><td>'+esc(employee.site)+'</td>'+['regular_hours','ot_hours','holiday_hours'].map(function(key){return '<td class="prd-number">'+esc(employee[key])+'</td>';}).join('')+['basic','ot','holiday','allowances','add_back','deductions','gross','net'].map(function(key){return '<td class="prd-number">'+money(employee[key])+'</td>';}).join('')+'<td>'+esc(recorded(employee.payment_date))+'</td></tr>';}).join('')+'</tbody></table></div>'
          : '<div class="prd-empty">No linked employee breakdown is available.</div>')+'</section>'
        + '<section class="prd-section"><h3>Status history</h3>'+(data.history.length ? data.history.map(function(entry){return '<article class="prd-history"><strong>'+esc(entry.status)+'</strong><div class="prd-help">'+esc(date(entry.created_at))+' · '+esc(recorded(entry.updated_by))+'</div><p>'+esc(entry.remarks || '')+'</p>'+(entry.proof_url ? '<a target="_blank" rel="noopener" href="'+esc(entry.proof_url)+'">View proof</a>' : '')+'</article>';}).join('') : '<div class="prd-empty">No status changes were recorded for this run.</div>')+'</section>'
        + '<section class="prd-section"><h3>Billing files</h3>'+(data.exports.length || data.revisions.length ? data.exports.map(function(file){return '<div class="prd-file"><div><strong>Original export #'+esc(file.ExportID)+'</strong><small>'+esc(date(file.CreatedAt))+'</small></div><a class="btn btn-sm btn-outline-success" href="../../Api/Accounting/download_billing_file.php?export_id='+Number(file.ExportID)+'">Download</a></div>';}).join('')+data.revisions.map(function(revision){return '<div class="prd-file"><div><strong>Revision #'+esc(revision.RevisionID)+'</strong><small>'+esc(date(revision.ConfirmedAt))+' · '+esc(recorded(revision.imported_by))+' · '+esc(revision.ChangedEmployees)+' employee(s)</small><p>'+esc(revision.Reason)+'</p></div><a class="btn btn-sm btn-outline-success" href="../../Api/Accounting/download_billing_file.php?revision_id='+Number(revision.RevisionID)+'">Download workbook</a></div>';}).join('') : '<div class="prd-empty">No billing files are saved for this run.</div>')+'</section>'
        + '<dl class="prd-audit"><div><dt>Created</dt><dd>'+esc(date(r.CreatedAt))+'</dd></div><div><dt>Approved</dt><dd>'+esc(r.ApprovedAt ? date(r.ApprovedAt) : 'Not recorded')+'</dd></div><div><dt>Created by</dt><dd>'+esc(recorded(r.created_by))+'</dd></div></dl>';

    }).catch(function (error) { if (generation === detailsGeneration) body.innerHTML = '<div class="alert alert-danger" role="alert">' + esc(error.message) + '</div>'; });
  }
  function importBilling(run) {
    var body = modal('billingImportReview', 'Import Updated Billing');
    body.innerHTML = '<p>Upload the workbook exported for this run. All employee-row billing values can be revised, including rates, days, hours, earnings, deductions and client charges. Employee names, designation and area changes apply to this billing record. The original employee IDs, client and payroll period identify the matching payroll. Keep existing formulas or replace them with a corrected numeric value. Finalize payroll before revising amounts.</p><form id="billing-preview-form"><label class="form-label" for="billing-workbook">Returned XLSX workbook (maximum 10 MB)</label><input id="billing-workbook" name="workbook" class="form-control mb-3" type="file" accept=".xlsx" required><button class="btn btn-primary" type="submit">Preview Changes</button></form><div id="billing-preview" class="mt-4" aria-live="polite"></div>';
    var form = body.querySelector('form'), output = body.querySelector('#billing-preview');
    form.addEventListener('submit', function (event) {
      event.preventDefault(); var button = form.querySelector('button'); button.disabled = true;
      output.innerHTML = '<p role="status">Validating workbook…</p>';
      var fd = new FormData(form); fd.append('action', 'preview'); fd.append('run_id', run);
      request('../../Api/Accounting/import_client_billing.php', { method: 'POST', body: fd }).then(function (r) { return r.json(); }).then(function (data) {
        if (!data.success) throw new Error(data.message);
        var p = data.preview;
        output.innerHTML = '<div class="alert alert-info"><strong>' + esc(p.billing_ref) + '</strong><br>' + esc(p.client) + ' · ' + esc(p.site) + '<br>' + esc(p.cutoff) + '<br>Changed employees: ' + p.changed_employees + ' · Unchanged: ' + p.unchanged_employees + ' · Invalid rows: ' + p.invalid_rows + '</div>'
          + '<p>' + esc(data.message) + '</p>'
          + (p.changes.length ? '<div class="table-responsive"><table class="table table-bordered"><thead><tr><th>Employee</th><th>Field</th><th>Current value</th><th>Imported value</th><th>Difference</th></tr></thead><tbody>' + p.changes.map(function (change) { return '<tr><td>' + esc(change.employee_name) + '<div class="small text-muted">' + esc(change.employee_number) + '</div></td><td>' + esc(change.label || change.component.replace(/_/g,' ')) + '</td><td>' + (typeof change.old==='number'?esc(change.old.toLocaleString('en-PH',{maximumFractionDigits:2})):esc(change.old)) + '</td><td>' + (typeof change.new==='number'?esc(change.new.toLocaleString('en-PH',{maximumFractionDigits:2})):esc(change.new)) + '</td><td>' + (change.difference==null?'—':esc(change.difference.toLocaleString('en-PH',{maximumFractionDigits:2}))) + '</td></tr>'; }).join('') + '</tbody></table></div>' : '');
        if(p.impacts && p.impacts.length) output.insertAdjacentHTML('beforeend','<h6 class="mt-3">Result after recalculation</h6><div class="table-responsive"><table class="table table-bordered"><thead><tr><th>Employee</th><th>Gross pay</th><th>Deductions</th><th>Net pay</th><th>Client invoice</th></tr></thead><tbody>'+p.impacts.map(function(i){return '<tr><td>'+esc(i.employee_name)+'</td>'+['gross','deductions','net','invoice'].map(function(k){return '<td class="text-nowrap">'+money(i[k+'_before'])+' → '+money(i[k+'_after'])+'</td>';}).join('')+'</tr>';}).join('')+'</tbody></table></div>');
        if (!p.changes.length || data.already_confirmed) return;
        var confirm = document.createElement('form'); confirm.innerHTML = '<label class="form-label" for="billing-reason">Reason for revision</label><textarea class="form-control mb-3" id="billing-reason" name="reason" maxlength="2000" required></textarea><div class="alert alert-warning">Confirming saves the revised payroll and billing values and notifies each affected employee. Source attendance records and submitted DTRs remain unchanged.</div><button class="btn btn-success" type="submit">Confirm ' + p.changed_employees + ' Employee Update(s)</button><div class="mt-2" role="status"></div>'; output.appendChild(confirm);
        confirm.addEventListener('submit', function (ev) {
          ev.preventDefault(); var btn = confirm.querySelector('button'), status = confirm.querySelector('[role="status"]'); btn.disabled = true;
          var commit = new FormData(confirm); commit.append('action', 'confirm'); commit.append('preview_token', data.preview_token);
          request('../../Api/Accounting/import_client_billing.php', { method: 'POST', body: commit }).then(function (r) { return r.json(); }).then(function (result) {
            if (!result.success) throw new Error(result.message);
            output.innerHTML = '<div class="alert alert-success" role="status">' + esc(result.message || 'This revision was already confirmed.') + '</div>';
            if (global.Accounting) global.Accounting.load();
          }).catch(function (error) { btn.disabled = false; status.textContent = error.message; status.className = 'text-danger mt-2'; });
        });
      }).catch(function (error) { output.innerHTML = '<div class="alert alert-danger" role="alert">' + esc(error.message) + '</div>'; }).finally(function () { button.disabled = false; });
    });
  }
  document.addEventListener('click', function (event) { var button = event.target.closest('[data-billing-import]'); if (button) importBilling(button.getAttribute('data-billing-import')); });
  global.PayrollBilling = { request: request, details: detail, importBilling: importBilling };
})(window);
