/* HR-only, read-only overview. Rendering uses textContent for all database strings. */
(function () {
  'use strict';
  const api = '../../Api/HR/hr_dashboard.php';
  const definitions = [
    ['total','Total employees','Employee records','total'],
    ['active','Active employees','Active flag = 1','active'],
    ['inactive','Inactive employees','Active flag = 0','inactive'],
    ['awol','AWOL employees','Explicit recorded status','awol'],
    ['resigned','Resigned employees','Recorded resignation','resigned'],
    ['recent_hires','Recently hired','Last 30 calendar days','recent_hires'],
    ['deployed','Currently deployed','Active employees assigned today','deployed'],
    ['applicants','Total applicants','Registered applicant profiles'],
    ['pending','Pending applications','Awaiting initial review',null,'section-applicant-review','Applied'],
    ['applications','Total applications','Includes hired · open Applicant Review',null,'section-applicant-review',''],
    ['open_orders','Open job orders','View job orders',null,'section-job-orders'],
    ['pending_orders','Pending job requests','View hiring requests',null,'section-job-orders'],
    ['notifications','Unread notifications','Your HR notifications',null,'section-notifications']
  ];
  let root, inflight, lastData;
  const $ = id => document.getElementById(id);
  function node(tag, text, cls) { const el=document.createElement(tag); if(text!==undefined) el.textContent=text; if(cls) el.className=cls; return el; }
  function navigate(target, filter) {
    if(target==='section-applicant-review') $('filterStatus').value=filter || '';
    if(typeof window.hrShowSection==='function') window.hrShowSection(target);
  }
  function metrics(data) {
    const box=$('hrOverviewMetrics'); box.replaceChildren();
    const primary=['total','active','deployed','pending'];
    const icons={total:'people',active:'person-check',deployed:'geo-alt',pending:'clipboard-check'};
    definitions.filter(d=>primary.includes(d[0])).sort((a,b)=>primary.indexOf(a[0])-primary.indexOf(b[0])).forEach(([key,label,note,filter,target,status]) => {
      const card=node('button',undefined,'hr-metric hr-kpi-'+key);
      if(card.tagName==='BUTTON') { card.type='button'; card.addEventListener('click',()=> filter ? employees(filter,1) : navigate(target,status)); }
      const content=node('span',undefined,'hr-metric-content'); content.append(node('span',label,'hr-metric-label'),node('span',data ? Number(data[key]).toLocaleString() : '—','hr-metric-value'),node('span',note,'hr-metric-note'));
      const icon=node('span',undefined,'hr-kpi-icon'); icon.setAttribute('aria-hidden','true'); icon.append(node('i',undefined,'bi bi-'+icons[key])); card.append(content,icon); box.append(card);
    });
    compactStats('hrWorkforceStats',['total','active','inactive','awol','resigned','recent_hires','deployed'],data);
    compactStats('hrRecruitmentStats',['applicants','applications','pending','open_orders','pending_orders','recent_hires'],data);
    const alerts=$('hrPendingActions'); alerts.replaceChildren();
    if(!data) { alerts.append(node('p','Loading pending actions…','text-muted small')); return; }
    [['pending','Applications awaiting review','Review pending applications','section-applicant-review','Applied'],['pending_orders','Job requests awaiting action','Review job requests','section-job-orders']].forEach(([key,label,action,target,filter])=> {
      if(!data[key]) return;
      const item=node('button',undefined,'hr-action-alert hr-action-'+key); item.type='button';
      item.append(node('span',label,'hr-alert-title'),node('span',Number(data[key]).toLocaleString()+' · '+action,'hr-alert-message')); item.addEventListener('click',()=>navigate(target,filter)); alerts.append(item);
    });
    if(!alerts.children.length) alerts.append(node('p','No applications or job requests awaiting review.','hr-empty-state'));
  }
  function compactStats(id,keys,data) {
    const box=$(id); box.replaceChildren();
    keys.forEach(key=> {
      const [,label,note,filter,target,status]=definitions.find(d=>d[0]===key);
      const item=node(filter || target ? 'button' : 'div',undefined,'hr-compact-stat');
      if(filter || target) {item.type='button';item.addEventListener('click',()=>filter ? employees(filter,1) : navigate(target,status));}
      item.setAttribute('title',note); item.append(node('span',label,'hr-stat-label'),node('strong',data ? Number(data[key]).toLocaleString() : '—','hr-stat-value'));box.append(item);
    });
  }
  function chart(id, rows, action, empty) {
    const box=$(id); box.replaceChildren();
    if(!rows.length || !rows.some(r=>r.count>0)) { box.append(node('p',empty || 'No records available.','text-muted small')); return; }
    const max=Math.max(...rows.map(r=>r.count));
    rows.forEach(row=> {
      const clickable=action && row.filter!==undefined;
      const bar=node(clickable ? 'button' : 'div',undefined,'hr-bar');
      if(clickable) { bar.type='button'; bar.addEventListener('click',()=>action(row)); }
      const label=node('span',undefined,'hr-bar-label'); label.append(node('span',row.label),node('strong',Number(row.count).toLocaleString()));
      const track=node('span',undefined,'hr-bar-track'); track.setAttribute('aria-hidden','true');
      const fill=node('span',undefined,'hr-bar-fill'); fill.style.width=(row.count/max*100)+'%'; track.append(fill); bar.append(label,track); box.append(bar);
    });
  }
  function render(data) {
    metrics(data.metrics);
    chart('hrRecruitmentChart',data.recruitment,row=>navigate('section-applicant-review',row.filter),'No applications recorded.');
    chart('hrDeploymentChart',data.deployments,null,'No active employees currently deployed.');
    const m=data.metrics;
    chart('hrWorkforceChart',[{label:'Active',count:m.active,filter:'active'},{label:'Inactive',count:m.inactive,filter:'inactive'},{label:'Unspecified active flag',count:m.total-m.active-m.inactive}],row=>employees(row.filter || 'total',1),'No employee records.');
    chart('hrDtrChart',data.dtr,null,'No DTR submissions in the last 30 days.');
    $('hrAttendance').textContent=`${data.date} · ${data.attendance.clocked_in} employees with recorded clock-ins · ${data.attendance.open_shifts} records without a clock-out.`;
    const list=$('hrRecentActivities'); list.replaceChildren();
    if(!data.activities.length) list.append(node('li','No HR activity recorded.','hr-empty-state'));
    data.activities.forEach(row=> {
      const item=node('li',undefined,'hr-activity'); const marker=node('span',undefined,'hr-activity-marker'); marker.setAttribute('aria-hidden','true'); marker.append(node('span',undefined,'hr-activity-dot'),node('span',undefined,'hr-activity-line'));
      const content=node('div',undefined,'hr-activity-content');content.append(node('p',row.label,'hr-activity-message'),node('time',row.happened_at+' · Cagayan de Oro','hr-activity-time'));item.append(marker,content);list.append(item);
    });
    $('hrOverviewUpdated').textContent='Updated '+new Date(data.as_of).toLocaleString('en-PH',{timeZone:'Asia/Manila'})+' · Cagayan de Oro';
  }
  async function request(query) {
    const response=await fetch(api+query,{credentials:'same-origin',cache:'no-store'});
    let result; try { result=await response.json(); } catch (_) { throw new Error('The server returned an invalid response. Please retry.'); }
    if(!response.ok || !result.success) throw new Error(result.message || 'Dashboard unavailable. Please retry.');
    return result.data;
  }
  function load() {
    if(!root || inflight) return inflight;
    root.setAttribute('aria-busy','true'); $('hrOverviewRefresh').disabled=true;
    inflight=request('?action=summary').then(data=> {
      render(data); lastData=data; $('hrOverviewAlert').classList.add('d-none');

    }).catch(error=> {
      $('hrOverviewAlert').textContent=error.message+(lastData ? ' Displaying the last successful snapshot.' : ' No dashboard statistics are available.');
      $('hrOverviewAlert').classList.remove('d-none');
      if(!lastData) { ['hrRecruitmentChart','hrDeploymentChart','hrWorkforceChart','hrDtrChart','hrAttendance','hrRecentActivities','hrPendingActions'].forEach(id=>$(id).textContent='Data unavailable. Use Refresh to retry.'); $('hrOverviewUpdated').textContent='Could not load current records.'; }
    }).finally(()=> { root.setAttribute('aria-busy','false'); $('hrOverviewRefresh').disabled=false; inflight=null; });
    return inflight;
  }
  function employees(filter) { if(window.HrEmployees) window.HrEmployees.open(filter); }
  window.HrOverview={load};
  document.addEventListener('DOMContentLoaded',()=> {
    root=$('section-overview'); if(!root || !$('hrOverviewMetrics')) return;
    metrics(null); $('hrOverviewRefresh').addEventListener('click',()=> { load(); if(window.AdminSidebarBadges) window.AdminSidebarBadges.poll('../../Api/Common/realtime_counters.php'); });
    root.querySelectorAll('[data-hr-target]').forEach(button=>button.addEventListener('click',()=>navigate(button.dataset.hrTarget)));
    const visible=()=>!document.hidden && !root.classList.contains('d-none');
    document.addEventListener('visibilitychange',()=> { if(visible()) load(); });
    window.setInterval(()=> { if(visible()) load(); },60000);
    load();
  });
})();
