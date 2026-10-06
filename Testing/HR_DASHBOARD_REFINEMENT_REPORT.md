# Targeted HR Dashboard refinement

## Exact files modified

- `Html/HR/hr_dashboard.html`: independent column containers; Admin bell markup; four sidebar badge hooks; existing Admin badge script reference.
- `Css/hr_dashboard.css`: column stacks and HR-scoped Admin bell/badge/alignment styles.
- `Js/HR/hr_overview.js`: user-facing location labels and sidebar counter refresh on the dashboard Refresh button.
- `Testing/hr_dashboard_frontend_test.js`: location, column structure, existing badge renderer and bell hook checks.
- `Testing/hr_dashboard_test.php`: existing counter/notification API and database verification.

Created this report. Admin files, shared notification code/styles, sidebar badge renderer, business APIs, helpers, authentication, database schema, calculations and other role dashboards were unchanged. SHA-256 baseline comparison confirms only the three named application files changed.

## Location label

The previous `Manila` suffix was hardcoded in `Js/HR/hr_overview.js` for activity timestamps and the refreshed-at label. Both now say `Cagayan de Oro`. The stored activity timestamp is rendered unchanged. JavaScript still formats refreshed-at time with `Asia/Manila`; PHP/database timezone configuration and API timezone values remain unchanged. No global replacement was performed.

## Independent column layout

The former four-panel grid shared row heights: the taller Workforce Overview determined when both second-row panels began, leaving a gap above Recent HR Activity.

The desktop grid now contains two flex columns. The left stacks Workforce Overview then Recruitment / Applicant Overview. The right stacks the existing HR alerts/Attendance & DTR panel then Recent HR Activity. Each column has its own 1.5rem gaps and content-driven heights. No absolute positioning, offsets, negative margins or JavaScript positioning were added. Below 992px the columns stack in normal flow; existing small-screen padding and table/chart responsiveness remain.

## Admin notification presentation reused

HR now uses Admin's `cc-notif-btn` styling, `bi bi-bell fs-5` icon and the exact Bootstrap-positioned red badge pattern: `data-global-notif-badge`, top/start/translate positioning, rounded pill and white border, .65rem font and 1.1rem minimum width. Bell padding, hover background and header-action gap match Admin. Styles are HR scoped.

The `owNotifBellBtn` ID is retained. The unchanged shared `ow_notifications.js` recognizes the badge hook, updates unread counts, opens the existing in-page Notifications section, refreshes its list and handles marking read. HR did not have a separate popup implementation to replace, and this task did not introduce one.

## HR sidebar badges and data

The unchanged `Js/Admin/admin_sidebar_badges.js` is loaded as the existing reusable component. HR already calls its `apply` method from its five-second realtime counter poll when the component is available. Its configured element IDs are retained, including the `admin` prefix, to reuse the component without changing Admin behavior.

All counts come from existing authenticated `Api/Common/realtime_counters.php` queries:

- Job Orders (`hrJobOrdersBadge`, `job_orders`): job_order_requests with Status Pending.
- Applicant Review (`adminApplicantReviewBadge`, `applicant_review_pending`): the existing Admin/HR review/interview queue query against job_applications/status; unhired, not approved/rejected, not endorsed, with null status or For Interview status.
- Applicant Contracts (`adminApplicantContractsBadge`, `applicant_contracts_pending`): approved, unhired applications missing the existing contract-upload marker in Remarks.
- Employee Conversion (`adminEmployeeConversionBadge`, `employee_conversion_ready`): approved, unhired applications with that contract-upload marker.

The marker is produced by the existing `Api/HR/upload_contract.php` workflow. No browser-only derivation or new endpoint was introduced.

The existing renderer hides zero badges, shows positive counts, caps display at 99+, and supplies accessible labels. Counts refresh on reload, the existing five-second poll, and dashboard Refresh. Existing HR conversion code also invokes this renderer's poll after conversion.

No Deployment badge was added because the inspected workflow does not define a pending deployment-action count. No badge was added to Agency Contact or Dashboard because neither represents a pending workflow.

**Existing definition limitation:** the Admin/HR Applicant Review sidebar count is a different queue definition from the dashboard's Applied-only Pending Applications KPI. Its existing SQL does not include explicit Pending status records. This task reuses that same Admin mechanism without changing shared recruitment rules or the counter API. It should not be read as a count of every Applied-stage record.

## Verification

- **PASS — location/controller:** activity retains its exact timestamp with the Cagayan de Oro suffix; refresh display changes; technical timezone remains Asia/Manila.
- **PASS — layout structure/static CSS:** separate left/right containers and correct panel ownership; one-column base, two-column desktop breakpoint, independent flex stacks. Actual pixel layout is not covered by this static result.
- **PASS — existing renderer:** positive review/contract/conversion counts render, zero job/review counts hide, polling updates from response data. Synthetic counts exist only in test fixtures.
- **PASS — API/database:** 82 read-only HTTP/database assertions against local MariaDB, including all dashboard counters/charts, record-based activity, HR-only dashboard RBAC, existing sidebar counts matched to their SQL definitions, actual unread count, and existing notification-list API.
- **PASS — JavaScript:** 42 controller/SPA assertions, including the added refinement checks and preserved loading/error/refresh/navigation behavior.
- **PASS — static:** JavaScript syntax, PHP test-runner syntax, unique HTML IDs, all relative local script/style assets exist, application scope hash check.
- **NOT VERIFIED — browser visuals:** rendered desktop/tablet/mobile geometry, exact visual equivalence of bell/badge positioning, browser console and responsive drawer behavior. No authenticated browser session was used.
- **NOT VERIFIED — notification UI runtime:** browser bell click, in-page notification rendering, and mark-as-read interactions. The existing handlers and selectors were inspected and preserved; real notifications were not mutated for a styling test.
- **NOT VERIFIED — full regression:** other role dashboards and all HR write workflows. Their application code was not modified.
- **FAIL:** no outstanding failure in the executed checks. The existing review-queue definition limitation above remains unchanged.

The temporary PHP test server was stopped. No application database records were written.
