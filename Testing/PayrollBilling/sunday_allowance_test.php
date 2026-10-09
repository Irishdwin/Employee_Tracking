<?php
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/../../Api/Helpers/hire_applicant.php';
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
$db=new mysqli('127.0.0.1','root','');$name='codex_sunday_'.bin2hex(random_bytes(6));
function fixture(mysqli $db,string $table,array $values): int {
    foreach($db->query('SHOW COLUMNS FROM `'.$table.'`')->fetch_all(MYSQLI_ASSOC) as $c) {
        if(array_key_exists($c['Field'],$values) || $c['Null']==='YES' || $c['Default']!==null || str_contains($c['Extra'],'auto_increment'))continue;
        $type=strtolower($c['Type']);$values[$c['Field']]=preg_match('/int|decimal|float|double/',$type)?1:(str_starts_with($type,'date')?'2026-10-01':(str_starts_with($type,'enum')?explode("'",$type)[1]:'Synthetic'));
    }
    $fields=array_keys($values);$stmt=$db->prepare('INSERT INTO `'.$table.'` (`'.implode('`,`',$fields).'`) VALUES('.implode(',',array_fill(0,count($fields),'?')).')');$args=array_values($values);$stmt->bind_param(str_repeat('s',count($args)),...$args);$stmt->execute();return (int)$db->insert_id;
}
try{$db->query('CREATE DATABASE `'.$name.'`');$db->select_db($name);foreach($db->query('SHOW TABLES FROM onazul_hris_db')->fetch_all()as$r)$db->query('CREATE TABLE `'.$r[0].'` LIKE onazul_hris_db.`'.$r[0].'`');
$db->query('INSERT INTO roles SELECT * FROM onazul_hris_db.roles');$db->query('INSERT INTO status SELECT * FROM onazul_hris_db.status');
$role=(int)$db->query("SELECT RolesID FROM roles WHERE RoleName='Applicant'")->fetch_row()[0];
fixture($db,'users',['UsersID'=>101,'RoleID'=>$role,'FullName'=>'Synthetic Applicant','Email'=>'sex-test@synthetic.invalid','IsActive'=>1]);
fixture($db,'applicants',['ApplicantID'=>201,'UserID'=>101,'Email'=>'sex-test@synthetic.invalid','FirstName'=>'Synthetic','LastName'=>'Applicant','FullName'=>'Synthetic Applicant','Gender'=>'Female']);
fixture($db,'clientcompany',['CompanyID'=>301,'Name'=>'Synthetic Company']);
fixture($db,'deployment_sites',['SiteID'=>302,'CompanyID'=>301,'Name'=>'Synthetic Site','IsActive'=>1]);
fixture($db,'joborder',['JobOrderID'=>401,'CompanyID'=>301,'Position'=>'Synthetic Worker']);
fixture($db,'job_applications',['ApplicationID'=>501,'ApplicantID'=>201,'JobOrderID'=>401,'ClientApprovalStatus'=>'Approved','HiredDate'=>null,'Remarks'=>'Contract uploaded: synthetic test contract']);
$adminRole=(int)$db->query("SELECT RolesID FROM roles WHERE RoleName='Admin'")->fetch_row()[0];
fixture($db,'users',['UsersID'=>103,'RoleID'=>$adminRole,'FullName'=>'Synthetic Admin','Email'=>'admin@synthetic.invalid','IsActive'=>1]);
$r=hireApplicantFromApplication($db,501,103,'SSS-test','PH-test','PI-test',500,0,'300.00');if(!$r['success'])throw new RuntimeException(json_encode($r));
$employee=$db->query('SELECT gender FROM employee WHERE EmployeeID='.(int)$r['employee_id'])->fetch_assoc();if($employee['gender']!=='Female')throw new RuntimeException('Sex not carried to employee');
if($db->query('SELECT Gender FROM applicants WHERE ApplicantID=201')->fetch_row()[0]!=='Female')throw new RuntimeException('Applicant record lost sex');

require_once __DIR__.'/../../vendor/autoload.php';
require_once __DIR__.'/../../Api/Helpers/client_billing_helper.php';
require_once __DIR__.'/../../Api/Helpers/billing_revision_helper.php';
require_once __DIR__.'/../../Api/Helpers/payroll_finalization.php';
require_once __DIR__.'/../../Api/Helpers/accounting_profile_helper.php';
require_once __DIR__.'/../../Api/Accounting/Lib/client_billing_spreadsheet.php';
$checks=[];
function check(bool $ok,string $label):void {global $checks;if(!$ok)throw new RuntimeException($label);$checks[]=$label;}
function denied(callable $fn,string $label):void {try{$fn();}catch(InvalidArgumentException|RuntimeException $e){check(true,$label);return;}throw new RuntimeException($label.' was not rejected');}
$eid=(int)$r['employee_id'];
check($db->query('SELECT sunday_duty_allowance FROM employee WHERE EmployeeID='.$eid)->fetch_row()[0]==='300.00','actual conversion stores 300.00');
foreach(['-1','abc','300.001','100000000','1e2'] as $bad)denied(fn()=>employee_sunday_allowance_input($bad),'invalid allowance '.$bad);
check(employee_sunday_allowance_input('0')==='0.00','zero valid');
$accountingRole=(int)$db->query("SELECT RolesID FROM roles WHERE RoleName='Accounting'")->fetch_row()[0];
fixture($db,'users',['UsersID'=>601,'RoleID'=>$accountingRole,'FullName'=>'Juan Dela Cruz','Email'=>'accounting@synthetic.invalid','IsActive'=>1]);
fixture($db,'users',['UsersID'=>602,'RoleID'=>$accountingRole,'FullName'=>'Other Accountant','Email'=>'other@synthetic.invalid','IsActive'=>1]);
$_SESSION=['user_id'=>601,'role'=>'Accounting'];
check(payroll_require_accounting($db)===601,'server role authorization');
$token=payroll_csrf_token();$_SERVER['HTTP_X_PAYROLL_CSRF']='bad';denied(fn()=>payroll_require_accounting($db,true),'CSRF enforced');
$_SERVER['HTTP_X_PAYROLL_CSRF']=$token;check(payroll_require_accounting($db,true)===601,'valid CSRF');
accounting_profile_save($db,601,['full_name'=>'Maria Santos']);check(accounting_profile_name($db,601)==='Maria Santos','current full name updated');
denied(fn()=>accounting_profile_save($db,601,['full_name'=>'Forged','user_id'=>602]),'cross user edit denied');
check(accounting_profile_name($db,602)==='Other Accountant','other profile unchanged');
foreach(['',str_repeat('x',101)]as$bad)denied(fn()=>accounting_profile_save($db,601,['full_name'=>$bad]),'invalid name rejected');
$active=(int)$db->query("SELECT StatusID FROM status WHERE Name='Active' LIMIT 1")->fetch_row()[0];
fixture($db,'deployment',['DeploymentID'=>701,'EmployeeID'=>$eid,'CompanyID'=>301,'SiteID'=>302,'SiteSuperVisorID'=>601,'StartDate'=>'2026-01-01','EndDate'=>null,'StatusID'=>$active,'WorkingDays'=>'Mon-Sun','TimeIn'=>'08:00:00','TimeOut'=>'17:00:00','BreakDuration'=>60]);
$pdo=attendance_pdo($db);attendance_ensure_table($pdo);
$period=['2026-09-11','2026-09-25'];
$compute=fn()=>payroll_compute_period($db,$pdo,$eid,500,62.5,0,13000,$period[0],$period[1],null,['sss'=>false,'philhealth'=>false,'pagibig'=>false],701,null,false);
$none=$compute();check($none['earnings']['sunday_allowance']===0,'no actual duty no allowance');
function duty(PDO $pdo,int $eid,string $date,?string $out='17:00:00'):void {
 $stmt=$pdo->prepare('INSERT INTO daily_time_records(employee_id,date,time_in,time_out,status) VALUES(?,?,?,?,?)');$stmt->execute([$eid,$date,$date.' 08:00:00',$out?$date.' '.$out:null,$out?'Clocked Out':'Clocked In']);
}
duty($pdo,$eid,'2026-09-13');$one=$compute();check($one['earnings']['sunday_allowance']===300,'one completed Sunday');
duty($pdo,$eid,'2026-09-20');$two=$compute();check($two['earnings']['sunday_allowance']===600,'two completed Sundays');
check($compute()['earnings']['sunday_allowance']===600,'recompute idempotent');
$pdo->exec("UPDATE daily_time_records SET time_out=time_in WHERE date='2026-09-20'");check($compute()['earnings']['sunday_allowance']===300,'zero-hour Sunday excluded');
$pdo->exec("UPDATE daily_time_records SET time_out='2026-09-20 17:00:00' WHERE date='2026-09-20'");
$db->query('UPDATE employee SET sunday_duty_allowance=0 WHERE EmployeeID='.$eid);$noRate=$compute();check(abs($two['gross_pay']-$noRate['gross_pay']-600)<0.001,'gross includes allowance exactly once');
$db->query('UPDATE employee SET sunday_duty_allowance=300 WHERE EmployeeID='.$eid);
duty($pdo,$eid,'2026-09-06');check($compute()['earnings']['sunday_allowance']===600,'outside cutoff excluded');
$pdo->exec("UPDATE daily_time_records SET time_out=NULL,status='Clocked In' WHERE date='2026-09-20'");check($compute()['earnings']['sunday_allowance']===300,'incomplete Sunday excluded');
$pdo->exec("UPDATE daily_time_records SET time_out='2026-09-20 17:00:00',status='Clocked Out' WHERE date='2026-09-20'");
$db->query("UPDATE deployment SET WorkingDays='Mon-Fri' WHERE DeploymentID=701");check($compute()['earnings']['sunday_allowance']===600,'rest-day rendered duty qualifies without overwriting premiums');
$db->query("UPDATE deployment SET WorkingDays='Mon-Sun' WHERE DeploymentID=701");
fixture($db,'dtr_submissions',['DTR_SubmissionID'=>801,'EmployeeID'=>$eid,'DeploymentID'=>701,'SiteID'=>302,'SiteSupervisorID'=>601,'CutoffStartDate'=>$period[0],'CutoffEndDate'=>$period[1],'ReviewedBy'=>601,'ReviewDate'=>'2026-09-26']);
$code=client_billing_run_code($period[0],$period[1],301,601,302);
fixture($db,'payroll_runs',['PayrollRunID'=>901,'RunCode'=>$code,'BillingRefNo'=>client_billing_billing_ref($code),'CompanyID'=>301,'SiteID'=>302,'SiteSupervisorID'=>601,'PeriodStart'=>$period[0],'PeriodEnd'=>$period[1],'CurrentStatus'=>'Payroll Computed','Status'=>'Draft']);
$export=client_billing_build_export($db,$code);check(count($export['rows'])===1 && (int)$export['rows'][0]['employee_db_id']===$eid,'selected run actual roster');
$row=$export['rows'][0];
denied(fn()=>billing_apply_components($row,['sunday_ot_amount'=>0]),'import cannot remove configured Sunday allowance');check($row['sunday_allowance']===600.0,'billing consumes engine allowance');
$export['prepared_by']=accounting_profile_name($db,601);$book=client_billing_render_spreadsheet($export);$sheet=$book->getActiveSheet();
check((float)$sheet->getCell('H6')->getValue()===$row['sunday_ot_amount'],'Sunday H6 includes premium plus allowance');
check((float)$sheet->getCell('O6')->getValue()===$row['allowances'],'other allowance O6 does not duplicate Sunday');
check((float)$sheet->getCell('AD6')->getCalculatedValue()===$row['gross_payroll'],'billing total matches engine');
function signature($book):string {foreach($book->getActiveSheet()->getRowIterator()as$r)foreach($r->getCellIterator()as$c)if($c->getValue()==='PAYROLL IN-CHARGE')return $c->getColumn().($c->getRow()-1);throw new RuntimeException('Signature missing');}
$sig=signature($book);check($sheet->getCell($sig)->getValue()==='MARIA SANTOS','dynamic prepared by discovered template cell '.$sig);
accounting_profile_save($db,601,['full_name'=>'Pedro Garcia']);$export['prepared_by']=accounting_profile_name($db,601);$new=client_billing_render_spreadsheet($export);
check($new->getActiveSheet()->getCell(signature($new))->getValue()==='PEDRO GARCIA','next export current name');
check($sheet->getCell($sig)->getValue()==='MARIA SANTOS','old export remains unchanged');
$export['prepared_by']='=1+1';$formula=client_billing_render_spreadsheet($export);check($formula->getActiveSheet()->getCell(signature($formula))->getDataType()==='s','prepared by formula injection prevented');
fixture($db,'payroll_status_history',['PayrollRunID'=>901,'Status'=>'Payroll Computed','Remarks'=>'Computed from DTR submission batch; export_after_id=0','UpdatedBy'=>601]);
$export['prepared_by']='Pedro Garcia';
fixture($db,'payroll_billing_exports',['ExportID'=>1001,'PayrollRunID'=>901,'ExportToken'=>str_repeat('c',64),'BaselineHash'=>hash('sha256',billing_json($export)),'SnapshotJson'=>billing_json($export),'FilePath'=>'synthetic.xlsx','CreatedBy'=>601]);
$final=payroll_finalize_reviewed($db,601,601,$period[0],$period[1],302,1001,true);$pid=$final[0]['payroll_id'];
$pay=$db->query('SELECT * FROM payroll WHERE Payroll_ID='.$pid)->fetch_assoc();check((float)$pay['Allowances']===$row['allowances']+600,'finalized payroll persists Sunday allowance');
check(abs((float)$pay['OvertimePay']-($row['total_overtime']))<0.001,'allowance is not persisted as OT pay');
check((float)$pay['NetPay']===$row['net_salary'],'finalized net agrees');
$member=json_decode($db->query('SELECT SnapshotJson FROM payroll_run_members WHERE Payroll_ID='.$pid)->fetch_row()[0],true);check((float)$member['sunday_allowance']===600.0,'history snapshot freezes Sunday component');
$again=payroll_finalize_reviewed($db,601,601,$period[0],$period[1],302,1001,true);check($again[0]['already_finalized'],'repeat finalization no duplicate');
$export['rows']=[$member];$frozen=billing_prepare_snapshot($db,$export);check($frozen['rows'][0]['allowances']===$row['allowances'],'frozen export excludes Sunday from O');
$historyOutput=shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/employee_history_fixture.php').' '.escapeshellarg($name).' '.(int)$pid);
$history=json_decode($historyOutput,true);check(($history['success']??false) && (float)($history['payslip']['earnings']['sunday_allowance']??-1)===600.0,'actual employee history endpoint exposes Sunday allowance');
check((float)$history['payslip']['earnings']['allowance']===$row['allowances'],'history other allowance is separate');
check((float)$history['payslip']['net_pay']===$row['net_salary'],'history endpoint finalized net matches billing');
$file=tempnam(sys_get_temp_dir(),'onazul_sunday_').'.xlsx';try{(new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($file);$loaded=\PhpOffice\PhpSpreadsheet\IOFactory::load($file);check($loaded->getActiveSheet()->getCell($sig)->getValue()==='MARIA SANTOS','saved historical workbook unchanged');}finally{if(is_file($file))unlink($file);}
echo json_encode(['success'=>true,'passed'=>count($checks),'tests'=>$checks],JSON_PRETTY_PRINT);
}finally{$db->query('DROP DATABASE IF EXISTS `'.$name.'`');}
