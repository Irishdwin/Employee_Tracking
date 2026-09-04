<?php
/**
 * Employee payroll computation — kinsenas period, deductions, payslip breakdown.
 * Integrates deployment-bound schedule resolution (Time In, Time Out, Grace Period, Working Days).
 */
declare(strict_types=1);

require_once __DIR__ . '/deduction_rates_helper.php';
require_once __DIR__ . '/employee_salary_helper.php';
require_once __DIR__ . '/dtr_qr_logic.php';

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
        "SELECT TimeIn, TimeOut, BreakDuration, GracePeriod, WorkingDays, ShiftName
         FROM deployment
         WHERE EmployeeID = ? 
           AND StartDate <= ? 
           AND (EndDate IS NULL OR EndDate >= ?)
         ORDER BY StartDate DESC
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

    return [
        'time_in' => !empty($row['TimeIn']) ? $row['TimeIn'] : '08:00:00',
        'time_out' => !empty($row['TimeOut']) ? $row['TimeOut'] : '17:00:00',
        'break_duration' => isset($row['BreakDuration']) ? (int)$row['BreakDuration'] : 60,
        'grace_period' => isset($row['GracePeriod']) ? (int)$row['GracePeriod'] : 5,
        'working_days' => !empty($row['WorkingDays']) ? $row['WorkingDays'] : 'Mon-Fri',
        'shift_name' => !empty($row['ShiftName']) ? $row['ShiftName'] : 'Regular Shift'
    ];
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

/** @return string[] */
function payroll_ph_holidays_for_range(string $start, string $end): array
{
    $year = (int)date('Y', strtotime($start));
    $candidates = [
        $year . '-01-01',
        $year . '-04-09',
        $year . '-05-01',
        $year . '-06-12',
        $year . '-08-21',
        $year . '-11-30',
        $year . '-12-25',
        $year . '-12-30',
    ];
    return array_values(array_filter($candidates, static function ($d) use ($start, $end) {
        return $d >= $start && $d <= $end;
    }));
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
function payroll_fetch_approved_overtime(mysqli $conn, int $employeeId, string $periodStart, string $periodEnd, float $hourlyRate): array
{
    $stmt = $conn->prepare(
        "SELECT ot.OvertimeHours, ot.OvertimeRate
         FROM overtime ot
         INNER JOIN status s ON s.StatusID = ot.StatusID AND s.Name = 'Approved'
         WHERE ot.EmployeeID = ? AND ot.OvertimeDate BETWEEN ? AND ?"
    );
    if (!$stmt) {
        return ['hours' => 0.0, 'pay' => 0.0, 'sessions' => 0];
    }
    $stmt->bind_param('iss', $employeeId, $periodStart, $periodEnd);
    $stmt->execute();
    $res = $stmt->get_result();

    $hours = 0.0;
    $pay = 0.0;
    $sessions = 0;
    while ($row = $res->fetch_assoc()) {
        $h = (float)($row['OvertimeHours'] ?? 0);
        $rate = isset($row['OvertimeRate']) && (float)$row['OvertimeRate'] > 0
            ? (float)$row['OvertimeRate']
            : round($hourlyRate * 1.25, 2);
        $hours += $h;
        $pay += $h * $rate;
        $sessions++;
    }
    $stmt->close();

    return ['hours' => round($hours, 2), 'pay' => round($pay, 2), 'sessions' => $sessions];
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
    ?array $deductionFlags = null
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

    $daysWorked = 0;
    $sundayOtHours = 0.0;
    $regularOtHours = 0.0;
    $holidayDays = 0;
    $presentCount = 0;
    $lateCount = 0;
    $absentCount = 0;
    $totalLateMinutes = 0;
    $totalUndertimeMinutes = 0;
    $holidays = payroll_ph_holidays_for_range($periodStart, $periodEnd);

    $cursor = new DateTime($periodStart);
    $endDt = new DateTime($periodEnd);
    while ($cursor <= $endDt) {
        $iso = $cursor->format('Y-m-d');
        $dtr = $dtrByDate[$iso] ?? null;

        // Dynamically resolve deployment schedule for each day
        $schedule = payroll_resolve_deployment_schedule($conn, $employeeId, $iso);

        $dayMeta = payroll_timesheet_day_status($dtr, $iso, $schedule, $todayIso);

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
            if ($dow === 0) {
                $sundayOtHours += max(0, $hours);
            } elseif ($hours > 8) {
                $regularOtHours += ($hours - 8);
            }
        }

        if (in_array($iso, $holidays, true) && $dtr && !empty($dtr['time_in'])) {
            $holidayDays++;
        }

        $cursor->modify('+1 day');
    }

    $basicPay = round($daysWorked * $dailyRate, 2);
    $sundayOtPay = round($sundayOtHours * $hourlyRate * 1.30, 2);
    $regularOtPay = round($regularOtHours * $hourlyRate * 1.25, 2);
    $holidayPay = round($holidayDays * $dailyRate, 2);
    $allowanceHalf = round($monthlyAllowance / 2, 2);
    $silAccrual = round($dailyRate * 5 / 24, 2);

    $totalLateUndertimeMinutes = $totalLateMinutes + $totalUndertimeMinutes;
    $perMinuteRate = round($dailyRate / 8 / 60, 4);
    $lateUndertimeDeduction = round($perMinuteRate * $totalLateUndertimeMinutes, 2);

    // Team-Lead-recorded, Accounting-approved overtime (separate OT sessions —
    // NOT the same-day >8h / Sunday auto-derivation above, which stays as-is
    // for backward compatibility with existing attendance-only OT).
    $approvedOt = payroll_fetch_approved_overtime($conn, $employeeId, $periodStart, $periodEnd, $hourlyRate);

    $grossPay = round(
        $basicPay + $sundayOtPay + $regularOtPay + $approvedOt['pay'] + $holidayPay + $allowanceHalf - $lateUndertimeDeduction,
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
            'basic_pay' => $basicPay,
            'sunday_overtime' => $sundayOtPay,
            'regular_overtime' => $regularOtPay,
            'approved_overtime' => $approvedOt['pay'],
            'holiday_pay' => $holidayPay,
            'sil_accrual' => $silAccrual,
            'allowance' => $allowanceHalf,
        ],
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
            'approved_overtime_hours' => $approvedOt['hours'],
            'approved_overtime_pay' => $approvedOt['pay'],
            'approved_overtime_sessions' => $approvedOt['sessions'],
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