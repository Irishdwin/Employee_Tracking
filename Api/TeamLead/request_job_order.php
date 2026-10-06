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
require_once __DIR__ . '/../Helpers/job_categories.php';
require_once __DIR__ . '/../Helpers/job_categories_lookup.php';
require_once __DIR__ . '/../Helpers/AuditLogger.php';

function json_resp($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit();
}

function company_has_active_agreement(mysqli $conn, int $companyId): bool {
    if ($companyId <= 0) return false;
    $sql = "SELECT ServiceAgreementID
            FROM serviceagreement
            WHERE ClientCompanyID = ?
              AND Status = 'Active'
              AND COALESCE(NULLIF(TRIM(SignedDocumentPath), ''), '') <> ''
            ORDER BY ServiceAgreementID DESC
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;
    $stmt->bind_param('i', $companyId);
    $stmt->execute();
    $has = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $has;
}

require_once __DIR__ . '/../Helpers/job_order_request_workflow.php';
if (!jorw_active_user($conn,(int)$_SESSION['user_id'],['TeamLead','Team Lead'])) json_resp(['success'=>false,'message'=>'Forbidden'],403);

$method = $_SERVER['REQUEST_METHOD'];
$userId = (int)$_SESSION['user_id'];

if ($method === 'GET') {
    $stmt = $conn->prepare("
        SELECT
            r.RequestID,
            r.CompanyID,
            c.Name AS CompanyName,
            r.Position,
            r.JobCategory,
            r.category_id,
            jc.category_name AS job_category_name,
            r.RequiredCount,
            r.Location,
            r.WorkSchedule,
            r.Remarks,
            r.RejectionReason,
            r.Status,
            r.CreatedAt
        FROM job_order_requests r
        INNER JOIN clientcompany c ON r.CompanyID = c.CompanyID
        LEFT JOIN job_categories jc ON jc.id = r.category_id
        WHERE r.RequestedBy = ?
        ORDER BY r.CreatedAt DESC
    ");
    if (!$stmt) json_resp(['success' => false, 'message' => 'Failed to prepare query.'], 500);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $positionName = trim((string)($r['Position'] ?? ''));
        if ($positionName === '0') {
            $positionName = '';
        }
        $rows[] = [
            'request_id' => (int)$r['RequestID'],
            'company_id' => (int)$r['CompanyID'],
            'company_name' => $r['CompanyName'] ?? '',
            'position' => $positionName,
            'position_name' => $positionName,
            'job_category' => trim((string)($r['job_category_name'] ?? $r['JobCategory'] ?? '')),
            'category_id' => isset($r['category_id']) ? (int)$r['category_id'] : 0,
            'required_count' => (int)$r['RequiredCount'],
            'location' => $r['Location'] ?? '',
            'work_schedule' => $r['WorkSchedule'] ?? '',
            'remarks' => $r['Remarks'] ?? '',
            'rejection_reason' => $r['RejectionReason'] ?? '',
            'status' => $r['Status'] ?? 'Pending',
            'deployments' => jorw_rows($conn,'SELECT d.job_category,k.classification_name,s.sub_classification_name FROM job_order_details d LEFT JOIN job_classifications k ON k.id=d.classification_id LEFT JOIN job_sub_classifications s ON s.id=d.sub_classification_id WHERE d.RequestID=? ORDER BY d.detail_id','i',[(int)$r['RequestID']]),
            'created_at' => $r['CreatedAt'],
        ];
    }
    $stmt->close();
    json_resp(['success' => true, 'requests' => $rows]);
}

if ($method === 'POST') {
    // Keep the legacy form endpoint, routing its write through the same validated transaction.
    $jorConsolidatedInputOverride=[
        'company_id'=>$_POST['company_id'] ?? 0,
        'remarks'=>$_POST['remarks'] ?? '',
        'positions'=>[[
            'position'=>$_POST['position'] ?? '',
            'category_id'=>is_numeric($_POST['category_id'] ?? $_POST['job_category'] ?? '') ? (int)($_POST['category_id'] ?? $_POST['job_category']) : 0,
            'job_category_name'=>$_POST['job_category_name'] ?? $_POST['job_category'] ?? '',
            'classification_id'=>$_POST['classification_id'] ?? null,
            'sub_classification_id'=>$_POST['sub_classification_id'] ?? null,
            'workers_needed'=>$_POST['required_count'] ?? 0,
            'deployment_site'=>$_POST['location'] ?? '',
            'work_schedule'=>$_POST['work_schedule'] ?? '',
        ]],
    ];
    require __DIR__.'/submit_consolidated_jor.php';
    exit;
}
json_resp(['success'=>false,'message'=>'Invalid request method.'],405);
