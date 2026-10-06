<?php
/**
 * GET — today's real-time attendance roster for the logged-in Team Lead.
 *
 * IMPORTANT: This endpoint must stay consistent with the Employee Dashboard's
 * authoritative attendance status (Api/Employee/get_employee_attendance.php).
 * That authoritative logic never trusts the raw daily_time_records.status
 * text column; it recomputes Present / Late / Absent / Rest Day from
 * time_in vs. the employee's scheduled shift + grace period, using
 * payroll_timesheet_day_status(). This file now does the same, per
 * employee, so both dashboards always agree.
 */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

require_once __DIR__ . '/../Db_connection/db_connect.php'; // mysqli $conn — needed for employee_fetch_hired_placement_context()
require_once __DIR__ . '/../Helpers/attendance_pdo.php';
require_once __DIR__ . '/../Helpers/employee_payroll_helper.php';      // payroll_timesheet_day_status(), payroll_parse_shift_start()
require_once __DIR__ . '/../Helpers/employee_deployment_context.php'; // employee_fetch_hired_placement_context()
if (file_exists(__DIR__ . '/../Helpers/dtr_qr_logic.php')) {
    require_once __DIR__ . '/../Helpers/dtr_qr_logic.php';
}

require_once __DIR__ . '/../Helpers/work_site_geofence.php';
$current=site_current_deployment_sql();
$teamLeadUserId = (int)$_SESSION['user_id'];
$today = date('Y-m-d');

// Helper fallback if dtr_format_time is not defined
if (!function_exists('dtr_format_time')) {
    function dtr_format_time($time) {
        if (!$time) return '—';
        $timestamp = strtotime($time);
        return $timestamp ? date('h:i A', $timestamp) : $time;
    }
}

