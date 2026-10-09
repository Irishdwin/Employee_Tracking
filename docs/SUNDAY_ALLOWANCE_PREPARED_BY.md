# Sunday duty allowance and billing prepared-by implementation

Implemented in the connected Employee_Tracking project. No production employee was hired or payroll finalized during testing.

## Changed production files

- Api/Helpers/employee_salary_helper.php — additive salary schema and strict decimal allowance validation.
- Api/Helpers/hire_applicant.php — stores the Sunday rate during the existing hiring transaction.
- Api/HR/convert_to_employee.php — forwards validated compensation input and checks current database Admin/HR authorization.
- Html/HR/employee_conversion.html — Sunday duty allowance input in the shared Admin/HR conversion form; bounded modal with visible footer, existing search/filter/pagination and contract refresh behavior.
- Html/HR/hr_dashboard.html — Sunday allowance input in the existing legacy hire form.
- Js/HR/hr_dashboard.js — submits the legacy hire form's Sunday rate.
- Api/Helpers/employee_payroll_helper.php — retrieves the employee's stored Sunday rate, qualifies completed duty through existing DTR/schedule logic, and adds the component once to gross pay.
- Api/Helpers/attendance_pdo.php — optionally selects the server connection's database; default production database is unchanged. Enables billing to share the selected authoritative database and isolated integration tests.
- Api/Helpers/client_billing_helper.php — maps authoritative payroll results into the existing Sunday billing field.
- Api/Helpers/payroll_finalization.php — stores Sunday duty allowance in payroll.Allowances, separately from payroll.OvertimePay, with the existing snapshot and employee notification workflow.
- Api/Helpers/billing_revision_helper.php — preserves the frozen Sunday allowance through subsequent billing imports/exports and prevents the combined Sunday amount from falling below its configured allowance component.
- Api/Accounting/export_client_billing.php — retrieves the current authenticated user's full name for each new export; stores it in existing export SnapshotJson.
- Api/Accounting/Lib/client_billing_spreadsheet.php — discovers the signature cell from the template and writes the name explicitly as text, retaining formatting.
- Html/Accounting/accounting_dashboard.html — full-name editor in the existing My Account section.
- Api/Employee/get_employee_payroll.php — separates Sunday allowance from other allowances and overtime in finalized current payslips.
- Api/Employee/get_employee_payroll_history.php — reads the frozen Sunday allowance from the finalized run member snapshot.
- Html/Employee/employee_dashboard.html — Sunday Duty Allowance line in the existing payslip.
- Js/Employee/employee_payroll.js — renders the separate allowance component.

## Created files

- Api/Helpers/accounting_profile_helper.php — existing users.FullName read/save operations, prepared statements, validation and own-profile enforcement.
- Api/Accounting/profile.php — authenticated GET/POST for the existing Accounting profile, with existing payroll RBAC and CSRF protection.
- Js/Accounting/accounting_profile.js — profile form loading/saving and success feedback.
- sql/employee_sunday_allowance_migration.sql — additive employee column migration, applied locally.
- Testing/PayrollBilling/sunday_allowance_test.php — disposable database integration checks.
- Testing/PayrollBilling/employee_history_fixture.php — CLI-only isolated actual employee history endpoint runner.
- Testing/PayrollBilling/sunday_profile_ui.cjs — desktop/mobile browser checks.
- This implementation report.

Test evidence images: Testing/PayrollBilling/accounting-name-1366.png, accounting-name-390.png, sunday-conversion-1366.png, sunday-conversion-390.png.
Testing/Attendance/holiday_e2e_runner.php was updated to isolate the revised PDO helper and supply the existing CSRF and compute–review–finalize prerequisites in regression fixtures.

## Database and existing fields

One new field: employee.sunday_duty_allowance DECIMAL(10,2) NOT NULL DEFAULT 0.00. Existing employee rows, monthly allowance values, and payroll history are preserved. Existing allowance is monthly compensation (half per cutoff), so reinterpreting that field as a Sunday rate would alter existing pay; the new field represents a distinct compensation rule.

Reused fields: employee.allowance, daily_rate, hourly_rate, monthly_salary_credit; users.FullName; daily_time_records; deployment schedule fields; payroll.Allowances, GrossPay, NetPay and OvertimePay; payroll_run_members.SnapshotJson; payroll_billing_exports.SnapshotJson and CreatedBy. No new payroll, billing, attendance, user-profile, payout or notification table.

## Allowance flow

Admin opens Employee Conversion, enters Sunday Duty Allowance before hiring, and completes the existing required salary/government-ID fields. The legacy HR hire modal supports the same field. Server resolves the employee through the authorized application/hiring workflow, not a submitted EmployeeID. Values must be decimal money with up to two fractional digits, non-negative and at most 99,999,999.99. Storage uses a prepared statement inside the existing hire transaction. Repeated allowance multiplication uses integer cents.

