# Employee attendance geofence investigation and repair — 2026-10-05

## 1. Root cause and evidence limits

The supplied employee reading and registered site are **5,466 m apart** using the actual frontend and PHP Haversine functions. Reported accuracy **129 m exceeds the registered 100 m radius**. Both explain rejection under the existing safety rule. Enabling Clock In would not resolve either problem.

The current database mapping is correct for the affected employee. Confirmed code defects were separate: Team Lead registration retained old coordinates and an enabled Save button during subsequent failed/pending GPS requests; registration did not reject unreliable accuracy; the Clock In/Out API parsed accuracy but never enforced the frontend's accuracy rule. The Team Lead UI also lacked an explicit company selector for a multi-company assignment, although its existing helper rejected ambiguous assignments. These defects are repaired. Their role in the original physical-location mismatch cannot be established retrospectively.

## 2–4. Coordinate provenance

SM Uptown's `clientcompany` row currently contains **8.48000000, 124.65000000**, radius **100**, `site_registered_at = 2026-10-05 16:09:38`, and `registered_by_tl_id = 56`. Both registered and legacy coordinate pairs match. The UI formats the stored values to six decimal places; no extra precision is hidden in this particular database record.

No hardcoded fallback for these coordinates was found in the scanned application code. They are actual persisted current registration data, not coordinates selected from another company's row. Whether their original capture was genuine GPS, manual/test input, or an inaccurate reading is **NOT VERIFIED**: the schema stores neither capture accuracy nor historical capture provenance. Trailing zeros alone do not establish that they are placeholders. No replacement coordinates were invented or saved.

Team Lead source: Register Location → browser `navigator.geolocation.getCurrentPosition()` → preview → explicit confirmation → `Api/TeamLead/register_work_site.php` → selected authorized `clientcompany` → `Api/TeamLead/get_work_site.php` → UI.

Employee source: independent browser geolocation (`getCurrentPosition` and `watchPosition`). Refresh requests `maximumAge: 0`, prefers high accuracy, clears previous coordinates/watch, updates actual accuracy, recalculates, and restores the button. Its existing standard-accuracy fallback also requests `maximumAge: 0`; it cannot bypass the quality rule. The browser's coordinates do not disclose whether its provider used satellite GPS, Wi-Fi, or network positioning. The quoted employee reading is user-provided evidence, not a fresh reading obtained in this run.

## 5–6. Mapping and authoritative record

Verified from the actual database and production resolver:

`Employee user 57 → active employee 3 (EMP2026000003, Tom Cruize) → active deployment 3 → CompanyID 4 (SM Uptown) → SiteSuperVisorID 56 (Sherrish Mae) → clientcompany.CompanyID 4 registered coordinates`.

Deployment 3 starts 2026-10-01, has no EndDate, and StatusID 5 resolves to `Active`. Its location is the recorded SM Uptown address on Fr. Masterson Avenue / Gran Via Street. User/employee/company active flags are checked. Resolution now explicitly excludes non-Active deployment statuses in addition to future/ended dates. Existing latest StartDate then DeploymentID ordering remains deterministic. This employee has exactly one date-current deployment in the inspected database.

The current schema has one coordinate pair per client company, not a separate geofence row per physical deployment address. CompanyID is a primary key, so there are no duplicate company geofence rows for SM Uptown. A company with several distinct physical sites would require a separately scoped design; this repair does not invent that relationship or alter the schema.

## 7–10. Distance, accuracy, client and server

- Distance: existing Haversine, Earth radius 6,371,000 m, latitude and longitude in correct order. PHP rounds to two decimals; the browser displays rounded meters. The screenshot pair agrees at 5,466 m.
- Accuracy: finite, nonnegative reported accuracy must be **≤ the authoritative radius**. This reuses the Employee dashboard's existing radius-based rule. Missing, invalid, negative, infinite, and wider-than-radius readings fail closed. No new arbitrary threshold was invented.
- Client: preserves Inside Geofence, Outside Geofence, Accuracy Too Low, and unavailable states. Poor accuracy has warning styling and the requested retry guidance. Lost GPS disables attendance; failed site retrieval cannot authorize against retained old site coordinates. Each Clock In/Out click obtains fresh GPS/site data before the selfie step.
- Server: Employee work-site retrieval and Clock In/Out both use `work_site_resolve_for_user()` for the authenticated user. Clock API recomputes distance against database coordinates/radius and rejects poor accuracy before saving a photo or attendance. Frontend site coordinates, radius, EmployeeID, and TeamLeadID are not authority. Existing attendance/DTR/payroll calculations were not changed.

## 11–12. Registration and refresh changes

Team Lead registration identifies the assigned official work site, previews fresh coordinates and accuracy, offers Retry GPS, and disables Save while pending/invalid/unreliable. A new attempt clears old coordinates immediately; stale callbacks cannot overwrite newer attempts, and closing the modal invalidates pending readings. The server independently requires authenticated Team Lead access, authorizes the selected company, validates coordinates/radius/accuracy, and uses the existing fixed company registration fields. Saved coordinates remain fixed when the Team Lead subsequently moves.

