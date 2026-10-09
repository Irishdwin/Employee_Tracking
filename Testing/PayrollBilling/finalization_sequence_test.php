<?php
/** Synthetic database only. No production employee/payroll data is copied or changed. */
declare(strict_types=1);
if(PHP_SAPI!=='cli') {http_response_code(404);exit;}
require_once __DIR__.'/../../vendor/autoload.php';
require_once __DIR__.'/../../Api/Helpers/client_billing_helper.php';
require_once __DIR__.'/../../Api/Helpers/billing_revision_helper.php';
require_once __DIR__.'/../../Api/Helpers/employee_calendar_holidays.php';
require_once __DIR__.'/../../Api/Helpers/leave_pay_notice.php';
require_once __DIR__.'/../../Api/Helpers/payroll_finalization.php';
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
    foreach(['users','roles','employee','clientcompany','deployment','deployment_sites','dtr_submissions','payroll','status','notifications','payroll_adjustments','payroll_audit_log','payroll_runs','payroll_status_history','payroll_group_status_history','holidays','payroll_run_members','payroll_billing_exports','payroll_billing_revisions','payroll_billing_adjustment_sources','holiday_deployment_sites','add_back_requests'] as $table)$db->query('CREATE TABLE `'.$table.'` LIKE onazul_hris_db.`'.$table.'`');
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

    $db->query('DELETE FROM payroll');
    rejected(fn()=>payroll_finalize_reviewed($db,10,30,$start,$end,1,1,true),'finalize before compute blocked');
    check($db->query('SELECT COUNT(*) FROM payroll')->fetch_row()[0]==0,'no payslip before finalize');
    fixture($db,'payroll_status_history',['PayrollRunID'=>1,'Status'=>'Payroll Computed','Remarks'=>'Computed from DTR submission batch; export_after_id=0','UpdatedBy'=>10]);
    rejected(fn()=>payroll_finalize_reviewed($db,10,30,$start,$end,1,1,true),'finalize before export blocked');
    $snapshot=['run'=>$run,'rows'=>$rows];
    fixture($db,'payroll_billing_exports',['ExportID'=>1,'PayrollRunID'=>1,'ExportToken'=>str_repeat('a',64),'BaselineHash'=>hash('sha256',billing_json($snapshot)),'SnapshotJson'=>billing_json($snapshot),'FilePath'=>'synthetic.xlsx','CreatedBy'=>10]);
    rejected(fn()=>payroll_finalize_reviewed($db,10,30,$start,$end,1,1,false),'review confirmation required');
    rejected(fn()=>payroll_finalize_reviewed($db,10,30,$start,$end,1,2,true),'wrong or stale export blocked');
    rejected(fn()=>payroll_finalize_reviewed($db,10,30,$start,$end,2,1,true),'wrong site blocked');
    $db->query('UPDATE dtr_submissions SET ReviewedBy=NULL WHERE EmployeeID=2');
    rejected(fn()=>payroll_finalize_reviewed($db,10,30,$start,$end,1,1,true),'unverified DTR blocks finalization');
    $db->query('UPDATE dtr_submissions SET ReviewedBy=10 WHERE EmployeeID=2');
    $final=payroll_finalize_reviewed($db,10,30,$start,$end,1,1,true);
    check(count($final)===2,'both reviewed employees finalized atomically');
    $pay=$db->query('SELECT * FROM payroll ORDER BY EmployeeID')->fetch_all(MYSQLI_ASSOC);
    check((float)$pay[0]['NetPay']===1000.0 && (float)$pay[0]['GrossPay']===1100.0 && (float)$pay[0]['TotalDeductions']===100.0,'payslip exactly matches reviewed billing amounts');
    check((int)$pay[0]['PayslipGenerated']===1,'payslip published after finalization');
    check($db->query('SELECT COUNT(*) FROM payroll_run_members')->fetch_row()[0]==2,'reviewed itemized snapshot retained');
    check($db->query("SELECT COUNT(*) FROM notifications WHERE NotificationType='payroll_finalized'")->fetch_row()[0]==2,'employees notified only after finalization');
    payroll_finalize_reviewed($db,10,30,$start,$end,1,1,true);
    check($db->query('SELECT COUNT(*) FROM payroll')->fetch_row()[0]==2,'repeat finalization does not duplicate payroll');
    check($db->query('SELECT COUNT(*) FROM notifications')->fetch_row()[0]==2,'repeat finalization does not duplicate notifications');
    fixture($db,'payroll_status_history',['PayrollRunID'=>1,'Status'=>'Payroll Computed','Remarks'=>'Computed from DTR submission batch; export_after_id=1','UpdatedBy'=>10]);
    check(!payroll_batch_review($db,30,$start,$end,1)['can_finalize'],'recompute requires a new export even within the same second');
    $completed=payroll_batch_review($db,30,$start,$end,1);check($completed['finalized'] && !$completed['can_finalize'] && !$completed['can_compute'],'completed modal shows published payslips and disables compute/finalize');
    $db->query('UPDATE dtr_submissions SET SiteSupervisorID=31 WHERE SiteID=2');$completed=payroll_batch_review($db,30,$start,$end,0);check($completed['finalized'],'unscoped legacy modal resolves a single canonical site');
    payroll_status_require_finalized($db,[['EmployeeID'=>1,'DTR_SubmissionID'=>1]],$start,$end);check(true,'finalized payroll satisfies release prerequisite');
    rejected(fn()=>payroll_status_require_finalized($db,[['EmployeeID'=>3,'DTR_SubmissionID'=>3]],$start,$end),'release blocked without finalized employee payroll');
    echo implode("\n",$results)."\nAll reviewed-finalization checks passed.\n";
}finally{$db->query('DROP DATABASE IF EXISTS `'.$name.'`');}
