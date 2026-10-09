# Add Back historical salary rate correction

## Confirmed cause

Read-only inspection of the live database found request #1 for employee #77, deployment #47, company #4, work date 2026-10-08. Its RateSnapshot is empty and there are no salary_rates records for this employee. The table has no verification flag. No existing endpoint writes effective-dated daily/hourly salary history.

Approval must remain blocked until HR/Admin records the actual documented historical rate. No salary amount was inferred or inserted into production.

## Implementation

- Historical lookup resolves active salary history covering the work date and matching the employee and applicable deployment/company. Employee-wide records are supported without requiring duplicates. The newest applicable record is checked before accepting its positive daily/hourly amounts and the existing daily / 8 hourly rule.
- Authorization provenance uses CreatedBy linked to an HR/Admin role because the actual schema has no verification column. Records lacking that provenance remain blocked; changing the creator's role can affect eligibility. Existing server-captured request snapshots remain preserved.
- The blocked Accounting review identifies the missing employee/deployment/date context, links to a new authorized salary-history form, and offers Refresh eligibility.
- The form requires documented effective dates, daily rate and salary basis. Only an active HR/Admin session can write history. Hourly rate uses the existing salary helper. The transaction checks deployment coverage, rejects overlapping active deployment-specific history, records the actor, and audits the documented basis. It does not overwrite history or change current salary. Existing overlapping incorrect records require authorized maintenance rather than this create-only action.
- Eligible reviews show daily/hourly rates, effective date, source, missing payable hours and adjustment amount. Approval requires a confirmation modal and shows a success modal after the server completes approval. Backend validation remains authoritative.
- Existing evidence storage/view/download, immutable attendance/DTR records, duplicate prevention, employee notifications, separate payroll adjustment and open-cutoff carry-forward remain intact. No database migration is needed.

## Verification

`Testing/AddBack/run.php` passed using a disposable database: regular duty, regular holiday, holiday/rest day, incomplete attendance, rejection, missing history corrected by Admin, unverified creator blocked, changed historical rate, evidence uploads/downloads, authorization/CSRF, duplicate approval/payment, real payroll computation, payslip/finalization and billing Add Back column Q. Saved payroll is checked on Payroll Reconciliation because the main billing sheet uses the user-selected agency formulas.

Production checksums before/after matched for employee, attendance, DTR, payroll, adjustments, requests, evidence, notifications, salary history and holidays. The disposable database was removed.

`Testing/AddBack/settlement_history_ui.cjs` and `historical_rate_ui.cjs` passed at 1366px and 390px. The latter covers blocked approval, correction link, refreshed eligibility, rate source, cancel without approval, confirmation, successful approval and history/badge refresh. Browser API responses are synthetic; payroll and authorization tests use real endpoints in the disposable database.

PHP syntax checks and JavaScript syntax checks passed.

## Live next action

An authorized HR/Admin must open `Html/HR/add_back_salary_history.html?request_id=1`, enter the actual documented rate and dates covering 2026-10-08, and record its documentary basis. Accounting then clicks Refresh eligibility and reviews the evidence before approving. No live request was approved during testing.
