# Client workbook / production holiday payroll verification

Verified 2026-10-05 against the connected Employee_Tracking project and local XAMPP/MariaDB.

## Why did the test return Holiday Pay = PHP 0?

The database contains no holiday rows between 2026-09-01 and 2026-10-31, including the previously tested September 23 date. The production helper obtains holidays from the `holidays` table by date. A testing-screen expected-rule selection does not create an official holiday record. Zero holiday pay on that date therefore follows the stored data. No holidays were added to the live database during this work.

This data issue is distinct from the defects corrected below. A worked regular holiday now produces a PHP 1,000 holiday premium plus PHP 1,000 basic pay, rather than putting the entire PHP 2,000 into the holiday component. A worked special holiday produces a PHP 300 premium plus PHP 1,000 basic pay.

## Reference inspected and preservation

The actual supplied file is `C:/Users/Irishdwin Gamutan/OneDrive/Desktop/Onazul Capstone/files/SAMPLE.xlsx`, not a file named SAMPLE(3).xlsx. It is byte-for-byte identical to the project's `Templates/SAMPLE.xlsx`.

Both SHA-256 hashes before and after verification are:

`231d425ef115a2e3215586e57139dd4621160104eaf971f63cac393fd7c506b7`

Neither workbook was saved or modified. Formula comparisons loaded a workbook into memory and changed controlled inputs there only.

One worksheet exists: **JUNE 16-30 2026**, with a formatted extent of 969 rows and 60 columns. Actual named employee rows are 6–15. All worksheet rows were scanned for formulas, including the unnamed row 16 and summary rows 17–18. The title in B1 says JUNE 16-31 2026, an invalid date retained in the reference.

## Actual workbook formulas and interactions

The following are the actual row 6 formulas, repeated across named employee rows 6–15:

- E: daily rate; F: number of days; G `=F6*E6` basic total.
- I `=E6*1.25/8` ordinary OT hourly rate; J ordinary OT hours; K `=I6*J6` ordinary OT total.
- L: late minutes; M `=E6/8/60*L6`; N gross salary `=G6+K6-M6`.
- O: allowance; P SIL accrual `=E6*5/12/2`; Q add-back.
- R: legal holiday duty hours; S holiday pay `=E6*2/8/2*R6`. This simplifies to hourly rate times hours: the **additional 100% premium**, not total 200% duty pay.
- T: legal holiday OT hours; U `=E6/8*T6*2.6`: the full legal holiday OT amount.
- V: special holiday duty hours; W `=(E6*30%)/8*V6`: the **additional 30% premium**.
- X: special holiday OT hours; Y `=(E6*1.3)*1.3/8`: hourly rate times 1.69; Z `=X6*Y6`.
- AA additional earnings `=O6+P6+Q6+S6+U6+W6+Z6`.
- AB gross payroll `=AA6+N6`; AD total `=AB6`.

Basic pay already pays the first 100% of worked holiday hours through F/G. Adding total 200% or 130% holiday duty pay again would overpay. Holiday OT is separate from ordinary OT and must be added exactly once.

Exceptions found: unnamed row 16 uses W16 `=(E16*30%+E16)/8*V16`, representing total 130%, but has blank employee/rate/day inputs and no AA16/AB16 formulas. It is not evidence that named rows use total 130% in W. Row 17 sums rows 6–16. AD18 subtracts P17 from AD17. Thus the workbook's row-level SIL inclusion and later summary subtraction must not be treated as an established cash-payment policy without clarification. No corresponding rest-day or double-holiday formulas were found in the named employee rows. Cached/reference inputs do not establish actual historical holiday payments; controlled formula recalculation was used instead.

## Comparison matrix

Each item gives client formula, production behavior after correction, status, and action. MATCH refers to the controlled comparable component, not every possible payroll policy.

