// Mock browser APIs/DOM: real holiday records are read from local MariaDB, not invented in UI.
const fs=require('fs'),vm=require('vm'),assert=require('assert'),cp=require('child_process');
let passed=0;function check(v,label){assert(v,label);console.log('PASS '+label);passed++;}
const html=fs.readFileSync('Html/Employee/employee_dashboard.html','utf8'),gps=fs.readFileSync('Js/Employee/employee_attendance_sync.js','utf8');
function slice(source,name,next){let a=source.indexOf('function '+name+'('),b=source.indexOf('function '+next+'(',a);assert(a>=0&&b>a);return source.slice(a,b).replace(/async\s*$/,'');}
class Element {
 constructor(){this.children=[];this.dataset={};this.style={};this.listeners={};this.attributes={};this.html='';this.classes=new Set();this.textContent='';}
 set innerHTML(value){this.html=value;this.children=[];}get innerHTML(){return this.html;}
 appendChild(el){this.children.push(el);}prepend(el){this.children.unshift(el);}
 setAttribute(k,v){this.attributes[k]=v;}addEventListener(k,fn){this.listeners[k]=fn;}
 get classList(){return {add:(...xs)=>xs.forEach(x=>this.classes.add(x)),remove:(...xs)=>xs.forEach(x=>this.classes.delete(x))};}
}
const calendarGrid=new Element(),detailPanel=new Element(),monthLabel=new Element(),status=new Element();
const ctx={calendarGrid,detailPanel,monthLabel,viewYear:2026,viewMonth:10,selectedIso:null,logByDate:{},holidaysByDate:{},expectedSchedules:{},expectedMonth:'',expectedLoadingMonth:'',expectedLoadedAt:0,otByDate:{},dashboardState:{deployment:{}},
 STATUS_CLASSES:{future:{bg:'future',text:'future'},empty:{bg:'empty',text:'empty'},present:{bg:'present',text:'white'}},BADGE_BY_STATUS:{present:{cls:'present'},empty:{cls:'empty'},absent:{cls:'absent'}},
 todayIso:()=> '2026-10-05',pad2:n=>String(n).padStart(2,'0'),isoDate:(y,m,d)=>`${y}-${String(m+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`,compareIso:(a,b)=>a<b?-1:a>b?1:0,
 escHtml:s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])),timesForLog:()=>({in:'8 AM',out:'5 PM',breakTime:'1 h',hours:'8 h'}),statusLabelForCalendar:s=>s,dtrStatusToCalendarKey:s=>String(s).toLowerCase(),
 document:{createElement:()=>new Element(),querySelectorAll:()=>[],getElementById:id=>id==='calendar-load-status'?status:null},window:{alert(){}},beginEmployeeGpsInit(){}};
