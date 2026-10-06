# Applicant re-endorsement implementation

Implemented in the connected Employee_Tracking project on 2026-10-05. Existing unrelated workspace changes were preserved.

## 1. Existing workflow

HR and Admin use the shared `Js/HR/hr_dashboard.js` Applicant Review controller in their dashboards. `Html/HR/applicant_review.html` is another existing review screen. `Api/HR/applicant_review.php` loads applications; `get_application_details.php` supplies details and hiring eligibility. Interview scheduling leads to `endorse_to_client.php`, which updates `job_applications`. Both existing Team Lead interfaces use `Api/TeamLead/endorsed_applicants.php`. Applicant tracking uses `Api/Applicant/get_my_applications.php` and the shared status helper. Approved applications continue through contract upload and `hireApplicantFromApplication()` in the existing `Api/Helpers/hire_applicant.php`.

## 2. Previous rejection implementation and problems

The Team Lead API updated `ClientApprovalStatus`/`ClientApprovalDate` and appended optional comments to the application's `Remarks`. It did not require a rejection reason, protect final decisions, notify HR/Admin about reassignment, or preserve separate attempts. The shared status helper called client rejection “Declined.” Repeated endorsement replaced current endorsement dates without a history structure. The details API allowed Team Leads to read applications without checking company scope. These directly relevant issues were addressed.

## 3. Existing tables and statuses

Reused: `job_applications`, `applicants`, `joborder`, `job_order_details`, `clientcompany`, `serviceagreement`, `deployment`, `users`, `roles`, `status`, `job_categories`, `job_classifications`, `job_sub_classifications`, `notifications`, `system_logs`, and existing applicant documents/hiring tables. The current job is `job_applications.JobOrderID`; placement is `target_detail_id`. Company comes from `joborder.CompanyID`. Applicant belongs to `applicants.UserID`.

Actual local recruitment status IDs inspected: 1 For Interview, 2 Endorsed, 3 Hired, 4 Pending, 8 Rejected. Existing client decisions remain Pending/Approved/Rejected. No new status rows or parallel hiring workflow were introduced. The endpoint resolves the existing Endorsed alias instead of hardcoding its ID.

## 4. Client versus agency rejection

Client rejection stays in `ClientApprovalStatus='Rejected'`, with an attempt decision and reason. Its derived workflow stage is `client_rejected`, displayed internally as “Rejected by Client.” Existing agency status Rejected/Declined/Failed is resolved as final `rejected` and cannot be reassigned. A client-rejected application must have an endorsement date and a latest rejected attempt to re-endorse.

## 5. Return to HR/Admin

HR/Admin lists display the client rejection and a Re-Endorse action. Existing Applicant Review badges now include client-rejected, non-hired applications that are not agency-final-rejected. Review details show each company, position, location, sender, decision, reviewer, date, and internal reason. The first attempt identifies the original endorsed opportunity.

## 6. Re-Endorse UI and APIs

The existing review modal includes endorsement history and “Find Another Company.” A shared Bootstrap modal loads real alternatives, shows placement location, available slots, hierarchy and schedule, and requires an internal reassignment reason. It includes loading, empty, error, retry and saving states. Success reloads Applicant Review and the existing badge API.

Existing API expanded: `GET Api/HR/endorse_to_client.php?application_id=...` lists eligible alternatives. Existing POST supports initial endorsement and reassignment with application_id, attempt_id, job_order_id, target_detail_id and reason. No new API route or page was created. Existing Team Lead POST now requires attempt_id and a reason for rejection. Both Team Lead UIs use a required-reason modal; cancellation submits nothing.

## 7. Alternative eligibility

Uses actual available placements and the existing hired-only slot helper. Requires Open Job Order, active company, the Job Order's own linked Active signed service agreement belonging to that company, non-future start, and a valid end date for an Expiring agreement. Open agreements follow the existing Open contract type semantics. An active assigned Team Lead must exist. Matching uses the applicant's valid category/classification/sub-classification preferences; legacy profiles fall back to their reviewed placement hierarchy, or the exact reviewed position when no hierarchy exists. Inactive or inconsistent hierarchy tuples are excluded. Location is shown to HR for selection, since the inspected application has no hard applicant-location restriction.

