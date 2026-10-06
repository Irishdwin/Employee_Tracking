<?php
/** Existing Accounting/employee-payroll rate precedence, without changing rounding. */
declare(strict_types=1);
function payroll_employee_rates(array $employee, bool $deriveSalaryCredit=false): array {
    $daily=(float)($employee['daily_rate']??0); $hourly=(float)($employee['hourly_rate']??0);
    $credit=(float)($employee['monthly_salary_credit']??0);
    $dailySource='employee.daily_rate'; $hourlySource='employee.hourly_rate';
    if($daily<=0 && (float)($employee['BasicSalary']??0)>0){
        $daily=round((float)$employee['BasicSalary']/26,2);$hourly=round($daily/8,2);
        $credit=round((float)$employee['BasicSalary'],2);
        $dailySource='round(employee.BasicSalary / 26, 2)';$hourlySource='round(resolved daily_rate / 8, 2)';
    }
    if($hourly<=0 && $daily>0){$hourly=round($daily/8,2);$hourlySource='round(resolved daily_rate / 8, 2)';}
    if($deriveSalaryCredit && $credit<=0 && $daily>0)$credit=round($daily*26,2);
    return ['daily_rate'=>$daily,'hourly_rate'=>$hourly,'allowance'=>(float)($employee['allowance']??0),'monthly_salary_credit'=>$credit,
        'daily_rate_source'=>$dailySource,'hourly_rate_source'=>$hourlySource,'effective_dated'=>false,
        'valid'=>$daily>0 && $hourly>0 && is_finite($daily) && is_finite($hourly)];
}
