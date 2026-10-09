const fs=require('fs'),assert=require('assert/strict');
const {chromium}=require('C:/Users/Irishdwin Gamutan/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
(async()=>{
const source=fs.readFileSync('Html/TeamLead/team_lead_dashboard.html','utf8');
for(const m of source.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/g))if(m[1].trim())new Function(m[1]);
const load=source.slice(source.indexOf('      var dtrRequestSequence='),source.indexOf('      function openDtrDetailModal'));
const sync=source.slice(source.indexOf('      function syncDtrSelectAllState()'),source.indexOf('      var dtrRequestSequence='));
const browser=await chromium.launch({headless:true,channel:'chrome'});const results=[];
try{for(const width of [1366,390]){const page=await browser.newPage({viewport:{width,height:900}});
await page.setContent('<select id="tlDtrSite"><option value="4">SM Uptown</option><option value="8">Other site</option></select><input id="tlDtrCutoffRef"><span id="dtr-cutoff-label"></span><div id="dtr-due-warning" class="hidden"></div><input type="checkbox" id="dtrSelectAll"><button id="btn-submit-dtr">Submit</button><table><tbody id="dtrTableBody"></tbody></table>');
// Use the dashboard functions unchanged, with controlled asynchronous API responses.
await page.evaluate(({load,sync})=>{
window.escapeHtml=v=>String(v).replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;');window.TL_DTR_REVIEW_API='/review';window.dtrReviewState={};window.TeamLeadSites={prepare:async()=>{}};window.requests=[];window.fetch=url=>new Promise(resolve=>requests.push({url,resolve}));
window.document.getElementById('dtrSelectAll').id='dtr-select-all';
(0,eval)(sync+load);
}, {load,sync});
await page.evaluate(()=>loadDtrCutoffReview());await page.waitForFunction(()=>requests.length===1);
assert.match(await page.locator('#dtrTableBody').textContent(),/Loading/);
await page.selectOption('#tlDtrSite','8');await page.evaluate(()=>loadDtrCutoffReview());await page.waitForFunction(()=>requests.length===2);
await page.evaluate(()=>requests[1].resolve({json:async()=>({success:true,period:{label:'New cutoff'},submission_warning:'Please submit employee DTRs as soon as possible so Accounting can process payroll. Late submission is still allowed.',employees:[{employee_id:2,employee_name:'Review employee',requires_review:true,review_message:'Verify preceding duty',is_submitted:false},{employee_id:3,employee_name:'Locked',is_submitted:true,total_regular_hours:80},{employee_id:4,employee_name:'Eligible',is_submitted:false,total_regular_hours:8}]})}));
await page.waitForFunction(()=>document.querySelectorAll('.dtr-checkbox').length===2);assert.match(await page.locator('#dtrTableBody').textContent(),/Accounting review required/);assert.match(await page.locator('#dtr-cutoff-label').textContent(),/New cutoff/);
assert.equal(await page.locator('.dtr-checkbox[value="4"]').inputValue(),'4');assert.match(await page.locator('#dtr-due-warning').textContent(),/as soon as possible/);assert.equal(await page.locator('#dtr-due-warning').evaluate(e=>e.classList.contains('hidden')),false);
await page.evaluate(()=>requests[0].resolve({json:async()=>({success:true,period:{label:'STALE'},employees:[]})}));await page.waitForTimeout(30);assert.match(await page.locator('#dtrTableBody').textContent(),/Eligible/);
await page.evaluate(()=>loadDtrCutoffReview());await page.waitForFunction(()=>requests.length===3);assert.equal(await page.locator('#btn-submit-dtr').isDisabled(),true);assert.equal(await page.locator('#dtr-select-all').isDisabled(),true);
await page.evaluate(()=>requests[2].resolve({json:async()=>({success:true,period:{label:'Empty cutoff'},employees:[]})}));await page.waitForFunction(()=>document.querySelector('#dtrTableBody').textContent.includes('No employees'));
assert.match(await page.locator('#dtr-cutoff-label').textContent(),/Empty cutoff/);
results.push(width+'px: original dashboard functions: loading, stale response, review-blocked/locked rows, eligible selection, empty cutoff PASS');await page.close();}
fs.writeFileSync('Testing/Attendance/overdue_dtr_ui_results.json',JSON.stringify({passed:results.length,results},null,2));console.log(results.join('\n'));
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exit(1)});
