<?php
/** CLI-only regression cases for the extracted production salary precedence. */
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require __DIR__.'/../../Api/Helpers/employee_payroll_rates.php';
$cases=[
 ['stored rates',['daily_rate'=>700,'hourly_rate'=>87.50,'BasicSalary'=>18200],700,87.50],
 ['rounded hourly derivation',['daily_rate'=>645,'hourly_rate'=>0],645,80.63],
 ['BasicSalary fallback overrides hourly as before',['daily_rate'=>0,'hourly_rate'=>999,'BasicSalary'=>18200],700,87.50],
 ['missing salary has no fallback',[],0,0],
];
$checks=[];
foreach($cases as [$name,$row,$daily,$hourly]) {
 $r=payroll_employee_rates($row,true);
 $checks[$name]=$r['daily_rate']===$daily*1.0 && $r['hourly_rate']===$hourly*1.0 && $r['valid']===($daily>0&&$hourly>0);
}
$row=['daily_rate'=>500,'hourly_rate'=>62.50,'monthly_salary_credit'=>0];
$checks['finalizer salary-credit behavior preserved']=payroll_employee_rates($row,false)['monthly_salary_credit']===0.0;
$checks['employee API salary-credit behavior preserved']=payroll_employee_rates($row,true)['monthly_salary_credit']===13000.0;
echo json_encode(['success'=>!in_array(false,$checks,true),'checks'=>$checks],JSON_PRETTY_PRINT);
exit(in_array(false,$checks,true)?1:0);
