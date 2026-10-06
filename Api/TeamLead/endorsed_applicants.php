<?php
require_once __DIR__ . '/../Common/session_init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$role = trim((string)($_SESSION['role'] ?? ''));
if (!in_array($role, ['TeamLead', 'Team Lead'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit();
}

require __DIR__ . '/../Db_connection/db_connect.php';
require_once __DIR__ . '/../Helpers/application_endorsement.php';
try { ae_authorize($conn,(int)$_SESSION['user_id'],['TeamLead','Team Lead']); } catch (DomainException $e) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Forbidden']); exit; }

function json_resp($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit();
}

$userId = (int)$_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $sql = "SELECT ja.ApplicationID, ja.EndorsedDate, ja.ClientApprovalStatus, ja.Remarks,
                   a.FullName AS ApplicantName, a.Email,
                   COALESCE(jod.position_title,jo.Position) Position, c.Name AS CompanyName, ea.AttemptID, ea.DecisionReason, ja.HiredDate, s.Name AgencyStatus
            FROM job_applications ja
            INNER JOIN applicants a ON ja.ApplicantID = a.ApplicantID
            INNER JOIN joborder jo ON ja.JobOrderID = jo.JobOrderID
            INNER JOIN clientcompany c ON jo.CompanyID = c.CompanyID
            LEFT JOIN job_order_details jod ON jod.detail_id=ja.target_detail_id AND jod.JobOrderID=ja.JobOrderID
            LEFT JOIN status s ON s.StatusID=ja.StatusID
            LEFT JOIN application_endorsement_attempts ea ON ea.ApplicationID=ja.ApplicationID AND ea.AttemptNumber=(SELECT MAX(h.AttemptNumber) FROM application_endorsement_attempts h WHERE h.ApplicationID=ja.ApplicationID)
            WHERE ja.EndorsedDate IS NOT NULL
              AND (
                    EXISTS (
                        SELECT 1
                        FROM deployment d
                        WHERE d.CompanyID = jo.CompanyID
                          AND d.SiteSuperVisorID = ?
                    )
                    OR EXISTS (
                        SELECT 1
                        FROM clientcompany cc
                        WHERE cc.CompanyID = jo.CompanyID
                          AND cc.UserID = ?
                    )
              )
            ORDER BY ja.EndorsedDate DESC, ja.ApplicationID DESC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) json_resp(['success' => false, 'message' => 'Failed to prepare query.'], 500);
    $stmt->bind_param("ii", $userId, $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $rows[] = [
            'application_id' => (int)$r['ApplicationID'],
            'applicant_name' => $r['ApplicantName'] ?? '',
            'email' => $r['Email'] ?? '',
            'position' => $r['Position'] ?? '',
            'company_name' => $r['CompanyName'] ?? '',
            'endorsed_date' => $r['EndorsedDate'],
            'client_status' => $r['ClientApprovalStatus'] ?? 'Pending',
            'remarks' => $r['DecisionReason'] ?? '',
            'attempt_id'=>(int)($r['AttemptID']??0),
            'can_decide'=>empty($r['HiredDate']) && !in_array($r['AgencyStatus'],['Rejected','Declined','Failed'],true) && $r['ClientApprovalStatus']==='Pending',
        ];
    }
    $stmt->close();

    json_resp(['success' => true, 'applications' => $rows]);
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) $input = $_POST;

    $appId = (int)($input['application_id'] ?? 0);
    $decision = trim((string)($input['decision'] ?? ''));
    $comment = trim((string)($input['comment'] ?? ''));

    if ($appId <= 0) json_resp(['success' => false, 'message' => 'Invalid application ID.'], 400);
    if ($decision !== 'Approved' && $decision !== 'Rejected') {
        json_resp(['success' => false, 'message' => 'Decision must be Approved or Rejected.'], 400);
    }

    try {
        ae_decide($conn,$userId,$appId,(int)($input['attempt_id']??0),$decision,$comment);
    } catch (DomainException $e) { json_resp(['success'=>false,'message'=>$e->getMessage()],$e->getCode()?:400); }
    catch (Throwable $e) { error_log('Client decision: '.$e->getMessage()); json_resp(['success'=>false,'message'=>'Could not save decision. Please try again.'],500); }

    json_resp(['success' => true, 'message' => 'Decision saved.']);
}

json_resp(['success' => false, 'message' => 'Invalid request method.'], 405);
