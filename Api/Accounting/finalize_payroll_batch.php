<?php
/**
 * POST — Accounting "Finalize Payroll" for a Team Lead DTR submission batch.
 *
 * WHY THIS FILE EXISTS:
 * The `payroll` table (Payroll_ID, EmployeeID, PayPeriodStart/End, BasicSalary,
 * OvertimePay, HolidayPay, GrossPay, SSSDeduction, PhilHealthDeduction,
 * PagIBIGDeduction, TotalDeductions, NetPay, ProcessedBy, ProcessedDate,
 * PayslipGenerated, DTR_SubmissionID, StatusID, ...) already exists in the
 * schema but nothing in the codebase writes to it — Api/Accounting/
 * compute_payroll_for_batch.php only advances payroll_runs.CurrentStatus and
 * computes figures LIVE for on-screen review; it never persists them. Without
 * a persisted, immutable record, there is nothing for an Employee Payroll
 * History feature to read that qualifies as "finalized" rather than a live,
 * still-changeable calculation.
 *
 * This endpoint is that missing persistence step. It does NOT introduce a new
 * calculation — it calls the exact same payroll_compute_period() already
 * trusted by submit_dtr.php and the Accounting review screen, then writes
 * that result into `payroll` once per employee/cutoff (idempotent: re-running
 * this on an already-finalized batch UPDATEs the existing row instead of
 * inserting a duplicate).
 *
 * NOTE — Payroll Type A/B: no `PayrollType` column or alternating
 * SSS/PhilHealth/Pag-IBIG-by-cycle logic exists anywhere in this codebase
 * (checked `clientcompany` schema and every Accounting/payroll PHP file).
 * payroll_compute_period() currently applies all three government deductions,
 * halved, every cutoff — the same one formula used everywhere else in the
 * project today. This endpoint reuses that as-is rather than inventing a
 * Type A/B toggle that doesn't exist in the actual system.
 *
 * Body (JSON or form): tl_id, cutoff_start (YYYY-MM-DD), cutoff_end (YYYY-MM-DD)
 */
declare(strict_types=1);

require_once __DIR__ . '/../Common/session_init.php';
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit();
}

require_once __DIR__ . '/../Db_connection/db_connect.php'; // mysqli $conn
require_once __DIR__ . '/../Helpers/attendance_pdo.php'; // attendance_pdo()
require_once __DIR__ . '/../Helpers/employee_payroll_helper.php'; // payroll_compute_period(), payroll_kinsenas_period()

$accountingUserId = (int)$_SESSION['user_id'];

$raw = file_get_contents('php://input');
$json = json_decode($raw, true);
$body = is_array($json) ? $json : $_POST;

$tlId = (int)($body['tl_id'] ?? 0);
$cutoffStart = trim((string)($body['cutoff_start'] ?? ''));
$cutoffEnd = trim((string)($body['cutoff_end'] ?? ''));

if ($tlId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $cutoffStart) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $cutoffEnd)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'tl_id, cutoff_start, and cutoff_end are required.']);
    exit();
}

function payroll_finalize_status_id(mysqli $conn): ?int
{
    $stmt = $conn->prepare("SELECT StatusID FROM status WHERE Name = 'Finalized' LIMIT 1");
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (int)$row['StatusID'] : null;
}

