<?php
/**
 * GET — Overtime Management roster for the logged-in Team Lead.
 *
 * For every employee currently deployed under this Team Lead (SAME ownership
 * check as Api/TeamLead/get_dtr_cutoff_review.php and
 * Api/TeamLead/get_tl_attendance_roster.php: deployment.SiteSuperVisorID =
 * the logged-in TL's UsersID), returns their regular schedule (via the
 * existing payroll_resolve_deployment_schedule()) plus any `overtime` table
 * rows for the requested date (default: today).
 *
 * Query params:
 *   ?date=YYYY-MM-DD   (optional, defaults to today)
 *
 * Read-only. Nothing is written here.
 */
declare(strict_types=1);

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
require_once __DIR__ . '/../Helpers/employee_payroll_helper.php'; // payroll_resolve_deployment_schedule()

require_once __DIR__ . '/../Helpers/work_site_geofence.php';
$current=site_current_deployment_sql();
$teamLeadUserId = (int)$_SESSION['user_id'];
require_once __DIR__.'/../Helpers/overtime_workflow.php';

$date = isset($_GET['date']) ? trim((string)$_GET['date']) : date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

try {
    otw_authorize($conn,$teamLeadUserId,['TeamLead','Team Lead']);
    // Same deployed-team base query as get_dtr_cutoff_review.php / get_tl_attendance_roster.php.
    $sql = "
        SELECT
            e.EmployeeID AS employee_id,
            e.EmployeeNumber AS employee_number,
            CONCAT(e.FirstName, ' ', e.LastName) AS employee_name,
            COALESCE(d.Position, e.Position, 'Staff') AS position,
            d.DeploymentID AS deployment_id,
            d.CompanyID AS company_id,
            s.SiteID AS site_id, s.Name AS site_name,
            cc.Name AS company_name
        FROM deployment d
        INNER JOIN deployment_sites s ON s.SiteID=d.SiteID AND s.IsActive=1
        INNER JOIN employee e ON e.EmployeeID = d.EmployeeID AND e.IsActive = 1
        LEFT JOIN clientcompany cc ON cc.CompanyID = d.CompanyID
        WHERE s.TeamLeadID = ? AND {$current}
          AND (d.EndDate IS NULL OR d.EndDate >= CURDATE())
        GROUP BY e.EmployeeID
        ORDER BY employee_name ASC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $teamLeadUserId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Existing overtime row(s) for this date, keyed by EmployeeID (an employee
    // could in principle have more than one OT session in a day — return all).
    $otByEmployee = [];
    $otStmt = $conn->prepare("
        SELECT ot.OvertimeID, ot.EmployeeID, ot.OTStartTime, ot.OTEndTime, ot.OvertimeHours,
               ot.OvertimeType, ot.OvertimeRate, ot.Remarks, ot.RequestedBy, ot.ApprovedBy, ot.EmployeeResponseRequired, ot.EmployeeResponse, ot.EmployeeRejectionReason, ot.EmployeeRespondedAt, ot.ActualTimeIn, ot.ActualTimeOut,
               s.Name AS status_name
        FROM overtime ot
        LEFT JOIN status s ON s.StatusID = ot.StatusID
        WHERE ot.DeploymentID IN (
            SELECT d.DeploymentID FROM deployment d JOIN deployment_sites ds ON ds.SiteID=d.SiteID AND ds.IsActive=1 WHERE ds.TeamLeadID = ?
        )
        AND ot.OvertimeDate = ?
        ORDER BY ot.OTStartTime ASC
    ");
    $otStmt->bind_param('is', $teamLeadUserId, $date);
    $otStmt->execute();
    $otRes = $otStmt->get_result();
    while ($otRow = $otRes->fetch_assoc()) {
        $eid = (int)$otRow['EmployeeID'];
        if (!isset($otByEmployee[$eid])) {
            $otByEmployee[$eid] = [];
        }
        $otByEmployee[$eid][] = [
            'overtime_id' => (int)$otRow['OvertimeID'],
            'ot_start' => $otRow['OTStartTime'],
            'ot_end' => $otRow['OTEndTime'],
            'ot_start_display' => $otRow['OTStartTime'] ? date('h:i A', strtotime($otRow['OTStartTime'])) : null,
            'ot_end_display' => $otRow['OTEndTime'] ? date('h:i A', strtotime($otRow['OTEndTime'])) : null,
            'ot_hours' => $otRow['OvertimeHours'] !== null ? (float)$otRow['OvertimeHours'] : null,
            'overtime_type' => $otRow['OvertimeType'],
            'overtime_rate' => $otRow['OvertimeRate'] !== null ? (float)$otRow['OvertimeRate'] : null,
            'remarks' => $otRow['Remarks'],
            'status' => $otRow['status_name'] ?? 'Pending',
            'response_required'=>(bool)$otRow['EmployeeResponseRequired'], 'employee_response'=>$otRow['EmployeeResponse'], 'rejection_reason'=>$otRow['EmployeeRejectionReason'], 'responded_at'=>$otRow['EmployeeRespondedAt'], 'actual_time_in'=>$otRow['ActualTimeIn'], 'actual_time_out'=>$otRow['ActualTimeOut'],
        ];
    }
    $otStmt->close();

    $employees = [];
    foreach ($rows as $row) {
        $employeeId = (int)$row['employee_id'];
        $schedule = payroll_resolve_deployment_schedule($conn, $employeeId, $date);

        $employees[] = [
            'employee_id' => $employeeId,
            'employee_number' => $row['employee_number'] ?: $employeeId,
            'employee_name' => $row['employee_name'],
            'position' => $row['position'],
            'company_id' => (int)$row['company_id'],
            'site_id' => (int)$row['site_id'], 'site_name' => $row['site_name'],
            'company_name' => $row['company_name'],
            'deployment_id' => (int)$row['deployment_id'],
            'regular_schedule' => date('h:i A', strtotime($schedule['time_in'])) . ' - ' . date('h:i A', strtotime($schedule['time_out'])),
            'regular_time_in' => $schedule['time_in'],
            'regular_time_out' => $schedule['time_out'],
            'overtime_sessions' => $otByEmployee[$employeeId] ?? [],
        ];
    }

    echo json_encode([
        'success' => true,
        'date' => $date, 'csrf_token'=>otw_csrf(),
        'employees' => $employees,
    ]);
} catch (DomainException $e) {
    http_response_code($e->getCode() ?: 403);
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
} catch (Throwable $e) {
    error_log('get_overtime_roster: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load overtime roster.',

    ]);
}
