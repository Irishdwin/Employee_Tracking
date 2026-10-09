<?php
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/../../Api/Common/session_init.php';
require __DIR__.'/../../Api/Db_connection/db_connect.php';
$testDb=trim(file_get_contents(__DIR__.'/test_db.txt'));
if(!preg_match('/^codex_ot_ui_[a-f0-9]{12}$/',$testDb))exit;
$conn->select_db($testDb);
$input=json_decode(stream_get_contents(STDIN),true);$role=$input['role'];
$_SESSION=['user_id'=>$role==='TeamLead'?11:21,'role'=>$role,'last_activity'=>time(),'overtime_csrf'=>'test-token'];
$_SERVER['REQUEST_METHOD']=$input['method'];$_POST=$input['body']??[];$_GET=$input['query']??[];
register_shutdown_function(function(){file_put_contents(__DIR__.'/last_http_status.txt',(string)(http_response_code()?:200));});
if($input['endpoint']==='clock')require __DIR__.'/../../Api/Employee/clock_in_out_geofence.php';
elseif($input['endpoint']==='roster')require __DIR__.'/../../Api/TeamLead/get_overtime_roster.php';
elseif($input['endpoint']==='save')require __DIR__.'/../../Api/TeamLead/save_overtime.php';
else{
 // POST endpoints parse php://input; CLI uses validated service and the same responses.
 require __DIR__.'/../../Api/Helpers/overtime_workflow.php';
 try{if($_SERVER['REQUEST_METHOD']==='GET'){echo json_encode(['success'=>true,'requests'=>otw_list($conn,21),'counts'=>['pending'=>(int)$conn->query("SELECT COUNT(*) FROM overtime WHERE EmployeeResponse='Pending'")->fetch_row()[0],'accepted'=>(int)$conn->query("SELECT COUNT(*) FROM overtime WHERE EmployeeResponse='Accepted'")->fetch_row()[0],'active'=>0],'csrf_token'=>'test-token']);}
 else{otw_check_csrf($_POST);otw_respond($conn,21,(int)$_POST['overtime_id'],$_POST['action'],$_POST['reason']??'');echo json_encode(['success'=>true,'message'=>'Overtime '.$_POST['action'].' saved.']);}}
 catch(DomainException $e){http_response_code($e->getCode());echo json_encode(['success'=>false,'message'=>$e->getMessage()]);}
 }
