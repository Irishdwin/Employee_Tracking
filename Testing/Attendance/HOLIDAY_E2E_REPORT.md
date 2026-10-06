# Controlled holiday pay end-to-end report — October 6, 2026

All eleven requested scenarios PASS, plus one additional preceding-holiday eligibility regression. Evidence: `holiday_e2e_results.json`; the pre-fix comparison is `holiday_e2e_baseline.json`. The Testing page displays the expected/actual matrix and expandable component checks. These are production-engine results in an isolated database, not an expected-value calculator.

Browser verification also clicked the real test button through a temporary local Admin-session harness: all twelve results displayed PASS, with production-unchanged and disposable-database cleanup PASS. Screenshot: `holiday_e2e_verified.jpg`. The harness server was stopped after verification. A separate assertion checked all twelve returned payslips are finalized and their gross amounts match expectations.

1. **Existing testing implementation.** `attendance_testing.html/.js` manually creates real attendance/DTR through Testing APIs. `get_payroll_verification.php` already delegates to production `payroll_compute_period()`. The former JavaScript expected-value calculator was separate from that engine; it has been removed. Manual expected amounts remain reference inputs.

2. **Why expected holidays previously did not reflect.** Choosing a scenario did not insert an official holiday into the engine's calendar. Test attendance also lacked HolidayID/IsHolidayWork/HolidayPayRate metadata. The controlled runner now supplies calendar records only in its disposable database. Manual attendance metadata now comes from the production engine. A separate preceding-day defect was reproduced and fixed: the search stopped at another non-working holiday rather than continuing to the preceding scheduled working day.

3. **Detection source.** Production `Api/Helpers/holidays_helper.php`, `holidays_fetch_for_range()`, reads the official `holidays` table as date-to-record-list. Fixtures use `holidays_create()` with real supported holiday types. No test type is added to production payroll inputs.

4. **Calculation authority.** `Api/Helpers/employee_payroll_helper.php::payroll_compute_period()` resolves attendance, schedules, approved OT, holiday classification and earnings. `dtr_classification.php` projects those results into hours. The runner copies current production source into a temporary fixture and redirects only the fixture database/session configuration. Source hashes are included in the results.

5. **HolidayPay meaning.** On worked days, HolidayPay is the additional premium over basic compensation, not the whole holiday day's pay. On an eligible unworked regular holiday, it carries the guaranteed entitlement because there is no worked basic pay. Holiday OT is separate in engine output and included in stored payroll OvertimePay. Example: regular holiday plus two OT hours = basic ₱1,000 + holiday premium ₱1,000 + holiday OT ₱650 = ₱2,650.

6. **Team Lead Holiday Pay Hrs.** This is worked holiday duty hours, not pesos or an extra block to add again to gross. First eight holiday duty hours are informational alongside regular baseline hours; approved holiday OT is separately classified. Unworked holiday entitlement does not fabricate worked hours.

7. **Regular holiday formula.** Eight hours: ₱1,000 basic + ₱1,000 premium = ₱2,000, equivalent to 200% daily compensation.

8. **Special non-working formula.** Eight hours: ₱1,000 basic + ₱300 premium = ₱1,300, equivalent to 130%. An unworked special non-working holiday pays zero in the tested policy.

9. **Holiday OT.** Approved regular-holiday OT: ₱125 × 2.00 × 1.30 × 2 = ₱650. Approved special-holiday OT: ₱125 × 1.30 × 1.30 × 2 = ₱422.50. These were retrieved from the production engine and persisted through finalization, not calculated by the test UI.

10. **Rest-day handling.** The actual dated deployment schedule determines rest days. A Thursday was omitted from fixture working days to prove that this is not limited to Sundays. Regular holiday/rest duty = 260%, ₱2,600; special non-working/rest duty = 150%, ₱1,500.

11. **Double holiday support.** Supported: two distinct regular-holiday records on one date produce 300% worked compensation, ₱3,000. The old Testing page's unsupported note was corrected. Double-holiday rest-day/OT combinations are outside the requested matrix and NOT VERIFIED here.

12. **Preceding working day.** `payroll_holiday_no_work_eligible()` uses actual scheduled days and prior DTR presence or approved leave. It now skips regular and special non-working holidays as well as rest days; special working holidays remain working days. Boundary L has attendance September 28, a holiday September 29 and scheduled rest September 30 before October 1. Before: ₱0 FAIL; after: ₱1,000 PASS.

