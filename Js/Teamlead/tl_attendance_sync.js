/**
 * Team Lead — real-time attendance roster, employee history modal, work site geofence registration.
 */
(function (global) {
  'use strict';

  var ROSTER_API = '../../Api/TeamLead/get_tl_attendance_roster.php';
  var REGISTER_SITE_API = '../../Api/TeamLead/register_work_site.php';
  var LOGS_API = '../../Api/TeamLead/get_employee_attendance_logs.php';

  var pollTimer = null;
  var pollMs = 10000;
  var lastRoster = [];

  function esc(s) {
    if (s == null) return '';
    var d = document.createElement('div');
    d.textContent = String(s);
    return d.innerHTML;
  }

  function statusBadgeClass(status) {
    var s = String(status || 'Absent');
    if (s === 'Present') return 'bg-success';
    if (s === 'Late') return 'bg-warning text-dark';
    if (s === 'Absent') return 'bg-danger';
    if (s === 'Rest Day') return 'bg-light text-dark border';
    if (s === 'On Leave') return 'bg-info text-dark';
    // Backward compatibility with any older raw DTR-state values.
    if (s === 'Clocked In') return 'bg-success';
    if (s === 'On Break') return 'bg-warning text-dark';
    if (s === 'Clocked Out') return 'bg-secondary';
    return 'bg-light text-dark border';
  }

  function statusBadgeHtml(status) {
    var s = String(status || 'Absent');
    return '<span class="badge rounded-pill ' + statusBadgeClass(s) + '">' + esc(s.toUpperCase()) + '</span>';
  }

  function fetchJson(url, options) {
    return fetch(url, options || { credentials: 'same-origin' })
      .then(function (r) { return r.json().catch(function () { return {}; }).then(function (d) { return { ok: r.ok, data: d }; }); });
  }

  function updateKpis(summary) {
    summary = summary || {};
    var total = document.getElementById('kpiTotalTeam');
    var clocked = document.getElementById('kpiClockedIn');
    var late = document.getElementById('kpiLate');
    var absent = document.getElementById('kpiAbsent');

    if (total) total.textContent = String(summary.total != null ? summary.total : '—');

    // Clocked In Today = employees with a valid clock-in today (Present + Late).
    // Late employees are counted here too, per spec — they showed up, just late.
    if (clocked) clocked.textContent = String(summary.clocked_in != null ? summary.clocked_in : '—');

    if (late) late.textContent = String(summary.late != null ? summary.late : '—');

    if (absent) absent.textContent = String(summary.absent != null ? summary.absent : '—');
  }

  function renderRosterTable(roster) {
    var body = document.getElementById('dashboardRosterBody');
    if (!body) return;

    roster = roster || [];
    if (!roster.length) {
      body.innerHTML = '<tr><td colspan="4" class="px-4 py-6 text-center text-slate-500">No deployed employees found.</td></tr>';
      return;
    }

    body.innerHTML = roster.map(function (row) {
      return (
        '<tr class="hover:bg-slate-50/50">' +
        '<td class="px-4 py-3">' +
        '<button type="button" class="btn btn-link p-0 text-start fw-semibold text-decoration-none tl-emp-name" data-employee-id="' + row.employee_id + '">' +
        esc(row.employee_name) + '</button></td>' +
        '<td class="px-4 py-3 text-slate-600">' + esc(row.position || '—') + '</td>' +
        '<td class="px-4 py-3">' + statusBadgeHtml(row.attendance_status || row.status) +
        (row.time_in_display && row.time_in_display !== '—' ? '<div class="small text-muted mt-1">In: ' + esc(row.time_in_display) + '</div>' : '') +
        '</td>' +
        '<td class="px-4 py-3 text-end">' +
        '<button type="button" class="btn btn-sm btn-outline-primary tl-btn-logs" data-employee-id="' + row.employee_id + '"><i class="bi bi-clock-history me-1"></i>Logs</button>' +
        '</td></tr>'
      );
    }).join('');

    body.querySelectorAll('.tl-emp-name').forEach(function (btn) {
      btn.addEventListener('click', function () {
        openEmployeeLogs(parseInt(btn.getAttribute('data-employee-id'), 10));
      });
    });
    body.querySelectorAll('.tl-btn-logs').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.stopPropagation();
        openEmployeeLogs(parseInt(btn.getAttribute('data-employee-id'), 10));
      });
    });
  }

  function loadAttendanceRoster() {
    return fetchJson(ROSTER_API).then(function (resp) {
      var data = resp.data || {};
      if (!resp.ok || !data.success) {
        var body = document.getElementById('dashboardRosterBody');
        if (body) {
          body.innerHTML = '<tr><td colspan="4" class="px-4 py-6 text-center text-danger">' +
            esc((data && data.message) || 'Failed to load attendance') + '</td></tr>';
        }
        return;
      }
      lastRoster = data.roster || [];
      updateKpis(data.summary);
      renderRosterTable(lastRoster);
      return data;
    });
  }

  function openEmployeeLogs(employeeId) {
    if (!employeeId) return;
    var modalEl = document.getElementById('tlAttendanceModal');
    var titleEl = document.getElementById('tlAttendanceModalTitle');
    var bodyEl = document.getElementById('tlAttendanceModalBody');
    if (!modalEl || !bodyEl) return;

    bodyEl.innerHTML = '<p class="text-muted mb-0">Loading duty logs…</p>';
    if (titleEl) titleEl.textContent = 'Employee Attendance History';

    var modal = global.bootstrap && bootstrap.Modal ? bootstrap.Modal.getOrCreateInstance(modalEl) : null;
    if (modal) modal.show();

    fetchJson(LOGS_API + '?employee_id=' + employeeId + '&limit=60').then(function (resp) {
      var data = resp.data || {};
      if (!resp.ok || !data.success) {
        bodyEl.innerHTML = '<div class="alert alert-danger mb-0">' + esc(data.message || 'Failed to load logs') + '</div>';
        return;
      }
      if (data.employee && titleEl) {
        titleEl.textContent = data.employee.employee_name + ' — Duty Logs';
      }
      var logs = data.logs || [];
      if (!logs.length) {
        bodyEl.innerHTML = '<p class="text-muted mb-0">No attendance records yet.</p>';
        return;
      }
      var employeeName = (data.employee && data.employee.employee_name) || 'Employee';
      bodyEl.innerHTML =
        '<div class="table-responsive"><table class="table table-sm table-striped align-middle mb-0">' +
        '<thead><tr><th>Date</th><th>Status</th><th>Time In</th><th>Break</th><th>Time Out</th><th>Hours</th><th>View</th></tr></thead><tbody>' +
        logs.map(function (log, idx) {
          return '<tr><td>' + esc(log.date) + '</td><td>' + statusBadgeHtml(log.status) + '</td>' +
            '<td>' + esc(log.time_in_display) + '</td><td>' + esc(log.break_time_display) + '</td>' +
            '<td>' + esc(log.time_out_display) + '</td><td>' +
            (log.total_hours_rendered != null ? esc(log.total_hours_rendered) : '—') + '</td>' +
            '<td><button type="button" class="btn btn-sm btn-outline-primary tl-btn-view-day" data-log-idx="' + idx + '"><i class="bi bi-eye me-1"></i>View</button></td></tr>';
        }).join('') + '</tbody></table></div>';

      bodyEl.querySelectorAll('.tl-btn-view-day').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var idx = parseInt(btn.getAttribute('data-log-idx'), 10);
          openAttendanceDayModal(logs[idx], employeeName);
        });
      });
    });
  }

  function photoUrl(path) {
    if (!path) return null;
    // Photo paths are stored relative to the project root (e.g.
    // "uploads/attendance_photos/clockin_43_....jpg"), same convention as
    // the rest of this project's uploaded-file paths.
    return '../../' + path;
  }

  function openAttendanceDayModal(log, employeeName) {
    var modalEl = document.getElementById('tlAttendanceDayModal');
    var titleEl = document.getElementById('tlAttendanceDayModalTitle');
    var bodyEl = document.getElementById('tlAttendanceDayModalBody');
    if (!modalEl || !bodyEl || !log) return;

    if (titleEl) titleEl.textContent = esc(employeeName) + ' — ' + esc(log.date);

    var clockInPhoto = photoUrl(log.clockin_photo);
    var clockOutPhoto = photoUrl(log.clockout_photo);

    bodyEl.innerHTML =
      '<div class="row g-3 mb-3 text-center">' +
      '  <div class="col-4"><div class="text-muted small">Time In</div><div class="fw-bold">' + esc(log.time_in_display) + '</div></div>' +
      '  <div class="col-4"><div class="text-muted small">Break</div><div class="fw-bold">' + esc(log.break_time_display) + '</div></div>' +
      '  <div class="col-4"><div class="text-muted small">Time Out</div><div class="fw-bold">' + esc(log.time_out_display) + '</div></div>' +
      '</div>' +
      '<div class="mb-2">' + statusBadgeHtml(log.status) +
      (log.total_hours_rendered != null ? ' <span class="text-muted small ms-2">' + esc(log.total_hours_rendered) + ' hrs</span>' : '') +
      (log.distance_from_site != null ? ' <span class="text-muted small ms-2">' + esc(log.distance_from_site) + 'm from site</span>' : '') +
      '</div>' +
      '<div class="row g-3">' +
      '  <div class="col-6 text-center">' +
      '    <div class="text-muted small mb-1">Clock-In Photo</div>' +
      (clockInPhoto
        ? '<a href="' + clockInPhoto + '" target="_blank"><img src="' + clockInPhoto + '" class="img-fluid rounded border" alt="Clock-in photo" style="max-height:220px;object-fit:cover;"></a>'
        : '<div class="border rounded d-flex align-items-center justify-content-center text-muted small" style="height:150px;">No photo</div>') +
      '  </div>' +
      '  <div class="col-6 text-center">' +
      '    <div class="text-muted small mb-1">Clock-Out Photo</div>' +
      (clockOutPhoto
        ? '<a href="' + clockOutPhoto + '" target="_blank"><img src="' + clockOutPhoto + '" class="img-fluid rounded border" alt="Clock-out photo" style="max-height:220px;object-fit:cover;"></a>'
        : '<div class="border rounded d-flex align-items-center justify-content-center text-muted small" style="height:150px;">No photo</div>') +
      '  </div>' +
      '</div>';

    var modal = global.bootstrap && bootstrap.Modal ? bootstrap.Modal.getOrCreateInstance(modalEl) : null;
    if (modal) modal.show();
  }

  function startPolling() {
    if (pollTimer) clearInterval(pollTimer);
    loadAttendanceRoster();
    pollTimer = setInterval(loadAttendanceRoster, pollMs);
  }

  function stopPolling() {
    if (pollTimer) {
      clearInterval(pollTimer);
      pollTimer = null;
    }
  }

  function hookDashboardView(showViewFn) {
    if (!showViewFn || showViewFn.__tlAttendanceHooked) return;
    var original = showViewFn;
    global.showView = function (viewId) {
      var result = original.apply(this, arguments);
      if (viewId === 'dashboard' || viewId === 'scanner' || viewId === 'team') {
        startPolling();
      } else {
        stopPolling();
      }
      return result;
    };
    global.showView.__tlAttendanceHooked = true;
    startPolling();
  }

  function init() {
    if (!document.getElementById('dashboardRosterBody')) return;

    if (global.showView) {
      hookDashboardView(global.showView);
    } else {
      var tries = 0;
      var t = setInterval(function () {
        tries++;
        if (global.showView) {
          clearInterval(t);
          hookDashboardView(global.showView);
        } else if (tries > 60) {
          clearInterval(t);
          startPolling();
        }
      }, 100);
    }
  }

  global.TlAttendanceSync = {
    loadRoster: loadAttendanceRoster,
    openEmployeeLogs: openEmployeeLogs,
    startPolling: startPolling,
    stopPolling: stopPolling
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})(window);

