<?php
/** Request-specific reads/writes. No runtime schema changes; all writes use mysqli. */
require_once __DIR__.'/../Common/NotificationHelper.php';
require_once __DIR__.'/AuditLogger.php';
require_once __DIR__.'/job_categories_lookup.php';
require_once __DIR__.'/job_hierarchy.php';
require_once __DIR__.'/work_site_geofence.php';

function jorw_rows(mysqli $db, string $sql, string $types='', array $params=[]): array {
    $stmt=$db->prepare($sql);
    if ($types!=='') $stmt->bind_param($types,...$params);
    $stmt->execute(); $rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close(); return $rows;
}
function jorw_write(mysqli $db, string $sql, string $types, array $params): int {
    $stmt=$db->prepare($sql); if ($types!=='') $stmt->bind_param($types,...$params); $stmt->execute();
    $count=$stmt->affected_rows; $stmt->close(); return $count;
}
function jorw_active_user(mysqli $db, int $uid, array $roles): bool {
    $row=jorw_rows($db,'SELECT r.RoleName FROM users u JOIN roles r ON r.RolesID=u.RoleID WHERE u.UsersID=? AND u.IsActive=1','i',[$uid]);
    return $row && in_array($row[0]['RoleName'],$roles,true);
}
function jorw_companies(mysqli $db, int $uid): array {
    return jorw_rows($db,"SELECT c.CompanyID company_id,c.Name company_name,c.Address location FROM clientcompany c WHERE c.IsActive=1 AND EXISTS(SELECT 1 FROM deployment_sites s WHERE s.CompanyID=c.CompanyID AND s.TeamLeadID=? AND s.IsActive=1) ORDER BY c.Name,c.CompanyID",'i',[$uid]);
}
function jorw_validate_company(mysqli $db, int $uid, int $companyId): void {
    $assigned=false;
    foreach (jorw_companies($db,$uid) as $company) if ((int)$company['company_id']===$companyId) $assigned=true;
    if (!$assigned) throw new DomainException('You can only request job orders for active companies currently assigned to you.',403);
    $registered=false;
    foreach(sites_for_lead($db,$uid) as $site)if((int)$site['CompanyID']===$companyId && is_numeric($site['registered_latitude']) && is_numeric($site['registered_longitude']))$registered=true;
    if(!$registered)throw new DomainException('Please register an assigned work site before creating a job order.',403);
    if (!jorw_rows($db,"SELECT ServiceAgreementID FROM serviceagreement WHERE ClientCompanyID=? AND Status='Active' AND COALESCE(NULLIF(TRIM(SignedDocumentPath),''),'')<>'' LIMIT 1",'i',[$companyId])) throw new DomainException('Your company needs an active service agreement with uploaded document before requesting a job order.',403);
}
/** Same existing legacy category mappings, using read-only queries in write transactions. */
function jorw_category(mysqli $db, int $id, string $name, string $position): ?array {
    if ($id>0) {
        $rows=jorw_rows($db,'SELECT id,category_name FROM job_categories WHERE id=?','i',[$id]);
        if ($rows) return $rows[0];
    }
    $name=job_category_sanitize_name($name); $map=job_category_legacy_name_map();
    $name=$map[strtolower($name)] ?? $name;
    if ($name!=='') {
        $rows=jorw_rows($db,'SELECT id,category_name FROM job_categories WHERE LOWER(category_name)=LOWER(?) LIMIT 1','s',[$name]);
        if ($rows) return $rows[0];
    }
    foreach ([$name,$position] as $text) foreach ($map as $needle=>$category) {
        if (strpos(strtolower($text),$needle)!==false) {
            $rows=jorw_rows($db,'SELECT id,category_name FROM job_categories WHERE LOWER(category_name)=LOWER(?) LIMIT 1','s',[$category]);
            if ($rows) return $rows[0];
        }
    }
    return null;
}
function jorw_notify_submitted(mysqli $db,int $requestId): void {
    $row=jorw_rows($db,'SELECT r.Position,c.Name,u.FullName FROM job_order_requests r JOIN clientcompany c ON c.CompanyID=r.CompanyID JOIN users u ON u.UsersID=r.RequestedBy WHERE r.RequestID=?','i',[$requestId])[0];
    foreach (jorw_rows($db,"SELECT u.UsersID FROM users u JOIN roles ro ON ro.RolesID=u.RoleID WHERE u.IsActive=1 AND ro.RoleName IN ('HR','Admin')") as $recipient) {
        if (!insert_system_notification($db,(int)$recipient['UsersID'],'job_order_request','New Job Order Request',$row['FullName'].' requested '.$row['Position'].' for '.$row['Name'].'.','job_order_request',$requestId)) throw new RuntimeException('Request notification could not be saved.');
    }
    if (!AuditLogger::log($db,'JobOrder','Submit Request','Submitted Job Order Request #'.$requestId,(string)$requestId)) throw new RuntimeException('Request audit event could not be saved.');
}
/** Stored IDs are authoritative; reviewers may only complete missing legacy fields. */
function jorw_classification_choice(array $stored,array $review): array {
    $choice=[];
    foreach(['category_id','classification_id','sub_classification_id'] as $key){
        $saved=jh_id($stored[$key]??null);
        $sent=jh_id($review[$key]??null);
        if($saved&&$sent&&$saved!==$sent)throw new DomainException('Submitted request classification cannot be changed while posting.',400);
        $choice[$key]=$saved?:$sent;
    }
    return $choice;
}
function jorw_decide(mysqli $db,int $requestId,int $uid,string $action,string $reason='',array $review=[]): array {
    if (!in_array($action,['approve','reject'],true)) throw new DomainException('Invalid action.',400);
    if ($action==='reject' && trim($reason)==='') throw new DomainException('A reason is required to reject this request.',400);
    $db->begin_transaction();
    try {
        $rows=jorw_rows($db,'SELECT r.*,c.Name CompanyName FROM job_order_requests r JOIN clientcompany c ON c.CompanyID=r.CompanyID WHERE r.RequestID=? FOR UPDATE','i',[$requestId]);
        if (!$rows) throw new DomainException('Job order request not found.',404);
        $request=$rows[0];
        if ($request['Status']!=='Pending' || $request['JobOrderID']!==null) throw new DomainException('Request already processed. Current status: '.$request['Status'].'.',409);
        $orderId=null;
        if ($action==='approve') {
            $agreements=jorw_rows($db,"SELECT ServiceAgreementID FROM serviceagreement WHERE ClientCompanyID=? AND Status='Active' AND COALESCE(NULLIF(TRIM(SignedDocumentPath),''),'')<>'' ORDER BY ContractEndDate DESC,ServiceAgreementID DESC LIMIT 1",'i',[(int)$request['CompanyID']]);
            if (!$agreements) throw new DomainException('Cannot post Job Order: No active Service Agreement with uploaded document exists for this client.',400);
            $details=jorw_rows($db,'SELECT * FROM job_order_details WHERE RequestID=? ORDER BY detail_id FOR UPDATE','i',[$requestId]);
            $selections=[];
            if(isset($review['placements'])&&!is_array($review['placements']))throw new DomainException('Invalid placement classifications.',400);
            foreach(($review['placements']??[]) as $selection)if(!is_array($selection))throw new DomainException('Invalid placement classification.',400);
            foreach(($review['placements']??[]) as $selection){$detailId=jh_id($selection['detail_id']??null);if(isset($selections[$detailId]))throw new DomainException('Duplicate placement classification.',400);$selections[$detailId]=$selection;}
            $hierarchies=[];
            foreach($details as $detail){
                $choice=jorw_classification_choice($detail,$selections[(int)$detail['detail_id']]??[]);
                $hierarchies[(int)$detail['detail_id']]=jh_validate($db,jh_id($choice['category_id']??null),jh_id($choice['classification_id']??null),jh_id($choice['sub_classification_id']??null));
            }
            foreach($selections as $detailId=>$selection)if(!isset($hierarchies[$detailId]))throw new DomainException('Placement does not belong to this request.',400);
            $headerChoice=jorw_classification_choice($request,$review);
            $headerHierarchy=$details?$hierarchies[(int)$details[0]['detail_id']]:jh_validate($db,$headerChoice['category_id'],$headerChoice['classification_id'],$headerChoice['sub_classification_id']);
            $first=$details[0] ?? [];
            $position=$first['position_title'] ?? $request['Position'];
            $count=$details ? array_sum(array_column($details,'required_workers')) : (int)$request['RequiredCount'];
            $location=$details ? (count($details)>1 ? 'Multiple sites' : $first['deployment_site']) : $request['Location'];
            $schedule=$first['work_schedule'] ?? $request['WorkSchedule'];
            $category=['id'=>$headerHierarchy['category_id'],'category_name'=>$headerHierarchy['category_name']];
            if (!$category) throw new DomainException(job_category_submission_alert($position),400);
            if ($count<=0) throw new DomainException('Request must specify workers needed.',400);
            jorw_write($db,"INSERT INTO joborder (CompanyID,ServiceAgreementID,Position,category_id,RequiredCount,Location,WorkSchedule,JobCategory,RequestDate,CreatedBy,ApprovedBy,PublishedDate,Status) VALUES (?,?,?,?,?,?,?,?,CURDATE(),?,?,CURDATE(),'Open')",'iisiisssii',[(int)$request['CompanyID'],(int)$agreements[0]['ServiceAgreementID'],$position,(int)$category['id'],$count,$location,$schedule,$category['category_name'],$uid,$uid]);
            $orderId=(int)$db->insert_id;
            if(!$details)jorw_write($db,'INSERT INTO job_order_details (RequestID,position_title,category_id,job_category,required_workers,deployment_site,work_schedule,classification_id,sub_classification_id,JobOrderID) VALUES (?,?,?,?,?,?,?,?,?,?)','isisissiii',[$requestId,$position,(int)$headerHierarchy['category_id'],$headerHierarchy['category_name'],$count,(string)$location,(string)$schedule,(int)$headerHierarchy['classification_id'],(int)$headerHierarchy['sub_classification_id'],$orderId]);
            jorw_write($db,'UPDATE joborder SET classification_id=?,sub_classification_id=? WHERE JobOrderID=?','iii',[(int)$headerHierarchy['classification_id'],(int)$headerHierarchy['sub_classification_id'],$orderId]);
            foreach($hierarchies as $detailId=>$h)jorw_write($db,'UPDATE job_order_details SET category_id=?,job_category=?,classification_id=?,sub_classification_id=? WHERE detail_id=? AND RequestID=?','isiiii',[(int)$h['category_id'],$h['category_name'],(int)$h['classification_id'],(int)$h['sub_classification_id'],$detailId,$requestId]);
            jorw_write($db,'UPDATE job_order_details SET JobOrderID=? WHERE RequestID=?','ii',[$orderId,$requestId]);
            $changed=jorw_write($db,"UPDATE job_order_requests SET Status='Approved',ProcessedBy=?,ProcessedAt=NOW(),JobOrderID=?,RejectionReason=NULL WHERE RequestID=? AND Status='Pending' AND JobOrderID IS NULL",'iii',[$uid,$orderId,$requestId]);
        } else {
            $changed=jorw_write($db,"UPDATE job_order_requests SET Status='Rejected',ProcessedBy=?,ProcessedAt=NOW(),RejectionReason=? WHERE RequestID=? AND Status='Pending' AND JobOrderID IS NULL",'isi',[$uid,trim($reason),$requestId]);
        }
        if ($changed!==1) throw new DomainException('Request already processed.',409);
        $approved=$action==='approve';
        $title=$approved ? 'Job Order Request Approved' : 'Job Order Request Rejected';
        $message='Your Job Order Request for '.$request['CompanyName'].($approved ? ' has been approved.' : ' has been rejected. Reason: '.trim($reason));
        if (!insert_system_notification($db,(int)$request['RequestedBy'],'job_order_request',$title,$message,$approved ? 'job_order' : 'job_order_request',$orderId ?? $requestId)) throw new RuntimeException('Decision notification could not be saved.');
        if (!AuditLogger::log($db,'JobOrder',$approved ? 'Approve Request' : 'Reject Request','Processed Job Order Request #'.$requestId,(string)$requestId)) throw new RuntimeException('Decision audit event could not be saved.');
        $db->commit();
        return ['success'=>true,'message'=>$approved ? 'Job Order posted successfully.' : 'Request rejected.','job_order_id'=>$orderId];
    } catch (Throwable $e) { $db->rollback(); throw $e; }
}
