const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const {chromium}=require('C:/Users/Irishdwin Gamutan/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
(async()=>{
 const browser=await chromium.launch({headless:true,channel:'chrome'});
 const results=[];
 try {
  for(const viewport of [{width:1366,height:900},{width:390,height:844}]) {
   const page=await browser.newPage({viewport});let setup=true, unknown=false;
   await page.route('**/Api/**',async route=>{
    const url=route.request().url();let data={success:true};
    if(url.includes('check_session.php')) data={success:true,role:'Employee',full_name:'TEST Employee',timeout_seconds:1200,remaining_seconds:1200};
    else if(url.includes('/Employee/password.php')) {
     if(route.request().method()==='POST') {const body=route.request().postDataJSON();assert.equal(body.action,'setup');assert(!('user_id' in body));setup=false;data={success:true,message:'Your password has been set successfully.'};}
     else data={success:true,setup_required:setup,state_unknown:unknown,email:'test@example.invalid',csrf_token:'synthetic-csrf'};
    } else if(url.includes('/forgot_password.php')) {
     const action=new URLSearchParams(route.request().postData()).get('action');
     if(action==='verify_code') setup=true;
     data={success:true,message:action==='send_code'?'A code was sent.':'Email verified.'};
    } else if(url.includes('/notifications.php')) data={success:true,unread_count:1,notifications:[{id:1,type:'employee_password_setup',title:'Set up your Employee password',message:'TEST: Open Settings to set up your password.',is_read:false}]};
    else if(url.includes('dashboard_data.php')) data={success:true,profile:{full_name:'TEST Employee'},documents:[],attendance:[]};
    await route.fulfill({contentType:'application/json',body:JSON.stringify(data)});
   });
   await page.goto('http://localhost/Employee_Tracking/Html/Employee/employee_dashboard.html',{waitUntil:'domcontentloaded'});
   await page.locator('#profile-menu-btn').click();
   await page.locator('[data-show-section="section-settings"]').click();
   await page.waitForFunction(()=>document.getElementById('employeePasswordTitle').textContent==='Password Setup Required');
   await assert.equal(await page.locator('#employeeOldPasswordGroup').isVisible(),false);
   await page.locator('[name="new_password"]').fill('Fjord!Maple42');
   await assert.equal(await page.locator('#section-settings .password-strength-label').textContent(),'Strong');
   await page.locator('[data-security-toggle="employeeSecuritynew_password"]').click();
   assert.equal(await page.locator('[name="new_password"]').getAttribute('type'),'text');
   await page.locator('[data-security-toggle="employeeSecuritynew_password"]').click();
   await page.locator('[name="confirm_password"]').fill('different');
   assert.equal(await page.locator('#employeePasswordConfirmHint').textContent(),'Passwords do not match.');
   await page.locator('[name="confirm_password"]').fill('Fjord!Maple42');
   await page.screenshot({path:path.join(__dirname,`settings-${viewport.width}.png`),fullPage:true});
   const bounds=await page.locator('#section-settings .security-card').boundingBox();assert(bounds.x>=0&&bounds.x+bounds.width<=viewport.width+1);
   await page.locator('#employeeSavePassword').click();
   await page.waitForFunction(()=>document.getElementById('employeePasswordState').textContent==='Your password is configured.');
   await page.locator('#employeeChangePassword').click();
   assert(await page.locator('[name="old_password"]').isVisible());
   assert.equal(await page.locator('#employeePasswordForm input:visible').count(),3);
   await page.screenshot({path:path.join(__dirname,`change-password-${viewport.width}.png`),fullPage:true});
   await page.locator('#employeeCancelPassword').click();
   unknown=true;
   await page.evaluate(()=>EmployeeSecurity.load(false));
   await page.locator('#employeeSetupExistingPassword').click();
   await page.locator('#employeeSendPasswordCode').click();
   await page.locator('#employeePasswordCode').fill('1234');
   await page.getByRole('button',{name:'Verify code',exact:true}).click();
   await page.waitForFunction(()=>!document.getElementById('employeePasswordForm').hidden);
   assert(!(await page.locator('[name="old_password"]').isVisible()));
   await page.screenshot({path:path.join(__dirname,`legacy-setup-${viewport.width}.png`),fullPage:true});
   await page.locator('#section-settings [data-show-section="section-dashboard-main"]').click();
   assert(!(await page.locator('#section-settings').isVisible()));
   await page.evaluate(()=>OwNotifications.show());
   await page.getByRole('button',{name:'Open Settings',exact:true}).click();
   assert(await page.locator('#section-settings').isVisible());
   results.push({viewport:viewport.width,status:'PASS',checks:'Profile Settings; setup fields; shared strength; eye toggle; confirmation; success state; three-field change; container fits; Cancel; legacy email-code setup; Back; notification action'});
   await page.close();
  }
  fs.writeFileSync(path.join(__dirname,'ui_results.json'),JSON.stringify({success:true,results,api_data:'Synthetic responses; production backend independently tested in run.php'},null,2));
  console.log(JSON.stringify(results,null,2));
 } finally {await browser.close();}
})().catch(error=>{console.error(error);process.exit(1);});
