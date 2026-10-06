<?php
declare(strict_types=1);
require_once __DIR__.'/attendance_evidence.php';
require_once __DIR__.'/employee_payroll_helper.php';
require_once __DIR__.'/employee_payroll_rates.php';
require_once __DIR__.'/AuditLogger.php';

function add_back_status(PDO $pdo,string $name): int {
    $q=$pdo->prepare('SELECT StatusID FROM status WHERE Name=?'); $q->execute([$name]);
    $id=$q->fetchColumn(); if (!$id) throw new RuntimeException('Required status is missing.'); return (int)$id;
}
function add_back_date(string $date): string {
    $d=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
    if (!$d || $d->format('Y-m-d')!==$date) throw new DomainException('A valid work date is required.',400);
    return $date;
}
/** The affected deployment is resolved by date, never supplied by the browser. */
function add_back_source(PDO $pdo,mysqli $conn,int $employee,string $date): array {
    add_back_date($date);
    $q=$pdo->prepare('SELECT d.*,c.Name CompanyName FROM deployment d JOIN clientcompany c ON c.CompanyID=d.CompanyID WHERE d.EmployeeID=? AND (d.StartDate IS NULL OR d.StartDate<=?) AND (d.EndDate IS NULL OR d.EndDate>=?) ORDER BY d.StartDate DESC,d.DeploymentID DESC');
    $q->execute([$employee,$date,$date]); $dep=$q->fetch();
    if (!$dep) throw new DomainException('No deployment covers this work date.',422);
    $q=$pdo->prepare('SELECT * FROM attendance WHERE EmployeeID=? AND Date=? ORDER BY AttendanceID DESC LIMIT 1'); $q->execute([$employee,$date]); $att=$q->fetch()?:null;
    if ($att && !empty($att['EvidenceDeploymentID']) && (int)$att['EvidenceDeploymentID']!==(int)$dep['DeploymentID']) throw new DomainException('Attendance deployment requires Accounting verification.',409);
    $q=$pdo->prepare('SELECT * FROM daily_time_records WHERE employee_id=? AND date=?'); $q->execute([$employee,$date]); $dtr=$q->fetch()?:null;
    $schedule=payroll_resolve_deployment_schedule($conn,$employee,$date);
    if (!empty($schedule['deployment_id']) && (int)$schedule['deployment_id']!==(int)$dep['DeploymentID']) throw new DomainException('Ambiguous deployment context.',409);
    $hoursSchedule=$schedule; $hoursSchedule['working_days']='Mon,Tue,Wed,Thu,Fri,Sat,Sun';
    $meta=payroll_timesheet_day_status($dtr,$date,$hoursSchedule,date('Y-m-d'));
    $q=$pdo->prepare('SELECT DTR_SubmissionID FROM dtr_submissions WHERE EmployeeID=? AND ? BETWEEN CutoffStartDate AND CutoffEndDate ORDER BY DTR_SubmissionID'); $q->execute([$employee,$date]);
    return ['deployment'=>$dep,'attendance'=>$att,'dtr'=>$dtr,'schedule'=>$schedule,
        'original_hours'=>round((float)($meta['total_hours']??0),2),'dtr_submissions'=>$q->fetchAll(PDO::FETCH_COLUMN),
        'holidays'=>holidays_fetch_for_range($conn,$date,$date)];
}
/** Compare only relevant immutable source values, not incidental update timestamps. */
function add_back_fingerprint(array $source): string {
    return hash('sha256',json_encode([$source['deployment'],$source['attendance'],$source['dtr'],$source['schedule'],$source['holidays']],JSON_UNESCAPED_UNICODE));
}
function add_back_rates(PDO $pdo,array $employee,int $deployment,int $company,string $date): ?array {
    // Explicit rate fields avoid interpreting the previously unused BasicRate/RateType.
    $q=$pdo->prepare('SELECT SalaryRatesID,DailyRate,HourlyRate FROM salary_rates WHERE EmployeeID=? AND DeploymentID=? AND CompanyID=? AND EffectiveDate<=? AND (EndDate IS NULL OR EndDate>=?) AND DailyRate>0 AND HourlyRate>0 ORDER BY EffectiveDate DESC,SalaryRatesID DESC LIMIT 1');
    $q->execute([$employee['EmployeeID'],$deployment,$company,$date,$date]); $history=$q->fetch();
    if ($history) return ['daily_rate'=>(float)$history['DailyRate'],'hourly_rate'=>(float)$history['HourlyRate'],'source'=>'salary_rates #'.$history['SalaryRatesID'],'effective_dated'=>true,'work_date'=>$date];
    // An authoritative current snapshot proves today's rate only, not a past rate.
    if ($date!==date('Y-m-d')) return null;
    $rate=payroll_employee_rates($employee);
    return $rate['valid'] ? $rate+['source'=>$rate['daily_rate_source'],'work_date'=>$date] : null;
}
function add_back_duty(array $source,string $start,string $end): array {
    $date=$source['attendance']['Date']??$source['dtr']['date']??null;
    $start=str_replace('T',' ',$start); $end=str_replace('T',' ',$end);
    foreach ([$start,$end] as $stamp) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/',$stamp) || strtotime($stamp)===false) throw new DomainException('Actual duty start and end are required.',400);
    }
    $seconds=strtotime($end)-strtotime($start);
    if ($seconds<=0 || $seconds>86400 || substr($start,0,10)>date('Y-m-d') || strtotime($end)>time()) throw new DomainException('Duty must be completed, with a duration of at most 24 hours.',422);
    if ($date && substr($start,0,10)!==$date) throw new DomainException('Duty must start on the work date.',422);
    $original=$source['dtr'];
    if (!empty($original['time_in']) && strtotime($start)>strtotime($original['time_in'])) throw new DomainException('Actual duty must include the original clock in.',422);
    if (!empty($original['time_out']) && strtotime($end)<strtotime($original['time_out'])) throw new DomainException('Actual duty must include the original clock out.',422);
    $row=['date'=>substr($start,0,10),'time_in'=>date('Y-m-d H:i:s',strtotime($start)),
        'time_out'=>date('Y-m-d H:i:s',strtotime($end)),'break_time'=>$original['break_time']??null,
        'total_hours_rendered'=>$seconds/3600,'status'=>'Clocked Out'];
    $hoursSchedule=$source['schedule']; $hoursSchedule['working_days']='Mon,Tue,Wed,Thu,Fri,Sat,Sun';
    $meta=payroll_timesheet_day_status($row,$row['date'],$hoursSchedule,date('Y-m-d'));
    $hours=round((float)($meta['total_hours']??0)-(float)$source['original_hours'],2);
    if ($hours<=0) throw new DomainException('Actual duty does not restore any missing hours.',422);
    return ['row'=>$row,'hours'=>$hours];
}
function add_back_notify(PDO $pdo,int $user,string $type,string $title,string $message,int $id): void {
    $q=$pdo->prepare("INSERT INTO notifications(UserID,NotificationType,Title,Message,RelateedModule,RelatedRecord,IsRead,Priority) VALUES(?,?,?,?, 'add_back',?,0,'Normal')");
    $q->execute([$user,$type,$title,$message,$id]);
}
function add_back_submit(PDO $pdo,mysqli $conn,int $user,array $body): int {
    foreach (['approved_amount','amount','rate','employee_id','company_id','team_lead_id'] as $key) if (isset($body[$key])) throw new DomainException('Employee, rate and approved amount are controlled by the server.',400);
    $date=add_back_date((string)($body['work_date']??''));
    if ($date>date('Y-m-d')) throw new DomainException('Future duty cannot be requested.',422);
    $reason=trim((string)($body['reason']??'')); if (strlen($reason)<10 || strlen($reason)>2000) throw new DomainException('Explain the missing duty in 10â€“2,000 characters.',422);
    $pdo->beginTransaction(); $copies=[];
    try {
        $employee=evidence_employee($pdo,$user); $emp=(int)$employee['EmployeeID'];
        // Serializes all requests/reviews/payroll for this employee, preventing overlap races.
        $q=$pdo->prepare('SELECT EmployeeID FROM employee WHERE EmployeeID=? FOR UPDATE'); $q->execute([$emp]);
        $source=add_back_source($pdo,$conn,$emp,$date);
        $q=$pdo->prepare("SELECT r.RequestID FROM add_back_requests r JOIN status s ON s.StatusID=r.StatusID WHERE r.EmployeeID=? AND r.WorkDate=? AND s.Name IN ('Pending','Approved') LIMIT 1"); $q->execute([$emp,$date]);
        if ($q->fetch()) throw new DomainException('A pending or approved correction already covers this day. Accounting must resolve it before another request.',409);
        $duty=add_back_duty($source,(string)($body['duty_start']??''),(string)($body['duty_end']??''));
        if ($duty['row']['date']!==$date) throw new DomainException('Duty must start on the selected work date.',422);
        $photos=[];
        foreach (['clockin','clockout'] as $kind) {
            $ref=$source['attendance'][$kind.'_photo']??null;
            if ($ref) {
                try { $photos[$kind]=evidence_file($ref); }
                catch (DomainException $e) {
                    // A lost clock photo must not discard another valid recorded photo.
                    if ($e->getCode()!==404) throw $e;
                }
            }
        }
        if (!$photos) throw new DomainException('No recorded clock photo is available. Ask Accounting to verify the evidence source before requesting Add Back.',422);
        $dep=$source['deployment']; $rates=add_back_rates($pdo,$employee,(int)$dep['DeploymentID'],(int)$dep['CompanyID'],$date);
        $q=$pdo->prepare('INSERT INTO add_back_requests(EmployeeID,SubmittedBy,DeploymentID,CompanyID,AttendanceID,WorkDate,DutyStart,DutyEnd,RequestedHours,OriginalHours,Reason,SourceSnapshot,RateSnapshot,StatusID) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $q->execute([$emp,$user,$dep['DeploymentID'],$dep['CompanyID'],$source['attendance']['AttendanceID']??null,$date,$duty['row']['time_in'],$duty['row']['time_out'],$duty['hours'],$source['original_hours'],$reason,json_encode($source),$rates?json_encode($rates):null,add_back_status($pdo,'Pending')]); $id=(int)$pdo->lastInsertId();
        foreach ($photos as $kind=>$file) {
            $name=bin2hex(random_bytes(24)).($file['mime']==='image/png'?'.png':'.jpg');
            $path=__DIR__.'/../../uploads/add_back_evidence/'.$name;
            if (!copy($file['path'],$path)) throw new RuntimeException('Could not preserve evidence.'); $copies[]=$path;
            $q=$pdo->prepare('INSERT INTO add_back_evidence(RequestID,EvidenceType,StorageName,MimeType,ByteSize,Sha256,CapturedAt) VALUES(?,?,?,?,?,?,?)');
            $q->execute([$id,$kind,$name,$file['mime'],$file['size'],$file['sha256'],$source['attendance'][$kind==='clockin'?'TimeIn':'TimeOut']??null]);
        }
        $users=$pdo->query("SELECT u.UsersID FROM users u JOIN roles r ON r.RolesID=u.RoleID WHERE r.RoleName='Accounting'")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($users as $to) add_back_notify($pdo,(int)$to,'add_back_submitted','Add Back needs verification','Request #'.$id.' for '.$date.' restores '.$duty['hours'].' duty hours.',$id);
        if (!AuditLogger::log($pdo,'Add Back','Submitted','Missing duty request submitted', (string)$id)) throw new RuntimeException('Audit write failed.');
        $pdo->commit(); return $id;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack(); foreach ($copies as $path) unlink($path); throw $e;
    }
}
/** Default is the current, or next unfinalized cutoff; original deployment must still cover it. */
function add_back_target(PDO $pdo,array $request,?string $reference=null): array {
    $today=payroll_kinsenas_period(date('Y-m-d'));
    $period=payroll_kinsenas_period($reference?add_back_date($reference):date('Y-m-d'));
    if ($period['start']<$today['start']) throw new DomainException('An historical payroll cannot receive a new correction.',422);
    for ($i=0;$i<12;$i++) {
        $q=$pdo->prepare('SELECT Payroll_ID FROM payroll WHERE EmployeeID=? AND PayPeriodStart=? AND PayPeriodEnd=? LIMIT 1'); $q->execute([$request['EmployeeID'],$period['start'],$period['end']]);
        $existing=$q->fetchColumn();
        $q=$pdo->prepare("SELECT PayrollRunID FROM payroll_runs WHERE CompanyID=? AND PeriodStart=? AND PeriodEnd=? AND (ApprovedAt IS NOT NULL OR LOWER(CurrentStatus) IN ('approved','exported','released','finalized','client approved','payroll released') OR LOWER(Status) IN ('approved','exported','released','finalized','client approved','payroll released'))");
        $q->execute([$request['CompanyID'],$period['start'],$period['end']]);
        if (!$existing && !$q->fetch()) break;
        if ($reference) throw new DomainException('This payroll is already finalized or released. Select an open cutoff.',409);
        $period=payroll_kinsenas_period(date('Y-m-d',strtotime($period['end'].' +1 day')));
    }
    $q=$pdo->prepare('SELECT DeploymentID FROM deployment WHERE DeploymentID=? AND EmployeeID=? AND CompanyID=? AND (StartDate IS NULL OR StartDate<=?) AND (EndDate IS NULL OR EndDate>=?)');
    $q->execute([$request['DeploymentID'],$request['EmployeeID'],$request['CompanyID'],$period['end'],$period['start']]);
    if (!$q->fetch()) throw new DomainException('The original deployment does not cover an open payment cutoff. Accounting must resolve the payment context.',422);
    if ($i===12) throw new DomainException('No eligible open payroll cutoff found.',409);
    return $period;
}
function add_back_quote(PDO $pdo,mysqli $conn,array $request): array {
    $source=json_decode($request['SourceSnapshot'],true);
    $current=add_back_source($pdo,$conn,(int)$request['EmployeeID'],$request['WorkDate']);
    if (!hash_equals(add_back_fingerprint($source),add_back_fingerprint($current))) throw new DomainException('Attendance, schedule or holiday context changed. Reject and request a fresh review.',409);
    $rates=$request['RateSnapshot']?json_decode($request['RateSnapshot'],true):null;
    if (!$rates) {
        $q=$pdo->prepare('SELECT * FROM employee WHERE EmployeeID=?'); $q->execute([$request['EmployeeID']]);
        $rates=add_back_rates($pdo,$q->fetch(),(int)$request['DeploymentID'],(int)$request['CompanyID'],$request['WorkDate']);
    }
    if (!$rates) throw new DomainException('A verified effective-dated DailyRate and HourlyRate is required in salary_rates for this historical work date. Approval is blocked until that rate is recorded.',422);
    $duty=add_back_duty($source,$request['DutyStart'],$request['DutyEnd']);
    $p=payroll_kinsenas_period($request['WorkDate']);
    $args=[$conn,$pdo,(int)$request['EmployeeID'],(float)$rates['daily_rate'],(float)$rates['hourly_rate'],0.0,0.0,$p['start'],$p['end'],null,null,(int)$request['DeploymentID']];
    // Full-cutoff before/after preserves holiday eligibility and approved-OT rules.
    $before=payroll_compute_period(...array_merge($args,[null,false]));
    $after=payroll_compute_period(...array_merge($args,[[$request['WorkDate']=>$duty['row']],false]));
    $amount=round((float)$after['gross_pay']-(float)$before['gross_pay'],2);
    if ($amount<=0 || !is_finite($amount)) throw new DomainException('The production engine does not identify omitted payable compensation. Verify the schedule and approved overtime records.',422);
    return ['amount'=>$amount,'hours'=>$duty['hours'],'rates'=>$rates,'before'=>$before,'after'=>$after,'calculation'=>'Production payroll cutoff difference; original records unchanged'];
}
function add_back_review(PDO $pdo,mysqli $conn,int $user,string $role,int $id,array $body): array {
    if (!in_array($role,['Accounting','Admin'],true)) throw new DomainException('Only Accounting or Admin can review Add Back.',403);
    if (isset($body['amount']) || isset($body['approved_amount']) || isset($body['rate'])) throw new DomainException('Approved compensation is calculated by the server.',400);
    $decision=(string)($body['decision']??''); $remarks=trim((string)($body['remarks']??''));
    if (!in_array($decision,['approve','reject'],true)) throw new DomainException('Approve or reject is required.',400);
    if (strlen($remarks)>2000 || ($decision==='reject' && strlen($remarks)<5)) throw new DomainException('A rejection reason of at least 5 characters is required.',422);
    $q=$pdo->prepare('SELECT EmployeeID FROM add_back_requests WHERE RequestID=?'); $q->execute([$id]); $emp=$q->fetchColumn(); if (!$emp) throw new DomainException('Request not found.',404);
    $pdo->beginTransaction();
    try {
        $q=$pdo->prepare('SELECT EmployeeID FROM employee WHERE EmployeeID=? FOR UPDATE'); $q->execute([$emp]);
        $q=$pdo->prepare('SELECT r.*,s.Name StatusName FROM add_back_requests r JOIN status s ON s.StatusID=r.StatusID WHERE r.RequestID=? FOR UPDATE'); $q->execute([$id]); $r=$q->fetch();
        if ((int)$r['SubmittedBy']===$user) throw new DomainException('You cannot approve your own request.',403);
        if ($r['StatusName']!=='Pending') throw new DomainException('This request has already been reviewed.',409);
        $quote=null; $period=null;
        if ($decision==='approve') {
            $q=$pdo->prepare('SELECT * FROM add_back_evidence WHERE RequestID=?'); $q->execute([$id]); $evidence=$q->fetchAll();
            if (!$evidence) throw new DomainException('Recorded evidence is required.',422);
            foreach ($evidence as $ev) if (!hash_equals($ev['Sha256'],evidence_file($ev['StorageName'],'add_back_evidence')['sha256'])) throw new DomainException('Evidence integrity check failed.',409);
            $quote=add_back_quote($pdo,$conn,$r); $period=add_back_target($pdo,$r,$body['target_date']??null);
            $q=$pdo->prepare("INSERT INTO payroll_adjustments(Payroll_ID,EmployeeID,AdjustmentType,Amount,IsAddition,Reason,ApprovedBy,CreatedBy,AddBackRequestID) VALUES(NULL,?,'Add Back',?,1,?,?,?,?)");
            $q->execute([$r['EmployeeID'],$quote['amount'],'Add Back #'.$id.': '.$r['Reason'],$user,$user,$id]);
        }
        $q=$pdo->prepare('UPDATE add_back_requests SET StatusID=?,ReviewedBy=?,ReviewedAt=NOW(),ReviewRemarks=?,ApprovedHours=?,ApprovedAmount=?,CalculationSnapshot=?,RateSnapshot=COALESCE(?,RateSnapshot),TargetStart=?,TargetEnd=? WHERE RequestID=?');
        $q->execute([add_back_status($pdo,$decision==='approve'?'Approved':'Rejected'),$user,$remarks,$quote['hours']??null,$quote['amount']??null,$quote?json_encode($quote):null,$quote?json_encode($quote['rates']):null,$period['start']??null,$period['end']??null,$id]);
        add_back_notify($pdo,(int)$r['SubmittedBy'],'add_back_'.$decision,'Add Back '.($decision==='approve'?'approved':'rejected'),'Request #'.$id.' for '.$r['WorkDate'].'. '.$remarks,$id);
        if (!AuditLogger::log($pdo,'Add Back',ucfirst($decision),'Request #'.$id.'; '.($quote?'approved amount '.$quote['amount']:$remarks),(string)$id)) throw new RuntimeException('Audit write failed.');
        $pdo->commit(); return ['amount'=>$quote['amount']??null,'target'=>$period];
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}
