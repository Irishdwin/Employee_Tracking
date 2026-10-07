<?php
declare(strict_types=1);
require_once __DIR__.'/payroll_workflow.php';
require_once __DIR__.'/payroll_status_helper.php';
require_once __DIR__.'/../Common/NotificationHelper.php';

function payroll_status_notify(mysqli $db,array $employees,string $status,string $start,string $end,string $module,int $event): int {
    $recipients=[];
    foreach(array_unique(array_map('intval',$employees)) as $employee) {
        $users=payroll_query($db,"SELECT u.UsersID FROM users u JOIN roles r ON r.RolesID=u.RoleID JOIN employee e ON LOWER(TRIM(e.Email))=LOWER(TRIM(u.Email)) WHERE e.EmployeeID=? AND r.RoleName='Employee' AND u.IsActive=1",'i',[$employee])->get_result();
        while($u=$users->fetch_assoc()) $recipients[(int)$u['UsersID']]=true;
    }
    foreach(array_keys($recipients) as $user) {
        if (!insert_system_notification($db,$user,'payroll_status_updated','Payroll Status Updated','Your payroll for '.$start.' to '.$end.' is now "'.$status.'". Open Payroll to view the details.',$module,$event)) throw new RuntimeException('Unable to save employee notification.');
    }
    return count($recipients);
}
function payroll_status_update_group(mysqli $db,int $lead,int $company,string $start,string $end,string $status,string $remarks,?string $proof,int $actor): array {
    if (!in_array($status,payroll_status_allowed_values(),true)) throw new InvalidArgumentException('Invalid payroll status.');
    $db->begin_transaction();
    try {
        $rows=payroll_query($db,'SELECT ds.DTR_SubmissionID,ds.EmployeeID FROM dtr_submissions ds JOIN deployment d ON d.DeploymentID=ds.DeploymentID AND d.EmployeeID=ds.EmployeeID WHERE ds.SiteSupervisorID=? AND d.CompanyID=? AND ds.CutoffStartDate=? AND ds.CutoffEndDate=? ORDER BY ds.DTR_SubmissionID FOR UPDATE','iiss',[$lead,$company,$start,$end])->get_result()->fetch_all(MYSQLI_ASSOC);
        if (!$rows) throw new RuntimeException('Payroll group not found.',404);
        $current=payroll_status_get_current($db,$lead,$company,$start,$end);
        $unchanged=true;
        foreach($rows as $row) {
            $effective=payroll_status_for_dtr($db,(int)$row['DTR_SubmissionID']);
            if(($effective['current']['status'] ?? '')!==$status)$unchanged=false;
        }
        if ($unchanged) {
            $db->commit();
            return ['success'=>true,'changed'=>false,'message'=>'Payroll status is already '.$status.'. No duplicate notifications were sent.'];
        }
        payroll_query($db,'INSERT INTO payroll_group_status_history (SiteSupervisorID,CompanyID,CutoffStartDate,CutoffEndDate,Status,Remarks,ProofImagePath,UpdatedBy) VALUES(?,?,?,?,?,?,?,?)','iisssssi',[$lead,$company,$start,$end,$status,$remarks,$proof,$actor]);
        $id=(int)$db->insert_id;
        $count=payroll_status_notify($db,array_column($rows,'EmployeeID'),$status,$start,$end,'payroll_group_status',$id);
        $db->commit();
        return ['success'=>true,'changed'=>true,'payroll_status_history_id'=>$id,'status'=>$status,'notified_employees'=>$count,'message'=>'Payroll status updated. '.$count.' employee account(s) notified.'];
    } catch(Throwable $e) {$db->rollback();throw $e;}
}
function payroll_status_for_dtr(mysqli $db,int $dtr): array {
    $context=payroll_query($db,'SELECT ds.*,d.CompanyID FROM dtr_submissions ds JOIN deployment d ON d.DeploymentID=ds.DeploymentID AND d.EmployeeID=ds.EmployeeID WHERE ds.DTR_SubmissionID=?','i',[$dtr])->get_result()->fetch_assoc();
    if (!$context) return ['current'=>null,'history'=>[]];
    $history=payroll_status_get_history($db,(int)$context['SiteSupervisorID'],(int)$context['CompanyID'],$context['CutoffStartDate'],$context['CutoffEndDate']);
    foreach($history as &$entry) {$entry['source']='group';$entry['proof_url']=$entry['has_proof'] ? '../../Api/Common/view_payroll_proof.php?id='.$entry['id'] : null;} unset($entry);
    $runs=payroll_query($db,'SELECT h.HistoryID id,h.Status status,h.Remarks remarks,h.UpdatedAt created_at,h.AttachmentPath,u.FullName updated_by FROM payroll_status_history h JOIN payroll_runs r ON r.PayrollRunID=h.PayrollRunID LEFT JOIN users u ON u.UsersID=h.UpdatedBy WHERE r.CompanyID=? AND r.PeriodStart=? AND r.PeriodEnd=? AND (r.SiteSupervisorID IS NULL OR r.SiteSupervisorID=?) AND (r.SiteID IS NULL OR r.SiteID=?)','issii',[(int)$context['CompanyID'],$context['CutoffStartDate'],$context['CutoffEndDate'],(int)$context['SiteSupervisorID'],(int)$context['SiteID']])->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach($runs as $entry) {$entry['source']='run';$entry['has_proof']=!empty($entry['AttachmentPath']);$entry['proof_url']=$entry['has_proof'] ? '../../Api/Common/view_payroll_proof.php?source=run&id='.$entry['id'] : null;unset($entry['AttachmentPath']);$history[]=$entry;}
    usort($history,static fn($a,$b)=>strcmp($a['created_at'],$b['created_at']) ?: strcmp($a['source'],$b['source']) ?: ($a['id']<=>$b['id']));
    return ['current'=>$history ? $history[count($history)-1] : null,'history'=>$history];
}
