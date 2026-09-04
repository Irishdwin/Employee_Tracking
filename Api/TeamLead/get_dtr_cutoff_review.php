<?php
/**
 * GET — DTR Cutoff Review list for the logged-in Team Lead.
 *
 * For every employee currently deployed under this Team Lead (same
 * `deployment.SiteSuperVisorID` ownership check used by
 * Api/TeamLead/get_tl_attendance_roster.php and
 * Api/TeamLead/get_employee_attendance_logs.php), this returns:
 *   - the current kinsenas cutoff period (via payroll_kinsenas_period(),
 *     the existing, non-hardcoded cutoff resolver)
 *   - per-employee totals for that cutoff (Total Regular Hrs, Total OT Hrs,
 *     Holiday Pay Hrs, attendance summary) computed the same way as the
 *     Employee Dashboard's payroll view (Api/Employee/get_employee_payroll.php),
 *     via the existing payroll_compute_period() helper — no new payroll math.
 *   - a day-by-day breakdown (date, time in/out, rendered hours, late
 *     minutes, undertime minutes, status) computed via the SAME
 *     payroll_timesheet_day_status() authoritative status function used by
 *     the Employee Dashboard and the TL roster, so status always agrees.
 *   - whether a dtr_submissions row already exists for this employee +
 *     this exact cutoff (so the frontend can mark it "Submitted" / lock it).
 *
 * This file does not write anything — it is read-only.
 */
require_once __DIR__ . '/../Common/session_init.php';
date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json; charset=utf-8');

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

require_once __DIR__ . '/../Db_connection/db_connect.php'; // mysqli $conn
require_once __DIR__ . '/../Helpers/attendance_pdo.php';
require_once __DIR__ . '/../Helpers/employee_payroll_helper.php';      // payroll_kinsenas_period(), payroll_compute_period(), payroll_timesheet_day_status(), payroll_resolve_deployment_schedule()
require_once __DIR__ . '/../Helpers/employee_deployment_context.php'; // employee_fetch_hired_placement_context()
require_once __DIR__ . '/../Helpers/payroll_type_helper.php';         // payroll_type_period_for_company(), payroll_type_deductions()

payroll_type_ensure_schema($conn);

$teamLeadUserId = (int)$_SESSION['user_id'];

// Optional ?cutoff_ref=YYYY-MM-DD lets the frontend ask about a specific
// past cutoff later; defaults to "today" -> current cutoff, same as
// payroll_kinsenas_period()'s own default.
$cutoffRef = isset($_GET['cutoff_ref']) ? trim((string)$_GET['cutoff_ref']) : null;
if ($cutoffRef !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $cutoffRef)) {
    $cutoffRef = null;
}

