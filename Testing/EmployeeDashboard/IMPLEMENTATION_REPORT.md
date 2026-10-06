# Employee Dashboard GPS and Smart Calendar fixes

Implemented directly in the connected Employee_Tracking project, 2026-10-05. Existing unrelated workspace changes were preserved.

## 1. GPS root causes found

`work_site_resolve_for_user()` omitted `deployment.StartDate <= CURDATE()`. Its latest-deployment ordering could therefore select a future deployment. When the deployed company's coordinates were missing, it could borrow coordinates/radius from another company with the same Team Lead. The clock endpoint independently implemented similar cross-company fallback, and SQL COALESCE selected latitude/longitude separately, potentially combining registered and legacy coordinates. The two attendance paths consequently did not share one authoritative resolver.

Team Lead site retrieval also had an unscoped “first company with coordinates” fallback, and registration had an UPDATE fallback with LIMIT 1 but no company WHERE. These directly related unsafe fallbacks were removed. Multiple assigned companies now require an explicit authorized company_id instead of guessing.

The frontend set a six-second JavaScript timeout and a six-second browser timeout. Both could invoke fallback while the first fallback was still pending, producing duplicate requests. The watch callback printed every GPS fix. These were fixed independently of the lookup errors.

Important evidence limit: the quoted 8.43131320 / 124.65691955 is also the actual Company 1 registered site coordinate. That fact alone does not establish an inaccurate physical device fix or prove which account was in the screenshot. This attachment contained request text only; no screenshot image was available to inspect. Device/provider accuracy remains unverified.

## 2–5. GPS files, sources and relationships

Employee GPS is obtained from `navigator.geolocation.getCurrentPosition()` and `watchPosition()` in `Js/Employee/employee_attendance_sync.js`. The live latitude, longitude, accuracy and timestamp stay separate from `window.employeeWorkSite`.

Expected coordinates are resolved in `Api/Helpers/work_site_geofence.php`, consumed by `Api/Employee/get_employee_work_site.php` and now by `Api/Employee/clock_in_out_geofence.php`.

Server relationship: authenticated UsersID → active Employee-role user → active employee via matching users.Email/employee.Email → deployment with StartDate <= today and null/current/future EndDate → latest StartDate then DeploymentID → deployment.CompanyID's active clientcompany. Assigned Lead is deployment.SiteSuperVisorID → users.UsersID. Expected coordinates use that company's complete registered_latitude/registered_longitude pair, with a complete legacy latitude/longitude pair only from the same company as fallback. Radius comes from the same company's geofence_radius, retaining the existing 100m default for missing/nonpositive radius. No browser-supplied EmployeeID/TeamLeadID determines this lookup.

Team Lead registration writes the existing clientcompany coordinate fields, site_registered_at and registered_by_tl_id. It now verifies an active actual TeamLead role and authorized active company through clientcompany.UserID or current deployments.SiteSuperVisorID. A single assigned company may be inferred; ambiguous assignments fail closed. Retrieval follows the same authorized-company selection and coordinate-pair rules.

Read-only local database verification found:

- User 51 → Deployment 1 → Company 1 → Lead 50; registered coordinates match Company 1 and registered_by_tl_id=50.
- User 53 → Deployment 2 → Company 2 → Lead 52; registered coordinates match Company 2 and registered_by_tl_id=52.
- User 57 → Deployment 3 → Company 4 → Lead 56; registered coordinates match Company 4 and registered_by_tl_id=56.

Other relevant existing sources inspected: `Api/Helpers/employee_deployment_context.php`, `Api/Employee/dashboard_data.php`, `Api/Helpers/geofence_helper.php`, `Api/TeamLead/register_work_site.php`, and `Api/TeamLead/get_work_site.php`.

## 6. Distance/security

