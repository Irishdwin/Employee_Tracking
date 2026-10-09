<?php
/** Synthetic database only. No production employee/payroll data is copied or changed. */
declare(strict_types=1);
if(PHP_SAPI!=='cli') {http_response_code(404);exit;}
require_once __DIR__.'/../../vendor/autoload.php';
require_once __DIR__.'/../../Api/Helpers/client_billing_helper.php';
require_once __DIR__.'/../../Api/Helpers/billing_revision_helper.php';
require_once __DIR__.'/../../Api/Helpers/employee_calendar_holidays.php';
require_once __DIR__.'/../../Api/Helpers/leave_pay_notice.php';
require_once __DIR__.'/../../Api/Accounting/Lib/client_billing_spreadsheet.php';
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
$db=new mysqli('127.0.0.1','root','');$db->set_charset('utf8mb4');
$name='codex_billing_test_'.bin2hex(random_bytes(5));$results=[];$files=[];
function check(bool $ok,string $test): void {global $results;if(!$ok)throw new RuntimeException('FAIL: '.$test);$results[]=$test;}
function rejected(callable $fn,string $test): void {try{$fn();}catch(Throwable $e){check(true,$test);return;}check(false,$test);}
/** Populate only required schema columns with harmless synthetic values. */
function fixture(mysqli $db,string $table,array $values): int {
    foreach($db->query('SHOW COLUMNS FROM `'.$table.'`')->fetch_all(MYSQLI_ASSOC) as $c) {
        if(array_key_exists($c['Field'],$values) || $c['Null']==='YES' || $c['Default']!==null || str_contains($c['Extra'],'auto_increment'))continue;
        $type=strtolower($c['Type']);$values[$c['Field']]=preg_match('/int|decimal|float|double/',$type)?1:(str_starts_with($type,'date')?'2026-10-01':(str_starts_with($type,'enum')?explode("'",$type)[1]:'Synthetic'));
    }
    $fields=array_keys($values);$stmt=$db->prepare('INSERT INTO `'.$table.'` (`'.implode('`,`',$fields).'`) VALUES('.implode(',',array_fill(0,count($fields),'?')).')');$args=array_values($values);$stmt->bind_param(str_repeat('s',count($args)),...$args);$stmt->execute();return (int)$db->insert_id;
}
try {
    $db->query('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4');$db->select_db($name);
    foreach(['users','roles','employee','clientcompany','deployment','deployment_sites','dtr_submissions','payroll','status','notifications','payroll_adjustments','payroll_audit_log','payroll_runs','payroll_status_history','payroll_group_status_history','holidays','payroll_run_members','payroll_billing_exports','payroll_billing_revisions','payroll_billing_adjustment_sources','holiday_deployment_sites'] as $table)$db->query('CREATE TABLE `'.$table.'` LIKE onazul_hris_db.`'.$table.'`');
    fixture($db,'roles',['RolesID'=>1,'RoleName'=>'Accounting']);fixture($db,'roles',['RolesID'=>2,'RoleName'=>'Employee']);
    fixture($db,'users',['UsersID'=>10,'RoleID'=>1,'Email'=>'accounting@synthetic.invalid','IsActive'=>1]);
    fixture($db,'users',['UsersID'=>20,'RoleID'=>2,'Email'=>'one@synthetic.invalid','IsActive'=>1]);
    fixture($db,'users',['UsersID'=>21,'RoleID'=>2,'Email'=>'two@synthetic.invalid','IsActive'=>1]);
    fixture($db,'users',['UsersID'=>22,'RoleID'=>2,'Email'=>'foreign@synthetic.invalid','IsActive'=>1]);
    fixture($db,'users',['UsersID'=>30,'RoleID'=>1,'Email'=>'lead@synthetic.invalid','IsActive'=>1]);
    fixture($db,'status',['StatusID'=>1,'Name'=>'Finalized']);
    fixture($db,'clientcompany',['CompanyID'=>1,'Name'=>'Synthetic Client','PayrollType'=>'B']);fixture($db,'clientcompany',['CompanyID'=>2,'Name'=>'Foreign Client','PayrollType'=>'B']);
    fixture($db,'deployment_sites',['SiteID'=>1,'CompanyID'=>1,'Name'=>'Site One','Address'=>'Cagayan de Oro City, Misamis Oriental']);fixture($db,'deployment_sites',['SiteID'=>2,'CompanyID'=>2,'Name'=>'Other Site','Address'=>'Iligan City']);
    $start='2026-09-26';$end='2026-10-10';$rows=[];
    for($i=1;$i<=3;$i++) {
        fixture($db,'employee',['EmployeeID'=>$i,'EmployeeNumber'=>'TEST'.$i,'Email'=>['one','two','foreign'][$i-1].'@synthetic.invalid','FirstName'=>'Employee','LastName'=>(string)$i,'IsActive'=>1]);
        fixture($db,'deployment',['DeploymentID'=>$i,'EmployeeID'=>$i,'CompanyID'=>$i===3?2:1,'SiteID'=>$i===3?2:1,'Location'=>'Site One','StartDate'=>'2026-01-01','EndDate'=>null]);
        fixture($db,'dtr_submissions',['DTR_SubmissionID'=>$i,'EmployeeID'=>$i,'DeploymentID'=>$i,'SiteID'=>$i===3?2:1,'SiteSupervisorID'=>30,'CutoffStartDate'=>$start,'CutoffEndDate'=>$end,'ReviewedBy'=>10,'ReviewDate'=>'2026-10-01']);
        fixture($db,'payroll',['Payroll_ID'=>$i,'EmployeeID'=>$i,'DTR_SubmissionID'=>$i,'PayPeriodStart'=>$start,'PayPeriodEnd'=>$end,'StatusID'=>1,'BasicSalary'=>1000,'Allowances'=>100,'GrossPay'=>1100,'TotalDeductions'=>100,'SSSDeduction'=>50,'PhilHealthDeduction'=>30,'PagIBIGDeduction'=>20,'NetPay'=>1000]);
        if($i===3)continue;
        $rows[]=['employee_db_id'=>$i,'employee_id'=>'TEST'.$i,'employee_name'=>'Employee '.$i,'designation'=>'Worker','area_site'=>'Site One','dtr_submission_id'=>$i,'site_id'=>1,'daily_rate'=>500,'days_worked'=>2,'basic_total'=>1000,'allowances'=>100,'gross_salary'=>1000,'gross_payroll'=>1100,'total_ee_deductions'=>100,'other_ee_deductions'=>0,'net_salary'=>1000,'sss_ee'=>50,'philhealth_ee'=>30,'pagibig_ee'=>20,'total_er_dues'=>50,'thirteenth_month_accrual'=>80,'admin_overhead_pct'=>10,'vat_pct'=>12,'admin_overhead_amount'=>123,'vat_on_overhead'=>14.76,'subtotal_labor_cost'=>1230,'grand_total_invoice'=>1367.76,'sil_accrual'=>0];
    }
    $code=client_billing_run_code($start,$end,1,30,1);
    fixture($db,'payroll_runs',['PayrollRunID'=>1,'RunCode'=>$code,'BillingRefNo'=>client_billing_billing_ref($code),'CompanyID'=>1,'SiteID'=>1,'SiteSupervisorID'=>30,'PeriodStart'=>$start,'PeriodEnd'=>$end,'CutoffLabel'=>'Sep 26 – Oct 10, 2026','CurrentStatus'=>'Payroll Computed','Status'=>'Draft']);
    $run=$db->query('SELECT * FROM payroll_runs WHERE PayrollRunID=1')->fetch_assoc();

    $db->query('UPDATE payroll SET TotalDaysWorked=4,BasicSalary=2000,GrossPay=2000,Allowances=104.17,HolidayPay=0,TotalDeductions=587.50,NetPay=1412.50 WHERE Payroll_ID=1');
    $db->query('UPDATE payroll SET TotalDaysWorked=7,BasicSalary=4900,GrossPay=5775.04,Allowances=145.83,HolidayPay=0,OvertimePay=875.04,OvertimeHours=8,TotalDeductions=777.50,NetPay=4997.54 WHERE Payroll_ID=2');
    $db->query('UPDATE employee SET daily_rate=99999,hourly_rate=9999,allowance=50000 WHERE EmployeeID IN(1,2)');
    $before=$db->query('SELECT * FROM payroll ORDER BY Payroll_ID')->fetch_all(MYSQLI_ASSOC);
    $export=client_billing_build_export($db,$code);
    check((float)$export['totals']['gross_payroll']===7775.04 && (float)$export['totals']['net_salary']===6410.04,'legacy stored totals retained despite changed current salaries and allowance');
    $db->begin_transaction();$export=billing_prepare_snapshot($db,$export);$db->commit();
    check((int)$db->query('SELECT COUNT(*) FROM payroll_run_members')->fetch_row()[0]===2,'legacy billing snapshots freeze both finalized employees');
    $book=client_billing_render_spreadsheet($export);$sheet=$book->getSheet(0);
    check((float)$sheet->getCell('AD6')->getCalculatedValue()===2000.0 && (float)$sheet->getCell('AM6')->getCalculatedValue()===1412.5,'legacy payroll allowance is not added twice');
    check(abs((float)$sheet->getCell('AD7')->getCalculatedValue()-5775.04)<0.005 && abs((float)$sheet->getCell('AM7')->getCalculatedValue()-4997.54)<0.005,'legacy aggregate overtime and deductions reconcile');
    check(str_contains($sheet->getComment('AA7')->getText()->getPlainText(),'875.04'),'unarchived itemization is disclosed');
    check($before===$db->query('SELECT * FROM payroll ORDER BY Payroll_ID')->fetch_all(MYSQLI_ASSOC),'legacy export never overwrites payroll history');$book->disconnectWorksheets();
    $db->query('UPDATE dtr_submissions SET ReviewDate=NULL WHERE SiteID=1');
    rejected(fn()=>client_billing_build_export($db,$code),'missing verified DTRs produce an explicit error');
    echo implode("\n",$results)."\nAll legacy recovery checks passed.\n";
}finally{if(preg_match('/^codex_billing_test_[a-f0-9]{10}$/',$name))$db->query('DROP DATABASE `'.$name.'`');}
