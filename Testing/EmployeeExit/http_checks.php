<?php
/** HTTP checks use copied APIs wired exclusively to the disposable schema. */
function exit_http_checks(mysqli $db,string $schema): void {
 $tmp=sys_get_temp_dir().'/codex_exit_http_'.bin2hex(random_bytes(6));mkdir($tmp);mkdir($tmp.'/sessions');
 $copy=function(string $from,string $to)use(&$copy):void{if(is_dir($from)){if(!is_dir($to))mkdir($to);foreach(scandir($from) as $n)if($n!=='.'&&$n!=='..')$copy($from.'/'.$n,$to.'/'.$n);}else copy($from,$to);};
 $remove=function(string $path)use(&$remove,$tmp):void{if(!str_starts_with($path,$tmp.'/'))throw new RuntimeException('Unsafe temporary cleanup');if(is_dir($path)){foreach(scandir($path) as $n)if($n!=='.'&&$n!=='..')$remove($path.'/'.$n);rmdir($path);}else unlink($path);};
 $server=null;
 try{
  $copy(__DIR__.'/../../Api',$tmp.'/Api');mkdir($tmp.'/uploads');mkdir($tmp.'/uploads/employee_exit');mkdir($tmp.'/uploads/quitclaims');
  $config=$tmp.'/Api/Db_connection/db_connect.php';$source=file_get_contents($config);$source=str_replace("\$dbname = 'onazul_hris_db';","\$dbname = '".$schema."';",$source);file_put_contents($config,$source);
  $sessionFile=$tmp.'/Api/Common/session_init.php';file_put_contents($sessionFile,str_replace('<?php','<?php ini_set("session.save_path",'.var_export($tmp.'/sessions',true).');',file_get_contents($sessionFile)));
  foreach([10=>'HR',12=>'Accounting',13=>'TeamLead',14=>'TeamLead',20=>'Employee',21=>'Employee',22=>'Employee'] as $uid=>$role){$session=['user_id'=>$uid,'role'=>$role,'full_name'=>'Synthetic '.$role,'last_activity'=>time(),'exit_csrf'=>str_repeat('a',64)];$encoded='';foreach($session as $k=>$v)$encoded.=$k.'|'.serialize($v);file_put_contents($tmp.'/sessions/sess_exit'.$uid,$encoded);}
  $port=18900+random_int(1,500);$pipes=[];$server=proc_open([PHP_BINARY,'-S','127.0.0.1:'.$port,'-t',$tmp],[0=>['pipe','r'],1=>['file',$tmp.'/server.log','a'],2=>['file',$tmp.'/server.log','a']],$pipes);if(!is_resource($server))throw new RuntimeException('Test server failed');fclose($pipes[0]);
  $call=function(int $uid,string $path,?array $data=null,bool $csrf=true)use($port):array{
   $c=curl_init('http://127.0.0.1:'.$port.$path);$headers=['Cookie: PHPSESSID=exit'.$uid];if($csrf)$headers[]='X-Exit-CSRF: '.str_repeat('a',64);curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>15]);if($data!==null){curl_setopt($c,CURLOPT_POST,true);curl_setopt($c,CURLOPT_POSTFIELDS,$data);} $body=curl_exec($c);$code=curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);return ['code'=>$code,'body'=>$body,'json'=>json_decode((string)$body,true)];
  };
  for($i=0;$i<30;$i++){if($call(10,'/Api/HR/resignations.php')['code'])break;usleep(100000);}
  check($call(0,'/Api/HR/resignations.php')['code']===403,'HTTP anonymous exit access denied');
  $r=$call(10,'/Api/HR/resignations.php');if($r['code']!==200)throw new RuntimeException('Synthetic HTTP failure: '.json_encode($r).' Log: '.file_get_contents($tmp.'/server.log'));check($r['json']['csrf_token']===str_repeat('a',64),'HTTP active HR gets workflow and CSRF token');
  check($call(10,'/Api/HR/resignations.php',['action'=>'register','employee_id'=>3,'received_date'=>date('Y-m-d')],false)['code']===403,'HTTP missing CSRF blocks mutation');
  $r=$call(10,'/Api/HR/resignations.php',['action'=>'register','employee_id'=>3,'received_date'=>date('Y-m-d')]);check($r['code']===200&&$r['json']['success'],'HTTP physical letter registration succeeds');$id=$r['json']['id'];
  check($call(21,'/Api/Employee/resignation_request.php',['action'=>'register'])['code']===403,'HTTP online employee resignation disabled');
  check($call(22,'/Api/Employee/resignation_request.php?id='.$id)['code']===200,'HTTP employee can track own physical request');check($call(21,'/Api/Employee/resignation_request.php?id='.$id)['code']===404,'HTTP foreign employee record hidden');
  check($call(13,'/Api/TeamLead/clearance.php?id='.$id)['code']===404,'HTTP foreign site Team Lead record hidden');check($call(14,'/Api/TeamLead/clearance.php?id='.$id)['code']===200,'HTTP assigned Team Lead access succeeds');
  file_put_contents($tmp.'/fake.pdf','<?php echo "invalid";');$bad=['action'=>'attach_document','resignation_id'=>$id,'document_type'=>'letter','document'=>new CURLFile($tmp.'/fake.pdf','application/pdf','fake.pdf')];check($call(10,'/Api/HR/resignations.php',$bad)['code']===422,'HTTP forged PDF content rejected');
  $png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aDrsAAAAASUVORK5CYII=');file_put_contents($tmp.'/scan.png',$png);
  $upload=['action'=>'attach_document','resignation_id'=>$id,'document_type'=>'letter','document'=>new CURLFile($tmp.'/scan.png','image/png','scan.png')];$r=$call(10,'/Api/HR/resignations.php',$upload);check($r['code']===200&&$r['json']['success'],'HTTP optional scan stored securely');
  check($call(10,'/Api/HR/resignations.php',$upload)['code']===409,'HTTP duplicate scan preserves original');
  $doc='/Api/Common/view_exit_document.php?id='.$id.'&type=letter';check($call(22,$doc)['body']===$png,'HTTP owner can view protected scan');check($call(21,$doc)['code']===403,'HTTP foreign employee cannot read scan');check($call(13,$doc)['code']===403,'HTTP foreign Team Lead cannot read scan');check($call(14,'/Api/Common/view_exit_document.php?id='.$id.'&type=quitclaim')['code']===403,'HTTP Team Lead quitclaim access denied');
  check($call(20,'/Api/Login/check_session.php')['code']===401,'HTTP existing resigned employee session revoked');check($call(20,'/Api/Employee/resignation_request.php')['code']===403,'HTTP revoked session cannot regain exit access');
  check($call(21,'/Api/Login/check_session.php')['code']===200,'HTTP active employee session regression check');
  $case=$call(10,'/Api/HR/resignations.php')['json']['awol_cases'][0];check(count($case['history'])>=3,'HTTP AWOL decision history visible to HR');
 }finally{
  if(is_resource($server)){proc_terminate($server);proc_close($server);}
  foreach(scandir($tmp) as $n)if($n!=='.'&&$n!=='..')$remove($tmp.'/'.$n);rmdir($tmp);
 }
}
