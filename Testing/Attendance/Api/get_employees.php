<?php
declare(strict_types=1);
require_once __DIR__.'/_testing_guard.php';require_once __DIR__.'/_selection_context.php';
testing_lead_site($conn,$_GET);$current=site_current_deployment_sql();
$rows=site_query($conn,"SELECT e.EmployeeID employee_id,e.EmployeeNumber employee_number,CONCAT(e.FirstName,' ',e.LastName) name,d.DeploymentID deployment_id FROM deployment d JOIN employee e ON e.EmployeeID=d.EmployeeID AND e.IsActive=1 WHERE d.SiteID=? AND d.CompanyID=? AND $current ORDER BY name",'ii',[(int)$_GET['site_id'],(int)$_GET['company_id']])->fetch_all(MYSQLI_ASSOC);
echo json_encode(['success'=>true,'employees'=>$rows]);
