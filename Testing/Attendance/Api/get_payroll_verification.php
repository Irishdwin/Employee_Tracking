<?php
/**
 * DEVELOPMENT / TESTING ONLY. GET ?employee_id=X&date=YYYY-MM-DD
 *
 * Payroll-test-scenario support ("8. Payroll Verification"). Read-only.
 *
 * This is the "Actual" side of Expected-vs-Actual. It does NOT reimplement
 * any payroll formula — it resolves the same inputs the real payroll flow
 * resolves (employee's real daily_rate/hourly_rate/allowance/monthly salary
 * credit, real deployment work schedule, the real Payroll Type A/B cutoff
 * that the test date falls in — via payroll_type_period_for_company(), the
 * same function Api/Employee/get_employee_payroll.php uses) and then calls
 * the REAL production function payroll_compute_period() from
 * Api/Helpers/employee_payroll_helper.php for that exact cutoff — the same
 * function Accounting's own payroll computation
 * (Api/Accounting/compute_payroll_for_batch.php) and the Employee payslip
 * both call. The result is computed by that engine for the resolved inputs;
 * verifying a finalized Accounting batch also requires matching its inputs.
 */
declare(strict_types=1);

require_once __DIR__ . '/_testing_guard.php';
require_once __DIR__ . '/_selection_context.php';
require_once __DIR__ . '/../../../Api/Helpers/employee_payroll_helper.php';
require_once __DIR__ . '/../../../Api/Helpers/employee_salary_helper.php';
require_once __DIR__ . '/../../../Api/Helpers/attendance_pdo.php';
require_once __DIR__ . '/../../../Api/Helpers/client_billing_helper.php';
require_once __DIR__ . '/../../../Api/Helpers/payroll_type_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(405); echo json_encode(['success'=>false,'message'=>'Payroll verification is read-only.']); exit; }

$employeeId = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
$date = trim((string)($_GET['date'] ?? ''));

$parts=explode('-',$date);
if ($employeeId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !checkdate((int)($parts[1]??0),(int)($parts[2]??0),(int)($parts[0]??0))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'employee_id and a valid date (YYYY-MM-DD) are required.']);
    exit();
}

try {
    $selectedDeployment=testing_employee_context($conn,$_GET,$employeeId,$date);

    // Same salary resolution/fallback chain as
    // Api/Employee/get_employee_payroll.php — not re-derived differently.
    $empStmt = $conn->prepare(
        "SELECT EmployeeID, EmployeeNumber, FirstName, LastName, daily_rate, hourly_rate, allowance, monthly_salary_credit, BasicSalary
         FROM employee WHERE EmployeeID = ? LIMIT 1"
    );
    $empStmt->bind_param('i', $employeeId);
    $empStmt->execute();
    $employee = $empStmt->get_result()->fetch_assoc();
    $empStmt->close();

    if (!$employee) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Employee not found or inactive.']);
        exit();
    }

    require_once __DIR__ . '/../../../Api/Helpers/employee_payroll_rates.php';
    $resolvedRates=payroll_employee_rates($employee,true);
    $dailyRate=$resolvedRates['daily_rate'];
    $hourlyRate=$resolvedRates['hourly_rate'];
    $allowance=$resolvedRates['allowance'];
    $msc=$resolvedRates['monthly_salary_credit'];

    if ($dailyRate <= 0) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'This employee has no configured Daily Rate / Basic Salary — cannot run a real payroll computation for them.']);
        exit();
    }

    $depRow=$selectedDeployment;
    $depRow['company_name']=testing_lead_site($conn,$_GET)['CompanyName'];

    if (!$depRow || (int)$depRow['CompanyID'] <= 0) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'This employee has no active deployment on ' . $date . ' — cannot determine which payroll cutoff/company to compute against.']);
        exit();
    }

    $companyId = (int)$depRow['CompanyID'];
    $workSchedule = trim((string)($depRow['WorkingDays'] ?? ''));

    // Resolves the REAL Payroll Type A/B cutoff containing the test date —
    // NOT necessarily "today's" period, so a backdated test scenario is
    // checked against the cutoff it actually belongs to.
    $period = payroll_type_period_for_company($conn, $companyId, $date);
    $deductionFlags = payroll_type_deductions($period['type'] ?? 'B', $period['half'] ?? '1st');

    $pdo = attendance_pdo($conn);

    $payroll = payroll_compute_period(
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

    echo json_encode([
        'success' => true,
        'employee' => [
            'employee_id' => $employeeId,
            'employee_number' => (string)$employee['EmployeeNumber'],
            'name' => trim($employee['FirstName'] . ' ' . $employee['LastName']),
            'company' => (string)($depRow['company_name'] ?? ''),
            'daily_rate' => $dailyRate,
            'hourly_rate' => $hourlyRate,
        ],
        'period' => $period,
        'payroll' => $payroll,
        'source' => 'payroll_compute_period() — the same production function used by Accounting payroll computation and the Employee payslip. This is the real, actual result, not a re-derived estimate.',
    ]);
} catch (Throwable $e) {
    error_log('[Testing/Attendance] get_payroll_verification: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to run the real payroll computation.']);
}
