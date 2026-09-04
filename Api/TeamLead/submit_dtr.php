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
require_once __DIR__ . '/../Helpers/employee_payroll_helper.php'; // payroll_kinsenas_period(), payroll_compute_period()
require_once __DIR__ . '/../Helpers/employee_deployment_context.php';

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
    $pdo = attendance_pdo();
    if (function_exists('attendance_ensure_table')) {
        attendance_ensure_table($pdo);
    }

    // Resolve the cutoff's display label the same way get_dtr_cutoff_review.php does,
    // by re-deriving it from a date inside the range via payroll_kinsenas_period().
    $period = payroll_kinsenas_period($cutoffStart);
    $cutoffLabel = ($period['start'] === $cutoffStart && $period['end'] === $cutoffEnd)
        ? $period['label']
        : (date('M j, Y', strtotime($cutoffStart)) . ' – ' . date('M j, Y', strtotime($cutoffEnd)));

    $submitted = [];
    $alreadySubmitted = [];
    $skippedNotOwned = [];

    $ownerCheckStmt = $conn->prepare("
        SELECT DeploymentID, CompanyID
        FROM deployment
        WHERE EmployeeID = ? AND SiteSuperVisorID = ?
        LIMIT 1
    ");

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
            (EmployeeID, DeploymentID, SiteSupervisorID, CutoffPeriod, CutoffStartDate, CutoffEndDate,
             TotalHours, OvertimeHours, TotalOvertimeHours, SubmittedBy, CreatedAt)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    foreach ($employeeIds as $employeeId) {
        // 1) Ownership check — this employee must actually be on this TL's roster.
        $ownerCheckStmt->bind_param('ii', $employeeId, $teamLeadUserId);
        $ownerCheckStmt->execute();
        $ownerRow = $ownerCheckStmt->get_result()->fetch_assoc();
        if (!$ownerRow) {
            $skippedNotOwned[] = $employeeId;
            continue;
        }

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
            $workSchedule !== '' ? $workSchedule : null
        );

        $totalHours = (float)($periodTotals['days_worked'] * 8); // regular-hours basis, consistent with basic_pay's day-rate calc
        $overtimeHours = (float)($periodTotals['ot_detail']['regular_ot_hours'] ?? 0);
        // Approved Team-Lead-recorded overtime (from the `overtime` table, via
        // payroll_compute_period() -> payroll_fetch_approved_overtime() rolls
        // into the same TotalOvertimeHours figure Accounting already reads —
        // no new dtr_submissions column needed.
        $approvedOtHours = (float)($periodTotals['ot_detail']['approved_overtime_hours'] ?? 0);
        $totalOvertimeHours = round(
            (float)($periodTotals['ot_detail']['sunday_hours'] ?? 0) + $overtimeHours + $approvedOtHours,
            2
        );

        // 4) Insert the real submission row.
        $deploymentId = (int)$ownerRow['DeploymentID'];
        $insertStmt->bind_param(
            'iiisssdddi',
            $employeeId,
            $deploymentId,
            $teamLeadUserId,
            $cutoffLabel,
            $cutoffStart,
            $cutoffEnd,
            $totalHours,
            $overtimeHours,
            $totalOvertimeHours,
            $teamLeadUserId
        );
        if ($insertStmt->execute()) {
            $submitted[] = $employeeId;
        } else {
            $skippedNotOwned[] = $employeeId; // insert failure — surfaced generically below
        }
    }

    $ownerCheckStmt->close();
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
    error_log('submit_dtr: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to submit DTR.',
        'error_details' => $e->getMessage(),
    ]);
}