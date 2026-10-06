<?php
/**
 * DEVELOPMENT / TESTING ONLY. POST — attendance_id
 *
 * Deletes a test attendance record from BOTH `attendance` and
 * `daily_time_records`. Refuses to delete anything already linked to a
 * DTR submission (DTRSubmissionID IS NOT NULL) — once a record has gone to
 * Accounting, it's out of scope for this dev tool, matching "DO NOT bypass
 * Accounting" from the spec.
 *
 * This is also the intended way to simulate an "Absent" day: since Absent
 * is inferred purely from the ABSENCE of a row on a scheduled day (see
 * payroll_timesheet_day_status() in Api/Helpers/employee_payroll_helper.php),
 * deleting a previously-created test record for a past date makes it read
 * as Absent everywhere, with no separate "Absent" write path needed.
 */
declare(strict_types=1);

require_once __DIR__ . '/_testing_guard.php';
require_once __DIR__ . '/_selection_context.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST required.']);
    exit();
}

$raw = file_get_contents('php://input');
$json = json_decode($raw, true);
$body = is_array($json) ? $json : $_POST;

$attendanceId = (int)($body['attendance_id'] ?? 0);
if ($attendanceId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'attendance_id is required.']);
    exit();
}

try {
    $stmt = $conn->prepare("SELECT EmployeeID, Date, DTRSubmissionID FROM attendance WHERE AttendanceID = ? LIMIT 1");
    $stmt->bind_param('i', $attendanceId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Attendance record not found.']);
        exit();
    }
    if ($row['DTRSubmissionID'] !== null) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'This record has already been submitted to Accounting and is locked. The testing tool will not delete submitted records.']);
        exit();
    }

    $employeeId = (int)$row['EmployeeID'];
    $date = $row['Date'];
    testing_employee_context($conn,$body,$employeeId,$date);

    $delAtt = $conn->prepare("DELETE FROM attendance WHERE AttendanceID = ?");
    $delAtt->bind_param('i', $attendanceId);
    $delAtt->execute();
    $delAtt->close();

    $delDtr = $conn->prepare("DELETE FROM daily_time_records WHERE employee_id = ? AND date = ?");
    $delDtr->bind_param('is', $employeeId, $date);
    $delDtr->execute();
    $delDtr->close();

    echo json_encode([
        'success' => true,
        'message' => 'Test attendance record deleted. This date will now read as Absent (if it is a past scheduled working day) or simply have no record.',
    ]);
} catch (Throwable $e) {
    error_log('[Testing/Attendance] delete_attendance: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to delete test attendance record.']);
}
