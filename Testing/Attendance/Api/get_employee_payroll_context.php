<?php
declare(strict_types=1);
require_once __DIR__.'/_testing_guard.php';
require_once __DIR__.'/_selection_context.php';
require_once __DIR__.'/../../../Api/Helpers/employee_payroll_rates.php';
require_once __DIR__.'/../../../Api/Helpers/payroll_type_helper.php';
$date=(string)($_GET['date']??date('Y-m-d'));
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)||date('Y-m-d',strtotime($date))!==$date)testing_context_fail('Select a valid test date.');
$employee=(int)($_GET['employee_id']??0);
$dep=testing_employee_context($conn,$_GET,$employee,$date);$site=testing_lead_site($conn,$_GET);
$row=site_query($conn,'SELECT EmployeeID,EmployeeNumber,FirstName,LastName,Position,daily_rate,hourly_rate,BasicSalary,allowance,monthly_salary_credit FROM employee WHERE EmployeeID=? AND IsActive=1','i',[$employee])->fetch_assoc();
if(!$row)testing_context_fail('Employee is no longer active. Reload the selection.');
$rates=payroll_employee_rates($row?:[],true);
echo json_encode(['success'=>true,'employee'=>['id'=>$employee,'number'=>$row['EmployeeNumber'],'name'=>trim($row['FirstName'].' '.$row['LastName']),'position'=>$dep['Position']?:$row['Position']],
    'context'=>['company_id'=>(int)$dep['CompanyID'],'company'=>$site['CompanyName'],'site_id'=>(int)$dep['SiteID'],'site'=>$site['Name'],'team_lead_id'=>(int)$_GET['team_lead_id'],'deployment_id'=>(int)$dep['DeploymentID'],'payroll_type'=>$site['PayrollType']],
    'rates'=>$rates,'date'=>$date,'period'=>payroll_type_period_for_company($conn,(int)$dep['CompanyID'],$date),
    'message'=>$rates['valid']?'':'No valid payroll rate was found for this employee. Check employee.daily_rate, hourly_rate and BasicSalary.']);
