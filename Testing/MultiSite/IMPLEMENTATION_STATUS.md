# Multi-site implementation and verification report

Implementation is saved in the connected project. AVG quarantined required helpers during development; the user restored work_site_geofence.php, its stale dependency was repaired, and final syntax checks confirm it remains present. Isolated API/calendar testing passes; authenticated UI, real GPS hardware, and full payroll/billing/export regression remain NOT VERIFIED.

1. Previous relationship: clientcompany.UserID identifies a company contact/Lead; each employee has a deployment.SiteSuperVisorID; GPS belongs to clientcompany. No stable branch entity existed.
2. Implemented relationship: clientcompany → deployment_sites.SiteID → employee deployment.SiteID. One users identity may own multiple sites, including different companies.
3. Database support: multiple employee deployments existed, but distinct same-company branch geofences and assignments could not be represented. Additive schema was necessary.
4. Assumptions found: Admin excluded already-assigned Leads; company GPS conflated branches; rosters included historical rows; DTR submission selected an arbitrary owned deployment; Accounting grouped branches together.
5. Company creation: initial site name and existing Lead selection added, preserving the existing agreement upload/company workflow. End-to-end company creation is NOT VERIFIED.
6. Existing Lead: Admin site configuration lists all active TeamLead users; the same user can be assigned to multiple branches.
7. New Lead: site configuration calls the existing create_team_lead_user_account function, preserving email uniqueness/password validation/hash/role. Additional account-creation tests remain NOT VERIFIED.
8. Deployment Sites: new Team Lead sidebar/page lists authorized sites with real current employee counts and View Employees.
9. Employees: site-filtered roster and current-deployment helper. HTTP branch isolation PASS.
10. Geofence: stable SiteID selection; independent site coordinates/radius; Employee resolves its active deployment SiteID. HTTP storage/isolation PASS; physical GPS/phone/browser saving NOT VERIFIED.
11. Schedule: site selection/filtering and server checks added without changing schedule calculations. HTTP roster/isolation PASS; dated schedule save and CSRF rejection PASS; full schedule UI regression NOT VERIFIED.
12. DTR: site-first empty state and server-filtered employee review. HTTP PASS; browser interactions NOT VERIFIED.
13. Submission: stores SiteID, EmployeeID, DeploymentID, session Lead and cutoff; rejects cross-branch employees and validates production cutoff. HTTP PASS.
14. Accounting: site-grouped submission list, site-filtered detail and verification. HTTP PASS. Payroll run/client billing/export regression NOT VERIFIED; their formulas were not changed.
15. Payroll Type: clientcompany.PayrollType remains authoritative. No new per-site payroll type override was introduced.
16. Calendar: existing payroll_type_helper.php wraps production Type B resolver and supplies Type A. No calendar/formula changes.
17. Reminder start: production period.payday minus five calendar days. Type A/Type B activation, overdue persistence, mixed company types and February payday clamping PASS (12 calendar assertions).
18. Generation: lazy during Team Lead counter polling, and refresh after successful DTR submission.
19. Deduplication: notifications.DTRReminderKey is unique on Lead + Site + cutoff start/end + reminder type; per-user database advisory lock serializes refresh. Repeated HTTP generation PASS; concurrent stress test NOT VERIFIED.
20. Badge: counts actual pending site/cutoff obligations from active eligible deployments; submitted/verified rows suppress employee obligations. HTTP badge 2 → 1 → 0 PASS; reading bell reminders does not clear actionable badge PASS.
21. Security: authenticated roles preserved; site ownership derived from session and active database assignments; roster/geofence/DTR/schedule reject manipulated site IDs. HTTP PASS. All role browser sign-ins NOT VERIFIED.
22. Migration: sql/deployment_sites_migration.sql applied to local MariaDB. Adds deployment_sites, indexed SiteID columns on deployment/dtr_submissions and unique nullable notification reminder key. Safely maps companies with at most one distinct legacy location; all current deployment rows mapped. No business table dropped/truncated or deployment history deleted. Multiple ambiguous legacy locations require manual assignment.

23. Files changed for this request:

