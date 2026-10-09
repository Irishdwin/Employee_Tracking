# Reviewed payroll finalization

Accounting now follows Verify DTR → Compute Payroll → Download and review Billing Export → Finalize Payroll. The batch modal explains the steps, provides an export link and refresh action, and disables Finalize until a post-computation export exists. Final confirmation explicitly attests that the latest export has been reviewed.

The server independently requires an active Accounting/Admin account, payroll CSRF token, a successful compute event, a subsequent archived export for the same Team Lead/site/cutoff, the latest export ID, reviewed DTRs and matching employee/DTR/site identities. A recomputation invalidates earlier exports even within the same timestamp second. Finalization locks the run and employees, saves the exact reviewed export amounts and itemized snapshots, audits the export ID and notifies employees. It does not mark salary as released or invent payroll rates.

Employee APIs already publish only stored Finalized payroll, so compute and export remain private Accounting review steps. Existing finalized records remain available. Repeating finalization creates no duplicate payroll or notifications. No migration is needed; existing payroll, run membership, exports, status history, audit and notification tables are reused.

Changed: Api/Accounting/compute_payroll_for_batch.php, finalize_payroll_batch.php, get_submitted_dtrs.php and Html/Accounting/accounting_dashboard.html. New shared implementation: Api/Helpers/payroll_finalization.php.

Validation: 15 isolated backend sequence checks passed, desktop/mobile sequence UI checks passed, and all 100 existing billing workflow regression checks passed. No actual employee payroll was finalized during testing. The review confirmation records Accounting's attestation; the system cannot verify that a person actually inspected the downloaded workbook.

Run: C:/xampp/php/php.exe Testing/PayrollBilling/finalization_sequence_test.php
UI: node Testing/PayrollBilling/finalization_sequence_ui.cjs
