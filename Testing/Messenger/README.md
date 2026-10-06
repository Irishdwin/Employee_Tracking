# Messenger implementation report

Implemented and verified on October 6, 2026.

Unread-badge follow-up: successful history loading immediately clears the contact dot and reconciles the total badge with server counts. Older unread requests are ignored after a read, and contacts responses cannot restore stale unread values. The seven browser cases were rerun with an initial seven-message unread badge and assertions that both the contact dot and floating badge disappear after reading. JavaScript syntax checks passed.

## 1. Existing implementation

The project already had a shared floating Messenger in the Admin, HR, Accounting, Team Lead, and Employee dashboards. It used `ow_messenger.js`, `messenger_rbac.js`, Common API endpoints, and the existing chat helpers and tables. Applicant pages had a legacy HR inbox but lacked the shared Messenger widget. The implementation extends these components rather than creating another messaging backend.

## 2. Modified files

- `Api/Helpers/messenger_rbac.php`
- `Api/Helpers/messenger_unread.php`
- `Api/Helpers/chat_helper.php`
- `Api/Common/messenger_contacts.php`
- `Api/Common/messenger_thread.php`
- `Api/Common/messages_messages.php`
- `Api/Common/messages_send.php`
- `Api/Common/messages_threads.php`
- `Api/Common/get_unread_counts.php`
- `Api/Common/mark_messages_read.php`
- `Api/Common/realtime_counters.php`
- `Api/Common/notifications.php`
- `Api/Applicant/notifications.php`
- `Api/Applicant/applicant_poll_messages.php`
- `Js/Shared/messenger_rbac.js`
- `Js/Shared/ow_messenger.js`
- `Js/Shared/ow_notifications.js`
- `Js/Applicant/applicant_notifications.js`
- `Js/Applicant/applicant_notification_page.js`
- `Js/HR/hr_dashboard.js`
- `Html/Applicant/applicant_landing.html`
- `Html/Applicant/applicant_profile_master.html`
- `Html/Applicant/my_applications.html`
- `Html/Applicant/saved_jobs.html`
- `Html/Applicant/applicant_notifications.html`
- `Html/Applicant/inbox.html`
- `Html/Applicant/job_board.html`

Existing Admin API aliases and message-fetch aliases inherit the Common endpoint changes. Existing staff dashboard markup and unrelated features remain in place.

## 3. New files

- `sql/messenger_read_cursor.sql`
- `Testing/Messenger/run.php`: actual HTTP/API/database integration tests.
- `Testing/Messenger/ui.cjs`: browser UI tests with mocked API responses.
- `Testing/Messenger/results.json` and `progress.json`: recorded integration results.
- This report.

## 4. Reused database schema

Reused `users` and `roles` for identity, active account state, and role names; `employee` for the existing normalized email linkage to user accounts; `deployment`, `deployment_sites`, `clientcompany`, and `status` for current employment and site authority.

Reused `chat_threads` (`ThreadID`, `ThreadType`, `RelatedModule`, `RelatedRecord`), `chat_thread_participants` (`ThreadID`, `UserID`), `chat_messages` (`MessageID`, `ThreadID`, `SenderUserID`, `MessageText`, `CreatedAt`), and `chat_thread_reads` (`ThreadID`, `UserID`, `LastReadAt`). Reused `notifications`, including the actual schema's `RelateedModule` spelling and existing `hr_message` notification type. No replacement chat, authentication, or notification tables were created.

## 5. Migration

The additive `sql/messenger_read_cursor.sql` migration was applied to the connected database. It adds `chat_thread_reads.LastReadMessageID` and backfills existing timestamp-based read positions. Existing messages and conversations are preserved. Message IDs prevent a message arriving within the same second as a read operation from being incorrectly considered read.

## 6. Contact rules

- Admin: all active users across actual roles.
- HR: Admin, Accounting, Team Lead, Applicant, and Employee.
- Team Lead: HR, Accounting, and employees whose current active sites are managed by that Team Lead.
- Employee: HR, Accounting, the current site's Team Lead, and other currently deployed employees in the same company.
- Applicant: HR only.
- Accounting: Admin, HR, Team Lead, and Employee, providing reciprocal access for the requested staff conversations.

These are directed rules. Each user's ability to list contacts, open history, send, or mark a conversation read is independently checked. Admin's broad contact access does not grant Applicants permission to read or reply to Admin conversations.

