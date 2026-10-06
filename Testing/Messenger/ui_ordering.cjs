const {chromium}=require('C:/Users/Irishdwin Gamutan/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const assert=require('node:assert/strict'),http=require('node:http'),fs=require('node:fs'),path=require('node:path');
(async()=>{
 const root=process.cwd();const server=http.createServer((req,res)=>{const file=path.resolve(root,'.'+decodeURIComponent(req.url.split('?')[0]));if(!file.startsWith(root+path.sep))return res.writeHead(403).end();fs.readFile(file,(err,data)=>{if(err)return res.writeHead(404).end();res.setHeader('Content-Type',file.endsWith('.js')?'application/javascript':file.endsWith('.css')?'text/css':'text/html');res.end(data);});});
 await new Promise(r=>server.listen(0,'127.0.0.1',r));const browser=await chromium.launch({channel:'chrome',headless:true});
 try{for(const role of ['HR','Admin']){
  const p=await browser.newPage();let counts={},latest={};const contacts=[{user_id:2,full_name:'Z Admin',role:'Admin'},{user_id:3,full_name:'B Lead',role:'TeamLead'},{user_id:4,full_name:'A Worker',role:'Employee'}].map(c=>({...c,server_authorized:true,is_online:true,company:'TEST'}));
  const payload=()=>({total:Object.values(counts).reduce((a,b)=>a+b,0),by_contact:{...counts},latest_unread:{...latest}});
  await p.route('**/Api/**',r=>{const u=new URL(r.request().url());let d={success:true,user_id:1,role,full_name:'TEST',notifications:[],jobs:[],threads:[],data:[],counts:{}};
   if(u.pathname.includes('messenger_contacts'))d={success:true,viewer:{user_id:1,role},csrf_token:'TEST',roles:['Admin','TeamLead','Employee'],contacts:contacts.filter(c=>(!u.searchParams.get('role')||c.role===u.searchParams.get('role'))&&(!u.searchParams.get('q')||c.full_name.toLowerCase().includes(u.searchParams.get('q').toLowerCase())))};
   if(u.pathname.includes('get_unread_counts'))d={success:true,...payload()};
   if(u.pathname.includes('messenger_thread'))d={success:true,thread_id:Number(u.searchParams.get('user_id'))};
   if(u.pathname.includes('messages_messages')){const id=Number(u.searchParams.get('thread_id'));delete counts[id];delete latest[id];d={success:true,read_confirmed:true,unread:payload(),messages:[{message_id:100+id,sender_user_id:id,message_text:'TEST readable message'}]};}
   return r.fulfill({contentType:'application/json',body:JSON.stringify(d)});
  });
  const url='http://127.0.0.1:'+server.address().port+'/Html/'+(role==='HR'?'HR/hr_dashboard':'Admin/admin_dashboard')+'.html';await p.goto(url);await p.locator('#owMessengerFab').click();await p.waitForFunction(()=>document.querySelectorAll('#owMessengerContacts [data-user-id]').length===3);
  const order=()=>p.locator('#owMessengerContacts [data-user-id]').evaluateAll(es=>es.map(e=>Number(e.dataset.userId)));
  assert.deepEqual(await order(),role==='HR'?[2,4,3]:[4,3,2]);
  counts[4]=1;latest[4]={created_at:'2026-10-07 10:03:00',message_id:1};await p.evaluate(()=>OwMessenger.fetchUnreadCounts());assert.deepEqual(await order(),role==='HR'?[2,4,3]:[4,3,2]);
  counts[3]=2;latest[3]={created_at:'2026-10-07 10:05:00',message_id:2};await p.evaluate(()=>OwMessenger.fetchUnreadCounts());assert.deepEqual(await order(),role==='HR'?[2,3,4]:[3,4,2]);assert.equal(await p.locator('[data-global-message-badge]').textContent(),'3');
  await p.locator('[data-user-id="3"]').click();await p.waitForFunction(()=>OwMessenger.getUnreadData().total===1);assert.deepEqual(await order(),role==='HR'?[2,4,3]:[4,3,2]);assert.equal(await p.locator('#owMessengerChatTitle').textContent(),'B Lead');
  await p.locator('[data-user-id="4"]').click();await p.waitForFunction(()=>OwMessenger.getUnreadData().total===0);assert(await p.locator('[data-global-message-badge]').evaluate(e=>e.classList.contains('d-none')));
  await p.reload();await p.waitForTimeout(300);assert(await p.locator('[data-global-message-badge]').evaluate(e=>e.classList.contains('d-none')));
  counts[3]=1;latest[3]={created_at:'2026-10-07 10:06:00',message_id:3};await p.evaluate(()=>OwMessenger.fetchUnreadCounts());assert.equal(await p.locator('[data-global-message-badge]').textContent(),'1');await p.locator('#owMessengerFab').click();await p.locator('[data-user-id="3"]').click();await p.waitForFunction(()=>OwMessenger.getUnreadData().total===0);assert(await p.locator('[data-global-message-badge]').evaluate(e=>e.classList.contains('d-none')));
  await p.locator('#owMessengerSearch').fill('Worker');await p.waitForFunction(()=>document.querySelectorAll('#owMessengerContacts [data-user-id]').length===1);assert.deepEqual(await order(),[4]);
  console.log('PASS '+role+': unread recency ordering, read removes priority, selected chat retained, zero after reload, new-message badge reappears, search');await p.close();
 }}finally{await browser.close();await new Promise(r=>server.close(r));}
})().catch(e=>{console.error(e);process.exit(1);});
