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
  let csrf='';
  async function request(url = api, body) {
    if(body && !csrf)await request();
    const multipart=body instanceof FormData;
    const response = await fetch(url, { credentials: 'same-origin', cache: 'no-store', ...(body ? { method: 'POST', headers: { 'X-AddBack-CSRF':csrf,...(multipart?{}:{'Content-Type':'application/json'}) }, body: multipart?body:JSON.stringify(body) } : {}) });
    const data = await response.json();if(data.csrf_token)csrf=data.csrf_token;
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
    if (!items.length) return '<p class="text-muted">No image evidence available.</p>';
    return '<div class="row g-3">'+items.map(item=>{const uploaded=item.type.startsWith('upload');const label=uploaded?'Employee-uploaded supporting evidence '+item.type.slice(6):'System-recorded '+(item.type==='clockin'?'Clock In':'Clock Out');return '<div class="col-md-6"><h6>'+esc(label)+'</h6>'+(uploaded?'<p class="small text-muted">Supporting image · subject to Accounting verification</p>':'')+'<a href="'+esc(item.url)+'" target="_blank" rel="noopener"><img src="'+esc(item.url)+'" alt="'+esc(label)+'" class="border rounded w-100" style="max-height:420px;object-fit:contain;background:#eff4fa"></a><a class="btn btn-sm btn-outline-primary mt-2" href="'+esc(item.url)+'&download=1">Download image</a></div>';}).join('')+'</div>';
  }
  const host = employee || accounting;
  host.innerHTML = '<div class="d-flex flex-wrap justify-content-between gap-2 mb-3">' + (employee ? '<h2 class="h5 fw-bold mb-0">Add Back Requests</h2>' : '<span class="fw-semibold">Submitted requests</span>') + '<div>' + (employee ? '<button type="button" class="btn btn-primary btn-sm me-2" id="addBackNew">New Request</button>' : '') + '<button type="button" class="btn btn-outline-primary btn-sm" id="addBackRefresh">Refresh</button></div></div><p class="small text-muted">Restore omitted duty through Accounting verification. Original attendance remains unchanged.</p><div id="addBackNotice"></div><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Request</th>' + (accounting ? '<th>Employee / Site</th>' : '') + '<th>Work Date</th><th>Missing Hours</th><th>Status</th><th>Accounting Remarks</th><th></th></tr></thead><tbody id="addBackRows"></tbody></table></div>';
  if (accounting) {
    const history=document.createElement('section');
    history.className='mt-5 border-top pt-4';
    history.innerHTML='<h3 class="h5 fw-semibold">Add Back History</h3><p class="small text-muted">Settled requests, including approved and rejected decisions. Open a record to review its evidence, remarks and payroll details.</p><div class="table-responsive"><table class="table align-middle"><thead>'+host.querySelector('thead').innerHTML+'</thead><tbody id="addBackHistoryRows"></tbody></table></div>';
    host.appendChild(history);
  }
  function requestRows(rows, history=false) {
    return rows.map(r => '<tr><td>#' + esc(r.RequestID) + '</td>' + (accounting ? '<td>' + esc(r.FirstName + ' ' + r.LastName) + '<br><small>' + esc(r.CompanyName + ' · ' + r.Location) + '</small></td>' : '') + '<td>' + esc(r.WorkDate) + '</td><td>' + esc(r.RequestedHours) + ' hrs' + (r.ApprovedAmount != null ? '<br><small>' + money(r.ApprovedAmount) + '</small>' : '') + '</td><td><span class="badge ' + (r.StatusName === 'Approved' ? 'bg-success' : r.StatusName === 'Rejected' ? 'bg-danger' : 'bg-warning text-dark') + '">' + (r.StatusName === 'Pending' ? 'Pending Verification' : esc(r.StatusName)) + '</span></td><td>' + esc(r.ReviewRemarks || '—') + '</td><td><button type="button" class="btn btn-sm btn-outline-primary" data-addback-view="' + esc(r.RequestID) + '">' + (accounting && !history ? 'Review' : 'View') + '</button></td></tr>').join('') || '<tr><td colspan="7" class="text-muted">'+(history?'No settled Add Back requests yet.':'No submitted Add Back requests awaiting settlement.')+'</td></tr>';
  }
  let refreshing=null;
  function refresh() {
    if(refreshing)return refreshing;
    refreshing=(async()=>{
      try {
        const data=await request();
        const rows=data.requests||[];
        const settled=r=>['Approved','Rejected'].includes(r.StatusName);
        document.getElementById('addBackRows').innerHTML=requestRows(accounting?rows.filter(r=>!settled(r)):rows);
        if(accounting){
          document.getElementById('addBackHistoryRows').innerHTML=requestRows(rows.filter(settled),true);
          const count=rows.filter(r=>!settled(r)).length;
          const badge=document.getElementById('accountingAddBackBadge');
          if(badge){badge.textContent=String(count);badge.classList.toggle('hidden',count===0);badge.setAttribute('aria-label',count+' unsettled Add Back requests');}
        }
        document.getElementById('addBackNotice').innerHTML='';
      }catch(error){document.getElementById('addBackNotice').innerHTML=alertBox(error.message);}
    })();
    return refreshing.finally(()=>{refreshing=null;});
  }
  function rateInfo(r) {
    const q=r.quote;
    return '<p><strong>Work date:</strong> '+esc(r.WorkDate || String(r.DutyStart).slice(0,10))+' · <strong>Existing recorded:</strong> '+esc(r.OriginalHours??'Unavailable')+' hrs</p>' +
      (r.approval_blocked ? alertBox(r.approval_blocked)+'<p>Ask HR/Admin to <a target="_blank" rel="noopener" href="../../Html/HR/add_back_salary_history.html?request_id='+Number(r.RequestID)+'">record documented salary history</a>. Accounting cannot enter or assume historical rates.</p>' :
      '<p><strong>Historical daily rate:</strong> '+(q?.daily_rate!=null?money(q.daily_rate):'Unavailable')+' · <strong>Hourly:</strong> '+(q?.hourly_rate!=null?money(q.hourly_rate):'Unavailable')+'<br><strong>Missing payable:</strong> '+esc(q?.hours??'Unavailable')+' hrs · <strong>Add Back:</strong> '+(q?money(q.amount):'Unavailable')+'<br><strong>Rate source:</strong> '+esc(q?.rate_source??'Unavailable')+' · Effective '+esc(q?.effective_date??'Unavailable')+'</p>')+
      '<button type="button" class="btn btn-outline-primary mb-3" id="addBackRefreshReview">Refresh eligibility</button>';
  }
  async function view(id) {
    try {
      const { request: r } = await request(api + '?request_id=' + encodeURIComponent(id));
      const items = r.evidence.map(ev => ({ type: ev.EvidenceType, url: ev.url }));
      show('Add Back Request #' + r.RequestID, '<div id="addBackReviewNotice"></div><h6>' + esc(r.FirstName + ' ' + r.LastName + ' · ' + r.EmployeeNumber) + '</h6><p>' + esc(r.CompanyName + ' · ' + r.Location + ' · ' + (r.Position || '—')) + '</p><p>' + scheduleText(r.source.schedule) + '</p>' + original(r.source.attendance, r.OriginalHours) + '<p><strong>Requested actual duty:</strong> ' + esc(stamp(r.DutyStart)) + '–' + esc(stamp(r.DutyEnd)) + '<br><strong>Missing:</strong> ' + esc(r.RequestedHours) + ' hrs</p><p><strong>Reason:</strong> ' + esc(r.Reason) + '</p><p class="small">Submitted ' + esc(stamp(r.SubmittedAt)) + (r.ReviewedAt ? ' · Reviewed ' + esc(stamp(r.ReviewedAt)) + ' by user #' + esc(r.ReviewedBy) : '') + ' · ' + esc(r.StatusName) + '</p>' + (r.ReviewRemarks ? '<p><strong>Accounting:</strong> ' + esc(r.ReviewRemarks) + '</p>' : '') + (r.source.locked_dtr ? alertBox('This date belongs to a submitted DTR. The correction is a separate payroll adjustment.') : '') + (r.ApprovedAmount != null ? '<p><strong>Approved Add Back:</strong> ' + money(r.ApprovedAmount) + ' · Payment cutoff ' + esc(r.TargetStart + '–' + r.TargetEnd) + (r.Payroll_ID ? ' · Payroll #' + esc(r.Payroll_ID) : ' · Awaiting payroll processing') + '</p>' : '') + photos(items) + (accounting && r.StatusName === 'Pending' ? '<div class="border-top mt-4 pt-3">' + rateInfo(r) + '<label class="form-label" for="addBackTarget">Date within the payment cutoff</label><input type="date" id="addBackTarget" class="form-control mb-3" value="' + esc(r.default_target?.start || '') + '"><label class="form-label" for="addBackRemarks">Accounting remarks (required to reject)</label><textarea id="addBackRemarks" maxlength="2000" class="form-control mb-3"></textarea><button type="button" class="btn btn-success me-2" id="addBackApprove" ' + (r.approval_blocked ? 'disabled' : '') + '>Approve Add Back</button><button type="button" class="btn btn-danger" id="addBackReject">Reject</button></div>' : ''));
      if (accounting && r.StatusName === 'Pending') {
        document.getElementById('addBackRefreshReview').onclick = () => view(id);
        const review = async decision => {
          const remarks = document.getElementById('addBackRemarks').value.trim();
          if (decision === 'reject' && remarks.length < 5) { document.getElementById('addBackReviewNotice').innerHTML = alertBox('Enter a rejection reason of at least 5 characters.'); return; }
          if (decision === 'approve' && !await AppDialog.confirm('Approve Add Back for '+r.FirstName+' '+r.LastName+' on '+r.WorkDate+'? Missing duty: '+r.quote.hours+' hrs. Daily rate: '+money(r.quote.daily_rate)+', hourly: '+money(r.quote.hourly_rate)+'. Calculated amount: '+money(r.quote.amount)+'. Confirm that the evidence supports this duty.', 'Confirm Add Back approval')) return;
          const buttons = [document.getElementById('addBackApprove'), document.getElementById('addBackReject')]; buttons.forEach(b => b.disabled = true);
          try { await request(api, { request_id: Number(id), decision, remarks, ...(decision === 'approve' ? { target_date: document.getElementById('addBackTarget').value } : {}) }); await refresh(); await view(id); if (decision === 'approve') await AppDialog.alert('The Add Back request was approved successfully and recorded for payroll processing.', 'Add Back approved successfully'); }
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
    show('New Add Back Request', '<form id="addBackForm"><div id="addBackFormNotice" role="alert"></div><label for="addBackDate" class="form-label">Work Date</label><input required type="date" id="addBackDate" class="form-control mb-3" value="'+esc(date)+'"><div id="addBackContext" class="mb-3"></div><label for="addBackStart" class="form-label">Actual duty start</label><input required type="datetime-local" id="addBackStart" class="form-control mb-3"><label for="addBackEnd" class="form-label">Actual duty end</label><input required type="datetime-local" id="addBackEnd" class="form-control mb-3"><p class="small text-muted">Enter the complete duty interval. Missing hours and pay are calculated on the server; overtime requires the existing approved overtime record.</p><label for="addBackReason" class="form-label">Reason for missing duty</label><textarea required minlength="10" maxlength="2000" id="addBackReason" class="form-control mb-3"></textarea><label for="addBackImages" class="form-label fw-semibold">Upload Image Evidence</label><input type="file" id="addBackImages" class="form-control" multiple accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" aria-describedby="addBackImageHelp"><p id="addBackImageHelp" class="small text-muted mt-2">Upload a photo or screenshot supporting your missing duty hours. Accepted formats: JPG, JPEG, PNG, WEBP. Maximum 5 MB per image. Up to 3 images.</p><div id="addBackImageError" class="text-danger small mb-2" role="alert"></div><div id="addBackPreviews" class="row g-2 mb-3"></div><p class="small">Available recorded clock photos are preserved automatically. Provide at least one recorded photo or uploaded image.</p><button type="submit" class="btn btn-primary">Submit for Accounting verification</button></form>');
    const form=document.getElementById('addBackForm');let selected=[],previewUrls=[],context=null,loading=false,submitting=false,contextGeneration=0;
    function clearPreviews(){previewUrls.forEach(url=>URL.revokeObjectURL(url));previewUrls=[];}
    function renderPreviews(){clearPreviews();document.getElementById('addBackPreviews').innerHTML=selected.map((file,index)=>{const url=URL.createObjectURL(file);previewUrls.push(url);return '<div class="col-12 col-sm-4"><div class="border rounded p-2"><img alt="Selected supporting evidence" src="'+url+'" class="w-100" style="height:130px;object-fit:contain"><div class="small text-break mt-2">'+esc(file.name)+'</div><button type="button" class="btn btn-sm btn-outline-danger mt-2" data-remove-evidence="'+index+'">Remove</button></div></div>';}).join('');}
    const preventDismiss=event=>{if(submitting)event.preventDefault();};modal.addEventListener('hide.bs.modal',preventDismiss);
    modal.addEventListener('hidden.bs.modal',()=>{clearPreviews();modal.removeEventListener('hide.bs.modal',preventDismiss);},{once:true});
    document.getElementById('addBackPreviews').onclick=event=>{const button=event.target.closest('[data-remove-evidence]');if(button&&!submitting){selected.splice(Number(button.dataset.removeEvidence),1);renderPreviews();}};
    document.getElementById('addBackImages').onchange=event=>{const files=Array.from(event.target.files);let error='';if(selected.length+files.length>3)error='You can upload a maximum of 3 images.';else if(files.some(file=>!(/\.(jpg|jpeg|png|webp)$/i.test(file.name))||!['image/jpeg','image/png','image/webp'].includes(file.type)))error='Only JPG, JPEG, PNG, and WEBP images are allowed.';else if(files.some(file=>file.size>5*1024*1024))error='Each image must not exceed 5 MB.';document.getElementById('addBackImageError').textContent=error;if(!error){selected.push(...files);renderPreviews();}event.target.value='';};
    const loadContext=async()=>{const generation=++contextGeneration;loading=true;context=null;try{const date=document.getElementById('addBackDate').value;const {context:c}=await request(api+'?work_date='+encodeURIComponent(date));if(generation!==contextGeneration||!form.isConnected)return;context=c;document.getElementById('addBackContext').innerHTML='<strong>'+esc(c.company+' · '+c.site)+'</strong><p>'+scheduleText(c.schedule)+'</p>'+original(c.attendance,c.original_hours)+(!c.attendance?.clockin_url&&!c.attendance?.clockout_url?alertBox('No recorded clock photo is available for this date. You may upload your own image evidence for Accounting verification.'):'');document.getElementById('addBackStart').value=(c.attendance?.TimeIn||date+'T'+(c.schedule?.time_in||'08:00')).replace(' ','T').slice(0,16);const end=date+'T'+(c.schedule?.time_out||'17:00');const value=new Date(end);if(c.schedule?.time_out<c.schedule?.time_in)value.setDate(value.getDate()+1);document.getElementById('addBackEnd').value=value.getFullYear()+'-'+String(value.getMonth()+1).padStart(2,'0')+'-'+String(value.getDate()).padStart(2,'0')+'T'+end.slice(11,16);}catch(error){if(generation===contextGeneration&&form.isConnected)document.getElementById('addBackContext').innerHTML=alertBox(error.message);}finally{if(generation===contextGeneration)loading=false;}};
    document.getElementById('addBackDate').onchange=loadContext;if(date)loadContext();
    form.onsubmit=async event=>{event.preventDefault();if(submitting)return;const notice=document.getElementById('addBackFormNotice');const date=document.getElementById('addBackDate').value,start=document.getElementById('addBackStart').value,end=document.getElementById('addBackEnd').value,reason=document.getElementById('addBackReason').value.trim();let error='';if(loading||!context)error='Select a valid work date and wait for its duty details.';else if(!reason)error='Please enter a reason for your Add Back request.';else if(reason.length<10)error='Explain the missing duty in at least 10 characters.';else if(!start||!end||new Date(end)<=new Date(start))error='Actual duty end must be after actual duty start.';else if(!selected.length&&!context.attendance?.clockin_url&&!context.attendance?.clockout_url)error='Please upload at least one image as evidence.';if(error){notice.innerHTML=alertBox(error);return;}
      submitting=true;const button=form.querySelector('[type="submit"]');button.disabled=true;button.textContent='Submitting…';modal.querySelectorAll('[data-bs-dismiss]').forEach(el=>el.disabled=true);const data=new FormData();data.append('work_date',date);data.append('duty_start',start);data.append('duty_end',end);data.append('reason',reason);selected.forEach(file=>data.append('evidence[]',file));
      try{const saved=await request(api,data);submitting=false;modal.addEventListener('hidden.bs.modal',()=>submitted(saved.submission),{once:true});dialog.hide();}catch(error){notice.innerHTML=alertBox(error.message);button.disabled=false;button.textContent='Submit for Accounting verification';}finally{submitting=false;modal.querySelectorAll('[data-bs-dismiss]').forEach(el=>el.disabled=false);}
    };
  }
  const successModal=document.createElement('div');successModal.id='addBackSubmittedModal';successModal.className='modal fade';successModal.tabIndex=-1;successModal.innerHTML='<div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Add Back Request Submitted</h5></div><div class="modal-body"></div><div class="modal-footer"><button type="button" class="btn btn-success" data-bs-dismiss="modal">Done</button></div></div></div>';document.body.appendChild(successModal);
  successModal.addEventListener('hidden.bs.modal',refresh);
  function submitted(r){successModal.querySelector('.modal-body').innerHTML='<div class="text-center mb-3"><i class="bi bi-check-circle-fill text-success fs-1" aria-hidden="true"></i></div><p>Your Add Back request has been submitted successfully and is now waiting for Accounting verification.</p><dl><dt>Request reference</dt><dd>#'+esc(r.RequestID)+'</dd><dt>Work date</dt><dd>'+esc(r.WorkDate)+'</dd><dt>Requested duty</dt><dd>'+esc(stamp(r.DutyStart))+' – '+esc(stamp(r.DutyEnd))+'</dd><dt>Uploaded evidence images</dt><dd>'+esc(r.uploaded_count)+'</dd><dt>Status</dt><dd><span class="badge bg-warning text-dark">Pending Accounting Verification</span></dd></dl>';bootstrap.Modal.getOrCreateInstance(successModal).show();}
  window.AddBack={view,refresh};
  document.addEventListener('onazul:notification-open',event=>{const n=event.detail;if(n.related_module==='add_back'&&Number(n.related_record)>0){if(accounting)document.querySelector('[data-nav="addback"]')?.click();else if(window.showEmpTab)window.showEmpTab('section-emp-timesheet');view(Number(n.related_record));}});
  document.addEventListener('click', event => {
    const evidenceButton = event.target.closest('[data-attendance-evidence-date]'); if (evidenceButton) evidence(evidenceButton.dataset.attendanceEvidenceDate);
    const viewButton = event.target.closest('[data-addback-view]'); if (viewButton) view(viewButton.dataset.addbackView);
  });
  document.getElementById('addBackRefresh').onclick = refresh;
  if (employee) document.getElementById('addBackNew').onclick = () => newRequest();
  refresh(); setInterval(() => { if (!document.hidden && !modal.classList.contains('show')) refresh(); }, 30000);
})();