1. **Basic daily pay — MATCH.** Client G=days*daily rate; engine basic pay is earned workdays*daily rate. Controlled ordinary day: 1,000. Preserved.
2. **Ordinary OT — MATCH for default rate.** Client I/J/K use hourly*1.25*hours. Engine uses the same fallback and preserves explicit approved OT rates. Export K now receives the actual ordinary approved/DTR OT amount. Custom approved rates intentionally need not equal template I*J.
3. **Legal holiday duty hours — MATCH for worked duty.** Client R holds duty hours; export uses the production breakdown's capped first-eight duty hours rather than assuming every entry is eight hours. Corrected mapping.
4. **Legal holiday pay — MATCH for ordinary worked holiday.** Client S adds 100%; production adds (2-1)*hourly*duty hours on top of basic. Controlled total 2,000. Corrected export AA to include S once.
5. **Legal holiday OT — MISMATCH corrected / MATCH in controlled ordinary case.** Client U=hourly*2.6*OT hours. Engine previously read a date's list of holidays as one holiday object; now normalizes it and applies the day's multiplier*1.3. Export U uses computed amounts, including supported rest/double variants. Controlled OT 650.
6. **Special duty hours — MATCH for worked duty.** Client V holds hours; export reads actual capped duty hours. Corrected mapping.
7. **Special holiday premium — MATCH for named rows.** Client W adds 30%; engine adds (1.3-1)*hourly*duty hours. Controlled total 1,300. Unnamed row 16's different formula is documented above.
8. **Special holiday OT — MISMATCH corrected / MATCH in controlled ordinary case.** Client X/Y/Z use hours*hourly*1.69. Engine normalizes holidays and classifies approved holiday OT correctly. Builder now supplies the missing special OT amount; export Z uses it. Controlled OT 422.50. Y shows Z/X when OT exists, so mixed/rest-day rates are represented accurately.
9. **Gross salary — PARTIAL.** Client N=basic+ordinary OT-lates. Export now uses G+K-M+H, retaining the project's existing separate Sunday earnings in H. Internal billing helper gross_salary still includes its total OT for existing accrual calculations; those unrelated policies were preserved. Therefore internal helper components are not a literal cell-for-cell copy of N.
10. **Additional earnings — MISMATCH corrected, remaining PARTIAL.** Export AA now includes O/P/Q/S/U/W/Z once. Allowance/add-back and holiday components follow this structure. SIL P remains an export accrual absent from engine gross earnings; it was not silently inserted into employee gross pay.
11. **Gross payroll — MISMATCH corrected for holiday reconciliation, remaining PARTIAL for complete payroll.** N+AA now adds holiday premiums/OT once; AD=AB avoids the earlier repeated additions. Four client formulas and all 14 controlled renderer cases reconcile when unrelated SIL/earnings are explicitly zero. Full payroll agreement is not claimed because of SIL/Sunday/accrual differences.
12. **Scheduled rest-day holiday — MISMATCH corrected / client NOT VERIFIED.** Engine now includes the earned basic portion and avoids treating first-eight holiday Sunday hours as extra ordinary Sunday OT. Regular rest duty 2,600 and special rest duty 1,500 passed. The supplied named-row workbook has no explicit rest-day formulas.
13. **Double regular holiday — production PASS / client MISSING.** Worked duty 3,000 and eligible unworked duty 2,000 passed. No explicit corresponding workbook formula. Combined double-holiday/rest-day coverage is not established by these tests.
14. **Preceding-day eligibility — PARTIAL.** Presence on the preceding scheduled working day passed; absence failed eligibility as expected. Existing approved-leave logic does not distinguish paid from unpaid leave. `leaves.AffectsPayroll` and `LeaveType` have no implemented paid/unpaid interpretation elsewhere in the scanned PHP APIs. No new policy was inferred.

## Root causes and changes

- Holiday lookup returns a date-to-list structure. The OT path incorrectly accessed multiplier/name/type on that list, causing incorrect holiday OT calculations. Normalize the date list before computing rates.
- A rest-day DTR classification bypassed normal earned-duty accounting. Compute worked holiday duty using the existing time calculation with a local schedule copy; do not change Team Schedule or attendance calculation helpers.
- Holiday Sunday duty could enter ordinary Sunday OT accounting. Keep holiday duty and holiday OT in the holiday branch.
- Approved OT used its ordinary rate even on a holiday. Classify approved sessions by holiday date and add them to holiday OT once; keep ordinary explicit approved rates intact.
- Holiday premiums assumed full days. Use capped actual duty hours and expose those hours for the export.
- Export construction omitted approved ordinary OT and special holiday OT amounts. Supply those actual components.
- Export formulas omitted legal premium S from additional earnings and mixed already-totalled amounts with additional earnings, repeating components. Restore separate N/AA/AB/AD aggregation and use computed holiday OT amounts.

