<?php
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/../../Api/Helpers/applicant_required_documents.php';
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
$db=new mysqli('127.0.0.1','root','');$name='codex_required_docs_'.bin2hex(random_bytes(6));$files=[];
function fixture(mysqli $db,string $table,array $values): int {
    foreach($db->query('SHOW COLUMNS FROM `'.$table.'`')->fetch_all(MYSQLI_ASSOC) as $c) {
        if(array_key_exists($c['Field'],$values) || $c['Null']==='YES' || $c['Default']!==null || str_contains($c['Extra'],'auto_increment'))continue;
        $type=strtolower($c['Type']);$values[$c['Field']]=preg_match('/int|decimal|float|double/',$type)?1:(str_starts_with($type,'date')?'2026-10-01':(str_starts_with($type,'enum')?explode("'",$type)[1]:'Synthetic'));
    }
    $fields=array_keys($values);$stmt=$db->prepare('INSERT INTO `'.$table.'` (`'.implode('`,`',$fields).'`) VALUES('.implode(',',array_fill(0,count($fields),'?')).')');$args=array_values($values);$stmt->bind_param(str_repeat('s',count($args)),...$args);$stmt->execute();return (int)$db->insert_id;
}
function check($ok,$label){if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
try{$db->query('CREATE DATABASE `'.$name.'`');$db->select_db($name);foreach(['applicants','applicant_uploaded_docs'] as $t)$db->query('CREATE TABLE `'.$t.'` LIKE onazul_hris_db.`'.$t.'`');
fixture($db,'applicants',['ApplicantID'=>1,'UserID'=>101]);fixture($db,'applicants',['ApplicantID'=>2,'UserID'=>102]);
check(count(applicant_missing_documents($db,101))===2,'both documents required');
foreach(['resume','id'] as $key){$file='codex_required_docs_'.bin2hex(random_bytes(8)).'.txt';$path=__DIR__.'/../../Uploads/Documents/'.$file;file_put_contents($path,'Synthetic document validation fixture');$files[$key]=$path;$refs[$key]='../../Uploads/Documents/'.$file;}
fixture($db,'applicant_uploaded_docs',['ApplicantID'=>1,'DocType'=>'Resume','FilePath'=>$refs['resume']]);
check(applicant_missing_documents($db,101)===['Government ID'],'resume alone does not allow save');
fixture($db,'applicant_uploaded_docs',['ApplicantID'=>2,'DocType'=>'Government ID','FilePath'=>$refs['id']]);
check(applicant_missing_documents($db,101)===['Government ID'],'another applicant ID does not count');
fixture($db,'applicant_uploaded_docs',['ApplicantID'=>1,'DocType'=>'Government ID','FilePath'=>$refs['id']]);
check(applicant_missing_documents($db,101)===[],'resume and ID sufficient without certification or CSV');
unlink($files['id']);check(applicant_missing_documents($db,101)===['Government ID'],'missing physical upload cannot satisfy requirement');
}finally{foreach($files as $file)if(is_file($file))unlink($file);$db->query('DROP DATABASE IF EXISTS `'.$name.'`');}
