# Admin list enhancements

## Inspected and modified
- `Html/Admin/admin_dashboard.html`: existing Applicant Review/Deployments tables and embedded contract/conversion pages.
- `Js/HR/hr_dashboard.js`: existing loaders, row rendering and ID-based review/end actions.
- `Html/HR/applicant_contracts.html`, `Html/HR/employee_conversion.html`: existing eligible-list caches, search/status controls, uploads, conversion modal and direct application links.
- `Api/HR/applicant_review.php`, `Api/HR/deployments.php`, `Api/HR/get_conversion_list.php`: existing authorization, joins, status conditions and response mapping.
- Inspected existing `Js/Shared/pagination_ui.js`, contract expiry/parser, application authorization and installed-schema queries.

## Added shared helpers
- `Api/Helpers/list_query_page.php`: optional server pagination with prepared search/filter/count/limit queries. Existing callers without `page` retain their response behavior.
- `Js/Shared/managed_list.js`: consistent responsive controls, default 10 rows, optional 25/50, 300 ms search debounce, result ranges, compact shared Bootstrap pagination and Reset.

## Search and filters
- Applicant Review: application/applicant IDs, name, email, phone, position, company and stored status text. Existing application status selector retained; company and position filters added. Existing default excludes hired applications; Hired selector retains its original scope.
- Deployments: deployment/employee IDs, employee number/name, company, site location, position and Team Lead. Filters: company, location, Team Lead and Active/Ended derived from the existing end-date rule.
- Contracts: application/applicant IDs, applicant name, position and company. Filters: existing pending/open/expiring/expired states, company and position.
- Conversion: application/applicant IDs, applicant name, position and company. Filters: company, position and readiness based on the existing contract-upload flag (Ready to convert / Contract required).
- Contract/conversion lists retain their original eligibility: approved by client, not hired. No new conversion status or historical-record workflow was introduced.

## Query behavior and security
Server-side pagination avoids fetching every eligible record. Search, selected filters, existing eligibility and counts use the same query scope. Values are bound; SQL columns come from internal allowlists. LIKE wildcard characters are escaped for literal substring searches. Page and size are validated and pages clamped after changes/deletions. Filters use distinct values from existing records, fetched only on first list load. Ordering uses the existing date and an identity tie-breaker.

Reset clears search/filters, restores size 10 and page 1, then reloads. Search, filter and size changes reset to page 1. Loading replaces rows and removes page controls; superseded fetches are aborted and request sequence guards discard stale responses. Empty results show explicit messages and omit pagination controls.

Session role checks remain; Deployments and Conversion now also use the same active-account database authorization as Applicant Review. Browser IDs/filters never authorize access. Existing rendering/actions remain, passing entity IDs rather than page indexes. Conversion direct links fetch the requested eligible application, open its modal, and restore the full list afterwards.

Deployment filters use actual deployment company/location/supervisor joins. A Team Lead can match deployments in multiple companies/sites; no one-company constraint was introduced. There are no new APIs, database tables, migrations or persistent test data. Existing contract expiry maintenance remains unchanged.

## Verification
- `query_test.php`: 7 checks pass using connection-local temporary synthetic rows: pages/counts, combined filters, page clamping, literal wildcard/injection searches, reset and multi-site lead.
- `schema_query_test.php`: all 3 production list queries prepare/execute against installed schema with read-only zero-result searches, including filter option/count queries.
- `auth_test.php`: 16 checks pass: all 3 endpoints deny anonymous, Employee, TeamLead and inactive Admin identities despite manipulated filters; malformed pagination/search/filter inputs rejected.
- `ui_test.cjs`: all 4 modules at 1366px and 390px pass: page 2, search returns page 1, combined company/search, all existing application/contract status parameters, Reset, empty results, responsive control widths and ID-based action dispatch.
- Existing `Testing/JobOrderRequests/frontend_test.js`: 37 checks pass, including parsing dashboard scripts and existing action/filter regressions.
- Changed PHP and JavaScript syntax checks and Git whitespace check pass.

UI tests use synthetic intercepted API responses and action spies, not live approvals, contract uploads, deployment ending or employee conversion. Production mutation workflows were deliberately not executed during list testing. Database query tests are read-only except for a temporary synthetic table that vanishes on connection close.
