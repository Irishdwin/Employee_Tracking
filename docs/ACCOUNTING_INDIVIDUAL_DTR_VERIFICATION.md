# Accounting individual DTR verification

## Delivered behavior

The individual Submitted DTR information card no longer displays Total Late Minutes. Its remaining six fields align in two rows on desktop. Daily Late (min), attendance calculations, payroll deductions and billing remain intact. The modal header stays visible while scrolling; existing dashboard navigation and Back to Employee List are preserved.

An unverified employee has a Verify DTR action inside the modal. Confirmation shows the employee, deployment site, cutoff and current verification status. Cancel sends no request. Confirmation verifies only the exact employee/submission/site/cutoff. A success modal follows the server response; details, the parent group list and notifications refresh. Returning to the employee list loads fresh verification and payroll eligibility state without a browser refresh.

Verified employees show the reviewer and timestamp and no duplicate verification button. Locked payroll shows the reason instead of permitting a change. Compute Payroll and Finalize Payroll remain separate actions; other unverified employees still block computation.

## Existing verification logic and safeguards

The existing `verify_dtr_submission.php` endpoint already supported employee_id, so the new button reuses it. Both individual and batch verification now share exact target resolution, Accounting/Admin database authorization, the existing payroll CSRF token, transaction locks, deployment identity checks, existing payroll rate resolution and `payroll_compute_period()`.

Unverified records are validated before recording ReviewedBy/ReviewDate. Previously verified records are not overwritten. Duplicate individual verification returns 409. Verification audits use the existing AuditLogger. Saved payroll and approved/released/finalized run states prevent changes. Batch verification rolls back if an employee fails a mandatory validation.

## Holiday eligibility review

Inspection found no existing holiday review API or interface. A focused review panel was added inside the existing DTR modal; no standalone page or database migration was introduced.

The panel shows unworked regular holiday dates, the preceding scheduled workday, recorded attendance, approved leave and approved Add Back corrections. Accounting records eligible, ineligible or unresolved with a documentary basis and explanation. Approved leave alone is not automatically interpreted as paid. Eligible decisions require supporting completed attendance, approved leave confirmed paid by Accounting, or an approved attendance correction. Ineligible decisions require documented unpaid absence and cannot contradict completed attendance or an approved correction.

Decisions are appended to existing system_logs with reviewer, timestamp, submission reference, holiday/preceding dates, basis, explanation and source fingerprint. The production holiday eligibility function reads those authorized decisions; holiday multipliers and payroll formulas are unchanged. Changed attendance/leave/schedule/holiday evidence invalidates the decision. Unresolved or insufficient information still prevents verification. Other holiday issues, such as incomplete or overnight holiday duty, remain subject to the existing production checks.

System logs now contain durable holiday eligibility evidence. Retaining those audit records is necessary for unresolved historical eligibility to remain reproducible; deleting a decision may restore the original validation block. Documentary confirmation of paid leave or unpaid absence remains an Accounting responsibility.

## Production files changed

- Html/Accounting/accounting_dashboard.html — summary removal, exact employee/site/submission navigation, sticky header, batch verification CSRF header, individual review integration.
- Js/Accounting/dtr_employee_review.js — modal review UI, confirmations, duplicate-click prevention, success/failure feedback and refresh.
- Api/Accounting/get_submitted_dtrs.php — reviewer/time/site/submission metadata, holiday review context, lock state, CSRF token, consistent verified aggregation.
- Api/Accounting/verify_dtr_submission.php — existing verification endpoint, selected/batch targets, production checks, locks and audit.
- Api/Accounting/review_dtr_holiday.php — authorized documentary holiday review action.
- Api/Helpers/dtr_verification.php — shared identity and lock checks.
- Api/Helpers/dtr_holiday_review.php — preceding-workday evidence, source fingerprints and audit decision retrieval.
- Api/Helpers/employee_payroll_helper.php — consume authorized holiday eligibility outcomes through the existing holiday rule.

## Actual validation

`Testing/Attendance/individual_dtr_verification_test.php` passed all checks using actual endpoints and production payroll helpers in a disposable database. It covers individual identity, wrong site/cutoff, authorization, CSRF, unsupported paid-leave claims, stale reviews, unresolved/eligible/ineligible reviews, preserved reviewer identity, duplicate decisions/verification, one employee only, partially/fully verified groups, computation prerequisites, existing regular holiday pay amounts, finalized payroll protection and unchanged attendance.

`Testing/Attendance/review_pending_dtr_test.php` passed its existing pending-review submission, verification and payroll regression checks.

`Testing/Attendance/individual_dtr_ui.cjs` passed at 1366px and 390px against the actual dashboard with synthetic API responses: removed summary field, retained daily late minutes, confirmation/cancel, blocked holiday verification, recorded review and retry, success feedback, parent refresh, verified record display and remaining payroll prerequisites. Browser mocks do not substitute for the real endpoint tests above.

PHP syntax checks, the new JavaScript syntax check and all seven dashboard inline script syntax checks passed.

The broader `Testing/AddBack/run.php` suite also passed: evidence uploads/downloads, historical salary rates, adjustment approvals, payroll computation, payslips/finalization, billing column Q and duplicate/security checks. Production checksums matched and its disposable database was removed.

Production checksums matched before/after the database tests. Disposable databases were removed. No live DTR was verified, no live holiday decision was recorded and no live payroll was computed or finalized during testing.
