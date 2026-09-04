<?php
/**
 * Create a notification for an applicant when HR updates their application status.
 * @param mysqli $conn Database connection
 * @param int $applicationId job_applications.ApplicationID
 * @param string $type NotificationType (e.g. 'application_status')
 * @param string $title Short title
 * @param string $message Full message
 * @return bool Success
 */
function createApplicationNotification($conn, $applicationId, $type, $title, $message) {
    $stmt = $conn->prepare("
        SELECT a.UserID FROM applicants a
        INNER JOIN job_applications ja ON ja.ApplicantID = a.ApplicantID
        WHERE ja.ApplicationID = ? AND a.UserID IS NOT NULL
        LIMIT 1
    ");
    $stmt->bind_param("i", $applicationId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) return false;

    $userId = (int)$row['UserID'];
    $ins = $conn->prepare("
        INSERT INTO notifications (UserID, NotificationType, Title, Message, RelateedModule, RelatedRecord)
        VALUES (?, ?, ?, ?, 'job_application', ?)
    ");
    $ins->bind_param("isssi", $userId, $type, $title, $message, $applicationId);
    $ok = $ins->execute();
    $ins->close();
    return $ok;
}
