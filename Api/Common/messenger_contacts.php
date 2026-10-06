<?php
require_once __DIR__.'/session_init.php';header('Content-Type: application/json');header('Cache-Control: no-store');
require __DIR__.'/../Db_connection/db_connect.php';require_once __DIR__.'/../Helpers/messenger_rbac.php';require_once __DIR__.'/../Helpers/messenger_unread.php';
try {
 $actor=messenger_actor($conn);require_once __DIR__.'/../Helpers/messenger_presence.php';messenger_record_activity($conn,$actor);$allowed=messenger_allowed_roles_for($actor['role']);$where='';$params=[$actor['user_id']];
 if($allowed!=='*'){$expanded=$allowed;if(in_array('TeamLead',$allowed,true))$expanded[]='Team Lead';if(in_array('Accounting',$allowed,true))$expanded[]='Accounting Staff';if(!$expanded)$where=' AND 0=1';else{$where=' AND r.RoleName IN ('.implode(',',array_fill(0,count($expanded),'?')).')';$params=array_merge($params,$expanded);}}
 $rows=site_query($conn,'SELECT u.UsersID user_id,u.FullName full_name,u.Email email,r.RoleName role,IF(u.MessengerLastActivity>=UNIX_TIMESTAMP()-120,1,0) is_online FROM users u JOIN roles r ON r.RolesID=u.RoleID WHERE u.IsActive=1 AND u.UsersID<>?'.$where.' ORDER BY u.FullName,u.UsersID',str_repeat('s',count($params)),$params)->fetch_all(MYSQLI_ASSOC);
 $contacts=[];$roles=[];$search=mb_strtolower(trim((string)($_GET['q']??'')));$filter=messenger_normalize_role((string)($_GET['role']??''));$counts=messenger_get_unread_counts($conn,$actor['user_id']);
 foreach($rows as$u){$u['role']=messenger_normalize_role($u['role']);if(!messenger_can_contact($conn,$actor,$u))continue;$roles[$u['role']]=true;$company='Onazul Manpower';
  if($u['role']==='Employee'){$d=messenger_deployment($conn,$u);$company=$d['company']??$company;}
  elseif($u['role']==='TeamLead'){$names=array_unique(array_column(sites_for_lead($conn,$u['user_id']),'CompanyName'));if($names)$company=implode(', ',$names);}
  if($filter!==''&&$filter!=='All'&&$u['role']!==$filter)continue;if($search!==''&&!str_contains(mb_strtolower($u['full_name'].' '.$u['role'].' '.$company),$search))continue;
  $contacts[]=['user_id'=>(int)$u['user_id'],'id'=>(int)$u['user_id'],'full_name'=>$u['full_name'],'role'=>$u['role'],'company'=>$company,'subtitle'=>$u['role'].' · '.$company,'is_online'=>(bool)$u['is_online'],'server_authorized'=>true,'thread_id'=>$counts['threads'][$u['user_id']]['thread_id']??null,'unread_count'=>$counts['by_contact'][$u['user_id']]??0,'latest_unread'=>$counts['latest_unread'][$u['user_id']]??null];
 }
 usort($contacts,function($a,$b)use($actor){
  if($actor['role']==='HR'){$priority=($a['role']==='Admin'?0:1)<=>($b['role']==='Admin'?0:1);if($priority)return $priority;}
  $priority=(int)($b['unread_count']>0)<=>(int)($a['unread_count']>0);if($priority)return $priority;
  $priority=strcmp((string)($b['latest_unread']['created_at']??''),(string)($a['latest_unread']['created_at']??''));if($priority)return $priority;
  $priority=($b['latest_unread']['message_id']??0)<=>($a['latest_unread']['message_id']??0);if($priority)return $priority;
  return strnatcasecmp($a['full_name'],$b['full_name'])?:($a['user_id']<=>$b['user_id']);
 });
 $_SESSION['messenger_csrf']??=bin2hex(random_bytes(32));echo json_encode(['success'=>true,'viewer'=>['user_id'=>$actor['user_id'],'role'=>$actor['role']],'contacts'=>$contacts,'roles'=>array_keys($roles),'messages_unread'=>$counts['total'],'csrf_token'=>$_SESSION['messenger_csrf']]);
}catch(Throwable $e){messenger_api_error($e);}
