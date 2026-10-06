# Session warning flow correction — 2026-10-05

## Why the warning could disappear

The existing modal was present as `buildWarningModal()` in `Js/Shared/role_guard.js`. It was not a missing Bootstrap modal or stylesheet. Apache served the current script successfully; its response matched the connected source file.

Two concrete silent-redirect paths were identified:

1. A delayed warning callback, such as after a suspended/background tab resumes, calculates zero remaining seconds. The previous showWarning appended the overlay, immediately called logout from updateCountdown, and removed the overlay in the same JavaScript turn. The browser could redirect without ever painting the warning. Deterministic tests now reproduce delivery of overdue callbacks at the current clock time and verify the overlay survives a paint opportunity.
2. Confirmed background 401 handling called logout directly. Admin also had a separate SweetAlert/login redirect handler, and treated network/JSON confirmation failures as an expired session. This could bypass the shared warning flow or redirect a user without proof that authentication expired.

These are verified code paths and regression reproductions. The precise trigger in the user's authenticated browser has not been captured because normal sign-in was not completed in the opened browser tab. They are not claimed as an authenticated reproduction of every reported occurrence.

## Responsible files and component

- Automatic timeout, warning rendering and countdown: `Js/Shared/role_guard.js`.
- Server inactivity enforcement: `Api/Common/session_init.php`.
- Session validation: `Api/Login/check_session.php`.
- Server renewal: `Api/Common/session_heartbeat.php`.
- Secure logout/session destruction: `Api/Login/logout.php`.
- Competing Admin authentication redirect: `Js/Admin/admin_dashboard.js`, handleAuthExpired.

The existing shared overlay was repaired and reused. No second modal/controller/endpoint was created. It is a custom DOM modal with its own backdrop and explicit viewport positioning/z-index; it does not depend on Bootstrap JavaScript initialization. Its real DOM visibility was verified in the browser harness. Bootstrap dashboard loading was inspected, but authenticated dashboard visual testing is still outstanding.

## Exact changes in this task

Production files modified:
- `Js/Shared/role_guard.js`: reconciles warning deadlines before protected fetches, on real activity and on visibility/focus/pageshow; handles overdue warning callbacks without removing their modal before paint; routes confirmed expiry through the existing secure logout; prevents renewal of an already-expired session; tracks/clears the bounded notice redirect timer; protects DOM readiness; uses the Logout label and normal login redirect for voluntary logout; exposes shared expiry handling to existing role handlers; retains one shared controller for embedded modules.
- `Js/Admin/admin_dashboard.js`: confirmed expiry delegates to the shared controller; valid-session 403/stale 401 remains an operation failure, not logout; network or invalid-JSON confirmation failure is not falsely treated as expired authentication. No Admin layout or business function changed.

Temporary production test edit, restored:
- `Api/Common/session_init.php`: briefly set to 60 seconds for the authorized local test window, then restored to `15 * 60`. No permanent PHP behavior change was made in this task.

Tests modified:
- `Testing/SessionTimeout/frontend_test.js`
- `Testing/SessionTimeout/visual_harness.html`
- `Testing/SessionTimeout/visual_child.html`

Files created:
- `Testing/SessionTimeout/warning_server_boundary_test.ps1`
- `Testing/SessionTimeout/short_warning_verified.png`
- `Testing/SessionTimeout/FLOW_FIX_REPORT.md`

No business records, database schema, authentication credentials, dashboard layouts, Employee Management, Job Wall content, recruitment/deployment logic, attendance, payroll, schedules or notification logic were changed.

## Final timing and countdown

Production inactivity limit is **900 seconds / 15 minutes**, from the existing shared PHP constant. The frontend reads the server timeout/remaining lifetime. The warning is **60 seconds before that deadline**, normally **14 minutes after last activity**. The frontend derives a smaller warning offset only when the server explicitly supplies a short development cycle: a 60-second test cycle uses a 30-second warning offset. Production remains 14/15 minutes; no production test flag or alternative timeout system remains.

The existing one-second countdown interval displays mm:ss. Warning display is reconciled from the deadline, not granted a new arbitrary timeout. Polling, focus or returning to a visible tab can reveal an overdue warning while the server session is still valid. Backdrop clicks and Escape do not dismiss or renew it. There is no X button.

## Stay Logged In, cancellation and logout

Stay Logged In disables duplicate submission and POSTs the existing authenticated heartbeat. That endpoint first enforces server expiry, verifies existing PHP session user_id/role, updates `$_SESSION['last_activity'] = time()` and returns authenticated success with the full configured window. Only that acknowledgement closes the modal, clears the old warning/expiration/countdown/request timers and creates a new deadline. Timer generation guards and shared in-flight renewal prevent stale callbacks from overriding a new cycle. Embedded pages continue using the owner controller.

