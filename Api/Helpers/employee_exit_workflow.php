<?php
declare(strict_types=1);
require_once __DIR__.'/payroll_workflow.php';
require_once __DIR__.'/employee_payroll_helper.php';
require_once __DIR__.'/AuditLogger.php';
require_once __DIR__.'/../Common/NotificationHelper.php';

function exit_rows(mysqli $db,string $sql,array $args=[]): array {
    return payroll_query($db,$sql,str_repeat('s',count($args)),$args)->get_result()->fetch_all(MYSQLI_ASSOC);
}
function exit_actor(mysqli $db,int $uid,array $allowed): array {
    $actor=exit_rows($db,'SELECT u.UsersID,u.IsActive,u.Email,r.RoleName FROM users u JOIN roles r ON r.RolesID=u.RoleID WHERE u.UsersID=?',[$uid])[0]??null;
    if(!$actor || !$actor['IsActive'] || !in_array($actor['RoleName'],$allowed,true) || ($_SESSION['role']??'')!==$actor['RoleName'])throw new RuntimeException('Exit workflow authorization required.',403);
    return $actor;
}
function exit_status(mysqli $db,string $name): int {
    $row=exit_rows($db,'SELECT StatusID FROM status WHERE Name=? LIMIT 1',[$name])[0]??null;
    if($row)return (int)$row['StatusID'];
    payroll_query($db,'INSERT INTO status(Name) VALUES(?)','s',[$name]);return (int)$db->insert_id;
}
function exit_date(string $date,bool $past=false): string {
    $dt=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
    if(!$dt || $dt->format('Y-m-d')!==$date || ($past && $date>date('Y-m-d')))throw new InvalidArgumentException('Enter a valid'.($past?' past or current':'').' date.');
    return $date;
}
function exit_required(array $input,string $key): string {
    $value=trim((string)($input[$key]??''));
    if($value==='' || strlen($value)>4000)throw new InvalidArgumentException('Provide '.$key.'.');
    return $value;
}
function exit_history(mysqli $db,string $type,int $id,int $actor,string $action,array $details): void {
    payroll_query($db,'INSERT INTO exit_workflow_history(RecordType,RecordID,ActorID,Action,Details) VALUES(?,?,?,?,?)','siiss',[$type,$id,$actor,$action,json_encode($details,JSON_THROW_ON_ERROR)]);
    if(!AuditLogger::log($db,'Employee Exit',$action,json_encode($details,JSON_THROW_ON_ERROR),(string)$id))throw new RuntimeException('Unable to save audit history.');
}
function exit_deployment(mysqli $db,int $employee,string $date): ?array {
    return exit_rows($db,'SELECT d.*,ds.Name SiteName,ds.TeamLeadID,ds.IsActive SiteActive FROM deployment d LEFT JOIN deployment_sites ds ON ds.SiteID=d.SiteID WHERE d.EmployeeID=? AND d.StartDate<=? AND (d.EndDate IS NULL OR d.EndDate>=?) ORDER BY d.StartDate DESC,d.DeploymentID DESC LIMIT 1',[$employee,$date,$date])[0]??null;
}
function exit_tl_owns(mysqli $db,int $uid,int $employee,?int $site=null): bool {
    // Pinned clearance sites retain read/review access after duty ends. General
    // attendance alerts remain scoped to current assignments.
    return (bool)exit_rows($db,'SELECT d.DeploymentID FROM deployment d JOIN deployment_sites ds ON ds.SiteID=d.SiteID WHERE d.EmployeeID=? AND ds.TeamLeadID=? AND ds.IsActive=1 AND (? IS NULL OR ds.SiteID=?) AND d.StartDate<=CURDATE() AND (? IS NOT NULL OR d.EndDate IS NULL OR d.EndDate>=CURDATE())',[$employee,$uid,$site,$site,$site]);
}
function exit_can_view(mysqli $db,array $actor,array $row,array $workflow): bool {
    if(in_array($actor['RoleName'],['HR','Admin','Accounting'],true))return true;
    if($actor['RoleName']==='Employee')return strcasecmp(trim($actor['Email']),trim($row['Email']))===0;
    return exit_tl_owns($db,(int)$actor['UsersID'],(int)$row['EmployeeID'],isset($workflow['site_id'])?(int)$workflow['site_id']:null);
}
function exit_notify(mysqli $db,int $employee,int $record,string $event,string $message,?int $site=null): void {
    $recipients=exit_rows($db,"SELECT u.UsersID FROM users u JOIN roles r ON r.RolesID=u.RoleID WHERE u.IsActive=1 AND (r.RoleName IN ('HR','Admin') OR (r.RoleName='Employee' AND ? NOT LIKE '%awol%' AND u.Email=(SELECT Email FROM employee WHERE EmployeeID=?)) OR (r.RoleName='Accounting' AND ? IN ('verify_clearance','pay_ready','release_pay')))",[$event,$employee,$event]);
    $leads=exit_rows($db,'SELECT DISTINCT ds.TeamLeadID UsersID FROM deployment d JOIN deployment_sites ds ON ds.SiteID=d.SiteID JOIN users u ON u.UsersID=ds.TeamLeadID AND u.IsActive=1 WHERE d.EmployeeID=? AND ds.IsActive=1 AND (? IS NULL OR ds.SiteID=?) AND d.StartDate<=CURDATE() AND (? IS NOT NULL OR d.EndDate IS NULL OR d.EndDate>=CURDATE())',[$employee,$site,$site,$site]);
    foreach(array_unique(array_map('intval',array_column(array_merge($recipients,$leads),'UsersID'))) as $uid)
        if(!insert_system_notification($db,$uid,'employee_exit','Employee exit update',$message,'employee_exit',$record))throw new RuntimeException('Unable to save notification.');
}
function exit_target(mysqli $db,string $accepted,array $days): string {
    $valid=['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
    if(!$days || array_diff($days,$valid))throw new InvalidArgumentException('Confirm the agency working weekdays.');
    $cursor=new DateTimeImmutable($accepted);$count=0;
    for($i=0;$i<730;$i++){
        $cursor=$cursor->modify('+1 day');$date=$cursor->format('Y-m-d');
        $holiday=exit_rows($db,"SELECT HolidayID FROM holidays WHERE HolidayDate=? AND IsNationwide=1 AND HolidayType IN ('Regular Holiday','Special Non-working Day')",[$date]);
        if(in_array($cursor->format('D'),$days,true)&&!$holiday && ++$count===30)return $date;
    }
    throw new InvalidArgumentException('The confirmed calendar cannot yield a target date.');
}
function exit_list(mysqli $db,array $actor,int $id=0): array {
    $rows=exit_rows($db,"SELECT rr.*,CONCAT(e.FirstName,' ',e.LastName) EmployeeName,e.EmployeeNumber,e.Email,COALESCE(s.Name,'Unspecified') Status FROM resignationrequest rr JOIN employee e ON e.EmployeeID=rr.EmployeeID LEFT JOIN status s ON s.StatusID=rr.StatusID WHERE (?=0 OR rr.ResignationID=?) ORDER BY rr.ResignationID DESC",[$id,$id]);$out=[];
    foreach($rows as $row){
        $w=json_decode($row['WorkflowDetails']??'',true)?:['stage'=>'Legacy record — HR review required'];
        if(!exit_can_view($db,$actor,$row,$w))continue;
        if($actor['RoleName']==='Accounting'&&!in_array($w['stage'],['Clearance Complete','Final Pay Processing','Final Pay Ready','Final Pay Released','Resigned','Legacy record — HR review required'],true))continue;
        $history=exit_rows($db,'SELECT h.Action,h.Details,h.CreatedAt,u.FullName Actor FROM exit_workflow_history h JOIN users u ON u.UsersID=h.ActorID WHERE h.RecordType=? AND h.RecordID=? ORDER BY h.HistoryID',['resignation',$row['ResignationID']]);
        $row['history']=$history;$row['workflow']=$w;unset($row['WorkflowDetails'],$row['Email'],$row['ClearanceFormPath'],$row['QuitclaimDocumentPath']);
        if(isset($row['workflow']['documents']))foreach($row['workflow']['documents'] as &$doc)unset($doc['path']);unset($doc);
        $row['WorkingDaysRemaining']=null;
        if(!empty($w['calendar_days'])&&!empty($w['target_date'])){
            $cursor=new DateTimeImmutable(max(date('Y-m-d'),$w['accepted_date']));$left=0;
            while($cursor->format('Y-m-d')<$w['target_date']){$cursor=$cursor->modify('+1 day');$date=$cursor->format('Y-m-d');if(in_array($cursor->format('D'),$w['calendar_days'],true)&&!exit_rows($db,"SELECT HolidayID FROM holidays WHERE HolidayDate=? AND IsNationwide=1 AND HolidayType IN ('Regular Holiday','Special Non-working Day')",[$date]))$left++;}
            $row['WorkingDaysRemaining']=$left;
        }
        if(in_array($actor['RoleName'],['TeamLead','Team Lead'],true)){
            foreach(['FinalPay','FinalPayAmount','FinalPayComputationDetails','FinalPayDate'] as $key)unset($row[$key]);
            foreach(['components','payment_reference','final_amount'] as $key)unset($row['workflow'][$key]);
            $row['history']=array_values(array_filter($history,static fn($h)=>!in_array($h['Action'],['pay_ready','release_pay'],true)));
            foreach($row['history'] as &$event){$details=json_decode($event['Details'],true)?:[];$event['Details']=json_encode(['stage'=>$details['stage']??null,'remarks'=>$details['remarks']??'']);}unset($event);
        }
        if($id && in_array($actor['RoleName'],['HR','Accounting','Admin'],true)){
            $row['payroll_history']=exit_rows($db,'SELECT p.Payroll_ID,p.PayPeriodStart,p.PayPeriodEnd,p.GrossPay,p.TotalDeductions,p.NetPay,s.Name RecordedStatus FROM payroll p LEFT JOIN status s ON s.StatusID=p.StatusID WHERE p.EmployeeID=? ORDER BY p.PayPeriodStart DESC',[$row['EmployeeID']]);
            $row['approved_adjustments']=exit_rows($db,'SELECT AdjustmentID,Payroll_ID,AdjustmentType,Amount,IsAddition,Reason,ApprovedBy FROM payroll_adjustments WHERE EmployeeID=? AND ApprovedBy IS NOT NULL ORDER BY AdjustmentID DESC',[$row['EmployeeID']]);
            $row['payment_status_history']=exit_rows($db,'SELECT DISTINCT h.HistoryID,h.PayrollRunID,h.Status,h.Remarks,h.UpdatedAt FROM payroll_status_history h JOIN payroll_run_members m ON m.PayrollRunID=h.PayrollRunID WHERE m.EmployeeID=? ORDER BY h.HistoryID DESC',[$row['EmployeeID']]);
        }
        $out[]=$row;
    }
    if($id&&!$out)throw new RuntimeException('Request unavailable.',404);
    return $out;
}

function exit_attach_document(mysqli $db,array $actor,array $input,array $file): int {
    $id=(int)($input['resignation_id']??0);$type=(string)($input['document_type']??'');
    $allowed=$type==='quitclaim'?['HR','Accounting','Admin']:['HR','Admin'];
    if(!in_array($type,['letter','clearance','quitclaim'],true)||!in_array($actor['RoleName'],$allowed,true))throw new RuntimeException('Document attachment not permitted.',403);
    if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_uploaded_file($file['tmp_name'])||filesize($file['tmp_name'])>10*1024*1024)throw new InvalidArgumentException('Choose a PDF, JPG or PNG up to 10 MB.');
    $ext=strtolower(pathinfo($file['name'],PATHINFO_EXTENSION));$mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if(!isset(['pdf'=>'application/pdf','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png'][$ext])||['pdf'=>'application/pdf','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png'][$ext]!==$mime)throw new InvalidArgumentException('Document content must match PDF, JPG or PNG format.');
    $path='uploads/employee_exit/'.bin2hex(random_bytes(20)).'.'.$ext;$absolute=dirname(__DIR__,2).'/'.$path;
    $db->begin_transaction();try{
        $row=exit_rows($db,'SELECT WorkflowDetails FROM resignationrequest WHERE ResignationID=? FOR UPDATE',[$id])[0]??null;
        $w=$row?json_decode($row['WorkflowDetails']??'',true):null;
        if(!$w||$w['stage']==='Resigned')throw new InvalidArgumentException('Choose an open registered request.');
        if(isset($w['documents'][$type]))throw new RuntimeException('A document is already recorded. Preserve the original; use the reference/remarks for corrections.',409);
        if(!move_uploaded_file($file['tmp_name'],$absolute))throw new RuntimeException('Unable to store document.');
        $w['documents'][$type]=['path'=>$path,'mime'=>$mime,'attached_by'=>(int)$actor['UsersID'],'attached_at'=>date('c')];
        payroll_query($db,'UPDATE resignationrequest SET WorkflowDetails=? WHERE ResignationID=?','si',[json_encode($w),$id]);
        exit_history($db,'resignation',$id,(int)$actor['UsersID'],'document_attached',['document_type'=>$type]);$db->commit();return $id;
    }catch(Throwable $e){$db->rollback();if(is_file($absolute))unlink($absolute);throw $e;}
}
function exit_transition(mysqli $db,array $actor,array $input): int {
    $action=(string)($input['action']??'');$uid=(int)$actor['UsersID'];$role=$actor['RoleName'];
    $hr=in_array($role,['HR','Admin'],true);$accounting=in_array($role,['Accounting','Admin'],true);
    $id=(int)($input['resignation_id']??0);
    $db->begin_transaction();
    try{
        if($action==='register'){
            if(!$hr)throw new RuntimeException('HR authorization required.',403);
            $employee=(int)($input['employee_id']??0);
            $emp=exit_rows($db,'SELECT * FROM employee WHERE EmployeeID=? AND IsActive=1 FOR UPDATE',[$employee])[0]??null;
            if(!$emp)throw new InvalidArgumentException('Choose an active employee.');
            if(exit_rows($db,"SELECT rr.ResignationID FROM resignationrequest rr LEFT JOIN status s ON s.StatusID=rr.StatusID WHERE rr.EmployeeID=? AND COALESCE(s.Name,'')<>'Resigned'",[$employee]))throw new RuntimeException('An open resignation already exists.',409);
            $received=exit_date(exit_required($input,'received_date'),true);$last=trim((string)($input['last_working_date']??''));if($last!=='')exit_date($last);
            $dep=exit_deployment($db,$employee,date('Y-m-d'));
            if(!$dep)$dep=exit_rows($db,'SELECT d.*,ds.Name SiteName,ds.TeamLeadID FROM deployment d LEFT JOIN deployment_sites ds ON ds.SiteID=d.SiteID WHERE d.EmployeeID=? AND d.StartDate<=CURDATE() ORDER BY d.StartDate DESC,d.DeploymentID DESC LIMIT 1',[$employee])[0]??null;
            $w=['stage'=>'Resignation Pending','received_by'=>$uid,'received_at'=>date('c'),'deployment_id'=>$dep['DeploymentID']??null,'site_id'=>$dep['SiteID']??null,'site_name'=>$dep['SiteName']??'Unassigned','letter_reference'=>trim((string)($input['letter_reference']??'')),'hr_remarks'=>trim((string)($input['remarks']??''))];
            $w['company_name']=$dep?((exit_rows($db,'SELECT Name FROM clientcompany WHERE CompanyID=?',[$dep['CompanyID']])[0]['Name']??'Unassigned')):'Unassigned';
            $w['team_lead_name']=!empty($dep['TeamLeadID'])?(exit_rows($db,'SELECT FullName FROM users WHERE UsersID=?',[$dep['TeamLeadID']])[0]['FullName']??'Unassigned'):'Unassigned';
            payroll_query($db,'INSERT INTO resignationrequest(EmployeeID,SubmissionDate,EffectiveDate,ResignationType,Reason,StatusID,WorkflowDetails) VALUES(?,?,?,?,?,?,?)','issssis',[$employee,$received,$last?:null,'Physical letter',trim((string)($input['reason']??'')),exit_status($db,'Resignation Pending'),json_encode($w)]);$id=(int)$db->insert_id;
        }else{
            $row=exit_rows($db,'SELECT * FROM resignationrequest WHERE ResignationID=? FOR UPDATE',[$id])[0]??null;
            if(!$row)throw new RuntimeException('Request unavailable.',404);
            $employee=(int)$row['EmployeeID'];$w=json_decode($row['WorkflowDetails']??'',true);
            if(!$w)throw new InvalidArgumentException('Legacy request requires HR reconciliation; original data has been preserved.');
            $stage=$w['stage'];$remark=trim((string)($input['remarks']??''));
            $routes=['approve'=>['Resignation Pending','HR Approved',$hr],'return_request'=>['Resignation Pending','Returned for Correction',$hr],
                'resubmit'=>['Returned for Correction','Resignation Pending',$hr],'issue_clearance'=>['HR Approved','Pending Team Lead Clearance',$hr],
                'tl_clear'=>['Pending Team Lead Clearance','Team Lead Cleared',in_array($role,['TeamLead','Team Lead'],true)],
                'tl_return'=>['Pending Team Lead Clearance','Clearance Returned',in_array($role,['TeamLead','Team Lead'],true)],
                'reissue'=>['Clearance Returned','Pending Team Lead Clearance',$hr],
                'verify_clearance'=>['Team Lead Cleared','Clearance Complete',$hr],
                'start_pay'=>['Clearance Complete','Final Pay Processing',$accounting],
                'pay_ready'=>['Final Pay Processing','Final Pay Ready',$accounting],
                'release_pay'=>['Final Pay Ready','Final Pay Released',$accounting],
                'finalize'=>['Final Pay Released','Resigned',$hr]];
            if(in_array($action,['set_target','quitclaim_prepare','quitclaim_sign'],true)){
                if(!($action==='set_target'?($hr||$accounting):($hr||$accounting)))throw new RuntimeException('Staff authorization required.',403);
                if(!in_array($stage,['Clearance Complete','Final Pay Processing','Final Pay Ready','Final Pay Released'],true))throw new InvalidArgumentException('HR must accept completed clearance first.');
            }elseif(!isset($routes[$action]))throw new InvalidArgumentException('Unknown workflow action.');
            else{
                if(!$routes[$action][2])throw new RuntimeException('Action not permitted for your role.',403);
                if($stage!==$routes[$action][0])throw new RuntimeException('The request has changed or this transition is not allowed.',409);
                if(in_array($action,['tl_clear','tl_return'],true)&&!exit_tl_owns($db,$uid,$employee,isset($w['site_id'])?(int)$w['site_id']:null))throw new RuntimeException('Clearance is outside your assigned sites.',403);
            }
            if(in_array($action,['return_request','tl_return','tl_clear','approve','verify_clearance','finalize'],true))$remark=exit_required($input,'remarks');
            if($action==='approve'){$w['hr_approved_by']=$uid;$w['hr_approved_at']=date('c');payroll_query($db,'UPDATE resignationrequest SET ApprovedBy=? WHERE ResignationID=?','ii',[$uid,$id]);}
            if(in_array($action,['issue_clearance','reissue'],true)){$w['clearance_reference']=exit_required($input,'clearance_reference');$w['issued_by']=$uid;$w['issued_at']=date('c');}
            if($action==='tl_clear'){$w['tl_approved_by']=$uid;$w['tl_approved_at']=date('c');$w['tl_remarks']=$remark;payroll_query($db,'UPDATE resignationrequest SET ClearanceApprovedDate=CURDATE() WHERE ResignationID=?','i',[$id]);}
            if($action==='verify_clearance'){
                if(($input['physical_received']??'')!=='1')throw new InvalidArgumentException('Confirm receipt of the physical signed clearance.');
                $w['accepted_date']=exit_date(exit_required($input,'accepted_date'),true);
                if($w['accepted_date']<substr($w['tl_approved_at'],0,10))throw new InvalidArgumentException('Receipt cannot precede Team Lead approval.');
                $w['hr_verified_by']=$uid;$w['hr_verified_at']=date('c');$w['calendar_policy']='Pending agency working-day calendar confirmation';
            }
            if($action==='set_target'){
                $previousTarget=[$w['target_date']??null,$w['calendar_days']??null];
                $days=array_values(array_unique(array_filter(array_map('trim',explode(',',(string)($input['working_days']??''))))));
                if($days){
                    if(!$hr||($input['calendar_confirmed']??'')!=='1')throw new InvalidArgumentException('HR must confirm the agency calendar and holiday exclusions.');
                    $w['calendar_days']=$days;$w['target_date']=exit_target($db,$w['accepted_date'],$days);$w['calendar_policy']='HR confirmed weekdays; nationwide regular/special non-working holidays excluded; 30 days after acceptance';
                }else{
                    $target=exit_date(exit_required($input,'target_date'));
                    if($target<$w['accepted_date'])throw new InvalidArgumentException('Target cannot precede clearance acceptance.');
                    $w['target_date']=$target;unset($w['calendar_days']);$w['calendar_policy']='Manual staff target; agency working-day policy pending confirmation';
                }
                $w['target_confirmed_by']=$uid;$w['target_reason']=exit_required($input,'remarks');
                if($previousTarget===[$w['target_date'],$w['calendar_days']??null])throw new RuntimeException('Tracking target is unchanged.',409);
            }
            if($action==='pay_ready'){
                if(($input['components_confirmed']??'')!=='1')throw new InvalidArgumentException('Confirm reviewed final-pay components under agency policy.');
                $components=json_decode((string)($input['components']??''),true);if(!is_array($components)||!$components||count($components)>50)throw new InvalidArgumentException('Enter documented credit/deduction components.');
                $cents=0;
                foreach($components as $component){
                    exit_required($component,'description');exit_required($component,'reference');
                    if(!in_array($component['type']??'',['credit','deduction'],true)||!preg_match('/^\d{1,9}(\.\d{1,2})?$/D',(string)($component['amount']??'')))throw new InvalidArgumentException('Enter valid component amounts.');
                    $cents+=(int)round((float)$component['amount']*100)*($component['type']==='credit'?1:-1);
                    if(!empty($component['payroll_id'])&&!exit_rows($db,'SELECT Payroll_ID FROM payroll WHERE EmployeeID=? AND Payroll_ID=?',[$employee,(int)$component['payroll_id']]))throw new InvalidArgumentException('Payroll reference does not belong to this employee.');
                }
                if($cents<0 || $cents>999999999999)throw new InvalidArgumentException('Settlement requires amount review; it cannot be silently clamped or overflowed.');
                $w['components']=$components;$w['final_amount']=$cents/100;$w['pay_prepared_by']=$uid;$w['pay_prepared_at']=date('c');
                payroll_query($db,'UPDATE resignationrequest SET FinalPayAmount=?,FinalPay=?,FinalPayComputationDetails=?,ProcessedBy=? WHERE ResignationID=?','ddsii',[$w['final_amount'],$w['final_amount'],json_encode($components),$uid,$id]);
            }
            if($action==='release_pay'){
                if(($input['payment_confirmed']??'')!=='1')throw new InvalidArgumentException('Confirm actual payment, not just preparation.');
                $w['payment_reference']=exit_required($input,'payment_reference');$w['released_date']=exit_date(exit_required($input,'released_date'),true);
                if($w['released_date']<$w['accepted_date'])throw new InvalidArgumentException('Payment cannot precede clearance acceptance.');
                $w['released_by']=$uid;$w['released_at']=date('c');
                payroll_query($db,'UPDATE resignationrequest SET FinalPayDate=? WHERE ResignationID=?','si',[$w['released_date'],$id]);
            }
            if($action==='quitclaim_prepare'){
                if(!empty($w['quitclaim_signed_at']))throw new RuntimeException('Signed quitclaim is locked.',409);
                if(($w['quitclaim_reference']??null)===($input['quitclaim_reference']??null))throw new RuntimeException('Quitclaim preparation is unchanged.',409);
                $w['quitclaim_reference']=exit_required($input,'quitclaim_reference');$w['quitclaim_prepared_by']=$uid;$w['quitclaim_prepared_at']=date('c');
            }
            if($action==='quitclaim_sign'){
                if(empty($w['quitclaim_reference'])||($input['signature_confirmed']??'')!=='1')throw new InvalidArgumentException('Prepare the quitclaim and confirm the actual employee signature.');
                if(!empty($w['quitclaim_signed_at']))throw new RuntimeException('Quitclaim completion already recorded.',409);
                $w['quitclaim_signed_date']=exit_date(exit_required($input,'signed_date'),true);$w['quitclaim_signed_at']=date('c');$w['quitclaim_verified_by']=$uid;$w['quitclaim_remarks']=exit_required($input,'remarks');
                payroll_query($db,'UPDATE resignationrequest SET QuitclaimSignedDate=? WHERE ResignationID=?','si',[$w['quitclaim_signed_date'],$id]);
            }
            if($action==='finalize'){
                if(empty($w['hr_approved_at'])||empty($w['tl_approved_at'])||empty($w['hr_verified_at'])||empty($w['released_at'])||empty($w['quitclaim_signed_at']))throw new InvalidArgumentException('Approval, verified clearance, actual payment and signed quitclaim are all required.');
                $last=exit_date(exit_required($input,'last_working_date'),true);
                $emp=exit_rows($db,'SELECT Email FROM employee WHERE EmployeeID=? FOR UPDATE',[$employee])[0];
                if(exit_rows($db,'SELECT DeploymentID FROM deployment WHERE EmployeeID=? AND StartDate<=CURDATE() AND StartDate>?',[$employee,$last]))throw new InvalidArgumentException('Last working date precedes a current deployment start. Review deployment dates.');
                payroll_query($db,'UPDATE employee SET StatusID=?,IsActive=0 WHERE EmployeeID=?','ii',[exit_status($db,'Resigned'),$employee]);
                payroll_query($db,"UPDATE users u JOIN roles r ON r.RolesID=u.RoleID SET u.IsActive=0 WHERE LOWER(TRIM(u.Email))=LOWER(TRIM(?)) AND r.RoleName='Employee'",'s',[$emp['Email']]);
                payroll_query($db,'UPDATE deployment SET EndDate=GREATEST(StartDate,?),StatusID=?,AllowDTRSubmission=0 WHERE EmployeeID=? AND (EndDate IS NULL OR EndDate>?)','siis',[$last,exit_status($db,'Completed'),$employee,$last]);
                $w['finalized_by']=$uid;$w['finalized_at']=date('c');$w['last_working_date']=$last;
                payroll_query($db,'UPDATE resignationrequest SET CompletedDate=CURDATE(),EffectiveDate=? WHERE ResignationID=?','si',[$last,$id]);
            }
            if(isset($routes[$action]))$w['stage']=$routes[$action][1];
            $w['last_remarks']=$remark;
            payroll_query($db,'UPDATE resignationrequest SET StatusID=?,WorkflowDetails=? WHERE ResignationID=?','isi',[exit_status($db,$w['stage']),json_encode($w,JSON_THROW_ON_ERROR),$id]);
        }
        exit_history($db,'resignation',$id,$uid,$action,['stage'=>$w['stage'],'remarks'=>$input['remarks']??'','workflow'=>$w]);
        exit_notify($db,$employee,$id,$action,'Request #'.$id.': '.$w['stage'].'. Open resignation progress for details.',isset($w['site_id'])?(int)$w['site_id']:null);
        $db->commit();return $id;
    }catch(Throwable $error){$db->rollback();throw $error;}
}

