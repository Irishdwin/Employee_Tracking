<?php
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/../../Api/Db_connection/db_connect.php';
require __DIR__.'/../../Api/Helpers/overtime_workflow.php';
$testDb='codex_ot_'.bin2hex(random_bytes(6));$conn->query("CREATE DATABASE `$testDb`");register_shutdown_function(function()use($conn,$testDb){$conn->query("DROP DATABASE IF EXISTS `$testDb`");});
foreach(['overtime','attendance','dtr_submissions','notifications','deployment','deployment_sites','employee','users','roles','status','clientcompany'] as $t){$conn->query("CREATE TABLE `$testDb`.`$t` LIKE `onazul_hris_db`.`$t`");}
$conn->select_db($testDb);
$conn->query("INSERT INTO roles(RolesID,RoleName) VALUES(1,'TeamLead'),(2,'Employee')");
$conn->query("INSERT INTO status(StatusID,Name) VALUES(1,'Pending'),(2,'Active'),(3,'Completed'),(4,'Approved'),(5,'Rejected'),(6,'Accepted')");
$conn->query("INSERT INTO users(UsersID,RoleID,FullName,Email,Password,IsActive) VALUES(11,1,'Lead One','lead1@example.invalid','x',1),(12,1,'Lead Two','lead2@example.invalid','x',1),(21,2,'Employee One','emp1@example.invalid','x',1),(22,2,'Employee Two','emp2@example.invalid','x',1),(23,2,'Employee Three','emp3@example.invalid','x',1)");
$conn->query("INSERT INTO employee(EmployeeID,FirstName,LastName,Email,IsActive) VALUES(31,'Employee','One','emp1@example.invalid',1),(32,'Employee','Two','emp2@example.invalid',1),(33,'Employee','Three','emp3@example.invalid',1)");
$conn->query("INSERT INTO clientcompany(CompanyID,Name,IsActive) VALUES(41,'Company One',1),(42,'Company Two',1)");
$conn->query("INSERT INTO deployment_sites(SiteID,CompanyID,Name,TeamLeadID,IsActive) VALUES(51,41,'Site One',11,1),(52,42,'Site Two',11,1),(53,42,'Outside Site',12,1)");
$conn->query("INSERT INTO deployment(DeploymentID,EmployeeID,CompanyID,SiteID,SiteSuperVisorID,StartDate,StatusID) VALUES(61,31,41,51,12,CURDATE()-INTERVAL 1 DAY,2),(62,32,42,52,12,CURDATE()-INTERVAL 1 DAY,2),(63,33,42,53,11,CURDATE()-INTERVAL 1 DAY,2)");
function check($condition,$message){if(!$condition)throw new RuntimeException($message);echo "PASS $message\n";}
function blocked(callable $fn,string $message){try{$fn();throw new RuntimeException('Not blocked: '.$message);}catch(DomainException $e){echo "PASS $message (HTTP ".$e->getCode().")\n";}}
$date=date('Y-m-d',strtotime('+1 day'));$input=['employee_ids'=>[31,32],'date'=>$date,'ot_start'=>'18:00','ot_end'=>'20:00','remarks'=>'Inventory support'];
$r=otw_create($conn,11,$input);check(count($r['created'])===2,'multi-site create saves two rows despite different legacy supervisors');$a=$r['created'][0]['overtime_id'];$b=$r['created'][1]['overtime_id'];check(count(otw_query($conn,'SELECT * FROM notifications'))===2,'each employee notified after successful save');
blocked(fn()=>otw_create($conn,11,$input),'duplicate session blocked');blocked(fn()=>otw_create($conn,11,$input+[]+['x'=>1]),'overlap blocked');
$bad=$input;$bad['employee_ids']=[33];blocked(fn()=>otw_create($conn,11,$bad),'outside assigned site blocked even legacy supervisor matches');
$bad['employee_ids']=[31,33];$bad['ot_start']='20:00';$bad['ot_end']='21:00';$before=count(otw_query($conn,'SELECT * FROM notifications'));blocked(fn()=>otw_create($conn,11,$bad),'mixed-authority batch rolled back');check(count(otw_query($conn,'SELECT * FROM notifications'))===$before,'failed batch sends no notification');
blocked(fn()=>otw_respond($conn,22,$a,'accept',''),'employee IDOR blocked');blocked(fn()=>otw_respond($conn,21,$a,'reject','  '),'empty rejection blocked');
otw_respond($conn,21,$a,'accept','');$row=otw_list($conn,21)[0];check($row['StatusName']==='Accepted'&&$row['EmployeeRespondedAt'],'accept stores status and timestamp');
blocked(fn()=>otw_respond($conn,21,$a,'accept',''),'double accept blocked');blocked(fn()=>otw_respond($conn,21,$a,'reject','Changed mind'),'reject after acceptance blocked');
otw_respond($conn,22,$b,'reject','Unavailable due to a family appointment');$row=otw_list($conn,22)[0];check($row['EmployeeRejectionReason']==='Unavailable due to a family appointment','rejection reason stored');check(count(otw_query($conn,"SELECT * FROM notifications WHERE UserID=11 AND NotificationType='overtime_response'"))===2,'Team Lead receives acceptance and rejection notices');
blocked(fn()=>otw_respond($conn,22,$b,'accept',''),'accept after rejection blocked');
$site=['deployment_id'=>61];blocked(fn()=>otw_clock($conn,21,$a,'clock_in',$site,$date.' 17:49:00','test.jpg',[]),'early OT time-in blocked');
blocked(fn()=>otw_clock($conn,22,$b,'clock_in',['deployment_id'=>62],$date.' 18:00:00','test.jpg',[]),'rejected OT cannot clock in');
blocked(fn()=>otw_clock($conn,21,$a,'clock_in',['deployment_id'=>62],$date.' 18:00:00','test.jpg',[]),'wrong deployment clock-in blocked');
otw_clock($conn,21,$a,'clock_in',$site,$date.' 18:15:00','test.jpg',[]);check(otw_list($conn,21)[0]['StatusName']==='Active','OT time-in becomes Active');
blocked(fn()=>otw_clock($conn,21,$a,'clock_in',$site,$date.' 18:20:00','test.jpg',[]),'duplicate time-in blocked');
otw_clock($conn,21,$a,'clock_out',$site,$date.' 20:05:00','test.jpg',[]);$row=otw_list($conn,21)[0];check($row['StatusName']==='Completed'&&(float)$row['OvertimeHours']===1.75,'time-out stores 1.75 actual hours capped to schedule');
check(count(otw_query($conn,'SELECT * FROM attendance'))===0,'OT does not duplicate regular attendance');
blocked(fn()=>otw_manage($conn,12,['action'=>'set_status','overtime_id'=>$a,'status'=>'Approved']),'unauthorized payroll approval blocked');
otw_manage($conn,11,['action'=>'set_status','overtime_id'=>$a,'status'=>'Approved']);check(otw_list($conn,21)[0]['StatusName']==='Approved','completed OT can be approved for existing payroll engine');
blocked(fn()=>otw_manage($conn,11,['action'=>'set_status','overtime_id'=>$a,'status'=>'Active']),'approved OT cannot reactivate');
blocked(fn()=>otw_range('2026-02-30','18:00','20:00'),'invalid date blocked');blocked(fn()=>otw_range($date,'25:00','27:00'),'invalid time blocked');
require __DIR__.'/../../Api/Helpers/employee_payroll_helper.php';
$pay=payroll_fetch_approved_overtime($conn,31,$date,$date,100,[],61);check($pay['hours']===1.75&&$pay['pay']===218.75,'existing payroll helper uses actual approved hours and existing multiplier');
$_SESSION=['overtime_csrf'=>'fixture'];blocked(fn()=>otw_check_csrf([]),'missing CSRF blocked');blocked(fn()=>otw_check_csrf(['csrf_token'=>'bad']),'invalid CSRF blocked');
blocked(fn()=>otw_manage($conn,11,['action'=>'update','overtime_id'=>$a,'ot_start'=>'19:00','ot_end'=>'20:00']),'approved schedule cannot be changed');
$late=otw_create($conn,11,['employee_ids'=>[31],'date'=>date('Y-m-d',strtotime('+2 days')),'ot_start'=>'18:00','ot_end'=>'20:00']);$lateId=$late['created'][0]['overtime_id'];
otw_query($conn,'UPDATE overtime SET OvertimeDate=? WHERE OvertimeID=?','si',[date('Y-m-d',strtotime('-1 day')),$lateId]);blocked(fn()=>otw_respond($conn,21,$lateId,'accept',''),'expired request cannot be accepted');
$conn->query("UPDATE users SET IsActive=0 WHERE UsersID=11");blocked(fn()=>otw_create($conn,11,$input),'inactive lead blocked');
echo "All backend tests passed; disposable database removed automatically.\n";
