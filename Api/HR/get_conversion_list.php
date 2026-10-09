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
require_once __DIR__ . '/../Helpers/application_endorsement.php';
try { ae_authorize($conn,(int)$_SESSION['user_id'],['Admin','HR']); } catch (DomainException $e) { http_response_code(403); header('Content-Type: application/json'); echo json_encode(['success'=>false,'message'=>'Forbidden']); exit; }

header('Content-Type: application/json');
require_once __DIR__ . '/../Helpers/contract_expiry_helper.php';
employment_contract_open_after_six_months($conn);

function parseContractRemarks($remarks) {
    $result = [
        'contract_type' => null,
        'contract_years' => null,
        'contract_months' => null,
        'signed_date' => null,
        'expiry_date' => null,
        'uploaded_at' => null,
        'contract_path' => null,
        'contract_state' => null
    ];

    if (!$remarks || strpos($remarks, 'Contract uploaded:') === false) {
        return $result;
    }

    // Remarks format example:
    // Contract uploaded: type=Open Contract; years=2; months=3; signed_date=2026-03-01; uploaded_at=2026-03-23 12:34:56; path=../../uploads/employment_contracts/file.pdf
    if (preg_match('/type=([^;]+)/', $remarks, $m)) $result['contract_type'] = trim($m[1]);
    if (preg_match('/years=([0-9]+)/', $remarks, $m)) $result['contract_years'] = (int)$m[1];
    if (preg_match('/months=([0-9]+)/', $remarks, $m)) $result['contract_months'] = (int)$m[1];
    if (preg_match('/signed_date=([0-9]{4}-[0-9]{2}-[0-9]{2})/', $remarks, $m)) $result['signed_date'] = $m[1];
    if (preg_match('/expiry_date=([0-9]{4}-[0-9]{2}-[0-9]{2})/', $remarks, $m)) $result['expiry_date'] = $m[1];
    if (preg_match('/uploaded_at=([0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2})/', $remarks, $m)) $result['uploaded_at'] = $m[1];
    if (preg_match('/path=([^;\n]+)/', $remarks, $m)) $result['contract_path'] = trim($m[1]);

    $typeNormalized = strtolower((string)$result['contract_type']);
    if (strpos($typeNormalized, 'open') !== false) {
        $result['contract_state'] = 'open';
    } else if (!empty($result['expiry_date'])) {
        $today = date('Y-m-d');
        $result['contract_state'] = ($result['expiry_date'] < $today) ? 'expired' : 'expiring';
    } else {
        $result['contract_state'] = 'expiring';
    }

    return $result;
}

// Get applications that are approved by client and ready for conversion
$sql = "
    SELECT 
        ja.ApplicationID, ja.ApplicantID,
        a.FullName AS ApplicantName,
        COALESCE(jod.position_title,jo.Position) AS Position,
        c.Name AS CompanyName,
        ja.ClientApprovalStatus,
        ja.Remarks, ja.EndorsedDate,
        CASE WHEN ja.Remarks LIKE '%Contract uploaded:%' THEN 'Ready to convert' ELSE 'Contract required' END ConversionState,
        CASE WHEN COALESCE(ja.Remarks,'') NOT LIKE '%Contract uploaded:%' THEN 'pending'
             WHEN LOWER(REGEXP_SUBSTR(ja.Remarks,'type=[^;]+')) LIKE '%open%' THEN 'open'
             WHEN ja.Remarks REGEXP 'expiry_date=[0-9]{4}-[0-9]{2}-[0-9]{2}' AND SUBSTRING(REGEXP_SUBSTR(ja.Remarks,'expiry_date=[0-9]{4}-[0-9]{2}-[0-9]{2}'),13,10)<CURDATE() THEN 'expired'
             ELSE 'expiring' END ContractState,
        CASE WHEN ja.Remarks LIKE '%Contract uploaded:%' THEN 1 ELSE 0 END AS ContractUploaded
    FROM job_applications ja
    INNER JOIN applicants a ON ja.ApplicantID = a.ApplicantID
    INNER JOIN joborder jo ON ja.JobOrderID = jo.JobOrderID
    LEFT JOIN job_order_details jod ON jod.detail_id=ja.target_detail_id AND jod.JobOrderID=ja.JobOrderID INNER JOIN clientcompany c ON jo.CompanyID = c.CompanyID
    WHERE ja.ClientApprovalStatus = 'Approved'
    AND ja.HiredDate IS NULL
    ORDER BY ja.EndorsedDate DESC
";

require_once __DIR__ . '/../Helpers/list_query_page.php';
if (isset($_GET['application_id'])) {
    $id=filter_var($_GET['application_id'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if(!$id)list_query_invalid();
    $sql=str_replace('AND ja.HiredDate IS NULL','AND ja.HiredDate IS NULL AND ja.ApplicationID = '.(int)$id,$sql);
}
[$sql,$types,$values,$pagination]=list_query_page($conn,$sql,['ApplicationID','ApplicantName','Position','CompanyName','ApplicantID'],['company'=>'CompanyName','position'=>'Position','contract_state'=>'ContractState','conversion_state'=>'ConversionState']);
$stmt=$conn->prepare($sql); if($types!=='')$stmt->bind_param($types,...$values); $stmt->execute(); $result=$stmt->get_result();
$applications = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $applications[] = [
            'application_id' => (int)$row['ApplicationID'],
            'applicant_name' => $row['ApplicantName'],
            'position' => $row['Position'],
            'company_name' => $row['CompanyName'],
            'contract_uploaded' => (bool)$row['ContractUploaded'],
            'contract_meta' => parseContractRemarks($row['Remarks'] ?? '')
        ];
    }
    $result->free();
}

echo json_encode([
    'success' => true,
    'applications' => $applications,
    'pagination'=>$pagination
]);
?>
