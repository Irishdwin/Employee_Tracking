/* Add Back and private attendance evidence; all authority remains in the API. */
(function () {
  'use strict';
  const api = '../../Api/Common/add_back_requests.php';
  const employee = document.getElementById('employeeAddBack');
  const accounting = document.getElementById('accountingAddBack');
  if (!employee && !accounting) return;
  const esc = value => { const el = document.createElement('span'); el.textContent = value == null ? '' : String(value); return el.innerHTML; };
  const money = value => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(value || 0));
  const stamp = value => value ? new Date(String(value).replace(' ', 'T')).toLocaleString('en-PH', { hour12: true }) : '—';
  async function request(url = api, body) {
    const response = await fetch(url, { credentials: 'same-origin', cache: 'no-store', ...(body ? { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) } : {}) });
    const data = await response.json();
    if (!response.ok || !data.success) throw new Error(data.message || 'The request could not be completed.');
    return data;
  }
  const modal = document.createElement('div');
  modal.className = 'modal fade'; modal.id = 'addBackModal'; modal.tabIndex = -1;
  modal.innerHTML = '<div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Attendance evidence</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div></div></div>';
  document.body.appendChild(modal);
  const dialog = bootstrap.Modal.getOrCreateInstance(modal);
  function show(title, html) { modal.querySelector('.modal-title').textContent = title; modal.querySelector('.modal-body').innerHTML = html; dialog.show(); }
  function alertBox(message, success = false) { return '<div role="status" class="alert ' + (success ? 'alert-success' : 'alert-warning') + '">' + esc(message) + '</div>'; }
  function scheduleText(s) { return s ? esc((s.shift_name || 'Shift') + ' · ' + s.time_in + '–' + s.time_out + ' · Break ' + (s.break_minutes ?? s.break_duration ?? 0) + ' min') : '—'; }
  function original(a, hours) {
    if (!a) return '<p>No clock record available.</p>';
    return '<div class="rounded border p-3 mb-3"><strong>Original attendance · ' + esc(a.Date) + '</strong><p class="mb-1">Clock In: ' + esc(stamp(a.TimeIn)) + ' · Clock Out: ' + esc(stamp(a.TimeOut)) + '</p><p class="mb-1">Rendered: ' + esc(hours) + ' hrs · Status: ' + esc(a.attendance_status) + '</p><p class="mb-0 small">' + esc(a.geofence_status || 'Geofence result unavailable') + (a.gps_accuracy != null ? ' · Accuracy ±' + esc(a.gps_accuracy) + 'm' : '') + '</p><p class="small mb-0">Clock In GPS: ' + esc(a.clockin_latitude ?? '—') + ', ' + esc(a.clockin_longitude ?? '—') + ' · Clock Out GPS: ' + esc(a.clockout_latitude ?? '—') + ', ' + esc(a.clockout_longitude ?? '—') + '</p></div>';
  }
  function photos(items) {
    if (!items.length) return '<p class="text-muted">No recorded clock photos available.</p>';
    return '<div class="row g-3">' + items.map(item => '<div class="col-md-6"><h6>' + (item.type === 'clockin' ? 'Clock In' : 'Clock Out') + '</h6><a href="' + esc(item.url) + '" target="_blank" rel="noopener"><img src="' + esc(item.url) + '" alt="' + (item.type === 'clockin' ? 'Clock In evidence' : 'Clock Out evidence') + '" class="border rounded w-100" style="max-height:420px;object-fit:contain;background:#eff4fa"></a><a class="btn btn-sm btn-outline-primary mt-2" href="' + esc(item.url) + '&download=1">Download ' + (item.type === 'clockin' ? 'Clock In' : 'Clock Out') + '</a></div>').join('') + '</div>';
  }
  const host = employee || accounting;
  host.innerHTML = '<div class="d-flex flex-wrap justify-content-between gap-2 mb-3">' + (employee ? '<h2 class="h5 fw-bold mb-0">Add Back Requests</h2>' : '<span class="fw-semibold">Submitted requests</span>') + '<div>' + (employee ? '<button type="button" class="btn btn-primary btn-sm me-2" id="addBackNew">New Request</button>' : '') + '<button type="button" class="btn btn-outline-primary btn-sm" id="addBackRefresh">Refresh</button></div></div><p class="small text-muted">Restore omitted duty through Accounting verification. Original attendance remains unchanged.</p><div id="addBackNotice"></div><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Request</th>' + (accounting ? '<th>Employee / Site</th>' : '') + '<th>Work Date</th><th>Missing Hours</th><th>Status</th><th>Accounting Remarks</th><th></th></tr></thead><tbody id="addBackRows"></tbody></table></div>';
  async function refresh() {
    try {
      const data = await request();
      document.getElementById('addBackRows').innerHTML = data.requests.map(r => '<tr><td>#' + esc(r.RequestID) + '</td>' + (accounting ? '<td>' + esc(r.FirstName + ' ' + r.LastName) + '<br><small>' + esc(r.CompanyName + ' · ' + r.Location) + '</small></td>' : '') + '<td>' + esc(r.WorkDate) + '</td><td>' + esc(r.RequestedHours) + ' hrs' + (r.ApprovedAmount != null ? '<br><small>' + money(r.ApprovedAmount) + '</small>' : '') + '</td><td><span class="badge ' + (r.StatusName === 'Approved' ? 'bg-success' : r.StatusName === 'Rejected' ? 'bg-danger' : 'bg-warning text-dark') + '">' + (r.StatusName === 'Pending' ? 'Pending Verification' : esc(r.StatusName)) + '</span></td><td>' + esc(r.ReviewRemarks || '—') + '</td><td><button type="button" class="btn btn-sm btn-outline-primary" data-addback-view="' + esc(r.RequestID) + '">' + (accounting ? 'Review' : 'View') + '</button></td></tr>').join('') || '<tr><td colspan="7" class="text-muted">No Add Back requests yet.</td></tr>';
      document.getElementById('addBackNotice').innerHTML = '';
    } catch (error) { document.getElementById('addBackNotice').innerHTML = alertBox(error.message); }
  }
  async function view(id) {
    try {
      const { request: r } = await request(api + '?request_id=' + encodeURIComponent(id));
      const items = r.evidence.map(ev => ({ type: ev.EvidenceType, url: ev.url }));
      show('Add Back Request #' + r.RequestID, '<div id="addBackReviewNotice"></div><h6>' + esc(r.FirstName + ' ' + r.LastName + ' · ' + r.EmployeeNumber) + '</h6><p>' + esc(r.CompanyName + ' · ' + r.Location + ' · ' + (r.Position || '—')) + '</p><p>' + scheduleText(r.source.schedule) + '</p>' + original(r.source.attendance, r.OriginalHours) + '<p><strong>Requested actual duty:</strong> ' + esc(stamp(r.DutyStart)) + '–' + esc(stamp(r.DutyEnd)) + '<br><strong>Missing:</strong> ' + esc(r.RequestedHours) + ' hrs</p><p><strong>Reason:</strong> ' + esc(r.Reason) + '</p><p class="small">Submitted ' + esc(stamp(r.SubmittedAt)) + (r.ReviewedAt ? ' · Reviewed ' + esc(stamp(r.ReviewedAt)) + ' by user #' + esc(r.ReviewedBy) : '') + ' · ' + esc(r.StatusName) + '</p>' + (r.ReviewRemarks ? '<p><strong>Accounting:</strong> ' + esc(r.ReviewRemarks) + '</p>' : '') + (r.source.locked_dtr ? alertBox('This date belongs to a submitted DTR. The correction is a separate payroll adjustment.') : '') + (r.ApprovedAmount != null ? '<p><strong>Approved Add Back:</strong> ' + money(r.ApprovedAmount) + ' · Payment cutoff ' + esc(r.TargetStart + '–' + r.TargetEnd) + (r.Payroll_ID ? ' · Payroll #' + esc(r.Payroll_ID) : ' · Awaiting payroll processing') + '</p>' : '') + photos(items) + (accounting && r.StatusName === 'Pending' ? '<div class="border-top mt-4 pt-3">' + (r.approval_blocked ? alertBox(r.approval_blocked) : '<p><strong>Production calculation:</strong> ' + money(r.quote.amount) + ' · ' + esc(r.quote.rate_source) + '</p>') + '<label class="form-label" for="addBackTarget">Date within the payment cutoff</label><input type="date" id="addBackTarget" class="form-control mb-3" value="' + esc(r.default_target?.start || '') + '"><label class="form-label" for="addBackRemarks">Accounting remarks (required to reject)</label><textarea id="addBackRemarks" maxlength="2000" class="form-control mb-3"></textarea><button type="button" class="btn btn-success me-2" id="addBackApprove" ' + (r.approval_blocked ? 'disabled' : '') + '>Approve Add Back</button><button type="button" class="btn btn-danger" id="addBackReject">Reject</button></div>' : ''));
      if (accounting && r.StatusName === 'Pending') {
        const review = async decision => {
          const remarks = document.getElementById('addBackRemarks').value.trim();
          if (decision === 'reject' && remarks.length < 5) { document.getElementById('addBackReviewNotice').innerHTML = alertBox('Enter a rejection reason of at least 5 characters.'); return; }
          const buttons = [document.getElementById('addBackApprove'), document.getElementById('addBackReject')]; buttons.forEach(b => b.disabled = true);
          try { await request(api, { request_id: Number(id), decision, remarks, ...(decision === 'approve' ? { target_date: document.getElementById('addBackTarget').value } : {}) }); await refresh(); await view(id); }
          catch (error) { document.getElementById('addBackReviewNotice').innerHTML = alertBox(error.message); buttons.forEach(b => b.disabled = false); if (r.approval_blocked) buttons[0].disabled = true; }
        };
        document.getElementById('addBackApprove').onclick = () => review('approve'); document.getElementById('addBackReject').onclick = () => review('reject');
      }
    } catch (error) { show('Add Back', alertBox(error.message)); }
  }
  async function evidence(date) {
    try {
      const { context: c } = await request(api + '?work_date=' + encodeURIComponent(date));
      const a = c.attendance;
      show('Attendance Evidence · ' + date, '<h6>' + esc((c.employee_name || '') + ' · ' + (c.employee_number || '') + ' · ' + c.company + ' · ' + c.site) + '</h6><p>' + scheduleText(c.schedule) + '</p>' + original(a, c.original_hours) + photos(a ? ['in', 'out'].filter(k => a['clock' + k + '_url']).map(k => ({ type: 'clock' + k, url: a['clock' + k + '_url'] })) : []) + '<button type="button" class="btn btn-primary mt-4" id="addBackFromEvidence">Request missing duty</button>');
      document.getElementById('addBackFromEvidence').onclick = () => newRequest(date);
    } catch (error) { show('Attendance Evidence', alertBox(error.message)); }
  }
  function newRequest(date = '') {
    show('New Add Back Request', '<form id="addBackForm"><div id="addBackFormNotice"></div><label for="addBackDate" class="form-label">Work Date</label><input required type="date" id="addBackDate" class="form-control mb-3" value="' + esc(date) + '"><div id="addBackContext" class="mb-3"></div><label for="addBackStart" class="form-label">Actual duty start</label><input required type="datetime-local" id="addBackStart" class="form-control mb-3"><label for="addBackEnd" class="form-label">Actual duty end</label><input required type="datetime-local" id="addBackEnd" class="form-control mb-3"><p class="small text-muted">Enter the complete duty interval. Missing hours and pay are calculated on the server; overtime requires the existing approved overtime record.</p><label for="addBackReason" class="form-label">Reason for missing duty</label><textarea required minlength="10" maxlength="2000" id="addBackReason" class="form-control mb-3"></textarea><p class="small">Available clock photos will be preserved with this request. At least one recorded photo is required.</p><button type="submit" class="btn btn-primary">Submit for Accounting verification</button></form>');
    const loadContext = async () => {
      try {
        const { context: c } = await request(api + '?work_date=' + encodeURIComponent(document.getElementById('addBackDate').value));
        document.getElementById('addBackContext').innerHTML = '<strong>' + esc(c.company + ' · ' + c.site) + '</strong><p>' + scheduleText(c.schedule) + '</p>' + original(c.attendance, c.original_hours);
        document.getElementById('addBackStart').value = (c.attendance?.TimeIn || document.getElementById('addBackDate').value + 'T08:00').replace(' ', 'T').slice(0, 16);
        document.getElementById('addBackEnd').value = document.getElementById('addBackDate').value + 'T17:00';
      } catch (error) { document.getElementById('addBackContext').innerHTML = alertBox(error.message); }
    };
    document.getElementById('addBackDate').onchange = loadContext; if (date) loadContext();
    document.getElementById('addBackForm').onsubmit = async event => {
      event.preventDefault(); const button = event.target.querySelector('[type="submit"]'); button.disabled = true;
      try {
        const data = await request(api, { work_date: document.getElementById('addBackDate').value, duty_start: document.getElementById('addBackStart').value, duty_end: document.getElementById('addBackEnd').value, reason: document.getElementById('addBackReason').value });
        await refresh(); await view(data.request_id);
      } catch (error) { document.getElementById('addBackFormNotice').innerHTML = alertBox(error.message); button.disabled = false; }
    };
  }
  document.addEventListener('click', event => {
    const evidenceButton = event.target.closest('[data-attendance-evidence-date]'); if (evidenceButton) evidence(evidenceButton.dataset.attendanceEvidenceDate);
    const viewButton = event.target.closest('[data-addback-view]'); if (viewButton) view(viewButton.dataset.addbackView);
  });
  document.getElementById('addBackRefresh').onclick = refresh;
  if (employee) document.getElementById('addBackNew').onclick = () => newRequest();
  refresh(); setInterval(() => { if (!document.hidden && !modal.classList.contains('show')) refresh(); }, 30000);
})();
