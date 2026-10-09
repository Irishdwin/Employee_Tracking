<?php
declare(strict_types=1);
require_once __DIR__.'/session_init.php';require_once __DIR__.'/../Db_connection/db_connect.php';
require_once __DIR__.'/../Helpers/employee_exit_workflow.php';
try{
    $actor=exit_actor($conn,(int)($_SESSION['user_id']??0),['HR','Admin','Accounting','Employee','TeamLead','Team Lead']);
    $id=(int)($_GET['id']??0);$type=(string)($_GET['type']??'');
    if(!in_array($type,['letter','clearance','quitclaim'],true)||($type==='quitclaim'&&in_array($actor['RoleName'],['TeamLead','Team Lead'],true)))throw new RuntimeException('Document unavailable.',403);
    $row=exit_rows($conn,'SELECT rr.*,e.Email FROM resignationrequest rr JOIN employee e ON e.EmployeeID=rr.EmployeeID WHERE rr.ResignationID=?',[$id])[0]??null;
    if(!$row)throw new RuntimeException('Document unavailable.',404);$w=json_decode($row['WorkflowDetails']??'',true)?:[];
    if(!exit_can_view($conn,$actor,$row,$w))throw new RuntimeException('Document unavailable.',403);
    $relative=$w['documents'][$type]['path']??($type==='quitclaim'?$row['QuitclaimDocumentPath']:($type==='clearance'?$row['ClearanceFormPath']:''));
    $path=$relative?realpath(dirname(__DIR__,2).'/'.$relative):false;$roots=[realpath(dirname(__DIR__,2).'/uploads/employee_exit'),realpath(dirname(__DIR__,2).'/uploads/quitclaims')];$inside=false;
    foreach($roots as $root)if($root&&$path&&str_starts_with(strtolower($path),strtolower($root.DIRECTORY_SEPARATOR)))$inside=true;
    if(!$inside||!is_file($path))throw new RuntimeException('Document unavailable.',404);
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($path);if(!in_array($mime,['application/pdf','image/jpeg','image/png'],true))throw new RuntimeException('Unsupported stored document.',404);
    header('Content-Type: '.$mime);header('Content-Disposition: inline; filename="'.$type.'.'.($mime==='application/pdf'?'pdf':($mime==='image/png'?'png':'jpg')).'"');
    header('X-Content-Type-Options: nosniff');header('Content-Security-Policy: sandbox');header('Cache-Control: private, no-store');readfile($path);
}catch(Throwable $e){http_response_code(in_array($e->getCode(),[403,404],true)?$e->getCode():500);echo 'Document unavailable.';}