Reused existing `calculateHaversineDistance()` with Earth radius 6,371,000m and the exact deployed company's coordinate pair. Both frontend distance display and backend checks compare live employee GPS to expected site GPS. Accuracy never increases the allowed radius. Backend rejection occurs before selfie persistence or attendance writes. No attendance calculations, payroll formulas or schedules were changed.

## 7–8. GPS states and console output

High-accuracy timeout is expected browser fallback behavior if standard accuracy succeeds, not proof of a wrong site. A fallbackStarted guard prevents the timer/error race from making two fallback requests. Permission denial stops without a futile fallback. Position unavailable or timeout may try standard accuracy once; failure remains visible. Both requests use maximumAge=0. Continuous updates reuse one watcher, replacing the previous watcher before starting another.

Removed repetitive live-coordinate console logging and the normal fallback warning. Real site-loading/initialization errors remain logged. Watch errors remain visible and are logged when the error code changes rather than on every repeated identical error. Permission denial clears the watcher/current fix and disables attendance. Low/unknown accuracy is shown when it exceeds the site's radius; the existing inside/outside rule remains fixed. Nullable/blank coordinates are rejected before numeric conversion.

## 9–10. Holiday source and APIs

Inspected actual `holidays` schema: HolidayID, HolidayName, HolidayDate, HolidayType, PayMultiplier, IsNationwide, Region, Year, Description, CreatedAt, UpdatedAt. Admin Holiday Management and existing attendance/payroll helpers use this table. They were not redesigned or given different holiday/pay formulas.

Extended existing authenticated read-only `Api/Employee/get_employee_schedule.php` to return holidays grouped by date alongside schedules. New read-only helper `Api/Helpers/employee_calendar_holidays.php` returns required name/date/type/scope/remarks fields, supports multiple records on one date, performs no schema changes and does not calculate pay.

Extended `Api/Employee/get_employee_attendance.php` with optional validated start/end dates so calendar navigation loads records for the viewed month instead of relying on the most recent 30 rows. Both existing range APIs limit requests to 62 days. Attendance formatting continues to use existing helpers and calculations.

## 11–14. Calendar behavior

Previous holiday visibility depended on logByDate[date].holiday, so a date without attendance could not show a holiday. The calendar now reads holidaysByDate independently, with old log holiday metadata retained only as a compatibility fallback. A purple inset border matches the existing Holiday legend, tooltip and accessible label. It layers over the original attendance/status color rather than replacing it.

Click/tap reuses the existing date-details panel. A responsive Holiday Details section shows actual names, date, type, Nationwide or stored Region/local scope, and remarks when present. Multiple holidays are displayed separately. Existing attendance/shift details remain below it. Future dates require no attendance record.

Previous/next month reloads attendance and schedules/holidays using existing endpoints, clears old-month selection and holiday data, replaces the viewed month's attendance cache, and rejects late responses for a different month. Loading and API errors are visible above the calendar. There is one existing Holiday legend; no duplicate legend/table or hardcoded holiday definitions were added.

Regional holiday selection preserves the existing `holidays_fetch_for_range()` policy of reading all records in the date range. Scope is displayed explicitly. The schema has no normalized deployment Region relationship, and company addresses include abbreviations such as CDO. A new inferred regional eligibility policy was deliberately not introduced; see limitations.

## 15. Exact files modified for this task

- Api/Helpers/work_site_geofence.php
- Api/Employee/clock_in_out_geofence.php
- Api/Employee/get_employee_schedule.php
- Api/Employee/get_employee_attendance.php
- Api/TeamLead/get_work_site.php
- Api/TeamLead/register_work_site.php
- Js/Employee/employee_attendance_sync.js
- Html/Employee/employee_dashboard.html

Files created:

- Api/Helpers/employee_calendar_holidays.php
- Testing/EmployeeDashboard/server_test.php
- Testing/EmployeeDashboard/frontend_test.js
- Testing/EmployeeDashboard/IMPLEMENTATION_REPORT.md

## 16. Database changes

