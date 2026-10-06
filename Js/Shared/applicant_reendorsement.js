(function () {
  'use strict';
  const api = '../../Api/HR/';
  const style=document.createElement('style');style.textContent='#reendorsementModal .modal-dialog{max-width:860px;margin-left:auto;margin-right:auto}#reendorsementModal .modal-body{padding:24px;overflow-wrap:anywhere}#reendorsementModal .list-group-item{background:#f5f8fc;border-color:#dde6f0;padding:14px 16px}#reendorsementModal fieldset{min-width:0}.reendorsement-options{max-height:250px;overflow:auto;display:grid;gap:10px;padding:3px}.reendorsement-option{display:flex;align-items:flex-start;gap:12px;padding:14px;border:1px solid #cbd5e1;border-radius:10px;cursor:pointer;background:#f8fafc}.reendorsement-option:has(input:checked){border-color:#0056b3;background:#eaf3ff;box-shadow:0 0 0 1px #0056b3}.reendorsement-option input{margin-top:5px;flex:none}.reendorsement-option span{min-width:0}.reendorsement-option strong,.reendorsement-location,.reendorsement-option small{display:block;overflow-wrap:anywhere}.reendorsement-location{color:#526580;margin:5px 0;line-height:1.5}.reendorsement-option small{color:#526580}@media(max-width:600px){#reendorsementModal .modal-dialog{margin:12px}#reendorsementModal .modal-body{padding:16px}}';document.head.appendChild(style);
  const esc = value => String(value == null ? '' : value).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  async function request(url, options) {
    const response = await fetch(url, Object.assign({credentials:'same-origin'}, options));
    const data = await response.json();
    if (!response.ok || !data.success) throw new Error(data.message || data.error || 'Could not load application.');
    return data;
  }
  function historyHtml(attempts) {
    if (!attempts.length) return '<p class="text-muted">No client endorsements recorded.</p>';
    return '<div class="list-group">' + attempts.map(a => '<div class="list-group-item">' +
      '<div class="d-flex justify-content-between gap-2 flex-wrap"><strong>Attempt ' + Number(a.AttemptNumber) + ' · ' + esc(a.CompanyName) + '</strong><span>' + esc(a.Decision === 'Rejected' ? 'Rejected by Client' : a.Decision === 'Pending' ? 'Pending Client Review' : 'Approved by Company') + '</span></div>' +
      '<div>' + esc(a.PositionTitle) + ' · ' + esc(a.DeploymentSite || 'Location unspecified') + '</div>' +
      '<div class="small text-muted">Endorsed: ' + esc(a.EndorsedAt) + ' · ' + esc(a.EndorserName || 'Sender not recorded') + '</div>' +
      (a.Decision !== 'Pending' ? '<div class="small">Decision: ' + esc(a.DecidedAt || 'Date not recorded') + ' · ' + esc(a.ReviewerName || 'Reviewer not recorded') + '</div>' : '') +
      (a.DecisionReason ? '<div class="small mt-1"><strong>Internal reason:</strong> ' + esc(a.DecisionReason) + '</div>' : '') +
      (a.ReendorsementReason ? '<div class="small"><strong>Reassignment reason:</strong> ' + esc(a.ReendorsementReason) + '</div>' : '') +
      (Number(a.LegacySnapshot) ? '<div class="small text-muted">Imported current endorsement; earlier attempts and reviewer identity were not recorded by the previous workflow.</div>' : '') + '</div>').join('') + '</div>';
  }
  function attach(container, data, refresh) {
    const section = document.createElement('section');
    section.className = 'mb-3';
    section.innerHTML = '<h6>Client Endorsement History</h6>' + historyHtml(data.endorsement_history || []) +
      (data.can_reendorse ? '<p class="small text-muted mt-2">Client rejection returns this application to HR/Admin for possible reassignment.</p><button type="button" class="btn btn-primary btn-sm">Find Another Company</button>' : '');
    container.appendChild(section);
    const button = section.querySelector('button');
    if (button) button.addEventListener('click', () => open(data.application_id, refresh));
  }
  async function open(id, refresh) {
    const review = document.getElementById('reviewModal');
    if (review && review.classList.contains('show')) {
      await new Promise(resolve => { review.addEventListener('hidden.bs.modal', resolve, {once:true});bootstrap.Modal.getOrCreateInstance(review).hide(); });
    }
    let element = document.getElementById('reendorsementModal');
    if (!element) {
      element = document.createElement('div'); element.id = 'reendorsementModal'; element.className = 'modal fade'; element.tabIndex = -1;element.setAttribute('aria-labelledby','reendorsementTitle');
      element.innerHTML = '<div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="reendorsementTitle">Find Another Company / Re-Endorse</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"></div></div></div>';
      document.body.appendChild(element);
    }
    const body = element.querySelector('.modal-body');
    body.innerHTML = '<p role="status">Loading application and eligible opportunities…</p>';
    bootstrap.Modal.getOrCreateInstance(element).show();
    try {
      const [data, alternatives] = await Promise.all([request(api+'get_application_details.php?id='+encodeURIComponent(id)), request(api+'endorse_to_client.php?application_id='+encodeURIComponent(id))]);
      const options = alternatives.opportunities || [];
      body.innerHTML = '<h6>' + esc(data.applicant_name) + '</h6><p class="text-muted small">Choose an open placement compatible with the applicant’s hierarchy preferences. Previously rejected opportunities are excluded. Availability is checked again when you confirm.</p>' +
        historyHtml(data.endorsement_history || []) + (options.length ?  '<form class="mt-3"><fieldset><legend class="fs-6 fw-semibold">Available company / Job Order / placement</legend><div class="reendorsement-options">' +
        options.map((o,i) => '<label class="reendorsement-option"><input type="radio" name="reendorsementChoice" value="'+i+'" required><span><strong>'+esc(o.company_name)+' · '+esc(o.position)+'</strong><span class="reendorsement-location">'+esc(o.location||'Location unspecified')+'</span><small>Job Order #'+Number(o.job_order_id)+' · '+Number(o.open_slots)+' open slots</small></span></label>').join('') + '</div></fieldset><div id="reendorsementDetails" class="small text-muted mt-2"></div><label for="reendorsementReason" class="form-label mt-3">Internal reassignment reason</label><textarea id="reendorsementReason" class="form-control" required maxlength="2000" rows="3"></textarea><div class="alert alert-danger mt-2 d-none" role="alert"></div><button class="btn btn-primary mt-3" type="submit">Confirm Re-Endorsement</button></form>' : '<div class="alert alert-info mt-3" role="status">No eligible alternatives currently have available slots. The application remains available for HR/Admin review.</div>');
      const form = body.querySelector('form');
      if (!form) return;
      const selectedOption = () => form.querySelector('[name=reendorsementChoice]:checked');
      form.addEventListener('change', () => {
        const o=options[selectedOption()?.value]; const h=o?.hierarchy || {};
        form.querySelector('#reendorsementDetails').textContent = o ? [h.category_name,h.classification_name,h.sub_classification_name,o.work_schedule].filter(Boolean).join(' · ') : '';
      });
      form.addEventListener('submit', async event => {
        event.preventDefault(); if (!form.reportValidity()) return;
        const o=options[selectedOption()?.value], button=form.querySelector('button'), error=form.querySelector('[role="alert"]');
        const reason=form.querySelector('textarea').value.trim();
        if (!o || !reason) { error.textContent='Select an opportunity and enter a reassignment reason.'; error.classList.remove('d-none'); return; }
        button.disabled=true; button.textContent='Saving endorsement…'; error.classList.add('d-none');
        try {
          await request(api+'endorse_to_client.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({application_id:id,attempt_id:data.attempt_id,job_order_id:o.job_order_id,target_detail_id:o.target_detail_id,reason})});
          let success=document.getElementById('reendorsementSuccess');
          if(!success){success=document.createElement('dialog');success.id='reendorsementSuccess';success.setAttribute('aria-labelledby','reendorsementSuccessTitle');success.style.cssText='border:0;border-radius:16px;padding:30px;width:min(450px,calc(100vw - 32px));margin:auto;text-align:center;color:#172e4d';success.innerHTML='<div aria-hidden="true" style="color:#15803d;font-size:42px">✓</div><h2 id="reendorsementSuccessTitle" style="font-size:22px;font-weight:700">Applicant Re-Endorsed</h2><p class="mt-3"></p><button type="button" class="btn btn-primary mt-2">OK</button>';success.querySelector('button').onclick=()=>success.close();document.body.appendChild(success);const st=document.createElement('style');st.textContent='#reendorsementSuccess::backdrop{background:#0f172a99}';document.head.appendChild(st);}
          success.querySelector('p').textContent=(data.applicant_name||'The applicant')+' has been successfully re-endorsed to '+o.company_name+'.';
          element.addEventListener('hidden.bs.modal',()=>{success.showModal();success.querySelector('button').focus();},{once:true});
          bootstrap.Modal.getInstance(element).hide();
          if (typeof refresh==='function') refresh();
          if (window.AdminSidebarBadges?.poll) window.AdminSidebarBadges.poll('../../Api/Common/realtime_counters.php');
          window.dispatchEvent(new CustomEvent('ow:application-updated'));
        } catch (e) { error.textContent=e.message; error.classList.remove('d-none'); }
        finally { button.disabled=false; button.textContent='Confirm Re-Endorsement'; }
      });
    } catch (e) {
      body.innerHTML='<div class="alert alert-danger" role="alert">'+esc(e.message)+'</div><button class="btn btn-outline-primary" type="button">Retry</button>';
      body.querySelector('button').addEventListener('click',()=>open(id,refresh));
    }
  }
  window.ApplicantReendorsement={open,attach,historyHtml};
}());
