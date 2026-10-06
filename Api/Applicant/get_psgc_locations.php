<?php
require_once __DIR__.'/../Common/session_init.php';
header('Content-Type: application/json; charset=utf-8');
if(!isset($_SESSION['user_id']) || !in_array($_SESSION['role']??'',['Applicant','Employee'],true)){http_response_code(401);echo json_encode(['success'=>false,'message'=>'Login required.']);exit;}
if($_SERVER['REQUEST_METHOD']!=='GET'){http_response_code(405);echo json_encode(['success'=>false]);exit;}
require_once __DIR__.'/../Helpers/psgc_address.php';
try {
    require __DIR__.'/../Db_connection/db_connect.php';
    $level=(string)($_GET['level']??'regions');
    if(!in_array($level,['regions','provinces','cities','barangays'],true))throw new DomainException('Invalid geographic level.');
    $parent=(string)($_GET['parent']??'');
    if($level!=='regions' && !preg_match('/^\d{10}$/',$parent))throw new DomainException('Select a valid parent location.');
    $items=psgc_items($level,$parent);
    $direct=$level==='provinces' && count(psgc_items('cities',$parent))>0;
    echo json_encode(['success'=>true,'data'=>$items,'has_direct_cities'=>$direct,'version'=>psgc_metadata()['version']],JSON_UNESCAPED_UNICODE);
} catch(DomainException $e){http_response_code(400);echo json_encode(['success'=>false,'message'=>$e->getMessage()]);}
catch(Throwable $e){error_log('PSGC lookup: '.$e->getMessage());http_response_code(503);echo json_encode(['success'=>false,'message'=>'Unable to load locations. Please try again.']);}
