// Rebuild all editable sources and SVGs from one balanced logical model.
const fs=require('fs'),path=require('path');
const runtime=process.env.CODEX_NODE_MODULES || 'C:/Users/Irishdwin Gamutan/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules';
const {instance}=require(path.join(runtime,'@viz-js/viz'));
const out=__dirname;
const entities={A:'Admin',H:'HR',R:'Applicant',E:'Employee',T:'Team Lead / Site Supervisor',C:'Accounting Staff',G:'Google Identity Service',M:'Email Delivery Service'};
const processes={P1:'1.0 Authenticate and Manage Accounts',P2:'2.0 Manage Recruitment and Hiring',P3:'3.0 Manage Job Orders',P4:'4.0 Manage Employee Records and Separation',P5:'5.0 Manage Clients, Sites and Deployments',P6:'6.0 Manage Team Schedules',P7:'7.0 Record Attendance and Submit DTR',P8:'8.0 Manage Overtime',P9:'9.0 Compute and Finalize Payroll',P10:'10.0 Manage Billing Revisions and Payroll Status',P11:'11.0 Deliver Notifications',P12:'12.0 Exchange Authorized Messages',P13:'13.0 Produce Reports and Audit Views',P14:'14.0 Review Attendance Add Back'};
const stores={D1:'Accounts and Security',D2:'Applicants and Applications',D3:'Employees and Separation',D4:'Clients and Agreements',D5:'Job Orders and Classifications',D6:'Sites and Deployments',D7:'Schedules and Holidays',D8:'Attendance and DTR',D9:'Overtime and Existing Leave',D10:'Payroll, Billing and Status History',D11:'Notifications',D12:'Messenger',D13:'Audit and Configuration',D14:'Add Back Requests and Evidence',D15:'Uploaded Documents and Evidence Files'};
const external=[];const ext=(a,p,input,output)=>{if(input)external.push([a,p,input]);if(output)external.push([p,a,output]);};
// Shared external flows are factored using a repeated entity group, not a new role.
entities.U='Portal Users (Admin, HR, Applicant, Employee, Team Lead, Accounting)';
ext('U','P1','Credentials, Google ID token, account profile and recovery inputs','Session, role access, profile and account recovery results');
ext('A','P1','Account creation and activation changes','Account directory and management results');
ext('P1','G','Google public signing-key request','Public signing keys');
ext('P1','M','Verification and password recovery email payloads','Delivery result');
ext('R','P2','Applicant profile, documents, application and contract files','Job listings, application status and document results');
ext('H','P2','Review, interview, endorsement, contract and hiring inputs','Applicant dossiers and recruitment results');
ext('T','P2','Endorsed applicant approval or rejection','Endorsed applicant dossiers');
ext('T','P3','Multi-position job order request','Job order status and approved staffing requirements');
ext('H','P3','Job order review and maintenance inputs','Job order requests and published job records');
ext('A','P3','Job order review and classification changes','Job order and classification results');
ext('E','P4','Employee profile, documents and resignation submission','Profile, documents and resignation status');
ext('H','P4','Employee changes, clearance decisions and separation completion','Employee records and clearance results');
ext('C','P4','Final-pay computation and quitclaim upload','Clearance-ready cases and final-pay results');
ext('A','P4','Separation completion decision','Separation result');
ext('A','P5','Client, service agreement, site and Team Lead assignments','Client, agreement, site and deployment records');
ext('H','P5','Deployment assignment and end date','Employee, client and site assignment results');
ext('T','P5','Worksite registration and company profile inputs','Assigned sites, geofence and deployed team records');
ext('E','P5',null,'Current worksite and deployment context');
ext('T','P6','Site-scoped fixed or dated schedules','Team schedule and validation results');
ext('E','P6',null,'Work schedule and smart calendar');
ext('E','P7','Clock action, GPS coordinates and attendance evidence','Validated clock result, attendance and timesheet');
ext('T','P7','Site and cutoff DTR submission inputs','Attendance roster, cutoff review and DTR results');
ext('T','P8','Overtime invitation and completion approval','Overtime roster, employee responses and status');
ext('E','P8','Overtime acceptance, rejection and clock evidence','Overtime invitations and session results');
ext('C','P9','DTR verification, payroll computation and billing-reviewed finalization','Verified DTR, computation preview and finalized payroll results');
ext('E','P9','Payroll history or payslip selection','Finalized payroll history and payslip details');
ext('C','P10','Billing export selection, revised workbook, confirmation, status and proof','Billing workbook, revision preview and payroll status timeline');
ext('E','P10',null,'Payroll status and authorized proof view');
ext('U','P11','Notification query and read acknowledgment','Authorized event notifications and unread counts');
ext('U','P12','Contact search, message content and read cursor','Permitted contacts, conversations and recipient messages');
ext('A','P13','Report filters and audit export selection','Dashboard summaries, payroll billing reports and audit exports');
ext('H','P13','HR dashboard filters','Recruitment and workforce summaries');
ext('C','P13','Accounting dashboard filters','DTR and payroll summaries');
ext('E','P14','Attendance add-back claim using recorded evidence','Add-back status and approved compensation');
ext('C','P14','Add-back review decision','Evidence snapshot and server-calculated quote');
ext('A','P14','Add-back review decision','Add-back evidence and review results');
const internal=[];const flow=(a,b,l)=>internal.push([a,b,l]);
function rw(p,d,read,write){flow(d,p,read);if(write)flow(p,d,write);}
rw('P1','D1','Account identity, role and password history','Account and security changes');
rw('P2','D2','Applicant dossiers and application history','Profile, application, review and endorsement records');
rw('P2','D5','Published positions and staffing availability');
flow('P2','P4','Approved applicant, contract and hiring identity');flow('P2','P1','Applicant-to-Employee account elevation');
rw('P3','D5','Job order requests and classifications','Approved orders, position details and classification changes');
rw('P3','D4','Client and agreement eligibility');
rw('P4','D3','Employee, resignation and clearance records','Employee, clearance, final-pay and resignation changes');
rw('P4','D10','Salary, payroll and outstanding advance context');flow('P4','P5','Hired employee or separation deployment change');
rw('P5','D4','Client and agreement records','Client profiles and service agreements');
rw('P5','D6','Sites, geofences and assignments','Site, worksite and deployment changes');rw('P5','D3','Active employee records');
rw('P6','D6','Assigned site, employee deployment and fixed shift','Fixed deployment schedule');rw('P6','D7','Dated schedules and scoped holidays','Team schedule entries');
rw('P7','D6','Current deployment and worksite geofence');rw('P7','D7','Effective schedule and holiday classification');rw('P7','D8','Attendance evidence and cutoff records','Attendance, daily time records and DTR submissions');
rw('P7','D9','Existing approved leave and overtime context');
rw('P8','D6','Authorized site and employee deployment');rw('P8','D9','Overtime invitations and sessions','Responses, actual hours and approval decisions');
rw('P9','D8','Submitted DTR and attendance','DTR review decisions');rw('P9','D3','Employee salary configuration');rw('P9','D7','Effective shifts and holiday rules');rw('P9','D9','Approved overtime and existing leave');rw('P9','D13','Government deduction rate configuration');rw('P9','D10','Payroll runs, billing snapshots and adjustments','Computed runs, finalized payroll and member snapshots');flow('P9','P10','Computed billing rows and finalized run membership');
rw('P10','D4','Payroll type and service agreement billing terms');rw('P10','D10','Export baseline, finalized payroll and status history','Billing exports, confirmed revisions, adjustments and status history');
rw('P11','D11','Authorized notifications','Read acknowledgments and reminder notifications');rw('P12','D12','Permitted threads and message history','Messages, participants and read cursors');rw('P12','D1','Active identities and roles');rw('P12','D6','Recipient company and Team Lead scope');
rw('P13','D13','System audit events and configuration');rw('P13','D10','Payroll and billing summaries');rw('P13','D2','Recruitment summary records');rw('P13','D3','Workforce summary records');rw('P13','D8','DTR submission summaries');
rw('P14','D14','Claim and captured evidence snapshots','Claim, decision and calculated compensation');rw('P14','D8','Recorded attendance evidence');rw('P14','D7','Work-date schedule');rw('P14','D3','Employee pay rates');flow('P14','D10','Approved compensation adjustment for eligible payroll');
for(const p of ['P2','P4','P7','P8','P10'])rw(p,'D15','Authorized document or evidence content','Uploaded document, evidence or proof file');
for(const p of ['P1','P2','P3','P5','P6','P7','P8','P9','P10','P12','P14'])flow(p,'D11','Recorded '+processes[p].replace(/^\S+ /,'').toLowerCase()+' notification');
for(const p of ['P1','P2','P4','P5','P9','P10'])flow(p,'D13','Actor and business audit event');
const q=s=>JSON.stringify(s);const wrap=s=>s.replace(/(.{1,32})(?:\s+|$)/g,'$1\n').trim();
async function render(name,title,nodes,edges){
 const m=['%% Gane & Sarson notation. DOT/SVG is the exact open-ended-store version.','flowchart LR','  classDef entity fill:#ffffff,stroke:#43835b,color:#172b20,stroke-width:1px;','  classDef process fill:#f4faf5,stroke:#43835b,color:#172b20,stroke-width:1px;','  classDef store fill:#ffffff,stroke:#43835b,color:#172b20,stroke-width:1px;','  linkStyle default stroke:#43835b;'];const dot=['digraph DFD {','// Gane & Sarson: rectangular entities, rounded processes, open-right stores.','graph [rankdir=LR, bgcolor="white", nodesep=0.4, ranksep=1.3, pad=0.35, label='+q(title+'\nGane & Sarson notation')+', labelloc=t, fontsize=22, fontname="Arial"];','node [fontname="Arial", fontsize=12, margin="0.16,0.10", color="#43835b", fontcolor="#172b20"];','edge [fontname="Arial", fontsize=10, color="#43835b", fontcolor="#172b20", arrowsize=0.7];'];
 for(const [id,label] of Object.entries(nodes)){const store=id.startsWith('D'),proc=id.startsWith('P')||id==='S';const text=store?id+' - '+label:label;m.push('  '+id+(proc?'("':store?'[["':'["')+text+(proc?'")':store?'"]]':'"]')+':::'+(proc?'process':store?'store':'entity'));dot.push(id+' [label='+q(wrap(text))+', shape=box'+(proc?', style="rounded,filled", fillcolor="#f4faf5"':store?', shape=plain, label=< <TABLE BORDER="0" CELLBORDER="0" CELLSPACING="0" COLOR="#43835b"><TR><TD BORDER="1" SIDES="LTB" CELLPADDING="9">'+text.replace(/&/g,'&amp;')+'</TD></TR></TABLE> >':'')+'];');}
 for(const [a,b,l]of edges){if(!nodes[a]||!nodes[b])throw Error('Missing node '+a+' '+b);m.push('  '+a+' -->|"'+l+'"| '+b);dot.push(a+' -> '+b+' [label='+q(wrap(l))+'];');}
 dot.push('}');fs.writeFileSync(path.join(out,name+'.mmd'),m.join('\n')+'\n');fs.writeFileSync(path.join(out,name+'.dot'),dot.join('\n')+'\n');fs.writeFileSync(path.join(out,name+'.svg'),viz.renderString(dot.join('\n'),{format:'svg',engine:'dot'}));
 return {name,nodes:Object.keys(nodes).length,flows:edges.length};
}
let viz; (async()=>{viz=await instance();const results=[];
// Context uses exactly the Level 1 boundary flows: process endpoints collapse to S.
const collapsed=external.map(([a,b,l])=>[processes[a]?'S':a,processes[b]?'S':b,l]);
const grouped=new Map();for(const[a,b,l]of collapsed){const key=a+'>'+b;if(!grouped.has(key))grouped.set(key,[a,b,[]]);grouped.get(key)[2].push(l);}
const summaries={'A>S':'Account, job order, client/site, separation and add-back decisions; report selections','S>A':'Management results; client/site records; reports and review evidence','H>S':'Recruitment, job order, employee, clearance and deployment inputs; report filters','S>H':'Applicant, job order, employee, clearance and deployment records; HR summaries','R>S':'Applicant profile, applications and document/contract files','S>R':'Job listings, application status and document results','E>S':'Profile, resignation, GPS clock evidence, overtime responses and add-back claims','S>E':'Profile, deployment, schedule, attendance, overtime, payslip, payroll and claim status','T>S':'Endorsement decisions, staffing requests, site profile, schedules, DTR and overtime inputs','S>T':'Applicant dossiers, job order status, assigned sites/teams, schedule, DTR and overtime results','C>S':'Final-pay, DTR review, payroll, billing revision, status/proof and add-back inputs','S>C':'Final-pay, verified DTR, payroll/billing results, status history, claim evidence and summaries','U>S':'Credentials, Google token, account/recovery inputs; notification and message inputs','S>U':'Session and account results; notifications; permitted conversations and contacts','S>G':'Google public signing-key request','G>S':'Public signing keys','S>M':'Verification and recovery email payloads','M>S':'Delivery result'};
const ctx=[...grouped.values()].map(([a,b])=>[a,b,summaries[a+'>'+b]]);
fs.writeFileSync(path.join(out,'context_balance.json'),JSON.stringify([...grouped.values()].map(([a,b,labels])=>({source:a,target:b,contextLabel:summaries[a+'>'+b],level1BoundaryLabels:labels})),null,2));
results.push(await render('DFD_Level_0_Context','ONAZUL HRIS - Context Diagram',{...entities,S:'ONAZUL HRIS'},ctx));
results.push(await render('DFD_Level_1','ONAZUL HRIS - Level 1 (use SVG zoom; detailed sheets accompany overview)',{...entities,...processes,...stores},[...external,...internal]));
// Focus sheets repeat parent process IDs for traceability rather than renumbering the business model.
for(const [name,title,ps] of [['DFD_Recruitment_Detail','Recruitment, Job Orders and Employee Conversion',['P1','P2','P3','P4','P5']],['DFD_Attendance_Detail','Deployment, Schedules, Attendance, Overtime and Add Back',['P5','P6','P7','P8','P14']],['DFD_Payroll_Detail','DTR Verification, Payroll Finalization, Billing Revisions and Release Status',['P9','P10']]]){
 const edges=[...external,...internal].filter(([a,b])=>ps.includes(a)||ps.includes(b));const ids=new Set(edges.flatMap(e=>e.slice(0,2)));const all={...entities,...processes,...stores};const nodes=Object.fromEntries([...ids].map(id=>[id,all[id]]));results.push(await render(name,title,nodes,edges));}
const pn={C:entities.C,E:entities.E,D3:stores.D3,D7:stores.D7,D8:stores.D8,D9:stores.D9,D10:stores.D10,D13:stores.D13,D11:stores.D11,P91:'9.1 Verify Submitted DTR',P92:'9.2 Compute Payroll Preview',P93:'9.3 Finalize Reviewed Billing Snapshot',P94:'9.4 Retrieve Finalized Payslip',P10:processes.P10};
const pe=[['C','P91','DTR verification decision'],['D8','P91','Submitted site and cutoff DTR'],['P91','D8','Reviewed DTR'],['P91','C','Verification result'],['C','P92','Site-scoped computation inputs'],['D8','P92','Verified employee DTR'],['D3','P92','Employee salary configuration'],['D7','P92','Schedules and holiday rules'],['D9','P92','Approved overtime and existing leave'],['D13','P92','Government deduction rates'],['D10','P92','Existing runs and adjustments'],['P92','D10','Computed run and computation history'],['P92','P10','Computed billing rows'],['P92','C','Payroll preview and computation result'],['P10','D10','Persisted billing export snapshot'],['C','P93','Latest export ID and billing-reviewed confirmation'],['D10','P93','Latest post-computation export and roster snapshot'],['D8','P93','Verified site-scoped DTR membership'],['P93','D10','Finalized payroll and frozen run-member snapshots'],['P93','D11','Payslip available notification'],['P93','C','Finalization result'],['E','P94','Payroll history or payslip selection'],['D10','P94','Finalized payroll and billing details'],['P94','E','Payslip and payroll history']];
results.push(await render('DFD_Level_2_Payroll','Level 2 - Process 9.0 Payroll Computation and Finalization',pn,pe));
fs.writeFileSync(path.join(out,'model.json'),JSON.stringify({entities,processes,stores,external,internal},null,2));
fs.writeFileSync(path.join(out,'verification.json'),JSON.stringify({date:'2026-10-08',boundaryBalanced:collapsed.every(([a,b,l])=>grouped.get(a+'>'+b)[2].includes(l)&&!!summaries[a+'>'+b]),diagrams:results},null,2));
console.log(JSON.stringify(results));
})().catch(e=>{console.error(e);process.exit(1)});
