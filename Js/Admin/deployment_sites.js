(function () {
  'use strict';
  var API='../../Api/Admin/client_companies.php';
  function esc(v){var d=document.createElement('div');d.textContent=v==null?'':String(v);return d.innerHTML;}
  async function request(url,options){var r=await fetch(url,Object.assign({credentials:'include'},options));var d=await r.json();if(!r.ok||!d.success)throw new Error(d.message||'Unable to load deployment sites.');return d;}
  window.manageDeploymentSites=async function(company){
    try{
      var data=await request(API+'?list=sites&company_id='+company);
      var html='<div class="text-start"><button type="button" class="btn btn-primary mb-3" id="addSiteButton">Add deployment site</button>'+
        data.sites.map(function(s){return '<div class="border rounded p-3 mb-2"><strong>'+esc(s.Name)+'</strong><div>'+esc(s.Address)+'</div><div>Team Lead: '+esc(s.TeamLeadName||'Unassigned')+' · Employees: '+s.EmployeeCount+'</div><div>'+ (Number(s.IsActive)?'Active':'Inactive')+'</div><button type="button" class="btn btn-sm btn-outline-primary mt-2" data-site-edit="'+s.SiteID+'">Configure / reassign</button></div>';}).join('')+'</div>';
      Swal.fire({title:'Deployment Sites',html:html,width:720,showConfirmButton:false,showCloseButton:true,didOpen:function(){
        document.getElementById('addSiteButton').onclick=function(){editSite(company,null);};
        Swal.getHtmlContainer().querySelectorAll('[data-site-edit]').forEach(function(b){b.onclick=function(){editSite(company,data.sites.find(function(s){return String(s.SiteID)===b.dataset.siteEdit;}));};});
      }});
    }catch(e){Swal.fire('Deployment Sites',e.message,'error');}
  };
  async function editSite(company,site){
    try{
      var data=await request(API+'?list=team_leads');
      var result=await Swal.fire({title:site?'Configure deployment site':'Add deployment site',width:640,
        html:'<form id="siteEditor" class="text-start"><label class="form-label">Site / branch name</label><input name="site_name" class="form-control mb-3" maxlength="150" required value="'+esc(site&&site.Name)+'">'+
          '<label class="form-label">Site address</label><textarea name="site_address" class="form-control mb-3">'+esc(site&&site.Address)+'</textarea>'+ 
          '<label class="form-label">Team Lead</label><select name="team_lead_id" class="form-select mb-3"><option value="">Unassigned / remove assignment</option>'+data.team_leads.map(function(t){return '<option value="'+t.UsersID+'"'+(site&&Number(site.TeamLeadID)===Number(t.UsersID)?' selected':'')+'>'+esc(t.FullName)+' — '+esc(t.Email)+'</option>';}).join('')+'</select>'+ 
          '<label class="form-label">Account option</label><select name="lead_mode" class="form-select mb-3"><option value="existing">Use existing Team Lead</option><option value="new">Create new Team Lead</option></select>'+ 
          '<fieldset id="siteNewLead" hidden><label>Full name</label><input class="form-control mb-2" name="tl_full_name"><label>Gmail</label><input type="email" class="form-control mb-2" name="tl_email"><label>Password</label><input type="password" class="form-control mb-2" name="tl_password" autocomplete="new-password"><label>Confirm password</label><input type="password" class="form-control mb-2" name="tl_password_confirm" autocomplete="new-password"></fieldset>'+ 
          '<label class="form-label">Site status</label><select name="is_active" class="form-select"><option value="1">Active</option><option value="0"'+(site&&!Number(site.IsActive)?' selected':'')+'>Inactive</option></select></form>',
        showCancelButton:true,confirmButtonText:'Save site',showLoaderOnConfirm:true,allowOutsideClick:function(){return !Swal.isLoading();},
        didOpen:function(){var f=document.getElementById('siteEditor');f.lead_mode.onchange=function(){document.getElementById('siteNewLead').hidden=f.lead_mode.value!=='new';f.team_lead_id.disabled=f.lead_mode.value==='new';};},
        preConfirm:async function(){var f=document.getElementById('siteEditor');if(!f.reportValidity())return false;var fd=new FormData(f);fd.set('action','save_site');fd.set('company_id',company);fd.set('site_id',site?site.SiteID:0);try{return await request(API,{method:'POST',body:fd});}catch(e){Swal.showValidationMessage(e.message);return false;}}
      });
      if(result.isConfirmed)manageDeploymentSites(company);
    }catch(e){Swal.fire('Deployment Sites',e.message,'error');}
  }
  // Reuse the existing company account form; an existing identity needs no new password.
  var form=document.getElementById('formAddClient');
  if(form){
    var name=form.querySelector('[name="tl_full_name"]');
    if(name){var box=document.createElement('div');box.className='mb-3';box.innerHTML='<label class="form-label">Initial deployment site name</label><input name="site_name" class="form-control mb-2" maxlength="150" placeholder="Defaults to company name"><label class="form-label">Assign Team Lead</label><select name="team_lead_id" class="form-select"><option value="">Create new Team Lead below</option></select>';name.parentElement.before(box);
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
})();
