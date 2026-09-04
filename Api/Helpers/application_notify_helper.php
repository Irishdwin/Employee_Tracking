<?php
/**
 * Notify Admin + HR when an applicant submits a job application.
 */
require_once __DIR__ . '/../Common/NotificationHelper.php';

function ja_notify_allowed_roles(): array
{
    return ['Admin', 'HR'];
}

/**
 * @return array{notified_count:int,recipients:array<int,array{user_id:int,role:string,notification_id:int}>}
 */
function notify_new_job_application(
    mysqli $conn,
    int $applicationId,
    string $applicantName,
    string $positionTitle,
    string $companyName,
    ?string $deploymentSite = null
): array {
    $applicantName = trim($applicantName) !== '' ? trim($applicantName) : 'An applicant';
    $positionTitle = trim($positionTitle) !== '' ? trim($positionTitle) : 'a role';
    $companyName = trim($companyName) !== '' ? trim($companyName) : 'a client company';
    $siteSuffix = ($deploymentSite !== null && trim($deploymentSite) !== '')
        ? ' · Site: ' . trim($deploymentSite)
        : '';

    $title = 'New Job Application';
    $message = sprintf(
        '%s applied for %s at %s%s.',
        $applicantName,
        $positionTitle,
        $companyName,
        $siteSuffix
    );

    $allowedRoles = ja_notify_allowed_roles();
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
        return ['notified_count' => 0, 'recipients' => []];
    }

    $stmt->bind_param($types, ...$allowedRoles);
    $stmt->execute();
    $result = $stmt->get_result();

    $recipients = [];
    while ($row = $result->fetch_assoc()) {
        $userId = (int)$row['user_id'];
        if ($userId <= 0) {
            continue;
        }
        $ok = insert_system_notification(
            $conn,
            $userId,
            'job_application_submitted',
            $title,
            $message,
            'job_application',
            $applicationId
        );
        if ($ok) {
            $recipients[] = [
                'user_id' => $userId,
                'role' => (string)$row['role'],
                'notification_id' => (int)$conn->insert_id,
            ];
        }
    }
    $stmt->close();

    return [
        'notified_count' => count($recipients),
        'recipients' => $recipients,
    ];
}
