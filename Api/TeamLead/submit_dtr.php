<?php
/**
 * POST — Team Lead submits selected employees' DTR for the current cutoff to Accounting.
 *
 * Body (JSON or form): employee_ids[] , cutoff_start (YYYY-MM-DD), cutoff_end (YYYY-MM-DD)
 *
 * Writes one row per employee into the EXISTING `dtr_submissions` table
 * (already present in the schema with FKs to employee/deployment/users/status —
 * no new table, no schema change). This is a real database write; nothing is
 * simulated on the frontend.
 *
 * Authorization: a Team Lead may only submit an employee who is on THEIR OWN
 * deployment roster (deployment.SiteSuperVisorID = the logged-in TL's UsersID) —
 * same ownership check used by get_dtr_cutoff_review.php and
 * get_employee_attendance_logs.php. Requesting someone else's employee_id is
 * silently skipped, not trusted.
 *
 * A given employee cannot be submitted twice for the same cutoff — if a
 * dtr_submissions row already exists for that EmployeeID + CutoffStartDate +
 * CutoffEndDate, that employee is skipped (reported back as "already_submitted").
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit();
}

require_once __DIR__ . '/../Db_connection/db_connect.php'; // mysqli $conn
require_once __DIR__ . '/../Helpers/attendance_pdo.php';
require_once __DIR__ . '/../Helpers/dtr_classification.php';
require_once __DIR__ . '/../Helpers/employee_payroll_helper.php'; // payroll_kinsenas_period(), payroll_compute_period()
require_once __DIR__ . '/../Helpers/employee_deployment_context.php';

require_once __DIR__ . '/../Helpers/work_site_geofence.php';
require_once __DIR__ . '/../Helpers/payroll_type_helper.php';
$teamLeadUserId = (int)$_SESSION['user_id'];

// Accept either JSON body or classic form POST.
$raw = file_get_contents('php://input');
$json = json_decode($raw, true);
$employeeIds = [];
$cutoffStart = '';
$cutoffEnd = '';

if (is_array($json)) {
    $employeeIds = isset($json['employee_ids']) && is_array($json['employee_ids']) ? $json['employee_ids'] : [];
    $cutoffStart = trim((string)($json['cutoff_start'] ?? ''));
    $cutoffEnd = trim((string)($json['cutoff_end'] ?? ''));
} else {
    $employeeIds = isset($_POST['employee_ids']) && is_array($_POST['employee_ids']) ? $_POST['employee_ids'] : [];
    $cutoffStart = trim((string)($_POST['cutoff_start'] ?? ''));
    $cutoffEnd = trim((string)($_POST['cutoff_end'] ?? ''));
}

$employeeIds = array_values(array_unique(array_map('intval', $employeeIds)));
$employeeIds = array_filter($employeeIds, static function ($id) { return $id > 0; });

if (empty($employeeIds)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No employees selected.']);
    exit();
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $cutoffStart) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $cutoffEnd)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'cutoff_start and cutoff_end (YYYY-MM-DD) are required.']);
    exit();
}

try {
    $input=is_array($json)?$json:$_POST;$siteId=(int)($input['site_id']??0);
    $site=site_for_lead($conn,$teamLeadUserId,$siteId);
    $period=payroll_type_period_for_company($conn,(int)$site['CompanyID'],$cutoffStart);
    if($period['start']!==$cutoffStart || $period['end']!==$cutoffEnd)throw new InvalidArgumentException('Cutoff does not match this site’s production payroll calendar.');
    $owned=[];
    foreach($employeeIds as $eid){
        $d=site_owned_employee($conn,$teamLeadUserId,$eid,$siteId);
        if(!$d || !(int)$d['AllowDTRSubmission'] || $d['StartDate']>$cutoffEnd)throw new DomainException('An employee is not eligible for DTR submission at this site.',403);
        $owned[$eid]=$d;
    }
    $conn->begin_transaction();
    // A site lock serializes concurrent submission checks and assignment changes.
    site_query($conn,'SELECT SiteID FROM deployment_sites WHERE SiteID=? AND TeamLeadID=? AND IsActive=1 FOR UPDATE','ii',[$siteId,$teamLeadUserId]);
    $pdo = attendance_pdo();
    if (function_exists('attendance_ensure_table')) {
        attendance_ensure_table($pdo);
    }

    // Resolve the cutoff's display label the same way get_dtr_cutoff_review.php does,
    // by re-deriving it from a date inside the range via payroll_kinsenas_period().
    $period = payroll_type_period_for_company($conn,(int)$site['CompanyID'],$cutoffStart);
    $cutoffLabel = ($period['start'] === $cutoffStart && $period['end'] === $cutoffEnd)
        ? $period['label']
        : (date('M j, Y', strtotime($cutoffStart)) . ' – ' . date('M j, Y', strtotime($cutoffEnd)));

    $submitted = [];
    $alreadySubmitted = [];
    $skippedNotOwned = [];

    $existsStmt = $conn->prepare("
        SELECT DTR_SubmissionID FROM dtr_submissions
        WHERE EmployeeID = ? AND CutoffStartDate = ? AND CutoffEndDate = ?
        LIMIT 1
    ");

    $employeeRateStmt = $conn->prepare("
        SELECT daily_rate, hourly_rate, allowance, monthly_salary_credit, BasicSalary, Email
        FROM employee WHERE EmployeeID = ? LIMIT 1
    ");

    $insertStmt = $conn->prepare("
        INSERT INTO dtr_submissions
            (SiteID, EmployeeID, DeploymentID, SiteSupervisorID, CutoffPeriod, CutoffStartDate, CutoffEndDate,
             TotalHours, OvertimeHours, TotalOvertimeHours, SubmittedBy, Remarks, CreatedAt)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    foreach ($employeeIds as $employeeId) {
        // Shares the employee mutex with clock writes and Add Back review.
        site_query($conn,'SELECT EmployeeID FROM employee WHERE EmployeeID=? FOR UPDATE','i',[$employeeId]);
        // 1) Ownership check — this employee must actually be on this TL's roster.
        $ownerRow=site_owned_employee($conn,$teamLeadUserId,$employeeId,$siteId);
        if(!$ownerRow)throw new DomainException('Site assignment changed. Reload before submitting.',403);
        // 2) Duplicate-submission check — same employee + same cutoff can't submit twice.
        $existsStmt->bind_param('iss', $employeeId, $cutoffStart, $cutoffEnd);
        $existsStmt->execute();
        $existsRow = $existsStmt->get_result()->fetch_assoc();
        if ($existsRow) {
            $alreadySubmitted[] = $employeeId;
            continue;
        }

        // 3) Recompute this employee's totals for the cutoff server-side (never trust
        // client-supplied hours) using the same payroll_compute_period() helper the
        // review screen and the Employee Dashboard both use.
        $employeeRateStmt->bind_param('i', $employeeId);
        $employeeRateStmt->execute();
        $empRow = $employeeRateStmt->get_result()->fetch_assoc();
        if (!$empRow) {
            $skippedNotOwned[] = $employeeId;
            continue;
        }

        $dailyRate = (float)($empRow['daily_rate'] ?? 0);
        $hourlyRate = (float)($empRow['hourly_rate'] ?? 0);
        $allowance = (float)($empRow['allowance'] ?? 0);
        $msc = (float)($empRow['monthly_salary_credit'] ?? 0);
        if ($dailyRate <= 0 && (float)($empRow['BasicSalary'] ?? 0) > 0) {
            $dailyRate = round((float)$empRow['BasicSalary'] / 26, 2);
            $hourlyRate = round($dailyRate / 8, 2);
            $msc = round((float)$empRow['BasicSalary'], 2);
        }
        if ($hourlyRate <= 0 && $dailyRate > 0) {
            $hourlyRate = round($dailyRate / 8, 2);
        }

        $workSchedule = '';
        $userLookupStmt = $conn->prepare("SELECT UsersID FROM users WHERE Email = ? LIMIT 1");
        if ($userLookupStmt && !empty($empRow['Email'])) {
            $userLookupStmt->bind_param('s', $empRow['Email']);
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

        $periodTotals = payroll_compute_period(
            $conn,
            $pdo,
            $employeeId,
            $dailyRate,
            $hourlyRate,
            $allowance,
            $msc,
            $cutoffStart,
            $cutoffEnd,
            $workSchedule !== '' ? $workSchedule : null,
            null,
            (int)$ownerRow['DeploymentID']
        );

        $classification=dtr_classification_from_payroll($periodTotals);
        $totalHours=$classification['regular_hours'];
        $overtimeHours=$classification['ordinary_overtime_hours'];
        $totalOvertimeHours=$classification['overtime_hours'];
        $classificationRemarks='DTR_CLASSIFICATION_V1:'.json_encode($classification,JSON_THROW_ON_ERROR);

        // 4) Insert the real submission row.
        $deploymentId = (int)$ownerRow['DeploymentID'];
        $insertStmt->bind_param(
            'iiiisssdddis',
            $siteId,
            $employeeId,
            $deploymentId,
            $teamLeadUserId,
            $cutoffLabel,
            $cutoffStart,
            $cutoffEnd,
            $totalHours,
            $overtimeHours,
            $totalOvertimeHours,
            $teamLeadUserId,
            $classificationRemarks
        );
        if ($insertStmt->execute()) {
            $submitted[] = $employeeId;
        } else {
            $skippedNotOwned[] = $employeeId; // insert failure — surfaced generically below
        }
    }

    $conn->commit();
    try { site_dtr_sync_notifications($conn,$teamLeadUserId); } catch(Throwable $notificationError) { error_log('DTR reminder refresh: '.$notificationError->getMessage()); }
    $existsStmt->close();
    $employeeRateStmt->close();
    $insertStmt->close();

    echo json_encode([
        'success' => true,
        'submitted' => $submitted,
        'already_submitted' => $alreadySubmitted,
        'skipped' => $skippedNotOwned,
        'message' => count($submitted) > 0
            ? (count($submitted) . ' DTR(s) submitted to Accounting.')
            : 'No new DTRs were submitted.',
    ]);
} catch (Throwable $e) {
    $conn->rollback();
    if($e instanceof DomainException || $e instanceof InvalidArgumentException){http_response_code($e->getCode()?:422);echo json_encode(['success'=>false,'message'=>$e->getMessage()]);exit;}
    error_log('submit_dtr: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to submit DTR.',
        'error_details' => $e->getMessage(),
    ]);
}
