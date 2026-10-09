# Team Lead worksite and date-specific duty update

Implemented in the connected project on 9 October 2026. Existing employee attendance records were not changed. No database migration or replacement schedule/payroll engine was added.

## Files

Modified application files:
- Html/TeamLead/team_lead_dashboard.html
- Js/Teamlead/tl_attendance_sync.js
- Js/Teamlead/tl_team_schedule.js
- Js/Shared/onazul_dialogs.js
- Api/TeamLead/team_schedule.php
- Api/Helpers/team_schedule_helper.php
- Api/TeamLead/submit_consolidated_jor.php
- Api/Helpers/job_order_request_workflow.php

Modified isolated API test: Testing/ClientCompanies/http_test.php and its generated http_results.json. Created browser test: Testing/TeamSchedule/worksite_schedule_ui.cjs; generated desktop/mobile screenshots worksite-1366.png and worksite-390.png. Created this report. Other pre-existing workspace changes were preserved.

## Worksite registration, GPS and Job Orders

1. Discovered the existing Bootstrap registration dialog, progressive GPS watch, registration policy API, deployment_sites coordinates, and shared work_site_geofence helper.
2. Improved that dialog with a readable blue header, selected company/site card, current and best readings, separate accuracy/radius guidance, scrollable body, and Cancel/Retry/Save footer.
3. Acquisition requests a fresh high-accuracy fix alongside one progressive watch. It retains the best fresh reading and stops immediately when policy accuracy is met.
4. The production policy remains 100 meters or better by default; the existing server policy controls the threshold and bounded acquisition duration. Tests use simulated readings only.
5. Denied permission is terminal; unavailable/temporary timeout can recover until the acquisition deadline. No-reading and poor-accuracy deadline messages offer retry/device guidance.
6. Retry resets the fix and previous capture. Closing the dialog or leaving the page stops watching and invalidates obsolete callbacks.
7. Stale readings are ignored and fixes older than 30 seconds cannot be submitted. Invalid coordinates and accuracy are rejected.
8. Geofence radius remains a separate 30–1,000 meter attendance-distance setting.
9. Successful registration uses the existing server endpoint and status modal with selected site, actual coordinates, accuracy and radius.
10. Job Order deployment choices come from actual assigned active sites. No company selection during initial hidden-form loading produces no prerequisite popup.
11. The frontend blocks an unregistered selected site and shows Location Registration Required with Cancel and Go to Work Site Geofence. A missing assignment has a distinct message.
12. The API checks the selected branch against server-side ownership/company and registered valid coordinates. Registration of branch A does not authorize unregistered branch B; legacy name/address selection must identify exactly one owned site.
13. Existing active-company/service-agreement prerequisites remain enforced. Profile appears before Settings with existing handlers retained.
14. Tests verify GPS freshness, best-reading retention, immediate stop, retry, permission denial, unavailable/deadline handling, closed-dialog cleanup, responsive layout and Job Order blocking/navigation; isolated HTTP tests verify registration scope/accuracy/persistence and actual allowed/blocked Job Order submissions.
15. A browser cannot guarantee hardware GPS accuracy or prove physical presence. Approximate-location computers may still require a phone outdoors with precise location enabled. No live physical GPS test or device speed benchmark was performed.

## Existing TEAM SCHEDULE duty changes

1. Discovered the existing deployment recurring schedule, team_schedule_entries dated overrides/fixed history, transactional team_schedule API, shared team_schedule_resolve and existing notifications/audit helpers.
2. Added Current Duty and a date-specific default to the existing TEAM SCHEDULE form; the recurring Fixed option remains available for its original use.
3. Selecting employee/date resolves the effective duty from the existing backend; stale concurrent responses cannot replace a newer selection.
4. The existing Rotating backend value stores a dated override; the UI calls it Date-specific duty. This does not rewrite the recurring deployment schedule.
5. Saving a dated change displays employee, date, current duty and proposed hours in the existing confirmation dialog. Cancel makes no POST. The submitted values are captured before confirmation.
6. After saving, the selected date remains selected and Current Duty refreshes. Existing schedule list refresh remains in place.
7. The affected employee receives Duty Schedule Changed through the existing notification helper, with effective date and new duty times. Identical effective duty is a no-op with no duplicate notification/audit.
8. Notification recipients are resolved from the employee deployment/email and existing active Employee user mapping; no second notification system was introduced.
9. Existing employee schedule/calendar data consumes the shared effective-schedule resolver on its normal refresh. No new calendar or attendance record was created.
10. Existing attendance/DTR consumers retain their production schedule resolution and grace/late calculations. Recorded attendance and submitted DTR/payroll dates remain locked by the existing guards.
11. The existing payroll resolver consumes the dated override; no new payroll formulas were added. Neighboring dates retain their base duty.
12. The existing AuditLogger records employee, effective date, old duty and new duty within the schedule save transaction. Dated overnight handling remains supported by existing resolution and next-day display.
13. API site ownership and employee deployment ownership remain checked on the server, alongside existing RBAC, CSRF, date/shift validation and transaction locking.
14. Isolated tests exercise actual dated save/read, unchanged neighboring date, notification/no-op/audit, cross-site rejection, locked DTR, and production payroll/attendance resolver behavior. Browser tests exercise confirmation/cancellation/dated submission at 1366px and 390px. PHP and external/inline JavaScript syntax checks passed; six existing employee GPS state regression checks passed.
15. Tests use disposable database/session fixtures and mocked browser APIs. They do not constitute a physical-device GPS test or a complete live payroll finalization run. Employees see updated schedules through the existing refresh/polling behavior.

## Validation

89 isolated HTTP/database checks passed, including the pre-existing Client Companies/hiring regression checks. 26 desktop/mobile browser checks passed. PHP syntax and five dashboard inline scripts plus the changed shared/Team Lead JavaScript passed syntax checks. The mobile registration screenshot was visually inspected: controls remain accessible with internal scrolling.
