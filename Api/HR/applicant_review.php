<?php
require_once __DIR__ . '/../Common/session_init.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$role = trim((string)$_SESSION['role']);
if (!in_array(strtoupper($role), ['ADMIN', 'HR'], true)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit();
}

require __DIR__ . '/../Db_connection/db_connect.php';
require_once __DIR__ . '/../Helpers/application_endorsement.php';
try { ae_authorize($conn,(int)$_SESSION['user_id'],['Admin','HR']); } catch (DomainException $e) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Forbidden']); exit; }

header('Content-Type: application/json');

if(isset($_GET['status']) && (!is_scalar($_GET['status']) || strlen((string)$_GET['status'])>100)) { http_response_code(422); echo json_encode(['success'=>false,'message'=>'Invalid status filter.']); exit; }
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';

$sql = "
    SELECT 
        ja.ApplicationID, ja.ApplicantID,
        ja.StatusID,
        a.FullName AS ApplicantName,
        a.Email,
        a.MobileNumber,
        COALESCE(jod.position_title,jo.Position) Position,
        c.Name AS CompanyName,
        ja.ApplicationDate,
        ja.InterviewDate,
        ja.EndorsedDate,
        ja.ClientApprovalStatus,
        ja.ClientApprovalDate,
        ja.HiredDate,
        COALESCE(s.Name, 'Pending') AS StatusFromTable,
        (
            SELECT COUNT(DISTINCT d.DocID)
            FROM applicant_uploaded_docs d
            WHERE d.ApplicationID = ja.ApplicationID
               OR (d.ApplicationID IS NULL AND d.ApplicantID = ja.ApplicantID)
        ) AS DocCount
    FROM job_applications ja
    INNER JOIN applicants a ON ja.ApplicantID = a.ApplicantID
    INNER JOIN joborder jo ON ja.JobOrderID = jo.JobOrderID
    INNER JOIN clientcompany c ON jo.CompanyID = c.CompanyID
    LEFT JOIN job_order_details jod ON jod.detail_id=ja.target_detail_id AND jod.JobOrderID=ja.JobOrderID
    LEFT JOIN status s ON ja.StatusID = s.StatusID
    WHERE 1=1
";

if ($statusFilter !== 'Hired') {
    $sql .= ' AND ja.HiredDate IS NULL';
}

$bindTypes = '';
$bindValues = [];

if ($statusFilter !== '') {
    if ($statusFilter === 'Applied' || $statusFilter === 'Pending') {
        $sql .= " AND ja.HiredDate IS NULL
                  AND ja.ClientApprovalStatus NOT IN ('Approved', 'Rejected')
                  AND ja.EndorsedDate IS NULL
                  AND ja.InterviewDate IS NULL
                  AND (ja.StatusID IS NULL OR s.Name IN ('Pending'))";
    } elseif ($statusFilter === 'Scheduled for Interview' || $statusFilter === 'For Interview') {
        $sql .= " AND ja.HiredDate IS NULL
                  AND ja.EndorsedDate IS NULL
                  AND ja.ClientApprovalStatus NOT IN ('Approved', 'Rejected')
                  AND (ja.InterviewDate IS NOT NULL OR s.Name IN ('For Interview', 'Scheduled for Interview'))";
    } elseif ($statusFilter === 'Endorsed to Client' || $statusFilter === 'Endorsed') {
        $sql .= " AND ja.HiredDate IS NULL
                  AND ja.EndorsedDate IS NOT NULL
                  AND ja.ClientApprovalStatus = 'Pending'";
    } elseif ($statusFilter === 'Approved by Company' || $statusFilter === 'Approved') {
        $sql .= " AND ja.HiredDate IS NULL AND ja.ClientApprovalStatus = 'Approved'";
    } elseif ($statusFilter === 'Rejected by Client') {
        $sql .= " AND ja.ClientApprovalStatus = 'Rejected' AND COALESCE(s.Name,'') NOT IN ('Rejected','Declined','Failed')";
    } elseif ($statusFilter === 'Rejected' || $statusFilter === 'Declined') {
        $sql .= " AND s.Name IN ('Rejected','Declined','Failed')";
    } elseif ($statusFilter === 'Hired') {
        $sql .= ' AND ja.HiredDate IS NOT NULL';
    } else {
        $sql .= ' AND COALESCE(s.Name, \'Pending\') = ?';
        $bindTypes .= 's';
        $bindValues[] = $statusFilter;
    }
}

$sql .= ' ORDER BY ja.ApplicationDate DESC';
require_once __DIR__ . '/../Helpers/list_query_page.php';
[$sql,$bindTypes,$bindValues,$pagination]=list_query_page($conn,$sql,['ApplicationID','ApplicantName','Email','MobileNumber','Position','CompanyName','ApplicantID','StatusFromTable'],['company'=>'CompanyName','position'=>'Position'],$bindTypes,$bindValues);

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to prepare query.']);
    exit();
}

if ($bindTypes !== '') {
    $stmt->bind_param($bindTypes, ...$bindValues);
}

$stmt->execute();
$result = $stmt->get_result();

$applications = [];
while ($row = $result->fetch_assoc()) {
    $label = ash_resolve_display_status($row);
    $applications[] = [
        'application_id'   => (int)$row['ApplicationID'],
        'applicant_name'   => $row['ApplicantName'],
        'email'            => $row['Email'],
        'mobile'           => $row['MobileNumber'],
        'position'         => $row['Position'],
        'company_name'     => $row['CompanyName'],
        'application_date' => date('M d, Y', strtotime($row['ApplicationDate'])),
        'status'           => $label,
        'can_reendorse' => ae_can_reendorse($row),
        'status_badge'     => ash_admin_badge_bootstrap($label),
        'doc_count'        => (int)$row['DocCount'],
        'document_count'   => (int)$row['DocCount'],
    ];
}
$stmt->close();

echo json_encode([
    'success'      => true,
    'applications' => $applications,
    'pagination' => $pagination,
]);
