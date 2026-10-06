# Session timeout / Stay Signed In fix

Implemented 2026-10-05 directly in the connected Employee_Tracking project, following the ONAZUL HRIS Development Skill and the attached request.

## Root cause and architecture

The shared `Js/Shared/role_guard.js` controller called `hideWarning()`, fired `sendHeartbeat(true)` without inspecting its response, and immediately reset its local timers. The heartbeat function discarded failures and did not check HTTP status or JSON success. The client therefore claimed renewal even when the server did not accept it. Normal activity also restarted local time independently of server renewal. Page reload always started a fresh client 15-minute window even when the existing server session had less time left. No initialization guard prevented duplicate inclusion from creating additional timers/listeners. Existing background-401 confirmation callbacks were not associated with the renewal cycle.

The existing server heartbeat already updated `$_SESSION['last_activity']` for valid sessions. Server expiration itself was not disabled or extended to fix this bug. The missing connection was confirmation of that renewal and synchronization of client deadlines.

Flow: password/Google/applicant login establishes the PHP session → `Api/Login/check_session.php` validates it and synchronizes the existing role → shared role guard checks page access → warning at one minute before server idle expiration → Stay Signed In calls existing `Api/Common/session_heartbeat.php` → shared `session_init.php` first rejects expired sessions → authenticated heartbeat renews `last_activity` → confirmed response clears old client timers and starts the next cycle.

The authoritative window remains `SESSION_IDLE_TIMEOUT_SECONDS = 900` in `Api/Common/session_init.php`. Ordinary polling does not renew activity. Local PHP configuration uses a browser-session cookie (`cookie_lifetime=0`) and a 1440-second GC lifetime, so a persistent cookie renewal was not required. No activity/authentication data is stored in localStorage by this timeout controller.

## Exact production files modified

- `Js/Shared/role_guard.js`: one-controller guard; server remaining-time initialization; acknowledged heartbeat renewal; disabled Stay button while pending; warning stays open on failure; retry error text; bounded stalled renewal; one shared in-flight heartbeat; throttled activity including failures; old deadline defers while renewal is pending; timer generation guards; stale background-confirmation protection; idempotent logout.
- `Api/Common/session_heartbeat.php`: existing endpoint retained; requires both session user ID and role; adds confirmed authenticated and remaining-time fields; disables caching. Continues to rely entirely on the server session and existing expiry helper.
- `Api/Login/check_session.php`: exposes configured timeout and actual remaining seconds; disables caching. Existing user/role database synchronization and response fields retained.

No new API, database migration, timeout duration change, dashboard design edit, RBAC change, or module business logic change.

Created tests/report: `Testing/SessionTimeout/frontend_test.js`, `Testing/SessionTimeout/server_test.cli`, this README. PHP test files disappeared after successful runs during verification. The repeatable server test is retained as a PHP CLI source with a .cli extension. Production changes remained intact.

## Renewal and timer behavior

The Stay button does not close the modal itself. The controller sends a credentialed POST and accepts only a successful HTTP response with `success:true` and `authenticated:true`. On success it clears the old warning timeout, expiration timeout, countdown interval, and the request watchdog. It removes the warning and starts one new warning/expiration pair. Cycle IDs also make queued callbacks from earlier cycles harmless. There is no separately scheduled redirect during an active cycle; login redirect happens after the committed logout flow, and a late renewal cannot undo that flow.

The client deadline uses the server's remaining seconds and the request start time conservatively, accounting for network delay instead of claiming more remaining time. A pending renewal may defer the old deadline for up to ten seconds; server-side expiration remains enforced. HTTP 401 triggers logout without recreating authentication. Network/server failures keep the warning and allow retry while the original deadline remains applicable. Pending requests are deduplicated. Ordinary activity requests are throttled to one per minute; explicit Stay/retry can bypass that throttle.

## Roles and security

The controller is shared by Admin, HR, Accounting, Team Lead, Employee, and protected Applicant pages. Existing dashboard role checks and redirects remain. No UsersID/role supplied by the browser is accepted as proof of identity; heartbeat receives no identity payload. It preserves authenticated user ID, role, additional RoleID/authorization fields, and session identity. Expired, anonymous, partial, and logged-out sessions cannot renew. Manual logout still clears variables, deletes its cookie, destroys the server session, and makes subsequent session checks unauthorized.

## Test results

PASS — 61 deterministic-clock tests execute the actual shared JavaScript controller, with no production timeout reduction. Each of the six roles covers warning, wait-for-server behavior, renewal, original timeout cancellation, next warning, repeated renewal without timer accumulation, automatic expiration, and redirect. Additional checks cover duplicate script inclusion, double click, retry after failure, renewal crossing the original deadline, stalled request expiration, expired-server response, manual logout during pending renewal, failed/successful activity throttling, and stale 401 confirmation.

PASS — 82 actual HTTP/PHP-session assertions on the local PHP server and MariaDB-backed session check. All six roles cover refreshed server timestamp, preserved IDs/roles/authorization, the unchanged full timeout, noncached response, authenticated API after renewal, repeated renewal, expired session rejection, manual logout, and inability to revive logout. Reload remaining-time, no renewal from ordinary polling, anonymous heartbeat, and partial session checks also pass.

PASS — regression suites: 82 existing HR API checks, 42 HR dashboard frontend checks, 37 Job Order Request frontend checks.

PASS — PHP syntax for both modified PHP endpoints and retained server test; JavaScript syntax and controller tests.

PASS — test cleanup: isolated session fixtures removed and only logout audit entries identified by the unique synthetic session marker removed. No business records or schema were changed. The temporary PHP server is stopped after verification.

NOT VERIFIED — a real browser login followed by a physical 15-minute wait/click/second physical wait; browser visual/focus behavior; external deployment and browser sleep/multiple-tab behavior end-to-end. Original expiry cancellation and second warning are verified through deterministic execution of the real controller, rather than claimed as a physical browser UAT pass. Login credentials/OAuth flows were inspected, not changed or exercised with real passwords.

## Remaining limitations / issues found

- Several legacy APIs still start sessions directly instead of using `session_init.php`, including some applicant, HR, Team Lead and messaging endpoints. This is a broader pre-existing idle-expiration consistency issue; those unrelated module endpoints were not changed. The tested heartbeat/check-session/logout paths use the shared helper.
- The page-level controller does not coordinate deadline changes across different browser tabs. Explicit renewal is authoritative on the server; other tabs still have their own UI timers. Cross-tab UAT remains unverified.
- A renewal sent after server expiry requires a new login, as intended. Network failures cannot be treated as successful renewal.
- Modern Fetch/AbortController support is required, consistent with the existing browser-oriented frontend.

## Reproduce

From the project root: `node Testing/SessionTimeout/frontend_test.js`.

For server tests, start a local PHP server on port 8894 with an isolated temporary `session.save_path`, then run `php` with the same session directory and `Testing/SessionTimeout/server_test.cli http://127.0.0.1:8894`. The server test synthesizes isolated PHP sessions for existing active local role users; it does not log in by using their passwords or alter their accounts. Temporary logout audit entries are uniquely marked and cleaned in finally.

