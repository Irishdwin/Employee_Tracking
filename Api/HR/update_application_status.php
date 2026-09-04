<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$allowedRoles = ['Admin', 'HR'];
if (!in_array($_SESSION['role'], $allowedRoles)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit();
}

require __DIR__ . '/../Db_connection/db_connect.php';
require __DIR__ . '/../Helpers/notification_helper.php';
require __DIR__ . '/../Helpers/chat_helper.php';
require_once __DIR__ . '/../Helpers/AuditLogger.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$applicationId = isset($input['application_id']) ? (int)$input['application_id'] : 0;
$statusName = isset($input['status']) ? trim($input['status']) : '';

if ($applicationId <= 0 || $statusName === '') {
    echo json_encode(['success' => false, 'message' => 'Application ID and status are required']);
    exit();
}

// Get status ID
$statusStmt = $conn->prepare("SELECT StatusID FROM status WHERE Name = ? LIMIT 1");
$statusStmt->bind_param("s", $statusName);
$statusStmt->execute();
$statusResult = $statusStmt->get_result();
$statusRow = $statusResult->fetch_assoc();
$statusStmt->close();

if (!$statusRow) {
    // Create status if missing
    $createStmt = $conn->prepare("INSERT INTO status (Name) VALUES (?)");
    $createStmt->bind_param("s", $statusName);
    $createStmt->execute();
    $statusId = $conn->insert_id;
    $createStmt->close();
} else {
    $statusId = (int)$statusRow['StatusID'];
}

// Update application
$updateStmt = $conn->prepare("UPDATE job_applications SET StatusID = ? WHERE ApplicationID = ?");
$updateStmt->bind_param("ii", $statusId, $applicationId);

if ($updateStmt->execute()) {
    $updateStmt->close();
    createApplicationNotification($conn, $applicationId, 'application_status', 'Application Status Updated', 'Your application status has been updated to: ' . $statusName);

    // Audit Log
    $appNameQuery = $conn->query("
        SELECT COALESCE(a.FullName, CONCAT(a.FirstName, ' ', a.LastName)) AS app_name 
        FROM job_applications ja 
        LEFT JOIN applicants a ON ja.ApplicantID = a.ApplicantID 
        WHERE ja.ApplicationID = " . (int)$applicationId
    );
    $appName = ($appNameQuery && $r = $appNameQuery->fetch_assoc()) ? $r['app_name'] : "Application #{$applicationId}";
    AuditLogger::log($conn, 'Applicant', 'Update Status', "Updated application status of {$appName} to '{$statusName}'.", (string)$applicationId);

    // Post status update to HR/applicant chat thread for this application
    chat_ensure_tables($conn);
    $actorUserId = (int)$_SESSION['user_id'];
    $applicantUserId = chat_get_applicant_user_id($conn, $applicationId);
    if ($applicantUserId) {
        $hrUsers = chat_get_active_users_by_role($conn, 'HR');
        $participantIds = [(int)$applicantUserId, $actorUserId];
        foreach ($hrUsers as $u) $participantIds[] = (int)$u['user_id'];
        $participantIds = array_values(array_unique($participantIds));

        $threadId = chat_find_or_create_thread(
            $conn,
            'APPLICATION_EVENT',
            'job_application',
            $applicationId,
            $actorUserId,
            $participantIds
        );

        $chatText = 'Application status updated to: ' . $statusName . '.';
        chat_add_message($conn, $threadId, $actorUserId, $chatText, true);
        chat_mark_thread_read($conn, $threadId, $actorUserId);
    }
    echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
} else {
    $updateStmt->close();
    echo json_encode(['success' => false, 'message' => 'Failed to update status: ' . $conn->error]);
}
?>