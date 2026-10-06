# Stale Messenger badge and unread-contact priority — October 7, 2026

## Root cause

Read-only aggregate inspection of the connected database showed **49 unread HR participant messages in APPLICATION_EVENT threads**, plus one unread DIRECT message. All 72 application-event messages in the database had `IsSystemMessage=1`; all 10 DIRECT messages had `IsSystemMessage=0`. These automated workflow messages were included by the Messenger unread helper, while opening a direct conversation read only that conversation. Consequently the workflow-event contribution remained visible even after the user read their direct messages.

This was a mismatch in what the counter classified as Messenger messages, rather than the notification bell's count being copied. The previous browser tests used direct messages only and did not reproduce these production application events. The corrected regression seeds 49 automated events and confirms that they contribute zero to Messenger unread totals. Automated workflow data and general notification-bell behavior are preserved.

## Read state and authoritative counts

Reused `chat_thread_reads.LastReadMessageID` and existing `chat_mark_thread_read`. History retrieval validates the database-resolved actor, role/relationship rules, and conversation membership. After selecting and returning authorized messages, it advances the user's cursor through the observed messages. Only that user's state is updated.

The shared unread helper counts authorized incoming **non-system** messages beyond the actor's cursor. Own messages are excluded. Automated application events are excluded using the existing `IsSystemMessage` column; no data is deleted or marked read merely to make a badge disappear.

History responses now return `read_confirmed` and the recalculated authoritative `unread` payload after the read operation. The frontend applies this payload directly instead of subtracting an independent optimistic count. Old requests are invalidated after a confirmed read. History/unread APIs and their fetches use no-store caching. The floating badge's text is cleared and the element hidden when the total is zero; new incoming messages restore it. Older clients can continue using the response's existing fields.

## Contact priority

The unread helper also supplies each authorized sender's latest unread incoming timestamp and message ID. These values are generated from the database, never accepted from the browser. Contacts remain unique by user.

Contacts sort by:

1. For HR, Admin roles first, dynamically resolved from the database.
2. Contacts with unread incoming messages.
3. Latest unread timestamp descending; message ID descending for timestamp ties.
4. Normal name order, then user ID for deterministic ties.

The backend sorts the authorized, searched, role-filtered result set. The frontend repeats the same priorities when newer unread polling metadata arrives, preserving the active conversation. Reading removes unread priority; a later message restores it. Admin's HR priority applies independently of unread state, but cannot inject an Admin into a nonmatching search or another role's filter.

Presence remains the existing server-observed recent session activity estimate. Online/Offline neither supplies unread counts nor influences ordering. The existing coordinated five-second polling cycle is reused; no polling timer or separate messaging system was added.

## Files and schema

Modified:

- `Api/Helpers/messenger_unread.php`: exclude system messages and calculate latest unread metadata.
- `Api/Common/messenger_contacts.php`: return metadata and sort authorized contacts.
- `Api/Common/get_unread_counts.php`: return latest unread metadata and disable caching.
- `Api/Common/messages_messages.php`: return confirmed post-read counts and disable caching.
- `Js/Shared/ow_messenger.js`: apply authoritative read responses, synchronize recency metadata, sort during refresh, and avoid cached fetches.
- Existing Messenger script includes: version updated to `20261007-unread2`.
- `Testing/Messenger/run.php`, `ui.cjs`, and recorded results.

Created `Testing/Messenger/ui_ordering.cjs` and this report. No database migration was needed; the existing system-message flag, timestamps, message IDs, and read cursor were sufficient. Authentication, CSRF validation, recipient authorization, company/site restrictions, Applicant HR-only access, and conversation membership checks remain enforced.

## Actual verification

All **156 API checks passed** against actual copied production APIs and a disposable clone of the real schema. New checks reproduce the 49 automated-event messages; assert they do not inflate Messenger counts; verify HR Admin-first followed by latest unread Team Lead and Employee; assert numerical totals of six, four, one, and zero; verify the database cursors cover the returned messages; and verify non-HR recency priority and restored normal ordering after reads. Timestamp ties use message IDs. Existing unread totals, refresh/read behavior, role/IDOR restrictions, CSRF recovery, persistence, pagination, presence, and production-record checksum tests remain passing. Test PHP server logs contain no PHP errors.

Seven existing browser cases passed with actual HTML/CSS/JS and mocked APIs, now using authoritative post-read payloads. Two additional browser cases (HR and Admin) passed dynamic recency ordering, reading removes priority, active conversation preservation, zero-count badge after page reload, new-message badge reappearance, and search behavior.

PHP and JavaScript syntax checks passed for the changed executable files. Production user/business records were unchanged by the disposable tests. The aggregate diagnosis did not retrieve message contents or change read state for real human accounts.

## Limits

Browser verification uses mocked APIs, while database/read verification uses real endpoints with synthetic sessions in the disposable schema. No message was sent or marked read as a real production user. Updates use the existing five-second polling interval. Read semantics remain conversation/history cursor semantics; no selective per-message read UI was introduced. Older human messages in legacy inbox conversations retain their existing unread behavior and remain readable through those inboxes; only automated system messages are excluded from the Messenger total.