try {
    $pdo = attendance_pdo();
    if (function_exists('attendance_ensure_table')) {
        attendance_ensure_table($pdo);
    }

    // Same deployed-team base query as before, plus the employee's Email so we
    // can resolve their linked user account (needed to look up work_schedule
    // the exact same way the Employee Dashboard does).
    $sql = "
        SELECT
            d.SiteID AS site_id, s.Name AS site_name,
            e.EmployeeID AS employee_id,
            CONCAT(e.FirstName, ' ', e.LastName) AS employee_name,
            e.Email AS employee_email,
            COALESCE(d.Position, e.Position, 'Staff') AS position,
            dtr.id AS record_id,
            dtr.time_in,
            dtr.break_time,
            dtr.time_out,
            dtr.total_hours_rendered,
            dtr.updated_at
        FROM deployment d
        INNER JOIN deployment_sites s ON s.SiteID=d.SiteID AND s.IsActive=1
        INNER JOIN clientcompany c ON c.CompanyID=s.CompanyID AND c.IsActive=1
        INNER JOIN employee e ON e.EmployeeID = d.EmployeeID AND e.IsActive = 1
        LEFT JOIN daily_time_records dtr
            ON dtr.employee_id = e.EmployeeID AND dtr.date = :today
        WHERE s.TeamLeadID = :tl_id AND {$current}
          AND (d.EndDate IS NULL OR d.EndDate >= CURDATE())
        GROUP BY e.EmployeeID
        ORDER BY employee_name ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['today' => $today, 'tl_id' => $teamLeadUserId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Approved-leave lookup prepared once and reused per employee (mysqli, existing `leaves` table).
    $leaveStmt = $conn->prepare("
        SELECT LeaveID FROM leaves
        WHERE EmployeeID = ? AND StartDate <= ? AND EndDate >= ?
        LIMIT 1
    ");

    // Resolve a user's hired work_schedule text via their Email -> users.UsersID,
    // exactly like Api/Employee/get_employee_attendance.php does.
    $userLookupStmt = $conn->prepare("SELECT UsersID FROM users WHERE Email = ? LIMIT 1");

    $roster = [];
    $summary = ['total' => 0, 'clocked_in' => 0, 'late' => 0, 'absent' => 0, 'rest_day' => 0, 'on_leave' => 0];

    foreach ($rows as $row) {
        $employeeId = (int)$row['employee_id'];
        $summary['total']++;

        // 1) Resolve this employee's work schedule the same way the Employee Dashboard does.
        $workSchedule = '';
        if (!empty($row['employee_email']) && $userLookupStmt) {
            $userLookupStmt->bind_param('s', $row['employee_email']);
            $userLookupStmt->execute();
            $uRes = $userLookupStmt->get_result()->fetch_assoc();
            if ($uRes && !empty($uRes['UsersID'])) {
                $linkedUserId = (int)$uRes['UsersID'];
                $hiredContext = null;
                try {
                    $hiredContext = employee_fetch_hired_placement_context($conn, $linkedUserId);
                } catch (Throwable $ex) {
                    $hiredContext = null;
                }
                $workSchedule = $hiredContext ? trim((string)($hiredContext['work_schedule'] ?? '')) : '';
            }
        }

        $shiftStart = function_exists('payroll_parse_shift_start')
            ? payroll_parse_shift_start($workSchedule !== '' ? $workSchedule : null)
            : '08:00:00';

        $breakMinutes = function_exists('payroll_get_break_minutes')
            ? payroll_get_break_minutes($conn, $employeeId)
            : 60;

        // Same schedule shape used by get_employee_attendance.php (grace_period 5,
        // working_days hardcoded 'Mon-Fri') so both dashboards compute identically.
        $schedule = payroll_resolve_deployment_schedule($conn, $employeeId, $today);

        // 2) Build the dtr row shape payroll_timesheet_day_status() expects.
        $dtrRow = $row['record_id'] ? [
            'time_in' => $row['time_in'],
            'break_time' => $row['break_time'],
            'time_out' => $row['time_out'],
            'total_hours_rendered' => $row['total_hours_rendered'],
        ] : null;

        $isClockedIn = $dtrRow && !empty($dtrRow['time_in']);

        // 3) Determine attendance status.
        if ($isClockedIn) {
            // Employee actually showed up today — compute Present/Late from time_in.
            $meta = function_exists('payroll_timesheet_day_status')
                ? payroll_timesheet_day_status($dtrRow, $today, $schedule, $today)
                : ['status' => 'Present', 'is_late' => false];
            $attendanceStatus = $meta['status'] ?? 'Present';
            // payroll_timesheet_day_status() only returns Present/Late/Absent/Rest Day/No record
            // for a dtrRow with a time_in; guard against unexpected values defensively.
            if (!in_array($attendanceStatus, ['Present', 'Late'], true)) {
                $attendanceStatus = !empty($meta['is_late']) ? 'Late' : 'Present';
            }
        } else {
            // Not clocked in — check Rest Day / On Leave / Absent, in that order.
            $meta = function_exists('payroll_timesheet_day_status')
                ? payroll_timesheet_day_status(null, $today, $schedule, $today)
                : ['status' => 'Absent'];
            $attendanceStatus = $meta['status'] ?? 'Absent';

            if ($attendanceStatus === 'Rest Day') {
                // Leave as Rest Day.
            } else {
                $onLeave = false;
                if ($leaveStmt) {
                    $leaveStmt->bind_param('iss', $employeeId, $today, $today);
                    $leaveStmt->execute();
                    $lRes = $leaveStmt->get_result()->fetch_assoc();
                    $onLeave = (bool)$lRes;
                }
                $attendanceStatus = $onLeave ? 'On Leave' : 'Absent';
            }
        }

        switch ($attendanceStatus) {
            case 'Late':
                $summary['clocked_in']++;
                $summary['late']++;
                break;
            case 'Present':
                $summary['clocked_in']++;
                break;
            case 'Rest Day':
                $summary['rest_day']++;
                break;
            case 'On Leave':
                $summary['on_leave']++;
                break;
            default: // Absent / No record
                $summary['absent']++;
                break;
        }

        $roster[] = [
            'site_id'=>(int)$row['site_id'],'site_name'=>$row['site_name'],
            'employee_id' => $employeeId,
            'employee_number' => $employeeId, // Fallback if EmployeeNumber column doesn't exist
            'employee_name' => $row['employee_name'],
            'position' => $row['position'],
            'record_id' => $row['record_id'] ? (int)$row['record_id'] : null,
            'attendance_status' => $attendanceStatus,
            'status' => $attendanceStatus, // kept for backward compatibility with older frontend code
            'is_clocked_in' => $isClockedIn,
            'time_in' => $row['time_in'],
            'time_in_display' => dtr_format_time($row['time_in']),
            'break_time' => $row['break_time'],
            'break_time_display' => dtr_format_time($row['break_time']),
            'time_out' => $row['time_out'],
            'time_out_display' => dtr_format_time($row['time_out']),
            'total_hours_rendered' => $row['total_hours_rendered'] !== null ? (float)$row['total_hours_rendered'] : null,
            'updated_at' => $row['updated_at'],
        ];
    }

    if ($leaveStmt) $leaveStmt->close();
    if ($userLookupStmt) $userLookupStmt->close();

    echo json_encode([
        'success' => true,
        'date' => $today,
        'summary' => $summary,
        'roster' => $roster,
    ]);
} catch (Throwable $e) {
    error_log('get_tl_attendance_roster: ' . $e->getMessage());
    http_response_code(500);
    // Expose exact exception message to facilitate quick debugging
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load attendance roster.',
        'error_details' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
