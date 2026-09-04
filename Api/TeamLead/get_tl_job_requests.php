<?php
/**
 * GET — Team Lead consolidated job order requests with nested deployment rows.
 */
require_once __DIR__ . '/../Common/session_init.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$role = trim((string)$_SESSION['role']);
if (!in_array($role, ['TeamLead', 'Team Lead'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden — Team Lead access only']);
    exit;
}

require __DIR__ . '/../Db_connection/db_connect.php';
require_once __DIR__ . '/../Helpers/jor_multi_position.php';

jor_multi_ensure_schema($conn);

$userId = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT
        r.RequestID,
        r.CompanyID,
        c.Name AS CompanyName,
        r.Position,
        r.RequiredCount,
        r.Location,
        r.WorkSchedule,
        r.Remarks,
        r.Status,
        r.IsConsolidated,
        r.CreatedAt,
        d.detail_id,
        d.position_title,
        d.required_workers,
        d.deployment_site,
        d.work_schedule,
        d.job_category
    FROM job_order_requests r
    INNER JOIN clientcompany c ON r.CompanyID = c.CompanyID
    LEFT JOIN job_order_details d ON d.RequestID = r.RequestID
    WHERE r.RequestedBy = ?
    ORDER BY r.CreatedAt DESC, r.RequestID DESC, d.detail_id ASC
");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to prepare query.']);
    exit;
}

$stmt->bind_param('i', $userId);
$stmt->execute();
$res = $stmt->get_result();

$byId = [];
while ($row = $res->fetch_assoc()) {
    $rid = (int)$row['RequestID'];
    if (!isset($byId[$rid])) {
        $byId[$rid] = [
            'jor_id' => $rid,
            'request_id' => $rid,
            'date' => substr((string)$row['CreatedAt'], 0, 10),
            'created_at' => $row['CreatedAt'],
            'company_id' => (int)$row['CompanyID'],
            'company' => $row['CompanyName'] ?? '',
            'company_name' => $row['CompanyName'] ?? '',
            'status' => $row['Status'] ?? 'Pending',
            'remarks' => $row['Remarks'] ?? '',
            'is_consolidated' => (int)($row['IsConsolidated'] ?? 0) === 1,
            'deployments' => [],
            'position' => trim((string)($row['Position'] ?? '')),
            'required_count' => (int)($row['RequiredCount'] ?? 0),
            'location' => $row['Location'] ?? '',
        ];
    }

    if (!empty($row['detail_id'])) {
        $byId[$rid]['deployments'][] = [
            'detail_id' => (int)$row['detail_id'],
            'position' => (string)$row['position_title'],
            'position_title' => (string)$row['position_title'],
            'workers' => (int)$row['required_workers'],
            'required_workers' => (int)$row['required_workers'],
            'site' => (string)$row['deployment_site'],
            'deployment_site' => (string)$row['deployment_site'],
            'work_schedule' => (string)$row['work_schedule'],
            'job_category' => (string)($row['job_category'] ?? ''),
        ];
    }
}
$stmt->close();

$requests = [];
foreach ($byId as $item) {
    if (empty($item['deployments'])) {
        $pos = trim((string)$item['position']);
        if ($pos !== '' && $pos !== '0') {
            $item['deployments'][] = [
                'detail_id' => null,
                'position' => $pos,
                'position_title' => $pos,
                'workers' => (int)$item['required_count'],
                'required_workers' => (int)$item['required_count'],
                'site' => (string)$item['location'],
                'deployment_site' => (string)$item['location'],
                'work_schedule' => '',
                'job_category' => '',
            ];
        }
    }
    $requests[] = $item;
}

echo json_encode([
    'success' => true,
    'requests' => $requests,
]);
