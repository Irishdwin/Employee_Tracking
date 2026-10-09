<?php
declare(strict_types=1);
require_once __DIR__.'/../Common/session_init.php';
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/../Db_connection/db_connect.php';
require_once __DIR__.'/../Helpers/payroll_finalization.php';
try {
 if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);echo json_encode(['success'=>false,'message'=>'POST required']);exit;}
 $actor=payroll_require_accounting($conn,true);
 $body=json_decode(file_get_contents('php://input'),true);if(!is_array($body))$body=$_POST;
 $lead=(int)($body['tl_id']??0);$start=(string)($body['cutoff_start']??'');$end=(string)($body['cutoff_end']??'');
 if($lead<=0||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$start)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$end))throw new InvalidArgumentException('Choose a valid payroll batch.');
 $final=payroll_finalize_reviewed($conn,$actor,$lead,$start,$end,(int)($body['site_id']??0),(int)($body['export_id']??0),($body['billing_reviewed']??false)===true);
 echo json_encode(['success'=>true,'finalized'=>$final,'message'=>count($final).' payroll record(s) finalized. Employee payslips are now available.']);
}catch(Throwable $e){payroll_api_error($e);}
