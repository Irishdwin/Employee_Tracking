# Team Lead DTR classification fix — 2026-10-06

Implemented directly in C:\xampp\htdocs\Employee_Tracking. Existing unrelated work was preserved. The Team Lead table design and headings were not changed.

## Findings and changes

1. **Why OT showed zero:** get_dtr_cutoff_review.php called payroll_compute_period(), but discarded its OT totals. It independently aggregated excess daily hours. The current seven daily records each resolve to 8 net hours, so that loop returned zero despite approved overtime records. Approved sessions were missing from the review totals.

2. **Actual Oct 1:** Jhonel Tuling, EmployeeID 77, DeploymentID 47, SiteID 4. Attendance and daily_time_records store 08:00–17:00 and 8 hours. Break timestamps are NULL; the resolved schedule supplies a 60-minute break. There is a separate Approved 2-hour overtime session, 17:00–19:00, for DeploymentID 47. Result: 8 regular, 2 approved ordinary OT, 0 holiday-work hours.

3. **Actual Oct 2:** same 08:00–17:00 attendance, stored 8 hours and resolved 60-minute scheduled break. No approved overtime for that date. Result: 8 regular, 0 OT, 0 holiday-work hours.

4. **Were the screenshot's two extra hours approved?** There is no evidence of an approved 1-hour session on each of those dates. Oct 1 has a separate 2-hour approved session; Oct 2 has none. A nine-hour clock span does not prove a payable ninth hour. The current database differs from the older screenshot: it stores 8 hours for both dates. The screenshot's historical 9-hour display cannot be reproduced from current records; its precise data/browser version is NOT VERIFIED. Current production classification subtracts the scheduled break even when break timestamps are absent.

5. **Previous OT source:** Team Lead's own per-day excess-hours aggregation, ignoring the production helper's approved-overtime bucket.

6. **Fixed OT source:** one shared projection of payroll_compute_period(): ordinary, existing Sunday duty, and approved holiday OT buckets. Ordinary and holiday overtime are now sourced from overtime records whose status.Name is Approved. Rendered excess alone does not create payable ordinary/holiday overtime. This fixes the production helper's former addition of inferred excess plus approved sessions, which could count the same hour twice. Existing rates, deductions and holiday pay multipliers were preserved. Employee payroll and the verified billing/payroll helper pass the deployment context to the same engine.

7. **Previous holiday source:** the review detected holiday dates and independently capped qualifying daily hours. The date lookup already returns date strings; it was not a structured-list comparison defect. In this real cutoff, zero holiday hours is valid: holidays contains no dates within Sep 26–Oct 10. The significant defects were separate aggregation, incomplete worked/rest-day classification, missing persisted holiday detail, and submission's omission of holiday OT from TotalOvertimeHours.

8. **Fixed holiday source:** payroll_compute_period().holiday_breakdown duty_hours for worked entries, plus the separate holiday_ot_breakdown. A double holiday retains its actual rest-day flag. Date detection still uses the calendar even when an unworked holiday has no pay entitlement.

9. **Meaning of Holiday Pay Hrs:** worked holiday base duty hours according to production classification; approved holiday OT is included in Total Overtime Hrs and retained separately. It is not money or unworked holiday entitlement. These worked holiday hours overlap the regular paid-day hours and must not be added to them as another full shift. The existing heading was kept; “Holiday Work Hrs” would be clearer if a later UI change is requested.

10. **Holiday source:** holidays: HolidayID, HolidayDate, HolidayName, HolidayType, PayMultiplier and the existing applicability fields; holidays_helper.php provides the production calendar. Regular Holiday, Special Non-working Day, double regular holiday and holiday/rest-day classifications remain distinct. Unworked eligible regular-holiday pay remains in the engine's entitlement breakdown with worked=false and no invented duty hours.

11. **Persistence:** new dtr_submissions rows use the same projection as review for TotalHours, OvertimeHours and TotalOvertimeHours. A versioned JSON snapshot is stored in the existing Remarks column. It includes worked holiday hours, holiday OT, holiday types/IDs, worked/rest-day/double flags and approved OT record/date/deployment references. Existing SiteID, DeploymentID and cutoff columns retain their meaning. No schema migration or historical backfill was made. Old submitted rows use their stored totals and show unknown holiday hours as null/“—”.

12. **Accounting:** both individual detail and batch APIs expose the snapshot; the batch OT column uses saved TotalOvertimeHours rather than a fresh overtime-table sum. The isolated verify_dtr_submission.php test passed and did not alter the classification snapshot. No changes were needed to that verification endpoint in this task.

13. **Payroll:** the shared production engine tests passed; the isolated compute_payroll_for_batch.php endpoint succeeded. client_billing_build_export() produced 8 regular-holiday duty hours, 2 holiday OT hours and 0 ordinary OT for the verified holiday fixture. Holiday rate formulas and the spreadsheet template were not edited. The current payroll/export architecture still recalculates from live source records; this task does not turn an older DTR snapshot into an immutable monetary payroll snapshot.

14. **Double counting:** ordinary and holiday inferred excess is no longer added on top of approved overtime. Approved holiday sessions go only into the holiday bucket; that bucket enters the DTR total once. Holiday worked hours exclude holiday OT and unworked entitlement. Double regular holidays have one worked-duty breakdown rather than two copies of the same shift. The existing non-holiday Sunday duty model was retained; a separate Sunday-duty/approved-session overlap scenario was NOT VERIFIED.

