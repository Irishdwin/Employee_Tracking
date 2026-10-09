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
    check(count($snapshot['rows'])===2,'stable two-employee roster');
    $book=client_billing_render_spreadsheet($snapshot);$snapshot['token']=str_repeat('a',64);$snapshot['sheet_title']=$book->getSheet(0)->getTitle();$snapshot['cells']=billing_capture_cells($book,$snapshot);$snapshot['column_values']=billing_capture_values($book,$snapshot);billing_add_manifest($book,$snapshot,$snapshot['token']);
    $manifest=json_decode($book->getSheetByName('_ONAZUL_EXPORT')->getCell('A1')->getValue(),true);
    check(billing_detect_changes($book,$snapshot,$manifest)===[],'unchanged workbook has zero changes');
    $tmp=tempnam(sys_get_temp_dir(),'billing_').'.xlsx';$files[]=$tmp;(new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($tmp);billing_validate_zip($tmp);
    $loaded=(new \PhpOffice\PhpSpreadsheet\Reader\Xlsx())->load($tmp);
    check(billing_detect_changes($loaded,$snapshot,json_decode($loaded->getSheetByName('_ONAZUL_EXPORT')->getCell('A1')->getValue(),true))===[],'saved XLSX round trip unchanged');
    check(abs((float)$book->getSheet(0)->getCell('AW6')->getValue()-1367.76)<0.001,'workbook invoice includes VAT');
    $book->getSheet(0)->setCellValue('O6',200);$book->getSheet(0)->setCellValue('AK6',50);
    $changes=billing_detect_changes($book,$snapshot,$manifest);
    check(count($changes)===2 && $changes[0]['employee_id']===1,'two components affect only one employee');
    $book->getSheet(0)->setCellValue('B6','Renamed');check(count(billing_detect_changes($book,$snapshot,$manifest))===3,'billing display name can be revised without changing employee identity');$book->getSheet(0)->setCellValue('B6',$snapshot['cells']['B6']);
    $book->getSheet(0)->setCellValue('AM6','=99999');rejected(fn()=>billing_detect_changes($book,$snapshot,$manifest),'formula changes rejected');$book->getSheet(0)->setCellValue('AM6',$snapshot['cells']['AM6']);
    $book->getSheet(0)->setCellValue('O6','=1+1');rejected(fn()=>billing_detect_changes($book,$snapshot,$manifest),'editable formula rejected');$book->getSheet(0)->setCellValue('O6',200);
    $bad=$manifest;$bad['company_id']=2;rejected(fn()=>billing_detect_changes($book,$snapshot,$bad),'foreign company rejected');$bad=$manifest;$bad['site_id']=2;rejected(fn()=>billing_detect_changes($book,$snapshot,$bad),'foreign site rejected');$bad=$manifest;$bad['billing_ref']='BAD';rejected(fn()=>billing_detect_changes($book,$snapshot,$bad),'billing reference tampering rejected');
    $book->getSheet(0)->setCellValue('A900',3);$book->getSheet(0)->setCellValue('B900','Foreign');rejected(fn()=>billing_detect_changes($book,$snapshot,$manifest),'inserted foreign row rejected');$book->getSheet(0)->setCellValue('A900',null);$book->getSheet(0)->setCellValue('B900',null);
    rejected(fn()=>billing_money(-1),'negative amounts rejected');rejected(fn()=>billing_money(1.234),'precision rejected');
    payroll_query($db,'INSERT INTO payroll_billing_exports (ExportID,PayrollRunID,ExportToken,BaselineHash,SnapshotJson,FilePath,CreatedBy) VALUES(1,1,?,?,?,?,10)','ssss',[$snapshot['token'],hash('sha256',billing_json($snapshot)),billing_json($snapshot),'synthetic.xlsx']);
    $preview=['changes'=>$changes];$token=str_repeat('b',64);
    payroll_query($db,'INSERT INTO payroll_billing_revisions (RevisionID,PayrollRunID,ExportID,FileHash,PreviewToken,PreviewJson,FilePath,CreatedBy) VALUES(1,1,1,?,?,?,?,10)','ssss',[str_repeat('c',64),$token,billing_json($preview),'synthetic-import.xlsx']);
    $result=billing_confirm_revision($db,$token,'Synthetic authorized correction',10);
    check($result['changed_employees']===1,'confirmation changes only affected employee');
    $p=$db->query('SELECT GrossPay,TotalDeductions,NetPay FROM payroll WHERE Payroll_ID=1')->fetch_assoc();
    check((float)$p['GrossPay']===1200.0 && (float)$p['TotalDeductions']===150.0 && (float)$p['NetPay']===1050.0,'stored payroll reflects allowance and deduction revision');
    check((float)$db->query('SELECT NetPay FROM payroll WHERE Payroll_ID=2')->fetch_assoc()['NetPay']===1000.0,'unchanged employee untouched');
    $repeat=billing_confirm_revision($db,$token,'Replay',10);check($repeat['already_confirmed']===true,'identical confirmation is idempotent');
    check((int)$db->query('SELECT COUNT(*) n FROM payroll_adjustments')->fetch_assoc()['n']===2,'no duplicate adjustments');
    check((int)$db->query('SELECT COUNT(*) n FROM payroll_audit_log')->fetch_assoc()['n']===2,'component audit entries recorded');
    check((int)$db->query("SELECT COUNT(*) n FROM notifications WHERE UserID=20 AND NotificationType='payroll_billing_updated'")->fetch_assoc()['n']===1,'billing revision notifies changed employee once');
    check((int)$db->query("SELECT COUNT(*) n FROM notifications WHERE UserID=21 AND NotificationType='payroll_billing_updated'")->fetch_assoc()['n']===0,'unchanged employee gets no billing revision notification');
    // Every visible employee-row value is detected; full-template validation is tested above.
    $columnBook=new \PhpOffice\PhpSpreadsheet\Spreadsheet();$columnBook->getSheet(0)->setTitle($snapshot['sheet_title']);$columnSnapshot=$snapshot;
    $columnSnapshot['cells']=array_filter($snapshot['cells'],static function($address){preg_match('/([0-9]+)$/',$address,$m);return (int)$m[1]<=7;},ARRAY_FILTER_USE_KEY);
    foreach($columnSnapshot['cells'] as $address=>$value)$columnBook->getSheet(0)->setCellValue($address,$value);
    billing_add_manifest($columnBook,$columnSnapshot,$columnSnapshot['token']);
    foreach(BILLING_EDITABLE_COLUMNS as $column=>$component) {
        $previous=$columnBook->getSheet(0)->getCell($column.'7')->getValue();
        $columnBook->getSheet(0)->setCellValue($column.'7',in_array($component,BILLING_TEXT_FIELDS,true)?'Corrected label':123.45);
        $detected=billing_detect_changes($columnBook,$columnSnapshot,$manifest);
        check(in_array($component,array_column($detected,'component'),true),'import recognizes column '.$column.' / '.$component);
        $columnBook->getSheet(0)->setCellValue($column.'7',$previous);
    }
    rejected(fn()=>billing_apply_components($snapshot['rows'][0],['gross_payroll'=>100,'net_salary'=>999]),'inconsistent net payroll is rejected');
    $newToken=str_repeat('d',64);payroll_query($db,'INSERT INTO payroll_billing_revisions (PayrollRunID,ExportID,FileHash,PreviewToken,PreviewJson,FilePath,CreatedBy) VALUES(1,1,?,?,?,?,10)','ssss',[str_repeat('e',64),$newToken,billing_json($preview),'stale.xlsx']);
    rejected(fn()=>billing_confirm_revision($db,$newToken,'Stale',10),'stale preview rejected');
    $db->begin_transaction();$fresh=billing_prepare_snapshot($db,$snapshot);$db->commit();
    payroll_query($db,'INSERT INTO payroll_billing_exports (ExportID,PayrollRunID,ExportToken,BaselineHash,SnapshotJson,FilePath,CreatedBy) VALUES(2,1,?,?,?,?,10)','ssss',[str_repeat('f',64),hash('sha256',billing_json($fresh)),billing_json($fresh),'new-synthetic.xlsx']);
    $rollbackToken=str_repeat('9',64);$both=[];
    foreach($fresh['rows'] as $row)$both[]=['employee_id'=>(int)$row['employee_db_id'],'component'=>'allowances','new'=>(float)$row['allowances']+50];
    payroll_query($db,'INSERT INTO payroll_billing_revisions (PayrollRunID,ExportID,FileHash,PreviewToken,PreviewJson,FilePath,CreatedBy) VALUES(1,2,?,?,?,?,10)','ssss',[str_repeat('8',64),$rollbackToken,billing_json(['changes'=>$both]),'rollback.xlsx']);
    $db->query("CREATE TRIGGER fail_second_audit BEFORE INSERT ON payroll_audit_log FOR EACH ROW BEGIN IF NEW.Payroll_ID=2 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic rollback'; END IF; END");
    rejected(fn()=>billing_confirm_revision($db,$rollbackToken,'Rollback test',10),'multi-employee failure rolls back');
    check((float)$db->query('SELECT NetPay FROM payroll WHERE Payroll_ID=1')->fetch_assoc()['NetPay']===1050.0 && (int)$db->query('SELECT COUNT(*) n FROM payroll_adjustments')->fetch_assoc()['n']===2,'first employee and adjustments rolled back after second employee failure');
    $db->query('DROP TRIGGER fail_second_audit');
    $notice=payroll_status_update_group($db,30,1,$start,$end,'For Client Approval','Test',null,10);
    check($notice['notified_employees']===2,'exact group recipients notified');
    $repeat=payroll_status_update_group($db,30,1,$start,$end,'For Client Approval','Again',null,10);check($repeat['changed']===false,'same status produces no event');
    check((int)$db->query("SELECT COUNT(*) n FROM notifications WHERE UserID=22")->fetch_assoc()['n']===0,'foreign employee never notified');
    check(payroll_status_for_dtr($db,1)['current']['status']==='For Client Approval','current and history resolve the same group status');
    $db->query("CREATE TRIGGER fail_second_notification BEFORE INSERT ON notifications FOR EACH ROW BEGIN IF NEW.UserID=21 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic notification rollback'; END IF; END");
    rejected(fn()=>payroll_status_update_group($db,30,1,$start,$end,'Client Approved','Rollback',null,10),'notification failure rolls back status');
    check((int)$db->query('SELECT COUNT(*) n FROM payroll_group_status_history')->fetch_assoc()['n']===1 && (int)$db->query("SELECT COUNT(*) n FROM notifications WHERE NotificationType='payroll_status_updated'")->fetch_assoc()['n']===2,'status and all notifications roll back together');
    $db->query('DROP TRIGGER fail_second_notification');
    check(client_billing_update_run_status($db,$code,'Client Approved','Run status',null,10),'run-level status update succeeds');
    check(payroll_status_for_dtr($db,1)['current']['status']==='Client Approved','newer run status is visible in employee history');
    $again=payroll_status_update_group($db,30,1,$start,$end,'For Client Approval','Return',null,10);
    check($again['changed']===true && payroll_status_for_dtr($db,1)['current']['status']==='For Client Approval','group update after newer run status is not incorrectly suppressed');
    employee_leave_pay_notice($db,20);employee_leave_pay_notice($db,20);
    check((int)$db->query("SELECT COUNT(*) n FROM notifications WHERE UserID=20 AND NotificationType='leave_pay_policy'")->fetch_assoc()['n']===1,'leave pay policy notice sent once');
    fixture($db,'holidays',['HolidayID'=>1,'HolidayName'=>'Future Nationwide','HolidayDate'=>'2026-10-30','HolidayType'=>'Regular Holiday','IsNationwide'=>1]);
    fixture($db,'holidays',['HolidayID'=>2,'HolidayName'=>'Local One','HolidayDate'=>'2026-10-31','HolidayType'=>'Local/Company Holiday','IsNationwide'=>0,'Region'=>'Unknown']);holiday_scope_save($db,2,[1]);
    fixture($db,'holidays',['HolidayID'=>3,'HolidayName'=>'Other Local','HolidayDate'=>'2026-10-31','HolidayType'=>'Local/Company Holiday','IsNationwide'=>0,'Region'=>'Unknown']);holiday_scope_save($db,3,[2]);
    $calendar=employee_calendar_holidays($db,'2026-10-01','2026-10-31',1);
    check(count($calendar['2026-10-30'])===1,'future holiday appears without attendance');
    check(count($calendar['2026-10-31'])===1 && $calendar['2026-10-31'][0]['id']===2,'local holiday scope filters other sites');
    check(!isset($calendar['2026-10-29']),'ordinary dates stay ordinary');
    check((int)$db->query('SELECT COUNT(*) n FROM payroll')->fetch_assoc()['n']===3,'calendar does not create payroll');
    $_SESSION=['user_id'=>20,'payroll_csrf'=>'token'];rejected(fn()=>payroll_require_accounting($db,true),'employee cannot use accounting writes');
    $_SESSION=['user_id'=>10,'payroll_csrf'=>'token'];$_SERVER['HTTP_X_PAYROLL_CSRF']='wrong';rejected(fn()=>payroll_require_accounting($db,true),'CSRF rejected');$_SERVER['HTTP_X_PAYROLL_CSRF']='token';check(payroll_require_accounting($db,true)===10,'authorized accounting CSRF accepted');
    // Confirm a broad correction against a fresh signed export.
    $updatedRows=array_map(static fn($m)=>json_decode($m['SnapshotJson'],true),$db->query('SELECT SnapshotJson FROM payroll_run_members WHERE PayrollRunID=1 ORDER BY EmployeeID')->fetch_all(MYSQLI_ASSOC));
    $db->begin_transaction();$wide=billing_prepare_snapshot($db,['rows'=>$updatedRows,'run'=>$db->query('SELECT * FROM payroll_runs WHERE PayrollRunID=1')->fetch_assoc(),'client_name'=>'Synthetic Client']);$db->commit();
    $wide['token']=str_repeat('7',64);$wideBook=client_billing_render_spreadsheet($wide);$wide['sheet_title']=$wideBook->getSheet(0)->getTitle();$wide['cells']=billing_capture_cells($wideBook,$wide);$wide['column_values']=billing_capture_values($wideBook,$wide);billing_add_manifest($wideBook,$wide,$wide['token']);
    payroll_query($db,'INSERT INTO payroll_billing_exports (ExportID,PayrollRunID,ExportToken,BaselineHash,SnapshotJson,FilePath,CreatedBy) VALUES(3,1,?,?,?,?,10)','ssss',[$wide['token'],hash('sha256',billing_json($wide)),billing_json($wide),'wide.xlsx']);
    foreach(['B'=>'Billing Name','C'=>'Updated Designation','D'=>'Updated Area','E'=>600,'F'=>3,'K'=>120,'P'=>20,'Q'=>30,'S'=>40,'AE'=>60,'AH'=>35,'AJ'=>25,'AN'=>5,'AT'=>90,'AU'=>150] as $column=>$v)$wideBook->getSheet(0)->setCellValue($column.'6',$v);
    $wideChanges=billing_detect_changes($wideBook,$wide,json_decode($wideBook->getSheetByName('_ONAZUL_EXPORT')->getCell('A1')->getValue(),true));$wideToken=str_repeat('6',64);
    payroll_query($db,'INSERT INTO payroll_billing_revisions (PayrollRunID,ExportID,FileHash,PreviewToken,PreviewJson,FilePath,CreatedBy) VALUES(1,3,?,?,?,?,10)','ssss',[str_repeat('5',64),$wideToken,billing_json(['changes'=>$wideChanges]),'wide-import.xlsx']);
    $unchanged=$db->query('SELECT * FROM payroll WHERE Payroll_ID=2')->fetch_assoc();$dtrBefore=$db->query('CHECKSUM TABLE dtr_submissions')->fetch_assoc()['Checksum'];
    $beforeWide=$db->query('SELECT * FROM payroll WHERE Payroll_ID=1')->fetch_assoc();
    $db->query("CREATE TRIGGER fail_billing_notification BEFORE INSERT ON notifications FOR EACH ROW BEGIN IF NEW.NotificationType='payroll_billing_updated' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic billing notification failure'; END IF; END");
    rejected(fn()=>billing_confirm_revision($db,$wideToken,'Notification rollback',10),'billing notification failure aborts the revision');
    check($beforeWide===$db->query('SELECT * FROM payroll WHERE Payroll_ID=1')->fetch_assoc(),'notification failure rolls back revised payroll');
    $db->query('DROP TRIGGER fail_billing_notification');
    billing_confirm_revision($db,$wideToken,'Broad authorized billing correction',10);
    $pay=$db->query('SELECT * FROM payroll WHERE Payroll_ID=1')->fetch_assoc();
    check((float)$pay['BasicSalary']===1800.0 && (float)$pay['TotalDaysWorked']===3.0 && (float)$pay['OvertimePay']===120.0 && (float)$pay['HolidayPay']===40.0,'broad import persists basic pay days overtime and holiday pay');
    check((float)$pay['SSSDeduction']===60.0 && (float)$pay['PhilHealthDeduction']===35.0 && (float)$pay['PagIBIGDeduction']===25.0,'broad import persists statutory deduction revisions');
    check(abs((float)$pay['NetPay']-((float)$pay['GrossPay']-(float)$pay['TotalDeductions']))<0.001,'broad import payroll totals reconcile');
    $member=json_decode($db->query('SELECT SnapshotJson FROM payroll_run_members WHERE EmployeeID=1')->fetch_assoc()['SnapshotJson'],true);
    check($member['employee_name']==='Billing Name' && $member['designation']==='Updated Designation' && $member['area_site']==='Updated Area' && (float)$member['sil_accrual']===20.0 && (float)$member['addback']===30.0 && (float)$member['admin_overhead_amount']===150.0,'billing labels leave Add Back and client charge detail persist');
    $newBook=client_billing_render_spreadsheet(['rows'=>[$member],'run'=>$wide['run'],'client_name'=>'Synthetic Client']);
    check($newBook->getSheet(0)->getCell('B6')->getValue()==='Billing Name' && (float)$newBook->getSheet(0)->getCell('Q6')->getValue()===30.0 && (float)$newBook->getSheet(0)->getCell('AU6')->getValue()===150.0,'future export retains imported billing values');
    check($unchanged===$db->query('SELECT * FROM payroll WHERE Payroll_ID=2')->fetch_assoc() && $dtrBefore===$db->query('CHECKSUM TABLE dtr_submissions')->fetch_assoc()['Checksum'],'unaffected employee and original DTR remain unchanged');
    $noticeCount=(int)$db->query("SELECT COUNT(*) n FROM notifications WHERE UserID=20 AND NotificationType='payroll_billing_updated'")->fetch_assoc()['n'];billing_confirm_revision($db,$wideToken,'Replay',10);
    check($noticeCount===(int)$db->query("SELECT COUNT(*) n FROM notifications WHERE UserID=20 AND NotificationType='payroll_billing_updated'")->fetch_assoc()['n'],'replayed broad import does not duplicate employee notification');
    if(isset($GLOBALS['billing_http_test']))($GLOBALS['billing_http_test'])($db,$name,$code);
    echo json_encode(['success'=>true,'passed'=>count($results),'tests'=>$results],JSON_PRETTY_PRINT).PHP_EOL;
} catch(Throwable $e) {$failed=true;fwrite(STDERR,$e->getMessage().PHP_EOL.$e->getTraceAsString().PHP_EOL);}
finally {
    foreach($files as $path)if(str_starts_with($path,sys_get_temp_dir().DIRECTORY_SEPARATOR) && is_file($path))unlink($path);
    if(preg_match('/^codex_billing_test_[a-f0-9]{10}$/',$name))$db->query('DROP DATABASE `'.$name.'`');
}
if(isset($failed))exit(1);
