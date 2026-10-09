const fs=require('fs'),assert=require('assert/strict');
const {chromium}=require('C:/Users/Irishdwin Gamutan/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
(async()=>{
 const source=fs.readFileSync('Html/TeamLead/team_lead_dashboard.html','utf8');
 const start=source.indexOf("      document.getElementById('btn-submit-dtr').addEventListener('click', async function ()");
 const handler=source.slice(start,source.indexOf('      var profileBtn',start));
 const browser=await chromium.launch({headless:true,channel:'chrome'});
 try{for(const width of [1366,390]){
  const page=await browser.newPage({viewport:{width,height:850}});
  await page.setContent('<button id="btn-submit-dtr">Submit</button><input class="dtr-checkbox" type="checkbox" checked value="78"><select id="tlDtrSite"><option value="4">Site</option></select><input id="tlDtrCutoffRef">');
  await page.evaluate(handler=>{
   window.dtrReviewState={employeesById:{78:{period_start:'2026-09-26',period_end:'2026-10-10'}}};
   window.dtrSaving=false;window.TL_DTR_SUBMIT_API='/submit';window.alerts=[];window.toasts=[];window.refreshes=0;
   window.AppDialog={alert:(message,title)=>{alerts.push({message,title});return Promise.resolve();}};
   window.OnazulDialogs={confirm:()=>Promise.resolve(true)};window.showToast=(message,error)=>toasts.push({message,error});
   window.loadDtrCutoffReview=()=>refreshes++;window.syncDtrSelectAllState=()=>{document.getElementById('btn-submit-dtr').disabled=false};
   window.response={success:true,submitted:[78]};window.fetch=()=>Promise.resolve({json:()=>Promise.resolve(response)});
   (0,eval)(handler);
  },handler);
  await page.click('#btn-submit-dtr');await page.waitForFunction(()=>alerts.length===1);
  assert.deepEqual(await page.evaluate(()=>alerts[0]),{title:'DTR submitted successfully',message:'1 DTR(s) successfully submitted to Accounting for review.'});
  assert.equal(await page.evaluate(()=>refreshes),1);
  await page.evaluate(()=>{alerts=[];response={success:true,submitted:[]}});await page.click('#btn-submit-dtr');await page.waitForFunction(()=>alerts.length===1);
  assert.equal(await page.evaluate(()=>alerts[0].title),'DTR submission status');
  await page.evaluate(()=>{alerts=[];response={success:false,message:'Submission rejected'}});await page.click('#btn-submit-dtr');await page.waitForFunction(()=>toasts.length===1);
  assert.equal(await page.evaluate(()=>alerts.length),0);assert.equal(await page.evaluate(()=>toasts[0].error),true);
  console.log(width+'px: confirmed success modal, zero-submission message, refresh, and failure without false success PASS');await page.close();
 }}finally{await browser.close();}
})().catch(error=>{console.error(error);process.exit(1)});
