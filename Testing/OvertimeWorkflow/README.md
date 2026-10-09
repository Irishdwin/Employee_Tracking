# Overtime employee response workflow

## Problem and fix
The Team Lead insert supplied ten values but used a nine-character mysqli binding string (`iiisssdis`). PHP raised ArgumentCountError, which the endpoint hid behind “Failed to save overtime.” The corrected binding is `iiisssdiis`. Roster ownership also used the legacy deployment supervisor rather than the current deployment-site Team Lead. Creation, management and roster visibility now use active site ownership, including multiple sites.

## Storage and migration
Existing table: `overtime`. Existing status lookup and notification tables are reused. No replacement attendance or payroll tables were created.

`sql/overtime_employee_response_migration.sql` was applied to the local database. It adds EmployeeResponseRequired, EmployeeResponse, EmployeeRespondedAt, EmployeeRejectionReason, ActualTimeIn, ActualTimeOut, ClockInEvidence and ClockOutEvidence, and adds Accepted to the existing status lookup. Existing rows and their legacy workflows are retained.

New requests follow Pending → Accepted → Active → Completed → Approved (or payroll review Rejected). Employees may instead reject Pending requests with a required reason. Employee acceptance is separate from payroll approval. Actual hours are capped to the scheduled bounds; payroll uses its existing Approved-overtime calculation.

## Files and APIs
New:
- `Api/Helpers/overtime_workflow.php`: shared authorization, transactions, transitions and notifications.
- `Api/Employee/overtime_requests.php`: employee GET list and POST accept/reject.
- `Js/Employee/employee_overtime.js`: requests, rejection dialog, dashboard counts and Smart Calendar context.
- `sql/overtime_employee_response_migration.sql`: additive schema change.
- `Testing/OvertimeWorkflow/`: isolated backend, endpoint and browser checks.

Modified:
- `Api/TeamLead/save_overtime.php`: create, edit and approval operations; correct binding and safe errors.
- `Api/TeamLead/get_overtime_roster.php`: site-based ownership, response/reason/actual timestamps and CSRF token.
- `Api/Employee/clock_in_out_geofence.php`: existing GPS/selfie endpoint accepts validated overtime context.
- `Js/TeamLead/tl_overtime.js`: CSRF, response display, consent-aware actions and save feedback.
- `Js/Employee/employee_attendance_sync.js`: existing selfie modal passes overtime context.
- `Html/Employee/employee_dashboard.html`: Overtime card/view, Leave profile access and existing-calendar event.
- `Js/Shared/ow_notifications.js`: View Overtime action in notification details.

## Employee and Team Lead behavior
Successful creation inserts the request and employee notification in one transaction. The employee can open it from notifications or the Overtime card. Accept stores the response timestamp, notifies the Team Lead, and opens the existing Smart Calendar on the request date. Reject requires a reason, stores it, and notifies the Team Lead. The roster shows responses and rejection reasons.

Accepted overtime uses the existing GPS/geofence/live-selfie modal and endpoint. The server verifies the employee, deployment, accepted state and allowed time before clock-in; clock-out stores actual evidence and elapsed hours. Regular attendance is not overwritten. Completed sessions use the existing Team Lead payroll approval path.

The dashboard card displays database-backed pending, accepted and active counts. Leave is now in the profile menu, with the existing balance and calendar access. The old filing button had no submission implementation; its contact action now opens existing Messenger to HR. Automatic leave filing was not invented by this change.

## Security
Active users and roles are checked against the database. Employees are resolved from the authenticated account, never trusted from a posted employee ID. Current deployment and active Team Lead site ownership are checked on each mutation. CSRF, prepared statements, transaction locks, overlap checks, submitted-DTR checks, ownership and state checks protect against duplicate and unauthorized actions. Required notifications roll back with failed mutations. Raw database errors are logged server-side rather than returned to the browser.

## Verification
- Exact original binding reproduced ArgumentCountError.
- Backend: 33 checks passed in a disposable database, including multi-site authorization, mixed-batch rollback, IDOR, response transitions, rejection reasons, duplicate and overlap blocking, actual clock timestamps/hours, inactive users.
- Existing production payroll helper accepted only approved overtime: 1.75 actual hours at a synthetic hourly rate of 100 produced 218.75 using its existing multiplier.
- Desktop 1366px and mobile 390px browser checks passed: real create endpoint, database-backed employee card, accept to Smart Calendar, future clock-in disabled, reason persisted and response visible to Team Lead. Screenshots were visually checked.
- Existing GPS/selfie endpoint integration passed with synthetic GPS/photo: outside geofence and missing selfie denied, accepted clock-in succeeded, duplicate clock-in denied. Actual clock-out and bounded hours were checked through the shared backend service.

Tests use disposable schema clones and synthetic accounts. No actual employee payroll or attendance records were changed. Human camera/GPS operation on a physical phone remains a manual verification. Leave submission remains a pre-existing limitation as explained above.

Run backend tests: `C:/xampp/php/php.exe Testing/OvertimeWorkflow/backend_test.php`.
Run browser tests: `node Testing/OvertimeWorkflow/ui_test.cjs` (requires the configured Playwright runtime and Chrome).
Run GPS/selfie endpoint tests: `node Testing/OvertimeWorkflow/clock_endpoint_test.cjs`.
