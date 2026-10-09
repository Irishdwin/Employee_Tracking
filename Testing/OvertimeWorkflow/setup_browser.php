<?php
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/../../Api/Db_connection/db_connect.php';
$testDb='codex_ot_ui_'.bin2hex(random_bytes(6));$conn->query("CREATE DATABASE `$testDb`");
foreach(array_column($conn->query('SHOW TABLES')->fetch_all(MYSQLI_NUM),0) as $t){$conn->query("CREATE TABLE `$testDb`.`$t` LIKE `onazul_hris_db`.`$t`");}
$conn->select_db($testDb);
$conn->query("INSERT INTO roles(RolesID,RoleName) VALUES(1,'TeamLead'),(2,'Employee')");
$conn->query("INSERT INTO status(StatusID,Name) VALUES(1,'Pending'),(2,'Active'),(3,'Completed'),(4,'Approved'),(5,'Rejected'),(6,'Accepted')");
$conn->query("INSERT INTO users(UsersID,RoleID,FullName,Email,Password,IsActive) VALUES(11,1,'Lead One','lead1@example.invalid','x',1),(12,1,'Lead Two','lead2@example.invalid','x',1),(21,2,'Employee One','emp1@example.invalid','x',1),(22,2,'Employee Two','emp2@example.invalid','x',1),(23,2,'Employee Three','emp3@example.invalid','x',1)");
$conn->query("INSERT INTO employee(EmployeeID,FirstName,LastName,Email,IsActive) VALUES(31,'Employee','One','emp1@example.invalid',1),(32,'Employee','Two','emp2@example.invalid',1),(33,'Employee','Three','emp3@example.invalid',1)");
$conn->query("INSERT INTO clientcompany(CompanyID,Name,IsActive) VALUES(41,'Company One',1),(42,'Company Two',1)");
$conn->query("INSERT INTO deployment_sites(SiteID,CompanyID,Name,TeamLeadID,IsActive) VALUES(51,41,'Site One',11,1),(52,42,'Site Two',11,1),(53,42,'Outside Site',12,1)");
$conn->query("INSERT INTO deployment(DeploymentID,EmployeeID,CompanyID,SiteID,SiteSuperVisorID,StartDate,StatusID) VALUES(61,31,41,51,12,CURDATE()-INTERVAL 1 DAY,2),(62,32,42,52,12,CURDATE()-INTERVAL 1 DAY,2),(63,33,42,53,11,CURDATE()-INTERVAL 1 DAY,2)");
file_put_contents(__DIR__.'/test_db.txt',$testDb);echo $testDb;
