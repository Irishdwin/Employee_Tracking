<?php
require_once __DIR__ . '/../Common/session_init.php';
date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../Db_connection/db_connect.php';
require_once __DIR__ . '/../Helpers/work_site_geofence.php';
require_once __DIR__ . '/../Helpers/work_site_registration_policy.php';
function respond(bool $success,array $data=[],int $status=200): void {
    http_response_code($status); echo json_encode(array_merge(['success'=>$success,'registration_policy'=>work_site_registration_policy()],$data)); exit;
}
if (!isset($_SESSION['user_id'],$_SESSION['role'])) respond(false,['message'=>'User session expired.'],401);
if (!in_array($_SESSION['role'],['TeamLead','Team Lead'],true)) respond(false,['message'=>'Only an assigned Team Lead can load work sites.'],403);
if ($_SERVER['REQUEST_METHOD']!=='GET') respond(false,['message'=>'GET is required.'],405);
$requestedCompany=filter_var($_GET['site_id']??0,FILTER_VALIDATE_INT);
if ($requestedCompany===false || $requestedCompany<0) respond(false,['message'=>'Invalid work site selection.'],400);
try {
    $userId=(int)$_SESSION['user_id'];
    $companies=work_site_companies_for_team_lead($conn,$userId);
    if (!$companies && $requestedCompany) respond(false,['message'=>'This site is not assigned to you.'],403);
    if (!$companies) respond(true,['companies'=>[],'requires_selection'=>false,'company_id'=>null,'latitude'=>null,'longitude'=>null,'message'=>'No current official work sites are assigned to you.']);
    if (!$requestedCompany && count($companies)>1) respond(true,['companies'=>$companies,'requires_selection'=>true,'latitude'=>null,'longitude'=>null,'message'=>'Select the intended work site.']);
    $companyId=work_site_company_for_team_lead($conn,$userId,(int)$requestedCompany);
    $row=site_for_lead($conn,$userId,$companyId);
    $row['dep_registered_latitude']=$row['registered_latitude'];$row['dep_registered_longitude']=$row['registered_longitude'];
    $coords=geofence_pick_coordinates($row,'dep');
    respond(true,['companies'=>$companies,'requires_selection'=>false,'site_id'=>$companyId,'company_id'=>(int)$row['CompanyID'],'company_name'=>$row['CompanyName'],'site_name'=>$row['Name'],'address'=>$row['Address'],'site_registered'=>$coords!==null,'latitude'=>$coords['latitude']??null,'longitude'=>$coords['longitude']??null,'geofence_radius'=>(int)($row['geofence_radius']?:100),'site_registered_at'=>$row['site_registered_at']]);
} catch (DomainException $e) {
    respond(false,['message'=>$e->getMessage()],403);
} catch (Throwable $e) {
    error_log('Team Lead work site: '.$e->getMessage());
    respond(false,['message'=>'Unable to load registered work site.'],500);
}
