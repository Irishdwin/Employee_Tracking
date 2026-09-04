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
require __DIR__ . '/../Helpers/application_status_helper.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$applicationId = isset($input['application_id']) ? (int)$input['application_id'] : 0;
$interviewDate = isset($input['interview_date']) ? trim($input['interview_date']) : '';

if ($applicationId <= 0 || $interviewDate === '') {
    echo json_encode(['success' => false, 'message' => 'Application ID and interview date are required']);
    exit();
}

// Canonical workflow status for interview scheduling
$statusId = ash_get_or_create_status_id($conn, 'Scheduled for Interview', ['For Interview']);
if (!$statusId) {
    echo json_encode(['success' => false, 'message' => 'Could not resolve interview status.']);
    exit();
}

// Update application
$updateStmt = $conn->prepare("
    UPDATE job_applications 
    SET InterviewDate = ?, StatusID = ?
    WHERE ApplicationID = ?
");
$updateStmt->bind_param("sii", $interviewDate, $statusId, $applicationId);

if ($updateStmt->execute()) {
    $updateStmt->close();
    createApplicationNotification($conn, $applicationId, 'interview_scheduled', 'Scheduled for Interview', 'An interview has been scheduled for your application on ' . date('M d, Y \a\t g:i A', strtotime($interviewDate)));

    // Also post to HR/applicant chat thread for this application
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

        $chatText = 'Interview scheduled for your application on ' . $interviewDate . ' (status: Scheduled for Interview).';
        chat_add_message($conn, $threadId, $actorUserId, $chatText, true);
        chat_mark_thread_read($conn, $threadId, $actorUserId);
    }
    echo json_encode(['success' => true, 'message' => 'Interview scheduled successfully', 'status' => 'Scheduled for Interview']);
} else {
    $updateStmt->close();
    echo json_encode(['success' => false, 'message' => 'Failed to schedule interview: ' . $conn->error]);
}
?>
