<?php
/**
 * DEVELOPMENT / TESTING ONLY. POST — action=create|delete
 *
 * Payroll-test-scenario support ("5. Overtime Details"). Writes directly
 * into the EXISTING `overtime` table (no new table, no new columns) using
 * the SAME column set/shape as the real Team-Lead-facing endpoint
 * (Api/TeamLead/save_overtime.php's action=create) — this file does not
 * modify or duplicate that endpoint's logic, it only mirrors its INSERT
 * shape for admin-driven test data, then sets the row straight to
 * 'Approved' (test data doesn't need to go through a separate approval
 * click) so it is immediately picked up by
 * payroll_fetch_approved_overtime() inside payroll_compute_period() — the
 * REAL production payroll function — exactly like a Team-Lead-approved
 * session would be.
 *
 * IMPORTANT — do not double-count OT: payroll_compute_period() ADDS
 * approved `overtime` table hours ON TOP OF any hours already implied by
 * daily_time_records.total_hours_rendered exceeding 8 for that day (the
 * pre-existing attendance-only OT auto-derivation, kept for backward
 * compatibility). Creating a Time Out here that is already >8h after Time
 * In on save_attendance.php AND an approved overtime row for the same
 * hours will double-count in the real payroll run. Use ONE of:
 *   (a) Time Out > 8h after Time In on the attendance record (item B), or
 *   (b) an exact 8h attendance day PLUS a separate approved OT session here.
 * The dashboard JS enforces this by disabling this section unless the
 * Time In/Time Out on the record are exactly 8 working hours apart.
 *
 * Every row this endpoint creates is tagged in Remarks so this file's own
 * action=delete can identify (and ONLY delete) rows this tool created — it
 * will never delete an overtime row without that tag.
 */
declare(strict_types=1);

require_once __DIR__ . '/_testing_guard.php';
require_once __DIR__ . '/_selection_context.php';

const TEST_OT_TAG = 'Created via Attendance Testing Tool (development only).';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST required.']);
    exit();
}

$raw = file_get_contents('php://input');
$json = json_decode($raw, true);
$body = is_array($json) ? $json : $_POST;

$action = trim((string)($body['action'] ?? 'create'));

function tot_hours_between(string $date, string $start, string $end): ?float
{
    $startTs = strtotime($date . ' ' . $start);
    $endTs = strtotime($date . ' ' . $end);
    if ($startTs === false || $endTs === false || $endTs <= $startTs) return null;
    return round(($endTs - $startTs) / 3600, 2);
}

