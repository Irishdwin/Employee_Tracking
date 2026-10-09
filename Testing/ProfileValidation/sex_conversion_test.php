<?php
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/../../Api/Helpers/hire_applicant.php';
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
$db=new mysqli('127.0.0.1','root','');$name='codex_sex_hire_'.bin2hex(random_bytes(6));
function fixture(mysqli $db,string $table,array $values): int {
    foreach($db->query('SHOW COLUMNS FROM `'.$table.'`')->fetch_all(MYSQLI_ASSOC) as $c) {
        if(array_key_exists($c['Field'],$values) || $c['Null']==='YES' || $c['Default']!==null || str_contains($c['Extra'],'auto_increment'))continue;
        $type=strtolower($c['Type']);$values[$c['Field']]=preg_match('/int|decimal|float|double/',$type)?1:(str_starts_with($type,'date')?'2026-10-01':(str_starts_with($type,'enum')?explode("'",$type)[1]:'Synthetic'));
    }
    $fields=array_keys($values);$stmt=$db->prepare('INSERT INTO `'.$table.'` (`'.implode('`,`',$fields).'`) VALUES('.implode(',',array_fill(0,count($fields),'?')).')');$args=array_values($values);$stmt->bind_param(str_repeat('s',count($args)),...$args);$stmt->execute();return (int)$db->insert_id;
}
try{$db->query('CREATE DATABASE `'.$name.'`');$db->select_db($name);foreach($db->query('SHOW TABLES FROM onazul_hris_db')->fetch_all()as$r)$db->query('CREATE TABLE `'.$r[0].'` LIKE onazul_hris_db.`'.$r[0].'`');
$db->query('INSERT INTO roles SELECT * FROM onazul_hris_db.roles');$db->query('INSERT INTO status SELECT * FROM onazul_hris_db.status');
$role=(int)$db->query("SELECT RolesID FROM roles WHERE RoleName='Applicant'")->fetch_row()[0];
fixture($db,'users',['UsersID'=>101,'RoleID'=>$role,'FullName'=>'Synthetic Applicant','Email'=>'sex-test@synthetic.invalid','IsActive'=>1]);
fixture($db,'applicants',['ApplicantID'=>201,'UserID'=>101,'Email'=>'sex-test@synthetic.invalid','FirstName'=>'Synthetic','LastName'=>'Applicant','FullName'=>'Synthetic Applicant','Gender'=>'Female']);
fixture($db,'clientcompany',['CompanyID'=>301,'Name'=>'Synthetic Company']);
fixture($db,'deployment_sites',['SiteID'=>302,'CompanyID'=>301,'Name'=>'Synthetic Site','IsActive'=>1]);
fixture($db,'joborder',['JobOrderID'=>401,'CompanyID'=>301,'Position'=>'Synthetic Worker']);
fixture($db,'job_applications',['ApplicationID'=>501,'ApplicantID'=>201,'JobOrderID'=>401,'ClientApprovalStatus'=>'Approved','HiredDate'=>null,'Remarks'=>'Contract uploaded: synthetic test contract']);
$r=hireApplicantFromApplication($db,501,101,'SSS-test','PH-test','PI-test',500,0);if(!$r['success'])throw new RuntimeException(json_encode($r));
$employee=$db->query('SELECT gender FROM employee WHERE EmployeeID='.(int)$r['employee_id'])->fetch_assoc();if($employee['gender']!=='Female')throw new RuntimeException('Sex not carried to employee');
if($db->query('SELECT Gender FROM applicants WHERE ApplicantID=201')->fetch_row()[0]!=='Female')throw new RuntimeException('Applicant record lost sex');
echo 'PASS actual hiring flow preserves applicant sex and copies it to employee record.';
}finally{$db->query('DROP DATABASE IF EXISTS `'.$name.'`');}
