(function () {
  'use strict';
  const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  function render(res) {
    const sub=res.submission, reviews=res.holiday_reviews || [];
    return '<section class="mt-4" id="employeeDtrReview"><h3 class="text-sm font-bold mb-2">Employee verification</h3>' +
      '<p class="text-sm mb-2">'+(sub.reviewed ? 'Verified by '+esc(sub.reviewed_by || 'Accounting')+' · '+esc(sub.reviewed_at) : 'Not Verified')+'</p>'+
      (sub.verification_blocked?'<div class="alert alert-warning">'+esc(sub.verification_blocked)+'</div>':'')+
      reviews.map((r,i)=>'<details class="border rounded-lg p-3 mb-3" '+(!sub.reviewed?'open':'')+'><summary class="font-semibold">Holiday eligibility: '+esc(r.holiday_date)+'</summary><p class="mt-2 text-sm">Preceding scheduled workday: <strong>'+esc(r.preceding_workday || 'Not established')+'</strong></p><p class="text-sm">Attendance: '+(r.attendance?esc(r.attendance.status || 'Recorded')+' · '+esc(r.attendance.time_in || 'No time in')+' – '+esc(r.attendance.time_out || 'No time out'):'No recorded attendance')+'</p><p class="text-sm">Approved leave: '+(r.approved_leaves.length?r.approved_leaves.map(l=>esc(l.LeaveType || 'Leave')+' (#'+Number(l.LeaveID)+', approved by #'+Number(l.ApprovedBy)+')').join('; '):'None')+'. Approval alone does not establish paid leave.</p><p class="text-sm">Approved attendance corrections: '+(r.approved_corrections.length?r.approved_corrections.map(c=>'#'+Number(c.RequestID)+' · '+esc(c.DutyStart)+' – '+esc(c.DutyEnd)).join('; '):'None')+'</p>'+
        (r.decision?'<p class="text-sm">Recorded outcome: <strong>'+esc(r.decision.outcome)+'</strong> · Reviewer #'+Number(r.decision.reviewer_id)+' · '+esc(r.decision.reviewed_at)+'<br>'+esc(r.decision.reason)+'</p>':'<p class="text-amber-700 text-sm">Review unresolved. Inspect the available records and documentary evidence before deciding.</p>')+
        (!sub.reviewed&&!sub.verification_blocked&&(!r.decision||r.decision.outcome==='unresolved')?'<form data-holiday-review="'+i+'" class="mt-3"><label class="block text-sm">Review outcome<select required name="outcome" class="form-select mb-2"><option value="">Select outcome</option><option value="eligible">Eligible for unworked holiday pay</option><option value="ineligible">Not eligible</option><option value="unresolved">Insufficient information — unresolved</option></select></label><label class="block text-sm">Documented basis<select name="basis" class="form-select mb-2"><option value="attendance">Completed preceding attendance</option><option value="paid_leave">Approved leave confirmed paid</option><option value="approved_correction">Approved attendance correction</option><option value="documented_unpaid_absence">Documented unpaid absence; leave and corrections checked</option><option value="insufficient_information">Insufficient information</option></select></label><label class="block text-sm">Documentary reference and review explanation<textarea required minlength="20" maxlength="2000" name="reason" class="form-control mb-2"></textarea></label><button class="btn btn-outline-primary" type="submit">Record Holiday Review</button></form>':'')+'</details>').join('')+
      (!sub.reviewed?'<button type="button" class="btn btn-primary" id="btnVerifyEmployeeDtr" '+(sub.verification_blocked?'disabled':'')+'>Verify DTR</button>':'<span class="badge bg-primary">Verified</span>')+'</section>';
  }
  function mount(res, refresh) {
    const sub=res.submission, root=document.getElementById('employeeDtrReview');
    const identity={tl_id:sub.site_supervisor_id,site_id:sub.site_id,employee_id:sub.employee_id,submission_id:sub.dtr_submission_id,cutoff_start:sub.cutoff_start,cutoff_end:sub.cutoff_end};
    let busy=false;
    async function send(endpoint, extra, title, message, label) {
      if(busy)return;busy=true;
      const buttons=root.querySelectorAll('button');buttons.forEach(b=>b.disabled=true);
      try {
        if(!await AppDialog.confirm(message,title,label))return;
        const result=await fetch('../../Api/Accounting/'+endpoint,{method:'POST',credentials:'include',headers:{'Content-Type':'application/json','X-Payroll-CSRF':res.payroll_csrf},body:JSON.stringify({...identity,...extra})}).then(r=>r.json());
        if(!result.success)throw Error(result.message || 'Review failed.');
        refresh();if(window.OwNotifications)OwNotifications.poll();
        await AppDialog.alert(result.message,endpoint==='verify_dtr_submission.php'?'DTR Verified Successfully':'Holiday Review Recorded');
      }catch(error){await AppDialog.alert(error.message,'Verification / Review Failed');}
      finally{busy=false;buttons.forEach(b=>b.disabled=false);}
    }
    const verify=root.querySelector('#btnVerifyEmployeeDtr');
    if(verify&&!sub.verification_blocked)verify.onclick=()=>send('verify_dtr_submission.php',{},'Verify Employee DTR','Verify '+sub.employee_name+'?\nDeployment site: '+(sub.site_name || sub.company_name)+'\nPayroll cutoff: '+sub.cutoff_start+' – '+sub.cutoff_end+'\nCurrent verification status: Not Verified','Confirm Verification');
    root.querySelectorAll('[data-holiday-review]').forEach(form=>form.onsubmit=e=>{
      e.preventDefault();const review=res.holiday_reviews[Number(form.dataset.holidayReview)],values=new FormData(form);
      send('review_dtr_holiday.php',{holiday_date:review.holiday_date,fingerprint:review.fingerprint,outcome:values.get('outcome'),basis:values.get('basis'),reason:values.get('reason')},'Record Holiday Eligibility Review','Record the documented '+values.get('outcome')+' outcome for '+sub.employee_name+' on '+review.holiday_date+'? This does not verify the DTR or finalize payroll.','Record Review');
    });
  }
  window.EmployeeDtrReview={render,mount};
})();
