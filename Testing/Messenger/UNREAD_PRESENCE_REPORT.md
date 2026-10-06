# Messenger unread, contact priority, and presence

October 7 clarification: the target is specifically `[data-global-message-badge]` inside the bottom-right `#owMessengerFab`. The shared Messenger now renders that element directly instead of delegating to the dashboard badge helper (which capped counts at 99+). The general notification bell renderer was not changed. Added real API and browser assertions for zero → one received message → three additional messages (four total) → zero after opening history. The full API suite now passes 142 checks, and the seven browser cases specifically assert this floating-icon sequence while preserving presence, filtering, and send recovery.

## Existing state and implementation

1. Unread messages already use `chat_messages.MessageID`, participant membership, and each user's `chat_thread_reads.LastReadMessageID`. General notification counts are not used as Messenger totals.
2. Opening history reuses `chat_mark_thread_read`, advancing through the messages returned. The existing mark-read API remains available. Reading a conversation clears only its related chat notifications, not unrelated system notifications. Reading a notification alone does not advance the message cursor.
3. The unread API resolves the active actor from the authenticated session and database. It counts incoming messages from currently authorized peers beyond the actor's read cursor, excluding own messages and inaccessible conversations. Browser user IDs or supplied counts have no authority.
4. The floating badge displays the actual total, including totals above 99. Contacts show their numerical unread counts. The existing five-second timer coordinates contacts, current conversation history, and unread refresh without overlapping timer cycles.
5. A successful history load invalidates older unread responses, clears the displayed contact indicator, and reconciles authoritative server counts. Zero hides the badge and removes its text; newer incoming messages make it appear again. Stale contacts responses cannot restore old badge values.
6. HR contacts are sorted after server authorization, search, and role filtering. Admin roles rank first, then contacts are sorted by name and user ID for ties. No Admin IDs are hardcoded. Multiple Admin accounts follow the same ordering. A search or non-Admin role filter does not force an unrelated Admin into the results.
7. Presence reuses `$_SESSION['last_activity']`, maintained by the existing interaction-only `session_heartbeat.php`. A small helper mirrors that server timestamp into the user's database record when the authenticated Messenger contacts/unread endpoints run. It does not refresh or modify the session idle clock.
8. A contact is Online when their server-recorded interaction activity is within 120 seconds; otherwise Offline. Null activity is Offline. Active-account status is distinct. Background polling cannot turn a stale interaction timestamp into a fresh one. Expired sessions cannot update presence. No browser online flag or user ID is accepted. Contacts and selected conversation headers refresh status through the existing polling cycle.
9. Applied `sql/messenger_presence.sql`, adding nullable `users.MessengerLastActivity BIGINT UNSIGNED` as Unix seconds. The existing schema had `LastLogin` but no cross-user activity field suitable for presence. No unread, chat, or notification tables were added. Existing data remains intact.

## Files

10. Modified:

- `Api/Common/messenger_contacts.php`
- `Api/Common/get_unread_counts.php`
- `Js/Shared/ow_messenger.js`
- `Js/Shared/messenger_rbac.js`
- Messenger script include versions in existing Admin, HR, Accounting, Team Lead, Employee, Applicant pages.
- `Testing/Messenger/run.php`, `ui.cjs`, and generated result files.

11. Created:

- `Api/Helpers/messenger_presence.php`
- `sql/messenger_presence.sql`
- This report.

## Verification

12–13. **138 actual API/database integration checks passed** in a disposable clone of the production schema using synthetic authenticated users. New scenarios include:

- Admin sends two messages, Accounting three, Employee one: HR total six. Reading their conversations leaves four, one, then zero.
- New batches of one, two, and five incoming messages produce exactly those totals; reading returns zero each time.
- Supplied unread user IDs do not change whose messages are counted.
- HR Admin-first ordering, matching search, and Accounting-only filter behavior.
- Recent authenticated activity shows Online. Aging the activity timestamp by 180 seconds shows Offline despite an active account. A stale session's background polling leaves it Offline. Existing interaction heartbeat restores Online.
- Supplied presence user ID/online parameters cannot update another account. Expired sessions are rejected.
- Presence booleans are supplied for authorized contacts across Admin, HR, Team Lead, Employee, and Applicant roles.
- Existing CSRF recovery, all required role access rules, cross-company/site restrictions, own-message exclusion, same-second unread handling, pagination, IDOR prevention, persistent history, and retry deduplication remain passing.
- Test PHP server log contains no PHP errors. Production business-record checksums remain unchanged by the test run.

Seven browser cases verify actual page HTML/CSS/JS with mocked APIs: Applicant desktop/mobile, Admin, HR, Accounting, Team Lead, and Employee mobile. Assertions cover an exact seven-message badge, hidden badge and cleared contact indicator after reading, Online contact/header becoming Offline after refreshed contacts, role filtering, history pagination, CSRF send recovery, safe text rendering, and viewport fit. PHP and JavaScript syntax checks passed for the changed executable files.

14. Presence is an activity estimate, not a permanent login flag. It expires two minutes after last recorded interaction, including after logout when no further authenticated updates occur; no separate logout/session system was added. A user's other active session can keep them Online. Unread reads follow the existing conversation/history cursor; the UI does not provide selective per-message read controls, so a manual partial-message read UI test is not applicable. Presence expiry was tested by aging server timestamps rather than waiting two minutes. Browser tests use mocked APIs; backend tests use actual APIs and database-backed synthetic accounts, not real human production sessions.
