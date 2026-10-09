<?php
declare(strict_types=1);
require_once __DIR__.'/_testing_guard.php';require_once __DIR__.'/_selection_context.php';
testing_lead_site($conn,$_GET);$current=($_GET['history']??'')==='1' ? 'd.DeploymentID=(SELECT dx.DeploymentID FROM deployment dx WHERE dx.EmployeeID=d.EmployeeID AND dx.SiteID=d.SiteID ORDER BY dx.StartDate DESC,dx.DeploymentID DESC LIMIT 1)' : site_current_deployment_sql();
$employeeActive=($_GET['history']??'')==='1' ? '' : ' AND e.IsActive=1';
$rows=site_query($conn,"SELECT e.EmployeeID employee_id,e.EmployeeNumber employee_number,CONCAT(e.FirstName,' ',e.LastName) name,d.DeploymentID deployment_id FROM deployment d JOIN employee e ON e.EmployeeID=d.EmployeeID $employeeActive WHERE d.SiteID=? AND d.CompanyID=? AND $current ORDER BY name",'ii',[(int)$_GET['site_id'],(int)$_GET['company_id']])->fetch_all(MYSQLI_ASSOC);
echo json_encode(['success'=>true,'employees'=>$rows]);