- Api/Admin/client_companies.php
- Api/HR/deployments.php
- Api/Helpers/hire_applicant.php
- Api/Helpers/work_site_geofence.php — restored; includes site ownership and reminder helpers
- Api/Helpers/team_schedule_helper.php
- Api/Helpers/job_order_request_workflow.php
- Api/TeamLead/team_deployments.php
- Api/TeamLead/get_work_site.php
- Api/TeamLead/register_work_site.php
- Api/TeamLead/get_tl_attendance_roster.php
- Api/TeamLead/get_employee_attendance_logs.php
- Api/TeamLead/get_overtime_roster.php
- Api/TeamLead/save_overtime.php
- Api/TeamLead/team_schedule.php
- Api/TeamLead/get_dtr_cutoff_review.php
- Api/TeamLead/submit_dtr.php
- Api/TeamLead/team_lead_counters.php
- Api/Employee/dashboard_data.php
- Api/Employee/get_employee_work_site.php
- Api/Accounting/get_submitted_dtrs.php
- Api/Accounting/verify_dtr_submission.php
- Html/Admin/admin_dashboard.html
- Html/HR/hr_dashboard.html — deployment selector script only
- Html/TeamLead/team_lead_dashboard.html
- Html/Accounting/accounting_dashboard.html
- Js/Admin/admin_dashboard.js
- Js/Teamlead/tl_attendance_sync.js
- Js/Teamlead/tl_team_schedule.js
- Js/Shared/ow_notifications.js — notification-open event only

Files created: sql/deployment_sites_migration.sql; Js/Admin/deployment_sites.js; Js/Shared/deployment_site_select.js; Js/Teamlead/tl_deployment_sites.js; Testing/MultiSite/setup.php; Testing/MultiSite/http_test.cjs; Testing/MultiSite/calendar_test.cjs; Testing/MultiSite/extended_test.cjs; Testing/MultiSite/runtime.json; this report and RECOVERY.md. Initially separate deployment_sites.php/site_dtr_reminders.php helpers and a PHP test runner were removed by antivirus; functions were consolidated into the canonical geofence helper, which the user has now restored. The consolidated helper is the only dependency; quarantined separate helpers are not required.

24. Tests performed: final scoped PHP syntax, external JavaScript and dashboard inline JavaScript syntax checks PASS. Fresh isolated clone of actual MariaDB schema/data: 34 HTTP assertions PASS, 12 calendar assertions PASS, 8 extended API assertions PASS (54 total). Extended checks cover assigned Lead selection, schedule CSRF/load/save with notification, inaccurate GPS rejection, former-Lead geofence denial, existing account reuse and empty-site reminder exclusion. Database codex_multisite_20261005_r2; temporary PHP server port 8895. Fixtures and authenticated API test sessions affect only isolated copies, not production browser sessions or business records.

25. Required scenario results:

- 1. Same Lead/two sites: PASS (API).
- 2. Admin existing Lead assignment: PASS (API); new-account creation and company creation NOT VERIFIED.
- 3. Deployment Sites: PASS (API); page interactions NOT VERIFIED.
- 4. Employee filtering: PASS (API).
- 5. Unauthorized sites: PASS (API).
- 6. Independent geofences: PASS (simulated API saves); physical GPS and phone NOT VERIFIED.
- 7. Site-first DTR: PASS (API); UI NOT VERIFIED.
- 8. Site-specific submission: PASS (API).
- 9. Accounting branch detail/verification: PASS (API); UI NOT VERIFIED.
- 10. Type A reminder boundary: PASS.
- 11. Type B reminder boundary and February clamp: PASS.
- 12. One Lead across two payroll types: PASS.
- 13. Badge 2 → 1 → 0 and reading bell independence: PASS.
- 14. Reminder deduplication: PASS (sequential refresh); concurrent stress NOT VERIFIED.
- 15. Reassignment: PASS; explicit removal UI NOT VERIFIED.
- 16. Single-site compatibility: PASS (API after reassignment); existing browser workflow NOT VERIFIED.
- 17. Scoped syntax, roster, schedule save, DTR and Accounting APIs: PASS. Full attendance, payroll, billing, export, job-order and notification browser regression NOT VERIFIED.

26. Remaining limitations: the Codex browser session was on the login page; a fresh Team Lead sign-in was requested for UI verification. Physical GPS requires a device at the intended official site with browser location permission and sufficient measured accuracy; simulated coordinates do not verify device acquisition or real clock-in/out. New-account creation, company upload workflow, explicit assignment removal UI, complete frontend loading/error/race behavior, concurrent reminder stress and full payroll/billing/export regression need further runtime verification. Ambiguous multi-location legacy deployment mappings require deliberate site assignment. Test databases/copies remain available for reproducibility. No fixture branches, employees or test geofences were added to production.
