<?php
declare(strict_types=1);
require_once __DIR__.'/../../../Api/Helpers/work_site_geofence.php';
function testing_context_fail(string $message): void {
 http_response_code(422); echo json_encode(['success'=>false,'message'=>$message]); exit;
}
function testing_site(mysqli $conn,array $input): array {
 $company=(int)($input['company_id']??0);$site=(int)($input['site_id']??0);
 if (!$company || !$site) testing_context_fail('Select a company and deployment site.');
 $historical=($input['history']??'')==='1' || (!empty($input['date']) && $input['date']<date('Y-m-d'));
 $active=$historical ? ' AND EXISTS(SELECT 1 FROM deployment d WHERE d.SiteID=s.SiteID AND d.CompanyID=s.CompanyID)' : ' AND c.IsActive=1 AND s.IsActive=1';
 $row=site_query($conn,'SELECT s.* FROM deployment_sites s JOIN clientcompany c ON c.CompanyID=s.CompanyID WHERE s.SiteID=? AND s.CompanyID=?'.$active,'ii',[$site,$company])->fetch_assoc();
 if (!$row) testing_context_fail('The selected site is not active in this company.'); return $row;
}
function testing_lead_site(mysqli $conn,array $input): array {
 $site=testing_site($conn,$input);
 if(($input['history']??'')==='1' || (!empty($input['date']) && $input['date']<date('Y-m-d'))){
  $row=site_query($conn,"SELECT s.*,c.Name CompanyName,c.PayrollType,u.FullName TeamLeadName FROM deployment_sites s JOIN clientcompany c ON c.CompanyID=s.CompanyID JOIN users u ON u.UsersID=s.TeamLeadID JOIN roles r ON r.RolesID=u.RoleID AND r.RoleName IN ('TeamLead','Team Lead') WHERE s.SiteID=? AND s.TeamLeadID=?",'ii',[(int)$site['SiteID'],(int)($input['team_lead_id']??0)])->fetch_assoc();
  if(!$row)testing_context_fail('The selected Team Lead does not own this historical site.');return $row;
 }
 try {return site_for_lead($conn,(int)($input['team_lead_id']??0),(int)$site['SiteID']);}
 catch(DomainException $e){testing_context_fail('The selected Team Lead is not assigned to this active site.');}
}
/** Same date ordering as the production schedule resolver; never extend a deployment. */
function testing_dated_deployment(mysqli $conn,int $employee,string $date): array {
 $parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
 if(!$parsed || $parsed->format('Y-m-d')!==$date) testing_context_fail('Select a valid test date.');
 $dep=site_query($conn,'SELECT d.* FROM deployment d JOIN employee e ON e.EmployeeID=d.EmployeeID WHERE d.EmployeeID=? AND d.StartDate<=? AND (d.EndDate IS NULL OR d.EndDate>=?) ORDER BY d.StartDate DESC,d.DeploymentID DESC LIMIT 1','iss',[$employee,$date,$date])->fetch_assoc();
 if(!$dep)testing_context_fail('No deployment was found for this employee on the selected date. Please choose a date within an existing deployment period.');
 if($date>=date('Y-m-d') && !site_query($conn,'SELECT EmployeeID FROM employee WHERE EmployeeID=? AND IsActive=1','i',[$employee])->fetch_assoc())testing_context_fail('This employee is inactive. Only covered historical deployment dates may be tested.');
 return $dep;
}
function testing_employee_context(mysqli $conn,array $input,int $employee,?string $date=null): array {
 if($date!==null)$input['date']=$date;
 testing_lead_site($conn,$input);
 $dep=$date ? testing_dated_deployment($conn,$employee,$date) : site_owned_employee($conn,(int)$input['team_lead_id'],$employee,(int)$input['site_id']);
 if (!$dep || (int)$dep['SiteID']!==(int)$input['site_id'] || (int)$dep['CompanyID']!==(int)$input['company_id'] || (int)$dep['DeploymentID']!==(int)($input['deployment_id']??0)) testing_context_fail('The selected context does not match the deployment effective on this date. Refresh the employee/date selection.');
 return $dep;
}
/** Period locks also protect dates without an existing attendance row. */
function testing_assert_unlocked(mysqli $conn,int $employee,string $date): void {
 $submitted=site_query($conn,'SELECT DTR_SubmissionID FROM dtr_submissions WHERE EmployeeID=? AND ? BETWEEN CutoffStartDate AND CutoffEndDate LIMIT 1','is',[$employee,$date])->fetch_assoc();
 $published=site_query($conn,"SELECT p.Payroll_ID FROM payroll p LEFT JOIN status s ON s.StatusID=p.StatusID WHERE p.EmployeeID=? AND ? BETWEEN p.PayPeriodStart AND p.PayPeriodEnd AND (p.PayslipGenerated=1 OR LOWER(s.Name) IN ('finalized','approved','released','payroll released','paid')) LIMIT 1",'is',[$employee,$date])->fetch_assoc();
 $run=site_query($conn,"SELECT r.PayrollRunID FROM payroll_runs r JOIN deployment d ON d.CompanyID=r.CompanyID AND d.EmployeeID=? AND d.StartDate<=? AND (d.EndDate IS NULL OR d.EndDate>=?) WHERE ? BETWEEN r.PeriodStart AND r.PeriodEnd AND (r.SiteID IS NULL OR r.SiteID=d.SiteID) AND (r.ApprovedAt IS NOT NULL OR LOWER(r.CurrentStatus) IN ('approved','exported','released','finalized','client approved','payroll released') OR LOWER(r.Status) IN ('approved','exported','released','finalized','client approved','payroll released')) LIMIT 1",'isss',[$employee,$date,$date,$date])->fetch_assoc();
 if($submitted||$published||$run)throw new DomainException('This date belongs to a submitted DTR or approved/finalized payroll period. Use the authorized Add Back/correction workflow or an isolated test fixture; historical testing cannot reopen or change this period.',409);
}
