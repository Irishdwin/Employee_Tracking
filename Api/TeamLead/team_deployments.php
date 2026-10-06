<?php

require_once __DIR__ . '/../Common/session_init.php';



header('Content-Type: application/json');



if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {

    http_response_code(401);

    echo json_encode(['success' => false, 'message' => 'Unauthorized']);

    exit();

}



$role = trim((string)($_SESSION['role'] ?? ''));

$allowedRoles = ['TeamLead', 'Team Lead', 'Admin', 'HR'];

if (!in_array($role, $allowedRoles, true)) {

    http_response_code(403);

    echo json_encode(['success' => false, 'message' => 'Forbidden']);

    exit();

}



require __DIR__ . '/../Db_connection/db_connect.php';



function json_resp($data, $status = 200) {

    http_response_code($status);

    echo json_encode($data);

    exit();

}



$userId = (int)$_SESSION['user_id'];

$teamLeadName = $_SESSION['full_name'] ?? 'Team Lead';



$nameStmt = $conn->prepare("SELECT FullName FROM users WHERE UsersID = ? LIMIT 1");

if ($nameStmt) {

    $nameStmt->bind_param("i", $userId);

    $nameStmt->execute();

    $nameRes = $nameStmt->get_result();

    if ($nameRes && ($row = $nameRes->fetch_assoc())) {

        if (!empty($row['FullName'])) $teamLeadName = $row['FullName'];

    }

    $nameStmt->close();

}



require_once __DIR__ . '/../Helpers/work_site_geofence.php';

try {

    $sites=sites_for_lead($conn,$userId);$siteId=(int)($_GET['site_id']??0);

    if($siteId)site_for_lead($conn,$userId,$siteId);

    if(($_GET['sites_only']??'')==='1')json_resp(['success'=>true,'sites'=>$sites,'deployments'=>[]]);
    $current=site_current_deployment_sql();

    $rows=site_query($conn,"SELECT d.*,e.EmployeeNumber,CONCAT(e.FirstName,' ',e.LastName) EmployeeName,c.Name CompanyName,s.Name SiteName,s.Address SiteAddress

       FROM deployment d JOIN employee e ON e.EmployeeID=d.EmployeeID AND e.IsActive=1

       JOIN deployment_sites s ON s.SiteID=d.SiteID AND s.IsActive=1

       JOIN clientcompany c ON c.CompanyID=s.CompanyID AND c.IsActive=1

       WHERE s.TeamLeadID=? AND (?=0 OR s.SiteID=?) AND $current ORDER BY EmployeeName",'iii',[$userId,$siteId,$siteId])->fetch_all(MYSQLI_ASSOC);

    $deployments=array_map(static fn($r)=>[

      'deployment_id'=>(int)$r['DeploymentID'],'employee_id'=>(int)$r['EmployeeID'],'employee_name'=>$r['EmployeeName'],

      'employee_number'=>$r['EmployeeNumber'],'company_id'=>(int)$r['CompanyID'],'company_name'=>$r['CompanyName'],

      'site_id'=>(int)$r['SiteID'],'site_name'=>$r['SiteName'],'position'=>$r['Position'],'location'=>$r['SiteAddress'],

      'start_date'=>$r['StartDate'],'end_date'=>$r['EndDate']],$rows);

    json_resp(['success'=>true,'team_lead_name'=>$teamLeadName,'deployments'=>$deployments,'sites'=>$sites]);

}catch(DomainException $e){json_resp(['success'=>false,'message'=>$e->getMessage()],403);}

catch(Throwable $e){error_log($e->getMessage());json_resp(['success'=>false,'message'=>'Unable to load assigned deployment sites.'],500);}