try {
    $pdo = attendance_pdo();

    require_once __DIR__.'/../Helpers/add_back_payroll.php';
    $statusId = payroll_finalize_status_id($conn);
    if ($statusId === null) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => "The 'Finalized' payroll status is not configured. Run sql/payroll_history_feature_migration.sql first."]);
        exit();
    }

    // Every employee this TL submitted a DTR for, in this cutoff — same base
    // query shape as get_submitted_dtrs.php's batch employee list.
    $listStmt = $conn->prepare("
        SELECT ds.DTR_SubmissionID, ds.EmployeeID, ds.DeploymentID
        FROM dtr_submissions ds
        WHERE ds.SiteSupervisorID = ? AND ds.CutoffStartDate = ? AND ds.CutoffEndDate = ?
    ");
    $listStmt->bind_param('iss', $tlId, $cutoffStart, $cutoffEnd);
    $listStmt->execute();
    $submissions = $listStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $listStmt->close();

    if (empty($submissions)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'No DTR submissions found for this Team Lead and cutoff.']);
        exit();
    }

    $employeeRateStmt = $conn->prepare("
        SELECT daily_rate, hourly_rate, allowance, monthly_salary_credit, BasicSalary, Email
        FROM employee WHERE EmployeeID = ? LIMIT 1
    ");
    $existingPayrollStmt = $conn->prepare("
        SELECT Payroll_ID FROM payroll WHERE EmployeeID = ? AND PayPeriodStart = ? AND PayPeriodEnd = ? LIMIT 1
    ");
    $insertStmt = $conn->prepare("
        INSERT INTO payroll
            (EmployeeID, PayPeriodStart, PayPeriodEnd, TotalDaysWorked, TotalHoursWorked, RegularHours,
             OvertimeHours, BasicSalary, OvertimePay, HolidayPay, Allowances, GrossPay,
             SSSDeduction, PhilHealthDeduction, PagIBIGDeduction, TotalDeductions, NetPay,
             PaymentDate, ProcessedBy, ProcessedDate, PayslipGenerated, DTR_SubmissionID, StatusID, HolidayID, CreatedAt)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 1, ?, ?, ?, NOW())
    ");
    $updateStmt = $conn->prepare("
        UPDATE payroll SET
            TotalDaysWorked = ?, TotalHoursWorked = ?, RegularHours = ?, OvertimeHours = ?,
            BasicSalary = ?, OvertimePay = ?, HolidayPay = ?, Allowances = ?, GrossPay = ?,
            SSSDeduction = ?, PhilHealthDeduction = ?, PagIBIGDeduction = ?, TotalDeductions = ?, NetPay = ?,
            PaymentDate = ?, ProcessedBy = ?, ProcessedDate = NOW(), PayslipGenerated = 1,
            DTR_SubmissionID = ?, StatusID = ?, HolidayID = ?
        WHERE Payroll_ID = ?
    ");

    $finalized = [];
    $errors = [];

    $period = payroll_kinsenas_period($cutoffStart);
    $paymentDate = $period['payday'];

    foreach ($submissions as $sub) {
        $employeeId = (int)$sub['EmployeeID'];

        $employeeRateStmt->bind_param('i', $employeeId);
        $employeeRateStmt->execute();
        $empRow = $employeeRateStmt->get_result()->fetch_assoc();
        if (!$empRow) {
            $errors[] = ['employee_id' => $employeeId, 'message' => 'Employee record not found.'];
            continue;
        }

        require_once __DIR__ . '/../Helpers/employee_payroll_rates.php';
        $resolvedRates=payroll_employee_rates($empRow,false);
        $dailyRate=$resolvedRates['daily_rate'];
        $hourlyRate=$resolvedRates['hourly_rate'];
        $allowance=$resolvedRates['allowance'];
        $msc=$resolvedRates['monthly_salary_credit'];

        $conn->begin_transaction();
        $employeeLock=$conn->prepare('SELECT EmployeeID FROM employee WHERE EmployeeID=? FOR UPDATE');
        $employeeLock->bind_param('i',$employeeId); $employeeLock->execute(); $employeeLock->close();
        $existingPayrollStmt->bind_param('iss',$employeeId,$cutoffStart,$cutoffEnd);
        $existingPayrollStmt->execute(); $lockedExisting=$existingPayrollStmt->get_result()->fetch_assoc();
        if ($lockedExisting) {
            $conn->commit();
            $finalized[]=['employee_id'=>$employeeId,'payroll_id'=>(int)$lockedExisting['Payroll_ID'],'already_finalized'=>true];
            continue;
        }
        $totals = payroll_compute_period(
            $conn,
            $pdo,
            $employeeId,
            $dailyRate,
            $hourlyRate,
            $allowance,
            $msc,
            $cutoffStart,
            $cutoffEnd,
            null,
            null,
            (int)$sub['DeploymentID']
        );

        $earnings = $totals['earnings'] ?? [];
        $deductions = $totals['deductions'] ?? [];
        $otDetail = $totals['ot_detail'] ?? [];

        $totalDaysWorked = (float)($totals['days_worked'] ?? 0);
        $totalHoursWorked = round($totalDaysWorked * 8, 2);
        $regularHours = $totalHoursWorked;
        $overtimeHours = (float)($otDetail['sunday_hours'] ?? 0) + (float)($otDetail['regular_ot_hours'] ?? 0) + (float)($otDetail['approved_overtime_hours'] ?? 0) + (float)($otDetail['holiday_ot_hours'] ?? 0);
        $basicSalary = (float)($earnings['basic_pay'] ?? 0);
        // Holiday OT (overtime rendered on a holiday, paid at multiplier x 1.3
        // per Templates/SAMPLE.xlsx) is folded into OvertimePay rather than
        // HolidayPay — it IS overtime, and the payroll table has no separate
        // "holiday OT" column, so this avoids adding a duplicate pay column.
        // HolidayPay stays dedicated to the non-OT holiday-duty premium only.
        $overtimePay = (float)($earnings['sunday_overtime'] ?? 0) + (float)($earnings['regular_overtime'] ?? 0) + (float)($earnings['approved_overtime'] ?? 0) + (float)($earnings['holiday_ot_pay'] ?? 0);
        $holidayPay = (float)($earnings['holiday_pay'] ?? 0);
        $holidayId = $totals['holiday_id'] ?? null;
        $allowancesPaid = (float)($earnings['allowance'] ?? 0) + (float)($earnings['sil_accrual'] ?? 0);
        $grossPay = (float)($totals['gross_pay'] ?? 0);
        $sss = (float)($deductions['sss_ee'] ?? 0);
        $philhealth = (float)($deductions['philhealth_ee'] ?? 0);
        $pagibig = (float)($deductions['pagibig_ee'] ?? 0);
        $totalDeductions = (float)($totals['total_deductions'] ?? ($sss + $philhealth + $pagibig));
        $netPay = (float)($totals['net_pay'] ?? ($grossPay - $totalDeductions));

        $dtrSubmissionId = (int)$sub['DTR_SubmissionID'];

        $existingPayrollStmt->bind_param('iss', $employeeId, $cutoffStart, $cutoffEnd);
        $existingPayrollStmt->execute();
        $existingRow = $existingPayrollStmt->get_result()->fetch_assoc();

        if ($existingRow) {
            $payrollId = (int)$existingRow['Payroll_ID'];
            $updateStmt->bind_param(
                'ddddddddddddddsiiiii',
                $totalDaysWorked,
                $totalHoursWorked,
                $regularHours,
                $overtimeHours,
                $basicSalary,
                $overtimePay,
                $holidayPay,
                $allowancesPaid,
                $grossPay,
                $sss,
                $philhealth,
                $pagibig,
                $totalDeductions,
                $netPay,
                $paymentDate,
                $accountingUserId,
                $dtrSubmissionId,
                $statusId,
                $holidayId,
                $payrollId
            );
            $ok = $updateStmt->execute();
        } else {
            $insertStmt->bind_param(
                'issddddddddddddddsiiii',
                $employeeId,
                $cutoffStart,
                $cutoffEnd,
                $totalDaysWorked,
                $totalHoursWorked,
                $regularHours,
                $overtimeHours,
                $basicSalary,
                $overtimePay,
                $holidayPay,
                $allowancesPaid,
                $grossPay,
                $sss,
                $philhealth,
                $pagibig,
                $totalDeductions,
                $netPay,
                $paymentDate,
                $accountingUserId,
                $dtrSubmissionId,
                $statusId,
                $holidayId
            );
            $ok = $insertStmt->execute();
            $payrollId = $insertStmt->insert_id;
        }

        if ($ok) {
            add_back_post_payroll($conn,(int)$payrollId,$employeeId,(int)$sub['DeploymentID'],$cutoffStart,$cutoffEnd);
            $conn->commit();
            $finalized[] = ['employee_id' => $employeeId, 'payroll_id' => $payrollId, 'net_pay' => round($netPay, 2)];
        } else {
            $conn->rollback();
            $errors[] = ['employee_id' => $employeeId, 'message' => $conn->error];
        }
    }

    echo json_encode([
        'success' => true,
        'finalized' => $finalized,
        'errors' => $errors,
        'message' => count($finalized) . ' payroll record(s) finalized.',
    ]);
} catch (Throwable $e) {
    $conn->rollback();
    error_log('finalize_payroll_batch: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to finalize payroll.',
        'error_details' => $e->getMessage(),
    ]);
}