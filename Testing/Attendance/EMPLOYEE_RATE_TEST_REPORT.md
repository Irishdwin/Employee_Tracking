# Selected employee rate / controlled holiday implementation

Verified 2026-10-06 against the connected XAMPP project. No ZIP project extraction.

1. **Daily Rate source:** current `employee.daily_rate`. Existing fallback is
   `round(employee.BasicSalary / 26, 2)` when daily rate is nonpositive and BasicSalary
   is positive. There is no arbitrary ₱1,000 fallback for selected employees.
2. **Hourly Rate:** current `employee.hourly_rate`; otherwise `round(daily / 8, 2)`.
   BasicSalary fallback also derives hourly, preserving existing production behavior.
   Four-decimal display does not change stored/computed precision. ₱645 daily derives
   ₱80.63 hourly, not ₱80.625; that is the existing production rounding.
3. **Helpers:** extracted existing precedence into `payroll_employee_rates()` in
   `Api/Helpers/employee_payroll_rates.php`; reused by Accounting finalization,
   Employee payroll API and Testing. Production `payroll_compute_period()`,
   schedule/cutoff helpers and `dtr_classification_from_payroll()` compute actuals.
   No holiday multiplier or deduction formula was changed in this task.
4. **Employee:** Jhonel Tuling, EMP2026000009, source EmployeeID 77, Cashier.
5. **Company:** SM Uptown, CompanyID 4.
6. **Site:** SM Uptown, SiteID 4; assigned lead Sherrish Mae, user 56.
7. **Deployment:** source DeploymentID 47. Disposable fixture IDs differ; result
   `employee_context` records the original source IDs, rates and schedule.
8. **Payroll Type:** B. Test date 2026-10-06; cutoff 2026-09-26–2026-10-10,
   release 2026-10-15. Cutoff comes from production payroll-type helper.
9. **Daily Rate:** ₱700.00; matches current production employee row.
10. **Hourly Rate:** ₱87.5000 displayed, ₱87.50 production value.
11. **Effective dating:** NOT applied by these production payroll paths. The existing
    `salary_rates` schema has EffectiveDate/EndDate and company/deployment fields,
    but Accounting finalization and Employee payroll currently read the employee
    row. The UI explicitly states this limitation rather than inventing history.
12. **Normal day:** expected ₱700.00, actual ₱700.00, difference ₱0.00, PASS.
    Basic ₱700; holiday premium/holiday OT both zero.
13. **Regular holiday 8h:** expected ₱1,400.00, actual ₱1,400.00, PASS.
    Basic ₱700 + holiday premium ₱700.
14. **Special non-working holiday 8h:** expected ₱910.00, actual ₱910.00, PASS.
    Basic ₱700 + holiday premium ₱210.
15. **Holiday OT:** regular 8h + 2h OT expected/actual ₱1,855.00: basic ₱700,
    holiday premium ₱700, holiday OT ₱455. Special + 2h OT expected/actual
    ₱1,205.75: basic ₱700, premium ₱210, holiday OT ₱295.75. Both PASS.
    Expected values are independent normalized acceptance references, never sent
    to production calculation. Actuals come from the production engine and APIs.
16. **Team Lead Holiday Pay Hrs:** production cutoff-review endpoint returns 8h for
    regular holiday, special holiday and the tested holiday-OT cases.
17. **Team Lead OT:** production cutoff-review endpoint returns 2h total OT and
    classification `holiday_overtime_hours=2`; ordinary OT remains zero. Separate
    assertion verifies the Team Lead total OT, avoiding holiday/ordinary duplication.
18. **Accounting:** submission classification equals production classification;
    verification succeeds; Accounting payroll export's daily rate equals ₱700 and
    basic/holiday/holiday-OT components match. No billing code changed. Browser POST
    with daily/hourly both ₱5,000 still resolves ₱700/₱87.50 and produces ₱1,855.
19. **Payroll:** finalized persisted BasicSalary ₱700, HolidayPay ₱700,
    OvertimePay ₱455, GrossPay ₱1,855. Current Type B Employee payslip endpoint also
    verified these amounts. Actual production deductions run unchanged in the
    fixture; their values are excluded from the economic-compensation comparison.
