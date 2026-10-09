# Client holiday formula verification

Verified 9 October 2026 against the supplied workbook, four screenshots and current connected production code.

## Reference and workbook inspection

The attachment prompt calls the workbook SAMPLE(4).xlsx. The actual file supplied with this request is `C:\Users\Irishdwin Gamutan\OneDrive\Desktop\Onazul Capstone\files\SAMPLE.xlsx`; that file was inspected. Its SHA-256 is `231d425ef115a2e3215586e57139dd4621160104eaf971f63cac393fd7c506b7`. `Templates/SAMPLE.xlsx` is byte-for-byte identical. Neither file was modified.

Every populated value and formula across all worksheets was scanned, including cells outside the visible payroll table. There is one worksheet, `JUNE 16-30 2026`, with stored dimensions A1:BH969. Employee identities and rates occupy rows 6–15. Row 16 has formulas but no identity or daily rate. Row 17 totals rows 6–16. Rows 22–45 contain billing summaries; remaining populated cells include preparer information and a footer at AA67. Most of the apparent 969-row extent is empty formatting.

All formulas in comparable employee rows 7–15 match translated row-6 formulas. There are no literal Excel error cells. This is an inventory and reference check, not proof of native Excel recalculation. Formula inventory and merge/print metadata are saved in `Testing/PayrollBilling/client_workbook_inventory.json`.

The key chain is:

- G6 = F6*E6: ordinary basic wages.
- I6 = E6*1.25/8; K6 = I6*J6: ordinary overtime.
- M6 = E6/8/60*L6; N6 = G6+K6-M6: wage deduction and gross salary.
- S6 = E6*2/8/2*R6: **additional 100%**, not full 200%, regular-holiday duty pay.
- U6 = E6/8*T6*2.6: full regular-holiday overtime at 260% of ordinary hourly wages.
- W6 = (E6*30%)/8*V6: **additional 30%**, not full 130%, special-holiday duty pay.
- Y6 = (E6*1.3)*1.3/8; Z6 = X6*Y6: full special-holiday overtime at 169%.
- AA6 adds allowance, incentive-leave accrual, Add Back and S/U/W/Z; AB6 = AA6+N6.
- AD6 = AB6; AM6 = AD6-AL6: gross less workbook employee deductions.
- C23:C31 links basic wages, ordinary OT, allowance, Add Back, incentive leave and holiday amounts to row 17. C32 sums these, C34 deducts lateness, and subsequent summary rows add employer costs, overhead and tax/withholding to C45.

No worked duty hours or overtime hours are populated in the sample. The sample therefore establishes arithmetic and presentation, but does not prove attendance sourcing, approval or eligibility.

W16 = (E16*30%+E16)/8*V16 computes **full 130%**, unlike the employee-row 30% premiums. Row 16 is not the summary row and has no identity, daily rate or ordinary-pay formula G16. It cannot safely establish a different employee policy. If populated later, its result depends on whether basic wages are already included elsewhere. The row is reported for client confirmation and was not copied as a new business rule. Other row-16 exceptions include N16 omitting the lateness deduction and different government-dues references.

The workbook title B1 says June 16–31 although the worksheet is June 16–30. Other pre-existing presentation/deduction limitations include AL6 summing only AE6+AF6 and AS6 omitting AQ6. These were documented without changing unrelated deductions or the source template.

## Comparison

Rates below use the requested test daily wage of PHP 1,000 and hourly wage of PHP 125. Production code uses actual server-resolved employee rates.

