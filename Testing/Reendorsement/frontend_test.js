// DOM/controller checks with mocked transport; no signed-in browser session is fabricated.
const fs=require('fs'),vm=require('vm'),assert=require('assert');
let passed=0;
function check(value,label){assert(value,label);passed++;console.log('PASS '+label);}
class Element {
  constructor(){this.listeners={};this.nodes={};this.html='';this.value='';this.classes=new Set();this.children=[];this.validity='';}
  set innerHTML(value){this.html=value;this.nodes={};}
  get innerHTML(){return this.html;}
  appendChild(node){this.children.push(node);if(node.id)elements[node.id]=node;}
  querySelector(selector){
    if(selector==='form'&&!this.html.includes('<form'))return null;
    if(selector==='button'&&!this.html.includes('<button'))return null;
    if(!this.nodes[selector]){this.nodes[selector]=new Element();this.nodes[selector].html=this.html;}return this.nodes[selector];
  }
  addEventListener(name,fn){this.listeners[name]=fn;}
  get classList(){return {add:name=>this.classes.add(name),remove:name=>this.classes.delete(name)};}
  reportValidity(){return !this.validity;}
  setCustomValidity(message){this.validity=message;}
  focus(){}
  remove(){this.removed=true;}
}
let elements={},queue=[],requests=[],refreshes=0;
const body=new Element();
const document={body,createElement:()=>new Element(),getElementById:id=>elements[id]||null};
const bootstrap={Modal:{getOrCreateInstance:element=>({show(){element.listeners['shown.bs.modal']?.();},hide(){element.listeners['hidden.bs.modal']?.();},dispose(){}}),getInstance:element=>({hide(){element.hidden=true;element.listeners['hidden.bs.modal']?.();}})}};
const window={dispatchEvent(){},AdminSidebarBadges:{poll(){}}};
const context={window,document,bootstrap,CustomEvent:class {},fetch:async(url,options)=>{requests.push({url,options});const item=queue.shift();if(item instanceof Error)throw item;return {ok:item?.success!==false,json:async()=>item};}};
vm.createContext(context);
vm.runInContext(fs.readFileSync('Js/Shared/applicant_reendorsement.js','utf8'),context);
vm.runInContext(fs.readFileSync('Js/Shared/client_review_reason.js','utf8'),context);
const history=[{AttemptNumber:1,CompanyName:'Company <A>',PositionTitle:'Guard',Decision:'Rejected',DecisionReason:'Internal <reason>',EndorsedAt:'2026-10-01',DecidedAt:'2026-10-02',ReviewerName:'Lead A'}];
const data={success:true,application_id:9,attempt_id:71,can_reendorse:true,applicant_name:'Test <Candidate>',endorsement_history:history};
const options=[{job_order_id:22,target_detail_id:32,company_name:'Company B',position:'Guard',location:'Site B',open_slots:2,hierarchy:{category_name:'Security'},work_schedule:'Day'}];
async function tick(){await Promise.resolve();await Promise.resolve();await new Promise(r=>setImmediate(r));}
(async()=>{
 const ui=window.ApplicantReendorsement;
 check(ui.historyHtml(history).includes('Company &lt;A&gt;'),'History escapes company name');
 check(ui.historyHtml(history).includes('Internal &lt;reason&gt;'),'Internal history escapes reason');
 check(ui.historyHtml([]).includes('No client endorsements'),'Empty history state');
 const panel=new Element();ui.attach(panel,data,()=>refreshes++);
 check(panel.children[0].html.includes('Find Another Company'),'Rejected application has reassignment action');
 const readonly=new Element();ui.attach(readonly,{endorsement_history:history,can_reendorse:false});check(!readonly.children[0].html.includes('<button'),'Approved/final application has history without reassign action');
 queue=[data,{success:true,opportunities:options}];const loading=ui.open(9,()=>refreshes++);
 check(elements.reendorsementModal.querySelector('.modal-body').html.includes('Loading'),'Loading state');await loading;
 const modal=elements.reendorsementModal,content=modal.querySelector('.modal-body');
 check(content.html.includes('Company B')&&content.html.includes('2 open slots'),'Real opportunity rendered from API response');
 check(content.html.includes('Test &lt;Candidate&gt;'),'Applicant name escaped');
 const form=content.querySelector('form'),select=form.querySelector('select');select.value='0';select.listeners.change();
 check(form.querySelector('#reendorsementDetails').textContent==='Security · Day','Hierarchy and schedule selection detail');
 form.querySelector('textarea').value='   ';await form.listeners.submit({preventDefault(){}});
 check(form.querySelector('[role="alert"]').textContent.includes('reason'),'Whitespace reason rejected');
 form.querySelector('textarea').value='Compatible alternate';queue=[{success:false,message:'Opportunity changed'}];await form.listeners.submit({preventDefault(){}});
 check(form.querySelector('[role="alert"]').textContent==='Opportunity changed','Server validation error visible');
 check(!form.querySelector('button').disabled,'Retry enabled after save error');
 queue=[{success:true}];await form.listeners.submit({preventDefault(){}});
 const payload=JSON.parse(requests.at(-1).options.body);check(payload.attempt_id===71&&payload.job_order_id===22&&payload.target_detail_id===32,'Submit carries current attempt and exact placement');
 check(refreshes===1&&modal.hidden,'Successful submit closes and refreshes existing review');
 queue=[data,{success:true,opportunities:[]}];await ui.open(9);check(modal.querySelector('.modal-body').html.includes('No eligible alternatives'),'Empty opportunity state');
 queue=[new Error('Network unavailable'),{success:true,opportunities:[]}];await ui.open(9);check(modal.querySelector('.modal-body').html.includes('Network unavailable')&&modal.querySelector('.modal-body').html.includes('Retry'),'Load error and retry state');
 let reasonPromise=window.ClientReviewReason('Rejected');let reasonElement=body.children.at(-1);check(reasonElement.html.includes('required')&&reasonElement.html.includes('Confirm Rejection'),'Rejection reason modal requires input');
 reasonElement.querySelector('textarea').value='  ';reasonElement.querySelector('form').listeners.submit({preventDefault(){}});check(reasonElement.querySelector('textarea').validity!=='','Whitespace rejection prevented');
 reasonElement.querySelector('textarea').value='Requirements not met';reasonElement.querySelector('textarea').listeners.input({target:reasonElement.querySelector('textarea')});reasonElement.querySelector('form').listeners.submit({preventDefault(){}});check(await reasonPromise==='Requirements not met','Professional reason returned');
 reasonPromise=window.ClientReviewReason('Rejected');reasonElement=body.children.at(-1);reasonElement.listeners['hidden.bs.modal']();check(await reasonPromise===null,'Cancel returns null so no decision submitted');
 reasonPromise=window.ClientReviewReason('Approved');reasonElement=body.children.at(-1);reasonElement.querySelector('form').listeners.submit({preventDefault(){}});check(await reasonPromise==='','Approval supports optional remarks');
 console.log('TOTAL '+passed+' PASS (mock DOM/transport; browser visual NOT VERIFIED)');
})().catch(error=>{console.error(error);process.exitCode=1;});
