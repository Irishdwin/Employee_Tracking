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
    $pdo=attendance_pdo($conn);
    $auth=$pdo->prepare('SELECT r.RoleName FROM users u JOIN roles r ON r.RolesID=u.RoleID WHERE u.UsersID=? AND u.IsActive=1');$auth->execute([$user]);if($auth->fetchColumn()!==$role)throw new DomainException('Forbidden.',403);
    if(empty($_SESSION['add_back_csrf']))$_SESSION['add_back_csrf']=bin2hex(random_bytes(32)); $emp=$role==='Employee'?evidence_employee($pdo,$user):null;
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        // Keep cross-site and token protection for JSON and multipart requests.
        $origin=$_SERVER['HTTP_ORIGIN']??null;
        if ($origin && parse_url($origin,PHP_URL_HOST)!==explode(':',$_SERVER['HTTP_HOST']??'')[0]) throw new DomainException('Invalid request origin.',403);
        if(!hash_equals($_SESSION['add_back_csrf'],(string)($_SERVER['HTTP_X_ADDBACK_CSRF']??'')))throw new DomainException('Invalid request token. Refresh the request list and retry.',403);
        if(array_diff(array_keys($_FILES),['evidence']))throw new DomainException('Unsupported upload field.',415);
        if($role!=='Employee' && $_FILES)throw new DomainException('Only employees can submit supporting images.',403);
        $body=str_starts_with($_SERVER['CONTENT_TYPE']??'','multipart/form-data')?$_POST:json_decode(file_get_contents('php://input'),true);
        if (!is_array($body)) throw new DomainException('A JSON request is required.',400);
        if ($role==='Employee') {
            $id=add_back_submit($pdo,$conn,$user,$body,$_FILES['evidence']??[]);$q=$pdo->prepare("SELECT r.RequestID,r.WorkDate,r.DutyStart,r.DutyEnd,(SELECT COUNT(*) FROM add_back_evidence ev WHERE ev.RequestID=r.RequestID AND ev.EvidenceType LIKE 'upload%') uploaded_count FROM add_back_requests r WHERE r.RequestID=?");$q->execute([$id]);$data=['request_id'=>$id,'submission'=>$q->fetch()];
        }
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
            $q=$pdo->prepare($sql.' ORDER BY r.RequestID DESC'.($emp ? ' LIMIT 100' : '')); $q->execute($args); $rows=$q->fetchAll();
            if ($id && !$rows) throw new DomainException('Request not found or not owned by this account.',403);
            foreach ($rows as &$r) {
                if($r['StatusName']==='Pending'){try{add_back_notify_accounting($pdo,(int)$r['RequestID']);}catch(Throwable $e){error_log('Add Back notification retry: '.$e->getMessage());}}
                $source=json_decode($r['SourceSnapshot'],true);
                $r['source']=['attendance'=>$source['attendance']?evidence_safe_attendance($source['attendance']):null,'schedule'=>$source['schedule'],'locked_dtr'=>!empty($source['dtr_submissions'])];
                $q=$pdo->prepare('SELECT EvidenceID,EvidenceType,CapturedAt FROM add_back_evidence WHERE RequestID=?'); $q->execute([$r['RequestID']]); $r['evidence']=$q->fetchAll();
                foreach ($r['evidence'] as &$ev) $ev['url']='../../Api/Common/attendance_evidence.php?evidence_id='.(int)$ev['EvidenceID'].'&type='.$ev['EvidenceType']; unset($ev);
                if (!$emp && $id && $r['StatusName']==='Pending') {
                    try { $quote=add_back_quote($pdo,$conn,$r); $r['quote']=['amount'=>$quote['amount'],'hours'=>$quote['hours'],'rate_source'=>$quote['rates']['source'],'daily_rate'=>$quote['rates']['daily_rate'],'hourly_rate'=>$quote['rates']['hourly_rate'],'effective_date'=>$quote['rates']['effective_date']??$r['WorkDate']]; $r['default_target']=add_back_target($pdo,$r); }
                    catch (DomainException $e) { $r['approval_blocked']=$e->getMessage(); }
                }
                unset($r['SourceSnapshot'],$r['RateSnapshot'],$r['CalculationSnapshot']);
            } unset($r);
            $data=$id?['request'=>$rows[0]]:['requests'=>$rows];
        }
    } else throw new DomainException('Method not allowed.',405);
    echo json_encode(['success'=>true,'csrf_token'=>$_SESSION['add_back_csrf']]+$data);
} catch (Throwable $e) {
    http_response_code($e instanceof DomainException?($e->getCode()?:400):500);
    if (!($e instanceof DomainException)) error_log('add_back_requests: '.$e->getMessage());
    echo json_encode(['success'=>false,'message'=>$e instanceof DomainException?$e->getMessage():'Add Back could not be processed.']);
}
