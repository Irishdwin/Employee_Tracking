# Employee exit workflow implementation

Implemented directly in Employee_Tracking on 2026-10-09. The additive migration has been applied. Existing employee, attendance, DTR, payroll and notification records were not changed by implementation or testing.

## Existing behavior and repaired gaps

The project already had a resignationrequest table, standalone employee/HR/Accounting pages, notification and audit helpers, employee/deployment statuses, payroll history, schedules and holidays. The old employee endpoint required online submission and assumed a Monday–Friday notice calendar. Clearance could complete without assigned Team Lead verification. Accounting used an unsupported salary/deduction formula; uploading a quitclaim could mark it signed. Final completion did not require recorded actual payment and did not disable the matching login or consistently set Resigned status. Several old fields referenced by endpoints were absent from the live schema.

The replacement uses the existing resignation table, shared statuses, scheduling/holiday helpers, recorded payroll references, notifications and audit logger. No new payroll calculation engine or benefit formula was introduced.

## Physical document workflow

1. HR/Admin registers the personally received physical letter against an active employee. The record captures receipt date, optional requested last working date, reason/reference/remarks, company, site and responsible Team Lead. Online employee submission is disabled; employees have a read-only progress page.
2. HR approves or returns the request for correction. Corrected letters can be resubmitted for review.
3. HR records physical clearance issuance and its reference.
4. The currently assigned Team Lead for the pinned site verifies the physical clearance and approves or returns it with remarks. Scope includes all assigned sites, not one primary company. Pinned clearance history remains available after that deployment ends. HR cannot impersonate Team Lead approval.
5. HR explicitly acknowledges receipt of the signed physical clearance and its acceptance date. Team Lead approval alone does not complete clearance acceptance or resignation.
6. Accounting starts final-pay review, records documented credit/deduction components after reviewing payroll history, approved adjustments and recorded payment statuses, and confirms preparation. Amounts are summed from those reviewed entries only.
7. Accounting separately confirms actual payment with the release date and receipt/transaction reference. Preparation, an elapsed deadline or an uploaded document cannot release payment.
8. Authorized staff records quitclaim preparation and separately verifies the actual employee signature/acknowledgement and signature date. An optional scan does not sign it.
9. HR/Admin finalizes only after HR approval, Team Lead clearance, HR acceptance, payment release and quitclaim signature verification. Finalization records the confirmed last working date, sets the employee to Resigned/inactive, disables the matching Employee-role login and closes remaining deployment/DTR access. Historical records are retained.

Every transition locks the request and commits its state, audit history and notifications together. Repeated transitions, releases, signatures and unchanged targets are rejected. A notification failure rolls back the corresponding payment transition.

## Final-pay target and policy limits

No agency calendar was assumed. Clearance acceptance initially leaves the target and remaining-working-day count unset. HR/Accounting can record a controlled manual target with a reason, explicitly labeled as pending agency calendar confirmation.

HR can alternatively confirm the weekday list and the displayed exclusion rule. This option counts 30 working days **after** clearance acceptance, includes Saturdays/Sundays when selected, and excludes registered nationwide regular and special non-working holidays. The target and remaining working days use that confirmed rule. If the agency uses different local/site holidays or counting conventions, use the manual target until that policy is configured; employee duty schedules do not establish the agency's settlement calendar.

The target is tracking information. It never releases money or changes account status automatically. Unpaid-wage eligibility, deduction authorization, prorated benefits, leave conversion, 13th-month calculations and separation benefits still require the agency's confirmed rules. Stored payroll/payment history is evidence for review, not proof that every net amount is unpaid or should be added again.

## AWOL review

HR/Admin's **Review attendance for AWOL** action evaluates a selected employee's recent 60-day history and creates/updates an episode review case. No background scheduler was installed. Review uses actual dated/fixed schedule history and applicable deployment dates, including completed overnight duties. It does not assume weekdays when duty configuration is absent.

Only explicit Absent DTRs without clock entries, supported by reviewed Absent attendance entries with approver/date, qualify. Missing, unreviewed, contradictory or incomplete attendance cannot substitute for evidence. Scheduled rest days and non-working holidays do not increment the count; approved leave interrupts an unjustified absence streak. An explicit dated duty override can establish holiday duty. Employment/deployment boundaries and future/in-progress duties are excluded.

