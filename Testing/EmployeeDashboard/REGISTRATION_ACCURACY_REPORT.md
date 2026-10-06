# Work-site registration accuracy policy

## Latest adjustment: default registration limit 100 m

Following the user's request for usable registration with the actual device reading, the default and invalid-configuration fallback were changed from 50 m to 100 m in `work_site_registration_policy.php`. The earlier 50 m results below are historical verification of the preceding implementation. The displayed ±97 m reading is now permitted by the default server policy, without modifying its latitude, longitude, accuracy or the employee geofence radius. A valid reading above 100 m remains blocked. PHP checks passed for 97/100 accepted, 101/50,000 rejected, null/negative rejected, and unchanged employee radius validation. Syntax passed. No live coordinates were saved; the current device reading must still be captured and confirmed at the intended site. This enables registration of an approximate browser measurement and does not claim mathematically exact physical location.

Implemented and verified 2026-10-05 in the connected Employee_Tracking project.

## Cause and policy

The ±105 m value is supplied by the browser/device location provider, not calculated from the client address or geofence radius. Its precise hardware/provider cause cannot be established from the screenshot. The application defect was comparing registration accuracy to the employee geofence radius, conflating uncertainty of the registered center with an employee's permitted distance from that center.

Registration now has a separate default maximum uncertainty of **50 meters**, a conservative application default, not a claim of exact GPS or a legal standard. It is centrally defined by `work_site_registration_policy()` in `Api/Helpers/work_site_registration_policy.php`. The server environment variable `WORK_SITE_REGISTRATION_MAX_ACCURACY_METERS` can configure a positive finite value up to 1,000 m; invalid configuration uses the 50 m default. No environment variable was changed during implementation. Changing this policy does not change stored geofence radii. The browser receives the policy from the existing authenticated `get_work_site.php`; no JavaScript copy of the default threshold exists. Missing policy disables registration.

**A ±105 m reading still fails the default registration policy**, whether the chosen geofence radius is 30, 100, 150 or 1,000 m. It fails because of the independent 50 m registration requirement, not because it exceeds a 100 m radius. The real uncertainty is displayed and never changed to pretend it is more precise. A business decision to allow less precise registration can configure the server policy independently; this work did not silently accept coarse locations.

Employee attendance retains its existing frontend/backend policy: measured accuracy must be finite/nonnegative and fit within the employee's assigned site's radius. The shared attendance validator was not changed. Registration no longer calls that radius-based accuracy function.

## Flow and acquisition

Before the latest changes, registration used a fresh one-shot getCurrentPosition request, then a 30-second watchPosition improvement window, but both registration and attendance still used the radius as the accuracy limit.

The production flow now is: authenticated Team Lead → authorized CompanyID from get_work_site → open site-specific modal → load the server-defined registration policy → fresh high-accuracy watchPosition with maximumAge=0 → retain actual best coordinates → apply separate registration policy → explicit Save → register_work_site API validates role, authorized CompanyID, coordinate ranges, radius and registration accuracy → update only that clientcompany row.

- High-accuracy acquisition lasts up to the centrally defined 30 seconds, stopping sooner when the best reading meets the policy.
- Current coordinates/current accuracy and best coordinates/best accuracy are distinct. A worse later reading never replaces the best candidate.
- The countdown updates while watch acquisition is active, then disappears when stopped. The fallback one-shot path does not pretend an active watch is running.
- Retry stops the old watch/timers, clears candidates and requests a fresh reading. Request IDs ignore callbacks from obsolete attempts.
- Modal close or site selection stops the watch and both timers, clears temporary candidates and disables saving.
- Permission denied, unavailable location and timeout have distinct messages. Timeout retains the best displayed approximate reading for explanation but cannot enable saving if it fails policy.
- Save is disabled while acquisition is active, when policy is unavailable, on failed acquisition, on poor accuracy and during saving. The server independently enforces the registration limit, so browser manipulation cannot bypass it.
- Actual latitude/longitude/accuracy are transmitted; no address geocoding, rounding toward a site, subtraction of error, fabricated accuracy or coordinate substitution occurs. Display formatting uses six decimals only; persistence receives the actual numeric measurement.

Existing radius limits (30–1,000 m), selected-site authorization and company-specific storage remain unchanged. No automatic radius increase occurs. Latitude/longitude database precision remains DECIMAL(10,8)/DECIMAL(11,8). Accuracy is validated but no new accuracy storage column was added.

## Employee integration and distance

Employee work-site retrieval and Clock In/Out validation continue to use `work_site_resolve_for_user()` / `geofence_validate_employee_position()`: authenticated employee → current active deployment → CompanyID → that company's geofence. The Team Lead's most recently selected site is not consulted.

