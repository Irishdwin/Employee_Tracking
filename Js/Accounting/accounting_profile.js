(function(){'use strict';
const form=document.getElementById('accountingProfileForm'),input=document.getElementById('accountingFullName'),message=document.getElementById('accountingProfileMessage');
if(!form)return;let csrf='';const url='../../Api/Accounting/profile.php';
fetch(url,{credentials:'same-origin',cache:'no-store'}).then(r=>r.json()).then(d=>{if(!d.success)throw Error(d.message);input.value=d.full_name;csrf=d.csrf_token;}).catch(e=>message.textContent=e.message);
form.addEventListener('submit',async e=>{e.preventDefault();const button=form.querySelector('button');button.disabled=true;message.textContent='';try{
 const r=await fetch(url,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-Payroll-CSRF':csrf},body:JSON.stringify({full_name:input.value})});const d=await r.json();if(!r.ok||!d.success)throw Error(d.message||'Could not save your name.');
 input.value=d.full_name;document.getElementById('upcFullName').textContent=d.full_name;message.textContent='Name saved. Your next billing export will use this name.';
 if(window.UserProfileHydrate)UserProfileHydrate.load();
 if(window.AppDialog)await AppDialog.alert('Your name was saved successfully.','Profile updated');
}catch(e){message.textContent=e.message;}finally{button.disabled=false;}});
})();
