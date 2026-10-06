(function(){
  'use strict';
  var form=document.getElementById('addDeploymentForm'),company=document.getElementById('selCompany');if(!form||!company)return;
  var box=document.createElement('div');box.className='mb-3';
  box.innerHTML='<label class="form-label" for="deploymentSiteSelect">Deployment site *</label><select name="site_id" id="deploymentSiteSelect" class="form-select" required disabled><option value="">Select company first</option></select><p id="deploymentSiteHelp" class="small text-muted"></p>';
  company.parentElement.after(box);var select=box.querySelector('select'),sites=[],sequence=0;
  async function load(){var version=++sequence;sites=[];select.disabled=true;select.replaceChildren(new Option('Loading sites…',''));
    try{var r=await fetch('../../Api/HR/deployments.php?list=sites&company_id='+encodeURIComponent(company.value),{credentials:'include'});var d=await r.json();if(version!==sequence)return;if(!r.ok||!d.success)throw new Error(d.message||'Unable to load sites');sites=d.sites;select.replaceChildren(new Option(sites.length?'Select deployment site':'No active sites configured',''));sites.forEach(function(s){select.add(new Option(s.Name,s.SiteID));});select.disabled=!sites.length;document.getElementById('deploymentSiteHelp').textContent=sites.length?'The site determines location and Team Lead.':'Admin must configure this company’s deployment sites first.';
    }catch(e){if(version!==sequence)return;select.replaceChildren(new Option('Unable to load sites',''));document.getElementById('deploymentSiteHelp').textContent=e.message;}
  }
  company.addEventListener('change',load);form.addEventListener('reset',function(){++sequence;select.disabled=true;select.replaceChildren(new Option('Select company first',''));});
  select.addEventListener('change',function(){var s=sites.find(function(x){return String(x.SiteID)===select.value;});if(form.location){form.location.value=s?s.Address:'';form.location.readOnly=true;}var tl=document.getElementById('selTeamLead');if(tl){tl.value=s&&s.TeamLeadID?String(s.TeamLeadID):'';tl.disabled=true;}});
})();
