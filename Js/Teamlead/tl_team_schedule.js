(function (global) {
  'use strict';
  var API = '../../Api/TeamLead/team_schedule.php';
  var state = { employees: [], schedules: [], csrf: '', today: '', loading: false, saving: false, edit: null, current: null, dutyRequest: 0, confirming: false };
  function el(id) { return document.getElementById(id); }
  function esc(value) { var d = document.createElement('div'); d.textContent = value == null ? '' : String(value); return d.innerHTML; }
  function message(text, danger) {
    var box = el('tlScheduleMessage'); box.textContent = text;
    box.className = 'alert mt-4 ' + (danger ? 'alert-danger' : 'alert-success');
  }
  async function request(options) {
    var response = await fetch(API+(options&&options.method==='POST'?'':'?site_id='+encodeURIComponent(el('tlScheduleSite').value)), Object.assign({ credentials: 'include' }, options || {}));
    var data = await response.json();
    if (!response.ok || !data.success) throw new Error(data.message || 'Unable to load Team Schedule.');
    return data;
  }
  function setType() {
    var fixed = el('tlScheduleType').value === 'Fixed';
    el('tlScheduleDays').classList.toggle('d-none', !fixed);
    el('tlScheduleDate').readOnly = fixed || Boolean(state.edit && state.edit.schedule_id);
    el('tlScheduleHelp').textContent = fixed
      ? 'Fixed changes take effect today. Earlier recurring settings are preserved.'
      : 'This expected shift applies only to the selected date. An earlier Time Out means the following day.';
    if (fixed && state.today) el('tlScheduleDate').value = state.today;
  }
  function fill(s) {
    el('tlScheduleType').value = s.schedule_type;
    el('tlScheduleDate').value = s.date || state.today;
    el('tlScheduleTimeIn').value = s.time_in.slice(0, 5);
    el('tlScheduleTimeOut').value = s.time_out.slice(0, 5);
    el('tlScheduleBreak').value = s.break_duration;
    el('tlScheduleGrace').value = s.grace_period;
    el('tlScheduleForm').querySelectorAll('[name="schedule_day"]').forEach(function (box) {
      box.checked = (s.working_day_names || String(s.working_days).split(/,\s*/)).indexOf(box.value) >= 0;
    });
    setType();
  }
  function selectEmployee() {
    state.edit = null; el('tlScheduleEmployee').disabled = false; el('tlScheduleType').disabled = false;
    el('tlScheduleType').options[0].disabled = false;
    var employee = state.employees.find(function (e) { return String(e.deployment_id) === el('tlScheduleEmployee').value; });
    if (employee) {
      fill(employee.fixed);
      el('tlScheduleType').value='Rotating';setType();refreshDuty();
      el('tlScheduleDate').min = employee.start_date;
      el('tlScheduleDate').max = employee.end_date || '';
    }
    render();
  }
  async function refreshDuty(){
    var seq=++state.dutyRequest;state.current=null;el('tlScheduleCurrentDuty').textContent='Loading current duty…';
    if(!el('tlScheduleEmployee').value){el('tlScheduleCurrentDuty').textContent='Select an employee and date.';return;}
    try{var r=await fetch(API+'?site_id='+encodeURIComponent(el('tlScheduleSite').value)+'&date='+encodeURIComponent(el('tlScheduleDate').value),{credentials:'include'});var d=await r.json();if(seq!==state.dutyRequest)return;if(!r.ok||!d.success)throw new Error(d.message||'Unable to load current duty.');var employee=d.employees.find(function(e){return String(e.deployment_id)===el('tlScheduleEmployee').value;});state.current=employee&&employee.current_duty;if(!state.current)throw new Error('Select an assigned employee.');el('tlScheduleCurrentDuty').textContent=state.current.is_working_day?formatTime(state.current.time_in)+' – '+formatTime(state.current.time_out)+(state.current.overnight?' (next day)':''):'Rest day';}catch(e){if(seq===state.dutyRequest)el('tlScheduleCurrentDuty').textContent=e.message;}
  }
  function formatTime(t) {
    var p = t.split(':'); var h = Number(p[0]);
    return (h % 12 || 12) + ':' + p[1] + (h >= 12 ? ' PM' : ' AM');
  }
  function render() {
    var selected = el('tlScheduleEmployee').value;
    var filtered = state.schedules.filter(function (s) { return !selected || String(s.deployment_id) === selected; });
    el('tlScheduleRows').innerHTML = filtered.length ? filtered.map(function (s) {
      var index = state.schedules.indexOf(s);
      return '<tr><td>' + esc(s.employee_name) + '</td><td>' + esc(s.schedule_type === 'Fixed' ? s.working_days : s.date) +
        '</td><td>' + esc(s.schedule_type) + '</td><td>' + esc(formatTime(s.time_in)) + '</td><td>' +
        esc(formatTime(s.time_out)) + (s.time_out < s.time_in ? ' <small>(next day)</small>' : '') +
        '</td><td>' + s.break_duration + ' min</td><td>' + esc(s.status) + '</td><td class="text-nowrap">' +
        '<button type="button" class="btn btn-sm btn-outline-primary" data-edit="' + index + '">Edit</button>' +
        (s.schedule_id ? ' <button type="button" class="btn btn-sm btn-outline-danger" data-delete="' + index + '">Delete</button>' : '') + '</td></tr>';
    }).join('') : '<tr><td colspan="8" class="text-muted">No deployed team schedules found.</td></tr>';
  }
  document.getElementById('tlScheduleSite').addEventListener('change',function(){el('tlScheduleEmployee').value='';load();});
  async function load() {
    if (state.loading || state.saving) return;
    state.loading = true;
    var requestedSite=el('tlScheduleSite').value;
    var selectedDate=el('tlScheduleDate').value;
    el('tlScheduleSite').disabled=true;
    el('tlScheduleSave').disabled=true;
    try {
      var data = await request();
      var selected = el('tlScheduleEmployee').value;
      var selectedSite=requestedSite;
      el('tlScheduleSite').replaceChildren(new Option('Select deployment site',''));
      (data.sites||[]).forEach(function(s){el('tlScheduleSite').add(new Option(s.CompanyName+' — '+s.Name,s.SiteID));});
      el('tlScheduleSite').value=selectedSite;
      state.employees = data.employees; state.schedules = data.schedules; state.csrf = data.csrf_token; state.today = data.today;
      state.edit = null;
      el('tlScheduleEmployee').disabled = false;
      el('tlScheduleType').disabled = false;
      el('tlScheduleType').options[0].disabled = false;
      el('tlScheduleEmployee').innerHTML = '<option value="">Select an employee</option>' + state.employees.map(function (e) {
        return '<option value="' + e.deployment_id + '">' + esc(e.name + ' — ' + e.company) + '</option>';
      }).join('');
      el('tlScheduleEmployee').value = selected;
      el('tlScheduleDate').value = state.today;
      el('tlScheduleSave').disabled = !state.employees.length;
      if (selected && !state.edit) {selectEmployee();el('tlScheduleDate').value=selectedDate||state.today;refreshDuty();}
      setType(); render();
    } catch (error) { message(error.message, true); }
    finally { state.loading = false;el('tlScheduleSite').disabled=false; }
  }
  async function mutate(payload) {
    if (state.saving) return;
    state.saving = true; el('tlScheduleSave').disabled = true;el('tlScheduleSite').disabled=true;
    try {
      payload.csrf_token = state.csrf;payload.site_id=Number(el('tlScheduleSite').value);
      var data = await request({ method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
      state.edit = null; el('tlScheduleEmployee').disabled = false; el('tlScheduleType').disabled = false;
      message(data.message, false);
      state.saving = false;
      await load();
    } catch (error) { message(error.message, true); }
    finally { state.saving = false;el('tlScheduleSite').disabled=false; el('tlScheduleSave').disabled = !state.employees.length; }
  }
  el('tlScheduleForm').addEventListener('submit', async function (event) {
    event.preventDefault();
    if (!el('tlScheduleForm').reportValidity()) return;
    var selected = el('tlScheduleEmployee').value;
    if (!selected) { message('Select a deployed employee.', true); return; }
    if(state.confirming || state.saving)return;
    var payload={ action: 'save' , deployment_id: Number(selected), schedule_type: el('tlScheduleType').value,
      date: el('tlScheduleDate').value, time_in: el('tlScheduleTimeIn').value, time_out: el('tlScheduleTimeOut').value,
      break_duration: Number(el('tlScheduleBreak').value), grace_period: Number(el('tlScheduleGrace').value),
      working_days: Array.from(el('tlScheduleForm').querySelectorAll('[name="schedule_day"]:checked')).map(function (c) { return c.value; }) };
    if(el('tlScheduleType').value!=='Fixed'){
      if(!state.current || state.current.date!==el('tlScheduleDate').value){message('Wait for the current duty to load before saving.',true);return;}
      state.confirming=true;
      var employee=state.employees.find(function(e){return String(e.deployment_id)===selected;});
      var confirmed=await global.OnazulDialogs.confirm('Employee: '+employee.name+'\nEffective Date: '+el('tlScheduleDate').value+'\nCurrent Duty: '+el('tlScheduleCurrentDuty').textContent+'\nNew Duty: '+formatTime(el('tlScheduleTimeIn').value)+' – '+formatTime(el('tlScheduleTimeOut').value)+'\nThis change applies only to the selected date.',{title:'Change Employee Duty Schedule?',confirmText:'Confirm Change'});
      state.confirming=false;if(!confirmed)return;
    }
    mutate(payload);
  });
  el('tlScheduleEmployee').addEventListener('change', selectEmployee);
  el('tlScheduleDate').addEventListener('change',refreshDuty);
  el('tlScheduleType').addEventListener('change', setType);
  el('tlScheduleRefresh').addEventListener('click', load);
  el('tlScheduleReset').addEventListener('click', function () { state.edit = null; el('tlScheduleForm').reset(); selectEmployee(); setType(); });
  el('tlScheduleRows').addEventListener('click', async function (event) {
    var edit = event.target.closest('[data-edit]'); var remove = event.target.closest('[data-delete]');
    if (state.saving || state.loading) return;
    if (edit) {
      var s = state.schedules[Number(edit.dataset.edit)];
      el('tlScheduleEmployee').value = String(s.deployment_id); selectEmployee(); fill(s);
      refreshDuty();state.edit = s; el('tlScheduleEmployee').disabled = true;
      el('tlScheduleType').options[0].disabled = Boolean(s.schedule_id);
      el('tlScheduleDate').readOnly = Boolean(s.schedule_id) || s.schedule_type === 'Fixed';
      el('tlScheduleForm').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    if (remove) {
      var row = state.schedules[Number(remove.dataset.delete)];
      if (await global.OnazulDialogs.confirm('Remove this dated schedule for ' + row.employee_name + ' on ' + row.date + '? Recurring hours will apply again.')) {
        mutate({ action: 'delete', deployment_id: row.deployment_id, schedule_id: row.schedule_id });
      }
    }
  });
  global.TlTeamSchedule = { load: load };
  if (global.location.hash === '#view-schedule' && typeof global.showView === 'function') global.showView('schedule');
})(window);
