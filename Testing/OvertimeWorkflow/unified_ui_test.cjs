const {chromium}=require('C:/Users/Irishdwin Gamutan/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const assert=require('node:assert/strict');
(async()=>{
const browser=await chromium.launch({headless:true,channel:'chrome',args:['--use-fake-device-for-media-stream','--use-fake-ui-for-media-stream','--autoplay-policy=no-user-gesture-required']});
try{for(const width of [1366,390]){
const ctx=await browser.newContext({viewport:{width,height:950},permissions:['geolocation','camera'],geolocation:{latitude:8.4,longitude:124.6,accuracy:5}});
let payload,context={kind:'overtime',can_in:true,can_out:false},errors=[];
const date=new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Manila',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date());
const row={OvertimeID:88,OvertimeDate:date,OTStartTime:'21:00:00',OTEndTime:'23:00:00',EmployeeResponseRequired:1,EmployeeResponse:'Accepted',StatusName:'Accepted',TeamLeadName:'Assigned Lead',CompanyName:'Test Company',SiteName:'Test Site'};context.row=row;
await ctx.route('**/Api/**',async route=>{
const path=new URL(route.request().url()).pathname;
let d={success:true,role:'Employee',user:{role:'Employee',full_name:'Test Employee'},password_configured:true,remaining_seconds:1200,notifications:[],requests:[],employees:[],sites:[],companies:[],documents:[],history:[],records:[],attendance:[],stats:{},schedule:{},days:[],logs:[]};
if(path.endsWith('/overtime_requests.php'))d={...d,requests:[row],counts:{pending:0,accepted:1,active:0},csrf_token:'fixture-csrf',attendance_context:context};
if(path.endsWith('/get_employee_work_site.php'))d={...d,work_site:{site_id:1,site_name:'Test Site',deployment_id:1,company_id:1,company_name:'Test Company',latitude:8.4,longitude:124.6,geofence_radius:100,is_registered:true}};
if(path.endsWith('/clock_in_out_geofence.php')){payload=route.request().postDataJSON();d={success:false,message:'Fixture received the punch.'};}
return route.fulfill({contentType:'application/json',body:JSON.stringify(d)});
});
const page=await ctx.newPage();page.on('pageerror',e=>errors.push(e.message));
await page.goto('http://localhost/Employee_Tracking/Html/Employee/employee_dashboard.html');
await page.waitForFunction(()=>window.EmployeeOvertime&&window.showEmpTab);
await page.evaluate(date=>{showEmpTab('section-emp-timesheet');EmployeeOvertime.calendar(88);},date);
await page.locator('#btnClockIn').waitFor();
await page.waitForFunction(()=>!document.getElementById('btnClockIn').disabled);
assert.equal(await page.locator('[data-ot-clock]').count(),0);
assert.equal(await page.getByRole('button',{name:'Overtime Time In',exact:true}).count(),0);
assert.equal(await page.locator('#btnClockOut').isDisabled(),true);
assert((await page.locator('#employeeUnifiedOvertimeNotice').textContent()).includes('5-minute grace'));
assert.equal(await page.locator('#employeeAttendanceDetails').getAttribute('open'),null);
assert.equal(await page.locator('#employeeUnifiedOvertimeNotice').isVisible(),false);
await page.locator('#employeeAttendanceDetails summary').click();
assert.equal(await page.locator('#employeeUnifiedOvertimeNotice').isVisible(),true);
await page.locator('#employeeAttendanceDetails summary').click();
await page.locator('#btnClockIn').click();await page.locator('#selfieCameraModal').waitFor({state:'visible'});
if(width===1366){
await page.locator('#selfieVideo').evaluate(v=>{v.muted=true;v.play().catch(()=>{});});
await page.waitForFunction(()=>document.getElementById('selfieVideo').readyState>=2);
await page.locator('#btnCaptureSelfie').click();await page.locator('#btnSubmitAttendance').click();
await page.waitForFunction(()=>document.querySelector('.app-feedback-dialog'));
assert(payload.selfie.startsWith('data:image/jpeg'));assert.equal(payload.action,'clock_in');assert.equal(payload.csrf_token,'fixture-csrf');assert(!('overtime_id' in payload));
await page.locator('.app-feedback-dialog button').click();
}
await page.evaluate(()=>{closeCamera();bootstrap.Modal.getInstance(document.getElementById('selfieCameraModal')).hide();});
context={kind:'overtime',can_in:false,can_out:true,row:{...row,StatusName:'Active'}};await page.evaluate(()=>EmployeeOvertime.load());
await page.waitForFunction(()=>document.getElementById('btnClockIn').disabled&&!document.getElementById('btnClockOut').disabled);
context={kind:'closed',can_in:false,can_out:false,message:'Overtime clock-in opens at 8:50 PM.'};await page.evaluate(()=>EmployeeOvertime.load());
await page.waitForFunction(()=>document.getElementById('btnClockIn').disabled&&document.getElementById('btnClockOut').disabled);
assert.equal(errors.length,0,errors.join('\n'));
console.log(width+'px PASS: same controls, camera modal, GPS, Active clock-out state and closed state; desktop additionally verifies photo capture and CSRF payload without OT ID.');await ctx.close();
}}finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
