const {chromium}=require('C:/Users/Irishdwin Gamutan/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const assert=require('node:assert/strict');
(async()=>{
 const browser=await chromium.launch({channel:'chrome',headless:true});
 try {
  for(const width of [1366,390]) {
   const page=await browser.newPage({viewport:{width,height:900}});let confirmed=false;
   await page.route('**/Api/**',async route=>{
    const url=route.request().url();let data={success:true,role:'Accounting',full_name:'Synthetic Accounting',timeout_seconds:1200,remaining_seconds:1200};
    if(url.includes('payroll_workflow_context'))data={success:true,csrf_token:'synthetic-token'};
    else if(url.includes('get_payroll_runs'))data={success:true,active_runs:[],history_runs:[],clients:[]};
    else if(url.includes('get_payroll_run_details'))data={success:true,legacy:false,run:{RunCode:'TEST',BillingRefNo:'BR-TEST',client_name:'Synthetic Client',DeploymentSite:'Site One',CutoffLabel:'Sep 26 – Oct 10, 2026',PayrollType:'B',CurrentStatus:'For Client Approval',Status:'Exported',CreatedAt:'2026-10-07 10:00:00',TotalInvoice:1500},sites:['Site One'],employee_count:1,totals:{regular_hours:8,ot_hours:2,gross:1000,deductions:100,net:900},billing:{agency_fee:100,vat:12},employees:[{name:'Synthetic Employee',employee_number:'TEST001',site:'Site One',regular_hours:8,ot_hours:2,holiday_hours:null,basic:800,ot:100,holiday:0,allowances:100,add_back:0,deductions:100,gross:1000,net:900,payment_date:'2026-10-15'}],history:[{status:'For Client Approval',created_at:'2026-10-07 10:00:00',updated_by:'Accounting',remarks:'For review',proof_url:null}],exports:[{ExportID:1,CreatedAt:'2026-10-07 10:00:00'}],revisions:[]};
    else if(url.includes('import_client_billing')) {
      assert.equal(route.request().headers()['x-payroll-csrf'],'synthetic-token');
      if(route.request().postData().includes('name="action"\r\n\r\nconfirm')) {confirmed=true;data={success:true,changed_employees:1,message:'1 employee payroll record(s) updated.'};}
      else data={success:true,preview_token:'synthetic-preview',message:'Review the differences before confirming.',preview:{billing_ref:'BR-TEST',client:'Synthetic Client',site:'Site One',cutoff:'Sep 26 – Oct 10',changed_employees:1,unchanged_employees:4,invalid_rows:0,changes:[{employee_name:'Synthetic Employee',employee_number:'TEST001',component:'allowances',label:'Allowances',old:100,new:200,difference:100},{employee_name:'Synthetic Employee',employee_number:'TEST001',component:'area_site',label:'Area / site (billing record)',old:'Site One',new:'Corrected Site',difference:null}],impacts:[{employee_name:'Synthetic Employee',gross_before:1000,gross_after:1100,deductions_before:100,deductions_after:100,net_before:900,net_after:1000,invoice_before:1500,invoice_after:1612}]}};
    }
    await route.fulfill({contentType:'application/json',body:JSON.stringify(data)});
   });
   await page.goto('http://localhost/Employee_Tracking/Html/Accounting/accounting_dashboard.html');
   await page.waitForFunction(()=>window.PayrollBilling && window.bootstrap);
   await page.evaluate(()=>PayrollBilling.details('TEST'));
   await page.getByRole('heading',{name:'Employee breakdown'}).waitFor();
   await page.waitForFunction(()=>getComputedStyle(document.getElementById('billingRunDetails')).opacity==='1');
   const box=await page.locator('#billingRunDetails .modal-content').boundingBox();assert(box.x>=0&&box.x+box.width<=width+1);
   assert(await page.getByText('Synthetic Employee',{exact:false}).isVisible());
   assert.equal(await page.locator('#billingRunDetails [contenteditable=true],#billingRunDetails input').count(),0);
   await page.screenshot({path:__dirname+`/history-${width}.png`});
   await page.locator('#billingRunDetails .btn-close').click();
   await page.waitForFunction(()=>!document.getElementById('billingRunDetails').classList.contains('show'));
   await page.evaluate(()=>PayrollBilling.importBilling('TEST'));
   await page.locator('#billing-workbook').setInputFiles({name:'returned.xlsx',mimeType:'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',buffer:Buffer.from('synthetic UI fixture')});
   await page.getByRole('button',{name:'Preview Changes',exact:true}).click();
   await page.locator('#billing-reason').waitFor();
   assert(await page.getByText('Corrected Site',{exact:true}).isVisible());
   assert(await page.getByRole('heading',{name:'Result after recalculation'}).isVisible());
   await page.waitForFunction(()=>getComputedStyle(document.getElementById('billingImportReview')).opacity==='1');
   assert(await page.getByText('Changed employees: 1',{exact:false}).isVisible());
   await page.screenshot({path:__dirname+`/import-${width}.png`});
   await page.locator('#billing-reason').fill('Authorized allowance correction');
   await page.getByRole('button',{name:'Confirm 1 Employee Update(s)',exact:true}).click();
   await page.getByText('1 employee payroll record(s) updated.',{exact:true}).waitFor();assert(confirmed);
   console.log(`PASS ${width}: details, responsive fit, read-only breakdown, import preview, CSRF and confirmation`);
   await page.close();
  }
 } finally {await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
