<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Allow Admin, HR, and TeamLead roles to inspect application details
$allowedRoles = ['Admin', 'HR', 'TeamLead'];
if (!in_array($_SESSION['role'], $allowedRoles)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Forbidden']);
    exit();
}

require __DIR__ . '/../Db_connection/db_connect.php';
require __DIR__ . '/../Helpers/application_status_helper.php';

header('Content-Type: application/json');

$applicationId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($applicationId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid application ID']);
    exit();
}

$stmt = $conn->prepare("
    SELECT 
        ja.ApplicationID,
        ja.ApplicantID,
        a.FullName AS ApplicantName,
        a.Email,
        a.MobileNumber,
        a.Address,
        a.BirthDate,
        jo.Position,
        c.Name AS CompanyName,
        ja.ApplicationDate,
        ja.InterviewDate,
        ja.InterviewNotes,
        ja.EndorsedDate,
        ja.ClientApprovalStatus,
        ja.HiredDate,
        ja.ClientApprovalDate,
        ja.Remarks,
        COALESCE(s.Name, 'Pending') AS StatusFromTable
    FROM job_applications ja
    INNER JOIN applicants a ON ja.ApplicantID = a.ApplicantID
    INNER JOIN joborder jo ON ja.JobOrderID = jo.JobOrderID
    INNER JOIN clientcompany c ON jo.CompanyID = c.CompanyID
    LEFT JOIN status s ON ja.StatusID = s.StatusID
    WHERE ja.ApplicationID = ?
");
$stmt->bind_param("i", $applicationId);
$stmt->execute();
$result = $stmt->get_result();
$application = $result->fetch_assoc();
$stmt->close();

if (!$application) {
    echo json_encode(['success' => false, 'message' => 'Application not found']);
    exit();
}

$displayStatus = ash_resolve_display_status($application);
$workflowStage = ash_resolve_stage($application);
$canSimulateClientApproval = (
    !empty($application['EndorsedDate'])
    && empty($application['HiredDate'])
    && trim((string)($application['ClientApprovalStatus'] ?? '')) === 'Pending'
);

// Get documents
$docStmt = $conn->prepare("
    SELECT DocID, DocType, FilePath, OriginalFileName, IsVerified
    FROM applicant_uploaded_docs
    WHERE (ApplicationID = ?)
       OR (ApplicationID IS NULL AND ApplicantID = ?)
    ORDER BY UploadedAt DESC
");
$docStmt->bind_param("ii", $applicationId, $application['ApplicantID']);
$docStmt->execute();
$docResult = $docStmt->get_result();
$documents = [];

while ($row = $docResult->fetch_assoc()) {
    $documents[] = [
        'doc_id' => (int)$row['DocID'],
        'doc_type' => $row['DocType'],
        'file_path' => $row['FilePath'],
        'original_filename' => $row['OriginalFileName'],
        'is_verified' => (bool)$row['IsVerified']
    ];
}
$docStmt->close();

echo json_encode([
    'success' => true,
    'application_id' => (int)$application['ApplicationID'],
    'applicant_name' => $application['ApplicantName'],
    'email' => $application['Email'],
    'mobile' => $application['MobileNumber'],
    'address' => $application['Address'],
    'birth_date' => $application['BirthDate'],
    'position' => $application['Position'],
    'company_name' => $application['CompanyName'],
    'application_date' => date('M d, Y', strtotime($application['ApplicationDate'])),
    'interview_date' => $application['InterviewDate'] ? date('M d, Y H:i', strtotime($application['InterviewDate'])) : null,
    'interview_notes' => $application['InterviewNotes'],
    'endorsed_date' => $application['EndorsedDate'] ? date('M d, Y', strtotime($application['EndorsedDate'])) : null,
    'client_approval_status' => $application['ClientApprovalStatus'],
    'status' => $displayStatus,
    'status_badge' => ash_admin_badge_bootstrap($displayStatus),
    'workflow_stage' => $workflowStage,
    'can_simulate_client_approval' => $canSimulateClientApproval,
    'timeline' => ash_build_timeline($application),
    'hired_date' => $application['HiredDate'],
    'is_hired' => !empty($application['HiredDate']),
    'contract_uploaded' => (strpos((string)($application['Remarks'] ?? ''), 'Contract uploaded:') !== false),
    'can_hire' => (
        ($application['ClientApprovalStatus'] ?? '') === 'Approved'
        && empty($application['HiredDate'])
        && strpos((string)($application['Remarks'] ?? ''), 'Contract uploaded:') !== false
    ),
    'documents' => $documents
]);
?>