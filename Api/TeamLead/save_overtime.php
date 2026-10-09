<?php
declare(strict_types=1);
require_once __DIR__.'/../Common/session_init.php';
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/../Db_connection/db_connect.php';
require_once __DIR__.'/../Helpers/overtime_workflow.php';
try{
 if($_SERVER['REQUEST_METHOD']!=='POST')throw new DomainException('POST required.',405);
 if(!isset($_SESSION['user_id']))throw new DomainException('Unauthorized.',401);
 $body=json_decode(file_get_contents('php://input'),true);if(!is_array($body))$body=$_POST;
 otw_check_csrf($body);$uid=(int)$_SESSION['user_id'];
 if(($body['action']??'')==='create')$result=otw_create($conn,$uid,$body);
 elseif(in_array($body['action']??'',['update','set_status'],true)){otw_manage($conn,$uid,$body);$result=['message'=>'Overtime updated.'];}
 else throw new DomainException('Unknown overtime action.',422);
 echo json_encode(['success'=>true]+$result);
}catch(DomainException $e){http_response_code($e->getCode()?:422);echo json_encode(['success'=>false,'message'=>$e->getMessage()]);}
catch(Throwable $e){error_log('save_overtime: '.$e->getMessage());http_response_code(500);echo json_encode(['success'=>false,'message'=>'Could not save overtime. Please retry or contact the administrator.']);}
