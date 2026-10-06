<?php
require_once __DIR__ . '/../Common/session_init.php';
date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../Db_connection/db_connect.php';
require_once __DIR__ . '/../Helpers/work_site_geofence.php';
require_once __DIR__ . '/../Helpers/work_site_registration_policy.php';
function respond(bool $success, string $message, int $status=200): void {
    http_response_code($status);
    echo json_encode(['success'=>$success,'message'=>$message]); exit;
}
if (!isset($_SESSION['user_id'],$_SESSION['role'])) respond(false,'User session expired. Please log in again.',401);
if (!in_array($_SESSION['role'],['TeamLead','Team Lead'],true)) respond(false,'Only an assigned Team Lead can register a work site.',403);
if ($_SERVER['REQUEST_METHOD']!=='POST') respond(false,'POST is required.',405);
$data=json_decode(file_get_contents('php://input'),true);
if (!is_array($data)) respond(false,'Invalid JSON payload received.',400);
$latitude=filter_var($data['latitude']??null,FILTER_VALIDATE_FLOAT);
$longitude=filter_var($data['longitude']??null,FILTER_VALIDATE_FLOAT);
$accuracy=filter_var($data['accuracy']??null,FILTER_VALIDATE_FLOAT);
$radius=filter_var($data['geofence_radius']??100,FILTER_VALIDATE_INT);
$requestedCompany=filter_var($data['site_id']??null,FILTER_VALIDATE_INT);
if (!geofence_coords_are_valid($latitude,$longitude)) respond(false,'Valid GPS coordinates were not provided.',400);
if ($radius===false || $radius<30 || $radius>1000) respond(false,'Radius must be between 30 and 1000 meters.',400);
if (!$requestedCompany || $requestedCompany<1) respond(false,'Select the intended work site before registration.',400);
if (!work_site_registration_accuracy_allowed($accuracy)) respond(false,'Registration requires GPS accuracy of ±'.work_site_registration_policy()['max_accuracy_meters'].' meters or better, independently of the geofence radius. Retry GPS at the official work site.',422);
try {
    $userId=(int)$_SESSION['user_id'];
    // CompanyID is only a selection; server authorization resolves its ownership.
    $companyId=work_site_company_for_team_lead($conn,$userId,(int)$requestedCompany);
    $stmt=$conn->prepare('UPDATE deployment_sites SET registered_latitude=?,registered_longitude=?,geofence_radius=?,site_registered_at=NOW(),registered_by_tl_id=? WHERE SiteID=? AND TeamLeadID=? AND IsActive=1');
    $stmt->bind_param('ddiiii',$latitude,$longitude,$radius,$userId,$companyId,$userId);
    $stmt->execute(); $stmt->close();
    respond(true,'Work site location registered successfully! Allowed radius: '.$radius.'m.');
} catch (DomainException $e) {
    respond(false,$e->getMessage(),403);
} catch (Throwable $e) {
    error_log('Work site registration: '.$e->getMessage());
    respond(false,'Unable to save work-site coordinates. Please retry.',500);
}
