<?php
/**
 * GET — logged-in employee's today status + historical duty logs with computed timesheet fields (Asia/Manila time).
 */
require_once __DIR__ . '/../Common/session_init.php';
date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (trim((string)$_SESSION['role']) !== 'Employee') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit();
}

$limit = isset($_GET['limit']) ? min(90, max(1, (int)$_GET['limit'])) : 30;
$userId = (int)$_SESSION['user_id'];
$today = date('Y-m-d');

require_once __DIR__ . '/../Db_connection/db_connect.php'; // mysqli $conn
require_once __DIR__ . '/../Helpers/attendance_pdo.php';
require_once __DIR__ . '/../Helpers/dtr_qr_logic.php';
require_once __DIR__ . '/../Helpers/employee_deployment_context.php';
require_once __DIR__ . '/../Helpers/employee_payroll_helper.php';
require_once __DIR__ . '/../Helpers/holidays_helper.php';

try {
    $rangeStart=null; $rangeEnd=null;
    if (isset($_GET['start']) || isset($_GET['end'])) {
        try {
            $rangeStart=team_schedule_date((string)($_GET['start']??''));
            $rangeEnd=team_schedule_date((string)($_GET['end']??''));
            if ($rangeEnd<$rangeStart || (strtotime($rangeEnd)-strtotime($rangeStart))/86400>61) throw new InvalidArgumentException('Choose a date range of at most 62 days.');
        } catch (InvalidArgumentException $e) { http_response_code(422); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); exit(); }
    }
    $pdo = attendance_pdo();
    attendance_ensure_table($pdo);

    $empStmt = $pdo->prepare("
        SELECT e.EmployeeID, e.EmployeeNumber, e.FirstName, e.LastName, e.Position
        FROM users u
        INNER JOIN employee e ON e.Email = u.Email AND e.IsActive = 1
        WHERE u.UsersID = ?
        LIMIT 1
    ");
    $empStmt->execute([$userId]);
    $employee = $empStmt->fetch();

    if (!$employee) {
        echo json_encode([
            'success' => true,
            'has_employee' => false,
            'message' => 'No employee profile linked to this account.',
            'today' => null,
            'logs' => [],
        ]);
        exit();
    }

    $hiredContext = null;
    try {
        if (function_exists('employee_fetch_hired_placement_context') && isset($conn)) {
            $hiredContext = employee_fetch_hired_placement_context($conn, $userId);
        }
    } catch (Throwable $ex) {
        $hiredContext = null;
    }

    $workSchedule = $hiredContext ? trim((string)($hiredContext['work_schedule'] ?? '')) : '';
    $shiftStart = function_exists('payroll_parse_shift_start') ? payroll_parse_shift_start($workSchedule !== '' ? $workSchedule : null) : '08:00:00';
    $scheduledWeekdays = function_exists('payroll_scheduled_weekdays') ? payroll_scheduled_weekdays($workSchedule !== '' ? $workSchedule : 'Mon-Fri') : [1,2,3,4,5];

    $employeeId = (int)$employee['EmployeeID'];
    $breakMinutes = function_exists('payroll_get_break_minutes') && isset($conn)
        ? payroll_get_break_minutes($conn, $employeeId)
        : 60;

    $todayStmt = $pdo->prepare('SELECT * FROM daily_time_records WHERE employee_id = ? AND date = ? LIMIT 1');
    $todayStmt->execute([$employeeId, $today]);
    $todayRow = $todayStmt->fetch();

    $formatTimeManila = static function (?string $datetime): string {
        if (empty($datetime)) return '—';
        $dt = new DateTime($datetime, new DateTimeZone('Asia/Manila'));
        return $dt->format('h:i A');
    };

    $formatBreakDisplay = static function (int $minutes): string {
        if ($minutes <= 0) return '—';
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        if ($h > 0 && $m > 0) return $h . 'h ' . $m . 'm';
        if ($h > 0) return $h . 'h';
        return $m . 'm';
    };

    // Real holiday info keyed by date, from the SAME `holidays` table the
    // production payroll engine reads (holidays_fetch_for_range() —
    // Api/Helpers/holidays_helper.php). The actual peso holiday-pay figure
    // already surfaces correctly on the Employee's own Payroll tab via
    // payroll_compute_period() (Api/Employee/get_employee_payroll.php) — this
    // only attaches which dates were holidays, for the Timesheet/Calendar to
    // indicate, not a second payroll calculation.
    // $logs below is the $limit most recent daily_time_records ROWS, which
    // can span a wider calendar range than $limit days if there are gaps
    // (e.g. weekends/rest days with no row) — widen the lookup window so a
    // day's holiday flag isn't missed just because of such gaps.
    $holidayRangeStart = date('Y-m-d', strtotime('-' . ($limit * 3) . ' days', strtotime($today)));
    $holidaysByDate = holidays_fetch_for_range($conn, $rangeStart ?? $holidayRangeStart, $rangeEnd ?? $today);

    $formatEntry = static function (?array $row, string $isoDate) use ($conn, $employeeId, $today, $formatTimeManila, $formatBreakDisplay, $holidaysByDate): array {
        $schedule = payroll_resolve_deployment_schedule($conn, $employeeId, $isoDate);
        $holiday = isset($holidaysByDate[$isoDate]) ? $holidaysByDate[$isoDate][0] : null;
        if (!$row) {
            $meta = function_exists('payroll_timesheet_day_status') 
                ? payroll_timesheet_day_status(null, $isoDate, $schedule, $today)
                : ['status' => 'No record', 'total_hours' => null, 'break_minutes' => 0, 'undertime_minutes' => null, 'is_late' => false];
            return [
                'date' => $isoDate,
                'status' => $meta['status'] ?? 'No record',
                'attendance_status' => $meta['status'] ?? 'No record',
                'time_in_display' => '—',
                'break_time_display' => '—',
                'time_out_display' => '—',
                'break_minutes' => 0,
                'total_hours' => null,
                'total_hours_rendered' => null,
                'undertime_minutes' => null,
                'is_late' => false,
                'holiday' => $holiday,
            ];
        }

        $totalHours = function_exists('payroll_compute_total_hours')
            ? payroll_compute_total_hours(
                $row['time_in'] ?? null,
                $row['break_time'] ?? null,
                $row['time_out'] ?? null,
                $row['total_hours_rendered'] !== null ? (float)$row['total_hours_rendered'] : null
              )
            : null;
            
        $row['total_hours_rendered'] = $totalHours;
        $meta = function_exists('payroll_timesheet_day_status')
            ? payroll_timesheet_day_status($row, $isoDate, $schedule, $today)
            : ['status' => $row['status'] ?? 'Present', 'total_hours' => $totalHours, 'break_minutes' => 0, 'undertime_minutes' => null, 'is_late' => false];

        return [
            'id' => (int)($row['id'] ?? 0),
            'date' => $row['date'] ?? $isoDate,
            'status' => $row['status'] ?? 'Present',
            'attendance_status' => $meta['status'] ?? 'Present',
            'time_in_display' => $formatTimeManila($row['time_in'] ?? null),
            'break_time_display' => $formatBreakDisplay((int)($meta['break_minutes'] ?? 0)),
            'time_out_display' => $formatTimeManila($row['time_out'] ?? null),
            'break_minutes' => $meta['break_minutes'] ?? 0,
            'total_hours' => $meta['total_hours'] ?? $totalHours,
            'total_hours_rendered' => $meta['total_hours'] ?? $totalHours,
            'undertime_minutes' => $meta['undertime_minutes'] ?? null,
            'is_late' => $meta['is_late'] ?? false,
            'updated_at' => $row['updated_at'] ?? null,
            'holiday' => $holiday,
        ];
    };

    $todayPayload = $formatEntry($todayRow ?: null, $today);
    $todaySchedule = payroll_resolve_deployment_schedule($conn, $employeeId, $today);
    $todayPayload['expected_schedule'] = !empty($todaySchedule['deployment_id']) ? team_schedule_public($todaySchedule, $today) : null;
    $todayPayload['employee_id'] = $employeeId;
    $todayPayload['time_in'] = $todayRow['time_in'] ?? null;
    $todayPayload['break_time'] = $todayRow['break_time'] ?? null;
    $todayPayload['time_out'] = $todayRow['time_out'] ?? null;

    $logSql='SELECT id,date,time_in,break_time,time_out,total_hours_rendered,status,updated_at FROM daily_time_records WHERE employee_id=?';
    if ($rangeStart!==null) $logSql.=' AND date BETWEEN ? AND ?';
    $logSql.=' ORDER BY date DESC,id DESC LIMIT ?';
    $logStmt=$pdo->prepare($logSql);
    $logStmt->bindValue(1,$employeeId,PDO::PARAM_INT);
    if ($rangeStart!==null) {
        $logStmt->bindValue(2,$rangeStart); $logStmt->bindValue(3,$rangeEnd); $logStmt->bindValue(4,90,PDO::PARAM_INT);
    } else $logStmt->bindValue(2,$limit,PDO::PARAM_INT);
    $logStmt->execute();

    $logs = [];
    $logByDate = [];
    $hoursThisWeek = 0.0;
    $weekStart = date('Y-m-d', strtotime('monday this week'));

    while ($row = $logStmt->fetch()) {
        $entry = $formatEntry($row, $row['date']);
        $logs[] = $entry;
        $logByDate[$row['date']] = $entry;
        if ($row['date'] >= $weekStart && $entry['total_hours'] !== null) {
            $hoursThisWeek += (float)$entry['total_hours'];
        }
    }

    // Overtime sessions the Team Lead has recorded for this employee, covering
    // the same window as $logs above (most recent $limit duty days). Kept as a
    // separate list (not merged into `logs`) since OT is a separate session
    // from regular Time In/Time Out, per the deployment's regular attendance.
    $overtime = [];
    $otStmt = $conn->prepare("
        SELECT ot.OvertimeDate, ot.OTStartTime, ot.OTEndTime, ot.OvertimeHours, ot.Remarks,
               s.Name AS status_name
        FROM overtime ot
        LEFT JOIN status s ON s.StatusID = ot.StatusID
        WHERE ot.EmployeeID = ?
        ORDER BY ot.OvertimeDate DESC
        LIMIT ?
    ");
    if ($otStmt) {
        $otStmt->bind_param('ii', $employeeId, $limit);
        $otStmt->execute();
        $otRes = $otStmt->get_result();
        while ($otRow = $otRes->fetch_assoc()) {
            $overtime[] = [
                'date' => $otRow['OvertimeDate'],
                'ot_start_display' => $otRow['OTStartTime'] ? date('h:i A', strtotime($otRow['OTStartTime'])) : '—',
                'ot_end_display' => $otRow['OTEndTime'] ? date('h:i A', strtotime($otRow['OTEndTime'])) : '—',
                'ot_hours' => $otRow['OvertimeHours'] !== null ? (float)$otRow['OvertimeHours'] : null,
                'status' => $otRow['status_name'] ?? 'Pending',
                'remarks' => $otRow['Remarks'],
            ];
        }
        $otStmt->close();
    }

    echo json_encode([
        'success' => true,
        'has_employee' => true,
        'date' => $today,
        'shift_start' => $shiftStart,
        'work_schedule' => $workSchedule,
        'employee' => [
            'employee_id' => $employeeId,
            'employee_number' => $employee['EmployeeNumber'],
            'employee_name' => trim($employee['FirstName'] . ' ' . $employee['LastName']),
            'position' => $employee['Position'],
        ],
        'today' => $todayPayload,
        'logs' => $logs,
        'log_by_date' => $logByDate,
        'hours_this_week' => round($hoursThisWeek, 2),
        'overtime' => $overtime,
    ]);
} catch (Throwable $e) {
    error_log('get_employee_attendance exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load attendance: ' . $e->getMessage()]);
}
