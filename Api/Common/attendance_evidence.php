<?php
declare(strict_types=1);
require_once __DIR__.'/session_init.php';
require_once __DIR__.'/../Helpers/attendance_pdo.php';
require_once __DIR__.'/../Db_connection/db_connect.php';
require_once __DIR__.'/../Helpers/attendance_evidence.php';
try {
    $user=(int)($_SESSION['user_id']??0); $role=(string)($_SESSION['role']??'');
    if (!$user) throw new DomainException('Unauthorized.',401);
    if (isset($_GET['file']) || isset($_GET['path'])) throw new DomainException('File paths are not accepted.',400);
    $type=(string)($_GET['type']??'');
    if (!in_array($type,['clockin','clockout','upload1','upload2','upload3'],true)) throw new DomainException('Invalid evidence type.',400);
    $pdo=attendance_pdo($conn);$auth=$pdo->prepare('SELECT r.RoleName FROM users u JOIN roles r ON r.RolesID=u.RoleID WHERE u.UsersID=? AND u.IsActive=1');$auth->execute([$user]);if($auth->fetchColumn()!==$role)throw new DomainException('Forbidden.',403);
    if (isset($_GET['evidence_id'])) {
        $q=$pdo->prepare('SELECT ev.*,r.EmployeeID,r.SubmittedBy,r.WorkDate FROM add_back_evidence ev JOIN add_back_requests r ON r.RequestID=ev.RequestID WHERE ev.EvidenceID=?');
        $q->execute([(int)$_GET['evidence_id']]); $ev=$q->fetch();
        if (!$ev) throw new DomainException('Evidence not found.',404);
        if ($role==='Employee') {
            if ((int)evidence_employee($pdo,$user)['EmployeeID']!==(int)$ev['EmployeeID']) throw new DomainException('Forbidden.',403);
        } elseif (!in_array($role,['Accounting','Admin'],true)) throw new DomainException('Forbidden.',403);
        if ($type!==$ev['EvidenceType']) throw new DomainException('Invalid evidence type.',400);
        $file=evidence_file($ev['StorageName'],'add_back_evidence');
        if (!hash_equals($ev['Sha256'],$file['sha256'])) throw new DomainException('Evidence integrity check failed.',409);
        $date=$ev['WorkDate'];
    } else {
        if(!in_array($type,['clockin','clockout'],true))throw new DomainException('Invalid evidence type.',400);
        $a=evidence_attendance($pdo,(int)($_GET['attendance_id']??0),$user,$role);
        $file=evidence_file((string)($a[$type.'_photo']??'')); $date=$a['Date'];
    }
    header('Content-Type: '.$file['mime']); header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store'); header('Content-Length: '.$file['size']);
    $extension=['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'][$file['mime']];
    header('Content-Disposition: '.(!empty($_GET['download'])?'attachment':'inline').'; filename="attendance_'.$date.'_'.$type.'.'.$extension.'"');
    session_write_close(); readfile($file['path']);
} catch (Throwable $e) {
    http_response_code($e instanceof DomainException ? ($e->getCode()?:400) : 500);
    header('Content-Type: application/json'); header('Cache-Control: no-store');
    if (!($e instanceof DomainException)) error_log('attendance_evidence: '.$e->getMessage());
    echo json_encode(['success'=>false,'message'=>$e instanceof DomainException?$e->getMessage():'Evidence could not be loaded.']);
}