/* ==========================================================================
   WORK SITE GEOFENCE REGISTRATION ENGINE
   ========================================================================== */
var tlRegisteredLocation = null;
var tlWorkSiteCompanyId = null;
var tlLocationRequest = 0;
var tlLocationBusy = false;
var tlSiteSaving = false;
var tlSiteLoadRequest = 0;
var tlGpsWatch = null;
var tlGpsTimer = null;
var tlGpsProgressTimer = null;
var tlRegistrationPolicy = null;
function stopTlGpsCapture(){
    if(tlGpsWatch!==null&&navigator.geolocation&&navigator.geolocation.clearWatch)navigator.geolocation.clearWatch(tlGpsWatch);
    tlGpsWatch=null;
    if(tlGpsTimer!==null)clearTimeout(tlGpsTimer);
    tlGpsTimer=null;
    if(tlGpsProgressTimer!==null)clearInterval(tlGpsProgressTimer);
    tlGpsProgressTimer=null;
    var countdown=document.getElementById('tlGpsCountdown');if(countdown)countdown.textContent='';
}


function updateTlRegistrationQuality() {
    var limit=tlRegistrationPolicy?tlRegistrationPolicy.max_accuracy_meters:null;
    var location = tlRegisteredLocation;
    var valid = !!location && Number.isFinite(location.accuracy) && location.accuracy >= 0 && Number.isFinite(limit) && location.accuracy <= limit;
    var button = document.getElementById('btnConfirmRegisterSite');
    if (button) button.disabled = !valid || !tlWorkSiteCompanyId || tlLocationBusy || tlSiteSaving;
    var accuracyEl = document.getElementById('tlGpsAccuracyText');
    if (accuracyEl && location) accuracyEl.innerText = 'Best GPS accuracy: ±' + Math.round(location.accuracy) + ' meters' +
        (valid ? '' : ' — Registration requires ±'+limit+' meters or better.');
    return valid;
}

