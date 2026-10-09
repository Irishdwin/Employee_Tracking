<?php
declare(strict_types=1);
require_once __DIR__.'/client_billing_helper.php';
require_once __DIR__.'/billing_revision_helper.php';
require_once __DIR__.'/add_back_payroll.php';
require_once __DIR__.'/../Common/NotificationHelper.php';
function payroll_batch_review(mysqli $db,int $lead,string $start,string $end,int $site): array {
 if($site===0){$scope=payroll_query($db,'SELECT DISTINCT SiteID FROM dtr_submissions WHERE SiteSupervisorID=? AND CutoffStartDate=? AND CutoffEndDate=?','iss',[$lead,$start,$end])->get_result()->fetch_all(MYSQLI_ASSOC);if(count($scope)===1)$site=(int)$scope[0]['SiteID'];}
 $runs=payroll_query($db,'SELECT * FROM payroll_runs WHERE SiteSupervisorID=? AND PeriodStart=? AND PeriodEnd=? AND (?=0 OR SiteID=?) ORDER BY PayrollRunID DESC','issii',[$lead,$start,$end,$site,$site])->get_result()->fetch_all(MYSQLI_ASSOC);
 if($site>0){$preferred=array_values(array_filter($runs,static fn($r)=>$r['RunCode']===client_billing_run_code($start,$end,(int)$r['CompanyID'],$lead,$site)));if(count($preferred)===1)$runs=$preferred;}
 if(count($runs)!==1)return ['computed'=>false,'exported'=>false,'can_finalize'=>false,'message'=>'Compute payroll for this deployment site first.'];
 $run=$runs[0];
 $compute=payroll_query($db,"SELECT * FROM payroll_status_history WHERE PayrollRunID=? AND Status='Payroll Computed' AND Remarks LIKE 'Computed from DTR submission batch%' ORDER BY HistoryID DESC LIMIT 1",'i',[(int)$run['PayrollRunID']])->get_result()->fetch_assoc();
 $baseline=0;if($compute&&preg_match('/export_after_id=(\d+)/',$compute['Remarks'],$m))$baseline=(int)$m[1];
 $export=$compute?payroll_query($db,'SELECT ExportID,CreatedAt,SnapshotJson FROM payroll_billing_exports WHERE PayrollRunID=? AND CreatedAt>=? AND ExportID>? ORDER BY ExportID DESC LIMIT 1','isi',[(int)$run['PayrollRunID'],substr($compute['UpdatedAt'],0,19),$baseline])->get_result()->fetch_assoc():null;
 $subs=payroll_query($db,'SELECT ds.DTR_SubmissionID,ds.EmployeeID,ds.ReviewedBy,ds.ReviewDate,p.Payroll_ID,s.Name,p.PayslipGenerated FROM dtr_submissions ds JOIN deployment d ON d.DeploymentID=ds.DeploymentID AND d.EmployeeID=ds.EmployeeID LEFT JOIN payroll p ON p.DTR_SubmissionID=ds.DTR_SubmissionID AND p.EmployeeID=ds.EmployeeID AND p.PayPeriodStart=ds.CutoffStartDate AND p.PayPeriodEnd=ds.CutoffEndDate LEFT JOIN status s ON s.StatusID=p.StatusID WHERE d.CompanyID=? AND ds.SiteSupervisorID=? AND ds.SiteID=? AND ds.CutoffStartDate=? AND ds.CutoffEndDate=?','iiiss',[(int)$run['CompanyID'],$lead,$site,$start,$end])->get_result()->fetch_all(MYSQLI_ASSOC);
 $verified=(bool)$subs;$finalized=(bool)$subs;$final=[];foreach($subs as $sub){$verified=$verified&&!empty($sub['ReviewedBy'])&&!empty($sub['ReviewDate']);$finalized=$finalized&&$sub['Name']==='Finalized'&&!empty($sub['PayslipGenerated']);if($sub['Name']==='Finalized')$final[]=['employee_id'=>(int)$sub['EmployeeID'],'payroll_id'=>(int)$sub['Payroll_ID'],'already_finalized'=>true];}
 return ['finalized'=>$finalized,'finalized_payrolls'=>$final,'can_compute'=>$verified&&!$finalized,'computed'=>(bool)$compute,'exported'=>(bool)$export,'can_finalize'=>(bool)$export&&$verified&&!$finalized,'run_id'=>$run['RunCode'],'payroll_run_id'=>(int)$run['PayrollRunID'],'export_id'=>$export?(int)$export['ExportID']:null,'message'=>$finalized?'Payroll is already finalized. Employee payslips are available in Payroll History.':(!$verified?'Verify every DTR before finalizing.':(!$compute?'Compute payroll first.':(!$export?'Download and review the billing export before finalizing.':'Review the billing export, then finalize to publish employee payslips.'))),'snapshot'=>$export?json_decode($export['SnapshotJson'],true,512,JSON_THROW_ON_ERROR):null];
}
function payroll_finalize_reviewed(mysqli $db,int $actor,int $lead,string $start,string $end,int $site,int $exportId,bool $reviewed): array {
 if(!$reviewed)throw new RuntimeException('Confirm that you reviewed the billing export before finalizing.',409);
 $state=payroll_batch_review($db,$lead,$start,$end,$site);
 if(!empty($state['finalized']))return $state['finalized_payrolls'];
 if(!$state['can_finalize'])throw new RuntimeException($state['message'],409);
 if($exportId!==$state['export_id'])throw new RuntimeException('The billing export changed. Reload this batch and review the latest export.',409);
 $snapshot=$state['snapshot'];$run=$snapshot['run'];$rows=$snapshot['rows'];
 if(!$rows)throw new RuntimeException('The billing export contains no employees.',409);
 $status=payroll_query($db,"SELECT StatusID FROM status WHERE Name='Finalized' LIMIT 1")->get_result()->fetch_assoc();if(!$status)throw new RuntimeException('Finalized payroll status is not configured.');
 $db->begin_transaction();try {
  payroll_query($db,'SELECT PayrollRunID FROM payroll_runs WHERE PayrollRunID=? FOR UPDATE','i',[$state['payroll_run_id']]);
  $current=payroll_batch_review($db,$lead,$start,$end,$site);if(!empty($current['finalized'])){$db->commit();return $current['finalized_payrolls'];}if(!$current['can_finalize'])throw new RuntimeException($current['message'],409);if($current['export_id']!==$exportId)throw new RuntimeException('Review the latest billing export before finalizing.',409);
  $subs=payroll_query($db,'SELECT DTR_SubmissionID,EmployeeID,DeploymentID,SiteID,ReviewedBy,ReviewDate FROM dtr_submissions WHERE SiteSupervisorID=? AND CutoffStartDate=? AND CutoffEndDate=? AND SiteID=? FOR UPDATE','issi',[$lead,$start,$end,(int)$run['SiteID']])->get_result()->fetch_all(MYSQLI_ASSOC);
  $expected=[];foreach($subs as $sub){if(!$sub['ReviewedBy']||!$sub['ReviewDate'])throw new RuntimeException('Verify every DTR before finalizing.',409);$expected[(int)$sub['EmployeeID']]=$sub;}
  $ids=array_map('intval',array_column($rows,'employee_db_id'));$actual=array_keys($expected);sort($ids);sort($actual);if($ids!==$actual)throw new RuntimeException('The employee roster changed. Compute and review a new billing export.',409);
  $final=[];usort($rows,static fn($a,$b)=>(int)$a['employee_db_id']<=>(int)$b['employee_db_id']);
  foreach($rows as $row){
   $eid=(int)$row['employee_db_id'];$sub=$expected[$eid];if((int)$row['dtr_submission_id']!==(int)$sub['DTR_SubmissionID']||(int)$row['site_id']!==(int)$sub['SiteID'])throw new RuntimeException('Billing export DTR or site mismatch.',409);
   payroll_query($db,'SELECT EmployeeID FROM employee WHERE EmployeeID=? FOR UPDATE','i',[$eid]);
   $existing=payroll_query($db,'SELECT Payroll_ID,DTR_SubmissionID,StatusID FROM payroll WHERE EmployeeID=? AND PayPeriodStart=? AND PayPeriodEnd=? FOR UPDATE','iss',[$eid,$start,$end])->get_result()->fetch_assoc();
   if($existing && ((int)$existing['StatusID']!==(int)$status['StatusID']||(int)$existing['DTR_SubmissionID']!==(int)$sub['DTR_SubmissionID']))throw new RuntimeException('An existing payroll conflicts with this DTR. Resolve it before finalizing.',409);
   if($existing){$final[]=['employee_id'=>$eid,'payroll_id'=>(int)$existing['Payroll_ID'],'already_finalized'=>true];continue;}
   $number=static function(string $key)use($row):float{$v=$row[$key]??0;if(!is_numeric($v)||!is_finite((float)$v))throw new RuntimeException('Invalid billing amount.');return round((float)$v,2);};
   $ot=$number('regular_ot_amount')+($number('sunday_ot_amount')-$number('sunday_allowance'))+$number('holiday_ot_amount')+$number('special_holiday_ot_amount');
   $hours=$number('regular_ot_hours')+$number('sunday_ot_hours')+$number('holiday_ot_hrs')+$number('special_holiday_ot_hours');
   $phil=$number('philhealth_ee')+$number('additional_philhealth_ee');$pagibig=$number('pagibig_ee')+$number('additional_pagibig_ee')+$number('other_pagibig_ee');
   $values=[$eid,$start,$end,$number('days_worked'),$number('days_worked')*8,$number('days_worked')*8,$hours,$number('basic_total'),$ot,$number('holiday_duty')+$number('special_holiday_pay'),($number('allowances')+$number('sunday_allowance')),$number('gross_payroll'),$number('sss_ee'),$phil,$pagibig,$number('total_ee_deductions'),$number('net_salary'),payroll_kinsenas_period($start)['payday'],$actor,(int)$sub['DTR_SubmissionID'],(int)$status['StatusID']];
   payroll_query($db,'INSERT INTO payroll(EmployeeID,PayPeriodStart,PayPeriodEnd,TotalDaysWorked,TotalHoursWorked,RegularHours,OvertimeHours,BasicSalary,OvertimePay,HolidayPay,Allowances,GrossPay,SSSDeduction,PhilHealthDeduction,PagIBIGDeduction,TotalDeductions,NetPay,PaymentDate,ProcessedBy,DTR_SubmissionID,StatusID,PayslipGenerated,ProcessedDate,CreatedAt) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,NOW(),NOW())','iss'.str_repeat('d',14).'siii',$values);
   $pid=(int)$db->insert_id;add_back_post_payroll($db,$pid,$eid,(int)$sub['DeploymentID'],$start,$end);
   $row['payroll_id']=$pid;$p=payroll_query($db,'SELECT * FROM payroll WHERE Payroll_ID=?','i',[$pid])->get_result()->fetch_assoc();$row['payroll_snapshot']=$p;$row['payroll_fingerprint']=billing_fingerprint($p);
   payroll_query($db,'INSERT INTO payroll_run_members(PayrollRunID,EmployeeID,Payroll_ID,DTR_SubmissionID,SiteID,SnapshotJson) VALUES(?,?,?,?,?,?)','iiiiis',[$state['payroll_run_id'],$eid,$pid,(int)$sub['DTR_SubmissionID'],(int)$sub['SiteID'],billing_json($row)]);
   payroll_query($db,'INSERT INTO payroll_audit_log(Payroll_ID,Action,PerformedBy,NewValue) VALUES(?,?,?,?)','isis',[$pid,'Finalized',$actor,billing_json(['export_id'=>$exportId,'net_pay'=>$number('net_salary'),'billing_reviewed'=>true])]);
   $users=payroll_query($db,'SELECT u.UsersID FROM users u JOIN employee e ON e.Email=u.Email WHERE e.EmployeeID=? AND u.IsActive=1','i',[$eid])->get_result();while($user=$users->fetch_assoc())if(!insert_system_notification($db,(int)$user['UsersID'],'payroll_finalized','Your payslip is available','Accounting has reviewed and finalized your payroll for '.$start.' to '.$end.'. Open Payroll History to view your payslip.','payroll',$pid))throw new RuntimeException('Unable to notify the employee.');
   $final[]=['employee_id'=>$eid,'payroll_id'=>$pid,'net_pay'=>$number('net_salary')];
  }
  $db->commit();return $final;
 }catch(Throwable $e){$db->rollback();throw $e;}
}
