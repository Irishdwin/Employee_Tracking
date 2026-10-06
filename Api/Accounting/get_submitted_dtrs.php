<?php
/**
 * GET — list Team-Lead-submitted DTR submission batches for the Accounting "Verify Submitted
 * Timesheets" panel, and with parameters (batch grouping) or employee lookup.
 */
declare(strict_types=1);

require_once __DIR__ . '/../Common/session_init.php';
require_once __DIR__ . '/../Helpers/dtr_classification.php';
date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$role = trim((string)$_SESSION['role']);
if (!in_array($role, ['Accounting', 'Admin'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit();
}

require_once __DIR__ . '/../Db_connection/db_connect.php';
require_once __DIR__ . '/../Helpers/attendance_pdo.php';
require_once __DIR__ . '/../Helpers/employee_payroll_helper.php';
require_once __DIR__ . '/../Helpers/payroll_type_helper.php';
require_once __DIR__ . '/../Helpers/payroll_status_helper.php';

payroll_type_ensure_schema($conn);
payroll_status_ensure_schema($conn);

try {
    // ---- Detail mode 2: Individual Employee DTR breakdown ----
    if (isset($_GET['employee_id'], $_GET['cutoff_start'], $_GET['cutoff_end'])) {
        $employeeId = (int)$_GET['employee_id'];
        $cutoffStart = trim((string)$_GET['cutoff_start']);
        $cutoffEnd = trim((string)$_GET['cutoff_end']);

        if ($employeeId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $cutoffStart) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $cutoffEnd)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
            exit();
        }

        $subStmt = $conn->prepare("
            SELECT ds.DTR_SubmissionID, ds.EmployeeID, ds.DeploymentID, ds.SiteSupervisorID,
                   ds.CutoffPeriod, ds.CutoffStartDate, ds.CutoffEndDate, ds.TotalHours,
                   ds.OvertimeHours, ds.TotalOvertimeHours, ds.Remarks AS classification_remarks, ds.CreatedAt,
                   CONCAT(e.FirstName, ' ', e.LastName) AS employee_name,
                   e.EmployeeNumber AS employee_number,
                   e.Position AS position,
                   tl.FullName AS tl_full_name,
                   ds.SiteID AS site_id,ws.Name AS site_name,cc.Name AS company_name
            FROM dtr_submissions ds
            INNER JOIN employee e ON e.EmployeeID = ds.EmployeeID
            LEFT JOIN users tl ON tl.UsersID = ds.SiteSupervisorID
            LEFT JOIN deployment d ON d.DeploymentID = ds.DeploymentID
            LEFT JOIN deployment_sites ws ON ws.SiteID=ds.SiteID
            LEFT JOIN clientcompany cc ON cc.CompanyID = d.CompanyID
            WHERE ds.EmployeeID = ? AND ds.CutoffStartDate = ? AND ds.CutoffEndDate = ?
            LIMIT 1
        ");
        $subStmt->bind_param('iss', $employeeId, $cutoffStart, $cutoffEnd);
        $subStmt->execute();
        $submission = $subStmt->get_result()->fetch_assoc();
        $subStmt->close();

        if (!$submission) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Submitted DTR not found.']);
            exit();
        }

        $pdo = attendance_pdo();
        if (function_exists('attendance_ensure_table')) {
            attendance_ensure_table($pdo);
        }
        $dtrStmt = $pdo->prepare("
            SELECT date, time_in, break_time, time_out, total_hours_rendered, status
            FROM daily_time_records
            WHERE employee_id = ? AND date BETWEEN ? AND ?
            ORDER BY date ASC
        ");
        $dtrStmt->execute([$employeeId, $cutoffStart, $cutoffEnd]);
        $dtrByDate = [];
        foreach ($dtrStmt->fetchAll(PDO::FETCH_ASSOC) as $dtrRow) {
            $dtrByDate[$dtrRow['date']] = $dtrRow;
        }

        $todayIso = date('Y-m-d');
        $holidays = payroll_ph_holidays_for_range($conn, $cutoffStart, $cutoffEnd);
        $dailyBreakdown = [];

        $cursor = new DateTime($cutoffStart);
        $endDt = new DateTime($cutoffEnd);
        while ($cursor <= $endDt) {
            $iso = $cursor->format('Y-m-d');
            $dtrRow = $dtrByDate[$iso] ?? null;
            $schedule = payroll_resolve_deployment_schedule($conn, $employeeId, $iso);
            $schedule['break_minutes'] = $schedule['break_duration'] ?? 60;
            $schedule['time_in'] = $schedule['time_in'] ?? '08:00:00';

            $rowForStatus = $dtrRow;
            if ($rowForStatus) {
                $rowForStatus['total_hours_rendered'] = $dtrRow['total_hours_rendered'] !== null ? (float)$dtrRow['total_hours_rendered'] : null;
            }
            $meta = payroll_timesheet_day_status($rowForStatus, $iso, $schedule, $todayIso);

            $dailyBreakdown[] = [
                'date' => $iso,
                'time_in' => $dtrRow['time_in'] ?? null,
                'time_out' => $dtrRow['time_out'] ?? null,
                'rendered_hours' => $meta['total_hours'],
                'late_minutes' => $meta['late_minutes'] ?? 0,
                'undertime_minutes' => $meta['undertime_minutes'],
                'status' => $meta['status'],
                'is_holiday' => in_array($iso, $holidays, true),
            ];
            $cursor->modify('+1 day');
        }

        // Overtime sessions the Team Lead recorded for this employee within this
        // cutoff (section 10: OT Start/End/Hours/Status shown on the DTR detail view).
        $otSessions = [];
        $otStmt = $conn->prepare("
            SELECT ot.OTStartTime, ot.OTEndTime, ot.OvertimeHours, ot.OvertimeDate, ot.Remarks,
                   s.Name AS status_name
            FROM overtime ot
            LEFT JOIN status s ON s.StatusID = ot.StatusID
            WHERE ot.EmployeeID = ? AND ot.OvertimeDate BETWEEN ? AND ?
            ORDER BY ot.OvertimeDate ASC, ot.OTStartTime ASC
        ");
        if ($otStmt) {
            $otStmt->bind_param('iss', $employeeId, $cutoffStart, $cutoffEnd);
            $otStmt->execute();
            $otRes = $otStmt->get_result();
            while ($otRow = $otRes->fetch_assoc()) {
                $otSessions[] = [
                    'date' => $otRow['OvertimeDate'],
                    'ot_start' => $otRow['OTStartTime'],
                    'ot_end' => $otRow['OTEndTime'],
                    'ot_hours' => $otRow['OvertimeHours'] !== null ? (float)$otRow['OvertimeHours'] : null,
                    'status' => $otRow['status_name'] ?? 'Pending',
                    'remarks' => $otRow['Remarks'],
                ];
            }
            $otStmt->close();
        }

        echo json_encode([
            'success' => true,
            'overtime_sessions' => $otSessions,
            'submission' => [
                'dtr_submission_id' => (int)$submission['DTR_SubmissionID'],
                'employee_id' => (int)$submission['EmployeeID'],
                'employee_name' => $submission['employee_name'],
                'employee_number' => $submission['employee_number'],
                'position' => $submission['position'],
                'site_supervisor_id' => (int)$submission['SiteSupervisorID'],
                'team_lead_name' => trim((string)($submission['tl_full_name'] ?? '')),
                'company_name' => $submission['company_name'],
                'cutoff_period' => $submission['CutoffPeriod'],
                'cutoff_start' => $submission['CutoffStartDate'],
                'cutoff_end' => $submission['CutoffEndDate'],
                'total_hours' => $submission['TotalHours'] !== null ? (float)$submission['TotalHours'] : null,
                'overtime_hours' => $submission['OvertimeHours'] !== null ? (float)$submission['OvertimeHours'] : null,
                'total_overtime_hours' => $submission['TotalOvertimeHours'] !== null ? (float)$submission['TotalOvertimeHours'] : null,
                'classification' => dtr_classification_decode($submission['classification_remarks'] ?? null),
                'submitted_at' => $submission['CreatedAt'],
                'status' => 'Submitted',
            ],
            'daily_breakdown' => $dailyBreakdown,
        ]);
        exit();
    }

    // ---- Detail mode 1: Get Employees included in a specific Batch (TeamLead + Company + Cutoff) ----
    if (isset($_GET['tl_id'], $_GET['cutoff_start'], $_GET['cutoff_end'])) {
        $tlId = (int)$_GET['tl_id'];
        $cutoffStart = trim((string)$_GET['cutoff_start']);
        $cutoffEnd = trim((string)$_GET['cutoff_end']);

        $siteId=(int)($_GET['site_id']??0);
        $empListStmt = $conn->prepare("
            SELECT ds.DTR_SubmissionID, ds.EmployeeID,
                   CONCAT(e.FirstName, ' ', e.LastName) AS employee_name,
                   e.EmployeeNumber AS employee_number,
                   e.Position AS position,
                   ds.TotalHours, ds.CreatedAt,
                   ds.ReviewedBy, ds.ReviewDate,
                   tl.FullName AS tl_full_name,
                   cc.CompanyID AS company_id,
                   ds.SiteID AS site_id,ws.Name AS site_name,
                   cc.Name AS company_name,
                   cc.PayrollType AS payroll_type,
                   ds.CutoffPeriod, ds.TotalOvertimeHours, ds.OvertimeHours, ds.Remarks
            FROM dtr_submissions ds
            INNER JOIN employee e ON e.EmployeeID = ds.EmployeeID
            LEFT JOIN users tl ON tl.UsersID = ds.SiteSupervisorID
            LEFT JOIN deployment d ON d.DeploymentID = ds.DeploymentID
            LEFT JOIN deployment_sites ws ON ws.SiteID=ds.SiteID
            LEFT JOIN clientcompany cc ON cc.CompanyID = d.CompanyID
            WHERE ds.SiteSupervisorID = ? AND ds.CutoffStartDate = ? AND ds.CutoffEndDate = ? AND (?=0 OR ds.SiteID=?)
            ORDER BY employee_name ASC
        ");
        $empListStmt->bind_param('issii', $tlId, $cutoffStart, $cutoffEnd,$siteId,$siteId);
        $empListStmt->execute();
        $resEmp = $empListStmt->get_result();

        $pdo = attendance_pdo();
        if (function_exists('attendance_ensure_table')) {
            attendance_ensure_table($pdo);
        }
        $todayIso = date('Y-m-d');

        $employees = [];
        $batchInfo = null;
        while ($row = $resEmp->fetch_assoc()) {
            if (!$batchInfo) {
                $batchInfo = [
                    'team_lead_name' => trim((string)($row['tl_full_name'] ?? '')),
                    'company_id' => isset($row['company_id']) ? (int)$row['company_id'] : null,
                    'site_id'=>(int)($row['site_id']??0),'site_name'=>$row['site_name']??'Legacy site',
                    'company_name' => $row['company_name'],
                    'payroll_type' => payroll_type_normalize($row['payroll_type'] ?? null),
                    'payroll_type_label' => payroll_type_label($row['payroll_type'] ?? null),
                    'cutoff_period' => $row['CutoffPeriod'],
                    'cutoff_start' => $cutoffStart,
                    'cutoff_end' => $cutoffEnd,
                ];
            }

            // Per-employee Late / Undertime totals + a single summary Attendance
            // Status for this cutoff (Absent takes priority, then Late, else
            // Present) — reusing the same day-by-day status engine the single-
            // employee DTR detail view (below) and the Smart Calendar already use.
            $empId = (int)$row['EmployeeID'];
            $dtrStmt2 = $pdo->prepare("
                SELECT date, time_in, break_time, time_out, total_hours_rendered, status
                FROM daily_time_records
                WHERE employee_id = ? AND date BETWEEN ? AND ?
            ");
            $dtrStmt2->execute([$empId, $cutoffStart, $cutoffEnd]);
            $dtrByDate2 = [];
            foreach ($dtrStmt2->fetchAll(PDO::FETCH_ASSOC) as $dtrRow2) {
                $dtrByDate2[$dtrRow2['date']] = $dtrRow2;
            }

            $totalLateMinutes = 0;
            $totalUndertimeMinutes = 0;
            $hasAbsent = false;
            $hasLate = false;
            $cursor2 = new DateTime($cutoffStart);
            $end2 = new DateTime($cutoffEnd);
            while ($cursor2 <= $end2) {
                $iso2 = $cursor2->format('Y-m-d');
                $dtrRow2 = $dtrByDate2[$iso2] ?? null;
                $schedule2 = payroll_resolve_deployment_schedule($conn, $empId, $iso2);
                $schedule2['break_minutes'] = $schedule2['break_duration'] ?? 60;
                $schedule2['time_in'] = $schedule2['time_in'] ?? '08:00:00';
                $rowForStatus2 = $dtrRow2;
                if ($rowForStatus2) {
                    $rowForStatus2['total_hours_rendered'] = $dtrRow2['total_hours_rendered'] !== null ? (float)$dtrRow2['total_hours_rendered'] : null;
                }
                $meta2 = payroll_timesheet_day_status($rowForStatus2, $iso2, $schedule2, $todayIso);
                $totalLateMinutes += (int)($meta2['late_minutes'] ?? 0);
                $totalUndertimeMinutes += (int)($meta2['undertime_minutes'] ?? 0);
                if (($meta2['status'] ?? '') === 'Absent') {
                    $hasAbsent = true;
                } elseif (($meta2['status'] ?? '') === 'Late') {
                    $hasLate = true;
                }
                $cursor2->modify('+1 day');
            }
            $attendanceStatus = $hasAbsent ? 'Absent' : ($hasLate ? 'Late' : 'Present');

            $employees[] = [
                'dtr_submission_id' => (int)$row['DTR_SubmissionID'],
                'employee_id' => $empId,
                'employee_name' => $row['employee_name'],
                'employee_number' => $row['employee_number'],
                'position' => $row['position'],
                'total_hours' => $row['TotalHours'] !== null ? (float)$row['TotalHours'] : null,
                'late_minutes' => $totalLateMinutes,
                'undertime_minutes' => $totalUndertimeMinutes,
                'approved_ot_hours' => (float)($row['TotalOvertimeHours'] ?? $row['OvertimeHours'] ?? 0),
                'classification' => dtr_classification_decode($row['Remarks'] ?? null),
                'attendance_status' => $attendanceStatus,
                'status' => 'Submitted',
                'reviewed' => $row['ReviewedBy'] !== null,
                'reviewed_at' => $row['ReviewDate'],
            ];
        }
        $empListStmt->close();

        // Team-level payroll status (payroll_status_history) for this exact
        // group — one lookup, not per employee. Falls back to the existing
        // auto-derived DTR review status when Accounting hasn't manually set
        // one yet (no separate table needed for that fallback case).
        $payrollStatus = null;
        $payrollStatusHistory = [];
        if ($batchInfo && $batchInfo['company_id']) {
            $currentStatus = payroll_status_get_current($conn, $tlId, $batchInfo['company_id'], $cutoffStart, $cutoffEnd);
            if ($currentStatus) {
                $payrollStatus = [
                    'status' => $currentStatus['Status'],
                    'remarks' => $currentStatus['Remarks'],
                    'has_proof' => $currentStatus['ProofImagePath'] !== null,
                    'proof_history_id' => $currentStatus['ProofImagePath'] !== null ? (int)$currentStatus['PayrollStatusHistoryID'] : null,
                    'updated_at' => $currentStatus['CreatedAt'],
                ];
            }
            $payrollStatusHistory = payroll_status_get_history($conn, $tlId, $batchInfo['company_id'], $cutoffStart, $cutoffEnd);
        }

        echo json_encode([
            'success' => true,
            'batch' => $batchInfo,
            'employees' => $employees,
            'payroll_status' => $payrollStatus,
            'payroll_status_history' => $payrollStatusHistory,
        ]);
        exit();
    }

    // ---- List mode: Grouped batch list for the main Accounting table ----
    $filterPayrollType = isset($_GET['payroll_type']) ? strtoupper(trim((string)$_GET['payroll_type'])) : '';
    $filterCompanyId = isset($_GET['company_id']) ? (int)$_GET['company_id'] : 0;
    $filterCutoffStart = isset($_GET['cutoff_start']) ? trim((string)$_GET['cutoff_start']) : '';
    $filterCutoffEnd = isset($_GET['cutoff_end']) ? trim((string)$_GET['cutoff_end']) : '';
    $filterStatus = isset($_GET['status']) ? trim((string)$_GET['status']) : '';

    $where = [];
    $params = [];
    $types = '';
    if ($filterPayrollType === 'A' || $filterPayrollType === 'B') {
        $where[] = 'cc.PayrollType = ?';
        $params[] = $filterPayrollType;
        $types .= 's';
    }
    if ($filterCompanyId > 0) {
        $where[] = 'cc.CompanyID = ?';
        $params[] = $filterCompanyId;
        $types .= 'i';
    }
    if ($filterCutoffStart !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterCutoffStart)) {
        $where[] = 'ds.CutoffStartDate = ?';
        $params[] = $filterCutoffStart;
        $types .= 's';
    }
    if ($filterCutoffEnd !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterCutoffEnd)) {
        $where[] = 'ds.CutoffEndDate = ?';
        $params[] = $filterCutoffEnd;
        $types .= 's';
    }
    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $listStmt = $conn->prepare("
        SELECT ds.SiteSupervisorID AS tl_id,
               ds.CutoffPeriod, ds.CutoffStartDate, ds.CutoffEndDate,
               MIN(ds.CreatedAt) AS submitted_at,
               COUNT(ds.EmployeeID) AS employee_count,
               SUM(CASE WHEN ds.ReviewedBy IS NOT NULL THEN 1 ELSE 0 END) AS reviewed_count,
               tl.FullName AS tl_full_name,
               cc.CompanyID AS company_id,
               ds.SiteID AS site_id,ws.Name AS site_name,
                   cc.Name AS company_name,
               cc.PayrollType AS payroll_type
        FROM dtr_submissions ds
        LEFT JOIN users tl ON tl.UsersID = ds.SiteSupervisorID
        LEFT JOIN deployment d ON d.DeploymentID = ds.DeploymentID
            LEFT JOIN deployment_sites ws ON ws.SiteID=ds.SiteID
        LEFT JOIN clientcompany cc ON cc.CompanyID = d.CompanyID
        {$whereSql}
        GROUP BY ds.SiteSupervisorID, ds.CutoffStartDate, ds.CutoffEndDate, cc.CompanyID,ds.SiteID
        ORDER BY submitted_at DESC
    ");
    if ($params) {
        $listStmt->bind_param($types, ...$params);
    }
    $listStmt->execute();
    $res = $listStmt->get_result();

    $submissions = [];
    while ($row = $res->fetch_assoc()) {
        $employeeCount = (int)$row['employee_count'];
        $reviewedCount = (int)$row['reviewed_count'];
        $status = 'Submitted';
        if ($employeeCount > 0 && $reviewedCount >= $employeeCount) {
            $status = 'Verified';
        } elseif ($reviewedCount > 0) {
            $status = 'Partially Verified';
        }
        if ($filterStatus !== '' && strcasecmp($filterStatus, $status) !== 0 && strcasecmp($filterStatus, 'All') !== 0) {
            continue;
        }

        // Team-level payroll status for this group (one lookup per row here
        // is fine — this list is the small "one row per Team Lead/cutoff"
        // batch table, not per-employee). Null when Accounting hasn't
        // manually set one yet; the UI falls back to the auto-derived
        // 'status' above in that case.
        $payrollStatusLabel = null;
        if (!empty($row['company_id'])) {
            $currentPayrollStatus = payroll_status_get_current(
                $conn,
                (int)$row['tl_id'],
                (int)$row['company_id'],
                $row['CutoffStartDate'],
                $row['CutoffEndDate']
            );
            if ($currentPayrollStatus) {
                $payrollStatusLabel = $currentPayrollStatus['Status'];
            }
        }

        $submissions[] = [
            'tl_id' => (int)$row['tl_id'],
            'team_lead_name' => trim((string)($row['tl_full_name'] ?? '')),
            'company_id' => isset($row['company_id']) ? (int)$row['company_id'] : null,
            'site_id'=>(int)($row['site_id']??0),'site_name'=>$row['site_name']??'Legacy site',
                    'company_name' => $row['company_name'] ?? '—',
            'payroll_type' => payroll_type_normalize($row['payroll_type'] ?? null),
            'payroll_type_label' => payroll_type_label($row['payroll_type'] ?? null),
            'cutoff_period' => $row['CutoffPeriod'],
            'cutoff_start' => $row['CutoffStartDate'],
            'cutoff_end' => $row['CutoffEndDate'],
            'employee_count' => $employeeCount,
            'reviewed_count' => $reviewedCount,
            'submitted_at' => $row['submitted_at'],
            'status' => $status,
            'payroll_status' => $payrollStatusLabel,
        ];
    }
    $listStmt->close();

    echo json_encode([
        'success' => true,
        'submissions' => $submissions,
    ]);
} catch (Throwable $e) {
    error_log('get_submitted_dtrs: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load submitted DTRs.',
        'error_details' => $e->getMessage(),
    ]);
}