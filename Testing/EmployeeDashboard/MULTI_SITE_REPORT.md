# Official Work Site management verification

## Subsequent authenticated browser check

The user signed in normally as Adele G. Tripon in the Codex in-app browser. Opening Work Site Geofence successfully displayed Ayala Malls, its real address, Location Not Registered, Not Set radius display and enabled Register Location. The registration modal showed the selected Ayala Malls site and configured 100-meter radius. No browser warning/error logs were captured during this check. Authenticated site loading and registration-modal display are now **PASS**; physical GPS capture remains **NOT VERIFIED** because the actual browser request timed out after 15 seconds. The timeout message appeared and Save Work Site Coordinates stayed disabled. No coordinates were submitted or saved. Multi-site switching, authenticated registration POST and actual attendance writes remain unverified.

2026-10-05, connected Employee_Tracking project / local XAMPP MariaDB.

## Model before and after

Before this change, an official site was a `clientcompany` record identified by `CompanyID`, not a global Team Lead GPS value. `clientcompany` stores `registered_latitude`, `registered_longitude`, legacy `latitude`/`longitude`, `geofence_radius`, registration time and registering Lead. This existing model is retained.

`deployment.CompanyID` links employee deployments to that authoritative geofence. `deployment.SiteSuperVisorID` links deployments to a Team Lead/user. A company may also be directly assigned through `clientcompany.UserID`. There is no separate WorkSite table in the inspected database. Deployment Location and Job Order placement text provide labels, not a separate geofence-bearing ID. Current active deployment data has one distinct location per company. Multiple physical locations under the same CompanyID are not independently representable; this implementation does not invent an identity from address text.

The schema permits one Team Lead to supervise multiple companies. The existing resolver already supports this through an EXISTS query, without duplicating a company for every employee. Current live deployment supervisors each have one active company; multi-company behavior was verified with temporary data. No migration or architecture replacement was required.

## Authorized site resolution

`work_site_companies_for_team_lead()` verifies an active TeamLead/Team Lead user and active companies, then accepts direct company assignment or an active deployment whose start/end dates cover today. It returns one row per CompanyID, using EXISTS for deployments. Future, ended and non-Active deployments do not independently authorize a site. Labels and address come from actual clientcompany data.

`get_work_site.php` returns these authorized companies. Multiple companies require explicit selection; one company may be selected automatically. No assigned companies now returns a successful empty state rather than an exception. A requested positive CompanyID is checked against the same authorized set.

## 400 investigation and limitations of the screenshot

The screenshot identifies Adele G. Tripon and a truncated work-site endpoint URL ending in `...ter_work_site.php`, with status 400. It does not show the full request URL, method, parameters or server response body; those exact historical details are **NOT VERIFIED**. The endpoint suffix appears consistent with register_work_site.php, but that is an inference, not a captured request.

Current frontend contracts were traced:

- Initial loading: GET `/Employee_Tracking/Api/TeamLead/get_work_site.php`, no parameters.
- Selected loading: GET the same endpoint with `company_id=<selected CompanyID>`.
- Registration: POST `/Employee_Tracking/Api/TeamLead/register_work_site.php`, JSON containing company_id, latitude, longitude, accuracy and geofence_radius.

The current registration API returns 400 for invalid JSON, invalid coordinates, invalid radius, or missing/invalid company selection; it returns 403 for unauthorized sites and 422 for insufficient accuracy. The loading API returns 400 for malformed/negative selection. These errors are retained, not suppressed. We cannot identify which validation produced the earlier screenshot without its request/response.

A controlled CLI invocation of the actual loading endpoint with an in-memory session fixture for actual user 112 (Adele, active TeamLead), GET and empty query returned **200**, company 29 (Ayala Malls), success=true, site_registered=false, latitude/longitude=null and configured radius 100. No authenticated browser session was manufactured or modified. This confirms the current read path works for that stored assignment, not that the historical browser error was reproduced.

## Changes and UI behavior

- Authorized-company lookup also returns address and the existing geofence fields, allowing badge calculations to use the identical site set.
- The page shows the selected site's real address, provides Refresh sites, and disables registration until an authorized site has loaded.
- Loading, no assigned sites, selection needed, unregistered site and failed loading have explicit states. Errors display the server message instead of leaving the select stuck at Loading assigned sites.
- Registered sites show their actual coordinates/radius and Update Location; unregistered sites show Location Not Registered / Not Set / Register Location. Initial markup no longer claims Active Site before data loads.
- A request sequence counter prevents late site responses from overwriting a newer selection, including duplicated loading calls.
- Existing GPS modal provides site name, fresh coordinates, accuracy, configured/editable radius, Retry GPS and Save Work Site Coordinates. The selected site's existing radius is loaded; it is not increased automatically. Capture retains enableHighAccuracy=true, maximumAge=0 and a 15-second timeout. Poor accuracy remains blocked.
- The site select is locked while a save is pending. Server authorization independently validates selected CompanyID. The registration UPDATE continues to target only that CompanyID; it does not write a global Lead coordinate or update every company.
- The geofence warning now counts every authorized current site lacking a complete valid registered or same-site legacy coordinate pair. It appears if any site is missing and clears only when all authorized sites are registered. Partial coordinate pairs do not count as registration.

