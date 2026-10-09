Add Back image evidence implementation and verification
2026-10-09

1. Existing functionality inspected
Employee and Accounting dashboard mounts; Js/Shared/add_back.js; Api/Common/add_back_requests.php and attendance_evidence.php; existing Add Back, private attendance evidence, audit, notification, payroll adjustment and billing helpers; actual add_back_evidence and notifications schemas; private uploads/.htaccess rules; existing Testing/AddBack/run.php. Applied the ONazul HRIS Development Skill from the user-supplied development skill ZIP.

2. Evidence limitation
The request API explicitly rejected every upload. Submission only copied recorded clock-in/out photos, and the form offered no upload input. The private evidence reader accepted JPEG/PNG and only clockin/clockout request evidence types. No upload-backed submission confirmation or notification-to-request navigation was present.

3. Files modified
Api/Common/add_back_requests.php
Api/Common/attendance_evidence.php
Api/Helpers/add_back.php
Api/Helpers/attendance_evidence.php
Js/Shared/add_back.js
Api/Helpers/client_billing_helper.php: direct helper callers now reuse existing frozen membership snapshots, preserving reviewed Add Back itemization. No attendance or payroll formula changed.
Testing/AddBack/run.php: current CSRF and compute/export/review/finalize sequence, isolated connections and upload test hook.

4. Files created
Testing/AddBack/upload_cases.php
Testing/AddBack/upload_ui.cjs
Testing/AddBack/accounting_upload_ui.cjs
Testing/AddBack/upload_results.json (actual final backend output)
docs/ADD_BACK_IMAGE_EVIDENCE.md

5. Database changes
None. Existing add_back_evidence.EvidenceType VARCHAR(20) and its unique RequestID/EvidenceType key support upload1, upload2, upload3 alongside clockin and clockout. Existing protected storage names, MIME, size, hash and request foreign key are reused. Actual schemas were inspected read-only. No migration or new API endpoint is required.

6. Image upload
The Bootstrap form adds Upload Image Evidence after the reason. It shows filenames, responsive previews, removal controls, format/size/count errors and a submitting state. It accepts up to three JPG/JPEG/PNG/WEBP files, maximum 5 MB each. The existing stricter camera policy of 20 million pixels is also enforced server-side. Recorded photos are automatically preserved, and either recorded or uploaded evidence satisfies the evidence requirement. No recorded photo now produces the requested non-blocking upload guidance.

Multipart submission uses the existing Add Back endpoint. Server checks actual PHP upload provenance, upload error, extension/MIME agreement, physical size, image dimensions and decoded image content. Files receive random generated names under existing protected uploads/add_back_evidence. EvidenceType, MIME, byte count and SHA-256 link each image to its request. Newly uploaded images have no fabricated clock capture timestamp. Failed request/audit transactions remove copied files and roll back database records. No client employee, pay or deployment identity is trusted.

7. Success modal
Only a confirmed saved request closes the form and opens Add Back Request Submitted with a green checkmark. It displays the actual database request ID, work date, duty interval, uploaded image count and Pending Accounting Verification. Done closes the modal and refreshes the request list. Backend errors keep form fields and selected images; duplicate clicks are blocked. Dismissal is blocked while the request is in flight so the submission state remains visible.

8. Accounting notifications
The existing notifications table and Add Back notification helper are reused. Delivery occurs after the request commit, to all active Accounting users. A request-row lock and existing notification lookup make retries idempotent without changing read state. Failed delivery is logged and retried when a persisted pending request is listed. Failed submission creates no notification. The notification includes employee, site, work date and the actual request ID. Existing notification-open events navigate to Add Back Requests and open the matching request. Existing badge polling and mark-read behavior are retained; no second notification transport is introduced.

9. Accounting evidence
Existing list/detail views show both sources. Labels explicitly distinguish System-recorded Clock In/Out from Employee-uploaded supporting evidence, with Accounting verification still required. Existing authenticated evidence preview/download endpoint serves both sources and checks ownership and file integrity. WEBP is supported for uploaded evidence; recorded attendance image behavior remains JPEG/PNG. Approval/rejection, employee status history and approval/rejection notifications remain intact.

10. Authorization and security
Active database role must match the authenticated session. Employees resolve their own employee record from the session; Accounting/Admin retain the existing global Accounting scope. JSON review writes and multipart submissions require a session-bound Add Back CSRF token and existing origin validation. Evidence requests accept database IDs and source types, reject caller-supplied paths and unauthorized roles, and deny another employee's evidence. Private storage retains Require all denied. Prepared statements, employee locks, request locks and existing transaction/audit rules remain enforced. Uploaded evidence never automatically approves duty. Approval uses existing compensation calculations and separate Add Back adjustments rather than rewriting locked attendance, DTRs or finalized payroll.

11. Tests and actual results
RUNTIME VERIFIED — Testing/AddBack/run.php, copied real APIs and disposable database:
- Existing normal, regular holiday, holiday/rest day, incomplete clock, rejected and missing historical rate flows passed.
- Real compute → XLSX export → reviewed finalization passed for approved adjustments, including the original separate Add Back payslip/billing line and no duplicate adjustment/payment.
- Valid JPEG, PNG, WEBP and three-image multipart uploads passed without recorded attendance photos.
- Pending state, actual success details, authorized preview/download and cross-employee denial passed.
- PDF, oversized image, disguised executable, four images, missing evidence, missing CSRF and manipulated employee ID were rejected.
- Invalid submissions created no request/notification; synthetic audit failure rolled back the request and removed copied evidence.
- Two active Accounting accounts received notifications; inactive Accounting did not; duplicate submissions/retries created no duplicate notifications; shared read state passed.
- Uploaded evidence rejection updated the employee status; uploaded evidence approval used the existing Add Back adjustment calculation.
- Existing recorded-only submissions still passed without additional uploads.
- Production checksums for employee, attendance, daily_time_records, dtr_submissions, payroll, payroll_adjustments, Add Back requests/evidence, notifications, salary_rates and holidays were unchanged. Disposable test database removed.

RUNTIME VERIFIED — mocked browser APIs, 1366px and 390px:
- upload_ui.cjs passed file count/type/size checks, previews/removal, missing evidence, failed submission value preservation, no false success, duplicate-click prevention, correct success details, Done and pending list refresh.
- accounting_upload_ui.cjs passed notification badge display, notification click to the matching request, mark-read behavior, source labels and authenticated evidence download control.

Syntax checks passed for modified PHP and JavaScript. Actual backend results are saved in Testing/AddBack/upload_results.json. Initial runner failures were outdated disposable connection/CSRF/finalization assumptions; the runner was updated to the current production interfaces before successful runs. Browser API tests verify UI behavior; actual upload/content/authorization behavior is separately covered by real HTTP requests to the copied APIs.

12. Limitations
The existing notification badge refresh cadence is retained; delivery is committed immediately, while display follows existing polling. If notification persistence is unavailable, delivery is logged and retried from the persisted pending request. Supporting evidence remains unverified until Accounting reviews it. The 20-million-pixel image policy may reject unusually large-resolution images even below 5 MB. Historical payroll remains immutable; Add Back compensation follows existing effective-dated rate and open-cutoff rules. Validation used synthetic identities, not real employee submissions.
