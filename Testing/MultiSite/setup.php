<?php
// CLI-only isolated copy of the actual schema. Never modify business records.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$cfg=json_decode(file_get_contents(__DIR__.'/runtime.json'),true);
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
$c=new mysqli('127.0.0.1','root','','onazul_hris_db');$c->set_charset('utf8mb4');
$db=$cfg['database'];if(!preg_match('/^codex_multisite_[a-z0-9_]+$/',$db))throw new RuntimeException('Invalid isolated database name.');
$c->query("CREATE DATABASE `$db` CHARACTER SET utf8mb4");
foreach($c->query('SHOW TABLES')->fetch_all() as $row){$t=$row[0];if(!preg_match('/^[a-zA-Z0-9_]+$/',$t))continue;$c->query("CREATE TABLE `$db`.`$t` LIKE onazul_hris_db.`$t`");$c->query("INSERT INTO `$db`.`$t` SELECT * FROM onazul_hris_db.`$t`");}
$c->select_db($db);
$role=(int)$c->query("SELECT RolesID FROM roles WHERE RoleName='TeamLead'")->fetch_assoc()['RolesID'];
$hash=password_hash('FixtureOnly!2026Zx',PASSWORD_DEFAULT);
$st=$c->prepare('INSERT INTO users(RoleID,FullName,Email,Password,IsActive) VALUES(?,?,?,?,1)');
$name='MultiSite Fixture Lead';$email='multisite.fixture@gmail.com';$st->bind_param('isss',$role,$name,$email,$hash);$st->execute();$lead=$c->insert_id;
$name='Other Fixture Lead';$email='multisite.other@gmail.com';$st->execute();$other=$c->insert_id;$st->close();
$c->query("INSERT INTO clientcompany(Name,Address,UserID,PayrollType,IsActive) VALUES('Villa Tuna Test','Fixture address',$lead,'B',1)");$company=$c->insert_id;
$sites=[];foreach(['Branch 1','Branch 2'] as $name){$st=$c->prepare('INSERT INTO deployment_sites(CompanyID,Name,Address,TeamLeadID) VALUES(?,?,?,?)');$address=$name.' fixture location';$st->bind_param('issi',$company,$name,$address,$lead);$st->execute();$sites[]=$c->insert_id;}
$employees=[];foreach(['A','B','C','D'] as $suffix){
 $base=$c->query('SELECT * FROM employee ORDER BY EmployeeID LIMIT 1')->fetch_assoc();unset($base['EmployeeID']);$base['EmployeeNumber']='FIX-'.$suffix;$base['Email']='fixture.'.$suffix.'@gmail.com';$base['FirstName']='Employee';$base['LastName']=$suffix;$base['IsActive']=1;
 $cols=implode(',',array_map(static fn($k)=>'`'.$k.'`',array_keys($base)));$q=implode(',',array_fill(0,count($base),'?'));
 $st=$c->prepare("INSERT INTO employee($cols) VALUES($q)");$values=array_values($base);$st->bind_param(str_repeat('s',count($values)),...$values);$st->execute();$employees[]=$c->insert_id;
}
$cfg+=['lead'=>$lead,'other'=>$other,'company'=>$company,'sites'=>$sites,'employees'=>$employees];
$cfg['admin']=(int)$c->query("SELECT u.UsersID FROM users u JOIN roles r ON r.RolesID=u.RoleID WHERE r.RoleName='Admin' AND u.IsActive=1 LIMIT 1")->fetch_assoc()['UsersID'];
$cfg['accounting']=(int)$c->query("SELECT u.UsersID FROM users u JOIN roles r ON r.RolesID=u.RoleID WHERE r.RoleName='Accounting' AND u.IsActive=1 LIMIT 1")->fetch_assoc()['UsersID'];
file_put_contents(__DIR__.'/runtime.json',json_encode($cfg));echo "Isolated actual-schema fixture ready.\n";
