<?php
require_once __DIR__.'/../Common/session_init.php';
require_once __DIR__.'/../Db_connection/db_connect.php';
require_once __DIR__.'/applicant_doc_fs.php';
require_once __DIR__.'/../Helpers/application_endorsement.php';
// Historical URLs use the same document ownership and recruitment authorization.
if(isset($_GET['legacy_file'])){
 $legacy=basename((string)$_GET['legacy_file']);
 $q=$conn->prepare("SELECT DocID FROM applicant_uploaded_docs WHERE SUBSTRING_INDEX(REPLACE(FilePath,CHAR(92),'/'),'/',-1)=? LIMIT 1");$q->bind_param('s',$legacy);$q->execute();$old=$q->get_result()->fetch_assoc();$q->close();
 if(!$old){http_response_code(404);exit('Document unavailable.');}
 $_GET['id']=(int)$old['DocID'];
}
$uid=(int)($_SESSION['user_id']??0);$role=(string)($_SESSION['role']??'');
if(!in_array($role,['Applicant','Employee','HR','Admin','TeamLead','Team Lead'],true)){http_response_code(403);exit('Unauthorized');}
try { ae_authorize($conn,$uid,[$role]); } catch(Throwable $e){http_response_code(403);exit('Unauthorized');}
$id=(int)($_GET['id']??$_GET['doc_id']??0);$type=trim((string)($_GET['type']??''));
if($id>0){$q=$conn->prepare('SELECT d.*,a.UserID OwnerID,a.Email FROM applicant_uploaded_docs d JOIN applicants a ON a.ApplicantID=d.ApplicantID WHERE DocID=?');$q->bind_param('i',$id);}
else {$q=$conn->prepare('SELECT d.*,a.UserID OwnerID,a.Email FROM applicant_uploaded_docs d JOIN applicants a ON a.ApplicantID=d.ApplicantID WHERE a.UserID=? AND d.DocType=? ORDER BY d.DocID DESC LIMIT 1');$q->bind_param('is',$uid,$type);}
$q->execute();$doc=$q->get_result()->fetch_assoc();$q->close();
if(!$doc){http_response_code(404);exit('Document not found.');}
$allowed=in_array($role,['Admin','HR'],true)||((int)$doc['OwnerID']===$uid && in_array($role,['Applicant','Employee'],true));
if(in_array($role,['TeamLead','Team Lead'],true)){
 $q=$conn->prepare('SELECT jo.CompanyID FROM job_applications ja JOIN joborder jo ON jo.JobOrderID=ja.JobOrderID WHERE ja.ApplicantID=? AND ja.EndorsedDate IS NOT NULL');$q->bind_param('i',$doc['ApplicantID']);$q->execute();$rows=$q->get_result();while($row=$rows->fetch_assoc())if(ae_tl_scope($conn,$uid,(int)$row['CompanyID']))$allowed=true;$q->close();
}
if(!$allowed){http_response_code(403);exit('Unauthorized');}
$path=applicant_doc_absolute_path($doc['FilePath']);
if(!$path || !is_readable($path)){http_response_code(404);exit('Document unavailable.');}
if(str_contains(str_replace('\\','/',$doc['FilePath']),'/Resumes/')){require_once __DIR__.'/../Helpers/resume_upload.php';try{resume_validate_file(['error'=>UPLOAD_ERR_OK,'tmp_name'=>$path,'name'=>$doc['OriginalFileName']]);}catch(InvalidArgumentException $e){http_response_code(422);exit('The resume PDF is invalid or unavailable. Please upload a replacement.');}}
$mime=(new finfo(FILEINFO_MIME_TYPE))->file($path);
$inline=in_array($mime,['application/pdf','image/jpeg','image/png','image/webp'],true)&&empty($_GET['download']);
$name=preg_replace('/[\r\n"\\\\]/','_',basename($doc['OriginalFileName']?:$path));
header('Content-Type: '.($mime?:'application/octet-stream'));header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store');header('Content-Security-Policy: sandbox');
header('Content-Disposition: '.($inline?'inline':'attachment').'; filename="'.$name.'"');header('Content-Length: '.filesize($path));readfile($path);