/** Only reviewed explicit absence records count; a missing DTR is unknown. */
function exit_awol_dates(mysqli $db,int $employee,string $through): array {
    $through=exit_date($through,true);$emp=exit_rows($db,'SELECT HiredDate,IsActive FROM employee WHERE EmployeeID=?',[$employee])[0]??null;
    if(!$emp || !$emp['IsActive'])return [];
    $cursor=(new DateTimeImmutable($through))->modify('-60 days');$streak=[];$qualified=[];
    while($cursor->format('Y-m-d')<=$through){
        $date=$cursor->format('Y-m-d');$cursor=$cursor->modify('+1 day');
        if($date>date('Y-m-d') || (!empty($emp['HiredDate'])&&$date<$emp['HiredDate']))continue;
        $dep=exit_deployment($db,$employee,$date);if(!$dep){$streak=[];continue;}
        if(empty($dep['WorkingDays'])&&(!team_schedule_table_available($db)||!exit_rows($db,"SELECT ScheduleID FROM team_schedule_entries WHERE DeploymentID=? AND ((EntryKind='Dated' AND ScheduleDate=?) OR (EntryKind='FixedHistory' AND ScheduleDate<=? AND ValidUntil>=?))",[$dep['DeploymentID'],$date,$date,$date]))){$streak=[];continue;}
        $schedule=team_schedule_resolve($db,$dep,$date);
        if(!payroll_is_scheduled_day($date,payroll_scheduled_weekdays($schedule['working_days'])))continue;
        // Do not count a shift whose overnight duty has not yet finished.
        $end=strtotime($date.' '.$schedule['time_out']);if($schedule['time_out']<$schedule['time_in'])$end+=86400;
        if($end>=time())continue;
        $holiday=holidays_fetch_for_range($db,$date,$date,$employee,(int)$dep['DeploymentID'])[$date]??[];
        $nonworking=(bool)array_filter($holiday,static fn($h)=>in_array($h['type'],['Regular Holiday','Special Non-working Day'],true));
        if($nonworking){
            $dated=team_schedule_table_available($db)?exit_rows($db,"SELECT ScheduleID FROM team_schedule_entries WHERE DeploymentID=? AND EntryKind='Dated' AND ScheduleDate=?",[$dep['DeploymentID'],$date]):[];
            if(!$dated)continue;
        }
        $leave=exit_rows($db,"SELECT l.LeaveID FROM leaves l JOIN status s ON s.StatusID=l.StatusID WHERE l.EmployeeID=? AND l.StartDate<=? AND l.EndDate>=? AND s.Name='Approved'",[$employee,$date,$date]);
        if($leave){$streak=[];continue;}
        $dtr=exit_rows($db,'SELECT status,time_in,time_out FROM daily_time_records WHERE employee_id=? AND date=?',[$employee,$date])[0]??null;
        $evidence=exit_rows($db,'SELECT AttendanceID,attendance_status,TimeIn,TimeOut,ApprovedBy,ApprovedDate FROM attendance WHERE EmployeeID=? AND Date=?',[$employee,$date]);
        $verified=(bool)$evidence;
        foreach($evidence as $a)if(strcasecmp((string)$a['attendance_status'],'Absent')!==0||!$a['ApprovedBy']||!$a['ApprovedDate']||$a['TimeIn']||$a['TimeOut'])$verified=false;
        if($dtr && $dtr['status']==='Absent' && !$dtr['time_in'] && !$dtr['time_out'] && $verified){$streak[]=$date;if(count($streak)>=3)$qualified=$streak;}
        else $streak=[];
    }
    return $qualified;
}
function exit_awol_scan(mysqli $db,array $actor,int $employee): ?int {
    if(!in_array($actor['RoleName'],['HR','Admin'],true))throw new RuntimeException('HR review required.',403);
    $db->begin_transaction();try{
        $emp=exit_rows($db,'SELECT EmployeeID FROM employee WHERE EmployeeID=? AND IsActive=1 FOR UPDATE',[$employee]);if(!$emp)throw new InvalidArgumentException('Choose an active employee.');
        $dates=exit_awol_dates($db,$employee,date('Y-m-d'));if(!$dates){$db->commit();return null;}
        $existing=exit_rows($db,'SELECT * FROM awol_review_cases WHERE EmployeeID=? AND EpisodeStart=? FOR UPDATE',[$employee,$dates[0]])[0]??null;
        if($existing){
            if(in_array($existing['State'],['Pending Review','Confirmed'],true)&&json_decode($existing['Evidence'],true)['dates']!==$dates){
                payroll_query($db,'UPDATE awol_review_cases SET EpisodeEnd=?,Evidence=? WHERE CaseID=?','ssi',[end($dates),json_encode(['dates'=>$dates]),(int)$existing['CaseID']]);
                exit_history($db,'awol',(int)$existing['CaseID'],(int)$actor['UsersID'],'awol_evidence_updated',['dates'=>$dates]);
            }
            $db->commit();return (int)$existing['CaseID'];
        }
        payroll_query($db,'INSERT INTO awol_review_cases(EmployeeID,EpisodeStart,EpisodeEnd,Evidence) VALUES(?,?,?,?)','isss',[$employee,$dates[0],end($dates),json_encode(['dates'=>$dates])]);$id=(int)$db->insert_id;
        exit_history($db,'awol',$id,(int)$actor['UsersID'],'awol_review_created',['employee_id'=>$employee,'dates'=>$dates]);
        exit_notify($db,$employee,$id,'awol_review','Three reviewed scheduled-duty absences require HR review. No account deactivation has occurred.');
        $db->commit();return $id;
    }catch(Throwable $e){$db->rollback();throw $e;}
}
function exit_awol_review(mysqli $db,array $actor,array $input): void {
    if(!in_array($actor['RoleName'],['HR','Admin'],true))throw new RuntimeException('HR review required.',403);
    $db->begin_transaction();try{
        $id=(int)($input['case_id']??0);$case=exit_rows($db,'SELECT * FROM awol_review_cases WHERE CaseID=? FOR UPDATE',[$id])[0]??null;
        if(!$case)throw new RuntimeException('Case unavailable.',404);
        $remark=exit_required($input,'remarks');$action=$input['action']??'';$employee=(int)$case['EmployeeID'];
        $emp=exit_rows($db,'SELECT StatusID,IsActive FROM employee WHERE EmployeeID=? FOR UPDATE',[$employee])[0];
        if(!$emp['IsActive'])throw new InvalidArgumentException('Review inactive employee status separately.');
        if($action==='confirm_awol'){
            if($case['State']!=='Pending Review'||($input['explanations_reviewed']??'')!=='1')throw new InvalidArgumentException('Review employee explanations and pending evidence first.');
            $dates=exit_awol_dates($db,$employee,$case['EpisodeEnd']);$saved=json_decode($case['Evidence'],true)['dates'];
            if(count(array_intersect($dates,$saved))<3)throw new InvalidArgumentException('Attendance evidence has changed. Re-evaluate the case.');
            $previous=$emp['StatusID'];payroll_query($db,'UPDATE employee SET StatusID=? WHERE EmployeeID=?','ii',[exit_status($db,'AWOL'),$employee]);$state='Confirmed';
        }elseif($action==='dismiss_awol'){
            if($case['State']!=='Pending Review')throw new RuntimeException('Case already reviewed.',409);$state='Dismissed';$previous=$case['PreviousStatusID'];
        }elseif($action==='reevaluate_awol'){
            if(!in_array($case['State'],['Pending Review','Confirmed'],true))throw new RuntimeException('Case already closed.',409);
            $dates=exit_awol_dates($db,$employee,$case['EpisodeEnd']);$saved=json_decode($case['Evidence'],true)['dates'];
            if(count(array_intersect($dates,$saved))>=3)throw new InvalidArgumentException('Evidence still meets the threshold. Review explanations before changing the outcome.');
            $state='Resolved';$previous=$case['PreviousStatusID'];
            if($case['State']==='Confirmed' && (int)$emp['StatusID']===exit_status($db,'AWOL'))payroll_query($db,'UPDATE employee SET StatusID=? WHERE EmployeeID=?','ii',[$previous,$employee]);
        }else throw new InvalidArgumentException('Unknown review action.');
        payroll_query($db,'UPDATE awol_review_cases SET State=?,PreviousStatusID=?,ReviewedBy=?,ReviewedAt=NOW(),Remarks=? WHERE CaseID=?','siisi',[$state,$previous,(int)$actor['UsersID'],$remark,$id]);
        exit_history($db,'awol',$id,(int)$actor['UsersID'],$action,['state'=>$state,'remarks'=>$remark]);exit_notify($db,$employee,$id,$action,'AWOL review #'.$id.': '.$state.'.');$db->commit();
    }catch(Throwable $e){$db->rollback();throw $e;}
}