Modified production files in this task:

- `Api/Helpers/employee_payroll_helper.php`
- `Api/Helpers/client_billing_helper.php`
- `Api/Accounting/Lib/client_billing_spreadsheet.php`

Created verification files:

- `Testing/Attendance/holiday_payroll_test.php`
- `Testing/Attendance/holiday_comparison_results.json`
- `Testing/Attendance/HOLIDAY_PAYROLL_REPORT.md`

Other pre-existing workspace changes were preserved. No new production API, authentication change, database migration, live holiday definition, payroll payment, or reference-workbook modification was performed.

## Export mapping

- R: regular duty hours from actual breakdown.
- S: regular/double holiday earnings component; worked cases are additional premiums, eligible unworked cases are the full unworked entitlement with no earned basic component.
- T: regular holiday OT hours.
- U: actual regular holiday OT amount, including applicable rest/double rates.
- V: special duty hours.
- W: additional special holiday premium.
- X: special holiday OT hours.
- Y: effective special OT rate Z/X when hours exist, otherwise the template default hourly*1.69.
- Z: actual special holiday OT amount.
- AA: O+P+Q+S+U+W+Z; AB: N+AA; AD: AB.

This keeps premiums and OT separate instead of placing the entire holiday total into one column.

## Controlled calculations and test results

Daily rate 1,000; hourly rate 125; unrelated earnings/deductions zero:

- Regular worked eight hours: basic 1,000 + premium 1,000 = **2,000 — PASS**.
- Regular eight hours plus two OT: 1,000 + 1,000 + 650 = **2,650 — PASS**.
- Special worked eight hours: 1,000 + 300 = **1,300 — PASS**.
- Special eight hours plus two OT: 1,000 + 300 + 422.50 = **1,722.50 — PASS**.

Executed `C:/xampp/php/php.exe Testing/Attendance/holiday_payroll_test.php`: **PASS**, exit 0, 32 assertions (14 production-engine scenarios, four recalculations of the actual client formulas, 14 actual export-renderer reconciliations). Engine scenarios also include ordinary work, scheduled rest days, eligible/ineligible unworked holidays, double regular holidays, and approved holiday OT. PHP warnings are converted to exceptions in the test. Tests use temporary MariaDB shadow tables and an in-memory SQLite DTR store; no live payroll/attendance/holiday records are written. Renderer tests exercise the real production renderer but provide controlled row data and explicitly zero SIL in memory.

PHP syntax checks for the three changed production files and the test: **PASS**. Reference hashes unchanged: **PASS**.

Full authenticated Accounting batch/export and Employee payslip browser workflow: **NOT VERIFIED** in this task. Full live-data nonzero holiday payroll: **NOT VERIFIED**, since the inspected September–October range has no official holiday records. Native Microsoft Excel recalculation and visual print/export review: **NOT VERIFIED**; calculation checks used PhpSpreadsheet. Full payroll reconciliation including SIL, Sunday column conventions, 13th-month accrual and government deductions: **NOT VERIFIED** and outside these targeted holiday corrections. Exempt employee coverage and paid-versus-unpaid approved-leave eligibility remain limitations.

## Does the production engine now reach the client's economic holiday result?

**Yes for the four controlled ordinary worked-holiday cases:** the real production engine, the client's actual named-row formulas, and the production export renderer agree on 2,000 / 2,650 / 1,300 / 1,722.50 when unrelated earnings are equal. This does not claim full payroll equivalence or verified live holiday data. Rest-day and double-holiday cases were checked in the engine/renderer; the workbook does not supply explicit matching formulas for those cases.

Regular-holiday rest-day and overtime multipliers are consistent with [DOLE's published holiday wage guidance](https://dole.gov.ph/news/payment-of-rightful-holiday-wages-a-duty-bello/). The workbook itself is the reference for the client component formulas; no unsupported business policy was introduced.
