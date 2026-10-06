<?php
/** CLI-only tests against a disposable empty schema and copied production APIs. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/../../Api/Db_connection/db_connect.php';
$live=$conn; $source=$dbname; $name='codex_documents_'.bin2hex(random_bytes(6));
$root=sys_get_temp_dir().'/onazul-documents-'.bin2hex(random_bytes(8));
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

 $role=(int)$db->query("SELECT RolesID FROM roles WHERE RoleName='Employee'")->fetch_row()[0];
 $user=ep_insert($db,'users',['RoleID'=>$role,'FullName'=>'TEST Employee','Email'=>'doc-test@example.invalid','Password'=>password_hash(bin2hex(random_bytes(12)),PASSWORD_DEFAULT)]);
 $other=ep_insert($db,'users',['RoleID'=>$role,'FullName'=>'TEST Other','Email'=>'doc-other@example.invalid','Password'=>password_hash(bin2hex(random_bytes(12)),PASSWORD_DEFAULT)]);
 $app=ep_insert($db,'applicants',['UserID'=>$user,'FullName'=>'TEST','Email'=>'doc-test@example.invalid']);
 ep_insert($db,'applicants',['UserID'=>$other,'FullName'=>'TEST','Email'=>'doc-other@example.invalid']);
 $doc=ep_insert($db,'applicant_uploaded_docs',['ApplicantID'=>$app,'DocType'=>'Certification','DocCategory'=>'academic','FilePath'=>'../../Uploads/Documents/original.png','OriginalFileName'=>'old.png','IsVerified'=>1,'VerifiedBy'=>$user,'VerifiedDate'=>'2026-01-01']);
 $csrf=bin2hex(random_bytes(32));$sid=ep_session($root,$user,'Employee',['employee_document_csrf'=>$csrf]);$otherSid=ep_session($root,$other,'Employee',['employee_document_csrf'=>$csrf]);
 mkdir($root.'/upload_tmp',0700,true);
 $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$err);$address=stream_socket_get_name($socket,false);fclose($socket);
 $server=proc_open([PHP_BINARY,'-d','display_errors=stderr','-d','display_startup_errors=0','-d','upload_tmp_dir='.$root.'/upload_tmp','-S',$address,'-t',$root],[0=>['pipe','r'],1=>['file',$root.'/server.log','a'],2=>['file',$root.'/server.log','a']],$pipes);fclose($pipes[0]);usleep(400000);
 $image=imagecreatetruecolor(4,4);imagepng($image,$root.'/test.png');imagedestroy($image);file_put_contents($root.'/fake.png','not an image');
 $url='http://'.$address.'/Api/Employee/upload_document.php';
 function doc_http($url,$sid,$fields,$expected){$ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$fields,CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIE=>'PHPSESSID='.$sid,CURLOPT_TIMEOUT=>15]);$raw=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);if($code!==$expected)throw new RuntimeException('Upload '.$code.' expected '.$expected.': '.$raw);$data=json_decode($raw,true);if(!is_array($data))throw new RuntimeException('Invalid JSON: '.substr($raw,0,500));return $data;}
 $fields=['document'=>new CURLFile($root.'/test.png','image/png','updated.png'),'csrf_token'=>$csrf,'replace_doc_id'=>$doc,'doc_type'=>'Tampered type'];
 doc_http($url,$otherSid,$fields,403);ep_assert(true,'Cross-account replacement rejected');
 $bad=$fields;$bad['csrf_token']='wrong';doc_http($url,$sid,$bad,403);ep_assert(true,'CSRF replacement rejected');
 $bad=$fields;$bad['document']=new CURLFile($root.'/fake.png','image/png','fake.png');doc_http($url,$sid,$bad,415);ep_assert($db->query('SELECT OriginalFileName FROM applicant_uploaded_docs WHERE DocID='.$doc)->fetch_row()[0]==='old.png','Invalid upload preserves original');
 $response=doc_http($url,$sid,$fields,200);if(empty($response['success'])) throw new RuntimeException('Replace: '.json_encode($response));ep_assert(true,'Replace succeeds');
 $row=$db->query('SELECT * FROM applicant_uploaded_docs WHERE DocID='.$doc)->fetch_assoc();ep_assert($row['OriginalFileName']==='updated.png' && $row['DocType']==='Certification' && $row['DocCategory']==='academic','Document identity type and category preserved');
 ep_assert((int)$row['IsVerified']===0 && $row['VerifiedBy']===null && $row['VerifiedDate']===null,'Replacement requires fresh verification');
 ep_assert((int)$db->query('SELECT COUNT(*) FROM applicant_uploaded_docs')->fetch_row()[0]===1,'Replacement creates no duplicate record');
 ep_assert(is_file($root.'/'.substr($row['FilePath'],6)),'Updated document stored');
 unset($fields['replace_doc_id']);$fields['doc_type']='General Document';doc_http($url,$sid,$fields,200);ep_assert((int)$db->query('SELECT COUNT(*) FROM applicant_uploaded_docs')->fetch_row()[0]===2,'New uploads remain supported');
 ep_assert(ep_checksum($live)===$before,'Production data unchanged');
 $result=['success'=>true,'checks'=>$checks];
} catch(Throwable $e){$result=['success'=>false,'checks'=>$checks,'error'=>$e->getMessage()];file_put_contents(__DIR__.'/results.json',json_encode($result,JSON_PRETTY_PRINT));echo json_encode($result);}
finally{if(is_resource($server)){proc_terminate($server);proc_close($server);}if($created)$live->query('DROP DATABASE `'.$name.'`');if(is_dir($root))ep_remove($root);}
file_put_contents(__DIR__.'/results.json',json_encode($result,JSON_PRETTY_PRINT));echo json_encode($result,JSON_PRETTY_PRINT);exit(empty($result['success'])?1:0);
