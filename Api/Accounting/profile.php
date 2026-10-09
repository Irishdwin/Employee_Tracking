<?php
require_once __DIR__.'/../Common/session_init.php';
require __DIR__.'/../Db_connection/db_connect.php';
require_once __DIR__.'/../Helpers/accounting_profile_helper.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
try {
 $write=$_SERVER['REQUEST_METHOD']==='POST';
 if(!$write && $_SERVER['REQUEST_METHOD']!=='GET'){http_response_code(405);exit;}
 $actor=payroll_require_accounting($conn,$write);
 $name=$write?accounting_profile_save($conn,$actor,json_decode(file_get_contents('php://input'),true,512,JSON_THROW_ON_ERROR)):accounting_profile_name($conn,$actor);
 echo json_encode(['success'=>true,'full_name'=>$name,'csrf_token'=>payroll_csrf_token()]);
} catch(Throwable $e){payroll_api_error($e);}
