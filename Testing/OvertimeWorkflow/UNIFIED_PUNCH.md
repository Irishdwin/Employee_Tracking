# Unified regular and overtime attendance

Implemented in the connected Employee_Tracking project on October 8, 2026.

## Behavior

Employees use the existing Clock In & Take Photo and Clock Out & Take Photo controls. Separate overtime punch buttons were removed from employee overtime cards. Acceptance/rejection and Open Smart Calendar remain.

The authenticated employee, active deployment, regular attendance and accepted overtime are resolved by the server. Active overtime routes Clock Out to that session. Open regular attendance must be closed before overtime. Otherwise an accepted session in its admission window routes Clock In to overtime. A closed attendance record cannot be reopened as a new regular record. Multiple applicable sessions fail safely rather than choosing an arbitrary record. Browser employee/site/overtime IDs and browser time do not determine the punch context.

Clock In opens ten minutes before the scheduled OT start and remains available through its end. Early admission never increases credited hours. Arrival no later than five minutes after start has effective start at the approved start. Later arrival uses the actual punch time and stores late minutes. Clock Out records its raw time and caps the credited end to the approved end. Rendered hours are stored in overtime.OvertimeHours, not just displayed.

The local CLI worker runs every minute and closes qualifying active sessions after the scheduled end plus twenty minutes. Effective end is the scheduled end, never the processing time. The raw processing time is preserved in AutoClosedAt and automatic evidence. The existing notification helper creates the employee alert. Existing Messenger helpers create a system-marked reminder from the currently assigned Team Lead to the employee in their ordinary direct conversation. Current Messenger relationship authorization is checked. Closure, notification and message are one transaction; repeated runs do not duplicate them.

The worker's registered Windows task is `Onazul Overtime Automatic Timeout`. It runs hidden with the current Windows user signed in; XAMPP MySQL must be available. It is independent of an open browser. Employee polling also processes that employee's due sessions. The one-minute worker interval means processing normally occurs within a minute of the twenty-minute threshold, rather than guaranteeing execution at the exact second.

Team Lead completion approval is still required before overtime becomes payable. Submitted DTR periods remain protected. New employee-response overtime that is active or completed must receive a Team Lead decision before its DTR is submitted; an accepted session whose window is still open also blocks submission. Expired accepted requests without a punch do not permanently block DTR. Production payroll_compute_period and dtr_classification_from_payroll consume the stored approved OverTimeHours; no pay rates or payroll formulas were duplicated.

GPS coordinates still undergo the existing authoritative work-site resolution, radius and accuracy checks. Live photo validation, employee/deployment ownership, active-account roles, overtime CSRF and transactional duplicate protection remain. Browser GPS alone cannot prove physical presence; the existing device GPS validation behavior was retained.

## Modified files

- Api/Helpers/overtime_workflow.php — unified context, early/grace policy, effective hours and transactional auto-close/message.
- Api/Employee/clock_in_out_geofence.php — existing clock endpoint routes punches using the server context instead of a supplied overtime ID.
- Api/Employee/overtime_requests.php — server context, current time and CSRF returned with requests; due timeout processing.
- Api/TeamLead/submit_dtr.php — prevents locking unresolved new overtime out of DTR/payroll.
- Js/Employee/employee_attendance_sync.js — existing GPS controls use server-authoritative allowed states; shared payload carries CSRF without overtime ID.
- Js/Employee/employee_overtime.js — removes separate OT punches, explains admission/grace/timeout and updates attendance context.
- Js/Teamlead/tl_overtime.js — active sessions show In progress instead of calling planned hours actual rendered hours.
- Testing/OvertimeWorkflow/backend_test.php — updates early-rejection fixture to more than ten minutes before start.

## Created files

- sql/overtime_unified_punch_migration.sql — additive migration, applied locally.
- Api/Jobs/overtime_auto_timeout.php — CLI-only background worker.
- scripts/install_overtime_timeout_task.ps1 — installs/updates the named local Windows worker task.
- Testing/OvertimeWorkflow/unified_punch_test.php — disposable database timing, notifications, Messenger, DTR and payroll service tests.
- Testing/OvertimeWorkflow/unified_endpoint_test.php — disposable database tests of the actual clock endpoint.
- Testing/OvertimeWorkflow/unified_endpoint_runner.php — test harness redirects only the database connection to the disposable fixture; production endpoint routing remains in use.
- Testing/OvertimeWorkflow/unified_ui_test.cjs — desktop/mobile browser state and photo-flow checks using mocked API/GPS/camera.
- Testing/OvertimeWorkflow/UNIFIED_PUNCH.md — this report.

## Database changes

Added nullable EffectiveTimeIn, EffectiveTimeOut, AutoClosedAt and nonnegative-default LateMinutes to the existing overtime table. Existing ActualTimeIn, ActualTimeOut, ClockInEvidence, ClockOutEvidence, OverTimeHours, statuses and employee response fields are reused. Existing data and tables are preserved. No duplicate attendance, notification or messaging tables were introduced.

## Verification

Passed disposable database service tests:

- 8:55 early punch for 9–11 PM credits 2 hours.
- 9:04 and 9:05 arrivals credit 2 hours under requested grace policy.
- 9:06 arrival stores 6 late minutes and 1.9 hours.
- 10:30 early departure credits 1.5 hours.
- 11:10 departure is capped at 11 PM and 2 hours.
- 8:49 punch is rejected; 8:50 punch is accepted.
- No automatic close at 11:19:59; automatic close at 11:20 with effective end 11 PM.
- Notification and assigned Team Lead Messenger reminder are created exactly once.
- Repeat worker runs do not duplicate completion or messages.
- Duplicate punches and cross-employee punches are blocked.
- Closed OT cannot start another punch; no accepted OT retains regular routing.
- Each tested credited-hour value reaches production period calculation, Team Lead DTR classification and the existing overtime payroll helper.

Passed actual endpoint tests with disposable records and synthetic GPS/photo:

- Outside-geofence and missing-photo rejection.
- Existing regular Clock In and Clock Out succeed; duplicate Clock In is rejected.
- The same Clock In routes accepted overtime without an overtime ID; forged employee/site/overtime IDs are ignored.
- The same Clock Out completes overtime without inserting extra regular attendance rows.

The existing overtime backend regression suite passed. PHP/JavaScript syntax and git whitespace checks passed. Background task was inspected after successful execution.

Desktop (1366px) and mobile (390px) browser checks passed for the same controls, camera modal, GPS gating, active clock-out and closed states. Desktop additionally passed simulated live-photo capture and verified CSRF submission without an overtime ID. Mobile physical-camera capture was not verified; the headless mobile media stream stalled during the initial capture test. Simulated camera/GPS cannot verify a physical employee phone or actual work-site accuracy.

The existing request model supports same-day overtime only and rejects end-before-start. This restriction is preserved explicitly; ordinary overnight attendance continues using its existing previous-day handling. Auto timeout can finish a same-day session during the next day's twenty-minute safety period.
