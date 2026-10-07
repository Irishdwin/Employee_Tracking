<?php
declare(strict_types=1);
require_once __DIR__.'/../Common/session_init.php';
require __DIR__.'/../Db_connection/db_connect.php';
require_once __DIR__.'/../Helpers/payroll_status_service.php';
header('Content-Type: application/json; charset=utf-8');
try {
    payroll_require_accounting($conn);
    if($_SERVER['REQUEST_METHOD']!=='GET') {http_response_code(405);exit;}
    $code=trim((string)($_GET['run_id'] ?? ''));
    $run=payroll_query($conn,'SELECT r.*,c.Name client_name,r.PayrollTypeSnapshot PayrollType,u.FullName created_by FROM payroll_runs r JOIN clientcompany c ON c.CompanyID=r.CompanyID LEFT JOIN users u ON u.UsersID=r.CreatedBy WHERE r.RunCode=? OR r.BillingRefNo=?','ss',[$code,$code])->get_result()->fetch_assoc();
    if(!$run) throw new RuntimeException('Payroll run not found.',404);
    $members=payroll_query($conn,'SELECT p.*,e.EmployeeNumber,CONCAT(e.FirstName,\' \',e.LastName) EmployeeName,d.Location,ds.SiteID,m.SnapshotJson FROM payroll p JOIN employee e ON e.EmployeeID=p.EmployeeID JOIN dtr_submissions ds ON ds.DTR_SubmissionID=p.DTR_SubmissionID AND ds.EmployeeID=p.EmployeeID JOIN deployment d ON d.DeploymentID=ds.DeploymentID AND d.EmployeeID=p.EmployeeID LEFT JOIN payroll_run_members m ON m.PayrollRunID=? AND m.Payroll_ID=p.Payroll_ID WHERE d.CompanyID=? AND p.PayPeriodStart=? AND p.PayPeriodEnd=? AND ds.CutoffStartDate=p.PayPeriodStart AND ds.CutoffEndDate=p.PayPeriodEnd AND ( ?=0 OR ds.SiteID=?) AND (?=0 OR ds.SiteSupervisorID=?) ORDER BY e.LastName,e.FirstName,p.Payroll_ID','iissiiii',[(int)$run['PayrollRunID'],(int)$run['CompanyID'],$run['PeriodStart'],$run['PeriodEnd'],(int)$run['SiteID'],(int)$run['SiteID'],(int)$run['SiteSupervisorID'],(int)$run['SiteSupervisorID']])->get_result()->fetch_all(MYSQLI_ASSOC);
    $employees=[];$timeline=[];$states=[];$totals=['regular_hours'=>0.0,'ot_hours'=>0.0,'gross'=>0.0,'deductions'=>0.0,'net'=>0.0];$sites=[];
    foreach($members as $row) {
        $snap=$row['SnapshotJson'] ? json_decode($row['SnapshotJson'],true) : null;
        $addback=payroll_query($conn,"SELECT COALESCE(SUM(Amount),0) amount FROM payroll_adjustments WHERE Payroll_ID=? AND AdjustmentType='Add Back' AND IsAddition=1",'i',[(int)$row['Payroll_ID']])->get_result()->fetch_assoc();
        $employees[]=['employee_id'=>(int)$row['EmployeeID'],'payroll_id'=>(int)$row['Payroll_ID'],'name'=>$snap['employee_name']??$row['EmployeeName'],'employee_number'=>$row['EmployeeNumber'],'site'=>$snap['area_site']??$row['Location'],'regular_hours'=>(float)$row['RegularHours'],'ot_hours'=>(float)$row['OvertimeHours'],'holiday_hours'=>isset($snap['holiday_duty_hrs'],$snap['special_holiday_hrs']) ? (float)$snap['holiday_duty_hrs']+(float)$snap['special_holiday_hrs'] : null,'basic'=>(float)$row['BasicSalary'],'ot'=>(float)$row['OvertimePay'],'holiday'=>(float)$row['HolidayPay'],'allowances'=>(float)$row['Allowances'],'add_back'=>isset($snap['addback'])?(float)$snap['addback']:(float)$addback['amount'],'deductions'=>(float)$row['TotalDeductions'],'gross'=>(float)$row['GrossPay'],'net'=>(float)$row['NetPay'],'payment_date'=>$row['PaymentDate'],'processed_by'=>$row['ProcessedBy']];
        foreach(['regular_hours'=>'RegularHours','ot_hours'=>'OvertimeHours','gross'=>'GrossPay','deductions'=>'TotalDeductions','net'=>'NetPay'] as $k=>$field)$totals[$k]=round($totals[$k]+(float)$row[$field],2);
        $sites[(int)$row['SiteID']]=$row['Location'];
        $resolved=payroll_status_for_dtr($conn,(int)$row['DTR_SubmissionID']);
        if($resolved['current'])$states[$resolved['current']['status']]=true;
        foreach($resolved['history'] as $entry)$timeline[$entry['source'].'-'.$entry['id']]=$entry;
    }
    $timeline=array_values($timeline);usort($timeline,static fn($a,$b)=>strcmp($a['created_at'],$b['created_at']) ?: ($a['id']<=>$b['id']));
    if(count($states)===1)$run['CurrentStatus']=array_key_first($states);
    elseif(count($states)>1)$run['CurrentStatus']='Mixed employee statuses';
    $exports=payroll_query($conn,'SELECT ExportID,CreatedAt,CreatedBy FROM payroll_billing_exports WHERE PayrollRunID=? ORDER BY ExportID','i',[(int)$run['PayrollRunID']])->get_result()->fetch_all(MYSQLI_ASSOC);
    $revisions=payroll_query($conn,'SELECT r.RevisionID,r.CreatedAt,r.ConfirmedAt,r.ChangedEmployees,r.Reason,u.FullName imported_by FROM payroll_billing_revisions r LEFT JOIN users u ON u.UsersID=r.CreatedBy WHERE r.PayrollRunID=? AND r.ConfirmedAt IS NOT NULL ORDER BY r.RevisionID','i',[(int)$run['PayrollRunID']])->get_result()->fetch_all(MYSQLI_ASSOC);
    $billing=null;
    if($exports) {
        $snapshot=payroll_query($conn,'SELECT SnapshotJson FROM payroll_billing_exports WHERE ExportID=?','i',[(int)$exports[count($exports)-1]['ExportID']])->get_result()->fetch_assoc();
        $snapshot=json_decode($snapshot['SnapshotJson'],true);$billing=['agency_fee'=>0.0,'vat'=>0.0,'total_invoice'=>(float)$run['TotalInvoice']];
        foreach($snapshot['rows'] as $r) {$billing['agency_fee']+=(float)$r['admin_overhead_amount'];$billing['vat']+=(float)$r['vat_on_overhead'];}
        // Confirmed revisions update the member component snapshot.
        $current=payroll_query($conn,'SELECT SnapshotJson FROM payroll_run_members WHERE PayrollRunID=?','i',[(int)$run['PayrollRunID']])->get_result()->fetch_all(MYSQLI_ASSOC);
        if(count($current)===count($snapshot['rows'])) {
            $billing['agency_fee']=0.0;$billing['vat']=0.0;
            foreach($current as $m) {$r=json_decode($m['SnapshotJson'],true);$billing['agency_fee']+=(float)$r['admin_overhead_amount'];$billing['vat']+=(float)$r['vat_on_overhead'];}
        }
    }
    echo json_encode(['success'=>true,'run'=>$run,'employees'=>$employees,'employee_count'=>count($employees),'sites'=>array_values($sites),'totals'=>$totals,'billing'=>$billing,'history'=>$timeline,'exports'=>$exports,'revisions'=>$revisions,'legacy'=>!count($exports)]);
} catch(Throwable $e) {payroll_api_error($e);}