| Scenario | Workbook formula | Screenshot rule | Current system formula | Result | Required action |
| --- | --- | --- | --- | --- | --- |
| Regular worked 8h | G ordinary wages + S=E/8*R | Daily*2 = 2,000 | Ordinary daily wages + hourly duty premium*(2-1) | EQUIVALENT WITH DIFFERENT ACCOUNTING PRESENTATION | Retain premium accounting; verified 2,000 |
| Regular worked 8h+2OT | G+S+U; U=E/8*T*2.6 | 2,000+650=2,650 | Base + 100% premium + approved OT*hourly*2.6 | MATCH | Verified 2,650; ordinary OT bucket excludes holiday OT |
| Regular worked on rest day 8h | No dedicated 260% duty formula | Daily*2.6=2,600 | Base + 160% duty premium | MISSING FROM WORKBOOK | Existing engine verified; export authoritative S value |
| Eligible unworked regular | No eligibility/unworked formula; R=0 produces S=0 | Daily*1=1,000 | No basic duty day; holiday earnings daily*1 after preceding-workday evidence | MISSING FROM WORKBOOK | Existing entitlement verified; export S=1,000 with R=0 |
| Unworked special | No duty input gives W=0 | 0, absent an explicit company policy | No holiday earnings | MATCH | Retain no-work/no-pay; no invented company policy |
| Special worked 8h | G+W; W=E*.3/8*V | Daily*1.3=1,300 | Ordinary wages + 30% duty premium | EQUIVALENT WITH DIFFERENT ACCOUNTING PRESENTATION | Verified 1,300 |
| Special worked 8h+2OT | G+W+Z; Y=E/8*1.3*1.3 | 1,300+422.50=1,722.50 | Base + 30% premium + approved OT*hourly*1.69 | MATCH | Verified 1,722.50 |
| Special worked on rest day 8h | No dedicated 150% duty formula | Daily*1.5=1,500 | Base + 50% duty premium | MISSING FROM WORKBOOK | Existing engine verified; export authoritative W value |
| Double regular worked 8h | No double-holiday formula | Daily*3=3,000 | Base + 200% duty premium, counted once per date | MISSING FROM WORKBOOK | Existing engine verified; export authoritative S value |
| Eligible unworked double regular | No formula | Daily*2=2,000 | No basic duty day; holiday earnings daily*2 after eligibility | MISSING FROM WORKBOOK | Added complete pipeline regression; verified 2,000 |
| W16 versus W6:W15 | Full 130% versus additional 30% | Full total is 130%, premium is 30% when base included | Employee duty premiums remain separate from base | REQUIRES CLIENT CONFIRMATION | Keep unused source row; do not infer a second employee policy |
| Rest-day holiday OT / double-holiday OT | U=2.6 and Y=1.69 do not establish these combinations | Unspecified; prompt prohibits invention | Previously automatically derived duty multiplier*1.3; now review required | REQUIRES CLIENT CONFIRMATION | Stop computation for approved OT in unsupported combinations |
| Double holiday plus rest-day duty | No formula | Unspecified | Previously ordinary double-holiday rate; now review required | REQUIRES CLIENT CONFIRMATION | Confirm client combination before payroll computation |
| Approved leave preceding holiday | No leave logic | Paid leave qualifies; unpaid absence may not | Previously any approved leave qualified; now review required without reliable paid status | REQUIRES CLIENT CONFIRMATION | Do not infer paid status from approval, LeaveType text or AffectsPayroll |

## Trace and changes

Inspected `Api/Helpers/employee_payroll_helper.php`, `holidays_helper.php`, `team_schedule_helper.php`, `dtr_classification.php`, `client_billing_helper.php`, `payroll_workflow.php`, `payroll_finalization.php`, `billing_revision_helper.php`, `Api/Accounting/compute_payroll_for_batch.php`, `finalize_payroll_batch.php`, `Lib/client_billing_spreadsheet.php`, `Api/Employee/get_employee_payroll.php`, and the existing payroll/attendance test runners and leaves schema.

The production helper already implemented the ten established monetary examples. It remains the single calculator. No second payroll engine was introduced. Ordinary daily wages are in basic pay; worked holiday earnings add only `(multiplier-1)*daily/8*dutyHours`, capped at the first eight rendered hours. Unworked holiday compensation adds the full entitlement because there is no ordinary worked day. Approved holiday overtime is separate from ordinary overtime. Existing late/undertime deductions reduce ordinary wages on partial days; a three-hour double-holiday fixture verified a 750 premium and 1,125 total after the existing deduction, within its rounding tolerance.

Changes in this request:

1. `employee_payroll_helper.php`: preceding-day holidays now respect employee/deployment scope; qualifying attendance requires completed clock-out. Missing preceding-day evidence and approved leave with uncertain paid status stop for Accounting review. No preceding working day within the existing lookup also stops for review.
2. The same helper stops on incomplete holiday attendance, ambiguous recorded classifications, unsupported coinciding-holiday combinations, double holiday plus rest-day duty, unconfirmed rest-day/double-holiday overtime, and overnight holiday records needing calendar-date allocation. Future unworked holidays are not paid prematurely or subjected to a missing-future-attendance eligibility decision.
3. `Api/Employee/get_employee_payroll.php`: finalized current-period payslips read stored payroll before considering live recomputation. Later review guards do not block or change stored finalized amounts. Existing payroll snapshot components are still used for itemization.
4. `Testing/Attendance/holiday_e2e_runner.php`: eligible unworked double-holiday scenario and review/partial/rate/stored-payslip guards added; unpaid-absence fixture now contains an explicit absent record.
5. New read-only inventory script, Excel parity test and this report.

No migration was required. No production records, historical attendance, deduction rates, dashboard layouts, source templates, holiday exemptions or calendar indicators were changed. The authoritative Holidays table and existing per-date fixed/dated schedule resolver continue to determine holiday and rest-day context. Sunday is not assumed to be every employee's rest day.

## DTR, export and integrity

The disposable scenarios traverse actual attendance recording, Team Lead DTR review/submission, Accounting verification/computation, billing construction, payroll finalization, stored payroll and the employee payslip endpoint. No production employee records are copied into fixtures. Production checksums before/after verify the protected production tables remained unchanged; fixture database and temporary server are removed in finally cleanup.

