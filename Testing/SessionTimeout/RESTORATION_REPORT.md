# Session timeout warning restoration

Verified against the connected Employee_Tracking project on 2026-10-05. This report supplements the earlier README and distinguishes deterministic, server-runtime and browser evidence.

## 1. Causes found

The modal was not deleted. It is created dynamically by `buildWarningModal()` in the existing shared role guard, rather than stored as dashboard HTML. Searches found no separate timeout stylesheet or second independent timeout implementation. The five staff dashboard HTML files still include the shared guard with matching role settings.

The current authenticated Applicant landing page did not include the guard at all, so its warning never initialized. Its older Applicant dashboard did include it, but that did not cover the actual landing/job-wall flow.

HR and Admin dashboards embed authenticated modules such as applicant_contracts.html and employee_conversion.html. Those modules also load the guard. The initialization flag was local to each window, allowing hidden frames to create competing modal/countdown/expiration controllers. Renewing the parent did not reset an iframe's original deadline. Reproduction with the real controller and independent parent/frame clocks demonstrated this stale-controller path; it can interrupt the visible warning/renewal flow.

Other gaps: dashboard fetch-based manual logout did not stop the idle controller; synthetic activity could renew the session; Applicant password and verified Google login did not explicitly reset an existing last_activity value; backend expiration used >900 rather than >=900.

No evidence established a missing staff script URL, changed modal ID, removed CSS, Bootstrap initialization failure or specific recent commit as the cause. An authenticated staff dashboard browser reproduction of the reported disappearance is NOT VERIFIED.

## 2–3. Existing implementation and restoration approach

Reused `Js/Shared/role_guard.js`, its dynamic `session-timeout-overlay`, countdown, acknowledgement-based renewal, throttled activity listeners, background-401 confirmation, generation-guarded timers and existing PHP session/logout endpoints. No second timeout system or replacement endpoint was created. The modal retains the existing visual design with a clearer title, Stay Logged In label, mm:ss countdown and dialog attributes. Background clicks and Escape have no dismissal handler and cannot renew while the warning is visible.

## 4. Exact files modified/created in this task

Production files modified:
- `Js/Shared/role_guard.js`: same-origin embedded modules delegate to the ancestor controller, including while ancestor validation is pending; optional authenticated public-page mode; existing inline Applicant auth-success event initialization; synthetic-event exclusion; manual logout cleanup and pending-renewal abort; warning title/label/countdown/accessibility.
- `Html/Applicant/applicant_landing.html`: only two script lines added to load the existing guard in optional Applicant-session mode. Guests continue using the public page normally.
- `Api/Common/session_init.php`: expiration comparison changed from > to >= the existing timeout constant.
- `Api/Applicant/applicant_login.php`: one last_activity assignment following successful password authentication.
- `Api/Helpers/google_auth_rbac.php`: one last_activity assignment following verified Google authentication.

Tests modified:
- `Testing/SessionTimeout/frontend_test.js`
- `Testing/SessionTimeout/server_test.cli`

Created:
- `Testing/SessionTimeout/visual_harness.html`
- `Testing/SessionTimeout/visual_child.html`
- `Testing/SessionTimeout/warning_visual_verified.png`
- `Testing/SessionTimeout/RESTORATION_REPORT.md`

The visual harnesses explicitly mock transport, require no authentication and open no private data. A temporary authenticated browser fixture was rejected by automatic approval review and removed. No such endpoint remains. Earlier unrelated workspace edits were preserved; the complete Git diff is not this task's change list.

## 5–7. Shared component and timing

One shared guard owns the timeout for the top authenticated dashboard. Same-origin embedded modules retain their own role validation but forward real activity to the owner and do not create competing timers/modals. Independent tabs and cross-origin frames retain independent controllers; they are not synchronized across browser tabs.

Authoritative configuration remains `SESSION_IDLE_TIMEOUT_SECONDS = 15 * 60` in the shared PHP session initializer. Session validation returns timeout_seconds and remaining_seconds; the frontend consumes them. The existing frontend fallback remains 15 minutes. Warning occurs 60 seconds before the authoritative deadline, normally at 14 minutes, and expiry is 900 seconds/15 minutes. Reload uses the server's remaining lifetime rather than granting a new 15 minutes. Production values were never shortened for testing.

## 8–12. Renewal and cancellation flow

Login establishes PHP user_id/role and last_activity. check_session.php validates that session and existing RBAC. The controller schedules warning and expiration using the returned remaining time.

Stay Logged In disables duplicate submission and POSTs the existing `Api/Common/session_heartbeat.php` with the session cookie. The shared initializer checks expiry first. A valid session updates `$_SESSION['last_activity'] = time()` and returns success:true, authenticated:true and the full 900-second window. No browser UsersID is accepted as proof of identity. An expired or absent session returns 401 and cannot be recreated by renewal.

Only acknowledged renewal resets the client deadline, clears the previous warning timeout, expiration timeout and countdown interval, removes the overlay and starts a new cycle. Timer generation guards invalidate old callbacks. The request timeout is cleared when the request settles; stalled renewals are bounded to ten seconds. Failed renewal keeps the warning and retry available. An in-flight renewal cannot be superseded by the original expiry callback; an expired server rejects it.

