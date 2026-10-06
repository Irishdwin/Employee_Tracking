(function () {
  'use strict';
  // Shared by both existing Team Lead review screens; cancellation never submits a decision.
  window.ClientReviewReason = function (decision) {
    return new Promise(resolve => {
      const rejecting=decision==='Rejected';
      const element=document.createElement('div'); element.className='modal fade'; element.tabIndex=-1;
      element.innerHTML='<div class="modal-dialog"><div class="modal-content"><form><div class="modal-header"><h5 class="modal-title">'+(rejecting?'Reject Client Endorsement':'Approve Client Endorsement')+'</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><label class="form-label">'+(rejecting?'Professional rejection reason (required)':'Review remarks (optional)')+'</label><textarea class="form-control" rows="4" maxlength="2000" '+(rejecting?'required':'')+'></textarea><p class="small text-muted mt-2">Internal remarks are available to HR/Admin. Rejection returns the application for possible reassignment.</p></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn '+(rejecting?'btn-danger':'btn-success')+'">'+(rejecting?'Confirm Rejection':'Confirm Approval')+'</button></div></form></div></div>';
      document.body.appendChild(element); const modal=bootstrap.Modal.getOrCreateInstance(element); let result=null;
      element.querySelector('form').addEventListener('submit',event=>{
        event.preventDefault(); const input=element.querySelector('textarea');
        input.setCustomValidity(rejecting&&!input.value.trim()?'Enter a professional rejection reason.':'');
        if (!input.reportValidity()) return; result=input.value.trim(); modal.hide();
      });
      element.querySelector('textarea').addEventListener('input',event=>event.target.setCustomValidity(''));
      element.addEventListener('shown.bs.modal',()=>element.querySelector('textarea').focus(),{once:true});
      element.addEventListener('hidden.bs.modal',()=>{modal.dispose();element.remove();resolve(result);},{once:true});
      modal.show();
    });
  };
}());
