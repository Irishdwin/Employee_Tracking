(function () {
  'use strict';
  var panel=document.getElementById('section-settings');
  var form=document.getElementById('employeePasswordForm');
  if (!panel || !form) return;
  var state=null;
  var busy=false;
  var api='../../Api/Employee/password.php';
  var message=document.getElementById('employeeSecurityMessage');
  var newInput=form.elements.new_password;
  var confirmInput=form.elements.confirm_password;
  var setupButton=document.getElementById('employeeSetupExistingPassword');
  var verification=document.getElementById('employeeEmailVerification');
  var codeForm=document.getElementById('employeePasswordCodeForm');
  function showMessage(text,error) {
    message.textContent=text;
    message.className='alert '+(error?'alert-danger':'alert-success');
    message.hidden=false;
  }
  function refreshValidation() {
    var value=newInput.value;
    var checks=[value.length>=8,/[A-Z]/.test(value),/[a-z]/.test(value),/\d/.test(value),/[^A-Za-z0-9]/.test(value)];
    document.querySelectorAll('#employeePasswordChecklist li').forEach(function (li,index) {
      li.className=value ? (checks[index]?'text-success':'text-danger'):'text-muted';
    });
    var hint=document.getElementById('employeePasswordConfirmHint');
    hint.textContent=confirmInput.value ? (value===confirmInput.value?'Passwords match.':'Passwords do not match.'):'Re-enter the same password.';
    hint.className='form-text '+(confirmInput.value ? (value===confirmInput.value?'text-success':'text-danger'):'');
    confirmInput.classList.toggle('is-invalid',!!confirmInput.value && value!==confirmInput.value);
  }
  attachPasswordStrengthMeter(newInput);
  newInput.addEventListener('input',refreshValidation);
  confirmInput.addEventListener('input',refreshValidation);
  panel.querySelectorAll('[data-security-toggle]').forEach(function (button) {
    button.addEventListener('click',function () {
      var input=document.getElementById(button.dataset.securityToggle);
      var show=input.type==='password';
      input.type=show?'text':'password';
      button.setAttribute('aria-label',show?'Hide password':'Show password');
      button.innerHTML='<i class="bi '+(show?'bi-eye-slash':'bi-eye')+'"></i>';
    });
  });
  async function load(keepMessage) {
    state=null;
    form.hidden=true;
    verification.hidden=true;
    setupButton.hidden=true;
    document.getElementById('employeeChangePassword').hidden=true;
    if (!keepMessage) message.hidden=true;
    document.getElementById('employeePasswordState').textContent='Loading account security…';
    try {
      var response=await fetch(api,{credentials:'same-origin',cache:'no-store'});
      var data=await response.json();
      if (!response.ok || !data.success) throw new Error(data.message || 'Unable to load Settings.');
      state=data;
      form.reset();
      form.querySelectorAll('input').forEach(function (input) {input.type='password';input.classList.remove('is-invalid');});
      panel.querySelectorAll('[data-security-toggle]').forEach(function (button) {button.setAttribute('aria-label','Show password');button.innerHTML='<i class="bi bi-eye"></i>';});
      newInput.dispatchEvent(new Event('input'));
      document.getElementById('employeeOldPasswordGroup').hidden=data.setup_required;
      form.elements.old_password.required=!data.setup_required;
      document.getElementById('employeePasswordTitle').textContent=data.setup_required?'Password Setup Required':'Password';
      document.getElementById('employeePasswordState').textContent=data.setup_required
        ? 'Set up a password so you can log in using your email and password.'
        : data.state_unknown
          ? 'Already have a password? Choose Change Password. If you only used Google or cannot sign in with a password, choose Set Up Password and verify your email.'
          : 'Your password is configured.';
      document.getElementById('employeeSavePassword').textContent=data.setup_required?'Set Up Password':'Change Password';
      form.hidden=!data.setup_required;
      document.getElementById('employeeChangePassword').hidden=data.setup_required;
      setupButton.hidden=data.setup_required;
      setupButton.textContent=data.state_unknown?'Set Up Password':'Reset with email verification';
    } catch (error) {showMessage(error.message,true);}
  }
  window.EmployeeSecurity={open:function () {
    if (window.profileShowSection) window.profileShowSection('section-settings');
    else load(false);
  },load:load};
  document.getElementById('employeeChangePassword').addEventListener('click',function () {
    message.hidden=true;
    document.getElementById('employeePasswordTitle').textContent='Change Password';
    verification.hidden=true;setupButton.hidden=true;
    form.hidden=false;this.hidden=true;form.elements.old_password.focus();
  });
  document.getElementById('employeeCancelPassword').addEventListener('click',function () {load(false);});
  setupButton.addEventListener('click',function () {
    if (!state || busy) return;
    form.hidden=true;verification.hidden=false;codeForm.hidden=true;codeForm.reset();
    document.getElementById('employeeVerificationEmail').textContent=state.email;
  });
  async function emailAction(action,code) {
    if (!state) throw new Error('Reopen Settings and try again.');
    var response=await fetch('../../Api/Login/forgot_password.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({action:action,email:state.email,code:code||''})});
    var result=await response.json();
    if (!response.ok || !result.success) throw new Error(result.message||'Unable to verify email.');
    return result;
  }
  document.getElementById('employeeSendPasswordCode').addEventListener('click',async function () {
    if (busy) return;busy=true;this.disabled=true;
    try {
      var result=await emailAction('send_code');showMessage(result.message,false);
      codeForm.hidden=false;document.getElementById('employeePasswordCode').focus();
    } catch(error) {showMessage(error.message,true);}
    finally {busy=false;this.disabled=false;}
  });
  codeForm.addEventListener('submit',async function (event) {
    event.preventDefault();if(busy)return;busy=true;
    var button=codeForm.querySelector('button');button.disabled=true;
    try {
      await emailAction('verify_code',document.getElementById('employeePasswordCode').value);
      await load(false);showMessage('Email verified. You can now set your password.',false);
      newInput.focus();
    } catch(error) {showMessage(error.message,true);}
    finally {busy=false;button.disabled=false;}
  });
  form.addEventListener('submit',async function (event) {
    event.preventDefault();
    if (!state || busy) return;
    if (newInput.value!==confirmInput.value) {showMessage('New password and confirmation do not match.',true);confirmInput.focus();return;}
    busy=true;
    document.getElementById('employeeSavePassword').disabled=true;
    try {
      var response=await fetch(api,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({
        action:state.setup_required?'setup':'change',csrf_token:state.csrf_token,
        old_password:form.elements.old_password.value,new_password:newInput.value,confirm_password:confirmInput.value
      })});
      var result=await response.json();
      if (!response.ok || !result.success) throw new Error(result.message || 'Unable to save password.');
      showMessage(result.message,false);
      await load(true);
      if (window.OwNotifications) OwNotifications.poll();
    } catch (error) {showMessage(error.message,true);}
    finally {busy=false;document.getElementById('employeeSavePassword').disabled=false;}
  });
})();