try {
    if ($action === 'delete') {
        $overtimeId = (int)($body['overtime_id'] ?? 0);
        if ($overtimeId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'overtime_id is required.']);
            exit();
        }
        // Only ever delete a row this tool created — never a real Team-Lead
        // overtime record, even if the caller supplies an arbitrary ID.
        $tag = TEST_OT_TAG;
        $chk = $conn->prepare("SELECT OvertimeID,EmployeeID,OvertimeDate FROM overtime WHERE OvertimeID = ? AND Remarks = ? LIMIT 1");
        $chk->bind_param('is', $overtimeId, $tag);
        $chk->execute();
        $found = $chk->get_result()->fetch_assoc();
        $chk->close();

        if (!$found) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'No test-created overtime record with that ID was found. Only records created by this tool can be removed here.']);
            exit();
        }

        testing_employee_context($conn,$body,(int)$found['EmployeeID'],$found['OvertimeDate']);
        testing_assert_unlocked($conn,(int)$found['EmployeeID'],$found['OvertimeDate']);
        $del = $conn->prepare("DELETE FROM overtime WHERE OvertimeID = ?");
        $del->bind_param('i', $overtimeId);
        $del->execute();
        $del->close();

        echo json_encode(['success' => true, 'message' => 'Test overtime record removed.']);
        exit();
    }

    // action === 'create'
    $employeeId = (int)($body['employee_id'] ?? 0);
    $date = trim((string)($body['date'] ?? ''));
    $otStart = trim((string)($body['ot_start'] ?? ''));
    $otEnd = trim((string)($body['ot_end'] ?? ''));

    if ($employeeId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'employee_id is required.']);
        exit();
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'A valid date (YYYY-MM-DD) is required.']);
        exit();
    }
    if (!preg_match('/^\d{2}:\d{2}$/', $otStart) || !preg_match('/^\d{2}:\d{2}$/', $otEnd)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Valid Overtime Start/End (HH:MM) are required.']);
        exit();
    }
    $selectedDeployment=testing_employee_context($conn,$body,$employeeId,$date);
    testing_assert_unlocked($conn,$employeeId,$date);
    $hours = tot_hours_between($date, $otStart, $otEnd);
    if ($hours === null) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Overtime End must be after Overtime Start.']);
        exit();
    }
    if ($hours > 12.0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Overtime session exceeds the 12-hour sanity cap (same cap used by the Team Lead overtime tool).']);
        exit();
    }

    $empCheck = $conn->prepare("SELECT EmployeeID FROM employee WHERE EmployeeID = ? LIMIT 1");
    $empCheck->bind_param('i', $employeeId);
    $empCheck->execute();
    if (!$empCheck->get_result()->fetch_assoc()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Employee not found or inactive.']);
        exit();
    }
    $empCheck->close();

    // Existing overlap guard — same rule as the real Team Lead OT tool: no
    // two OT sessions for the same employee/date may overlap.
    $overlapStmt = $conn->prepare(
        "SELECT OvertimeID FROM overtime WHERE EmployeeID = ? AND OvertimeDate = ? AND OTStartTime < ? AND OTEndTime > ? LIMIT 1"
    );
    $overlapStmt->bind_param('isss', $employeeId, $date, $otEnd, $otStart);
    $overlapStmt->execute();
    $overlap = $overlapStmt->get_result()->fetch_assoc();
    $overlapStmt->close();
    if ($overlap) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'An overtime session already exists for this employee/date that overlaps this range.']);
        exit();
    }

    $deploymentId=(int)$selectedDeployment['DeploymentID'];

    $attStmt = $conn->prepare("SELECT AttendanceID FROM attendance WHERE EmployeeID = ? AND Date = ? LIMIT 1");
    $attStmt->bind_param('is', $employeeId, $date);
    $attStmt->execute();
    $attRow = $attStmt->get_result()->fetch_assoc();
    $attStmt->close();
    $attendanceId = $attRow ? (int)$attRow['AttendanceID'] : null;

    $statusStmt = $conn->prepare("SELECT StatusID FROM status WHERE Name = 'Approved' LIMIT 1");
    $statusStmt->execute();
    $statusRow = $statusStmt->get_result()->fetch_assoc();
    $statusStmt->close();
    $approvedStatusId = $statusRow ? (int)$statusRow['StatusID'] : null;

    $adminUserId = (int)$_SESSION['user_id'];
    $tag = TEST_OT_TAG;

    $insStmt = $conn->prepare("
        INSERT INTO overtime
            (EmployeeID, AttendanceID, DeploymentID, OvertimeDate, OTStartTime, OTEndTime,
             RegularHours, OvertimeHours, OvertimeType, RequestedBy, ApprovedBy, ApprovalDate, StatusID, Remarks, CreatedAt, UpdatedAt)
        VALUES (?, ?, ?, ?, ?, ?, 8.00, ?, 'Regular', ?, ?, NOW(), ?, ?, NOW(), NOW())
    ");
    $insStmt->bind_param(
        'iiisssdiiis',
        $employeeId,
        $attendanceId,
        $deploymentId,
        $date,
        $otStart,
        $otEnd,
        $hours,
        $adminUserId,
        $adminUserId,
        $approvedStatusId,
        $tag
    );
    $insStmt->execute();
    $overtimeId = (int)$conn->insert_id;
    $insStmt->close();

    echo json_encode([
        'success' => true,
        'overtime_id' => $overtimeId,
        'ot_hours' => $hours,
        'message' => 'Test overtime session saved and marked Approved. It will be included in the real payroll computation for this employee\'s cutoff.',
    ]);
} catch (Throwable $e) {
    error_log('[Testing/Attendance] save_test_overtime: ' . $e->getMessage());
    http_response_code($e instanceof DomainException ? ($e->getCode()?:422) : 500);
    echo json_encode(['success' => false, 'message' => $e instanceof DomainException ? $e->getMessage() : 'Failed to save test overtime record.', 'error_details' => $e->getMessage()]);
}