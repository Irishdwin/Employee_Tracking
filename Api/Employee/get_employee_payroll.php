<?php
/**
 * GET — current kinsenas payroll summary + payslip breakdown + status tracker for logged-in employee.
 */
require_once __DIR__ . '/../Common/session_init.php';
require __DIR__ . '/../Db_connection/db_connect.php';
require_once __DIR__ . '/../Helpers/employee_deployment_context.php';
require_once __DIR__ . '/../Helpers/employee_payroll_helper.php';
require_once __DIR__ . '/../Helpers/employee_salary_helper.php';
require_once __DIR__ . '/../Helpers/attendance_pdo.php';
require_once __DIR__ . '/../Helpers/client_billing_helper.php';
require_once __DIR__ . '/../Helpers/payroll_type_helper.php';
require_once __DIR__.'/../Helpers/leave_pay_notice.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'], $_SESSION['role']) || $_SESSION['role'] !== 'Employee') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$userId = (int)$_SESSION['user_id'];

try {
    employee_salary_ensure_schema($conn);
    client_billing_ensure_schema($conn);
    payroll_type_ensure_schema($conn);

    $empStmt = $conn->prepare(
        "SELECT e.EmployeeID, e.EmployeeNumber, e.FirstName, e.LastName, e.Position,
                e.daily_rate, e.hourly_rate, e.allowance, e.monthly_salary_credit, e.BasicSalary
         FROM users u
         INNER JOIN employee e ON e.Email = u.Email AND e.IsActive = 1
         WHERE u.UsersID = ?
         LIMIT 1"
    );
    $empStmt->bind_param('i', $userId);
    $empStmt->execute();
    $employee = $empStmt->get_result()->fetch_assoc();
    $empStmt->close();

    if (!$employee) {
        echo json_encode(['success' => false, 'message' => 'No employee profile linked to this account.']);
        exit();
    }

    employee_leave_pay_notice($conn,$userId);
    require_once __DIR__ . '/../Helpers/employee_payroll_rates.php';
    $resolvedRates=payroll_employee_rates($employee,true);
    $dailyRate=$resolvedRates['daily_rate'];
    $hourlyRate=$resolvedRates['hourly_rate'];
    $allowance=$resolvedRates['allowance'];
    $msc=$resolvedRates['monthly_salary_credit'];

    $hiredContext = employee_fetch_hired_placement_context($conn, $userId);
    $workSchedule = $hiredContext ? trim((string)($hiredContext['work_schedule'] ?? '')) : '';

    $deploymentCompanyId = 0;
    $deploymentCompany = '';
    $deploymentSite = '';
    $depStmt = $conn->prepare(
        "SELECT dep.DeploymentID, dep.CompanyID, dep.Location, cc.Name AS company_name
         FROM deployment dep
         LEFT JOIN clientcompany cc ON cc.CompanyID = dep.CompanyID
         WHERE dep.EmployeeID = ? AND (dep.EndDate IS NULL OR dep.EndDate >= CURDATE())
         ORDER BY dep.StartDate DESC
         LIMIT 1"
    );
    $empId = (int)$employee['EmployeeID'];
    $depStmt->bind_param('i', $empId);
    $depStmt->execute();
    $depRow = $depStmt->get_result()->fetch_assoc();
    $depStmt->close();

    if ($depRow) {
        $deploymentCompanyId = (int)($depRow['CompanyID'] ?? 0);
        $deploymentCompany = trim((string)($depRow['company_name'] ?? ''));
        $deploymentSite = employee_resolve_site_label((string)($depRow['Location'] ?? ''), $hiredContext);
        if ($deploymentSite === '—') {
            $deploymentSite = '';
        }
    }

    $period = $deploymentCompanyId > 0
        ? payroll_type_period_for_company($conn, $deploymentCompanyId)
        : payroll_kinsenas_period();
    $deductionFlags = payroll_type_deductions($period['type'] ?? 'B', $period['half'] ?? '1st');
    $pdo = attendance_pdo();
    attendance_ensure_table($pdo);

    $payroll = payroll_compute_period(
        $conn,
        $pdo,
        $empId,
        $dailyRate,
        $hourlyRate,
        $allowance,
        $msc,
        $period['start'],
        $period['end'],
        $workSchedule !== '' ? $workSchedule : null,
        $deductionFlags,
        isset($depRow['DeploymentID']) ? (int)$depRow['DeploymentID'] : null
    );

    $tracker = [
        'current_status' => 'Pending DTR',
        'remarks' => '',
        'updated_at' => '',
        'attachment_path' => '',
        'history' => []
    ];
    $ownDtr=payroll_query($conn,'SELECT DTR_SubmissionID FROM dtr_submissions WHERE EmployeeID=? AND DeploymentID=? AND CutoffStartDate=? AND CutoffEndDate=? ORDER BY DTR_SubmissionID DESC LIMIT 1','iiss',[$empId,(int)($depRow['DeploymentID'] ?? 0),$period['start'],$period['end']])->get_result()->fetch_assoc();
    if($ownDtr) {
        $resolved=payroll_status_for_dtr($conn,(int)$ownDtr['DTR_SubmissionID']);
        if($resolved['current']) {
            $latest=$resolved['current'];
            $tracker['current_status']=$latest['status'];$tracker['remarks']=$latest['remarks'];$tracker['updated_at']=$latest['created_at'];
            $tracker['proof_url']=$latest['proof_url'];$tracker['history']=$resolved['history'];
        }
    }
    $stored=payroll_query($conn,"SELECT p.* FROM payroll p JOIN status s ON s.StatusID=p.StatusID WHERE p.EmployeeID=? AND p.PayPeriodStart=? AND p.PayPeriodEnd=? AND s.Name='Finalized' ORDER BY p.Payroll_ID DESC LIMIT 1",'iss',[$empId,$period['start'],$period['end']])->get_result()->fetch_assoc();
    if($stored) {
        $payroll['earnings']['basic_pay']=(float)$stored['BasicSalary'];$payroll['earnings']['allowance']=(float)$stored['Allowances'];
        $payroll['earnings']['regular_overtime']=(float)$stored['OvertimePay'];$payroll['earnings']['sunday_overtime']=0.0;$payroll['earnings']['approved_overtime']=0.0;$payroll['earnings']['holiday_ot_pay']=0.0;
        $payroll['earnings']['holiday_pay']=(float)$stored['HolidayPay'];$payroll['earnings']['sil_accrual']=null;
        $payroll['gross_pay']=(float)$stored['GrossPay'];$payroll['total_deductions']=(float)$stored['TotalDeductions'];$payroll['net_pay']=(float)$stored['NetPay'];
        $payroll['deductions']['sss_ee']=(float)$stored['SSSDeduction'];$payroll['deductions']['philhealth_ee']=(float)$stored['PhilHealthDeduction'];$payroll['deductions']['pagibig_ee']=(float)$stored['PagIBIGDeduction'];
        $payroll['deductions']['other']=round((float)$stored['TotalDeductions']-(float)$stored['SSSDeduction']-(float)$stored['PhilHealthDeduction']-(float)$stored['PagIBIGDeduction'],2);
        $payroll['days_worked']=(float)$stored['TotalDaysWorked'];
        $detail=payroll_query($conn,'SELECT SnapshotJson FROM payroll_run_members WHERE Payroll_ID=? ORDER BY PayrollRunID DESC LIMIT 1','i',[(int)$stored['Payroll_ID']])->get_result()->fetch_assoc();
        $detail=$detail ? json_decode($detail['SnapshotJson'],true) : null;
        $payroll['daily_rate']=$detail['daily_rate'] ?? ((float)$stored['TotalDaysWorked']>0 ? round((float)$stored['BasicSalary']/(float)$stored['TotalDaysWorked'],2) : null);
        if($detail) {
            $payroll['earnings']['add_back']=(float)($detail['addback']??0);
            $payroll['earnings']['sil_accrual']=(float)($detail['sil_accrual']??0);
            $payroll['earnings']['regular_overtime']=(float)($detail['regular_ot_amount']??0);
            $payroll['earnings']['sunday_overtime']=(float)($detail['sunday_ot_amount']??0);
            $payroll['earnings']['holiday_ot_pay']=(float)($detail['holiday_ot_amount']??0)+(float)($detail['special_holiday_ot_amount']??0);
            $payroll['earnings']['approved_overtime']=round((float)$stored['OvertimePay']-$payroll['earnings']['regular_overtime']-$payroll['earnings']['sunday_overtime']-$payroll['earnings']['holiday_ot_pay'],2);
        }
        $payroll['late_undertime']=['late_minutes'=>$detail['lates_minutes']??null,'undertime_minutes'=>null,'amount'=>$detail['lates_undertime'] ?? null];
    }

    // Employee-facing status label + whether this run's computed figures are locked-in
    // final numbers, or still a running estimate for a cutoff that hasn't ended yet.
    // See payroll_status_label() for why 'Pending DTR' / 'Payroll Computed' are the only
    // two "not finalized" statuses.
    $statusMeta = payroll_status_label($tracker['current_status']);
    $tracker['status_label'] = $statusMeta['label'];
    $tracker['status_badge'] = $statusMeta['badge'];
    $statusMeta['finalized'] = (bool)$stored;
    $tracker['is_finalized'] = $statusMeta['finalized'];

    $fullName = trim($employee['FirstName'] . ' ' . $employee['LastName']);

    // Deductions and net pay must only ever reach the Employee Dashboard / Payroll
    // Overview / Payslip once the run is finalized. While a cutoff is still in progress,
    // government deductions are calculated for the FULL half-month while gross pay only
    // reflects days worked SO FAR — that mismatch is what produced misleading negative
    // "net pay" numbers, so we simply don't expose money figures until the run is final.
    $payslip = [
        'employee_name' => $fullName,
        'employee_number' => (string)$employee['EmployeeNumber'],
        'designation' => (string)($employee['Position'] ?? ''),
        'assigned_site' => $deploymentSite !== '' ? $deploymentSite : ($deploymentCompany !== '' ? $deploymentCompany : '—'),
        'company' => $deploymentCompany !== '' ? $deploymentCompany : 'Onazul Manpower Services',
        'period_label' => $period['label'],
        'is_finalized' => $statusMeta['finalized'],
        'status_label' => $statusMeta['label'],
    ];
    if(!empty($tracker['history'])) {
        $last=$tracker['history'][count($tracker['history'])-1];
        $payslip['payroll_status']=['status'=>$last['status'],'remarks'=>$last['remarks'],'updated_at'=>$last['created_at'],'has_proof'=>$last['has_proof'],'proof_url'=>$last['proof_url'],'proof_history_id'=>$last['id']];
        $payslip['payroll_status_history']=$tracker['history'];
    }
    if ($statusMeta['finalized']) {
        $payslip['days_worked'] = $payroll['days_worked'];
        $payslip['daily_rate'] = $payroll['daily_rate'];
        $payslip['earnings'] = $payroll['earnings'];
        // Late minutes / undertime minutes / combined deduction amount, shown as their
        // own payslip line items (see payroll_compute_period()'s 'late_undertime').
        $payslip['late_undertime'] = $payroll['late_undertime'];
        $payslip['deductions'] = $payroll['deductions'];
        $payslip['gross_pay'] = $payroll['gross_pay'];
        $payslip['total_deductions'] = $payroll['total_deductions'];
        $payslip['net_pay'] = $payroll['net_pay'];
        $payslip['message'] = '';
    } else {
        $payslip['message'] = 'Your payslip will be available once payroll has been finalized.';
    }

    echo json_encode([
        'success' => true,
        'employee' => [
            'employee_id' => $empId,
            'employee_number' => (string)$employee['EmployeeNumber'],
            'full_name' => $fullName,
            'position' => (string)($employee['Position'] ?? ''),
            'company' => $deploymentCompany,
            'site' => $deploymentSite,
        ],
        'period' => $period,
        'payroll_type' => $period['type'] ?? 'B',
        'payroll_type_label' => payroll_type_label($period['type'] ?? 'B'),
        'scheduled_deductions' => array_values(array_filter([
            $deductionFlags['philhealth'] ? 'PhilHealth' : null,
            $deductionFlags['pagibig'] ? 'Pag-IBIG' : null,
            $deductionFlags['sss'] ? 'SSS' : null,
        ])),
        // NOTE: 'payroll' retains the full computed figures (gross/deductions/net) for
        // any other internal consumer, but the employee-facing UI must render only
        // 'period', 'tracker' status fields, and 'attendance_summary' — never these money
        // fields directly — until 'payroll_tracker.is_finalized' is true. The finalized
        // amounts belong on the payslip only (see 'payslip' below).
        'payroll' => $stored ? $payroll : ['days_worked'=>$payroll['days_worked'],'attendance_summary'=>$payroll['attendance_summary']],
        'leave_pay_notice' => 'Your leave pay will be paid together with your 13th-month pay.',
        'payroll_tracker' => $tracker,
        'payslip' => $payslip,
    ]);
} catch (Throwable $e) {
    error_log('get_employee_payroll: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to compute payroll.']);
}