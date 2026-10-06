# Messenger send HTTP 403 fix

## Root cause and evidence

The reported response text, `Reopen Messenger and try again.`, was emitted only by the direct-message CSRF check in `Api/Common/messages_send.php`. The condition rejected a missing/non-string token or a token unequal to `$_SESSION['messenger_csrf']`. The check runs after conversation and recipient authorization. HR to Admin is allowed by the current database-resolved role rules; role labels and JSON parsing were not responsible for this reported response.

The actual API test reproduced HR to Admin sending `TEST hello world` without a token: HTTP 403, with no message written. A stale token also returned HTTP 403. Fetching the authenticated contacts endpoint's current session token and resending returned HTTP 200, and the message appeared in reloaded database-backed conversation history.

The screenshot's particular browser/session was not inspected, so whether its token was missing or stale cannot be distinguished from the supplied error alone. Its absent recent role-filter UI also suggests an older cached script, but that is an inference rather than a verified browser-cache diagnosis.

## Changes

- `Api/Common/messages_send.php`: retain and strengthen token validation (an empty server token is invalid); return a safe explanatory message and the specific `MESSENGER_CSRF_EXPIRED` response code for token rejection.
- `Js/Shared/ow_messenger.js`: obtain a token if missing; on that specific rejection only, refresh it using the existing authenticated contacts API and retry once. Retain the same request ID to prevent duplicates. Permission failures are not retried. Failed-message bubbles display safe backend explanations or a connection message.
- Existing Messenger script includes in Admin, HR, Accounting, Team Lead, Employee, and Applicant HTML pages: add `?v=20261006-csrf2` to ensure the changed script URL loads the current code after page reload. No other page features were redesigned.
- `Testing/Messenger/run.php`, `ui.cjs`, and generated results: add the reproduction/recovery and browser-retry regression checks.

No database migration, authentication change, new session mechanism, or role-policy change was required. The existing session-specific Messenger token remains server-validated; sender identity, active account checks, live recipient authorization, membership, and message validation remain enforced.

## Verification

101 integration checks passed against actual project APIs and a disposable clone of the real schema. Important results:

- HR to Admin: missing and stale tokens rejected with HTTP 403; refreshed token accepted with HTTP 200; message persisted and appeared after history reload.
- HR to Accounting, Team Lead, Applicant, and Employee: accepted.
- HR to HR peer and manipulated unauthorized recipients: rejected.
- Nonmember and forged-membership conversation access: rejected.
- Applicant to HR: accepted; Applicant to Admin: rejected.
- Employee to same-company Employee: accepted; cross-company Employee: rejected.
- Team Lead to employees at managed sites: accepted; outside managed sites: rejected.
- Invalid CSRF tokens: still rejected. Same-second unread arrivals, read clearing, own-message exclusion, pagination, and retry deduplication: passed.
- Test PHP server log: no PHP warnings, notices, fatal errors, or parse errors.
- Production business-record checksums: unchanged by the tests.

Seven browser cases passed using actual dashboard HTML/CSS/JS with mocked API responses. Each typed a message, received a simulated HTTP 403 token rejection, refreshed the token, and retried successfully with the same request ID. The successful bubble had no Failed to send label. Role filtering, unread badge clearing, responsive fit, pagination, and safe text rendering also passed.

Actual API tests used synthetic accounts in the disposable schema; no test messages were sent as real human production users. Browser tests used a temporary local server rather than the user's authenticated browser. The reported CSRF rejection and recovery are reproduced, but a combined live-human-session browser test was not performed.
