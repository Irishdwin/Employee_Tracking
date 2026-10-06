$ErrorActionPreference='Stop'
$code=@'
<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require getcwd().'/Api/Db_connection/db_connect.php';
require getcwd().'/Api/Helpers/hr_dashboard_helper.php';
session_save_path(getenv('TEMP').'/onazul-employee-management-tests');ini_set('session.use_cookies','0');ini_set('session.cache_limiter','');
$base='http://127.0.0.1:8894';$count=0;$sessions=[];$employees=[];$createdStatuses=[];$company=$user=$applicant=$order=$application=0;
$marker='EMC'.bin2hex(random_bytes(6));$before=(int)$conn->query('SELECT COUNT(*) n FROM employee')->fetch_assoc()['n'];
function ck($ok,$label){global $count;if(!$ok)throw new RuntimeException('FAIL '.$label);++$count;echo "PASS $label\n";}
function sql($query,$types='',...$params){global $conn;$s=$conn->prepare($query);if($types!=='')$s->bind_param($types,...$params);$s->execute();$id=$s->insert_id;$s->close();return $id;}
function sess($u,$role=null,$age=0){global $sessions;$id='emcheck'.bin2hex(random_bytes(12));session_id($id);session_start();$_SESSION=['user_id'=>(int)$u['UsersID'],'role'=>$role??$u['RoleName'],'RoleID'=>(int)$u['RoleID'],'last_activity'=>time()-$age];session_write_close();$sessions[]=$id;return $id;}
function req($action,$sid=null,$query=[],$method='GET',$body=null){global $base;$path=str_contains($action,'.php')?$action:'HR/hr_dashboard.php?'.http_build_query(['action'=>$action]+$query);$raw=file_get_contents($base.'/Api/'.$path,false,stream_context_create(['http'=>['method'=>$method,'header'=>($sid?"Cookie: PHPSESSID=$sid\r\n":'')."Content-Type: application/json\r\n",'content'=>$body?json_encode($body):'','ignore_errors'=>true,'timeout'=>15]]));preg_match('/\s(\d{3})\s/',$http_response_header[0],$m);return [(int)$m[1],json_decode($raw,true)];}
function status($name){global $conn,$createdStatuses;$s=$conn->prepare('SELECT StatusID FROM status WHERE Name=?');$s->bind_param('s',$name);$s->execute();$r=$s->get_result()->fetch_assoc();$s->close();if($r)return (int)$r['StatusID'];$id=sql('INSERT INTO status (Name) VALUES (?)','s',$name);$createdStatuses[]=$id;return $id;}
try{
$roles=[];$r=$conn->query('SELECT u.UsersID,u.RoleID,r.RoleName FROM users u JOIN roles r ON r.RolesID=u.RoleID WHERE u.IsActive=1 ORDER BY u.UsersID');while($row=$r->fetch_assoc())$roles[$row['RoleName']]??=$row;
$hr=sess($roles['HR']);
foreach(['employee_list','employee_options','employee_details'] as $action){ck(req($action,null,['employee_id'=>1])[0]===401,'Anonymous denied '.$action);foreach(['Admin','Accounting','TeamLead','Employee','Applicant'] as $role)ck(req($action,sess($roles[$role]),['employee_id'=>1])[0]===403,$role.' denied '.$action);}
ck(req('employee_list',sess($roles['Admin'],'HR'))[0]===403,'Forged HR role checked against database');
ck(req('employee_list',sess($roles['HR'],null,1000))[0]===401,'Expired session denied');
ck(req('employee_list',$hr,[],'POST')[0]===405,'Mutation method denied');
[$code,$list]=req('employee_list',$hr);ck($code===200&&$list['success'],'Authenticated HR list loads');$data=$list['data'];ck($data['total']===$before,'List total equals actual employee table');
$summary=req('summary',$hr)[1]['data']['metrics'];
ck(req('employee_list',$hr,['q'=>'','filter'=>'total','company_id'=>'','site'=>'','position'=>'','employee_type'=>''])[1]['data']['total']===$before,'Empty browser filter values accepted');
foreach(hrd_filters() as $key=>$predicate){$filtered=req('employee_list',$hr,['filter'=>$key])[1]['data'];ck($filtered['total']===$summary[$key],'Dashboard consistency '.$key);}
$first=$data['rows'][0];$id=(int)$first['EmployeeID'];
ck(!array_intersect(['Email','SSSNumber','PhilHealthNumber','PagIBIGNumber','Password','BasicSalary','daily_rate','hourly_rate','BankName'],array_keys($first)),'Main list excludes sensitive fields');
foreach(['q'=>$first['EmployeeNumber'],'position'=>$first['Position'],'company_id'=>$first['CompanyID'],'site'=>$first['WorkSite']] as $key=>$value){if($value===null||$value==='')continue;$filtered=req('employee_list',$hr,[$key=>$value])[1]['data'];ck(in_array($id,array_map(fn($e)=>(int)$e['EmployeeID'],$filtered['rows']),true),'Real employee filter '.$key);}
$details=req('employee_details',$hr,['employee_id'=>$id]);ck($details[0]===200&&(int)$details[1]['data']['employee']['EmployeeID']===$id,'Employee details identity');$dbEmployee=$conn->query('SELECT Email,SSSNumber,PhilHealthNumber,PagIBIGNumber FROM employee WHERE EmployeeID='.$id)->fetch_assoc();foreach($dbEmployee as $key=>$value)ck($details[1]['data']['employee'][$key]===$value,'Details read actual '.$key);
ck(!array_intersect(['Password','BasicSalary','daily_rate','BankName','TINNumber'],array_keys($details[1]['data']['employee'])),'Details omit credentials and unnecessary pay fields');
$dep=$conn->query('SELECT DeploymentID FROM deployment WHERE EmployeeID='.$id.' AND StartDate<=CURDATE() AND (EndDate IS NULL OR EndDate>=CURDATE()) ORDER BY StartDate DESC,DeploymentID DESC LIMIT 1')->fetch_assoc();
ck((int)$details[1]['data']['employee']['DeploymentID']===(int)$dep['DeploymentID'],'Current deployment reads actual latest record');
$options=req('employee_options',$hr);ck($options[0]===200&&count($options[1]['data']['positions'])>0,'Filter options from employee database');ck($options[1]['data']['types']===[],'Null employment types not fabricated');
foreach([['filter'=>'bad'],['page'=>0],['page'=>'bad'],['q'=>['bad']],['q'=>str_repeat('x',101)],['company_id'=>'bad'],['employee_type'=>['bad']]] as $query)ck(req('employee_list',$hr,$query)[0]===400,'Invalid list input denied');
ck(req('employee_list',$hr,['q'=>"' OR 1=1 --"])[1]['data']['total']===0,'SQL injection search treated as literal');ck(req('employee_list',$hr,['q'=>'%'])[1]['data']['total']===0,'Search wildcard treated as literal');
ck(req('employee_details',$hr)[0]===400,'Missing employee ID denied');ck(req('employee_details',$hr,['employee_id'=>[1]])[0]===400,'Array employee ID denied');ck(req('employee_details',$hr,['employee_id'=>999999])[0]===404,'Missing record returns 404');ck(req('employee_details',$hr,['employee_id'=>$id,'detail_page'=>0])[0]===400,'Invalid history page denied');
$active=status('Active');$awol=status('AWOL');$resigned=status('Resigned');
for($i=0;$i<26;$i++){$statusId=$i===1?$awol:$active;$flag=$i>=24?0:1;$employees[]=sql('INSERT INTO employee (StatusID,EmployeeNumber,FirstName,LastName,Email,Position,EmployeeType,HiredDate,IsActive) VALUES (?,?,?,?,?,?,?,CURDATE(),?)','issssssi',$statusId,$marker.$i,$marker,'Person'.$i,strtolower($marker.$i).'@example.invalid',$marker.' Position','Test Contract',$flag);}
$company=sql('INSERT INTO clientcompany (Name) VALUES (?)','s',$marker.' Company');
$e=$employees[0];$lead=(int)$roles['TeamLead']['UsersID'];
$old=sql("INSERT INTO deployment (EmployeeID,CompanyID,Position,Location,StartDate,EndDate,StatusID,SiteSuperVisorID) VALUES (?,?,?, ?,DATE_SUB(CURDATE(),INTERVAL 2 DAY),NULL,?,?)",'iissii',$e,$company,'Older position','Older site',$active,$lead);
$current=sql("INSERT INTO deployment (EmployeeID,CompanyID,Position,Location,StartDate,EndDate,StatusID,SiteSuperVisorID) VALUES (?,?,?, ?,CURDATE(),NULL,?,?)",'iissii',$e,$company,'Current position','Current site',$active,$lead);
sql("INSERT INTO deployment (EmployeeID,CompanyID,Position,Location,StartDate,StatusID) VALUES (?,?,?, ?,DATE_ADD(CURDATE(),INTERVAL 1 DAY),?)",'iissi',$e,$company,'Future position','Future site',$active);
for($i=0;$i<11;$i++)sql("INSERT INTO deployment (EmployeeID,CompanyID,StartDate,EndDate,StatusID) VALUES (?,?,DATE_SUB(CURDATE(),INTERVAL 10 DAY),DATE_SUB(CURDATE(),INTERVAL 5 DAY),?)",'iii',$e,$company,$active);
sql("INSERT INTO resignationrequest (EmployeeID,StatusID,EffectiveDate) VALUES (?,?,CURDATE())",'ii',$employees[25],$resigned);
$fixtureList=req('employee_list',$hr,['q'=>$marker])[1]['data'];ck($fixtureList['total']===26&&count($fixtureList['rows'])===20&&$fixtureList['pages']===2,'Server-side employee pagination bounded');$second=req('employee_list',$hr,['q'=>$marker,'page'=>2])[1]['data'];ck(count($second['rows'])===6&&$second['total']===26,'Page two retains filters');ck(!array_intersect(array_column($fixtureList['rows'],'EmployeeID'),array_column($second['rows'],'EmployeeID')),'Pages have no duplicate employees');
foreach(['active'=>24,'inactive'=>2,'awol'=>1,'resigned'=>1,'deployed'=>1] as $key=>$expected)ck(req('employee_list',$hr,['q'=>$marker,'filter'=>$key])[1]['data']['total']===$expected,'Actual fixture workforce rule '.$key);
ck(req('employee_list',$hr,['q'=>$marker,'employee_type'=>'Test Contract'])[1]['data']['total']===26,'Actual employment type filter');
$detail=req('employee_details',$hr,['employee_id'=>$e])[1]['data'];ck((int)$detail['employee']['DeploymentID']===$current,'Latest current deployment excludes future and ended records');ck(count($detail['deployments'])===14&&!isset($detail['deployment_pagination']),'Complete deployment history without pagination');ck(count(req('employee_details',$hr,['employee_id'=>$e,'detail_page'=>2])[1]['data']['deployments'])===14,'Legacy page input does not hide deployment history');
ck(req('employee_list',$hr,['q'=>$marker,'company_id'=>$company,'site'=>'Current site'])[1]['data']['total']===1,'Combined current company/worksite filters');
ck(req('employee_details',$hr,['employee_id'=>$employees[2]])[1]['data']['employee']['DeploymentID']===null,'Not Deployed from missing actual assignment');
$roleId=(int)$roles['Applicant']['RoleID'];$email=strtolower($marker).'converted@example.invalid';$user=sql("INSERT INTO users (RoleID,FullName,Email,Password) VALUES (?,?,?,'test account disabled for sign-in')",'iss',$roleId,$marker.' Converted',$email);
$applicant=sql('INSERT INTO applicants (UserID,FullName,FirstName,LastName,Email) VALUES (?,?,?,?,?)','issss',$user,$marker.' Converted',$marker,'Converted',$email);
$order=sql("INSERT INTO joborder (CompanyID,Position,RequiredCount,Location) VALUES (?,?,1,?)",'iss',$company,$marker.' Converted position','Conversion site');
$application=sql("INSERT INTO job_applications (ApplicantID,JobOrderID,ClientApprovalStatus,Remarks) VALUES (?,?,'Approved','Contract uploaded: test fixture')",'ii',$applicant,$order);
$converted=req('HR/convert_to_employee.php',$hr,[],'POST',['application_id'=>$application,'sss_number'=>'TEST-SSS','philhealth_number'=>'TEST-PH','pagibig_number'=>'TEST-PI','daily_rate'=>500,'allowance'=>0]);ck($converted[0]===200&&!empty($converted[1]['success']),'Existing conversion API completes disposable hiring workflow');$convertedId=(int)$converted[1]['employee_id'];$employees[]=$convertedId;
$view=req('employee_list',$hr,['q'=>$converted[1]['employee_number']])[1]['data'];ck($view['total']===1&&(int)$view['rows'][0]['EmployeeID']===$convertedId,'Converted employee appears automatically without duplicate creation');
ck((int)req('employee_details',$hr,['employee_id'=>$convertedId])[1]['data']['employee']['CompanyID']===$company,'Existing conversion deployment visible');
$repeat=req('HR/convert_to_employee.php',$hr,[],'POST',['application_id'=>$application,'sss_number'=>'TEST-SSS','philhealth_number'=>'TEST-PH','pagibig_number'=>'TEST-PI','daily_rate'=>500]);ck(empty($repeat[1]['success']),'Existing conversion blocks duplicate hire');
ck(req('employee_list',$hr)[1]['data']['total']===req('summary',$hr)[1]['data']['metrics']['total'],'Converted workforce total remains consistent');
echo "TOTAL $count PASS\n";
}finally{
if($user)sql('DELETE FROM notifications WHERE UserID=?','i',$user);
if($application)sql('DELETE FROM job_applications WHERE ApplicationID=?','i',$application);
if($order)sql('DELETE FROM joborder WHERE JobOrderID=?','i',$order);
if($applicant)sql('DELETE FROM applicants WHERE ApplicantID=?','i',$applicant);
foreach($employees as $id){sql('DELETE FROM resignationrequest WHERE EmployeeID=?','i',$id);sql('DELETE FROM deployment WHERE EmployeeID=?','i',$id);sql('DELETE FROM employee WHERE EmployeeID=? AND FirstName=?','is',$id,$marker);}
if($company)sql('DELETE FROM clientcompany WHERE CompanyID=? AND Name=?','is',$company,$marker.' Company');
if($user)sql('DELETE FROM users WHERE UsersID=? AND Email=?','is',$user,$email);
foreach($createdStatuses as $id)sql('DELETE FROM status WHERE StatusID=?','i',$id);
foreach($sessions as $sid){$file=session_save_path().'/sess_'.$sid;if(is_file($file))unlink($file);}
ck((int)$conn->query('SELECT COUNT(*) n FROM employee')->fetch_assoc()['n']===$before,'Disposable fixtures cleaned; original employee count restored');
}

'@
$testPath=Join-Path $env:TEMP ('onazul-em-'+[guid]::NewGuid().ToString('N')+'.php')
try {
 [IO.File]::WriteAllText($testPath,$code)
 & 'C:\xampp\php\php.exe' $testPath
 if($LASTEXITCODE -ne 0){throw 'Employee Management runtime tests failed'}
} finally {Remove-Item -LiteralPath $testPath -ErrorAction SilentlyContinue}

