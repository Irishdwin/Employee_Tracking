<?php
/**
 * Unread direct-message counts per other user (sender) for the logged-in viewer.
 *
 * @return array{total:int,by_contact:array<int,int>,threads:array<int,array{thread_id:int,unread_count:int}>}
 */
function messenger_get_unread_counts(mysqli $conn, int $viewerId): array
{
    $byContact = [];
    $threads = [];
    $total = 0;

    $stmt = $conn->prepare("
        SELECT
            t.ThreadID,
            t.RelatedModule,
            (
                SELECT COUNT(*)
                FROM chat_messages m
                WHERE m.ThreadID = t.ThreadID
                  AND m.SenderUserID <> ?
                  AND m.CreatedAt > COALESCE(
                        (
                            SELECT r.LastReadAt
                            FROM chat_thread_reads r
                            WHERE r.ThreadID = t.ThreadID AND r.UserID = ?
                        ),
                        '1970-01-01'
                  )
            ) AS unread_count
        FROM chat_threads t
        INNER JOIN chat_thread_participants p ON p.ThreadID = t.ThreadID AND p.UserID = ?
        WHERE t.ThreadType = 'DIRECT'
    ");
    if (!$stmt) {
        return ['total' => 0, 'by_contact' => [], 'threads' => []];
    }

    $stmt->bind_param('iii', $viewerId, $viewerId, $viewerId);
    if (!$stmt->execute()) {
        $stmt->close();
        return ['total' => 0, 'by_contact' => [], 'threads' => []];
    }

    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $unread = (int)($row['unread_count'] ?? 0);
        if ($unread <= 0) {
            continue;
        }
        $rm = (string)($row['RelatedModule'] ?? '');
        if (!preg_match('/^dm_(\d+)_(\d+)$/', $rm, $m)) {
            continue;
        }
        $a = (int)$m[1];
        $b = (int)$m[2];
        $otherId = ($a === $viewerId) ? $b : (($b === $viewerId) ? $a : 0);
        if ($otherId <= 0 || $otherId === $viewerId) {
            continue;
        }
        $byContact[$otherId] = ($byContact[$otherId] ?? 0) + $unread;
        $threads[$otherId] = [
            'thread_id' => (int)$row['ThreadID'],
            'unread_count' => $unread,
        ];
        $total += $unread;
    }
    $stmt->close();

    return [
        'total' => $total,
        'by_contact' => $byContact,
        'threads' => $threads,
    ];
}

/**
 * Mark a DIRECT thread with another user as read for the viewer.
 */
function messenger_mark_read_with_user(mysqli $conn, int $viewerId, int $otherUserId): ?int
{
    $pairKey = 'dm_' . min($viewerId, $otherUserId) . '_' . max($viewerId, $otherUserId);
    $stmt = $conn->prepare("
        SELECT t.ThreadID
        FROM chat_threads t
        INNER JOIN chat_thread_participants p ON p.ThreadID = t.ThreadID AND p.UserID = ?
        WHERE t.ThreadType = 'DIRECT' AND t.RelatedModule = ?
        LIMIT 1
    ");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('is', $viewerId, $pairKey);
    if (!$stmt->execute()) {
        $stmt->close();
        return null;
    }
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row || empty($row['ThreadID'])) {
        return null;
    }
    return (int)$row['ThreadID'];
}
