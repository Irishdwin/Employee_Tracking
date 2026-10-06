# Team Lead → HR Job Order Requests implementation report

Completed in the connected Employee_Tracking project, 2026-10-05, following the audited workflow and subsequent PROCEED authorization.

## Problems and root causes

- HR JavaScript already had request review functions, but HR HTML lacked the request queue and review/rejection modal elements. HR could not complete the workflow through its dashboard.
- Approval performed several independent writes without a transaction or request lock. Repeated/concurrent decisions could create duplicate orders or overwrite a completed decision; failures could leave partial records.
- Submission did not notify HR or record a submission audit event. Decision notifications were outside the complete atomic workflow.
- Team Lead company selection and server checks used inconsistent assignment sources, including expired deployments. The selector disabled selection even with multiple companies.
- Request reads invoked schema creation/alteration helpers. These request paths now rely on the inspected existing schema.
- Queue errors could appear as empty results, and older asynchronous responses could replace a newer selection.

## Implemented flow

Team Lead dashboard → `submit_consolidated_jor.php` → validated assigned company → Pending parent and placement details → existing notification/audit infrastructure → HR dashboard Job Orders request queue → full placement preview → approve/reject.

Approval locks the Pending request and commits the Open Job Order, all placement links, parent decision, requesting Team Lead notification, and audit event together. Rejection commits its reason, reviewer, timestamp, notification, and audit together. Already processed requests return HTTP 409. Legacy single-position submissions use the same validation and persistence path.

The HR queue is separate from actual Job Orders, defaults to Pending, supports status filtering and Refresh, and includes loading/empty/error states. Review actions use current server status. Decision/Refresh updates existing sidebar badges and HR overview counters. Approved placement rows satisfy the existing recruitment open-slot predicate; applicant pages were not changed.

## Files modified

- `Html/HR/hr_dashboard.html`: request queue and review/rejection modals, reusing existing Bootstrap/Admin request markup and HR styling.
- `Js/HR/hr_dashboard.js`: current-status action visibility, safe error/empty/loading handling, stale-response protection, refresh/counter integration, modal accessibility registration.
- `Html/TeamLead/team_lead_dashboard.html`: request-specific assigned-company API and multiple-company selection.
- `Api/HR/job_order_requests.php`: authenticated active HR/Admin authorization and atomic decision helper; removed request-path runtime DDL.
- `Api/HR/get_jor_details.php`: active HR/Admin authorization; removed runtime DDL.
- `Api/TeamLead/get_tl_job_requests.php`: active Team Lead authorization and assigned-company read action; own-history restriction retained; removed runtime DDL.
- `Api/TeamLead/submit_consolidated_jor.php`: consistent company authorization, schema-length/worker validation, shared category resolution, transactional submission notifications/audit.
- `Api/TeamLead/request_job_order.php`: legacy submission adapter to the consolidated workflow; own-history restriction retained; removed runtime DDL.

## Files created

- `Api/Helpers/job_order_request_workflow.php`: prepared reads/writes, active database-role checks, assignment validation, read-only category resolution, submission notifications/audit, atomic review decisions.
- `Testing/JobOrderRequests/integration.php`: CLI-only isolated fixture suite with cleanup, HTTP RBAC tests, storage-fault rollback test, and separate-process concurrent approval test.
- `Testing/JobOrderRequests/frontend_test.js`: focused real-controller tests with a DOM stub and script syntax checks.
- This report.

## APIs and database

Reused APIs: TeamLead submission, own request history, legacy request endpoint; HR request queue/decision and detail endpoint; existing HR dashboard summary and realtime counters. Added `GET Api/TeamLead/get_tl_job_requests.php?action=companies`; no new notification API/system.

Existing tables used: `users`, `roles`, `clientcompany`, `deployment`, `serviceagreement`, `job_categories`, `job_order_requests`, `job_order_details`, `joborder`, `notifications`, `system_logs`. Existing recruitment open-slot verification reads `job_applications`. Integration fixtures also use `employee`. No database migration or structural change was made.

IDs remain distinct: session `user_id` maps to `users.UsersID`; request `RequestedBy` stores that user; `CompanyID` identifies `clientcompany`; `RequestID` identifies the request; `JobOrderID` identifies its resulting order and links all placement rows. Requests and orders remain separate concepts.

## Security

PASS: shared session expiration preserved; anonymous/expired sessions rejected; session role plus active database role checked; forged HR role rejected; Employee/Applicant/Accounting denied submission/approval; HR/Admin review access preserved; Team Leads cannot approve or read another lead's history; direct/current deployment assignments accepted and foreign/ended assignments denied. Existing registered-site and active signed-agreement prerequisites retained. Prepared statements are used for browser-supplied IDs and values. Notification/audit failures roll back business writes. No authentication helper or shared RBAC behavior changed.

## Tests and results

- PASS — 40 local MariaDB/PHP workflow assertions: real parent/detail persistence, HR listing/preview, live pending dashboard count, notifications, audit, authorization, rejection reason, single-order approval/linkage, repeated decisions, legacy/Admin compatibility, notification-storage failure rollback, concurrent review, recruitment open slots.
- PASS — 37 request UI/controller assertions: required elements exist once, HR JavaScript and HR/TL inline script syntax, loading/empty/errors, escaping, filters/view identity, latest response wins, server-status action visibility, blank rejection reason, rejection conflict, counter refresh, assigned-company picker wiring.
- PASS — existing HR dashboard API regression suite: 82 checks.
- PASS — existing HR dashboard frontend regression suite: 42 checks.
- PASS — PHP syntax checks for all six changed/created production PHP files and integration test.
- PASS — before/after source hashes: only the eight existing production files listed above changed; new workflow helper added. Team Schedule, payroll/holiday/attendance calculations, shared authentication, other role HTML dashboards, HR overview/chart files, and CSS remained unchanged during this implementation. Shared HR request JS/API compatibility with Admin was checked.
- PASS — fixture cleanup: zero fixture companies/users/employees; original request distribution remains four Approved, two Rejected; four Job Orders and six request details remain. Test inserts consume auto-increment IDs, leaving normal gaps.
- NOT VERIFIED — authenticated full-browser interaction, Bootstrap rendering/focus behavior, mobile/responsive visual appearance, complete applicant application journey, external deployment, every unrelated role/module end-to-end.

Tests used the accessible local MariaDB database and a temporary PHP server on port 8894 with isolated sessions. Test-session SQL strict mode was temporarily set on the test connection only to induce a notification storage fault, then restored. No server-global database settings changed.

## Remaining limitations

- Existing consolidated-order behavior is retained: one order header uses the first placement's position/category/schedule; every requested placement remains linked for recruitment. Multi-site headers summarize as Multiple sites.
- Employment type, description, requirements, separate requested-date input, and request UpdatedAt are not added: the existing request schema/form does not provide all these dedicated fields.
- No cross-company reassignment/edit workflow or schema-level unique link constraint was added. Duplicate review prevention is transactional row locking plus conditional Pending updates.
- Existing category migration/schema helpers in unrelated APIs remain outside scope. Signed agreement prerequisite follows existing active status/document-path rules.
- Notifications use the existing in-app system; no email/SMS delivery was added.

Run from the project root with an isolated PHP session directory and a local PHP server sharing it: `php Testing/JobOrderRequests/integration.php http://127.0.0.1:8894`; `node Testing/JobOrderRequests/frontend_test.js`. The integration test creates identifiable temporary fixtures and removes them in finally.
