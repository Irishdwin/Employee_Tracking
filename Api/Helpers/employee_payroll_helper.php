<?php
/**
 * Employee payroll computation — kinsenas period, deductions, payslip breakdown.
 * Integrates deployment-bound schedule resolution (Time In, Time Out, Grace Period, Working Days).
 */
declare(strict_types=1);

require_once __DIR__ . '/deduction_rates_helper.php';
require_once __DIR__ . '/employee_salary_helper.php';
require_once __DIR__ . '/dtr_qr_logic.php';
require_once __DIR__ . '/holidays_helper.php';
require_once __DIR__ . '/team_schedule_helper.php';

/**
 * Resolves the active deployment schedule for an employee on a given date.
 * Returns array with TimeIn, TimeOut, BreakDuration, GracePeriod, WorkingDays, ShiftName.
 */
function payroll_resolve_deployment_schedule(mysqli $conn, int $employeeId, string $dateIso): array
{
    $defaultSchedule = [
        'time_in' => '08:00:00',
        'time_out' => '17:00:00',
        'break_duration' => 60,
        'grace_period' => 5,
        'working_days' => 'Mon-Fri',
        'shift_name' => 'Regular Shift'
    ];

    $stmt = $conn->prepare(
        "SELECT DeploymentID, TimeIn, TimeOut, BreakDuration, GracePeriod, WorkingDays, ShiftName
         FROM deployment
         WHERE EmployeeID = ? 
           AND StartDate <= ? 
           AND (EndDate IS NULL OR EndDate >= ?)
         ORDER BY StartDate DESC, DeploymentID DESC
         LIMIT 1"
    );
    if (!$stmt) {
        return $defaultSchedule;
    }
    $stmt->bind_param('iss', $employeeId, $dateIso, $dateIso);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return $defaultSchedule;
    }

    return team_schedule_resolve($conn, $row, $dateIso);
}

/**
 * Onazul's confirmed kinsenas cutoff:
 *   1st cutoff: 26th of previous month → 10th of current month, payday the 15th.
 *   2nd cutoff: 11th → 25th of current month, payday the 30th (or the month's
 *               last day when the month is shorter than 30 days, e.g. February).
 *
 * @return array{start:string,end:string,payday:string,label:string,half:string}
 */
function payroll_kinsenas_period(?string $refDate = null): array
{
    $ref = $refDate ? new DateTime($refDate) : new DateTime('today');
    $day = (int)$ref->format('j');
    $year = (int)$ref->format('Y');
    $month = (int)$ref->format('n');

    if ($day >= 11 && $day <= 25) {
        // 2nd cutoff: 11th - 25th of this month.
        $start = sprintf('%04d-%02d-11', $year, $month);
        $end = sprintf('%04d-%02d-25', $year, $month);
        $lastDay = (int)$ref->format('t');
        $paydayDay = min(30, $lastDay);
        $payday = sprintf('%04d-%02d-%02d', $year, $month, $paydayDay);
        $half = '2nd';
    } elseif ($day >= 26) {
        // 1st cutoff (upcoming): 26th of THIS month -> 10th of NEXT month, payday 15th next month.
        $start = sprintf('%04d-%02d-26', $year, $month);
        $nextMonthDt = (clone $ref)->modify('first day of next month');
        $end = $nextMonthDt->format('Y-m-') . '10';
        $payday = $nextMonthDt->format('Y-m-') . '15';
        $half = '1st';
    } else {
        // day 1-10: 1st cutoff: 26th of PREVIOUS month -> 10th of THIS month, payday 15th this month.
        $prevMonthDt = (clone $ref)->modify('last day of previous month');
        $start = $prevMonthDt->format('Y-m-') . '26';
        $end = sprintf('%04d-%02d-10', $year, $month);
        $payday = sprintf('%04d-%02d-15', $year, $month);
        $half = '1st';
    }

    $startFmt = date('M j, Y', strtotime($start));
    $endFmt = date('M j, Y', strtotime($end));

    return [
        'start' => $start,
        'end' => $end,
        'payday' => $payday,
        'payday_display' => date('M j, Y', strtotime($payday)),
        'label' => $startFmt . ' – ' . $endFmt,
        'half' => $half,
    ];
}

function payroll_parse_shift_start(?string $workSchedule, string $default = '08:00:00'): string
{
    if ($workSchedule === null || trim($workSchedule) === '') {
        return $default;
    }
    if (preg_match('/(\d{1,2}):(\d{2})\s*(AM|PM)?/i', $workSchedule, $m)) {
        $h = (int)$m[1];
        $min = (int)$m[2];
        $ampm = strtoupper($m[3] ?? '');
        if ($ampm === 'PM' && $h < 12) {
            $h += 12;
        }
        if ($ampm === 'AM' && $h === 12) {
            $h = 0;
        }
        return sprintf('%02d:%02d:00', $h, $min);
    }
    return $default;
}

/**
 * Parses the deployment's free-text `WorkingDays` value (e.g. "Mon-Fri",
 * "Tue-Sat", "Sunday-Thursday", "Mon, Wed, Fri") into the set of scheduled
 * weekday numbers. Supports a contiguous day-range ("X-Y", wrapping across
 * the week when needed, e.g. "Fri-Tue"), an explicit comma/slash-separated
 * day list, and the pre-existing "6 days" / "7 days" shorthands. Falls back
 * to the standard Mon-Fri work week when the value is empty or unparseable.
 *
 * @return int[] 0=Sun … 6=Sat
 */
