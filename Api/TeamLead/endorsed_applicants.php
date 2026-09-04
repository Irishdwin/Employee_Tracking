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
require __DIR__ . '/../Helpers/notification_helper.php';

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
                   jo.Position, c.Name AS CompanyName
            FROM job_applications ja
            INNER JOIN applicants a ON ja.ApplicantID = a.ApplicantID
            INNER JOIN joborder jo ON ja.JobOrderID = jo.JobOrderID
            INNER JOIN clientcompany c ON jo.CompanyID = c.CompanyID
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
            'remarks' => $r['Remarks'] ?? '',
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

    // Ensure this application belongs to a company supervised by current team lead.
    $checkSql = "SELECT ja.ApplicationID
                 FROM job_applications ja
                 INNER JOIN joborder jo ON ja.JobOrderID = jo.JobOrderID
                 WHERE ja.ApplicationID = ?
                   AND (
                        EXISTS (
                            SELECT 1 FROM deployment d
                            WHERE d.CompanyID = jo.CompanyID
                              AND d.SiteSuperVisorID = ?
                        )
                        OR EXISTS (
                            SELECT 1 FROM clientcompany cc
                            WHERE cc.CompanyID = jo.CompanyID
                              AND cc.UserID = ?
                        )
                   )
                 LIMIT 1";
    $check = $conn->prepare($checkSql);
    if (!$check) json_resp(['success' => false, 'message' => 'Failed to validate application.'], 500);
    $check->bind_param("iii", $appId, $userId, $userId);
    $check->execute();
    if ($check->get_result()->num_rows === 0) {
        $check->close();
        json_resp(['success' => false, 'message' => 'Application not found for your assigned sites.'], 404);
    }
    $check->close();

    $upd = $conn->prepare("UPDATE job_applications
                           SET ClientApprovalStatus = ?, ClientApprovalDate = NOW(),
                               Remarks = CASE
                                   WHEN ? = '' THEN Remarks
                                   WHEN Remarks IS NULL OR TRIM(Remarks) = '' THEN ?
                                   ELSE CONCAT(Remarks, '\n', ?)
                               END
                           WHERE ApplicationID = ?");
    if (!$upd) json_resp(['success' => false, 'message' => 'Failed to save decision.'], 500);
    $upd->bind_param("ssssi", $decision, $comment, $comment, $comment, $appId);
    if (!$upd->execute()) {
        $upd->close();
        json_resp(['success' => false, 'message' => 'Failed to save decision.'], 500);
    }
    $upd->close();

    if ($decision === 'Approved') {
        createApplicationNotification(
            $conn,
            $appId,
            'client_approved',
            'Approved by Company',
            'Your application has been approved by the client company.'
        );
    } elseif ($decision === 'Rejected') {
        createApplicationNotification(
            $conn,
            $appId,
            'client_rejected',
            'Declined',
            'Your application was not approved by the client company.'
        );
    }

    json_resp(['success' => true, 'message' => 'Decision saved.']);
}

json_resp(['success' => false, 'message' => 'Invalid request method.'], 405);