All previously rejected exact company + Job Order opportunities are excluded, not just the immediately previous attempt. Another valid Job Order at the same company may be eligible. There is no same-opportunity override workflow in this implementation. POST rechecks eligibility and exact placement rather than trusting the dropdown.

## 8. Attempt history and concurrency

The inspected schema had only the current application endorsement and general logs, with no structure capable of preserving multiple typed attempts. Added `application_endorsement_attempts`: application, attempt number, job/company/placement IDs, company/position/location snapshots, endorser/time, decision/reviewer/time/reason, reassignment reason and legacy snapshot indicator. Application IDs remain unchanged. Prior decisions are never rewritten by reassignment. Current application fields move to the new opportunity so existing downstream code continues to work.

Application row locks serialize transitions. Latest attempt tokens reject stale screens, final decisions cannot be overwritten, and a unique generated pending-application key enforces one pending endorsement per application. This preserves the existing independent-job-application model; separate applications by the same candidate remain separate workflows.

## 9. Migration

`sql/application_endorsement_attempts.sql` was applied to local `onazul_hris_db`. Additive table creation and idempotent backfill only. Three existing current endorsements were snapshotted; six live applications remain. Rerunning the migration still produced three attempts. No existing application was reassigned during testing. Legacy data cannot reconstruct earlier overwritten attempts or missing reviewer identity; the UI labels imported snapshots and missing fields honestly. Legacy rejected Remarks are preserved internally as available historical notes, not represented as a newly verified professional reason.

## 10. Notifications and transaction safety

Reused `createApplicationNotification()` and `insert_system_notification()` with the existing notification table. Rejection notifies active HR/Admin users using client_rejected, including the internal reason. Endorsement/re-endorsement notifies active assigned Team Leads using endorsed_applicant and the applicant using application_endorsed. Applicant client_rejected messages explain continuing HR review and omit reasons. Approval retains client_approved. General audit logs use the existing logger. Application, attempt, notification and audit writes are in one transaction; failure rolls them back. No runtime schema DDL occurs in these transitions.

## 11. Authorization

Existing session authentication is preserved. Staff endpoint roles are checked and the helper verifies the actual active database user role. Reassignment is limited to HR/Admin. Team Lead company access reuses `clientcompany.UserID` or `deployment.SiteSuperVisorID` for that company. Both decision writes and application detail reads enforce scope on the server. Details limit Team Lead history to the current company. A stale attempt is denied even when the same Lead happens to supervise the new company. Applicants cannot reassign.

## 12. Applicant tracking

Client rejection displays “Client Review Unsuccessful — For Reassignment.” Existing progress tracking stays at the client-review step. Timeline includes actual endorsements and company decisions across attempts, with company/position snapshots and recorded timestamps. It never serializes rejection or reassignment reasons into the applicant response. Re-endorsement returns the current stage to Endorsed to Client, and timeline identifies it as Re-Endorsed to Client. Final agency rejection remains Declined.

## 13. Capacity

Existing calculations count `job_applications` with non-null HiredDate. Header orders count hires per JobOrderID against max(RequiredCount,1); multi-placement orders count hires per JobOrderID + target_detail_id against max(required_workers,1). Rejected and pending attempts do not consume hired slots. Calculation formulas were not changed. Availability is checked again during reassignment; pending endorsements do not reserve slots, matching the existing model.

## 14. Hiring integration

Latest company approval updates the existing current application fields. Contract upload and employee conversion remain the existing workflow, using the new JobOrderID/target_detail_id. Reassignment clears a previous client's `Contract uploaded:` hiring marker without deleting documents or other remarks, requiring the existing new-contract step. Actual existing hiring helper was executed successfully in the isolated database, creating an employee and deployment for Company B and its Lead. Hiring helper and deployment code were not modified for this task.

## 15. Files modified for this task

