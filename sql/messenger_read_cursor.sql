ALTER TABLE chat_thread_reads ADD COLUMN IF NOT EXISTS LastReadMessageID INT NOT NULL DEFAULT 0;
UPDATE chat_thread_reads r SET LastReadMessageID=COALESCE((SELECT MAX(m.MessageID) FROM chat_messages m WHERE m.ThreadID=r.ThreadID AND m.CreatedAt<=r.LastReadAt),0) WHERE r.LastReadMessageID=0 AND r.LastReadAt IS NOT NULL;
