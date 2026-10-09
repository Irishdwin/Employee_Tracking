<?php
require_once __DIR__.'/../Common/session_init.php';

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
require_once __DIR__ . '/../Helpers/application_endorsement.php';
try { ae_authorize($conn,(int)$_SESSION['user_id'],['Admin','HR','TeamLead']); } catch (DomainException $e) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Forbidden']); exit; }

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
        jo.CompanyID,
        a.FullName AS ApplicantName,
        a.Email,
        a.MobileNumber,
        a.Address,
        a.BirthDate,
        COALESCE(jod.position_title,jo.Position) Position,
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
    LEFT JOIN job_order_details jod ON jod.detail_id=ja.target_detail_id AND jod.JobOrderID=ja.JobOrderID
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

if ($_SESSION['role']==='TeamLead' && !ae_tl_scope($conn,(int)$_SESSION['user_id'],(int)$application['CompanyID'])) {
    http_response_code(403); echo json_encode(['success'=>false,'message'=>'Application is outside your assigned companies.']); exit;
}
$attempts=ae_history($conn,$applicationId);
// Internal reasons and other companies' reviews are available only to HR/Admin.
if ($_SESSION['role']==='TeamLead') $attempts=array_values(array_filter($attempts,fn($a)=>(int)$a['CompanyID']===(int)$application['CompanyID']));
$application['EndorsementAttempts']=$attempts;
$last=$attempts?end($attempts):null;
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
        'file_path' => preg_match('/^(resume|cv|resume\s*\/\s*cv)$/i',$row['DocType']) ? '../../Api/Applicant/view_document.php?id='.(int)$row['DocID'] : $row['FilePath'],
        'original_filename' => $row['OriginalFileName'],
        'is_verified' => (bool)$row['IsVerified']
    ];
}
$docStmt->close();

echo json_encode([
    'success' => true,
    'application_id' => (int)$application['ApplicationID'],
    'endorsement_history' => $attempts,
    'attempt_id' => $last ? (int)$last['AttemptID'] : null,
    'can_reendorse' => in_array($_SESSION['role'],['Admin','HR'],true) && ae_can_reendorse($application),
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