- Api/HR/endorse_to_client.php
- Api/HR/applicant_review.php
- Api/HR/get_application_details.php
- Api/TeamLead/endorsed_applicants.php
- Api/Applicant/get_my_applications.php
- Api/Helpers/application_status_helper.php
- Api/Helpers/hr_dashboard_helper.php (recognize the new derived stage in real recruitment totals)
- Api/Common/realtime_counters.php (existing Applicant Review badge)
- Js/HR/hr_dashboard.js (shared HR/Admin recruitment controller)
- Html/HR/hr_dashboard.html (recruitment filter/script only)
- Html/Admin/admin_dashboard.html (recruitment filter/script only)
- Html/HR/applicant_review.html
- Html/TeamLead/endorsed_applicants.html
- Html/TeamLead/team_lead_dashboard.html (endorsed-applicant review only)
- Html/Applicant/my_applications.html (client-review status badge/progress only)

## 16. Files created

- Api/Helpers/application_endorsement.php
- Js/Shared/applicant_reendorsement.js
- Js/Shared/client_review_reason.js
- sql/application_endorsement_attempts.sql
- Testing/Reendorsement/workflow_test.php
- Testing/Reendorsement/frontend_test.js
- Testing/Reendorsement/IMPLEMENTATION_REPORT.md

## 17–18. Executed tests and results

Database suite: `C:\xampp\php\php.exe Testing/Reendorsement/workflow_test.php` — 42 PASS. It clones the actual schema without business data into a randomly named test database, uses fixture records and the actual helpers, and removes only that test database afterward. No leftover test databases were found. An initial CLI session-directory warning was resolved in test setup; final runs were clean.

- Test 1 initial A endorsement and Lead notification: PASS.
- Test 2 A rejection, required reason, reviewer and return eligibility: PASS.
- Test 3 B reassignment and current Job Order: PASS.
- Test 4 old Lead denial and stale token denial: PASS.
- Test 5 B approval: PASS in a separate fixture branch, because an approved decision cannot later be rejected.
- Test 6 B rejection and return: PASS.
- Test 7 C endorsement after A/B rejection: PASS.
- Test 8 HR/Admin, new Lead and safe applicant notifications: PASS.
- Test 9 same rejecting opportunity blocked: PASS.
- Test 10 prior history unchanged: PASS.
- Test 11 existing contract requirement and successful employee conversion targeting B: PASS.
- Additional: closed/inactive/expired/unsigned/date-ineligible/hierarchy-incompatible/no-active-Lead opportunities excluded; exact placement and location; full slots excluded; caller RBAC; agency/client distinction; public timeline privacy; prior contract marker invalidation; hired-only capacity; duplicate pending attempt and repeat final decision blocked; notification failure rollback for both endorsement and rejection: PASS.

Frontend suite: `node Testing/Reendorsement/frontend_test.js` — 21 PASS for escaping, history, action eligibility, selection, attempt/placement submission, refresh, required reasons, cancellation, optional approval remarks and loading/empty/error/retry states. Uses mock DOM/transport, not a signed-in browser.

Existing regression suite: `node Testing/hr_dashboard_frontend_test.js` — 41 PASS.

PHP lint: all 10 modified/new PHP implementation/test files — PASS. JavaScript syntax: new shared scripts and modified HR controller, plus inline scripts in all six affected HTML pages — PASS.

Live Apache unauthenticated HTTP requests to endorsement, Team Lead reviews, HR details and HR review — HTTP 401 PASS.

Migration rerun, snapshot count, live application count and temporary DB cleanup — PASS.

Signed-in endpoint responses, browser visual/responsive appearance, and complete HR/Admin → Team Lead → Applicant click-through using normal authenticated sessions: NOT VERIFIED. No authentication bypass or fabricated browser session was used. Other role dashboard runtime sessions: NOT VERIFIED; their layouts and controllers outside the specified recruitment hooks were not changed.

## 19. Remaining limitations

Legacy overwritten decisions and missing reviewer/date data cannot be recovered. Opportunities without a usable hierarchy use the conservative legacy-position fallback. Pending endorsements do not reserve slots; another hire may fill a slot before review completes, as in the existing capacity model. The existing hiring helper does not add a new last-moment capacity reservation here. Same previously rejected Job Order re-entry has no override path. Existing HR JavaScript references a simulate_client_approval.php route that is absent from the connected project; this unrelated legacy reference was not implemented. Full authenticated browser acceptance remains required.
