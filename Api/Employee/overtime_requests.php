<?php
declare(strict_types=1);
require_once __DIR__.'/../Common/session_init.php';
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/../Db_connection/db_connect.php';
require_once __DIR__.'/../Helpers/overtime_workflow.php';
try{
 if(!isset($_SESSION['user_id']))throw new DomainException('Unauthorized.',401);$uid=(int)$_SESSION['user_id'];
 if($_SERVER['REQUEST_METHOD']==='GET'){
 $eid=otw_employee($conn,$uid);$now=date('Y-m-d H:i:s');otw_auto_close($conn,$now,$eid);$site=work_site_resolve_for_user($conn,$uid);
 $context=$site?otw_punch_context($conn,$eid,(int)$site['deployment_id'],'clock_in',$now):['kind'=>'unassigned','can_in'=>false,'can_out'=>false];
 $rows=otw_list($conn,$uid);$counts=['pending'=>0,'accepted'=>0,'active'=>0];
 foreach($rows as $r){if($r['EmployeeResponse']==='Pending'&&strtotime($r['OvertimeDate'].' '.$r['OTEndTime'])>time())$counts['pending']++;if($r['StatusName']==='Accepted'&&strtotime($r['OvertimeDate'].' '.$r['OTEndTime'])>time())$counts['accepted']++;if($r['StatusName']==='Active')$counts['active']++;}
 echo json_encode(['success'=>true,'requests'=>$rows,'counts'=>$counts,'csrf_token'=>otw_csrf(),'attendance_context'=>$context,'server_now'=>$now]);
 }elseif($_SERVER['REQUEST_METHOD']==='POST'){
 $body=json_decode(file_get_contents('php://input'),true);if(!is_array($body))throw new DomainException('Invalid request.',422);otw_check_csrf($body);
 otw_respond($conn,$uid,(int)($body['overtime_id']??0),otw_text($body,'action'),otw_text($body,'reason'));echo json_encode(['success'=>true,'message'=>($body['action']==='accept'?'Overtime accepted. Use the existing Clock In / Clock Out controls in Smart Calendar.':'Overtime rejected. Your Team Lead has been notified.')]);
 }else throw new DomainException('Method not allowed.',405);
}catch(DomainException $e){http_response_code($e->getCode()?:422);echo json_encode(['success'=>false,'message'=>$e->getMessage()]);}
catch(Throwable $e){error_log('employee_overtime: '.$e->getMessage());http_response_code(500);echo json_encode(['success'=>false,'message'=>'Could not process overtime. Please retry.']);}