Employee Refresh GPS retains its existing button/loading/retry behavior. Unknown/negative accuracy is no longer coerced into an apparently accurate reading. Site-fetch failure and GPS-watch failure fail closed. The pre-selfie Clock In/Out step also requests a fresh reading. No automatic registration or employee-coordinate substitution was added.

## 13–14. Exact files and database changes

Modified application files:

- `Api/Helpers/work_site_geofence.php`
- `Api/TeamLead/get_work_site.php`
- `Api/TeamLead/register_work_site.php`
- `Api/Employee/clock_in_out_geofence.php`
- `Js/Teamlead/tl_attendance_sync.js`
- `Js/Employee/employee_attendance_sync.js`
- `Html/TeamLead/team_lead_dashboard.html` (geofence card/modal only)

Updated the related deployment fixtures in `Testing/EmployeeDashboard/server_test.php` to explicitly use Active status; its unrelated holiday checks were preserved. That legacy combined suite was syntax checked, not executed.

Created `geofence_test.php`, `geofence_registration_test.cjs`, `geofence_ui_test.cjs`, this report, and `geofence_registration_preview.png` under `Testing/EmployeeDashboard`.

**No persistent database records or schema were changed by this work.** Existing registration-time schema ALTER attempts were removed; the inspected schema already contains the needed columns. New resolver tests use connection-local temporary fixture tables, automatically discarded when the connection closes, and read actual application records. SM Uptown's coordinates, radius, registrant, and timestamp were queried again and remain unchanged. No real attendance, payroll, or photo submission was executed.

## 15–16. Tests and acceptance results

Automated checks: PHP syntax checks pass for all changed PHP files; both changed JavaScript files pass Node syntax checking. `geofence_test.php` passes **22** database/helper/security checks. The existing `manualgps_test.js` passes **10** mocked refresh checks. Registration and UI test scripts pass their fresh-capture, failure, stale-callback, payload-scoping, accuracy, distance, and decision assertions. Synthetic positions are explicitly test inputs, not evidence of physical presence.

Authenticated Team Lead browser: Sherrish Mae's page successfully retrieves SM Uptown, its persisted coordinates, and 100 m radius through the actual API. Register Location opens the revised preview with SM Uptown identified. The real geolocation request timed out; Save stayed disabled and Retry GPS started a fresh request. Captured browser error logs were empty. Nothing was saved. Anonymous work-site GET, registration POST, and attendance POST requests each returned 401 JSON.

Required tests:

1. **PASS — Deployment:** actual employee 3 resolves to active deployment 3; temporary future, Completed, and ended deployments are excluded.
2. **PASS — Team Lead:** actual deployment resolves to Sherrish Mae / user 56.
3. **PASS — Site:** actual deployment resolves to SM Uptown / company 4.
4. **PASS — Site coordinates at helper/data layer:** Employee API's shared resolver returns the same authoritative coordinates observed in the authenticated Team Lead UI. **NOT VERIFIED** as an authenticated Employee HTTP/browser response in this run.
5. **PASS — Fresh GPS request behavior:** mocked Employee refresh checks high accuracy and maximumAge 0, clears stale state, and restores the button. **NOT VERIFIED** for a successful physical Employee device reading in this run.
6. **NOT VERIFIED — Physically inside:** synthetic inside-position client/helper checks pass; actual physical presence was not established.
7. **NOT VERIFIED — Physically outside:** screenshot-coordinate and synthetic outside-position checks pass; actual device presence was not established.
8. **PASS — Poor accuracy validation:** client tests and shared server rule reject 129 m at radius 100, as well as missing/negative accuracy. **NOT VERIFIED** as a submitted authenticated Employee attendance HTTP request.
9. **NOT VERIFIED — Successful Clock In:** no real attendance was submitted.
10. **NOT VERIFIED — Successful Clock Out:** same authoritative distance and quality checks apply in code; no real attendance was submitted.
11. **PASS — Wrong deployment/company:** actual other employee retains its own company; fixture invalid deployments and unassigned Lead company selections are rejected.
12. **PASS — Server authority in helper/static tests:** server resolves site from the authenticated user and database; browser radius/site inputs are not used. **NOT VERIFIED** for an authenticated manipulated attendance HTTP submission.

Main same-physical-location acceptance test A–D: **NOT VERIFIED**. Real Team Lead GPS acquisition did not succeed, the stored site was not overwritten, and no paired Employee device at the site was tested. Do not describe the physical geofence problem as fully resolved merely because the code tests pass.

## 17. Remaining limitations and next physical test

The current coordinate mismatch remains until reliable readings establish the official physical site's location. An authorized Team Lead physically at SM Uptown should retry registration, review coordinates/accuracy at the existing 100 m radius, and confirm only the correct site reading. Then the assigned employee should refresh GPS at the same site and test Clock In/Out with the required selfie when accuracy satisfies the rule. If a device keeps returning poor or misplaced readings, application code cannot invent a reliable GPS fix. Network/Wi-Fi/device-provider attribution and the historical registration's true physical location remain unverified.
