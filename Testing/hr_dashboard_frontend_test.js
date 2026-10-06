// Focused DOM/controller tests; this is not a full browser or visual test.
const fs=require('fs'),vm=require('vm'),assert=require('assert');
class Element {
  constructor(tag='div') { this.tagName=tag.toUpperCase(); this.children=[]; this.listeners={}; this.attributes={}; this.style={}; this.dataset={}; this.classes=new Set(); this._text=''; this.disabled=false; }
  set textContent(value) { this._text=String(value); this.children=[]; }
  get textContent() { return this._text+this.children.map(c=>c.textContent).join(''); }
  set className(value) { this.classes=new Set(value.split(' ')); }
  get classList() { const c=this.classes; return {add:n=>c.add(n),remove:n=>c.delete(n),contains:n=>c.has(n),toggle:(n,v)=> { if(v===undefined) v=!c.has(n); v ? c.add(n) : c.delete(n); }}; }
  append(...nodes) { this.children.push(...nodes); }
  replaceChildren(...nodes) { this._text=''; this.children=nodes; }
  setAttribute(k,v) { this.attributes[k]=v; }
  getAttribute(k) { return this.attributes[k]; }
  addEventListener(type,fn) { (this.listeners[type] ||= []).push(fn); }
  click() { this.listeners.click?.forEach(fn=>fn({preventDefault(){}})); }
  querySelectorAll() { return []; }
}
const ids=['section-overview','hrOverviewMetrics','hrOverviewUpdated','hrOverviewRefresh','hrOverviewAlert','hrEmployeePanel','hrEmployeeTitle','hrEmployeeRows','hrEmployeePrev','hrEmployeeNext','hrEmployeePage','hrEmployeeClose','hrRecruitmentChart','hrDeploymentChart','hrWorkforceChart','hrDtrChart','hrAttendance','hrRecentActivities','filterStatus','hrWorkforceStats','hrRecruitmentStats','hrPendingActions'];
const elements=Object.fromEntries(ids.map(id=>[id,new Element()]));
elements.hrEmployeePanel.classList.add('d-none'); elements.hrOverviewAlert.classList.add('d-none');
const handlers={}, requests=[], pending=[], routes=[], timers=[];
const document={hidden:false,getElementById:id=>elements[id],createElement:tag=>new Element(tag),addEventListener:(event,fn)=> { (handlers[event] ||= []).push(fn); }};
const window={setInterval:fn=>timers.push(fn),hrShowSection:target=>routes.push({target,filter:elements.filterStatus.value}),HrEmployees:{open:filter=>routes.push({target:'section-employee-management',filter})}};
const context=vm.createContext({document,window,fetch:(url,options)=>new Promise((resolve,reject)=> {requests.push({url,options}); pending.push({resolve,reject});}),console,Date});
vm.runInContext(fs.readFileSync('Js/HR/hr_overview.js','utf8'),context);
const metrics={total:3,active:3,inactive:0,awol:0,resigned:0,recent_hires:3,deployed:3,applicants:5,pending:2,applications:6,open_orders:2,pending_orders:0,notifications:35};
const data={metrics,recruitment:[{label:'Applied',count:2,filter:'Applied'},{label:'Hired',count:3,filter:'Hired'}],deployments:[{label:'Client <script>',count:3}],attendance:{clocked_in:0,open_shifts:0},dtr:[],activities:[{label:'HR <script>',happened_at:'2026-10-05 00:00:00'}],date:'2026-10-05',as_of:'2026-10-05T00:00:00+08:00'};
const settle=()=>new Promise(resolve=>setImmediate(resolve));
const respond=async(value,status=200)=> {pending.shift().resolve({ok:status===200,json:async()=>({success:status===200,data:value,message:status===403?'HR access required.':undefined})}); await settle();};
let count=0; function check(condition,label) {assert.ok(condition,label); ++count; console.log('PASS '+label);}
(async()=> {
 handlers.DOMContentLoaded.forEach(fn=>fn());
 check(elements.hrOverviewMetrics.children.length===4,'Exactly four primary KPIs present during loading');
 check(elements.hrOverviewMetrics.textContent.includes('—'),'Loading does not fabricate zeros');
 check(elements.hrOverviewRefresh.disabled,'Refresh disabled while loading');
 window.HrOverview.load(); check(requests.length===1,'Concurrent summary requests deduplicated');
 await respond(data);
 check(elements.hrOverviewMetrics.children[0].textContent.includes('3'),'Live summary rendered');
 check(!elements.hrOverviewRefresh.disabled,'Refresh enabled after success');
 check(elements.hrDtrChart.textContent==='No DTR submissions in the last 30 days.','Empty DTR state');
 check(elements.hrRecentActivities.textContent.includes('HR <script>'),'Database strings rendered as text');
 check(elements.hrRecentActivities.textContent.includes('2026-10-05 00:00:00 · Cagayan de Oro'),'Activity location changes without altering the stored timestamp');
 check(elements.hrOverviewUpdated.textContent.includes('Cagayan de Oro'),'Refresh timestamp uses the client location label');
 check(fs.readFileSync('Js/HR/hr_overview.js','utf8').includes("timeZone:'Asia/Manila'"),'Philippine timezone identifier preserved');
 elements.hrRecruitmentChart.children[1].click(); check(routes.at(-1).target==='section-applicant-review' && routes.at(-1).filter==='Hired','Chart navigates with correct Hired filter');
 elements.hrOverviewMetrics.children[3].click(); check(routes.at(-1).filter==='Applied','Pending card sets initial-review filter');
 elements.hrRecruitmentStats.children[3].click(); check(routes.at(-1).target==='section-job-orders','Job Order card navigates');
 check(!elements.hrOverviewMetrics.textContent.includes('Unread notifications'),'Notifications remain in the existing bell instead of a KPI');
 elements.hrPendingActions.children[0].click(); check(routes.at(-1).filter==='Applied','Real pending-action alert selects Applied filter');
 elements.hrWorkforceStats.children[3].click(); check(routes.at(-1).target==='section-employee-management'&&routes.at(-1).filter==='awol','AWOL card opens centralized Employee Management');
 elements.hrOverviewMetrics.children[0].click(); check(routes.at(-1).filter==='total','Total employees opens all workforce records');
 elements.hrWorkforceChart.children[1].click(); check(routes.at(-1).filter==='inactive','Inactive composition opens matching workforce filter');
 elements.hrOverviewRefresh.click(); await respond(null,403);
 check(elements.hrOverviewAlert.textContent.includes('last successful snapshot'),'Failed refresh visibly preserves last successful snapshot');
 check(elements.hrOverviewMetrics.children[0].textContent.includes('3'),'Failed refresh preserves statistics');
 elements.hrOverviewRefresh.click(); await respond({...data,recruitment:[],deployments:[],activities:[],metrics:{...metrics,total:0,active:0,deployed:0,pending:0,pending_orders:0}});
 check(elements.hrOverviewAlert.classList.contains('d-none'),'Successful retry clears error');
 check(elements.hrRecruitmentChart.textContent==='No applications recorded.','Empty recruitment state');
 check(elements.hrDeploymentChart.textContent.includes('No active employees'),'Empty deployment state');
 check(elements.hrRecentActivities.textContent==='No HR activity recorded.','Empty activities state');
 check(elements.hrPendingActions.textContent==='No applications or job requests awaiting review.','No alerts invented when pending counters are zero');
 elements['section-overview'].classList.add('d-none'); const before=requests.length; timers[0](); check(requests.length===before,'Hidden overview does not poll');
 elements['section-overview'].classList.remove('d-none'); timers[0](); check(requests.length===before+1,'Visible overview refreshes automatically'); await respond(data);
 // Fresh page failure must not retain a perpetual loading state or show invented zeros.
 const fresh=Object.fromEntries(ids.map(id=>[id,new Element()])), freshHandlers={}; fresh.hrEmployeePanel.classList.add('d-none');
 const freshWindow={setInterval(){}};
 vm.runInNewContext(fs.readFileSync('Js/HR/hr_overview.js','utf8'),{document:{...document,getElementById:id=>fresh[id],addEventListener:(event,fn)=>freshHandlers[event]=fn},window:freshWindow,fetch:async()=>{throw new Error('Network unavailable');},Date});
 freshHandlers.DOMContentLoaded(); await settle();
 check(fresh.hrOverviewAlert.textContent.includes('No dashboard statistics are available'),'Initial network failure has explicit error');
 check(fresh.hrOverviewMetrics.textContent.includes('—') && fresh.hrRecentActivities.textContent.includes('Data unavailable'),'Initial failure retains unknown values and ends loading');
 // Exercise the actual shared SPA initializer with the HR configuration.
 const sections=['section-overview','section-profile','section-applicant-review','section-job-orders','section-deployments','section-notifications'].map(id=> {const e=new Element();e.id=id;return e;});
 const main=new Element(); main.querySelectorAll=()=>sections;
 const spaElements=Object.fromEntries(sections.map(e=>[e.id,e])); spaElements['main-content-area']=main;
 const links=sections.slice(2).map(section=> {const e=new Element('a');e.attributes['data-target']=section.id;return e;});
 const calls=[];
 const spaWindow={location:{hash:''},addEventListener(){},HrOverview:{load:()=>calls.push('overview')}};
 const spaContext=vm.createContext({window:spaWindow,document:{getElementById:id=>spaElements[id],querySelectorAll:selector=>selector.includes('sidebar-link')?links:[],querySelector:()=>null},history:{replaceState(){}},loadJobOrders:()=>calls.push('orders'),loadJobOrderRequests:()=>calls.push('requests'),loadApplications:()=>calls.push('applications'),loadDeployments:()=>calls.push('deployments')});
 vm.runInContext(fs.readFileSync('Js/Shared/profile_view_switch.js','utf8'),spaContext); spaContext.ProfileViewSwitch=spaWindow.ProfileViewSwitch;
 const source=fs.readFileSync('Js/HR/hr_dashboard.js','utf8');vm.runInContext(source.slice(source.indexOf('function initSPA()'),source.indexOf('function initOverviewLinks()'))+'\ninitSPA();',spaContext);
 check(typeof spaWindow.hrShowSection==='function','Actual HR SPA initializes with main-content-area');
 links[0].click(); check(calls.includes('applications') && !spaElements['section-applicant-review'].classList.contains('d-none'),'Actual sidebar opens Applicant Review and loads its API');
 spaWindow.hrShowSection('section-overview');check(calls.at(-1)==='overview' && !spaElements['section-overview'].classList.contains('d-none'),'Returning to overview refreshes summary through shared SPA');
 const badgeIds=['hrJobOrdersBadge','adminApplicantReviewBadge','adminApplicantContractsBadge','adminEmployeeConversionBadge'];
 const badgeElements=Object.fromEntries(badgeIds.map(id=>[id,new Element('span')]));const badgeWindow={};
 vm.runInNewContext(fs.readFileSync('Js/Admin/admin_sidebar_badges.js','utf8'),{window:badgeWindow,document:{getElementById:id=>badgeElements[id]},fetch:async()=>({json:async()=>({success:true,logged_in:true,counts:{applicant_review_pending:4}})})});
 badgeWindow.AdminSidebarBadges.apply({job_orders:0,applicant_review_pending:3,applicant_contracts_pending:2,employee_conversion_ready:1});
 check(badgeElements.hrJobOrdersBadge.classList.contains('d-none'),'Zero job-request badge hidden');
 check(badgeElements.adminApplicantReviewBadge.textContent==='3' && !badgeElements.adminApplicantReviewBadge.classList.contains('d-none'),'Existing Admin renderer shows real response review count');
 check(badgeElements.adminApplicantContractsBadge.textContent==='2' && badgeElements.adminEmployeeConversionBadge.textContent==='1','Supported contract and conversion badge counts rendered');
 badgeWindow.AdminSidebarBadges.apply({applicant_review_pending:0});check(badgeElements.adminApplicantReviewBadge.classList.contains('d-none'),'Resolved review count hides the badge');
 await badgeWindow.AdminSidebarBadges.poll('../../Api/Common/realtime_counters.php');check(badgeElements.adminApplicantReviewBadge.textContent==='4','Existing polling refreshes sidebar counts from API response');
 const html=fs.readFileSync('Html/HR/hr_dashboard.html','utf8');const columns=[...html.matchAll(/<div class="hr-overview-column">([\s\S]*?)\n                <\/div>/g)].map(m=>m[1]);
 check(columns.length===2 && columns[0].includes('hrWorkforceHeading') && columns[0].includes('hrRecruitmentHeading'),'Left column independently stacks workforce and recruitment');
 check(columns[1].includes('hrAttendance') && columns[1].includes('hrActivityHeading'),'Right column independently stacks attendance and recent activity');
 check(html.includes('data-global-notif-badge') && html.includes('bi bi-bell fs-5') && html.includes('cc-notif-btn position-relative'),'HR bell uses Admin icon and shared badge hooks');
 console.log(`TOTAL ${count} PASS`);
})().catch(error=> {console.error(error); process.exitCode=1;});
