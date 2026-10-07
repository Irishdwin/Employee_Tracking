# Login security check fix — 7 October 2026

## Cause and reproduction

Before the fix, `Api/Login/captcha_gen.php` overwrote one shared `$_SESSION['captcha_code']` on every load/refresh. Both `check_captcha.php` and `login.php` compared against that single answer. Opening another tab changed the expected answer without updating the first tab's display. The actual browser reproduction showed **10 + 23**, entered **33**, and received **Please fill correct value** after opening a second tab.

The input handler also sent a validation request for each keystroke without guarding against an older response arriving last. Refresh requests had the same missing response guard. `index.html` included the login script once; duplicate inclusion was not found. Shared session initialization did not regenerate session IDs during challenge generation, and normal login had no session-ID regeneration. Session regeneration was not the cause.

## Implementation and files

Modified `Api/Login/captcha_gen.php`, `Api/Login/check_captcha.php`, `Api/Login/login.php`, `Js/Login/employer_login.js`, and `index.html`. Created `Api/Login/captcha_helper.php` and this test directory.

- Server-generated 256-bit challenge IDs identify separate answers in the existing PHP session. Only the ID and display operands are returned; the expected answer stays server-side. No database migration is needed.
- Challenges expire after 10 minutes. Storage is bounded to 64 active challenges per session. Refresh retires the submitted previous challenge, preserving other tabs. Login validation consumes the specific challenge even if the answer or credentials fail. Feedback validation does not consume it. Replay is rejected.
- Feedback requests are debounced and checked against the current input, challenge ID, and request generation. Late refresh responses cannot replace newer display values. Loading and login disable conflicting controls. Reload and back/forward restoration obtain a fresh challenge.
- Server validation trims surrounding whitespace and accepts at most three ASCII digits. Incorrect answers, arrays, and values such as `82abc` are rejected. Browser input does not strip malformed characters into a valid answer.
- Challenge responses use `Cache-Control: no-store`; the changed login script has an asset version. Global caching is unchanged.
- Password/account validation, hashes, legacy password upgrade, employee-number lookup, role redirects, password setup/change, and the separate Google login flow remain in place. Successful password login now regenerates the session ID once, after credential verification, retaining other tabs' active challenges.

## Tests and results

- `node Testing/LoginCaptcha/reproduce.cjs`: run **before** the fix; confirmed the actual two-tab false rejection. This script documents the former bug and intentionally expects its former error.
- `php Testing/LoginCaptcha/server_test.php`: **53 passed**. Exact sums 2+5, 9+12, 3+79, 45+27; malformed/incorrect input; whitespace; expiry; replacement; replay; independent tabs; actual copied HTTP login endpoints with synthetic accounts; correct/wrong passwords; nonexistent/inactive accounts; employee number and RBAC redirect; session regeneration; other-tab continuity. Initializes a disposable MariaDB on a random loopback port from repository schema definitions, without importing account data, and cleans up its own server/data directory.
- `node Testing/LoginCaptcha/browser_test.cjs`: **7 passed** against the actual local login page/endpoints, including two tabs in both directions, delayed feedback, deliberately out-of-order rapid refresh, stale challenge rejection, reload, and malformed answers.
- PHP/JavaScript syntax and whitespace checks passed.

## Limits

The installed XAMPP MariaDB currently returns **Host '127.0.0.1' is not allowed to connect** (localhost/IPv6 also fails). Password authentication was therefore verified using the real endpoint code on the isolated synthetic server; live production-account login remains dependent on repairing that separate database availability issue. This task did not change database grants or restart XAMPP.

Google's endpoint was checked to remain CAPTCHA-independent and its implementation was unchanged. A complete external Google OAuth sign-in was not performed. Challenges intentionally expire after 10 minutes, and exceeding 64 concurrent challenges evicts the oldest.