Logout uses the existing secure logout endpoint, clears the controller and pending renewal, and redirects normally to Login. Ignoring a warning logs out at the existing deadline. Existing manual logout handlers are retained.

If a browser was suspended until after server expiry, the code cannot retroactively provide a valid pre-expiry choice. It now shows an honest expired notice, disables invalid renewal, destroys the session immediately and permits at most 1.5 seconds for the notice to paint before redirecting. This brief display is not an authentication grace period. When the warning has already been visible normally for a minute, expiration/logout has no extra display delay.

## Backend versus frontend checks

No 14-minute backend cutoff was found. The shared initializer uses 900 seconds. The local PHP configuration file has session.gc_maxlifetime=1440 and session.cookie_lifetime=0, so its configured garbage-collection limit is not shorter than the warning cycle. PHP helper runtime tests confirmed authentication remains valid at ages 840 and 899 seconds, expires at 900/901, and validation does not renew last_activity. No PHP timeout was extended or expiration disabled.

The heartbeat remains server-authoritative. No browser UsersID, permanent session, stored password/token, fake production keep-alive success or restoration of expired login was introduced. Existing protected APIs continue enforcing their own authorization.

## Roles and duplicate handling

Admin, HR, Accounting, TeamLead, Employee and Applicant use the existing shared guard. Their role settings/script inclusion were inspected, and all six role controller paths were tested. The earlier iframe ownership fix remains in place. The competing Admin expiry handler now delegates to the same controller rather than redirecting independently. No separate role-specific inactivity timer was added.

## Tests actually executed in this task

- Shared controller suite: **89 PASS**. Includes all six roles; renewal and original deadline cancellation; repeated cycles; failures and stale responses; embedded ownership; real-activity throttling; missed warnings before polling; returning tabs; overdue callbacks; bounded expired notices; and Admin delegation/network-failure/valid-session authorization behavior.
- PHP shared-session boundary suite: **17 PASS**, isolated local PHP session fixtures, no database access or browser authentication bypass. Confirms 900-second production setting, valid warning-period sessions, exact expiry and no polling renewal.
- HR dashboard regression suite: **41 PASS**.
- HR Employee Management regression suite: **35 PASS**.
- Shared/Admin JavaScript syntax checks: **PASS**.
- PHP shared initializer syntax check: **PASS**.
- Browser short-cycle harness: actual shared script rendered the warning after approximately 30 seconds, with countdown and Logout/Stay Logged In; clicking Stay Logged In closed it; the page remained past the original 60-second deadline; a second warning was observed; leaving it unanswered returned to Login; a fresh warning's Logout button also returned to Login. Screenshot captured.

The browser harness explicitly mocks transport and opens no private dashboard or database data. Its visible behavior is runtime-tested; its mock acknowledgement is NOT evidence of real server renewal. The normal local sign-in page was opened for authenticated testing, but no authenticated dashboard was reached. The previously rejected synthetic authenticated browser fixture was not recreated or used. No new authenticated PHP/HTTP renewal test is claimed for this task; the real endpoint is unchanged.

## Required acceptance results

- Modal visibility: **PASS in actual short-cycle browser harness; NOT VERIFIED in a normally authenticated dashboard**.
- Countdown: **PASS in short-cycle browser and deterministic tests**.
- Stay Logged In: **PASS controller and mocked browser interaction; authenticated button-to-server browser flow NOT VERIFIED**.
- Original timeout cancellation: **PASS deterministic tests and short-cycle browser observation; authenticated dashboard runtime NOT VERIFIED**.
- New full 15-minute cycle: **PASS deterministic production-timing tests; full 15-minute authenticated browser wait NOT VERIFIED**.
- Logout button: **PASS short-cycle browser navigation and controller tests; actual authenticated session destruction through that browser button NOT VERIFIED**.
- Automatic logout: **PASS no-response short-cycle browser and controller tests; full 15-minute authenticated browser runtime NOT VERIFIED**.
- Server renewal: **NOT VERIFIED again at runtime in this task**. Existing endpoint was inspected; no fake production success was introduced.

No final automated test failed. Two browser wait/click attempts missed the short warning window and timed out; the subsequent fresh timed runs above verified warning, renewal, subsequent warning, no-response redirect and explicit Logout. They are not misreported as successful attempts.

## Remaining limitations

The primary normal-login acceptance scenario remains **NOT VERIFIED**, rather than claimed complete. The user can complete normal sign-in in the opened localhost tab for that test. A background/sleeping browser cannot display UI while its execution/rendering is suspended; a session already past the server deadline must remain expired. Separate tabs retain independent client deadlines as previously documented. Existing APIs that bypass the shared PHP initializer remain outside this narrowly scoped change; their pre-existing enforcement limitation is documented in the earlier report.

