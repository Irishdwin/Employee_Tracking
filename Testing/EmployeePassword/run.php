<?php
/** CLI-only tests against a disposable empty schema and copied production APIs. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/../../Api/Db_connection/db_connect.php';
$live=$conn; $source=$dbname; $name='codex_password_'.bin2hex(random_bytes(6));
$root=sys_get_temp_dir().'/onazul-password-'.bin2hex(random_bytes(8));
$server=null; $created=false; $checks=[]; $result=[];
function ep_copy($from,$to) { if (!is_dir($to)) mkdir($to,0700,true); foreach(new DirectoryIterator($from) as $f) {if($f->isDot())continue;$dest=$to.'/'.$f->getFilename();if($f->isDir())ep_copy($f->getPathname(),$dest);else copy($f->getPathname(),$dest);} }
function ep_insert($db,$table,$row) { $q=$db->prepare('INSERT INTO `'.$table.'` (`'.implode('`,`',array_keys($row)).'`) VALUES ('.implode(',',array_fill(0,count($row),'?')).')');$values=array_values($row);$q->bind_param(str_repeat('s',count($values)),...$values);$q->execute();return $db->insert_id; }
function ep_assert($ok,$label) {global $checks;if(!$ok)throw new RuntimeException($label);$checks[$label]='PASS';file_put_contents(__DIR__.'/progress.json',json_encode($checks,JSON_PRETTY_PRINT));}
function ep_session($root,$user,$role,$extra=[]) {ini_set('session.save_path',$root.'/sessions');ini_set('session.use_cookies',0);ini_set('session.cache_limiter','');$id=bin2hex(random_bytes(16));session_id($id);session_start();$_SESSION=array_merge(['user_id'=>$user,'role'=>$role,'last_activity'=>time()],$extra);session_write_close();return $id;}
function ep_http($url,$sid,$body=null,$expected=200,$form=false) {
 $headers="Cookie: PHPSESSID=$sid\r\nX-Requested-With: XMLHttpRequest\r\nContent-Type: ".($form?'application/x-www-form-urlencoded':'application/json')."\r\n";
 $ctx=stream_context_create(['http'=>['method'=>$body===null?'GET':'POST','header'=>$headers,'content'=>$body===null?'':($form?http_build_query($body):json_encode($body)),'ignore_errors'=>true,'timeout'=>20]]);
 $raw=file_get_contents($url,false,$ctx);preg_match('/\s(\d{3})\s/',$http_response_header[0]??'',$match);$code=(int)($match[1]??0);$data=json_decode($raw,true);
 if($code!==$expected||!is_array($data))throw new RuntimeException('Unexpected response '.$code.' expected '.$expected.': '.substr($raw,0,200));return $data;
}
function ep_checksum($db) {$out=[];foreach(['users','employee','applicants','job_applications','notifications','deployment','payroll','attendance'] as $t)$out[$t]=$db->query('CHECKSUM TABLE `'.$t.'`')->fetch_assoc()['Checksum'];return $out;}
function ep_remove($path) {foreach(new DirectoryIterator($path)as$f){if($f->isDot())continue;if($f->isDir())ep_remove($f->getPathname());else unlink($f->getPathname());}rmdir($path);}
try {
 $before=ep_checksum($live);$live->query('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4');$created=true;
 $db=new mysqli($servername,$username,$password,$name);$db->set_charset('utf8mb4');$db->query('SET FOREIGN_KEY_CHECKS=0');
 foreach($live->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'")->fetch_all()as$t)$db->query($live->query('SHOW CREATE TABLE `'.$t[0].'`')->fetch_row()[1]);$db->query('SET FOREIGN_KEY_CHECKS=1');
 foreach(['roles','status']as$t)foreach($live->query('SELECT * FROM `'.$t.'`')->fetch_all(MYSQLI_ASSOC)as$row)ep_insert($db,$t,$row);
 ep_copy(__DIR__.'/../../Api',$root.'/Api');mkdir($root.'/sessions',0700,true);
 $path=$root.'/Api/Common/session_init.php';file_put_contents($path,"<?php\nini_set('session.save_path',".var_export($root.'/sessions',true).");\n".substr(file_get_contents($path),5));
 $path=$root.'/Api/Db_connection/db_connect.php';file_put_contents($path,str_replace("\$dbname = '$source';","\$dbname = '$name';",file_get_contents($path)));
 foreach(['hris_pdo.php','attendance_pdo.php']as$file){$path=$root.'/Api/Helpers/'.$file;if(is_file($path))file_put_contents($path,str_replace('dbname='.$source.';','dbname='.$name.';',file_get_contents($path)));}
 $roles=[];foreach($db->query('SELECT RolesID,RoleName FROM roles')->fetch_all(MYSQLI_ASSOC)as$r)$roles[$r['RoleName']]=$r['RolesID'];
 require $root.'/Api/Helpers/google_auth_rbac.php';require $root.'/Api/Helpers/hire_applicant.php';
 $admin=ep_insert($db,'users',['RoleID'=>$roles['Admin'],'FullName'=>'TEST Admin','Email'=>'password-admin@example.invalid','Password'=>password_hash(bin2hex(random_bytes(16)),PASSWORD_DEFAULT)]);
 ini_set('session.save_path',$root.'/sessions');ini_set('session.use_cookies',0);ini_set('session.cache_limiter','');session_id(bin2hex(random_bytes(16)));session_start();
 google_rbac_process_verified_email($db,'password-google@example.invalid','TEST Google','http://localhost/Employee_Tracking/Api/Applicant/google_oauth_callback.php');
 $user=(int)$_SESSION['user_id'];session_write_close();
 $hash=$db->query('SELECT Password FROM users WHERE UsersID='.$user)->fetch_assoc()['Password'];
 ep_assert((int)$db->query('SELECT LocalPasswordConfigured FROM users WHERE UsersID='.$user)->fetch_row()[0]===0,'Google new account requires setup');
 $app=(int)$db->query('SELECT ApplicantID FROM applicants WHERE UserID='.$user)->fetch_row()[0];
 $company=ep_insert($db,'clientcompany',['Name'=>'TEST Password Company']);
 ep_insert($db,'deployment_sites',['CompanyID'=>$company,'Name'=>'TEST site','Address'=>'TEST site']);
 $job=ep_insert($db,'joborder',['CompanyID'=>$company,'Position'=>'TEST Worker','Location'=>'TEST site','RequiredCount'=>2]);
 $application=ep_insert($db,'job_applications',['ApplicantID'=>$app,'JobOrderID'=>$job,'ClientApprovalStatus'=>'Approved','Remarks'=>'Contract uploaded: test.pdf']);
 $hire=hireApplicantFromApplication($db,$application,$admin,'1111111111','222222222222','333333333333',1000,0);
 if (empty($hire['success'])) throw new RuntimeException('Hire: '.($hire['message']??'failed'));
 ep_assert(true,'Production hiring converts Google applicant');
 ep_assert($db->query('SELECT Password FROM users WHERE UsersID='.$user)->fetch_row()[0]===$hash,'Hiring preserves original hash');
 ep_assert((int)$db->query("SELECT COUNT(*) FROM notifications WHERE UserID=$user AND NotificationType='employee_password_setup'")->fetch_row()[0]===1,'Hire creates setup notification');
 createEmployeePasswordNotification($db,$user);
 ep_assert((int)$db->query("SELECT COUNT(*) FROM notifications WHERE UserID=$user AND NotificationType='employee_password_setup'")->fetch_row()[0]===1,'Notification retry deduplicates');
 $retry=hireApplicantFromApplication($db,$application,$admin,'1','2','3',1000,0);ep_assert(empty($retry['success']),'Hire retry creates no duplicate employee');
 $existingPassword='Fjord!Maple42';$changedPassword='Cloud!Birch84';
 $other=ep_insert($db,'users',['RoleID'=>$roles['Employee'],'FullName'=>'TEST Existing','Email'=>'password-existing@example.invalid','Password'=>password_hash($existingPassword,PASSWORD_DEFAULT)]);
 ep_insert($db,'employee',['FirstName'=>'TEST','LastName'=>'Existing','Email'=>'password-existing@example.invalid','EmployeeNumber'=>'TEST-PASSWORD-2']);
 $otherHash=$db->query('SELECT Password FROM users WHERE UsersID='.$other)->fetch_row()[0];
 $sid=ep_session($root,$user,'Employee');$otherSid=ep_session($root,$other,'Employee');$adminSid=ep_session($root,$admin,'Admin');
 $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$err);$address=stream_socket_get_name($socket,false);fclose($socket);
 $server=proc_open([PHP_BINARY,'-S',$address,'-t',$root],[0=>['pipe','r'],1=>['file',$root.'/server.log','a'],2=>['file',$root.'/server.log','a']],$pipes);fclose($pipes[0]);usleep(400000);$base='http://'.$address;$api=$base.'/Api/Employee/password.php';
 $state=ep_http($api,$sid);ep_assert($state['setup_required'],'Setup state API');
 ep_http($api,'',null,403);ep_http($api,$adminSid,null,403);
 $forgedRole=ep_session($root,$admin,'Employee');ep_http($api,$forgedRole,null,403);
 ep_assert(true,'Anonymous, non-Employee and stale forged role rejected');
 $body=['action'=>'setup','csrf_token'=>$state['csrf_token'],'new_password'=>$existingPassword,'confirm_password'=>$existingPassword];
 ep_http($api,$sid,array_merge($body,['csrf_token'=>'invalid']),403);ep_assert(true,'CSRF rejected');
 ep_http($api,$sid,array_merge($body,['user_id'=>$other]),400);ep_assert($db->query('SELECT Password FROM users WHERE UsersID='.$other)->fetch_row()[0]===$otherHash,'IDOR rejected without changing other account');
 foreach([''=>'empty','Ab1!'=>'short','fjord!maple42'=>'uppercase','FJORD!MAPLE42'=>'lowercase','Fjord!Maple'=>'number','FjordMaple42'=>'special','Admin@123'=>'predictable']as$pwd=>$label){ep_http($api,$sid,array_merge($body,['new_password'=>$pwd,'confirm_password'=>$pwd]),422);ep_assert($db->query('SELECT Password FROM users WHERE UsersID='.$user)->fetch_row()[0]===$hash,'Policy rejects '.$label);}
 ep_http($api,$sid,array_merge($body,['confirm_password'=>'different']),422);ep_assert(true,'Confirmation mismatch rejected');
 ep_http($api,$sid,$body);ep_assert(password_verify($existingPassword,$db->query('SELECT Password FROM users WHERE UsersID='.$user)->fetch_row()[0]),'Setup stores verified hash');
 ep_assert((int)$db->query("SELECT IsRead FROM notifications WHERE UserID=$user AND NotificationType='employee_password_setup'")->fetch_row()[0]===1,'Setup resolves unread reminder');
 ep_http($api,$sid,$body,403);
 $fresh=ep_http($api,$sid);$body['csrf_token']=$fresh['csrf_token'];ep_http($api,$sid,$body,409);ep_assert(true,'Setup replay rejected');
 $login=ep_session($root,0,'',['captcha_code'=>123]);$loginUrl=$base.'/Api/Login/login.php';
 ep_http($loginUrl,$login,['username'=>'password-google@example.invalid','password'=>$existingPassword,'captcha_input'=>'0'],400,true);ep_assert(true,'CAPTCHA still enforced');
 $logged=ep_http($loginUrl,$login,['username'=>'password-google@example.invalid','password'=>$existingPassword,'captcha_input'=>'123'],200,true);ep_assert(str_contains($logged['redirect'],'Employee/employee_dashboard.html'),'New password normal login Employee redirect');
 $state=ep_http($api,$sid);ep_assert(!$state['setup_required']&&!$state['state_unknown'],'Configured state after setup');
 $change=['action'=>'change','csrf_token'=>$state['csrf_token'],'old_password'=>'wrong','new_password'=>$changedPassword,'confirm_password'=>$changedPassword];
 ep_http($api,$sid,$change,422);ep_assert(password_verify($existingPassword,$db->query('SELECT Password FROM users WHERE UsersID='.$user)->fetch_row()[0]),'Incorrect old password preserves hash');
 $change['old_password']=$existingPassword;ep_http($api,$sid,$change);
 ep_http($loginUrl,$login,['username'=>'password-google@example.invalid','password'=>$existingPassword,'captcha_input'=>'123'],401,true);ep_http($loginUrl,$login,['username'=>'password-google@example.invalid','password'=>$changedPassword,'captcha_input'=>'123'],200,true);ep_assert(true,'Old password fails and changed password logs in');
 $legacyState=ep_http($api,$otherSid);ep_assert(!$legacyState['setup_required']&&$legacyState['state_unknown'],'Existing unknown account not forced to reset');
 ep_http($api,$otherSid,['action'=>'change','csrf_token'=>$legacyState['csrf_token'],'old_password'=>$existingPassword,'new_password'=>$changedPassword,'confirm_password'=>$changedPassword]);ep_assert(true,'Existing Employee changes working password');
 $state=ep_http($api,$otherSid);
 $wrong=['action'=>'change','csrf_token'=>$state['csrf_token'],'old_password'=>'incorrect','new_password'=>$existingPassword,'confirm_password'=>$existingPassword];
 for($i=0;$i<5;$i++)ep_http($api,$otherSid,$wrong,422);
 ep_http($api,$otherSid,$wrong,429);ep_assert(true,'Repeated incorrect current passwords throttled');
 $db->query('UPDATE users SET LocalPasswordConfigured=0 WHERE UsersID='.$other);
 $resetSession=ep_session($root,0,'',['forgot_password_otp'=>['email'=>'password-existing@example.invalid','user_id'=>$other,'verified'=>true,'verified_at'=>time()]]);
 ep_http($base.'/Api/Login/forgot_password.php',$resetSession,['action'=>'reset_password','email'=>'password-existing@example.invalid','new_password'=>$existingPassword,'confirm_password'=>$existingPassword],200,true);
 ep_assert((int)$db->query('SELECT LocalPasswordConfigured FROM users WHERE UsersID='.$other)->fetch_row()[0]===1&&password_verify($existingPassword,$db->query('SELECT Password FROM users WHERE UsersID='.$other)->fetch_row()[0]),'Existing verified OTP reset configures local password');
 $postHash=$db->query('SELECT Password FROM users WHERE UsersID='.$user)->fetch_row()[0];session_id(bin2hex(random_bytes(16)));session_start();$google=google_rbac_process_verified_email($db,'password-google@example.invalid','TEST Google','http://localhost/Employee_Tracking/Api/Applicant/google_oauth_callback.php');session_write_close();
 ep_assert($google['role']==='Employee'&&$db->query('SELECT Password FROM users WHERE UsersID='.$user)->fetch_row()[0]===$postHash,'Verified Google RBAC retains Employee password');
 $db->query('UPDATE users SET LocalPasswordConfigured=NULL WHERE UsersID='.$other);
 $legacy=ep_session($root,$other,'Employee');$state=ep_http($api,$legacy);
 $setupBody=['action'=>'setup','csrf_token'=>$state['csrf_token'],'new_password'=>$changedPassword,'confirm_password'=>$changedPassword];
 ep_http($api,$legacy,$setupBody,409);ep_assert(true,'Legacy setup requires ownership verification');
 foreach(['mismatched'=>['user_id'=>$user,'email'=>'password-google@example.invalid','verified_at'=>time()], 'expired'=>['user_id'=>$other,'email'=>'password-existing@example.invalid','verified_at'=>time()-601]]as$case=>$grant) {
  $session=ep_session($root,$other,'Employee',['forgot_password_otp'=>array_merge($grant,['verified'=>true])]);$state=ep_http($api,$session);$setupBody['csrf_token']=$state['csrf_token'];ep_http($api,$session,$setupBody,409);ep_assert(true,'Legacy setup rejects '.$case.' OTP grant');
 }
 $session=ep_session($root,$other,'Employee',['forgot_password_otp'=>['user_id'=>$other,'email'=>'password-existing@example.invalid','verified'=>true,'verified_at'=>time()]]);$state=ep_http($api,$session);ep_assert($state['setup_required'],'Verified own-email OTP enables legacy setup');
 $setupBody['csrf_token']=$state['csrf_token'];ep_http($api,$session,$setupBody);ep_assert(password_verify($changedPassword,$db->query('SELECT Password FROM users WHERE UsersID='.$other)->fetch_row()[0]),'Legacy setup completes without unknown old password');
 ep_assert(!ep_http($api,$session)['setup_required'],'OTP setup authorization consumed');
 $db->query('UPDATE users SET LocalPasswordConfigured=NULL WHERE UsersID='.$other);
 $googleSession=ep_session($root,$other,'Employee',['verified_google_login'=>['user_id'=>$other,'at'=>time()]]);ep_assert(ep_http($api,$googleSession)['setup_required'],'Recent verified Google login enables legacy setup');
 $staleGoogle=ep_session($root,$other,'Employee',['verified_google_login'=>['user_id'=>$other,'at'=>time()-601]]);ep_assert(!ep_http($api,$staleGoogle)['setup_required'],'Expired Google proof cannot enable legacy setup');
 ep_assert(ep_checksum($live)===$before,'Production data unchanged');
 $result=['success'=>true,'checks'=>$checks,'google_provider_interaction'=>'NOT VERIFIED: verified-email production RBAC tested; external Google consent not exercised'];
} catch(Throwable $e) {$result=['success'=>false,'checks'=>$checks,'error'=>$e->getMessage()];}
finally {if(is_resource($server)){proc_terminate($server);proc_close($server);}if($created)$live->query('DROP DATABASE `'.$name.'`');if(is_dir($root))ep_remove($root);}
file_put_contents(__DIR__.'/results.json',json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;exit(empty($result['success'])?1:0);