function payroll_scheduled_weekdays(string $workingDays): array
{
    $dayMap = ['sun' => 0, 'mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6];
    $s = strtolower(trim($workingDays));

    if ($s === '') {
        return [1, 2, 3, 4, 5];
    }

    if (strpos($s, '7 days') !== false || strpos($s, 'mon-sun') !== false || strpos($s, 'monday-sunday') !== false
        || strpos($s, 'everyday') !== false || strpos($s, 'every day') !== false) {
        return [0, 1, 2, 3, 4, 5, 6];
    }
    if (strpos($s, '6 days') !== false || strpos($s, 'mon-sat') !== false || strpos($s, 'monday-saturday') !== false) {
        return [1, 2, 3, 4, 5, 6];
    }

    // Contiguous named range, e.g. "Tue-Sat", "Sunday-Thursday", "Fri-Tue" (wraps).
    if (preg_match('/([a-z]{3,})\s*-\s*([a-z]{3,})/', $s, $m)) {
        $startKey = substr($m[1], 0, 3);
        $endKey = substr($m[2], 0, 3);
        if (isset($dayMap[$startKey], $dayMap[$endKey])) {
            $days = [];
            $d = $dayMap[$startKey];
            $end = $dayMap[$endKey];
            while (true) {
                $days[] = $d;
                if ($d === $end || count($days) > 7) break;
                $d = ($d + 1) % 7;
            }
            return $days;
        }
    }

    // Explicit comma/slash-separated day list, e.g. "Mon, Wed, Fri".
    if (preg_match('/[a-z]{3,}/', $s)) {
        $days = [];
        foreach (preg_split('/[\s,\/]+/', $s, -1, PREG_SPLIT_NO_EMPTY) as $part) {
            $key = substr($part, 0, 3);
            if (isset($dayMap[$key]) && !in_array($dayMap[$key], $days, true)) {
                $days[] = $dayMap[$key];
            }
        }
        if (!empty($days)) {
            sort($days);
            return $days;
        }
    }

    return [1, 2, 3, 4, 5];
}

function payroll_is_scheduled_day(string $isoDate, array $weekdays): bool
{
    $dow = (int)date('w', strtotime($isoDate));
    return in_array($dow, $weekdays, true);
}

function payroll_compute_total_hours(?string $timeIn, ?string $breakTime, ?string $timeOut, ?float $stored = null): ?float
{
    if ($stored !== null && $stored > 0) {
        return round((float)$stored, 2);
    }
    if ($timeIn === null || $timeIn === '' || $timeOut === null || $timeOut === '') {
        return null;
    }
    $inTs = strtotime($timeIn);
    $outTs = strtotime($timeOut);
    if ($inTs === false || $outTs === false || $outTs <= $inTs) {
        return null;
    }
    $grossHours = ($outTs - $inTs) / 3600;
    $breakHours = payroll_break_minutes($timeIn, $breakTime, $timeOut) / 60;
    return round(max(0.0, $grossHours - $breakHours), 2);
}

function payroll_break_minutes(?string $timeIn, ?string $breakTime, ?string $timeOut): int
{
    if (!$timeIn || !$breakTime || !$timeOut) {
        return 0;
    }
    $in = strtotime($timeIn);
    $brk = strtotime($breakTime);
    $out = strtotime($timeOut);
    if ($in === false || $brk === false || $out === false || $brk <= $in) {
        return 0;
    }
    if ($out > $brk) {
        return (int)round(($brk - $in) / 60);
    }
    return 0;
}

/**
 * @return array{status:string,total_hours:?float,raw_hours:?float,break_minutes:int,undertime_minutes:?int,is_late:bool}
 */
function payroll_timesheet_day_status(
    ?array $dtrRow,
    string $isoDate,
    array $schedule,
    string $todayIso
): array {
    $scheduledWeekdays = payroll_scheduled_weekdays($schedule['working_days']);
    $isScheduled = payroll_is_scheduled_day($isoDate, $scheduledWeekdays);
    $isPast = $isoDate < $todayIso;
    $isToday = $isoDate === $todayIso;
    $fixedBreakMinutes = max(0, (int)($schedule['break_minutes'] ?? 0));

    if (!$isScheduled) {
        return [
            'status' => 'Rest Day',
            'total_hours' => null,
            'raw_hours' => null,
            'break_minutes' => 0,
            'undertime_minutes' => null,
            'late_minutes' => 0,
            'is_late' => false,
        ];
    }

    if (!$dtrRow || empty($dtrRow['time_in'])) {
        if ($isPast) {
            return ['status' => 'Absent', 'total_hours' => null, 'raw_hours' => null, 'break_minutes' => 0, 'undertime_minutes' => null, 'late_minutes' => 0, 'is_late' => false];
        }
        return ['status' => $isToday ? 'No record' : 'Scheduled', 'total_hours' => null, 'raw_hours' => null, 'break_minutes' => 0, 'undertime_minutes' => null, 'late_minutes' => 0, 'is_late' => false];
    }

    $breakMinutes = $fixedBreakMinutes;

    $rawHours = payroll_compute_total_hours(
        $dtrRow['time_in'] ?? null,
        $dtrRow['break_time'] ?? null,
        $dtrRow['time_out'] ?? null,
        isset($dtrRow['total_hours_rendered']) ? (float)$dtrRow['total_hours_rendered'] : null
    );

    // Accurately compute net total hours from clock-in to clock-out minus fixed break (preventing double subtraction)
    $totalHours = null;
    if (!empty($dtrRow['time_in']) && !empty($dtrRow['time_out'])) {
        $inTs = strtotime($dtrRow['time_in']);
        $outTs = strtotime($dtrRow['time_out']);
        if ($inTs !== false && $outTs !== false && $outTs > $inTs) {
            $grossHours = ($outTs - $inTs) / 3600;
            $netHours = max(0.0, $grossHours - ($breakMinutes / 60));
            $totalHours = round($netHours, 2);
        }
    } elseif ($rawHours !== null) {
        $totalHours = max(0.0, round($rawHours, 2));
    }

    // Late minutes measured from scheduled shift start
    $isLate = false;
    $lateMinutes = 0;
    if (!empty($dtrRow['time_in'])) {
        $clockInTs = strtotime($dtrRow['time_in']);
        $shiftStart = $schedule['time_in'];
        $graceSeconds = ($schedule['grace_period'] ?? 5) * 60;
        $shiftTs = strtotime($isoDate . ' ' . $shiftStart);
        if ($clockInTs !== false && $shiftTs !== false && $clockInTs > $shiftTs + $graceSeconds) {
            $isLate = true;
            $lateMinutes = max(0, (int)round(($clockInTs - $shiftTs) / 60));
        }
    }

    // Undertime: shortfall against a full 8-hour day based on net hours
    $undertimeMinutes = null;
    if (!empty($dtrRow['time_out']) && $totalHours !== null) {
        $undertimeMinutes = max(0, (int)round((8.0 - $totalHours) * 60));
    }

    if ($totalHours !== null && $totalHours >= 8.0) {
        return [
            'status' => $isLate ? 'Late' : 'Present',
            'total_hours' => $totalHours,
            'raw_hours' => $rawHours,
            'break_minutes' => $breakMinutes,
            'undertime_minutes' => $undertimeMinutes,
            'late_minutes' => $lateMinutes,
            'is_late' => $isLate,
        ];
    }

    if ($isLate || ($totalHours !== null && $totalHours > 0)) {
        return [
            'status' => 'Late',
            'total_hours' => $totalHours,
            'raw_hours' => $rawHours,
            'break_minutes' => $breakMinutes,
            'undertime_minutes' => $undertimeMinutes,
            'late_minutes' => $lateMinutes,
            'is_late' => true,
        ];
    }

    return [
        'status' => $isPast ? 'Absent' : 'No record',
        'total_hours' => $totalHours,
        'raw_hours' => $rawHours,
        'break_minutes' => $breakMinutes,
        'undertime_minutes' => $undertimeMinutes,
        'late_minutes' => $lateMinutes,
        'is_late' => false,
    ];
}

/**
 * Fixed daily break duration (minutes) for an employee's active deployment.
 * Falls back to $default when there's no active deployment or the column is null.
 */
function payroll_get_break_minutes(mysqli $conn, int $employeeId, int $default = 60): int
{
    $stmt = $conn->prepare("
        SELECT BreakDuration FROM deployment
        WHERE EmployeeID = ? AND (EndDate IS NULL OR EndDate >= CURDATE())
        ORDER BY StartDate DESC
        LIMIT 1
    ");
    if (!$stmt) {
        return $default;
    }
    $stmt->bind_param('i', $employeeId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || $row['BreakDuration'] === null || $row['BreakDuration'] === '') {
        return $default;
    }
    return max(0, (int)$row['BreakDuration']);
}

function payroll_lookup_sss_bracket(mysqli $conn, float $msc): ?array
{
    deduction_rates_ensure_schema($conn);
    $stmt = $conn->prepare(
        'SELECT regular_ss_ee, mpf_ee, monthly_salary_credit
         FROM sss_rates_config
         WHERE is_active = 1 AND monthly_salary_credit <= ?
         ORDER BY monthly_salary_credit DESC
         LIMIT 1'
    );
    $stmt->bind_param('d', $msc);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        return $row;
    }

    $stmt2 = $conn->prepare(
        'SELECT regular_ss_ee, mpf_ee, monthly_salary_credit
         FROM sss_rates_config
         WHERE is_active = 1 AND ? >= salary_min AND (salary_max IS NULL OR ? <= salary_max)
         ORDER BY salary_min DESC
         LIMIT 1'
    );
    $stmt2->bind_param('dd', $msc, $msc);
    $stmt2->execute();
    $row2 = $stmt2->get_result()->fetch_assoc();
    $stmt2->close();

    return $row2 ?: null;
}

function payroll_fetch_gov_rates(mysqli $conn): array
{
    deduction_rates_ensure_schema($conn);
    $res = $conn->query('SELECT philhealth_ee_pct, philhealth_er_pct, pagibig_ee_fixed, pagibig_er_fixed FROM gov_percentage_rates WHERE id = 1 LIMIT 1');
    $row = $res ? $res->fetch_assoc() : null;
    return [
        'philhealth_ee_pct' => (float)($row['philhealth_ee_pct'] ?? 2.5),
        'philhealth_er_pct' => (float)($row['philhealth_er_pct'] ?? 2.5),
        'pagibig_ee_fixed' => (float)($row['pagibig_ee_fixed'] ?? 200.0),
        'pagibig_er_fixed' => (float)($row['pagibig_er_fixed'] ?? 200.0),
    ];
}

/**
 * Holiday DATES only (flat list) within a range — kept for the two existing
 * callers (get_dtr_cutoff_review.php, get_submitted_dtrs.php) that only ever
 * did `in_array($iso, $holidays, true)` to render an "is_holiday" badge, so
 * their contract with this function is unchanged.
 *
 * PREVIOUSLY: hardcoded array of 8 fixed dates per year, entirely bypassing
 * the `holidays` table — meaning Admin had no way to add/edit/remove a
 * holiday, and the same 8 dates applied every year forever. NOW: reads the
 * real `holidays` table (see Api/Helpers/holidays_helper.php), the same
 * table Api/Employee/resignation_request.php and Api/HR/resignations.php
 * already query directly for their own holiday-exclusion logic — so this
 * also makes payroll's holiday calendar consistent with theirs for the
 * first time.
 *
 * For the actual PAY CALCULATION (which needs the per-holiday multiplier,
 * not just yes/no), see holidays_fetch_for_range() in holidays_helper.php,
 * used directly inside payroll_compute_period() below.
 *
 * @return string[]
 */
function payroll_ph_holidays_for_range(mysqli $conn, string $start, string $end): array
{
    return array_keys(holidays_fetch_for_range($conn, $start, $end));
}

/**
 * @param array<int,array<string,mixed>> $dtrByDate keyed by Y-m-d
 * @return array<string,mixed>
 */
/**
 * Sum of Team-Lead-recorded, Accounting-eligible overtime for one employee
 * over a date range — reads ONLY rows in the `overtime` table whose StatusID
 * resolves to the 'Approved' row in `status` (per the project's DB rule:
 * unapproved OT must never contribute to payroll).
 *
 * Pay rate: uses the row's own `OvertimeRate` when the Team Lead/system set
 * one; otherwise falls back to the SAME 1.25x hourly multiplier already used
 * for regular overtime pay a few lines below in payroll_compute_period() —
 * not a new invented rate, the existing one reused.
 *
 * @return array{hours: float, pay: float, sessions: int}
 */
function payroll_fetch_approved_overtime(mysqli $conn, int $employeeId, string $periodStart, string $periodEnd, float $hourlyRate, array $holidayRates = [], ?int $deploymentId = null): array
{
    $stmt = $conn->prepare(
        "SELECT ot.OvertimeID, ot.DeploymentID, ot.OvertimeDate, ot.OvertimeHours, ot.OvertimeRate
         FROM overtime ot
         INNER JOIN status s ON s.StatusID = ot.StatusID AND s.Name = 'Approved'
         WHERE ot.EmployeeID = ? AND ot.OvertimeDate BETWEEN ? AND ? AND (? IS NULL OR ot.DeploymentID = ?)"
    );
    if (!$stmt) {
        return ['hours' => 0.0, 'pay' => 0.0, 'sessions' => 0];
    }
    $stmt->bind_param('issii', $employeeId, $periodStart, $periodEnd, $deploymentId, $deploymentId);
    $stmt->execute();
    $res = $stmt->get_result();

    $hours = 0.0;
    $pay = 0.0;
    $sessions = 0;
    $holidayHours = 0.0; $holidayPay = 0.0; $holidayBreakdown = []; $approvedBreakdown = [];
    while ($row = $res->fetch_assoc()) {
        $h = (float)($row['OvertimeHours'] ?? 0);
        $approvedBreakdown[] = ['overtime_id'=>(int)$row['OvertimeID'], 'deployment_id'=>(int)$row['DeploymentID'], 'date'=>$row['OvertimeDate'], 'hours'=>$h, 'holiday_type'=>$holidayRates[$row['OvertimeDate']]['type']??null];
        if (isset($holidayRates[$row['OvertimeDate']])) {
            $holiday = $holidayRates[$row['OvertimeDate']];
            $holidayAmount = round($h * $hourlyRate * $holiday['ot_multiplier'], 2);
            $holidayHours += $h; $holidayPay += $holidayAmount;
            $holidayBreakdown[] = ['date'=>$row['OvertimeDate'],'holiday_id'=>$holiday['id'],'name'=>$holiday['name'],'type'=>$holiday['type'],'hours'=>$h,'pay_multiplier'=>$holiday['pay_multiplier'],'pay'=>$holidayAmount,'source'=>'approved_overtime'];
            $sessions++;
            continue;
        }
        $rate = isset($row['OvertimeRate']) && (float)$row['OvertimeRate'] > 0
            ? (float)$row['OvertimeRate']
            : round($hourlyRate * 1.25, 2);
        $hours += $h;
        $pay += $h * $rate;
        $sessions++;
    }
    $stmt->close();

    return ['hours' => round($hours, 2), 'pay' => round($pay, 2), 'sessions' => $sessions, 'holiday_hours'=>round($holidayHours,2),'holiday_pay'=>round($holidayPay,2),'holiday_breakdown'=>$holidayBreakdown,'approved_breakdown'=>$approvedBreakdown];
}

/**
 * Regular/Legal Holiday, no work rendered: PH labor law still guarantees
 * 100% pay, but only if the employee was present (or on paid leave) on the
 * workday immediately preceding the holiday. This walks backward from the
 * holiday date to the nearest date that is one of the employee's actual
 * scheduled working days (not just "yesterday"), then checks attendance /
 * approved leave for that specific date directly against the DB -- not
 * against the current payroll period's already-loaded DTR map, since the
 * preceding workday can fall in a previous cutoff.
 *
 * NOTE on "paid" leave: this codebase has no established convention for
 * distinguishing paid vs unpaid leave -- `leaves.AffectsPayroll` and
 * `leaves.LeaveType` are never read anywhere else in the project (verified
 * by scanning the whole codebase), so there is no existing precedent to
 * safely interpret them against. This treats ANY Approved leave as
 * qualifying, matching how the only two existing consumers of the `leaves`
 * table (Api/TeamLead/get_employee_attendance_logs.php,
 * get_tl_attendance_roster.php) already treat "Approved" as the only
 * distinction that matters. This should be confirmed with HR/payroll if a
 * stricter paid/unpaid split is actually required -- reported as a gap in
 * the implementation report rather than silently guessed.
 */
function payroll_holiday_no_work_eligible(mysqli $conn, PDO $pdo, int $employeeId, string $holidayIso, array $schedule): bool
{
    $cursor = new DateTime($holidayIso);
    $precedingWorkday = null;
    for ($i = 0; $i < 14; $i++) {
        $cursor->modify('-1 day');
        $candidate = $cursor->format('Y-m-d');
        $candidateSchedule = payroll_resolve_deployment_schedule($conn, $employeeId, $candidate);
        $candidateHolidays = holidays_fetch_for_range($conn, $candidate, $candidate)[$candidate] ?? [];
        $nonWorkingHoliday = false;
        foreach ($candidateHolidays as $holiday) {
            if (in_array($holiday['type'], ['Regular Holiday', 'Special Non-working Day'], true)) {
                $nonWorkingHoliday = true;
                break;
            }
        }
        if (!$nonWorkingHoliday && payroll_is_scheduled_day($candidate, payroll_scheduled_weekdays($candidateSchedule['working_days']))) {
            $precedingWorkday = $candidate;
            break;
        }
    }
    if ($precedingWorkday === null) {
        return false;
    }

    $stmt = $pdo->prepare('SELECT time_in FROM daily_time_records WHERE employee_id = ? AND date = ? LIMIT 1');
    $stmt->execute([$employeeId, $precedingWorkday]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['time_in'])) {
        return true;
    }

    $leaveStmt = $conn->prepare("
        SELECT l.LeaveID FROM leaves l
        INNER JOIN status s ON s.StatusID = l.StatusID AND s.Name = 'Approved'
        WHERE l.EmployeeID = ? AND l.StartDate <= ? AND l.EndDate >= ?
        LIMIT 1
    ");
    if (!$leaveStmt) {
        return false;
    }
    $leaveStmt->bind_param('iss', $employeeId, $precedingWorkday, $precedingWorkday);
    $leaveStmt->execute();
    $has = $leaveStmt->get_result()->num_rows > 0;
    $leaveStmt->close();
    return $has;
}

function payroll_compute_period(
    mysqli $conn,
    PDO $pdo,
    int $employeeId,
    float $dailyRate,
    float $hourlyRate,
    float $monthlyAllowance,
    float $monthlySalaryCredit,
    string $periodStart,
    string $periodEnd,
    ?string $workScheduleFallback = null,
    ?array $deductionFlags = null,
    ?int $deploymentId = null,
    ?array $attendanceOverride = null,
    bool $includeAddBack = true
): array {
    employee_salary_ensure_schema($conn);

    $todayIso = date('Y-m-d');

    $stmt = $pdo->prepare(
        'SELECT date, time_in, break_time, time_out, total_hours_rendered, status
         FROM daily_time_records
         WHERE employee_id = ? AND date BETWEEN ? AND ?
         ORDER BY date ASC'
    );
    $stmt->execute([$employeeId, $periodStart, $periodEnd]);
    $dtrByDate = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $dtrByDate[$row['date']] = $row;
    }
    // Server-built reconstruction used only to value an Add Back; never persisted.
    if ($attendanceOverride !== null) {
        foreach ($attendanceOverride as $date => $row) {
            if ($date >= $periodStart && $date <= $periodEnd) $dtrByDate[$date] = $row;
        }
    }

    $daysWorked = 0;
    $sundayOtHours = 0.0;
    $regularOtHours = 0.0;
    $holidayDays = 0;
    $holidayPay = 0.0;
    $holidayOtHours = 0.0;
    $holidayOtPay = 0.0;
    $holidayIdForPeriod = null;
    $holidayBreakdown = [];
    $holidayOtBreakdown = [];
    $holidayRates = [];
    $presentCount = 0;
    $lateCount = 0;
    $absentCount = 0;
    $totalLateMinutes = 0;
    $totalUndertimeMinutes = 0;
    // Full per-date detail (id, name, type, pay_multiplier) from the Admin-
    // managed `holidays` table — NOT the flat payroll_ph_holidays_for_range()
    // list above, because computing pay needs the multiplier, not just a
    // yes/no. See Api/Helpers/holidays_helper.php.
    $holidayDetail = holidays_fetch_for_range($conn, $periodStart, $periodEnd);

    $cursor = new DateTime($periodStart);
    $endDt = new DateTime($periodEnd);
    while ($cursor <= $endDt) {
        $iso = $cursor->format('Y-m-d');
        $dtr = $dtrByDate[$iso] ?? null;

        // Dynamically resolve deployment schedule for each day
        $schedule = payroll_resolve_deployment_schedule($conn, $employeeId, $iso);

        $dateHolidays = $holidayDetail[$iso] ?? [];
        $regularHolidaysToday = array_values(array_filter($dateHolidays, static fn($h) => $h['type'] === 'Regular Holiday'));
        $isDoubleHoliday = count($regularHolidaysToday) >= 2;
        $holInfo = $isDoubleHoliday ? $regularHolidaysToday[0] : ($dateHolidays[0] ?? null);
        $isRestDayToday = !payroll_is_scheduled_day($iso, payroll_scheduled_weekdays($schedule['working_days']));
        $holidayMultiplier = $holInfo ? (float)$holInfo['pay_multiplier'] : 1.0;
        if ($isDoubleHoliday) $holidayMultiplier = 3.0;
        elseif ($isRestDayToday && ($holInfo['type'] ?? '') === 'Regular Holiday') $holidayMultiplier = 2.6;
        elseif ($isRestDayToday && ($holInfo['type'] ?? '') === 'Special Non-working Day') $holidayMultiplier = 1.5;
        if ($holInfo) $holidayRates[$iso] = array_merge($holInfo, ['pay_multiplier'=>$holidayMultiplier,'ot_multiplier'=>$holidayMultiplier * ($holInfo['type']==='Special Working Day' ? 1.25 : 1.30)]);

        $dayMeta = payroll_timesheet_day_status($dtr, $iso, $schedule, $todayIso);
        // A worked holiday on a scheduled rest day still has ordinary duty pay.
        // Resolve its hours locally; do not change the schedule or DTR status rules.
        if ($holInfo && $isRestDayToday && $dtr && !empty($dtr['time_in'])) {
            $holidayDutySchedule = $schedule;
            $holidayDutySchedule['working_days'] = 'Mon,Tue,Wed,Thu,Fri,Sat,Sun';
            $dayMeta = payroll_timesheet_day_status($dtr, $iso, $holidayDutySchedule, $todayIso);
        }

        if ($dayMeta['status'] === 'Present') {
            $presentCount++;
        } elseif ($dayMeta['status'] === 'Late') {
            $lateCount++;
        } elseif ($dayMeta['status'] === 'Absent') {
            $absentCount++;
        }

        $totalLateMinutes += (int)($dayMeta['late_minutes'] ?? 0);
        $totalUndertimeMinutes += (int)($dayMeta['undertime_minutes'] ?? 0);

        if (in_array($dayMeta['status'], ['Present', 'Late', 'Undertime'], true)) {
            $daysWorked++;
            $hours = (float)($dayMeta['total_hours'] ?? 0);
            $dow = (int)$cursor->format('w');
            $isHolidayToday = isset($holidayDetail[$iso]) && $dtr && !empty($dtr['time_in']);

            // Ordinary and holiday OT come from Approved overtime records below.
            // Retain the existing non-holiday Sunday duty classification.
            if (!$isHolidayToday && $dow === 0) {
                $sundayOtHours += max(0, $hours);
            }
        }

        // Holiday pay only applies when the employee actually rendered duty
        // that day (a DTR row exists with a time_in) — the holiday existing
        // on the calendar is not by itself "holiday work". PayMultiplier
        // comes from the Admin-managed holidays table (holidays_helper.php):
        // e.g. 2.00 for a Regular Holiday (double pay), 1.30 for a Special
        // Non-working Day — never hardcoded here.
        if (!empty($holidayDetail[$iso])) {
            $dateHolidays = $holidayDetail[$iso];
            $regularHolidaysToday = array_values(array_filter($dateHolidays, static function ($h) {
                return $h['type'] === 'Regular Holiday';
            }));
            // Double Holiday: two Regular/Legal Holidays sharing the same
            // date (e.g. Maundy Thursday landing on Araw ng Kagitingan).
            // Only this exact combination is documented by the reference --
            // Admin validation (holidays_helper.php) already prevents any
            // other same-date combination from being created, so this is
            // the only multi-holiday case that can occur here.
            $isDoubleHoliday = count($regularHolidaysToday) >= 2;

            if ($isDoubleHoliday) {
                $holInfo = $regularHolidaysToday[0];
                $isRegularHoliday = true;
                $isSpecialNonWorking = false;
                $doubleHolidayIds = array_column($regularHolidaysToday, 'id');
                $doubleHolidayName = implode(' + ', array_column($regularHolidaysToday, 'name'));
            } else {
                $holInfo = $dateHolidays[0];
                $isRegularHoliday = ($holInfo['type'] === 'Regular Holiday');
                $isSpecialNonWorking = ($holInfo['type'] === 'Special Non-working Day');
            }
            $workedHolidayToday = $dtr && !empty($dtr['time_in']);

            if ($workedHolidayToday) {
                $holidayDays++;

                if ($isDoubleHoliday) {
                    // Worked = Daily Rate x 300% total, per the Double
                    // Holiday reference -- a flat rate, not a stack of the
                    // two holidays' own multipliers.
                    $multiplier = 3.00;
                } else {
                    $multiplier = (float)$holInfo['pay_multiplier'];

                    // Worked on a holiday that is ALSO the employee's
                    // scheduled rest day overrides the holiday's own stored
                    // multiplier for this specific combination only, per the
                    // references provided so far:
                    //   Regular Holiday + rest day           = 260% total
                    //   Special Non-working Day + rest day   = 150% total
                    // Not applied to any other holiday type or to Double
                    // Holiday -- no reference covers Double Holiday + rest
                    // day, so that combination is not invented here (see
                    // final report).
                    $isRestDayToday = !payroll_is_scheduled_day($iso, payroll_scheduled_weekdays($schedule['working_days']));
                    if ($isRegularHoliday && $isRestDayToday) {
                        $multiplier = 2.60;
                    } elseif ($isSpecialNonWorking && $isRestDayToday) {
                        $multiplier = 1.50;
                    }
                }

                $holidayDutyHours = min(8.0, max(0.0, (float)($dayMeta['total_hours'] ?? 0)));
                $dayHolidayPay = round(($multiplier - 1) * ($dailyRate / 8) * $holidayDutyHours, 2);
                $holidayPay = round($holidayPay + $dayHolidayPay, 2);
                if ($holidayIdForPeriod === null) {
                    $holidayIdForPeriod = $holInfo['id'];
                }
                $holidayBreakdown[] = [
                    'date' => $iso,
                    'holiday_id' => $holInfo['id'],
                    'name' => $isDoubleHoliday ? $doubleHolidayName : $holInfo['name'],
                    'type' => $holInfo['type'],
                    'pay_multiplier' => $multiplier,
                    'pay' => $dayHolidayPay,
                    'is_rest_day' => $isRestDayToday,
                    'worked' => true,
                    'duty_hours' => $holidayDutyHours,
                    'is_double_holiday' => $isDoubleHoliday,
                    'holiday_ids' => $isDoubleHoliday ? $doubleHolidayIds : [$holInfo['id']],
                ];
            } elseif ($isRegularHoliday) {
                // No work rendered on a Regular Holiday (including a Double
                // Holiday, which is still two Regular Holidays): PH law
                // still guarantees pay -- 100% normally, 200% for a Double
                // Holiday per its own reference -- but only if the employee
                // was present or on approved leave on the workday
                // immediately preceding the holiday (see
                // payroll_holiday_no_work_eligible() below, which also
                // implements the "not absent without pay on the immediately
                // preceding WORKING day" rule using the actual schedule, not
                // just the calendar day before). Special Non-working Day
                // explicitly follows "no work, no pay" per its own reference
                // (Pay = P0) -- so this guarantee deliberately does NOT
                // extend to it, nor to Special Working Day / Local holiday.
                if (payroll_holiday_no_work_eligible($conn, $pdo, $employeeId, $iso, $schedule)) {
                    $noWorkMultiplier = $isDoubleHoliday ? 2.00 : 1.00;
                    $noWorkPay = round($dailyRate * $noWorkMultiplier, 2);
                    $holidayPay = round($holidayPay + $noWorkPay, 2);
                    if ($holidayIdForPeriod === null) {
                        $holidayIdForPeriod = $holInfo['id'];
                    }
                    $holidayBreakdown[] = [
                        'date' => $iso,
                        'holiday_id' => $holInfo['id'],
                        'name' => $isDoubleHoliday ? $doubleHolidayName : $holInfo['name'],
                        'type' => $holInfo['type'],
                        'pay_multiplier' => $noWorkMultiplier,
                        'pay' => $noWorkPay,
                        'is_rest_day' => false,
                        'worked' => false,
                        'is_double_holiday' => $isDoubleHoliday,
                        'holiday_ids' => $isDoubleHoliday ? $doubleHolidayIds : [$holInfo['id']],
                    ];
                }
            }
            // Special Non-working Day, did not work: Pay = P0, no branch
            // needed -- simply adds nothing, which is already correct.
        }

        $cursor->modify('+1 day');
    }

    $basicPay = round($daysWorked * $dailyRate, 2);
    $sundayOtPay = round($sundayOtHours * $hourlyRate * 1.30, 2);
    $regularOtPay = round($regularOtHours * $hourlyRate * 1.25, 2);
    // holidayPay already accumulated day-by-day above using each holiday's
    // own PayMultiplier (was: round($holidayDays * $dailyRate, 2) — an
    // implicit hardcoded "+1x day" that assumed every holiday is double pay).
    $allowanceHalf = round($monthlyAllowance / 2, 2);
    $silAccrual = round($dailyRate * 5 / 24, 2);

    $totalLateUndertimeMinutes = $totalLateMinutes + $totalUndertimeMinutes;
    $perMinuteRate = round($dailyRate / 8 / 60, 4);
    $lateUndertimeDeduction = round($perMinuteRate * $totalLateUndertimeMinutes, 2);

    // Team-Lead-recorded, Accounting-approved overtime (separate OT sessions —
    // NOT the same-day >8h / Sunday auto-derivation above, which stays as-is
    // for backward compatibility with existing attendance-only OT).
    $approvedOt = payroll_fetch_approved_overtime($conn, $employeeId, $periodStart, $periodEnd, $hourlyRate, $holidayRates, $deploymentId);
    $holidayOtHours += (float)($approvedOt['holiday_hours'] ?? 0);
    $holidayOtPay = round($holidayOtPay + (float)($approvedOt['holiday_pay'] ?? 0), 2);
    $holidayOtBreakdown = array_merge($holidayOtBreakdown, $approvedOt['holiday_breakdown'] ?? []);

    $addBack = 0.0;
    if ($includeAddBack && $deploymentId !== null) {
        require_once __DIR__ . '/add_back_payroll.php';
        $addBack = add_back_payroll_total($pdo, $employeeId, $deploymentId, $periodStart, $periodEnd);
    }
    $grossPay = round(
        $basicPay + $sundayOtPay + $regularOtPay + $approvedOt['pay'] + $holidayPay + $holidayOtPay + $allowanceHalf - $lateUndertimeDeduction + $addBack,
        2
    );

    $gov = payroll_fetch_gov_rates($conn);
    $sssBracket = payroll_lookup_sss_bracket($conn, $monthlySalaryCredit);
    $sssRegularEe = $sssBracket ? (float)$sssBracket['regular_ss_ee'] : round(min($monthlySalaryCredit, 20000) * 0.05, 2);
    $sssMpfEe = $sssBracket ? (float)$sssBracket['mpf_ee'] : round(max(0, $monthlySalaryCredit - 20000) * 0.05, 2);
    $sssMonthlyEe = round($sssRegularEe + $sssMpfEe, 2);
    $sssPeriodEe = round($sssMonthlyEe / 2, 2);

    $philhealthMonthlyEe = round($monthlySalaryCredit * ($gov['philhealth_ee_pct'] / 100), 2);
    $philhealthPeriodEe = round($philhealthMonthlyEe / 2, 2);
    $pagibigPeriodEe = round($gov['pagibig_ee_fixed'] / 2, 2);

    // Payroll-Type-driven deduction gating (Client Company's Payroll Type + which
    // half of the cutoff this period is determines which of these three actually
    // apply — see payroll_type_deductions() in payroll_type_helper.php). When the
    // caller doesn't pass $deductionFlags (e.g. any call site not yet updated),
    // all three apply — the pre-existing, unconditional behavior is preserved.
    $applySss = $deductionFlags['sss'] ?? true;
    $applyPhilhealth = $deductionFlags['philhealth'] ?? true;
    $applyPagibig = $deductionFlags['pagibig'] ?? true;
    if (!$applySss) {
        $sssPeriodEe = 0.0;
    }
    if (!$applyPhilhealth) {
        $philhealthPeriodEe = 0.0;
    }
    if (!$applyPagibig) {
        $pagibigPeriodEe = 0.0;
    }

    $totalDeductions = round($sssPeriodEe + $philhealthPeriodEe + $pagibigPeriodEe, 2);
    $netPay = round($grossPay - $totalDeductions, 2);

    return [
        'period_start' => $periodStart,
        'period_end' => $periodEnd,
        'days_worked' => $daysWorked,
        'daily_rate' => $dailyRate,
        'hourly_rate' => $hourlyRate,
        'monthly_allowance' => $monthlyAllowance,
        'monthly_salary_credit' => $monthlySalaryCredit,
        'earnings' => [
            'add_back' => $addBack,
            'basic_pay' => $basicPay,
            'sunday_overtime' => $sundayOtPay,
            'regular_overtime' => $regularOtPay,
            'approved_overtime' => $approvedOt['pay'],
            'holiday_pay' => $holidayPay,
            'holiday_ot_pay' => $holidayOtPay,
            'sil_accrual' => $silAccrual,
            'allowance' => $allowanceHalf,
        ],
        'holiday_id' => $holidayIdForPeriod,
        'holiday_breakdown' => $holidayBreakdown,
        'holiday_ot_breakdown' => $holidayOtBreakdown,
        'late_undertime' => [
            'late_minutes' => $totalLateMinutes,
            'undertime_minutes' => $totalUndertimeMinutes,
            'total_minutes' => $totalLateUndertimeMinutes,
            'per_minute_rate' => $perMinuteRate,
            'amount' => $lateUndertimeDeduction,
        ],
        'late_undertime_deduction' => $lateUndertimeDeduction,
        'deductions' => [
            'sss_ee' => $sssPeriodEe,
            'sss_regular_ee' => $applySss ? round($sssRegularEe / 2, 2) : 0.0,
            'sss_mpf_ee' => $applySss ? round($sssMpfEe / 2, 2) : 0.0,
            'philhealth_ee' => $philhealthPeriodEe,
            'pagibig_ee' => $pagibigPeriodEe,
            'applied' => ['sss' => $applySss, 'philhealth' => $applyPhilhealth, 'pagibig' => $applyPagibig],
        ],
        'gross_pay' => $grossPay,
        'total_deductions' => $totalDeductions,
        'net_pay' => $netPay,
        'ot_detail' => [
            'sunday_hours' => round($sundayOtHours, 2),
            'regular_ot_hours' => round($regularOtHours, 2),
            'holiday_ot_hours' => round($holidayOtHours, 2),
            'holiday_ot_pay' => $holidayOtPay,
            'approved_overtime_hours' => $approvedOt['hours'],
            'approved_overtime_pay' => $approvedOt['pay'],
            'approved_overtime_sessions' => $approvedOt['sessions'],
            'approved_overtime_breakdown' => $approvedOt['approved_breakdown'] ?? [],
        ],
        'attendance_summary' => [
            'present' => $presentCount,
            'late' => $lateCount,
            'absent' => $absentCount,
        ],
    ];
}

function payroll_status_label(string $status): array
{
    $s = trim($status);
    if ($s === '') {
        $s = 'Pending DTR';
    }
    $sl = strtolower($s);

    if ($sl === 'pending dtr') {
        return ['label' => 'Pending', 'badge' => 'secondary', 'finalized' => false];
    }
    if ($sl === 'payroll computed') {
        return ['label' => 'Processing', 'badge' => 'warning', 'finalized' => false];
    }
    if ($sl === 'ready for salary release') {
        return ['label' => 'Finalized', 'badge' => 'info', 'finalized' => true];
    }
    if (strpos($sl, 'release') !== false || strpos($sl, 'disburs') !== false || strpos($sl, 'paid') !== false) {
        return ['label' => 'Released', 'badge' => 'success', 'finalized' => true];
    }

    return ['label' => $s, 'badge' => 'primary', 'finalized' => true];
}
