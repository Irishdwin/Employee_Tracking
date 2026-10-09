<?php
declare(strict_types=1);
require_once __DIR__.'/_testing_guard.php';
require_once __DIR__.'/_selection_context.php';
require_once __DIR__.'/../../../Api/Helpers/employee_payroll_rates.php';
require_once __DIR__.'/../../../Api/Helpers/payroll_type_helper.php';
$date=(string)($_GET['date']??date('Y-m-d'));
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)||date('Y-m-d',strtotime($date))!==$date)testing_context_fail('Select a valid test date.');
$employee=(int)($_GET['employee_id']??0);
// Read-only Admin preview resolves the applicable site; writes still verify every supplied ID.
if(($_GET['resolve']??'')==='1'){
 $dep=testing_dated_deployment($conn,$employee,$date);
 $preview=site_query($conn,'SELECT CompanyID,TeamLeadID FROM deployment_sites WHERE SiteID=?','i',[(int)$dep['SiteID']])->fetch_assoc();
 if(!$preview||(int)$preview['CompanyID']!==(int)$dep['CompanyID'])testing_context_fail('The historical deployment has no valid mapped site.');
 $input=['company_id'=>$dep['CompanyID'],'site_id'=>$dep['SiteID'],'team_lead_id'=>$preview['TeamLeadID'],'deployment_id'=>$dep['DeploymentID'],'date'=>$date];
 $site=testing_lead_site($conn,$input);
}else{$dep=testing_employee_context($conn,$_GET,$employee,$date);$site=testing_lead_site($conn,$_GET);}
$lockedMessage='';try{testing_assert_unlocked($conn,$employee,$date);}catch(DomainException $e){$lockedMessage=$e->getMessage();}

$row=site_query($conn,'SELECT EmployeeID,EmployeeNumber,FirstName,LastName,Position,daily_rate,hourly_rate,BasicSalary,allowance,monthly_salary_credit FROM employee WHERE EmployeeID=?','i',[$employee])->fetch_assoc();
if(!$row)testing_context_fail('Employee is no longer active. Reload the selection.');
$rates=payroll_employee_rates($row?:[],true);
echo json_encode(['success'=>true,'employee'=>['id'=>$employee,'number'=>$row['EmployeeNumber'],'name'=>trim($row['FirstName'].' '.$row['LastName']),'position'=>$dep['Position']?:$row['Position']],
    'context'=>['company_id'=>(int)$dep['CompanyID'],'company'=>$site['CompanyName'],'site_id'=>(int)$dep['SiteID'],'site'=>$site['Name'],'team_lead_name'=>$site['TeamLeadName'],'team_lead_id'=>(int)$site['TeamLeadID'],'deployment_id'=>(int)$dep['DeploymentID'],'payroll_type'=>$site['PayrollType']],
    'locked'=>$lockedMessage!=='','lock_message'=>$lockedMessage,'rates'=>$rates,'date'=>$date,'period'=>payroll_type_period_for_company($conn,(int)$dep['CompanyID'],$date),
    'message'=>$rates['valid']?'':'No valid payroll rate was found for this employee. Check employee.daily_rate, hourly_rate and BasicSalary.']);
