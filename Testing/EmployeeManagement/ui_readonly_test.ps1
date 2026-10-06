$ErrorActionPreference='Stop'
$php=@'
<?php
require getcwd().'/Api/Db_connection/db_connect.php';
session_save_path(getenv('TEMP').'/onazul-employee-ui-tests');ini_set('session.use_cookies','0');ini_set('session.cache_limiter','');
$ids=[];$count=0;
function check($ok,$label){global $count;if(!$ok)throw new RuntimeException('FAIL '.$label);$count++;echo "PASS $label\n";}
function sessionFor($u){global $ids;$id='emui'.bin2hex(random_bytes(12));session_id($id);session_start();$_SESSION=['user_id'=>(int)$u['UsersID'],'role'=>$u['RoleName'],'last_activity'=>time()];session_write_close();$ids[]=$id;return $id;}
function get($action,$id=null,$query=[]){$raw=file_get_contents('http://127.0.0.1:8894/Api/HR/hr_dashboard.php?'.http_build_query(['action'=>$action]+$query),false,stream_context_create(['http'=>['header'=>$id?"Cookie: PHPSESSID=$id\r\n":'','ignore_errors'=>true,'timeout'=>15]]));preg_match('/\s(\d{3})\s/',$http_response_header[0],$m);return [(int)$m[1],json_decode($raw,true)];}
try{
$users=[];$r=$conn->query('SELECT u.UsersID,r.RoleName FROM users u JOIN roles r ON r.RolesID=u.RoleID WHERE u.IsActive=1 ORDER BY u.UsersID');while($row=$r->fetch_assoc())$users[$row['RoleName']]??=$row;
check(get('employee_details',null,['employee_id'=>1])[0]===401,'Anonymous details denied');
foreach(['Admin','Accounting','TeamLead','Employee','Applicant'] as $role)check(get('employee_details',sessionFor($users[$role]),['employee_id'=>1])[0]===403,$role.' details denied');
$hr=sessionFor($users['HR']);$list=get('employee_list',$hr)[1]['data'];$count=(int)$count;
check($list['total']===(int)$conn->query('SELECT COUNT(*) n FROM employee')->fetch_assoc()['n'],'Main list matches actual employee count');
check(count($list['rows'])<=20&&$list['page_size']===20,'Main employee pagination remains bounded');
foreach($list['rows'] as $employee){$id=(int)$employee['EmployeeID'];[$code,$result]=get('employee_details',$hr,['employee_id'=>$id]);$data=$result['data'];check($code===200,'HR details load for existing employee '.$id);
check(!isset($data['deployment_pagination']),'No deployment pagination metadata '.$id);
$actual=$conn->query('SELECT DeploymentID FROM deployment WHERE EmployeeID='.$id.' ORDER BY StartDate DESC,DeploymentID DESC')->fetch_all(MYSQLI_ASSOC);
$expected=array_map('intval',array_column($actual,'DeploymentID'));$returned=array_map('intval',array_column($data['deployments'],'DeploymentID'));sort($expected);sort($returned);check($returned===$expected,'Complete actual deployment history '.$id);
if($employee['DeploymentID'])check((int)$data['deployments'][0]['DeploymentID']===(int)$employee['DeploymentID'],'Server-selected current deployment appears first '.$id);
$legacy=get('employee_details',$hr,['employee_id'=>$id,'detail_page'=>2])[1]['data'];check(count($legacy['deployments'])===count($actual),'Legacy page does not hide history '.$id);
}
check(get('employee_details',$hr,['employee_id'=>[1]])[0]===400,'Malformed employee ID denied');
check(get('employee_details',$hr,['employee_id'=>999999])[0]===404,'Missing employee returns 404');
check(get('employee_details',$hr,['employee_id'=>1,'detail_page'=>0])[0]===400,'Legacy page validated');
$filtered=get('employee_list',$hr,['page'=>2,'q'=>$list['rows'][0]['EmployeeNumber']])[1]['data'];check($filtered['total']===1&&count($filtered['rows'])===1,'Main list paging retains search');
echo "TOTAL $count PASS\n";
}finally{foreach($ids as $id){$path=session_save_path().'/sess_'.$id;if(is_file($path))unlink($path);}}

'@
$php | & 'C:\xampp\php\php.exe'
if($LASTEXITCODE -ne 0){throw 'Read-only employee UI tests failed'}

