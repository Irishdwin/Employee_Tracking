<?php
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/../../Api/Helpers/work_site_geofence.php';
$c=new mysqli('localhost','root','','codex_multisite_20261005_r2');
function check($ok,$label){if(!$ok)throw new Exception($label);echo "PASS $label\n";}
$a=work_site_resolve_for_user($c,115);$b=work_site_resolve_for_user($c,117);
check($a['site_id']===6&&$b['site_id']===7,'Two employees resolve distinct stable branch IDs');
check($a['site_latitude']===8.43&&$b['site_latitude']===8.44,'Saved branch coordinates remain distinct');
check(geofence_validate_employee_position($c,115,8.43,124.65)['allowed'],'Inside saved site accepted');
check(!geofence_validate_employee_position($c,115,8.44,124.65)['allowed'],'Other branch coordinates rejected');
$c->begin_transaction();
try {
$c->query('UPDATE deployment_sites SET registered_latitude=NULL,registered_longitude=NULL WHERE SiteID=6');
check(!geofence_validate_employee_position($c,115,8.43,124.65)['allowed'],'Unregistered branch fails closed');
$c->query('UPDATE deployment SET EndDate=DATE_SUB(CURDATE(),INTERVAL 1 DAY) WHERE EmployeeID=4');
check(work_site_resolve_for_user($c,115)===null,'Ended deployment is not selected');
$c->query("UPDATE deployment SET EndDate=NULL WHERE EmployeeID=4");
$c->query("INSERT INTO deployment(EmployeeID,CompanyID,SiteID,StartDate,StatusID) SELECT EmployeeID,CompanyID,SiteID,StartDate,StatusID FROM deployment WHERE EmployeeID=4 LIMIT 1");
try{work_site_resolve_for_user($c,115);throw new Exception('Duplicate deployment silently selected');}catch(DomainException $e){check($e->getCode()===409,'Ambiguous active deployments rejected');}
}finally{$c->rollback();}
