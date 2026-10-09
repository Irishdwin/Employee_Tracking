(function () {
  'use strict';
  var API='../../Api/Admin/client_companies.php';
  function esc(v){var d=document.createElement('div');d.textContent=v==null?'':String(v);return d.innerHTML;}
  async function request(url,options){var r=await ClientCompaniesUI.request(url,options);var d=await r.json();if(!r.ok||!d.success)throw new Error(d.message||'Unable to load deployment sites.');return d;}
  window.manageDeploymentSites=async function(company){
    try{
      var data=await request(API+'?list=sites&company_id='+company);
      var html='<div class="text-start">'+
        data.sites.map(function(s){return '<div class="border rounded p-3 mb-2"><strong>'+esc(s.Name)+'</strong><div>'+esc(s.Address)+'</div><div>Team Lead: '+esc(s.TeamLeadName||'Unassigned')+' · Employees: '+s.EmployeeCount+'</div><div>'+ (Number(s.IsActive)?'Active':'Inactive')+'</div><button type="button" class="btn btn-sm btn-outline-primary mt-2" data-site-edit="'+s.SiteID+'">Configure / reassign</button></div>';}).join('')+'</div>';
      Swal.fire({title:'Deployment Sites',html:html,width:720,showConfirmButton:false,showCloseButton:true,didOpen:function(){
        Swal.getHtmlContainer().querySelectorAll('[data-site-edit]').forEach(function(b){b.onclick=function(){editSite(company,data.sites.find(function(s){return String(s.SiteID)===b.dataset.siteEdit;}));};});
      }});
    }catch(e){Swal.fire('Deployment Sites',e.message,'error');}
  };
  async function editSite(company,site,draft){
    try{
      var data=await request(API+'?list=team_leads');
      // Reuse the actual Add Client account fields, checklist and visibility controls.
      var source=document.getElementById('addTlFullName').closest('.border').cloneNode(true);
      source.querySelectorAll('.password-strength-meter').forEach(function(el){el.remove();});
      source.querySelectorAll('[id]').forEach(function(el){el.id=el.id.replace(/^addTl/,'siteTl');});
      source.querySelectorAll('[data-target]').forEach(function(el){el.dataset.target=el.dataset.target.replace(/^addTl/,'siteTl');delete el.dataset.accountToggleBound;});
      source.querySelectorAll('input').forEach(function(el){el.value='';el.disabled=false;el.classList.remove('is-valid','is-invalid');delete el.dataset.strengthMeterAttached;if(el.name.indexOf('password')>=0)el.type='password';});
      source.querySelectorAll('li').forEach(function(el){el.className='text-muted';});
      source.querySelectorAll('[data-toggle-password]').forEach(function(el){el.setAttribute('aria-label','Show password');el.querySelector('i').className='bi bi-eye';});
      source.querySelector('#siteTlEmailCheckStatus').textContent='Must be a valid Gmail address unique across the system.';
      source.querySelector('#siteTlEmailCheckStatus').className='text-muted d-block mt-1';
      source.querySelector('#siteTlPasswordConfirmHint').textContent='Re-enter the same password.';
      source.querySelector('#siteTlPasswordConfirmHint').className='form-text';
      source.querySelector('p').textContent='Create a Team Lead account and assign it to this deployment site.';
      var contact=document.createElement('div');contact.className='mb-3';
      contact.innerHTML='<label class="form-label" for="siteTlContactNumber">Contact number <span class="text-danger">*</span></label><input type="tel" id="siteTlContactNumber" name="tl_contact_number" class="form-control" placeholder="e.g., 09171234567 or +639171234567" autocomplete="tel" required>';
      source.querySelector('#siteTlFullName').parentElement.after(contact);
      var result=await Swal.fire({title:site?'Configure deployment site':'Add deployment site',width:640,
        customClass:{popup:'client-site-editor'},
        html:'<form id="siteEditor" class="text-start"><label class="form-label">Site / branch name</label><input name="site_name" class="form-control mb-3" maxlength="150" required value="'+esc(site&&site.Name)+'">'+
          '<label class="form-label">Site address</label><textarea name="site_address" class="form-control mb-3">'+esc(site&&site.Address)+'</textarea>'+ 
          '<label class="form-label">Team Lead</label><select name="team_lead_id" class="form-select mb-3"><option value="">Unassigned / remove assignment</option>'+data.team_leads.map(function(t){return '<option value="'+t.UsersID+'"'+(site&&Number(site.TeamLeadID)===Number(t.UsersID)?' selected':'')+'>'+esc(t.FullName)+' — '+esc(t.Email)+'</option>';}).join('')+'</select>'+ 
          '<label class="form-label">Account option</label><select name="lead_mode" class="form-select mb-3"><option value="existing">Use existing Team Lead</option><option value="new">Create new Team Lead</option></select>'+ 
          '<fieldset id="siteNewLead" class="site-account-fields" hidden disabled>'+source.outerHTML+'</fieldset>'+
          '<label class="form-label">Site status</label><select name="is_active" class="form-select"><option value="1">Active</option><option value="0"'+(site&&!Number(site.IsActive)?' selected':'')+'>Inactive</option></select></form>',
        showCancelButton:true,confirmButtonText:'Save site',showLoaderOnConfirm:true,allowOutsideClick:function(){return !Swal.isLoading();},
        didOpen:function(){
          var f=document.getElementById('siteEditor');
          if(draft)Object.keys(draft).forEach(function(key){if(f.elements[key])f.elements[key].value=draft[key];});
          f._validateAccount=ClientCompaniesUI.attachAccount(f,'siteTl');
          f._validateContact=PhMobile.attach(document.getElementById('siteTlContactNumber'),{profile:true});
          attachPasswordStrengthMeterById('siteTlPassword');
          f.lead_mode.onchange=function(){var fieldset=document.getElementById('siteNewLead'),isNew=f.lead_mode.value==='new';fieldset.hidden=!isNew;fieldset.disabled=!isNew;f.team_lead_id.disabled=isNew;};f.lead_mode.onchange();
        },
        preConfirm:function(){var f=document.getElementById('siteEditor');if(f.lead_mode.value==='new'){f._validateAccount();f._validateContact();}if(!f.reportValidity())return false;var fd=new FormData(f);fd.set('action','save_site');fd.set('company_id',company);fd.set('site_id',site?site.SiteID:0);return {fd:fd,draft:Object.fromEntries(fd.entries())};}
      });
      if(!result.isConfirmed)return;
      var values=result.value.draft,lead=data.team_leads.find(function(t){return String(t.UsersID)===values.team_lead_id;});
      var selectedName=values.lead_mode==='new'?values.tl_full_name:(lead?lead.FullName:'Unassigned');
      var confirmation=await Swal.fire({icon:'question',title:'Confirm Changes',text:'Save site “'+values.site_name+'” with Team Lead '+selectedName+'?',showCancelButton:true,confirmButtonText:'Confirm Changes',cancelButtonText:'Cancel'});
      if(!confirmation.isConfirmed)return editSite(company,site,values);
      try {
        Swal.fire({title:'Saving site…',allowOutsideClick:false,allowEscapeKey:false,didOpen:function(){Swal.showLoading();}});
        await request(API,{method:'POST',body:result.value.fd});
        if(typeof loadCompanies==='function')loadCompanies();
        await Swal.fire({icon:'success',title:'Success',text:'Deployment site changes were saved successfully.',confirmButtonText:'OK'});
        manageDeploymentSites(company);
      } catch(error) {
        await Swal.fire({icon:'error',title:'Unable to save changes',text:error.message,confirmButtonText:'OK'});
        editSite(company,site,values);
      }
    }catch(e){Swal.fire('Deployment Sites',e.message,'error');}
  }
  // Reuse the existing company account form; an existing identity needs no new password.
  var form=document.getElementById('formAddClient');
  if(form){
    var name=form.querySelector('[name="tl_full_name"]');
    if(name){var box=document.createElement('div');box.className='mb-3';box.innerHTML='<label class="form-label">Company Team Lead account</label><select name="team_lead_id" class="form-select"><option value="">Create new Team Lead below</option></select><div class="form-text">Configure deployment sites after registering this company.</div>';name.closest('.border').before(box);
      var select=box.querySelector('select');
      function mode(){['tl_full_name','tl_email','tl_password','tl_password_confirm'].forEach(function(n){form[n].disabled=Boolean(select.value);form[n].required=!select.value;});}
      select.onchange=function(){
        mode();
        var contact=form.querySelector('[name="contact_number"]');
        var option=select.options[select.selectedIndex];
        if(contact){
          contact.value=(select.value && option && option.dataset.contactNumber) || '+63';
          contact.dispatchEvent(new Event('input',{bubbles:true}));
        }
      };
      form.addEventListener('reset',function(){setTimeout(mode,0);});
      request(API+'?list=team_leads').then(function(d){d.team_leads.forEach(function(t){var option=new Option(t.FullName+' — '+t.Email,t.UsersID);option.dataset.contactNumber=t.ContactNumber||'';select.add(option);});}).catch(function(e){box.insertAdjacentHTML('beforeend','<p class="text-danger">'+esc(e.message)+'</p>');});
    }
  }
  [['industrySelect','btnEditSelectedIndustry'],['editIndustrySelect','btnEditSelectedIndustryEdit']].forEach(function(ids){
    var select=document.getElementById(ids[0]),button=document.getElementById(ids[1]);if(!select||!button)return;
    function update(){button.disabled=!(select.selectedOptions[0]&&select.selectedOptions[0].dataset.industryId);}
    select.addEventListener('change',update);button.addEventListener('click',function(){openIndustryEditor(ids[0]);});update();
  });
})();
