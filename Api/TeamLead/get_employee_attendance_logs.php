<?php
/**
 * GET — a Team Lead's view of ONE of their employees' attendance history,
 * with computed timesheet fields (Asia/Manila time), plus that day's
 * clock-in/clock-out photo paths for the "View" action.
 *
 * FIX (was previously broken): this file lived under Api/TeamLead/ and was
 * already being called with ?employee_id=<target> by
 * Js/Teamlead/tl_attendance_sync.js, but its body was actually a copy of
 * the EMPLOYEE's own "my attendance" endpoint — it required
 * $_SESSION['role'] === 'Employee' (guaranteeing 403 for every Team Lead)
 * and completely ignored ?employee_id=, resolving the target employee from
 * the logged-in session instead. Fixed below: Team Lead role check +
 * required ?employee_id= + ownership verified via
 * deployment.SiteSuperVisorID (same pattern already used in
 * get_overtime_roster.php / get_dtr_cutoff_review.php) — a Team Lead can
 * only ever see logs for an employee actually deployed under them.
 *
 * All of the original computation logic below (schedule resolution, leave
 * lookup, status calculation, Smart Calendar range) is UNCHANGED — only
 * identity resolution and authorization were rewritten.
 */
require_once __DIR__ . '/../Common/session_init.php';
date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

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

$teamLeadUserId = (int)$_SESSION['user_id'];
$requestedEmployeeId = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
if ($requestedEmployeeId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'employee_id is required.']);
    exit();
}

require_once __DIR__ . '/../Db_connection/db_connect.php'; // mysqli $conn (needed early for the ownership check)

// Ownership check: this employee must actually be deployed under the
// logged-in Team Lead. Never trust employee_id alone.
require_once __DIR__ . '/../Helpers/work_site_geofence.php';
$ownershipRow=site_owned_employee($conn,$teamLeadUserId,$requestedEmployeeId,(int)($_GET['site_id']??0));

if (!$ownershipRow) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'This employee is not on your team.']);
    exit();
}

$limit = isset($_GET['limit']) ? min(90, max(1, (int)$_GET['limit'])) : 30;
$employeeId = $requestedEmployeeId;
$today = date('Y-m-d');

