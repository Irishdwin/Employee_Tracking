/* HR Employee Management: read-only records from the secured dashboard API. */
(function(global){
  'use strict';
  const api='../../Api/HR/hr_dashboard.php',sectionId='section-employee-management';
  let initialized=false,optionsPromise=null,listToken=0,detailToken=0,page=1,detailId=null,modal,searchTimer,requestedFilter;
  const $=id=>document.getElementById(id);
  const fields={q:'hrEmployeesSearch',filter:'hrEmployeesStatus',company_id:'hrEmployeesCompany',site:'hrEmployeesSite',position:'hrEmployeesPosition',employee_type:'hrEmployeesType'};
  function node(tag,text,cls){const el=document.createElement(tag);if(text!==undefined)el.textContent=text;if(cls)el.className=cls;return el;}
  async function request(action,params={}){
    const query=new URLSearchParams({action,...params});
    const response=await fetch(api+'?'+query,{credentials:'same-origin',cache:'no-store'});
    let data;try{data=await response.json();}catch(_){throw new Error('Invalid server response. Please retry.');}
    if(!response.ok||!data.success)throw new Error(data.message||'Employee records could not be loaded.');
    return data.data;
  }
  function values(){return Object.fromEntries(Object.entries(fields).map(([key,id])=>[key,$(id).value]));}
  function fill(id,rows,empty,key,label,saved){
    const select=$(id);select.replaceChildren(new Option(empty,''));
    rows.forEach(row=>select.add(new Option(row[label],String(row[key]))));
    select.value=saved||'';
    if(saved&&select.value!==String(saved)){select.add(new Option(String(saved)+' (no longer listed)',String(saved)));select.value=String(saved);}
    select.disabled=false;
  }
  function options(data,saved){
    const status=$('hrEmployeesStatus');status.replaceChildren();
    data.workforce.forEach(row=>status.add(new Option(row.label,row.value)));status.value=saved.filter||'total';status.disabled=false;
    fill('hrEmployeesCompany',data.companies,'All current companies','id','label',saved.company_id);
    fill('hrEmployeesSite',data.sites,'All current work sites','value','value',saved.site);
    fill('hrEmployeesPosition',data.positions,'All positions','value','value',saved.position);
    fill('hrEmployeesType',data.types,'All employment types','value','value',saved.employee_type);
  }
  function state(text){const tr=node('tr'),td=node('td',text,'text-center text-muted py-4');td.colSpan=8;tr.append(td);$('hrEmployeeRows').replaceChildren(tr);}
  function badge(text,active){return node('span',text,'badge '+(active?'bg-success-subtle text-success-emphasis':'bg-secondary-subtle text-secondary-emphasis'));}
  function row(employee){
    const tr=node('tr');
    const name=[employee.FirstName,employee.LastName].filter(Boolean).join(' ');
    [employee.EmployeeNumber||'Not recorded',name,employee.Position||'Not recorded'].forEach(value=>tr.append(node('td',value)));
    const deployment=node('td');
    if(employee.DeploymentID){deployment.append(node('div',employee.CompanyName||'Company not recorded'),node('div',employee.WorkSite||'Work site not recorded','small text-muted'));}
    else deployment.textContent='Not Deployed';
    tr.append(deployment,node('td',employee.EmployeeType||'Not recorded'),node('td',employee.HiredDate||'Not recorded'));
    const status=node('td');status.append(badge(employee.RecordedStatus,employee.RecordedStatus==='Active'),node('div',employee.IsActive===null?'Active flag unspecified':Number(employee.IsActive)===1?'Active flag: Yes':'Active flag: No','small text-muted'));tr.append(status);
    const actions=node('td'),button=node('button','View Details','btn btn-sm btn-outline-primary');button.type='button';button.setAttribute('aria-label','View details for '+name);button.addEventListener('click',()=>details(Number(employee.EmployeeID)));actions.append(button);tr.append(actions);return tr;
  }
  async function load(target=page,refreshOptions=false){
    if(!init())return;
    clearTimeout(searchTimer);const token=++listToken;const saved=values();
    if(requestedFilter){saved.filter=requestedFilter;requestedFilter=null;}
    $('hrEmployeePanel').setAttribute('aria-busy','true');$('hrEmployeesRefresh').disabled=true;
    $('hrEmployeesAlert').classList.add('d-none');$('hrEmployeePagination').replaceChildren();state('Loading employee records…');
    try{
      if(refreshOptions)optionsPromise=null;
      if(!optionsPromise)optionsPromise=request('employee_options').catch(error=>{optionsPromise=null;throw error;});
      const choices=await optionsPromise;if(token!==listToken)return;options(choices,saved);
      // Keep explicit selected values in the request even if their lookup option was removed.
      const result=await request('employee_list',{...saved,page:target});
      if(token!==listToken)return;
      page=result.page;$('hrEmployeeRows').replaceChildren();
      if(!result.rows.length)state('No employees match these filters.');
      result.rows.forEach(employee=>$('hrEmployeeRows').append(row(employee)));
      $('hrEmployeeTitle').textContent=result.total.toLocaleString()+' employee'+(result.total===1?'':'s');
      $('hrEmployeesUpdated').textContent='Updated '+new Date(result.as_of).toLocaleString('en-PH',{timeZone:'Asia/Manila'});
      global.renderPaginationControls('hrEmployeePagination',result,next=>load(next),'employees');
    }catch(error){
      if(token!==listToken)return;
      state('Employee records unavailable. Use Refresh to retry.');$('hrEmployeeTitle').textContent='Employee records';
      $('hrEmployeesUpdated').textContent='Could not load current records.';
      $('hrEmployeesAlert').textContent=error.message;$('hrEmployeesAlert').classList.remove('d-none');
    }finally{if(token===listToken){$('hrEmployeePanel').setAttribute('aria-busy','false');$('hrEmployeesRefresh').disabled=false;}}
  }
  function info(title,entries){
    const section=node('section',undefined,'mb-4');section.append(node('h3',title,'h6 mb-3'));const dl=node('dl');
    entries.forEach(([label,value])=>dl.append(node('dt',label),node('dd',value===null||value===undefined||value===''?'Not recorded':String(value))));section.append(dl);return section;
  }
  function renderDetails(data){
    const body=$('hrEmployeeDetailsBody');body.replaceChildren();const e=data.employee;
    body.append(info('Personal and employment',[
      ['Employee number',e.EmployeeNumber],['Name',[e.FirstName,e.LastName].filter(Boolean).join(' ')],['Email',e.Email],
      ['Position',e.Position],['Department',e.Department],['Employment type',e.EmployeeType],['Hire date',e.HiredDate],
      ['Recorded status',e.RecordedStatus],['Employee active flag',e.IsActive===null?'Unspecified':Number(e.IsActive)===1?'Active':'Inactive']
    ]));
    body.append(info('Current deployment',[['Client company',e.DeploymentID?(e.CompanyName||'Not recorded'):'Not Deployed'],['Work site',e.DeploymentID?e.WorkSite:'—'],['Deployment position',e.DeploymentID?e.DeploymentPosition:'—'],['Team Lead',e.DeploymentID?e.CurrentTeamLead:'—'],['Start date',e.DeploymentID?e.CurrentStartDate:'—'],['Recorded deployment status',e.DeploymentID?e.CurrentDeploymentStatus:'—']]));
    body.append(info('Government information',[['SSS',e.SSSNumber],['PhilHealth',e.PhilHealthNumber],['Pag-IBIG',e.PagIBIGNumber]]));
    if(data.resignation)body.append(info('Latest recorded resignation',[['Status',data.resignation.RecordedStatus],['Effective date',data.resignation.EffectiveDate]]));
    body.append(node('h3','Deployment records','h6'));
    if(!data.deployments.length){body.append(node('p','No deployment records.','hr-deployment-empty text-muted'));return;}
    const history=node('div',undefined,'hr-deployment-history');
    data.deployments.forEach(d=>{
      // The server selects the current deployment; never infer it from dates/status text here.
      const current=e.DeploymentID!==null&&e.DeploymentID!==undefined&&Number(e.DeploymentID)===Number(d.DeploymentID);
      const card=node('article',undefined,'hr-deployment-record'+(current?' is-current':''));
      const heading=node('div',undefined,'hr-deployment-heading');
      heading.append(node('h4',d.CompanyName||'Company not recorded','hr-deployment-company'));
      const labels=node('div',undefined,'hr-deployment-badges');
      if(current)labels.append(node('span','Current deployment','badge bg-primary-subtle text-primary-emphasis'));
      const status=d.RecordedStatus||'Unspecified';labels.append(badge(status,status.trim().toLowerCase()==='active'));
      heading.append(labels);card.append(heading,node('p',d.Location||'Work site not recorded','hr-deployment-address'));
      const grid=node('dl',undefined,'hr-deployment-grid');
      [['Position',d.Position],['Team Lead',d.TeamLeadName],['Start date',deploymentDate(d.StartDate)],['End date',d.EndDate?deploymentDate(d.EndDate):'No end date']].forEach(([label,value])=>{
        const field=node('div');field.append(node('dt',label),node('dd',value||'Not recorded'));grid.append(field);
      });
      card.append(grid);history.append(card);
    });body.append(history);
  }
  function deploymentDate(value){
    if(!value)return 'Not recorded';
    // Reuse the existing HR formatter, with a local date-time to preserve date-only values.
    if(typeof global.formatJorDetailDate==='function')return global.formatJorDetailDate(String(value).slice(0,10)+'T00:00:00');
    const date=new Date(String(value).slice(0,10)+'T00:00:00');
    return isNaN(date.getTime())?String(value):date.toLocaleDateString('en-PH',{year:'numeric',month:'short',day:'numeric'});
  }
  async function details(id){
    const token=++detailToken;detailId=id;
    $('hrEmployeeDetailsBody').textContent='Loading employee details…';$('hrEmployeeDetailsBody').setAttribute('aria-busy','true');$('hrEmployeeDetailsRetry').classList.add('d-none');
    modal=modal||global.bootstrap.Modal.getOrCreateInstance($('hrEmployeeDetailsModal'));modal.show();
    try{const data=await request('employee_details',{employee_id:id});if(token===detailToken)renderDetails(data);}
    catch(error){if(token===detailToken){$('hrEmployeeDetailsBody').textContent=error.message;$('hrEmployeeDetailsRetry').classList.remove('d-none');}}
    finally{if(token===detailToken)$('hrEmployeeDetailsBody').setAttribute('aria-busy','false');}
  }
  function init(){
    if(initialized)return true;if(!$(sectionId))return false;initialized=true;
    $('hrEmployeesFilters').addEventListener('submit',event=>{event.preventDefault();load(1);});
    Object.entries(fields).filter(([key])=>key!=='q').forEach(([,id])=>$(id).addEventListener('change',()=>load(1)));
    $('hrEmployeesSearch').addEventListener('input',()=>{clearTimeout(searchTimer);searchTimer=setTimeout(()=>load(1),300);});
    $('hrEmployeesClear').addEventListener('click',()=>{Object.entries(fields).forEach(([key,id])=>$(id).value=key==='filter'?'total':'');load(1);});
    $('hrEmployeesRefresh').addEventListener('click',()=>load(page,true));
    $('hrEmployeeDetailsRetry').addEventListener('click',()=>details(detailId));
    $('hrEmployeeDetailsModal').addEventListener('hidden.bs.modal',()=>{++detailToken;detailId=null;$('hrEmployeeDetailsBody').replaceChildren();});
    return true;
  }
  function open(filter='total'){
    if(!init())return;page=1;Object.entries(fields).forEach(([key,id])=>$(id).value=key==='filter'?filter:'');requestedFilter=filter;
    if(typeof global.hrShowSection==='function')global.hrShowSection(sectionId);else load(1);
  }
  global.HrEmployees={load,open};
  document.addEventListener('DOMContentLoaded',()=>{init();});
})(window);

