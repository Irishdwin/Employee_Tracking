const {chromium}=require('C:/Users/Irishdwin Gamutan/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const assert=require('node:assert/strict');
(async()=>{const browser=await chromium.launch({headless:true,channel:'chrome'});try{for(const width of [1366,390]){
const ctx=await browser.newContext({viewport:{width,height:900}});let name='Juan Dela Cruz',saved,role='Accounting';
await ctx.route('**/Api/**',async route=>{let d={success:true,role,user:{role,full_name:name},password_configured:true,remaining_seconds:1200,notifications:[],requests:[],records:[],companies:[],logs:[],applications:[],stats:{},csrf_token:'fixture-token'};
const path=new URL(route.request().url()).pathname;
if(path.endsWith('/profile.php')){if(route.request().method()==='POST'){saved=route.request().postDataJSON();assert.equal(route.request().headers()['x-payroll-csrf'],'fixture-token');name=saved.full_name;}d.full_name=name;}
if(path.endsWith('/get_conversion_list.php'))d.pagination={page:1,pages:1,total:0,filters:{company:[],position:[],conversion_state:[]}};
await route.fulfill({contentType:'application/json',body:JSON.stringify(d)});});
const page=await ctx.newPage();await page.goto('http://localhost/Employee_Tracking/Html/Accounting/accounting_dashboard.html');
await page.waitForFunction(()=>document.getElementById('accountingFullName').value==='Juan Dela Cruz');
await page.evaluate(()=>{const section=document.getElementById('view-profile');section.classList.remove('hidden');section.style.display='block';});
await page.locator('#accountingFullName').fill('Maria Santos');await page.locator('#accountingProfileForm button').click();
await page.waitForFunction(()=>document.getElementById('accountingProfileMessage').textContent.includes('Name saved'));
assert.deepEqual(saved,{full_name:'Maria Santos'});assert.equal(await page.locator('#upcFullName').textContent(),'Maria Santos');
await page.screenshot({path:__dirname+'/accounting-name-'+width+'.png'});
role='HR';await page.goto('http://localhost/Employee_Tracking/Html/HR/employee_conversion.html?embedded=1');
await page.waitForFunction(()=>typeof openConversion==='function');await page.evaluate(()=>openConversion(1));await page.locator('#conversionSundayAllowance').waitFor({state:'visible'});
assert.equal(await page.locator('#conversionSundayAllowance').inputValue(),'0');await page.locator('#conversionSundayAllowance').fill('300.00');
await page.waitForTimeout(400);await page.screenshot({path:__dirname+'/sunday-conversion-'+width+'.png'});
role='Employee';await page.goto('http://localhost/Employee_Tracking/Html/Employee/employee_dashboard.html');await page.waitForFunction(()=>window.EmployeePayroll);
await page.evaluate(()=>{EmployeePayroll.fillPayslipModal({is_finalized:true,earnings:{sunday_allowance:600,allowance:100},deductions:{}});bootstrap.Modal.getOrCreateInstance(document.getElementById('payslipModal')).show();});
await page.locator('#payslipModal').waitFor({state:'visible'});assert((await page.locator('#payslipSundayAllowance').textContent()).includes('600.00'));assert((await page.locator('#payslipAllowance').textContent()).includes('100.00'));
console.log(width+'px PASS: conversion allowance, accounting profile save and separate payslip allowance');await ctx.close();
}}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1});
