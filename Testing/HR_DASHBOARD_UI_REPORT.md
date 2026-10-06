# HR Dashboard UI/layout update

## Files inspected

- `Html/Admin/admin_dashboard.html`: primary reference, including its Command Center sidebar, header, KPI grid, panels, alerts and activity timeline.
- `Html/HR/hr_dashboard.html`, `Js/HR/hr_dashboard.js`, `Js/HR/hr_overview.js`, `Css/hr_dashboard.css`.
- Shared `ow_dashboard_theme.css`, `ow_collapsible_sidebar.css`, `ow_widgets.css`, `profile_view_switch.js`, `role_guard.js`, `ow_notifications.js` and sidebar behavior.
- `Api/HR/hr_dashboard.php`, `Api/Helpers/hr_dashboard_helper.php`, and the existing integration/controller test runners. The existing API/database flow was traced and tested without changing it.

## Files modified

- `Html/HR/hr_dashboard.html`: overview organization, HR brand/header presentation and scoped body class; existing navigation/menu/bell IDs preserved.
- `Css/hr_dashboard.css`: HR-only Admin design patterns and responsive layout.
- `Js/HR/hr_overview.js`: presentation of existing response fields, compact statistics, pending-action alerts and timeline rendering.
- `Testing/hr_dashboard_frontend_test.js`: updated assertions for four KPIs, compact drilldowns and real pending-action states.

Created this report. No PHP, shared CSS/JavaScript, database, authentication, Admin or other-role dashboard files were changed.

## Admin design patterns reused

The slate sidebar, two-line brand treatment, compact sidebar links, blue active gradient, sticky white header, dark circular avatar, 1.5rem content margins and section spacing, .75rem panel radii, subtle borders/shadows, uppercase .75rem KPI labels, prominent values, 44px colored icon containers and Bootstrap icons are adapted from Admin. Panel titles use the same icon/heading hierarchy. Alerts use Admin's amber/sky notice patterns. Recent activity uses its dot-and-line timeline pattern.

Styles are scoped through `.hr-dashboard` or `#section-overview`; shared styles and the reference Admin page remain untouched.

## HR layout and KPIs

The title is HR Dashboard with the subtitle Workforce and Recruitment Overview.

Four primary KPIs: Total Employees, Active Employees, Currently Deployed, Pending Applications. They retain the existing employee drilldowns or Applicant Review navigation.

First panel row: Workforce Overview and HR Alerts / Pending Actions. Second row: Recruitment / Applicant Overview and Recent HR Activity. Attendance/DTR remains a compact subsection of the alerts/information panel; deployment is a workforce subsection. The paginated employee drilldown remains available.

All seven employee summaries are retained as compact rows: total, active, inactive, AWOL, resigned, recently hired and currently deployed. Recruitment groups total applicants, total applications, pending applications, open job orders, pending job requests and recently hired counts. Existing supported review/job/deployment destinations remain accessible.

Unread notifications are removed from the KPI area. The original notification bell, badge, identity hydration, profile dropdown, logout and sidebar-toggle hooks are preserved.

## Charts, alerts and recent activity

Existing real-data workforce, deployment, recruitment and DTR bar visualizations are reorganized rather than replaced. No new chart library is introduced.

Pending applications and pending job requests produce actionable alerts only when their existing API counts are greater than zero. Application alerts select the Applied filter; job-request alerts open Job Orders. Both zero produces a clear no-pending-actions state. An API failure displays unavailable data rather than a fabricated all-clear state.

Recent activity still renders the same actual application, hire, job-order and HR audit records supplied by the API, now as a compact timeline. Empty history retains its existing empty state. Database strings continue to use textContent.

## Existing data integration preserved

HR HTML → `hr_overview.js` → `Api/HR/hr_dashboard.php` → `hr_dashboard_helper.php` → existing mysqli connection/MariaDB.

Summary/drilldown endpoints, source tables, queries, recruitment rules, date windows, authorization and all business logic remain unchanged. Existing module navigation calls the same HR SPA loaders. No sample values are rendered by the application. Synthetic response fixtures appear only in the controller tests.

## Responsiveness

The primary KPI grid follows Admin's breakpoints: one column below 576px, two from 576px, four from 1280px. Main panels stack below 992px and form two columns above it. Compact statistics stack on small screens. Content/header/panel padding is reduced on phones; the avatar remains visible while the long identity text is hidden. Charts wrap long labels, panel actions wrap, and the employee table remains horizontally scrollable. Existing collapsible/drawer sidebar behavior is retained.

## Tests and results

- **PASS — 75 HTTP/database integration assertions:** rerun against local XAMPP MariaDB using an isolated temporary PHP server/session directory. Existing real counters, charts, attendance/DTR, activities, employee drilldowns, refresh, HR authorization and existing HR read APIs passed.
- **PASS — 31 JavaScript controller/SPA assertions:** four primary KPIs, unknown loading values, refresh/deduplication, compact employee drilldowns, recruitment/job navigation, pending-action alerts, no invented zero-count alerts, empty/error/retry/stale states, visible-only polling and actual shared SPA routing passed.
- **PASS — static:** JavaScript syntax and unique HTML IDs. No PHP was edited; the HTTP tests exercised the preserved PHP endpoints successfully.
- **PASS — scope:** SHA-256 comparison found exactly three application files changed: HR HTML, HR-scoped CSS and HR overview JavaScript. Admin, all PHP APIs/helpers, shared notifications, role guard and other dashboards were unchanged.
- **NOT VERIFIED — browser:** no authenticated browser tab was available. Full visual desktop/mobile rendering, actual browser console, profile dropdown clicks, bell clicks/badge rendering, mobile drawer interaction and CDN availability were not runtime-tested. Their existing selectors/handlers and dependencies were inspected and preserved. A DOM harness is not a browser engine.
- **NOT VERIFIED — complete application regression:** HR write workflows and other-role runtime workflows were not exercised by this UI-only task.

The temporary test server was stopped after verification. No application/database records were inserted, updated or deleted.
