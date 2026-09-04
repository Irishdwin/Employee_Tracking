<?php
/**
 * GET ?job_order_id= — placement options for a posted job order (applicant apply modal).
 */
require_once __DIR__ . '/../Common/session_init.php';

header('Content-Type: application/json; charset=utf-8');

$jobOrderId = isset($_GET['job_order_id']) ? (int)$_GET['job_order_id'] : 0;
if ($jobOrderId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'job_order_id is required.']);
    exit;
}

require __DIR__ . '/../Db_connection/db_connect.php';
require_once __DIR__ . '/../Helpers/job_order_wall.php';
require_once __DIR__ . '/../Helpers/jor_multi_position.php';

jor_multi_ensure_schema($conn);

$joStmt = $conn->prepare("
    SELECT jo.JobOrderID, jo.Position, jo.Location, jo.WorkSchedule, jo.RequiredCount, c.Name AS CompanyName
    FROM joborder jo
    INNER JOIN clientcompany c ON jo.CompanyID = c.CompanyID
    WHERE jo.JobOrderID = ?
      AND c.IsActive = 1
      AND " . job_order_has_open_slots_sql() . "
    LIMIT 1
");
if (!$joStmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not load job order.']);
    exit;
}
$joStmt->bind_param('i', $jobOrderId);
$joStmt->execute();
$job = $joStmt->get_result()->fetch_assoc();
$joStmt->close();

if (!$job) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Job order not found or no longer open.']);
    exit;
}

$placements = jor_fetch_placements_for_job_order($conn, $jobOrderId);

if (count($placements) === 0) {
    $placements[] = [
        'detail_id' => null,
        'position' => (string)$job['Position'],
        'position_title' => (string)$job['Position'],
        'deployment_site' => (string)($job['Location'] ?? ''),
        'site' => (string)($job['Location'] ?? ''),
        'work_schedule' => (string)($job['WorkSchedule'] ?? ''),
        'required_workers' => (int)$job['RequiredCount'],
        'workers' => (int)$job['RequiredCount'],
        'job_category' => '',
        'label' => trim((string)$job['Position']) . ' — ' . trim((string)($job['Location'] ?? 'Open site')),
    ];
}

echo json_encode([
    'success' => true,
    'job_order_id' => $jobOrderId,
    'company_name' => $job['CompanyName'] ?? '',
    'placement_count' => count($placements),
    'requires_selection' => count($placements) > 1,
    'placements' => $placements,
]);
