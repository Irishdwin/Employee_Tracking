# Payroll and attendance start on the recorded hire date

The authoritative field is employee.HiredDate, already populated by the hiring workflow. The shared schedule/status resolver and production payroll calculator did not previously check it, so a mid-cutoff hire could be marked absent on earlier scheduled days.

Dates before HiredDate now show Not Yet Hired rather than Absent or Rest Day. Those days contribute no payroll workdays, hours, absence count, lateness/undertime deductions, holiday earnings or approved overtime. Attendance on the hire date remains eligible and retains normal late/grace-period rules. The original cutoff dates and payment schedule remain unchanged. A cutoff ending entirely before hiring cannot be computed for that employee.

The Accounting modal shows the recorded hire date. Accounting, Team Lead and Employee attendance views reuse the same boundary. Pre-hire holiday eligibility reviews are excluded. Unresolved Accounting warnings are refreshed using the production calculator so an obsolete pre-hire holiday warning does not block the current review interface. Historical reviewed/finalized snapshots are not recalculated or rewritten by display refresh.

Source attendance rows remain stored; the change prevents pre-employment data from counting toward attendance/payroll. No production dates were modified and no saved payroll was overwritten. Existing deduction rates, holiday multipliers and post-hire allowance policy were not changed. Missing legacy hire dates retain existing behavior rather than using an invented date.

## Files

- Api/Helpers/employee_payroll_helper.php — recorded hire date resolution, shared status boundary, payable-day/overtime bounds and preceding-day holiday eligibility.
- Api/Helpers/dtr_holiday_review.php — exclude reviews outside employment.
- Api/Accounting/get_submitted_dtrs.php — expose hire date and refresh unresolved display classifications through existing payroll validation.
- Api/TeamLead/get_employee_attendance_logs.php — preserve hire date in its normalized schedule.
- Html/Accounting/accounting_dashboard.html — explain the hire-date payroll boundary in the employee modal.

## Validation

Testing/Attendance/hire_date_payroll_test.php passed against actual endpoints and production helpers in a disposable database. Cases include hiring inside a cutoff, hire-day lateness, pre-hire recorded ordinary/holiday duty and approved overtime excluded, pre-hire rows not absent, obsolete review warning cleared, preserved source data, wholly pre-hire cutoff blocked, pre-hire holiday reviews excluded, employees hired before the cutoff, and missing-date compatibility.

Testing/Attendance/individual_dtr_verification_test.php passed the existing individual verification, authorization, holiday review, duplicate, group status and finalized payroll safeguards. Production checksums matched before/after both test suites, and disposable databases were removed.

PHP syntax checks and all seven dashboard inline JavaScript syntax checks passed. No live employee payroll or DTR was changed during testing.
