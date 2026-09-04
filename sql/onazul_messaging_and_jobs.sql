-- Onazul internal messaging (implemented in Api/Helpers/chat_helper.php with self-healing CREATE TABLE IF NOT EXISTS).
-- Read receipts: chat_thread_reads.LastReadAt per user/thread (unread = messages after LastReadAt from others).
-- Optional per-message IsRead is not used; thread-level read is sufficient for badge counts.

-- chat_threads: ThreadID, ThreadType (e.g. HR_CONTACT, ADMIN_CONTACT), RelatedModule, RelatedRecord, ...
-- chat_thread_participants: ThreadID, UserID
-- chat_messages: MessageID, ThreadID, SenderUserID, MessageText, IsSystemMessage, CreatedAt
-- chat_thread_reads: ThreadID, UserID, LastReadAt

-- Job categories (joborder.JobCategory, applicants.JobCategory): canonical values only:
--   Cashier | Chef | Security
