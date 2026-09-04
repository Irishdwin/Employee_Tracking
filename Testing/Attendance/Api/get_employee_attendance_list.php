<?php
/**
 * DEVELOPMENT / TESTING ONLY. GET ?employee_id=X
 * Returns the employee's resolved duty schedule (reused directly from the
 * production helper — no duplicated schedule logic) plus their most recent
 * attendance rows, so the tool can be used to edit/delete previously-created
 * test records.
 */
declare(strict_types=1);

require_once __DIR__ . '/_testing_guard.php';
require_once __DIR__ . '/../../../Api/Helpers/employee_payroll_helper.php'; // payroll_resolve_deployment_schedule() — REUSED, not duplicated

$employeeId = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
if ($employeeId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'employee_id is required.']);
    exit();
}

try {
    $today = date('Y-m-d');
    $schedule = payroll_resolve_deployment_schedule($conn, $employeeId, $today);

    $stmt = $conn->prepare("
        SELECT AttendanceID, Date, TimeIn, TimeOut, attendance_status, minutes_late, DTRSubmissionID
        FROM attendance
        WHERE EmployeeID = ?
        ORDER BY Date DESC
        LIMIT 30
    ");
    $stmt->bind_param('i', $employeeId);
    $stmt->execute();
    $res = $stmt->get_result();

    $records = [];
    while ($row = $res->fetch_assoc()) {
        $records[] = [
            'attendance_id' => (int)$row['AttendanceID'],
            'date' => $row['Date'],
            'time_in' => $row['TimeIn'] ? date('H:i', strtotime($row['TimeIn'])) : null,
            'time_out' => $row['TimeOut'] ? date('H:i', strtotime($row['TimeOut'])) : null,
            'status' => $row['attendance_status'],
            'minutes_late' => (int)$row['minutes_late'],
            'locked' => $row['DTRSubmissionID'] !== null, // already submitted to Accounting — testing tool won't edit/delete these
        ];
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'schedule' => [
            'time_in' => substr($schedule['time_in'], 0, 5),
            'time_out' => substr($schedule['time_out'], 0, 5),
            'break_duration' => $schedule['break_duration'],
            'grace_period' => $schedule['grace_period'],
            'working_days' => $schedule['working_days'],
        ],
        'records' => $records,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load attendance records.']);
}
