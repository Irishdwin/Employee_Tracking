<?php
declare(strict_types=1);
require_once __DIR__.'/../Common/session_init.php';
require_once __DIR__.'/../Db_connection/db_connect.php';
require_once __DIR__.'/employee_exit_workflow.php';
header('Content-Type: application/json');header('Cache-Control: no-store');
try{
    $actor=exit_actor($conn,(int)($_SESSION['user_id']??0),EXIT_ALLOWED_ROLES);
    if(empty($_SESSION['exit_csrf']))$_SESSION['exit_csrf']=bin2hex(random_bytes(32));
    if($_SERVER['REQUEST_METHOD']==='GET'){
        $action=$_GET['action']??'list';
        if($action==='employees'){
            if(!in_array($actor['RoleName'],['HR','Admin'],true))throw new RuntimeException('HR authorization required.',403);
            $rows=exit_rows($conn,"SELECT EmployeeID,EmployeeNumber,CONCAT(FirstName,' ',LastName) EmployeeName FROM employee WHERE IsActive=1 ORDER BY LastName,FirstName");
            echo json_encode(['success'=>true,'employees'=>$rows,'csrf_token'=>$_SESSION['exit_csrf']]);exit;
        }
        $rows=exit_list($conn,$actor,(int)($_GET['id']??0));$cases=[];
        if(in_array($actor['RoleName'],['HR','Admin','TeamLead','Team Lead'],true)){
            foreach(exit_rows($conn,"SELECT c.*,CONCAT(e.FirstName,' ',e.LastName) EmployeeName,e.EmployeeNumber FROM awol_review_cases c JOIN employee e ON e.EmployeeID=c.EmployeeID ORDER BY c.CaseID DESC") as $c){
                if(in_array($actor['RoleName'],['HR','Admin'],true)||exit_tl_owns($conn,(int)$actor['UsersID'],(int)$c['EmployeeID'])){
                    $c['history']=exit_rows($conn,"SELECT h.Action,h.Details,h.CreatedAt,u.FullName Actor FROM exit_workflow_history h JOIN users u ON u.UsersID=h.ActorID WHERE h.RecordType='awol' AND h.RecordID=? ORDER BY h.HistoryID",[$c['CaseID']]);$cases[]=$c;
                }
            }
        }
        echo json_encode(['success'=>true,'resignations'=>$rows,'awol_cases'=>$cases,'role'=>$actor['RoleName'],'csrf_token'=>$_SESSION['exit_csrf']]);exit;
    }
    if($_SERVER['REQUEST_METHOD']!=='POST')throw new InvalidArgumentException('Unsupported method.');
    if(!hash_equals($_SESSION['exit_csrf'],(string)($_SERVER['HTTP_X_EXIT_CSRF']??'')))throw new RuntimeException('Reload this page before saving.',403);
    if($actor['RoleName']==='Employee')throw new RuntimeException('Submit the physical letter personally to HR. Online requests are disabled.',403);
    $input=$_POST;if(!$input)$input=json_decode(file_get_contents('php://input'),true)?:[];
    $action=$input['action']??'';
    if($action==='attach_document')$id=exit_attach_document($conn,$actor,$input,$_FILES['document']??[]);
    elseif($action==='scan_awol')$id=exit_awol_scan($conn,$actor,(int)($input['employee_id']??0));
    elseif(in_array($action,['confirm_awol','dismiss_awol','reevaluate_awol'],true)){exit_awol_review($conn,$actor,$input);$id=(int)$input['case_id'];}
    else $id=exit_transition($conn,$actor,$input);
    echo json_encode(['success'=>true,'id'=>$id,'message'=>'Workflow recorded successfully. Notifications saved for authorized dashboard recipients.']);
}catch(Throwable $e){error_log('Employee exit: '.$e->getMessage());payroll_api_error($e);}
