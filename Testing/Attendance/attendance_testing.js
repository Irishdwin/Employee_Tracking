/**
 * DEVELOPMENT / TESTING ONLY.
 * Drives the Attendance Testing Dashboard — talks only to the endpoints in
 * ./Api/, which in turn write to the real production `attendance` /
 * `daily_time_records` tables.
 */
(function () {
  'use strict';

  var API = 'Api/';
  var state = {
    companyId: null,
    siteId: null, deploymentId: null, cascadeVersion: 0,
    teamLeadId: null,
    employeeId: null,
    rateReady: false, rateRequest: 0,
    dailyRate: 0,
    hourlyRate: 0,
    breakMinutes: 60,
  };

  function $(id) { return document.getElementById(id); }

  function esc(s) {
    if (s == null) return '';
    var d = document.createElement('div');
    d.textContent = String(s);
    return d.innerHTML;
  }

  function showAlert(el, message, isError) {
    el.textContent = message;
    el.className = 'alert small mt-3 ' + (isError ? 'alert-danger' : 'alert-success');
    el.classList.remove('d-none');
  }

  function fetchJson(url, options) {
    if (!url.includes('run_holiday_scenarios.php')) {
      var context = {company_id: state.companyId, site_id: state.siteId, team_lead_id: state.teamLeadId, deployment_id: state.deploymentId};
      if (options && options.body) options.body = JSON.stringify(Object.assign(JSON.parse(options.body), context));
      else url += (url.includes('?') ? '&' : '?') + new URLSearchParams(context).toString();
    }
    return fetch(url, Object.assign({ credentials: 'include' }, options || {}))
      .then(function (r) { return r.json(); });
  }

  function loadCompanies() {
    fetchJson(API + 'get_companies.php')
      .then(function (res) {
        var sel = $('selCompany');
        if (!res || !res.success) {
          sel.innerHTML = '<option value="">Failed to load — Admin login required?</option>';
          return;
        }
        sel.innerHTML = '<option value="">Select a company…</option>' +
          res.companies.map(function (c) { return '<option value="' + c.company_id + '">' + esc(c.name) + '</option>'; }).join('');
      })
      .catch(function () {
        $('selCompany').innerHTML = '<option value="">Network error</option>';
      });
  }

  function loadSites(companyId) {
    var version = state.cascadeVersion, sel = $('selSite');
    sel.disabled = true; sel.innerHTML = '<option value="">Loading…</option>';
    fetchJson(API + 'get_sites.php?company_id=' + encodeURIComponent(companyId)).then(function(res) {
      if (version !== state.cascadeVersion) return;
      if (!res.success) throw new Error(res.message);
      sel.innerHTML = '<option value="">' + (res.sites.length ? 'Select a site…' : 'No active deployment sites') + '</option>' + res.sites.map(function(x){return '<option value="'+x.site_id+'">'+esc(x.name)+'</option>';}).join('');
      sel.disabled = !res.sites.length;
    }).catch(function(){if(version === state.cascadeVersion)sel.innerHTML='<option value="">Failed to load sites</option>';});
  }

  function loadTeamLeads(companyId) {
    var version = state.cascadeVersion, sel = $('selTeamLead');
    sel.disabled = true;
    sel.innerHTML = '<option value="">Loading…</option>';
    resetEmployeeAndBelow();

    fetchJson(API + 'get_teamleads.php?company_id=' + encodeURIComponent(companyId))
      .then(function (res) {
        if (version !== state.cascadeVersion) return;
        if (!res || !res.success) {
          sel.innerHTML = '<option value="">Failed to load</option>';
          return;
        }
        if (!res.team_leads.length) {
          sel.innerHTML = '<option value="">No active Team Lead assigned to this site</option>';
          return;
        }
        sel.innerHTML = '<option value="">Select a Team Lead…</option>' +
          res.team_leads.map(function (t) { return '<option value="' + t.team_lead_id + '">' + esc(t.name) + '</option>'; }).join('');
        sel.disabled = false;
      })
      .catch(function () {
        if (version !== state.cascadeVersion) return;
        sel.innerHTML = '<option value="">Network error</option>';
      });
  }

  function loadEmployees(companyId, teamLeadId) {
    var version = state.cascadeVersion, sel = $('selEmployee');
    sel.disabled = true;
    sel.innerHTML = '<option value="">Loading…</option>';
    resetRecordCards();

    fetchJson(API + 'get_employees.php?company_id=' + encodeURIComponent(companyId) + '&team_lead_id=' + encodeURIComponent(teamLeadId))
      .then(function (res) {
        if (version !== state.cascadeVersion) return;
        if (!res || !res.success) {
          sel.innerHTML = '<option value="">Failed to load</option>';
          return;
        }
        if (!res.employees.length) {
          sel.innerHTML = '<option value="">No active employees at this site</option>';
          return;
        }
        sel.innerHTML = '<option value="">Select an employee…</option>' +
          res.employees.map(function (e) { return '<option data-deployment-id="' + e.deployment_id + '" value="' + e.employee_id + '">' + esc(e.name) + ' (' + esc(e.employee_number) + ')</option>'; }).join('');
        sel.disabled = false;
      })
      .catch(function () {
        if (version !== state.cascadeVersion) return;
        sel.innerHTML = '<option value="">Network error</option>';
      });
  }

  function loadEmployeeContext(employeeId) {
    var version = state.cascadeVersion;
    fetchJson(API + 'get_employee_attendance_list.php?employee_id=' + encodeURIComponent(employeeId))
      .then(function (res) {
        if (version !== state.cascadeVersion || employeeId !== state.employeeId || !res || !res.success) return;

        var scheduleEl = $('scheduleInfo');
        var s = res.schedule;
        scheduleEl.innerHTML = '<strong>Duty schedule:</strong> ' + esc(s.working_days) + ', ' + esc(s.time_in) + '–' + esc(s.time_out) +
          ', ' + esc(s.break_duration) + ' min break, ' + esc(s.grace_period) + ' min grace period.';
        scheduleEl.innerHTML += '<br><strong>Deployment context:</strong> Site ' + esc(res.context.site_id) + ' · Deployment ' + esc(res.context.deployment_id) + ' · Payroll Type ' + esc(res.context.payroll_type);
        scheduleEl.classList.remove('d-none');

        state.dailyRate = Number(s.daily_rate) || 0;
        state.hourlyRate = Number(s.hourly_rate) || 0;
        state.breakMinutes = Number(s.break_duration) || 0;

        $('cardScenario').style.display = '';
        $('cardAddRecord').style.display = '';
        $('cardHoliday').style.display = '';
        $('cardOvertime').style.display = '';
        $('cardExpected').style.display = '';
        $('cardVerify').style.display = '';
        $('cardRecordsList').style.display = '';
        renderRecords(res.records || []);
        renderOtList(res.test_overtime || []);
        lookupHoliday();
        updateOtAvailability();
      })
      .catch(function () {});
  }

  function loadEmployeeRate() {
    var now=new Date(), today=now.getFullYear()+'-'+String(now.getMonth()+1).padStart(2,'0')+'-'+String(now.getDate()).padStart(2,'0');
    if(!$('inpDate').value)$('inpDate').value=today;
    var employee=state.employeeId, version=state.cascadeVersion, date=$('inpDate').value, request=++state.rateRequest;
    state.rateReady=false; state.dailyRate=0;state.hourlyRate=0;
    $('employeeRateCard').classList.remove('d-none');$('employeeRateDetails').textContent='Loading authoritative employee payroll rate…';$('btnRunEmployeeScenario').disabled=true;
    fetchJson(API+'get_employee_payroll_context.php?employee_id='+encodeURIComponent(employee)+'&date='+encodeURIComponent(date)).then(function(res){
      if(request!==state.rateRequest||version!==state.cascadeVersion||employee!==state.employeeId)return;
      if(!res.success)throw new Error(res.message || 'Could not resolve payroll context.');
      var r=res.rates,c=res.context,e=res.employee;
      $('employeeRateDetails').innerHTML='<p class="mb-1"><strong>'+esc(e.name)+'</strong> ('+esc(e.number)+') · '+esc(e.position)+'</p><p class="mb-1">Client: '+esc(c.company)+' · Site: '+esc(c.site)+' · Deployment '+esc(c.deployment_id)+' · Payroll Type '+esc(c.payroll_type)+'</p>'+
        '<p class="mb-1"><strong>Daily Rate: '+controlledMoney(r.daily_rate)+'</strong> · <strong>Hourly Rate: ₱'+Number(r.hourly_rate).toFixed(4)+'</strong></p><p class="small mb-1">Daily source: '+esc(r.daily_rate_source)+'<br>Hourly source: '+esc(r.hourly_rate_source)+'<br>Date: '+esc(res.date)+' · Cutoff: '+esc(res.period.start)+' to '+esc(res.period.end)+'<br>Accounting reads current employee salary fields; salary_rates effective dates are not applied by the current payroll path.</p>';
      if(!r.valid)throw new Error(res.message);
      state.dailyRate=r.daily_rate;state.hourlyRate=r.hourly_rate;state.rateReady=true;$('btnRunEmployeeScenario').disabled=false;
    }).catch(function(err){if(request!==state.rateRequest||version!==state.cascadeVersion)return;$('employeeRateDetails').textContent=err.message;});
  }
  function runEmployeeScenario() {
    var scenarioMap={custom:'A',t1:'A',t4:'B',t5:'C',t8:'D',t9:'E',t6:'F',t10:'G',t13:'H'};
    var scenario=scenarioMap[$('selScenario').value], date=$('inpDate').value, target=$('employeeScenarioResult'), version=state.cascadeVersion, rateRequest=state.rateRequest;
    if(!state.rateReady||!date||!scenario){target.textContent='Select a valid employee rate, test date and supported worked scenario (Custom uses Normal Day).';return;}
    var context={company_id:state.companyId,site_id:state.siteId,team_lead_id:state.teamLeadId,employee_id:state.employeeId,deployment_id:state.deploymentId,date:date,scenario:scenario};
    $('btnRunEmployeeScenario').disabled=true;target.textContent='Running the production Attendance → Team Lead DTR → Accounting → payroll path in a disposable database…';
    fetchJson(API+'run_employee_holiday_scenario.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(context)}).then(function(res){
      if(version!==state.cascadeVersion||date!==$('inpDate').value||rateRequest!==state.rateRequest)return;
      if(!res.success)throw new Error(res.message);
      var x=res.results[0],e=x.earnings,ref=x.reference_earnings,c=res.employee_context;
      target.innerHTML='<h3 class="h6">Holiday Pay Verification: '+esc(x.scenario)+'</h3><p>Server-resolved daily '+controlledMoney(c.rates.daily_rate)+' · hourly ₱'+Number(c.rates.hourly_rate).toFixed(4)+'</p><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Component</th><th>Independent expected reference</th><th>Actual production result</th></tr></thead><tbody>'+[['Basic/regular',ref.basic_pay,e.basic_pay],['Holiday premium',ref.holiday_pay,e.holiday_pay],['Holiday OT',ref.holiday_ot_pay,e.holiday_ot_pay],['Economic compensation',x.expected,x.actual]].map(function(row){return '<tr><th>'+row[0]+'</th><td>'+controlledMoney(row[1])+'</td><td>'+controlledMoney(row[2])+'</td></tr>';}).join('')+'</tbody></table></div><p>Difference: '+controlledMoney(x.difference)+' · <strong>'+esc(x.status)+'</strong></p><p>Team Lead holiday duty: '+esc(x.classification.holiday_work_hours)+'h · holiday OT: '+esc(x.classification.holiday_overtime_hours)+'h. Accounting rate/component checks: '+(Object.values(x.checks).every(Boolean)?'PASS':'FAIL')+'.</p><p>Production unchanged: '+(res.production_unchanged?'PASS':'FAIL')+' · '+esc(res.cleanup)+'</p><p class="small text-muted">Expected amounts use the independent normalized acceptance references at the server-resolved rates. No expected amounts or browser rates are fed into production computation.</p>';
      loadEmployeeRate();
    }).catch(function(err){if(version===state.cascadeVersion&&date===$('inpDate').value&&rateRequest===state.rateRequest)target.textContent=err.message;}).finally(function(){if(version===state.cascadeVersion)$('btnRunEmployeeScenario').disabled=!state.rateReady;});
  }

  // ---------- 4. Holiday Details ----------

  function lookupHoliday() {
    var date = $('inpDate').value;
    var holidayEl = $('holidayInfo');
    var restEl = $('scheduleRestInfo');
    var cutoffEl = $('cutoffInfo');
    if (!state.employeeId || !date) {
      holidayEl.textContent = 'Set a Date above to look up holiday information.';
      holidayEl.className = 'alert alert-secondary small mb-2';
      restEl.textContent = '';
      cutoffEl.textContent = '';
      state.currentHolidayType = null;
      return;
    }
    fetchJson(API + 'get_holiday_for_date.php?employee_id=' + encodeURIComponent(state.employeeId) + '&date=' + encodeURIComponent(date))
      .then(function (res) {
        if (!res || !res.success) {
          holidayEl.textContent = (res && res.message) || 'Failed to look up holiday information.';
          holidayEl.className = 'alert alert-danger small mb-2';
          state.currentHolidayType = null;
          return;
        }
        if (res.holiday) {
          holidayEl.innerHTML = '<strong>' + esc(res.holiday.name || 'Holiday') + '</strong> — ' +
            esc(res.holiday.type) +
            (res.holiday.region ? ' &middot; Region: ' + esc(res.holiday.region) : (res.holiday.is_nationwide ? ' &middot; Nationwide' : '')) +
            (res.holiday.pay_multiplier ? ' &middot; Pay Multiplier on file: ' + esc(res.holiday.pay_multiplier) + 'x' : '');
          holidayEl.className = 'alert alert-warning small mb-2';
          state.currentHolidayType = res.holiday.type || null;
        } else {
          holidayEl.textContent = 'Normal Workday — no holiday on file for this date.';
          holidayEl.className = 'alert alert-secondary small mb-2';
          state.currentHolidayType = null;
        }
        restEl.textContent = res.scheduled + ' for this employee on ' + date + '.';
        if (res.payroll_period) {
          cutoffEl.textContent = 'Payroll cutoff: ' + res.payroll_period.start + ' to ' + res.payroll_period.end +
            ' (Type ' + res.payroll_period.type + ', ' + res.payroll_period.half + ' half). Release: ' + (res.payroll_period.payday_display || res.payroll_period.payday || '—') + '.';
        } else {
          cutoffEl.textContent = 'Could not determine this employee\'s payroll cutoff for this date (no active deployment found).';
        }
      })
      .catch(function () {
        holidayEl.textContent = 'Network error while looking up holiday information.';
        holidayEl.className = 'alert alert-danger small mb-2';
      });
  }

  // ---------- 5. Overtime Details ----------

  function currentAttendanceHours() {
    var timeIn = $('inpTimeIn').value;
    var timeOut = $('inpTimeOut').value;
    if (!timeIn || !timeOut) return 0;
    var inMin = toMinutes(timeIn), outMin = toMinutes(timeOut);
    if (outMin <= inMin) outMin += 24 * 60; // overnight shift
    var worked = (outMin - inMin) - (state.breakMinutes || 0);
    return Math.max(0, worked / 60);
  }

  function toMinutes(hhmm) {
    var parts = hhmm.split(':');
    return (parseInt(parts[0], 10) * 60) + parseInt(parts[1], 10);
  }

  function updateOtAvailability() {
    var hours = currentAttendanceHours();
    var overEight = hours > 8.01;
    $('inpOtStart').disabled = overEight;
    $('inpOtEnd').disabled = overEight;
    $('btnSaveOt').disabled = overEight;
    var otResult = $('otResult');
    if (overEight) {
      showAlert(otResult, 'Disabled: the Attendance Details above already imply ' + hours.toFixed(2) +
        ' worked hours (>8). Reduce Time Out to an 8-hour day if you want to test a separate approved OT session instead.', true);
    } else {
      otResult.classList.add('d-none');
    }
  }

  function previewOtHours() {
    var start = $('inpOtStart').value, end = $('inpOtEnd').value;
    var preview = $('inpOtHoursPreview');
    if (!start || !end) { preview.value = ''; return; }
    var s = toMinutes(start), e = toMinutes(end);
    if (e <= s) e += 24 * 60;
    preview.value = ((e - s) / 60).toFixed(2) + ' hrs';
  }

  function renderOtList(list) {
    var tbody = $('otTableBody');
    if (!list.length) {
      tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-2">No test overtime sessions yet.</td></tr>';
      return;
    }
    tbody.innerHTML = list.map(function (o) {
      return '<tr><td>' + esc(o.date) + '</td><td>' + esc(o.ot_start || '—') + '</td><td>' + esc(o.ot_end || '—') + '</td>' +
        '<td>' + esc(o.hours != null ? o.hours : '—') + '</td>' +
        '<td><button type="button" class="btn btn-sm btn-outline-danger btn-delete-ot" data-id="' + o.overtime_id + '">Delete</button></td></tr>';
    }).join('');
    tbody.querySelectorAll('.btn-delete-ot').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (!window.confirm('Remove this test overtime session?')) return;
        fetchJson(API + 'save_test_overtime.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'delete', overtime_id: btn.getAttribute('data-id') })
        }).then(function (res) {
          if (!res || !res.success) { alert((res && res.message) || 'Failed to delete.'); return; }
          loadEmployeeContext(state.employeeId);
        }).catch(function () { alert('Network error while deleting.'); });
      });
    });
  }

  // ---------- 8. Payroll Verification ----------

  function findBreakdownForDate(breakdown, date) {
    if (!breakdown || !breakdown.length) return null;
    for (var i = 0; i < breakdown.length; i++) {
      if (breakdown[i].date === date) return breakdown[i];
    }
    return null;
  }

  function fetchPayrollVerification(url) {
    return fetch(url,{credentials:'same-origin'}).then(function(response){
      var status=response.status;
      var messages={404:'Payroll verification endpoint unavailable.',401:'Sign in as Admin to run payroll verification.',403:'Payroll verification requires authorized Admin access.',400:'Invalid employee or payroll date.',422:'Invalid employee, salary or payroll cutoff.',500:'Payroll computation/server error.'};
      return response.text().then(function(body){
        var data; try { data=JSON.parse(body); } catch (_) {
          throw new Error(messages[status] || 'Payroll verification returned an invalid server response.');
        }
        if (!response.ok || !data.success) throw new Error(messages[status] || 'Payroll verification failed.');
        if (!data.payroll || !data.payroll.earnings || !data.period || !data.employee ||
            ['gross_pay','total_deductions','net_pay'].some(function(key){return typeof data.payroll[key]!=='number' || !Number.isFinite(data.payroll[key]);}) ||
            Number(data.employee.employee_id)!==Number(state.employeeId)) throw new Error('Payroll verification returned incomplete or mismatched data.');
        return data;
      });
    },function(){throw new Error('Network error while running payroll verification. Check your connection and retry.');});
  }

  function runVerification() {
    var date = $('inpDate').value;
    var out = $('verifyResult');
    if (!state.employeeId || !date) {
      out.innerHTML = '<div class="alert alert-danger small">Select an employee and a Date first.</div>';
      return;
    }
    out.innerHTML = '<div class="text-muted small">Running the real payroll computation…</div>';

    fetchPayrollVerification(API + 'get_payroll_verification.php?employee_id=' + encodeURIComponent(state.employeeId) + '&date=' + encodeURIComponent(date))
      .then(function (res) {
        if (!res || !res.success) {
          out.innerHTML = '<div class="alert alert-danger small">' + esc((res && res.message) || 'Failed to run payroll verification.') + '</div>';
          return;
        }
        var p = res.payroll;
        var e = p.earnings || {};
        // "Overtime Pay" here folds together every OT source
        // payroll_compute_period() itself adds into gross pay: plain-day OT,
        // Sunday OT, approved Team-Lead OT sessions, AND holiday OT — since
        // the Expected panel only has one Overtime Pay line. Holiday
        // (non-OT) premium stays in its own Holiday Pay line.
        var overtimePay = (Number(e.regular_overtime) || 0) + (Number(e.sunday_overtime) || 0) +
          (Number(e.approved_overtime) || 0) + (Number(e.holiday_ot_pay) || 0);
        var dayHoliday = findBreakdownForDate(p.holiday_breakdown, date);
        var dayHolidayOt = findBreakdownForDate(p.holiday_ot_breakdown, date);

        function money(v) { return '₱' + (Number(v) || 0).toFixed(2); }
        function cmp(expectedId, actual) {
          var exp = parseFloat($(expectedId).value);
          if (isNaN(exp)) return '<span class="text-muted">no Expected value entered</span>';
          var match = Math.abs(exp - actual) < 0.01;
          return match
            ? '<span class="badge bg-success">MATCH</span>'
            : '<span class="badge bg-danger">MISMATCH</span> (expected ' + money(exp) + ')';
        }

        var html = '';
        html += '<p class="small mb-2"><strong>Cutoff:</strong> ' + esc(res.period.start) + ' to ' + esc(res.period.end) +
          ' &middot; <strong>Employee:</strong> ' + esc(res.employee.name) + ' (' + esc(res.employee.company) + ')</p>';

        html += '<table class="table table-sm table-bordered"><thead class="table-light"><tr><th></th><th>Actual (period total)</th><th>vs Expected</th></tr></thead><tbody>';
        html += '<tr><td>Regular Pay</td><td>' + money(e.basic_pay) + '</td><td>' + cmp('expRegular', e.basic_pay) + '</td></tr>';
        html += '<tr><td>Overtime Pay</td><td>' + money(overtimePay) + '</td><td>' + cmp('expOt', overtimePay) + '</td></tr>';
        html += '<tr><td>Holiday Pay</td><td>' + money(e.holiday_pay) + '</td><td>' + cmp('expHoliday', e.holiday_pay) + '</td></tr>';
        html += '<tr><td>Gross Pay</td><td>' + money(p.gross_pay) + '</td><td>' + cmp('expGross', p.gross_pay) + '</td></tr>';
        html += '<tr><td>Deductions</td><td>' + money(p.total_deductions) + '</td><td>' + cmp('expDeductions', p.total_deductions) + '</td></tr>';
        html += '<tr><td>Net Pay</td><td>' + money(p.net_pay) + '</td><td>' + cmp('expNet', p.net_pay) + '</td></tr>';
        html += '</tbody></table>';

        html += '<p class="small text-muted mb-1"><strong>Note:</strong> the row above is the TOTAL for the whole payroll cutoff, ' +
          'not just this one date — it will only line up cleanly with a single-day Expected figure if this test date is the ' +
          'only attendance record this employee has in this cutoff. For a precise day-level check, use the breakdown below instead.</p>';

        if (dayHoliday || dayHolidayOt) {
          html += '<p class="small mb-0"><strong>Day-specific breakdown for ' + esc(date) + ' (safe to compare 1:1 against Expected Holiday Pay):</strong><br>';
          if (dayHoliday) html += 'Holiday pay this date: ' + money(dayHoliday.pay) + ' (' + esc(dayHoliday.type || '') + ')<br>';
          if (dayHolidayOt) html += 'Holiday OT pay this date: ' + money(dayHolidayOt.pay);
          html += '</p>';
        }

        out.innerHTML = html;
      })
      .catch(function (error) {
        out.innerHTML = '<div class="alert alert-danger small">' + esc(error.message) + '</div>';
      });
  }

  // ---------- 2. Scenario presets ----------

  var SCENARIOS = {
    custom: null,
    t1: { timeIn: '08:00', timeOut: '17:00', ot: null, hint: 'Normal workday, no holiday needed — just pick any scheduled workday.' },
    t2: { timeIn: '08:00', timeOut: '17:00', ot: ['17:00', '19:00'], hint: 'Normal workday — pick any scheduled workday. 2 OT hours pre-filled below.' },
    t3: { timeIn: '', timeOut: '', ot: null, hint: 'Pick a Date that is a Regular/Legal Holiday in the Holidays table, and leave Time In/Out blank (Did Not Work).' },
    t4: { timeIn: '08:00', timeOut: '17:00', ot: null, hint: 'Pick a Date that is a Regular/Legal Holiday in the Holidays table.' },
    t5: { timeIn: '08:00', timeOut: '17:00', ot: ['17:00', '19:00'], hint: 'Pick a Date that is a Regular/Legal Holiday in the Holidays table.' },
    t6: { timeIn: '08:00', timeOut: '17:00', ot: null, hint: 'Pick a Date that is BOTH a Regular/Legal Holiday AND falls outside this employee\'s Working Days shown above (their scheduled rest day).' },
    t7: { timeIn: '', timeOut: '', ot: null, hint: 'Pick a Date that is a Special Non-Working Holiday. Leave Time In/Out blank (Did Not Work).' },
    t8: { timeIn: '08:00', timeOut: '17:00', ot: null, hint: 'Pick a Date that is a Special Non-Working Holiday.' },
    t9: { timeIn: '08:00', timeOut: '17:00', ot: ['17:00', '19:00'], hint: 'Pick a Date that is a Special Non-Working Holiday.' },
    t10: { timeIn: '08:00', timeOut: '17:00', ot: null, hint: 'Pick a Date that is BOTH a Special Non-Working Holiday AND falls outside this employee\'s Working Days shown above.' },
    t11: { timeIn: '08:00', timeOut: '17:00', ot: null, hint: 'Pick a Date that is a Special Working Day. Production applies no extra premium for this type — Expected Holiday Pay should be 0.' },
    t12: { timeIn: '', timeOut: '', ot: null, hint: 'Use the controlled holiday suite for an isolated double-holiday test. This manual preset never creates official holidays.', unsupported: true },
    t13: { timeIn: '08:00', timeOut: '17:00', ot: null, hint: 'Use the controlled holiday suite for an isolated double-holiday test. This manual preset never creates official holidays.', unsupported: true },
    t14: { timeIn: '08:00', timeOut: '17:00', ot: ['17:00', '19:00'], hint: 'Use the controlled holiday suite for an isolated double-holiday test. This manual preset never creates official holidays.', unsupported: true },
  };

  function applyScenario() {
    var key = $('selScenario').value;
    var warn = $('doubleHolidayWarning');
    var cfg = SCENARIOS[key];
    if (!cfg) { warn.classList.add('d-none'); return; }

    warn.classList.toggle('d-none', !cfg.unsupported);
    $('inpTimeIn').value = cfg.timeIn;
    $('inpTimeOut').value = cfg.timeOut;
    if (cfg.ot) {
      $('inpOtStart').value = cfg.ot[0];
      $('inpOtEnd').value = cfg.ot[1];
    } else {
      $('inpOtStart').value = '';
      $('inpOtEnd').value = '';
    }
    previewOtHours();
    updateOtAvailability();
    var otResult = $('otResult');
    if (cfg.hint) {
      otResult.textContent = cfg.hint;
      otResult.className = 'alert alert-info small mt-3';
      otResult.classList.remove('d-none');
    }
  }

  function renderRecords(records) {
    var tbody = $('recordsTableBody');
    if (!records.length) {
      tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">No attendance records yet for this employee.</td></tr>';
      return;
    }
    tbody.innerHTML = records.map(function (r) {
      var lockedNote = r.locked ? ' <span class="badge bg-secondary">Submitted — locked</span>' : '';
      var actions = r.locked
        ? '<span class="text-muted small">—</span>'
        : '<button type="button" class="btn btn-sm btn-outline-primary me-1 btn-edit-record" data-id="' + r.attendance_id + '" data-date="' + esc(r.date) + '" data-time-in="' + esc(r.time_in || '') + '" data-time-out="' + esc(r.time_out || '') + '">Edit</button>' +
          '<button type="button" class="btn btn-sm btn-outline-danger btn-delete-record" data-id="' + r.attendance_id + '">Delete</button>';
      return '<tr class="' + (r.locked ? 'record-locked' : '') + '">' +
        '<td>' + esc(r.date) + '</td>' +
        '<td>' + esc(r.time_in || '—') + '</td>' +
        '<td>' + esc(r.time_out || '—') + '</td>' +
        '<td>' + esc(r.status) + lockedNote + '</td>' +
        '<td>' + esc(r.minutes_late) + '</td>' +
        '<td>' + actions + '</td>' +
        '</tr>';
    }).join('');

    tbody.querySelectorAll('.btn-edit-record').forEach(function (btn) {
      btn.addEventListener('click', function () {
        $('inpDate').value = btn.getAttribute('data-date');
        $('inpTimeIn').value = btn.getAttribute('data-time-in');
        $('inpTimeOut').value = btn.getAttribute('data-time-out');
        $('btnSaveRecord').setAttribute('data-editing-id', btn.getAttribute('data-id'));
        $('btnSaveRecord').textContent = 'Update Record';
        window.scrollTo({ top: $('cardAddRecord').offsetTop - 20, behavior: 'smooth' });
      });
    });
    tbody.querySelectorAll('.btn-delete-record').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (!window.confirm('Delete this test attendance record? This cannot be undone.')) return;
        fetchJson(API + 'delete_attendance.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ attendance_id: btn.getAttribute('data-id') })
        }).then(function (res) {
          if (!res || !res.success) {
            alert((res && res.message) || 'Failed to delete.');
            return;
          }
          loadEmployeeContext(state.employeeId);
        }).catch(function () {
          alert('Network error while deleting.');
        });
      });
    });
  }

  function resetEmployeeAndBelow() {
    state.teamLeadId = null;
    state.employeeId = null;
    $('selEmployee').disabled = true;
    $('selEmployee').innerHTML = '<option value="">Select a Team Lead first</option>';
    resetRecordCards();
  }

  function resetRecordCards() {
    state.employeeId = null;state.rateReady=false;state.rateRequest++;state.dailyRate=0;state.hourlyRate=0;
    $('employeeRateCard').classList.add('d-none');$('employeeRateDetails').textContent='';$('employeeScenarioResult').textContent='';$('btnRunEmployeeScenario').disabled=true;
    state.currentHolidayType = null;
    $('scheduleInfo').classList.add('d-none');
    $('cardScenario').style.display = 'none';
    $('cardAddRecord').style.display = 'none';
    $('cardHoliday').style.display = 'none';
    $('cardOvertime').style.display = 'none';
    $('cardExpected').style.display = 'none';
    $('cardVerify').style.display = 'none';
    $('cardRecordsList').style.display = 'none';
    $('saveResult').classList.add('d-none');
    $('verifyResult').innerHTML = '';
    $('selScenario').value = 'custom';
    $('doubleHolidayWarning').classList.add('d-none');
    $('inpOtStart').value = '';
    $('inpOtEnd').value = '';
    $('inpOtHoursPreview').value = '';
  }

  function resetSaveForm() {
    $('inpDate').value = '';
    $('inpTimeIn').value = '';
    $('inpTimeOut').value = '';
    $('inpRemarks').value = '';
    $('btnSaveRecord').removeAttribute('data-editing-id');
    $('btnSaveRecord').textContent = 'Save Record';
  }

  function controlledMoney(value) { return '₱' + Number(value || 0).toFixed(2); }

  // The runner returns production results and fixed acceptance fixtures; no pay formula lives here.
  function renderControlledHolidayResults(res) {
    if (!res || !res.success) throw new Error((res && res.message) || 'Controlled tests could not complete.');
    var rows = Array.isArray(res.results) ? res.results : [];
    var html = '<p class="small">Scenario date: ' + esc(res.scenario_date) + ' · Cutoff: ' + esc(res.cutoff.join(' to ')) +
      '<br>Production records unchanged: <strong>' + (res.production_unchanged ? 'PASS' : 'FAIL') + '</strong> · ' + esc(res.cleanup) + '</p>';
    html += '<div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Scenario</th><th>Expected</th><th>Actual production</th><th>Difference</th><th>Result</th></tr></thead><tbody>';
    rows.forEach(function (r) {
      html += '<tr><td>' + esc(r.id + ' — ' + r.scenario) + '</td><td>' + controlledMoney(r.expected) + '</td><td>' + controlledMoney(r.actual) + '</td><td>' + controlledMoney(r.difference) + '</td><td><span class="badge ' + (r.status === 'PASS' ? 'bg-success' : 'bg-danger') + '">' + esc(r.status) + '</span></td></tr>';
    });
    html += '</tbody></table></div><h3 class="h6 mt-3">Production breakdown and pipeline evidence</h3>';
    rows.forEach(function (r) {
      var e = r.earnings || {};
      var c = r.classification || {};
      var failures = Object.keys(r.checks || {}).filter(function (key) { return r.checks[key] !== true; });
      html += '<details class="border rounded p-2 mb-2"><summary>' + esc(r.id + ' — ' + r.scenario) + '</summary>' +
        '<p class="small mt-2">Holiday: ' + esc(r.holiday_type || 'Normal day') + ' · Daily ' + controlledMoney(r.daily_rate) + ' · Hourly ' + controlledMoney(r.hourly_rate) +
        '<br>Hours worked: ' + esc(r.hours_worked) + ' · Holiday OT: ' + esc(r.holiday_ot_hours) + 'h · Scheduled rest day: ' + (r.rest_day ? 'Yes' : 'No') + ' · Eligibility: ' + esc(r.eligibility == null ? 'Not applicable' : r.eligibility) +
        '<br>Basic: ' + controlledMoney(e.basic_pay) + ' · Holiday premium / unworked entitlement: ' + controlledMoney(e.holiday_pay) + ' · Holiday OT: ' + controlledMoney(e.holiday_ot_pay) +
        '<br>Other earnings: ' + controlledMoney((Number(e.sunday_overtime)||0)+(Number(e.regular_overtime)||0)+(Number(e.approved_overtime)||0)+(Number(e.allowance)||0)) +
        '<br>Gross / total economic compensation: ' + controlledMoney(r.actual) +
        '<br>Team Lead holiday duty: ' + esc(c.holiday_work_hours) + 'h · Holiday OT: ' + esc(c.holiday_overtime_hours) + 'h' +
        '<br>Accounting snapshot, verification, component and double-counting checks: ' + esc(failures.length ? 'FAIL: ' + failures.join(', ') : 'PASS') + '</p></details>';
    });
    html += '<p class="small text-muted">This suite verifies actual HTTP endpoints and production computation in an isolated database. It does not issue real payslips or submit real employee payroll.</p>';
    $('controlledHolidayResult').innerHTML = html;
  }

  function runControlledHolidaySuite() {
    var button = $('btnRunHolidaySuite');
    button.disabled = true;
    $('controlledHolidayResult').textContent = 'Running isolated production pipeline checks. This may take up to two minutes…';
    return fetch(API + 'run_holiday_scenarios.php', { credentials: 'include', method: 'POST', headers: { 'Content-Type': 'application/json' }, body: '{}' })
      .then(function(response) {
        return response.text().then(function(text) {
          var result;
          try { result = JSON.parse(text); }
          catch (_) { throw new Error('HTTP ' + response.status + ': runner response was not valid JSON. Check the PHP development log.'); }
          if (!response.ok || !result.success) throw new Error('HTTP ' + response.status + ': ' + (result.message || 'Controlled runner failed.') + (result.stage ? ' [stage: ' + result.stage + ']' : ''));
          return result;
        });
      })
      .then(renderControlledHolidayResults)
      .catch(function (error) { $('controlledHolidayResult').textContent = error.message || 'Controlled holiday test failed.'; })
      .finally(function () { button.disabled = false; });
  }

  function bindEvents() {
    $('btnRunEmployeeScenario').addEventListener('click',runEmployeeScenario);
    $('inpDate').addEventListener('change',function(){if(state.employeeId){$('employeeScenarioResult').textContent='';loadEmployeeRate();}});
    $('btnRunHolidaySuite').addEventListener('click', runControlledHolidaySuite);
    $('selCompany').addEventListener('change', function () {
      state.cascadeVersion++; state.companyId=this.value || null; state.siteId=null; state.teamLeadId=null; state.deploymentId=null;
      resetEmployeeAndBelow(); $('selTeamLead').disabled=true; $('selTeamLead').innerHTML='<option value="">Select a site first</option>';
      $('selSite').disabled=true; $('selSite').innerHTML='<option value="">Select a company first</option>';
      if(state.companyId)loadSites(state.companyId);
    });
    $('selSite').addEventListener('change',function(){
      state.cascadeVersion++; state.siteId=this.value || null; state.teamLeadId=null; state.deploymentId=null;
      resetEmployeeAndBelow(); $('selTeamLead').disabled=true; $('selTeamLead').innerHTML='<option value="">Select a site first</option>';
      if(state.siteId)loadTeamLeads(state.companyId);
    });

    $('selTeamLead').addEventListener('change', function () {
      state.cascadeVersion++; state.deploymentId=null; resetEmployeeAndBelow();
      state.teamLeadId = this.value || null;
      if (state.teamLeadId) {
        loadEmployees(state.companyId, state.teamLeadId);
      } else {
        resetRecordCards();
        $('selEmployee').innerHTML = '<option value="">Select a Team Lead first</option>';
        $('selEmployee').disabled = true;
      }
    });

    $('selEmployee').addEventListener('change', function () {
      $('employeeScenarioResult').textContent='';
      state.cascadeVersion++; state.employeeId = this.value || null;
      state.deploymentId = this.selectedOptions[0] ? this.selectedOptions[0].getAttribute('data-deployment-id') : null;
      resetSaveForm();
      if (state.employeeId) {
        loadEmployeeContext(state.employeeId);loadEmployeeRate();
      } else {
        resetRecordCards();
      }
    });

    $('btnSaveRecord').addEventListener('click', function () {
      var editingId = this.getAttribute('data-editing-id');
      var date = $('inpDate').value;
      var timeIn = $('inpTimeIn').value;
      var timeOut = $('inpTimeOut').value;
      var remarks = $('inpRemarks').value;
      var resultEl = $('saveResult');

      if (!date || !timeIn) {
        showAlert(resultEl, 'Date and Time In are required.', true);
        return;
      }

      var body = editingId
        ? { action: 'update', attendance_id: editingId, date: date, time_in: timeIn, time_out: timeOut, remarks: remarks }
        : { action: 'create', employee_id: state.employeeId, date: date, time_in: timeIn, time_out: timeOut, remarks: remarks };

      fetchJson(API + 'save_attendance.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
      }).then(function (res) {
        if (!res || !res.success) {
          showAlert(resultEl, (res && res.message) || 'Failed to save.', true);
          return;
        }
        showAlert(resultEl, res.message + ' Computed status: ' + res.computed_status +
          (res.minutes_late ? (' (' + res.minutes_late + ' min late)') : '') +
          (res.undertime_minutes ? (', ' + res.undertime_minutes + ' min undertime') : ''), false);
        resetSaveForm();
        loadEmployeeContext(state.employeeId);
      }).catch(function () {
        showAlert(resultEl, 'Network error while saving.', true);
      });
    });

    $('inpDate').addEventListener('change', function () {
      lookupHoliday();
      updateOtAvailability();
    });
    $('inpTimeIn').addEventListener('change', updateOtAvailability);
    $('inpTimeOut').addEventListener('change', updateOtAvailability);
    $('inpOtStart').addEventListener('input', previewOtHours);
    $('inpOtEnd').addEventListener('input', previewOtHours);
    $('selScenario').addEventListener('change', applyScenario);
    $('btnRunVerify').addEventListener('click', runVerification);

    $('btnSaveOt').addEventListener('click', function () {
      var date = $('inpDate').value;
      var otStart = $('inpOtStart').value;
      var otEnd = $('inpOtEnd').value;
      var resultEl = $('otResult');
      if (!date || !otStart || !otEnd) {
        showAlert(resultEl, 'Date, Overtime Start and Overtime End are required.', true);
        return;
      }
      fetchJson(API + 'save_test_overtime.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'create', employee_id: state.employeeId, date: date, ot_start: otStart, ot_end: otEnd })
      }).then(function (res) {
        if (!res || !res.success) {
          showAlert(resultEl, (res && res.message) || 'Failed to save overtime session.', true);
          return;
        }
        showAlert(resultEl, res.message, false);
        $('inpOtStart').value = '';
        $('inpOtEnd').value = '';
        $('inpOtHoursPreview').value = '';
        loadEmployeeContext(state.employeeId);
      }).catch(function () {
        showAlert(resultEl, 'Network error while saving overtime session.', true);
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    bindEvents();
    loadCompanies();
  });
})();
