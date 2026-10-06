<?php
/**
 * Simple chat/inbox helpers for HR-contact + application event messaging.
 * - Creates required tables if missing (self-healing for local dev).
 * - Provides helper to ensure a thread exists and to add messages.
 */

function chat_ensure_tables(mysqli $conn): void {
    $conn->query("
        CREATE TABLE IF NOT EXISTS chat_threads (
            ThreadID INT(11) NOT NULL AUTO_INCREMENT,
            ThreadType VARCHAR(30) NOT NULL,
            RelatedModule VARCHAR(100) DEFAULT NULL,
            RelatedRecord INT(11) DEFAULT NULL,
            CreatedBy INT(11) DEFAULT NULL,
            CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UpdatedAt DATETIME DEFAULT NULL,
            PRIMARY KEY (ThreadID),
            KEY idx_chat_threads_type_related (ThreadType, RelatedModule, RelatedRecord)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS chat_thread_participants (
            ThreadID INT(11) NOT NULL,
            UserID INT(11) NOT NULL,
            PRIMARY KEY (ThreadID, UserID)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS chat_messages (
            MessageID INT(11) NOT NULL AUTO_INCREMENT,
            ThreadID INT(11) NOT NULL,
            SenderUserID INT(11) NOT NULL,
            MessageText TEXT NOT NULL,
            IsSystemMessage TINYINT(1) DEFAULT 0,
            CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (MessageID),
            KEY idx_chat_messages_thread_time (ThreadID, CreatedAt)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS chat_thread_reads (
            ThreadID INT(11) NOT NULL,
            UserID INT(11) NOT NULL,
            LastReadAt DATETIME DEFAULT NULL,
            LastReadMessageID INT NOT NULL DEFAULT 0,
            PRIMARY KEY (ThreadID, UserID)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
}

function chat_get_active_users_by_role(mysqli $conn, string $roleName): array {
    $stmt = $conn->prepare("
        SELECT u.UsersID, u.FullName
        FROM users u
        INNER JOIN roles r ON u.RoleID = r.RolesID
        WHERE r.RoleName = ? AND u.IsActive = 1
        ORDER BY u.FullName
    ");
    $stmt->bind_param("s", $roleName);
    $stmt->execute();
    $result = $stmt->get_result();
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = [
            'user_id' => (int)$row['UsersID'],
            'full_name' => $row['FullName'],
        ];
    }
    $stmt->close();
    return $users;
}

function chat_get_user_display(mysqli $conn, int $userId): string {
    $stmt = $conn->prepare("SELECT FullName FROM users WHERE UsersID = ? LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row && isset($row['FullName']) && $row['FullName'] !== '' ? (string)$row['FullName'] : ('User #' . $userId);
}

function chat_get_applicant_user_id(mysqli $conn, int $applicationId): ?int {
    $stmt = $conn->prepare("
        SELECT u.UsersID
        FROM job_applications ja
        INNER JOIN applicants a ON ja.ApplicantID = a.ApplicantID
        INNER JOIN users u ON a.UserID = u.UsersID
        WHERE ja.ApplicationID = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $applicationId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) return null;
    $uid = $row['UsersID'] ?? null;
    return $uid !== null ? (int)$uid : null;
}

function chat_find_or_create_thread(
    mysqli $conn,
    string $threadType,
    ?string $relatedModule,
    ?int $relatedRecord,
    ?int $createdBy,
    array $participantUserIds
): int {
    $threadId = 0;

    if ($relatedModule === null && $relatedRecord === null) {
        // Contact inbox threads
        $stmt = $conn->prepare("
            SELECT ThreadID FROM chat_threads
            WHERE ThreadType = ? AND RelatedModule IS NULL AND RelatedRecord IS NULL
            ORDER BY ThreadID DESC LIMIT 1
        ");
        $stmt->bind_param("s", $threadType);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row && isset($row['ThreadID'])) $threadId = (int)$row['ThreadID'];
        if ($threadId === 0) {
            $stmt3 = $conn->prepare("
                INSERT INTO chat_threads (ThreadType, RelatedModule, RelatedRecord, CreatedBy, UpdatedAt)
                VALUES (?, NULL, NULL, ?, NOW())
            ");
            $stmt3->bind_param("si", $threadType, $createdBy);
            $stmt3->execute();
            $threadId = (int)$conn->insert_id;
            $stmt3->close();
        }
    } else {
        // Event threads (application/job order, etc.)
        $stmt = $conn->prepare("
            SELECT ThreadID FROM chat_threads
            WHERE ThreadType = ? AND RelatedModule = ? AND RelatedRecord = ?
            ORDER BY ThreadID DESC LIMIT 1
        ");
        $relatedModuleSafe = $relatedModule ?? '';
        $relatedRecordSafe = $relatedRecord ?? 0;
        $stmt->bind_param("ssi", $threadType, $relatedModuleSafe, $relatedRecordSafe);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row && isset($row['ThreadID'])) $threadId = (int)$row['ThreadID'];
        if ($threadId === 0) {
            $stmt3 = $conn->prepare("
                INSERT INTO chat_threads (ThreadType, RelatedModule, RelatedRecord, CreatedBy, UpdatedAt)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt3->bind_param("ssii", $threadType, $relatedModuleSafe, $relatedRecordSafe, $createdBy);
            $stmt3->execute();
            $threadId = (int)$conn->insert_id;
            $stmt3->close();
        }
    }

    if (!empty($participantUserIds)) {
        $ins = $conn->prepare("INSERT IGNORE INTO chat_thread_participants (ThreadID, UserID) VALUES (?, ?)");
        foreach ($participantUserIds as $pid) {
            $pid = (int)$pid;
            $ins->bind_param("ii", $threadId, $pid);
            $ins->execute();
        }
        $ins->close();

        // Ensure a reads row exists for participants
        $readIns = $conn->prepare("INSERT IGNORE INTO chat_thread_reads (ThreadID, UserID, LastReadAt) VALUES (?, ?, NULL)");
        foreach ($participantUserIds as $pid) {
            $pid = (int)$pid;
            $readIns->bind_param("ii", $threadId, $pid);
            $readIns->execute();
        }
        $readIns->close();
    }

    return $threadId;
}

function chat_add_message(
    mysqli $conn,
    int $threadId,
    int $senderUserId,
    string $messageText,
    bool $isSystem = false
): int {
    $stmt = $conn->prepare("
        INSERT INTO chat_messages (ThreadID, SenderUserID, MessageText, IsSystemMessage)
        VALUES (?, ?, ?, ?)
    ");
    $isSys = $isSystem ? 1 : 0;
    $stmt->bind_param("iisi", $threadId, $senderUserId, $messageText, $isSys);
    $stmt->execute();
    $id = (int)$conn->insert_id;
    $stmt->close();
    $conn->query("UPDATE chat_threads SET UpdatedAt = NOW() WHERE ThreadID = " . (int)$threadId);
    return $id;
}

function chat_mark_thread_read(mysqli $conn,int $threadId,int $userId,?int $through=null):void {
 if($through===null){$q=$conn->prepare('SELECT COALESCE(MAX(MessageID),0) n FROM chat_messages WHERE ThreadID=?');$q->bind_param('i',$threadId);$q->execute();$through=(int)$q->get_result()->fetch_assoc()['n'];$q->close();}
 $q=$conn->prepare('INSERT INTO chat_thread_reads(ThreadID,UserID,LastReadAt,LastReadMessageID) VALUES(?,?,NOW(),?) ON DUPLICATE KEY UPDATE LastReadAt=NOW(),LastReadMessageID=GREATEST(LastReadMessageID,VALUES(LastReadMessageID))');$q->bind_param('iii',$threadId,$userId,$through);$q->execute();$q->close();
 // Opening messages also clears their corresponding existing global alerts.
 $q=$conn->prepare("UPDATE notifications SET IsRead=1 WHERE UserID=? AND RelateedModule='chat_thread' AND RelatedRecord=? AND NotificationType IN ('hr_message','messenger_message')");$q->bind_param('ii',$userId,$threadId);$q->execute();$q->close();
}

function chat_create_notification(
    mysqli $conn,
    int $userId,
    string $notificationType,
    string $title,
    string $message,
    ?string $relatedModule,
    ?int $relatedRecord,
    ?string $priority = null
): void {
    $stmt = $conn->prepare("
        INSERT INTO notifications (UserID, NotificationType, Title, Message, RelateedModule, RelatedRecord, Priority)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $rm = $relatedModule;
    $rr = $relatedRecord;
    $pri = $priority;
    // Priority can be NULL; binding as string allows NULL insertion in most MySQL setups.
    $stmt->bind_param("issssis", $userId, $notificationType, $title, $message, $rm, $rr, $pri);
    $stmt->execute();
    $stmt->close();
}

