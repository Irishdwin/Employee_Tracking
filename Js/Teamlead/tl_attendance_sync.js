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

function openRegisterSiteModal() {
    var modalEl = document.getElementById('registerSiteModal');
    if (!modalEl) return;
    var modal = window.bootstrap && bootstrap.Modal ? bootstrap.Modal.getOrCreateInstance(modalEl) : null;
    if (modal) modal.show();
    acquireTlLocation();
}

function acquireTlLocation() {
    var statusEl = document.getElementById('tlCurrentGpsStatus');
    var accuracyEl = document.getElementById('tlGpsAccuracyText');
    var btnSave = document.getElementById('btnConfirmRegisterSite');

    if (!navigator.geolocation) {
        if (statusEl) statusEl.innerText = "Geolocation is not supported by your browser.";
        return;
    }

    navigator.geolocation.getCurrentPosition(
        function (position) {
            tlRegisteredLocation = {
                latitude: position.coords.latitude,
                longitude: position.coords.longitude,
                accuracy: position.coords.accuracy
            };

            if (statusEl) {
                statusEl.innerText = 'GPS Acquired: ' + tlRegisteredLocation.latitude.toFixed(6) + ', ' + tlRegisteredLocation.longitude.toFixed(6);
            }
            if (accuracyEl) {
                accuracyEl.innerText = 'Accuracy: ±' + Math.round(tlRegisteredLocation.accuracy) + ' meters';
            }
            if (btnSave) btnSave.disabled = false;
        },
        function (error) {
            if (statusEl) statusEl.innerText = "Error acquiring GPS position.";
            if (accuracyEl) accuracyEl.innerText = "Please enable location permissions in browser.";
        },
        { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
    );
}

function submitSiteRegistration() {
  if (!tlRegisteredLocation) {
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
  if (btnSave) {
      btnSave.disabled = true;
      btnSave.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving Work Site...';
  }

  var payload = {
      latitude: tlRegisteredLocation.latitude,
      longitude: tlRegisteredLocation.longitude,
      accuracy: tlRegisteredLocation.accuracy,
      geofence_radius: radiusVal
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
              console.error("Server returned non-JSON output:", text);
              throw new Error("Server Error: " + (text.substring(0, 150) || "Empty server response"));
          }
      });
  })
  .then(function (data) {
    if (btnSave) {
      btnSave.disabled = false;
      btnSave.innerHTML = 'Save Work Site Coordinates';
  }
  
  if (data.success) {
      showStatusModal("Success!", data.message, true, true);
  } else {
      showStatusModal("Registration Failed", data.message, false, false);
  }})
  .catch(function (err) {
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
/* ==========================================================================
   LIVE WORK SITE DISPLAY ENGINE
   ========================================================================== */
   function loadWorkSiteDetails() {
    var coordsEl = document.getElementById('registeredSiteCoords');
    var radiusEl = document.getElementById('registeredGeofenceRadius');

    if (!coordsEl) return;

    fetch('../../Api/TeamLead/get_work_site.php', { credentials: 'same-origin' })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.success && data.latitude !== null && data.longitude !== null) {
                coordsEl.innerText = data.latitude.toFixed(6) + ', ' + data.longitude.toFixed(6);
                if (radiusEl) {
                    radiusEl.innerText = (data.geofence_radius || 100) + ' Meters';
                }
            } else {
                coordsEl.innerText = 'No work site coordinates registered yet.';
                if (radiusEl) radiusEl.innerText = 'Not Set';
            }
        })
        .catch(function () {
            if (coordsEl) coordsEl.innerText = 'Failed to load coordinates';
        });
}

window.loadWorkSiteDetails = loadWorkSiteDetails;

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', loadWorkSiteDetails);
} else {
    loadWorkSiteDetails();
}