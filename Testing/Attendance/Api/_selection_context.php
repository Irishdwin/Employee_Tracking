<?php
declare(strict_types=1);
require_once __DIR__.'/../../../Api/Helpers/work_site_geofence.php';
function testing_context_fail(string $message): void {
 http_response_code(422); echo json_encode(['success'=>false,'message'=>$message]); exit;
}
function testing_site(mysqli $conn,array $input): array {
 $company=(int)($input['company_id']??0);$site=(int)($input['site_id']??0);
 if (!$company || !$site) testing_context_fail('Select a company and deployment site.');
 $row=site_query($conn,'SELECT s.* FROM deployment_sites s JOIN clientcompany c ON c.CompanyID=s.CompanyID AND c.IsActive=1 WHERE s.SiteID=? AND s.CompanyID=? AND s.IsActive=1','ii',[$site,$company])->fetch_assoc();
 if (!$row) testing_context_fail('The selected site is not active in this company.'); return $row;
}
function testing_lead_site(mysqli $conn,array $input): array {
 $site=testing_site($conn,$input);
 try {return site_for_lead($conn,(int)($input['team_lead_id']??0),(int)$site['SiteID']);}
 catch(DomainException $e){testing_context_fail('The selected Team Lead is not assigned to this active site.');}
}
function testing_employee_context(mysqli $conn,array $input,int $employee,?string $date=null): array {
 testing_lead_site($conn,$input);
 $dep=site_owned_employee($conn,(int)$input['team_lead_id'],$employee,(int)$input['site_id']);
 if (!$dep || (int)$dep['CompanyID']!==(int)$input['company_id'] || (int)$dep['DeploymentID']!==(int)($input['deployment_id']??0)) testing_context_fail('Employee/deployment does not belong to the selected active company, site and Team Lead.');
 if ($date && ($date<$dep['StartDate'] || (!empty($dep['EndDate']) && $date>$dep['EndDate']))) testing_context_fail('The test date is outside the selected deployment.');
 if ($date) {
  $dated=site_query($conn,'SELECT DeploymentID FROM deployment WHERE EmployeeID=? AND StartDate<=? AND (EndDate IS NULL OR EndDate>=?) ORDER BY StartDate DESC,DeploymentID DESC LIMIT 1','iss',[$employee,$date,$date])->fetch_assoc();
  if ((int)($dated['DeploymentID']??0)!==(int)$dep['DeploymentID']) testing_context_fail('The production schedule resolves a different deployment for this date. Choose a date in the selected context.');
 }
 return $dep;
}
