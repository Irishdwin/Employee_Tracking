<?php
/**
 * DEVELOPMENT / TESTING ONLY. POST — action=create|update
 *
 * Writes directly into the EXISTING production tables `attendance` and
 * `daily_time_records`, using the SAME dual-write pattern as
 * Api/Employee/clock_in_out_geofence.php (the real clock-in/out endpoint) —
 * so records created here are indistinguishable from real ones to every
 * downstream screen (Employee Dashboard, Smart Calendar, Team Lead roster,
 * DTR submission, Accounting, Payroll).
 *
 * Status/late-minutes/undertime-minutes are computed by calling
 * payroll_timesheet_day_status() — the SAME shared function the Employee
 * dashboard, Team Lead roster, and payroll computation all already use.
 * This file does not reimplement that logic; it only calls it.
 *
 * Body (JSON or form):
 *   action        create | update
 *   attendance_id required for update
 *   employee_id   required for create
 *   date          YYYY-MM-DD
 *   time_in       HH:MM (24h) — required
 *   time_out      HH:MM (24h) — optional (leave blank to simulate "still clocked in" / incomplete day)
 *   remarks       optional
 *
 * NOTE ON "ABSENT": there is no explicit Absent write path here. Absent is
 * inferred by production purely from the ABSENCE of a row on a scheduled
 * working day (see payroll_timesheet_day_status()) — so to simulate an
 * absence, simply don't create a record for that date, or delete_attendance.php
 * an existing one. This avoids inventing a parallel "Absent" record type.
 *
 * NOTE ON daily_time_records.status: that column's ENUM
 * ('Absent','Clocked In','On Break','Clocked Out','Late','Present') is the
 * raw real-time clock-state field the real clock-in/out endpoint writes —
 * it does NOT include 'Undertime' and is NOT what Employee/TeamLead
 * dashboards read for the sophisticated Present/Late/Undertime/Absent
 * label (that's recomputed live from time_in/time_out via
 * payroll_timesheet_day_status()). For a completed day (time_out present)
 * this file writes 'Clocked Out' here, matching what the real clock-out
 * endpoint itself writes — the accurate Present/Late/Undertime label still
 * comes from the same recomputation every other screen already relies on.
 */
declare(strict_types=1);

require_once __DIR__ . '/_testing_guard.php';
require_once __DIR__ . '/_selection_context.php';
require_once __DIR__ . '/_late_minutes.php';
require_once __DIR__ . '/../../../Api/Helpers/employee_payroll_helper.php'; // payroll_resolve_deployment_schedule(), payroll_timesheet_day_status() — REUSED
require_once __DIR__ . '/../../../Api/Helpers/attendance_pdo.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST required.']);
    exit();
}

$raw = file_get_contents('php://input');
$json = json_decode($raw, true);
$body = is_array($json) ? $json : $_POST;

$action = trim((string)($body['action'] ?? 'create'));
$date = trim((string)($body['date'] ?? ''));
$timeIn = trim((string)($body['time_in'] ?? ''));
$timeOut = trim((string)($body['time_out'] ?? ''));
$remarks = trim((string)($body['remarks'] ?? 'Created via Attendance Testing Tool (development only).'));
$remarks='Created via Attendance Testing Tool (development only). '. $remarks;
$transaction=false;
$hasLateMinutes=array_key_exists('late_minutes',$body)&&$body['late_minutes']!==''&&$body['late_minutes']!==null;

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || date('Y-m-d',strtotime($date))!==$date) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A valid date (YYYY-MM-DD) is required.']);
    exit();
}
if (!$hasLateMinutes && !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $timeIn)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A valid Time In (HH:MM) is required.']);
    exit();
}
if ($timeOut !== '' && !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $timeOut)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Time Out must be HH:MM or left blank.']);
    exit();
}