The existing Haversine helper accepts latitude then longitude, converts degree differences to radians and uses an Earth radius of 6,371,000 meters. A controlled calculation from (8.48,124.65) to (8.43,124.65) produced **5,559.746 m**, within 0.01 m of the expected calculation. These points are several kilometers apart, not interchangeable. Existing frontend inside/outside/poor-accuracy tests passed. No attendance calculations were altered.

## Exact files

Created:

- `Api/Helpers/work_site_registration_policy.php`
- `Testing/EmployeeDashboard/REGISTRATION_ACCURACY_REPORT.md`

Modified:

- `Api/TeamLead/get_work_site.php` — returns centralized registration policy with existing responses.
- `Api/TeamLead/register_work_site.php` — separate server registration accuracy validation and clear rejection message.
- `Js/Teamlead/tl_attendance_sync.js` — consumes policy, distinct current/best readings, countdown, cleanup and independent acceptance.
- `Html/TeamLead/team_lead_dashboard.html` — additional text fields inside the existing registration modal.
- `Testing/EmployeeDashboard/geofence_registration_test.cjs` — supplies server-policy fixture and checks updated policy message.

Database migrations: none. Live coordinates and attendance records were not written. Payroll, holiday, Job Order, applicant, Team Schedule, DTR, messaging and session implementations were preserved.

## Required results

1. Initial location — **PASS**: fresh high-accuracy settings verified in controller tests; authenticated Adele/Ayala modal opened and displayed acquisition/countdown.
2. Multiple readings — **PASS controller**: ±180/105/45/60 retained ±45 and displayed current ±60 separately. Synthetic test only.
3. Acceptable reading — **PASS controller/server policy**: 45/50 accepted, 51 rejected; Save enabled after acceptable acquisition stops.
4. Poor reading — **PASS controller/server policy**: ±105 remains unchanged and blocked, best reading retained with guidance after deadline. No false precision.
5. Retry — **PASS controller**: old watcher stopped, candidate reset and stale callbacks ignored.
6. Cache — **PASS controller configuration**: maximumAge=0 and high accuracy enabled; actual device cache internals cannot be inspected.
7. Site A — **PASS unchanged source-of-truth/static mapping and earlier isolated company persistence tests; real registration POST NOT VERIFIED** in this task.
8. Site B — **PASS unchanged isolated mapping evidence**: previous temporary-table tests verified B update preserves A; no live GPS save performed.
9. Unauthorized site — **PASS existing production helper isolation tests/static API invocation order**; authenticated manipulated registration POST NOT VERIFIED.
10. Employee — **PASS existing frontend/production resolver validation evidence; physical Clock In and actual attendance write NOT VERIFIED**.
11. Outside geofence — **PASS existing geofence_ui_test.cjs**: known distant point rejected and attendance buttons disabled. Physical outside-site test NOT VERIFIED.
12. Distance — **PASS PHP calculation**: 5,559.746 m for the specified coordinates, correct meter units and coordinate order.
13. Mobile — **NOT VERIFIED on a physical phone**. Existing responsive modal and same production endpoint preserved; no separate mobile database flow.
14. Modal close — **PASS controller watch cleanup and authenticated browser close**. DOM hidden event stops acquisition, invalidates callbacks and clears candidates.
15. Console/network — **PASS syntax and authenticated read/modal flow**, no related browser warnings/errors captured during this check. Full valid registration POST/network transaction NOT VERIFIED.

Additional tests: registration policy rejects null/negative/infinite accuracy; configurable 120 m limit accepts actual 105 m independently of radius in an isolated process, then environment override is removed; unchanged employee accuracy rule rejects 105 at radius 100 and accepts it at radius 150. Existing geofence_registration_test.cjs and geofence_ui_test.cjs passed. Actual acquisition controller passed policy independence when radius changed to 1,000, countdown, deadline, retry, permission and watcher-stop assertions. Changed PHP and JavaScript syntax checks passed.

## Browser observation and limitations

The existing normally authenticated Adele G. Tripon session was reused. Ayala Malls loaded with its actual address/unregistered state. The modal displayed the server's ±50 m registration policy, configured 100 m business radius, 30-second countdown and disabled Save. It was closed without saving. No permission bypass or synthetic position was injected into the live browser.

Actual precise GPS capture, physical presence at the site, a phone registration and a successful authenticated registration write remain **NOT VERIFIED**. Software cannot guarantee a laptop's location provider will improve ±105 m to precise phone-quality GPS. Users should enable device location services and browser location permission, retry at the official site, or use a GPS-capable device. No uncertain reading is described as exact.