20. **Exact implementation files added/modified this task:**
    - `Api/Helpers/employee_payroll_rates.php` — shared existing rate precedence.
    - `Api/Accounting/finalize_payroll_batch.php` — replace duplicate rate block only.
    - `Api/Employee/get_employee_payroll.php` — replace duplicate rate block only.
    - `Testing/Attendance/Api/get_employee_payroll_context.php` — rate-card API.
    - `Testing/Attendance/Api/get_employee_attendance_list.php` — shared rate helper.
    - `Testing/Attendance/Api/get_payroll_verification.php` — shared rate helper.
    - `Testing/Attendance/Api/run_employee_holiday_scenario.php` — selected mode entry.
    - `Testing/Attendance/Api/run_holiday_scenarios.php` — context-only server launcher.
    - `Testing/Attendance/holiday_e2e_runner.php` — source-rate isolated fixture mode.
    - `Testing/Attendance/attendance_testing.html` — read-only card and result region.
    - `Testing/Attendance/attendance_testing.js` — auto-load, stale guards, selected mode.
    - `Testing/Attendance/employee_rate_resolution_test.php` — CLI precedence tests.
    - `Testing/Attendance/README.md` and this report — documentation.
    Existing unrelated working-tree changes were retained.
21. **Database changes:** no production schema changes or employee salary writes.
    Controlled runs create disposable schema-only databases and anonymous test
    records, then remove them. Production checksums before/after match for holidays,
    employee, attendance, daily_time_records, overtime, dtr_submissions, payroll and
    payroll_runs. All completed runs report cleanup PASS. Temporary browser-only
    verification wrappers/session were removed after verification.
22. **Tests 1–12:**
    - Test 1 rate retrieval: PASS, browser card matched source row.
    - Test 2 switch employee: PASS, Jhonel ₱700/₱87.50 → Tom Cruize ₱500/₱62.50;
      old card cleared and button disabled while loading.
    - Test 3 normal day: PASS, `employee_rate_A.json`.
    - Test 4 regular holiday: PASS, `employee_rate_B.json`.
    - Test 5 special holiday: PASS, `employee_rate_D.json`.
    - Test 6 regular holiday OT: PASS, `employee_rate_tamper_test.json` and browser.
    - Test 7 Team Lead: PASS for actual production review API and classification in
      disposable fixture. Live Team Lead browser rendering: NOT VERIFIED this task;
      no real employee records were added for this test.
    - Test 8 Accounting: PASS for production submit/verify/compute/finalize APIs in
      disposable fixture. Live Accounting browser rendering: NOT VERIFIED this task.
    - Test 9 different site: PASS, Limketkai / Trishia / charlene suzon,
      EMP2026000002, deployment 2, own ₱500/₱62.50 loaded in browser.
    - Test 10 missing rate: PASS, salary-less anonymous branch fixture API returns
      rates.valid=false and explicit error, no fallback. All real selected employees
      had rates; none was modified to manufacture a missing salary.
    - Test 11 tampered rate: PASS, submitted ₱5,000 rates ignored; authoritative
      server amount remains ₱1,855, not the submitted-rate amount.
    - Test 12 synthetic regression: PASS, all A–K + boundary (12 cases), including
      missing-rate and branch/context checks. `employee_rate_synthetic_regression.json`.
    PHP lint and JS syntax checks passed. Six rate precedence/rounding checks passed
    in `employee_rate_resolution_test.json`, preserving the finalizer's existing
    salary-credit behavior separately from Employee API's existing credit fallback.
23. **Remaining limitations:** selected controlled mode supports worked A–H scenarios,
    fixed 8h duty and optional 2h OT; rest scenarios need an actual scheduled rest
    date. Unworked/boundary acceptance cases remain available in the synthetic suite.
    Manual Save Record/Save OT tools still write actual attendance; the new controlled
    button isolates records instead. Current salary fields, not historical rate
    dates, are used. Allowance is zeroed only in the anonymous fixture for isolated
    compensation comparison. Tests do not validate allowance or statutory deductions.
    Different historical/current payslip cutoffs are explicitly marked NOT VERIFIED
    by the runner. No full overnight-shift or every schedule/rate combination tested.

Browser proof: `employee_rate_browser_verified.jpg`. Structured selected results:
`employee_rate_selected_test.json`, `employee_rate_A.json`, `employee_rate_B.json`,
`employee_rate_D.json`, `employee_rate_E.json`, `employee_rate_tamper_test.json`.
