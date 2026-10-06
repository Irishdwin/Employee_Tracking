<?php
/** Current application stays compatible with hiring; attempts preserve client decisions. No DDL at runtime. */
require_once __DIR__ . '/job_hierarchy.php';
require_once __DIR__ . '/job_order_wall.php';
require_once __DIR__ . '/jor_multi_position.php';
require_once __DIR__ . '/application_status_helper.php';
require_once __DIR__ . '/notification_helper.php';
require_once __DIR__ . '/../Common/NotificationHelper.php';
require_once __DIR__ . '/AuditLogger.php';

function ae_query(mysqli $db, string $sql, string $types='', array $args=[]): array {
    $s=$db->prepare($sql);
    if ($types!=='') $s->bind_param($types,...$args);
    $s->execute(); $result=$s->get_result();
    $rows=$result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $s->close(); return $rows;
}
function ae_authorize(mysqli $db, int $uid, array $roles): void {
    $user=ae_query($db,'SELECT r.RoleName FROM users u JOIN roles r ON r.RolesID=u.RoleID WHERE u.UsersID=? AND u.IsActive=1','i',[$uid])[0]??[];
    if (!in_array($user['RoleName']??'', $roles,true)) throw new DomainException('Forbidden.',403);
}
function ae_application(mysqli $db,int $id,bool $lock=false): array {
    $row=ae_query($db,"SELECT ja.*,s.Name StatusFromTable,a.FullName ApplicantName,a.UserID ApplicantUserID,
        jo.CompanyID,COALESCE(d.position_title,jo.Position) Position,c.Name CompanyName,
        COALESCE(d.deployment_site,jo.Location) DeploymentSite,
        COALESCE(d.category_id,jo.category_id) category_id,
        COALESCE(d.classification_id,jo.classification_id) classification_id,
        COALESCE(d.sub_classification_id,jo.sub_classification_id) sub_classification_id
        FROM job_applications ja JOIN applicants a ON a.ApplicantID=ja.ApplicantID
        JOIN joborder jo ON jo.JobOrderID=ja.JobOrderID JOIN clientcompany c ON c.CompanyID=jo.CompanyID
        LEFT JOIN status s ON s.StatusID=ja.StatusID
        LEFT JOIN job_order_details d ON d.detail_id=ja.target_detail_id AND d.JobOrderID=ja.JobOrderID
        WHERE ja.ApplicationID=?".($lock?' FOR UPDATE':''),'i',[$id])[0]??[];
    if (!$row) throw new DomainException('Application not found.',404);
    return $row;
}
function ae_history(mysqli $db,int $id): array {
    return ae_query($db,'SELECT ea.*,u.FullName EndorserName,v.FullName ReviewerName FROM application_endorsement_attempts ea LEFT JOIN users u ON u.UsersID=ea.EndorsedBy LEFT JOIN users v ON v.UsersID=ea.DecidedBy WHERE ea.ApplicationID=? ORDER BY ea.AttemptNumber','i',[$id]);
}
function ae_can_reendorse(array $row): bool {
    return ash_resolve_stage($row)==='client_rejected' && !empty($row['EndorsedDate']);
}
function ae_tl_scope(mysqli $db,int $uid,int $company): bool {
    return jor_tl_can_access_company($db,$uid,$company);
}
/** Profile hierarchy first, original/current placement hierarchy for legacy profiles. */
function ae_opportunities(mysqli $db,array $app): array {
    $profile=ae_query($db,'SELECT category_id,classification_id,sub_classification_id,JobCategory job_category,PreferredClassification preferred_classification,PreferredSubClassification preferred_sub_classification FROM applicants WHERE ApplicantID=?','i',[(int)$app['ApplicantID']])[0];
    try { $match=jh_resolve_profile($db,$profile); }
    catch (DomainException $e) {
        $match=array_intersect_key($app,array_flip(['category_id','classification_id','sub_classification_id']));
        if (!empty($match['category_id'])) jh_validate($db,(int)$match['category_id'],(int)$match['classification_id'],(int)$match['sub_classification_id'],false);
    }
    $orders=ae_query($db,"SELECT jo.*,c.Name CompanyName FROM joborder jo JOIN clientcompany c ON c.CompanyID=jo.CompanyID
        JOIN serviceagreement sa ON sa.ServiceAgreementID=jo.ServiceAgreementID AND sa.ClientCompanyID=c.CompanyID
        WHERE jo.Status='Open' AND c.IsActive=1 AND sa.Status='Active'
        AND NULLIF(TRIM(sa.SignedDocumentPath),'') IS NOT NULL
        AND (sa.ContractStartDate IS NULL OR sa.ContractStartDate<=CURDATE())
        AND (sa.ContractType='Open' OR (sa.ContractEndDate IS NOT NULL AND sa.ContractEndDate>=CURDATE()))
        AND EXISTS(SELECT 1 FROM users u JOIN roles r ON r.RolesID=u.RoleID WHERE u.IsActive=1 AND r.RoleName IN ('TeamLead','Team Lead') AND (c.UserID=u.UsersID OR EXISTS(SELECT 1 FROM deployment dp WHERE dp.CompanyID=c.CompanyID AND dp.SiteSuperVisorID=u.UsersID)))
        AND NOT EXISTS(SELECT 1 FROM application_endorsement_attempts ea WHERE ea.ApplicationID=? AND ea.JobOrderID=jo.JobOrderID AND ea.CompanyID=c.CompanyID AND ea.Decision='Rejected')
        AND jo.JobOrderID<>? ORDER BY c.Name,jo.JobOrderID",'ii',[(int)$app['ApplicationID'],(int)$app['JobOrderID']]);
    $out=[];
    foreach ($orders as $order) {
        foreach (job_order_fetch_placement_slots_mysqli($db,(int)$order['JobOrderID'],(int)$order['RequiredCount']) as $slot) {
            if ($slot['is_filled']) continue;
            $h=!empty($slot['detail_id'])?$slot:$order;
            $compatible=true;
            foreach (['category_id','classification_id','sub_classification_id'] as $key) {
                if (!empty($match[$key]) && (int)($h[$key]??0)!==(int)$match[$key]) $compatible=false;
            }
            $position=trim((string)($slot['position_title']?:$order['Position']));
            // Legacy records without a usable hierarchy can only match their reviewed position.
            if (empty($match['category_id']) && strcasecmp($position,trim((string)$app['Position']))!==0) $compatible=false;
            if (!$compatible || $position==='' || $position==='0') continue;
            try { $labels=jh_validate($db,(int)($h['category_id']??0),(int)($h['classification_id']??0),(int)($h['sub_classification_id']??0),false); }
            catch (DomainException $e) { continue; }
            $out[]=['job_order_id'=>(int)$order['JobOrderID'],'target_detail_id'=>(int)$slot['detail_id'],
                'company_id'=>(int)$order['CompanyID'],'company_name'=>$order['CompanyName'],'position'=>$position,
                'location'=>$slot['deployment_site']?:$order['Location'],'work_schedule'=>$slot['work_schedule']?:$order['WorkSchedule'],
                'open_slots'=>(int)$slot['open_slots'],'hierarchy'=>$labels];
        }
    }
    return $out;
}
function ae_notify(mysqli $db,int $uid,string $type,string $title,string $message,int $id): void {
    if (!insert_system_notification($db,$uid,$type,$title,$message,'job_application',$id)) throw new RuntimeException('Notification could not be saved.');
}
function ae_applicant_notify(mysqli $db,int $id,string $type,string $title,string $message): void {
    if (!createApplicationNotification($db,$id,$type,$title,$message)) throw new RuntimeException('Applicant notification could not be saved.');
}
function ae_endorse(mysqli $db,int $uid,int $id,array $input=[]): int {
    ae_authorize($db,$uid,['Admin','HR']);
    $db->begin_transaction();
    try {
        $app=ae_application($db,$id,true); $history=ae_history($db,$id);
        $last=$history?end($history):null; $reassign=!empty($input['job_order_id']);
        if ($reassign) {
            if (!ae_can_reendorse($app) || !$last || $last['Decision']!=='Rejected') throw new DomainException('Only client-rejected applications can be reassigned.',409);
            if ((int)($input['attempt_id']??0)!==(int)$last['AttemptID']) throw new DomainException('The endorsement changed. Reload the application.',409);
            $reason=trim((string)($input['reason']??''));
            if ($reason==='' || strlen($reason)>2000) throw new DomainException('Enter a reassignment reason (maximum 2,000 characters).',400);
            // Serialize selection with other endorsement writers, then recheck current availability.
            ae_query($db,'SELECT JobOrderID FROM joborder WHERE JobOrderID=? FOR UPDATE','i',[(int)$input['job_order_id']]);
            $choice=null;
            foreach (ae_opportunities($db,$app) as $option) if ($option['job_order_id']===(int)$input['job_order_id'] && $option['target_detail_id']===(int)($input['target_detail_id']??0)) $choice=$option;
            if (!$choice) throw new DomainException('This opportunity is no longer eligible. Reload the available opportunities.',409);
            // A previous client's contract marker must not unlock hiring for the new client.
            // Keep uploaded documents and other remarks; the previous attempt snapshot is retained.
            $remarks=preg_replace('/^.*Contract uploaded:.*(?:\r?\n|$)/m','',(string)($app['Remarks']??''));
            ae_query($db,"UPDATE job_applications SET JobOrderID=?,target_detail_id=NULLIF(?,0),ClientApprovalDate=NULL,Remarks=? WHERE ApplicationID=?",'iisi',[$choice['job_order_id'],$choice['target_detail_id'],$remarks,$id]);
            $app=ae_application($db,$id);
        } else {
            $reason=null;
            if ($last || !empty($app['EndorsedDate']) || !in_array(ash_resolve_stage($app),['applied','scheduled_for_interview'],true)) throw new DomainException('This application already has an endorsement or is closed.',409);
            if (empty($app['InterviewDate'])) throw new DomainException('Schedule the applicant interview before endorsement.',409);
        }
        $status=ash_get_or_create_status_id($db,'Endorsed to Client',['Endorsed']);
        if (!$status) throw new RuntimeException('Endorsed status unavailable.');
        ae_query($db,"UPDATE job_applications SET EndorsedDate=NOW(),EndorsedBy=?,ClientApprovalStatus='Pending',ClientApprovalDate=NULL,StatusID=? WHERE ApplicationID=?",'iii',[$uid,$status,$id]);
        $number=$last?(int)$last['AttemptNumber']+1:1;
        ae_query($db,"INSERT INTO application_endorsement_attempts(ApplicationID,AttemptNumber,JobOrderID,CompanyID,TargetDetailID,CompanyName,PositionTitle,DeploymentSite,EndorsedBy,EndorsedAt,ReendorsementReason) VALUES(?,?,?,?,?,?,?,?,?,NOW(),?)",'iiiiisssis',[$id,$number,(int)$app['JobOrderID'],(int)$app['CompanyID'],$app['target_detail_id'],$app['CompanyName'],$app['Position'],$app['DeploymentSite'],$uid,$reason]);
        $attempt=(int)$db->insert_id;
        $recipients=ae_query($db,"SELECT DISTINCT u.UsersID FROM users u JOIN roles r ON r.RolesID=u.RoleID WHERE u.IsActive=1 AND r.RoleName IN ('TeamLead','Team Lead') AND (EXISTS(SELECT 1 FROM clientcompany c WHERE c.CompanyID=? AND c.UserID=u.UsersID) OR EXISTS(SELECT 1 FROM deployment d WHERE d.CompanyID=? AND d.SiteSuperVisorID=u.UsersID))",'ii',[(int)$app['CompanyID'],(int)$app['CompanyID']]);
        if (!$recipients) throw new DomainException('The selected company has no active assigned Team Lead.',409);
        foreach ($recipients as $recipient) ae_notify($db,(int)$recipient['UsersID'],'endorsed_applicant','New Applicant Endorsed',"{$app['ApplicantName']} has been endorsed for {$app['Position']} at {$app['CompanyName']}.",$id);
        ae_applicant_notify($db,$id,'application_endorsed',$reassign?'Re-Endorsed to Client':'Endorsed to Client','Your application has been endorsed to '.$app['CompanyName'].' for client review.');
        if (!AuditLogger::log($db,'Applicant',$reassign?'Re-Endorse':'Endorse',"Application $id endorsed to {$app['CompanyName']} (attempt $number).",(string)$id)) throw new RuntimeException('Audit log could not be saved.');
        $db->commit(); return $attempt;
    } catch (Throwable $e) { $db->rollback(); throw $e; }
}
function ae_decide(mysqli $db,int $uid,int $id,int $attempt,string $decision,string $reason): void {
    ae_authorize($db,$uid,['TeamLead','Team Lead']);
    $reason=trim($reason);
    if (!in_array($decision,['Approved','Rejected'],true) || strlen($reason)>2000 || ($decision==='Rejected' && $reason==='')) throw new DomainException('A rejection requires a reason; remarks must not exceed 2,000 characters.',400);
    $db->begin_transaction();
    try {
        $app=ae_application($db,$id,true);
        if (!ae_tl_scope($db,$uid,(int)$app['CompanyID'])) throw new DomainException('Application is outside your assigned companies.',403);
        $history=ae_history($db,$id); $last=$history?end($history):null;
        if (!$last || (int)$last['AttemptID']!==$attempt || $last['Decision']!=='Pending' || ash_resolve_stage($app)!=='endorsed_to_client') throw new DomainException('This endorsement is no longer pending. Reload the list.',409);
        ae_query($db,'UPDATE application_endorsement_attempts SET Decision=?,DecidedBy=?,DecidedAt=NOW(),DecisionReason=? WHERE AttemptID=?','sisi',[$decision,$uid,$reason,$attempt]);
        ae_query($db,'UPDATE job_applications SET ClientApprovalStatus=?,ClientApprovalDate=NOW() WHERE ApplicationID=?','si',[$decision,$id]);
        if ($decision==='Rejected') {
            foreach (ae_query($db,"SELECT u.UsersID FROM users u JOIN roles r ON r.RolesID=u.RoleID WHERE u.IsActive=1 AND r.RoleName IN ('Admin','HR')") as $recipient) ae_notify($db,(int)$recipient['UsersID'],'client_rejected','Client endorsement rejected',"{$app['ApplicantName']} was rejected by {$app['CompanyName']}. Reason: $reason Review application $id for possible reassignment.",$id);
            ae_applicant_notify($db,$id,'client_rejected','Client Review Unsuccessful','This client review was unsuccessful. HR will review your application for possible reassignment.');
        } else ae_applicant_notify($db,$id,'client_approved','Approved by Company','Your application has been approved by the client company.');
        if (!AuditLogger::log($db,'Applicant','Client '.$decision,"Application $id: {$app['CompanyName']} decision $decision (attempt {$last['AttemptNumber']}).",(string)$id)) throw new RuntimeException('Audit log could not be saved.');
        $db->commit();
    } catch (Throwable $e) { $db->rollback(); throw $e; }
}
