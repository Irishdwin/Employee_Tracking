<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/../../Api/Login/captcha_helper.php';
$checks=[];
function verify(bool $ok,string $label): void {global $checks;if(!$ok)throw new RuntimeException($label);$checks[]=$label;}
$_SESSION=[];
foreach([[2,5],[9,12],[3,79],[45,27]] as [$a,$b]) {
    $id=bin2hex(random_bytes(32));$_SESSION['login_captchas'][$id]=['answer'=>$a+$b,'expires'=>time()+600];
    verify(login_captcha_validate($id,(string)($a+$b))['valid'],"$a + $b accepted");
    foreach([(string)($a+$b-1),(string)($a+$b+1),'abc',($a+$b).'abc','',str_repeat('0',12).($a+$b)] as $wrong)
        verify(!login_captcha_validate($id,$wrong)['valid'],'Malformed/incorrect rejected');
    verify(login_captcha_validate($id,' '.($a+$b).' ',true)['valid'],'Whitespace normalized');
    verify(!login_captcha_validate($id,(string)($a+$b),true)['valid'],'Replay rejected');
}
$a=login_captcha_generate();$b=login_captcha_generate();
verify(login_captcha_validate($a['challenge_id'],(string)($a['numerator']+$a['denominator']))['valid'],'Tab A independent');
verify(login_captcha_validate($b['challenge_id'],(string)($b['numerator']+$b['denominator']))['valid'],'Tab B independent');
$new=login_captcha_generate($a['challenge_id']);
verify(!login_captcha_validate($a['challenge_id'],'1')['valid'],'Replaced challenge retired');
verify(login_captcha_validate($b['challenge_id'],(string)($b['numerator']+$b['denominator']))['valid'],'Refresh preserves other tab');
$_SESSION['login_captchas'][$new['challenge_id']]['expires']=time()-1;
verify(login_captcha_validate($new['challenge_id'],'1')['code']==='expired','Expiry enforced');
verify(!login_captcha_validate($b['challenge_id'],['answer'=>'1'])['valid'],'Array input rejected');
$mysqlProcess=null;$process=null;$root=sys_get_temp_dir().'/codex-captcha-'.bin2hex(random_bytes(8));$database='codex_captcha_'.bin2hex(random_bytes(5));$db=null;
try {
    mkdir($root,0700,true);mkdir($root.'/sessions',0700,true);
    $source=realpath(__DIR__.'/../..');
    $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);$dbAddress=stream_socket_get_name($socket,false);fclose($socket);$dbPort=(int)substr(strrchr($dbAddress,':'),1);
    $install=proc_open(['C:/xampp/mysql/bin/mysql_install_db.exe','--datadir='.$root.'/mysql','--port='.$dbPort,'--allow-remote-root-access'],[0=>['pipe','r'],1=>['file',$root.'/mysql-install.log','a'],2=>['file',$root.'/mysql-install.log','a']],$installPipes);fclose($installPipes[0]);
    if(proc_close($install)!==0)throw new RuntimeException('Isolated MariaDB initialization failed.');
    $mysqlProcess=proc_open(['C:/xampp/mysql/bin/mysqld.exe','--no-defaults','--basedir=C:/xampp/mysql','--datadir='.$root.'/mysql','--port='.$dbPort,'--bind-address=127.0.0.1','--skip-grant-tables','--innodb-buffer-pool-size=32M'],[0=>['pipe','r'],1=>['file',$root.'/mysql.log','a'],2=>['file',$root.'/mysql.log','a']],$mysqlPipes);fclose($mysqlPipes[0]);
    for($i=0;$i<100;$i++){try{$db=new mysqli('127.0.0.1','root','',null,$dbPort);break;}catch(Throwable $e){usleep(100000);}}
    if(!$db)throw new RuntimeException('Isolated MariaDB startup failed.');
    $db->query('CREATE DATABASE `'.$database.'`');$db->select_db($database);
    $schema=file_get_contents($source.'/onazul_hris_db.sql');
    foreach(['users','roles','employee','system_logs'] as $table) {
        if(!preg_match('/CREATE TABLE `'.preg_quote($table,'/').'` \(.*?\) ENGINE=.*?;/s',$schema,$match))throw new RuntimeException('Missing fixture schema.');
        $db->query($match[0]);
    }
    $db->query('ALTER TABLE users ADD COLUMN LocalPasswordConfigured TINYINT NULL');
    $db->query('ALTER TABLE system_logs ADD PRIMARY KEY(log_id), MODIFY log_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
    function fixture(mysqli $db,string $table,array $values): void {
        foreach($db->query('SHOW COLUMNS FROM `'.$table.'`')->fetch_all(MYSQLI_ASSOC) as $c) {
            if(array_key_exists($c['Field'],$values)||$c['Null']==='YES'||$c['Default']!==null||str_contains($c['Extra'],'auto_increment'))continue;
            $type=strtolower($c['Type']);$values[$c['Field']]=preg_match('/int|decimal|float|double/',$type)?1:(str_starts_with($type,'date')?'2026-10-01':(str_starts_with($type,'enum')?explode("'",$type)[1]:'Synthetic'));
        }
        $stmt=$db->prepare('INSERT INTO `'.$table.'` (`'.implode('`,`',array_keys($values)).'`) VALUES('.implode(',',array_fill(0,count($values),'?')).')');$args=array_values($values);$stmt->bind_param(str_repeat('s',count($args)),...$args);$stmt->execute();
    }
    fixture($db,'roles',['RolesID'=>1,'RoleName'=>'Employee']);
    foreach([1,2] as $id)fixture($db,'users',['UsersID'=>$id,'Email'=>'test'.$id.'@synthetic.invalid','RoleID'=>1,'Password'=>password_hash('Synthetic-Test-42!',PASSWORD_DEFAULT),'IsActive'=>$id===1?1:0]);
    fixture($db,'employee',['EmployeeID'=>1,'EmployeeNumber'=>'TEST0001','Email'=>'test1@synthetic.invalid']);
    $source=realpath(__DIR__.'/../..');
    foreach(['Login/captcha_helper.php','Login/captcha_gen.php','Login/check_captcha.php','Login/login.php','Common/session_init.php','Db_connection/db_connect.php','Helpers/AuditLogger.php','Login/google_login_handler.php'] as $relative) {
        $path=$root.'/Api/'.$relative;if(!is_dir(dirname($path)))mkdir(dirname($path),0700,true);
        $text=file_get_contents($source.'/Api/'.$relative);
        if($relative==='Db_connection/db_connect.php')$text=str_replace("\$dbname = 'onazul_hris_db';","\$dbname = '$database';",$text);
        if($relative==='Db_connection/db_connect.php')$text=str_replace('new mysqli($servername, $username, $password, $dbname)', 'new mysqli($servername, $username, $password, $dbname, '.$dbPort.')',$text);
        if($relative==='Common/session_init.php')$text="<?php\nini_set('session.save_path',".var_export($root.'/sessions',true).");\n".substr($text,5);
        file_put_contents($path,$text);
    }
    $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);$address=stream_socket_get_name($socket,false);fclose($socket);
    $process=proc_open([PHP_BINARY,'-S',$address,'-t',$root],[0=>['pipe','r'],1=>['file',$root.'/server.log','a'],2=>['file',$root.'/server.log','a']],$pipes,$root);fclose($pipes[0]);
    for($i=0;$i<50;$i++){if($probe=@stream_socket_client('tcp://'.$address,$errno,$error,.1)){fclose($probe);break;}usleep(100000);}
    $cookie=$root.'/cookies';
    $http=static function(string $endpoint,array $post=[]) use($address,$cookie): array {
        $curl=curl_init('http://'.$address.'/Api/Login/'.$endpoint);curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_TIMEOUT=>15,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($post),CURLOPT_HTTPHEADER=>['Accept: application/json']]);
        $body=curl_exec($curl);$status=curl_getinfo($curl,CURLINFO_HTTP_CODE);curl_close($curl);$json=json_decode($body,true);if(!is_array($json))throw new RuntimeException($status.': '.$body);return [$status,$json];
    };
    $login=static function(string $user,string $password,?int $wrong=null) use($http): array {
        [, $challenge]=$http('captcha_gen.php');return $http('login.php',['username'=>$user,'password'=>$password,'captcha_challenge_id'=>$challenge['challenge_id'],'captcha_input'=>(string)($wrong ?? $challenge['numerator']+$challenge['denominator'])]);
    };
    [, $a]=$http('captcha_gen.php');[, $b]=$http('captcha_gen.php');
    foreach([$a,$b] as $c){[, $j]=$http('check_captcha.php',['challenge_id'=>$c['challenge_id'],'answer'=>(string)($c['numerator']+$c['denominator'])]);verify($j['valid'],'HTTP independent tabs');}
    [$status,$j]=$login('test1@synthetic.invalid','wrong');verify($status===401,'Correct CAPTCHA proceeds to wrong password rejection');
    [$status,$j]=$login('absent@synthetic.invalid','wrong');verify($status===401,'Nonexistent account rejected');
    [$status,$j]=$login('test2@synthetic.invalid','Synthetic-Test-42!');verify($status===403,'Inactive account rejected');
    [$status,$j]=$login('test1@synthetic.invalid','Synthetic-Test-42!',201);verify($status===400,'Incorrect CAPTCHA blocks correct password');
    $oldCookies=file_get_contents($cookie);[$status,$j]=$login('TEST0001','Synthetic-Test-42!');
    verify($status===200 && $j['success'] && $j['redirect']==='/Employee_Tracking/Html/Employee/employee_dashboard.html','Employee number, password and RBAC redirect');
    verify(file_get_contents($cookie)!==$oldCookies,'Successful login regenerates session');
    [$status,$j]=$http('login.php',['username'=>'test1@synthetic.invalid','password'=>'Synthetic-Test-42!','captcha_challenge_id'=>$a['challenge_id'],'captcha_input'=>(string)($a['numerator']+$a['denominator'])]);verify($status===200 && $j['success'],'Other tab survives secure session regeneration');
    [$status,$j]=$http('login.php',['username'=>'test1@synthetic.invalid','password'=>'Synthetic-Test-42!','captcha_challenge_id'=>$a['challenge_id'],'captcha_input'=>(string)($a['numerator']+$a['denominator'])]);verify($status===400,'HTTP successful challenge replay rejected');
    [$status,$j]=$http('google_login_handler.php');verify($status===400 && $j['message']==='Missing authorization code.','Google path has no arithmetic CAPTCHA requirement');
    echo json_encode(['success'=>true,'passed'=>count($checks),'tests'=>$checks],JSON_PRETTY_PRINT).PHP_EOL;
} finally {
    if(is_resource($process)){proc_terminate($process);proc_close($process);}
    if($db && preg_match('/\Acodex_captcha_[a-f0-9]{10}\z/',$database))$db->query('DROP DATABASE IF EXISTS `'.$database.'`');
    if($db)$db->close();
    if(is_resource($mysqlProcess)){proc_terminate($mysqlProcess);proc_close($mysqlProcess);}
    $resolved=realpath($root);$temp=realpath(sys_get_temp_dir());
    if($resolved && $temp && str_starts_with($resolved,$temp.DIRECTORY_SEPARATOR) && str_starts_with(basename($resolved),'codex-captcha-')) {
        foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($resolved,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST) as $item){if($item->isDir()&&!$item->isLink())rmdir($item->getPathname());else unlink($item->getPathname());}rmdir($resolved);
    }
}