// Optional ?month=YYYY-MM — the Smart Calendar's month being viewed. Defaults
// to the current month so the existing "today status" polling (which never
// sends this param) keeps working exactly as before.
$monthParam = (isset($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', (string)$_GET['month']))
    ? (string)$_GET['month']
    : date('Y-m');
if(isset($_GET['month'])) {
    $parsedMonth=DateTimeImmutable::createFromFormat('!Y-m',(string)$_GET['month']);
    if(!$parsedMonth || $parsedMonth->format('Y-m') !== (string)$_GET['month']) {
        http_response_code(422);echo json_encode(['success'=>false,'message'=>'Enter a valid attendance month.']);exit;
    }
}
$calendarRangeStart = $monthParam . '-01';
$calendarRangeEnd = date('Y-m-t', strtotime($calendarRangeStart));

require_once __DIR__ . '/../Helpers/attendance_pdo.php';
require_once __DIR__ . '/../Helpers/dtr_qr_logic.php';
require_once __DIR__ . '/../Helpers/employee_deployment_context.php';
require_once __DIR__ . '/../Helpers/employee_payroll_helper.php';

try {
    $pdo = attendance_pdo($conn);
    attendance_ensure_table($pdo);

    $empStmt = $pdo->prepare("
        SELECT EmployeeID, EmployeeNumber, FirstName, LastName, Position
        FROM employee
        WHERE EmployeeID = ? AND IsActive = 1
        LIMIT 1
    ");
    $empStmt->execute([$employeeId]);
    $employee = $empStmt->fetch();

    if (!$employee) {
        echo json_encode([
            'success' => true,
            'has_employee' => false,
            'message' => 'Employee not found or inactive.',
            'today' => null,
            'logs' => [],
        ]);
        exit();
    }

    $employeeId = (int)$employee['EmployeeID'];

    // The applicant/job-order "hired placement context" lookup below is keyed
    // by UsersID and is Employee-self-service oriented (it existed in the
    // original Employee-facing version of this file). It isn't needed for
    // the Team Lead's view — the authoritative schedule already comes from
    // payroll_resolve_deployment_schedule() below — so it's skipped here
    // rather than resolving a UsersID we don't otherwise need.
    $workSchedule = '';

    // Authoritative duty-schedule resolver: the employee's actual assigned
    // deployment schedule (deployment.TimeIn/TimeOut/BreakDuration/GracePeriod/
    // WorkingDays), the same source dashboard_data.php and get_duty_schedule.php
    // already read from — resolved per-date since a deployment can change over
    // time, and cached so a given date is only looked up once per request.
    $scheduleCache = [];
    $resolveSchedule = static function (string $isoDate) use ($conn, $employeeId, &$scheduleCache): array {
        if (isset($scheduleCache[$isoDate])) {
            return $scheduleCache[$isoDate];
        }
        $dep = (function_exists('payroll_resolve_deployment_schedule') && isset($conn))
            ? payroll_resolve_deployment_schedule($conn, $employeeId, $isoDate)
            : ['time_in' => '08:00:00', 'time_out' => '17:00:00', 'break_duration' => 60, 'grace_period' => 5, 'working_days' => 'Mon-Fri', 'shift_name' => 'Regular Shift'];
        $schedule = [
            'working_days' => $dep['working_days'] ?? 'Mon-Fri',
            'hired_date' => $dep['hired_date'] ?? null,
            'time_in' => $dep['time_in'] ?? '08:00:00',
            'grace_period' => (int)($dep['grace_period'] ?? 5),
            'break_minutes' => (int)($dep['break_duration'] ?? 60),
        ];
        $scheduleCache[$isoDate] = $schedule;
        return $schedule;
    };
    // Kept for the response payload / backward compatibility (display-only).
    $shiftStart = $resolveSchedule($today)['time_in'];

    // Approved-leave lookup (existing `leaves` table + `status` table, the same
    // Approved-only pattern already used for overtime approval elsewhere in this
    // project). Covers whichever is wider: the requested calendar month or today,
    // so both the calendar grid and the "today" status agree on leave coverage.
    $leaveLookupStart = min($calendarRangeStart, $today);
    $leaveLookupEnd = max($calendarRangeEnd, $today);
    $approvedLeaveRanges = [];
    $leaveStmt = $conn->prepare("
        SELECT l.StartDate, l.EndDate
        FROM leaves l
        INNER JOIN status s ON s.StatusID = l.StatusID AND s.Name = 'Approved'
        WHERE l.EmployeeID = ? AND l.StartDate <= ? AND l.EndDate >= ?
    ");
    if ($leaveStmt) {
        $leaveStmt->bind_param('iss', $employeeId, $leaveLookupEnd, $leaveLookupStart);
        $leaveStmt->execute();
        $leaveRes = $leaveStmt->get_result();
        while ($leaveRow = $leaveRes->fetch_assoc()) {
            $approvedLeaveRanges[] = [$leaveRow['StartDate'], $leaveRow['EndDate']];
        }
        $leaveStmt->close();
    }
    $isOnLeave = static function (string $isoDate) use ($approvedLeaveRanges): bool {
        foreach ($approvedLeaveRanges as $range) {
            if ($isoDate >= $range[0] && $isoDate <= $range[1]) {
                return true;
            }
        }
        return false;
    };

    $todayStmt = $pdo->prepare('SELECT * FROM daily_time_records WHERE employee_id = ? AND date = ? LIMIT 1');
    $todayStmt->execute([$employeeId, $today]);
    $todayRow = $todayStmt->fetch();

    // Clock-in/out photo paths + GPS, for the Team Lead's "View" action.
    // These live on the `attendance` table (see Api/Employee/clock_in_out_geofence.php),
    // not on daily_time_records — joined here by (EmployeeID, Date) so each
    // day's log entry can carry its own photos without a second round trip.
    $attendancePhotosByDate = [];
    $photoLookupStart = min($calendarRangeStart, date('Y-m-d', strtotime($today . ' -95 days')));
    $photoStmt = $conn->prepare("
        SELECT AttendanceID, Date, clockin_photo, clockout_photo, clockin_latitude, clockin_longitude,
               clockout_latitude, clockout_longitude, distance_from_site
        FROM attendance
        WHERE EmployeeID = ? AND Date BETWEEN ? AND ?
    ");
    if ($photoStmt) {
        $photoStmt->bind_param('iss', $employeeId, $photoLookupStart, $calendarRangeEnd);
        $photoStmt->execute();
        $photoRes = $photoStmt->get_result();
        while ($photoRow = $photoRes->fetch_assoc()) {
            $attendancePhotosByDate[$photoRow['Date']] = [
                'clockin_photo' => $photoRow['clockin_photo'] ? 'Api/Common/attendance_evidence.php?attendance_id='.(int)$photoRow['AttendanceID'].'&type=clockin' : null,
                'clockout_photo' => $photoRow['clockout_photo'] ? 'Api/Common/attendance_evidence.php?attendance_id='.(int)$photoRow['AttendanceID'].'&type=clockout' : null,
                'clockin_latitude' => $photoRow['clockin_latitude'],
                'clockin_longitude' => $photoRow['clockin_longitude'],
                'clockout_latitude' => $photoRow['clockout_latitude'],
                'clockout_longitude' => $photoRow['clockout_longitude'],
                'distance_from_site' => $photoRow['distance_from_site'],
            ];
        }
        $photoStmt->close();
    }

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

    $formatEntry = static function (?array $row, string $isoDate) use ($resolveSchedule, $today, $formatTimeManila, $formatBreakDisplay, $isOnLeave, $attendancePhotosByDate): array {
        $schedule = $resolveSchedule($isoDate);
        $onLeave = $isOnLeave($isoDate);
        $photos = $attendancePhotosByDate[$isoDate] ?? null;

        if (!$row) {
            $meta = function_exists('payroll_timesheet_day_status')
                ? payroll_timesheet_day_status(null, $isoDate, $schedule, $today)
                : ['status' => 'No record', 'total_hours' => null, 'break_minutes' => 0, 'undertime_minutes' => null, 'is_late' => false];
            // Approved Leave takes priority over Rest Day / Scheduled / Absent.
            $status = ($meta['status'] ?? '') === 'Not Yet Hired' ? 'Not Yet Hired' : ($onLeave ? 'On Leave' : ($meta['status'] ?? 'No record'));
            return [
                'date' => $isoDate,
                'status' => $status,
                'attendance_status' => $status,
                'time_in_display' => '—',
                'break_time_display' => '—',
                'time_out_display' => '—',
                'break_minutes' => 0,
                'total_hours' => null,
                'total_hours_rendered' => null,
                'undertime_minutes' => null,
                'is_late' => false,
                'on_leave' => $onLeave,
                'clockin_photo' => $photos['clockin_photo'] ?? null,
                'clockout_photo' => $photos['clockout_photo'] ?? null,
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

        $status = $onLeave ? 'On Leave' : ($meta['status'] ?? 'Present');

        return [
            'id' => (int)($row['id'] ?? 0),
            'date' => $row['date'] ?? $isoDate,
            'status' => $row['status'] ?? 'Present',
            'attendance_status' => $status,
            'time_in_display' => $formatTimeManila($row['time_in'] ?? null),
            'break_time_display' => $formatBreakDisplay((int)($meta['break_minutes'] ?? 0)),
            'time_out_display' => $formatTimeManila($row['time_out'] ?? null),
            'break_minutes' => $meta['break_minutes'] ?? 0,
            'total_hours' => $meta['total_hours'] ?? $totalHours,
            'total_hours_rendered' => $meta['total_hours'] ?? $totalHours,
            'undertime_minutes' => $meta['undertime_minutes'] ?? null,
            'late_minutes' => $meta['late_minutes'] ?? 0,
                'is_late' => $meta['is_late'] ?? false,
            'updated_at' => $row['updated_at'] ?? null,
            'on_leave' => $onLeave,
            'clockin_photo' => $photos['clockin_photo'] ?? null,
            'clockout_photo' => $photos['clockout_photo'] ?? null,
            'clockin_lat' => $photos['clockin_latitude'] ?? null,
            'clockin_lng' => $photos['clockin_longitude'] ?? null,
            'clockout_lat' => $photos['clockout_latitude'] ?? null,
            'clockout_lng' => $photos['clockout_longitude'] ?? null,
            'distance_from_site' => $photos['distance_from_site'] ?? null,
        ];
    };

    $todayPayload = $formatEntry($todayRow ?: null, $today);
    $todayPayload['employee_id'] = $employeeId;
    $todayPayload['time_in'] = $todayRow['time_in'] ?? null;
    $todayPayload['break_time'] = $todayRow['break_time'] ?? null;
    $todayPayload['time_out'] = $todayRow['time_out'] ?? null;

    $filterMonth=isset($_GET['month']);
    $logStmt = $pdo->prepare('SELECT id,date,time_in,break_time,time_out,total_hours_rendered,status,updated_at FROM daily_time_records WHERE employee_id=?'.($filterMonth?' AND date BETWEEN ? AND ?':'').' ORDER BY date DESC,id DESC LIMIT ?');
    $logStmt->bindValue(1,$employeeId,PDO::PARAM_INT);
    if($filterMonth){$logStmt->bindValue(2,$calendarRangeStart);$logStmt->bindValue(3,$calendarRangeEnd);$logStmt->bindValue(4,$limit,PDO::PARAM_INT);}
    else $logStmt->bindValue(2,$limit,PDO::PARAM_INT);
    $logStmt->execute();

    $logs = [];
    $hoursThisWeek = 0.0;
    $weekStart = date('Y-m-d', strtotime('monday this week'));

    while ($row = $logStmt->fetch()) {
        $entry = $formatEntry($row, $row['date']);
        $logs[] = $entry;
        if ($row['date'] >= $weekStart && $entry['total_hours'] !== null) {
            $hoursThisWeek += (float)$entry['total_hours'];
        }
    }

    // Smart Calendar data for the requested (or current) month: unlike $logs
    // above — which only lists dates that actually have a daily_time_records
    // row — this covers EVERY date in the month so Rest Day / Absent / On
    // Leave / Scheduled days that never got a DTR row still get a computed
    // status instead of silently falling back to "no record".
    $calendarRowsByDate = [];
    $calRowStmt = $pdo->prepare('SELECT * FROM daily_time_records WHERE employee_id = ? AND date BETWEEN ? AND ?');
    $calRowStmt->execute([$employeeId, $calendarRangeStart, $calendarRangeEnd]);
    while ($calRow = $calRowStmt->fetch()) {
        $calendarRowsByDate[$calRow['date']] = $calRow;
    }

    $logByDate = [];
    $cursor = new DateTime($calendarRangeStart);
    $rangeEndDt = new DateTime($calendarRangeEnd);
    while ($cursor <= $rangeEndDt) {
        $iso = $cursor->format('Y-m-d');
        $logByDate[$iso] = $formatEntry($calendarRowsByDate[$iso] ?? null, $iso);
        $cursor->modify('+1 day');
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
        'month' => $monthParam,
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