The export already uses production holiday breakdowns in R/S/T/U and V/W/X/Y/Z. It retains source formulas when their calculated values match the authoritative components, and uses authoritative amounts when a source formula cannot represent rest-day, double or unworked holiday compensation. S is additional premium for worked duty but full entitlement for eligible unworked holidays; R accurately remains zero for unworked holidays. No extra hours are invented to make S match E/8*R. Y can display the effective special-OT rate. Existing totals add each component once.

This export representation is necessarily limited: the source has no distinct rest-day, double-holiday or unworked-entitlement columns. Aggregated S/W values cannot by themselves explain every date's eligibility or multiplier. DTR/payroll breakdowns retain the date-level context. No new client columns were added.

Server role/CSRF checks, employee/site ownership, approved-OT filtering, DTR verification, transaction rollback, audit and authorized billing revision workflows remain in place. Review messages are validation failures, not database debug output. The billing workflow regression verifies protected formulas, ownership, rollback and replay safeguards. Existing adjustment workflows are retained; this task adds no mechanism for bypassing a review guard using a browser-supplied multiplier or paid-leave claim.

## Actual verification

Artifacts:

- `Testing/PayrollBilling/client_workbook_inventory.json`: full formula and layout inventory; employee-row exceptions empty; source/template identical.
- `Testing/PayrollBilling/client_holiday_results.json`: **13/13** end-to-end scenarios passed, with production checksums unchanged and cleanup confirmed.
- The same file: **13/13** additional guards passed: missing preceding attendance, ambiguous paid leave, incomplete clock-out, double-holiday OT, unapproved OT exclusion, overnight allocation review, partial premium, partial total, different salary rate, double-holiday rest day, stored finalized payslip after review, rest-day holiday OT and unknown classification.
- `Testing/PayrollBilling/client_holiday_export_results.json`: **163/163** checks passed using the production PhpSpreadsheet renderer/evaluator. G/R/S/T/U/V/W/X/Z/AB/AM agree with all 13 synthetic payroll rows; employee payslip net values agree; summary gross/net/holiday/OT cells reconcile; sample names are replaced by synthetic fixture names.
- `Testing/PayrollBilling/client_holiday_workflow_results.json`: **100/100** existing billing/revision/integrity checks passed.
- PHP syntax checks passed for both changed production files, the runner and export test.

Baseline expected/actual holiday totals were identical: eligible unworked regular 1,000; regular worked 2,000; regular with OT 2,650; regular rest day 2,600; unworked special 0; special worked 1,300; special with OT 1,722.50; special rest day 1,500; eligible unworked double 2,000; worked double 3,000. Extra scenarios cover ordinary duty, explicit unpaid absence and skipping preceding holiday/rest days.

An early export-test draft referenced NetPay fields not included in the fixture's stored-payroll projection and compared an uppercase export name case-sensitively. Those assertions failed; the test was corrected to use the actual finalized payslip net field and established uppercase export formatting. The corrected 163-check run passed. No production calculation was changed to satisfy those draft assertions.

## Limits and client decisions

- The schema has LeaveType, Approved status and AffectsPayroll, but no established reliable paid/unpaid convention. Approved leave therefore requires review; this run deliberately does not automatically award or deny paid-leave eligibility. Confirm that convention before adding automated entitlement.
- Confirm holiday/rest-day overtime, double-holiday overtime, double holiday plus rest-day duty, and overnight calendar-date allocation. The screenshots do not establish these combinations; calculations stop instead of guessing.
- Native Microsoft Excel rendering/recalculation and full manual UI review were not executed. Workbook files/layout were unchanged. Export numerical verification used the actual production PhpSpreadsheet engine.
- The 13 end-to-end examples use synthetic Type A October 1–15 fixtures at 1,000/125; a second salary rate was tested directly. Site scoping and different-client ownership are covered in existing workflow checks. This is not an exhaustive end-to-end matrix of every Type A/B cutoff and salary combination.
- Approved paid-leave payment and successful overnight holiday allocation remain unimplemented policy-dependent scenarios; their review stops are tested. Unsupported combinations are not claimed as completed computations.

For supplementary accuracy checking only, DOLE's [2024 holiday pay guidance](https://dole.gov.ph/news/dole-issues-pay-rules-for-regular-holidays-special-non-working-days-in-2024/) and [2024 monetary benefits handbook](https://nwpc.dole.gov.ph/wp-content/uploads/2024/11/Workers-Statutory-Monetary-Benefits-Handbook-2024-Edition.pdf) corroborate the standard rates and paid-leave distinction. These were not substituted for the client's references to invent additional business rules. Exempt-employee classifications remain outside scope as instructed.
