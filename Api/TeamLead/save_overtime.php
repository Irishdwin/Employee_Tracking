<?php
/**
 * POST — Team Lead creates, edits, or changes the status of overtime records
 * in the EXISTING `overtime` table (no new table).
 *
 * Body (JSON or form): action = create | update | set_status
 *
 *   action=create:
 *     employee_ids[]   (one row is inserted per employee, all with the same range)
 *     date              YYYY-MM-DD
 *     ot_start           HH:MM (24h) or HH:MM:SS
 *     ot_end             HH:MM (24h) or HH:MM:SS
 *     remarks            optional
 *
 *   action=update:
 *     overtime_id
 *     ot_start, ot_end   (at least one)
 *     remarks            optional
 *
 *   action=set_status:
 *     overtime_id
 *     status             Active | Completed | Approved | Rejected
 *                         (Pending is the initial state on create only)
 *
 * Validation (section 6/18 of the spec):
 *   - OT end must be after OT start
 *   - hours must be > 0 and <= OT_MAX_HOURS_PER_SESSION (sanity cap, not a
 *     payroll rate — see constant below)
 *   - no duplicate/overlapping OT range for the same employee + date
 *
 * Ownership (section 16): a Team Lead may only touch overtime for an
 * employee on THEIR OWN deployment (deployment.SiteSuperVisorID = the
 * logged-in TL's UsersID) — same check used by submit_dtr.php and
 * get_dtr_cutoff_review.php. Never trusts employee_id/overtime_id alone.
 */
declare(strict_types=1);

require_once __DIR__ . '/../Common/session_init.php';
date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json; charset=utf-8');

