// Isolated HTTP API tests. Test sessions never touch production browser sessions.
const fs=require('fs'),cp=require('child_process'),path=require('path');
const cfg=JSON.parse(fs.readFileSync(path.join(__dirname,'runtime.json'),'utf8'));
const base='http://127.0.0.1:8895';let passed=0;
function check(ok,label,value){if(!ok)throw Error('FAIL '+label+' '+JSON.stringify(value));passed++;console.log('PASS '+label);}
function quote(s){return "'"+s.replace(/\\/g,'\\\\').replace(/'/g,"\\'")+"'";}
function session(uid,role){const code=`ini_set('session.save_path',${quote(cfg.root+'/sessions')});ini_set('session.use_cookies','0');ini_set('session.cache_limiter','');$id=bin2hex(random_bytes(16));session_id($id);session_start();$_SESSION=['user_id'=>${uid},'role'=>${quote(role)},'last_activity'=>time()];session_write_close();echo $id;`;return cp.execFileSync('C:/xampp/php/php.exe',['-r',code],{encoding:'utf8'}).trim();}
async function req(url,sid,body,form=false){let r=await fetch(base+url,{method:body?'POST':'GET',headers:{...(sid?{Cookie:'PHPSESSID='+sid}:{}),'Content-Type':form?'application/x-www-form-urlencoded':'application/json'},...(body?{body:form?new URLSearchParams(body):JSON.stringify(body)}:{})});let raw=await r.text(),data;try{data=JSON.parse(raw);}catch{data=raw;}return{status:r.status,data};}
(async()=>{
 const tl=session(cfg.lead,'TeamLead'),other=session(cfg.other,'TeamLead'),acc=session(cfg.accounting,'Accounting'),admin=session(cfg.admin,'Admin');
 const [a,b]=cfg.sites,emps=cfg.employees;let r;
 r=await req('/Api/TeamLead/team_deployments.php');check(r.status===401,'Unauthenticated access rejected',r);
 r=await req('/Api/TeamLead/team_deployments.php',acc);check(r.status===403,'Wrong-role access rejected',r);
 for(const [i,eid] of emps.entries()){
  r=await req('/Api/HR/deployments.php',admin,{employee_id:eid,company_id:cfg.company,site_id:i<2?a:b,position:'Fixture Staff',start_date:'2026-09-11',team_lead_id:cfg.other},true);
  check((r.data.success)||String(r.data.message).includes('already deployed'),'Deployment stores site and derives assigned supervisor',r);
 }
 for(const [i,site] of [a,b].entries()){
  r=await req('/Api/TeamLead/register_work_site.php',tl,{site_id:site,latitude:8.43+i*.01,longitude:124.65,accuracy:97,geofence_radius:100+i*50});check(r.data.success,'Independent branch geofence save',r);
 }
 r=await req('/Api/TeamLead/team_deployments.php?sites_only=1',tl);check(r.data.sites.length===2,'One Team Lead owns two branches',r);check(r.data.sites.every(s=>Number(s.EmployeeCount)===2),'Branch counters count current unique employees',r);
 for(const [i,s] of [a,b].entries()){r=await req('/Api/TeamLead/team_deployments.php?site_id='+s,tl);check(JSON.stringify(r.data.deployments.map(e=>e.employee_id))===JSON.stringify(emps.slice(i*2,i*2+2)),'Branch roster isolation '+s,r);}
 r=await req('/Api/TeamLead/team_deployments.php?site_id='+a,other);check(r.status===403,'Unauthorized site rejected',r);
 r=await req('/Api/TeamLead/get_work_site.php?site_id='+a,other);check(r.status===403,'Unauthorized geofence rejected',r);
 r=await req('/Api/TeamLead/get_work_site.php?site_id='+a,tl);check(r.data.latitude===8.43&&r.data.geofence_radius===100,'Branch 1 retained independent coordinates',r);
 r=await req('/Api/TeamLead/get_work_site.php?site_id='+b,tl);check(r.data.latitude===8.44&&r.data.geofence_radius===150,'Branch 2 retained independent coordinates',r);
 r=await req('/Api/TeamLead/get_dtr_cutoff_review.php',tl);check(r.data.requires_site&&r.data.employees.length===0,'DTR requires site first',r);
 r=await req('/Api/TeamLead/get_dtr_cutoff_review.php?site_id='+a+'&cutoff_ref=2026-09-11',tl);check(r.data.success&&JSON.stringify(r.data.employees.map(e=>e.employee_id))===JSON.stringify(emps.slice(0,2)),'DTR site-specific rows',r);
 r=await req('/Api/TeamLead/team_schedule.php?site_id='+b,tl);check(r.data.success&&JSON.stringify(r.data.employees.map(e=>e.employee_id))===JSON.stringify(emps.slice(2)),'Schedule site-specific roster',r);
 r=await req('/Api/TeamLead/team_lead_counters.php',tl);check(r.data.badges?.dtr===2,'Two pending site/cutoff obligations give badge 2',r);
 r=await req('/Api/TeamLead/team_lead_counters.php',tl);check(r.data.badges?.dtr===2,'Refresh preserves obligation count',r);
 r=await req('/Api/Common/notifications.php',tl);check(r.data.notifications.filter(n=>n.type==='dtr_submission_reminder').length===2,'Refresh deduplicates reminder records',r);
 await req('/Api/Common/notifications.php',tl,{action:'mark_all_read'});r=await req('/Api/TeamLead/team_lead_counters.php',tl);check(r.data.badges.dtr===2,'Reading bell reminders does not clear action badge',r);
 r=await req('/Api/TeamLead/submit_dtr.php',tl,{site_id:a,employee_ids:[emps[2]],cutoff_start:'2026-09-11',cutoff_end:'2026-09-25'});check(r.status===403,'Cross-branch DTR write rejected',r);
 for(const [i,s] of [a,b].entries()){
  r=await req('/Api/TeamLead/submit_dtr.php',tl,{site_id:s,employee_ids:emps.slice(i*2,i*2+2),cutoff_start:'2026-09-11',cutoff_end:'2026-09-25'});check(r.data.success&&r.data.submitted.length===2,'Submit branch '+s,r);
  r=await req('/Api/TeamLead/team_lead_counters.php',tl);check(r.data.badges.dtr===1-i,'Action badge decreases to '+(1-i),r);
 }
 r=await req('/Api/Accounting/get_submitted_dtrs.php',acc);check(r.data.success&&r.data.submissions.filter(s=>s.tl_id===cfg.lead).length===2,'Accounting separates same-company branch batches',r);
 const detail='/Api/Accounting/get_submitted_dtrs.php?tl_id='+cfg.lead+'&site_id='+a+'&cutoff_start=2026-09-11&cutoff_end=2026-09-25';
 r=await req(detail,acc);check(r.data.success&&r.data.batch.site_id===a&&r.data.employees.length===2,'Accounting resolves correct site/company/lead/employees/cutoff',r);
 r=await req('/Api/Accounting/verify_dtr_submission.php',acc,{tl_id:cfg.lead,site_id:a,cutoff_start:'2026-09-11',cutoff_end:'2026-09-25'});check(r.data.success&&r.data.verified_count===2,'Accounting verifies selected branch only',r);
 r=await req('/Api/Admin/client_companies.php',admin,{action:'save_site',company_id:cfg.company,site_id:b,site_name:'Branch 2',site_address:'Fixture branch 2',team_lead_id:cfg.other,is_active:1},true);check(r.data.success,'Admin reassigns one branch to an existing account',r);
 r=await req('/Api/TeamLead/team_deployments.php?sites_only=1',tl);check(r.data.sites.length===1&&Number(r.data.sites[0].SiteID)===a,'Original lead retains Branch 1 after reassignment',r);
 r=await req('/Api/TeamLead/team_deployments.php?site_id='+b,tl);check(r.status===403,'Former lead cannot access reassigned branch',r);
 console.log('HTTP checks passed: '+passed);
})().catch(e=>{console.error(e.message);process.exitCode=1;});