13. **Expected versus actual gross matrix.** Each pair below is expected / actual; all differences are ₱0.00.

    - A normal 8 hours: ₱1,000 / ₱1,000 — PASS.
    - B regular holiday 8 hours: ₱2,000 / ₱2,000 — PASS.
    - C regular holiday with 2 approved OT hours: ₱2,650 / ₱2,650 — PASS.
    - D special non-working 8 hours: ₱1,300 / ₱1,300 — PASS.
    - E special non-working with 2 approved OT hours: ₱1,722.50 / ₱1,722.50 — PASS.
    - F scheduled rest plus regular holiday: ₱2,600 / ₱2,600 — PASS.
    - G scheduled rest plus special non-working holiday: ₱1,500 / ₱1,500 — PASS.
    - H double regular holiday: ₱3,000 / ₱3,000 — PASS.
    - I eligible unworked regular holiday: ₱1,000 / ₱1,000 — PASS.
    - J ineligible unworked regular holiday: ₱0 / ₱0 — PASS.
    - K unworked special non-working holiday: ₱0 / ₱0 — PASS.
    - L preceding-holiday/rest eligibility boundary: ₱1,000 / ₱1,000 — PASS.

14. **Team Lead verification.** Real HTTP requests use isolated role sessions to `get_dtr_cutoff_review.php` and `submit_dtr.php`. Worked holiday hours are eight; C/E holiday OT hours are two. Submission persists the production DTR classification snapshot and locks records through the normal flow.

15. **Accounting verification.** Real HTTP `get_submitted_dtrs.php` detail output matches submitted classification, and `verify_dtr_submission.php` reports the expected verified submission. No substitute test workflow was used.

16. **Payroll verification.** Real HTTP `compute_payroll_for_batch.php` creates the run. `client_billing_build_export()` uses the production engine and its economic rows match. Real HTTP `finalize_payroll_batch.php` persists BasicSalary, HolidayPay, OvertimePay and GrossPay. All stored gross amounts match the matrix. `get_employee_payroll.php` also returns matching current-cutoff gross and finalized payslip objects for every case. This verifies API data; screenshots of real users' employee payslip screens are NOT VERIFIED. No billing workbook layout was changed.

17. **Double counting.** Component checks compare basic, holiday premium, holiday OT, ordinary OT and gross across engine, billing row and stored payroll. Ordinary OT remains zero for C/E while holiday OT is ₱650/₱422.50. Holiday premiums are added once; holiday duty hours are not treated as an additional salary line. Gross is the acceptance target; statutory net deductions and SIL policy were not changed.

18. **Exact implementation files changed in this task.** `Api/Helpers/employee_payroll_helper.php` (preceding-day search only); `Testing/Attendance/Api/save_attendance.php` (production-derived metadata); `Testing/Attendance/Api/get_holiday_for_date.php` (support note); `Testing/Attendance/attendance_testing.html`; `Testing/Attendance/attendance_testing.js`; `Testing/Attendance/README.md`. New executable files: `Testing/Attendance/holiday_e2e_runner.php`, `Testing/Attendance/Api/run_holiday_scenarios.php`. New evidence/report files: this report, `holiday_e2e_baseline.json`, `holiday_e2e_results.json`. Other working-tree changes predate this task and were preserved.

19. **Database test records.** Each run creates a random `codex_holiday_<12 hex>` database with cloned schema only, nonpersonal role/status reference rows, synthetic Admin/Accounting users and one synthetic Team Lead, employee, employee user, company, site and deployment for each A–L case. Holiday rows are replaced between cases within that isolated database; A has none, H has two regular records. Worked cases use test attendance/DTR, C/E use approved OT, I/L use prior attendance. All twelve cases submit DTRs and create/finalize payroll runs. The JSON contains concrete isolated employee/site/submission/run IDs. No real salaries, holidays, users or attendance were edited by the suite.

20. **Cleanup.** The runner terminates its fixture server and drops only the random database it created in that invocation, guarded by the exact prefix/hex pattern and a check against the connected source database. Results report cleanup PASS. Temporary fixture code and server logs remain in the system temporary directory for diagnosis; no isolated database records remain. Before/after checksums of production holidays, employee, attendance, daily_time_records, overtime, dtr_submissions, payroll and payroll_runs are identical and non-null. Computer time/timezone were not changed.

21. **Overall statuses.** A–K PASS; additional L PASS after a reproduced baseline FAIL. Syntax checks and the existing payroll verification response tests PASS. Admin-only access controls reject unauthenticated requests (403) and authenticated GET requests (405). The browser test uses a separate temporary Admin session; it does not establish real account credentials. The fixture uses the current October 1–15 cutoff. Future runs against a different current cutoff explicitly label the employee payslip endpoint NOT VERIFIED, while fixed-date engine/finalized payroll checks remain available.

22. **Remaining limitations.** Approved-leave eligibility currently does not distinguish paid versus unpaid leave; the prior-working-day search has a 14-day cap. Geographic holiday applicability and extra double-holiday combinations were not certified in this test. Existing statutory deductions, SIL accrual display/storage, and the payroll status label `Computed` were preserved. Manual Attendance Testing still writes real records: use the new isolated suite for these holiday scenarios. It requires local PHP process execution and database creation privileges. Formula expectations are the user's supplied acceptance rules; this is implementation verification, not a legal-policy audit.
