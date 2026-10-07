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
function check(bool $ok,string $test): void {global $results;if(!$ok)throw new RuntimeException('FAIL: '.$test);$results[]=$test;file_put_contents(__DIR__.'/progress.json',json_encode($results,JSON_PRETTY_PRINT));}
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
    $db->begin_transaction();$snapshot=billing_prepare_snapshot($db,['rows'=>$rows,'run'=>$run,'client_name'=>'Synthetic Client']);$db->commit();
    payroll_query($db,'INSERT INTO payroll_billing_exports (ExportID,PayrollRunID,ExportToken,BaselineHash,SnapshotJson,FilePath,CreatedBy) VALUES(1,1,?,?,?,?,10)','ssss',[str_repeat('a',64),hash('sha256',billing_json($snapshot)),billing_json($snapshot),'synthetic.xlsx']);
    $token=str_repeat('b',64);$preview=['changes'=>[['employee_id'=>1,'component'=>'allowances','new'=>150]]];
    payroll_query($db,'INSERT INTO payroll_billing_revisions (RevisionID,PayrollRunID,ExportID,FileHash,PreviewToken,PreviewJson,FilePath,CreatedBy) VALUES(1,1,1,?,?,?,?,10)','ssss',[str_repeat('c',64),$token,billing_json($preview),'synthetic.xlsx']);
    $before=$db->query('SELECT * FROM payroll WHERE Payroll_ID=1')->fetch_assoc();
    $db->query("CREATE TRIGGER fail_billing_notice BEFORE INSERT ON notifications FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic notification failure'; END");
    rejected(fn()=>billing_confirm_revision($db,$token,'Notification failure test',10),'notification failure aborts import');
    check($before===$db->query('SELECT * FROM payroll WHERE Payroll_ID=1')->fetch_assoc(),'notification failure restores original payroll');
    check((int)$db->query('SELECT COUNT(*) FROM payroll_adjustments')->fetch_row()[0]===0 && (int)$db->query('SELECT COUNT(*) FROM payroll_audit_log')->fetch_row()[0]===0,'notification failure restores adjustments and audit records');
    check($db->query('SELECT ConfirmedAt FROM payroll_billing_revisions WHERE RevisionID=1')->fetch_row()[0]===null,'failed import remains unconfirmed');
    $db->query('DROP TRIGGER fail_billing_notice');billing_confirm_revision($db,$token,'Retry after delivery fixed',10);
    check((float)$db->query('SELECT NetPay FROM payroll WHERE Payroll_ID=1')->fetch_row()[0]===1050.0 && (int)$db->query("SELECT COUNT(*) FROM notifications WHERE UserID=20 AND NotificationType='payroll_billing_updated'")->fetch_row()[0]===1,'successful retry updates payroll and notification together');
    echo json_encode(['success'=>true,'tests'=>$results],JSON_PRETTY_PRINT).PHP_EOL;
} catch(Throwable $e) {$failed=true;fwrite(STDERR,$e->getMessage().PHP_EOL);}
finally {if(preg_match('/^codex_billing_test_[a-f0-9]{10}$/',$name))$db->query('DROP DATABASE `'.$name.'`');}
if(isset($failed))exit(1);