15. **Site and cutoff:** selected-site roster, other-lead rejection, cross-site submission rejection, and OT DeploymentID filtering passed. Queries restrict OT and daily records to cutoff dates. Existing daily_time_records has no SiteID/DeploymentID, so historical reassignment within one cutoff cannot be fully attributed to branches from that table; that scenario remains NOT VERIFIED. Unlinked legacy OT rows with NULL DeploymentID cannot be included in a deployment-scoped result without guessing their ownership. The actual Oct 1/2 records also predate DeploymentID 47's Oct 5 start date; they use the production fallback schedule. Their historical site provenance and whether they were entered manually are NOT VERIFIED; no attendance data was rewritten to conceal this inconsistency.

16. **Application files modified in this task:**

- Api/TeamLead/get_dtr_cutoff_review.php — shared totals and locked snapshot display.
- Api/TeamLead/submit_dtr.php — same totals, holiday OT and classification persistence.
- Api/Accounting/get_submitted_dtrs.php — persisted totals and snapshot retrieval.
- Api/Helpers/employee_payroll_helper.php — approved ordinary/holiday OT classification, deployment filter, traceable OT breakdown and double-holiday rest-day metadata.
- Api/Helpers/client_billing_helper.php — passes each verified DTR deployment to the same production engine.
- Api/Employee/get_employee_payroll.php — passes the selected placement's deployment to the same production engine.

Created application file: Api/Helpers/dtr_classification.php. Created test/evidence files under Testing/DTRClassification: engine_test.php, http_test.cjs, audit_actual.php, engine_results.txt, engine_output.txt, http_results.json, payroll_run.json, actual_employee_audit.json, REPORT.md and .htaccess. The test/evidence directory denies web access; PHP test scripts also require CLI execution.

17. **Database:** no migrations, new tables, new columns, destructive production changes or production business-record writes. Existing tables traced: employee, users, deployment, deployment_sites, daily_time_records, attendance, overtime, status, holidays, leaves, dtr_submissions and payroll_runs, plus the existing salary/deduction/schedule helpers' configuration tables. Fixture writes were limited to codex_multisite_20261005_r2, an existing disposable test database.

## Required tests

18. **Tests performed and results:**

- Test 1 normal 8-hour day — PASS: 8 regular, 0 OT, 0 holiday.
- Test 2 nine net rendered hours without approval — PASS: 8 regular, 0 payable OT. Pending overtime also excluded.
- Test 3 nine net rendered hours plus 1 approved OT — PASS: 8 regular, 1 OT counted once.
- Test 4 actual Oct 1 — PASS, database/helper trace: 8 regular, 2 approved OT, 0 holiday.
- Test 5 actual Oct 2 — PASS, database/helper trace: 8 regular, 0 OT, 0 holiday.
- Test 6 multi-day cutoff — PASS: isolated aggregation and seven-day actual audit; actual cutoff 56 regular, 8 approved OT, 0 holiday work.
- Test 7 regular holiday work — PASS: production-engine fixture and review/submission/Accounting HTTP flow.
- Test 8 special non-working holiday work — PASS: production-engine fixture preserves type and duty hours.
- Test 9 holiday plus approved OT — PASS: ten net duty hours plus approved two-hour session yields 8 holiday base hours and 2 holiday OT hours, counted once. Payroll export also preserves the holiday bucket.
- Test 10 unworked regular holiday — PASS: preceding-day eligible entitlement is preserved, worked holiday hours remain zero.
- Test 11 normal day after holiday — PASS: no holiday-work hours and no out-of-range OT.
- Test 12 DTR submission — PASS: actual isolated HTTP insert stores the same regular/OT/holiday snapshot as review; repeat submission does not rewrite it.
- Test 13 Accounting — PASS: detail, batch and verification endpoints retain submitted values even after the live OT status changes.
- Test 14 Payroll — PASS for production engine, isolated compute-run API and verified export hours/type mapping. Historical immutable monetary reconciliation is NOT VERIFIED.
- Test 15 already submitted employee — PASS: API displays saved totals after live source changes; existing production EmployeeID 3 submission still has 56 / 7 / 19 saved hours and ReviewedBy=3, unchanged. Old missing holiday snapshots remain unknown.
- Test 16 site filter — PASS for roster/access/submission and deployment-scoped approved OT. Historical same-employee reassignment within cutoff is NOT VERIFIED because daily records lack site/deployment linkage.
- Test 17 cutoff filter — PASS: queries and engine exclude the approved Oct 1 session from Oct 2-only computation.
- Test 18 broad regression — NOT VERIFIED in full. DTR review/submission, Accounting verification, payroll run, verified billing scope and all six spreadsheet renderer counts (0,1,3,10,12,25) passed. Seven changed application PHP files passed syntax checks. No production JavaScript or HTML was changed. Clock In/Out, Smart Calendar browser rendering, physical GPS, Team Schedule and other role dashboard runtime flows were not exercised during this task.

Additional PASS: rest-day plus regular holiday; double regular holiday preserving both flags; snapshot round trip; legacy unknown snapshot; unauthenticated access 401; wrong role 403; other Team Lead's site 403. Engine tests use real production helpers against fixture tables, not a second calculation engine. API tests use isolated authenticated sessions and an isolated PHP server; they are not tests of the user's active browser session.

19. **PASS / FAIL / NOT VERIFIED:** covered individually above. All executed final assertions passed. Initial isolated-server session-path and test-parameter setup errors were corrected before the passing rerun; they were not production fixes. No claim is made that every unrelated module or physical-device test passed.

20. **Remaining limitations:** historical same-cutoff branch reassignment requires reliable site-linked attendance data; legacy submission holiday snapshots cannot be reconstructed safely; payroll still recalculates money from live sources; non-holiday Sunday overlap requires a separate explicit verification; future-dated attendance rows already exist for Oct 7–9 and were retained; the older nine-hour screenshot's provenance cannot be established. These limitations were reported rather than solved through unrelated schema, payroll-policy, GPS or UI changes.