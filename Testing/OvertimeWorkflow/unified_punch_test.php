<?php
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/../../Api/Db_connection/db_connect.php';
require __DIR__.'/../../Api/Helpers/overtime_workflow.php';
$testDb='codex_ot_'.bin2hex(random_bytes(6));$conn->query("CREATE DATABASE `$testDb`");register_shutdown_function(function()use($conn,$testDb){$conn->query("DROP DATABASE IF EXISTS `$testDb`");});
foreach(array_column($conn->query('SHOW TABLES')->fetch_all(MYSQLI_NUM),0) as $t){$conn->query("CREATE TABLE `$testDb`.`$t` LIKE `onazul_hris_db`.`$t`");}
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

$site=['deployment_id'=>61];
$cases=[['20:55:00','23:00:00',2.0,0],['21:04:00','23:00:00',2.0,0],['21:05:00','23:00:00',2.0,0],['21:06:00','23:00:00',1.9,6],['21:00:00','22:30:00',1.5,0],['21:00:00','23:10:00',2.0,0],['20:50:00','23:00:00',2.0,0]];
foreach($cases as $i=>$case){
 $date=date('Y-m-d',strtotime('+'.($i+1).' days'));
 $r=otw_create($conn,11,['employee_ids'=>[31],'date'=>$date,'ot_start'=>'21:00','ot_end'=>'23:00']);$id=$r['created'][0]['overtime_id'];otw_respond($conn,21,$id,'accept','');
 $context=otw_punch_context($conn,31,61,'clock_in',$date.' '.$case[0]);check($context['kind']==='overtime'&&$context['id']===$id,'server resolves correct accepted OT '.$i);
 otw_clock($conn,21,$id,'clock_in',$site,$date.' '.$case[0],'photo-in.jpg',[]);
 blocked(fn()=>otw_clock($conn,21,$id,'clock_in',$site,$date.' 21:30:00','duplicate.jpg',[]),'duplicate punch blocked '.$i);
 otw_clock($conn,21,$id,'clock_out',$site,$date.' '.$case[1],'photo-out.jpg',[]);
 $row=otw_row($conn,$id);check((float)$row['OvertimeHours']===$case[2]&&(int)$row['LateMinutes']===$case[3],'actual credited hours and late minutes '.$i);
 otw_manage($conn,11,['action'=>'set_status','overtime_id'=>$id,'status'=>'Approved']);
 require_once __DIR__.'/../../Api/Helpers/employee_payroll_helper.php';
 $pay=payroll_fetch_approved_overtime($conn,31,$date,$date,100,[],61);check($pay['hours']===$case[2],'production payroll uses credited OT '.$i);
 $pdo=new PDO('mysql:host=127.0.0.1;dbname='.$testDb.';charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 $period=payroll_compute_period($conn,$pdo,31,800,100,0,0,$date,$date,null,['sss'=>false,'philhealth'=>false,'pagibig'=>false],61,null,false);
 require_once __DIR__.'/../../Api/Helpers/dtr_classification.php';
 $classification=dtr_classification_from_payroll($period);
 check($classification['overtime_hours']===$case[2],'Team Lead DTR classification matches credited OT '.$i);
 check($period['ot_detail']['approved_overtime_hours']===$case[2]&&$period['earnings']['approved_overtime']===$pay['pay'],'Accounting period computation includes authoritative OT pay '.$i);
}
$date=date('Y-m-d',strtotime('+9 days'));$r=otw_create($conn,11,['employee_ids'=>[31],'date'=>$date,'ot_start'=>'21:00','ot_end'=>'23:00']);$id=$r['created'][0]['overtime_id'];otw_respond($conn,21,$id,'accept','');
blocked(fn()=>otw_clock($conn,21,$id,'clock_in',$site,$date.' 20:49:00','photo.jpg',[]),'more than ten minutes early blocked');
otw_clock($conn,21,$id,'clock_in',$site,$date.' 21:00:00','photo.jpg',[]);
check(otw_auto_close($conn,$date.' 23:19:59',31)===0,'no auto timeout before twenty minutes');
check(otw_auto_close($conn,$date.' 23:20:00',31)===1,'automatic close at twenty minutes');
$row=otw_row($conn,$id);check($row['ActualTimeOut']===$date.' 23:00:00'&&(float)$row['OvertimeHours']===2.0&&$row['AutoClosedAt'],'automatic close caps end and hours');
check(count(otw_query($conn,"SELECT * FROM notifications WHERE NotificationType='overtime_auto_timeout' AND UserID=21"))===1,'automatic close notifies employee');
check(count(otw_query($conn,'SELECT * FROM chat_messages WHERE SenderUserID=11'))===1,'existing Messenger receives assigned Team Lead reminder');
check(otw_auto_close($conn,$date.' 23:30:00',31)===0,'automatic close is idempotent');
check(count(otw_query($conn,'SELECT * FROM chat_messages WHERE SenderUserID=11'))===1,'no duplicate reminder');
check(otw_punch_context($conn,31,61,'clock_in',$date.' 23:30:00')['kind']==='closed','closed OT cannot start another punch');
check(otw_punch_context($conn,32,62,'clock_in',date('Y-m-d').' 08:00:00')['kind']==='regular','no accepted overtime routes regular attendance');
blocked(fn()=>otw_clock($conn,22,$id,'clock_out',['deployment_id'=>62],$date.' 23:00:00','photo.jpg',[]),'cross employee punch denied');
blocked(fn()=>otw_range($date,'23:00','01:00'),'overnight OT remains explicitly unsupported by existing create rules');
echo "Unified overtime service tests passed.\n";
