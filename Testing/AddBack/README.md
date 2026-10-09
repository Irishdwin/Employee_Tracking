# Auditable Add Back

Implemented in the connected Employee_Tracking project. The additive migration in `sql/add_back_workflow.sql` has been applied to the connected database.

## Use

1. Employee → Timesheet → Daily Timesheet Logs → View Evidence. View/download the employee's own Clock In and Clock Out photos.
2. Employee → Timesheet → Add Back Requests → New Request. Select the work date, enter the complete actual duty interval and a reason. The server resolves employee/company/deployment, derives missing hours and preserves copies of available recorded clock photos. One photo is sufficient for incomplete attendance; additional uploads are intentionally unsupported.
3. Accounting → Add Back Requests → Review. Review the original record, schedule, frozen photos, requested duty, reason and production calculation. Approve into an eligible current/next open cutoff or reject with a reason.
4. Approval creates exactly one queued `payroll_adjustments` entry linked to the request. Payroll finalization attaches that adjustment to the new payroll atomically. Historical attendance, submitted DTR and finalized payroll are not rewritten.
5. Employee Payroll/Payslip displays a separate Add Back line. Client Billing writes the monetary amount into template column Q (`ADDBACK`), with gross/net including it once. The workbook does not store missing hours in Q.

## Historical rate prerequisite

The existing employee fields have no effective-date history, and production `salary_rates` was empty during the audit. Past duty cannot safely be priced with today's salary.

Historical approval requires a verified `salary_rates` record matching EmployeeID, DeploymentID, CompanyID and the work date between EffectiveDate and EndDate. The migration adds explicit DailyRate and HourlyRate fields to avoid guessing the meaning of the previously unused BasicRate/RateType. Populate these only from verified agency salary records through an authorized database maintenance process. This change does not introduce a browser-entered salary or Add Back amount. A request for today's duty preserves a server-resolved current rate snapshot when submitted.

If the original deployment does not cover an open payment cutoff, approval is blocked pending Accounting's resolution of the payment context. An existing finalized payroll or client-approved/released run cannot receive a new adjustment. Ordinary/holiday/rest-day/approved-overtime valuation uses the production cutoff engine's before/after difference; unsupported or zero-compensation corrections cannot be approved automatically.

## Integrity and access

- Employee evidence is resolved from session → linked employee → AttendanceID ownership. Accounting/Admin can review; Team Lead retains dated roster evidence access.
- Images resolve only beneath fixed allowed folders, with MIME/size/format validation. Arbitrary paths and traversal are rejected. `.htaccess` blocks direct static access; Apache was checked to return HTTP 403 for an existing photo.
- Submitted evidence is copied into private storage and SHA-256 checked when reviewed/downloaded. Employees cannot replace request evidence.
- Employee locks serialize request/review/posting and submitted-day clock protection. Pending/approved corrections block another correction for the same day; a unique ledger source key prevents duplicate payment.
- Shared notifications report submission and review outcomes once. `system_logs` records submission, review and payroll posting.

## Verification

Run `C:\xampp\php\php.exe Testing/AddBack/run.php`. This is CLI-only and clones the current schema into a randomly named disposable database. All people, photos, salary history and attendance are synthetic; live business data is not copied or modified. The random test database is dropped in `finally`. `--fast` skips only the slow workbook rendering step, retaining the export-row checks. `--browser` stages copied dashboards against the disposable database for at most five minutes, then removes its temporary fixture-only session bootstrap and stops the local server. Never install that bootstrap in production.

Results:

- `results.json`: full Add Back end-to-end, actual workbook Q6/gross checks, isolated browser fixture.
- `final_security_results.json`: repeated workflow plus actual production clock API photo capture/download, submitted-day lock, self-approval, cross-employee ownership, path containment, tampered amount, unsupported image/upload and duplicate request/approval/payment checks.
- `payroll_regression.json`: all 12 existing production holiday scenarios pass after integration.
- Browser checks: Employee evidence modal and request form, Accounting list and historical-rate approval guard; screenshots in this directory show synthetic test identities only.

At ₱1,000/day, missing two hours yielded ₱250 normal duty, ₱500 regular holiday and ₱650 regular holiday plus rest day. An incomplete record with only Clock In evidence was accepted and reconstructed using the same engine. Rejection produced no adjustment; absent historical rate prevented approval. Approved adjustments appeared once in payroll, historical payslip and selected-context export. Original attendance/DTR and production checksums remained unchanged throughout testing.

The pre-existing Clock Out SQL parameter binding treated the image filename as a number. It is corrected to a string and verified through the actual isolated clock endpoint. Previously corrupted/missing photos cannot be reconstructed by this feature.

Image upload verification (2026-10-09): run.php now includes upload_cases.php and follows the existing reviewed payroll finalization sequence. Run upload_ui.cjs and accounting_upload_ui.cjs for mocked desktop/mobile validation. See docs/ADD_BACK_IMAGE_EVIDENCE.md.
