<?php
/**
 * Insert into `notifications` (shared by Admin/HR/TeamLead/Applicant flows).
 */
function insert_system_notification(
    mysqli $conn,
    int $toUserId,
    string $notificationType,
    string $title,
    string $message,
    ?string $relatedModule = null,
    ?int $relatedRecordId = null
): bool {
    if ($toUserId <= 0) {
        return false;
    }
    $mod = $relatedModule !== null && $relatedModule !== '' ? $relatedModule : 'system';

    if ($relatedRecordId !== null && $relatedRecordId > 0) {
        $stmt = $conn->prepare(
            'INSERT INTO notifications (UserID, NotificationType, Title, Message, RelateedModule, RelatedRecord, IsRead, Priority)
             VALUES (?, ?, ?, ?, ?, ?, 0, ?)'
        );
        if (!$stmt) {
            return false;
        }
        $pri = 'Normal';
        $stmt->bind_param('issssis', $toUserId, $notificationType, $title, $message, $mod, $relatedRecordId, $pri);
    } else {
        $stmt = $conn->prepare(
            'INSERT INTO notifications (UserID, NotificationType, Title, Message, RelateedModule, RelatedRecord, IsRead, Priority)
             VALUES (?, ?, ?, ?, ?, NULL, 0, ?)'
        );
        if (!$stmt) {
            return false;
        }
        $pri = 'Normal';
        $stmt->bind_param('isssss', $toUserId, $notificationType, $title, $message, $mod, $pri);
    }

    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}
