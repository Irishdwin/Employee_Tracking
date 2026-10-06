const fs=require('fs'),vm=require('vm'),assert=require('assert');
const source=fs.readFileSync('Js/Employee/employee_attendance_sync.js','utf8');let count=0;const check=(v,m)=>{assert(v,m);console.log('PASS '+m);count++;};
const button={disabled:false,innerHTML:''},badge={},text={};let calls=[],cleared=[],updates=0,watchers=0,enabled=false;
const site={latitude:10,longitude:120,geofence_radius:100,is_registered:true};
const ctx={window:{employeeWorkSite:site},document:{getElementById:id=>id==='btnRefreshEmployeeGps'?button:id==='geofenceBadge'?badge:text},navigator:{geolocation:{clearWatch:id=>cleared.push(id),getCurrentPosition:(success,error,options)=>calls.push({success,error,options})}},setTimeout:()=>1,clearTimeout(){},gpsStarting:false,gpsRequestToken:0,watchPositionId:4,currentGpsLocation:{latitude:1,longitude:1,accuracy:5},setAttendanceButtons:v=>{enabled=v;},loadEmployeeWorkSite:async()=>site,updateGpsUi:()=>updates++,startGpsWatcher:()=>watchers++,startWorkSitePolling(){},gpsErrorMessage:e=>e.code===1?'Location permission is required to verify your work site.':'GPS request timed out. Please try refreshing your location.',console,
setGpsLocation:p=>{ctx.currentGpsLocation=p.coords;ctx.window.currentEmployeeCoords=p.coords;return true;}};
vm.createContext(ctx);
vm.runInContext(source.slice(source.indexOf('  function requestEmployeeGps()'),source.indexOf('  function refreshGpsButton('))+source.slice(source.indexOf('  function refreshGpsButton('),source.indexOf('  function updateLiveCameraHud(')),ctx);
(async()=>{
const before=JSON.stringify(site);const work=ctx.window.refreshEmployeeGpsLocation();
check(calls.length===1&&calls[0].options.maximumAge===0&&calls[0].options.enableHighAccuracy,'Manual refresh requests fresh high accuracy');
check(button.disabled&&button.innerHTML.includes('Refreshing GPS'),'Loading button disabled');
ctx.window.refreshEmployeeGpsLocation();check(calls.length===1,'Repeated clicks cannot duplicate request');
check(cleared[0]===4&&ctx.currentGpsLocation===null&&!enabled,'Old watcher and cached reading cleared while actions disabled');
calls[0].success({coords:{latitude:10.00001,longitude:120.00001,accuracy:12}});await work;
check(ctx.currentGpsLocation.accuracy===12&&ctx.currentGpsLocation.latitude===10.00001,'New coordinates and actual accuracy replace old reading');
check(updates>=1&&watchers===1,'Refresh recalculates UI and restarts one watcher');
check(!button.disabled&&button.innerHTML.includes('Refresh GPS Location'),'Button restored after success');
check(JSON.stringify(site)===before,'Expected work-site coordinates unchanged');
const denied=ctx.window.refreshEmployeeGpsLocation();calls[1].error({code:1});await denied;
check(!button.disabled&&!enabled&&text.innerHTML.includes('permission'),'Permission failure shows safe message and restores retry button');
const html=fs.readFileSync('Html/Employee/employee_dashboard.html','utf8');check(html.indexOf('id="btnRefreshEmployeeGps"')>html.indexOf('id="btnClockOut"'),'Refresh control below Clock Out');
console.log('TOTAL '+count+' PASS (mock browser APIs; physical GPS NOT VERIFIED)');
})().catch(e=>{console.error(e);process.exitCode=1;});