vm.createContext(ctx);
const loader=html.slice(html.indexOf('async function loadExpectedScheduleMonth('),html.indexOf("      document.getElementById('cal-prev').addEventListener"));
vm.runInContext(slice(html,'calendarStatusForDay','timesForLog')+slice(html,'renderDetailPanel','renderAttendanceDetailPanel')+slice(html,'renderAttendanceDetailPanel','buildCalendar')+slice(html,'buildCalendar','loadExpectedScheduleMonth')+'\n'+loader,ctx);
const actual=JSON.parse(cp.execFileSync('C:/xampp/php/php.exe',['-r',"require 'Api/Db_connection/db_connect.php'; require 'Api/Helpers/employee_calendar_holidays.php'; echo json_encode(employee_calendar_holidays($conn,'2026-11-01','2026-12-31'));"],{encoding:'utf8'}));
let calls=[];
ctx.fetch=async url=>{calls.push(url);const key=String(url).match(/start=(\d{4}-\d{2})/)[1];return {ok:true,json:async()=>url.includes('get_employee_schedule')?{success:true,schedules:{},holidays:Object.fromEntries(Object.entries(actual).filter(([day])=>day.startsWith(key)))}:{success:true,log_by_date:{}}};};
(async()=>{
 await ctx.loadExpectedScheduleMonth();
 check(calls.length===2&&calls.every(url=>url.includes('start=2026-11-01')),'Month loads both attendance and schedule/holiday data');
 const holidayCell=calendarGrid.children.find(el=>el.dataset.iso==='2026-11-01');
 check(holidayCell.style.boxShadow.includes('#7c3aed'),'Actual future holiday marked without an attendance record');
 check(holidayCell.title.includes(actual['2026-11-01'][0].name),'Actual holiday name in tooltip');
 holidayCell.listeners.click.call(holidayCell);
 check(detailPanel.children[0].html.includes(ctx.escHtml(actual['2026-11-01'][0].name))&&detailPanel.children[0].html.includes(actual['2026-11-01'][0].type),'Tap/click shows real holiday name and type');
 check(detailPanel.children[0].html.includes('2026-11-01')&&detailPanel.children[0].html.includes('Nationwide'),'Holiday details show actual date and scope');
 ctx.todayIso=()=> '2026-12-05';ctx.logByDate={'2026-11-30':{attendance_status:'Present'}};ctx.buildCalendar();
 const worked=calendarGrid.children.find(el=>el.dataset.iso==='2026-11-30');check(worked.dataset.status==='present'&&worked.style.boxShadow.includes('#7c3aed'),'Holiday marker preserves Present attendance color/status');
 worked.listeners.click.call(worked);check(detailPanel.html.includes('Attendance Record')&&detailPanel.children[0].html.includes(ctx.escHtml(actual['2026-11-30'][0].name)),'Worked holiday retains attendance details and holiday details');
 ctx.viewMonth=11;await ctx.loadExpectedScheduleMonth();check(Object.keys(ctx.holidaysByDate).length===5&&!ctx.holidaysByDate['2026-11-01'],'December loads its five actual holidays without stale November data');
 ctx.viewMonth=9;await ctx.loadExpectedScheduleMonth();check(Object.keys(ctx.holidaysByDate).length===0,'Actual empty October month clears holiday markers');
 ctx.viewMonth=10;await ctx.loadExpectedScheduleMonth();check(Object.keys(ctx.holidaysByDate).length===3,'Returning to November reloads correct holiday records');
 ctx.expectedMonth='';ctx.fetch=async()=>{throw new Error('Offline');};await ctx.loadExpectedScheduleMonth();check(status.textContent.includes('Offline'),'Month retrieval error visible');
 // A late response for an old month cannot replace the new month.
 let release;ctx.expectedMonth='';ctx.expectedLoadingMonth='';ctx.fetch=url=>new Promise(resolve=>{if(url.includes('get_employee_schedule'))release=()=>resolve({ok:true,json:async()=>({success:true,schedules:{},holidays:actual})});else resolve({ok:true,json:async()=>({success:true,log_by_date:{}})});});
 const old=ctx.loadExpectedScheduleMonth();ctx.viewMonth=11;ctx.holidaysByDate={};release();await old;check(Object.keys(ctx.holidaysByDate).length===0,'Stale month response ignored');
 let requests=[],timers=[],messages=[];
 const gpsCtx={navigator:{geolocation:{getCurrentPosition:(success,error,options)=>requests.push({success,error,options})}},setTimeout:fn=>{timers.push(fn);return timers.length;},clearTimeout(){},currentGpsLocation:null,
 setGpsLocation(position){if(!position.coords)return false;gpsCtx.currentGpsLocation=position.coords;return true;},console:{warn:(...args)=>messages.push(args)}};
 vm.createContext(gpsCtx);vm.runInContext(slice(gps,'requestEmployeeGps','initEmployeeGeolocation'),gpsCtx);
 let result=gpsCtx.requestEmployeeGps();timers[0]();requests[0].error({code:3});check(requests.length===2,'Timeout timer/error race starts only one fallback');
 check(requests[1].options.enableHighAccuracy===false&&requests[1].options.maximumAge===0,'Fallback requests fresh standard accuracy');
 requests[1].success({coords:{latitude:10,longitude:120,accuracy:97}});check((await result).accuracy===97,'Successful fallback resolves usable coordinates');
 check(messages.length===0,'Normal high-accuracy timeout does not emit production error spam');
 requests=[];timers=[];result=gpsCtx.requestEmployeeGps().catch(e=>e);requests[0].error({code:1});check((await result).code===1&&requests.length===1,'Permission denial rejects without futile fallback');
 requests=[];timers=[];result=gpsCtx.requestEmployeeGps();requests[0].success({coords:{latitude:11,longitude:121,accuracy:5}});check((await result).accuracy===5&&requests.length===1,'High-accuracy success does not request fallback');
 requests=[];timers=[];result=gpsCtx.requestEmployeeGps().catch(e=>e);requests[0].error({code:2});requests[1].error({code:2});check((await result).code===2,'Unavailable position reported after one fallback');
 check(!gps.includes('[GPS] Live employee coordinates:'),'Repetitive live coordinate logging removed');
 const distanceText=new Element(),badge=new Element();let enabled;
 const quality={currentGpsLocation:{latitude:10,longitude:120,accuracy:200},window:{employeeWorkSite:{geofence_radius:100}},document:{getElementById:id=>id==='gpsDistanceText'?distanceText:badge},getDistanceFromSite:()=>50,setAttendanceButtons:value=>{enabled=value;}};
 vm.createContext(quality);vm.runInContext(slice(gps,'updateGpsUi','setGpsLocation'),quality);quality.updateGpsUi();
 check(distanceText.html.includes('accuracy is wider'),'Low accuracy has a visible warning');
 quality.getDistanceFromSite=()=>150;quality.updateGpsUi();check(enabled===false,'Low accuracy does not expand the fixed site radius');
 console.log('TOTAL '+passed+' PASS (mock geolocation and DOM; live device and responsive visuals NOT VERIFIED)');
})().catch(e=>{console.error(e);process.exitCode=1;});
