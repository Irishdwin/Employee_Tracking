(function(global){
 'use strict';
 var rule=/^\+639\d{9}$/;
 // Extracted from applicant onboarding's existing input mask.
 function format(value){
  if(!value.startsWith('+63'))value='+63'+value.replace(/^\+?6?3?/,'');
  return '+63'+value.slice(3).replace(/\D/g,'').slice(0,10);
 }
 function message(value){
  if(!value || value==='+63')return 'Format: +639XXXXXXXXX (10 digits required after +63).';
  if(!/^\+63\d*$/.test(value))return 'Use +63 followed by digits only.';
  if(value.length>3 && value[3]!=='9')return 'Mobile number must start with +639.';
  return rule.test(value)?'':'Format: +639XXXXXXXXX (10 digits required after +63).';
 }
 var profileRule=/^(09|\+639|639)\d{9}$/;
 function profileValid(value){return profileRule.test(value) && !/^(\d)\1+$/.test(value.replace(/\D/g,''));}
 function attach(input,options){
  var profile=options && options.profile;
  var feedback=document.createElement('div');feedback.className='invalid-feedback';feedback.setAttribute('aria-live','polite');
  feedback.id=input.id+'Feedback';input.after(feedback);input.setAttribute('aria-describedby',feedback.id);input.inputMode='tel';
  function update(){if(profile){input.value=input.value.replace(/\s+/g,'').replace(/(?!^\+)[^\d]/g,'');}var value=input.value.trim();var error=profile?(profileValid(value)?'':'Please enter a valid Philippine mobile number.'):message(value);input.setCustomValidity(error);feedback.textContent=error;input.classList.toggle('is-invalid',!!error && (!profile || !!value));input.classList.toggle('is-valid',!error);return !error;}
  input.addEventListener('input',update);input.addEventListener('blur',update);
  if(input.form)input.form.addEventListener('reset',function(){setTimeout(function(){input.setCustomValidity('');input.classList.remove('is-invalid','is-valid');},0);});
  return update;
 }
 global.PhMobile={rule:rule,profileRule:profileRule,profileValid:profileValid,format:format,message:message,attach:attach};
})(window);
