(function(){
 'use strict';
 const root=document.getElementById('employeeExit');if(!root||root.dataset.exitBound==='1')return;root.dataset.exitBound='1';
 const api=root.dataset.api;let csrf='',role='',employees=[],busy=false,loading=false,search='',statusFilter='',currentData=null;
 const dashboard=root.dataset.dashboard==='true';
 const visible=()=>!root.closest('.dashboard-section')?.classList.contains('d-none');
 const hr=()=>['HR','Admin'].includes(role),accounting=()=>['Accounting','Admin'].includes(role),lead=()=>['TeamLead','Team Lead'].includes(role);
 const today=()=>new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Manila',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date());
 const el=(tag,text,cls)=>{const n=document.createElement(tag);if(text!==undefined)n.textContent=text;if(cls)n.className=cls;return n;};
 const button=(text,handler)=>{const b=el('button',text,'btn btn-outline-primary btn-sm');b.type='button';b.onclick=handler;return b;};
 async function request(query='',payload=null){const options=payload?{method:'POST',headers:{'X-Exit-CSRF':csrf},body:payload}:{};const response=await fetch(api+query,options);const data=await response.json();if(!data.success)throw new Error(data.message||'Unable to load exit workflow.');return data;}
 async function load(){if(busy||loading)return;loading=true;try{const data=await request();csrf=data.csrf_token;role=data.role;if(role==='Admin'){const back=document.querySelector('[data-exit-page-header] a');if(back)back.href='../Admin/admin_dashboard.html';}render(data);}catch(error){root.replaceChildren(el('p',error.message,'exit-error'));}finally{loading=false;}}
 function meta(parent,label,value){const block=el('div');block.append(el('small',label),el('strong',value===null||value===undefined||value===''?'—':String(value)));parent.append(block);}
 function render(data){currentData=data;root.replaceChildren();const toolbar=el('div',undefined,'exit-toolbar');toolbar.append(button('Refresh',load));
 if(hr()){toolbar.append(button('Register physical resignation letter',()=>openForm('register',null)));toolbar.append(button('Review attendance for AWOL',()=>openForm('scan_awol',null)));}root.append(toolbar);
 if(role==='Employee')root.append(el('p','Personally submit your resignation letter to HR and return the signed physical clearance. This page tracks progress; it does not submit an online resignation.','exit-readonly'));
 root.append(el('p','Final pay tracking starts when HR accepts the returned clearance. A target date does not release payment or deactivate an account.','text-muted small mt-3'));
 let shown=data.resignations;
 if(dashboard){
  const filters=el('div',undefined,'exit-filters mb-3');
  const searchWrap=el('div');const searchLabel=el('label','Search employee or deployment site','form-label small');searchLabel.htmlFor='exit-record-search';
  const searchInput=el('input',undefined,'form-control');searchInput.type='search';searchInput.id='exit-record-search';searchInput.placeholder='Employee, company or deployment site…';searchInput.value=search;searchWrap.append(searchLabel,searchInput);
  const statusWrap=el('div');const statusLabel=el('label','Workflow status','form-label small');statusLabel.htmlFor='exit-record-status';
  const status=el('select',undefined,'form-select');status.id='exit-record-status';const all=el('option','All statuses');all.value='';status.append(all);
  [...new Set(data.resignations.map(r=>r.workflow.stage))].sort().forEach(v=>{const o=el('option',v);o.value=v;status.append(o);});status.value=statusFilter;statusWrap.append(statusLabel,status);filters.append(searchWrap,statusWrap);root.append(filters);
  function applyFilters(){search=searchInput.value;statusFilter=status.value;render(currentData);const input=document.getElementById('exit-record-search');if(document.activeElement===document.body){input.focus();input.setSelectionRange(search.length,search.length);}}
  searchInput.oninput=applyFilters;status.onchange=()=>{statusFilter=status.value;render(currentData);document.getElementById('exit-record-status').focus();};
  shown=data.resignations.filter(r=>(!statusFilter||r.workflow.stage===statusFilter)&&[r.EmployeeName,r.EmployeeNumber,r.workflow.company_name,r.workflow.site_name].join(' ').toLowerCase().includes(search.trim().toLowerCase()));
 }
 if(!shown.length){const empty=el('div',undefined,dashboard?'exit-empty card border-0 shadow-sm p-4 text-center':'text-muted mt-3');empty.append(el('p',search||statusFilter?'No resignation requests match your filters.':'No resignation requests found.',dashboard?'fw-semibold mb-1':''));if(dashboard)empty.append(el('p','Register a physical resignation letter to begin processing, or review attendance for AWOL.','text-muted small mb-0'));root.append(empty);}
 shown.forEach(row=>renderRequest(row));
 if(data.awol_cases?.length){root.append(el('h2','AWOL review','h5 mt-4'));data.awol_cases.forEach(c=>{const card=el('article',undefined,'exit-card');card.append(el('h3',c.EmployeeName+' · '+c.State,'h6'),el('p','Reviewed absence dates: '+(JSON.parse(c.Evidence).dates||[]).join(', ')),el('p',c.Remarks||'HR must review explanations before confirming AWOL.','text-muted'));
 card.append(historyView(c.history));if(hr()){const actions=el('div',undefined,'exit-actions');if(c.State==='Pending Review'){actions.append(button('Confirm AWOL',()=>openForm('confirm_awol',c)),button('Dismiss / valid reason',()=>openForm('dismiss_awol',c)));}if(['Pending Review','Confirmed'].includes(c.State))actions.append(button('Re-evaluate corrected attendance',()=>openForm('reevaluate_awol',c)));card.append(actions);}root.append(card);});}
 }
 function renderRequest(row){const w=row.workflow,stage=w.stage;const card=el('article',undefined,'exit-card');card.append(el('h2',row.EmployeeName+' · '+(row.EmployeeNumber||'')),el('span',stage,'exit-stage'));
 const info=el('div',undefined,'exit-meta');meta(info,'Letter received',row.SubmissionDate);meta(info,'Requested last working day',row.EffectiveDate);meta(info,'Client company',w.company_name);meta(info,'Deployment site',w.site_name);meta(info,'Assigned Team Lead',w.team_lead_name);meta(info,'HR clearance accepted',w.accepted_date);meta(info,'Final pay target',w.target_date);meta(info,'Working days remaining',row.WorkingDaysRemaining);meta(info,'Payment released',w.released_date);meta(info,'Quitclaim signed',w.quitclaim_signed_date);if(!lead())meta(info,'Recorded final pay',row.FinalPayAmount);card.append(info,el('p',w.calendar_policy||'Agency final-pay calendar awaiting confirmation.','text-muted small'));
 if(w.last_remarks)card.append(el('p',w.last_remarks));
 const actions=el('div',undefined,'exit-actions');
 const add=(name,label)=>actions.append(button(label,()=>openForm(name,row)));
 if(hr()){
 if(stage==='Resignation Pending'){add('approve','Approve HR review');add('return_request','Return for correction');}
 if(stage==='Returned for Correction')add('resubmit','Record corrected letter');
 if(stage==='HR Approved')add('issue_clearance','Record physical clearance issuance');
 if(stage==='Clearance Returned')add('reissue','Reissue corrected clearance');
 if(stage==='Team Lead Cleared')add('verify_clearance','Accept returned physical clearance');
 if(stage==='Final Pay Released'&&w.quitclaim_signed_at)add('finalize','Finalize resignation & deactivate');
 }
 if(lead()&&stage==='Pending Team Lead Clearance'){add('tl_clear','Verify & approve physical clearance');add('tl_return','Return clearance for correction');}
 if(accounting()){if(stage==='Clearance Complete')add('start_pay','Start final-pay review');if(stage==='Final Pay Processing')add('pay_ready','Record reviewed final-pay components');if(stage==='Final Pay Ready')add('release_pay','Record actual payment release');}
 if((hr()||accounting())&&['Clearance Complete','Final Pay Processing','Final Pay Ready','Final Pay Released'].includes(stage)){
 add('set_target','Set final-pay tracking target');if(!w.quitclaim_signed_at){add(w.quitclaim_reference?'quitclaim_sign':'quitclaim_prepare',w.quitclaim_reference?'Confirm employee signed quitclaim':'Record quitclaim preparation');}}
 if(hr()||accounting())actions.append(button('Payroll history',()=>viewPayroll(row)));
 card.append(actions);
 const docs=el('details');docs.append(el('summary','Physical document references and optional scans'),el('p','Letter: '+(w.letter_reference||'No reference recorded')+' · Clearance: '+(w.clearance_reference||'Not issued')+' · Quitclaim: '+(w.quitclaim_reference||'Not prepared')));
 ['letter','clearance','quitclaim'].forEach(type=>{if(lead()&&type==='quitclaim')return;if(w.documents?.[type]){const a=el('a','View '+type,'btn btn-link btn-sm');a.href='../../Api/Common/view_exit_document.php?id='+row.ResignationID+'&type='+type;a.target='_blank';a.rel='noopener';docs.append(a);}else if(stage!=='Resigned'&&(hr()||(accounting()&&type==='quitclaim')))docs.append(button('Attach '+type+' scan',()=>openUpload(row,type)));});card.append(docs);
 card.append(historyView(row.history));root.append(card);
 }
 function historyView(events){const history=el('details');history.append(el('summary','Approval and verification history'));const list=el('ol',undefined,'exit-steps');(events||[]).forEach(h=>{const details=JSON.parse(h.Details||'{}');list.append(el('li',h.CreatedAt+' · '+h.Actor+' · '+h.Action.replaceAll('_',' ')+(details.remarks?' · '+details.remarks:'')));});history.append(list);return history;}

 function field(form,name,label,type='text',required=true,value=''){const wrap=el('div',undefined,'exit-field');const id='exit-'+name;const lab=el('label',label);lab.htmlFor=id;const input=el(type==='textarea'?'textarea':'input',undefined,'form-control');input.id=id;input.name=name;if(type!=='textarea')input.type=type;input.required=required;input.value=value;wrap.append(lab,input);form.append(wrap);return input;}
 function check(form,name,label){const wrap=el('label',undefined,'d-flex gap-2 mb-3');const input=el('input');input.type='checkbox';input.name=name;input.value='1';input.required=true;wrap.append(input,el('span',label));form.append(wrap);}
 async function selectEmployee(form){if(!employees.length){const d=await request('?action=employees');employees=d.employees;}const wrap=el('div',undefined,'exit-field');const lab=el('label','Employee');lab.htmlFor='exit-employee_id';const s=el('select',undefined,'form-select');s.name='employee_id';s.id='exit-employee_id';s.required=true;const placeholder=el('option','Select employee');placeholder.value='';s.append(placeholder);employees.forEach(e=>{const o=el('option',e.EmployeeName+' · '+(e.EmployeeNumber||''));o.value=e.EmployeeID;s.append(o);});wrap.append(lab,s);form.append(wrap);}
 async function openForm(action,row){const dialog=el('dialog',undefined,'exit-modal');const title=el('h2',action.replaceAll('_',' '),'h5 text-capitalize');const form=el('form');dialog.append(title,form);document.body.append(dialog);
 const hidden=(name,value)=>{const i=el('input');i.type='hidden';i.name=name;i.value=value;form.append(i);};hidden('action',action);if(row?.ResignationID)hidden('resignation_id',row.ResignationID);if(row?.CaseID)hidden('case_id',row.CaseID);
 let components;
 if(['register','scan_awol'].includes(action)){try{await selectEmployee(form);}catch(e){dialog.remove();await AppDialog.alert(e.message);return;}}
 if(action==='register'){field(form,'received_date','Physical letter received on','date',true,today());field(form,'last_working_date','Requested last working day (optional)','date',false);field(form,'letter_reference','Letter reference (optional)','text',false);field(form,'reason','Resignation reason (optional)','textarea',false);}
 if(['issue_clearance','reissue'].includes(action))field(form,'clearance_reference','Physical clearance reference');
 if(action==='verify_clearance'){field(form,'accepted_date','Returned clearance accepted on','date',true,today());check(form,'physical_received','I received and verified the signed physical clearance approved by the Team Lead.');}
 if(action==='set_target'){
 form.append(el('p','Leave weekdays blank to record a manual target while the agency calendar is unconfirmed. HR can instead confirm weekday names (Mon,Tue,…); the calculation counts 30 days after acceptance and excludes nationwide regular and special non-working holidays.','text-muted small'));
 field(form,'target_date','Manual target date','date',false);if(hr()){field(form,'working_days','Confirmed agency working weekdays (optional)','text',false);const c=field(form,'calendar_confirmed','Confirm calendar rule','checkbox',false);c.value='1';}}
 if(action==='pay_ready'){
 form.append(el('p','Record only reviewed unpaid wages, adjustments, benefits and deductions supported by payroll records and confirmed agency rules. No separation benefit or proration is assumed.','text-muted small'));
 components=el('div');form.append(components);const addComponent=()=>{const r=el('div',undefined,'exit-component');const type=el('select',undefined,'form-select');type.dataset.key='type';['credit','deduction'].forEach(v=>{const o=el('option',v);o.value=v;type.append(o);});r.append(type);[['description','Component description'],['amount','Amount (PHP)'],['reference','Policy / payroll reference'],['payroll_id','Payroll record ID (optional)']].forEach(([key,label])=>{const i=el('input',undefined,'form-control');i.dataset.key=key;i.placeholder=label;i.setAttribute('aria-label',label);i.required=key!=='payroll_id';if(key==='amount'){i.type='number';i.min='0';i.step='0.01';}r.append(i);});r.append(button('Remove component',()=>r.remove()));components.append(r);};addComponent();form.append(button('Add component',addComponent));check(form,'components_confirmed','I reviewed the payroll history, payment status and agency policy supporting these amounts.');}
 if(action==='release_pay'){field(form,'released_date','Actual payment release date','date',true,today());field(form,'payment_reference','Payment receipt / transaction reference');check(form,'payment_confirmed','The recorded amount was actually released to the employee.');}
 if(action==='quitclaim_prepare')field(form,'quitclaim_reference','Physical quitclaim reference');
 if(action==='quitclaim_sign'){field(form,'signed_date','Employee signature date','date',true,today());check(form,'signature_confirmed','I verified the actual employee acknowledgement/signature on the physical quitclaim.');}
 if(action==='finalize'){form.append(el('p','Finalization records Resigned status, ends deployment access and disables the employee account. Historical attendance, DTR and payroll are retained.','exit-readonly'));field(form,'last_working_date','Confirmed last working day','date',true,row.EffectiveDate||today());}
 if(action==='confirm_awol')check(form,'explanations_reviewed','I reviewed attendance evidence and employee explanations under the existing HR process.');
 field(form,'remarks','Review remarks','textarea',!['register','scan_awol','start_pay'].includes(action));
 const error=el('p','', 'exit-error');const controls=el('div',undefined,'exit-actions');const cancel=button('Cancel',()=>dialog.close());const save=el('button','Save','btn btn-primary');save.type='submit';controls.append(cancel,save);form.append(error,controls);
 form.onsubmit=async event=>{event.preventDefault();if(busy)return;if(!form.reportValidity())return;save.disabled=true;const data=new FormData(form);if(components){const values=[...components.children].map(r=>Object.fromEntries([...r.querySelectorAll('[data-key]')].map(i=>[i.dataset.key,i.value])));data.set('components',JSON.stringify(values));}
 try{if(!await AppDialog.confirm('Record this '+action.replaceAll('_',' ')+' action?','Confirm workflow action'))return;busy=true;const result=await request('',data);dialog.close();await AppDialog.alert(action==='scan_awol'&&!result.id?'No reviewed three-day absence episode found. Missing or unverified attendance is not counted.':result.message,'Saved successfully');}
 catch(e){error.textContent=e.message;}finally{busy=false;save.disabled=false;load();}};
 dialog.addEventListener('close',()=>dialog.remove(),{once:true});dialog.showModal();
 }
 function openUpload(row,type){const file=el('input');file.type='file';file.accept='.pdf,.jpg,.jpeg,.png';file.onchange=async()=>{if(!file.files[0]||busy)return;const fd=new FormData();fd.set('action','attach_document');fd.set('resignation_id',row.ResignationID);fd.set('document_type',type);fd.set('document',file.files[0]);try{busy=true;await request('',fd);await AppDialog.alert('Scan stored securely. A scan does not record an employee signature or approval.','Document attached');}catch(e){await AppDialog.alert(e.message);}finally{busy=false;load();}};file.click();}
 async function viewPayroll(row){try{const data=await request('?id='+row.ResignationID);const d=el('dialog',undefined,'exit-modal');d.append(el('h2','Recorded payroll history','h5'),el('p','Verify payment status and approved adjustments in Accounting before including any unpaid amounts. These records are not automatically added to final pay.','text-muted small'));const t=el('table',undefined,'exit-table');const head=el('tr');['ID','Period','Recorded status','Gross','Deductions','Net'].forEach(l=>head.append(el('th',l)));t.append(head);data.resignations[0].payroll_history.forEach(p=>{const tr=el('tr');[p.Payroll_ID,p.PayPeriodStart+' – '+p.PayPeriodEnd,p.RecordedStatus,p.GrossPay,p.TotalDeductions,p.NetPay].forEach((v,i)=>{const td=el('td',String(v??'—'));td.dataset.label=['ID','Period','Status','Gross','Deductions','Net'][i];tr.append(td);});t.append(tr);});d.append(t);const references=data.resignations[0];d.append(el('h3','Approved adjustments','h6 mt-3'));(references.approved_adjustments||[]).forEach(a=>d.append(el('p','#'+a.AdjustmentID+' · Payroll #'+a.Payroll_ID+' · '+a.AdjustmentType+' · '+(a.IsAddition?'Credit':'Deduction')+' PHP '+a.Amount+' · '+(a.Reason||''))));d.append(el('h3','Recorded payroll payment status history','h6 mt-3'));(references.payment_status_history||[]).forEach(h=>d.append(el('p','Run #'+h.PayrollRunID+' · '+h.Status+' · '+h.UpdatedAt+' · '+(h.Remarks||''))));d.append(button('Close',()=>d.close()));document.body.append(d);d.addEventListener('close',()=>d.remove(),{once:true});d.showModal();}catch(e){AppDialog.alert(e.message);}}
 if(new URLSearchParams(location.search).has('embedded'))document.querySelector('[data-exit-page-header]')?.remove();
 window.EmployeeExit={load};
 if(!dashboard)load();
 setInterval(()=>{if(visible()&&document.visibilityState==='visible'&&!document.querySelector('dialog[open]'))load();},15000);
})();
