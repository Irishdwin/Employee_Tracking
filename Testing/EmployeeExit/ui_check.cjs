const {chromium}=require('C:/Users/Irishdwin Gamutan/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const assert=require('assert/strict'),fs=require('fs');
(async()=>{
 const browser=await chromium.launch({headless:true,channel:'chrome'}),results=[];
 try{
  for(const width of [1366,390])for(const [role,folder,pageName,initial,button,next] of [
   // HR is covered in dashboard_ui_check.cjs after dashboard integration.
   ['TeamLead','TeamLead','clearance.html','Pending Team Lead Clearance','Verify & approve physical clearance','Team Lead Cleared'],
   ['Accounting','Accounting','resignation_final_pay.html','Final Pay Processing','Record reviewed final-pay components','Final Pay Ready'],
   ['Employee','Employee','resignation_request.html','Clearance Complete',null,null]
  ]){
   const page=await browser.newPage({viewport:{width,height:900}});let stage=initial,posts=0;const errors=[];page.on('pageerror',e=>errors.push(e.message));
   await page.route('**/Api/**',async route=>{
    if(/check_session|session_heartbeat/.test(route.request().url()))return route.fulfill({json:{success:true,role,full_name:'Synthetic '+role,remaining_seconds:1200,timeout_seconds:1200}});
    if(route.request().method()==='POST'){assert.equal(route.request().headers()['x-exit-csrf'],'synthetic-token');posts++;stage=next;return route.fulfill({json:{success:true,id:1,message:'Workflow recorded successfully.'}});}
    const row={ResignationID:1,EmployeeID:1,EmployeeName:'Synthetic Employee',EmployeeNumber:'EXIT-1',SubmissionDate:'2026-10-09',FinalPayAmount:990.25,WorkingDaysRemaining:null,workflow:{stage,site_name:'Synthetic company — Second deployment site',accepted_date:stage==='Clearance Complete'?'2026-10-09':null,calendar_policy:'Pending agency working-day calendar confirmation',letter_reference:'LETTER-REFERENCE-2026',last_remarks:'Physical documents verified by authorized staff.'},history:[{Action:'register',CreatedAt:'2026-10-09 08:00:00',Actor:'Synthetic HR',Details:'{"remarks":"Physical letter personally received"}'}],payroll_history:[],approved_adjustments:[],payment_status_history:[]};
    await route.fulfill({json:{success:true,role,csrf_token:'synthetic-token',resignations:[row],awol_cases:role==='HR'?[{CaseID:1,EmployeeName:'Synthetic AWOL review',State:'Resolved',Evidence:'{"dates":["2026-10-02","2026-10-05","2026-10-06"]}',Remarks:'Corrected attendance verified',history:row.history}]:[],employees:[{EmployeeID:1,EmployeeName:'Synthetic Employee',EmployeeNumber:'EXIT-1'}]}});
   });
   await page.goto('http://localhost/Employee_Tracking/Html/'+folder+'/'+pageName,{waitUntil:'networkidle'});await page.locator('.exit-card').first().waitFor();
   assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true,role+' horizontal overflow '+width);
   assert.equal(await page.locator('button').filter({hasText:'Finalize resignation'}).count(),0);
   if(button){await page.getByRole('button',{name:button,exact:true}).click();const dialog=page.locator('.exit-modal');await dialog.waitFor();await dialog.locator('[name=remarks]').fill('Physical evidence reviewed.');if(role==='Accounting'){await dialog.locator('[data-key=description]').fill('Reviewed unpaid wage');await dialog.locator('[data-key=amount]').fill('990.25');await dialog.locator('[data-key=reference]').fill('PAYROLL-1 reviewed');await dialog.locator('[name=components_confirmed]').check();}await dialog.getByRole('button',{name:'Save',exact:true}).click();await page.locator('.app-feedback-dialog').getByRole('button',{name:'Continue',exact:true}).click();await page.locator('.app-feedback-dialog').getByRole('button',{name:'OK',exact:true}).click();await page.getByText(next,{exact:true}).waitFor();assert.equal(posts,1);}
   else{assert.match(await page.locator('.exit-readonly').textContent(),/physical/);assert.equal(await page.getByRole('button',{name:'Register physical resignation letter'}).count(),0);assert.equal(posts,0);}
   assert.deepEqual(errors,[]);await page.screenshot({path:__dirname+'/'+role.toLowerCase()+'-'+width+'.png',fullPage:true});results.push(role+' '+width+'px: responsive layout, permitted actions, confirmation/success, CSRF');await page.close();
  }
  fs.writeFileSync(__dirname+'/ui_results.json',JSON.stringify({passed:results.length,tests:results},null,2));console.log(results.join('\n'));
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1)});
