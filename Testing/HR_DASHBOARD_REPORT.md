# HR Dashboard implementation report

Implemented directly in the connected `Employee_Tracking` project on 2026-10-05. The current working files and live `onazul_hris_db` schema were the source of truth. No database writes or migrations were required.

## Problems and root causes

- The overview contained two shortcuts and no data loader, metrics, charts, activity feed, or data states.
- HR navigation passed the shared SPA default container (`main-content`), while the actual HR markup uses `main-content-area`. The initializer returned null, preventing shared section navigation from binding correctly.
- No HR-specific overview endpoint existed. Existing employee, recruitment, deployment, hiring, attendance and notification sources served their individual modules rather than a coherent overview.
- Employee activation, recruitment stages and resignation completion are separate concepts. Treating attendance gaps as AWOL or treating every resignation request as a completed resignation would produce incorrect counters.

## Flow and inspected dependencies

`Html/HR/hr_dashboard.html` → `Js/HR/hr_overview.js` → `Api/HR/hr_dashboard.php` → `Api/Helpers/hr_dashboard_helper.php` → shared mysqli connection → live MariaDB.

The existing `Js/HR/hr_dashboard.js` remains responsible for module loading. The shared ProfileViewSwitch, role guard, user hydration, sidebar, notifications, Bootstrap, and dashboard theme remain in use. The new helper reuses `application_status_helper.php` for recruitment stages.

Inspected authentication/session/RBAC, database configuration and live columns; HR applicant review/conversion and contract modules; employee/deployment APIs; HR job orders and requests; shared notifications/realtime counters; daily time records/DTR submissions; and resignation completion behavior. No table or column names were invented.

## Files modified

- `Html/HR/hr_dashboard.html`: replaced only the overview, and added its CSS/script references.
- `Js/HR/hr_dashboard.js`: supplied the correct HR SPA container and refreshed overview data when returning to it. Existing unrelated working changes were preserved.

## Files created

- `Api/HR/hr_dashboard.php`: read-only HR overview API.
- `Api/Helpers/hr_dashboard_helper.php`: database queries, canonical recruitment grouping, employee drilldown.
- `Js/HR/hr_overview.js`: scoped rendering and interactions.
- `Css/hr_dashboard.css`: styles scoped to the HR overview, using existing colors and Bootstrap variables.
- `Testing/hr_dashboard_test.php`: CLI-only, read-only HTTP/database integration checks.
- `Testing/hr_dashboard_frontend_test.js`: DOM/controller and actual shared SPA checks.
- `Testing/HR_DASHBOARD_REPORT.md`: this report.

## APIs and tables

Created `GET Api/HR/hr_dashboard.php?action=summary` and `GET Api/HR/hr_dashboard.php?action=employees&filter=...&page=...`.

Navigation uses the existing HR module loaders and their unchanged APIs: `job_orders.php`, `job_order_requests.php`, `applicant_review.php`, `deployments.php`, and shared notifications. No notification records are created or changed by the overview.

Tables read: `employee`, `status`, `resignationrequest`, `applicants`, `job_applications`, `joborder`, `job_order_requests`, `deployment`, `clientcompany`, `daily_time_records`, `dtr_submissions`, `notifications`, `system_logs`, `users`, `roles`.

## Features and definitions

- Total, active and inactive employees: actual employee records and activation flags.
- AWOL: explicit employee status named AWOL only.
- Resigned: explicit employee status or a resignation record marked Resigned by the completion workflow. A pending request is not counted.
- Recently hired: employee HiredDate during the last 30 calendar days, inclusive of today.
- Currently deployed: distinct active employees whose deployment starts on/before today and ends on/after today or has no end date. Future and ended deployments are excluded. Client chart assigns each employee to their latest current deployment so overlapping assignments do not double-count them.
- Total applicant profiles and applications; pending applications means the canonical Applied stage, awaiting initial review.
- Recruitment pipeline, workforce composition, deployment by client and DTR submission status charts use database aggregates. Charts use accessible text and CSS bars with no additional library dependency.
- Open job orders and pending hiring requests use their recorded statuses.
- Today's clock-ins and records without a clock-out use daily_time_records for active employees. No lateness, absence, overtime, payroll or holiday calculations were added.
- DTR submission counts cover the last 30 calendar days and use recorded status labels.
- Recent activities come from application receipts, employee hire dates, job-order creation dates and HR audit records. Private audit descriptions, applicant identities and device/network metadata are not exposed.
- Current HR user's unread notification count links to the existing notification module.

