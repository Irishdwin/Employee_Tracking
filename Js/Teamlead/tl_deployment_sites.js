(function(global){
  'use strict';var sites=[],loading=null;
  function esc(v){var d=document.createElement('div');d.textContent=v==null?'':String(v);return d.innerHTML;}
  async function prepare(){
    if(loading)return loading;
    loading=(async function(){var r=await fetch('../../Api/TeamLead/team_deployments.php?sites_only=1',{credentials:'include',cache:'no-store'});var d=await r.json();if(!r.ok||!d.success)throw new Error(d.message||'Unable to load your sites.');sites=d.sites||[];
      ['tlTeamSite','tlDtrSite'].forEach(function(id){var e=document.getElementById(id);if(!e)return;var selected=e.value;e.replaceChildren(new Option('Select deployment site',''));sites.forEach(function(s){e.add(new Option(s.CompanyName+' — '+s.Name,s.SiteID));});e.value=selected;});return sites;
    })();try{return await loading;}finally{loading=null;}
  }
  async function load(){var grid=document.getElementById('tlSitesGrid');grid.textContent='Loading assigned sites…';
    try{await prepare();grid.innerHTML=sites.length?sites.map(function(s){var registered=s.registered_latitude!==null&&s.registered_longitude!==null;return '<article class="rounded-xl bg-white p-5 shadow-card ring-1 ring-slate-100"><h2 class="font-bold">'+esc(s.CompanyName+' — '+s.Name)+'</h2><p class="text-sm text-slate-600 mt-2">'+esc(s.Address)+'</p><p class="mt-2">Employees: '+s.EmployeeCount+'</p><p>Geofence: '+(registered?'Registered':'Not registered')+'</p><button type="button" class="btn btn-primary mt-3" data-site-team="'+s.SiteID+'">View Employees</button></article>';}).join(''):'No active deployment sites assigned.';
      grid.querySelectorAll('[data-site-team]').forEach(function(b){b.onclick=function(){document.getElementById('tlTeamSite').value=b.dataset.siteTeam;global.showView('team');};});
    }catch(e){grid.textContent=e.message;}
  }
  global.TeamLeadSites={prepare:prepare,load:load};
  document.getElementById('tlTeamSite').addEventListener('change',function(){global.loadDeployedPersonnel();});
  document.getElementById('tlDtrSite').addEventListener('change',function(){global.loadDtrCutoffReview();});
  document.getElementById('tlDtrCutoffRef').addEventListener('change',function(){global.loadDtrCutoffReview();});
  global.openSiteDtr=async function(site,ref){await prepare();document.getElementById('tlDtrSite').value=String(site);document.getElementById('tlDtrCutoffRef').value=ref||'';global.showView('dtr');};
})(window);
