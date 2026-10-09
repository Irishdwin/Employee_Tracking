# DTR submission warnings and Accounting review

Updated 2026-10-09 following the user's clarification.

- A cutoff date does not prevent DTR submission. Existing authorization, unfinished-duty checks, duplicate prevention and submitted-record locks remain.
- The reminder/urgent warning begins the day after cutoff end, using each company's configured payroll period. It no longer begins five days before payday. This allows duty on the cutoff day to be recorded before the reminder.
- Team Leads may select and submit DTRs whose payroll calculation needs Accounting review. Existing nullable total columns remain NULL and the existing Remarks classification snapshot records the review reason; no zero hours or holiday entitlement is fabricated and no schema change is required.
- Accounting sees the review reason. Verification recalculates only review-pending classifications through the existing production helper after the evidence issue has been resolved. Unresolved review returns 422 without verifying the batch. Payroll computation also rejects a pending review with 422 before creating payroll runs. Verification never rewrites saved payroll.
- The cutoff list keeps pending review visible after submission, while the submitted row remains locked.

Changed production files: Api/TeamLead/get_dtr_cutoff_review.php, Api/TeamLead/submit_dtr.php, Api/Helpers/work_site_geofence.php, Html/TeamLead/team_lead_dashboard.html, Api/Accounting/get_submitted_dtrs.php, Api/Accounting/verify_dtr_submission.php, Api/Accounting/compute_payroll_for_batch.php, Html/Accounting/accounting_dashboard.html.

Validation: 13 holiday/payroll scenarios and 27 guard checks passed in the full disposable DTR suite. Eight focused overdue/reminder/submission checks passed. Nine focused pending-review submission → blocked verification/computation → resolved verification/computation checks passed. Browser checks passed at 1366px and 390px; overdue warnings do not disable selection and pending-review rows remain selectable. Production checksum comparisons remained unchanged; disposable databases were removed. PHP syntax checks passed. No real employee DTR was submitted automatically.

The new review submission policy supersedes earlier reports/tests that required resolving payroll review before DTR submission. Payroll review is still mandatory before verification/computation; no eligibility or pay formulas were changed. The tests use synthetic evidence to exercise both unresolved and resolved states, not live employee data corrections.