function selectTlWorkSite() {
    if(tlSiteSaving)return;
    tlWorkSiteCompanyId = Number(document.getElementById('tlWorkSiteCompany').value) || null;
    stopTlGpsCapture();
    ++tlLocationRequest;
    tlRegisteredLocation = null;
    tlLocationBusy = false;
    updateTlRegistrationQuality();
    loadWorkSiteDetails();
}

function openRegisterSiteModal() {
    var selected = document.getElementById('tlWorkSiteCompany');
    if (!tlWorkSiteCompanyId || tlSiteSaving) {
        showStatusModal('Select Work Site', 'Select an assigned official work site before registering its location.', false);
        return;
    }
    document.getElementById('tlRegisterSiteName').innerText = selected.options[selected.selectedIndex].text;
    var modalEl = document.getElementById('registerSiteModal');
    if (!modalEl) return;
    var modal = window.bootstrap && bootstrap.Modal ? bootstrap.Modal.getOrCreateInstance(modalEl) : null;
    document.getElementById('tlGeofenceRadius').value=tlLoadedSiteRadius||100;
    if (modal) modal.show();
    acquireTlLocation();
}

function acquireTlLocation() {
    if(tlSiteSaving)return;
    stopTlGpsCapture();
    var request=++tlLocationRequest;
    var statusEl=document.getElementById('tlCurrentGpsStatus');
    var accuracyEl=document.getElementById('tlGpsAccuracyText');
    tlRegisteredLocation=null;tlLocationBusy=true;updateTlRegistrationQuality();
    if(statusEl)statusEl.innerText='Getting fresh GPS at the selected work site...';
    if(accuracyEl)accuracyEl.innerText='Best GPS accuracy: waiting';
    ['tlGpsCurrentReading','tlGpsCurrentAccuracy'].forEach(function(id){var el=document.getElementById(id);if(el)el.textContent=id==='tlGpsCurrentReading'?'Current reading: waiting':'Current GPS accuracy: waiting';});
    if(!tlRegistrationPolicy){tlLocationBusy=false;if(statusEl)statusEl.innerText='Registration policy unavailable. Close this dialog and refresh sites.';return;}
    var duration=tlRegistrationPolicy.acquisition_seconds;
    var deadline=Date.now()+duration*1000;
    var policyText=document.getElementById('tlGpsPolicyText');if(policyText)policyText.textContent='Registration requires GPS accuracy of ±'+tlRegistrationPolicy.max_accuracy_meters+' m or better. This does not change the geofence radius.';
    if(!navigator.geolocation){tlLocationBusy=false;if(statusEl)statusEl.innerText='Geolocation is not supported by your browser.';return;}
    var watching=typeof navigator.geolocation.watchPosition==='function';
    function guidance(){return 'Enable precise location in your device settings and allow this browser location access. If this computer only provides approximate location, use a GPS-enabled phone at the official site.';}
    function received(position){
        if(request!==tlLocationRequest)return;
        var lat=position.coords.latitude,lng=position.coords.longitude,accuracy=position.coords.accuracy;
        if(!Number.isFinite(lat)||!Number.isFinite(lng)||Math.abs(lat)>90||Math.abs(lng)>180||(lat===0&&lng===0)||!Number.isFinite(accuracy)||accuracy<0){if(statusEl)statusEl.innerText='The device returned an invalid location. Retry GPS.';return;}
        // Keep the best fresh observation while the device improves its fix.
        if(!tlRegisteredLocation||accuracy<tlRegisteredLocation.accuracy)tlRegisteredLocation={latitude:lat,longitude:lng,accuracy:accuracy};
        var current=document.getElementById('tlGpsCurrentReading');if(current)current.textContent='Current reading: '+lat.toFixed(6)+', '+lng.toFixed(6);
        var currentAccuracy=document.getElementById('tlGpsCurrentAccuracy');if(currentAccuracy)currentAccuracy.textContent='Current GPS accuracy: ±'+Math.round(accuracy)+' meters';
        var precise=tlRegisteredLocation.accuracy<=tlRegistrationPolicy.max_accuracy_meters;
        tlLocationBusy=watching&&!precise;
        if(precise)stopTlGpsCapture();
        if(statusEl)statusEl.innerText=(precise?'Ready to register — best location: ':(watching?'Improving location accuracy — best location: ':'Location accuracy is still too low — best location: '))+tlRegisteredLocation.latitude.toFixed(6)+', '+tlRegisteredLocation.longitude.toFixed(6);
        updateTlRegistrationQuality();
        if(!precise&&accuracyEl)accuracyEl.innerText='Best GPS accuracy: ±'+Math.round(tlRegisteredLocation.accuracy)+' meters — Registration requires ±'+tlRegistrationPolicy.max_accuracy_meters+' m or better. '+(watching?'Collecting fresh readings. ':'')+guidance();
    }
    function failed(error){
        if(request!==tlLocationRequest)return;
        stopTlGpsCapture();++tlLocationRequest;tlLocationBusy=false;if(error.code===1)tlRegisteredLocation=null;updateTlRegistrationQuality();
        if(statusEl)statusEl.innerText=error.code===1?'Location permission is required.':(error.code===3?'Unable to obtain a sufficiently accurate location within the allowed time. Retry GPS.':'Location is currently unavailable.');
        if(accuracyEl)accuracyEl.innerText=(tlRegisteredLocation?'Best GPS accuracy: ±'+Math.round(tlRegisteredLocation.accuracy)+' meters. ':'')+guidance();
    }
    var options={enableHighAccuracy:true,timeout:duration*1000,maximumAge:0};
    if(watching){
        tlGpsWatch=navigator.geolocation.watchPosition(received,failed,options);
        function progress(){var el=document.getElementById('tlGpsCountdown');if(el)el.textContent='Improving GPS accuracy… '+Math.max(0,Math.ceil((deadline-Date.now())/1000))+' seconds remaining';}
        progress();tlGpsProgressTimer=setInterval(progress,1000);
        tlGpsTimer=setTimeout(function(){
            if(request!==tlLocationRequest)return;
            stopTlGpsCapture();++tlLocationRequest;tlLocationBusy=false;updateTlRegistrationQuality();
            if(statusEl)statusEl.innerText='Location accuracy is still too low. Retry GPS or use a GPS-enabled device.';
            if(accuracyEl)accuracyEl.innerText=(tlRegisteredLocation?'Best accuracy: ±'+Math.round(tlRegisteredLocation.accuracy)+' meters. ':'')+guidance();
        },duration*1000);
    }else navigator.geolocation.getCurrentPosition(received,failed,options);
}

