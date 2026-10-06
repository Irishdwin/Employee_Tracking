# Employee geofence integration report

1. Root causes found: GPS fallback relied on browser callbacks without a final application deadline; initialization awaited both site retrieval and GPS; site polling began only after successful GPS; callbacks retained DOM nodes replaced by calendar rendering. Dashboard profile deployment lookup allowed future/inactive rows and arbitrary ties. Work-site resolver silently chose one of multiple active deployments.
2. Actual SM Uptown storage: deployment_sites.registered_latitude=8.48000000, registered_longitude=124.65000000, geofence_radius=100.
3. Stable SiteID=4; CompanyID=4; managing TeamLead users ID=56. The saved site record owns the coordinates.
4. Current SM Uptown mappings found: EmployeeID=3/UserID=57/DeploymentID=3/SiteID=4; EmployeeID=77/UserID=113/DeploymentID=47/SiteID=4. Screenshot initials alone cannot establish which authenticated account was shown.
5. Both actual deployments resolve to the same saved site used by the Team Lead. No database remapping was necessary.
6. Previous work-site lookup already followed Employee email -> deployment.SiteID -> deployment_sites, but used latest-row LIMIT 1.
7. Fixed lookup rejects multiple current active deployments with controlled 409. It excludes future/ended/inactive deployment rows and never chooses another Team Lead site.
8. API retains work_site mapping and adds site_name and deployment_id beside site_id. Identity comes from the authenticated session; query parameters do not select another Employee.
9. Indefinite Acquiring GPS was possible when fallback callbacks never arrived or site fetch remained pending. The screenshot alone cannot establish browser/device permission status.
10. GPS has an 18-second application deadline; high-accuracy attempt and fresh standard fallback use maximumAge=0. Permission denial is terminal; invalid results and thrown browser errors settle. Site request has a 10-second abort timeout and runs independently. Loading flags and Refresh GPS button reset in finally. UI updates reacquire current DOM elements.
11. Existing JS calculateHaversine and PHP calculateHaversineDistance both use radius 6371000 meters with radians conversion. Comparison remains distance<=saved radius; existing GPS accuracy requirement is retained.
12. Clock In: session Employee -> authoritative deployment/site -> saved coordinates/radius -> PHP Haversine/accuracy validation -> existing selfie, schedule and attendance rules.
13. Clock Out uses the same authoritative geofence gate, preserving existing inside-site policy and overnight attendance rules.
14. Multi-site test resolves isolated Employee users 115/117 to SiteIDs 6/7 independently; other-branch coordinates are rejected.
15. Files modified: Js/Employee/employee_attendance_sync.js; Html/Employee/employee_dashboard.html; Api/Employee/get_employee_work_site.php; Api/Helpers/work_site_geofence.php; Api/Employee/dashboard_data.php; Api/Employee/clock_in_out_geofence.php. Files created: Testing/EmployeeGeofence/gps_state_test.cjs; site_resolution_test.php; this report.
16. No schema or production coordinate/deployment changes. Test mutations used an isolated database transaction and rolled back.
17. Tests: three Node VM browser-callback scenarios; seven isolated MariaDB/helper assertions; PHP and external/inline JavaScript syntax. These simulate browser callbacks and are not physical GPS evidence.
18. Requested test results:

- Saved geofence source and stable identity: PASS (actual database read).
- Employee matching: PASS for both mapped accounts; screenshot account identity NOT VERIFIED.
- Employee site API mapping: PASS by inspection/helper tests; authenticated browser/network NOT VERIFIED.
- Multi-site isolation: PASS.
- GPS success: PASS (simulated callback).
- Permission denial: PASS (simulated callback).
- GPS timeout/no callback: PASS (simulated callback).
- Refresh loading cleanup: PASS (simulated).
- Inside geofence: PASS (helper).
- Outside geofence: PASS (helper).
- Physical Clock In: NOT VERIFIED.
- Physical Clock Out: NOT VERIFIED.
- Unregistered site: PASS (helper).
- No current deployment: PASS (ended-deployment helper test).
- Site identity/security: PASS by server inspection and branch rejection; complete manipulated HTTP payload test NOT VERIFIED.
- Browser reload: NOT VERIFIED.
- Console/network: syntax PASS; actual browser console/network NOT VERIFIED.

Remaining limits: a physically present Employee device, location permission and adequate measured accuracy are required to verify real GPS/camera Clock In/Out. Saved center correctness relative to the official physical site cannot be established from screenshots or synthetic tests. No radius enlargement, sample Employee coordinates or GPS bypass was introduced.

## Authenticated browser follow-up

The user signed in the Employee account in Codex. The Timesheet panel displayed SM Uptown, registered, radius 100 meters. Clicking Refresh GPS entered acquiring state and finished with GPS Timeout; Refresh re-enabled and clock buttons stayed disabled. Captured error/warning console logs were empty. This verifies authenticated site loading and bounded GPS refresh, not physical location acquisition.

The user's Chrome screenshot independently showed accuracy ±118m and distance 5474m from the saved center, radius 100m. Both block attendance under existing rules. Fixed status priority to show Outside Geofence when outside even if accuracy is also poor, with separate accuracy guidance and instruction to verify the site's saved center if already physically there. Added and passed an outside-plus-low-accuracy UI test. No production site center/radius or attendance records were changed. Correct physical center cannot be inferred from the screenshot; user's confirmation of physical presence/saved-center correctness remains necessary.

## Same-device follow-up

The user confirmed both accounts used one device and believes their coordinates match. Added an expandable Location comparison showing the exact saved site pair and current Employee pair used by the distance calculation. Equal-pair tests return 0 meters; equal-pair accuracy 118 meters still blocks a 100-meter site under the existing accuracy policy. Added and passed that explicit UI test. Fixed Today's Shift label to refresh from loaded site metadata even when the profile request has not completed. Real Codex device GPS acquisition remains unresolved because it times out; no claim is made that both actual coordinate pairs match until a fresh device reading is available.

## Accuracy fluctuation after correct registration

User screenshots confirm the site is now near 8.431335,124.657015. Employee first reports distance 12m/accuracy 100m (accepted), then distance 1m/accuracy 107m (rejected by the existing radius-based accuracy rule). Site retrieval succeeds in both images; browser accuracy changed, not the saved center.

Changed Employee refresh to collect fresh high-accuracy watch samples within an 18-second deadline, keeping the best measured accuracy from that attempt and completing early on an acceptable reading. Low-quality first responses no longer immediately terminate collection. Permission denial remains terminal; the watch is cleaned up; no-sample timeout still clears loading. Added a 107m -> 95m improvement scenario: PASS. Existing success, denial, timeout, outside and equal-coordinate poor-accuracy cases PASS. No database, radius or server acceptance-rule changes. Real hardware can still return insufficient accuracy; no guarantee of perpetual clock-button availability is made.
