# Attendance Testing multi-site selection fix — October 6, 2026

1. **Root cause:** Testing inferred site management from employee deployment rows. Production assigns site management independently, so an assigned site with no employees incorrectly looked unassigned.

2. **Old Team Lead query:** `SELECT DISTINCT u.UsersID,u.FullName FROM deployment d JOIN users u ON u.UsersID=d.SiteSuperVisorID WHERE d.CompanyID=? AND (d.EndDate IS NULL OR d.EndDate>=CURDATE())`. It did not read site assignments, did not require active deployment status/start date, and could not distinguish branches.

3. **Downtown failure:** Current database site 6 belongs to SM Downtown and is assigned to user 56, Sherrish Mae. There are no current employee deployments at that site. The old query therefore returned no Team Lead despite a genuine site assignment. The contact-person label was not used as authorization evidence.

4. **Production source of truth:** Admin client/site management persists `deployment_sites.CompanyID`, `TeamLeadID` and `IsActive`. Site `TeamLeadID` references a users account; its RoleID resolves through roles. `deployment.SiteID` ties employees to the site, with CompanyID checked for consistency. Production Team Lead deployment roster, schedule, geofence and DTR paths reuse site authority in `Api/Helpers/work_site_geofence.php`.

5. **Site selector needed:** Yes. Employee deployments and Team Lead authority are site based; company alone cannot distinguish branches.

6. **New flow:** Client Company → Deployment Site → assigned Team Lead → current Employee. Changing company/site/lead clears dependent selections and record panels. Cascade versions discard stale dropdown responses. No employee selection is required for the separate isolated holiday suite.

7. **Team Lead relationship:** `deployment_sites.TeamLeadID`, validated through production `site_for_lead()`/`sites_for_lead()`. The company/site must be active, the user active, and the actual role name TeamLead or Team Lead. No hardcoded person/company/role IDs are used in selector code.

8. **Employee relationship:** `site_owned_employee()` validates selected employee/deployment/site/lead authority. Lists use `site_current_deployment_sql()` and employee.IsActive=1, scoped to selected SiteID and CompanyID. Legacy deployment.SiteSuperVisorID is not substituted for site authority. Isolated fixtures deliberately give a branch employee a different legacy supervisor to verify this distinction.

9. **Active handling:** Production predicate requires named Active status, StartDate≤today, null EndDate or EndDate≥today, and the most recent qualifying active deployment by StartDate/DeploymentID. Site/company/user/employee activation is preserved. The schema has no separate dated Team Lead site-assignment table; authority resides on the active site row.

10. **Payroll Type:** Read from the selected site's client company; shown with deployment context. Existing employees retain their configured rates. Live Uptown verification returned Type B and ₱700/day, ₱87.50/hour for selected employee 77; these values are evidence, not constants in implementation.

11. **Cutoff:** Existing `payroll_type_period_for_company()` determines the cutoff for the selected test date. October 6 resolves to September 26–October 10, payday October 15, Type B. Both holiday lookup and payroll verification returned that period. No second cutoff formula was introduced.

12. **Test 1, SM Downtown — PASS:** Browser company/site selection showed Sherrish Mae, then accurately reported “No active employees at this site.” It did not invent employees or assignments.

13. **Test 2, SM Uptown — PASS:** Browser showed Sherrish Mae and three current employees: Jhonel Tuling, Merry Joy Ucat and Tom Cruize. Selecting Jhonel loaded schedule, Site 4, Deployment 47 and Type B.

14. **Same lead/multiple sites — PASS:** Actual sites 4 and 6 share lead 56. Both dropdowns were verified. Isolated fixture Test 5 also verified one company with two branches sharing a lead.

15. **Test 4, site isolation — PASS:** Cross-company/site request rejected with HTTP 422. Isolated fixture branches return distinct employees. Switching company cleared old site/lead/employee selections in the browser.

16. **Test 3, employee isolation — PASS:** Uptown list matches current active deployments. Wrong employee/site and wrong deployment requests return HTTP 422. Test 6 different branch/lead also PASS; wrong lead request returns 422.

17. **Test 9, holiday regression — PASS:** Selected live employee context reached read-only holiday/payroll endpoints with real rate/type/cutoff. The isolated A–K suite plus eligibility boundary L all PASS after context enforcement, using synthetic context IDs on its writes. No fake holiday was added to the live calendar.

18. **Test 10, DTR regression — PASS in isolated database:** Actual safe attendance writes, Team Lead site cutoff review/submission, Accounting verification/finalization and employee payroll API completed for all twelve cases. No attendance was generated for a real employee during verification. A real-user Team Lead browser review after a new live attendance write is NOT VERIFIED, intentionally avoided to preserve real records.

19. **Exact implementation files:** `Testing/Attendance/attendance_testing.html`, `attendance_testing.js`, `README.md`; `Api/get_teamleads.php`, `get_employees.php`, `get_employee_attendance_list.php`, `get_holiday_for_date.php`, `get_payroll_verification.php`, `save_attendance.php`, `save_test_overtime.php`, `delete_attendance.php`; new `Api/get_sites.php` and `Api/_selection_context.php`; `holiday_e2e_runner.php` adds selected context and isolated branch regressions. All paths here are relative to `Testing/Attendance/`. Production files/formulas/UI were not modified in this task.

20. **Database changes:** No production assignments, schema or attendance changes. Isolated synthetic branch/site/user/employee/deployment fixtures were added to the existing disposable holiday runner and automatically removed when its random database was dropped. Production attendance/payroll/holiday checksums remained identical. Browser verification used an independent temporary Admin session, without replacing a real user's login. Temporary fixture code/logs remain for diagnosis.

21. **Tests 1–10:** 1 Downtown PASS; 2 Uptown PASS; 3 employee list PASS; 4 site isolation PASS; 5 same-company multi-branch PASS (isolated); 6 different branch leads PASS (isolated); 7 inactive site assignment PASS (isolated); 8 ended/future deployments and inactive employees PASS (isolated); 9 holiday context/suite PASS; 10 production DTR pipeline PASS (isolated). Unauthenticated endpoint requests return 403. PHP/JavaScript syntax and existing payroll response tests PASS. Evidence: `multisite_access_checks.json`, `multisite_context_verification.json`, `multisite_holiday_regression.json`, `multisite_downtown_verified.jpg`, `multisite_uptown_verified.jpg`.

22. **Limitations:** Downtown needs a genuine active employee deployment before attendance can be generated there. This selector intentionally supports current deployments, not an independent historical mode. Dates outside the selected deployment, or dates whose production schedule resolver selects a different deployment, are rejected. Existing production payroll behavior across transfers within a cutoff was not redesigned or separately certified. Inactive-deployment status filtering is inherited from the shared production predicate; fixture Test 8 specifically exercised ended/future deployments and employee inactivity. The isolated holiday suite keeps its explicit synthetic Type A fixtures; real selections use actual Type A/B metadata. This remains an Admin-only development tool.