try {
    $pdo = attendance_pdo();
    if (function_exists('attendance_ensure_table')) {
        attendance_ensure_table($pdo);
    }

    // NOTE: no single global $period here anymore — each employee's cutoff is
    // resolved from THEIR OWN deployment company's Payroll Type below (a Team
    // Lead overseeing companies of different types must review each employee
    // against the correct cutoff). $fallbackPeriod only covers employees with
    // no resolvable company (legacy/unassigned data) and mirrors the old
    // universal behavior exactly.
    $fallbackPeriod = payroll_kinsenas_period($cutoffRef);

    // Same deployed-team base query as get_tl_attendance_roster.php, minus the
    // today-only DTR join (we need the whole cutoff range per employee, not today).
    $sql = "
        SELECT
            e.EmployeeID AS employee_id,
            e.EmployeeNumber AS employee_number,
            CONCAT(e.FirstName, ' ', e.LastName) AS employee_name,
            e.Email AS employee_email,
            COALESCE(d.Position, e.Position, 'Staff') AS position,
            e.daily_rate,
            e.hourly_rate,
            e.allowance,
            e.monthly_salary_credit,
            e.BasicSalary,
            d.DeploymentID AS deployment_id,
            d.CompanyID AS company_id,
            cc.Name AS company_name
        FROM deployment d
        INNER JOIN employee e ON e.EmployeeID = d.EmployeeID AND e.IsActive = 1
        LEFT JOIN clientcompany cc ON cc.CompanyID = d.CompanyID
        WHERE d.SiteSuperVisorID = ?
          AND (d.EndDate IS NULL OR d.EndDate >= CURDATE())
        GROUP BY e.EmployeeID
        ORDER BY employee_name ASC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $teamLeadUserId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Existing dtr_submissions rows for this TL, ACROSS ANY cutoff — keyed by
    // "EmployeeID|CutoffStart|CutoffEnd" so each employee can be matched against
    // THEIR OWN resolved cutoff (which may differ by company/Payroll Type) once
    // computed below, instead of assuming one shared cutoff for the whole TL.
    $existingByEmployeeCutoff = [];
    $subStmt = $conn->prepare("
        SELECT DTR_SubmissionID, EmployeeID, CutoffStartDate, CutoffEndDate, CreatedAt
        FROM dtr_submissions
        WHERE SiteSupervisorID = ?
    ");
    if ($subStmt) {
        $subStmt->bind_param('i', $teamLeadUserId);
        $subStmt->execute();
        $subRes = $subStmt->get_result();
        while ($subRow = $subRes->fetch_assoc()) {
            $key = (int)$subRow['EmployeeID'] . '|' . $subRow['CutoffStartDate'] . '|' . $subRow['CutoffEndDate'];
            $existingByEmployeeCutoff[$key] = [
                'dtr_submission_id' => (int)$subRow['DTR_SubmissionID'],
                'submitted_at' => $subRow['CreatedAt'],
            ];
        }
        $subStmt->close();
    }

    $todayIso = date('Y-m-d');

    $employees = [];
    foreach ($rows as $row) {
        $employeeId = (int)$row['employee_id'];
        $companyId = (int)($row['company_id'] ?? 0);

        // Each employee's cutoff/period is resolved from THEIR OWN deployment
        // company's configured Payroll Type — not one shared cutoff for the
        // whole Team Lead roster.
        $period = $companyId > 0
            ? payroll_type_period_for_company($conn, $companyId, $cutoffRef)
            : $fallbackPeriod;
        $payrollType = $period['type'] ?? 'B';
        $deductionFlags = payroll_type_deductions($payrollType, $period['half'] ?? '1st');
        $holidays = payroll_ph_holidays_for_range($period['start'], $period['end']);

        // Resolve work schedule via the linked user account exactly like
        // get_tl_attendance_roster.php / get_employee_attendance_logs.php do.
        $workSchedule = '';
        $userLookupStmt = $conn->prepare("SELECT UsersID FROM users WHERE Email = ? LIMIT 1");
        if ($userLookupStmt) {
            $userLookupStmt->bind_param('s', $row['employee_email']);
            $userLookupStmt->execute();
            $uRes = $userLookupStmt->get_result()->fetch_assoc();
            $userLookupStmt->close();
            if ($uRes && !empty($uRes['UsersID'])) {
                try {
                    $hiredContext = employee_fetch_hired_placement_context($conn, (int)$uRes['UsersID']);
                    $workSchedule = $hiredContext ? trim((string)($hiredContext['work_schedule'] ?? '')) : '';
                } catch (Throwable $ex) {
                    $workSchedule = '';
                }
            }
        }

        // Rate fallbacks — identical logic to Api/Employee/get_employee_payroll.php,
        // so the figures shown to the TL match what the employee/payroll side computes.
        $dailyRate = (float)($row['daily_rate'] ?? 0);
        $hourlyRate = (float)($row['hourly_rate'] ?? 0);
        $allowance = (float)($row['allowance'] ?? 0);
        $msc = (float)($row['monthly_salary_credit'] ?? 0);
        if ($dailyRate <= 0 && (float)($row['BasicSalary'] ?? 0) > 0) {
            $dailyRate = round((float)$row['BasicSalary'] / 26, 2);
            $hourlyRate = round($dailyRate / 8, 2);
            $msc = round((float)$row['BasicSalary'], 2);
        }
        if ($hourlyRate <= 0 && $dailyRate > 0) {
            $hourlyRate = round($dailyRate / 8, 2);
        }
        if ($msc <= 0 && $dailyRate > 0) {
            $msc = round($dailyRate * 26, 2);
        }

        // Aggregate totals for this cutoff — reuses the SAME payroll_compute_period()
        // helper the Employee Dashboard uses. No new payroll computation logic here.
        $periodTotals = payroll_compute_period(
            $conn,
            $pdo,
            $employeeId,
            $dailyRate,
            $hourlyRate,
            $allowance,
            $msc,
            $period['start'],
            $period['end'],
            $workSchedule !== '' ? $workSchedule : null,
            $deductionFlags
        );

        // Day-by-day breakdown for the "view DTR" detail modal — built the same way
        // Api/TeamLead/get_employee_attendance_logs.php builds its history list, but
        // scoped to this cutoff's date range instead of a fixed row limit, so the TL
        // sees exactly the days that fall inside the cutoff being reviewed.
        $dtrStmt = $pdo->prepare("
            SELECT date, time_in, break_time, time_out, total_hours_rendered, status
            FROM daily_time_records
            WHERE employee_id = ? AND date BETWEEN ? AND ?
            ORDER BY date ASC
        ");
        $dtrStmt->execute([$employeeId, $period['start'], $period['end']]);
        $dtrByDate = [];
        foreach ($dtrStmt->fetchAll(PDO::FETCH_ASSOC) as $dtrRow) {
            $dtrByDate[$dtrRow['date']] = $dtrRow;
        }

        $dailyBreakdown = [];
        $totalRegularHours = 0.0;
        $totalOtHours = 0.0;
        $totalHolidayHours = 0.0;

        $cursor = new DateTime($period['start']);
        $endDt = new DateTime($period['end']);
        while ($cursor <= $endDt) {
            $iso = $cursor->format('Y-m-d');
            $dtrRow = $dtrByDate[$iso] ?? null;
            $schedule = payroll_resolve_deployment_schedule($conn, $employeeId, $iso);
            // payroll_timesheet_day_status() expects 'break_minutes'; the resolver
            // returns 'break_duration'. Map it here (read-only, display purposes
            // only) without touching the shared helper file itself.
            $schedule['break_minutes'] = $schedule['break_duration'] ?? 60;
            $schedule['time_in'] = $schedule['time_in'] ?? '08:00:00';

            $rowForStatus = $dtrRow;
            if ($rowForStatus) {
                $rowForStatus['total_hours_rendered'] = $dtrRow['total_hours_rendered'] !== null ? (float)$dtrRow['total_hours_rendered'] : null;
            }

            $meta = payroll_timesheet_day_status($rowForStatus, $iso, $schedule, $todayIso);
            $dayHours = (float)($meta['total_hours'] ?? 0);
            $isHoliday = in_array($iso, $holidays, true);

            if (in_array($meta['status'], ['Present', 'Late'], true)) {
                if ($isHoliday) {
                    $totalHolidayHours += $dayHours;
                } elseif ($dayHours > 8) {
                    $totalRegularHours += 8;
                    $totalOtHours += ($dayHours - 8);
                } else {
                    $totalRegularHours += $dayHours;
                }
            }

            $dailyBreakdown[] = [
                'date' => $iso,
                'time_in' => $dtrRow['time_in'] ?? null,
                'time_out' => $dtrRow['time_out'] ?? null,
                'rendered_hours' => $meta['total_hours'],
                'late_minutes' => $meta['late_minutes'] ?? 0,
                'undertime_minutes' => $meta['undertime_minutes'],
                'status' => $meta['status'],
                'is_holiday' => $isHoliday,
            ];

            $cursor->modify('+1 day');
        }

        $alreadySubmitted = $existingByEmployeeCutoff[$employeeId . '|' . $period['start'] . '|' . $period['end']] ?? null;

        $employees[] = [
            'employee_id' => $employeeId,
            'employee_number' => $row['employee_number'] ?: $employeeId,
            'employee_name' => $row['employee_name'],
            'position' => $row['position'],
            'company_id' => (int)$row['company_id'],
            'company_name' => $row['company_name'],
            'deployment_id' => (int)$row['deployment_id'],
            'payroll_type' => $payrollType,
            'payroll_type_label' => payroll_type_label($payrollType),
            'period_start' => $period['start'],
            'period_end' => $period['end'],
            'cutoff_label' => $period['label'],
            'release_date' => $period['payday'],
            'release_date_display' => $period['payday_display'],
            'scheduled_deductions' => array_values(array_filter([
                $deductionFlags['philhealth'] ? 'PhilHealth' : null,
                $deductionFlags['pagibig'] ? 'Pag-IBIG' : null,
                $deductionFlags['sss'] ? 'SSS' : null,
            ])),
            'total_regular_hours' => round($totalRegularHours, 2),
            'total_overtime_hours' => round($totalOtHours, 2),
            'holiday_pay_hours' => round($totalHolidayHours, 2),
            'attendance_summary' => $periodTotals['attendance_summary'],
            'daily_breakdown' => $dailyBreakdown,
            'is_submitted' => $alreadySubmitted !== null,
            'dtr_submission_id' => $alreadySubmitted['dtr_submission_id'] ?? null,
            'submitted_at' => $alreadySubmitted['submitted_at'] ?? null,
        ];
    }

    // Backward-compatible top-level 'period' — the first employee's resolved
    // period (the common single-company-per-TL case, which is how this system
    // is normally configured). Multi-company Team Leads should read each
    // employee's own period_start/period_end/cutoff_label instead.
    $primaryPeriod = $employees[0] ?? null;
    $responsePeriod = $primaryPeriod
        ? [
            'start' => $primaryPeriod['period_start'],
            'end' => $primaryPeriod['period_end'],
            'label' => $primaryPeriod['cutoff_label'],
            'payday' => $primaryPeriod['release_date'],
            'payday_display' => $primaryPeriod['release_date_display'],
            'type' => $primaryPeriod['payroll_type'],
        ]
        : $fallbackPeriod;

    echo json_encode([
        'success' => true,
        'period' => $responsePeriod,
        'employees' => $employees,
    ]);
} catch (Throwable $e) {
    error_log('get_dtr_cutoff_review: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load DTR cutoff review.',
        'error_details' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
}