payroll_compute_period retrieves the stored rate using EmployeeID from the server's payroll context. Within the selected cutoff it qualifies each unique Sunday date only when the DTR has time-in and completed time-out, positive rendered hours according to payroll_timesheet_day_status, and time-out is not in the future. Absent/leave/rejected records are excluded. The date must belong to the applicable deployment, and when a specific deployment is selected it must match. A rendered Sunday rest day is valued through the same DTR hour helper for allowance eligibility; its existing schedule and premium classification are not changed. No minimum full-day requirement was introduced: completed positive rendered duty qualifies according to the current hours engine.

Each qualifying date contributes one configured rate. Recomputing produces the same derived component; no incremental allowance rows are added. The earnings component sunday_allowance is added once to existing gross/net payroll formulas. Monthly allowance remains unchanged. Existing holiday/rest-day and approved overtime calculations continue to own premium pay.

client_billing_build_export consumes the engine's component. Column H, identified from the template header H4 = SUNDAY, contains existing Sunday premium pay plus Sunday duty allowance. Column O remains other/monthly allowance, preventing double counting. Snapshot sunday_allowance, sunday_duty_dates and sunday_allowance_rate preserve the breakdown. Sunday allowance is included in payroll and invoice totals once; it does not inflate the existing 13th-month accrual base. The normal compute, billing review, finalize and release/status workflow remains in use, including existing employee notifications. Finalization persists monthly plus Sunday allowance in payroll.Allowances, with Sunday detail frozen in the run member snapshot; premium amounts remain in OvertimePay. No separate allowance payment is created.

## Accounting profile and export flow

Accounting opens My Account, edits Full name for billing Prepared by, and saves. The server identifies the active user from the session and current database role. It writes users.FullName through a prepared statement. Names are required, limited to 100 characters, and reject control characters. A forged other-user ID is rejected. Writes require the existing payroll CSRF token. UI uses textContent rather than HTML for names.

Every new authorized billing export reads users.FullName at generation time using the session actor. Browser preparedBy/name/user-ID parameters are not accepted. Existing Admin access to Accounting exports is retained. The renderer finds PAYROLL IN-CHARGE in the original template and writes the name in the preceding signature row with explicit spreadsheet string type, uppercased according to the existing signature presentation. In the source template this is B51 (label B49, title B52). After the actual employee-row resize, the one-employee test export has the signature at B41. Row placement, merge/style and title remain template-owned.

The current prepared_by value is stored in the existing export SnapshotJson. New profile names affect only new exports; archived exports are downloaded as stored files and remain unchanged.

## Tests actually performed

- New Sunday/profile integration suite: 44 checks passed against a disposable clone of table structures. Covers real hiring storage, validation, no duty, one/two qualifying Sundays, zero hours, incomplete duty, cutoff exclusions, rest-day qualification, exact gross increase, repeated computation, selected run roster, protecting the configured component on import, Sunday H and other-allowance O totals, real profile saves, CSRF/RBAC and cross-user rejection, signature discovery, updated names, historical workbook persistence, formula injection, real finalization, finalized snapshot/idempotency, and the actual employee payroll-history endpoint.
- Existing payroll/billing workflow suite: 100 checks passed, including import, arithmetic, ownership, audit, notification rollback and replay protection.
- Existing reviewed-finalization sequence suite: all checks passed for compute/export/review gates, atomic finalization, payslip amounts, frozen detail, notifications and idempotency.
- Existing unified overtime service/payroll checks: passed, including credited-hour boundaries, duplicate/foreign-employee punches, timeout, payroll/DTR agreement and reminder idempotency.
- Holiday/rest-day regression: all 12 scenarios passed; production checksums unchanged and disposable test database cleaned up. The fixture initially required updating for the selected-database PDO helper and existing CSRF/review gates; the final run passed.
- Browser UI checks at 1366px and 390px: conversion field, own-profile saving with CSRF and only full_name payload, and separate payslip allowance lines passed. Conversion screenshot visually checked with an opaque white modal, bounded body scrolling and visible action footer.
- PHP lint, changed JavaScript syntax checks and git diff --check passed. Git reported only an unrelated pre-existing line-ending warning.

## Limits

Existing employees default to zero Sunday allowance; conversion does not retroactively assign rates to them. Already-finalized payroll and historical files are not rewritten. Tests used synthetic records and mocked browser API data; no real employee login/name was changed, no real applicant was hired, and no live payroll was finalized or released. Actual finalized records, employee history response and saved XLSX files were verified in isolated fixtures. Native Excel visual rendering and a live production bank/funding release were not exercised; existing release/status architecture was retained.