try {
    if(!in_array($action,['create','update'],true))throw new DomainException('Choose create or update.',400);
    if ($action === 'update') {
        $attendanceId = (int)($body['attendance_id'] ?? 0);
        if ($attendanceId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'attendance_id is required for update.']);
            exit();
        }
        $existStmt = $conn->prepare("SELECT EmployeeID, Date, DTRSubmissionID FROM attendance WHERE AttendanceID = ? LIMIT 1");
        $existStmt->bind_param('i', $attendanceId);
        $existStmt->execute();
        $existing = $existStmt->get_result()->fetch_assoc();
        $existStmt->close();

        if (!$existing) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Attendance record not found.']);
            exit();
        }
        if ($existing['DTRSubmissionID'] !== null) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'This record has already been submitted to Accounting and is locked. The testing tool will not edit submitted records.']);
            exit();
        }
        $employeeId = (int)$existing['EmployeeID'];
        $date = $existing['Date']; // date is not editable on update, matches production (one row per employee/day)
    } else {
        $employeeId = (int)($body['employee_id'] ?? 0);
        if ($employeeId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'employee_id is required for create.']);
            exit();
        }
    }

    $selectedDeployment=testing_employee_context($conn,$body,$employeeId,$date);
    testing_assert_unlocked($conn,$employeeId,$date);

    // Verify the employee actually exists and is active — never trust a
    // client-supplied ID blindly, even in a dev tool.
    $empCheck = $conn->prepare("SELECT EmployeeID FROM employee WHERE EmployeeID = ? LIMIT 1");
    $empCheck->bind_param('i', $employeeId);
    $empCheck->execute();
    if (!$empCheck->get_result()->fetch_assoc()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Employee not found or inactive.']);
        exit();
    }
    $empCheck->close();

    // Reuse the exact production schedule resolver + status calculator —
    // same mapping fix already applied in get_dtr_cutoff_review.php
    // ('break_minutes' vs 'break_duration' key name).
    $schedule = payroll_resolve_deployment_schedule($conn, $employeeId, $date);
    $schedule['break_minutes'] = $schedule['break_duration'] ?? 60;
    if($hasLateMinutes){
        try{$timeIn=testing_late_time_in($body['late_minutes'],$schedule);}catch(InvalidArgumentException $e){http_response_code(400);echo json_encode(['success'=>false,'message'=>$e->getMessage()]);exit;}
    }
    $overnight=substr($schedule['time_out'],0,5)<substr($schedule['time_in'],0,5);
    if($timeOut!=='' && $timeOut<=$timeIn && (!$overnight||$timeOut===$timeIn))throw new DomainException('Time Out must be after Time In unless the effective schedule is an overnight shift.',400);
    $outDate=$overnight && $timeOut!=='' && $timeOut<$timeIn ? date('Y-m-d',strtotime($date.' +1 day')) : $date;
    $fullTimeIn = $date . ' ' . $timeIn . ':00';
    $fullTimeOut = $timeOut !== '' ? ($outDate . ' ' . $timeOut . ':00') : null;

    $syntheticRow = [
        'time_in' => $fullTimeIn,
        'time_out' => $fullTimeOut,
        'break_time' => null,
        'total_hours_rendered' => null,
    ];
    $meta = payroll_timesheet_day_status($syntheticRow, $date, $schedule, date('Y-m-d'));

    $attendanceStatus = $meta['status']; // Present | Late | Undertime | Rest Day (valid free-text column, unlike the DTR enum below)
    $minutesLate = (int)($meta['late_minutes'] ?? 0);
    $undertimeMinutes = (int)($meta['undertime_minutes'] ?? 0);
    $totalHours = $meta['total_hours'] !== null ? (float)$meta['total_hours'] : null;

    // Derive holiday metadata from the same production engine, not a testing multiplier.
    $ratesStmt = $conn->prepare('SELECT daily_rate, hourly_rate, BasicSalary FROM employee WHERE EmployeeID = ?');
    $ratesStmt->bind_param('i', $employeeId); $ratesStmt->execute();
    $rates = $ratesStmt->get_result()->fetch_assoc(); $ratesStmt->close();
    $dailyRate = (float)($rates['daily_rate'] ?? 0);
    if ($dailyRate <= 0) $dailyRate = round((float)($rates['BasicSalary'] ?? 0) / 26, 2);
    $hourlyRate = (float)($rates['hourly_rate'] ?? 0);
    if ($hourlyRate <= 0) $hourlyRate = round($dailyRate / 8, 2);
    $dayPayroll = null;$payrollReview='';
    try { $dayPayroll = payroll_compute_period($conn, attendance_pdo($conn), $employeeId, $dailyRate, $hourlyRate, 0, 0, $date, $date, null, null, (int)$selectedDeployment['DeploymentID'], [$date=>$syntheticRow], false); } catch(InvalidArgumentException $e) {
        // Production intentionally requires Accounting review for incomplete/overnight holiday duty.
        $review=$e->getMessage();
        if(!str_contains($review,'requires Accounting review') && !str_contains($review,'requires review') && !str_contains($review,'requires confirmation'))throw $e;
        $payrollReview=$review;
    }
    $holiday = $dayPayroll['holiday_breakdown'][0] ?? null;
    $holidayId = $holiday['holiday_id'] ?? null;
    $holidayWork = $holiday && !empty($holiday['worked']) ? 1 : 0;
    $holidayRate = $holiday['pay_multiplier'] ?? null;
    if($payrollReview!==''){
        $holidayRows=holidays_fetch_for_range($conn,$date,$date,$employeeId,(int)$selectedDeployment['DeploymentID']);
        $holidayId=$holidayRows[$date][0]['id']??null;$holidayWork=$holidayId?1:0;$holidayRate=null;
        $remarks.=' Payroll review required: '.$payrollReview;
    }
    $isRestDay = (isset($schedule['is_working_day']) ? $schedule['is_working_day'] : payroll_is_scheduled_day($date, payroll_scheduled_weekdays($schedule['working_days']))) ? 0 : 1;
    $conn->begin_transaction();$transaction=true;
    site_query($conn,'SELECT EmployeeID FROM employee WHERE EmployeeID=? FOR UPDATE','i',[$employeeId]);
    testing_assert_unlocked($conn,$employeeId,$date);
    // Check for an existing row on this employee/date (one attendance row
    // per employee per day, same as production).
    $dupStmt = $conn->prepare("SELECT AttendanceID, DTRSubmissionID FROM attendance WHERE EmployeeID = ? AND Date = ? LIMIT 1 FOR UPDATE");
    $dupStmt->bind_param('is', $employeeId, $date);
    $dupStmt->execute();
    $dupRow = $dupStmt->get_result()->fetch_assoc();
    $dupStmt->close();

    if($dupRow && $dupRow['DTRSubmissionID']!==null)throw new DomainException('This attendance record is linked to a submitted DTR and is locked.',409);
    if ($action === 'update') {
        if(!$dupRow || (int)$dupRow['AttendanceID']!==(int)$body['attendance_id'])throw new DomainException('Attendance changed while this request was being prepared. Reload the record.',409);
        $attendanceId = (int)($body['attendance_id']);
        $updStmt = $conn->prepare("
            UPDATE attendance SET
                TimeIn = ?, TimeOut = ?, TotalHours = ?, attendance_status = ?,
                minutes_late = ?, LateMinutes = ?, UndertimeMinutes = ?, Remarks = ?,
                geofence_status = 'Inside Geofence (Test Data)'
            WHERE AttendanceID = ?
        ");
        $updStmt->bind_param(
            'ssdsiiisi',
            $fullTimeIn,
            $fullTimeOut,
            $totalHours,
            $attendanceStatus,
            $minutesLate,
            $minutesLate,
            $undertimeMinutes,
            $remarks,
            $attendanceId
        );
        $updStmt->execute();
        $updStmt->close();
    } elseif ($dupRow || site_query($conn,'SELECT id FROM daily_time_records WHERE employee_id=? AND date=? LIMIT 1','is',[$employeeId,$date])->fetch_assoc()) {
        throw new DomainException('Attendance already exists for this employee/date. Use Edit Record explicitly; existing attendance was not changed.',409);
    } else {
        $insStmt = $conn->prepare("
            INSERT INTO attendance (
                EmployeeID, Date, TimeIn, TimeOut, TotalHours, attendance_status,
                minutes_late, LateMinutes, UndertimeMinutes, geofence_status, Remarks
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Inside Geofence (Test Data)', ?)
        ");
        $insStmt->bind_param(
            'isssdsiiis',
            $employeeId,
            $date,
            $fullTimeIn,
            $fullTimeOut,
            $totalHours,
            $attendanceStatus,
            $minutesLate,
            $minutesLate,
            $undertimeMinutes,
            $remarks
        );
        $insStmt->execute();
        $attendanceId = (int)$conn->insert_id;
        $insStmt->close();
    }

    // Mirror into daily_time_records — the table Employee/TeamLead dashboards
    // actually read for status recomputation. 'status' here is the raw
    // real-time clock-state (see file header note), not the sophisticated
    // Present/Late/Undertime label.
    $dtrStatus = $fullTimeOut !== null ? 'Clocked Out' : ($minutesLate > 0 ? 'Late' : 'Clocked In');

    $dtrCheck = $conn->prepare("SELECT id FROM daily_time_records WHERE employee_id = ? AND date = ? LIMIT 1");
    $dtrCheck->bind_param('is', $employeeId, $date);
    $dtrCheck->execute();
    $dtrRow = $dtrCheck->get_result()->fetch_assoc();
    $dtrCheck->close();

    if ($dtrRow) {
        $dtrUpd = $conn->prepare("
            UPDATE daily_time_records SET
                time_in = ?, time_out = ?, total_hours_rendered = ?, status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $dtrUpd->bind_param('ssdsi', $fullTimeIn, $fullTimeOut, $totalHours, $dtrStatus, $dtrRow['id']);
        $dtrUpd->execute();
        $dtrUpd->close();
    } else {
        $dtrIns = $conn->prepare("
            INSERT INTO daily_time_records (employee_id, date, time_in, time_out, total_hours_rendered, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $dtrIns->bind_param('isssds', $employeeId, $date, $fullTimeIn, $fullTimeOut, $totalHours, $dtrStatus);
        $dtrIns->execute();
        $dtrIns->close();
    }

    $metaStmt = $conn->prepare('UPDATE attendance SET HolidayID=?, IsHolidayWork=?, HolidayPayRate=?, IsRestDay=? WHERE AttendanceID=?');
    $metaStmt->bind_param('iidii', $holidayId, $holidayWork, $holidayRate, $isRestDay, $attendanceId);
    $metaStmt->execute(); $metaStmt->close();

    $evidenceDep=(int)$selectedDeployment['DeploymentID'];$evidenceStmt=$conn->prepare('UPDATE attendance SET EvidenceDeploymentID=? WHERE AttendanceID=?');$evidenceStmt->bind_param('ii',$evidenceDep,$attendanceId);$evidenceStmt->execute();$evidenceStmt->close();
    require_once __DIR__.'/../../../Api/Helpers/AuditLogger.php';
    if(!AuditLogger::log($conn,'Attendance Testing',$action,'Test attendance '.$date.' employee '.$employeeId.' deployment '.$selectedDeployment['DeploymentID'],(string)$attendanceId))throw new RuntimeException('Attendance audit failed.');
    $conn->commit();$transaction=false;
    echo json_encode([
        'success' => true,
        'attendance_id' => $attendanceId,
        'computed_status' => $attendanceStatus,
        'minutes_late' => $minutesLate,
        'undertime_minutes' => $undertimeMinutes,
        'total_hours' => $totalHours,
        'payroll_review' => $payrollReview,
        'message' => 'Test attendance record saved. It will now appear on the Employee Dashboard, Smart Calendar, and Team Lead roster like any real record.'.($payrollReview!==''?' Payroll was not valued: '.$payrollReview:''),
    ]);
} catch (Throwable $e) {
    error_log('[Testing/Attendance] save_attendance: ' . $e->getMessage());
    if($transaction)$conn->rollback();
    http_response_code($e instanceof DomainException ? ($e->getCode()?:422) : 500);
    echo json_encode(['success' => false, 'message' => $e instanceof DomainException ? $e->getMessage() : 'Failed to save test attendance record. No changes were committed.']);
}
