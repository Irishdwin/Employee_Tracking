# Team Schedule implementation and verification

## 1. Existing schedule functionality found

The live MariaDB schema was inspected before implementation. Recurring expectations already live in `deployment`. `work_schedule_lookup` contains schedule labels, not per-employee/date shifts. `attendance` and `daily_time_records` store actual clocking. Existing payroll, DTR review/submission, billing, and overtime roster consumers use `payroll_resolve_deployment_schedule()`.

## 2. Existing fields and relationships reused

Recurring settings reuse `deployment.TimeIn`, `TimeOut`, `BreakDuration`, `GracePeriod`, `WorkingDays`, and `ShiftName`. Ownership follows `deployment.EmployeeID -> employee.EmployeeID` and `deployment.SiteSuperVisorID -> users.UsersID`. An employee's notification recipient is resolved through `employee.Email -> users.Email`, checked for exactly one active Employee account. EmployeeID is never used as the notification UserID.

## 3. New feature files

- `Api/Helpers/team_schedule_helper.php`: input validation, owned deployment lookup, shared expectations, history, locks, transactional mutations, recipient resolution.
- `Api/TeamLead/team_schedule.php`: authenticated Team Lead GET/POST interface with a feature-scoped CSRF token.
- `Api/Employee/get_employee_schedule.php`: authenticated, view-only lookup for the session's employee; date range limited to 62 days.
- `Js/Teamlead/tl_team_schedule.js`: schedule form, deployed employee selection, existing Bootstrap controls/dialogs, edit/delete, feedback, refresh.
- `sql/team_schedule_migration.sql`: additive schema migration.
- `Testing/TeamSchedule/http_schedule_test.php`: CLI-only HTTP/integration suite with disposable fixtures and exact-ID cleanup.

The Team Schedule view is a new panel inside `Html/TeamLead/team_lead_dashboard.html`, following its existing sidebar and view switching. No separate dashboard design or Job Order Request workflow was introduced.
The standalone `Html/TeamLead/request_job_order.html` and `Html/TeamLead/endorsed_applicants.html` sidebars each receive only a Team Schedule navigation link to that panel; their workflow code and designs are untouched.

## 4. Database changes

Applied `sql/team_schedule_migration.sql` to the existing local database. It adds only `team_schedule_entries`, with foreign keys to deployment and the user making the change, and a unique deployment/date/entry-kind index.

- `Dated` rows hold Rotating/Flexible expectations for one shift-start date.
- `FixedHistory` rows archive earlier recurring settings over date ranges, only when Fixed settings change. The current recurring schedule remains in deployment; there is no second active recurring schedule record.
- Existing tables and data were preserved. Actual attendance tables are not duplicated.

## 5. APIs/functions involved

`team_schedule_save()` and `team_schedule_delete()` run within the Team Lead API's transaction. Ownership is validated and the current deployment row locked. A mutation and its employee notification commit together; any error rolls both back. Identical saves create no extra notification. Invalid dates, times, types, weekdays, break lengths, and CSRF tokens are rejected.

`team_schedule_resolve()` supplies dated overrides first, then historical/current recurring settings. `payroll_resolve_deployment_schedule()` remains the common entry point for downstream consumers.

## 6. Attendance integration

The following existing files were changed only where they resolve/display schedules:

- `Api/Helpers/employee_payroll_helper.php`: shared resolver plus per-date preceding-workday lookup for holiday eligibility.
- `Api/Employee/get_employee_attendance.php`: date-specific expectations for current/historical status; expectations returned separately from actual attendance.
- `Api/Employee/clock_in_out_geofence.php`: shared late/grace helper, expected time snapshots in existing attendance columns, and previous-date handling for open overnight shifts.
- `Api/Employee/dashboard_data.php`: today's schedule label and duty details.
- `Api/DutySchedule/get_duty_schedule.php`: shared expected schedule.
- `Api/TeamLead/get_tl_attendance_roster.php`: same daily expectations as Employee attendance.
- `Html/Employee/employee_dashboard.html` and `Js/Employee/employee_attendance_sync.js`: today's expectations, calendar date details, dated rest/workday display.

Actual TimeIn/TimeOut remain server clock timestamps. Existing DTR submission, payroll computation, overtime roster, and client billing inherit the resolver without separate payroll formulas. Break minutes are supplied using the existing calculation helper's `break_minutes` key.

## 7. Notification integration

