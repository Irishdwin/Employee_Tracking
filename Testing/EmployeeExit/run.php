<?php
/** Isolated synthetic schema; no production business records copied or changed. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
date_default_timezone_set('Asia/Manila');session_start();
require_once __DIR__.'/../../Api/Helpers/employee_exit_workflow.php';
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
$db=new mysqli('127.0.0.1','root','');$db->set_charset('utf8mb4');$db->query("SET time_zone='+08:00'");
$name='codex_exit_test_'.bin2hex(random_bytes(6));$results=[];
function check(bool $ok,string $label): void {global $results;if(!$ok)throw new RuntimeException('FAIL: '.$label);$results[]=$label;}
function reject(callable $fn,string $label): void {try{$fn();}catch(Throwable $e){check($e instanceof InvalidArgumentException||$e instanceof RuntimeException,$label);return;}check(false,$label);}
function fixture(mysqli $db,string $table,array $values): int {
 foreach($db->query('SHOW COLUMNS FROM `'.$table.'`')->fetch_all(MYSQLI_ASSOC) as $c){
  if(array_key_exists($c['Field'],$values)||$c['Null']==='YES'||$c['Default']!==null||str_contains($c['Extra'],'auto_increment'))continue;
  $type=strtolower($c['Type']);$values[$c['Field']]=preg_match('/int|decimal|float|double/',$type)?1:(str_starts_with($type,'date')?'2026-10-01':(str_starts_with($type,'enum')?explode("'",$type)[1]:'Synthetic'));
 }
 $s=$db->prepare('INSERT INTO `'.$table.'` (`'.implode('`,`',array_keys($values)).'`) VALUES('.implode(',',array_fill(0,count($values),'?')).')');$a=array_values($values);$s->bind_param(str_repeat('s',count($a)),...$a);$s->execute();return (int)$db->insert_id;
}
function actor(mysqli $db,int $uid,string $role): array {$_SESSION=['user_id'=>$uid,'role'=>$role,'full_name'=>'Synthetic '.$role];return exit_actor($db,$uid,[$role]);}
function action(mysqli $db,int $uid,string $role,string $action,int $id,array $extra=[]): int {return exit_transition($db,actor($db,$uid,$role),array_merge(['action'=>$action,'resignation_id'=>$id,'remarks'=>'Synthetic reviewed evidence'],$extra));}
function status(mysqli $db,int $id): array {return exit_rows($db,'SELECT e.IsActive,s.Name FROM employee e JOIN status s ON s.StatusID=e.StatusID WHERE EmployeeID=?',[$id])[0];}
$protected=['employee','users','resignationrequest','deployment','attendance','daily_time_records','payroll','notifications'];$before=[];
foreach($protected as $t)$before[$t]=$db->query('CHECKSUM TABLE onazul_hris_db.`'.$t.'`')->fetch_assoc()['Checksum'];
try{
 $db->query('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4');$db->select_db($name);
 foreach(['users','roles','employee','clientcompany','deployment','deployment_sites','resignationrequest','status','notifications','system_logs','auth_rate_limits','payroll','payroll_adjustments','payroll_status_history','payroll_run_members','holidays','holiday_deployment_sites','team_schedule_entries','attendance','daily_time_records','leaves'] as $t)$db->query('CREATE TABLE `'.$t.'` LIKE onazul_hris_db.`'.$t.'`');
 $db->multi_query(file_get_contents(__DIR__.'/../../sql/employee_exit_workflow_migration.sql'));do{if($r=$db->store_result())$r->free();}while($db->more_results()&&$db->next_result());
 foreach(['HR','Admin','Accounting','TeamLead','Employee'] as $i=>$role)fixture($db,'roles',['RolesID'=>$i+1,'RoleName'=>$role]);
 foreach([[10,1],[11,2],[12,3],[13,4],[14,4],[20,5],[21,5],[22,5]] as [$uid,$rid])fixture($db,'users',['UsersID'=>$uid,'RoleID'=>$rid,'Email'=>'user'.$uid.'@synthetic.invalid','IsActive'=>1,'FullName'=>'Synthetic '.$uid]);
 fixture($db,'status',['StatusID'=>1,'Name'=>'Active']);fixture($db,'status',['StatusID'=>2,'Name'=>'Approved']);
 fixture($db,'clientcompany',['CompanyID'=>1,'Name'=>'Synthetic company','IsActive'=>1]);
 foreach([1,2,3] as $sid)fixture($db,'deployment_sites',['SiteID'=>$sid,'CompanyID'=>1,'TeamLeadID'=>$sid===3?14:13,'IsActive'=>1,'Name'=>'Synthetic site '.$sid]);
 foreach([1,2,3] as $eid){fixture($db,'employee',['EmployeeID'=>$eid,'EmployeeNumber'=>'EXIT'.$eid,'Email'=>'user'.($eid+19).'@synthetic.invalid','IsActive'=>1,'StatusID'=>1,'HiredDate'=>'2026-09-01','FirstName'=>'Synthetic','LastName'=>(string)$eid]);fixture($db,'deployment',['DeploymentID'=>$eid,'EmployeeID'=>$eid,'CompanyID'=>1,'SiteID'=>$eid,'StartDate'=>'2026-09-01','EndDate'=>null,'StatusID'=>1,'AllowDTRSubmission'=>1,'WorkingDays'=>'Mon,Tue,Fri','TimeIn'=>'08:00:00','TimeOut'=>'17:00:00']);}
 fixture($db,'payroll',['Payroll_ID'=>1,'EmployeeID'=>1,'StatusID'=>1,'NetPay'=>1234]);fixture($db,'payroll',['Payroll_ID'=>2,'EmployeeID'=>3,'StatusID'=>1,'NetPay'=>4321]);
 $today=date('Y-m-d');
 reject(fn()=>action($db,20,'Employee','register',0,['employee_id'=>1,'received_date'=>$today]),'employee cannot register online resignation');
 $id=action($db,10,'HR','register',0,['employee_id'=>1,'received_date'=>$today]);
 check(status($db,1)['IsActive']==1,'physical registration keeps account active');
 check(exit_list($db,actor($db,12,'Accounting'))===[],'Accounting only sees clearance-eligible requests');
 reject(fn()=>action($db,10,'HR','register',0,['employee_id'=>1,'received_date'=>$today]),'duplicate open request blocked');
 check(count(exit_list($db,actor($db,20,'Employee')))==1,'employee sees own request');check(count(exit_list($db,actor($db,21,'Employee')))==0,'other employee cannot view request');
 check(count(exit_list($db,actor($db,13,'TeamLead')))==1,'assigned Team Lead sees clearance');check(count(exit_list($db,actor($db,14,'TeamLead')))==0,'foreign Team Lead cannot view');
 reject(fn()=>action($db,10,'HR','finalize',$id,['last_working_date'=>$today]),'pending request cannot finalize');
 action($db,10,'HR','return_request',$id);action($db,10,'HR','resubmit',$id);action($db,10,'HR','approve',$id);action($db,10,'HR','issue_clearance',$id,['clearance_reference'=>'CLEAR-1']);
 reject(fn()=>action($db,14,'TeamLead','tl_clear',$id),'foreign Team Lead cannot approve');reject(fn()=>action($db,10,'HR','tl_clear',$id),'HR cannot impersonate Team Lead approval');
 action($db,13,'TeamLead','tl_return',$id);action($db,10,'HR','reissue',$id,['clearance_reference'=>'CLEAR-2']);action($db,13,'TeamLead','tl_clear',$id);
 reject(fn()=>action($db,10,'HR','verify_clearance',$id,['accepted_date'=>$today]),'HR must confirm physical returned clearance');
 action($db,10,'HR','verify_clearance',$id,['accepted_date'=>$today,'physical_received'=>'1']);
 check(empty(exit_list($db,actor($db,10,'HR'),$id)[0]['workflow']['target_date']),'acceptance does not invent a calendar');
 action($db,12,'Accounting','set_target',$id,['target_date'=>'2026-11-30']);check(str_contains(exit_list($db,actor($db,10,'HR'),$id)[0]['workflow']['calendar_policy'],'Manual'),'manual target explicitly labels unconfirmed policy');
 reject(fn()=>action($db,12,'Accounting','set_target',$id,['working_days'=>'Mon,Tue,Wed,Thu,Fri,Sat','calendar_confirmed'=>'1']),'Accounting cannot confirm agency weekday policy');
 fixture($db,'holidays',['HolidayID'=>1,'HolidayDate'=>'2026-10-12','HolidayType'=>'Regular Holiday','IsNationwide'=>1,'HolidayName'=>'Synthetic holiday']);
 action($db,10,'HR','set_target',$id,['working_days'=>'Mon,Tue,Wed,Thu,Fri,Sat','calendar_confirmed'=>'1']);
 $n=count(exit_rows($db,'SELECT * FROM notifications'));reject(fn()=>action($db,10,'HR','set_target',$id,['working_days'=>'Mon,Tue,Wed,Thu,Fri,Sat','calendar_confirmed'=>'1']),'unchanged target does not repeat transition');check(count(exit_rows($db,'SELECT * FROM notifications'))===$n,'unchanged target sends no duplicate notification');
 check(exit_target($db,'2026-10-09',['Mon','Tue','Wed','Thu','Fri','Sat'])==='2026-11-14','30 confirmed working days includes Saturdays and excludes holiday');
 action($db,12,'Accounting','start_pay',$id);
 reject(fn()=>action($db,12,'Accounting','pay_ready',$id,['components'=>'[]','components_confirmed'=>'1']),'empty final-pay components blocked');
 reject(fn()=>action($db,12,'Accounting','pay_ready',$id,['components'=>json_encode([['type'=>'credit','description'=>'Unpaid wage','amount'=>'100','reference'=>'PAY-2','payroll_id'=>2]]),'components_confirmed'=>'1']),'foreign payroll reference blocked');
 $components=[['type'=>'credit','description'=>'Reviewed unpaid wage','amount'=>'1000.50','reference'=>'PAY-1','payroll_id'=>1],['type'=>'deduction','description'=>'Reviewed deduction','amount'=>'10.25','reference'=>'Authorized policy']];
 action($db,12,'Accounting','pay_ready',$id,['components'=>json_encode($components),'components_confirmed'=>'1']);
 $row=exit_list($db,actor($db,12,'Accounting'),$id)[0];check((float)$row['FinalPayAmount']===990.25,'final pay sums only documented reviewed components');check(count($row['payroll_history'])===1,'payroll history scoped to employee');
 $lead=exit_list($db,actor($db,13,'TeamLead'),$id)[0];check(!isset($lead['FinalPayAmount'],$lead['workflow']['components']),'Team Lead does not receive settlement amounts');check(!str_contains(json_encode($lead['history']),'1000.50'),'financial history not leaked to Team Lead');
 reject(fn()=>action($db,12,'Accounting','release_pay',$id,['released_date'=>$today,'payment_reference'=>'TX-1']),'preparation does not equal actual release');
 $db->query("CREATE TRIGGER fail_exit_notice BEFORE INSERT ON notifications FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic notification failure'");
 reject(fn()=>action($db,12,'Accounting','release_pay',$id,['released_date'=>$today,'payment_reference'=>'TX-1','payment_confirmed'=>'1']),'notification failure rolls back payment release');
 check(exit_list($db,actor($db,12,'Accounting'),$id)[0]['workflow']['stage']==='Final Pay Ready','failed release preserves previous stage');$db->query('DROP TRIGGER fail_exit_notice');
 action($db,12,'Accounting','release_pay',$id,['released_date'=>$today,'payment_reference'=>'TX-1','payment_confirmed'=>'1']);
 reject(fn()=>action($db,12,'Accounting','release_pay',$id,['released_date'=>$today,'payment_reference'=>'TX-1','payment_confirmed'=>'1']),'duplicate payment release blocked');
 reject(fn()=>action($db,10,'HR','finalize',$id,['last_working_date'=>$today]),'payment without signed quitclaim cannot finalize');
 action($db,12,'Accounting','quitclaim_prepare',$id,['quitclaim_reference'=>'QUIT-1']);
 check(empty(exit_list($db,actor($db,12,'Accounting'),$id)[0]['workflow']['quitclaim_signed_at']),'preparing quitclaim does not fabricate signature');
 reject(fn()=>action($db,12,'Accounting','quitclaim_sign',$id,['signed_date'=>$today]),'actual signature acknowledgement required');
 action($db,12,'Accounting','quitclaim_sign',$id,['signed_date'=>$today,'signature_confirmed'=>'1']);reject(fn()=>action($db,12,'Accounting','quitclaim_sign',$id,['signed_date'=>$today,'signature_confirmed'=>'1']),'duplicate signature blocked');
 check(status($db,1)['IsActive']==1,'all intermediate steps keep employee active');
 $auditCount=count(exit_list($db,actor($db,10,'HR'),$id)[0]['history']);check($auditCount>=14,'approval and return history retained');
 action($db,11,'Admin','finalize',$id,['last_working_date'=>$today]);$s=status($db,1);check($s['Name']==='Resigned'&&$s['IsActive']==0,'finalization records Resigned and inactive employee');
 check(exit_rows($db,'SELECT IsActive FROM users WHERE UsersID=20')[0]['IsActive']==0,'matching employee login disabled');reject(fn()=>exit_actor($db,20,['Employee']),'inactive actor rejected');
 require_once __DIR__.'/../../Api/Helpers/auth_password_protection.php';payroll_query($db,'UPDATE users SET Password=? WHERE UsersID=20','s',[password_hash('SyntheticPassword9!',PASSWORD_DEFAULT)]);check(!auth_password_login($db,'user20@synthetic.invalid','SyntheticPassword9!')['success'],'existing password login rejects resigned account even with correct credentials');
 $dep=exit_rows($db,'SELECT EndDate,AllowDTRSubmission FROM deployment WHERE EmployeeID=1')[0];check($dep['EndDate']===$today&&!$dep['AllowDTRSubmission'],'deployment closed and DTR submission disabled');check(count(exit_rows($db,'SELECT * FROM payroll WHERE EmployeeID=1'))===1,'payroll history preserved');
 reject(fn()=>action($db,11,'Admin','finalize',$id,['last_working_date'=>$today]),'duplicate resignation finalization blocked');
 // Multi-site ownership and reviewed absence evidence across rest days.
 check(exit_tl_owns($db,13,2,2),'second assigned site included in Team Lead scope');
 $db->query("UPDATE deployment SET EndDate='2026-10-01' WHERE EmployeeID=1");check(exit_tl_owns($db,13,1,1),'assigned Team Lead retains pinned clearance history after deployment ends');
 foreach(['2026-10-02','2026-10-05','2026-10-06'] as $date){fixture($db,'daily_time_records',['employee_id'=>2,'date'=>$date,'status'=>'Absent','time_in'=>null,'time_out'=>null,'team_lead_user_id'=>13]);fixture($db,'attendance',['EmployeeID'=>2,'Date'=>$date,'attendance_status'=>'Absent','TimeIn'=>null,'TimeOut'=>null,'ApprovedBy'=>10,'ApprovedDate'=>'2026-10-07','StatusID'=>1]);}
 check(exit_awol_dates($db,2,'2026-10-02')===[],'one scheduled absence does not qualify');check(exit_awol_dates($db,2,'2026-10-05')===[],'two scheduled absences do not qualify');check(count(exit_awol_dates($db,2,'2026-10-06'))===3,'Friday Monday Tuesday qualify across rest days');
 $db->query("UPDATE deployment SET WorkingDays='' WHERE EmployeeID=2");check(exit_awol_dates($db,2,$today)===[],'unknown duty calendar does not assume Monday Friday');$db->query("UPDATE deployment SET WorkingDays='Mon,Tue,Fri' WHERE EmployeeID=2");
 fixture($db,'holidays',['HolidayID'=>2,'HolidayDate'=>'2026-10-05','HolidayType'=>'Special Non-working Day','IsNationwide'=>1,'HolidayName'=>'Synthetic nonworking holiday']);check(exit_awol_dates($db,2,$today)===[],'nonworking holiday does not create absent duty');$db->query('DELETE FROM holidays WHERE HolidayID=2');
 $leave=fixture($db,'leaves',['EmployeeID'=>2,'StartDate'=>'2026-10-05','EndDate'=>'2026-10-05','StatusID'=>2]);check(exit_awol_dates($db,2,$today)===[],'approved leave breaks unjustified streak');$db->query('DELETE FROM leaves WHERE LeaveID='.$leave);
 $db->query("UPDATE attendance SET ApprovedDate=NULL WHERE EmployeeID=2 AND Date='2026-10-05'");check(exit_awol_dates($db,2,$today)===[],'unreviewed attendance cannot qualify');$db->query("UPDATE attendance SET ApprovedDate='2026-10-07' WHERE EmployeeID=2");
 $db->query("UPDATE daily_time_records SET status='Clocked In',time_in='08:00:00' WHERE employee_id=2 AND date='2026-10-05'");check(exit_awol_dates($db,2,$today)===[],'incomplete clocked-in record cannot qualify');$db->query("UPDATE daily_time_records SET status='Absent',time_in=NULL WHERE employee_id=2");
 reject(fn()=>exit_awol_scan($db,actor($db,13,'TeamLead'),2),'Team Lead cannot open HR case');
 $case=exit_awol_scan($db,actor($db,10,'HR'),2);check($case!==null,'three reviewed absences create review case');$n=count(exit_rows($db,'SELECT * FROM notifications'));check(exit_awol_scan($db,actor($db,10,'HR'),2)===$case&&count(exit_rows($db,'SELECT * FROM notifications'))===$n,'duplicate episode scan does not duplicate case or notifications');
 check(count(exit_rows($db,"SELECT * FROM notifications WHERE NotificationType='employee_exit' AND UserID=14 AND RelatedRecord=?",[$case]))===0,'foreign site Team Lead receives no AWOL alert');
 reject(fn()=>exit_awol_review($db,actor($db,13,'TeamLead'),['case_id'=>$case,'action'=>'confirm_awol','remarks'=>'Synthetic','explanations_reviewed'=>'1']),'Team Lead cannot confirm AWOL');
 exit_awol_review($db,actor($db,10,'HR'),['case_id'=>$case,'action'=>'confirm_awol','remarks'=>'Explanation reviewed','explanations_reviewed'=>'1']);$s=status($db,2);check($s['Name']==='AWOL'&&$s['IsActive']==1,'HR confirms AWOL without resignation or deactivation');check(exit_rows($db,'SELECT IsActive FROM users WHERE UsersID=21')[0]['IsActive']==1,'AWOL employee login remains active');
 $db->query("UPDATE attendance SET attendance_status='Present',TimeIn='08:00:00',TimeOut='17:00:00' WHERE EmployeeID=2 AND Date='2026-10-05'");exit_awol_review($db,actor($db,10,'HR'),['case_id'=>$case,'action'=>'reevaluate_awol','remarks'=>'Corrected attendance verified']);check(status($db,2)['Name']==='Active','audited attendance correction restores prior status');check(count(exit_rows($db,"SELECT * FROM exit_workflow_history WHERE RecordType='awol' AND RecordID=?",[$case]))===3,'AWOL decision history retained');
 require_once __DIR__.'/http_checks.php';exit_http_checks($db,$name);
 foreach($protected as $t)check($db->query('CHECKSUM TABLE onazul_hris_db.`'.$t.'`')->fetch_assoc()['Checksum']===$before[$t],'production '.$t.' unchanged');
 file_put_contents(__DIR__.'/results.json',json_encode(['passed'=>count($results),'tests'=>$results,'database'=>$name,'production_business_records_unchanged'=>true],JSON_PRETTY_PRINT));echo count($results)." checks passed\n";
}finally{if(str_starts_with($name,'codex_exit_test_'))$db->query('DROP DATABASE IF EXISTS `'.$name.'`');}
