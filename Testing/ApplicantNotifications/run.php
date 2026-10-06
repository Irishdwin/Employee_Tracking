<?php
/** CLI-only tests against a disposable empty schema and copied production APIs. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/../../Api/Db_connection/db_connect.php';
$live=$conn; $source=$dbname; $name='codex_notif_'.bin2hex(random_bytes(6));
$root=sys_get_temp_dir().'/onazul-notif-'.bin2hex(random_bytes(8));
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


 $role=(int)$db->query("SELECT RolesID FROM roles WHERE RoleName='Applicant'")->fetch_row()[0];
 $user=ep_insert($db,'users',['RoleID'=>$role,'FullName'=>'TEST','Email'=>'notif@example.invalid','Password'=>'test']);$other=ep_insert($db,'users',['RoleID'=>$role,'FullName'=>'OTHER','Email'=>'other@example.invalid','Password'=>'test']);
 $app=ep_insert($db,'applicants',['UserID'=>$user,'FullName'=>'TEST','Email'=>'notif@example.invalid']);$company=ep_insert($db,'clientcompany',['Name'=>'TEST']);$job=ep_insert($db,'joborder',['CompanyID'=>$company,'Position'=>'TEST','RequiredCount'=>1]);$application=ep_insert($db,'job_applications',['ApplicantID'=>$app,'JobOrderID'=>$job]);
 require __DIR__.'/../../Api/Helpers/notification_helper.php';
 foreach(['application_submitted','interview_scheduled','application_endorsed','client_approved','client_rejected','application_status','application_hired']as$type)ep_assert(createApplicationNotification($db,$application,$type,'TEST '.$type,'TEST status update'),'Creates '.$type);
 $foreign=ep_insert($db,'notifications',['UserID'=>$other,'Title'=>'OTHER']);$sid=ep_session($root,$user,'Applicant');
 $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$err);$address=stream_socket_get_name($socket,false);fclose($socket);
 $server=proc_open([PHP_BINARY,'-d','display_errors=stderr','-d','display_startup_errors=0','-S',$address,'-t',$root],[0=>['pipe','r'],1=>['file',$root.'/server.log','a'],2=>['file',$root.'/server.log','a']],$pipes);fclose($pipes[0]);usleep(400000);
 $url='http://'.$address.'/Api/Applicant/notifications.php';$get=ep_http($url,$sid);ep_assert(count($get['notifications'])===7&&$get['unread_count']===7,'Status notifications reach own inbox');
 ep_http($url,$sid,['action'=>'mark_read','notification_id'=>$foreign]);ep_assert((int)$db->query('SELECT IsRead FROM notifications WHERE NotificationID='.$foreign)->fetch_row()[0]===0,'Other account notification protected');
 ep_http($url,$sid,['action'=>'mark_read','notification_id'=>$get['notifications'][0]['id']]);ep_assert(ep_http($url,$sid)['unread_count']===6,'Only selected notification becomes read');ep_http($url,$sid,['action'=>'mark_all_read']);ep_assert(ep_http($url,$sid)['unread_count']===0,'Mark all read');ep_assert(ep_checksum($live)===$before,'Production unchanged');$result=['success'=>true,'checks'=>$checks];
} catch(Throwable $e){$result=['success'=>false,'checks'=>$checks,'error'=>$e->getMessage()];file_put_contents(__DIR__.'/results.json',json_encode($result,JSON_PRETTY_PRINT));echo json_encode($result);}
finally{if(is_resource($server)){proc_terminate($server);proc_close($server);}if($created)$live->query('DROP DATABASE `'.$name.'`');if(is_dir($root))ep_remove($root);}
file_put_contents(__DIR__.'/results.json',json_encode($result,JSON_PRETTY_PRINT));echo json_encode($result,JSON_PRETTY_PRINT);exit(empty($result['success'])?1:0);
