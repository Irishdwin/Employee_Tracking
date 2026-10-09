<?php
declare(strict_types=1);
require_once __DIR__.'/work_site_geofence.php';
require_once __DIR__.'/../Common/NotificationHelper.php';
function otw_query(mysqli $db,string $sql,string $types='',array $args=[]): array {
 $q=$db->prepare($sql);if($types!=='')$q->bind_param($types,...$args);$q->execute();$r=$q->get_result();$out=$r?$r->fetch_all(MYSQLI_ASSOC):[];$q->close();return $out;
}
function otw_authorize(mysqli $db,int $uid,array $roles): void {
 $row=otw_query($db,'SELECT r.RoleName FROM users u JOIN roles r ON r.RolesID=u.RoleID WHERE u.UsersID=? AND u.IsActive=1','i',[$uid])[0]??[];
 if(!in_array($row['RoleName']??'', $roles,true))throw new DomainException('Forbidden.',403);
}
function otw_employee(mysqli $db,int $uid): int {
 otw_authorize($db,$uid,['Employee']);$r=otw_query($db,'SELECT e.EmployeeID FROM employee e JOIN users u ON u.Email=e.Email WHERE u.UsersID=? AND e.IsActive=1','i',[$uid])[0]??[];
 if(!$r)throw new DomainException('Active employee account not found.',403);return (int)$r['EmployeeID'];
}
function otw_status(mysqli $db,string $name): int {
 $r=otw_query($db,'SELECT StatusID FROM status WHERE Name=? LIMIT 1','s',[$name])[0]??[];
 if(!$r)throw new RuntimeException('Overtime status is not configured.');return (int)$r['StatusID'];
}
function otw_row(mysqli $db,int $id): array {
 $r=otw_query($db,'SELECT ot.*,st.Name StatusName FROM overtime ot LEFT JOIN status st ON st.StatusID=ot.StatusID WHERE ot.OvertimeID=? FOR UPDATE','i',[$id])[0]??[];
 if(!$r)throw new DomainException('Overtime request not found.',404);return $r;
}
function otw_lock_employee(mysqli $db,int $eid): void {otw_query($db,'SELECT EmployeeID FROM employee WHERE EmployeeID=? FOR UPDATE','i',[$eid]);}
function otw_open_day(mysqli $db,int $eid,string $date): void {
 if(otw_query($db,'SELECT DTR_SubmissionID FROM dtr_submissions WHERE EmployeeID=? AND ? BETWEEN CutoffStartDate AND CutoffEndDate LIMIT 1','is',[$eid,$date]))throw new DomainException('This date belongs to a submitted DTR. Use Add Back for omitted duty.',409);
}
function otw_text(array $input,string $key,string $default=''): string {
 if(!array_key_exists($key,$input))return $default;
 if(!is_string($input[$key]))throw new DomainException('Invalid overtime field: '.$key,422);
 return trim($input[$key]);
}
function otw_range(string $date,string $start,string $end): float {
 $d=DateTimeImmutable::createFromFormat('!Y-m-d',$date);if(!$d||$d->format('Y-m-d')!==$date)throw new DomainException('Choose a valid overtime date.',422);
 foreach([$start,$end] as $t)if(!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/',$t))throw new DomainException('Enter valid overtime start and end times.',422);
 $h=(strtotime("$date $end")-strtotime("$date $start"))/3600;
 if($h<=0||$h>12)throw new DomainException('Overtime end must follow start, with a maximum of 12 hours.',422);return round($h,2);
}
function otw_notify(mysqli $db,int $uid,string $type,string $title,string $message,int $id): void {
 if(!insert_system_notification($db,$uid,$type,$title,$message,'overtime',$id))throw new RuntimeException('Could not save overtime notification.');
}
function otw_create(mysqli $db,int $uid,array $input): array {
 otw_authorize($db,$uid,['TeamLead','Team Lead']);
 $ids=$input['employee_ids']??[];if(!is_array($ids)||!$ids||count($ids)>200)throw new DomainException('Select between 1 and 200 employees.',422);
 foreach($ids as $id)if(!(is_int($id)||is_string($id))||!ctype_digit((string)$id)||(int)$id<=0)throw new DomainException('Invalid employee selection.',422);
 $ids=array_values(array_unique(array_map('intval',$ids)));sort($ids);
 $date=otw_text($input,'date');$start=otw_text($input,'ot_start');$end=otw_text($input,'ot_end');$hours=otw_range($date,$start,$end);
 $remarks=otw_text($input,'remarks');if(mb_strlen($remarks)>2000)throw new DomainException('Remarks must be at most 2,000 characters.',422);
 if(strtotime("$date $end")<=time())throw new DomainException('Choose an overtime session that has not ended.',422);
 $db->begin_transaction();try{
 $created=[];$pending=otw_status($db,'Pending');$lead=otw_query($db,'SELECT FullName FROM users WHERE UsersID=?','i',[$uid])[0]['FullName'];
 foreach($ids as $eid){otw_lock_employee($db,$eid);$dep=site_owned_employee($db,$uid,$eid);if(!$dep)throw new DomainException('One or more selected employees are outside your assigned sites.',403);
 otw_open_day($db,$eid,$date);
 if(otw_query($db,'SELECT OvertimeID FROM overtime WHERE EmployeeID=? AND OvertimeDate=? AND OTStartTime<? AND OTEndTime>? LIMIT 1','isss',[$eid,$date,$end,$start]))throw new DomainException('An overlapping overtime session already exists. Refresh the roster.',409);
 $user=otw_query($db,"SELECT u.UsersID FROM users u JOIN employee e ON e.Email=u.Email JOIN roles r ON r.RolesID=u.RoleID WHERE e.EmployeeID=? AND u.IsActive=1 AND r.RoleName='Employee'",'i',[$eid])[0]??[];
 if(!$user)throw new DomainException('The selected employee has no active Employee account.',422);
 $att=otw_query($db,'SELECT AttendanceID FROM attendance WHERE EmployeeID=? AND Date=? LIMIT 1','is',[$eid,$date])[0]['AttendanceID']??null;
 otw_query($db,"INSERT INTO overtime(EmployeeID,AttendanceID,DeploymentID,OvertimeDate,OTStartTime,OTEndTime,RegularHours,OvertimeHours,OvertimeType,RequestedBy,StatusID,Remarks,EmployeeResponseRequired,EmployeeResponse) VALUES(?,?,?,?,?,?,8,?,'Regular',?,?,?,1,'Pending')",'iiisssdiis',[$eid,$att,(int)$dep['DeploymentID'],$date,$start,$end,$hours,$uid,$pending,$remarks]);
 $id=(int)$db->insert_id;$created[]=['employee_id'=>$eid,'overtime_id'=>$id];
 otw_notify($db,(int)$user['UsersID'],'overtime_request','Overtime Request',"$lead scheduled overtime on ".date('M d, Y',strtotime($date)).' from '.date('h:i A',strtotime($start)).' to '.date('h:i A',strtotime($end)).'. Open Overtime to accept or reject.'.($remarks?" Reason: $remarks":''),$id);
 }
 $db->commit();return ['created'=>$created,'message'=>count($created).' overtime request(s) saved. Employees have been notified.'];
 }catch(Throwable $e){$db->rollback();throw $e;}
}
function otw_ownership(mysqli $db,array $row,int $eid): array {
 if((int)$row['EmployeeID']!==$eid)throw new DomainException('This overtime request does not belong to you.',403);
 $dep=site_owned_employee($db,(int)$row['RequestedBy'],$eid);
 if(!$dep||(int)$dep['DeploymentID']!==(int)$row['DeploymentID'])throw new DomainException('The overtime deployment is no longer valid.',409);return $dep;
}
function otw_respond(mysqli $db,int $uid,int $id,string $action,string $reason): void {
 $eid=otw_employee($db,$uid);if(!in_array($action,['accept','reject'],true))throw new DomainException('Choose accept or reject.',422);
 $reason=trim($reason);if($action==='reject'&&($reason===''||mb_strlen($reason)>2000))throw new DomainException('A rejection reason of 1–2,000 characters is required.',422);
 $db->begin_transaction();try{otw_lock_employee($db,$eid);$row=otw_row($db,$id);otw_ownership($db,$row,$eid);otw_open_day($db,$eid,$row['OvertimeDate']);
 if(!(int)$row['EmployeeResponseRequired']||$row['EmployeeResponse']!=='Pending'||$row['StatusName']!=='Pending')throw new DomainException('This request has already been answered or is no longer pending.',409);
 if(strtotime($row['OvertimeDate'].' '.$row['OTEndTime'])<=time())throw new DomainException('This overtime request has expired.',409);
 $response=$action==='accept'?'Accepted':'Rejected';$status=otw_status($db,$response);
 otw_query($db,'UPDATE overtime SET EmployeeResponse=?,EmployeeRespondedAt=NOW(),EmployeeRejectionReason=?,StatusID=? WHERE OvertimeID=?','ssii',[$response,$action==='reject'?$reason:null,$status,$id]);
 $name=otw_query($db,'SELECT FullName FROM users WHERE UsersID=?','i',[$uid])[0]['FullName'];
 otw_notify($db,(int)$row['RequestedBy'],'overtime_response','Overtime '.$response,"$name ".strtolower($response).' overtime on '.$row['OvertimeDate'].($action==='reject'?". Reason: $reason":'.'),$id);
 $db->commit();}catch(Throwable $e){$db->rollback();throw $e;}
}
function otw_manage(mysqli $db,int $uid,array $input): void {
 otw_authorize($db,$uid,['TeamLead','Team Lead']);$id=(int)($input['overtime_id']??0);
 // Lock employee before overtime consistently with response/clock operations.
 $eid=(int)(otw_query($db,'SELECT EmployeeID FROM overtime WHERE OvertimeID=?','i',[$id])[0]['EmployeeID']??0);
 $db->begin_transaction();try{otw_lock_employee($db,$eid);$r=otw_row($db,$id);$dep=site_owned_employee($db,$uid,$eid);
 if(!$dep||(int)$dep['DeploymentID']!==(int)$r['DeploymentID'])throw new DomainException('You do not manage this overtime deployment.',403);
 otw_open_day($db,$eid,$r['OvertimeDate']);
 if(($input['action']??'')==='update'){
 if($r['StatusName']!=='Pending'||((int)$r['EmployeeResponseRequired']&&$r['EmployeeResponse']!=='Pending'))throw new DomainException('Only unanswered pending sessions can be edited.',409);
 $start=otw_text($input,'ot_start',$r['OTStartTime']);$end=otw_text($input,'ot_end',$r['OTEndTime']);$hours=otw_range($r['OvertimeDate'],$start,$end);$remarks=otw_text($input,'remarks',(string)$r['Remarks']);
 if(mb_strlen($remarks)>2000||strtotime($r['OvertimeDate'].' '.$end)<=time())throw new DomainException('Use a valid upcoming session and remarks of at most 2,000 characters.',422);
 if(otw_query($db,'SELECT OvertimeID FROM overtime WHERE EmployeeID=? AND OvertimeDate=? AND OTStartTime<? AND OTEndTime>? AND OvertimeID<>? LIMIT 1','isssi',[$eid,$r['OvertimeDate'],$end,$start,$id]))throw new DomainException('The revised time overlaps another session.',409);
 otw_query($db,'UPDATE overtime SET OTStartTime=?,OTEndTime=?,OvertimeHours=?,Remarks=? WHERE OvertimeID=?','ssdsi',[$start,$end,$hours,$remarks,$id]);
 $user=otw_query($db,'SELECT u.UsersID FROM users u JOIN employee e ON e.Email=u.Email WHERE e.EmployeeID=? AND u.IsActive=1','i',[$eid])[0]??[];
 if($user)otw_notify($db,(int)$user['UsersID'],'overtime_updated','Overtime Request Updated','Your pending overtime schedule changed. Review it in Overtime.',$id);
 }else{
 $next=(string)($input['status']??'');$allowed=['Pending'=>['Active'],'Active'=>['Completed'],'Completed'=>['Approved','Rejected']];
 if((int)$r['EmployeeResponseRequired'])$allowed=['Completed'=>['Approved','Rejected']];
 if(!in_array($next,$allowed[$r['StatusName']]??[],true))throw new DomainException('This overtime status transition is not allowed. Employee acceptance and actual time-in/out are required for new requests.',409);
 $status=otw_status($db,$next);otw_query($db,'UPDATE overtime SET StatusID=?,ApprovedBy=?,ApprovalDate=NOW() WHERE OvertimeID=?','iii',[$status,$uid,$id]);
 }
 $db->commit();}catch(Throwable $e){$db->rollback();throw $e;}
}
function otw_clock(mysqli $db,int $uid,int $id,string $action,array $site,string $now,string $photo,array $evidence,bool $inTransaction=false): void {
 if(!in_array($action,['clock_in','clock_out'],true))throw new DomainException('Invalid clock action.',422);
 $eid=otw_employee($db,$uid);if(!$inTransaction)$db->begin_transaction();try{otw_lock_employee($db,$eid);$r=otw_row($db,$id);otw_ownership($db,$r,$eid);otw_open_day($db,$eid,$r['OvertimeDate']);
 if((int)$site['deployment_id']!==(int)$r['DeploymentID'])throw new DomainException('Overtime is assigned to a different deployment.',403);
 if(!(int)$r['EmployeeResponseRequired']||$r['EmployeeResponse']!=='Accepted')throw new DomainException('Accept this overtime request before time-in.',409);
 $start=$r['OvertimeDate'].' '.$r['OTStartTime'];$end=$r['OvertimeDate'].' '.$r['OTEndTime'];$json=json_encode($evidence+['photo'=>$photo],JSON_THROW_ON_ERROR);
 if($action==='clock_in'){
 if($r['StatusName']!=='Accepted'||$r['ActualTimeIn']||$now<date('Y-m-d H:i:s',strtotime($start)-600)||$now>$end)throw new DomainException('Overtime time-in is available only during its accepted schedule.',409);
 $regular=otw_query($db,'SELECT AttendanceID FROM attendance WHERE EmployeeID=? AND Date=? AND TimeIn IS NOT NULL AND TimeOut IS NULL LIMIT 1','is',[$eid,$r['OvertimeDate']]);
 if($regular)throw new DomainException('Clock out of your regular shift before starting overtime.',409);
 $credit=otw_credit($r,$now,$end);$status=otw_status($db,'Active');otw_query($db,'UPDATE overtime SET ActualTimeIn=?,EffectiveTimeIn=?,LateMinutes=?,ClockInEvidence=?,StatusID=? WHERE OvertimeID=?','ssisii',[$now,$credit['start'],$credit['late_minutes'],$json,$status,$id]);
 }else{
 if($r['StatusName']!=='Active'||!$r['ActualTimeIn']||$r['ActualTimeOut']||$now<=$r['ActualTimeIn'])throw new DomainException('Overtime time-out is not available for this session.',409);
 $credit=otw_credit($r,$r['ActualTimeIn'],$now);$status=otw_status($db,'Completed');
 otw_query($db,'UPDATE overtime SET ActualTimeOut=?,EffectiveTimeIn=?,EffectiveTimeOut=?,LateMinutes=?,ClockOutEvidence=?,OvertimeHours=?,StatusID=? WHERE OvertimeID=?','sssisdii',[$now,$credit['start'],$credit['end'],$credit['late_minutes'],$json,$credit['hours'],$status,$id]);
 }
 if(!$inTransaction)$db->commit();}catch(Throwable $e){if(!$inTransaction)$db->rollback();throw $e;}
}
function otw_list(mysqli $db,int $uid): array {
 $eid=otw_employee($db,$uid);
 return otw_query($db,'SELECT ot.*,st.Name StatusName,u.FullName TeamLeadName,s.Name SiteName,c.Name CompanyName FROM overtime ot LEFT JOIN status st ON st.StatusID=ot.StatusID LEFT JOIN users u ON u.UsersID=ot.RequestedBy LEFT JOIN deployment d ON d.DeploymentID=ot.DeploymentID LEFT JOIN deployment_sites s ON s.SiteID=d.SiteID LEFT JOIN clientcompany c ON c.CompanyID=d.CompanyID WHERE ot.EmployeeID=? ORDER BY ot.OvertimeDate DESC,ot.OTStartTime DESC LIMIT 200','i',[$eid]);
}
function otw_csrf(): string {if(empty($_SESSION['overtime_csrf']))$_SESSION['overtime_csrf']=bin2hex(random_bytes(32));return $_SESSION['overtime_csrf'];}
function otw_check_csrf(array $body): void {if(!is_string($body['csrf_token']??null)||!hash_equals(otw_csrf(),$body['csrf_token']))throw new DomainException('Reload overtime before submitting.',403);}

/** Client policy: ten-minute early admission, five-minute grace, approved window cap. */
function otw_credit(array $row,string $rawIn,string $rawOut): array {
 $start=strtotime($row['OvertimeDate'].' '.$row['OTStartTime']);$end=strtotime($row['OvertimeDate'].' '.$row['OTEndTime']);
 $in=strtotime($rawIn);$late=$in>$start+300?(int)ceil(($in-$start)/60):0;
 $effectiveIn=$late?max($start,$in):$start;$effectiveOut=min($end,strtotime($rawOut));
 return ['start'=>date('Y-m-d H:i:s',$effectiveIn),'end'=>date('Y-m-d H:i:s',$effectiveOut),'late_minutes'=>$late,'hours'=>round(max(0,$effectiveOut-$effectiveIn)/3600,2)];
}
/** No client overtime ID participates in attendance routing. Caller holds the employee lock when writing. */
function otw_punch_context(mysqli $db,int $eid,int $deployment,string $action,string $now): array {
 $regular=otw_query($db,'SELECT AttendanceID FROM attendance WHERE EmployeeID=? AND TimeIn IS NOT NULL AND TimeOut IS NULL AND Date>=DATE(?) - INTERVAL 1 DAY','is',[$eid,$now]);
 $rows=otw_query($db,"SELECT ot.*,s.Name StatusName FROM overtime ot JOIN status s ON s.StatusID=ot.StatusID WHERE ot.EmployeeID=? AND ot.DeploymentID=? AND ot.EmployeeResponseRequired=1 AND ot.EmployeeResponse='Accepted' AND s.Name IN ('Accepted','Active') ORDER BY ot.OvertimeDate,ot.OTStartTime",'ii',[$eid,$deployment]);
 $active=array_values(array_filter($rows,fn($r)=>$r['StatusName']==='Active'&&!$r['ActualTimeOut']));
 if(count($active)>1)throw new DomainException('Multiple active overtime sessions require HR review.',409);
 if($active)return ['kind'=>'overtime','id'=>(int)$active[0]['OvertimeID'],'row'=>$active[0],'can_in'=>false,'can_out'=>true];
 if($regular)return ['kind'=>'regular','can_in'=>false,'can_out'=>true];
 $applicable=array_values(array_filter($rows,fn($r)=>$now>=date('Y-m-d H:i:s',strtotime($r['OvertimeDate'].' '.$r['OTStartTime'])-600)&&$now<=$r['OvertimeDate'].' '.$r['OTEndTime']));
 if(count($applicable)>1)throw new DomainException('Overlapping overtime schedules require HR review.',409);
 if($applicable)return ['kind'=>'overtime','id'=>(int)$applicable[0]['OvertimeID'],'row'=>$applicable[0],'can_in'=>true,'can_out'=>false];
 $closed=otw_query($db,'SELECT AttendanceID FROM attendance WHERE EmployeeID=? AND Date=DATE(?) AND TimeOut IS NOT NULL LIMIT 1','is',[$eid,$now]);
 $upcoming=array_values(array_filter($rows,fn($r)=>$r['OvertimeDate']===substr($now,0,10)&&$r['OvertimeDate'].' '.$r['OTStartTime']>$now));
 $finished=otw_query($db,"SELECT ot.OvertimeID FROM overtime ot JOIN status s ON s.StatusID=ot.StatusID WHERE ot.EmployeeID=? AND ot.OvertimeDate=DATE(?) AND ot.EmployeeResponseRequired=1 AND ot.ActualTimeOut IS NOT NULL AND s.Name IN ('Completed','Approved') LIMIT 1",'is',[$eid,$now]);
 if($finished && !$upcoming)return ['kind'=>'closed','can_in'=>false,'can_out'=>false,'message'=>'Your overtime attendance is already closed.'];
 if($upcoming && !$closed){
  require_once __DIR__.'/employee_payroll_helper.php';
  $schedule=payroll_resolve_deployment_schedule($db,$eid,substr($now,0,10));
  if($schedule['time_out']>$schedule['time_in'] && substr($now,11)>=$schedule['time_out'])$closed=[true];
 }
 if($closed)return ['kind'=>'closed','can_in'=>false,'can_out'=>false,'message'=>$upcoming?'Overtime clock-in opens at '.date('h:i A',strtotime($upcoming[0]['OvertimeDate'].' '.$upcoming[0]['OTStartTime'])-600).'.':'Your attendance is already closed.'];
 return ['kind'=>'regular','can_in'=>true,'can_out'=>false];
}
function otw_auto_close(mysqli $db,string $now,?int $onlyEmployee=null): int {
 require_once __DIR__.'/chat_helper.php';require_once __DIR__.'/messenger_rbac.php';
 $due=otw_query($db,"SELECT ot.OvertimeID,ot.EmployeeID FROM overtime ot JOIN status s ON s.StatusID=ot.StatusID WHERE s.Name='Active' AND ot.EmployeeResponseRequired=1 AND ot.ActualTimeIn IS NOT NULL AND ot.ActualTimeOut IS NULL AND TIMESTAMP(ot.OvertimeDate,ot.OTEndTime)+INTERVAL 20 MINUTE<=?".($onlyEmployee?' AND ot.EmployeeID=?':''),'s'.($onlyEmployee?'i':''),$onlyEmployee?[$now,$onlyEmployee]:[$now]);
 $count=0;
 foreach($due as $candidate){$db->begin_transaction();try{
  otw_lock_employee($db,(int)$candidate['EmployeeID']);$row=otw_row($db,(int)$candidate['OvertimeID']);
  if($row['StatusName']!=='Active'||$row['ActualTimeOut']){$db->commit();continue;}
  otw_open_day($db,(int)$row['EmployeeID'],$row['OvertimeDate']);
  $end=$row['OvertimeDate'].' '.$row['OTEndTime'];$credit=otw_credit($row,$row['ActualTimeIn'],$end);$status=otw_status($db,'Completed');
  otw_query($db,'UPDATE overtime SET ActualTimeOut=?,EffectiveTimeIn=?,EffectiveTimeOut=?,LateMinutes=?,OvertimeHours=?,AutoClosedAt=?,ClockOutEvidence=?,StatusID=? WHERE OvertimeID=?','sssidssii',[$end,$credit['start'],$end,$credit['late_minutes'],$credit['hours'],$now,json_encode(['automatic'=>true,'processed_at'=>$now,'effective_end'=>$end]),$status,(int)$row['OvertimeID']]);
  $user=otw_query($db,'SELECT u.UsersID FROM users u JOIN employee e ON e.Email=u.Email WHERE e.EmployeeID=? AND u.IsActive=1','i',[(int)$row['EmployeeID']])[0]??null;
  if(!$user)throw new RuntimeException('Employee account unavailable for auto-timeout notification.');
  $message='You did not clock out from your approved overtime on '.$row['OvertimeDate'].'. Your overtime attendance was automatically closed at '.date('h:i A',strtotime($end)).'. Credited overtime: '.$credit['hours'].' hours. Please remember to clock out after overtime.';
  otw_notify($db,(int)$user['UsersID'],'overtime_auto_timeout','Overtime Time-Out Automatically Recorded',$message,(int)$row['OvertimeID']);
  $dep=otw_query($db,'SELECT s.TeamLeadID FROM deployment d JOIN deployment_sites s ON s.SiteID=d.SiteID WHERE d.DeploymentID=?','i',[(int)$row['DeploymentID']])[0]??[];
  $lead=messenger_user($db,(int)($dep['TeamLeadID']??0));$recipient=$lead?messenger_require_recipient($db,$lead,(int)$user['UsersID']):null;
  if(!$lead||!$recipient)throw new DomainException('Assigned Team Lead cannot message this employee.',409);
  $a=(int)$lead['user_id'];$b=(int)$user['UsersID'];$key='dm_'.min($a,$b).'_'.max($a,$b);
  $lock=otw_query($db,'SELECT GET_LOCK(?,5) locked','s',['messenger_'.$key])[0];if(!(int)$lock['locked'])throw new RuntimeException('Messenger busy.');
  try{$thread=chat_find_or_create_thread($db,'DIRECT',$key,max($a,$b),$a,[$a,$b]);messenger_require_thread($db,$lead,$thread);chat_add_message($db,$thread,$a,'Automatic overtime reminder: '.$message,true);chat_create_notification($db,$b,'messenger_message','Overtime reminder from '.$lead['full_name'],$message,'chat_thread',$thread);}finally{otw_query($db,'SELECT RELEASE_LOCK(?)','s',['messenger_'.$key]);}
  $db->commit();$count++;
 }catch(Throwable $e){$db->rollback();error_log('Overtime auto-close '.$candidate['OvertimeID'].': '.$e->getMessage());}}
 return $count;
}
