# Controlled holiday runner HTTP 500 fix — October 6, 2026

1. **Exact root cause:** The launcher used `PHP_BINARY` as its CLI interpreter. In XAMPP's `apache2handler` runtime this is `C:\xampp\apache\bin\httpd.exe`. It therefore started Apache with a PHP script argument instead of executing the script. Earlier tests under PHP's built-in development server did not reveal this because there PHP_BINARY is php.exe.

2. **Original server error:** The runner stderr log contains `[mpm_winnt:crit] (OS 109)The pipe has been ended. : AH02965: Child: Unable to retrieve my generation from the parent`, at 15:27:54 and 15:28:36. This is an Apache child-process error, not a payroll PHP exception. The original endpoint then failed JSON decoding and returned its generic 500 message.

3. **Original location:** `Testing/Attendance/Api/run_holiday_scenarios.php`, original line 13, `proc_open([PHP_BINARY,...])`; original line 17 produced the generic HTTP 500. A temporary local runtime diagnostic confirmed Apache SAPI, httpd.exe binary and compiled PHP_BINDIR `C:\php` (which is not the installed XAMPP CLI directory).

4. **URL:** Correct. Relative `Api/run_holiday_scenarios.php` resolves beneath `Testing/Attendance/`; PHP was reached. No fetch-directory change was needed.

5. **Database:** Correct. The runner uses a new random `codex_holiday_<12 hex>` schema, cloned structure without production personal rows. Current credentials successfully create/use/drop that schema. MySQL source and PDO isolation assertions remain in place.

6. **Schema:** No schema mismatch caused the original failure; the runner was never executed by the wrong executable. Both successful Apache runs exercised current fixture schema without schema changes.

7. **Multi-site compatibility:** Existing fixtures provide CompanyID, SiteID, deployment_sites.TeamLeadID, employee DeploymentID and the selector context required by Testing APIs. Branch/isolation checks also pass. No site validation was weakened.

8. **Helper signatures:** No production helper signature change caused this error. Current Attendance, DTR, Accounting, payroll and payslip calls executed successfully after launch repair. No production helpers or formulas were modified in this task.

9. **Files changed:** `Testing/Attendance/Api/run_holiday_scenarios.php`; `Testing/Attendance/holiday_e2e_runner.php`; `Testing/Attendance/attendance_testing.js`. This report and evidence files were added. Temporary `LaunchVerification` files and the runtime diagnostic were removed after testing.

10. **Launcher/fixture changes:** CLI candidates come from the actual PHP configuration directory and PHP_BINDIR; PHP_BINARY is accepted only under cli/cli-server. Candidates must exist and be named php.exe/php; the browser cannot supply an executable. XAMPP resolves to `C:\xampp\php\php.exe`. Exit code and JSON decoding errors are reported/logged. Fixture HTTP read warnings now become a RuntimeException and diagnostics go to stderr, preserving JSON stdout. The CLI-only `--verify-failure-cleanup` switch deliberately throws after creating synthetic records to verify finally cleanup; the HTTP endpoint passes no flags or user overrides.

11. **Frontend errors:** The controlled button reads the HTTP response as text, validates JSON, then displays HTTP status plus the returned message/stage on failure. HTML/warning-corrupted responses report invalid JSON explicitly. The button is re-enabled after either outcome. Global PHP display_errors settings were not changed.

12. **Normal 8h:** ₱1,000 expected and actual — PASS.

13. **Regular holiday 8h:** ₱2,000 expected and actual — PASS.

14. **Special non-working holiday 8h:** ₱1,300 expected and actual — PASS.

15. **Full matrix:** A ₱1,000; B ₱2,000; C regular holiday plus 2h approved OT ₱2,650; D ₱1,300; E special holiday plus 2h approved OT ₱1,722.50; F regular/rest ₱2,600; G special/rest ₱1,500; H double regular ₱3,000; I eligible unworked regular ₱1,000; J ineligible unworked regular ₱0; K unworked special ₱0; L eligibility boundary ₱1,000. All twelve PASS with zero differences in both successful Apache runs.

16. **Cleanup evidence:** Successful runs report production checksums unchanged and disposable schema removed. The intentional exception test returns valid JSON with success=false, intentional_cleanup_test=true, production_unchanged=true and cleanup PASS. `SHOW DATABASES LIKE 'codex_holiday_%'` returned no remaining schemas after verification. No production attendance, assignments, holidays or payroll were written. Fixture code/logs in the system temporary directory remain for diagnosis.

17. **Required Tests 1–10:** 1 direct Apache POST PASS (HTTP 200, application/json); 2 Apache-served dashboard button PASS; 3 normal day PASS; 4 regular holiday PASS; 5 special holiday PASS; 6 regular holiday OT PASS; 7 special holiday OT PASS; 8 success/failure cleanup PASS; 9 repeated run PASS (direct then button); 10 isolated production Attendance→Team Lead DTR→Accounting→finalized payroll/payslip pipeline PASS, with identical production checksums. Real employee attendance writes and real user payroll UI changes were not performed.

18. **Limits and diagnostic findings:** The first corrected launch encountered an intermittent fixture HTTP failure at Accounting compute; its PHP warning had previously contaminated JSON stdout. The warning boundary was corrected and subsequent direct/button runs both passed. A future transport failure still fails honestly with stage/message and cleanup; no automatic retry of payroll writes was added. Apache verification used an independent temporary Admin session and an identical endpoint copy with a wrapper to the actual runner, avoiding changes to a real user's session. The original path and PHP runtime were separately confirmed. Original pre-fix HTTP failure was supplied by the user and corroborated by logs/runtime; no pre-fix browser screenshot was captured. Local CLI/database privileges remain prerequisites; existing fixed October 2026 fixture dates/current-cutoff payslip limitations remain unchanged.

Evidence: `holiday_apache_direct.json`, `holiday_apache_verified.jpg`, `holiday_failure_cleanup.json`. PHP and JavaScript syntax checks PASS.
