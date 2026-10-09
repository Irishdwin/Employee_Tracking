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
require_once __DIR__ . '/_selection_context.php';
require_once __DIR__ . '/../../../Api/Helpers/employee_payroll_helper.php'; // payroll_resolve_deployment_schedule() — REUSED, not duplicated

$employeeId = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
if ($employeeId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'employee_id is required.']);
    exit();
}

try {
    $today = (string)($_GET['date']??date('Y-m-d'));
    $selectedDeployment=testing_employee_context($conn,$_GET,$employeeId,$today);
    $schedule = payroll_resolve_deployment_schedule($conn, $employeeId, $today);

    // Read-only display rates use the same production precedence. Actual
    // computation re-resolves salary fields on the server independently.
    $rateStmt = $conn->prepare("SELECT daily_rate, hourly_rate, BasicSalary FROM employee WHERE EmployeeID = ? LIMIT 1");
    $rateStmt->bind_param('i', $employeeId);
    $rateStmt->execute();
    $rateRow = $rateStmt->get_result()->fetch_assoc();
    $rateStmt->close();
    require_once __DIR__.'/../../../Api/Helpers/employee_payroll_rates.php';
    $resolvedRates=payroll_employee_rates($rateRow?:[],true);
    $dailyRate=$resolvedRates['daily_rate'];$hourlyRate=$resolvedRates['hourly_rate'];

    $stmt = $conn->prepare("
        SELECT AttendanceID, Date, TimeIn, TimeOut, attendance_status, minutes_late, DTRSubmissionID
        FROM attendance
        WHERE EmployeeID = ? AND Date >= ? AND (? IS NULL OR Date <= ?)
        ORDER BY Date DESC
        LIMIT 30
    ");
    $stmt->bind_param('isss', $employeeId, $selectedDeployment['StartDate'], $selectedDeployment['EndDate'], $selectedDeployment['EndDate']);
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

    // Test-created overtime sessions only (tagged Remarks — see
    // save_test_overtime.php), so the dashboard can list/remove them for
    // cleanup without ever touching a real Team-Lead-requested OT record.
    $testOtTag = 'Created via Attendance Testing Tool (development only).';
    $otStmt = $conn->prepare("
        SELECT OvertimeID, OvertimeDate, OTStartTime, OTEndTime, OvertimeHours
        FROM overtime
        WHERE EmployeeID = ? AND Remarks = ? AND DeploymentID = ?
        ORDER BY OvertimeDate DESC
        LIMIT 30
    ");
    $otStmt->bind_param('isi', $employeeId, $testOtTag, $selectedDeployment['DeploymentID']);
    $otStmt->execute();
    $otRes = $otStmt->get_result();
    $testOvertime = [];
    while ($row = $otRes->fetch_assoc()) {
        $testOvertime[] = [
            'overtime_id' => (int)$row['OvertimeID'],
            'date' => $row['OvertimeDate'],
            'ot_start' => $row['OTStartTime'] ? substr($row['OTStartTime'], 0, 5) : null,
            'ot_end' => $row['OTEndTime'] ? substr($row['OTEndTime'], 0, 5) : null,
            'hours' => isset($row['OvertimeHours']) ? (float)$row['OvertimeHours'] : null,
        ];
    }
    $otStmt->close();

    echo json_encode([
        'success' => true,
        'context' => ['company_id'=>(int)$selectedDeployment['CompanyID'],'site_id'=>(int)$selectedDeployment['SiteID'],'team_lead_id'=>(int)$_GET['team_lead_id'],'deployment_id'=>(int)$selectedDeployment['DeploymentID'],'payroll_type'=>testing_lead_site($conn,$_GET)['PayrollType']],
        'schedule' => [
            'time_in' => substr($schedule['time_in'], 0, 5),
            'time_out' => substr($schedule['time_out'], 0, 5),
            'break_duration' => $schedule['break_duration'],
            'grace_period' => $schedule['grace_period'],
            'working_days' => $schedule['working_days'],
            'daily_rate' => $dailyRate,
            'hourly_rate' => $hourlyRate,
        ],
        'records' => $records,
        'test_overtime' => $testOvertime,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load attendance records.']);
}
