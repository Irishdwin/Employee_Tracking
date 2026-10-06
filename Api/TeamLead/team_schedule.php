<?php
declare(strict_types=1);
require_once __DIR__ . '/../Common/session_init.php';
header('Content-Type: application/json; charset=utf-8');

function team_schedule_response(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data);
    exit();
}

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    team_schedule_response(['success' => false, 'message' => 'Unauthorized'], 401);
}
if (!in_array(trim((string)$_SESSION['role']), ['TeamLead', 'Team Lead'], true)) {
    team_schedule_response(['success' => false, 'message' => 'Forbidden'], 403);
}
require_once __DIR__ . '/../Db_connection/db_connect.php';
require_once __DIR__ . '/../Helpers/employee_payroll_helper.php';
$leadId = (int)$_SESSION['user_id'];
$transaction = false;

try {
    if (!team_schedule_table_available($conn)) {
        team_schedule_response(['success' => false, 'message' => 'Apply sql/team_schedule_migration.sql before using Team Schedule.'], 503);
    }
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $_SESSION['team_schedule_csrf'] = $_SESSION['team_schedule_csrf'] ?? bin2hex(random_bytes(32));
        $siteId=(int)($_GET['site_id']??0);$sites=sites_for_lead($conn,$leadId);
        if($siteId)site_for_lead($conn,$leadId,$siteId);
        $rows = team_schedule_query($conn,
            "SELECT d.*, CONCAT(e.FirstName, ' ', e.LastName) AS EmployeeName, c.Name AS CompanyName
             FROM deployment d JOIN employee e ON e.EmployeeID = d.EmployeeID AND e.IsActive = 1
             JOIN clientcompany c ON c.CompanyID = d.CompanyID
             WHERE EXISTS(SELECT 1 FROM deployment_sites s WHERE s.SiteID=d.SiteID AND s.TeamLeadID=? AND s.IsActive=1) AND d.SiteID=? AND d.StartDate <= CURDATE()
               AND (d.EndDate IS NULL OR d.EndDate >= CURDATE())
               AND d.DeploymentID = (SELECT d2.DeploymentID FROM deployment d2
                   WHERE d2.EmployeeID = d.EmployeeID AND d2.StartDate <= CURDATE()
                     AND (d2.EndDate IS NULL OR d2.EndDate >= CURDATE())
                   ORDER BY d2.StartDate DESC, d2.DeploymentID DESC LIMIT 1)
             ORDER BY EmployeeName", 'ii', [$leadId,$siteId]);
        $employees = []; $entries = [];
        while ($d = $rows->fetch_assoc()) {
            $schedule = team_schedule_shape($d);
            $schedule['schedule_type'] = 'Fixed';
            $schedule['date'] = date('Y-m-d');
            $schedule['working_day_names'] = array_map(static function ($n) {
                return ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'][$n];
            }, payroll_scheduled_weekdays($schedule['working_days']));
            $employees[] = [
                'deployment_id' => (int)$d['DeploymentID'], 'employee_id' => (int)$d['EmployeeID'],
                'name' => $d['EmployeeName'], 'company' => $d['CompanyName'],
                'start_date' => $d['StartDate'], 'end_date' => $d['EndDate'], 'fixed' => $schedule,
            ];
            $schedule['employee_name'] = $d['EmployeeName'];
            $schedule['status'] = 'Recurring'; $entries[] = $schedule;
            $dated = team_schedule_query($conn,
                "SELECT * FROM team_schedule_entries WHERE DeploymentID = ? AND EntryKind = 'Dated' ORDER BY ScheduleDate DESC",
                'i', [(int)$d['DeploymentID']]);
            while ($row = $dated->fetch_assoc()) {
                $entry = team_schedule_shape($row);
                $entry['date'] = $row['ScheduleDate'];
                $entry['employee_name'] = $d['EmployeeName'];
                $entry['status'] = $row['ScheduleDate'] < date('Y-m-d') ? 'Past' : 'Assigned';
                $entries[] = $entry;
            }
        }
        team_schedule_response(['success' => true, 'employees' => $employees, 'schedules' => $entries,
            'sites'=>$sites,'today' => date('Y-m-d'), 'csrf_token' => $_SESSION['team_schedule_csrf']]);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        team_schedule_response(['success' => false, 'message' => 'Method not allowed.'], 405);
    }
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) throw new InvalidArgumentException('A JSON request is required.');
    if (!isset($_SESSION['team_schedule_csrf']) || !is_string($input['csrf_token'] ?? null)
        || !hash_equals($_SESSION['team_schedule_csrf'], $input['csrf_token'])) {
        team_schedule_response(['success' => false, 'message' => 'Refresh Team Schedule before saving.'], 403);
    }
    $deploymentId = filter_var($input['deployment_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$deploymentId || $deploymentId < 1) throw new InvalidArgumentException('Select a deployed employee.');
    $siteId=(int)($input['site_id']??0);site_for_lead($conn,$leadId,$siteId);
    $selected=site_query($conn,'SELECT EmployeeID FROM deployment WHERE DeploymentID=? AND SiteID=?','ii',[$deploymentId,$siteId])->fetch_assoc();
    if(!$selected || !site_owned_employee($conn,$leadId,(int)$selected['EmployeeID'],$siteId))throw new DomainException('Employee is not currently assigned to the selected site.',403);
    $conn->begin_transaction(); $transaction = true;
    if (($input['action'] ?? '') === 'save') {
        $event = team_schedule_save($conn, $leadId, $deploymentId, $input);
    } elseif (($input['action'] ?? '') === 'delete') {
        $scheduleId = filter_var($input['schedule_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$scheduleId || $scheduleId < 1) throw new InvalidArgumentException('Select a dated schedule.');
        team_schedule_delete($conn, $leadId, $deploymentId, $scheduleId);
        $event = 'removed';
    } else {
        throw new InvalidArgumentException('Invalid schedule action.');
    }
    $conn->commit(); $transaction = false;
    team_schedule_response(['success' => true, 'event' => $event,
        'message' => $event === 'unchanged' ? 'No schedule changes to save.' : 'Schedule ' . $event . '. The employee has been notified.']);
} catch (Throwable $e) {
    if ($transaction) $conn->rollback();
    if ($e instanceof InvalidArgumentException || $e instanceof DomainException) {
        team_schedule_response(['success' => false, 'message' => $e->getMessage()], $e->getCode() ?: 422);
    }
    error_log('team_schedule: ' . $e->getMessage());
    team_schedule_response(['success' => false, 'message' => 'Unable to save or load the team schedule.'], 500);
}
