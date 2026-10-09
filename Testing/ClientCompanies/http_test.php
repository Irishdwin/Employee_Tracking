<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Manila');
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
$db=new mysqli('127.0.0.1','root','');$db->set_charset('utf8mb4');
$database='codex_clients_'.bin2hex(random_bytes(6));$root=sys_get_temp_dir().'/'.$database;$source=dirname(__DIR__,2);$process=null;$checks=[];
function check(bool $ok,string $label):void {global $checks;if(!$ok)throw new RuntimeException($label);$checks[]=$label;}
function fixture(mysqli $db,string $table,array $values):int {
 foreach($db->query('SHOW COLUMNS FROM `'.$table.'`')->fetch_all(MYSQLI_ASSOC) as $c){
  if(array_key_exists($c['Field'],$values)||$c['Null']==='YES'||$c['Default']!==null||str_contains($c['Extra'],'auto_increment'))continue;
  $t=strtolower($c['Type']);$values[$c['Field']]=preg_match('/int|decimal|float|double/',$t)?1:(str_starts_with($t,'date')?'2026-10-09':(str_starts_with($t,'enum')?explode("'",$t)[1]:'Fixture'));
 }
 $s=$db->prepare('INSERT INTO `'.$table.'` (`'.implode('`,`',array_keys($values)).'`) VALUES('.implode(',',array_fill(0,count($values),'?')).')');$v=array_values($values);$s->bind_param(str_repeat('s',count($v)),...$v);$s->execute();return (int)$db->insert_id;
}
try {
 $db->query('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4');$db->select_db($database);
 foreach($db->query('SHOW TABLES FROM onazul_hris_db')->fetch_all()as $r)$db->query('CREATE TABLE `'.$r[0].'` LIKE onazul_hris_db.`'.$r[0].'`');
 $db->query('INSERT INTO roles SELECT * FROM onazul_hris_db.roles');$db->query('INSERT INTO status SELECT * FROM onazul_hris_db.status');
 $roles=[];foreach($db->query('SELECT RolesID,RoleName FROM roles')->fetch_all(MYSQLI_ASSOC)as$r)$roles[$r['RoleName']]=$r['RolesID'];
 foreach([[8,'HR','Test HR'],[1,'Admin','Test Admin'],[2,'TeamLead','First Lead'],[3,'TeamLead','Second Lead'],[4,'Employee','Wrong Role'],[5,'Applicant','Resume Tester'],[6,'Applicant','Other Resume Tester']]as$user)fixture($db,'users',['UsersID'=>$user[0],'RoleID'=>$roles[$user[1]],'FullName'=>$user[2],'Email'=>'test'.$user[0].'@gmail.com','Password'=>password_hash('Birch!58Lemon',PASSWORD_DEFAULT),'IsActive'=>1]);
 fixture($db,'industry_lookup',['IndustryID'=>1,'Name'=>'Retial']);fixture($db,'industry_lookup',['IndustryID'=>2,'Name'=>'Manufacturing']);
 foreach([1,2,3]as$id)fixture($db,'clientcompany',['CompanyID'=>$id,'Name'=>'Client '.$id,'ContactPerson'=>'First Lead','UserID'=>2,'ContactNumber'=>'+639123456789','Industry'=>$id===3?'Manufacturing':'Retial','IsActive'=>1]);
 foreach([1,2]as$id)fixture($db,'deployment_sites',['SiteID'=>$id,'CompanyID'=>1,'Name'=>'Branch '.$id,'Address'=>'Test site','TeamLeadID'=>2,'IsActive'=>1]);
 $activeStatus=(int)$db->query("SELECT StatusID FROM status WHERE Name='Active' LIMIT 1")->fetch_row()[0];
 foreach([1,2,3]as$id){fixture($db,'employee',['EmployeeID'=>$id,'EmployeeNumber'=>'TEST'.$id,'FirstName'=>'Employee','LastName'=>(string)$id,'IsActive'=>1]);fixture($db,'deployment',['DeploymentID'=>$id,'EmployeeID'=>$id,'CompanyID'=>1,'SiteID'=>$id===3?null:$id,'SiteSuperVisorID'=>2,'StartDate'=>'2020-01-01','EndDate'=>null,'StatusID'=>$activeStatus]);}
 mkdir($root,0700,true);
 $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source.'/Api',FilesystemIterator::SKIP_DOTS));
 foreach($iterator as$file){if(!$file->isFile())continue;$relative=substr($file->getPathname(),strlen($source.'/Api')+1);$target=$root.'/Api/'.$relative;if(!is_dir(dirname($target)))mkdir(dirname($target),0700,true);copy($file->getPathname(),$target);}
 $config=$root.'/Api/Db_connection/db_connect.php';file_put_contents($config,str_replace("\$dbname = 'onazul_hris_db';","\$dbname = '$database';",file_get_contents($config)));
 mkdir($root.'/sessions',0700,true);$init=$root.'/Api/Common/session_init.php';file_put_contents($init,"<?php\nini_set('session.save_path',".var_export($root.'/sessions',true).");\n".substr(file_get_contents($init),5));
 $sessions=[];$csrf=bin2hex(random_bytes(32));
 foreach(['admin'=>[1,'Admin'],'employee'=>[4,'Employee'],'forged'=>[4,'Admin'],'lead2'=>[2,'TeamLead'],'lead3'=>[3,'TeamLead'],'hr'=>[8,'HR'],'resume'=>[5,'Applicant'],'resumeOther'=>[6,'Applicant']]as$label=>$user){$id='clients'.bin2hex(random_bytes(12));$sessions[$label]=$id;file_put_contents($root.'/sessions/sess_'.$id,'user_id|i:'.$user[0].';role|s:'.strlen($user[1]).':"'.$user[1].'";payroll_csrf|s:64:"'.$csrf.'";last_activity|i:'.time().';');}
 $sessions['guest']='clients'.bin2hex(random_bytes(12));file_put_contents($root.'/sessions/sess_'.$sessions['guest'],'last_activity|i:'.time().';');
 $socket=stream_socket_server('tcp://127.0.0.1:0');$address=stream_socket_get_name($socket,false);fclose($socket);
 $process=proc_open([PHP_BINARY,'-d','session.save_path='.$root.'/sessions','-S',$address,'-t',$root],[0=>['pipe','r'],1=>['file',$root.'/server.log','a'],2=>['file',$root.'/server.log','a']],$pipes,$root);fclose($pipes[0]);
 for($i=0;$i<40;$i++){if($probe=@stream_socket_client('tcp://'.$address,$errno,$error,0.1)){fclose($probe);break;}usleep(100000);}
 $http=function(string $endpoint,?array $post=null,string $viewer='admin',bool $token=true,bool $json=false)use($sessions,$csrf,$address):array{
  $curl=curl_init('http://'.$address.'/Api/'.$endpoint);$headers=['Accept: application/json'];if($token)$headers[]='X-Payroll-CSRF: '.$csrf;if($json)$headers[]='Content-Type: application/json';
  curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIE=>'PHPSESSID='.($sessions[$viewer]??''),CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>25]);
  if($post!==null)curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$json?json_encode($post):$post]);$body=curl_exec($curl);$status=curl_getinfo($curl,CURLINFO_HTTP_CODE);if($body===false)throw new RuntimeException(curl_error($curl));curl_close($curl);return [$status,json_decode($body,true),$body];
 };
 fixture($db,'applicants',['ApplicantID'=>100,'UserID'=>5,'FullName'=>'Resume Tester','Email'=>'test5@gmail.com']);
 $pdf="%PDF-1.4\n";$offsets=[0];$objects=['<< /Type /Catalog /Pages 2 0 R >>','<< /Type /Pages /Kids [3 0 R] /Count 1 >>','<< /Type /Page /Parent 2 0 R /MediaBox [0 0 200 200] >>'];foreach($objects as $i=>$obj){$offsets[]=strlen($pdf);$pdf.=($i+1)." 0 obj\n$obj\nendobj\n";}$xref=strlen($pdf);$pdf.="xref\n0 4\n0000000000 65535 f \n";foreach(array_slice($offsets,1)as$o)$pdf.=sprintf("%010d 00000 n \n",$o);$pdf.="trailer\n<< /Size 4 /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";
 file_put_contents($root.'/valid.pdf',$pdf);file_put_contents($root.'/image.jpg',base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2Q=='));file_put_contents($root.'/fake.pdf','not a PDF');file_put_contents($root.'/large.pdf',$pdf.str_repeat(' ',5*1024*1024));
 file_put_contents($root.'/image.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aS1sAAAAASUVORK5CYII='));copy($root.'/image.jpg',$root.'/renamed.pdf');
 foreach(['upload_applicant_document.php','upload_document.php']as$upload){
  $type=$upload==='upload_document.php'?'doc_type':'document_type';
  foreach(['image.jpg','image.png','renamed.pdf','fake.pdf','large.pdf']as$bad){[$status,$response]=$http('Applicant/'.$upload,[$type=>'Resume','document'=>new CURLFile($root.'/'.$bad)],'resume');check(!$response['success'],'Resume rejects '.$bad.' through '.$upload);}
  [$status,$response]=$http('Applicant/'.$upload,[$type=>'Resume','document'=>new CURLFile($root.'/valid.pdf','application/pdf','resume.pdf')],'resume');check($response['success'],'Valid PDF saved through '.$upload);
 }
 $resumeDocs=$db->query('SELECT DocID,FilePath FROM applicant_uploaded_docs WHERE ApplicantID=100 ORDER BY DocID DESC')->fetch_all(MYSQLI_ASSOC);check(count($resumeDocs)===2,'Resume replacement preserves earlier records');$doc=(int)$resumeDocs[0]['DocID'];
 foreach(['resume','admin','hr']as$viewer){[$status,$json,$body]=$http('Applicant/view_document.php?id='.$doc,null,$viewer);check($status===200&&str_starts_with($body,'%PDF'),'Authorized resume preview '.$viewer);}
 [$status]=$http('Applicant/view_document.php?id='.$doc,null,'employee');check($status===403,'Other user cannot view resume');
 [$status,$json,$body]=$http('Applicant/download_applicant_document.php?doc_id='.$doc,null,'resume');check($status===200&&$body===$pdf,'Applicant download returns stored PDF');
 [$status,$response]=$http('Applicant/upload_document.php',['doc_type'=>'Resume','application_id'=>999999,'document'=>new CURLFile($root.'/valid.pdf')],'resume');check($status===403&&!$response['success'],'Unauthorized application upload rejected');
 [$status,$response]=$http('Applicant/upload_applicant_document.php',['document_type'=>'Government ID','document'=>new CURLFile($root.'/image.png')],'resume');check($response['success'],'Valid ID image upload unchanged');
 $legacyDir=$root.'/Uploads/Documents';file_put_contents($legacyDir.'/legacy.jpg',file_get_contents($root.'/image.jpg'));fixture($db,'applicant_uploaded_docs',['ApplicantID'=>100,'DocType'=>'Resume','DocCategory'=>'employment','FilePath'=>'../../Uploads/Documents/legacy.jpg','OriginalFileName'=>'legacy.jpg']);$legacyId=$db->insert_id;
 [$status,$j,$body]=$http('Applicant/view_document.php?id='.$legacyId,null,'resume');check($status===200&&$body===file_get_contents($root.'/image.jpg'),'Legacy image resume remains accessible');
 [$status,$j,$body]=$http('Applicant/view_document.php?id='.$doc,null,'forged');check($status===403,'Forged role cannot access resume');
 [$status,$response]=$http('Applicant/upload_applicant_document.php',['document_type'=>'Resume'],'resume');check(!$response['success'],'Missing resume upload rejected');
 require_once $source.'/Api/Helpers/applicant_required_documents.php';check(in_array('Resume',applicant_missing_documents($db,2),true),'Required resume remains enforced for missing documents');
 [$status]=$http('Applicant/view_document.php?id='.$doc,null,'resumeOther');check($status===403,'Another applicant cannot preview this resume');
 [$status]=$http('Applicant/download_applicant_document.php?doc_id='.$doc,null,'resumeOther');check($status===403,'Another applicant cannot download this resume');
 fixture($db,'joborder',['JobOrderID'=>2000,'CompanyID'=>1,'Position'=>'Resume Test Worker']);fixture($db,'job_applications',['ApplicationID'=>2000,'ApplicantID'=>100,'JobOrderID'=>2000,'EndorsedDate'=>'2026-10-09']);
 [$status]=$http('Applicant/view_document.php?id='.$doc,null,'lead2');check($status===200,'Assigned Team Lead can view endorsed resume');
 [$status]=$http('Applicant/view_document.php?id='.$doc,null,'lead3');check($status===403,'Unassigned Team Lead cannot view endorsed resume');
 [$status,$detail,$detailBody]=$http('HR/get_application_details.php?id=2000',null,'hr');check($status===200&&count($detail['documents'])>0&&count(array_filter($detail['documents'],fn($d)=>$d['doc_type']==='Resume'&&str_contains($d['file_path'],'view_document.php')))>0,'Recruitment workflow returns protected resume URL: '.$status.' '.$detailBody);
 [$status,$context]=$http('Admin/client_companies.php?list=context');check($status===200&&$context['csrf_token']===$csrf,'Authenticated Admin context');
 foreach(['employee','forged','guest']as$v){[$status]=$http('Admin/client_companies.php?list=context',null,$v);check(in_array($status,[401,403]),'Reject unauthorized or forged Admin '.$v);}
 [$status]=$http('Admin/industries.php',['name'=>'No CSRF'], 'admin',false);check($status===403,'Industry CSRF');
 [$status,$list]=$http('Admin/industries.php');$item=array_values(array_filter($list['industries'],fn($i)=>$i['id']===1))[0];
 [$status]=$http('Admin/industries.php',['action'=>'update','industry_id'=>2,'edit_token'=>$item['edit_token'],'name'=>'Tampered']);check($status===409,'Industry token binds selected record');
 [$status]=$http('Admin/industries.php',['action'=>'update','industry_id'=>1,'edit_token'=>$item['edit_token'],'name'=>'retail']);check($status===200,'Industry rename');
 check($db->query('SELECT Name FROM industry_lookup WHERE IndustryID=1')->fetch_row()[0]==='retail','Same industry ID preserved');
 check((int)$db->query("SELECT COUNT(*) FROM clientcompany WHERE Industry='retail'")->fetch_row()[0]===2,'Shared company references updated');
 check($db->query('SELECT Industry FROM clientcompany WHERE CompanyID=3')->fetch_row()[0]==='Manufacturing','Unrelated industry unchanged');
 [$status]=$http('Admin/industries.php',['action'=>'update','industry_id'=>1,'edit_token'=>$item['edit_token'],'name'=>'Stale edit']);check($status===409,'Stale industry selection rejected');
 [$status,$freshIndustries]=$http('Admin/industries.php');$freshItem=array_values(array_filter($freshIndustries['industries'],fn($i)=>$i['id']===1))[0];
 $db->query("CREATE TRIGGER fixture_industry_failure BEFORE UPDATE ON clientcompany FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Fixture failure'");
 [$status,$failedIndustry]=$http('Admin/industries.php',['action'=>'update','industry_id'=>1,'edit_token'=>$freshItem['edit_token'],'name'=>'Failed rename']);
 check($status===500&&$failedIndustry['message']==='Unable to save industry.','Industry failure message contains no SQL');
 check($db->query('SELECT Name FROM industry_lookup WHERE IndustryID=1')->fetch_row()[0]==='retail','Industry and company rename roll back together');$db->query('DROP TRIGGER fixture_industry_failure');
 [$status]=$http('Admin/industries.php',['action'=>'create','name'=>'RETAIL']);check($status===409,'Case insensitive duplicate industry rejected');
 [$status]=$http('Admin/industries.php',['action'=>'create','name'=>str_repeat('A',101)]);check($status===400,'Industry length enforced');
 [$status]=$http('Admin/industries.php',['action'=>'create','name'=>'Logistics']);check($status===200,'Industry creation retained');

 $db->query("UPDATE employee SET Email='test4@gmail.com' WHERE EmployeeID=1");
 $db->query("UPDATE deployment SET TimeIn='08:00:00',TimeOut='17:00:00',BreakDuration=60,GracePeriod=5,WorkingDays='Mon-Fri',ShiftName='Fixed' WHERE DeploymentID=1");
 $date=date('Y-m-d',strtotime('+6 days'));
 [$status,$schedule]=$http('TeamLead/team_schedule.php?site_id=1&date='.$date,null,'lead2');check($status===200&&$schedule['employees'][0]['current_duty']['time_in']==='08:00:00','Current duty resolved for selected date');
 $duty=['action'=>'save','deployment_id'=>1,'site_id'=>1,'csrf_token'=>$schedule['csrf_token'],'schedule_type'=>'Rotating','date'=>$date,'time_in'=>'10:00','time_out'=>'19:00','break_duration'=>60,'grace_period'=>5];
 [$status,$changed]=$http('TeamLead/team_schedule.php',$duty,'lead2',true,true);check($status===200&&$changed['event']==='assigned','Actual date-specific duty save');
 [$status,$schedule]=$http('TeamLead/team_schedule.php?site_id=1&date='.$date,null,'lead2');check($schedule['employees'][0]['current_duty']['time_in']==='10:00:00','Employee date-specific current duty updated');
 [$status,$neighbor]=$http('TeamLead/team_schedule.php?site_id=1&date='.date('Y-m-d',strtotime($date.' +1 day')),null,'lead2');check($neighbor['employees'][0]['current_duty']['time_in']==='08:00:00','Adjacent date retains base duty');
 $before=(int)$db->query("SELECT COUNT(*) FROM notifications WHERE NotificationType='schedule_assigned' AND UserID=4 AND Title='Duty Schedule Changed'")->fetch_row()[0];check($before===1,'Affected employee gets duty-change notification');
 [$status,$same]=$http('TeamLead/team_schedule.php',$duty,'lead2',true,true);check($status===200&&$same['event']==='unchanged','Identical duty is no-op');check((int)$db->query("SELECT COUNT(*) FROM notifications WHERE NotificationType='schedule_assigned' AND UserID=4")->fetch_row()[0]===$before,'No duplicate duty notification');
 check((int)$db->query("SELECT COUNT(*) FROM system_logs WHERE module='Team Schedule' AND action='Schedule Change'")->fetch_row()[0]===1,'One schedule audit record');
 [$status]=$http('TeamLead/team_schedule.php',array_replace($duty,['site_id'=>2]),'lead2',true,true);check($status===403,'Cross-site employee schedule rejected');
 [$status]=$http('TeamLead/team_schedule.php',$duty,'lead3',true,true);check($status===403,'Other Team Lead schedule rejected');
 fixture($db,'dtr_submissions',['EmployeeID'=>1,'DeploymentID'=>1,'CutoffStartDate'=>$date,'CutoffEndDate'=>$date]);
 [$status]=$http('TeamLead/team_schedule.php',array_replace($duty,['time_in'=>'11:00']),'lead2',true,true);check($status===409,'Submitted DTR prevents duty changes');
 $db->query('DELETE FROM dtr_submissions WHERE EmployeeID=1');
 require_once $source.'/Api/Helpers/employee_payroll_helper.php';
 $resolved=payroll_resolve_deployment_schedule($db,1,$date);check($resolved['time_in']==='10:00:00','Production payroll resolver uses dated duty');
 $statusDay=payroll_timesheet_day_status(['time_in'=>$date.' 10:06:00','time_out'=>$date.' 19:00:00'],$date,$resolved,date('Y-m-d'));check($statusDay['is_late'],'Production attendance grace/late engine uses dated duty');
 $gps=['site_id'=>1,'latitude'=>8.43,'longitude'=>124.65,'accuracy'=>60,'geofence_radius'=>100];
 [$status]=$http('TeamLead/register_work_site.php',array_replace($gps,['site_id'=>2]),'lead3',true,true);check($status===403,'Cross-Team Lead GPS registration rejected');
 [$status]=$http('TeamLead/register_work_site.php',array_replace($gps,['accuracy'=>150]),'lead2',true,true);check($status===422,'Poor GPS accuracy rejected independently of radius');
 [$status]=$http('TeamLead/register_work_site.php',$gps,'lead2',true,true);check($status===200,'Actual selected site coordinates saved');
 $savedGps=$db->query('SELECT registered_latitude,registered_longitude,geofence_radius FROM deployment_sites WHERE SiteID=1')->fetch_assoc();check((float)$savedGps['registered_latitude']===8.43&&(float)$savedGps['registered_longitude']===124.65&&(int)$savedGps['geofence_radius']===100,'Stored coordinates and radius verified');
 require_once $source.'/Api/Helpers/job_order_request_workflow.php';
 check(jorw_registered_site($db,2,1,'Branch 1',1)['SiteID']==1,'Registered branch eligible for job request');
 try{jorw_registered_site($db,2,1,'Branch 2',2);throw new RuntimeException('Unregistered site accepted');}catch(DomainException $e){check(true,'Other unregistered branch remains ineligible');}

 fixture($db,'serviceagreement',['ClientCompanyID'=>1,'Status'=>'Active','SignedDocumentPath'=>'fixture.pdf']);
 fixture($db,'job_categories',['id'=>1,'category_name'=>'Fixture Category','is_active'=>1]);
 fixture($db,'job_classifications',['id'=>1,'category_id'=>1,'classification_name'=>'Fixture Classification','is_active'=>1]);
 fixture($db,'job_sub_classifications',['id'=>1,'classification_id'=>1,'sub_classification_name'=>'Fixture Subclass','is_active'=>1]);
 $jor=['company_id'=>1,'positions'=>[['position'=>'Fixture Worker','category_id'=>1,'classification_id'=>1,'sub_classification_id'=>1,'workers_needed'=>1,'site_id'=>2,'deployment_site'=>'Branch 2','work_schedule'=>'Day Shift']]];
 [$status,$blocked]=$http('TeamLead/submit_consolidated_jor.php',$jor,'lead2',true,true);check($status===403&&$blocked['error_code']==='WORK_SITE_NOT_REGISTERED','Actual JOR API rejects unregistered branch even when another branch registered');
 check((int)$db->query('SELECT COUNT(*) FROM job_order_requests')->fetch_row()[0]===0,'Blocked JOR writes no request');
 $jor['positions'][0]['site_id']=1;$jor['positions'][0]['deployment_site']='Branch 1';
 [$status,$createdJor,$body]=$http('TeamLead/submit_consolidated_jor.php',$jor,'lead2',true,true);check($status===200&&$createdJor['success'],'Actual JOR API accepts registered assigned branch: '.$body);
 $base=['action'=>'save_site','company_id'=>1,'site_id'=>1,'site_name'=>'Branch 1','site_address'=>'Updated test address','team_lead_id'=>3,'lead_mode'=>'existing','is_active'=>1];
 $users=(int)$db->query('SELECT COUNT(*) FROM users')->fetch_row()[0];
 [$status]=$http('Admin/client_companies.php',$base,'admin',false);check($status===403,'Site CSRF');
 [$status]=$http('Admin/client_companies.php',$base);check($status===200,'Existing Team Lead save');
 check((int)$db->query('SELECT TeamLeadID FROM deployment_sites WHERE SiteID=1')->fetch_row()[0]===3,'Selected site updated');
 check((int)$db->query('SELECT TeamLeadID FROM deployment_sites WHERE SiteID=2')->fetch_row()[0]===2,'Other site assignment unchanged');
 check((int)$db->query('SELECT COUNT(*) FROM users')->fetch_row()[0]===$users,'Existing Lead does not create account');
 check((int)$db->query('SELECT UserID FROM clientcompany WHERE CompanyID=1')->fetch_row()[0]===3,'Company saved assigned Team Lead');
 check($db->query('SELECT ContactPerson FROM clientcompany WHERE CompanyID=1')->fetch_row()[0]==='Second Lead','Company saved assigned Team Lead name');
 check((int)$db->query("SELECT COUNT(*) FROM notifications WHERE UserID=3 AND NotificationType='team_lead_site_assigned' AND RelatedRecord=1 AND IsRead=0")->fetch_row()[0]===1,'Assigned lead notified');
 [$status,$roster]=$http('TeamLead/team_deployments.php',null,'lead3');check($status===200&&count($roster['deployments'])===1&&(int)$roster['deployments'][0]['employee_id']===1,'Assigned lead can view transferred employee roster');
 [$status,$roster]=$http('TeamLead/team_deployments.php',null,'lead2');check($status===200&&count($roster['deployments'])===1&&(int)$roster['deployments'][0]['employee_id']===2,'Previous lead retains only other branch employees');
 check($db->query('SELECT SiteID FROM deployment WHERE DeploymentID=3')->fetch_row()[0]===null,'Ambiguous legacy employee is not moved to arbitrary branch');

 [$status]=$http('Admin/client_companies.php',array_replace($base,['site_id'=>2]));check($status===200,'Multi-site assignment');
 check((int)$db->query('SELECT COUNT(*) FROM deployment_sites WHERE TeamLeadID=3')->fetch_row()[0]===2,'One lead retains two sites');
 [$status]=$http('Admin/client_companies.php',array_replace($base,['team_lead_id'=>4]));check($status===422,'Wrong-role Team Lead rejected');
 $new=array_replace($base,['lead_mode'=>'new','tl_contact_number'=>'09234966660','tl_full_name'=>'New Test Lead','tl_email'=>'new.test.lead@gmail.com','tl_password'=>'Birch!58Lemon','tl_password_confirm'=>'Birch!58Lemon']);
 foreach([['tl_contact_number'=>''],['tl_contact_number'=>'0923'],['tl_email'=>'wrong@example.com'],['tl_password'=>'Admin@123','tl_password_confirm'=>'Admin@123'],['tl_password_confirm'=>'Mismatch'],['company_id'=>2]]as$invalid){[$status]=$http('Admin/client_companies.php',array_replace($new,$invalid));check($status===422,'Invalid new lead rejected '.implode(',',array_keys($invalid)));}
 check((int)$db->query('SELECT COUNT(*) FROM users')->fetch_row()[0]===$users,'Invalid requests leave no accounts');
 $db->query("CREATE TRIGGER fixture_site_failure BEFORE UPDATE ON deployment_sites FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Fixture failure'");
 [$status,$failure]=$http('Admin/client_companies.php',$new);check($status===422&&$failure['message']==='Unable to save deployment site.','Assignment failure reported safely');
 check((int)$db->query('SELECT COUNT(*) FROM users')->fetch_row()[0]===$users,'Assignment failure rolls back account');$db->query('DROP TRIGGER fixture_site_failure');
 [$status,$created]=$http('Admin/client_companies.php',$new);check($status===200,'New Team Lead created and assigned');
 $lead=$db->query("SELECT UsersID,Password,RoleID FROM users WHERE Email='new.test.lead@gmail.com'")->fetch_assoc();
 check(password_verify('Birch!58Lemon',$lead['Password'])&&$lead['Password']!=='Birch!58Lemon','Secure existing password hashing');
 check((int)$lead['RoleID']===(int)$roles['TeamLead'],'Server chooses Team Lead role');
 $phoneProfile=json_decode($db->query('SELECT ProfileJSON FROM teamlead_personal_profiles WHERE UserID='.(int)$lead['UsersID'])->fetch_row()[0],true);check($phoneProfile['MobileNumber']==='+639234966660','New Lead contact saved in personal profile and normalized');
 check((int)$db->query('SELECT TeamLeadID FROM deployment_sites WHERE SiteID=1')->fetch_row()[0]===(int)$lead['UsersID'],'New Lead site relationship');
 check((int)$db->query('SELECT UserID FROM clientcompany WHERE CompanyID=1')->fetch_row()[0]===(int)$lead['UsersID'],'New account saved as company Team Lead');
 fixture($db,'deployment_sites',['SiteID'=>3,'CompanyID'=>2,'Name'=>'Only branch','Address'=>'Only site','TeamLeadID'=>2,'IsActive'=>1]);
 fixture($db,'employee',['EmployeeID'=>4,'EmployeeNumber'=>'TEST4','FirstName'=>'Legacy','LastName'=>'Employee','IsActive'=>1]);
 fixture($db,'deployment',['DeploymentID'=>4,'EmployeeID'=>4,'CompanyID'=>2,'SiteID'=>null,'SiteSuperVisorID'=>2,'StartDate'=>'2020-01-01','EndDate'=>null,'StatusID'=>$activeStatus]);
 [$status]=$http('Admin/client_companies.php',array_replace($base,['company_id'=>2,'site_id'=>3,'site_name'=>'Only branch']));check($status===200,'Single site company reassigned');
 check((int)$db->query('SELECT SiteID FROM deployment WHERE DeploymentID=4')->fetch_row()[0]===3,'Legacy employee attached to sole company site');
 [$status,$roster]=$http('TeamLead/team_deployments.php?site_id=3',null,'lead3');check($status===200&&count($roster['deployments'])===1&&(int)$roster['deployments'][0]['employee_id']===4,'Legacy company employee visible in new lead site roster');

 [$status]=$http('Admin/client_companies.php',array_replace($new,['site_id'=>2]));check($status===422,'Duplicate account rejected');

 foreach(['admin','hr']as$offset=>$viewer){
  $uid=20+$offset;$aid=30+$offset;$jid=40+$offset;$appid=50+$offset;
  fixture($db,'users',['UsersID'=>$uid,'RoleID'=>$roles['Applicant'],'FullName'=>'Hire Fixture','Email'=>'hire'.$uid.'@synthetic.invalid','IsActive'=>1]);
  fixture($db,'applicants',['ApplicantID'=>$aid,'UserID'=>$uid,'FullName'=>'Hire Fixture','FirstName'=>'Hire','LastName'=>'Fixture','Email'=>'hire'.$uid.'@synthetic.invalid','Gender'=>'Female']);
  fixture($db,'joborder',['JobOrderID'=>$jid,'CompanyID'=>2,'Position'=>'Test Worker','Location'=>'Only site']);
  fixture($db,'job_applications',['ApplicationID'=>$appid,'ApplicantID'=>$aid,'JobOrderID'=>$jid,'ClientApprovalStatus'=>'Approved','HiredDate'=>null,'Remarks'=>'Contract uploaded: test']);
  $hire=['application_id'=>$appid,'sss_number'=>'SSS-test','philhealth_number'=>'PH-test','pagibig_number'=>'PI-test','daily_rate'=>800,'sunday_duty_allowance'=>'300'];
  [$status,$hired,$body]=$http($viewer==='admin'?'Admin/process_hire.php':'HR/convert_to_employee.php',$hire,$viewer,true,true);
  check($status===200&&($hired['success']??false),'Actual '.$viewer.' HTTP hire without monthly allowance: '.$body);
  $emp=$db->query('SELECT * FROM employee WHERE EmployeeID='.(int)$hired['employee_id'])->fetch_assoc();
  check((float)$emp['allowance']===0.0&&(float)$emp['daily_rate']===800.0&&(float)$emp['hourly_rate']===100.0&&(float)$emp['monthly_salary_credit']===20800.0&&(float)$emp['sunday_duty_allowance']===300.0,'Actual '.$viewer.' salary defaults and Sunday amount');
 }
 $document=$root.'/signed.pdf';file_put_contents($document,"%PDF-1.4\n%%EOF");
 $register=['action'=>'create','name'=>'Registered Client','address'=>'Company address','contact_number'=>'+63','industry'=>'retail','team_lead_id'=>2,'payroll_type'=>'A','agreement_start_date'=>'2026-10-09','agreement_contract_type'=>'Open','agreement_status'=>'Active','signed_document'=>new CURLFile($document,'application/pdf','signed.pdf')];
 foreach(['+63','+638123456789','+63912345678','+6391234567890','+63+639123456789','+63912letters']as$invalid){[$status]=$http('Admin/client_companies.php',array_replace($register,['contact_number'=>$invalid]));check($status===400,'Invalid server contact '.$invalid);}
 [$status,$client,$body]=$http('Admin/client_companies.php',array_replace($register,['contact_number'=>'+639123456789']));check($status===200,'Client registration with service agreement: '.$body);
 $id=(int)$client['company_id'];check((int)$db->query('SELECT COUNT(*) FROM deployment_sites WHERE CompanyID='.$id)->fetch_row()[0]===0,'Registration creates no deployment site');
 check((int)$db->query('SELECT COUNT(*) FROM serviceagreement WHERE ClientCompanyID='.$id)->fetch_row()[0]===1,'Service agreement retained');
 [$status,$view]=$http('Admin/client_companies.php?company_id='.$id);check($status===200&&$view['success'],'View Client retained');
 [$status]=$http('Admin/client_companies.php',['action'=>'update','company_id'=>$id,'name'=>'Edited Client','address'=>'Edited address','contact_number'=>'+639123456789','industry'=>'Logistics','payroll_type'=>'B','team_lead_id'=>2]);check($status===200,'Edit Client retained');
 check($db->query('SELECT PayrollType FROM clientcompany WHERE CompanyID='.$id)->fetch_row()[0]==='B','Payroll Type retained');
 [$status]=$http('Admin/client_companies.php',array_replace($base,['site_id'=>0,'company_id'=>$id,'site_name'=>'New branch']));check($status===200,'Add site available after registration');
 [$status,$captcha]=$http('Login/captcha_gen.php',null,'guest');
 $answer=(int)$captcha['numerator']+(int)$captcha['denominator'];
 [$status,$login,$body]=$http('Login/login.php',['username'=>'new.test.lead@gmail.com','password'=>'Birch!58Lemon','captcha_challenge_id'=>$captcha['challenge_id'],'captcha_input'=>(string)$answer],'guest');
 check($status===200&&$login['success']&&str_contains(strtolower($login['redirect']),'teamlead'),'New Team Lead actual login: '.$body);
 $result=['passed'=>count($checks),'tests'=>$checks,'scope'=>'Actual HTTP endpoints; disposable schema/session/uploads only'];file_put_contents(__DIR__.'/http_results.json',json_encode($result,JSON_PRETTY_PRINT));echo json_encode($result,JSON_PRETTY_PRINT).PHP_EOL;
} finally {
 if(is_resource($process)){proc_terminate($process);proc_close($process);}
 if(preg_match('/^codex_clients_[a-f0-9]{12}$/',$database))$db->query('DROP DATABASE IF EXISTS `'.$database.'`');
 $resolved=realpath($root);$temp=realpath(sys_get_temp_dir());
 if($resolved&&$temp&&str_starts_with($resolved,$temp.DIRECTORY_SEPARATOR)&&basename($resolved)===$database){$items=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($resolved,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($items as$item){if($item->isDir())rmdir($item->getPathname());else unlink($item->getPathname());}rmdir($resolved);}
}
