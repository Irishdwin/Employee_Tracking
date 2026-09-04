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

jor_multi_ensure_schema($conn);

$userId = (int)$_SESSION['user_id'];
$raw = file_get_contents('php://input');
$input = is_array(json_decode($raw, true)) ? json_decode($raw, true) : [];

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

if (!jor_tl_can_access_company($conn, $userId, $companyId)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You can only request job orders for companies assigned to you.']);
    exit;
}

// Strict Work Site Geofence Check before Job Order creation[cite: 16, 17]
$stmtGeo = $conn->prepare("SELECT latitude, longitude FROM clientcompany WHERE CompanyID = ? AND UserID = ? LIMIT 1");
$stmtGeo->bind_param("ii", $companyId, $userId);
$stmtGeo->execute();
$geoRow = $stmtGeo->get_result()->fetch_assoc();
$stmtGeo->close();

if (!$geoRow || $geoRow['latitude'] === null || $geoRow['longitude'] === '') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error_code' => 'WORK_SITE_NOT_REGISTERED',
        'message' => 'Please register your work site before creating a job order.'
    ]);
    exit;
}

if (!jor_company_has_active_agreement($conn, $companyId)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Your company needs an active service agreement with uploaded document before requesting a job order.',
        'error_code' => 'NO_ACTIVE_SERVICE_AGREEMENT',
    ]);
    exit;
}

$pdo = hris_pdo();
job_categories_ensure_schema($pdo);

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

    $resolved = job_category_resolve_input($pdo, $categoryId, $categoryName);
    if (!$resolved && $position !== '') {
        $resolved = job_category_infer_from_text($pdo, $position);
    }

    if ($position === '' || $workers <= 0 || $site === '' || $schedule === '' || !$resolved) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Each row needs position, category, workers, deployment site, and work schedule (row ' . ($idx + 1) . ').',
        ]);
        exit;
    }

    $normalized[] = [
        'position' => $position,
        'category_id' => (int)$resolved['id'],
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
        (RequestID, position_title, category_id, job_category, required_workers, deployment_site, work_schedule)
        VALUES (?, ?, ?, ?, ?, ?, ?)");
    if (!$detail) {
        throw new RuntimeException('Failed to prepare detail insert.');
    }

    foreach ($normalized as $row) {
        $detail->bind_param(
            'isisiss',
            $requestId,
            $row['position'],
            $row['category_id'],
            $row['job_category'],
            $row['workers'],
            $row['site'],
            $row['schedule']
        );
        if (!$detail->execute()) {
            throw new RuntimeException('Failed to save position row.');
        }
    }
    $detail->close();

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