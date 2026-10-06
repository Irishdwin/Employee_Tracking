<?php
session_start();

require_once __DIR__ . '/../Common/session_init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Applicant') {
    echo json_encode(['success' => false, 'logged_in' => false, 'message' => 'Not signed in as an applicant.']);
    exit();
}

require __DIR__ . '/../Db_connection/db_connect.php';
require_once __DIR__ . '/../Helpers/application_endorsement.php';

$userId = (int)$_SESSION['user_id'];

$appStmt = $conn->prepare('SELECT ApplicantID, FullName FROM applicants WHERE UserID = ? LIMIT 1');
$appStmt->bind_param('i', $userId);
$appStmt->execute();
$appStmt->bind_result($applicantId, $fullName);
$appStmt->fetch();
$appStmt->close();

if (!$applicantId) {
    echo json_encode(['success' => false, 'logged_in' => true, 'message' => 'Applicant profile not found.']);
    exit();
}

$sql = "
    SELECT
        ja.ApplicationID,
        ja.JobOrderID,
        COALESCE(jod.position_title,jo.Position) Position,
        c.Name AS CompanyName,
        COALESCE(jod.deployment_site,jo.Location) Location,
        COALESCE(jod.work_schedule,jo.WorkSchedule) WorkSchedule,
        jo.RequestDate,
        jo.Qualifications,
        jo.RequiredSkills,
        ja.ApplicationDate,
        ja.EndorsedDate,
        ja.InterviewDate,
        ja.InterviewNotes,
        ja.ClientApprovalDate,
        ja.ClientApprovalStatus,
        ja.HiredDate,
        COALESCE(s.Name, 'Pending') AS StatusFromTable
    FROM job_applications ja
    INNER JOIN joborder jo ON ja.JobOrderID = jo.JobOrderID
    INNER JOIN clientcompany c ON jo.CompanyID = c.CompanyID
    LEFT JOIN job_order_details jod ON jod.detail_id=ja.target_detail_id AND jod.JobOrderID=ja.JobOrderID
    LEFT JOIN status s ON ja.StatusID = s.StatusID
    WHERE ja.ApplicantID = ?
    ORDER BY ja.ApplicationDate DESC, ja.ApplicationID DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $applicantId);
$stmt->execute();
$result = $stmt->get_result();

$applications = [];

while ($row = $result->fetch_assoc()) {
    $stage = ash_resolve_stage($row);
    $badge = $stage==='client_rejected' ? 'Client Review Unsuccessful — For Reassignment' : ash_stage_display_label($stage);
    $row['EndorsementAttempts']=ae_history($conn,(int)$row['ApplicationID']);

    $applications[] = [
        'application_id' => (int)$row['ApplicationID'],
        'job_order_id' => (int)$row['JobOrderID'],
        'position' => (string)($row['Position'] ?? ''),
        'company_name' => (string)($row['CompanyName'] ?? ''),
        'location' => (string)($row['Location'] ?? ''),
        'work_schedule' => (string)($row['WorkSchedule'] ?? ''),
        'request_date' => ash_fmt_date($row['RequestDate'] ?? null, false),
        'application_date' => ash_fmt_date($row['ApplicationDate'] ?? null, false),
        'status' => $stage,
        'status_badge' => $badge,
        'status_badge_class' => ash_applicant_badge_bootstrap($badge),
        'workflow_step' => ash_workflow_step_index($badge),
        'timeline' => ash_build_timeline($row, true, true),
        'qualifications' => (string)($row['Qualifications'] ?? ''),
        'required_skills' => (string)($row['RequiredSkills'] ?? ''),
    ];
}

$stmt->close();

echo json_encode([
    'success' => true,
    'logged_in' => true,
    'applicant_name' => $fullName,
    'applications' => $applications,
]);

exit();