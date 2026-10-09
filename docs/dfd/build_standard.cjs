const fs=require('fs'),path=require('path');
const rows=[
 ['1.0','Manage Accounts','All six portal roles','Credentials and account inputs','Session and account results','D1','Accounts and Security','Account identity and roles','Account changes'],
 ['2.0','Manage Recruitment','Applicant / HR / Team Lead','Profile, application and review inputs','Application and hiring results','D2','Recruitment Records','Applicant and job records','Applications and decisions'],
 ['3.0','Manage Staffing and Deployment','Admin / HR / Team Lead','Job orders, sites and assignments','Staffing and deployment results','D3','Staffing and Deployment Records','Clients, orders and sites','Orders and deployments'],
 ['4.0','Manage Employee Records','Employee / HR / Accounting / Admin','Profile, resignation and clearance inputs','Employee and final-pay results','D4','Employee Records','Employee and clearance records','Profile and separation changes'],
 ['5.0','Manage Work Time','Employee / Team Lead / Accounting','Clock evidence, schedules, OT and DTR inputs','Attendance, OT and DTR results','D5','Work Time Records','Schedules, attendance and DTR','Schedules, clock and review records'],
 ['6.0','Process Payroll and Billing','Accounting / Employee','Payroll review, revision and status inputs','Billing, payslip and payroll status','D6','Payroll and Billing Records','Runs, rates and billing snapshots','Finalized payroll and status'],
 ['7.0','Manage Communications','All six portal roles','Authorized messages and read inputs','Messages and notifications','D7','Communication Records','Messages and notifications','Messages and read states'],
 ['8.0','Produce Reports','Admin / HR / Accounting','Report filters and export selection','Audit, workforce and payroll summaries','D8','Audit and Reference Records','Audit and configuration records',null]
];
const esc=s=>s.replace(/&/g,'&amp;').replace(/</g,'&lt;');
const text=(x,y,s,size=16)=>`<text x="${x}" y="${y}" text-anchor="middle" font-family="Arial" font-size="${size}" fill="#172b20">${esc(s)}</text>`;
const arrow=(x1,y1,x2,y2,label)=>`<path d="M${x1},${y1} L${x2},${y2}" fill="none" stroke="#356b47" stroke-width="1.5" marker-end="url(#arrow)"/>`+text((x1+x2)/2,(y1+y2)/2-9,label,14);
let svg=`<svg xmlns="http://www.w3.org/2000/svg" width="1580" height="2040" viewBox="0 0 1580 2040"><defs><marker id="arrow" markerWidth="9" markerHeight="7" refX="8" refY="3.5" orient="auto"><path d="M0,0 L9,3.5 L0,7 Z" fill="#356b47"/></marker></defs><rect width="1580" height="2040" fill="white"/>`;
svg+=text(790,40,'ONAZUL HRIS — Level 1 Data Flow Diagram',25)+text(790,70,'Gane & Sarson notation · Consolidated business processes',16);
svg+=text(150,110,'EXTERNAL ENTITIES',14)+text(760,110,'PROCESSES',14)+text(1370,110,'LOGICAL DATA STORES',14);
const m=['%% Consolidated standard DFD. Exact Gane & Sarson store symbols are in SVG.','flowchart LR'];
rows.forEach((r,i)=>{const[num,name,role,input,output,store,storeName,read,write]=r,y=150+i*220,cy=y+55;
 svg+=`<rect x="20" y="${y}" width="300" height="110" fill="white" stroke="#356b47"/>`;
 const roles=role.split(' / ');roles.forEach((s,j)=>svg+=text(170,cy+(j-(roles.length-1)/2)*22,s,17));
 svg+=`<rect x="620" y="${y}" width="290" height="110" rx="14" fill="#f4faf5" stroke="#356b47"/><path d="M620,${y+34} H910" stroke="#356b47"/>`+text(765,y+24,num,18)+text(765,y+70,name,17);
 svg+=`<path d="M1545,${y} H1220 V${y+110} H1545 M1270,${y} V${y+110}" fill="none" stroke="#356b47"/>`+text(1245,cy+6,store,16)+text(1405,cy+6,storeName,15);
 svg+=arrow(320,y+35,620,y+35,input)+arrow(620,y+85,320,y+85,output)+arrow(1220,y+35,910,y+35,read);if(write)svg+=arrow(910,y+85,1220,y+85,write);
 m.push(` E${i}["${role}"] -->|"${input}"| P${i}("${num} ${name}")`,` P${i} -->|"${output}"| E${i}`,` ${store}[["${store} ${storeName}"]] -->|"${read}"| P${i}`);if(write)m.push(` P${i} -->|"${write}"| ${store}`);
});
// Shared logical records feed downstream calculations and reporting. Repeated
// store symbols below are references to the same store, not duplicate databases.
svg+=text(790,1950,'Shared store reads: D2 → 4.0 approved hiring record; D3 → 5.0 deployment/worksite; D5 → 6.0 verified DTR, schedule and overtime.',15);
svg+=text(790,1978,'D4 → 6.0 employee rates; D8 → 6.0 deduction rules; D2–D6 → 8.0 report records; business events → D7 notifications.',15);
svg+=text(790,2006,'Entity groups list participating roles; permissions remain specific to each operation. See detailed diagrams for individual flows.',14);
// Include these dependencies as real arrows in the editable source and companion sheet.
const deps=[['D2','P3','Approved hiring record'],['D3','P4','Deployment and worksite'],['D5','P5','Verified DTR, schedule and overtime'],['D4','P5','Employee rates'],['D8','P5','Deduction rules'],...['D2','D3','D4','D5','D6'].map(d=>[d,'P7','Report records']),...['P0','P1','P2','P4','P5'].map(p=>[p,'D7','Event notifications'])];
for(const[a,b,l]of deps)m.push(` ${a} -->|"${l}"| ${b}`);
for(let i=0;i<rows.length;i++){
 const y=150+i*220,inputs=deps.filter(([a,b])=>b==='P'+i);
 inputs.forEach(([a,b,l],j)=>{const x=180+j*260;
 svg+=`<path d="M${x+240},${y+148} H${x} V${y+188} H${x+240}" fill="white" stroke="#356b47"/>`+text(x+120,y+166,a+' · '+l,12);
 svg+=`<path d="M${x+120},${y+148} L${650+j*42},${y+110}" stroke="#356b47" fill="none" marker-end="url(#arrow)"/>`;
 });
 if(deps.some(([a,b])=>a==='P'+i&&b==='D7')){
 svg+=`<path d="M1435,${y+145} H1220 V${y+185} H1435" fill="white" stroke="#356b47"/>`+text(1325,y+170,'D7 · Notifications',13);
 svg+=`<path d="M910,${y+110} L1220,${y+160}" stroke="#356b47" fill="none" marker-end="url(#arrow)"/>`+text(1050,y+135,'Event notifications',13);
 }
}
svg+='</svg>';fs.writeFileSync(path.join(__dirname,'DFD_Standard_Level_1.svg'),svg);fs.writeFileSync(path.join(__dirname,'DFD_Standard_Level_1.mmd'),m.join('\n'));
fs.writeFileSync(path.join(__dirname,'standard_model.json'),JSON.stringify({rows,dependencies:deps,grouping:{'1.0':['P1'],'2.0':['P2'],'3.0':['P3','P5'],'4.0':['P4'],'5.0':['P6','P7','P8','P14'],'6.0':['P9','P10'],'7.0':['P11','P12'],'8.0':['P13']}},null,2));
console.log('Standard DFD created');
