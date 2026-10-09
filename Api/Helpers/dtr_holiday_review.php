<?php
declare(strict_types=1);
/** Documentary decisions use the existing append-only audit structures, not attendance edits. */
function dtr_holiday_context(mysqli $db, PDO $pdo, int $employee, string $holiday): array {
    $schedule=payroll_resolve_deployment_schedule($db,$employee,$holiday);
    $holidays=holidays_fetch_for_range($db,$holiday,$holiday,$employee,$schedule['deployment_id']??null)[$holiday]??[];
    $previous=null;$cursor=new DateTimeImmutable($holiday);
    for($i=0;$i<14;$i++){
        $cursor=$cursor->modify('-1 day');$date=$cursor->format('Y-m-d');$s=payroll_resolve_deployment_schedule($db,$employee,$date);
        $h=holidays_fetch_for_range($db,$date,$date,$employee,$s['deployment_id']??null)[$date]??[];
        $nonWorking=false;foreach($h as $item)if(in_array($item['type'],['Regular Holiday','Special Non-working Day'],true))$nonWorking=true;
        if(!$nonWorking && payroll_is_scheduled_day($date,payroll_scheduled_weekdays($s['working_days']))){$previous=$date;break;}
    }
    $attendance=null;$leaves=[];$corrections=[];
    if($previous){
        $q=$pdo->prepare('SELECT * FROM daily_time_records WHERE employee_id=? AND date=?');$q->execute([$employee,$previous]);$attendance=$q->fetch(PDO::FETCH_ASSOC)?:null;
        $q=$pdo->prepare("SELECT l.LeaveID,l.LeaveType,l.StartDate,l.EndDate,l.ApprovedBy,l.ApprovalDate,l.AffectsPayroll,l.Reason FROM leaves l JOIN status s ON s.StatusID=l.StatusID AND s.Name='Approved' WHERE l.EmployeeID=? AND l.StartDate<=? AND l.EndDate>=? ORDER BY l.LeaveID");$q->execute([$employee,$previous,$previous]);$leaves=$q->fetchAll(PDO::FETCH_ASSOC);
        $q=$pdo->prepare("SELECT r.RequestID,r.DutyStart,r.DutyEnd,r.ApprovedAmount,r.ReviewedBy,r.ReviewedAt FROM add_back_requests r JOIN status s ON s.StatusID=r.StatusID AND s.Name='Approved' WHERE r.EmployeeID=? AND r.WorkDate=? ORDER BY r.RequestID");$q->execute([$employee,$previous]);$corrections=$q->fetchAll(PDO::FETCH_ASSOC);
    }
    $context=['employee_id'=>$employee,'deployment_id'=>(int)($schedule['deployment_id']??0),'holiday_date'=>$holiday,'holidays'=>$holidays,'preceding_workday'=>$previous,'attendance'=>$attendance,'approved_leaves'=>$leaves,'approved_corrections'=>$corrections,'schedule'=>$schedule];
    $context['fingerprint']=hash('sha256',json_encode($context,JSON_THROW_ON_ERROR));
    return $context;
}
function dtr_holiday_decision(PDO $pdo,array $context): ?array {
    $ref=$context['employee_id'].':'.$context['deployment_id'].':'.$context['holiday_date'];
    $q=$pdo->prepare("SELECT description,user_id,created_at FROM system_logs WHERE module='Holiday eligibility' AND action='Review' AND reference_id=? AND role IN ('Accounting','Admin') ORDER BY log_id DESC LIMIT 1");$q->execute([$ref]);$row=$q->fetch(PDO::FETCH_ASSOC);
    if(!$row)return null;$value=json_decode($row['description'],true);
    if(!is_array($value)||($value['version']??0)!==1||!hash_equals($context['fingerprint'],(string)($value['fingerprint']??'')))return null;
    return $value+['reviewer_id'=>$row['user_id'],'reviewed_at'=>$row['created_at']];
}
function dtr_holiday_reviews(mysqli $db,PDO $pdo,array $sub): array {
    $out=[];$hireDate=payroll_employee_hired_date($db,(int)$sub['EmployeeID']);$all=holidays_fetch_for_range($db,$sub['CutoffStartDate'],$sub['CutoffEndDate'],(int)$sub['EmployeeID'],(int)$sub['DeploymentID']);
    foreach($all as $date=>$holidays){
        if($hireDate && $date<$hireDate)continue;
        if(!array_filter($holidays,static fn($h)=>$h['type']==='Regular Holiday'))continue;
        $q=$pdo->prepare('SELECT time_in,time_out FROM daily_time_records WHERE employee_id=? AND date=?');$q->execute([$sub['EmployeeID'],$date]);$row=$q->fetch(PDO::FETCH_ASSOC);
        if(!empty($row['time_in'])||!empty($row['time_out']))continue;
        $context=dtr_holiday_context($db,$pdo,(int)$sub['EmployeeID'],$date);
        if($hireDate && $context['preceding_workday'] && $context['preceding_workday']<$hireDate)continue;
        $context['decision']=dtr_holiday_decision($pdo,$context);$out[]=$context;
    }
    return $out;
}
