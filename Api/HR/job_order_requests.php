<?php
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Max-Age: 86400');
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../Common/session_init.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}
$allowedRoles = ['Admin', 'HR'];
if (!in_array($_SESSION['role'], $allowedRoles)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit();
}

require __DIR__ . '/../Db_connection/db_connect.php';
require_once __DIR__ . '/../Helpers/job_categories.php';
require_once __DIR__ . '/../Helpers/job_categories_lookup.php';
require_once __DIR__ . '/../Helpers/jor_multi_position.php';
require_once __DIR__ . '/../Helpers/job_order_request_workflow.php';

function json_resp($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit();
}

/**
 * @param array<int, array<string,mixed>> $details
 * @param array<string,mixed> $parentRow
 * @return array{position:string,required_count:int,location:string,work_schedule:string}
 */
function jor_hr_list_summary(array $details, array $parentRow): array
{
    if (empty($details)) {
        return [
            'position' => (string)$parentRow['Position'],
            'required_count' => (int)$parentRow['RequiredCount'],
            'location' => (string)($parentRow['Location'] ?? ''),
            'work_schedule' => (string)($parentRow['WorkSchedule'] ?? ''),
        ];
    }

    $first = $details[0];
    $position = (string)($first['position_title'] ?? $parentRow['Position']);
    $extra = count($details) - 1;
    if ($extra > 0) {
        $position .= ' (+' . $extra . ' more)';
    }

    $totalWorkers = 0;
    $sites = [];
    $schedules = [];
    foreach ($details as $d) {
        $totalWorkers += (int)($d['required_workers'] ?? 0);
        $site = trim((string)($d['deployment_site'] ?? ''));
        if ($site !== '') {
            $sites[$site] = true;
        }
        $sched = trim((string)($d['work_schedule'] ?? ''));
        if ($sched !== '') {
            $schedules[$sched] = true;
        }
    }

    $siteKeys = array_keys($sites);
    $schedKeys = array_keys($schedules);

    return [
        'position' => $position,
        'required_count' => $totalWorkers > 0 ? $totalWorkers : (int)$parentRow['RequiredCount'],
        'location' => count($siteKeys) > 1 ? 'Multiple sites' : ($siteKeys[0] ?? (string)($parentRow['Location'] ?? '')),
        'work_schedule' => count($schedKeys) > 1 ? 'Multiple schedules' : ($schedKeys[0] ?? (string)($parentRow['WorkSchedule'] ?? '')),
    ];
}

function getActiveAgreementsForCompany($conn, $companyId) {
    $stmt = $conn->prepare("SELECT ServiceAgreementID FROM serviceagreement
        WHERE ClientCompanyID = ? AND Status = 'Active'
        AND SignedDocumentPath IS NOT NULL AND SignedDocumentPath != ''
        ORDER BY ContractEndDate DESC");
    if (!$stmt) return [];
    $stmt->bind_param("i", $companyId);
    $stmt->execute();
    $result = $stmt->get_result();
    $list = [];
    while ($row = $result->fetch_assoc()) $list[] = (int)$row['ServiceAgreementID'];
    $stmt->close();
    return $list;
}

$method = $_SERVER['REQUEST_METHOD'];

if (!jorw_active_user($conn,(int)$_SESSION['user_id'],['HR','Admin'])) json_resp(['success'=>false,'message'=>'Forbidden'],403);
try {
if ($method === 'GET') {
    $statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
    $sql = "SELECT r.RequestID, r.RequestedBy, r.CompanyID, r.Position, r.JobCategory, r.RequiredCount, r.Location, r.WorkSchedule, r.Remarks, r.RejectionReason, r.Status, r.CreatedAt, r.JobOrderID,
            c.Name AS CompanyName,
            u.FullName AS RequestedByName
            FROM job_order_requests r
            INNER JOIN clientcompany c ON r.CompanyID = c.CompanyID
            LEFT JOIN users u ON r.RequestedBy = u.UsersID
            WHERE 1=1";
    $params = [];
    $types = '';
    if ($statusFilter === 'Pending' || $statusFilter === 'Approved' || $statusFilter === 'Rejected') {
        $sql .= " AND r.Status = ?";
        $params[] = $statusFilter;
        $types .= 's';
    }
    $sql .= " ORDER BY r.CreatedAt DESC";

    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
    }

    $rawRows = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rawRows[] = $row;
        }
    }

    $detailsByRequest = [];
    if (!empty($rawRows)) {
        $ids = array_map(static function ($r) {
            return (int)$r['RequestID'];
        }, $rawRows);
        $ids = array_values(array_unique(array_filter($ids)));
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $types = str_repeat('i', count($ids));
            $detailSql = "SELECT RequestID, position_title, required_workers, deployment_site, work_schedule
                          FROM job_order_details
                          WHERE RequestID IN ($placeholders)
                          ORDER BY RequestID ASC, detail_id ASC";
            $detailStmt = $conn->prepare($detailSql);
            if ($detailStmt) {
                $detailStmt->bind_param($types, ...$ids);
                $detailStmt->execute();
                $detailRes = $detailStmt->get_result();
                while ($d = $detailRes->fetch_assoc()) {
                    $rid = (int)$d['RequestID'];
                    if (!isset($detailsByRequest[$rid])) {
                        $detailsByRequest[$rid] = [];
                    }
                    $detailsByRequest[$rid][] = $d;
                }
                $detailStmt->close();
            }
        }
    }

    $requests = [];
    foreach ($rawRows as $row) {
        $requestId = (int)$row['RequestID'];
        $details = $detailsByRequest[$requestId] ?? [];
        $summary = jor_hr_list_summary($details, $row);
        $requests[] = [
            'request_id'     => $requestId,
            'jor_id'         => $requestId,
            'company_id'     => (int)$row['CompanyID'],
            'company_name'   => $row['CompanyName'],
            'position'       => $summary['position'],
            'job_category'   => $row['JobCategory'] ?? '',
            'required_count' => $summary['required_count'],
            'location'       => $summary['location'],
            'work_schedule'  => $summary['work_schedule'],
            'remarks'        => $row['Remarks'],
            'rejection_reason' => $row['RejectionReason'] ?? '',
            'status'         => $row['Status'],
            'created_at'     => $row['CreatedAt'],
            'requested_by'   => $row['RequestedByName'],
            'job_order_id'   => $row['JobOrderID'] ? (int)$row['JobOrderID'] : null,
            'deployment_count' => count($details),
        ];
    }
    json_resp(['success' => true, 'requests' => $requests]);
}

if ($method === 'POST') {
    $input=json_decode(file_get_contents('php://input'),true);
    if (!is_array($input)) $input=$_POST;
    $id=filter_var($input['request_id'] ?? 0,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    $action=$input['action'] ?? '';
    $reason=$input['reason'] ?? '';
    if (!$id || !is_string($action) || !is_string($reason)) json_resp(['success'=>false,'message'=>'Invalid request.'],400);
    json_resp(jorw_decide($conn,$id,(int)$_SESSION['user_id'],$action,$reason,$input));
}
json_resp(['success'=>false,'message'=>'Invalid request method.'],405);
} catch (DomainException $e) {
    json_resp(['success'=>false,'message'=>$e->getMessage()],$e->getCode() ?: 400);
} catch (Throwable $e) {
    error_log('job_order_requests: '.$e->getMessage());
    json_resp(['success'=>false,'message'=>'Could not process job order requests. Please retry.'],500);
}
