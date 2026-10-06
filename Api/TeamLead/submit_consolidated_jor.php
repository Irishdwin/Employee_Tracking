<?php
/**
 * POST JSON — consolidated multi-position Job Order Request from Team Lead.
 *
 * Payload:
 * {
 *   "client_company": 1,
 *   "company_id": 1,
 *   "remarks": "...",
 *   "positions": [
 *     { "position": "Teacher", "category_id": 2, "workers_needed": 5,
 *       "deployment_site": "Carmen Campus", "work_schedule": "Day Shift" }
 *   ]
 * }
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

require __DIR__ . '/../Db_connection/db_connect.php';
require_once __DIR__ . '/../Helpers/job_categories.php';
require_once __DIR__ . '/../Helpers/job_categories_lookup.php';
require_once __DIR__ . '/../Helpers/jor_multi_position.php';

require_once __DIR__ . '/../Helpers/job_order_request_workflow.php';
if (!jorw_active_user($conn,(int)$_SESSION['user_id'],['TeamLead','Team Lead'])) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Forbidden']); exit; }

$userId = (int)$_SESSION['user_id'];
$raw = file_get_contents('php://input');
$input = $jorConsolidatedInputOverride ?? (is_array(json_decode($raw, true)) ? json_decode($raw, true) : []);

$companyId = (int)($input['company_id'] ?? $input['client_company'] ?? 0);
$remarks = trim((string)($input['remarks'] ?? ''));
$positions = $input['positions'] ?? [];

if (!is_array($positions) || count($positions) === 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'At least one position row is required.']);
    exit;
}

if ($companyId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Client company is required.']);
    exit;
}

try { jorw_validate_company($conn,$userId,$companyId); }
catch (DomainException $e) {
    http_response_code($e->getCode());
    $code=strpos($e->getMessage(),'register your work site')!==false ? 'WORK_SITE_NOT_REGISTERED' : (strpos($e->getMessage(),'service agreement')!==false ? 'NO_ACTIVE_SERVICE_AGREEMENT' : 'COMPANY_NOT_ASSIGNED');
    echo json_encode(['success'=>false,'message'=>$e->getMessage(),'error_code'=>$code]); exit;
}

$normalized = [];
foreach ($positions as $idx => $row) {
    if (!is_array($row)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid position row at index ' . $idx]);
        exit;
    }
    $position = trim((string)($row['position'] ?? $row['position_title'] ?? ''));
    $categoryId = (int)($row['category_id'] ?? 0);
    $categoryName = trim((string)($row['job_category'] ?? $row['job_category_name'] ?? ''));
    $workers = (int)($row['workers_needed'] ?? $row['required_workers'] ?? 0);
    $site = trim((string)($row['deployment_site'] ?? $row['location'] ?? ''));
    $schedule = trim((string)($row['work_schedule'] ?? ''));

    try {
        $resolved = jh_validate($conn,jh_id($row['category_id'] ?? null),jh_id($row['classification_id'] ?? null),jh_id($row['sub_classification_id'] ?? null));
    } catch (DomainException $e) {
        http_response_code(400);
        echo json_encode(['success'=>false,'message'=>'Position row '.($idx+1).': '.$e->getMessage()]);
        exit;
    }

    if ($position === '' || mb_strlen($position)>100 || $workers <= 0 || $workers>2147483647 || $site === '' || mb_strlen($site)>150 || $schedule === '' || mb_strlen($schedule)>100 || !$resolved) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Each row needs position, category, workers, deployment site, and work schedule (row ' . ($idx + 1) . ').',
        ]);
        exit;
    }

    $normalized[] = [
        'position' => $position,
        'category_id' => (int)$resolved['category_id'],
        'classification_id' => (int)$resolved['classification_id'],
        'sub_classification_id' => (int)$resolved['sub_classification_id'],
        'job_category' => $resolved['category_name'],
        'workers' => $workers,
        'site' => $site,
        'schedule' => $schedule,
    ];
}

$first = $normalized[0];
$totalWorkers = array_sum(array_map(static fn($r) => $r['workers'], $normalized));
$summaryPosition = count($normalized) === 1
    ? $first['position']
    : $first['position'] . ' (+' . (count($normalized) - 1) . ' more)';
if (mb_strlen($summaryPosition)>100 || $totalWorkers>2147483647) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'Request summary exceeds supported position or worker limits.']); exit; }
$summaryLocation = count($normalized) === 1
    ? $first['site']
    : 'Multiple sites';

$conn->begin_transaction();

try {
    $hasCatId = false;
    $cc = $conn->query("SHOW COLUMNS FROM job_order_requests LIKE 'category_id'");
    if ($cc && $cc->num_rows > 0) {
        $hasCatId = true;
    }

    if ($hasCatId) {
        $parent = $conn->prepare("INSERT INTO job_order_requests
            (RequestedBy, CompanyID, Position, category_id, JobCategory, RequiredCount, Location, WorkSchedule, Remarks, Status, IsConsolidated)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', 1)");
        if (!$parent) {
            throw new RuntimeException('Failed to prepare parent request.');
        }
        $parent->bind_param(
            'iisisisss',
            $userId,
            $companyId,
            $summaryPosition,
            $first['category_id'],
            $first['job_category'],
            $totalWorkers,
            $summaryLocation,
            $first['schedule'],
            $remarks
        );
    } else {
        $parent = $conn->prepare("INSERT INTO job_order_requests
            (RequestedBy, CompanyID, Position, JobCategory, RequiredCount, Location, WorkSchedule, Remarks, Status, IsConsolidated)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending', 1)");
        if (!$parent) {
            throw new RuntimeException('Failed to prepare parent request.');
        }
        $parent->bind_param(
            'iississs',
            $userId,
            $companyId,
            $summaryPosition,
            $first['job_category'],
            $totalWorkers,
            $summaryLocation,
            $first['schedule'],
            $remarks
        );
    }

    if (!$parent->execute()) {
        throw new RuntimeException('Failed to save parent request.');
    }
    $requestId = (int)$conn->insert_id;
    $parent->close();

    $detail = $conn->prepare("INSERT INTO job_order_details
        (RequestID, position_title, category_id, job_category, required_workers, deployment_site, work_schedule, classification_id, sub_classification_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$detail) {
        throw new RuntimeException('Failed to prepare detail insert.');
    }

    foreach ($normalized as $row) {
        $detail->bind_param(
            'isisissii',
            $requestId,
            $row['position'],
            $row['category_id'],
            $row['job_category'],
            $row['workers'],
            $row['site'],
            $row['schedule'],
            $row['classification_id'],
            $row['sub_classification_id']
        );
        if (!$detail->execute()) {
            throw new RuntimeException('Failed to save position row.');
        }
    }
    $detail->close();

    jorw_notify_submitted($conn,$requestId);
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Consolidated job order request submitted.',
        'request_id' => $requestId,
        'jor_id' => $requestId,
        'position_count' => count($normalized),
    ]);
} catch (Throwable $e) {
    $conn->rollback();
    error_log('submit_consolidated_jor: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not save request. Please try again.']);
}
