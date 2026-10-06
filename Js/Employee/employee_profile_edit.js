(function () {
  'use strict';
  var anchor=document.querySelector('#section-profile #userProfileCard') || document.getElementById('p-name')?.closest('section');
  if(!anchor)return;
  var openButton=document.createElement('button');openButton.type='button';openButton.textContent='Edit Profile';
  openButton.style.cssText='background:#0a2a5e;color:white;padding:9px 16px;border-radius:8px;margin-bottom:16px;font-weight:600;';
  anchor.insertBefore(openButton,anchor.firstChild);
  var successDialog=document.createElement('dialog');successDialog.id='employeeProfileSuccess';
  successDialog.setAttribute('aria-labelledby','employeeProfileSuccessTitle');
  successDialog.innerHTML='<div style="font-size:42px;color:#15803d;margin-bottom:12px" aria-hidden="true">✓</div><h2 id="employeeProfileSuccessTitle" style="font-size:22px;font-weight:700;margin-bottom:10px">Updated successfully</h2><p style="color:#64748b;margin-bottom:24px">Your profile has been updated successfully.</p><button type="button" style="background:#0a2a5e;color:white;border:0;border-radius:8px;padding:10px 28px;font-weight:600">OK</button>';
  successDialog.style.cssText='border:0;border-radius:16px;padding:30px;width:min(420px,calc(100vw - 32px));text-align:center;color:#172e4d;box-shadow:0 16px 50px #0003;margin:auto';
  document.body.appendChild(successDialog);
  var successStyle=document.createElement('style');successStyle.textContent='#employeeProfileSuccess::backdrop{background:rgba(15,23,42,.6)}';document.head.appendChild(successStyle);
  successDialog.querySelector('button').addEventListener('click',function(){successDialog.close();});
  successDialog.addEventListener('close',function(){openButton.focus();});
  var modal=document.createElement('div');modal.id='employeeProfileEdit';modal.hidden=true;
  modal.innerHTML='<div class="employee-edit-card" role="dialog" aria-modal="true" aria-labelledby="employeeEditTitle"><div class="employee-edit-heading"><h2 id="employeeEditTitle">Edit Personal Profile</h2><button type="button" data-edit-close aria-label="Close">×</button></div><p class="employee-edit-note">Update your contact details and home address. Employment and login details are managed by Admin/HR.</p><div id="employeeEditStatus" role="status" hidden></div><form id="employeeEditForm"><div class="employee-edit-grid"><label>Mobile number<input name="mobile_number" type="tel" placeholder="09XXXXXXXXX or +639XXXXXXXXX" required></label><label>Birth date<input name="birth_date" type="date"></label><label>Gender<select name="gender"><option value="">Not specified</option><option>Male</option><option>Female</option><option>Other</option><option>Prefer not to say</option></select></label></div><div class="employee-edit-address"><p id="employeeSavedAddress"></p><label><input type="checkbox" id="employeeUpdateAddress"> Update my address</label></div><div id="employeeAddressFields" hidden><div class="employee-edit-grid" id="employeeAddressSelects"></div><div class="employee-edit-grid"><label>Street / house number<input id="streetAddressInput" maxlength="255"></label><label>ZIP code<input id="postalCodeInput" inputmode="numeric" maxlength="4"></label></div></div><div class="employee-edit-actions"><button type="submit" id="employeeSaveProfile">Save Changes</button><button type="button" data-edit-close>Cancel</button></div></form></div>';
  document.body.appendChild(modal);
  var style=document.createElement('style');style.textContent='#employeeProfileEdit[hidden],#employeeProfileEdit [hidden]{display:none!important}#employeeProfileEdit{position:fixed;inset:0;z-index:1200;display:flex;align-items:center;justify-content:center;padding:16px;background:rgba(15,23,42,.6)}.employee-edit-card{background:white;border-radius:16px;width:min(820px,100%);max-height:94vh;overflow:auto;padding:24px;color:#172e4d;box-shadow:0 16px 50px #0003}.employee-edit-heading{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}.employee-edit-heading h2{font-size:22px;font-weight:700}.employee-edit-heading button{font-size:26px;padding:0 10px}.employee-edit-note{font-size:14px;color:#64748b;margin-bottom:20px}.employee-edit-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px;margin:16px 0}.employee-edit-card label{font-size:14px;font-weight:600}.employee-edit-grid input,.employee-edit-grid select{display:block;width:100%;margin-top:7px;border:1px solid #cbd5e1;border-radius:8px;padding:10px;background:white;font-size:14px}.employee-edit-address{padding:16px;background:#f1f6fc;border-radius:10px;margin:20px 0}.employee-edit-address p{font-size:14px;margin-bottom:12px}.employee-edit-actions{display:flex;gap:12px;justify-content:flex-end;border-top:1px solid #e2e8f0;padding-top:18px;margin-top:24px}.employee-edit-actions button{padding:10px 18px;border:1px solid #cbd5e1;border-radius:8px}.employee-edit-actions button[type=submit]{background:#0a2a5e;color:white;border-color:#0a2a5e}#employeeEditStatus{padding:12px;background:#fff0f0;color:#a31919;border-radius:8px;margin:12px 0}.employee-edit-card input:focus,.employee-edit-card select:focus{outline:2px solid #60a5fa} @media(max-width:600px){.employee-edit-grid{grid-template-columns:1fr}.employee-edit-card{padding:18px}}';document.head.appendChild(style);
  ['Region','Province','City','Barangay'].forEach(function(level){
    var wrapper=document.createElement('div');var text=level==='City'?'City / Municipality':level==='Province'?'Province / geographic path':level;
    wrapper.innerHTML='<div style="font-size:14px;font-weight:600;margin-bottom:7px">'+text+'</div><div class="searchable-select" id="address'+level+'Wrap"><button type="button" class="searchable-select__trigger" aria-label="'+text+'"><span class="searchable-select__trigger-label is-placeholder">Select '+text+'</span><span>⌄</span></button><div class="searchable-select__panel"><input type="text" class="searchable-select__search" aria-label="Search '+text+'"><ul class="searchable-select__list" role="listbox"></ul></div></div>';
    document.getElementById('employeeAddressSelects').appendChild(wrapper);
  });
  var form=document.getElementById('employeeEditForm'),status=document.getElementById('employeeEditStatus');
  var toggle=document.getElementById('employeeUpdateAddress'),fields=document.getElementById('employeeAddressFields');
  var csrf='',busy=false,returnFocus=null;
  function close(){if(busy)return;modal.hidden=true;returnFocus?.focus();}
  modal.querySelectorAll('[data-edit-close]').forEach(function(button){button.addEventListener('click',close);});
  modal.addEventListener('click',function(event){if(event.target===modal)close();});
  document.addEventListener('keydown',function(event){if(modal.hidden)return;if(event.key==='Escape'){event.stopImmediatePropagation();close();}if(event.key==='Tab'){var focusable=Array.from(modal.querySelectorAll('button,input,select')).filter(e=>!e.disabled&&e.getClientRects().length);var first=focusable[0],last=focusable[focusable.length-1];if(event.shiftKey&&document.activeElement===first){event.preventDefault();last.focus();}else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first.focus();}}},true);
  function showError(text){status.textContent=text;status.hidden=false;}
  toggle.addEventListener('change',function(){fields.hidden=!toggle.checked;document.getElementById('streetAddressInput').required=toggle.checked;document.getElementById('postalCodeInput').required=toggle.checked;});
  openButton.addEventListener('click',async function(){
    returnFocus=openButton;modal.hidden=false;status.hidden=true;form.hidden=true;csrf='';
    try{
      var r=await fetch('../../Api/Employee/profile.php',{credentials:'same-origin',cache:'no-store'});var data=await r.json();if(!r.ok||!data.success)throw new Error(data.message);
      csrf=data.csrf_token;var p=data.profile;form.reset();form.elements.mobile_number.value=p.mobile_number||'';form.elements.birth_date.value=p.birth_date||'';form.elements.gender.value=p.gender||'';
      document.getElementById('employeeSavedAddress').textContent='Current address: '+(p.address||'Not provided');toggle.checked=false;toggle.dispatchEvent(new Event('change'));form.hidden=false;
      await ApplicantProfileCascades.init();await ApplicantProfileCascades.hydrateAddress(p);form.elements.mobile_number.focus();
    }catch(error){showError(error.message||'Unable to load profile.');}
  });
  form.addEventListener('submit',async function(event){
    event.preventDefault();if(busy||!csrf)return;busy=true;var button=document.getElementById('employeeSaveProfile');button.disabled=true;
    try{
      var payload={csrf_token:csrf,mobile_number:form.elements.mobile_number.value.trim(),birth_date:form.elements.birth_date.value,gender:form.elements.gender.value,update_address:toggle.checked};
      if(toggle.checked){var address=ApplicantProfileCascades.getAddressValues();payload.address_psgc=address.address_psgc;payload.street_address=address.street_address;payload.postal_code=address.postal_code;}
      var r=await fetch('../../Api/Employee/profile.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});var data=await r.json();
      if(!r.ok||!data.success){showError(data.errors?Object.values(data.errors).join(' '):data.message);var key=Object.keys(data.errors||{})[0];(form.elements[key]||document.getElementById(key==='street_address'?'streetAddressInput':key==='postal_code'?'postalCodeInput':'addressRegionWrap'))?.focus();return;}
      status.hidden=true;modal.hidden=true;successDialog.showModal();successDialog.querySelector('button').focus();
      if(window.UserProfileHydrate)UserProfileHydrate.load();document.dispatchEvent(new Event('employee-profile-updated'));
    }catch(error){showError('Unable to save your profile. Please try again.');}
    finally{busy=false;button.disabled=false;}
  });
})();
