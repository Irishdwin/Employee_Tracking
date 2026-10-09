# Historical Attendance Testing — implementation and verification

Implemented in the connected project on 2026-10-09. No database migration or production clock-in/out or payroll formula change.

## 1. Root cause

The selection helper first called `site_owned_employee()`, whose production roster query requires a deployment covering **today**. Only afterwards did it compare the test date with that current deployment. The employee selector likewise excluded ended deployments. The attendance-list schedule was resolved for today instead of the selected date. Date changes refreshed holiday/rate previews without resolving and switching the deployment context. Consequently, a legitimate earlier deployment could never satisfy the selected current deployment ID.

## 2. Files modified

Testing-only APIs under `C:/xampp/htdocs/Employee_Tracking/Testing/Attendance/Api/`: `_selection_context.php`, `_testing_guard.php`, `get_companies.php`, `get_sites.php`, `get_teamleads.php`, `get_employees.php`, `get_employee_payroll_context.php`, `get_employee_attendance_list.php`, `get_payroll_verification.php`, `save_attendance.php`, `delete_attendance.php`, `save_test_overtime.php`.

Frontend: `C:/xampp/htdocs/Employee_Tracking/Testing/Attendance/attendance_testing.js`.

Tests: `historical_attendance_test.php`, `historical_attendance_ui.cjs`, `historical_attendance_results.json`, `historical_holiday_regression.json`, and the existing `holiday_e2e_runner.php` and `late_minutes_ui.cjs` under the same Attendance directory. The holiday runner now resolves selected historical contexts by date; its deliberately invalid post-finalization review fixtures are inserted only inside its disposable CLI database, since public testing APIs correctly refuse locked writes.

## 3. Frontend validation

Employee/date selection requests the existing payroll-context API in read-only resolution mode. The returned company, site, stored site owner and deployment ID replace stale selections. Dependent choices, historical schedule, holiday details, cutoff and attendance records refresh automatically. Save stays disabled while resolving, when no deployment covers the date, or when the period is locked. Older asynchronous date/rate responses are ignored. The selected date is retained after saving. Past dates have no artificial minimum.

No-deployment message: “No deployment was found for this employee on the selected date. Please choose a date within an existing deployment period.” The date controls remain visible so a former employee/deployment can be tested even when today's date is ineligible.

## 4. Backend validation

The date resolver uses the same inclusive StartDate/EndDate predicate and latest-StartDate/DeploymentID ordering as the production schedule resolver. Every mutation still checks employee identity, company/site membership, stored Team Lead site ownership and exact resolved deployment ID. Invalid calendar dates and invalid clock times are rejected. Current/future inactive employees remain ineligible; covered historical dates can use archived employees, companies and sites.

Create now rejects an existing attendance or daily-time-record row with HTTP 409. Existing evidence is not silently replaced. Changes require explicit Edit Record. Attendance, its daily-time-record mirror, existing EvidenceDeploymentID and audit entry commit together; errors roll the transaction back. Overnight Time Out uses the following calendar date only where the effective schedule is overnight.

## 5. Historical deployment resolution

Earlier deployments are discoverable through testing-only history selectors. The preview resolves the applicable deployment and mapped site automatically; no start/end dates are created or extended. Genuine gaps remain invalid. Missing site mappings must be corrected through the authorized deployment process rather than guessed from free-text locations.

## 6. Schedule, holiday and cutoff integration

Reuse `payroll_resolve_deployment_schedule()` and its existing dated/FixedHistory schedule entries, including breaks, grace and working days. Reuse the Admin holiday table, `holidays_fetch_for_range()` with employee/deployment scope, and existing Payroll Type A/B cutoff helpers. Holiday metadata comes from the unchanged production calculator. Where overnight/incomplete holiday duty or an unconfirmed client rule requires Accounting review, attendance can be saved with an explicit review message and recorded holiday identity; no unconfirmed holiday pay multiplier is stored.

## 7. DTR/payroll/security protection

The tool requires both an Admin session and an active database Admin account. Employees, Team Leads, Accounting and anonymous requests cannot insert attendance. Dates covered by submitted DTRs, published/approved/finalized payroll or approved payroll runs are blocked even if no attendance row exists yet. Edit/delete and test overtime apply the same period protection. Use the authorized Add Back/correction process or disposable fixtures for those periods; this tool does not reopen or rewrite their payroll.

Test rows retain the existing test geofence marker, an explicit Attendance Testing Tool remarks prefix and actual deployment evidence ID. Changes use the existing system audit log. The existing deployment instruction remains: exclude the development `Testing` directory from production deployment; authentication does not turn this into an employee historical clock endpoint.

## 8. Executed tests

- 33 isolated real HTTP/backend checks passed: yesterday, last week, last month, previous cutoff, previous site/deployment, historical regular/special holidays, dated historical schedule, overnight attendance, overnight holiday Accounting review, invalid deployment bounds, wrong deployment ID, duplicate rejection with unchanged evidence, explicit edit/grace, anonymous/non-Admin denial, submitted/finalized locks, audit logging, archived historical records and production checksum protection.
- Browser checks at 1366px and 390px passed: dated company/site/lead/schedule/cutoff/records refresh, correct historical save payload, no-deployment and locked-period messages, and stale requests ignored. These browser checks use mocked synthetic APIs; real API behavior is covered by the disposable HTTP suite.
- Existing 13 holiday/payroll end-to-end scenarios and 13 Accounting-review guards passed, with disposable database cleanup and matching production checksums.
- Existing late-minute regression: 12 PHP checks and desktop/mobile browser checks passed.
- PHP/JavaScript syntax and whitespace checks passed for the changed code.

Evidence: `C:/xampp/htdocs/Employee_Tracking/Testing/Attendance/historical_attendance_results.json` and `historical_holiday_regression.json`; browser screenshots `historical-attendance-1366.png` and `historical-attendance-390.png` in that directory.

## 9. Remaining limits

Historical attendance requires an actual existing deployment and site mapping. Missing old fixed-schedule archives cannot be reconstructed; the existing production resolver's available historical/base records remain authoritative. Team Lead ownership uses the site's stored owner, since this task does not invent historical ownership records. Existing payroll salary-source precedence is retained; the rate card explains that current employee salary fields are used by the current payroll path. Ambiguous holiday/overnight pay remains subject to the existing Accounting review rule. Business-data tests used isolated schemas; real applicant/employee attendance and finalized payroll were not edited by these tests.
