# Employee password setup and management

Implemented Employee Dashboard → Profile → Settings → Account Security, using the existing Bootstrap Team Lead password field appearance, eye controls, policy checklist, confirmation feedback, shared strength bar and existing server policy.

Initial setup presents New Password and Confirm New Password. Configured accounts can open Change Password with Old Password, New Password and Confirm New Password. Success/errors appear in an accessible inline alert.

## Exact modified production files

- `C:/xampp/htdocs/Employee_Tracking/Html/Employee/employee_dashboard.html`: Settings menu/section, shared strength assets, section controller integration and header navigation back to dashboard.
- `C:/xampp/htdocs/Employee_Tracking/Api/Helpers/hire_applicant.php`: setup reminder inside the existing hire transaction; existing welcome text no longer assumes a known local password.
- `C:/xampp/htdocs/Employee_Tracking/Api/Helpers/notification_helper.php`: reminder through existing notifications; user row lock and duplicate check.
- `C:/xampp/htdocs/Employee_Tracking/Api/Helpers/google_auth_rbac.php`: newly created Google accounts get LocalPasswordConfigured=0; existing accounts and hashes preserved.
- `C:/xampp/htdocs/Employee_Tracking/Api/Applicant/register_applicant.php`: locally registered accounts get LocalPasswordConfigured=1.
- `C:/xampp/htdocs/Employee_Tracking/Api/Applicant/applicant_login.php`: successful local login marks password configured.
- `C:/xampp/htdocs/Employee_Tracking/Api/Login/login.php`: successful local login marks password configured; CAPTCHA and role redirects preserved.
- `C:/xampp/htdocs/Employee_Tracking/Api/Login/forgot_password.php`: existing verified-OTP reset marks password configured.
- `C:/xampp/htdocs/Employee_Tracking/Js/Shared/ow_notifications.js`: explicit Open Settings button in password-reminder details; clicking a notification still opens its details.

## New production files

- `C:/xampp/htdocs/Employee_Tracking/Api/Employee/password.php`: authenticated own-account status/setup/change endpoint.
- `C:/xampp/htdocs/Employee_Tracking/Js/Employee/employee_security.js`: Settings forms and existing password-strength component integration.
- `C:/xampp/htdocs/Employee_Tracking/sql/employee_password_setup.sql`: additive migration, applied to the connected database.

## Database migration

Adds `users.LocalPasswordConfigured TINYINT(1) NULL DEFAULT NULL`. No users, Employees or password hashes are replaced. NULL preserves historical accounts whose origin is unknown. Zero identifies future Google-created accounts requiring setup; one identifies known local passwords. Successful normal login, setup, change or existing recovery establishes one.

Historical Google-only accounts cannot be identified from their hashes. Those accounts now have Set Up Password inside Settings. A recent verified Google login (10 minutes, session-bound UsersID) authorizes initial setup for unknown accounts; otherwise the user verifies the existing email OTP inside Settings. Known-password accounts can choose Reset with email verification. The proof must match the session user and registered email, expire within 10 minutes, and is consumed after setup. The login page also exposes Forgot password / Set up password through the existing recovery modal. Existing working passwords remain usable. Hiring a locally registered applicant does not force a reset or send an inaccurate setup-required message.

## Authentication and notification safeguards

The endpoint resolves UsersID from the session, checks both session and current database Employee role, activation and matching Employee record. No supplied account identity can select the target. Mutations require a session CSRF token, prepared statements and a locked user row. Setup is allowed only while state is zero. Change requires the old password; modern hashes use password_verify, with existing legacy compatibility retained. New passwords are always hashed using PASSWORD_DEFAULT and validated through Api/Helpers/password_policy.php. A 72-byte maximum prevents silent bcrypt truncation. Passwords/hashes are never returned or logged. Repeated incorrect-current-password attempts are throttled per session.

Hiring saves the setup reminder within the existing transaction and deduplicates by authenticated target user/type. Successful setup/change marks that reminder read and refreshes the badge. The existing bell, inbox and unread infrastructure are reused.

## Tests and evidence

`run.php` is CLI-only. It clones schema and role/status reference data into a random disposable database, copies production APIs to a temporary directory and exercises them over HTTP. All accounts are synthetic. Production table checksums are compared before/after; the disposable database is removed. `results.json` contains individual PASS checks.

PASS: production Google-account creation after verified email; shared production Applicant-to-Employee hiring; original password preservation; setup notification and deduplication; repeated hire rejection; own-account setup state; anonymous/non-Employee/stale-role rejection; CSRF and IDOR rejection; empty/short/character-policy/predictable passwords; confirmation mismatch; secure setup hash; reminder read status; replay rejection; CAPTCHA enforcement; normal-login Employee redirect; configured state; incorrect old password preserving hash; successful change; old-password rejection/new-password login; existing-account preservation/change; attempt throttling; existing verified-OTP reset updating state; Google RBAC preserving Employee role/password; unchanged production data.

`ui.cjs` tests the actual Employee dashboard in headless Chrome at 1366×900 and 390×844 with synthetic API responses. PASS: Profile → Settings; setup fields; shared strength/label; eye toggle; confirmation; success state; exactly three change fields; card fits viewport; Back navigation; notification Open Settings. See `ui_results.json`, `settings-1366.png` and `settings-390.png`. Browser API mocks test UI behavior; backend results come independently from the disposable production-API tests.

NOT VERIFIED: external Google provider consent/token exchange, SMTP delivery, physical mobile-device interaction. OTP reset after an already verified code was tested; sending an email was not.

No payroll, attendance, DTR, deployment or GPS business logic was changed.

## Unrelated finding

The existing Admin post-hire Team Lead notification uses `Type`, `ReferenceID` and `CreatedAt`, absent from the inspected notifications table. Left unchanged as reported in the approved audit; the new Employee reminder uses actual schema fields and the shared hiring helper.


## Follow-up: legacy setup and Settings UI

Added `C:/xampp/htdocs/Employee_Tracking/Css/employee_security.css` for a wider responsive security card, clear blue header, padded fields, grouped policy checklist and consistent action buttons. Change Password includes Cancel. Modified `index.html` only to expose the existing setup/reset recovery entry and explanatory text; no second recovery API was added. Employee password API, Employee security JS, Google session bootstrap and normal/Applicant login were updated for the ownership proof described above. Existing normal logins clear the Google proof; working passwords are never changed automatically.

PASS: the original checks plus unverified legacy setup rejection, wrong-owner OTP rejection, expired OTP rejection, own verified OTP setup without an old password, consumed authorization, recent Google proof and expired Google proof. Total 40 backend checks pass. Desktop and mobile UI checks pass, including Cancel and legacy email-code setup. Screenshots include `change-password-1366.png`, `change-password-390.png`, `legacy-setup-1366.png`, and `legacy-setup-390.png`. Production data checksums unchanged. No further database migration was needed.

NOT VERIFIED: delivery to a real mailbox and external Google consent. Browser email-code responses are synthetic; server authorization is independently tested with session grants issued by the existing verified-code flow.
