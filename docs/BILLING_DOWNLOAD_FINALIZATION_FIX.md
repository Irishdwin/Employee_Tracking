Billing downloads, finalization and employee payslips — verification report
2026-10-09

1. Submitted DTR download root cause
Apache logs recorded the non-negative amount validation failure. The reported cutoff already has two Finalized payroll rows with PayslipGenerated=1, but neither run membership snapshots nor archived exports. The exporter was recomputing current attendance and rates, then mixing those components with saved finalized payroll totals. This can produce inconsistent/negative components. It now recovers eligible legacy billing from persisted payroll totals and freezes the recovered snapshot. The submitted batch also had both a legacy unscoped run and a canonical site run; a single-site legacy request now selects the canonical run.

2. Client Billing table export root cause
The listing fell back to currently deployed employee counts when no cutoff DTRs existed. Those counts enabled export even though the export roster requires verified DTRs. The reported company rows other than Company 4 had no eligible verified cutoff roster. The list now separately counts verified DTRs or archived members and explains why an export is unavailable. No foreign company employees are substituted.

3. Finalize Payroll root cause
The workflow treated existence of a post-computation export as sufficient and did not report payroll that was already finalized. Its legacy run lookup could also be ambiguous. The affected employees are already finalized; their button correctly becomes disabled with a Payslips Published label and an explanation. Eligible unfinished batches still require verified DTRs, computation, the current subsequent export and explicit review confirmation.

4. Implementation files modified for this request
Api/Accounting/export_client_billing.php
Api/Accounting/compute_payroll_for_batch.php
Api/Accounting/Lib/client_billing_spreadsheet.php
Api/Helpers/client_billing_helper.php
Api/Helpers/billing_revision_helper.php
Api/Helpers/payroll_finalization.php
Api/Helpers/payroll_status_service.php
Html/Accounting/accounting_dashboard.html
Js/Accounting/accounting.js
Js/Shared/ow_notifications.js
Tests: Testing/PayrollBilling/http_test.php, finalization_sequence_test.php; added legacy_snapshot_test.php and download_ui.cjs.
Existing unrelated changes in the workspace were preserved. Templates/SAMPLE.xlsx, vendor dependencies and payroll calculation formulas were not edited.

5. API changes
Existing endpoints and existing database tables are reused. Workflow responses now include finalized, finalized_payrolls and can_compute. Listings include verified_employee_count and export_block_reason. Finalized payroll cannot be recomputed. Expected export conflicts return 409 and validation failures 400, rather than an unexplained 500. XLSX output is buffered, PHP display warnings are suppressed, filenames are sanitized, and mismatched supplied billing references are rejected.

6. JavaScript changes
Both export locations use delegated authenticated fetch and Blob download handling, including dynamically created modal links. Successful responses require XLSX MIME type and ZIP signature. Errors appear through the existing dialog system without navigating away. Completed batches disable compute/finalize. Failed finalization refreshes server eligibility. payroll_finalized notifications open existing employee Payroll History and the related payslip.

7. Workbook corrections
Existing workbook layout, template and normal payroll computation are retained. Legacy finalized exports preserve saved basic, gross, employee deductions and net; the allowance column stored outside gross is not added again. Unarchived additional earnings are shown as the recorded gross less basic rather than assigned an invented overtime/holiday classification. An Excel cell comment discloses aggregate saved overtime and allowance and the missing original breakdown. Employer and accrual billing use the existing billing settings, with Accounting review explicitly required. Once saved, member snapshots drive subsequent exports.

8. State transitions
Verify DTR → Compute → Export/review → Finalize/publish remains enforced. Review requires both reviewer and review date. Finalization checks the current export while holding the run lock, rejects conflicting existing employee/cutoff payroll and is idempotent on a completed batch. Payroll Released now requires an actual Finalized payroll for every group/run employee. Finalization itself does not mark payment released or client billing approved. Existing Released history remains intact.

9. Payslip publication
The existing finalization transaction saves reviewed payroll, sets PayslipGenerated, freezes employee billing membership, records audit entries and sends employee notifications. The existing employee history API serves only persisted Finalized payroll belonging to the session's employee. No new payslip module or recalculation was introduced. The existing Print button supports the browser print/save-to-PDF flow.

10. Security checks
Accounting/Admin session plus actual active user role checks remain in place. Payroll mutations retain CSRF protection. Exact company/site/cutoff/DTR roster checks, database transactions and employee locks remain enforced. Employee attempts to use Accounting endpoints were rejected. Foreign employee payslip IDs were rejected with 404. Billing files remain protected by existing private file access. No real employee payroll was finalized, recomputed or revised during validation.

11. Tests actually executed
- workflow_test.php: 100 passed.
- http_test.php: 123 passed including those 100 workflow checks and 23 copied-API HTTP checks. Includes real XLSX downloads via run_id and billing ref, workbook reopen and amount checks, historical payslip ownership, multipart import/confirmation, CSRF and archive behavior. All use a disposable synthetic database.
- finalization_sequence_test.php: final run 19 passed, including published state, legacy single-site resolution and release prerequisites.
- legacy_snapshot_test.php: 7 passed using synthetic copies of the reported amounts. Confirms gross/net sums, no allowance duplication, recorded overtime disclosure, frozen membership and unchanged payroll despite changed current salary rates.
- finalization_sequence_ui.cjs: passed at 1366px and 390px.
- download_ui.cjs: dynamic download/error checks passed at 1366px and 390px; existing employee payslip printing passed. Browser API responses are mocked in this UI test; actual workbook correctness is covered separately by the HTTP tests.
- holiday_regression.php: 13 scenarios passed, production_unchanged=true, disposable database cleanup passed.
- Modified PHP and JavaScript syntax checks passed.
Initial UI test failures were fixture placement/finalized-state issues and were corrected before the successful runs. A backend fixture initially incorrectly expected a multi-site unscoped request to resolve one site; the fixture was corrected to exercise single-site resolution.

12. Expected versus actual
Populated XLSX from both billing URLs: passed real HTTP download, reopened workbook and exact saved gross/net comparisons.
No export with missing verified DTRs: explicit error passed; list eligibility fixed.
Valid reviewed payroll publishes payslips: passed, with exact reviewed amounts and employee notifications.
Already finalized batch: reported as published; compute/finalize disabled; repeated submission adds no payroll/notifications.
Own historical payslip and print: passed; foreign payslip rejected.
Payroll and DTR history preservation: passed. Holiday scenarios: passed. No production business records were changed by tests.

13. Limitations
Original detailed billing components cannot be reconstructed when an old finalized payroll never archived them. Recovered aggregate detail is disclosed in the workbook; employer/accrual billing settings must be reviewed by Accounting. Legacy records with nonzero unclassified holiday pay, gross below basic, or irreconcilable component totals are explicitly blocked pending recovery/reconciliation; no fabricated classifications are used. Existing snapshot-backed records retain their archived detail. A download plus review confirmation records Accounting's attestation; it cannot prove that a person inspected the workbook. Save-to-PDF remains the existing browser print function; no independent PDF service was added. Validation did not use real employee logins or create production billing exports.
