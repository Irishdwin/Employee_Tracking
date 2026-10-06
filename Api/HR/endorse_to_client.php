<?php
require_once __DIR__ . '/../Common/session_init.php';
header('Content-Type: application/json; charset=utf-8');
if (empty($_SESSION['user_id']) || empty($_SESSION['role'])) { http_response_code(401); echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
if (!in_array($_SESSION['role'],['Admin','HR'],true)) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Forbidden']); exit; }
require __DIR__ . '/../Db_connection/db_connect.php';
require_once __DIR__ . '/../Helpers/application_endorsement.php';
try {
    $uid=(int)$_SESSION['user_id']; ae_authorize($conn,$uid,['Admin','HR']);
    if ($_SERVER['REQUEST_METHOD']==='GET') {
        $app=ae_application($conn,(int)($_GET['application_id']??0));
        if (!ae_can_reendorse($app)) throw new DomainException('Only client-rejected applications can be reassigned.',409);
        echo json_encode(['success'=>true,'opportunities'=>ae_opportunities($conn,$app)]);
    } elseif ($_SERVER['REQUEST_METHOD']==='POST') {
        $input=json_decode(file_get_contents('php://input'),true); if (!is_array($input)) $input=$_POST;
        $attempt=ae_endorse($conn,$uid,(int)($input['application_id']??0),$input);
        echo json_encode(['success'=>true,'attempt_id'=>$attempt,'message'=>'Applicant endorsed successfully.']);
    } else throw new DomainException('Method not allowed.',405);
} catch (DomainException $e) { http_response_code($e->getCode()?:400); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
catch (Throwable $e) { error_log('Endorsement: '.$e->getMessage()); http_response_code(500); echo json_encode(['success'=>false,'message'=>'Could not save or load endorsement. Please try again.']); }
