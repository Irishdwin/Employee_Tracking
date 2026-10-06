/**
 * Employee dashboard — live attendance status + historical logs & robust GPS engine.
 */
(function (global) {
    'use strict';
  
    var API = '../../Api/Employee/get_employee_attendance.php';
    var pollTimer = null;
    var pollMs = 10000;
    var lastPayload = null;
  
    function esc(s) {
      if (s == null) return '';
      var d = document.createElement('div');
      d.textContent = String(s);
      return d.innerHTML;
    }
  
    function attendanceStatusBadgeClass(status) {
      var s = String(status || 'No record');
      if (s === 'Present' || s === 'Clocked In') return 'bg-success';
      if (s === 'Late' || s === 'On Break') return 'bg-warning text-dark';
      if (s === 'Absent' || s === 'Rejected') return 'bg-danger';
      if (s === 'Rest Day') return 'bg-secondary';
      if (s === 'On Leave') return 'bg-info text-dark';
      if (s === 'Clocked Out') return 'bg-primary';
      return 'bg-light text-dark border';
    }
  
    function setText(id, val) {
      var el = document.getElementById(id);
      if (el) el.textContent = val != null && val !== '' ? String(val) : '—';
    }
  
    function setUndertime(id, minutes) {
      var el = document.getElementById(id);
      if (!el) return;
      if (minutes != null && minutes > 0) {
        el.textContent = 'Undertime: ' + minutes + ' min';
        el.classList.remove('hidden');
      } else {
        el.textContent = '—';
        el.classList.add('hidden');
      }
    }

    // Same "Holiday" badge color (#7c3aed) already used for this purpose in
    // Html/Accounting/accounting_dashboard.html and
    // Html/TeamLead/team_lead_dashboard.html — reused here, not a new color.
    function holidayBadgeHtml(holiday) {
      if (!holiday) return '';
      var title = esc(holiday.name || 'Holiday') + (holiday.type ? ' (' + esc(holiday.type) + ')' : '');
      return ' <span class="badge" style="background:#7c3aed;color:#fff;" title="' + title + '">' + esc(holiday.name || 'Holiday') + '</span>';
    }

    function renderToday(today) {
      if (!today) return;
      var badge = document.getElementById('empTodayStatusBadge');
      var displayStatus = today.attendance_status || today.status || 'No record';
      if (badge) {
        badge.textContent = displayStatus;
        badge.className = 'badge rounded-pill ' + attendanceStatusBadgeClass(displayStatus);
      }
      setText('empTodayTimeIn', today.time_in_display);
      setText('empTodayBreak', today.break_time_display);
      setText('empTodayTimeOut', today.time_out_display);
      var expected = today.expected_schedule;
      setText('empExpectedScheduleToday', expected ? (expected.is_working_day
        ? 'Expected today: ' + expected.schedule_type + ' · ' + expected.time_in_display + '–' + expected.time_out_display +
          (expected.overnight ? ' (next day)' : '') + ' · Break: ' + expected.break_duration + ' min · Grace: ' + expected.grace_period + ' min'
        : 'Expected today: Rest Day') : 'No assigned schedule found for today.');
      var hours = today.total_hours != null ? today.total_hours : today.total_hours_rendered;
      setText('empTodayHours', hours != null ? hours + ' hrs total' : '—');
      setUndertime('empTodayUndertime', today.undertime_minutes);
      var dateEl = document.getElementById('empTodayDate');
      if (dateEl) dateEl.innerHTML = esc(today.date || new Date().toISOString().slice(0, 10)) + holidayBadgeHtml(today.holiday);
    }
  
    function buildLogRow(log, includeLateCol) {
      var attStatus = log.attendance_status || log.status || '—';
      var hours = log.total_hours != null ? log.total_hours : log.total_hours_rendered;
      var breakLabel = log.break_time_display || '—';
      var row =
        '<tr><td>' + esc(log.date) + holidayBadgeHtml(log.holiday) + '</td>' +
        '<td><span class="badge rounded-pill ' + attendanceStatusBadgeClass(attStatus) + '">' + esc(attStatus) + '</span></td>' +
        '<td>' + esc(log.time_in_display) + '</td>' +
        '<td>' + esc(breakLabel) + '</td>' +
        '<td>' + esc(log.time_out_display) + '</td>' +
        '<td class="fw-semibold">' + (hours != null ? esc(hours) + ' hrs' : '—') + '</td>';
      if (includeLateCol) {
        var badges = '';
        if (log.is_late) badges += '<span class="text-warning-emphasis small fw-semibold">Late</span>';
        if (log.undertime_minutes != null && log.undertime_minutes > 0) {
          if (badges) badges += ' ';
          badges += '<span class="text-amber-700 small fw-semibold">Undertime ' + esc(log.undertime_minutes) + 'm</span>';
        }
        row += '<td>' + (badges || '—') + '</td>';
        row += '<td><button type="button" class="btn btn-sm btn-outline-primary" data-attendance-evidence-date="' + esc(log.date) + '">View Evidence</button></td>';
      }
      return row + '</tr>';
    }
  
    function renderLogs(logs) {
      var tbody = document.getElementById('empAttendanceLogsBody');
      var timesheetBody = document.getElementById('empTimesheetLogsBody');
      logs = logs || [];
      var empty6 = '<tr><td colspan="6" class="text-center text-muted py-3">No duty logs recorded yet.</td></tr>';
      var empty7 = '<tr><td colspan="8" class="text-center text-muted py-3">No duty logs recorded yet.</td></tr>';
      if (tbody) {
        tbody.innerHTML = logs.length ? logs.map(function (log) { return buildLogRow(log, false); }).join('') : empty6;
      }
      if (timesheetBody) {
        timesheetBody.innerHTML = logs.length ? logs.map(function (log) { return buildLogRow(log, true); }).join('') : empty7;
      }
    }
  
    function overtimeStatusBadgeClass(status) {
      var s = String(status || 'Pending');
      if (s === 'Approved' || s === 'Completed') return 'bg-success';
      if (s === 'Active') return 'bg-primary';
      if (s === 'Pending') return 'bg-warning text-dark';
      if (s === 'Rejected') return 'bg-danger';
      return 'bg-light text-dark border';
    }

    function renderOvertime(overtime) {
      var tbody = document.getElementById('empOvertimeLogsBody');
      if (!tbody) return;
      overtime = overtime || [];
      if (!overtime.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">No overtime recorded.</td></tr>';
        return;
      }
      tbody.innerHTML = overtime.map(function (ot) {
        return '<tr><td>' + esc(ot.date) + '</td>' +
          '<td class="text-muted small">Regular duty per schedule</td>' +
          '<td>' + esc(ot.ot_start_display) + '</td>' +
          '<td>' + esc(ot.ot_end_display) + '</td>' +
          '<td class="fw-semibold">' + (ot.ot_hours != null ? esc(ot.ot_hours) + ' hrs' : '—') + '</td>' +
          '<td><span class="badge rounded-pill ' + overtimeStatusBadgeClass(ot.status) + '">' + esc(ot.status) + '</span></td></tr>';
      }).join('');
    }

    function loadEmployeeAttendance() {
      return fetch(API, { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data || !data.success) return;
          var card = document.getElementById('empAttendanceCard');
          if (card && data.has_employee === false) {
            card.classList.add('d-none');
            return;
          }
          if (card) card.classList.remove('d-none');
          renderToday(data.today);
          renderLogs(data.logs);
          renderOvertime(data.overtime);
          var sync = document.getElementById('empAttendanceLastSync');
          if (sync) sync.textContent = 'Updated ' + new Date().toLocaleTimeString();
          lastPayload = data;
          try {
            global.dispatchEvent(new CustomEvent('employee-attendance-updated', { detail: data }));
          } catch (e) {}
          return data;
        })
        .catch(function (err) {
          console.warn('employee attendance error:', err);
        });
    }
  
    function startPolling() {
      if (pollTimer) clearInterval(pollTimer);
      loadEmployeeAttendance();
      pollTimer = setInterval(loadEmployeeAttendance, pollMs);
    }
  
    global.EmployeeAttendanceSync = {
      load: loadEmployeeAttendance,
      startPolling: startPolling,
      getLastPayload: function () { return lastPayload; }
    };
  
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', startPolling);
    } else {
      startPolling();
    }
  })(window);
  
  /* ==========================================================================
     LIVE EMPLOYEE GPS + GEOFENCE + CAMERA ATTENDANCE
     --------------------------------------------------------------------------
     The Employee device supplies the live coordinates. The Team Lead's
     registered site is the reference point. This section intentionally keeps
     the existing dashboard attendance-sync code above intact.
     ========================================================================== */

  var currentGpsLocation = null;
  var currentAction = null;
  var cameraStream = null;
  var selfieBase64Data = null;
  var hudIntervalTimer = null;
  var watchPositionId = null;
  var gpsRequestToken = 0;
  var gpsStarting = false;
  var workSiteLoadPromise = null;
  var workSitePollTimer = null;
  var workSitePollMs = 4000; // near-real-time refresh of the Team Lead's registered geofence
  var workSiteError = '';
  var gpsFailure = null;
  window.employeeWorkSite = null;
  window.currentEmployeeCoords = null;

  function calculateHaversine(lat1, lon1, lat2, lon2) {
      var R = 6371000;
      var dLat = (Number(lat2) - Number(lat1)) * Math.PI / 180;
      var dLon = (Number(lon2) - Number(lon1)) * Math.PI / 180;
      var a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
          Math.cos(Number(lat1) * Math.PI / 180) *
          Math.cos(Number(lat2) * Math.PI / 180) *
          Math.sin(dLon / 2) * Math.sin(dLon / 2);
      return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
  }
  window.calculateHaversine = calculateHaversine;

  function validCoordinate(value, min, max) {
      if (value === null || value === undefined || String(value).trim() === '') return false;
      var n = Number(value);
      return Number.isFinite(n) && n >= min && n <= max;
  }

  function isValidGpsPosition(position) {
      return !!(
          position &&
          position.coords &&
          validCoordinate(position.coords.latitude, -90, 90) &&
          validCoordinate(position.coords.longitude, -180, 180) &&
          !(Number(position.coords.latitude) === 0 && Number(position.coords.longitude) === 0)
      );
  }

  function setGpsMessage(message, type) {
      var badge = document.getElementById('geofenceBadge');
      var text = document.getElementById('gpsDistanceText');
      var classes = {
          warning: 'badge bg-warning text-dark mb-1',
          danger: 'badge bg-danger mb-1',
          success: 'badge bg-success mb-1',
          secondary: 'badge bg-secondary mb-1'
      };
      if (badge) {
          badge.className = classes[type || 'secondary'];
          badge.innerHTML = message;
      }
      if (text && type !== 'success') {
          text.textContent = message.replace(/<[^>]*>/g, '');
      }
  }

  function setAttendanceButtons(enabled) {
      var btnIn = document.getElementById('btnClockIn');
      var btnOut = document.getElementById('btnClockOut');
      if (btnIn) btnIn.disabled = !enabled;
      if (btnOut) btnOut.disabled = !enabled;
  }

  function getDistanceFromSite() {
      if (!currentGpsLocation || !window.employeeWorkSite) return null;
      var site = window.employeeWorkSite;
      if (!validCoordinate(site.latitude, -90, 90) ||
          !validCoordinate(site.longitude, -180, 180)) {
          return null;
      }
      return calculateHaversine(
          currentGpsLocation.latitude,
          currentGpsLocation.longitude,
          Number(site.latitude),
          Number(site.longitude)
      );
  }

  function updateGpsUi() {
      var badge = document.getElementById('geofenceBadge');
      var text = document.getElementById('gpsDistanceText');

      var title=document.getElementById('employeeTodayShiftTitle');
      if(title && window.employeeWorkSite)title.textContent="Today's Shift: "+window.employeeWorkSite.site_name;
      var context=document.getElementById('employeeWorkSiteSummary');
      if(context) context.textContent=window.employeeWorkSite
          ? 'Work Site: '+window.employeeWorkSite.site_name+' · '+(window.employeeWorkSite.is_registered
              ? 'Registered · Geofence: '+window.employeeWorkSite.geofence_radius+' meters'
              : 'Geofence not registered. Please contact your Team Lead.')
          : workSiteError || 'Loading assigned work site...';
      if (!currentGpsLocation) {
          setAttendanceButtons(false);
          if(badge) { badge.className='badge '+(gpsFailure?'bg-danger':'bg-warning text-dark')+' mb-1';
              badge.textContent=gpsFailure ? ({1:'Location Permission Denied',2:'Location Unavailable',3:'GPS Timeout'}[gpsFailure.code]||'GPS Unavailable') : gpsStarting?'Acquiring Live GPS...':'GPS Not Ready'; }
          if(text)text.textContent=gpsFailure?gpsErrorMessage(gpsFailure):'Requesting your device location. Please allow browser location access.';
          return;
      }

      var dist = getDistanceFromSite();

      if (dist === null) {
          if (window.employeeWorkSite && window.employeeWorkSite.is_registered === false) {
              if (badge) {
                  badge.className = 'badge bg-warning text-dark mb-1';
                  badge.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i>Site Not Registered';
              }
              if (text) text.textContent = 'Your Team Lead must register the work site before attendance.';
          } else {
              if (badge) {
                  badge.className = 'badge bg-warning text-dark mb-1';
                  badge.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i>Work Site Unavailable';
              }
              if (text) {
                  text.textContent =
                      'Lat: ' + currentGpsLocation.latitude.toFixed(6) +
                      ', Lng: ' + currentGpsLocation.longitude.toFixed(6) +
                      ' (±' + Math.round(currentGpsLocation.accuracy || 0) + 'm). Unable to verify the assigned work site. Retry GPS.';
              }
          }
          setAttendanceButtons(false);
          return;
      }

      var radius = Number(window.employeeWorkSite.geofence_radius || 100);
      var inside = dist <= radius;
      var lowAccuracy = currentGpsLocation.accuracy === null || currentGpsLocation.accuracy > radius;

      if (badge) {
          badge.className = !inside ? 'badge bg-danger mb-1' : lowAccuracy ? 'badge bg-warning text-dark mb-1' : inside
              ? 'badge bg-success mb-1'
              : 'badge bg-danger mb-1';
          badge.innerHTML = !inside ? '<i class="bi bi-geo-alt-fill me-1"></i>Outside Geofence' : lowAccuracy ? '<i class="bi bi-exclamation-triangle me-1"></i>Location Accuracy Too Low' : inside
              ? '<i class="bi bi-geo-alt-fill me-1"></i>Inside Geofence'
              : '<i class="bi bi-geo-alt-fill me-1"></i>Outside Geofence';
      }

      if (text) {
          text.innerHTML =
              'GPS Ready · Accuracy: ±' + Math.round(currentGpsLocation.accuracy || 0) + 'm' +
              '<br><span class="' + (lowAccuracy ? 'text-warning' : inside ? 'text-success' : 'text-danger') +
              ' fw-bold">' +
              (!inside ? 'OUTSIDE GEOFENCE' : lowAccuracy ? 'LOCATION ACCURACY TOO LOW' : 'INSIDE GEOFENCE') +
              ' — Distance: ' + Math.round(dist) + 'm / Max: ' + Math.round(radius) + 'm</span>' +
              '<details class="mt-2"><summary>Location comparison</summary><div>Saved site: ' + Number(window.employeeWorkSite.latitude).toFixed(6) + ', ' + Number(window.employeeWorkSite.longitude).toFixed(6) +
              '<br>Your device: ' + currentGpsLocation.latitude.toFixed(6) + ', ' + currentGpsLocation.longitude.toFixed(6) + '</div></details>' +
              (!inside ? '<br><span class="text-danger">Move inside the registered work site. If you are already there, ask your Team Lead to verify and update this site’s saved center.</span>' : '') +
              (lowAccuracy ? '<br><span class="text-warning">GPS accuracy must be within the site radius. Refresh GPS or use a device with a better location signal.</span>' : '');
      }

      // Never enable attendance while outside the official registered radius.
      setAttendanceButtons(inside && !lowAccuracy && !window.employeeGpsRefreshing);
  }

  function setGpsLocation(position) {
      if (!isValidGpsPosition(position)) return false;

      currentGpsLocation = {
          latitude: Number(position.coords.latitude),
          longitude: Number(position.coords.longitude),
          accuracy: position.coords.accuracy !== null && position.coords.accuracy !== undefined && Number.isFinite(Number(position.coords.accuracy)) && Number(position.coords.accuracy) >= 0
              ? Number(position.coords.accuracy)
              : null,
          timestamp: Number(position.timestamp) || Date.now()
      };

      window.currentEmployeeCoords = currentGpsLocation;
      updateGpsUi();
      return true;
  }

  function loadEmployeeWorkSite() {
      if (workSiteLoadPromise) return workSiteLoadPromise;

      var controller=new AbortController();
      var siteTimeout=setTimeout(function(){controller.abort();},10000);
      workSiteLoadPromise = fetch('../../Api/Employee/get_employee_work_site.php', {
          signal:controller.signal,
          credentials: 'same-origin',
          cache: 'no-store',
          headers: { 'Accept': 'application/json' }
      })
      .then(function (response) {
          if (!response.ok) {
              return response.json().then(function(data){throw new Error(data.message || 'Work-site request failed.');});
          }
          return response.json();
      })
      .then(function (data) {
          if (!data || !data.success || !data.work_site) {
              window.employeeWorkSite = null;
              workSiteError=(data && data.message)||'No active deployment was found for your account.';
              updateGpsUi(); return null;
          }

          var site = data.work_site;
          var lat = Number(site.latitude);
          var lng = Number(site.longitude);
          var registered = !!site.is_registered &&
              validCoordinate(site.latitude, -90, 90) &&
              validCoordinate(site.longitude, -180, 180);

          workSiteError='';
          window.employeeWorkSite = {
              site_id:site.site_id,
              deployment_id:site.deployment_id,
              site_name:site.site_name||site.company_name||'',
              company_id: site.company_id || null,
              company_name: site.company_name || '',
              deployment_location: site.deployment_location || '',
              latitude: registered ? lat : null,
              longitude: registered ? lng : null,
              geofence_radius: Math.max(1, Number(site.geofence_radius || 100)),
              is_registered: registered,
              coordinate_source: site.coordinate_source || ''
          };

          updateGpsUi();
          return window.employeeWorkSite;
      })
      .catch(function (error) {
          console.error('[GPS] Failed to retrieve registered work site:', error);
          // Fail closed instead of authorizing attendance with potentially stale site data.
          window.employeeWorkSite = null;
          workSiteError=error.name==='AbortError'?'Work-site request timed out. Please retry.':error.message;
          updateGpsUi();
          if (!window.employeeWorkSite) {
              var text = document.getElementById('gpsDistanceText');
              if (text) {
                  text.textContent = workSiteError;
              }
              setAttendanceButtons(false);
          }
          return null;
      })
      .finally(function () {
          clearTimeout(siteTimeout);
          workSiteLoadPromise = null;
      });

      return workSiteLoadPromise;
  }

  function startWorkSitePolling() {
      // Bug fix: the Team Lead's newly-registered coordinates were only ever fetched once
      // (inside initEmployeeGeolocation(), on initial GPS acquisition), so an already-open
      // Employee dashboard never saw a coordinate/radius change until the page was reloaded.
      // Polling this lightweight endpoint periodically — and letting loadEmployeeWorkSite()'s
      // existing updateGpsUi() call redraw the badge whenever the config actually differs —
      // closes that gap without a page reload.
      if (workSitePollTimer) clearInterval(workSitePollTimer);
      workSitePollTimer = setInterval(loadEmployeeWorkSite, workSitePollMs);
  }

  function gpsErrorMessage(error) {
      if (!error) return 'Unable to acquire your device location.';
      switch (error.code) {
          case 1:
              return 'Location permission was denied. Allow location access for this site, then retry.';
          case 2:
              return 'Your device could not determine a location. Turn on Location Services and try again.';
          case 3:
              return 'The GPS request timed out. Keep Location Services enabled and retry.';
          default:
              return 'Unable to acquire your device location. Please retry.';
      }
  }

  function startGpsWatcher() {
      if (!navigator.geolocation) return;
      var lastErrorCode = null;

      if (watchPositionId !== null) {
          navigator.geolocation.clearWatch(watchPositionId);
          watchPositionId = null;
      }

      watchPositionId = navigator.geolocation.watchPosition(
          function (position) {
              if (setGpsLocation(position)) {
                  lastErrorCode = null;
              }
          },
          function (error) {
              currentGpsLocation=null; window.currentEmployeeCoords=null;
              setAttendanceButtons(false);
              var badge=document.getElementById('geofenceBadge');
              if (badge) { badge.className='badge bg-warning text-dark mb-1'; badge.textContent='Location Unavailable'; }
              if (error.code === 1) {
                  if (watchPositionId !== null) navigator.geolocation.clearWatch(watchPositionId);
                  watchPositionId=null;
              }
              var text=document.getElementById('gpsDistanceText');
              if (text) text.textContent=gpsErrorMessage(error);
              if (lastErrorCode !== error.code) console.warn('[GPS] Live watcher error:', error.code, error.message);
              lastErrorCode = error.code;
          },
          {
              enableHighAccuracy: true,
              timeout: 20000,
              maximumAge: 0
          }
      );
  }

  function requestEmployeeGps() {
      return new Promise(function (resolve, reject) {
          var settled=false, fallbackStarted=false, best=null, acquisitionWatch=null;
          var highTimer;
          var deadline=setTimeout(function () { finish(best, best?null:{code:3}); }, 18000);
          function finish(position,error) {
              if(settled) return;
              settled=true;clearTimeout(deadline);clearTimeout(highTimer);
              if(acquisitionWatch!==null)navigator.geolocation.clearWatch(acquisitionWatch);
              if(error) { reject(error);return; }
              if(!setGpsLocation(position)) { reject(new Error('Invalid GPS coordinates.'));return; }
              resolve(currentGpsLocation);
          }
          function sample(position) {
              if(settled || !isValidGpsPosition(position))return;
              var accuracy=position.coords.accuracy;
              if(!Number.isFinite(accuracy) || accuracy<0)return;
              // Only samples acquired during this refresh are candidates.
              if(!best || accuracy<best.coords.accuracy)best=position;
              var radius=window.employeeWorkSite?Number(window.employeeWorkSite.geofence_radius):0;
              if(radius>0 && accuracy<=radius) { finish(position);return; }
              setGpsLocation(best);
              var badge=document.getElementById('geofenceBadge');
              if(badge) { badge.className='badge bg-warning text-dark mb-1';badge.textContent='Improving GPS Accuracy...'; }
          }
          function failed(error) {
              if(error && error.code===1)finish(null,error);
              // Unavailable/timeout may be transient; the overall deadline still settles.
          }
          function fallback(error) {
              if(settled || fallbackStarted)return;
              if(error && error.code===1){finish(null,error);return;}
              fallbackStarted=true;clearTimeout(highTimer);
              try { navigator.geolocation.getCurrentPosition(sample,failed,
                  {enableHighAccuracy:false,timeout:10000,maximumAge:0}); }
              catch(e){failed(e);}
          }
          if(!navigator.geolocation){finish(null,new Error('GPS Unsupported'));return;}
          highTimer=setTimeout(function(){fallback({code:3});},6000);
          try {
              navigator.geolocation.getCurrentPosition(sample,fallback,
                  {enableHighAccuracy:true,timeout:6000,maximumAge:0});
              if(!settled)acquisitionWatch=navigator.geolocation.watchPosition(sample,failed,
                  {enableHighAccuracy:true,timeout:18000,maximumAge:0});
          }catch(e){finish(null,e);}
      });
  }

  function refreshGpsButton(busy) {
      var button=document.getElementById('btnRefreshEmployeeGps');
      if (!button) return;
      button.disabled=busy;
      button.innerHTML='<i class="bi bi-arrow-clockwise me-1"></i>'+ (busy?'Refreshing GPS...':'Refresh GPS Location');
  }
  function initEmployeeGeolocation(manual) {
      if(gpsStarting) { updateGpsUi();refreshGpsButton(true);return; }
      gpsStarting=true;window.employeeGpsRefreshing=true;gpsFailure=null;
      refreshGpsButton(true);setAttendanceButtons(false);
      if(watchPositionId!==null && navigator.geolocation) navigator.geolocation.clearWatch(watchPositionId);
      watchPositionId=null;currentGpsLocation=null;window.currentEmployeeCoords=null;
      var token=++gpsRequestToken;
      updateGpsUi();
      // Site metadata/polling runs independently of device GPS acquisition.
      loadEmployeeWorkSite(); startWorkSitePolling();
      return requestEmployeeGps().then(function(){
          if(token!==gpsRequestToken)return;
          startGpsWatcher();
      }).catch(function(error){
          if(token===gpsRequestToken)gpsFailure=error;
      }).finally(function(){
          if(token!==gpsRequestToken)return;
          gpsStarting=false;window.employeeGpsRefreshing=false;
          refreshGpsButton(false);updateGpsUi();
      });
  }
  window.initEmployeeGeolocation = initEmployeeGeolocation;
  window.refreshEmployeeGpsLocation = function () { return initEmployeeGeolocation(true); };

  function updateLiveCameraHud() {
      var dtEl = document.getElementById('hudDateTime');
      var coordsEl = document.getElementById('hudCoords');
      var locStatusEl = document.getElementById('hudLocationStatus');
      var actionStatusEl = document.getElementById('hudActionStatus');

      if (!dtEl) return;

      var now = new Date();
      dtEl.textContent =
          'DATE: ' + now.toLocaleDateString('en-PH', {
              year: 'numeric',
              month: 'short',
              day: 'numeric',
              timeZone: 'Asia/Manila'
          }) +
          ' | TIME: ' + now.toLocaleTimeString('en-PH', {
              hour: '2-digit',
              minute: '2-digit',
              second: '2-digit',
              hour12: true,
              timeZone: 'Asia/Manila'
          });

      if (currentGpsLocation) {
          coordsEl.textContent =
              'LATITUDE: ' + currentGpsLocation.latitude.toFixed(6) +
              ' | LONGITUDE: ' + currentGpsLocation.longitude.toFixed(6);

          if (locStatusEl) {
              var dist = getDistanceFromSite();
              var radius = window.employeeWorkSite
                  ? Number(window.employeeWorkSite.geofence_radius || 100)
                  : null;
              var inside = dist !== null && radius !== null && dist <= radius;

              locStatusEl.textContent = inside
                  ? 'LOCATION: VERIFIED — INSIDE GEOFENCE (' + Math.round(dist) + 'm)'
                  : 'LOCATION: NOT VERIFIED — OUTSIDE GEOFENCE';

              locStatusEl.className = inside
                  ? 'text-success font-semibold'
                  : 'text-danger font-semibold';
          }
      } else {
          coordsEl.textContent = 'LATITUDE: Acquiring... | LONGITUDE: Acquiring...';
          if (locStatusEl) {
              locStatusEl.textContent = 'LOCATION: ACQUIRING GPS...';
              locStatusEl.className = 'text-warning font-semibold';
          }
      }

      if (actionStatusEl) {
          var isOut = currentAction === 'clock_out';
          actionStatusEl.textContent = isOut ? 'CLOCK OUT' : 'CLOCK IN';
          actionStatusEl.className =
              'fw-bold px-1.5 py-0.5 rounded text-white ' +
              (isOut ? 'bg-danger' : 'bg-success');
      }
  }

  function openSelfieModal(action) {
      if (gpsStarting) return;
      return Promise.resolve(initEmployeeGeolocation(true)).then(function () { openSelfieModalWithPosition(action); });
  }

  function openSelfieModalWithPosition(action) {
      if (!currentGpsLocation) {
          alert('Your live GPS location has not been acquired yet. Please wait or retry GPS.');
          initEmployeeGeolocation();
          return;
      }

      var dist = getDistanceFromSite();
      var radius = window.employeeWorkSite
          ? Number(window.employeeWorkSite.geofence_radius || 100)
          : null;

      if (dist === null || !window.employeeWorkSite || !window.employeeWorkSite.is_registered) {
          alert('The registered work site is not available. You cannot clock in/out yet.');
          return;
      }

      if (currentGpsLocation.accuracy === null || currentGpsLocation.accuracy > radius) {
          alert('Location accuracy is too low for this work-site radius. Refresh GPS Location and try again.'); return;
      }
      if (dist > radius) {
          alert(
              'You are outside the allowed work site.\n\n' +
              'Distance: ' + Math.round(dist) + ' meters\n' +
              'Allowed radius: ' + Math.round(radius) + ' meters'
          );
          updateGpsUi();
          return;
      }

      currentAction = action;
      var modalEl = document.getElementById('selfieCameraModal');
      if (!modalEl) return;

      var modal = new bootstrap.Modal(modalEl);
      modal.show();
      startCamera();

      if (hudIntervalTimer) clearInterval(hudIntervalTimer);
      hudIntervalTimer = setInterval(updateLiveCameraHud, 500);
      updateLiveCameraHud();
  }
  window.openSelfieModal = openSelfieModal;

  function startCamera() {
      var video = document.getElementById('selfieVideo');
      var preview = document.getElementById('selfiePreview');
      var hud = document.getElementById('liveCameraHud');
      var btnCapture = document.getElementById('btnCaptureSelfie');
      var btnRetake = document.getElementById('btnRetakeSelfie');
      var btnSubmit = document.getElementById('btnSubmitAttendance');

      if (preview) preview.classList.add('d-none');
      if (video) video.classList.remove('d-none');
      if (hud) hud.classList.remove('d-none');
      if (btnCapture) btnCapture.style.display = 'inline-block';
      if (btnRetake) btnRetake.style.display = 'none';
      if (btnSubmit) btnSubmit.style.display = 'none';

      if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
          alert('This browser does not support camera access.');
          return;
      }

      navigator.mediaDevices.getUserMedia({
          video: { facingMode: 'user' },
          audio: false
      })
      .then(function (stream) {
          cameraStream = stream;
          if (video) {
              video.srcObject = stream;
              var playPromise = video.play();
              if (playPromise && typeof playPromise.catch === 'function') {
                  playPromise.catch(function () {});
              }
          }
      })
      .catch(function (err) {
          console.error('[Camera] getUserMedia failed:', err);
          alert('Camera access denied or unavailable. Please grant camera permission to verify your selfie.');
      });
  }
  window.startCamera = startCamera;

  function captureSelfieSnapshot() {
      var video = document.getElementById('selfieVideo');
      var canvas = document.getElementById('selfieCanvas');
      var preview = document.getElementById('selfiePreview');
      var hud = document.getElementById('liveCameraHud');
      var btnCapture = document.getElementById('btnCaptureSelfie');
      var btnRetake = document.getElementById('btnRetakeSelfie');
      var btnSubmit = document.getElementById('btnSubmitAttendance');

      if (!video || !canvas) return;

      if (!currentGpsLocation) {
          alert('Live GPS is not ready. Please wait for the coordinates to appear and try again.');
          initEmployeeGeolocation();
          return;
      }

      var dist = getDistanceFromSite();
      var radius = window.employeeWorkSite
          ? Number(window.employeeWorkSite.geofence_radius || 100)
          : null;

      if (dist === null || !window.employeeWorkSite || !window.employeeWorkSite.is_registered || dist > radius) {
          alert('Attendance cannot be captured because you are not verified inside the registered work-site geofence.');
          updateGpsUi();
          return;
      }

      if (!video.videoWidth || !video.videoHeight) {
          alert('Camera is still starting. Please wait a moment and try again.');
          return;
      }

      canvas.width = video.videoWidth;
      canvas.height = video.videoHeight;
      var ctx = canvas.getContext('2d');
      ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

      var now = new Date();
      var dateStr = now.toLocaleDateString('en-PH', {
          year: 'numeric',
          month: 'short',
          day: 'numeric',
          timeZone: 'Asia/Manila'
      });
      var timeStr = now.toLocaleTimeString('en-PH', {
          hour: '2-digit',
          minute: '2-digit',
          second: '2-digit',
          hour12: true,
          timeZone: 'Asia/Manila'
      });

      var latStr = currentGpsLocation.latitude.toFixed(6);
      var lngStr = currentGpsLocation.longitude.toFixed(6);
      var statusText = currentAction === 'clock_out' ? 'CLOCK OUT' : 'CLOCK IN';

      var padding = 16;
      var boxHeight = 142;
      var boxWidth = canvas.width - (padding * 2);
      var boxX = padding;
      var boxY = canvas.height - boxHeight - padding;

      ctx.fillStyle = 'rgba(10, 42, 94, 0.88)';
      if (ctx.roundRect) {
          ctx.beginPath();
          ctx.roundRect(boxX, boxY, boxWidth, boxHeight, 8);
          ctx.fill();
      } else {
          ctx.fillRect(boxX, boxY, boxWidth, boxHeight);
      }

      ctx.strokeStyle = '#fdba74';
      ctx.lineWidth = 2;
      if (ctx.roundRect) {
          ctx.beginPath();
          ctx.roundRect(boxX, boxY, boxWidth, boxHeight, 8);
          ctx.stroke();
      } else {
          ctx.strokeRect(boxX, boxY, boxWidth, boxHeight);
      }

      ctx.fillStyle = '#ffffff';
      ctx.font = 'bold 15px Inter, system-ui, sans-serif';
      ctx.fillText('ONAZUL MANPOWER SERVICES', boxX + 16, boxY + 25);

      ctx.font = '13px Inter, system-ui, sans-serif';
      ctx.fillText('DATE: ' + dateStr + '   |   TIME: ' + timeStr, boxX + 16, boxY + 52);
      ctx.fillText('LATITUDE: ' + latStr + '   |   LONGITUDE: ' + lngStr, boxX + 16, boxY + 76);

      ctx.fillStyle = '#22c55e';
      ctx.font = 'bold 13px Inter, system-ui, sans-serif';
      ctx.fillText(
          'LOCATION: VERIFIED (' + Math.round(dist) + 'm)   |   STATUS: ' + statusText,
          boxX + 16,
          boxY + 104
      );

      ctx.fillStyle = '#ffffff';
      ctx.font = '12px Inter, system-ui, sans-serif';
      ctx.fillText(
          'GPS ACCURACY: ±' + Math.round(currentGpsLocation.accuracy || 0) + 'm',
          boxX + 16,
          boxY + 125
      );

      selfieBase64Data = canvas.toDataURL('image/jpeg', 0.85);

      if (preview) {
          preview.src = selfieBase64Data;
          preview.classList.remove('d-none');
      }
      if (video) video.classList.add('d-none');
      if (hud) hud.classList.add('d-none');
      if (btnCapture) btnCapture.style.display = 'none';
      if (btnRetake) btnRetake.style.display = 'inline-block';
      if (btnSubmit) btnSubmit.style.display = 'inline-block';

      if (hudIntervalTimer) {
          clearInterval(hudIntervalTimer);
          hudIntervalTimer = null;
      }
      if (cameraStream) {
          cameraStream.getTracks().forEach(function (track) { track.stop(); });
          cameraStream = null;
      }
  }
  window.captureSelfieSnapshot = captureSelfieSnapshot;

  function closeCamera() {
      if (hudIntervalTimer) {
          clearInterval(hudIntervalTimer);
          hudIntervalTimer = null;
      }
      if (cameraStream) {
          cameraStream.getTracks().forEach(function (track) { track.stop(); });
          cameraStream = null;
      }
      selfieBase64Data = null;
  }
  window.closeCamera = closeCamera;

  function submitGeofenceAttendance() {
      if (!currentGpsLocation) {
          alert('A valid live GPS location is required.');
          initEmployeeGeolocation();
          return;
      }

      if (!selfieBase64Data) {
          alert('A live selfie photo is required.');
          return;
      }

      var dist = getDistanceFromSite();
      var radius = window.employeeWorkSite
          ? Number(window.employeeWorkSite.geofence_radius || 100)
          : null;

      if (dist === null || !window.employeeWorkSite || !window.employeeWorkSite.is_registered) {
          alert('The registered work site could not be verified.');
          return;
      }

      if (currentGpsLocation.accuracy === null || currentGpsLocation.accuracy > radius) {
          alert('Location accuracy is too low for this work-site radius. Refresh GPS Location and try again.'); return;
      }
      if (dist > radius) {
          alert('Attendance rejected: you are outside the registered work-site geofence.');
          updateGpsUi();
          return;
      }

      var payload = {
          action: currentAction,
          latitude: currentGpsLocation.latitude,
          longitude: currentGpsLocation.longitude,
          accuracy: currentGpsLocation.accuracy,
          selfie: selfieBase64Data
      };

      fetch('../../Api/Employee/clock_in_out_geofence.php', {
          method: 'POST',
          headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json'
          },
          credentials: 'same-origin',
          body: JSON.stringify(payload)
      })
      .then(function (response) {
          return response.json().then(function (data) {
              if (!response.ok && (!data || data.success !== true)) {
                  throw new Error((data && data.message) || ('Server returned HTTP ' + response.status));
              }
              return data;
          });
      })
      .then(function (data) {
          alert(data.message || 'Attendance submitted.');
          if (data.success) {
              location.reload();
          }
      })
      .catch(function (error) {
          console.error('[Attendance] Submission error:', error);
          alert(error.message || 'Error submitting attendance.');
      });
  }
  window.submitGeofenceAttendance = submitGeofenceAttendance;

  window.addEventListener('beforeunload', function () {
      if (watchPositionId !== null && navigator.geolocation) {
          navigator.geolocation.clearWatch(watchPositionId);
          watchPositionId = null;
      }
      if (workSitePollTimer) {
          clearInterval(workSitePollTimer);
          workSitePollTimer = null;
      }
      closeCamera();
  });
