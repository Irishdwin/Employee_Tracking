// Isolated HTTP API tests. Test sessions never touch production browser sessions.
const fs=require('fs'),cp=require('child_process'),path=require('path');
const cfg=JSON.parse(fs.readFileSync(path.join(__dirname,'runtime.json'),'utf8'));
const base='http://127.0.0.1:8895';let passed=0;
function check(ok,label,value){if(!ok)throw Error('FAIL '+label+' '+JSON.stringify(value));passed++;console.log('PASS '+label);}
function quote(s){return "'"+s.replace(/\\/g,'\\\\').replace(/'/g,"\\'")+"'";}
function session(uid,role){const code=`ini_set('session.save_path',${quote(cfg.root+'/sessions')});ini_set('session.use_cookies','0');ini_set('session.cache_limiter','');$id=bin2hex(random_bytes(16));session_id($id);session_start();$_SESSION=['user_id'=>${uid},'role'=>${quote(role)},'last_activity'=>time()];session_write_close();echo $id;`;return cp.execFileSync('C:/xampp/php/php.exe',['-r',code],{encoding:'utf8'}).trim();}
async function req(url,sid,body,form=false){let r=await fetch(base+url,{method:body?'POST':'GET',headers:{...(sid?{Cookie:'PHPSESSID='+sid}:{}),'Content-Type':form?'application/x-www-form-urlencoded':'application/json'},...(body?{body:form?new URLSearchParams(body):JSON.stringify(body)}:{})});let raw=await r.text(),data;try{data=JSON.parse(raw);}catch{data=raw;}return{status:r.status,data};}
(async()=>{
const tl=session(cfg.lead,'TeamLead'),admin=session(cfg.admin,'Admin'); const [a,b]=cfg.sites; let r;
r=await req('/Api/Admin/client_companies.php?list=team_leads',admin);check(r.data.team_leads.some(x=>Number(x.UsersID)===cfg.lead),'Already assigned Team Lead remains selectable',r);
r=await req('/Api/TeamLead/team_schedule.php?site_id='+a,tl);check(r.data.success&&!!r.data.csrf_token,'Schedule loads with CSRF',r);
const item=r.data.employees[0]; const payload={action:'save',site_id:a,deployment_id:item.deployment_id,csrf_token:r.data.csrf_token,schedule_type:'Rotating',date:'2026-10-06',time_in:'08:00',time_out:'17:00',break_duration:60,grace_period:5};
r=await req('/Api/TeamLead/team_schedule.php',tl,{...payload,csrf_token:'invalid'});check(r.status===403,'Invalid schedule CSRF rejected',r);
r=await req('/Api/TeamLead/team_schedule.php',tl,payload);check(r.data.success,'Site-owned dated schedule saved with notification',r);
r=await req('/Api/TeamLead/register_work_site.php',tl,{site_id:a,latitude:8.43,longitude:124.65,accuracy:50000,geofence_radius:100});check(r.status>=400&&!r.data.success,'Very inaccurate GPS rejected',r);
r=await req('/Api/TeamLead/register_work_site.php',tl,{site_id:b,latitude:8.44,longitude:124.65,accuracy:20,geofence_radius:100});check(r.status===403,'Former lead cannot overwrite reassigned geofence',r);
r=await req('/Api/Admin/client_companies.php',admin,{action:'save_site',company_id:cfg.company,site_name:'Empty fixture branch',site_address:'Fixture address',team_lead_id:cfg.lead,is_active:1},true);check(r.data.success,'Existing lead reused for additional branch',r);
r=await req('/Api/TeamLead/team_lead_counters.php',tl);check(r.data.badges.dtr===0,'Empty branch creates no DTR obligation',r);
console.log('Extended HTTP checks passed: '+passed);
})().catch(e=>{console.error(e.message);process.exitCode=1;});
