(function(global){
 'use strict';
 var context;
 function security(){
  if(!context)context=fetch('../../Api/Admin/client_companies.php?list=context',{credentials:'include'}).then(function(r){return r.json();}).then(function(d){if(!d.success||!d.csrf_token)throw Error('Reload the page to verify your session.');return d.csrf_token;}).catch(function(e){context=null;throw e;});
  return context;
 }
 async function request(url,options){
  options=Object.assign({credentials:'include'},options);
  if((options.method||'GET').toUpperCase()==='POST') {
   var headers=new Headers(options.headers);headers.set('X-Payroll-CSRF',await security());options.headers=headers;
  }
  return fetch(url,options);
 }
 function gmailMessage(value){
  return /^[A-Z0-9._%+\-]+@gmail\.com$/i.test(value.trim())?'':'Team Lead email must be a valid Gmail address.';
 }
 function attachAccount(form,prefix){
  var email=form.querySelector('[name="tl_email"]'),password=form.querySelector('[name="tl_password"]'),confirm=form.querySelector('[name="tl_password_confirm"]'),name=form.querySelector('[name="tl_full_name"]');
  var timer,generation=0,checkedEmail='',checkedExists=false,status=document.getElementById(prefix+'EmailCheckStatus');
  function displayEmail(message,error){if(status){status.textContent=message;status.className='d-block mt-1 small '+(error?'text-danger':'text-success');}email.setCustomValidity(error?message:'');email.classList.toggle('is-invalid',error);email.classList.toggle('is-valid',!error && !!email.value);}
  function emailUpdate(){
   clearTimeout(timer);var current=++generation,value=email.value.trim();
   var error=gmailMessage(value);displayEmail(value?error:'Gmail is required.',true);
   if(error || email.disabled)return;
   if(checkedEmail===value){displayEmail(checkedExists?'This Gmail address is already registered. Please use another Gmail account.':'Gmail is available.',checkedExists);return;}
   displayEmail('Checking Gmail availability…',false);email.classList.remove('is-valid');
   timer=setTimeout(async function(){try{var response=await request('../../Api/Admin/client_companies.php?check_email='+encodeURIComponent(value));var d=await response.json();if(current!==generation||!email.isConnected)return;if(!response.ok||!d.success)throw Error();checkedEmail=value;checkedExists=!!d.exists;displayEmail(d.exists?'This Gmail address is already registered. Please use another Gmail account.':'Gmail is available.',!!d.exists);}catch(e){if(current===generation && status){status.textContent='Unable to check availability. The server will verify when saving.';status.className='small text-muted d-block mt-1';}}},300);
  }
  function passwordUpdate(){
   global.updateTlPasswordValidationUI(prefix);
   var result=global.validateStrongPassword(password.value);
   password.setCustomValidity(password.disabled || result.isValid?'':result.commonOrPredictable?'Password is too common or predictable. Please choose a stronger password.':'Password must include '+result.failed.join(', ')+'.');
   confirm.setCustomValidity(confirm.disabled || (confirm.value && confirm.value===password.value)?'':'Passwords do not match.');
  }
  email.addEventListener('input',emailUpdate);password.addEventListener('input',passwordUpdate);confirm.addEventListener('input',passwordUpdate);
  name.addEventListener('input',function(){var valid=!!name.value.trim();name.setCustomValidity(valid?'':'Full name is required.');name.classList.toggle('is-invalid',!valid);name.classList.toggle('is-valid',valid);});
  form.querySelectorAll('[data-toggle-password]').forEach(function(button){button.dataset.accountToggleBound='1';button.addEventListener('click',function(){var input=document.getElementById(button.dataset.target);var show=input.type==='password';input.type=show?'text':'password';button.setAttribute('aria-label',show?'Hide password':'Show password');button.querySelector('i').className=show?'bi bi-eye-slash':'bi bi-eye';});});
  form.addEventListener('reset',function(){generation++;clearTimeout(timer);[email,password,confirm,name].forEach(function(input){input.setCustomValidity('');input.classList.remove('is-valid','is-invalid');});});
  return function(){emailUpdate();passwordUpdate();name.dispatchEvent(new Event('input'));return form.checkValidity();};
 }
 global.ClientCompaniesUI={request:request,attachAccount:attachAccount,gmailMessage:gmailMessage};
})(window);
