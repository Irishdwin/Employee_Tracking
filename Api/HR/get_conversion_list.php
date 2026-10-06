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
        ja.ApplicationID,
        a.FullName AS ApplicantName,
        jo.Position,
        c.Name AS CompanyName,
        ja.ClientApprovalStatus,
        ja.Remarks,
        CASE WHEN ja.Remarks LIKE '%Contract uploaded:%' THEN 1 ELSE 0 END AS ContractUploaded
    FROM job_applications ja
    INNER JOIN applicants a ON ja.ApplicantID = a.ApplicantID
    INNER JOIN joborder jo ON ja.JobOrderID = jo.JobOrderID
    INNER JOIN clientcompany c ON jo.CompanyID = c.CompanyID
    WHERE ja.ClientApprovalStatus = 'Approved'
    AND ja.HiredDate IS NULL
    ORDER BY ja.EndorsedDate DESC
";

$result = $conn->query($sql);
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
    'applications' => $applications
]);
?>
