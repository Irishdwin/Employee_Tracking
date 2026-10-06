<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Manila');
require_once __DIR__.'/session_init.php';
header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
require_once __DIR__.'/../Db_connection/db_connect.php';
require_once __DIR__.'/../Helpers/attendance_pdo.php';
require_once __DIR__.'/../Helpers/add_back.php';
try {
    $user=(int)($_SESSION['user_id']??0); $role=(string)($_SESSION['role']??'');
    if (!$user) throw new DomainException('Unauthorized.',401);
    if (!in_array($role,['Employee','Accounting','Admin'],true)) throw new DomainException('Forbidden.',403);
    $pdo=attendance_pdo(); $emp=$role==='Employee'?evidence_employee($pdo,$user):null;
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        // Reject cross-site writes and all uploads; references are selected server-side.
        $origin=$_SERVER['HTTP_ORIGIN']??null;
        if ($origin && parse_url($origin,PHP_URL_HOST)!==explode(':',$_SERVER['HTTP_HOST']??'')[0]) throw new DomainException('Invalid request origin.',403);
        if ($_FILES) throw new DomainException('Additional uploads are not supported. Use recorded attendance evidence.',415);
        $body=json_decode(file_get_contents('php://input'),true);
        if (!is_array($body)) throw new DomainException('A JSON request is required.',400);
        if ($role==='Employee') $data=['request_id'=>add_back_submit($pdo,$conn,$user,$body)];
        else $data=add_back_review($pdo,$conn,$user,$role,(int)($body['request_id']??0),$body);
    } elseif ($_SERVER['REQUEST_METHOD']==='GET') {
        if (isset($_GET['work_date'])) {
            if (!$emp) throw new DomainException('Employee work-date context required.',403);
            $source=add_back_source($pdo,$conn,(int)$emp['EmployeeID'],add_back_date((string)$_GET['work_date']));
            $a=$source['attendance'];
            $data=['context'=>['employee_name'=>trim($emp['FirstName'].' '.$emp['LastName']),'employee_number'=>$emp['EmployeeNumber'],'company'=>$source['deployment']['CompanyName'],'site'=>$source['deployment']['Location'],'position'=>$source['deployment']['Position'],'schedule'=>$source['schedule'],'original_hours'=>$source['original_hours'],'attendance'=>$a?evidence_safe_attendance($a):null]];
        } else {
            $id=(int)($_GET['request_id']??0);
            $sql='SELECT r.*,s.Name StatusName,e.FirstName,e.LastName,e.EmployeeNumber,d.Position,d.Location,c.Name CompanyName FROM add_back_requests r JOIN status s ON s.StatusID=r.StatusID JOIN employee e ON e.EmployeeID=r.EmployeeID JOIN deployment d ON d.DeploymentID=r.DeploymentID JOIN clientcompany c ON c.CompanyID=r.CompanyID WHERE 1=1'; $args=[];
            if ($emp) { $sql.=' AND r.EmployeeID=?'; $args[]=$emp['EmployeeID']; }
            if ($id) { $sql.=' AND r.RequestID=?'; $args[]=$id; }
            $q=$pdo->prepare($sql.' ORDER BY r.RequestID DESC LIMIT 100'); $q->execute($args); $rows=$q->fetchAll();
            if ($id && !$rows) throw new DomainException('Request not found or not owned by this account.',403);
            foreach ($rows as &$r) {
                $source=json_decode($r['SourceSnapshot'],true);
                $r['source']=['attendance'=>$source['attendance']?evidence_safe_attendance($source['attendance']):null,'schedule'=>$source['schedule'],'locked_dtr'=>!empty($source['dtr_submissions'])];
                $q=$pdo->prepare('SELECT EvidenceID,EvidenceType,CapturedAt FROM add_back_evidence WHERE RequestID=?'); $q->execute([$r['RequestID']]); $r['evidence']=$q->fetchAll();
                foreach ($r['evidence'] as &$ev) $ev['url']='../../Api/Common/attendance_evidence.php?evidence_id='.(int)$ev['EvidenceID'].'&type='.$ev['EvidenceType']; unset($ev);
                if (!$emp && $id && $r['StatusName']==='Pending') {
                    try { $quote=add_back_quote($pdo,$conn,$r); $r['quote']=['amount'=>$quote['amount'],'hours'=>$quote['hours'],'rate_source'=>$quote['rates']['source']]; $r['default_target']=add_back_target($pdo,$r); }
                    catch (DomainException $e) { $r['approval_blocked']=$e->getMessage(); }
                }
                unset($r['SourceSnapshot'],$r['RateSnapshot'],$r['CalculationSnapshot']);
            } unset($r);
            $data=$id?['request'=>$rows[0]]:['requests'=>$rows];
        }
    } else throw new DomainException('Method not allowed.',405);
    echo json_encode(['success'=>true]+$data);
} catch (Throwable $e) {
    http_response_code($e instanceof DomainException?($e->getCode()?:400):500);
    if (!($e instanceof DomainException)) error_log('add_back_requests: '.$e->getMessage());
    echo json_encode(['success'=>false,'message'=>$e instanceof DomainException?$e->getMessage():'Add Back could not be processed.']);
}
