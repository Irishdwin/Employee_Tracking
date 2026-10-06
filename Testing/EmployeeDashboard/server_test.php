<?php
if (PHP_SAPI!=='cli') { http_response_code(404);exit; }
require __DIR__.'/../../Api/Db_connection/db_connect.php';
require __DIR__.'/../../Api/Helpers/work_site_geofence.php';
require __DIR__.'/../../Api/Helpers/employee_calendar_holidays.php';
$passed=0;
function check($ok,$label) { global $passed;if (!$ok) throw new RuntimeException('FAIL '.$label);++$passed;echo "PASS $label\n"; }
// Actual relationships, read-only.
$actual=$conn->query("SELECT u.UsersID,d.DeploymentID,d.CompanyID,d.SiteSuperVisorID,c.registered_latitude,c.registered_longitude,c.registered_by_tl_id FROM users u JOIN employee e ON e.Email=u.Email AND e.IsActive=1 JOIN deployment d ON d.EmployeeID=e.EmployeeID AND d.StartDate<=CURDATE() AND (d.EndDate IS NULL OR d.EndDate>=CURDATE()) JOIN clientcompany c ON c.CompanyID=d.CompanyID WHERE u.IsActive=1 ORDER BY u.UsersID,d.StartDate DESC,d.DeploymentID DESC")->fetch_all(MYSQLI_ASSOC);
$seen=[];
foreach ($actual as $row) {
    if (isset($seen[$row['UsersID']])) continue;$seen[$row['UsersID']]=true;
    $site=work_site_resolve_for_user($conn,(int)$row['UsersID']);
    check($site && $site['deployment_id']===(int)$row['DeploymentID'] && $site['company_id']===(int)$row['CompanyID'] && $site['site_supervisor_id']===(int)$row['SiteSuperVisorID'],'Actual deployment/company/Lead mapping user '.$row['UsersID']);
    check($site['site_latitude']===(float)$row['registered_latitude'] && $site['site_longitude']===(float)$row['registered_longitude'],'Actual registered coordinate pair user '.$row['UsersID']);
    check((int)$row['registered_by_tl_id']===$site['site_supervisor_id'],'Actual coordinates registered by assigned Lead user '.$row['UsersID']);
    check(geofence_validate_employee_position($conn,(int)$row['UsersID'],$site['site_latitude'],$site['site_longitude'])['allowed'],'Assigned site origin inside geofence');
    check(!geofence_validate_employee_position($conn,(int)$row['UsersID'],$site['site_latitude']+0.02,$site['site_longitude'])['allowed'],'Known distant point outside fixed radius');
}
check(count($seen)===3,'Three actual employee deployments inspected');
$nov=employee_calendar_holidays($conn,'2026-11-01','2026-11-30');$dec=employee_calendar_holidays($conn,'2026-12-01','2026-12-31');
check(count($nov)===3 && count($dec)===5,'Actual future November/December holiday records retrieved without attendance joins');
check(isset($nov['2026-11-01'][0]['name'],$nov['2026-11-01'][0]['type']) && array_key_exists('description',$nov['2026-11-01'][0]),'Actual holiday detail fields returned (remarks may be null)');
// Isolated schema for future/ended deployments, missing sites and ambiguity; no business data copied.
$name='onazul_gps_calendar_test_'.bin2hex(random_bytes(5));$conn->query("CREATE DATABASE `$name` CHARACTER SET utf8mb4");$test=new mysqli('127.0.0.1','root','',$name);
try {
    $test->query('SET FOREIGN_KEY_CHECKS=0');
    foreach ($conn->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'")->fetch_all() as $table) $test->query($conn->query('SHOW CREATE TABLE `'.$table[0].'`')->fetch_row()[1]);
    $test->query('SET FOREIGN_KEY_CHECKS=1');
    $test->query("INSERT INTO roles(RolesID,RoleName) VALUES(4,'TeamLead'),(6,'Employee')");
    $test->query("INSERT INTO status(StatusID,Name) VALUES(5,'Active')");
    $test->query("INSERT INTO users(UsersID,RoleID,FullName,Email,Password) VALUES(1,6,'Test employee','employee@example.invalid','not-a-login'),(2,4,'Test Lead','lead@example.invalid','not-a-login')");
    $test->query("INSERT INTO employee(EmployeeID,EmployeeNumber,FirstName,LastName,Email,Position,IsActive) VALUES(1,'FIXTURE','Test','Employee','employee@example.invalid','Test',1)");
    $test->query("INSERT INTO clientcompany(CompanyID,Name,UserID,registered_latitude,registered_longitude,geofence_radius) VALUES(1,'Current company',2,10,120,100),(2,'Other company',2,11,121,200)");
    $test->query("INSERT INTO deployment(DeploymentID,EmployeeID,CompanyID,SiteSuperVisorID,StartDate,EndDate,StatusID) VALUES(1,1,1,2,DATE_SUB(CURDATE(),INTERVAL 3 DAY),NULL,5),(2,1,2,2,DATE_ADD(CURDATE(),INTERVAL 1 DAY),NULL,5),(3,1,2,2,DATE_SUB(CURDATE(),INTERVAL 10 DAY),DATE_SUB(CURDATE(),INTERVAL 1 DAY),5)");
    check(work_site_resolve_for_user($test,1)['deployment_id']===1,'Future and ended deployments excluded');
    $test->query('UPDATE clientcompany SET registered_latitude=NULL,registered_longitude=NULL,latitude=NULL,longitude=NULL WHERE CompanyID=1');
    check(!work_site_resolve_for_user($test,1)['site_registered'],'Missing assigned coordinates do not borrow another Lead company');
    check(!geofence_validate_employee_position($test,1,11,121)['allowed'],'Another company coordinates cannot unlock attendance');
    $test->query('UPDATE clientcompany SET registered_latitude=10,registered_longitude=NULL,latitude=12,longitude=122 WHERE CompanyID=1');
    $site=work_site_resolve_for_user($test,1);check($site['site_latitude']===12.0 && $site['site_longitude']===122.0,'Coordinate pairs never mix registered and legacy axes');
    try { work_site_company_for_team_lead($test,2);throw new RuntimeException('Ambiguous company accepted'); } catch (DomainException $e) {check(true,'Ambiguous Lead work sites fail closed');}
    check(work_site_company_for_team_lead($test,2,1)===1,'Explicit assigned Lead company accepted');
    try { work_site_company_for_team_lead($test,2,999);throw new RuntimeException('Unknown company accepted'); } catch (DomainException $e) {check(true,'Unassigned Lead company rejected');}
    $test->query('UPDATE users SET IsActive=0 WHERE UsersID=1');check(work_site_resolve_for_user($test,1)===null,'Inactive authenticated employee rejected');
    $test->query("INSERT INTO holidays(HolidayName,HolidayDate,HolidayType,Description) VALUES('First','2026-11-01','Regular','Details'),('Second','2026-11-01','Special','More details')");
    check(count(employee_calendar_holidays($test,'2026-11-01','2026-11-30')['2026-11-01'])===2,'Multiple holidays on one date preserved');
    check(employee_calendar_holidays($test,'2026-10-01','2026-10-31')===[],'Empty holiday month supported');
    check(abs(calculateHaversineDistance(0,1,1,1)-111194.93)<0.1,'Existing Haversine reference distance verified');
    echo "TOTAL $passed PASS\n";
} finally {$test->close();$conn->query("DROP DATABASE `$name`");}