Three consecutive scheduled-duty absences create a review case, not automatic AWOL status. HR must review evidence and employee explanations, then confirm or dismiss with remarks. Confirmation changes status to AWOL while leaving employee/login activation unchanged. It does not create resignation or release final pay. Corrections are re-evaluated against the saved episode dates, retain history and restore the previous status where appropriate. An unchanged episode does not produce duplicate cases or notifications. HR/Admin and the responsible Team Lead receive alerts; no new disciplinary notice or due-process policy was invented.

## Dashboard and security integration

- HR/Admin: registration, review/return, issuance, returned-clearance acceptance, tracking target, finalization and AWOL decisions.
- Team Lead: scoped clearance review/return, relevant AWOL alerts and decision history.
- Accounting: only clearance-eligible/settlement/history records, payroll references, final-pay preparation/release and quitclaim tracking.
- Employee: own request progress and physical procedure instructions.
- Existing notification details now link to the appropriate employee-exit page. Notifications are persisted in the existing dashboard system; browser receipt was not presented as guaranteed delivery.

Server authorization checks actual current role/activation, ownership and assigned sites. POST mutations require the session's exit CSRF token. Database-backed APIs reject an inactive account on its next request and destroy its existing session; the existing password login also rejects inactive credentials. Standalone pages reuse the session guard; embedded views reuse their parent dashboard controller.

Optional PDF/JPG/PNG scans are capped at 10 MB, checked against actual MIME content and randomly named in a private directory. Apache denies direct access. The authenticated viewer checks document/request ownership, role, MIME and filesystem containment. Team Leads cannot view quitclaim documents or financial components/history. Original scans cannot be silently overwritten.

Legacy resignation records remain visible and read-only when their old state lacks the required verification evidence. They require controlled HR reconciliation rather than guessed clearance/payment/signature approvals. Existing physical records are preserved. Legacy scans outside the two protected document directories are not automatically served by the new viewer.

## Schema and changed files

Applied `sql/employee_exit_workflow_migration.sql`: adds nullable WorkflowDetails to resignationrequest and creates exit_workflow_history and awol_review_cases, including episode uniqueness and actor/employee references. It does not delete or rebuild employee/payroll tables. Required shared status names are resolved or added transactionally when used. CLI installer: `scripts/apply_employee_exit_migration.php`.

New backend files: `Api/Helpers/employee_exit_workflow.php`, `Api/Helpers/employee_exit_endpoint.php`, `Api/TeamLead/clearance.php`, `Api/Common/view_exit_document.php`.

Replaced existing exit endpoints: `Api/HR/resignations.php`, `Api/Accounting/resignation_final_pay.php`, `Api/Admin/resignation_complete.php`, `Api/Employee/resignation_request.php`. Modified `Api/Db_connection/db_connect.php` for inactive-session revocation.

New shared UI: `Js/Shared/employee_exit.js`, `Css/employee_exit.css`, `Html/TeamLead/clearance.html`. Updated existing standalone HR/Accounting/Employee resignation pages, the HR/Admin/Team Lead/Accounting/Employee dashboard links/views, and `Js/Shared/ow_notifications.js`. Added private directory rules under `uploads/employee_exit` and `uploads/quitclaims`.

## Actual validation

- **88 backend/HTTP checks passed**: physical workflow and returns; role/ownership and multi-site scope; no invented calendar; Saturday/holiday target; actual preparation/release/signature separation; foreign payroll references; duplicate guards; notification transaction rollback; finalization/status/login/deployment safeguards; AWOL evidence, leave/holiday/unknown-calendar exclusions, confirmation and corrected attendance; MIME/private-viewer/CSRF checks; existing-session revocation; and production business-table checksums unchanged.
- **8 browser scenarios passed**: HR, Team Lead, Accounting and Employee pages at 1366 px and 390 px, no horizontal page overflow, allowed actions, confirmation/success modals, CSRF submission and no browser script errors. Browser API responses were synthetic; backend tests exercised the actual PHP endpoints against a disposable schema.
- Apache direct private-document probe returned **403**; the harmless probe was removed.
- Existing isolated holiday/attendance regression: **13 scenarios passed**, production unchanged and disposable schema cleanup passed.
- PHP syntax checks passed for 12 implementation/test files; both changed shared JavaScript files passed Node syntax checks.

Test sources and results: `Testing/EmployeeExit/run.php`, `http_checks.php`, `ui_check.cjs`, `results.json`, `ui_results.json`, plus desktop/mobile screenshots in the same directory. Synthetic schemas and HTTP server copies were removed after tests. No real resignation, payment, signature or AWOL decision was created for testing.
