const {chromium}=require('C:/Users/Irishdwin Gamutan/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const assert=require('node:assert/strict');const fs=require('node:fs');
(async()=>{
 const browser=await chromium.launch({headless:true,channel:'chrome'});const checks=[];
 try {for(const width of [1535,390]){
  const page=await browser.newPage({viewport:{width,height:820}});const writes=[],errors=[];let industry='Retial',failSave=false;
  await page.route('https://cdn.jsdelivr.net/npm/sweetalert2@11',r=>r.fulfill({contentType:'application/javascript',body:fs.readFileSync('Testing/ClientCompanies/sweetalert2.test.js','utf8')}));
  await page.route('https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js',r=>r.fulfill({contentType:'application/javascript',body:fs.readFileSync('Testing/ClientCompanies/bootstrap.test.js','utf8')}));
  await page.route('https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',r=>r.fulfill({contentType:'text/css',body:fs.readFileSync('Testing/ClientCompanies/bootstrap.test.css','utf8')}));
  page.on('pageerror',e=>errors.push(e.message));
  page.on('console',m=>{if(m.type()==='error')console.log('Browser console:',m.text());});
  page.on('requestfailed',r=>console.log('Failed asset:',r.url(),r.failure()));
  await page.route('**/Api/**',async route=>{
   const url=new URL(route.request().url()),req=route.request();
   let data={success:true,role:'Admin',remaining_seconds:1200,user:{role:'Admin',full_name:'Test Admin'},users:[],companies:[],agreements:[],applications:[],deployments:[],logs:[],notifications:[],unread_count:0,categories:[],holidays:[],brackets:[],requests:[],job_orders:[],industries:[],pagination:{page:1,pages:1,total:0,page_size:10,filters:{}},stats:{},rates:{}};
   if(url.pathname.endsWith('industries.php')){
    data.industries=[{id:1,name:industry,edit_token:'signed-selection'}];
    if(req.method()==='POST'){writes.push({url:url.pathname,body:req.postData()});industry='Retail';data.message='Industry updated.';}
   }
   if(url.searchParams.get('list')==='context')data.csrf_token='fixture-csrf';
   if(url.searchParams.has('check_email'))data.exists=url.searchParams.get('check_email')==='used@gmail.com';
   if(url.searchParams.get('list')==='team_leads')data.team_leads=[{UsersID:2,FullName:'First Lead',Email:'first@gmail.com'},{UsersID:3,FullName:'Second Lead',Email:'second@gmail.com'}];
   if(url.searchParams.get('list')==='sites')data.sites=[{SiteID:1,Name:'Branch One',Address:'Test address',TeamLeadID:2,TeamLeadName:'First Lead',EmployeeCount:3,IsActive:1}];
   if(url.pathname.endsWith('client_companies.php')&&req.method()==='POST'){writes.push({url:url.pathname,body:req.postData()});if(failSave)data={success:false,message:'Unable to save deployment site.'};}
   await route.fulfill({status:data.success?200:422,contentType:'application/json',body:JSON.stringify(data)});
  });
  await page.goto('http://localhost/Employee_Tracking/Html/Admin/admin_dashboard.html#section-clients');
  try{await page.waitForFunction(()=>typeof window.manageDeploymentSites==='function'&&typeof Swal!=='undefined');}catch(e){console.log('Load diagnostics',errors,await page.evaluate(()=>({url:location.href,manage:typeof window.manageDeploymentSites,swal:typeof window.Swal})));throw e;}
  await page.evaluate(()=>openAddClientModal());const contact=page.locator('#addContactNumber');
  for(const value of ['+63','+638123456789','+63912345678','+63912letters','+63+639123456789','+6391234567890']){await contact.fill(value);assert.equal(await contact.evaluate(el=>el.classList.contains('is-invalid')&&!el.checkValidity()),true);checks.push(`${width}: immediate invalid contact ${value}`);}
  await contact.fill('+639123456789');assert.equal(await contact.evaluate(el=>el.classList.contains('is-valid')&&el.checkValidity()),true);checks.push(`${width}: valid canonical contact`);
  assert.equal(await page.locator('#formAddClient [name="site_name"]').count(),0);assert.equal(await page.locator('#formAddClient').getByText('Add deployment site',{exact:true}).count(),0);checks.push(`${width}: registration has no site control`);
  await page.locator('#industrySelect').selectOption('Retial');await page.locator('#btnEditSelectedIndustry').click();await page.locator('#industryNameInput').fill('Retail');await page.locator('#btnSaveIndustry').click();await page.locator('.swal2-title').filter({hasText:'Saved'}).waitFor();
  assert.ok(writes.some(w=>w.url.endsWith('industries.php')&&w.body.includes('signed-selection')&&w.body.includes('update')));checks.push(`${width}: selected industry edit`);
  await page.locator('.swal2-confirm').click();await page.evaluate(()=>bootstrap.Modal.getInstance(document.getElementById('modalAddClient')).hide());await page.waitForTimeout(350);
  await page.evaluate(()=>manageDeploymentSites(1));await page.locator('[data-site-edit="1"]').click();await page.locator('#siteEditor [name="team_lead_id"]').selectOption('3');
  const before=writes.length;await page.getByRole('button',{name:'Save site',exact:true}).click();await page.getByRole('heading',{name:'Confirm Changes',exact:true}).waitFor();assert.equal(writes.length,before);assert.ok((await page.locator('.swal2-html-container').innerText()).includes('Second Lead'));checks.push(`${width}: no save before confirmation`);
  await page.locator('.swal2-cancel').click();await page.locator('#siteEditor').waitFor();assert.equal(writes.length,before);checks.push(`${width}: cancel saves nothing`);
  await page.getByRole('button',{name:'Save site',exact:true}).click();await page.getByRole('button',{name:'Confirm Changes',exact:true}).click();await page.getByRole('heading',{name:'Success',exact:true}).waitFor();assert.equal(writes.length,before+1);checks.push(`${width}: confirm saves once then success`);
  await page.locator('.swal2-confirm').click();await page.locator('[data-site-edit="1"]').click();await page.locator('#siteEditor [name="lead_mode"]').selectOption('new');
  await page.locator('#siteTlEmail').fill('bad@example.com');assert.equal(await page.locator('#siteTlEmail').evaluate(el=>el.classList.contains('is-invalid')&&!el.checkValidity()),true);checks.push(`${width}: Gmail validation reused`);
  await page.locator('#siteTlPassword').fill('Admin@123');assert.equal(await page.locator('#siteTlPassword').evaluate(el=>el.classList.contains('is-invalid')&&!el.checkValidity()),true);assert.equal(await page.locator('#siteTlPasswordChecklist li.text-success').count(),5);checks.push(`${width}: predictable password rejected despite format checklist`);
  await page.locator('#siteTlPassword').fill('Birch!58Lemon');await page.locator('#siteTlPasswordConfirm').fill('Mismatch');assert.equal(await page.locator('#siteTlPasswordConfirmHint').innerText(),'Passwords do not match.');
  const beforeNew=writes.length;await page.getByRole('button',{name:'Save site',exact:true}).click();assert.equal(writes.length,beforeNew);assert.equal(await page.locator('#siteEditor').isVisible(),true);checks.push(`${width}: invalid new account does not submit`);
  await page.locator('#siteTlFullName').fill('New Lead');await page.locator('#siteTlEmail').fill('newlead@gmail.com');await page.locator('#siteTlPasswordConfirm').fill('Birch!58Lemon');await page.waitForTimeout(400);assert.equal(await page.locator('#siteTlPasswordConfirmHint').innerText(),'Passwords match.');
  assert.equal(await page.locator('#siteNewLead .password-strength-meter').count(),1);
  await page.locator('[data-target="siteTlPassword"]').click();assert.equal(await page.locator('#siteTlPassword').getAttribute('type'),'text');await page.locator('[data-target="siteTlPassword"]').click();checks.push(`${width}: show/hide reused`);
  assert.equal(await page.locator('#siteTlPassword').evaluate(el=>getComputedStyle(el).cursor),'text');assert.notEqual(await page.locator('#siteTlPassword').evaluate(el=>getComputedStyle(el).caretColor),'rgba(0, 0, 0, 0)');
  assert.equal(await page.locator('.swal2-title').evaluate(el=>getComputedStyle(el).cursor),'default');assert.equal(await page.locator('.swal2-confirm').evaluate(el=>getComputedStyle(el).cursor),'pointer');checks.push(`${width}: static/input/button cursors preserved`);
  await page.screenshot({path:`Testing/ClientCompanies/site-new-lead-${width}.png`});
  await page.getByRole('button',{name:'Save site',exact:true}).click();await page.getByRole('button',{name:'Confirm Changes',exact:true}).click();await page.getByRole('heading',{name:'Success',exact:true}).waitFor();assert.equal(writes.length,beforeNew+1);checks.push(`${width}: valid new account save success`);
  await page.locator('.swal2-confirm').click();await page.locator('[data-site-edit="1"]').click();failSave=true;await page.getByRole('button',{name:'Save site',exact:true}).click();await page.getByRole('button',{name:'Confirm Changes',exact:true}).click();await page.getByRole('heading',{name:'Unable to save changes',exact:true}).waitFor();assert.equal(await page.getByRole('heading',{name:'Success',exact:true}).count(),0);checks.push(`${width}: failure shows error only`);
  assert.deepEqual(errors,[]);await page.close();
 }}finally{await browser.close();}
 fs.writeFileSync('Testing/ClientCompanies/ui_results.json',JSON.stringify({passed:checks.length,tests:checks,scope:'Actual dashboard/components with mocked API replies; writes independently tested over HTTP'},null,2));console.log(JSON.stringify({passed:checks.length,tests:checks},null,2));
})().catch(e=>{console.error(e);process.exit(1)});
