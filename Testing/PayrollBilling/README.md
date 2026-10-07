# Payroll billing workflow

Apply `sql/payroll_billing_revision_migration.sql` before deploying these endpoints. It is additive and has been applied to this local installation.

Accounting exports a finalized, scoped payroll run and uses **Import Updated Billing** to preview edited employee-row values (columns B–AW). This includes billing labels, rates, days, hours, earnings, deductions, employer charges and totals. The row sequence, signed employee IDs, client, site ownership, payroll period, headers and export metadata continue to match the original run. Label edits affect the billing record rather than an employee's account or deployment assignment.

The server recalculates affected payroll and billing totals and validates net pay against gross less deductions. Existing formulas may remain; edited formulas must be replaced with a numeric value. Imported formulas are never executed. Preview shows both edited fields and recalculated gross, deductions, net and invoice totals. Confirmation requires a reason, updates only affected employees, keeps an audit trail, and sends each affected employee one in-system notification with a View Payroll action. Payroll, snapshots, audit entries and notifications commit together or roll back together. Re-importing the same confirmed file does not create additional adjustments or notifications. Released payroll remains locked against billing revisions.

Revised breakdown values are retained in future exports and employee current/historical payslips. Source attendance and DTR records are not rewritten by a billing revision.

Use a newly generated workbook for this workflow: older workbooks lack the server-issued export identity and roster. Review exports without finalized payroll cannot be confirmed for import. Historical archive downloads return the stored file without recalculating attendance or payroll. Missing historical itemization appears as unavailable rather than an invented amount.

Employee leave pay is announced as payable together with 13th-month pay. No new entitlement, accrual, or proration formula is introduced. Holidays come from Admin registration; local holidays can be assigned to specific deployment sites.

## Verification

- `php Testing/PayrollBilling/workflow_test.php`: isolated synthetic database; workbook protection, revisions, rollback, notifications, authorization, and holiday scope.
- `php Testing/PayrollBilling/http_test.php`: same synthetic workflow plus real isolated HTTP endpoints and multipart XLSX upload.
- `php Testing/PayrollBilling/holiday_regression.php`: existing controlled holiday scenarios, production checksum verification, disposable database cleanup.
- `node Testing/PayrollBilling/ui_test.cjs`: local browser with synthetic API responses; desktop/mobile details and import controls.

The database tests create uniquely named disposable databases and remove them in `finally`. Production employee, attendance, DTR, and payroll amounts are not copied or rewritten.