## 7–8. Admin and HR filtering

The server builds an authorized contact set before applying search or role filters. Role options come from actual eligible contacts rather than invented IDs. Admin receives all eligible roles; HR receives its explicit allowed roles. Search matches name, role, or company within this set. The old response exposing an unrestricted `all_users` collection was removed.

## 9. Team Lead authorization

The implementation reuses the production current-deployment SQL helper. It resolves the employee's current eligible deployment and joins active sites and companies. `deployment_sites.TeamLeadID` is the authority, covering every site managed by the Team Lead across multiple companies. A stale `deployment.SiteSuperVisorID` does not grant access.

## 10. Employee same-company authorization

User accounts link to active employee records through the project's existing normalized email relationship. Current eligible deployments, dates, status, active sites, and active companies determine company membership. Historical deployments do not authorize cross-company messaging. The assigned Team Lead comes from the employee's current site.

## 11–12. Applicant restrictions and backend enforcement

Every request resolves the actor's active account and current role from the database, not a browser-supplied identity or stale session role. Contact access, conversation creation, sending, history, unread counts, and read operations enforce the same centralized rules. Conversation membership alone cannot bypass recipient authorization. Canonical direct-message participant pairs are validated, and creation is serialized to reuse a pair's existing conversation.

Applicant contacts and legacy inbox access are HR-only. Legacy group history is filtered to permitted senders so existing HR conversations remain usable without exposing unauthorized peer messages. Inactive actors are rejected. Direct sends validate the session CSRF token, validate message length/type, and support retry request IDs. Text is rendered as text rather than HTML. Errors return safe messages instead of SQL details.

## 13. Unread messages and notifications

Unread totals and contact badges count authorized incoming messages after the user's message-ID cursor. Own messages are excluded. Opening history advances the cursor through the observed messages, preserving unread arrivals after that point. Existing chat notifications are reused and cleared through the existing read workflow; inaccessible chat notifications are excluded from lists and counts. Eligible notification details provide an Open Conversation action.

The widget refreshes through authenticated Common API polling every five seconds. The previous browser-claimed WebSocket identity path is disabled; no new WebSocket provider or external messaging service was introduced.

## 14. Applicant integration and persistent history

The same floating Messenger, styles, RBAC script, and Common APIs were added to the Applicant landing, profile, applications, saved jobs, notifications, inbox, and job board pages. Guests do not receive an active Messenger. The legacy HR inbox continues to work, with the unauthorized Admin channel removed.

Messages and pair conversations persist in the existing tables. The viewer loads the newest 200 messages and supports Load earlier messages, preserving previously loaded history during polling. Contact search and role filters, unread badges, sending, and failed-send feedback use the shared implementation.

## 15–16. Tests and results

**95 integration checks passed**, recorded in `results.json`. `run.php` calls actual production API code through HTTP sessions against a disposable database cloned from the real schema, using synthetic accounts and deployments. Coverage includes all six roles' exact contact sets, every requested allowed contact category, denied forged recipients, forged participant membership, inactive users, stale session roles, cross-company history, multiple sites and companies, stale supervisor IDs, CSRF rejection, persistent sends/history, retry deduplication, pair reuse, same-second unread arrivals, own-message exclusion, read clearing, legacy HR inbox filtering, notification filtering, and 210-message pagination.

Checksums of the connected production users, employee, applicants, job applications, notifications, deployment, payroll, and attendance records were unchanged by the test run. Disposable test resources were cleaned up.

**Seven browser cases passed**: Applicant landing at 1366 px; Applicant applications at 390 px; Admin, HR, Accounting, and Team Lead at 1366 px; Employee at 390 px. The browser tests load actual project HTML/CSS/JavaScript from a temporary local server with mocked APIs. They verify widget opening, contact selection, server role-filter requests, sending with CSRF/thread identity, loading older messages, text-safe rendering of an HTML payload, and panel fit within the viewport.

PHP syntax checks passed for all 15 affected PHP/test files. Node syntax checks passed for all seven affected JavaScript/test files.

## 17. Remaining limitations

No required implementation item remains blocked. Browser verification used mocked APIs; actual backend behavior was verified separately through real HTTP integration tests using the disposable schema. No messages were sent as real human production accounts during testing. Delivery uses five-second polling, so updates appear on the next refresh interval rather than an external push transport. Apache was unavailable during browser verification, so the browser test used a temporary local static server.
