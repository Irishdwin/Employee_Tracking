<?php
/**
 * Service Agreement upload — persist notifications + WebSocket envelope.
 *
 * Database (existing):
 *   notifications (
 *     NotificationID, UserID, NotificationType, Title, Message,
 *     RelateedModule, RelatedRecord, IsRead, Priority
 *   )
 *
 * Optional future metadata column:
 *   ALTER TABLE notifications ADD COLUMN MetadataJSON JSON NULL;
 */

require_once __DIR__ . '/../Common/NotificationHelper.php';

/** Roles that receive SERVICE_AGREEMENT_UPLOADED alerts. */
function sa_notify_allowed_roles(): array
{
    return ['Admin', 'HR', 'TeamLead'];
}

function sa_normalize_date_ymd(?string $date): string
{
    $date = trim((string)$date);
    if ($date === '') {
        return '';
    }
    $ts = strtotime($date);
    if ($ts === false) {
        return $date;
    }
    return date('Y-m-d', $ts);
}

function sa_build_agreement_copy(string $documentName, string $startDate, string $expirationDate): array
{
    $start = sa_normalize_date_ymd($startDate);
    $end = sa_normalize_date_ymd($expirationDate);
    if ($end === '') {
        $end = 'Ongoing';
    }

    $title = 'New Active Service Agreement';
    $message = sprintf(
        'Agreement %s is now active. Contract duration: %s to %s.',
        $documentName,
        $start !== '' ? $start : '—',
        $end
    );

    return [
        'title' => $title,
        'message' => $message,
        'document_name' => $documentName,
        'start_date' => $start,
        'expiration_date' => $end,
    ];
}

/**
 * Insert inbox rows + build real-time broadcast envelope.
 *
 * @return array{
 *   notified_count:int,
 *   recipients:array<int,array{user_id:int,role:string,notification_id:int}>,
 *   websocket_broadcast:array
 * }
 */
function notify_service_agreement_uploaded(
    mysqli $conn,
    int $serviceAgreementId,
    string $documentName,
    string $startDate,
    ?string $expirationDate = null,
    ?string $companyName = null
): array {
    $copy = sa_build_agreement_copy($documentName, $startDate, $expirationDate ?? '');
    $allowedRoles = sa_notify_allowed_roles();

    $placeholders = implode(',', array_fill(0, count($allowedRoles), '?'));
    $types = str_repeat('s', count($allowedRoles));

    $sql = "
        SELECT u.UsersID AS user_id, r.RoleName AS role
        FROM users u
        INNER JOIN roles r ON r.RolesID = u.RoleID
        WHERE u.IsActive = 1 AND r.RoleName IN ($placeholders)
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [
            'notified_count' => 0,
            'recipients' => [],
            'websocket_broadcast' => sa_websocket_envelope([], $copy, $serviceAgreementId, $companyName),
        ];
    }

    $stmt->bind_param($types, ...$allowedRoles);
    $stmt->execute();
    $result = $stmt->get_result();

    $recipients = [];
    $recipientPayloads = [];

    while ($row = $result->fetch_assoc()) {
        $uid = (int)$row['user_id'];
        $role = (string)$row['role'];

        $inserted = insert_system_notification(
            $conn,
            $uid,
            'service_agreement_uploaded',
            $copy['title'],
            $copy['message'],
            'service_agreement',
            $serviceAgreementId > 0 ? $serviceAgreementId : null
        );

        if (!$inserted) {
            continue;
        }

        $notificationId = (int)$conn->insert_id;
        $recipients[] = [
            'user_id' => $uid,
            'role' => $role,
            'notification_id' => $notificationId,
        ];

        $recipientPayloads[] = [
            'user_id' => $uid,
            'role' => $role,
            'notification_id' => $notificationId,
            'payload' => sa_payload_for_user($notificationId, $copy, $serviceAgreementId, $companyName),
        ];
    }
    $stmt->close();

    return [
        'notified_count' => count($recipients),
        'recipients' => $recipients,
        'websocket_broadcast' => sa_websocket_envelope($recipientPayloads, $copy, $serviceAgreementId, $companyName),
    ];
}

function sa_payload_for_user(
    int $notificationId,
    array $copy,
    int $serviceAgreementId,
    ?string $companyName
): array {
    return [
        'id' => $notificationId,
        'notification_id' => $notificationId,
        'type' => 'service_agreement_uploaded',
        'title' => $copy['title'],
        'message' => $copy['message'],
        'document_name' => $copy['document_name'],
        'start_date' => $copy['start_date'],
        'expiration_date' => $copy['expiration_date'],
        'company_name' => $companyName ?? '',
        'service_agreement_id' => $serviceAgreementId,
        'from' => 'System',
        'is_read' => false,
    ];
}

function sa_websocket_envelope(
    array $recipientPayloads,
    array $copy,
    int $serviceAgreementId,
    ?string $companyName
): array {
    return [
        'type' => 'system_notification',
        'event' => 'SERVICE_AGREEMENT_UPLOADED',
        'legacy_event' => 'service_agreement_uploaded',
        'roles' => sa_notify_allowed_roles(),
        'recipient_payloads' => $recipientPayloads,
        'payload' => [
            'title' => $copy['title'],
            'message' => $copy['message'],
            'document_name' => $copy['document_name'],
            'start_date' => $copy['start_date'],
            'expiration_date' => $copy['expiration_date'],
            'company_name' => $companyName ?? '',
            'service_agreement_id' => $serviceAgreementId,
            'type' => 'service_agreement_uploaded',
            'from' => 'System',
            'is_read' => false,
        ],
    ];
}
