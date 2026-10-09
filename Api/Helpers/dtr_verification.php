<?php
declare(strict_types=1);
require_once __DIR__.'/client_billing_helper.php';
require_once __DIR__.'/AuditLogger.php';
require_once __DIR__.'/employee_payroll_rates.php';
require_once __DIR__.'/dtr_classification.php';
function dtr_verification_targets(mysqli $db,array $body): array {
    $lead=(int)($body['tl_id']??0);$site=(int)($body['site_id']??0);$employee=(int)($body['employee_id']??0);$id=(int)($body['submission_id']??0);
    $start=(string)($body['cutoff_start']??'');$end=(string)($body['cutoff_end']??'');
    foreach([$start,$end] as $date){$d=DateTimeImmutable::createFromFormat('!Y-m-d',$date);if(!$d||$d->format('Y-m-d')!==$date)throw new RuntimeException('Valid cutoff dates required.',400);}
    if($lead<=0||$start>$end||strtotime($end)-strtotime($start)>31*86400|| (isset($body['employee_id'])&&$employee<=0))throw new RuntimeException('Invalid DTR identity or cutoff.',400);
    $q=$db->prepare('SELECT ds.*,d.CompanyID,e.daily_rate,e.hourly_rate,e.BasicSalary,e.allowance,e.monthly_salary_credit FROM dtr_submissions ds JOIN deployment d ON d.DeploymentID=ds.DeploymentID AND d.EmployeeID=ds.EmployeeID AND d.SiteSuperVisorID=ds.SiteSupervisorID AND (d.SiteID=ds.SiteID OR (d.SiteID IS NULL AND ds.SiteID IS NULL)) JOIN employee e ON e.EmployeeID=ds.EmployeeID WHERE ds.SubmittedBy IS NOT NULL AND ds.SiteSupervisorID=? AND ds.CutoffStartDate=? AND ds.CutoffEndDate=? AND (?=0 OR ds.SiteID=?) AND (?=0 OR ds.EmployeeID=?) AND (?=0 OR ds.DTR_SubmissionID=?) ORDER BY ds.DTR_SubmissionID FOR UPDATE');
    $q->bind_param('issiiiiii',$lead,$start,$end,$site,$site,$employee,$employee,$id,$id);$q->execute();$rows=$q->get_result()->fetch_all(MYSQLI_ASSOC);
    if(!$rows)throw new RuntimeException('No submitted DTR matches this employee, site and cutoff.',404);
    if($employee && count($rows)!==1)throw new RuntimeException('Select the exact submitted DTR and deployment site.',409);
    if(count(array_unique(array_column($rows,'CompanyID')))>1)throw new RuntimeException('Select one client payroll group.',409);
    if(!$site && count(array_unique(array_column($rows,'SiteID')))>1)throw new RuntimeException('Select one deployment site.',409);
    return $rows;
}
function dtr_verification_unlocked(mysqli $db,array $row): void {
    $q=$db->prepare('SELECT EmployeeID FROM employee WHERE EmployeeID=? FOR UPDATE');$q->bind_param('i',$row['EmployeeID']);$q->execute();$q->get_result()->free();$q->close();
    $q=$db->prepare('SELECT Payroll_ID FROM payroll WHERE EmployeeID=? AND PayPeriodStart=? AND PayPeriodEnd=? LIMIT 1 FOR UPDATE');$q->bind_param('iss',$row['EmployeeID'],$row['CutoffStartDate'],$row['CutoffEndDate']);$q->execute();
    if($q->get_result()->num_rows)throw new RuntimeException('Saved payroll cannot be changed by DTR verification or holiday review.',409);
    $q=$db->prepare("SELECT PayrollRunID FROM payroll_runs WHERE CompanyID=? AND PeriodStart=? AND PeriodEnd=? AND (ApprovedAt IS NOT NULL OR LOWER(CurrentStatus) IN ('approved','released','finalized','payroll released','client approved')) LIMIT 1 FOR UPDATE");$q->bind_param('iss',$row['CompanyID'],$row['CutoffStartDate'],$row['CutoffEndDate']);$q->execute();
    if($q->get_result()->num_rows)throw new RuntimeException('This payroll cutoff is locked.',409);
}
