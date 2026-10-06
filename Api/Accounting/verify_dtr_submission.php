<?php
/**
 * POST — Accounting marks a submitted DTR batch (Team Lead + Cutoff) as reviewed/verified.
 *
 * This is Step 2 ("Review DTR" / "Verify DTR") of the DTR Submission -> Review ->
 * Compute Payroll -> Review Payroll -> Export Payroll workflow.
 *
 * Uses the EXISTING dtr_submissions.ReviewedBy / dtr_submissions.ReviewDate columns
 * (already present in the schema — see onazul_hris_db.sql) instead of inventing a
 * new status table. No schema change required.
 *
 * Body (JSON or form):
 *   tl_id (required), cutoff_start (YYYY-MM-DD, required), cutoff_end (YYYY-MM-DD, required)
 *   employee_id (optional) — if given, verifies only that one employee's row within
 *   the batch; otherwise verifies every employee in the Team Lead+cutoff batch.
 */
declare(strict_types=1);

require_once __DIR__ . '/../Common/session_init.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$role = trim((string)$_SESSION['role']);
if (!in_array($role, ['Accounting', 'Admin'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit();
}

require_once __DIR__ . '/../Db_connection/db_connect.php';

$raw = file_get_contents('php://input');
$json = json_decode($raw, true);
$body = is_array($json) ? $json : $_POST;

$siteId=(int)($body['site_id']??0);
$tlId = (int)($body['tl_id'] ?? 0);
$cutoffStart = trim((string)($body['cutoff_start'] ?? ''));
$cutoffEnd = trim((string)($body['cutoff_end'] ?? ''));
$employeeId = isset($body['employee_id']) ? (int)$body['employee_id'] : null;

if ($tlId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $cutoffStart) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $cutoffEnd)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'tl_id, cutoff_start, and cutoff_end are required.']);
    exit();
}

$reviewerId = (int)$_SESSION['user_id'];

try {
    if ($employeeId) {
        $stmt = $conn->prepare("
            UPDATE dtr_submissions
            SET ReviewedBy = ?, ReviewDate = NOW()
            WHERE SiteSupervisorID = ? AND CutoffStartDate = ? AND CutoffEndDate = ? AND EmployeeID = ? AND (?=0 OR SiteID=?)
        ");
        $stmt->bind_param('iissiii', $reviewerId, $tlId, $cutoffStart, $cutoffEnd, $employeeId,$siteId,$siteId);
    } else {
        $stmt = $conn->prepare("
            UPDATE dtr_submissions
            SET ReviewedBy = ?, ReviewDate = NOW()
            WHERE SiteSupervisorID = ? AND CutoffStartDate = ? AND CutoffEndDate = ? AND (?=0 OR SiteID=?)
        ");
        $stmt->bind_param('iissii', $reviewerId, $tlId, $cutoffStart, $cutoffEnd,$siteId,$siteId);
    }
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    echo json_encode([
        'success' => true,
        'verified_count' => $affected,
        'message' => $affected > 0 ? 'DTR verified.' : 'No matching submission rows found to verify.',
    ]);
} catch (Throwable $e) {
    error_log('verify_dtr_submission: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to verify DTR.', 'error_details' => $e->getMessage()]);
}
