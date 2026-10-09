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
    if (!preg_match('/^[A-Za-z0-9_-]+\.(jpg|jpeg|png|webp)$/i',$name)) throw new DomainException('Invalid evidence reference.',403);
    $path=$root ? realpath($root.'/'.$name) : false;
    if (!$path || !is_file($path) || !str_starts_with(strtolower($path),strtolower($root.DIRECTORY_SEPARATOR))) throw new DomainException('Evidence file is unavailable.',404);
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($path);
    if (!in_array($mime,$folder==='add_back_evidence'?['image/jpeg','image/png','image/webp']:['image/jpeg','image/png'],true) || !getimagesize($path)) throw new DomainException('Unsupported evidence format.',415);
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

/** Validate the PHP upload and decoded image before any request is committed. */
function evidence_uploads(array $uploads): array {
    if(!$uploads)return [];
    if(!isset($uploads['name']) || !is_array($uploads['name']))throw new DomainException('Invalid image upload.',422);
    $files=[];
    foreach($uploads['name'] as $i=>$original){
        $error=$uploads['error'][$i]??UPLOAD_ERR_NO_FILE;
        if($error===UPLOAD_ERR_NO_FILE)continue;
        if(count($files)>=3)throw new DomainException('You can upload a maximum of 3 images.',422);
        if(in_array($error,[UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_FORM_SIZE],true))throw new DomainException('Each image must not exceed 5 MB.',422);
        if($error!==UPLOAD_ERR_OK)throw new DomainException('Image upload failed. Please select the images again.',422);
        $path=$uploads['tmp_name'][$i]??'';
        if(!is_string($path)||!is_uploaded_file($path))throw new DomainException('Invalid image upload.',422);
        $size=filesize($path);if(!$size||$size>5*1024*1024)throw new DomainException('Each image must not exceed 5 MB.',422);
        $extension=strtolower(pathinfo((string)$original,PATHINFO_EXTENSION));$mime=(new finfo(FILEINFO_MIME_TYPE))->file($path);
        $types=['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp'];
        if(!isset($types[$extension])||$types[$extension]!==$mime)throw new DomainException('Only JPG, JPEG, PNG, and WEBP images are allowed.',422);
        $info=@getimagesize($path);
        if(!$info || $info['mime']!==$mime || $info[0]>16384 || $info[1]>16384 || $info[0]*$info[1]>20000000)throw new DomainException('The selected file is not a supported image or its dimensions are too large.',422);
        $decoded=@imagecreatefromstring(file_get_contents($path));if(!$decoded)throw new DomainException('The selected image is damaged or invalid.',422);imagedestroy($decoded);
        $files[]=['path'=>$path,'mime'=>$mime,'size'=>$size,'sha256'=>hash_file('sha256',$path)];
    }
    return $files;
}