Uses `Api/Common/NotificationHelper.php::insert_system_notification()` and the existing `notifications` table, Common notification list/read API, real unread query, and shared Employee popup/panel. Events are `schedule_assigned`, `schedule_updated`, and `schedule_removed`. `Api/Common/notifications.php` labels these events as coming from Team Lead. Messages include type, date/effective date, expected hours, overnight indicator, and break. Deletion messages explain the recurring fallback.

## 8. Recipient and authorization rules

Only the affected employee's active linked Employee user receives the notification. No broadcast is performed. Both GET and mutations scope Team Leads to their current deployed team. The latest applicable deployment is selected deterministically. Employees may read only their own expectations, regardless of supplied employee IDs, and may not write schedules.

Schedules with recorded clock-in, a submitted DTR cutoff, or an existing payroll period are locked against changes. This preserves already-recorded and submitted expectations. Fixed changes preserve previous days through history.

## 9. Verification evidence

**DATABASE VERIFIED:** actual deployment, employee, users, notifications, attendance, daily_time_records, dtr_submissions, payroll, holidays, and schedule schema/relationships inspected. Migration installed successfully. Temporary fixture rows cleaned up; no test schedule rows remain.

**RUNTIME VERIFIED:** final retained HTTP/integration suite passed **45 checks** on October 5, 2026 (Asia/Manila). It uses an isolated local PHP test server/session directory. Existing accounts receive only read-only checks. Successful writes use disposable, clearly labelled synthetic users/deployment/attendance, removed by exact IDs in `finally`.

The suite exercises the requested cases:

1. Fixed save through actual HTTP API and Employee retrieval.
2. September 29 rotating expectation.
3. Independent September 30 expectation.
4. Flexible overnight expectation.
5. Existing attendance helper's actual-vs-expected late/grace comparison.
6. Update notification through actual API.
7. Delete notification through actual API.
8. Existing notification list returns the correct schedule title/details. Visual bell rendering still requires manual verification.
9. Real unread count and mark-read handling.
10. Cross-team modification rejected by actual HTTP API.
11. Actual payroll engine uses an existing saved holiday with the dated schedule; existing holiday/rest-day branch also exercised.
12. Payroll receives real fixture DTR timestamps and the saved start/break settings; existing additional-30-minute overtime branch exercised.

Additional checks cover anonymous/Employee management rejection, CSRF, invalid actions/dates/ranges/types/hours/breaks, history, overnight hours, idempotent saves, and attendance/DTR locks.

**STATICALLY VERIFIED:** modified PHP files, external JavaScript, and both dashboards' inline JavaScript pass syntax checks. Existing shared notification rendering accepts arbitrary returned titles/messages and derives its count from the real API.

To rerun the HTTP suite, start a localhost-only PHP test server with an isolated session directory (do not use the production Apache session store):

```powershell
$scheduleSessionDir = Join-Path $env:TEMP 'onazul-team-schedule-http'
New-Item -ItemType Directory -Force -Path $scheduleSessionDir | Out-Null
php -d "session.save_path='$scheduleSessionDir'" -S 127.0.0.1:8893 -t C:\xampp\htdocs\Employee_Tracking
```

Then, from another terminal in the project:

```powershell
$scheduleSessionDir = Join-Path $env:TEMP 'onazul-team-schedule-http'
php Testing/TeamSchedule/http_schedule_test.php $scheduleSessionDir http://127.0.0.1:8893
```

Stop the test server after testing. The script is CLI-only; production browsers cannot execute it.

## 10. Remaining limitations / manual verification

- Perform signed-in Team Lead/Employee browser UAT for layout, mobile controls, refresh, date switching, and notification bell/popup rendering.
- Perform actual GPS/selfie clock-in and an overnight clock-out using an approved test employee/site. Calculation helpers and HTTP attendance retrieval were tested; live camera/location capture was not.
- The existing calculation engine uses an eight-hour standard for undertime and automatic overtime. A six-hour assigned shift with six hours actually worked may still accrue two hours of undertime under that existing rule. No payroll or holiday formulas were changed.
- Fixed changes take effect today and require that the affected date/period is unlocked. Future single-day expectations use Rotating/Flexible. Earlier fixed settings remain historical.
- Dated shifts can be deleted; the deployment's recurring Fixed baseline is edited rather than deleted. A deleted dated shift restores the applicable recurring expectation, rather than creating a rest-day record.
- Dates without a dated override continue to use the recurring deployment schedule. No new unassigned-date business rule was invented for Rotating/Flexible.
- Overnight clock-out handles an open shift from the previous date while an active deployment/site is still available. Deployment transitions at midnight require a separate business decision and UAT.

Only files directly related to Team Schedule, shared schedule lookup/display, schedule notifications, and the focused test suite were edited. Existing unrelated local changes were preserved.