GPS accuracy is captured and used for validation/preview; no registration-accuracy column exists, so no persisted accuracy value is claimed.

## Employee attendance mapping

`Api/Employee/get_employee_work_site.php` and `Api/Employee/clock_in_out_geofence.php` already use `work_site_resolve_for_user()` / `geofence_validate_employee_position()`. They independently resolve authenticated Employee → active employee record → current Active deployment, ordered by start date and DeploymentID → that deployment's active CompanyID → that company's geofence.

Team Lead dropdown selection is a frontend management variable only. It is not used by employee geofence queries. Missing coordinates do not fall back to another Lead/company. The existing missing-registration message is distinct from outside-geofence validation. No Employee attendance calculation or clock record was modified in this work.

## Exact files modified

- `Api/Helpers/work_site_geofence.php` — additional authorized-site address/geofence fields only; employee resolution preserved.
- `Api/TeamLead/get_work_site.php` — no-assignment empty state, address and registration flag.
- `Api/TeamLead/team_lead_counters.php` — warning based on any missing authorized current site's coordinate pair; unrelated badge logic preserved.
- `Js/Teamlead/tl_attendance_sync.js` — site loading states, sequence guard, selected-site display/radius, save-time selection lock.
- `Html/TeamLead/team_lead_dashboard.html` — directly related address, refresh, registration button and initial status markup.

Created: this report. Database migrations: **none**. Live coordinates/assignments/attendance records: **unchanged**. Applicant, Job Order hierarchy, payroll, holiday, Team Schedule, DTR, messaging and session logic were not changed.

## Required test results

1. API 400 — **PASS controlled current loading endpoint (200); historical request and authenticated browser NOT VERIFIED**. Exact historical method/body/response unavailable.
2. Authorized sites — **PASS temporary-database resolver**: all authorized companies returned; future/ended-only company excluded. Actual Adele assignment retrieved successfully.
3. Deduplication — **PASS temporary database**: duplicate employee deployment rows produced one entry per CompanyID.
4. Site A registration — **PASS isolated storage/helper check; real GPS POST NOT VERIFIED**. A's coordinate pair stored only on A's temporary company row.
5. Site B registration — **PASS isolated storage/helper check; real GPS POST NOT VERIFIED**. Registering B left A unchanged.
6. Switching — **PASS controller checks**: site-specific display/address, unregistered state and stale-response guard. Full visual A→B→A browser acceptance NOT VERIFIED.
7. Employee A — **PASS production resolver and geofence validator with temporary records**: A resolves A, not B.
8. Employee B — **PASS production resolver and geofence validator with temporary records**: B resolves B independently.
9. Unregistered site — **PASS**: B did not inherit registered A coordinates; backend missing-registration message and frontend unregistered state checked.
10. Security — **PASS production authorization helper** rejects unauthorized company. Full manipulated authenticated POST NOT VERIFIED; registration uses that helper before UPDATE.
11. GPS registration — **PASS actual JS controller with mocked device**: fresh capture options, invalid/poor accuracy, retry clearing stale position, selected CompanyID/radius payload. Physical device GPS NOT VERIFIED.
12. Update location — **PASS isolated company-row update**: updating B leaves A unchanged; full authenticated registration POST NOT VERIFIED.
13. Warning badge — **PASS production resolver/coordinate-picker logic**: all registered means zero missing; one unregistered site means warning. Full visual badge polling NOT VERIFIED.
14. Clock In — **PASS production geofence validation at synthetic assigned-site origins and rejection at another site; physical GPS and actual attendance write NOT VERIFIED**.

Additional tests: 13 temporary-database assertions passed without live business writes. The actual loader controller passed registered display/address, unregistered state, empty state, stale-response protection and surfaced error tests. Existing geofence_registration_test.cjs and geofence_ui_test.cjs passed. Changed PHP files and dashboard/shared attendance JS syntax passed. Tests intentionally did not register fake coordinates or clock anyone in/out in live data.

## Remaining limitations

- No physical-location acceptance test or signed-in browser runtime test was completed.
- The exact historical 400 response cannot be reconstructed from the screenshot alone; current loading endpoint succeeds for the shown assignment.
- Separate physical campuses within one CompanyID still share the existing company geofence; the inspected active data does not establish such a multi-campus requirement, and no guessed site table/IDs were introduced.
- The second screenshot shows a captcha validation issue even though 89+50=139. It is outside the attached work-site request's strict scope, which excludes unrelated authentication/session changes, and was not modified.