Normal manual fetch-based dashboard logout now stops the same controller and aborts pending renewal before the existing logout request. Logout uses the existing session destruction/cookie deletion mechanism. Late renewal responses cannot create new timers after logout. Embedded manual logout stops the owner too. No new redirect timer is installed.

Background polling does not update last_activity. Existing real click/keydown/mousemove/scroll/touch activity remains throttled to at most one heartbeat per minute, including failed requests; synthetic events are excluded. While the warning is visible, only its explicit buttons decide renewal/logout.

## 13–15. Roles, manual logout and security

Admin, HR, Accounting, TeamLead and Employee dashboard script inclusion was inspected. Applicant's current landing page is now covered, including inline login via the existing onazul-auth-success event. Existing protected Applicant pages continue using the guard.

Actual PHP/HTTP tests for all six roles confirmed renewal, repeated renewal, timestamp update, unchanged identity/role, protected session validation, full 900-second response, exact boundary expiry denial, logout and rejection of renewal after logout. Browser normal credential login/manual-dashboard logout was not performed.

Existing authentication, RBAC, cookies, expired-session rejection and session destruction remain intact. No permanent session, localStorage password/token, trusted browser identity, fake production keep-alive success or business-record/database-schema change was introduced. The test server used an isolated temporary session directory and existing local users without changing their accounts. Test-generated audit events had unique markers and were cleaned by the existing suite; no business records were modified.

## 16–17. Tests and PASS / FAIL / NOT VERIFIED

Actually executed for this implementation:
- Shared controller suite: **75 PASS**, deterministic clock, all six roles, repeated renewal, original deadline cancellation, network failures, stale responses, duplicate initialization, optional public page, synthetic activity, normal/embedded logout, and parent/frame startup race.
- Local PHP/HTTP suite: **82 PASS**, existing MariaDB users, isolated PHP sessions, all six roles, real renewal/logout/validation APIs, exact 900-second boundary.
- HR dashboard controller regression suite: **41 PASS**.
- HR Employee Management controller regression suite: **35 PASS**.
- PHP syntax checks for the three changed PHP files: **PASS**.
- Shared JavaScript syntax check: **PASS**.
- Browser visual harness: **PASS** for visible warning/countdown, one parent overlay and zero child overlays, Stay Logged In closing warning after mock acknowledgement. Screenshot saved. This tests the real shared rendering/controller with explicit mock transport; it is not an authenticated dashboard test or proof of server renewal.

Requested scenarios:
1. Warning restored: **PASS in visual harness**; **NOT VERIFIED after normal login in a production dashboard**.
2. Automatic expiry: **PASS deterministic controller and aged-session PHP/HTTP tests**; **NOT VERIFIED as a real 15-minute browser wait**.
3. Stay Logged In: **PASS real server renewal plus controller tests and mocked browser interaction**; combined authenticated dashboard button-to-server browser flow **NOT VERIFIED**.
4. Pass original expiry: **PASS deterministic parent and embedded-controller tests**; real 15-minute browser runtime **NOT VERIFIED**.
5. New full cycle: **PASS 900-second server response and deterministic timing tests**; real 15-minute browser wait **NOT VERIFIED**.
6. Second renewal: **PASS controller and real server tests**; consecutive browser inactivity cycles **NOT VERIFIED**.
7. Protected API after renewal: **PASS actual check_session.php HTTP validation for all six roles**; role-specific business API interaction **NOT VERIFIED**.
8. Manual logout: **PASS actual logout endpoint and post-logout renewal rejection for all six roles, plus normal/embedded controller cleanup tests**; normal dashboard logout button in an authenticated browser **NOT VERIFIED**.
9. Multiple roles: **PASS controller and PHP/HTTP tests for all six roles**; authenticated visual dashboard checks for each role **NOT VERIFIED**.

No full 15-minute browser runtime test was executed. No final automated suite failed. Browser harness logging captured a MutationObserver observe TypeError without a source URL; the loaded project harness/controller files contain no MutationObserver call. Its origin remains unresolved, so browser-console cleanliness is NOT VERIFIED rather than claimed as passed.

## 18. Remaining limitations

Automatic approval review rejected browser access through a synthetic HR session because it bypassed normal sign-in. The safe visual harness was used instead and the fixture was removed. Normal authenticated browser testing remains necessary to confirm the user-observed staff-dashboard disappearance and full elapsed-time flow. No request for credentials or weakening of authentication was introduced to complete testing.

Separate tabs retain separate client lifecycles, as before. Several pre-existing APIs call session_start() directly instead of the shared initializer; those endpoints do not enforce inactivity themselves until a shared-session endpoint/controller destroys the session. This was found in the scan and is reported rather than changing unrelated business API files in a warning-restoration task. The changed renewal/validation/logout endpoints themselves enforce the authoritative expiry.

During final cleanup verification, the existing server_test.cli source was absent after its successful 82-check run. Attempting to restore that path failed with a filesystem write error. The cause is not established; no other process is assumed. Its runtime output was captured, but retention of that repeatable server test source is NOT VERIFIED. The frontend tests, visual harnesses, report and production changes remain present.

HR Employee Management, dashboard layouts, recruitment/deployment business logic, attendance, payroll, schedules, Job Wall content and notification behavior were not edited for this request.