// Sanity cap on a single OT session's hours — an input-validation guard
// (section 6: "OT hours exceeding a reasonable configured limit"), NOT a
// payroll rate. No existing config for this was found in the project, so a
// conservative, clearly-labeled constant is used instead of leaving the
// field unbounded.
const OT_MAX_HOURS_PER_SESSION = 12.0;

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$role = trim((string)$_SESSION['role']);
if (!in_array($role, ['TeamLead', 'Team Lead'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit();
}

require_once __DIR__ . '/../Db_connection/db_connect.php'; // mysqli $conn

$teamLeadUserId = (int)$_SESSION['user_id'];

$raw = file_get_contents('php://input');
$json = json_decode($raw, true);
$body = is_array($json) ? $json : $_POST;

$action = trim((string)($body['action'] ?? ''));

/** Confirm this employee is on the logged-in TL's own deployment roster. Returns DeploymentID or null. */
function ot_owned_deployment(mysqli $conn, int $employeeId, int $teamLeadUserId): ?int
{
    $stmt = $conn->prepare("
        SELECT DeploymentID FROM deployment
        WHERE EmployeeID = ? AND SiteSuperVisorID = ?
          AND (EndDate IS NULL OR EndDate >= CURDATE())
        LIMIT 1
    ");
    $stmt->bind_param('ii', $employeeId, $teamLeadUserId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (int)$row['DeploymentID'] : null;
}

function ot_status_id(mysqli $conn, string $name): ?int
{
    $stmt = $conn->prepare("SELECT StatusID FROM status WHERE Name = ? LIMIT 1");
    $stmt->bind_param('s', $name);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (int)$row['StatusID'] : null;
}

function ot_find_attendance_id(mysqli $conn, int $employeeId, string $date): ?int
{
    $stmt = $conn->prepare("SELECT AttendanceID FROM attendance WHERE EmployeeID = ? AND DATE(Date) = ? LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param('is', $employeeId, $date);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (int)$row['AttendanceID'] : null;
}

/** Any existing OT session for this employee/date whose time range overlaps [start,end)? */
function ot_has_overlap(mysqli $conn, int $employeeId, string $date, string $start, string $end, ?int $excludeOvertimeId): bool
{
    $sql = "
        SELECT OvertimeID FROM overtime
        WHERE EmployeeID = ? AND OvertimeDate = ?
          AND OTStartTime < ? AND OTEndTime > ?
    ";
    $params = [$employeeId, $date, $end, $start];
    $types = 'isss';
    if ($excludeOvertimeId !== null) {
        $sql .= " AND OvertimeID != ?";
        $params[] = $excludeOvertimeId;
        $types .= 'i';
    }
    $sql .= " LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (bool)$row;
}

function ot_hours_between(string $date, string $start, string $end): ?float
{
    $startTs = strtotime($date . ' ' . $start);
    $endTs = strtotime($date . ' ' . $end);
    if ($startTs === false || $endTs === false) return null;
    if ($endTs <= $startTs) return null; // OT end must be after OT start
    return round(($endTs - $startTs) / 3600, 2);
}

try {
    if ($action === 'create') {
        $employeeIds = isset($body['employee_ids']) && is_array($body['employee_ids']) ? $body['employee_ids'] : [];
        $employeeIds = array_values(array_unique(array_filter(array_map('intval', $employeeIds), static fn($v) => $v > 0)));
        $date = trim((string)($body['date'] ?? ''));
        $otStart = trim((string)($body['ot_start'] ?? ''));
        $otEnd = trim((string)($body['ot_end'] ?? ''));
        $remarks = trim((string)($body['remarks'] ?? ''));

        if (empty($employeeIds)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No employees selected.']);
            exit();
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Valid date (YYYY-MM-DD) is required.']);
            exit();
        }
        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $otStart) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $otEnd)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Valid OT start/end times are required.']);
            exit();
        }

        $hours = ot_hours_between($date, $otStart, $otEnd);
        if ($hours === null) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'OT end time must be after OT start time.']);
            exit();
        }
        if ($hours > OT_MAX_HOURS_PER_SESSION) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'OT session exceeds the maximum of ' . OT_MAX_HOURS_PER_SESSION . ' hours.']);
            exit();
        }

        $pendingStatusId = ot_status_id($conn, 'Pending');
        $insertStmt = $conn->prepare("
            INSERT INTO overtime
                (EmployeeID, AttendanceID, DeploymentID, OvertimeDate, OTStartTime, OTEndTime,
                 RegularHours, OvertimeHours, OvertimeType, RequestedBy, StatusID, Remarks, CreatedAt, UpdatedAt)
            VALUES (?, ?, ?, ?, ?, ?, 8.00, ?, 'Regular', ?, ?, ?, NOW(), NOW())
        ");

        $created = [];
        $skippedNotOwned = [];
        $skippedDuplicate = [];

        foreach ($employeeIds as $employeeId) {
            $deploymentId = ot_owned_deployment($conn, $employeeId, $teamLeadUserId);
            if ($deploymentId === null) {
                $skippedNotOwned[] = $employeeId;
                continue;
            }
            if (ot_has_overlap($conn, $employeeId, $date, $otStart, $otEnd, null)) {
                $skippedDuplicate[] = $employeeId;
                continue;
            }
            $attendanceId = ot_find_attendance_id($conn, $employeeId, $date);
            $insertStmt->bind_param(
                'iiisssdis',
                $employeeId,
                $attendanceId,
                $deploymentId,
                $date,
                $otStart,
                $otEnd,
                $hours,
                $teamLeadUserId,
                $pendingStatusId,
                $remarks
            );
            if ($insertStmt->execute()) {
                $created[] = ['employee_id' => $employeeId, 'overtime_id' => $insertStmt->insert_id];
            } else {
                $skippedDuplicate[] = $employeeId; // e.g. unique-key collision at DB level
            }
        }
        $insertStmt->close();

        echo json_encode([
            'success' => true,
            'created' => $created,
            'skipped_not_owned' => $skippedNotOwned,
            'skipped_duplicate' => $skippedDuplicate,
            'ot_hours' => $hours,
            'message' => count($created) . ' overtime record(s) created.',
        ]);
        exit();
    }

    if ($action === 'update') {
        $overtimeId = (int)($body['overtime_id'] ?? 0);
        if ($overtimeId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'overtime_id is required.']);
            exit();
        }

        $existStmt = $conn->prepare("SELECT EmployeeID, OvertimeDate, OTStartTime, OTEndTime FROM overtime WHERE OvertimeID = ? LIMIT 1");
        $existStmt->bind_param('i', $overtimeId);
        $existStmt->execute();
        $existing = $existStmt->get_result()->fetch_assoc();
        $existStmt->close();

        if (!$existing) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Overtime record not found.']);
            exit();
        }

        $employeeId = (int)$existing['EmployeeID'];
        if (ot_owned_deployment($conn, $employeeId, $teamLeadUserId) === null) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'You do not manage this employee.']);
            exit();
        }

        $date = $existing['OvertimeDate'];
        $otStart = isset($body['ot_start']) && trim((string)$body['ot_start']) !== '' ? trim((string)$body['ot_start']) : substr((string)$existing['OTStartTime'], 0, 5);
        $otEnd = isset($body['ot_end']) && trim((string)$body['ot_end']) !== '' ? trim((string)$body['ot_end']) : substr((string)$existing['OTEndTime'], 0, 5);
        $remarks = isset($body['remarks']) ? trim((string)$body['remarks']) : null;

        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $otStart) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $otEnd)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Valid OT start/end times are required.']);
            exit();
        }

        $hours = ot_hours_between($date, $otStart, $otEnd);
        if ($hours === null) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'OT end time must be after OT start time.']);
            exit();
        }
        if ($hours > OT_MAX_HOURS_PER_SESSION) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'OT session exceeds the maximum of ' . OT_MAX_HOURS_PER_SESSION . ' hours.']);
            exit();
        }
        if (ot_has_overlap($conn, $employeeId, $date, $otStart, $otEnd, $overtimeId)) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'This range overlaps an existing overtime record for this employee/date.']);
            exit();
        }

        if ($remarks === null) {
            $updStmt = $conn->prepare("UPDATE overtime SET OTStartTime = ?, OTEndTime = ?, OvertimeHours = ?, UpdatedAt = NOW() WHERE OvertimeID = ?");
            $updStmt->bind_param('ssdi', $otStart, $otEnd, $hours, $overtimeId);
        } else {
            $updStmt = $conn->prepare("UPDATE overtime SET OTStartTime = ?, OTEndTime = ?, OvertimeHours = ?, Remarks = ?, UpdatedAt = NOW() WHERE OvertimeID = ?");
            $updStmt->bind_param('ssdsi', $otStart, $otEnd, $hours, $remarks, $overtimeId);
        }
        $ok = $updStmt->execute();
        $updStmt->close();

        echo json_encode([
            'success' => (bool)$ok,
            'overtime_id' => $overtimeId,
            'ot_hours' => $hours,
            'message' => $ok ? 'Overtime record updated.' : 'Failed to update overtime record.',
        ]);
        exit();
    }

    if ($action === 'set_status') {
        $overtimeId = (int)($body['overtime_id'] ?? 0);
        $status = trim((string)($body['status'] ?? ''));
        $allowed = ['Active', 'Completed', 'Approved', 'Rejected'];

        if ($overtimeId <= 0 || !in_array($status, $allowed, true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'overtime_id and a valid status are required.']);
            exit();
        }

        $existStmt = $conn->prepare("SELECT EmployeeID FROM overtime WHERE OvertimeID = ? LIMIT 1");
        $existStmt->bind_param('i', $overtimeId);
        $existStmt->execute();
        $existing = $existStmt->get_result()->fetch_assoc();
        $existStmt->close();

        if (!$existing) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Overtime record not found.']);
            exit();
        }
        if (ot_owned_deployment($conn, (int)$existing['EmployeeID'], $teamLeadUserId) === null) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'You do not manage this employee.']);
            exit();
        }

        $statusId = ot_status_id($conn, $status);
        if ($statusId === null) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Status not configured.']);
            exit();
        }

        if ($status === 'Approved') {
            $updStmt = $conn->prepare("UPDATE overtime SET StatusID = ?, ApprovedBy = ?, ApprovalDate = NOW(), UpdatedAt = NOW() WHERE OvertimeID = ?");
            $updStmt->bind_param('iii', $statusId, $teamLeadUserId, $overtimeId);
        } else {
            $updStmt = $conn->prepare("UPDATE overtime SET StatusID = ?, UpdatedAt = NOW() WHERE OvertimeID = ?");
            $updStmt->bind_param('ii', $statusId, $overtimeId);
        }
        $ok = $updStmt->execute();
        $updStmt->close();

        echo json_encode([
            'success' => (bool)$ok,
            'overtime_id' => $overtimeId,
            'status' => $status,
            'message' => $ok ? ('Overtime marked ' . $status . '.') : 'Failed to update status.',
        ]);
        exit();
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
} catch (Throwable $e) {
    error_log('save_overtime: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to save overtime.',
        'error_details' => $e->getMessage(),
    ]);
}
