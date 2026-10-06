<?php
/**
 * Server-side messenger RBAC (must match Js/messenger_rbac.js).
 */

function messenger_normalize_role(string $role): string
{
    $role = trim($role);
    $map = [
        'Accounting Staff' => 'Accounting',
        'Team Lead' => 'TeamLead',
    ];
    return $map[$role] ?? $role;
}

/** @return array<int, string>|string '*' */
function messenger_allowed_roles_for(string $viewerRole)
{
    $viewerRole = messenger_normalize_role($viewerRole);
    $rules = [
        'Admin' => '*',
        'HR' => ['Admin', 'Accounting', 'TeamLead', 'Applicant', 'Employee'],
        'Accounting' => ['Admin', 'HR', 'TeamLead', 'Employee'],
        'TeamLead' => ['Employee', 'Accounting', 'HR'],
        'Applicant' => ['HR'],
        'Employee' => ['Employee', 'Accounting', 'TeamLead', 'HR'],
    ];
    return $rules[$viewerRole] ?? [];
}

require_once __DIR__.'/work_site_geofence.php';
function messenger_user(mysqli $db,int $id):?array {$r=site_query($db,'SELECT u.UsersID user_id,u.FullName full_name,u.Email email,r.RoleName role FROM users u JOIN roles r ON r.RolesID=u.RoleID WHERE u.UsersID=? AND u.IsActive=1','i',[$id])->fetch_assoc();if($r)$r['role']=messenger_normalize_role($r['role']);return $r;}
function messenger_actor(mysqli $db):array {$u=messenger_user($db,(int)($_SESSION['user_id']??0));if(!$u)throw new DomainException('Sign in with an active account.',401);return $u;}
function messenger_deployment(mysqli $db,array $user):?array {
 $current=site_current_deployment_sql('d');
 return site_query($db,"SELECT d.CompanyID,s.TeamLeadID,s.SiteID,c.Name company FROM employee e JOIN deployment d ON d.EmployeeID=e.EmployeeID JOIN deployment_sites s ON s.SiteID=d.SiteID AND s.CompanyID=d.CompanyID AND s.IsActive=1 JOIN clientcompany c ON c.CompanyID=s.CompanyID AND c.IsActive=1 WHERE LOWER(TRIM(e.Email))=LOWER(TRIM(?)) AND e.IsActive=1 AND $current",'s',[$user['email']])->fetch_assoc();
}
function messenger_can_contact(mysqli $db,array $actor,array $recipient):bool {
 if((int)$actor['user_id']===(int)$recipient['user_id'])return false;$allowed=messenger_allowed_roles_for($actor['role']);
 if($allowed==='*')return true;if(!in_array($recipient['role'],$allowed,true))return false;
 if($actor['role']==='TeamLead'&&$recipient['role']==='Employee'){$d=messenger_deployment($db,$recipient);return $d&&(int)$d['TeamLeadID']===(int)$actor['user_id'];}
 if($actor['role']==='Employee'&&in_array($recipient['role'],['Employee','TeamLead'],true)){
  $d=messenger_deployment($db,$actor);if(!$d)return false;
  if($recipient['role']==='TeamLead')return (int)$d['TeamLeadID']===(int)$recipient['user_id'];
  $other=messenger_deployment($db,$recipient);return $other&&(int)$d['CompanyID']===(int)$other['CompanyID'];
 }return true;
}
function messenger_require_recipient(mysqli $db,array $actor,int $id):array {$u=messenger_user($db,$id);if(!$u||!messenger_can_contact($db,$actor,$u))throw new DomainException('You are not allowed to message this contact.',403);return $u;}
function messenger_require_thread(mysqli $db,array $actor,int $id):array {
 $rows=site_query($db,'SELECT t.ThreadID,t.ThreadType,t.RelatedModule,p.UserID FROM chat_threads t JOIN chat_thread_participants p ON p.ThreadID=t.ThreadID WHERE t.ThreadID=?','i',[$id])->fetch_all(MYSQLI_ASSOC);$members=array_map('intval',array_column($rows,'UserID'));
 if(!$rows||!in_array((int)$actor['user_id'],$members,true))throw new DomainException('This conversation is unavailable.',403);
 $peers=[];foreach($members as$uid)if($uid!==(int)$actor['user_id']){try{$peers[]=messenger_require_recipient($db,$actor,$uid);}catch(DomainException $e){if($rows[0]['ThreadType']==='DIRECT')throw $e;}}
 if(!$peers)throw new DomainException('This conversation is unavailable.',403);
 if($rows[0]['ThreadType']==='DIRECT'){if(count($peers)!==1)throw new DomainException('This conversation is unavailable.',403);$key='dm_'.min($actor['user_id'],$peers[0]['user_id']).'_'.max($actor['user_id'],$peers[0]['user_id']);if($rows[0]['RelatedModule']!==$key)throw new DomainException('This conversation is unavailable.',403);}
 return $peers;
}
function messenger_api_error(Throwable $e):void {http_response_code($e instanceof DomainException?($e->getCode()?:403):500);echo json_encode(['success'=>false,'message'=>$e instanceof DomainException?$e->getMessage():'Messenger is temporarily unavailable. Please try again.']);}

function messenger_notification_peer(mysqli $db,int $user,array $row):?int {
 if(($row['RelateedModule']??'')!=='chat_thread')return 0;
 try{$actor=messenger_user($db,$user);if(!$actor)return null;$peers=messenger_require_thread($db,$actor,(int)$row['RelatedRecord']);return count($peers)===1?(int)$peers[0]['user_id']:0;}catch(DomainException $e){return null;}
}
function messenger_notification_unread(mysqli $db,int $user):int {
 $rows=site_query($db,'SELECT RelateedModule,RelatedRecord FROM notifications WHERE UserID=? AND IsRead=0','i',[$user])->fetch_all(MYSQLI_ASSOC);$n=0;foreach($rows as$row)if(messenger_notification_peer($db,$user,$row)!==null)$n++;return $n;
}