No production schema/migration or business-record changes. The server test reads actual deployment/holiday records, then clones schema without business data into a uniquely named test database for fixtures. The test database is removed in finally; cleanup was verified. Existing Team Lead schema-compatibility checks predate this task and were not added by this change.

## 17–18. Executed tests

`C:\xampp\php\php.exe Testing/EmployeeDashboard/server_test.php`: 29 PASS. Actual three-employee deployment/company/Lead/registered-coordinate checks; assigned-origin inside/distant-point outside; actual November/December holiday retrieval; future/ended deployment exclusion; missing site cannot borrow another Lead company; coordinate pairs cannot mix axes; ambiguous/unassigned Lead companies fail closed; explicit assigned company allowed; inactive employee denied; multiple holidays preserved; empty month; existing Haversine reference distance.

`node Testing/EmployeeDashboard/frontend_test.js`: 22 PASS. Uses actual database holiday data and mocked browser APIs/DOM. Month attendance+holiday loading; future marker without a log; real tooltip; click details/date/type/scope; Present plus holiday; retained attendance details; October/November/December navigation; empty month; error state; stale response rejection; single GPS fallback race; fresh standard accuracy; successful fallback; no normal fallback warning; permission denial; high accuracy success; position unavailable; repetitive log removal; low-accuracy warning; fixed radius not expanded.

PHP lint: eight modified/new PHP implementation/test files PASS. JavaScript syntax: modified attendance script, frontend test and all Employee Dashboard inline scripts PASS.

Actual anonymous Apache HTTP requests to employee work-site, schedule, attendance and clock endpoints: HTTP 401 PASS. Authenticated endpoint request/response behavior was not exercised with fabricated sessions.

Requested test results:

1. Deployment mapping: PASS against all three actual local database relationships.
2. Wrong Team Lead/site: PASS actual expected-coordinate equality plus isolated missing-site/foreign-company denial.
3. Live browser GPS with permission: NOT VERIFIED on a physical device.
4. Distance: PASS existing Haversine reference and assigned-origin/distant-point server checks; actual device live-point comparison NOT VERIFIED.
5. GPS fallback: PASS mocked timer/error race and standard-accuracy success; actual hardware timeout observation NOT VERIFIED.
6. Console: PASS coordinate logging removal and mocked normal timeout without warnings; production browser console NOT VERIFIED.
7. Current October month holiday: October has no actual holiday record, so a current-month holiday display is NOT VERIFIED; the actual empty-month behavior passed.
8. Future holidays: PASS frontend rendering using actual November/December database records without attendance.
9. Holiday details: PASS actual database name/type/date/scope rendered through existing panel in mock DOM; signed-in browser click-through NOT VERIFIED.
10. Month navigation: PASS October → November → December → November data/controller tests and stale-response handling; signed-in browser navigation NOT VERIFIED.
11. Holiday + attendance: PASS controlled Present attendance plus actual holiday metadata, with state and details retained; no production attendance was inserted for testing.
12. Desktop/laptop/tablet/small viewport: NOT VERIFIED in browser. Existing seven-column calendar grid retained, holiday markers do not enlarge cells, and detail text wraps; these are implementation observations, not executed responsive tests.

Initial test assertions incorrectly treated a nullable real Description as missing and compared an escaped holiday apostrophe to unescaped text. The test assertions were corrected; final suites pass cleanly.

## 19. Remaining limitations

Browsers/devices control reported physical coordinates and accuracy; the application cannot infer the true location from console values. Real signed-in GPS, selfie clock operations, browser console and viewport testing remain NOT VERIFIED. Legacy regional holiday records follow the existing global range-selection behavior; normalized regional applicability would require an explicit business mapping. A Team Lead with multiple assigned companies must provide a scoped company_id; the current single-site registration UI has no multi-company selector and now reports ambiguity instead of choosing an arbitrary site. The model stores one coordinate pair per company, not per distinct physical site within the same company; no unrequested schema redesign was made. No screenshot image was supplied with this request attachment.
