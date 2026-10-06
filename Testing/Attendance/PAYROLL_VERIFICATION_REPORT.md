# Payroll verification repair — 2026-10-05

## Request and root cause

The existing button constructs `Api/get_payroll_verification.php?employee_id=<selected EmployeeID>&date=<selected YYYY-MM-DD>`. From the connected testing page this resolves to `http://localhost/Employee_Tracking/Testing/Attendance/Api/get_payroll_verification.php?employee_id=<selected EmployeeID>&date=<selected YYYY-MM-DD>`.

For the employee/date exercised in the browser, the exact URL is `http://localhost/Employee_Tracking/Testing/Attendance/Api/get_payroll_verification.php?employee_id=1&date=2026-09-23`. The original screenshot's complete query parameters were not supplied; its original EmployeeID must not be assumed.

The expected endpoint did not exist. The actual adapter had the doubled filename `Testing/Attendance/Api/get_payroll_verification.phpget_payroll_verification.php`. The relative directory was correct. A 404 HTML response failed JSON parsing and the old catch handler incorrectly called it a network error.

## Files changed

- Renamed the existing adapter to `Testing/Attendance/Api/get_payroll_verification.php`; added GET-only handling, real calendar-date validation, stable deployment ordering, 422 for an unavailable employee, and safe server-error responses. Removed its explicit schema-initialization calls.
- Modified only the Payroll Verification request/error handling in `Testing/Attendance/attendance_testing.js`. Existing unrelated edits in this file were preserved.
- Created `Testing/Attendance/payroll_verification_response_test.cjs`, this report, and `Testing/Attendance/payroll_verification_verified.png`.

No production helper, authentication file, business formula, database structure, or unrelated UI was edited for this repair. The project already contained other uncommitted changes.

## Integration and security

The existing adapter was restored, rather than creating another payroll engine. EmployeeID comes from the existing company → Team Lead → Employee selectors. The endpoint loads the selected active employee's configured rates and existing salary fallback inputs, and its deployment covering the selected test date. `payroll_type_period_for_company()` resolves the company's actual Type A/B cutoff; `payroll_type_deductions()` supplies the existing cutoff deduction flags. The adapter passes those inputs and WorkingDays to `payroll_compute_period()` in `Api/Helpers/employee_payroll_helper.php`.

Accounting's `client_billing_build_export()` in `Api/Helpers/client_billing_helper.php` and `Api/Employee/get_employee_payroll.php` use that same engine. No independent earnings, holiday, overtime, or deduction formulas were added. Existing manual/auto-filled Expected values remain comparison inputs in the browser and are not sent to the endpoint.

The unchanged `_testing_guard.php` uses existing session initialization and requires authenticated Admin access. An unauthenticated request returned HTTP 403. The authorized browser verification succeeded with JSON data. GET-only enforcement prevents other methods from invoking the computation. Exceptions are logged on the server; raw exception details and HTML server responses are not displayed to users.

Queries use existing `employee`, `deployment`, `clientcompany`, `daily_time_records`, `overtime`, `status`, `leaves`, `holidays`, `sss_rates_config`, and `gov_percentage_rates` data through existing helpers. The verification does not call payroll creation, approval, finalization, payment, or attendance mutation APIs.

## Runtime evidence

Using the normal signed-in Admin session, selected employee 1 and date 2026-09-23 produced Type B cutoff 2026-09-11 through 2026-09-25, release 2026-09-30. Actual basic/gross pay was 700, overtime and holiday pay were 0, deductions were 450, and net pay was 250. A separate CLI invocation of the production engine with the employee's database rates, deployment, and resolved cutoff returned the identical gross/deductions/net amounts. No Accounting batch was created or paid for this comparison.

Full database SQL snapshots including schema and rows were identical before and after the first browser verification: SHA-256 `0CD55F30755240C76445F14682CC45275817CDDC830CF157A399C1B10102D966`. Temporary dumps were removed immediately after comparison. This verifies no database changes in the tested environment. Existing production helpers retain internal schema-ensure behavior; this does not establish database-enforced read-only behavior on an uninitialized installation.

The comparison was then rerun with Expected Gross = 700 and Expected Net = 999. Gross displayed MATCH and net displayed MISMATCH, while Actual amounts remained unchanged. The test tab's captured error log was empty.

## Required test results

1. **PASS — Endpoint:** Run Actual Payroll Check executed successfully in the authenticated browser and rendered the real response; the repaired PHP success path returns HTTP 200. No endpoint 404 occurred.
2. **PASS — Employee:** Output belongs to selected employee 1 and its selected company.
3. **PASS — Period:** Browser and direct production helper returned Type B September 11–25 for September 23.
4. **PASS — Production engine:** Existing adapter invokes `payroll_compute_period()`; direct invocation agrees with browser totals.
5. **PASS — No duplicate formulas:** Adapter resolves inputs and delegates payroll computation. No new payroll formula was implemented.
6. **PASS — Read-only verification:** Database rows and schema snapshots were identical around the browser check; no payroll run, approval, payment, or attendance write occurred.
7. **PASS — Actual result:** Basic, overtime, holiday, gross, deductions, and net were displayed from the response.
8. **PASS — Comparison:** Both MATCH and MISMATCH exercised using manual Expected values.
9. **PASS — Console:** Captured test-tab error log was empty. PHP lint and JavaScript syntax check passed.
10. **NOT VERIFIED — Attendance mutation features:** Company/lead/employee selectors, attendance listing, and holiday/cutoff lookup loaded successfully. Add/edit/delete and save overtime were deliberately not executed because they mutate real records; their handlers/endpoints were not changed.

Additional **PASS**: isolated response-contract tests cover valid success, HTTP 404/401/403/400/422/500, invalid JSON, incomplete response, mismatched employee, and genuine fetch rejection. These simulated transport tests do not claim actual server fault injection or authenticated non-Admin testing. Actual non-Admin-role sessions and every invalid-data server branch are **NOT VERIFIED**.

## Limitations and separate issues

No separate production payroll defect was established. Exact equivalence with a finalized Accounting batch remains **NOT VERIFIED**; its selected employees, cutoff, rates, and schedule must match, and creating a real batch was outside this read-only repair. Type A was traced to the existing resolver but not exercised against a Type A employee in the browser. Direct browser navigation to the JSON endpoint was blocked by the browser client; the dashboard's authenticated fetch and rendered result were successfully tested. The production helpers' schema-ensure behavior remains unchanged and should not be described as a database-enforced read-only transaction.