function submitSiteRegistration() {
  if (tlLocationBusy || tlSiteSaving) return;
  if (!tlRegisteredLocation || !tlWorkSiteCompanyId || !updateTlRegistrationQuality()) {
    showStatusModal("GPS Error", "GPS position has not been acquired yet.", false);
      return;
  }

  var radiusEl = document.getElementById('tlGeofenceRadius');
  var radiusVal = radiusEl ? parseInt(radiusEl.value, 10) : 100;

  if (isNaN(radiusVal) || radiusVal < 30 || radiusVal > 1000) {
    showStatusModal("Invalid Radius", "Please enter a valid radius between 30 and 1000 meters.", false);
      return;
  }

  var btnSave = document.getElementById('btnConfirmRegisterSite');
  tlSiteSaving = true;
  var siteSelect=document.getElementById('tlWorkSiteCompany');if(siteSelect)siteSelect.disabled=true;
  if (btnSave) {
      btnSave.disabled = true;
      btnSave.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving Work Site...';
  }

  var payload = {
      latitude: tlRegisteredLocation.latitude,
      longitude: tlRegisteredLocation.longitude,
      accuracy: tlRegisteredLocation.accuracy,
      geofence_radius: radiusVal,
      site_id: tlWorkSiteCompanyId
  };

  fetch('../../Api/TeamLead/register_work_site.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(payload)
  })
  .then(function (res) {
      return res.text().then(function (text) {
          try {
              return JSON.parse(text);
          } catch (e) {
              throw new Error('Unable to register the work site. Please retry.');
          }
      });
  })
  .then(function (data) {
    tlSiteSaving = false;
    if(siteSelect)siteSelect.disabled=false;
    if (btnSave) {
      btnSave.disabled = false;
      btnSave.innerHTML = 'Save Work Site Coordinates';
  }
  
  if (data.success) {
      // Update the aggregate badge immediately, without waiting for OK or the poll timer.
      if (typeof window.pollRealtimeCounters === "function") window.pollRealtimeCounters();
      loadWorkSiteDetails();
      showStatusModal("Success!", data.message, true, true);
  } else {
      showStatusModal("Registration Failed", data.message, false, false);
  }})
  .catch(function (err) {
    tlSiteSaving = false;
    if(siteSelect)siteSelect.disabled=false;
    if (btnSave) {
      btnSave.disabled = false;
      btnSave.innerHTML = 'Save Work Site Coordinates';
  }
  showStatusModal("Server Error", err.message || "Failed to register work site location.", false, false);});
}
function showStatusModal(title, message, isSuccess = true, shouldReload = false) {
  // 1. Hide the active "Register Work Site" modal so popups don't overlap
  var regModalEl = document.getElementById('registerSiteModal');
  if (regModalEl) {
      var regModal = bootstrap.Modal.getInstance(regModalEl);
      if (regModal) regModal.hide();
  }

  // 2. Select HTML elements inside the status modal
  var titleEl = document.getElementById('statusModalTitle');
  var msgEl = document.getElementById('statusModalMessage');
  var iconEl = document.getElementById('statusModalIcon');
  var okBtn = document.getElementById('btnStatusModalOk');

  // 3. Inject dynamic text passed to the function
  if (titleEl) titleEl.innerText = title;
  if (msgEl) msgEl.innerText = message;
  
  // 4. Dynamically inject a Green Checkmark (Success) or Red Exclamation (Error) icon
  if (iconEl) {
      iconEl.innerHTML = isSuccess 
          ? '<i class="bi bi-check-circle-fill text-success" style="font-size: 3.5rem;"></i>' 
          : '<i class="bi bi-exclamation-circle-fill text-danger" style="font-size: 3.5rem;"></i>';
  }

  // 5. Configure what the "OK" button does when clicked
  if (okBtn) {
    okBtn.onclick = function () {
        var statusModalEl = document.getElementById('statusModal');
        var statusModal = bootstrap.Modal.getInstance(statusModalEl);
        if (statusModal) statusModal.hide();

        // Refresh live work site details on screen
        if (shouldReload) {
            if (typeof loadWorkSiteDetails === 'function') {
                loadWorkSiteDetails();
            } else {
                location.reload();
            }
        }
    };
}

  // 6. Launch the Bootstrap modal on screen using JS API
  var statusModal = new bootstrap.Modal(document.getElementById('statusModal'));
  statusModal.show();
}