Employee cards open a paginated read-only roster in the overview. Recruitment stage buttons select the existing Applicant Review filter. Job Order and notification cards open their modules; deployment has a Manage link. Total applicant profiles are informational because no matching HR profile-management view exists.

Manual refresh, refresh when returning to the overview, visibility refresh, and one-minute polling while the overview is visible are implemented. Concurrent summary requests are deduplicated. Loading uses unknown values, genuine zeros produce empty states, errors offer retry, and failed refreshes explicitly identify a retained previous snapshot.

## Security

- Shared session initialization and idle timeout are preserved.
- Server checks require an authenticated HR session AND a currently active database account whose role is HR. Admin and all other roles are rejected by the new endpoint.
- GET only, whitelisted employee filters, validated bounded page values, prepared user/page parameters, no-store responses, and safe server error messages.
- Employee roster exposes only employee number, name, position, recorded status, active flag and hired date. No salary, contact details, government identifiers or documents.
- Database strings render through textContent, rather than HTML interpolation.
- The PHP test runner refuses web execution and deletes its own synthetic session files. Tests did not insert or alter application records.

## Verification results

- **PASS — DATABASE / HTTP:** 75 assertions against the actual local MariaDB through a temporary PHP HTTP server. Includes unauthenticated, other-role, forged-role, missing-account and expired-session rejection; method/input validation; summary loading; every employee/applicant/job/notification counter; every canonical recruitment stage; deployment chart totals; attendance and DTR sources; source-record activity matching; employee drilldowns/privacy; refresh; HTML availability; and existing HR read APIs.
- **PASS — JavaScript controller:** 29 assertions for loading, empty states, initial network errors, retained snapshots on failed refresh, retry recovery, metric and chart navigation, employee drilldown, pagination boundaries, notification/job navigation, visible-only polling, actual shared SPA initialization, Applicant Review sidebar navigation and refresh when returning home. These use a lightweight DOM harness, not a browser engine. Synthetic data is confined to test code and is never used by the application.
- **PASS — STATIC:** PHP syntax for new endpoint/helper/test runner; JavaScript syntax for HR scripts/test runner; no duplicate HTML IDs.
- **PASS — SCOPE:** SHA-256 comparison found 306 existing API/JS/HTML/CSS files outside the HR target set unchanged during implementation. Other role dashboards, shared authentication, Team Schedule and calculations were not edited.
- **NOT VERIFIED:** full authenticated browser rendering, browser console, responsive visual layout at mobile/desktop sizes, CDN availability, Apache-hosted end-to-end behavior, and all HR write workflows. Responsive CSS and navigation code were inspected, but inspection is not a visual runtime test.
- **NOT VERIFIED:** other roles' complete runtime regression suites. Their files were unchanged; this does not claim their pre-existing behavior is bug-free.
- **NOT VERIFIED:** high-volume pagination/performance and all-empty database integration. Current data exercises empty AWOL/inactive/resigned rosters and zero attendance; other empty/error presentation paths were exercised in the controller harness.
- **FAIL:** no outstanding failures in the final executed checks. Initial test setup could not write XAMPP's session folder within the workspace sandbox; tests were rerun successfully with an isolated temporary session directory. Application authentication configuration was not changed.

## Remaining limitations / unrelated findings

- The current status catalog has no AWOL or Resigned entry, so those counters are legitimately zero until explicit records exist. The dashboard does not classify employees itself.
- Applicant Review's existing default list excludes hired applications. The total applications card includes hired and opens the default review view; the Hired chart stage selects the existing Hired filter. Existing legacy status-only records can be resolved by the canonical helper differently from that API's SQL filter conditions. The unrelated recruitment API was not changed.
- `Api/HR/resignations.php` references `ClearanceIssuedDate`, which is absent from the live resignationrequest schema. This existing schema/API mismatch was identified statically and left untouched. Dashboard resignation counting uses available columns.
- The dashboard summarizes stored attendance and DTR records, without introducing an HR attendance editing/approval module.
- Employee hire activity has date precision because employee.HiredDate is DATE. Shared notifications have no timestamp column, so the overview shows the current unread count rather than inventing notification dates.

## Reproduce checks

Use the same isolated session.save_path for PHP's temporary server and the CLI integration runner. Start a PHP server rooted at the connected project on port 8894, then run `php Testing/hr_dashboard_test.php` with that matching session setting. Run `node Testing/hr_dashboard_frontend_test.js` from the project root. Stop the temporary server afterward. These checks require local MariaDB and an existing active HR account, but do not require its password or modify its records.
