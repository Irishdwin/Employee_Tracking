// Focused controller tests with synthetic responses; real database/browser tests are separate.
const fs=require('fs'),vm=require('vm'),assert=require('assert');
class Element{
 constructor(tag='div'){this.tagName=tag.toUpperCase();this.children=[];this.listeners={};this.attributes={};this.classes=new Set();this.disabled=false;this.value='';this._text='';}
 set textContent(t){this._text=String(t);this.children=[];}get textContent(){return this._text+this.children.map(e=>e.textContent).join('');}
 set className(t){this.classes=new Set(t.split(' '));}
 get classList(){return {add:k=>this.classes.add(k),remove:k=>this.classes.delete(k),contains:k=>this.classes.has(k)};}
 append(...children){this.children.push(...children);}replaceChildren(...children){this._text='';this.children=children;}add(child){this.append(child);}
 setAttribute(k,v){this.attributes[k]=v;}addEventListener(k,fn){(this.listeners[k]??=[]).push(fn);}
 fire(k){this.listeners[k]?.forEach(fn=>fn({preventDefault(){}}));}click(){this.fire('click');}
}
const ids=['section-employee-management','hrEmployeesSearch','hrEmployeesStatus','hrEmployeesCompany','hrEmployeesSite','hrEmployeesPosition','hrEmployeesType','hrEmployeesFilters','hrEmployeesClear','hrEmployeesRefresh','hrEmployeePanel','hrEmployeePagination','hrEmployeeRows','hrEmployeeTitle','hrEmployeesUpdated','hrEmployeesAlert','hrEmployeeDetailsModal','hrEmployeeDetailsTitle','hrEmployeeDetailsBody','hrEmployeeDetailsRetry'];
const elements=Object.fromEntries(ids.map(id=>[id,new Element()]));elements.hrEmployeesStatus.value='total';
const document={getElementById:id=>elements[id],createElement:tag=>new Element(tag),addEventListener(){}};
const requests=[],pending=[],pages=[],routes=[],timers=new Map();let nextTimer=0;
const window={bootstrap:{Modal:{getOrCreateInstance:()=>({show(){}})}},renderPaginationControls:(el,info,change)=>pages.push({el,info,change}),hrShowSection:target=>{routes.push(target);window.HrEmployees.load();}};
const fetch=(url,opts)=>new Promise((resolve,reject)=>{requests.push({url,opts});pending.push({resolve,reject});});
const context={window,document,fetch,URLSearchParams,Option:function(label,value){const e=new Element('option');e.textContent=label;e.value=value;return e;},Date,setTimeout:fn=>{const id=++nextTimer;timers.set(id,fn);return id;},clearTimeout:id=>timers.delete(id)};
vm.runInNewContext(fs.readFileSync('Js/HR/hr_employees.js','utf8'),context);
const choices={workforce:[{value:'total',label:'All employees'},{value:'awol',label:'AWOL employees'}],companies:[{id:8,label:'Company <script>'}],sites:[{value:'Site'}],positions:[{value:'Position'}],types:[]};
const employee={EmployeeID:10,EmployeeNumber:'EMP-10',FirstName:'<script>',LastName:'Employee',Position:'Position',EmployeeType:null,HiredDate:'2026-10-01',RecordedStatus:'Active',IsActive:1,DeploymentID:null};
const list={rows:[employee],total:1,page:1,pages:1,as_of:'2026-10-05T00:00:00+08:00'};
const settle=()=>new Promise(r=>setImmediate(r));async function respond(data,status=200){pending.shift().resolve({ok:status===200,json:async()=>({success:status===200,data,message:status===403?'HR access required.':'Request failed.'})});await settle();}
let count=0;function ck(ok,label){assert.ok(ok,label);count++;console.log('PASS '+label);}
(async()=>{
 window.HrEmployees.open('awol');ck(routes.at(-1)==='section-employee-management','Workforce card uses existing section navigation');ck(elements.hrEmployeeRows.textContent.includes('Loading'),'Initial loading state');ck(elements.hrEmployeesRefresh.disabled,'Refresh disabled while loading');
 ck(requests.length===1&&requests[0].url.includes('action=employee_options'),'Choices fetched once before list');
 await respond(choices);ck(requests.at(-1).url.includes('filter=awol')&&requests.at(-1).url.includes('page=1'),'Requested KPI filter survives asynchronous options');
 await respond({...list,rows:[],total:0});ck(elements.hrEmployeeRows.textContent.includes('No employees match'),'Empty state');ck(pages.at(-1).info.total===0,'Empty dataset passed to shared pagination');ck(!elements.hrEmployeesRefresh.disabled,'Refresh reenabled');
 window.HrEmployees.open('total');await settle();ck(requests.filter(r=>r.url.includes('employee_options')).length===1,'Options reused across navigation');await respond(list);
 ck(elements.hrEmployeeRows.textContent.includes('<script> Employee'),'Names rendered literally as text');ck(elements.hrEmployeeRows.textContent.includes('Not Deployed'),'No current assignment has explicit state');ck(elements.hrEmployeeRows.textContent.includes('Not recorded'),'Missing employment type not fabricated');
 const button=elements.hrEmployeeRows.children[0].children.at(-1).children[0];button.click();ck(requests.at(-1).url.includes('employee_id=10'),'View Details uses employee identity');ck(elements.hrEmployeeDetailsBody.textContent.includes('Loading'),'Details loading state');
 await respond({employee,deployments:[],resignation:null});ck(elements.hrEmployeeDetailsBody.textContent.includes('No deployment records.'),'Empty deployment history');ck(elements.hrEmployeeDetailsBody.textContent.includes('Personal and employment'),'Employee details rendered');
 const deployments=Array.from({length:12},(_,i)=>({DeploymentID:20+i,CompanyName:'Company '+i,Location:'Long work site address '.repeat(20),Position:'Position <script>',TeamLeadName:'Team Lead',StartDate:'2026-10-01',EndDate:i?'2026-10-03':null,RecordedStatus:i?'Inactive':'Active'}));
 const walk=el=>[el,...el.children.flatMap(walk)];
 const pagesBefore=pages.length;button.click();ck(!requests.at(-1).url.includes('detail_page'),'Details requests complete history without page parameter');
 await respond({employee:{...employee,DeploymentID:20},deployments,resignation:null});
 const cards=walk(elements.hrEmployeeDetailsBody).filter(el=>el.classes.has('hr-deployment-record'));
 ck(cards.length===12,'All twelve deployment records rendered as cards');
 ck(cards.filter(el=>el.classes.has('is-current')).length===1&&cards[0].textContent.includes('Current deployment'),'Only server-selected current deployment highlighted');
 ck(cards[0].textContent.includes('Oct 1, 2026')&&cards[0].textContent.includes('No end date'),'Human-readable start and missing-end dates');
 ck(cards[1].textContent.includes('Inactive')&&!cards[1].textContent.includes('Current deployment'),'Historical recorded status preserved without frontend current guessing');
 ck(cards[0].textContent.includes(deployments[0].Location),'Entire long address preserved');
 ck(cards[0].textContent.includes('Position <script>'),'Deployment fields rendered safely as text');
 ck(pages.length===pagesBefore,'Deployment records do not create pagination');
 button.click();elements.hrEmployeeDetailsModal.fire('hidden.bs.modal');await respond({employee,deployments:[],resignation:null});ck(elements.hrEmployeeDetailsBody.children.length===0,'Closed modal discards delayed sensitive details');
 // Stale employee response cannot replace the newer selected filter.
 const first=window.HrEmployees.load(1);await settle();const older=pending.shift();elements.hrEmployeesSearch.value='new';
 const newer=window.HrEmployees.load(1);await settle();await respond({...list,rows:[],total:0});
 older.resolve({ok:true,json:async()=>({success:true,data:list})});await first;await newer;
 ck(elements.hrEmployeeRows.textContent.includes('No employees match'),'Stale list response ignored');
 elements.hrEmployeesSearch.value='EMP & 10';elements.hrEmployeesCompany.value='8';elements.hrEmployeesFilters.fire('submit');await settle();ck(requests.at(-1).url.includes('q=EMP+%26+10')&&requests.at(-1).url.includes('company_id=8'),'Search and combined filters encoded');await respond(list);
 pages.at(-1).change(2);await settle();ck(requests.at(-1).url.includes('page=2')&&requests.at(-1).url.includes('q=EMP+%26+10'),'Paging preserves active search');await respond({...list,page:2,pages:2});
 elements.hrEmployeesClear.click();await settle();ck(elements.hrEmployeesSearch.value===''&&elements.hrEmployeesCompany.value===''&&elements.hrEmployeesStatus.value==='total','Clear resets filters');await respond(list);
 elements.hrEmployeesRefresh.click();ck(requests.at(-1).url.includes('employee_options'),'Refresh reloads database options');await respond(choices);await respond(null,403);ck(elements.hrEmployeesAlert.textContent==='HR access required.','Authorization error visible');ck(elements.hrEmployeeRows.textContent.includes('unavailable')&&!elements.hrEmployeesRefresh.disabled,'Error leaves usable retry control');
 elements.hrEmployeesRefresh.click();await respond(choices);pending.shift().resolve({ok:true,json:async()=>{throw Error('invalid JSON');}});await settle();ck(elements.hrEmployeesAlert.textContent.includes('Invalid server response'),'Malformed response handled');
 elements.hrEmployeesSearch.fire('input');elements.hrEmployeesSearch.fire('input');ck(timers.size===1,'Search input is debounced');
 ck(requests.every(r=>r.opts.credentials==='same-origin'&&r.opts.cache==='no-store'),'All requests keep authentication and disable sensitive caching');
 console.log('TOTAL '+count+' PASS');
})().catch(error=>{console.error(error);process.exitCode=1;});