window.openRegisterSiteModal = openRegisterSiteModal;
window.submitSiteRegistration = submitSiteRegistration;
window.selectTlWorkSite = selectTlWorkSite;
window.openTlWorkSite = function(siteId) {
    if(tlSiteSaving)return;
    stopTlGpsCapture();
    ++tlLocationRequest;
    tlWorkSiteCompanyId=Number(siteId)||null;
    tlRegisteredLocation=null;
    tlLocationBusy=false;
    loadWorkSiteDetails();
};
document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('tlGeofenceRadius').addEventListener('input', updateTlRegistrationQuality);
    document.getElementById('registerSiteModal').addEventListener('hidden.bs.modal', function () {
        ++tlLocationRequest; tlRegisteredLocation=null; tlLocationBusy=false; updateTlRegistrationQuality();
    });
});
/* ==========================================================================
   LIVE WORK SITE DISPLAY ENGINE
   ========================================================================== */
var tlLoadedSiteRadius=100;
function loadWorkSiteDetails() {
    var coordsEl=document.getElementById('registeredSiteCoords');
    if(!coordsEl||tlSiteSaving)return;
    var select=document.getElementById('tlWorkSiteCompany');
    var radiusEl=document.getElementById('registeredGeofenceRadius');
    var badge=document.getElementById('siteRegStatusBadge');
    var address=document.getElementById('tlWorkSiteAddress');
    var register=document.getElementById('btnOpenRegisterSite');
    var request=++tlSiteLoadRequest;
    var requestedCompany=tlWorkSiteCompanyId;
    if(register)register.disabled=true;
    if(select)select.disabled=true;
    if(address)address.textContent='';
    coordsEl.innerText='Loading site coordinates...';
    if(radiusEl)radiusEl.innerText='Not Set';
    if(badge){badge.textContent='Loading site';badge.className='badge bg-secondary';}
    fetch('../../Api/TeamLead/get_work_site.php'+(requestedCompany?'?site_id='+encodeURIComponent(requestedCompany):''),{credentials:'same-origin',cache:'no-store'})
    .then(function(res){return res.json().then(function(data){if(!res.ok||!data.success)throw Error(data.message||'Unable to load assigned sites.');return data;});})
    .then(function(data){
        if(request!==tlSiteLoadRequest)return;
        var policy=data.registration_policy;
        tlRegistrationPolicy=policy&&Number.isFinite(policy.max_accuracy_meters)&&policy.max_accuracy_meters>0&&Number.isFinite(policy.acquisition_seconds)&&policy.acquisition_seconds>0?policy:null;
        var companies=Array.isArray(data.companies)?data.companies:[];
        if(select){select.replaceChildren(new Option(companies.length?'Select official work site':'No assigned work sites',''));companies.forEach(function(c){select.add(new Option(c.CompanyName+' — '+c.Name,String(c.SiteID)));});select.disabled=!companies.length;}
        tlWorkSiteCompanyId=Number(data.site_id)||null;
        if(select)select.value=tlWorkSiteCompanyId?String(tlWorkSiteCompanyId):'';
        tlLoadedSiteRadius=Number(data.geofence_radius)||100;
        var radiusInput=document.getElementById('tlGeofenceRadius');if(radiusInput)radiusInput.value=tlLoadedSiteRadius;
        if(address)address.textContent=data.address||'';
        var registered=tlWorkSiteCompanyId&&Number.isFinite(data.latitude)&&Number.isFinite(data.longitude);
        coordsEl.innerText=registered?data.latitude.toFixed(6)+', '+data.longitude.toFixed(6):(data.message||'No work site coordinates registered yet.');
        if(radiusEl)radiusEl.innerText=registered?tlLoadedSiteRadius+' Meters':'Not Set';
        if(badge){badge.textContent=registered?'Active Site':(tlWorkSiteCompanyId?'Location Not Registered':(companies.length?'Select a Site':'No Assigned Sites'));badge.className=registered?'badge bg-success':'badge bg-warning text-dark';}
        if(register){register.disabled=!tlWorkSiteCompanyId||!tlRegistrationPolicy;register.innerHTML='<i class="bi bi-pin-map-fill me-1"></i>'+(registered?'Update Location':'Register Location');}
    })
    .catch(function(error){
        if(request!==tlSiteLoadRequest)return;
        tlWorkSiteCompanyId=null;
        if(select){select.replaceChildren(new Option('Unable to load sites — click Refresh sites',''));select.disabled=true;}
        coordsEl.innerText=error.message||'Failed to load coordinates';
        if(radiusEl)radiusEl.innerText='Not Set';
        if(badge){badge.textContent='Site Unavailable';badge.className='badge bg-warning text-dark';}
    });
}

var tlGpsModal=document.getElementById('registerSiteModal');
if(tlGpsModal)tlGpsModal.addEventListener('hidden.bs.modal',function(){stopTlGpsCapture();++tlLocationRequest;tlLocationBusy=false;tlRegisteredLocation=null;updateTlRegistrationQuality();});
window.loadWorkSiteDetails = loadWorkSiteDetails;

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', loadWorkSiteDetails);
} else {
    loadWorkSiteDetails();
}
