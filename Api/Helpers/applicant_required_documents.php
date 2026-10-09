<?php
declare(strict_types=1);
function applicant_missing_documents(mysqli $db,int $userId): array {
 $q=$db->prepare("SELECT d.DocType,d.FilePath FROM applicant_uploaded_docs d JOIN applicants a ON a.ApplicantID=d.ApplicantID WHERE a.UserID=? AND d.DocType IN ('Resume','Government ID')");$q->bind_param('i',$userId);$q->execute();$rows=$q->get_result();$found=[];
 require_once __DIR__.'/../Applicant/applicant_doc_fs.php';
 $root=realpath(__DIR__.'/../../Uploads/Documents');
 while($r=$rows->fetch_assoc()){
  $file=basename(str_replace('\\','/',$r['FilePath']));$path=applicant_doc_absolute_path($r['FilePath']);
  if($path&&is_file($path)&&str_starts_with(strtolower($path),strtolower($root.DIRECTORY_SEPARATOR)))$found[$r['DocType']]=true;
 }$q->close();
 return array_values(array_filter(['Resume','Government ID'],static fn($type)=>empty($found[$type])));
}
