<?php
declare(strict_types=1);

function evidence_employee(PDO $pdo, int $userId): array {
    $q=$pdo->prepare('SELECT e.* FROM employee e JOIN users u ON u.Email=e.Email WHERE u.UsersID=? AND e.IsActive=1 LIMIT 1');
    $q->execute([$userId]); $e=$q->fetch();
    if (!$e) throw new DomainException('Employee account not found.',403);
    return $e;
}

/** Resolve only database references beneath a fixed private directory. */
function evidence_file(string $reference, string $folder='attendance_photos'): array {
    if (!in_array($folder,['attendance_photos','add_back_evidence'],true)) throw new DomainException('Invalid evidence type.',400);
    $root=realpath(__DIR__.'/../../uploads/'.$folder);
    $prefix='uploads/'.$folder.'/';
    $reference=str_replace('\\','/',$reference);
    if ($folder==='attendance_photos') {
        if (!str_starts_with($reference,$prefix)) throw new DomainException('Invalid evidence reference.',403);
        $name=substr($reference,strlen($prefix));
    } else $name=$reference;
    if (!preg_match('/^[A-Za-z0-9_-]+\.(jpg|jpeg|png)$/i',$name)) throw new DomainException('Invalid evidence reference.',403);
    $path=$root ? realpath($root.'/'.$name) : false;
    if (!$path || !is_file($path) || !str_starts_with(strtolower($path),strtolower($root.DIRECTORY_SEPARATOR))) throw new DomainException('Evidence file is unavailable.',404);
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($path);
    if (!in_array($mime,['image/jpeg','image/png'],true) || !getimagesize($path)) throw new DomainException('Unsupported evidence format.',415);
    return ['path'=>$path,'mime'=>$mime,'size'=>filesize($path),'sha256'=>hash_file('sha256',$path)];
}

function evidence_attendance(PDO $pdo, int $id, int $userId, string $role): array {
    $q=$pdo->prepare('SELECT a.*,e.FirstName,e.LastName FROM attendance a JOIN employee e ON e.EmployeeID=a.EmployeeID WHERE a.AttendanceID=?');
    $q->execute([$id]); $a=$q->fetch();
    if (!$a) throw new DomainException('Attendance not found.',404);
    if ($role==='Employee') {
        $e=evidence_employee($pdo,$userId);
        if ((int)$e['EmployeeID']!==(int)$a['EmployeeID']) throw new DomainException('This evidence belongs to another employee.',403);
    } elseif ($role==='TeamLead') {
        $q=$pdo->prepare('SELECT DeploymentID FROM deployment WHERE EmployeeID=? AND SiteSuperVisorID=? AND (StartDate IS NULL OR StartDate<=?) AND (EndDate IS NULL OR EndDate>=?)');
        $q->execute([$a['EmployeeID'],$userId,$a['Date'],$a['Date']]);
        if (!$q->fetch()) throw new DomainException('Evidence is outside your assigned team.',403);
    } elseif (!in_array($role,['Accounting','Admin'],true)) throw new DomainException('Forbidden.',403);
    return $a;
}

function evidence_safe_attendance(array $a): array {
    $keys=['AttendanceID','EmployeeID','Date','TimeIn','TimeOut','TotalHours','scheduled_time_in','scheduled_time_out','attendance_status','clockin_latitude','clockin_longitude','clockout_latitude','clockout_longitude','gps_accuracy','geofence_status','EvidenceDeploymentID'];
    $out=array_intersect_key($a,array_flip($keys));
    foreach (['in','out'] as $kind) {
        $available=false;
        if (!empty($a['clock'.$kind.'_photo'])) {
            try { evidence_file((string)$a['clock'.$kind.'_photo']); $available=true; }
            catch (DomainException $e) { /* Unavailable evidence is not a usable image link. */ }
        }
        $out['clock'.$kind.'_url']=$available ? '../../Api/Common/attendance_evidence.php?attendance_id='.(int)$a['AttendanceID'].'&type=clock'.$kind : null;
    }
    return $out;
}
